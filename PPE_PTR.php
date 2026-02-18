<?php
require 'auth.php';
require 'config.php';
require 'functions.php';

// Ensure PTR table exists
$conn->query("CREATE TABLE IF NOT EXISTS ppe_ptr (
    ptr_id INT AUTO_INCREMENT PRIMARY KEY,
    ptr_no VARCHAR(100) NOT NULL,
    entity_name VARCHAR(255) DEFAULT 'TESDA Regional Office',
    fund_cluster VARCHAR(100) DEFAULT '101',
    from_officer VARCHAR(255),
    to_officer VARCHAR(255),
    transfer_date DATE,
    transfer_type VARCHAR(100),
    reason TEXT,
    approved_by VARCHAR(255),
    released_by VARCHAR(255),
    received_by VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
)");

// Add signature columns if they don't exist (for existing tables)
// Check and add approved_by column
$result = $conn->query("SHOW COLUMNS FROM ppe_ptr LIKE 'approved_by'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE ppe_ptr ADD COLUMN approved_by VARCHAR(255)");
}

// Check and add released_by column
$result = $conn->query("SHOW COLUMNS FROM ppe_ptr LIKE 'released_by'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE ppe_ptr ADD COLUMN released_by VARCHAR(255)");
}

// Check and add received_by column
$result = $conn->query("SHOW COLUMNS FROM ppe_ptr LIKE 'received_by'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE ppe_ptr ADD COLUMN received_by VARCHAR(255)");
}

$conn->query("CREATE TABLE IF NOT EXISTS ppe_ptr_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ptr_id INT NOT NULL,
    ppe_id INT NOT NULL,
    FOREIGN KEY (ptr_id) REFERENCES ppe_ptr(ptr_id) ON DELETE CASCADE,
    FOREIGN KEY (ppe_id) REFERENCES ppe_property(id) ON DELETE CASCADE
)");

// DELETE LOGIC
if (isset($_GET['delete_ptr_id'])) {
    $ptr_id = (int)$_GET['delete_ptr_id'];
    // Delete PTR items first due to foreign key constraint
    $conn->query("DELETE FROM ppe_ptr_items WHERE ptr_id = $ptr_id");
    // Then delete PTR header
    $conn->query("DELETE FROM ppe_ptr WHERE ptr_id = $ptr_id");
    // Redirect to avoid resubmission on refresh
    $sortParam = isset($_GET['sort']) ? ('?sort=' . urlencode($_GET['sort'])) : '';
    header("Location: PPE_PTR.php" . $sortParam);
    exit();
}

// SORT LOGIC
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_newest';
$order_clause = '';

switch ($sort_by) {
    case 'ptr_no':
        $order_clause = "ORDER BY ptr_no ASC";
        break;
    case 'date_oldest':
        $order_clause = "ORDER BY transfer_date ASC";
        break;
    case 'amount_highest':
        $order_clause = "ORDER BY total_amount DESC";
        break;
    case 'amount_lowest':
        $order_clause = "ORDER BY total_amount ASC";
        break;
    case 'date_newest':
    default:
        $order_clause = "ORDER BY transfer_date DESC";
        break;
}

// SEARCH FILTER
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = '';
if ($search !== '') {
    $esc = $conn->real_escape_string($search);
    $whereClause = " WHERE (ptr_no LIKE '%$esc%' OR from_officer LIKE '%$esc%' OR to_officer LIKE '%$esc%' OR transfer_type LIKE '%$esc%')";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Transfer Report (PTR) - TESDA Inventory System</title>
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
    box-shadow: 0 0 0 3px #3b82f626;
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
<body class="ptr-page">
<?php include 'sidebar.php'; ?>
<div class="content">
    <h2>Property Transfer Report (PTR)</h2>

  <form id="ptr-filters" method="get" class="filters">
      <div class="control">
        <label for="sort-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001F80;">
          <i class="fas fa-sort"></i> Sort by:
        </label>
        <select id="sort-select" name="sort" onchange="this.form.submit()">
          <option value="date_newest" <?= ($sort_by == 'date_newest') ? 'selected' : '' ?>>Date (Newest First)</option>
          <option value="date_oldest" <?= ($sort_by == 'date_oldest') ? 'selected' : '' ?>>Date (Oldest First)</option>
          <option value="ptr_no" <?= ($sort_by == 'ptr_no') ? 'selected' : '' ?>>PTR No. (A-Z)</option>
          <option value="amount_highest" <?= ($sort_by == 'amount_highest') ? 'selected' : '' ?>>Total Amount (Highest)</option>
          <option value="amount_lowest" <?= ($sort_by == 'amount_lowest') ? 'selected' : '' ?>>Total Amount (Lowest)</option>
        </select>
      </div>
      <div class="control">
        <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#111827;color:#001f80;">
          <i class="fas fa-search"></i> Search:
        </label>
        <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search PTR no, officer, or type..." />
        <a href="add_ptr.php" class="pill-btn pill-add">
          <i class="fas fa-plus"></i> Add PTR Form
        </a>
      </div>
    </form>
    
    <table>
        <thead>
            <tr>
                <th><i class="fas fa-hashtag"></i> PTR No.</th>
                <th><i class="fas fa-calendar"></i> Transfer Date</th>
                <th><i class="fas fa-user"></i> From Officer</th>
                <th><i class="fas fa-user"></i> To Officer</th>
                <th><i class="fas fa-exchange-alt"></i> Transfer Type</th>
                <th><i class="fas fa-dollar-sign"></i> Total Amount</th>
                <th><i class="fas fa-cogs"></i> Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $query = "
                SELECT 
                    p.*,
                    (
                        SELECT COALESCE(SUM(ppe.amount), 0)
                        FROM ppe_ptr_items pi
                        LEFT JOIN ppe_property ppe ON ppe.id = pi.ppe_id
                        WHERE pi.ptr_id = p.ptr_id
                    ) AS total_amount
                FROM ppe_ptr p
                $whereClause
                $order_clause";
            $result = $conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($row['ptr_no']) . '</strong></td>';
                    echo '<td>' . date('M d, Y', strtotime($row['transfer_date'])) . '</td>';
                    echo '<td>' . htmlspecialchars($row['from_officer']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['to_officer']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['transfer_type']) . '</td>';
                    echo '<td>₱' . number_format($row['total_amount'], 2) . '</td>';
                    echo '<td>
                        <a href="view_ptr.php?ptr_id=' . $row["ptr_id"] . '" title="View PTR">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="edit_ptr.php?ptr_id=' . $row["ptr_id"] . '" title="Edit PTR">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        
                        <a href="export_ptr.php?ptr_id=' . $row["ptr_id"] . '" title="Export PTR">
                            <i class="fas fa-download"></i> Export
                        </a>
                        <a href="PPE_PTR.php?delete_ptr_id=' . $row["ptr_id"] . '" 
                           onclick="return confirm(\'Are you sure you want to delete this PTR?\')"
                           title="Delete PTR">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="7">
                        <i class="fas fa-inbox"></i> No PTR records found.
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
