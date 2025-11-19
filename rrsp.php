<?php
require 'config.php';
require 'functions.php';

// RRSP listing page (mirrors ICS listing). If deletion requested, process first.
if (isset($_GET['delete_rrsp_id'])) {
    $del_id = (int)$_GET['delete_rrsp_id'];
    $conn->query("DELETE FROM rrsp_items WHERE rrsp_id = $del_id");
    $conn->query("DELETE FROM rrsp WHERE rrsp_id = $del_id");
    $sortParam = isset($_GET['sort']) ? ('?sort=' . urlencode($_GET['sort'])) : '';
    header("Location: rrsp.php" . $sortParam);
    exit();
}

// SEARCH FILTER
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sorting logic similar to ICS
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_newest';
switch ($sort_by) {
    case 'rrsp_no':
        $order_clause = "ORDER BY rrsp_no ASC"; break;
    case 'date_oldest':
        $order_clause = "ORDER BY date_prepared ASC"; break;
    case 'amount_highest':
        $order_clause = "ORDER BY total_amount DESC"; break;
    case 'amount_lowest':
        $order_clause = "ORDER BY total_amount ASC"; break;
    case 'date_newest':
    default:
        $order_clause = "ORDER BY date_prepared DESC"; break;
}

// Fetch RRSP forms with computed total (sum of item quantity * unit_cost)
$whereClause = '';
if ($search !== '') {
    $esc = $conn->real_escape_string($search);
    $whereClause = " WHERE (r.rrsp_no LIKE '%$esc%' OR r.returned_by LIKE '%$esc%' OR r.received_by LIKE '%$esc%' OR r.fund_cluster LIKE '%$esc%')";
}
$result = $conn->query("SELECT r.*, (
    SELECT COALESCE(SUM(ri.quantity * ri.unit_cost),0) FROM rrsp_items ri WHERE ri.rrsp_id = r.rrsp_id
) AS total_amount FROM rrsp r $whereClause $order_clause");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>RRSP - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>" />
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
<body class="rrsp-page">
<?php include 'sidebar.php'; ?>
<div class="content">
    <h2>Receipt of Returned Semi-Expendable Property (RRSP)</h2>
    
  <form id="rrsp-filters" method="get" class="filters">
      <div class="control">
        <label for="sort-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-sort"></i> Sort by:
        </label>
        <select id="sort-select" name="sort" onchange="this.form.submit()">
          <option value="date_newest" <?= ($sort_by==='date_newest')?'selected':''; ?>>Date (Newest First)</option>
          <option value="date_oldest" <?= ($sort_by==='date_oldest')?'selected':''; ?>>Date (Oldest First)</option>
          <option value="rrsp_no" <?= ($sort_by==='rrsp_no')?'selected':''; ?>>RRSP No. (A-Z)</option>
          <option value="amount_highest" <?= ($sort_by==='amount_highest')?'selected':''; ?>>Total Amount (Highest)</option>
          <option value="amount_lowest" <?= ($sort_by==='amount_lowest')?'selected':''; ?>>Total Amount (Lowest)</option>
        </select>
      </div>
      <div class="control">
        <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-search"></i> Search:
        </label>
        <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search description or RRSP no..." />
        <a href="add_rrsp.php" class="pill-btn pill-add">
          <i class="fas fa-plus"></i> Add RRSP Form
        </a>
      </div>
    </form>
    <table id="rrsp-list">
        <thead>
            <tr>
                <th><i class="fas fa-hashtag"></i> RRSP No.</th>
                <th><i class="fas fa-calendar"></i> Date Prepared</th>
                <th><i class="fas fa-user"></i> Returned By</th>
                <th><i class="fas fa-user-check"></i> Received By</th>
                <th><i class="fas fa-building"></i> Fund Cluster</th>
                <th><i class="fas fa-coins"></i> Amount</th>
                <th style="text-align:center;"><i class="fas fa-cogs"></i> Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td><strong>' . htmlspecialchars($row['rrsp_no']) . '</strong></td>';
                echo '<td>' . date('M d, Y', strtotime($row['date_prepared'])) . '</td>';
                echo '<td>' . htmlspecialchars($row['returned_by']) . '</td>';
                echo '<td>' . htmlspecialchars($row['received_by']) . '</td>';
                echo '<td>' . htmlspecialchars($row['fund_cluster']) . '</td>';
                echo '<td>₱' . number_format($row['total_amount'], 2) . '</td>';
                // Actions cell (separate echoes to avoid complex escaping issues)
                echo '<td>';
                echo '<a href="view_rrsp.php?rrsp_id=' . (int)$row['rrsp_id'] . '" title="View RRSP"><i class="fas fa-eye"></i> View</a> ';
                echo '<a href="edit_rrsp.php?rrsp_id=' . (int)$row['rrsp_id'] . '" title="Edit RRSP"><i class="fas fa-edit"></i> Edit</a> ';
                echo '<a href="export_rrsp.php?rrsp_id=' . (int)$row['rrsp_id'] . '" title="Export RRSP"><i class="fas fa-download"></i> Export</a> ';
                echo '<a href="rrsp.php?delete_rrsp_id=' . (int)$row['rrsp_id'] . '" onclick="return confirm(\'Delete this RRSP form?\')" title="Delete RRSP"><i class="fas fa-trash"></i> Delete</a>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7"><i class="fas fa-inbox"></i> No RRSP forms found.</td></tr>';
        }
        ?>
        </tbody>
    </table>
</div>
<script>
// Form auto-submits on sort change via onchange event
</script>
</body>
</html>
