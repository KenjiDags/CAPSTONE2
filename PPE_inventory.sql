CREATE DATABASE IF NOT EXISTS tesda_inventory;
USE tesda_inventory;

CREATE TABLE ppe_property (
    id INT PRIMARY KEY AUTO_INCREMENT,

    par_no VARCHAR(50) NOT NULL,                    
    item_name TEXT NOT NULL,        
    item_description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    officer_incharge VARCHAR(255) NOT NULL,

    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'unit',
    
    custodian VARCHAR(255) NOT NULL,
    entity_name VARCHAR(255) NOT NULL,

    ptr_no VARCHAR(50) NULL,

    `condition` ENUM('Good', 'Fair', 'Poor', 'Unserviceable') DEFAULT 'Good',
    status ENUM('Active', 'Transferred', 'Returned', 'For Repair', 'Unserviceable', 'Disposed') DEFAULT 'Active',

    fund_cluster VARCHAR(10) DEFAULT '101',
    remarks TEXT
);

CREATE TABLE ppe_pc (
    id INT PRIMARY KEY AUTO_INCREMENT,

    date_created DATE NOT NULL,
    par_no VARCHAR(50) NOT NULL,                    
    ppe_property_no VARCHAR(50) NOT NULL UNIQUE,
    item_name TEXT NOT NULL,        
    item_description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,

    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'unit',
    
    custodian VARCHAR(255) NOT NULL,
    officer VARCHAR(255) NOT NULL,
    entity_name VARCHAR(255) NOT NULL,

    ptr_no VARCHAR(50) NULL,

    fund_cluster VARCHAR(10) DEFAULT '101',
    remarks TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);