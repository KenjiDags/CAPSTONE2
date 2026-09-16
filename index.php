<?php
session_start();

// The application entry point always starts at the login form.
if (!empty($_SESSION)) {
    session_unset();
    session_destroy();
    session_start();
}

require 'config.php';

$error = '';
$cookie_username = '';
$remember_checked = false;

if (!empty($_COOKIE['remember_username'])) {
    $cookie_username = $_COOKIE['remember_username'];
    $remember_checked = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT user_id, password FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $username;
                $_SESSION['logged_in'] = true;
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

                // Login is session-only; do not persist authentication across visits.
                $stmtToken = $conn->prepare("UPDATE users SET remember_token = NULL WHERE user_id = ?");
                $stmtToken->bind_param("i", $user['user_id']);
                $stmtToken->execute();
                $stmtToken->close();
                setcookie('remember_token', '', time() - 3600, "/", "localhost", false, true);

                if ($remember) {
                    setcookie('remember_username', $username, time() + 30 * 24 * 60 * 60, '/', 'localhost', false, true);
                } else {
                    setcookie('remember_username', '', time() - 3600, '/', 'localhost', false, true);
                }

                header('Location:inventory.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
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
            <div class="success">Account created successfully! You can now login.</div>
        <?php endif; ?>

        <?php if ($logged_out): ?>
            <div class="success">You have been logged out successfully.</div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" required autofocus
                       value="<?= htmlspecialchars($cookie_username) ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group remember-group">
                <input type="checkbox" name="remember" id="remember" <?= $remember_checked ? 'checked' : '' ?>>
                <label for="remember">Remember Me</label>
            </div>
            <button type="submit">Login</button>
        </form>

        <div class="signup-link">
            Don't have an account? <a href="register.php">Register</a>
        </div>
    </div>
</body>
</html>
