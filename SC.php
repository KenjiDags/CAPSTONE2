<?php 
    require 'auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Card - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-Specific Icon */
        .container h2::before {
            content: "\f022";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #3b82f6;
        }
        
        /* Container spacing override */
        .container {
            margin: 20px auto;
        }
        
        /* SC page specific container layout */
        .container .search-add-container {
            justify-content: center;
            position: relative;
            gap: 0;
        }
        
        /* Search input styling */
        .search-add-container input[type="text"] {
            width: 700px;
            max-width: 700px;
            padding: 12px 20px;
            font-size: 15px;
            border: 2px solid #cbd5e1;
            border-radius: 25px;
            transition: border-color 0.2s ease;
        }
        
        .search-add-container input[type="text"]:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        
        /* Export button styling */
        .export-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 20px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .export-btn:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            color: white;
            text-decoration: none;
        }
        
        .export-btn i {
            font-size: 1.1em;
        }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="container">
    <h2>Stock Card (SC)</h2>

    <div class="search-add-container">
        <input type="text" id="searchInput" placeholder="Search by stock number, item, or unit...">
        <a href="sc_export_all.php" class="export-btn" title="Export All Items">
            <i class="fas fa-file-export"></i> Export All
        </a>
    </div>

    <div class="table-container">
        <table id="scTable">
        <thead>
            <tr>
                <th><i class="fas fa-barcode"></i> Stock No.</th>
                <th><i class="fas fa-file-invoice"></i> I.A.R</th>
                <th><i class="fas fa-tag"></i> Item</th>
                <th><i class="fas fa-align-left"></i> Description</th>
                <th><i class="fas fa-ruler"></i> Unit of Measurement</th>
                <th><i class="fas fa-cubes"></i> Quantity</th>
                <th><i class="fas fa-exclamation-triangle"></i> Reorder Point</th>
                <th><i class="fas fa-cogs"></i> Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            require 'config.php';
                $sql = "SELECT * FROM items ORDER BY stock_number ASC";
                $result = $conn->query($sql);            
                
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr data-id='{$row['item_id']}'>
                            <td><strong>{$row['stock_number']}</strong></td>
                            <td>{$row['iar']}</td>
                            <td>{$row['item_name']}</td>
                            <td>{$row['description']}</td>
                            <td>{$row['unit']}</td>
                            <td>{$row['quantity_on_hand']}</td>
                            <td>{$row['reorder_point']}</td>
                            <td>
                                <a href='view_sc.php?item_id={$row['item_id']}' title='View SC'>
                                    <i class='fas fa-eye'></i> View
                                </a>
                                <a class='scexport' href='sc_export.php?item_id={$row['item_id']}' title='Export SC'>
                                    <i class='fas fa-download'></i> Export
                                </a>
                            
                            </td>
                            
                        </tr>";
                }
            } else {
                echo '<tr><td colspan="5">
                        <i class="fas fa-inbox"></i> Item not found.
                      </td></tr>';
            }
            ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Search Bar JS-->
<script>
    document.getElementById('searchInput').addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#scTable tbody tr');

        rows.forEach(row => {
            const stockNo = row.cells[0].textContent.toLowerCase();
            const item_name = row.cells[1].textContent.toLowerCase(); 
            const description = row.cells[2].textContent.toLowerCase();
            const unit = row.cells[3].textContent.toLowerCase();

            const match = stockNo.includes(filter) || item_name.includes(filter) || description.includes(filter) || unit.includes(filter);
            row.style.display = match ? '' : 'none';
        });
    });
</script>

</body>
</html>