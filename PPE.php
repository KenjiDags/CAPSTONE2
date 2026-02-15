<?php
require 'auth.php';
require 'config.php';
include 'sidebar.php';

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

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    if (!empty($params)) $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);

    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
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
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
            .search-add-container { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                margin-bottom: 20px; 
                gap: 20px; 
            }
            .search-form { 
                display: flex; 
                gap: 10px; 
            }
            .search-input { 
                padding: 10px; 
                border-radius: 4px; 
                border: 1px solid #ddd; 
                width: 400px; 
                max-width: 100%; 
            }
            .btn { 
                padding: 8px 16px; 
                border-radius: 4px; 
                border: none; 
                cursor: pointer; 
                text-decoration: none; 
                display: inline-flex; 
                align-items: center; 
                gap: 6px; 
                font-size: 14px; 
            }
            .btn-primary { 
                background: #2563eb; 
                color: #fff; 
            } 
            .btn-primary:hover { 
                background: #1d4ed8; 
            }
            .btn-success { 
                background: #10b981; 
                color: #fff; 
            } 
            .btn-success:hover { 
                background: #059669; 
            }
            .btn-secondary { 
                background: #6b7280; 
                color: #fff; 
            } 
            .btn-secondary:hover { 
                background: #4b5563; 
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 40px; 
            }
            table th, table td { 
                padding: 10px; 
                border: 1px solid #ddd; 
                text-align: left; }
            .actions-cell { white-space: nowrap; 
            }
            .pill-btn { 
                display: inline-flex; 
                align-items: center; 
                justify-content: center; 
                gap: 6px; padding: 6px 12px; 
                border-radius: 9999px; 
                color: #fff; 
                font-weight: 600; 
                font-size: 13px; 
                border: none; 
                cursor: pointer; 
            }
            .pill-edit { 
                background: linear-gradient(135deg,#67a8ff 0%,#3b82f6 100%); 
            }
            .pill-delete { 
                background: linear-gradient(135deg,#ff9aa2 0%,#ef4444 100%); 
            }
            .pill-view { 
                background: linear-gradient(135deg,#facc15 0%,#f59e0b 100%); 
            }
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                align-items: center;
                justify-content: center;
            }
            .modal-content {
                background: white;
                padding: 20px;
                border-radius: 8px;
                width: 90%;
                max-width: 600px;
                max-height: 90vh;
                overflow-y: auto;
            }
            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            .modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
            }
            .detail-row { 
                margin-bottom: 10px; 
                padding: 8px 0; 
                border-bottom: 1px solid #f3f4f6; 
                }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h2>PPE Inventory List</h2>
    </header>

    <!-- Search and Add -->
    <div class="search-add-container">
        <form method="GET" class="search-form">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by PAR no, name, description, or officer" class="search-input">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            <?php if (!empty($search)): ?>
                <a href="PPE.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
        <a href="add_ppe.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New Item</a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Item deleted successfully.</div>
    <?php elseif (isset($_GET['added'])): ?>
        <div class="alert alert-success">Item added successfully.</div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>PAR No</th>
                <th>Item Name</th>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Officer in charge</th>
                <th>Status</th>
                <th>Condition</th>
                <th>Amount</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="10" style="text-align:center; padding: 40px;">No items found. Add your first item!</td></tr>
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
                            <form method="POST" onsubmit="return confirm('Delete this item permanently?');">
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
    fetch(`get_ppe_details.php?id=${id}`)
    .then(res=>res.json())
    .then(data=>{
        if(data.success){
            const item = data.item;
            document.getElementById('itemDetails').innerHTML = `
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
            document.getElementById('viewModal').style.display='flex';
        } else alert('Error loading item details');
    });
}

function closeModal(id){document.getElementById(id).style.display='none';}
window.onclick=function(event){if(event.target.classList.contains('modal')) closeModal(event.target.id);}
</script>
</body>
</html>
