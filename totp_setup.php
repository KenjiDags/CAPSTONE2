<?php
session_start();
require 'config.php';
require_once 'totp.php';
totpTable($conn);
$pending = $_SESSION['totp_setup_pending'] ?? null;
if (!$pending || ($pending['expires'] ?? 0) < time()) {
    unset($_SESSION['totp_setup_pending']);
    header('Location: index.php'); exit;
}
$id = (int)$pending['id'];
$stmt = $conn->prepare('SELECT username, role, status FROM users WHERE user_id = ?');
$stmt->bind_param('i', $id); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$user || !in_array($user['status'], ['approved', 'active'], true)) {
    unset($_SESSION['totp_setup_pending']); header('Location: index.php'); exit;
}
$stmt = $conn->prepare('SELECT secret, enabled_at FROM user_totp WHERE user_id = ?');
$stmt->bind_param('i', $id); $stmt->execute(); $factor = $stmt->get_result()->fetch_assoc(); $stmt->close();
if ($factor && $factor['enabled_at']) {
    unset($_SESSION['totp_setup_pending']); header('Location: index.php'); exit;
}
if (!$factor) {
    $secret = totpSecret();
    $stmt = $conn->prepare('INSERT INTO user_totp (user_id, secret) VALUES (?, ?)');
    $stmt->bind_param('is', $id, $secret); $stmt->execute(); $stmt->close();
} else $secret = $factor['secret'];
if (empty($_SESSION['totp_setup_csrf'])) $_SESSION['totp_setup_csrf'] = bin2hex(random_bytes(32));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['totp_setup_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400); exit('Invalid request. Reload setup and try again.');
    }
    if (isset($_POST['regenerate_key'])) {
        $secret = totpSecret();
        $stmt = $conn->prepare('UPDATE user_totp SET secret = ? WHERE user_id = ? AND enabled_at IS NULL');
        $stmt->bind_param('si', $secret, $id); $stmt->execute(); $stmt->close();
        $_SESSION['totp_setup_pending']['attempts'] = 0;
        $error = 'A new key was generated. Replace the old account in your phone app with this key.';
    } else {
    $step = totpVerify($secret, trim((string)($_POST['code'] ?? '')));
    if ($step === null) {
        $_SESSION['totp_setup_pending']['attempts'] = ($pending['attempts'] ?? 0) + 1;
        if ($_SESSION['totp_setup_pending']['attempts'] >= 10) {
            unset($_SESSION['totp_setup_pending']); header('Location: index.php'); exit;
        }
        $error = 'That code did not match. Check the phone time and try again.';
    } else {
        $codes = [];
        for ($i = 0; $i < 8; $i++) $codes[] = strtoupper(bin2hex(random_bytes(5)));
        $hashes = json_encode(array_map('password_hash', $codes, array_fill(0, 8, PASSWORD_DEFAULT)));
        $stmt = $conn->prepare('UPDATE user_totp SET enabled_at = NOW(), last_step = ?, recovery_hashes = ? WHERE user_id = ? AND enabled_at IS NULL');
        $stmt->bind_param('isi', $step, $hashes, $id); $stmt->execute(); $saved = $stmt->affected_rows === 1; $stmt->close();
        if (!$saved) { header('Location: index.php'); exit; }
        if ($user['status'] === 'approved') {
            $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND status = 'approved'");
            $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
        }
        unset($_SESSION['totp_setup_pending'], $_SESSION['totp_setup_csrf']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id; $_SESSION['username'] = $user['username']; $_SESSION['role'] = $user['role'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? ''; $_SESSION['logged_in'] = true;
        $_SESSION['totp_new_recovery'] = $codes;
        header('Location: totp_recovery.php'); exit;
    }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set up authenticator - TESDA Inventory</title><link rel="stylesheet" href="css/styles.css">
<style>body{font-family:Arial,sans-serif;background:#f5f5f5;min-height:100vh;display:grid;place-items:center;margin:0;padding:20px}.card{background:white;max-width:470px;width:100%;padding:30px;border-radius:8px;box-shadow:0 2px 12px #0002}label{display:block;margin:20px 0 8px;font-weight:bold}input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #999;border-radius:5px;font-size:18px}button{margin-top:20px;padding:12px 18px;background:#0066cc;color:white;border:0;border-radius:5px;cursor:pointer}.setup-key{font-family:monospace;font-size:17px;letter-spacing:0;background:#eef3f9}.key-row{display:flex;gap:8px;align-items:center}.key-row button{margin:0;white-space:nowrap}.hint{font-size:14px;color:#444}.error{color:#a00}#setup-qr{width:200px;min-height:200px;margin:12px auto}#setup-qr img,#setup-qr canvas{display:block;margin:auto}</style></head><body><main class="card">
<h1>Set up your authenticator</h1>
<p>Before opening the dashboard, add this account to an authenticator app on your phone.</p>
<ol><li>Open your authenticator app and choose <strong>Add account</strong>, then scan the QR code below.</li><li>If scanning is unavailable, choose <strong>Enter setup key</strong> and use the key shown below with <strong>Time based</strong>.</li><li>Enter the six digit code shown on your phone.</li></ol>
<div id="setup-qr" role="img" aria-label="Authenticator setup QR code"></div>
<p class="hint" id="qr-status">If the QR code does not appear, enter the setup key manually.</p>
<label for="setup-key">Setup key (no spaces)</label>
<div class="key-row"><input class="setup-key" id="setup-key" type="text" readonly value="<?= htmlspecialchars($secret, ENT_QUOTES) ?>" onclick="this.select()" aria-describedby="key-hint"><button type="button" id="copy-key">Copy key</button></div>
<p class="hint" id="key-hint">Use the letters exactly as shown. The key can contain the letter <strong>O</strong> but never zero (0), and it contains only digits 2–7.</p>
<?php if (strlen($secret) > 24): ?><p class="hint">This is an older, longer key. Choose <strong>Generate a new key</strong> below for the shorter version, then add that new key to your phone app.</p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['totp_setup_csrf'], ENT_QUOTES) ?>"><button type="submit" name="regenerate_key" value="1">Generate a new key</button></form>
<?php if ($error): ?><p class="error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['totp_setup_csrf'], ENT_QUOTES) ?>"><label for="code">Six digit authenticator code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus><button type="submit">Verify and continue</button></form>
<p><a href="index.php">Start over</a></p></main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
document.getElementById('copy-key').addEventListener('click', async function () { const field = document.getElementById('setup-key'); try { await navigator.clipboard.writeText(field.value); this.textContent = 'Copied'; } catch (_) { field.select(); this.textContent = 'Select and copy the key'; } });
if (typeof QRCode !== 'undefined') {
    const label = 'TESDA Inventory:' + <?= json_encode($user['username'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const uri = 'otpauth://totp/' + encodeURIComponent(label) + '?secret=' + encodeURIComponent(document.getElementById('setup-key').value) + '&issuer=' + encodeURIComponent('TESDA Inventory') + '&algorithm=SHA1&digits=6&period=30';
    new QRCode(document.getElementById('setup-qr'), {text: uri, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M});
    document.getElementById('qr-status').textContent = 'Scan this QR code with your authenticator app. The setup key below is a backup option.';
}
</script></body></html>
