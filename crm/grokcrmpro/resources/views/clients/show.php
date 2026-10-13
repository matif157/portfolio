<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0" style="color:#fff"><?= e($client['full_name']) ?></h4>
        <small class="text-muted"><?= e($client['company_name'] ?? '') ?> · <?= e($client['client_number'] ?? '') ?></small>
    </div>
    <div class="d-flex gap-2">
        <?php if (has_permission('client.update')): ?>
        <a href="<?= url('/clients/'.$client['id'].'/edit') ?>" class="btn btn-outline-warning btn-sm">Edit</a>
        <?php endif; ?>
        <?php if (has_permission('estimate.create')): ?>
        <a href="<?= url('/estimates/create?client_id='.$client['id']) ?>" class="btn btn-outline-primary btn-sm">New Estimate</a>
        <?php endif; ?>
        <?php if (has_permission('invoice.create')): ?>
        <a href="<?= url('/invoices/create?client_id='.$client['id']) ?>" class="btn btn-primary btn-sm">New Invoice</a>
        <?php endif; ?>
        <?php if (has_permission('payment.create')): ?>
        <a href="<?= url('/payments/create?client_id='.$client['id']) ?>" class="btn btn-success btn-sm">Record Payment</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="stat"><div class="label">Outstanding</div><div class="value <?= $outstanding>0?'text-danger':'' ?>"><?= money($outstanding) ?></div></div></div>
    <div class="col-md-3"><div class="stat"><div class="label">Status</div><div class="value" style="font-size:1.1rem"><span class="badge bg-<?= $client['status']==='active'?'success':'secondary' ?>"><?= e($client['status']) ?></span></div></div></div>
    <div class="col-md-3"><div class="stat"><div class="label">Email</div><div class="value" style="font-size:1rem"><?= e($client['email'] ?? '-') ?></div></div></div>
    <div class="col-md-3"><div class="stat"><div class="label">Phone</div><div class="value" style="font-size:1rem"><?= e($client['phone'] ?? $client['cell_phone'] ?? '-') ?></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">Invoices</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Number</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No invoices</td></tr>
                    <?php else: foreach ($invoices as $inv): ?>
                        <tr>
                            <td><a href="<?= url('/invoices/'.$inv['id']) ?>"><?= e($inv['invoice_number']) ?></a></td>
                            <td><?= e($inv['issue_date']) ?></td>
                            <td><?= money($inv['grand_total']) ?></td>
                            <td><?= money($inv['amount_paid']) ?></td>
                            <td class="<?= $inv['balance_due']>0?'text-danger':'' ?>"><?= money($inv['balance_due']) ?></td>
                            <td><span class="badge bg-secondary"><?= e($inv['status']) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Estimates</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Number</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (empty($estimates)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No estimates</td></tr>
                    <?php else: foreach ($estimates as $est): ?>
                        <tr>
                            <td><a href="<?= url('/estimates/'.$est['id']) ?>"><?= e($est['estimate_number']) ?></a></td>
                            <td><?= e($est['issue_date']) ?></td>
                            <td><?= money($est['grand_total']) ?></td>
                            <td><span class="badge bg-secondary"><?= e($est['status']) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Contact</div>
            <div class="card-body small">
                <div><?= e($client['address'] ?? '') ?></div>
                <div><?= e(trim(($client['city']??'').', '.($client['state']??'').' '.($client['zip']??''), ', ')) ?></div>
                <?php if ($client['notes']): ?><hr><div><?= e($client['notes']) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Payments</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Date</th><th>Amount</th></tr></thead>
                    <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="2" class="text-center text-muted py-3">None</td></tr>
                    <?php else: foreach ($payments as $p): ?>
                        <tr>
                            <td><a href="<?= url('/payments/'.$p['id']) ?>"><?= e($p['payment_date']) ?></a></td>
                            <td class="text-success"><?= money($p['amount']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
