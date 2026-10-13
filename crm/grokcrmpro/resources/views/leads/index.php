<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2" method="GET">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search leads..." value="<?= e($q) ?>" style="min-width:200px">
        <select name="status" class="form-select form-select-sm" style="width:140px">
            <option value="">All Status</option>
            <?php foreach (['new','contacted','qualified','estimate','accepted','converted','lost'] as $st): ?>
            <option value="<?= $st ?>" <?= $status===$st?'selected':'' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary">Filter</button>
    </form>
    <?php if (has_permission('lead.create')): ?>
    <a href="<?= url('/leads/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Lead</a>
    <?php endif; ?>
</div>
<div class="card"><div class="card-body p-0">
<table class="table table-hover table-sm mb-0">
<thead><tr><th>Name</th><th>Company</th><th>Source</th><th>Interest</th><th>Value</th><th>Status</th><th>Assigned</th><th></th></tr></thead>
<tbody>
<?php if (empty($leads)): ?>
<tr><td colspan="8" class="text-center text-muted py-4">No leads yet</td></tr>
<?php else: foreach ($leads as $l): ?>
<tr>
    <td><a href="<?= url('/leads/'.$l['id']) ?>"><?= e($l['lead_name']) ?></a></td>
    <td><?= e($l['company_name'] ?? '-') ?></td>
    <td><?= e($l['source'] ?? '-') ?></td>
    <td><?= e($l['service_interest'] ?? '-') ?></td>
    <td><?= money($l['estimated_value']) ?></td>
    <td><span class="badge bg-secondary"><?= e($l['status']) ?></span></td>
    <td class="small"><?= e($l['assigned_name'] ?? '-') ?></td>
    <td><a href="<?= url('/leads/'.$l['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div></div>
