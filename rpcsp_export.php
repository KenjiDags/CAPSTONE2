<?php
require 'auth.php';
require 'config.php';

// Load semi-expendable items
$items = [];
$sql = "SELECT category, item_description, semi_expendable_property_no, unit, amount, quantity_balance, remarks FROM semi_expendable_property ORDER BY item_description";
$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) { $items[] = $r; }
}

$report_date = htmlspecialchars($_GET['report_date'] ?? date('Y-m-d'));
$fund_cluster = htmlspecialchars($_GET['fund_cluster'] ?? '');
$accountable_officer = htmlspecialchars($_GET['accountable_officer'] ?? '');
$official_designation = htmlspecialchars($_GET['official_designation'] ?? '');
$entity_name = htmlspecialchars($_GET['entity_name'] ?? '');
$assumption_date = htmlspecialchars($_GET['assumption_date'] ?? '');
$signature_name_1 = htmlspecialchars($_GET['signature_name_1'] ?? '');
$signature_name_2 = htmlspecialchars($_GET['signature_name_2'] ?? '');
$signature_name_3 = htmlspecialchars($_GET['signature_name_3'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RPCSP - Export</title>
  <style>
    @media print {
      .no-print {
        display: none !important;
      }
      body {
        margin: 0;
        padding: 10px;
      }
      .container {
        border: none !important;
      }
    }

    body {
      font-family: Arial, sans-serif;
      font-size: 11px;
      line-height: 1.2;
      margin: 0;
      padding: 20px;
      background: #fff;
      color: #000;
    }

    .container {
      max-width: 1000px;
      margin: 0 auto;
      border: 1px solid #000;
      padding: 16px;
      box-sizing: border-box;
      position: relative;
    }

    .appendix {
      position: absolute;
      top: 10px;
      right: 15px;
      font-style: italic;
      font-size: 12px;
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
      font-size: 12px;
    }

    .header {
      text-align: center;
      margin-bottom: 15px;
      margin-top: 15px;
    }

    .title {
      font-weight: bold;
      font-size: 16px;
      margin: 0;
    }

    .subtitle {
      font-style: italic;
      margin: 4px 0 12px;
      font-size: 12px;
    }

    .field-row {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 8px;
      font-size: 12px;
    }

    .field-input {
      display: inline-block;
      border-bottom: 1px solid #000;
      min-width: 120px;
      padding: 0 4px;
      font-size: 11px;
    }

    .field-input.long {
      min-width: 200px;
    }

    .field-input.medium {
      min-width: 120px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 9px;
    }

    th,
    td {
      border: 1px solid #000;
      padding: 4px 6px;
      vertical-align: top;
      text-align: center;
    }

    th {
      background: #f5f5f5;
      font-weight: bold;
    }

    .text-left {
      text-align: left;
    }

    .main tbody tr.signature-row td {
      border-top: 1px solid #000;
      vertical-align: top;
      height: 100px;
      font-size: 10px;
      text-align: left;
      padding: 8px;
    }

    .signature-title {
      font-weight: bold;
      margin-bottom: 4px;
      text-align: left;
    }

    .signature-line {
      border-bottom: 1px solid #000;
      margin-bottom: 6px;
      text-align: center;
      margin-top: 10px;
      min-height: 16px;
    }

    .signature-label {
      font-size: 10px;
    }

    .btn {
      display: inline-block;
      padding: 8px 16px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      text-decoration: none;
    }

    .btn-print {
      background: #007cba;
      color: #fff;
    }

    .btn-back {
      background: #6c757d;
      color: #fff;
    }
  </style>
</head>
<body>
  <div class="no-print">
    <button class="btn btn-print" onclick="window.print()">🖨️ Print/Save as PDF</button>
    <a href="rpcsp.php" class="btn btn-back" style="margin-left:8px;">← Back to Form</a>
    <hr style="margin: 20px 0;">
  </div>

  <div class="container">
    <div class="appendix">Appendix A.8</div>

    <div class="agency-header">
      <img src="images/TESDA-Logo-export.png" alt="TESDA Logo">
      <div class="agency-text">
        <div>Republic of the Philippines</div>
        <div><strong>TECHNICAL EDUCATION &amp; SKILLS DEVELOPMENT AUTHORITY</strong></div>
        <div>Cordillera Administrative Region</div>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <td colspan="11" style="font-size: 10px; border-bottom: none;">
            <div class="header">
              <h1 class="title">REPORT ON THE PHYSICAL COUNT OF SEMI-EXPENDABLE PROPERTY</h1>
              <p class="subtitle">(Semi-Expendable Property)</p>
            </div>
            <span style="font-size:11px;">As at</span>
            <span class="field-input"><?= $report_date ?></span>
          </td>
        </tr>

        <tr>
          <td colspan="11" style="border-top: none; border-bottom: none;">&nbsp;</td>
        </tr>

        <tr>
          <td colspan="11" style="text-align: left; font-size: 12px; border-top: none; border-bottom: none;">
            <span>Fund Cluster:</span>
            <span class="field-input long"><?= $fund_cluster ?></span>
          </td>
        </tr>

        <tr>
          <td colspan="11" style=" text-align:left; font-size: 12px; border-top: none; border-bottom: none;">
            For which <span class="field-input medium"><?= $accountable_officer ?></span>,
            <span class="field-input medium"><?= $official_designation ?></span>,
            <span class="field-input medium"><?= $entity_name ?></span> is accountable, having assumed such accountability on
            <span class="field-input medium"><?= $assumption_date ?></span>.
          </td>
        </tr>

        <tr>
          <th rowspan="2" style="width:8%;">Article</th>
          <th rowspan="2" style="width:12%;">Item</th>
          <th rowspan="2" style="width:14%;">Description</th>
          <th rowspan="2" style="width:10%;">Property Number</th>
          <th rowspan="2" style="width:8%;">Unit of Measure</th>
          <th rowspan="2" style="width:8%;">Unit Value</th>
          <th rowspan="2" style="width:8%;">Balance Per Card<br>(Quantity)</th>
          <th rowspan="2" style="width:8%;">On Hand Per Count<br>(Quantity)</th>
          <th colspan="2" style="width:12%;">Shortage/Overage</th>
          <th rowspan="2" style="width:14%;">Remarks</th>
        </tr>
        <tr>
          <th style="width:6%;">Quantity</th>
          <th style="width:6%;">Value</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $row_count = 0;
        foreach ($items as $row) {
            echo '<tr>';
            echo '<td>Semi-Expendable</td>';
            echo '<td class="text-left">' . htmlspecialchars($row['category'] ?? '') . '</td>';
            echo '<td class="text-left">' . htmlspecialchars($row['item_description'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['semi_expendable_property_no'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['unit'] ?? '') . '</td>';
            echo '<td>' . (isset($row['amount']) ? htmlspecialchars(number_format((float)$row['amount'], 2)) : '') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['quantity_balance'] ?? '')) . '</td>';
            echo '<td>&nbsp;</td>';
            echo '<td>&nbsp;</td>';
            echo '<td>&nbsp;</td>';
            echo '<td>' . htmlspecialchars($row['remarks'] ?? '') . '</td>';
            echo '</tr>';
            $row_count++;
        }
        for ($i = $row_count; $i < 15; $i++) {
            echo '<tr>';
            for ($j = 0; $j < 11; $j++) echo '<td>&nbsp;</td>';
            echo '</tr>';
        }
        ?>

        <tr class="signature-row">
          <td colspan="3" style="padding-bottom: 20px;">
            <div class="signature-title">Certified Correct by:</div>
            <div class="signature-line"><?= $signature_name_1 ?: '&nbsp;' ?></div>
            <div class="signature-label">Signature over Printed Name of Inventory Committee Chair and Members</div>
          </td>
          <td colspan="4" style="padding-bottom: 20px;">
            <div class="signature-title">Approved by:</div>
            <div class="signature-line"><?= $signature_name_2 ?: '&nbsp;' ?></div>
            <div class="signature-label">Signature over Printed Name of Head of Agency/Entity or Authorized Representative</div>
          </td>
          <td colspan="4" style="padding-bottom: 20px;">
            <div class="signature-title">Verified by:</div>
            <div class="signature-line"><?= $signature_name_3 ?: '&nbsp;' ?></div>
            <div class="signature-label">Signature over Printed Name of COA Representative</div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</body>
</html>
