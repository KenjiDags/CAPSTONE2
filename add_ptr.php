<?php
require 'auth.php';
require 'config.php';
require 'functions.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ptr'])) {
    $ptr_no = trim($_POST['ptr_no']);
    $entity_name = trim($_POST['entity_name']);
    $fund_cluster = trim($_POST['fund_cluster']);
    $from_officer = trim($_POST['from_officer']);
    $to_officer = trim($_POST['to_officer']);
    $transfer_date = $_POST['transfer_date'];
    $transfer_type = $_POST['transfer_type'];
    $reason = trim($_POST['reason']);
    $approved_by = trim($_POST['approved_by']);
    $released_by = trim($_POST['released_by']);
    $received_by = trim($_POST['received_by']);
    $item_ids = isset($_POST['item_ids']) ? $_POST['item_ids'] : [];

    if (empty($ptr_no) || empty($from_officer) || empty($to_officer) || empty($transfer_date) || empty($item_ids)) {
        $error = "Please fill in all required fields and select at least one item.";
    } else {
        // Insert PTR header
        $stmt = $conn->prepare("INSERT INTO ppe_ptr (ptr_no, entity_name, fund_cluster, from_officer, to_officer, transfer_date, transfer_type, reason, approved_by, released_by, received_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssss", $ptr_no, $entity_name, $fund_cluster, $from_officer, $to_officer, $transfer_date, $transfer_type, $reason, $approved_by, $released_by, $received_by);
        
        if ($stmt->execute()) {
            $ptr_id = $conn->insert_id;
            
            // Insert PTR items
            $item_stmt = $conn->prepare("INSERT INTO ppe_ptr_items (ptr_id, ppe_id) VALUES (?, ?)");
            foreach ($item_ids as $ppe_id) {
                $item_stmt->bind_param("ii", $ptr_id, $ppe_id);
                $item_stmt->execute();
            }
            $item_stmt->close();
            
            $success = "Property Transfer Report created successfully!";
            header("Location: PPE_PTR.php");
            exit();
        } else {
            $error = "Failed to create PTR: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all PPE items for selection
$ppe_items = $conn->query("SELECT * FROM ppe_property ORDER BY par_no ASC");

include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Property Transfer Report</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
<style>
    .form-container {
        max-width: 1000px;
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
    .items-selection {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        max-height: 400px;
        overflow-y: auto;
    }
    .item-checkbox {
        display: flex;
        align-items: center;
        padding: 8px;
        border-bottom: 1px solid #f0f0f0;
    }
    .item-checkbox:hover {
        background: #f8f9fa;
    }
    .item-checkbox input {
        width: auto;
        margin-right: 10px;
    }
    .item-info {
        flex: 1;
        font-size: 14px;
    }
</style>
</head>
<body>
<div class="container">
    <div class="form-container">
        <header style="margin-bottom: 30px;">
            <h1>Add Property Transfer Report (PTR)</h1>
            <p>Create a new property transfer record</p>
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
                    <label for="ptr_no">PTR No <span class="required">*</span></label>
                    <input type="text" id="ptr_no" name="ptr_no" required>
                </div>
                <div class="form-group">
                    <label for="transfer_date">Transfer Date <span class="required">*</span></label>
                    <input type="date" id="transfer_date" name="transfer_date" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="entity_name">Entity Name <span class="required">*</span></label>
                    <input type="text" id="entity_name" name="entity_name" value="TESDA Regional Office" required>
                </div>
                <div class="form-group">
                    <label for="fund_cluster">Fund Cluster</label>
                    <input type="text" id="fund_cluster" name="fund_cluster" value="101">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="from_officer">From Accountable Officer <span class="required">*</span></label>
                    <input type="text" id="from_officer" name="from_officer" required>
                </div>
                <div class="form-group">
                    <label for="to_officer">To Accountable Officer <span class="required">*</span></label>
                    <input type="text" id="to_officer" name="to_officer" required>
                </div>
            </div>

            <div class="form-group">
                <label for="transfer_type">Transfer Type</label>
                <select id="transfer_type" name="transfer_type">
                    <option value="Donation">Donation</option>
                    <option value="Relocation">Relocation</option>
                    <option value="Reassignment">Reassignment</option>
                    <option value="Others">Others</option>
                </select>
            </div>

            <div class="form-group">
                <label for="reason">Reason / Purpose</label>
                <textarea id="reason" name="reason"></textarea>
            </div>

            <h3 style="margin-top: 30px; margin-bottom: 15px; color: #333;">Signature Details</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="approved_by">Approved By</label>
                    <input type="text" id="approved_by" name="approved_by" placeholder="Name and Position">
                </div>
                <div class="form-group">
                    <label for="released_by">Released/Issued By</label>
                    <input type="text" id="released_by" name="released_by" placeholder="Name and Position">
                </div>
            </div>

            <div class="form-group">
                <label for="received_by">Received By</label>
                <input type="text" id="received_by" name="received_by" placeholder="Name and Position">
            </div>

            <div class="form-group">
                <label>Select PPE Items to Transfer <span class="required">*</span></label>
                <div class="items-selection">
                    <?php while ($item = $ppe_items->fetch_assoc()): ?>
                        <div class="item-checkbox">
                            <input type="checkbox" name="item_ids[]" value="<?= $item['id'] ?>" id="item_<?= $item['id'] ?>">
                            <label for="item_<?= $item['id'] ?>" class="item-info">
                                <strong><?= htmlspecialchars($item['par_no']) ?></strong> - 
                                <?= htmlspecialchars($item['item_name']) ?> - 
                                <?= htmlspecialchars($item['item_description']) ?> 
                                (₱<?= number_format($item['amount'], 2) ?>)
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" name="submit_ptr" class="btn btn-primary">Create PTR</button>
                <a href="PPE_PTR.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
