<?php
require 'auth.php';
require 'config.php';

// Check if we're exporting from a saved report or creating new
$report_date = $_GET['report_date'] ?? date('Y-m-d');
$fund_cluster = $_GET['fund_cluster'] ?? '101';
$accountable_officer = $_GET['accountable_officer'] ?? '';
$official_designation = $_GET['official_designation'] ?? '';
$entity_name = $_GET['entity_name'] ?? 'TESDA Regional Office';
$assumption_date = $_GET['assumption_date'] ?? '';
$certified_by = $_GET['certified_by'] ?? '';
$approved_by = $_GET['approved_by'] ?? '';
$verified_by = $_GET['verified_by'] ?? '';

// Fetch PPE items with physical count data if available
$items = [];
$sql = "SELECT p.id, p.item_name, p.item_description, p.par_no, p.unit, p.amount, p.quantity 
        FROM ppe_property p 
        ORDER BY p.par_no";
        
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'article' => 'PPE',
            'item_name' => $row['item_name'],
            'item_description' => $row['item_description'] ?? '',
            'property_number' => $row['par_no'],
            'unit' => $row['unit'],
            'unit_value' => $row['amount'],
            'qty_property_card' => $row['quantity'],
            'qty_physical_count' => 0,
            'shortage_qty' => 0,
            'shortage_value' => 0.00,
            'remarks' => ''
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>RPCPPE Report - <?php echo htmlspecialchars($report_date); ?></title>
<style>
    body { 
        font-family: Arial, Helvetica, sans-serif; 
        font-size: 12px; 
        margin: 25px; 
    }
    @media print { 
        .no-print { display: none; } 
    }
    .page-wrapper { 
        width: 1000px;
        max-width: 1000px; 
        margin: 0 auto; 
        border: 2px solid #000; 
        padding: 25px; 
        position: relative; 
    }
    .appendix { 
        position: absolute; 
        top: 10px; 
        right: 15px; 
        font-size: 11px; 
        font-style: italic; 
    }
    .title { 
        text-align: center; 
        font-weight: bold; 
        font-size: 14px; 
        margin-bottom: 0px; 
    }
    .header-row { 
        font-size: 11px; 
        margin-bottom: 15px; 
    }
    .header-row div { 
        margin-bottom: 5px; 
    }
    .underline { display: inline-block; 
    border-bottom: 1px solid #000; 
    height: 14px; 
    vertical-align: bottom; 
    margin-left: 5px; 
}
    table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 11px; 
    }
    th, td { 
        border: 2px solid black; 
        padding: 3px; 
        text-align: center; 
        vertical-align: middle; }
    th { 
        font-weight: bold;
        height: 20px; 
        vertical-align: middle; 
     }
    td.desc { 
        text-align: left;
        width: 230px; 
    }
    td.uom, th.uom { 
        width: 65px; 
    }
    td.quantity, th.quantity { 
        width: 100px; 
    }
    td.unit-value, th.unit-value { 
        width: 70px; 
    }
    td.remarks { 
        width: 200px; 
    }
    th.short-top { 
        height: 8px !important; 
        padding: 1px !important;
    }
    th.short-top-2 { 
        height: 8px !important; 
        padding: 1px !important;
        border-bottom: none !important;
    }
    th.middle-blank { 
        height: 20px !important; 
        padding: 2px !important;
        border-bottom: none !important;
    }
    th.small-bottom { 
        height: 10px !important; 
        padding: 2px !important;
        border-top: none !important;
    }
    th.tall-bottom { 
        height: 55px !important; 
        vertical-align: middle !important;
    }
    .sig-box {
        width: 33.33%;
        padding: 5px;
        box-sizing: border-box;
        text-align: left; /* change from center to left */
    }

    .sig-line {
        border-bottom: 1px solid #000;
        width: 80%;
        height: 12px;
        margin: 10px 0 5px 0; /* remove auto centering */
    }

    .sig-label {
        font-size: 11px;
        margin-top: 5px;
    }
    
    .print-button {
        background: #007cba;
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        margin-right: 8px;
    }
    .print-button:hover { 
        background: #005a87;
    }
    .back-link {
        background: #6c757d;
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 13px;
        text-decoration: none;
        display: inline-block;
    }
    .instruction-box {
        background: #fffacd;
        border: 1px solid #ddd;
        padding: 10px;
        margin-bottom: 12px;
        border-radius: 4px;
        font-size: 12px;
    }

</style>
</head>
<body>

<div class="no-print">
    <div class="instruction-box">
        <strong>📄 Export Instructions:</strong>
        <div>1. Click the Print/Save button below.</div>
        <div>2. In the print dialog choose "Save as PDF" or your printer.</div>
        <div>3. Save or print the document.</div>
    </div>
    <button class="print-button" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <a href="RPCPPE.php" class="back-link">← Back to RPCPPE</a>
    <hr style="margin:14px 0;">
</div>

<div class="page-wrapper">
    <div class="appendix">Appendix 73</div>

    <div class="title">REPORT ON THE PHYSICAL COUNT OF PROPERTY, PLANT AND EQUIPMENT</div>

    <!-- Header -->
    <div class="header-row">
        <div style="text-align:center; margin-bottom: 30px; font-size: 11px;">
            <span class="underline" style="width:200px;">PPE</span><br>
            <em>(Type of Property, Plant and Equipment)</em><br>
            As at <span class="underline" style="width:220px;"><?php echo htmlspecialchars($report_date); ?></span>
        </div>
        <div>
            <strong>Fund Cluster:</strong>
            <span class="underline" style="width:150px;"><?php echo htmlspecialchars($fund_cluster); ?></span>
        </div>
        <div>
            For which <span class="underline" style="width:170px;"><?php echo htmlspecialchars($accountable_officer); ?></span>,
            <span class="underline" style="width:120px;"><?php echo htmlspecialchars($official_designation); ?></span>,
            <span class="underline" style="width:120px;"><?php echo htmlspecialchars($entity_name); ?></span>
            is accountable, having assumed such accountability on
            <span class="underline" style="width:110px;"><?php echo htmlspecialchars($assumption_date); ?></span>.
        </div>
    </div>

    <!-- Table -->
    <table>
        <tr>
            <th rowspan="4">ARTICLE</th>
            <th rowspan="4">DESCRIPTION</th>
            <th rowspan="4">PROPERTY NUMBER</th>
            <th rowspan="4" class="uom">UNIT OF MEASURE</th>
            <th rowspan="4" class="unit-value">UNIT VALUE</th>
            <th rowspan="4" class="quantity">QUANTITY <br> per <br> PROPERTY CARD</th>
            <th rowspan="4" class="quantity">QUANTITY <br> per <br> PHYSICAL COUNT</th>
            <th colspan="2" rowspan="2">SHORTAGE/OVERAGE</th>
            <th class="short-top"></th> 
        </tr>
        <tr>
            <th class="short-top-2">REMARKS</th>
        </tr>
        <tr>
            <th style="width:55px;" rowspan="2">Qty</th>
            <th style="width:55px;" rowspan="2">Value</th>
            <th style="border-top: none !important; border-bottom: none !important; height: 0px; padding: 0px;"></th>
        </tr>
        <tr>
            <th class="small-bottom"></th>
        </tr>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['article']); ?></td>
                <td class="desc"><?php echo htmlspecialchars($item['item_name'] . ' - ' . $item['item_description']); ?></td>
                <td class="uom"><?php echo htmlspecialchars($item['property_number']); ?></td>
                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                <td class="unit-value"><?php echo number_format($item['unit_value'], 2); ?></td>
                <td><?php echo htmlspecialchars($item['qty_property_card']); ?></td>
                <td><?php echo htmlspecialchars($item['qty_physical_count']); ?></td>
                <td><?php echo htmlspecialchars($item['shortage_qty']); ?></td>
                <td><?php echo number_format($item['shortage_value'], 2); ?></td>
                <td><?php echo htmlspecialchars($item['remarks']); ?></td>
            </tr>
        <?php endforeach; ?>

        <?php
        // Fill up blank rows to make table 15 rows
        $blank_rows = max(0, 15 - count($items));
        for ($i = 0; $i < $blank_rows; $i++):
        ?>
        <tr>
            <?php for ($j = 0; $j < 10; $j++): ?>
                <td>&nbsp;</td>
            <?php endfor; ?>
        </tr>
        <?php endfor; ?>

        <!-- Signatures row inside table -->
        <tr>
            <td colspan="10" style="padding:5px 10px 5px 10px;">
                <div style="display: flex; width: 100%;">
                    
                    <!-- Certified Correct -->
                    <div style="display:flex; flex-direction:column; align-items:flex-start;;">
                        <div style="font-size:11px; margin-bottom:20px; margin-right:40px;  margin-left:0px;">Certified Correct by:</div>
                            <div style="margin-left: 60px;">
                                <div style="border-bottom:1px solid #000; width:200px; height:12px; margin-bottom:5px; text-align:center; font-weight:bold;"><?php echo htmlspecialchars($certified_by); ?></div>
                                <div style="font-size:11px;">Signature over Printed Name of Inventory <br> Committee Chair and <br> Members</div>
                            </div>
                    </div>

                    <!-- Approved By -->
                    <div style="display:flex; flex-direction:column; align-items:flex-start;">
                        <div style="font-size:11px; margin-bottom:20px; margin-left:85px; margin-right:40px;">Approved by:</div>
                            <div style="margin-left: 133px;">
                                <div style="border-bottom:1px solid #000; width:220px; height:12px; margin-bottom:5px; text-align:center; font-weight:bold;"><?php echo htmlspecialchars($approved_by); ?></div>
                                <div style="font-size:11px;">Signature over Printed Name of Head of <br> Agency/Entity or Authorized Representative</div>
                            </div>
                    </div>

                    <!-- Verified By -->
                    <div style="display:flex; flex-direction:column; align-items:flex-start;">
                        <div style="font-size:11px; margin-bottom:20px; margin-left:100px;">Verified by:</div>
                        <div style="margin-left: 150px;">
                            <div style="border-bottom:1px solid #000; width:200px; height:12px; margin-bottom:5px; text-align:center; font-weight:bold;"><?php echo htmlspecialchars($verified_by); ?></div>
                            <div style="font-size:11px;">Signature over Printed Name of <br> COA Representative</div>
                        </div>
                    </div>

                </div>
            </td>
        </tr>

    </table>
</div>

</body>
</html>
