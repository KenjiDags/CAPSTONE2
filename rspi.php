<?php
require 'auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSPI - Report of Semi-Expendable Property Issued</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .container h2::before {
            content: "\f15c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            margin-right: 12px;
            color: #3b82f6;
        }
        .export-section { margin-bottom: 20px; border-radius: 5px; }
        .export-btn { background-color: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; text-decoration: none; display: inline-block; font-weight: bold; margin-right: 10px; cursor: pointer; }
        .export-btn:hover { background-color: #218838; color: white; text-decoration: none; }
        .export-btn i { margin-right: 5px; }
        /* Themed month filter bar */
        .rspi-filters {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: var(--white);
            padding: 8px 16px;
            border-radius: 25px;
            box-shadow: 0 4px 15px var(--shadow-blue);
            border: 2px solid var(--border-gray);
            transition: all 0.3s ease;
        }
        .rspi-filters:hover { box-shadow: 0 6px 20px var(--shadow-blue-hover); border-color: var(--primary-blue); }
        .rspi-filters label { font-weight: 600; color: var(--primary-blue); font-size: 1rem; display: flex; align-items: center; gap: 6px; }
        .rspi-filters input[type="month"] {
            padding: 8px 15px;
            border: 2px solid var(--border-gray);
            border-radius: 10px;
            background: var(--light-gray);
            font-size: 1rem;
            color: var(--text-gray);
            min-width: 180px;
            transition: all 0.3s ease;
        }
        .rspi-filters input[type="month"]:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.1); background-color: var(--white); }
        .rspi-filters .clear-link { color: var(--text-gray); text-decoration: none; font-size: 0.9rem; padding: 6px 10px; border-radius: 14px; border: 1px solid var(--border-gray); background: var(--light-gray); }
        .rspi-filters .clear-link:hover { color: var(--primary-blue); border-color: var(--primary-blue); background: var(--white); }
    </style>
</head>
<body class="rspi-page">
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <h2>Report of Semi-Expendable Property Issued (RSPI)</h2>

        <?php
            // Determine selected month (YYYY-MM); default to current month
            $selectedMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
            $periodStart = $selectedMonth . '-01';
            $periodEnd = date('Y-m-t', strtotime($periodStart));
        ?>
        <!-- Filters & Export -->
        <div class="export-section">
            <form method="get" class="rspi-filters">
                <label for="month">Month</label>
                <input type="month" id="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" />
                <?php if (!empty($_GET['month'])): ?>
                    <a class="clear-link" href="rspi.php">Clear</a>
                <?php endif; ?>
            </form>
            <a href="export_rspi.php?month=<?= urlencode($selectedMonth) ?>" class="btn pill-btn pill-export" target="_blank">
                <i class="fas fa-file-pdf"></i>
                Export to PDF
            </a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>ICS No.</th>
                    <th>Property No.</th>
                    <th>Item</th>
                    <th>Unit</th>
                    <th>Quantity Issued</th>
                    <th>Unit Cost</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                require 'config.php';
                $sql = "
                    SELECT 
                        i.date_issued AS date,
                        i.ics_no AS ics_no,
                        ii.stock_number AS property_no,
                        COALESCE(NULLIF(sep.remarks, ''), sep.item_description, ii.description) AS item_description,
                        COALESCE(ii.unit, '') AS unit,
                        ii.quantity AS issued_qty,
                        COALESCE(sep.amount, ii.unit_cost) AS unit_cost,
                        COALESCE(sep.amount, ii.unit_cost) * ii.quantity AS amount_total
                    FROM ics i
                    INNER JOIN ics_items ii ON ii.ics_id = i.ics_id
                    LEFT JOIN semi_expendable_property sep 
                        ON sep.semi_expendable_property_no = ii.stock_number COLLATE utf8mb4_general_ci
                    WHERE ii.quantity > 0 AND i.date_issued BETWEEN ? AND ?
                    ORDER BY i.date_issued DESC, ii.ics_item_id DESC
                ";

                // Submit month filter automatically on change
                echo '<script>document.getElementById("month").addEventListener("change", function(){ this.form.submit(); });</script>';

                $stmt = $conn->prepare($sql);
                $result = null;
                if ($stmt) {
                    $stmt->bind_param('ss', $periodStart, $periodEnd);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                    }
                }
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['date']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['ics_no']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['property_no']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['item_description']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['unit']) . '</td>';
                        echo '<td>' . htmlspecialchars((string)(float)$row['issued_qty']) . '</td>';
                        echo '<td class="currency">₱ ' . number_format((float)$row['unit_cost'], 2) . '</td>';
                        echo '<td class="currency">₱ ' . number_format((float)$row['amount_total'], 2) . '</td>';
                        echo '</tr>';
                    }
                    if ($stmt) { $stmt->close(); }
                } else {
                    echo '<tr><td colspan="8">No RSPI entries found.</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <h2>Recapitulation</h2>
        <table>
            <thead>
                <tr>
                    <th>Property No.</th>
                    <th>Total Quantity Issued</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $recapSql = " 
                    SELECT 
                        ii.stock_number AS property_no,
                        SUM(ii.quantity) AS total_issued,
                        SUM(COALESCE(sep.amount, ii.unit_cost) * ii.quantity) AS total_amount
                    FROM ics_items ii
                    LEFT JOIN ics i ON ii.ics_id = i.ics_id
                    LEFT JOIN semi_expendable_property sep 
                        ON sep.semi_expendable_property_no = ii.stock_number COLLATE utf8mb4_general_ci
                    WHERE ii.quantity > 0 AND i.date_issued BETWEEN ? AND ?
                    GROUP BY ii.stock_number
                    ORDER BY ii.stock_number DESC
                ";
                $recapStmt = $conn->prepare($recapSql);
                $recap = null;
                if ($recapStmt) {
                    $recapStmt->bind_param('ss', $periodStart, $periodEnd);
                    if ($recapStmt->execute()) {
                        $recap = $recapStmt->get_result();
                    }
                }

                if ($recap && $recap->num_rows > 0) {
                    while ($row = $recap->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['property_no']) . '</td>';
                        echo '<td>' . htmlspecialchars((string)(float)$row['total_issued']) . '</td>';
                        echo '<td class="currency">₱ ' . number_format((float)$row['total_amount'], 2) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="3">No recapitulation data found.</td></tr>';
                }
                if ($recapStmt) { $recapStmt->close(); }
                $conn->close();
                ?>
            </tbody>
        </table>
    </div>

    <script>
        // Optional: sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.querySelector('.menu-button');
            if (menuButton) menuButton.addEventListener('click', toggleSidebar);
        });
    </script>
</body>
</html>
