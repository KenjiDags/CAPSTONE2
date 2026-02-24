<?php
require 'auth.php';
require 'config.php';
require 'functions.php';
include 'sidebar.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // $par_no removed
    $item_name = $_POST['item_name'];
    $item_description = $_POST['item_description'];
    $amount = floatval($_POST['amount']);
    $quantity = intval($_POST['quantity']);
    $unit = $_POST['unit'];
    $officer_incharge = $_POST['officer_incharge'];
    $custodian = $_POST['custodian'];
    $entity_name = $_POST['entity_name'];
    // $ptr_no removed
    $status = $_POST['status'];
    $condition = $_POST['condition'];
    $fund_cluster = $_POST['fund_cluster'] ?? '101';
    $remarks = $_POST['remarks'];

    // Insert PPE item without PAR No.
    $stmt_insert = $conn->prepare("
        INSERT INTO ppe_property 
        (item_name, item_description, amount, quantity, unit, officer_incharge, custodian, entity_name, status, `condition`, fund_cluster, remarks) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_insert->bind_param(
        "ssdissssssss",
        $item_name, $item_description, $amount, $quantity, $unit,
        $officer_incharge, $custodian, $entity_name, $status, $condition, $fund_cluster, $remarks
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
        $insert = $conn->prepare("INSERT INTO item_history_ppe (property_no, item_name, description, unit, unit_cost, quantity_on_hand, quantity_change, receipt_qty, issue_qty, balance_qty, officer_incharge, change_direction, change_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->bind_param(
            "isssdiiiiisss",
            $new_id,
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
            <h1>Add New PPE Item</h1>
            <p>Register a new PPE item in the system</p>
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
