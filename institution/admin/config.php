<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_name('LOCKER_INSTITUTION');
    session_start();
}
date_default_timezone_set('Asia/Kuala_Lumpur');

// Institution Database - load from .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            
            // Set for getenv(), $_ENV, and $_SERVER
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Function to safely get environment variables
if (!function_exists('get_env_var')) {
    function get_env_var($key, $default = null) {
        return getenv($key) ?: ($_ENV[$key] ?? ($_SERVER[$key] ?? $default));
    }
}

define('DB_HOST', get_env_var('DB_HOST', 'localhost'));
define('DB_NAME', get_env_var('DB_NAME', ''));
define('DB_USER', get_env_var('DB_USER', ''));
define('DB_PASS', get_env_var('DB_PASS', ''));

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    $pdo = null;
    error_log("Admin DB Failed: " . $e->getMessage());
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}
function requireAdmin() {
    if (!isAdminLoggedIn()) { header("Location: login.php"); exit; }
}
function redirect($u) { header("Location: $u"); exit; }
function sanitize($input) {
    if (is_array($input)) return array_map('sanitize', $input);
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
if (ob_get_length()) ob_end_flush();
?>