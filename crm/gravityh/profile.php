<?php
/**
 * GRAVITY CRM - User Profile
 * Phase 1: Foundation
 * 
 * Yahan user apna profile dekh sakta hai:
 * - Apni entered data
 * - Apne tasks
 * - Apni activities
 * - Assigned work
 * - Profile picture upload
 * - Progress
 * 
 * NOTE: User apna password change NAHI kar sakta.
 * Password sirf admin users.php se change kar sakta hai.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('profile', 'view');

$user = current_user();
$page_title = 'My Profile';
$active_page = 'profile';

// Force password change notice (first login)
$force_change = get('force_change', 0);

// Profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf();
    
    if ($_POST['action'] === 'upload_picture') {
        require_permission('profile', 'edit');
        
        $upload = handle_upload('profile_picture', 'profiles');
        if ($upload[0]) {
            // Purani picture delete karo agar ho
            if ($user['profile_picture'] && file_exists(UPLOAD_PATH . '/' . $user['profile_picture'])) {
                unlink(UPLOAD_PATH . '/' . $user['profile_picture']);
            }
            
            db_execute('UPDATE users SET profile_picture = ? WHERE id = ?', [$upload[1], $user['id']]);
            log_activity('profile', 'update', $user['id'], 'Profile picture updated');
            flash('success', 'Profile picture updated successfully.');
        } else {
            flash('error', $upload[2]);
        }
        redirect('profile.php');
    }
    
    if ($_POST['action'] === 'update_profile') {
        require_permission('profile', 'edit');
        
        $first_name = sanitize(post('first_name'));
        $last_name = sanitize(post('last_name'));
        $email = sanitize(post('email'));
        $phone = sanitize(post('phone'));
        
        $errors = [];
        validate_required($first_name, 'First name', $errors);
        validate_required($last_name, 'Last name', $errors);
        validate_required($email, 'Email', $errors);
        
        if (!validate_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        // Email duplicate check
        $exists = db_row('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $user['id']]);
        if ($exists) {
            $errors[] = 'This email is already in use.';
        }
        
        if (empty($errors)) {
            $changes = [];
            if ($user['first_name'] !== $first_name) $changes['first_name'] = ['old' => $user['first_name'], 'new' => $first_name];
            if ($user['last_name'] !== $last_name) $changes['last_name'] = ['old' => $user['last_name'], 'new' => $last_name];
            if ($user['email'] !== $email) $changes['email'] = ['old' => $user['email'], 'new' => $email];
            if ($user['phone'] !== $phone) $changes['phone'] = ['old' => $user['phone'], 'new' => $phone];
            
            db_execute('UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?', [$first_name, $last_name, $email, $phone, $user['id']]);
            log_activity('profile', 'update', $user['id'], 'Profile updated', false, $changes);
            flash('success', 'Profile updated successfully.');
            redirect('profile.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
}

// User data refresh karo
$user = current_user();

// User stats
$total_clients = (int)db_value('SELECT COUNT(*) FROM clients WHERE created_by = ?', [$user['id']]);
$total_leads = (int)db_value('SELECT COUNT(*) FROM leads WHERE created_by = ?', [$user['id']]);
$total_activities = (int)db_value('SELECT COUNT(*) FROM activities WHERE user_id = ?', [$user['id']]);

// User tasks/progress
$user_progress = db_query('SELECT * FROM user_progress WHERE user_id = ? ORDER BY updated_at DESC LIMIT 20', [$user['id']]);

// Recent activities
$recent_activities = db_query('SELECT * FROM activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 10', [$user['id']]);

// Assigned work (clients assigned to this user)
$assigned_clients = db_query('SELECT id, first_name, last_name, email, phone, status FROM clients WHERE assigned_user_id = ? ORDER BY created_at DESC LIMIT 10', [$user['id']]);

// User email config
$email_config = db_row('SELECT * FROM user_email_configs WHERE user_id = ?', [$user['id']]);
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-person-circle"></i> My Profile</h1>
</div>

<?php if ($force_change): ?>
    <div class="alert alert-warning">
        <strong>Security Notice:</strong> You must change your password. Please contact your administrator to set a new password.
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Card -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <img src="<?= e(profile_picture_url($user)) ?>" alt="Profile" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid var(--primary);margin-bottom:1rem;">
                <h3><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                <p class="text-muted"><?= e($user['role_name']) ?></p>
                <p><span class="badge badge-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e(ucfirst($user['status'])) ?></span></p>
                
                <hr>
                
                <form method="POST" enctype="multipart/form-data" style="text-align:left;">
                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="upload_picture">
                    <div class="form-group">
                        <label class="form-label-custom">Upload Profile Picture</label>
                        <input type="file" name="profile_picture" class="form-control-custom" accept="image/jpeg,image/png,image/gif" required>
                    </div>
                    <button type="submit" class="btn-primary-custom w-100"><i class="bi bi-upload"></i> Upload</button>
                </form>
            </div>
        </div>
        
        <!-- User Email Config -->
        <div class="card">
            <div class="card-header">Email Configuration</div>
            <div class="card-body">
                <?php if ($email_config && $email_config['is_active']): ?>
                    <p><strong>SMTP Host:</strong> <?= e($email_config['smtp_host']) ?></p>
                    <p><strong>From:</strong> <?= e($email_config['from_name'] . ' <' . $email_config['from_email'] . '>') ?></p>
                    <p><span class="badge badge-success">Active</span></p>
                <?php else: ?>
                    <p class="text-muted">No personal email configuration set. System email will be used.</p>
                <?php endif; ?>
                <a href="profile_email.php" class="btn-secondary-custom btn-sm"><i class="bi bi-envelope"></i> Configure Email</a>
            </div>
        </div>
    </div>
    
    <!-- Profile Details -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                Personal Information
                <button class="btn-secondary-custom btn-sm" onclick="toggleEditProfile()"><i class="bi bi-pencil"></i> Edit</button>
            </div>
            <div class="card-body">
                <form method="POST" id="profileForm" style="display:none;">
                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label-custom">First Name</label>
                            <input type="text" name="first_name" class="form-control-custom" value="<?= e($user['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Last Name</label>
                            <input type="text" name="last_name" class="form-control-custom" value="<?= e($user['last_name']) ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label-custom">Email</label>
                            <input type="email" name="email" class="form-control-custom" value="<?= e($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Phone</label>
                            <input type="text" name="phone" class="form-control-custom" value="<?= e($user['phone']) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label-custom">Username</label>
                            <input type="text" class="form-control-custom" value="<?= e($user['username']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Role</label>
                            <input type="text" class="form-control-custom" value="<?= e($user['role_name']) ?>" disabled>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> You cannot change your own password. Please contact your administrator.
                    </div>
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Save Changes</button>
                    <button type="button" class="btn-secondary-custom" onclick="toggleEditProfile()">Cancel</button>
                </form>
                
                <div id="profileView">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Username:</strong> <?= e($user['username']) ?></p>
                            <p><strong>Email:</strong> <?= e($user['email']) ?></p>
                            <p><strong>Phone:</strong> <?= e($user['phone'] ?: 'Not set') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Role:</strong> <?= e($user['role_name']) ?></p>
                            <p><strong>Last Login:</strong> <?= e(format_datetime($user['last_login_at'])) ?></p>
                            <p><strong>Member Since:</strong> <?= e(format_date($user['created_at'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-people"></i></div>
                    <div class="stat-value"><?= $total_clients ?></div>
                    <div class="stat-label">Clients Created</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-lightning"></i></div>
                    <div class="stat-value"><?= $total_leads ?></div>
                    <div class="stat-label">Leads Created</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-clock-history"></i></div>
                    <div class="stat-value"><?= $total_activities ?></div>
                    <div class="stat-label">Activities</div>
                </div>
            </div>
        </div>
        
        <!-- Assigned Work -->
        <div class="card">
            <div class="card-header">
                Assigned Work
                <a href="clients.php" class="btn-secondary-custom btn-sm">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($assigned_clients)): ?>
                    <p class="text-muted">No clients assigned to you.</p>
                <?php else: ?>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_clients as $client): ?>
                                <tr>
                                    <td><a href="clients.php?view=<?= $client['id'] ?>"><?= e($client['first_name'] . ' ' . $client['last_name']) ?></a></td>
                                    <td><?= e($client['email']) ?></td>
                                    <td><?= e($client['phone']) ?></td>
                                    <td><?= status_badge($client['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- My Progress / Tasks -->
        <div class="card">
            <div class="card-header">
                My Progress
                <a href="user_progress.php" class="btn-secondary-custom btn-sm">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($user_progress)): ?>
                    <p class="text-muted">No progress records yet.</p>
                <?php else: ?>
                    <?php foreach ($user_progress as $progress): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <strong><?= e($progress['progress_label'] ?? $progress['module']) ?></strong>
                                <span><?= e($progress['progress_value']) ?>%</span>
                            </div>
                            <div class="progress" style="height:8px;background:#e5e7eb;border-radius:4px;">
                                <div class="progress-bar" style="width:<?= (int)$progress['progress_value'] ?>%;background:var(--primary);border-radius:4px;"></div>
                            </div>
                            <small class="text-muted"><?= status_badge($progress['status']) ?> Updated: <?= e(time_ago($progress['updated_at'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="card">
            <div class="card-header">
                Recent Activities
                <a href="activities.php" class="btn-secondary-custom btn-sm">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                    <p class="text-muted">No activities yet.</p>
                <?php else: ?>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Description</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activities as $activity): ?>
                                <tr>
                                    <td><?= e(ucfirst($activity['action'])) ?></td>
                                    <td><?= e(ucfirst($activity['module'])) ?></td>
                                    <td><?= e(truncate($activity['description'] ?? '', 60)) ?></td>
                                    <td><?= e(time_ago($activity['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleEditProfile() {
    document.getElementById('profileForm').style.display = document.getElementById('profileForm').style.display === 'none' ? 'block' : 'none';
    document.getElementById('profileView').style.display = document.getElementById('profileView').style.display === 'none' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/footer.php'; ?>