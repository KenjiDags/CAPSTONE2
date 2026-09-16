<?php
require 'config.php';

if (!empty($_COOKIE['remember_token'])) {
    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
    $stmt->bind_param("s", $_COOKIE['remember_token']);
    $stmt->execute();
    $stmt->close();
    setcookie('remember_token', '', time() - 3600, '/', 'localhost', false, true);
}

session_start();
session_destroy();
header('Location: index.php?logged_out=1');
exit;
