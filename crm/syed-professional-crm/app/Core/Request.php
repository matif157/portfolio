<?php
declare(strict_types=1);

namespace App\Core;

class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = Url::base();

        if ($base !== '' && str_starts_with($p, $base)) {
            $p = substr($p, strlen($base));
        }

        $p = preg_replace('#(?:^|/)index\.php#', '', $p) ?: '/';
        $p = '/' . trim((string) $p, '/');

        return $p === '/' ? '/' : rtrim($p, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }
}
