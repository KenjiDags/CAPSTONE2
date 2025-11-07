<?php
require 'config.php';
include 'sidebar.php';

// Fetch semi-expendable items from database
$items = [];
$sql = "SELECT category, item_description, semi_expendable_property_no, unit, amount, quantity_balance, remarks FROM semi_expendable_property ORDER BY item_description";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
} else {
    error_log('RPCSP DB error: ' . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RPCSP - Report on the Physical Count of Semi-Expendable Property</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <style>
        .rpcsp-page .content { padding: 16px; }
        .rpcsp-form { background: var(--white); border-radius: 12px; padding: 16px; box-shadow: 0 4px 15px var(--shadow-blue); }
        .rpcsp-header h2 { margin: 0 0 6px; }
        .rpcsp-meta { display: grid; grid-template-columns: repeat(auto-fit,minmax(250px,1fr)); gap: 10px; margin-top: 8px; }
        .rpcsp-meta label { font-weight: 600; margin-right: 6px; }
        .rpcsp-meta input[type="text"], .rpcsp-meta input[type="date"] { padding: 8px 10px; border: 1px solid var(--border-gray); border-radius: 8px; width: 100%; }
        .rpcsp-table-wrapper { overflow: auto; border: 1px solid var(--border-gray); border-radius: 10px; max-height: 520px; }
        .rpcsp-table { width: 100%; border-collapse: collapse; }
        .rpcsp-table th, .rpcsp-table td { border-bottom: 1px solid #e5e7eb; padding: 10px; text-align: center; }
        .rpcsp-table thead th { position: sticky; top: 0; background: #f8fafc; z-index: 2; }
        .currency { text-align: right; }
        .table-controls, .search-container { display:flex; align-items:center; gap:8px; margin: 10px 0; }
        .search-input-rpcsp { flex:1; padding: 10px; border:1px solid var(--border-gray); border-radius: 8px; }
        .export-btn { background: #2563eb; color: #fff; border: none; padding: 10px 14px; border-radius: 8px; cursor:pointer; }
    </style>
    <script>
        function openExport() {
            const params = new URLSearchParams({
                report_date: document.getElementById('report_date').value,
                fund_cluster: document.getElementById('fund_cluster').value,
                accountable_officer: document.getElementById('accountable_officer').value,
                official_designation: document.getElementById('official_designation').value,
                entity_name: document.getElementById('entity_name').value,
                assumption_date: document.getElementById('assumption_date').value,
            });
            window.location.href = './rpcsp_export.php?' + params.toString();
        }
    </script>
</head>
<body class="rpcsp-page">
    <div class="content">
        <div class="rpcsp-form">
            <div class="rpcsp-header">
                <h2>Report on the Physical Count of Semi-Expendable Property</h2>
                <div class="form-subtitle">(Semi-Expendable Property)</div>
                <div class="rpcsp-meta">
                    <div>
                        <label for="report_date">As at:</label>
                        <input type="date" id="report_date" name="report_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label for="fund_cluster">Fund Cluster</label>
                        <input type="text" id="fund_cluster" name="fund_cluster" placeholder="Enter Fund Cluster">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label>For which 
                            <input type="text" id="accountable_officer" name="accountable_officer" placeholder="Name of Accountable Officer">, 
                            <input type="text" id="official_designation" name="official_designation" placeholder="Official Designation">, 
                            <input type="text" id="entity_name" name="entity_name" placeholder="Entity Name">
                            is accountable, having assumed such accountability on 
                            <input type="date" id="assumption_date" name="assumption_date">.
                        </label>
                    </div>
                </div>
            </div>

            <div class="table-controls">
                <label for="row_limit">Show:</label>
                <select id="row_limit" aria-label="Rows to display">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="all">All</option>
                </select>
            </div>

            <div class="search-container">
                <input type="text" id="searchInput" class="search-input-rpcsp" placeholder="Search by property no., category, or description...">
            </div>

            <div class="rpcsp-table-wrapper">
                <table class="rpcsp-table" id="rpcsp-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Article</th>
                            <th rowspan="2">Item</th>
                            <th rowspan="2">Description</th>
                            <th rowspan="2">Property Number</th>
                            <th rowspan="2">Unit of Measure</th>
                            <th rowspan="2">Unit Value</th>
                            <th rowspan="2">Balance Per Card<br>(Quantity)</th>
                            <th rowspan="2">On Hand Per Count<br>(Quantity)</th>
                            <th colspan="2">Shortage/Overage</th>
                            <th rowspan="2">Remarks</th>
                        </tr>
                        <tr>
                            <th>Quantity</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="11" style="text-align:center; padding: 32px; color: var(--text-gray); font-style: italic;">No semi-expendable items found</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $row): ?>
                                <tr>
                                    <td>Semi-Expendable</td>
                                    <td><?= htmlspecialchars($row['category'] ?? '') ?></td>
                                    <td class="text-left"><?= htmlspecialchars($row['item_description'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['semi_expendable_property_no'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['unit'] ?? '') ?></td>
                                    <td class="currency"><?= htmlspecialchars(isset($row['amount']) ? number_format((float)$row['amount'], 2) : '') ?></td>
                                    <td><?= htmlspecialchars((string)($row['quantity_balance'] ?? '')) ?></td>
                                    <td></td>
                                    <td></td>
                                    <td class="currency"></td>
                                    <td><?= htmlspecialchars($row['remarks'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="signature-section" style="display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap: 12px; margin-top: 16px;">
                <div class="signature-box">
                    <h4>Certified Correct by:</h4>
                    <input type="text" class="signature-input" name="signature_name_1" placeholder="Signature over Printed Name">
                    <div class="signature-text">Signature over Printed Name of Inventory<br>Committee Chair and Members</div>
                </div>
                <div class="signature-box">
                    <h4>Approved by:</h4>
                    <input type="text" class="signature-input" name="signature_name_2" placeholder="Signature over Printed Name">
                    <div class="signature-text">Signature over Printed Name of Head of Agency/Entity<br>or Authorized Representative</div>
                </div>
                <div class="signature-box">
                    <h4>Verified by:</h4>
                    <input type="text" class="signature-input" name="signature_name_3" placeholder="Signature over Printed Name">
                    <div class="signature-text">Signature over Printed Name of COA Representative</div>
                </div>
            </div>

            <div style="text-align:center; margin-top: 18px;">
                <button type="button" class="export-btn" onclick="openExport()">📄 Export to PDF</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const limitSelect = document.getElementById('row_limit');
        const wrapper = document.querySelector('.rpcsp-table-wrapper');
        const table = document.getElementById('rpcsp-table');
        const thead = table.querySelector('thead');

        function applyLimit() {
            const val = limitSelect.value;
            const sampleRow = table.querySelector('tbody tr:not([style*="display: none"])');
            if (!wrapper || !thead || !sampleRow) return;
            const headerHeight = thead.getBoundingClientRect().height;
            const rowHeight = sampleRow.getBoundingClientRect().height;
            if (val === 'all') { wrapper.style.maxHeight = 'none'; }
            else { wrapper.style.maxHeight = `${headerHeight + rowHeight * parseInt(val,10)}px`; }
        }

        limitSelect.addEventListener('change', applyLimit);
        applyLimit();
        window.addEventListener('resize', applyLimit);

        // Searchbar
        document.getElementById('searchInput').addEventListener('keyup', function () {
            const q = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const category = (row.cells[1]?.textContent || '').toLowerCase();
                const desc = (row.cells[2]?.textContent || '').toLowerCase();
                const propno = (row.cells[3]?.textContent || '').toLowerCase();
                const unit = (row.cells[4]?.textContent || '').toLowerCase();
                const match = category.includes(q) || desc.includes(q) || propno.includes(q) || unit.includes(q);
                row.style.display = match ? '' : 'none';
            });
            applyLimit();
        });
    });
    </script>
</body>
</html>
