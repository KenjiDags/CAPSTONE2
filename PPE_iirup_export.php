<?php
require 'config.php';

// Get IIRUP ID from URL
$iirup_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($iirup_id <= 0) {
    die("Invalid IIRUP ID");
}

// Fetch IIRUP header
$stmt = $conn->prepare("SELECT * FROM ppe_iirup WHERE id = ?");
$stmt->bind_param("i", $iirup_id);
$stmt->execute();
$result = $stmt->get_result();
$iirup = $result->fetch_assoc();
$stmt->close();

if (!$iirup) {
    die("IIRUP not found");
}

// Fetch IIRUP items
$items_stmt = $conn->prepare("
    SELECT * FROM ppe_iirup_items
    WHERE ppe_iirup_id = ?
    ORDER BY id
");
$items_stmt->bind_param("i", $iirup_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

$dataRows = count($items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory and Inspection Report</title>
<style>
    @page {
        size: landscape;
        margin: 10mm;
    }

    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        font-size: 12px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        table-layout: fixed;
    }
    table, th, td {
        border: 1px solid #000;
    }
    th, td {
        padding: 5px;
        text-align: center;
        font-weight: 400;
    }
    .header-section {
        text-align: center;
        font-weight: bold;
        margin: 20px 0 20px 0;
        padding: 20px 0 30px 0;
    }
    .sign-section {
        margin-top: 25px;
        width: 100%;
    }
    .sign-block {
        width: 48%;
        display: inline-block;
        vertical-align: top;
        text-align: center;
        margin-top: 30px;
    }
    .signature-line {
        margin-top: 40px;
        border-top: 1px solid #000;
        width: 80%;
        margin-left: auto;
        margin-right: auto;
        padding-top: 5px;
    }
    .page-wraper {
        width: 100%;
        max-width: 1530px;
        margin: 0 auto;
        border: 2px solid #000;
        padding: 20px;
        position: relative;
    }
    .appendix {
        position: absolute;
        top: 10px; 
        right: 10px;
        font-size: 12px;
        font-style: italic;
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
    .table-container {
        position: relative;
    }

    #inventoryTable {
        border-collapse: collapse;
        width: 100%;
        border: 2px solid #000;
    }

    #inventoryTable th,
    #inventoryTable td {
        padding: 5px;
        text-align: center;
        font-weight: 400;
        position: relative; 
    }

    #inventoryTable td:nth-child(10),
    #inventoryTable th:nth-child(10) {
        border-right: 2px solid #000;
    }

    @media print {
        .no-print { display:none !important; }
        body {
            margin: 5mm;
            padding: 0;
            font-size: 7px;
        }
        .page-wraper {
            border: none !important;
            padding: 3px;
            max-width: none !important;
            width: 100% !important;
        }
        table {
            font-size: 6px;
            margin-top: 1px;
            width: 100%;
            table-layout: fixed;
        }
        th, td {
            padding: 0.5px 0px;
        }
        .header-section {
            margin: 0px 0;
            font-size: 8px;
        }
        p {
            margin: 0px 0;
            font-size: 6px;
        }
        .appendix {
            font-size: 7px;
        }
        .agency-header {
            padding-top: 8px;
            padding-bottom: 6px;
        }
        .agency-header img {
            width: 42px;
            height: 42px;
            top: 45%;
        }
        .agency-text {
            font-size: 8px;
            line-height: 1.1;
        }
        .sig-day { width: 20px !important; }
        .sig-month { width: 55px !important; }
        .sig-year { width: 28px !important; }
        .sig-name-lg { width: 110px !important; }
        .sig-name-md { width: 95px !important; }
        .sig-designation { width: 125px !important; }
        
    }

    .instruction-box {
        background: #fffacd;
        border: 1px solid #ddd;
        padding: 10px;
        margin-bottom: 12px;
        border-radius: 4px;
        font-size: 13px;
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

    .back-link {
        background: #6c757d;
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 13px;
        text-decoration: none;
        display: inline-block;
    }

    #inventoryTable td,
    #inventoryTable th {
        overflow: hidden;
        word-wrap: break-word;
    }

    .sig-line {
        display: inline-block;
        border-bottom: 1px solid #000;
        text-align: center;
    }

    .sig-day { width: 40px; }
    .sig-month { width: 100px; }
    .sig-year { width: 50px; }
    .sig-name-lg { width: 220px; }
    .sig-name-md { width: 180px; }
    .sig-designation { width: 250px; }

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
    <a href="PPE_iirup.php" class="back-link">← Back to List</a>
    <hr style="margin:14px 0;">
</div>

<div class="page-wraper">
    <div class="appendix">appendix 74</div>
    <div class="agency-header">
        <img src="images/TESDA-Logo-export.png" alt="TESDA Logo">
        <div class="agency-text">
            <div>Republic of the Philippines</div>
            <div><strong>TECHNICAL EDUCATION &amp; SKILLS DEVELOPMENT AUTHORITY</strong></div>
            <div>Cordillera Administrative Region</div>
        </div>
    </div>
    <div class="table-container">
        <table id="inventoryTable">
            <colgroup>
                <col style="width: 5%;">
                <col style="width: 8%;">
                <col style="width: 5%;">
                <col style="width: 4%;">
                <col style="width: 4%;">
                <col style="width: 4%;">
            <tr>
                <th colspan="18" style="text-align: center; border: none; padding-bottom: 10px; font-size: 12px; font-weight: bold;">
                    INVENTORY AND INSPECTION REPORT OF UNSERVICEABLE PROPERTY<br>
                    <span style="font-size: 9px; font-weight: normal;">As at <span style="border-bottom: 1px solid #000; display: inline-block; min-width: 200px; text-align: center;"><?= htmlspecialchars(date('M d, Y', strtotime($iirup['date_reported']))) ?></span></span>
                </th>
            </tr>
            <tr>
                <th colspan="9" style="text-align: left; border: none;"><strong>Entity Name:</strong> <?= htmlspecialchars($iirup['entity_name'] ?? 'TESDA Regional Office') ?></th>
                <th colspan="9" style="text-align: right; border: none;"><strong>Fund Cluster:</strong> <?= htmlspecialchars($iirup['fund_cluster'] ?? '101') ?></th>
            </tr>
            <tr>
                <th colspan="3" style="font-style: italic; border: none;">___________________________<br>(Name of Accountable Officer)</th>
                <th style="border: none;"></th>
                <th colspan="3" style="font-style: italic; border: none;">___________________________<br>(Designation)</th>
                <th colspan="2" style="border: none;"></th>
                <th colspan="3" style="font-style: italic; border: none;">___________________________<br>(Station)</th>
            </tr>
                <col style="width: 5.5%;">
                <col style="width: 5.5%;">
                <col style="width: 5.5%;">
                <col style="width: 5.5%;">
                <col style="width: 4%;">
                <col style="width: 4%;">
                <col style="width: 4%;">
                <col style="width: 4%;">
                <col style="width: 4%;">
                <col style="width: 5.5%;">
                <col style="width: 5.5%;">
                <col style="width: 5.5%;">
            </colgroup>
            <tr style="border: 2px solid #000;">
                <th colspan="10" style="border: 2px solid #000;">INVENTORY</th>
                <th colspan="8">INSPECTION and DISPOSAL</th>
            </tr>

            <tr>
                <th rowspan="2">Date Acquired</th>
                <th rowspan="2">Particulars / Articles</th>
                <th rowspan="2">Property No.</th>
                <th rowspan="2">Qty</th>
                <th rowspan="2">Unit Cost</th>
                <th rowspan="2">Total Cost</th>
                <th rowspan="2">Accum. Depreciation</th>
                <th rowspan="2">Accum. Impairment Losses</th>
                <th rowspan="2">Carrying Amount</th>
                <th rowspan="2" id="remarksCol">Remarks</th>

                <th colspan="5">Disposal</th>
                <th rowspan="2">Appraised Value</th>
                <th colspan="2">Record of Sales</th>
            </tr>

            <tr>
                <th>Sale</th>
                <th>Transfer</th>
                <th>Destruction</th>
                <th>Others<br>(Specify)</th>
                <th>Total</th>
                <th>OR No.</th>
                <th>Amount</th>
            </tr>
            <tr style="border: 2px solid #000;">
                <th>(1)</th>
                <th>(2)</th>
                <th>(3)</th>
                <th>(4)</th>
                <th>(5)</th>
                <th>(6)</th>
                <th>(7)</th>
                <th>(8)</th>
                <th>(9)</th>
                <th id="remarksCol">(10)</th>
                <th>(11)</th>
                <th>(12)</th>
                <th>(13)</th>
                <th>(14)</th>
                <th>(15)</th>
                <th>(16)</th>
                <th>(17)</th>
                <th>(18)</th>
            </tr>
        </tr>

        <!-- data rows populated from database -->
        <?php
        if (!empty($items)) {
            foreach ($items as $item) {
                $totalCost = ($item['quantity'] ?? 0) * (($item['amount'] ?? 0) / ($item['quantity'] ?? 1));
                echo "<tr>";
                echo "<td>" . htmlspecialchars(date('m/d/Y', strtotime($item['date_acquired'] ?? 'now'))) . "</td>";
                echo "<td style='text-align:left;'>" . htmlspecialchars($item['particulars'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($item['PPE_no'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($item['quantity'] ?? 0) . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format(($item['amount'] ?? 0) / ($item['quantity'] ?? 1), 2) . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format($totalCost, 2) . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format($item['depreciation'] ?? 0, 2) . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format($item['impairment_loss'] ?? 0, 2) . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format($item['carrying_amount'] ?? 0, 2) . "</td>";
                echo "<td id='remarksCol' style='text-align:left;'>" . htmlspecialchars($item['remarks'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($item['sale'] ?? 0) . "</td>";
                echo "<td>" . htmlspecialchars($item['transfer'] ?? 0) . "</td>";
                echo "<td>" . htmlspecialchars($item['destruction'] ?? 0) . "</td>";
                echo "<td>" . htmlspecialchars($item['other'] ?? 0) . "</td>";
                echo "<td>" . htmlspecialchars($item['total'] ?? 0) . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format($item['appraised_value'] ?? 0, 2) . "</td>";
                echo "<td>" . htmlspecialchars($item['or_no'] ?? '') . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format($item['amount'] ?? 0, 2) . "</td>";
                echo "</tr>";
            }
        }
        $totalRows = 20;
        $emptyRows = $totalRows - $dataRows;
        for ($i = 0; $i < $emptyRows; $i++) {
            echo "<tr>";
            for ($c = 0; $c < 18; $c++) {
                if ($c == 9) {
                    echo '<td id="remarksCol">&nbsp;</td>';
                } else {
                    echo '<td>&nbsp;</td>';
                }
            }
            echo "</tr>";
        }
        ?>
    </table>
</div>

<script>
    const table = document.getElementById("inventoryTable");

    // Prevent duplicate insertion
    if (!document.getElementById("signatoriesRow")) {

        // ROW 1: Statements
        let row1 = document.createElement("tr");
        row1.id = "signatoriesRow";
        row1.innerHTML = `
            <td colspan="10" style="text-align:left; border: none; border-top: 2px solid #000; border-right: 2px solid #000;">
                I HEREBY request inspection and disposition, pursuant to Section 79 of PD 1445, of the property enumerated above.
            </td>
            <td colspan="5" style="text-align:left; border: none; border-top: 2px solid #000;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I CERTIFY that I have inspected each<br> and every article enumerated in this<br> report, and that the disposition made<br> thereof was, in my judgment, the best for<br> the public interest.
            </td>
            <td colspan="3" style="text-align:left; border: none; border-top: 2px solid #000;">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;I CERTIFY that I have <br>witnessed the disposition of the<br> articles enumerated on this<br> report this <span class="sig-line sig-day"><?= htmlspecialchars(date('d', strtotime($iirup['date_reported']))) ?></span> day of<br> <span class="sig-line sig-month"><?= htmlspecialchars(date('F', strtotime($iirup['date_reported']))) ?></span>, <span class="sig-line sig-year"><?= htmlspecialchars(date('Y', strtotime($iirup['date_reported']))) ?></span>.
            </td>
        `;
        table.appendChild(row1);

        // ROW 2: Labels
        let row2 = document.createElement("tr");
        row2.innerHTML = `
            <td colspan="5" style="border: none; text-align: left;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Requested by:</td>
            <td colspan="5" style="border: none; border-right: 2px solid #000; text-align: left;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Approved by:</td>
            <td colspan="5" style="border: none;"></td>
            <td colspan="3" style="border: none;"></td>
        `;
        table.appendChild(row2);

        // ROW 3: Signature lines
        let row3 = document.createElement("tr");
        row3.innerHTML = `
            <td colspan="5" style="border: none; vertical-align:bottom;">
                <span class="sig-line sig-name-lg" style="padding-bottom: 2px;">
                    <?= htmlspecialchars($iirup['requested_by_name'] ?? '') ?>
                </span><br>
                (Signature over Printed Name<br>
                 of Accountable Officer)
            </td>
            <td colspan="5" style="border: none; border-right: 2px solid #000; vertical-align:bottom;">
                <span class="sig-line sig-name-lg" style="padding-bottom: 2px;">
                    <?= htmlspecialchars($iirup['approved_by_name'] ?? '') ?>
                </span><br>
                (Signature over Printed Name of<br>
                 Authorized Official)
            </td>
            <td colspan="4" style="border: none; vertical-align:bottom;">
                <span class="sig-line sig-name-md" style="padding-bottom: 2px;">
                    <?= htmlspecialchars($iirup['inspection_officer_name'] ?? '') ?>
                </span><br>
                (Signature over Printed Name of<br>
                Inspection Officer)
            </td>
            <td style="border:none;">
            </td>
            <td colspan="3" style="border: none; vertical-align:bottom;">
                <span class="sig-line sig-name-md" style="padding-bottom: 2px;">
                    <?= htmlspecialchars($iirup['witness_name'] ?? '') ?>
                </span><br>
                (Signature over Printed Name of<br>
                 Witness)
            </td>
        `;
        table.appendChild(row3);

        // ROW 4: Designations
        let row4 = document.createElement("tr");
        row4.innerHTML = `
            <td colspan="5" style="border: none; border-bottom: 2px solid #000;">
                <span class="sig-line sig-designation" style="padding-bottom: 2px;">
                    <?= htmlspecialchars($iirup['requested_by_designation'] ?? '') ?>
                </span><br>
                Designation of Accountable Officer
            </td>
            <td colspan="5" style="border: none; border-right: 2px solid #000; border-bottom: 2px solid #000;">
                <span class="sig-line sig-designation" style="padding-bottom: 2px;">
                    <?= htmlspecialchars($iirup['approved_by_designation'] ?? '') ?>
                </span><br>
                Designation of Authorized Official
            </td>
            <td colspan="4" style="border:none; border-bottom: 2px solid #000;"></td>
            <td colspan="4" style="border:none; border-bottom: 2px solid #000;"></td>
        `;
        table.appendChild(row4);
    }
</script>


</body>
</html>