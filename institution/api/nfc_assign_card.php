<?php
// institution/api/nfc_assign_card.php
// Admin assign NFC UID ke student (by user_id atau matric/id_number)

header('Content-Type: application/json');
header('ngrok-skip-browser-warning: true');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Support admin session (admin_id) ATAU user session (user_id)
$admin_id = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
if (!$admin_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);

$nfc_uid        = strtoupper(trim($input['nfc_uid']  ?? ''));
$target_user_id = (int)($input['user_id']            ?? 0);
$id_number      = trim($input['id_number']            ?? '');
$revoke         = !empty($input['revoke']);

// Handle revoke
if ($revoke && $target_user_id) {
    try {
        // First get user info (matric number)
        $stmt_u = $pdo->prepare("SELECT user_id_number FROM users WHERE id = ? LIMIT 1");
        $stmt_u->execute([$target_user_id]);
        $user_row = $stmt_u->fetch();
        $matric = $user_row ? $user_row['user_id_number'] : '';

        $pdo->beginTransaction();

        // 1. Clear in users table
        $pdo->prepare("UPDATE users SET nfc_uid=NULL, nfc_registered_at=NULL WHERE id=?")
            ->execute([$target_user_id]);

        // 2. Clear in registered_matrics table using matric number (if found)
        if ($matric) {
            $pdo->prepare("UPDATE registered_matrics SET nfc_uid=NULL WHERE id_number=?")
                ->execute([$matric]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Card berjaya direvoke']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Revoke Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Gagal revoke: Server error']);
    }
    exit;
}

if (empty($nfc_uid)) {
    echo json_encode(['success' => false, 'message' => 'Missing NFC UID']);
    exit;
}

if (empty($target_user_id) && empty($id_number)) {
    echo json_encode(['success' => false, 'message' => 'Missing user_id or id_number']);
    exit;
}

try {
    // Cari user
    if ($target_user_id) {
        $stmt = $pdo->prepare("SELECT id, full_name, user_id_number FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$target_user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, user_id_number FROM users WHERE user_id_number = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$id_number]);
    }
    $target_user = $stmt->fetch();

    if (!$target_user) {
        echo json_encode(['success' => false, 'message' => 'Student tidak dijumpai dalam sistem']);
        exit;
    }

    // Semak UID dah digunakan
    $chk = $pdo->prepare("SELECT id FROM users WHERE nfc_uid = ? AND id != ? LIMIT 1");
    $chk->execute([$nfc_uid, $target_user['id']]);
    if ($chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'NFC UID ini sudah diassign kepada student lain']);
        exit;
    }

    // Assign UID ke users table
    $pdo->prepare("
        UPDATE users
        SET nfc_uid = ?, nfc_registered_at = NOW(), nfc_assigned_by = ?
        WHERE id = ?
    ")->execute([$nfc_uid, $admin_id, $target_user['id']]);

    // Juga update registered_matrics kalau ada
    $pdo->prepare("
        UPDATE registered_matrics SET nfc_uid = ?
        WHERE id_number = ?
    ")->execute([$nfc_uid, $target_user['user_id_number']]);

    echo json_encode([
        'success'  => true,
        'message'  => 'NFC card berjaya diassign kepada ' . $target_user['full_name'],
        'data'     => [
            'user_id'        => $target_user['id'],
            'full_name'      => $target_user['full_name'],
            'user_id_number' => $target_user['user_id_number'],
            'nfc_uid'        => $nfc_uid,
        ]
    ]);

} catch (Exception $e) {
    error_log("inst nfc_assign_card error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>