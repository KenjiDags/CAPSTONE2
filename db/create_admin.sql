-- Standalone setup for the database used by config.php.
-- Demo credentials: admin / admin123. Change the password before deployment.
-- The password is a PHP password_hash() value, not plaintext.
-- Re-running this file resets admin's password.

CREATE DATABASE IF NOT EXISTS tesda_inventory
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tesda_inventory;

CREATE TABLE IF NOT EXISTS users (
    user_id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(250) NOT NULL,
    full_name VARCHAR(255) NULL,
    user_full_name VARCHAR(255) NULL,
    user_position VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    remember_token VARCHAR(255) NULL,
    role VARCHAR(16) NOT NULL DEFAULT 'user',
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    removed_at DATETIME NULL,
    removed_from_status VARCHAR(16) NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY username (username),
    UNIQUE KEY unique_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CREATE TABLE does not change an older users table, so add missing columns.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS user_full_name VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS user_position VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS remember_token VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS role VARCHAR(16) NOT NULL DEFAULT 'user',
    ADD COLUMN IF NOT EXISTS status VARCHAR(16) NOT NULL DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS removed_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS removed_from_status VARCHAR(16) NULL;

ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS unique_user_email (email);

INSERT INTO users (username, password, full_name, role, status)
VALUES (
    'admin',
    '$2y$10$QulA.kft0OBoeo2sf6u5pOXnRmUxVpcphVG4G1BFsCFCivJjFkFW6',
    'Administrator',
    'admin',
    'active'
)
ON DUPLICATE KEY UPDATE
    password = VALUES(password),
    role = 'admin',
    status = 'active';
