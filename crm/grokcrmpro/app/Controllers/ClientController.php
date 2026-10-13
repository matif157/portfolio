<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\InvoiceBalanceService;
use App\Services\NumberService;

class ClientController
{
    public function index(): void
    {
        require_permission('client.view');
        $pdo = Database::pdo();
        $q = trim($_GET['q'] ?? '');
        $status = $_GET['status'] ?? '';

        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(full_name LIKE ? OR company_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $s = "%$q%";
            $params = array_merge($params, [$s, $s, $s, $s]);
        }
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $sql = 'SELECT * FROM clients WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clients = $stmt->fetchAll();

        // Attach outstanding
        foreach ($clients as &$c) {
            $c['outstanding'] = InvoiceBalanceService::clientOutstanding((int)$c['id']);
        }

        View::render('clients/index', [
            'title' => 'Clients',
            'clients' => $clients,
            'q' => $q,
            'status' => $status,
        ]);
    }

    public function create(): void
    {
        require_permission('client.create');
        View::render('clients/create', ['title' => 'Add Client']);
    }

    public function store(): void
    {
        require_permission('client.create');
        Security::requireCsrf();
        $pdo = Database::pdo();

        $fullName = trim($_POST['full_name'] ?? '');
        if ($fullName === '') {
            flash('danger', 'Client name is required.');
            redirect('/clients/create');
        }

        $clientNumber = NumberService::next('client');
        $pdo->prepare(
            'INSERT INTO clients (client_number, full_name, company_name, email, phone, cell_phone, address, city, state, zip, status, notes, assigned_user_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $clientNumber,
            $fullName,
            trim($_POST['company_name'] ?? '') ?: null,
            trim($_POST['email'] ?? '') ?: null,
            trim($_POST['phone'] ?? '') ?: null,
            trim($_POST['cell_phone'] ?? '') ?: null,
            trim($_POST['address'] ?? '') ?: null,
            trim($_POST['city'] ?? '') ?: null,
            trim($_POST['state'] ?? '') ?: null,
            trim($_POST['zip'] ?? '') ?: null,
            $_POST['status'] ?? 'active',
            trim($_POST['notes'] ?? '') ?: null,
            Auth::id(),
        ]);
        $id = (int)$pdo->lastInsertId();
        Auth::audit('client.create', 'Client', "Created client $fullName", 'client', $id);
        flash('success', 'Client created.');
        redirect('/clients/' . $id);
    }

    public function show(string $id): void
    {
        require_permission('client.view');
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute([(int)$id]);
        $client = $stmt->fetch();
        if (!$client) {
            flash('danger', 'Client not found.');
            redirect('/clients');
        }

        $invoices = $pdo->prepare('SELECT * FROM invoices WHERE client_id = ? ORDER BY id DESC LIMIT 20');
        $invoices->execute([(int)$id]);
        $invoices = $invoices->fetchAll();

        $payments = $pdo->prepare('SELECT * FROM payments WHERE client_id = ? ORDER BY id DESC LIMIT 15');
        $payments->execute([(int)$id]);
        $payments = $payments->fetchAll();

        $estimates = $pdo->prepare('SELECT * FROM estimates WHERE client_id = ? ORDER BY id DESC LIMIT 10');
        $estimates->execute([(int)$id]);
        $estimates = $estimates->fetchAll();

        $outstanding = InvoiceBalanceService::clientOutstanding((int)$id);

        View::render('clients/show', [
            'title' => $client['full_name'],
            'client' => $client,
            'invoices' => $invoices,
            'payments' => $payments,
            'estimates' => $estimates,
            'outstanding' => $outstanding,
        ]);
    }

    public function edit(string $id): void
    {
        require_permission('client.update');
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute([(int)$id]);
        $client = $stmt->fetch();
        if (!$client) {
            flash('danger', 'Client not found.');
            redirect('/clients');
        }
        View::render('clients/edit', ['title' => 'Edit Client', 'client' => $client]);
    }

    public function update(string $id): void
    {
        require_permission('client.update');
        Security::requireCsrf();
        $pdo = Database::pdo();
        $id = (int)$id;

        $pdo->prepare(
            'UPDATE clients SET full_name=?, company_name=?, email=?, phone=?, cell_phone=?, address=?, city=?, state=?, zip=?, status=?, notes=? WHERE id=?'
        )->execute([
            trim($_POST['full_name'] ?? ''),
            trim($_POST['company_name'] ?? '') ?: null,
            trim($_POST['email'] ?? '') ?: null,
            trim($_POST['phone'] ?? '') ?: null,
            trim($_POST['cell_phone'] ?? '') ?: null,
            trim($_POST['address'] ?? '') ?: null,
            trim($_POST['city'] ?? '') ?: null,
            trim($_POST['state'] ?? '') ?: null,
            trim($_POST['zip'] ?? '') ?: null,
            $_POST['status'] ?? 'active',
            trim($_POST['notes'] ?? '') ?: null,
            $id,
        ]);
        Auth::audit('client.update', 'Client', 'Updated client', 'client', $id);
        flash('success', 'Client updated.');
        redirect('/clients/' . $id);
    }
}
