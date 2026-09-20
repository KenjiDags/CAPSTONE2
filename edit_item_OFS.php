<?php
require 'auth.php';
require 'config.php';
require 'functions.php';
include 'sidebar.php';

$item_id = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);
$item = null;

if ($item_id) {
    $stmt = $conn->prepare('SELECT * FROM items WHERE item_id = ? LIMIT 1');
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$item) {
    header('Location: inventory.php');
    exit;
}

$entry_stmt = $conn->prepare('SELECT COUNT(*) AS entry_count FROM inventory_entries WHERE item_id = ?');
$entry_stmt->bind_param('i', $item_id);
$entry_stmt->execute();
$entry_count = (int) $entry_stmt->get_result()->fetch_assoc()['entry_count'];
$entry_stmt->close();
$has_multiple_entries = (int) $item['initial_quantity'] > 0 && $entry_count > 0;

function edit_ofs_value($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Office Supply Item</title>
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
    .form-group { margin-bottom: 20px; }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
    }
    .form-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .alert {
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        display: none;
    }
    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .entries-note {
        padding: 12px 15px;
        margin-bottom: 20px;
        border: 1px solid #fde68a;
        border-radius: 4px;
        background: #fffbeb;
        color: #92400e;
        font-size: 13px;
    }
    .actions-row { margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; }
    @media (max-width: 700px) {
        .form-container { margin: 20px 10px; padding: 24px; }
        .form-row { grid-template-columns: 1fr; gap: 0; }
    }
</style>
</head>
<body>
<div class="container">
    <div class="form-container">
        <header style="margin-bottom: 30px; border-bottom: 3px solid #3b82f6; padding-bottom: 15px;">
            <h1 style="margin: 0 0 8px 0; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-edit" style="color: #3b82f6;"></i> Edit Office Supply Item
            </h1>
            <p style="color: #64748b; margin: 0;">Update the details for this inventory item.</p>
        </header>

        <div id="formAlert" class="alert"></div>
        <?php if ($has_multiple_entries): ?>
            <div class="entries-note">
                <i class="fas fa-exclamation-triangle"></i>
                This item has <?= $entry_count ?> inventory entries plus initial stock. Changing quantity or unit cost will clear all entries.
            </div>
        <?php endif; ?>

        <form id="editForm" autocomplete="off">
            <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="stock_number">Stock Number <span class="required">*</span></label>
                    <input type="text" name="stock_number" id="stock_number" value="<?= edit_ofs_value($item['stock_number']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="iar">I.A.R <span class="required">*</span></label>
                    <input type="text" name="iar" id="iar" value="<?= edit_ofs_value($item['iar']) ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="item_name">Item Name <span class="required">*</span></label>
                    <input type="text" name="item_name" id="item_name" value="<?= edit_ofs_value($item['item_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Description <span class="required">*</span></label>
                    <input type="text" name="description" id="description" value="<?= edit_ofs_value($item['description']) ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="unit">Unit <span class="required">*</span></label>
                    <input type="text" name="unit" id="unit" value="<?= edit_ofs_value($item['unit']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="reorder_point">Reorder Point <span class="required">*</span></label>
                    <input type="number" name="reorder_point" id="reorder_point" min="0" value="<?= (int) $item['reorder_point'] ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="unit_cost">Unit Cost (PHP) <span class="required">*</span></label>
                    <input type="number" step="0.01" name="unit_cost" id="unit_cost" min="0" value="<?= edit_ofs_value(number_format((float) $item['unit_cost'], 2, '.', '')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="quantity_on_hand">Quantity on Hand <span class="required">*</span></label>
                    <input type="number" name="quantity_on_hand" id="quantity_on_hand" min="0" value="<?= (int) $item['quantity_on_hand'] ?>" required>
                </div>
            </div>
            <div class="actions-row">
                <button type="submit" class="pill-btn pill-add"><i class="fas fa-save"></i> Update Item</button>
                <a href="inventory.php" class="pill-btn pill-view" style="text-decoration: none;"><i class="fas fa-ban"></i> Cancel</a>
            </div>
        </form>
    </div>
</div>
<script>
const form = document.getElementById('editForm');
const alertBox = document.getElementById('formAlert');
const itemId = form.elements.item_id.value;
const original = {
    stock_number: <?= json_encode((string) $item['stock_number']) ?>,
    iar: <?= json_encode((string) ($item['iar'] ?? '')) ?>,
    item_name: <?= json_encode((string) $item['item_name']) ?>,
    description: <?= json_encode((string) $item['description']) ?>,
    unit: <?= json_encode((string) ($item['unit'] ?? '')) ?>,
    reorder_point: <?= (int) $item['reorder_point'] ?>,
    unit_cost: <?= json_encode((float) $item['unit_cost']) ?>,
    quantity_on_hand: <?= (int) $item['quantity_on_hand'] ?>
};
const multipleEntries = <?= $has_multiple_entries ? 'true' : 'false' ?>;

function showAlert(message, type) {
    alertBox.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
    alertBox.textContent = message;
    alertBox.style.display = 'block';
}

form.addEventListener('submit', function(event) {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(form).entries());
    values.reorder_point = parseInt(values.reorder_point, 10);
    values.unit_cost = parseFloat(values.unit_cost);
    values.quantity_on_hand = parseInt(values.quantity_on_hand, 10);

    const changed = {};
    Object.keys(original).forEach(function(field) {
        const different = field === 'unit_cost'
            ? Math.abs(values[field] - original[field]) > 0.001
            : values[field] !== original[field];
        if (different) changed[field] = values[field];
    });

    if (Object.keys(changed).length === 0) {
        showAlert('No changes detected. Nothing to update.', 'error');
        return;
    }

    const criticalChanged = Object.prototype.hasOwnProperty.call(changed, 'unit_cost') || Object.prototype.hasOwnProperty.call(changed, 'quantity_on_hand');
    if (multipleEntries && criticalChanged && !confirm('Changing quantity or unit cost will permanently delete all inventory entries and use the new values as base values. Are you sure you want to continue?')) {
        return;
    }
    if (multipleEntries && Object.prototype.hasOwnProperty.call(changed, 'quantity_on_hand') && !Object.prototype.hasOwnProperty.call(changed, 'unit_cost')) {
        changed.unit_cost = values.unit_cost;
    }

    const payload = new FormData();
    payload.append('item_id', itemId);
    payload.append('selective_update', 'true');
    Object.keys(changed).forEach(field => payload.append(field, changed[field]));

    fetch('inventory.php?action=update', { method: 'POST', body: payload })
        .then(response => response.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Failed to update item.');
            window.dispatchEvent(new Event('inventory:updated'));
            showAlert(data.message || 'Item updated successfully.', 'success');
            setTimeout(() => { window.location.href = 'inventory.php'; }, 900);
        })
        .catch(error => showAlert(error.message || 'An error occurred while updating the item.', 'error'));
});
</script>
</body>
</html>
