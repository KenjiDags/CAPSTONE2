<?php
require 'auth.php';
require 'config.php';

if (!isset($_GET['itr_id'])) {
    die("❌ Error: ITR ID not specified in the URL.");
}
$itr_id = (int)$_GET['itr_id'];

// Fetch ITR header
$itr_res = $conn->query("SELECT * FROM itr WHERE itr_id = $itr_id");
if (!$itr_res) { die("❌ Database error: " . $conn->error); }
if ($itr_res->num_rows === 0) { die("❌ No ITR record found for ITR ID: $itr_id"); }
$itr = $itr_res->fetch_assoc();

// Fetch ITR items
$items_res = $conn->query("SELECT * FROM itr_items WHERE itr_id = $itr_id ORDER BY date_acquired ASC, itr_item_id ASC");

// Load items into an array to avoid reusing the result cursor repeatedly
$items = [];
if ($items_res) {
  while ($row = $items_res->fetch_assoc()) { $items[] = $row; }
  $items_res->free();
}

// Preload maps for unit amounts to compute quantities without many per-row queries
$itemNos = [];
foreach ($items as $it) {
  $v = trim((string)($it['item_no'] ?? ''));
  if ($v !== '') { $itemNos[$v] = true; }
}
$semiMap = []; // key: property_no => amount
if (!empty($itemNos)) {
  $esc = array_map(function($s) use ($conn){ return "'".$conn->real_escape_string($s)."'"; }, array_keys($itemNos));
  $sql = "SELECT semi_expendable_property_no, amount FROM semi_expendable_property WHERE semi_expendable_property_no IN (".implode(',', $esc).")";
  if ($res = $conn->query($sql)) {
    while ($r = $res->fetch_assoc()) { $semiMap[(string)$r['semi_expendable_property_no']] = (float)($r['amount'] ?? 0); }
    $res->close();
  }
}

// Map inventory_item_no -> latest ICS (stock_number, unit_cost, total_cost, quantity)
$icsInvMap = [];
if (!empty($itemNos)) {
  $esc = array_map(function($s) use ($conn){ return "'".$conn->real_escape_string($s)."'"; }, array_keys($itemNos));
  $sql = "SELECT inventory_item_no, stock_number, unit_cost, total_cost, quantity, ics_item_id FROM ics_items WHERE inventory_item_no IN (".implode(',', $esc).") ORDER BY ics_item_id DESC";
  if ($res = $conn->query($sql)) {
    while ($r = $res->fetch_assoc()) {
      $inv = (string)($r['inventory_item_no'] ?? '');
      if ($inv === '') continue;
      if (!isset($icsInvMap[$inv])) { // first is latest due to DESC
        $icsInvMap[$inv] = [
          'stock_number' => (string)($r['stock_number'] ?? ''),
          'unit_cost' => (float)($r['unit_cost'] ?? 0),
          'total_cost' => (float)($r['total_cost'] ?? 0),
          'quantity' => (float)($r['quantity'] ?? 0)
        ];
      }
    }
    $res->close();
  }
}

// For all discovered stock_numbers, map to latest ICS costs and also semi amounts by stock number
$stockSet = [];
foreach ($icsInvMap as $rec) { $sn = trim((string)($rec['stock_number'] ?? '')); if ($sn !== '') $stockSet[$sn] = true; }
if (!empty($stockSet)) {
  $esc = array_map(function($s) use ($conn){ return "'".$conn->real_escape_string($s)."'"; }, array_keys($stockSet));
  // ICS by stock
  $sqlS = "SELECT stock_number, unit_cost, total_cost, quantity, ics_item_id FROM ics_items WHERE stock_number IN (".implode(',', $esc).") ORDER BY ics_item_id DESC";
  $icsStockMap = [];
  if ($resS = $conn->query($sqlS)) {
    while ($r = $resS->fetch_assoc()) {
      $sn = (string)($r['stock_number'] ?? '');
      if ($sn === '') continue;
      if (!isset($icsStockMap[$sn])) {
        $icsStockMap[$sn] = [
          'unit_cost' => (float)($r['unit_cost'] ?? 0),
          'total_cost' => (float)($r['total_cost'] ?? 0),
          'quantity' => (float)($r['quantity'] ?? 0)
        ];
      }
    }
    $resS->close();
  }
  // Semi by stock
  $sqlSemiS = "SELECT semi_expendable_property_no, amount FROM semi_expendable_property WHERE semi_expendable_property_no IN (".implode(',', $esc).")";
  if ($resSS = $conn->query($sqlSemiS)) {
    while ($r = $resSS->fetch_assoc()) {
      $semiMap[(string)$r['semi_expendable_property_no']] = (float)($r['amount'] ?? 0);
    }
    $resSS->close();
  }
} else {
  $icsStockMap = [];
}

// Now compute total amount from the in-memory items array
$total_amount = 0.0;
foreach ($items as $r) { $total_amount += (float)($r['amount'] ?? 0); }

// Compute total amount (will be recalculated after we load items into $items)
$total_amount = 0.0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View ITR - TESDA Inventory System</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
</head>
<body class="view-ris-page">
  <?php include 'sidebar.php'; ?>
  <div class="content">
    <h2>📋 Viewing ITR No. <?php echo htmlspecialchars($itr['itr_no']); ?></h2>

    <!-- Action Buttons -->
    <div class="ris-actions">
      <a href="itr.php" class="btn btn-secondary">← Back to ITR List</a>
      <a href="edit_itr.php?itr_id=<?php echo $itr_id; ?>" class="btn btn-primary">✏️ Edit ITR</a>
    </div>

    <!-- ITR Details -->
    <div class="ris-details">
      <p><strong>Entity Name:</strong> <?php echo htmlspecialchars($itr['entity_name'] ?? ''); ?></p>
      <p><strong>Fund Cluster:</strong> <?php echo htmlspecialchars($itr['fund_cluster'] ?? ''); ?></p>
      <p><strong>ITR No.:</strong> <?php echo htmlspecialchars($itr['itr_no'] ?? ''); ?></p>
      <p><strong>Date:</strong> <?php echo htmlspecialchars($itr['itr_date'] ?? ''); ?></p>
      <p><strong>From Accountable:</strong> <?php echo htmlspecialchars($itr['from_accountable'] ?? ''); ?></p>
      <p><strong>To Accountable:</strong> <?php echo htmlspecialchars($itr['to_accountable'] ?? ''); ?></p>
      <p><strong>Transfer Type:</strong> <?php echo htmlspecialchars($itr['transfer_type'] ?? ''); ?></p>
      <?php if (!empty($itr['transfer_other'])): ?>
        <p><strong>Other Transfer Type:</strong> <?php echo htmlspecialchars($itr['transfer_other']); ?></p>
      <?php endif; ?>
      <?php if (!empty($itr['reason'])): ?>
        <p><strong>Reason:</strong> <?php echo nl2br(htmlspecialchars($itr['reason'])); ?></p>
      <?php endif; ?>
      <?php if (!empty($itr['remarks'])): ?>
        <p><strong>Remarks:</strong> <?php echo nl2br(htmlspecialchars($itr['remarks'])); ?></p>
      <?php endif; ?>
    </div>

    <h3>📦 Items</h3>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th style="white-space:nowrap;">Date Acquired</th>
            <th>Item No.</th>
            <th>ICS No./Date</th>
            <th>Description</th>
            <th>Quantity</th>
            <th>Condition</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($items)): ?>
            <?php foreach ($items as $item): ?>
              <tr>
                <td><?php echo htmlspecialchars($item['date_acquired'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($item['item_no'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($item['ics_info'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
        <?php
          // Derive quantity via preloaded maps
          $itemNo = trim((string)($item['item_no'] ?? ''));
          $amtVal = (float)($item['amount'] ?? 0);
          $unitAmt = 0.0; $stock = null; $ics_uc = 0.0; $ics_tot = 0.0; $ics_qty = 0.0;
          if ($itemNo !== '' && isset($semiMap[$itemNo]) && $semiMap[$itemNo] > 0) {
            $unitAmt = $semiMap[$itemNo];
          }
          if ($unitAmt <= 0 && isset($icsInvMap[$itemNo])) {
            $stock = $icsInvMap[$itemNo]['stock_number'] ?? null;
            $ics_uc = (float)($icsInvMap[$itemNo]['unit_cost'] ?? 0);
            $ics_tot = (float)($icsInvMap[$itemNo]['total_cost'] ?? 0);
            $ics_qty = (float)($icsInvMap[$itemNo]['quantity'] ?? 0);
          }
          if ($unitAmt <= 0 && $stock && isset($semiMap[$stock]) && $semiMap[$stock] > 0) {
            $unitAmt = $semiMap[$stock];
          }
          if ($unitAmt <= 0 && $stock && isset($icsStockMap[$stock])) {
            $ics_uc = max($ics_uc, (float)($icsStockMap[$stock]['unit_cost'] ?? 0));
            $ics_tot = max($ics_tot, (float)($icsStockMap[$stock]['total_cost'] ?? 0));
            $ics_qty = max($ics_qty, (float)($icsStockMap[$stock]['quantity'] ?? 0));
          }
          if ($unitAmt <= 0) {
            if ($ics_uc > 0) { $unitAmt = $ics_uc; }
            elseif ($ics_tot > 0 && $ics_qty > 0) { $unitAmt = $ics_tot / max(1.0, $ics_qty); }
          }
          $qtyDisp = ($unitAmt > 0) ? (int)floor($amtVal / $unitAmt) : '';
        ?>
                <td><?php echo ($qtyDisp === '' ? '' : (int)$qtyDisp); ?></td>
                <td><?php echo htmlspecialchars($item['cond'] ?? ''); ?></td>
                <td>₱<?php echo number_format((float)($item['amount'] ?? 0), 2); ?></td>
              </tr>
      <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7">No items found for this ITR.</td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="6" style="text-align:right;"><strong>Grand Total:</strong></td>
            <td><strong>₱<?php echo number_format($total_amount, 2); ?></strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php // (No prepared statements to clean up; all results already closed) ?>

    <h3>✍️ Signatories</h3>
    <div class="ris-details">
      <p><strong>Approved by:</strong> <?php echo htmlspecialchars($itr['approved_name'] ?? ''); ?></p>
      <p><strong>Designation (Approved):</strong> <?php echo htmlspecialchars($itr['approved_designation'] ?? ''); ?></p>
      <p><strong>Approved Date:</strong> <?php echo htmlspecialchars($itr['approved_date'] ?? ''); ?></p>
      <p><strong>Released/Issued by:</strong> <?php echo htmlspecialchars($itr['released_name'] ?? ''); ?></p>
      <p><strong>Designation (Released):</strong> <?php echo htmlspecialchars($itr['released_designation'] ?? ''); ?></p>
      <p><strong>Released Date:</strong> <?php echo htmlspecialchars($itr['released_date'] ?? ''); ?></p>
      <p><strong>Received by:</strong> <?php echo htmlspecialchars($itr['received_name'] ?? ''); ?></p>
      <p><strong>Designation (Received):</strong> <?php echo htmlspecialchars($itr['received_designation'] ?? ''); ?></p>
      <p><strong>Received Date:</strong> <?php echo htmlspecialchars($itr['received_date'] ?? ''); ?></p>
    </div>
  </div>
  <script src="js/view_ris_script.js?v=<?= time() ?>"></script>
</body>
</html>
<?php $conn->close(); ?>
