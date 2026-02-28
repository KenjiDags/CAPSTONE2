<?php
ob_start();
require 'auth.php';
require 'config.php';
require 'functions.php';

// Handle deletion (POST or GET)
if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) || isset($_GET['delete_ppe_iirup_id'])) {
    $delete_id = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : (int)$_GET['delete_ppe_iirup_id'];
    if ($delete_id > 0) {
        $conn->query("DELETE FROM ppe_iirup WHERE id=$delete_id");
    }
    if (ob_get_level() > 0) { ob_end_clean(); }
    $sortParam = isset($_GET['sort']) ? ('?sort=' . urlencode($_GET['sort']) . '&deleted=1') : '?deleted=1';
    header('Location: PPE_iirup.php' . $sortParam);
    exit;
}

// SEARCH FILTER
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sorting
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_newest';
switch ($sort_by) {
    case 'id':
        $order_clause = "ORDER BY id ASC"; break;
    case 'date_oldest':
        $order_clause = "ORDER BY date_reported ASC, id ASC"; break;
    case 'amount_highest':
        $order_clause = "ORDER BY amount DESC"; break;
    case 'amount_lowest':
        $order_clause = "ORDER BY amount ASC"; break;
    case 'date_newest':
    default:
        $order_clause = "ORDER BY date_reported DESC, id DESC"; break;
}

// Fetch PPE IIRUP records
$whereClause = '';
if ($search !== '') {
    $esc = $conn->real_escape_string($search);
    $whereClause = " WHERE (particulars LIKE '%$esc%' OR property_number LIKE '%$esc%' OR remarks LIKE '%$esc%')";
}
$query = "SELECT * FROM ppe_iirup $whereClause $order_clause";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PPE IIRUP - Inventory Inspection Report</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
  .container h2::before {
    content: "\f0ae";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    margin-right: 12px;
    color: #3b82f6;
  }
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
<body class="ppe-iirup-page">
<?php include 'sidebar.php'; ?>
<div class="container">
    <h2>Inventory and Inspection Report of Unserviceable PPE (IIRUP)</h2>

  <form id="ppe-iirup-filters" method="get" class="filters" style="display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
      <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; flex: 1;">
        <div class="control">
          <label for="sort-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001f80;">
            <i class="fas fa-sort"></i> Sort by:
          </label>
          <select id="sort-select" name="sort" onchange="this.form.submit()">
            <option value="date_newest" <?= ($sort_by==='date_newest')?'selected':''; ?>>Date (Newest First)</option>
            <option value="date_oldest" <?= ($sort_by==='date_oldest')?'selected':''; ?>>Date (Oldest First)</option>
            <option value="id" <?= ($sort_by==='id')?'selected':''; ?>>ID (Ascending)</option>
            <option value="amount_highest" <?= ($sort_by==='amount_highest')?'selected':''; ?>>Total Amount (Highest)</option>
            <option value="amount_lowest" <?= ($sort_by==='amount_lowest')?'selected':''; ?>>Total Amount (Lowest)</option>
          </select>
        </div>
        <div class="control">
          <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001f80;">
            <i class="fas fa-search"></i> Search:
          </label>
          <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search particulars, property number, or remarks..." />
        </div>
      </div>
      <a href="add_iirup.php" class="pill-btn pill-add" style="margin-left:auto; border-radius: 8px !important;">
        <i class="fas fa-plus"></i> Add IIRUP
      </a>
    </form>

    <table>
            <thead>
                <tr>
                <th>ID</th>
                <th>Date Reported</th>
                <th>Particulars</th>
                <th>Property Number</th>
                <th>Quantity</th>
                <th>Unit Cost</th>
                <th>Depreciation</th>
                <th>Impairment Loss</th>
                <th>Carrying Amount</th>
                <th>Remarks</th>
                <th>Total Amount</th>
                <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['id']) ?></strong></td>
                        <td><?= date('M d, Y', strtotime($row['date_reported'])) ?></td>
                        <td><?= htmlspecialchars($row['particulars']) ?></td>
                        <td><?= htmlspecialchars($row['property_number']) ?></td>
                        <td><?= htmlspecialchars($row['quantity']) ?></td>
                        <td><?= number_format($row['unit_cost'] ?? 0, 2) ?></td>
                        <td><?= number_format($row['depreciation'] ?? 0, 2) ?></td>
                        <td><?= number_format($row['impairment_loss'] ?? 0, 2) ?></td>
                        <td><?= number_format($row['carrying_amount'] ?? 0, 2) ?></td>
                        <td><?= htmlspecialchars($row['remarks']) ?></td>
                        <td><?= number_format($row['amount'] ?? 0, 2) ?></td>
                        <td>
                            <a href="view_ppe_iirup.php?id=<?= $row['id'] ?>" class="pill-btn pill-view" title="View PPE IIRUP"><i class="fas fa-eye"></i> View</a>
                            <a href="edit_ppe_iirup.php?id=<?= $row['id'] ?>" class="pill-btn pill-edit" title="Edit PPE IIRUP"><i class="fas fa-edit"></i> Edit</a>
                            <a href="export_ppe_iirup.php?id=<?= $row['id'] ?>" class="pill-btn pill-export" title="Export PPE IIRUP"><i class="fas fa-download"></i> Export</a>
                            <a href="PPE_iirup.php?delete_ppe_iirup_id=<?= $row['id'] ?>" class="pill-btn pill-delete" onclick="return confirm('Are you sure you want to delete this PPE IIRUP?')" title="Delete PPE IIRUP"><i class="fas fa-trash"></i> Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12">
                        <i class="fas fa-inbox"></i> No PPE IIRUP records found.
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
