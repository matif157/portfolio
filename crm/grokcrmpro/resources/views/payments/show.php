<div class="d-flex justify-content-between mb-3">
    <h4 style="color:#fff" class="mb-0"><?= e($payment['payment_number']) ?></h4>
    <div>
        <a href="<?= url('/payments/'.$payment['id'].'/receipt') ?>" class="btn btn-outline-primary btn-sm" target="_blank">Receipt</a>
        <a href="<?= url('/payments') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card"><div class="card-header">Payment Details</div><div class="card-body">
            <div class="mb-2"><span class="text-muted">Client:</span> <a href="<?= url('/clients/'.$payment['client_id']) ?>"><?= e($payment['client_name']) ?></a></div>
            <div class="mb-2"><span class="text-muted">Date:</span> <?= e($payment['payment_date']) ?></div>
            <div class="mb-2"><span class="text-muted">Amount:</span> <strong class="text-success"><?= money($payment['amount']) ?></strong></div>
            <div class="mb-2"><span class="text-muted">Method:</span> <?= e($payment['method']) ?></div>
            <div class="mb-2"><span class="text-muted">Reference:</span> <?= e($payment['reference_number'] ?? '-') ?></div>
            <div class="mb-2"><span class="text-muted">Received by:</span> <?= e($payment['received_by_name'] ?? '-') ?></div>
            <?php if ($payment['note']): ?><div><span class="text-muted">Note:</span> <?= e($payment['note']) ?></div><?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-header">Allocated To</div><div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Invoice</th><th>Amount</th></tr></thead>
                <tbody>
                <?php if (empty($allocations)): ?>
                <tr><td colspan="2" class="text-center text-muted py-3">No allocations</td></tr>
                <?php else: foreach ($allocations as $a): ?>
                <tr>
                    <td><a href="<?= url('/invoices/'.$a['invoice_id']) ?>"><?= e($a['invoice_number']) ?></a></td>
                    <td><?= money($a['amount']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>
