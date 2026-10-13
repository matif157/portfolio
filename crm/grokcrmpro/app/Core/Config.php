<?php
declare(strict_types=1);

namespace App\Core;

class Config
{
    private static array $items = [];

    public static function load(string $rootPath): void
    {
        // Load .env
        $envFile = $rootPath . '/.env';
        if (!is_file($envFile)) {
            $envFile = $rootPath . '/.env.example';
        }
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                if (strpos($line, '=') === false) continue;
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val, " \t\"'");
                $_ENV[$key] = $val;
                putenv("$key=$val");
            }
        }

        // Detect base URL from script location
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $dir = str_replace('\\', '/', dirname($script));
        if ($dir === '/' || $dir === '\\') {
            $baseUrl = '';
        } else {
            $baseUrl = rtrim($dir, '/');
        }

        self::$items = [
            'app' => [
                'name'     => $_ENV['APP_NAME'] ?? 'SPS CRM',
                'env'      => $_ENV['APP_ENV'] ?? 'local',
                'debug'    => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'base_url' => $baseUrl,
                'url'      => $_ENV['APP_URL'] ?? ('http://localhost' . $baseUrl),
            ],
            'db' => [
                'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
                'port' => $_ENV['DB_PORT'] ?? '3306',
                'name' => $_ENV['DB_NAME'] ?? 'sps_crm',
                'user' => $_ENV['DB_USER'] ?? 'root',
                'pass' => $_ENV['DB_PASS'] ?? '',
            ],
            'session' => [
                'name' => $_ENV['SESSION_NAME'] ?? 'sps_crm_session',
            ],
        ];
    }

    public static function get(string $key, $default = null)
    {
        $parts = explode('.', $key);
        $val = self::$items;
        foreach ($parts as $p) {
            if (!is_array($val) || !array_key_exists($p, $val)) {
                return $default;
            }
            $val = $val[$p];
        }
        return $val;
    }
}
