<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

class PaymentService
{
    /**
     * Record payment and allocate to invoice(s).
     * $allocations = [ invoice_id => amount, ... ]
     */
    public static function record(int $clientId, float $amount, string $date, string $method, array $allocations, ?string $reference = null, ?string $note = null): int
    {
        $pdo = Database::pdo();
        Database::begin();
        try {
            $paymentNumber = NumberService::next('payment');

            $pdo->prepare(
                'INSERT INTO payments (payment_number, client_id, payment_date, amount, method, reference_number, note, status, received_by)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([
                $paymentNumber, $clientId, $date, $amount, $method, $reference, $note, 'completed', Auth::id()
            ]);
            $paymentId = (int)$pdo->lastInsertId();

            $allocated = 0.0;
            foreach ($allocations as $invoiceId => $allocAmount) {
                $allocAmount = round((float)$allocAmount, 2);
                if ($allocAmount <= 0) continue;
                $pdo->prepare('INSERT INTO payment_allocations (payment_id, invoice_id, amount) VALUES (?,?,?)')
                    ->execute([$paymentId, (int)$invoiceId, $allocAmount]);
                $allocated += $allocAmount;
                InvoiceBalanceService::recalculate((int)$invoiceId);
            }

            Auth::audit('payment.create', 'Payment', "Payment $paymentNumber of $amount recorded", 'payment', $paymentId);

            Database::commit();
            return $paymentId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }
}
