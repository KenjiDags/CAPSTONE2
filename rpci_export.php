<?php
require 'auth.php';
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
$signature_name_1 = htmlspecialchars($_GET['signature_name_1'] ?? '');
$signature_name_2 = htmlspecialchars($_GET['signature_name_2'] ?? '');
$signature_name_3 = htmlspecialchars($_GET['signature_name_3'] ?? '');

$rows_per_page = 17;
$item_pages = array_chunk($inventory_items, $rows_per_page);
if (empty($item_pages)) {
    $item_pages = [[]];
}
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
            @page {
                size: auto;
                margin: 0.2in;
            }

            .no-print { display: none !important; }
            body { margin: 0; padding: 0; }
            .container {border: none !important; padding: 10px 8px 8px !important; width: 100%; max-width: none;}
            .screen-only { display: none !important; }
            .print-only { display: block !important; }
            thead { display: table-header-group; }
            .appendix { display: none !important; }
            .agency-header { display: none !important; }
            .appendix-repeat-row { display: table-row !important; }
            .agency-header-row { display: table-row !important; }
            .appendix-repeat-cell,
            .agency-header-row th,
            .agency-header-cell {
                border: none !important;
                background: transparent !important;
            }

            .appendix-repeat-cell {
                text-align: right !important;
                font-style: italic;
                font-weight: normal;
                font-size: 11px;
                padding: 0 0 2px 0 !important;
            }
            .table-page-break { page-break-after: always; break-after: page; }

            .print-only table {
                width: 100%;
                table-layout: fixed;
            }

            .print-only th,
            .print-only td {
                padding: 2px 3px;
                font-size: 8px;
                word-break: break-word;
                overflow-wrap: anywhere;
            }

            .print-only .title {
                font-size: 14px;
            }

            .print-only .table-inline-input {
                min-width: 120px;
            }

            .print-only .agency-header-inline {
                gap: 8px;
            }

            .print-only .agency-header-logo {
                width: 40px;
                height: 40px;
            }
        }

        @media print and (orientation: portrait) {
            .print-only table {
                font-size: 8.5px;
            }

            .print-only th,
            .print-only td {
                padding: 2.5px 3px;
                font-size: 8.5px;
            }

            .print-only .title {
                font-size: 15px;
            }

            .print-only .table-inline-input {
                min-width: 130px;
            }

            .print-only .agency-header-logo {
                width: 42px;
                height: 42px;
            }
        }

        @media print and (orientation: landscape) {
            .print-only th,
            .print-only td {
                padding: 2px 3px;
                font-size: 8px;
            }

            .print-only .title {
                font-size: 14px;
            }

            .print-only .table-inline-input {
                min-width: 120px;
            }

            .print-only .agency-header-logo {
                width: 40px;
                height: 40px;
            }
        }

        @media screen {
            .screen-only { display: block; }
            .print-only { display: none; }
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
            padding: 28px 16px 16px;
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

        .agency-header-row {
            display: none;
        }

        .appendix-repeat-row {
            display: none;
        }

        .agency-header-cell {
            border-bottom: none !important;
            padding: 8px 6px !important;
        }

        .agency-header-inline {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
        }

        .agency-header-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            flex: 0 0 auto;
        }

        .agency-header-inline .agency-text {
            display: inline-block;
            text-align: center;
            line-height: 1.2;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            margin-top: 40px;
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

        .form-fields {
            margin-bottom: 8px;
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

        .field-input.long {
            min-width: 200px;
        }

        .field-input.short {
            min-width: 80px;
        }

        .accountability-text {
            font-size: 12px;
            margin-bottom: 15px;
        }

        td.accountability-text {
            text-align: left !important;
            padding: 4px 6px !important;
            line-height: 1.3;
            margin-bottom: 0;
        }

        td.fund-cluster-row {
            text-align: left !important;
            padding: 4px 6px !important;
            font-size: 12px;
        }

        .table-inline-input {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 150px;
            padding: 0 4px;
            margin-left: 8px;
            text-align: center;
        }

        .table-wrapper {
            overflow: visible;
        }

        .table-page-break {
            margin-bottom: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        th, td {
            border-right: 2px solid #000;
            border-left: 2px solid #000;
            padding: 4px 6px;
            vertical-align: top;
            text-align: center;
        }

        th {
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
            margin-bottom: 6px;
            margin-top: 20px; ;
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

        th,td .full-border{
            border: 2px solid #000 !important;
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

        <div class="agency-header">
            <img src="images/TESDA-Logo-export.png" alt="TESDA Logo">
            <div class="agency-text">
                <div>Republic of the Philippines</div>
                <div><strong>TECHNICAL EDUCATION &amp; SKILLS DEVELOPMENT AUTHORITY</strong></div>
                <div>Cordillera Administrative Region</div>
            </div>
        </div>

        <div class="table-wrapper">
            <div class="screen-only">
            <table>
                <thead>
                    <tr>
                        <th colspan="11" class="title" style="border-bottom: none !important;">REPORT ON THE PHYSICAL COUNT OF INVENTORIES</th>
                    </tr>

                    <tr>
                        <td colspan="11" class="subtitle" style="border-top: none !important; border-bottom: none;">(Type of Inventory Items)</td>
                    </tr>

                    <tr>
                        <td colspan="11" style="text-align: center !important; border-top: none !important; border-bottom: none;" class="fund-cluster-row">As at:
                            <span class="table-inline-input"><?= $report_date ?></span>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="11" style="border-top: none; border-bottom:none;">&nbsp;</td>
                    </tr>

                    <tr>
                        <td colspan="11" class="fund-cluster-row" style="border-top: none; border-bottom: none;">Fund Cluster:
                            <span class="table-inline-input"><?= $fund_cluster ?></span>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="11" class="accountability-text" style="border-top: none;">For which
                            <span style="display: inline-block; border-bottom: 1px solid #000; min-width: 140px; text-align: center;"><?= $accountable_officer ?></span>,
                            <span style="display: inline-block; border-bottom: 1px solid #000; min-width: 140px; text-align: center;"><?= $official_designation ?></span>,
                            <span style="display: inline-block; border-bottom: 1px solid #000; min-width: 150px; text-align: center;"><?= $entity_name ?></span> is accountable, having
                            <span style="margin-top: 10px; display: inline-block;">assumed such accountability on </span>
                            <span style="display: inline-block; border-bottom: 1px solid #000; min-width: 120px; text-align: center;"><?= $assumption_date ?></span>.
                        </td>
                    </tr>

                    <tr>
                        <th rowspan="2" style="width:8%;" class="full-border">Article</th>
                        <th rowspan="2" style="width:12%;">Item</th>
                        <th rowspan="2" style="width:18%;">Description</th>
                        <th rowspan="2" style="width:10%;">Stock Number</th>
                        <th rowspan="2" style="width:8%;">Unit of Measure</th>
                        <th rowspan="2" style="width:8%;">Unit Value</th>
                        <th  style="width:8%;">Balance Per Card</th>
                        <th  style="width:8%;">On Hand Per Count</th>
                        <th colspan="2" style="width:12%;">Shortage/Overage</th>
                        <th rowspan="2" style="width:8%;">Remarks</th>
                    </tr>
                    <tr>
                        <th>Quantity</th>
                        <th>Quantity</th>
                        <th style="width:6%;">Quantity</th>
                        <th style="width:6%;">Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($inventory_items)) {
                        foreach ($inventory_items as $item) {
                            echo '<tr>';
                            echo '<td>Office Supplies</td>';
                            echo '<td class="text-left">' . htmlspecialchars($item['item_name'] ?? '') . '</td>';
                            echo '<td class="text-left">' . htmlspecialchars($item['description'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($item['stock_number'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($item['unit'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($item['unit_cost'] ?? '') . '</td>';
                            echo '<td>&nbsp;</td>';
                            echo '<td>&nbsp;</td>';
                            echo '<td>&nbsp;</td>';
                            echo '<td>&nbsp;</td>';
                            echo '<td>&nbsp;</td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                    <tr>
                        <td colspan="3" style="border: 2px solid black; border-right: none;">
                            <div class="sig-title">Certified Correct by:</div>
                            <div class="sig-name"><?php echo !empty($signature_name_1) ? htmlspecialchars($signature_name_1) : '&nbsp;'; ?></div>
                            <div class="small">Signature over Printed Name of Inventory Committee Chair and Members</div>
                        </td>
                        <td colspan="4" style="border: 2px solid black; border-left: none; border-right: none;">
                            <div class="sig-title">Approved by:</div>
                            <div class="sig-name"><?php echo !empty($signature_name_2) ? htmlspecialchars($signature_name_2) : '&nbsp;'; ?></div>
                            <div class="small">Signature over Printed Name of Head of Agency/Entity or Authorized Representative</div>
                        </td>
                        <td colspan="4" style="border: 2px solid black; border-left: none;">
                            <div class="sig-title">Verified by:</div>
                            <div class="sig-name"><?php echo !empty($signature_name_3) ? htmlspecialchars($signature_name_3) : '&nbsp;'; ?></div>
                            <div class="small">Signature over Printed Name of COA Representative</div>
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>

            <div class="print-only">
            <?php foreach ($item_pages as $page_index => $page_items): ?>
            <?php $is_last_page = ($page_index === count($item_pages) - 1); ?>
            <table class="<?php echo $is_last_page ? '' : 'table-page-break'; ?>">
                <thead>

                    <tr class="appendix-repeat-row">
                        <th colspan="11" class="appendix-repeat-cell">Appendix 66</th>
                    </tr>

                    <tr class="agency-header-row">
                        <th colspan="11" class="agency-header-cell">
                            <div class="agency-header-inline">
                                <img src="images/TESDA-Logo-export.png" alt="TESDA Logo" class="agency-header-logo">
                                <div class="agency-text">
                                    <div>Republic of the Philippines</div>
                                    <div><strong>TECHNICAL EDUCATION &amp; SKILLS DEVELOPMENT AUTHORITY</strong></div>
                                    <div>Cordillera Administrative Region</div>
                                </div>
                            </div>
                        </th>
                    </tr>

                    <tr>
                        <th colspan="11" class="title" style="border-bottom: none !important;">REPORT ON THE PHYSICAL COUNT OF INVENTORIES</th>
                    </tr>

                    <tr>
                        <td colspan="11" class="subtitle" style="border-top: none !important; border-bottom: none;">(Type of Inventory Items)</td>
                    </tr>

                    <tr>
                        <td colspan="11" style="text-align: center !important; border-top: none !important; border-bottom: none;" class="fund-cluster-row">As at:
                            <span class="table-inline-input"><?= $report_date ?></span>
                        </td>
                        
                    </tr>

                    <tr>
                        <td colspan="11" style="border-top: none; border-bottom:none;">&nbsp;</td>
                    </tr>

                    <tr>
                        <td colspan="11" class="fund-cluster-row" style="border-top: none; border-bottom: none;">Fund Cluster:
                            <span class="table-inline-input"><?= $fund_cluster ?></span>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="11" class="accountability-text" style="border-top: none;">For which
                            <span style="display: inline-block; border-bottom: 1px solid #000; min-width: 140px; text-align: center;"><?= $accountable_officer ?></span>,
                            <span style="display: inline-block; border-bottom: 1px solid #000; min-width: 140px; text-align: center;"><?= $official_designation ?></span>,
                            <span style="display: inline-block; border-bottom: 1px solid #000; min-width: 150px; text-align: center;"><?= $entity_name ?></span> is accountable, having 
                            <span style="margin-top: 10px; display: inline-block;">assumed such accountability on </span>
                            <span style="display: inline-block; border-bottom: 1px solid #000; min-width: 120px; text-align: center;"><?= $assumption_date ?></span>.
                        </td>
                    </tr>

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
                    if (!empty($page_items)) {
                        foreach ($page_items as $item) {
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
                    
                    // Add empty rows to reach minimum of 17 rows
                    for ($i = $row_count; $i < 17; $i++) {
                        echo '<tr>';
                        for ($j = 0; $j < 11; $j++) {
                            echo '<td>&nbsp;</td>';
                        }
                        echo '</tr>';
                    }
                    ?>

            <tr>
                <td colspan="3" style="border: 2px solid black !important; border-right: none !important;">
                    <div class="sig-title">Certified Correct by:</div>
                    <div class="sig-name"><?php echo !empty($signature_name_1) ? htmlspecialchars($signature_name_1) : '&nbsp;'; ?></div>
                    <div class="small">Signature over Printed Name of Inventory Committee Chair and Members</div>
                </td>
                <td colspan="4" style="border: 2px solid black !important; border-left: none !important; border-right: none !important;">
                    <div class="sig-title">Approved by:</div>
                    <div class="sig-name"><?php echo !empty($signature_name_2) ? htmlspecialchars($signature_name_2) : '&nbsp;'; ?></div>
                    <div class="small">Signature over Printed Name of Head of Agency/Entity or Authorized Representative</div>
                </td>
                <td colspan="4" style="border: 2px solid black !important; border-left: none !important;">
                    <div class="sig-title">Verified by:</div>
                    <div class="sig-name"><?php echo !empty($signature_name_3) ? htmlspecialchars($signature_name_3) : '&nbsp;'; ?></div>
                    <div class="small">Signature over Printed Name of COA Representative</div>
                </td>
            </tr>
                </tbody>
            </table>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>