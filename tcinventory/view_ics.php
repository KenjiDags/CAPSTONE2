<?php
require 'config.php';

if (!isset($_GET['ics_id'])) {
    die("❌ Error: ICS ID not specified in the URL.");
}

$ics_id = (int)$_GET['ics_id'];

// Fetch ICS header
$ics_result = $conn->query("SELECT * FROM ics WHERE ics_id = $ics_id");
if (!$ics_result || $ics_result->num_rows === 0) {
    die("❌ No ICS record found for ICS ID: $ics_id");
}
$ics = $ics_result->fetch_assoc();

// Fetch items that were actually issued (only those with quantity > 0)
$item_query = "
    SELECT 
        i.stock_number,
        i.item_name,
        i.description,
        i.unit,
        ii.quantity,
        ii.unit_cost,
        ii.total_cost,
        ii.estimated_useful_life,
        ii.serial_number
    FROM items i
    INNER JOIN ics_items ii ON i.stock_number = ii.stock_number 
    WHERE ii.ics_id = $ics_id AND ii.quantity > 0
    ORDER BY i.stock_number
";
$item_result = $conn->query($item_query);

// Calculate total amount
$total_amount = 0;
if ($item_result && $item_result->num_rows > 0) {
    $items = [];
    while ($row = $item_result->fetch_assoc()) {
        $items[] = $row;
        $total_amount += $row['total_cost'];
    }
    // Reset result pointer
    $item_result->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICS No. <?php echo htmlspecialchars($ics['ics_no']); ?> - Export</title>
    <style>
        /* Print-specific styles */
        @media print {
            body { margin: 0; padding: 10px; }
            .no-print { display: none !important; }
            .print-container { page-break-inside: avoid; }
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
        }
        
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
        }
        
        .header-title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 15px;
            border: 2px solid #000;
            padding: 8px;
            background: #f9f9f9;
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
            width: 50%;
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
        
        .total-row {
            background-color: #f0f0f0;
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
        <a href="ics.php" class="back-button">← Back to ICS List</a>
        <a href="add_ics.php?ics_id=<?php echo $ics_id; ?>" class="back-button">✏️ Edit ICS</a>
        <hr style="margin: 20px 0;">
    </div>

    <div class="print-container">
        <div class="header-title">INVENTORY CUSTODIAN SLIP (ICS)</div>

        <table class="info-section">
            <tr>
                <td class="label">Entity Name:</td>
                <td class="value"><?php echo htmlspecialchars($ics['entity_name']); ?></td>
                <td class="label">ICS No.:</td>
                <td class="value"><?php echo htmlspecialchars($ics['ics_no']); ?></td>
            </tr>
            <tr>
                <td class="label">Fund Cluster:</td>
                <td class="value"><?php echo htmlspecialchars($ics['fund_cluster']); ?></td>
                <td class="label">Date Issued:</td>
                <td class="value"><?php echo date('F d, Y', strtotime($ics['date_issued'])); ?></td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 8%;">Quantity</th>
                    <th rowspan="2" style="width: 8%;">Unit</th>
                    <th colspan="2" style="width: 18%;">Amount</th>
                    <th rowspan="2" style="width: 30%;">Description</th>
                    <th rowspan="2" style="width: 12%;">Inventory Item No.</th>
                    <th rowspan="2" style="width: 12%;">Estimated Useful Life</th>
                </tr>
                <tr>
                    <th style="width: 9%;">Unit Cost</th>
                    <th style="width: 9%;">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Add issued items to the table
                $row_count = 0;
                if ($item_result && $item_result->num_rows > 0) {
                    while ($item = $item_result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . number_format($item['quantity'], 2) . '</td>';
                        echo '<td>' . htmlspecialchars($item['unit']) . '</td>';
                        echo '<td>₱' . number_format($item['unit_cost'], 2) . '</td>';
                        echo '<td>₱' . number_format($item['total_cost'], 2) . '</td>';
                        echo '<td class="description">' . htmlspecialchars($item['item_name']) . ', ' . htmlspecialchars($item['description']);
                        if (!empty($item['serial_number'])) {
                            echo '<br><small><strong>Serial No.:</strong> ' . htmlspecialchars($item['serial_number']) . '</small>';
                        }
                        echo '</td>';
                        echo '<td>' . htmlspecialchars($item['stock_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($item['estimated_useful_life']) . '</td>';
                        echo '</tr>';
                        $row_count++;
                    }
                }
                
                // Add empty rows to fill the table (minimum 10 rows total)
                for ($i = $row_count; $i < 10; $i++) {
                    echo '<tr>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '</tr>';
                }
                
                // Add total row
                echo '<tr class="total-row">';
                echo '<td colspan="3" style="text-align: center;"><strong>TOTAL</strong></td>';
                echo '<td><strong>₱' . number_format($total_amount, 2) . '</strong></td>';
                echo '<td colspan="3">&nbsp;</td>';
                echo '</tr>';
                ?>
            </tbody>
        </table>

        <table class="signatures">
            <tr>
                <td>
                    <div class="signature-title">Received from:</div>
                    <div class="signature-name"><?php echo htmlspecialchars($ics['received_from']); ?></div>
                    <div class="signature-label">Signature Over Printed Name</div>
                    <br>
                    <div style="font-weight: bold;"><?php echo htmlspecialchars($ics['received_from_position']); ?></div>
                    <div class="signature-label">Position/Office</div>
                    <br>
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 5px auto;">&nbsp;</div>
                    <div class="signature-label">Date</div>
                </td>
                <td>
                    <div class="signature-title">Received by:</div>
                    <div class="signature-name"><?php echo htmlspecialchars($ics['received_by']); ?></div>
                    <div class="signature-label">Signature Over Printed Name</div>
                    <br>
                    <div style="font-weight: bold;"><?php echo htmlspecialchars($ics['received_by_position']); ?></div>
                    <div class="signature-label">Position/Office</div>
                    <br>
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 5px auto;">&nbsp;</div>
                    <div class="signature-label">Date</div>
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