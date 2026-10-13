<div class="d-flex justify-content-between mb-3">
    <div></div>
    <?php if (has_permission('payment.create')): ?>
    <a href="<?= url('/payments/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Record Payment</a>
    <?php endif; ?>
</div>
<div class="card"><div class="card-body p-0">
<table class="table table-hover table-sm mb-0">
<thead><tr><th>Number</th><th>Client</th><th>Date</th><th>Amount</th><th>Method</th><th></th></tr></thead>
<tbody>
<?php if (empty($payments)): ?>
<tr><td colspan="6" class="text-center text-muted py-4">No payments</td></tr>
<?php else: foreach ($payments as $p): ?>
<tr>
    <td><a href="<?= url('/payments/'.$p['id']) ?>"><?= e($p['payment_number']) ?></a></td>
    <td><?= e($p['client_name'] ?? '-') ?></td>
    <td><?= e($p['payment_date']) ?></td>
    <td class="text-success"><?= money($p['amount']) ?></td>
    <td><?= e($p['method']) ?></td>
    <td><a href="<?= url('/payments/'.$p['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div></div>
