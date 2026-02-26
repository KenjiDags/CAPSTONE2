<?php
// Enable safe redirects even if sidebar outputs content
ob_start();
require 'auth.php';
require_once 'config.php';
require_once 'functions.php';
require_once 'sidebar.php'; 
// Ensure required columns exist (idempotent)
ensure_semi_expendable_amount_columns($conn);
// Ensure 'unit' column exists on semi_expendable_property (idempotent)
try {
    if (function_exists('columnExists') && !columnExists($conn, 'semi_expendable_property', 'unit')) {
        @$conn->query("ALTER TABLE semi_expendable_property ADD COLUMN unit VARCHAR(64) NULL AFTER item_description");
    }
} catch (Throwable $e) { /* no-op */ }

// Local helper: generate a simple ICS number like YY-NN (e.g., 25-01 for year 2025)
if (!function_exists('generateICSNumberSimple')) {
    function generateICSNumberSimple($conn) {
        $year_short = date('y'); // Last 2 digits of year (e.g., '25' for 2025)
        $current_year = (int)date('Y');
        
        // Get the highest number used THIS YEAR ONLY - always starts at 01 for new year
        $stmt = $conn->prepare("SELECT ics_no FROM ics WHERE YEAR(date_issued) = ? AND ics_no LIKE ? ORDER BY ics_id DESC LIMIT 1");
        $next_increment = 1;
        if ($stmt) {
            $pattern = $year_short . '-%';
            $stmt->bind_param('is', $current_year, $pattern);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $last_ics_no = $row['ics_no'];
                    // Try to extract the number part (format: YY-NN)
                    if (preg_match('/(\d+)-(\d+)/', $last_ics_no, $matches)) {
                        $next_increment = ((int)$matches[2]) + 1;
                    }
                }
                // If no results found for current year pattern, next_increment stays at 1
            }
            $stmt->close();
        }
        
        $formatted = str_pad((string)$next_increment, 2, '0', STR_PAD_LEFT);
        return $year_short . '-' . $formatted;
    }
}

$error = '';
$success = '';

// Valid categories
$valid_categories = ['Other PPE', 'Office Equipment', 'ICT Equipment', 'Communication Equipment', 'Furniture and Fixtures'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Assign POST values to variables
    $date = $_POST['date'];
    $ics_rrsp_no_input = isset($_POST['ics_rrsp_no']) ? trim($_POST['ics_rrsp_no']) : '';
    $ics_rrsp_no = ''; // Will be set based on logic below
    $semi_expendable_property_no = $_POST['semi_expendable_property_no'];
    $item_description = $_POST['item_description'];
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $estimated_useful_life = intval($_POST['estimated_useful_life']);
    // Base Quantity (receipt)
    $quantity_issued = intval($_POST['quantity_issued']);
    $office_officer_issued = $_POST['office_officer_issued'];
    $quantity_returned = intval($_POST['quantity_returned'] ?? 0);
    $office_officer_returned = $_POST['office_officer_returned'];
    $quantity_reissued = intval($_POST['quantity_reissued'] ?? 0);
    $office_officer_reissued = $_POST['office_officer_reissued'];
    $quantity_disposed = intval($_POST['quantity_disposed'] ?? 0);
    // Quantity Issued (issued-out movement that reduces balance)
    $quantity_issued_out = intval($_POST['quantity_issued_out'] ?? 0);
    // Compute balance: base quantity - (issued + reissued + disposed)
    // Note: quantity_issued_out is stored in quantity_issued column
    $quantity_balance = max(0, $quantity_issued - ($quantity_issued_out + $quantity_reissued + $quantity_disposed));
    // Unit amount entered by user; store total = quantity × unit amount
    $unit_amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
    // amount_total = unit_amount × base quantity
    $amount_total = round($unit_amount * $quantity_issued, 2);
    $category = $_POST['category'];
    $fund_cluster = $_POST['fund_cluster'] ?? '101';
    $remarks = $_POST['remarks'];

    // Check for duplicate property number
    $check_stmt = $conn->prepare("SELECT id FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
    $check_stmt->bind_param("s", $semi_expendable_property_no);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result && $check_result->num_rows > 0) {
        $error = "A record with this Semi-Expendable Property No. already exists.";
        $check_stmt->close();
    } else {
        $check_stmt->close();
        $stmt = $conn->prepare("
            INSERT INTO semi_expendable_property 
            (date, ics_rrsp_no, semi_expendable_property_no, item_description, unit, estimated_useful_life, 
             quantity, quantity_issued, office_officer_issued, quantity_returned, office_officer_returned, 
             quantity_reissued, office_officer_reissued, quantity_disposed, quantity_balance, 
             amount, amount_total, category, fund_cluster, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            $error = "Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param(
                "sssssiiisisisiiddsss",
                $date,
                $ics_rrsp_no,
                $semi_expendable_property_no,
                $item_description,
                $unit,
                $estimated_useful_life,
                $quantity_issued,      // quantity (base)
                $quantity_issued_out,  // quantity_issued (issued-out movement)
                $office_officer_issued,
                $quantity_returned,
                $office_officer_returned,
                $quantity_reissued,
                $office_officer_reissued,
                $quantity_disposed,
                $quantity_balance,
                $unit_amount,
                $amount_total,
                $category,
                $fund_cluster,
                $remarks
            );
            if ($stmt->execute()) {
                // Capture new row id before closing
                $new_id = $conn->insert_id;
                $stmt->close();

                // Record initial snapshot in history so exports always show a baseline row
                ensure_semi_expendable_history($conn);
                if ($h = $conn->prepare("INSERT INTO semi_expendable_history (
                        semi_id, date, ics_rrsp_no, quantity, quantity_issued, quantity_returned, quantity_reissued, quantity_disposed,
                        quantity_balance, office_officer_issued, office_officer_returned, office_officer_reissued, amount, amount_total, remarks
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")) {
                    $histRemarks = $remarks !== '' ? $remarks : 'Initial Receipt';
                    $h->bind_param(
                        "issiiiiiisssdds",
                        $new_id,
                        $date,
                        $ics_rrsp_no,
                        $quantity_issued,        // quantity (base)
                        $quantity_issued_out,    // quantity_issued (issued-out)
                        $quantity_returned,
                        $quantity_reissued,
                        $quantity_disposed,
                        $quantity_balance,
                        $office_officer_issued,
                        $office_officer_returned,
                        $office_officer_reissued,
                        $unit_amount,
                        $amount_total,
                        $histRemarks
                    );
                    @ $h->execute();
                    $h->close();
                }

                // If user provided Quantity Issued (issued-out movement), create ICS entry
                if ($quantity_issued_out > 0) {
                    // Determine ICS No: use user input if provided, otherwise generate
                    $auto_ics_no = ($ics_rrsp_no_input !== '') ? $ics_rrsp_no_input : generateICSNumberSimple($conn);
                    
                    // Check if ICS No. already exists
                    $checkIcs = $conn->prepare("SELECT ics_id FROM ics WHERE ics_no = ? LIMIT 1");
                    $checkIcs->bind_param("s", $auto_ics_no);
                    $checkIcs->execute();
                    $checkIcsResult = $checkIcs->get_result();
                    if ($checkIcsResult && $checkIcsResult->num_rows > 0) {
                        $error = "A record with this ICS/RRSP No. already exists. Please use a different number.";
                        $checkIcs->close();
                        // Delete the semi-expendable record we just created
                        $conn->query("DELETE FROM semi_expendable_property WHERE id = $new_id");
                        // Don't close $stmt here - it was already closed after insert
                    } else {
                        $checkIcs->close();
                        
                        $entity_name = 'TESDA Regional Office';
                        $received_by = $office_officer_issued ?: 'N/A';
                        $received_by_position = '(auto)';
                        $received_from = 'Property Custodian';
                        $received_from_position = '(auto)';

                        if ($icsHdr = $conn->prepare("INSERT INTO ics (ics_no, entity_name, fund_cluster, date_issued, received_by, received_by_position, received_from, received_from_position) VALUES (?,?,?,?,?,?,?,?)")) {
                            $icsHdr->bind_param("ssssssss", $auto_ics_no, $entity_name, $fund_cluster, $date, $received_by, $received_by_position, $received_from, $received_from_position);
                            if ($icsHdr->execute()) {
                            $new_ics_id = $icsHdr->insert_id;
                            $icsHdr->close();

                            // Insert ICS item mapped from the just-added semi-expendable
                            $stock_no = $semi_expendable_property_no;
                            $issued_qty = (float)$quantity_issued_out;
                            $unit_cost = (float)$unit_amount;
                            $total_cost = $issued_qty * $unit_cost;
                            // Per request: do not use remarks as description; always use item_description
                            $descVal = $item_description;
                            $unitVal = $unit;
                            $useful_life_val = (string)$estimated_useful_life;
                            $serial_no = '';

                            if ($icsItem = $conn->prepare("INSERT INTO ics_items (ics_id, stock_number, quantity, unit, unit_cost, total_cost, description, inventory_item_no, estimated_useful_life, serial_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")) {
                                $icsItem->bind_param("isdsddssss", $new_ics_id, $stock_no, $issued_qty, $unitVal, $unit_cost, $total_cost, $descVal, $stock_no, $useful_life_val, $serial_no);
                                @ $icsItem->execute();
                                $icsItem->close();
                            }

                            // Update semi-expendable with ICS No. reference
                            if ($u2 = $conn->prepare("UPDATE semi_expendable_property SET ics_rrsp_no = ? WHERE id = ?")) {
                                $u2->bind_param("si", $auto_ics_no, $new_id);
                                @ $u2->execute();
                                $u2->close();
                            }

                            // Also add to regspi table (using same structure as submit_itr.php)
                            try {
                                // Find or create regspi header
                                $regspi_id = null;
                                $entity_name_val = 'TESDA Regional Office';
                                $find = $conn->prepare("SELECT id FROM regspi WHERE entity_name = ? AND (fund_cluster <=> ?) LIMIT 1");
                                $find->bind_param('ss', $entity_name_val, $fund_cluster);
                                $find->execute();
                                $fr = $find->get_result();
                                if ($fr && $fr->num_rows > 0) { $regspi_id = (int)$fr->fetch_assoc()['id']; }
                                $find->close();
                                if (!$regspi_id) {
                                    $ins = $conn->prepare("INSERT INTO regspi (entity_name, fund_cluster, semi_expendable_property) VALUES (?, ?, ?)");
                                    $sep = $category;
                                    $ins->bind_param('sss', $entity_name_val, $fund_cluster, $sep);
                                    if ($ins->execute()) { $regspi_id = $ins->insert_id; }
                                    $ins->close();
                                }
                                if ($regspi_id) {
                                    $useful_life_val = (string)$estimated_useful_life;
                                    $amt = $unit_cost * $issued_qty;
                                    $disposed1 = 0; $disposed2 = 0; $returnedQty = 0; $returnedOffice = null; $reissued_office = null;
                                    $insE = $conn->prepare("INSERT INTO regspi_entries (regspi_id, `date`, ics_rrsp_no, property_no, item_description, useful_life, issued_qty, issued_office, returned_qty, returned_office, reissued_qty, reissued_office, disposed_qty1, disposed_qty2, balance_qty, amount, remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                                    $insE->bind_param('isssssisisisiiids', $regspi_id, $date, $auto_ics_no, $stock_no, $descVal, $useful_life_val, $issued_qty, $received_by, $returnedQty, $returnedOffice, $quantity_reissued, $reissued_office, $disposed1, $disposed2, $quantity_balance, $amt, $remarks);
                                    @$insE->execute();
                                    $insE->close();
                                }
                            } catch (Throwable $e) { /* ignore non-fatal */ }

                            // Add issuance snapshot to history similar to manual ICS issuance
                            $amount_total_curr = round($unit_amount * $quantity_issued, 2);
                            if ($h2 = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity, quantity_issued, quantity_reissued, quantity_disposed, quantity_balance, office_officer_issued, amount, amount_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")) {
                                    // Do not set an automatic remark here; leave blank to avoid extra tags
                                    $remarks2 = '';
                                $h2->bind_param("issiiiiisdds", $new_id, $date, $auto_ics_no, $quantity_issued, $quantity_issued_out, $quantity_reissued, $quantity_disposed, $quantity_balance, $received_by, $unit_amount, $amount_total_curr, $remarks2);
                                @ $h2->execute();
                                $h2->close();
                            }
                            
                            // Redirect back to listing with category filter after successful ICS creation
                            $redirectCategory = isset($category) && $category !== '' ? ('?category=' . urlencode($category)) : '';
                            if (ob_get_level() > 0) { ob_end_clean(); }
                            header('Location: semi_expendible.php' . $redirectCategory);
                            exit();
                        } else {
                            $icsHdr->close();
                        }
                        }
                    }
                }
                
                // Only redirect if no error occurred and no ICS was needed
                if (empty($error)) {
                    $redirectCategory = isset($category) && $category !== '' ? ('?category=' . urlencode($category)) : '';
                    if (ob_get_level() > 0) { ob_end_clean(); }
                    header('Location: semi_expendible.php' . $redirectCategory);
                    exit();
                }
            } else {
                $error = "Failed to add item: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

// Get default category from URL
$default_category = isset($_GET['category']) && in_array($_GET['category'], $valid_categories) ? $_GET['category'] : 'Other PPE';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Semi-Expendable Item</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?php echo time(); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        .form-container {
            max-width: 800px;
            margin: 30px auto;
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            backdrop-filter: blur(10px);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, #475569 0%, #334155 100%);
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .required {
            color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <header style="margin-bottom: 30px; border-bottom: 3px solid #3b82f6; padding-bottom: 15px;">
                <h1 style="color: #1e293b; display: flex; align-items: center; gap: 12px; margin: 0 0 8px 0;">
                    <i class="fas fa-plus-circle" style="color: #3b82f6;"></i>
                    Add New Semi-Expendable Property
                </h1>
                <p style="color: #64748b; margin: 0;">Register a new item in the semi-expendable property registry</p>
            </header>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="date">Date <span class="required">*</span></label>
                        <input type="date" id="date" name="date" value="<?php echo $_POST['date'] ?? date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="ics_rrsp_no">ICS/RRSP No.</label>
                        <input type="text" id="ics_rrsp_no" name="ics_rrsp_no" 
                               value="<?php echo $_POST['ics_rrsp_no'] ?? ''; ?>" 
                               placeholder="e.g., 22-01 (optional)">
                        
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="semi_expendable_property_no">Semi-Expendable Property No. <span class="required">*</span></label>
                        <input type="text" id="semi_expendable_property_no" name="semi_expendable_property_no" 
                               value="<?php echo $_POST['semi_expendable_property_no'] ?? ''; ?>" 
                               placeholder="e.g., HV-22-101-01" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category <span class="required">*</span></label>
                        <select id="category" name="category" required>
                            <?php foreach ($valid_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php echo ($cat === ($_POST['category'] ?? $default_category)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="item_description">Item Description <span class="required">*</span></label>
                    <textarea id="item_description" name="item_description" 
                              placeholder="Detailed description of the item..." required><?php echo $_POST['item_description'] ?? ''; ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="estimated_useful_life">Estimated Useful Life (years) <span class="required">*</span></label>
                        <input type="number" id="estimated_useful_life" name="estimated_useful_life" 
                               value="<?php echo $_POST['estimated_useful_life'] ?? '5'; ?>" min="1" max="20" required>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount <span class="required">*</span></label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0"
                               value="<?php echo $_POST['amount'] ?? ''; ?>" 
                               placeholder="Unit amount (per item)" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="unit">Unit</label>
                        <input type="text" id="unit" name="unit" 
                               value="<?php echo isset($_POST['unit']) ? htmlspecialchars($_POST['unit']) : ''; ?>" 
                               placeholder="e.g., pc, box, set">
                    </div>
                    <div class="form-group">
                        <label for="quantity_issued">Quantity <span class="required">*</span></label>
                        <input type="number" id="quantity_issued" name="quantity_issued" min="0"
                               value="<?php echo $_POST['quantity_issued'] ?? '1'; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="fund_cluster">Fund Cluster</label>
                    <input type="text" id="fund_cluster" name="fund_cluster" 
                           value="<?php echo $_POST['fund_cluster'] ?? '101'; ?>" 
                           placeholder="101">
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 20px; color: #374151;">Returns, Issued & Reissued (Optional)</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity_issued_out">Quantity Issued</label>
                        <input type="number" id="quantity_issued_out" name="quantity_issued_out" min="0"
                               value="<?php echo $_POST['quantity_issued_out'] ?? '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="office_officer_issued">Office/Officer Issued</label>
                        <input type="text" id="office_officer_issued" name="office_officer_issued" 
                               value="<?php echo $_POST['office_officer_issued'] ?? ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity_returned">Quantity Returned</label>
                        <input type="number" id="quantity_returned" name="quantity_returned" min="0"
                               value="<?php echo $_POST['quantity_returned'] ?? '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="office_officer_returned">Office/Officer Returned</label>
                        <input type="text" id="office_officer_returned" name="office_officer_returned" 
                               value="<?php echo $_POST['office_officer_returned'] ?? ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity_reissued">Quantity Re-issued</label>
                        <input type="number" id="quantity_reissued" name="quantity_reissued" min="0"
                               value="<?php echo $_POST['quantity_reissued'] ?? '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="office_officer_reissued">Office/Officer Re-issued</label>
                        <input type="text" id="office_officer_reissued" name="office_officer_reissued" 
                               value="<?php echo $_POST['office_officer_reissued'] ?? ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity_disposed">Quantity Disposed</label>
                        <input type="number" id="quantity_disposed" name="quantity_disposed" min="0"
                               value="<?php echo $_POST['quantity_disposed'] ?? '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="quantity_balance">Quantity Balance <span class="required">*</span></label>
                        <input type="number" id="quantity_balance" name="quantity_balance" min="0"
                               value="<?php echo $_POST['quantity_balance'] ?? '1'; ?>" required readonly>
                    
                    </div>
                </div>

                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" placeholder="Additional notes or remarks..."><?php echo $_POST['remarks'] ?? ''; ?></textarea>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="pill-btn pill-add">
                        <i class="fas fa-save"></i> Add Item
                    </button>
                    <a href="semi_expendible.php?category=<?php echo urlencode($default_category); ?>">
                        <button class="pill-btn pill-view" type="button"><i class="fas fa-ban"></i> Cancel</button>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Auto-calculate balance when quantities change
    function calculateBalance() {
        const quantity = parseInt(document.getElementById('quantity_issued').value) || 0; // base quantity
        const qtyIssuedOut = parseInt(document.getElementById('quantity_issued_out')?.value) || 0;
        const qtyReissued = parseInt(document.getElementById('quantity_reissued').value) || 0;
        const qtyDisposed = parseInt(document.getElementById('quantity_disposed').value) || 0;
        const balance = quantity - (qtyIssuedOut + qtyReissued + qtyDisposed);
        document.getElementById('quantity_balance').value = Math.max(0, balance);

        // When balance is 0, allow decreases but prevent increases beyond current values
        const locked = (Math.max(0, balance) === 0);
        ['quantity_issued_out','quantity_reissued','quantity_disposed'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (locked) {
                // Use current value as the cap
                const cap = parseInt(el.value) || 0;
                el.max = String(cap);
                el.title = 'Balance is 0: You can reduce values but cannot increase beyond current amounts.';
            } else {
                el.removeAttribute('max');
                el.removeAttribute('title');
            }
        });
    }

    // Add event listeners
    document.addEventListener('DOMContentLoaded', function() {
    const quantityFields = ['quantity_issued', 'quantity_issued_out', 'quantity_returned', 'quantity_reissued', 'quantity_disposed'];
        
        quantityFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('input', calculateBalance);
                field.addEventListener('change', calculateBalance);
            }
        });

        // Initial calculation and lock state
        calculateBalance();
    });
    </script>
</body>
</html>