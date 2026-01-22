<?php
require 'auth.php';
require 'config.php';
require_once 'functions.php';
// Ensure history table exists to avoid runtime errors
ensure_semi_expendable_history($conn);

// Optional filters (match PC_semi)
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';

$params = [];
$types  = '';
$where  = [];
if ($category !== '') { $where[] = 'category = ?'; $params[] = $category; $types .= 's'; }
if ($search !== '') {
    $where[] = '(item_description LIKE ? OR semi_expendable_property_no LIKE ? OR ics_rrsp_no LIKE ?)';
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss';
}

$sql = "SELECT id, date, ics_rrsp_no, semi_expendable_property_no, item_description, 
               estimated_useful_life, quantity_issued, office_officer_issued, quantity_returned,
               office_officer_returned, quantity_reissued, office_officer_reissued, quantity_disposed,
               quantity_balance, amount_total, category, fund_cluster, remarks
        FROM semi_expendable_property";
if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY date DESC, id DESC';

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = false;
    }
} else {
    $result = $conn->query($sql);
}

if (!$result || $result->num_rows === 0) {
    die('No Semi-Expendable records found for export.');
}

// Collect all rows
$rows = [];
while ($r = $result->fetch_assoc()) { $rows[] = $r; }
if (isset($stmt) && $stmt) { $stmt->close(); }
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Export All Semi-Expendable Property Cards</title>
  <style>
    @media print {
      .no-print { display:none !important; }
      @page { size: landscape; margin: 0.5cm 1.5cm 0.5cm 1.5cm; }
      .page-break { page-break-after: always; }
    }
    body { margin: 20px; font-family: "Times New Roman", serif; font-size: 12px; color: #000; background: #fff; }
  .print-button { background:#007cba; color:#fff; padding:6px 14px; border:none; border-radius:4px; cursor:pointer; font-size:12px; margin-right:6px; text-decoration:none; }
  .back-link { background:#6c757d; color:#fff; padding:6px 14px; border-radius:4px; font-size:12px; text-decoration:none; }
    .instruction-box { background:#fffacd; border:1px solid #ddd; padding:8px; margin-bottom:10px; border-radius:4px; font-size:12px; }

    .card-wrapper { max-width: 1200px; margin: 0 auto; border: 2px solid #000; padding: 12px 12px 16px 12px; position: relative; background: #fff; box-sizing: border-box; }
    .title-row { display:flex; justify-content:space-between; align-items:flex-start; }
    .title { text-align:center; font-weight:bold; font-size:18px; margin: 2px 0 10px; letter-spacing:0.5px; flex:1; }
    .annex { font-style:italic; font-size:12px; }
    .meta-top { width:100%; border-collapse:collapse; margin-bottom:6px; }
    .meta-top td { padding:2px 4px; }
    .meta-underline { border-bottom:1px solid #000; display:inline-block; min-width:320px; height:14px; }
    .inline-fill { display:flex; align-items:flex-end; gap:4px; }
    .inline-fill .meta-underline { flex: 1 1 auto; min-width:200px; }
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
  <div class="no-print" style="margin-bottom:12px;">
    <div class="instruction-box">
      <strong>Export Instructions:</strong>
      <div>1. Click the Print/Save button below.</div>
      <div>2. In the print dialog choose "Save as PDF" or printer of choice.</div>
      <div>3. Save.</div>
    </div>
    <button class="print-button" onclick="window.print()">Print / Save as PDF</button>
    <?php
      // Build a back URL to the list with current filters
      $backUrl = 'semi_expendible.php';
      $qs = [];
      if ($category !== '') { $qs['category'] = $category; }
      if ($search !== '') { $qs['search'] = $search; }
      if (!empty($qs)) { $backUrl .= '?' . http_build_query($qs); }
    ?>
    <a class="back-link" href="<?php echo htmlspecialchars($backUrl); ?>">← Back</a>
    <hr style="margin:14px 0;">
  </div>

  <?php foreach ($rows as $index => $item): ?>
    <?php
  $qtyIssued = (int)($item['quantity_issued'] ?? 0);
  $amountTotal = (float)($item['amount_total'] ?? 0);
  $unitCost = isset($item['amount']) && $item['amount'] !== null ? (float)$item['amount'] : ($qtyIssued > 0 ? $amountTotal / $qtyIssued : 0);
      $qtyIssueTransfer = (
        (int)($item['quantity_issued'] ?? 0) +
        (int)($item['quantity_reissued'] ?? 0) +
        (int)($item['quantity_disposed'] ?? 0)
      );
      // Load history rows for this semi item
      $hist_rows = [];
      // Lazy connection not available here (closed above), so reuse collected data only if needed.
    ?>
    <?php
      // We need a connection to load history; reopen a lightweight one
  require 'config.php';
  require_once 'functions.php';
  ensure_semi_expendable_history($conn);
  if ($stmtH = $conn->prepare("SELECT * FROM semi_expendable_history WHERE semi_id = ? ORDER BY created_at ASC, id ASC")) {
        $sid = (int)$item['id'];
        $stmtH->bind_param("i", $sid);
        $stmtH->execute();
        $resH = $stmtH->get_result();
        while ($hr = $resH->fetch_assoc()) { $hist_rows[] = $hr; }
        $stmtH->close();
      }
    // Remove only 'Pre-ICS Snapshot' rows from export rendering (show Initial Receipt as old data)
    if (!empty($hist_rows)) {
      $hist_rows = array_values(array_filter($hist_rows, function($hr){
        $remarks = isset($hr['remarks']) ? trim(strtolower($hr['remarks'])) : '';
        $ref = isset($hr['ics_rrsp_no']) ? strtolower($hr['ics_rrsp_no']) : '';
        // Exclude Pre-ICS Snapshot and any RRSP-related rows (by reference, remarks, or typical RRSP fields)
        $is_rrsp = strpos($ref, 'rrsp') !== false
          || strpos($remarks, 'rrsp') !== false
          || (isset($hr['quantity_returned']) && (int)$hr['quantity_returned'] > 0 && empty($hr['quantity_issued']) && empty($hr['quantity_disposed']) && empty($hr['quantity_reissued']));
        return strcasecmp($remarks, 'pre-ics snapshot') !== 0 && !$is_rrsp;
      }));
    }
      $conn->close();
    ?>
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
            // Render all history rows first (oldest to newest)
            $rendered = 0;
            if (!empty($hist_rows)) {
                foreach ($hist_rows as $hr) {
                    $h_qtyIssued = (int)($hr['quantity'] ?? ($hr['quantity_issued'] ?? 0));
                    $h_amount = (float)($hr['amount_total'] ?? 0.0);
                    $h_unit = isset($hr['amount']) && $hr['amount'] !== null ? (float)$hr['amount'] : ($h_qtyIssued > 0 ? $h_amount / $h_qtyIssued : 0);
                    // Issue/Transfer/Disposal should show only the first non-zero value (no addition)
                    if ((int)($hr['quantity_disposed'] ?? 0) > 0) {
                      $h_issue = (int)$hr['quantity_disposed'];
                    } elseif ((int)($hr['quantity_reissued'] ?? 0) > 0) {
                      $h_issue = (int)$hr['quantity_reissued'];
                    } elseif ((int)($hr['quantity_issued'] ?? 0) > 0) {
                      $h_issue = (int)$hr['quantity_issued'];
                    } else {
                      $h_issue = 0;
                    }
                    // Determine officer for this history row ahead of rendering
                    $rtext = strtolower(trim($hr['remarks'] ?? ''));
                    $h_officer = '';
                    if (strpos($rtext, 'returned') !== false || (isset($hr['quantity_returned']) && (int)$hr['quantity_returned'] > 0)) {
                      $h_officer = $hr['office_officer_returned'] ?? '';
                    } elseif (strpos($rtext, 're-issue') !== false || strpos($rtext, 'reissued') !== false || (isset($hr['quantity_reissued']) && (int)$hr['quantity_reissued'] > 0)) {
                      $h_officer = $hr['office_officer_reissued'] ?? '';
                    } elseif (strpos($rtext, 'issued') !== false || (isset($hr['quantity_issued']) && (int)$hr['quantity_issued'] > 0)) {
                      $h_officer = $hr['office_officer_issued'] ?? '';
                    } else {
                      $h_officer = $hr['office_officer_issued'] ?: ($hr['office_officer_returned'] ?: ($hr['office_officer_reissued'] ?? ''));
                    }
                    // Always show Item No. when there is any issuance activity, regardless of officer field
                    $showItemNo = ($h_issue > 0);
          ?>
              <tr>
                <td><?php echo htmlspecialchars($hr['date'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($hr['ics_rrsp_no'] ?? ''); ?></td>
                <td><?php echo ($h_issue > 0) ? '' : ($h_qtyIssued ?: ''); ?></td>
                <td><?php echo ($h_issue > 0) ? '' : ($h_qtyIssued ? number_format($h_unit, 2) : ''); ?></td>
                <td><?php echo ($h_issue > 0) ? '' : ($h_amount ? number_format($h_amount, 2) : ''); ?></td>
                <td><?php echo $showItemNo ? htmlspecialchars($item['semi_expendable_property_no'] ?? '') : ''; ?></td>
                <td><?php echo $h_issue ?: ''; ?></td>
        <td><?php echo htmlspecialchars($h_officer); ?></td>
                <td><?php echo htmlspecialchars($hr['quantity_balance'] ?? ''); ?></td>
                <td>
                  <?php
                    $balance_qty = isset($hr['quantity_balance']) ? (float)$hr['quantity_balance'] : 0;
                    $unit_cost = isset($hr['amount']) ? (float)$hr['amount'] : 0;
                    $amount = $balance_qty * $unit_cost;
                    echo $amount ? number_format($amount, 2) : '';
                  ?>
                </td>
                <td><?php 
                      $r = isset($hr['remarks']) ? trim($hr['remarks']) : ''; 
                      echo htmlspecialchars(strcasecmp($r, 'Initial Receipt') === 0 ? '' : $r); 
                    ?></td>
              </tr>
          <?php
                    $rendered++;
                }
            }

            // Append current snapshot only when there is no history at all
            if (empty($hist_rows)) {
                $qtyIssued = (int)($item['quantity'] ?? ($item['quantity_issued'] ?? 0));
                $amountTotal = (float)($item['amount_total'] ?? 0);
                $unitCost = isset($item['amount']) && $item['amount'] !== null ? (float)$item['amount'] : ($qtyIssued > 0 ? $amountTotal / $qtyIssued : 0);
                // Include reissued in Issue/Transfer/Disposal summary
                $issue_qty = (int)($item['quantity_issued'] ?? 0)
                           + (int)($item['quantity_reissued'] ?? 0)
                           + (int)($item['quantity_disposed'] ?? 0);
                // Determine officer for snapshot ahead of rendering
                $c_officer = '';
                if (!empty($item['office_officer_returned']) || ((int)($item['quantity_returned'] ?? 0)) > 0) {
                  $c_officer = $item['office_officer_returned'] ?? '';
                } elseif (!empty($item['office_officer_reissued']) || ((int)($item['quantity_reissued'] ?? 0)) > 0) {
                  $c_officer = $item['office_officer_reissued'] ?? '';
                } else {
                  $c_officer = $item['office_officer_issued'] ?? '';
                }
                // Likewise for snapshot rows: show Item No. whenever there's issuance activity
                $showItemNoSnap = ($issue_qty > 0);
          ?>
          <tr>
            <td><?php echo htmlspecialchars($item['date'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($item['ics_rrsp_no'] ?? ''); ?></td>
            <td><?php echo ($issue_qty > 0) ? '' : ($qtyIssued ?: ''); ?></td>
            <td><?php echo ($issue_qty > 0) ? '' : ($qtyIssued ? number_format($unitCost, 2) : ''); ?></td>
            <td><?php echo ($issue_qty > 0) ? '' : ($amountTotal ? number_format($amountTotal, 2) : ''); ?></td>
            <td><?php echo $showItemNoSnap ? htmlspecialchars($item['semi_expendable_property_no'] ?? '') : ''; ?></td>
            <td><?php echo $issue_qty ?: ''; ?></td>
      <td><?php echo htmlspecialchars($c_officer); ?></td>
            <td><?php echo htmlspecialchars($item['quantity_balance'] ?? ''); ?></td>
            <td>
              <?php
                $balance_qty = isset($item['quantity_balance']) ? (float)$item['quantity_balance'] : 0;
                $unit_cost = isset($item['amount']) ? (float)$item['amount'] : 0;
                $amount = $balance_qty * $unit_cost;
                echo $amount ? number_format($amount, 2) : '';
              ?>
            </td>
            <td><?php 
                  $r = isset($item['remarks']) ? trim($item['remarks']) : '';
                  echo htmlspecialchars(strcasecmp($r, 'Initial Receipt') === 0 ? '' : $r);
                ?></td>
          </tr>
          <?php $rendered++; } ?>
          <?php for ($i = $rendered; $i < 20; $i++): ?>
            <tr>
              <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>

    <?php if ($index < count($rows) - 1): ?>
      <div class="page-break"></div>
    <?php endif; ?>

  <?php endforeach; ?>

</body>
</html>