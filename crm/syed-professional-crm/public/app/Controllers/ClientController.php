<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Url;

class ClientController
{
    /**
     * Display client directory.
     *
     * Temporary sample data is used here.
     * Real SQL integration comes after the database
     * structure is finalized.
     */
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

        require __DIR__
            . '/../../resources/views/clients/index.php';
    }


    /**
     * Show create-client form.
     */
    public function create(): void
    {
        Auth::requireLogin();

        require __DIR__
            . '/../../resources/views/clients/create.php';
    }


    /**
     * Store new client.
     *
     * Temporary workflow only.
     *
     * Later this will:
     *
     * Request
     * ↓
     * Validation
     * ↓
     * ClientService
     * ↓
     * ClientRepository
     * ↓
     * Database
     * ↓
     * Audit Log
     */
    public function store(): void
    {
        Auth::requireLogin();

        $name = trim(
            $_POST['name'] ?? ''
        );

        if ($name === '') {

            header(
                'Location: '
                . Url::to('clients/create?error=name')
            );

            exit;
        }

        /*
         * Temporary successful workflow.
         *
         * We are deliberately NOT writing to SQL yet.
         */

        header(
            'Location: '
            . Url::to('clients?created=1')
        );

        exit;
    }
}