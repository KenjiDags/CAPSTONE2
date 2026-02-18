<?php
    require 'auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSMI - Report on Stock of Materials and Supplies Issued</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f570";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }
        
        /* Container spacing override */
        .container {
            margin: 20px auto;
        }
        
        /* Align export button properly in container */
        .search-add-container {
            align-items: center;
            padding: 15px 20px 0px 20px;
        }
        
        /* Export button styling */
        .export-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        .export-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            color: white;
            text-decoration: none;
        }
        
        .export-btn i {
            font-size: 1.1em;
        }
        
        .export-section {
            margin-bottom: 20px;
        }
        
        /* Currency cell styling */
        .currency {
            text-align: right;
            font-weight: 600;
            color: #059669;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <h2>Report on the Stock of Materials and Supplies Issued (RSMI)</h2>

        <!-- Export Section -->
        <div class="search-add-container">
            <a href="rsmi_export.php" class="export-btn" target="_blank">
                <i class="fas fa-file-pdf"></i> Export to PDF
            </a>
        </div>

        <div class="table-container">
            <table>
            <thead>
                <tr>
                    <th><i class="fas fa-hashtag"></i> RIS No.</th>
                    <th><i class="fas fa-barcode"></i> Stock No.</th>
                    <th><i class="fas fa-tag"></i> Item</th>
                    <th><i class="fas fa-ruler"></i> Unit</th>
                    <th><i class="fas fa-cubes"></i> Quantity Issued</th>
                    <th><i class="fas fa-dollar-sign"></i> Unit Cost</th>
                    <th><i class="fas fa-calculator"></i> Amount</th>
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
        </div>

        <h2>Recapitulation</h2>
        
        <div class="table-container">
            <table>
            <thead>
                <tr>
                    <th><i class="fas fa-barcode"></i> Stock No.</th>
                    <th><i class="fas fa-cubes"></i> Total Quantity Issued</th>
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