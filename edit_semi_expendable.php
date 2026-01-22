<?php
// edit_semi_expendable.php - Edit form for semi-expendable property
// Start output buffering to allow safe redirects even if sidebar outputs content
ob_start();
require 'auth.php';
require_once 'config.php';
require_once 'functions.php';
require_once 'sidebar.php'; 
// Ensure 'unit' column exists on semi_expendable_property (idempotent)
try {
    if (function_exists('columnExists') && !columnExists($conn, 'semi_expendable_property', 'unit')) {
        @$conn->query("ALTER TABLE semi_expendable_property ADD COLUMN unit VARCHAR(64) NULL AFTER item_description");
    }
} catch (Throwable $e) { /* no-op */ }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = null;
$error = '';
$success = '';

// Valid categories
$valid_categories = ['Other PPE', 'Office Equipment', 'ICT Equipment', 'Communication Equipment', 'Furniture and Fixtures'];

// Fetch item details
if ($id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM semi_expendable_property WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        
        if (!$item) {
            $error = "Item not found.";
        }
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $item) {
    try {
    // Ensure required columns exist (idempotent)
    ensure_semi_expendable_amount_columns($conn);
        // Ensure history table exists (idempotent) and record a snapshot BEFORE updating
    $conn->query("CREATE TABLE IF NOT EXISTS semi_expendable_history (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    // Add missing column for legacy tables
    try { $conn->query("ALTER TABLE semi_expendable_history ADD COLUMN IF NOT EXISTS amount DECIMAL(15,2) DEFAULT 0"); } catch (Throwable $e) { /* no-op */ }

        // Begin transaction so history and update are atomic
        if (method_exists($conn, 'begin_transaction')) {
            $conn->begin_transaction();
        }

        $stmt = $conn->prepare("
            UPDATE semi_expendable_property 
            SET date = ?, 
                ics_rrsp_no = ?, 
                semi_expendable_property_no = ?, 
                item_description = ?, 
                unit = ?, 
                estimated_useful_life = ?, 
                quantity = ?,
                quantity_issued = ?, 
                office_officer_issued = ?, 
                quantity_returned = ?, 
                office_officer_returned = ?, 
                quantity_reissued = ?, 
                office_officer_reissued = ?, 
                quantity_disposed = ?, 
                quantity_balance = ?, 
                amount = ?, 
                amount_total = ?, 
                category = ?, 
                remarks = ?
            WHERE id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Prepare variables (must be variables for bind_param references)
        $p_date = $_POST['date'];
        $p_ics_rrsp_no = ''; // Default empty value since field is removed
        $p_property_no = $_POST['semi_expendable_property_no'];
    $p_item_desc = $_POST['item_description'];
    $p_unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $p_useful_life = isset($_POST['estimated_useful_life']) ? (int)$_POST['estimated_useful_life'] : 0;
    $p_qty_issued = isset($_POST['quantity_issued']) ? (int)$_POST['quantity_issued'] : 0; // base quantity
    $p_qty_issued_out = isset($_POST['quantity_issued_out']) ? (int)$_POST['quantity_issued_out'] : 0; // optional issued qty for balance
        $p_officer_issued = $_POST['office_officer_issued'] ?? '';
        $p_qty_returned = isset($_POST['quantity_returned']) ? (int)$_POST['quantity_returned'] : 0;
        $p_officer_returned = $_POST['office_officer_returned'] ?? '';
        $p_qty_reissued = isset($_POST['quantity_reissued']) ? (int)$_POST['quantity_reissued'] : 0;
        $p_officer_reissued = $_POST['office_officer_reissued'] ?? '';
        $p_qty_disposed = isset($_POST['quantity_disposed']) ? (int)$_POST['quantity_disposed'] : 0;
    // Balance = Quantity - (Quantity Issued + Quantity Re-issued + Quantity Disposed)
    $p_qty_balance = max(0, $p_qty_issued - ($p_qty_issued_out + $p_qty_reissued + $p_qty_disposed));
    // Unit amount input; compute total = quantity × unit amount
    $p_unit_amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
    $p_amount_total = round($p_unit_amount * $p_qty_issued, 2);
    $p_category = $_POST['category'];
    $p_remarks = $_POST['remarks'] ?? '';

    // Previous state values for validation (from loaded $item)
    $prev_qty_base = isset($item['quantity']) ? (int)$item['quantity'] : (int)($item['quantity_issued'] ?? 0);
    $prev_qty_issued = (int)($item['quantity_issued'] ?? 0);
    $prev_qty_returned = (int)($item['quantity_returned'] ?? 0);
    $prev_qty_reissued = (int)($item['quantity_reissued'] ?? 0);
    $prev_qty_disposed = (int)($item['quantity_disposed'] ?? 0);
    $prev_qty_balance = isset($item['quantity_balance']) ? (int)$item['quantity_balance'] : max(0, $prev_qty_base - ($prev_qty_reissued + $prev_qty_disposed));

        // If current balance is zero, allow decreases but block increases beyond previous values
        if ($prev_qty_balance === 0) {
            $incIssued = $p_qty_issued_out > $prev_qty_issued;
            $incReissued = $p_qty_reissued > $prev_qty_reissued;
            $incDisposed = $p_qty_disposed > $prev_qty_disposed;
            if ($incIssued || $incReissued || $incDisposed) {
                throw new Exception('Balance is 0: You can reduce values but cannot increase issued/reissued/disposed.');
            }
        }

        // Do not allow the sum of issued+reissued+disposed to exceed base quantity
        $totalOut = $p_qty_issued_out + $p_qty_reissued + $p_qty_disposed;
        if ($totalOut > $p_qty_issued) {
            throw new Exception('Issued + Re-issued + Disposed cannot exceed Quantity.');
        }

        $stmt->bind_param(
            "sssssiiisisisiiddssi",
            $p_date,
            $p_ics_rrsp_no,
            $p_property_no,
            $p_item_desc,
            $p_unit,
            $p_useful_life,
            $p_qty_issued, // quantity (base)
            $p_qty_issued_out,
            $p_officer_issued,
            $p_qty_returned,
            $p_officer_returned,
            $p_qty_reissued,
            $p_officer_reissued,
            $p_qty_disposed,
            $p_qty_balance,
            $p_unit_amount,
            $p_amount_total,
            $p_category,
            $p_remarks,
            $id
        );
        
        if ($stmt->execute()) {
            // After successful update, sync related ICS items so ICS stays connected to Semi edits
            $stmt->close();

            // Record a post-update snapshot so exports reflect the latest values
            if ($histPost = $conn->prepare("INSERT INTO semi_expendable_history (
                    semi_id, date, ics_rrsp_no, quantity, quantity_issued, quantity_returned, quantity_reissued, quantity_disposed,
                    quantity_balance, office_officer_issued, office_officer_returned, office_officer_reissued, amount, amount_total, remarks
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")) {
                $histPost->bind_param(
                    "issiiiiiisssdds",
                    $id,
                    $p_date,
                    $p_ics_rrsp_no,
                    $p_qty_issued,
                    $p_qty_issued_out,
                    $p_qty_returned,
                    $p_qty_reissued,
                    $p_qty_disposed,
                    $p_qty_balance,
                    $p_officer_issued,
                    $p_officer_returned,
                    $p_officer_reissued,
                    $p_unit_amount,
                    $p_amount_total,
                    $p_remarks
                );
                @ $histPost->execute();
                $histPost->close();
            }

            // 1) Update ICS item with ALL semi-expendable changes: description, useful life, quantity, unit_cost, total_cost, and unit
            $newPropNo = (string)$p_property_no;
            $newDesc = (string)$p_item_desc;
            $newLife = (string)$p_useful_life; // ics_items stores useful life as text
            $newUnit = (string)$p_unit;
            $newQuantity = (float)$p_qty_issued_out; // Use the issued quantity
            $newUnitCost = (float)$p_unit_amount;
            $newTotalCost = round($newQuantity * $newUnitCost, 2);
            
            if ($u1 = $conn->prepare("UPDATE ics_items SET description = ?, estimated_useful_life = ?, unit = ?, quantity = ?, unit_cost = ?, total_cost = ? WHERE inventory_item_no = ? OR stock_number = ?")) {
                $u1->bind_param("sssdddss", $newDesc, $newLife, $newUnit, $newQuantity, $newUnitCost, $newTotalCost, $newPropNo, $newPropNo);
                @ $u1->execute();
                $u1->close();
            }

            // 2) If the property number changed, propagate the new number to existing ICS items
            $oldPropNo = (string)($item['semi_expendable_property_no'] ?? '');
            if ($oldPropNo !== '' && $oldPropNo !== $newPropNo) {
                if ($u2 = $conn->prepare("UPDATE ics_items SET inventory_item_no = ?, stock_number = ? WHERE inventory_item_no = ? OR stock_number = ?")) {
                    $u2->bind_param("ssss", $newPropNo, $newPropNo, $oldPropNo, $oldPropNo);
                    @ $u2->execute();
                    $u2->close();
                }
            }

            if (method_exists($conn, 'commit')) { $conn->commit(); }
            // Determine return target: prefer explicit return param, else fallback to supply list by category
            $returnTarget = $_POST['return'] ?? ($_GET['return'] ?? '');
            // Basic safety: only allow relative PHP pages with optional query string
            $isSafe = is_string($returnTarget) && preg_match('/^[A-Za-z0-9_\-]+\.php(\?.*)?$/', $returnTarget);
            if (ob_get_level() > 0) { ob_end_clean(); }
            if ($isSafe && $returnTarget !== '') {
                header('Location: ' . $returnTarget);
                exit();
            }
            $redirectCategory = isset($p_category) && $p_category !== '' ? ('?category=' . urlencode($p_category)) : '';
            header('Location: semi_expendible.php' . $redirectCategory);
            exit();
        } else {
            $error = "Failed to update item: " . $stmt->error;
            $stmt->close();
            if (method_exists($conn, 'rollback')) { $conn->rollback(); }
        }
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
        if (method_exists($conn, 'rollback')) { $conn->rollback(); }
    }
}

if (!$item && empty($error)) {
    $error = "Invalid item ID.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Semi-Expendable Item</title>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <header style="margin-bottom: 30px;">
                <h1>Edit Semi-Expendable Property</h1>
                <p>Update item details in the registry</p>
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

                        <?php if ($item): ?>
                <?php
                  // Compute cancel/back URL: prefer 'return' when safe
                  $cancelUrl = 'semi_expendible.php?category=' . urlencode($item['category']);
                  $returnGet = $_GET['return'] ?? '';
                  if (is_string($returnGet) && preg_match('/^[A-Za-z0-9_\-]+\.php(\?.*)?$/', $returnGet)) {
                      $cancelUrl = $returnGet;
                  }
                                    // Determine previous balance from DB to drive client-side lock behavior
                                    $orig_qty_base = isset($item['quantity']) ? (int)$item['quantity'] : (int)($item['quantity_issued'] ?? 0);
                                    $orig_qty_reissued = (int)($item['quantity_reissued'] ?? 0);
                                    $orig_qty_disposed = (int)($item['quantity_disposed'] ?? 0);
                                    $orig_qty_balance = isset($item['quantity_balance']) ? (int)$item['quantity_balance'] : max(0, $orig_qty_base - ($orig_qty_reissued + $orig_qty_disposed));
                ?>
                <form method="POST">
                    <input type="hidden" name="return" value="<?php echo htmlspecialchars($_GET['return'] ?? ''); ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? $item['date']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="semi_expendable_property_no">Semi-Expendable Property No.</label>
                            <input type="text" id="semi_expendable_property_no" name="semi_expendable_property_no" 
                                   value="<?php echo htmlspecialchars($_POST['semi_expendable_property_no'] ?? $item['semi_expendable_property_no']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" required>
                                <?php foreach ($valid_categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" 
                        <?php echo $cat === ($_POST['category'] ?? $item['category']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="item_description">Item Description</label>
                        <textarea id="item_description" name="item_description" required><?php echo htmlspecialchars($_POST['item_description'] ?? $item['item_description']); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="estimated_useful_life">Estimated Useful Life (years)</label>
                <input type="number" id="estimated_useful_life" name="estimated_useful_life" 
                    value="<?php echo htmlspecialchars($_POST['estimated_useful_life'] ?? $item['estimated_useful_life']); ?>" min="1" max="20" required>
                        </div>
                        <div class="form-group">
                            <label for="amount">Amount</label>
                            <?php 
                                $unitAmount = null;
                                if (isset($_POST['amount'])) {
                                    $unitAmount = (float)$_POST['amount'];
                                } else {
                                    $qtyBase = isset($item['quantity']) ? (int)$item['quantity'] : 0;
                                    $unitAmount = ($qtyBase > 0) ? ((float)$item['amount_total'] / $qtyBase) : 0.0;
                                }
                            ?>
                            <input type="number" id="amount" name="amount" step="0.01" min="0"
                                   value="<?php echo htmlspecialchars(number_format((float)$unitAmount, 2, '.', '')); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="unit">Unit</label>
                            <input type="text" id="unit" name="unit"
                                   value="<?php echo htmlspecialchars(isset($_POST['unit']) ? $_POST['unit'] : ($item['unit'] ?? '')); ?>"
                                   placeholder="e.g., pc, box, set">
                        </div>
                        <div class="form-group">
                            <label for="quantity_issued">Quantity</label>
                <input type="number" id="quantity_issued" name="quantity_issued" min="0"
                    value="<?php echo htmlspecialchars(isset($_POST['quantity_issued']) ? $_POST['quantity_issued'] : (isset($item['quantity']) ? $item['quantity'] : ($item['quantity_issued'] ?? 0))); ?>" required>
                        </div>
                    </div>

                    <h3 style="margin-top: 30px; margin-bottom: 20px; color: #374151;">Returns, Issued & Reissued (Optional)</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity_issued_out">Quantity Issued</label>
                            <input type="number" id="quantity_issued_out" name="quantity_issued_out" min="0" value="<?php echo htmlspecialchars(isset($_POST['quantity_issued_out']) ? (int)$_POST['quantity_issued_out'] : (int)($item['quantity_issued'] ?? 0)); ?>" data-original="<?php echo htmlspecialchars((int)($item['quantity_issued'] ?? 0)); ?>">
                        </div>
                        <div class="form-group">
                            <label for="office_officer_issued">Office/Officer Issued</label>
                            <input type="text" id="office_officer_issued" name="office_officer_issued" 
                                   value="<?php echo htmlspecialchars($_POST['office_officer_issued'] ?? $item['office_officer_issued'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity_returned">Quantity Returned</label>
                            <input type="number" id="quantity_returned" name="quantity_returned" min="0"
                                   value="<?php echo htmlspecialchars($_POST['quantity_returned'] ?? $item['quantity_returned'] ?? '0'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="office_officer_returned">Office/Officer Returned</label>
                            <input type="text" id="office_officer_returned" name="office_officer_returned" 
                                   value="<?php echo htmlspecialchars($_POST['office_officer_returned'] ?? $item['office_officer_returned'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity_reissued">Quantity Re-issued</label>
                <input type="number" id="quantity_reissued" name="quantity_reissued" min="0"
                    value="<?php echo htmlspecialchars($_POST['quantity_reissued'] ?? $item['quantity_reissued']); ?>" data-original="<?php echo htmlspecialchars((int)($item['quantity_reissued'] ?? 0)); ?>">
                        </div>
                        <div class="form-group">
                            <label for="office_officer_reissued">Office/Officer Re-issued</label>
                            <input type="text" id="office_officer_reissued" name="office_officer_reissued" 
                                   value="<?php echo htmlspecialchars($_POST['office_officer_reissued'] ?? $item['office_officer_reissued'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity_disposed">Quantity Disposed</label>
                <input type="number" id="quantity_disposed" name="quantity_disposed" min="0"
                    value="<?php echo htmlspecialchars($_POST['quantity_disposed'] ?? $item['quantity_disposed']); ?>" data-original="<?php echo htmlspecialchars((int)($item['quantity_disposed'] ?? 0)); ?>">
                        </div>
                        <div class="form-group">
                            <label for="quantity_balance">Quantity Balance</label>
                            <input type="number" id="quantity_balance" name="quantity_balance" min="0"
                                   value="<?php echo htmlspecialchars($_POST['quantity_balance'] ?? $item['quantity_balance']); ?>" required>                   
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks"><?php echo htmlspecialchars($_POST['remarks'] ?? $item['remarks'] ?? ''); ?></textarea>
                    </div>

                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">Update Item</button>
                        <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?> 
        </div>
    </div>

    <script>
    // Previous balance from DB; if zero, we lock increases beyond original values
    window.PREV_BALANCE_ZERO = <?php echo ($orig_qty_balance === 0) ? 'true' : 'false'; ?>;

    // Auto-calculate balance when quantities change
    function calculateBalance(ev) {
        const qEl = document.getElementById('quantity_issued');
        const iEl = document.getElementById('quantity_issued_out');
        const rEl = document.getElementById('quantity_reissued');
        const dEl = document.getElementById('quantity_disposed');
        const retEl = document.getElementById('quantity_returned');
        const bEl = document.getElementById('quantity_balance');

        const quantity = parseInt(qEl?.value) || 0;
        let issuedOut = parseInt(iEl?.value) || 0;
        let reissued  = parseInt(rEl?.value) || 0;
        let disposed  = parseInt(dEl?.value) || 0;
        let returned  = parseInt(retEl?.value) || 0;

        // Only enforce lock if the PREVIOUS (DB) balance was already zero
        const locked = !!window.PREV_BALANCE_ZERO;
        if (locked) {
            [{el:iEl},{el:rEl},{el:dEl},{el:retEl}].forEach(({el}) => {
                if (!el) return;
                const maxVal = el.dataset.original ? parseInt(el.dataset.original) : null;
                if (maxVal !== null && !Number.isNaN(maxVal)) {
                    el.max = String(maxVal);
                    const cur = parseInt(el.value) || 0;
                    if (cur > maxVal) { el.value = String(maxVal); }
                }
                el.title = 'Previous balance is 0: you can reduce values but cannot increase beyond original amounts.';
            });
            // Re-read values after potential clamping
            issuedOut = parseInt(iEl?.value) || 0;
            reissued  = parseInt(rEl?.value) || 0;
            disposed  = parseInt(dEl?.value) || 0;
            returned  = parseInt(retEl?.value) || 0;
        } else {
            // No lock: ensure fields are free to change
            [iEl, rEl, dEl, retEl].forEach(el => { if (el) { el.removeAttribute('max'); el.removeAttribute('title'); } });
        }

        // Enforce that issuedOut + reissued + disposed - returned cannot exceed quantity
        let total = issuedOut + reissued + disposed;
        if (!locked && total > quantity) {
            const last = ev && ev.target ? ev.target.id : '';
            const clampField = (fieldEl) => {
                if (!fieldEl) return;
                const cur = parseInt(fieldEl.value) || 0;
                const over = (issuedOut + reissued + disposed) - quantity;
                const newVal = Math.max(0, cur - over);
                fieldEl.value = String(newVal);
            };
            // Prefer clamping the field being edited; fallback to issuedOut
            if (last === 'quantity_reissued') clampField(rEl);
            else if (last === 'quantity_disposed') clampField(dEl);
            else if (last === 'quantity_returned') clampField(retEl);
            else clampField(iEl);
            // Recompute after clamping
            issuedOut = parseInt(iEl?.value) || 0;
            reissued  = parseInt(rEl?.value) || 0;
            disposed  = parseInt(dEl?.value) || 0;
            returned  = parseInt(retEl?.value) || 0;
            total = issuedOut + reissued + disposed - returned;
        }

        const balance = quantity - total;
        if (bEl) bEl.value = Math.max(0, balance);

        // If balance hits 0 during this session (and previous balance wasn't zero), lock further increases at current values
        if (!locked) {
            const atZero = Math.max(0, balance) === 0;
            [iEl, rEl, dEl, retEl].forEach(el => {
                if (!el) return;
                if (atZero) {
                    const cur = parseInt(el.value) || 0;
                    el.max = String(cur); // lock at current value; allows decreases only
                    el.title = 'Balance is 0: cannot increase further; reduce values to change.';
                } else {
                    el.removeAttribute('max');
                    el.removeAttribute('title');
                }
            });
        }
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