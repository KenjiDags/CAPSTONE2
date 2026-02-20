<?php
require 'config.php';
require 'functions.php';

// Get PAR ID from URL
$par_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($par_id <= 0) {
    die("Invalid PAR ID");
}

// Fetch PAR header data
$par_query = "SELECT * FROM ppe_par WHERE par_id = ?";
$stmt = $conn->prepare($par_query);
$stmt->bind_param("i", $par_id);
$stmt->execute();
$par_result = $stmt->get_result();

if ($par_result->num_rows === 0) {
    die("PAR not found");
}

$par = $par_result->fetch_assoc();
$stmt->close();

// Fetch PAR items with property details
$items_query = "SELECT p.* FROM ppe_par_items pi 
                JOIN ppe_property p ON pi.ppe_id = p.id 
                WHERE pi.par_id = ? 
                ORDER BY p.par_no";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $par_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

// Calculate total amount
$total_amount = 0;
foreach ($items as $item) {
    $total_amount += floatval($item['amount']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Property Acknowledgment Receipt - <?php echo htmlspecialchars($par['par_no']); ?></title>
<style>
    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
        margin: 25px;
    }
    @media print {
        .no-print { display: none; }
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

    /* Header labels */
    .header-row {
        margin-bottom: 15px;
        font-size: 13px;
    }
    .header-row div {
        margin-bottom: 5px;
    }
    .underline {
        display: inline-block;
        border-bottom: 1px solid #000;
        width: 250px;
        height: 14px;
        vertical-align: bottom;
        margin-left: 5px;
    }

    /* Table */
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
    th {
        font-weight: bold;
    }
    td.desc {
        text-align: left;
    }

    /* Signature panel */
    .signature-container {
        display: flex;
        width: 100%;
        margin-top: 15px;
        border-top: none;
    }
    .sig-box {
        width: 50%;
        border: 1px solid #000;
        padding: 15px;
        box-sizing: border-box;
    }
    .sig-title {
        margin-top: 30px;
        text-align: center;
    }
    .sig-line {
        border-bottom: 1px solid #000;
        width: 70%;
        margin: 40px auto 5px auto;
        height: 12px;
    }
    .sig-label {
        text-align: center;
        font-size: 11px;
    }
    
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
    .print-button:hover { 
        background: #005a87;
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
    .instruction-box {
        background: #fffacd;
        border: 1px solid #ddd;
        padding: 10px;
        margin-bottom: 12px;
        border-radius: 4px;
        font-size: 12px;
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
    <a href="PPE_PAR.php" class="back-link">← Back to PAR List</a>
    <hr style="margin:14px 0;">
</div>

<div class="page-wrapper">
    <div class="appendix">Appendix 71</div>

    <div class="title">PROPERTY ACKNOWLEDGMENT RECEIPT</div>

    <!-- Header -->
    <div class="header-row" style="font-size:11px;">

        <div style="margin-bottom:8px;">
            <strong>Entity Name:</strong> 
            <span class="underline" style="width:300px;"><?php echo htmlspecialchars($par['entity_name']); ?></span>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <strong>Fund Cluster:</strong>
                <span class="underline" style="width:300px;"><?php echo htmlspecialchars($par['fund_cluster']); ?></span>
            </div>

            <div>
                <strong>PAR No.:</strong>
                <span class="underline" style="width:150px;"><?php echo htmlspecialchars($par['par_no']); ?></span>
            </div>
        </div>

    </div>

    <!-- Table -->
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <th style="width:7%;">Quantity</th>
            <th style="width:7%;">Unit</th>
            <th style="width:26.1%;">Description</th> <!-- Adjusted to align perfectly -->
            <th style="width:16%;">PAR No.</th>
            <th style="width:12%;">Date Acquired</th>
            <th style="width:12%;">Amount</th>
        </tr>

        <!-- Actual items from database -->
        <?php foreach ($items as $item): ?>
        <tr>
            <td>1</td>
            <td><?php echo htmlspecialchars($item['unit'] ?? 'unit'); ?></td>
            <td class="desc"><?php echo htmlspecialchars($item['item_name'] . ' - ' . $item['item_description']); ?></td>
            <td><?php echo htmlspecialchars($item['par_no']); ?></td>
            <td><?php echo $par['received_by_date'] ? date('m/d/Y', strtotime($par['received_by_date'])) : ''; ?></td>
            <td><?php echo number_format($item['amount'], 2); ?></td>
        </tr>
        <?php endforeach; ?>

        <!-- Blank rows to fill the rest of the page -->
        <?php 
        $items_count = count($items);
        $blank_rows = max(0, 20 - $items_count);
        for ($i = 0; $i < $blank_rows; $i++): 
        ?>
        <tr>
            <td>&nbsp;</td>
            <td></td>
            <td class="desc"></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <?php endfor; ?>

        <!-- SIGNATORIES -->
        <tr>
            <td colspan="6" style="padding:0;">

                <table style="width:100%; border-collapse:collapse; border:none; font-size:11px;">
                    <tr>
                        <!-- LEFT SIDE (50%) -->
                        <td style="width:50%; border-right:1px solid black; padding:20px;">
                            <div style="text-align:center; font-weight:bold; margin-bottom:20px;">
                                Received by:
                            </div>

                            <div style="border-bottom:1px solid #000; width:80%; height:12px; margin:0 auto;"><?php echo htmlspecialchars($par['received_by'] ?? ''); ?></div>
                            <div style="text-align:center; margin-top:4px;">Signature over Printed Name of End User</div>

                            <div style="border-bottom:1px solid #000; width:80%; height:12px; margin:25px auto 0 auto;"><?php echo htmlspecialchars($par['received_by_designation'] ?? ''); ?></div>
                            <div style="text-align:center; margin-top:4px;">Position/Office</div>

                            <div style="border-bottom:1px solid #000; width:40%; height:12px; margin:25px auto 0 auto;"><?php echo $par['received_by_date'] ? date('m/d/Y', strtotime($par['received_by_date'])) : ''; ?></div>
                            <div style="text-align:center; margin-top:4px;">Date</div>
                        </td>

                        <!-- RIGHT SIDE (50%) -->
                        <td style="width:50%; padding:20px;">
                            <div style="text-align:center; font-weight:bold; margin-bottom:20px;">
                                Issued by:
                            </div>

                            <div style="border-bottom:1px solid #000; width:80%; height:12px; margin:0 auto;"><?php echo htmlspecialchars($par['issued_by'] ?? ''); ?></div>
                            <div style="text-align:center; margin-top:4px;">Signature over Printed Name of Supply and/or Property Custodian</div>

                            <div style="border-bottom:1px solid #000; width:80%; height:12px; margin:25px auto 0 auto;"><?php echo htmlspecialchars($par['issued_by_designation'] ?? ''); ?></div>
                            <div style="text-align:center; margin-top:4px;">Position/Office</div>

                            <div style="border-bottom:1px solid #000; width:40%; height:12px; margin:25px auto 0 auto;"><?php echo $par['issued_by_date'] ? date('m/d/Y', strtotime($par['issued_by_date'])) : ''; ?></div>
                            <div style="text-align:center; margin-top:4px;">Date</div>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</div>

</body>
</html>