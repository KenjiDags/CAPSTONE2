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
        border: 1px solid #000; /* keep row and table borders */
        position: relative; /* ensures line overlays correctly */
    }

</style>
</head>
<body>
<div class="page-wraper">
    <div class="appendix">appendix 74</div>
    <div class="header-section">
        INVENTORY AND INSPECTION REPORT OF UNSERVICEABLE PROPERTY<br>
        As at ________________________________
    </div>

    <p><strong>Entity Name:</strong> Sample Agency Name<br>
    <strong>Fund Cluster:</strong> 01</p>

    <div class="table-container">
        <table id="inventoryTable" style="border-right: 2px solid #000;">
            <tr style="border: 2px solid #000;">
                <th colspan="10">INVENTORY</th>
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
                <th rowspan="2">Remarks</th>

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
                <th></th>
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

        <!-- your data rows here (2 sample rows + 18 generated) -->
    </table>
</div>

<script>
// Always produce 20 total rows
const TOTAL_ROWS = 20;
const table = document.getElementById("inventoryTable");

let existingDataRows = table.rows.length - 2;
let rowsToAdd = TOTAL_ROWS - existingDataRows;

for (let i = 0; i < rowsToAdd; i++) {
    let tr = document.createElement("tr");
    for (let c = 0; c < 18; c++) {
        let td = document.createElement("td");
        td.innerHTML = "&nbsp;";
        tr.appendChild(td);
    }
    table.appendChild(tr);
}

// Add the SIGNATORIES HEADER ROW inside the table (perfect alignment)
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

// Select the first "Inspection and Disposal" column header
const tableContainer = document.querySelector('.table-container');
const firstInspectionTh = document.querySelector('#inventoryTable tr th[colspan="8"]');

// Create the vertical line div
const verticalLine = document.createElement('div');
verticalLine.style.position = 'absolute';
verticalLine.style.top = '0';
verticalLine.style.bottom = '0';
verticalLine.style.width = '1px';
verticalLine.style.backgroundColor = '#000';
verticalLine.style.left = firstInspectionTh.offsetLeft + 'px'; // align perfectly
verticalLine.style.zIndex = '1';

tableContainer.appendChild(verticalLine);
</script>


</body>
</html>