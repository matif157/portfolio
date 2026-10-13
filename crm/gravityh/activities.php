<?php
/**
 * GRAVITY CRM - Activities / Audit Log
 * Phase 1: Foundation
 * 
 * Yahan saari activities aur audit trail dikhti hai.
 * Filters: user, module, action, date.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('activities', 'view');

$user = current_user();
$page_title = 'Activities';
$active_page = 'activities';

// Filters
$where = '1=1';
$params = [];

$user_filter = (int)get('user', 0);
if ($user_filter > 0) {
    $where .= ' AND a.user_id = ?';
    $params[] = $user_filter;
}

$module_filter = get('module', '');
if ($module_filter !== '') {
    $where .= ' AND a.module = ?';
    $params[] = $module_filter;
}

$action_filter = get('action', '');
if ($action_filter !== '') {
    $where .= ' AND a.action = ?';
    $params[] = $action_filter;
}

$date_from = get('date_from', '');
if ($date_from !== '') {
    $where .= ' AND DATE(a.created_at) >= ?';
    $params[] = $date_from;
}

$date_to = get('date_to', '');
if ($date_to !== '') {
    $where .= ' AND DATE(a.created_at) <= ?';
    $params[] = $date_to;
}

$sensitive_only = get('sensitive', 0);
if ($sensitive_only) {
    $where .= ' AND a.is_sensitive = 1';
}

// Pagination
$per_page = (int)get_setting('items_per_page', 20);
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $per_page;

$total = (int)db_value("SELECT COUNT(*) FROM activities a WHERE {$where}", $params);
$activities = db_query(
    "SELECT a.*, u.first_name, u.last_name 
     FROM activities a 
     LEFT JOIN users u ON a.user_id = u.id 
     WHERE {$where} 
     ORDER BY a.created_at DESC 
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);
$total_pages = max(1, ceil($total / $per_page));

// Get users for filter
$users = db_query('SELECT id, first_name, last_name FROM users ORDER BY first_name');

// Get distinct modules
$modules = db_query('SELECT DISTINCT module FROM activities ORDER BY module');

// Get distinct actions
$actions = db_query('SELECT DISTINCT action FROM activities ORDER BY action');

// Stats
$total_activities = (int)db_value('SELECT COUNT(*) FROM activities');
$sensitive_activities = (int)db_value('SELECT COUNT(*) FROM activities WHERE is_sensitive = 1');
$today_activities = (int)db_value('SELECT COUNT(*) FROM activities WHERE DATE(created_at) = CURDATE()');
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-clock-history"></i> Activities</h1>
</div>

<!-- Stats -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-clock-history"></i></div>
            <div class="stat-value"><?= $total_activities ?></div>
            <div class="stat-label">Total Activities</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-value"><?= $today_activities ?></div>
            <div class="stat-label">Today</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2;color:#991b1b;"><i class="bi bi-shield-exclamation"></i></div>
            <div class="stat-value"><?= $sensitive_activities ?></div>
            <div class="stat-label">Sensitive Access</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-2">
                <select name="user" class="form-control-custom">
                    <option value="0">All Users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $user_filter == $u['id'] ? 'selected' : '' ?>>
                            <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="module" class="form-control-custom">
                    <option value="">All Modules</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= e($m['module']) ?>" <?= $module_filter === $m['module'] ? 'selected' : '' ?>>
                            <?= e(ucfirst($m['module'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="action" class="form-control-custom">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= e($a['action']) ?>" <?= $action_filter === $a['action'] ? 'selected' : '' ?>>
                            <?= e(ucfirst($a['action'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control-custom" value="<?= e($date_from) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control-custom" value="<?= e($date_to) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Filter</button>
                <a href="activities.php" class="btn-secondary-custom">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Activities Table -->
<div class="card">
    <div class="card-header">Activities (<?= $total ?>)</div>
    <div class="card-body">
        <?php if (empty($activities)): ?>
            <p class="text-muted">No activities found.</p>
        <?php else: ?>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th>IP</th>
                        <th>Sensitive</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity): ?>
                        <tr>
                            <td><?= e($activity['first_name'] ? $activity['first_name'] . ' ' . $activity['last_name'] : 'System') ?></td>
                            <td><span class="badge badge-info"><?= e(ucfirst($activity['action'])) ?></span></td>
                            <td><?= e(ucfirst($activity['module'])) ?></td>
                            <td><?= e(truncate($activity['description'] ?? '', 80)) ?></td>
                            <td><?= e($activity['ip_address'] ?: '-') ?></td>
                            <td><?= $activity['is_sensitive'] ? '<span class="badge badge-danger">Sensitive</span>' : '-' ?></td>
                            <td><?= e(format_datetime($activity['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?= pagination_links($total_pages, $page, 'activities.php?' . http_build_query(array_filter(['user' => $user_filter, 'module' => $module_filter, 'action' => $action_filter, 'date_from' => $date_from, 'date_to' => $date_to]))) ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>