<?php
// api/config.php
// Central config to manage both Commercial and Institution DBs for the ESP32 Gateway
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide error output to prevent breaking JSON

function loadEnv($path) {
    if (!file_exists($path)) return [];
    
    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env[trim($parts[0])] = trim($parts[1]);
        }
    }
    return $env;
}

// Load configurations
$comm_env = loadEnv(__DIR__ . '/../commercial/.env');
$inst_env = loadEnv(__DIR__ . '/../institution/.env');

$pdo_comm = null;
$pdo_inst = null;

// Connect to Commercial DB
try {
    if (!empty($comm_env)) {
        $pdo_comm = new PDO(
            "mysql:host=" . ($comm_env['DB_HOST'] ?? 'localhost') . ";dbname=" . ($comm_env['DB_NAME'] ?? '') . ";charset=utf8mb4",
            $comm_env['DB_USER'] ?? '',
            $comm_env['DB_PASS'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }
} catch (PDOException $e) {
    error_log("Commercial API DB Connection Failed: " . $e->getMessage());
}

// Connect to Institution DB
try {
    if (!empty($inst_env)) {
        $pdo_inst = new PDO(
            "mysql:host=" . ($inst_env['DB_HOST'] ?? 'localhost') . ";dbname=" . ($inst_env['DB_NAME'] ?? '') . ";charset=utf8mb4",
            $inst_env['DB_USER'] ?? '',
            $inst_env['DB_PASS'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }
} catch (PDOException $e) {
    error_log("Institution API DB Connection Failed: " . $e->getMessage());
}
?>
