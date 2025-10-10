<?php
require 'config.php';

// Fetch inventory items from database
$inventory_items = [];
$sql = "SELECT item_name, description, stock_number, unit, unit_cost FROM items ORDER BY item_name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inventory_items[] = $row;
    }
} else {
    error_log("Database error: " . $conn->error);
}

$report_date = htmlspecialchars($_GET['report_date'] ?? date('Y-m-d'));
$fund_cluster = htmlspecialchars($_GET['fund_cluster'] ?? '');
$accountable_officer = htmlspecialchars($_GET['accountable_officer'] ?? '');
$official_designation = htmlspecialchars($_GET['official_designation'] ?? '');
$entity_name = htmlspecialchars($_GET['entity_name'] ?? '');
$assumption_date = htmlspecialchars($_GET['assumption_date'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RPCI Report - Export</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Print-specific */
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 10px; }
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
            font-weight: normal;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .title {
            font-weight: bold;
            font-size: 16px;
            margin: 0;
            text-decoration: underline;
        }

        .subtitle {
            font-style: italic;
            margin: 4px 0 12px;
            font-size: 12px;
        }

        .form-fields {
            margin-bottom: 15px;
        }

        .field-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 12px;
        }

        .field-row label {
            margin-right: 8px;
        }

        .field-input {
            border-bottom: 1px solid #000;
            min-width: 120px;
            padding: 0 4px;
            margin: 0 8px;
        }

        .field-input.long {
            min-width: 200px;
        }

        .field-input.short {
            min-width: 80px;
        }

        .accountability-text {
            font-size: 12px;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .table-wrapper {
            overflow: visible;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        th, td {
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

        .small {
            font-size: 9px;
        }

        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }

        .signatures td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: top;
            height: 100px;
            font-size: 10px;
        }

        .sig-title {
            font-weight: bold;
            margin-bottom: 4px;
        }

        .sig-name {
            border-bottom: 1px solid #000;
            min-height: 20px;
            margin-bottom: 6px;
        }

        .instructions {
            background: #fffacd;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 16px;
            border-radius: 4px;
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
        <div class="instructions">
            <h3>📄 Export Instructions</h3>
            <p><strong>To save as PDF:</strong></p>
            <ol>
                <li>Click the "Print/Save as PDF" button below.</li>
                <li>In the print dialog, choose "Save as PDF" or equivalent.</li>
                <li>Save to your desired location.</li>
            </ol>
            <p><strong>Best viewed in:</strong> Chrome or Edge for consistent PDF output.</p>
        </div>
        <button class="btn btn-print" onclick="window.print()">🖨️ Print/Save as PDF</button>
        <a href="rpci.php" class="btn btn-back" style="margin-left:8px;">← Back to Form</a>
        <hr style="margin: 20px 0;">
    </div>

    <div class="container">
        <div class="appendix">Appendix 66</div>
        
        <div class="header">
            <h1 class="title">REPORT ON THE PHYSICAL COUNT OF INVENTORIES</h1>
            <p class="subtitle">(Type of Inventory Items)</p>
        </div>

        <div class="form-fields">
            <div class="field-row">
                <span>As at</span>
                <span class="field-input"><?= $report_date ?></span>
            </div>
            
            <div class="field-row" style="margin-top: 15px;">
                <span>Fund Cluster:</span>
                <span class="field-input long"><?= $fund_cluster ?></span>
            </div>
            
            <div class="accountability-text" style="margin-top: 15px;">
                For which <span style="text-decoration: underline;"><?= $accountable_officer ?></span>, 
                <em></em> <span style="text-decoration: underline;"><?= $official_designation ?></span>, 
                <span style="text-decoration: underline;"><?= $entity_name ?></span> is accountable, having assumed such accountability on 
                <span style="text-decoration: underline;"><?= $assumption_date ?></span>.
            </div>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="width:8%;">Article</th>
                        <th rowspan="2" style="width:12%;">Item</th>
                        <th rowspan="2" style="width:18%;">Description</th>
                        <th rowspan="2" style="width:10%;">Stock Number</th>
                        <th rowspan="2" style="width:8%;">Unit of Measure</th>
                        <th rowspan="2" style="width:8%;">Unit Value</th>
                        <th rowspan="2" style="width:8%;">Balance Per Card<br>(Quantity)</th>
                        <th rowspan="2" style="width:8%;">On Hand Per Count<br>(Quantity)</th>
                        <th colspan="2" style="width:12%;">Shortage/Overage</th>
                        <th rowspan="2" style="width:8%;">Remarks</th>
                    </tr>
                    <tr>
                        <th style="width:6%;">Quantity</th>
                        <th style="width:6%;">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $row_count = 0;
                    if (!empty($inventory_items)) {
                        foreach ($inventory_items as $item) {
                            echo '<tr>';
                            echo '<td>Office Supplies</td>';
                            echo '<td class="text-left">' . htmlspecialchars($item['item_name'] ?? '') . '</td>';
                            echo '<td class="text-left">' . htmlspecialchars($item['description'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($item['stock_number'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($item['unit'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($item['unit_cost'] ?? '') . '</td>';
                            echo '<td>&nbsp;</td>'; // balance per card
                            echo '<td>&nbsp;</td>'; // on hand
                            echo '<td>&nbsp;</td>'; // shortage qty
                            echo '<td>&nbsp;</td>'; // shortage value
                            echo '<td>&nbsp;</td>'; // remarks
                            echo '</tr>';
                            $row_count++;
                        }
                    }
                    
                    // Add empty rows to reach minimum of 15 rows
                    for ($i = $row_count; $i < 15; $i++) {
                        echo '<tr>';
                        for ($j = 0; $j < 11; $j++) {
                            echo '<td>&nbsp;</td>';
                        }
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <table class="signatures">
            <tr>
                <td style="width: 33.33%;">
                    <div class="sig-title">Certified Correct by:</div>
                    <div class="sig-name">&nbsp;</div>
                    <div class="small">Signature over Printed Name of Inventory Committee Chair and Members</div>
                </td>
                <td style="width: 33.33%;">
                    <div class="sig-title">Approved by:</div>
                    <div class="sig-name">&nbsp;</div>
                    <div class="small">Signature over Printed Name of Head of Agency/Entity or Authorized Representative</div>
                </td>
                <td style="width: 33.34%;">
                    <div class="sig-title">Verified by:</div>
                    <div class="sig-name">&nbsp;</div>
                    <div class="small">Signature over Printed Name of COA Representative</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>