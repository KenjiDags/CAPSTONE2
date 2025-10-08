<?php
require 'config.php';
include 'sidebar.php'
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registry of Semi-Expendable Property Issued</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="content">
        <h2>Registry of Semi-Expendable Property Issued</h2>
        
        <div class="search-container">
        <button class="add-btn" onclick="document.getElementById('addModal').style.display='block'">
                <i class="fas fa-plus"></i> Add new Item
            </button>
            <input type="text" id="searchInput" class="search-input" placeholder="Search by stock number, description, or unit...">
        </div>

        <div class="container">
            <!-- Header Form -->
            <div class="rsep-form">
                <div class="form-fields">
                    <div class="field-group">
                        <label for="entity-name">Entity Name:</label>
                        <input type="text" id="entity-name" name="entity_name" value="TESDA-CAR">
                    </div>
                    <div class="field-group">
                        <label for="fund-cluster">Fund Cluster:</label>
                        <input type="text" id="fund-cluster" name="fund_cluster" value="101">
                    </div>
                    <div class="field-group">
                        <label for="semi-expendable-property">Semi-Expendable Property:</label>
                        <select id="semi-expendable-property" name="semi_expendable_property">
                            <option value="ICT Equipment" selected>ICT Equipment</option>
                            <option value="Office Equipment">Office Equipment</option>
                            <option value="Other PPE">Other PPE</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="zoom-slider-container">
                <label for="zoomSlider">🔍 Zoom:</label>
                <input type="range" id="zoomSlider" min="50" max="200" value="100">
                <span id="zoomValue">100%</span>
            </div>

            <!-- Main Table -->
            <div class="rsep-table-container">
                <div class="rsep-table-wrapper">
                    <div class="table-zoom-wrapper">
                        <table class="rsep-table">
                            <thead>
                                <tr>
                                    <th rowspan="3">Date</th>
                                    <th colspan="2">Reference</th>
                                    <th rowspan="3">Item Description</th>
                                    <th rowspan="3">Estimated Useful Life</th>
                                    <th colspan="2">Issued</th>
                                    <th colspan="2">Returned</th>
                                    <th colspan="2">Re-issued</th>
                                    <th colspan="2">Disposed</th>
                                    <th rowspan="3">Balance Qty.</th>
                                    <th rowspan="3">Amount (TOTAL)</th>
                                    <th rowspan="3">Remarks</th>
                                </tr>
                                <tr>
                                    <th rowspan="2">ICS/RRSP No.</th>
                                    <th rowspan="2">Semi-Expendable Property No.</th>
                                    <th rowspan="2">Qty.</th>
                                    <th rowspan="2">Office/Officer</th>
                                    <th rowspan="2">Qty.</th>
                                    <th rowspan="2">Office/Officer</th>
                                    <th rowspan="2">Qty.</th>
                                    <th rowspan="2">Office/Officer</th>
                                    <th rowspan="2">Qty.</th>
                                    <th rowspan="2">Qty.</th>
                                </tr>
                                <tr>
                                    <!-- Empty row for proper header structure -->
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <!-- Sample data rows - will be populated dynamically -->
                                <tr>
                                    <td>2024-01-15</td>
                                    <td>ICS-2024-001</td>
                                    <td>SEP-ICT-001</td>
                                    <td>Desktop Computer Dell OptiPlex</td>
                                    <td>5 Years</td>
                                    <td>1</td>
                                    <td>IT Department</td>
                                    <td>0</td>
                                    <td>-</td>
                                    <td>0</td>
                                    <td>-</td>
                                    <td>0</td>
                                    <td>0</td>
                                    <td>1</td>
                                    <td>₱45,000.00</td>
                                    <td>Good condition</td>
                                </tr>
                                <!-- Empty rows will be added here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
                <div style="text-align: center; margin-top: 30px;">
                    <button type="button" class="export-btn" onclick="openExport()">📄 Export to PDF</button>
                </div>

    <!-- Modal for Adding Items -->
    <div class="modal-overlay" id="itemModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Item</h3>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <form class="modal-form" id="itemForm">
                    <div class="modal-field">
                        <label for="itemDate">Date:</label>
                        <input type="date" id="itemDate" name="date" required>
                    </div>
                    
                    <div class="modal-field">
                        <label for="icsRrspNo">ICS/RRSP No.:</label>
                        <input type="text" id="icsRrspNo" name="ics_rrsp_no" placeholder="Enter ICS/RRSP Number" required>
                    </div>
                    
                    <div class="modal-field">
                        <label for="propertyNo">Semi-Expendable Property No.:</label>
                        <input type="text" id="propertyNo" name="property_no" placeholder="Enter Property Number" required>
                    </div>
                    
                    <div class="modal-field full-width">
                        <label for="itemDescription">Item Description:</label>
                        <textarea id="itemDescription" name="item_description" placeholder="Enter detailed item description" required></textarea>
                    </div>
                    
                    <div class="modal-field">
                        <label for="usefulLife">Estimated Useful Life:</label>
                        <input type="text" id="usefulLife" name="useful_life" placeholder="e.g., 5 Years" required>
                    </div>
                    
                    <div class="modal-field">
                        <label for="issuedQty">Issued Quantity:</label>
                        <input type="number" id="issuedQty" name="issued_qty" placeholder="0" min="0" required>
                    </div>
                    
                    <div class="modal-field">
                        <label for="issuedOffice">Issued Office/Officer:</label>
                        <input type="text" id="issuedOffice" name="issued_office" placeholder="Enter office or officer name">
                    </div>
                    
                    <div class="modal-field">
                        <label for="returnedQty">Returned Quantity:</label>
                        <input type="number" id="returnedQty" name="returned_qty" placeholder="0" min="0" value="0">
                    </div>
                    
                    <div class="modal-field">
                        <label for="returnedOffice">Returned Office/Officer:</label>
                        <input type="text" id="returnedOffice" name="returned_office" placeholder="Enter office or officer name">
                    </div>
                    
                    <div class="modal-field">
                        <label for="reissuedQty">Re-issued Quantity:</label>
                        <input type="number" id="reissuedQty" name="reissued_qty" placeholder="0" min="0" value="0">
                    </div>
                    
                    <div class="modal-field">
                        <label for="reissuedOffice">Re-issued Office/Officer:</label>
                        <input type="text" id="reissuedOffice" name="reissued_office" placeholder="Enter office or officer name">
                    </div>
                    
                    <div class="modal-field">
                        <label for="disposedQty1">Disposed Quantity 1:</label>
                        <input type="number" id="disposedQty1" name="disposed_qty1" placeholder="0" min="0" value="0">
                    </div>
                    
                    <div class="modal-field">
                        <label for="disposedQty2">Disposed Quantity 2:</label>
                        <input type="number" id="disposedQty2" name="disposed_qty2" placeholder="0" min="0" value="0">
                    </div>
                    
                    <div class="modal-field">
                        <label for="balanceQty">Balance Quantity:</label>
                        <input type="number" id="balanceQty" name="balance_qty" placeholder="0" min="0" readonly>
                    </div>
                    
                    <div class="modal-field">
                        <label for="amount">Amount (Total):</label>
                        <input type="text" id="amount" name="amount" placeholder="₱0.00">
                    </div>
                    
                    <div class="modal-field full-width">
                        <label for="remarks">Remarks:</label>
                        <textarea id="remarks" name="remarks" placeholder="Enter any additional remarks or notes"></textarea>
                    </div>
                </form>
                
                <div class="modal-actions">
                    <button type="button" class="modal-btn secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="modal-btn primary" id="addItemBtn">Add Item</button>
                </div>
                
            </div>
        </div>
    </div>
    
    

    <script>
        // Modal functionality
        const modal = document.getElementById('itemModal');
        const addItemsBtn = document.getElementById('addItemsBtn');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const addItemBtn = document.getElementById('addItemBtn');
        const itemForm = document.getElementById('itemForm');
        const tableBody = document.getElementById('tableBody');

        // Open modal
        addItemsBtn.addEventListener('click', function() {
            modal.classList.add('show');
            // Set today's date as default
            document.getElementById('itemDate').valueAsDate = new Date();
        });

        // Close modal functions
        function closeModalFunc() {
            modal.classList.remove('show');
            itemForm.reset();
        }

        closeModal.addEventListener('click', closeModalFunc);
        cancelBtn.addEventListener('click', closeModalFunc);

        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModalFunc();
            }
        });

        // Auto-calculate balance quantity
        function calculateBalance() {
            const issued = parseInt(document.getElementById('issuedQty').value) || 0;
            const returned = parseInt(document.getElementById('returnedQty').value) || 0;
            const reissued = parseInt(document.getElementById('reissuedQty').value) || 0;
            const disposed1 = parseInt(document.getElementById('disposedQty1').value) || 0;
            const disposed2 = parseInt(document.getElementById('disposedQty2').value) || 0;
            
            const balance = issued - returned - reissued - disposed1 - disposed2;
            document.getElementById('balanceQty').value = Math.max(0, balance);
        }

        // Add event listeners for auto-calculation
        ['issuedQty', 'returnedQty', 'reissuedQty', 'disposedQty1', 'disposedQty2'].forEach(id => {
            document.getElementById(id).addEventListener('input', calculateBalance);
        });

        // Add item to table
        addItemBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(itemForm);
            const data = Object.fromEntries(formData);
            
            // Validate required fields
            if (!data.date || !data.ics_rrsp_no || !data.property_no || !data.item_description || !data.useful_life || !data.issued_qty) {
                alert('Please fill in all required fields.');
                return;
            }

            // Create new row
            const newRow = tableBody.insertRow();
            newRow.innerHTML = `
                <td>${data.date}</td>
                <td>${data.ics_rrsp_no}</td>
                <td>${data.property_no}</td>
                <td>${data.item_description}</td>
                <td>${data.useful_life}</td>
                <td>${data.issued_qty}</td>
                <td>${data.issued_office || '-'}</td>
                <td>${data.returned_qty || '0'}</td>
                <td>${data.returned_office || '-'}</td>
                <td>${data.reissued_qty || '0'}</td>
                <td>${data.reissued_office || '-'}</td>
                <td>${data.disposed_qty1 || '0'}</td>
                <td>${data.disposed_qty2 || '0'}</td>
                <td>${data.balance_qty || '0'}</td>
                <td>${data.amount || '-'}</td>
                <td>${data.remarks || '-'}</td>
            `;

            // Close modal and reset form
            closeModalFunc();
            
            // Show success message
            alert('Item added successfully!');
        });

        // Clear all items
        document.addEventListener('click', function(e) {
            if (e.target.textContent.includes('Clear All')) {
                if (confirm('Are you sure you want to clear all items? This action cannot be undone.')) {
                    // Keep only the header sample row
                    const firstRow = tableBody.rows[0];
                    tableBody.innerHTML = '';
                    if (firstRow) {
                        tableBody.appendChild(firstRow);
                    }
                }
            }
        });
    </script>
    <script>
        const tableWrapper = document.querySelector(".table-zoom-wrapper");
        const table = document.querySelector(".rsep-table");
        const zoomSlider = document.getElementById("zoomSlider");
        const zoomValue = document.getElementById("zoomValue");

        let zoomLevel = 1;

        function applyZoom(zoomPercent) {
            zoomLevel = zoomPercent / 100;
            table.style.transform = `scale(${zoomLevel})`;
            zoomSlider.value = zoomPercent;
            zoomValue.textContent = `${zoomPercent}%`;
        }

        // Zoom via slider
        zoomSlider.addEventListener("input", function () {
            applyZoom(this.value);
        });

        // Zoom via scroll
        tableWrapper.addEventListener("wheel", function (e) {
            if (e.ctrlKey || e.shiftKey) return;

            e.preventDefault();
            const zoomFactor = 5;
            let newZoom = zoomLevel * 100 + (e.deltaY < 0 ? zoomFactor : -zoomFactor);
            newZoom = Math.min(Math.max(50, newZoom), 200);
            applyZoom(newZoom);
        });

        // Initialize
        applyZoom(100);
        </script>


</body>
</html>