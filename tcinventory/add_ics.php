<?php include 'sidebar.php'; ?>
<?php require 'config.php'; ?>

<?php
require 'functions.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Check if we're editing or creating new
    $is_editing = isset($_POST['is_editing']) && $_POST['is_editing'] == '1';
    $ics_id = $is_editing ? (int)$_POST['ics_id'] : null;

    // ICS Header fields
    $ics_no = $_POST['ics_no'];
    $entity_name = $_POST['entity_name'];
    $fund_cluster = $_POST['fund_cluster'];
    $date_issued = $_POST['date_issued'];
    $received_by = $_POST['received_by'];
    $received_by_position = $_POST['received_by_position'];
    $received_from = $_POST['received_from'];
    $received_from_position = $_POST['received_from_position'];

    if ($is_editing) {
        // Update existing ICS
        $stmt = $conn->prepare("UPDATE ics SET entity_name = ?, fund_cluster = ?, date_issued = ?, 
                               received_by = ?, received_by_position = ?, received_from = ?, received_from_position = ? 
                               WHERE ics_id = ?");
        $stmt->bind_param("sssssssi", $entity_name, $fund_cluster, $date_issued, 
                         $received_by, $received_by_position, $received_from, $received_from_position, $ics_id);
        $stmt->execute();
        $stmt->close();
        
        // Get old items to restore inventory quantities
        $old_items = [];
        $stmt = $conn->prepare("SELECT stock_number, issued_quantity FROM ics_items WHERE ics_id = ?");
        $stmt->bind_param("i", $ics_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $old_items[$row['stock_number']] = $row['issued_quantity'];
        }
        $stmt->close();
        
        // Delete old items
        $stmt = $conn->prepare("DELETE FROM ics_items WHERE ics_id = ?");
        $stmt->bind_param("i", $ics_id);
        $stmt->execute();
        $stmt->close();
        
        // Restore inventory quantities from old items
        foreach ($old_items as $stock_no => $old_qty) {
            $stmt = $conn->prepare("UPDATE items SET quantity_on_hand = quantity_on_hand + ? WHERE stock_number = ?");
            $stmt->bind_param("ds", $old_qty, $stock_no);
            $stmt->execute();
            $stmt->close();
        }
        
    } else {
        // Insert new ICS
        $stmt = $conn->prepare("INSERT INTO ics (ics_no, entity_name, fund_cluster, date_issued, 
                               received_by, received_by_position, received_from, received_from_position)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $ics_no, $entity_name, $fund_cluster, $date_issued, 
                         $received_by, $received_by_position, $received_from, $received_from_position);
        $stmt->execute();
        $ics_id = $stmt->insert_id;
        $stmt->close();
    }

    // ICS Items arrays from form
    $stock_numbers = $_POST['stock_number'];
    $issued_quantities = $_POST['issued_quantity'];
    $estimated_useful_lives = $_POST['estimated_useful_life'];
    $serial_numbers = $_POST['serial_number'];

    // Insert new items and update inventory
    for ($i = 0; $i < count($stock_numbers); $i++) {
        $stock_no = $stock_numbers[$i];
        $issued_qty = (float)$issued_quantities[$i];
        $useful_life = $estimated_useful_lives[$i];
        $serial_no = $serial_numbers[$i];

        // Only insert if there's an issued quantity
        if ($issued_qty > 0) {
            // Get item details
            $stmt = $conn->prepare("SELECT item_id, item_name, description, unit, average_unit_cost FROM items WHERE stock_number = ?");
            $stmt->bind_param("s", $stock_no);
            $stmt->execute();
            $result = $stmt->get_result();
            $item_data = $result->fetch_assoc();
            $stmt->close();

            if ($item_data) {
                $unit_cost = $item_data['average_unit_cost'];
                $total_cost = $issued_qty * $unit_cost;
                
                // Insert ICS item
                $stmt = $conn->prepare("INSERT INTO ics_items (ics_id, stock_number, quantity, unit, unit_cost, total_cost, 
                                       description, inventory_item_no, estimated_useful_life, serial_number)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isdddssss", $ics_id, $stock_no, $issued_qty, $item_data['unit'], 
                                 $unit_cost, $total_cost, $item_data['description'], $stock_no, $useful_life, $serial_no);
                $stmt->execute();
                $stmt->close();

                // Update inventory: deduct issued quantity
                $stmt = $conn->prepare("UPDATE items SET quantity_on_hand = quantity_on_hand - ? WHERE stock_number = ?");
                $stmt->bind_param("ds", $issued_qty, $stock_no);
                $stmt->execute();
                $stmt->close();

                // Insert a NEGATIVE inventory entry
                $negative_qty = -$issued_qty;
                $zero_cost = 0.00;
                $stmt = $conn->prepare("INSERT INTO inventory_entries (item_id, quantity, unit_cost, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param("idd", $item_data['item_id'], $negative_qty, $zero_cost);
                $stmt->execute();
                $stmt->close();
                
                logItemHistory($conn, $item_data['item_id'], -$issued_qty, 'issued_ics', $ics_id);

                // Recalculate average cost
                updateAverageCost($conn, $item_data['item_id']);
            }
        }
    }

    // Redirect after successful submission
    if ($is_editing) {
        header("Location: view_ics.php?ics_id=" . $ics_id);
    } else {
        header("Location: ics.php");
    }
    exit();
}

// Check if we're editing an existing ICS
$is_editing = isset($_GET['ics_id']) && !empty($_GET['ics_id']);
$ics_id = $is_editing ? (int)$_GET['ics_id'] : null;

// Initialize variables
$ics_data = [];
$ics_items = [];

if ($is_editing) {
    // Fetch existing ICS data
    $stmt = $conn->prepare("SELECT * FROM ics WHERE ics_id = ?");
    $stmt->bind_param("i", $ics_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $ics_data = $result->fetch_assoc();
        
        // Fetch ICS items
        $stmt = $conn->prepare("SELECT * FROM ics_items WHERE ics_id = ?");
        $stmt->bind_param("i", $ics_id);
        $stmt->execute();
        $items_result = $stmt->get_result();
        
        while ($item = $items_result->fetch_assoc()) {
            $ics_items[$item['stock_number']] = $item;
        }
    } else {
        // ICS not found, redirect back
        header("Location: ics.php");
        exit();
    }
}

// Function to generate the next ICS number (only for new ICS)
// Fixed version of the generateICSNumber function with error handling
function generateICSNumber($conn) {
    $current_year = date('Y');
    $current_month = date('m');
    $prefix = 'ICS-' . $current_year . '/' . $current_month . '/';
    
    // Get the highest ICS number for current month/year
    $query = "SELECT ics_no FROM ics WHERE ics_no LIKE ? ORDER BY ics_no DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    
    // Add error handling for prepare
    if (!$stmt) {
        // If prepare fails, log the error and return a basic format
        error_log("MySQL prepare error in generateICSNumber: " . $conn->error);
        return $prefix . '0001';
    }
    
    $search_pattern = $prefix . '%';
    $stmt->bind_param('s', $search_pattern);
    
    if (!$stmt->execute()) {
        // If execute fails, close statement and return basic format
        error_log("MySQL execute error in generateICSNumber: " . $stmt->error);
        $stmt->close();
        return $prefix . '0001';
    }
    
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_ics = $row['ics_no'];
        
        // Extract the incremental part (last 4 digits)
        $last_increment = (int)substr($last_ics, -4);
        $next_increment = $last_increment + 1;
    } else {
        // First ICS for this month/year
        $next_increment = 1;
    }
    
    $stmt->close();
    
    // Format the increment with leading zeros (4 digits)
    $formatted_increment = str_pad($next_increment, 4, '0', STR_PAD_LEFT);
    
    return $prefix . $formatted_increment;
}

// Alternative simpler version if you're still having issues
function generateICSNumberSimple($conn) {
    $current_year = date('Y');
    $current_month = date('m');
    $prefix = 'ICS-' . $current_year . '/' . $current_month . '/';
    
    // Use a simpler query without LIKE
    $query = "SELECT COUNT(*) as count FROM ics WHERE YEAR(date_issued) = ? AND MONTH(date_issued) = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("MySQL prepare error: " . $conn->error);
        return $prefix . '0001';
    }
    
    $stmt->bind_param('ii', $current_year, $current_month);
    
    if (!$stmt->execute()) {
        error_log("MySQL execute error: " . $stmt->error);
        $stmt->close();
        return $prefix . '0001';
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    $stmt->close();
    
    $next_increment = $count + 1;
    $formatted_increment = str_pad($next_increment, 4, '0', STR_PAD_LEFT);
    
    return $prefix . $formatted_increment;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_editing ? 'Edit ICS Form' : 'Add ICS Form'; ?></title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
</head>
<body>
    <div class="edit-ics-page content">
        <h2><?php echo $is_editing ? 'Edit ICS Form' : 'Add ICS Form'; ?></h2>

        <form method="post" action="">
            <?php if ($is_editing): ?>
                <input type="hidden" name="ics_id" value="<?php echo $ics_id; ?>">
                <input type="hidden" name="is_editing" value="1">
            <?php endif; ?>
            
            <h3>ICS Details</h3>

            <label>Entity Name:</label>
            <input type="text" name="entity_name" value="<?php echo htmlspecialchars($ics_data['entity_name'] ?? 'TESDA Regional Office'); ?>" required>

            <label>Fund Cluster:</label>
            <select name="fund_cluster" required>
                <option value="">-- Select Fund Cluster --</option>
                <option value="General Fund" <?php echo (isset($ics_data['fund_cluster']) && $ics_data['fund_cluster'] == 'General Fund') ? 'selected' : ''; ?>>General Fund</option>
                <option value="Special Purpose Fund" <?php echo (isset($ics_data['fund_cluster']) && $ics_data['fund_cluster'] == 'Special Purpose Fund') ? 'selected' : ''; ?>>Special Purpose Fund</option>
                <option value="Trust Fund" <?php echo (isset($ics_data['fund_cluster']) && $ics_data['fund_cluster'] == 'Trust Fund') ? 'selected' : ''; ?>>Trust Fund</option>
            </select>

            <label>ICS No.:</label>
            <input type="text" name="ics_no" value="<?php echo htmlspecialchars($auto_ics_number); ?>" readonly style="background-color: #f5f5f5;">

            <label>Date Issued:</label>
            <input type="date" name="date_issued" value="<?php echo $ics_data['date_issued'] ?? date('Y-m-d'); ?>" required>

            <h3>Personnel Information</h3>

            <label>Received By:</label>
            <input type="text" name="received_by" value="<?php echo htmlspecialchars($ics_data['received_by'] ?? ''); ?>" required>

            <label>Received By Position:</label>
            <input type="text" name="received_by_position" value="<?php echo htmlspecialchars($ics_data['received_by_position'] ?? ''); ?>" required>

            <label>Received From:</label>
            <input type="text" name="received_from" value="<?php echo htmlspecialchars($ics_data['received_from'] ?? ''); ?>" required>

            <label>Received From Position:</label>
            <input type="text" name="received_from_position" value="<?php echo htmlspecialchars($ics_data['received_from_position'] ?? ''); ?>" required>

            <h3>Semi-Expendable Items</h3>
            
            <!-- Search Container -->
            <div class="search-container">
                <input type="text" id="itemSearch" class="search-input" placeholder="Start typing to search semi-expendable items..." onkeyup="filterItems()">
            </div>
            
            <div style="overflow-x:auto;">
                <table id="itemsTable">
                    <thead>
                        <tr>
                            <th>Stock No.</th>
                            <th>Item</th>
                            <th>Description</th>
                            <th>Unit</th>
                            <th>Quantity on Hand</th>
                            <th>Unit Cost</th>
                            <th>Issued Qty</th>
                            <th>Estimated Useful Life</th>
                            <th>Serial Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="no-search-placeholder" class="no-search-placeholder">
                            <td colspan="9">Start typing in the search box to find semi-expendable items...</td>
                        </tr>
                        <?php 
                        // Only show semi-expendable items (you might want to add a category field to distinguish)
                        $result = $conn->query("SELECT * FROM items WHERE category = 'Semi-Expendable' OR category LIKE '%equipment%' OR category LIKE '%furniture%' OR item_name LIKE '%computer%' OR item_name LIKE '%printer%' OR item_name LIKE '%chair%' OR item_name LIKE '%table%'");
                        if (!$result || $result->num_rows == 0) {
                            // Fallback: show all items if no semi-expendable category exists yet
                            $result = $conn->query("SELECT * FROM items");
                        }
                        
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $stock_number = $row['stock_number'];
                                $existing_item = $ics_items[$stock_number] ?? null;
                                
                                echo '<tr class="item-row hidden" data-stock="' . htmlspecialchars(strtolower($stock_number)) . '" data-item_name="' . htmlspecialchars(strtolower($row['item_name'])) . '" data-description="' . htmlspecialchars(strtolower($row['description'])) . '" data-unit="' . htmlspecialchars(strtolower($row['unit'])) . '">';
                                echo '<td><input type="hidden" name="stock_number[]" value="' . htmlspecialchars($stock_number) . '">' . htmlspecialchars($stock_number) . '</td>';
                                echo '<td>' . htmlspecialchars($row['item_name']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['description']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['unit']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['quantity_on_hand']) . '</td>';
                                echo '<td>₱' . number_format($row['average_unit_cost'], 2) . '</td>';
                                echo '<td><input type="number" name="issued_quantity[]" value="' . ($existing_item ? htmlspecialchars($existing_item['quantity']) : '') . '" min="0" max="' . htmlspecialchars($row['quantity_on_hand']) . '" step="0.01"></td>';
                                echo '<td><input type="text" name="estimated_useful_life[]" value="' . ($existing_item ? htmlspecialchars($existing_item['estimated_useful_life']) : '') . '" placeholder="e.g., 5 years"></td>';
                                echo '<td><input type="text" name="serial_number[]" value="' . ($existing_item ? htmlspecialchars($existing_item['serial_number']) : '') . '" placeholder="Serial No."></td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr id="no-items-row"><td colspan="9">No semi-expendable items found.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <button type="submit"><?php echo $is_editing ? 'Update ICS' : 'Submit ICS'; ?></button>
            <a href="<?php echo $is_editing ? 'view_ics.php?ics_id=' . $ics_id : 'ics.php'; ?>" style="margin-left: 10px;">
                <button type="button">Cancel</button>
            </a>
        </form>
    </div>

    <script>
        function filterItems() {
            // Get the search input value and convert to lowercase
            const searchValue = document.getElementById('itemSearch').value.toLowerCase();
            
            // Get all item rows
            const itemRows = document.querySelectorAll('.item-row');
            const noSearchPlaceholder = document.getElementById('no-search-placeholder');
            
            // Counter for visible rows
            let visibleRows = 0;
            
            // If search is empty, hide all rows and show placeholder
            if (searchValue.trim() === '') {
                itemRows.forEach(function(row) {
                    row.classList.remove('visible');
                    row.classList.add('hidden');
                });
                
                // Show the placeholder
                if (noSearchPlaceholder) {
                    noSearchPlaceholder.style.display = 'table-row';
                }
                
                // Hide the no items message
                const noItemsRow = document.getElementById('no-items-row');
                if (noItemsRow) {
                    noItemsRow.style.display = 'none';
                }
                return;
            }
            
            // Hide the placeholder when searching
            if (noSearchPlaceholder) {
                noSearchPlaceholder.style.display = 'none';
            }
            
            // Loop through each row
            itemRows.forEach(function(row) {
                // Get the data attributes
                const stockNumber = row.getAttribute('data-stock');
                const item_name = row.getAttribute('data-item_name');
                const description = row.getAttribute('data-description');
                const unit = row.getAttribute('data-unit');
                
                // Check if search value matches any of the fields
                if ((stockNumber && stockNumber.includes(searchValue)) || 
                    (item_name && item_name.includes(searchValue)) ||
                    (description && description.includes(searchValue)) || 
                    (unit && unit.includes(searchValue))) {
                    // Show the row
                    row.classList.remove('hidden');
                    row.classList.add('visible');
                    visibleRows++;
                } else {
                    // Hide the row
                    row.classList.remove('visible');
                    row.classList.add('hidden');
                }
            });
            
            // Handle the "no items found" message
            const noItemsRow = document.getElementById('no-items-row');
            if (noItemsRow) {
                if (visibleRows === 0) {
                    // Show "no results found" message
                    noItemsRow.style.display = 'table-row';
                    noItemsRow.innerHTML = '<td colspan="9">No items match your search criteria.</td>';
                } else {
                    // Hide the message
                    noItemsRow.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>