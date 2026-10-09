<?php
require 'config.php';
require_once 'trusted_devices.php';

// Signing out revokes this browser's trusted-device token.
$trustedToken = $_COOKIE[TRUSTED_DEVICE_COOKIE] ?? null;
if (is_string($trustedToken) && preg_match('/^[a-f0-9]{64}$/D', $trustedToken)) {
    $tokenHash = hash('sha256', $trustedToken);
    $stmt = $conn->prepare('DELETE FROM auth_trusted_devices WHERE token_hash = ?');
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $stmt->close();
}
setcookie(TRUSTED_DEVICE_COOKIE, '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);

if (!empty($_COOKIE['remember_token'])) {
    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
    $stmt->bind_param("s", $_COOKIE['remember_token']);
    $stmt->execute();
    $stmt->close();
    setcookie('remember_token', '', time() - 3600, '/', 'localhost', false, true);
}

session_start();
session_destroy();
header('Location: index.php?logged_out=1');
exit;
