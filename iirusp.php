<?php
require 'config.php';
include 'sidebar.php';

// Fetch semi-expendable items from database
$items = [];
$sql = "SELECT date, category, item_description, semi_expendable_property_no, amount_total, quantity_balance, remarks FROM semi_expendable_property ORDER BY item_description";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
} else {
    error_log('IIRUSP DB error: ' . $conn->error);
}
?>
<style>
    .iirusp-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 24px;
    }
    
    .iirusp-header {
        text-align: center;
        margin-bottom: 32px;
        border-bottom: 3px solid #007bff;
        padding-bottom: 16px;
    }
    
    .iirusp-header h1 {
        font-size: 24px;
        margin: 0;
        color: #333;
    }
    
    .iirusp-header p {
        color: #666;
        font-size: 14px;
        margin: 8px 0 0 0;
    }
    
    .form-section {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 24px;
    }
    
    .form-section-title {
        font-weight: 600;
        font-size: 16px;
        color: #495057;
        margin-bottom: 16px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 16px;
    }
    
    .form-row.full {
        grid-template-columns: 1fr;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
    }
    
    .form-group label {
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
        font-size: 13px;
    }
    
    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group select,
    .form-group textarea {
        padding: 10px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
        font-family: inherit;
        transition: border-color 0.3s;
    }
    
    .form-group input[type="text"]:focus,
    .form-group input[type="date"]:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }
    
    .search-box {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 12px;
        margin-bottom: 16px;
        display: flex;
        gap: 12px;
        align-items: center;
    }
    
    .search-box input {
        flex: 1;
        border: 1px solid #ced4da;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .table-wrapper {
        overflow-x: auto;
        overflow-y: auto;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 24px;
        max-height: 600px;
        border: 2px solid #dee2e6;
    }
    
    .iirusp-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    
    .iirusp-table thead {
        background: #007bff;
        color: white;
    }
    
    .iirusp-table th {
        padding: 12px;
        text-align: left;
        font-weight: 600;
        border: 1px solid #0056b3;
    }
    
    .iirusp-table tbody tr {
        border-bottom: 1px solid #dee2e6;
        transition: background-color 0.2s;
    }
    
    .iirusp-table tbody tr:hover {
        background-color: #f8f9fa;
    }
    
    .iirusp-table td {
        padding: 8px;
        border: 1px solid #dee2e6;
    }
    
    .iirusp-table .col-center {
        text-align: center;
    }
    
    .iirusp-table .col-right {
        text-align: right;
    }
    
    .item-input {
        width: 100%;
        padding: 4px 6px;
        border: 1px solid #ced4da;
        border-radius: 2px;
        font-family: inherit;
        font-size: 12px;
        box-sizing: border-box;
    }
    
    .item-input:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.1);
        background: #fffbf0;
    }
    
    .item-input[readonly] {
        background-color: #f5f5f5;
        color: #666;
        cursor: not-allowed;
    }
    
    .item-input[type="number"] {
        text-align: right;
    }
    
    .item-input[type="date"] {
        font-size: 11px;
    }
    
    .disposal-check {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .no-items {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
        font-style: italic;
    }
    
    .signature-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 24px;
        margin-top: 32px;
    }
    
    .signature-block {
        display: flex;
        flex-direction: column;
    }
    
    .signature-label {
        font-weight: 600;
        color: #333;
        margin-bottom: 12px;
        font-size: 13px;
    }
    
    .signature-line {
        border-bottom: 2px solid #333;
        height: 40px;
        margin-bottom: 8px;
    }
    
    .signature-hint {
        font-size: 11px;
        color: #666;
        font-style: italic;
    }
    
    .button-group {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-top: 32px;
        margin-bottom: 24px;
    }
    
    .btn {
        padding: 12px 28px;
        font-size: 14px;
        font-weight: 600;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .btn-primary {
        background: #007bff;
        color: white;
    }
    
    .btn-primary:hover {
        background: #0056b3;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,123,255,0.3);
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #545b62;
    }
    
    .disposition-box {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 16px;
        margin-bottom: 16px;
    }
    
    .disposition-title {
        font-weight: 600;
        margin-bottom: 12px;
        color: #333;
    }
    
    .checkbox-group {
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
    }
    
    .checkbox-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-weight: normal;
        margin-bottom: 0;
    }
    
    .checkbox-group input[type="checkbox"] {
        cursor: pointer;
        width: 18px;
        height: 18px;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .signature-section {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
function openIIRUSPExport() {
    const entityName = document.getElementById('entity_name').value.trim();
    const reportDate = document.getElementById('report_date').value;
    
    if (!entityName || !reportDate) {
        alert('Please fill in Entity Name and Date fields');
        return;
    }
    
    const params = new URLSearchParams({
        entity_name: entityName,
        fund_cluster: document.getElementById('fund_cluster').value.trim(),
        accountable_name: document.getElementById('accountable_name').value.trim(),
        report_date: reportDate,
        iirusp_no: document.getElementById('iirusp_no').value.trim()
    });
    window.open('./iirusp_export.php?' + params.toString(), '_blank');
}

// Auto-calculate totals and carrying amounts
function calculateTotals() {
    const rows = document.querySelectorAll('.editable-row');
    rows.forEach((row, idx) => {
        const qtyInput = row.querySelector('.qty');
        const unitCostInput = row.querySelector('.unit-cost');
        const totalCostInput = row.querySelector('.total-cost');
        const impairmentInput = row.querySelector('[name="item_impairment[]"]');
        const carryingAmtInput = row.querySelector('.carrying-amt');
        const appraisedInput = row.querySelector('[name="item_appraised_value[]"]');
        const disposalTotalInput = row.querySelector('.disposal-total');
        
        if (qtyInput && unitCostInput && totalCostInput) {
            const qty = parseFloat(qtyInput.value) || 0;
            const unitCost = parseFloat(unitCostInput.value) || 0;
            const totalCost = qty * unitCost;
            totalCostInput.value = totalCost.toFixed(2);
            
            // Calculate carrying amount (Total Cost - Accumulated Impairment)
            if (carryingAmtInput && impairmentInput) {
                const impairment = parseFloat(impairmentInput.value) || 0;
                const carryingAmt = totalCost - impairment;
                carryingAmtInput.value = carryingAmt.toFixed(2);
            }
            
            // Disposal total = Appraised Value or use Total Cost if not specified
            if (disposalTotalInput && appraisedInput) {
                const appraised = parseFloat(appraisedInput.value);
                if (!isNaN(appraised) && appraised > 0) {
                    disposalTotalInput.value = appraised.toFixed(2);
                } else {
                    disposalTotalInput.value = totalCost.toFixed(2);
                }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // Add change listeners to all input fields
    const inputs = document.querySelectorAll('.item-input');
    inputs.forEach(input => {
        input.addEventListener('change', calculateTotals);
        input.addEventListener('keyup', () => {
            if (input.classList.contains('qty') || input.classList.contains('unit-cost') || input.classList.contains('item-impairment')) {
                calculateTotals();
            }
        });
    });
    
    // Listen for appraised value changes
    const appraisedInputs = document.querySelectorAll('[name="item_appraised_value[]"]');
    appraisedInputs.forEach(input => {
        input.addEventListener('change', calculateTotals);
        input.addEventListener('keyup', calculateTotals);
    });
    
    // Initial calculation
    calculateTotals();
});
</script>

<div class="iirusp-container">
    <div class="iirusp-header">
        <h1>📋 Inventory and Inspection Report</h1>
        <h2 style="font-size: 16px; font-weight: normal; color: #666; margin: 8px 0 0 0;">of Unserviceable Semi-Expendable Property (IIRUSP)</h2>
        <p>Fill in the report details below and select items for inspection and disposal</p>
    </div>

    <form method="POST" action="">
        <!-- Report Header Section -->
        <div class="form-section">
            <div class="form-section-title">📝 Report Information</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="entity_name">Entity Name *</label>
                    <input type="text" id="entity_name" name="entity_name" placeholder="e.g., Department of Education" required>
                </div>
                <div class="form-group">
                    <label for="fund_cluster">Fund Cluster</label>
                    <input type="text" id="fund_cluster" name="fund_cluster" placeholder="e.g., 101">
                </div>
            </div>
            
            <div class="form-row full">
                <div class="form-group">
                    <label for="accountable_name">Name of Accountable Officer / Designation / Station</label>
                    <input type="text" id="accountable_name" name="accountable_name" placeholder="e.g., John Doe / Supply Officer / Main Office">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="report_date">Report Date *</label>
                    <input type="date" id="report_date" name="report_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label for="iirusp_no">IIRUSP Reference No.</label>
                    <input type="text" id="iirusp_no" name="iirusp_no" placeholder="e.g., 2025-01-001">
                </div>
            </div>
        </div>

        <!-- Items Section -->
        <div class="form-section">
            <div class="form-section-title">� Inventory & Inspection and Disposal (18 Items)</div>
            
            <div style="margin-bottom: 12px; padding: 12px; background: #e7f3ff; border-left: 4px solid #007bff; border-radius: 4px;">
                <p style="margin: 0; font-size: 13px; color: #004085;">
                    <strong>ℹ️ Instructions:</strong> Fill in each row with item details. Check boxes in the DISPOSAL section for Sale, Transfer, or Destruction. All columns are editable.
                </p>
            </div>

            <div class="table-wrapper">
                <table class="iirusp-table" id="iirusp-table">
                    <thead>
                        <tr>
                            <th colspan="10" style="text-align: center; background: #0056b3; border-right: 2px solid #fff;">INVENTORY</th>
                            <th colspan="8" style="text-align: center; background: #0056b3;">INSPECTION and DISPOSAL</th>
                        </tr>
                        <tr>
                            <!-- INVENTORY COLUMNS -->
                            <th style="width:3%; min-width:28px;">#</th>
                            <th style="width:9%; min-width:75px;">Date Acquired</th>
                            <th style="width:14%; min-width:110px;">Particulars / Articles</th>
                            <th style="width:9%; min-width:85px;">Semi-Expendable Property No.</th>
                            <th style="width:4%; min-width:40px;">Qty</th>
                            <th style="width:7%; min-width:65px;">Unit Cost</th>
                            <th style="width:7%; min-width:65px;">Total Cost</th>
                            <th style="width:8%; min-width:75px;">Accum. Impair.</th>
                            <th style="width:8%; min-width:75px;">Carrying Amt</th>
                            <th style="width:11%; min-width:90px;">Remarks</th>
                            <!-- DISPOSAL COLUMNS -->
                            <th style="width:5%; min-width:45px;">Sale</th>
                            <th style="width:6%; min-width:50px;">Transfer</th>
                            <th style="width:7%; min-width:60px;">Destruction</th>
                            <th style="width:9%; min-width:75px;">Others (Specify)</th>
                            <th style="width:5%; min-width:45px;">Total</th>
                            <th style="width:7%; min-width:60px;">Appraised Value</th>
                            <th style="width:6%; min-width:55px;">OR No.</th>
                            <th style="width:6%; min-width:55px;">Assessor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Show database items first
                        $item_no = 0;
                        if (!empty($items)) {
                            foreach ($items as $row): 
                                $item_no++;
                                if ($item_no > 18) break; // Limit to 18 rows
                                $date = $row['date'] ?? '';
                                $desc = $row['item_description'] ?? '';
                                $prop = $row['semi_expendable_property_no'] ?? '';
                                $qty = $row['quantity_balance'] ?? 0;
                                $unit_cost = isset($row['amount_total']) ? number_format((float)$row['amount_total'],2) : '0.00';
                                $total = isset($row['amount_total']) ? number_format((float)$row['amount_total'] * (float)$qty,2) : '0.00';
                        ?>
                            <tr class="editable-row" data-item="<?= $item_no ?>">
                                <!-- INVENTORY CELLS -->
                                <td class="col-center" style="font-weight: 600; background: #f0f0f0; border-right: 2px solid #dee2e6;"><?= $item_no ?></td>
                                <td><input type="date" class="item-input" name="item_date[]" value="<?= htmlspecialchars($date) ?>"></td>
                                <td><input type="text" class="item-input" name="item_description[]" value="<?= htmlspecialchars($desc) ?>" placeholder="Item description"></td>
                                <td><input type="text" class="item-input" name="item_property_no[]" value="<?= htmlspecialchars($prop) ?>" placeholder="Property #"></td>
                                <td><input type="number" class="item-input qty" name="item_qty[]" value="<?= htmlspecialchars((string)$qty) ?>" placeholder="0"></td>
                                <td><input type="number" class="item-input unit-cost" name="item_unit_cost[]" value="<?= $unit_cost ?>" placeholder="0.00" step="0.01"></td>
                                <td><input type="text" class="item-input total-cost" name="item_total_cost[]" value="<?= $total ?>" placeholder="0.00" readonly></td>
                                <td><input type="number" class="item-input item-impairment" name="item_impairment[]" placeholder="0.00" step="0.01"></td>
                                <td><input type="text" class="item-input carrying-amt" name="item_carrying_amt[]" placeholder="0.00" readonly style="border-right: 2px solid #dee2e6;"></td>
                                <td><input type="text" class="item-input" name="item_remarks[]" value="<?= htmlspecialchars($row['remarks'] ?? '') ?>" placeholder="Remarks"></td>
                                <!-- DISPOSAL CELLS -->
                                <td class="col-center"><input type="checkbox" class="disposal-check" name="item_disposal_sale[]"></td>
                                <td class="col-center"><input type="checkbox" class="disposal-check" name="item_disposal_transfer[]"></td>
                                <td class="col-center"><input type="checkbox" class="disposal-check" name="item_disposal_destruction[]"></td>
                                <td><input type="text" class="item-input" name="item_disposal_other[]" placeholder="Specify"></td>
                                <td><input type="text" class="item-input disposal-total" name="item_disposal_total[]" placeholder="0.00" readonly></td>
                                <td><input type="number" class="item-input" name="item_appraised_value[]" placeholder="0.00" step="0.01"></td>
                                <td><input type="text" class="item-input" name="item_or_no[]" placeholder="OR No."></td>
                                <td><input type="text" class="item-input" name="item_assessor[]" placeholder="Assessor name"></td>
                            </tr>
                        <?php endforeach;
                        }
                        
                        // Fill remaining rows (up to 18 total)
                        for ($i = $item_no; $i < 18; $i++):
                        ?>
                            <tr class="editable-row" data-item="<?= $i + 1 ?>">
                                <!-- INVENTORY CELLS -->
                                <td class="col-center" style="font-weight: 600; background: #f0f0f0; border-right: 2px solid #dee2e6;"><?= $i + 1 ?></td>
                                <td><input type="date" class="item-input" name="item_date[]"></td>
                                <td><input type="text" class="item-input" name="item_description[]" placeholder="Item description"></td>
                                <td><input type="text" class="item-input" name="item_property_no[]" placeholder="Property #"></td>
                                <td><input type="number" class="item-input qty" name="item_qty[]" placeholder="0"></td>
                                <td><input type="number" class="item-input unit-cost" name="item_unit_cost[]" placeholder="0.00" step="0.01"></td>
                                <td><input type="text" class="item-input total-cost" name="item_total_cost[]" placeholder="0.00" readonly></td>
                                <td><input type="number" class="item-input item-impairment" name="item_impairment[]" placeholder="0.00" step="0.01"></td>
                                <td><input type="text" class="item-input carrying-amt" name="item_carrying_amt[]" placeholder="0.00" readonly style="border-right: 2px solid #dee2e6;"></td>
                                <td><input type="text" class="item-input" name="item_remarks[]" placeholder="Remarks"></td>
                                <!-- DISPOSAL CELLS -->
                                <td class="col-center"><input type="checkbox" class="disposal-check" name="item_disposal_sale[]"></td>
                                <td class="col-center"><input type="checkbox" class="disposal-check" name="item_disposal_transfer[]"></td>
                                <td class="col-center"><input type="checkbox" class="disposal-check" name="item_disposal_destruction[]"></td>
                                <td><input type="text" class="item-input" name="item_disposal_other[]" placeholder="Specify"></td>
                                <td><input type="text" class="item-input disposal-total" name="item_disposal_total[]" placeholder="0.00" readonly></td>
                                <td><input type="number" class="item-input" name="item_appraised_value[]" placeholder="0.00" step="0.01"></td>
                                <td><input type="text" class="item-input" name="item_or_no[]" placeholder="OR No."></td>
                                <td><input type="text" class="item-input" name="item_assessor[]" placeholder="Assessor name"></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Inspection & Disposition Section -->
        <div class="form-section">
            <div class="form-section-title">🏷️ Inspection & Disposition Certification</div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Left Column: Request -->
                <div style="border: 1px solid #ccc; padding: 16px; border-radius: 4px;">
                    <div style="font-size: 12px; line-height: 1.6; margin-bottom: 20px;">
                        <strong>I HEREBY request inspection and disposition, pursuant to Section 79 of P.D. No. 1445, of the property enumerated above.</strong>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="border-bottom: 1px solid #000; min-height: 35px; margin-bottom: 4px;"></div>
                        <div style="font-size: 11px; text-align: center; font-style: italic;">Signature over Printed Name of<br>Accountable Officer</div>
                    </div>
                    
                    <div>
                        <div style="border-bottom: 1px solid #000; min-height: 25px; margin-bottom: 4px;"></div>
                        <div style="font-size: 11px; text-align: center; font-style: italic;">Designation of Accountable Officer</div>
                    </div>
                </div>
                
                <!-- Middle Column: Inspection Officer Certification -->
                <div style="border: 1px solid #ccc; padding: 16px; border-radius: 4px;">
                    <div style="font-size: 12px; line-height: 1.6; margin-bottom: 20px;">
                        <strong>I CERTIFY that I have inspected each and every article enumerated in this report, and that the disposition made thereof was, in my judgment, the best for the public interest.</strong>
                    </div>
                    
                    <div>
                        <div style="border-bottom: 1px solid #000; min-height: 35px; margin-bottom: 4px;"></div>
                        <div style="font-size: 11px; text-align: center; font-style: italic;">Signature over Printed Name of<br>Inspection Officer</div>
                    </div>
                </div>
                
                <!-- Right Column: Witness Certification -->
                <div style="border: 1px solid #ccc; padding: 16px; border-radius: 4px;">
                    <div style="font-size: 12px; line-height: 1.6; margin-bottom: 20px;">
                        <strong>I CERTIFY that I have witnessed the disposition of the articles enumerated on this report this _____ day of __________.</strong>
                    </div>
                    
                    <div>
                        <div style="border-bottom: 1px solid #000; min-height: 35px; margin-bottom: 4px;"></div>
                        <div style="font-size: 11px; text-align: center; font-style: italic;">Signature over Printed Name of<br>Witness</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="button-group">
            <button type="button" class="btn btn-primary" onclick="openIIRUSPExport()">📄 Export to Printable PDF</button>
            <button type="reset" class="btn btn-secondary">🔄 Clear Form</button>
        </div>
    </form>
</div>

</body>
</html>
