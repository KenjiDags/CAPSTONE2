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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPE IIRUP Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { font-size: 20px; color: #333; margin-bottom: 5px; }
        .header p { color: #666; font-size: 14px; }
        .info-section { margin-bottom: 25px; }
        .info-section h3 { background: #f0f0f0; padding: 10px; margin-bottom: 10px; font-size: 14px; }
        .info-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 10px; }
        .info-item { font-size: 13px; }
        .info-item strong { display: block; color: #333; margin-bottom: 3px; }
        .info-item span { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table th { background: #0056b3; color: white; padding: 12px; text-align: left; font-size: 12px; font-weight: bold; }
        table td { padding: 10px 12px; border-bottom: 1px solid #ddd; font-size: 12px; }
        table tr:hover { background: #f9f9f9; }
        .currency { text-align: right; }
        .actions { text-align: center; margin-top: 30px; }
        .btn { padding: 10px 20px; margin: 0 5px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .btn-print { background: #0056b3; color: white; }
        .btn-back { background: #6c757d; color: white; }
        @media print {
            body { background: white; }
            .container { box-shadow: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>PPE IIRUP Report</h1>
            <p>Inventory & Inspection Report - Unserviceable PPE</p>
        </div>

        <div class="info-section">
            <h3>Report Information</h3>
            <div class="info-row">
                <div class="info-item">
                    <strong>Report ID:</strong>
                    <span><?= htmlspecialchars($iirup['id']) ?></span>
                </div>
                <div class="info-item">
                    <strong>Date Reported:</strong>
                    <span><?= htmlspecialchars(date('M d, Y', strtotime($iirup['date_reported']))) ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item">
                    <strong>PPE No.:</strong>
                    <span><?= htmlspecialchars($iirup['PPE_no'] ?? 'N/A') ?></span>
                </div>
                <div class="info-item">
                    <strong>Particulars:</strong>
                    <span><?= htmlspecialchars($iirup['particulars'] ?? 'N/A') ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item">
                    <strong>Total Quantity:</strong>
                    <span><?= htmlspecialchars($iirup['quantity'] ?? 0) ?></span>
                </div>
                <div class="info-item">
                    <strong>Total Amount:</strong>
                    <span>₱<?= number_format($iirup['amount'] ?? 0, 2) ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item">
                    <strong>Remarks:</strong>
                    <span><?= htmlspecialchars($iirup['remarks'] ?? 'N/A') ?></span>
                </div>
            </div>
        </div>

        <div class="info-section">
            <h3>Disposal Breakdown</h3>
            <div class="info-row">
                <div class="info-item">
                    <strong>Sale:</strong>
                    <span><?= htmlspecialchars($iirup['sale'] ?? 0) ?></span>
                </div>
                <div class="info-item">
                    <strong>Transfer:</strong>
                    <span><?= htmlspecialchars($iirup['transfer'] ?? 0) ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item">
                    <strong>Destruction:</strong>
                    <span><?= htmlspecialchars($iirup['destruction'] ?? 0) ?></span>
                </div>
                <div class="info-item">
                    <strong>Other:</strong>
                    <span><?= htmlspecialchars($iirup['other'] ?? 0) ?></span>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date Acquired</th>
                    <th>Particulars</th>
                    <th>Quantity</th>
                    <th>Sale</th>
                    <th>Transfer</th>
                    <th>Destruction</th>
                    <th>Other</th>
                    <th>Remarks</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($item['date_acquired'] ?? 'now'))) ?></td>
                        <td><?= htmlspecialchars($item['particulars'] ?? 'N/A') ?></td>
                        <td style="text-align:center;"><?= htmlspecialchars($item['quantity'] ?? 0) ?></td>
                        <td style="text-align:center;"><?= htmlspecialchars($item['sale'] ?? 0) ?></td>
                        <td style="text-align:center;"><?= htmlspecialchars($item['transfer'] ?? 0) ?></td>
                        <td style="text-align:center;"><?= htmlspecialchars($item['destruction'] ?? 0) ?></td>
                        <td style="text-align:center;"><?= htmlspecialchars($item['other'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($item['remarks'] ?? 'N/A') ?></td>
                        <td class="currency">₱<?= number_format($item['amount'] ?? 0, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align:center; color:#999;">No items found for this IIRUP</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="actions">
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <a href="PPE_iirup.php" class="btn btn-back">← Back to List</a>
        </div>
    </div>
</body>
</html>
