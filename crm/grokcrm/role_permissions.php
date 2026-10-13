<?php
require_once 'includes/log_in.php';
require_permission('System', 'roles');
$pageTitle = 'Role Permissions';

$role_id = (int)($_GET['role_id'] ?? 0);
if ($role_id <= 0) { header('Location: roles.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->execute([$role_id]);
$role = $stmt->fetch();
if (!$role) { set_flash('Role not found.','danger'); header('Location: roles.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$role_id]);
    if (!empty($_POST['permissions']) && is_array($_POST['permissions'])) {
        $ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)");
        foreach ($_POST['permissions'] as $pid) {
            $ins->execute([$role_id, (int)$pid]);
        }
    }
    log_activity('update_permissions', 'Roles', 'Updated permissions for role: ' . $role['role_name']);
    set_flash('Permissions saved for ' . $role['role_name']);
    header("Location: role_permissions.php?role_id=$role_id");
    exit;
}

$all = $pdo->query("SELECT * FROM permissions ORDER BY module_name, permission_key")->fetchAll();
$grouped = [];
foreach ($all as $p) $grouped[$p['module_name']][] = $p;

$assigned = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
$assigned->execute([$role_id]);
$assigned_ids = $assigned->fetchAll(PDO::FETCH_COLUMN);

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0">Permissions for: <span class="text-primary"><?= e($role['role_name']) ?></span></h5>
        <small class="text-muted"><?= e($role['description'] ?? '') ?></small>
    </div>
    <a href="roles.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<form method="POST">
    <div class="row g-3">
        <?php foreach ($grouped as $module => $perms): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between">
                    <strong class="text-uppercase"><?= e($module) ?></strong>
                    <div class="form-check">
                        <input class="form-check-input module-check" type="checkbox" data-module="<?= e($module) ?>">
                        <label class="form-check-label small">All</label>
                    </div>
                </div>
                <div class="card-body">
                    <?php foreach ($perms as $p): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input perm-check" type="checkbox" name="permissions[]" 
                               value="<?= $p['id'] ?>" data-module="<?= e($module) ?>"
                               id="p<?= $p['id'] ?>"
                               <?= in_array($p['id'], $assigned_ids) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="p<?= $p['id'] ?>">
                            <?= e($p['permission_label']) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-4 text-end">
        <button type="submit" class="btn btn-primary btn-lg px-4"><i class="bi bi-save"></i> Save Permissions</button>
    </div>
</form>

<script>
document.querySelectorAll('.module-check').forEach(cb => {
    cb.addEventListener('change', function() {
        const mod = this.dataset.module;
        document.querySelectorAll(`.perm-check[data-module="${mod}"]`).forEach(p => p.checked = this.checked);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
