<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$input     = json_decode(file_get_contents('php://input'), true);
$locker_id = isset($input['locker_id']) ? (int)$input['locker_id'] : 0;
$note      = isset($input['note']) ? trim($input['note']) : '';

if (!$locker_id) {
    echo json_encode(['success' => false, 'message' => 'Locker tidak dipilih']);
    exit();
}

require_once '../config.php';
$user_id = $_SESSION['user_id'];

// Check locker is available
$stmt = $pdo->prepare("SELECT id, status FROM lockers WHERE id = ? AND status = 'available'");
$stmt->execute([$locker_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Locker tidak available']);
    exit();
}

// Check user has no pending request
$stmt = $pdo->prepare("SELECT id FROM locker_requests WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Anda sudah ada request pending. Tunggu kelulusan admin.']);
    exit();
}

// Check locker not already requested by someone
$stmt = $pdo->prepare("SELECT id FROM locker_requests WHERE locker_id = ? AND status = 'pending'");
$stmt->execute([$locker_id]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Locker ini sudah diminta oleh pengguna lain']);
    exit();
}

// Insert request
$stmt = $pdo->prepare("
    INSERT INTO locker_requests (user_id, locker_id, note, status, created_at)
    VALUES (?, ?, ?, 'pending', NOW())
");
$stmt->execute([$user_id, $locker_id, $note]);

echo json_encode(['success' => true, 'message' => 'Request berjaya dihantar']);