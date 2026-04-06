<?php
// Set unique session name for Commercial edition
if (session_status() === PHP_SESSION_NONE) {
    session_name('LOCKER_COMMERCIAL');
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
$user_id = (int)$_SESSION['user_id'];
$locker_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($locker_id) {
    try {
        // Remove assignment
        $stmt = $pdo->prepare("UPDATE user_locker_assignments SET is_active = 0 WHERE user_id = ? AND locker_id = ? AND is_active = 1");
        $stmt->execute([$user_id, $locker_id]);
        
        // Return locker status to available
        $pdo->prepare("UPDATE lockers SET status = 'available' WHERE id = ?")->execute([$locker_id]);
    } catch (Exception $e) {
        // Suppress errors during redirect
        error_log("Commercial remove_access error: " . $e->getMessage());
    }
}

// Redirect back to dashboard or my-locker
header("Location: my-locker.php");
exit();
?>
