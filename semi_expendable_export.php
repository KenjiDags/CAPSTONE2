<?php
require 'config.php';
require_once 'functions.php';
// Ensure history table exists to prevent fatal errors on export
ensure_semi_expendable_history($conn);

if (!isset($_GET['id'])) {
    die("❌ Error: item not found.");
}
$id = (int)$_GET['id'];
if ($id <= 0) {
    die("❌ Invalid item ID.");
}
// Fetch item
$stmt = $conn->prepare("SELECT * FROM semi_expendable_property WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    die("❌ No record found for Item ID $id.");
}
$item = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Semi-Expendable Property Card Export</title>
  <style>
    @media print {
      .no-print { display:none !important; }
      @page { size: landscape; margin: 0.5cm 1.5cm 0.5cm 1.5cm; }
    }
    body {
      margin: 20px;
      font-family: "Times New Roman", serif;
      font-size: 12px; /* match sc_export on screen */
      color: #000;
      background: #fff;
    }
    .instruction-box { background:#fffacd; border:1px solid #ddd; padding:8px; margin-bottom:10px; border-radius:4px; font-size:12px; }
    .print-button { background:#007cba; color:#fff; padding:6px 14px; border:none; border-radius:4px; cursor:pointer; font-size:12px; margin-right:6px; text-decoration:none; }
    .back-link { background:#6c757d; color:#fff; padding:6px 14px; border-radius:4px; font-size:12px; text-decoration:none; }

    .card-wrapper {
      max-width: 1200px;
      margin: 0 auto;
      border: 2px solid #000;
      padding: 12px 12px 16px 12px;
      position: relative;
      background: #fff;
      box-sizing: border-box;
    }
    .title-row { display:flex; justify-content:space-between; align-items:flex-start; }
    .title { text-align:center; font-weight:bold; font-size:18px; margin: 2px 0 10px; letter-spacing:0.5px; flex:1; }
    .annex { font-style:italic; font-size:12px; }

    .meta-top { width:100%; border-collapse:collapse; margin-bottom:6px; }
    .meta-top td { padding:2px 4px; }
  .meta-underline { border-bottom:1px solid #000; display:inline-block; min-width:320px; height:14px; }
  .inline-fill { display:flex; align-items:flex-end; gap:4px; }
  .inline-fill .meta-underline { flex: 1 1 auto; min-width:200px; }
  /* Show only the vertical divider (no box) */
  .spine-only { border: 0 !important; border-left: 1px solid #000 !important; }
  .no-right-border { border-right: 0 !important; }
  .no-bottom-border { border-bottom: 0 !important; }

  .property-card-table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:11px; border:2px solid #000; }
    .property-card-table th, .property-card-table td { border:1px solid #000; padding:4px 6px; text-align:center; vertical-align:middle; }
    .meta-row .meta-cell { border:1px solid #000; padding:4px 6px; text-align:left; font-weight:bold; }
    .meta-line { border-bottom:1px solid #000; display:inline-block; width:100%; height:14px; vertical-align:bottom; }
  .th-ref { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="no-print">
    <div class="instruction-box">
      <strong>Export Instructions:</strong>
      <div>1. Click the Print/Save button below.</div>
      <div>2. In the print dialog choose "Save as PDF" or printer of choice.</div>
      <div>3. Save.</div>
    </div>
    <button class="print-button" onclick="window.print()">Print / Save as PDF</button>
    <a class="back-link" href="view_semi_expendable.php?id=<?php echo $id; ?>">← Back to Property No. <?php echo htmlspecialchars($item['semi_expendable_property_no']); ?></a>
    <hr style="margin:14px 0;">
  </div>

  <div class="card-wrapper">
    <div class="title-row">
      <span></span>
      <div class="title">SEMI-EXPENDABLE PROPERTY CARD</div>
      <div class="annex">Annex A.1</div>
    </div>

    <table class="meta-top">
  <tr>
    <td style="width:60%; font-weight:bold;">Entity Name:&nbsp; <span class="meta-underline">TESDA</span></td>
    <td style="width:40%; font-weight:normal;">
      <div class="inline-fill"><strong>Fund Cluster:</strong> <span class="meta-underline"><?php echo htmlspecialchars($item['fund_cluster'] ?? ''); ?></span></div>
    </td>
  </tr>
    </table>

    <?php
      // Load history rows for this semi item
      $hist_rows = [];
  if ($hstmt = $conn->prepare("SELECT * FROM semi_expendable_history WHERE semi_id = ? ORDER BY created_at ASC, id ASC")) {
        $hstmt->bind_param("i", $id);
        $hstmt->execute();
        $hres = $hstmt->get_result();
    while ($hr = $hres->fetch_assoc()) { $hist_rows[] = $hr; }
        $hstmt->close();
      }
    // Remove only 'Pre-ICS Snapshot' rows from export rendering (show Initial Receipt as old data)
    if (!empty($hist_rows)) {
      $hist_rows = array_values(array_filter($hist_rows, function($hr){
        $remarks = isset($hr['remarks']) ? trim($hr['remarks']) : '';
        return strcasecmp($remarks, 'Pre-ICS Snapshot') !== 0;
      }));
    }
    ?>

    <table class="property-card-table">
      <thead>
        <tr class="meta-row">
          <td colspan="8" class="meta-cell no-right-border">
            <div class="inline-fill"><strong>Semi-expendable Property:</strong> <span><?php echo htmlspecialchars($item['category'] ?? ''); ?></span></div>
          </td>
          <td colspan="3" class="meta-cell no-bottom-border">Semi-expendable Property Number: <span><?php echo htmlspecialchars($item['semi_expendable_property_no'] ?? ''); ?></span></td>
        </tr>
        <tr class="meta-row">
          <td colspan="8" class="meta-cell no-right-border">
            <div class="inline-fill"><strong>Description:</strong> <span><?php echo htmlspecialchars($item['item_description'] ?? ''); ?></span></div>
          </td>
          <td colspan="3" class="meta-cell spine-only"></td>
        </tr>
        <tr>
          <th rowspan="2" style="width:8%">Date</th>
          <th rowspan="2" style="width:12%" class="th-ref">Reference</th>
          <th colspan="3">Receipt</th>
          <th colspan="3">Issue/Transfer/Disposal</th>
          <th>Balance</th>
          <th rowspan="2" style="width:10%">Amount</th>
          <th rowspan="2" style="width:12%">Remarks</th>
        </tr>
        <tr>
          <th>Qty.</th>
          <th>Unit Cost</th>
          <th>Total Cost</th>
          <th>Item No.</th>
          <th>Qty.</th>
          <th>Office/Officer</th>
          <th>Qty.</th>
        </tr>
      </thead>
      <tbody>
        <?php
          // First, render history rows (oldest to newest)
          $rendered = 0;
          if (!empty($hist_rows)) {
              foreach ($hist_rows as $hr) {
                  $h_qtyIssued = (int)($hr['quantity'] ?? ($hr['quantity_issued'] ?? 0));
                  $h_amount = (float)($hr['amount_total'] ?? 0.0);
                  $h_unit = isset($hr['amount']) && $hr['amount'] !== null ? (float)$hr['amount'] : ($h_qtyIssued > 0 ? $h_amount / $h_qtyIssued : 0);
                  $h_issue = (int)($hr['quantity_issued'] ?? 0) + (int)($hr['quantity_disposed'] ?? 0);
        ?>
            <tr>
              <td><?php echo htmlspecialchars($hr['date'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($hr['ics_rrsp_no'] ?? ''); ?></td>
              <td><?php echo $h_qtyIssued ?: ''; ?></td>
              <td><?php echo $h_qtyIssued ? number_format($h_unit, 2) : ''; ?></td>
              <td><?php echo $h_amount ? number_format($h_amount, 2) : ''; ?></td>
              <td><?php echo htmlspecialchars($item['semi_expendable_property_no'] ?? ''); ?></td>
              <td><?php echo $h_issue ?: ''; ?></td>
        <td><?php 
          // Choose the correct Office/Officer based on action for this history row
          $rtext = strtolower(trim($hr['remarks'] ?? ''));
          $h_officer = '';
          if (strpos($rtext, 'returned') !== false || (isset($hr['quantity_returned']) && (int)$hr['quantity_returned'] > 0)) {
            $h_officer = $hr['office_officer_returned'] ?? '';
          } elseif (strpos($rtext, 're-issue') !== false || strpos($rtext, 'reissued') !== false || (isset($hr['quantity_reissued']) && (int)$hr['quantity_reissued'] > 0)) {
            $h_officer = $hr['office_officer_reissued'] ?? '';
          } elseif (strpos($rtext, 'issued') !== false || (isset($hr['quantity_issued']) && (int)$hr['quantity_issued'] > 0)) {
            $h_officer = $hr['office_officer_issued'] ?? '';
          } else {
            // Fallback to any available value
            $h_officer = $hr['office_officer_issued'] ?: ($hr['office_officer_returned'] ?: ($hr['office_officer_reissued'] ?? ''));
          }
          echo htmlspecialchars($h_officer);
        ?></td>
              <td><?php echo htmlspecialchars($hr['quantity_balance'] ?? ''); ?></td>
              <td><?php echo $h_amount ? number_format($h_amount, 2) : ''; ?></td>
              <td><?php 
                    $r = isset($hr['remarks']) ? trim($hr['remarks']) : ''; 
                    echo htmlspecialchars(strcasecmp($r, 'Initial Receipt') === 0 ? '' : $r); 
                  ?></td>
            </tr>
        <?php
                  $rendered++;
              }
          }

          // Only append current snapshot when there is no history at all
          if (empty($hist_rows)) {
              $qtyIssued = (int)($item['quantity'] ?? ($item['quantity_issued'] ?? 0));
              $amountTotal = (float)($item['amount_total'] ?? 0);
              $unitCost = isset($item['amount']) && $item['amount'] !== null ? (float)$item['amount'] : ($qtyIssued > 0 ? $amountTotal / $qtyIssued : 0);
              $qtyIssueTransfer = (int)($item['quantity_issued'] ?? 0) + (int)($item['quantity_disposed'] ?? 0);
        ?>
              <tr>
                <td><?php echo htmlspecialchars($item['date'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($item['ics_rrsp_no'] ?? ''); ?></td>
                <td><?php echo $qtyIssued ?: ''; ?></td>
                <td><?php echo $qtyIssued ? number_format($unitCost, 2) : ''; ?></td>
                <td><?php echo $amountTotal ? number_format($amountTotal, 2) : ''; ?></td>
                <td><?php echo htmlspecialchars($item['semi_expendable_property_no'] ?? ''); ?></td>
                <td><?php echo $qtyIssueTransfer ?: ''; ?></td>
        <td><?php 
            // Choose appropriate Office/Officer for current snapshot (no history)
            $c_officer = '';
            if (!empty($item['office_officer_returned']) || ((int)($item['quantity_returned'] ?? 0)) > 0) {
              $c_officer = $item['office_officer_returned'] ?? '';
            } elseif (!empty($item['office_officer_reissued']) || ((int)($item['quantity_reissued'] ?? 0)) > 0) {
              $c_officer = $item['office_officer_reissued'] ?? '';
            } else {
              $c_officer = $item['office_officer_issued'] ?? '';
            }
            echo htmlspecialchars($c_officer);
          ?></td>
                <td><?php echo htmlspecialchars($item['quantity_balance'] ?? ''); ?></td>
                <td><?php echo $amountTotal ? number_format($amountTotal, 2) : ''; ?></td>
                <td><?php 
                      $r = isset($item['remarks']) ? trim($item['remarks']) : '';
                      echo htmlspecialchars(strcasecmp($r, 'Initial Receipt') === 0 ? '' : $r);
                    ?></td>
              </tr>
        <?php
              $rendered++;
          }
        ?>
        <?php for ($i = $rendered; $i < 20; $i++): ?>
        <tr>
          <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>

</body>
</html>
<?php $conn->close(); ?>

          <td><?php echo htmlspecialchars($item['semi_expendable_property_no'] ?? ''); ?></td>
