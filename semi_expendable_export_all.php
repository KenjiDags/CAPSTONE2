<?php
require 'config.php';

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
  </div>

  <?php foreach ($rows as $index => $item): ?>
    <?php
      $qtyIssued = (int)($item['quantity_issued'] ?? 0);
      $amountTotal = (float)($item['amount_total'] ?? 0);
      $unitCost = $qtyIssued > 0 ? $amountTotal / $qtyIssued : 0;
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
      if ($stmtH = $conn->prepare("SELECT * FROM semi_expendable_history WHERE semi_id = ? ORDER BY created_at DESC, id DESC")) {
        $sid = (int)$item['id'];
        $stmtH->bind_param("i", $sid);
        $stmtH->execute();
        $resH = $stmtH->get_result();
        while ($hr = $resH->fetch_assoc()) { $hist_rows[] = $hr; }
        $stmtH->close();
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
            <td colspan="5" class="meta-cell no-right-border">
              <div class="inline-fill"><strong>Semi-expendable Property:</strong> <span><?php echo htmlspecialchars($item['category'] ?? ''); ?></span></div>
            </td>
            <td colspan="2" class="meta-cell no-bottom-border">Semi-expendable Property Number: <span><?php echo htmlspecialchars($item['semi_expendable_property_no'] ?? ''); ?></span></td>
          </tr>
          <tr class="meta-row">
            <td colspan="7" class="meta-cell">
              <div class="inline-fill"><strong>Description:</strong> <span><?php echo htmlspecialchars($item['item_description'] ?? ''); ?></span></div>
            </td>
          </tr>
          <tr>
            <th rowspan="2" style="width:10%">Date</th>
            <th rowspan="2" style="width:16%" class="th-ref">Reference</th>
            <th>Receipt Qty.</th>
            <th colspan="2">Issue</th>
            <th rowspan="2" style="width:12%">Balance Qty.</th>
            <th rowspan="2" style="width:14%">Days to Consume</th>
          </tr>
          <tr>
            <th style="width:12%">Qty.</th>
            <th style="width:12%">Qty.</th>
            <th style="width:24%">Office</th>
          </tr>
        </thead>
        <tbody>
          <?php 
            // Render current snapshot first
            $issue_qty = (int)($item['quantity_reissued'] ?? 0) + (int)($item['quantity_disposed'] ?? 0);
            $office = ($item['office_officer_reissued'] ?? '') ?: ($item['office_officer_issued'] ?? '');
          ?>
          <tr>
            <td><?php echo htmlspecialchars($item['date'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($item['ics_rrsp_no'] ?? ''); ?></td>
            <td><?php echo $qtyIssued ?: ''; ?></td>
            <td><?php echo $issue_qty ?: ''; ?></td>
            <td><?php echo htmlspecialchars($office); ?></td>
            <td><?php echo htmlspecialchars($item['quantity_balance'] ?? ''); ?></td>
            <td>--</td>
          </tr>
          <?php $rendered = 1; ?>
          <?php if (!empty($hist_rows)): ?>
            <?php foreach ($hist_rows as $hr): ?>
              <?php 
                $h_issue = (int)($hr['quantity_reissued'] ?? 0) + (int)($hr['quantity_disposed'] ?? 0);
                $h_office = ($hr['office_officer_reissued'] ?? '') ?: ($hr['office_officer_issued'] ?? '');
              ?>
              <tr>
                <td><?php echo htmlspecialchars($hr['date'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($hr['ics_rrsp_no'] ?? ''); ?></td>
                <td><?php echo (int)($hr['quantity_issued'] ?? 0) ?: ''; ?></td>
                <td><?php echo $h_issue ?: ''; ?></td>
                <td><?php echo htmlspecialchars($h_office); ?></td>
                <td><?php echo htmlspecialchars($hr['quantity_balance'] ?? ''); ?></td>
                <td>--</td>
              </tr>
              <?php $rendered++; ?>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php for ($i = $rendered; $i < 20; $i++): ?>
            <tr>
              <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
              <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
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