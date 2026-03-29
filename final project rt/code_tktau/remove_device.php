<?php
include '../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = getUserID();
    $device_id = $input['device_id'] ?? '';
    
    $sql = "UPDATE user_devices SET is_active = FALSE 
            WHERE user_id = ? AND device_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $device_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove device']);
    }
}
?>