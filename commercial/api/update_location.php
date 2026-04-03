<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$locker_id = isset($input['locker_id']) ? (int)$input['locker_id'] : 0;
$location  = isset($input['location'])  ? trim($input['location']) : '';

if (!$locker_id || empty($location)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

require_once '../config.php';

$user_id = $_SESSION['user_id'];

// Verify this user is assigned to this locker
$check = $pdo->prepare("SELECT id FROM user_locker_assignments WHERE user_id = ? AND locker_id = ? AND is_active = 1");
$check->execute([$user_id, $locker_id]);
if (!$check->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Update location
$stmt = $pdo->prepare("UPDATE lockers SET location = ? WHERE id = ?");
$stmt->execute([$location, $locker_id]);

echo json_encode(['success' => true, 'message' => 'Location updated']);