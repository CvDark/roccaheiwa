<?php
// Matikan paparan ralat untuk memastikan output JSON bersih
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

header('Content-Type: application/json');
require_once '../config.php';

// Mulakan session hanya jika belum bermula
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

// Sokong kedua-dua format: baru (access_key+locker_id) dan lama (id_number+locker_code)
$access_key  = $input['access_key']  ?? $input['key']         ?? '';
$locker_id   = $input['locker_id']   ?? $input['id']          ?? '';
$id_number   = $input['id_number']   ?? '';   // format lama
$locker_code = $input['locker_code'] ?? '';   // format lama

// Tentukan mod: baru atau lama
$use_new_mode = !empty($access_key) && !empty($locker_id);
$use_old_mode = !empty($id_number)  && !empty($locker_code);

if (!$use_new_mode && !$use_old_mode) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

try {
    if ($use_new_mode) {
        // ── MOD BARU: verify access_key + locker_id dalam user_locker_assignments ──
        $stmt = $pdo->prepare("
            SELECT ula.locker_id, l.status, l.id
            FROM user_locker_assignments ula
            JOIN lockers l ON l.id = ula.locker_id
            WHERE ula.locker_id  = ?
              AND ula.key_value  = ?
              AND ula.user_id    = ?
              AND ula.is_active  = 1
              AND l.status      != 'maintenance'
            LIMIT 1
        ");
        $stmt->execute([$locker_id, $access_key, $user_id]);
        $match = $stmt->fetch();

        if (!$match) {
            echo json_encode(['success' => false, 'message' => 'Access key tidak sah atau locker tidak dijumpai']);
            exit;
        }

        $final_locker_id = $match['id'];
        $key_used        = $access_key;

    } else {
        // ── MOD LAMA: id_number + locker_code ──
        $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_number = ? AND is_active = 1");
        $stmt->execute([$id_number]);
        $target_user = $stmt->fetch();
        if (!$target_user) { echo json_encode(['success' => false, 'message' => 'ID not found']); exit; }

        $stmt = $pdo->prepare("SELECT id, status FROM lockers WHERE (unique_code = ? OR device_id = ?) AND status != 'maintenance'");
        $stmt->execute([$locker_code, $locker_code]);
        $locker = $stmt->fetch();
        if (!$locker) { echo json_encode(['success' => false, 'message' => 'Locker not found']); exit; }

        $stmt = $pdo->prepare("SELECT * FROM user_locker_assignments WHERE user_id = ? AND locker_id = ? AND is_active = 1");
        $stmt->execute([$user_id, $locker['id']]);
        if (!$stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'No access to this locker']); exit; }

        $final_locker_id = $locker['id'];
        $key_used        = $id_number;
    }

    // Log aktiviti
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs 
        (user_id, locker_id, device_id, access_method, key_used, success, timestamp) 
        VALUES (?, ?, 'web_api', 'qr_code', ?, 1, NOW())
    ");
    $stmt->execute([$user_id, $final_locker_id, $key_used]);

    // Set command UNLOCK untuk ESP32
    $pdo->prepare("UPDATE lockers SET command_status = 'UNLOCK' WHERE id = ?")
        ->execute([$final_locker_id]);

    echo json_encode(['success' => true, 'message' => 'Locker berjaya dibuka!']);

} catch (Exception $e) {
    // Catat error di log server, tapi kembalikan mesej umum
    error_log("Open locker error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}