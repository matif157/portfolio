<?php
// =============================================
// FILE: role_permissions.php
// Permission Assignment Matrix
// =============================================

require_once 'log_in.php';

$allowed_roles = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'], $allowed_roles)) {
    exit("<div style='padding:40px;font-family:Arial;'>
            <h2 style='color:#dc3545;'>Access Denied</h2>
            <a href='dashboard.php'>← Back to Dashboard</a>
          </div>");
}

// Role ID check
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
    $_SESSION['msg'] = 'Role not found!';
    $_SESSION['msg_type'] = 'danger';
    header('Location: roles.php');
    exit;
}

// -------------------------------------------------
// Save Permissions (Form Submit)
// -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Pehle is role ke saare old permissions delete kar do
    $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$role_id]);

    // Naye selected permissions insert karo
    if (!empty($_POST['permissions']) && is_array($_POST['permissions'])) {
        $insert = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        
        foreach ($_POST['permissions'] as $perm_id) {
            $insert->execute([$role_id, (int)$perm_id]);
        }
    }

    $_SESSION['msg'] = 'Permissions updated successfully for <strong>' . htmlspecialchars($role['role_name']) . '</strong>!';
    $_SESSION['msg_type'] = 'success';
    header("Location: role_permissions.php?role_id=$role_id");
    exit;
}

// -------------------------------------------------
// Fetch All Permissions (grouped by module)
// -------------------------------------------------
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permissions - <?= htmlspecialchars($role['role_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .module-card {
            border-left: 4px solid #0d6efd;
        }
        .form-check-label {
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1">
                <i class="bi bi-shield-lock"></i> 
                Permissions for: <span class="text-primary"><?= htmlspecialchars($role['role_name']) ?></span>
            </h3>
            <small class="text-muted"><?= htmlspecialchars($role['description'] ?? '') ?></small>
        </div>
        <div>
            <a href="roles.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Roles
            </a>
        </div>
    </div>

    <!-- Flash Message -->
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show">
            <?= $_SESSION['msg'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <form method="POST">
        <div class="row g-4">

            <?php foreach ($grouped as $module => $permissions): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card module-card shadow-sm h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <strong class="text-uppercase"><?= htmlspecialchars($module) ?></strong>
                            <div class="form-check">
                                <input class="form-check-input module-check" type="checkbox" 
                                       data-module="<?= htmlspecialchars($module) ?>" id="mod_<?= $module ?>">
                                <label class="form-check-label small" for="mod_<?= $module ?>">Select All</label>
                            </div>
                        </div>
                        <div class="card-body">
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
                                        <small class="text-muted">(<?= $perm['permission_key'] ?>)</small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>

        <!-- Save Button -->
        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary btn-lg px-5">
                <i class="bi bi-save"></i> Save Permissions
            </button>
        </div>
    </form>
<<?php require_once 'sidebar.php'; ?>

<div style="margin-left:260px; padding:40px;">
    <!-- Page content yahan -->
</div></div>

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

// Agar module ke saare permissions checked hain to "Select All" bhi checked dikhao
document.querySelectorAll('.module-check').forEach(function(moduleCheckbox) {
    const module = moduleCheckbox.dataset.module;
    const perms = document.querySelectorAll(`.perm-check[data-module="${module}"]`);
    const allChecked = [...perms].every(cb => cb.checked);
    moduleCheckbox.checked = allChecked && perms.length > 0;
});
</script>
</body>
</html>