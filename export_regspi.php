<?php
require 'auth.php';
require 'config.php';
require_once 'functions.php';

// Get category filter (from POST or GET); treat "All" as none
$selected_category = isset($_POST['category']) ? trim($_POST['category']) : (isset($_GET['category']) ? trim($_GET['category']) : '');
if ($selected_category === 'All') { $selected_category = ''; }

// Build ICS-only dataset (mirrors regspi.php)
$rows = [];
$sql = "
    SELECT
        i.date_issued AS date,
        i.ics_no AS ics_rrsp_no,
        ii.stock_number AS property_no,
        COALESCE(NULLIF(sep.remarks, ''), sep.item_description, ii.description) AS item_description,
        COALESCE(sep.estimated_useful_life, ii.estimated_useful_life, '') AS useful_life,
        ii.quantity AS issued_qty,
        i.received_by AS issued_office,
        0 AS returned_qty,
        '' AS returned_office,
        0 AS reissued_qty,
        '' AS reissued_office,
        0 AS disposed_qty1,
        0 AS disposed_qty2,
        COALESCE(sep.amount, ii.unit_cost) * ii.quantity AS amount_total,
        '' AS remarks,
        0 AS row_type
    FROM ics i
    INNER JOIN ics_items ii ON ii.ics_id = i.ics_id
    LEFT JOIN semi_expendable_property sep 
        ON sep.semi_expendable_property_no = ii.stock_number COLLATE utf8mb4_general_ci
    WHERE ii.quantity > 0

    UNION ALL

    SELECT
        r.date_prepared AS date,
        r.rrsp_no AS ics_rrsp_no,
        rii.ics_no AS property_no,
        COALESCE(NULLIF(sep.remarks, ''), sep.item_description, rii.item_description) AS item_description,
        COALESCE(sep.estimated_useful_life, '') AS useful_life,
        0 AS issued_qty,
        '' AS issued_office,
        rii.quantity AS returned_qty,
        r.returned_by AS returned_office,
        0 AS reissued_qty,
        '' AS reissued_office,
        0 AS disposed_qty1,
        0 AS disposed_qty2,
        COALESCE(sep.amount, rii.unit_cost) * rii.quantity AS amount_total,
        r.remarks AS remarks,
        1 AS row_type
    FROM rrsp r
    INNER JOIN rrsp_items rii ON rii.rrsp_id = r.rrsp_id
    LEFT JOIN semi_expendable_property sep 
        ON sep.semi_expendable_property_no = rii.ics_no COLLATE utf8mb4_general_ci
    WHERE rii.quantity > 0
";

$binds = [];
$types = '';
if ($selected_category !== '' && columnExists($conn, 'semi_expendable_property', 'category')) {
    $sql .= " AND sep.category = ?";
    $binds[] = $selected_category;
    $types .= 's';
}
$sql .= " ORDER BY STR_TO_DATE(date, '%Y-%m-%d') ASC, row_type ASC, ics_rrsp_no ASC";

if (!empty($binds)) {
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$binds);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) { $rows[] = $r; }
            $res->close();
        }
        $stmt->close();
    }
} else {
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
        $res->close();
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$categoryLabel = ($selected_category !== '') ? $selected_category : 'All';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registry of Semi-Expendable Property Issued - Export</title>
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
            font-family: Arial, sans-serif;
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
            padding: 15px;
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
            <a href="regspi.php" class="back-btn">← Back to Registry</a>
            <hr style="margin:14px 0;">
    </div>

    <div class="form-container">
        <div class="annex-reference" style="text-align: right; font-style: italic; font-size: 11px; margin-bottom: 10px;">Annex A.4</div>
        <div class="form-title">REGISTRY OF SEMI-EXPENDABLE PROPERTY ISSUED</div>
        
        <div class="header-row">
            <div class="header-left">
                <div class="info-line">Entity Name: <span contenteditable="true">TESDA-CAR</span></div>
                <div class="info-line">Semi-expendable Property: <span contenteditable="true"><?php echo h($categoryLabel); ?></span></div>
            </div>
            <div class="header-right">
                <div class="info-line">Fund Cluster: <span contenteditable="true" style="min-width: 100px;">101</span></div>
                <div class="info-line">Sheet No.: <span contenteditable="true" class="sheet-number" id="sheetNumber" style="min-width: 100px;">1</span></div>
            </div>
        </div>
        
        <table class="form-header">
            <tr>
                <td class="label">Entity Name:</td>
                <td class="value" contenteditable="true">TESDA-CAR</td>
                <td style="width: 100px;"></td>
                <td class="label">Fund Cluster :</td>
                <td class="value" style="width: 80px;" contenteditable="true">101</td>
            </tr>
        </table>
        <table class="form-header" style="margin-top:0;">
            <tr>
                <td class="label">Semi-Expendable Property:</td>
                <td class="value" contenteditable="true"><?php echo h($categoryLabel); ?></td>
                <td colspan="3"></td>
            </tr>
        </table>

        <table class="main-table">
            <thead>
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
                            <td contenteditable="true"><?php echo h($r['date'] ? date('n/j/Y', strtotime($r['date'])) : ''); ?></td>
                            <td contenteditable="true"><?php echo h($r['ics_rrsp_no']); ?></td>
                            <td contenteditable="true"><?php echo h($r['property_no']); ?></td>
                            <td class="text-left" contenteditable="true"><?php echo h($r['item_description']); ?></td>
                            <td contenteditable="true"><?php echo h($r['useful_life']); ?></td>
                            <?php if ((float)$r['issued_qty'] > 0): ?>
                                <td contenteditable="true"><?php echo number_format((float)$r['issued_qty']); ?></td>
                                <td class="text-left" contenteditable="true"><?php echo h($r['issued_office']); ?></td>
                            <?php else: ?>
                                <td contenteditable="true"></td>
                                <td class="text-left" contenteditable="true"></td>
                            <?php endif; ?>
                            <?php if ((float)$r['returned_qty'] > 0): ?>
                                <td contenteditable="true"><?php echo number_format((float)$r['returned_qty']); ?></td>
                                <td class="text-left" contenteditable="true"><?php echo h($r['returned_office']); ?></td>
                            <?php else: ?>
                                <td contenteditable="true"></td>
                                <td class="text-left" contenteditable="true"></td>
                            <?php endif; ?>
                            <td contenteditable="true"></td>
                            <td contenteditable="true"></td>
                            <td contenteditable="true"></td>
                            <td contenteditable="true"></td>
                            <td class="text-right" contenteditable="true"><?php echo number_format((float)$r['amount_total'], 2); ?></td>
                            <td contenteditable="true"><?php echo h($r['remarks']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="15" style="text-align:center;">No records found.</td>
                    </tr>
                <?php endif; ?>
                <!-- Extra blank rows for manual edits -->
                <?php for ($i=0; $i<15; $i++): ?>
                <tr>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td class="text-left" contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td class="text-left" contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td contenteditable="true"></td>
                    <td class="text-right" contenteditable="true"></td>
                    <td contenteditable="true"></td>
                </tr>
                <?php endfor; ?>
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
        
        // Optional helper to add rows via Ctrl+Enter
        function addRow() {
            const tbody = document.querySelector('.main-table tbody');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td contenteditable="true"></td>
                <td contenteditable="true"></td>
                <td contenteditable="true"></td>
                <td class="text-left" contenteditable="true"></td>
                <td contenteditable="true"></td>
                <td contenteditable="true"></td>
                <td class="text-left" contenteditable="true"></td>
                <td contenteditable="true"></td>
                <td contenteditable="true"></td>
                <td contenteditable="true"></td>
                <td contenteditable="true"></td>
                <td contenteditable="true"></td>
                <td contenteditable="true"></td>
                <td class="text-right" contenteditable="true"></td>
                <td contenteditable="true"></td>`;
            tbody.appendChild(tr);
        }
        document.addEventListener('keydown', function(e){ if (e.ctrlKey && e.key === 'Enter') addRow(); });
    </script>
</body>
</html>