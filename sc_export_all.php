<?php
require 'auth.php';
require 'config.php';

// Fetch all items with history
$item_stmt = $conn->prepare("
    SELECT i.*
    FROM items i
    INNER JOIN item_history ih ON i.item_id = ih.item_id
    GROUP BY i.item_id
    ORDER BY i.stock_number ASC
");
$item_stmt->execute();
$items_result = $item_stmt->get_result();
if (!$items_result || $items_result->num_rows === 0) {
    die("❌ No items found with history.");
}

$stock_cards = [];
while ($item = $items_result->fetch_assoc()) {
    // Fetch history for this item
    $history_stmt = $conn->prepare("
        SELECT ih.*, r.ris_no AS ris_no
        FROM item_history ih
        LEFT JOIN ris r ON ih.ris_id = r.ris_id
        WHERE ih.item_id = ?
        ORDER BY ih.changed_at DESC
    ");
    $history_stmt->bind_param("i", $item['item_id']);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();

    $history_rows = [];
    while ($row = $history_result->fetch_assoc()) {
        $history_rows[] = $row;
    }
    $history_stmt->close();

    $stock_cards[] = [
        'item' => $item,
        'history' => $history_rows
    ];
}

$item_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Export All Stock Cards</title>
<style>
@media print {
    .no-print { display:none !important; }
  .card-wrapper { page-break-inside: avoid; page-break-after: always; border: none !important; padding-top: 20px !important; }
}

body {
    font-family: "Times New Roman", serif;
    font-size: 12px;
    color: #000;
    margin: 20px;
}

.card-wrapper {
    max-width: 800px;
    margin: 0 auto 40px auto;
    border: 2px solid #000;
  padding: 20px 12px 16px;
    position: relative;
}

.appendix {
    position: absolute;
    top: 8px;
    right: 12px;
    font-size: 11px;
    font-style: italic;
}

.agency-header {
  position: relative;
  text-align: center;
  padding-top: 12px;
  padding-bottom: 12px;
}

.agency-header img {
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 60px;
  height: 60px;
  object-fit: contain;
}

.agency-text {
  text-align: center;
  line-height: 1.2;
  display: inline-block;
}

.title {
    text-align: center;
    font-weight: bold;
    font-size: 18px;
    margin: 4px 0 15px;
    letter-spacing: 1px;
}

.stock-card-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
    font-size: 11px;
}
.stock-card-table th,
.stock-card-table td {
    border: 1px solid #000;
    padding: 4px 6px;
    text-align: center;
    vertical-align: middle;
}
.stock-card-table th {
    font-weight: bold;
}

.table-main-title {
  text-align: center !important;
  font-weight: bold !important;
  font-size: 18px !important;
  letter-spacing: 1px;
  padding: 8px 6px !important;
  border-bottom: none !important;
}

.label-row th {
    text-align: left;
    font-weight: bold;
}
.label-row th span {
    font-weight: normal;
}
.underline {
    border-bottom: 1px solid #000;
    display: inline-block;
    min-width: 100px;
}

.no-history {
    font-style: italic;
    color: #444;
}

.print-button {
    background: #007cba;
    color: white;
    padding: 6px 14px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    margin-bottom: 12px;
}
.back-link {
    background: #6c757d;
    color: white;
    padding: 6px 14px;
    border-radius: 4px;
    font-size: 12px;
    text-decoration: none;
}
.instruction-box {
    background: #fffacd;
    border: 1px solid #ddd;
    padding: 8px;
    margin-bottom: 10px;
    border-radius: 4px;
    font-size: 12px;
}
</style>
</head>
<body>

<div class="no-print">
    <div class="instruction-box">
      <strong>📄 Export Instructions:</strong>
      <div>1. Click the Print/Save button below.</div>
      <div>2. In the print dialog choose "Save as PDF" or printer of choice.</div>
      <div>3. Save.</div>
    </div>
    <button class="print-button" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <a class="back-link" href="SC.php">← Back to SC</a>
    <hr style="margin:14px 0;">
</div>

<?php foreach ($stock_cards as $data):
    $item = $data['item'];
    $history_rows = $data['history'];
?>
<div class="card-wrapper">
    <div class="appendix">Appendix 53</div>

    <div class="agency-header">
      <img src="images/TESDA-Logo-export.png" alt="TESDA Logo">
      <div class="agency-text">
        <div>Republic of the Philippines</div>
        <div><strong>TECHNICAL EDUCATION &amp; SKILLS DEVELOPMENT AUTHORITY</strong></div>
        <div>Cordillera Administrative Region</div>
      </div>
    </div>

    <table class="stock-card-table">
      <thead>
        <tr>
          <th colspan="7" class="table-main-title">STOCK CARD</th>
        </tr>

        <tr class="label-row">
          <th colspan="5" style="border: none; border-left: 1px solid #000;">LGU: <span class="underline" style="min-width: 200px;">TESDA</span></th>
          <th colspan="2" style="border: none; border-right: 1px solid #000;">Fund: <span class="underline" style="min-width: 100px;">(TEMP DATA)</span></th>
        </tr>
        <tr class="label-row">
          <th colspan="5">Item: <span><?= htmlspecialchars($item['item_name']); ?></span></th>
          <th colspan="2">Stock No.: <span><?= htmlspecialchars($item['stock_number']); ?></span></th>
        </tr>
        <tr class="label-row">
          <th colspan="5">Description: <span><?= htmlspecialchars($item['description']); ?></span></th>
          <th colspan="2">Re-order Point: <span><?= htmlspecialchars($item['reorder_point']); ?></span></th>
        </tr>
        <tr class="label-row">
          <th colspan="5">Unit of Measurement: <span><?= htmlspecialchars($item['unit']); ?></span></th>
          <th colspan="2"></th>
        </tr>
        <tr>
          <th rowspan="2" style="width: 20%">Date</th>
          <th rowspan="2" style="width: 20%;">Reference</th>
          <th colspan="1" style="width: 10%;">Receipt</th>
          <th colspan="2" style="width: 25%;">Issue</th>
          <th rowspan="1" style="width: 10%;">Balance</th>
          <th rowspan="2" style="width: 15%;">No. of Days to Consume</th>
        </tr>
        <tr>
          <th>Qty.</th>
          <th style="width: 5%;">Qty.</th>
          <th>Office</th>
          <th>Qty.</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($history_rows) > 0): 
            foreach ($history_rows as $h):
              $date = date('M d, Y', strtotime($h['changed_at']));
              $reference = !empty($h['ris_no']) ? htmlspecialchars($h['ris_no']) : htmlspecialchars($item['iar']);
              $receipt_qty = $h['quantity_change'] > 0 ? htmlspecialchars($h['quantity_change']) : '';
              $issue_qty = $h['quantity_change'] < 0 ? abs(htmlspecialchars($h['quantity_change'])) : '';
              $office = ''; // add office info if available
              $balance = htmlspecialchars($h['quantity_on_hand']);
              $days = '--';
        ?>
        <tr>
          <td><?= $date; ?></td>
          <td><?= $reference; ?></td>
          <td><?= $receipt_qty; ?></td>
          <td><?= $issue_qty; ?></td>
          <td><?= $office; ?></td>
          <td><?= $balance; ?></td>
          <td><?= $days; ?></td>
        </tr>
        <?php endforeach; 
        else: ?>
          <tr>
            <td>--</td><td>--</td><td>0</td><td>0</td><td>--</td><td>0</td><td>--</td>
          </tr>
        <?php endif; ?>

        <?php for ($i=0; $i<max(0,20-count($history_rows)); $i++): ?>
          <tr>
            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
</div>
<?php endforeach; ?>

</body>
</html>