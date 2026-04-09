<?php
require 'auth.php';
require 'config.php';
require 'functions.php';

// Validate input
$itr_id = isset($_GET['itr_id']) ? (int)$_GET['itr_id'] : 0;
if ($itr_id <= 0) {
    header('Location: itr.php');
    exit();
}

// Fetch ITR header
$stmt = $conn->prepare("SELECT * FROM itr WHERE itr_id = ? LIMIT 1");
$stmt->bind_param('i', $itr_id);
$stmt->execute();
$res = $stmt->get_result();
$itr = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$itr) { header('Location: itr.php'); exit(); }

// Fetch ITR items
$stmt = $conn->prepare("SELECT date_acquired, item_no, ics_info, description, amount, cond FROM itr_items WHERE itr_id = ? ORDER BY itr_item_id ASC");
$stmt->bind_param('i', $itr_id);
$stmt->execute();
$items_rs = $stmt->get_result();
$items = [];
$total_amount = 0.0;
if ($items_rs) {
    while ($row = $items_rs->fetch_assoc()) {
        $items[] = $row;
        $total_amount += (float)($row['amount'] ?? 0);
    }
}
$stmt->close();

// Limit items so the printout fits on a single A4 page
$MAX_ITEMS_ON_PAGE = 12; 

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function peso($n) { $n = (float)$n; return number_format($n, 2); }

// Transfer type flags
$tt = strtolower((string)($itr['transfer_type'] ?? ''));
$isDonation = $tt === 'donation';
$isReassignment = $tt === 'reassignment';
$isRelocate = $tt === 'relocate';
$isOthers = $tt === 'others' || ($tt !== '' && !$isDonation && !$isReassignment && !$isRelocate);
$otherText = $isOthers ? (string)($itr['transfer_other'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITR No. <?php echo h($itr['itr_no']); ?> - Export</title>
    <style>
        @media print {
            @page { size: A4 portrait; margin: 12mm; }
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .print-container { page-break-inside: avoid; }
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.2;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 20px;
        }

        .print-container {
            width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 8px 6px 12px 6px;
        }

        @media screen {
            .print-container { max-width: 720px; }
        }


    .main-table thead th.header-title {
        padding: 8px 4px;
        background: #fff;
        border: 1px solid #000;
        font-size: 14px;
        font-weight: bold;
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
        font-size: 11px;
    }

    .uline { display:inline-block; min-width: 260px; border-bottom:1px solid #000; padding:0 4px; }
    .uline-sm { display:inline-block; min-width: 50px; border-bottom:1px solid #000; padding:0 4px; text-align: left;}
    .transfer-row { border-top:2px solid #000; border-bottom:2px solid #000; padding-top:6px; margin-top:4px; }
    .uline-inline { display: inline-block; border-bottom:1px solid #000; padding:0 2px; line-height: 1; width:auto; margin: 4px 0px; }
    .cbox { display: inline-flex; align-items: center; }
    .cbox .sq { display:inline-block; width:12px; height:12px; border:1px solid #000; vertical-align:middle; }
    .cbox .sq.checked { background:#000; }

    @media print {
        .cbox .sq {
            position: relative;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .cbox .sq.checked::after {
            content: "X";
            position: absolute;
            top: -2px;
            left: 1px;
            font-size: 11px;
            line-height: 12px;
            font-weight: bold;
            color: #000;
        }
    }
    .other-line { display:inline-block; flex:1; min-width: 260px; border-bottom:1px solid #000; height: 0; margin-left: 8px; vertical-align: middle; position: relative; top: 3px; }
    .main-table { width: 100%; border-collapse: collapse; margin: 0; border: 2px solid #000; page-break-inside: avoid; }
    .main-table th, .main-table td { border: 1px solid #000; padding: 6px 4px; text-align: center; font-size: 10px; vertical-align: middle; }
    .main-table th { font-weight: bold; }
    .main-table .label { font-weight: bold; text-align: left;}
    .main-table .description { text-align: left; font-size: 10px; }
    .main-table thead tr.info-row td,
    .main-table thead tr.transfer-row td { text-align: left; vertical-align: top; font-size: 10px; }
    .main-table thead tr.info-row td { padding: 6px 4px; }
    .main-table thead tr.transfer-row td { padding: 6px 4px; }

    .tt-table { border-collapse: collapse; }
    .tt-table td { padding-right: 48px; padding-top: 2px; padding-bottom: 2px; vertical-align: middle; }
    .main-table tbody tr.reason-row td {
        text-align: left;
        vertical-align: top;
        padding: 8px 6px;
    }
    .main-table tbody tr.reason-row .line {
        border-bottom:1px solid #000;
        height:16px;
        margin:6px 0;
        display:block;
        width:100%;
    }
    .main-table tbody tr.signature-head-row th {
        border-bottom: none !important;
    }
    .main-table tbody tr.signature-row td {
        border-top: none !important;
        vertical-align: top;
        padding: 10px 14px;
    }
    .main-table tbody tr.signature-row td.sig-labels {
        width: 15%;
    }
    .main-table tbody tr.signature-row td.sig-approve,
    .main-table tbody tr.signature-row td.sig-release,
    .main-table tbody tr.signature-row td.sig-receive {
        width: 28.333%;
    }
    .sig-row { display:grid; grid-template-columns: auto 1fr; align-items: start; column-gap: 0px; margin: 5px 0; }
    .sig-row .sig-line { border-bottom:1px solid #000; min-height:8px; line-height:12px; width:100%; white-space: nowrap; overflow: hidden; }

    .print-instructions { background: #fffacd; border: 1px solid #ddd; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
    .print-button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; margin-right: 10px; }
    .print-button:hover { background: #005a87; }
    .back-button { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
    .back-button:hover { background: #545b62; }
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
        <a href="itr.php" class="back-button">← Back to ITR List</a>
        <a href="add_itr.php?itr_id=<?php echo (int)$itr_id; ?>" class="back-button">✏️ Edit ITR</a>
        <hr style="margin: 20px 0;">
    </div>

    <div class="print-container">
        <div style="text-align:right; font-style:italic; font-size:11px;">Annex A.5</div>

        <div class="agency-header">
            <img src="images/TESDA-Logo-export.png" alt="TESDA Logo">
            <div class="agency-text">
                <div>Republic of the Philippines</div>
                <div><strong>TECHNICAL EDUCATION &amp; SKILLS DEVELOPMENT AUTHORITY</strong></div>
                <div>Cordillera Administrative Region</div>
            </div>
        </div>

        <table class="main-table">
            <thead>
                <tr>
                    <th colspan="6" class="header-title">INVENTORY TRANSFER REPORT</th>
                </tr>
                <tr class="info-row">
                    <td colspan="4">
                        <strong>Entity Name :</strong>
                        <span class="uline"><?php echo h($itr['entity_name']); ?></span>
                    </td>
                    <td colspan="2" style="text-align:left;">
                        <strong>Fund Cluster :</strong>
                        <span class="uline-sm"><?php echo h($itr['fund_cluster']); ?></span>
                    </td>
                </tr>

                <tr> 
                    <td colspan="4" class="label">From Accountable Officer/Agency/Fund Cluster : <span class="uline"><?php echo h($itr['from_accountable']); ?></span></td>
                    <td colspan="2" class="label">ITR No : <span class="uline-inline"><?php echo h($itr['itr_no']); ?></span></td>
                </tr>

                <tr>
                    <td colspan="4" class="label">To Accountable Officer/Agency/Fund Cluster : <span class="uline"><?php echo h($itr['to_accountable']); ?></span></td>
                    <td colspan="2" class="label">Date : <span class="uline-inline"><?php echo h($itr['itr_date']); ?></span></td>
                </tr>

                <tr class="transfer-row">
                    <td colspan="6">
                            <div class="tt-head">
                                <strong>Transfer Type:</strong> <em>(check only one)</em>
                            </div>
                            <table class="tt-table" style="margin-left:12px;">
                                <tr>
                                    <td style="border: none;">
                                        <span class="cbox">
                                            <span class="sq <?php echo $isDonation ? 'checked' : ''; ?>"></span>
                                            <span style="margin-left:6px;">Donation</span>
                                        </span>
                                    </td>
                                    <td style="border: none;">
                                        <span class="cbox">
                                            <span class="sq <?php echo $isRelocate ? 'checked' : ''; ?>"></span>
                                            <span style="margin-left:6px;">Relocate</span>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border: none;">
                                        <span class="cbox">
                                            <span class="sq <?php echo $isReassignment ? 'checked' : ''; ?>"></span>
                                            <span style="margin-left:6px;">Reassignment</span>
                                        </span>
                                    </td>
                                    <td style="border: none;">
                                        <span class="cbox">
                                            <span class="sq <?php echo $isOthers ? 'checked' : ''; ?>"></span>
                                            <span style="margin-left:6px;">Others (Specify)</span>
                                            <span class="other-line"></span>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                    </td>
                </tr>
                <tr>
                    <th style="width: 15%">Date Acquired</th>
                    <th style="width: 15%">Item No.</th>
                    <th style="width: 15%">ICS No./Date</th>
                    <th style="width: 20%">Description</th>
                    <th style="width: 15%">Amount</th>
                    <th style="width: 20%">Condition of<br>Inventory</th>
                </tr>

            </thead>
            <tbody>
                <?php
                // Only render up to MAX_ITEMS_ON_PAGE rows to keep to a single page
                $display_items = array_slice($items, 0, $MAX_ITEMS_ON_PAGE);
                $row_count = 0;
                foreach ($display_items as $it) {
                    echo '<tr>';
                    echo '<td>' . h($it['date_acquired']) . '</td>';
                    echo '<td>' . h($it['item_no']) . '</td>';
                    echo '<td>' . h($it['ics_info']) . '</td>';
                    echo '<td class="description">' . h($it['description']) . '</td>';
                    echo '<td style="text-align:right;">' . peso($it['amount']) . '</td>';
                    echo '<td>' . h($it['cond']) . '</td>';
                    echo '</tr>';
                    $row_count++;
                }

                for ($i = $row_count; $i < $MAX_ITEMS_ON_PAGE; $i++) {
                    echo '<tr>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '<td>&nbsp;</td>';
                    echo '</tr>';
                }
                ?>

                <tr class="reason-row">
                    <td colspan="6" style="border: 2px solid #000;">
                        <strong>Reason/s for Transfer:</strong>
                        <div class="line">&nbsp;</div>
                        <div class="line">&nbsp;</div>
                        <div class="line">&nbsp;</div>
                    </td>
                </tr>

                    <tr class="signature-head-row">
                        <th>&nbsp;</th>
                        <th colspan="2" style="text-align: left;">Approved by:</th>
                        <th style="text-align: left;">Released/Issued by:</th>
                        <th colspan="2" style="text-align: left;">Received by:</th>
                    </tr>
                    <tr class="signature-row">
                        <td class="sig-labels">
                            <div class="sig-row"><div>Signature :</div></div>
                            <div class="sig-row"><div>Printed Name :</div></div></div>
                            <div class="sig-row"><div>Designation :</div></div></div>
                            <div class="sig-row"><div>Date :</div></div></div>
                        </td>
                        <td colspan="2" class="sig-approve">
                            <div class="sig-row"><div></div><div class="sig-line"></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['approved_name'] ?? ''); ?></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['approved_designation'] ?? ''); ?></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['approved_date'] ?? ''); ?></div></div>
                        </td>
                        <td class="sig-release">
                            <div class="sig-row"><div></div><div class="sig-line"></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['released_name'] ?? ''); ?></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['released_designation'] ?? ''); ?></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['released_date'] ?? ''); ?></div></div>
                        </td>
                        <td colspan="2" class="sig-receive">
                            <div class="sig-row"><div></div><div class="sig-line"></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['received_name'] ?? ''); ?></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['received_designation'] ?? ''); ?></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['received_date'] ?? ''); ?></div></div>
                        </td>
                    </tr>

            </tbody>
        </table>

    </div>


</body>
</html>

