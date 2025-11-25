<?php ob_start(); include 'sidebar.php'; ?>
<?php require 'config.php'; ?>

<?php
require 'functions.php';
// Ensure semi_expendable_property has a 'unit' column directly (runtime-safe)
try {
    if (function_exists('columnExists') && !columnExists($conn, 'semi_expendable_property', 'unit')) {
        @$conn->query("ALTER TABLE semi_expendable_property ADD COLUMN unit VARCHAR(64) NULL AFTER item_description");
    }
} catch (Throwable $e) { /* non-fatal safeguard */ }

// Ensure ics_items has a 'unit' column (runtime-safe)
try {
    if (function_exists('columnExists') && !columnExists($conn, 'ics_items', 'unit')) {
        @$conn->query("ALTER TABLE ics_items ADD COLUMN unit VARCHAR(64) NULL AFTER quantity");
    }
} catch (Throwable $e) { /* non-fatal safeguard */ }
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Wrap everything in a transaction for consistency
    if (method_exists($conn, 'begin_transaction')) { $conn->begin_transaction(); }
    try {
    
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
        if (!$stmt->execute()) { throw new Exception('Failed to update ICS header: ' . $stmt->error); }
        $stmt->close();
        
        // Get old items (with unit_cost) to restore semi-expendable quantities and preserve original unit amounts in history
        $old_items = [];
        $stmt = $conn->prepare("SELECT stock_number, quantity, unit_cost FROM ics_items WHERE ics_id = ?");
        $stmt->bind_param("i", $ics_id);
        if (!$stmt->execute()) { throw new Exception('Failed to load old ICS items: ' . $stmt->error); }
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $old_items[$row['stock_number']] = [
                'qty' => (float)$row['quantity'],
                'unit_cost' => isset($row['unit_cost']) ? (float)$row['unit_cost'] : null,
            ];
        }
        $stmt->close();
        
        // Delete old items
        $stmt = $conn->prepare("DELETE FROM ics_items WHERE ics_id = ?");
        $stmt->bind_param("i", $ics_id);
        if (!$stmt->execute()) { throw new Exception('Failed to delete old ICS items: ' . $stmt->error); }
        $stmt->close();
        
        // Restore semi-expendable balances from old items
        foreach ($old_items as $stock_no => $old) {
            // stock_no holds semi_expendable_property_no in this ICS context
            $stmt = $conn->prepare("SELECT id, quantity, quantity_issued, quantity_returned, quantity_reissued, quantity_disposed, amount FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
            $stmt->bind_param("s", $stock_no);
            if (!$stmt->execute()) { throw new Exception('Failed to load semi-expendable for reversal: ' . $stmt->error); }
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $semi_id = (int)$row['id'];
                $qty = (int)$row['quantity'];
                $returned = (int)($row['quantity_returned'] ?? 0);
                $issued = max(0, (int)$row['quantity_issued'] - (int)$old['qty']);
                $reissued = (int)$row['quantity_reissued'];
                $disposed = (int)$row['quantity_disposed'];
                $balance = max(0, $qty - ($issued + $reissued + $disposed) + $returned);
                $u = $conn->prepare("UPDATE semi_expendable_property SET quantity_issued = ?, quantity_balance = ? WHERE id = ?");
                $u->bind_param("iii", $issued, $balance, $semi_id);
                if (!$u->execute()) { $u->close(); throw new Exception('Failed to restore semi-expendable stock: ' . $u->error); }
                $u->close();
                // Optional: snapshot history on reversal
                $h = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity, quantity_issued, quantity_reissued, quantity_disposed, quantity_balance, office_officer_issued, amount, amount_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $remarks = 'ICS Edit Reversal';
                // Prefer original unit_cost from the old ICS item to preserve historical totals
                $unit_amount = isset($old['unit_cost']) && $old['unit_cost'] !== null ? (float)$old['unit_cost'] : (isset($row['amount']) ? (float)$row['amount'] : 0.0);
                $amount_total = round($unit_amount * $qty, 2);
                $h->bind_param("issiiiiisdds", $semi_id, $date_issued, $ics_no, $qty, $issued, $reissued, $disposed, $balance, $received_by, $unit_amount, $amount_total, $remarks);
                @ $h->execute();
                $h->close();
            }
        }
        
    } else {
        // Insert new ICS
        $stmt = $conn->prepare("INSERT INTO ics (ics_no, entity_name, fund_cluster, date_issued, 
                               received_by, received_by_position, received_from, received_from_position)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $ics_no, $entity_name, $fund_cluster, $date_issued, 
                         $received_by, $received_by_position, $received_from, $received_from_position);
        if (!$stmt->execute()) { throw new Exception('Failed to insert ICS header: ' . $stmt->error); }
        $ics_id = $stmt->insert_id;
        $stmt->close();
    }

    // ICS Items arrays from form
    $stock_numbers = $_POST['stock_number'];
    $issued_quantities = $_POST['issued_quantity'];
    $estimated_useful_lives = $_POST['estimated_useful_life'];
    // Serial numbers column removed from form; keep server-side optional
    $serial_numbers = isset($_POST['serial_number']) ? $_POST['serial_number'] : [];

    // Insert new items and update inventory
    for ($i = 0; $i < count($stock_numbers); $i++) {
        $stock_no = $stock_numbers[$i];
        $issued_qty = (float)$issued_quantities[$i];
        $useful_life = $estimated_useful_lives[$i];
    $serial_no = isset($serial_numbers[$i]) ? $serial_numbers[$i] : '';

        // Only insert if there's an issued quantity
        if ($issued_qty > 0) {
            // Get semi-expendable details by property no (we use stock_no to carry property_no)
            $stmt = $conn->prepare("SELECT id, item_description, remarks, unit, estimated_useful_life, amount, quantity, quantity_issued, quantity_returned, quantity_reissued, quantity_disposed, quantity_balance FROM semi_expendable_property WHERE semi_expendable_property_no = ?");
            $stmt->bind_param("s", $stock_no);
            if (!$stmt->execute()) { throw new Exception('Failed to fetch item data: ' . $stmt->error); }
            $result = $stmt->get_result();
            $item_data = $result->fetch_assoc();
            $stmt->close();

            if ($item_data) {
                // Clamp to available quantity balance
                $returned = (float)($item_data['quantity_returned'] ?? 0);
                $available = (float)$item_data['quantity_balance'];
                // Recompute available using returned
                $qty = (float)$item_data['quantity'];
                $issued = (float)$item_data['quantity_issued'];
                $reissued = (float)$item_data['quantity_reissued'];
                $disposed = (float)$item_data['quantity_disposed'];
                $available = max(0, $qty - ($issued + $reissued + $disposed) + $returned);
                if ($issued_qty > $available) { $issued_qty = $available; }
                if ($issued_qty <= 0) { continue; }
                $unit_cost = (float)$item_data['amount'];
                $total_cost = $issued_qty * $unit_cost;

                // Pre-ICS snapshot intentionally removed per request to avoid extra baseline rows
                
                // Insert ICS item
                $stmt = $conn->prepare("INSERT INTO ics_items (ics_id, stock_number, quantity, unit, unit_cost, total_cost, 
                                       description, inventory_item_no, estimated_useful_life, serial_number)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $unitVal = isset($item_data['unit']) && $item_data['unit'] !== '' ? (string)$item_data['unit'] : '';
                // Per request: do not use remarks as description; always use item_description
                $descVal = $item_data['item_description'];
                $usefulVal = (string)($item_data['estimated_useful_life'] ?? $useful_life);
                $stmt->bind_param("isdsddssss", $ics_id, $stock_no, $issued_qty, $unitVal, 
                                 $unit_cost, $total_cost, $descVal, $stock_no, $usefulVal, $serial_no);
                if (!$stmt->execute()) { throw new Exception('Failed to insert ICS item: ' . $stmt->error); }
                $stmt->close();

                // Update semi-expendable: increase issued, recalc balance
                $semi_id = (int)$item_data['id'];
                $qty = (int)$item_data['quantity'];
                $returned = (int)($item_data['quantity_returned'] ?? 0);
                $issued = (int)$item_data['quantity_issued'] + (int)$issued_qty;
                $reissued = (int)$item_data['quantity_reissued'];
                $disposed = (int)$item_data['quantity_disposed'];
                $balance = max(0, $qty - ($issued + $reissued + $disposed) + $returned);
                // Also reflect ICS reference and receiver in semi-expendable table
                // Append new ICS number to existing ics_rrsp_no (comma-separated) so multiple ICS references are preserved
                $u = $conn->prepare("UPDATE semi_expendable_property SET quantity_issued = ?, quantity_balance = ?, ics_rrsp_no = CASE WHEN COALESCE(ics_rrsp_no, '') = '' THEN ? ELSE CONCAT(ics_rrsp_no, ',', ?) END, office_officer_issued = ?, fund_cluster = ? WHERE id = ?");
                $u->bind_param("iissssi", $issued, $balance, $ics_no, $ics_no, $received_by, $fund_cluster, $semi_id);
                if (!$u->execute()) { $u->close(); throw new Exception('Failed to update semi-expendable stock: ' . $u->error); }
                $u->close();

                // Insert history snapshot post-issuance; avoid duplicate row for same ICS + semi if already recorded.
                $amount_total = round($item_data['amount'] * $qty, 2);
                if ($chk = $conn->prepare("SELECT 1 FROM semi_expendable_history WHERE semi_id = ? AND ics_rrsp_no = ? LIMIT 1")) {
                    $chk->bind_param('is', $semi_id, $ics_no);
                    if ($chk->execute()) {
                        $existsRes = $chk->get_result();
                        $exists = $existsRes && $existsRes->num_rows > 0;
                    } else { $exists = false; }
                    $chk->close();
                } else { $exists = false; }
                if (!$exists) {
                    if ($h = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity, quantity_issued, quantity_reissued, quantity_disposed, quantity_balance, office_officer_issued, amount, amount_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")) {
                        $remarks = '';
                        $h->bind_param("issiiiiisdds", $semi_id, $date_issued, $ics_no, $qty, $issued, $reissued, $disposed, $balance, $received_by, $unit_cost, $amount_total, $remarks);
                        @ $h->execute();
                        $h->close();
                    }
                }
            }
        }
    }

    // Redirect after successful submission
        if (method_exists($conn, 'commit')) { $conn->commit(); }
        if ($is_editing) {
            header("Location: view_ics.php?ics_id=" . $ics_id);
        } else {
            header("Location: ics.php");
        }
        exit();
    } catch (Throwable $e) {
        if (method_exists($conn, 'rollback')) { $conn->rollback(); }
        // Basic error handling: show a simple message
        echo '<div style="color:#b91c1c; background:#fee2e2; padding:12px; border:1px solid #fca5a5; margin:10px 0;">'
            . 'ICS save failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Check if we're editing an existing ICS
$is_editing = isset($_GET['ics_id']) && !empty($_GET['ics_id']);
$ics_id = $is_editing ? (int)$_GET['ics_id'] : null;

// Initialize variables
$ics_data = [];
$ics_items = [];
$selected_category = 'All';
$category_options = [];

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

// Generate or show ICS number for the form
if ($is_editing) {
    $auto_ics_number = $ics_data['ics_no'] ?? '';
} else {
    $auto_ics_number = generateICSNumber($conn);
    if (!$auto_ics_number || $auto_ics_number === '') {
        $auto_ics_number = generateICSNumberSimple($conn);
    }
}

// Prepare category filter defaults and options (All by default)
if (isset($_GET['filter_category']) && $_GET['filter_category'] !== '') {
    $selected_category = $_GET['filter_category'];
}
// Build category options purely from DB distinct categories
if (columnExists($conn, 'semi_expendable_property', 'category')) {
    $catRes = $conn->query("SELECT DISTINCT category FROM semi_expendable_property WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
    if ($catRes) {
        while ($r = $catRes->fetch_assoc()) { $category_options[] = $r['category']; }
    }
}

// Function to generate the next ICS number (only for new ICS)
// New format: "NN-YY" where NN is a 2+ digit serial (zero-padded to 2) and YY is the last two digits of the year
function generateICSNumber($conn) {
    $yy = date('y'); // last two digits of the current year (e.g., '25' for 2025)
    $current_year = (int)date('Y');

    // Find the latest ICS no for this year matching YY-NN pattern
    $pattern = $yy . '-%';
    $query = "SELECT ics_no FROM ics WHERE YEAR(date_issued) = ? AND ics_no LIKE ? ORDER BY ics_id DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("MySQL prepare error in generateICSNumber: " . $conn->error);
        return $yy . '-01';
    }

    $stmt->bind_param('is', $current_year, $pattern);
    if (!$stmt->execute()) {
        error_log("MySQL execute error in generateICSNumber: " . $stmt->error);
        $stmt->close();
        return $yy . '-01';
    }

    $result = $stmt->get_result();
    $next_serial = 1;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_ics_no = $row['ics_no'];
        // Extract the number part after the dash (format: YY-NN)
        if (preg_match('/(\d+)-(\d+)/', $last_ics_no, $matches)) {
            $next_serial = ((int)$matches[2]) + 1;
        }
    }
    $stmt->close();

    // Zero-pad to 2 digits
    $serial_formatted = str_pad((string)$next_serial, 2, '0', STR_PAD_LEFT);
    return $yy . '-' . $serial_formatted;
}

// Alternative simpler version as a fallback
// Uses count of ICS in the current year and formats as YY-NN
function generateICSNumberSimple($conn) {
    $year = (int)date('Y');
    $yy = date('y'); // last two digits of the current year
    $pattern = $yy . '-%';

    $query = "SELECT COUNT(*) AS count FROM ics WHERE YEAR(date_issued) = ? AND ics_no LIKE ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("MySQL prepare error in generateICSNumberSimple: " . $conn->error);
        return $yy . '-01';
    }

    $stmt->bind_param('is', $year, $pattern);
    if (!$stmt->execute()) {
        error_log("MySQL execute error in generateICSNumberSimple: " . $stmt->error);
        $stmt->close();
        return $yy . '-01';
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $count = $row && isset($row['count']) ? (int)$row['count'] : 0;
    $next_serial = $count + 1;
    $serial_formatted = str_pad((string)$next_serial, 2, '0', STR_PAD_LEFT);
    return $yy . '-' . $serial_formatted;
}

// columnExists helper is provided in functions.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_editing ? 'Edit ICS Form' : 'Add ICS Form'; ?></title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <style>
        /* Lightweight layout helpers for better UX */
        .section-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:16px; }
        .section-card h3 { margin-top:0; margin-bottom:12px; }
        .form-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
        .form-grid .form-group { display:flex; flex-direction:column; }
        @media (max-width: 800px) { .form-grid { grid-template-columns: 1fr; } }
        .actions { display:flex; gap:10px; align-items:center; margin-top:14px; }
        /* Scrollable items table, preserve existing theme/colors */
    /* Frame: remove top border and flatten top corners to avoid a visible white strip */
    .table-frame { border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 8px 8px; overflow: hidden; }
    /* Viewport: use the same gradient as header behind the sticky thead */
    .table-viewport { max-height: 420px; overflow-y: auto; overflow-x: auto; scrollbar-gutter: stable; background: var(--white); }
    /* Override global table overflow so sticky headers aren't clipped */
    #itemsTable { overflow: visible !important; width: 100%; border-collapse: collapse; background: transparent !important; border-radius: 0 !important; margin-top: 0 !important; }
    /* Make header stick within the scrolling viewport */
    #itemsTable thead th { position: sticky; top: 0; z-index: 3; background: var(--blue-gradient); color: #fff; }
    /* Ensure any top corners are flat across container/viewport/table */
    .table-frame, .table-viewport, #itemsTable { border-top-left-radius: 0 !important; border-top-right-radius: 0 !important; }
    /* Spacing improvements */
    #itemsTable th, #itemsTable td { padding: 10px 12px; vertical-align: middle; }
    #itemsTable thead th { height: 44px; }
    .search-container { margin: 8px 0 12px !important; }
    .form-grid .form-group input, .form-grid .form-group select, .form-grid .form-group textarea { padding: 8px 10px; }
    </style>
</head>
<body>
    <div class="edit-ics-page content edit-ris-page">
        <h2><?php echo $is_editing ? 'Edit ICS Form' : 'Add ICS Form'; ?></h2>

        <form method="post" action="">
            <?php if ($is_editing): ?>
                <input type="hidden" name="ics_id" value="<?php echo $ics_id; ?>">
                <input type="hidden" name="is_editing" value="1">
            <?php endif; ?>
            
            <div class="section-card">
                <h3>ICS Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Entity Name:</label>
                        <input type="text" name="entity_name" value="<?php echo htmlspecialchars($ics_data['entity_name'] ?? 'TESDA Regional Office'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Fund Cluster:</label>
                        <input type="text" name="fund_cluster" value="<?php echo htmlspecialchars($ics_data['fund_cluster'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>ICS No.:</label>
                        <input type="text" name="ics_no" value="<?php echo htmlspecialchars($auto_ics_number); ?>" readonly style="background-color: #f5f5f5;">
                    </div>
                    <div class="form-group">
                        <label>Date Issued:</label>
                        <input type="date" name="date_issued" value="<?php echo $ics_data['date_issued'] ?? date('Y-m-d'); ?>" required>
                    </div>
                </div>
            </div>

            <div class="section-card">
            <h3>ICS Items</h3>
            <div style="display:flex; gap:12px; align-items:center; margin-bottom:10px; flex-wrap:wrap;">
                <label for="filter_category" style="font-weight:600; color:#0038a8;">Category:</label>
                <select id="filter_category" name="filter_category" style="padding:8px 12px; border:2px solid #e8f0fe; border-radius:8px; background:#f8fbff;">
                    <option value="All" <?php echo ($selected_category==='All')?'selected':''; ?>>All</option>
                    <?php foreach ($category_options as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($selected_category===$cat)?'selected':''; ?>><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="clamp-notice" style="display:none; margin:0 0 10px; padding:8px 10px; background:#fef3c7; color:#92400e; border:1px solid #fcd34d; border-radius:4px;"></div>
            
            <!-- Search Container -->
            <div class="search-container">
                <input type="text" id="itemSearch" class="search-input" placeholder="Start typing to search items..." onkeyup="filterItems()">
            </div>
            
            <div class="table-frame">
                <div class="table-viewport">
                    <table id="itemsTable" tabindex="-1">
                    <thead>
                        <tr>
                            <th>Item No.</th>
                            <th>Description</th>
                            <th>Unit</th>
                            <th>Quantity on Hand</th>
                            <th>Unit Cost</th>
                            <th>Total Cost</th>
                            <th>Issued Qty</th>
                            <th>Estimated Useful Life</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Read from semi_expendable_property, filter by category if selected
                        if ($selected_category !== 'All' && columnExists($conn, 'semi_expendable_property', 'category')) {
                            $stmt = $conn->prepare("SELECT * FROM semi_expendable_property WHERE category = ? ORDER BY date DESC, id DESC");
                            $stmt->bind_param("s", $selected_category);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $stmt->close();
                        } else {
                            $result = $conn->query("SELECT * FROM semi_expendable_property ORDER BY date DESC, id DESC");
                        }
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $stock_number = $row['semi_expendable_property_no'];
                                $existing_item = $ics_items[$stock_number] ?? null;
                                $qtyOnHand = (int)$row['quantity_balance'];
                                $unitCost = (float)($row['amount'] ?? 0);
                                $remarks = $row['remarks'] ?? '';
                                $existingDesc = $existing_item && isset($existing_item['description']) ? (string)$existing_item['description'] : '';
                                // Per request: do not use remarks in display; prefer existing description or item_description
                                $displayDesc = $existingDesc !== '' ? $existingDesc : $row['item_description'];

                                $catVal = isset($row['category']) ? $row['category'] : '';
                                $unitDisp = isset($row['unit']) && $row['unit'] !== '' ? $row['unit'] : '-';
                                echo '<tr class="item-row" data-stock="' . htmlspecialchars(strtolower($stock_number)) . '" data-item_name="' . htmlspecialchars(strtolower($row['item_description'])) . '" data-description="' . htmlspecialchars(strtolower($displayDesc)) . '" data-unit="' . htmlspecialchars(strtolower($unitDisp)) . '" data-category="' . htmlspecialchars(strtolower($catVal)) . '" data-unit-cost="' . htmlspecialchars(number_format($unitCost,2,'.','')) . '">';
                                // First column is Item No. (stock number) and hidden input for submission
                                echo '<td>'  . htmlspecialchars($stock_number) . '<input type="hidden" name="stock_number[]" value="' . htmlspecialchars($stock_number) . '"></td>';
                                echo '<td>' . htmlspecialchars($displayDesc) . '</td>';
                                echo '<td>' . htmlspecialchars($unitDisp) . '</td>';
                                echo '<td>' . htmlspecialchars($qtyOnHand) . '</td>';
                                echo '<td>₱' . number_format($unitCost, 2) . '</td>';
                                $initialQty = $existing_item ? (float)$existing_item['quantity'] : 0;
                                $initialTotal = $unitCost * $initialQty;
                                echo '<td class="cell-total-cost">₱' . number_format($initialTotal, 2) . '</td>';
                                echo '<td><input type="number" name="issued_quantity[]" value="' . ($existing_item ? htmlspecialchars($existing_item['quantity']) : '') . '" min="0" max="' . htmlspecialchars($qtyOnHand) . '" step="1" class="issued-qty"></td>';
                                echo '<td><input type="text" name="estimated_useful_life[]" value="' . ($existing_item ? htmlspecialchars($existing_item['estimated_useful_life']) : htmlspecialchars($row['estimated_useful_life'])) . '" placeholder="e.g., 5 years"></td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr id="no-items-row"><td colspan="8">No semi-expendable items found.</td></tr>';
                        }
                        ?>
                    </tbody>
                    </table>
                </div>
            </div>
            </div>

            <div class="section-card">
                <h3>Signatories</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Received By:</label>
                        <input type="text" name="received_by" value="<?php echo htmlspecialchars($ics_data['received_by'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Received By Position:</label>
                        <input type="text" name="received_by_position" value="<?php echo htmlspecialchars($ics_data['received_by_position'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Received From:</label>
                        <input type="text" name="received_from" value="<?php echo htmlspecialchars($ics_data['received_from'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Received From Position:</label>
                        <input type="text" name="received_from_position" value="<?php echo htmlspecialchars($ics_data['received_from_position'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <button type="submit"><?php echo $is_editing ? 'Update ICS' : 'Submit ICS'; ?></button>
            <a href="<?php echo $is_editing ? 'ics.php?ics_id=' . $ics_id : 'ics.php'; ?>" style="margin-left: 10px;">
                <button type="button">Cancel</button>
            </a>
        </form>
    </div>

    <script>
        // Show a temporary notice when we clamp an input value
        function showClampNotice(message) {
            const notice = document.getElementById('clamp-notice');
            if (!notice) return;
            notice.textContent = message;
            notice.style.display = 'block';
            clearTimeout(window.__clampNoticeTimer);
            window.__clampNoticeTimer = setTimeout(() => {
                notice.style.display = 'none';
            }, 2500);
        }

        // Attach guards to Issued Qty inputs to enforce min/max on the client
        function attachIssuedQtyGuards() {
            const rows = document.querySelectorAll('.item-row');
            rows.forEach(row => {
                const qtyInput = row.querySelector('input[name="issued_quantity[]"]');
                if (!qtyInput) return;
                const stockNo = (row.getAttribute('data-stock') || (row.querySelector('td:first-child')?.innerText || '(unknown)')).trim();

                const handleClamp = () => {
                    const max = parseFloat(qtyInput.max || '0');
                    let val = parseFloat(qtyInput.value);
                    if (isNaN(val)) {
                        qtyInput.value = '';
                        return;
                    }
                    if (val < 0) {
                        qtyInput.value = '0';
                        showClampNotice(`Issued Qty for ${stockNo} cannot be negative. Set to 0.`);
                        return;
                    }
                    if (max > 0 && val > max) {
                        qtyInput.value = String(max);
                        showClampNotice(`Requested Issued Qty for ${stockNo} exceeded available stock. Set to ${max}.`);
                    }
                };

                qtyInput.addEventListener('input', handleClamp);
                qtyInput.addEventListener('blur', handleClamp);
            });

            // Re-validate all before submit as a safety net
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', () => {
                    const inputs = form.querySelectorAll('input[name="issued_quantity[]"]');
                    inputs.forEach(inp => {
                        const max = parseFloat(inp.max || '0');
                        let val = parseFloat(inp.value);
                        if (isNaN(val) || val < 0) { inp.value = val < 0 ? '0' : ''; return; }
                        if (max > 0 && val > max) { inp.value = String(max); }
                    });
                });
            }
        }

        document.addEventListener('DOMContentLoaded', attachIssuedQtyGuards);

        function filterItems() {
            const searchValue = document.getElementById('itemSearch').value.toLowerCase().trim();
            const itemRows = document.querySelectorAll('.item-row');
            let visibleRows = 0;

            itemRows.forEach(function(row) {
                const stockNumber = row.getAttribute('data-stock') || '';
                const item_name = row.getAttribute('data-item_name') || '';
                const description = row.getAttribute('data-description') || '';
                const unit = row.getAttribute('data-unit') || '';

                const match = searchValue === '' ||
                              stockNumber.includes(searchValue) ||
                              item_name.includes(searchValue) ||
                              description.includes(searchValue) ||
                              unit.includes(searchValue);
                row.style.display = match ? '' : 'none';
                if (match) visibleRows++;
            });

                    const noItemsRow = document.getElementById('no-items-row');
            if (noItemsRow) {
                noItemsRow.style.display = visibleRows === 0 ? 'table-row' : 'none';
                if (visibleRows === 0) {
                    noItemsRow.innerHTML = '<td colspan="11">No items match your search criteria.</td>';
                }
            }
        }

        // Handle category change: refresh page without jumping to top
        document.addEventListener('DOMContentLoaded', function(){
            try { if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; } } catch(e){}

            // Restore previous scroll position if set
            const saved = sessionStorage.getItem('ics_scroll');
            if (saved) {
                const y = parseInt(saved, 10);
                if (!isNaN(y)) {
                    setTimeout(() => window.scrollTo(0, y), 0);
                }
                sessionStorage.removeItem('ics_scroll');
            }

            var sel = document.getElementById('filter_category');
            if (!sel) return;
            sel.addEventListener('change', function(){
                // Save current scroll before navigating
                try { sessionStorage.setItem('ics_scroll', String(window.scrollY || window.pageYOffset || 0)); } catch(e){}

                var url = new URL(window.location.href);
                url.searchParams.set('filter_category', this.value);
                // Preserve edit context
                <?php if ($is_editing): ?>
                url.searchParams.set('ics_id', '<?php echo (int)$ics_id; ?>');
                <?php else: ?>
                url.searchParams.delete('ics_id');
                <?php endif; ?>
                // Jump to the items table after reload
                url.hash = 'itemsTable';
                window.location.href = url.toString();
            });
            // Initialize total cost cells and attach live updates for issued qty
            document.querySelectorAll('.item-row').forEach(function(row){
                const unit = parseFloat(row.getAttribute('data-unit-cost') || '0');
                const qtyInput = row.querySelector('input.issued-qty');
                const totalCell = row.querySelector('.cell-total-cost');
                const recalc = function(){
                    const q = parseFloat(qtyInput && qtyInput.value ? qtyInput.value : '0') || 0;
                    const total = unit * q;
                    if (totalCell) totalCell.textContent = '₱' + total.toFixed(2);
                };
                recalc();
                if (qtyInput) qtyInput.addEventListener('input', recalc);
                if (qtyInput) qtyInput.addEventListener('blur', recalc);
            });
        });
    </script>
</body>
</html>