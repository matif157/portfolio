<?php

namespace App\Core;

class Url
{
    private static string $basePath = '';

    public static function setBasePath(string $basePath): void
    {
        self::$basePath = '/' . trim($basePath, '/');

        if (self::$basePath === '/') {
            self::$basePath = '';
        }
    }

    public static function base(): string
    {
        return self::$basePath;
    }

    public static function to(string $path = ''): string
    {
        $path = trim($path, '/');

        if ($path === '') {
            return self::$basePath . '/';
        }

        return self::$basePath . '/' . $path;
    }

    public static function asset(string $path): string
    {
        return self::to($path);
    }
}