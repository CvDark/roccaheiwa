<?php
// api/config.php
// Central config to manage both Commercial and Institution DBs for the ESP32 Gateway
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep logs in error_log, not in output to avoid breaking ESP32 JSON

function loadEnv($path)
{
    $realPath = realpath($path);
    if (!$realPath || !file_exists($realPath)) {
        error_log("Env file not found at: " . $path);
        return [];
    }

    $env = [];
    $lines = file($realPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0)
            continue;

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
if (!empty($comm_env)) {
    try {
        $host = $comm_env['DB_HOST'] ?? '127.0.0.1';
        $db   = $comm_env['DB_NAME'] ?? '';
        $user = $comm_env['DB_USER'] ?? '';
        $pass = $comm_env['DB_PASS'] ?? '';

        $pdo_comm = new PDO(
            "mysql:host=$host;dbname=$db;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        error_log("Commercial DB Connection Failed: " . $e->getMessage());
    }
} else {
    error_log("Commercial .env is empty or missing.");
}

// Connect to Institution DB
if (!empty($inst_env)) {
    try {
        $host = $inst_env['DB_HOST'] ?? '127.0.0.1';
        $db   = $inst_env['DB_NAME'] ?? '';
        $user = $inst_env['DB_USER'] ?? '';
        $pass = $inst_env['DB_PASS'] ?? '';

        $pdo_inst = new PDO(
            "mysql:host=$host;dbname=$db;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    } catch (PDOException $e) {
        error_log("Institution DB Connection Failed: " . $e->getMessage());
    }
} else {
    error_log("Institution .env is empty or missing.");
}
?>