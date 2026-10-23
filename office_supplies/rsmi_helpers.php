<?php
// Saved reports retain their data even when the source RIS or inventory changes.
function rsmiEnsureTable(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS rsmi_reports (
        rsmi_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        serial_no VARCHAR(40) NULL,
        report_date DATE NOT NULL,
        custodian_name VARCHAR(255) NOT NULL,
        accounting_staff VARCHAR(255) NOT NULL,
        total_issued DECIMAL(18,2) NOT NULL,
        total_amount DECIMAL(18,2) NOT NULL,
        item_count INT UNSIGNED NOT NULL,
        snapshot LONGTEXT NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY rsmi_serial (serial_no),
        KEY rsmi_date (report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function rsmiSourceRows(mysqli $conn): array {
    return $conn->query("SELECT ris.ris_no, ri.stock_number, i.item_name, i.description, i.unit,
        ri.issued_quantity, ri.unit_cost_at_issue AS unit_cost,
        (ri.issued_quantity * ri.unit_cost_at_issue) AS amount,
        ris.date_requested, ris.entity_name, ris.fund_cluster, ris.division,
        ris.responsibility_center_code, ris.office
        FROM ris_items ri JOIN ris ON ri.ris_id = ris.ris_id
        JOIN items i ON ri.stock_number = i.stock_number
        WHERE ri.issued_quantity > 0
        ORDER BY ris.date_requested DESC, ris.ris_id DESC, ri.stock_number ASC")->fetch_all(MYSQLI_ASSOC);
}

function rsmiRecap(array $rows): array {
    $groups = [];
    foreach ($rows as $row) {
        $key = 'stock:' . $row['stock_number'];
        if (!isset($groups[$key])) {
            $groups[$key] = ['stock_number' => $row['stock_number'], 'total_issued' => 0,
                'avg_unit_cost' => 0, 'total_cost' => 0, 'count' => 0,
                'uacs_object_code' => $row['uacs_object_code'] ?? ''];
        }
        $groups[$key]['total_issued'] += (float)$row['issued_quantity'];
        $groups[$key]['avg_unit_cost'] += (float)$row['unit_cost'];
        $groups[$key]['total_cost'] += (float)$row['amount'];
        $groups[$key]['count']++;
    }
    foreach ($groups as &$group) {
        $group['avg_unit_cost'] /= $group['count'];
        unset($group['count']);
    }
    unset($group);
    usort($groups, static fn($a, $b) => strcmp($a['stock_number'], $b['stock_number']));
    return array_values($groups);
}

function rsmiSave(mysqli $conn, array $rows, string $date, string $custodian, string $accounting, int $userId): int {
    if (!$rows) throw new InvalidArgumentException('There are no issued supplies to save.');
    $snapshot = json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $quantity = array_sum(array_column($rows, 'issued_quantity'));
    $amount = array_sum(array_column($rows, 'amount'));
    $count = count($rows);
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('INSERT INTO rsmi_reports (report_date, custodian_name, accounting_staff, total_issued, total_amount, item_count, snapshot, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssddisi', $date, $custodian, $accounting, $quantity, $amount, $count, $snapshot, $userId);
        $stmt->execute();
        $id = (int)$conn->insert_id;
        $stmt->close();
        $serial = 'RSMI-' . substr($date, 0, 4) . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare('UPDATE rsmi_reports SET serial_no = ? WHERE rsmi_id = ?');
        $stmt->bind_param('si', $serial, $id);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        return $id;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function rsmiLoad(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare('SELECT * FROM rsmi_reports WHERE rsmi_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$report) return null;
    $report['rows'] = json_decode($report['snapshot'], true, 512, JSON_THROW_ON_ERROR);
    return $report;
}

function rsmiH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
