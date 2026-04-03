<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

define('DB_HOST', 'localhost');
define('DB_NAME', 'locker_institution');
define('DB_USER', 'root');
define('DB_PASS', '');

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