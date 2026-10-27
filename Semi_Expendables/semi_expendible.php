<?php
require_once __DIR__ . '/bootstrap.php';
$viewReturnFilters = [];
foreach (['category', 'search', 'sort'] as $filter) {
    if (isset($_GET[$filter]) && is_string($_GET[$filter])) $viewReturnFilters[$filter] = $_GET[$filter];
}
$viewReturnUrl = 'Semi_Expendables/semi_expendible.php' . ($viewReturnFilters ? '?' . http_build_query($viewReturnFilters) : '');
ob_start();
require dirname(__DIR__) . '/auth.php';
include dirname(__DIR__) . '/config.php'; 

// Handle delete action (POST or GET)
if ((
        ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) ||
        (isset($_GET['delete_id']))
    )) {
    $delete_id = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : (int)$_GET['delete_id'];
    if ($delete_id > 0) {
    require_once dirname(__DIR__) . '/archive_helpers.php';
    archiveRecord($conn, 'Semi-expendable', $delete_id);
    }
    $redirectCategory = isset($_POST['category']) ? $_POST['category'] : (isset($_GET['category']) ? $_GET['category'] : '');
    if (ob_get_level() > 0) { ob_end_clean(); }
    header('Location: semi_expendible.php' . ($redirectCategory ? ('?category=' . urlencode($redirectCategory) . '&deleted=1') : ''));
    exit();
}

// Get category from URL parameter, default to 'Other PPE'
$category = isset($_GET['category']) ? $_GET['category'] : 'Other PPE';

// Valid categories based on the data
$valid_categories = ['Other PPE', 'Office Equipment', 'ICT Equipment', 'Communication Equipment', 'Furniture and Fixtures'];

if (!in_array($category, $valid_categories)) {
    $category = 'Other PPE';
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build SQL query
$sql = "SELECT * FROM semi_expendable_property WHERE category = ? AND (NOT EXISTS (SELECT 1 FROM iirusp_items disposed_item WHERE disposed_item.semi_expendable_property_no COLLATE utf8mb4_general_ci = semi_expendable_property.semi_expendable_property_no COLLATE utf8mb4_general_ci) OR (COALESCE(semi_expendable_property.quantity, 0) > 0 AND (COALESCE(semi_expendable_property.quantity_balance, 0) > 0 OR COALESCE(semi_expendable_property.quantity_issued, 0) > 0 OR COALESCE(semi_expendable_property.quantity_reissued, 0) > 0)))";
$types = "s";
$params = [$category];

if (!empty($search)) {
    $sql .= " AND (item_description LIKE ? OR semi_expendable_property_no LIKE ? OR office_officer_issued LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types = "ssss";
}

$sql .= " ORDER BY date DESC";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters dynamically
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    $items = [];
    $error = "Database error: " . $e->getMessage();
}

// Calculate totals
$total_items = count($items);
$total_value = array_sum(array_column($items, 'amount_total'));
$total_quantity = array_sum(array_column($items, 'quantity_balance'));
?>
<!DOCTYPE html>
<html data-ppe-styles lang="en">
<head>
    <base href="<?= htmlspecialchars(APP_ROOT_URL, ENT_QUOTES, 'UTF-8') ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category); ?> - Semi-Expendable Property</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f1b2";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }

        /* Category Tabs */
        .category-tabs {
            display: flex !important;
            gap: 10px !important;
            margin-bottom: 20px !important;
            border-bottom: 2px solid #e5e7eb !important;
            flex-wrap: wrap !important;
        }
        .category-tab {
            padding: 12px 20px !important;
            text-decoration: none !important;
            color: #6b7280 !important;
            border-bottom: 3px solid transparent !important;
            transition: all 0.3s !important;
            font-weight: 500 !important;
            font-size: 14px !important;
            margin-bottom: -2px !important;
        }
        .category-tab:hover {
            color: #3b82f6 !important;
            border-bottom-color: #93c5fd !important;
        }
        .category-tab.active {
            color: #3b82f6 !important;
            border-bottom-color: #3b82f6 !important;
            font-weight: 600 !important;
        }

        /* Statistics Cards */
        .stats-cards {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important;
            gap: 15px !important;
            margin-bottom: 20px !important;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            padding: 20px !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1) !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease !important;
        }
        .stat-card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 12px rgba(0,0,0,0.15) !important;
        }
        .stat-card:nth-child(1) {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%) !important;
        }
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important;
        }
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%) !important;
        }
        .stat-card h3 {
            margin: 0 0 10px 0 !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            opacity: 0.9 !important;
        }
        .stat-number {
            font-size: 28px !important;
            font-weight: 700 !important;
            margin: 0 !important;
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

        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        /* Modal */
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
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 15px;
        }
        .modal-header h3 {
            margin: 0;
            color: #1e3a8a;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #6b7280;
            transition: color 0.2s;
        }
        .modal-close:hover {
            color: #ef4444;
        }
        .detail-row {
            margin-bottom: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-row strong {
            color: #374151;
            font-weight: 600;
        }

        .actions-menu {
            position: relative;
            display: inline-block;
        }

        .actions-menu-toggle {
            width: 30px;
            height: 30px;
            padding: 0;
            border: 0;
            border-radius: 5px;
            background: #fff;
            color: #6b7280;
            cursor: pointer;
        }

        .actions-menu-toggle:hover,
        .actions-menu.is-open .actions-menu-toggle {
            color: #2563eb;
        }

        .actions-menu-list {
            display: none;
            position: fixed;
            z-index: 10000;
            min-width: 110px;
            padding: 4px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fff;
            box-shadow: 0 6px 16px rgba(15, 23, 42, .12);
        }

        .actions-menu.is-open .actions-menu-list {
            display: block;
        }

        .actions-menu-list a {
            display: flex;
            width: 100%;
            align-items: center;
            gap: 8px;
            padding: 7px 8px;
            border-radius: 4px;
            color: #374151;
            font-size: 12px;
            text-align: left;
            text-decoration: none !important;
            border: none !important;
            box-shadow: none !important;
            transform: none !important;
        }

        .actions-menu-list a:hover,
        .actions-menu-list a:focus,
        .actions-menu-list a:active {
            background: #f3f4f6;
            text-decoration: none !important;
            border: none !important;
            box-shadow: none !important;
            transform: none !important;
        }

        .actions-menu-list .delete-action {
            color: #dc2626;
        }

        body.dark-mode .actions-menu-toggle,
        body.dark-mode .actions-menu-list {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }

        body.dark-mode .actions-menu-list a {
            color: #e2e8f0;
        }

        body.dark-mode .actions-menu-list a:hover {
            background: #334155;
        }

        .table-container tbody tr,
        .table-container tbody td,

    </style>
</head>
<body>
<?php include dirname(__DIR__) . '/sidebar.php'; ?>
<div class="container">
    <h2>Semi-Expendable Property — <?php echo htmlspecialchars($category); ?></h2>

        <!-- Category Tabs -->
        <div class="category-tabs">
            <?php foreach ($valid_categories as $cat): ?>
                <a href="Semi_Expendables/semi_expendible.php?category=<?php echo urlencode($cat); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                   class="category-tab <?php echo $cat === $category ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Sort, Search and Add Container -->
        <form method="GET" action="Semi_Expendables/semi_expendible.php" class="filters">
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">

            <div class="inventory-controls">

                <!-- Sort By -->
                <div class="control-sort">
                    <label for="sortBy">Sort By:</label>
                    <select id="sortBy" name="sort" onchange="this.form.submit()">
                        <option value="date_desc" <?php echo (($_GET['sort'] ?? 'date_desc') === 'date_desc') ? 'selected' : ''; ?>>
                            Newest
                        </option>
                        <option value="date_asc" <?php echo (($_GET['sort'] ?? '') === 'date_asc') ? 'selected' : ''; ?>>
                            Oldest
                        </option>
                        <option value="quantity_low" <?php echo (($_GET['sort'] ?? '') === 'quantity_low') ? 'selected' : ''; ?>>
                            Quantity: Low to High
                        </option>
                        <option value="quantity_high" <?php echo (($_GET['sort'] ?? '') === 'quantity_high') ? 'selected' : ''; ?>>
                            Quantity: High to Low
                        </option>
                        <option value="amount_low" <?php echo (($_GET['sort'] ?? '') === 'amount_low') ? 'selected' : ''; ?>>
                            Amount: Low to High
                        </option>
                        <option value="amount_high" <?php echo (($_GET['sort'] ?? '') === 'amount_high') ? 'selected' : ''; ?>>
                            Amount: High to Low
                        </option>
                    </select>
                </div>

                <!-- Search -->
                <div class="control-search">
                    <label for="searchInput">
                        <i class="fas fa-search"></i> Search:
                    </label>

                    <input
                        type="text"
                        id="searchInput"
                        name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search by description, property number, or officer..."
                    >

                    <?php if (!empty($search)): ?>
                        <a href="Semi_Expendables/semi_expendible.php?category=<?php echo urlencode($category); ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Add Button -->
                <div class="control-actions">
                    <a href="Semi_Expendables/add_semi_expendable.php?category=<?php echo urlencode($category); ?>" class="btn btn-add">
                        <i class="fas fa-plus"></i> Add New Item
                    </a>
                </div>

            </div>
        </form>

    <!-- Statistics Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <h3>Total Items</h3>
            <p class="stat-number"><?php echo number_format($total_items); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Quantity</h3>
            <p class="stat-number"><?php echo number_format($total_quantity); ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Value</h3>
            <p class="stat-number">₱<?php echo number_format($total_value, 2); ?></p>
        </div>
        <div class="stat-card">
            <h3>Fund Cluster</h3>
            <p class="stat-number">101</p>
        </div>
    </div>

        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
            <div class="alert alert-success">Item deleted successfully.</div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Items Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>

                        <th>Property No.</th>
                        <th>Item Description</th>
                        <th>Quantity</th>
                        <th>Office/Officer</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                <?php if (!empty($search)): ?>
                                    <p>No items found matching your search criteria.</p>
                                    <a href="Semi_Expendables/add_semi_expendable.php?category=<?php echo urlencode($category); ?>" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Add First Item
                                    </a>
                                <?php else: ?>
                                    <p>No items found in this category.</p>
                                    <a href="Semi_Expendables/add_semi_expendable.php?category=<?php echo urlencode($category); ?>" class="btn btn-success" style="height: 70px;">
                                        <i class="fas fa-plus" style="margin-bottom: 0 !important;"></i> Add First Item
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($item['date'])); ?></td>

                                <td><?php echo htmlspecialchars($item['semi_expendable_property_no']); ?></td>
                                <td title="<?php echo htmlspecialchars($item['item_description']); ?>">
                                    <?php echo htmlspecialchars(strlen($item['item_description']) > 50 ? 
                                        substr($item['item_description'], 0, 50) . '...' : 
                                        $item['item_description']); ?>
                                </td>
                                <td><?php echo number_format(isset($item['quantity']) ? $item['quantity'] : 0); ?></td>
                                <?php 
                                    // Determine label and value dynamically per row
                                    $list_label = 'Office/Officer Issued';
                                    $list_officer = '';
                                    if (!empty($item['office_officer_returned']) || ((int)($item['quantity_returned'] ?? 0)) > 0) {
                                        $list_label = 'Office/Officer Returned';
                                        $list_officer = $item['office_officer_returned'] ?? '';
                                    } elseif (!empty($item['office_officer_reissued']) || ((int)($item['quantity_reissued'] ?? 0)) > 0) {
                                        $list_label = 'Office/Officer Re-issued';
                                        $list_officer = $item['office_officer_reissued'] ?? '';
                                    } else {
                                        $list_label = 'Office/Officer Issued';
                                        $list_officer = $item['office_officer_issued'] ?? '';
                                    }
                                ?>
                                <td title="<?php echo htmlspecialchars($list_label); ?>"><?php echo htmlspecialchars($list_officer); ?></td>
                                <?php
                                    if ((int)($item['quantity_disposed'] ?? 0) > 0) {
                                        $item_status = 'Disposed';
                                    } elseif ((int)($item['quantity_returned'] ?? 0) > 0) {
                                        $item_status = 'Returned';
                                    } elseif ((int)($item['quantity_reissued'] ?? 0) > 0) {
                                        $item_status = 'Re-issued';
                                    } elseif ((int)($item['quantity_issued'] ?? 0) > 0) {
                                        $item_status = 'Issued';
                                    } else {
                                        $item_status = 'Available';
                                    }
                                ?>
                                <td><span class="inventory-status" data-status="<?php echo htmlspecialchars(strtolower($item_status), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item_status); ?></span></td>
                                <td class="currency">₱<?php echo number_format($item['amount_total'], 2); ?></td>
                                <td class="actions-cell">
                                    <div class="actions-menu">
                                        <button type="button" class="actions-menu-toggle" aria-label="Open actions" aria-expanded="false"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="actions-menu-list">
                                            <a href="Semi_Expendables/view_item_semi.php?id=<?= (int)$item['id'] ?>&amp;return=<?= urlencode($viewReturnUrl) ?>"><i class="fas fa-eye"></i> View</a>
                                            <a href="Semi_Expendables/edit_semi_expendable.php?id=<?php echo $item['id']; ?>"><i class="fas fa-pen"></i> Edit</a>
                                            <a href="javascript:void(0)" class="delete-action" onclick="deleteItem(<?php echo (int)$item['id']; ?>, '<?php echo htmlspecialchars($category, ENT_QUOTES); ?>')"><i class="fas fa-trash"></i> Delete</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function deleteItem(id, category) {
            if (confirm('Move this item to Archive?')) {
                // Redirect with delete_id and category as GET params
                let url = `Semi_Expendables/semi_expendible.php?delete_id=${encodeURIComponent(id)}&category=${encodeURIComponent(category)}`;
                window.location.href = url;
            }
        }
    </script>

    <script src="js/actions_menu.js"></script>

</body>
</html>
