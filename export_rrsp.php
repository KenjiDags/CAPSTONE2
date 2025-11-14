<?php
require 'config.php';
$rrsp_id = isset($_GET['rrsp_id']) ? (int)$_GET['rrsp_id'] : 0;
$rrsp=null; $items=[];
if($rrsp_id){
  $h=$conn->prepare("SELECT * FROM rrsp WHERE rrsp_id=? LIMIT 1"); $h->bind_param('i',$rrsp_id); $h->execute(); $rr=$h->get_result(); $rrsp=$rr->fetch_assoc(); $h->close();
  $i=$conn->prepare("SELECT * FROM rrsp_items WHERE rrsp_id=? ORDER BY rrsp_item_id ASC"); $i->bind_param('i',$rrsp_id); $i->execute(); $res=$i->get_result(); while($r=$res->fetch_assoc()){ $items[]=$r; } $i->close();
}
if(!$rrsp){ echo 'RRSP not found.'; exit; }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Export RRSP</title><link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" /><style>body{background:#fff;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #000;padding:4px 6px;font-size:12px;}th{background:#eee;}@media print {.no-print{display:none;}}</style></head><body>
<button class="no-print" onclick="window.print()">Print / Save PDF</button>
<h3 style="text-align:center; margin:4px 0;">RECEIPT OF RETURNED SEMI-EXPENDABLE PROPERTY (RRSP)</h3>
<table style="margin-bottom:10px;">
<tr><td><strong>Entity Name:</strong> <?= htmlspecialchars($rrsp['entity_name']) ?></td><td><strong>RRSP No.:</strong> <?= htmlspecialchars($rrsp['rrsp_no']) ?></td></tr>
<tr><td><strong>Date Prepared:</strong> <?= htmlspecialchars($rrsp['date_prepared']) ?></td><td><strong>Fund Cluster:</strong> <?= htmlspecialchars($rrsp['fund_cluster']) ?></td></tr>
</table>
<table>
<thead><tr><th>Item Description</th><th>Quantity</th><th>ICS No.</th><th>End-user</th><th>Remarks</th><th>Unit Cost</th><th>Total Amount</th></tr></thead><tbody>
<?php if(empty($items)): ?><tr><td colspan="7" style="text-align:center;">No items</td></tr><?php else: foreach($items as $it): $tot=(float)$it['quantity']*(float)$it['unit_cost']; ?>
<tr><td><?= htmlspecialchars($it['item_description']) ?></td><td style="text-align:right;"><?= (int)$it['quantity'] ?></td><td><?= htmlspecialchars($it['ics_no']) ?></td><td><?= htmlspecialchars($it['end_user']) ?></td><td><?= htmlspecialchars($it['item_remarks']) ?></td><td style="text-align:right;">₱<?= number_format($it['unit_cost'],2) ?></td><td style="text-align:right;">₱<?= number_format($tot,2) ?></td></tr>
<?php endforeach; endif; ?>
</tbody></table>
<br>
<table style="width:100%; border:none;">
<tr style="border:none;"><td style="border:none; width:50%; text-align:center;">
  <div style="height:70px;"></div>
  <strong><?= htmlspecialchars($rrsp['returned_by']) ?></strong><br>
  Returned by <?= $rrsp['returned_date'] ? '(' . htmlspecialchars($rrsp['returned_date']) . ')' : '' ?>
</td><td style="border:none; width:50%; text-align:center;">
  <div style="height:70px;"></div>
  <strong><?= htmlspecialchars($rrsp['received_by']) ?></strong><br>
  Received by <?= $rrsp['received_date'] ? '(' . htmlspecialchars($rrsp['received_date']) . ')' : '' ?>
</td></tr>
</table>
</body></html>