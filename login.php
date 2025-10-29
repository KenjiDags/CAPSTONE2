<?php
session_start();
require 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT user_id, username, password FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                header('Location: inventory.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
        $stmt->close();
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TESDA Inventory</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; min-height: 100vh; margin: 0; }
        .center-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container { max-width: 400px; width: 100%; background: #fff; padding: 32px 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        h2 { text-align: center; margin-bottom: 24px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .error { color: #d00; margin-bottom: 12px; text-align: center; }
        button { width: 100%; padding: 10px; background: #007cba; color: #fff; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        button:hover { background: #005fa3; }
    </style>
</head>
<body>
    <div class="center-wrapper">
        <div class="login-container">
        <div style="text-align:center;margin-bottom:24px;">
            <img src="images/tesda_logo.png" alt="TESDA CARes Logo" style="max-width:100px;width:100%;height:auto;">
            <div style="font-size:2.2rem;font-weight:bold;color:#003399;margin-top:10px;letter-spacing:2px;">TESDA</div>
        </div>
        <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
            <div class="success" style="color:#080;margin-bottom:12px;text-align:center;">Registration successful! You can now log in.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
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
            <button type="submit">Login</button>
        </form>
        <a class="register-link" href="register.php" style="display:block;text-align:center;margin-top:16px;color:#007cba;text-decoration:none;">Don't have an account? Register</a>
    </div>
</body>
</html>
