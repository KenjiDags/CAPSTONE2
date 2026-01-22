<?php
require 'auth.php';
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
                    header('Location: index.php?registered=1');
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
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
            background: #f5f5f5;
            min-height: 100vh; 
            margin: 0; 
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .register-container { 
            max-width: 380px; 
            width: 100%; 
            background: #fff; 
            padding: 40px 30px; 
            border-radius: 4px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }
        .logo {
            text-align: center;
            margin-bottom: 40px;
        }
        .logo img {
            max-width: 90px;
            height: auto;
            margin-bottom: 15px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .logo h1 {
            color: #0052a3;
            margin: 0;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .form-group { 
            margin-bottom: 20px; 
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
        .login-link { 
            display: block; 
            text-align: center; 
            margin-top: 20px; 
            color: #666; 
            text-decoration: none;
            font-size: 13px;
        }
        .login-link a {
            color: #0066cc;
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover { 
            text-decoration: underline; 
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <img src="images/tesda_logo.png" alt="TESDA Logo">
            <h1>TESDA</h1>
        </div>

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

        <div class="login-link">
            Already have an account? <a href="index.php">Login here</a>
        </div>
    </div>
</body>
</html>
