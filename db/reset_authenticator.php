<?php
// For a lost authenticator when no other administrator can reset it:
// php db/reset_authenticator.php <existing-username>
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (empty($argv[1])) {
    fwrite(STDERR, "Usage: php db/reset_authenticator.php <existing-username>\n");
    exit(1);
}
require dirname(__DIR__) . '/config.php';

$username = $argv[1];
$stmt = $conn->prepare("UPDATE users SET totp_secret = NULL, status = 'approved' WHERE username = ? AND status IN ('active', 'approved')");
$stmt->bind_param('s', $username);
$stmt->execute();
$changed = $stmt->affected_rows;
$stmt->close();
if (!$changed) {
    fwrite(STDERR, "Active account not found or already awaiting setup.\n");
    exit(1);
}
echo "Authenticator reset. The account must enroll again at next login.\n";
