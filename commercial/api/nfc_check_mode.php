<?php
// commercial/api/nfc_check_mode.php
// ESP32 polls this every 3 seconds to know its operating mode.
// Returns plain text: "REGISTER" or "ACCESS"
// Auto-resets register mode after 90 seconds to prevent ESP32 from getting stuck.

while (ob_get_level()) ob_end_clean();
header('Content-Type: text/plain');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';
while (ob_get_level()) ob_end_clean();

$device_id = trim($_GET['device_id'] ?? '');

if (empty($device_id)) {
    echo "ACCESS";
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT nfc_register_mode
        FROM lockers
        WHERE device_id = ?
        LIMIT 1
    ");
    $stmt->execute([$device_id]);
    $row = $stmt->fetch();

    if (!$row || (int)$row['nfc_register_mode'] !== 1) {
        echo "ACCESS";
        exit;
    }

    // ── Auto-reset: if register mode has been ON for > 90s with no recent scan, reset it ──
    // This prevents ESP32 from being permanently stuck in REGISTER mode when
    // the browser never calls stopNFC() (e.g. page closed, ngrok dropped, timeout)
    $scanCheck = $pdo->prepare("
        SELECT MAX(created_at) AS last_scan
        FROM nfc_scan_queue
        WHERE esp32_device_id = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
    ");
    $scanCheck->execute([$device_id]);
    $scanRow = $scanCheck->fetch();

    // Check age using a timestamp file (no DB column needed)
    $stamp_file = sys_get_temp_dir() . '/nfc_reg_' . md5($device_id) . '.txt';

    if (!file_exists($stamp_file)) {
        // First time we see REGISTER mode for this device — record the time
        file_put_contents($stamp_file, time());
        echo "REGISTER";
        exit;
    }

    $mode_on_since = (int) file_get_contents($stamp_file);
    $elapsed = time() - $mode_on_since;

    if ($elapsed > 90) {
        // Mode has been ON too long — auto-reset to prevent ESP32 lockup
        $pdo->prepare("UPDATE lockers SET nfc_register_mode = 0 WHERE device_id = ?")
            ->execute([$device_id]);
        // Clear any leftover pending scans
        $pdo->prepare("DELETE FROM nfc_scan_queue WHERE esp32_device_id = ? AND status = 'pending'")
            ->execute([$device_id]);
        @unlink($stamp_file);
        echo "ACCESS";
        exit;
    }

    echo "REGISTER";

} catch (Exception $e) {
    @unlink($stamp_file ?? '');
    echo "ACCESS";
}
?>