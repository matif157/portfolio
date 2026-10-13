<?php
namespace App\Core;
final class Session {
    public static function start(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('sps_crm');
            session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax', 'secure'=>!empty($_SERVER['HTTPS'])]);
            session_start();
        }
    }
    public static function get(string $key, mixed $default=null): mixed { self::start(); return $_SESSION[$key] ?? $default; }
    public static function put(string $key, mixed $value): void { self::start(); $_SESSION[$key] = $value; }
    public static function forget(string $key): void { self::start(); unset($_SESSION[$key]); }
    public static function flash(string $key, string $value): void { self::put('_flash_'.$key, $value); }
    public static function pullFlash(string $key): ?string { $v=self::get('_flash_'.$key); self::forget('_flash_'.$key); return $v; }
}
