<?php
require 'config.php';

// Get IIRUP ID from URL
$iirup_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($iirup_id <= 0) {
    die("Invalid IIRUP ID");
}

// Fetch IIRUP header
$stmt = $conn->prepare("SELECT * FROM ppe_iirup WHERE id = ?");
$stmt->bind_param("i", $iirup_id);
$stmt->execute();
$result = $stmt->get_result();
$iirup = $result->fetch_assoc();
$stmt->close();

if (!$iirup) {
    die("IIRUP not found");
}

// Fetch IIRUP items
$items_stmt = $conn->prepare("
    SELECT * FROM ppe_iirup_items
    WHERE ppe_iirup_id = ?
    ORDER BY id
");
$items_stmt->bind_param("i", $iirup_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

$dataRows = count($items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory and Inspection Report</title>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        font-size: 14px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    table, th, td {
        border: 1px solid #000;
    }
    th, td {
        padding: 5px;
        text-align: center;
        font-weight: 400;
    }
    .header-section {
        text-align: center;
        font-weight: bold;
        margin: 20px 0 20px 0;
    }
    .sign-section {
        margin-top: 25px;
        width: 100%;
    }
    .sign-block {
        width: 48%;
        display: inline-block;
        vertical-align: top;
        text-align: center;
        margin-top: 30px;
    }
    .signature-line {
        margin-top: 40px;
        border-top: 1px solid #000;
        width: 80%;
        margin-left: auto;
        margin-right: auto;
        padding-top: 5px;
    }
    .page-wraper {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        border: 2px solid #000;
        padding: 20px;
        position: relative;
    }
    .appendix {
        position: absolute;
        top: 10px; 
        right: 10px;
        font-size: 15px;
        font-style: italic;
    }
    .table-container {
        position: relative;
    }

    .table-container::after {
        content: "";
        position: absolute;
        top: 0;
        bottom: 0;
        left: 0; 
        width: 2px;
        background-color: #000;
        z-index: 1;
    }
    #inventoryTable {
        border-collapse: collapse;
        width: 100%;
    }

    #inventoryTable th,
    #inventoryTable td {
        padding: 5px;
        text-align: center;
        font-weight: 400;
        position: relative; /* ensures line overlays correctly */
    }

    #remarksCol {
        border-right: 2px solid #000;
    }

</style>
</head>
<body>
<div class="page-wraper">
    <div class="appendix">appendix 74</div>
    <div class="header-section">
        INVENTORY AND INSPECTION REPORT OF UNSERVICEABLE PROPERTY<br>
        As at <?= htmlspecialchars(date('M d, Y', strtotime($iirup['date_reported']))) ?>
    </div>

    <p><strong>Entity Name:</strong> <?= htmlspecialchars($iirup['entity_name'] ?? 'TESDA Regional Office') ?><br>
    <strong>Fund Cluster:</strong> <?= htmlspecialchars($iirup['fund_cluster'] ?? '101') ?></p>

    <div class="table-container">
        <table id="inventoryTable" style="border-right: 2px solid #000;">
            <tr style="border: 2px solid #000;">
                <th colspan="10" style="border: 2px solid #000;">INVENTORY</th>
                <th colspan="8">INSPECTION and DISPOSAL</th>
            </tr>

            <tr>
                <th rowspan="2">Date Acquired</th>
                <th rowspan="2">Particulars / Articles</th>
                <th rowspan="2">Property No.</th>
                <th rowspan="2">Qty</th>
                <th rowspan="2">Unit Cost</th>
                <th rowspan="2">Total Cost</th>
                <th rowspan="2">Accum. Depreciation</th>
                <th rowspan="2">Accum. Impairment Losses</th>
                <th rowspan="2">Carrying Amount</th>
                <th rowspan="2" id="remarksCol">Remarks</th>

                <th colspan="5">Disposal</th>
                <th rowspan="2">Appraised Value</th>
                <th colspan="2">Record of Sales</th>
            </tr>

            <tr>
                <th>Sale</th>
                <th>Transfer</th>
                <th>Destruction</th>
                <th>Others</th>
                <th>Total</th>
                <th>OR No.</th>
                <th>Amount</th>
            </tr>
            <tr style="border: 2px solid #000;">
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th id="remarksCol"></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        </tr>

        <!-- data rows populated from database -->
        <?php
        if (!empty($items)) {
            foreach ($items as $item) {
                $totalCost = ($item['quantity'] ?? 0) * (($item['amount'] ?? 0) / ($item['quantity'] ?? 1));
                echo "<tr>";
                echo "<td>" . htmlspecialchars(date('m/d/Y', strtotime($item['date_acquired'] ?? 'now'))) . "</td>";
                echo "<td style='text-align:left;'>" . htmlspecialchars($item['particulars'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($item['PPE_no'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($item['quantity'] ?? 0) . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format(($item['amount'] ?? 0) / ($item['quantity'] ?? 1), 2) . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format($totalCost, 2) . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format($item['depreciation'] ?? 0, 2) . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format($item['impairment_loss'] ?? 0, 2) . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format($item['carrying_amount'] ?? 0, 2) . "</td>";
                echo "<td id='remarksCol' style='text-align:left;'>" . htmlspecialchars($item['remarks'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($item['sale'] ?? 0) . "</td>";
                echo "<td>" . htmlspecialchars($item['transfer'] ?? 0) . "</td>";
                echo "<td>" . htmlspecialchars($item['destruction'] ?? 0) . "</td>";
                echo "<td>" . htmlspecialchars($item['other'] ?? 0) . "</td>";
                echo "<td>" . htmlspecialchars($item['total'] ?? 0) . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format($item['appraised_value'] ?? 0, 2) . "</td>";
                echo "<td>" . htmlspecialchars($item['or_no'] ?? '') . "</td>";
                echo "<td style='text-align:right;'>₱" . number_format($item['amount'] ?? 0, 2) . "</td>";
                echo "</tr>";
            }
        }
        $totalRows = 20;
        $emptyRows = $totalRows - $dataRows;
        for ($i = 0; $i < $emptyRows; $i++) {
            echo "<tr>";
            for ($c = 0; $c < 18; $c++) {
                if ($c == 9) {
                    echo '<td id="remarksCol">&nbsp;</td>';
                } else {
                    echo '<td>&nbsp;</td>';
                }
            }
            echo "</tr>";
        }
        ?>
    </table>
</div>

<script>
// Always produce 20 total rows
const TOTAL_ROWS = 20;
const table = document.getElementById("inventoryTable");

// Count existing data rows (excluding header rows)
let dataRowCount = <?= $dataRows ?>;
let existingRows = table.rows.length - 3; // Subtract header rows
let rowsToAdd = TOTAL_ROWS - dataRowCount;

// Rows are already added by PHP, just ensure we have the signature row
let lastRow = table.rows[table.rows.length - 1];
if (!lastRow.innerHTML.includes("SIGNATORIES")) {
    // Add the SIGNATORIES ROW inside the table
    let signRow = document.createElement("tr");
    signRow.innerHTML = `
        <th colspan="10" style="border: 2px solid #000;">
            <div>
                I HEREBY request inspection and disposition, pursuant to Section 79 of PD 1445, of the property enumerated above.
            </div>
        </th>
        <th colspan="8" style="border: 2px solid #000;">SIGNATORIES 2</th>
    `;
    table.appendChild(signRow);
}
</script>


</body>
</html>