<?php

namespace App\Core;

class Auth
{
    public static function login(
        int|string $userId,
        string $name,
        string $role = 'Administrator'
    ): void {
        Session::start();

        Session::regenerate();

        Session::set('auth_user_id', $userId);
        Session::set('auth_user_name', $name);
        Session::set('auth_user_role', $role);
        Session::set('authenticated', true);
    }

    public static function check(): bool
    {
        return Session::get('authenticated', false) === true;
    }

    public static function id(): mixed
    {
        return Session::get('auth_user_id');
    }

    public static function name(): string
    {
        return (string) Session::get(
            'auth_user_name',
            'User'
        );
    }

    public static function role(): string
    {
        return (string) Session::get(
            'auth_user_role',
            'User'
        );
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: ' . Url::to('login'));
            exit;
        }
    }
}