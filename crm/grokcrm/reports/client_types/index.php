<?php
/**
 * Client Types
 * Drop into: client_types/index.php
 */
require_once __DIR__ . '/../includes/log_in.php';
require_permission('ClientType', 'view');
$pageTitle = 'Client Types';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('ClientType', 'manage')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO client_types (type_name, description, color, sort_order, is_active) VALUES (?,?,?,?,1)")
            ->execute([
                trim($_POST['type_name'] ?? ''),
                trim($_POST['description'] ?? ''),
                trim($_POST['color'] ?? '#0d6efd'),
                (int)($_POST['sort_order'] ?? 0)
            ]);
        log_activity('create_client_type', 'ClientType', 'Added type: ' . ($_POST['type_name'] ?? ''));
        set_flash('Client type added.');
    }
    if ($action === 'edit') {
        $pdo->prepare("UPDATE client_types SET type_name=?, description=?, color=?, sort_order=?, is_active=? WHERE id=?")
            ->execute([
                trim($_POST['type_name'] ?? ''),
                trim($_POST['description'] ?? ''),
                trim($_POST['color'] ?? '#0d6efd'),
                (int)($_POST['sort_order'] ?? 0),
                isset($_POST['is_active']) ? 1 : 0,
                (int)$_POST['id']
            ]);
        log_activity('update_client_type', 'ClientType', 'Updated type ID ' . $_POST['id']);
        set_flash('Client type updated.');
    }
    header('Location: index.php');
    exit;
}

$types = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM members WHERE client_type_id = t.id) as client_count 
                      FROM client_types t ORDER BY t.sort_order, t.type_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between mb-3">
    <p class="text-muted mb-0">Classify clients (Individual, Business, Guest, etc.).</p>
    <?php if (has_permission('ClientType', 'manage')): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> Add Type
    </button>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-dark">
                <tr>
                    <th width="40">#</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Color</th>
                    <th>Clients</th>
                    <th>Status</th>
                    <th width="90">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($types)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No types. Run update_tables.sql first.</td></tr>
                <?php else: foreach ($types as $i => $t): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= e($t['type_name']) ?></strong></td>
                        <td class="small"><?= e($t['description'] ?? '-') ?></td>
                        <td>
                            <span class="d-inline-block rounded-circle" style="width:18px;height:18px;background:<?= e($t['color']) ?>;vertical-align:middle"></span>
                            <code class="small"><?= e($t['color']) ?></code>
                        </td>
                        <td><span class="badge bg-secondary"><?= $t['client_count'] ?></span></td>
                        <td><?= $t['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        <td>
                            <?php if (has_permission('ClientType', 'manage')): ?>
                            <button class="btn btn-sm btn-outline-warning" onclick='editType(<?= json_encode($t, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="add">
            <div class="modal-header"><h5 class="modal-title">Add Client Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Type Name *</label><input type="text" name="type_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label">Color</label><input type="color" name="color" class="form-control form-control-color" value="#0d6efd"></div>
                    <div class="col-6"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="0"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header"><h5 class="modal-title">Edit Client Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Type Name *</label><input type="text" name="type_name" id="edit_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea></div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label">Color</label><input type="color" name="color" id="edit_color" class="form-control form-control-color"></div>
                    <div class="col-6"><label class="form-label">Sort Order</label><input type="number" name="sort_order" id="edit_sort" class="form-control"></div>
                </div>
                <div class="form-check mt-2">
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
function editType(t) {
    document.getElementById('edit_id').value = t.id;
    document.getElementById('edit_name').value = t.type_name || '';
    document.getElementById('edit_desc').value = t.description || '';
    document.getElementById('edit_color').value = t.color || '#0d6efd';
    document.getElementById('edit_sort').value = t.sort_order || 0;
    document.getElementById('edit_active').checked = t.is_active == 1;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
