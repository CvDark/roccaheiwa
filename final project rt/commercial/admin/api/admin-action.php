<?php
// admin/api/admin-action.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit();
}

$input      = json_decode(file_get_contents('php://input'), true);
$request_id = (int)($input['request_id'] ?? 0);
$action     = $input['action'] ?? '';
$note       = trim($input['note'] ?? '');

if (!$request_id || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']); exit();
}

require_once dirname(__DIR__, 2) . '/config.php';

// Get the request
$stmt = $pdo->prepare("SELECT * FROM locker_requests WHERE id = ? AND status = 'pending'");
$stmt->execute([$request_id]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) {
    echo json_encode(['success' => false, 'message' => 'Request not found or already processed']); exit();
}

if ($action === 'approve') {
    // Check locker still available
    $check = $pdo->prepare("SELECT id FROM lockers WHERE id = ? AND status = 'available'");
    $check->execute([$req['locker_id']]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Locker is no longer available']); exit();
    }

    $pdo->beginTransaction();
    try {
        // Update request status
        $pdo->prepare("UPDATE locker_requests SET status='approved', admin_note=?, updated_at=NOW() WHERE id=?")
            ->execute([$note, $request_id]);

        // Generate access key
        $access_key = bin2hex(random_bytes(16));

        // Assign locker to user
        $pdo->prepare("INSERT INTO user_locker_assignments (user_id, locker_id, key_value, assigned_at, is_active)
                        VALUES (?, ?, ?, NOW(), 1)")
            ->execute([$req['user_id'], $req['locker_id'], $access_key]);

        // Update locker status to active
        $pdo->prepare("UPDATE lockers SET status='active' WHERE id=?")
            ->execute([$req['locker_id']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Request approved. Locker assigned successfully.']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }

} else {
    // Reject
    $pdo->prepare("UPDATE locker_requests SET status='rejected', admin_note=?, updated_at=NOW() WHERE id=?")
        ->execute([$note, $request_id]);

    echo json_encode(['success' => true, 'message' => 'Request rejected.']);
}