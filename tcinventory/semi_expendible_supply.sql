

-- Create ICS header table (matching RIS structure)
CREATE TABLE IF NOT EXISTS ics (
    ics_id INT(11) NOT NULL AUTO_INCREMENT,
    ics_no VARCHAR(50) NOT NULL,
    entity_name VARCHAR(255) NOT NULL,
    fund_cluster VARCHAR(100) NOT NULL,
    date_issued DATE NOT NULL,
    received_by VARCHAR(255) NOT NULL,
    received_by_position VARCHAR(255) NOT NULL,
    received_from VARCHAR(255) NOT NULL,
    received_from_position VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ics_id),
    UNIQUE KEY unique_ics_no (ics_no)
);

-- Create ICS items table (matching RIS items structure)
CREATE TABLE IF NOT EXISTS ics_items (
    ics_item_id INT(11) NOT NULL AUTO_INCREMENT,
    ics_id INT(11) NOT NULL,
    stock_number VARCHAR(50) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    inventory_item_no VARCHAR(100),
    estimated_useful_life VARCHAR(100),
    serial_number VARCHAR(100),
    PRIMARY KEY (ics_item_id),
    FOREIGN KEY (ics_id) REFERENCES ics(ics_id) ON DELETE CASCADE,
    INDEX idx_stock_number (stock_number)
);



-- Note: You may need to add a 'category' column to your existing 'items' table to distinguish semi-expendable items
-- ALTER TABLE items ADD COLUMN category VARCHAR(100) DEFAULT 'Consumable';
UPDATE items SET category = 'Semi-Expendable' WHERE item_name LIKE '%computer%' OR item_name LIKE '%printer%' OR item_name LIKE '%chair%' OR item_name LIKE '%table%' OR item_name LIKE '%equipment%';
    DROP TABLE IF EXISTS `ics_items`;
    DROP TABLE IF EXISTS `ics`;
    CREATE TABLE `ics` (
    ics_id INT(11) NOT NULL AUTO_INCREMENT,
    ics_no VARCHAR(50) NOT NULL,
    entity_name VARCHAR(255) NOT NULL,
    fund_cluster VARCHAR(100) NOT NULL,
    date_issued DATE NOT NULL,
    received_by VARCHAR(255) NOT NULL,
    received_by_position VARCHAR(255) NOT NULL,
    received_from VARCHAR(255) NOT NULL,
    received_from_position VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ics_id),
    UNIQUE KEY unique_ics_no (ics_no)
);
    

-- Create ICS items table
CREATE TABLE IF NOT EXISTS ics_items (
    ics_item_id INT(11) NOT NULL AUTO_INCREMENT,
    ics_id INT(11) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    inventory_item_no VARCHAR(100),
    estimated_useful_life VARCHAR(100),
    serial_number VARCHAR(100),
    PRIMARY KEY (ics_item_id),
    FOREIGN KEY (ics_id) REFERENCES ics(ics_id) ON DELETE CASCADE
);

  DROP TABLE IF EXISTS `property_cards`;
-- Property Cards table for semi-expendable supplies
  CREATE TABLE `property_cards` (
    `pc_id` int(11) NOT NULL AUTO_INCREMENT,
    `entity_name` varchar(255) NOT NULL,
    `fund_cluster` varchar(50) NOT NULL,
    `ppe_type` varchar(255) NOT NULL COMMENT 'Property, Plant and Equipment type',
    `description` text NOT NULL COMMENT 'Description of the PPE (brand, size, color, serial no., etc.)',
    `property_number` varchar(100) NOT NULL COMMENT 'Number assigned by Supply/Property Division',
    `transaction_date` date NOT NULL COMMENT 'Date of acquisition/issue/transfer/disposal',
    `reference_par_no` varchar(100) DEFAULT NULL COMMENT 'Reference document or PAR number',
    `receipt_qty` decimal(10,2) DEFAULT 0.00 COMMENT 'Quantity received',
    `issue_qty` decimal(10,2) DEFAULT 0.00 COMMENT 'Quantity issued/transferred/disposed',
    `office_officer` varchar(255) DEFAULT NULL COMMENT 'Receiving office/officer name',
    `amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount of PPE',
    `remarks` text DEFAULT NULL COMMENT 'Important information or comments',
    `transaction_type` enum('receipt','issue','transfer','disposal') NOT NULL DEFAULT 'receipt',
    `created_by` int(11) DEFAULT NULL,
    `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
    `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`pc_id`),
    KEY `idx_entity_fund` (`entity_name`, `fund_cluster`),
    KEY `idx_property_number` (`property_number`),
    KEY `idx_ppe_type` (`ppe_type`),
    KEY `idx_transaction_date` (`transaction_date`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- Sample data for testing
  INSERT INTO `property_cards` (`entity_name`, `fund_cluster`, `ppe_type`, `description`, `property_number`, `transaction_date`, `reference_par_no`, `receipt_qty`, `issue_qty`, `office_officer`, `amount`, `remarks`, `transaction_type`) VALUES
  ('Department of Education', '01', 'Office Equipment', 'Desktop Computer - Dell OptiPlex 3070, Intel Core i5, 8GB RAM, 256GB SSD, Serial: DL123456', 'PPE-2024-001', '2024-01-15', 'IAR-2024-001', 1.00, 0.00, NULL, 45000.00, 'Brand new unit for Admin Office', 'receipt'),
  ('Department of Education', '01', 'Office Equipment', 'Desktop Computer - Dell OptiPlex 3070, Intel Core i5, 8GB RAM, 256GB SSD, Serial: DL123456', 'PPE-2024-001', '2024-01-20', 'PAR-2024-001', 0.00, 1.00, 'Admin Office - John Doe', 45000.00, 'Issued to Admin Office', 'issue'),
  ('Department of Education', '01', 'Furniture and Fixtures', 'Office Chair - Ergonomic Swivel Chair, Black Leather, Model: EC-2024', 'PPE-2024-002', '2024-01-16', 'IAR-2024-002', 5.00, 0.00, NULL, 12500.00, 'Set of 5 office chairs', 'receipt'),
  ('Department of Education', '01', 'Furniture and Fixtures', 'Office Chair - Ergonomic Swivel Chair, Black Leather, Model: EC-2024', 'PPE-2024-002', '2024-01-22', 'PAR-2024-002', 0.00, 3.00, 'HR Department - Jane Smith', 7500.00, 'Issued 3 chairs to HR Dept', 'issue'),
  ('Department of Education', '02', 'IT Equipment', 'Printer - HP LaserJet Pro M404dn, Monochrome, Network Ready, Serial: HP789012', 'PPE-2024-003', '2024-01-18', 'IAR-2024-003', 2.00, 0.00, NULL, 24000.00, 'Network printers for offices', 'receipt'),
  ('Department of Education', '02', 'IT Equipment', 'Printer - HP LaserJet Pro M404dn, Monochrome, Network Ready, Serial: HP789012', 'PPE-2024-003', '2024-01-25', 'PAR-2024-003', 0.00, 1.00, 'Finance Office - Mike Johnson', 12000.00, 'Assigned to Finance Office', 'issue'),
  ('Department of Education', '01', 'Appliances', 'Air Conditioning Unit - Split Type 1.5HP, Inverter, Brand: Samsung, Model: AR12NVFXAWKNEU', 'PPE-2024-004', '2024-01-20', 'IAR-2024-004', 1.00, 0.00, NULL, 35000.00, 'For conference room installation', 'receipt'),
  ('Department of Education', '01', 'Office Equipment', 'Filing Cabinet - 4-Drawer Steel Cabinet, Gray Color, with Lock', 'PPE-2024-005', '2024-01-22', 'IAR-2024-005', 3.00, 0.00, NULL, 18000.00, 'Storage cabinets for documents', 'receipt'),
  ('Department of Education', '01', 'Office Equipment', 'Filing Cabinet - 4-Drawer Steel Cabinet, Gray Color, with Lock', 'PPE-2024-005', '2024-01-28', 'PAR-2024-004', 0.00, 2.00, 'Records Office - Sarah Wilson', 12000.00, 'Transferred to Records Office', 'transfer'),
  ('Department of Education', '02', 'IT Equipment', 'Laptop Computer - Lenovo ThinkPad E14, Intel i7, 16GB RAM, 512GB SSD', 'PPE-2024-006', '2024-02-01', 'IAR-2024-006', 4.00, 0.00, NULL, 200000.00, 'Mobile workstations for staff', 'receipt'),
  ('Department of Education', '02', 'IT Equipment', 'Laptop Computer - Lenovo ThinkPad E14, Intel i7, 16GB RAM, 512GB SSD', 'PPE-2024-006', '2024-02-05', 'PAR-2024-005', 0.00, 2.00, 'IT Department - Alex Brown', 100000.00, 'Issued to IT staff for field work', 'issue');


  -- Create semi_expendable_property table
CREATE TABLE semi_expendable_property (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    ics_rrsp_no VARCHAR(50) NOT NULL,
    semi_expendable_property_no VARCHAR(50) NOT NULL,
    item_description TEXT NOT NULL,
    estimated_useful_life INT DEFAULT 5,
    quantity_issued INT DEFAULT 0,
    office_officer_issued VARCHAR(255),
    quantity_returned INT DEFAULT 0,
    office_officer_returned VARCHAR(255),
    quantity_reissued INT DEFAULT 0,
    office_officer_reissued VARCHAR(255),
    quantity_disposed INT DEFAULT 0,
    quantity_balance INT DEFAULT 0,
    amount_total DECIMAL(10,2) DEFAULT 0.00,
    category ENUM('Other PPE', 'Office Equipment', 'ICT Equipment', 'Communication Equipment', 'Furniture and Fixtures') NOT NULL,
    fund_cluster VARCHAR(10) DEFAULT '101',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample data from the registry images

-- Other PPE Category
INSERT INTO semi_expendable_property (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity_issued, office_officer_issued, quantity_balance, amount_total, category) VALUES
('2022-12-09', '22-20', 'HV-22-101-31', 'Water Dispenser (hot, cold and normal) with bottom load, Everest, model ETWD601BL', 5, 1, 'Billy F. Balingit', 1, 9400.00, 'Other PPE'),
('2022-05-10', '22-28', 'LV-22-101-41', 'Whiteboard with aluminum frame and stand, 2x3', 5, 1, 'Belma G. Angoting', 1, 4350.00, 'Other PPE'),
('2022-05-10', '22-29', 'LV-22-101-42', 'Whiteboard with aluminum frame and stand, 2x3', 5, 1, 'Mary Jane C. Bernales', 1, 4350.00, 'Other PPE'),
('2022-02-07', '22-30', 'HV-22-101-43 to 44', 'Bathroom Toilet Tissue, Panasonic model DG4HSJP', 5, 2, 'Billy F. Balingit', 2, 17000.00, 'Other PPE'),
('2022-10-18', '22-32', 'HV-22-101-46', 'Water Dispenser (hot, cold and normal) with bottom load, Everest, model ETWD601BL', 5, 1, 'Billy F. Balingit', 1, 9400.00, 'Other PPE'),
('2022-06-12', '22-37', 'LV-22-101-54', 'Coffee Urn Percolator, Boiler, 40 cups capacity, Dynamex 632', 5, 1, 'Mary Jane C. Bernales', 1, 4467.00, 'Other PPE');

-- Office Equipment Category
INSERT INTO semi_expendable_property (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity_issued, office_officer_issued, quantity_balance, amount_total, category) VALUES
('2022-09-03', '22-01', 'LV-22-101-02 to 03', 'WEBCAM FULL HD, A4 TECH, PK-940HA; True 1080p @ 30fps or true 720p @ 60fps; autofocus with fixed mounting clip', 5, 2, 'Daisy D. Jamorabon', 2, 7300.00, 'Office Equipment'),
('2022-09-03', '22-01', 'LV-22-101-04', 'TRIPOD, camera; universal; 2 m or longer; stable', 5, 1, 'Daisy D. Jamorabon', 1, 950.00, 'Office Equipment'),
('2022-11-03', '22-02', 'HV-22-101-05', 'Webcam Full HD, Logitech, True 1080p @ 30fps or 720p@60fps autofocus with fix mounting clip', 5, 1, 'Susana G. Carbonell', 1, 6500.00, 'Office Equipment'),
('2022-11-03', '22-03', 'LV-22-101-06 to 10', 'Computer Headset (wired); HD; A4 tech AU-7P', 5, 5, 'Susana G. Carbonell', 5, 5500.00, 'Office Equipment'),
('2022-11-03', '22-03', 'HV-22-101-11 to 12', 'Wireless Directional Speaker', 5, 2, 'Susana G. Carbonell', 2, 18800.00, 'Office Equipment'),
('2022-07-26', '22-11', 'LV-22-101-19', 'Dryesal Stamp(New TESDA logo)', 5, 1, 'Mabelle G. Panganiban', 1, 2000.00, 'Office Equipment');

-- ICT Equipment Category  
INSERT INTO semi_expendable_property (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity_issued, office_officer_issued, quantity_balance, amount_total, category) VALUES
('2022-09-03', '22-01', 'HV-22-101-01', 'Printer, multi function all in one, HP 3777, deskjet ink advantage, sn: CN19F7J950', 5, 1, 'Daisy D. Jamorabon', 1, 5995.00, 'ICT Equipment'),
('2022-03-28', '22-04', 'HV-22-101-13', 'MONITOR, desktop computer; 24" - 27" minimum; LED; Viewsonic VA; 2415 series; sn: V2M192000234', 5, 1, 'Brenda A. Hiano', 1, 9000.00, 'ICT Equipment'),
('2022-07-29', '22-15', 'HV-22-101-01', 'Video processor, 2GB NVIDIA GeForce Operating', 5, 1, 'Mary Ann L. Gavlican', 1, 49500.00, 'ICT Equipment'),
('2022-10-09', '22-16', 'HV-22-101-27', 'Printer, flatbed with color CIS, Epson Eco Tank L3250', 5, 1, 'Stephanie Nicole S. Pelioman', 1, 10595.00, 'ICT Equipment'),
('2022-08-19', '22-18', 'HV-22-101-30', 'All in one desktop computer, ACER Aspire C24-1750, sn: DBJLQSP01290950000', 5, 1, 'Belmar G. Angoting', 1, 48500.00, 'ICT Equipment');

-- Communication Equipment Category
INSERT INTO semi_expendable_property (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity_issued, office_officer_issued, quantity_balance, amount_total, category) VALUES
('2022-04-05', '22-07', 'HV-22-101-16', 'Cellular Phone, Android, Samsung M 52, 5G + LTE +Wifi', 5, 1, 'Belmar G. Angoting', 1, 14800.00, 'Communication Equipment'),
('2022-10-18', '22-33', 'HV-22-101-47', 'Mobile Phone, 256GB, IOS 16 operating system, phone 13midnight model A2633, SN: R6Q2RXNXNF6', 5, 1, 'RD Jovencio M. Ferrer Jr.', 1, 49850.00, 'Communication Equipment');

-- Furniture and Fixtures Category
INSERT INTO semi_expendable_property (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity_issued, office_officer_issued, quantity_balance, amount_total, category) VALUES
('2021-06-24', '21-16', '21-101-23', 'Chair, executive', 5, 1, 'Dante J. Navarro', 1, 9800.00, 'Furniture and Fixtures');

-- Additional entries from 2021 data (Other PPE category)
INSERT INTO semi_expendable_property (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity_issued, office_officer_issued, quantity_balance, amount_total, category) VALUES
('2021-01-12', '21-01', '21-101-01', 'Microwave Oven, American Home sn:', 5, 1, 'Mary Jane C. Bernales', 1, 7418.00, 'Other PPE'),
('2021-01-12', '21-01', '21-101-02', 'Foam Space Heater sn: 7760247', 5, 1, 'Mary Jane C. Bernales', 1, 3117.00, 'Other PPE'),
('2021-03-17', '21-05', '21-101-03', 'Coffee Percolator, 15 liters capacity, Innoflex', 5, 1, 'Daisy D. Jamorabon', 1, 5860.00, 'Other PPE'),
('2021-03-17', '21-06', '21-101-10', 'Coffee Maker, 10-15 cups capacity, tefal', 5, 1, 'Daisy D. Jamorabon', 1, 6805.00, 'Other PPE'),
('2021-05-04', '21-09', '21-101-14', 'Solar Panel', 5, 1, 'Mary Jane C. Bernales', 1, 2030.00, 'Other PPE');