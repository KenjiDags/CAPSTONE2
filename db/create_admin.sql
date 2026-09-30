-- Run db/migrate_user_access.php before importing this file.
-- Demo credentials: admin / admin123. Change the password before deployment.
-- The password is a PHP password_hash() value, not plaintext.
-- An existing username 'admin' is left unchanged.

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
    totp_secret VARCHAR(64) NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY username (username),
    UNIQUE KEY unique_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (username, password, full_name, role, status, totp_secret)
SELECT
    'admin',
    '$2y$10$QulA.kft0OBoeo2sf6u5pOXnRmUxVpcphVG4G1BFsCFCivJjFkFW6',
    'Administrator',
    'admin',
    'approved',
    NULL
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE username = 'admin'
);
