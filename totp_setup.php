<?php
session_start();
require_once 'config.php';
require_once 'totp.php';
require_once 'trusted_devices.php';
totpTable($conn);
$pending = $_SESSION['totp_setup_pending'] ?? null;
if (!$pending || ($pending['expires'] ?? 0) < time()) {
    unset($_SESSION['totp_setup_pending']);
    header('Location: index.php'); exit;
}
$id = (int)$pending['id'];
$stmt = $conn->prepare('SELECT username, role, status FROM users WHERE user_id = ?');
$stmt->bind_param('i', $id); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$user || $user['role'] !== 'admin' || !in_array($user['status'], ['approved', 'active'], true)) {
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
        rememberLoginUsername($user['username']);
        header('Location: totp_recovery.php'); exit;
    }
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set up two-factor authentication - TESDA Inventory</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;min-height:100dvh;display:grid;place-items:center;padding:24px;background:#080e19;color:#e2e8f0;font-family:Arial,sans-serif;font-size:14px;line-height:1.5}.setup-card{width:100%;max-width:770px;padding:16px 14px;background:#101a2b;border:1px solid #1e293b;border-radius:18px}.setup-header{display:flex;align-items:flex-start;gap:10px;padding-bottom:18px}.shield{display:grid;place-items:center;width:34px;height:34px;flex:0 0 34px;background:#233f65;color:#93c5fd;border-radius:12px}.shield svg,.continue-button svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}h1{font-size:18px;margin:0;font-weight:700}h2{font-size:16px;margin:0 0 5px}.setup-header p{margin:4px 0 0;color:#b5c4da;font-size:13px}.setup-step{display:flex;align-items:flex-start;gap:12px;padding:20px 0;border-top:1px solid #344258}.step-number{display:grid;place-items:center;flex:0 0 30px;height:30px;border-radius:50%;background:#264e81;color:#dbeafe;font-weight:700;font-size:16px}.step-content{flex:1;min-width:0}.step-content>p{margin:0 0 6px;color:#b5c4da}.qr-panel{display:flex;align-items:center;justify-content:center;min-height:224px;padding:12px;background:white;border-radius:12px;margin:6px 0}#setup-qr{width:200px;height:200px}#setup-qr img,#setup-qr canvas{display:block;max-width:100%}.hint{font-size:12px;color:#b5c4da;margin:6px 0}.key-row{position:relative;display:flex;align-items:center}.setup-key,#code{width:100%;border:1px solid #435570;border-radius:9px;background:#101a2b;color:#e2e8f0;font:inherit;min-height:46px;padding:12px}.setup-key{font-family:monospace;padding-right:48px}button{font:inherit;cursor:pointer}.copy-button{position:absolute;right:7px;display:grid;place-items:center;width:34px;height:34px;border:0;background:transparent;color:#93c5fd;border-radius:6px}.copy-button svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}.copy-button:hover{background:#233f65}#code{height:52px;text-align:center;font-size:18px;letter-spacing:.3em;font-weight:700;margin-top:6px}#code::placeholder{color:#a9bad2;opacity:1}.continue-button{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;min-height:50px;padding:12px;border:0;border-radius:9px;background:#2563eb;color:#fff;font-weight:700;font-size:16px}.continue-button:hover{background:#1d4ed8}button:disabled{opacity:.55;cursor:not-allowed}a{color:#93c5fd;text-decoration:none}a:hover{text-decoration:underline}.back-link{display:block;text-align:center;margin-top:8px;font-size:12px}.error{padding:10px 12px;color:#fecaca;background:#4c2533;border-radius:8px}.key-options{margin-top:8px;color:#b5c4da;font-size:12px}.regenerate-button{padding:6px 0;border:0;background:transparent;color:#93c5fd;font-size:12px}:is(button,input,a,summary):focus-visible{outline:2px solid #60a5fa;outline-offset:3px}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}@media(max-width:480px){body{padding:12px}.setup-card{padding:14px 12px}.setup-step{gap:9px}.step-number{flex-basis:26px;height:26px}.setup-key{font-size:12px}h1{font-size:17px}}
.qr-disclosure{margin:10px 0}.qr-disclosure summary{cursor:pointer;color:#93c5fd;padding:8px 0;font-weight:600}.qr-hide-label{display:none}.qr-disclosure[open] .qr-show-label{display:none}.qr-disclosure[open] .qr-hide-label{display:inline}.qr-toggle{display:flex;align-items:center;justify-content:center;width:100%;min-height:80px;padding:14px;margin:6px 0;border:0;border-radius:12px;background:#fff;color:#172033;cursor:pointer}.qr-placeholder{display:flex;flex-direction:column;align-items:center;gap:10px}.qr-placeholder svg{width:30px;height:30px}.qr-placeholder>span{font-size:12px;color:#52637d}.qr-toggle[aria-expanded="true"]{min-height:228px}.qr-toggle[aria-expanded="true"] .qr-placeholder{display:none}#setup-qr[hidden]{display:none}.qr-toggle:hover{box-shadow:inset 0 0 0 2px #93c5fd}</style></head><body><main class="setup-card">
<header class="setup-header"><span class="shield" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m9 12 2 2 4-4"/></svg></span><div><h1>Set up two-factor authentication</h1><p>Secure your administrator account with an authenticator app.</p></div></header>
<section class="setup-step" aria-labelledby="connect-heading"><span class="step-number" aria-hidden="true">1</span><div class="step-content"><h2 id="connect-heading">Connect your authenticator app</h2><p>Open Google Authenticator, Microsoft Authenticator, or another compatible app and scan the QR code.</p>
<button type="button" class="qr-toggle" id="qr-toggle" aria-expanded="false" aria-controls="setup-qr" aria-label="Show authenticator QR code">
  <span class="qr-placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="15" y="3" width="6" height="6" rx="1"/><rect x="3" y="15" width="6" height="6" rx="1"/><path d="M15 15h3v3h3m-6 3h3M21 12v1M12 3v3m0 6h3M3 12h3m6 3v6"/></svg><span>Show QR code</span></span>
  <span id="setup-qr" role="img" aria-label="Authenticator setup QR code" hidden></span>
</button>
<p class="hint" id="qr-status" role="status">Can't scan the QR code? Use the setup key instead.</p>
<label class="sr-only" for="setup-key">Setup key</label><div class="key-row"><input class="setup-key" id="setup-key" type="text" readonly value="<?= htmlspecialchars($secret, ENT_QUOTES) ?>" onclick="this.select()" aria-describedby="key-hint"><button type="button" class="copy-button" id="copy-key" aria-label="Copy setup key" title="Copy setup key"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="3" width="12" height="13" rx="2"/><path d="M16 16v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h3"/></svg></button></div><span class="sr-only" id="copy-status" role="status"></span><p class="sr-only" id="key-hint">When entering the key manually, choose Time based in your authenticator app.</p>
<details class="key-options"><summary>Need a new setup key?</summary><p>Generating a new key replaces this one. Add the replacement key to your authenticator app.</p><form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars(($_SESSION['totp_setup_csrf'] ?? ''), ENT_QUOTES) ?>"><button class="regenerate-button" type="submit" name="regenerate_key" value="1">Generate a new key</button></form></details>
</div></section>
<form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars(($_SESSION['totp_setup_csrf'] ?? ''), ENT_QUOTES) ?>">
<section class="setup-step" aria-labelledby="verify-heading"><span class="step-number" aria-hidden="true">2</span><div class="step-content"><h2 id="verify-heading">Verify your authenticator</h2><p>Enter the 6-digit code generated by your app.</p><label class="sr-only" for="code">Six digit authenticator code</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
<?php if ($error): ?><p class="error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
</div></section>
<section class="setup-step" aria-labelledby="complete-heading"><span class="step-number" aria-hidden="true">3</span><div class="step-content"><h2 id="complete-heading">Complete setup</h2><button class="continue-button" type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m9 12 2 2 4-4"/></svg>Verify &amp; Continue</button><a class="back-link" href="index.php">Back to sign in</a></div></section>
</form></main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
const qrToggle = document.getElementById('qr-toggle');
qrToggle.addEventListener('click', () => {
    const expanded = qrToggle.getAttribute('aria-expanded') !== 'true';
    qrToggle.setAttribute('aria-expanded', String(expanded));
    qrToggle.setAttribute('aria-label', expanded ? 'Hide authenticator QR code' : 'Show authenticator QR code');
    document.getElementById('setup-qr').hidden = !expanded;
});
document.getElementById('copy-key').addEventListener('click', async function () { const field = document.getElementById('setup-key'); try { await navigator.clipboard.writeText(field.value); this.setAttribute('aria-label', 'Setup key copied'); document.getElementById('copy-status').textContent = 'Setup key copied.'; } catch (_) { field.select(); document.getElementById('copy-status').textContent = 'Select and copy the key manually.'; } });
if (typeof QRCode !== 'undefined') {
    const label = 'TESDA Inventory:' + <?= json_encode($user['username'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const uri = 'otpauth://totp/' + encodeURIComponent(label) + '?secret=' + encodeURIComponent(document.getElementById('setup-key').value) + '&issuer=' + encodeURIComponent('TESDA Inventory') + '&algorithm=SHA1&digits=6&period=30';
    new QRCode(document.getElementById('setup-qr'), {text: uri, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M});
    document.getElementById('qr-status').textContent = 'Can\'t scan the QR code? Use the setup key instead.';
}
</script></body></html>
