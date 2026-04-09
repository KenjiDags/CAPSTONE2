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

function h($value) {
  return htmlspecialchars((string)($value ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View RRSP</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" />
<link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
  .container h2::before {
    content: "\f15b";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    color: #3b82f6;
    margin-right: 10px;
  }
</style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="container">
  <h2>Viewing RRSP No. <?= h($rrsp['rrsp_no']) ?></h2>

  <div class="ris-actions">
    <a href="rrsp.php" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Back to RRSP List
    </a>
    <a href="export_rrsp.php?rrsp_id=<?= (int)$rrsp_id ?>" class="btn btn-success">
      <i class="fas fa-file-pdf"></i> Export RRSP
    </a>
    <a href="edit_rrsp.php?rrsp_id=<?= (int)$rrsp_id ?>" class="btn btn-primary">
      <i class="fas fa-edit"></i> Edit RRSP
    </a>
  </div>

  <h3><i class="fas fa-info-circle"></i> RRSP Details</h3>
  <div class="ris-details">
    <p><strong>RRSP No:</strong> <span><?= h($rrsp['rrsp_no']) ?></span></p>
    <p><strong>Entity Name:</strong> <span><?= h($rrsp['entity_name']) ?></span></p>
    <p><strong>Fund Cluster:</strong> <span><?= h($rrsp['fund_cluster']) ?></span></p>
    <p><strong>Date Prepared:</strong> <span><?= h($rrsp['date_prepared']) ?></span></p>
    <?php if(!empty($rrsp['remarks'])): ?>
      <p><strong>General Remarks:</strong> <span><?= nl2br(h($rrsp['remarks'])) ?></span></p>
    <?php endif; ?>
  </div>

  <h3><i class="fas fa-box"></i> Returned Items</h3>
  <div class="table-container" style="margin: 25px 0;">
    <table>
      <thead>
        <tr>
          <th><i class="fas fa-align-left"></i> Description</th>
          <th><i class="fas fa-hashtag"></i> Qty</th>
          <th><i class="fas fa-barcode"></i> ICS No.</th>
          <th><i class="fas fa-user"></i> End-user</th>
          <th><i class="fas fa-comment"></i> Remarks</th>
          <th><i class="fas fa-coins"></i> Unit Cost</th>
          <th><i class="fas fa-calculator"></i> Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($items)): ?>
          <tr>
            <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
              <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
              No returned items recorded for this RRSP.
            </td>
          </tr>
        <?php else: ?>
          <?php
          $total_amount = 0;
          foreach($items as $it):
            $tot = (float)$it['quantity'] * (float)$it['unit_cost'];
            $total_amount += $tot;
          ?>
          <tr>
            <td><?= h($it['item_description']) ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td><?= h($it['ics_no']) ?></td>
            <td><?= h($it['end_user']) ?></td>
            <td><?= h($it['item_remarks']) ?></td>
            <td>₱ <?= number_format((float)$it['unit_cost'], 2) ?></td>
            <td>₱ <?= number_format($tot, 2) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if(!empty($items)): ?>
      <tfoot>
        <tr>
          <td colspan="6" style="text-align: right; font-weight: bold;">Total Amount:</td>
          <td style="font-weight: bold;">₱ <?= number_format($total_amount, 2) ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>

  <h3><i class="fas fa-pen-nib"></i> Signatories</h3>
  <div class="ris-details">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
      <div>
        <h4><i class="fas fa-undo-alt"></i> Returned by:</h4>
        <p><strong>Name:</strong> <span><?= h($rrsp['returned_by']) ?></span></p>
        <p><strong>Date:</strong> <span><?= h($rrsp['returned_date']) ?></span></p>
      </div>
      <div>
        <h4><i class="fas fa-hand-holding"></i> Received by:</h4>
        <p><strong>Name:</strong> <span><?= h($rrsp['received_by']) ?></span></p>
        <p><strong>Date:</strong> <span><?= h($rrsp['received_date']) ?></span></p>
      </div>
    </div>
  </div>
</div>
</body>
</html>