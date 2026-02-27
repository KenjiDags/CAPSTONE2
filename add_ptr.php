<?php
require 'auth.php';
require 'config.php';
require 'functions.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ptr'])) {
    // Auto-generate PTR No if not editing
    $is_editing = isset($_POST['is_editing']) && $_POST['is_editing'] == '1';
    if (!$is_editing) {
        $current_yy = date('y');
        $current_mm = date('m');
        // Find latest increment for this month/year
        $stmt = $conn->prepare("SELECT ptr_no FROM ppe_ptr WHERE ptr_no LIKE ? ORDER BY ptr_no DESC LIMIT 1");
        $prefix = $current_yy . '-' . $current_mm . '-%';
        $stmt->bind_param('s', $prefix);
        $stmt->execute();
        $res = $stmt->get_result();
        $next_num = 1;
        if ($res && $row = $res->fetch_assoc()) {
            // Extract last number
            $last_ptr_no = $row['ptr_no'];
            $parts = explode('-', $last_ptr_no);
            if (count($parts) === 3 && is_numeric($parts[2])) {
                $next_num = intval($parts[2]) + 1;
            }
        }
        $stmt->close();
        $ptr_no = sprintf('%s-%s-%02d', $current_yy, $current_mm, $next_num);
    } else {
        $ptr_no = trim($_POST['ptr_no']);
    }
    $entity_name = trim($_POST['entity_name']);
    $fund_cluster = trim($_POST['fund_cluster']);
    $from_officer = trim($_POST['from_officer']);
    $to_officer = trim($_POST['to_officer']);
    $transfer_date = $_POST['transfer_date'];
    $transfer_type = $_POST['transfer_type'];
    
    // If "Others" is selected, use the custom input value
    if ($transfer_type === 'Others' && !empty($_POST['transfer_type_others'])) {
        $transfer_type = 'Others: ' . trim($_POST['transfer_type_others']);
    }
    
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

    if (empty($from_officer) || empty($to_officer) || empty($transfer_date) || empty($item_ids)) {
        $error = "Please fill in all required fields and select at least one item.";
    } else {
        // Insert PTR header
        $stmt = $conn->prepare("INSERT INTO ppe_ptr (ptr_no, entity_name, fund_cluster, from_officer, to_officer, transfer_date, transfer_type, reason, approved_by, approved_by_designation, approved_by_date, released_by, released_by_designation, released_by_date, received_by, received_by_designation, received_by_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssssssssss", $ptr_no, $entity_name, $fund_cluster, $from_officer, $to_officer, $transfer_date, $transfer_type, $reason, $approved_by, $approved_by_designation, $approved_by_date, $released_by, $released_by_designation, $released_by_date, $received_by, $received_by_designation, $received_by_date);
        
        if ($stmt->execute()) {
            $ptr_id = $conn->insert_id;
            
            // Insert PTR items
            $item_stmt = $conn->prepare("INSERT INTO ppe_ptr_items (ptr_id, ppe_id) VALUES (?, ?)");

            foreach ($item_ids as $ppe_id) {
                $item_stmt->bind_param("ii", $ptr_id, $ppe_id);
                $item_stmt->execute();

                // Fetch PPE item details
                $ppe_q = $conn->prepare("SELECT * FROM ppe_property WHERE id = ? LIMIT 1");
                $ppe_q->bind_param("i", $ppe_id);
                $ppe_q->execute();
                $ppe_res = $ppe_q->get_result();
                if ($ppe_row = $ppe_res->fetch_assoc()) {
                    $property_no = $ppe_row['id'];
                    $par_no = $ppe_row['par_no'] ?? '';
                    $item_name = $ppe_row['item_name'] ?? '';
                    $item_description = $ppe_row['item_description'] ?? '';
                    $unit = $ppe_row['unit'] ?? '';
                    $unit_cost = $ppe_row['amount'] ?? 0;
                    $quantity_on_hand = $ppe_row['quantity'] ?? 0;
                    $officer_incharge = $to_officer;
                    $change_direction = 'transfer';
                    $change_type = $transfer_type;
                    $quantity_change = 0;
                    $receipt_qty = 0;
                    $issue_qty = 0;
                    $balance_qty = $quantity_on_hand;

                    $insert = $conn->prepare("INSERT INTO item_history_ppe (property_no, PAR_number, item_name, description, unit, unit_cost, quantity_on_hand, quantity_change, receipt_qty, issue_qty, balance_qty, officer_incharge, change_direction, change_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insert->bind_param(
                        "issssdiiiiisss",
                        $property_no,
                        $par_no,
                        $item_name,
                        $item_description,
                        $unit,
                        $unit_cost,
                        $quantity_on_hand,
                        $quantity_change,
                        $receipt_qty,
                        $issue_qty,
                        $balance_qty,
                        $officer_incharge,
                        $change_direction,
                        $change_type
                    );
                    $insert->execute();
                    $insert->close();
                }
                $ppe_q->close();
            }
            $item_stmt->close();
            
            // Auto-create corresponding PAR (Property Acknowledgement Receipt)
            // Generate PAR number based on PTR number
            $par_no = str_replace('PTR', 'PAR', $ptr_no);
            
            // Property number is stored in individual items, not PAR header
            $property_number = '';
            
            // Insert PAR using PTR data
            $par_stmt = $conn->prepare("INSERT INTO ppe_par (par_no, entity_name, fund_cluster, date_acquired, property_number, received_by, received_by_designation, received_by_date, issued_by, issued_by_designation, issued_by_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $par_stmt->bind_param("sssssssssss", $par_no, $entity_name, $fund_cluster, $transfer_date, $property_number, $received_by, $received_by_designation, $received_by_date, $released_by, $released_by_designation, $released_by_date);
            
            if ($par_stmt->execute()) {
                $par_id = $conn->insert_id;
                
                // Insert PAR items (link same properties to PAR)
                $par_item_stmt = $conn->prepare("INSERT INTO ppe_par_items (par_id, ppe_id) VALUES (?, ?)");
                foreach ($item_ids as $ppe_id) {
                    $par_item_stmt->bind_param("ii", $par_id, $ppe_id);
                    $par_item_stmt->execute();
                }
                $par_item_stmt->close();
            }
            $par_stmt->close();
            
            // Update officer_incharge and custodian for all transferred items
            $update_stmt = $conn->prepare("UPDATE ppe_property SET officer_incharge = ?, custodian = ?, status = 'Transferred' WHERE id = ?");
            foreach ($item_ids as $ppe_id) {
                $update_stmt->bind_param("ssi", $to_officer, $to_officer, $ppe_id);
                $update_stmt->execute();
            }
            $update_stmt->close();
            
            $success = "Property Transfer Report and Property Acknowledgement Receipt created successfully!";
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
<link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
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

    .form-grid .form-group label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 6px;
        color: #374151;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
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
    .radio-group {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin-top: 8px;
    }
    .radio-option {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        padding: 10px 16px;
        border-radius: 8px;
        background: #fff;
        transition: all 0.3s ease;
    }
    .radio-option:hover {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .radio-option input[type="radio"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #3b82f6;
    }
    .radio-option input[type="radio"]:checked + label {
        color: #3b82f6;
        font-weight: 600;
    }
    .radio-option label {
        cursor: pointer;
        margin: 0;
        font-weight: 500;
        color: #333;
    }
    #others_input_container {
        display: none;
        margin-top: 12px;
        animation: slideDown 0.3s ease;
    }
    #others_input_container.show {
        display: block;
    }
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
</head>
<body>
<div class="content">
    <div class="form-container">
        <header class="page-header">
            <h1><i class="fas fa-exchange-alt"></i> Add Property Transfer Report (PTR)</h1>
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
            <div class="section-card">
                <h3><i class="fas fa-info-circle"></i> PTR Details </h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="ptr_no">PTR No <span class="required">*</span></label>
                        <input type="text" id="ptr_no" name="ptr_no" value="<?php echo isset($ptr_no) ? htmlspecialchars($ptr_no) : ''; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="transfer_date">Transfer Date <span class="required">*</span></label>
                        <input type="date" id="transfer_date" name="transfer_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="entity_name">Entity Name <span class="required">*</span></label>
                        <input type="text" id="entity_name" name="entity_name" value="TESDA Regional Office" required>
                    </div>
                    <div class="form-group">
                        <label for="fund_cluster">Fund Cluster</label>
                        <input type="text" id="fund_cluster" name="fund_cluster" value="101">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="from_officer">From Accountable Officer <span class="required">*</span></label>
                        <input type="text" id="from_officer" name="from_officer" required readonly style="background:#f3f4f6;cursor:not-allowed;">
                    </div>
                    <div class="form-group">
                        <label for="to_officer">To Accountable Officer <span class="required">*</span></label>
                        <input type="text" id="to_officer" name="to_officer" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Transfer Type</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="transfer_donation" name="transfer_type" value="Donation" checked>
                            <label for="transfer_donation">Donation</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="transfer_relocation" name="transfer_type" value="Relocation">
                            <label for="transfer_relocation">Relocation</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="transfer_reassignment" name="transfer_type" value="Reassignment">
                            <label for="transfer_reassignment">Reassignment</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="transfer_others" name="transfer_type" value="Others">
                            <label for="transfer_others">Others</label>
                        </div>
                    </div>
                    <div id="others_input_container">
                        <input type="text" id="others_input" name="transfer_type_others" placeholder="Please specify...">
                    </div>
                </div>
                <div class="form-group">
                    <label for="reason">Reason / Purpose</label>
                    <textarea id="reason" name="reason"></textarea>
                </div>
            </div>

            <div class="section-card">
                <h3><i class="fas fa-boxes"></i> Available Items</h3>
                <div class="form-group">
                    <label>Select PPE Items to Transfer <span class="required"></span></label>
                    <div class="items-selection" style="overflow-x:auto;">
                        <table class="table table-bordered" style="width:100%; min-width:700px; border-collapse:collapse; margin-top: 0 !important;">
                            <thead>
                                <tr style="background:#f8f8f8;">
                                    <th style="width:40px;"></th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Current Officer</th>
                                    <th>Amount</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $ppe_items->data_seek(0); while ($item = $ppe_items->fetch_assoc()): ?>
                                <tr>
                                    <td style="text-align:center;">
                                        <input type="checkbox" name="item_ids[]" value="<?= $item['id'] ?>" id="item_<?= $item['id'] ?>" data-officer="<?= htmlspecialchars($item['officer_incharge']) ?>">
                                    </td>
                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td><?= htmlspecialchars($item['item_description']) ?></td>
                                    <td><?= htmlspecialchars($item['officer_incharge']) ?></td>
                                    <td class="currency">₱<?= number_format($item['amount'], 2) ?></td>
                                    <td>
                                        <div style="display:flex; align-items:center; justify-content:center;">
                                            <input type="number" name="quantity[<?= $item['id'] ?>]" min="1" max="<?= htmlspecialchars($item['quantity']) ?>" value="1" style="width:60px; text-align:center; padding:4px !important; border-radius:4px;" <?= ($item['quantity'] <= 0 ? 'disabled' : '') ?>>
                                            <span style="font-size:11px; color:#888; margin-left:4px;">/ <?= htmlspecialchars($item['quantity']) ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> 

            <div class="section-card">
                <h3><i class="fas fa-pen-nib"></i> Signature Details</h3>
                <div class="form-row" style="row-gap: 0px !important;">
                    <div class="form-group" style="display: flex; gap: 16px; align-items: flex-end;">
                        <div>
                            <label for="approved_by">Approved By - Name</label>
                            <input type="text" id="approved_by" name="approved_by" placeholder="Full Name" style="width: 350px;">
                        </div>
                        <div>
                            <label for="approved_by_designation">Approved By - Designation</label>
                            <input type="text" id="approved_by_designation" name="approved_by_designation" placeholder="Position/Designation" style="width: 350px;">
                        </div>
                        <div>
                            <label for="approved_by_date">Approved By - Date</label>
                            <input type="date" id="approved_by_date" name="approved_by_date" value="<?= date('Y-m-d') ?>" style="width: 160px;">
                        </div>
                    </div>
                </div>
                <div class="form-row" style="row-gap: 0px !important;">
                    <div class="form-group" style="display: flex; gap: 16px; align-items: flex-end;">
                        <div>
                            <label for="released_by">Released/Issued By - Name</label>
                            <input type="text" id="released_by" name="released_by" placeholder="Full Name" style="width: 350px;">
                        </div>
                        <div>
                            <label for="released_by_designation">Released/Issued By - Designation</label>
                            <input type="text" id="released_by_designation" name="released_by_designation" placeholder="Position/Designation" style="width: 350px;">
                        </div>
                        <div>
                            <label for="released_by_date">Released/Issued By - Date</label>
                            <input type="date" id="released_by_date" name="released_by_date" value="<?= date('Y-m-d') ?>" style="width: 160px;">
                        </div>
                    </div>
                </div>
                <div class="form-row" style="row-gap: 0px !important;">
                    <div class="form-group" style="display: flex; gap: 16px; align-items: flex-end;">
                        <div>
                            <label for="received_by">Received By - Name</label>
                            <input type="text" id="received_by" name="received_by" placeholder="Full Name" style="width: 350px;">
                        </div>
                        <div>
                            <label for="received_by_designation">Received By - Designation</label>
                            <input type="text" id="received_by_designation" name="received_by_designation" placeholder="Position/Designation" style="width: 350px;">
                        </div>
                        <div>
                            <label for="received_by_date">Received By - Date</label>
                            <input type="date" id="received_by_date" name="received_by_date" value="<?= date('Y-m-d') ?>" style="width: 160px;">
                        </div>
                    </div>
                </div>
            </div>

                <button type="submit" name="submit_ptr" class="pill-btn btn-add">
                    <i class="fas fa-save"></i>SUBMIT PTR
                </button>
                <a href="PPE_PTR.php">
                    <button class="pill-btn pill-view" type="button"><i class="fas fa-ban"></i> Cancel</button>
                </a>
        </form>
    </div>
</div>

<script>
// Toggle "Others" input box visibility
document.addEventListener('DOMContentLoaded', function() {
    const radioButtons = document.querySelectorAll('input[name="transfer_type"]');
    const othersContainer = document.getElementById('others_input_container');
    const othersInput = document.getElementById('others_input');
    const fromOfficerInput = document.getElementById('from_officer');
    const itemCheckboxes = document.querySelectorAll('input[name="item_ids[]"]');

    // Set from_officer to officer_incharge of first checked item
    function updateFromOfficer() {
        let found = false;
        itemCheckboxes.forEach(cb => {
            if (cb.checked && !found) {
                fromOfficerInput.value = cb.getAttribute('data-officer') || '';
                found = true;
            }
        });
        if (!found) fromOfficerInput.value = '';
    }

    itemCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateFromOfficer);
    });

    // On page load, set if any are checked
    updateFromOfficer();

    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'Others' && this.checked) {
                othersContainer.classList.add('show');
                othersInput.focus();
            } else {
                othersContainer.classList.remove('show');
                othersInput.value = '';
            }
        });
    });
});
</script>
</body>
</html>
