<?php
/**
 * GRAVITY CRM - User Progress
 * Phase 1: Foundation
 * 
 * Yahan user apna progress, tasks, aur work status dekh sakta hai.
 * Authorized users/admins doosre users ka progress bhi dekh sakte hain.
 */

require_once __DIR__ . '/functions.php';
require_login();

$user = current_user();
$page_title = 'My Progress';
$active_page = 'user_progress';

// View another user's progress (admin only)
$view_user_id = (int)get('user', $user['id']);
if ($view_user_id !== (int)$user['id'] && !user_can('users', 'view')) {
    $view_user_id = (int)$user['id'];
}

$view_user = db_row('SELECT * FROM users WHERE id = ?', [$view_user_id]);
if (!$view_user) {
    $view_user = $user;
    $view_user_id = (int)$user['id'];
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'update_progress') {
        $progress_id = (int)post('progress_id', 0);
        $progress_value = max(0, min(100, (int)post('progress_value', 0)));
        $status = sanitize(post('status', 'in_progress'));
        
        $existing = db_row('SELECT * FROM user_progress WHERE id = ? AND user_id = ?', [$progress_id, $user['id']]);
        if ($existing) {
            db_execute('UPDATE user_progress SET progress_value = ?, status = ?, updated_by = ? WHERE id = ?', [$progress_value, $status, $user['id'], $progress_id]);
            log_activity('user_progress', 'update', $progress_id, 'Updated progress: ' . ($existing['progress_label'] ?: $existing['module']));
            flash('success', 'Progress updated successfully.');
        }
        redirect('user_progress.php');
    }
    
    if ($action === 'add_progress') {
        $module = sanitize(post('module'));
        $progress_label = sanitize(post('progress_label'));
        $progress_value = max(0, min(100, (int)post('progress_value', 0)));
        $status = sanitize(post('status', 'not_started'));
        
        db_execute(
            'INSERT INTO user_progress (user_id, module, progress_label, progress_value, status, updated_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$user['id'], $module, $progress_label, $progress_value, $status, $user['id']]
        );
        log_activity('user_progress', 'create', db_last_id(), 'Added progress: ' . $progress_label);
        flash('success', 'Progress item added successfully.');
        redirect('user_progress.php');
    }
    
    if ($action === 'delete_progress') {
        $progress_id = (int)post('progress_id', 0);
        $existing = db_row('SELECT * FROM user_progress WHERE id = ? AND user_id = ?', [$progress_id, $user['id']]);
        if ($existing) {
            db_execute('DELETE FROM user_progress WHERE id = ? AND user_id = ?', [$progress_id, $user['id']]);
            log_activity('user_progress', 'delete', $progress_id, 'Deleted progress: ' . ($existing['progress_label'] ?: $existing['module']));
            flash('success', 'Progress item deleted successfully.');
        }
        redirect('user_progress.php');
    }
}

// Get user progress
$user_progress = db_query('SELECT * FROM user_progress WHERE user_id = ? ORDER BY updated_at DESC', [$view_user_id]);

// Stats
$total_items = count($user_progress);
$completed = (int)db_value('SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND status = "completed"', [$view_user_id]);
$in_progress = (int)db_value('SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND status = "in_progress"', [$view_user_id]);
$blocked = (int)db_value('SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND status = "blocked"', [$view_user_id]);

// Get all users for admin view
$all_users = user_can('users', 'view') ? db_query('SELECT id, first_name, last_name FROM users WHERE status = "active" ORDER BY first_name') : [];
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-graph-up"></i> <?= $view_user_id === (int)$user['id'] ? 'My Progress' : e($view_user['first_name'] . ' ' . $view_user['last_name'] . '\'s Progress') ?></h1>
    <button class="btn-primary-custom" onclick="toggleNewProgress()"><i class="bi bi-plus"></i> Add Progress Item</button>
</div>

<?php if (!empty($all_users)): ?>
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <select name="user" class="form-control-custom" onchange="this.form.submit()">
                        <?php foreach ($all_users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $view_user_id == $u['id'] ? 'selected' : '' ?>>
                                <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-list-check"></i></div>
            <div class="stat-value"><?= $total_items ?></div>
            <div class="stat-label">Total Items</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-check-circle"></i></div>
            <div class="stat-value"><?= $completed ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value"><?= $in_progress ?></div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2;color:#991b1b;"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-value"><?= $blocked ?></div>
            <div class="stat-label">Blocked</div>
        </div>
    </div>
</div>

<!-- New Progress Form -->
<div class="card" id="newProgressForm" style="display:none;">
    <div class="card-header">Add Progress Item</div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_progress">
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Module</label>
                    <input type="text" name="module" class="form-control-custom" placeholder="e.g. clients" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Label</label>
                    <input type="text" name="progress_label" class="form-control-custom" placeholder="e.g. Complete client onboarding" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label-custom">Progress %</label>
                    <input type="number" name="progress_value" class="form-control-custom" min="0" max="100" value="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label-custom">Status</label>
                    <select name="status" class="form-control-custom">
                        <option value="not_started">Not Started</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="blocked">Blocked</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Add</button>
            <button type="button" class="btn-secondary-custom" onclick="toggleNewProgress()">Cancel</button>
        </form>
    </div>
</div>

<!-- Progress List -->
<div class="card">
    <div class="card-header">Progress Items (<?= $total_items ?>)</div>
    <div class="card-body">
        <?php if (empty($user_progress)): ?>
            <p class="text-muted">No progress items yet.</p>
        <?php else: ?>
            <?php foreach ($user_progress as $progress): ?>
                <div class="mb-4 p-3" style="border:1px solid #e5e7eb;border-radius:8px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= e($progress['progress_label'] ?: $progress['module']) ?></strong>
                            <span class="badge badge-<?= $progress['status'] === 'completed' ? 'success' : ($progress['status'] === 'blocked' ? 'danger' : ($progress['status'] === 'in_progress' ? 'primary' : 'secondary')) ?>">
                                <?= e(ucfirst(str_replace('_', ' ', $progress['status']))) ?>
                            </span>
                            <div class="text-muted" style="font-size:0.85rem;">Module: <?= e($progress['module']) ?></div>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($view_user_id === (int)$user['id']): ?>
                                <button class="btn-secondary-custom btn-sm" onclick="toggleEditProgress(<?= $progress['id'] ?>)"><i class="bi bi-pencil"></i></button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this item?')">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_progress">
                                    <input type="hidden" name="progress_id" value="<?= $progress['id'] ?>">
                                    <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-2">
                        <div class="d-flex justify-content-between">
                            <span style="font-size:0.85rem;">Progress</span>
                            <span style="font-size:0.85rem;"><?= $progress['progress_value'] ?>%</span>
                        </div>
                        <div class="progress" style="height:8px;background:#e5e7eb;border-radius:4px;">
                            <div class="progress-bar" style="width:<?= (int)$progress['progress_value'] ?>%;background:var(--primary);border-radius:4px;"></div>
                        </div>
                    </div>
                    
                    <div id="editProgress_<?= $progress['id'] ?>" style="display:none;margin-top:1rem;">
                        <form method="POST" action="" class="row g-2">
                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_progress">
                            <input type="hidden" name="progress_id" value="<?= $progress['id'] ?>">
                            <div class="col-md-4">
                                <input type="number" name="progress_value" class="form-control-custom" min="0" max="100" value="<?= $progress['progress_value'] ?>">
                            </div>
                            <div class="col-md-4">
                                <select name="status" class="form-control-custom">
                                    <option value="not_started" <?= $progress['status'] === 'not_started' ? 'selected' : '' ?>>Not Started</option>
                                    <option value="in_progress" <?= $progress['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= $progress['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="blocked" <?= $progress['status'] === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn-primary-custom btn-sm"><i class="bi bi-check"></i> Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleNewProgress() {
    const form = document.getElementById('newProgressForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function toggleEditProgress(id) {
    const form = document.getElementById('editProgress_' + id);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/footer.php'; ?>