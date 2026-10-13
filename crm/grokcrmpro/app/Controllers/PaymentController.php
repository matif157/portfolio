<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Security;
use App\Core\View;
use App\Services\PaymentService;

class PaymentController
{
    public function index(): void
    {
        require_permission('payment.view');
        $rows = Database::pdo()->query(
            'SELECT p.*, c.full_name AS client_name FROM payments p
             LEFT JOIN clients c ON c.id = p.client_id ORDER BY p.id DESC LIMIT 100'
        )->fetchAll();
        View::render('payments/index', ['title' => 'Payments', 'payments' => $rows]);
    }

    public function create(): void
    {
        require_permission('payment.create');
        $clients = Database::pdo()->query("SELECT id, full_name, company_name FROM clients WHERE status='active' ORDER BY full_name")->fetchAll();
        $preClient = (int)($_GET['client_id'] ?? 0);
        $preInvoice = (int)($_GET['invoice_id'] ?? 0);

        $openInvoices = [];
        if ($preClient > 0) {
            $stmt = Database::pdo()->prepare(
                "SELECT id, invoice_number, grand_total, balance_due, status FROM invoices
                 WHERE client_id = ? AND status NOT IN ('cancelled','draft','paid') AND balance_due > 0
                 ORDER BY due_date"
            );
            $stmt->execute([$preClient]);
            $openInvoices = $stmt->fetchAll();
        }

        View::render('payments/create', [
            'title' => 'Record Payment',
            'clients' => $clients,
            'preClient' => $preClient,
            'preInvoice' => $preInvoice,
            'openInvoices' => $openInvoices,
        ]);
    }

    public function store(): void
    {
        require_permission('payment.create');
        Security::requireCsrf();

        $clientId = (int)($_POST['client_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $date = $_POST['payment_date'] ?? date('Y-m-d');
        $method = $_POST['method'] ?? 'cash';
        $reference = trim($_POST['reference_number'] ?? '') ?: null;
        $note = trim($_POST['note'] ?? '') ?: null;

        if ($clientId <= 0 || $amount <= 0) {
            flash('danger', 'Client and amount are required.');
            redirect('/payments/create');
        }

        // Allocations: alloc[invoice_id] = amount
        $alloc = $_POST['alloc'] ?? [];
        $allocations = [];
        foreach ($alloc as $invId => $amt) {
            $amt = (float)$amt;
            if ($amt > 0) {
                $allocations[(int)$invId] = $amt;
            }
        }

        // If no explicit allocation but single invoice provided
        if (empty($allocations) && !empty($_POST['invoice_id'])) {
            $allocations[(int)$_POST['invoice_id']] = $amount;
        }

        try {
            $paymentId = PaymentService::record($clientId, $amount, $date, $method, $allocations, $reference, $note);
            flash('success', 'Payment recorded.');
            redirect('/payments/' . $paymentId);
        } catch (\Throwable $e) {
            flash('danger', $e->getMessage());
            redirect('/payments/create?client_id=' . $clientId);
        }
    }

    public function show(string $id): void
    {
        require_permission('payment.view');
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT p.*, c.full_name AS client_name, u.full_name AS received_by_name
             FROM payments p
             LEFT JOIN clients c ON c.id = p.client_id
             LEFT JOIN users u ON u.id = p.received_by
             WHERE p.id = ?'
        );
        $stmt->execute([(int)$id]);
        $payment = $stmt->fetch();
        if (!$payment) {
            flash('danger', 'Not found.');
            redirect('/payments');
        }
        $allocations = $pdo->prepare(
            'SELECT pa.*, i.invoice_number FROM payment_allocations pa
             JOIN invoices i ON i.id = pa.invoice_id WHERE pa.payment_id = ?'
        );
        $allocations->execute([(int)$id]);
        $allocations = $allocations->fetchAll();

        View::render('payments/show', [
            'title' => 'Payment ' . $payment['payment_number'],
            'payment' => $payment,
            'allocations' => $allocations,
        ]);
    }

    public function receipt(string $id): void
    {
        require_permission('payment.view');
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT p.*, c.full_name AS client_name, c.company_name FROM payments p
             LEFT JOIN clients c ON c.id = p.client_id WHERE p.id = ?'
        );
        $stmt->execute([(int)$id]);
        $payment = $stmt->fetch();
        if (!$payment) die('Not found');
        $allocations = $pdo->prepare(
            'SELECT pa.*, i.invoice_number FROM payment_allocations pa
             JOIN invoices i ON i.id = pa.invoice_id WHERE pa.payment_id = ?'
        );
        $allocations->execute([(int)$id]);
        $allocations = $allocations->fetchAll();
        $company = \App\Services\SettingsService::company();
        extract(compact('payment', 'allocations', 'company'));
        require VIEW_PATH . '/payments/receipt.php';
    }
}
