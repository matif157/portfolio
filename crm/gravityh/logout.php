<?php
/**
 * GRAVITY CRM - Logout
 * Phase 1: Foundation
 * 
 * Yahan session destroy karke user ko login page par bheja jata hai.
 */

require_once __DIR__ . '/functions.php';

// Logout activity record karo agar logged in ho (Stage-2 security event, Spec §29)
if (current_user()) {
    log_security_event('logout', 'User logged out', (int)current_user()['id'], 'info');
}

// Session destroy karo
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}
session_destroy();

redirect('login.php');