<?php
// ============================================================
// FILE: includes/functions.php - Helper Functions
// ============================================================

/**
 * Check if current user has a specific permission
 */
function has_permission(string $module, string $permission_key): bool
{
    if (!isset($_SESSION['user_id']) || !isset($GLOBALS['pdo'])) {
        return false;
    }

    // Super Admin always has full access
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Super Admin') {
        return true;
    }

    global $pdo;

    $sql = "SELECT COUNT(*) 
            FROM role_permissions rp
            INNER JOIN permissions p ON rp.permission_id = p.id
            INNER JOIN users u ON u.role_id = rp.role_id
            WHERE u.id = ? 
              AND p.module_name = ? 
              AND p.permission_key = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id'], $module, $permission_key]);

    return $stmt->fetchColumn() > 0;
}

/**
 * Require permission or redirect
 */
function require_permission(string $module, string $permission_key, string $redirect = 'dashboard.php')
{
    if (!has_permission($module, $permission_key)) {
        $_SESSION['msg'] = 'You do not have permission to perform this action.';
        $_SESSION['msg_type'] = 'danger';
        header("Location: $redirect");
        exit;
    }
}

/**
 * Log activity
 */
function log_activity(string $action, string $module = null, string $description = null): void
{
    global $pdo;
    if (!isset($pdo)) return;

    $user_id   = $_SESSION['user_id'] ?? null;
    $user_name = $_SESSION['user_name'] ?? 'System';
    $ip        = $_SERVER['REMOTE_ADDR'] ?? null;
    $agent     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs 
            (user_id, user_name, action, module, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $user_name, $action, $module, $description, $ip, $agent]);
    } catch (Exception $e) {
        // Logging must never break the main flow
    }
}

/**
 * Safe HTML escape
 */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format money
 */
function money($amount): string
{
    return '$' . number_format((float)$amount, 2);
}

/**
 * Flash message helper
 */
function set_flash(string $message, string $type = 'success')
{
    $_SESSION['msg'] = $message;
    $_SESSION['msg_type'] = $type;
}
