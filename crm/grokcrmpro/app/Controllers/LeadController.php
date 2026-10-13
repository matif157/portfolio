<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\NumberService;

class LeadController
{
    public function index(): void
    {
        require_permission('lead.view');
        $q = trim($_GET['q'] ?? '');
        $status = $_GET['status'] ?? '';
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(lead_name LIKE ? OR company_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $s = "%$q%";
            $params = array_merge($params, [$s, $s, $s, $s]);
        }
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $sql = 'SELECT l.*, u.full_name AS assigned_name FROM leads l
                LEFT JOIN users u ON u.id = l.assigned_user_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY l.id DESC LIMIT 150';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        View::render('leads/index', [
            'title' => 'Leads',
            'leads' => $stmt->fetchAll(),
            'q' => $q,
            'status' => $status,
        ]);
    }

    public function create(): void
    {
        require_permission('lead.create');
        $users = Database::pdo()->query("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
        $services = Database::pdo()->query("SELECT id, name FROM services WHERE is_active=1 ORDER BY name")->fetchAll();
        View::render('leads/create', ['title' => 'New Lead', 'users' => $users, 'services' => $services]);
    }

    public function store(): void
    {
        require_permission('lead.create');
        Security::requireCsrf();
        $name = trim($_POST['lead_name'] ?? '');
        if ($name === '') {
            flash('danger', 'Lead name is required.');
            redirect('/leads/create');
        }
        Database::pdo()->prepare(
            'INSERT INTO leads (lead_name, company_name, email, phone, source, service_interest, status, priority, assigned_user_id, estimated_value, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $name,
            trim($_POST['company_name'] ?? '') ?: null,
            trim($_POST['email'] ?? '') ?: null,
            trim($_POST['phone'] ?? '') ?: null,
            trim($_POST['source'] ?? '') ?: null,
            trim($_POST['service_interest'] ?? '') ?: null,
            $_POST['status'] ?? 'new',
            $_POST['priority'] ?? 'normal',
            (int)($_POST['assigned_user_id'] ?? 0) ?: Auth::id(),
            (float)($_POST['estimated_value'] ?? 0),
            trim($_POST['notes'] ?? '') ?: null,
        ]);
        $id = (int)Database::pdo()->lastInsertId();
        Auth::audit('lead.create', 'Lead', "Created lead $name", 'lead', $id);
        flash('success', 'Lead created.');
        redirect('/leads/' . $id);
    }

    public function show(string $id): void
    {
        require_permission('lead.view');
        $stmt = Database::pdo()->prepare(
            'SELECT l.*, u.full_name AS assigned_name, c.full_name AS client_name
             FROM leads l
             LEFT JOIN users u ON u.id = l.assigned_user_id
             LEFT JOIN clients c ON c.id = l.converted_client_id
             WHERE l.id = ?'
        );
        $stmt->execute([(int)$id]);
        $lead = $stmt->fetch();
        if (!$lead) {
            flash('danger', 'Lead not found.');
            redirect('/leads');
        }
        View::render('leads/show', ['title' => $lead['lead_name'], 'lead' => $lead]);
    }

    public function edit(string $id): void
    {
        require_permission('lead.update');
        $stmt = Database::pdo()->prepare('SELECT * FROM leads WHERE id = ?');
        $stmt->execute([(int)$id]);
        $lead = $stmt->fetch();
        if (!$lead) {
            flash('danger', 'Not found.');
            redirect('/leads');
        }
        $users = Database::pdo()->query("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
        View::render('leads/edit', ['title' => 'Edit Lead', 'lead' => $lead, 'users' => $users]);
    }

    public function update(string $id): void
    {
        require_permission('lead.update');
        Security::requireCsrf();
        Database::pdo()->prepare(
            'UPDATE leads SET lead_name=?, company_name=?, email=?, phone=?, source=?, service_interest=?, status=?, priority=?, assigned_user_id=?, estimated_value=?, notes=? WHERE id=?'
        )->execute([
            trim($_POST['lead_name'] ?? ''),
            trim($_POST['company_name'] ?? '') ?: null,
            trim($_POST['email'] ?? '') ?: null,
            trim($_POST['phone'] ?? '') ?: null,
            trim($_POST['source'] ?? '') ?: null,
            trim($_POST['service_interest'] ?? '') ?: null,
            $_POST['status'] ?? 'new',
            $_POST['priority'] ?? 'normal',
            (int)($_POST['assigned_user_id'] ?? 0) ?: null,
            (float)($_POST['estimated_value'] ?? 0),
            trim($_POST['notes'] ?? '') ?: null,
            (int)$id,
        ]);
        Auth::audit('lead.update', 'Lead', 'Updated lead', 'lead', (int)$id);
        flash('success', 'Lead updated.');
        redirect('/leads/' . $id);
    }

    /** Convert lead → client (preserves history) */
    public function convert(string $id): void
    {
        require_permission('lead.convert');
        Security::requireCsrf();
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM leads WHERE id = ?');
        $stmt->execute([(int)$id]);
        $lead = $stmt->fetch();
        if (!$lead) {
            flash('danger', 'Lead not found.');
            redirect('/leads');
        }
        if ($lead['status'] === 'converted' && $lead['converted_client_id']) {
            flash('info', 'Already converted.');
            redirect('/clients/' . $lead['converted_client_id']);
        }

        $pdo->beginTransaction();
        try {
            $clientNumber = NumberService::next('client');
            $pdo->prepare(
                'INSERT INTO clients (client_number, full_name, company_name, email, phone, status, source, notes, assigned_user_id)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([
                $clientNumber,
                $lead['lead_name'],
                $lead['company_name'],
                $lead['email'],
                $lead['phone'],
                'active',
                $lead['source'] ?: 'Lead conversion',
                $lead['notes'],
                $lead['assigned_user_id'] ?: Auth::id(),
            ]);
            $clientId = (int)$pdo->lastInsertId();

            $pdo->prepare(
                "UPDATE leads SET status='converted', converted_client_id=?, converted_at=NOW() WHERE id=?"
            )->execute([$clientId, (int)$id]);

            $pdo->commit();
            Auth::audit('lead.convert', 'Lead', "Converted lead to client #$clientId", 'lead', (int)$id);
            flash('success', 'Lead converted to client.');
            redirect('/clients/' . $clientId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('danger', $e->getMessage());
            redirect('/leads/' . $id);
        }
    }
}
