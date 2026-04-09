<?php
require 'auth.php';
require 'config.php';

$iirusp_id = isset($_GET['iirusp_id']) ? (int) $_GET['iirusp_id'] : 0;
if ($iirusp_id <= 0) {
    die('Invalid IIRUSP ID');
}

$stmt = $conn->prepare('SELECT * FROM iirusp WHERE iirusp_id = ? LIMIT 1');
$stmt->bind_param('i', $iirusp_id);
$stmt->execute();
$iirusp = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$iirusp) {
    die('IIRUSP record not found');
}

$items_stmt = $conn->prepare('SELECT * FROM iirusp_items WHERE iirusp_id = ? ORDER BY iirusp_item_id ASC');
$items_stmt->bind_param('i', $iirusp_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

$dataRows = count($items);

function h($value)
{
    return htmlspecialchars((string) ($value ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>IIRUSP Export</title>
<style>
    @page {
        size: landscape;
        margin: 10mm;
    }

    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        font-size: 10px;
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
    }
    .header-section {
        text-align: center;
        font-weight: bold;
        margin: 20px 0 20px 0;
        padding: 20px 0 30px 0;
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
        font-size: 15px;
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
        position: relative;
    }

    #inventoryTable td:nth-child(10),
    #inventoryTable th:nth-child(10) {
        border-right: 2px solid #000;
    }

    #inventoryTable td,
    #inventoryTable th {
        overflow: hidden;
        word-wrap: break-word;
    }

    .report-title-row th {
        text-align: center;
        border-top: none;
        border-left: none;
        border-right: none;
    }

    .report-title-text {
        font-weight: bold;
        font-size: 12px;
    }

    .as-at-line {
        border-bottom: 1px solid #000;
        display: inline-block;
        min-width: 200px;
        text-align: center;
    }

    .meta-row th {
        border-top: none;
        border-left: none;
        border-right: none;
        text-align: left;
    }

    .officer-row th {
        border: none;
        font-style: italic;
        text-align: center;
    }

    .officer-spacer {
        border: none !important;
    }

    .fixed-underline {
        display: inline-block;
        border-bottom: 1px solid #000;
        text-align: center;
    }

    .name-line { width: 210px; }
    .designation-line { width: 190px; }
    .station-line { width: 170px; }

    .sig-line {
        display: inline-block;
        border-bottom: 1px solid #000;
        text-align: center;
        padding-bottom: 2px;
    }

    .sig-line-lg { width: 220px; }
    .sig-line-md { width: 180px; }
    .sig-line-xl { width: 250px; }

    .sig-day-line { width: 40px; }
    .sig-month-line { width: 100px; }
    .sig-year-line { width: 50px; }

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

    @media print {
        .no-print { display: none !important; }
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
            padding: 0.5px 0;
        }
        .header-section {
            margin: 0;
            font-size: 8px;
        }
        p {
            margin: 0;
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
            top: 38%;
        }
        .agency-text {
            font-size: 10px;
            line-height: 1.1;
        }
        .as-at-line {
            min-width: 130px;
        }
        .name-line {
            width: 145px;
        }
        .designation-line {
            width: 130px;
        }
        .station-line {
            width: 120px;
        }
        .sig-line-lg {
            width: 140px;
        }
        .sig-line-md {
            width: 120px;
        }
        .sig-line-xl {
            width: 160px;
        }
        .sig-day-line {
            width: 26px;
        }
        .sig-month-line {
            width: 70px;
        }
        .sig-year-line {
            width: 35px;
        }
    }
</style>
</head>
<body>
<div class="no-print">
    <div class="instruction-box">
        <strong>Export Instructions:</strong>
        <div>1. Click the Print/Save button below.</div>
        <div>2. In the print dialog choose Save as PDF or your printer.</div>
        <div>3. Save or print the document.</div>
    </div>
    <button class="print-button" onclick="window.print()">Print / Save as PDF</button>
    <a href="iirusp.php" class="back-link">Back to List</a>
    <hr style="margin:14px 0;">
</div>

<div class="page-wraper">
    <div class="appendix" style="font-size: 10px;">appendix 74</div>

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
            <tr class="report-title-row">
                <th colspan="18"  style="padding: 10px 0 !important; border-bottom: none;">
                    <span class="report-title-text">INVENTORY AND INSPECTION REPORT OF UNSERVICEABLE SEMI-EXPENDABLE PROPERTY</span><br>
                    As at <span class="as-at-line"><?= h(date('M d, Y', strtotime($iirusp['as_at'] ?? date('Y-m-d')))) ?></span>
                </th>
            </tr>
            <tr class="meta-row">
                <th colspan="15" style="text-align: left !important; border-top: none !important; border-bottom: none;"><strong>Entity Name:</strong> <?= h($iirusp['entity_name'] ?? 'TESDA Regional Office') ?></th>
                <th colspan="3" style="border-bottom: none;"><strong>Fund Cluster:</strong> <?= h($iirusp['fund_cluster'] ?? '') ?></th>
            </tr>
            <tr class="officer-row">
                <th colspan="3">
                    <span class="fixed-underline name-line"><?= h($iirusp['accountable_officer_name'] ?? '') ?></span><br>
                    (Name of Accountable Officer)
                </th>
                <th colspan="1" class="officer-spacer"></th>
                <th colspan="3">
                    <span class="fixed-underline designation-line"><?= h($iirusp['accountable_officer_designation'] ?? '') ?></span><br>
                    (Designation)
                </th>
                <th colspan="2" class="officer-spacer"></th>
                <th colspan="3">
                    <span class="fixed-underline station-line"><?= h($iirusp['accountable_officer_station'] ?? '') ?></span><br>
                    (Station)
                </th>
            </tr>
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

            <?php if (!empty($items)): ?>
                <?php foreach ($items as $item): ?>
                    <?php
                    $dateAcquired = $item['date_acquired'] ?? '';
                    $qty = (float)($item['quantity'] ?? 0);
                    $unitCost = (float)($item['unit_cost'] ?? 0);
                    $totalCost = (float)($item['total_cost'] ?? ($qty * $unitCost));
                    ?>
                    <tr>
                        <td><?= h($dateAcquired ? date('m/d/Y', strtotime($dateAcquired)) : '') ?></td>
                        <td style="text-align:left;"><?= h($item['particulars'] ?? '') ?></td>
                        <td><?= h($item['semi_expendable_property_no'] ?? '') ?></td>
                        <td><?= h($item['quantity'] ?? '') ?></td>
                        <td style="text-align:right;"><?= $unitCost > 0 ? 'P' . number_format($unitCost, 2) : '' ?></td>
                        <td style="text-align:right;"><?= $totalCost > 0 ? 'P' . number_format($totalCost, 2) : '' ?></td>
                        <td style="text-align:right;"></td>
                        <td style="text-align:right;"></td>
                        <td style="text-align:right;"></td>
                        <td id="remarksCol" style="text-align:left;"><?= h($item['remarks'] ?? '') ?></td>
                        <td><?= h($item['disposal_sale'] ?? '') ?></td>
                        <td><?= h($item['disposal_transfer'] ?? '') ?></td>
                        <td><?= h($item['disposal_destruction'] ?? '') ?></td>
                        <td><?= h($item['disposal_others'] ?? '') ?></td>
                        <td><?= h($item['disposal_total'] ?? '') ?></td>
                        <td style="text-align:right;"><?= isset($item['appraised_value']) && $item['appraised_value'] !== '' ? 'P' . number_format((float)$item['appraised_value'], 2) : '' ?></td>
                        <td><?= h($item['or_no'] ?? '') ?></td>
                        <td style="text-align:right;"><?= isset($item['sales_amount']) && $item['sales_amount'] !== '' ? 'P' . number_format((float)$item['sales_amount'], 2) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php
            $totalRows = 20;
            $emptyRows = max(0, $totalRows - $dataRows);
            for ($i = 0; $i < $emptyRows; $i++):
            ?>
                <tr>
                    <?php for ($c = 0; $c < 18; $c++): ?>
                        <?php if ($c === 9): ?>
                            <td id="remarksCol">&nbsp;</td>
                        <?php else: ?>
                            <td>&nbsp;</td>
                        <?php endif; ?>
                    <?php endfor; ?>
                </tr>
            <?php endfor; ?>
        </table>
    </div>
</div>

<script>
    const table = document.getElementById('inventoryTable');

    if (!document.getElementById('signatoriesRow')) {
        const row1 = document.createElement('tr');
        row1.id = 'signatoriesRow';
        row1.innerHTML = `
            <td colspan="10" style="text-align:left; border: none; border-top: 2px solid #000; border-right: 2px solid #000;">
                I HEREBY request inspection and disposition, pursuant to Section 79 of PD 1445, of the property enumerated above.
            </td>
            <td colspan="5" style="text-align:left; border: none; border-top: 2px solid #000;">
                I CERTIFY that I have inspected each and every article enumerated in this report, and that the disposition made thereof was, in my judgment, the best for the public interest.
            </td>
            <td colspan="3" style="text-align:left; border: none; border-top: 2px solid #000;">
                I CERTIFY that I have witnessed the disposition of the articles enumerated on this report this <span class="sig-line sig-day-line"><?= h(date('d', strtotime($iirusp['as_at'] ?? date('Y-m-d')))) ?></span> day of <span class="sig-line sig-month-line"><?= h(date('F', strtotime($iirusp['as_at'] ?? date('Y-m-d')))) ?></span>, <span class="sig-line sig-year-line"><?= h(date('Y', strtotime($iirusp['as_at'] ?? date('Y-m-d')))) ?></span>.
            </td>
        `;
        table.appendChild(row1);

        const row2 = document.createElement('tr');
        row2.innerHTML = `
            <td colspan="5" style="border: none; text-align: left;">Requested by:</td>
            <td colspan="5" style="border: none; border-right: 2px solid #000; text-align: left;">Approved by:</td>
            <td colspan="5" style="border: none;"></td>
            <td colspan="3" style="border: none;"></td>
        `;
        table.appendChild(row2);

        const row3 = document.createElement('tr');
        row3.innerHTML = `
            <td colspan="5" style="border: none; vertical-align:bottom;">
                <span class="sig-line sig-line-lg"><?= h($iirusp['requested_by'] ?? '') ?></span><br>
                (Signature over Printed Name of<br> Accountable Officer)
            </td>
            <td colspan="5" style="border: none; border-right: 2px solid #000; vertical-align:bottom;">
                <span class="sig-line sig-line-lg"><?= h($iirusp['approved_by'] ?? '') ?></span><br>
                (Signature over Printed Name of<br> Authorized Official)
            </td>
            <td colspan="4" style="border: none; vertical-align:bottom;">
                <span class="sig-line sig-line-md"><?= h($iirusp['inspection_officer'] ?? '') ?></span><br>
                (Signature over Printed Name of<br> Inspection Officer)
            </td>
            <td style="border:none;"></td>
            <td colspan="3" style="border: none; vertical-align:bottom;">
                <span class="sig-line sig-line-md"><?= h($iirusp['witness'] ?? '') ?></span><br>
                (Signature over Printed Name of<br> Witness)
            </td>
        `;
        table.appendChild(row3);

        const row4 = document.createElement('tr');
        row4.innerHTML = `
            <td colspan="5" style="border: none; border-bottom: 2px solid #000;">
                <span class="sig-line sig-line-xl"><?= h($iirusp['requested_by_designation'] ?? '') ?></span><br>
                Designation of Accountable Officer
            </td>
            <td colspan="5" style="border: none; border-right: 2px solid #000; border-bottom: 2px solid #000;">
                <span class="sig-line sig-line-xl"><?= h($iirusp['approved_by_designation'] ?? '') ?></span><br>
                Designation of Authorized Official
            </td>


        `;
        table.appendChild(row4);
    }
</script>

</body>
</html>