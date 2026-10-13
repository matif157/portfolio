<?php
/**
 * SPS Professional Services CRM
 * Session Authentication & Role Permission Helpers
 */

require_once __DIR__ . '/functions.php';

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to access the SPS CRM.');
        redirect('login.php');
    }
}

function current_user() {
    global $pdo;
    static $user = null;
    if ($user === null && is_logged_in()) {
        $stmt = $pdo->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ? AND u.status = 'active'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user) {
            // Account deactivated or deleted
            session_destroy();
            redirect('login.php');
        }
    }
    return $user;
}

function user_can($permission_code) {
    global $pdo;
    $user = current_user();
    if (!$user) return false;
    
    // Admin role (role_id = 1) has all permissions
    if ($user['role_id'] == 1) return true;
    
    static $user_permissions = null;
    if ($user_permissions === null) {
        $stmt = $pdo->prepare("
            SELECT p.code 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$user['role_id']]);
        $user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    return in_array($permission_code, $user_permissions);
}

function require_permission($permission_code) {
    require_login();
    if (!user_can($permission_code)) {
        log_activity('Unauthorized Access Attempt', 'security', 0, "User attempted to access permission: $permission_code", 'stage2_sensitive');
        die('<div style="font-family:sans-serif; padding:40px; text-align:center;"><h2>403 Access Denied</h2><p>You do not have permission to view or modify this page.</p><p><a href="dashboard.php">Return to Dashboard</a></p></div>');
    }
}
