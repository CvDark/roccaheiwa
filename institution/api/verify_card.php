<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$session_user_id = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$access_key = trim($input['access_key'] ?? '');
$locker_id  = (int)($input['locker_id']  ?? 0);
$card_data  = trim($input['card_data']  ?? '');

if (empty($locker_id) || empty($card_data)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

try {
    // ── 1. Parse card QR data ──
    // Format yang dijangka dari kad matrik/student card:
    // JSON: {"id":"A12345","name":"Ahmad","type":"student"} 
    // ATAU plain text: "A12345"
    // ATAU barcode number sahaja

    $card_user_id_number = null;
    $card_parsed = null;

    // Try JSON parse
    $try_json = json_decode($card_data, true);
    if ($try_json && isset($try_json['id'])) {
        $card_user_id_number = $try_json['id'];
        $card_parsed = $try_json;
    } elseif ($try_json && isset($try_json['matric'])) {
        $card_user_id_number = $try_json['matric'];
        $card_parsed = $try_json;
    } elseif ($try_json && isset($try_json['student_id'])) {
        $card_user_id_number = $try_json['student_id'];
        $card_parsed = $try_json;
    } else {
        // Plain text / barcode — treat as ID number directly
        $card_user_id_number = $card_data;
    }

    // ── 2. Find user by ID number ──
    $stmt = $pdo->prepare("
        SELECT id, full_name, user_id_number, user_type, institution, is_active
        FROM users
        WHERE user_id_number = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$card_user_id_number]);
    $card_user = $stmt->fetch();

    if (!$card_user) {
        $pdo->prepare("INSERT INTO activity_logs (user_id, locker_id, device_id, access_method, key_used, success, notes, timestamp) VALUES (?, ?, 'web_scan', 'card_scan', '', 0, 'Card not found', NOW())")
            ->execute([$session_user_id, $locker_id]);

        echo json_encode(['success' => false, 'message' => 'Kad tidak diiktiraf. ID tidak dijumpai dalam sistem.']);
        exit;
    }

    $card_owner_id = (int)$card_user['id'];

    // ── 3. Verify: card owner must match locker assignment ──
    // Cek sama ada matric owner ada assignment untuk locker ini
    $stmt2 = $pdo->prepare("
        SELECT ula.id, ula.locker_id, ula.key_value, l.id AS lid,
               COALESCE(ula.custom_name, l.unique_code) AS locker_name,
               COALESCE(ula.custom_location, '') AS location,
               l.device_id
        FROM user_locker_assignments ula
        JOIN lockers l ON l.id = ula.locker_id
        WHERE ula.locker_id = ?
          AND ula.user_id   = ?
          AND ula.is_active = 1
          AND l.status     != 'maintenance'
        LIMIT 1
    ");
    $stmt2->execute([$locker_id, $card_owner_id]);
    $match = $stmt2->fetch();

    if (!$match) {
        $pdo->prepare("INSERT INTO activity_logs (user_id, locker_id, device_id, access_method, key_used, success, notes, timestamp) VALUES (?, ?, 'web_scan', 'card_scan', '', 0, 'Locker not assigned to card owner', NOW())")
            ->execute([$card_owner_id, $locker_id]);

        echo json_encode(['success' => false, 'message' => 'Matrik ini tidak mempunyai akses ke locker ini.']);
        exit;
    }

    // ── 4. Log success ──
    $pdo->prepare("INSERT INTO activity_logs (user_id, locker_id, device_id, access_method, key_used, success, notes, timestamp) VALUES (?, ?, ?, 'card_scan', ?, 1, 'Matric scan verified', NOW())")
        ->execute([$card_owner_id, $match['lid'], $match['device_id'] ?? 'web_scan', $match['key_value']]);

    echo json_encode([
        'success' => true,
        'message' => 'Identiti disahkan!',
        'data'    => [
            'full_name'      => $card_user['full_name'],
            'user_id_number' => $card_user['user_id_number'],
            'user_type'      => $card_user['user_type'],
            'institution'    => $card_user['institution'],
            'locker_name'    => $match['locker_name'],
            'location'       => $match['location'],
            'locker_id'      => $match['lid'],
            'device_id'      => $match['device_id'],
        ]
    ]);

} catch (Exception $e) {
    error_log("verify_card error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>