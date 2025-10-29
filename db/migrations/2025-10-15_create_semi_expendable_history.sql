-- Idempotent creation of semi_expendable_history table
-- Safe to run multiple times; only creates if missing

SET @DB := DATABASE();

-- Create table if it does not exist
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
