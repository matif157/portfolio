<?php
/**
 * SPS Professional Services CRM
 * Payment Receipts & Collections Journal
 */
require_once __DIR__ . '/auth.php';
require_permission('invoices.view');

$payments = $pdo->query("
    SELECT p.*, i.invoice_number, c.first_name, c.last_name, c.company_name
    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    JOIN clients c ON p.client_id = c.id
    ORDER BY p.payment_date DESC
")->fetchAll();

render_header('Payments Receipts', 'invoices');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Payment Receipts Journal</h1>
        <p class="page-subtitle">Track incoming bank transfers, checks, and client invoice receipts</p>
    </div>
    <div>
        <a href="invoices.php" class="btn btn-primary">← View Invoices</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Payment #</th>
                    <th>Invoice #</th>
                    <th>Client Owner</th>
                    <th>Amount Paid</th>
                    <th>Payment Method</th>
                    <th>Reference #</th>
                    <th>Payment Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No payment receipts recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><strong><?= e($p['payment_number']) ?></strong></td>
                            <td><a href="invoice_view.php?id=<?= $p['invoice_id'] ?>"><strong><?= e($p['invoice_number']) ?></strong></a></td>
                            <td><?= e($p['company_name'] ?: "{$p['first_name']} {$p['last_name']}") ?></td>
                            <td><strong style="color:var(--success);"><?= format_currency($p['amount']) ?></strong></td>
                            <td><span class="badge badge-secondary"><?= ucfirst(str_replace('_', ' ', $p['payment_method'])) ?></span></td>
                            <td><code><?= e($p['reference_no'] ?: '-') ?></code></td>
                            <td><?= format_date($p['payment_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
