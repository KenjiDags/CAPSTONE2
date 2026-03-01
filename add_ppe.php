<?php
require 'auth.php';
require 'config.php';
require 'functions.php';
include 'sidebar.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = $_POST['item_name'];
    $PPE_no = $_POST['PPE_no'];

    // Check for duplicate PPE_no
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM ppe_property WHERE PPE_no = ?");
    $stmt_check->bind_param('s', $PPE_no);
    $stmt_check->execute();
    $stmt_check->bind_result($ppe_no_count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($ppe_no_count > 0) {
        $error = "Item with PPE No already exists";
    } else {
        // Autogenerate property_no as YY-MM-000# (unique)
        $now = new DateTime();
        $yy = $now->format('y');
        $mm = $now->format('m');
        $prefix = $yy . '-' . $mm . '-';
        $stmt = $conn->prepare("SELECT property_no FROM ppe_property WHERE property_no LIKE ? ORDER BY property_no DESC LIMIT 1");
        $like = $prefix . '%';
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $stmt->bind_result($last_no);
        $stmt->fetch();
        $stmt->close();
        if ($last_no) {
            $last_seq = intval(substr($last_no, 6));
            $new_seq = $last_seq + 1;
        } else {
            $new_seq = 1;
        }
        $property_no = sprintf('%s%04d', $prefix, $new_seq);
        $item_description = $_POST['item_description'];
        $amount = floatval($_POST['amount']);
        $quantity = intval($_POST['quantity']);
        $unit = $_POST['unit'];
        $officer_incharge = $_POST['officer_incharge'];
        $custodian = $_POST['custodian'];
        $entity_name = $_POST['entity_name'];
        $status = $_POST['status'];
        $condition = $_POST['condition'];
        $fund_cluster = $_POST['fund_cluster'] ?? '101';
        $remarks = $_POST['remarks'];

        // Insert PPE item without PAR No.
        $stmt_insert = $conn->prepare("
            INSERT INTO ppe_property
            (PPE_no, property_no, item_name, item_description, amount, quantity, unit,
            officer_incharge, custodian, entity_name, `condition`, status, fund_cluster, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
        ");
        $stmt_insert->bind_param(
            "sssssdisssssss",
            $PPE_no, $property_no, $item_name, $item_description, $amount, $quantity, $unit,
            $officer_incharge, $custodian, $entity_name, $condition, $status, $fund_cluster, $remarks
        );
        if ($stmt_insert->execute()) {
            $success = "PPE item added successfully!";
            // Log history for initial addition to item_history_ppe
            $new_id = $conn->insert_id;
        $change_direction = 'increase';
        $change_type = 'add';
        $receipt_qty = $quantity;
        $balance_qty = $quantity;
        $quantity_change = $quantity;
        $issue_qty = 0;
        $insert = $conn->prepare("INSERT INTO item_history_ppe (PPE_no, item_name, description, unit, unit_cost, quantity_on_hand, quantity_change, receipt_qty, issue_qty, balance_qty, officer_incharge, change_direction, change_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->bind_param(
            "ssssdiiiiisss",
            $PPE_no,
            $item_name,
            $item_description,
            $unit,
            $amount,
            $quantity,
            $quantity_change,
            $receipt_qty,
            $issue_qty,
            $balance_qty,
            $custodian,
            $change_direction,
            $change_type
        );
        $insert->execute();
        $insert->close();
        $stmt_insert->close();
    } else {
        $error = "Failed to add item: " . $stmt_insert->error;
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add New PPE Item</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
<style>
    .form-container {
        max-width: 800px;
        margin: 30px auto;
        background: rgba(255, 255, 255, 0.95);
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
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
        <header style="margin-bottom: 30px; border-bottom: 3px solid #3b82f6; padding-bottom: 15px;">
            <h1 style="margin: 0 0 8px 0; display: flex; align-items: center; gap: 12px;"><i class="fas fa-plus-circle" style="color: #3b82f6;"></i> Add New PPE Item</h1>
            <p style="color: #64748b; margin: 0;">Register a new PPE item in the system</p>
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
                    <label for="item_name">Item Name <span class="required">*</span></label>
                    <input type="text" id="item_name" name="item_name" required>
                </div>
                <div class="form-group">
                    <label for="PPE_no">PPE No. <span class="required">*</span></label>
                    <input type="text" id="PPE_no" name="PPE_no" required>
                </div>
            </div>

            <div class="form-group">
                <label for="item_description">Description <span class="required">*</span></label>
                <textarea id="item_description" name="item_description" required></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="amount">Amount <span class="required">*</span></label>
                    <input type="number" id="amount" name="amount" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity <span class="required">*</span></label>
                    <input type="number" id="quantity" name="quantity" value="1" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="unit">Unit</label>
                    <input type="text" id="unit" name="unit" value="unit">
                </div>
                <div class="form-group">
                    <label for="officer_incharge">Officer In-charge <span class="required">*</span></label>
                    <input type="text" id="officer_incharge" name="officer_incharge" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="custodian">Custodian <span class="required">*</span></label>
                    <input type="text" id="custodian" name="custodian" required>
                </div>
                <div class="form-group">
                    <label for="entity_name">Entity Name <span class="required">*</span></label>
                    <input type="text" id="entity_name" name="entity_name" value="TESDA Regional Office" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Active">Active</option>
                        <option value="Transferred">Transferred</option>
                        <option value="Returned">Returned</option>
                        <option value="For Repair">For Repair</option>
                        <option value="Unserviceable">Unserviceable</option>
                        <option value="Disposed">Disposed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="fund_cluster">Fund Cluster</label>
                    <input type="text" id="fund_cluster" name="fund_cluster" value="101">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="condition">Condition</label>
                    <select id="condition" name="condition">
                        <option value="Good">Good</option>
                        <option value="Fair">Fair</option>
                        <option value="Poor">Poor</option>
                        <option value="Unserviceable">Unserviceable</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks"></textarea>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="pill-btn pill-add"><i class="fas fa-plus"></i> Add Item</button>
                <a href="PPE.php">
                    <button type="button" class="pill-btn pill-view">
                        <i class="fas fa-ban"></i> Cancel
                    </button>
                </a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
