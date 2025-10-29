<?php
require 'config.php';
include 'sidebar.php';

// Fetch inventory items from database
$inventory_items = [];
$sql = "SELECT item_name, description, stock_number, unit, unit_cost FROM items ORDER BY item_name";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inventory_items[] = $row;
    }
} else {
    // Handle database error
    error_log("Database error: " . $conn->error);
    $inventory_items = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPCI - Report on Physical Count of Inventories</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
</head>
<body class="rpci-page">
    <div class="content">
        <div class="rpci-form">
            <div class="rpci-header">
                <h2>Report on the Physical Count of Inventories</h2>
                <div class="form-subtitle">(Type of Inventory Item)</div>

                <div class="rpci-meta">
                    <div class="rpci-meta-row">
                        <label for="report_date">As at:</label>
                        <input type="date" id="report_date" name="report_date" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
            
            <form method="POST" action="">
                <div class="form-fields">
                    <div class="field-group">
                        <label for="fund_cluster">Fund Cluster:
                            <input type="text" id="fund_cluster" name="fund_cluster" placeholder="Enter Fund Cluster">
                        </label>
                        
                    </div>

                    <div class="field-group" style="grid-column: 1 / -1;">
                        <label>For which: 
                            <input type="text" id="accountable_officer" name="accountable_officer" placeholder="Name of Accountable Officer">,    
                            <input type="text" id="official_designation" name="official_designation" placeholder="Official Designation">,
                            <input type="text" id="entity_name" name="entity_name" placeholder="Entity Name">
                            is accountable, having assumed such accountability on
                            <input type="date" id="assumption_date" name="assumption_date">
                            .
                            </label>
                        </div>
                    </div>
                </div>

                <div class="table-controls" style="margin: 12px 0; display:flex; gap:8px; align-items:center;">
                    <label for="row_limit">Show:</label>
                    <select id="row_limit" aria-label="Rows to display">
                        <option value="5" selected>5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="all">All</option>
                    </select>
                </div>

                    <div class="search-container">
                                <input type="text" id="searchInput" class="search-input-rpci" placeholder="Search by stock number, item, or unit...">
                    </div>

            <div class="rpci-table-wrapper">
                <table class="rpci-table" id="rpci-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Article</th>
                            <th rowspan="2">Item</th>
                            <th rowspan="2">Description</th>
                            <th rowspan="2">Stock Number</th>
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
                        <?php
                        if (empty($inventory_items)) {
                            echo '<tr><td colspan="10" style="text-align: center; padding: 40px; color: var(--text-gray); font-style: italic;">No inventory items found</td></tr>';
                        } else {
                            foreach ($inventory_items as $item) {
                                echo '<tr>';
                                // Article column with default value "Office Supplies"
                                echo '<td>Office Supplies</td>';
                                //Item Name
                                echo '<td>' . htmlspecialchars($item['item_name'] ?? '') . '</td>';
                                // Description from database
                                echo '<td>' . htmlspecialchars($item['description'] ?? '') . '</td>';
                                // Stock Number from database
                                echo '<td>' . htmlspecialchars($item['stock_number'] ?? '') . '</td>';
                                // Empty cells for manual input or future database integration
                                echo '<td>' . htmlspecialchars($item['unit' ?? '']) . '</td>'; // Unit of Measure
                                echo '<td class="currency">' . htmlspecialchars($item['unit_cost' ?? '']) . '</td>'; // Unit Value
                                echo '<td></td>'; // Balance Per Card
                                echo '<td></td>'; // On Hand Per Count
                                echo '<td></td>'; // Shortage/Overage Quantity
                                echo '<td class="currency"></td>'; // Shortage/Overage Value
                                echo '<td></td>'; // Remarks
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
                </div>
                
                <div class="signature-section">
                    <div class="signature-box">
                        <h4>Certified Correct by:</h4>
                        <input type="text" class="signature-input" name="signature_name_1" placeholder="Signature over Printed Name">
                        <div class="signature-text">
                            Signature over Printed Name of Inventory<br>
                            Committee Chair and Members
                        </div>
                    </div>
                    
                    <div class="signature-box">
                        <h4>Approved by:</h4>
                        <input type="text" class="signature-input" name="signature_name_2" placeholder="Signature over Printed Name">
                        <div class="signature-text">
                            Signature over Printed Name of Head of Agency/Entity<br>
                            or Authorized Representative
                        </div>
                    </div>
                    
                    <div class="signature-box">
                        <h4>Verified by:</h4>
                        <input type="text" class="signature-input" name="signature_name_3" placeholder="Signature over Printed Name">
                        <div class="signature-text">
                            Signature over Printed Name of COA Representative
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="button" class="export-btn" onclick="openExport()">📄 Export to PDF</button>
                </div>
            </form>
        </div>
    </div>
    
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
            window.location.href = './rpci_export.php?' + params.toString();
        }
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const limitSelect = document.getElementById('row_limit');
        const wrapper = document.querySelector('.rpci-table-wrapper');
        const table = document.querySelector('.rpci-table');
        const thead = table.querySelector('thead');

        function applyLimit() {
            const val = limitSelect.value;
            const sampleRow = table.querySelector('tbody tr:not([style*="display: none"])');
            if (!wrapper || !thead || !sampleRow) return;

            const headerHeight = thead.getBoundingClientRect().height;
            const rowHeight = sampleRow.getBoundingClientRect().height;

            if (val === 'all') {
                wrapper.style.maxHeight = 'none';
            } else {
                const count = parseInt(val, 10);
                wrapper.style.maxHeight = `${headerHeight + rowHeight * count}px`;
            }
        }

        limitSelect.addEventListener('change', applyLimit);
        applyLimit();

        window.addEventListener('resize', applyLimit);
    });

    // Searchbar JS
        document.getElementById('searchInput').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#rpci-table tbody tr');

        rows.forEach(row => {
            const stockNo = row.cells[0].textContent.toLowerCase();
            const item_name = row.cells[1].textContent.toLowerCase(); 
            const description = row.cells[2].textContent.toLowerCase();
            const unit = row.cells[3].textContent.toLowerCase();

            const match = stockNo.includes(filter) || item_name.includes(filter) || description.includes(filter) || unit.includes(filter);
            row.style.display = match ? '' : 'none';
        });
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const firstRow = document.querySelector('.rpci-table thead tr:first-child');
        const secondRowThs = document.querySelectorAll('.rpci-table thead tr:nth-child(2) th');
        if (!firstRow || secondRowThs.length === 0) return;
        const firstRowHeight = firstRow.getBoundingClientRect().height;
        secondRowThs.forEach(th => {
            th.style.top = firstRowHeight + 'px';
        });
    });
    </script>

</body>
</html>