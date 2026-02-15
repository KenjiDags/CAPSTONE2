<?php
require 'config.php';

// Sample data - replace with actual data from database
$entity_name = $_GET['entity_name'] ?? 'TESDA Regional Office';
$fund_cluster = $_GET['fund_cluster'] ?? '101';
$from_officer = $_GET['from_officer'] ?? '';
$to_officer = $_GET['to_officer'] ?? '';
$ptr_no = $_GET['ptr_no'] ?? '';
$transfer_date = $_GET['date'] ?? date('Y-m-d');
$transfer_type = $_GET['transfer_type'] ?? '';
$reason = $_GET['reason'] ?? '';

// Fetch items if IDs are provided
$items = [];
if (isset($_GET['item_ids'])) {
    $ids = explode(',', $_GET['item_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT * FROM ppe_property WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Property Transfer Report - Export</title>
  <style>
    @media print {
      .no-print { display:none !important; }
    }
    body {
      margin: 20px;
      font-family: Arial, sans-serif;
      font-size: 11px;
      color: #000;
    }
    .page-wrapper {
      max-width: 850px;
      margin: 0 auto;
      border: 2px solid #000;
      padding: 20px;
      position: relative;
    }
    .appendix {
      position: absolute;
      top: 10px;
      right: 15px;
      font-size: 10px;
      font-style: italic;
    }
    .title {
      text-align: center;
      font-weight: bold;
      font-size: 14px;
      margin: 0 0 15px 0;
      letter-spacing: 0.5px;
    }
    
    .form-table {
      width: 100%;
      border: 2px solid #000;
      border-collapse: collapse;
    }
    .form-table td,
    .form-table th {
      border: 1px solid #000;
      padding: 4px 8px;
      text-align: center;
    }
    .form-table th {
      font-weight: bold;
      background: #f5f5f5;
    }
    .form-table td.text-left {
      text-align: left;
    }
    
    .transfer-type {
      padding: 4px 8px;
    }
    .checkbox {
      display: inline-block;
      width: 12px;
      height: 12px;
      border: 1px solid #000;
      margin: 0 4px 0 8px;
      vertical-align: middle;
    }
    
    .reason-cell {
      padding: 8px;
      min-height: 60px;
      text-align: left;
    }
    .reason-cell strong {
      display: block;
      margin-bottom: 8px;
    }
    .reason-lines {
      border-bottom: 1px solid #000;
      margin: 6px 0;
      min-height: 14px;
    }
    
    .signature-cell {
        padding: 8px;
        vertical-align: top;
        width: 100%;
        font-size: 11px;
    }

    .signature-row-wrapper {
        display: flex;
        flex-direction: column;
        width: 100%;
    }

    .signature-title-row {
        display: flex;
        margin-bottom: 6px; /* space between title and lines */
    }

    .signature-title {
        flex: 1;
        text-align: center;
        font-weight: bold;
    }

    .signature-row {
        display: flex;
        align-items: center;
        margin: 2px 0;
        white-space: nowrap;
    }

    .signature-label {
        width: 100px;  /* fixed label width */
        text-align: left;
        margin-right: 10px;
    }

    .signature-line {
        flex: 1;           /* line expands to fill remaining space */
        border-bottom: 1px solid #000;
        height: 1em;
        margin-right: 10px;
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
    <a href="PPE.php" class="back-link">← Back to PPE List</a>
    <hr style="margin:14px 0;">
  </div>

  <div class="page-wrapper">
    <div class="appendix">Appendix 76</div>
    
    <div class="title">PROPERTY TRANSFER REPORT</div>

    <!-- Entity Name and Fund Cluster Header -->
    <div style="display: flex; margin-top: 15px; margin-bottom: 10px; padding: 5px 0;">
        <div style="width: 70%;"><strong>Entity Name:</strong> <?= htmlspecialchars($entity_name) ?></div>
        <div style="width: 30%;"><strong>Fund Cluster:</strong> <?= htmlspecialchars($fund_cluster) ?></div>
    </div>

    <!-- Single Connected Form Table -->
    <table class="form-table">
        <!-- Header Section (aligned with 5-column layout) -->
        <tr>
            <td colspan="3"><strong>From Accountable Officer/Agency/Fund Cluster:</strong></td>
            <td colspan="2"><strong>PTR No.:</strong> <?= htmlspecialchars($ptr_no) ?></td>
        </tr>
        <tr>
            <td colspan="3"><strong>To Accountable Officer/Agency/Fund Cluster:</strong><br>
            <td colspan="2"><strong>Date:</strong> <?= htmlspecialchars($transfer_date) ?></td>
        </tr>
        <tr>
            <td colspan="5" class="transfer-type">
                <strong>Transfer Type:</strong> (check only one)
                <span class="checkbox"><?= $transfer_type=='Donation'?'✓':'' ?></span> Donation
                <span class="checkbox"><?= $transfer_type=='Relocation'?'✓':'' ?></span> Relocation
                <span class="checkbox"><?= $transfer_type=='Reassignment'?'✓':'' ?></span> Reassignment
                <span class="checkbox"><?= $transfer_type=='Others'?'✓':'' ?></span> Others (Specify) _____________
            </td>
        </tr>
        
        <!-- Items Table Headers -->
        <tr>
            <th style="width: 12%;">Date Acquired</th>
            <th style="width: 15%;">Property No.</th>
            <th style="width: 43%;">Description</th>
            <th style="width: 15%;">Amount</th>
            <th style="width: 15%;">Condition of<br>PPE</th>
        </tr>
        
        <!-- Items Rows -->
        <?php
        $max_rows = 15;
        $count = 0;
        
        if (!empty($items)) {
            foreach ($items as $item) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($item['date_acquired'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($item['par_no'] ?? '') . '</td>';
                echo '<td class="text-left">' . htmlspecialchars($item['item_description'] ?? '') . '</td>';
                echo '<td>' . number_format($item['amount'] ?? 0, 2) . '</td>';
                echo '<td>' . htmlspecialchars($item['condition'] ?? '') . '</td>';
                echo '</tr>';
                $count++;
            }
        }
        
        // Fill remaining rows with empty cells
        for ($i = $count; $i < $max_rows; $i++) {
            echo '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
        }
        ?>
        
        <!-- Reason for Transfer -->
        <tr>
            <td colspan="5" class="reason-cell">
                <strong>Reason for Transfer:</strong>
                <div class="reason-lines"><?= htmlspecialchars($reason) ?></div>
                <div class="reason-lines"></div>
                <div class="reason-lines"></div>
                <div class="reason-lines"></div>
            </td>
        </tr>
        
        <!-- Signature Section -->
<tr>
    <td colspan="5" class="signature-cell">

        <!-- Titles row -->
        <div class="signature-row">
            <div class="signature-label"></div> <!-- empty for labels -->
            <div class="signature-title">Approved by</div>
            <div class="signature-title">Released/Issued by</div>
            <div class="signature-title">Received by</div>
        </div>

        <!-- Signature -->
        <div class="signature-row">
            <div class="signature-label">Signature:</div>
            <div class="signature-line"></div>
            <div class="signature-line"></div>
            <div class="signature-line"></div>
        </div>

        <!-- Printed Name -->
        <div class="signature-row">
            <div class="signature-label">Printed Name:</div>
            <div class="signature-line"></div>
            <div class="signature-line"></div>
            <div class="signature-line"></div>
        </div>

        <!-- Designation -->
        <div class="signature-row">
            <div class="signature-label">Designation:</div>
            <div class="signature-line"></div>
            <div class="signature-line"></div>
            <div class="signature-line"></div>
        </div>

        <!-- Date -->
        <div class="signature-row">
            <div class="signature-label">Date:</div>
            <div class="signature-line"></div>
            <div class="signature-line"></div>
            <div class="signature-line"></div>
        </div>

    </td>
</tr>


    </table>
  </div>

</body>
</html>