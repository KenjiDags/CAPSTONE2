-- Migration: add base quantity column to semi_expendable_property
-- Safe to run multiple times
CREATE DATABASE IF NOT EXISTS `tesda_inventory` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `tesda_inventory`;

-- Add `quantity` column if missing
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'semi_expendable_property'
    AND COLUMN_NAME = 'quantity'
);

SET @sql := IF(@col_exists = 0,
    'ALTER TABLE semi_expendable_property ADD COLUMN quantity INT DEFAULT 0 AFTER estimated_useful_life;','SELECT "quantity column already exists" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure table engine and charset are consistent
-- Optional normalize (commented out by default)
-- ALTER TABLE semi_expendable_property ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
