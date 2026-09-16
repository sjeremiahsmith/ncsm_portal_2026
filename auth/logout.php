<?php

require_once __DIR__ . '/../includes/config.php';

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/functions.php';
    try {
        logActivity('logout', 'User logged out');
    } catch (Exception $e) {
        error_log('Failed to log logout: ' . $e->getMessage());
    }
}

$_SESSION = [];
session_destroy();

header('Location: ' . APP_URL . 'auth/login.php');
exit;
