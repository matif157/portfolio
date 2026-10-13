<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\InvoiceBalanceService;
use App\Services\NumberService;
use App\Services\SettingsService;

class InvoiceController
{
    public function index(): void
    {
        require_permission('invoice.view');
        $q = trim($_GET['q'] ?? '');
        $status = $_GET['status'] ?? '';
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(i.invoice_number LIKE ? OR c.full_name LIKE ? OR c.company_name LIKE ?)';
            $s = "%$q%";
            $params = array_merge($params, [$s, $s, $s]);
        }
        if ($status !== '') {
            $where[] = 'i.status = ?';
            $params[] = $status;
        }
        $sql = 'SELECT i.*, c.full_name AS client_name, c.company_name FROM invoices i
                LEFT JOIN clients c ON c.id = i.client_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id DESC LIMIT 100';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll();

        View::render('invoices/index', [
            'title' => 'Invoices',
            'invoices' => $invoices,
            'q' => $q,
            'status' => $status,
        ]);
    }

    public function create(): void
    {
        require_permission('invoice.create');
        $clients = Database::pdo()->query("SELECT id, full_name, company_name FROM clients WHERE status='active' ORDER BY full_name")->fetchAll();
        $services = Database::pdo()->query("SELECT * FROM services WHERE is_active=1 ORDER BY name")->fetchAll();
        View::render('invoices/create', [
            'title' => 'New Invoice',
            'clients' => $clients,
            'services' => $services,
            'preClient' => (int)($_GET['client_id'] ?? 0),
            'dueDays' => (int)SettingsService::get('invoice_due_days', '15'),
        ]);
    }

    public function store(): void
    {
        require_permission('invoice.create');
        Security::requireCsrf();
        $pdo = Database::pdo();
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId <= 0) {
            flash('danger', 'Select a client.');
            redirect('/invoices/create');
        }

        $client = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
        $client->execute([$clientId]);
        $client = $client->fetch();

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
            $items[] = compact('d', 'q', 'p', 'line') + ['service_id' => (int)($serviceIds[$i] ?? 0) ?: null];
        }
        $discount = (float)($_POST['discount_total'] ?? 0);
        $tax = (float)($_POST['tax_total'] ?? 0);
        $grand = round($subtotal - $discount + $tax, 2);

        $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
        $dueDate = $_POST['due_date'] ?: null;
        $addr = trim(($client['address'] ?? '') . "\n" . ($client['city'] ?? '') . ', ' . ($client['state'] ?? '') . ' ' . ($client['zip'] ?? ''));

        $pdo->beginTransaction();
        try {
            $num = NumberService::next('invoice');
            $pdo->prepare(
                'INSERT INTO invoices (invoice_number, client_id, issue_date, due_date, status,
                 bill_to_name, bill_to_company, bill_to_address, bill_to_email, bill_to_phone,
                 subtotal, discount_total, tax_total, grand_total, amount_paid, credits_applied, balance_due,
                 notes, terms, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,?,?,?,?)'
            )->execute([
                $num, $clientId, $issueDate, $dueDate, 'sent',
                $client['full_name'], $client['company_name'], $addr, $client['email'], $client['phone'],
                $subtotal, $discount, $tax, $grand, $grand,
                trim($_POST['notes'] ?? '') ?: null,
                trim($_POST['terms'] ?? '') ?: SettingsService::get('invoice_terms'),
                Auth::id(),
            ]);
            $invId = (int)$pdo->lastInsertId();
            $itemStmt = $pdo->prepare(
                'INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, line_total) VALUES (?,?,?,?,?,?)'
            );
            foreach ($items as $it) {
                $itemStmt->execute([$invId, $it['service_id'], $it['d'], $it['q'], $it['p'], $it['line']]);
            }
            $pdo->commit();
            Auth::audit('invoice.create', 'Invoice', "Created $num", 'invoice', $invId);
            flash('success', "Invoice $num created.");
            redirect('/invoices/' . $invId);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('danger', $e->getMessage());
            redirect('/invoices/create');
        }
    }

    public function show(string $id): void
    {
        require_permission('invoice.view');
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT i.*, c.full_name AS client_name FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id WHERE i.id = ?'
        );
        $stmt->execute([(int)$id]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            flash('danger', 'Not found.');
            redirect('/invoices');
        }
        // Refresh status based on due date
        InvoiceBalanceService::recalculate((int)$id);
        $stmt->execute([(int)$id]);
        $invoice = $stmt->fetch();

        $items = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id');
        $items->execute([(int)$id]);
        $items = $items->fetchAll();

        $allocations = $pdo->prepare(
            'SELECT pa.*, p.payment_number, p.payment_date, p.method
             FROM payment_allocations pa
             JOIN payments p ON p.id = pa.payment_id
             WHERE pa.invoice_id = ? ORDER BY p.payment_date'
        );
        $allocations->execute([(int)$id]);
        $allocations = $allocations->fetchAll();

        $company = SettingsService::company();

        View::render('invoices/show', [
            'title' => 'Invoice ' . $invoice['invoice_number'],
            'invoice' => $invoice,
            'items' => $items,
            'allocations' => $allocations,
            'company' => $company,
        ]);
    }

    public function print(string $id): void
    {
        require_permission('invoice.view');
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
        $stmt->execute([(int)$id]);
        $invoice = $stmt->fetch();
        if (!$invoice) die('Not found');
        $items = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = ?');
        $items->execute([(int)$id]);
        $items = $items->fetchAll();
        $company = SettingsService::company();
        $user = Auth::user();

        // Standalone print view (no layout)
        extract([
            'invoice' => $invoice,
            'items' => $items,
            'company' => $company,
            'user' => $user,
        ]);
        require VIEW_PATH . '/invoices/print.php';
    }

    public function send(string $id): void
    {
        require_permission('invoice.send');
        Security::requireCsrf();
        $pdo = Database::pdo();
        $pdo->prepare("UPDATE invoices SET status='sent' WHERE id=? AND status='draft'")->execute([(int)$id]);
        InvoiceBalanceService::recalculate((int)$id);
        Auth::audit('invoice.send', 'Invoice', 'Invoice marked sent', 'invoice', (int)$id);
        flash('success', 'Invoice marked as sent.');
        redirect('/invoices/' . $id);
    }
}
