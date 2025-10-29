-- Create RSPI Reports table
CREATE TABLE IF NOT EXISTS `rspi_reports` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `serial_no` varchar(20) NOT NULL COMMENT 'Format: YYYY-MM-XXXX',
    `entity_name` varchar(255) NOT NULL,
    `fund_cluster` varchar(100) NOT NULL,
    `report_date` date NOT NULL,
    `custodian_name` varchar(255) NOT NULL COMMENT 'Property and/or Supply Custodian',
    `posted_by` varchar(255) NOT NULL COMMENT 'Designated Accounting Staff',
    `status` enum('draft','posted','archived') NOT NULL DEFAULT 'draft',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL COMMENT 'User ID who created the report',
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` int(11) DEFAULT NULL COMMENT 'User ID who last updated the report',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_serial_no` (`serial_no`),
    KEY `idx_report_date` (`report_date`),
    KEY `idx_status` (`status`),
    KEY `idx_created_by` (`created_by`),
    CONSTRAINT `fk_rspi_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_rspi_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Report of Semi-Expendable Property Issued main records';

-- Create RSPI Items table
CREATE TABLE IF NOT EXISTS `rspi_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `rspi_id` int(11) NOT NULL,
    `ics_no` varchar(50) NOT NULL COMMENT 'Reference to ICS number',
    `responsibility_center` varchar(100) NOT NULL,
    `property_no` varchar(50) NOT NULL,
    `item_description` text NOT NULL,
    `unit` varchar(50) NOT NULL,
    `quantity_issued` decimal(10,2) NOT NULL DEFAULT '0.00',
    `unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
    `amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'quantity_issued * unit_cost',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rspi_id` (`rspi_id`),
    KEY `idx_ics_no` (`ics_no`),
    CONSTRAINT `fk_rspi_items_report` FOREIGN KEY (`rspi_id`) REFERENCES `rspi_reports` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_amount` CHECK (`amount` = `quantity_issued` * `unit_cost`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual items in RSPI reports';

-- Add triggers to maintain data integrity

-- Trigger to calculate amount before insert
DELIMITER //
CREATE TRIGGER before_rspi_item_insert
BEFORE INSERT ON rspi_items
FOR EACH ROW
BEGIN
    SET NEW.amount = NEW.quantity_issued * NEW.unit_cost;
END//

-- Trigger to calculate amount before update
CREATE TRIGGER before_rspi_item_update
BEFORE UPDATE ON rspi_items
FOR EACH ROW
BEGIN
    SET NEW.amount = NEW.quantity_issued * NEW.unit_cost;
END//
DELIMITER ;

-- Sample Data Insertions

-- Insert sample RSPI reports
INSERT INTO `rspi_reports` 
(`serial_no`, `entity_name`, `fund_cluster`, `report_date`, `custodian_name`, `posted_by`, `status`, `created_by`) 
VALUES 
('2025-10-001', 'TESDA RTC Iligan', '101', '2025-10-15', 'John Smith', 'Maria Garcia', 'posted', 1),
('2025-10-002', 'TESDA RTC Iligan', '101', '2025-10-20', 'John Smith', 'Maria Garcia', 'posted', 1),
('2025-10-003', 'TESDA RTC Iligan', '102', '2025-10-25', 'John Smith', 'Maria Garcia', 'draft', 1);

-- Insert sample RSPI items
INSERT INTO `rspi_items` 
(`rspi_id`, `ics_no`, `responsibility_center`, `property_no`, `item_description`, `unit`, `quantity_issued`, `unit_cost`) 
VALUES 
-- Items for RSPI #2025-10-001
(1, 'ICS-2025-001', 'IT Department', 'PROP-001', 'Desktop Computer Set', 'unit', 2, 45000.00),
(1, 'ICS-2025-002', 'IT Department', 'PROP-002', 'LaserJet Printer', 'unit', 1, 15000.00),
(1, 'ICS-2025-003', 'IT Department', 'PROP-003', 'UPS 650VA', 'unit', 2, 2500.00),

-- Items for RSPI #2025-10-002
(2, 'ICS-2025-004', 'Admin Office', 'PROP-004', 'Executive Chair', 'piece', 3, 5000.00),
(2, 'ICS-2025-005', 'Admin Office', 'PROP-005', 'Filing Cabinet', 'unit', 2, 8000.00),

-- Items for RSPI #2025-10-003
(3, 'ICS-2025-006', 'Training Department', 'PROP-006', 'Projector Screen', 'unit', 1, 12000.00),
(3, 'ICS-2025-007', 'Training Department', 'PROP-007', 'Whiteboard', 'piece', 2, 3500.00),
(3, 'ICS-2025-008', 'Training Department', 'PROP-008', 'Training Tables', 'piece', 10, 2000.00);

-- Insert this migration record
INSERT INTO `schema_migrations` (`migration`, `migrated_at`) 
VALUES ('2025-10-26_create_rspi_tables.sql', NOW());