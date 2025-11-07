<?php
require 'config.php';

if (!isset($_GET['ics_id'])) {
    die("❌ Error: ICS ID not specified in the URL.");
}

$ics_id = (int)$_GET['ics_id'];

// Fetch ICS header
$ics_result = $conn->query("SELECT * FROM ics WHERE ics_id = $ics_id");
if (!$ics_result) {
    die("❌ Database error: " . $conn->error);
}
if ($ics_result->num_rows === 0) {
    die("❌ No ICS record found for ICS ID: $ics_id");
}
$ics = $ics_result->fetch_assoc();

// Fetch items issued on this ICS
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

// Calculate total amount with semi amount fallback
$total_amount = 0.0;
if ($item_result && $item_result->num_rows > 0) {
    $sumSemiStmt = $conn->prepare("SELECT amount FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
    while ($r = $item_result->fetch_assoc()) {
        $semi_amount = null;
        if ($sumSemiStmt) {
            $sn = $r['stock_number'];
            $sumSemiStmt->bind_param("s", $sn);
            if ($sumSemiStmt->execute()) {
                $semiRes = $sumSemiStmt->get_result();
                if ($semiRes && $semiRes->num_rows > 0) {
                    $semi_amount = (float)($semiRes->fetch_assoc()['amount']);
                }
            }
        }
        $qtyVal = (float)$r['quantity'];
        $total_amount += ($semi_amount !== null) ? ($semi_amount * $qtyVal) : (float)$r['total_cost'];
    }
    if ($sumSemiStmt) { $sumSemiStmt->close(); }
    // Reset pointer for rendering
    $item_result->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View ICS - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
</head>
<body class="view-ris-page">
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <h2>📋 Viewing ICS No. <?php echo htmlspecialchars($ics['ics_no']); ?></h2>

        <!-- Action Buttons -->
        <div class="ris-actions">
            <a href="ics.php" class="btn btn-secondary">← Back to ICS List</a>
            <a href="edit_ics.php?ics_id=<?php echo $ics_id; ?>" class="btn btn-primary">✏️ Edit ICS</a>
            <a href="export_ics.php?ics_id=<?php echo $ics_id; ?>" class="btn btn-primary">📄 Export PDF</a>
        </div>

        <!-- ICS Details -->
        <div class="ris-details">
            <p><strong>Entity Name:</strong> <?php echo htmlspecialchars($ics['entity_name']); ?></p>
            <p><strong>Fund Cluster:</strong> <?php echo htmlspecialchars($ics['fund_cluster']); ?></p>
            <p><strong>ICS No.:</strong> <?php echo htmlspecialchars($ics['ics_no']); ?></p>
            <p><strong>Date Issued:</strong> <?php echo htmlspecialchars($ics['date_issued']); ?></p>
            <p><strong>Received By:</strong> <?php echo htmlspecialchars($ics['received_by']); ?></p>
            <p><strong>Received By Position:</strong> <?php echo htmlspecialchars($ics['received_by_position']); ?></p>
            <p><strong>Received From:</strong> <?php echo htmlspecialchars($ics['received_from']); ?></p>
            <p><strong>Received From Position:</strong> <?php echo htmlspecialchars($ics['received_from_position']); ?></p>
        </div>

        <h3>📦 Items</h3>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                        <th>Description</th>
                        <th>Item No.</th>
                        <th>Estimated Useful Life</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($item_result && $item_result->num_rows > 0) {
                    $semiStmt = $conn->prepare("SELECT amount FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
                    while ($item = $item_result->fetch_assoc()) {
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
                        $qtyDisplay = (fmod($qtyVal, 1.0) == 0.0) ? number_format($qtyVal, 0) : number_format($qtyVal, 2);
                        echo '<tr>';
                        echo '<td>' . $qtyDisplay . '</td>';
                        $unit = isset($item['unit']) && $item['unit'] !== '' ? $item['unit'] : '-';
                        echo '<td>' . htmlspecialchars($unit) . '</td>';
                        echo '<td>₱' . number_format($unitCostVal, 2) . '</td>';
                        echo '<td>₱' . number_format($totalCostVal, 2) . '</td>';
                        echo '<td>' . htmlspecialchars($item['description']) . '</td>';
                        echo '<td>' . htmlspecialchars($item['stock_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($item['estimated_useful_life']) . '</td>';
                        echo '</tr>';
                    }
                    if ($semiStmt) { $semiStmt->close(); }
                } else {
                    echo '<tr><td colspan="7">No items found for this ICS.</td></tr>';
                }
                ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align:right;"><strong>Grand Total:</strong></td>
                        <td colspan="4"><strong>₱<?php echo number_format($total_amount, 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        
    </div>

    <script src="js/view_ris_script.js?v=<?= time() ?>"></script>
</body>
</html>

<?php $conn->close(); ?>
