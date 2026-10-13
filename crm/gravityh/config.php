<?php
/**
 * gravityh CRM - Configuration
 * Phase 1: Foundation
 * 
 * Yahan database credentials aur core application settings hain.
 * Is file ko production ke liye apne environment ke mutabiq update karein.
 */

// ============================================================
// DATABASE CONFIGURATION
// ============================================================
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_NAME', 'gravityh');
define('DB_USER', 'root');
define('DB_PASS', '');

// ============================================================
// APPLICATION CONFIGURATION
// ============================================================
define('APP_NAME', 'gravityh CRM');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'America/New_York');
define('APP_CURRENCY', 'USD');
define('APP_DATE_FORMAT', 'Y-m-d');
define('APP_ITEMS_PER_PAGE', 20);

// ============================================================
// SECURITY
// ============================================================
define('SESSION_NAME', 'gravityh_session');
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('CSRF_TOKEN_NAME', 'csrf_token');
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('UPLOAD_ALLOWED_EXTENSIONS', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,csv');

// ============================================================
// PATHS
// ============================================================
define('BASE_PATH', __DIR__);
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('UPLOAD_URL', 'uploads');
define('BACKUP_PATH', BASE_PATH . '/backups');
define('BACKUP_URL', 'backups');

// ============================================================
// AI CONFIGURATION (Phase 4 will use these)
// ============================================================
define('AI_PROVIDER', 'openai');
define('AI_MODEL', 'gpt-4o-mini');
define('AI_CONFIDENCE_THRESHOLD', 0.70);

// ============================================================
// ERROR REPORTING
// Production: display_errors = 0, log_errors = 1
// Development: display_errors = 1
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/logs/error.log');

// ============================================================
// TIMEZONE
// ============================================================
date_default_timezone_set(APP_TIMEZONE);

// ============================================================
// SESSION
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// ============================================================
// ENCRYPTION KEY (for sensitive settings - Phase 5 will use)
// ============================================================
define('ENCRYPTION_KEY', getenv('gravityh_ENCRYPTION_KEY') ?: 'change-this-key-in-production');