<?php
// Archive snapshots are independent of live records and survive cascading deletes.
function ensureArchiveTable(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS deleted_records (
        archive_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        record_type VARCHAR(40) NOT NULL,
        record_label VARCHAR(255) NOT NULL,
        source_id INT NOT NULL,
        snapshot LONGTEXT NOT NULL,
        deleted_by INT NOT NULL,
        deleted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX archive_date (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function archiveRows(mysqli $conn, string $table, string $key, int $id): array {
    // Identifiers come exclusively from the internal mapping below or database metadata.
    foreach ([$table, $key] as $identifier) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) throw new RuntimeException('Invalid archive source.');
    }
    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$key` = ? FOR UPDATE");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function archiveSources(): array {
    return [
        'RIS' => ['ris', 'ris_id', 'ris_no', ['ris_items' => 'ris_id', 'item_history' => 'ris_id']],
        'SC' => ['items', 'item_id', 'stock_number', ['item_history' => 'item_id']],
        'Supply' => ['items', 'item_id', 'stock_number', ['item_history' => 'item_id', 'inventory_entries' => 'item_id']],
        'ICS' => ['ics', 'ics_id', 'ics_no', ['ics_items' => 'ics_id']],
        'ITR' => ['itr', 'itr_id', 'itr_no', ['itr_items' => 'itr_id']],
        'PTR' => ['ppe_ptr', 'ptr_id', 'ptr_no', ['ppe_ptr_items' => 'ptr_id']],
        'PAR' => ['ppe_par', 'par_id', 'par_no', ['ppe_par_items' => 'par_id']],
        'IIRUP' => ['ppe_iirup', 'id', 'iirup_no', ['ppe_iirup_items' => 'ppe_iirup_id']],
        'PC' => ['item_history_ppe', 'id', 'PPE_no', []],
        'RRSP' => ['rrsp', 'rrsp_id', 'rrsp_no', ['rrsp_items' => 'rrsp_id']],
        'IIRUSP' => ['iirusp', 'iirusp_id', 'iirusp_no', ['iirusp_items' => 'iirusp_id']],
        'PPE' => ['ppe_property', 'id', 'property_no', []],
        'Semi-expendable' => ['semi_expendable_property', 'id', 'property_no', []],
        'ICT' => ['ict_registry', 'id', 'property_no', []],
    ];
}

function archiveRecord(mysqli $conn, string $type, int $id, bool $delete = true): void {
    $sources = archiveSources();
    if (!isset($sources[$type])) throw new InvalidArgumentException('Unknown archive type.');
    ensureArchiveTable($conn); // DDL must run before the transaction.
    [$table, $key, $labelKey, $children] = $sources[$type];
    archiveTransactionalTables($conn, array_merge([$table, 'deleted_records'], array_keys($children), $type === 'SC' ? ['item_history_archive'] : []));
    $conn->begin_transaction();
    try {
        $rows = archiveRows($conn, $table, $key, $id);
        if (!$rows) throw new RuntimeException('Record no longer exists.');
        $snapshot = [$table => $rows];
        if ($type === 'PC') {
            $stmt = $conn->prepare('SELECT * FROM item_history_ppe WHERE PPE_no = ? FOR UPDATE');
            $stmt->bind_param('s', $rows[0]['PPE_no']);
            $stmt->execute();
            $snapshot[$table] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        foreach ($children as $child => $foreignKey) {
            $snapshot[$child] = archiveRows($conn, $child, $foreignKey, $id);
        }
        $historySources = [
            'Supply' => ['item_history_archive' => 'item_id', 'stock_card' => 'item_id'],
            'Semi-expendable' => ['semi_expendable_history' => 'semi_id'],
        ];
        foreach ($historySources[$type] ?? [] as $child => $foreignKey) {
            $exists = $conn->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $exists->bind_param('s', $child);
            $exists->execute();
            if ($exists->get_result()->num_rows) $snapshot[$child] = archiveRows($conn, $child, $foreignKey, $id);
        }
        // Capture other FK-linked records before the database cascades their deletion.
        $stmt = $conn->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?');
        $stmt->bind_param('ss', $table, $key);
        $stmt->execute();
        foreach ($stmt->get_result() as $relation) {
            $child = $relation['TABLE_NAME'];
            if (!isset($snapshot[$child])) $snapshot[$child] = archiveRows($conn, $child, $relation['COLUMN_NAME'], $id);
        }
        if ($type === 'RIS') {
            $stmt = $conn->prepare('SELECT r.ris_no, ri.*, i.item_name, i.description, i.unit FROM ris_items ri JOIN ris r ON r.ris_id = ri.ris_id LEFT JOIN items i ON i.stock_number = ri.stock_number WHERE ri.ris_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $snapshot['RSMI'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $label = (string)($rows[0][$labelKey] ?? ($type . ' #' . $id));
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $user = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $conn->prepare('INSERT INTO deleted_records (record_type, record_label, source_id, snapshot, deleted_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('ssisi', $type, $label, $id, $json, $user);
        if (!$stmt->execute()) throw new RuntimeException('Unable to save archive.');
        if ($delete) {
            if ($type === 'PC') {
                $stmt = $conn->prepare('DELETE FROM item_history_ppe WHERE PPE_no = ?');
                $stmt->bind_param('s', $rows[0]['PPE_no']);
                if (!$stmt->execute()) throw new RuntimeException('Unable to clear property card.');
            }
            foreach ($children as $child => $foreignKey) {
                if (!$conn->query("DELETE FROM `$child` WHERE `$foreignKey` = " . (int)$id)) throw new RuntimeException('Unable to delete related records.');
            }
            if (!$conn->query("DELETE FROM `$table` WHERE `$key` = " . (int)$id)) throw new RuntimeException('Unable to delete record.');
        } else {
            // Clearing a stock card preserves the inventory item and its current balance.
            if (!$conn->query('INSERT INTO item_history_archive SELECT * FROM item_history WHERE item_id = ' . (int)$id)) throw new RuntimeException('Unable to preserve stock card undo history.');
            if (!$conn->query('DELETE FROM item_history WHERE item_id = ' . (int)$id)) throw new RuntimeException('Unable to clear stock card.');
        }
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function archiveIdentifier(string $name): string {
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name)) throw new RuntimeException('Invalid archive field.');
    return '`' . $name . '`';
}

function archiveExecute(mysqli $conn, string $sql, array $values = []): mysqli_stmt {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('Unable to prepare archive operation.');
    if ($values) $stmt->bind_param(str_repeat('s', count($values)), ...$values);
    if (!$stmt->execute()) throw new RuntimeException('Unable to complete archive operation.');
    return $stmt;
}

function archiveTransactionalTables(mysqli $conn, array $tables): void {
    // Older installations used MyISAM for ICS and semi-expendables. Upgrade
    // before starting a transaction so failed restores cannot leave partial rows.
    foreach (array_unique($tables) as $table) {
        $row = archiveExecute($conn, 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])->get_result()->fetch_assoc();
        if (!$row) throw new RuntimeException('A required record table is missing.');
        if ($row['ENGINE'] !== 'InnoDB' && !$conn->query('ALTER TABLE ' . archiveIdentifier($table) . ' ENGINE=InnoDB')) {
            throw new RuntimeException('Unable to enable transactional archive storage.');
        }
    }
}

function archiveRestoreRows(mysqli $conn, string $table, array $rows): void {
    $name = archiveIdentifier($table);
    $columns = $conn->query("SHOW COLUMNS FROM $name")->fetch_all(MYSQLI_ASSOC);
    $known = array_column($columns, 'Field');
    $primary = array_column(array_filter($columns, static fn($column) => $column['Key'] === 'PRI'), 'Field');
    $occurrences = [];
    foreach ($rows as $row) {
        if (!$row || array_diff(array_keys($row), $known)) throw new RuntimeException('Archived fields do not match the current table.');
        $keys = $primary ?: array_keys($row);
        if (array_diff($keys, array_keys($row))) throw new RuntimeException('Archived record is missing its key.');
        $where = implode(' AND ', array_map(static fn($key) => archiveIdentifier($key) . ' <=> ?', $keys));
        $existing = archiveExecute($conn, "SELECT * FROM $name WHERE $where FOR UPDATE", array_map(static fn($key) => $row[$key], $keys))->get_result()->fetch_all(MYSQLI_ASSOC);
        if ($primary && $existing) {
            foreach ($row as $key => $value) {
                if (($value === null) !== ($existing[0][$key] === null) || (string)$value !== (string)$existing[0][$key]) {
                    throw new RuntimeException('A live record conflicts with this archive. Nothing was restored.');
                }
            }
            continue;
        }
        if (!$primary) {
            $signature = json_encode($row, JSON_THROW_ON_ERROR);
            $occurrences[$signature] = ($occurrences[$signature] ?? 0) + 1;
            if (count($existing) >= $occurrences[$signature]) continue;
        }
        $fields = implode(', ', array_map('archiveIdentifier', array_keys($row)));
        $placeholders = implode(', ', array_fill(0, count($row), '?'));
        archiveExecute($conn, "INSERT INTO $name ($fields) VALUES ($placeholders)", array_values($row));
    }
}

function archiveAction(mysqli $conn, int $id, string $action): void {
    if (!in_array($action, ['restore', 'delete'], true)) throw new InvalidArgumentException('Invalid archive action.');
    $record = archiveExecute($conn, 'SELECT * FROM deleted_records WHERE archive_id = ?', [$id])->get_result()->fetch_assoc();
    if (!$record) throw new RuntimeException('This archived record is no longer available.');
    $source = archiveSources()[$record['record_type']] ?? null;
    if (!$source) throw new RuntimeException('Unsupported archive record type.');
    [$parent, $key, $label, $children] = $source;
    $snapshot = json_decode($record['snapshot'], true, 512, JSON_THROW_ON_ERROR);
    if (empty($snapshot[$parent])) throw new RuntimeException('The archived record is incomplete.');
    $allowed = array_merge([$parent], array_keys($children), ['item_history_archive', 'stock_card', 'semi_expendable_history']);
    $relations = archiveExecute($conn, 'SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?', [$parent, $key])->get_result();
    foreach ($relations as $relation) $allowed[] = $relation['TABLE_NAME'];
    unset($snapshot['RSMI']); // RSMI is generated from restored RIS entries.
    if (array_diff(array_keys($snapshot), $allowed)) throw new RuntimeException('The archive contains unsupported related records.');
    $isSC = $record['record_type'] === 'SC';
    $restore = $isSC ? ['item_history' => $snapshot['item_history'] ?? []] : $snapshot;
    archiveTransactionalTables($conn, array_merge(['deleted_records'], $action === 'restore' ? array_keys($restore) : [], $isSC ? ['item_history_archive'] : []));
    $conn->begin_transaction();
    try {
        $locked = archiveExecute($conn, 'SELECT snapshot FROM deleted_records WHERE archive_id = ? FOR UPDATE', [$id])->get_result()->fetch_assoc();
        if (!$locked || $locked['snapshot'] !== $record['snapshot']) throw new RuntimeException('This archive changed. Reload the page and try again.');
        if ($action === 'restore') {
            if ($isSC) {
                $live = archiveRows($conn, $parent, $key, (int)$record['source_id']);
                if (!$live || $live[0][$label] !== $snapshot[$parent][0][$label]) throw new RuntimeException('Restore the original supply item before its stock card history.');
            }
            // Snapshot order starts with the parent, then its dependent rows.
            $parentExisted = !$isSC && (bool)archiveRows($conn, $parent, $key, (int)$record['source_id']);
            foreach ($restore as $table => $rows) {
                if ($table === 'item_stockouts' && $parent === 'items' && !$parentExisted) {
                    // The item INSERT trigger creates a new episode; use the archived episode instead.
                    archiveExecute($conn, 'DELETE FROM item_stockouts WHERE item_id = ?', [$record['source_id']]);
                }
                archiveRestoreRows($conn, $table, $rows);
            }
        }
        if ($isSC) {
            // Remove only this snapshot's legacy undo copies, leaving other archives intact.
            foreach ($snapshot['item_history'] ?? [] as $row) {
                archiveExecute($conn, 'DELETE FROM item_history_archive WHERE history_id = ? AND item_id = ?', [$row['history_id'], $row['item_id']]);
            }
        }
        archiveExecute($conn, 'DELETE FROM deleted_records WHERE archive_id = ?', [$id]);
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}
