<?php
require 'auth.php';
require 'config.php';

$rrsp_id = isset($_GET['rrsp_id']) ? (int)$_GET['rrsp_id'] : 0;
$rrsp = null;
$items = [];

if ($rrsp_id) {
    $h = $conn->prepare("SELECT * FROM rrsp WHERE rrsp_id=? LIMIT 1");
    $h->bind_param('i', $rrsp_id);
    $h->execute();
    $rr = $h->get_result();
    $rrsp = $rr->fetch_assoc();
    $h->close();
    
    $i = $conn->prepare("SELECT * FROM rrsp_items WHERE rrsp_id=? ORDER BY rrsp_item_id ASC");
    $i->bind_param('i', $rrsp_id);
    $i->execute();
    $res = $i->get_result();
    while ($r = $res->fetch_assoc()) {
        $items[] = $r;
    }
    $i->close();
}

if (!$rrsp) {
    echo 'RRSP not found.';
    exit;
}

$returnedBy = htmlspecialchars(trim($rrsp['returned_by'] ?? ''));
$receivedBy = htmlspecialchars(trim($rrsp['received_by'] ?? ''));
$returnedDate = htmlspecialchars(trim($rrsp['returned_date'] ?? ''));
$receivedDate = htmlspecialchars(trim($rrsp['received_date'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RRSP No. <?php echo htmlspecialchars($rrsp['rrsp_no']); ?> - Export</title>
    <style>
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .print-container { page-break-inside: avoid; width: 100%; margin: 0; }
            .content-wrapper { border: none !important; padding: 0; }
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.2;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 20px;
        }

        .print-container {
            width: 100%;
            margin: 0;
            background: #fff;
            border: none;
            padding: 0 20px 20px 20px;
            box-sizing: border-box;
        }

        .content-wrapper {
            width: 900px;
            margin: 0 auto;
            border: 2px solid black;
            box-sizing: border-box;
        }

        .content-wrapper .Annex{
            text-align: right;
            font-style: italic;
            font-size: 11px;
            padding-top: 4px;
            padding-right: 10px;
        }

        .agency-header {
            position: relative;
            text-align: center;
            padding-top: 12px;
            padding-bottom: 12px;
        }

        .agency-header img {
            position: absolute;
            left: 17px;
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
            font-size: 11px;
        }

        @media screen {
            .print-container { max-width: 100%; }
            .content-wrapper { max-width: 90%; }
        }

        .form-table {
            width: 100%;
            border-collapse: collapse;
        }

        .form-table th,
        .form-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
            font-size: 11px;
            vertical-align: middle;
        }

        .title-row th {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.5px;
            padding: 8px 6px;
        }

        .entity-row {
            padding: 6px 8px;
            font-size: 11px;
        }

        .entity-row strong,
        .label-cell strong {
            font-weight: bold;
        }

        .label-cell {
            padding: 6px 8px;
            font-size: 11px;
            width: 12%;
        }

        .value-cell {
            padding: 6px 8px;
            font-size: 11px;
            width: 18%;
        }

        .subtitle-row th {
            font-weight: bold;
            font-size: 11px;
            text-align: center;
            padding: 6px 8px;
        }

        .col-header {
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            padding: 8px 6px;
        }

        .form-table tbody td {
            text-align: center;
            font-size: 9px;
            padding: 8px 6px;
            height: 5px;
            vertical-align: middle;
        }

        .text-left {
            text-align: left !important;
        }

        .signature-cell {
            border-right: none !important;
            padding: 18px 24px 22px;
            vertical-align: top;
            text-align: left;
            height: 130px;
        }

        .signature-cell.right {
            border: 1px solid #000 !important;
            border-left: 0 !important;
        }

        .sig-label {
            font-size: 11px;
            margin-bottom: 32px;
            text-align: left;
        }

        .sig-line {
            border-bottom: 1px solid #000;
            height: 10px;
            width: 70%;
            margin: 18px auto 6px;
            position: relative;
        }

        .sig-text {
            font-size: 10px;
            text-align: center;
            margin-bottom: 10px;
        }

        .sig-value {
            font-size: 10px;
            text-align: center;
            white-space: nowrap;
        }

        .sig-value.name {
            text-transform: uppercase;
            font-weight: bold;
        }

        .sig-line .sig-value {
            position: absolute;
            top: -5px;
            left: 50%;
            transform: translateX(-50%);
        }

        .sig-line.date-line {
            width: 45%;
            margin: 14px auto 6px;
            height: 10px;
        }

        .print-instructions {
            background: #fffacd;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .print-button {
            background: #007cba;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 10px;
        }

        .print-button:hover {
            background: #005a87;
        }

        .back-button {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }

        .back-button:hover {
            background: #545b62;
        }
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
        <a href="rrsp.php" class="back-button">← Back to RRSP List</a>
        <hr style="margin: 20px 0;">
    </div>

    <div class="content-wrapper">
        <div class="Annex">Annex A.6</div>

        <div class="agency-header">
            <img src="images/TESDA-Logo-export.png" alt="TESDA Logo">
            <div class="agency-text">
                <div>Republic of the Philippines</div>
                <div><strong>TECHNICAL EDUCATION &amp; SKILLS DEVELOPMENT AUTHORITY</strong></div>
                <div>Cordillera Administrative Region</div>
            </div>
        </div>

        <div class="print-container">
            <table class="form-table">
            <thead>
                <tr class="title-row">
                    <th colspan="5">RECEIPT OF RETURNED SEMI-EXPENDABLE PROPERTY</th>
                </tr>
                <tr>
                    <td rowspan="2" colspan="3" class="entity-row"><strong>Entity Name:&nbsp;</strong><?php echo htmlspecialchars($rrsp['entity_name']); ?></td>
                    <td colspan="2" class="label-cell"><strong>Date:</strong> <?php echo htmlspecialchars($rrsp['date_prepared']); ?></td>
                </tr>
                <tr class="divider-row">
                    <td colspan="2" class="label-cell line-cell"><strong>RRSP No.:</strong> <?php echo htmlspecialchars($rrsp['rrsp_no']); ?></td>
                </tr>
                <tr class="subtitle-row">
                    <th colspan="5">This is to acknowledge receipt of the returned Semi-expendable Property</th>
                </tr>
                <tr>
                    <th class="col-header" style="width: 38%;">Item Description</th>
                    <th class="col-header" style="width: 12%;">Quantity</th>
                    <th class="col-header" style="width: 15%;">ICS No.</th>
                    <th class="col-header" style="width: 15%;">End-user</th>
                    <th class="col-header" style="width: 20%;">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $row_count = 0;
                if (!empty($items)) {
                    foreach ($items as $item) {
                        echo '<tr>';
                        echo '<td class="text-left">' . htmlspecialchars($item['item_description'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($item['quantity'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($item['ics_no'] ?? '') . '</td>';
                        echo '<td>' . ($returnedBy !== '' ? $returnedBy : htmlspecialchars($item['end_user'] ?? '')) . '</td>';
                        echo '<td class="text-left">' . htmlspecialchars($item['item_remarks'] ?? '') . '</td>';
                        echo '</tr>';
                        $row_count++;
                    }
                }

                for ($i = $row_count; $i < 9; $i++) {
                    echo '<tr>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '</tr>';
                }
                ?>

                <tr>
                    <td colspan="2" class="signature-cell">
                        <div class="sig-label">Returned by:</div>
                        <div class="sig-line">
                            <?php if ($returnedBy !== ''): ?>
                                <span class="sig-value name"><?php echo $returnedBy; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="sig-text">End User</div>
                        <div class="sig-line date-line">
                            <?php if ($returnedDate !== ''): ?>
                                <span class="sig-value name"><?php echo $returnedDate; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="sig-text">Date</div>
                    </td>
                    <td colspan="3" class="signature-cell right">
                        <div class="sig-label">Received by:</div>
                        <div class="sig-line">
                            <?php if ($receivedBy !== ''): ?>
                                <span class="sig-value name"><?php echo $receivedBy; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="sig-text">Head, Property and/or Supply Division/Unit</div>
                        <div class="sig-line date-line">
                            <?php if ($receivedDate !== ''): ?>
                                <span class="sig-value name"><?php echo $receivedDate; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="sig-text">Date</div>
                    </td>
                </tr>
            </tbody>
            </table>
        </div>
    </div>

</body>
</html>
<?php $conn->close(); ?>