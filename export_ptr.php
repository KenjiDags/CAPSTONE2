<?php
require 'config.php';

// Get PTR ID from URL
$ptr_id = isset($_GET['ptr_id']) ? intval($_GET['ptr_id']) : 0;

if ($ptr_id <= 0) {
    die("Invalid PTR ID");
}

// Fetch PTR header
$stmt = $conn->prepare("SELECT * FROM ppe_ptr WHERE ptr_id = ?");
$stmt->bind_param("i", $ptr_id);
$stmt->execute();
$result = $stmt->get_result();
$ptr = $result->fetch_assoc();
$stmt->close();

if (!$ptr) {
    die("PTR not found");
}

// Fetch PTR items with PPE details
$items_stmt = $conn->prepare("
    SELECT p.* 
    FROM ppe_ptr_items pi
    JOIN ppe_property p ON p.id = pi.ppe_id
    WHERE pi.ptr_id = ?
    ORDER BY p.par_no
");
$items_stmt->bind_param("i", $ptr_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

$entity_name = $ptr['entity_name'];
$fund_cluster = $ptr['fund_cluster'];
$from_officer = $ptr['from_officer'];
$to_officer = $ptr['to_officer'];
$ptr_no = $ptr['ptr_no'];
$transfer_date = $ptr['transfer_date'];
$transfer_type = $ptr['transfer_type'];
$reason = $ptr['reason'];
$approved_by = $ptr['approved_by'] ?? '';
$approved_by_designation = $ptr['approved_by_designation'] ?? '';
$approved_by_date = $ptr['approved_by_date'] ?? '';
$released_by = $ptr['released_by'] ?? '';
$released_by_designation = $ptr['released_by_designation'] ?? '';
$released_by_date = $ptr['released_by_date'] ?? '';
$received_by = $ptr['received_by'] ?? '';
$received_by_designation = $ptr['received_by_designation'] ?? '';
$received_by_date = $ptr['received_by_date'] ?? '';
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
      max-width: 780px;
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
    .form-table td.text-left div {
      margin-bottom: 4px;
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
    
    .data-underline {
        display: inline-block;
        border-bottom: 1px solid #000; /* underline */
        width: 35%;        /* fill remaining space in parent */
        padding-left: 4px;  /* small space from label */
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
        min-height: 80px; 
        text-align: left;
    }

    .reason-cell strong {
        display: block;
        margin-bottom: 6px;
    }

    .reason-lines-container {
        display: flex;
        flex-direction: column;
        justify-content: flex-start;  
        gap: 6px; 
        height: 90px; 
        box-sizing: border-box;
    }

    .reason-lines-container .line {
        border-bottom: 1px solid #000;
        min-height: 14px;    
        width: 100%;
        padding-left: 4px;   
        box-sizing: border-box;
        display: flex;        
        align-items: flex-start;
    }

    .reason-lines-container .line span {
        display: inline-block;
    }

    .signature-row-wrapper {
        display: flex;
        flex-direction: column;
        width: 100%;
    }

    .signature-title-row {
        display: flex;
        margin-bottom: 6px;
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
        width: 100px;
        text-align: left;
        margin-right: 10px;
    }

    .signature-line {
        flex: 1;
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

    .transfer-type {
        text-align: left !important;
        padding-left: 8px;
    }

    .transfer-options {
        display: grid;                  
        grid-template-columns: repeat(2, max-content);
        column-gap: 16px;
        row-gap: 4px;
        margin-top: 6px;
        margin-left: 72px;
    }

    .option {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .checkbox {
        width: 12px;
        height: 12px;
        border: 1px solid black;
        display: inline-flex;
        justify-content: center;
        align-items: center;
        margin-right: 6px;
        font-size: 12px;
        font-weight: bold;
    }

    .inline-underline {
        display: flex;
        align-items: center;
        margin-bottom: 6px; /* space between rows */
    }

    .inline-underline strong {
        flex: 0 0 auto; /* label stays its natural width */
        margin-right: 6px; /* gap between label and underline */
    }

    .full-underline-inline {
        border-bottom: 1px solid #000;
        flex: 1 1 auto; /* underline stretches to fill remaining space */
        display: inline-block;
        min-height: 12px; /* optional: makes line more visible */
        vertical-align: bottom;
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
    <a href="PPE_PTR.php" class="back-link">← Back to PTR List</a>
    <hr style="margin:14px 0;">
  </div>

  <div class="page-wrapper">
    <div class="appendix">Appendix 76</div>
    
    <div class="title">PROPERTY TRANSFER REPORT</div>

    <!-- Entity Name and Fund Cluster Header -->
    <div style="display: flex; margin-top: 15px; margin-bottom: 0px; padding: 5px 0;">
        <div style="width: 69%;"><strong>Entity Name:</strong> <span class="data-underline"><?= htmlspecialchars($entity_name) ?></span></div>
        <div style="width: 31%;"><strong>Fund Cluster:</strong> <span class="data-underline"><?= htmlspecialchars($fund_cluster) ?></span></div>
    </div>

    <!-- Single Connected Form Table -->
    <table class="form-table">
        <tr>
            <td colspan="3" class="text-left">
                <div class="inline-underline">
                    <strong>From Accountable Officer/Agency/Fund Cluster:</strong>
                    <span class="full-underline-inline"><?= htmlspecialchars($from_officer) ?></span>
                </div>
                <div class="inline-underline">
                    <strong>To Accountable Officer/Agency/Fund Cluster:</strong>
                    <span class="full-underline-inline"><?= htmlspecialchars($to_officer) ?></span>
                </div>
            </td>
            <td colspan="2" class="text-left">
                <div class="inline-underline">
                    <strong>PTR No.:</strong>
                    <span class="full-underline-inline"><?= htmlspecialchars($ptr_no) ?></span>
                </div>
                <div class="inline-underline">
                    <strong>Date:</strong>
                    <span class="full-underline-inline"><?= htmlspecialchars(date('F d, Y', strtotime($transfer_date))) ?></span>
                </div>
            </td>
        </tr>

        <tr>
            <td colspan="5" class="transfer-type">
                <strong>Transfer Type:</strong> (check only one)

                <div class="transfer-options">

                    <div class="option">
                        <div class="checkbox"><?= $transfer_type=='Donation'?'✓':'' ?></div>
                        Donation
                    </div>

                    <div class="option">
                        <div class="checkbox"><?= $transfer_type=='Relocation'?'✓':'' ?></div>
                        Relocate
                    </div>

                    <div class="option">
                        <div class="checkbox"><?= $transfer_type=='Reassignment'?'✓':'' ?></div>
                        Reassignment
                    </div>

                    <div class="option">
                        <div class="checkbox"><?= $transfer_type=='Others'?'✓':'' ?></div>
                        Others (Specify) _____________
                    </div>

                </div>
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
        $total_amount = 0;
        $row_count = 0;
        $max_rows = 20;
        
        // Display actual items
        foreach ($items as $item): 
            $total_amount += $item['amount'];
            $row_count++;
        ?>
        <tr>
            <td><?= htmlspecialchars($item['date_acquired'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($item['par_no']) ?></td>
            <td class="text-left"><?= htmlspecialchars($item['item_name'] . ' - ' . $item['item_description']) ?></td>
            <td>₱<?= number_format($item['amount'], 2) ?></td>
            <td><?= htmlspecialchars($item['condition']) ?></td>
        </tr>
        <?php 
        endforeach;
        
        // Fill remaining rows to reach 20 total rows
        for ($i = $row_count; $i < $max_rows; $i++): 
        ?>
        <tr>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td class="text-left">&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
        <?php endfor; ?>

        <!-- Total Row -->
        <tr>
            <td colspan="3" class="text-left"><strong>TOTAL</strong></td>
            <td><strong>₱<?= number_format($total_amount, 2) ?></strong></td>
            <td></td>
        </tr>

        <!-- Reason/Purpose Section -->
        <tr>
            <td colspan="5" class="reason-cell">
                <strong>Reason/Purpose:</strong>
                <div class="reason-lines-container">
                    <div class="line"><span><?= htmlspecialchars($reason) ?></span></div>
                    <div class="line"></div>
                    <div class="line"></div>
                    <div class="line"></div>
                </div>
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
                    <div class="signature-line"><?= htmlspecialchars($approved_by) ?></span></div>
                    <div class="signature-line"><?= htmlspecialchars($released_by) ?></span></div>
                    <div class="signature-line"><?= htmlspecialchars($received_by) ?></span></div>
                </div>

                <!-- Designation -->
                <div class="signature-row">
                    <div class="signature-label">Designation:</div>
                    <div class="signature-line"><?= htmlspecialchars($approved_by_designation) ?></span></div>
                    <div class="signature-line"><?= htmlspecialchars($released_by_designation) ?></span></div>
                    <div class="signature-line"><?= htmlspecialchars($received_by_designation) ?></span></div>
                </div>

                <!-- Date -->
                <div class="signature-row">
                    <div class="signature-label">Date:</div>
                    <div class="signature-line"><?= $approved_by_date ? htmlspecialchars(date('F d, Y', strtotime($approved_by_date))) : '' ?></span></div>
                    <div class="signature-line"><?= $released_by_date ? htmlspecialchars(date('F d, Y', strtotime($released_by_date))) : '' ?></span></div>
                    <div class="signature-line"><?= $received_by_date ? htmlspecialchars(date('F d, Y', strtotime($received_by_date))) : '' ?></span></div>
                </div>

            </td>
        </tr>
    </table>
  </div>
</body>
</html>
