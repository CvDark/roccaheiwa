<?php
ob_start(); // Start output buffering

// Set unique session name for Institution edition BEFORE session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_name('LOCKER_INSTITUTION');
    session_start();
}
date_default_timezone_set('Asia/Kuala_Lumpur');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Institution Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'locker_institution');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
    $_SESSION['db_connected'] = true;
} catch (PDOException $e) {
    $_SESSION['db_error']     = $e->getMessage();
    $_SESSION['db_connected'] = false;
    $pdo = null;
    error_log("Institution DB Connection Failed: " . $e->getMessage());
}

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isUser() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function sanitize($input) {
    if (is_array($input)) return array_map('sanitize', $input);
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function checkDatabase() {
    global $pdo;
    if (!$pdo) return false;
    try {
        $pdo->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        error_log("Institution DB Check Failed: " . $e->getMessage());
        return false;
    }
}

function isDbConnected() {
    return isset($_SESSION['db_connected']) && $_SESSION['db_connected'] === true;
}

if (ob_get_length()) ob_end_flush();
?>