<?php
// admin/includes/auth.php — include at top of every admin page
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin-login.php');
    exit();
}

// Load DB — path dari admin/includes/ naik 2 level ke root
$config_path = dirname(__DIR__, 2) . '/config.php';
require_once $config_path;

$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'staff';