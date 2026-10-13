<?php
declare(strict_types=1);

namespace App\Services;

class ServiceCatalogLazyLoader
{
    private static ?array $catalog = null;
    private static string $catalogPath = '';

    public static function load(string $catalogPath): array
    {
        if (self::$catalog !== null && self::$catalogPath === $catalogPath) {
            return self::$catalog;
        }

        self::$catalogPath = $catalogPath;
        self::$catalog = [];

        if (!is_dir($catalogPath)) {
            return self::$catalog;
        }

        foreach (glob($catalogPath . '/*.php') as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            self::$catalog[$key] = require $file;
        }

        return self::$catalog;
    }

    public static function getService(string $key): ?array
    {
        $catalog = self::$catalog ?? [];
        return $catalog[$key] ?? null;
    }
}
