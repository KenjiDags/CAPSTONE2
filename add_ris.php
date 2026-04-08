<?php
require 'auth.php';
require 'config.php';
require 'functions.php';
ob_start();
include 'sidebar.php';
?>

<?php
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Check if we're editing or creating new
    $is_editing = isset($_POST['is_editing']) && $_POST['is_editing'] == '1';
    $ris_id = $is_editing ? (int)$_POST['ris_id'] : null;

    // RIS Header fields
    $ris_no = $_POST['ris_no'];
    $entity_name = $_POST['entity_name'];
    $fund_cluster = $_POST['fund_cluster'];
    $division = $_POST['division'];
    $office = $_POST['office'];
    $responsibility_center_code = $_POST['responsibility_center_code'];
    $date_requested = $_POST['date_requested'];
    $purpose = $_POST['purpose'];
    $requested_by = $_POST['requested_by'];
    $approved_by = $_POST['approved_by'];
    $issued_by = $_POST['issued_by'];
    $received_by = $_POST['received_by'];
    $requested_by_designation = $_POST['requested_by_designation'] ?? '';
    $approved_by_designation = $_POST['approved_by_designation'] ?? '';
    $issued_by_designation = $_POST['issued_by_designation'] ?? '';
    $received_by_designation = $_POST['received_by_designation'] ?? '';
    $requested_by_date = $_POST['requested_by_date'] ?? '';
    $approved_by_date = $_POST['approved_by_date'] ?? '';
    $issued_by_date = $_POST['issued_by_date'] ?? '';
    $received_by_date = $_POST['received_by_date'] ?? '';

    if ($is_editing) {
        // Update existing RIS
        $stmt = $conn->prepare("UPDATE ris SET entity_name = ?, fund_cluster = ?, division = ?, office = ?, 
                               responsibility_center_code = ?, date_requested = ?, purpose = ?, 
                               requested_by = ?, approved_by = ?, issued_by = ?, received_by = ?,
                               requested_by_designation = ?, approved_by_designation = ?, issued_by_designation = ?, received_by_designation = ?,
                               requested_by_date = ?, approved_by_date = ?, issued_by_date = ?, received_by_date = ?
                               WHERE ris_id = ?");
        $stmt->bind_param("sssssssssssssssssssi", $entity_name, $fund_cluster, $division, $office, 
                         $responsibility_center_code, $date_requested, $purpose, 
                         $requested_by, $approved_by, $issued_by, $received_by,
                         $requested_by_designation, $approved_by_designation, $issued_by_designation, $received_by_designation,
                         $requested_by_date, $approved_by_date, $issued_by_date, $received_by_date,
                         $ris_id);
        $stmt->execute();
        $stmt->close();
        
        // Get old items to restore inventory quantities
        $old_items = [];
        $stmt = $conn->prepare("SELECT stock_number, issued_quantity FROM ris_items WHERE ris_id = ?");
        $stmt->bind_param("i", $ris_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $old_items[$row['stock_number']] = $row['issued_quantity'];
        }
        $stmt->close();
        
        // Delete old items
        $stmt = $conn->prepare("DELETE FROM ris_items WHERE ris_id = ?");
        $stmt->bind_param("i", $ris_id);
        $stmt->execute();
        $stmt->close();
        
        // Restore inventory quantities from old items
        foreach ($old_items as $stock_no => $old_qty) {
            $stmt = $conn->prepare("UPDATE items SET quantity_on_hand = quantity_on_hand + ? WHERE stock_number = ?");
            $stmt->bind_param("is", $old_qty, $stock_no);
            $stmt->execute();
            $stmt->close();
        }
        
    } else {
        // Insert new RIS
        $stmt = $conn->prepare("INSERT INTO ris (ris_no, entity_name, fund_cluster, division, office, 
                               responsibility_center_code, date_requested, purpose, requested_by, 
                               approved_by, issued_by, received_by,
                               requested_by_designation, approved_by_designation, issued_by_designation, received_by_designation,
                               requested_by_date, approved_by_date, issued_by_date, received_by_date)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssssssssssss", $ris_no, $entity_name, $fund_cluster, $division, $office, 
                         $responsibility_center_code, $date_requested, $purpose, 
                         $requested_by, $approved_by, $issued_by, $received_by,
                         $requested_by_designation, $approved_by_designation, $issued_by_designation, $received_by_designation,
                         $requested_by_date, $approved_by_date, $issued_by_date, $received_by_date);
        $stmt->execute();
        $ris_id = $stmt->insert_id;
        $stmt->close();
    }

    // RIS Items arrays from form
    $stock_numbers = $_POST['stock_number'];
    $stock_availables = $_POST['stock_available'];
    $issued_quantities = $_POST['issued_quantity'];
    $remarks = $_POST['remarks'];

    // Insert new items and update inventory
    for ($i = 0; $i < count($stock_numbers); $i++) {
        $stock_no = $stock_numbers[$i];
        $stock_available = $stock_availables[$i];
        $issued_qty = (int)$issued_quantities[$i];
        $remark = $remarks[$i];

        // Only insert if there's an issued quantity or remarks
        if ($issued_qty > 0 || !empty($remark)) {
            // GET THE CURRENT AVERAGE UNIT COST BEFORE ANY CHANGES
            $stmt = $conn->prepare("SELECT average_unit_cost FROM items WHERE stock_number = ?");
            $stmt->bind_param("s", $stock_no);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_unit_cost = $result->fetch_assoc()['average_unit_cost'];
            $stmt->close();

            // INSERT INTO RIS_ITEMS WITH UNIT COST AT TIME OF ISSUE
            $stmt = $conn->prepare("INSERT INTO ris_items (ris_id, stock_number, stock_available, issued_quantity, remarks, unit_cost_at_issue)
                                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ississ", $ris_id, $stock_no, $stock_available, $issued_qty, $remark, $current_unit_cost);
            $stmt->execute();
            $stmt->close();

            // Update inventory: deduct issued quantity
            if ($issued_qty > 0) {
                // Get item_id first
                $stmt = $conn->prepare("SELECT item_id FROM items WHERE stock_number = ?");
                $stmt->bind_param("s", $stock_no);
                $stmt->execute();
                $result = $stmt->get_result();
                $item = $result->fetch_assoc();
                $item_id = $item['item_id'];
                $stmt->close();

                // Deduct from main quantity_on_hand
                $stmt = $conn->prepare("UPDATE items SET quantity_on_hand = quantity_on_hand - ? WHERE stock_number = ?");
                $stmt->bind_param("is", $issued_qty, $stock_no);
                $stmt->execute();
                $stmt->close();

                // Insert a NEGATIVE entry with ZERO cost (doesn't affect arithmetic mean)
                $negative_qty = -$issued_qty;
                $zero_cost = 0.00;
                $stmt = $conn->prepare("INSERT INTO inventory_entries (item_id, quantity, unit_cost, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param("iid", $item_id, $negative_qty, $zero_cost);
                $stmt->execute();
                $stmt->close();
                
                // Log the history
                logItemHistory($conn, $item_id, -$issued_qty, 'issued', $ris_id);

                // Recalculate average cost using arithmetic mean
                updateAverageCost($conn, $item_id);
            }
        }
    }

    // Redirect after successful submission
    if ($is_editing) {
        header("Location: view_ris.php?ris_id=" . $ris_id);
    } else {
        header("Location: ris.php");
    }
    exit();
}

// Check if we're editing an existing RIS
$is_editing = isset($_GET['ris_id']) && !empty($_GET['ris_id']);
$ris_id = $is_editing ? (int)$_GET['ris_id'] : null;

// Fetch current user's full name and position for default values
$current_user_full_name = '';
$current_user_position = '';
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

// Fetch officer names and positions for autocomplete/auto-fill
$officer_names = [];
$officer_positions = [];
$officer_data = [];
$officers_result = $conn->query("SELECT officer_name, officer_position FROM officers ORDER BY officer_name ASC");
if ($officers_result && $officers_result->num_rows > 0) {
    while ($row = $officers_result->fetch_assoc()) {
        $name = trim($row['officer_name'] ?? '');
        $position = trim($row['officer_position'] ?? '');
        if ($name !== '') {
            $officer_names[] = $name;
            $officer_data[$name] = $position;
        }
        if ($position !== '') {
            $officer_positions[] = $position;
        }
    }
}
$officer_positions = array_values(array_unique($officer_positions));
$officer_names_json = json_encode($officer_names);
$officer_data_json = json_encode($officer_data);

// Initialize variables
$ris_data = [];
$ris_items = [];

if ($is_editing) {
    // Fetch existing RIS data
    $stmt = $conn->prepare("SELECT * FROM ris WHERE ris_id = ?");
    $stmt->bind_param("i", $ris_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $ris_data = $result->fetch_assoc();
        
        // Fetch RIS items
        $stmt = $conn->prepare("SELECT * FROM ris_items WHERE ris_id = ?");
        $stmt->bind_param("i", $ris_id);
        $stmt->execute();
        $items_result = $stmt->get_result();
        
        while ($item = $items_result->fetch_assoc()) {
            $ris_items[$item['stock_number']] = $item;
        }
    } else {
        // RIS not found, redirect back
        header("Location: ris.php");
        exit();
    }
}

// Function to generate the next RIS number (only for new RIS)
function generateRISNumber($conn) {
    $current_year = date('Y');
    $current_month = date('m');
    $prefix = $current_year . '/' . $current_month . '/';
    
    // Get the highest RIS number for current month/year
    $query = "SELECT ris_no FROM ris WHERE ris_no LIKE ? ORDER BY ris_no DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $search_pattern = $prefix . '%';
    $stmt->bind_param('s', $search_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_ris = $row['ris_no'];
        
        // Extract the incremental part (last 4 digits)
        $last_increment = (int)substr($last_ris, -4);
        $next_increment = $last_increment + 1;
    } else {
        // First RIS for this month/year
        $next_increment = 1;
    }
    
    // Format the increment with leading zeros (4 digits)
    $formatted_increment = str_pad($next_increment, 4, '0', STR_PAD_LEFT);
    
    return $prefix . $formatted_increment;
}

// Generate the RIS number only for new RIS
$auto_ris_number = $is_editing ? $ris_data['ris_no'] : generateRISNumber($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_editing ? 'Edit RIS Form' : 'Add RIS Form'; ?></title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-specific: smaller max-width for RIS forms */
        .form-container {
            max-width: 1200px;
        }
        
        /* Form grid responsive */
        @media (max-width: 800px) { 
            .form-grid { grid-template-columns: 1fr; } 
        }
        
        /* Scrollable items table */
        .table-frame { 
            border: 2px solid #e5e7eb; 
            border-top: 0; 
            border-radius: 0 0 8px 8px; 
            overflow: hidden; 
        }
        .table-viewport { 
            max-height: 420px; 
            overflow-y: auto; 
            overflow-x: auto; 
            scrollbar-gutter: stable; 
            background: var(--white); 
        }
        #itemsTable { 
            overflow: visible !important; 
            width: 100%; 
            border-collapse: collapse; 
            background: transparent !important; 
            border-radius: 0 !important; 
            margin-top: 0 !important; 
        }
        #itemsTable thead th { 
            position: sticky; 
            top: 0; 
            z-index: 3; 
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); 
            color: #fff; 
        }
        .table-frame, .table-viewport, #itemsTable { 
            border-top-left-radius: 0 !important; 
            border-top-right-radius: 0 !important; 
        }
        #itemsTable th, #itemsTable td { 
            padding: 10px 12px; 
            vertical-align: middle; 
        }
        #itemsTable thead th { 
            height: 44px; 
        }
        
        /* Search container */
        .search-container { 
            margin: 8px 0 12px !important; 
        }
        .search-container input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .search-container input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Required field indicator */
        .required {
            color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="form-container">
            <header class="page-header">
                <h1>
                    <i class="fas fa-file-invoice"></i>
                    <?php echo $is_editing ? 'Edit RIS Form' : 'Add RIS Form'; ?>
                </h1>
                <p><?php echo $is_editing ? 'Update requisition and issue slip details' : 'Create a new requisition and issue slip'; ?></p>
            </header>

            <form method="post" action="">
                <?php if ($is_editing): ?>
                    <input type="hidden" name="ris_id" value="<?php echo $ris_id; ?>">
                    <input type="hidden" name="is_editing" value="1">
                <?php endif; ?>
                
                <div class="section-card">
                    <h3><i class="fas fa-info-circle"></i> RIS Details</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Entity Name: <span class="required">*</span></label>
                            <input type="text" name="entity_name" value="<?php echo htmlspecialchars($ris_data['entity_name'] ?? 'TESDA Regional Office'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Fund Cluster:</label>
                            <input type="text" name="fund_cluster" value="<?php echo htmlspecialchars($ris_data['fund_cluster'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Division: <span class="required">*</span></label>
                            <select name="division" required>
                                <option value="">-- Select Division --</option>
                                <option value="ORD" <?php echo (isset($ris_data['division']) && $ris_data['division'] == 'ORD') ? 'selected' : ''; ?>>ORD</option>
                                <option value="ROD" <?php echo (isset($ris_data['division']) && $ris_data['division'] == 'ROD') ? 'selected' : ''; ?>>ROD</option>
                                <option value="FASD" <?php echo (isset($ris_data['division']) && $ris_data['division'] == 'FASD') ? 'selected' : ''; ?>>FASD</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Office:</label>
                            <input type="text" name="office" value="<?php echo htmlspecialchars($ris_data['office'] ?? 'TESDA CAR'); ?>">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Responsibility Center Code:</label>
                            <input type="text" name="responsibility_center_code" value="<?php echo htmlspecialchars($ris_data['responsibility_center_code'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>RIS No.:</label>
                            <input type="text" name="ris_no" value="<?php echo htmlspecialchars($auto_ris_number); ?>" readonly style="background-color: #f9fafb;">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Date: <span class="required">*</span></label>
                            <input type="date" name="date_requested" value="<?php echo $ris_data['date_requested'] ?? ''; ?>" required>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h3><i class="fas fa-box"></i> RIS Items</h3>
                    
                    <!-- Search Container -->
                    <div class="search-container">
                        <input type="text" id="itemSearch" class="search-input" placeholder="🔍 Start typing to search items..." onkeyup="filterItems()">
                    </div>
                    
                    <div class="table-frame">
                        <div class="table-viewport">
                            <table id="itemsTable">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-barcode"></i> Stock No.</th>
                                        <th><i class="fas fa-tag"></i> Item</th>
                                        <th><i class="fas fa-align-left"></i> Description</th>
                                        <th><i class="fas fa-ruler"></i> Unit</th>
                                        <th><i class="fas fa-cubes"></i> Quantity on Hand</th>
                                        <th><i class="fas fa-check-circle"></i> Stock Available</th>
                                        <th><i class="fas fa-sign-out-alt"></i> Issued Qty</th>
                                        <th><i class="fas fa-comment"></i> Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $result = $conn->query("SELECT * FROM items");
                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $stock_number = $row['stock_number'];
                                            $existing_item = $ris_items[$stock_number] ?? null;
                                            
                                            echo '<tr class="item-row" data-stock="' . htmlspecialchars(strtolower($stock_number)) . '" data-item_name="' . htmlspecialchars(strtolower($row['item_name'])) . '" data-description="' . htmlspecialchars(strtolower($row['description'])) . '" data-unit="' . htmlspecialchars(strtolower($row['unit'])) . '">';
                                            echo '<td><input type="hidden" name="stock_number[]" value="' . htmlspecialchars($stock_number) . '">' . htmlspecialchars($stock_number) . '</td>';
                                            echo '<td>' . htmlspecialchars($row['item_name']) . '</td>';
                                            echo '<td>' . htmlspecialchars($row['description']) . '</td>';
                                            echo '<td>' . htmlspecialchars($row['unit']) . '</td>';
                                            echo '<td>' . htmlspecialchars($row['quantity_on_hand']) . '</td>';
                                            echo '<td>
                                                    <select name="stock_available[]">
                                                        <option value="Yes"' . (($existing_item && $existing_item['stock_available'] == 'Yes') ? ' selected' : '') . '>Yes</option>
                                                        <option value="No"' . (($existing_item && $existing_item['stock_available'] == 'No') ? ' selected' : '') . '>No</option>
                                                    </select>
                                                </td>';
                                            echo '<td><input type="number" name="issued_quantity[]" value="' . ($existing_item ? htmlspecialchars($existing_item['issued_quantity']) : '') . '" min="0" max="' . htmlspecialchars($row['quantity_on_hand']) . '"></td>';
                                            echo '<td><input type="text" name="remarks[]" value="' . ($existing_item ? htmlspecialchars($existing_item['remarks']) : '') . '"></td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr id="no-items-row"><td colspan="8">No inventory items found.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h3><i class="fas fa-clipboard-list"></i> Purpose</h3>
                    <div class="form-group">
                        <label>Purpose:</label>
                        <textarea name="purpose" rows="3" style="width: 100%; resize: vertical;"><?php echo htmlspecialchars($ris_data['purpose'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="section-card">
                    <h3><i class="fas fa-users"></i> Signatories</h3>
                    <div class="form-grid">
                        <div class="form-group" style="position: relative;">
                            <label><i class="fas fa-user"></i> Requested by:</label>
                            <input type="text" id="requested_by" name="requested_by" value="<?php echo htmlspecialchars($ris_data['requested_by'] ?? ''); ?>" autocomplete="off">
                            <div id="requested_by_dropdown" class="autocomplete-dropdown"></div>
                            <div class="form-grid" style="margin-top: 10px;">
                                <div class="form-group">
                                    <label>Designation:</label>
                                    <input type="text" id="requested_by_designation" name="requested_by_designation" value="<?php echo htmlspecialchars($ris_data['requested_by_designation'] ?? ''); ?>" placeholder="Enter designation" list="officer_position_list">
                                </div>
                                <div class="form-group">
                                    <label>Date:</label>
                                    <input type="date" name="requested_by_date" value="<?php echo htmlspecialchars($ris_data['requested_by_date'] ?? date('Y-m-d')); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-user-check"></i> Approved by:</label>
                            <input type="text" id="approved_by" name="approved_by" value="<?php echo htmlspecialchars($ris_data['approved_by'] ?? $current_user_full_name); ?>">
                            <div class="form-grid" style="margin-top: 10px;">
                                <div class="form-group">
                                    <label>Designation:</label>
                                    <input type="text" id="approved_by_designation" name="approved_by_designation" value="<?php echo htmlspecialchars($ris_data['approved_by_designation'] ?? $current_user_position); ?>" placeholder="Enter designation" list="officer_position_list">
                                </div>
                                <div class="form-group">
                                    <label>Date:</label>
                                    <input type="date" name="approved_by_date" value="<?php echo htmlspecialchars($ris_data['approved_by_date'] ?? date('Y-m-d')); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-user-cog"></i> Issued by:</label>
                            <input type="text" id="issued_by" name="issued_by" value="<?php echo htmlspecialchars($ris_data['issued_by'] ?? $current_user_full_name); ?>">
                            <div class="form-grid" style="margin-top: 10px;">
                                <div class="form-group">
                                    <label>Designation:</label>
                                    <input type="text" id="issued_by_designation" name="issued_by_designation" value="<?php echo htmlspecialchars($ris_data['issued_by_designation'] ?? $current_user_position); ?>" placeholder="Enter designation" list="officer_position_list">
                                </div>
                                <div class="form-group">
                                    <label>Date:</label>
                                    <input type="date" name="issued_by_date" value="<?php echo htmlspecialchars($ris_data['issued_by_date'] ?? date('Y-m-d')); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="position: relative;">
                            <label><i class="fas fa-user-circle"></i> Received by:</label>
                            <input type="text" id="received_by" name="received_by" value="<?php echo htmlspecialchars($ris_data['received_by'] ?? ''); ?>" autocomplete="off">
                            <div id="received_by_dropdown" class="autocomplete-dropdown"></div>
                            <div class="form-grid" style="margin-top: 10px;">
                                <div class="form-group">
                                    <label>Designation:</label>
                                    <input type="text" id="received_by_designation" name="received_by_designation" value="<?php echo htmlspecialchars($ris_data['received_by_designation'] ?? ''); ?>" placeholder="Enter designation" list="officer_position_list">
                                </div>
                                <div class="form-group">
                                    <label>Date:</label>
                                    <input type="date" name="received_by_date" value="<?php echo htmlspecialchars($ris_data['received_by_date'] ?? date('Y-m-d')); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <datalist id="officer_position_list">
                    <?php foreach ($officer_positions as $position): ?>
                        <option value="<?= htmlspecialchars($position) ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                    <button type="submit" class="pill-btn pill-add">
                        <i class="fas fa-save"></i>
                        <?php echo $is_editing ? 'Update RIS' : 'Submit RIS'; ?>
                    </button>
                <a href="ris.php">
                    <button type="button" class="pill-btn pill-view"> <i class="fas fa-ban"></i> Cancel </button>
                </a>
            </form>
        </div>
    </div>

    <script>
        function filterItems() {
            // Get the search input value and convert to lowercase
            const searchValue = document.getElementById('itemSearch').value.toLowerCase();
            
            // Get all item rows
            const itemRows = document.querySelectorAll('.item-row');
            
            // Counter for visible rows
            let visibleRows = 0;
            
            // Loop through each row
            itemRows.forEach(function(row) {
                // Get the data attributes
                const stockNumber = row.getAttribute('data-stock');
                const item_name = row.getAttribute('data-item_name');
                const description = row.getAttribute('data-description');
                const unit = row.getAttribute('data-unit');
                
                // Check if search value matches any of the fields
                if (searchValue.trim() === '' || 
                    (stockNumber && stockNumber.includes(searchValue)) || 
                    (item_name && item_name.includes(searchValue)) ||
                    (description && description.includes(searchValue)) || 
                    (unit && unit.includes(searchValue))) {
                    // Show the row
                    row.style.display = 'table-row';
                    visibleRows++;
                } else {
                    // Hide the row
                    row.style.display = 'none';
                }
            });
            
            // Handle the "no items found" message
            const noItemsRow = document.getElementById('no-items-row');
            if (noItemsRow) {
                if (visibleRows === 0) {
                    // Show "no results found" message
                    noItemsRow.style.display = 'table-row';
                    noItemsRow.innerHTML = '<td colspan="8">No items match your search criteria.</td>';
                } else {
                    // Hide the message
                    noItemsRow.style.display = 'none';
                }
            }
        }
    </script>


    <script>
        // Officer names from database
        const officerNames = <?php echo $officer_names_json; ?>;
        const officerPositions = <?php echo $officer_data_json; ?>;

        function fillDesignation(nameInput, designationInput) {
            if (!nameInput || !designationInput) return;
            const name = nameInput.value.trim();
            if (name && officerPositions[name]) {
                designationInput.value = officerPositions[name];
            }
        }

        // Autocomplete functionality
        function setupAutocomplete(inputId, dropdownId, designationInputId) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);
            const designationInput = designationInputId ? document.getElementById(designationInputId) : null;
            
            if (!input || !dropdown) return;

            // Show dropdown on focus
            input.addEventListener('focus', function() {
                if (this.value.trim() === '') {
                    showAllSuggestions(dropdown, input, designationInput);
                } else {
                    filterSuggestions(this.value, dropdown, input, designationInput);
                }
            });

            // Prevent click on input from closing dropdown
            input.addEventListener('click', function(e) {
                e.stopPropagation();
                if (dropdown.style.display !== 'block') {
                    if (this.value.trim() === '') {
                        showAllSuggestions(dropdown, input, designationInput);
                    } else {
                        filterSuggestions(this.value, dropdown, input, designationInput);
                    }
                }
            });

            // Filter on input
            input.addEventListener('input', function() {
                const value = this.value;
                if (value.trim() === '') {
                    showAllSuggestions(dropdown, input, designationInput);
                } else {
                    filterSuggestions(value, dropdown, input, designationInput);
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
                            fillDesignation(input, designationInput);
                            dropdown.style.display = 'none';
                        }
                        break;
                    
                    case 'Tab':
                        const itemToSelect = selectedItem || items[0];
                        if (itemToSelect) {
                            const text = itemToSelect.textContent || itemToSelect.innerText;
                            input.value = text;
                            fillDesignation(input, designationInput);
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

            input.addEventListener('blur', function() {
                fillDesignation(input, designationInput);
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

        function showAllSuggestions(dropdown, input, designationInput) {
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
                    fillDesignation(input, designationInput);
                    dropdown.style.display = 'none';
                });
                dropdown.appendChild(item);
            });
            
            dropdown.style.display = 'block';
        }

        function filterSuggestions(value, dropdown, input, designationInput) {
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
                    fillDesignation(input, designationInput);
                    dropdown.style.display = 'none';
                });
                dropdown.appendChild(item);
            });
            
            dropdown.style.display = 'block';
        }

        // Initialize autocomplete for both fields
        document.addEventListener('DOMContentLoaded', function() {
            setupAutocomplete('requested_by', 'requested_by_dropdown', 'requested_by_designation');
            setupAutocomplete('received_by', 'received_by_dropdown', 'received_by_designation');

            const approvedBy = document.getElementById('approved_by');
            const approvedByDesignation = document.getElementById('approved_by_designation');
            const issuedBy = document.getElementById('issued_by');
            const issuedByDesignation = document.getElementById('issued_by_designation');

            if (approvedBy) {
                approvedBy.addEventListener('blur', function() {
                    fillDesignation(approvedBy, approvedByDesignation);
                });
            }

            if (issuedBy) {
                issuedBy.addEventListener('blur', function() {
                    fillDesignation(issuedBy, issuedByDesignation);
                });
            }
        });
    </script>
</body>
</html>