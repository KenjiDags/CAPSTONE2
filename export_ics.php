<?php
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

    // If this is a return, use the new format
   

    // Otherwise, it's a transfer (remove '(Transferred)')
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
        serial_number,
        inventory_item_no,
        ics_item_id
    FROM ics_items
    WHERE ics_id = $ics_id
    ORDER BY stock_number
";
$item_result = $conn->query($item_query);

$items = [];
$total_amount = 0.0;
$icsItemIds = [];
if ($item_result && $item_result->num_rows > 0) {
    $sumSemiStmt = $conn->prepare("SELECT amount FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
    while ($row = $item_result->fetch_assoc()) {
        $semi_amount = null;
        if ($sumSemiStmt) {
            $sn = $row['stock_number'];
            $sumSemiStmt->bind_param("s", $sn);
            if ($sumSemiStmt->execute()) {
                $semiRes = $sumSemiStmt->get_result();
                if ($semiRes && $semiRes->num_rows > 0) {
                    $semi_amount = (float)($semiRes->fetch_assoc()['amount']);
                }
                if ($semiRes) { $semiRes->free(); }
            }
        }
        $qtyVal = (float)$row['quantity'];
        $unitCostVal = ($semi_amount !== null) ? $semi_amount : (float)$row['unit_cost'];
        $totalCostVal = ($semi_amount !== null) ? ($semi_amount * $qtyVal) : (float)$row['total_cost'];
        $row['unit_cost_display'] = $unitCostVal;
        $row['total_cost_display'] = $totalCostVal;
        $items[] = $row;
        $total_amount += $totalCostVal;

        if (!empty($row['ics_item_id'])) {
            $icsItemIds[(int)$row['ics_item_id']] = true;
        }
    }
    if ($sumSemiStmt) { $sumSemiStmt->close(); }
    $item_result->free();
} elseif ($item_result) {
    $item_result->free();
}

$transferNotesByItemId = [];
$transferNotesByInventory = [];
if (!empty($items)) {
    $whereParts = [];
    $whereParts[] = 'ii.ics_id = ' . $ics_id;
    if (!empty($icsItemIds)) {
        $idList = implode(',', array_map('intval', array_keys($icsItemIds)));
        $whereParts[] = 'ii.ics_item_id IN (' . $idList . ')';
    }
    $whereClause = implode(' OR ', array_unique($whereParts));
    $transferSql = "
        SELECT 
            ii.itr_item_id,
            ii.ics_item_id,
            ii.item_no,
            ii.transfer_qty,
            itr.transfer_type,
            itr.transfer_other,
            itr.to_accountable
        FROM itr_items ii
        INNER JOIN itr ON itr.itr_id = ii.itr_id
        WHERE ii.transfer_qty > 0 AND ($whereClause)
    ";
    $transfer_result = $conn->query($transferSql);
    if ($transfer_result && $transfer_result->num_rows > 0) {
        $seenTransfers = [];
        while ($transferRow = $transfer_result->fetch_assoc()) {
            $transferId = (int)$transferRow['itr_item_id'];
            if (isset($seenTransfers[$transferId])) { continue; }
            $seenTransfers[$transferId] = true;

            $note = formatItrTransferNote(
                $transferRow['transfer_qty'] ?? 0,
                $transferRow['to_accountable'] ?? '',
                $transferRow['transfer_type'] ?? '',
                $transferRow['transfer_other'] ?? ''
            );
            if (!$note) { continue; }

            $itemId = (int)($transferRow['ics_item_id'] ?? 0);
            if ($itemId > 0) {
                $transferNotesByItemId[$itemId][] = $note;
            }

            $itemNo = trim((string)($transferRow['item_no'] ?? ''));
            if ($itemNo !== '') {
                $transferNotesByInventory[$itemNo][] = $note;
            }
        }
        $transfer_result->free();
    } elseif ($transfer_result) {
        $transfer_result->free();
    }
}

// Load history records grouped by ics_item_id
$historyByItemId = [];
$historySql = "SELECT ics_item_id, stock_number, description, unit, quantity_before, quantity_after, quantity_change, unit_cost, total_cost_before, total_cost_after, reference_type, reference_id, reference_no, reference_details, created_at FROM ics_history WHERE ics_id = $ics_id ORDER BY created_at ASC, id ASC";
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

// Load itr_history to enrich transfer notes when reference_details is missing
$itrHistByItemId = [];
$itrHistIdxByItemId = [];
$itrHistSql = "SELECT ics_item_id, transfer_qty, to_accountable, transfer_type, transfer_other, created_at, id FROM itr_history WHERE ics_id = $ics_id ORDER BY created_at ASC, id ASC";
$itrHistRes = $conn->query($itrHistSql);
if ($itrHistRes && $itrHistRes->num_rows > 0) {
    while ($r = $itrHistRes->fetch_assoc()) {
        $iid = isset($r['ics_item_id']) ? (int)$r['ics_item_id'] : 0;
        if ($iid <= 0) { continue; }
        $itrHistByItemId[$iid][] = $r;
    }
    $itrHistRes->free();
} elseif ($itrHistRes) {
    $itrHistRes->free();
}

// Load IIRUSP disposals by stock_number (property_no)
$iirusp_disposals_by_stock = [];
$iirusp_sql = "
    SELECT 
        ii.semi_expendable_property_no,
        ii.quantity,
        ii.disposal_sale,
        ii.disposal_transfer,
        ii.disposal_destruction,
        i.iirusp_no,
        i.as_at
    FROM iirusp_items ii
    JOIN iirusp i ON ii.iirusp_id = i.iirusp_id
    WHERE ii.semi_expendable_property_no IN (
        SELECT DISTINCT stock_number FROM ics_items WHERE ics_id = $ics_id
    )
    ORDER BY i.as_at ASC
";
$iirusp_res = $conn->query($iirusp_sql);
if ($iirusp_res && $iirusp_res->num_rows > 0) {
    while ($r = $iirusp_res->fetch_assoc()) {
        $sn = $r['semi_expendable_property_no'];
        if (!isset($iirusp_disposals_by_stock[$sn])) {
            $iirusp_disposals_by_stock[$sn] = [];
        }
        $iirusp_disposals_by_stock[$sn][] = $r;
    }
    $iirusp_res->free();
} elseif ($iirusp_res) {
    $iirusp_res->free();
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
        
        .items-table tbody tr.history-row td {
            font-style: normal;
        }
        .items-table tbody tr.current-row td {
            font-weight: normal;
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
                $row_count = 0;
                if (!empty($items)) {
                    foreach ($items as $item) {
                        $itemId = (int)($item['ics_item_id'] ?? 0);
                        $qtyVal = (float)($item['quantity'] ?? 0);
                        $unitCostVal = (float)($item['unit_cost_display'] ?? ($item['unit_cost'] ?? 0));
                        $totalCostVal = (float)($item['total_cost_display'] ?? ($item['total_cost'] ?? 0));
                        $qtyDisplay = (fmod($qtyVal, 1.0) == 0.0) ? number_format($qtyVal, 0) : number_format($qtyVal, 2);
                        $unit = isset($item['unit']) && $item['unit'] !== '' ? $item['unit'] : '-';

                        // Get historical records for this item (full series)
                        $historyEntries = [];
                        if ($itemId > 0 && isset($historyByItemId[$itemId])) {
                            $historyEntries = $historyByItemId[$itemId];
                        }

                        // If we have history, render a baseline row from the first entry's quantity_before,
                        // then render each transfer event as its own row. Otherwise, render the single current row.
                        if (!empty($historyEntries)) {
                            $firstEntry = $historyEntries[0];
                            $baseQty = isset($firstEntry['quantity_before']) ? (float)$firstEntry['quantity_before'] : $qtyVal;
                            $baseQtyDisplay = (fmod($baseQty, 1.0) == 0.0) ? number_format($baseQty, 0) : number_format($baseQty, 2);
                            $baseUnitCost = isset($firstEntry['unit_cost']) ? (float)$firstEntry['unit_cost'] : $unitCostVal;
                            $baseTotal = isset($firstEntry['total_cost_before']) && $firstEntry['total_cost_before'] !== null
                                ? (float)$firstEntry['total_cost_before']
                                : ($baseQty * $baseUnitCost);

                            // Baseline/original issuance row (no notes)
                            echo '<tr class="current-row">';
                            echo '<td>' . $baseQtyDisplay . '</td>';
                            echo '<td>' . htmlspecialchars($unit) . '</td>';
                            echo '<td>' . number_format($baseUnitCost, 2) . '</td>';
                            echo '<td>' . number_format($baseTotal, 2) . '</td>';
                            echo '<td class="description">' . htmlspecialchars((string)($item['description'] ?? '')) . '</td>';
                            echo '<td>' . htmlspecialchars($item['stock_number'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($item['estimated_useful_life'] ?? '') . '</td>';
                            echo '</tr>';
                            $row_count++;

                            // Render each history/transfer as its own row with a friendly note
                            // Prepare itr_history pointer for this item
                            $itrHistList = $itrHistByItemId[$itemId] ?? [];
                            if (!isset($itrHistIdxByItemId[$itemId])) { $itrHistIdxByItemId[$itemId] = 0; }
                            $itrIdx =& $itrHistIdxByItemId[$itemId];

                            foreach ($historyEntries as $hist) {
                                $histQty = isset($hist['quantity_after']) ? (float)$hist['quantity_after'] : (float)($hist['quantity_before'] ?? 0);
                                $histQtyDisplay = (fmod($histQty, 1.0) == 0.0) ? number_format($histQty, 0) : number_format($histQty, 2);
                                $histUnitCost = isset($hist['unit_cost']) ? (float)$hist['unit_cost'] : $unitCostVal;
                                $histTotal = isset($hist['total_cost_after']) && $hist['total_cost_after'] !== null ? (float)$hist['total_cost_after'] : ($histQty * $histUnitCost);
                                $histDescription = $hist['description'] ?? ($item['description'] ?? '');
                                $histUnit = $hist['unit'] ?? $unit;

                                // Build note from reference_details if present
                                $noteText = '';
                                if (!empty($hist['reference_details'])) {
                                    $details = json_decode($hist['reference_details'], true);
                                    if (is_array($details)) {
                                        $noteBuilt = formatItrTransferNote(
                                            $details['transfer_qty'] ?? 0,
                                            $details['to_accountable'] ?? '',
                                            $details['transfer_type'] ?? '',
                                            $details['transfer_other'] ?? ''
                                        );
                                        if ($noteBuilt) { $noteText = $noteBuilt; }
                                    }
                                }

                                // Fallback: match against itr_history rows by transfer quantity
                                if ($noteText === '') {
                                    $qtyBefore = isset($hist['quantity_before']) ? (float)$hist['quantity_before'] : null;
                                    $qtyAfter = isset($hist['quantity_after']) ? (float)$hist['quantity_after'] : null;
                                    $qtyChange = null;
                                    if ($qtyBefore !== null && $qtyAfter !== null) { $qtyChange = $qtyAfter - $qtyBefore; }
                                    if ($qtyChange !== null && $qtyChange < 0) {
                                        $expected = abs($qtyChange);
                                        // find the next itr_history entry with matching qty
                                        for ($k = $itrIdx; $k < count($itrHistList); $k++) {
                                            $ih = $itrHistList[$k];
                                            $tq = isset($ih['transfer_qty']) ? (float)$ih['transfer_qty'] : 0.0;
                                            if (abs($tq - $expected) < 0.0001) {
                                                $built = formatItrTransferNote(
                                                    $tq,
                                                    $ih['to_accountable'] ?? '',
                                                    $ih['transfer_type'] ?? '',
                                                    $ih['transfer_other'] ?? ''
                                                );
                                                if ($built) { $noteText = $built; }
                                                $itrIdx = $k + 1; // advance pointer
                                                break;
                                            }
                                        }
                                        // If still empty, provide minimal note
                                        if ($noteText === '') {
                                            $noteText = ($expected == 1)
                                                ? '1 unit returned'
                                                : (number_format($expected, (fmod($expected,1.0)==0.0?0:2)) . ' units returned');
                                        }
                                    }
                                }

                                echo '<tr class="history-row">';
                                echo '<td>' . $histQtyDisplay . '</td>';
                                echo '<td>' . htmlspecialchars($histUnit) . '</td>';
                                echo '<td>' . number_format($histUnitCost, 2) . '</td>';
                                echo '<td>' . number_format($histTotal, 2) . '</td>';
                                $descOut = htmlspecialchars($histDescription);
                                if ($noteText !== '') { $descOut .= ' (' . htmlspecialchars($noteText) . ')'; }
                                echo '<td class="description">' . $descOut . '</td>';
                                echo '<td>' . htmlspecialchars($hist['stock_number'] ?? $item['stock_number'] ?? '') . '</td>';
                                echo '<td>' . htmlspecialchars($item['estimated_useful_life'] ?? '') . '</td>';
                                echo '</tr>';
                                $row_count++;
                            }

                            // Add IIRUSP disposal rows for this item
                            $stockNo = $item['stock_number'] ?? '';
                            if ($stockNo && isset($iirusp_disposals_by_stock[$stockNo])) {
                                foreach ($iirusp_disposals_by_stock[$stockNo] as $disposal) {
                                    $dispQty = (float)($disposal['quantity'] ?? 0);
                                    $dispQtyDisplay = (fmod($dispQty, 1.0) == 0.0) ? number_format($dispQty, 0) : number_format($dispQty, 2);
                                    $dispType = 'Disposal';
                                    if ((float)$disposal['disposal_sale'] > 0) { $dispType = 'Disposed (Sale)'; }
                                    elseif ((float)$disposal['disposal_transfer'] > 0) { $dispType = 'Disposed (Transfer)'; }
                                    elseif ((float)$disposal['disposal_destruction'] > 0) { $dispType = 'Disposed (Destruction)'; }
                                    
                                    $dispDate = $disposal['as_at'] ? date('m/d/Y', strtotime($disposal['as_at'])) : '';
                                    $dispNote = $dispType . ' via IIRUSP ' . htmlspecialchars($disposal['iirusp_no']);
                                    
                                    echo '<tr class="history-row">';
                                    echo '<td>' . $dispQtyDisplay . '</td>';
                                    echo '<td>' . htmlspecialchars($unit) . '</td>';
                                    echo '<td>' . number_format($unitCostVal, 2) . '</td>';
                                    echo '<td>' . number_format($dispQty * $unitCostVal, 2) . '</td>';
                                    echo '<td class="description">' . htmlspecialchars($item['description'] ?? '') . ' (' . $dispNote . ')</td>';
                                    echo '<td>' . htmlspecialchars($stockNo) . '</td>';
                                    echo '<td>' . htmlspecialchars($item['estimated_useful_life'] ?? '') . '</td>';
                                    echo '</tr>';
                                    $row_count++;
                                }
                            }
                        } else {
                            // Check for IIRUSP disposals even if no history
                            $stockNo = $item['stock_number'] ?? '';
                            if ($stockNo && isset($iirusp_disposals_by_stock[$stockNo])) {
                                // Render baseline row first
                                echo '<tr class="current-row">';
                                echo '<td>' . $qtyDisplay . '</td>';
                                echo '<td>' . htmlspecialchars($unit) . '</td>';
                                echo '<td>' . number_format($unitCostVal, 2) . '</td>';
                                echo '<td>' . number_format($totalCostVal, 2) . '</td>';
                                echo '<td class="description">' . htmlspecialchars($item['description'] ?? '') . '</td>';
                                echo '<td>' . htmlspecialchars($item['stock_number'] ?? '') . '</td>';
                                echo '<td>' . htmlspecialchars($item['estimated_useful_life'] ?? '') . '</td>';
                                echo '</tr>';
                                $row_count++;
                                
                                // Then render disposal rows
                                foreach ($iirusp_disposals_by_stock[$stockNo] as $disposal) {
                                    $dispQty = (float)($disposal['quantity'] ?? 0);
                                    $dispQtyDisplay = (fmod($dispQty, 1.0) == 0.0) ? number_format($dispQty, 0) : number_format($dispQty, 2);
                                    $dispType = 'Disposal';
                                    if ((float)$disposal['disposal_sale'] > 0) { $dispType = 'Disposed (Sale)'; }
                                    elseif ((float)$disposal['disposal_transfer'] > 0) { $dispType = 'Disposed (Transfer)'; }
                                    elseif ((float)$disposal['disposal_destruction'] > 0) { $dispType = 'Disposed (Destruction)'; }
                                    
                                    $dispNote = $dispType . ' via IIRUSP ' . htmlspecialchars($disposal['iirusp_no']);
                                    
                                    echo '<tr class="history-row">';
                                    echo '<td>' . $dispQtyDisplay . '</td>';
                                    echo '<td>' . htmlspecialchars($unit) . '</td>';
                                    echo '<td>' . number_format($unitCostVal, 2) . '</td>';
                                    echo '<td>' . number_format($dispQty * $unitCostVal, 2) . '</td>';
                                    echo '<td class="description">' . htmlspecialchars($item['description'] ?? '') . ' (' . $dispNote . ')</td>';
                                    echo '<td>' . htmlspecialchars($stockNo) . '</td>';
                                    echo '<td>' . htmlspecialchars($item['estimated_useful_life'] ?? '') . '</td>';
                                    echo '</tr>';
                                    $row_count++;
                                }
                            } else {
                                // No history and no disposal: render single current row
                                echo '<tr class="current-row">';
                                echo '<td>' . $qtyDisplay . '</td>';
                                echo '<td>' . htmlspecialchars($unit) . '</td>';
                                echo '<td>' . number_format($unitCostVal, 2) . '</td>';
                                echo '<td>' . number_format($totalCostVal, 2) . '</td>';
                                echo '<td class="description">' . htmlspecialchars((string)($item['description'] ?? '')) . '</td>';
                                echo '<td>' . htmlspecialchars($item['stock_number'] ?? '') . '</td>';
                                echo '<td>' . htmlspecialchars($item['estimated_useful_life'] ?? '') . '</td>';
                                echo '</tr>';
                                $row_count++;
                            }
                        }
                    }
                }

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