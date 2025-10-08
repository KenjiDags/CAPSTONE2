<?php
require 'config.php';

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
            border: 2px solid #000;
            padding: 8px;
            background: #f9f9f9;
            position: relative;
        }
        
        .info-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .info-section td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 9px;
        }
        
        .info-section .label {
            font-weight: bold;
            width: 15%;
        }
        
        .info-section .value {
            width: 35%;
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
        
        .purpose-section {
            width: 97.8%;
            border: 1px solid #000;
            padding: 8px;
            margin-bottom: 15px;
            font-size: 9px;
            min-height: 40px;
        }
        
        .purpose-section .label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .signatures td {
            border: 1px solid #000;
            padding: 8px;
            font-size: 8px;
            height: 80px;
            vertical-align: top;
            width: 25%;
        }
        
        .signatures .signature-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .signatures .signature-name {
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            padding-bottom: 2px;
            min-height: 20px;
        }
        
        .signatures .signature-label {
            font-size: 7px;
            color: #666;
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
        <a href="view_ris.php?ris_id=<?php echo $ris_id; ?>" class="back-button">← Back to RIS</a>
        <hr style="margin: 20px 0;">
    </div>
    
    <div class="print-container">
        <div class="appendix-label">Appendix 63</div>
        
        <div class="header-title">REQUISITION AND ISSUE SLIP</div>

        <table class="info-section">
            <tr>
                <td class="label">Entity Name:</td>
                <td class="value"><?php echo htmlspecialchars($ris['entity_name']); ?></td>
                <td class="label">Fund Cluster:</td>
                <td class="value"><?php echo htmlspecialchars($ris['fund_cluster']); ?></td>
            </tr>
            <tr>
                <td class="label">Division:</td>
                <td class="value"><?php echo htmlspecialchars($ris['division']); ?></td>
                <td class="label">Responsibility Center Code:</td>
                <td class="value"><?php echo htmlspecialchars($ris['responsibility_center_code']); ?></td>
            </tr>
            <tr>
                <td class="label">Office:</td>
                <td class="value"><?php echo htmlspecialchars($ris['office']); ?></td>
                <td class="label">RIS No:</td>
                <td class="value"><?php echo htmlspecialchars($ris['ris_no']); ?></td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 10%;">Stock No.</th>
                    <th rowspan="2" style="width: 25%;">Description</th>
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
            </tbody>
        </table>

        <div class="purpose-section">
            <div class="label">Purpose:</div>
            <div><?php echo nl2br(htmlspecialchars($ris['purpose'])); ?></div>
        </div>

        <table class="signatures">
            <tr>
                <td>
                    <div class="signature-title">Requested by:</div>
                    <div class="signature-name"><?php echo htmlspecialchars($ris['requested_by']); ?></div>
                    <div class="signature-label">Printed Name</div>
                    <br>
                    <div class="signature-label">Designation: _______________</div>
                    <br>
                    <div class="signature-label">Date: _______________</div>
                </td>
                <td>
                    <div class="signature-title">Approved by:</div>
                    <div class="signature-name"><?php echo htmlspecialchars($ris['approved_by']); ?></div>
                    <div class="signature-label">Printed Name</div>
                    <br>
                    <div class="signature-label">Designation: _______________</div>
                    <br>
                    <div class="signature-label">Date: _______________</div>
                </td>
                <td>
                    <div class="signature-title">Issued by:</div>
                    <div class="signature-name"><?php echo htmlspecialchars($ris['issued_by']); ?></div>
                    <div class="signature-label">Printed Name</div>
                    <br>
                    <div class="signature-label">Designation: _______________</div>
                    <br>
                    <div class="signature-label">Date: _______________</div>
                </td>
                <td>
                    <div class="signature-title">Received by:</div>
                    <div class="signature-name"><?php echo htmlspecialchars($ris['received_by']); ?></div>
                    <div class="signature-label">Printed Name</div>
                    <br>
                    <div class="signature-label">Designation: _______________</div>
                    <br>
                    <div class="signature-label">Date: _______________</div>
                </td>
            </tr>
        </table>
    </div>

    <script>
        // Auto-focus on print when page loads (optional)
        // window.addEventListener('load', function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 500);
        // });
    </script>
</body>
</html>

<?php
$conn->close();
?>