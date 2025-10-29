<?php
require 'config.php';

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
        }

        /* Appendix 64 styling */
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

        .form-title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            padding: 8px;
            border-bottom: 1px solid black;
            background-color: #f8f9fa;
            margin-top: 20px; 
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
        }

        .main-table th,
        .main-table td {
            border: 1px solid black;
            padding: 4px;
            text-align: center;
            vertical-align: middle;
            font-size: 10px;
        }

        .main-table th {
            background-color: #f0f0f0;
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

        .bottom-section {
            display: flex;
            border-top: 2px solid black;
        }

        .recapitulation-left,
        .recapitulation-right {
            flex: 1;
        }

        .recapitulation-left {
            border-right: 1px solid black;
        }

        .recap-table {
            width: 100%;
            border-collapse: collapse;
        }

        .recap-table th,
        .recap-table td {
            border: 1px solid black;
            padding: 4px;
            text-align: center;
            font-size: 10px;
        }

        .recap-table th {
            background-color: #f0f0f0;
            font-weight: bold;
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
        }

        .signature-left {
            border-right: 1px solid black;
        }

        .signature-line {
            border-bottom: 1px solid black;
            height: 25px;
            margin-top: 15px;
            margin-bottom: 5px;
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
        <!-- Appendix 64 Label -->
        <div class="appendix-label">Appendix 64</div>
        
        <!-- Header Section -->
        <div class="form-title">
            REPORT ON THE STOCK OF MATERIALS AND SUPPLIES ISSUED (RSMI)
        </div>
        
        <div class="header-section">
            <div class="header-left">
                <div class="header-field">
                    <strong>Entity Name:</strong>
                    <div class="header-value"><?= htmlspecialchars($entity_info['entity_name'] ?? '') ?></div>
                </div>
                <div class="header-field">
                    <strong>Fund Cluster:</strong>
                    <div class="header-value"><?= htmlspecialchars($entity_info['fund_cluster'] ?? '') ?></div>
                </div>
                <div class="header-field">
                    <strong>Division:</strong>
                    <div class="header-value"><?= htmlspecialchars($entity_info['division'] ?? '') ?></div>
                </div>
                <div class="header-field">
                    <strong>Office:</strong>
                    <div class="header-value"><?= htmlspecialchars($entity_info['office'] ?? '') ?></div>
                </div>
            </div>
            <div class="header-right">
                <div class="header-field">
                    <strong>Serial No.:</strong>
                    <div class="header-value">RSMI-<?= date('Y') ?>-001</div>
                </div>
                <div class="header-field">
                    <strong>Date:</strong>
                    <div class="header-value"><?= date('F d, Y') ?></div>
                </div>
                <div class="header-field">
                    <strong>Responsibility Code:</strong>
                    <div class="header-value"><?= htmlspecialchars($entity_info['responsibility_center_code'] ?? '') ?></div>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="instructions">
            To be filled up by the Supply and/or Property Division/Unit
        </div>
        <div class="instructions">
            To be filled up by the Accounting Division/Unit
        </div>

        <!-- Main Table -->
        <table class="main-table">
            <thead>
                <tr>
                    <th style="width: 8%;">RIS No.</th>
                    <th style="width: 15%;">Responsibility Center</th>
                    <th style="width: 10%;">Stock No.</th>
                    <th style="width: 25%;">Item</th>
                    <th style="width: 8%;">Unit</th>
                    <th style="width: 10%;">Quantity Issued</th>
                    <th style="width: 12%;">Unit Cost</th>
                    <th style="width: 12%;">Amount</th>
                </tr>
            </thead>
            <tbody>
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
                
                // Fill remaining rows with empty cells (up to 25 rows total)
                for ($i = $row_count; $i < 25; $i++) {
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
            </tbody>
        </table>

        <!-- Recapitulation Section -->
        <div class="bottom-section">
            <div class="recapitulation-left">
                <table class="recap-table">
                    <thead>
                        <tr>
                            <th colspan="2">Recapitulation:</th>
                        </tr>
                        <tr>
                            <th>Stock No.</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $recap_count = 0;
                        if ($recap_result && $recap_result->num_rows > 0) {
                            while ($recap_row = $recap_result->fetch_assoc()) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($recap_row['stock_number']) . '</td>';
                                echo '<td>' . htmlspecialchars($recap_row['total_issued']) . '</td>';
                                echo '</tr>';
                                $recap_count++;
                            }
                        }
                        
                        // Fill remaining recap rows (up to 15 rows)
                        for ($i = $recap_count; $i < 15; $i++) {
                            echo '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="recapitulation-right">
                <table class="recap-table">
                    <thead>
                        <tr>
                            <th colspan="3">Recapitulation:</th>
                        </tr>
                        <tr>
                            <th>Unit Cost</th>
                            <th>Total Cost</th>
                            <th>UACS Object Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Reset the recap result pointer
                        if ($recap_result) {
                            $recap_result->data_seek(0);
                            $recap_count = 0;
                            while ($recap_row = $recap_result->fetch_assoc()) {
                                echo '<tr>';
                                echo '<td class="text-right">₱ ' . number_format($recap_row['avg_unit_cost'], 2) . '</td>';
                                echo '<td class="text-right">₱ ' . number_format($recap_row['total_cost'], 2) . '</td>';
                                echo '<td>&nbsp;</td>'; // UACS Object Code - you may need to add this field to your database
                                echo '</tr>';
                                $recap_count++;
                            }
                            
                            // Fill remaining rows
                            for ($i = $recap_count; $i < 15; $i++) {
                                echo '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-left">
                <div style="font-size: 10px; margin-bottom: 10px;">
                    I hereby certify to the correctness of the above information.
                </div>
                <div style="text-align: center; margin-top: auto;">
                    <div class="signature-line"></div>
                    <div class="signature-text">
                        Signature over Printed Name of Supply<br>
                        and/or Property Custodian
                    </div>
                </div>
            </div>
            <div class="signature-right">
                <div class="posted-by">Posted by:</div>
                <div style="text-align: center; margin-top: auto;">
                    <div class="signature-line"></div>
                    <div class="signature-text">
                        of Designated Accounting<br>
                        Staff
                    </div>
                    <div style="margin-top: 15px;">
                        <div class="signature-line"></div>
                        <div class="signature-text">Date</div>
                    </div>
                </div>
            </div>
        </div>
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

        // Optional: Auto-focus print dialog on page load
        // window.addEventListener('load', function() {
        //     setTimeout(function() {
        //         if (confirm('Would you like to print/save this RSMI form as PDF?')) {
        //             printForm();
        //         }
        //     }, 1000);
        // });
    </script>
</body>
</html>