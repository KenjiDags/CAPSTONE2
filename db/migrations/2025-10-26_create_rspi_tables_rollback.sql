-- Rollback script for RSPI tables

-- First, check if the tables exist to avoid errors
SET @table_exists = (SELECT COUNT(*) 
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name IN ('rspi_items', 'rspi_reports'));

-- Only proceed if the tables exist
SET @sql = IF(@table_exists > 0, 'SET FOREIGN_KEY_CHECKS = 0;', 'SELECT "Tables do not exist, nothing to rollback.";');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop triggers if they exist
DROP TRIGGER IF EXISTS before_rspi_item_insert;
DROP TRIGGER IF EXISTS before_rspi_item_update;

-- Drop tables if they exist (in correct order due to foreign key constraints)
DROP TABLE IF EXISTS `rspi_items`;
DROP TABLE IF EXISTS `rspi_reports`;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Remove migration record
DELETE FROM `schema_migrations` 
WHERE `migration` = '2025-10-26_create_rspi_tables.sql';

-- Log rollback completion
INSERT INTO `migration_logs` (`migration`, `action`, `performed_at`, `details`)
VALUES (
    '2025-10-26_create_rspi_tables.sql',
    'rollback',
    NOW(),
    'Removed RSPI tables and related objects'
);