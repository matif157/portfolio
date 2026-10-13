<?php
/**
 * GRAVITY CRM - Tasks
 * Phase 1: Foundation
 * 
 * Yahan user tasks/progress items manage hote hain.
 * (Phase 3 mein service automation se linked tasks aayenge)
 */

require_once __DIR__ . '/functions.php';
require_login();

$user = current_user();
$page_title = 'My Tasks';
$active_page = 'tasks';

// Filter
$status_filter = get('status', '');

$where = 'user_id = ?';
$params = [$user['id']];

if ($status_filter !== '') {
    $where .= ' AND status = ?';
    $params[] = $status_filter;
}

// Pagination
$per_page = (int)get_setting('items_per_page', 20);
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $per_page;

$total = (int)db_value("SELECT COUNT(*) FROM user_progress WHERE {$where}", $params);
$tasks = db_query(
    "SELECT * FROM user_progress WHERE {$where} ORDER BY 
     CASE status 
        WHEN 'blocked' THEN 1 
        WHEN 'in_progress' THEN 2 
        WHEN 'not_started' THEN 3 
        ELSE 4 
     END, updated_at DESC 
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);
$total_pages = max(1, ceil($total / $per_page));

// Stats
$pending = (int)db_value('SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND status != "completed"', [$user['id']]);
$blocked = (int)db_value('SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND status = "blocked"', [$user['id']]);
$completed_count = (int)db_value('SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND status = "completed"', [$user['id']]);
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-check2-square"></i> My Tasks</h1>
    <a href="user_progress.php" class="btn-primary-custom"><i class="bi bi-plus"></i> Manage Progress</a>
</div>

<!-- Stats -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-hourglass"></i></div>
            <div class="stat-value"><?= $pending ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2;color:#991b1b;"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-value"><?= $blocked ?></div>
            <div class="stat-label">Blocked</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-check-circle"></i></div>
            <div class="stat-value"><?= $completed_count ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <select name="status" class="form-control-custom" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="not_started" <?= $status_filter === 'not_started' ? 'selected' : '' ?>>Not Started</option>
                    <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="blocked" <?= $status_filter === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                </select>
            </div>
            <div class="col-md-8">
                <a href="tasks.php" class="btn-secondary-custom">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Tasks List -->
<div class="card">
    <div class="card-header">Tasks (<?= $total ?>)</div>
    <div class="card-body">
        <?php if (empty($tasks)): ?>
            <p class="text-muted">No tasks found.</p>
        <?php else: ?>
            <?php foreach ($tasks as $task): ?>
                <div class="p-3 mb-2" style="border:1px solid #e5e7eb;border-radius:8px;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= e($task['progress_label'] ?: $task['module']) ?></strong>
                            <span class="badge badge-<?= $task['status'] === 'completed' ? 'success' : ($task['status'] === 'blocked' ? 'danger' : ($task['status'] === 'in_progress' ? 'primary' : 'secondary')) ?>">
                                <?= e(ucfirst(str_replace('_', ' ', $task['status']))) ?>
                            </span>
                            <div class="text-muted" style="font-size:0.85rem;">Module: <?= e($task['module']) ?> | Updated: <?= e(time_ago($task['updated_at'])) ?></div>
                        </div>
                        <div>
                            <a href="user_progress.php" class="btn-secondary-custom btn-sm"><i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="progress" style="height:6px;background:#e5e7eb;border-radius:4px;">
                            <div class="progress-bar" style="width:<?= (int)$task['progress_value'] ?>%;background:var(--primary);border-radius:4px;"></div>
                        </div>
                        <small class="text-muted"><?= $task['progress_value'] ?>%</small>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?= pagination_links($total_pages, $page, 'tasks.php?' . http_build_query(['status' => $status_filter])) ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>