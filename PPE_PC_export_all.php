<?php
require 'config.php';
require 'functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Property Cards</title>
<style>
    @media print { 
        .no-print { display:none !important; } 
    }

    body { 
        font-family: Arial, sans-serif; 
        font-size: 12px; 
        margin-top:25px; 
    }

    .instruction-box {
        background: #fffacd;
        border: 1px solid #ddd;
        padding: 10px;
        margin-bottom: 12px;
        border-radius: 4px;
        font-size: 12px;
    }

    .page-wrapper {
        width: 850px;
        border: 2px solid #000;
        padding: 20px;
        position: relative;
        margin: 0 auto 40px auto;
        page-break-after: always;
    }

    table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 11px; 
    }

    th, td { 
        border: 1px solid #000; 
        padding: 4px; 
    }
    
    th { background: #f2f2f2; font-weight: bold; }
    .no-border { border: none !important; background: #f2f2f2; font-weight: bold; }
    .header-label { background: #f2f2f2; font-weight:bold; }
    .underline { display: inline-block; width: 250px; border-bottom: 1px solid #000; height: 12px; vertical-align: middle; margin-left: 4px; }
    .small-underline { width:189px; }
    .large-underline { width:300px; }
    .appendix { position:absolute; top:10px; right:10px; font-size:11px; font-style:italic; }
    .prop-no-underline { width: 165px; display: inline-block; box-sizing: border-box; }
    h2 { text-align:center; margin:0 0 15px 0; }
    .print-button { background: #007cba; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; margin-right: 8px; }
    .back-link { background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; font-size: 13px; text-decoration: none; display: inline-block; }
    .header-row td { height: 20px; }
    .text-center { text-align:center; }
    .currency { text-align:right; }
</style>
</head>

<body>
<div class="no-print">
    <div class="instruction-box">
        <strong>📄 Export Instructions:</strong>
        <div>1. Click the Print/Save button below.</div>
        <div>2. In the print dialog choose "Save as PDF" or your printer.</div>
        <div>3. Save or print the document.</div>
    </div>
    <button class="print-button" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <a href="PPE_PC.php" class="back-link">← Back to List</a>
    <hr style="margin:14px 0;">
</div>

<?php
// Fetch all PPE properties that have at least one history record
$ppe_items = [];
$sql = "SELECT p.* FROM ppe_property p
        LEFT JOIN item_history_ppe h ON h.PPE_no = p.PPE_no
        GROUP BY p.PPE_no
        ORDER BY p.id ASC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $ppe_items[] = $row;
}

foreach ($ppe_items as $ppe) {
    $ppe_no = $ppe['PPE_no'];

    // Fetch history per PPE_no
    $rows = [];
    $stmt = $conn->prepare("
        SELECT h.*, p.property_no, p.item_name AS ppe_name, p.item_description AS ppe_description, p.remarks AS ppe_property_remarks
        FROM item_history_ppe h
        LEFT JOIN ppe_property p ON h.PPE_no = p.PPE_no
        WHERE h.PPE_no = ?
        ORDER BY h.changed_at ASC, h.id ASC
    ");
    $stmt->bind_param("s", $ppe_no);
    $stmt->execute();
    $res2 = $stmt->get_result();
    while ($r = $res2->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
?>

<div class="page-wrapper">
    <div class="appendix">Appendix 69</div>
    <h2>PROPERTY CARD</h2>

    <table>
        <!-- Entity + Fund -->
        <tr>
            <td colspan="6" class="no-border">
                <strong>Entity Name:</strong>
                <span class="underline large-underline"><?= htmlspecialchars($ppe['entity_name'] ?? '') ?></span>
            </td>
            <td colspan="3" class="no-border">
                <strong>Fund Cluster:</strong>
                <span class="underline small-underline"><?= htmlspecialchars($ppe['fund_cluster'] ?? '') ?></span>
            </td>
        </tr>

        <!-- PPE + Property Number -->
        <tr class="header-row">
            <td colspan="6" class="header-label">
                Property, Plant and Equipment:
                <span style="font-weight: lighter;"><?= htmlspecialchars($ppe['item_name'] ?? '') ?></span>
            </td>
            <td colspan="3" class="header-label" style="border-bottom: none !important; padding-bottom: 0 !important;">
                Property Number:
                <span class="underline prop-no-underline"><?= htmlspecialchars($ppe['property_no'] ?? '') ?></span>
            </td>
        </tr>

        <!-- Description -->
        <tr class="header-row">
            <td colspan="6" class="header-label">
                Description:
                <span style="font-weight: lighter;"><?= htmlspecialchars($ppe['item_description'] ?? '') ?></span>
            </td>
            <td colspan="3" class="header-label" style="border-top: none !important;"></td>
        </tr>

        <!-- Main header -->
        <tr>
            <th rowspan="2">Date</th>
            <th rowspan="2">Reference / PAR No.</th>
            <th colspan="2">Receipt</th>
            <th colspan="2">Issue / Transfer / Disposal</th>
            <th rowspan="2" style="width:70px;">Balance Qty.</th>
            <th rowspan="2" style="width:90px;">Amount</th>
            <th rowspan="2" style="width:90px;">Remarks</th>
        </tr>
        <tr>
            <th>Qty.</th>
            <th>Qty.</th>
            <th>Office/Officer</th>
            <th>Qty.</th>
        </tr>

        <?php
        $row_count = 0;
        foreach ($rows as $r) {
            $receipt_qty = (int)($r['receipt_qty'] ?? 0);
            $issue_qty = (int)($r['issue_qty'] ?? 0);
            $balance_qty = (int)($r['balance_qty'] ?? 0);
            $amount = ((float)($r['unit_cost'] ?? 0)) * ((int)($r['quantity_on_hand'] ?? 0));

            // Reference logic: PPE_no for addition, PAR_number for transfer
            $reference_no = '';
            if (isset($r['change_direction']) && $r['change_direction'] === 'add') {
                $reference_no = $r['PPE_no'] ?? '';
            } elseif (isset($r['change_direction']) && $r['change_direction'] === 'transfer') {
                $reference_no = $r['PAR_number'] ?? '';
            } else {
                $reference_no = $r['PAR_number'] ?? $r['PPE_no'] ?? '';
            }

            echo '<tr>';
            echo '<td>' . htmlspecialchars(date('Y-m-d', strtotime($r['changed_at']))) . '</td>';
            echo '<td>' . htmlspecialchars($reference_no) . '</td>';
            echo '<td class="text-center">' . ($receipt_qty ?: '') . '</td>';
            echo '<td class="text-center"></td>';
            echo '<td>' . htmlspecialchars($r['officer_incharge'] ?? '') . '</td>';
            echo '<td class="text-center">' . ($issue_qty ?: '') . '</td>';
            echo '<td class="text-center">' . ($balance_qty ?: '') . '</td>';
            echo '<td class="currency">' . ($amount ? number_format($amount, 2) : '') . '</td>';
            echo '<td>' . htmlspecialchars($r['ppe_property_remarks'] ?? '') . '</td>';
            echo '</tr>';

            $row_count++;
        }

        for ($i = $row_count; $i < 15; $i++) {
            echo '<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        }
        ?>
    </table>
</div>

<?php } // end foreach PPE ?>
</body>
</html>