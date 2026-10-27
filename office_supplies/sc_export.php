<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';

if (!isset($_GET['item_id'])) {
    die("❌ Error: item not found.");
}

$item_id = (int)$_GET['item_id'];
if ($item_id <= 0) {
    die("❌ Invalid item ID.");
}

// Fetch item
$item_stmt = $conn->prepare("SELECT items.*,
    COALESCE((SELECT MIN(changed_at) FROM item_history h WHERE h.item_id = items.item_id AND h.quantity_change > 0),
             (SELECT MIN(created_at) FROM inventory_entries e WHERE e.item_id = items.item_id AND e.quantity > 0)) AS first_receipt
    FROM items WHERE item_id = ?");
$item_stmt->bind_param("i", $item_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();
if (!$item_result || $item_result->num_rows === 0) {
    die("❌ No record found for Item ID $item_id.");
}
$items = $item_result->fetch_assoc();
$item_stmt->close();

// Older installations can export before the new RIS form has added its column.
$daysColumn = $conn->query("SHOW COLUMNS FROM ris_items LIKE 'days_to_consume'");
$savedDaysSelect = $daysColumn->num_rows > 0
    ? "(SELECT MAX(ri.days_to_consume) FROM ris_items ri WHERE ri.ris_id = ih.ris_id AND ri.stock_number = ih.stock_number)"
    : "NULL";

function stockCardRemainingDays($initial, $firstReceipt, $movementDate, $savedDays = null) {
    if ($savedDays !== null) return max(0, (int)$savedDays) . ' days';
    if (!preg_match('/^\s*(\d+)\s*(?:days?)?\s*$/i', (string)$initial, $match)) return '';
    $days = min(2147483647, (int)$match[1]);
    if ($firstReceipt) {
        $start = new DateTimeImmutable(substr($firstReceipt, 0, 10));
        $end = new DateTimeImmutable(substr($movementDate, 0, 10));
        $days = max(0, $days - max(0, (int)$start->diff($end)->format('%r%a')));
    }
    return $days . ' days';
}

$history_stmt = $conn->prepare("
  SELECT ih.*, r.ris_no AS ris_no, r.date_requested, r.office AS ris_office, r.fund_cluster AS movement_fund, $savedDaysSelect AS saved_days_to_consume
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

// Use the fund appearing in the most recorded movements.
// Rows are newest first, so ties favor the most recently used fund.
$fundCounts = [];
foreach ($history_rows as $movement) {
    $fund = trim((string)($movement['movement_fund'] ?? ''));
    if ($fund !== '') $fundCounts[$fund] = ($fundCounts[$fund] ?? 0) + 1;
}
$stock_card_fund = '';
$mostFundMovements = 0;
foreach ($fundCounts as $fund => $count) {
    if ($count > $mostFundMovements) {
        $stock_card_fund = (string)$fund;
        $mostFundMovements = $count;
    }
}

// Entity (LGU)
$ris = ['entity_name' => 'TESDA'];
?>

<!DOCTYPE html>
<html data-export-styles lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Item ID: <?php echo htmlspecialchars($items['item_id']); ?> - Export</title>
  <style>
    @media print {
      .no-print { display:none !important; }
      .card-wrapper {
        border: none !important;
        padding-top: 20px !important;
      }
    }
    body {
      margin: 20px;
      font-family: 'Century Gothic', Century Gothic, sans-serif;
      font-size: 12px;
      color: #000;
    }
    .card-wrapper {
      max-width: 800px;
      margin: 0 auto;
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
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
<script src="js/export-preview.js?v=<?= time() ?>" defer data-export-preview data-preview-title="Stock Card Preview" data-preview-code="SC" data-preview-back="office_supplies/SC.php" data-preview-width="850" data-preview-reference="<?= htmlspecialchars((string)($items['stock_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></script>
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
    <a class="back-link" href="#" onclick="window.history.back(); return false;">← Back</a>
    <hr style="margin:14px 0;">
  </div>

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
          <th colspan="5" style="border: none; border-left: 1px solid #000;">LGU: <span class="underline" style="min-width: 200px;"><?php echo htmlspecialchars($ris['entity_name']); ?></span></th>
          <th colspan="2" style="border: none; border-right: 1px solid #000;">Fund: <span class="underline" style="min-width: 100px;"><?= htmlspecialchars($stock_card_fund, ENT_QUOTES, 'UTF-8') ?></span></th>
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
            $office = htmlspecialchars((string)($h['ris_office'] ?? ''), ENT_QUOTES, 'UTF-8');
            $balance = htmlspecialchars($h['quantity_on_hand']);
            $days = htmlspecialchars(stockCardRemainingDays(
                $items['estimated_life'] ?? '',
                $items['first_receipt'],
                $h['date_requested'] ?? $h['changed_at'],
                $h['saved_days_to_consume']
            ), ENT_QUOTES, 'UTF-8');
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