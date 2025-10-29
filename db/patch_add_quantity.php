<?php
// One-off patch to add `quantity` column to semi_expendable_property
// Safe to run multiple times.
header('Content-Type: text/plain');

require_once __DIR__ . '/../config.php';

try {
    // Ensure DB selected via config.php
    $dbName = $conn->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '(unknown)';

    // Check if table exists
    $tableExists = false;
    if ($res = $conn->query("SHOW TABLES LIKE 'semi_expendable_property'")) {
        $tableExists = $res->num_rows > 0;
        $res->free();
    }
    if (!$tableExists) {
        throw new Exception("Table 'semi_expendable_property' does not exist in database '$dbName'.");
    }

    // Check if column exists
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'semi_expendable_property' AND COLUMN_NAME = 'quantity'");
    if (!$stmt) { throw new Exception('Prepare failed: ' . $conn->error); }
    $stmt->execute();
    $cnt = 0; $stmt->bind_result($cnt); $stmt->fetch(); $stmt->close();

    if ($cnt > 0) {
        echo "OK: Column 'quantity' already exists on semi_expendable_property in '$dbName'.\n";
    } else {
        $sql = "ALTER TABLE semi_expendable_property ADD COLUMN quantity INT DEFAULT 0 AFTER estimated_useful_life";
        if (!$conn->query($sql)) {
            throw new Exception('ALTER TABLE failed: ' . $conn->error);
        }
        echo "DONE: Added column 'quantity' INT DEFAULT 0 to semi_expendable_property in '$dbName'.\n";
    }

    // Verify
    if ($res = $conn->query("SHOW COLUMNS FROM semi_expendable_property LIKE 'quantity'")) {
        if ($res->num_rows > 0) {
            $col = $res->fetch_assoc();
            echo "VERIFY: quantity column present (Type=" . $col['Type'] . ")\n";
        }
        $res->free();
    }

    echo "Patch complete.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
} finally {
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
}
