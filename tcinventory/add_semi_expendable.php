<?php
require_once 'config.php';
require_once 'sidebar.php'; // Add sidebar requirement

$error = '';
$success = '';

// Valid categories
$valid_categories = ['Other PPE', 'Office Equipment', 'ICT Equipment', 'Communication Equipment', 'Furniture and Fixtures'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Assign POST values to variables
    $date = $_POST['date'];
    $ics_rrsp_no = $_POST['ics_rrsp_no'];
    $semi_expendable_property_no = $_POST['semi_expendable_property_no'];
    $item_description = $_POST['item_description'];
    $estimated_useful_life = intval($_POST['estimated_useful_life']);
    $quantity_issued = intval($_POST['quantity_issued']);
    $office_officer_issued = $_POST['office_officer_issued'];
    $quantity_returned = intval($_POST['quantity_returned'] ?? 0);
    $office_officer_returned = $_POST['office_officer_returned'];
    $quantity_reissued = intval($_POST['quantity_reissued'] ?? 0);
    $office_officer_reissued = $_POST['office_officer_reissued'];
    $quantity_disposed = intval($_POST['quantity_disposed'] ?? 0);
    $quantity_balance = intval($_POST['quantity_balance']);
    $amount_total = floatval($_POST['amount_total']);
    $category = $_POST['category'];
    $fund_cluster = $_POST['fund_cluster'] ?? '101';
    $remarks = $_POST['remarks'];

    // Check for duplicate property number
    $check_stmt = $conn->prepare("SELECT id FROM semi_expendable_property WHERE semi_expendable_property_no = ? LIMIT 1");
    $check_stmt->bind_param("s", $semi_expendable_property_no);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result && $check_result->num_rows > 0) {
        $error = "A record with this Semi-Expendable Property No. already exists.";
        $check_stmt->close();
    } else {
        $check_stmt->close();
        $stmt = $conn->prepare("
            INSERT INTO semi_expendable_property 
            (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, 
             quantity_issued, office_officer_issued, quantity_returned, office_officer_returned, 
             quantity_reissued, office_officer_reissued, quantity_disposed, quantity_balance, 
             amount_total, category, fund_cluster, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            $error = "Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param(
                "ssssiississdsssss",
                $date,
                $ics_rrsp_no,
                $semi_expendable_property_no,
                $item_description,
                $estimated_useful_life,
                $quantity_issued,
                $office_officer_issued,
                $quantity_returned,
                $office_officer_returned,
                $quantity_reissued,
                $office_officer_reissued,
                $quantity_disposed,
                $quantity_balance,
                $amount_total,
                $category,
                $fund_cluster,
                $remarks
            );
            if ($stmt->execute()) {
                $success = "Item added successfully!";
                $stmt->close();
                $_POST = [];
            } else {
                $error = "Failed to add item: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

// Get default category from URL
$default_category = isset($_GET['category']) && in_array($_GET['category'], $valid_categories) ? $_GET['category'] : 'Other PPE';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Semi-Expendable Item</title>
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
        .required {
            color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <header style="margin-bottom: 30px;">
                <h1>Add New Semi-Expendable Property</h1>
                <p>Register a new item in the semi-expendable property registry</p>
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

            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="date">Date <span class="required">*</span></label>
                        <input type="date" id="date" name="date" value="<?php echo $_POST['date'] ?? date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="ics_rrsp_no">ICS/RRSP No. <span class="required">*</span></label>
                        <input type="text" id="ics_rrsp_no" name="ics_rrsp_no" value="<?php echo $_POST['ics_rrsp_no'] ?? ''; ?>" 
                               placeholder="e.g., 22-01" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="semi_expendable_property_no">Semi-Expendable Property No. <span class="required">*</span></label>
                        <input type="text" id="semi_expendable_property_no" name="semi_expendable_property_no" 
                               value="<?php echo $_POST['semi_expendable_property_no'] ?? ''; ?>" 
                               placeholder="e.g., HV-22-101-01" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category <span class="required">*</span></label>
                        <select id="category" name="category" required>
                            <?php foreach ($valid_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php echo ($cat === ($_POST['category'] ?? $default_category)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="item_description">Item Description <span class="required">*</span></label>
                    <textarea id="item_description" name="item_description" 
                              placeholder="Detailed description of the item..." required><?php echo $_POST['item_description'] ?? ''; ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="estimated_useful_life">Estimated Useful Life (years) <span class="required">*</span></label>
                        <input type="number" id="estimated_useful_life" name="estimated_useful_life" 
                               value="<?php echo $_POST['estimated_useful_life'] ?? '5'; ?>" min="1" max="20" required>
                    </div>
                    <div class="form-group">
                        <label for="amount_total">Amount (Total) <span class="required">*</span></label>
                        <input type="number" id="amount_total" name="amount_total" step="0.01" min="0"
                               value="<?php echo $_POST['amount_total'] ?? ''; ?>" 
                               placeholder="0.00" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="fund_cluster">Fund Cluster</label>
                    <input type="text" id="fund_cluster" name="fund_cluster" 
                           value="<?php echo $_POST['fund_cluster'] ?? '101'; ?>" 
                           placeholder="101">
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 20px; color: #374151;">Issued Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity_issued">Quantity Issued <span class="required">*</span></label>
                        <input type="number" id="quantity_issued" name="quantity_issued" min="0"
                               value="<?php echo $_POST['quantity_issued'] ?? '1'; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="office_officer_issued">Office/Officer Issued</label>
                        <input type="text" id="office_officer_issued" name="office_officer_issued" 
                               value="<?php echo $_POST['office_officer_issued'] ?? ''; ?>"
                               placeholder="Name of officer or office">
                    </div>
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 20px; color: #374151;">Returns & Reissued (Optional)</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity_returned">Quantity Returned</label>
                        <input type="number" id="quantity_returned" name="quantity_returned" min="0"
                               value="<?php echo $_POST['quantity_returned'] ?? '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="office_officer_returned">Office/Officer Returned</label>
                        <input type="text" id="office_officer_returned" name="office_officer_returned" 
                               value="<?php echo $_POST['office_officer_returned'] ?? ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity_reissued">Quantity Re-issued</label>
                        <input type="number" id="quantity_reissued" name="quantity_reissued" min="0"
                               value="<?php echo $_POST['quantity_reissued'] ?? '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="office_officer_reissued">Office/Officer Re-issued</label>
                        <input type="text" id="office_officer_reissued" name="office_officer_reissued" 
                               value="<?php echo $_POST['office_officer_reissued'] ?? ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity_disposed">Quantity Disposed</label>
                        <input type="number" id="quantity_disposed" name="quantity_disposed" min="0"
                               value="<?php echo $_POST['quantity_disposed'] ?? '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label for="quantity_balance">Quantity Balance <span class="required">*</span></label>
                        <input type="number" id="quantity_balance" name="quantity_balance" min="0"
                               value="<?php echo $_POST['quantity_balance'] ?? '1'; ?>" required readonly>
                        <small style="color: #6b7280;">Auto-calculated based on issued, returned, reissued, and disposed quantities</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" placeholder="Additional notes or remarks..."><?php echo $_POST['remarks'] ?? ''; ?></textarea>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">Add Item</button>
                    <a href="semi_expendable.php?category=<?php echo urlencode($default_category); ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
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

        // Initial calculation
        calculateBalance();
    });
    </script>
</body>
</html>