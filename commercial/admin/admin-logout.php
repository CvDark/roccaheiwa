<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('LOCKER_COMMERCIAL');
    session_start();
}
session_destroy();
header('Location: admin-login.php');
exit();