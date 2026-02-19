<?php
require 'auth.php';
require 'config.php';
require 'functions.php';

$error = '';
$success = '';

// Get PTR ID from URL
$ptr_id = isset($_GET['ptr_id']) ? intval($_GET['ptr_id']) : 0;

if ($ptr_id <= 0) {
    header("Location: PPE_PTR.php");
    exit();
}

// Fetch existing PTR data
$stmt = $conn->prepare("SELECT * FROM ppe_ptr WHERE ptr_id = ?");
$stmt->bind_param("i", $ptr_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: PPE_PTR.php");
    exit();
}

$ptr = $result->fetch_assoc();
$stmt->close();

// Fetch existing PTR items
$items_stmt = $conn->prepare("SELECT ppe_id FROM ppe_ptr_items WHERE ptr_id = ?");
$items_stmt->bind_param("i", $ptr_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$existing_item_ids = [];
while ($row = $items_result->fetch_assoc()) {
    $existing_item_ids[] = $row['ppe_id'];
}
$items_stmt->close();

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
    $approved_by_designation = trim($_POST['approved_by_designation']);
    $approved_by_date = $_POST['approved_by_date'];
    $released_by = trim($_POST['released_by']);
    $released_by_designation = trim($_POST['released_by_designation']);
    $released_by_date = $_POST['released_by_date'];
    $received_by = trim($_POST['received_by']);
    $received_by_designation = trim($_POST['received_by_designation']);
    $received_by_date = $_POST['received_by_date'];
    $item_ids = isset($_POST['item_ids']) ? $_POST['item_ids'] : [];

    if (empty($ptr_no) || empty($from_officer) || empty($to_officer) || empty($transfer_date) || empty($item_ids)) {
        $error = "Please fill in all required fields and select at least one item.";
    } else {
        // Update PTR header
        $stmt = $conn->prepare("UPDATE ppe_ptr SET ptr_no = ?, entity_name = ?, fund_cluster = ?, from_officer = ?, to_officer = ?, transfer_date = ?, transfer_type = ?, reason = ?, approved_by = ?, approved_by_designation = ?, approved_by_date = ?, released_by = ?, released_by_designation = ?, released_by_date = ?, received_by = ?, received_by_designation = ?, received_by_date = ? WHERE ptr_id = ?");
        $stmt->bind_param("sssssssssssssssssi", $ptr_no, $entity_name, $fund_cluster, $from_officer, $to_officer, $transfer_date, $transfer_type, $reason, $approved_by, $approved_by_designation, $approved_by_date, $released_by, $released_by_designation, $released_by_date, $received_by, $received_by_designation, $received_by_date, $ptr_id);
        
        if ($stmt->execute()) {
            // Delete old PTR items
            $conn->query("DELETE FROM ppe_ptr_items WHERE ptr_id = $ptr_id");
            
            // Insert new PTR items
            $item_stmt = $conn->prepare("INSERT INTO ppe_ptr_items (ptr_id, ppe_id) VALUES (?, ?)");
            foreach ($item_ids as $ppe_id) {
                $item_stmt->bind_param("ii", $ptr_id, $ppe_id);
                $item_stmt->execute();
            }
            $item_stmt->close();
            
            // Update officer_incharge and custodian for all transferred items
            $update_stmt = $conn->prepare("UPDATE ppe_property SET officer_incharge = ?, custodian = ?, status = 'Transferred' WHERE id = ?");
            foreach ($item_ids as $ppe_id) {
                $update_stmt->bind_param("ssi", $to_officer, $to_officer, $ppe_id);
                $update_stmt->execute();
            }
            $update_stmt->close();
            
            $success = "Property Transfer Report updated successfully!";
            header("Location: PPE_PTR.php");
            exit();
        } else {
            $error = "Failed to update PTR: " . $stmt->error;
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
<title>Edit Property Transfer Report</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    .form-container {
        max-width: 1000px;
        margin: 0 auto;
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .page-header {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 3px solid #3b82f6;
    }
    .page-header h1 {
        color: #1e293b;
        font-size: 28px;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .page-header h1 i {
        color: #3b82f6;
    }
    .page-header p {
        margin: 0;
        color: #64748b;
        font-size: 15px;
    }
    h3, h4 {
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    h3 i, h4 i {
        color: #3b82f6;
        font-size: 0.9em;
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
        padding: 12px 14px;
        border: 2px solid #e5e7eb;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s ease;
        background-color: #fff;
    }
    .form-group input:hover,
    .form-group select:hover,
    .form-group textarea:hover {
        border-color: #cbd5e1;
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
        padding: 12px 28px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-right: 12px;
        font-size: 15px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    }
    .btn-secondary {
        background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%);
        color: white;
    }
    .btn-secondary:hover {
        background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    }
    .button-group {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #e5e7eb;
        display: flex;
        gap: 12px;
    }
    .alert {
        padding: 16px 20px;
        border-radius: 8px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
    }
    .alert::before {
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        font-size: 20px;
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 2px solid #a7f3d0;
    }
    .alert-success::before {
        content: "\f058";
        color: #10b981;
    }
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 2px solid #fca5a5;
    }
    .alert-error::before {
        content: "\f06a";
        color: #ef4444;
    }
    .required {
        color: #dc2626;
    }
    .items-selection {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 15px;
        max-height: 400px;
        overflow-y: auto;
        background: #f9fafb;
    }
    .item-checkbox {
        display: flex;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid #e5e7eb;
        border-radius: 6px;
        margin-bottom: 4px;
        background: #fff;
        transition: all 0.2s ease;
    }
    .item-checkbox:last-child {
        border-bottom: none;
    }
    .item-checkbox:hover {
        background: #eff6ff;
        border-color: #3b82f6;
        transform: translateX(4px);
    }
    .item-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-right: 12px;
        cursor: pointer;
        accent-color: #3b82f6;
    }
    .item-info {
        flex: 1;
        font-size: 14px;
        cursor: pointer;
    }
    .item-info strong {
        color: #1e293b;
    }
</style>
</head>
<body>
<div class="container">
    <div class="form-container">
        <header class="page-header">
            <h1><i class="fas fa-edit"></i> Edit Property Transfer Report (PTR)</h1>
            <p>Update property transfer record</p>
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
                    <input type="text" id="ptr_no" name="ptr_no" value="<?= htmlspecialchars($ptr['ptr_no']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="transfer_date">Transfer Date <span class="required">*</span></label>
                    <input type="date" id="transfer_date" name="transfer_date" value="<?= htmlspecialchars($ptr['transfer_date']) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="entity_name">Entity Name <span class="required">*</span></label>
                    <input type="text" id="entity_name" name="entity_name" value="<?= htmlspecialchars($ptr['entity_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="fund_cluster">Fund Cluster</label>
                    <input type="text" id="fund_cluster" name="fund_cluster" value="<?= htmlspecialchars($ptr['fund_cluster']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="from_officer">From Accountable Officer <span class="required">*</span></label>
                    <input type="text" id="from_officer" name="from_officer" value="<?= htmlspecialchars($ptr['from_officer']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="to_officer">To Accountable Officer <span class="required">*</span></label>
                    <input type="text" id="to_officer" name="to_officer" value="<?= htmlspecialchars($ptr['to_officer']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="transfer_type">Transfer Type</label>
                <select id="transfer_type" name="transfer_type">
                    <option value="Donation" <?= $ptr['transfer_type'] == 'Donation' ? 'selected' : '' ?>>Donation</option>
                    <option value="Relocation" <?= $ptr['transfer_type'] == 'Relocation' ? 'selected' : '' ?>>Relocation</option>
                    <option value="Reassignment" <?= $ptr['transfer_type'] == 'Reassignment' ? 'selected' : '' ?>>Reassignment</option>
                    <option value="Others" <?= $ptr['transfer_type'] == 'Others' ? 'selected' : '' ?>>Others</option>
                </select>
            </div>

            <div class="form-group">
                <label for="reason">Reason / Purpose</label>
                <textarea id="reason" name="reason"><?= htmlspecialchars($ptr['reason']) ?></textarea>
            </div>

            <h3 style="margin-top: 30px; margin-bottom: 15px;"><i class="fas fa-pen-nib"></i> Signature Details</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="approved_by">Approved By - Name</label>
                    <input type="text" id="approved_by" name="approved_by" value="<?= htmlspecialchars($ptr['approved_by']) ?>" placeholder="Full Name">
                </div>
                <div class="form-group">
                    <label for="approved_by_designation">Approved By - Designation</label>
                    <input type="text" id="approved_by_designation" name="approved_by_designation" value="<?= htmlspecialchars($ptr['approved_by_designation']) ?>" placeholder="Position/Designation">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="released_by">Released/Issued By - Name</label>
                    <input type="text" id="released_by" name="released_by" value="<?= htmlspecialchars($ptr['released_by']) ?>" placeholder="Full Name">
                </div>
                <div class="form-group">
                    <label for="released_by_designation">Released/Issued By - Designation</label>
                    <input type="text" id="released_by_designation" name="released_by_designation" value="<?= htmlspecialchars($ptr['released_by_designation']) ?>" placeholder="Position/Designation">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="received_by">Received By - Name</label>
                    <input type="text" id="received_by" name="received_by" value="<?= htmlspecialchars($ptr['received_by']) ?>" placeholder="Full Name">
                </div>
                <div class="form-group">
                    <label for="received_by_designation">Received By - Designation</label>
                    <input type="text" id="received_by_designation" name="received_by_designation" value="<?= htmlspecialchars($ptr['received_by_designation']) ?>" placeholder="Position/Designation">
                </div>
            </div>

            <h4 style="margin-top: 20px; margin-bottom: 15px;"><i class="fas fa-calendar-alt"></i> Signature Dates</h4>

            <div class="form-row">
                <div class="form-group">
                    <label for="approved_by_date">Approved By - Date</label>
                    <input type="date" id="approved_by_date" name="approved_by_date" value="<?= htmlspecialchars($ptr['approved_by_date']) ?>">
                </div>
                <div class="form-group">
                    <label for="released_by_date">Released/Issued By - Date</label>
                    <input type="date" id="released_by_date" name="released_by_date" value="<?= htmlspecialchars($ptr['released_by_date']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="received_by_date">Received By - Date</label>
                <input type="date" id="received_by_date" name="received_by_date" value="<?= htmlspecialchars($ptr['received_by_date']) ?>">
            </div>

            <div class="form-group">
                <label>Select PPE Items to Transfer <span class="required">*</span></label>
                <div class="items-selection">
                    <?php while ($item = $ppe_items->fetch_assoc()): ?>
                        <div class="item-checkbox">
                            <input type="checkbox" name="item_ids[]" value="<?= $item['id'] ?>" id="item_<?= $item['id'] ?>" <?= in_array($item['id'], $existing_item_ids) ? 'checked' : '' ?>>
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

            <div class="button-group">
                <button type="submit" name="submit_ptr" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Update PTR
                </button>
                <a href="PPE_PTR.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
