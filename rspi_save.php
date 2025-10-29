<?php
require_once 'config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

// Function to validate required fields
function validateField($field, $label) {
    if (empty($field)) {
        throw new Exception("$label is required");
    }
    return htmlspecialchars(trim($field));
}

// Check if it's an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $conn->begin_transaction();

        // Validate and sanitize main form fields
        $entityName = validateField($_POST['entityName'], 'Entity Name');
        $fundCluster = validateField($_POST['fundCluster'], 'Fund Cluster');
        $serialNo = validateField($_POST['serialNo'], 'Serial No.');
        $date = validateField($_POST['date'], 'Date');
        $custodianName = validateField($_POST['custodian_name'], 'Property/Supply Custodian');
        $postedBy = validateField($_POST['posted_by'], 'Posted By');

        // Insert main RSPI record
        $stmt = $conn->prepare("INSERT INTO rspi_reports (
            serial_no, entity_name, fund_cluster, report_date, 
            custodian_name, posted_by, created_at, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");

        $stmt->bind_param("ssssssi", 
            $serialNo, $entityName, $fundCluster, $date,
            $custodianName, $postedBy, $_SESSION['user_id']
        );
        $stmt->execute();
        $rspiId = $conn->insert_id;

        // Process each item row
        $icsNos = $_POST['ics_no'];
        $respCenters = $_POST['resp_center'];
        $propertyNos = $_POST['property_no'];
        $descriptions = $_POST['description'];
        $units = $_POST['unit'];
        $quantities = $_POST['quantity'];
        $unitCosts = $_POST['unit_cost'];
        $amounts = $_POST['amount'];

        // Prepare statement for items
        $itemStmt = $conn->prepare("INSERT INTO rspi_items (
            rspi_id, ics_no, responsibility_center, property_no,
            item_description, unit, quantity_issued, unit_cost, amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Insert each item
        for ($i = 0; $i < count($icsNos); $i++) {
            // Validate required fields for each row
            $icsNo = validateField($icsNos[$i], 'ICS No.');
            $respCenter = validateField($respCenters[$i], 'Responsibility Center');
            $propertyNo = validateField($propertyNos[$i], 'Property No.');
            $description = validateField($descriptions[$i], 'Item Description');
            $unit = validateField($units[$i], 'Unit');
            $quantity = validateField($quantities[$i], 'Quantity');
            $unitCost = validateField($unitCosts[$i], 'Unit Cost');
            $amount = validateField($amounts[$i], 'Amount');

            // Validate numeric fields
            if (!is_numeric($quantity) || $quantity <= 0) {
                throw new Exception("Invalid quantity in row " . ($i + 1));
            }
            if (!is_numeric($unitCost) || $unitCost <= 0) {
                throw new Exception("Invalid unit cost in row " . ($i + 1));
            }
            if (!is_numeric($amount) || $amount <= 0) {
                throw new Exception("Invalid amount in row " . ($i + 1));
            }

            $itemStmt->bind_param("isssssddd",
                $rspiId, $icsNo, $respCenter, $propertyNo,
                $description, $unit, $quantity, $unitCost, $amount
            );
            $itemStmt->execute();

            // Update semi_expendable_history for each item
            $historyStmt = $conn->prepare("INSERT INTO semi_expendable_history (
                semi_id, date, ics_rrsp_no, quantity_issued, 
                quantity_balance, office_officer_issued, 
                amount, amount_total, remarks
            ) VALUES (
                (SELECT id FROM semi_expendable WHERE ics_no = ?),
                ?, ?, ?, 
                (SELECT quantity - ? FROM semi_expendable WHERE ics_no = ?),
                ?, ?, ?, 'RSPI Issue'
            )");

            $historyStmt->bind_param("sssdssdd",
                $icsNo, $date, $serialNo, $quantity,
                $quantity, $icsNo, $respCenter,
                $unitCost, $amount
            );
            $historyStmt->execute();
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'RSPI record saved successfully',
            'rspiId' => $rspiId
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}

$conn->close();
?>