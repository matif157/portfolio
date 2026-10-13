<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;

class ServiceController
{
    public function index(): void
    {
        require_permission('service.view');
        $services = Database::pdo()->query(
            'SELECT s.*, c.name AS category_name FROM services s
             LEFT JOIN service_categories c ON c.id = s.category_id
             ORDER BY s.name'
        )->fetchAll();
        View::render('services/index', ['title' => 'Services', 'services' => $services]);
    }

    public function create(): void
    {
        require_permission('service.manage');
        $categories = Database::pdo()->query('SELECT * FROM service_categories ORDER BY sort_order')->fetchAll();
        View::render('services/create', ['title' => 'Add Service', 'categories' => $categories]);
    }

    public function store(): void
    {
        require_permission('service.manage');
        Security::requireCsrf();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('danger', 'Service name required.');
            redirect('/services/create');
        }
        Database::pdo()->prepare(
            'INSERT INTO services (service_code, category_id, name, description, default_price, billing_type, tax_rate, is_active)
             VALUES (?,?,?,?,?,?,?,1)'
        )->execute([
            trim($_POST['service_code'] ?? '') ?: null,
            (int)($_POST['category_id'] ?? 0) ?: null,
            $name,
            trim($_POST['description'] ?? '') ?: null,
            (float)($_POST['default_price'] ?? 0),
            $_POST['billing_type'] ?? 'one_time',
            (float)($_POST['tax_rate'] ?? 0),
        ]);
        Auth::audit('service.create', 'Service', "Created service $name");
        flash('success', 'Service created.');
        redirect('/services');
    }

    public function edit(string $id): void
    {
        require_permission('service.manage');
        $stmt = Database::pdo()->prepare('SELECT * FROM services WHERE id = ?');
        $stmt->execute([(int)$id]);
        $service = $stmt->fetch();
        if (!$service) {
            flash('danger', 'Not found.');
            redirect('/services');
        }
        $categories = Database::pdo()->query('SELECT * FROM service_categories ORDER BY sort_order')->fetchAll();
        View::render('services/edit', ['title' => 'Edit Service', 'service' => $service, 'categories' => $categories]);
    }

    public function update(string $id): void
    {
        require_permission('service.manage');
        Security::requireCsrf();
        Database::pdo()->prepare(
            'UPDATE services SET service_code=?, category_id=?, name=?, description=?, default_price=?, billing_type=?, tax_rate=?, is_active=? WHERE id=?'
        )->execute([
            trim($_POST['service_code'] ?? '') ?: null,
            (int)($_POST['category_id'] ?? 0) ?: null,
            trim($_POST['name'] ?? ''),
            trim($_POST['description'] ?? '') ?: null,
            (float)($_POST['default_price'] ?? 0),
            $_POST['billing_type'] ?? 'one_time',
            (float)($_POST['tax_rate'] ?? 0),
            isset($_POST['is_active']) ? 1 : 0,
            (int)$id,
        ]);
        Auth::audit('service.update', 'Service', 'Updated service', 'service', (int)$id);
        flash('success', 'Service updated.');
        redirect('/services');
    }
}
