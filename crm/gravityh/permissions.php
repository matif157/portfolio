<?php
/**
 * GRAVITY CRM - Permissions
 * Phase 1: Foundation
 * 
 * Yahan saari permissions dikhti hain.
 * Admin naye permissions bhi create kar sakta hai.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('permissions', 'view');

$user = current_user();
$page_title = 'Permissions';
$active_page = 'permissions';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create') {
        require_permission('permissions', 'edit');
        
        $module = sanitize(post('module'));
        $action_name = sanitize(post('action_name'));
        $label = sanitize(post('label'));
        
        $errors = [];
        validate_required($module, 'Module', $errors);
        validate_required($action_name, 'Action', $errors);
        validate_required($label, 'Label', $errors);
        
        if (empty($errors)) {
            try {
                db_execute('INSERT INTO permissions (module, action, label) VALUES (?, ?, ?)', [$module, $action_name, $label]);
                log_activity('permissions', 'create', db_last_id(), 'Created permission: ' . $label . ' for ' . $module);
                flash('success', 'Permission created successfully.');
            } catch (PDOException $e) {
                flash('error', 'This permission already exists.');
            }
            redirect('permissions.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
}

// Get all permissions grouped by module
$permissions = db_query('SELECT * FROM permissions ORDER BY module, action');
$permission_groups = [];
foreach ($permissions as $perm) {
    $permission_groups[$perm['module']][] = $perm;
}

// Stats
$total_permissions = count($permissions);
$total_modules = count($permission_groups);
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-key"></i> Permissions</h1>
    <button class="btn-primary-custom" onclick="toggleNewPermission()"><i class="bi bi-plus"></i> New Permission</button>
</div>

<!-- Stats -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-key"></i></div>
            <div class="stat-value"><?= $total_permissions ?></div>
            <div class="stat-label">Total Permissions</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-grid"></i></div>
            <div class="stat-value"><?= $total_modules ?></div>
            <div class="stat-label">Modules</div>
        </div>
    </div>
</div>

<!-- New Permission Form -->
<div class="card" id="newPermissionForm" style="display:none;">
    <div class="card-header">Create New Permission</div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Module *</label>
                    <input type="text" name="module" class="form-control-custom" placeholder="e.g. reports" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Action *</label>
                    <input type="text" name="action_name" class="form-control-custom" placeholder="e.g. export" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Label *</label>
                    <input type="text" name="label" class="form-control-custom" placeholder="e.g. Export Reports" required>
                </div>
            </div>
            
            <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Create</button>
            <button type="button" class="btn-secondary-custom" onclick="toggleNewPermission()">Cancel</button>
        </form>
    </div>
</div>

<!-- Permissions List -->
<div class="card">
    <div class="card-header">All Permissions</div>
    <div class="card-body">
        <?php if (empty($permission_groups)): ?>
            <p class="text-muted">No permissions found.</p>
        <?php else: ?>
            <div class="row">
                <?php foreach ($permission_groups as $module => $perms): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header" style="text-transform:capitalize;">
                                <?= e(ucfirst($module)) ?> (<?= count($perms) ?>)
                            </div>
                            <div class="card-body">
                                <?php foreach ($perms as $perm): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>
                                            <i class="bi bi-shield-check text-success"></i>
                                            <?= e($perm['label']) ?>
                                        </span>
                                        <small class="text-muted"><?= e($perm['action']) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleNewPermission() {
    const form = document.getElementById('newPermissionForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
        form.querySelectorAll('input[type="text"]').forEach(i => i.value = '');
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>