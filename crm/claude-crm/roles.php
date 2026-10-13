<?php
// ============================================================
// SPS CRM - Roles
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('admin.users');

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name) {
        $rid = db_insert("INSERT INTO roles (name, description, created_at) VALUES (?,?,NOW())", [$name, $desc ?: null]);
        flash_success('Role created. Now assign its permissions below.');
        redirect('roles.php?edit=' . $rid);
    }
    redirect('roles.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_perms') {
    verify_csrf();
    $role_id = (int)($_POST['role_id'] ?? 0);
    $perm_ids = array_filter(array_map('intval', $_POST['permission_ids'] ?? []));
    db_query("DELETE FROM role_permissions WHERE role_id=?", [$role_id]);
    foreach ($perm_ids as $pid) {
        db_query("INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)", [$role_id, $pid]);
    }
    log_activity('update', 'roles', $role_id, 'Permissions updated', null, 2);
    flash_success('Permissions saved.');
    redirect('roles.php');
}

$roles = db_rows("SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id=r.id) as user_count FROM roles r ORDER BY r.name");
$permissions = db_rows("SELECT * FROM permissions ORDER BY module, label");
$edit_role_id = (int)($_GET['edit'] ?? 0);
$edit_role = $edit_role_id ? db_row("SELECT * FROM roles WHERE id=?", [$edit_role_id]) : null;
$edit_perm_ids = $edit_role ? array_column(db_rows("SELECT permission_id FROM role_permissions WHERE role_id=?", [$edit_role_id]), 'permission_id') : [];

$grouped = [];
foreach ($permissions as $p) { $grouped[$p['module']][] = $p; }

render_header('Roles', 'roles');
?>
<?= render_flash() ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

<div class="card">
  <div class="card-header">
    <h2>Roles</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('add-role-modal')">+ Add Role</button>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Name</th><th>Description</th><th>Users</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($roles as $r): ?>
      <tr>
        <td style="font-weight:500"><?= e($r['name']) ?></td>
        <td class="text-muted"><?= e($r['description'] ?: '—') ?></td>
        <td><?= (int)$r['user_count'] ?></td>
        <td><a href="roles.php?edit=<?= $r['id'] ?>" class="btn btn-xs btn-secondary">Edit Permissions</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($edit_role): ?>
<div class="card">
  <div class="card-header"><h3>Permissions — <?= e($edit_role['name']) ?></h3></div>
  <div class="card-body">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_perms">
      <input type="hidden" name="role_id" value="<?= $edit_role_id ?>">
      <?php foreach ($grouped as $module => $perms): ?>
      <div style="margin-bottom:12px">
        <div style="font-size:12px;font-weight:600;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px"><?= e($module) ?></div>
        <?php foreach ($perms as $p): ?>
        <label class="checkbox-label" style="display:block">
          <input type="checkbox" name="permission_ids[]" value="<?= $p['id'] ?>" <?= in_array($p['id'], $edit_perm_ids) ? 'checked' : '' ?>>
          <?= e($p['label']) ?>
        </label>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
      <button type="submit" class="btn btn-primary">Save Permissions</button>
    </form>
  </div>
</div>
<?php else: ?>
<div class="card"><div class="card-body text-center text-muted" style="padding:40px">Select "Edit Permissions" on a role to configure its access.</div></div>
<?php endif; ?>

</div>

<div class="modal-overlay" id="add-role-modal">
  <div class="modal">
    <div class="modal-header"><h3>Add Role</h3><button class="modal-close" onclick="closeModal('add-role-modal')">×</button></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group"><label>Role Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
        <div class="form-group"><label>Description</label><input type="text" name="description" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-role-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Role</button>
      </div>
    </form>
  </div>
</div>
<?php render_footer(); ?>
