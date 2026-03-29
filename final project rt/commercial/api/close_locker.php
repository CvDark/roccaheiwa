<?php
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

header('Content-Type: application/json');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$access_key = $input['access_key'] ?? '';
$locker_id  = $input['locker_id']  ?? '';

if (empty($access_key) || empty($locker_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

try {
    // Verify user ada akses ke locker ni
    $stmt = $pdo->prepare("
        SELECT ula.locker_id, l.id
        FROM user_locker_assignments ula
        JOIN lockers l ON l.id = ula.locker_id
        WHERE ula.locker_id = ?
          AND ula.key_value = ?
          AND ula.user_id   = ?
          AND ula.is_active = 1
          AND l.status     != 'maintenance'
        LIMIT 1
    ");
    $stmt->execute([$locker_id, $access_key, $user_id]);
    $match = $stmt->fetch();

    if (!$match) {
        echo json_encode(['success' => false, 'message' => 'Akses tidak sah']);
        exit;
    }

    // Log aktiviti
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs 
        (user_id, locker_id, device_id, access_method, key_used, success, timestamp) 
        VALUES (?, ?, 'web_api', 'qr_code', ?, 1, NOW())
    ");
    $stmt->execute([$user_id, $match['id'], $access_key]);

    // Set command LOCK untuk ESP32
    $pdo->prepare("UPDATE lockers SET command_status = 'LOCK' WHERE id = ?")
        ->execute([$match['id']]);

    echo json_encode(['success' => true, 'message' => 'Locker berjaya dikunci!']);

} catch (Exception $e) {
    error_log("Close locker error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
?>