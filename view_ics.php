<?php
require 'auth.php';
require 'config.php';

function formatItrTransferNote($transferQty, $toAccountable, $transferType, $transferOther)
{
    $qty = (int)$transferQty;
    if ($qty <= 0) { return null; }

    $toAccountable = trim((string)$toAccountable);
    $transferType = trim((string)$transferType);
    $transferOther = trim((string)$transferOther);

    $typeLabel = $transferType;
    if ($typeLabel === '' && $transferOther !== '') { $typeLabel = $transferOther; }
    if ($typeLabel !== '' && strcasecmp($typeLabel, 'Others') === 0 && $transferOther !== '') { $typeLabel = $transferOther; }

    $note = ($qty === 1) ? '1 unit transferred' : ($qty . ' units transferred');
    if ($toAccountable !== '') {
        $note .= ' to ' . $toAccountable;
    }
    if ($typeLabel !== '') {
        $note .= ' - ' . $typeLabel;
    }
    return $note;
}

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
        ics_item_id,
        stock_number,
        description,
        unit,
        quantity,
        unit_cost,
        total_cost,
        estimated_useful_life,
        serial_number
    FROM ics_items
    WHERE ics_id = $ics_id
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

// Fetch ICS history for all items in this ICS
$historyByItemId = [];
$historySql = "SELECT ics_item_id, stock_number, description, unit, quantity_before, quantity_after, quantity_change, unit_cost, total_cost_before, total_cost_after, reference_type, reference_id, reference_no, reference_details, created_at 
              FROM ics_history 
              WHERE ics_id = $ics_id 
              ORDER BY ics_item_id ASC, created_at ASC, id ASC";
$history_result = $conn->query($historySql);
if ($history_result && $history_result->num_rows > 0) {
    while ($historyRow = $history_result->fetch_assoc()) {
        $histItemId = isset($historyRow['ics_item_id']) ? (int)$historyRow['ics_item_id'] : 0;
        if ($histItemId <= 0) { continue; }
        $historyByItemId[$histItemId][] = $historyRow;
    }
    $history_result->free();
} elseif ($history_result) {
    $history_result->free();
}

// Load itr_history to enrich transfer notes if needed
$itrHistByItemId = [];
$itrHistSql = "SELECT ics_item_id, transfer_qty, to_accountable, transfer_type, transfer_other, created_at, id FROM itr_history WHERE ics_id = $ics_id ORDER BY created_at ASC, id ASC";
$itrRes = $conn->query($itrHistSql);
if ($itrRes && $itrRes->num_rows > 0) {
    while ($r = $itrRes->fetch_assoc()) {
        $iid = isset($r['ics_item_id']) ? (int)$r['ics_item_id'] : 0;
        if ($iid <= 0) { continue; }
        $itrHistByItemId[$iid][] = $r;
    }
    $itrRes->free();
} elseif ($itrRes) {
    $itrRes->free();
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
                        $icsItemId = isset($item['ics_item_id']) ? (int)$item['ics_item_id'] : 0;
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
                        $currentQtyVal = (float)$item['quantity'];
                        $unitCostVal = ($semi_amount !== null) ? $semi_amount : (float)$item['unit_cost'];
                        $currentTotalCostVal = ($semi_amount !== null) ? ($semi_amount * $currentQtyVal) : (float)$item['total_cost'];
                        $unit = isset($item['unit']) && $item['unit'] !== '' ? $item['unit'] : '-';
                        
                        // History-aware rendering: baseline + per-transfer rows
                        if ($icsItemId > 0 && isset($historyByItemId[$icsItemId]) && !empty($historyByItemId[$icsItemId])) {
                            $historyEntries = $historyByItemId[$icsItemId];

                            // Baseline (original issuance) from first entry's quantity_before
                            $first = $historyEntries[0];
                            $baseQty = isset($first['quantity_before']) ? (float)$first['quantity_before'] : $currentQtyVal;
                            $baseQtyDisplay = (fmod($baseQty, 1.0) == 0.0) ? number_format($baseQty, 0) : number_format($baseQty, 2);
                            $baseUnitCost = isset($first['unit_cost']) ? (float)$first['unit_cost'] : $unitCostVal;
                            $baseTotal = isset($first['total_cost_before']) && $first['total_cost_before'] !== null
                                ? (float)$first['total_cost_before']
                                : ($baseQty * $baseUnitCost);

                            echo '<tr style="font-weight:600; background-color:#eef6ff;">';
                            echo '<td>' . $baseQtyDisplay . '</td>';
                            echo '<td>' . htmlspecialchars($unit) . '</td>';
                            echo '<td>₱' . number_format($baseUnitCost, 2) . '</td>';
                            echo '<td>₱' . number_format($baseTotal, 2) . '</td>';
                            echo '<td>' . htmlspecialchars($item['description']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['stock_number']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['estimated_useful_life']) . '</td>';
                            echo '</tr>';

                            // Prepare itr_history pointer list for this item
                            $itrList = $itrHistByItemId[$icsItemId] ?? [];
                            $itrIdx = 0;

                            // Each transfer/history event
                            foreach ($historyEntries as $hist) {
                                $histQty = isset($hist['quantity_after']) ? (float)$hist['quantity_after'] : (isset($hist['quantity_before']) ? (float)$hist['quantity_before'] : 0);
                                $histQtyDisplay = (fmod($histQty, 1.0) == 0.0) ? number_format($histQty, 0) : number_format($histQty, 2);
                                $histUnitCost = isset($hist['unit_cost']) ? (float)$hist['unit_cost'] : $unitCostVal;
                                $histTotal = isset($hist['total_cost_after']) && $hist['total_cost_after'] !== null
                                    ? (float)$hist['total_cost_after']
                                    : ($histQty * $histUnitCost);
                                $histDescription = $hist['description'] ?? $item['description'];
                                $histUnit = $hist['unit'] ?? $unit;

                                // Build friendly note
                                $noteText = '';
                                if (!empty($hist['reference_details'])) {
                                    $details = json_decode($hist['reference_details'], true);
                                    if (is_array($details)) {
                                        $built = formatItrTransferNote(
                                            $details['transfer_qty'] ?? 0,
                                            $details['to_accountable'] ?? '',
                                            $details['transfer_type'] ?? '',
                                            $details['transfer_other'] ?? ''
                                        );
                                        if ($built) { $noteText = $built; }
                                    }
                                }
                                // Fallback: infer qty change and match itr_history
                                if ($noteText === '') {
                                    $qb = isset($hist['quantity_before']) ? (float)$hist['quantity_before'] : null;
                                    $qa = isset($hist['quantity_after']) ? (float)$hist['quantity_after'] : null;
                                    if ($qb !== null && $qa !== null && ($qa - $qb) < 0) {
                                        $expected = abs($qa - $qb);
                                        for (; $itrIdx < count($itrList); $itrIdx++) {
                                            $ih = $itrList[$itrIdx];
                                            $tq = isset($ih['transfer_qty']) ? (float)$ih['transfer_qty'] : 0.0;
                                            if (abs($tq - $expected) < 0.0001) {
                                                $built = formatItrTransferNote(
                                                    $tq,
                                                    $ih['to_accountable'] ?? '',
                                                    $ih['transfer_type'] ?? '',
                                                    $ih['transfer_other'] ?? ''
                                                );
                                                if ($built) { $noteText = $built; }
                                                $itrIdx++;
                                                break;
                                            }
                                        }
                                        if ($noteText === '') {
                                            $noteText = ($expected == 1)
                                                ? '1 unit transferred'
                                                : (number_format($expected, (fmod($expected,1.0)==0.0?0:2)) . ' units transferred');
                                        }
                                    }
                                }

                                $descOut = htmlspecialchars($histDescription);
                                if ($noteText !== '') { $descOut .= ' (' . htmlspecialchars($noteText) . ')'; }

                                echo '<tr style="background-color:#f8fafc;">';
                                echo '<td>' . $histQtyDisplay . '</td>';
                                echo '<td>' . htmlspecialchars($histUnit) . '</td>';
                                echo '<td>₱' . number_format($histUnitCost, 2) . '</td>';
                                echo '<td>₱' . number_format($histTotal, 2) . '</td>';
                                echo '<td>' . $descOut . '</td>';
                                echo '<td>' . htmlspecialchars($hist['stock_number'] ?? $item['stock_number']) . '</td>';
                                echo '<td>' . htmlspecialchars($item['estimated_useful_life']) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            // No history: render single current row as usual
                            $qtyDisplay = (fmod($currentQtyVal, 1.0) == 0.0) ? number_format($currentQtyVal, 0) : number_format($currentQtyVal, 2);
                            echo '<tr style="font-weight:600; background-color:#e0f2fe;">';
                            echo '<td>' . $qtyDisplay . '</td>';
                            echo '<td>' . htmlspecialchars($unit) . '</td>';
                            echo '<td>₱' . number_format($unitCostVal, 2) . '</td>';
                            echo '<td>₱' . number_format($currentTotalCostVal, 2) . '</td>';
                            echo '<td>' . htmlspecialchars($item['description']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['stock_number']) . '</td>';
                            echo '<td>' . htmlspecialchars($item['estimated_useful_life']) . '</td>';
                            echo '</tr>';
                        }
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
