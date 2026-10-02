-- Create the core PPE tables when this installation has not imported PPE_inventory.sql.
-- Existing tables and records are left intact.
CREATE TABLE IF NOT EXISTS ppe_property (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name TEXT NOT NULL,
    item_description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    officer_incharge VARCHAR(255) NOT NULL,
    PPE_no VARCHAR(50) NOT NULL UNIQUE,
    property_no VARCHAR(100) NULL,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'unit',
    custodian VARCHAR(255) NOT NULL,
    entity_name VARCHAR(255) NOT NULL,
    date_acquired DATE NULL,
    `condition` ENUM('Good', 'Fair', 'Poor', 'Unserviceable') DEFAULT 'Good',
    status ENUM('Active', 'Transferred', 'Returned', 'For Repair', 'Unserviceable', 'Disposed') DEFAULT 'Active',
    fund_cluster VARCHAR(10) DEFAULT '101',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ppe_pc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_created DATE NOT NULL,
    ppe_property_no VARCHAR(50) NOT NULL UNIQUE,
    property_no VARCHAR(100) NULL,
    item_name TEXT NOT NULL,
    item_description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'unit',
    custodian VARCHAR(255) NOT NULL,
    officer VARCHAR(255) NOT NULL,
    entity_name VARCHAR(255) NOT NULL,
    fund_cluster VARCHAR(10) DEFAULT '101',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_history_ppe (
    id INT AUTO_INCREMENT PRIMARY KEY,
    PPE_no VARCHAR(50) NULL,
    property_no VARCHAR(100) NULL,
    PAR_number VARCHAR(100) NULL,
    refference_no VARCHAR(100) NULL,
    item_name VARCHAR(255) NULL,
    description TEXT NULL,
    unit VARCHAR(50) NULL,
    unit_cost DECIMAL(15,2) NULL,
    quantity_on_hand INT NULL,
    quantity_change INT NULL,
    receipt_qty INT NULL,
    issue_qty INT NULL,
    balance_qty INT NULL,
    officer_incharge VARCHAR(255) NULL,
    change_direction VARCHAR(20) NULL,
    change_type VARCHAR(50) NULL,
    unserviceable_qty INT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_property_no (property_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
