<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
    <h4 style="color:#fff" class="mb-0"><?= e($estimate['estimate_number']) ?>
        <span class="badge bg-secondary ms-2"><?= e($estimate['status']) ?></span>
    </h4>
    <div class="d-flex gap-2">
        <?php if (in_array($estimate['status'], ['draft','sent','viewed']) && has_permission('estimate.accept')): ?>
        <form method="POST" action="<?= url('/estimates/'.$estimate['id'].'/accept') ?>" class="d-inline"><?= csrf_field() ?>
            <button class="btn btn-success btn-sm">Accept</button>
        </form>
        <?php endif; ?>
        <?php if ($estimate['status']==='accepted' && has_permission('invoice.create')): ?>
        <form method="POST" action="<?= url('/estimates/'.$estimate['id'].'/convert-invoice') ?>" class="d-inline"><?= csrf_field() ?>
            <button class="btn btn-primary btn-sm">Convert to Invoice</button>
        </form>
        <?php endif; ?>
        <a href="<?= url('/estimates') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>
<div class="card mb-3"><div class="card-body">
    <div class="row">
        <div class="col-md-6"><strong>Client:</strong> <a href="<?= url('/clients/'.$estimate['client_id']) ?>"><?= e($estimate['client_name']) ?></a></div>
        <div class="col-md-3"><strong>Issue:</strong> <?= e($estimate['issue_date']) ?></div>
        <div class="col-md-3"><strong>Expiry:</strong> <?= e($estimate['expiry_date'] ?? '-') ?></div>
    </div>
</div></div>
<div class="card"><div class="card-body p-0">
<table class="table table-sm mb-0">
<thead><tr><th>Description</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
<tbody>
<?php foreach ($items as $it): ?>
<tr>
    <td><?= e($it['description']) ?></td>
    <td><?= e((string)$it['quantity']) ?></td>
    <td><?= money($it['unit_price']) ?></td>
    <td><?= money($it['line_total']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr><td colspan="3" class="text-end">Subtotal</td><td><?= money($estimate['subtotal']) ?></td></tr>
<?php if ($estimate['discount_total']>0): ?><tr><td colspan="3" class="text-end">Discount</td><td>-<?= money($estimate['discount_total']) ?></td></tr><?php endif; ?>
<?php if ($estimate['tax_total']>0): ?><tr><td colspan="3" class="text-end">Tax</td><td><?= money($estimate['tax_total']) ?></td></tr><?php endif; ?>
<tr><td colspan="3" class="text-end fw-bold">Grand Total</td><td class="fw-bold"><?= money($estimate['grand_total']) ?></td></tr>
</tfoot>
</table>
</div></div>
