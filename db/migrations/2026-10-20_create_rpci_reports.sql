
-- Saved RPCI documents preserve the report metadata and inventory snapshot.
CREATE TABLE IF NOT EXISTS `rpci_reports` (
  `rpci_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `report_date` DATE NOT NULL,
  `entity_name` VARCHAR(255) NOT NULL,
  `accountable_officer` VARCHAR(255) NOT NULL,
  `item_count` INT UNSIGNED NOT NULL,
  `snapshot` LONGTEXT NOT NULL,
  `created_by` INT NOT NULL,
  `save_token` CHAR(64) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `rpci_save_token` (`save_token`),
  KEY `rpci_date` (`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;