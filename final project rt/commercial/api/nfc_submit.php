<?php
// api/nfc_submit.php
// ESP32 hantar UID NFC card yang baru discan
// Purpose: 'register' (masa user daftar card) atau 'access' (masa nak buka locker)

header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

$input = json_decode(file_get_contents('php://input'), true);

$esp32_device_id = trim($input['device_id'] ?? '');
$nfc_uid         = strtoupper(trim($input['uid'] ?? ''));
$purpose         = $input['purpose'] ?? 'access'; // 'register' atau 'access'

if (empty($esp32_device_id) || empty($nfc_uid)) {
    echo json_encode(['success' => false, 'message' => 'Missing device_id or uid']);
    exit;
}

if (!in_array($purpose, ['register', 'access'])) {
    $purpose = 'access';
}

try {
    // Buang scan lama yang pending untuk device + purpose yang sama
    $pdo->prepare("
        DELETE FROM nfc_scan_queue 
        WHERE esp32_device_id = ? AND purpose = ? AND status = 'pending'
    ")->execute([$esp32_device_id, $purpose]);

    // Simpan scan baru
    $pdo->prepare("
        INSERT INTO nfc_scan_queue (esp32_device_id, nfc_uid, purpose, status, created_at) 
        VALUES (?, ?, ?, 'pending', NOW())
    ")->execute([$esp32_device_id, $nfc_uid, $purpose]);

    echo json_encode(['success' => true, 'message' => 'NFC scan received', 'uid' => $nfc_uid]);

} catch (Exception $e) {
    error_log("nfc_submit error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>