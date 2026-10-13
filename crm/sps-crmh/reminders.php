<?php
/**
 * SPS Professional Services CRM
 * Manage Reminders List
 */
require_once __DIR__ . '/auth.php';
require_permission('services.view');

if (isset($_GET['delete_id'])) {
    verify_csrf();
    $rem_id = intval($_GET['delete_id']);
    $pdo->prepare("DELETE FROM reminders WHERE id = ?")->execute([$rem_id]);
    log_activity('Delete Reminder', 'calendar', $rem_id, 'Deleted reminder schedule');
    set_flash('success', 'Reminder deleted.');
    redirect('reminders.php');
}

$reminders = $pdo->query("
    SELECT r.*, u.full_name as assigned_staff,
           (SELECT GROUP_CONCAT(COALESCE(c.company_name, CONCAT(c.first_name, ' ', c.last_name)) SEPARATOR ', ')
            FROM reminder_clients rc JOIN clients c ON rc.client_id = c.id WHERE rc.reminder_id = r.id) as client_names
    FROM reminders r
    LEFT JOIN users u ON r.assigned_user_id = u.id
    ORDER BY r.reminder_date DESC, r.reminder_time ASC
")->fetchAll();

render_header('Reminders Manager', 'calendar');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reminders Directory</h1>
        <p class="page-subtitle">List of scheduled client notifications, tax deadlines, and follow-up activities</p>
    </div>
    <div>
        <a href="calendar.php" class="btn btn-primary">📅 Open Calendar View</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Date & Time</th>
                    <th>Associated Clients</th>
                    <th>Recurrence</th>
                    <th>Assigned User</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reminders)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No reminders scheduled.</td></tr>
                <?php else: ?>
                    <?php foreach ($reminders as $r): ?>
                        <tr>
                            <td>
                                <strong><?= e($r['title']) ?></strong>
                                <?php if ($r['description']): ?>
                                    <div style="font-size:12px; color:var(--text-muted);"><?= e($r['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= format_date($r['reminder_date']) ?></strong><br>
                                <span style="font-size:12px; color:var(--text-muted);"><?= date('g:i A', strtotime($r['reminder_time'])) ?></span>
                            </td>
                            <td><?= e($r['client_names'] ?: 'All / General') ?></td>
                            <td><span class="badge badge-info"><?= ucfirst($r['recurrence']) ?></span></td>
                            <td><?= e($r['assigned_staff'] ?: 'Unassigned') ?></td>
                            <td>
                                <a href="reminders.php?delete_id=<?= $r['id'] ?>&csrf_token=<?= csrf_token() ?>" onclick="return confirm('Delete this reminder?')" class="btn btn-sm btn-danger">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
