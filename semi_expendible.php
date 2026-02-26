<?php
ob_start();
require 'auth.php';
include 'config.php'; 
include 'sidebar.php';

// Handle delete action (POST or GET)
if ((
        ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) ||
        (isset($_GET['delete_id']))
    )) {
    $delete_id = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : (int)$_GET['delete_id'];
    if ($delete_id > 0) {
        if ($stmt = $conn->prepare("DELETE FROM semi_expendable_property WHERE id = ?")) {
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $stmt->close();
        }
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
$sql = "SELECT * FROM semi_expendable_property WHERE category = ?";
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category); ?> - Semi-Expendable Property</title>
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?php echo time(); ?>">
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
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="container">
    <h2>Semi-Expendable Property — <?php echo htmlspecialchars($category); ?></h2>

        <!-- Category Tabs -->
        <div class="category-tabs">
            <?php foreach ($valid_categories as $cat): ?>
                <a href="?category=<?php echo urlencode($cat); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="category-tab <?php echo $cat === $category ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Search and Add Container -->
        <div class="filters" style="display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
            <div class="control" style="flex: 1;">
                <form method="GET" class="search-form" style="display: flex; align-items: center; gap: 10px;">
                    <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001F80;">
                        <i class="fas fa-search"></i> Search:
                    </label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by description, property number, or officer..." 
                           class="search-input">
                    <?php if (!empty($search)): ?>
                        <a href="?category=<?php echo urlencode($category); ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="add-btn-section" style="margin-left: auto;">
                <a href="add_semi_expendable.php?category=<?php echo urlencode($category); ?>" class="btn btn-add">
                    <i class="fas fa-plus"></i> Add New Item
                </a>
            </div>
        </div>

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
                        <th>ICS/RRSP No.</th>
                        <th>Property No.</th>
                        <th>Item Description</th>
                        <th>Quantity</th>
                        <th>Office/Officer</th>
                        <th>Balance</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px;">
                                <?php if (!empty($search)): ?>
                                    <p>No items found matching your search criteria.</p>
                                    <a href="add_semi_expendable.php?category=<?php echo urlencode($category); ?>" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Add First Item
                                    </a>
                                <?php else: ?>
                                    <p>No items found in this category.</p>
                                    <a href="add_semi_expendable.php?category=<?php echo urlencode($category); ?>" class="btn btn-success" style="height: 70px;">
                                        <i class="fas fa-plus" style="margin-bottom: 0 !important;"></i> Add First Item
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($item['date'])); ?></td>
                                <td><?php echo htmlspecialchars($item['ics_rrsp_no']); ?></td>
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
                                <td><?php echo number_format($item['quantity_balance']); ?></td>
                                <td class="currency">₱<?php echo number_format($item['amount_total'], 2); ?></td>
                                <td class="actions-cell">
                                    <div class="action-row">
                                        <a href="edit_semi_expendable.php?id=<?php echo $item['id']; ?>" class="btn edit-btn" title="Edit" aria-label="Edit" style="height: 30px;">
                                            <i class="fas fa-pen"></i> Edit
                                        </a>
                                        <button type="button" class="btn delete-btn" title="Delete" aria-label="Delete"
                                            onclick="deleteItem(<?php echo (int)$item['id']; ?>, '<?php echo htmlspecialchars($category, ENT_QUOTES); ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- View Item Modal -->
    <div id="viewModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Item Details</h3>
                <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-body" id="itemDetails">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function deleteItem(id, category) {
            if (confirm('Delete this item permanently?')) {
                // Redirect with delete_id and category as GET params
                let url = `semi_expendible.php?delete_id=${encodeURIComponent(id)}&category=${encodeURIComponent(category)}`;
                window.location.href = url;
            }
        }
    function viewItem(id) {
        // Fetch item details via AJAX
        fetch(`get_item_details.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('itemDetails').innerHTML = `
                        <div class="detail-grid">
                            <div class="detail-row">
                                <strong>Date:</strong> ${data.item.date}
                            </div>
                            <div class="detail-row">
                                <strong>ICS/RRSP No:</strong> ${data.item.ics_rrsp_no}
                            </div>
                            <div class="detail-row">
                                <strong>Property No:</strong> ${data.item.semi_expendable_property_no}
                            </div>
                            <div class="detail-row">
                                <strong>Description:</strong> ${data.item.item_description}
                            </div>
                            <div class="detail-row">
                                <strong>Category:</strong> ${data.item.category}
                            </div>
                            <div class="detail-row">
                                <strong>Estimated Useful Life:</strong> ${data.item.estimated_useful_life} years
                            </div>
                            <div class="detail-row">
                                <strong>Quantity Issued:</strong> ${data.item.quantity_issued}
                            </div>
                            <div class="detail-row">
                                <strong>Officer/Office Issued:</strong> ${data.item.office_officer_issued || 'N/A'}
                            </div>
                            <div class="detail-row">
                                <strong>Quantity Returned:</strong> ${data.item.quantity_returned}
                            </div>
                            <div class="detail-row">
                                <strong>Quantity Balance:</strong> ${data.item.quantity_balance}
                            </div>
                            <div class="detail-row">
                                <strong>Amount (Total):</strong> ₱${parseFloat(data.item.amount_total).toLocaleString('en-US', {minimumFractionDigits: 2})}
                            </div>
                            <div class="detail-row">
                                <strong>Fund Cluster:</strong> ${data.item.fund_cluster}
                            </div>
                            ${data.item.remarks ? `<div class="detail-row"><strong>Remarks:</strong> ${data.item.remarks}</div>` : ''}
                        </div>
                    `;
                    document.getElementById('viewModal').style.display = 'flex';
                } else {
                    alert('Error loading item details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading item details');
            });
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
    </script>
</body>
</html>