<?php
/**
 * GRAVITY CRM — User Profile & Workspace Management
 * 
 * Yahan user apni entered data dekh sakta hai, tasks and work progress track kar sakta hai,
 * apni profile picture upload kar sakta hai, aur personal email SMTP configure kar sakta hai.
 * NOTE: Security policy ke mutabiq user apna password yahan khud tabdeel nahi kar sakta (Admin controlled).
 */

require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();
$user_id = $user['id'];

// Handle Profile Photo Upload & Personal Email Configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile_info') {
        $full_name = trim($_POST['full_name'] ?? '');
        $use_personal_email = isset($_POST['use_personal_email']) ? 1 : 0;
        $user_email_smtp_host = trim($_POST['user_email_smtp_host'] ?? '');
        $user_email_smtp_port = trim($_POST['user_email_smtp_port'] ?? '587');
        $user_email_user = trim($_POST['user_email_user'] ?? '');
        $user_email_pass = trim($_POST['user_email_pass'] ?? '');

        // Avatar Image File Upload Handling
        $avatar_path = $user['avatar'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_name = $_FILES['avatar']['name'];
            $file_size = $_FILES['avatar']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                set_flash('danger', 'Invalid image format. Allowed formats: JPG, PNG, WEBP.');
                redirect('profile.php');
            }

            if ($file_size > 5 * 1024 * 1024) {
                set_flash('danger', 'Profile picture size must not exceed 5MB.');
                redirect('profile.php');
            }

            $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
            $destination = AVATAR_DIR . $new_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                $avatar_path = 'uploads/avatars/' . $new_filename;
            }
        }

        // Database Update Query using PDO Prepared Statements
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, avatar = ?, use_personal_email = ?, user_email_smtp_host = ?, 
                user_email_smtp_port = ?, user_email_user = ?, user_email_pass = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $full_name ?: $user['full_name'],
            $avatar_path,
            $use_personal_email,
            $user_email_smtp_host,
            $user_email_smtp_port,
            $user_email_user,
            $user_email_pass ?: $user['user_email_pass'],
            $user_id
        ]);

        log_activity('Update Profile', 'users', $user_id, "User {$user['username']} updated profile settings & picture", 'stage1_normal');
        set_flash('success', 'Profile and personal email configuration updated successfully!');
        redirect('profile.php');
    }
}

// Re-fetch fresh user info
$stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// 1. Fetch User Assigned Tasks
$stmtTasks = $pdo->prepare("
    SELECT t.*, c.first_name, c.last_name, c.company_name, p.title as project_title
    FROM tasks t
    LEFT JOIN clients c ON t.client_id = c.id
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ?
    ORDER BY CASE WHEN t.status = 'pending' THEN 1 WHEN t.status = 'in_progress' THEN 2 ELSE 3 END, t.due_date ASC
");
$stmtTasks->execute([$user_id]);
$user_tasks = $stmtTasks->fetchAll();

// Task Metrics
$total_tasks = count($user_tasks);
$completed_tasks = count(array_filter($user_tasks, fn($t) => $t['status'] === 'completed'));
$pending_tasks = count(array_filter($user_tasks, fn($t) => $t['status'] === 'pending'));
$in_progress_tasks = count(array_filter($user_tasks, fn($t) => $t['status'] === 'in_progress'));
$progress_pct = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 100;

// 2. Fetch User Entered Data (Clients entered/assigned to this user)
$stmtClients = $pdo->prepare("
    SELECT c.*, (SELECT COUNT(*) FROM businesses WHERE client_id = c.id) as business_count
    FROM clients c 
    WHERE c.assigned_staff_id = ?
    ORDER BY c.created_at DESC LIMIT 20
");
$stmtClients->execute([$user_id]);
$entered_clients = $stmtClients->fetchAll();

// 3. Fetch User Personal Templates
$stmtTemplates = $pdo->prepare("
    SELECT * FROM user_templates WHERE user_id = ? ORDER BY updated_at DESC
");
$stmtTemplates->execute([$user_id]);
$personal_templates = $stmtTemplates->fetchAll();

// 4. Fetch User Recent Activity History
$stmtActivities = $pdo->prepare("
    SELECT * FROM activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 25
");
$stmtActivities->execute([$user_id]);
$user_activities = $stmtActivities->fetchAll();

render_header('User Profile & Work Dashboard', 'profile');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">?? My Profile &amp; Workspace</h1>
        <p class="page-subtitle">Personal overview, entered records, assigned work, progress tracking, and templates</p>
    </div>
    <div>
        <a href="personal_templates.php" class="btn btn-secondary">?? Manage Personal Templates</a>
        <a href="tasks.php?filter=my_tasks" class="btn btn-primary">? Go to Tasks</a>
    </div>
</div>

<div class="form-row" style="align-items:flex-start; margin-bottom:24px;">
    <!-- Profile Card & Avatar Upload -->
    <div style="flex:1; min-width:320px; max-width:380px;">
        <div class="card" style="text-align:center;">
            <div style="position:relative; width:110px; height:110px; margin:0 auto 16px;">
                <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
                    <img src="<?= e($user['avatar']) ?>" style="width:110px; height:110px; border-radius:50%; object-fit:cover; border:3px solid var(--accent); box-shadow:var(--shadow-md);" alt="Avatar">
                <?php else: ?>
                    <div style="width:110px; height:110px; border-radius:50%; background:linear-gradient(135deg, #2563eb, #3b82f6); color:#fff; display:flex; align-items:center; justify-content:center; font-size:40px; font-weight:700; border:3px solid var(--accent); margin:0 auto;">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <h2 style="font-size:18px; font-weight:700; color:var(--secondary); margin-bottom:2px;"><?= e($user['full_name']) ?></h2>
            <div style="color:var(--text-muted); font-size:13px; margin-bottom:8px;"><code>@<?= e($user['username']) ?></code></div>
            <div>
                <span class="badge badge-info"><?= e($user['role_name']) ?></span>
                <span class="badge badge-success"><?= ucfirst($user['status']) ?></span>
            </div>

            <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border-color); text-align:left; font-size:13px;">
                <div style="margin-bottom:8px;"><strong>Email:</strong> <?= e($user['email']) ?></div>
                <div style="margin-bottom:8px;"><strong>Role Access:</strong> <?= e($user['role_name']) ?></div>
                <div style="margin-bottom:8px;"><strong>Member Since:</strong> <?= format_date($user['created_at']) ?></div>
            </div>

            <!-- Password Security Notice -->
            <div style="margin-top:20px; padding:12px; background:#fffaf0; border:1px solid #fed7aa; border-radius:6px; font-size:12px; color:#9a3412; text-align:left;">
                ?? <strong>Password Policy Notice:</strong><br>
                For enterprise security compliance, users are not permitted to change their own password. Please contact your CRM Administrator for password resets.
            </div>
        </div>

        <!-- Personal Email SMTP Settings Card -->
        <div class="card">
            <div class="card-title">?? My Outbound Email Configuration</div>
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile_info">

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Upload Profile Picture (JPG, PNG, WEBP)</label>
                    <input type="file" name="avatar" class="form-control" accept="image/*">
                </div>

                <div class="form-group" style="padding-top:10px; border-top:1px solid var(--border-color);">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:600; cursor:pointer;">
                        <input type="checkbox" name="use_personal_email" value="1" <?= !empty($user['use_personal_email']) ? 'checked' : '' ?>>
                        <span>Use My Personal Email for Communications</span>
                    </label>
                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                        When checked and permitted by administrator, outgoing emails will be sent via your configured SMTP credentials.
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Personal SMTP Host</label>
                    <input type="text" name="user_email_smtp_host" value="<?= e($user['user_email_smtp_host'] ?? '') ?>" class="form-control" placeholder="e.g. smtp.office365.com or smtp.gmail.com">
                </div>

                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label">SMTP Port</label>
                        <input type="text" name="user_email_smtp_port" value="<?= e($user['user_email_smtp_port'] ?? '587') ?>" class="form-control">
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label">SMTP Username</label>
                        <input type="text" name="user_email_user" value="<?= e($user['user_email_user'] ?? '') ?>" class="form-control" placeholder="user@company.com">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">SMTP Password / App Key</label>
                    <input type="password" name="user_email_pass" value="<?= e($user['user_email_pass'] ?? '') ?>" class="form-control" placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">Save Profile &amp; Settings</button>
            </form>
        </div>
    </div>

    <!-- Main Content Area: Progress, Assigned Work, Entered Data, Templates, Activity -->
    <div style="flex:2; min-width:340px;">
        <!-- Work & Task Progress Overview -->
        <div class="card">
            <div class="card-title">
                <span>?? My Work Status &amp; Progress Tree</span>
                <span class="badge badge-info"><?= $progress_pct ?>% Completed</span>
            </div>
            
            <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom:16px;">
                <div class="stat-card" style="padding:14px;">
                    <div class="stat-label">Total Assigned</div>
                    <div class="stat-value" style="font-size:22px;"><?= $total_tasks ?></div>
                    <div class="stat-desc">All assigned tasks</div>
                </div>
                <div class="stat-card" style="padding:14px;">
                    <div class="stat-label">In Progress</div>
                    <div class="stat-value" style="font-size:22px; color:var(--primary);"><?= $in_progress_tasks ?></div>
                    <div class="stat-desc">Currently working</div>
                </div>
                <div class="stat-card" style="padding:14px;">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value" style="font-size:22px; color:var(--warning);"><?= $pending_tasks ?></div>
                    <div class="stat-desc">Waiting to start</div>
                </div>
                <div class="stat-card" style="padding:14px;">
                    <div class="stat-label">Completed</div>
                    <div class="stat-value" style="font-size:22px; color:var(--success);"><?= $completed_tasks ?></div>
                    <div class="stat-desc">Successfully closed</div>
                </div>
            </div>

            <div style="margin-top:10px;">
                <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                    <span>Overall Completion Rate</span>
                    <strong><?= $completed_tasks ?> of <?= $total_tasks ?> tasks done</strong>
                </div>
                <div style="background:#e2e8f0; height:10px; border-radius:5px; overflow:hidden;">
                    <div style="background:linear-gradient(90deg, #2563eb, #10b981); height:100%; width:<?= $progress_pct ?>%; transition:width 0.3s;"></div>
                </div>
            </div>
        </div>

        <!-- Assigned Tasks & Work Items -->
        <div class="card">
            <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                <span>? My Assigned Work &amp; Action Items</span>
                <span class="badge badge-secondary"><?= count($user_tasks) ?> Items</span>
            </div>

            <?php if (empty($user_tasks)): ?>
                <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">
                    No pending tasks assigned to you at this moment.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Task Title</th>
                                <th>Related Client / Project</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_tasks as $task): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($task['title']) ?></strong>
                                        <?php if (!empty($task['description'])): ?>
                                            <div style="font-size:11px; color:var(--text-muted);"><?= e(substr($task['description'], 0, 60)) ?>...</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($task['first_name'])): ?>
                                            <?= e($task['first_name'] . ' ' . $task['last_name']) ?>
                                        <?php elseif (!empty($task['project_title'])): ?>
                                            <?= e($task['project_title']) ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">General</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $task['priority'] === 'high' ? 'danger' : ($task['priority'] === 'medium' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst($task['priority']) ?>
                                        </span>
                                    </td>
                                    <td><?= format_date($task['due_date']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $task['status'] === 'completed' ? 'success' : ($task['status'] === 'in_progress' ? 'info' : 'warning') ?>">
                                            <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Entered Clients & Assigned Portfolio -->
        <div class="card">
            <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                <span>?? My Entered / Assigned Clients (<?= count($entered_clients) ?>)</span>
                <a href="add_client.php" class="btn btn-sm btn-primary">+ Add New Client</a>
            </div>

            <?php if (empty($entered_clients)): ?>
                <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">
                    No clients assigned or entered yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Client #</th>
                                <th>Client Name</th>
                                <th>Company / Businesses</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entered_clients as $cl): ?>
                                <tr>
                                    <td><code><?= e($cl['client_number']) ?></code></td>
                                    <td><strong><?= e($cl['first_name'] . ' ' . $cl['last_name']) ?></strong></td>
                                    <td>
                                        <?= e($cl['company_name'] ?: '—') ?>
                                        <?php if ($cl['business_count'] > 0): ?>
                                            <span class="badge badge-info" style="font-size:10px;"><?= $cl['business_count'] ?> Entities</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($cl['email']) ?></td>
                                    <td><span class="badge badge-<?= $cl['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($cl['status']) ?></span></td>
                                    <td><a href="client_profile.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-secondary">Profile ?</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Personal Templates Quick Summary -->
        <div class="card">
            <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                <span>?? My Personal Templates</span>
                <a href="personal_templates.php" class="btn btn-sm btn-secondary">Open Template Studio ?</a>
            </div>

            <?php if (empty($personal_templates)): ?>
                <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">
                    You have not created any personal templates yet. <a href="personal_templates.php">Create Word, Excel, or Notepad templates</a>.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Template Title</th>
                                <th>Format Type</th>
                                <th>Last Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($personal_templates, 0, 5) as $tpl): ?>
                                <tr>
                                    <td><strong><?= e($tpl['title']) ?></strong></td>
                                    <td>
                                        <?php if ($tpl['template_type'] === 'word'): ?>
                                            <span class="badge badge-info">?? Word (.docx)</span>
                                        <?php elseif ($tpl['template_type'] === 'excel'): ?>
                                            <span class="badge badge-success">?? Excel (.csv)</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">?? Notepad / Text</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= format_datetime($tpl['updated_at']) ?></td>
                                    <td><a href="personal_templates.php?id=<?= $tpl['id'] ?>" class="btn btn-sm btn-secondary">Open</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- User Recent Activity Logs -->
        <div class="card">
            <div class="card-title">?? My Recent Activity History</div>
            <?php if (empty($user_activities)): ?>
                <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">
                    No activities recorded yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_activities as $act): ?>
                                <tr>
                                    <td><span style="font-size:12px; color:var(--text-muted);"><?= format_datetime($act['created_at']) ?></span></td>
                                    <td><strong><?= e($act['action']) ?></strong></td>
                                    <td><span class="badge badge-info"><?= e($act['module']) ?></span></td>
                                    <td><?= e($act['description']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php render_footer(); ?>
