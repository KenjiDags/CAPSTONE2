<?php
// Run with PHP CLI. All fixture and issuance writes are rolled back.
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/PPE/par_helpers.php';
function parCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$token = 'PAR-TEST-' . bin2hex(random_bytes(6));
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO ppe_property (PPE_no, item_name, item_description, amount, quantity, unit, officer_incharge, custodian, entity_name, status, `condition`) VALUES (?, 'PAR test', 'Transaction-only fixture', 250, 2, 'unit', '', 'Test supply officer', 'Test Entity', 'Active', 'Good')");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $id = $conn->insert_id;
    $header = ['par_no' => $token, 'entity_name' => 'Test Entity', 'fund_cluster' => '101', 'received_by' => 'Test Recipient', 'received_by_designation' => 'End User', 'received_by_date' => '2026-10-26', 'issued_by' => 'Test Issuer', 'issued_by_designation' => 'Property Officer', 'issued_by_date' => '2026-10-26'];
    $parId = ppeSavePar($conn, $header, [$id]);
    $row = $conn->query("SELECT * FROM ppe_property WHERE id = $id")->fetch_assoc();
    parCheck($row['status'] === 'Issued' && $row['custodian'] === 'Test Recipient' && $row['officer_incharge'] === 'Test Recipient', 'Assignment fields were not saved.');
    parCheck((int)$row['quantity'] === 2, 'Issuance must preserve property units.');
    parCheck((int)$conn->query("SELECT COUNT(*) AS n FROM ppe_par_items WHERE par_id = $parId AND ppe_id = $id")->fetch_assoc()['n'] === 1, 'PAR link was not saved.');
    $historyStmt = $conn->prepare('SELECT issue_qty, balance_qty, PAR_number FROM item_history_ppe WHERE PPE_no = ?');
    $historyStmt->bind_param('s', $token);
    $historyStmt->execute();
    $history = $historyStmt->get_result()->fetch_assoc();
    parCheck((int)$history['issue_qty'] === 2 && (int)$history['balance_qty'] === 0 && $history['PAR_number'] === $token, 'Issue history is incorrect.');
    $export = $conn->query("SELECT p.* FROM ppe_par_items pi JOIN ppe_property p ON p.id = pi.ppe_id WHERE pi.par_id = $parId")->fetch_assoc();
    parCheck((int)$export['quantity'] === 2 && $export['PPE_no'] === $token, 'PAR export item query failed.');
    $rejected = false;
    try { ppeSavePar($conn, $header, [$id]); } catch (InvalidArgumentException $e) { $rejected = true; }
    parCheck($rejected, 'Already issued property was accepted.');
    foreach ([['quantity' => 0], ['condition' => 'Unserviceable'], ['status' => 'Transferred'], ['status' => 'Unserviceable'], ['status' => 'For Repair'], ['status' => 'Disposed'], ['status' => 'Issued'], ['status' => 'Not Issued'], ['has_assignment' => 1]] as $change) {
        $available = ['quantity' => 2, 'condition' => 'Good', 'status' => 'Active', 'has_assignment' => 0];
        parCheck(!ppeParEligible(array_replace($available, $change)), 'Unavailable property was accepted.');
    }
    parCheck(ppeParEligible(['quantity' => 1, 'condition' => 'Good', 'status' => 'Available', 'has_assignment' => 0]), 'Available property should be eligible.');
    parCheck(!ppeParEligible(['quantity' => 1, 'condition' => 'Unserviceable', 'status' => 'Returned', 'has_assignment' => 1]), 'Unserviceable returned property must be rejected.');
    parCheck(ppeParEligible(['quantity' => 1, 'condition' => 'Good', 'status' => 'Returned', 'has_assignment' => 1]), 'Returned property should be available again.');
    echo "PASS: issuance, export linkage, quantities, history, duplicate protection, and availability rules.\n";
} finally {
    $conn->rollback();
}
$check = $conn->prepare('SELECT COUNT(*) AS n FROM ppe_par WHERE par_no = ?');
$check->bind_param('s', $token);
$check->execute();
parCheck((int)$check->get_result()->fetch_assoc()['n'] === 0, 'Test PAR was not rolled back.');
echo "PASS: test data rolled back.\n";
