<?php
require 'config.php';
require 'functions.php';

// Normalize ICS number for display to the new format "NN-YY"
// - If already in NN-YY, return as is
// - If in old format "ICS-YYYY/MM/####", convert to "NN-YY" where NN is serial (min 2 digits) and YY is last two digits of year
function formatICSNo($ics_no) {
    if (preg_match('/^\d+-\d{2}$/', $ics_no)) {
        return $ics_no; // already new format
    }
    if (preg_match('/^ICS-(\d{4})\/\d{2}\/(\d{1,})$/', $ics_no, $m)) {
        $yy = substr($m[1], -2);
        $serial = ltrim($m[2], '0');
        if ($serial === '') { $serial = '0'; }
        if (strlen($serial) < 2) { $serial = str_pad($serial, 2, '0', STR_PAD_LEFT); }
        return $serial . '-' . $yy;
    }
    // Fallback: return original if we can't confidently parse
    return $ics_no;
}

// DELETE LOGIC
if (isset($_GET['delete_ics_id'])) {
    $ics_id = (int)$_GET['delete_ics_id'];
    // Delete ICS items first due to foreign key constraint
    $conn->query("DELETE FROM ics_items WHERE ics_id = $ics_id");
    // Then delete ICS header
    $conn->query("DELETE FROM ics WHERE ics_id = $ics_id");
    // Redirect to avoid resubmission on refresh (preserve sort param if present)
    $sortParam = isset($_GET['sort']) ? ('?sort=' . urlencode($_GET['sort'])) : '';
    header("Location: ics.php" . $sortParam);
    exit();
}

// SORT LOGIC
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_newest';
$order_clause = '';

switch ($sort_by) {
    case 'ics_no':
        // Sort by year (YY) then serial, handling both new (NN-YY) and old (ICS-YYYY/MM/####) formats
        $order_clause = "ORDER BY 
            CAST(CASE 
                WHEN ics_no REGEXP '^[0-9]+-[0-9]{2}$' THEN RIGHT(ics_no, 2)
                WHEN ics_no LIKE 'ICS-%' THEN RIGHT(SUBSTRING_INDEX(ics_no, '/', 1), 2)
                ELSE 0 END AS UNSIGNED) ASC,
            CAST(CASE 
                WHEN ics_no REGEXP '^[0-9]+-[0-9]{2}$' THEN SUBSTRING_INDEX(ics_no, '-', 1)
                WHEN ics_no LIKE 'ICS-%' THEN RIGHT(ics_no, 4)
                ELSE 0 END AS UNSIGNED) ASC";
        break;
    case 'date_oldest':
        $order_clause = "ORDER BY date_issued ASC";
        break;
    case 'amount_highest':
        $order_clause = "ORDER BY total_amount DESC";
        break;
    case 'amount_lowest':
        $order_clause = "ORDER BY total_amount ASC";
        break;
    case 'date_newest':
    default:
        $order_clause = "ORDER BY date_issued DESC";
        break;
}

// SEARCH FILTER
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = '';
if ($search !== '') {
    $esc = $conn->real_escape_string($search);
    $whereClause = " WHERE (ics_no LIKE '%$esc%' OR received_by LIKE '%$esc%' OR fund_cluster LIKE '%$esc%')";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICS - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
  .filters { margin-bottom:12px; display:flex; gap:12px; align-items:center; flex-wrap: wrap; }
  .filters .control { display:flex; align-items:center; gap:10px; }
  /* Themed fields (match site style) */
  .filters select, .filters input {
    height: 38px;
    padding: 8px 14px;
    border-radius: 9999px;
    border: 1px solid #cbd5e1; /* slate-300 */
    background-color: #f8fafc; /* slate-50 */
    color: #111827; /* gray-900 */
    font-size: 14px;
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
  }
  .filters input::placeholder { color: #9ca3af; }
  .filters select:hover, .filters input:hover { background-color: #ffffff; }
  .filters select:focus, .filters input:focus {
    border-color: #3b82f6; /* primary */
    box-shadow: 0 0 0 3px #3b82f626;
    background-color: #ffffff;
  }
  /* Custom arrow for select to fit theme */
  .filters select {
    appearance: none; -webkit-appearance: none; -moz-appearance: none;
    padding-right: 38px; /* room for arrow */
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 8l4 4 4-4' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 18px 18px;
  }
  .filters .pill-btn { height: 38px; padding: 0 16px; }
  /* Make search box a bit longer */
  .filters #searchInput { width: 400px; max-width: 65vw; }
    /* Pill-style action buttons matching the sample */
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
<body class="ics-page">
<?php include 'sidebar.php'; ?>
<div class="content">
    <h2>Inventory Custodian Slip (ICS)</h2>

  <form id="ics-filters" method="get" class="filters">
      <div class="control">
        <label for="sort-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001F80;">
          <i class="fas fa-sort"></i> Sort by:
        </label>
        <select id="sort-select" name="sort" onchange="this.form.submit()">
          <option value="date_newest" <?= ($sort_by == 'date_newest') ? 'selected' : '' ?>>Date (Newest First)</option>
          <option value="date_oldest" <?= ($sort_by == 'date_oldest') ? 'selected' : '' ?>>Date (Oldest First)</option>
          <option value="ics_no" <?= ($sort_by == 'ics_no') ? 'selected' : '' ?>>ICS No. (A-Z)</option>
          <option value="amount_highest" <?= ($sort_by == 'amount_highest') ? 'selected' : '' ?>>Total Amount (Highest)</option>
          <option value="amount_lowest" <?= ($sort_by == 'amount_lowest') ? 'selected' : '' ?>>Total Amount (Lowest)</option>
        </select>
      </div>
      <div class="control">
        <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#111827;color:#001f80;">
          <i class="fas fa-search"></i> Search:
        </label>
        <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search description or ICS no..." />
        <a href="add_ics.php" class="pill-btn pill-add">
          <i class="fas fa-plus"></i> Add ICS Form
        </a>
      </div>
    </form>
    
    <table>
        <thead>
            <tr>
                <th><i class="fas fa-hashtag"></i> ICS No.</th>
                <th><i class="fas fa-calendar"></i> Date Issued</th>
                <th><i class="fas fa-user"></i> Received By</th>
                <th><i class="fas fa-building"></i> Fund Cluster</th>
                <th><i class="fas fa-dollar-sign"></i> Total Amount</th>
                <th><i class="fas fa-cogs"></i> Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $query = "
                SELECT * FROM (
                    SELECT 
                        i.*,
                        (
                            SELECT COALESCE(SUM(ii2.quantity * COALESCE(sep.amount, ii2.unit_cost)), 0)
                            FROM ics_items ii2
                            LEFT JOIN semi_expendable_property sep 
                              ON sep.semi_expendable_property_no = ii2.stock_number COLLATE utf8mb4_general_ci
                            WHERE ii2.ics_id = i.ics_id AND ii2.quantity > 0
                        ) AS total_amount
                    FROM ics i
                ) t
                $whereClause
                $order_clause";
            $result = $conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars(formatICSNo($row['ics_no'])) . '</strong></td>';
                    echo '<td>' . date('M d, Y', strtotime($row['date_issued'])) . '</td>';
                    echo '<td>' . htmlspecialchars($row['received_by']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['fund_cluster']) . '</td>';
                    echo '<td>₱' . number_format($row['total_amount'], 2) . '</td>';
                    echo '<td>
                        <a href="view_ics.php?ics_id=' . $row["ics_id"] . '" title="View ICS">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="edit_ics.php?ics_id=' . $row["ics_id"] . '" title="Edit ICS">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        
                        <a href="export_ics.php?ics_id=' . $row["ics_id"] . '" title="Export ICS">
                            <i class="fas fa-download"></i> Export
                        </a>
                        <a href="ics.php?delete_ics_id=' . $row["ics_id"] . '" 
                           onclick="return confirm(\'Are you sure you want to delete this ICS?\')"
                           title="Delete ICS">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="6">
                        <i class="fas fa-inbox"></i> No ICS records found.
                      </td></tr>';
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