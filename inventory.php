<?php
require 'auth.php';
require 'config.php';
require 'functions.php';



if (isset($_GET['fix_null_item_history'])) {
    $update_sql = "UPDATE item_history SET reorder_point = 0 WHERE reorder_point IS NULL";
    if ($conn->query($update_sql)) {
        echo "<div style='color:green;font-weight:bold;'>All item_history records with null reorder_point have been updated to 0.</div>";
    } else {
        echo "<div style='color:red;font-weight:bold;'>Error updating item_history records: {$conn->error}</div>";
    }
}
// Utility: Update all old items with null reorder_point to 0
if (isset($_GET['fix_null_reorder_point'])) {
    $update_sql = "UPDATE items SET reorder_point = 0 WHERE reorder_point IS NULL";
    if ($conn->query($update_sql)) {
        echo "<div style='color:green;font-weight:bold;'>All items with null reorder_point have been updated to 0.</div>";
    } else {
        echo "<div style='color:red;font-weight:bold;'>Error updating items: {$conn->error}</div>";
    }
}

// SORT LOGIC
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'stock_number';
$order_clause = '';

switch ($sort_by) {
    case 'item_name':
        $order_clause = "ORDER BY i.item_name ASC";
        break;
    case 'quantity_low':
        $order_clause = "ORDER BY i.quantity_on_hand ASC";
        break;
    case 'quantity_high':
        $order_clause = "ORDER BY i.quantity_on_hand DESC";
        break;
    case 'cost_low':
        $order_clause = "ORDER BY i.unit_cost ASC";
        break;
    case 'cost_high':
        $order_clause = "ORDER BY i.unit_cost DESC";
        break;
    case 'stock_number':
    default:
        $order_clause = "ORDER BY i.stock_number ASC";
        break;
}

// SEARCH FILTER
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = '';
if ($search !== '') {
    $esc = $conn->real_escape_string($search);
    $whereClause = " WHERE (i.stock_number LIKE '%$esc%' OR i.item_name LIKE '%$esc%' OR i.description LIKE '%$esc%' OR i.unit LIKE '%$esc%')";
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'check_stock':
            if (isset($_POST['stock_number'])) {
                $stock_number = $_POST['stock_number'];
                $stmt = $conn->prepare("SELECT * FROM items WHERE stock_number = ? LIMIT 1");
                $stmt->bind_param("s", $stock_number);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    echo json_encode([
                        'exists' => true,
                        'item' => [
                            'item_name' => $row['item_name'],
                            'description' => $row['description'],
                            'unit' => $row['unit'],
                            'reorder_point' => $row['reorder_point'],
                            'unit_cost' => $row['unit_cost'],
                            'iar' => $row['iar']
                        ]
                    ]);

                } else {
                    echo json_encode(['exists' => false]);
                }
                $stmt->close();
            }
            break;
            
        case 'delete':
            if (isset($_POST['id'])) {
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error deleting item']);
                }
                $stmt->close();
            }
            break;
            
        case 'add':
            if (isset($_POST['stock_number'])) {
                
                $stock_number = $_POST['stock_number'];
                $item_name = $_POST['item_name'];
                $description = $_POST['description'];
                $unit = $_POST['unit'];
                $reorder_point = intval($_POST['reorder_point']);
                $unit_cost = floatval($_POST['unit_cost']);
                $quantity_on_hand = intval($_POST['quantity_on_hand']);
                $iar = $_POST['iar'];
                
                // Check if stock number already exists
                $check_stmt = $conn->prepare("SELECT item_id FROM items WHERE stock_number = ?");
                $check_stmt->bind_param("s", $stock_number);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Add new inventory entry for existing item
                    $existing_item = $check_result->fetch_assoc();
                    $entry_stmt = $conn->prepare("INSERT INTO inventory_entries (item_id, quantity, unit_cost) VALUES (?, ?, ?)");
                    $entry_stmt->bind_param("iid", $existing_item['item_id'], $quantity_on_hand, $unit_cost);
                    
                    // Check if this is the first entry for this item
                    $count_stmt = $conn->prepare("SELECT COUNT(*) as entry_count FROM inventory_entries WHERE item_id = ?");
                    $count_stmt->bind_param("i", $existing_item['item_id']);
                    $count_stmt->execute();
                    $count_result = $count_stmt->get_result();
                    $count_row = $count_result->fetch_assoc();

                    $count_stmt->close();
                    if ($entry_stmt->execute()) {
                        // Update the main item's quantity_on_hand by adding the new entry quantity
                        $update_qty_stmt = $conn->prepare("UPDATE items SET quantity_on_hand = quantity_on_hand + ? WHERE item_id = ?");
                        $update_qty_stmt->bind_param("ii", $quantity_on_hand, $existing_item['item_id']);
                        $update_qty_stmt->execute();
                        $update_qty_stmt->close();

                        updateAverageCost($conn, $existing_item['item_id']);
                        //Log History
                        logItemHistory($conn, $existing_item['item_id'], $quantity_on_hand, 'entry');


                        echo json_encode([
                            'success' => true, 
                            'message' => 'New inventory entry added',
                            'updated' => true,
                            'item_id' => $existing_item['item_id'],
                            'has_multiple_entries' => true  // ADD THIS LINE
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error adding inventory entry']);
                    }
                    $entry_stmt->close();
                } else {
                    // Insert new item
                $stmt = $conn->prepare("
                    INSERT INTO items (stock_number, item_name, description, iar, unit, reorder_point, unit_cost, quantity_on_hand, initial_quantity)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssssiidi", $stock_number, $item_name, $description, $iar, $unit, $reorder_point, $unit_cost, $quantity_on_hand, $quantity_on_hand);
                    
                    if ($stmt->execute()) {
                        $new_id = $conn->insert_id;

                        updateAverageCost($conn, $new_id);

                        // record history
                        logItemHistory($conn, $new_id, $quantity_on_hand, 'add');

                        echo json_encode([
                            'success' => true, 
                            'message' => 'Item added successfully',
                            'item' => [
                            'item_id' => $new_id,
                            'stock_number' => $stock_number,
                            'item_name' => $item_name,
                            'description' => $description,
                            'unit' => $unit,
                            'reorder_point' => $reorder_point,
                            'unit_cost' => $unit_cost,
                            'quantity_on_hand' => $quantity_on_hand,
                            'created_at' => date('Y-m-d H:i:s'),
                            'has_multiple_entries' => false  
                        ]
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error adding item']);
                    }
                    $stmt->close();
                }
                $check_stmt->close();
            }
            break;

case 'update':
    if (isset($_POST['item_id'])) {
        $id = intval($_POST['item_id']);
        
        // Get current item data to compare changes
        $current_stmt = $conn->prepare("
            SELECT 
                stock_number,
                item_name,
                description,
                iar,
                unit,
                reorder_point,
                unit_cost, 
                quantity_on_hand, 
                initial_quantity,
                calculated_unit_cost,
                calculated_quantity,
                (SELECT COUNT(*) FROM inventory_entries WHERE item_id = ?) as entry_count
            FROM items WHERE item_id = ?
        ");
        $current_stmt->bind_param("ii", $id, $id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        $current_data = $current_result->fetch_assoc();
        $current_stmt->close();

        if (!$current_data) {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
            break;
        }
        
        $has_multiple_entries = ($current_data['initial_quantity'] > 0 && $current_data['entry_count'] > 0);
        
        // Check if this is a selective update
        $is_selective_update = isset($_POST['selective_update']) && $_POST['selective_update'] === 'true';
        
        // Define allowed fields and their types
        $allowed_fields = [
            'stock_number' => 'string',
            'item_name' => 'string', 
            'description' => 'string',
            'iar' => 'string',
            'unit' => 'string',
            'reorder_point' => 'int',
            'unit_cost' => 'float',
            'quantity_on_hand' => 'int'
        ];
        
        // Critical fields that affect inventory entries
        $critical_fields = ['unit_cost', 'quantity_on_hand'];
        
        // Collect changed fields and their values
        $fields_to_update = [];
        $values_to_bind = [];
        $param_types = '';
        $critical_fields_changed = false;
        $updated_field_names = [];
        
        foreach ($allowed_fields as $field => $type) {
            if (isset($_POST[$field])) {
                $new_value = $_POST[$field];
                
                // Type conversion
                switch ($type) {
                    case 'int':
                        $new_value = intval($new_value);
                        $current_value = intval($current_data[$field]);
                        break;
                    case 'float':
                        $new_value = floatval($new_value);
                        $current_value = floatval($current_data[$field]);
                        break;
                    default: // string
                        $new_value = trim($new_value);
                        $current_value = trim($current_data[$field]);
                        break;
                }
                
                // Check if value actually changed
                $value_changed = false;
                if ($type === 'float') {
                    $value_changed = abs($new_value - $current_value) > 0.001;
                } else {
                    $value_changed = $new_value !== $current_value;
                }
                
                if ($value_changed || !$is_selective_update) {
                    $fields_to_update[] = "$field = ?";
                    $values_to_bind[] = $new_value;
                    $param_types .= ($type === 'string') ? 's' : (($type === 'int') ? 'i' : 'd');
                    $updated_field_names[] = ucwords(str_replace('_', ' ', $field));
                    
                    if (in_array($field, $critical_fields)) {
                        $critical_fields_changed = true;
                    }
                }
            }
        }
        
        // If no fields to update
        if (empty($fields_to_update)) {
            echo json_encode([
                'success' => true, 
                'message' => 'No changes detected',
                'updated_fields' => []
            ]);
            break;
        }
        
        // Determine update strategy based on whether critical fields changed
        if ($has_multiple_entries && $critical_fields_changed) {
            // Critical fields changed - clear entries and reset to new base values
            $delete_entries_stmt = $conn->prepare("DELETE FROM inventory_entries WHERE item_id = ?");
            $delete_entries_stmt->bind_param("i", $id);
            $delete_entries_stmt->execute();
            $delete_entries_stmt->close();
            
            // Add fields to clear calculated values if critical fields changed
            $fields_to_update[] = "calculated_unit_cost = NULL";
            $fields_to_update[] = "calculated_quantity = NULL"; 
            $fields_to_update[] = "average_unit_cost = NULL";
            
            // Update initial_quantity to match quantity_on_hand if quantity changed
            if ($critical_fields_changed) {
                $new_qty = isset($_POST['quantity_on_hand']) ? intval($_POST['quantity_on_hand']) : $current_data['quantity_on_hand'];
                $fields_to_update[] = "initial_quantity = ?";
                $values_to_bind[] = $new_qty;
                $param_types .= 'i';
            }

            
            $message = 'Updated: ' . implode(', ', $updated_field_names) . ' (all entries cleared due to critical changes)';
            $change_type = 'entries_cleared_and_updated';
            $final_has_multiple = false;
            
        } else if ($has_multiple_entries && !$critical_fields_changed) {
            // Only non-critical fields changed - preserve entries and calculated values
            // Remove critical fields from update if they somehow got included
            $non_critical_updates = [];
            $non_critical_values = [];
            $non_critical_types = '';
            $non_critical_names = [];
            
            for ($i = 0; $i < count($fields_to_update); $i++) {
                $field_name = explode(' = ', $fields_to_update[$i])[0];
                if (!in_array($field_name, $critical_fields)) {
                    $non_critical_updates[] = $fields_to_update[$i];
                    if (isset($values_to_bind[$i])) {
                        $non_critical_values[] = $values_to_bind[$i];
                        $non_critical_types .= substr($param_types, $i, 1);
                        $non_critical_names[] = $updated_field_names[$i];
                    }
                }
            }
            
            $fields_to_update = $non_critical_updates;
            $values_to_bind = $non_critical_values;
            $param_types = $non_critical_types;
            $updated_field_names = $non_critical_names;
            
            $message = empty($updated_field_names) ? 
                'No non-critical fields to update (entries preserved)' : 
                'Updated: ' . implode(', ', $updated_field_names) . ' (entries preserved)';
            $change_type = 'update_non_critical';
            $final_has_multiple = true;
            
        } else {
    // No multiple entries - regular selective update
    $message = 'Updated: ' . implode(', ', $updated_field_names);
    $change_type = 'selective_update';
    $final_has_multiple = false;

    // If quantity changed, also update initial_quantity to match
    if (in_array('quantity_on_hand', array_keys($_POST))) {
        $fields_to_update[] = "initial_quantity = ?";
        $values_to_bind[] = intval($_POST['quantity_on_hand']);
        $param_types .= 'i';

        // Optional: add to updated field names list
        $updated_field_names[] = "Initial Quantity";
    }
}

        
        // Execute the update if there are fields to update
        if (!empty($fields_to_update)) {
            $sql = "UPDATE items SET " . implode(', ', $fields_to_update) . " WHERE item_id = ?";
            $values_to_bind[] = $id;
            $param_types .= 'i';
            
            $stmt = $conn->prepare($sql);
            if (!empty($values_to_bind)) {
                $stmt->bind_param($param_types, ...$values_to_bind);
            }
            
            if ($stmt->execute()) {
                // Log history
                // Get quantity change (if provided), else null
                    $qty_change = isset($_POST['quantity_on_hand']) 
                        ? intval($_POST['quantity_on_hand']) 
                        : null;

                    // Log history with correct item ID
                    logItemHistory($conn, $id, $qty_change, 'update');

                // Get updated item data for response
                $updated_stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
                $updated_stmt->bind_param("i", $id);
                $updated_stmt->execute();
                $updated_result = $updated_stmt->get_result();
                $updated_item = $updated_result->fetch_assoc();
                $updated_stmt->close();
                
                echo json_encode([
                    'success' => true, 
                    'message' => $message,
                    'updated_fields' => $updated_field_names,
                    'item' => [
                        'item_id' => $id,
                        'stock_number' => $updated_item['stock_number'],
                        'item_name' => $updated_item['item_name'],
                        'description' => $updated_item['description'],
                        'unit' => $updated_item['unit'],
                        'reorder_point' => $updated_item['reorder_point'],
                        'unit_cost' => $updated_item['unit_cost'],
                        'quantity_on_hand' => $updated_item['quantity_on_hand'],
                        'has_multiple_entries' => $final_has_multiple
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Error updating item: ' . $stmt->error
                ]);
            }
            $stmt->close();
        } else {
            echo json_encode([
                'success' => true, 
                'message' => 'No changes to apply',
                'updated_fields' => []
            ]);
        }
    }
    break;
    
    case 'clear_entries':
    if (isset($_POST['item_id'])) {
        $item_id = intval($_POST['item_id']);

        // Fetch calculated values to preserve
        $fetch_stmt = $conn->prepare("SELECT calculated_quantity, calculated_unit_cost FROM items WHERE item_id = ?");
        $fetch_stmt->bind_param("i", $item_id);
        $fetch_stmt->execute();
        $fetch_result = $fetch_stmt->get_result();
        $item_data = $fetch_result->fetch_assoc();
        $fetch_stmt->close();

        $new_qty = $item_data['calculated_quantity'] ?? 0;
        $new_cost = $item_data['calculated_unit_cost'] ?? 0;

        // Delete all inventory entries
        $delete_stmt = $conn->prepare("DELETE FROM inventory_entries WHERE item_id = ?");
        $delete_stmt->bind_param("i", $item_id);

        if ($delete_stmt->execute()) {
            // Reset base using calculated values
            $update_stmt = $conn->prepare("
                UPDATE items SET 
                    quantity_on_hand = ?, 
                    unit_cost = ?, 
                    initial_quantity = ?, 
                    calculated_quantity = NULL, 
                    calculated_unit_cost = NULL, 
                    average_unit_cost = NULL 
                WHERE item_id = ?
            ");
            $update_stmt->bind_param("idii", $new_qty, $new_cost, $new_qty, $item_id);

            if ($update_stmt->execute()) {
                logItemHistory($conn, $item_id, null, 'cleared');
                echo json_encode(['success' => true, 'message' => 'Entries cleared. Current totals saved as base.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating base values.']);
            }

            $update_stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error clearing entries.']);
        }

        $delete_stmt->close();
    }
    break;
    case 'check_entries':
    if (isset($_POST['item_id'])) {
        $item_id = intval($_POST['item_id']);
        
        $stmt = $conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM inventory_entries WHERE item_id = ?) as entry_count,
                initial_quantity
            FROM items WHERE item_id = ?
        ");
        $stmt->bind_param("ii", $item_id, $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        $hasMultipleEntries = ($data['initial_quantity'] > 0 && $data['entry_count'] > 0);
        
        echo json_encode([
            'hasMultipleEntries' => $hasMultipleEntries,
            'entryCount' => $data['entry_count']
        ]);
    }
    break;

        }
    
        
    $conn->close();
    exit();

    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TESDA Inventory Management System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f0f7";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="container">
    <h2>Office Supplies</h2>

    <form id="inventory-filters" method="get" class="filters">
        <div class="control">
            <label for="sort-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001F80;">
                <i class="fas fa-sort"></i> Sort by:
            </label>
            <select id="sort-select" name="sort" onchange="this.form.submit()">
                <option value="stock_number" <?= ($sort_by == 'stock_number') ? 'selected' : '' ?>>Stock Number (A-Z)</option>
                <option value="item_name" <?= ($sort_by == 'item_name') ? 'selected' : '' ?>>Item Name (A-Z)</option>
                <option value="quantity_low" <?= ($sort_by == 'quantity_low') ? 'selected' : '' ?>>Quantity (Low to High)</option>
                <option value="quantity_high" <?= ($sort_by == 'quantity_high') ? 'selected' : '' ?>>Quantity (High to Low)</option>
                <option value="cost_low" <?= ($sort_by == 'cost_low') ? 'selected' : '' ?>>Unit Cost (Low to High)</option>
                <option value="cost_high" <?= ($sort_by == 'cost_high') ? 'selected' : '' ?>>Unit Cost (High to Low)</option>
            </select>
        </div>
        <div class="control" style="flex: 1; display: flex; align-items: center; gap: 10px; justify-content: flex-start; padding-left: 20px;">
            <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001F80;">
                <i class="fas fa-search"></i> Search:
            </label>
            <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by stock number, item name, description, or unit..." />
        </div>
        <div class="control" style="display: flex; align-items: center; gap: 10px;">
            <button type="button" class="btn btn-success" onclick="document.getElementById('addModal').style.display='block'">
                <i class="fas fa-plus"></i> Add New Item
            </button>
            <button type="button" class="btn btn-success" onclick="window.location.href='add_multiple_items.php'">
                <i class="fas fa-box"></i> Restock Items
            </button>
        </div>
    </form>

    <div class="table-container">
        <table id="inventoryTable">
            <thead>
                <tr>
                    <th><i class="fas fa-barcode"></i> Stock Number</th>
                    <th><i class="fas fa-tag"></i> Item</th>
                    <th><i class="fas fa-align-left"></i> Description</th>
                    <th><i class="fas fa-ruler"></i> Unit</th>
                    <th><i class="fas fa-cubes"></i> Quantity</th>
                    <th><i class="fas fa-dollar-sign"></i> Unit Cost</th>
                    <th><i class="fas fa-calculator"></i> Total Cost</th>
                    <th><i class="fas fa-exclamation-triangle"></i> Reorder Point</th>
                    <th><i class="fas fa-clock"></i> Last Updated</th>
                    <th><i class="fas fa-cogs"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT i.*, 
                    (COALESCE(i.calculated_quantity, i.quantity_on_hand) * CASE 
                        WHEN i.calculated_unit_cost IS NOT NULL THEN i.calculated_unit_cost
                        WHEN (i.initial_quantity > 0 AND (SELECT COUNT(*) FROM inventory_entries ie WHERE ie.item_id = i.item_id) > 0)
                        THEN ((i.initial_quantity * i.unit_cost) + COALESCE((SELECT SUM(ie.quantity * ie.unit_cost) FROM inventory_entries ie WHERE ie.item_id = i.item_id), 0)) / (i.initial_quantity + COALESCE((SELECT SUM(ie.quantity) FROM inventory_entries ie WHERE ie.item_id = i.item_id), 0))
                        ELSE i.unit_cost 
                    END) as total_cost,
                    (SELECT COUNT(*) FROM inventory_entries ie WHERE ie.item_id = i.item_id) as entry_count,
                    CASE 
                        WHEN i.calculated_unit_cost IS NOT NULL THEN i.calculated_unit_cost
                        WHEN (i.initial_quantity > 0 AND (SELECT COUNT(*) FROM inventory_entries ie WHERE ie.item_id = i.item_id) > 0)
                        THEN ((i.initial_quantity * i.unit_cost) + COALESCE((SELECT SUM(ie.quantity * ie.unit_cost) FROM inventory_entries ie WHERE ie.item_id = i.item_id), 0)) / (i.initial_quantity + COALESCE((SELECT SUM(ie.quantity) FROM inventory_entries ie WHERE ie.item_id = i.item_id), 0))
                        ELSE i.unit_cost 
                    END as calculated_average_cost,
                    CASE 
                        WHEN (i.initial_quantity > 0 AND (SELECT COUNT(*) FROM inventory_entries ie WHERE ie.item_id = i.item_id) > 0)
                        THEN 1
                        ELSE 0 
                    END as has_multiple_entries,
                    COALESCE(i.calculated_quantity, i.quantity_on_hand) as display_quantity
                    FROM items i 
                    $whereClause
                    $order_clause";
                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $total_cost = $row["total_cost"];
                        $status_class = $row["quantity_on_hand"] <= $row["reorder_point"] ? 'status-low' : 'status-normal';
                        
                        echo "<tr data-id='{$row['item_id']}'>
                            <td><strong>{$row['stock_number']}</strong></td>
                            <td>{$row['item_name']}</strong></td>
                            <td>{$row['description']}</td>
                            <td>{$row['unit']}</td>
                            <td class='quantity-cell'>
                                <div class='main-quantity'>
                                    <span class='" . ($row['display_quantity'] <= $row['reorder_point'] ? 'status-low' : 'status-normal') . "'>
                                        " . $row['display_quantity'] . "
                                    </span>
                                </div>
                                <div class='sub-entries' id='sub-entries-{$row['item_id']}'>
                                    <!-- Sub-entries will be loaded here -->
                                </div>
                            </td>
                            <td class='cost-cell'>
                                <div class='main-cost'>₱ " . number_format((isset($row['calculated_unit_cost']) && $row['calculated_unit_cost'] !== null ? $row['calculated_unit_cost'] : ($row['has_multiple_entries'] ? $row['calculated_average_cost'] : $row['unit_cost'])), 2) . " " . ((isset($row['calculated_unit_cost']) && $row['calculated_unit_cost'] !== null) || $row['has_multiple_entries'] ? '(average)' : '') . "</div>                            <div class='sub-entries' id='sub-cost-{$row['item_id']}'>
                                    <!-- Sub-entries will be loaded here -->
                                </div>
                            </td>
                            <td class='currency'>₱ " . number_format($total_cost, 2) . "</td>
                            <td>{$row['reorder_point']}</td>
                            <td>" . date('M d, Y H:i', strtotime($row['created_at'] ?? 'now')) . "</td>
                            <td class='actions-cell'>
                                <div class='actions-row'>
                                    <button 
                                        class='btn edit-btn' 
                                        onclick='openEditModal(this)'
                                        data-id='{$row['item_id']}'
                                        data-stock_number='{$row['stock_number']}'
                                        data-item_name= '{$row['item_name']}'
                                        data-description='{$row['description']}'
                                        data-unit='{$row['unit']}'
                                        data-reorder_point='{$row['reorder_point']}'
                                        data-unit_cost='" . (isset($row['calculated_unit_cost']) && $row['calculated_unit_cost'] !== null ? $row['calculated_unit_cost'] : ($row['has_multiple_entries'] ? $row['calculated_average_cost'] : $row['unit_cost'])) . "'                                        data-quantity_on_hand='{$row['quantity_on_hand']}'
                                        data-iar='{$row['iar']}'
                                        title='Edit Item'
                                    >
                                        <i class='fas fa-edit'></i> Edit
                                    </button>

                                    <button 
                                        class='btn delete-btn' 
                                        onclick='deleteItem({$row['item_id']})' 
                                        title='Delete Item'
                                    >
                                        <i class='fas fa-trash'></i> Delete
                                    </button>
                                </div>
                                
                                " . ($row['has_multiple_entries'] ? "
                                <div class='actions-row actions-row-full'>
                                    <button 
                                        class='btn clear-entries-btn' 
                                        onclick='clearEntries({$row['item_id']})' 
                                        title='Clear All Entries'
                                        style='width: 100%; max-width: none;'
                                    >
                                        <i class='fas fa-broom'></i> Clear Entries
                                    </button>
                                </div>
                                " : "") . "
                            </td>
                        </tr>";

                        // Add sub-entries query and display
                        $sub_sql = "SELECT quantity, unit_cost, created_at FROM inventory_entries WHERE item_id = {$row['item_id']} ORDER BY created_at DESC";
                        $sub_result = $conn->query($sub_sql);

                        // Show sub-entries only if there are inventory entries
                        $total_entries = $sub_result->num_rows;
                        if ($total_entries > 0) {
                            echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const subQtyContainer = document.getElementById('sub-entries-{$row['item_id']}');
                                const subCostContainer = document.getElementById('sub-cost-{$row['item_id']}');
                                let subQtyHTML = '';
                                let subCostHTML = '';
                            
                                // Add original quantity first (only when entries exist)
                                if ({$row['initial_quantity']} > 0) {
                                    subQtyHTML += '<div class=\"sub-entry initial\">{$row['initial_quantity']} (initial)</div>';
                                    // Only show initial cost breakdown when there are multiple entries
                                    if ({$row['has_multiple_entries']}) {
                                        subCostHTML += '<div class=\"sub-entry initial\">₱ " . number_format($row['unit_cost'], 2) . " (initial)</div>';
                                    }
                                }";
                            
                            // Add inventory entries
                            $sub_result->data_seek(0); 
                            while ($sub_row = $sub_result->fetch_assoc()) {
                                echo "
                                subQtyHTML += '<div class=\"sub-entry\">{$sub_row['quantity']}</div>';
                                subCostHTML += '<div class=\"sub-entry\">₱ " . number_format($sub_row['unit_cost'], 2) . "</div>';";
                            }
                            
                            echo "
                                subQtyContainer.innerHTML = subQtyHTML;
                                subCostContainer.innerHTML = subCostHTML;
                            });
                            </script>";
                        }
                    }
                            
                } else {
                    echo "<tr><td colspan='9' style='text-align: center; color: #666; font-style: italic;'>
                            <i class='fas fa-inbox'></i> No inventory data found.
                          </td></tr>";
                }

                $conn->close();
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Item Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <h3><i class="fas fa-plus-circle"></i> Add New Item</h3>
        <form id="addForm">
            <label for="add_stock_number">Stock Number</label>
            <input type="text" name="stock_number" id="add_stock_number" placeholder="Enter stock number" required>
            <div id="stock_status" class="stock-status"></div>

            <label for="add_iar">I.A.R</label>
            <input type="text" name="iar" id="add_iar" placeholder="Enter I.A.R" required readonly>

            <label for="add_item_name">Item Name</label>
            <input type="text" name="item_name" id="add_item_name" placeholder="Name" required readonly>

            <label for="add_description">Description</label>
            <input type="text" name="description" id="add_description" placeholder="Description" required readonly>

            <label for="add_unit">Unit (pcs, box, etc.)</label>
            <input type="text" name="unit" id="add_unit" placeholder="Unit (pcs, box, etc.)" required readonly>

            <label for="add_reorder_point">Reorder Point</label>
            <input type="number" name="reorder_point" id="add_reorder_point" placeholder="Reorder Point" required min="0" readonly>

            <label for="add_unit_cost">Unit Cost (₱)</label>
            <input type="number" step="0.01" name="unit_cost" id="add_unit_cost" placeholder="Unit Cost (₱)" required min="0" readonly>

            <label for="add_quantity_on_hand">Quantity on Hand</label>
            <input type="number" name="quantity_on_hand" id="add_quantity_on_hand" placeholder="Quantity on Hand" required min="0">
            
            <button type="submit" class="save-btn">
                <i class="fas fa-save"></i> Save Item
            </button>
        </form>
    </div>
</div>

<!-- Edit Item Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
        <h3><i class="fas fa-edit"></i> Edit Item</h3>
        <form id="editForm">
            <input type="hidden" name="item_id" id="edit_item_id">

            <label for="edit_stock_number">Stock Number</label>
            <input type="text" name="stock_number" id="edit_stock_number" placeholder="e.g. 1001" required>

            <label for="edit_iar">I.A.R</label>
            <input type="text" name="iar" id="edit_iar" placeholder="Enter I.A.R" required>

            <label for="edit_item_name">Item Name</label>
            <input type="text" name="item_name" id="edit_item_name" placeholder="e.g Hammer" required>

            <label for="edit_description">Description</label>
            <input type="text" name="description" id="edit_description" placeholder="e.g. Red" required>

            <label for="edit_unit">Unit</label>
            <input type="text" name="unit" id="edit_unit" placeholder="pcs, box, etc." required>

            <label for="edit_reorder_point">Reorder Point</label>
            <input type="number" name="reorder_point" id="edit_reorder_point" placeholder="e.g. 10" required min="0">

            <label for="edit_unit_cost">Unit Cost (₱)</label>
            <input type="number" step="0.01" name="unit_cost" id="edit_unit_cost" placeholder="e.g. 25.00" required min="0">

            <label for="edit_quantity_on_hand">Quantity on Hand</label>
            <input type="number" name="quantity_on_hand" id="edit_quantity_on_hand" placeholder="e.g. 100" required min="0">
            
            <button type="submit" class="save-btn">
                <i class="fas fa-save"></i> Update Item
            </button>
        </form>
    </div>
</div>

<!-- Notification -->
<div id="notification" class="notification"></div>

<script>
// Handle search form submission on Enter key
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('inventory-filters').submit();
            }
        });
    }
});
</script>

<script src="js/inventory_script.js?v=<?= time() ?>"></script>

</body>
</html>
