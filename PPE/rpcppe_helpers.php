<?php
function rpcppeNumber(array $data, string $field, int $id): string {
    $values = $data[$field] ?? [];
    $value = is_array($values) ? ($values[$id] ?? '') : '';
    if (!is_scalar($value)) throw new InvalidArgumentException('Invalid physical count value.');
    $value = trim((string)$value);
    if ($value === '') return '';
    $integer = $field !== 'shortage_value';
    if (!is_numeric($value) || !is_finite((float)$value) || ($integer && !preg_match('/^-?\d+$/D', $value)) || abs((float)$value) > ($integer ? 2147483647 : 99999999.99) || ($field === 'on_hand_count' && (float)$value < 0)) {
        throw new InvalidArgumentException('Enter valid physical counts and shortage/overage values.');
    }
    return $value;
}

function rpcppeBuildItems(mysqli $conn, array $data, bool $lock = false): array {
    $result = $conn->query("SELECT id, item_name, item_description, PPE_no, unit, amount, quantity FROM ppe_property WHERE quantity > 0 AND COALESCE(status, '') NOT IN ('Unserviceable', 'Disposed') AND COALESCE(`condition`, '') <> 'Unserviceable' ORDER BY PPE_no, id" . ($lock ? ' FOR UPDATE' : ''));
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        $remarks = $data['remarks'][$id] ?? '';
        if (!is_scalar($remarks)) throw new InvalidArgumentException('Invalid remarks.');
        $onHand = rpcppeNumber($data, 'on_hand_count', $id);
        $items[] = [
            'ppe_id' => $id, 'article' => 'PPE', 'item_name' => $row['item_name'],
            'item_description' => $row['item_description'] ?? '', 'property_number' => $row['PPE_no'],
            'unit' => $row['unit'] ?? '', 'unit_value' => $row['amount'], 'qty_property_card' => $row['quantity'],
            'qty_physical_count' => $onHand,
            'shortage_qty' => $onHand === '' ? '' : (string)((int)$onHand - (int)$row['quantity']),
            'shortage_value' => rpcppeNumber($data, 'shortage_value', $id), 'remarks' => (string)$remarks,
        ];
    }
    return $items;
}

function rpcppeSaveReport(mysqli $conn, array $data): int {
    $date = trim((string)($data['report_date'] ?? ''));
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Select a valid report date.');
    $assumption = trim((string)($data['assumption_date'] ?? ''));
    if ($assumption !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $assumption);
        if (!$parsed || $parsed->format('Y-m-d') !== $assumption) throw new InvalidArgumentException('Select a valid assumption date.');
    }
    $assumption = $assumption !== '' ? $assumption : null;
    $fund = trim((string)($data['fund_cluster'] ?? '101'));
    $officer = trim((string)($data['accountable_officer'] ?? ''));
    $designation = trim((string)($data['official_designation'] ?? ''));
    $entity = trim((string)($data['entity_name'] ?? 'TESDA Regional Office'));
    $certified = trim((string)($data['signature_name_1'] ?? ''));
    $approved = trim((string)($data['signature_name_2'] ?? ''));
    $verified = trim((string)($data['signature_name_3'] ?? ''));
    $conn->begin_transaction();
    try {
        $items = rpcppeBuildItems($conn, $data, true);
        if (!$items) throw new InvalidArgumentException('There are no PPE items to include in this report.');
        $snapshot = json_encode(['items' => $items], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $count = count($items);
        $total = array_sum(array_map(static fn($item) => (float)$item['unit_value'] * (int)$item['qty_property_card'], $items));
        $stmt = $conn->prepare('INSERT INTO rpcppe (report_date, fund_cluster, accountable_officer, official_designation, entity_name, assumption_date, certified_by, approved_by, verified_by, snapshot, item_count, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssssssssid', $date, $fund, $officer, $designation, $entity, $assumption, $certified, $approved, $verified, $snapshot, $count, $total);
        $stmt->execute();
        $id = (int)$conn->insert_id;
        $stmt->close();
        $stmt = $conn->prepare('INSERT INTO rpcppe_items (rpcppe_id, ppe_id, on_hand_per_count, shortage_overage_qty, shortage_overage_value, remarks) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($items as $item) {
            $ppeId = $item['ppe_id'];
            $onHand = $item['qty_physical_count'] === '' ? null : (int)$item['qty_physical_count'];
            $difference = $item['shortage_qty'] === '' ? null : (int)$item['shortage_qty'];
            $value = $item['shortage_value'] === '' ? null : (float)$item['shortage_value'];
            $remarks = $item['remarks'];
            $stmt->bind_param('iiiids', $id, $ppeId, $onHand, $difference, $value, $remarks);
            $stmt->execute();
        }
        $stmt->close();
        $conn->commit();
        return $id;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function rpcppeLoadReport(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare('SELECT * FROM rpcppe WHERE rpcppe_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$report) return null;
    if ($report['snapshot'] !== null) {
        $snapshot = json_decode($report['snapshot'], true, 512, JSON_THROW_ON_ERROR);
        if (!isset($snapshot['items']) || !is_array($snapshot['items'])) throw new RuntimeException('Invalid saved report snapshot.');
        $report['items'] = $snapshot['items'];
    } else {
        // Reports saved before snapshots were introduced retain their linked item data.
        $stmt = $conn->prepare('SELECT i.ppe_id, p.item_name, p.item_description, p.PPE_no AS property_number, p.unit, p.amount AS unit_value, p.quantity AS qty_property_card, i.on_hand_per_count AS qty_physical_count, i.shortage_overage_qty AS shortage_qty, i.shortage_overage_value AS shortage_value, i.remarks FROM rpcppe_items i JOIN ppe_property p ON p.id = i.ppe_id WHERE i.rpcppe_id = ? ORDER BY i.id');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $report['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($report['items'] as &$item) {
            $item['article'] = 'PPE';
            foreach (['qty_physical_count', 'shortage_qty', 'shortage_value'] as $field) $item[$field] = $item[$field] === null ? '' : (string)$item[$field];
        }
        unset($item);
        $stmt->close();
    }
    return $report;
}