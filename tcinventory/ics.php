<?php
require 'config.php';
require 'functions.php';

// DELETE LOGIC
if (isset($_GET['delete_ics_id'])) {
    $ics_id = (int)$_GET['delete_ics_id'];
    // Delete ICS items first due to foreign key constraint
    $conn->query("DELETE FROM ics_items WHERE ics_id = $ics_id");
    // Then delete ICS header
    $conn->query("DELETE FROM ics WHERE ics_id = $ics_id");
    // Redirect to avoid resubmission on refresh
    header("Location: ics.php");
    exit();
}

// SORT LOGIC
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_newest';
$order_clause = '';

switch ($sort_by) {
    case 'ics_no':
        $order_clause = "ORDER BY ics_no ASC";
        break;
    case 'date_oldest':
        $order_clause = "ORDER BY date_issued ASC";
        break;
    case 'date_newest':
    default:
        $order_clause = "ORDER BY date_issued DESC";
        break;
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
    
</head>
<body class="ics-page">
<?php include 'sidebar.php'; ?>
<div class="content">
    <h2>Inventory Custodian Slip (ICS)</h2>

    
    <div class="header-controls">
        <button onclick="window.location.href='add_ics.php'">
            <i class="fas fa-plus"></i> Add ICS Form
        </button>
        
        <div class="sort-container">
            <label for="sort-select">
                <i class="fas fa-sort"></i> Sort by:
            </label>
            <select id="sort-select" onchange="sortTable(this.value)">
                <option value="date_newest" <?= ($sort_by == 'date_newest') ? 'selected' : '' ?>>Date (Newest First)</option>
                <option value="date_oldest" <?= ($sort_by == 'date_oldest') ? 'selected' : '' ?>>Date (Oldest First)</option>
                <option value="ics_no" <?= ($sort_by == 'ics_no') ? 'selected' : '' ?>>ICS No. (A-Z)</option>
            </select>
        </div>
    </div>
    
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
            $result = $conn->query("
                SELECT i.*, 
                       COALESCE(SUM(ii.total_cost), 0) as total_amount
                FROM ics i 
                LEFT JOIN ics_items ii ON i.ics_id = ii.ics_id 
                GROUP BY i.ics_id 
                $order_clause
            ");
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($row['ics_no']) . '</strong></td>';
                    echo '<td>' . date('M d, Y', strtotime($row['date_issued'])) . '</td>';
                    echo '<td>' . htmlspecialchars($row['received_by']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['fund_cluster']) . '</td>';
                    echo '<td>₱' . number_format($row['total_amount'], 2) . '</td>';
                    echo '<td>
                        <a href="view_ics.php?ics_id=' . $row["ics_id"] . '" title="View ICS">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="add_ics.php?ics_id=' . $row["ics_id"] . '" title="Edit ICS">
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
function sortTable(sortBy) {
    // Get current URL and update sort parameter
    const url = new URL(window.location);
    url.searchParams.set('sort', sortBy);
    
    // Redirect to new URL with sort parameter
    window.location.href = url.toString();
}
</script>

</body>
</html>