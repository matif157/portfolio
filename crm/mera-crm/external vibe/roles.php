<?php
// =============================================
// FILE: roles.php - ROLE MANAGEMENT
// =============================================

require_once 'log_in.php';

$allowed_roles = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'], $allowed_roles)) {
    exit('Access Denied');
}

// Fetch all roles
$roles = $pdo->query("SELECT r.*, 
                             (SELECT COUNT(*) FROM users WHERE role_id = r.id) as user_count
                      FROM roles r 
                      ORDER BY r.id ASC")->fetchAll();

// Add / Edit Role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    $role_name   = trim($_POST['role_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $role_id     = (int)($_POST['role_id'] ?? 0);

    if (empty($role_name)) {
        $_SESSION['msg'] = 'Role name is required.';
        $_SESSION['msg_type'] = 'danger';
        header('Location: roles.php');
        exit;
    }

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)");
        $stmt->execute([$role_name, $description]);
        $_SESSION['msg'] = 'Role added successfully!';
        $_SESSION['msg_type'] = 'success';
    }

    if ($action === 'edit' && $role_id > 0) {
        $stmt = $pdo->prepare("UPDATE roles SET role_name = ?, description = ? WHERE id = ?");
        $stmt->execute([$role_name, $description, $role_id]);
        $_SESSION['msg'] = 'Role updated successfully!';
        $_SESSION['msg_type'] = 'success';
    }

    header('Location: roles.php');
    exit;
}

// Toggle Active
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE roles SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    $_SESSION['msg'] = 'Role status updated!';
    $_SESSION['msg_type'] = 'success';
    header('Location: roles.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Roles Management - mera_crm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Roles Management</h3>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
            <i class="bi bi-plus-lg"></i> Add New Role
        </button>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Users</th>
                        <th>Status</th>
                        <th width="180">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $i => $role): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= htmlspecialchars($role['role_name']) ?></strong></td>
                            <td><?= htmlspecialchars($role['description'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-secondary"><?= $role['user_count'] ?> users</span>
                            </td>
                            <td>
                                <?php if ($role['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning" 
                                        onclick="editRole(<?= $role['id'] ?>, '<?= htmlspecialchars(addslashes($role['role_name'])) ?>', '<?= htmlspecialchars(addslashes($role['description'] ?? '')) ?>')">
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <a href="role_permissions.php?role_id=<?= $role['id'] ?>" 
                                   class="btn btn-sm btn-outline-info" title="Manage Permissions">
                                    <i class="bi bi-shield-lock"></i>
                                </a>

                                <?php if ($role['id'] > 2): // Super Admin & Admin ko toggle mat karo ?>
                                    <a href="roles.php?toggle=<?= $role['id'] ?>" 
                                       class="btn btn-sm btn-outline-secondary"
                                       onclick="return confirm('Are you sure?')">
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
</div>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h5 class="modal-title">Add New Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Role Name *</label>
                    <input type="text" name="role_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="role_id" id="edit_role_id">
            <div class="modal-header">
                <h5 class="modal-title">Edit Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Role Name *</label>
                    <input type="text" name="role_name" id="edit_role_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Role</button>
            </div>
        </form>
    </div>
<<?php require_once 'sidebar.php'; ?>

<div style="margin-left:260px; padding:40px;">
    <!-- Page content yahan -->
</div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editRole(id, name, description) {
    document.getElementById('edit_role_id').value = id;
    document.getElementById('edit_role_name').value = name;
    document.getElementById('edit_description').value = description;
    new bootstrap.Modal(document.getElementById('editRoleModal')).show();
}
</script>
</body>
</html>