<?php
/**
 * Services Catalog
 * Drop into: services/index.php
 */
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Service', 'view');
$pageTitle = 'Services';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('Service', 'manage')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO services (service_name, service_code, description, default_price, unit, category, is_active) VALUES (?,?,?,?,?,?,1)")
            ->execute([
                trim($_POST['service_name'] ?? ''),
                trim($_POST['service_code'] ?? ''),
                trim($_POST['description'] ?? ''),
                (float)($_POST['default_price'] ?? 0),
                trim($_POST['unit'] ?? 'each'),
                trim($_POST['category'] ?? '')
            ]);
        log_activity('create_service', 'Service', 'Added service: ' . ($_POST['service_name'] ?? ''));
        set_flash('Service added.');
    }
    if ($action === 'edit') {
        $pdo->prepare("UPDATE services SET service_name=?, service_code=?, description=?, default_price=?, unit=?, category=?, is_active=? WHERE id=?")
            ->execute([
                trim($_POST['service_name'] ?? ''),
                trim($_POST['service_code'] ?? ''),
                trim($_POST['description'] ?? ''),
                (float)($_POST['default_price'] ?? 0),
                trim($_POST['unit'] ?? 'each'),
                trim($_POST['category'] ?? ''),
                isset($_POST['is_active']) ? 1 : 0,
                (int)$_POST['id']
            ]);
        log_activity('update_service', 'Service', 'Updated service ID ' . $_POST['id']);
        set_flash('Service updated.');
    }
    header('Location: index.php');
    exit;
}

if (isset($_GET['toggle']) && has_permission('Service', 'manage')) {
    $pdo->prepare("UPDATE services SET is_active = NOT is_active WHERE id = ?")->execute([(int)$_GET['toggle']]);
    set_flash('Status updated.');
    header('Location: index.php');
    exit;
}

$services = $pdo->query("SELECT * FROM services ORDER BY category, sort_order, service_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between mb-3">
    <p class="text-muted mb-0">Service catalog used when creating invoice line items.</p>
    <?php if (has_permission('Service', 'manage')): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> Add Service
    </button>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Service</th>
                    <th>Code</th>
                    <th>Category</th>
                    <th>Default Price</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th width="100">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($services)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No services. Run update_tables.sql and add services.</td></tr>
                <?php else: foreach ($services as $i => $s): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td>
                            <strong><?= e($s['service_name']) ?></strong>
                            <?php if ($s['description']): ?><br><small class="text-muted"><?= e($s['description']) ?></small><?php endif; ?>
                        </td>
                        <td><code><?= e($s['service_code'] ?? '-') ?></code></td>
                        <td><?= e($s['category'] ?? '-') ?></td>
                        <td><?= money($s['default_price']) ?></td>
                        <td><?= e($s['unit'] ?? 'each') ?></td>
                        <td><?= $s['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        <td>
                            <?php if (has_permission('Service', 'manage')): ?>
                            <button class="btn btn-sm btn-outline-warning" onclick='editService(<?= json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="?toggle=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-toggle-on"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="add">
            <div class="modal-header"><h5 class="modal-title">Add Service</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Service Name *</label><input type="text" name="service_name" class="form-control" required></div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label">Code</label><input type="text" name="service_code" class="form-control"></div>
                    <div class="col-6"><label class="form-label">Category</label><input type="text" name="category" class="form-control" placeholder="Accounting, Tax..."></div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6"><label class="form-label">Default Price</label><input type="number" step="0.01" name="default_price" class="form-control" value="0"></div>
                    <div class="col-6"><label class="form-label">Unit</label><input type="text" name="unit" class="form-control" value="each"></div>
                </div>
                <div class="mb-3 mt-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header"><h5 class="modal-title">Edit Service</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Service Name *</label><input type="text" name="service_name" id="edit_name" class="form-control" required></div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label">Code</label><input type="text" name="service_code" id="edit_code" class="form-control"></div>
                    <div class="col-6"><label class="form-label">Category</label><input type="text" name="category" id="edit_category" class="form-control"></div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6"><label class="form-label">Default Price</label><input type="number" step="0.01" name="default_price" id="edit_price" class="form-control"></div>
                    <div class="col-6"><label class="form-label">Unit</label><input type="text" name="unit" id="edit_unit" class="form-control"></div>
                </div>
                <div class="mb-3 mt-2"><label class="form-label">Description</label><textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea></div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="edit_active">
                    <label class="form-check-label" for="edit_active">Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function editService(s) {
    document.getElementById('edit_id').value = s.id;
    document.getElementById('edit_name').value = s.service_name || '';
    document.getElementById('edit_code').value = s.service_code || '';
    document.getElementById('edit_category').value = s.category || '';
    document.getElementById('edit_price').value = s.default_price || 0;
    document.getElementById('edit_unit').value = s.unit || 'each';
    document.getElementById('edit_desc').value = s.description || '';
    document.getElementById('edit_active').checked = s.is_active == 1;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
