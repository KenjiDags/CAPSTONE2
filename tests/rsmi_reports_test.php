<?php
require_once __DIR__ . '/../office_supplies/rsmi_helpers.php';
function checkRsmi(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$rows = [
    ['ris_no' => 'RIS-1', 'stock_number' => '001', 'item_name' => 'Paper & pens', 'description' => '<sample>',
        'unit' => 'box', 'issued_quantity' => '2', 'unit_cost' => '10', 'amount' => '20',
        'entity_name' => 'TESDA', 'fund_cluster' => '101', 'responsibility_center_code' => 'A'],
    ['ris_no' => 'RIS-2', 'stock_number' => '001', 'item_name' => 'Paper & pens', 'description' => '<sample>',
        'unit' => 'box', 'issued_quantity' => '3', 'unit_cost' => '20', 'amount' => '60',
        'entity_name' => 'TESDA', 'fund_cluster' => '101', 'responsibility_center_code' => 'A'],
];
$recap = rsmiRecap($rows);
checkRsmi(count($recap) === 1, 'Same stock must be grouped.');
checkRsmi($recap[0]['stock_number'] === '001', 'Stock numbers must keep leading zeroes.');
checkRsmi($recap[0]['total_issued'] === 5.0 && $recap[0]['total_cost'] === 80.0, 'Recap totals incorrect.');
checkRsmi($recap[0]['avg_unit_cost'] === 15.0, 'Average cost changed from original report behavior.');
checkRsmi(rsmiRecap([]) === [], 'Empty recap incorrect.');
for ($i = 2; $i < 17; $i++) {
    $row = $rows[0];
    $row['stock_number'] = sprintf('%03d', $i);
    $rows[] = $row;
}
checkRsmi(count(rsmiRecap($rows)) === 16, 'Recap must retain more than ten stocks.');
echo "RSMI recapitulation checks passed.\n";
if (in_array('--database', $argv, true)) {
    require __DIR__ . '/../config.php';
    // Connection-local temporary table: no production reports or RIS records are changed.
    $sql = file_get_contents(__DIR__ . '/../db/migrations/2026-10-11_create_rsmi_reports.sql');
    $conn->query(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $sql));
    $id = rsmiSave($conn, $rows, '2026-10-11', "O'Neil <Custodian>", 'Accounting & Staff', 1);
    $saved = rsmiLoad($conn, $id);
    checkRsmi($saved !== null && $saved['rows'] === $rows, 'Saved rows did not round-trip exactly.');
    checkRsmi(count($saved['rows']) === 17, 'Report truncated entries.');
    checkRsmi($saved['custodian_name'] === "O'Neil <Custodian>" && $saved['accounting_staff'] === 'Accounting & Staff', 'Signatories not preserved.');
    checkRsmi($saved['report_date'] === '2026-10-11', 'Saved date changed.');
    checkRsmi((float)$saved['total_issued'] === 35.0 && (float)$saved['total_amount'] === 380.0, 'Saved summary totals incorrect.');
    checkRsmi($saved['serial_no'] === 'RSMI-2026-0001', 'Serial number incorrect.');
    $rows[0]['item_name'] = 'Changed source item';
    checkRsmi(rsmiLoad($conn, $id)['rows'][0]['item_name'] === 'Paper & pens', 'Saved snapshot changed with source data.');
    $second = rsmiSave($conn, $rows, '2026-10-12', 'Second Custodian', 'Second Accountant', 1);
    checkRsmi($second !== $id && rsmiLoad($conn, $second)['serial_no'] !== $saved['serial_no'], 'Serials are not unique.');
    checkRsmi(rsmiLoad($conn, 999999) === null, 'Missing report should return null.');
    try {
        rsmiSave($conn, [], '2026-10-11', 'Custodian', 'Accountant', 1);
        throw new RuntimeException('Empty report accepted.');
    } catch (InvalidArgumentException $expected) {}
    $source = rsmiSourceRows($conn);
    foreach ($source as $row) checkRsmi((float)$row['issued_quantity'] > 0, 'Source contains unissued items.');
    // Exercise the real templates with authentication/sidebar excluded only in this test harness.
    // Database storage remains connection-local; no real documents are saved.
    function renderRsmiTemplate(string $file, mysqli $conn, array $query = []): string {
        $_GET = $query;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SESSION = ['user_id' => 1, 'rsmi_csrf' => str_repeat('a', 64)];
        $source = file_get_contents(__DIR__ . '/../' . $file);
        $source = str_replace(["require 'auth.php';", "require dirname(__DIR__) . '/auth.php';", "<?php include dirname(__DIR__) . '/sidebar.php'; ?>", "rsmiEnsureTable(\$conn);", "<?php include 'sidebar.php'; ?>"], '', $source);
        $source = str_replace('__DIR__', var_export(__DIR__ . '/../office_supplies', true), $source);
        ob_start();
        try {
            eval('?>' . $source);
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
    $export = renderRsmiTemplate('office_supplies/rsmi_export.php', $conn, ['rsmi_id' => $id]);
    checkRsmi(str_contains($export, 'RSMI-2026-0001') && str_contains($export, 'October 11, 2026'), 'Export does not use saved metadata.');
    checkRsmi(str_contains($export, '&lt;sample&gt;') && str_contains($export, '&lt;Custodian&gt;'), 'Export must escape saved fields.');
    checkRsmi(str_contains($export, 'Paper &amp; pens') && !str_contains($export, 'Changed source item'), 'Export is reading changed data.');
    checkRsmi(str_contains($export, '<td>016</td>'), 'Export truncated reports exceeding fifteen entries.');
    $listing = renderRsmiTemplate('office_supplies/rsmi.php', $conn);
    checkRsmi(str_contains($listing, 'RSMI-2026-0001') && str_contains($listing, 'RSMI-2026-0002'), 'Saved reports missing from list.');
    checkRsmi(str_contains($listing, 'add_rsmi.php') && str_contains($listing, 'rsmi_export.php?rsmi_id=1'), 'List links missing.');
    $filtered = renderRsmiTemplate('office_supplies/rsmi.php', $conn, ['search' => 'Second Custodian', 'sort' => 'amount_highest']);
    checkRsmi(!str_contains($filtered, 'RSMI-2026-0001') && str_contains($filtered, 'RSMI-2026-0002'), 'Search filtering failed.');
    $empty = renderRsmiTemplate('office_supplies/rsmi.php', $conn, ['search' => 'no matching fixture', 'sort' => 'invalid sort']);
    checkRsmi(str_contains($empty, 'No matching RSMI documents found.'), 'Empty search state missing.');
    $add = renderRsmiTemplate('office_supplies/add_rsmi.php', $conn);
    checkRsmi(str_contains($add, 'method="post" id="signatureForm"') && str_contains($add, 'form="signatureForm"'), 'Save form is not connected.');
    checkRsmi(str_contains($add, 'name="csrf"') && !str_contains($add, 'exportBtn'), 'Creation must save with CSRF rather than exporting.');
    echo "RSMI creation, listing/search, and saved export template checks passed.\n";
    $conn->close();
    echo "RSMI database save/load, unique serial, and snapshot checks passed (temporary table).\n";
}
