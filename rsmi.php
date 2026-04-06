<?php
    require 'auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSMI - Report on Stock of Materials and Supplies Issued</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f570";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }
        
        /* Align export button properly in container */
        .search-add-container {
            align-items: center;
            padding: 15px 20px 0px 20px;
        }
        
        .export-section {
            margin-bottom: 20px;
        }
        
        /* Currency cell styling - green color override */
        .currency {
            color: #059669;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <h2>Report on the Stock of Materials and Supplies Issued (RSMI)</h2>

        <div class="table-container">
            <table>
            <thead>
                <tr>
                    <th><i class="fas fa-hashtag"></i> RIS No.</th>
                    <th><i class="fas fa-barcode"></i> Stock No.</th>
                    <th><i class="fas fa-tag"></i> Item</th>
                    <th><i class="fas fa-ruler"></i> Unit</th>
                    <th><i class="fas fa-cubes"></i> Quantity Issued</th>
                    <th><i class="fas fa-dollar-sign"></i> Unit Cost</th>
                    <th><i class="fas fa-calculator"></i> Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                require 'config.php';

                // Fetch all officer names for autocomplete
                $officer_names = [];
                $officers_result = $conn->query("SELECT officer_name FROM officers ORDER BY officer_name ASC");
                if ($officers_result && $officers_result->num_rows > 0) {
                    while ($row = $officers_result->fetch_assoc()) {
                        $officer_names[] = $row['officer_name'];
                    }
                }
                $officer_names_json = json_encode($officer_names);

                // Fetch current user's full name for custodian default
                $default_custodian = '';
                $user_id = $_SESSION['user_id'];
                $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result_user = $stmt->get_result();
                if ($result_user && $result_user->num_rows > 0) {
                    $user_data = $result_user->fetch_assoc();
                    $default_custodian = $user_data['full_name'] ?? '';
                }
                $stmt->close();

                $result = $conn->query("
                    SELECT ris.ris_no, ri.stock_number, i.item_name, i.description, i.unit, ri.issued_quantity, 
                        ri.unit_cost_at_issue AS unit_cost,
                        (ri.issued_quantity * ri.unit_cost_at_issue) AS amount
                    FROM ris_items ri
                    JOIN ris ON ri.ris_id = ris.ris_id
                    JOIN items i ON ri.stock_number = i.stock_number
                    ORDER BY ris.date_requested DESC
                ");

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['ris_no']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['stock_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['item_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['unit']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['issued_quantity']) . '</td>';
                        echo '<td class="currency">₱ ' . number_format($row['unit_cost'], 2) . '</td>';
                        echo '<td class="currency">₱ ' . number_format($row['amount'], 2) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="7">No RSMI entries found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
        </div>

        <h2>Recapitulation</h2>
        
        <div class="table-container">
            <table>
            <thead>
                <tr>
                    <th><i class="fas fa-barcode"></i> Stock No.</th>
                    <th><i class="fas fa-cubes"></i> Total Quantity Issued</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $recap = $conn->query("
                    SELECT stock_number, SUM(issued_quantity) AS total_issued
                    FROM ris_items
                    GROUP BY stock_number
                ");

                if ($recap && $recap->num_rows > 0) {
                    while ($row = $recap->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['stock_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['total_issued']) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="2">No recapitulation data found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
        </div>

        <!-- Signature Names Form -->
        <div class="section-card" style="background: white; border: 2px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-top: 30px; margin-bottom: 20px;">
            <h3 style="color: #1e293b; font-size: 18px; margin: 0 0 15px 0; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-signature"></i> Signatory Information
            </h3>
            <form id="signatureForm" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label for="custodian_name" style="display: block; margin-bottom: 6px; font-weight: 600; color: #334155; font-size: 14px;">
                        <i class="fas fa-user"></i> Supply/Property Custodian:
                    </label>
                    <input 
                        type="text" 
                        id="custodian_name" 
                        name="custodian_name" 
                        value="<?php echo htmlspecialchars($default_custodian); ?>"
                        placeholder="Enter name"
                        style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; transition: all 0.3s ease;"
                    >
                </div>
                <div class="form-group" style="margin: 0; position: relative;">
                    <label for="accounting_staff" style="display: block; margin-bottom: 6px; font-weight: 600; color: #334155; font-size: 14px;">
                        <i class="fas fa-calculator"></i> Designated Accounting Staff:
                    </label>
                    <input 
                        type="text" 
                        id="accounting_staff" 
                        name="accounting_staff" 
                        placeholder="Enter name"
                        autocomplete="off"
                        style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; transition: all 0.3s ease;"
                    >
                    <div id="accounting_staff_dropdown" class="autocomplete-dropdown"></div>
                </div>
            </form>
            <style>
                #signatureForm input:hover {
                    border-color: #cbd5e1;
                }
                #signatureForm input:focus {
                    outline: none;
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
            </style>
        </div>

        <!-- Export Section -->
        <div class="search-add-container">
            <a href="#" id="exportBtn" class="export-btn">
                <i class="fas fa-file-pdf"></i> Export to PDF
            </a>
        </div>
    </div>

    <script>
        // Update export link with signature names
        document.getElementById('exportBtn').addEventListener('click', function(e) {
            e.preventDefault();
            
            const custodianName = document.getElementById('custodian_name').value;
            const accountingStaff = document.getElementById('accounting_staff').value;
            
            let exportUrl = 'rsmi_export.php';
            const params = new URLSearchParams();
            
            if (custodianName) {
                params.append('custodian_name', custodianName);
            }
            if (accountingStaff) {
                params.append('accounting_staff', accountingStaff);
            }
            
            if (params.toString()) {
                exportUrl += '?' + params.toString();
            }
            
            window.location.href = exportUrl;
        });
        
        // Add mobile sidebar toggle functionality if needed
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }

        // Officer names for autocomplete
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
            // Remove previous selection
            items.forEach(item => item.classList.remove('selected'));
            
            // Add selection to current item
            if (index >= 0 && index < items.length) {
                items[index].classList.add('selected');
                
                // Scroll into view if needed
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
                
                // Highlight matching text
                const index = name.toLowerCase().indexOf(searchValue);
                if (index !== -1) {
                    const before = name.substring(0, index);
                    const match = name.substring(index, index + searchValue.length);
                    const after = name.substring(index + searchValue.length);
                    item.innerHTML = before + '<strong>' + match + '</strong>' + after;
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

        // Add event listener for mobile menu button if you have one
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.querySelector('.menu-button');
            if (menuButton) {
                menuButton.addEventListener('click', toggleSidebar);
            }
            // Initialize autocomplete for accounting staff
            setupAutocomplete('accounting_staff', 'accounting_staff_dropdown');
        });
    </script>
</body>
</html>