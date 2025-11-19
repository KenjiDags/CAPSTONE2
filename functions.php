<?php 

function updateAverageCost($conn, $item_id) {
    // Get item info and entries
    $sql = "
        SELECT 
            i.unit_cost,
            i.initial_quantity,
            i.average_unit_cost,
            i.calculated_quantity,
            i.calculated_unit_cost
        FROM items i
        WHERE i.item_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) return;

    // Get last added positive entry's cost
    $last_entry_stmt = $conn->prepare("
        SELECT unit_cost 
        FROM inventory_entries 
        WHERE item_id = ? AND quantity > 0 
        ORDER BY created_at DESC LIMIT 1
    ");
    $last_entry_stmt->bind_param("i", $item_id);
    $last_entry_stmt->execute();
    $last_entry = $last_entry_stmt->get_result()->fetch_assoc();
    $last_entry_stmt->close();

    // If no entries, just set the average = unit cost
    if (!$last_entry && $item['initial_quantity'] > 0) {
        $avg_cost = $item['unit_cost'];
        $qty = $item['initial_quantity'];
    } else {
        // Determine the base cost for averaging
        if ($item['calculated_quantity'] == 0 && $item['average_unit_cost'] !== null) {
            // Stock was issued completely, start from preserved average
            $base_cost = $item['average_unit_cost'];
        } elseif ($item['initial_quantity'] > 0) {
            // First addition after initial stock
            $base_cost = $item['unit_cost'];
        } else {
            // If no initial quantity but we have an average stored
            $base_cost = $item['average_unit_cost'] ?? $item['unit_cost'];
        }

        $new_cost = $last_entry['unit_cost'] ?? $base_cost;

        // Arithmetic mean with preserved base cost + new entry cost
        $avg_cost = ($base_cost + $new_cost) / 2;

        // Get updated quantity
        $qty_stmt = $conn->prepare("SELECT SUM(quantity) as total_qty FROM inventory_entries WHERE item_id = ?");
        $qty_stmt->bind_param("i", $item_id);
        $qty_stmt->execute();
        $total_qty = $qty_stmt->get_result()->fetch_assoc()['total_qty'] ?? 0;
        $qty_stmt->close();

        $qty = max(0, $item['initial_quantity'] + $total_qty);
    }

    // Save new values
    $update = $conn->prepare("
        UPDATE items
        SET 
            average_unit_cost = ?,
            calculated_unit_cost = ?,
            calculated_quantity = ?
        WHERE item_id = ?
    ");
    $update->bind_param("ddii", $avg_cost, $avg_cost, $qty, $item_id);
    $update->execute();
    $update->close();
}


// Ensure the semi_expendable_history table exists (idempotent)
function ensure_semi_expendable_history($conn) {
    if (!$conn) { return; }
    $sql = "CREATE TABLE IF NOT EXISTS semi_expendable_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        semi_id INT NOT NULL,
        date DATE NULL,
        ics_rrsp_no VARCHAR(255) NULL,
        quantity INT DEFAULT 0,
        quantity_issued INT DEFAULT 0,
        quantity_returned INT DEFAULT 0,
        quantity_reissued INT DEFAULT 0,
        quantity_disposed INT DEFAULT 0,
        quantity_balance INT DEFAULT 0,
        office_officer_issued VARCHAR(255) NULL,
        office_officer_returned VARCHAR(255) NULL,
        office_officer_reissued VARCHAR(255) NULL,
        amount DECIMAL(15,2) DEFAULT 0,
        amount_total DECIMAL(15,2) DEFAULT 0,
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (semi_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    // Suppress fatal on create attempt; rely on mysqli error reporting elsewhere
    try { $conn->query($sql); } catch (Throwable $e) { /* no-op */ }
    // Add missing column if the table existed before (MySQL/MariaDB IF NOT EXISTS supported in common versions)
    try { $conn->query("ALTER TABLE semi_expendable_history ADD COLUMN IF NOT EXISTS amount DECIMAL(15,2) DEFAULT 0"); } catch (Throwable $e) { /* no-op */ }
}

// Ensure 'amount' column exists on both semi_expendable_property and semi_expendable_history
function ensure_semi_expendable_amount_columns($conn) {
    if (!$conn) { return; }
    try { $conn->query("ALTER TABLE semi_expendable_property ADD COLUMN IF NOT EXISTS amount DECIMAL(15,2) DEFAULT 0"); } catch (Throwable $e) { /* no-op */ }
    ensure_semi_expendable_history($conn);
}

function ensure_ics_history($conn) {
    if (!$conn) { return; }
    $sql = "CREATE TABLE IF NOT EXISTS ics_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ics_id INT NOT NULL,
        ics_item_id INT NULL,
        stock_number VARCHAR(100) NULL,
        description TEXT NULL,
        unit VARCHAR(50) NULL,
        quantity_before DECIMAL(15,4) NOT NULL DEFAULT 0,
        quantity_after DECIMAL(15,4) NOT NULL DEFAULT 0,
        quantity_change DECIMAL(15,4) NOT NULL DEFAULT 0,
        unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_cost_before DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_cost_after DECIMAL(15,2) NOT NULL DEFAULT 0,
        reference_type VARCHAR(50) NULL,
        reference_id INT NULL,
        reference_no VARCHAR(100) NULL,
        reference_details TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ics_id (ics_id),
        INDEX idx_ics_item_id (ics_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    try { $conn->query($sql); } catch (Throwable $e) { /* no-op */ }
}

// Ensure itr_history table exists (idempotent)
function ensure_itr_history($conn) {
    if (!$conn) { return; }
    $sql = "CREATE TABLE IF NOT EXISTS itr_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        itr_id INT NOT NULL,
        itr_item_id INT NULL,
        ics_id INT NULL,
        ics_item_id INT NULL,
        item_no VARCHAR(255) NULL,
        stock_number VARCHAR(100) NULL,
        description TEXT NULL,
        unit VARCHAR(50) NULL,
        transfer_qty DECIMAL(15,4) NOT NULL DEFAULT 0,
        unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        from_accountable VARCHAR(255) NULL,
        to_accountable VARCHAR(255) NULL,
        transfer_type VARCHAR(50) NULL,
        transfer_other VARCHAR(255) NULL,
        reference_no VARCHAR(100) NULL,
        reference_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_itr_id (itr_id),
        INDEX idx_itr_item_id (itr_item_id),
        INDEX idx_ics_item_id (ics_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    try { $conn->query($sql); } catch (Throwable $e) { /* no-op */ }
}

// Ensure rrsp_history table exists (idempotent)
function ensure_rrsp_history($conn) {
    if (!$conn) { return; }
    $sql = "CREATE TABLE IF NOT EXISTS rrsp_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rrsp_id INT NOT NULL,
        rrsp_item_id INT NULL,
        ics_no VARCHAR(100) NULL,
        item_description TEXT NULL,
        quantity DECIMAL(15,4) NOT NULL DEFAULT 0,
        unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        end_user VARCHAR(255) NULL,
        item_remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_rrsp_id (rrsp_id),
        INDEX idx_rrsp_item_id (rrsp_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    try { $conn->query($sql); } catch (Throwable $e) { /* no-op */ }
}

// Ensure iirusp_history table exists (idempotent)
function ensure_iirusp_history($conn) {
    if (!$conn) { return; }
    $sql = "CREATE TABLE IF NOT EXISTS iirusp_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        iirusp_id INT NOT NULL,
        iirusp_item_id INT NULL,
        semi_expendable_property_no VARCHAR(100) NULL,
        particulars TEXT NULL,
        quantity DECIMAL(15,4) NOT NULL DEFAULT 0,
        unit VARCHAR(50) NULL,
        unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
        accumulated_impairment DECIMAL(15,2) NOT NULL DEFAULT 0,
        carrying_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        remarks TEXT NULL,
        disposal_sale DECIMAL(15,2) NOT NULL DEFAULT 0,
        disposal_transfer DECIMAL(15,2) NOT NULL DEFAULT 0,
        disposal_destruction DECIMAL(15,2) NOT NULL DEFAULT 0,
        disposal_others TEXT NULL,
        disposal_total DECIMAL(15,2) NOT NULL DEFAULT 0,
        appraised_value DECIMAL(15,2) NOT NULL DEFAULT 0,
        or_no VARCHAR(100) NULL,
        sales_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_iirusp_id (iirusp_id),
        INDEX idx_iirusp_item_id (iirusp_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    try { $conn->query($sql); } catch (Throwable $e) { /* no-op */ }
}

/**
 * Check if a column exists on a given table in the current database
 *
 * @param mysqli $conn
 * @param string $table Table name (without schema)
 * @param string $column Column name
 * @return bool True if column exists, false otherwise
 */
if (!function_exists('columnExists')) {
    function columnExists($conn, $table, $column) {
        if (!$conn || !$table || !$column) { return false; }
        try {
            $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if (!$stmt) { return false; }
            $stmt->bind_param("ss", $table, $column);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();
            return $exists;
        } catch (Throwable $e) {
            // Fallback using SHOW COLUMNS (escape identifiers safely)
            $tableEsc = "`" . str_replace("`", "``", $table) . "`";
            $colVal = $conn->real_escape_string($column);
            $res = @$conn->query("SHOW COLUMNS FROM $tableEsc LIKE '$colVal'");
            if ($res) {
                $has = $res->num_rows > 0;
                $res->close();
                return $has;
            }
            return false;
        }
    }
}


function logItemHistory($conn, $item_id, ?int $quantity_change = null, string $change_type = 'update', ?int $ris_id = null) {
    // Fetch current item info
    $stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();

    if (!$item) {
        return; // No such item, skip logging
    }

    // Get previous quantity (latest history) or fallback to initial_quantity
    $prev_stmt = $conn->prepare("SELECT quantity_on_hand FROM item_history WHERE item_id = ? ORDER BY changed_at DESC LIMIT 1");
    $prev_stmt->bind_param("i", $item_id);
    $prev_stmt->execute();
    $prev_result = $prev_stmt->get_result();
    $prev_row = $prev_result->fetch_assoc();
    $prev_stmt->close();

    $previous_quantity = isset($prev_row['quantity_on_hand'])
        ? intval($prev_row['quantity_on_hand'])
        : (isset($item['initial_quantity']) ? intval($item['initial_quantity']) : 0);

    // Current quantity from items table
    $current_quantity = intval($item['quantity_on_hand']);

    // If quantity_change not provided, derive it
    if ($quantity_change === null) {
        $quantity_change = $current_quantity - $previous_quantity;
    }

    // Determine change direction (PHP 7.4 compatible)
    if ($quantity_change > 0) {
        $change_direction = 'increase';
    } elseif ($quantity_change < 0) {
        $change_direction = 'decrease';
    } else {
        $change_direction = 'no_change';
    }

    // Insert into history, including ris_id if available
    $insert = $conn->prepare("
        INSERT INTO item_history (
            item_id,
            stock_number,
            item_name,
            description,
            unit,
            reorder_point,
            unit_cost,
            quantity_on_hand,
            quantity_change,
            change_direction,
            change_type,
            ris_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Use average_unit_cost for history records instead of unit_cost
    $unit_cost = $item['average_unit_cost'] ?? $item['unit_cost'];

    $insert->bind_param(
        "issssidiissi",
        $item_id,
        $item['stock_number'],
        $item['item_name'],
        $item['description'],
        $item['unit'],
        $item['reorder_point'],
        $unit_cost,
        $current_quantity,
        $quantity_change,
        $change_direction,
        $change_type,
        $ris_id
    );

    $insert->execute();
    $insert->close();
}

?>