<?php
require 'auth.php';
require 'config.php';
$rrsp_id = isset($_GET['rrsp_id']) ? (int)$_GET['rrsp_id'] : 0;
$rrsp=null; $items=[];
if($rrsp_id){
  $h=$conn->prepare("SELECT * FROM rrsp WHERE rrsp_id=? LIMIT 1");
  $h->bind_param('i',$rrsp_id); $h->execute(); $rr=$h->get_result(); $rrsp=$rr->fetch_assoc(); $h->close();
  $i=$conn->prepare("SELECT * FROM rrsp_items WHERE rrsp_id=? ORDER BY rrsp_item_id ASC");
  $i->bind_param('i',$rrsp_id); $i->execute(); $res=$i->get_result(); while($r=$res->fetch_assoc()){ $items[]=$r; } $i->close();
}
if(!$rrsp){ echo 'RRSP not found.'; exit; }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>View RRSP</title><link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" /></head><body class="rrsp-page"><?php include 'sidebar.php'; ?><div class="content">
<h2>RRSP Details</h2>
<p><strong>RRSP No.:</strong> <?= htmlspecialchars($rrsp['rrsp_no']) ?> | <strong>Date Prepared:</strong> <?= htmlspecialchars($rrsp['date_prepared']) ?></p>
<p><strong>Entity:</strong> <?= htmlspecialchars($rrsp['entity_name']) ?> | <strong>Fund Cluster:</strong> <?= htmlspecialchars($rrsp['fund_cluster']) ?></p>
<p><strong>Returned by:</strong> <?= htmlspecialchars($rrsp['returned_by']) ?> <?= $rrsp['returned_date']? '('.htmlspecialchars($rrsp['returned_date']).')':''; ?> | <strong>Received by:</strong> <?= htmlspecialchars($rrsp['received_by']) ?> <?= $rrsp['received_date']? '('.htmlspecialchars($rrsp['received_date']).')':''; ?></p>
<?php if($rrsp['remarks']): ?><p><strong>Remarks:</strong> <?= nl2br(htmlspecialchars($rrsp['remarks'])) ?></p><?php endif; ?>
<table style="width:100%; border-collapse:collapse;">
<thead><tr style="background:#003f7f; color:#fff;"><th>Description</th><th>Qty</th><th>ICS No.</th><th>End-user</th><th>Remarks</th><th>Unit Cost</th><th>Total</th></tr></thead>
<tbody>
<?php if(empty($items)): ?><tr><td colspan="7" style="text-align:center; padding:24px; font-style:italic;">No items</td></tr><?php else: foreach($items as $it): $tot = (float)$it['quantity'] * (float)$it['unit_cost']; ?>
<tr><td><?= htmlspecialchars($it['item_description']) ?></td><td style="text-align:right;"><?= (int)$it['quantity'] ?></td><td><?= htmlspecialchars($it['ics_no']) ?></td><td><?= htmlspecialchars($it['end_user']) ?></td><td><?= htmlspecialchars($it['item_remarks']) ?></td><td style="text-align:right;">₱<?= number_format($it['unit_cost'],2) ?></td><td style="text-align:right; font-weight:600;">₱<?= number_format($tot,2) ?></td></tr>
<?php endforeach; endif; ?>
</tbody></table>
<div style="margin-top:18px; display:flex; gap:12px;">
  <a href="export_rrsp.php?rrsp_id=<?= (int)$rrsp_id ?>">Export PDF</a>
  <a href="edit_rrsp.php?rrsp_id=<?= (int)$rrsp_id ?>">Edit</a>
  <a href="rrsp.php">Back to list</a>
</div>
</div></body></html>