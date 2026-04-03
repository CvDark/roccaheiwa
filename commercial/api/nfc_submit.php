<?php
// api/nfc_submit.php
// ESP32 sends scanned NFC UID
// Purpose: 'register' (card registration) or 'access' (locker unlock)

// Clean any output buffering before sending JSON (config.php uses ob_start/ob_end_flush)
while (ob_get_level()) ob_end_clean();

header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

// Clean again after config.php in case it flushed anything
while (ob_get_level()) ob_end_clean();

$input = json_decode(file_get_contents('php://input'), true);

$esp32_device_id = trim($input['device_id'] ?? '');
$nfc_uid         = strtoupper(trim($input['uid'] ?? ''));
$purpose         = $input['purpose'] ?? 'access';

if (empty($esp32_device_id) || empty($nfc_uid)) {
    echo json_encode(['success' => false, 'message' => 'Missing device_id or uid']);
    exit;
}

if (!in_array($purpose, ['register', 'access'])) {
    $purpose = 'access';
}

try {
    // Remove old pending scans for this device (keep queue fresh)
    $pdo->prepare("
        DELETE FROM nfc_scan_queue
        WHERE esp32_device_id = ? AND status = 'pending'
    ")->execute([$esp32_device_id]);

    // Insert new scan — try with purpose column first, fallback without
    try {
        $pdo->prepare("
            INSERT INTO nfc_scan_queue (esp32_device_id, nfc_uid, purpose, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ")->execute([$esp32_device_id, $nfc_uid, $purpose]);
    } catch (Exception $e2) {
        // purpose column might not exist yet — insert without it
        $pdo->prepare("
            INSERT INTO nfc_scan_queue (esp32_device_id, nfc_uid, status, created_at)
            VALUES (?, ?, 'pending', NOW())
        ")->execute([$esp32_device_id, $nfc_uid]);
    }

    echo json_encode(['success' => true, 'message' => 'NFC scan received', 'uid' => $nfc_uid]);

} catch (Exception $e) {
    error_log("nfc_submit error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>