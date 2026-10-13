<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;

class DashboardController
{
    public function index(): void
    {
        $pdo = Database::pdo();

        $stats = [
            'clients'     => (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE status='active'")->fetchColumn(),
            'invoiced'    => (float)$pdo->query("SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE status NOT IN ('cancelled','draft')")->fetchColumn(),
            'collected'   => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'")->fetchColumn(),
            'outstanding' => (float)$pdo->query("SELECT COALESCE(SUM(balance_due),0) FROM invoices WHERE status NOT IN ('cancelled','draft','paid')")->fetchColumn(),
            'overdue'     => (float)$pdo->query("SELECT COALESCE(SUM(balance_due),0) FROM invoices WHERE status='overdue'")->fetchColumn(),
            'open_inv'    => (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE status NOT IN ('cancelled','draft','paid')")->fetchColumn(),
            'estimates'   => (int)$pdo->query("SELECT COUNT(*) FROM estimates WHERE status IN ('draft','sent')")->fetchColumn(),
            'services'    => (int)$pdo->query("SELECT COUNT(*) FROM services WHERE is_active=1")->fetchColumn(),
        ];

        $recentInvoices = $pdo->query(
            "SELECT i.*, c.full_name AS client_name FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             ORDER BY i.id DESC LIMIT 8"
        )->fetchAll();

        $recentPayments = $pdo->query(
            "SELECT p.*, c.full_name AS client_name FROM payments p
             LEFT JOIN clients c ON c.id = p.client_id
             ORDER BY p.id DESC LIMIT 8"
        )->fetchAll();

        View::render('dashboard/index', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'recentInvoices' => $recentInvoices,
            'recentPayments' => $recentPayments,
        ]);
    }
}
