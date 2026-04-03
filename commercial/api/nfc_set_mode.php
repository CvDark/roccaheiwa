<?php
// commercial/api/nfc_set_mode.php
// Dipanggil dari profile.php bila user klik "Register NFC Card"
// Set register mode ON/OFF untuk device yang linked ke locker user

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';
while (ob_get_level()) ob_end_clean();


if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$input     = json_decode(file_get_contents('php://input'), true);
$mode      = (int)($input['mode'] ?? 0);        // 1=ON, 0=OFF
$device_id = trim($input['device_id'] ?? '');   // device_id dari profile.php

try {
    // Kalau device_id dihantar dari client, verify ia wujud dalam DB
    if (!empty($device_id)) {
        $verify = $pdo->prepare("
            SELECT device_id FROM lockers
            WHERE device_id = ?
              AND device_id IS NOT NULL AND device_id != ''
              AND status != 'maintenance'
            LIMIT 1
        ");
        $verify->execute([$device_id]);
        $found = $verify->fetch();
        if (!$found) {
            // device_id tak wujud — jangan proceed
            echo json_encode(['success' => false, 'message' => 'Device ID tidak sah atau tidak aktif.']);
            exit;
        }
    } else {
        // Fallback: ambil dari locker user sendiri
        $stmt = $pdo->prepare("
            SELECT l.device_id FROM user_locker_assignments ula
            JOIN lockers l ON l.id = ula.locker_id
            WHERE ula.user_id = ? AND ula.is_active = 1
              AND l.device_id IS NOT NULL AND l.device_id != ''
              AND l.status != 'maintenance'
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $dev = $stmt->fetch();
        $device_id = $dev['device_id'] ?? '';

        if (empty($device_id)) {
            echo json_encode(['success' => false, 'message' => 'Tiada device ESP32 dijumpai. Pastikan locker dah dikonfigurasikan.']);
            exit;
        }
    }

    // Set register mode
    $pdo->prepare("
        UPDATE lockers SET nfc_register_mode = ? WHERE device_id = ?
    ")->execute([$mode, $device_id]);

    // Manage timestamp file used by nfc_check_mode.php for auto-reset
    $stamp_file = sys_get_temp_dir() . '/nfc_reg_' . md5($device_id) . '.txt';

    if ($mode === 1) {
        // Record when register mode was turned ON
        file_put_contents($stamp_file, time());
    } else {
        // Clear timestamp when turned OFF
        @unlink($stamp_file);
        // Clear ALL pending scans for this device (regardless of purpose column value)
        $pdo->prepare("
            DELETE FROM nfc_scan_queue
            WHERE esp32_device_id = ? AND status = 'pending'
        ")->execute([$device_id]);
    }

    echo json_encode([
        'success'   => true,
        'mode'      => $mode,
        'device_id' => $device_id,
        'message'   => $mode ? 'Register mode ON — sedia untuk scan' : 'Register mode OFF'
    ]);

} catch (Exception $e) {
    error_log("commercial nfc_set_mode error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>