<?php
require_once 'includes/log_in.php';
require_permission('System', 'roles');
$pageTitle = 'Roles Management';

$roles = $pdo->query("SELECT r.*, (SELECT COUNT(*) FROM users WHERE role_id = r.id) as user_count FROM roles r ORDER BY r.id")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $role_name = trim($_POST['role_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $role_id = (int)($_POST['role_id'] ?? 0);

    if ($role_name === '') {
        set_flash('Role name is required.', 'danger');
        header('Location: roles.php'); exit;
    }

    if ($action === 'add') {
        $pdo->prepare("INSERT INTO roles (role_name, description) VALUES (?,?)")->execute([$role_name, $description]);
        log_activity('create_role', 'Roles', "Created role: $role_name");
        set_flash('Role added successfully.');
    }
    if ($action === 'edit' && $role_id > 0) {
        $pdo->prepare("UPDATE roles SET role_name=?, description=? WHERE id=?")->execute([$role_name, $description, $role_id]);
        log_activity('update_role', 'Roles', "Updated role: $role_name");
        set_flash('Role updated successfully.');
    }
    header('Location: roles.php'); exit;
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE roles SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    set_flash('Role status updated.');
    header('Location: roles.php'); exit;
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between mb-3">
    <div></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
        <i class="bi bi-plus-lg"></i> Add Role
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Role Name</th>
                    <th>Description</th>
                    <th>Users</th>
                    <th>Status</th>
                    <th width="160">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $i => $r): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= e($r['role_name']) ?></strong></td>
                    <td><?= e($r['description'] ?? '-') ?></td>
                    <td><span class="badge bg-secondary"><?= $r['user_count'] ?></span></td>
                    <td><?= $r['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-warning" onclick="editRole(<?= $r['id'] ?>,'<?= e(addslashes($r['role_name'])) ?>','<?= e(addslashes($r['description']??'')) ?>')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="role_permissions.php?role_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-info" title="Permissions">
                            <i class="bi bi-shield-lock"></i>
                        </a>
                        <?php if ($r['id'] > 2): ?>
                        <a href="roles.php?toggle=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Toggle status?')">
                            <i class="bi bi-toggle-on"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="add">
            <div class="modal-header"><h5 class="modal-title">Add Role</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Role Name *</label><input type="text" name="role_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="role_id" id="edit_role_id">
            <div class="modal-header"><h5 class="modal-title">Edit Role</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Role Name *</label><input type="text" name="role_name" id="edit_role_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="edit_description" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function editRole(id, name, desc) {
    document.getElementById('edit_role_id').value = id;
    document.getElementById('edit_role_name').value = name;
    document.getElementById('edit_description').value = desc;
    new bootstrap.Modal(document.getElementById('editRoleModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
