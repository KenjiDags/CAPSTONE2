<?php
// One-shot patch: create semi_expendable_history table if it doesn't exist
// Usage: open http://localhost/tcinventory/db/patch_create_semi_expendable_history.php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../config.php';
header('Content-Type: text/plain');

try {
    // Determine current database
    $res = $conn->query('SELECT DATABASE() AS db');
    $row = $res->fetch_assoc();
    $dbName = $row && isset($row['db']) ? $row['db'] : '';
    if (!$dbName) {
        throw new Exception('No database selected in config.php connection.');
    }

    // Check if table exists
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'semi_expendable_history'");
    $stmt->bind_param('s', $dbName);
    $stmt->execute();
    $cntRes = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)($cntRes['cnt'] ?? 0) > 0) {
        echo "Table already exists: ${dbName}.semi_expendable_history\n";
        exit(0);
    }

    // Create table matching application expectations
    $createSql = <<<SQL
CREATE TABLE IF NOT EXISTS `semi_expendable_history` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `semi_id` INT NOT NULL,
  `date` DATE NULL,
  `ics_rrsp_no` VARCHAR(255) NULL,
  `quantity` INT DEFAULT 0,
  `quantity_issued` INT DEFAULT 0,
  `quantity_returned` INT DEFAULT 0,
  `quantity_reissued` INT DEFAULT 0,
  `quantity_disposed` INT DEFAULT 0,
  `quantity_balance` INT DEFAULT 0,
  `office_officer_issued` VARCHAR(255) NULL,
  `office_officer_returned` VARCHAR(255) NULL,
  `office_officer_reissued` VARCHAR(255) NULL,
  `amount_total` DECIMAL(15,2) DEFAULT 0,
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_semi_id` (`semi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    $conn->query($createSql);

    // Verify creation
    $verify = $conn->query("SHOW COLUMNS FROM `semi_expendable_history`");
    $cols = [];
    while ($c = $verify->fetch_assoc()) { $cols[] = $c['Field']; }

    echo "Created table: ${dbName}.semi_expendable_history\n";
    echo "Columns: " . implode(', ', $cols) . "\n";
    echo "STATUS: OK\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
    if (isset($conn) && $conn instanceof mysqli) {
        echo "MYSQLI ERRNO: " . $conn->errno . " MESSAGE: " . $conn->error . "\n";
    }
    exit(1);
}
