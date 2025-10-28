-- Migration: Create ITR (Inventory Transfer Report) tables
-- Date: 2025-10-28
-- Notes: Matches the schema expected by add_itr.php and submit_itr.php

-- Ensure database is selected externally, e.g., USE `tesda_inventory`;

CREATE TABLE IF NOT EXISTS `itr` (
  `itr_id` INT AUTO_INCREMENT PRIMARY KEY,
  `itr_no` VARCHAR(32) NOT NULL,
  `itr_date` DATE NOT NULL,
  `entity_name` VARCHAR(255) NULL,
  `fund_cluster` VARCHAR(255) NULL,
  `from_accountable` VARCHAR(255) NULL,
  `to_accountable` VARCHAR(255) NULL,
  `transfer_type` VARCHAR(50) NULL,
  `transfer_other` VARCHAR(255) NULL,
  `reason` TEXT NULL,
  `approved_name` VARCHAR(255) NULL,
  `approved_designation` VARCHAR(255) NULL,
  `approved_date` DATE NULL,
  `released_name` VARCHAR(255) NULL,
  `released_designation` VARCHAR(255) NULL,
  `released_date` DATE NULL,
  `received_name` VARCHAR(255) NULL,
  `received_designation` VARCHAR(255) NULL,
  `received_date` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `itr_no_unique` (`itr_no`),
  KEY `idx_itr_date` (`itr_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `itr_items` (
  `itr_item_id` INT AUTO_INCREMENT PRIMARY KEY,
  `itr_id` INT NOT NULL,
  `date_acquired` DATE NULL,
  `item_no` VARCHAR(255) NULL,
  `ics_info` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `amount` DECIMAL(15,2) DEFAULT 0,
  `cond` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_item_itr_id` (`itr_id`),
  CONSTRAINT `fk_itr_items_itr` FOREIGN KEY (`itr_id`) REFERENCES `itr`(`itr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
