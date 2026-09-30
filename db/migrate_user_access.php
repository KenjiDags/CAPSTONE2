<?php
// Run once from the command line: php db/migrate_user_access.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config.php';

function access_column_exists(mysqli $conn, string $column): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'users\' AND COLUMN_NAME = ? LIMIT 1');
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

$hadRole = access_column_exists($conn, 'role');
foreach ([
    'role' => "VARCHAR(16) NOT NULL DEFAULT 'user'",
    'status' => "VARCHAR(16) NOT NULL DEFAULT 'active'",
    'email' => 'VARCHAR(255) NULL',
] as $column => $definition) {
    if (!access_column_exists($conn, $column)) {
        if (!$conn->query("ALTER TABLE users ADD COLUMN $column $definition")) {
            throw new RuntimeException($conn->error);
        }
    }
}
if (!$conn->query("SHOW INDEX FROM users WHERE Key_name = 'unique_user_email'")->num_rows) {
    $conn->query('ALTER TABLE users ADD UNIQUE KEY unique_user_email (email)');
}

// Preserve access for existing users. With a single existing account, that
// account becomes the initial administrator; all future registrations wait.
if (!$hadRole) {
    $row = $conn->query('SELECT COUNT(*) AS total, MIN(user_id) AS first_id FROM users')->fetch_assoc();
    if ((int)$row['total'] === 1) {
        $stmt = $conn->prepare("UPDATE users SET role = 'admin', status = 'active' WHERE user_id = ?");
        $stmt->bind_param('i', $row['first_id']);
        $stmt->execute();
        $stmt->close();
        echo "The existing account is now the initial administrator.\n";
    } else {
        echo "Assign one trusted existing account the admin role before enabling approval.\n";
    }
}
echo "User access migration complete.\n";
