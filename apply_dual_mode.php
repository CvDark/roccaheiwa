<?php
// Function to connect to both systems
function connectDB($env) {
    if (empty($env)) return null;
    $socket = '/opt/lampp/var/mysql/mysql.sock';
    try {
        return new PDO("mysql:unix_socket=$socket;dbname=" . ($env['DB_NAME'] ?? '') . ";charset=utf8mb4", $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        $hosts = ['127.0.0.1', 'localhost'];
        foreach ($hosts as $host) {
            try {
                return new PDO("mysql:host=$host;dbname=" . ($env['DB_NAME'] ?? '') . ";charset=utf8mb4", $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } catch (PDOException $e2) { continue; }
        }
    }
    return null;
}

function loadEnvLocal($path) {
    if (!file_exists($path)) return [];
    $env = []; $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) $env[trim($parts[0])] = trim($parts[1]);
    }
    return $env;
}

$comm_env = loadEnvLocal(__DIR__ . '/commercial/.env');
$inst_env = loadEnvLocal(__DIR__ . '/institution/.env');

$pdo_comm = connectDB($comm_env);
$pdo_inst = connectDB($inst_env);

echo "--- Setting Dual-Mode Device ID (DEV-DUAL) ---\n";

if ($pdo_comm) {
    try {
        $pdo_comm->prepare("UPDATE lockers SET device_id = 'DEV-DUAL' WHERE id = 1")->execute();
        echo "Commercial Locker (ID 1) updated to DEV-DUAL.\n";
    } catch (Exception $e) { echo "Comm Error: " . $e->getMessage() . "\n"; }
}

if ($pdo_inst) {
    try {
        $pdo_inst->prepare("UPDATE lockers SET device_id = 'DEV-DUAL' WHERE id = 2")->execute();
        echo "Institution Locker (ID 2) updated to DEV-DUAL.\n";
    } catch (Exception $e) { echo "Inst Error: " . $e->getMessage() . "\n"; }
}
?>
