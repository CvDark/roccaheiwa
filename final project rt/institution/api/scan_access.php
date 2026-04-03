<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$input   = json_decode(file_get_contents('php://input'), true);

$access_key = trim($input['access_key'] ?? '');
$locker_id  = trim($input['locker_id']  ?? '');

if (empty($access_key) || empty($locker_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing access key or locker ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            ula.locker_id,
            ula.key_value,
            u.full_name,
            u.user_id_number,
            u.user_type,
            u.institution,
            l.id as lid,
            l.device_id,
            l.status as locker_status,
            l.unique_code,
            COALESCE(ula.custom_name, l.unique_code) AS locker_name,
            COALESCE(ula.custom_location, '') AS location
        FROM user_locker_assignments ula
        JOIN users   u ON u.id  = ula.user_id
        JOIN lockers l ON l.id  = ula.locker_id
        WHERE ula.locker_id = ?
          AND ula.key_value = ?
          AND ula.user_id   = ?
          AND ula.is_active = 1
          AND u.is_active   = 1
          AND l.status     != 'maintenance'
        LIMIT 1
    ");
    $stmt->execute([$locker_id, $access_key, $user_id]);
    $match = $stmt->fetch();

    if (!$match) {
        // Diagnose
        $chk = $pdo->prepare("SELECT COUNT(*) FROM user_locker_assignments WHERE user_id = ? AND locker_id = ? AND is_active = 1");
        $chk->execute([$user_id, $locker_id]);
        $has = $chk->fetchColumn();
        $msg = $has ? 'Access key tidak sah untuk locker ini.' : 'Locker tidak dijumpai atau anda tiada akses.';
        
        // Log failed attempt
        $pdo->prepare("INSERT INTO activity_logs (user_id, locker_id, device_id, access_method, key_used, success, timestamp) VALUES (?, ?, 'web_scan', 'qr_code', ?, 0, NOW())")
            ->execute([$user_id, $locker_id, $access_key]);

        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    // Log success
    $pdo->prepare("INSERT INTO activity_logs (user_id, locker_id, device_id, access_method, key_used, success, timestamp) VALUES (?, ?, ?, 'qr_code', ?, 1, NOW())")
        ->execute([$user_id, $match['lid'], $match['device_id'] ?? 'web_scan', $access_key]);

    echo json_encode([
        'success' => true,
        'message' => 'Access granted!',
        'data'    => [
            'full_name'      => $match['full_name'],
            'user_id_number' => $match['user_id_number'],
            'user_type'      => $match['user_type'],
            'institution'    => $match['institution'],
            'locker_name'    => $match['locker_name'],
            'location'       => $match['location'],
            'locker_id'      => $match['lid'],
            'device_id'      => $match['device_id'],
        ]
    ]);

} catch (Exception $e) {
    error_log("scan_access error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>