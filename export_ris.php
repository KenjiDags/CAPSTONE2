<?php
require 'config.php';
require 'auth.php';

if (!isset($_GET['ris_id'])) {
    die("❌ Error: RIS ID not specified in the URL.");
}

$ris_id = (int)$_GET['ris_id'];

// Fetch RIS header
$ris_result = $conn->query("SELECT * FROM ris WHERE ris_id = $ris_id");
if (!$ris_result || $ris_result->num_rows === 0) {
    die("❌ No RIS record found for RIS ID: $ris_id");
}
$ris = $ris_result->fetch_assoc();

function formatSignatoryDate($date_value) {
    if (empty($date_value)) {
        return '';
    }

    $date = DateTime::createFromFormat('Y-m-d', $date_value);
    if ($date && $date->format('Y-m-d') === $date_value) {
        return $date->format('F d, Y');
    }

    return $date_value;
}

// Fetch items that were actually issued (only those with issued_quantity > 0)
$item_query = "
    SELECT 
        i.stock_number,
        i.item_name,
        i.description,
        i.unit,
        ri.issued_quantity,
        ri.stock_available,
        ri.remarks
    FROM items i
    INNER JOIN ris_items ri ON i.stock_number = ri.stock_number 
    WHERE ri.ris_id = $ris_id AND ri.issued_quantity > 0
    ORDER BY i.stock_number
";
$item_result = $conn->query($item_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RIS No. <?php echo htmlspecialchars($ris['ris_no']); ?> - Export</title>
    <style>
        /* Print-specific styles */
        @media print {
            @page {
                size: A4;
                margin: 0.35in;
            }

            html,
            body {
                margin: 0;
                padding: 0;
                font-size: 8.5px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print { display: none !important; }
            .print-container {
                page-break-inside: avoid;
                max-width: none;
                width: 100%;
                margin: 0;
                padding: 6px !important;
                border: none;
                box-sizing: border-box;
                transform: scale(0.95);
                transform-origin: top left;
                width: 105.3%;
            }

            .items-table {
                width: 100%;
                table-layout: auto;
            }

            .items-table th,
            .items-table td {
                font-size: 6.5px !important;
                padding: 2.5px 2px !important;
                line-height: 1.25 !important;
            }

            .items-table th.table-main-title {
                font-size: 12px !important;
                line-height: 1.2 !important;
                padding: 6px 2px !important;
                font-weight: bold !important;
            }

            .fixed-underline,
            .header-fixed-underline,
            .header-fixed-underline-short {
                padding-bottom: 0 !important;
                line-height: 1 !important;
                min-height: 0 !important;
                vertical-align: bottom;
            }

            .header-fixed-underline {
                width: 150px;
            }

            .header-fixed-underline-short {
                width: 95px;
            }

            .fixed-underline {
                width: 120px;
            }

            .agency-header {
                padding-top: 8px;
                padding-bottom: 8px;
            }

                .agency-header img {
                    width: 43px !important;
                    height: 43px !important;
                    max-width: 43px !important;
                    max-height: 43px !important;
            }

            .appendix-label {
                top: 4px;
                right: 6px;
                font-size: 10px;
                font-style: italic;
                font-weight: normal;
                z-index: 1000;
            }
        }
        
        /* General styles */
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.2;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 20px;
            position: relative;
        }
        
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            position: relative;
        }
        
        .appendix-label {
            position: absolute;
            top: 8px;
            right: 12px;
            font-size: 11px;
            font-style: italic;
            font-weight: normal;
            color: #333;
            z-index: 100;
        }
        
        .header-title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 15px;
            margin-top: 25px;
            padding: 8px;
            position: relative;
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

        .table-main-title {
            text-align: center !important;
            font-weight: bold !important;
            font-size: 14px !important;
            padding: 8px !important;
            border: 1px solid #000 !important;
            height: 30px;
        }
        
        .info-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        
        .items-table .label {
            font-weight: bold;
            width: 15%;
            text-align: left;
        }
        
        .items-table .value {
            width: 35%;
            text-decoration: underline;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 3px;
            text-align: center;
            font-size: 8px;
            vertical-align: middle;
        }
        
        .items-table th {
            font-weight: bold;
        }
        
        .items-table .description {
            text-align: left;
            font-size: 7px;
        }
        
        .items-table .remarks {
            text-align: left;
            font-size: 7px;
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
        
        .checkbox {
            font-size: 12px;
            font-weight: bold;
        }

        .fixed-underline {
            display: inline-block;
            width: 150px;
            border-bottom: 1px solid #000;
            min-height: 0;
            line-height: 1;
            padding-bottom: 1px;
            text-align: center;
        }

        .header-fixed-underline {
            display: inline-block;
            width: 180px;
            border-bottom: 1px solid #000;
            min-height: 0;
            line-height: 1;
            padding-bottom: 1px;
            text-align: left;
        }

        .header-fixed-underline-short {
            display: inline-block;
            width: 120px;
            border-bottom: 1px solid #000;
            min-height: 0;
            line-height: 1;
            padding-bottom: 1px;
            text-align: left;
        }

        .fund-cluster-cell {
            white-space: nowrap;
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
        <button class="back-button" onclick="history.back()">← Back</button>
        <hr style="margin: 20px 0;">
    </div>
    
    <div class="print-container">
        <div class="appendix-label">Appendix 63</div>

        <div class="agency-header">
            <img src="images/TESDA-Logo-export.png" alt="TESDA Logo">
            <div class="agency-text">
                <div>Republic of the Philippines</div>
                <div><strong>TECHNICAL EDUCATION & SKILLS DEVELOPMENT AUTHORITY</strong></div>
                <div>Cordillera Administrative Region</div>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th colspan="9" class="table-main-title">REQUISITION AND ISSUE SLIP</th>
                </tr>

                <tr>
                    <td colspan="7" class="label" style="border-top: none !important; border-right: none !important;">Entity Name: <span class="header-fixed-underline"><?php echo htmlspecialchars($ris['entity_name']); ?></span></td>
                    <td colspan="2" class="label fund-cluster-cell" style="border-top: none !important; border-left: none !important;">Fund Cluster: <span class="header-fixed-underline-short"><?php echo htmlspecialchars($ris['fund_cluster']); ?></span></td>
                </tr>
                <tr>
                    <td colspan="5" class="label">Division: <span class="header-fixed-underline"><?php echo htmlspecialchars($ris['division']); ?></span></td>
                    <td colspan="5" class="label">Responsibility Center Code: <span class="header-fixed-underline"><?php echo htmlspecialchars($ris['responsibility_center_code']); ?></span></td>
                </tr>
                <tr>
                    <td colspan="5" class="label">Office: <span class="header-fixed-underline"><?php echo htmlspecialchars($ris['office']); ?></span></td>
                    <td colspan="5" class="label">RIS No: <span class="header-fixed-underline"><?php echo htmlspecialchars($ris['ris_no']); ?></span></td>
                </tr>

                <tr>
                    <th rowspan="2" style="width: 15%;">Stock No.</th>
                    <th rowspan="2" style="width: 20%;">Description</th>
                    <th rowspan="2" style="width: 20%;">Unit</th>
                    <th colspan="2" style="width: 15%;">Requisition</th>
                    <th colspan="2" style="width: 20%;">Stock Available?</th>
                    <th colspan="2" style="width: 20%;">Issue</th>
                </tr>
                <tr>
                    <th style="width: 8%;">Quantity</th>
                    <th style="width: 9%;">Remarks</th>
                    <th style="width: 5%;">Yes</th>
                    <th style="width: 5%;">No</th>
                    <th style="width: 10%;">Quantity</th>
                    <th style="width: 10%;">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Add issued items to the table
                $row_count = 0;
                if ($item_result && $item_result->num_rows > 0) {
                    while ($item = $item_result->fetch_assoc()) {
                        $stock_available_yes = ($item['stock_available'] == 'Yes') ? '<span class="checkbox">✓</span>' : '';
                        $stock_available_no = ($item['stock_available'] == 'No') ? '<span class="checkbox">✓</span>' : '';
                        
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($item['stock_number']) . '</td>';
                        echo '<td class="description">' . htmlspecialchars($item['item_name']) . ',' . htmlspecialchars($item['description']) . '</td>';
                        echo '<td>' . htmlspecialchars($item['unit']) . '</td>';
                        echo '<td>' . htmlspecialchars($item['issued_quantity']) . '</td>';
                        echo '<td class="remarks">' . htmlspecialchars($item['remarks']) . '</td>';
                        echo '<td>' . $stock_available_yes . '</td>';
                        echo '<td>' . $stock_available_no . '</td>';
                        echo '<td>' . htmlspecialchars($item['issued_quantity']) . '</td>';
                        echo '<td class="remarks">' . htmlspecialchars($item['remarks']) . '</td>';
                        echo '</tr>';
                        $row_count++;
                    }
                }
                
                // Add empty rows to fill the table (minimum 12 rows total)
                for ($i = $row_count; $i < 12; $i++) {
                    echo '<tr>';
                    echo '<td>&nbsp;</td>';
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

                <tr class="purpose-section">
                    <td rowspan="3"
                        style="text-align: right; vertical-align: top; border-right: none; font-weight: bold;">
                        Purpose:
                    </td>
                    <td colspan="8" style="border-left: none; text-align: left; ">
                        <?php 
                            echo !empty($ris['purpose']) 
                                ? nl2br(htmlspecialchars($ris['purpose'])) 
                                : '&nbsp;'; 
                        ?>
                    </td>
                </tr>

                <tr>
                    <td colspan="8" style="border: none !important; border-right: 1px solid black !important;">&nbsp;</td>
                </tr>

                <tr>
                    <td colspan="8" style="border-left: none;">&nbsp;</td>
                </tr>

                <tr>
                    <td style="border: none; border-left: 1px solid black;">Signature:</td>
                    <td style="border: none;" colspan="2"><span class="fixed-underline">&nbsp;</span></td>
                    <td style="border: none;" colspan="2"><span class="fixed-underline">&nbsp;</span></td>
                    <td style="border: none;" colspan="2"><span class="fixed-underline">&nbsp;</span></td>
                    <td style="border: none; border-right: 1px solid black;" colspan="2"><span class="fixed-underline">&nbsp;</span></td>
                </tr>

                <tr>
                    <td style="border: none; border-left: 1px solid black;">Printed Name:</td>
                    <td style="border: none;" colspan="2"><span class="fixed-underline"><?php echo htmlspecialchars($ris['requested_by']); ?></span></td>
                    <td style="border: none;" colspan="2"><span class="fixed-underline"><?php echo htmlspecialchars($ris['approved_by']); ?></span></td>
                    <td style="border: none;" colspan="2"><span class="fixed-underline"><?php echo htmlspecialchars($ris['issued_by']); ?></span></td>
                    <td style="border: none; border-right: 1px solid black;" colspan="2"><span class="fixed-underline"><?php echo htmlspecialchars($ris['received_by']); ?></span></td>
                </tr>

                <tr>
                    <td style="border: none; border-left: 1px solid black;">Designation:</td>
                    <td style="border: none;" colspan="2"><span class="fixed-underline"><?php echo htmlspecialchars($ris['requested_by_designation'] ?? ''); ?></span></td>
                    <td style="border: none;" colspan="2"><span class="fixed-underline"><?php echo htmlspecialchars($ris['approved_by_designation'] ?? ''); ?></span></td>
                    <td style="border: none;" colspan="2"><span class="fixed-underline"><?php echo htmlspecialchars($ris['issued_by_designation'] ?? ''); ?></span></td>
                    <td style="border: none; border-right: 1px solid black;" colspan="2"><span class="fixed-underline"><?php echo htmlspecialchars($ris['received_by_designation'] ?? ''); ?></span></td>
                </tr>

                <tr>
                    <td style="border: none; border-left: 1px solid black; border-bottom: 1px solid black;">Date:</td>
                    <td style="border: none; border-bottom: 1px solid black;" colspan="2"><span class="fixed-underline"><?php echo htmlspecialchars(formatSignatoryDate($ris['requested_by_date'] ?? '')); ?></span></td>
                    <td style="border: none; border-bottom: 1px solid black;" colspan="2"><span class="fixed-underline"><?php echo htmlspecialchars(formatSignatoryDate($ris['approved_by_date'] ?? '')); ?></span></td>
                    <td style="border: none; border-bottom: 1px solid black;" colspan="2"><span class="fixed-underline"><?php echo htmlspecialchars(formatSignatoryDate($ris['issued_by_date'] ?? '')); ?></span></td>
                    <td style="border: none; border-right: 1px solid black; border-bottom: 1px solid black;" colspan="2"><span class="fixed-underline"><?php echo htmlspecialchars(formatSignatoryDate($ris['received_by_date'] ?? '')); ?></span></td>
                </tr>

            </tbody>
        </table>


    </div>

</body>
</html>

<?php
$conn->close();
?>