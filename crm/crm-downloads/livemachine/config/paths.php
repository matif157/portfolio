<?php
declare(strict_types=1);

/**
 * Runtime-detected URL base for the project, so absolute paths work whether
 * the project sits at htdocs root (`/attendance-system/...`) or one level
 * deeper (`/new/attendance-system/...`).
 *
 * Resolves to e.g. "/new/attendance-system" — no trailing slash.
 */
if (!defined('BASE_URL')) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base   = str_replace('\\', '/', dirname($script));
    if ($base === '/' || $base === '.') $base = '';
    define('BASE_URL', rtrim($base, '/'));
}
