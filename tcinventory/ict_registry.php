<?php
// ict_registry.php
session_start();

// Check if user is logged in (uncomment if you have authentication)
// if (!isset($_SESSION['user_id'])) {
//     header("Location: login.php");
//     exit();
// }

require_once 'config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                addIctEntry($conn, $_POST);
                break;
            case 'edit':
                editIctEntry($conn, $_POST);
                break;
            case 'delete':
                deleteIctEntry($conn, $_POST['id']);
                break;
        }
        // Redirect to prevent form resubmission
        header("Location: ict_registry.php");
        exit();
    }
}

// Functions
function addIctEntry($conn, $data) {
    $stmt = $conn->prepare("INSERT INTO ict_registry (
        date, reference_no, property_no, item_description, useful_life, 
        issued_qty, issued_officer, returned_qty, returned_officer, 
        reissued_qty, reissued_officer, disposed_qty, balance_qty, 
        total_amount, remarks, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $balance_qty = calculateBalance($data);
    
    $stmt->bind_param("ssssissisissdss", 
        $data['entry_date'],
        $data['reference_no'],
        $data['property_no'],
        $data['item_description'],
        $data['useful_life'],
        $data['issued_qty'],
        $data['issued_officer'],
        $data['returned_qty'],
        $data['returned_officer'],
        $data['reissued_qty'],
        $data['reissued_officer'],
        $data['disposed_qty'],
        $balance_qty,
        $data['total_amount'],
        $data['remarks']
    );
    
    return $stmt->execute();
}

function editIctEntry($conn, $data) {
    $stmt = $conn->prepare("UPDATE ict_registry SET 
        date=?, reference_no=?, property_no=?, item_description=?, useful_life=?, 
        issued_qty=?, issued_officer=?, returned_qty=?, returned_officer=?, 
        reissued_qty=?, reissued_officer=?, disposed_qty=?, balance_qty=?, 
        total_amount=?, remarks=?, updated_at=NOW()
        WHERE id=?");
    
    $balance_qty = calculateBalance($data);
    
    $stmt->bind_param("ssssissisissdsi", 
        $data['entry_date'],
        $data['reference_no'],
        $data['property_no'],
        $data['item_description'],
        $data['useful_life'],
        $data['issued_qty'],
        $data['issued_officer'],
        $data['returned_qty'],
        $data['returned_officer'],
        $data['reissued_qty'],
        $data['reissued_officer'],
        $data['disposed_qty'],
        $balance_qty,
        $data['total_amount'],
        $data['remarks'],
        $data['id']
    );
    
    return $stmt->execute();
}

function deleteIctEntry($conn, $id) {
    $stmt = $conn->prepare("DELETE FROM ict_registry WHERE id = ?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

function calculateBalance($data) {
    $issued = intval($data['issued_qty'] ?? 0);
    $returned = intval($data['returned_qty'] ?? 0);
    $reissued = intval($data['reissued_qty'] ?? 0);
    $disposed = intval($data['disposed_qty'] ?? 0);
    
    return $issued + $reissued - $returned - $disposed;
}

// Fetch ICT registry data
$result = $conn->query("
    SELECT * FROM ict_registry 
    ORDER BY date DESC, id DESC
");

// Fetch recapitulation data
$recap_result = $conn->query("
    SELECT property_no, SUM(issued_qty) as total_issued
    FROM ict_registry
    GROUP BY property_no
    ORDER BY property_no
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICT Registry - Registry of Semi-Expendable Property Issued</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .export-section {
            padding: 15px;
            border-radius: 5px;
        }
        
        .export-btn {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .export-btn:hover {
            background-color: #218838;
            color: white;
            text-decoration: none;
        }
        
        .export-btn i {
            margin-right: 5px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group textarea {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-actions {
            text-align: right;
            margin-top: 20px;
        }

        .btn {
            padding: 8px 15px;
            margin-left: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-edit {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            font-size: 12px;
            margin-right: 5px;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            font-size: 12px;
        }

        .actions-cell {
            white-space: nowrap;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <h2>Registry of Semi-Expendable Property Issued - ICT Equipment</h2>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <!-- Export Section -->
        <div class="export-section">
            <button onclick="openAddModal()" class="export-btn">
                <i class="fas fa-plus"></i> Add New Item
            </button>
            <a href="ict_export.php" class="export-btn" target="_blank">
                📄 Export to PDF
            </a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference No.</th>
                    <th>Semi-Expendable Property No.</th>
                    <th>Item Description</th>
                    <th>Estimated Useful Life</th>
                    <th>Issued Qty.</th>
                    <th>Issued Officer</th>
                    <th>Returned Qty.</th>
                    <th>Returned Officer</th>
                    <th>Re-issued Qty.</th>
                    <th>Re-issued Officer</th>
                    <th>Disposed Qty.</th>
                    <th>Balance Qty.</th>
                    <th>Amount (TOTAL)</th>
                    <th>Remarks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $formatted_date = date('n/j/Y', strtotime($row['date']));
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($formatted_date) . '</td>';
                        echo '<td>' . htmlspecialchars($row['reference_no']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['property_no']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['item_description']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['useful_life']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['issued_qty']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['issued_officer']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['returned_qty'] ?: '-') . '</td>';
                        echo '<td>' . htmlspecialchars($row['returned_officer'] ?: '-') . '</td>';
                        echo '<td>' . htmlspecialchars($row['reissued_qty'] ?: '-') . '</td>';
                        echo '<td>' . htmlspecialchars($row['reissued_officer'] ?: '-') . '</td>';
                        echo '<td>' . htmlspecialchars($row['disposed_qty'] ?: '-') . '</td>';
                        echo '<td>' . htmlspecialchars($row['balance_qty']) . '</td>';
                        echo '<td class="currency">₱ ' . number_format($row['total_amount'], 2) . '</td>';
                        echo '<td>' . htmlspecialchars($row['remarks'] ?: '-') . '</td>';
                        echo '<td class="actions-cell">';
                        echo '<button class="btn-edit" onclick="editEntry(' . $row['id'] . ')">Edit</button>';
                        echo '<button class="btn-delete" onclick="deleteEntry(' . $row['id'] . ')">Delete</button>';
                        echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="16">No ICT registry entries found.</td></tr>';
                }
                ?>
            </tbody>
        </table>

        <h2>Recapitulation</h2>
        <table>
            <thead>
                <tr>
                    <th>Property No.</th>
                    <th>Total Quantity Issued</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($recap_result && $recap_result->num_rows > 0) {
                    while ($row = $recap_result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['property_no']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['total_issued']) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="2">No recapitulation data found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Add/Edit Modal -->
    <div id="entryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle">Add New ICT Entry</h3>
            
            <form id="entryForm" method="POST">
                <input type="hidden" id="formAction" name="action" value="add">
                <input type="hidden" id="entryId" name="id" value="">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="entry_date" id="entryDate" required>
                    </div>
                    <div class="form-group">
                        <label>Reference No.</label>
                        <input type="text" name="reference_no" id="referenceNo" required>
                    </div>
                    <div class="form-group">
                        <label>Semi-Expendable Property No.</label>
                        <input type="text" name="property_no" id="propertyNo" required>
                    </div>
                    <div class="form-group">
                        <label>Estimated Useful Life</label>
                        <input type="number" name="useful_life" id="usefulLife" value="5" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Item Description</label>
                        <textarea name="item_description" id="itemDescription" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Issued Quantity</label>
                        <input type="number" name="issued_qty" id="issuedQty" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Issued Officer</label>
                        <input type="text" name="issued_officer" id="issuedOfficer" required>
                    </div>
                    <div class="form-group">
                        <label>Returned Quantity</label>
                        <input type="number" name="returned_qty" id="returnedQty" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Returned Officer</label>
                        <input type="text" name="returned_officer" id="returnedOfficer">
                    </div>
                    <div class="form-group">
                        <label>Re-issued Quantity</label>
                        <input type="number" name="reissued_qty" id="reissuedQty" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Re-issued Officer</label>
                        <input type="text" name="reissued_officer" id="reissuedOfficer">
                    </div>
                    <div class="form-group">
                        <label>Disposed Quantity</label>
                        <input type="number" name="disposed_qty" id="disposedQty" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Amount (Total)</label>
                        <input type="number" name="total_amount" id="totalAmount" step="0.01" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Remarks</label>
                        <textarea name="remarks" id="remarks" rows="2"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Entry</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New ICT Entry';
            document.getElementById('entryForm').reset();
            document.getElementById('formAction').value = 'add';
            document.getElementById('entryId').value = '';
            document.getElementById('entryDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('usefulLife').value = '5';
            document.getElementById('entryModal').style.display = 'block';
        }

        function editEntry(id) {
            // Fetch entry data via AJAX or get from page data
            fetch(`get_ict_entry.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modalTitle').textContent = 'Edit ICT Entry';
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('entryId').value = data.id;
                    
                    document.getElementById('entryDate').value = data.date;
                    document.getElementById('referenceNo').value = data.reference_no;
                    document.getElementById('propertyNo').value = data.property_no;
                    document.getElementById('itemDescription').value = data.item_description;
                    document.getElementById('usefulLife').value = data.useful_life;
                    document.getElementById('issuedQty').value = data.issued_qty;
                    document.getElementById('issuedOfficer').value = data.issued_officer;
                    document.getElementById('returnedQty').value = data.returned_qty || 0;
                    document.getElementById('returnedOfficer').value = data.returned_officer || '';
                    document.getElementById('reissuedQty').value = data.reissued_qty || 0;
                    document.getElementById('reissuedOfficer').value = data.reissued_officer || '';
                    document.getElementById('disposedQty').value = data.disposed_qty || 0;
                    document.getElementById('totalAmount').value = data.total_amount;
                    document.getElementById('remarks').value = data.remarks || '';
                    
                    document.getElementById('entryModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading entry data');
                });
        }

        function deleteEntry(id) {
            if (confirm('Are you sure you want to delete this entry?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function closeModal() {
            document.getElementById('entryModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('entryModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Add mobile sidebar toggle functionality if needed
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.querySelector('.menu-button');
            if (menuButton) {
                menuButton.addEventListener('click', function() {
                    const sidebar = document.querySelector('.sidebar');
                    sidebar.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>