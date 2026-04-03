<?php
header('ngrok-skip-browser-warning: true');
header('Content-Type: text/plain');
require_once '../config.php';

$locker_id = $_GET['locker_id'] ?? '';

if (empty($locker_id)) die("ID_MISSING");

try {
    $stmt = $pdo->prepare("SELECT command_status FROM lockers WHERE id = ? LIMIT 1");
    $stmt->execute([$locker_id]);
    $locker = $stmt->fetch();

    if (!$locker) die("LOCKER_NOT_FOUND");

    if ($locker['command_status'] === 'UNLOCK') {
        $pdo->prepare("UPDATE lockers SET command_status = 'IDLE' WHERE id = ?")->execute([$locker_id]);
        echo "UNLOCK";
    } elseif ($locker['command_status'] === 'LOCK') {
        $pdo->prepare("UPDATE lockers SET command_status = 'IDLE' WHERE id = ?")->execute([$locker_id]);
        echo "LOCK";
    } else {
        echo "LOCKED";
    }
} catch (Exception $e) {
    die("ERROR");
}
?>