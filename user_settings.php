<?php
define('ACCOUNT_PAGE', true);
require 'auth.php';
require 'config.php';
if ($_SESSION['role'] !== 'admin' && isset($_POST['totp_action'])) {
    http_response_code(403); exit('Authenticator settings are only available to administrators.');
}
$totpNotice = '';
$totpRecovery = [];
$totpAccount = null;
if ($_SESSION['role'] === 'admin') {
require_once 'totp.php';
totpTable($conn);
if (empty($_SESSION['totp_csrf'])) $_SESSION['totp_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_action'])) {
    if (!hash_equals($_SESSION['totp_csrf'], (string)($_POST['totp_csrf'] ?? ''))) {
        http_response_code(400); exit('Invalid request. Reload settings and try again.');
    }
    $action = (string)$_POST['totp_action'];
    $pass = (string)($_POST['totp_password'] ?? '');
    $check = $conn->prepare('SELECT password FROM users WHERE user_id = ?');
    $check->bind_param('i', $_SESSION['user_id']); $check->execute();
    $passwordRow = $check->get_result()->fetch_assoc(); $check->close();
    if (!$passwordRow || !password_verify($pass, $passwordRow['password'])) {
        $totpNotice = 'Current password is incorrect.';
    } elseif ($action === 'start') {
        $existing = $conn->prepare('SELECT enabled_at FROM user_totp WHERE user_id = ?');
        $existing->bind_param('i', $_SESSION['user_id']); $existing->execute();
        $row = $existing->get_result()->fetch_assoc(); $existing->close();
        if ($row && $row['enabled_at']) $totpNotice = 'Authenticator is already enabled.';
        else {
            $secret = totpSecret();
            $save = $conn->prepare('INSERT INTO user_totp (user_id, secret) VALUES (?, ?) ON DUPLICATE KEY UPDATE secret = VALUES(secret), enabled_at = NULL, last_step = NULL, recovery_hashes = NULL');
            $save->bind_param('is', $_SESSION['user_id'], $secret); $save->execute(); $save->close();
            $totpNotice = 'Add the setup key to your authenticator app, then verify a code below.';
        }
    } elseif ($action === 'enable') {
        $get = $conn->prepare('SELECT secret, enabled_at FROM user_totp WHERE user_id = ?');
        $get->bind_param('i', $_SESSION['user_id']); $get->execute(); $row = $get->get_result()->fetch_assoc(); $get->close();
        $step = $row && !$row['enabled_at'] ? totpVerify($row['secret'], trim((string)($_POST['totp_code'] ?? ''))) : null;
        if ($step === null) $totpNotice = 'Invalid code. Check your phone time and try again.';
        else {
            for ($i = 0; $i < 8; $i++) $totpRecovery[] = strtoupper(bin2hex(random_bytes(5)));
            $hashes = json_encode(array_map('password_hash', $totpRecovery, array_fill(0, 8, PASSWORD_DEFAULT)));
            $save = $conn->prepare('UPDATE user_totp SET enabled_at = NOW(), last_step = ?, recovery_hashes = ? WHERE user_id = ?');
            $save->bind_param('isi', $step, $hashes, $_SESSION['user_id']); $save->execute(); $save->close();
            $totpNotice = 'Authenticator enabled. Save these recovery codes now; they will not be shown again.';
        }
    } elseif ($action === 'disable') {
        $get = $conn->prepare('SELECT secret, last_step, recovery_hashes FROM user_totp WHERE user_id = ? AND enabled_at IS NOT NULL');
        $get->bind_param('i', $_SESSION['user_id']); $get->execute(); $row = $get->get_result()->fetch_assoc(); $get->close();
        $entered = trim((string)($_POST['totp_code'] ?? ''));
        $valid = $row && totpVerify($row['secret'], $entered, $row['last_step'] === null ? null : (int)$row['last_step']) !== null;
        if ($row && !$valid) foreach (json_decode($row['recovery_hashes'] ?? '[]', true) ?: [] as $hash) {
            if (password_verify(strtoupper($entered), $hash)) { $valid = true; break; }
        }
        if (!$valid) $totpNotice = 'A current authenticator or recovery code is to disable it.';
        else {
            $del = $conn->prepare('DELETE FROM user_totp WHERE user_id = ?'); $del->bind_param('i', $_SESSION['user_id']); $del->execute(); $del->close();
            $totpNotice = 'Authenticator disabled.';
        }
    }
}
$totpStmt = $conn->prepare('SELECT secret, enabled_at FROM user_totp WHERE user_id = ?');
$totpStmt->bind_param('i', $_SESSION['user_id']); $totpStmt->execute();
$totpAccount = $totpStmt->get_result()->fetch_assoc(); $totpStmt->close();
}


$user_id = $_SESSION['user_id'];
$current_username = $_SESSION['username'];

// Fetch current user information
$stmt = $conn->prepare("
    SELECT 
        u.user_full_name,
        u.username,
        u.user_position
    FROM users u
    WHERE u.user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$current_full_name = '';
$current_user_position = '';
$current_username = $_SESSION['username'] ?? '';

if ($result && $result->num_rows > 0) {
    $user_data = $result->fetch_assoc();

    $current_full_name = $user_data['user_full_name'] ?? '';
    $current_username = $user_data['username'] ?? $current_username;
    $current_user_position = $user_data['user_position'] ?? '';
}

$stmt->close();
// Handle user_position update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_position'])) {
    $new_user_position = trim($_POST['new_user_position'] ?? '');
    if (empty($new_user_position)) {
        $_SESSION['error_message'] = 'User position cannot be empty.';
    } elseif (strlen($new_user_position) > 255) {
        $_SESSION['error_message'] = 'User position must be 255 characters or fewer.';
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
        $update = $conn->prepare("UPDATE users SET user_full_name = ? WHERE user_id = ?");
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
// Handle edit officer (inline update for name and position)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_officer_fields'])) {
    $officer_id = (int)$_POST['officer_id'];
    $new_name = trim($_POST['new_officer_name'] ?? '');
    $new_position = trim($_POST['new_officer_position'] ?? '');
    if (empty($new_name) || empty($new_position)) {
        $_SESSION['error_message'] = 'Officer name and position cannot be empty.';
    } else {
        $update = $conn->prepare("UPDATE officers SET officer_name = ?, officer_position = ? WHERE officer_id = ?");
        $update->bind_param("ssi", $new_name, $new_position, $officer_id);
        if ($update->execute()) {
            $_SESSION['success_message'] = 'Officer updated successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to update officer.';
        }
        $update->close();
    }
    header('Location: user_settings.php');
    exit;
}
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
// Handle edit officer (inline update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_officer_name'])) {
    $officer_id = (int)$_POST['officer_id'];
    $new_name = trim($_POST['new_officer_name'] ?? '');
    if (empty($new_name)) {
        $_SESSION['error_message'] = 'Officer name cannot be empty.';
    } else {
        $update = $conn->prepare("UPDATE officers SET officer_name = ? WHERE officer_id = ?");
        $update->bind_param("si", $new_name, $officer_id);
        if ($update->execute()) {
            $_SESSION['success_message'] = 'Officer name updated successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to update officer name.';
        }
        $update->close();
    }
    header('Location: user_settings.php');
    exit;
}
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

// Outline icons shared by the account settings controls.
$settingsIconPaths = [
    'user-cog' => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a7 7 0 0 1 12-5M18 13v2m0 6v2m-5-5h2m6 0h2m-8.5-3.5 1.4 1.4m4.2 4.2 1.4 1.4m-7 0 1.4-1.4m4.2-4.2 1.4-1.4"/><circle cx="18" cy="18" r="3"/>',
    'user-circle' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="9" r="3"/><path d="M5 19a7 7 0 0 1 14 0"/>',
    'user' => '<circle cx="12" cy="7" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/>',
    'user-friends' => '<circle cx="8" cy="7" r="3"/><path d="M2 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6m1 5a5 5 0 0 1 5 5v1"/>',
    'id-card' => '<rect x="2" y="4" width="20" height="16" rx="2"/><circle cx="8" cy="10" r="2"/><path d="M5 16a3 3 0 0 1 6 0m3-7h5m-5 4h5"/>',
    'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M8 7V3h8v4M2 12a24 24 0 0 0 20 0m-10 0v4"/>',
    'shield-alt' => '<path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6Z"/><path d="m8 12 3 3 5-6"/>',
    'lock' => '<rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v3"/>',
    'key' => '<circle cx="8" cy="8" r="5"/><path d="m12 12 9 9m-3-3 3-3m-6 0 3-3"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'pen' => '<path d="m16 3 5 5M3 21l5-1L21 7a2 2 0 0 0-5-5L3 15Z"/>',
    'trash-alt' => '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>',
    'check' => '<path d="m5 12 4 4L19 6"/>',
    'times' => '<path d="m6 6 12 12M6 18 18 6"/>',
    'check-circle' => '<circle cx="12" cy="12" r="10"/><path d="m7 12 3 3 7-7"/>',
    'exclamation-circle' => '<circle cx="12" cy="12" r="10"/><path d="M12 7v6m0 4h.01"/>',
    'moon' => '<path d="M21 13A9 9 0 0 1 11 3a9 9 0 1 0 10 10Z"/>',
    'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/>',
    'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    'eye-slash' => '<path d="m3 3 18 18M10.5 5.1 12 5c6 0 10 7 10 7a19 19 0 0 1-3 3.8M6.5 6.5A20 20 0 0 0 2 12s4 7 10 7a12 12 0 0 0 5.5-1.5M10 10a3 3 0 0 0 4 4"/>',
];
$settingsOutlineIcon = static function ($name) {
    return '<i class="settings-outline-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><use href="#settings-icon-' . $name . '"></use></svg></i>';
};
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
            margin: 12px auto 40px;
            padding: 0 20px;
        }
        
        .settings-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 8px 16px;
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
            grid-column: 1 / -1;
            grid-row: 2;
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
        
        .settings-container .settings-section h2 {
            color: #1e293b;
            font-size: 25px;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .settings-container .settings-subsection-title {
            color: #1e293b;
            font-size: 25px;
            margin: 0 0 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .settings-subsection-title i { font-size: 25px; color: #3b82f6; }
        body.dark-mode .settings-section .settings-subsection-title,
        body.dark-mode .settings-section .help-text { color: #cbd5e1; }
        .settings-container .settings-section h2 i {
            color: #3b82f6;
            font-size: 25px;
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
        
        .help-text {
            font-size: 13px;
            color: #64748b;
            margin-top: 6px;
        }
        
        .officers-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .settings-header .theme-toggle {
            grid-column: 2;
            grid-row: 1;
            justify-self: end;
            margin-top: 0;
            width: auto;
            background: #1e293b;
        }

        .theme-toggle:hover {
            background: #0f172a;
        }

        .dark-mode .settings-header h1,
        .dark-mode .settings-section h2,
        .dark-mode .settings-section h3,
        .dark-mode .form-row label {
            color: #e2e8f0;
        }

        .dark-mode .settings-header p,
        .dark-mode .help-text {
            color: #94a3b8;
        }

        .dark-mode .settings-section {
            background: #1e293b;
            border-color: #334155;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
        }

        .dark-mode .form-row input,
        .dark-mode .current-value {
            background-color: #0f172a;
            border-color: #475569;
            color: #e2e8f0;
        }

        .dark-mode .current-value strong,
        .dark-mode .current-value span {
            color: #e2e8f0;
        }

        .dark-mode .officers-table tbody tr {
            background-color: #1e293b !important;
            border-color: #334155 !important;
            color: #e2e8f0;
        }

        .dark-mode .officers-table tbody tr:hover {
            background-color: #334155 !important;
        }

        .dark-mode .officers-table table {
            background: #1e293b !important;
        }

        .dark-mode .officers-table input {
            background-color: #0f172a !important;
            border-color: #475569 !important;
            color: #e2e8f0;
        }

        .dark-mode .officers-table > div {
            border-color: #334155 !important;
        }
    </style>
</head>
<body>
<svg class="settings-icon-definitions" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" width="0" height="0">
    <defs><?php foreach ($settingsIconPaths as $name => $paths): ?><symbol id="settings-icon-<?= $name ?>" viewBox="0 0 24 24"><?= $paths ?></symbol><?php endforeach; ?></defs>
</svg>
    <?php include 'sidebar.php'; ?>
    
    <div class="content">
        <div class="settings-container">
            <header class="settings-header">
                <h1>
                    <?= $settingsOutlineIcon('user-cog') ?>
                    User Settings
                </h1>
                <p>Manage your account information and security settings</p>
                <button type="button" id="themeToggle" class="btn-update theme-toggle" aria-pressed="false">
                    <?= $settingsOutlineIcon('moon') ?>
                    <span>Dark Mode</span>
                </button>
            </header>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?= $settingsOutlineIcon('check-circle') ?>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?= $settingsOutlineIcon('exclamation-circle') ?>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <!-- User Info -->
            <section class="settings-section settings-card">
                <h2>
                    <?= $settingsOutlineIcon('user-circle') ?>
                    User Information
                </h2>
                    <form method="POST" id="inlineNameForm" autocomplete="off">
                        <div class="form-row">
                            <label for="inline_full_name"><?= $settingsOutlineIcon('id-card') ?> Full Name:</label>
                            <input 
                                type="text" 
                                id="inline_full_name" 
                                name="new_full_name" 
                                value="<?= htmlspecialchars($current_full_name) ?>"
                                onfocus="this.select()"
                                onkeydown="if(event.key==='Enter'){this.form.submit();return false;}"
                            >
                        </div>
                        <input type="hidden" name="update_full_name" value="1">
                    </form>

                    <div class="settings-inline-fields">
                    <form method="POST" id="inlineUserPositionForm" autocomplete="off">
                        <div class="form-row">
                            <label for="inline_user_position"><?= $settingsOutlineIcon('briefcase') ?> User Position:</label>
                            <input 
                                type="text" 
                                id="inline_user_position" 
                                name="new_user_position" 
                                value="<?= htmlspecialchars($current_user_position, ENT_QUOTES, 'UTF-8') ?>"
                                maxlength="255" required
                                onfocus="this.select()"

                            >
                        </div>
                        <input type="hidden" name="update_user_position" value="1">
                    </form>

                    <form method="POST">
                        <div class="form-row">
                            <label for="inline_username"><?= $settingsOutlineIcon('user') ?> Username:</label>
                            <input 
                                type="text" 
                                id="inline_username" 
                                name="new_username" 
                                value="<?= htmlspecialchars($current_username) ?>"
                                onfocus="this.select()"
                                onkeydown="if(event.key==='Enter'){this.form.submit();return false;}"
                            >
                        </div>
                        <input type="hidden" name="update_username" value="1">
                    </form>
                    </div>
                </section>
            
            
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <section class="settings-section">
                <h2><?= $settingsOutlineIcon('shield-alt') ?> Authenticator App</h2>
                <?php if ($totpNotice): ?><div class="alert <?= $totpRecovery ? 'alert-success' : 'alert-error' ?>" role="status"><?= htmlspecialchars($totpNotice) ?></div><?php endif; ?>
                <?php if ($totpRecovery): ?>
                    <p>Save each recovery code somewhere private. Each works once.</p>
                    <pre><?= htmlspecialchars(implode("\n", $totpRecovery)) ?></pre>
                <?php endif; ?>
                <?php if ($totpAccount && $totpAccount['enabled_at']): ?>
                    <p>Authenticator is enabled for this account.</p>
                    <form method="post">
                        <input type="hidden" name="totp_csrf" value="<?= htmlspecialchars($_SESSION['totp_csrf']) ?>">
                        <input type="hidden" name="totp_action" value="disable">
                        <div class="form-row"><label>Current password</label><input type="password" name="totp_password" autocomplete="current-password"></div>
                        <div class="form-row"><label>Authenticator or recovery code</label><input name="totp_code" autocomplete="one-time-code"></div>
                        <button type="submit" class="btn-update">Disable authenticator</button>
                    </form>
                <?php else: ?>
                    <?php if ($totpAccount): ?>
                        <p>On your phone, open an authenticator app and choose <strong>Add account → Enter setup key</strong>.</p>
                        <p>Account: <strong>TESDA Inventory (<?= htmlspecialchars($current_username) ?>)</strong></p>
                        <p>Setup key: <code style="overflow-wrap:anywhere;user-select:all"><?= htmlspecialchars($totpAccount['secret']) ?></code></p>
                        <p>Choose <strong>Time based</strong>, then enter the six digit code below.</p>
                        <form method="post">
                            <input type="hidden" name="totp_csrf" value="<?= htmlspecialchars($_SESSION['totp_csrf']) ?>">
                            <input type="hidden" name="totp_action" value="enable">
                            <div class="form-row"><label>Current password</label><input type="password" name="totp_password" autocomplete="current-password"></div>
                            <div class="form-row"><label>Authenticator code</label><input name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></div>
                            <button type="submit" class="btn-update">Verify and enable</button>
                        </form>
                    <?php else: ?>
                        <p>Use a phone authenticator app to add a second step to sign in.</p>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="totp_csrf" value="<?= htmlspecialchars($_SESSION['totp_csrf']) ?>">
                        <input type="hidden" name="totp_action" value="start">
                        <div class="form-row"><label>Current password</label><input type="password" name="totp_password" autocomplete="current-password"></div>
                        <button type="submit" class="btn-update"><?= $totpAccount ? 'Generate a new setup key' : 'Set up authenticator' ?></button>
                    </form>
                <?php endif; ?>
            </section>

            <?php endif; ?>

            <!-- Password Section -->
            <section class="settings-section settings-card">
                <h2>
                    <?= $settingsOutlineIcon('lock') ?>
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
                                autocomplete="current-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                <?= $settingsOutlineIcon('eye') ?>
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
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                <?= $settingsOutlineIcon('eye') ?>
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
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <?= $settingsOutlineIcon('eye') ?>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_password" class="btn-update">
                        <?= $settingsOutlineIcon('key') ?>
                        Update Password
                    </button>
                </form>
            </section>
            
            <!-- Officers Management Section -->
            <section class="settings-section settings-card officers-manager">
                <div class="officers-manager-header">
                    <h2><?= $settingsOutlineIcon('user-friends') ?> Manage Officers</h2>
                    <span class="officer-count"><?= count($officers) ?> <?= count($officers) === 1 ? 'Officer' : 'Officers' ?></span>
                </div>
                <form method="POST" class="officer-add-form">
                    <div class="officer-fields">
                        <div class="form-row">
                            <label for="officer_name">Officer Name</label>
                            <input type="text" id="officer_name" name="officer_name" placeholder="Enter officer name" required>
                        </div>
                        <div class="form-row">
                            <label for="officer_position">Position</label>
                            <input type="text" id="officer_position" name="officer_position" placeholder="Enter position" required>
                        </div>
                    </div>
                    <button type="submit" name="add_officer" class="officer-add-button"><?= $settingsOutlineIcon('plus') ?> Add Officer</button>
                </form>
                <div class="officer-register">
                    <div class="officer-register-header">
                        <h3>Registered Officers</h3>
                        <span><?= count($officers) ?> <?= count($officers) === 1 ? 'record' : 'records' ?></span>
                    </div>
                    <?php if ($officers): ?>
                    <div class="officer-table-scroll">
                        <table class="registered-officers">
                            <thead><tr><th>Name</th><th>Position</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($officers as $officer): $officerId = (int)$officer['officer_id']; ?>
                                <tr>
                                <?php if (isset($_POST['edit_officer']) && (int)($_POST['officer_id'] ?? 0) === $officerId): ?>
                                    <td>
                                        <form method="POST" id="officerEdit<?= $officerId ?>">
                                            <input type="hidden" name="officer_id" value="<?= $officerId ?>">
                                            <input type="hidden" name="edit_officer_fields" value="1">
                                            <input type="text" name="new_officer_name" value="<?= htmlspecialchars($officer['officer_name'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Officer name" required autofocus>
                                        </form>
                                    </td>
                                    <td><input type="text" form="officerEdit<?= $officerId ?>" name="new_officer_position" value="<?= htmlspecialchars($officer['officer_position'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Officer position" required></td>
                                    <td><div class="officer-row-actions">
                                        <button type="submit" form="officerEdit<?= $officerId ?>" class="officer-icon-button officer-edit" aria-label="Save officer" title="Save officer"><?= $settingsOutlineIcon('check') ?></button>
                                        <a href="user_settings.php" class="officer-icon-button officer-delete" aria-label="Cancel edit" title="Cancel edit"><?= $settingsOutlineIcon('times') ?></a>
                                    </div></td>
                                <?php else: ?>
                                    <td><?= htmlspecialchars($officer['officer_name']) ?></td>
                                    <td><?= htmlspecialchars($officer['officer_position']) ?></td>
                                    <td><div class="officer-row-actions">
                                        <form method="POST">
                                            <input type="hidden" name="officer_id" value="<?= $officerId ?>">
                                            <button type="submit" name="edit_officer" class="officer-icon-button officer-edit" aria-label="Edit <?= htmlspecialchars($officer['officer_name'], ENT_QUOTES, 'UTF-8') ?>" title="Edit officer"><?= $settingsOutlineIcon('pen') ?></button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this officer?');">
                                            <input type="hidden" name="officer_id" value="<?= $officerId ?>">
                                            <button type="submit" name="delete_officer" class="officer-icon-button officer-delete" aria-label="Delete <?= htmlspecialchars($officer['officer_name'], ENT_QUOTES, 'UTF-8') ?>" title="Delete officer"><?= $settingsOutlineIcon('trash-alt') ?></button>
                                        </form>
                                    </div></td>
                                <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="help-text">No officers added yet. Add your first officer above.</p>
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
                icon.querySelector('use').setAttribute('href', '#settings-icon-eye-slash');
            } else {
                field.type = 'password';
                icon.querySelector('use').setAttribute('href', '#settings-icon-eye');
            }
        }

        function updateThemeToggle() {
            const button = document.getElementById('themeToggle');
            const darkModeEnabled = document.body.classList.contains('dark-mode');
            const icon = button.querySelector('i');
            const label = button.querySelector('span');

            button.setAttribute('aria-pressed', darkModeEnabled ? 'true' : 'false');
            icon.querySelector('use').setAttribute('href', darkModeEnabled ? '#settings-icon-sun' : '#settings-icon-moon');
            label.textContent = darkModeEnabled ? 'Light Mode' : 'Dark Mode';
        }

        document.getElementById('themeToggle').addEventListener('click', function() {
            const darkModeEnabled = document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme', darkModeEnabled ? 'dark' : 'light');
            updateThemeToggle();
        });
        updateThemeToggle();
        
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

        // Also save scroll position for inline officer edit form (which is dynamically created)
        document.addEventListener('submit', function(e) {
            if (e.target && e.target.id && e.target.id.startsWith('editOfficerFieldsForm')) {
                sessionStorage.setItem('scrollPosition', window.scrollY);
            }
        });

        // Restore scroll position after page load
        window.addEventListener('load', function() {
            const scrollPos = sessionStorage.getItem('scrollPosition');
            if (scrollPos) {
                window.scrollTo(0, parseInt(scrollPos));
                sessionStorage.removeItem('scrollPosition');
            }
        });

        // Cancel officer name edit when clicking outside
        document.addEventListener('mousedown', function(event) {
            // Support multiple edit forms 
            const editForms = document.querySelectorAll('form[id^="editOfficerFieldsForm"]');
            if (editForms.length > 0) {
                let insideAny = false;
                editForms.forEach(function(form) {
                    if (form.contains(event.target)) {
                        insideAny = true;
                    }
                });
                if (!insideAny) {
                    // Save scroll position before canceling
                    sessionStorage.setItem('scrollPosition', window.scrollY);
                    // Submit a hidden cancel form to reload the page without saving
                    const cancelForm = document.createElement('form');
                    cancelForm.method = 'GET';
                    document.body.appendChild(cancelForm);
                    cancelForm.submit();
                }
            }
        });
    </script>
</body>
</html>
