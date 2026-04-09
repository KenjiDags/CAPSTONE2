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

    .header-label { 
        background: #f2f2f2; 
        font-weight:bold; 
    }
    
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

    .agency-header {
        position: relative;
        text-align: center;
        padding-top: 12px;
        padding-bottom: 10px;
    }

    .agency-header img {
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 60px;
        height: 60px;
        object-fit: contain;
    }

    .agency-text {
        text-align: center;
        line-height: 1.2;
        display: inline-block;
        font-size: 12px;
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
        height: 20px; 
    }

    .table-main-title {
        text-align: center;
        font-weight: bold;
        font-size: 18px;
        letter-spacing: 1px;
        padding: 8px 6px;
        background: #fff;
    }

    .text-center { 
        text-align:center; 
    }

    @media print {
        .agency-header {
            padding-top: 8px;
            padding-bottom: 6px;
        }
        .agency-header img {
            width: 42px;
            height: 42px;
        }
        .agency-text {
            font-size: 8px;
            line-height: 1.1;
        }
    }
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

    <div class="agency-header">
        <img src="images/TESDA-Logo-export.png" alt="TESDA Logo">
        <div class="agency-text">
            <div>Republic of the Philippines</div>
            <div><strong>TECHNICAL EDUCATION &amp; SKILLS DEVELOPMENT AUTHORITY</strong></div>
            <div>Cordillera Administrative Region</div>
        </div>
    </div>

    <?php
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $rows = [];
    $ppe = null;
    $main_ppe_no = '';

    if ($id > 0) {
        // Get main PPE_no from selected row
        $stmt = $conn->prepare("SELECT PPE_no FROM item_history_ppe WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $main_ppe_no = $row['PPE_no'];
        }
        $stmt->close();

        if ($main_ppe_no) {
            // Fetch all history rows for this PPE_no
            $stmt2 = $conn->prepare("
                SELECT h.*, p.property_no, p.item_name AS ppe_name, p.item_description AS ppe_description, 
                       p.entity_name, p.fund_cluster, p.remarks AS ppe_property_remarks
                FROM item_history_ppe h
                LEFT JOIN ppe_property p ON h.PPE_no = p.PPE_no
                WHERE h.PPE_no = ?
                ORDER BY h.changed_at ASC, h.id ASC
            ");
            $stmt2->bind_param("s", $main_ppe_no);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            while ($r = $res2->fetch_assoc()) {
                $rows[] = $r;
            }
            $stmt2->close();

            // Fetch PPE info for header
            $stmt3 = $conn->prepare("SELECT * FROM ppe_property WHERE PPE_no = ? LIMIT 1");
            $stmt3->bind_param("s", $main_ppe_no);
            $stmt3->execute();
            $res3 = $stmt3->get_result();
            if ($ppe_row = $res3->fetch_assoc()) {
                $ppe = $ppe_row;
            }
            $stmt3->close();
        }
    }
    ?>

    <table>
        <tr>
            <th colspan="9" class="table-main-title">PROPERTY CARD</th>
        </tr>

        <!-- ENTITY NAME + FUND CLUSTER -->
        <tr>
            <td colspan="6">
                <strong>Entity Name:</strong>
                <span class="underline large-underline"><?= htmlspecialchars($ppe['entity_name'] ?? '') ?></span>
            </td>
            <td colspan="3">
                <strong>Fund Cluster:</strong>
                <span class="underline small-underline"><?= htmlspecialchars($ppe['fund_cluster'] ?? '') ?></span>
            </td>
        </tr>

        <!-- PPE + PROPERTY NUMBER -->
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

        <!-- DESCRIPTION -->
        <tr class="header-row">
            <td colspan="6" class="header-label">
                Description:
                <span style="font-weight: lighter;"><?= htmlspecialchars($ppe['item_description'] ?? '') ?></span>
            </td>
            <td colspan="3" class="header-label" style="border-top: none !important;"></td>
        </tr>

        <!-- MAIN HEADER -->
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

            // Show PPE_no for initial addition, PAR_number for transfers
            $reference_no = $r['PAR_number'] ?? $r['PPE_no'] ?? '';

            echo '<tr>';
            echo '<td>' . htmlspecialchars(date('Y-m-d', strtotime($r['changed_at']))) . '</td>';
            echo '<td>' . htmlspecialchars($reference_no) . '</td>';
            echo '<td class="text-center">' . ($receipt_qty ?: '') . '</td>';
            echo '<td class="text-center"></td>';
            echo '<td>' . htmlspecialchars($r['officer_incharge'] ?? '') . '</td>';
            echo '<td class="text-center">' . ($issue_qty ?: '') . '</td>';
            echo '<td class="text-center">' . ($balance_qty ?: '') . '</td>';
            echo '<td class="currency" style="width:90px;">' . ($amount ? number_format($amount,2) : '') . '</td>';
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
</body>
</html>