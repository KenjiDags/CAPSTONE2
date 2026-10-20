<?php
require __DIR__ . '/../config.php';
$conn->query('CREATE TEMPORARY TABLE ppe_ptr (ptr_id INT PRIMARY KEY, ptr_no VARCHAR(50), reason TEXT)');
$conn->query('CREATE TEMPORARY TABLE ppe_ptr_items (ptr_id INT, ppe_id INT)');
$conn->query('CREATE TEMPORARY TABLE ppe_property (id INT PRIMARY KEY, par_no VARCHAR(50), item_name VARCHAR(100), item_description TEXT, amount DECIMAL(10,2))');
$conn->query("INSERT INTO ppe_ptr VALUES (1, 'PTR-TEST', '<reason>')");
$conn->query("INSERT INTO ppe_property VALUES (1, 'PAR-TEST', '<Laptop>', 'Test description', 1234.50)");
$conn->query('INSERT INTO ppe_ptr_items VALUES (1,1), (1,2)');
$_GET = ['ptr_id' => '1'];
define('APP_ROOT_URL', '/Capstone2/');
$source = file_get_contents(__DIR__ . '/../PPE/view_ptr.php');
$source = preg_replace('/^(?:require|require_once) .*;.*$/m', '', $source);
$source = str_replace("<?php include dirname(__DIR__) . '/sidebar.php'; ?>", '', $source);
ob_start();
try { eval('?>' . $source); $html = ob_get_contents(); } finally { ob_end_clean(); }
foreach (['PTR-TEST', 'PAR-TEST', '&lt;Laptop&gt;', '&lt;reason&gt;', '1,234.50', 'PPE item #2 unavailable', 'PPE/edit_ptr.php?ptr_id=1', 'PPE/export_ptr.php?ptr_id=1'] as $expected) {
    if (!str_contains($html, $expected)) throw new RuntimeException('PTR view missing: ' . $expected);
}
if (str_contains($html, '<Laptop>')) throw new RuntimeException('Unsafe item output');
$listing = file_get_contents(__DIR__ . '/../PPE/PPE_PTR.php');
if (!str_contains($listing, 'href="PPE/view_ptr.php?ptr_id=')) throw new RuntimeException('PTR View action not connected');
echo "PTR detail rendering and View link checks passed (temporary tables only).\n";