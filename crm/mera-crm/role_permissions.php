<?php
// =============================================
// FILE: role_permissions.php
// PURPOSE: Assign Permissions to a Role
// =============================================

require_once 'log_in.php';
require_once 'sidebar.php';

$allowed_roles = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'] ?? '', $allowed_roles)) {
    exit('Access Denied');
}

$role_id = (int)($_GET['role_id'] ?? 0);
if ($role_id <= 0) {
    header('Location: roles.php');
    exit;
}

// Role details
$stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->execute([$role_id]);
$role = $stmt->fetch();

if (!$role) {
    header('Location: roles.php');
    exit;
}

// ===================== SAVE PERMISSIONS =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Pehle purane permissions delete karo
    $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$role_id]);

    // Naye selected permissions insert karo
    if (!empty($_POST['permissions']) && is_array($_POST['permissions'])) {
        $insert = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($_POST['permissions'] as $perm_id) {
            $insert->execute([$role_id, (int)$perm_id]);
        }
    }

    if (function_exists('log_activity')) {
        log_activity($pdo, 'Roles', 'Permissions Updated', $role_id);
    }

    $_SESSION['msg'] = 'Permissions updated for ' . htmlspecialchars($role['role_name']);
    $_SESSION['msg_type'] = 'success';
    header("Location: role_permissions.php?role_id=$role_id");
    exit;
}

// Saari permissions (module wise)
$all_permissions = $pdo->query("SELECT * FROM permissions ORDER BY module_name, permission_key")->fetchAll();

// Group by module
$grouped = [];
foreach ($all_permissions as $perm) {
    $grouped[$perm['module_name']][] = $perm;
}

// Current role ke assigned permissions
$assigned = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
$assigned->execute([$role_id]);
$assigned_ids = $assigned->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Permissions - <?= htmlspecialchars($role['role_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #020617; color: #e2e8f0; }
        .main-wrapper { margin-left: 320px; padding: 30px; }
        .module-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-left: 4px solid #3b82f6;
            border-radius: 12px;
        }
        .form-check-label { cursor: pointer; color: #e2e8f0; }
    </style>
</head>
<body>

<div class="main-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="color:white;">
                Permissions for: <span style="color:#38bdf8;"><?= htmlspecialchars($role['role_name']) ?></span>
            </h2>
            <p class="text-muted"><?= htmlspecialchars($role['description'] ?? '') ?></p>
        </div>
        <a href="roles.php" class="btn btn-outline-light">← Back to Roles</a>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?>">
            <?= $_SESSION['msg'] ?>
        </div>
        <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <form method="POST">
        <div class="row g-4">
            <?php foreach ($grouped as $module => $permissions): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="module-card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <strong class="text-uppercase"><?= htmlspecialchars($module) ?></strong>
                            <div class="form-check">
                                <input class="form-check-input module-check" type="checkbox" 
                                       data-module="<?= htmlspecialchars($module) ?>" id="mod_<?= $module ?>">
                                <label class="form-check-label small" for="mod_<?= $module ?>">Select All</label>
                            </div>
                        </div>

                        <?php foreach ($permissions as $perm): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input perm-check" 
                                       type="checkbox" 
                                       name="permissions[]" 
                                       value="<?= $perm['id'] ?>"
                                       id="perm_<?= $perm['id'] ?>"
                                       data-module="<?= htmlspecialchars($module) ?>"
                                       <?= in_array($perm['id'], $assigned_ids) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="perm_<?= $perm['id'] ?>">
                                    <?= htmlspecialchars($perm['permission_label']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary btn-lg px-5">
                Save Permissions
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Select All per module
document.querySelectorAll('.module-check').forEach(function(moduleCheckbox) {
    moduleCheckbox.addEventListener('change', function() {
        const module = this.dataset.module;
        const checked = this.checked;
        document.querySelectorAll(`.perm-check[data-module="${module}"]`).forEach(function(cb) {
            cb.checked = checked;
        });
    });
});
</script>
</body>
</html>