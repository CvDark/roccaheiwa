<?php
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

$comm_env = loadEnvLocal(__DIR__ . '/commercial/.env');
$pdo = connectDB($comm_env);

echo "--- Assigning Locker Key for COMM-001 (Commercial) ---<br>";

if ($pdo) {
    try {
        // Generate a 16-character hex key
        $new_key = bin2hex(random_bytes(8));
        $unique_code = 'COMM-001';

        $stmt = $pdo->prepare("UPDATE lockers SET locker_key = ? WHERE unique_code = ?");
        $stmt->execute([$new_key, $unique_code]);

        if ($stmt->rowCount() > 0) {
            echo "✅ Successfully assigned key <b>$new_key</b> to locker <b>$unique_code</b>.<br>";
        } else {
            // Check if it already has a key
            $chk = $pdo->prepare("SELECT locker_key FROM lockers WHERE unique_code = ?");
            $chk->execute([$unique_code]);
            $row = $chk->fetch();
            if ($row) {
                if ($row['locker_key']) {
                   echo "⚠️ Locker <b>$unique_code</b> already has a key: <b>" . $row['locker_key'] . "</b>.<br>";
                } else {
                   echo "❌ Failed to update locker <b>$unique_code</b>.<br>";
                }
            } else {
                echo "❌ Locker with unique code <b>$unique_code</b> not found.<br>";
            }
        }
    } catch (Exception $e) { echo "Error: " . $e->getMessage() . "<br>"; }
} else {
    echo "Commercial DB Connection Failed.<br>";
}
?>
