<?php
// institution/api/nfc_register_self_poll.php
// User biasa (student/staff) poll masa mereka register NFC card sendiri dari profile page
// This is different from nfc_register_poll.php which is used by admin to assign cards

header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$device_id = trim($_GET['device_id'] ?? '');

if (empty($device_id)) {
    echo json_encode(['success' => false, 'found' => false, 'message' => 'Missing device_id']);
    exit;
}

try {
    // Semak ada scan 'register' pending dari device ini dalam 60 saat
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

    // Semak UID ni dah digunakan oleh user lain
    $chk = $pdo->prepare("SELECT id, full_name FROM users WHERE nfc_uid = ? AND id != ? LIMIT 1");
    $chk->execute([$nfc_uid, $user_id]);
    $existing = $chk->fetch();

    if ($existing) {
        // Mark processed
        $pdo->prepare("UPDATE nfc_scan_queue SET status='processed', processed_at=NOW() WHERE id=?")
            ->execute([$scan['id']]);

        echo json_encode([
            'success' => false,
            'found'   => true,
            'message' => 'Card ini sudah didaftarkan oleh pengguna lain.'
        ]);
        exit;
    }

    // Mark processed
    $pdo->prepare("UPDATE nfc_scan_queue SET status='processed', processed_at=NOW() WHERE id=?")
        ->execute([$scan['id']]);

    // Simpan UID dalam profile user
    $pdo->prepare("UPDATE users SET nfc_uid=?, nfc_registered_at=NOW() WHERE id=?")
        ->execute([$nfc_uid, $user_id]);

    echo json_encode([
        'success' => true,
        'found'   => true,
        'message' => 'NFC card berjaya didaftarkan!',
        'uid'     => $nfc_uid
    ]);

} catch (Exception $e) {
    error_log("institution nfc_register_self_poll error: " . $e->getMessage());
    echo json_encode(['success' => false, 'found' => false, 'message' => 'Server error']);
}
?>
