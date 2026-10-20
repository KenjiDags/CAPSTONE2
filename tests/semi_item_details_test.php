<?php
require __DIR__ . '/../config.php';
// Temporary fixture shadows the live table only on this connection.
$conn->query('CREATE TEMPORARY TABLE semi_expendable_property (id INT PRIMARY KEY, date DATE, item_description VARCHAR(100))');
$conn->query("INSERT INTO semi_expendable_property VALUES (1, '2026-10-20', 'Test item')");
$source = file_get_contents(__DIR__ . '/../Semi_Expendables/get_item_details.php');
$source = preg_replace('/^(?:require|require_once) .*;.*$/m', '', $source);
function renderSemiDetails(string $source, mysqli $conn, int $id): array {
    $_GET = ['id' => (string)$id];
    http_response_code(200);
    ob_start();
    try { eval('?>' . $source); return json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR); }
    finally { ob_end_clean(); }
}
$result = renderSemiDetails($source, $conn, 1);
if (!$result['success'] || $result['item']['item_description'] !== 'Test item' || $result['item']['date'] !== 'October 20, 2026') throw new RuntimeException('Item details response incorrect');
$missing = renderSemiDetails($source, $conn, 999);
if ($missing['success'] || http_response_code() !== 404) throw new RuntimeException('Missing item response incorrect');
echo "Semi-expendable View endpoint checks passed (temporary table only).\n";