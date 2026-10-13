<?php
/**
 * SPS Professional Services CRM
 * Notifications & Activity Center (Spec Section 14)
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

// Mark all as read
if (isset($_GET['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL")->execute([$_SESSION['user_id']]);
    set_flash('success', 'Notifications marked as read.');
    redirect('notifications.php');
}

$stage_filter = trim($_GET['stage'] ?? '');
$module_filter = trim($_GET['module'] ?? '');

$query = "
    SELECT n.*, u.full_name
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    WHERE 1=1
";
$params = [];
if ($stage_filter !== '') {
    $query .= " AND n.stage = ?";
    $params[] = $stage_filter;
}
if ($module_filter !== '') {
    $query .= " AND n.module = ?";
    $params[] = $module_filter;
}
$query .= " ORDER BY n.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

render_header('Notifications Center', 'communications');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Notifications & Activity Center</h1>
        <p class="page-subtitle">Real-time system alerts, missing statement triggers, and workflow progress notifications</p>
    </div>
    <div>
        <a href="notifications.php?mark_read=1" class="btn btn-secondary">✓ Mark All as Read</a>
    </div>
</div>

<div class="card">
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="notifications.php" class="btn btn-sm <?= empty($stage_filter) ? 'btn-primary' : 'btn-secondary' ?>">All Alerts</a>
        <a href="notifications.php?stage=warning" class="btn btn-sm <?= $stage_filter === 'warning' ? 'btn-primary' : 'btn-secondary' ?>">Warnings</a>
        <a href="notifications.php?stage=info" class="btn btn-sm <?= $stage_filter === 'info' ? 'btn-primary' : 'btn-secondary' ?>">System Info</a>
        <a href="notifications.php?module=bank_statements" class="btn btn-sm <?= $module_filter === 'bank_statements' ? 'btn-primary' : 'btn-secondary' ?>">Bank Statements</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Alert Title</th>
                    <th>Module</th>
                    <th>Notification Message</th>
                    <th>Stage</th>
                    <th>Timestamp</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($notifications)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No notifications found.</td></tr>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <tr <?= !$n['is_read'] ? 'style="background:#f0f9ff;"' : '' ?>>
                            <td><strong><?= e($n['title']) ?></strong></td>
                            <td><span class="badge badge-info"><?= e($n['module']) ?></span></td>
                            <td><?= e($n['message']) ?></td>
                            <td><span class="badge badge-<?= $n['stage'] === 'warning' ? 'danger' : 'secondary' ?>"><?= ucfirst($n['stage']) ?></span></td>
                            <td><?= format_datetime($n['created_at']) ?></td>
                            <td>
                                <?php if ($n['link']): ?>
                                    <a href="<?= e($n['link']) ?>" class="btn btn-sm btn-primary">View Target</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
