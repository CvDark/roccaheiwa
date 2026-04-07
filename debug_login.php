<?php
require_once 'institution/admin/config.php';

$username = 'DamZu';
$password_to_test = '123456';

try {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "User '$username' not found in database.\n";
    } else {
        echo "User found. ID: " . $admin['id'] . "\n";
        echo "Stored Hash: " . $admin['password'] . "\n";
        echo "Is Active: " . $admin['is_active'] . "\n";
        
        if (password_verify($password_to_test, $admin['password'])) {
            echo "VERIFICATION SUCCESS: Password matches hash.\n";
        } else {
            echo "VERIFICATION FAILED: Password does NOT match hash.\n";
            
            // Debug: Let's see what a fresh hash of '123456' looks like
            $fresh_hash = password_hash($password_to_test, PASSWORD_DEFAULT);
            echo "Fresh Hash of '$password_to_test': $fresh_hash\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
