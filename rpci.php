<?php
require 'auth.php';
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

// Fetch logged-in user's full_name for Accountable Officer
$current_user_full_name = '';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $current_user_full_name = $user_data['full_name'] ?? '';
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPCI - Report on Physical Count of Inventories</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f46d";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }
        
        /* RPCI specific styles */
        .rpci-form {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .rpci-header h2 {
            color: #1e293b;
            margin-bottom: 10px;
        }
        
        .form-subtitle {
            color: #64748b;
            font-style: italic;
            margin-bottom: 20px;
        }
        
        .rpci-meta {
            margin: 20px 0;
        }
        
        .rpci-meta-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .rpci-meta-row label {
            font-weight: 600;
            color: #334155;
        }
        
        .rpci-meta-row input[type="date"] {
            padding: 8px 12px;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-fields {
            display: grid;
            gap: 15px;
            margin: 20px 0;
        }
        
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .field-group label {
            font-weight: 600;
            color: #334155;
        }
        
        .field-group input[type="text"],
        .field-group input[type="date"] {
            padding: 10px 12px;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .field-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        
        .table-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .table-controls label {
            font-weight: 600;
            color: #334155;
        }
        
        .table-controls select {
            padding: 8px 8px;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            cursor: pointer;
        }
        
        .search-container {
            margin: 12px 0;
        }
        
        .search-input-rpci:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        
        .rpci-table-wrapper {
            overflow-x: auto;
            overflow-y: auto;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        
        .rpci-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .rpci-table thead {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
        }
        
        .rpci-table thead th {
            position: sticky !important;
            top: 0;
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%) !important;
            color: white;
            z-index: 2;
            padding: 12px 8px;
            white-space: normal;
            word-wrap: break-word;
            vertical-align: middle;
            line-height: 1.4;
        }
        
        .rpci-table tbody td {
            padding: 12px;
            white-space: normal;
            word-wrap: break-word;
            vertical-align: top;
        }
        
        .rpci-table tbody tr {
            transition: background-color 0.2s ease;
        }
        
        .rpci-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .rpci-table tbody tr:hover {
            background-color: #e0f2fe;
        }
        
        .signature-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .signature-box {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
        }
        
        .signature-box h4 {
            color: #334155;
            margin-bottom: 10px;
        }
        
        .signature-input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .signature-text {
            font-size: 0.85em;
            color: #64748b;
            line-height: 1.4;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="container">
    <div class="rpci-form">
            <div class="rpci-header">
                <h2>Report on the Physical Count of Inventories</h2>

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
                            <input type="text" id="accountable_officer" name="accountable_officer" value="<?= htmlspecialchars($current_user_full_name) ?>" placeholder="Name of Accountable Officer" style="min-width: 350px;">,    
                            <input type="text" id="official_designation" name="official_designation" placeholder="Official Designation" style="min-width: 250px;">,
                            <input type="text" id="entity_name" name="entity_name" value="TESDA Regional Office" placeholder="Entity Name" style="min-width: 250px;">
                            is accountable, having assumed such accountability on
                            <input type="date" id="assumption_date" name="assumption_date">
                            .
                            </label>
                        </div>
                    </div>
                </div>

                <div class="table-controls" style="display:flex; gap:12px; align-items:center; justify-content: flex-start;">
                    <label for="row_limit" style="color: #001F80;">Show:</label>
                    <select id="row_limit" aria-label="Rows to display">
                        <option value="5" selected>5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="all">All</option>
                    </select>
                    <div class="search-container" style="margin-left: 24px;">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search by stock number, item, or unit...">
                    </div>
                </div>

            <div class="rpci-table-wrapper">
                <table class="rpci-table" id="rpci-table">
                    <thead>
                        <tr>
                            <th rowspan="2"><i class="fas fa-list"></i> Article</th>
                            <th rowspan="2"><i class="fas fa-tag"></i> Item</th>
                            <th rowspan="2"><i class="fas fa-align-left"></i> Description</th>
                            <th rowspan="2"><i class="fas fa-barcode"></i> Stock Number</th>
                            <th rowspan="2"><i class="fas fa-ruler"></i> Unit of Measure</th>
                            <th rowspan="2"><i class="fas fa-dollar-sign"></i> Unit Value</th>
                            <th rowspan="2"><i class="fas fa-clipboard"></i> Balance Per Card<br>(Quantity)</th>
                            <th rowspan="2"><i class="fas fa-hand-paper"></i> On Hand Per Count<br>(Quantity)</th>
                            <th colspan="2"><i class="fas fa-chart-line"></i> Shortage/Overage</th>
                            <th rowspan="2"><i class="fas fa-comment"></i> Remarks</th>
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
                                echo '<td class="currency">₱ ' . htmlspecialchars($item['unit_cost' ?? '']) . '</td>'; // Unit Value
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