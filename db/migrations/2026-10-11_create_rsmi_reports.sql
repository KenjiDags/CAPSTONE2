CREATE TABLE IF NOT EXISTS rsmi_reports (
        rsmi_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        serial_no VARCHAR(40) NULL,
        report_date DATE NOT NULL,
        custodian_name VARCHAR(255) NOT NULL,
        accounting_staff VARCHAR(255) NOT NULL,
        total_issued DECIMAL(18,2) NOT NULL,
        total_amount DECIMAL(18,2) NOT NULL,
        item_count INT UNSIGNED NOT NULL,
        snapshot LONGTEXT NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY rsmi_serial (serial_no),
        KEY rsmi_date (report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
