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
$sort_by = $_GET['sort'] ?? 'date_newest';
$sql = "SELECT * FROM ppe_property";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " WHERE par_no LIKE ? OR item_name LIKE ? OR item_description LIKE ? OR custodian LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
    $types = "ssss";
}

// Sorting logic
switch ($sort_by) {
    case 'date_newest':
        $sql .= " ORDER BY id DESC";
        break;
    case 'date_oldest':
        $sql .= " ORDER BY id ASC";
        break;
    case 'property_no':
        $sql .= " ORDER BY par_no ASC";
        break;
    case 'amount_highest':
        $sql .= " ORDER BY amount DESC";
        break;
    case 'amount_lowest':
        $sql .= " ORDER BY amount ASC";
        break;
    default:
        $sql .= " ORDER BY id DESC";
}

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
<link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="css/PPE.css?v=<?php echo time(); ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .container h2::before {
            content: "\f1b3";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }

        /* Status and Condition Badges */
        table tbody td:nth-child(7),
        table tbody td:nth-child(8) {
            font-size: 13px;
            font-weight: 600;
        }
        
        /* Condition column colors */
        table tbody td:nth-child(8) {
            color: #0891b2;
        }

        .clickable-row {
            cursor: pointer;
        }

        .clickable-row:hover {
            background: #f8fafc;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="container">
    <h2>PPE Inventory List</h2>


    <!-- Search, Sort, and Add -->
    <form method="get" class="filters" style="display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; flex: 1;">
            <div class="control">
                <label for="sort-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001F80;">
                    <i class="fas fa-sort"></i> Sort by:
                </label>
                <select id="sort-select" name="sort" onchange="this.form.submit()">
                    <option value="date_newest" <?= ($sort_by == 'date_newest') ? 'selected' : '' ?>>Date (Newest First)</option>
                    <option value="date_oldest" <?= ($sort_by == 'date_oldest') ? 'selected' : '' ?>>Date (Oldest First)</option>
                    <option value="property_no" <?= ($sort_by == 'property_no') ? 'selected' : '' ?>>Property No. (A-Z)</option>
                    <option value="amount_highest" <?= ($sort_by == 'amount_highest') ? 'selected' : '' ?>>Total Amount (Highest)</option>
                    <option value="amount_lowest" <?= ($sort_by == 'amount_lowest') ? 'selected' : '' ?>>Total Amount (Lowest)</option>
                </select>
            </div>

            <div class="control">
                <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001F80;">
                    <i class="fas fa-search"></i> Search:
                </label>
                <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by PAR no, name, description, or officer">
                <?php if (!empty($search)): ?>
                    <a href="PPE.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </div>
            <div class="add-btn-section" style="margin-left: auto;">
                <a href="add_ppe.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New Item</a>
            </div>
        </div>
    </form>


    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Item deleted successfully.</div>
    <?php elseif (isset($_GET['added'])): ?>
        <div class="alert alert-success">Item added successfully.</div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th><i class="fas fa-hashtag"></i> PPE No.</th>
                <th><i class="fas fa-box"></i> Item Name</th>
                <th><i class="fas fa-align-left"></i> Description</th>
                <th><i class="fas fa-list-ol"></i> Quantity</th>
                <th><i class="fas fa-ruler"></i> Unit</th>
                <th><i class="fas fa-calendar"></i> Date Acquired</th>
                <th><i class="fas fa-user-tie"></i> Officer in charge</th>
                <th><i class="fas fa-info-circle"></i> Status</th>
                <th><i class="fas fa-check-circle"></i> Condition</th>
                <th><i class="fas fa-dollar-sign"></i> Amount</th>
                <th><i class="fas fa-cogs"></i> Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="11">
                    <div style="font-weight: 600; margin-bottom: 8px;">No PPE Items Found</div>
                    <div style="font-size: 14px;">Start by adding your first property, plant, and equipment item</div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr class="clickable-row" data-view-url="view_ppe_items.php?id=<?= (int)$item['id']; ?>">
                    <td><?= htmlspecialchars($item['PPE_no']); ?></td> 
                    <td><?= htmlspecialchars($item['item_name']); ?></td>
                    <td title="<?= htmlspecialchars($item['item_description']); ?>"><?= htmlspecialchars(strlen($item['item_description'])>50?substr($item['item_description'],0,50).'...':$item['item_description']); ?></td>
                    <td><?= number_format($item['quantity']); ?></td>
                    <td><?= htmlspecialchars($item['unit']); ?></td>
                    <td><?= htmlspecialchars($item['date_acquired'] ?? 'N/A'); ?></td>
                    <td><?= htmlspecialchars($item['custodian']); ?></td>
                    <td><?= htmlspecialchars($item['status']); ?></td>
                    <td><?= htmlspecialchars($item['condition']); ?></td>
                    <td class="currency">₱<?= number_format($item['amount'],2); ?></td>
                    <td class="actions-cell">
                        <div class="action-buttons">
                            <a href="edit_ppe.php?id=<?= $item['id']; ?>" class="pill-btn pill-edit" style="height: 30px;"><i class="fas fa-edit"></i> Edit</a>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('tr.clickable-row[data-view-url]');

    rows.forEach(function(row) {
        row.addEventListener('click', function(event) {
            if (event.target.closest('a, button, input, select, textarea, form, .actions-cell')) {
                return;
            }

            const viewUrl = row.getAttribute('data-view-url');
            if (viewUrl) {
                window.location.href = viewUrl;
            }
        });
    });
});
</script>
</body>
</html>
