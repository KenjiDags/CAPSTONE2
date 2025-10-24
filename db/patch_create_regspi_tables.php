<?php
// Idempotent patch: Create Registry of Semi-Expendable Property Issued tables
// Usage: open in browser or run via CLI: php db/patch_create_regspi_tables.php
header('Content-Type: text/plain');

require_once __DIR__ . '/../config.php';

function run($conn, $sql, $label) {
    if ($conn->query($sql)) {
        echo "OK: $label\n";
        return true;
    } else {
        echo "ERR: $label -> " . $conn->error . "\n";
        return false;
    }
}

echo "Applying REGSPI schema patch...\n\n";

// Create header table
$sqlHeader = "CREATE TABLE IF NOT EXISTS regspi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity_name VARCHAR(255) NOT NULL,
  fund_cluster VARCHAR(100) NULL,
  semi_expendable_property VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run($conn, $sqlHeader, 'create table regspi');

// Create entries table
$sqlEntries = "CREATE TABLE IF NOT EXISTS regspi_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  regspi_id INT NOT NULL,
  `date` DATE NOT NULL,
  ics_rrsp_no VARCHAR(100) NOT NULL,
  property_no VARCHAR(100) NOT NULL,
  item_description TEXT NOT NULL,
  useful_life VARCHAR(100) NOT NULL,
  issued_qty INT NOT NULL DEFAULT 0,
  issued_office VARCHAR(255) NULL,
  returned_qty INT NOT NULL DEFAULT 0,
  returned_office VARCHAR(255) NULL,
  reissued_qty INT NOT NULL DEFAULT 0,
  reissued_office VARCHAR(255) NULL,
  disposed_qty1 INT NOT NULL DEFAULT 0,
  disposed_qty2 INT NOT NULL DEFAULT 0,
  balance_qty INT NOT NULL DEFAULT 0,
  amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  remarks TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_regspi_id (regspi_id),
  INDEX idx_date (`date`),
  INDEX idx_ics_rrsp_no (ics_rrsp_no),
  INDEX idx_property_no (property_no),
  CONSTRAINT fk_regspi_entries_header FOREIGN KEY (regspi_id)
    REFERENCES regspi(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run($conn, $sqlEntries, 'create table regspi_entries');

// Best-effort: ensure missing columns (if tables pre-existed) - ignore failures
$safes = [
  "ALTER TABLE regspi ADD COLUMN IF NOT EXISTS entity_name VARCHAR(255) NOT NULL",
  "ALTER TABLE regspi ADD COLUMN IF NOT EXISTS fund_cluster VARCHAR(100) NULL",
  "ALTER TABLE regspi ADD COLUMN IF NOT EXISTS semi_expendable_property VARCHAR(100) NULL",
  "ALTER TABLE regspi ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
  "ALTER TABLE regspi ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS `date` DATE NOT NULL",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS ics_rrsp_no VARCHAR(100) NOT NULL",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS property_no VARCHAR(100) NOT NULL",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS item_description TEXT NOT NULL",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS useful_life VARCHAR(100) NOT NULL",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS issued_qty INT NOT NULL DEFAULT 0",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS issued_office VARCHAR(255) NULL",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS returned_qty INT NOT NULL DEFAULT 0",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS returned_office VARCHAR(255) NULL",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS reissued_qty INT NOT NULL DEFAULT 0",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS reissued_office VARCHAR(255) NULL",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS disposed_qty1 INT NOT NULL DEFAULT 0",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS disposed_qty2 INT NOT NULL DEFAULT 0",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS balance_qty INT NOT NULL DEFAULT 0",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS amount DECIMAL(15,2) NOT NULL DEFAULT 0.00",
  "ALTER TABLE regspi_entries ADD COLUMN IF NOT EXISTS remarks TEXT NULL",
  "ALTER TABLE regspi_entries ADD INDEX IF NOT EXISTS idx_regspi_id (regspi_id)",
  "ALTER TABLE regspi_entries ADD INDEX IF NOT EXISTS idx_date (`date`)",
  "ALTER TABLE regspi_entries ADD INDEX IF NOT EXISTS idx_ics_rrsp_no (ics_rrsp_no)",
  "ALTER TABLE regspi_entries ADD INDEX IF NOT EXISTS idx_property_no (property_no)"
];

foreach ($safes as $q) {
    try { $conn->query($q); } catch (Throwable $e) { /* ignore */ }
}

echo "\nREGSPI patch complete.\n";

$conn->close();
?>
