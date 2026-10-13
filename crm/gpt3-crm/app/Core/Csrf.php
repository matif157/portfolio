<?php
namespace App\Core;
final class Csrf {
    public static function token(): string { Session::start(); return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32)); }
    public static function check(?string $token): bool { return is_string($token) && hash_equals(self::token(), $token); }
    public static function input(): string { return '<input type="hidden" name="_csrf" value="'.htmlspecialchars(self::token(), ENT_QUOTES).'">'; }
}
