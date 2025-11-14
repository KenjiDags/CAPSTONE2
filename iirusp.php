<?php
require 'config.php';
require 'functions.php';

// Handle deletion
if (isset($_GET['delete']) && isset($_GET['iirusp_id'])) {
    $id = (int)$_GET['iirusp_id'];
    $conn->query("DELETE FROM iirusp WHERE iirusp_id=$id");
    header('Location: iirusp.php');
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

// Fetch IIRUSP records with computed total
$query = "SELECT i.*, 
          (SELECT SUM(total_cost) FROM iirusp_items WHERE iirusp_id=i.iirusp_id) as total_amount
          FROM iirusp i $order_clause";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IIRUSP - Inventory Inspection Report</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="iirusp-page">
<?php include 'sidebar.php'; ?>
<div class="content">
    <h2>IIRUSP - Inventory and Inspection Report of Unserviceable Semi-Expendable Property</h2>
    
    <div class="actions" style="margin-bottom: 20px;">
        <a href="add_iirusp.php" class="btn-primary">
            <i class="fas fa-plus"></i> Create New IIRUSP
        </a>
    </div>

    <div class="sort-pills" style="margin-bottom: 15px;">
        <span>Sort by:</span>
        <a href="?sort=date" class="pill <?= ($sort==='date')?'active':'' ?>">Date</a>
        <a href="?sort=number" class="pill <?= ($sort==='number')?'active':'' ?>">IIRUSP No.</a>
        <a href="?sort=amount" class="pill <?= ($sort==='amount')?'active':'' ?>">Amount</a>
    </div>

    <div class="search-container">
        <input type="text" id="searchInput" class="search-input" placeholder="Search IIRUSP records..." onkeyup="filterTable()">
    </div>

    <table id="iirusp_table" class="data-table">
        <thead>
            <tr>
                <th>IIRUSP No.</th>
                <th>As At</th>
                <th>Entity Name</th>
                <th>Fund Cluster</th>
                <th>Total Amount</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="searchable">
                        <td><?= htmlspecialchars($row['iirusp_no']) ?></td>
                        <td><?= date('M d, Y', strtotime($row['as_at'])) ?></td>
                        <td><?= htmlspecialchars($row['entity_name']) ?></td>
                        <td><?= htmlspecialchars($row['fund_cluster']) ?></td>
                        <td>₱<?= number_format($row['total_amount'] ?? 0, 2) ?></td>
                        <td class="actions-cell">
                            <a href="view_iirusp.php?iirusp_id=<?= $row['iirusp_id'] ?>" class="btn-view" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit_iirusp.php?iirusp_id=<?= $row['iirusp_id'] ?>" class="btn-edit" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="export_iirusp.php?iirusp_id=<?= $row['iirusp_id'] ?>" class="btn-export" title="Export" target="_blank">
                                <i class="fas fa-print"></i>
                            </a>
                            <a href="?delete=1&iirusp_id=<?= $row['iirusp_id'] ?>" class="btn-delete" title="Delete" 
                               onclick="return confirm('Are you sure you want to delete this IIRUSP record?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding:20px;">No IIRUSP records found. <a href="add_iirusp.php">Create one now</a></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<script>
function filterTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('iirusp_table');
    const rows = table.getElementsByClassName('searchable');

    for (let row of rows) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    }
}
</script>
</body>
</html>
