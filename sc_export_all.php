<?php
require 'auth.php';
require 'config.php';

// Fetch all items with history
$item_stmt = $conn->prepare("
    SELECT i.*
    FROM items i
    INNER JOIN item_history ih ON i.item_id = ih.item_id
    GROUP BY i.item_id
    ORDER BY i.stock_number ASC
");
$item_stmt->execute();
$items_result = $item_stmt->get_result();
if (!$items_result || $items_result->num_rows === 0) {
    die("❌ No items found with history.");
}

$stock_cards = [];
while ($item = $items_result->fetch_assoc()) {
    // Fetch history for this item
    $history_stmt = $conn->prepare("
        SELECT ih.*, r.ris_no AS ris_no
        FROM item_history ih
        LEFT JOIN ris r ON ih.ris_id = r.ris_id
        WHERE ih.item_id = ?
        ORDER BY ih.changed_at DESC
    ");
    $history_stmt->bind_param("i", $item['item_id']);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();

    $history_rows = [];
    while ($row = $history_result->fetch_assoc()) {
        $history_rows[] = $row;
    }
    $history_stmt->close();

    $stock_cards[] = [
        'item' => $item,
        'history' => $history_rows
    ];
}

$item_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title></title>
<style>
/* Print-specific rules */
@media print {
    .no-print { display: none !important; }
    body { margin: 0; }
    
    .top-spacer {
        height: 20mm;   
        visibility: hidden; 
    }

    /* Each card starts with consistent top margin */
    .card-wrapper {
        page-break-inside: avoid; 
        page-break-after: always; 
        margin: 20mm auto 0 auto;
        padding: 10px 12px;
        border: 2px solid #000;
        max-width: 1000px;
        position: relative;
    }

    table { page-break-inside: auto; }
    tr { page-break-inside: avoid; page-break-after: auto; }
}

/* Screen view styling */
body {
    font-family: "Times New Roman", serif;
    font-size: 12px;
    color: #000;
    margin: 20px;
}
.card-wrapper {
    border: 2px solid #000;
    padding: 10px 12px;
    position: relative;
    max-width: 1000px;
    margin: 0 auto 40px auto;
}
.appendix {
    position: absolute;
    top: 8px;
    right: 12px;
    font-size: 11px;
    font-style: italic;
}
.title {
    text-align: center;
    font-weight: bold;
    font-size: 18px;
    margin: 4px 0 8px;
    letter-spacing: 1px;
}
.meta-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 6px;
    font-size: 12px;
}
.meta-table td { padding: 3px 6px; vertical-align: bottom; }
.meta-item { display: flex; gap: 6px; align-items: flex-end; }
.meta-label { font-weight: bold; white-space: nowrap; }
.field-line { flex: 1 1 180px; border-bottom: 1px solid #000; min-height: 16px; line-height: 16px; padding: 0 4px; }
.field-line.empty:after { content: "\00a0"; }

.stock-card-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
    table-layout: fixed;
    font-size: 11px;
}
.stock-card-table th, .stock-card-table td {
    border: 1px solid #000;
    padding: 4px 6px;
    text-align: center;
    vertical-align: middle;
}
.stock-card-table th { font-weight: bold; }
.no-history { font-style: italic; color: #444; }

.print-button {
    background: #007cba;
    color: white;
    padding: 6px 14px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    margin-bottom: 12px;
}

.back-link {
    background: #6c757d;
    color: white;
    padding: 6px 14px;
    border-radius: 4px;
    font-size: 12px;
    text-decoration: none;
}

.instruction-box {
    background: #fffacd;
    border: 1px solid #ddd;
    padding: 8px;
    margin-bottom: 10px;
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
      <div>2. In the print dialog choose "Save as PDF" or printer of choice.</div>
      <div>3. Save.</div>
    </div>
    <button class="print-button" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <a class="back-link" href="SC.php">← Back to SC</a>
    <hr style="margin:14px 0;">
  </div>

<?php foreach ($stock_cards as $data): 
    $item = $data['item'];
    $history_rows = $data['history'];
?>
<div class="card-wrapper">
    <div class="top-spacer"></div> <!-- Invisible spacer -->
    <div class="appendix">Appendix 53</div>
    <div class="title">STOCK CARD</div>

    <table class="meta-table">
        <tr>
            <td>
                <div class="meta-item"><span class="meta-label">LGU:</span><div class="field-line">TESDA</div></div>
            </td>
            <td>
                <div class="meta-item"><span class="meta-label">Fund:</span><div class="field-line empty"></div></div>
            </td>
            <td></td>
        </tr>
        <tr>
            <td>
                <div class="meta-item"><span class="meta-label">Item:</span><div class="field-line"><?= htmlspecialchars($item['item_name']); ?></div></div>
            </td>
            <td>
                <div class="meta-item"><span class="meta-label">Stock No.:</span><div class="field-line"><?= htmlspecialchars($item['stock_number']); ?></div></div>
            </td>
            <td></td>
        </tr>
        <tr>
            <td>
                <div class="meta-item"><span class="meta-label">Description:</span><div class="field-line"><?= htmlspecialchars($item['description']); ?></div></div>
            </td>
            <td>
                <div class="meta-item"><span class="meta-label">Re-order Point:</span><div class="field-line"><?= htmlspecialchars($item['reorder_point']); ?></div></div>
            </td>
            <td></td>
        </tr>
        <tr>
            <td>
                <div class="meta-item"><span class="meta-label">Unit of Measurement:</span><div class="field-line"><?= htmlspecialchars($item['unit']); ?></div></div>
            </td>
            <td colspan="2"></td>
        </tr>
    </table>

    <table class="stock-card-table">
        <thead>
            <tr>
                <th rowspan="2">Date</th>
                <th rowspan="2">Reference</th>
                <th>Receipt Qty.</th>
                <th colspan="2">Issue</th>
                <th rowspan="2">Balance Qty.</th>
                <th rowspan="2">Days to Consume</th>
            </tr>
            <tr>
                <th>Qty.</th>
                <th>Qty.</th>
                <th>Office</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($history_rows) > 0): ?>
            <?php foreach ($history_rows as $h): ?>
            <tr>
                <td><?= date('M d, Y', strtotime($h['changed_at'])); ?></td>
                <td><?= !empty($h['ris_no']) ? htmlspecialchars($h['ris_no']) : htmlspecialchars($item['iar']); ?></td>
                <td><?= $h['quantity_change'] > 0 ? htmlspecialchars($h['quantity_change']) : ''; ?></td>
                <td><?= $h['quantity_change'] < 0 ? abs(htmlspecialchars($h['quantity_change'])) : ''; ?></td>
                <td></td>
                <td><?= htmlspecialchars($h['quantity_on_hand']); ?></td>
                <td>--</td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7" class="no-history">No history available for this item.</td></tr>
        <?php endif; ?>

        <?php for ($i = 0; $i < max(0, 20 - count($history_rows)); $i++): ?>
            <tr>
                <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

</body>
</html>