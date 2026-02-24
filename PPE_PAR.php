<?php
require 'auth.php';
require 'config.php';
require 'functions.php';

// Generates a PAR No in YY-MM-## format, where ## increments for each new PAR in the current month/year
function generate_par_id($conn) {
    $current_yy = date('y');
    $current_mm = date('m');
    $prefix = $current_yy . '-' . $current_mm . '-%';
    $stmt = $conn->prepare("SELECT par_no FROM ppe_par WHERE par_no LIKE ? ORDER BY par_no DESC LIMIT 1");
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $res = $stmt->get_result();
    $next_num = 1;
    if ($res && $row = $res->fetch_assoc()) {
        $last_par_no = $row['par_no'];
        $parts = explode('-', $last_par_no);
        if (count($parts) === 3 && is_numeric($parts[2])) {
            $next_num = intval($parts[2]) + 1;
        }
    }
    $stmt->close();
    return sprintf('%s-%s-%02d', $current_yy, $current_mm, $next_num);
}

// Ensure PAR table exists with complete structure
$conn->query("CREATE TABLE IF NOT EXISTS ppe_par (
    par_id INT AUTO_INCREMENT PRIMARY KEY,
    par_no VARCHAR(100) NOT NULL UNIQUE,
    entity_name VARCHAR(255) DEFAULT 'TESDA Regional Office',
    fund_cluster VARCHAR(100) DEFAULT '101',
    date_acquired DATE,
    property_number VARCHAR(100),
    received_by VARCHAR(255),
    received_by_designation VARCHAR(255),
    received_by_date DATE,
    issued_by VARCHAR(255),
    issued_by_designation VARCHAR(255),
    issued_by_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
)");

// Ensure PAR items table exists
$conn->query("CREATE TABLE IF NOT EXISTS ppe_par_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    par_id INT NOT NULL,
    ppe_id INT NOT NULL,
    FOREIGN KEY (par_id) REFERENCES ppe_par(par_id) ON DELETE CASCADE,
    FOREIGN KEY (ppe_id) REFERENCES ppe_property(id) ON DELETE CASCADE
)");

// DELETE LOGIC
if (isset($_GET['delete_par_id'])) {
    $par_id = (int)$_GET['delete_par_id'];
    // Delete PAR items first due to foreign key constraint
    $conn->query("DELETE FROM ppe_par_items WHERE par_id = $par_id");
    // Then delete PAR header
    $conn->query("DELETE FROM ppe_par WHERE par_id = $par_id");
    // Redirect to avoid resubmission on refresh
    $sortParam = isset($_GET['sort']) ? ('?sort=' . urlencode($_GET['sort'])) : '';
    header("Location: PPE_PAR.php" . $sortParam);
    exit();
}

// SORT LOGIC
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_newest';
$order_clause = '';

switch ($sort_by) {
    case 'par_no':
        $order_clause = "ORDER BY par_no ASC";
        break;
    case 'date_oldest':
        $order_clause = "ORDER BY date_acquired ASC";
        break;
    case 'amount_highest':
        $order_clause = "ORDER BY total_amount DESC";
        break;
    case 'amount_lowest':
        $order_clause = "ORDER BY total_amount ASC";
        break;
    case 'date_newest':
    default:
        $order_clause = "ORDER BY date_acquired DESC";
        break;
}

// SEARCH FILTER
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = '';
if ($search !== '') {
    $esc = $conn->real_escape_string($search);
    $whereClause = " WHERE (par_no LIKE '%$esc%' OR property_number LIKE '%$esc%' OR received_from LIKE '%$esc%' OR received_by LIKE '%$esc%')";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Acknowledgement Receipt (PAR) - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    /* Page-Specific Icon */
    .container h2::before {
        content: "\f15c";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        color: #3b82f6;
    }
    
    /* Amount Column Styling */
    table td:nth-child(5) {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: #059669;
    }
    
    /* Date Column Styling */
    table td:nth-child(2) {
        color: #64748b;
        font-size: 13px;
    }
    </style>
</head>
<body class="par-page">
<?php include 'sidebar.php'; ?>
<div class="container">
    <h2>Property Acknowledgement Receipt (PAR)</h2>

  <form id="par-filters" method="get" class="filters">
      <div class="control">
        <label for="sort-select" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#001F80;">
          <i class="fas fa-sort"></i> Sort by:
        </label>
        <select id="sort-select" name="sort" onchange="this.form.submit()">
          <option value="date_newest" <?= ($sort_by == 'date_newest') ? 'selected' : '' ?>>Date (Newest First)</option>
          <option value="date_oldest" <?= ($sort_by == 'date_oldest') ? 'selected' : '' ?>>Date (Oldest First)</option>
          <option value="par_no" <?= ($sort_by == 'par_no') ? 'selected' : '' ?>>PAR No. (A-Z)</option>
          <option value="amount_highest" <?= ($sort_by == 'amount_highest') ? 'selected' : '' ?>>Total Amount (Highest)</option>
          <option value="amount_lowest" <?= ($sort_by == 'amount_lowest') ? 'selected' : '' ?>>Total Amount (Lowest)</option>
        </select>
      </div>
      <div class="control">
        <label for="searchInput" style="margin-bottom:0;font-weight:500;display:flex;align-items:center;gap:6px;color:#111827;color:#001f80;">
          <i class="fas fa-search"></i> Search:
        </label>
        <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search PAR no, property no, or officer..." />
      </div>
    </form>
    
    <table>
        <thead>
            <tr>
                <th><i class="fas fa-hashtag"></i> PAR No.</th>
                <th><i class="fas fa-calendar"></i> Date Acquired</th>
                <th><i class="fas fa-barcode"></i> Property Number</th>
                <th><i class="fas fa-user"></i> Received By</th>
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
                        FROM ppe_par_items pi
                        LEFT JOIN ppe_property ppe ON ppe.id = pi.ppe_id
                        WHERE pi.par_id = p.par_id
                    ) AS total_amount
                FROM ppe_par p
                $whereClause
                $order_clause";
            $result = $conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($row['par_no']) . '</strong></td>';
                    echo '<td>' . ($row['date_acquired'] ? date('M d, Y', strtotime($row['date_acquired'])) : 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($row['property_number'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($row['received_by'] ?? 'N/A') . '</td>';
                    echo '<td>₱' . number_format($row['total_amount'], 2) . '</td>';
                    echo '<td>
                        <a href="view_par.php?id=' . $row["par_id"] . '" title="View PAR">
                            <i class="fas fa-eye"></i> View
                        </a>
                        
                        <a href="export_par.php?id=' . $row["par_id"] . '" title="Export PAR">
                            <i class="fas fa-download"></i> Export
                        </a>
                        <a href="PPE_PAR.php?delete_par_id=' . $row["par_id"] . '" 
                           onclick="return confirm(\'Are you sure you want to delete this PAR?\')"
                           title="Delete PAR">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="6" style="text-align: center;">
                        <i class="fas fa-inbox"></i>
                        <div style="font-weight: 600; margin-bottom: 8px;">No PAR Records Found</div>
                        <div style="font-size: 14px;">No Property Acknowledgement Receipts available</div>
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
