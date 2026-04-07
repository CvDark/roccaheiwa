<?php
// We'll define a custom connection function that tries both localhost and 127.0.0.1
function connectDB($env) {
    if (empty($env)) return null;
    $socket = '/opt/lampp/var/mysql/mysql.sock';
    try {
        return new PDO(
            "mysql:unix_socket=$socket;dbname=" . ($env['DB_NAME'] ?? '') . ";charset=utf8mb4",
            $env['DB_USER'] ?? '',
            $env['DB_PASS'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        $hosts = ['127.0.0.1', 'localhost'];
        foreach ($hosts as $host) {
            try {
                return new PDO(
                    "mysql:host=$host;dbname=" . ($env['DB_NAME'] ?? '') . ";charset=utf8mb4",
                    $env['DB_USER'] ?? '',
                    $env['DB_PASS'] ?? '',
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e2) { continue; }
        }
    }
    return null;
}

// Manually load envs since config.php might be failing
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

echo "--- Updating Commercial Locker ---\n";
if ($pdo_comm) {
    try {
        $pdo_comm->prepare("INSERT INTO lockers (id, unique_code, device_id, status) VALUES (1, 'COMM-001', 'DEV-COMM', 'available') ON DUPLICATE KEY UPDATE unique_code='COMM-001', device_id='DEV-COMM', status='available'")->execute();
        echo "Successfully updated Commercial id=1.\n";
    } catch (Exception $e) { echo "Comm Error: " . $e->getMessage() . "\n"; }
} else { echo "Comm DB Connection Failed.\n"; }

echo "\n--- Updating Institution Locker ---\n";
if ($pdo_inst) {
    try {
        $pdo_inst->prepare("UPDATE lockers SET unique_code='INST-BACKUP', device_id='INST-BACKUP' WHERE id=1")->execute();
        $pdo_inst->prepare("INSERT INTO lockers (id, unique_code, device_id, status) VALUES (2, 'INST-001', 'DEV-INST', 'available') ON DUPLICATE KEY UPDATE unique_code='INST-001', device_id='DEV-INST', status='available'")->execute();
        echo "Successfully updated Institution id=2.\n";
    } catch (Exception $e) { echo "Inst Error: " . $e->getMessage() . "\n"; }
} else { echo "Inst DB Connection Failed.\n"; }
?>

