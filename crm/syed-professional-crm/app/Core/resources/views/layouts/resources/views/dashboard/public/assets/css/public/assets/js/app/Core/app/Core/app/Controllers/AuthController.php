<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Url;

class AuthController
{
    /**
     * Temporary login page.
     *
     * IMPORTANT:
     * Database authentication will be connected later.
     * This allows us to finish and visually test the CRM first.
     */
    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: ' . Url::to('dashboard'));
            exit;
        }

        require __DIR__ . '/../../resources/views/auth/login.php';
    }

    /**
     * Temporary development login.
     *
     * This is NOT the final authentication system.
     * Later:
     *
     * Login
     * ↓
     * users table
     * ↓
     * password_hash/password_verify
     * ↓
     * role/permission system
     */
    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            header('Location: ' . Url::to('login?error=missing'));
            exit;
        }

        /*
         * Temporary development credentials.
         *
         * These will be removed when the users table
         * and real authentication repository are connected.
         */
        if (
            strtolower($email) === 'admin@sps.local'
            && $password === 'admin123'
        ) {
            Auth::login(
                1,
                'Muhammad Atif',
                'Administrator'
            );

            header('Location: ' . Url::to('dashboard'));
            exit;
        }

        header('Location: ' . Url::to('login?error=invalid'));
        exit;
    }

    public function logout(): void
    {
        Auth::logout();

        header('Location: ' . Url::to('login'));
        exit;
    }
}