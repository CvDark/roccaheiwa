<?php
// api/nfc_check_mode.php
// Central router: Checks if either commercial or institution system is in Register Mode

while (ob_get_level()) ob_end_clean();
header('Content-Type: text/plain');
header('ngrok-skip-browser-warning: true');
require_once 'config.php';
while (ob_get_level()) ob_end_clean();

$device_id = trim($_GET['device_id'] ?? '');

if (empty($device_id)) {
    echo "ACCESS";
    exit;
}

function checkMode($pdo, $device_id) {
    if (!$pdo) return false;
    try {
        $stmt = $pdo->prepare("SELECT nfc_register_mode FROM lockers WHERE device_id = ? LIMIT 1");
        $stmt->execute([$device_id]);
        $row = $stmt->fetch();
        return ($row && (int)$row['nfc_register_mode'] === 1);
    } catch (Exception $e) {
        return false;
    }
}

function autoResetMode($pdo, $device_id, $stamp_file) {
    if (!$pdo) return false;
    try {
        if (!file_exists($stamp_file)) {
            file_put_contents($stamp_file, time());
            return true;
        }

        $elapsed = time() - (int)file_get_contents($stamp_file);
        if ($elapsed > 90) {
            $pdo->prepare("UPDATE lockers SET nfc_register_mode = 0 WHERE device_id = ?")->execute([$device_id]);
            $pdo->prepare("DELETE FROM nfc_scan_queue WHERE esp32_device_id = ? AND status = 'pending'")->execute([$device_id]);
            @unlink($stamp_file);
            return false;
        }
        return true;
    } catch (Exception $e) {
        @unlink($stamp_file);
        return false;
    }
}

// 1. Check Commercial
if (checkMode($pdo_comm, $device_id)) {
    $stamp_file = sys_get_temp_dir() . '/comm_reg_' . md5($device_id) . '.txt';
    if (autoResetMode($pdo_comm, $device_id, $stamp_file)) {
        echo "REGISTER";
        exit;
    }
}

// 2. Check Institution
if (checkMode($pdo_inst, $device_id)) {
    $stamp_file = sys_get_temp_dir() . '/inst_reg_' . md5($device_id) . '.txt';
    if (autoResetMode($pdo_inst, $device_id, $stamp_file)) {
        echo "REGISTER";
        exit;
    }
}

// If neither is in register mode
echo "ACCESS";
?>
