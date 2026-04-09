<?php
require_once 'institution/admin/config.php';

$username = 'DamZu';
$password = '123456';
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // 1. Force update to exactly 123456 hash
    $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE username = ?");
    $stmt->execute([$hash, $username]);
    
    // 2. Fetch and verify
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    
    if ($row) {
        echo "User: " . $row['username'] . "\n";
        echo "Hash in DB: " . $row['password'] . "\n";
        echo "Hash Length: " . strlen($row['password']) . "\n";
        if (password_verify($password, $row['password'])) {
            echo "SUCCESS: Verification passed in this script.\n";
        } else {
            echo "FAILED: Verification failed even after update.\n";
        }
    } else {
        echo "User not found.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
