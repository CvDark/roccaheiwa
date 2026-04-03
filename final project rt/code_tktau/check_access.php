<?php
include '../config/database.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = getUserID();
    $locker_id = $input['locker_id'] ?? '';
    $key = $input['key'] ?? '';
    $method = $input['method'] ?? 'manual';
    $device_id = $input['device_id'] ?? 'web_interface';
    
    // Check if key is valid for this locker and user
    $sql = "SELECT ak.* FROM access_keys ak
            WHERE ak.locker_id = ? AND ak.key_value = ? AND ak.user_id = ? AND ak.is_active = TRUE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $locker_id, $key, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $access_granted = true;
        
        // Log successful access
        log_activity($user_id, $locker_id, $device_id, $key, $method, true);
        
        echo json_encode(['access_granted' => true]);
    } else {
        // Log failed attempt
        log_activity($user_id, $locker_id, $device_id, $key, $method, false);
        
        echo json_encode(['access_granted' => false]);
    }
}

function log_activity($user_id, $locker_id, $device_id, $key, $method, $success) {
    global $conn;
    
    $sql = "INSERT INTO activity_logs (user_id, locker_id, device_id, key_used, access_method, success) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisssi", $user_id, $locker_id, $device_id, $key, $method, $success);
    $stmt->execute();
}
?>