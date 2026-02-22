<?php
require 'config.php';
require 'functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Property Card</title>
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
        margin: 0 auto;
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
    th { background: #f2f2f2; }

    .no-border { border: none !important; }
    .header-label { background: #f2f2f2; font-weight:bold; }
    .underline {
        display: inline-block;
        width: 250px;
        border-bottom: 1px solid #000;
        height: 12px;
        vertical-align: middle;
        margin-left: 4px;
    }

    .small-underline { width:189px; }
    .large-underline { width:300px; }

    .appendix {
        position:absolute;
        top:10px;
        right:10px;
        font-size:11px;
        font-style:italic;
    }

    .prop-no-underline {
        width: 165px;
        display: inline-block;
        box-sizing: border-box;
    }

    h2 { text-align:center; margin:0 0 15px 0; }

    .print-button {
        background: #007cba;
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        margin-right: 8px;
    }

    .back-link {
        background: #6c757d;
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 13px;
        text-decoration: none;
        display: inline-block;
    }

    .header-row td {
        height: 20px; /* or whatever height you want */
    }

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

<div class="page-wrapper">
    <div class="appendix">Appendix 69</div>
    <h2>PROPERTY CARD</h2>

    <?php
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $main = null;
    $rows = [];
    $property_no = 0;
    $ppe = null;

    if ($id > 0) {
        $q = $conn->prepare("SELECT * FROM item_history_ppe WHERE id = ? LIMIT 1");
        $q->bind_param("i", $id);
        $q->execute();
        $res = $q->get_result();
        if ($main_row = $res->fetch_assoc()) {
            $main = $main_row;
            $property_no = $main_row['property_no'];
        }
        $q->close();
    }

    if ($property_no > 0) {
        // Fetch all history rows
        $q2 = $conn->prepare("SELECT * FROM item_history_ppe WHERE property_no = ? ORDER BY changed_at ASC, id ASC");
        $q2->bind_param("i", $property_no);
        $q2->execute();
        $res2 = $q2->get_result();
        while ($r = $res2->fetch_assoc()) {
            $rows[] = $r;
        }
        $q2->close();

        // Fetch ppe_property info
        $q3 = $conn->prepare("SELECT * FROM ppe_property WHERE id = ? LIMIT 1");
        $q3->bind_param("i", $property_no);
        $q3->execute();
        $res3 = $q3->get_result();
        if ($ppe_row = $res3->fetch_assoc()) {
            $ppe = $ppe_row;
        }
        $q3->close();
    }
    ?>

    <table>
        <!-- ENTITY NAME + FUND CLUSTER -->
        <tr>
            <td colspan="6" class="no-border">
                <strong>Entity Name:</strong>
                <span class="underline large-underline"><?= htmlspecialchars($ppe['entity_name'] ?? ($main['officer_incharge'] ?? '')) ?></span>
            </td>
            <td colspan="3" class="no-border">
                <strong>Fund Cluster:</strong>
                <span class="underline small-underline"><?= htmlspecialchars($ppe['fund_cluster'] ?? 'N/A') ?></span>
            </td>
        </tr>

        <!-- PPE + PROPERTY NUMBER -->
        <tr class="header-row">
            <td colspan="6" class="header-label">
                Property, Plant and Equipment:
                        <span style="font-weight: lighter;"><?= htmlspecialchars($ppe['item_name'] ?? 'N/A') ?></span>
            </td>
            <td colspan="3" class="header-label" style="border-bottom: none !important; padding-bottom: 0 !important;">
                Property Number:
                <span class="underline prop-no-underline"><?= htmlspecialchars($ppe['id'] ?? ($main['property_no'] ?? '')) ?></span>
            </td>
        </tr>

        <!-- DESCRIPTION -->
        <tr class="header-row">
            <td colspan="6" class="header-label">
                Description:
                <span style="font-weight: lighter;"><?= htmlspecialchars($ppe['item_description'] ?? ($main['description'] ?? '')) ?></span>
            </td>
            <td colspan="3" class="header-label" style="border-top: none !important;">
            </td>
        </tr>

        <!-- MAIN HEADER (9 columns) -->
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

            echo '<tr>';
            echo '<td>' . htmlspecialchars(date('Y-m-d', strtotime($r['changed_at']))) . '</td>';
            echo '<td>' . htmlspecialchars($r['PAR_number'] ?? $r['refference_no'] ?? '') . '</td>';
            echo '<td class="text-center">' . ($receipt_qty ?: '') . '</td>';
            echo '<td class="text-center"></td>'; // second Receipt Qty column (if needed)
            echo '<td>' . htmlspecialchars($r['officer_incharge'] ?? '') . '</td>';
            echo '<td class="text-center">' . ($issue_qty ?: '') . '</td>';
                    echo '<td class="text-center" style="width:70px;">' . ($balance_qty ?: '') . '</td>';
                    echo '<td class="currency" style="width:90px;">' . ($amount ? number_format($amount, 2) : '') . '</td>';
                    echo '<td style="width:90px;">' . htmlspecialchars($r['remarks'] ?? '') . '</td>';
            echo '</tr>';

            $row_count++;
        }

        // Fill remaining rows to 15
        for ($i = $row_count; $i < 15; $i++) {
            echo '<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        }
        ?>
    </table>
</div>
</body>
</html>