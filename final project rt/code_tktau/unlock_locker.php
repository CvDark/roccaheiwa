<?php
// Matikan paparan ralat untuk memastikan output JSON bersih
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

header('Content-Type: application/json');
require_once '../config.php';  // Pastikan laluan betul

// Mulakan session (notis mungkin muncul tapi tidak dipaparkan)
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$id_number = $input['id_number'] ?? '';
$locker_code = $input['locker_code'] ?? '';

if (empty($id_number) || empty($locker_code)) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

try {
    // 1. Cari user berdasarkan ID number (untuk pengesahan)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id_number = ? AND is_active = 1");
    $stmt->execute([$id_number]);
    $target_user = $stmt->fetch();

    if (!$target_user) {
        echo json_encode(['success' => false, 'message' => 'ID not found']);
        exit;
    }

    // 2. Cari locker berdasarkan kod
    $stmt = $pdo->prepare("SELECT id, status FROM lockers WHERE (unique_code = ? OR device_id = ?) AND status != 'maintenance'");
    $stmt->execute([$locker_code, $locker_code]);
    $locker = $stmt->fetch();

    if (!$locker) {
        echo json_encode(['success' => false, 'message' => 'Locker not found or in maintenance']);
        exit;
    }

    // 3. Semak sama ada user yang login mempunyai akses ke locker tersebut
    $stmt = $pdo->prepare("SELECT * FROM user_locker_assignments WHERE user_id = ? AND locker_id = ? AND is_active = 1");
    $stmt->execute([$user_id, $locker['id']]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        echo json_encode(['success' => false, 'message' => 'You do not have access to this locker']);
        exit;
    }

    // 4. Log aktiviti buka kunci
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs 
        (user_id, locker_id, device_id, access_method, key_used, success, timestamp) 
        VALUES (?, ?, 'web_api', 'manual', ?, 1, NOW())
    ");
    $stmt->execute([$user_id, $locker['id'], $id_number]);

    // (Pilihan) Hantar arahan buka kunci ke perkakasan di sini
    // ...

    echo json_encode(['success' => true, 'message' => 'Locker opened successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}