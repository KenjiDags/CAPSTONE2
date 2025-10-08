<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSMI - Report on Stock of Materials and Supplies Issued</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <style>
        .export-section {
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .export-btn {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .export-btn:hover {
            background-color: #218838;
            color: white;
            text-decoration: none;
        }
        
        .export-btn i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <h2>Report on the Stock of Materials and Supplies Issued (RSMI)</h2>

        <!-- Export Section -->
        <div class="export-section">
            <a href="rsmi_export.php" class="export-btn" target="_blank">
                📄 Export to PDF
            </a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>RIS No.</th>
                    <th>Stock No.</th>
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

                $result = $conn->query("
                    SELECT ris.ris_no, ri.stock_number, i.item_name, i.description, i.unit, ri.issued_quantity, 
                        ri.unit_cost_at_issue AS unit_cost,
                        (ri.issued_quantity * ri.unit_cost_at_issue) AS amount
                    FROM ris_items ri
                    JOIN ris ON ri.ris_id = ris.ris_id
                    JOIN items i ON ri.stock_number = i.stock_number
                    ORDER BY ris.date_requested DESC
                ");

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['ris_no']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['stock_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['item_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['unit']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['issued_quantity']) . '</td>';
                        echo '<td class="currency">₱ ' . number_format($row['unit_cost'], 2) . '</td>';
                        echo '<td class="currency">₱ ' . number_format($row['amount'], 2) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="7">No RSMI entries found.</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <h2>Recapitulation</h2>
        <table>
            <thead>
                <tr>
                    <th>Stock No.</th>
                    <th>Total Quantity Issued</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $recap = $conn->query("
                    SELECT stock_number, SUM(issued_quantity) AS total_issued
                    FROM ris_items
                    GROUP BY stock_number
                ");

                if ($recap && $recap->num_rows > 0) {
                    while ($row = $recap->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['stock_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['total_issued']) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="2">No recapitulation data found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <script>
        // Add mobile sidebar toggle functionality if needed
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }

        // Add event listener for mobile menu button if you have one
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.querySelector('.menu-button');
            if (menuButton) {
                menuButton.addEventListener('click', toggleSidebar);
            }
        });
    </script>
</body>
</html>