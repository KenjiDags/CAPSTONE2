<?php
session_start();
require 'config.php';

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($username && $password && $confirm) {
        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Check if username exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $error = 'Username already taken.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insert = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $insert->bind_param("ss", $username, $hash);
                if ($insert->execute()) {
                    header('Location: login.php?registered=1');
                    exit;
                } else {
                    $error = 'Registration failed. Please try again.';
                }
                $insert->close();
            }
            $stmt->close();
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TESDA Inventory</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; min-height: 100vh; margin: 0; }
    .center-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .register-container { max-width: 400px; width: 100%; background: #fff; padding: 32px 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    h2 { text-align: center; margin-bottom: 24px; }
    .form-group { margin-bottom: 18px; }
    label { display: block; margin-bottom: 6px; font-weight: bold; }
    input[type="text"], input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    .error { color: #d00; margin-bottom: 12px; text-align: center; }
    .success { color: #080; margin-bottom: 12px; text-align: center; }
    button { width: 100%; padding: 10px; background: #007cba; color: #fff; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
    button:hover { background: #005fa3; }
    .login-link { display: block; text-align: center; margin-top: 16px; color: #007cba; text-decoration: none; }
    .login-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="center-wrapper">
        <div class="register-container">
            <h2>Register Account</h2>
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>
                <button type="submit">Register</button>
            </form>
            <a class="login-link" href="login.php">Already have an account? Login</a>
        </div>
    </div>
</body>
</html>
