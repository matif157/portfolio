<?php
/**
 * GRAVITY CRM - Users Management
 * Phase 1: Foundation
 * 
 * Yahan admin users create/edit/delete kar sakta hai.
 * Password change bhi yahin se hota hai (users apna password khud change nahi kar sakte).
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('users', 'view');

$user = current_user();
$page_title = 'Users';
$active_page = 'users';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create' || $action === 'update') {
        require_permission('users', 'create');
        
        $user_id = (int)post('user_id', 0);
        $username = sanitize(post('username'));
        $email = sanitize(post('email'));
        $first_name = sanitize(post('first_name'));
        $last_name = sanitize(post('last_name'));
        $phone = sanitize(post('phone'));
        $role_id = (int)post('role_id', 0);
        $status = sanitize(post('status', 'active'));
        $password = post('password');
        $must_change = post('must_change') ? 1 : 0;
        
        $errors = [];
        validate_required($username, 'Username', $errors);
        validate_required($email, 'Email', $errors);
        validate_required($first_name, 'First name', $errors);
        validate_required($last_name, 'Last name', $errors);
        
        if (!validate_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        if ($role_id <= 0) {
            $errors[] = 'Please select a role.';
        }
        
        // Username duplicate check
        $exists = db_row('SELECT id FROM users WHERE username = ? AND id != ?', [$username, $user_id]);
        if ($exists) {
            $errors[] = 'This username is already in use.';
        }
        
        // Email duplicate check
        $exists = db_row('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $user_id]);
        if ($exists) {
            $errors[] = 'This email is already in use.';
        }
        
        // Password required for new users
        if ($action === 'create' && $password === '') {
            $errors[] = 'Password is required for new users.';
        }
        
        if (empty($errors)) {
            if ($action === 'create') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                db_execute(
                    'INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, phone, status, must_change_password) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$role_id, $username, $email, $hash, $first_name, $last_name, $phone, $status, $must_change]
                );
                $user_id = db_last_id();
                log_activity('users', 'create', $user_id, 'Created user: ' . $username);
                flash('success', 'User created successfully.');
            } else {
                $existing = db_row('SELECT * FROM users WHERE id = ?', [$user_id]);
                if (!$existing) {
                    flash('error', 'User not found.');
                    redirect('users.php');
                }
                
                db_execute(
                    'UPDATE users SET role_id = ?, username = ?, email = ?, first_name = ?, last_name = ?, phone = ?, status = ?, must_change_password = ? WHERE id = ?',
                    [$role_id, $username, $email, $first_name, $last_name, $phone, $status, $must_change, $user_id]
                );
                
                // Password update if provided
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    db_execute('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $user_id]);
                }
                
                log_activity('users', 'update', $user_id, 'Updated user: ' . $username);
                flash('success', 'User updated successfully.');
            }
            redirect('users.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete') {
        require_permission('users', 'delete');
        $user_id = (int)post('user_id', 0);
        
        if ($user_id === (int)$user['id']) {
            flash('error', 'You cannot delete your own account.');
            redirect('users.php');
        }
        
        $existing = db_row('SELECT * FROM users WHERE id = ?', [$user_id]);
        if (!$existing) {
            flash('error', 'User not found.');
            redirect('users.php');
        }
        
        // Soft delete - set status to suspended
        db_execute('UPDATE users SET status = "suspended" WHERE id = ?', [$user_id]);
        log_activity('users', 'delete', $user_id, 'Suspended user: ' . $existing['username']);
        flash('success', 'User suspended successfully.');
        redirect('users.php');
    }
    
    if ($action === 'reset_password') {
        require_permission('users', 'edit');
        $user_id = (int)post('user_id', 0);
        $new_password = post('new_password');
        
        if (strlen($new_password) < 8) {
            flash('error', 'Password must be at least 8 characters.');
            redirect('users.php');
        }
        
        $existing = db_row('SELECT * FROM users WHERE id = ?', [$user_id]);
        if (!$existing) {
            flash('error', 'User not found.');
            redirect('users.php');
        }
        
        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        db_execute('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?', [$hash, $user_id]);
        log_activity('users', 'update', $user_id, 'Password reset for user: ' . $existing['username']);
        flash('success', 'Password reset successfully. User must change it on next login.');
        redirect('users.php');
    }
    
    if ($action === 'unlock') {
        // Administrator release of a temporarily locked account (Spec §27)
        require_permission('users', 'edit');
        $user_id = (int)post('user_id', 0);
        
        if (!unlock_user_account($user_id)) {
            flash('error', 'User not found.');
            redirect('users.php');
        }
        
        flash('success', 'Account unlocked successfully.');
        redirect('users.php');
    }
}

// Get all users
$users = db_query(
    'SELECT u.*, r.name AS role_name 
     FROM users u JOIN roles r ON u.role_id = r.id 
     ORDER BY u.created_at DESC'
);

// Get all roles for dropdown
$roles = db_query('SELECT * FROM roles ORDER BY name');

// Edit user
$edit_user = null;
if (get('edit')) {
    $edit_user = db_row('SELECT * FROM users WHERE id = ?', [(int)get('edit')]);
}
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-people"></i> Users</h1>
    <button class="btn-primary-custom" onclick="toggleNewUser()"><i class="bi bi-plus"></i> New User</button>
</div>

<!-- New/Edit User Form -->
<div class="card" id="userForm" style="display:<?= $edit_user ? 'block' : 'none' ?>;">
    <div class="card-header">
        <?= $edit_user ? 'Edit User: ' . e($edit_user['username']) : 'Create New User' ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $edit_user ? 'update' : 'create' ?>">
            <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?? 0 ?>">
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Username *</label>
                    <input type="text" name="username" class="form-control-custom" value="<?= e($edit_user['username'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Email *</label>
                    <input type="email" name="email" class="form-control-custom" value="<?= e($edit_user['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Phone</label>
                    <input type="text" name="phone" class="form-control-custom" value="<?= e($edit_user['phone'] ?? '') ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">First Name *</label>
                    <input type="text" name="first_name" class="form-control-custom" value="<?= e($edit_user['first_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Last Name *</label>
                    <input type="text" name="last_name" class="form-control-custom" value="<?= e($edit_user['last_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Role *</label>
                    <select name="role_id" class="form-control-custom" required>
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>" <?= ($edit_user['role_id'] ?? '') == $role['id'] ? 'selected' : '' ?>>
                                <?= e($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Status</label>
                    <select name="status" class="form-control-custom">
                        <option value="active" <?= ($edit_user['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($edit_user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= ($edit_user['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom"><?= $edit_user ? 'New Password (leave blank to keep)' : 'Password *' ?></label>
                    <input type="password" name="password" class="form-control-custom" <?= $edit_user ? '' : 'required' ?>>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Force Password Change</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="must_change" value="1" class="form-check-input" id="mustChange" <?= ($edit_user['must_change_password'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="mustChange">User must change password on next login</label>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> <?= $edit_user ? 'Update' : 'Create' ?> User</button>
            <button type="button" class="btn-secondary-custom" onclick="toggleNewUser()">Cancel</button>
        </form>
    </div>
</div>

<!-- Users List -->
<div class="card">
    <div class="card-header">Users (<?= count($users) ?>)</div>
    <div class="card-body">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <img src="<?= e(profile_picture_url($u)) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;vertical-align:middle;margin-right:0.5rem;">
                            <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
                            <?php if ($u['id'] === (int)$user['id']): ?>
                                <span class="badge badge-primary">You</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($u['username']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e($u['role_name']) ?></td>
                        <td>
                            <?= status_badge($u['status']) ?>
                            <?php if (!empty($u['locked_until']) && strtotime((string)$u['locked_until']) > time()): ?>
                                <span class="badge" style="background:#dc3545;color:#fff;">Locked</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(format_datetime($u['last_login_at'])) ?></td>
                        <td>
                            <a href="users.php?edit=<?= $u['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                            <button class="btn-secondary-custom btn-sm" onclick="showResetPassword(<?= $u['id'] ?>, '<?= e($u['username']) ?>')"><i class="bi bi-key"></i></button>
                            <?php if ($u['id'] !== (int)$user['id'] && $u['status'] !== 'suspended'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Suspend this user?')">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-person-x"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if (user_can('users', 'edit') && !empty($u['locked_until']) && strtotime((string)$u['locked_until']) > time()): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Release the temporary lock for this user?')">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="unlock">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-secondary-custom btn-sm" title="Unlock account"><i class="bi bi-unlock"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetPasswordModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeResetPassword()">&times;</button>
        <h3><i class="bi bi-key"></i> Reset Password</h3>
        <p id="resetUserInfo"></p>
        <form method="POST" action="">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">
            <label class="form-label-custom">New Password</label>
            <input type="password" name="new_password" class="form-control-custom" required minlength="8">
            <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Reset Password</button>
            <button type="button" class="btn-secondary-custom" onclick="closeResetPassword()">Cancel</button>
        </form>
    </div>
</div>

<script>
function toggleNewUser() {
    const form = document.getElementById('userForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block' && !form.querySelector('input[name="user_id"]').value) {
        form.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]').forEach(i => i.value = '');
        form.querySelector('select[name="role_id"]').value = '';
        form.querySelector('select[name="status"]').value = 'active';
    }
}

function showResetPassword(userId, username) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUserInfo').textContent = 'Reset password for: ' + username;
    document.getElementById('resetPasswordModal').classList.add('show');
}

function closeResetPassword() {
    document.getElementById('resetPasswordModal').classList.remove('show');
}
</script>

<?php include __DIR__ . '/footer.php'; ?>