<?php
// edit_semi_expendable.php - Edit form for semi-expendable property
require_once 'config.php';
require_once 'sidebar.php'; // Add sidebar requirement

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = null;
$error = '';
$success = '';

// Valid categories
$valid_categories = ['Other PPE', 'Office Equipment', 'ICT Equipment', 'Communication Equipment', 'Furniture and Fixtures'];

// Fetch item details
if ($id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM semi_expendable_property WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        
        if (!$item) {
            $error = "Item not found.";
        }
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $item) {
    try {
        $stmt = $conn->prepare("
            UPDATE semi_expendable_property 
            SET date = ?, 
                ics_rrsp_no = ?, 
                semi_expendable_property_no = ?, 
                item_description = ?, 
                estimated_useful_life = ?, 
                quantity_issued = ?, 
                office_officer_issued = ?, 
                quantity_returned = ?, 
                office_officer_returned = ?, 
                quantity_reissued = ?, 
                office_officer_reissued = ?, 
                quantity_disposed = ?, 
                quantity_balance = ?, 
                amount_total = ?, 
                category = ?, 
                remarks = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param(
            "ssssiississdssi",
            $_POST['date'],
            $_POST['ics_rrsp_no'],
            $_POST['semi_expendable_property_no'],
            $_POST['item_description'],
            $_POST['estimated_useful_life'],
            $_POST['quantity_issued'],
            $_POST['office_officer_issued'],
            $_POST['quantity_returned'] ?: 0,
            $_POST['office_officer_returned'],
            $_POST['quantity_reissued'] ?: 0,
            $_POST['office_officer_reissued'],
            $_POST['quantity_disposed'] ?: 0,
            $_POST['quantity_balance'],
            $_POST['amount_total'],
            $_POST['category'],
            $_POST['remarks'],
            $id
        );
        
        if ($stmt->execute()) {
            $success = "Item updated successfully!";
            $stmt->close();
            
            // Refresh item data
            $stmt = $conn->prepare("SELECT * FROM semi_expendable_property WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $item = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error = "Failed to update item: " . $stmt->error;
            $stmt->close();
        }
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

if (!$item && empty($error)) {
    $error = "Invalid item ID.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Semi-Expendable Item</title>
    <!-- Add your existing CSS links here -->
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <header style="margin-bottom: 30px;">
                <h1>Edit Semi-Expendable Property</h1>
                <p>Update item details in the registry</p>
            </header>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($item): ?>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($item['date']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="ics_rrsp_no">ICS/RRSP No.</label>
                            <input type="text" id="ics_rrsp_no" name="ics_rrsp_no" value="<?php echo htmlspecialchars($item['ics_rrsp_no']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="semi_expendable_property_no">Semi-Expendable Property No.</label>
                            <input type="text" id="semi_expendable_property_no" name="semi_expendable_property_no" 
                                   value="<?php echo htmlspecialchars($item['semi_expendable_property_no']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" required>
                                <?php foreach ($valid_categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" 
                                            <?php echo $cat === $item['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="item_description">Item Description</label>
                        <textarea id="item_description" name="item_description" required><?php echo htmlspecialchars($item['item_description']); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="estimated_useful_life">Estimated Useful Life (years)</label>
                            <input type="number" id="estimated_useful_life" name="estimated_useful_life" 
                                   value="<?php echo htmlspecialchars($item['estimated_useful_life']); ?>" min="1" max="20" required>
                        </div>
                        <div class="form-group">
                            <label for="amount_total">Amount (Total)</label>
                            <input type="number" id="amount_total" name="amount_total" step="0.01" min="0"
                                   value="<?php echo htmlspecialchars($item['amount_total']); ?>" required>
                        </div>
                    </div>

                    <h3 style="margin-top: 30px; margin-bottom: 20px; color: #374151;">Issued Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity_issued">Quantity Issued</label>
                            <input type="number" id="quantity_issued" name="quantity_issued" min="0"
                                   value="<?php echo htmlspecialchars($item['quantity_issued']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="office_officer_issued">Office/Officer Issued</label>
                            <input type="text" id="office_officer_issued" name="office_officer_issued" 
                                   value="<?php echo htmlspecialchars($item['office_officer_issued']); ?>">
                        </div>
                    </div>

                    <h3 style="margin-top: 30px; margin-bottom: 20px; color: #374151;">Returns & Reissued</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity_returned">Quantity Returned</label>
                            <input type="number" id="quantity_returned" name="quantity_returned" min="0"
                                   value="<?php echo htmlspecialchars($item['quantity_returned']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="office_officer_returned">Office/Officer Returned</label>
                            <input type="text" id="office_officer_returned" name="office_officer_returned" 
                                   value="<?php echo htmlspecialchars($item['office_officer_returned']); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity_reissued">Quantity Re-issued</label>
                            <input type="number" id="quantity_reissued" name="quantity_reissued" min="0"
                                   value="<?php echo htmlspecialchars($item['quantity_reissued']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="office_officer_reissued">Office/Officer Re-issued</label>
                            <input type="text" id="office_officer_reissued" name="office_officer_reissued" 
                                   value="<?php echo htmlspecialchars($item['office_officer_reissued']); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity_disposed">Quantity Disposed</label>
                            <input type="number" id="quantity_disposed" name="quantity_disposed" min="0"
                                   value="<?php echo htmlspecialchars($item['quantity_disposed']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="quantity_balance">Quantity Balance</label>
                            <input type="number" id="quantity_balance" name="quantity_balance" min="0"
                                   value="<?php echo htmlspecialchars($item['quantity_balance']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks"><?php echo htmlspecialchars($item['remarks']); ?></textarea>
                    </div>

                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">Update Item</button>
                        <a href="semi_expendible.php?category=<?php echo urlencode($item['category']); ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            <?php endif; ?> 
        </div>
    </div>

    <script>
    // Auto-calculate balance when quantities change
    function calculateBalance() {
        const issued = parseInt(document.getElementById('quantity_issued').value) || 0;
        const returned = parseInt(document.getElementById('quantity_returned').value) || 0;
        const reissued = parseInt(document.getElementById('quantity_reissued').value) || 0;
        const disposed = parseInt(document.getElementById('quantity_disposed').value) || 0;
        
        const balance = issued - returned + reissued - disposed;
        document.getElementById('quantity_balance').value = Math.max(0, balance);
    }

    // Add event listeners
    document.addEventListener('DOMContentLoaded', function() {
        const quantityFields = ['quantity_issued', 'quantity_returned', 'quantity_reissued', 'quantity_disposed'];
        
        quantityFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('input', calculateBalance);
                field.addEventListener('change', calculateBalance);
            }
        });
    });
    </script>
</body>
</html>