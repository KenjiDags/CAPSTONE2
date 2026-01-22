<?php
// Start session only if not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Check if user is logged in and has a valid session
if (
    empty($_SESSION['logged_in']) || 
    $_SESSION['logged_in'] !== true ||
    empty($_SESSION['user_id'])
) {
    // Destroy any existing session just in case
    session_unset();
    session_destroy();
    
    // Redirect to login page
    header('Location: login.php');
    exit;
}

// Optional extra security: check if the user agent matches
if (
    isset($_SESSION['user_agent']) && 
    $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']
) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}