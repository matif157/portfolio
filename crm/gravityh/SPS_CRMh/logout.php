<?php
/**
 * SPS Professional Services CRM
 * User Logout Script
 */
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    log_activity('User Logout', 'auth', $_SESSION['user_id'] ?? 0, 'User logged out');
}

session_unset();
session_destroy();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

redirect('login.php');
