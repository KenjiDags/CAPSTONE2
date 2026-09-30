<?php
// Fresh installs: php db/promote_admin.php <existing-username>
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (empty($argv[1])) {
    fwrite(STDERR, "Usage: php db/promote_admin.php <existing-username>\n");
    exit(1);
}
require dirname(__DIR__) . '/config.php';

$username = $argv[1];
$check = $conn->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
$check->bind_param('s', $username);
$check->execute();
$user = $check->get_result()->fetch_assoc();
$check->close();
if (!$user) {
    fwrite(STDERR, "Account not found. Register it first.\n");
    exit(1);
}

$stmt = $conn->prepare("UPDATE users SET role = 'admin', status = 'approved' WHERE user_id = ?");
$stmt->bind_param('i', $user['user_id']);
$stmt->execute();
$stmt->close();
echo "Administrator access granted. Set up the authenticator at next login.\n";
