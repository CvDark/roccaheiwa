<?php
// institution/api/nfc_set_mode.php
// Set register mode ON/OFF — Dipanggil dari profile.php (user) atau nfc_management.php (admin)

header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$input     = json_decode(file_get_contents('php://input'), true);
$device_id = trim($input['device_id'] ?? '');
$mode      = (int)($input['mode'] ?? 0); // 1=ON, 0=OFF

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
            echo json_encode(['success' => false, 'message' => 'Device ID tidak sah atau tidak aktif.']);
            exit;
        }
    } else {
        // Fallback: ambil dari locker yang di-assign kepada user ini
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

        // Kalau masih kosong, cari mana-mana device dalam sistem
        if (empty($device_id)) {
            $stmt_any = $pdo->query("
                SELECT device_id FROM lockers
                WHERE device_id IS NOT NULL AND device_id != ''
                  AND status != 'maintenance'
                LIMIT 1
            ");
            $any = $stmt_any->fetch();
            $device_id = $any['device_id'] ?? '';
        }

        if (empty($device_id)) {
            echo json_encode(['success' => false, 'message' => 'Tiada device ESP32 dijumpai. Pastikan locker dah dikonfigurasikan.']);
            exit;
        }
    }

    // Set register mode pada device yang betul
    $pdo->prepare("
        UPDATE lockers SET nfc_register_mode = ? WHERE device_id = ?
    ")->execute([$mode, $device_id]);

    if ($mode === 0) {
        // Clear pending register scans untuk device ini
        $pdo->prepare("
            DELETE FROM nfc_scan_queue
            WHERE esp32_device_id = ? AND purpose = 'register' AND status = 'pending'
        ")->execute([$device_id]);
    }

    echo json_encode([
        'success'   => true,
        'mode'      => $mode,
        'device_id' => $device_id,
        'message'   => $mode ? 'Register mode ON — sedia untuk scan' : 'Register mode OFF'
    ]);

} catch (Exception $e) {
    error_log("institution nfc_set_mode error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>