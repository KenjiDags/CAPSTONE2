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
            font-size: 12px; /* match RSMI export base font-size */
            line-height: 1.3; /* match RSMI export line-height */
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
            .print-container { max-width: 720px; }
        }

    .header-title { text-align:center; font-weight:bold; font-size:14px; margin-bottom:8px; letter-spacing:0.3px; }
    .annex { text-align:right; font-style:italic; font-size:12px; }

        table.header { width:100%; border-collapse: collapse; margin-bottom:6px; }
        table.header td { border:none; padding:6px 8px; vertical-align:top; }
        .label { font-weight:700; width:160px; }
        .value { min-width:220px; border-bottom:1px solid #000; }
        .spacer { width:40px; }

        /* Main grid with thick outer border similar to ITR */
        .form-frame { border:2px solid #000; padding:0; }
        table.main { width:100%; border-collapse: collapse; border: none; border-bottom:2px solid #000; }
    table.main th, table.main td { border:1px solid #000; padding:4px; text-align:center; vertical-align:middle; font-size:10px; }
    table.main th { font-weight:700; }
    /* Remove horizontal row lines in body, keep vertical lines and make rows taller */
    table.main thead th { border:1px solid #000; }
    table.main tbody td { border-left:1px solid #000; border-right:1px solid #000; border-top:0; border-bottom:0; padding-top:10px; padding-bottom:10px; }
        table.main thead tr.group th { font-weight:700; background:#f8f8f8; font-style: italic; }
        .text-left { text-align:left; }
        /* Column widths */
        .w-ics{width:70px;} .w-rcc{width:120px;} .w-prop{width:120px;} .w-item{width:260px;} .w-unit{width:60px;} .w-qty{width:80px;} .w-uc{width:90px;} .w-amt{width:100px;}

    /* Footer certification section aligned to column grid */
    table.footer { border-collapse: collapse; width: auto; }
    table.footer td { padding:10px; vertical-align:bottom; }
    table.footer td.right { border-left:1px solid #000; }
    .sig-line { border-top:1px solid #000; margin:22px auto 0; padding-top:4px; text-align:center; width:85%; font-size:10px; }
        .date-line { text-align:right; margin-top:8px; }

        .print-instructions { background: #fffacd; border: 1px solid #ddd; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .print-button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; margin-right: 10px; }
        .print-button:hover { background: #005a87; }
        .back-button { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
        .back-button:hover { background: #545b62; }
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
        <div class="header-title">REPORT OF SEMI-EXPENDABLE PROPERTY ISSUED</div>

        <table class="header">
            <tr>
                <td class="label">Entity Name:</td>
                <td class="value" contenteditable="true"><?= h($entity_name) ?></td>
                <td class="spacer"></td>
                <td class="label">Serial No.:</td>
                <td class="value" contenteditable="true" title="Format: 0000-00-0000"><?= h($serial_no) ?></td>
            </tr>
            <tr>
                <td class="label">Fund Cluster:</td>
                <td class="value" contenteditable="true"><?= h($fund_cluster) ?></td>
                <td class="spacer"></td>
                <td class="label">Date:</td>
                <td class="value" contenteditable="true"><?= h($date_hint) ?></td>
            </tr>
        </table>

        <div class="form-frame">
            <table class="main">
                <thead>
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
                </tbody>
            </table>

            <table class="footer">
                <colgroup>
                    <col style="width:70px;" />
                    <col style="width:120px;" />
                    <col style="width:120px;" />
                    <col style="width:260px;" />
                    <col style="width:60px;" />
                    <col style="width:80px;" />
                    <col style="width:90px;" />
                    <col style="width:100px;" />
                </colgroup>
                <tr>
                    <!-- Left footer spans first five columns: aligns vertical split with line between Unit and Quantity Issued -->
                    <td colspan="5">
                        I hereby certify to the correctness of the above information.
                        <div class="sig-line">Signature over Printed Name of Property and/or Supply Custodian</div>
                    </td>
                    <!-- Right footer spans last three columns -->
                    <td class="right" colspan="3">
                        Posted by:
                        <div class="sig-line">Signature over Printed Name of Designated Accounting Staff</div>
                        <div class="date-line">Date: ____________</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <script>
        // window.addEventListener('load', function() { setTimeout(function(){ window.print(); }, 400); });
    </script>
</body>
</html>
