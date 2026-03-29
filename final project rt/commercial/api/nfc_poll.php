<?php
// api/nfc_poll.php
// Browser poll masa user cuba buka locker dengan NFC card
// Verify: UID card == user yang login, dan user ada access ke locker tu

header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$locker_id = (int)($_GET['locker_id'] ?? 0);

if (empty($locker_id)) {
    echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Missing locker_id']);
    exit;
}

try {
    // Dapatkan locker + device_id
    $stmt = $pdo->prepare("SELECT id, device_id, status FROM lockers WHERE id = ? LIMIT 1");
    $stmt->execute([$locker_id]);
    $locker = $stmt->fetch();

    if (!$locker || empty($locker['device_id'])) {
        echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Locker atau device tidak dijumpai']);
        exit;
    }

    if ($locker['status'] === 'maintenance') {
        echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Locker dalam maintenance']);
        exit;
    }

    $device_id = $locker['device_id'];

    // Dapatkan NFC UID yang didaftarkan untuk user ini
    $stmt = $pdo->prepare("SELECT nfc_uid FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || empty($user['nfc_uid'])) {
        echo json_encode([
            'success' => false,
            'scanned' => false,
            'message' => 'Anda belum mendaftarkan NFC card. Sila daftar dalam Profile.'
        ]);
        exit;
    }

    $registered_uid = $user['nfc_uid'];

    // Semak ada NFC scan 'access' pending dari device ini dalam 30 saat
    $stmt = $pdo->prepare("
        SELECT id, nfc_uid
        FROM nfc_scan_queue
        WHERE esp32_device_id = ?
          AND purpose     = 'access'
          AND status      = 'pending'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$device_id]);
    $scan = $stmt->fetch();

    if (!$scan) {
        echo json_encode(['success' => false, 'scanned' => false, 'message' => 'No scan yet']);
        exit;
    }

    $scanned_uid = $scan['nfc_uid'];

    // Mark processed
    $pdo->prepare("UPDATE nfc_scan_queue SET status='processed', processed_at=NOW() WHERE id=?")
        ->execute([$scan['id']]);

    // Verify UID match
    if ($scanned_uid !== $registered_uid) {
        $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, locker_id, device_id, access_method, key_used, success, notes, timestamp) 
            VALUES (?, ?, ?, 'nfc_card', ?, 0, 'NFC UID mismatch', NOW())
        ")->execute([$user_id, $locker_id, $device_id, $scanned_uid]);

        echo json_encode([
            'success' => false,
            'scanned' => true,
            'message' => 'NFC card tidak dikenali. Sila guna card yang didaftarkan.'
        ]);
        exit;
    }

    // Verify user ada assignment ke locker ini
    $stmt2 = $pdo->prepare("
        SELECT ula.key_value,
               COALESCE(ula.custom_name, l.unique_code) AS locker_name,
               COALESCE(ula.custom_location, '')         AS location
        FROM user_locker_assignments ula
        JOIN lockers l ON l.id = ula.locker_id
        WHERE ula.locker_id = ?
          AND ula.user_id   = ?
          AND ula.is_active = 1
          AND l.status     != 'maintenance'
        LIMIT 1
    ");
    $stmt2->execute([$locker_id, $user_id]);
    $assignment = $stmt2->fetch();

    if (!$assignment) {
        $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, locker_id, device_id, access_method, key_used, success, notes, timestamp) 
            VALUES (?, ?, ?, 'nfc_card', ?, 0, 'No assignment for this locker', NOW())
        ")->execute([$user_id, $locker_id, $device_id, $scanned_uid]);

        echo json_encode([
            'success' => false,
            'scanned' => true,
            'message' => 'Anda tiada akses ke locker ini.'
        ]);
        exit;
    }

    // Semua OK — log dan set UNLOCK
    $pdo->prepare("
        INSERT INTO activity_logs 
        (user_id, locker_id, device_id, access_method, key_used, success, notes, timestamp) 
        VALUES (?, ?, ?, 'nfc_card', ?, 1, 'NFC card verified', NOW())
    ")->execute([$user_id, $locker_id, $device_id, $assignment['key_value']]);

    $pdo->prepare("UPDATE lockers SET command_status = 'UNLOCK' WHERE id = ?")
        ->execute([$locker_id]);

    echo json_encode([
        'success' => true,
        'scanned' => true,
        'message' => 'Access granted!',
        'data'    => [
            'locker_name' => $assignment['locker_name'],
            'location'    => $assignment['location'],
        ]
    ]);

} catch (Exception $e) {
    error_log("nfc_poll error: " . $e->getMessage());
    echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Server error']);
}
?>