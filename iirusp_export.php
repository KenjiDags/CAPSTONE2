<?php
require 'auth.php';
require 'config.php';

$entity_name = htmlspecialchars($_GET['entity_name'] ?? '');
$fund_cluster = htmlspecialchars($_GET['fund_cluster'] ?? '');
$accountable_name = htmlspecialchars($_GET['accountable_name'] ?? '');
$report_date = htmlspecialchars($_GET['report_date'] ?? date('Y-m-d'));
$iirusp_no = htmlspecialchars($_GET['iirusp_no'] ?? '');

$items = [];
$sql = "SELECT date, item_description, semi_expendable_property_no, category, quantity_balance, amount_total, remarks FROM semi_expendable_property ORDER BY item_description LIMIT 18";
$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) { $items[] = $r; }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>IIRUSP Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #000;
            background: #f5f5f5;
            padding: 20px;
        }
        .page {
            width: 11in;
            height: 8.5in;
            margin: 0 auto 20px;
            padding: 0.5in;
            background: white;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 8px;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
        }
        .header h2 {
            font-size: 13px;
            font-weight: bold;
            margin: 2px 0;
        }
        .header p {
            font-size: 11px;
            margin: 2px 0;
        }
        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 8px;
            font-size: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        .info-label {
            font-weight: bold;
            width: 120px;
        }
        .info-value {
            flex: 1;
            border-bottom: 1px solid #000;
            padding: 0 4px;
        }
        .table-wrapper {
            margin: 8px 0;
            border: 2px solid #000;
            overflow-x: auto;
            max-height: 280px;
            overflow-y: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        .header-group {
            text-align: center;
            font-weight: bold;
            background: #fff;
            border: 1px solid #000;
        }
        th {
            border: 1px solid #000;
            padding: 3px 2px;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
            background: #fff;
            word-wrap: break-word;
        }
        td {
            border: 1px solid #000;
            padding: 2px;
            text-align: left;
            height: 16px;
        }
        td.center {
            text-align: center;
        }
        td.right {
            text-align: right;
        }
        .disposal-checkbox {
            text-align: center;
        }
        .disposal-checkbox input {
            width: 12px;
            height: 12px;
        }
        .certification-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-top: 6px;
            font-size: 9px;
        }
        .cert-box {
            border: 1px solid #000;
            padding: 6px;
            min-height: 85px;
            display: flex;
            flex-direction: column;
        }
        .cert-title {
            font-weight: bold;
            font-size: 8px;
            margin-bottom: 6px;
            line-height: 1.2;
            flex: 1;
        }
        .sig-line {
            border-bottom: 1px solid #000;
            height: 20px;
            margin-bottom: 2px;
        }
        .sig-label {
            font-size: 8px;
            text-align: center;
            font-style: italic;
            line-height: 1.1;
        }
        .button-section {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        button {
            padding: 8px 16px;
            margin: 0 4px;
            font-size: 12px;
            cursor: pointer;
            border: none;
            border-radius: 3px;
        }
        .btn-print {
            background: #007bff;
            color: white;
        }
        .btn-print:hover {
            background: #0056b3;
        }
        .btn-back {
            background: #6c757d;
            color: white;
        }
        .btn-back:hover {
            background: #545b62;
        }
        @media print {
            body { background: white; padding: 0; }
            .page { margin: 0; box-shadow: none; width: 100%; height: 100%; }
            .button-section { display: none; }
        }
    </style>
</head>
<body>

<div class="page">
    <!-- HEADER -->
    <div class="header">
        <h2>INVENTORY AND INSPECTION REPORT OF UNSERVICEABLE</h2>
        <h2>SEMI-EXPENDABLE PROPERTY</h2>
        <p>As at ___________________________________</p>
    </div>

    <!-- INFO SECTION -->
    <div class="info-section">
        <div>
            <div class="info-row">
                <span class="info-label">Entity Name:</span>
                <span class="info-value"><?= $entity_name ?></span>
            </div>
            <div class="info-row" style="margin-top: 2px;">
                <span class="info-label" style="font-size: 9px; font-style: italic;">(Name of Accountable Officer)</span>
            </div>
            <div class="info-row">
                <span style="font-size: 9px; font-style: italic;">Designation</span>
                <span class="info-value"></span>
                <span style="font-size: 9px; font-style: italic; margin-left: 10px;">Station</span>
                <span class="info-value"></span>
            </div>
        </div>
        <div>
            <div class="info-row">
                <span class="info-label">Fund Cluster:</span>
                <span class="info-value"><?= $fund_cluster ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span class="info-value"><?= $report_date ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">IIRUSP No.:</span>
                <span class="info-value"><?= $iirusp_no ?></span>
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th colspan="10" class="header-group">INVENTORY</th>
                    <th colspan="8" class="header-group">INSPECTION and DISPOSAL</th>
                    <th colspan="2" class="header-group" style="width: 4%;">RECORD<br>OF<br>SALES</th>
                </tr>
                <tr>
                    <!-- INVENTORY HEADERS -->
                    <th style="width: 2%;">#</th>
                    <th style="width: 7%;">Date<br>Acquired</th>
                    <th style="width: 13%;">Particulars/<br>Articles</th>
                    <th style="width: 11%;">Semi-expendable<br>Property No.</th>
                    <th style="width: 3%;">Qty</th>
                    <th style="width: 6%;">Unit<br>Cost</th>
                    <th style="width: 6%;">Total<br>Cost</th>
                    <th style="width: 7%;">Accumulated<br>Impairment<br>Losses</th>
                    <th style="width: 7%;">Carrying<br>Amount</th>
                    <th style="width: 7%;">Remarks</th>
                    <!-- DISPOSAL HEADERS -->
                    <th style="width: 3%;">Sale</th>
                    <th style="width: 4%;">Transfer</th>
                    <th style="width: 5%;">Destruction</th>
                    <th style="width: 9%;">Others<br>(Specify)</th>
                    <th style="width: 3%;">Total</th>
                    <th style="width: 6%;">Appraised<br>Value</th>
                    <th style="width: 4%;">OR No.</th>
                    <th style="width: 4%;">Assessor</th>
                    <th style="width: 2%;"></th>
                    <th style="width: 2%;"></th>
                </tr>
                <tr style="background: #f0f0f0;">
                    <th>(1)</th>
                    <th>(2)</th>
                    <th>(3)</th>
                    <th>(4)</th>
                    <th>(5)</th>
                    <th>(6)</th>
                    <th>(7)</th>
                    <th>(8)</th>
                    <th>(9)</th>
                    <th>(10)</th>
                    <th>(11)</th>
                    <th>(12)</th>
                    <th>(13)</th>
                    <th>(14)</th>
                    <th>(15)</th>
                    <th>(16)</th>
                    <th>(17)</th>
                    <th>(18)</th>
                    <th>(19)</th>
                    <th>(20)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $item_no = 0;
                foreach ($items as $row):
                    $item_no++;
                    $date = $row['date'] ?? '';
                    $desc = $row['item_description'] ?? '';
                    $prop = $row['semi_expendable_property_no'] ?? '';
                    $qty = $row['quantity_balance'] ?? 0;
                    $unit_cost = isset($row['amount_total']) ? number_format((float)$row['amount_total'],2) : '';
                    $total = isset($row['amount_total']) ? number_format((float)$row['amount_total'] * (float)$qty,2) : '';
                ?>
                <tr>
                    <td class="center"><?= $item_no ?></td>
                    <td><?= $date ?></td>
                    <td><?= htmlspecialchars($desc) ?></td>
                    <td><?= htmlspecialchars($prop) ?></td>
                    <td class="center"><?= $qty ?></td>
                    <td class="right"><?= $unit_cost ?></td>
                    <td class="right"><?= $total ?></td>
                    <td></td>
                    <td></td>
                    <td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
                    <!-- DISPOSAL CELLS -->
                    <td class="disposal-checkbox"><input type="checkbox"></td>
                    <td class="disposal-checkbox"><input type="checkbox"></td>
                    <td class="disposal-checkbox"><input type="checkbox"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endforeach;

                // Fill remaining rows (up to 18)
                for ($i = $item_no; $i < 18; $i++):
                ?>
                <tr>
                    <td class="center"><?= $i + 1 ?></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="center"></td>
                    <td class="right"></td>
                    <td class="right"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- DISPOSAL CELLS -->
                    <td class="disposal-checkbox"><input type="checkbox"></td>
                    <td class="disposal-checkbox"><input type="checkbox"></td>
                    <td class="disposal-checkbox"><input type="checkbox"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- CERTIFICATION SECTION -->
    <div class="certification-section">
        <div class="cert-box">
            <div class="cert-title">I HEREBY request inspection and disposition, pursuant to Section 79 of P.D. No. 1445, of the property enumerated above.</div>
            <div>
                <div class="sig-line"></div>
                <div class="sig-label">Requested by:<br>(Signature over Printed Name of<br>Accountable Officer)</div>
                <div class="sig-line" style="margin-top: 4px; height: 16px;"></div>
                <div class="sig-label">(Designation of Accountable Officer)</div>
            </div>
        </div>

        <div class="cert-box">
            <div class="cert-title">I CERTIFY that I have inspected each and every article enumerated in this report, and that the disposition made thereof was, in my judgment, the best for the public interest.</div>
            <div>
                <div class="sig-line"></div>
                <div class="sig-label">Approved by:<br>(Signature over Printed Name of<br>Inspection Officer)</div>
            </div>
        </div>

        <div class="cert-box">
            <div class="cert-title">I CERTIFY that I have witnessed the disposition of the articles enumerated on this report this _____ day of __________.</div>
            <div>
                <div class="sig-line"></div>
                <div class="sig-label">(Signature over Printed Name<br>of Witness)</div>
            </div>
        </div>
    </div>
</div>

<div class="button-section">
    <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <button class="btn-back"><a href="iirusp.php">⬅️ Back</a></button>
</div>
</body>
</html>
