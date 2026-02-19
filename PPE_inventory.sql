CREATE DATABASE IF NOT EXISTS tesda_inventory;
USE tesda_inventory;

/* Table for Personal Protective Equipment (PPE) Inventory */
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
    date_acquired DATE NULL,

    `condition` ENUM('Good', 'Fair', 'Poor', 'Unserviceable') DEFAULT 'Good',
    status ENUM('Active', 'Transferred', 'Returned', 'For Repair', 'Unserviceable', 'Disposed') DEFAULT 'Active',

    fund_cluster VARCHAR(10) DEFAULT '101',
    remarks TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

/* Table for Property Custodian Slip (PCS) */
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

/* Table for Property Transfer Receipt (PTR) */
CREATE TABLE ppe_ptr (
    ptr_id INT AUTO_INCREMENT PRIMARY KEY,
    ptr_no VARCHAR(100) NOT NULL,
    entity_name VARCHAR(255) DEFAULT 'TESDA Regional Office',
    fund_cluster VARCHAR(100) DEFAULT '101',
    from_officer VARCHAR(255),
    to_officer VARCHAR(255),
    transfer_date DATE,
    transfer_type VARCHAR(100),
    reason TEXT,
    approved_by VARCHAR(255),
    approved_by_designation VARCHAR(255),
    approved_by_date DATE,
    released_by VARCHAR(255),
    released_by_designation VARCHAR(255),
    released_by_date DATE,
    received_by VARCHAR(255),
    received_by_designation VARCHAR(255),
    received_by_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE ppe_ptr_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ptr_id INT NOT NULL,
    ppe_id INT NOT NULL,
    FOREIGN KEY (ptr_id) REFERENCES ppe_ptr(ptr_id) ON DELETE CASCADE,
    FOREIGN KEY (ppe_id) REFERENCES ppe_property(id) ON DELETE CASCADE
);


/* Table for Property Acknowledgment Receipt (PAR) */
CREATE TABLE ppe_par (
    par_id INT AUTO_INCREMENT PRIMARY KEY,
    par_no VARCHAR(100) NOT NULL UNIQUE,
    entity_name VARCHAR(255) DEFAULT 'TESDA Regional Office',
    fund_cluster VARCHAR(100) DEFAULT '101',
    date_acquired DATE,
    property_number VARCHAR(100),
    received_by VARCHAR(255),
    received_by_designation VARCHAR(255),
    received_by_date DATE,
    issued_by VARCHAR(255),
    issued_by_designation VARCHAR(255),
    issued_by_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

/* Table for PAR items (junction table for linking PAR to multiple properties) */
CREATE TABLE ppe_par_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    par_id INT NOT NULL,
    ppe_id INT NOT NULL,
    FOREIGN KEY (par_id) REFERENCES ppe_par(par_id) ON DELETE CASCADE,
    FOREIGN KEY (ppe_id) REFERENCES ppe_property(id) ON DELETE CASCADE
);

/* Table for Report on Physical Count of Property, Plant and Equipment (RPCPPE) */
DROP TABLE IF EXISTS rpcppe;
CREATE TABLE IF NOT EXISTS rpcppe (
    rpcppe_id INT AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL,
    fund_cluster VARCHAR(100) DEFAULT '101',
    accountable_officer VARCHAR(255),
    official_designation VARCHAR(255),
    entity_name VARCHAR(255) DEFAULT 'TESDA Regional Office',
    assumption_date DATE,
    certified_by VARCHAR(255),
    approved_by VARCHAR(255),
    verified_by VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

/* Table for RPCPPE items (physical count data for each PPE item) */
DROP TABLE IF EXISTS rpcppe_items;
CREATE TABLE IF NOT EXISTS rpcppe_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rpcppe_id INT NOT NULL,
    ppe_id INT NOT NULL,
    on_hand_per_count INT DEFAULT 0,
    shortage_overage_qty INT DEFAULT 0,
    shortage_overage_value DECIMAL(10,2) DEFAULT 0.00,
    remarks TEXT,
    FOREIGN KEY (rpcppe_id) REFERENCES rpcppe(rpcppe_id) ON DELETE CASCADE,
    FOREIGN KEY (ppe_id) REFERENCES ppe_property(id) ON DELETE CASCADE
);