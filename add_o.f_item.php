<?php
require 'auth.php';
require 'config.php';
require 'functions.php';
include 'sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Office Supply Item</title>
<link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
<link rel="stylesheet" href="css/PPE.css?v=<?= time() ?>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    .form-container {
        max-width: 800px;
        margin: 30px auto;
        background: rgba(255, 255, 255, 0.95);
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        backdrop-filter: blur(10px);
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .alert {
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        display: none;
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .stock-status {
        margin-top: 8px;
        margin-bottom: 12px;
        font-size: 13px;
        font-weight: 600;
        display: none;
        border: none !important;
        padding: 0 !important;
        background: transparent !important;
    }
    .stock-status.info { color: #1d4ed8; }
    .stock-status.success { color: #15803d; }
    .stock-status.error { color: #b91c1c; }
    .required {
        color: #dc2626;
    }
    .actions-row {
        margin-top: 20px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
</style>
</head>
<body>
<div class="container">
    <div class="form-container">
        <header style="margin-bottom: 30px; border-bottom: 3px solid #3b82f6; padding-bottom: 15px;">
            <h1 style="margin: 0 0 8px 0; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-plus-circle" style="color: #3b82f6;"></i> Add New Office Supply Item
            </h1>
            <p style="color: #64748b; margin: 0;">Create a new item or add stock to an existing stock number.</p>
        </header>

        <div id="formAlert" class="alert"></div>

        <form id="addForm" autocomplete="off">
            <div class="form-row">
                <div class="form-group">
                    <label for="stock_number">Stock Number <span class="required">*</span></label>
                    <input type="text" name="stock_number" id="stock_number" placeholder="Enter stock number" required>
                    <div id="stock_status" class="stock-status"></div>
                </div>
                <div class="form-group">
                    <label for="iar">I.A.R <span class="required">*</span></label>
                    <input type="text" name="iar" id="iar" placeholder="Enter I.A.R" required readonly>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="item_name">Item Name <span class="required">*</span></label>
                    <input type="text" name="item_name" id="item_name" placeholder="Item name" required readonly>
                </div>
                <div class="form-group">
                    <label for="description">Description <span class="required">*</span></label>
                    <input type="text" name="description" id="description" placeholder="Description" required readonly>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="unit">Unit <span class="required">*</span></label>
                    <input type="text" name="unit" id="unit" placeholder="pcs, box, etc." required readonly>
                </div>
                <div class="form-group">
                    <label for="reorder_point">Reorder Point <span class="required">*</span></label>
                    <input type="number" name="reorder_point" id="reorder_point" min="0" required readonly>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="unit_cost">Unit Cost (PHP) <span class="required">*</span></label>
                    <input type="number" step="0.01" name="unit_cost" id="unit_cost" min="0" required readonly>
                </div>
                <div class="form-group">
                    <label for="quantity_on_hand">Quantity on Hand <span class="required">*</span></label>
                    <input type="number" name="quantity_on_hand" id="quantity_on_hand" min="0" required>
                </div>
            </div>

            <div class="actions-row">
                <button type="submit" class="pill-btn pill-add"><i class="fas fa-save"></i> Save Item</button>
                <a href="inventory.php" class="pill-btn pill-view" style="text-decoration: none;"><i class="fas fa-ban"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
let stockCheckTimeout;

const stockInput = document.getElementById('stock_number');
const stockStatus = document.getElementById('stock_status');
const addForm = document.getElementById('addForm');
const formAlert = document.getElementById('formAlert');

const fields = {
    item_name: document.getElementById('item_name'),
    description: document.getElementById('description'),
    unit: document.getElementById('unit'),
    reorder_point: document.getElementById('reorder_point'),
    unit_cost: document.getElementById('unit_cost'),
    iar: document.getElementById('iar'),
    quantity_on_hand: document.getElementById('quantity_on_hand')
};

function showAlert(message, type) {
    // Render a success/error banner above the form.
    formAlert.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
    formAlert.textContent = message;
    formAlert.style.display = 'block';
}

function clearAlert() {
    // Hide the alert area before new validation or submit attempts.
    formAlert.style.display = 'none';
    formAlert.textContent = '';
}

function clearAddForm() {
    // Reset item detail fields and lock them until a stock number check completes.
    fields.item_name.value = '';
    fields.description.value = '';
    fields.unit.value = '';
    fields.reorder_point.value = '';
    fields.unit_cost.value = '';
    fields.iar.value = '';
    fields.quantity_on_hand.value = '';

    fields.item_name.readOnly = true;
    fields.description.readOnly = true;
    fields.unit.readOnly = true;
    fields.reorder_point.readOnly = true;
    fields.unit_cost.readOnly = true;
    fields.iar.readOnly = true;
}

function enableForNewItem() {
    // Unlock fields when stock number is new so the user can enter full item details.
    fields.item_name.readOnly = false;
    fields.description.readOnly = false;
    fields.unit.readOnly = false;
    fields.reorder_point.readOnly = false;
    fields.unit_cost.readOnly = false;
    fields.iar.readOnly = false;

    fields.item_name.value = '';
    fields.description.value = '';
    fields.unit.value = '';
    fields.reorder_point.value = '';
    fields.unit_cost.value = '';
    fields.iar.value = '';
    fields.quantity_on_hand.value = '';
}

function checkStockNumber(stockNumber) {
    // Check whether stock number already exists and toggle form behavior accordingly.
    stockStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking stock number...';
    stockStatus.className = 'stock-status info';
    stockStatus.style.display = 'block';

    fetch('inventory.php?action=check_stock', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'stock_number=' + encodeURIComponent(stockNumber)
    })
    .then(response => response.json())
    .then(data => {
        if (data.exists) {
            stockStatus.innerHTML = '<i class="fas fa-info-circle"></i> Existing item found. You can add a new quantity and update unit cost if needed.';
            stockStatus.className = 'stock-status info';

            fields.item_name.value = data.item.item_name || '';
            fields.description.value = data.item.description || '';
            fields.unit.value = data.item.unit || '';
            fields.reorder_point.value = data.item.reorder_point || 0;
            fields.unit_cost.value = data.item.unit_cost || 0;
            fields.iar.value = data.item.iar || '';

            fields.item_name.readOnly = true;
            fields.description.readOnly = true;
            fields.unit.readOnly = true;
            fields.reorder_point.readOnly = true;
            fields.unit_cost.readOnly = false;
            fields.iar.readOnly = true;

            fields.quantity_on_hand.value = '';
            fields.quantity_on_hand.focus();
        } else {
            stockStatus.innerHTML = '<i class="fas fa-plus-circle"></i> New item. Fill in all details.';
            stockStatus.className = 'stock-status success';
            enableForNewItem();
        }
    })
    .catch(() => {
        stockStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error checking stock number.';
        stockStatus.className = 'stock-status error';
    });
}

stockInput.addEventListener('input', function() {
    // Debounce server checks while typing stock number to avoid excessive requests.
    clearAlert();
    const stockNumber = this.value.trim();
    clearTimeout(stockCheckTimeout);

    if (!stockNumber) {
        clearAddForm();
        stockStatus.innerHTML = '';
        stockStatus.className = 'stock-status';
        stockStatus.style.display = 'none';
        return;
    }

    stockCheckTimeout = setTimeout(() => checkStockNumber(stockNumber), 400);
});

addForm.addEventListener('submit', function(e) {
    // Submit through the existing inventory add endpoint and redirect on success.
    e.preventDefault();
    clearAlert();

    const formData = new FormData(addForm);

    fetch('inventory.php?action=add', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message || 'Item saved successfully.', 'success');
            setTimeout(() => {
                window.location.href = 'inventory.php';
            }, 900);
        } else {
            showAlert(data.message || 'Failed to save item.', 'error');
        }
    })
    .catch(() => {
        showAlert('An error occurred while saving the item.', 'error');
    });
});

clearAddForm();
</script>
</body>
</html>
