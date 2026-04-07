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

$username = "DamZu";
$password = "123456";
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "--- DUAL ADMIN FIX (COMMERCIAL & INSTITUTION) ---<br>";

// 1. INSTITUTION
$inst_env = loadEnvLocal(__DIR__ . '/institution/.env');
$pdo_inst = connectDB($inst_env);
if ($pdo_inst) {
    try {
        $stmt = $pdo_inst->prepare("UPDATE admins SET password = ? WHERE username = ?");
        $stmt->execute([$hash, $username]);
        echo "✅ Institution DB: User '$username' updated.<br>";
    } catch (Exception $e) { echo "❌ Institution Error: " . $e->getMessage() . "<br>"; }
}

// 2. COMMERCIAL
$comm_env = loadEnvLocal(__DIR__ . '/commercial/.env');
$pdo_comm = connectDB($comm_env);
if ($pdo_comm) {
    try {
        $stmt = $pdo_comm->prepare("UPDATE admins SET password = ? WHERE username = ?");
        $stmt->execute([$hash, $username]);
        if ($stmt->rowCount() > 0) {
            echo "✅ Commercial DB: User '$username' updated.<br>";
        } else {
            // If doesn't exist, maybe create it? Or let user know.
            // Check if exists first
            $chk = $pdo_comm->prepare("SELECT id FROM admins WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetch()) {
                echo "⚠️ Commercial DB: User '$username' found but no changes made (maybe already hashed).<br>";
            } else {
                echo "❌ Commercial DB: User '$username' NOT FOUND in this database.<br>";
            }
        }
    } catch (Exception $e) { echo "❌ Commercial Error: " . $e->getMessage() . "<br>"; }
}
?>
