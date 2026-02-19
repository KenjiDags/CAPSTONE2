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
        
        /* Align export button properly in container */
        .search-add-container {
            align-items: center;
            padding: 15px 20px 0px 20px;
        }
        
        .export-section {
            margin-bottom: 20px;
        }
        
        /* Currency cell styling - green color override */
        .currency {
            color: #059669;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <h2>Report on the Stock of Materials and Supplies Issued (RSMI)</h2>

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

                // Fetch current user's full name for custodian default
                $default_custodian = '';
                $user_id = $_SESSION['user_id'];
                $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result_user = $stmt->get_result();
                if ($result_user && $result_user->num_rows > 0) {
                    $user_data = $result_user->fetch_assoc();
                    $default_custodian = $user_data['full_name'] ?? '';
                }
                $stmt->close();

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

        <!-- Signature Names Form -->
        <div class="section-card" style="background: white; border: 2px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-top: 30px; margin-bottom: 20px;">
            <h3 style="color: #1e293b; font-size: 18px; margin: 0 0 15px 0; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-signature"></i> Signatory Information
            </h3>
            <form id="signatureForm" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <label for="custodian_name" style="display: block; margin-bottom: 6px; font-weight: 600; color: #334155; font-size: 14px;">
                        <i class="fas fa-user"></i> Supply/Property Custodian:
                    </label>
                    <input 
                        type="text" 
                        id="custodian_name" 
                        name="custodian_name" 
                        value="<?php echo htmlspecialchars($default_custodian); ?>"
                        placeholder="Enter name"
                        style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; transition: all 0.3s ease;"
                    >
                </div>
                <div class="form-group" style="margin: 0;">
                    <label for="accounting_staff" style="display: block; margin-bottom: 6px; font-weight: 600; color: #334155; font-size: 14px;">
                        <i class="fas fa-calculator"></i> Designated Accounting Staff:
                    </label>
                    <input 
                        type="text" 
                        id="accounting_staff" 
                        name="accounting_staff" 
                        placeholder="Enter name"
                        style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; transition: all 0.3s ease;"
                    >
                </div>
            </form>
            <style>
                #signatureForm input:hover {
                    border-color: #cbd5e1;
                }
                #signatureForm input:focus {
                    outline: none;
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                }
            </style>
        </div>

        <!-- Export Section -->
        <div class="search-add-container">
            <a href="#" id="exportBtn" class="export-btn">
                <i class="fas fa-file-pdf"></i> Export to PDF
            </a>
        </div>
    </div>

    <script>
        // Update export link with signature names
        document.getElementById('exportBtn').addEventListener('click', function(e) {
            e.preventDefault();
            
            const custodianName = document.getElementById('custodian_name').value;
            const accountingStaff = document.getElementById('accounting_staff').value;
            
            let exportUrl = 'rsmi_export.php';
            const params = new URLSearchParams();
            
            if (custodianName) {
                params.append('custodian_name', custodianName);
            }
            if (accountingStaff) {
                params.append('accounting_staff', accountingStaff);
            }
            
            if (params.toString()) {
                exportUrl += '?' + params.toString();
            }
            
            window.location.href = exportUrl;
        });
        
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