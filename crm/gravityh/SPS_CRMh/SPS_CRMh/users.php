<?php
/**
 * GRAVITY CRM — User & Staff Account Directory
 * 
 * Yahan Administrator naye staff accounts create kar sakta hai, role assign kar sakta hai,
 * aur password reset kar sakta hai. 
 * Note: Password policy ke mutabiq non-admin users apna password khud change nahi kar sakte.
 */

require_once __DIR__ . '/auth.php';
require_permission('admin.settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Create User
    if ($action === 'add_user') {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role_id = intval($_POST['role_id']);

        if (!empty($username) && !empty($password) && !empty($email)) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$username, $password, $email, $full_name, $role_id]);
            $user_id = $pdo->lastInsertId();

            log_activity('Add User', 'users', $user_id, "Created staff user '$username' ($full_name)", 'stage2_sensitive');
            set_flash('success', "User '$username' created successfully!");
            redirect('users.php');
        }

    // Admin Reset User Password
    } elseif ($action === 'reset_password') {
        $target_user_id = intval($_POST['user_id']);
        $new_password = $_POST['new_password'];

        if ($target_user_id > 0 && !empty($new_password)) {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_password, $target_user_id]);

            log_activity('Reset Password', 'users', $target_user_id, "Administrator reset password for User ID #$target_user_id", 'stage2_sensitive');
            set_flash('success', "Password successfully reset for User #$target_user_id!");
            redirect('users.php');
        }
    }
}

$users = $pdo->query("
    SELECT u.*, r.name as role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    ORDER BY u.id ASC
")->fetchAll();

$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

render_header('Users & Staff Management', 'admin');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">👥 CRM Users &amp; Staff Directory</h1>
        <p class="page-subtitle">Manage system access accounts, staff positions, roles, and administrative password controls</p>
    </div>
    <div>
        <button onclick="document.getElementById('addUserModal').style.display = 'flex'" class="btn btn-primary">+ Add New Staff User</button>
        <a href="roles.php" class="btn btn-secondary">Manage Roles Matrix</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>Email Address</th>
                    <th>Assigned Role</th>
                    <th>Status</th>
                    <th>Created Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <?php if (!empty($u['avatar']) && file_exists(__DIR__ . '/' . $u['avatar'])): ?>
                                    <img src="<?= e($u['avatar']) ?>" style="width:34px; height:34px; border-radius:50%; object-fit:cover;" alt="Avatar">
                                <?php else: ?>
                                    <div style="width:34px; height:34px; border-radius:50%; background:#2563eb; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px;">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?= e($u['full_name']) ?></strong>
                                    <?php if ($u['id'] == current_user()['id']): ?>
                                        <span class="badge badge-secondary" style="font-size:9px;">(You)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><code><?= e($u['username']) ?></code></td>
                        <td><?= e($u['email']) ?></td>
                        <td><span class="badge badge-info"><?= e($u['role_name']) ?></span></td>
                        <td><span class="badge badge-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($u['status']) ?></span></td>
                        <td><?= format_date($u['created_at']) ?></td>
                        <td>
                            <button type="button" onclick="openResetModal(<?= $u['id'] ?>, '<?= e($u['username']) ?>')" class="btn btn-sm btn-secondary">🔑 Reset Password</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <span style="font-weight:700; color:#1e293b;">Create User Account</span>
            <button onclick="document.getElementById('addUserModal').style.display = 'none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form action="users.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_user">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required placeholder="Jane Smith">
                </div>

                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" required placeholder="jsmith">
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-control" required placeholder="jane@gravitycrm.com">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label">Assigned Role *</label>
                        <select name="role_id" class="form-control" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('addUserModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Admin Reset Password Modal -->
<div class="modal-overlay" id="resetPassModal" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <span style="font-weight:700; color:#1e293b;" id="resetModalTitle">Reset Staff Password</span>
            <button onclick="document.getElementById('resetPassModal').style.display = 'none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form action="users.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">New Password *</label>
                    <input type="password" name="new_password" class="form-control" required placeholder="Enter new secure password">
                </div>
                <div style="font-size:12px; color:var(--text-muted);">
                    Users cannot change their own password in their profile. The new password entered here will be immediately active.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('resetPassModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetModal(userId, username) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetModalTitle').innerText = 'Reset Password for @' + username;
    document.getElementById('resetPassModal').style.display = 'flex';
}
</script>

<?php render_footer(); ?>
