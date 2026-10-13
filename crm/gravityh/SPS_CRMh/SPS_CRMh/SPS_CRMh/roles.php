<?php
/**
 * GRAVITY CRM — Roles & Role-Permission Matrix Manager
 * 
 * Yahan Administrator naye access roles bana sakta hai, existing roles ki permissions toggle kar sakta hai,
 * aur role-specific layout profiles (CEO, Immigration, Bookkeeper, AR, Front Desk, Tax Filer) assign kar sakta hai.
 * PDO prepared statements aur transaction use ho rahi hai taake role permissions safely sync hon.
 */

require_once __DIR__ . '/auth.php';
require_permission('admin.settings');

// Handle Role & Permission Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_role') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $layout_profile = $_POST['layout_profile'] ?? 'custom';

        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO roles (name, description, layout_profile) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $layout_profile]);
            $new_role_id = $pdo->lastInsertId();

            log_activity('Create Role', 'roles', $new_role_id, "Created security access role '$name' with layout profile '$layout_profile'", 'stage2_sensitive');
            set_flash('success', "Security role '$name' created successfully!");
        }
        redirect('roles.php');

    } elseif ($action === 'update_permissions') {
        $role_id = intval($_POST['role_id']);
        $selected_permissions = $_POST['permissions'] ?? []; // Array of permission IDs

        if ($role_id > 0) {
            // Transaction start kar rahe hain taake bulk update atomicity maintain rahe
            $pdo->beginTransaction();
            try {
                // Pehle purani permissions remove karenge
                $stmtDel = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmtDel->execute([$role_id]);

                // Ab nayi checked permissions insert karenge
                if (!empty($selected_permissions)) {
                    $stmtIns = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                    foreach ($selected_permissions as $pid) {
                        $stmtIns->execute([$role_id, intval($pid)]);
                    }
                }

                $pdo->commit();
                log_activity('Update Role Permissions', 'roles', $role_id, "Updated permission entitlements for role ID #$role_id (" . count($selected_permissions) . " active)", 'stage2_sensitive');
                set_flash('success', 'Role permissions updated successfully!');
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Permission update error: " . $e->getMessage());
                set_flash('danger', 'Failed to update permissions: ' . $e->getMessage());
            }
        }
        redirect('roles.php?role_id=' . $role_id);
    }
}

// Fetch all roles & permissions grouped by category
$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$all_permissions = $pdo->query("SELECT * FROM permissions ORDER BY category, id ASC")->fetchAll();

// Group permissions by category for cleaner matrix display
$permissions_by_cat = [];
foreach ($all_permissions as $p) {
    $permissions_by_cat[$p['category']][] = $p;
}

// Active role for permission editing
$active_role_id = intval($_GET['role_id'] ?? ($roles[0]['id'] ?? 1));
$active_role = null;
foreach ($roles as $r) {
    if ($r['id'] == $active_role_id) {
        $active_role = $r;
        break;
    }
}

// Fetch currently active permission IDs for selected role
$stmtActivePerms = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
$stmtActivePerms->execute([$active_role_id]);
$current_role_perm_ids = $stmtActivePerms->fetchAll(PDO::FETCH_COLUMN);

render_header('Roles & Permissions Matrix', 'admin');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🛡️ Roles &amp; Permissions Matrix</h1>
        <p class="page-subtitle">Configure granular View, Create, Edit, and Delete privileges across all CRM modules</p>
    </div>
    <div>
        <button onclick="document.getElementById('createRoleModal').style.display='flex'" class="btn btn-primary">+ Create New Role</button>
        <a href="permissions.php" class="btn btn-secondary">Permissions Directory</a>
    </div>
</div>

<!-- Role Selection Tabs -->
<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px;">
    <?php foreach ($roles as $r): ?>
        <a href="roles.php?role_id=<?= $r['id'] ?>" class="btn <?= $r['id'] == $active_role_id ? 'btn-primary' : 'btn-secondary' ?>">
            <?= e($r['name']) ?>
            <?php if ($r['id'] == 1): ?>
                <span class="badge badge-warning" style="margin-left:4px; font-size:9px;">SUPER</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="form-row" style="align-items:flex-start;">
    <!-- Active Role Details Card -->
    <div style="flex:1; min-width:300px; max-width:360px;">
        <div class="card">
            <div class="card-title">Role Configuration</div>
            
            <div style="font-size:16px; font-weight:700; color:var(--secondary); margin-bottom:6px;">
                <?= e($active_role['name']) ?>
            </div>
            <div style="color:var(--text-muted); font-size:13px; margin-bottom:16px;">
                <?= e($active_role['description'] ?: 'Standard CRM role.') ?>
            </div>

            <div style="padding:12px; background:#f8fafc; border:1px solid var(--border-color); border-radius:8px; font-size:12px; margin-bottom:16px;">
                <div style="margin-bottom:6px;"><strong>Assigned Layout Profile:</strong></div>
                <div><span class="badge badge-info"><?= strtoupper(e($active_role['layout_profile'])) ?> LAYOUT</span></div>
                <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">
                    Customizes default dashboard metrics &amp; widgets for this user position.
                </div>
            </div>

            <?php if ($active_role['id'] == 1): ?>
                <div style="padding:12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; font-size:12px; color:#166534;">
                    ✨ <strong>Super Administrator Notice:</strong><br>
                    Role #1 inherently possesses all CRM privileges, AI access tokens, and administrative rights across every module.
                </div>
            <?php else: ?>
                <div style="font-size:12px; color:var(--text-muted);">
                    Select which privileges this role is entitled to in the matrix on the right, then click <strong>Save Matrix</strong>.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Role Permission Matrix Table -->
    <div style="flex:2; min-width:360px;">
        <div class="card">
            <form action="roles.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_permissions">
                <input type="hidden" name="role_id" value="<?= $active_role['id'] ?>">

                <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Privilege Matrix for <?= e($active_role['name']) ?></span>
                    <div>
                        <button type="button" onclick="toggleAllCheckboxes(true)" class="btn btn-sm btn-secondary">Select All</button>
                        <button type="button" onclick="toggleAllCheckboxes(false)" class="btn btn-sm btn-secondary">Deselect All</button>
                    </div>
                </div>

                <?php foreach ($permissions_by_cat as $cat => $perms): ?>
                    <div style="margin-bottom:20px; border:1px solid var(--border-color); border-radius:8px; overflow:hidden;">
                        <div style="padding:10px 16px; background:#f8fafc; font-weight:700; font-size:13px; color:var(--secondary); border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                            <span>📁 <?= e($cat) ?> Module Permissions</span>
                            <span class="badge badge-secondary"><?= count($perms) ?> Tokens</span>
                        </div>

                        <div style="padding:12px 16px; display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:10px;">
                            <?php foreach ($perms as $p): ?>
                                <?php $isChecked = ($active_role['id'] == 1 || in_array($p['id'], $current_role_perm_ids)); ?>
                                <label style="display:flex; align-items:flex-start; gap:8px; font-size:13px; cursor:pointer; padding:6px; border-radius:6px; background:<?= $isChecked ? '#f0f9ff' : 'transparent' ?>;">
                                    <input type="checkbox" name="permissions[]" value="<?= $p['id'] ?>" class="perm-checkbox" <?= $isChecked ? 'checked' : '' ?> <?= $active_role['id'] == 1 ? 'disabled' : '' ?> style="margin-top:2px;">
                                    <div>
                                        <div style="font-weight:600; color:var(--secondary);"><?= e($p['name']) ?></div>
                                        <div style="font-size:11px; color:var(--text-muted); font-family:monospace;"><?= e($p['code']) ?></div>
                                        <div style="font-size:11px; color:#64748b;"><?= e($p['description']) ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($active_role['id'] != 1): ?>
                    <div style="text-align:right; margin-top:20px;">
                        <button type="submit" class="btn btn-primary" style="padding:10px 24px;">Save Permission Matrix</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- Create Role Modal -->
<div class="modal-overlay" id="createRoleModal" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <span style="font-weight:700; color:#1e293b;">Create New Access Role</span>
            <button onclick="document.getElementById('createRoleModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form action="roles.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_role">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Role Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Compliance Auditor or Tax Associate">
                </div>

                <div class="form-group">
                    <label class="form-label">Role Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Responsibilities and scope for this role..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Layout Profile</label>
                    <select name="layout_profile" class="form-control">
                        <option value="custom">Standard / Custom Layout</option>
                        <option value="ceo">CEO / Executive Layout</option>
                        <option value="bookkeeper">Bookkeeper / Accountant Layout</option>
                        <option value="tax_filer">Tax Specialist Layout</option>
                        <option value="ar">Accounts Receivable (AR) Layout</option>
                        <option value="front_desk">Front Desk &amp; Reception Layout</option>
                        <option value="immigration">Immigration Services Layout</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('createRoleModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Role</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAllCheckboxes(state) {
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        if (!cb.disabled) {
            cb.checked = state;
        }
    });
}
</script>

<?php render_footer(); ?>
