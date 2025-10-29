<?php
require 'config.php';
require 'functions.php';
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
    @media screen {
    .header-controls { display:flex; gap:16px; align-items:center; justify-content: flex-start; flex-wrap: wrap; }
    .sort-container { display:flex; align-items:center; margin-left:0; margin-bottom:32px;}
    .sort-pill { display:inline-flex; align-items:center; gap:10px; background:#f3f7ff; border:1px solid #dbeafe; border-radius:9999px; padding:6px 12px; box-shadow: 0 4px 12px rgba(2, 6, 23, 0.06), inset 0 1px 1px rgba(0,0,0,0.03); height:44px; position:relative; top:2px; }
    .itr-page .header-controls > button { display:inline-flex; align-items:center; gap:8px; height:44px; padding:0 16px; line-height: 1; }
    .sort-select-container { display:flex; align-items:center; position:relative; }
    .sort-select { height: 36px; line-height: 36px; padding: 0 28px 0 10px; }
    .sort-pill label { margin:0; display:flex; align-items:center; gap:8px; color:#0b4abf; font-weight:600; }
    .sort-select { appearance:none; -webkit-appearance:none; -moz-appearance:none; background:#ffffff; border:1px solid #dbeafe; border-radius:12px; font-size:14px; color:#0f172a; box-shadow: 0 1px 1px rgba(0,0,0,0.04); outline: none; min-width: 220px; }
    .sort-select:focus { border-color:#60a5fa; box-shadow: 0 0 0 3px rgba(59,130,246,0.2); }
    .sort-select-chevron { position:absolute; right:8px; top:50%; transform: translateY(-50%); pointer-events:none; color:#64748b; font-size:12px; }
    }
    </style>
</head>
<body class="itr-page">
<?php include 'sidebar.php'; ?>
<div class="content">
    <h2>Inventory Transfer Report (ITR)</h2>

    <div class="header-controls">
        <button onclick="window.location.href='add_itr.php'">
            <i class="fas fa-plus"></i> Add ITR Form
        </button>
        <div class="sort-container">
            <div class="sort-pill">
                <label for="sort-select">
                    <i class="fas fa-sort" style="color:#0b4abf;"></i>
                    <span>Sort by:</span>
                </label>
                <div class="sort-select-container">
                    <select id="sort-select" class="sort-select" onchange="sortTable(this.value)">
                        <option value="date_newest">Date (Newest First)</option>
                        <option value="date_oldest">Date (Oldest First)</option>
                        <option value="itr_no">ITR No. (A-Z)</option>
                        <option value="amount_highest">Total Amount (Highest)</option>
                        <option value="amount_lowest">Total Amount (Lowest)</option>
                    </select>
                    <span class="sort-select-chevron"><i class="fas fa-chevron-down"></i></span>
                </div>
            </div>
        </div>
    </div>

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

                $sql = "SELECT i.*, IFNULL(SUM(it.amount),0) AS total_amount
                        FROM itr i
                        LEFT JOIN itr_items it ON it.itr_id = i.itr_id
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
                        echo '<td>—</td>';
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
function sortTable(sortBy) {
    const url = new URL(window.location);
    url.searchParams.set('sort', sortBy);
    window.location.href = url.toString();
}
</script>

</body>
</html>
