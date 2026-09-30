-- Run db/migrate_user_access.php before importing this file.
-- Demo credentials: admin / admin123. Change the password before deployment.
-- The password is a PHP password_hash() value, not plaintext.
-- An existing username 'admin' is left unchanged.

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
