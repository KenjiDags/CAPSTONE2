<?php
require 'auth.php';
require 'config.php';
include 'sidebar.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_report'])) {
    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    $fund_cluster = $_POST['fund_cluster'] ?? '';
    $accountable_officer = $_POST['accountable_officer'] ?? '';
    $official_designation = $_POST['official_designation'] ?? '';
    $entity_name = $_POST['entity_name'] ?? 'TESDA Regional Office';
    $assumption_date = $_POST['assumption_date'] ?? null;
    $certified_by = $_POST['signature_name_1'] ?? '';
    $approved_by = $_POST['signature_name_2'] ?? '';
    $verified_by = $_POST['signature_name_3'] ?? '';
    
    // Insert into rpcppe table
    $stmt = $conn->prepare("INSERT INTO rpcppe (report_date, fund_cluster, accountable_officer, official_designation, entity_name, assumption_date, certified_by, approved_by, verified_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssss", $report_date, $fund_cluster, $accountable_officer, $official_designation, $entity_name, $assumption_date, $certified_by, $approved_by, $verified_by);
    
    if ($stmt->execute()) {
        $rpcppe_id = $conn->insert_id;
        
        // Insert items data
        if (isset($_POST['on_hand_count']) && is_array($_POST['on_hand_count'])) {
            $stmt_items = $conn->prepare("INSERT INTO rpcppe_items (rpcppe_id, ppe_id, on_hand_per_count, shortage_overage_qty, shortage_overage_value, remarks) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($_POST['on_hand_count'] as $ppe_id => $on_hand) {
                $shortage_qty = $_POST['shortage_qty'][$ppe_id] ?? 0;
                $shortage_value = $_POST['shortage_value'][$ppe_id] ?? 0.00;
                $remarks = $_POST['remarks'][$ppe_id] ?? '';
                
                // Only save if there's data entered
                if ($on_hand !== '' || $shortage_qty !== '' || $shortage_value !== '' || $remarks !== '') {
                    $stmt_items->bind_param("iiidds", $rpcppe_id, $ppe_id, $on_hand, $shortage_qty, $shortage_value, $remarks);
                    $stmt_items->execute();
                }
            }
            $stmt_items->close();
        }
        
        $_SESSION['success'] = "RPCPPE report saved successfully!";
        header("Location: RPCPPE.php");
        exit();
    } else {
        $error = "Error saving report: " . $conn->error;
    }
    $stmt->close();
}

// Fetch PPE items from database
$ppe_items = [];
$sql = "SELECT id, item_name, item_description, par_no, unit, amount, quantity, officer_incharge, custodian FROM ppe_property ORDER BY par_no";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $ppe_items[] = $row;
    }
} else {
    // Handle database error
    error_log("Database error: " . $conn->error);
    $ppe_items = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPCPPE - Report on Physical Count of Property, Plant and Equipment</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f1b3";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }
        
        /* RPCPPE specific styles */
        .rpcppe-form {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .rpcppe-header h2 {
            color: #1e293b;
            margin-bottom: 10px;
        }
        
        .form-subtitle {
            color: #64748b;
            font-style: italic;
            margin-bottom: 20px;
        }
        
        .rpcppe-meta {
            margin: 20px 0;
        }
        
        .rpcppe-meta-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .rpcppe-meta-row label {
            font-weight: 600;
            color: #334155;
        }
        
        .rpcppe-meta-row input[type="date"] {
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
            margin: 12px 0;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .table-controls label {
            font-weight: 600;
            color: #334155;
        }
        
        .table-controls select {
            padding: 8px 12px;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            cursor: pointer;
        }
        
        .search-container {
            margin: 12px 0;
        }
        
        .search-input-rpcppe {
            width: 100%;
            padding: 10px 16px;
            font-size: 14px;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
        }
        
        .search-input-rpcppe:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        
        .rpcppe-table-wrapper {
            overflow-x: auto;
            overflow-y: auto;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        
        .rpcppe-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .rpcppe-table thead {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
        }
        
        .rpcppe-table thead th {
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
        
        .rpcppe-table tbody td {
            padding: 12px;
            white-space: normal;
            word-wrap: break-word;
            vertical-align: top;
        }
        
        .rpcppe-table tbody tr {
            transition: background-color 0.2s ease;
        }
        
        .rpcppe-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .rpcppe-table tbody tr:hover {
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
        
        .currency::before {
            content: "₱";
            margin-right: 2px;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="container">
    <div class="rpcppe-form">
            <div class="rpcppe-header">
                <h2>Report on the Physical Count of Property, Plant and Equipment</h2>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div style="background: #d1fae5; border: 2px solid #10b981; color: #065f46; padding: 12px; border-radius: 8px; margin: 10px 0;">
                        ✓ <?= htmlspecialchars($_SESSION['success']) ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div style="background: #fee2e2; border: 2px solid #ef4444; color: #991b1b; padding: 12px; border-radius: 8px; margin: 10px 0;">
                        ✗ <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="rpcppe-meta">
                    <div class="rpcppe-meta-row">
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
                            <input type="text" id="accountable_officer" name="accountable_officer" placeholder="Name of Accountable Officer" style="min-width: 350px;">,    
                            <input type="text" id="official_designation" name="official_designation" placeholder="Official Designation" style="min-width: 250px;">,
                            <input type="text" id="entity_name" name="entity_name" value="TESDA Regional Office" placeholder="Entity Name" style="min-width: 250px;">
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
                                <input type="text" id="searchInput" class="search-input-rpcppe" placeholder="Search by PAR number, item name, or description...">
                    </div>

            <div class="rpcppe-table-wrapper">
                <table class="rpcppe-table" id="rpcppe-table">
                    <thead>
                        <tr>
                            <th rowspan="2"><i class="fas fa-cube"></i> Article</th>
                            <th rowspan="2"><i class="fas fa-align-left"></i> Description</th>
                            <th rowspan="2"><i class="fas fa-file-alt"></i> Property Number</th>
                            <th rowspan="2"><i class="fas fa-ruler"></i> Unit of Measure</th>
                            <th rowspan="2"><i class="fas fa-dollar-sign"></i> Unit Value</th>
                            <th rowspan="2"><i class="fas fa-clipboard"></i> Balance Per Card<br>(Quantity)</th>
                            <th rowspan="2"><i class="fas fa-hand-paper"></i> On Hand Per Count<br>(Quantity)</th>
                            <th colspan="2"><i class="fas fa-chart-line"></i> Shortage/Overage</th>
                            <th rowspan="2"><i class="fas fa-comment"></i> Remarks</th>
                        </tr>
                        <tr>
                            <th>Qty</th>
                            <th>Value</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        if (empty($ppe_items)) {
                            echo '<tr><td colspan="10" style="text-align: center; padding: 40px; color: var(--text-gray); font-style: italic;">No PPE items found</td></tr>';
                        } else {
                            foreach ($ppe_items as $item) {
                                $ppe_id = $item['id'];
                                echo '<tr>';
                                // Article 
                                echo '<td>PPE</td>';
                                // Description 
                                $description = htmlspecialchars($item['item_name'] ?? '');
                                if (!empty($item['item_description'])) {
                                    $description .= ' - ' . htmlspecialchars($item['item_description']);
                                }
                                echo '<td>' . $description . '</td>';
                                // Property Number (PAR No)
                                echo '<td>' . htmlspecialchars($item['par_no'] ?? '') . '</td>';
                                // Unit of Measure
                                echo '<td>' . htmlspecialchars($item['unit'] ?? '') . '</td>';
                                // Unit Value
                                echo '<td class="currency">' . htmlspecialchars($item['amount'] ?? '') . '</td>';
                                // Balance Per Card 
                                echo '<td>' . htmlspecialchars($item['quantity'] ?? '') . '</td>';
                                // Input cells for manual entry with name attributes
                                echo '<td><input type="number" name="on_hand_count[' . $ppe_id . ']" step="1" style="width: 80px; padding: 4px; border: 1px solid #cbd5e1; border-radius: 4px;" /></td>'; 
                                echo '<td><input type="number" name="shortage_qty[' . $ppe_id . ']" step="1" style="width: 80px; padding: 4px; border: 1px solid #cbd5e1; border-radius: 4px;" /></td>'; 
                                echo '<td><input type="number" name="shortage_value[' . $ppe_id . ']" step="0.01" style="width: 100px; padding: 4px; border: 1px solid #cbd5e1; border-radius: 4px;" /></td>'; 
                                echo '<td><input type="text" name="remarks[' . $ppe_id . ']" style="width: 150px; padding: 4px; border: 1px solid #cbd5e1; border-radius: 4px;" /></td>';
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
                
                <div style="text-align: center; margin-top: 30px; display: flex; gap: 15px; justify-content: center;">
                    <button type="submit" name="save_report" class="export-btn" style="background: #10b981;">💾 Save Report</button>
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
                certified_by: document.querySelector('input[name="signature_name_1"]').value,
                approved_by: document.querySelector('input[name="signature_name_2"]').value,
                verified_by: document.querySelector('input[name="signature_name_3"]').value,
            });
            window.location.href = './export_rpcppe.php?' + params.toString();
        }
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const limitSelect = document.getElementById('row_limit');
        const wrapper = document.querySelector('.rpcppe-table-wrapper');
        const table = document.querySelector('.rpcppe-table');
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
        const rows = document.querySelectorAll('#rpcppe-table tbody tr');

        rows.forEach(row => {
            const article = row.cells[0].textContent.toLowerCase();
            const description = row.cells[1].textContent.toLowerCase(); 
            const propertyNo = row.cells[2].textContent.toLowerCase();
            const unit = row.cells[3].textContent.toLowerCase();

            const match = article.includes(filter) || description.includes(filter) || propertyNo.includes(filter) || unit.includes(filter);
            row.style.display = match ? '' : 'none';
        });
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const firstRow = document.querySelector('.rpcppe-table thead tr:first-child');
        const secondRowThs = document.querySelectorAll('.rpcppe-table thead tr:nth-child(2) th');
        if (!firstRow || secondRowThs.length === 0) return;
        const firstRowHeight = firstRow.getBoundingClientRect().height;
        secondRowThs.forEach(th => {
            th.style.top = firstRowHeight + 'px';
        });
    });
    </script>

</body>
</html>
