<?php
/**
 * GRAVITY CRM - Notifications
 * Phase 1: Foundation
 * 
 * Yahan user ki saari notifications dikhti hain.
 * Mark as read, delete, aur filter available hai.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('notifications', 'view');

$user = current_user();
$page_title = 'Notifications';
$active_page = 'notifications';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'mark_read') {
        $notif_id = (int)post('notif_id', 0);
        db_execute('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$notif_id, $user['id']]);
        redirect('notifications.php');
    }
    
    if ($action === 'mark_all_read') {
        db_execute('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$user['id']]);
        flash('success', 'All notifications marked as read.');
        redirect('notifications.php');
    }
    
    if ($action === 'delete') {
        $notif_id = (int)post('notif_id', 0);
        db_execute('DELETE FROM notifications WHERE id = ? AND user_id = ?', [$notif_id, $user['id']]);
        flash('success', 'Notification deleted.');
        redirect('notifications.php');
    }
}

// Filters
$where = 'user_id = ?';
$params = [$user['id']];

$status_filter = get('status', '');
if ($status_filter === 'unread') {
    $where .= ' AND is_read = 0';
} elseif ($status_filter === 'read') {
    $where .= ' AND is_read = 1';
}

// Pagination
$per_page = (int)get_setting('items_per_page', 20);
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $per_page;

$total = (int)db_value("SELECT COUNT(*) FROM notifications WHERE {$where}", $params);
$notifications = db_query(
    "SELECT * FROM notifications WHERE {$where} ORDER BY created_at DESC LIMIT {$per_page} OFFSET {$offset}",
    $params
);
$total_pages = max(1, ceil($total / $per_page));

$unread_count = unread_notification_count();
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-bell"></i> Notifications</h1>
    <div>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn-secondary-custom"><i class="bi bi-check-all"></i> Mark All Read</button>
        </form>
    </div>
</div>

<!-- Stats -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-bell"></i></div>
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-label">Total Notifications</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-bell-fill"></i></div>
            <div class="stat-value"><?= $unread_count ?></div>
            <div class="stat-label">Unread</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <select name="status" class="form-control-custom">
                    <option value="">All</option>
                    <option value="unread" <?= $status_filter === 'unread' ? 'selected' : '' ?>>Unread</option>
                    <option value="read" <?= $status_filter === 'read' ? 'selected' : '' ?>>Read</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Filter</button>
                <a href="notifications.php" class="btn-secondary-custom">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Notifications List -->
<div class="card">
    <div class="card-header">Notifications (<?= $total ?>)</div>
    <div class="card-body">
        <?php if (empty($notifications)): ?>
            <p class="text-muted">No notifications found.</p>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="d-flex justify-content-between align-items-start p-3 <?= $notif['is_read'] ? '' : 'bg-light' ?>" style="border-bottom:1px solid #f3f4f6;">
                    <div>
                        <strong><?= e($notif['title']) ?></strong>
                        <?php if (!$notif['is_read']): ?>
                            <span class="badge badge-primary">New</span>
                        <?php endif; ?>
                        <div class="text-muted" style="font-size:0.9rem;"><?= e($notif['message'] ?: '') ?></div>
                        <small class="text-muted"><?= e(format_datetime($notif['created_at'])) ?></small>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if (!$notif['is_read']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notif_id" value="<?= $notif['id'] ?>">
                                <button type="submit" class="btn-secondary-custom btn-sm"><i class="bi bi-check"></i></button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this notification?')">
                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="notif_id" value="<?= $notif['id'] ?>">
                            <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?= pagination_links($total_pages, $page, 'notifications.php?' . http_build_query(['status' => $status_filter])) ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>