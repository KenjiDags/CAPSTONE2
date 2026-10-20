<?php
require_once __DIR__ . '/../rpci_helpers.php';
function checkRpci(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$input = ['report_date' => '2026-10-20', 'assumption_date' => '2026-01-01', 'entity_name' => 'Test entity', 'accountable_officer' => 'Test officer', 'fund_cluster' => '101', 'signature_name_1' => 'Chair'];
checkRpci(rpciMetadata($input)['signature_name_1'] === 'Chair', 'Metadata not retained');
try {
    rpciMetadata(array_merge($input, ['report_date' => '2026-02-30']));
    throw new RuntimeException('Invalid date accepted');
} catch (InvalidArgumentException $expected) {}
checkRpci(rpciH('<test>') === '&lt;test&gt;', 'HTML escaping failed');
if (!in_array('--database', $argv, true)) { echo "RPCI validation checks passed.\n"; exit; }
require __DIR__ . '/../config.php';
// Temporary tables shadow the application tables only for this test connection.
$conn->query(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', rpciSchema()));
$conn->query('CREATE TEMPORARY TABLE items (item_id INT, item_name VARCHAR(255), description TEXT, stock_number VARCHAR(50), unit VARCHAR(50), unit_cost DECIMAL(10,4), quantity_on_hand INT, calculated_quantity INT)');
$conn->query("INSERT INTO items VALUES (1, 'Original item', 'Original description', 'TEST-1', 'pc', 12.34, 15, NULL)");
$input['row_data'] = json_encode(['1' => ['balance_per_card' => '15', 'on_hand_per_count' => '12', 'shortage_quantity' => '-3', 'shortage_value' => '-37.02', 'remarks' => '<Count checked>']]);
$token = str_repeat('a', 64);
$id = rpciSave($conn, $input, 1, $token);
checkRpci($id > 0, 'Save failed');
$conn->query("UPDATE items SET item_name = 'Changed item', unit_cost = 99");
$report = rpciLoad($conn, $id);
checkRpci($report['data']['rows'][0]['item_name'] === 'Original item', 'Saved inventory changed');
checkRpci((float)$report['data']['rows'][0]['unit_cost'] === 12.34, 'Saved price changed');
checkRpci($report['data']['metadata']['fund_cluster'] === '101', 'Saved metadata changed');
foreach (['balance_per_card' => '15', 'on_hand_per_count' => '12', 'shortage_quantity' => '-3', 'shortage_value' => '-37.02', 'remarks' => '<Count checked>'] as $field => $value) {
    checkRpci($report['data']['rows'][0][$field] === $value, 'Saved row field changed: ' . $field);
}
$zero = rpciApplyCounts([['item_id' => 1]], ['row_data' => '{"1":{"balance_per_card":"0"}}']);
checkRpci($zero[0]['balance_per_card'] === '0', 'Zero quantity lost');
try {
    rpciApplyCounts([['item_id' => 1]], ['row_data' => '{"1":{"on_hand_per_count":"-1"}}']);
    throw new RuntimeException('Negative physical count accepted');
} catch (InvalidArgumentException $expected) {}
try {
    rpciApplyCounts([['item_id' => 1]], ['row_data' => '{"1":{"shortage_value":"oops"}}']);
    throw new RuntimeException('Invalid amount accepted');
} catch (InvalidArgumentException $expected) {}
checkRpci(rpciSave($conn, $input, 1, $token) === $id, 'Duplicate save not prevented');
checkRpci((int)$conn->query('SELECT COUNT(*) AS n FROM rpci_reports')->fetch_assoc()['n'] === 1, 'Duplicate report created');
checkRpci(rpciLoad($conn, $id + 1) === null, 'Missing report not handled');
$conn->query('DELETE FROM items');
try {
    rpciSave($conn, $input, 1, str_repeat('b', 64));
    throw new RuntimeException('Empty inventory accepted');
} catch (InvalidArgumentException $expected) {}
echo "RPCI database checks passed (temporary tables only).\n";