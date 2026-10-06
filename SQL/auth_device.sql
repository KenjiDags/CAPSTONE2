USE tesda_inventory;
-- Import before using trusted devices. Expiry dates use UTC; raw tokens are never stored.
CREATE TABLE IF NOT EXISTS auth_trusted_devices (
    device_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    credential_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY uq_device_token (token_hash),
    KEY idx_device_user_expiry (user_id, expires_at),
    KEY idx_device_expiry (expires_at)
) ENGINE=InnoDB;
-- Optional cleanup: DELETE FROM auth_trusted_devices WHERE expires_at <= UTC_TIMESTAMP();