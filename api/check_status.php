<?php
// api/check_status.php
// Central router: Checks if either commercial or institution system commanded the locker to unlock

while (ob_get_level()) ob_end_clean();
header('Content-Type: text/plain');
header('ngrok-skip-browser-warning: true');
require_once 'config.php';
while (ob_get_level()) ob_end_clean();

$locker_id = (int)($_GET['locker_id'] ?? 0);

if ($locker_id <= 0) {
    echo "LOCK";
    exit;
}

function checkStatus($pdo, $locker_id) {
    if (!$pdo) return "LOCK";
    try {
        $stmt = $pdo->prepare("SELECT status FROM lockers WHERE id = ? LIMIT 1");
        $stmt->execute([$locker_id]);
        $row = $stmt->fetch();
        return ($row && strtoupper($row['status']) === 'UNLOCK') ? "UNLOCK" : "LOCK";
    } catch (Exception $e) {
        return "LOCK";
    }
}

// Check both systems
$comm_status = checkStatus($pdo_comm, $locker_id);
$inst_status = checkStatus($pdo_inst, $locker_id);

// If either says UNLOCK, then unlock the hardware
if ($comm_status === 'UNLOCK' || $inst_status === 'UNLOCK') {
    echo "UNLOCK";
} else {
    echo "LOCK";
}
?>
