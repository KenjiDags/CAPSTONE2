<?php
require 'auth.php';
require 'config.php';
require 'functions.php';

// Handle delete BEFORE any output so redirects work
if (isset($_GET['delete_itr_id'])) {
    $del_id = (int)$_GET['delete_itr_id'];
    try {
    require_once 'archive_helpers.php';
    archiveRecord($conn, 'ITR', $del_id);
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Unable to archive ITR. No records were deleted.');
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
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f0ec";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }
    </style>
</head>

<body>
<?php include 'sidebar.php'; ?>
<div class="container">
    <h2>Inventory Transfer Report (ITR)</h2>

    <?php // (delete handled before output) ?>

    <form id="itr-filters" method="get" class="filters">
        <div class="inventory-controls">

            <!-- Sort By -->
            <div class="control-sort">
                <label for="sort-select">
                    <i class="fas fa-sort"></i> Sort By:
                </label>

                <select id="sort-select" name="sort" onchange="this.form.submit()">
                    <?php $sort = $_GET['sort'] ?? 'date_newest'; ?>

                    <option value="date_newest" <?= ($sort == 'date_newest') ? 'selected' : '' ?>>
                        Date (Newest First)
                    </option>

                    <option value="date_oldest" <?= ($sort == 'date_oldest') ? 'selected' : '' ?>>
                        Date (Oldest First)
                    </option>

                    <option value="itr_no" <?= ($sort == 'itr_no') ? 'selected' : '' ?>>
                        ITR No. (A-Z)
                    </option>

                    <option value="amount_highest" <?= ($sort == 'amount_highest') ? 'selected' : '' ?>>
                        Total Amount (Highest)
                    </option>

                    <option value="amount_lowest" <?= ($sort == 'amount_lowest') ? 'selected' : '' ?>>
                        Total Amount (Lowest)
                    </option>
                </select>
            </div>

            <!-- Search -->
            <div class="control-search">
                <label for="searchInput">
                    <i class="fas fa-search"></i> Search:
                </label>

                <input
                    type="text"
                    id="searchInput"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search ITR no., from, or to..."
                >
            </div>

            <!-- Actions -->
            <div class="control-actions">
                <a href="add_itr.php" class="pill-btn pill-add">
                    <i class="fas fa-plus"></i> Add ITR Form
                </a>
            </div>

        </div>
    </form>

    <div class="table-container">
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
                        echo '<td class="currency">₱' . number_format((float)$row['total_amount'], 2) . '</td>';
                        echo '<td class="actions-cell">';
                        echo '    <div class="actions-menu">';
                        echo '        <button type="button" class="actions-menu-toggle" aria-label="Open actions" aria-expanded="false">';
                        echo '            <i class="fas fa-ellipsis-v"></i>';
                        echo '        </button>';

                        echo '        <div class="actions-menu-list">';

                        echo '            <a href="view_itr.php?itr_id=' . (int)$row['itr_id'] . '" class="view-action">';
                        echo '                <i class="fas fa-eye"></i> View';
                        echo '            </a>';

                        echo '            <a href="edit_itr.php?itr_id=' . (int)$row['itr_id'] . '" class="edit-action">';
                        echo '                <i class="fas fa-edit"></i> Edit';
                        echo '            </a>';

                        echo '            <a href="export_itr.php?itr_id=' . (int)$row['itr_id'] . '" class="export-action">';
                        echo '                <i class="fas fa-download"></i> Export';
                        echo '            </a>';

                        echo '            <a href="itr.php?delete_itr_id=' . (int)$row['itr_id'] .
                            (isset($_GET['sort']) ? ('&sort=' . urlencode($_GET['sort'])) : '') . '" ' .
                            'class="delete-action" ' .
                            'onclick="return confirm(\'Are you sure you want to delete this ITR?\')">';
                        echo '                <i class="fas fa-trash"></i> Delete';
                        echo '            </a>';

                        echo '        </div>';
                        echo '    </div>';
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
</div>

<script src="js/actions_menu.js"></script>

</body>
</html>
