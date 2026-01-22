<?php
ob_start();
require 'auth.php';
require 'config.php';
require 'functions.php';

// Handle deletion (POST or GET to mirror ICS UX)
if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) || isset($_GET['delete_iirusp_id'])) {
    $delete_id = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : (int)$_GET['delete_iirusp_id'];
    if ($delete_id > 0) {
        $conn->query("DELETE FROM iirusp WHERE iirusp_id=$delete_id");
    }
    if (ob_get_level() > 0) { ob_end_clean(); }
    $sortParam = isset($_GET['sort']) ? ('?sort=' . urlencode($_GET['sort']) . '&deleted=1') : '?deleted=1';
    header('Location: iirusp.php' . $sortParam);
    exit;
}

// SEARCH FILTER
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sorting (follow ICS-style options)
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_newest';
switch ($sort_by) {
    case 'iirusp_no':
        $order_clause = "ORDER BY iirusp_no ASC"; break;
    case 'date_oldest':
        $order_clause = "ORDER BY as_at ASC, iirusp_id ASC"; break;
    case 'amount_highest':
        $order_clause = "ORDER BY total_amount DESC"; break;
    case 'amount_lowest':
        $order_clause = "ORDER BY total_amount ASC"; break;
    case 'date_newest':
    default:
        $order_clause = "ORDER BY as_at DESC, iirusp_id DESC"; break;
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
$whereClause = '';
if ($search !== '') {
    $esc = $conn->real_escape_string($search);
    $whereClause = " WHERE (i.iirusp_no LIKE '%$esc%' OR i.entity_name LIKE '%$esc%' OR i.fund_cluster LIKE '%$esc%')";
}
$query = "SELECT i.*, 
          (SELECT SUM(total_cost) FROM iirusp_items WHERE iirusp_id=i.iirusp_id) as total_amount,
          (SELECT COUNT(*) FROM iirusp_items WHERE iirusp_id=i.iirusp_id) as item_count
          FROM iirusp i $whereClause $order_clause";
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
<style>
  .filters { margin-bottom:12px; display:flex; gap:12px; align-items:center; flex-wrap: wrap; }
  .filters .control { display:flex; align-items:center; gap:10px; }
  .filters select, .filters input {
    height: 38px;
    padding: 8px 14px;
    border-radius: 9999px;
    border: 1px solid #cbd5e1;
    background-color: #f8fafc;
    color: #111827;
    font-size: 14px;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
  }
  .filters input::placeholder { color: #9ca3af; }
  .filters select:hover, .filters input:hover { background-color: #ffffff; }
  .filters select:focus, .filters input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
    background-color: #ffffff;
  }
  .filters select {
    appearance: none; -webkit-appearance: none; -moz-appearance: none;
    padding-right: 38px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 8l4 4 4-4' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 18px 18px;
  }
  .filters .pill-btn { height: 38px; padding: 0 16px; }
  .filters #searchInput { width: 400px; max-width: 65vw; }
    .pill-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border-radius: 9999px;
      color: #fff;
      font-weight: 600;
      border: none;
      box-shadow: 0 4px 10px rgba(0,0,0,0.12);
      transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
      text-decoration: none;
      cursor: pointer;
    }
    .pill-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(0,0,0,0.18); text-decoration: none; opacity: 0.95; }
    .pill-add { background: linear-gradient(135deg, #67a8ff 0%, #3b82f6 100%); }
    .pill-btn .fas, .pill-btn .fa-solid { font-size: 0.95em; }
</style>
</head>
<body class="iirusp-page">
<?php include 'sidebar.php'; ?>
<div class="content">
    <h2>Inventory and Inspection Report of Unserviceable Semi-Expendable Property (IIRUSP)</h2>

  <form id="iirusp-filters" method="get" class="filters">
      <div class="control">
        <label for="sort-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001f80;">
          <i class="fas fa-sort"></i> Sort by:
        </label>
        <select id="sort-select" name="sort" onchange="this.form.submit()">
          <option value="date_newest" <?= ($sort_by==='date_newest')?'selected':''; ?>>Date (Newest First)</option>
          <option value="date_oldest" <?= ($sort_by==='date_oldest')?'selected':''; ?>>Date (Oldest First)</option>
          <option value="iirusp_no" <?= ($sort_by==='iirusp_no')?'selected':''; ?>>IIRUSP No. (A-Z)</option>
          <option value="amount_highest" <?= ($sort_by==='amount_highest')?'selected':''; ?>>Total Amount (Highest)</option>
          <option value="amount_lowest" <?= ($sort_by==='amount_lowest')?'selected':''; ?>>Total Amount (Lowest)</option>
        </select>
      </div>
      <div class="control">
        <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001f80;">
          <i class="fas fa-search"></i> Search:
        </label>
        <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search description or IIRUSP no..." />
        <a href="add_iirusp.php" class="pill-btn pill-add">
          <i class="fas fa-plus"></i> Add IIRUSP
        </a>
      </div>
    </form>

    <table>
            <thead>
                <tr>
                <th><i class="fas fa-hashtag"></i> IIRUSP No.</th>
                <th><i class="fas fa-calendar"></i> As At</th>
                <th><i class="fas fa-building"></i> Entity Name</th>
                <th><i class="fas fa-building"></i> Fund Cluster</th>
                <th><i class="fas fa-dollar-sign"></i> Total Amount</th>
                <th><i class="fas fa-cogs"></i> Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['iirusp_no']) ?></strong></td>
                        <td><?= date('M d, Y', strtotime($row['as_at'])) ?></td>
                        <td><?= htmlspecialchars($row['entity_name']) ?></td>
                        <td><?= htmlspecialchars($row['fund_cluster']) ?></td>
                        <td>₱<?= number_format($row['total_amount'] ?? 0, 2) ?></td>
                        <td>
                            <a href="view_iirusp.php?iirusp_id=<?= $row['iirusp_id'] ?>" title="View IIRUSP"><i class="fas fa-eye"></i> View</a>
                            <a href="edit_iirusp.php?iirusp_id=<?= $row['iirusp_id'] ?>" title="Edit IIRUSP"><i class="fas fa-edit"></i> Edit</a>
                            <a href="export_iirusp.php?iirusp_id=<?= $row['iirusp_id'] ?>" title="Export IIRUSP" target="_blank"><i class="fas fa-download"></i> Export</a>
                            <a href="iirusp.php?delete_iirusp_id=<?= $row['iirusp_id'] ?>" onclick="return confirm('Are you sure you want to delete this IIRUSP?')" title="Delete IIRUSP"><i class="fas fa-trash"></i> Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">
                        <i class="fas fa-inbox"></i> No IIRUSP records found.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
    </table>
</div>
<script>
// Form auto-submits on sort change via onchange event
</script>
</body>
</html>
