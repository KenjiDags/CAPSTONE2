<?php
require 'config.php';
require_once 'functions.php';
include 'sidebar.php';

// Load data for Registry of Semi-Expendable Property Issued (REGSPI)
// Primary source: semi_expendable_history (captures issued/returned/reissued/disposed)
// Fallback: ICS items joined to semi_expendable_property when history is not available

$rows = [];

// Read selected category from query string ("All" or empty => no filtering)
$selected_category = isset($_GET['category']) ? trim($_GET['category']) : '';
if ($selected_category === 'All') { $selected_category = ''; }

// Prefer history if the table exists
$has_history = false;
try {
    $chk = $conn->query("SHOW TABLES LIKE 'semi_expendable_history'");
    $has_history = $chk && $chk->num_rows > 0;
    if ($chk) { $chk->close(); }
} catch (Throwable $e) { $has_history = false; }

if ($has_history) {
    $sql = "
        SELECT 
            h.date,
            h.ics_rrsp_no,
            sep.semi_expendable_property_no AS property_no,
            COALESCE(NULLIF(sep.remarks, ''), sep.item_description) AS item_description,
            COALESCE(sep.estimated_useful_life, '') AS useful_life,
            h.quantity_issued AS issued_qty,
            h.office_officer_issued AS issued_office,
            h.quantity_returned AS returned_qty,
            h.office_officer_returned AS returned_office,
            h.quantity_reissued AS reissued_qty,
            h.office_officer_reissued AS reissued_office,
            h.quantity_disposed AS disposed_qty1,
            0 AS disposed_qty2,
            h.quantity_balance AS balance_qty,
            COALESCE(h.amount_total, ROUND(COALESCE(sep.amount, h.amount) * h.quantity, 2)) AS amount_total,
            h.remarks
        FROM semi_expendable_history h
        INNER JOIN semi_expendable_property sep ON sep.id = h.semi_id
        WHERE 1=1";
    // Apply category filter if column exists and a category is selected
    $binds = [];
    $types = '';
    if ($selected_category !== '' && columnExists($conn, 'semi_expendable_property', 'category')) {
        $sql .= " AND sep.category = ?";
        $binds[] = $selected_category;
        $types .= 's';
    }
    $sql .= " ORDER BY h.date DESC, h.id DESC";

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
}

// Fallback: derive from ICS rows if no history rows found
if (!$has_history || count($rows) === 0) {
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
            GREATEST(0, COALESCE(sep.quantity, 0) - (COALESCE(sep.quantity_issued, 0) + COALESCE(sep.quantity_reissued, 0) + COALESCE(sep.quantity_disposed, 0))) AS balance_qty,
            COALESCE(sep.amount, ii.unit_cost) * ii.quantity AS amount_total,
            '' AS remarks
        FROM ics i
        INNER JOIN ics_items ii ON ii.ics_id = i.ics_id
        LEFT JOIN semi_expendable_property sep 
            ON sep.semi_expendable_property_no = ii.stock_number COLLATE utf8mb4_general_ci
        WHERE ii.quantity > 0";
    $binds2 = [];
    $types2 = '';
    if ($selected_category !== '' && columnExists($conn, 'semi_expendable_property', 'category')) {
        $sql .= " AND sep.category = ?";
        $binds2[] = $selected_category;
        $types2 .= 's';
    }
    $sql .= " ORDER BY i.date_issued DESC, ii.ics_item_id DESC";

    if (!empty($binds2)) {
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param($types2, ...$binds2);
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
}

// Final pass: scrub boilerplate remarks like "Initial Receipt/Report"
if (!empty($rows)) {
    foreach ($rows as &$__r) {
        $rm = isset($__r['remarks']) ? trim((string)$__r['remarks']) : '';
        if ($rm !== '' && preg_match('/initial\s+(receipt|report)/i', $rm)) {
            $__r['remarks'] = '';
        }
    }
    unset($__r);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registry of Semi-Expendable Property Issued</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/fixed_head.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="js/regspi_script.js" defer></script>
    <style>
        /* Make the main REGSPI table scrollable */
        .rsep-table-container { margin-top: 0; }
        .rsep-table-wrapper {
            max-height: 520px; /* adjust as needed */
            overflow-y: auto; /* wrapper is the vertical scroller */
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-top: 0; /* remove top border so header sits flush */
            border-radius: 0 0 8px 8px; /* no rounded corners on top to avoid white arc */
            -webkit-overflow-scrolling: touch;
            scrollbar-gutter: stable; /* prevent layout shift when scrollbar appears */
            background: var(--white); /* match header to remove any visible top gap */
            position: relative; /* anchor for sticky */
        }
        .rsep-table-container, .rsep-table-wrapper, .rsep-table {
            border-top-left-radius: 0 !important;
            border-top-right-radius: 0 !important;
        }
    .rsep-table { width: 100%; border-collapse: collapse; }
    /* Critical: allow sticky header to work by not clipping the table box */
    .rsep-table { overflow: visible !important; border-radius: 0 !important; background: transparent !important; margin-top: 0 !important; }
        .rsep-table th, .rsep-table td { border: 0px solid #e5e7eb; padding: 6px 8px; }

        /* Sticky multi-row header inside scrollable wrapper (reference: rpci) */
        .rsep-table thead th {
            position: sticky !important;
            top: 0; /* first row default (others adjusted via JS offsets) */
            z-index: 2;
            background: var(--blue-gradient) !important; /* keep the blue gradient while sticky */
            color: #fff;
            height: 40px;
            line-height: 1.2;
        }
        /* Ensure top stacking order for grouped header row */
    .rsep-table thead tr:nth-child(1) th { z-index: 5; }
    .rsep-table thead tr:nth-child(2) th { z-index: 4; }
    .rsep-table thead tr:nth-child(3) th { z-index: 3; }

        /* Add subtle separation while scrolling */
        .rsep-table-wrapper.scrolling .rsep-table thead th {
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        /* reduce visual gap between head and body */
        .rsep-table-wrapper .rsep-table tbody tr:first-child td { border-top: 2px solid #002d80; }
    </style>
</head>
<body>
    <div class="content">
        <h2>Registry of Semi-Expendable Property Issued</h2>
        
        <div class="search-container">
        <button class="add-btn" id="addItemsBtn">
                <i class="fas fa-plus"></i> Add new Item
            </button>
            <input type="text" id="searchInput" class="search-input" placeholder="Search by stock number, description, or unit...">
        </div>

        <div class="container">
            <!-- Header Form -->
            <div class="rsep-form">
                <div class="form-fields">
                    <div class="field-group">
                        <label for="entity-name">Entity Name:</label>
                        <input type="text" id="entity-name" name="entity_name" value="TESDA-CAR">
                    </div>
                    <div class="field-group">
                        <label for="fund-cluster">Fund Cluster:</label>
                        <input type="text" id="fund-cluster" name="fund_cluster" value="101">
                    </div>
                    <div class="field-group">
                        <label for="semi-expendable-property">Semi-Expendable Property:</label>
                        <select id="semi-expendable-property" name="semi_expendable_property">
                            <?php
                            // Build category list
                            $categories = [];
                            if (function_exists('columnExists') && columnExists($conn, 'semi_expendable_property', 'category')) {
                                if ($resCat = $conn->query("SELECT DISTINCT category FROM semi_expendable_property WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC")) {
                                    while ($rowCat = $resCat->fetch_assoc()) { $categories[] = $rowCat['category']; }
                                    $resCat->close();
                                }
                            } else {
                                // Fallback baseline categories if column missing
                                $categories = ['ICT Equipment','Office Equipment','Other PPE'];
                            }
                            // Render options with persistence; default to All when no selection
                            echo '<option value="All"' . ($selected_category === '' ? ' selected' : '') . '>All</option>';
                            foreach ($categories as $cat) {
                                $sel = ($selected_category !== '' && strcasecmp($selected_category, $cat) === 0) ? ' selected' : '';
                                echo '<option value="' . htmlspecialchars($cat) . '"' . $sel . '>' . htmlspecialchars($cat) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            

            <!-- Controls copied from RPCI -->
            <div class="table-controls" style="margin: 12px 0; display:flex; gap:8px; align-items:center;">
                <label for="rsep_row_limit">Show:</label>
                <select id="rsep_row_limit" aria-label="Rows to display">
                    <option value="5" selected>5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="all">All</option>
                </select>
            </div>

            <!-- Main Table (single table like RPCI) -->
            <div class="rsep-table-container">
                <div class="rsep-table-wrapper">
                    <table class="rsep-table" id="rsep-table">
                        <thead>
                            <tr>
                                <th rowspan="3">Date</th>
                                <th colspan="2">Reference</th>
                                <th rowspan="3">Item Description</th>
                                <th rowspan="3">Estimated Useful Life</th>
                                <th colspan="2">Issued</th>
                                <th colspan="2">Returned</th>
                                <th colspan="2">Re-issued</th>
                                <th colspan="2">Disposed</th>
                                <th rowspan="3">Balance Qty.</th>
                                <th rowspan="3">Amount (TOTAL)</th>
                                <th rowspan="3">Remarks</th>
                            </tr>
                            <tr>
                                <th rowspan="2">ICS/RRSP No.</th>
                                <th rowspan="2">Semi-Expendable Property No.</th>
                                <th rowspan="2">Qty.</th>
                                <th rowspan="2">Office/Officer</th>
                                <th rowspan="2">Qty.</th>
                                <th rowspan="2">Office/Officer</th>
                                <th rowspan="2">Qty.</th>
                                <th rowspan="2">Office/Officer</th>
                                <th rowspan="2">Qty.</th>
                                <th rowspan="2">Qty.</th>
                            </tr>
                            <tr>
                                <!-- Empty row for proper header structure -->
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                                <?php if (count($rows) > 0): ?>
                                    <?php foreach ($rows as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['date']); ?></td>
                                            <td><?php echo htmlspecialchars($row['ics_rrsp_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['property_no']); ?></td>
                                            <td><?php echo htmlspecialchars($row['item_description']); ?></td>
                                            <td><?php echo htmlspecialchars($row['useful_life']); ?></td>
                                            <td><?php echo (int)($row['issued_qty'] ?? 0); ?></td>
                                            <td><?php echo htmlspecialchars($row['issued_office'] ?? '-'); ?></td>
                                            <td><?php echo (int)($row['returned_qty'] ?? 0); ?></td>
                                            <td><?php echo htmlspecialchars($row['returned_office'] ?? '-'); ?></td>
                                            <td><?php echo (int)($row['reissued_qty'] ?? 0); ?></td>
                                            <td><?php echo htmlspecialchars($row['reissued_office'] ?? '-'); ?></td>
                                            <td><?php echo (int)($row['disposed_qty1'] ?? 0); ?></td>
                                            <td><?php echo (int)($row['disposed_qty2'] ?? 0); ?></td>
                                            <td><?php echo (int)($row['balance_qty'] ?? 0); ?></td>
                                            <td><?php echo '₱' . number_format((float)($row['amount_total'] ?? 0), 2); ?></td>
                                            <td><?php echo htmlspecialchars($row['remarks'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="16" style="text-align:center;">No records found.</td></tr>
                                <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
                <div style="text-align: center; margin-top: 30px;">
                    <button type="button" class="export-btn" onclick="openExport()">📄 Export to PDF</button>
                </div>

    <!-- Modal for Adding Items -->
    <div class="modal-overlay" id="itemModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Item</h3>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <form class="modal-form" id="itemForm">
                    <div class="modal-field">
                        <label for="itemDate">Date:</label>
                        <input type="date" id="itemDate" name="date" required>
                    </div>
                    
                    <div class="modal-field">
                        <label for="icsRrspNo">ICS/RRSP No.:</label>
                        <input type="text" id="icsRrspNo" name="ics_rrsp_no" placeholder="Enter ICS/RRSP Number" required>
                    </div>
                    
                    <div class="modal-field">
                        <label for="propertyNo">Semi-Expendable Property No.:</label>
                        <input type="text" id="propertyNo" name="property_no" placeholder="Enter Property Number" required>
                    </div>
                    
                    <div class="modal-field full-width">
                        <label for="itemDescription">Item Description:</label>
                        <textarea id="itemDescription" name="item_description" placeholder="Enter detailed item description" required></textarea>
                    </div>
                    
                    <div class="modal-field">
                        <label for="usefulLife">Estimated Useful Life:</label>
                        <input type="text" id="usefulLife" name="useful_life" placeholder="e.g., 5 Years" required>
                    </div>
                    
                    <div class="modal-field">
                        <label for="issuedQty">Issued Quantity:</label>
                        <input type="number" id="issuedQty" name="issued_qty" placeholder="0" min="0" required>
                    </div>
                    
                    <div class="modal-field">
                        <label for="issuedOffice">Issued Office/Officer:</label>
                        <input type="text" id="issuedOffice" name="issued_office" placeholder="Enter office or officer name">
                    </div>
                    
                    <div class="modal-field">
                        <label for="returnedQty">Returned Quantity:</label>
                        <input type="number" id="returnedQty" name="returned_qty" placeholder="0" min="0" value="0">
                    </div>
                    
                    <div class="modal-field">
                        <label for="returnedOffice">Returned Office/Officer:</label>
                        <input type="text" id="returnedOffice" name="returned_office" placeholder="Enter office or officer name">
                    </div>
                    
                    <div class="modal-field">
                        <label for="reissuedQty">Re-issued Quantity:</label>
                        <input type="number" id="reissuedQty" name="reissued_qty" placeholder="0" min="0" value="0">
                    </div>
                    
                    <div class="modal-field">
                        <label for="reissuedOffice">Re-issued Office/Officer:</label>
                        <input type="text" id="reissuedOffice" name="reissued_office" placeholder="Enter office or officer name">
                    </div>
                    
                    <div class="modal-field">
                        <label for="disposedQty1">Disposed Quantity 1:</label>
                        <input type="number" id="disposedQty1" name="disposed_qty1" placeholder="0" min="0" value="0">
                    </div>
                    
                    <div class="modal-field">
                        <label for="disposedQty2">Disposed Quantity 2:</label>
                        <input type="number" id="disposedQty2" name="disposed_qty2" placeholder="0" min="0" value="0">
                    </div>
                    
                    <div class="modal-field">
                        <label for="balanceQty">Balance Quantity:</label>
                        <input type="number" id="balanceQty" name="balance_qty" placeholder="0" min="0" readonly>
                    </div>
                    
                    <div class="modal-field">
                        <label for="amount">Amount (Total):</label>
                        <input type="text" id="amount" name="amount" placeholder="₱0.00">
                    </div>
                    
                    <div class="modal-field full-width">
                        <label for="remarks">Remarks:</label>
                        <textarea id="remarks" name="remarks" placeholder="Enter any additional remarks or notes"></textarea>
                    </div>
                </form>
                
                <div class="modal-actions">
                    <button type="button" class="modal-btn secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="modal-btn primary" id="addItemBtn">Add Item</button>
                </div>
                
            </div>
        </div>
    </div>
    
    

    <script>
        // Modal functionality
    const modal = document.getElementById('itemModal');
    const addItemsBtn = document.getElementById('addItemsBtn');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const addItemBtn = document.getElementById('addItemBtn');
        const itemForm = document.getElementById('itemForm');
        const tableBody = document.getElementById('tableBody');

        // Open modal
        addItemsBtn.addEventListener('click', function() {
            modal.classList.add('show');
            // Set today's date as default
            document.getElementById('itemDate').valueAsDate = new Date();
        });

        // Close modal functions
        function closeModalFunc() {
            modal.classList.remove('show');
            itemForm.reset();
        }

        closeModal.addEventListener('click', closeModalFunc);
        cancelBtn.addEventListener('click', closeModalFunc);

        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModalFunc();
            }
        });

        // Auto-calculate balance quantity
        function calculateBalance() {
            const issued = parseInt(document.getElementById('issuedQty').value) || 0;
            const returned = parseInt(document.getElementById('returnedQty').value) || 0;
            const reissued = parseInt(document.getElementById('reissuedQty').value) || 0;
            const disposed1 = parseInt(document.getElementById('disposedQty1').value) || 0;
            const disposed2 = parseInt(document.getElementById('disposedQty2').value) || 0;
            
            const balance = issued - returned - reissued - disposed1 - disposed2;
            document.getElementById('balanceQty').value = Math.max(0, balance);
        }

        // Add event listeners for auto-calculation
        ['issuedQty', 'returnedQty', 'reissuedQty', 'disposedQty1', 'disposedQty2'].forEach(id => {
            document.getElementById(id).addEventListener('input', calculateBalance);
        });

        // Add item to table
        addItemBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(itemForm);
            const data = Object.fromEntries(formData);
            
            // Validate required fields
            if (!data.date || !data.ics_rrsp_no || !data.property_no || !data.item_description || !data.useful_life || !data.issued_qty) {
                alert('Please fill in all required fields.');
                return;
            }

            // Create new row
            const newRow = tableBody.insertRow();
            newRow.innerHTML = `
                <td>${data.date}</td>
                <td>${data.ics_rrsp_no}</td>
                <td>${data.property_no}</td>
                <td>${data.item_description}</td>
                <td>${data.useful_life}</td>
                <td>${data.issued_qty}</td>
                <td>${data.issued_office || '-'}</td>
                <td>${data.returned_qty || '0'}</td>
                <td>${data.returned_office || '-'}</td>
                <td>${data.reissued_qty || '0'}</td>
                <td>${data.reissued_office || '-'}</td>
                <td>${data.disposed_qty1 || '0'}</td>
                <td>${data.disposed_qty2 || '0'}</td>
                <td>${data.balance_qty || '0'}</td>
                <td>${data.amount || '-'}</td>
                <td>${data.remarks || '-'}</td>
            `;

            // Close modal and reset form
            closeModalFunc();
            
            // Show success message
            alert('Item added successfully!');
        });

        // Clear all items
        document.addEventListener('click', function(e) {
            if (e.target.textContent.includes('Clear All')) {
                if (confirm('Are you sure you want to clear all items? This action cannot be undone.')) {
                    // Keep only the header sample row
                    const firstRow = tableBody.rows[0];
                    tableBody.innerHTML = '';
                    if (firstRow) {
                        tableBody.appendChild(firstRow);
                    }
                }
            }
        });
    </script>
    <script>
    // Category filter: reload page with ?category=
    document.addEventListener('DOMContentLoaded', function() {
        const sel = document.getElementById('semi-expendable-property');
        if (!sel) return;
        sel.addEventListener('change', function() {
            const val = sel.value || '';
            const params = new URLSearchParams(window.location.search);
            if (val && val !== 'All') {
                params.set('category', val);
            } else {
                params.delete('category');
            }
            const qs = params.toString();
            window.location.assign(window.location.pathname + (qs ? ('?' + qs) : ''));
        });
    });
    </script>
    <script>
    // RPCI-like: Show N rows control adjusting wrapper height dynamically
    document.addEventListener('DOMContentLoaded', () => {
        const limitSelect = document.getElementById('rsep_row_limit');
        const wrapper = document.querySelector('.rsep-table-wrapper');
        const table = document.getElementById('rsep-table');
        const thead = table ? table.querySelector('thead') : null;

        function applyLimit() {
            if (!limitSelect || !wrapper || !thead || !table) return;
            const val = limitSelect.value;
            const sampleRow = table.querySelector('tbody tr:not([style*="display: none"])') || table.querySelector('tbody tr');
            if (!sampleRow) return;
            const headerHeight = thead.getBoundingClientRect().height;
            const rowHeight = sampleRow.getBoundingClientRect().height || 36;
            if (val === 'all') {
                wrapper.style.maxHeight = 'none';
            } else {
                const count = parseInt(val, 10);
                wrapper.style.maxHeight = `${headerHeight + rowHeight * count}px`;
            }
        }

        if (limitSelect) {
            limitSelect.addEventListener('change', applyLimit);
        }
        applyLimit();
        window.addEventListener('resize', applyLimit);
    });
    </script>
    <script>
    // RPCI-like: offset header rows so multi-row thead stays stacked while sticky
    document.addEventListener('DOMContentLoaded', () => {
        const thead = document.querySelector('#rsep-table thead');
        if (!thead) return;

        function offsetHeaderRows() {
            const rows = thead.querySelectorAll('tr');
            if (!rows || rows.length === 0) return;

            const first = rows[0];
            const firstH = first.getBoundingClientRect().height;
            // Second row under first
            const secondThs = thead.querySelectorAll('tr:nth-child(2) th');
            secondThs.forEach(th => { th.style.top = firstH + 'px'; });

            // Third row under first+second (if present)
            const second = rows[1];
            const thirdThs = thead.querySelectorAll('tr:nth-child(3) th');
            if (second && thirdThs.length) {
                const secondH = second.getBoundingClientRect().height;
                const total = firstH + secondH;
                thirdThs.forEach(th => { th.style.top = total + 'px'; });
            }
        }

        offsetHeaderRows();
        window.addEventListener('resize', offsetHeaderRows);
    });
    </script>
    <script>
    // Toggle shadow on sticky header when the table is scrolled
    document.addEventListener('DOMContentLoaded', () => {
        const wrapper = document.querySelector('.rsep-table-wrapper');
        if (!wrapper) return;
        const onScroll = () => {
            if (wrapper.scrollTop > 0) {
                wrapper.classList.add('scrolling');
            } else {
                wrapper.classList.remove('scrolling');
            }
        };
        wrapper.addEventListener('scroll', onScroll);
        onScroll(); // initialize state
    });
    </script>
    


</body>
</html>