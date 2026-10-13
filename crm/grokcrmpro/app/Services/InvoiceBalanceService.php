<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Single source of truth for invoice balance & status.
 */
class InvoiceBalanceService
{
    public static function recalculate(int $invoiceId): void
    {
        $pdo = Database::pdo();

        $inv = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
        $inv->execute([$invoiceId]);
        $invoice = $inv->fetch();
        if (!$invoice || $invoice['status'] === 'cancelled') {
            return;
        }

        // Sum allocations
        $paid = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payment_allocations WHERE invoice_id = ?');
        $paid->execute([$invoiceId]);
        $amountPaid = (float)$paid->fetchColumn();

        $grand = (float)$invoice['grand_total'];
        $credits = (float)$invoice['credits_applied'];
        $balance = round($grand - $amountPaid - $credits, 2);
        if ($balance < 0) $balance = 0;

        $status = self::deriveStatus($balance, $amountPaid, $invoice['due_date'], $invoice['status']);

        $pdo->prepare('UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$amountPaid, $balance, $status, $invoiceId]);
    }

    public static function deriveStatus(float $balance, float $amountPaid, ?string $dueDate, string $current): string
    {
        if ($current === 'cancelled' || $current === 'draft') {
            return $current;
        }
        if ($balance <= 0) {
            return 'paid';
        }
        if ($amountPaid > 0) {
            // Still overdue check
            if ($dueDate && $dueDate < date('Y-m-d')) {
                return 'overdue';
            }
            return 'partially_paid';
        }
        if ($dueDate && $dueDate < date('Y-m-d')) {
            return 'overdue';
        }
        return 'sent';
    }

    public static function clientOutstanding(int $clientId): float
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(balance_due),0) FROM invoices
             WHERE client_id = ? AND status NOT IN ('cancelled','draft')"
        );
        $stmt->execute([$clientId]);
        return (float)$stmt->fetchColumn();
    }
}
