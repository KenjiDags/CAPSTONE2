<?php
require 'config.php';
require 'functions.php';

// Handle delete BEFORE any output so redirects work
if (isset($_GET['delete_itr_id'])) {
    $del_id = (int)$_GET['delete_itr_id'];
    try {
        // Ensure tables exist defensively
        @$conn->query("DELETE FROM itr_items WHERE itr_id = $del_id");
        @$conn->query("DELETE FROM itr WHERE itr_id = $del_id");
    } catch (Throwable $e) {
        // swallow errors to avoid breaking UI; deletion attempt made
    }
    $sortParam = isset($_GET['sort']) ? ('?sort=' . urlencode($_GET['sort'])) : '';
    header('Location: itr.php' . $sortParam);
    exit();
}

// SEARCH FILTER
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITR - TESDA Inventory System</title>
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
<body class="itr-page">
<?php include 'sidebar.php'; ?>
<div class="content">
    <h2>Inventory Transfer Report (ITR)</h2>

    <?php // (delete handled before output) ?>

  <form id="itr-filters" method="get" class="filters">
      <div class="control">
        <label for="sort-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-sort"></i> Sort by:
        </label>
        <select id="sort-select" name="sort" onchange="this.form.submit()">
          <?php $sort = $_GET['sort'] ?? 'date_newest'; ?>
          <option value="date_newest" <?= ($sort == 'date_newest') ? 'selected' : '' ?>>Date (Newest First)</option>
          <option value="date_oldest" <?= ($sort == 'date_oldest') ? 'selected' : '' ?>>Date (Oldest First)</option>
          <option value="itr_no" <?= ($sort == 'itr_no') ? 'selected' : '' ?>>ITR No. (A-Z)</option>
          <option value="amount_highest" <?= ($sort == 'amount_highest') ? 'selected' : '' ?>>Total Amount (Highest)</option>
          <option value="amount_lowest" <?= ($sort == 'amount_lowest') ? 'selected' : '' ?>>Total Amount (Lowest)</option>
        </select>
      </div>
      <div class="control">
        <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-search"></i> Search:
        </label>
        <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search description or ITR no..." />
        <a href="add_itr.php" class="pill-btn pill-add">
          <i class="fas fa-plus"></i> Add ITR Form
        </a>
      </div>
    </form>

    <table>
        <thead>
            <tr>
                <th><i class="fas fa-hashtag"></i> ITR No.</th>
                <th><i class="fas fa-calendar"></i> Date</th>
                <th><i class="fas fa-user"></i> From</th>
                <th><i class="fas fa-user"></i> To</th>
                <th><i class="fas fa-dollar-sign"></i> Total Amount</th>
                <th><i class="fas fa-cogs"></i> Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Check if tables exist
            $hasItr = false;
            try {
                $res = $conn->query("SHOW TABLES LIKE 'itr'");
                $hasItr = $res && $res->num_rows > 0;
                if ($res) { $res->close(); }
            } catch (Throwable $e) { $hasItr = false; }

            if ($hasItr) {
                $sort = $_GET['sort'] ?? 'date_newest';
                $order = 'i.itr_date DESC, i.itr_id DESC';
                if ($sort === 'date_oldest') $order = 'i.itr_date ASC, i.itr_id ASC';
                if ($sort === 'itr_no') $order = 'i.itr_no ASC';
                // amount sorting computed after join
                $orderAmount = '';
                if ($sort === 'amount_highest') $orderAmount = ' ORDER BY total_amount DESC';
                if ($sort === 'amount_lowest') $orderAmount = ' ORDER BY total_amount ASC';

                $whereClause = '';
                if ($search !== '') {
                    $esc = $conn->real_escape_string($search);
                    $whereClause = " WHERE (i.itr_no LIKE '%$esc%' OR i.from_accountable LIKE '%$esc%' OR i.to_accountable LIKE '%$esc%')";
                }

                $sql = "SELECT i.*, IFNULL(SUM(it.amount),0) AS total_amount
                        FROM itr i
                        LEFT JOIN itr_items it ON it.itr_id = i.itr_id
                        $whereClause
                        GROUP BY i.itr_id
                        ";
                // Default order by date unless amount-specific requested
                if ($orderAmount) {
                    $sql .= $orderAmount;
                } else {
                    $sql .= " ORDER BY $order";
                }

                $rs = $conn->query($sql);
                if ($rs && $rs->num_rows > 0) {
                    while ($row = $rs->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['itr_no']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['itr_date']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['from_accountable']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['to_accountable']) . '</td>';
                    echo '<td>₱' . number_format((float)$row['total_amount'], 2) . '</td>';
                    echo '<td>';
                    // Actions: View, Edit, Export, Delete (View added)
                    echo '<a href="view_itr.php?itr_id=' . (int)$row['itr_id'] . '" title="View ITR"><i class="fas fa-eye"></i> View</a> ';
                    echo '<a href="edit_itr.php?itr_id=' . (int)$row['itr_id'] . '" title="Edit ITR"><i class="fas fa-edit"></i> Edit</a> ';
                    echo '<a href="export_itr.php?itr_id=' . (int)$row['itr_id'] . '" title="Export ITR"><i class="fas fa-download"></i> Export</a> ';
                    echo '<a href="itr.php?delete_itr_id=' . (int)$row['itr_id'] . (isset($_GET['sort']) ? ('&sort=' . urlencode($_GET['sort'])) : '') . '" ' .
                        'onclick="return confirm(\'Are you sure you want to delete this ITR?\')" ' .
                        'title="Delete ITR"><i class="fas fa-trash"></i> Delete</a>';
                    echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="6" style="text-align:center; padding:16px;"><i class="fas fa-inbox"></i> No ITR records found.</td></tr>';
                }
                if ($rs) { $rs->close(); }
            } else {
                echo '<tr><td colspan="6" style="text-align:center; padding:16px;"><i class="fas fa-inbox"></i> No ITR records found.</td></tr>';
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
