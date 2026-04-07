<?php
// Function to connect specifically to Institution DB
function connectInstDB($env) {
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
$pdo = connectInstDB($inst_env);

echo "--- Hashing Admin Password Fix ---\n";

if ($pdo) {
    try {
        // We will hash '123456' for user 'DamZu'
        $username = 'DamZu';
        $new_pass = '123456';
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE username = ?");
        $stmt->execute([$hash, $username]);

        if ($stmt->rowCount() > 0) {
            echo "Successfully hashed password for user '$username'. You can now login.\n";
        } else {
            echo "User '$username' not found or password already updated.\n";
        }
    } catch (Exception $e) { echo "Error: " . $e->getMessage() . "\n"; }
} else {
    echo "Institution DB Connection Failed.\n";
}
?>
