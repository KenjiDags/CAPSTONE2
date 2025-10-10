<?php include 'sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RIS - TESDA Inventory System</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="SC-Page">

<div class="content">
    <h2> Stock Card (SC)</h2>

    
<div class="search-container-sc" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
    <input type="text" id="searchInput" class="search-input-sc" placeholder="Search by stock number, item, or unit...">
    <a href="sc_export_all.php" class="btn export_all" title="Export All Items" style="padding: 0.5rem 1rem; background:#2d6cdf; color:#fff; border-radius:6px; text-decoration:none; font-size:0.9rem; display:inline-flex; align-items:center; gap:0.4rem;">
        <i class="fas fa-file-export" aria-hidden="true"></i> Export All
    </a>
</div>


    <table id="scTable">
        <thead>
            <tr>
                <th><i class=""></i> Stock No.</th>
                <th><i class=""></i> I.A.R</th>
                <th><i class=""></i> Item</th>
                <th><i class=""></i> Description</th>
                <th><i class=""></i> Unit of Measurement</th>
                <th><i class=""></i> Quantity</th>
                <th><i class=""></i> Reorder Point</th>
                <th><i class=""></i> Actions</th>
            </tr>
        </thead>
        <tbody>


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

</body>
</html>

