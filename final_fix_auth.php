<?php
// We'll define a custom connection function that handles the socket
function connectDB($env) {
    if (empty($env)) return null;
    $socket = '/opt/lampp/var/mysql/mysql.sock';
    try {
        return new PDO("mysql:unix_socket=$socket;dbname=" . ($env['DB_NAME'] ?? '') . ";charset=utf8mb4", $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        $hosts = ['127.0.0.1', 'localhost'];
        foreach ($hosts as $host) {
            try { return new PDO("mysql:host=$host;dbname=" . ($env['DB_NAME'] ?? '') . ";charset=utf8mb4", $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } catch (PDOException $e2) { continue; }
        }
    }
    return null;
}

function loadEnvLocal($path) {
    if (!file_exists($path)) return []; $env = []; $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) { $parts = explode('=', $line, 2); if (count($parts) === 2) $env[trim($parts[0])] = trim($parts[1]); }
    return $env;
}

$inst_env = loadEnvLocal(__DIR__ . '/institution/.env');
$pdo = connectDB($inst_env);

echo "--- FINAL AUTH RESET ---<br>";

if ($pdo) {
    try {
        $username = "DamZu";
        $password = "123456";
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Update with clean trim just in case
        $stmt = $pdo->prepare("UPDATE admins SET password = ?, username = TRIM(username) WHERE username = ? OR username = 'damzu'");
        $stmt->execute([$hash, $username]);

        if ($stmt->rowCount() > 0) {
            echo "✅ Password for <b>$username</b> has been reset to <b>$password</b> and HASHED.<br>";
        } else {
            echo "⚠️ No changes made. User might already have this hash or username mismatch.<br>";
        }

        // Verify immediately
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $row = $stmt->fetch();

        if ($row) {
            echo "User found in DB: " . $row['username'] . "<br>";
            if (password_verify($password, $row['password'])) {
                echo "🚀 <b>VERIFICATION TEST PASSED!</b> You should be able to login now.<br>";
            } else {
                echo "❌ <b>VERIFICATION TEST FAILED!</b> This is very strange.<br>";
            }
        } else {
            echo "❌ User '$username' not found after update.<br>";
        }

    } catch (Exception $e) { echo "Error: " . $e->getMessage() . "<br>"; }
} else {
    echo "Institution DB Connection Failed.<br>";
}
?>
