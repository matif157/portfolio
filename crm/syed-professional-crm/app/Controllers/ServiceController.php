<?php

namespace App\Controllers;

use App\Core\Auth;

class ServiceController
{
    public function index(): void
    {
        Auth::requireLogin();

        $services = [
            [
                'id' => 1,
                'name' => 'Immigration Services',
                'category' => 'Immigration',
                'description' => 'Visa, immigration and related case services.',
                'status' => 'Active',
            ],
            [
                'id' => 2,
                'name' => 'Tax Services',
                'category' => 'Tax',
                'description' => 'Tax preparation, filing and advisory services.',
                'status' => 'Active',
            ],
            [
                'id' => 3,
                'name' => 'Document Services',
                'category' => 'Documentation',
                'description' => 'Document preparation and related professional services.',
                'status' => 'Active',
            ],
            [
                'id' => 4,
                'name' => 'Consultation',
                'category' => 'Consultation',
                'description' => 'Professional consultation and case assessment.',
                'status' => 'Active',
            ],
        ];

        require __DIR__ . '/../../resources/views/services/index.php';
    }
}
