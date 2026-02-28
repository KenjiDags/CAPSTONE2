<?php
require 'config.php';
require 'functions.php';

$par_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($par_id <= 0) die("Invalid PAR ID");

// Fetch PAR header
$stmt = $conn->prepare("SELECT * FROM ppe_par WHERE par_id = ?");
$stmt->bind_param("i", $par_id);
$stmt->execute();
$par_result = $stmt->get_result();
if ($par_result->num_rows === 0) die("PAR not found");
$par = $par_result->fetch_assoc();
$stmt->close();

// Fetch PAR items
$items_stmt = $conn->prepare("
    SELECT p.* FROM ppe_par_items pi 
    JOIN ppe_property p ON pi.ppe_id = p.id 
    WHERE pi.par_id = ?
    ORDER BY p.par_no
");
$items_stmt->bind_param("i", $par_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Property Acknowledgment Receipt - <?= htmlspecialchars($par['par_no']) ?></title>

<style>
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    margin: 25px;
}

@media print {
    .no-print { display: none; }
    .page-wrapper { border: none !important; }
}

.page-wrapper {
    max-width: 850px;
    margin: 0 auto;
    border: 2px solid #000;
    padding: 25px;
    position: relative;
}

.appendix {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 11px;
    font-style: italic;
}

.title {
    text-align: center;
    font-weight: bold;
    font-size: 15px;
    margin-bottom: 60px;
}

/* Header underline fields */
.underline {
    display: inline-block;
    border-bottom: 1px solid #000;
    height: 14px;
    vertical-align: bottom;
    margin-left: 5px;
}

/* TABLE */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

th, td {
    border: 1px solid black;
    padding: 3px;
    text-align: center;
}

td.desc {
    text-align: left;
}

/* SIGNATORY AREA */
.signatory-cell {
    padding: 0 !important;
}

.signatory-wrapper {
    display: block;
    width: 100%;
}

.signatory-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 11px;
}

.signatory-table td {
    padding: 20px;
    vertical-align: top;
    box-sizing: border-box;
}

.sig-line {
    border-bottom: 1px solid #000;
    height: 12px;
    margin: 0 auto;
    width: 80%;
}

.sig-small {
    width: 40%;
}

.sig-label {
    text-align: center;
    font-size: 11px;
    margin-top: 4px;
}

/* PRINT BUTTONS */
.print-button {
    background: #007cba;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
}

.back-link {
    background: #6c757d;
    color: white;
    padding: 8px 16px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
}

.instruction-box {
    background: #fffacd;
    padding: 10px;
    border: 1px solid #ddd;
    margin-bottom: 12px;
}

#descColumn {
    border-right: 2px solid #000;
}
</style>
</head>

<body>

<!-- PRINT CONTROLS -->
<div class="no-print">
    <div class="instruction-box">
        <strong>📄 Export Instructions:</strong>
        <div>1. Click Print / Save.</div>
        <div>2. Choose “Save as PDF”.</div>
    </div>

    <button class="print-button" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <a href="PPE_PAR.php" class="back-link">← Back to PAR List</a>
    <hr>
</div>

<div class="page-wrapper">
    <div class="appendix">Appendix 71</div>

    <div class="title">PROPERTY ACKNOWLEDGMENT RECEIPT</div>

    <!-- HEADER -->
    <div style="font-size:11px;">
        <div style="margin-bottom:8px;">
            <strong>Entity Name:</strong>
            <span class="underline" style="width:300px;"><?= htmlspecialchars($par['entity_name']) ?></span>
        </div>

        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
            <div>
                <strong>Fund Cluster:</strong>
                <span class="underline" style="width:300px;"><?= htmlspecialchars($par['fund_cluster']) ?></span>
            </div>
            <div>
                <strong>PAR No.:</strong>
                <span class="underline" style="width:150px;"><?= htmlspecialchars($par['par_no']) ?></span>
            </div>
        </div>
    </div>

    <!-- MAIN TABLE -->
     <div class="table-container">
        <table style="border:2px solid black; border-bottom:none;">
            <tr style="border-bottom: 2px solid #000;">
                <th style="width:7%;">Quantity</th>
                <th style="width:7%;">Unit</th>
                <th id="descColumn" style="width:30%;">Description</th>
                <th style="width:16%;">PAR No.</th>
                <th style="width:12%;">Date Acquired</th>
                <th style="width:12%;">Amount</th>
            </tr>

            <?php foreach ($items as $item): ?>
            <tr>
                <td>1</td>
                <td><?= htmlspecialchars($item['unit'] ?? 'unit') ?></td>
                <td class="desc" id="descColumn"><?= htmlspecialchars($item['item_name'] . ' - ' . $item['item_description']) ?></td>
                <td><?= htmlspecialchars($par['par_no']) ?></td>
                <td><?= $par['received_by_date'] ? date('m/d/Y', strtotime($par['received_by_date'])) : '' ?></td>
                <td><?= number_format($item['amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>

            <?php 
            $blank = max(0, 20 - count($items));
            for ($i=0;$i<$blank;$i++): ?>
            <tr>
                <td>&nbsp;</td><td></td><td class="desc" id="descColumn"></td><td></td><td></td><td></td>
            </tr>
            <?php endfor; ?>

            <!-- SIGNATORIES -->
        </table>
    </div>

<script>
    // Select the table
    let table = document.querySelector('.table-container table');

    let signRow = document.createElement("tr");
    signRow.style.border = "2px solid black";
    signRow.innerHTML = `
        <!-- LEFT SIGNATORY -->
        <td colspan="3" class="signatory-cell" style="padding:10px; border-top:2px solid #000; text-align:center; border-right:2px solid #000; box-sizing:border-box;">
            <div style="font-weight:bold; margin-bottom:20px;">Received by:</div>
            <div style="border-bottom:1px solid #000; width:80%; margin:0 auto; height:12px;"><?= htmlspecialchars($par['received_by'] ?? '') ?></div>
            <div style="font-size:11px; margin-top:4px;">Signature over Printed Name of End User</div>

            <div style="border-bottom:1px solid #000; width:80%; margin:15px auto 0 auto; height:12px;"><?= htmlspecialchars($par['received_by_designation'] ?? '') ?></div>
            <div style="font-size:11px; margin-top:4px;">Position/Office</div>

            <div style="border-bottom:1px solid #000; width:40%; margin:15px auto 0 auto; height:12px;"><?= $par['received_by_date'] ? date('m/d/Y', strtotime($par['received_by_date'])) : '' ?></div>
            <div style="font-size:11px; margin-top:4px;">Date</div>
        </td>

        <!-- RIGHT SIGNATORY -->
        <td colspan="3" class="signatory-cell" style="padding:10px; border-top:2px solid #000; text-align:center; box-sizing:border-box;">
            <div style="font-weight:bold; margin-bottom:20px;">Issued by:</div>
            <div style="border-bottom:1px solid #000; width:80%; margin:0 auto; height:12px;"><?= htmlspecialchars($par['issued_by'] ?? '') ?></div>
            <div style="font-size:11px; margin-top:4px;">Signature over Printed Name of Supply and/or Property Custodian</div>

            <div style="border-bottom:1px solid #000; width:80%; margin:15px auto 0 auto; height:12px;"><?= htmlspecialchars($par['issued_by_designation'] ?? '') ?></div>
            <div style="font-size:11px; margin-top:4px;">Position/Office</div>

            <div style="border-bottom:1px solid #000; width:40%; margin:15px auto 0 auto; height:12px;"><?= $par['issued_by_date'] ? date('m/d/Y', strtotime($par['issued_by_date'])) : '' ?></div>
            <div style="font-size:11px; margin-top:4px;">Date</div>
        </td>
    `;
    table.appendChild(signRow);
</script>

</div>

</body>
</html>