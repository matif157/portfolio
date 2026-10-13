<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2" method="GET">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search..." value="<?= e($q) ?>" style="min-width:180px">
        <select name="status" class="form-select form-select-sm" style="width:150px">
            <option value="">All Status</option>
            <?php foreach (['draft','open','in_progress','waiting_client','under_review','completed','closed','cancelled'] as $st): ?>
            <option value="<?= $st ?>" <?= $status===$st?'selected':'' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary">Filter</button>
    </form>
    <?php if (has_permission('project.create')): ?>
    <a href="<?= url('/projects/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Project</a>
    <?php endif; ?>
</div>
<div class="card"><div class="card-body p-0">
<table class="table table-hover table-sm mb-0">
<thead><tr><th>Number</th><th>Name</th><th>Client</th><th>Manager</th><th>Status</th><th>Priority</th><th></th></tr></thead>
<tbody>
<?php if (empty($projects)): ?>
<tr><td colspan="7" class="text-center text-muted py-4">No projects</td></tr>
<?php else: foreach ($projects as $p): ?>
<tr>
    <td><a href="<?= url('/projects/'.$p['id']) ?>"><?= e($p['project_number']) ?></a></td>
    <td><?= e($p['name']) ?></td>
    <td><?= e($p['client_name'] ?? '-') ?></td>
    <td class="small"><?= e($p['manager_name'] ?? '-') ?></td>
    <td><span class="badge bg-secondary"><?= e($p['status']) ?></span></td>
    <td><?= e($p['priority']) ?></td>
    <td><a href="<?= url('/projects/'.$p['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div></div>
