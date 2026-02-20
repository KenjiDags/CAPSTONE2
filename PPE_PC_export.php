<?php
require 'config.php';
require 'functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Property Card</title>
<style>
    @media print {
      .no-print { display:none !important; }
    }

    body { 
        font-family: Arial, sans-serif; 
        font-size: 12px; 
        margin-top:25px;
    }

    .instruction-box {
        background: #fffacd;
        border: 1px solid #ddd;
        padding: 10px;
        margin-bottom: 12px;
        border-radius: 4px;
        font-size: 12px;
    }

    .page-wrapper {
        width: 850px;
        border: 2px solid #000;
        padding: 20px;
        position: relative;
        margin: 0 auto;
    }

    table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 11px; 
    }
    th, td { 
        border: 1px solid #000; 
        padding: 4px; 
    }
    th { background: #f2f2f2; }

    .no-border { border: none !important; }
    .header-label { background: #f2f2f2; font-weight:bold; }
    .underline {
        display: inline-block;
        width: 250px;
        border-bottom: 1px solid #000;
        height: 12px;
        vertical-align: middle;
        margin-left: 4px;
    }

    .small-underline { width:150px; }
    .large-underline { width:400px; }

    .appendix {
        position:absolute;
        top:10px;
        right:10px;
        font-size:11px;
        font-style:italic;
    }

    h2 { text-align:center; margin:0 0 15px 0; }

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

    .back-link {
        background: #6c757d;
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 13px;
        text-decoration: none;
        display: inline-block;
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
        <a href="PPE_PTR.php" class="back-link">← Back to List</a>
        <hr style="margin:14px 0;">
    </div>

<div class="page-wrapper">
    <div class="appendix">Appendix 69</div>
    <h2>PROPERTY CARD</h2>

    <table>
        <!-- ENTITY NAME + FUND CLUSTER -->
        <tr>
            <td colspan="6" class="no-border">
                <strong>Entity Name:</strong>
                <span class="underline large-underline">Sample Entity Name</span>
            </td>
            <td colspan="3" class="no-border">
                <strong>Fund Cluster:</strong>
                <span class="underline small-underline">01-Regular</span>
            </td>
        </tr>

        <!-- PPE + PROPERTY NUMBER -->
        <tr>
            <td colspan="6" class="header-label">
                Property, Plant and Equipment:
                <span class="underline large-underline">Office Equipment</span>
            </td>
            <td colspan="3" class="header-label">
                Property Number:
                <span class="underline small-underline">P-2024-001</span>
            </td>
        </tr>

        <!-- DESCRIPTION -->
        <tr>
            <td colspan="9" class="header-label">
                Description:
                <span class="underline large-underline">Laptop - Dell Latitude 5520</span>
            </td>
        </tr>

        <!-- MAIN HEADER -->
        <tr>
            <th rowspan="2">Date</th>
            <th rowspan="2">Reference / PAR No.</th>
            <th colspan="2">Receipt</th>
            <th colspan="2">Issue / Transfer / Disposal</th>
            <th rowspan="2">Balance Qty.</th>
            <th rowspan="2">Amount</th>
            <th rowspan="2">Remarks</th>
        </tr>

        <tr>
            <th>Qty.</th>
            <th>Qty.</th>
            <th>Office/Officer</th>
            <th>Qty.</th>
        </tr>

        <!-- SAMPLE DATA ROW -->
        <tr>
            <td>01/05/2024</td>
            <td>PAR-2024-001</td>
            <td>1</td>
            <td></td>
            <td></td>
            <td></td>
            <td>1</td>
            <td>45,000.00</td>
            <td>Newly acquired</td>
        </tr>

        <!-- 15 BLANK ROWS -->
        <!-- (Exact count requested) -->
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>

        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>

        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>

    </table>

</div>

</body>
</html>