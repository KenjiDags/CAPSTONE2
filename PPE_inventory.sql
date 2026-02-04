CREATE DATABASE IF NOT EXISTS tesda_inventory;
USE tesda_inventory;

CREATE TABLE ppe_property (
    id INT PRIMARY KEY AUTO_INCREMENT,

    date_acquired DATE NOT NULL,
    par_no VARCHAR(50) NOT NULL,                    
    ppe_property_no VARCHAR(50) NOT NULL UNIQUE,
    item_name TEXT NOT NULL,        
    item_description TEXT NOT NULL,

    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'unit',

    acquisition_cost DECIMAL(15,2) NOT NULL,
    accumulated_depreciation DECIMAL(15,2) DEFAULT 0.00,
    
    custodian VARCHAR(255) NOT NULL,
    entity_name VARCHAR(255) NOT NULL,

    last_transfer_date DATE NULL,
    last_transfer_from VARCHAR(255) NULL,
    last_transfer_to VARCHAR(255) NULL,
    ptr_no VARCHAR(50) NULL,

    `condition` ENUM('Good', 'Fair', 'Poor', 'Unserviceable') DEFAULT 'Good',
    status ENUM('Active', 'Transferred', 'Returned', 'For Repair', 'Unserviceable', 'Disposed') DEFAULT 'Active',

    disposal_date DATE NULL,
    disposal_document_no VARCHAR(50) NULL,
    disposal_officer VARCHAR(255) NULL,

    fund_cluster VARCHAR(10) DEFAULT '101',
    remarks TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);