<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password diperlukan']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User tidak dijumpai']);
        exit;
    }

    if (password_verify($password, $user['password_hash'])) {
        echo json_encode(['success' => true, 'message' => 'Password disahkan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Password salah. Cuba lagi.']);
    }

} catch (Exception $e) {
    error_log("Verify password error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>