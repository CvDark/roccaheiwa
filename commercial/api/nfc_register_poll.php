<?php
// api/nfc_register_poll.php
// Browser poll — detect NFC card tap and register it to user's account (multi-card support)

header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'found' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id   = (int) $_SESSION['user_id'];
$device_id = trim($_GET['device_id'] ?? '');

if (empty($device_id)) {
    echo json_encode(['success' => false, 'found' => false, 'message' => 'Missing device_id']);
    exit;
}

// ── Auto-create user_nfc_cards table if it doesn't exist ──────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_nfc_cards (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            user_id       INT NOT NULL,
            nfc_uid       VARCHAR(50) NOT NULL,
            label         VARCHAR(100) DEFAULT NULL,
            registered_at DATETIME NOT NULL DEFAULT NOW(),
            UNIQUE KEY uk_nfc_uid (nfc_uid),
            KEY idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    error_log("nfc_register_poll: cannot create table: " . $e->getMessage());
    echo json_encode(['success' => false, 'found' => true, 'message' => 'DB setup error: ' . $e->getMessage()]);
    exit;
}

try {
    // Check for a pending scan from this device in the last 60 seconds
    $stmt = $pdo->prepare("
        SELECT id, nfc_uid
        FROM nfc_scan_queue
        WHERE esp32_device_id = ?
          AND status      = 'pending'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$device_id]);
    $scan = $stmt->fetch();

    if (!$scan) {
        echo json_encode(['success' => false, 'found' => false, 'message' => 'No scan yet']);
        exit;
    }

    $nfc_uid  = $scan['nfc_uid'];
    $scan_id  = $scan['id'];

    // Mark as processed first (prevents race condition with parallel requests)
    $update = $pdo->prepare("
        UPDATE nfc_scan_queue
        SET status='processed', processed_at=NOW()
        WHERE id = ? AND status = 'pending'
    ");
    $update->execute([$scan_id]);

    if ($update->rowCount() === 0) {
        // Already grabbed by another request
        echo json_encode(['success' => false, 'found' => false, 'message' => 'No scan yet']);
        exit;
    }

    // ── Duplicate checks ──────────────────────────────────────────────────────

    // Card used by a different user?
    $chk = $pdo->prepare("SELECT user_id FROM user_nfc_cards WHERE nfc_uid = ? AND user_id != ? LIMIT 1");
    $chk->execute([$nfc_uid, $user_id]);
    if ($chk->fetch()) {
        echo json_encode([
            'success' => false,
            'found'   => true,
            'message' => 'This card is already registered to another user.'
        ]);
        exit;
    }

    // Card already in this user's list?
    $dup = $pdo->prepare("SELECT id FROM user_nfc_cards WHERE nfc_uid = ? AND user_id = ? LIMIT 1");
    $dup->execute([$nfc_uid, $user_id]);
    if ($dup->fetch()) {
        echo json_encode([
            'success' => false,
            'found'   => true,
            'message' => 'This card is already in your account.'
        ]);
        exit;
    }

    // ── Insert new card ───────────────────────────────────────────────────────
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM user_nfc_cards WHERE user_id = ?");
    $cnt->execute([$user_id]);
    $cardCount = (int) $cnt->fetchColumn();
    $label     = 'Card ' . ($cardCount + 1);

    $pdo->prepare("INSERT INTO user_nfc_cards (user_id, nfc_uid, label, registered_at) VALUES (?, ?, ?, NOW())")
        ->execute([$user_id, $nfc_uid, $label]);

    echo json_encode([
        'success' => true,
        'found'   => true,
        'message' => 'NFC card registered successfully!',
        'uid'     => $nfc_uid,
        'label'   => $label,
    ]);

} catch (Exception $e) {
    error_log("nfc_register_poll error: " . $e->getMessage());
    // Return found=true so the UI shows the error instead of waiting forever
    echo json_encode([
        'success' => false,
        'found'   => true,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>