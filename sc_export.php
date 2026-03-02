<?php
require 'config.php';
require 'auth.php';

if (!isset($_GET['item_id'])) {
    die("❌ Error: item not found.");
}

$item_id = (int)$_GET['item_id'];
if ($item_id <= 0) {
    die("❌ Invalid item ID.");
}

// Fetch item
$item_stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
$item_stmt->bind_param("i", $item_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();
if (!$item_result || $item_result->num_rows === 0) {
    die("❌ No record found for Item ID $item_id.");
}
$items = $item_result->fetch_assoc();
$item_stmt->close();

$history_stmt = $conn->prepare("
  SELECT ih.*, r.ris_no AS ris_no
  FROM item_history ih
  LEFT JOIN ris r ON ih.ris_id = r.ris_id
  WHERE ih.item_id = ?
    
  ORDER BY ih.changed_at DESC
");
$history_stmt->bind_param("i", $item_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$history_rows = [];
if ($history_result && $history_result->num_rows > 0) {
    while ($row = $history_result->fetch_assoc()) {
        $history_rows[] = $row;
    }
}
$history_stmt->close();

// Entity (LGU)
$ris = ['entity_name' => 'TESDA'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Item ID: <?php echo htmlspecialchars($items['item_id']); ?> - Export</title>
  <style>
    @media print {
      .no-print { display:none !important; }
      .card-wrapper {
        border: none !important;
      }
    }
    body {
      margin: 20px;
      font-family: "Times New Roman", serif;
      font-size: 12px;
      color: #000;
    }
    .card-wrapper {
      max-width: 800px;
      margin: 0 auto;
      border: 2px solid #000;
      padding: 8px 12px 16px;
      position: relative;
    }
    .appendix {
      position: absolute;
      top: 8px;
      right: 12px;
      font-size: 11px;
      font-style: italic;
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

    .sub-header {
      border-left: none;
      border-right: none;
      padding: 0;
    }
    .small {
      font-size: 10px;
    }
    .controls {
      margin-bottom: 12px;
    }
    .print-button {
      background: #007cba;
      color: white;
      padding: 6px 14px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      margin-right: 6px;
      text-decoration: none;
    }

    .print-button:hover { 
      filter: brightness(0.95); 
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
    .no-history {
      font-style: italic;
      color: #444;
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
    <a class="back-link" href="view_sc.php?item_id=<?php echo $item_id; ?>">← Back to Item No. <?php echo htmlspecialchars($items['item_id']); ?></a>
    <hr style="margin:14px 0;">
  </div>

  <div class="card-wrapper">
    <div class="appendix">Appendix 53</div>
    <div class="title">STOCK CARD</div>

    <table class="stock-card-table">
      <thead>

        <tr class="label-row">
          <th colspan="5" style="border: none;">LGU: <span class="underline" style="min-width: 200px;"><?php echo htmlspecialchars($ris['entity_name']); ?></span></th>
          <th colspan="2" style="border: none;">Fund: <span class="underline" style="min-width: 100px;">(TEMP DATA)</span></th>
        </tr>

        <tr class ="label-row">
          <th colspan="5">Item: <span><?php echo htmlspecialchars($items['item_name']); ?></span></th>
          <th colspan="2">Stock No. <span><?php echo htmlspecialchars($items['stock_number']); ?></span></th>
        </tr>
        <tr class="label-row">
          <th colspan="5">Description: <span><?php echo htmlspecialchars($items['description']); ?></span></th>
          <th colspan="2">Re-order Point: <span><?php echo htmlspecialchars($items['reorder_point']); ?></span></th>
        </tr>
        <tr class="label-row">
          <th colspan="5">Unit of Measurement: <span><?php echo htmlspecialchars($items['unit']); ?></span></th>
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
        <?php
        if (count($history_rows) > 0): 
          foreach ($history_rows as $h):
            $date = date('M d, Y', strtotime($h['changed_at']));
            $reference = !empty($h['ris_no']) ? htmlspecialchars($h['ris_no']) : htmlspecialchars($items['iar']);
            $receipt_qty = $h['quantity_change'] > 0 ? htmlspecialchars($h['quantity_change']) : '';
            $issue_qty = $h['quantity_change'] < 0 ? abs(htmlspecialchars($h['quantity_change'])) : '';
            $office = ''; // fill if you have office info
            $balance = htmlspecialchars($h['quantity_on_hand']);
            $days = '--'; // placeholder
        ?>
            <tr>
              <td><?php echo $date; ?></td>
              <td><?php echo $reference; ?></td>
              <td><?php echo $receipt_qty; ?></td>
              <td><?php echo $issue_qty; ?></td>
              <td><?php echo $office; ?></td>
              <td><?php echo $balance; ?></td>
              <td><?php echo $days; ?></td>
            </tr>
          <?php endforeach;
        else:
        // Show default row with dashes when no history exists
        ?>
            <tr>
              <td>--</td>
              <td>--</td>
              <td>0</td>
              <td>0</td>
              <td>--</td>
              <td>0</td>
              <td>--</td>
            </tr>
        <?php endif; ?>
        <!-- fill blank rows to emulate the template if desired -->
        <?php for ($i = 0; $i < max(0, 20 - count($history_rows)); $i++): ?>
          <tr>
            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>

</body>
</html>

<?php
$conn->close();
?>