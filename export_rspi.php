<?php
require 'auth.php';
require 'config.php';
require_once 'functions.php';

// Optional filters
$ics_id = isset($_GET['ics_id']) ? (int)$_GET['ics_id'] : 0;
$selected_category = isset($_GET['category']) ? trim($_GET['category']) : '';
// Month filter (YYYY-MM)
$selected_month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : '';
// Compute period range if month provided (or derive from ICS if only ics_id given)
$periodStart = '';
$periodEnd = '';
if ($selected_month !== '') {
    $periodStart = $selected_month . '-01';
    $periodEnd = date('Y-m-t', strtotime($periodStart));
}
if ($selected_category === 'All') { $selected_category = ''; }

// Load header hints (Entity/Fund) if single ICS is selected
$entity_name = 'TESDA-CAR';
$fund_cluster = '101';
$ics_no_hint = '';
$date_hint = '';
if ($ics_id > 0) {
    if ($res = $conn->query("SELECT entity_name, fund_cluster, ics_no, date_issued FROM ics WHERE ics_id = $ics_id")) {
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $entity_name = $row['entity_name'] ?: $entity_name;
            $fund_cluster = $row['fund_cluster'] ?: $fund_cluster;
            $ics_no_hint = $row['ics_no'] ?: '';
            // If month not explicitly set, derive from this ICS date
            if ($selected_month === '' && !empty($row['date_issued'])) {
                $selected_month = date('Y-m', strtotime($row['date_issued']));
                $periodStart = $selected_month . '-01';
                $periodEnd = date('Y-m-t', strtotime($periodStart));
            }
            $date_hint = $row['date_issued'] ? date('Y-m-d', strtotime($row['date_issued'])) : '';
        }
        $res->close();
    }
}
// If month provided and not a single ICS export, set a Month Year hint
if ($selected_month !== '' && $ics_id === 0) {
    $date_hint = date('F Y', strtotime($periodStart));
}

// Serial No. auto-generate with format NNNN-MM-YYYY; allow override via ?serial=
$serial_param = isset($_GET['serial']) ? trim($_GET['serial']) : '';
$serial_no = '';
if ($serial_param !== '') {
    $serial_no = $serial_param; // trust provided value
} else {
    $seq = 1; // default first for the month (one series per month)
    if ($selected_month !== '') {
        $mm = date('m', strtotime($periodStart));
        $yy = date('Y', strtotime($periodStart));
        $serial_no = str_pad((string)$seq, 4, '0', STR_PAD_LEFT) . '-' . $mm . '-' . $yy;
    } else {
        // If no month, fall back to today's month
        $mm = date('m'); $yy = date('Y');
        $serial_no = '0001-' . $mm . '-' . $yy;
    }
}

// Build RSPI rows (Annex A.7)
$rows = [];
$sql = "
    SELECT 
        i.ics_no,
        '' AS responsibility_center_code,
        ii.stock_number AS property_no,
        COALESCE(NULLIF(sep.remarks, ''), sep.item_description, ii.description) AS item_description,
        COALESCE(ii.unit, '') AS unit,
        ii.quantity AS issued_qty,
        COALESCE(sep.amount, ii.unit_cost) AS unit_cost,
        COALESCE(sep.amount, ii.unit_cost) * ii.quantity AS amount_total
    FROM ics i
    INNER JOIN ics_items ii ON ii.ics_id = i.ics_id
    LEFT JOIN semi_expendable_property sep 
        ON sep.semi_expendable_property_no = ii.stock_number COLLATE utf8mb4_general_ci
    WHERE ii.quantity > 0";
$types = '';
$params = [];
if ($ics_id > 0) { $sql .= " AND i.ics_id = ?"; $types .= 'i'; $params[] = $ics_id; }
if ($periodStart !== '' && $periodEnd !== '') { $sql .= " AND i.date_issued BETWEEN ? AND ?"; $types .= 'ss'; $params[] = $periodStart; $params[] = $periodEnd; }
if ($selected_category !== '' && function_exists('columnExists') && columnExists($conn, 'semi_expendable_property', 'category')) { $sql .= " AND sep.category = ?"; $types .= 's'; $params[] = $selected_category; }
$sql .= " ORDER BY i.date_issued DESC, ii.ics_item_id DESC";
// Execute query and populate $rows
if ($types !== '') {
    if ($stmt = $conn->prepare($sql)) {
        if (!empty($params)) { $stmt->bind_param($types, ...$params); }
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) { $rows[] = $r; }
            $res->close();
        }
        $stmt->close();
    }
} else {
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $res->close();
    }
}

// Safe HTML helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSPI - Export</title>
    <style>
        @media print {
            @page { size: A4 portrait; margin: 12mm; }
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .print-container { page-break-inside: avoid; }
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px; 
            line-height: 1.3; 
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
        }

        @media screen {
            .print-container { max-width: 900px; }
        }

    .header-title { text-align:center; font-weight:bold; font-size:14px; letter-spacing:0.3px; }
    .annex { text-align:right; font-style:italic; font-size:12px; }
        .agency-header {
            position: relative;
            text-align: center;
            padding-top: 12px;
            padding-bottom: 12px;
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
    .label { font-weight:700; width:160px; border: 2px solid #000;}
    .value { min-width:220px; border-bottom:1px solid #000; }
        /* Main grid */
    table.main { width:100%; border-collapse: collapse; }
    table.main th, table.main td {  padding:4px; vertical-align:middle; font-size:10px; }
    table.main th { font-weight:700; }
    table.main thead th { border:2px solid #000; }
    table.main thead th.header-title {
        padding: 8px 4px;
        background: #fff;
        font-weight: bold;
        font-size: 14px;
    }
    table.main tbody td { border-left:2px solid #000; border-right:2px solid #000; border-top:0; border-bottom:0; padding-top:5px; padding-bottom:5px; }
    table.main thead tr.group th { font-weight:700; background:#f8f8f8; font-style: italic; }
    .text-left { text-align:left; }
    .w-ics{width:70px;} .w-rcc{width:100px;} .w-prop{width:130px;} .w-item{width:210px;} .w-unit{width:60px;} .w-qty{width:80px;} .w-uc{width:220px;} .w-amt{width:150px;}
    .sig-line { border-top:1px solid #000; margin:22px auto 0; padding-top:4px; text-align:center; font-size:10px; }
    .print-instructions { background: #fffacd; border: 1px solid #ddd; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
    .print-button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; margin-right: 10px; }
    .print-button:hover { background: #005a87; }
    .back-button { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
    .back-button:hover { background: #545b62; }

    .underline { display:inline-block; border-bottom:1px solid #000; width: 60%;}

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
        <?php 
            $qs = [];
            if ($selected_month !== '') { $qs['month'] = $selected_month; }
            if ($selected_category !== '') { $qs['category'] = $selected_category; }
            $backUrl = ($ics_id>0) 
                ? ('view_ics2.php?ics_id='.(int)$ics_id)
                : ('rspi.php' . (!empty($qs) ? ('?' . http_build_query($qs)) : ''));
        ?>
        <a href="<?= htmlspecialchars($backUrl) ?>" class="back-button">← Back</a>
        <hr style="margin: 20px 0;">
    </div>

    <div class="print-container">
        <div class="annex">Annex A.7</div>

        <div class="agency-header">
            <img src="images/TESDA-Logo-export.png" alt="TESDA Logo">
            <div class="agency-text">
                <div>Republic of the Philippines</div>
                <div><strong>TECHNICAL EDUCATION &amp; SKILLS DEVELOPMENT AUTHORITY</strong></div>
                <div>Cordillera Administrative Region</div>
            </div>
        </div>

            <table class="main">
                <thead>
                        <tr>
                            <th colspan="8" class="header-title">REPORT OF SEMI-EXPENDABLE PROPERTY ISSUED</th>
                        </tr>
                        <tr>
                            <td colspan="6" class="label">Entity Name:<span class="underline"> <?= h($entity_name) ?></span></td>
                            <td colspan="2" class="label">Serial No.:<span class="underline"> <?= h($serial_no) ?></span></td>
                        </tr>
                        <tr>
                            <td colspan="6" class="label" contenteditable="true">Fund Cluster:<span class="underline"><?= h($fund_cluster) ?></span> </td>
                            <td colspan="2" class="label">Date: <span class="underline"><?= h($date_hint) ?></span></td>
                        </tr>

                    <tr class="group">
                        <th colspan="6">To be filled out by the Property and/or Supply Division/Unit</th>
                        <th colspan="2">To be filled out by the Accounting Division/Unit</th>
                    </tr>
                    <tr>
                        <th class="w-ics">ICS No.</th>
                        <th class="w-rcc">Responsibility Center Code</th>
                        <th class="w-prop">Semi-expendable Property No.</th>
                        <th class="w-item">Item Description</th>
                        <th class="w-unit">Unit</th>
                        <th class="w-qty">Quantity Issued</th>
                        <th class="w-uc">Unit Cost</th>
                        <th class="w-amt">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowCount = 0; if (!empty($rows)): foreach ($rows as $r): $rowCount++; ?>
                        <tr>
                            <td contenteditable="true"><?= h($r['ics_no'] ?? $ics_no_hint) ?></td>
                            <td contenteditable="true"></td>
                            <td contenteditable="true"><?= h($r['property_no'] ?? '') ?></td>
                            <td class="text-left" contenteditable="true"><?= h($r['item_description'] ?? '') ?></td>
                            <td contenteditable="true"><?= h($r['unit'] ?? '') ?></td>
                            <td contenteditable="true"><?php $q=(float)($r['issued_qty'] ?? 0); echo fmod($q,1.0)==0.0?number_format($q,0):number_format($q,2); ?></td>
                            <td class="text-right" contenteditable="true">₱<?= number_format((float)($r['unit_cost'] ?? 0), 2) ?></td>
                            <td class="text-right" contenteditable="true">₱<?= number_format((float)($r['amount_total'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <!-- Fill with extra blank rows to make table a little longer and extend vertical lines -->
                    <?php for($i=$rowCount; $i<15; $i++): ?>
                    <tr>
                        <td contenteditable="true"></td>
                        <td contenteditable="true"></td>
                        <td contenteditable="true"></td>
                        <td class="text-left" contenteditable="true"></td>
                        <td contenteditable="true"></td>
                        <td contenteditable="true"></td>
                        <td class="text-right" contenteditable="true"></td>
                        <td class="text-right" contenteditable="true"></td>
                    </tr>
                    <?php endfor; ?>

                    <tr>
                        <!-- Left footer -->
                        <td colspan="4" style="border-top: 2px solid #000; border-right: none !important; text-align: left !important; border-bottom: 2px solid #000;">
                            I hereby certify to the correctness of the above information.
                            <div class="sig-line" style="width: 75%;">Signature over Printed Name of Property and/or Supply Custodian</div>
                        </td>
                        <!-- Right footer -->
                        <td class="right" colspan="3" style="border-top: 2px solid #000; border-left: none !important; text-align: left !important; border-right: none !important; border-bottom: 2px solid #000;">
                            <span style="display:block; margin-left:50px; margin-top:0; margin-bottom:2px; position:relative; top:-6px;">Posted by:</span>
                            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; padding-left: 15px;">
                                <div class="sig-line" style="margin-bottom: 0; width: 75%;">Signature over Printed Name of Designated Accounting Staff</div>
                            </div>
                        </td>
                        <td style="border-top: 2px solid #000; border-left: none !important; border-bottom: 2px solid #000;">
                            <div class="sig-line" style="width: 70%;"><span class="date-line" style="margin-left: 16px; white-space: nowrap;"></span></div>
                        </td>
                    </tr>

                </tbody>
            </table>
    </div>

</body>
</html>
