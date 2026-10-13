<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Install / re-configure helper for XAMPP and other servers.
 */
class Installer
{
    public function __construct(private string $root)
    {
    }

    public function isInstalled(): bool
    {
        return is_file($this->root . '/storage/installed.lock');
    }

    public function requirements(): array
    {
        return [
            'php_version' => [
                'label' => 'PHP 8.1+',
                'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
                'detail' => PHP_VERSION,
            ],
            'pdo_mysql' => [
                'label' => 'PDO MySQL',
                'ok' => extension_loaded('pdo_mysql'),
                'detail' => extension_loaded('pdo_mysql') ? 'enabled' : 'missing',
            ],
            'mbstring' => [
                'label' => 'mbstring',
                'ok' => extension_loaded('mbstring'),
                'detail' => extension_loaded('mbstring') ? 'enabled' : 'missing',
            ],
            'storage_writable' => [
                'label' => 'storage/ writable',
                'ok' => is_dir($this->root . '/storage') && is_writable($this->root . '/storage'),
                'detail' => $this->root . '/storage',
            ],
        ];
    }

    public function testDatabase(array $db): array
    {
        try {
            $pdo = $this->connectServer($db);
            $pdo->exec(
                'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $db['database']) . '`
                 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
            $pdo->exec('USE `' . str_replace('`', '``', $db['database']) . '`');

            $conflict = $this->detectSchemaConflict($pdo);
            if ($conflict !== null) {
                return [
                    'ok' => false,
                    'message' => $conflict,
                ];
            }

            return ['ok' => true, 'message' => 'Database connection successful.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function detectSchemaConflict(PDO $pdo): ?string
    {
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if (!$stmt || !$stmt->fetch()) {
            return null;
        }

        $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
        $cols = array_map('strtolower', $cols);

        if (!in_array('password_hash', $cols, true) || !in_array('name', $cols, true)) {
            return 'Database already has a different CRM schema (legacy users table). Choose a new empty database name such as sps_professional_crm.';
        }

        $clients = $pdo->query("SHOW TABLES LIKE 'clients'");
        if ($clients && $clients->fetch()) {
            $clientCols = $pdo->query('SHOW COLUMNS FROM clients')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($clientCols as $col) {
                if (strtolower($col['Field']) === 'id' && !str_contains(strtolower($col['Type']), 'bigint')) {
                    return 'Existing clients.id is not BIGINT. Use a fresh empty database name (recommended: sps_professional_crm). Your old mera_crm / sps_crm data will not be touched.';
                }
            }
        }

        return null;
    }

    public function install(array $input): void
    {
        foreach ($this->requirements() as $req) {
            if (!$req['ok']) {
                throw new RuntimeException('Requirement failed: ' . $req['label']);
            }
        }

        $db = [
            'host' => trim((string) ($input['db_host'] ?? '127.0.0.1')),
            'port' => trim((string) ($input['db_port'] ?? '3306')),
            'database' => trim((string) ($input['db_database'] ?? 'sps_professional_crm')),
            'username' => trim((string) ($input['db_username'] ?? 'root')),
            'password' => (string) ($input['db_password'] ?? ''),
            'charset' => 'utf8mb4',
        ];

        $test = $this->testDatabase($db);
        if (!$test['ok']) {
            throw new RuntimeException('Database error: ' . $test['message']);
        }

        $pdo = $this->connectDatabase($db);
        $this->runSqlFile($pdo, $this->root . '/database/migrations/001_initial.sql');
        $this->seedAdmin(
            $pdo,
            trim((string) ($input['admin_name'] ?? 'Administrator')),
            trim((string) ($input['admin_email'] ?? 'admin@sps.local')),
            (string) ($input['admin_password'] ?? ''),
            !empty($input['_keep_password'])
        );
        $this->seedServices($pdo);

        $basePath = $this->normalizeBasePath((string) ($input['base_path'] ?? '/syed-professional-crm/public'));
        $appUrl = rtrim((string) ($input['app_url'] ?? ('http://localhost' . $basePath)), '/');

        $settings = [
            'APP_NAME' => trim((string) ($input['app_name'] ?? 'Syed Professional Services CRM')),
            'APP_DEBUG' => !empty($input['app_debug']) ? 'true' : 'false',
            'APP_URL' => $appUrl,
            'APP_BASE_PATH' => $basePath,
            'APP_TIMEZONE' => trim((string) ($input['timezone'] ?? 'America/New_York')),
            'COMPANY_NAME' => trim((string) ($input['company_name'] ?? 'Syed Professional Services')),
            'COMPANY_EMAIL' => trim((string) ($input['company_email'] ?? $input['admin_email'] ?? 'admin@sps.local')),
            'COMPANY_PHONE' => trim((string) ($input['company_phone'] ?? '')),
            'COMPANY_ADDRESS' => trim((string) ($input['company_address'] ?? '')),
            'DB_HOST' => $db['host'],
            'DB_PORT' => $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'],
            'DB_CHARSET' => 'utf8mb4',
            'SESSION_NAME' => 'sps_crm',
            'SESSION_SECURE' => !empty($input['session_secure']) ? 'true' : 'false',
            'SESSION_SAME_SITE' => 'Lax',
            'MAIL_DRIVER' => trim((string) ($input['mail_driver'] ?? 'log')),
            'MAIL_FROM' => trim((string) ($input['mail_from'] ?? 'noreply@sps.local')),
        ];

        $this->writeEnv($settings);
        $this->writeLocalConfig($settings);
        $this->writeHtaccess($basePath);
        $this->ensureStorage();

        file_put_contents(
            $this->root . '/storage/installed.lock',
            json_encode([
                'installed_at' => date('c'),
                'app_url' => $appUrl,
                'base_path' => $basePath,
            ], JSON_PRETTY_PRINT)
        );
    }

    public function saveSettings(array $input): void
    {
        $this->install($input);
    }

    public function currentSettings(): array
    {
        $localFile = $this->root . '/storage/config/local.php';
        $local = is_file($localFile) ? (require $localFile) : [];
        if (!is_array($local)) {
            $local = [];
        }

        return [
            'app_name' => $local['APP_NAME'] ?? 'Syed Professional Services CRM',
            'app_debug' => filter_var($local['APP_DEBUG'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'app_url' => $local['APP_URL'] ?? 'http://localhost/syed-professional-crm/public',
            'base_path' => $local['APP_BASE_PATH'] ?? '/syed-professional-crm/public',
            'timezone' => $local['APP_TIMEZONE'] ?? 'America/New_York',
            'company_name' => $local['COMPANY_NAME'] ?? 'Syed Professional Services',
            'company_email' => $local['COMPANY_EMAIL'] ?? 'admin@sps.local',
            'company_phone' => $local['COMPANY_PHONE'] ?? '',
            'company_address' => $local['COMPANY_ADDRESS'] ?? '',
            'db_host' => $local['DB_HOST'] ?? '127.0.0.1',
            'db_port' => $local['DB_PORT'] ?? '3306',
            'db_database' => $local['DB_DATABASE'] ?? 'sps_professional_crm',
            'db_username' => $local['DB_USERNAME'] ?? 'root',
            'db_password' => $local['DB_PASSWORD'] ?? '',
            'mail_driver' => $local['MAIL_DRIVER'] ?? 'log',
            'mail_from' => $local['MAIL_FROM'] ?? 'noreply@sps.local',
            'session_secure' => filter_var($local['SESSION_SECURE'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            'admin_email' => $local['COMPANY_EMAIL'] ?? 'admin@sps.local',
            'admin_name' => 'Administrator',
        ];
    }

    private function connectServer(array $db): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['charset'] ?? 'utf8mb4'
        );

        return new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function connectDatabase(array $db): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['database'],
            $db['charset'] ?? 'utf8mb4'
        );

        return new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function runSqlFile(PDO $pdo, string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException('Migration file missing: ' . $file);
        }

        $sql = (string) file_get_contents($file);
        $sql = preg_replace('/^\s*CREATE DATABASE.*?;/mi', '', $sql) ?? $sql;
        $sql = preg_replace('/^\s*USE\s+\w+\s*;/mi', '', $sql) ?? $sql;

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        $statements = preg_split('/;\s*(?=\n|$)/', $sql) ?: [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '' || str_starts_with($statement, '--')) {
                continue;
            }
            try {
                $pdo->exec($statement);
            } catch (\Throwable $e) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                throw new RuntimeException(
                    'SQL failed: ' . $e->getMessage() . "\nStatement: " . substr($statement, 0, 180),
                    0,
                    $e
                );
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function seedAdmin(
        PDO $pdo,
        string $name,
        string $email,
        string $password,
        bool $keepPassword = false
    ): void {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($keepPassword || $password === '') {
                $upd = $pdo->prepare(
                    'UPDATE users SET name = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?'
                );
                $upd->execute([$name, 'Admin', 'active', $existing['id']]);
                return;
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $upd = $pdo->prepare(
                'UPDATE users SET name = ?, password_hash = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?'
            );
            $upd->execute([$name, $hash, 'Admin', 'active', $existing['id']]);
            return;
        }

        if ($password === '') {
            $password = 'admin123';
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, status, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $ins->execute([$name, $email, $hash, 'Admin', 'active']);
    }

    private function seedServices(PDO $pdo): void
    {
        $services = [
            ['IMMIGRATION', 'Immigration Services', 'Immigration', 'Client immigration service workflows.'],
            ['TAX', 'Tax Services', 'Tax', 'Tax preparation and professional review workflows.'],
        ];

        foreach ($services as [$code, $name, $category, $description]) {
            $check = $pdo->prepare('SELECT id FROM services WHERE code = ? LIMIT 1');
            $check->execute([$code]);
            if ($check->fetch()) {
                continue;
            }
            $ins = $pdo->prepare(
                'INSERT INTO services (code, name, category, description, status, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $ins->execute([$code, $name, $category, $description, 'active']);
        }
    }

    private function writeEnv(array $settings): void
    {
        $lines = ["# Generated by SPS CRM installer", '# ' . date('c'), ''];
        foreach ($settings as $key => $value) {
            $value = str_replace(["\r", "\n"], '', (string) $value);
            if (preg_match('/[\s#\'"]/', $value)) {
                $value = '"' . str_replace('"', '\\"', $value) . '"';
            }
            $lines[] = $key . '=' . $value;
        }
        file_put_contents($this->root . '/.env', implode(PHP_EOL, $lines) . PHP_EOL);
    }

    private function writeLocalConfig(array $settings): void
    {
        $dir = $this->root . '/storage/config';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $export = var_export($settings, true);
        $php = "<?php\ndeclare(strict_types=1);\nreturn {$export};\n";
        file_put_contents($dir . '/local.php', $php);
    }

    private function writeHtaccess(string $basePath): void
    {
        $base = $basePath === '' ? '/' : rtrim($basePath, '/') . '/';
        $content = "RewriteEngine On\n"
            . "RewriteBase {$base}\n"
            . "RewriteCond %{REQUEST_FILENAME} !-f\n"
            . "RewriteCond %{REQUEST_FILENAME} !-d\n"
            . "RewriteRule ^ index.php [QSA,L]\n";
        file_put_contents($this->root . '/public/.htaccess', $content);
    }

    private function ensureStorage(): void
    {
        foreach (['storage', 'storage/logs', 'storage/uploads', 'storage/cache', 'storage/temp', 'storage/config', 'storage/generated'] as $dir) {
            $path = $this->root . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0775, true);
            }
        }
    }

    private function normalizeBasePath(string $path): string
    {
        $path = '/' . trim(str_replace('\\', '/', $path), '/');
        return $path === '/' ? '' : $path;
    }
}
