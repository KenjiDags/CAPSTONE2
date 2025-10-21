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
        $order_clause = "ORDER BY ics_no ASC";
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
    /* Screen-only styling for the Sort By pill */
    @media screen {
    .header-controls { display:flex; gap:16px; align-items:center; justify-content: flex-start; flex-wrap: wrap; }
    .sort-container { display:flex; align-items:center; margin-left:0; margin-bottom:32px;}
    .sort-pill { display:inline-flex; align-items:center; gap:10px; background:#f3f7ff; border:1px solid #dbeafe; border-radius:9999px; padding:6px 12px; box-shadow: 0 4px 12px rgba(2, 6, 23, 0.06), inset 0 1px 1px rgba(0,0,0,0.03); height:44px; position:relative; top:2px; }
    /* Make the Add button match the pill height and vertical centering */
    .ics-page .header-controls > button { display:inline-flex; align-items:center; gap:8px; height:44px; padding:0 16px; line-height: 1; }
    .sort-select-container { display:flex; align-items:center; position:relative; }
    .sort-select { height: 36px; line-height: 36px; padding: 0 28px 0 10px; }
        .sort-pill label { margin:0; display:flex; align-items:center; gap:8px; color:#0b4abf; font-weight:600; }
    .sort-select { appearance:none; -webkit-appearance:none; -moz-appearance:none; background:#ffffff; border:1px solid #dbeafe; border-radius:12px; font-size:14px; color:#0f172a; box-shadow: 0 1px 1px rgba(0,0,0,0.04); outline: none; min-width: 220px; }
        .sort-select:focus { border-color:#60a5fa; box-shadow: 0 0 0 3px rgba(59,130,246,0.2); }
        .sort-select-chevron { position:absolute; right:8px; top:50%; transform: translateY(-50%); pointer-events:none; color:#64748b; font-size:12px; }
    }
    </style>
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
            <div class="sort-pill">
                <label for="sort-select">
                    <i class="fas fa-sort" style="color:#0b4abf;"></i>
                    <span>Sort by:</span>
                </label>
                <div class="sort-select-container">
                    <select id="sort-select" class="sort-select" onchange="sortTable(this.value)">
                <option value="date_newest" <?= ($sort_by == 'date_newest') ? 'selected' : '' ?>>Date (Newest First)</option>
                <option value="date_oldest" <?= ($sort_by == 'date_oldest') ? 'selected' : '' ?>>Date (Oldest First)</option>
                <option value="ics_no" <?= ($sort_by == 'ics_no') ? 'selected' : '' ?>>ICS No. (A-Z)</option>
                <option value="amount_highest" <?= ($sort_by == 'amount_highest') ? 'selected' : '' ?>>Total Amount (Highest)</option>
                <option value="amount_lowest" <?= ($sort_by == 'amount_lowest') ? 'selected' : '' ?>>Total Amount (Lowest)</option>
                    </select>
                    <span class="sort-select-chevron"><i class="fas fa-chevron-down"></i></span>
                </div>
            </div>
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