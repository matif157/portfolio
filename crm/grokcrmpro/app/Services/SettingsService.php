<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class SettingsService
{
    private static ?array $cache = null;

    public static function get(string $key, $default = ''): string
    {
        self::load();
        return (string)(self::$cache[$key] ?? $default);
    }

    public static function company(): array
    {
        return [
            'name'    => self::get('company_name', 'Syed Professional Services'),
            'street'  => self::get('company_street'),
            'city'    => self::get('company_city'),
            'state'   => self::get('company_state'),
            'zip'     => self::get('company_zip'),
            'phone'   => self::get('company_phone'),
            'fax'     => self::get('company_fax'),
            'email'   => self::get('company_email'),
            'website' => self::get('company_website'),
        ];
    }

    public static function all(): array
    {
        self::load();
        return self::$cache ?? [];
    }

    public static function set(string $key, string $value): void
    {
        $pdo = Database::pdo();
        $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([$key, $value]);
        self::$cache = null;
    }

    private static function load(): void
    {
        if (self::$cache !== null) return;
        self::$cache = [];
        try {
            $rows = Database::pdo()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            foreach ($rows as $r) {
                self::$cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (\Throwable $e) {
            // table may not exist yet
        }
    }
}
