<?php
header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

$input = json_decode(file_get_contents('php://input'), true);

$esp32_device_id = $input['device_id']    ?? '';
$scanned_data    = $input['scanned_data'] ?? '';

if (empty($esp32_device_id) || empty($scanned_data)) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

try {
    // Buang scan lama yang pending
    $pdo->prepare("DELETE FROM gm861_scan_queue WHERE esp32_device_id = ? AND status = 'pending'")
        ->execute([$esp32_device_id]);

    // Simpan scan baru
    $pdo->prepare("INSERT INTO gm861_scan_queue (esp32_device_id, scanned_data, status, created_at) VALUES (?, ?, 'pending', NOW())")
        ->execute([$esp32_device_id, $scanned_data]);

    echo json_encode(['success' => true, 'message' => 'Scan received']);
} catch (Exception $e) {
    error_log("GM861 submit error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>