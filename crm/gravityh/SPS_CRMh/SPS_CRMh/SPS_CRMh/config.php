<?php
/**
 * GRAVITY CRM — Global Configuration File
 * 
 * Yeh file pure Gravity CRM ke global constants aur configuration settings ko define karti hai.
 * Yahan database credentials, application name, timezone aur security parameters set hain.
 */

// Database Credentials (XAMPP Defaults for Gravity CRM)
// Yahan hum database ka name 'gravity' define kar rahe hain jo XAMPP MySQL me use hoga
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'gravity');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Information
define('APP_NAME', 'Gravity CRM');
define('APP_VERSION', '1.0.0-Phase1');
define('APP_URL', 'http://localhost/SPS_CRM');

// System Timezone Settings
// Yahan standard timezone set ki gayi hai taake tamam timestamps accurate rahen
define('DEFAULT_TIMEZONE', 'America/New_York');
date_default_timezone_set(DEFAULT_TIMEZONE);

// Security & Session Settings
define('SESSION_NAME', 'GRAVITY_CRM_SESS');
define('MAX_UPLOAD_SIZE', 15 * 1024 * 1024); // 15MB limit
define('ALLOWED_FILE_EXT', ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt']);

// Upload Directories
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('AVATAR_DIR', __DIR__ . '/uploads/avatars/');
define('TEMPLATE_DIR', __DIR__ . '/uploads/templates/');

// Make sure upload folders exist
if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0777, true); }
if (!is_dir(AVATAR_DIR)) { @mkdir(AVATAR_DIR, 0777, true); }
if (!is_dir(TEMPLATE_DIR)) { @mkdir(TEMPLATE_DIR, 0777, true); }

// AI Engine Default Settings
define('AI_CONFIDENCE_THRESHOLD', 0.85);

// Communication Provider Settings (Configured via Settings Page)
define('EMAIL_CONFIGURED', false);
define('SMS_CONFIGURED', false);
define('WHATSAPP_CONFIGURED', false);
define('VOICE_CONFIGURED', false);
define('OCR_CONFIGURED', false);

// Namespace Autoloader for SPS classes (Single-Folder Architecture Mapping)
spl_autoload_register(function ($class) {
    $prefix = 'SPS\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $parts = explode('\\', $relative_class);
    $className = end($parts);
    $file = __DIR__ . '/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
