<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 style="color:#fff" class="mb-0"><?= e($project['project_number']) ?> — <?= e($project['name']) ?>
            <span class="badge bg-secondary ms-2"><?= e($project['status']) ?></span>
        </h4>
        <small class="text-muted">Client: <a href="<?= url('/clients/'.$project['client_id']) ?>"><?= e($project['client_name']) ?></a></small>
    </div>
    <div class="d-flex gap-2">
        <?php if (has_permission('project.update')): ?>
        <a href="<?= url('/projects/'.$project['id'].'/edit') ?>" class="btn btn-outline-warning btn-sm">Edit</a>
        <?php endif; ?>
        <?php if (has_permission('invoice.create')): ?>
        <a href="<?= url('/invoices/create?client_id='.$project['client_id']) ?>" class="btn btn-primary btn-sm">New Invoice</a>
        <?php endif; ?>
        <a href="<?= url('/projects') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="stat"><div class="label">Status</div><div class="value" style="font-size:1.1rem"><?= e($project['status']) ?></div></div></div>
    <div class="col-md-3"><div class="stat"><div class="label">Priority</div><div class="value" style="font-size:1.1rem"><?= e($project['priority']) ?></div></div></div>
    <div class="col-md-3"><div class="stat"><div class="label">Manager</div><div class="value" style="font-size:1rem"><?= e($project['manager_name'] ?? '-') ?></div></div></div>
    <div class="col-md-3"><div class="stat"><div class="label">Dates</div><div class="value" style="font-size:.95rem"><?= e($project['start_date'] ?? '-') ?> → <?= e($project['end_date'] ?? '-') ?></div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header">Services</div><div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Service</th><th>Qty</th><th>Price</th></tr></thead>
                <tbody>
                <?php if (empty($services)): ?>
                <tr><td colspan="3" class="text-center text-muted py-3">None attached</td></tr>
                <?php else: foreach ($services as $s): ?>
                <tr>
                    <td><?= e($s['service_name'] ?? $s['description'] ?? '-') ?></td>
                    <td><?= e((string)$s['quantity']) ?></td>
                    <td><?= money($s['unit_price']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div></div>
        <?php if ($project['description']): ?>
        <div class="card"><div class="card-header">Description</div><div class="card-body small"><?= e($project['description']) ?></div></div>
        <?php endif; ?>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header">Staff</div><div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Name</th><th>Role</th></tr></thead>
                <tbody>
                <?php if (empty($staff)): ?>
                <tr><td colspan="2" class="text-center text-muted py-3">None</td></tr>
                <?php else: foreach ($staff as $st): ?>
                <tr><td><?= e($st['full_name']) ?></td><td><?= e($st['role_label'] ?? '-') ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div></div>
        <div class="card"><div class="card-header">Linked Invoices</div><div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Number</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="3" class="text-center text-muted py-3">None</td></tr>
                <?php else: foreach ($invoices as $inv): ?>
                <tr>
                    <td><a href="<?= url('/invoices/'.$inv['id']) ?>"><?= e($inv['invoice_number']) ?></a></td>
                    <td><?= money($inv['grand_total']) ?></td>
                    <td><span class="badge bg-secondary"><?= e($inv['status']) ?></span></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>
