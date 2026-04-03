<?php
// api/nfc_remove_card.php
// Remove a specific NFC card from user's account

header('Content-Type: application/json');
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$input   = json_decode(file_get_contents('php://input'), true);
$card_id = (int) ($input['card_id'] ?? 0);

if ($card_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid card ID']);
    exit;
}

try {
    // Only delete if it belongs to this user
    $stmt = $pdo->prepare("DELETE FROM user_nfc_cards WHERE id = ? AND user_id = ?");
    $stmt->execute([$card_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Card not found or not yours']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'NFC card removed']);
} catch (Exception $e) {
    error_log("nfc_remove_card error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
