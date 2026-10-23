<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require dirname(__DIR__) . '/config.php';

$items = [];
$sql = "SELECT item_description, quantity_balance, semi_expendable_property_no, remarks FROM semi_expendable_property WHERE (NOT EXISTS (SELECT 1 FROM iirusp_items disposed_item WHERE disposed_item.semi_expendable_property_no COLLATE utf8mb4_general_ci = semi_expendable_property.semi_expendable_property_no COLLATE utf8mb4_general_ci) OR (COALESCE(semi_expendable_property.quantity, 0) > 0 AND (COALESCE(semi_expendable_property.quantity_balance, 0) > 0 OR COALESCE(semi_expendable_property.quantity_issued, 0) > 0 OR COALESCE(semi_expendable_property.quantity_reissued, 0) > 0))) ORDER BY item_description";
$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $items[] = $r;
    }
}

$entity_name = htmlspecialchars($_GET['entity_name'] ?? '');
$rrsp_date = htmlspecialchars($_GET['rrsp_date'] ?? date('Y-m-d'));
$rrsp_no = htmlspecialchars($_GET['rrsp_no'] ?? '');
$returned_by = htmlspecialchars($_GET['returned_by'] ?? '');
$returned_date = htmlspecialchars($_GET['returned_date'] ?? '');
$received_by = htmlspecialchars($_GET['received_by'] ?? '');
$received_date = htmlspecialchars($_GET['received_date'] ?? '');

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html>
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>"><meta charset="utf-8"><title>RRSP</title><link rel="stylesheet" href="css/export-preview.css?v=<?= time() ?>">
<script src="js/export-preview.js?v=<?= time() ?>" defer data-export-preview data-preview-title="Returned Semi-Expendable Property Preview" data-preview-code="RRSP" data-preview-back="Semi_Expendables/rrsp.php" data-preview-width="1000"></script>
</head>
<body>
<h1>RRSP</h1>
<p>Entity: <?= $entity_name ?></p>
<p>Date: <?= $rrsp_date ?></p>
<p>RRSP No: <?= $rrsp_no ?></p>
<?php exit();
