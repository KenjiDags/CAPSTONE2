<?php
session_start();

// The application entry point always starts at the login form.
if (!empty($_SESSION) && !isset($_POST['totp_login']) && !isset($_POST['trust_confirm']) && !isset($_GET['code']) && !isset($_GET['trust'])) {
    $loginCsrf = $_SESSION['login_csrf'] ?? null;
    session_unset();
    session_destroy();
    session_start();
    if ($loginCsrf) $_SESSION['login_csrf'] = $loginCsrf;
}

require 'config.php';
require_once 'totp.php';
totpTable($conn);
require_once 'trusted_devices.php';
trustedDeviceTable($conn);
if (empty($_SESSION['login_csrf'])) $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['login_csrf'], (string)($_POST['csrf'] ?? ''))) {
    http_response_code(400); exit('Invalid request. Reload the login page and try again.');
}

$error = '';
$cookie_username = '';
$remember_checked = false;

if (!empty($_COOKIE['remember_username'])) {
    $cookie_username = $_COOKIE['remember_username'];
    $remember_checked = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trust_confirm'])) {
    $pending = $_SESSION['trust_pending'] ?? null;
    if (!$pending || $pending['expires'] < time()) {
        unset($_SESSION['trust_pending']);
        $error = 'Login expired. Enter your username and password again.';
    } else {
        $id = (int)$pending['id'];
        $stmt = $conn->prepare('SELECT u.username, u.password, u.role, u.status, t.secret FROM users u JOIN user_totp t ON t.user_id = u.user_id WHERE u.user_id = ? AND t.enabled_at IS NOT NULL');
        $stmt->bind_param('i', $id); $stmt->execute(); $account = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$account || !in_array($account['status'], ['approved', 'active'], true) || !hash_equals($pending['fingerprint'], trustedDeviceFingerprint($account['password'], $account['secret']))) {
            unset($_SESSION['trust_pending']);
            $error = 'Your account changed. Enter your username and password again.';
        } elseif (isset($_POST['trust_device']) && !trustedDeviceCreate($conn, $id, $pending['fingerprint'])) {
            $error = 'Unable to trust this device. Try again, or uncheck the option to continue.';
        } else {
            completeDeviceLogin($conn, $id, $account);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_login'])) {
    $pending = $_SESSION['totp_pending'] ?? null;
    $code = trim((string)($_POST['totp_code'] ?? ''));
    if (!$pending || ($pending['expires'] ?? 0) < time()) {
        unset($_SESSION['totp_pending']);
        $error = 'Login expired. Enter your username and password again.';
    } else {
        $id = (int)$pending['id'];
        $stmt = $conn->prepare('SELECT u.username, u.password, u.role, u.status, t.secret, t.last_step, t.recovery_hashes FROM users u JOIN user_totp t ON t.user_id = u.user_id WHERE u.user_id = ? AND t.enabled_at IS NOT NULL');
        $stmt->bind_param('i', $id); $stmt->execute(); $account = $stmt->get_result()->fetch_assoc(); $stmt->close();
        $accepted = false;
        if ($account && in_array($account['status'], ['approved', 'active'], true)) {
            $step = totpVerify($account['secret'], $code, $account['last_step'] === null ? null : (int)$account['last_step']);
            if ($step !== null) {
                $save = $conn->prepare('UPDATE user_totp SET last_step = ? WHERE user_id = ? AND (last_step IS NULL OR last_step < ?)');
                $save->bind_param('iii', $step, $id, $step); $save->execute(); $accepted = $save->affected_rows === 1; $save->close();
            } else {
                $hashes = json_decode($account['recovery_hashes'] ?? '[]', true) ?: [];
                foreach ($hashes as $index => $hash) {
                    if (password_verify(strtoupper($code), $hash)) {
                        unset($hashes[$index]); $json = json_encode(array_values($hashes));
                        $save = $conn->prepare('UPDATE user_totp SET recovery_hashes = ? WHERE user_id = ? AND recovery_hashes = ?');
                        $old = $account['recovery_hashes']; $save->bind_param('sis', $json, $id, $old); $save->execute();
                        $accepted = $save->affected_rows === 1; $save->close(); break;
                    }
                }
            }
        }
        if ($accepted) {
            $_SESSION['trust_pending'] = ['id' => $id, 'expires' => time() + 300,
                'fingerprint' => trustedDeviceFingerprint($account['password'], $account['secret'])];
            unset($_SESSION['totp_pending']); session_regenerate_id(true);
            header('Location: index.php?trust=1'); exit;
        }
        $_SESSION['totp_pending']['attempts'] = ($pending['attempts'] ?? 0) + 1;
        if ($_SESSION['totp_pending']['attempts'] >= 5) unset($_SESSION['totp_pending']);
        $error = 'Invalid authenticator or recovery code.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT user_id, username, password, role, status FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password']) && in_array($user['status'], ['approved', 'active'], true)) {
                setcookie('remember_username', $remember ? $user['username'] : '', [
                    'expires' => $remember ? time() + 30 * 24 * 60 * 60 : time() - 3600,
                    'path' => '/',
                    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                $factor = $conn->prepare('SELECT enabled_at, secret FROM user_totp WHERE user_id = ?');
                $factor->bind_param('i', $user['user_id']); $factor->execute();
                $factorRow = $factor->get_result()->fetch_assoc(); $factor->close();
                if ($factorRow && $factorRow['enabled_at']) {
                    if (trustedDeviceValid($conn, (int)$user['user_id'], trustedDeviceFingerprint($user['password'], $factorRow['secret']))) {
                        completeDeviceLogin($conn, (int)$user['user_id'], $user);
                    }
                    session_regenerate_id(true);
                    $_SESSION['totp_pending'] = ['id' => (int)$user['user_id'], 'expires' => time() + 300, 'attempts' => 0];
                    header('Location: index.php?code=1'); exit;
                }
                session_regenerate_id(true);
                $_SESSION['totp_setup_pending'] = ['id' => (int)$user['user_id'], 'expires' => time() + 900, 'attempts' => 0];
                header('Location: totp_setup.php'); exit;
            } else {
                $error = 'Invalid credentials or account awaiting administrator approval.';
            }
        } else {
            $error = 'Invalid username or password.';
        }

        $stmt->close();
    } else {
        $error = 'Please fill in all fields.';
    }
}

$registered = isset($_GET['registered']) && $_GET['registered'] === '1';
$logged_out = isset($_GET['logged_out']) && $_GET['logged_out'] === '1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TESDA Inventory</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <style>
        body { 
            font-family: 'Century Gothic', Century Gothic, sans-serif;
            background: #f5f5f5;
            min-height: 100vh; 
            margin: 0; 
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container { 
            max-width: 380px; 
            width: 100%; 
            background: #fff; 
            padding: 40px 30px; 
            border-radius: 4px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo img {
            max-width: 150px;
            height: auto;
            margin-bottom: -5px;
        }
        .logo h1 {
            color: #0052a3;
            margin: 0;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .form-group input[type="checkbox"] {
            width: auto !important;
            margin: 0 6px 0 0;
        }
        .remember-group {
            display: flex;
            align-items: center;
            gap: 0;
        }
        .remember-group label {
            display: inline;
            margin: 0;
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 500;
            color: #333;
            font-size: 13px;
        }
        input[type="text"], 
        input[type="password"] { 
            width: 100%; 
            padding: 12px 14px; 
            border: 2px solid #ccc; 
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus, 
        input[type="password"]:focus { 
            outline: none;
            border-color: #0066cc;
        }
        input[type="checkbox"] {
            margin-right: 6px;
        }
        .error { 
            color: #dc3545; 
            margin-bottom: 16px; 
            text-align: center;
            padding: 10px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            font-size: 14px;
        }
        .success { 
            color: #155724; 
            margin-bottom: 16px; 
            text-align: center;
            padding: 10px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            font-size: 14px;
        }
        button { 
            width: 100%; 
            padding: 12px; 
            background: #0066cc;
            color: #fff; 
            border: none; 
            border-radius: 4px; 
            font-size: 16px; 
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }
        button:hover { 
            background: #0052a3;
        }
        button:active {
            background: #003d7a;
        }
        .signup-link { 
            display: block; 
            text-align: center; 
            margin-top: 20px; 
            color: #666; 
            text-decoration: none;
            font-size: 13px;
        }
        .signup-link a {
            color: #0066cc;
            text-decoration: none;
            font-weight: 600;
        }
        .signup-link a:hover { 
            text-decoration: underline; 
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="images/tesda_logo.png" alt="TESDA Logo">
            <h1>TESDA</h1>
        </div>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($registered): ?>
            <div class="success">Registration submitted. An administrator must approve your account before you can log in.</div>
        <?php endif; ?>

        <?php if ($logged_out): ?>
            <div class="success">You have been logged out successfully.</div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['trust_pending']) && $_SESSION['trust_pending']['expires'] >= time()): ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES) ?>">
            <input type="hidden" name="trust_confirm" value="1">
            <p>Code verified. Choose whether to trust this browser.</p>
            <div class="form-group remember-group">
                <input type="checkbox" name="trust_device" id="trust_device" value="1">
                <label for="trust_device">Trust this device for 30 days</label>
            </div>
            <p>You will still need your username and password. Only select this on a device you control.</p>
            <button type="submit">Continue to dashboard</button>
        </form>
        <?php elseif (!empty($_SESSION['totp_pending']) && ($_SESSION['totp_pending']['expires'] ?? 0) >= time()): ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES) ?>">
            <input type="hidden" name="totp_login" value="1">
            <div class="form-group">
                <label for="totp_code">Authenticator or recovery code</label>
                <input type="text" name="totp_code" id="totp_code" required autofocus autocomplete="one-time-code">
            </div>
            <button type="submit">Verify code</button>
        </form>
        <div class="signup-link"><a href="index.php">Start over</a></div>
        <?php else: ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES) ?>">
            <p class="required-fields-note"><span class="required-indicator" aria-hidden="true">*</span> indicates a required field.</p>
            <div class="form-group">
                <label for="username">Username <span class="required-indicator" aria-hidden="true">*</span></label>
                <input type="text" name="username" id="username" required autofocus
                       value="<?= htmlspecialchars($cookie_username) ?>">
            </div>
            <div class="form-group">
                <label for="password">Password <span class="required-indicator" aria-hidden="true">*</span></label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group remember-group">
                <input type="checkbox" name="remember" id="remember" <?= $remember_checked ? 'checked' : '' ?>>
                <label for="remember">Remember Me</label>
            </div>
            <button type="submit">Login</button>
        </form>
        <?php endif; ?>

        <div class="signup-link">
            Don't have an account? <a href="register.php">Request access</a>
        </div>
    </div>
</body>
</html>
