<?php
// PAR issues the whole quantity of each selected property record.
// Active is the PPE entry form's status for new available stock.
function ppeParEligible(array $item): bool {
    $status = strtolower(trim((string)($item['status'] ?? '')));
    $condition = strtolower(trim((string)($item['condition'] ?? '')));
    return (int)($item['quantity'] ?? 0) > 0
        && in_array($condition, ['good', 'fair', 'serviceable'], true)
        && in_array($status, ['active', 'available', 'returned'], true)
        && ($status === 'returned' || empty($item['has_assignment']));
}

// Caller owns the transaction and rolls back every write on failure.
function ppeSavePar(mysqli $conn, array $header, array $ids): int {
    if (!$ids) throw new InvalidArgumentException('Select at least one available property.');
    $items = [];
    sort($ids, SORT_NUMERIC);
    $select = $conn->prepare("SELECT p.*, EXISTS(SELECT 1 FROM ppe_par_items pi WHERE pi.ppe_id = p.id) OR EXISTS(SELECT 1 FROM ppe_ptr_items ti WHERE ti.ppe_id = p.id) AS has_assignment FROM ppe_property p WHERE p.id = ? FOR UPDATE");
    foreach ($ids as $id) {
        $select->bind_param('i', $id);
        $select->execute();
        $item = $select->get_result()->fetch_assoc();
        if (!$item || !ppeParEligible($item)) throw new InvalidArgumentException('A selected property is no longer available. Refresh the item selection.');
        $items[] = $item;
    }
    $select->close();
    $propertyNumbers = implode(', ', array_column($items, 'PPE_no'));
    if (strlen($propertyNumbers) > 100) $propertyNumbers = 'Multiple properties';
    $insert = $conn->prepare('INSERT INTO ppe_par (par_no, entity_name, fund_cluster, date_acquired, property_number, received_by, received_by_designation, received_by_date, issued_by, issued_by_designation, issued_by_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->bind_param('sssssssssss', $header['par_no'], $header['entity_name'], $header['fund_cluster'], $header['received_by_date'], $propertyNumbers, $header['received_by'], $header['received_by_designation'], $header['received_by_date'], $header['issued_by'], $header['issued_by_designation'], $header['issued_by_date']);
    $insert->execute();
    $parId = $conn->insert_id;
    $insert->close();
    $link = $conn->prepare('INSERT INTO ppe_par_items (par_id, ppe_id) VALUES (?, ?)');
    $update = $conn->prepare("UPDATE ppe_property SET status = 'Issued', custodian = ?, officer_incharge = ? WHERE id = ?");
    $history = $conn->prepare("INSERT INTO item_history_ppe (PPE_no, PAR_number, item_name, description, unit, unit_cost, quantity_on_hand, quantity_change, receipt_qty, issue_qty, balance_qty, officer_incharge, change_direction, change_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0, ?, 'issue', 'PAR')");
    foreach ($items as $item) {
        $id = (int)$item['id'];
        $link->bind_param('ii', $parId, $id);
        $link->execute();
        $update->bind_param('ssi', $header['received_by'], $header['received_by'], $id);
        $update->execute();
        $qty = (int)$item['quantity'];
        $change = -$qty;
        $history->bind_param('sssssdiiis', $item['PPE_no'], $header['par_no'], $item['item_name'], $item['item_description'], $item['unit'], $item['amount'], $qty, $change, $qty, $header['received_by']);
        $history->execute();
    }
    $link->close();
    $update->close();
    $history->close();
    return $parId;
}
