<?php
ob_start();
require 'config.php';
require 'functions.php';

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    if ($delete_id > 0) {
        $conn->query("DELETE FROM iirusp WHERE iirusp_id=$delete_id");
    }
    if (ob_get_level() > 0) { ob_end_clean(); }
    header('Location: iirusp.php?deleted=1');
    exit;
}

// Sorting
$sort = $_GET['sort'] ?? 'date';
$order_clause = "ORDER BY ";
switch($sort) {
    case 'number': $order_clause .= "iirusp_no DESC"; break;
    case 'amount': $order_clause .= "total_amount DESC"; break;
    default: $order_clause .= "as_at DESC, iirusp_id DESC";
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_records,
    COUNT(DISTINCT entity_name) as total_entities,
    (SELECT SUM(total_cost) FROM iirusp_items) as total_value,
    (SELECT SUM(carrying_amount) FROM iirusp_items) as total_carrying_amount
    FROM iirusp";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Fetch IIRUSP records with computed total
$query = "SELECT i.*, 
          (SELECT SUM(total_cost) FROM iirusp_items WHERE iirusp_id=i.iirusp_id) as total_amount,
          (SELECT COUNT(*) FROM iirusp_items WHERE iirusp_id=i.iirusp_id) as item_count
          FROM iirusp i $order_clause";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IIRUSP - Inventory Inspection Report</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f3f4f6; }
    .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
    header { margin-bottom: 30px; }
    header h1 { font-size: 2rem; color: #1f2937; margin-bottom: 5px; }
    header p { color: #6b7280; }
    header p { color: #6b7280; }
    
    .search-add-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        gap: 20px;
        flex-wrap: wrap;
    }
    .search-form {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .search-input {
        width: 500px;
        max-width: 100%;
        padding: 10px 15px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
    }
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 600;
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
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .stat-card h3 {
        font-size: 0.9rem;
        color: #6b7280;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #1f2937;
    }
    
    .sort-pills {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .sort-pills span {
        font-weight: 600;
        color: #6b7280;
    }
    .pill {
        padding: 8px 16px;
        border-radius: 20px;
        background: #f3f4f6;
        color: #4b5563;
        text-decoration: none;
        transition: all 0.2s;
        font-size: 0.9rem;
    }
    .pill:hover {
        background: #e5e7eb;
        color: #1f2937;
    }
    .pill.active {
        background: #2563eb;
        color: white;
    }
    
    .table-container {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .table {
        width: 100%;
        border-collapse: collapse;
    }
    .table th, .table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
    }
    .table th {
        background: #2563eb;
        color: white;
        font-weight: 600;
    }
    .table tbody tr:hover {
        background-color: #f9fafb;
    }
    
    .table td a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        text-decoration: none;
        color: #3b82f6;
        font-weight: 500;
        font-size: 13px;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .table td a:hover {
        background: #eff6ff;
        color: #2563eb;
    }
    .table td a i {
        font-size: 13px;
    }
    .table td form {
        display: inline;
        margin: 0;
    }
    .table td button {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: none;
        border: none;
        color: #ef4444;
        font-weight: 500;
        font-size: 13px;
        cursor: pointer;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .table td button:hover {
        background: #fef2f2;
        color: #dc2626;
    }
    .table td button i {
        font-size: 13px;
    }
    
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        background: #e0e7ff;
        color: #4f46e5;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6b7280;
    }
    .empty-state i {
        font-size: 4rem;
        color: #d1d5db;
        margin-bottom: 20px;
    }
    .empty-state h3 {
        font-size: 1.5rem;
        color: #374151;
        margin-bottom: 10px;
    }
    .empty-state a {
        color: #2563eb;
        font-weight: 600;
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
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
</style>
</head>
<body class="iirusp-page">
<?php include 'sidebar.php'; ?>
<div class="container">
    <header>
        <h1><i class="fas fa-clipboard-list"></i> IIRUSP - Inventory and Inspection Report</h1>
        <p>Inventory and Inspection Report of Unserviceable Semi-Expendable Property</p>
    </header>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> IIRUSP record deleted successfully.
        </div>
    <?php endif; ?>

    <!-- Search and Add Container -->
    <div class="search-add-container">
        <form method="GET" class="search-form">
            <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                   placeholder="Search by IIRUSP No., entity name, or fund cluster..." 
                   class="search-input" id="searchInput">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if (!empty($_GET['search'])): ?>
                <a href="iirusp.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
        
        <a href="add_iirusp.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New IIRUSP
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Reports</h3>
            <p class="stat-number"><?= number_format($stats['total_records']) ?></p>
        </div>
        <div class="stat-card">
            <h3>Entities</h3>
            <p class="stat-number"><?= number_format($stats['total_entities']) ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Cost Value</h3>
            <p class="stat-number">₱<?= number_format($stats['total_value'] ?? 0, 2) ?></p>
        </div>
        <div class="stat-card">
            <h3>Carrying Amount</h3>
            <p class="stat-number">₱<?= number_format($stats['total_carrying_amount'] ?? 0, 2) ?></p>
        </div>
    </div>

    <!-- Sort Pills -->
    <div class="sort-pills">
        <span>Sort by:</span>
        <a href="?sort=date<?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" class="pill <?= ($sort==='date')?'active':'' ?>">
            <i class="fas fa-calendar"></i> Date
        </a>
        <a href="?sort=number<?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" class="pill <?= ($sort==='number')?'active':'' ?>">
            <i class="fas fa-hashtag"></i> IIRUSP No.
        </a>
        <a href="?sort=amount<?= !empty($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>" class="pill <?= ($sort==='amount')?'active':'' ?>">
            <i class="fas fa-money-bill"></i> Amount
        </a>
    </div>

    <!-- Table -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>IIRUSP No.</th>
                    <th>As At</th>
                    <th>Entity Name</th>
                    <th>Fund Cluster</th>
                    <th>Items</th>
                    <th>Total Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['iirusp_no']) ?></strong></td>
                            <td><?= date('M d, Y', strtotime($row['as_at'])) ?></td>
                            <td><?= htmlspecialchars($row['entity_name']) ?></td>
                            <td><span class="badge"><?= htmlspecialchars($row['fund_cluster']) ?></span></td>
                            <td><?= number_format($row['item_count']) ?> items</td>
                            <td><strong>₱<?= number_format($row['total_amount'] ?? 0, 2) ?></strong></td>
                            <td>
                                <a href="view_iirusp.php?iirusp_id=<?= $row['iirusp_id'] ?>" title="View IIRUSP">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="edit_iirusp.php?iirusp_id=<?= $row['iirusp_id'] ?>" title="Edit IIRUSP">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="export_iirusp.php?iirusp_id=<?= $row['iirusp_id'] ?>" title="Export IIRUSP" target="_blank">
                                    <i class="fas fa-print"></i> Export
                                </a>
                                <form method="POST" style="display: inline; margin: 0;">
                                    <input type="hidden" name="delete_id" value="<?= $row['iirusp_id'] ?>">
                                    <button type="submit" title="Delete IIRUSP" 
                                            onclick="return confirm('Are you sure you want to delete IIRUSP #<?= htmlspecialchars($row['iirusp_no']) ?>?\n\nThis will permanently delete all associated items.\n\nThis action cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h3>No IIRUSP Records Found</h3>
                                <p>Get started by creating your first Inventory and Inspection Report.</p>
                                <a href="add_iirusp.php">Create New IIRUSP <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
