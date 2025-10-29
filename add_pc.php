<?php
require 'config.php';

// Check if editing existing record
$editing = false;
$pc_data = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $editing = true;
    $pc_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM property_cards WHERE pc_id = ?");
    $stmt->bind_param("i", $pc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $pc_data = $result->fetch_assoc();
    } else {
        header("Location: pc.php?error=Record not found");
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entity_name = trim($_POST['entity_name']);
    $fund_cluster = trim($_POST['fund_cluster']);
    $ppe_type = trim($_POST['ppe_type']);
    $description = trim($_POST['description']);
    $property_number = trim($_POST['property_number']);
    $transaction_date = $_POST['transaction_date'];
    $reference_par_no = trim($_POST['reference_par_no']);
    $receipt_qty = (float)$_POST['receipt_qty'];
    $issue_qty = (float)$_POST['issue_qty'];
    $office_officer = trim($_POST['office_officer']);
    $amount = (float)$_POST['amount'];
    $remarks = trim($_POST['remarks']);
    $transaction_type = $_POST['transaction_type'];
    
    if ($editing) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE property_cards SET entity_name=?, fund_cluster=?, ppe_type=?, description=?, property_number=?, transaction_date=?, reference_par_no=?, receipt_qty=?, issue_qty=?, office_officer=?, amount=?, remarks=?, transaction_type=? WHERE pc_id=?");
        $stmt->bind_param("sssssssddssssi", $entity_name, $fund_cluster, $ppe_type, $description, $property_number, $transaction_date, $reference_par_no, $receipt_qty, $issue_qty, $office_officer, $amount, $remarks, $transaction_type, $pc_id);
        
        if ($stmt->execute()) {
            header("Location: pc.php?success=Record updated successfully");
        } else {
            $error = "Error updating record: " . $conn->error;
        }
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO property_cards (entity_name, fund_cluster, ppe_type, description, property_number, transaction_date, reference_par_no, receipt_qty, issue_qty, office_officer, amount, remarks, transaction_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssddssss", $entity_name, $fund_cluster, $ppe_type, $description, $property_number, $transaction_date, $reference_par_no, $receipt_qty, $issue_qty, $office_officer, $amount, $remarks, $transaction_type);
        
        if ($stmt->execute()) {
            header("Location: pc.php?success=Record added successfully");
        } else {
            $error = "Error adding record: " . $conn->error;
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editing ? 'Edit' : 'Add' ?> Property Card Entry</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .required {
            color: red;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <h2><?= $editing ? 'Edit' : 'Add New' ?> Property Card Entry</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="entity_name">Entity Name <span class="required">*</span></label>
                        <input type="text" id="entity_name" name="entity_name" value="<?= htmlspecialchars($pc_data['entity_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="fund_cluster">Fund Cluster <span class="required">*</span></label>
                        <input type="text" id="fund_cluster" name="fund_cluster" value="<?= htmlspecialchars($pc_data['fund_cluster'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="ppe_type">Property, Plant and Equipment <span class="required">*</span></label>
                        <select id="ppe_type" name="ppe_type" required>
                            <option value="">Select PPE Type</option>
                            <option value="Office Equipment" <?= ($pc_data['ppe_type'] ?? '') == 'Office Equipment' ? 'selected' : '' ?>>Office Equipment</option>
                            <option value="IT Equipment" <?= ($pc_data['ppe_type'] ?? '') == 'IT Equipment' ? 'selected' : '' ?>>IT Equipment</option>
                            <option value="Furniture and Fixtures" <?= ($pc_data['ppe_type'] ?? '') == 'Furniture and Fixtures' ? 'selected' : '' ?>>Furniture and Fixtures</option>
                            <option value="Appliances" <?= ($pc_data['ppe_type'] ?? '') == 'Appliances' ? 'selected' : '' ?>>Appliances</option>
                            <option value="Vehicles" <?= ($pc_data['ppe_type'] ?? '') == 'Vehicles' ? 'selected' : '' ?>>Vehicles</option>
                            <option value="Other Equipment" <?= ($pc_data['ppe_type'] ?? '') == 'Other Equipment' ? 'selected' : '' ?>>Other Equipment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="property_number">Property Number <span class="required">*</span></label>
                        <input type="text" id="property_number" name="property_number" value="<?= htmlspecialchars($pc_data['property_number'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description <span class="required">*</span></label>
                    <textarea id="description" name="description" placeholder="Include brand, size, color, serial number, model, etc." required><?= htmlspecialchars($pc_data['description'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="transaction_date">Transaction Date <span class="required">*</span></label>
                        <input type="date" id="transaction_date" name="transaction_date" value="<?= $pc_data['transaction_date'] ?? date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="reference_par_no">Reference/PAR No.</label>
                        <input type="text" id="reference_par_no" name="reference_par_no" value="<?= htmlspecialchars($pc_data['reference_par_no'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="transaction_type">Transaction Type <span class="required">*</span></label>
                        <select id="transaction_type" name="transaction_type" required onchange="toggleQuantityFields()">
                            <option value="receipt" <?= ($pc_data['transaction_type'] ?? '') == 'receipt' ? 'selected' : '' ?>>Receipt</option>
                            <option value="issue" <?= ($pc_data['transaction_type'] ?? '') == 'issue' ? 'selected' : '' ?>>Issue</option>
                            <option value="transfer" <?= ($pc_data['transaction_type'] ?? '') == 'transfer' ? 'selected' : '' ?>>Transfer</option>
                            <option value="disposal" <?= ($pc_data['transaction_type'] ?? '') == 'disposal' ? 'selected' : '' ?>>Disposal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount <span class="required">*</span></label>
                        <input type="number" id="amount" name="amount" step="0.01" value="<?= $pc_data['amount'] ?? '0.00' ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="receipt_qty">Receipt Quantity</label>
                        <input type="number" id="receipt_qty" name="receipt_qty" step="0.01" value="<?= $pc_data['receipt_qty'] ?? '0.00' ?>">
                    </div>
                    <div class="form-group">
                        <label for="issue_qty">Issue/Transfer/Disposal Quantity</label>
                        <input type="number" id="issue_qty" name="issue_qty" step="0.01" value="<?= $pc_data['issue_qty'] ?? '0.00' ?>">
                    </div>
                </div>

                <div class="form-group" id="office_officer_group">
                    <label for="office_officer">Office/Officer (for Issue/Transfer/Disposal)</label>
                    <input type="text" id="office_officer" name="office_officer" value="<?= htmlspecialchars($pc_data['office_officer'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" placeholder="Important information, observations, or comments"><?= htmlspecialchars($pc_data['remarks'] ?? '') ?></textarea>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $editing ? 'Update' : 'Add' ?> Property Card Entry
                    </button>
                    <a href="pc.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleQuantityFields() {
            const transactionType = document.getElementById('transaction_type').value;
            const receiptQty = document.getElementById('receipt_qty');
            const issueQty = document.getElementById('issue_qty');
            const officeOfficer = document.getElementById('office_officer');
            
            if (transactionType === 'receipt') {
                receiptQty.style.backgroundColor = '#fff3cd';
                issueQty.style.backgroundColor = '#f8f9fa';
                officeOfficer.style.backgroundColor = '#f8f9fa';
                receiptQty.required = true;
                issueQty.required = false;
                officeOfficer.required = false;
            } else {
                receiptQty.style.backgroundColor = '#f8f9fa';
                issueQty.style.backgroundColor = '#fff3cd';
                officeOfficer.style.backgroundColor = '#fff3cd';
                receiptQty.required = false;
                issueQty.required = true;
                officeOfficer.required = true;
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleQuantityFields();
        });
        
        // Auto-generate property number if empty and PPE type is selected
        document.getElementById('ppe_type').addEventListener('change', function() {
            const propertyNumber = document.getElementById('property_number');
            if (!propertyNumber.value && this.value) {
                const year = new Date().getFullYear();
                const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
                propertyNumber.value = `PPE-${year}-${randomNum}`;
            }
        });
    </script>
</body>
</html>