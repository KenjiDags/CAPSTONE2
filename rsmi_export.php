<?php
require 'auth.php';
require 'config.php';

// Get signature names from GET parameters
$custodian_name = isset($_GET['custodian_name']) ? htmlspecialchars(trim($_GET['custodian_name'])) : '';
$accounting_staff = isset($_GET['accounting_staff']) ? htmlspecialchars(trim($_GET['accounting_staff'])) : '';

// Get report date from GET parameter (fallback to current date)
$report_date_raw = isset($_GET['report_date']) ? trim($_GET['report_date']) : date('Y-m-d');
$report_date_obj = DateTime::createFromFormat('Y-m-d', $report_date_raw);
if (!$report_date_obj || $report_date_obj->format('Y-m-d') !== $report_date_raw) {
    $report_date_obj = new DateTime();
}
$report_date_display = $report_date_obj->format('F d, Y');

// Get all RSMI data
$rsmi_query = "
    SELECT ris.ris_no, ri.stock_number, i.item_name, i.description, i.unit, ri.issued_quantity, 
        ri.unit_cost_at_issue AS unit_cost,
        (ri.issued_quantity * ri.unit_cost_at_issue) AS amount,
        ris.date_requested, ris.entity_name, ris.fund_cluster, ris.division, 
        ris.responsibility_center_code, ris.office
    FROM ris_items ri
    JOIN ris ON ri.ris_id = ris.ris_id
    JOIN items i ON ri.stock_number = i.stock_number
    ORDER BY ris.date_requested DESC
";

$rsmi_result = $conn->query($rsmi_query);

// Get recapitulation data
$recap_query = "
    SELECT ri.stock_number, SUM(ri.issued_quantity) AS total_issued,
           AVG(ri.unit_cost_at_issue) AS avg_unit_cost,
           SUM(ri.issued_quantity * ri.unit_cost_at_issue) AS total_cost
    FROM ris_items ri
    GROUP BY ri.stock_number
";

$recap_result = $conn->query($recap_query);

// Get entity information (assuming from the first record)
$entity_info = null;
if ($rsmi_result && $rsmi_result->num_rows > 0) {
    $rsmi_result->data_seek(0);
    $entity_info = $rsmi_result->fetch_assoc();
    $rsmi_result->data_seek(0); // Reset pointer
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
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
            font-family: Arial, sans-serif;
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
        .main-table .ris-header-row ~ tr > td:first-child,
        .main-table .ris-header-row ~ tr > th:first-child {
            border-left: 2px solid black;
        }

        .main-table .ris-header-row > th:last-child,
        .main-table .ris-header-row ~ tr > td:last-child,
        .main-table .ris-header-row ~ tr > th:last-child {
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
                page-break-inside: avoid;
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
    </style>
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
        <a href="rsmi.php" class="btn btn-secondary">← Back to RSMI</a>
    </div>

    <!-- RSMI Form -->
    <div class="form-container">
        <div class="appendix-label">Appendix 64</div>
        <div class="agency-header">
            <img src="images/TESDA-Logo-export.png" alt="TESDA Logo">
            <div class="agency-text">
                <div>Republic of the Philippines</div>
                <div><strong>TECHNICAL EDUCATION & SKILLS DEVELOPMENT AUTHORITY</strong></div>
                <div>Cordillera Administrative Region</div>
            </div>
        </div>
        <table class="main-table" style="width: 100%;">
            <tr>
                <td class="title-cell" colspan="8">REPORT OF SUPPLIES AND MATERIALS ISSUED (RSMI)</td>
            </tr>
            <tr>
                <td class="header-cell" colspan="4" style="padding-bottom: 3px !important; border-left: 2px solid black;">
                    <strong>Entity Name:</strong> <span class="data-underline"> <?= htmlspecialchars($entity_info['entity_name'] ?? '') ?> </span><br>
                    <strong>Fund Cluster:</strong> <span class="data-underline"> <?= htmlspecialchars($entity_info['fund_cluster'] ?? '') ?> </span><br>
                </td>
                <td class="header-cell" colspan="4" style="padding-bottom: 3px !important; border-right: 2px solid black;">
                    <strong>Serial No.:</strong> <span class="data-underline"> RSMI-<?= date('Y') ?>-001 </span><br>
                    <strong>Date:</strong> <span class="data-underline"> <?= date('F d, Y') ?> </span><br>
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
            <?php 
            $row_count = 0;
            if ($rsmi_result && $rsmi_result->num_rows > 0) {
                while ($row = $rsmi_result->fetch_assoc()) {
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
                        if ($recap_result && $recap_result->num_rows > 0) {
                            $recap_result->data_seek(0);
                            while ($recap_row = $recap_result->fetch_assoc()) {
                                echo '<tr>';
                                echo '<td>&nbsp;</td>';
                                echo '<td style="border: none; border-right: 2px solid black; border-left: 2px solid black;">' . htmlspecialchars($recap_row['stock_number']) . '</td>';
                                echo '<td style="border: none; border-right: 2px solid black; border-left: 2px solid black;">' . htmlspecialchars($recap_row['total_issued']) . '</td>';
                                echo '<td>&nbsp;</td>';
                                echo '<td>&nbsp;</td>';
                                echo '<td class="text-right" style="border: none; border-right: 2px solid black; border-left: 2px solid black;">₱ ' . number_format($recap_row['avg_unit_cost'], 2) . '</td>';
                                echo '<td class="text-right" style="border: none; border-right: 2px solid black; border-left: 2px solid black;">₱ ' . number_format($recap_row['total_cost'], 2) . '</td>';
                                echo '<td style="border: none; border-right: 2px solid black; border-left: 2px solid black;">&nbsp;</td>'; // UACS Object Code
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
                    <td style="border: 2px solid black; border-top: none; border-left: none; vertical-align: top;"><div class="signatory-name"><?= htmlspecialchars($report_date_display) ?></div><div class="signature-line"></div>Date</td>
                </tr>
                
        </table>

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