<?php
require 'config.php';

$items = [];
$sql = "SELECT item_description, quantity_balance, semi_expendable_property_no, remarks FROM semi_expendable_property ORDER BY item_description";
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
<head><meta charset="utf-8"><title>RRSP</title></head>
<body>
<h1>RRSP</h1>
<p>Entity: <?= $entity_name ?></p>
<p>Date: <?= $rrsp_date ?></p>
<p>RRSP No: <?= $rrsp_no ?></p>
<?php exit();
