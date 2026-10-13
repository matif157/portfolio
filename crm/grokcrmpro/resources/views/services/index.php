<div class="d-flex justify-content-between mb-3">
    <div></div>
    <?php if (has_permission('service.manage')): ?>
    <a href="<?= url('/services/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Service</a>
    <?php endif; ?>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover table-sm mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Price</th><th>Billing</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td><code><?= e($s['service_code'] ?? '-') ?></code></td>
                    <td><?= e($s['name']) ?></td>
                    <td><?= e($s['category_name'] ?? '-') ?></td>
                    <td><?= money($s['default_price']) ?></td>
                    <td><?= e($s['billing_type']) ?></td>
                    <td><?= $s['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Off</span>' ?></td>
                    <td>
                        <?php if (has_permission('service.manage')): ?>
                        <a href="<?= url('/services/'.$s['id'].'/edit') ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
