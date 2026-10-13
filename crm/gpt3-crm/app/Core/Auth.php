<?php
namespace App\Core;
use App\Repositories\UserRepository;
use App\Services\ActivityService;
final class Auth {
    public static function user(): ?array { $id=Session::get('user_id'); return $id ? (new UserRepository)->find((int)$id) : null; }
    public static function id(): ?int { $id=Session::get('user_id'); return $id ? (int)$id : null; }
    public static function check(): bool { return self::id() !== null; }
    public static function login(array $user): void { Session::start(); session_regenerate_id(true); Session::put('user_id',(int)$user['id']); Session::put('role_id',(int)$user['role_id']); Session::put('tenant_id',(int)($user['tenant_id'] ?? 1)); }
    public static function logout(): void { if (self::check()) (new ActivityService)->record('AUTH_LOGOUT','auth','user',self::id(),null,'stage_2'); Session::start(); $_SESSION=[]; session_destroy(); }
    public static function requireLogin(): void { if (!self::check()) Response::redirect(route('login')); }
}
