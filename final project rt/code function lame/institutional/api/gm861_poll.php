<?php
header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Not logged in']);
    exit;
}

$locker_id = (int)($_GET['locker_id'] ?? 0);

if (empty($locker_id)) {
    echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Missing locker_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, device_id, status FROM lockers WHERE id = ? LIMIT 1");
    $stmt->execute([$locker_id]);
    $locker = $stmt->fetch();

    if (!$locker || empty($locker['device_id'])) {
        echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Locker or device not found']);
        exit;
    }

    if ($locker['status'] === 'maintenance') {
        echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Locker in maintenance']);
        exit;
    }

    $device_id = $locker['device_id'];

    // Semak scan pending dari device ini dalam 30 saat
    $stmt = $pdo->prepare("
        SELECT id, scanned_data
        FROM gm861_scan_queue
        WHERE esp32_device_id = ?
          AND status = 'pending'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$device_id]);
    $scan = $stmt->fetch();

    if (!$scan) {
        echo json_encode(['success' => false, 'scanned' => false, 'message' => 'No scan pending']);
        exit;
    }

    // Mark sebagai processed
    $pdo->prepare("UPDATE gm861_scan_queue SET status = 'processed', processed_at = NOW() WHERE id = ?")
        ->execute([$scan['id']]);

    // Parse scanned data — JSON atau plain matric number
    $scanned_raw = trim($scan['scanned_data']);
    $matric_number = null;

    $try_json = json_decode($scanned_raw, true);
    if ($try_json) {
        $matric_number = $try_json['id'] ?? $try_json['matric'] ?? $try_json['student_id'] ?? null;
    }

    if (!$matric_number) {
        $matric_number = strtoupper($scanned_raw);
    }

    if (empty($matric_number)) {
        echo json_encode(['success' => false, 'scanned' => true, 'message' => 'Invalid scan data']);
        exit;
    }

    // Cari user by matric number
    $stmt = $pdo->prepare("
        SELECT id, full_name, user_id_number, user_type, institution
        FROM users
        WHERE user_id_number = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$matric_number]);
    $card_user = $stmt->fetch();

    if (!$card_user) {
        $pdo->prepare("INSERT INTO activity_logs (user_id, locker_id, device_id, access_method, key_used, success, notes, timestamp) VALUES (?, ?, ?, 'gm861_matric', ?, 0, 'Matric not found', NOW())")
            ->execute([$_SESSION['user_id'], $locker_id, $device_id, $matric_number]);
        echo json_encode(['success' => false, 'scanned' => true, 'message' => 'Matric number not found in the system.']);
        exit;
    }

    // Semak assignment
    $stmt2 = $pdo->prepare("
        SELECT ula.key_value,
               COALESCE(ula.custom_name, l.unique_code) AS locker_name,
               COALESCE(ula.custom_location, '')         AS location,
               l.id AS lid
        FROM user_locker_assignments ula
        JOIN lockers l ON l.id = ula.locker_id
        WHERE ula.locker_id = ?
          AND ula.user_id   = ?
          AND ula.is_active = 1
          AND l.status     != 'maintenance'
        LIMIT 1
    ");
    $stmt2->execute([$locker_id, $card_user['id']]);
    $match = $stmt2->fetch();

    if (!$match) {
        $pdo->prepare("INSERT INTO activity_logs (user_id, locker_id, device_id, access_method, key_used, success, notes, timestamp) VALUES (?, ?, ?, 'gm861_matric', ?, 0, 'No assignment for this locker', NOW())")
            ->execute([$card_user['id'], $locker_id, $device_id, $matric_number]);
        echo json_encode(['success' => false, 'scanned' => true, 'message' => 'Matric number has no access to the selected locker.']);
        exit;
    }

    // Log berjaya + set UNLOCK
    $pdo->prepare("INSERT INTO activity_logs (user_id, locker_id, device_id, access_method, key_used, success, notes, timestamp) VALUES (?, ?, ?, 'gm861_matric', ?, 1, 'Matric scan verified', NOW())")
        ->execute([$card_user['id'], $match['lid'], $device_id, $match['key_value']]);

    $pdo->prepare("UPDATE lockers SET command_status = 'UNLOCK' WHERE id = ?")
        ->execute([$match['lid']]);

    echo json_encode([
        'success' => true,
        'scanned' => true,
        'message' => 'Access granted!',
        'data'    => [
            'full_name'      => $card_user['full_name'],
            'user_id_number' => $card_user['user_id_number'],
            'user_type'      => $card_user['user_type'],
            'institution'    => $card_user['institution'],
            'locker_name'    => $match['locker_name'],
            'location'       => $match['location'],
        ]
    ]);

} catch (Exception $e) {
    error_log("GM861 poll error: " . $e->getMessage());
    echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Server error']);
}
?>