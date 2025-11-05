<?php
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
$MAX_ITEMS_ON_PAGE = 12; // Adjust if you need a bit more/less content on one page

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

        .header-title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 8px;
            border: none;
            padding: 0;
            background: transparent;
            letter-spacing: 0.5px;
        }

    .info-section { width: 100%; border-collapse: collapse; }
    .info-section td { border: 0; padding: 4px 6px; font-size: 10px; }
    /* Overall form frame (matches the thick box in the template) */
    .form-frame { border:2px solid #000; padding:0; page-break-inside: avoid; }
        /* Header zone inside the frame (with thick bottom line) */
        .header-box { border:none; border-bottom:0px solid #000; padding:0; margin:0; }
    .uline { display:inline-block; min-width: 260px; width: 70%; border-bottom:1px solid #000; padding:0 4px; }
    .uline-sm { display:inline-block; min-width: 200px; width: 70%; border-bottom:1px solid #000; padding:0 4px; }
    .transfer-row { border-top:2px solid #000; border-bottom:2px solid #000; padding-top:6px; margin-top:4px; }
    .hb-left { width:68%; }
    .hb-right { width:32%; border-left:2px solid #000; }
    /* Header grid built entirely with tables */
    .hdr-grid { width:100%; border-collapse: collapse; }
    .label-table { width:100%; border-collapse: collapse; }
    .label-table .label { font-weight:bold; padding:2px 6px 0 6px; }
    .label-table .line-cell { border-bottom:none; padding:2px 6px 4px 6px; height:18px; }
    .label-table .line-cell .line-inner { display:inline-block; border-bottom:1px solid #000; padding:0 2px 2px 2px; height:16px; width:85%; vertical-align:bottom; }
    .uline-inline { display:inline-block; border-bottom:1px solid #000; padding:0 2px 2px 2px; min-height:16px; line-height:16px; margin-left:6px; vertical-align:baseline; width:auto; min-width:0; }
    .hb-left .uline-inline { min-width: 200px; }
    .hb-right .uline-inline { min-width: 130px; }
    /* Fine tune underline lengths per side */
    .hb-left .line-inner { width:88%; }
    .hb-right .line-inner { width:80%; }
    
    .transfer-options { display: inline-grid; grid-template-columns: 1fr 1fr; column-gap: 48px; row-gap: 6px; align-items: center; }
    .cbox { display: inline-flex; align-items: center; }
    .cbox .sq { display:inline-block; width:12px; height:12px; border:1px solid #000; vertical-align:middle; }
    .other-line { display:inline-block; flex:1; min-width: 260px; border-bottom:1px solid #000; height: 0; margin-left: 8px; vertical-align: middle; }

        /* Items grid (full grid, thick line below the table) */
    .items-table { width: 100%; border-collapse: collapse; margin: 0; border: none; border-bottom:2px solid #000; page-break-inside: avoid; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 6px 4px; text-align: center; font-size: 10px; vertical-align: middle; }
        .items-table th { font-weight: bold; }
        .items-table .description { text-align: left; font-size: 10px; }

        .tt-table { border-collapse: collapse; }
        .tt-table td { padding-right: 48px; padding-top: 2px; padding-bottom: 2px; vertical-align: middle; }
        .signatures { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .signatures td { border: 2px solid #000; padding: 10px; font-size: 10px; height: 100px; vertical-align: top; width: 33.33%; }
        .signatures .signature-title { font-weight: bold; margin-bottom: 10px; }
        .signature-line { border-bottom: 1px solid #000; width: 90%; height: 18px; margin: 0 auto 4px auto; text-align: center; line-height: 18px; }
        .signature-line.position { margin-top: 6px; }
        .signatures .signature-label { font-size: 7px; color: #666; text-align:center; }
    /* Reasons section (inside frame) */
    .reasons-lines { padding: 8px 6px; border-bottom:2px solid #000; }
        .reasons-lines .line { border-bottom:1px solid #000; height:16px; margin:6px 0; }
    /* Bottom signature section: no inner borders, only the frame provides edges */
    .sig-section { padding: 2px; }
    .sig-section table { width:100%; border-collapse: collapse; }
    .sig-section th, .sig-section td { padding:10px 14px; vertical-align: top; font-size: 10px; text-align:left; }
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
    <!-- PDF export is disabled -->
        <a href="itr.php" class="back-button">← Back to ITR List</a>
        <a href="add_itr.php?itr_id=<?php echo (int)$itr_id; ?>" class="back-button">✏️ Edit ITR</a>
        <hr style="margin: 20px 0;">
    </div>

    <div class="print-container">
        <div style="text-align:right; font-style:italic; font-size:11px;">Annex A.5</div>
        <div class="header-title">INVENTORY TRANSFER REPORT</div>

        <!-- Top line: Entity Name (left) and Fund Cluster (right), outside the frame -->
        <table class="info-section" style="margin-bottom:6px;">
            <tr>
                <td>
                    <strong>Entity Name :</strong>
                    <span class="uline"><?php echo h($itr['entity_name']); ?></span>
                </td>
                <td style="text-align:right;">
                    <strong>Fund Cluster :</strong>
                    <span class="uline-sm"><?php echo h($itr['fund_cluster']); ?></span>
                </td>
            </tr>
        </table>

        <!-- Start of the framed form body -->
        <div class="form-frame">
            <div class="header-box">
                <table class="hdr-grid">
                    <tr>
                        <td class="hb-left" style="vertical-align:top;">
                            <table class="label-table">
                                <tr><td class="label">From Accountable Officer/Agency/Fund Cluster : <span class="uline-inline"><?php echo h($itr['from_accountable']); ?></span></td></tr>
                                <tr><td class="label">To Accountable Officer/Agency/Fund Cluster : <span class="uline-inline"><?php echo h($itr['to_accountable']); ?></span></td></tr>
                            </table>
                        </td>
                        <td class="hb-right" style="text-align:left; padding-left:12px; vertical-align:top;">
                            <table class="label-table">
                                <tr><td class="label">ITR No : <span class="uline-inline"><?php echo h($itr['itr_no']); ?></span></td></tr>
                                <tr><td class="label">Date : <span class="uline-inline"><?php echo h($itr['itr_date']); ?></span></td></tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="transfer-row">
                            <div class="tt-head">
                                <strong>Transfer Type:</strong> <em>(check only one)</em>
                            </div>
                                <table class="tt-table" style="margin-left:12px;">
                                    <tr>
                                        <td>
                                            <span class="cbox">
                                                <span class="sq" style="background: <?php echo $isDonation ? '#000' : 'transparent'; ?>;"></span>
                                                <span style="margin-left:6px;">Donation</span>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="cbox">
                                                <span class="sq" style="background: <?php echo $isRelocate ? '#000' : 'transparent'; ?>;"></span>
                                                <span style="margin-left:6px;">Relocate</span>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <span class="cbox">
                                                <span class="sq" style="background: <?php echo $isReassignment ? '#000' : 'transparent'; ?>;"></span>
                                                <span style="margin-left:6px;">Reassignment</span>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="cbox">
                                                <span class="sq" style="background: <?php echo $isOthers ? '#000' : 'transparent'; ?>;"></span>
                                                <span style="margin-left:6px;">Others (Specify)</span>
                                                <span class="other-line"></span>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                        </td>
                    </tr>
                </table>
            </div>

            <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 12%">Date Acquired</th>
                    <th style="width: 10%">Item No.</th>
                    <th style="width: 14%">ICS No./Date</th>
                    <th style="width: 42%">Description</th>
                    <th style="width: 10%">Amount</th>
                    <th style="width: 12%">Condition of<br>Inventory</th>
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

                // Pad with blank rows to keep the table height consistent
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
            </tbody>
        </table>

            <div class="reasons-lines">
                <strong>Reason/s for Transfer:</strong>
                <div class="line"></div>
                <div class="line"></div>
                <div class="line"></div>
            </div>

            <div class="sig-section">
                <table>
                    <tr>
                        <th style="width:15%"></th>
                        <th style="width:25%">Approved by:</th>
                        <th style="width:25%">Released/Issued by:</th>
                        <th style="width:25%">Received by:</th>
                    </tr>
                    <tr>
                        <td>
                            <div class="sig-row"><div>Signature :</div></div>
                            <div class="sig-row"><div>Printed Name :</div></div></div>
                            <div class="sig-row"><div>Designation :</div></div></div>
                            <div class="sig-row"><div>Date :</div></div></div>
                        </td>
                        <td>
                            <!-- Keep first line for handwritten signature; then show stored name/designation/date -->
                            <div class="sig-row"><div></div><div class="sig-line"></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['approved_name'] ?? ''); ?></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['approved_designation'] ?? ''); ?></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['approved_date'] ?? ''); ?></div></div>
                        </td>
                        <td>
                            <div class="sig-row"><div></div><div class="sig-line"></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['released_name'] ?? ''); ?></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['released_designation'] ?? ''); ?></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['released_date'] ?? ''); ?></div></div>
                        </td>
                        <td>
                            <div class="sig-row"><div></div><div class="sig-line"></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['received_name'] ?? ''); ?></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['received_designation'] ?? ''); ?></div></div>
                            <div class="sig-row"><div></div><div class="sig-line"><?php echo h($itr['received_date'] ?? ''); ?></div></div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Optional: auto-open print dialog
        // window.addEventListener('load', function() { setTimeout(function(){ window.print(); }, 400); });
    </script>
</body>
</html>

