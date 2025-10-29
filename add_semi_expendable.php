<?php
// Enable safe redirects even if sidebar outputs content
ob_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'sidebar.php'; // Add sidebar requirement
// Ensure required columns exist (idempotent)
ensure_semi_expendable_amount_columns($conn);
// Ensure 'unit' column exists on semi_expendable_property (idempotent)
try {
    if (function_exists('columnExists') && !columnExists($conn, 'semi_expendable_property', 'unit')) {
        @$conn->query("ALTER TABLE semi_expendable_property ADD COLUMN unit VARCHAR(64) NULL AFTER item_description");
    }
} catch (Throwable $e) { /* no-op */ }

// Local helper: generate a simple ICS number like ICS-YYYY/MM/0001
if (!function_exists('generateICSNumberSimple')) {
    function generateICSNumberSimple($conn) {
        $current_year = (int)date('Y');
        $current_month = (int)date('m');
        $prefix = 'ICS-' . date('Y') . '/' . date('m') . '/';
        $next_increment = 1;
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ics WHERE YEAR(date_issued) = ? AND MONTH(date_issued) = ?");
        if ($stmt) {
            $stmt->bind_param('ii', $current_year, $current_month);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res) {
                    $row = $res->fetch_assoc();
                    $next_increment = ((int)($row['count'] ?? 0)) + 1;
                }
            }
            $stmt->close();
        }
        $formatted = str_pad((string)$next_increment, 4, '0', STR_PAD_LEFT);
        return $prefix . $formatted;
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
    $ics_rrsp_no = $_POST['ics_rrsp_no'];
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

                // If user provided Quantity Issued (issued-out movement), auto-create a minimal ICS entry
                if ($quantity_issued_out > 0) {
                    // Create ICS header
                    $auto_ics_no = generateICSNumberSimple($conn);
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
                            $descVal = ($remarks !== '') ? $remarks : $item_description;
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

                            // Add issuance snapshot to history similar to manual ICS issuance
                            $amount_total_curr = round($unit_amount * $quantity_issued, 2);
                            if ($h2 = $conn->prepare("INSERT INTO semi_expendable_history (semi_id, date, ics_rrsp_no, quantity, quantity_issued, quantity_reissued, quantity_disposed, quantity_balance, office_officer_issued, amount, amount_total, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")) {
                                $remarks2 = 'ICS Issued';
                                $h2->bind_param("issiiiiisdds", $new_id, $date, $auto_ics_no, $quantity_issued, $quantity_issued_out, $quantity_reissued, $quantity_disposed, $quantity_balance, $received_by, $unit_amount, $amount_total_curr, $remarks2);
                                @ $h2->execute();
                                $h2->close();
                            }
                        } else {
                            $icsHdr->close();
                        }
                    }
                }
                // Redirect back to listing with category filter
                $redirectCategory = isset($category) && $category !== '' ? ('?category=' . urlencode($category)) : '';
                if (ob_get_level() > 0) { ob_end_clean(); }
                header('Location: semi_expendible.php' . $redirectCategory);
                exit();
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
    <!-- Add your existing CSS links here -->
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
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
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
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
            <header style="margin-bottom: 30px;">
                <h1>Add New Semi-Expendable Property</h1>
                <p>Register a new item in the semi-expendable property registry</p>
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
                        <label for="ics_rrsp_no">ICS/RRSP No. <span class="required">*</span></label>
                        <input type="text" id="ics_rrsp_no" name="ics_rrsp_no" value="<?php echo $_POST['ics_rrsp_no'] ?? ''; ?>" 
                               placeholder="e.g., 22-01" required>
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
                </div>

                <div class="form-group">
                    <label for="fund_cluster">Fund Cluster</label>
                    <input type="text" id="fund_cluster" name="fund_cluster" 
                           value="<?php echo $_POST['fund_cluster'] ?? '101'; ?>" 
                           placeholder="101">
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 20px; color: #374151;">Quantity</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity_issued">Quantity <span class="required">*</span></label>
                        <input type="number" id="quantity_issued" name="quantity_issued" min="0"
                               value="<?php echo $_POST['quantity_issued'] ?? '1'; ?>" required>
                    </div>
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
                        <small style="color: #6b7280;">Auto-calculated: Quantity - (Quantity Issued + Quantity Re-issued + Quantity Disposed)</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" placeholder="Additional notes or remarks..."><?php echo $_POST['remarks'] ?? ''; ?></textarea>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">Add Item</button>
                    <a href="semi_expendible.php?category=<?php echo urlencode($default_category); ?>" class="btn btn-secondary">Cancel</a>
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