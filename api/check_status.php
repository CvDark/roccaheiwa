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
    echo "IDLE";
    exit;
}

function checkAndResetCommand($pdo, $locker_id) {
    if (!$pdo) return "IDLE";
    try {
        $stmt = $pdo->prepare("SELECT command_status FROM lockers WHERE id = ? LIMIT 1");
        $stmt->execute([$locker_id]);
        $row = $stmt->fetch();
        
        if ($row && !empty($row['command_status'])) {
            $cmd = strtoupper($row['command_status']);
            
            // If there's an active command, we consume it and reset it to IDLE
            // This prevents the ESP32 from receiving UNLOCK continuously and looping
            if ($cmd === 'UNLOCK' || $cmd === 'LOCK') {
                $pdo->prepare("UPDATE lockers SET command_status = 'IDLE' WHERE id = ?")->execute([$locker_id]);
                return $cmd;
            }
        }
        return "IDLE";
    } catch (Exception $e) {
        return "IDLE";
    }
}

// Check both systems for any pending commands
$comm_status = checkAndResetCommand($pdo_comm, $locker_id);
$inst_status = checkAndResetCommand($pdo_inst, $locker_id);

// Priority: UNLOCK > LOCK > IDLE
if ($comm_status === 'UNLOCK' || $inst_status === 'UNLOCK') {
    echo "UNLOCK";
} elseif ($comm_status === 'LOCK' || $inst_status === 'LOCK') {
    echo "LOCK";
} else {
    // Return IDLE so the ESP32 knows to do nothing (maintains its current state)
    echo "IDLE";
}
?>
