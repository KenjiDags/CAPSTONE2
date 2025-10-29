<?php
// One-click DB patch: add 'amount' (unit price) columns to semi_expendable_property and semi_expendable_history
// Safe to run multiple times (idempotent).

header('Content-Type: text/plain');

require_once __DIR__ . '/../config.php';

function columnExists($conn, $table, $column) {
    $db = $conn->real_escape_string($conn->query('SELECT DATABASE() db')->fetch_assoc()['db']);
    $tableEsc = $conn->real_escape_string($table);
    $columnEsc = $conn->real_escape_string($column);
    $sql = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='{$db}' AND TABLE_NAME='{$tableEsc}' AND COLUMN_NAME='{$columnEsc}'";
    $res = $conn->query($sql);
    if (!$res) { return false; }
    $row = $res->fetch_assoc();
    return isset($row['cnt']) && (int)$row['cnt'] > 0;
}

function ensureHistoryTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS semi_expendable_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        semi_id INT NOT NULL,
        date DATE NULL,
        ics_rrsp_no VARCHAR(255) NULL,
        quantity INT DEFAULT 0,
        quantity_issued INT DEFAULT 0,
        quantity_returned INT DEFAULT 0,
        quantity_reissued INT DEFAULT 0,
        quantity_disposed INT DEFAULT 0,
        quantity_balance INT DEFAULT 0,
        office_officer_issued VARCHAR(255) NULL,
        office_officer_returned VARCHAR(255) NULL,
        office_officer_reissued VARCHAR(255) NULL,
        amount DECIMAL(15,2) DEFAULT 0,
        amount_total DECIMAL(15,2) DEFAULT 0,
        remarks TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (semi_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    if (!$conn->query($sql)) {
        throw new Exception('Failed ensuring semi_expendable_history: ' . $conn->error);
    }
}

$log = [];

try {
    // Ensure main table column
    if (!columnExists($conn, 'semi_expendable_property', 'amount')) {
        if (!$conn->query("ALTER TABLE semi_expendable_property ADD COLUMN amount DECIMAL(15,2) DEFAULT 0")) {
            throw new Exception('ALTER property failed: ' . $conn->error);
        }
        $log[] = "Added column semi_expendable_property.amount";
    } else {
        $log[] = "Column semi_expendable_property.amount already exists";
    }

    // Ensure history table and column
    ensureHistoryTable($conn);
    if (!columnExists($conn, 'semi_expendable_history', 'amount')) {
        if (!$conn->query("ALTER TABLE semi_expendable_history ADD COLUMN amount DECIMAL(15,2) DEFAULT 0")) {
            throw new Exception('ALTER history failed: ' . $conn->error);
        }
        $log[] = "Added column semi_expendable_history.amount";
    } else {
        $log[] = "Column semi_expendable_history.amount already exists";
    }

    echo "SUCCESS\n" . implode("\n", $log) . "\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Close connection
$conn->close();
?>
