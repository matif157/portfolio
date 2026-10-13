<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money($amount, string $symbol = '$'): string
{
    return $symbol . number_format((float)$amount, 2);
}

function redirect(string $path): void
{
    $base = rtrim(\App\Core\Config::get('app.base_url', ''), '/');
    if ($path !== '' && $path[0] !== '/') {
        $path = '/' . $path;
    }
    header('Location: ' . $base . $path);
    exit;
}

function url(string $path = ''): string
{
    $base = rtrim(\App\Core\Config::get('app.base_url', ''), '/');
    if ($path !== '' && $path[0] !== '/') {
        $path = '/' . $path;
    }
    return $base . $path;
}

function asset(string $path): string
{
    return url('/public/assets/' . ltrim($path, '/'));
}

function old(string $key, $default = ''): string
{
    return e((string)(\App\Core\Session::getFlash('_old')[$key] ?? $default));
}

function csrf_token(): string
{
    return \App\Core\Security::csrfToken();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function flash(string $type, string $message): void
{
    \App\Core\Session::flash('msg_type', $type);
    \App\Core\Session::flash('msg', $message);
}

function has_permission(string $permission): bool
{
    return \App\Core\Auth::can($permission);
}

function require_permission(string $permission): void
{
    if (!has_permission($permission)) {
        http_response_code(403);
        view('errors/403', ['title' => 'Forbidden']);
        exit;
    }
}

function view(string $name, array $data = []): void
{
    \App\Core\View::render($name, $data);
}

function auth_user(): ?array
{
    return \App\Core\Auth::user();
}

function dd(...$vars): void
{
    echo '<pre>';
    foreach ($vars as $v) {
        var_dump($v);
    }
    echo '</pre>';
    exit;
}
