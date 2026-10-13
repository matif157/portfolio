<?php
/**
 * GRAVITY CRM - Roles & Permissions
 * Phase 1: Foundation
 * 
 * Yahan roles create/edit/delete hote hain aur
 * permissions assign ki jati hain har role ko.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('roles', 'view');

$user = current_user();
$page_title = 'Roles & Permissions';
$active_page = 'roles';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create' || $action === 'update') {
        require_permission('roles', 'edit');
        
        $role_id = (int)post('role_id', 0);
        $name = sanitize(post('name'));
        $description = sanitize(post('description'));
        $permissions = post('permissions', []);
        
        $errors = [];
        validate_required($name, 'Role name', $errors);
        
        // Duplicate name check
        $exists = db_row('SELECT id FROM roles WHERE name = ? AND id != ?', [$name, $role_id]);
        if ($exists) {
            $errors[] = 'A role with this name already exists.';
        }
        
        if (empty($errors)) {
            if ($action === 'create') {
                db_execute('INSERT INTO roles (name, description) VALUES (?, ?)', [$name, $description]);
                $role_id = db_last_id();
                log_activity('roles', 'create', $role_id, 'Created role: ' . $name);
            } else {
                $existing = db_row('SELECT * FROM roles WHERE id = ?', [$role_id]);
                if (!$existing) {
                    flash('error', 'Role not found.');
                    redirect('roles.php');
                }
                db_execute('UPDATE roles SET name = ?, description = ? WHERE id = ?', [$name, $description, $role_id]);
                log_activity('roles', 'update', $role_id, 'Updated role: ' . $name);
            }
            
            // Update permissions
            db_execute('DELETE FROM role_permissions WHERE role_id = ?', [$role_id]);
            foreach ($permissions as $perm_id) {
                db_execute('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)', [$role_id, (int)$perm_id]);
            }
            
            flash('success', 'Role saved successfully.');
            redirect('roles.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete') {
        require_permission('roles', 'delete');
        $role_id = (int)post('role_id', 0);
        
        $existing = db_row('SELECT * FROM roles WHERE id = ?', [$role_id]);
        if (!$existing) {
            flash('error', 'Role not found.');
            redirect('roles.php');
        }
        
        if ($existing['is_system']) {
            flash('error', 'System roles cannot be deleted.');
            redirect('roles.php');
        }
        
        // Check if users are assigned
        $user_count = (int)db_value('SELECT COUNT(*) FROM users WHERE role_id = ?', [$role_id]);
        if ($user_count > 0) {
            flash('error', 'Cannot delete role. ' . $user_count . ' user(s) are assigned to this role.');
            redirect('roles.php');
        }
        
        db_execute('DELETE FROM roles WHERE id = ?', [$role_id]);
        log_activity('roles', 'delete', $role_id, 'Deleted role: ' . $existing['name']);
        flash('success', 'Role deleted successfully.');
        redirect('roles.php');
    }
}

// Get all roles with user counts
$roles = db_query(
    'SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count 
     FROM roles r ORDER BY r.id'
);

// Get all permissions grouped by module
$permissions = db_query('SELECT * FROM permissions ORDER BY module, action');

// Group permissions by module
$permission_groups = [];
foreach ($permissions as $perm) {
    $permission_groups[$perm['module']][] = $perm;
}

// Edit role
$edit_role = null;
$edit_role_permissions = [];
if (get('edit')) {
    $edit_role = db_row('SELECT * FROM roles WHERE id = ?', [(int)get('edit')]);
    if ($edit_role) {
        $rows = db_query('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$edit_role['id']]);
        $edit_role_permissions = array_column($rows, 'permission_id');
    }
}
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-shield-lock"></i> Roles & Permissions</h1>
    <button class="btn-primary-custom" onclick="toggleNewRole()"><i class="bi bi-plus"></i> New Role</button>
</div>

<!-- New/Edit Role Form -->
<div class="card" id="roleForm" style="display:<?= $edit_role ? 'block' : 'none' ?>;">
    <div class="card-header">
        <?= $edit_role ? 'Edit Role: ' . e($edit_role['name']) : 'Create New Role' ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $edit_role ? 'update' : 'create' ?>">
            <input type="hidden" name="role_id" value="<?= $edit_role['id'] ?? 0 ?>">
            
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label-custom">Role Name *</label>
                    <input type="text" name="name" class="form-control-custom" value="<?= e($edit_role['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">Description</label>
                    <input type="text" name="description" class="form-control-custom" value="<?= e($edit_role['description'] ?? '') ?>">
                </div>
            </div>
            
            <h5 class="mt-4 mb-3">Permissions</h5>
            <div class="row">
                <?php foreach ($permission_groups as $module => $perms): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header" style="text-transform:capitalize;">
                                <?= e(ucfirst($module)) ?>
                            </div>
                            <div class="card-body">
                                <?php foreach ($perms as $perm): ?>
                                    <div class="form-check mb-2">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="<?= $perm['id'] ?>" 
                                               class="form-check-input" 
                                               id="perm_<?= $perm['id'] ?>"
                                               <?= in_array($perm['id'], $edit_role_permissions) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="perm_<?= $perm['id'] ?>">
                                            <?= e($perm['label']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Save Role</button>
            <button type="button" class="btn-secondary-custom" onclick="toggleNewRole()">Cancel</button>
        </form>
    </div>
</div>

<!-- Roles List -->
<div class="card">
    <div class="card-header">Roles (<?= count($roles) ?>)</div>
    <div class="card-body">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Description</th>
                    <th>Users</th>
                    <th>System</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><strong><?= e($role['name']) ?></strong></td>
                        <td><?= e($role['description']) ?></td>
                        <td><span class="badge badge-info"><?= $role['user_count'] ?></span></td>
                        <td><?= $role['is_system'] ? '<span class="badge badge-secondary">System</span>' : '<span class="badge badge-success">Custom</span>' ?></td>
                        <td>
                            <a href="roles.php?edit=<?= $role['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                            <?php if (!$role['is_system'] && $role['user_count'] == 0): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this role?')">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                    <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleNewRole() {
    const form = document.getElementById('roleForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block' && !form.querySelector('input[name="role_id"]').value) {
        form.querySelector('input[name="name"]').value = '';
        form.querySelector('input[name="description"]').value = '';
        form.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>