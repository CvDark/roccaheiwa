<?php
// api/nfc_submit.php
// Central router: Forwards NFC scans to the appropriate database

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once 'config.php';
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

function insertScan($pdo, $device_id, $uid, $purpose) {
    if (!$pdo) return "PDO connection is NULL";
    try {
        // Remove old pending scans for this device to prevent clutter
        $pdo->prepare("DELETE FROM nfc_scan_queue WHERE esp32_device_id = ? AND status = 'pending'")->execute([$device_id]);
        
        $pdo->prepare("INSERT INTO nfc_scan_queue (esp32_device_id, nfc_uid, purpose, status, created_at) VALUES (?, ?, ?, 'pending', NOW())")
            ->execute([$device_id, $uid, $purpose]);
            
        return "SUCCESS";
    } catch (Exception $e) {
        error_log("NFC Insert Error: " . $e->getMessage());
        return "ERROR: " . $e->getMessage();
    }
}

function isRegisterMode($pdo, $device_id) {
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

if ($purpose === 'register') {
    // If it's a register scan, only send it to the system that is currently in register mode
    $comm_res = "NOT_IN_MODE";
    $inst_res = "NOT_IN_MODE";

    if (isRegisterMode($pdo_comm, $esp32_device_id)) {
        $comm_res = insertScan($pdo_comm, $esp32_device_id, $nfc_uid, $purpose);
    }
    if (isRegisterMode($pdo_inst, $esp32_device_id)) {
        $inst_res = insertScan($pdo_inst, $esp32_device_id, $nfc_uid, $purpose);
    }
    
    if ($comm_res === "SUCCESS" || $inst_res === "SUCCESS") {
        echo json_encode(['success' => true, 'message' => 'Registration scan routed', 'uid' => $nfc_uid, 'debug' => ['comm' => $comm_res, 'inst' => $inst_res]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed (No system in register mode or DB error)', 'debug' => ['comm' => $comm_res, 'inst' => $inst_res]]);
    }
} else {
    // If it's an access scan, we broadcast to BOTH databases
    $comm_res = insertScan($pdo_comm, $esp32_device_id, $nfc_uid, $purpose);
    $inst_res = insertScan($pdo_inst, $esp32_device_id, $nfc_uid, $purpose);
    
    if ($comm_res === "SUCCESS" || $inst_res === "SUCCESS") {
        echo json_encode(['success' => true, 'message' => 'Scan broadcasted', 'uid' => $nfc_uid, 'debug' => ['comm' => $comm_res, 'inst' => $inst_res]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to broadcast scan to any database', 'debug' => ['comm' => $comm_res, 'inst' => $inst_res]]);
    }
}
?>
