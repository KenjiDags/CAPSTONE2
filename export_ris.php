<?php
require 'config.php';
require 'auth.php';

if (!isset($_GET['ris_id'])) {
    die("❌ Error: RIS ID not specified in the URL.");
}

$ris_id = (int)$_GET['ris_id'];

// Fetch RIS header
$ris_result = $conn->query("SELECT * FROM ris WHERE ris_id = $ris_id");
if (!$ris_result || $ris_result->num_rows === 0) {
    die("❌ No RIS record found for RIS ID: $ris_id");
}
$ris = $ris_result->fetch_assoc();

// Fetch items that were actually issued (only those with issued_quantity > 0)
$item_query = "
    SELECT 
        i.stock_number,
        i.item_name,
        i.description,
        i.unit,
        ri.issued_quantity,
        ri.stock_available,
        ri.remarks
    FROM items i
    INNER JOIN ris_items ri ON i.stock_number = ri.stock_number 
    WHERE ri.ris_id = $ris_id AND ri.issued_quantity > 0
    ORDER BY i.stock_number
";
$item_result = $conn->query($item_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RIS No. <?php echo htmlspecialchars($ris['ris_no']); ?> - Export</title>
    <style>
        /* Print-specific styles */
        @media print {
            body { margin: 0; padding: 10px; }
            .no-print { display: none !important; }
            .print-container { page-break-inside: avoid; }
            .appendix-label {
                position: absolute;
                top: 15px;
                right: 15px;
                font-size: 11px;
                font-style: italic;
                font-weight: normal;
                z-index: 1000;
            }
        }
        
        /* General styles */
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.2;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 20px;
            position: relative;
        }
        
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            position: relative;
        }
        
        .appendix-label {
            position: absolute;
            top: 8px;
            right: 12px;
            font-size: 11px;
            font-style: italic;
            font-weight: normal;
            color: #333;
            z-index: 100;
        }
        
        .header-title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 15px;
            margin-top: 25px;
            padding: 8px;
            background: #f9f9f9;
            position: relative;
        }
        
        .info-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        
        .items-table .label {
            font-weight: bold;
            width: 15%;
            text-align: left;
        }
        
        .items-table .value {
            width: 35%;
            text-decoration: underline;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 3px;
            text-align: center;
            font-size: 8px;
            vertical-align: middle;
        }
        
        .items-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .items-table .description {
            text-align: left;
            font-size: 7px;
        }
        
        .items-table .remarks {
            text-align: left;
            font-size: 7px;
        }
        
        
        .print-instructions {
            background: #fffacd;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .print-button {
            background: #007cba;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 10px;
        }
        
        .print-button:hover {
            background: #005a87;
        }
        
        .back-button {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        
        .back-button:hover {
            background: #545b62;
        }
        
        .checkbox {
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div class="print-instructions">
            <h3>📄 Export Instructions</h3>
            <p><strong>To save as PDF:</strong></p>
            <ol>
                <li>Click the "Print/Save as PDF" button below</li>
                <li>In the print dialog, select "Save as PDF" or "Microsoft Print to PDF"</li>
                <li>Choose your destination and click "Save"</li>
            </ol>
            <p><strong>For best results:</strong> Use Chrome or Edge browser for optimal PDF formatting.</p>
        </div>
        
        <button class="print-button" onclick="window.print()">🖨️ Print/Save as PDF</button>
        <button class="back-button" onclick="history.back()">← Back</button>
        <hr style="margin: 20px 0;">
    </div>
    
    <div class="print-container">
        <div class="appendix-label">Appendix 63</div>
        
        <div class="header-title">REQUISITION AND ISSUE SLIP</div>

        <table class="items-table">
            <thead>

                <tr>
                    <td colspan="7" class="label" style="border: none !important;">Entity Name: <span class="value"><?php echo htmlspecialchars($ris['entity_name']); ?></span></td>
                    <td colspan="2" class="label" style="border: none !important;">Fund Cluster: <span class="value"><?php echo htmlspecialchars($ris['fund_cluster']); ?></span></td>
                </tr>
                <tr>
                    <td colspan="5" class="label">Division: <span class="value"><?php echo htmlspecialchars($ris['division']); ?></span></td>
                    <td colspan="5" class="label">Responsibility Center Code: <span class="value"><?php echo htmlspecialchars($ris['responsibility_center_code']); ?></span></td>
                </tr>
                <tr>
                    <td colspan="5" class="label">Office: <span class="value"><?php echo htmlspecialchars($ris['office']); ?></span></td>
                    <td colspan="5" class="label">RIS No: <span class="value"><?php echo htmlspecialchars($ris['ris_no']); ?></span></td>
                </tr>

                <tr>
                    <th rowspan="2" style="width: 10%;">Stock No.</th>
                    <th rowspan="2" style="width: 20%;">Description</th>
                    <th rowspan="2" style="width: 8%;">Unit</th>
                    <th colspan="2" style="width: 17%;">Requisition</th>
                    <th colspan="2" style="width: 10%;">Stock Available?</th>
                    <th colspan="2" style="width: 20%;">Issue</th>
                </tr>
                <tr>
                    <th style="width: 8%;">Quantity</th>
                    <th style="width: 9%;">Remarks</th>
                    <th style="width: 5%;">Yes</th>
                    <th style="width: 5%;">No</th>
                    <th style="width: 10%;">Quantity</th>
                    <th style="width: 10%;">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Add issued items to the table
                $row_count = 0;
                if ($item_result && $item_result->num_rows > 0) {
                    while ($item = $item_result->fetch_assoc()) {
                        $stock_available_yes = ($item['stock_available'] == 'Yes') ? '<span class="checkbox">✓</span>' : '';
                        $stock_available_no = ($item['stock_available'] == 'No') ? '<span class="checkbox">✓</span>' : '';
                        
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($item['stock_number']) . '</td>';
                        echo '<td class="description">' . htmlspecialchars($item['item_name']) . ',' . htmlspecialchars($item['description']) . '</td>';
                        echo '<td>' . htmlspecialchars($item['unit']) . '</td>';
                        echo '<td>' . htmlspecialchars($item['issued_quantity']) . '</td>';
                        echo '<td class="remarks">' . htmlspecialchars($item['remarks']) . '</td>';
                        echo '<td>' . $stock_available_yes . '</td>';
                        echo '<td>' . $stock_available_no . '</td>';
                        echo '<td>' . htmlspecialchars($item['issued_quantity']) . '</td>';
                        echo '<td class="remarks">' . htmlspecialchars($item['remarks']) . '</td>';
                        echo '</tr>';
                        $row_count++;
                    }
                }
                
                // Add empty rows to fill the table (minimum 12 rows total)
                for ($i = $row_count; $i < 12; $i++) {
                    echo '<tr>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '</tr>';
                }
                ?>

                <tr class="purpose-section">
                    <td rowspan="3"
                        style="text-align: right; vertical-align: top; border-right: none; font-weight: bold;">
                        Purpose:
                    </td>
                    <td colspan="8" style="border-left: none; text-align: left; ">
                        <?php 
                            echo !empty($ris['purpose']) 
                                ? nl2br(htmlspecialchars($ris['purpose'])) 
                                : '&nbsp;'; 
                        ?>
                    </td>
                </tr>

                <tr>
                    <td colspan="8" style="border: none !important; border-right: 1px solid black !important;">&nbsp;</td>
                </tr>

                <tr>
                    <td colspan="8" style="border-left: none;">&nbsp;</td>
                </tr>

            <tr>
                <td>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                        <tr><td style="border: none; padding: 2px 0;">Signature:</td></tr>
                        <tr><td style="border: none; padding: 2px 0;">Printed Name:</td></tr>
                        <tr><td style="border: none; padding: 2px 0;">Designation:</td></tr>
                        <tr><td style="border: none; padding: 2px 0;">Date:</td></tr>
                    </table>
                </td>
                <td colspan="2">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                        <tr><td style="border: none; padding: 2px 0; font-weight: bold;">Requested by:</td></tr>
                        <tr><td style="border-left: none; border-right: none; border-top: none; padding: 10px 0 2px 0;"><?php echo htmlspecialchars($ris['requested_by']); ?></td></tr>
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                    </table>
                </td>
                <td colspan="3">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                        <tr><td style="border: none; padding: 2px 0; font-weight: bold;">Approved by:</td></tr>
                        <tr><td style="border-left: none; border-right: none; border-top: none; padding: 10px 0 2px 0;"><?php echo htmlspecialchars($ris['approved_by']); ?></td></tr>
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                    </table>
                </td>
                <td colspan="2">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                        <tr><td style="border: none; padding: 2px 0; font-weight: bold;">Issued by:</td></tr>
                        <tr><td style="border-left: none; border-right: none; border-top: none; padding: 10px 0 2px 0;"><?php echo htmlspecialchars($ris['issued_by']); ?></td></tr>
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                    </table>
                </td>
                <td colspan="2">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                        <tr><td style="border: none; padding: 2px 0; font-weight: bold;">Received by:</td></tr>
                        <tr><td style="border-left: none; border-right: none; border-top: none; padding: 10px 0 2px 0;"><?php echo htmlspecialchars($ris['received_by']); ?></td></tr>
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                        <tr><td style="border: none; padding: 2px 0;">&nbsp;</td></tr>
                    </table>
                </td>
            </tr>

            </tbody>
        </table>


    </div>

</body>
</html>

<?php
$conn->close();
?>