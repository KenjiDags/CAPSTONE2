<?php
ob_start();
require 'auth.php';
include 'config.php'; 
include 'sidebar.php';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Minimal styling - use your existing styles */
        .category-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .category-tab {
            padding: 10px 20px;
            text-decoration: none;
            color: #666;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        .category-tab.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
            font-weight: 600;
        }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table-container {
            background: transparent;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: none;
        }
        .search-add-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 20px;
        }
        .search-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-input {
            width: 500px;      
            min-width: 0;       
            max-width: 100%;     
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .btn-info {
            background: #0ea5e9;
            color: white;
        }
        .btn-info:hover {
            background: #0284c7;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        /* Pill-style action buttons (Edit/Delete) */
        .pill-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            width: 110px; /* equal width for both, slightly smaller */
            border-radius: 9999px;
            color: #fff;
            font-weight: 600;
            font-size: 13px;
            border: none;
            box-shadow: 0 3px 8px rgba(0,0,0,0.12);
            transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
        }
        .pill-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(0,0,0,0.18); opacity: 0.96; text-decoration: none; }
        .pill-edit { background: linear-gradient(135deg, #67a8ff 0%, #3b82f6 100%); }
        .pill-delete { background: linear-gradient(135deg, #ff9aa2 0%, #ef4444 100%); }
    .pill-btn .fas, .pill-btn .fa-solid { font-size: 0.9em; }
        .actions-cell { white-space: nowrap; }
    .action-stack { display: inline-flex; flex-direction: column; gap: 6px; align-items: center; }
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
        .alert {
            padding: 15px;
            border-radius: 4px;
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
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }
        /* Align action buttons nicely */
        .actions-cell { white-space: nowrap; }
        .action-buttons { display: inline-flex; gap: 8px; align-items: center; }
        .action-buttons .btn { margin: 0; }
        .action-buttons form { margin: 0; display: inline; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h2>Semi-Expendable Property — <?php echo htmlspecialchars($category); ?></h2>
        </header>

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
        <div class="search-add-container">
            <form method="GET" class="search-form">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by description, property number, or officer..." 
                       class="search-input">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="?category=<?php echo urlencode($category); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
            
            <!-- Add New Item Button -->
            <a href="add_semi_expendable.php?category=<?php echo urlencode($category); ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> Add New Item
            </a>
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
                                    <a href="add_semi_expendable.php?category=<?php echo urlencode($category); ?>" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Add First Item
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
                                <td>₱<?php echo number_format($item['amount_total'], 2); ?></td>
                                <td class="actions-cell">
                                    <div class="action-stack">
                                        <a href="edit_semi_expendable.php?id=<?php echo $item['id']; ?>" class="pill-btn pill-edit" title="Edit" aria-label="Edit">
                                            <i class="fas fa-pen"></i> Edit
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Delete this item permanently?');" style="margin:0;">
                                            <input type="hidden" name="delete_id" value="<?php echo (int)$item['id']; ?>">
                                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                                            <button type="submit" class="pill-btn pill-delete" title="Delete" aria-label="Delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
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