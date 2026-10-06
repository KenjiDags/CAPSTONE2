<?php
define('ACCOUNT_PAGE', true);
define('RECOVERY_PAGE', true);
require 'auth.php';
$codes = $_SESSION['totp_new_recovery'] ?? null;
if (!$codes) { header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'analytics.php')); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['totp_new_recovery']);
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'analytics.php')); exit;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Save recovery codes - TESDA Inventory</title>
<style>body{font-family:Arial,sans-serif;background:#f5f5f5;min-height:100vh;display:grid;place-items:center;margin:0;padding:20px}.card{background:white;max-width:460px;width:100%;padding:30px;border-radius:8px;box-shadow:0 2px 12px #0002}pre{padding:18px;background:#eef3f9;font-size:18px;line-height:1.6}button{padding:12px 18px;background:#0066cc;color:white;border:0;border-radius:5px;cursor:pointer}</style></head><body><main class="card">
<h1>Save your recovery codes</h1><p>Use one of these codes if you lose access to your phone. Each code works once. Save them somewhere private now; they will not be shown again.</p>
<pre><?= htmlspecialchars(implode("\n", $codes), ENT_QUOTES) ?></pre>
<form method="post"><button type="submit">I saved my codes — continue to dashboard</button></form>
</main></body></html>
