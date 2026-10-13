<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Url;

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: ' . Url::to('dashboard'));
            exit;
        }

        require __DIR__ . '/../../resources/views/auth/login.php';
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            header('Location: ' . Url::to('login?error=missing'));
            exit;
        }

        /*
         * TEMPORARY DEVELOPMENT LOGIN.
         *
         * This will later be replaced with secure
         * database-backed authentication.
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