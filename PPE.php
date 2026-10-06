<?php
require 'auth.php';
require 'config.php';

// Handle Delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    if ($delete_id > 0) {
    require_once 'archive_helpers.php';
    archiveRecord($conn, 'PPE', $delete_id);
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
    $sql .= " WHERE PPE_no LIKE ? OR property_no LIKE ? OR item_name LIKE ? OR item_description LIKE ? OR custodian LIKE ?";
    $search_param = "%$search%";
    $params = array_fill(0, 5, $search_param);
    $types = "sssss";
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
        $sql .= " ORDER BY property_no ASC";
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
                <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by PPE or property no., name, description, or officer">
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
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="table-container">    
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
                    <tr>
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
                            <div class="actions-menu">
                                <button type="button"
                                        class="actions-menu-toggle"
                                        aria-label="Open actions"
                                        aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>

                                <div class="actions-menu-list">

                                    <a href="view_ppe_items.php?id=<?= (int)$item['id']; ?>" class="view-action">
                                        <i class="fas fa-eye"></i> View
                                    </a>

                                    <a href="edit_ppe.php?id=<?= (int)$item['id']; ?>" class="edit-action">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>

                                    <a href="#"
                                    class="delete-action"
                                    onclick="event.preventDefault(); document.getElementById('delete-form-<?= (int)$item['id']; ?>').requestSubmit();">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>

                                </div>
                            </div>

                            <form id="delete-form-<?= (int)$item['id']; ?>"
                                method="POST"
                                style="display: none;"
                                onsubmit="return confirm('Move this item to Archive?');">
                                <input type="hidden" name="delete_id" value="<?= (int)$item['id']; ?>">
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="js/actions_menu.js"></script>

</body>
</html>
