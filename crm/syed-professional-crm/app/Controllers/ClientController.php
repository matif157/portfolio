<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Url;

class ClientController
{
    public function index(): void
    {
        Auth::requireLogin();

        $clients = [
            [
                'id' => 1001,
                'name' => 'Ahmed Khan',
                'phone' => '+92 300 1234567',
                'email' => 'ahmed@example.com',
                'service' => 'Immigration',
                'status' => 'Active',
            ],
            [
                'id' => 1002,
                'name' => 'Muhammad Ali',
                'phone' => '+92 301 7654321',
                'email' => 'ali@example.com',
                'service' => 'Tax Services',
                'status' => 'Pending',
            ],
            [
                'id' => 1003,
                'name' => 'Sara Ahmed',
                'phone' => '+92 302 5551234',
                'email' => 'sara@example.com',
                'service' => 'Immigration',
                'status' => 'Active',
            ],
        ];

        require __DIR__ . '/../../resources/views/clients/index.php';
    }

    public function create(): void
    {
        Auth::requireLogin();

        require __DIR__ . '/../../resources/views/clients/create.php';
    }

    public function store(): void
    {
        Auth::requireLogin();

        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            header('Location: ' . Url::to('clients/create?error=name'));
            exit;
        }

        header('Location: ' . Url::to('clients?created=1'));
        exit;
    }
}
