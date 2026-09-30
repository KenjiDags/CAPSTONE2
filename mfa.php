<?php
session_start();
require 'config.php';

if (empty($_SESSION['mfa_pending_user_id']) || ($_SESSION['user_agent'] ?? '') !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare('SELECT user_id, username, role, status, totp_secret FROM users WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $_SESSION['mfa_pending_user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user || !in_array($user['status'], ['approved', 'active'], true)) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

function totp_base32_decode(string $text): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = 0;
    $value = 0;
    $result = '';
    foreach (str_split(strtoupper($text)) as $character) {
        $index = strpos($alphabet, $character);
        if ($index === false) return '';
        $value = ($value << 5) | $index;
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $result .= chr(($value >> $bits) & 255);
        }
    }
    return $result;
}

function totp_code(string $secret, int $counter): string
{
    $key = totp_base32_decode($secret);
    $message = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
    $hash = hash_hmac('sha1', $message, $key, true);
    $offset = ord($hash[19]) & 15;
    $number = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
    return str_pad((string)($number % 1000000), 6, '0', STR_PAD_LEFT);
}

if (empty($user['totp_secret']) && empty($_SESSION['mfa_enrollment_secret'])) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bytes = random_bytes(20);
    $bits = '';
    foreach (str_split($bytes) as $byte) $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    $secret = '';
    foreach (str_split($bits, 5) as $group) $secret .= $alphabet[bindec($group)];
    $_SESSION['mfa_enrollment_secret'] = $secret;
}
$enrolling = empty($user['totp_secret']);
$secret = $enrolling ? $_SESSION['mfa_enrollment_secret'] : $user['totp_secret'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = time();
    if ($now - ($_SESSION['mfa_attempt_window'] ?? 0) >= 300) {
        $_SESSION['mfa_attempt_window'] = $now;
        $_SESSION['mfa_attempts'] = 0;
    }
    if (($_SESSION['mfa_attempts'] ?? 0) >= 5) {
        $error = 'Too many attempts. Please wait five minutes.';
    } else {
        $_SESSION['mfa_attempts']++;
        $submitted = trim($_POST['code'] ?? '');
        $valid = false;
        if (preg_match('/^\d{6}$/', $submitted)) {
            $current = intdiv($now, 30);
            for ($step = -1; $step <= 1; $step++) {
                if (hash_equals(totp_code($secret, $current + $step), $submitted)) {
                    $valid = true;
                    break;
                }
            }
        }
        if ($valid) {
            if ($enrolling) {
                $update = $conn->prepare("UPDATE users SET totp_secret = ?, status = 'active' WHERE user_id = ? AND status IN ('approved', 'active')");
                $update->bind_param('si', $secret, $user['user_id']);
                $update->execute();
                $update->close();
            }
            session_regenerate_id(true);
            unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_enrollment_secret'], $_SESSION['mfa_attempts'], $_SESSION['mfa_attempt_window']);
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'analytics.php'));
            exit;
        }
        $error = 'Invalid code. Check the time on your phone and try again.';
    }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Authenticator verification - TESDA Inventory</title>
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
  <style>
    body { min-height: 100vh; margin: 0; display: grid; place-items: center; background: #f4f7fb; font-family: Arial, sans-serif; }
    main { width: min(420px, calc(100% - 32px)); padding: 28px; background: white; border-radius: 12px; box-shadow: 0 8px 24px #1e293b22; }
    h1 { margin-top: 0; color: #123d80; } label { display: block; margin: 20px 0 6px; font-weight: 600; }
    input { box-sizing: border-box; width: 100%; padding: 12px; font-size: 18px; letter-spacing: 3px; }
    button { width: 100%; margin-top: 16px; padding: 12px; color: white; background: #1457ae; border: 0; border-radius: 6px; cursor: pointer; }
    code { display: block; padding: 12px; background: #edf3fa; overflow-wrap: anywhere; }
    .error { color: #b91c1c; } .hint { color: #475569; font-size: 14px; }
  </style>
</head>
<body>
<main>
  <h1><?= $enrolling ? 'Set up your authenticator' : 'Enter authenticator code' ?></h1>
  <?php if ($enrolling): ?>
    <p>Add a new account in your authenticator app using this setup key:</p>
    <code><?= htmlspecialchars($secret) ?></code>
    <p class="hint">Account: TESDA Inventory / <?= htmlspecialchars($user['username']) ?>. Save the account in your app before continuing.</p>
  <?php endif; ?>
  <?php if ($error): ?><p class="error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post">
    <label for="code">Six-digit code</label>
    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
    <button type="submit"><?= $enrolling ? 'Verify and activate account' : 'Verify and continue' ?></button>
  </form>
  <p><a href="logout.php">Cancel and return to login</a></p>
</main>
</body>
</html>
