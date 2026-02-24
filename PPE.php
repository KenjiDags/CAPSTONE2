<?php
require 'auth.php';
require 'config.php';

// Handle Delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    if ($delete_id > 0) {
        $stmt = $conn->prepare("DELETE FROM ppe_property WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();
    }
    if (ob_get_level() > 0) { ob_end_clean(); }
    header('Location: PPE.php?deleted=1');
    exit();
}

// Search functionality
$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM ppe_property";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " WHERE par_no LIKE ? OR item_name LIKE ? OR item_description LIKE ? OR custodian LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
    $types = "ssss";
}

$sql .= " ORDER BY id DESC"; // no date_acquired, using id as newest first

require_once 'functions.php';
try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    if (!empty($params)) $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // If redirected from add_ppe.php with ?added=1, log initial add to history for the last inserted item
    if (isset($_GET['added']) && $_GET['added'] == 1 && !empty($items)) {
        $latest = $items[0];
        // Check if already logged to avoid duplicate
        $check = $conn->prepare("SELECT COUNT(*) as cnt FROM item_history_ppe WHERE property_no = ? AND change_type = 'add'");
        $check->bind_param("i", $latest['id']);
        $check->execute();
        $res = $check->get_result()->fetch_assoc();
        $check->close();
        if ($res['cnt'] == 0) {
            // Insert to item_history_ppe using correct fields
            $insert = $conn->prepare("
                INSERT INTO item_history_ppe 
                (property_no, PAR_number, refference_no, item_name, description, unit, 
                unit_cost, quantity_on_hand, quantity_change, receipt_qty, issue_qty, 
                balance_qty, officer_incharge, change_direction, change_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $change_direction = 'increase';
            $change_type = 'add';
            $receipt_qty = $latest['quantity'];
            $balance_qty = $latest['quantity'];
            $quantity_change = $latest['quantity'];
            $refference_no = null; // no reference on initial add

            $insert->bind_param(
                "isssssdi iiisss",
                $latest['id'],           // property_no
                $latest['par_no'],       // PAR_number
                $refference_no,          // refference_no
                $latest['item_name'],    // item_name
                $latest['item_description'], // description
                $latest['unit'],         // unit
                $latest['amount'],       // unit_cost
                $latest['quantity'],     // quantity_on_hand
                $quantity_change,        // quantity_change
                $receipt_qty,            // receipt_qty
                $issue_qty,              // issue_qty
                $balance_qty,            // balance_qty
                $latest['custodian'],    // officer_incharge
                $change_direction,       // change_direction
                $change_type             // change_type
            );
        }
    }
} catch (Exception $e) {
    $items = [];
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PPE Inventory List</title>
<link rel="stylesheet" href="/tesda/css/styles.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="/tesda/css/PPE.css?v=<?php echo time(); ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f1b3";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }
        
        /* Search Section Layout */
        .add-btn-section {
            flex: 0 0 auto;
        }
        
        .search-section {
            flex: 1;
            display: flex;
            justify-content: center;
        }
        
        .search-form { 
            display: flex; 
            gap: 10px;
            align-items: center;
        }
        
        .search-input { 
            padding: 12px 16px; 
            border-radius: 8px; 
            border: 2px solid #e5e7eb; 
            width: 400px; 
            max-width: 100%;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .search-input:hover {
            border-color: #cbd5e1;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Status and Condition Badges */
        table tbody td:nth-child(7),
        table tbody td:nth-child(8) {
            font-size: 13px;
            font-weight: 600;
        }
        
        /* Status column colors */
        table tbody tr:has(td:nth-child(7):contains("Active")) td:nth-child(7),
        table tbody td:nth-child(7) {
            color: #047857;
        }
        
        /* Condition column colors */
        table tbody td:nth-child(8) {
            color: #0891b2;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="container">
    <h2>PPE Inventory List</h2>

    <!-- Search and Add (Semi-Expendable style) -->
    <div class="search-add-container" style="display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
        <div class="control" style="flex: 1;">
            <form method="GET" class="search-form" style="display: flex; align-items: center; gap: 10px;">
                <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001F80;">
                    <i class="fas fa-search"></i> Search:
                </label>
                <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by PAR no, name, description, or officer" class="search-input">
                <?php if (!empty($search)): ?>
                    <a href="PPE.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="add-btn-section" style="margin-left: auto;">
            <a href="add_ppe.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New Item</a>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Item deleted successfully.</div>
    <?php elseif (isset($_GET['added'])): ?>
        <div class="alert alert-success">Item added successfully.</div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th><i class="fas fa-hashtag"></i> PAR No</th>
                <th><i class="fas fa-box"></i> Item Name</th>
                <th><i class="fas fa-align-left"></i> Description</th>
                <th><i class="fas fa-list-ol"></i> Quantity</th>
                <th><i class="fas fa-ruler"></i> Unit</th>
                <th><i class="fas fa-user-tie"></i> Officer in charge</th>
                <th><i class="fas fa-info-circle"></i> Status</th>
                <th><i class="fas fa-check-circle"></i> Condition</th>
                <th><i class="fas fa-dollar-sign"></i> Amount</th>
                <th><i class="fas fa-cogs"></i> Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="10">
                    <div style="font-weight: 600; margin-bottom: 8px;">No PPE Items Found</div>
                    <div style="font-size: 14px;">Start by adding your first property, plant, and equipment item</div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['par_no']); ?></td>
                    <td><?= htmlspecialchars($item['item_name']); ?></td>
                    <td title="<?= htmlspecialchars($item['item_description']); ?>"><?= htmlspecialchars(strlen($item['item_description'])>50?substr($item['item_description'],0,50).'...':$item['item_description']); ?></td>
                    <td><?= number_format($item['quantity']); ?></td>
                    <td><?= htmlspecialchars($item['unit']); ?></td>
                    <td><?= htmlspecialchars($item['custodian']); ?></td>
                    <td><?= htmlspecialchars($item['status']); ?></td>
                    <td><?= htmlspecialchars($item['condition']); ?></td>
                    <td>₱<?= number_format($item['amount'],2); ?></td>
                    <td class="actions-cell">
                        <div class="action-buttons">
                            <button class="pill-btn pill-view" onclick="viewItem(<?= $item['id']; ?>)"><i class="fas fa-eye"></i> View</button>
                            <a href="edit_ppe.php?id=<?= $item['id']; ?>" class="pill-btn pill-edit"><i class="fas fa-edit"></i> Edit</a>
                            <form method="POST" onsubmit="return confirm('Delete this item permanently?');" style="display: inline;">
                                <input type="hidden" name="delete_id" value="<?= $item['id']; ?>">
                                <button type="submit" class="pill-btn pill-delete"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>



<!-- View Item Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Item Details</h3>
            <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
        </div>
        <div id="itemDetails"></div>
    </div>
</div>

<script>
function viewItem(id){
    const modal = document.getElementById('viewModal');
    const details = document.getElementById('itemDetails');
    
    // Show loading state
    details.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #3b82f6;"></i><div style="margin-top: 12px; color: #64748b;">Loading...</div></div>';
    modal.style.display='flex';
    
    fetch(`get_ppe_details.php?id=${id}`)
    .then(res=>res.json())
    .then(data=>{
        if(data.success){
            const item = data.item;
            details.innerHTML = `
                <div class="detail-row"><strong>PAR No:</strong> ${item.par_no}</div>
                <div class="detail-row"><strong>Item Name:</strong> ${item.item_name}</div>
                <div class="detail-row"><strong>Description:</strong> ${item.item_description}</div>
                <div class="detail-row"><strong>Quantity:</strong> ${item.quantity}</div>
                <div class="detail-row"><strong>Unit:</strong> ${item.unit}</div>
                <div class="detail-row"><strong>Officer Incharge:</strong> ${item.officer_incharge}</div>
                <div class="detail-row"><strong>Custodian:</strong> ${item.custodian}</div>
                <div class="detail-row"><strong>Status:</strong> ${item.status}</div>
                <div class="detail-row"><strong>Condition:</strong> ${item.condition}</div>
                <div class="detail-row"><strong>Amount:</strong> ₱${parseFloat(item.amount).toLocaleString('en-US',{minimumFractionDigits:2})}</div>
                ${item.remarks?`<div class="detail-row"><strong>Remarks:</strong> ${item.remarks}</div>`:''}
            `;
        } else {
            details.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;"><i class="fas fa-exclamation-circle" style="font-size: 32px;"></i><div style="margin-top: 12px;">Error loading item details</div></div>';
        }
    })
    .catch(error => {
        details.innerHTML = '<div style="text-align: center; padding: 40px; color: #ef4444;"><i class="fas fa-exclamation-circle" style="font-size: 32px;"></i><div style="margin-top: 12px;">Error loading item details</div></div>';
    });
}

function closeModal(id){document.getElementById(id).style.display='none';}
window.onclick=function(event){if(event.target.classList.contains('modal')) closeModal(event.target.id);}
</script>
</body>
</html>
