<?php
require 'config.php';

if (!isset($_GET['ics_id'])) {
    die("❌ Error: ICS ID not specified in the URL.");
}
$ics_id = (int)$_GET['ics_id'];

// Fetch ICS header
$ics_res = $conn->query("SELECT * FROM ics WHERE ics_id = $ics_id");
if (!$ics_res) { die("❌ Database error: " . $conn->error); }
if ($ics_res->num_rows === 0) { die("❌ No ICS record found for ICS ID: $ics_id"); }
$ics = $ics_res->fetch_assoc();

// Build RSPI rows for this ICS
$rspi_rows = [];
$rspi_total = 0.0;
$sql = "
    SELECT 
        ii.stock_number AS property_no,
        COALESCE(NULLIF(sep.remarks, ''), sep.item_description, ii.description) AS item_description,
        COALESCE(ii.unit, '') AS unit,
        ii.quantity AS issued_qty,
        COALESCE(sep.amount, ii.unit_cost) AS unit_cost,
        (COALESCE(sep.amount, ii.unit_cost) * ii.quantity) AS amount_total,
        COALESCE(sep.estimated_useful_life, ii.estimated_useful_life, '') AS useful_life
    FROM ics_items ii
    LEFT JOIN semi_expendable_property sep
        ON sep.semi_expendable_property_no = ii.stock_number COLLATE utf8mb4_general_ci
    WHERE ii.ics_id = $ics_id AND ii.quantity > 0
    ORDER BY ii.stock_number
";
if ($res = $conn->query($sql)) {
    while ($r = $res->fetch_assoc()) { $rspi_rows[] = $r; $rspi_total += (float)$r['amount_total']; }
    $res->close();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$entityName = isset($ics['entity_name']) ? $ics['entity_name'] : '';
$fundCluster = isset($ics['fund_cluster']) ? $ics['fund_cluster'] : '';
$icsNo = isset($ics['ics_no']) ? $ics['ics_no'] : '';
$dateIssued = isset($ics['date_issued']) && $ics['date_issued'] ? date('Y-m-d', strtotime($ics['date_issued'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RSPI for ICS <?= h($icsNo) ?></title>
    <style>
        @page { size: landscape; margin: 10mm; }
        @media print { body { margin:0; padding:0; font-size: 11px; } .no-print { display:none; } table, tr { page-break-inside: avoid; } }
        body { font-family: Arial, sans-serif; margin: 20px; background:#f5f5f5; }
        .button-bar { margin: 10px 0 20px; display:flex; gap:10px; }
        .btn { background:#007bff; color:#fff; padding:8px 14px; border-radius:5px; text-decoration:none; font-weight:600; }
        .btn.secondary { background:#6c757d; }
        .container { background:#fff; border:2px solid #000; padding:10px; }
        .title { text-align:center; font-weight:800; text-transform:uppercase; margin:5px 0 10px; }
        .subtitle { text-align:center; font-weight:600; margin:0 0 10px; }
        .header { width:100%; border-collapse: collapse; margin-bottom: 0; }
        .header td { border:1px solid #000; padding:6px 8px; vertical-align:top; }
        .label { font-weight:700; width:160px; }
        .value { min-width:180px; }
        .spacer { width: 40px; border:none !important; }
        table.main { width:100%; border-collapse: collapse; margin-top:0; }
        table.main th, table.main td { border: 1px solid #000; padding:4px; text-align:center; vertical-align:middle; font-size: 11px; }
        table.main th { background: #fafafa; }
        .text-left { text-align:left; }
        .text-right { text-align:right; }
        .cert { margin-top: 14px; font-size: 12px; }
        .sign-row { display:flex; justify-content: space-between; gap: 20px; margin-top: 24px; }
        .sign-block { width:48%; border:1px solid #000; padding:10px; min-height:110px; }
        .muted { color:#555; font-size: 11px; }
        .w-70 { width:70px; } .w-90{ width:90px; } .w-120{ width:120px; } .w-160{ width:160px; } .w-220{ width:220px; }
    </style>
</head>
<body>
    <div class="no-print button-bar">
        <a class="btn" href="#" onclick="window.print(); return false;">🖨️ Print / Save as PDF</a>
        <a class="btn secondary" href="view_ics2.php?ics_id=<?= $ics_id ?>">← Back to ICS View</a>
    </div>

    <div class="container">
        <div class="title">REPORT OF SEMI-EXPENDABLE PROPERTY ISSUED (RSPI)</div>
        <table class="header">
            <tr>
                <td class="label">Entity Name:</td>
                <td class="value" contenteditable="true"><?= h($entityName) ?></td>
                <td class="spacer"></td>
                <td class="label">Fund Cluster:</td>
                <td class="value" contenteditable="true"><?= h($fundCluster) ?></td>
            </tr>
            <tr>
                <td class="label">Serial No.:</td>
                <td class="value" contenteditable="true" title="Format: 0000-00-0000"></td>
                <td class="spacer"></td>
                <td class="label">Date:</td>
                <td class="value" contenteditable="true"><?= h($dateIssued) ?></td>
            </tr>
            <tr>
                <td class="label">ICS No.:</td>
                <td class="value" contenteditable="true"><?= h($icsNo) ?></td>
                <td class="spacer"></td>
                <td class="label">Responsibility Center Code:</td>
                <td class="value" contenteditable="true" title="Code of the cost/responsibility center"></td>
            </tr>
        </table>

        <table class="main">
            <thead>
                <tr>
                    <th class="w-120">Semi-Expendable Property No.</th>
                    <th class="w-220">Item Description</th>
                    <th class="w-90">Unit</th>
                    <th class="w-90">Quantity Issued</th>
                    <th class="w-90">Unit Cost</th>
                    <th class="w-120">Amount</th>
                    <th class="w-160">Estimated Useful Life</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rspi_rows)): foreach ($rspi_rows as $row): ?>
                    <tr>
                        <td><?= h($row['property_no'] ?? '') ?></td>
                        <td class="text-left"><?= h($row['item_description'] ?? '') ?></td>
                        <td><?= h($row['unit'] ?? '') ?></td>
                        <td><?php $q=(float)($row['issued_qty'] ?? 0); echo fmod($q,1.0)==0.0?number_format($q,0):number_format($q,2); ?></td>
                        <td class="text-right">₱<?= number_format((float)($row['unit_cost'] ?? 0), 2) ?></td>
                        <td class="text-right">₱<?= number_format((float)($row['amount_total'] ?? 0), 2) ?></td>
                        <td><?= h($row['useful_life'] ?? '') ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7">No semi-expendable items found for this ICS.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right"><strong>Total</strong></td>
                    <td class="text-right"><strong>₱<?= number_format($rspi_total, 2) ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="cert">
            <div class="muted">Certification: This is to certify the correctness of the report as to the semi-expendable property issued within the date covered.</div>
            <div contenteditable="true" style="border:1px solid #000; padding:8px; min-height:40px; margin-top:6px;">Enter certification note and details here. This will be signed by the Property and/or Supply Custodian.</div>
        </div>

        <div class="sign-row">
            <div class="sign-block">
                <div style="font-weight:700; margin-bottom:6px;">Prepared by / Property or Supply Custodian</div>
                <div class="muted">Name, Signature, Date</div>
                <div contenteditable="true" style="border:1px solid #000; padding:8px; min-height:60px; margin-top:6px;"></div>
            </div>
            <div class="sign-block">
                <div style="font-weight:700; margin-bottom:6px;">Posted by / Accounting Division/Unit</div>
                <div class="muted">Printed name, signature and date of posting to SPLC</div>
                <div contenteditable="true" style="border:1px solid #000; padding:8px; min-height:60px; margin-top:6px;"></div>
            </div>
        </div>

        <div class="muted" style="margin-top:12px;">Note: At end of month, RSPIs shall be consolidated by Accounting for JEV preparation. Property/Supply and Accounting shall reconcile SPLCs and Semi-Expendable Property Cards to identify and adjust discrepancies.</div>
    </div>
</body>
</html>
