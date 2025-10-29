-- Registry of Semi-Expendable Property Issued (REGSPI)
-- Header + Entries tables
-- Run this in your tcinventory database

START TRANSACTION;

-- Header table (one row per registry header/config)
CREATE TABLE IF NOT EXISTS regspi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity_name VARCHAR(255) NOT NULL,
  fund_cluster VARCHAR(100) NULL,
  semi_expendable_property VARCHAR(100) NULL, -- e.g., ICT Equipment, Office Equipment
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Entries table (one row per line item/action)
CREATE TABLE IF NOT EXISTS regspi_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  regspi_id INT NOT NULL,
  `date` DATE NOT NULL,
  ics_rrsp_no VARCHAR(100) NOT NULL,
  property_no VARCHAR(100) NOT NULL, -- Semi-Expendable Property No.
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;