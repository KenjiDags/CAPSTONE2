<?php
const TRUSTED_DEVICE_COOKIE = 'tesda_trusted_device';
function trustedDeviceFingerprint(string $password, string $secret): string {
    return hash('sha256', $password . ':' . $secret);
}
function trustedDeviceValid(mysqli $conn, int $id, string $fingerprint): bool {
    $token = $_COOKIE[TRUSTED_DEVICE_COOKIE] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) return false;
    try {
        $hash = hash('sha256', $token);
        $stmt = $conn->prepare('SELECT credential_hash FROM auth_trusted_devices WHERE user_id = ? AND token_hash = ? AND expires_at > UTC_TIMESTAMP()');
        if (!$stmt) return false;
        $stmt->bind_param('is', $id, $hash); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return $row && hash_equals($row['credential_hash'], $fingerprint);
    } catch (mysqli_sql_exception $e) {
        error_log('Trusted device lookup failed: ' . $e->getMessage()); return false;
    }
}
function trustedDeviceCreate(mysqli $conn, int $id, string $fingerprint): bool {
    $token = bin2hex(random_bytes(32)); $hash = hash('sha256', $token);
    $expires = time() + 30 * 24 * 60 * 60; $date = gmdate('Y-m-d H:i:s', $expires);
    try {
        $stmt = $conn->prepare('INSERT INTO auth_trusted_devices (user_id, token_hash, credential_hash, expires_at) VALUES (?, ?, ?, ?)');
        if (!$stmt) return false;
        $stmt->bind_param('isss', $id, $hash, $fingerprint, $date);
        $saved = $stmt->execute(); $stmt->close();
        return $saved && setcookie(TRUSTED_DEVICE_COOKIE, $token, ['expires' => $expires, 'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true, 'samesite' => 'Strict']);
    } catch (mysqli_sql_exception $e) {
        error_log('Trusted device creation failed: ' . $e->getMessage()); return false;
    }
}
function completeDeviceLogin(mysqli $conn, int $id, array $account): void {
    if ($account['status'] === 'approved') {
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND status = 'approved'");
        $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    }
    unset($_SESSION['totp_pending'], $_SESSION['trust_pending'], $_SESSION['login_csrf']);
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id; $_SESSION['username'] = $account['username']; $_SESSION['role'] = $account['role'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? ''; $_SESSION['logged_in'] = true;
    header('Location: ' . ($account['role'] === 'admin' ? 'admin.php' : 'analytics.php')); exit;
}