<?php
require 'config.php';

// Get Property Card data
$pc_query = "
    SELECT pc.*, 
        COALESCE(pc.receipt_qty, 0) as receipt_qty,
        COALESCE(pc.issue_qty, 0) as issue_qty,
        COALESCE(pc.receipt_qty, 0) - COALESCE(pc.issue_qty, 0) as balance_qty
    FROM property_cards pc
    ORDER BY pc.entity_name, pc.fund_cluster, pc.ppe_type, pc.transaction_date DESC
";

$pc_result = $conn->query($pc_query);

// Get entity information (from the first record)
$entity_info = null;
if ($pc_result && $pc_result->num_rows > 0) {
    $pc_result->data_seek(0);
    $entity_info = $pc_result->fetch_assoc();
    $pc_result->data_seek(0); // Reset pointer
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Card Export</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.2;
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
            background-color: #17a2b8;
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .form-title {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            padding: 10px;
            border-bottom: 2px solid black;
            background-color: #f8f9fa;
        }

        .header-section {
            display: flex;
            border-bottom: 1px solid black;
            padding: 8px;
        }

        .header-left {
            flex: 1;
            border-right: 1px solid black;
            padding-right: 10px;
        }

        .header-right {
            flex: 1;
            padding-left: 10px;
        }

        .header-field {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }

        .header-field strong {
            min-width: 120px;
            font-weight: bold;
        }

        .header-value {
            border-bottom: 1px solid black;
            flex: 1;
            padding: 2px 5px;
            min-height: 20px;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
        }

        .main-table th,
        .main-table td {
            border: 1px solid black;
            padding: 3px;
            text-align: center;
            vertical-align: middle;
            font-size: 9px;
        }

        .main-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .main-table td {
            min-height: 18px;
        }

        .main-table .text-left {
            text-align: left;
        }

        .main-table .text-right {
            text-align: right;
        }

        .col-date { width: 6%; }
        .col-ref { width: 8%; }
        .col-receipt { width: 5%; }
        .col-issue-qty { width: 5%; }
        .col-issue-office { width: 15%; }
        .col-balance { width: 5%; }
        .col-amount { width: 8%; }
        .col-remarks { width: 10%; }

        .signature-section {
            border-top: 2px solid black;
            padding: 15px;
            min-height: 100px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .signature-block {
            text-align: center;
            width: 30%;
        }

        .signature-line {
            border-bottom: 1px solid black;
            height: 25px;
            margin-bottom: 5px;
        }

        .signature-text {
            font-size: 10px;
            font-weight: bold;
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

            @page {
                margin: 0.5in;
                size: A4 landscape;
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
        <p class="note">For best results: Use Chrome or Edge browser for optimal PDF formatting. Recommended: Landscape orientation.</p>
    </div>

    <!-- Buttons -->
    <div class="button-container">
        <button class="btn btn-primary" onclick="window.print()">📄 Print/Save as PDF</button>
        <a href="pc.php" class="btn btn-secondary">← Back to Property Card</a>
    </div>

    <!-- Property Card Form -->
    <div class="form-container">
        <!-- Form Title -->
        <div class="form-title">
            PROPERTY CARD
        </div>

        <!-- Header Section -->
        <div class="header-section">
            <div class="header-left">
                <div class="header-field">
                    <strong>Entity Name:</strong>
                    <div class="header-value"><?= htmlspecialchars($entity_info['entity_name'] ?? '') ?></div>
                </div>
                <div class="header-field">
                    <strong>Property, Plant and Equipment:</strong>
                    <div class="header-value"><?= htmlspecialchars($entity_info['ppe_type'] ?? '') ?></div>
                </div>
                <div class="header-field">
                    <strong>Description:</strong>
                    <div class="header-value"><?= htmlspecialchars($entity_info['description'] ?? '') ?></div>
                </div>
            </div>
            <div class="header-right">
                <div class="header-field">
                    <strong>Fund Cluster:</strong>
                    <div class="header-value"><?= htmlspecialchars($entity_info['fund_cluster'] ?? '') ?></div>
                </div>
                <div class="header-field">
                    <strong>Property Number:</strong>
                    <div class="header-value"><?= htmlspecialchars($entity_info['property_number'] ?? '') ?></div>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <table class="main-table">
            <thead>
                <tr>
                    <th rowspan="2" class="col-date">Date</th>
                    <th rowspan="2" class="col-ref">Reference/<br>PAR No.</th>
                    <th>Receipt</th>
                    <th colspan="2">Issue/Transfer/Disposal</th>
                    <th>Balance</th>
                    <th rowspan="2" class="col-amount">Amount</th>
                    <th rowspan="2" class="col-remarks">Remarks</th>
                </tr>
                <tr>
                    <th class="col-receipt">Qty.</th>
                    <th class="col-issue-qty">Qty.</th>
                    <th class="col-issue-office">Office/Officer</th>
                    <th class="col-balance">Qty.</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $row_count = 0;
                if ($pc_result && $pc_result->num_rows > 0) {
                    while ($row = $pc_result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['transaction_date'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($row['reference_par_no'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($row['receipt_qty'] > 0 ? $row['receipt_qty'] : '') . '</td>';
                        echo '<td>' . htmlspecialchars($row['issue_qty'] > 0 ? $row['issue_qty'] : '') . '</td>';
                        echo '<td class="text-left">' . htmlspecialchars($row['office_officer'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($row['balance_qty']) . '</td>';
                        echo '<td class="text-right">₱ ' . number_format($row['amount'] ?? 0, 2) . '</td>';
                        echo '<td class="text-left">' . htmlspecialchars($row['remarks'] ?? '') . '</td>';
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

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-text">
                    Supply and/or Property Custodian
                </div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-text">
                    Accounting Staff
                </div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-text">
                    Date: <?= date('F d, Y') ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Optional: Auto-focus print dialog on page load
        // window.addEventListener('load', function() {
        //     setTimeout(function() {
        //         if (confirm('Would you like to print/save this Property Card as PDF?')) {
        //             window.print();
        //         }
        //     }, 1000);
        // });
    </script>
</body>
</html>