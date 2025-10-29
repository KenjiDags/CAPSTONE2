let searchTimeout;
let stockCheckTimeout;

// Search functionality
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const filter = this.value.toLowerCase();
        const table = document.getElementById('inventoryTable');
        const rows = table.getElementsByTagName('tr');

        for (let i = 1; i < rows.length; i++) {
            const cells = rows[i].getElementsByTagName('td');
            if (cells.length > 0) {
                const stockNumber = cells[0].textContent.toLowerCase();
                const item_name = cells[1].textContent.toLowerCase();
                const description = cells[2].textContent.toLowerCase();
                const unit = cells[3].textContent.toLowerCase();

                if (stockNumber.includes(filter) || item_name.includes(filter) || description.includes(filter) || unit.includes(filter)) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    }, 300);
});

// Stock number check functionality
document.getElementById('add_stock_number').addEventListener('input', function() {
    const stockNumber = this.value.trim();
    const statusDiv = document.getElementById('stock_status');
    
    // Clear previous timeout
    clearTimeout(stockCheckTimeout);
    
    if (stockNumber === '') {
        clearAddForm();
        statusDiv.innerHTML = '';
        return;
    }
    
    // Set timeout for stock check
    stockCheckTimeout = setTimeout(() => {
        checkStockNumber(stockNumber);
    }, 500);
});

function checkStockNumber(stockNumber) {
    const statusDiv = document.getElementById('stock_status');
    statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking stock number...';
    
    fetch('?action=check_stock', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `stock_number=${encodeURIComponent(stockNumber)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.exists) {
            // Stock number exists - populate fields but allow unit cost to be editable
            statusDiv.innerHTML = '<i class="fas fa-info-circle text-info"></i> Existing item found. You can add different Unit Cost and/or Quantity.';
            statusDiv.className = 'stock-status info';
            
            document.getElementById('add_item_name').value = data.item.item_name;
            document.getElementById('add_description').value = data.item.description;
            document.getElementById('add_unit').value = data.item.unit;
            document.getElementById('add_reorder_point').value = data.item.reorder_point;
            document.getElementById('add_unit_cost').value = data.item.unit_cost;
            document.getElementById('add_iar').value = data.item.iar;
            
            
            // Make only description, unit, and reorder_point readonly
            document.getElementById('add_item_name').readOnly = true;
            document.getElementById('add_description').readOnly = true;
            document.getElementById('add_unit').readOnly = true;
            document.getElementById('add_reorder_point').readOnly = true;
            document.getElementById('add_unit_cost').readOnly = false; // Allow editing unit cost
            document.getElementById('add_iar').readOnly = true;
            
            
            // Keep quantity field editable but clear it
            document.getElementById('add_quantity_on_hand').value = '';
            document.getElementById('add_quantity_on_hand').focus();
        } else {
            // Stock number doesn't exist - enable all fields for new item
            statusDiv.innerHTML = '<i class="fas fa-plus-circle text-success"></i> New item - fill in all details.';
            statusDiv.className = 'stock-status success';
            
            enableAddFormFields();
        }
    })
    .catch(error => {
        statusDiv.innerHTML = '<i class="fas fa-exclamation-triangle text-error"></i> Error checking stock number.';
        statusDiv.className = 'stock-status error';
        console.error('Error:', error);
    });
}

function clearAddForm() {
    // Clear all form fields except stock number
    document.getElementById('add_item_name').value = '';
    document.getElementById('add_description').value = '';
    document.getElementById('add_unit').value = '';
    document.getElementById('add_reorder_point').value = '';
    document.getElementById('add_unit_cost').value = '';
    document.getElementById('add_quantity_on_hand').value = '';
    
    // Make fields readonly
    document.getElementById('add_item_name').readOnly = true;
    document.getElementById('add_description').readOnly = true;
    document.getElementById('add_unit').readOnly = true;
    document.getElementById('add_reorder_point').readOnly = true;
    document.getElementById('add_unit_cost').readOnly = true; // This will be changed when stock exists
    document.getElementById('add_iar').readOnly = true;

}

function enableAddFormFields() {
    // Enable all fields for new item entry
    document.getElementById('add_item_name').readOnly = false;
    document.getElementById('add_description').readOnly = false;
    document.getElementById('add_unit').readOnly = false;
    document.getElementById('add_reorder_point').readOnly = false;
    document.getElementById('add_unit_cost').readOnly = false; // Keep this enabled
    document.getElementById('add_iar').readOnly = false;

    
    // Clear fields
    document.getElementById('add_item_name').value = '';
    document.getElementById('add_description').value = '';
    document.getElementById('add_unit').value = '';
    document.getElementById('add_reorder_point').value = '';
    document.getElementById('add_unit_cost').value = '';
    document.getElementById('add_quantity_on_hand').value = '';
    document.getElementById('add_iar').value = '';

}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
    // Reset form when closing
    document.getElementById('addForm').reset();
    clearAddForm();
    document.getElementById('stock_status').innerHTML = '';
}

// Add item form submission
document.getElementById('addForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('?action=add', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        return response.text().then(text => {
            console.log('Raw response:', text); // This will help debug
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', text);
                throw new Error('Invalid JSON response');
            }
        });
    })
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            
            if (data.updated) {
                // Refresh the page to show updated quantity
                location.reload();
            } else if (data.item) {
                // Add new row to table
                addRowToTable(data.item);
            }
            
            closeAddModal();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while adding the item.', 'error');
        console.error('Error:', error);
    });
});

// Enhanced edit form submission with selective field updates
document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Get current form values
    const currentValues = {
        stock_number: document.getElementById('edit_stock_number').value.trim(),
        item_name: document.getElementById('edit_item_name').value.trim(),
        description: document.getElementById('edit_description').value.trim(),
        unit: document.getElementById('edit_unit').value.trim(),
        reorder_point: parseInt(document.getElementById('edit_reorder_point').value),
        unit_cost: parseFloat(document.getElementById('edit_unit_cost').value),
        quantity_on_hand: parseInt(document.getElementById('edit_quantity_on_hand').value),
        iar: document.getElementById('edit_iar').value.trim()
    };
    
    // Get original values (stored when modal was opened)
    const originalValues = {
        stock_number: document.getElementById('edit_stock_number').dataset.originalValue || '',
        item_name: document.getElementById('edit_item_name').dataset.originalValue || '',
        description: document.getElementById('edit_description').dataset.originalValue || '',
        unit: document.getElementById('edit_unit').dataset.originalValue || '',
        reorder_point: parseInt(document.getElementById('edit_reorder_point').dataset.originalValue || '0'),
        unit_cost: parseFloat(document.getElementById('edit_unit_cost').dataset.originalValue || '0'),
        quantity_on_hand: parseInt(document.getElementById('edit_quantity_on_hand').dataset.originalValue || '0'),
        iar: document.getElementById('edit_iar').dataset.originalValue || ''

    };
    
    // Identify which fields have changed
    const changedFields = {};
    let hasChanges = false;
    
    Object.keys(currentValues).forEach(key => {
        let isChanged = false;
        
        if (key === 'unit_cost') {
            // Handle floating point comparison with small tolerance
            isChanged = Math.abs(currentValues[key] - originalValues[key]) > 0.001;
        } else if (key === 'reorder_point' || key === 'quantity_on_hand') {
            // Handle integer comparison
            isChanged = currentValues[key] !== originalValues[key];
        } else {
            // Handle string comparison
            isChanged = currentValues[key] !== originalValues[key];
        }
        
        if (isChanged) {
            changedFields[key] = currentValues[key];
            hasChanges = true;
        }
    });
    
    // If no changes detected, just close the modal
    if (!hasChanges) {
        showNotification('No changes detected. Nothing to update.', 'info');
        document.getElementById('editModal').style.display = 'none';
        return;
    }
    
    // Check if this is an item with multiple entries and if critical fields changed
    const itemId = document.getElementById('edit_item_id').value;
    const note = document.getElementById('multiple-entries-note');
    const criticalFieldsChanged = changedFields.hasOwnProperty('unit_cost') || changedFields.hasOwnProperty('quantity_on_hand');
    
    if (note && criticalFieldsChanged) {
        // Show confirmation only when critical fields are changed
        const confirmed = confirm(
            'WARNING: You have changed the quantity or unit cost.\n\n' +
            'This will PERMANENTLY DELETE all inventory entries and use the new values as base values.\n\n' +
            'This action cannot be undone. Are you sure you want to continue?'
        );
        
        if (!confirmed) {
            return; // User cancelled
        }
    }
    
    // Create FormData with changed fields
    const formData = new FormData();
    formData.append('item_id', itemId);
    
    // Special handling for items with multiple entries:
    // If only quantity changed but unit cost didn't, we need to include both
    // because when entries are cleared, both need to be updated together
    if (note && changedFields.hasOwnProperty('quantity_on_hand') && !changedFields.hasOwnProperty('unit_cost')) {
        // Include the current unit cost value even if it didn't change
        changedFields['unit_cost'] = currentValues['unit_cost'];
        console.log('Added unit_cost to update because quantity changed on item with multiple entries');
    }
    
    // Add all changed fields (and unit_cost if needed for multiple entries scenario)
    Object.keys(changedFields).forEach(field => {
        formData.append(field, changedFields[field]);
    });
    
    // Add a field to indicate this is a selective update
    formData.append('selective_update', 'true');
    
    // Show which fields are being updated
    const fieldNames = Object.keys(changedFields).map(field => field.replace('_', ' ')).join(', ');
    console.log(`Updating fields: ${fieldNames}`);
    
    fetch('?action=update', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        return response.text().then(text => {
            console.log('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', text);
                throw new Error('Invalid JSON response');
            }
        });
    })
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            document.getElementById('editModal').style.display = 'none';
            
            // Refresh the page to show updated data
            setTimeout(() => {
                location.reload();
            }, 1000); // Give user time to see the success message
            
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while updating the item.', 'error');
        console.error('Error:', error);
    });
});

// Modified openEditModal function to store original values
function openEditModal(button) {
    const modal = document.getElementById('editModal');
    const itemId = button.dataset.id;
    
    // Set form values
    document.getElementById('edit_item_id').value = itemId;
    document.getElementById('edit_stock_number').value = button.dataset.stock_number;
    document.getElementById('edit_item_name').value = button.dataset.item_name;
    document.getElementById('edit_description').value = button.dataset.description;
    document.getElementById('edit_unit').value = button.dataset.unit;
    document.getElementById('edit_reorder_point').value = button.dataset.reorder_point;
    document.getElementById('edit_unit_cost').value = parseFloat(button.dataset.unit_cost).toFixed(2);
    document.getElementById('edit_quantity_on_hand').value = button.dataset.quantity_on_hand;
    document.getElementById('edit_iar').value = button.dataset.iar;
    
    // Store original values in data attributes for change detection
    document.getElementById('edit_stock_number').dataset.originalValue = button.dataset.stock_number;
    document.getElementById('edit_item_name').dataset.originalValue = button.dataset.item_name;
    document.getElementById('edit_description').dataset.originalValue = button.dataset.description;
    document.getElementById('edit_unit').dataset.originalValue = button.dataset.unit;
    document.getElementById('edit_reorder_point').dataset.originalValue = button.dataset.reorder_point;
    document.getElementById('edit_unit_cost').dataset.originalValue = parseFloat(button.dataset.unit_cost).toFixed(2);
    document.getElementById('edit_quantity_on_hand').dataset.originalValue = button.dataset.quantity_on_hand;
    document.getElementById('edit_iar').dataset.originalValue = button.dataset.iar;
    
    // Check if item has multiple entries
    checkMultipleEntries(itemId);
    
    modal.style.display = 'block';
}


function checkMultipleEntries(itemId) {
    fetch('?action=check_entries', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `item_id=${itemId}`
    })
    .then(response => response.json())
    .then(data => {
        const unitCostField = document.getElementById('edit_unit_cost');
        const quantityField = document.getElementById('edit_quantity_on_hand');
        const unitCostLabel = unitCostField.previousElementSibling;
        const quantityLabel = quantityField.previousElementSibling;
        
        // Remove any existing warning note
        const existingNote = document.getElementById('multiple-entries-note');
        if (existingNote) existingNote.remove();
        
        if (data.hasMultipleEntries) {
            // Show fields normally
            unitCostField.style.display = '';
            quantityField.style.display = '';
            unitCostLabel.style.display = '';
            quantityLabel.style.display = '';
            
            // Enable fields
            unitCostField.readOnly = false;
            quantityField.readOnly = false;
            unitCostField.style.backgroundColor = '';
            quantityField.style.backgroundColor = '';
            
            // Store original values for comparison
            unitCostField.dataset.originalValue = unitCostField.value;
            quantityField.dataset.originalValue = quantityField.value;
            
            // Add a warning note that updates based on changes
            const note = document.createElement('div');
            note.id = 'multiple-entries-note';
            note.innerHTML = `
                <div style="color: #0984e3; font-weight: bold; margin: 15px 0; padding: 15px; background: #e3f2fd; border: 2px solid #81c784; border-radius: 8px;">
                    <i class="fas fa-info-circle"></i> <strong>NOTICE:</strong> 
                    <p style="margin: 5px 0 0 0;">This item has <strong>${data.entryCount}</strong> inventory entries plus initial stock.</p>
                    <p style="margin: 5px 0 0 0;" id="edit-behavior-text">• You can safely edit other fields (stock number, name, description, etc.) without affecting entries.</p>
                    <p style="margin: 5px 0 0 0; color: #d63031;" id="warning-text" style="display: none;"><strong>⚠️ Changing quantity or unit cost will permanently delete ALL entries!</strong></p>
                </div>
            `;
            quantityField.parentNode.insertBefore(note, quantityField.nextSibling);
            
            // Add event listeners to show/hide warning based on changes
            const updateWarning = () => {
                const costChanged = parseFloat(unitCostField.value) !== parseFloat(unitCostField.dataset.originalValue);
                const quantityChanged = parseInt(quantityField.value) !== parseInt(quantityField.dataset.originalValue);
                const warningText = document.getElementById('warning-text');
                const noteDiv = note.querySelector('div');
                
                if (costChanged || quantityChanged) {
                    warningText.style.display = 'block';
                    noteDiv.style.borderColor = '#d63031';
                    noteDiv.style.backgroundColor = '#ffeaa7';
                    noteDiv.style.color = '#d63031';
                } else {
                    warningText.style.display = 'none';
                    noteDiv.style.borderColor = '#81c784';
                    noteDiv.style.backgroundColor = '#e3f2fd';
                    noteDiv.style.color = '#0984e3';
                }
            };
            
            unitCostField.addEventListener('input', updateWarning);
            quantityField.addEventListener('input', updateWarning);
            
        } else {
            // Show fields normally for items without multiple entries
            unitCostField.style.display = '';
            quantityField.style.display = '';
            unitCostLabel.style.display = '';
            quantityLabel.style.display = '';
            
            // Enable fields
            unitCostField.readOnly = false;
            quantityField.readOnly = false;
            unitCostField.style.backgroundColor = '';
            quantityField.style.backgroundColor = '';
        }
    })
    .catch(error => {
        console.error('Error checking entries:', error);
    });
}


function deleteItem(id) {
    if (confirm('Are you sure you want to delete this item?')) {
        fetch('?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                removeRowFromTable(id);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('An error occurred while deleting the item.', 'error');
            console.error('Error:', error);
        });
    }
}

function addRowToTable(item) {
    const table = document.getElementById('inventoryTable').getElementsByTagName('tbody')[0];
    const totalCost = item.quantity_on_hand * item.unit_cost;
    const statusClass = item.quantity_on_hand <= item.reorder_point ? 'status-low' : 'status-normal';
    
    const newRow = table.insertRow();
    newRow.setAttribute('data-id', item.item_id);
    newRow.innerHTML = `
        <td><strong>${item.stock_number}</strong></td>
        <td>${item.item_name}</td>
        <td>${item.description}</td>
        <td>${item.unit}</td>
        <td class="quantity-cell">
            <div class="main-quantity">
                <span class="${statusClass}">${item.total_quantity || item.quantity_on_hand}</span>
            </div>
            <div class="sub-entries" id="sub-entries-${item.item_id}">
                <!-- Will be populated on page reload -->
            </div>
        </td>
        <td class="cost-cell">
            <div class="main-cost">₱ ${Number(item.calculated_unit_cost || item.average_unit_cost || item.unit_cost).toLocaleString('en-PH', {minimumFractionDigits: 2})}${(item.calculated_unit_cost || item.has_multiple_entries) ? ' (average)' : ''}</div>
        </td>
        <td class="currency">₱ ${totalCost.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
        <td>${item.reorder_point}</td>
        <td>${new Date(item.created_at).toLocaleDateString('en-PH', {month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'})}</td>
        <td>
            <button class='btn edit-btn' onclick='openEditModal(this)'
                data-id='${item.item_id}'
                data-stock_number='${item.stock_number}'
                data-item_name='${item.item_name}'
                data-description='${item.description}'
                data-unit='${item.unit}'
                data-reorder_point='${item.reorder_point}'
                data-unit_cost='${item.unit_cost}'
                data-quantity_on_hand='${item.quantity_on_hand}'
                title='Edit Item'>
                <i class='fas fa-edit'></i> Edit
            </button>
            <button class='btn delete-btn' onclick='deleteItem(${item.item_id})' title='Delete Item'>
                <i class='fas fa-trash'></i> Delete
            </button>
        </td>
    `;
}

function updateRowInTable(item) {
    const row = document.querySelector(`tr[data-id="${item.item_id}"]`);
    if (row) {
        const totalCost = item.quantity_on_hand * item.unit_cost;
        const statusClass = item.quantity_on_hand <= item.reorder_point ? 'status-low' : 'status-normal';
        
        row.innerHTML = `
            <td><strong>${item.stock_number}</strong></td>
            <td>${item.item_name}</td>
            <td>${item.description}</td>
            <td>${item.unit}</td>
            <td class="quantity-cell">
            <div class="main-quantity">
                <span class="${statusClass}">${item.quantity_on_hand}</span>
            </div>
            <div class="sub-entries" id="sub-entries-${item.item_id}">
                <!-- Sub-entries will be preserved -->
            </div>
        </td>
            <td class="cost-cell">
                <div class="main-cost">₱ ${Number(item.calculated_unit_cost || item.average_unit_cost || item.unit_cost).toLocaleString('en-PH', {minimumFractionDigits: 2})}${(item.calculated_unit_cost || item.has_multiple_entries) ? ' (average)' : ''}</div>
                <div class="sub-entries" id="sub-cost-${item.item_id}"></div>
            </td>            <td class="currency">₱ ${totalCost.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
            <td>${item.reorder_point}</td>
            <td>${new Date().toLocaleDateString('en-PH', {month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'})}</td>
            <td>
                <button class='btn edit-btn' onclick='openEditModal(this)'
                    data-id='${item.item_id}'
                    data-stock_number='${item.stock_number}'
                    data-item_name='${item.item_name}'
                    data-description='${item.description}'
                    data-unit='${item.unit}'
                    data-reorder_point='${item.reorder_point}'
                    data-unit_cost='${item.unit_cost}'
                    data-quantity_on_hand='${item.quantity_on_hand}'
                    title='Edit Item'>
                    <i class='fas fa-edit'></i> Edit
                </button>
                <button class='btn delete-btn' onclick='deleteItem(${item.item_id})' title='Delete Item'>
                    <i class='fas fa-trash'></i> Delete
                </button>
            </td>
        `;
    }
}

function removeRowFromTable(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (row) {
        row.remove();
    }
}

// Enhanced showNotification function to handle 'info' type
function showNotification(message, type) {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = `notification ${type} show`;
    
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}
function clearEntries(itemId) {
    if (confirm('Are you sure you want to clear all inventory entries? This will reset the item to its initial quantity and cost.')) {
        fetch('?action=clear_entries', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `item_id=${itemId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                
                // Refresh the page to show updated data
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showNotification(data.message, 'error');
            }
            
            
        })
        .catch(error => {
            showNotification('An error occurred while clearing entries.', 'error');
            console.error('Error:', error);
        });
    }
}