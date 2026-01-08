<?php
require 'config.php';
require 'functions.php';

$iirusp_id = isset($_GET['iirusp_id']) ? (int)$_GET['iirusp_id'] : 0;
$iirusp = null; $items = [];

if ($iirusp_id) {
    $stmt = $conn->prepare("SELECT * FROM iirusp WHERE iirusp_id=? LIMIT 1");
    $stmt->bind_param('i', $iirusp_id);
    $stmt->execute();
    $iirusp = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT * FROM iirusp_items WHERE iirusp_id=? ORDER BY iirusp_item_id ASC");
    $stmt->bind_param('i', $iirusp_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $items[] = $r; }
    $stmt->close();
}

if (!$iirusp) { echo 'IIRUSP not found.'; exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>IIRUSP <?= htmlspecialchars($iirusp['iirusp_no']) ?></title>
<style>
@media print { .no-print { display: none; } }
body { font-family: Arial, sans-serif; font-size: 11pt; margin: 20px; }
.header { text-align: center; margin-bottom: 20px; }
.header h2 { margin: 5px 0; font-size: 14pt; }
.header h3 { margin: 5px 0; font-size: 12pt; font-weight: normal; }
.info-section { margin: 15px 0; }
.info-row { display: flex; justify-content: space-between; margin: 5px 0; }
.info-row label { font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 9pt; }
table th, table td { border: 1px solid #000; padding: 4px; text-align: center; }
table th { background: #f0f0f0; font-weight: bold; }
.text-left { text-align: left !important; }
.text-right { text-align: right !important; }
.signature-section { margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px; page-break-inside: avoid; }
.signature-box { text-align: center; }
.signature-line { border-top: 1px solid #000; margin: 40px 10px 5px; padding-top: 5px; font-weight: bold; }
.signature-label { font-size: 9pt; margin-top: 3px; }
.footer-text { font-size: 8pt; margin-top: 20px; font-style: italic; }
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:20px;">
    <button onclick="window.print()">Print</button>
    <button onclick="window.close()">Close</button>
</div>

<div class="header">
    <h2>INVENTORY AND INSPECTION REPORT OF UNSERVICEABLE</h2>
    <h2>SEMI-EXPENDABLE PROPERTY</h2>
    <h3>(IIRUSP)</h3>
</div>

<div class="info-section">
    <div class="info-row">
        <div><label>As at:</label> <?= date('F d, Y', strtotime($iirusp['as_at'])) ?></div>
        <div><label>IIRUSP No.:</label> <?= htmlspecialchars($iirusp['iirusp_no']) ?></div>
    </div>
    <div class="info-row">
        <div><label>Entity Name:</label> <?= htmlspecialchars($iirusp['entity_name']) ?></div>
        <div><label>Fund Cluster:</label> <?= htmlspecialchars($iirusp['fund_cluster']) ?></div>
    </div>
    <div class="info-row" style="flex-direction:column;">
        <div><label>Name of Accountable Officer:</label> <?= htmlspecialchars($iirusp['accountable_officer_name']) ?></div>
        <div style="display:flex; gap:30px; margin-top:5px;">
            <div><label>Designation:</label> <?= htmlspecialchars($iirusp['accountable_officer_designation']) ?></div>
            <div><label>Station:</label> <?= htmlspecialchars($iirusp['accountable_officer_station']) ?></div>
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th rowspan="2" style="width:60px;">Date<br>Acquired</th>
            <th rowspan="2" style="min-width:120px;">Particulars/<br>Articles</th>
            <th rowspan="2" style="width:80px;">Semi-expendable<br>Property No.</th>
            <th rowspan="2" style="width:40px;">Qty</th>
            <th rowspan="2" style="width:40px;">Unit</th>
            <th rowspan="2" style="width:70px;">Unit<br>Cost</th>
            <th rowspan="2" style="width:80px;">Total<br>Cost</th>
            <th rowspan="2" style="width:80px;">Accumulated<br>Impairment<br>Losses</th>
            <th rowspan="2" style="width:80px;">Carrying<br>Amount</th>
            <th rowspan="2" style="min-width:100px;">Remarks</th>
            <th colspan="5">INSPECTION and DISPOSAL</th>
        </tr>
        <tr>
            <th style="width:60px;">Sale</th>
            <th style="width:60px;">Transfer</th>
            <th style="width:60px;">Destruction</th>
            <th style="width:60px;">Others</th>
            <th style="width:60px;">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php $grandTotal = 0; foreach ($items as $item): $grandTotal += $item['total_cost']; ?>
        <tr>
            <td><?= $item['date_acquired'] ? date('m/d/Y', strtotime($item['date_acquired'])) : '-' ?></td>
            <td class="text-left"><?= htmlspecialchars($item['particulars'] ?? '') ?></td>
            <td><?= htmlspecialchars($item['semi_expendable_property_no'] ?? '') ?></td>
            <td><?= $item['quantity'] ?></td>
            <td><?= htmlspecialchars($item['unit'] ?? '') ?></td>
            <td class="text-right"><?= number_format($item['unit_cost'], 2) ?></td>
            <td class="text-right"><?= number_format($item['total_cost'], 2) ?></td>
            <td class="text-right"><?= number_format($item['accumulated_impairment'], 2) ?></td>
            <td class="text-right"><?= number_format($item['carrying_amount'], 2) ?></td>
            <td class="text-left"><?= htmlspecialchars($item['remarks'] ?? '') ?></td>
            <td class="text-right"><?= $item['disposal_sale'] > 0 ? number_format($item['disposal_sale'], 2) : '-' ?></td>
            <td class="text-right"><?= $item['disposal_transfer'] > 0 ? number_format($item['disposal_transfer'], 2) : '-' ?></td>
            <td class="text-right"><?= $item['disposal_destruction'] > 0 ? number_format($item['disposal_destruction'], 2) : '-' ?></td>
            <td class="text-left"><?= htmlspecialchars($item['disposal_others'] ?? '') ?></td>
            <td class="text-right"><?= number_format($item['disposal_total'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (count($items) < 10): for ($i = count($items); $i < 10; $i++): ?>
        <tr>
            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
            <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
        </tr>
        <?php endfor; endif; ?>
        <tr style="font-weight:bold;">
            <td colspan="6" class="text-right">TOTAL:</td>
            <td class="text-right">₱<?= number_format($grandTotal, 2) ?></td>
            <td colspan="8"></td>
        </tr>
    </tbody>
</table>

<div class="footer-text">
    <p>I HEREBY request inspection and disposition, pursuant to Section 79 of P.D. No. 1445, of the property enumerated above.</p>
</div>

<div class="signature-section" style="grid-template-columns: 1fr 1fr 1fr;">
    <div class="signature-box">
        <div class="signature-line"><?= htmlspecialchars($iirusp['requested_by']) ?></div>
        <div class="signature-label">Signature over Printed Name of<br>Accountable Officer</div>
    </div>
    <div class="signature-box">
        <div class="signature-line"><?= htmlspecialchars($iirusp['inspection_officer']) ?></div>
        <div class="signature-label">Signature over Printed Name of<br>Inspection Officer</div>
    </div>
    <div class="signature-box">
        <div class="signature-line"><?= htmlspecialchars($iirusp['witness']) ?></div>
        <div class="signature-label">Signature over Printed Name<br>of Witness</div>
    </div>
</div>

<div class="signature-section" style="margin-top:30px;">
    <div class="signature-box">
        <div class="signature-line"><?= htmlspecialchars($iirusp['approved_by']) ?></div>
        <div class="signature-label">Approved by</div>
    </div>
    <div></div>
</div>

<div style="margin-top:30px; font-size:9pt;">
    <p><strong>I CERTIFY</strong> that I have inspected each and every article enumerated in this report, and that the disposition made thereof was, in my judgment, the best for the public interest.</p>
    <p><strong>I CERTIFY</strong> that I have witnessed the disposition of the articles enumerated on this report this _____ day of _____________, _____.</p>
</div>

</body>
</html>
