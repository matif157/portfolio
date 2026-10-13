<div class="d-flex justify-content-between mb-3">
    <div></div>
    <?php if (has_permission('estimate.create')): ?>
    <a href="<?= url('/estimates/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Estimate</a>
    <?php endif; ?>
</div>
<div class="card"><div class="card-body p-0">
<table class="table table-hover table-sm mb-0">
<thead><tr><th>Number</th><th>Client</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php if (empty($estimates)): ?>
<tr><td colspan="6" class="text-center text-muted py-4">No estimates</td></tr>
<?php else: foreach ($estimates as $e): ?>
<tr>
    <td><a href="<?= url('/estimates/'.$e['id']) ?>"><?= e($e['estimate_number']) ?></a></td>
    <td><?= e($e['client_name'] ?? '-') ?></td>
    <td><?= e($e['issue_date']) ?></td>
    <td><?= money($e['grand_total']) ?></td>
    <td><span class="badge bg-secondary"><?= e($e['status']) ?></span></td>
    <td><a href="<?= url('/estimates/'.$e['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div></div>
