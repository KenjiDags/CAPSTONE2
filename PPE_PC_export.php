<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Property Card</title>
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
        max-width: 780px;
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
        font-size: 15px;
        margin-bottom: 30px;
    }

    /* Header labels */
    .header-row div {
        margin-bottom: 5px;
    }
    .underline {
        display: inline-block;
        border-bottom: 1px solid #000;
        width: 250px;
        height: 14px;
        vertical-align: bottom;
        margin-left: 5px;
    }

    /* Table */
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
    }
    th, td {
        border: 1px solid black;
        padding: 4px;
        text-align: center;
        vertical-align: top;
    }
    th {
        font-weight: bold;
        background: #f2f2f2;
    }
</style>
</head>

<body>

<div class="no-print" style="margin-bottom:15px;">
    <button onclick="window.print()" style="padding:8px 15px;">🖨 Print / Save PDF</button>
</div>

<div class="page-wrapper">
    <div class="appendix">Appendix 69</div>

    <div class="title">PROPERTY CARD</div>

    <!-- Header -->
    <div class="header-row" style="font-size:12px;">

        <div>
            <strong>Entity Name:</strong>
            <span class="underline" style="width:350px;">Sample Entity Name</span>
        </div>

        <div>
            <strong>Property, Plant, and Equipment:</strong>
            <span class="underline" style="width:300px;">Office Equipment</span>
        </div>

        <div style="display:flex; justify-content:space-between;">
            <div>
                <strong>Fund Cluster:</strong>
                <span class="underline" style="width:180px;">01-Regular</span>
            </div>

            <div>
                <strong>Property Number:</strong>
                <span class="underline" style="width:150px;">P-2024-001</span>
            </div>
        </div>

        <div>
            <strong>Description:</strong>
            <span class="underline" style="width:500px;">Laptop - Dell Latitude 5520</span>
        </div>

    </div>

    <br>

    <!-- Property Card Table -->
    <table>
        <tr>
            <th rowspan="2" style="width:10%;">Date</th>
            <th rowspan="2" style="width:12%;">Reference / PAR No.</th>
            <th colspan="2" style="width:14%;">Receipt</th>
            <th colspan="2" style="width:20%;">Issue / Transfer / Disposal</th>
            <th rowspan="2" style="width:8%;">Balance Qty.</th>
            <th rowspan="2" style="width:12%;">Amount</th>
            <th rowspan="2" style="width:12%;">Remarks</th>
        </tr>

        <tr>
            <th>Qty.</th>
            <th>Qty.</th>
            <th>Office/Officer</th>
            <th>Qty.</th>
        </tr>

        <!-- SAMPLE DATA ROWS -->
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

        <tr>
            <td>03/01/2024</td>
            <td>TR-2024-010</td>
            <td></td>
            <td></td>
            <td>Juan Dela Cruz</td>
            <td>1</td>
            <td>0</td>
            <td>0.00</td>
            <td>Issued</td>
        </tr>

        <!-- Blank Rows -->
        <!-- Add as many as needed -->
        <?php for ($i=0; $i<15; $i++): ?>
        <tr>
            <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        </tr>
        <?php endfor; ?>

    </table>

</div>

</body>
</html>