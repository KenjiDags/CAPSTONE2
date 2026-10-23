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
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RRSP Export</title>
  <style>
    @media print { .no-print { display:none !important; } body { margin:0; padding:10px; } }
    body { font-family: 'Century Gothic', Century Gothic, sans-serif; font-size: 11px; line-height: 1.2; margin:0; padding:20px; background:#fff; color:#000; }
    .container { max-width: 900px; margin: 0 auto; border: 1px solid #000; padding: 16px; box-sizing: border-box; }
    .header { text-align:center; margin-bottom: 15px; }
    .title { font-weight:bold; font-size:16px; margin:0; text-decoration: underline; }
    .field-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; font-size:12px; }
    .field-input { border-bottom:1px solid #000; min-width: 120px; padding:0 4px; }
    table { width:100%; border-collapse: collapse; font-size: 10px; }
    th, td { border:1px solid #000; padding: 6px 8px; vertical-align: top; text-align: center; }
    th { background:#f5f5f5; font-weight:bold; }
    .text-left { text-align: left; }
    .signatures { width:100%; border-collapse: collapse; margin-top: 20px; }
    .signatures td { border:1px solid #000; padding:8px; vertical-align:top; height:90px; font-size:10px; }
    .btn { display:inline-block; padding:8px 16px; border:none; border-radius:4px; cursor:pointer; font-size:12px; text-decoration:none; }
    .btn-print { background:#007cba; color:#fff; }
    .btn-back { background:#6c757d; color:#fff; }
  </style>
<link rel="stylesheet" href="css/export-preview.css?v=<?= time() ?>">
<script src="js/export-preview.js?v=<?= time() ?>" defer data-export-preview data-preview-title="Returned Semi-Expendable Property Preview" data-preview-code="RRSP" data-preview-back="Semi_Expendables/rrsp.php" data-preview-width="1000"></script>
</head>
<body>
  <div class="no-print">
    <button class="btn btn-print" onclick="window.print()">Print/Save as PDF</button>
    <a href="Semi_Expendables/rrsp.php" class="btn btn-back" style="margin-left:8px;">Back to Form</a>
    <hr style="margin: 20px 0;">
  </div>

  <div class="container">
    <div class="header">
      <h1 class="title">RECEIPT OF RETURNED SEMI-EXPENDABLE PROPERTY</h1>
    </div>

    <div class="field-row">
      <div><strong>Entity Name:</strong> <span class="field-input"><?php echo $entity_name; ?></span></div>
      <div><strong>Date:</strong> <span class="field-input"><?php echo $rrsp_date; ?></span></div>
      <div><strong>RRSP No.:</strong> <span class="field-input"><?php echo $rrsp_no; ?></span></div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Item Description</th>
          <th>Quantity</th>
          <th>ICS No.</th>
          <th>End-user</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if (empty($items)) {
            echo '<tr><td colspan="5">No items found</td></tr>';
        } else {
            foreach ($items as $row) {
                echo '<tr>';
                echo '<td class="text-left">' . htmlspecialchars($row['item_description'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars((string)($row['quantity_balance'] ?? '')) . '</td>';
                echo '<td>' . htmlspecialchars($row['semi_expendable_property_no'] ?? '') . '</td>';
                echo '<td>&nbsp;</td>';
                echo '<td class="text-left">' . htmlspecialchars($row['remarks'] ?? '') . '</td>';
                echo '</tr>';
            }
        }
        for ($i = count($items); $i < 12; $i++) {
            echo '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
        }
        ?>
      </tbody>
    </table>

    <table class="signatures">
      <tr>
        <td style="width:50%;">
          <div style="font-weight:bold; margin-bottom:6px;">Returned by:</div>
          <div style="border-bottom:1px solid #000; min-height:20px; margin-bottom:6px;"><?php echo htmlspecialchars($returned_by); ?></div>
          <div>End User</div>
          <div style="margin-top:6px;">Date: <?php echo $returned_date; ?></div>
        </td>
        <td style="width:50%;">
          <div style="font-weight:bold; margin-bottom:6px;">Received by:</div>
          <div style="border-bottom:1px solid #000; min-height:20px; margin-bottom:6px;"><?php echo htmlspecialchars($received_by); ?></div>
          <div>Head, Property and/or Supply Division/Unit</div>
          <div style="margin-top:6px;">Date: <?php echo $received_date; ?></div>
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
