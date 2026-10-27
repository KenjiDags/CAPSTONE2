<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
require_once __DIR__ . '/rsmi_helpers.php';
rsmiEnsureTable($conn);
$id = filter_var($_GET['rsmi_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    header('Location: rsmi.php');
    exit;
}
$report = rsmiLoad($conn, $id);
if (!$report) {
    http_response_code(404);
    exit('RSMI document not found.');
}
$rows = $report['rows'];
$recap = rsmiRecap($rows);
$entity_info = $rows[0] ?? [];
$custodian_name = htmlspecialchars($report['custodian_name'], ENT_QUOTES, 'UTF-8');
$accounting_staff = htmlspecialchars($report['accounting_staff'], ENT_QUOTES, 'UTF-8');
$report_date_display = date('F d, Y', strtotime($report['report_date']));
?>

<!DOCTYPE html>
<html data-export-styles lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSMI Export - Report on Stock of Materials and Supplies Issued</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Century Gothic', Century Gothic, sans-serif;
            font-size: 12px;
            line-height: 1.3;
            background: #f5f5f5;
            padding: 20px;
        }

        .export-instructions {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .export-instructions h3 {
            color: #856404;
            margin-bottom: 10px;
        }

        .export-instructions ol {
            margin-left: 20px;
            color: #856404;
        }

        .export-instructions .note {
            margin-top: 10px;
            font-weight: bold;
            color: #856404;
        }

        .button-container {
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-right: 10px;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.8;
        }

        .form-container {
            background: white;
            border: 2px solid black;
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            padding: 8px 12px 16px;
        }

        /* Appendix 64 styling */
        .appendix-label {
            position: absolute;
            top: 2px;
            right: 15px;
            font-size: 12px;
            font-style: italic;
            color: black;
            z-index: 10;
            padding: 2px 5px;
        }

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
        }

        .header-section {
            display: flex;
            border-bottom: 2px solid black;
        }

        .header-left, .header-right {
            flex: 1;
            padding: 8px;
        }

        .header-left {
            border-right: 1px solid black;
        }

        .header-field {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }

        .header-field strong {
            min-width: 100px;
            font-weight: bold;
        }

        .header-value {
            border-bottom: 1px solid black;
            flex: 1;
            padding: 2px 5px;
            min-height: 18px;
        }

        .instructions {
            text-align: center;
            padding: 3px;
            font-size: 10px;
            font-style: italic;
            border-bottom: 1px solid black;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }

        .main-table caption {
            caption-side: top;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            background-color: #f8f9fa;
            padding: 8px;
            height: 60px;
            border: none;
        }

        .main-table .header-cell {
            border: none;
            padding: 0px 8px;
            vertical-align: top;
            font-size: 12px;
            text-align: left;
        }

        .main-table .title-cell {
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            padding: 8px;
            height: 60px;
            border: 2px solid black;
            border-bottom: none;
        }

        .main-table .data-underline {
            display: inline-block;
            width: 220px;
            padding: 2px 5px 0;
            border-bottom: 1px solid black;
            vertical-align: bottom;
        }

        .main-table th,
        .main-table td {
            padding: 4px;
            text-align: center;
            vertical-align: middle;
            font-size: 10px;
        }

        .main-table th {
            font-weight: bold;
        }

        .main-table td {
            min-height: 20px;
        }

        .main-table .text-left {
            text-align: left;
        }

        .main-table .text-right {
            text-align: right;
        }

        .signature-section {
            display: flex;
            border-top: 2px solid black;
            min-height: 100px;
        }

        .signature-left,
        .signature-right {
            flex: 1;
            padding: 10px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 200px;
        }

        .signature-left {
            border-right: 1px solid black;
        }

        .signature-line {
            border-bottom: 1px solid black;
            margin-top: 3px;
            margin-bottom: 5px;
        }

        .signature-line-custodian {
            width: 65%;
            margin-left: auto;
            margin-right: auto;
        }

        .signatory-name {
            font-weight: bold;
            margin-bottom: 2px;
            font-size: 11px;
            line-height: 1.2;
        }

        .main-table .signature-date .signatory-name {
            white-space: nowrap;
            font-size: 10px;
        }

        .signature-text {
            text-align: center;
            font-size: 10px;
        }

        .posted-by {
            text-align: right;
            font-size: 10px;
            margin-bottom: 10px;
        }

        .main-table td.full-border,
        .main-table th.full-border {
            border: 2px solid black;
        }

        .main-table .ris-header-row > th:first-child,
        .main-table tbody > tr > td:first-child,
        .main-table tbody > tr > th:first-child {
            border-left: 2px solid black;
        }

        .main-table .ris-header-row > th:last-child,
        .main-table tbody > tr > td:last-child,
        .main-table tbody > tr > th:last-child {
            border-right: 2px solid black;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .export-instructions,
            .button-container {
                display: none;
            }

            .form-container {
                max-width: none;
                margin: 0;
                page-break-inside: auto;
                break-inside: auto;
                border: none !important;
            }

            .appendix-label {
                position: absolute;
                top: 8px;
                right: 15px;
                font-size: 12px;
                font-style: italic;
                color: black;
                z-index: 10;
                background: white;
                padding: 2px 5px;
            }

            @page {
                margin: 0.5in;
                size: A4;
            }
        }
        .main-table .repeating-agency-cell {
            padding: 0;
            border: none;
            font-size: 12px;
        }

        @media print {
            html, body, .form-container, .main-table {
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
            }
            .main-table { break-inside: auto; page-break-inside: auto; }
            .main-table thead { display: table-header-group; }
            .main-table tbody { display: table-row-group; }
            .main-table tfoot { display: table-footer-group; }
            .main-table tr { break-inside: avoid; page-break-inside: avoid; }
            .main-table thead { break-inside: avoid; page-break-inside: avoid; }
        }
        .main-table .page-bottom-border td {
            padding: 0;
            height: 0;
            min-height: 0;
            line-height: 0;
            font-size: 0;
            border-top: 2px solid black;
        }
    </style>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
<script src="js/export-preview.js?v=<?= time() ?>" defer data-export-preview data-preview-title="Materials and Supplies Issued Preview" data-preview-code="RSMI" data-preview-back="office_supplies/rsmi.php" data-preview-width="1100"></script>
</head>
<body>
    <!-- Export Instructions -->
    <div class="export-instructions">
        <h3>Export Instructions</h3>
        <p><strong>To save as PDF:</strong></p>
        <ol>
            <li>Click the "Print/Save as PDF" button below</li>
            <li>In the print dialog, select "Save as PDF" or "Microsoft Print to PDF"</li>
            <li>Choose your destination and click "Save"</li>
        </ol>
        <p class="note">For best results: Use Chrome or Edge browser for optimal PDF formatting.</p>
    </div>

    <!-- Buttons -->
    <div class="button-container">
        <button class="btn btn-primary" onclick="printForm()">📄 Print/Save as PDF</button>
        <a href="office_supplies/rsmi.php" class="btn btn-secondary">← Back to RSMI</a>
    </div>

    <!-- RSMI Form -->
    <div class="form-container">
        <table class="main-table" style="width: 100%;">
            <thead>
                <tr>
                    <td colspan="8" class="repeating-agency-cell">
                        <div class="agency-header">
                            <div class="appendix-label">Appendix 64</div>
                            <img src="images/TESDA-Logo-export.png" alt="TESDA Logo">
                            <div class="agency-text">
                                <div>Republic of the Philippines</div>
                                <div><strong>TECHNICAL EDUCATION & SKILLS DEVELOPMENT AUTHORITY</strong></div>
                                <div>Cordillera Administrative Region</div>
                            </div>
                        </div>
                    </td>
                </tr>
            <tr>
                <td class="title-cell" colspan="8">REPORT OF SUPPLIES AND MATERIALS ISSUED (RSMI)</td>
            </tr>
            <tr>
                <td class="header-cell" colspan="4" style="padding-bottom: 3px !important; border-left: 2px solid black;">
                    <strong>Entity Name:</strong> <span class="data-underline"> <?= htmlspecialchars($entity_info['entity_name'] ?? '') ?> </span><br>
                    <strong>Fund Cluster:</strong> <span class="data-underline"> <?= htmlspecialchars($entity_info['fund_cluster'] ?? '') ?> </span><br>
                </td>
                <td class="header-cell" colspan="4" style="padding-bottom: 3px !important; border-right: 2px solid black;">
                    <strong>Serial No.:</strong> <span class="data-underline"> <?= htmlspecialchars($report['serial_no'], ENT_QUOTES, 'UTF-8') ?> </span><br>
                    <strong>Date:</strong> <span class="data-underline"> <?= htmlspecialchars($report_date_display, ENT_QUOTES, 'UTF-8') ?> </span><br>
                </td>
            </tr>

            <tr>
                <td colspan="6" class="instructions full-border">To be filled up by the Supply and/or Property Division/Unit</td>
                <td colspan="2" class="instructions full-border">To be filled up by the Accounting Division/Unit</td>
            </tr>

            <tr class="ris-header-row">
                <th style="width: 10%;" class="instructions full-border">RIS No.</th>
                <th style="width: 10%;" class="instructions full-border">Responsibility Center Code</th>
                <th style="width: 10%;" class="instructions full-border">Stock No.</th>
                <th style="width: 20%;" class="instructions full-border">Item</th>
                <th style="width: 10%;" class="instructions full-border">Unit</th>
                <th style="width: 10%;" class="instructions full-border">Quantity Issued</th>
                <th style="width: 15%;" class="instructions full-border">Unit Cost</th>
                <th style="width: 15%;" class="instructions full-border">Amount</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $row_count = 0;
            if ($rows) {
                foreach ($rows as $row) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['ris_no']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['responsibility_center_code']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['stock_number']) . '</td>';
                    echo '<td class="text-left">' . htmlspecialchars($row['item_name']) . '-' . htmlspecialchars($row['description']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['unit']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['issued_quantity']) . '</td>';
                    echo '<td class="text-right">₱ ' . number_format($row['unit_cost'], 2) . '</td>';
                    echo '<td class="text-right">₱ ' . number_format($row['amount'], 2) . '</td>';
                    echo '</tr>';
                    $row_count++;
                }
            }
            // Fill remaining rows with empty cells (up to 15 rows total)
            for ($i = $row_count; $i < 15; $i++) {
                echo '<tr>';
                echo '<td>&nbsp;</td>';
                echo '<td>&nbsp;</td>';
                echo '<td>&nbsp;</td>';
                echo '<td>&nbsp;</td>';
                echo '<td>&nbsp;</td>';
                echo '<td>&nbsp;</td>';
                echo '<td>&nbsp;</td>';
                echo '<td>&nbsp;</td>';
                echo '</tr>';
            }
            ?>

            <tr>
                <th>&nbsp;</th>
                <th colspan="2" style="border: 2px solid black;">Recapitulation</th>
                <th>&nbsp;</th>
                <th>&nbsp;</th>
                <th colspan="3" style="border: 2px solid black;">Recapitulation</th>
            </tr>

            <tr>
                <th>&nbsp;</th>
                <th style="border: 2px solid black;">Stock No.</th>
                <th style="border: 2px solid black;">Quantity</th>
                <th>&nbsp;</th>
                <th>&nbsp;</th>
                <th style="border: 2px solid black;">Unit Cost</th>
                <th style="border: 2px solid black;">Total Cost</th>
                <th style="border: 2px solid black;">UACS Object Code</th>
            </tr>

                    <?php
                        $recap_count = 0;
                        if ($recap) {

                            foreach ($recap as $recap_row) {
                                echo '<tr>';
                                echo '<td>&nbsp;</td>';
                                echo '<td style="border: none; border-right: 2px solid black; border-left: 2px solid black;">' . htmlspecialchars($recap_row['stock_number']) . '</td>';
                                echo '<td style="border: none; border-right: 2px solid black; border-left: 2px solid black;">' . htmlspecialchars($recap_row['total_issued']) . '</td>';
                                echo '<td>&nbsp;</td>';
                                echo '<td>&nbsp;</td>';
                                echo '<td class="text-right" style="border: none; border-right: 2px solid black; border-left: 2px solid black;">₱ ' . number_format($recap_row['avg_unit_cost'], 2) . '</td>';
                                echo '<td class="text-right" style="border: none; border-right: 2px solid black; border-left: 2px solid black;">₱ ' . number_format($recap_row['total_cost'], 2) . '</td>';
                                echo '<td style="border: none; border-right: 2px solid black; border-left: 2px solid black;">' . rsmiH($recap_row['uacs_object_code'] ?? '') . '</td>'; // UACS Object Code
                                echo '</tr>';
                                $recap_count++;
                            }
                        }

                        // Fill remaining recap rows (up to 10 rows)
                        for ($i = $recap_count; $i < 10; $i++) {
                            echo '<tr>';
                            echo '<td>&nbsp;</td>';
                            echo '<td style="border: none; border-right: 2px solid black; border-left: 2px solid black;">&nbsp;</td>';
                            echo '<td style="border: none; border-right: 2px solid black; border-left: 2px solid black;">&nbsp;</td>';
                            echo '<td>&nbsp;</td>';
                            echo '<td>&nbsp;</td>';
                            echo '<td style="border: none; border-right: 2px solid black; border-left: 2px solid black;">&nbsp;</td>';
                            echo '<td style="border: none; border-right: 2px solid black; border-left: 2px solid black;">&nbsp;</td>';
                            echo '<td style="border: none; border-right: 2px solid black; border-left: 2px solid black;">&nbsp;</td>';
                            echo '</tr>';
                        }
                    ?>

                <tr>
                    <td colspan="5" style="text-align: left; border:2px solid black; border-bottom: none">&nbsp;</td>
                    <td colspan="2" style="text-align: left; border: none; border-top: 2px solid black;">Posted by:</td>
                    <td style=" border: none; border-top: 2px solid black; border-right: 2px solid black">&nbsp;</td>
                </tr>

                <tr>
                    <td style="border-left: 2px solid black;">&nbsp;</td>
                    <td colspan="4" style="border: none; text-align: left; border-right:2px solid black;">I hereby certify to the correctness of the above information.</td>
                    <td colspan="2" style="border: none;">&nbsp;</td>
                    <td style="border: 2px solid black; border: none; border-right: 2px solid black">&nbsp;</td>
                </tr>

                <tr>
                    <td colspan="5" style="border: 2px solid black; border-top:none; border-bottom: none ">&nbsp;</td>
                    <td colspan="2" style="border: none;">&nbsp;</td>
                    <td style="border-right: 2px solid black; border-top: none;">&nbsp;</td>
                </tr>

                <tr>
                    <td colspan="5" style="border: 2px solid black; border-top: none;"><?php if ($custodian_name): ?>
                            <div style="font-weight: bold; margin-bottom: 2px; font-size: 11px;"><?= $custodian_name ?></div>
                        <?php endif; ?><div class="signature-line signature-line-custodian"></div>Signature over Printed Name of Supply<br>and/or Property Custodian</td>
                    <td colspan="2" style=" border: none; border-bottom: 2px solid black; vertical-align: top;">
                        <?php if ($accounting_staff): ?>
                            <div class="signatory-name"><?= $accounting_staff ?></div>
                        <?php endif; ?><div class="signature-line"></div>Signature over Printed Name of<br> Designated Accounting Staff</td>
                    <td class="signature-date" style="border: 2px solid black; border-top: none; border-left: none; vertical-align: top;"><div class="signatory-name"><?= htmlspecialchars($report_date_display) ?></div><div class="signature-line"></div>Date</td>
                </tr>

            </tbody>
            <tfoot>
                <tr class="page-bottom-border"><td colspan="8"></td></tr>
            </tfoot>
        </table>
    </div>

    <script>
        function printForm() {
            // Hide instructions and buttons before printing
            const instructions = document.querySelector('.export-instructions');
            const buttons = document.querySelector('.button-container');

            if (instructions) instructions.style.display = 'none';
            if (buttons) buttons.style.display = 'none';

            // Print the page (opens in same tab)
            window.print();

            // Restore visibility after printing
            setTimeout(() => {
                if (instructions) instructions.style.display = 'block';
                if (buttons) buttons.style.display = 'block';
            }, 1000);
        }


    </script>
</body>
</html>
