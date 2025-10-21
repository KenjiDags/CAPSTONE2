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

// Fetch issued ICS items directly from ics_items (no JOIN) to avoid collation issues
$item_query = "
    SELECT 
        stock_number,
        description,
        unit,
        quantity,
        unit_cost,
        total_cost,
        estimated_useful_life,
        serial_number
    FROM ics_items
    WHERE ics_id = $ics_id AND quantity > 0
    ORDER BY stock_number
";
$item_result = $conn->query($item_query);

// Calculate total amount
$total_amount = 0;
if ($item_result && $item_result->num_rows > 0) {
    $items = [];
    // Prepare semi lookup for unit amount
    $sumSemiStmt = $conn->prepare("SELECT amount FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
    while ($row = $item_result->fetch_assoc()) {
        $items[] = $row;
        $semi_amount = null;
        if ($sumSemiStmt) {
            $sn = $row['stock_number'];
            $sumSemiStmt->bind_param("s", $sn);
            if ($sumSemiStmt->execute()) {
                $semiRes = $sumSemiStmt->get_result();
                if ($semiRes && $semiRes->num_rows > 0) {
                    $semi_amount = (float)($semiRes->fetch_assoc()['amount']);
                }
            }
        }
        $qtyVal = (float)$row['quantity'];
        $unitCostVal = ($semi_amount !== null) ? $semi_amount : (float)$row['unit_cost'];
        $total_amount += ($semi_amount !== null) ? ($semi_amount * $qtyVal) : (float)$row['total_cost'];
    }
    if ($sumSemiStmt) { $sumSemiStmt->close(); }
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
            @page { size: A4 portrait; margin: 12mm; }
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .print-container { page-break-inside: avoid; }
        }
        
        /* General styles */
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.2;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 20px;
        }
        
        .print-container {
            width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 8px 6px 12px 6px;
            border: 2px solid #000;
        }

        /* Make the on-screen view a bit narrower, but keep print full-width */
        @media screen {
            .print-container { max-width: 720px; }
        }
        
        .header-title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 8px;
            border: none;
            padding: 0;
            background: transparent;
            letter-spacing: 0.5px;
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
            margin-bottom: 8px;
            border: 2px solid #000;
        }
        
        .items-table th,
        .items-table td {
            padding: 4px 3px;
            text-align: center;
            font-size: 10px;
            vertical-align: middle;
        }
        /* Header keeps full grid lines */
        .items-table thead th {
            border: 1px solid #000;
        }
        /* Body rows show only vertical lines (no horizontal row lines) */
        .items-table tbody td {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-top: 0;
            border-bottom: 0;
            padding-top: 10px;
            padding-bottom: 10px; /* make rows taller so columns look longer */
        }
        
        .items-table th {
            font-weight: bold;
        }
        
        .items-table .description {
            text-align: left;
            font-size: 10px;
        }
        
        .signatures {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .signatures td {
            border: 2px solid #000;
            padding: 10px;
            font-size: 10px;
            height: 100px;
            vertical-align: top;
            width: 50%;
        }
        
        .signatures .signature-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .signatures .signature-label {
            font-size: 7px;
            color: #666;
        }
        /* Prefilled signature lines */
        .signature-line {
            border-bottom: 1px solid #000;
            width: 90%;
            height: 18px;
            margin: 0 auto 4px auto;
            text-align: center;
            line-height: 18px;
        }
        .signature-line.position {
            margin-top: 6px;
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
    <a href="edit_ics.php?ics_id=<?php echo $ics_id; ?>" class="back-button">✏️ Edit ICS</a>
        <hr style="margin: 20px 0;">
    </div>

    <div class="print-container">
        <div style="text-align:right; font-style:italic; font-size:11px;">Annex A.3</div>
        <div class="header-title">INVENTORY CUSTODIAN SLIP</div>

        <table class="info-section" style="border:0;">
            <tr>
                <td style="border:0; padding:2px 0;">
                    <strong>Entity Name:</strong>
                    <span style="display:inline-block; min-width:260px; border-bottom:1px solid #000; padding:0 4px;">
                        <?php echo htmlspecialchars($ics['entity_name']); ?>
                    </span>
                </td>
                <td style="border:0; padding:2px 0; text-align:right;">&nbsp;</td>
            </tr>
            <tr>
                <td style="border:0; padding:2px 0;">
                    <strong>Fund Cluster :</strong>
                    <span style="display:inline-block; min-width:260px; border-bottom:1px solid #000; padding:0 4px;">
                        <?php echo htmlspecialchars($ics['fund_cluster']); ?>
                    </span>
                </td>
                <td style="border:0; padding:2px 0; text-align:right;">
                    <strong>ICS No :</strong>
                    <span style="display:inline-block; min-width:160px; border-bottom:1px solid #000; padding:0 4px;">
                        <?php echo htmlspecialchars($ics['ics_no']); ?>
                    </span>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 7%">Quantity</th>
                    <th rowspan="2" style="width: 7%">Unit</th>
                    <th colspan="2" style="width: 24%">Amount</th>
                    <th rowspan="2" style="width: 38%">Description</th>
                    <th rowspan="2" style="width: 12%">Item No.</th>
                    <th rowspan="2" style="width: 12%">Estimated Useful Life</th>
                </tr>
                <tr>
                    <th style="width: 12%">Unit Cost</th>
                    <th style="width: 12%">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Add issued items to the table
                $row_count = 0;
                if ($item_result && $item_result->num_rows > 0) {
                    // Prepare a statement to fetch unit amount from semi_expendable_property by property no
                    $semiStmt = $conn->prepare("SELECT amount FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
                    while ($item = $item_result->fetch_assoc()) {
                        // Lookup unit cost from semi table; fall back to recorded ICS values if not found
                        $semi_amount = null;
                        if ($semiStmt) {
                            $sn = $item['stock_number'];
                            $semiStmt->bind_param("s", $sn);
                            if ($semiStmt->execute()) {
                                $semiRes = $semiStmt->get_result();
                                if ($semiRes && $semiRes->num_rows > 0) {
                                    $semi_amount = (float)($semiRes->fetch_assoc()['amount']);
                                }
                            }
                        }
                        $qtyVal = (float)$item['quantity'];
                        $unitCostVal = ($semi_amount !== null) ? $semi_amount : (float)$item['unit_cost'];
                        $totalCostVal = ($semi_amount !== null) ? ($semi_amount * $qtyVal) : (float)$item['total_cost'];
                        echo '<tr>';
                        // Quantity: show as whole number if integer
                        $qtyDisplay = (fmod($qtyVal, 1.0) == 0.0) ? number_format($qtyVal, 0) : number_format($qtyVal, 2);
                        echo '<td>' . $qtyDisplay . '</td>';
                        $unit = isset($item['unit']) && $item['unit'] !== '' ? $item['unit'] : '-';
                        echo '<td>' . htmlspecialchars($unit) . '</td>';
                        echo '<td>' . number_format($unitCostVal, 2) . '</td>';
                        echo '<td>' . number_format($totalCostVal, 2) . '</td>';
                        echo '<td class="description">' . htmlspecialchars($item['description']) . '</td>';
                        echo '<td>' . htmlspecialchars($item['stock_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($item['estimated_useful_life']) . '</td>';
                        echo '</tr>';
                        $row_count++;
                    }
                    if ($semiStmt) { $semiStmt->close(); }
                }

                // Add empty rows to fill the table (minimum 14 rows total for visual match)
                for ($i = $row_count; $i < 14; $i++) {
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
                ?>
            </tbody>
        </table>

        <table class="signatures">
            <tr>
                <td>
                    <div class="signature-title">Received from:</div>
                    <div class="signature-line">
                        <span><?php echo htmlspecialchars($ics['received_from'] ?? ''); ?></span>
                    </div>
                    <div class="signature-label" style="text-align:center;">Signature Over Printed Name</div>
                    <div class="signature-line position">
                        <span><?php echo htmlspecialchars($ics['received_from_position'] ?? ''); ?></span>
                    </div>
                    <div class="signature-label" style="text-align:center;">Position/Office</div>
                    <div class="signature-line" style="width: 60%;">
                        <span><?php echo (isset($ics['date_issued']) && $ics['date_issued']) ? htmlspecialchars(date('M d, Y', strtotime($ics['date_issued']))) : ''; ?></span>
                    </div>
                    <div class="signature-label" style="text-align:center;">Date</div>
                </td>
                <td>
                    <div class="signature-title">Received by:</div>
                    <div class="signature-line">
                        <span><?php echo htmlspecialchars($ics['received_by'] ?? ''); ?></span>
                    </div>
                    <div class="signature-label" style="text-align:center;">Signature Over Printed Name</div>
                    <div class="signature-line position">
                        <span><?php echo htmlspecialchars($ics['received_by_position'] ?? ''); ?></span>
                    </div>
                    <div class="signature-label" style="text-align:center;">Position/Office</div>
                    <div class="signature-line" style="width: 60%;">
                        <span><?php echo (isset($ics['date_issued']) && $ics['date_issued']) ? htmlspecialchars(date('M d, Y', strtotime($ics['date_issued']))) : ''; ?></span>
                    </div>
                    <div class="signature-label" style="text-align:center;">Date</div>
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