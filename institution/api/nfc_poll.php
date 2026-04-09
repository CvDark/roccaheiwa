<?php
// institution/api/nfc_poll.php
// Browser poll semasa student tap NFC card kat locker
//
// LOGIC:
// 1. Verify NFC UID → cari student (registered_matrics → users)
// 2. Semak ada assignment untuk locker ini:
//    → Ada assignment  = UNLOCK (access mode)
//    → Tiada assignment + locker available = AUTO ASSIGN + UNLOCK (claim mode)
//    → Tiada assignment + locker occupied/lain = REJECT

header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Not logged in']);
    exit;
}

$locker_id = (int)($_GET['locker_id'] ?? 0);

if (empty($locker_id)) {
    echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Missing locker_id']);
    exit;
}

try {
    // ── 1. Dapatkan locker info ──
    $stmt = $pdo->prepare("
        SELECT id, device_id, status, unique_code,
               COALESCE(locker_key, '') AS locker_key
        FROM lockers WHERE id = ? LIMIT 1
    ");
    $stmt->execute([$locker_id]);
    $locker = $stmt->fetch();

    if (!$locker || empty($locker['device_id'])) {
        echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Locker atau device tidak dijumpai']);
        exit;
    }

    if ($locker['status'] === 'maintenance') {
        echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Locker dalam penyelenggaraan']);
        exit;
    }

    $device_id = $locker['device_id'];

    // ── 2. Semak ada NFC scan pending ──
    $stmt = $pdo->prepare("
        SELECT id, nfc_uid
        FROM nfc_scan_queue
        WHERE esp32_device_id = ?
          AND purpose     = 'access'
          AND status      = 'pending'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$device_id]);
    $scan = $stmt->fetch();

    if (!$scan) {
        echo json_encode(['success' => false, 'scanned' => false, 'message' => 'No scan yet']);
        exit;
    }

    $nfc_uid = $scan['nfc_uid'];

    // Mark sebagai processed
    $pdo->prepare("UPDATE nfc_scan_queue SET status='processed', processed_at=NOW() WHERE id=?")
        ->execute([$scan['id']]);

    // ── 3. Verify UID → cari student ──
    // Semak registered_matrics dulu, fallback ke users
    $card_user = null;

    $stmt_rm = $pdo->prepare("
        SELECT rm.id_number, u.id AS user_id, u.full_name,
               u.user_id_number, u.user_type, u.institution
        FROM registered_matrics rm
        JOIN users u ON u.user_id_number = rm.id_number AND u.is_active = 1
        WHERE rm.nfc_uid = ?
        LIMIT 1
    ");
    $stmt_rm->execute([$nfc_uid]);
    $rm_match = $stmt_rm->fetch();

    if ($rm_match) {
        $card_user = $rm_match;
    } else {
        $stmt_u = $pdo->prepare("
            SELECT id AS user_id, full_name, user_id_number, user_type, institution
            FROM users
            WHERE nfc_uid = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt_u->execute([$nfc_uid]);
        $card_user = $stmt_u->fetch();
    }

    if (!$card_user) {
        $pdo->prepare("
            INSERT INTO activity_logs
            (user_id, locker_id, device_id, access_method, key_used, success, timestamp)
            VALUES (?, ?, ?, 'nfc_card', ?, 0, NOW())
        ")->execute([$_SESSION['user_id'], $locker_id, $device_id, $nfc_uid]);

        echo json_encode([
            'success' => false,
            'scanned' => true,
            'message' => 'NFC card tidak dikenali. Sila hubungi admin.'
        ]);
        exit;
    }

    $token_user_id = (int)$_SESSION['user_id'];
    $card_owner_id = (int)$card_user['user_id'];

    // ── 4. SECURITY: Semak card yang di-tap adalah milik user yang sedang login ──
    if ($token_user_id !== $card_owner_id) {
        $pdo->prepare("
            INSERT INTO activity_logs
            (user_id, locker_id, device_id, access_method, key_used, success, timestamp)
            VALUES (?, ?, ?, 'nfc_card', ?, 0, NOW())
        ")->execute([$token_user_id, $locker_id, $device_id, $nfc_uid]);

        echo json_encode([
            'success' => false,
            'scanned' => true,
            'message' => 'NFC card ini bukan milik anda. Sila tap card anda sendiri.'
        ]);
        exit;
    }

    // ── 5. Semak assignment untuk locker ini ──
    $stmt_assign = $pdo->prepare("
        SELECT ula.key_value,
               COALESCE(ula.custom_name, l.unique_code) AS locker_name,
               COALESCE(ula.custom_location, '')         AS location,
               l.id AS lid
        FROM user_locker_assignments ula
        JOIN lockers l ON l.id = ula.locker_id
        WHERE ula.locker_id = ?
          AND ula.user_id   = ?
          AND ula.is_active = 1
          AND l.status     != 'maintenance'
        LIMIT 1
    ");
    $stmt_assign->execute([$locker_id, $card_owner_id]);
    $assignment = $stmt_assign->fetch();

    // ── 6a. Ada assignment → UNLOCK ──
    if ($assignment) {
        $pdo->prepare("
            INSERT INTO activity_logs
            (user_id, locker_id, device_id, access_method, key_used, success, timestamp)
            VALUES (?, ?, ?, 'nfc_card', ?, 1, NOW())
        ")->execute([$card_owner_id, $assignment['lid'], $device_id, $nfc_uid]);

        $pdo->prepare("UPDATE lockers SET command_status = 'UNLOCK' WHERE id = ?")
            ->execute([$locker_id]);

        echo json_encode([
            'success'  => true,
            'scanned'  => true,
            'action'   => 'access',
            'message'  => 'Access granted!',
            'data'     => [
                'full_name'      => $card_user['full_name'],
                'user_id_number' => $card_user['user_id_number'],
                'user_type'      => $card_user['user_type'],
                'institution'    => $card_user['institution'],
                'locker_name'    => $assignment['locker_name'],
                'location'       => $assignment['location'],
                'access_key'     => $assignment['key_value'], // Added access_key back
            ]
        ]);
        exit;
    }

    // ── 6b. Tiada assignment — semak boleh claim tak ──

    // Locker kena 'available'
    if ($locker['status'] !== 'available') {
        $pdo->prepare("
            INSERT INTO activity_logs
            (user_id, locker_id, device_id, access_method, key_used, success, timestamp)
            VALUES (?, ?, ?, 'nfc_card', ?, 0, NOW())
        ")->execute([$card_owner_id, $locker_id, $device_id, $nfc_uid]);

        echo json_encode([
            'success' => false,
            'scanned' => true,
            'message' => 'Locker ini tidak available (status: ' . $locker['status'] . ').'
        ]);
        exit;
    }

    // Semak locker tak ada orang lain yang assign
    $chk_occupied = $pdo->prepare("
        SELECT COUNT(*) FROM user_locker_assignments
        WHERE locker_id = ? AND is_active = 1
    ");
    $chk_occupied->execute([$locker_id]);
    if ($chk_occupied->fetchColumn() > 0) {
        echo json_encode([
            'success' => false,
            'scanned' => true,
            'message' => 'Locker ini sudah dituntut oleh pengguna lain.'
        ]);
        exit;
    }

    // ── 5c. AUTO ASSIGN locker ke student ──
    $access_key = 'LK-' . strtoupper(substr(md5(uniqid($card_owner_id . $locker_id, true)), 0, 6));

    $pdo->prepare("
        INSERT INTO user_locker_assignments
        (user_id, locker_id, key_value, is_active, assigned_at)
        VALUES (?, ?, ?, 1, NOW())
    ")->execute([$card_owner_id, $locker_id, $access_key]);

    // Update locker status → occupied
    $pdo->prepare("UPDATE lockers SET status = 'occupied' WHERE id = ?")
        ->execute([$locker_id]);

    // Log assign
    $pdo->prepare("
        INSERT INTO activity_logs
        (user_id, locker_id, device_id, access_method, key_used, success, timestamp)
        VALUES (?, ?, ?, 'nfc_card', ?, 1, NOW())
    ")->execute([$card_owner_id, $locker_id, $device_id, $nfc_uid]); // Consistently log $nfc_uid for card scan

    // UNLOCK terus selepas assign
    $pdo->prepare("UPDATE lockers SET command_status = 'UNLOCK' WHERE id = ?")
        ->execute([$locker_id]);

    echo json_encode([
        'success'  => true,
        'scanned'  => true,
        'action'   => 'assigned',
        'message'  => 'Locker berjaya di-assign dan dibuka!',
        'data'     => [
            'full_name'      => $card_user['full_name'],
            'user_id_number' => $card_user['user_id_number'],
            'user_type'      => $card_user['user_type'],
            'institution'    => $card_user['institution'],
            'locker_name'    => $locker['unique_code'],
            'location'       => '',
            'access_key'     => $access_key,
        ]
    ]);

} catch (Exception $e) {
    error_log("inst nfc_poll error: " . $e->getMessage());
    echo json_encode(['success' => false, 'scanned' => false, 'message' => 'Server error']);
}
?>