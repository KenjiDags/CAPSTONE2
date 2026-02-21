<?php
require 'auth.php';
require 'config.php';


$user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];

// Fetch current full name and user_position from database
$stmt = $conn->prepare("SELECT full_name, user_position FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_full_name = '';
$current_user_position = '';
if ($result && $result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    $current_full_name = $user_data['full_name'] ?? '';
    $current_user_position = $user_data['user_position'] ?? '';
}
$stmt->close();
// Handle user_position update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_position'])) {
    $new_user_position = trim($_POST['new_user_position'] ?? '');
    if (empty($new_user_position)) {
        $_SESSION['error_message'] = 'User position cannot be empty.';
    } else {
        // Update user_position
        $update = $conn->prepare("UPDATE users SET user_position = ? WHERE user_id = ?");
        $update->bind_param("si", $new_user_position, $user_id);
        if ($update->execute()) {
            $_SESSION['success_message'] = 'User position updated successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to update user position. Please try again.';
        }
        $update->close();
    }
    header('Location: user_settings.php');
    exit;
}

// Get messages from session
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Handle full name update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_full_name'])) {
    $new_full_name = trim($_POST['new_full_name'] ?? '');
    
    if (empty($new_full_name)) {
        $_SESSION['error_message'] = 'Full name cannot be empty.';
    } else {
        // Update full name
        $update = $conn->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
        $update->bind_param("si", $new_full_name, $user_id);
        
        if ($update->execute()) {
            $_SESSION['success_message'] = 'Full name updated successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to update full name. Please try again.';
        }
        $update->close();
    }
    header('Location: user_settings.php');
    exit;
}

// Handle username update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_username'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    
    if (empty($new_username)) {
        $_SESSION['error_message'] = 'Username cannot be empty.';
    } else {
        // Check if username is already taken
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ? LIMIT 1");
        $stmt->bind_param("si", $new_username, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['error_message'] = 'Username already taken. Please choose another.';
        } else {
            // Update username
            $update = $conn->prepare("UPDATE users SET username = ? WHERE user_id = ?");
            $update->bind_param("si", $new_username, $user_id);
            
            if ($update->execute()) {
                $_SESSION['username'] = $new_username;
                $_SESSION['success_message'] = 'Username updated successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to update username. Please try again.';
            }
            $update->close();
        }
        $stmt->close();
    }
    header('Location: user_settings.php');
    exit;
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error_message'] = 'All password fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error_message'] = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $_SESSION['error_message'] = 'Password must be at least 6 characters long.';
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (password_verify($current_password, $user['password'])) {
                // Update password
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $update->bind_param("si", $new_hash, $user_id);
                
                if ($update->execute()) {
                    $_SESSION['success_message'] = 'Password updated successfully!';
                } else {
                    $_SESSION['error_message'] = 'Failed to update password. Please try again.';
                }
                $update->close();
            } else {
                $_SESSION['error_message'] = 'Current password is incorrect.';
            }
        } else {
            $_SESSION['error_message'] = 'User not found.';
        }
        $stmt->close();
    }
    header('Location: user_settings.php');
    exit;
}

// Handle add officer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_officer'])) {
    $officer_name = trim($_POST['officer_name'] ?? '');
    $officer_position = trim($_POST['officer_position'] ?? '');
    
    if (empty($officer_name) || empty($officer_position)) {
        $_SESSION['error_message'] = 'Both officer name and position are required.';
    } else {
        $insert = $conn->prepare("INSERT INTO officers (officer_name, officer_position) VALUES (?, ?)");
        $insert->bind_param("ss", $officer_name, $officer_position);
        
        if ($insert->execute()) {
            $_SESSION['success_message'] = 'Officer added successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to add officer. Please try again.';
        }
        $insert->close();
    }
    header('Location: user_settings.php');
    exit;
}

// Handle delete officer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_officer'])) {
    $officer_id = (int)$_POST['officer_id'];
    
    $delete = $conn->prepare("DELETE FROM officers WHERE officer_id = ?");
    $delete->bind_param("i", $officer_id);
    
    if ($delete->execute()) {
        $_SESSION['success_message'] = 'Officer deleted successfully!';
    } else {
        $_SESSION['error_message'] = 'Failed to delete officer. Please try again.';
    }
    $delete->close();
    header('Location: user_settings.php');
    exit;
}

// Fetch all officers
$officers = [];
$officers_result = $conn->query("SELECT * FROM officers ORDER BY officer_name ASC");
if ($officers_result && $officers_result->num_rows > 0) {
    while ($row = $officers_result->fetch_assoc()) {
        $officers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings - TESDA Inventory</title>
    <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Page-specific styling */
        .settings-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .settings-header {
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #3b82f6;
        }
        
        .settings-header h1 {
            color: #1e293b;
            font-size: 32px;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .settings-header h1 i {
            color: #3b82f6;
        }
        
        .settings-header p {
            margin: 0;
            color: #64748b;
            font-size: 15px;
        }
        
        .settings-section {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .settings-section h2 {
            color: #1e293b;
            font-size: 20px;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .settings-section h2 i {
            color: #3b82f6;
            font-size: 18px;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .alert i {
            font-size: 16px;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .current-value {
            background: #f8fafc;
            padding: 12px 16px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .current-value strong {
            color: #475569;
            font-size: 14px;
        }
        
        .current-value span {
            color: #0f172a;
            font-size: 15px;
            font-weight: 600;
        }
        
        .form-row {
            margin-bottom: 20px;
        }
        
        .form-row label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }
        
        .form-row input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: #fff;
            margin-bottom: 10px;
        }
        
        .form-row input:hover {
            border-color: #cbd5e1;
        }
        
        .form-row input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .password-toggle-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none !important;
            border: none !important;
            color: #000000 !important;
            cursor: pointer;
            padding: 8px !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            text-transform: none !important;
            letter-spacing: normal !important;
            font-size: 14px !important;
            font-weight: normal !important;
            margin: 0 !important;
        }
        
        .password-toggle:hover {
            background: none !important;
            color: #000000 !important;
            box-shadow: none !important;
            transform: translateY(-50%) !important;
        }
        
        .password-toggle:active {
            background: none !important;
            transform: translateY(-50%) !important;
        }
        
        .btn-update {
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-update:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-update:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-back {
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%);
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            margin-bottom: 20px;
        }
        
        .btn-back:hover {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .help-text {
            font-size: 13px;
            color: #64748b;
            margin-top: 6px;
        }
        
        .officers-table tbody tr:hover {
            background-color: #f9fafb;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="content">
        <div class="settings-container">
            <a href="inventory.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                Back to Inventory
            </a>
            
            <header class="settings-header">
                <h1>
                    <i class="fas fa-user-cog"></i>
                    User Settings
                </h1>
                <p>Manage your account information and security settings</p>
            </header>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <!-- User Info -->
            <section class="settings-section">
                <h2>
                    <i class="fas fa-user-circle"></i>
                    User Information
                </h2>
                    <form method="POST" id="inlineNameForm" autocomplete="off">
                        <div class="form-row">
                            <label for="inline_full_name"><i class="fas fa-id-card"></i> Full Name:</label>
                            <input 
                                type="text" 
                                id="inline_full_name" 
                                name="new_full_name" 
                                value="<?= htmlspecialchars($current_full_name) ?>"
                                onfocus="this.select()"
                                onkeydown="if(event.key==='Enter'){this.form.submit();return false;}"
                                required 
                            >
                        </div>
                        <input type="hidden" name="update_full_name" value="1">
                    </form>

                    <form method="POST" id="inlineUserPositionForm" autocomplete="off">
                        <div class="form-row">
                            <label for="inline_user_position"><i class="fas fa-briefcase"></i> User Position:</label>
                            <input 
                                type="text" 
                                id="inline_user_position" 
                                name="new_user_position" 
                                value="<?= htmlspecialchars($current_user_position) ?>"
                                onfocus="this.select()"
                                onkeydown="if(event.key==='Enter'){this.form.submit();return false;}"
                                required 
                            >
                        </div>
                        <input type="hidden" name="update_user_position" value="1">
                    </form>

                    <form method="POST">
                        <div class="form-row">
                            <label for="inline_username"><i class="fas fa-user"></i> Username:</label>
                            <input 
                                type="text" 
                                id="inline_username" 
                                name="new_username" 
                                value="<?= htmlspecialchars($current_username) ?>"
                                onfocus="this.select()"
                                onkeydown="if(event.key==='Enter'){this.form.submit();return false;}"
                                required 
                            >
                        </div>
                </section>
            
            
            <!-- Password Section -->
            <section class="settings-section">
                <h2>
                    <i class="fas fa-lock"></i>
                    Change Password
                </h2>
                
                <form method="POST">
                    <div class="form-row">
                        <label for="current_password">Current Password</label>
                        <div class="password-toggle-wrapper">
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <label for="new_password">New Password</label>
                        <div class="password-toggle-wrapper">
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                required
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="help-text">Password must be at least 6 characters long</p>
                    </div>
                    
                    <div class="form-row">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-toggle-wrapper">
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                required
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_password" class="btn-update">
                        <i class="fas fa-key"></i>
                        Update Password
                    </button>
                </form>
            </section>
            
            <!-- Officers Management Section -->
            <section class="settings-section">
                <h2>
                    <i class="fas fa-user-tie"></i>
                    Manage Officers
                </h2>
                
                <!-- Add Officer Form -->
                <form method="POST" style="margin-bottom: 30px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-row" style="margin-bottom: 0;">
                            <label for="officer_name">Officer Name</label>
                            <input 
                                type="text" 
                                id="officer_name" 
                                name="officer_name" 
                                placeholder="Enter officer name"
                                required
                            >
                        </div>
                        <div class="form-row" style="margin-bottom: 0;">
                            <label for="officer_position">Officer Position</label>
                            <input 
                                type="text" 
                                id="officer_position" 
                                name="officer_position" 
                                placeholder="Enter position/title"
                                required
                            >
                        </div>
                    </div>
                    
                    <button type="submit" name="add_officer" class="btn-update">
                        <i class="fas fa-plus"></i>
                        Add Officer
                    </button>
                </form>
                
                <!-- Officers List -->
                <div style="border-top: 2px solid #e5e7eb; padding-top: 20px;">
                    <h3 style="color: #1e293b; font-size: 16px; margin: 0 0 15px 0; font-weight: 600;">
                        <i class="fas fa-list"></i> Officers List
                    </h3>
                    
                    <?php if (count($officers) > 0): ?>
                        <div class="officers-table" style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; background: white;">
                                <thead>
                                    <tr style="background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);">
                                        <th style="padding: 12px; text-align: left; color: white; font-weight: 600; font-size: 14px; border-bottom: 2px solid #e5e7eb;">
                                            <i class="fas fa-user"></i> Name
                                        </th>
                                        <th style="padding: 12px; text-align: left; color: white; font-weight: 600; font-size: 14px; border-bottom: 2px solid #e5e7eb;">
                                            <i class="fas fa-briefcase"></i> Position
                                        </th>
                                        <th style="padding: 12px; text-align: center; color: white; font-weight: 600; font-size: 14px; border-bottom: 2px solid #e5e7eb; width: 100px;">
                                            <i class="fas fa-cog"></i> Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($officers as $index => $officer): ?>
                                        <tr class="<?= $index >= 4 ? 'officer-row-hidden' : '' ?>" style="border-bottom: 1px solid #e5e7eb; transition: background-color 0.2s ease; <?= $index >= 4 ? 'display: none;' : '' ?>">
                                            <td style="padding: 12px; font-size: 14px;"><?= htmlspecialchars($officer['officer_name']) ?></td>
                                            <td style="padding: 12px; font-size: 14px;"><?= htmlspecialchars($officer['officer_position']) ?></td>
                                            <td style="padding: 12px; text-align: center;">
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this officer?');">
                                                    <input type="hidden" name="officer_id" value="<?= $officer['officer_id'] ?>">
                                                    <button 
                                                        type="submit" 
                                                        name="delete_officer" 
                                                        style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; transition: all 0.3s ease;"
                                                        onmouseover="this.style.background='linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)'"
                                                        onmouseout="this.style.background='linear-gradient(135deg, #ef4444 0%, #dc2626 100%)'"
                                                    >
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (count($officers) > 4): ?>
                                <button 
                                    id="toggleOfficersBtn"
                                    onclick="toggleOfficers()"
                                    style="width: 100%; padding: 10px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin-top: 10px; transition: all 0.3s ease;"
                                    onmouseover="this.style.background='linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)'"
                                    onmouseout="this.style.background='linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)'"
                                >
                                    <i class="fas fa-chevron-down"></i> Show More (<?= count($officers) - 4 ?> hidden)
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #64748b; font-style: italic;">
                            <i class="fas fa-info-circle" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                            No officers added yet. Add your first officer above.
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
    
    <script>
        // Toggle officers list visibility
        function toggleOfficers() {
            const hiddenRows = document.querySelectorAll('.officer-row-hidden');
            const btn = document.getElementById('toggleOfficersBtn');
            const icon = btn.querySelector('i');
            
            let isHidden = hiddenRows[0].style.display === 'none';
            
            hiddenRows.forEach(row => {
                row.style.display = isHidden ? 'table-row' : 'none';
            });
            
            if (isHidden) {
                icon.className = 'fas fa-chevron-up';
                btn.innerHTML = '<i class="fas fa-chevron-up"></i> Show Less';
            } else {
                icon.className = 'fas fa-chevron-down';
                btn.innerHTML = '<i class="fas fa-chevron-down"></i> Show More (' + hiddenRows.length + ' hidden)';
            }
        }
        
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Auto-hide success/error messages after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Save scroll position before form submission
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                sessionStorage.setItem('scrollPosition', window.scrollY);
            });
        });

        // Restore scroll position after page load
        window.addEventListener('load', function() {
            const scrollPos = sessionStorage.getItem('scrollPosition');
            if (scrollPos) {
                window.scrollTo(0, parseInt(scrollPos));
                sessionStorage.removeItem('scrollPosition');
            }
        });
    </script>
</body>
</html>
