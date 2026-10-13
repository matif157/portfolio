<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\NumberService;

class EstimateController
{
    public function index(): void
    {
        require_permission('estimate.view');
        $rows = Database::pdo()->query(
            "SELECT e.*, c.full_name AS client_name FROM estimates e
             LEFT JOIN clients c ON c.id = e.client_id ORDER BY e.id DESC LIMIT 100"
        )->fetchAll();
        View::render('estimates/index', ['title' => 'Estimates', 'estimates' => $rows]);
    }

    public function create(): void
    {
        require_permission('estimate.create');
        $clients = Database::pdo()->query("SELECT id, full_name, company_name FROM clients WHERE status='active' ORDER BY full_name")->fetchAll();
        $services = Database::pdo()->query("SELECT * FROM services WHERE is_active=1 ORDER BY name")->fetchAll();
        View::render('estimates/create', [
            'title' => 'New Estimate',
            'clients' => $clients,
            'services' => $services,
            'preClient' => (int)($_GET['client_id'] ?? 0),
        ]);
    }

    public function store(): void
    {
        require_permission('estimate.create');
        Security::requireCsrf();
        $pdo = Database::pdo();
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId <= 0) {
            flash('danger', 'Select a client.');
            redirect('/estimates/create');
        }

        $descs = $_POST['description'] ?? [];
        $qtys = $_POST['quantity'] ?? [];
        $prices = $_POST['unit_price'] ?? [];
        $serviceIds = $_POST['service_id'] ?? [];

        $subtotal = 0;
        $items = [];
        for ($i = 0; $i < count($descs); $i++) {
            $d = trim($descs[$i] ?? '');
            if ($d === '') continue;
            $q = (float)($qtys[$i] ?? 1);
            $p = (float)($prices[$i] ?? 0);
            $line = round($q * $p, 2);
            $subtotal += $line;
            $items[] = [
                'service_id' => (int)($serviceIds[$i] ?? 0) ?: null,
                'description' => $d,
                'quantity' => $q,
                'unit_price' => $p,
                'line_total' => $line,
            ];
        }
        $discount = (float)($_POST['discount_total'] ?? 0);
        $tax = (float)($_POST['tax_total'] ?? 0);
        $grand = round($subtotal - $discount + $tax, 2);

        $pdo->beginTransaction();
        try {
            $num = NumberService::next('estimate');
            $pdo->prepare(
                'INSERT INTO estimates (estimate_number, client_id, issue_date, expiry_date, status, subtotal, discount_total, tax_total, grand_total, notes, terms, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $num, $clientId,
                $_POST['issue_date'] ?? date('Y-m-d'),
                $_POST['expiry_date'] ?: null,
                'draft',
                $subtotal, $discount, $tax, $grand,
                trim($_POST['notes'] ?? '') ?: null,
                trim($_POST['terms'] ?? '') ?: null,
                Auth::id(),
            ]);
            $estId = (int)$pdo->lastInsertId();
            $itemStmt = $pdo->prepare(
                'INSERT INTO estimate_items (estimate_id, service_id, description, quantity, unit_price, line_total) VALUES (?,?,?,?,?,?)'
            );
            foreach ($items as $it) {
                $itemStmt->execute([$estId, $it['service_id'], $it['description'], $it['quantity'], $it['unit_price'], $it['line_total']]);
            }
            $pdo->commit();
            Auth::audit('estimate.create', 'Estimate', "Created $num", 'estimate', $estId);
            flash('success', "Estimate $num created.");
            redirect('/estimates/' . $estId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('danger', 'Error: ' . $e->getMessage());
            redirect('/estimates/create');
        }
    }

    public function show(string $id): void
    {
        require_permission('estimate.view');
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT e.*, c.full_name AS client_name, c.company_name, c.email AS client_email
             FROM estimates e LEFT JOIN clients c ON c.id = e.client_id WHERE e.id = ?'
        );
        $stmt->execute([(int)$id]);
        $estimate = $stmt->fetch();
        if (!$estimate) {
            flash('danger', 'Not found.');
            redirect('/estimates');
        }
        $items = $pdo->prepare('SELECT * FROM estimate_items WHERE estimate_id = ? ORDER BY id');
        $items->execute([(int)$id]);
        $items = $items->fetchAll();

        View::render('estimates/show', [
            'title' => 'Estimate ' . $estimate['estimate_number'],
            'estimate' => $estimate,
            'items' => $items,
        ]);
    }

    public function accept(string $id): void
    {
        require_permission('estimate.accept');
        Security::requireCsrf();
        $pdo = Database::pdo();
        $pdo->prepare("UPDATE estimates SET status='accepted' WHERE id=? AND status IN ('draft','sent','viewed')")
            ->execute([(int)$id]);
        Auth::audit('estimate.accept', 'Estimate', 'Estimate accepted', 'estimate', (int)$id);
        flash('success', 'Estimate accepted.');
        redirect('/estimates/' . $id);
    }

    public function convertToInvoice(string $id): void
    {
        require_permission('invoice.create');
        Security::requireCsrf();
        $pdo = Database::pdo();

        $stmt = $pdo->prepare('SELECT * FROM estimates WHERE id = ?');
        $stmt->execute([(int)$id]);
        $est = $stmt->fetch();
        if (!$est || $est['status'] !== 'accepted') {
            flash('danger', 'Estimate must be accepted first.');
            redirect('/estimates/' . $id);
        }

        $items = $pdo->prepare('SELECT * FROM estimate_items WHERE estimate_id = ?');
        $items->execute([(int)$id]);
        $items = $items->fetchAll();

        $client = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
        $client->execute([(int)$est['client_id']]);
        $client = $client->fetch();

        $dueDays = (int)(\App\Services\SettingsService::get('invoice_due_days', '15'));
        $issueDate = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime("+{$dueDays} days"));

        $pdo->beginTransaction();
        try {
            $invNum = NumberService::next('invoice');
            $addr = trim(($client['address'] ?? '') . "\n" . ($client['city'] ?? '') . ', ' . ($client['state'] ?? '') . ' ' . ($client['zip'] ?? ''));

            $pdo->prepare(
                'INSERT INTO invoices (invoice_number, client_id, estimate_id, issue_date, due_date, status,
                 bill_to_name, bill_to_company, bill_to_address, bill_to_email, bill_to_phone,
                 subtotal, discount_total, tax_total, grand_total, amount_paid, credits_applied, balance_due,
                 notes, terms, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,?,?,?,?)'
            )->execute([
                $invNum, $est['client_id'], $est['id'], $issueDate, $dueDate, 'sent',
                $client['full_name'], $client['company_name'], $addr, $client['email'], $client['phone'],
                $est['subtotal'], $est['discount_total'], $est['tax_total'], $est['grand_total'],
                $est['grand_total'], $est['notes'], $est['terms'], Auth::id(),
            ]);
            $invId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, discount, tax_rate, line_total)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
            foreach ($items as $it) {
                $itemStmt->execute([
                    $invId, $it['service_id'], $it['description'], $it['quantity'],
                    $it['unit_price'], $it['discount'], $it['tax_rate'], $it['line_total'],
                ]);
            }
            $pdo->commit();
            Auth::audit('invoice.create', 'Invoice', "Converted estimate to $invNum", 'invoice', $invId);
            flash('success', "Invoice $invNum created from estimate.");
            redirect('/invoices/' . $invId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('danger', $e->getMessage());
            redirect('/estimates/' . $id);
        }
    }
}
