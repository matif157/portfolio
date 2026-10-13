<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat"><div class="label">Clients</div><div class="value"><?= (int)$stats['clients'] ?></div></div></div>
    <div class="col-md-3"><div class="stat"><div class="label">Total Invoiced</div><div class="value"><?= money($stats['invoiced']) ?></div></div></div>
    <div class="col-md-3"><div class="stat"><div class="label">Collected</div><div class="value text-success"><?= money($stats['collected']) ?></div></div></div>
    <div class="col-md-3"><div class="stat"><div class="label">Outstanding AR</div><div class="value text-danger"><?= money($stats['outstanding']) ?></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat"><div class="label">Overdue</div><div class="value text-danger"><?= money($stats['overdue']) ?></div></div></div>
    <div class="col-md-3"><div class="stat"><div class="label">Open Invoices</div><div class="value"><?= (int)$stats['open_inv'] ?></div></div></div>
    <div class="col-md-3"><div class="stat"><div class="label">Open Estimates</div><div class="value"><?= (int)$stats['estimates'] ?></div></div></div>
    <div class="col-md-3"><div class="stat"><div class="label">Active Services</div><div class="value"><?= (int)$stats['services'] ?></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Recent Invoices</span>
                <a href="<?= url('/invoices') ?>" class="small">View all</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Number</th><th>Client</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (empty($recentInvoices)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No invoices yet</td></tr>
                    <?php else: foreach ($recentInvoices as $inv): ?>
                        <tr>
                            <td><a href="<?= url('/invoices/'.$inv['id']) ?>"><?= e($inv['invoice_number']) ?></a></td>
                            <td><?= e($inv['client_name'] ?? '-') ?></td>
                            <td><?= money($inv['grand_total']) ?></td>
                            <td><span class="badge bg-secondary"><?= e($inv['status']) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Recent Payments</span>
                <a href="<?= url('/payments') ?>" class="small">View all</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Number</th><th>Client</th><th>Amount</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php if (empty($recentPayments)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No payments yet</td></tr>
                    <?php else: foreach ($recentPayments as $p): ?>
                        <tr>
                            <td><a href="<?= url('/payments/'.$p['id']) ?>"><?= e($p['payment_number']) ?></a></td>
                            <td><?= e($p['client_name'] ?? '-') ?></td>
                            <td class="text-success"><?= money($p['amount']) ?></td>
                            <td><?= e($p['payment_date']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
