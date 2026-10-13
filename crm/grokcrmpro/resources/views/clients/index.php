<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2" method="GET">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search..." value="<?= e($q) ?>" style="min-width:200px">
        <select name="status" class="form-select form-select-sm" style="width:120px">
            <option value="">All</option>
            <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
        <button class="btn btn-sm btn-outline-primary">Search</button>
    </form>
    <?php if (has_permission('client.create')): ?>
    <a href="<?= url('/clients/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Client</a>
    <?php endif; ?>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover table-sm mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Outstanding</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($clients)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No clients. <a href="<?= url('/clients/create') ?>">Add one</a></td></tr>
            <?php else: foreach ($clients as $i => $c): ?>
                <tr>
                    <td><?= e($c['client_number'] ?? $c['id']) ?></td>
                    <td><a href="<?= url('/clients/'.$c['id']) ?>"><?= e($c['full_name']) ?></a></td>
                    <td><?= e($c['company_name'] ?? '-') ?></td>
                    <td class="small"><?= e($c['email'] ?? '-') ?></td>
                    <td class="small"><?= e($c['phone'] ?? $c['cell_phone'] ?? '-') ?></td>
                    <td class="<?= $c['outstanding']>0?'text-danger fw-bold':'' ?>"><?= money($c['outstanding']) ?></td>
                    <td><span class="badge bg-<?= $c['status']==='active'?'success':'secondary' ?>"><?= e($c['status']) ?></span></td>
                    <td>
                        <a href="<?= url('/clients/'.$c['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        <?php if (has_permission('client.update')): ?>
                        <a href="<?= url('/clients/'.$c['id'].'/edit') ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
