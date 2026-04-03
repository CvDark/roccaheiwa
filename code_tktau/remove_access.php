<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$locker_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$locker_id) {
    header('Location: my-locker.php');
    exit();
}

// Remove assignment
$pdo->prepare("
    UPDATE user_locker_assignments SET is_active = 0 
    WHERE user_id = ? AND locker_id = ? AND is_active = 1
")->execute([$user_id, $locker_id]);

// Set locker available
$pdo->prepare("UPDATE lockers SET status = 'available' WHERE id = ?")->execute([$locker_id]);

header('Location: my-locker.php?success=removed');
exit();