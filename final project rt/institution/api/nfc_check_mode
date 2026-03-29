<?php
// institution/api/nfc_check_mode.php
// ESP32 poll endpoint — check sama ada register mode active untuk device ni
// Response: plain text "REGISTER" atau "ACCESS"

header('Content-Type: text/plain');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

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

    if ($row && (int)$row['nfc_register_mode'] === 1) {
        echo "REGISTER";
    } else {
        echo "ACCESS";
    }

} catch (Exception $e) {
    echo "ACCESS"; // fallback safe
}
?>