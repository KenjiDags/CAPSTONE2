<?php
// Saved RPCI reports retain the inventory and form values used when they were created.
function rpciSchema(): string {
    return "CREATE TABLE IF NOT EXISTS rpci_reports (
        rpci_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        report_date DATE NOT NULL,
        entity_name VARCHAR(255) NOT NULL,
        accountable_officer VARCHAR(255) NOT NULL,
        item_count INT UNSIGNED NOT NULL,
        snapshot LONGTEXT NOT NULL,
        created_by INT NOT NULL,
        save_token CHAR(64) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY rpci_save_token (save_token),
        KEY rpci_date (report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}
function rpciEnsureTable(mysqli $conn): void { $conn->query(rpciSchema()); }
function rpciH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function rpciSourceRows(mysqli $conn): array {
    return $conn->query('SELECT item_id, item_name, description, stock_number, unit, unit_cost, COALESCE(calculated_quantity, quantity_on_hand, 0) AS balance_per_card FROM items ORDER BY item_name, item_id')->fetch_all(MYSQLI_ASSOC);
}
function rpciMetadata(array $input): array {
    $data = [];
    foreach (['report_date', 'fund_cluster', 'accountable_officer', 'official_designation', 'entity_name', 'assumption_date', 'signature_name_1', 'signature_name_2', 'signature_name_3'] as $field) {
        if (isset($input[$field]) && !is_string($input[$field])) throw new InvalidArgumentException('Invalid report field.');
        $data[$field] = trim($input[$field] ?? '');
        if (strlen($data[$field]) > 255) throw new InvalidArgumentException('Report fields must be 255 characters or fewer.');
    }
    foreach (['report_date', 'assumption_date'] as $field) {
        if ($field === 'assumption_date' && $data[$field] === '') continue;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data[$field]);
        if (!$date || $date->format('Y-m-d') !== $data[$field]) throw new InvalidArgumentException('Enter a valid report and assumption date.');
    }
    return $data;
}
function rpciApplyCounts(array $rows, array $input): array {
    $encoded = $input['row_data'] ?? '{}';
    if (!is_string($encoded)) throw new InvalidArgumentException('Invalid item counts.');
    try { $counts = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $error) { throw new InvalidArgumentException('Invalid item counts.'); }
    if (!is_array($counts)) throw new InvalidArgumentException('Invalid item counts.');
    foreach ($rows as &$row) {
        $values = $counts[(string)$row['item_id']] ?? [];
        if (!is_array($values)) throw new InvalidArgumentException('Invalid item counts.');
        foreach (['balance_per_card', 'on_hand_per_count', 'shortage_quantity', 'shortage_value', 'remarks'] as $field) {
            $value = $field === 'balance_per_card' ? (string)($row['balance_per_card'] ?? '0') : ($values[$field] ?? '');
            if (!is_string($value)) throw new InvalidArgumentException('Invalid item value.');
            $value = trim($value);
            if ($field === 'remarks') {
                if (strlen($value) > 1000) throw new InvalidArgumentException('Remarks must be 1000 characters or fewer.');
            } elseif ($value !== '') {
                $pattern = $field === 'shortage_value' ? '/^-?\d{1,12}(\.\d{1,2})?$/D' : '/^-?\d{1,12}$/D';
                if (!preg_match($pattern, $value) || (in_array($field, ['balance_per_card', 'on_hand_per_count'], true) && (float)$value < 0)) {
                    throw new InvalidArgumentException('Enter valid quantities and values for each item.');
                }
            }
            $row[$field] = $value;
        }
    }
    unset($row);
    return $rows;
}
function rpciSave(mysqli $conn, array $input, int $userId, string $token): int {
    $metadata = rpciMetadata($input);
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new InvalidArgumentException('Invalid save token.');
    $rows = rpciApplyCounts(rpciSourceRows($conn), $input);
    if (!$rows) throw new InvalidArgumentException('There are no inventory items to save.');
    $snapshot = json_encode(['metadata' => $metadata, 'rows' => $rows], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $count = count($rows);
    // A retry of the same submission returns its original document instead of saving a duplicate.
    $stmt = $conn->prepare('INSERT INTO rpci_reports (report_date, entity_name, accountable_officer, item_count, snapshot, created_by, save_token) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE rpci_id = LAST_INSERT_ID(rpci_id)');
    $stmt->bind_param('sssisis', $metadata['report_date'], $metadata['entity_name'], $metadata['accountable_officer'], $count, $snapshot, $userId, $token);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}
function rpciLoad(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare('SELECT * FROM rpci_reports WHERE rpci_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$report) return null;
    $report['data'] = json_decode($report['snapshot'], true, 512, JSON_THROW_ON_ERROR);
    return $report;
}