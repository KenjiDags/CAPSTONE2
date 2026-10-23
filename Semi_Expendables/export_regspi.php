<?php
require_once __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/auth.php';
// Page-local RegSPI functions; saving reports does not change inventory.
function regspiH($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function regspiSchema(): string {
    return "CREATE TABLE IF NOT EXISTS regspi_reports (
        regspi_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        report_date DATE NOT NULL,
        entity_name VARCHAR(255) NOT NULL,
        fund_cluster VARCHAR(255) NOT NULL,
        category VARCHAR(255) NOT NULL,
        item_count INT UNSIGNED NOT NULL,
        snapshot LONGTEXT NOT NULL,
        created_by INT NOT NULL,
        save_token CHAR(64) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY regspi_save_token (save_token),
        KEY regspi_report_date (report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

function regspiEnsureTable(mysqli $conn): void {
    $conn->query(regspiSchema());
}

function regspiLoad(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare('SELECT * FROM regspi_reports WHERE regspi_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$report) return null;
    $report['data'] = json_decode($report['snapshot'], true, 512, JSON_THROW_ON_ERROR);
    return $report;
}

regspiEnsureTable($conn);
$id = filter_var($_GET['regspi_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { header('Location: regspi.php'); exit; }
$report = regspiLoad($conn, $id);
if (!$report) { http_response_code(404); exit('RegSPI document not found.'); }
$rows = $report['data']['rows'];
$metadata = $report['data']['metadata'];
$categoryLabel = $metadata['category'] ?: 'All';
function h($value) { return regspiH($value); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REGSPI-<?= str_pad((string)$id, 4, '0', STR_PAD_LEFT) ?> - Export</title>
    <style>
        /* Force print orientation to landscape */
        @page {
            size: landscape;
            margin: 10mm;
        }
        @media print {
            body { margin: 0; padding: 0; font-size: 11px; }
            .no-print { display: none; }
            .form-container { border: none; page-break-after: always; }
            .main-table { page-break-inside: auto; }
            .main-table tr { page-break-inside: avoid; page-break-after: auto; }
            .annex-reference { page-break-before: avoid; }
        }
        body {
            font-family: 'Century Gothic', Century Gothic, sans-serif;
            margin: 20px;
            font-size: 12px;
            background-color: #f5f5f5;
        }

        .export-instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .export-instructions h3 {
            margin: 0 0 10px 0;
            color: #856404;
        }

        .export-instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }

        .export-instructions p {
            margin: 10px 0 0 0;
            font-weight: bold;
            color: #856404;
        }

        .button-container {
            margin: 20px 0;
            display: flex;
            gap: 10px;
        }

        .print-button {
            background: #007cba;
            color: #fff;
            padding: 6px 14px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 6px;
            text-decoration: none;
        }

        .back-btn {
            background: #6c757d;
            color: #fff;
            padding: 6px 14px;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
        }

        .print-button:hover {
            background: #0056b3;
        }

        .back-btn:hover {
            background: #545b62;
        }

        .form-container {
            background: #fff;
            border: none;
            padding: 10px 12px;
            margin-bottom: 20px;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 5px;
        }

        .header-left {
            flex: 1;
        }

        .header-right {
            text-align: right;
        }

        .annex-reference {
            font-style: italic;
            font-size: 11px;
            margin: 0;
            padding: 0;
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

        .sheet-number {
            font-weight: bold;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }

        .form-title {
            text-align: center;
            font-weight: bold;
            margin: 15px 0;
            font-size: 13px;
            text-transform: uppercase;
        }

        .info-line {
            margin: 5px 0;
            font-size: 11px;
        }

        .info-line span {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 200px;
            padding: 0 5px;
        }

        .form-header {
            display: none;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
        }

        .main-table th,
        .main-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            text-align: center;
            vertical-align: middle;
            font-size: 10px;
        }

        .main-table thead tr.title-row th {
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
            padding: 10px 4px;
        }

        .main-table thead tr.meta-row td {
            padding: 3px 4px;
            font-size: 11px;
            text-align: left;
        }

        .main-table thead tr.meta-row td.meta-right {
            text-align: right;
        }

        .main-table td {
            font-size: 9px;
        }

        .main-table th {
            background: #fff;
            font-weight: normal;
        }

        .main-table .text-left {
            text-align: left;
        }

        .main-table .text-right {
            text-align: right;
        }

        /* Column widths */
        .date-col {
            width: 55px;
        }

        .ics-col {
            width: 65px;
        }

        .property-col {
            width: 70px;
        }

        .item-col {
            width: 200px;
        }

        .life-col {
            width: 55px;
        }

        .issued-qty-col {
            width: 40px;
        }

        .officer-col {
            width: 110px;
        }

        .returned-qty-col {
            width: 40px;
        }

        .returned-officer-col {
            width: 110px;
        }

        .reissued-qty-col {
            width: 40px;
        }

        .reissued-officer-col {
            width: 110px;
        }

        .disposed-qty-col {
            width: 40px;
        }

        .balance-col {
            width: 50px;
        }

        .amount-col {
            width: 70px;
        }

        .remarks-col {
            width: 70px;
        }
        
    </style>
<?php /* keep head clean */ ?>
<link rel="stylesheet" href="css/export-preview.css?v=<?= time() ?>">
<script src="js/export-preview.js?v=<?= time() ?>" defer data-export-preview data-preview-title="Registry of Property Issued Preview" data-preview-code="REGSPI" data-preview-back="Semi_Expendables/regspi.php" data-preview-width="1450"></script>
</head>
<body>
    <div class="no-print">
        <div class="export-instructions">
            <h3>Export Instructions</h3>
            <strong>To save as PDF:</strong>
            <ol>
                <li>Click the "Print/Save as PDF" button below</li>
                <li>In the print dialog, select "Save as PDF" or "Microsoft Print to PDF"</li>
                <li>Choose your destination and click "Save"</li>
            </ol>
            <p>For best results: Use Chrome or Edge for optimal PDF formatting.</p>
        </div>
            <button class="print-button" onclick="window.print()">Print/Save as PDF</button>
            <a href="Semi_Expendables/regspi.php" class="back-btn">← Back to Registry</a>
            <hr style="margin:14px 0;">
    </div>

    <div class="form-container">
        <p>REGSPI-<?= str_pad((string)$id, 4, '0', STR_PAD_LEFT) ?> &nbsp; Report Date: <?= h($metadata['report_date']) ?></p>
        <div class="annex-reference" style="text-align: right; font-style: italic; font-size: 11px;">Annex A.4</div>

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
                <tr class="title-row">
                    <th colspan="15">REGISTRY OF SEMI-EXPENDABLE PROPERTY ISSUED</th>
                </tr>
                <tr class="meta-row">
                    <td colspan="10">Entity Name: <span ><?= h($metadata['entity_name']) ?></span></td>
                    <td colspan="5" class="meta-right">Fund Cluster: <span  style="min-width: 100px;"><?= h($metadata['fund_cluster']) ?></span></td>
                </tr>
                <tr class="meta-row">
                    <td colspan="10">Semi-expendable Property: <span ><?php echo h($categoryLabel); ?></span></td>
                    <td colspan="5" class="meta-right">Sheet No.: <span  class="sheet-number" id="sheetNumber" style="min-width: 100px;">1</span></td>
                </tr>
                <tr>
                    <th rowspan="2" class="date-col">Date</th>
                    <th colspan="2">Reference</th>
                    <th rowspan="2" class="item-col">Item Description</th>
                    <th rowspan="2" class="life-col">Estimated Useful Life</th>
                    <th colspan="2">Issued</th>
                    <th colspan="2">Returned</th>
                    <th colspan="2">Re-issued</th>
                    <th colspan="1" class="disposed-qty-col">Disposed</th>
                    <th colspan="1" class="balance-col">Balance</th>
                    <th rowspan="2" class="amount-col">Amount</th>
                    <th rowspan="2" class="remarks-col">Remarks</th>
                </tr>
                <tr>
                    <th class="ics-col">ICS/RRSP No.</th>
                    <th class="property-col">Semi-Expendable Property No.</th>
                    <th class="issued-qty-col">Qty.</th>
                    <th class="officer-col">Office/Officer</th>
                    <th class="returned-qty-col">Qty.</th>
                    <th class="returned-officer-col">Office/Officer</th>
                    <th class="reissued-qty-col">Qty.</th>
                    <th class="reissued-officer-col">Office/Officer</th>
                    <th class="disposed-qty-col">Qty.</th>
                    <th class="balance-col">Qty.</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td ><?php echo h($r['date'] ? date('n/j/Y', strtotime($r['date'])) : ''); ?></td>
                            <td ><?php echo h($r['ics_rrsp_no']); ?></td>
                            <td ><?php echo h($r['property_no']); ?></td>
                            <td class="text-left" ><?php echo h($r['item_description']); ?></td>
                            <td ><?php echo h($r['useful_life']); ?></td>
                            <?php if ((float)$r['issued_qty'] > 0): ?>
                                <td ><?php echo number_format((float)$r['issued_qty']); ?></td>
                                <td class="text-left" ><?php echo h($r['issued_office']); ?></td>
                            <?php else: ?>
                                <td ></td>
                                <td class="text-left" ></td>
                            <?php endif; ?>
                            <?php if ((float)$r['returned_qty'] > 0): ?>
                                <td ><?php echo number_format((float)$r['returned_qty']); ?></td>
                                <td class="text-left" ><?php echo h($r['returned_office']); ?></td>
                            <?php else: ?>
                                <td ></td>
                                <td class="text-left" ></td>
                            <?php endif; ?>
                            <td><?= (int)$r['reissued_qty'] ?></td>
                            <td><?= h($r['reissued_office']) ?></td>
                            <td><?= (int)$r['disposed_qty1'] ?></td>
                            <td><?= (int)$r['balance_qty'] ?></td>
                            <td class="text-right" ><?php echo number_format((float)$r['amount_total'], 2); ?></td>
                            <td ><?php echo h($r['remarks']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="15" style="text-align:center;">No records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Auto-increment sheet number on new pages when printing
        window.addEventListener('beforeprint', function() {
            const containers = document.querySelectorAll('.form-container');
            containers.forEach((container, index) => {
                const sheetNum = container.querySelector('.sheet-number');
                if (sheetNum) {
                    sheetNum.textContent = index + 1;
                }
            });
        });
    </script>
</body>
</html>