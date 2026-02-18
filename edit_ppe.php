<?php
require 'auth.php';
require 'config.php';
require 'functions.php';
include 'sidebar.php';

$error = '';
$success = '';
$item = null;

// Get item ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: PPE.php');
    exit();
}

// Fetch existing item
$stmt = $conn->prepare("SELECT * FROM ppe_property WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    header('Location: PPE.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $par_no = $_POST['par_no'];
    $item_name = $_POST['item_name'];
    $item_description = $_POST['item_description'];
    $amount = floatval($_POST['amount']);
    $quantity = intval($_POST['quantity']);
    $unit = $_POST['unit'];
    $officer_incharge = $_POST['officer_incharge'];
    $custodian = $_POST['custodian'];
    $entity_name = $_POST['entity_name'];
    $ptr_no = $_POST['ptr_no'];
    $status = $_POST['status'];
    $condition = $_POST['condition'];
    $fund_cluster = $_POST['fund_cluster'] ?? '101';
    $remarks = $_POST['remarks'];

    // Check if PAR No already exists for a different item
    $stmt_check = $conn->prepare("SELECT id FROM ppe_property WHERE par_no = ? AND id != ? LIMIT 1");
    $stmt_check->bind_param('si', $par_no, $id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    
    if ($res_check && $res_check->num_rows > 0) {
        $error = "Another PPE item with this PAR No. already exists.";
    } else {
        $stmt_update = $conn->prepare("
            UPDATE ppe_property 
            SET par_no = ?, item_name = ?, item_description = ?, amount = ?, quantity = ?, 
                unit = ?, officer_incharge = ?, custodian = ?, entity_name = ?, ptr_no = ?, 
                status = ?, `condition` = ?, fund_cluster = ?, remarks = ?
            WHERE id = ?
        ");
        $stmt_update->bind_param(
            "sssdisssssssssi",
            $par_no, $item_name, $item_description, $amount, $quantity, $unit,
            $officer_incharge, $custodian, $entity_name, $ptr_no, $status, $condition, 
            $fund_cluster, $remarks, $id
        );
        
        if ($stmt_update->execute()) {
            $success = "PPE item updated successfully!";
            // Refresh item data
            $stmt = $conn->prepare("SELECT * FROM ppe_property WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $item = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error = "Failed to update item: " . $stmt_update->error;
        }
        $stmt_update->close();
    }
    $stmt_check->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit PPE Item</title>
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
            <h1>Edit PPE Item</h1>
            <p>Update PPE item details</p>
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
                    <label for="par_no">PAR No <span class="required">*</span></label>
                    <input type="text" id="par_no" name="par_no" value="<?= htmlspecialchars($item['par_no']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="item_name">Item Name <span class="required">*</span></label>
                    <input type="text" id="item_name" name="item_name" value="<?= htmlspecialchars($item['item_name']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="item_description">Description <span class="required">*</span></label>
                <textarea id="item_description" name="item_description" required><?= htmlspecialchars($item['item_description']) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="amount">Amount <span class="required">*</span></label>
                    <input type="number" id="amount" name="amount" step="0.01" value="<?= htmlspecialchars($item['amount']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="quantity">Quantity <span class="required">*</span></label>
                    <input type="number" id="quantity" name="quantity" value="<?= htmlspecialchars($item['quantity']) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="unit">Unit</label>
                    <input type="text" id="unit" name="unit" value="<?= htmlspecialchars($item['unit']) ?>">
                </div>
                <div class="form-group">
                    <label for="officer_incharge">Officer In-charge <span class="required">*</span></label>
                    <input type="text" id="officer_incharge" name="officer_incharge" value="<?= htmlspecialchars($item['officer_incharge']) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="custodian">Custodian <span class="required">*</span></label>
                    <input type="text" id="custodian" name="custodian" value="<?= htmlspecialchars($item['custodian']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="entity_name">Entity Name <span class="required">*</span></label>
                    <input type="text" id="entity_name" name="entity_name" value="<?= htmlspecialchars($item['entity_name']) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="ptr_no">PTR No</label>
                    <input type="text" id="ptr_no" name="ptr_no" value="<?= htmlspecialchars($item['ptr_no']) ?>">
                </div>
                <div class="form-group">
                    <label for="fund_cluster">Fund Cluster</label>
                    <input type="text" id="fund_cluster" name="fund_cluster" value="<?= htmlspecialchars($item['fund_cluster']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Active" <?= $item['status'] == 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Transferred" <?= $item['status'] == 'Transferred' ? 'selected' : '' ?>>Transferred</option>
                        <option value="Returned" <?= $item['status'] == 'Returned' ? 'selected' : '' ?>>Returned</option>
                        <option value="For Repair" <?= $item['status'] == 'For Repair' ? 'selected' : '' ?>>For Repair</option>
                        <option value="Unserviceable" <?= $item['status'] == 'Unserviceable' ? 'selected' : '' ?>>Unserviceable</option>
                        <option value="Disposed" <?= $item['status'] == 'Disposed' ? 'selected' : '' ?>>Disposed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="condition">Condition</label>
                    <select id="condition" name="condition">
                        <option value="Good" <?= $item['condition'] == 'Good' ? 'selected' : '' ?>>Good</option>
                        <option value="Fair" <?= $item['condition'] == 'Fair' ? 'selected' : '' ?>>Fair</option>
                        <option value="Poor" <?= $item['condition'] == 'Poor' ? 'selected' : '' ?>>Poor</option>
                        <option value="Unserviceable" <?= $item['condition'] == 'Unserviceable' ? 'selected' : '' ?>>Unserviceable</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks"><?= htmlspecialchars($item['remarks']) ?></textarea>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Update Item</button>
                <a href="PPE.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
