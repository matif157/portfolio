<?php
declare(strict_types=1);

/**
 * Central runtime config for XAMPP / any server deploy.
 * Values come from (in order): storage/config/local.php, .env, defaults.
 */

$root = dirname(__DIR__);
$localFile = $root . '/storage/config/local.php';
$envFile = $root . '/.env';

$env = [];

if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (
            strlen($value) >= 2
            && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))
        ) {
            $value = substr($value, 1, -1);
        }
        $env[$key] = $value;
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

$local = is_file($localFile) ? (require $localFile) : [];
if (!is_array($local)) {
    $local = [];
}

$get = static function (string $key, mixed $default = null) use ($local, $env): mixed {
    if (array_key_exists($key, $local)) {
        return $local[$key];
    }
    if (array_key_exists($key, $env)) {
        return $env[$key];
    }
    $fromEnv = getenv($key);
    return $fromEnv !== false ? $fromEnv : $default;
};

$basePath = (string) $get('APP_BASE_PATH', '/syed-professional-crm/public');
$basePath = '/' . trim(str_replace('\\', '/', $basePath), '/');
if ($basePath === '/') {
    $basePath = '';
}

$config = [
    'name' => (string) $get('APP_NAME', 'Syed Professional Services CRM'),
    'debug' => filter_var($get('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) $get('APP_URL', 'http://localhost/syed-professional-crm/public'), '/'),
    'base_path' => $basePath,
    'timezone' => (string) $get('APP_TIMEZONE', 'America/New_York'),
    'installed' => is_file($root . '/storage/installed.lock'),
    'company' => [
        'name' => (string) $get('COMPANY_NAME', 'Syed Professional Services'),
        'email' => (string) $get('COMPANY_EMAIL', 'admin@sps.local'),
        'phone' => (string) $get('COMPANY_PHONE', ''),
        'address' => (string) $get('COMPANY_ADDRESS', ''),
    ],
    'database' => [
        'host' => (string) $get('DB_HOST', '127.0.0.1'),
        'port' => (string) $get('DB_PORT', '3306'),
        'database' => (string) $get('DB_DATABASE', 'sps_professional_crm'),
        'username' => (string) $get('DB_USERNAME', 'root'),
        'password' => (string) $get('DB_PASSWORD', ''),
        'charset' => (string) $get('DB_CHARSET', 'utf8mb4'),
    ],
    'session' => [
        'name' => (string) $get('SESSION_NAME', 'sps_crm'),
        'secure' => filter_var($get('SESSION_SECURE', 'false'), FILTER_VALIDATE_BOOLEAN),
        'same_site' => (string) $get('SESSION_SAME_SITE', 'Lax'),
    ],
    'mail' => [
        'driver' => (string) $get('MAIL_DRIVER', 'log'),
        'from' => (string) $get('MAIL_FROM', 'noreply@sps.local'),
    ],
];

date_default_timezone_set($config['timezone']);
