<?php
require 'auth.php';
require 'config.php';

// Fetch property items
$item_stmt = $conn->prepare("
    SELECT *
    FROM property_items
    ORDER BY property_number ASC
");
$item_stmt->execute();
$items_result = $item_stmt->get_result();

if (!$items_result || $items_result->num_rows === 0) {
    die("❌ No property items found.");
}

$property_cards = [];

while ($item = $items_result->fetch_assoc()) {

    // Fetch history rows
    $history_stmt = $conn->prepare("
        SELECT ph.*, o.office_name
        FROM property_history ph
        LEFT JOIN offices o ON ph.office_id = o.office_id
        WHERE ph.property_id = ?
        ORDER BY ph.date DESC
    ");
    $history_stmt->bind_param("i", $item['property_id']);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();

    $history_rows = [];
    while ($row = $history_result->fetch_assoc()) {
        $history_rows[] = $row;
    }

    $history_stmt->close();

    $property_cards[] = [
        "item" => $item,
        "history" => $history_rows
    ];
}

$item_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Property Cards</title>

<style>
@media print {
    .no-print { display: none !important; }
    .page-break { page-break-after: always; }
}

body {
    margin: 20px;
    font-family: "Times New Roman", serif;
    font-size: 12px;
    color: black;
}

.card-wrapper {
    max-width: 1000px;
    margin: 0 auto;
    border: 2px solid black;
    padding: 10px 14px 18px;
    position: relative;
}

.appendix {
    position: absolute;
    top: 8px;
    right: 14px;
    font-size: 11px;
    font-style: italic;
}

.title {
    text-align: center;
    font-weight: bold;
    font-size: 18px;
    margin: 4px 0 10px;
}

.meta-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 6px;
    font-size: 12px;
}

.meta-table td {
    padding: 3px 6px;
    vertical-align: bottom;
}

.meta-item {
    display: flex;
    gap: 6px;
    align-items: flex-end;
}

.meta-label {
    font-weight: bold;
    white-space: nowrap;
}

.field-line {
    flex: 1;
    border-bottom: 1px solid black;
    min-height: 16px;
    line-height: 16px;
    padding: 0 4px;
}

.property-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 11px;
}

.property-table th,
.property-table td {
    border: 1px solid black;
    padding: 4px 4px;
    text-align: center;
    vertical-align: middle;
}

.property-table th {
    font-weight: bold;
}

.no-history {
    font-style: italic;
    color: #666;
}
</style>
</head>

<body>

<div class="no-print" style="margin-bottom: 12px;">
    <button onclick="window.print()" 
            style="padding: 6px 14px; background:#007cba; color:white; border:none; border-radius:4px;">
        🖨️ Print / Save as PDF
    </button>
</div>


<?php foreach ($property_cards as $index => $data): ?>
<?php $item = $data['item']; ?>
<?php $history_rows = $data['history']; ?>

<div class="card-wrapper">

    <div class="appendix">Appendix 69</div>
    <div class="title">PROPERTY CARD</div>

    <!-- HEADER FIELDS -->
    <table class="meta-table">
        <tr>
            <td style="width: 65%;">
                <div class="meta-item">
                    <span class="meta-label">Entity Name:</span>
                    <div class="field-line"></div>
                </div>
            </td>
            <td>
                <div class="meta-item">
                    <span class="meta-label">Fund Cluster:</span>
                    <div class="field-line"></div>
                </div>
            </td>
        </tr>

        <tr>
            <td>
                <div class="meta-item">
                    <span class="meta-label">Property, Plant and Equipment:</span>
                    <div class="field-line"><?= htmlspecialchars($item['item_name']); ?></div>
                </div>
            </td>
            <td>
                <div class="meta-item">
                    <span class="meta-label">Property Number:</span>
                    <div class="field-line"><?= htmlspecialchars($item['property_number']); ?></div>
                </div>
            </td>
        </tr>

        <tr>
            <td colspan="2">
                <div class="meta-item">
                    <span class="meta-label">Description:</span>
                    <div class="field-line"><?= htmlspecialchars($item['description']); ?></div>
                </div>
            </td>
        </tr>
    </table>

    <!-- MAIN TABLE -->
    <table class="property-table">
        <thead>
            <tr>
                <th rowspan="2">Date</th>
                <th rowspan="2">Reference / PAR No.</th>
                <th colspan="1">Receipt</th>
                <th colspan="2">Issue / Transfer / Disposal</th>
                <th rowspan="2">Balance Qty.</th>
                <th rowspan="2">Amount</th>
                <th rowspan="2">Remarks</th>
            </tr>
            <tr>
                <th>Qty.</th>
                <th>Qty.</th>
                <th>Office / Officer</th>
            </tr>
        </thead>

        <tbody>
            <?php if (count($history_rows) > 0): ?>
                <?php foreach ($history_rows as $h): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($h['date'])); ?></td>
                        <td><?= htmlspecialchars($h['reference_no']); ?></td>

                        <td><?= $h['qty_change'] > 0 ? $h['qty_change'] : '' ?></td>
                        <td><?= $h['qty_change'] < 0 ? abs($h['qty_change']) : '' ?></td>

                        <td><?= htmlspecialchars($h['office_name']); ?></td>

                        <td><?= htmlspecialchars($h['balance_qty']); ?></td>
                        <td><?= htmlspecialchars($h['amount']); ?></td>
                        <td><?= htmlspecialchars($h['remarks']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="no-history">No history available for this item.</td>
                </tr>
            <?php endif; ?>

            <?php for ($i = 0; $i < 18 - count($history_rows); $i++): ?>
                <tr>
                    <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                    <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>

</div>

<?php if ($index < count($property_cards) - 1): ?>
    <div class="page-break"></div>
<?php endif; ?>

<?php endforeach; ?>

</body>
</html>
