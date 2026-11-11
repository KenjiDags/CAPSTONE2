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
        'RSMI' => ['rsmi_reports', 'rsmi_id', 'serial_no', []],
        'RPCI' => ['rpci_reports', 'rpci_id', 'rpci_id', []],
        'RPCSP' => ['rpcsp_reports', 'rpcsp_id', 'rpcsp_id', []],
        'RPCPPE' => ['rpcppe', 'rpcppe_id', 'rpcppe_id', ['rpcppe_items' => 'rpcppe_id']],
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
        'PPE' => ['ppe_property', 'id', 'PPE_no', []],
        'Semi-expendable' => ['semi_expendable_property', 'id', 'semi_expendable_property_no', []],
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

// Other inventory pages include this file only for the archive functions.
// Run the Archive page controller and markup only for a direct request.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}


require 'auth.php';
require 'config.php';


ensureArchiveTable($conn);

if (empty($_SESSION['archive_csrf'])) {
    $_SESSION['archive_csrf'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    if (
        !hash_equals(
            $_SESSION['archive_csrf'],
            (string) ($_POST['csrf'] ?? '')
        )
    ) {
        http_response_code(403);
        exit('Invalid request. Reload Archive and try again.');
    }

    try {
        $action = (string) ($_POST['action'] ?? '');

        archiveAction(
            $conn,
            (int) ($_POST['archive_id'] ?? 0),
            $action
        );

        if ($action === 'restore') {
            require_once 'inventory_count_cache.php';
            invalidateInventoryCount();
        }

        $_SESSION['archive_notice'] = [
            'success',
            $action === 'restore'
                ? 'Record restored successfully.'
                : 'Archived record permanently deleted.'
        ];

    } catch (Throwable $error) {

        error_log(
            'Archive action failed: ' . $error->getMessage()
        );

        $_SESSION['archive_notice'] = [
            'error',
            'Unable to complete the action. The archive was kept. ' .
            'A conflicting live record or missing related record may ' .
            'prevent restoration.'
        ];
    }

    header(
        'Location: archive.php?' .
        http_build_query([
            'tab' => ($_POST['tab'] ?? '') === 'items' ? 'items' : 'documents',
            'search' => (string) ($_POST['search'] ?? ''),
            'type' => (string) ($_POST['type'] ?? '')
        ])
    );

    exit;
}

$notice = $_SESSION['archive_notice'] ?? null;
unset($_SESSION['archive_notice']);

function archiveEscape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$search = trim((string) ($_GET['search'] ?? ''));
$type = (string) ($_GET['type'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
$itemTypes = ['Supply', 'PPE', 'Semi-expendable', 'ICT'];
$tab = ($_GET['tab'] ?? '') === 'items' || (!isset($_GET['tab']) && in_array($type, $itemTypes, true)) ? 'items' : 'documents';
$itemTypeLabels = ['Supply' => 'Office Supplies', 'PPE' => 'PPE', 'Semi-expendable' => 'Semi-Expendables', 'ICT' => 'ICT'];
function archiveItemName(array $record): string {
    $snapshot = json_decode($record['snapshot'] ?? '{}', true);
    $source = archiveSources()[$record['record_type']] ?? null;
    $row = $source ? ($snapshot[$source[0]][0] ?? []) : [];
    return (string)($row['item_name'] ?? $row['item_description'] ?? $row['description'] ?? $record['record_label']);
}

$detail = null;

if ($id > 0) {

    $stmt = $conn->prepare(
        'SELECT * FROM deleted_records WHERE archive_id = ?'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    $detail = $stmt
        ->get_result()
        ->fetch_assoc();

    if (!$detail) {
        http_response_code(404);
    }
}

if ($detail) {
    $tab = in_array($detail['record_type'], $itemTypes, true) ? 'items' : 'documents';
}
$isItems = $tab === 'items';
$recordTypes = $isItems ? $itemTypes : ['SC', 'RIS', 'RSMI', 'RPCI', 'RPCSP', 'RPCPPE', 'ICS', 'ITR', 'PTR', 'PAR', 'PC', 'IIRUP', 'RRSP', 'IIRUSP'];
if (!in_array($type, $recordTypes, true)) $type = '';
$like = '%' . $search . '%';
$groupCondition = $isItems
    ? "record_type IN ('Supply', 'PPE', 'Semi-expendable', 'ICT')"
    : "record_type NOT IN ('Supply', 'PPE', 'Semi-expendable', 'ICT')";
$itemSearch = $isItems ? ' OR snapshot LIKE ?' : '';
$stmt = $conn->prepare("SELECT archive_id, record_type, record_label, snapshot, deleted_at
    FROM deleted_records
    WHERE $groupCondition AND (record_label LIKE ? OR record_type LIKE ? $itemSearch)
        AND (? = '' OR record_type = ? OR (? = 'RSMI' AND record_type = 'RIS'))
    ORDER BY deleted_at DESC, archive_id DESC LIMIT 200");
if ($isItems) {
    $stmt->bind_param('ssssss', $like, $like, $like, $type, $type, $type);
} else {
    $stmt->bind_param('sssss', $like, $like, $type, $type, $type);
}
$stmt->execute();
$records = $stmt->get_result();
?>

<!doctype html>
<html data-ppe-styles lang="en">
<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Archive - TESDA Inventory</title>
    <link rel="stylesheet" href="css/styles.css">


    <style>

        .archive-actions-menu {
            min-width: 185px;
        }

        .archive-actions-menu form {
            margin: 0;
        }

        .archive-actions-menu a,
        .archive-actions-menu button {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 8px;
            width: 100%;
            margin: 0 !important;
            padding: 8px !important;
            border: 0 !important;
            border-radius: 4px !important;
            background: transparent !important;
            color: #374151 !important;
            font: inherit;
            font-size: 12px !important;
            font-weight: 400 !important;
            text-align: left;
            text-decoration: none;
            cursor: pointer;
            transform: none !important;
            box-shadow: none !important;
            transition: none !important;
        }

        .archive-actions-menu .delete-action {
            color: #dc2626 !important;
        }

        body.dark-mode .archive-actions-menu a,
        body.dark-mode .archive-actions-menu button {
            color: #e2e8f0 !important;
        }

        body.dark-mode .archive-actions-menu .delete-action {
            color: #fca5a5 !important;
        }

        .archive-actions-menu :focus-visible {
            outline: 2px solid #3b82f6;
            outline-offset: -2px;
        }

        .archive-notice {
            margin: 20px;
            padding: 12px 16px;
            border: 1px solid #86b89b;
            border-radius: 8px;
            color: #166534;
            background: #edf7f0;
        }

        .archive-notice.error {
            color: #991b1b;
            background: #fef2f2;
            border-color: #fca5a5;
        }

        .archive-list table tbody tr,
        .archive-list table tbody tr:hover {
            background: #fff;
            transform: none !important;
            box-shadow: none !important;
            transition: none !important;
        }

        .archive-list table tbody tr:nth-child(even),
        .archive-list table tbody tr:nth-child(even):hover {
            background: #f8fbff;
        }

        body.dark-mode .container .archive-list table tbody tr td,
        body.dark-mode .container .archive-list table tbody tr:hover td {
            background: #1e293b !important;
        }

        .archive-intro {
            color: #64748b;
            margin: 0 20px 20px;
        }

        .archive-detail {
            margin: 20px;
            padding: 24px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #fff;
            color: #1e293b;
            min-width: 0;
        }

        .archive-detail h3 {
            margin: 16px 0 8px;
            overflow-wrap: anywhere;
        }

        .archive-back {
            display: inline-block;
            color: #1d4ed8;
            font-weight: 700;
        }

        .archive-detail details {
            border-top: 1px solid #cbd5e1;
            margin-top: 20px;
        }

        .archive-record {
            margin: 12px 0;
            padding: 18px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            min-width: 0;
        }

        .archive-record h4 {
            margin: 0 0 14px;
        }

        .archive-fields {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(min(100%, 240px), 1fr)
                );
            gap: 18px 24px;
            margin: 0;
        }

        .archive-field {
            min-width: 0;
        }

        .archive-fields dt {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .archive-fields dd {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        body.dark-mode .archive-detail {
            background: #1e293b;
            color: #e2e8f0;
            border-color: #475569;
        }

        body.dark-mode .archive-record {
            background: #0f172a;
            border-color: #475569;
        }

        body.dark-mode .archive-fields dt {
            color: #cbd5e1;
        }

        body.dark-mode .archive-back {
            color: #93c5fd;
        }

        .archive-filters #recordType { padding-right: 44px; min-width: 160px; box-sizing: border-box; }

        .archive-filters .control-search {
            flex: 0 1 700px !important;
        }

        .archive-filters .control-search input {
            width: 100%;
            box-sizing: border-box;
        }

        @media (max-width: 600px) {

            .archive-detail {
                margin: 12px 0;
                padding: 14px;
            }

            .archive-record {
                padding: 12px;
            }
        }

        .archive-detail summary {
            cursor: pointer;
            font-weight: 700;
            padding: 12px 0;
        }

        @media (max-width: 800px) {
            .archive-filters .inventory-controls { flex-wrap: wrap; }
            .archive-filters #recordType { padding-right: 44px; min-width: 160px; box-sizing: border-box; }

        .archive-filters .control-search { min-width: 0; padding-left: 0; }
        }
        .archive-page-header { margin-bottom: 24px; padding: 20px; border: 1px solid rgba(59,130,246,.2); border-radius: 12px; background: linear-gradient(135deg,rgba(255,255,255,.95),rgba(240,249,255,.95)); box-shadow: 0 4px 12px #0000001a; }
        .container .archive-page-header h2, body.dark-mode .container .archive-page-header h2 { margin: 0 0 8px !important; padding: 0 !important; border: 0 !important; border-radius: 0 !important; background: transparent !important; box-shadow: none !important; backdrop-filter: none !important; }
        .archive-page-header .archive-intro { margin: 0; }
        body.dark-mode .archive-page-header { background: #1e293b; border-color: #334155; }
        body.dark-mode .archive-page-header .archive-intro { color: #cbd5e1; }
    </style>

</head>

<body>

    <?php include 'sidebar.php'; ?>

    <main class="container">

        <header class="archive-page-header">
        <h2><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="4" rx="1"></rect><path d="M5 7v13h14V7M10 11h4"></path></svg> Archive</h2>
        <p class="archive-intro">Review archived documents and items, restore records you still need, or permanently delete them.</p>
        </header>
        <nav class="category-tabs" aria-label="Archive category">
            <a class="category-tab <?= !$isItems ? 'active' : '' ?>" href="archive.php?tab=documents"<?= !$isItems ? ' aria-current="page"' : '' ?>>Documents</a>
            <a class="category-tab <?= $isItems ? 'active' : '' ?>" href="archive.php?tab=items"<?= $isItems ? ' aria-current="page"' : '' ?>>Items</a>
        </nav>

        <?php if ($notice): ?>

            <p class="archive-notice <?= archiveEscape($notice[0]) ?>" role="status">
                <?= archiveEscape($notice[1]) ?>
            </p>

        <?php endif; ?>


        <?php if (!$detail): ?>

        <form
            id="archive-filters"
            method="get"
            class="filters archive-filters"
        >

            <input type="hidden" name="tab" value="<?= archiveEscape($tab) ?>">
            <div class="inventory-controls">

                <!-- Record Type Filter -->
                <div class="control-sort">

                    <label for="recordType">
                        <?= $isItems ? 'Item category:' : 'Record Type:' ?>
                    </label>

                    <select
                        id="recordType"
                        name="type"
                    >

                        <option value="">
                            <?= $isItems ? 'All Items' : 'All Types' ?>
                        </option>

                        <?php foreach ($recordTypes as $option): ?>

                            <option
                                value="<?= archiveEscape($option) ?>"
                                <?= $type === $option ? 'selected' : '' ?>
                            >
                                <?= archiveEscape($isItems ? $itemTypeLabels[$option] : $option) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Search -->
                <div class="control-search">

                    <label for="archiveSearch">
                        <i class="fas fa-search"></i>
                        Search:
                    </label>

                    <input
                        type="text"
                        id="archiveSearch"
                        name="search"
                        value="<?= archiveEscape($search) ?>"
                        placeholder="<?= $isItems ? 'Search by item name, description, or property number...' : 'Search by record number or type...' ?>"
                    >

                </div>

            </div>

        </form>

        <?php endif; ?>


        <?php if ($id && !$detail): ?>

            <p class="archive-intro">
                Archived record not found.
            </p>

        <?php endif; ?>


        <?php if ($detail): ?>

            <section
                class="archive-detail"
                aria-label="Archived record details"
            >

                <a
                    class="archive-back"
                    href="archive.php?<?= archiveEscape(
                        http_build_query([
                            'tab' => $tab,
                            'search' => $search,
                            'type' => $type
                        ])
                    ) ?>"
                >
                    &larr; Back to Archive
                </a>


                <h3>
                    <?= archiveEscape(
                        $detail['record_type'] .
                        ' — ' .
                        $detail['record_label']
                    ) ?>
                </h3>


                <p>
                    Deleted
                    <?= archiveEscape($detail['deleted_at']) ?>
                </p>


                <?php
                $snapshot = json_decode(
                    $detail['snapshot'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
                ?>


                <?php foreach ($snapshot as $table => $rows): ?>

                    <details open>

                        <summary>
                            <?= archiveEscape(
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $table
                                    )
                                )
                            ) ?>

                            (<?= count($rows) ?>)
                        </summary>


                        <?php if (!$rows): ?>

                            <p>
                                No related entries.
                            </p>

                        <?php else: ?>

                            <?php foreach ($rows as $index => $row): ?>

                                <article
                                    class="archive-record"
                                    aria-label="Entry <?= $index + 1 ?>"
                                >

                                    <?php if (count($rows) > 1): ?>

                                        <h4>
                                            Entry <?= $index + 1 ?>
                                        </h4>

                                    <?php endif; ?>


                                    <dl class="archive-fields">

                                        <?php foreach ($row as $column => $value): ?>

                                            <div class="archive-field">

                                                <dt>
                                                    <?= archiveEscape(
                                                        ucwords(
                                                            str_replace(
                                                                '_',
                                                                ' ',
                                                                $column
                                                            )
                                                        )
                                                    ) ?>
                                                </dt>

                                                <dd>
                                                    <?=
                                                        $value === null || $value === ''
                                                            ? '&mdash;'
                                                            : archiveEscape($value)
                                                    ?>
                                                </dd>

                                            </div>

                                        <?php endforeach; ?>

                                    </dl>

                                </article>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </details>

                <?php endforeach; ?>

            </section>

        <?php endif; ?>


        <?php if (!$detail): ?>

            <div class="table-container archive-list">

                <table>

                    <thead>

                        <tr>
                            <th>Type</th>
                            <th><?= $isItems ? 'Property / Stock number' : 'Record' ?></th>
                            <?php if ($isItems): ?><th>Item</th><?php endif; ?>
                            <th>Deleted at</th>
                            <th>Action</th>
                        </tr>

                    </thead>


                    <tbody>

                        <?php if (!$records->num_rows): ?>

                            <tr>
                                <td colspan="<?= $isItems ? 5 : 4 ?>">
                                    <?= $isItems ? 'No archived items found.' : 'No archived documents found.' ?>
                                </td>
                            </tr>

                        <?php endif; ?>


                        <?php foreach ($records as $record): ?>

                            <tr>

                                <td>
                                    <?= archiveEscape(
                                        $isItems ? ($itemTypeLabels[$record['record_type']] ?? $record['record_type']) : $record['record_type']
                                    ) ?>
                                </td>


                                <td>
                                    <?= archiveEscape(
                                        $record['record_label']
                                    ) ?>
                                </td>


                                <?php if ($isItems): ?>
                                    <td><?= archiveEscape(archiveItemName($record)) ?></td>
                                <?php endif; ?>
                                <td>
                                    <?= archiveEscape(
                                        $record['deleted_at']
                                    ) ?>
                                </td>


                                <td class="actions-cell">

                                    <div class="actions-menu">

                                        <button
                                            type="button"
                                            class="actions-menu-toggle"
                                            aria-label="Actions for <?= archiveEscape(
                                                $record['record_label']
                                            ) ?>"
                                            aria-expanded="false"
                                            aria-controls="archive-actions-<?= (int) $record['archive_id'] ?>"
                                        >

                                            <i
                                                class="fas fa-ellipsis-v"
                                                aria-hidden="true"
                                            ></i>

                                        </button>


                                        <div
                                            class="actions-menu-list archive-actions-menu"
                                            id="archive-actions-<?= (int) $record['archive_id'] ?>"
                                        >

                                            <a
                                                href="archive.php?<?= archiveEscape(
                                                    http_build_query([
                                                        'id' => $record['archive_id'],
                                                        'tab' => $tab,
                                                        'search' => $search,
                                                        'type' => $type
                                                    ])
                                                ) ?>"
                                            >

                                                <i
                                                    class="fas fa-eye"
                                                    aria-hidden="true"
                                                ></i>

                                                View

                                            </a>


                                            <?php
                                            $archiveActions = [
                                                'delete' => 'Delete permanently',
                                                'restore' => 'Restore'
                                            ];
                                            ?>


                                            <?php foreach (
                                                $archiveActions
                                                as $action => $label
                                            ): ?>

                                                <form
                                                    method="post"
                                                    action="archive.php"
                                                    data-archive-action="<?= $action ?>"
                                                >

                                                    <input type="hidden" name="tab" value="<?= archiveEscape($tab) ?>">
                                                    <input
                                                        type="hidden"
                                                        name="csrf"
                                                        value="<?= archiveEscape(
                                                            $_SESSION['archive_csrf']
                                                        ) ?>"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="archive_id"
                                                        value="<?= (int) $record['archive_id'] ?>"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="<?= $action ?>"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="search"
                                                        value="<?= archiveEscape($search) ?>"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="type"
                                                        value="<?= archiveEscape($type) ?>"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="<?= $action === 'delete'
                                                            ? 'delete-action'
                                                            : 'restore-action' ?>"
                                                    >

                                                        <i
                                                            class="fas <?= $action === 'delete'
                                                                ? 'fa-trash'
                                                                : 'fa-undo' ?>"
                                                            aria-hidden="true"
                                                        ></i>

                                                        <?= $label ?>

                                                    </button>

                                                </form>

                                            <?php endforeach; ?>

                                        </div>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <p class="archive-intro">
                Showing up to 200 matching records.
                Use search to narrow results.
                Records deleted before archiving was enabled
                may not be available.
            </p>

        <?php endif; ?>

    </main>


    <script src="js/actions_menu.js"></script>


    <script>

        document.addEventListener('submit', function (event) {

            const form = event.target.closest(
                '[data-archive-action]'
            );

            if (!form) {
                return;
            }

            const message =
                form.dataset.archiveAction === 'delete'
                    ? 'Permanently delete this archived record? This cannot be undone.'
                    : 'Restore this record and its related entries?';

            if (!confirm(message)) {
                event.preventDefault();
            }

        });


        document.addEventListener('keydown', function (event) {

            if (event.key !== 'Escape') {
                return;
            }

            const toggle = document.querySelector(
                '.archive-list .actions-menu.is-open .actions-menu-toggle'
            );

            if (toggle) {
                toggle.click();
                toggle.focus();
            }

        });

    </script>

    <script>
        const archiveForm = document.getElementById('archive-filters');
        const recordType = document.getElementById('recordType');
        const searchInput = document.getElementById('archiveSearch');

        if (recordType) {
            recordType.addEventListener('change', function () {
                archiveForm.submit();
            });
        }

        if (searchInput) {
            let searchTimeout;

            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);

                searchTimeout = setTimeout(function () {
                    archiveForm.submit();
                }, 500);
            });
        }
    </script>



</body>

</html>