<?php
// institution/api/nfc_register_poll.php
// Admin poll semasa assign NFC card ke student dalam admin panel
// ESP32 mesti dalam purpose='register' mode

header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Support admin session (admin_id) ATAU user session (user_id)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$device_id = trim($_GET['device_id'] ?? '');

if (empty($device_id)) {
    echo json_encode(['success' => false, 'found' => false, 'message' => 'Missing device_id']);
    exit;
}

try {
    // Semak ada NFC scan 'register' pending dalam 60 saat
    $stmt = $pdo->prepare("
        SELECT id, nfc_uid
        FROM nfc_scan_queue
        WHERE esp32_device_id = ?
          AND purpose     = 'register'
          AND status      = 'pending'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$device_id]);
    $scan = $stmt->fetch();

    if (!$scan) {
        echo json_encode(['success' => false, 'found' => false, 'message' => 'No scan yet']);
        exit;
    }

    $nfc_uid = $scan['nfc_uid'];

    // Mark processed
    $pdo->prepare("UPDATE nfc_scan_queue SET status='processed', processed_at=NOW() WHERE id=?")
        ->execute([$scan['id']]);

    // Semak UID dah digunakan user lain
    $chk_users = $pdo->prepare("SELECT id, full_name, user_id_number FROM users WHERE nfc_uid = ? LIMIT 1");
    $chk_users->execute([$nfc_uid]);
    $existing_user = $chk_users->fetch();

    $chk_rm = $pdo->prepare("SELECT id_number FROM registered_matrics WHERE nfc_uid = ? LIMIT 1");
    $chk_rm->execute([$nfc_uid]);
    $existing_rm = $chk_rm->fetch();

    if ($existing_user || $existing_rm) {
        $who = $existing_user ? ($existing_user['full_name'] . ' (' . $existing_user['user_id_number'] . ')') : $existing_rm['id_number'];
        echo json_encode([
            'success'  => false,
            'found'    => true,
            'uid'      => $nfc_uid,
            'message'  => 'Card ini sudah didaftarkan kepada: ' . $who
        ]);
        exit;
    }

    // UID baru, boleh diassign
    echo json_encode([
        'success' => true,
        'found'   => true,
        'uid'     => $nfc_uid,
        'message' => 'Card baru dikesan. Pilih student untuk assign.'
    ]);

} catch (Exception $e) {
    error_log("inst nfc_register_poll error: " . $e->getMessage());
    echo json_encode(['success' => false, 'found' => false, 'message' => 'Server error']);
}
?>