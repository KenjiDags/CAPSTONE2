<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])){
    echo json_encode(['success'=>false,'message'=>'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$current = $_POST['currentPassword'] ?? '';
$new = $_POST['newPassword'] ?? '';

if(!$current || !$new){
    echo json_encode(['success'=>false,'message'=>'All fields are required']);
    exit;
}

// Get current password hash
$stmt = $conn->prepare("SELECT password FROM users WHERE user_id=? LIMIT 1");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$result = $stmt->get_result();
if($result && $result->num_rows>0){
    $user = $result->fetch_assoc();
    if(password_verify($current,$user['password'])){
        $newHash = password_hash($new,PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
        $update->bind_param("si",$newHash,$user_id);
        if($update->execute()){
            echo json_encode(['success'=>true]);
        }else{
            echo json_encode(['success'=>false,'message'=>'Failed to update password']);
        }
    } else {
        echo json_encode(['success'=>false,'message'=>'Current password incorrect']);
    }
}else{
    echo json_encode(['success'=>false,'message'=>'User not found']);
}
$stmt->close();
$conn->close();