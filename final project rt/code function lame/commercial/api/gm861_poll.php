<?php
header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');

require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$esp32_device_id = $_GET['device_id'] ?? '';

if (empty($esp32_device_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing device_id']);
    exit;
}

try {
    // Check ada scan baru pending dari device ini
    $stmt = $pdo->prepare("
        SELECT id, scanned_data 
        FROM gm861_scan_queue
        WHERE esp32_device_id = ? 
          AND status = 'pending'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$esp32_device_id]);
    $scan = $stmt->fetch();

    if (!$scan) {
        echo json_encode(['success' => false, 'message' => 'No scan yet']);
        exit;
    }

    // Parse QR JSON
    $qr_data = json_decode($scan['scanned_data'], true);

    if (!$qr_data || !isset($qr_data['access_key'], $qr_data['locker_id'])) {
        $pdo->prepare("UPDATE gm861_scan_queue SET status='processed', processed_at=NOW() WHERE id=?")
            ->execute([$scan['id']]);
        echo json_encode(['success' => false, 'message' => 'Format QR tidak sah. Gunakan QR dari sistem ini.']);
        exit;
    }

    // Mark processed
    $pdo->prepare("UPDATE gm861_scan_queue SET status='processed', processed_at=NOW() WHERE id=?")
        ->execute([$scan['id']]);

    // Verify — locker_system.lockers ada column name & location terus
    $stmt2 = $pdo->prepare("
        SELECT 
            l.id        AS lid,
            l.name      AS locker_name,
            l.location  AS location,
            l.device_id,
            ula.key_value
        FROM user_locker_assignments ula
        JOIN lockers l ON l.id = ula.locker_id
        WHERE ula.locker_id = ?
          AND ula.key_value = ?
          AND ula.user_id   = ?
          AND ula.is_active = 1
          AND l.status     != 'maintenance'
        LIMIT 1
    ");
    $stmt2->execute([
        $qr_data['locker_id'],
        $qr_data['access_key'],
        $_SESSION['user_id']
    ]);
    $match = $stmt2->fetch();

    if (!$match) {
        echo json_encode(['success' => false, 'message' => 'Akses tidak sah. Key atau locker tidak sepadan.']);
        exit;
    }

    // Log
    $pdo->prepare("
        INSERT INTO activity_logs (user_id, locker_id, device_id, access_method, key_used, success, timestamp)
        VALUES (?, ?, 'gm861', 'qr_code', ?, 1, NOW())
    ")->execute([$_SESSION['user_id'], $match['lid'], $qr_data['access_key']]);

    // UNLOCK command untuk ESP32
    $pdo->prepare("UPDATE lockers SET command_status = 'UNLOCK' WHERE id = ?")
        ->execute([$match['lid']]);

    echo json_encode([
        'success'     => true,
        'message'     => 'Access granted!',
        'locker_name' => $match['locker_name'] ?? '-',
        'location'    => $match['location']    ?? '-',
        'access_key'  => $qr_data['access_key'],
        'locker_id'   => $qr_data['locker_id']
    ]);

} catch (Exception $e) {
    error_log("GM861 poll error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>