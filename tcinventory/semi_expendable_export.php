<?php
require 'config.php';

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
      margin: 0;
      font-family: "Times New Roman", serif;
      font-size: 11px;
      color: #000;
      background: #fff;
    }
    @media print {
      body {
        font-size: 12pt;
      }
      .property-card-table th,
      .property-card-table td {
        height: 32px;
      }
    }
    .card-wrapper {
      max-width: 1200px;
      margin: 0 auto;
      border: 2px solid #222;
      padding: 24px 24px 24px 24px;
      position: relative;
      background: #fff;
      box-sizing: border-box;
    }
    .meta-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 6px;
      font-size: 11px;
    }
    .meta-label {
      font-weight: bold;
      white-space: nowrap;
      flex: 0 0 auto;
      font-size: 13px;
      text-align: left;
      min-width: 120px;
      display: inline-block;
    }
    .field-line {
      flex: 1 1 180px;
      border-bottom: 1px solid #000;
      min-height: 16px;
      line-height: 16px;
      padding: 0 4px;
      box-sizing: border-box;
      display: inline-block;
      vertical-align: bottom;
      font-size: 13px;
      font-family: "Times New Roman", serif;
      font-weight: normal;
      text-align: left;
    }
      min-width: 120px;
      display: inline-block;
    }
    .field-line {
      flex: 1 1 180px;
      border-bottom: 1px solid #000;
      min-height: 16px;
      line-height: 16px;
      padding: 0 4px;
      box-sizing: border-box;
      display: inline-block;
      vertical-align: bottom;
      font-size: 11px;
      font-family: "Times New Roman", serif;
      font-weight: normal;
      text-align: left;
    }
    .field-line.empty:after {
      content: "\00a0";
    }
    .property-card-title {
      text-align: center;
      font-weight: bold;
      font-size: 20px;
      margin: 8px 0 18px 0;
      letter-spacing: 1px;
      border-bottom: 2px solid #222;
      padding-bottom: 8px;
    }
    .property-card-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      margin-top: 0;
      table-layout: fixed;
    }
    .property-card-table th {
      border: 1px solid #222;
      padding: 6px 4px;
      text-align: center;
      vertical-align: middle;
      font-weight: bold;
      background: #fff;
      font-size: 13px;
      font-family: "Times New Roman", serif;
    }
    .property-card-table td {
      border: 1px solid #222;
      padding: 6px 4px;
      text-align: center;
      vertical-align: middle;
      font-size: 13px;
      font-family: "Times New Roman", serif;
      min-height: 24px;
    }
    .annex {
      position: absolute;
      right: 0;
      bottom: 0;
      font-size: 12pt;
      font-style: italic;
      margin: 8px 16px;
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
    <a class="back-link" href="view_semi_expendable.php?id=<?php echo $id; ?>">← Back to Property No. <?php echo htmlspecialchars($item['semi_expendable_property_no']); ?></a>
    <hr style="margin:14px 0;">
  </div>

  <div class="card-wrapper">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
      <span style="font-weight:bold;font-size:20px;margin-top:8px;text-align:center;flex:1;">SEMI-EXPENDABLE PROPERTY CARD</span>
      <span style="font-style:italic;font-size:16px;margin-top:8px;">Annex A.1</span>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-top:18px;">
      <tr>
        <td style="font-weight:bold;font-size:16px;text-align:left;width:50%;padding-bottom:8px;">Entity Name : <span style="border-bottom:2px solid #222;padding:0 60px 0 0;">TESDA</span></td>
        <td style="font-weight:bold;font-size:16px;text-align:right;width:50%;padding-bottom:8px;">Fund Cluster: <span style="border-bottom:2px solid #222;padding:0 60px 0 0;"><?php echo htmlspecialchars($item['fund_cluster']); ?></span></td>
      </tr>
      <tr>
        <td style="font-weight:bold;font-size:16px;text-align:left;padding-bottom:8px;" colspan="2">Semi-expendable Property : <span style="border-bottom:2px solid #222;padding:0 700px 0 0;"></span></td>
        <td style="font-weight:bold;font-size:16px;text-align:right;">Semi-expendable Property Number: <span style="border-bottom:2px solid #222;padding:0 200px 0 0;"><?php echo htmlspecialchars($item['semi_expendable_property_no']); ?></span></td>
      </tr>
      <tr>
        <td style="font-weight:bold;font-size:16px;text-align:left;padding-bottom:8px;" colspan="3">Description : <span style="border-bottom:2px solid #222;padding:0 900px 0 0;"><?php echo htmlspecialchars($item['item_description']); ?></span></td>
      </tr>
    </table>
    <table class="property-card-table" style="margin-top:0;">
      <thead>
        <tr>
          <th rowspan="2" style="width:6%;font-size:13px;">Date</th>
          <th rowspan="2" style="width:8%;font-size:13px;">Reference</th>
          <th colspan="3" style="font-size:13px;">Receipt</th>
          <th colspan="2" style="font-size:13px;">Receipt</th>
          <th colspan="3" style="font-size:13px;">Issue/Transfer/ Disposal</th>
          <th colspan="1" style="font-size:13px;">Balance</th>
          <th rowspan="2" style="width:8%;font-size:13px;">Amount</th>
          <th rowspan="2" style="width:10%;font-size:13px;">Remarks</th>
        </tr>
        <tr>
          <th style="width:5%;font-size:13px;">Qty.</th>
          <th style="width:7%;font-size:13px;">Unit Cost</th>
          <th style="width:7%;font-size:13px;">Total Cost</th>
          <th style="width:7%;font-size:13px;">Qty.</th>
          <th style="width:7%;font-size:13px;">Item No.</th>
          <th style="width:7%;font-size:13px;">Qty.</th>
          <th style="width:7%;font-size:13px;">Office/Officer</th>
          <th style="width:7%;font-size:13px;">Qty.</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?php echo htmlspecialchars($item['date']); ?></td>
          <td><?php echo htmlspecialchars($item['ics_rrsp_no']); ?></td>
          <td><?php echo htmlspecialchars($item['quantity_issued']); ?></td>
          <td><?php echo number_format($item['amount_total'] / max($item['quantity_issued'],1), 2); ?></td>
          <td><?php echo number_format($item['amount_total'], 2); ?></td>
          <td><?php echo htmlspecialchars($item['quantity_issued']); ?></td>
          <td><?php echo htmlspecialchars($item['semi_expendable_property_no']); ?></td>
          <td><?php echo ($item['quantity_disposed'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($item['office_officer_reissued'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($item['quantity_balance'] ?? ''); ?></td>
          <td><?php echo number_format($item['amount_total'] ?? 0, 2); ?></td>
          <td><?php echo htmlspecialchars($item['remarks']); ?></td>
        </tr>
        <?php for ($i = 0; $i < 15; $i++): ?>
        <tr>
          <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
<?php $conn->close(); ?>
