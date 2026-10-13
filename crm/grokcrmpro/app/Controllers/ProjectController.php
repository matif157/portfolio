<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\NumberService;

class ProjectController
{
    public function index(): void
    {
        require_permission('project.view');
        $status = $_GET['status'] ?? '';
        $q = trim($_GET['q'] ?? '');
        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.project_number LIKE ? OR c.full_name LIKE ?)';
            $s = "%$q%";
            $params = array_merge($params, [$s, $s, $s]);
        }
        $sql = 'SELECT p.*, c.full_name AS client_name, u.full_name AS manager_name
                FROM projects p
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN users u ON u.id = p.manager_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY p.id DESC LIMIT 100';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        View::render('projects/index', [
            'title' => 'Projects',
            'projects' => $stmt->fetchAll(),
            'status' => $status,
            'q' => $q,
        ]);
    }

    public function create(): void
    {
        require_permission('project.create');
        $clients = Database::pdo()->query("SELECT id, full_name, company_name FROM clients WHERE status='active' ORDER BY full_name")->fetchAll();
        $users = Database::pdo()->query("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
        $services = Database::pdo()->query("SELECT id, name, default_price FROM services WHERE is_active=1 ORDER BY name")->fetchAll();
        View::render('projects/create', [
            'title' => 'New Project',
            'clients' => $clients,
            'users' => $users,
            'services' => $services,
            'preClient' => (int)($_GET['client_id'] ?? 0),
        ]);
    }

    public function store(): void
    {
        require_permission('project.create');
        Security::requireCsrf();
        $pdo = Database::pdo();
        $clientId = (int)($_POST['client_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($clientId <= 0 || $name === '') {
            flash('danger', 'Client and project name are required.');
            redirect('/projects/create');
        }

        $pdo->beginTransaction();
        try {
            $num = NumberService::next('project');
            $pdo->prepare(
                'INSERT INTO projects (project_number, client_id, name, description, start_date, end_date, status, priority, manager_id, billing_type, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $num, $clientId, $name,
                trim($_POST['description'] ?? '') ?: null,
                $_POST['start_date'] ?: null,
                $_POST['end_date'] ?: null,
                $_POST['status'] ?? 'open',
                $_POST['priority'] ?? 'normal',
                (int)($_POST['manager_id'] ?? 0) ?: Auth::id(),
                $_POST['billing_type'] ?? 'one_time',
                Auth::id(),
            ]);
            $projectId = (int)$pdo->lastInsertId();

            // Optional services
            $svcIds = $_POST['service_id'] ?? [];
            $itemStmt = $pdo->prepare('INSERT INTO project_services (project_id, service_id, description, quantity, unit_price) VALUES (?,?,?,?,?)');
            foreach ($svcIds as $sid) {
                $sid = (int)$sid;
                if ($sid <= 0) continue;
                $s = $pdo->prepare('SELECT name, default_price FROM services WHERE id = ?');
                $s->execute([$sid]);
                $svc = $s->fetch();
                if ($svc) {
                    $itemStmt->execute([$projectId, $sid, $svc['name'], 1, $svc['default_price']]);
                }
            }

            // Staff
            $staffIds = $_POST['staff_id'] ?? [];
            $staffStmt = $pdo->prepare('INSERT IGNORE INTO project_staff (project_id, user_id, role_label) VALUES (?,?,?)');
            foreach ($staffIds as $uid) {
                $uid = (int)$uid;
                if ($uid > 0) $staffStmt->execute([$projectId, $uid, 'Staff']);
            }
            // Manager as staff
            $mgr = (int)($_POST['manager_id'] ?? 0) ?: Auth::id();
            $staffStmt->execute([$projectId, $mgr, 'Manager']);

            $pdo->commit();
            Auth::audit('project.create', 'Project', "Created $num", 'project', $projectId);
            flash('success', "Project $num created.");
            redirect('/projects/' . $projectId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('danger', $e->getMessage());
            redirect('/projects/create');
        }
    }

    public function show(string $id): void
    {
        require_permission('project.view');
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT p.*, c.full_name AS client_name, c.company_name, u.full_name AS manager_name
             FROM projects p
             LEFT JOIN clients c ON c.id = p.client_id
             LEFT JOIN users u ON u.id = p.manager_id
             WHERE p.id = ?'
        );
        $stmt->execute([(int)$id]);
        $project = $stmt->fetch();
        if (!$project) {
            flash('danger', 'Not found.');
            redirect('/projects');
        }

        $services = $pdo->prepare(
            'SELECT ps.*, s.name AS service_name FROM project_services ps
             LEFT JOIN services s ON s.id = ps.service_id WHERE ps.project_id = ?'
        );
        $services->execute([(int)$id]);
        $services = $services->fetchAll();

        $staff = $pdo->prepare(
            'SELECT ps.*, u.full_name FROM project_staff ps
             JOIN users u ON u.id = ps.user_id WHERE ps.project_id = ?'
        );
        $staff->execute([(int)$id]);
        $staff = $staff->fetchAll();

        $invoices = [];
        try {
            $inv = $pdo->prepare('SELECT id, invoice_number, grand_total, balance_due, status FROM invoices WHERE project_id = ? ORDER BY id DESC');
            $inv->execute([(int)$id]);
            $invoices = $inv->fetchAll();
        } catch (\Throwable $e) {
            // column may not exist yet
        }

        View::render('projects/show', [
            'title' => $project['project_number'],
            'project' => $project,
            'services' => $services,
            'staff' => $staff,
            'invoices' => $invoices,
        ]);
    }

    public function edit(string $id): void
    {
        require_permission('project.update');
        $stmt = Database::pdo()->prepare('SELECT * FROM projects WHERE id = ?');
        $stmt->execute([(int)$id]);
        $project = $stmt->fetch();
        if (!$project) {
            flash('danger', 'Not found.');
            redirect('/projects');
        }
        $clients = Database::pdo()->query("SELECT id, full_name FROM clients WHERE status='active' ORDER BY full_name")->fetchAll();
        $users = Database::pdo()->query("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
        View::render('projects/edit', [
            'title' => 'Edit Project',
            'project' => $project,
            'clients' => $clients,
            'users' => $users,
        ]);
    }

    public function update(string $id): void
    {
        require_permission('project.update');
        Security::requireCsrf();
        Database::pdo()->prepare(
            'UPDATE projects SET name=?, description=?, start_date=?, end_date=?, status=?, priority=?, manager_id=?, billing_type=? WHERE id=?'
        )->execute([
            trim($_POST['name'] ?? ''),
            trim($_POST['description'] ?? '') ?: null,
            $_POST['start_date'] ?: null,
            $_POST['end_date'] ?: null,
            $_POST['status'] ?? 'open',
            $_POST['priority'] ?? 'normal',
            (int)($_POST['manager_id'] ?? 0) ?: null,
            $_POST['billing_type'] ?? 'one_time',
            (int)$id,
        ]);
        Auth::audit('project.update', 'Project', 'Updated project', 'project', (int)$id);
        flash('success', 'Project updated.');
        redirect('/projects/' . $id);
    }
}
