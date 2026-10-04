<?php
require 'auth.php';
require 'config.php';

// Fetch semi-expendable items from database
$items = [];
$sql = "SELECT id, category, item_description, semi_expendable_property_no, unit, amount, quantity_balance, remarks FROM semi_expendable_property ORDER BY item_description";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
} else {
    error_log('RPCSP DB error: ' . $conn->error);
}
// Fetch logged-in user's full_name and position for Accountable Officer
$current_user_full_name = '';
$current_user_position = '';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT full_name, user_position FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $current_user_full_name = $user_data['full_name'] ?? '';
        $current_user_position = $user_data['user_position'] ?? '';
    }
    $stmt->close();
}

// Fetch all officer names for autocomplete
$officer_names = [];
$officers_result = $conn->query("SELECT officer_name FROM officers ORDER BY officer_name ASC");
if ($officers_result && $officers_result->num_rows > 0) {
    while ($row = $officers_result->fetch_assoc()) {
        $officer_names[] = $row['officer_name'];
    }
}
$officer_names_json = json_encode($officer_names, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RPCSP - Report on the Physical Count of Semi-Expendable Property</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .container h2::before {
            content: "\f15c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            margin-right: 12px;
            color: #3b82f6;
        }

        .table-controls label {
            font-weight: 600;
            color: #001F80;
        }

        .table-controls select {
            padding: 8px 8px;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            cursor: pointer;
        }
        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: none;
            border-radius: 0 0 6px 6px;
            max-height: 250px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .autocomplete-item {
            padding: 10px 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .autocomplete-item:hover {
            background: #f0f4f8;
        }
        
        .autocomplete-item.selected {
            background: #3b82f6;
            color: white;
        }
        .count-input { box-sizing: border-box; width: 100%; min-width: 80px; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
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
                signature_name_1: document.querySelector('input[name="signature_name_1"]').value,
                signature_name_2: document.querySelector('input[name="signature_name_2"]').value,
                signature_name_3: document.querySelector('input[name="signature_name_3"]').value,
            });
            // Post all row values so large reports do not exceed URL limits.
            const exportForm = document.createElement('form');
            exportForm.method = 'POST';
            exportForm.action = './rpcsp_export.php';
            const addField = (name, value) => {
                const field = document.createElement('input');
                field.type = 'hidden';
                field.name = name;
                field.value = value;
                exportForm.appendChild(field);
            };
            params.forEach((value, name) => addField(name, value));
            document.querySelectorAll('#rpci-table tbody input[name]').forEach(input => {
                addField(input.name, input.value);
            });
            document.body.appendChild(exportForm);
            exportForm.submit();
            exportForm.remove();
        }
    </script>
</head>
<body class="rpci-page">
<?php include 'sidebar.php'; ?>
    <div class="container">
        <div class="rpci-form">
            <div class="rpci-header">
                <h2>Report on the Physical Count of Semi-Expendable Property</h2>

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
                            <input type="text" id="accountable_officer" name="accountable_officer" value="<?= htmlspecialchars($current_user_full_name) ?>" placeholder="Name of Accountable Officer" style="min-width: 300px;">,    
                            <input type="text" id="official_designation" name="official_designation" value="<?= htmlspecialchars($current_user_position) ?>" placeholder="Official Designation">,
                            <input type="text" id="entity_name" name="entity_name" value="TESDA Regional Office" placeholder="Entity Name">
                            is accountable, having assumed such accountability on
                            <input type="date" id="assumption_date" name="assumption_date">
                            .
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

                        <div class="search-container" style="margin-left: 24px; margin:12px;">
                                    <input type="text" id="searchInput" class="search-input" placeholder="Search by property no., category, or description...">
                        </div>`
                </div>

            <div class="rpci-table-wrapper">
                <table class="rpci-table" id="rpci-table">
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
                                    <td class="currency">₱ <?= htmlspecialchars(isset($row['amount']) ? number_format((float)$row['amount'], 2) : '') ?></td>
                                    <td><?= htmlspecialchars((string)($row['quantity_balance'] ?? '')) ?></td>
                                    <td><input type="number" class="count-input" name="on_hand_count[<?= (int)$row['id'] ?>]" min="0" step="1" aria-label="On Hand Per Count"></td>
                                    <td><input type="number" class="count-input" name="shortage_qty[<?= (int)$row['id'] ?>]" step="1" aria-label="Shortage or Overage Quantity"></td>
                                    <td><input type="number" class="count-input currency" name="shortage_value[<?= (int)$row['id'] ?>]" step="0.01" aria-label="Shortage or Overage Value"></td>
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
                    <div style="position: relative;">
                        <input type="text" class="signature-input" id="signature_name_1" name="signature_name_1" placeholder="Signature over Printed Name" autocomplete="off">
                        <div id="signature_name_1_dropdown" class="autocomplete-dropdown"></div>
                    </div>
                    <div class="signature-text">Signature over Printed Name of Inventory<br>Committee Chair and Members</div>
                </div>
                <div class="signature-box">
                    <h4>Approved by:</h4>
                    <div style="position: relative;">
                        <input type="text" class="signature-input" id="signature_name_2" name="signature_name_2" placeholder="Signature over Printed Name" autocomplete="off">
                        <div id="signature_name_2_dropdown" class="autocomplete-dropdown"></div>
                    </div>
                    <div class="signature-text">Signature over Printed Name of Head of Agency/Entity<br>or Authorized Representative</div>
                </div>
                <div class="signature-box">
                    <h4>Verified by:</h4>
                    <div style="position: relative;">
                        <input type="text" class="signature-input" id="signature_name_3" name="signature_name_3" placeholder="Signature over Printed Name" autocomplete="off">
                        <div id="signature_name_3_dropdown" class="autocomplete-dropdown"></div>
                    </div>
                    <div class="signature-text">Signature over Printed Name of COA Representative</div>
                </div>
            </div>

            <div style="text-align:center; margin-top: 18px;">
                <button type="button" class="export-btn" onclick="openExport()">
                    <i class="fas fa-file-pdf"></i>
                    Export to PDF
                </button>
            </div>
            </form>
    </div>

    <script>
        const officerNames = <?php echo $officer_names_json; ?>;

        function setupAutocomplete(inputId, dropdownId) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);
            
            if (!input || !dropdown) return;

            // Show dropdown on focus
            input.addEventListener('focus', function() {
                if (this.value.trim() === '') {
                    showAllSuggestions(dropdown, input);
                } else {
                    filterSuggestions(this.value, dropdown, input);
                }
            });

            // Prevent click on input from closing dropdown
            input.addEventListener('click', function(e) {
                e.stopPropagation();
                if (dropdown.style.display !== 'block') {
                    if (this.value.trim() === '') {
                        showAllSuggestions(dropdown, input);
                    } else {
                        filterSuggestions(this.value, dropdown, input);
                    }
                }
            });

            // Filter on input
            input.addEventListener('input', function() {
                const value = this.value;
                if (value.trim() === '') {
                    showAllSuggestions(dropdown, input);
                } else {
                    filterSuggestions(value, dropdown, input);
                }
            });

            // Handle keyboard navigation
            input.addEventListener('keydown', function(e) {
                if (dropdown.style.display !== 'block') return;

                const items = Array.from(dropdown.querySelectorAll('.autocomplete-item:not([style*="cursor: default"])'));
                if (items.length === 0) return;

                const selectedItem = dropdown.querySelector('.autocomplete-item.selected');
                let currentIndex = selectedItem ? items.indexOf(selectedItem) : -1;

                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        currentIndex = (currentIndex + 1) % items.length;
                        highlightItem(items, currentIndex, dropdown);
                        break;
                    
                    case 'ArrowUp':
                        e.preventDefault();
                        currentIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
                        highlightItem(items, currentIndex, dropdown);
                        break;
                    
                    case 'Enter':
                        e.preventDefault();
                        if (selectedItem) {
                            const text = selectedItem.textContent || selectedItem.innerText;
                            input.value = text;
                            dropdown.style.display = 'none';
                        }
                        break;
                    
                    case 'Tab':
                        const itemToSelect = selectedItem || items[0];
                        if (itemToSelect) {
                            const text = itemToSelect.textContent || itemToSelect.innerText;
                            input.value = text;
                            dropdown.style.display = 'none';
                        }
                        break;
                    
                    case 'Escape':
                        dropdown.style.display = 'none';
                        break;
                }
            });

            // Prevent clicks inside dropdown from closing it
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                }
            });
        }

        // Highlight selected item and scroll into view
        function highlightItem(items, index, dropdown) {
            items.forEach(item => item.classList.remove('selected'));
            
            if (index >= 0 && index < items.length) {
                items[index].classList.add('selected');
                
                const item = items[index];
                const dropdownRect = dropdown.getBoundingClientRect();
                const itemRect = item.getBoundingClientRect();
                
                if (itemRect.bottom > dropdownRect.bottom) {
                    item.scrollIntoView({ block: 'end', behavior: 'smooth' });
                } else if (itemRect.top < dropdownRect.top) {
                    item.scrollIntoView({ block: 'start', behavior: 'smooth' });
                }
            }
        }

        function showAllSuggestions(dropdown, input) {
            dropdown.innerHTML = '';
            
            if (officerNames.length === 0) {
                dropdown.innerHTML = '<div class="autocomplete-item" style="color: #999; cursor: default;">No officers available</div>';
                dropdown.style.display = 'block';
                return;
            }

            officerNames.forEach(name => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                item.textContent = name;
                item.addEventListener('click', function() {
                    input.value = name;
                    dropdown.style.display = 'none';
                });
                dropdown.appendChild(item);
            });
            
            dropdown.style.display = 'block';
        }

        function filterSuggestions(value, dropdown, input) {
            dropdown.innerHTML = '';
            const searchValue = value.toLowerCase();
            
            const filtered = officerNames.filter(name => 
                name.toLowerCase().includes(searchValue)
            );

            if (filtered.length === 0) {
                dropdown.innerHTML = '<div class="autocomplete-item" style="color: #999; cursor: default;">No matches found</div>';
                dropdown.style.display = 'block';
                return;
            }

            filtered.forEach(name => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                
                const index = name.toLowerCase().indexOf(searchValue);
                if (index !== -1) {
                    const before = name.substring(0, index);
                    const match = name.substring(index, index + searchValue.length);
                    const after = name.substring(index + searchValue.length);
                    const highlighted = document.createElement('strong');
                    highlighted.textContent = match;
                    item.append(document.createTextNode(before), highlighted, document.createTextNode(after));
                } else {
                    item.textContent = name;
                }
                
                item.addEventListener('click', function() {
                    input.value = name;
                    dropdown.style.display = 'none';
                });
                dropdown.appendChild(item);
            });
            
            dropdown.style.display = 'block';
        }

        // Initialize autocomplete on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            setupAutocomplete('signature_name_1', 'signature_name_1_dropdown');
            setupAutocomplete('signature_name_2', 'signature_name_2_dropdown');
            setupAutocomplete('signature_name_3', 'signature_name_3_dropdown');
        });

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
            if (val === 'all') { wrapper.style.maxHeight = 'none'; }
            else { wrapper.style.maxHeight = `${headerHeight + rowHeight * parseInt(val,10)}px`; }
        }

        limitSelect.addEventListener('change', applyLimit);
        applyLimit();
        window.addEventListener('resize', applyLimit);
    });

    // Searchbar JS (matches rpci behavior)
    document.getElementById('searchInput').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#rpci-table tbody tr');

        rows.forEach(row => {
            const category = (row.cells[1]?.textContent || '').toLowerCase();
            const desc = (row.cells[2]?.textContent || '').toLowerCase();
            const propno = (row.cells[3]?.textContent || '').toLowerCase();
            const unit = (row.cells[4]?.textContent || '').toLowerCase();

            const match = category.includes(filter) || desc.includes(filter) || propno.includes(filter) || unit.includes(filter);
            row.style.display = match ? '' : 'none';
        });
    });
    </script>
</body>
</html>
