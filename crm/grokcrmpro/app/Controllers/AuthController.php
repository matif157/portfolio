<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Security;
use App\Core\View;

class AuthController
{
    public function loginForm(): void
    {
        View::render('auth/login', ['title' => 'Login']);
    }

    public function login(): void
    {
        Security::requireCsrf();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            flash('danger', 'Please enter username and password.');
            redirect('/login');
        }

        if (Auth::attempt($username, $password)) {
            redirect('/dashboard');
        }

        flash('danger', 'Invalid credentials.');
        redirect('/login');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }
}
