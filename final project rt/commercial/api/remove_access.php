<?php
if (session_status() === PHP_SESSION_NONE) session_start();
set_error_handler(function($errno, $errstr) {
    echo json_encode(['success' => false, 'message' => "PHP Error: $errstr"]);
    exit();
});
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']); exit();
}
$config = dirname(__DIR__) . '/config.php';
if (!file_exists($config)) {
    echo json_encode(['success' => false, 'message' => 'Config not found: ' . $config]); exit();
}
require_once $config;
$user_id = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$locker_id = isset($input['locker_id']) ? (int)$input['locker_id'] : null;
try {
    if ($locker_id) {
        $stmt = $pdo->prepare("UPDATE user_locker_assignments SET is_active = 0 WHERE user_id = ? AND locker_id = ? AND is_active = 1");
        $stmt->execute([$user_id, $locker_id]);
        $pdo->prepare("UPDATE lockers SET status = 'available' WHERE id = ?")->execute([$locker_id]);
        $released = $stmt->rowCount();
    } else {
        $stmt = $pdo->prepare("SELECT locker_id FROM user_locker_assignments WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$user_id]);
        $locker_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("UPDATE user_locker_assignments SET is_active = 0 WHERE user_id = ? AND is_active = 1")->execute([$user_id]);
        if (!empty($locker_ids)) {
            $placeholders = implode(',', array_fill(0, count($locker_ids), '?'));
            $pdo->prepare("UPDATE lockers SET status = 'available' WHERE id IN ($placeholders)")->execute($locker_ids);
        }
        $released = count($locker_ids);
    }
    echo json_encode(['success' => true, 'message' => 'Access removed.', 'released_lockers' => $released]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}