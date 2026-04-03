<?php
header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$input   = json_decode(file_get_contents('php://input'), true);

$locker_id = (int)($input['locker_id'] ?? 0);
$nfc_uid   = strtoupper(trim($input['nfc_uid'] ?? ''));

if (empty($locker_id) || empty($nfc_uid)) {
    echo json_encode(['success' => false, 'message' => 'Missing locker_id or nfc_uid']);
    exit;
}

// Validate UID format — hex characters only, 4-20 chars
if (!preg_match('/^[0-9A-F]{4,20}$/', $nfc_uid)) {
    echo json_encode(['success' => false, 'message' => 'Format NFC UID tidak sah']);
    exit;
}

try {
    // Verify user ada assignment ke locker ini
    $stmt = $pdo->prepare("
        SELECT ula.id 
        FROM user_locker_assignments ula
        JOIN lockers l ON l.id = ula.locker_id
        WHERE ula.locker_id = ?
          AND ula.user_id   = ?
          AND ula.is_active = 1
          AND l.status     != 'maintenance'
        LIMIT 1
    ");
    $stmt->execute([$locker_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Tiada akses ke locker ini']);
        exit;
    }

    // Invalidate session lama untuk user + locker ini
    $pdo->prepare("
        UPDATE nfc_sessions 
        SET status = 'expired' 
        WHERE user_id = ? AND locker_id = ? AND status = 'active'
    ")->execute([$user_id, $locker_id]);

    // Buat session baru — expire dalam 5 minit
    $pdo->prepare("
        INSERT INTO nfc_sessions (user_id, locker_id, nfc_uid, status, created_at, expires_at)
        VALUES (?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE))
    ")->execute([$user_id, $locker_id, $nfc_uid]);

    echo json_encode(['success' => true, 'message' => 'NFC session registered. Sila tap phone ke locker.']);

} catch (Exception $e) {
    error_log("nfc_register_session error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>