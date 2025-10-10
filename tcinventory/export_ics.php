<?php
require 'config.php';

if (!isset($_GET['ics_id'])) {
    header("Location: ics.php");
    exit();
}

$ics_id = (int)$_GET['ics_id'];
$format = isset($_GET['format']) ? $_GET['format'] : 'view';

// Fetch ICS header
$ics_result = $conn->query("SELECT * FROM ics WHERE ics_id = $ics_id");
if (!$ics_result || $ics_result->num_rows === 0) {
    header("Location: ics.php");
    exit();
}
$ics = $ics_result->fetch_assoc();

// Fetch items that were actually issued
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
}

// Export to Excel
if ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="ICS_' . str_replace('/', '_', $ics['ics_no']) . '_' . date('Y-m-d') . '.xls"');
    
    echo '<html>';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><th colspan="7" style="text-align: center; font-size: 16px; font-weight: bold; padding: 10px;">INVENTORY CUSTODIAN SLIP (ICS)</th></tr>';
    echo '<tr><td colspan="7">&nbsp;</td></tr>';
    
    // ICS Information
    echo '<tr><td><strong>Entity Name:</strong></td><td colspan="2">' . htmlspecialchars($ics['entity_name']) . '</td><td><strong>ICS No.:</strong></td><td colspan="2">' . htmlspecialchars($ics['ics_no']) . '</td><td></td></tr>';
    echo '<tr><td><strong>Fund Cluster:</strong></td><td colspan="2">' . htmlspecialchars($ics['fund_cluster']) . '</td><td><strong>Date Issued:</strong></td><td colspan="2">' . date('F d, Y', strtotime($ics['date_issued'])) . '</td><td></td></tr>';
    echo '<tr><td colspan="7">&nbsp;</td></tr>';
    
    // Items Header
    echo '<tr>';
    echo '<th rowspan="2" style="text-align: center; vertical-align: middle;">Quantity</th>';
    echo '<th rowspan="2" style="text-align: center; vertical-align: middle;">Unit</th>';
    echo '<th colspan="2" style="text-align: center;">Amount</th>';
    echo '<th rowspan="2" style="text-align: center; vertical-align: middle;">Description</th>';
    echo '<th rowspan="2" style="text-align: center; vertical-align: middle;">Inventory Item No.</th>';
    echo '<th rowspan="2" style="text-align: center; vertical-align: middle;">Estimated Useful Life</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<th style="text-align: center;">Unit Cost</th>';
    echo '<th style="text-align: center;">Total Cost</th>';
    echo '</tr>';
    
    // Items Data
    if (!empty($items)) {
        foreach ($items as $item) {
            echo '<tr>';
            echo '<td style="text-align: center;">' . number_format($item['quantity'], 2) . '</td>';
            echo '<td>' . htmlspecialchars($item['unit']) . '</td>';
            echo '<td style="text-align: right;">₱' . number_format($item['unit_cost'], 2) . '</td>';
            echo '<td style="text-align: right;">₱' . number_format($item['total_cost'], 2) . '</td>';
            echo '<td>' . htmlspecialchars($item['item_name']) . ', ' . htmlspecialchars($item['description']);
            if (!empty($item['serial_number'])) {
                echo ' (S/N: ' . htmlspecialchars($item['serial_number']) . ')';
            }
            echo '</td>';
            echo '<td>' . htmlspecialchars($item['stock_number']) . '</td>';
            echo '<td>' . htmlspecialchars($item['estimated_useful_life']) . '</td>';
            echo '</tr>';
        }
    }
    
    // Total Row
    echo '<tr>';
    echo '<td colspan="3" style="text-align: center; font-weight: bold;">TOTAL</td>';
    echo '<td style="text-align: right; font-weight: bold;">₱' . number_format($total_amount, 2) . '</td>';
    echo '<td colspan="3"></td>';
    echo '</tr>';
    
    echo '<tr><td colspan="7">&nbsp;</td></tr>';
    
    // Signature Section
    echo '<tr>';
    echo '<td colspan="3" style="text-align: center; font-weight: bold;">Received from:</td>';
    echo '<td>&nbsp;</td>';
    echo '<td colspan="3" style="text-align: center; font-weight: bold;">Received by:</td>';
    echo '</tr>';
    echo '<tr><td colspan="3" style="text-align: center; height: 50px; vertical-align: bottom; border-bottom: 1px solid black;">&nbsp;</td><td>&nbsp;</td><td colspan="3" style="text-align: center; height: 50px; vertical-align: bottom; border-bottom: 1px solid black;">&nbsp;</td></tr>';
    echo '<tr><td colspan="3" style="text-align: center;">Signature Over Printed Name</td><td>&nbsp;</td><td colspan="3" style="text-align: center;">Signature Over Printed Name</td></tr>';
    echo '<tr><td colspan="3" style="text-align: center; font-weight: bold;">' . htmlspecialchars($ics['received_from']) . '</td><td>&nbsp;</td><td colspan="3" style="text-align: center; font-weight: bold;">' . htmlspecialchars($ics['received_by']) . '</td></tr>';
    echo '<tr><td colspan="3" style="text-align: center;">' . htmlspecialchars($ics['received_from_position']) . '</td><td>&nbsp;</td><td colspan="3" style="text-align: center;">' . htmlspecialchars($ics['received_by_position']) . '</td></tr>';
    echo '<tr><td colspan="3" style="text-align: center;">Position/Office</td><td>&nbsp;</td><td colspan="3" style="text-align: center;">Position/Office</td></tr>';
    
    echo '</table>';
    echo '</body></html>';
    exit();
}

// Default: Redirect to view page for PDF generation and printing
header("Location: view_ics.php?ics_id=" . $ics_id);
exit();
?>