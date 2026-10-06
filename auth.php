<?php
// Start session only if not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Check if user is logged in and has a valid session
if (
    empty($_SESSION['logged_in']) || 
    $_SESSION['logged_in'] !== true ||
    empty($_SESSION['user_id'])
) {
    // Destroy any existing session just in case
    session_unset();
    session_destroy();
    
    // Redirect to login page
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$accessStmt = $conn->prepare('SELECT username, role, status FROM users WHERE user_id = ? LIMIT 1');
$accessStmt->bind_param('i', $_SESSION['user_id']);
$accessStmt->execute();
$accessUser = $accessStmt->get_result()->fetch_assoc();
$accessStmt->close();
if (!$accessUser || $accessUser['status'] !== 'active') {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}
require_once 'totp.php';
totpTable($conn);
$factorStmt = $conn->prepare('SELECT 1 FROM user_totp WHERE user_id = ? AND enabled_at IS NOT NULL');
$factorStmt->bind_param('i', $_SESSION['user_id']);
$factorStmt->execute();
$factorEnabled = $factorStmt->get_result()->num_rows > 0;
$factorStmt->close();
if (!$factorEnabled) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}
$_SESSION['role'] = $accessUser['role'];
$_SESSION['username'] = $accessUser['username'];
if (!empty($_SESSION['totp_new_recovery']) && !defined('RECOVERY_PAGE')) {
    header('Location: totp_recovery.php');
    exit;
}
if ($accessUser['role'] === 'admin' && !defined('ADMIN_PAGE') && !defined('ACCOUNT_PAGE')) {
    header('Location: admin.php');
    exit;
}
if ($accessUser['role'] !== 'admin' && defined('ADMIN_PAGE') && !defined('ACCOUNT_PAGE')) {
    header('Location: analytics.php');
    exit;
}

// Optional extra security: check if the user agent matches
if (
    isset($_SESSION['user_agent']) && 
    $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')
) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}
