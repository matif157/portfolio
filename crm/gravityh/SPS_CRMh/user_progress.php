<?php
/**
 * GRAVITY CRM — User Progress / Workflow Overview
 *
 * Authorized users apna tasks, activities, client names, service/workflow status,
 * uploaded documents, invoices aur progress overview dekh sakte hain.
 */
require_once __DIR__ . '/auth.php';
require_login();

if (!user_can('admin.settings') && !user_can('clients.view') && !user_can('user.progress')) {
    require_permission('admin.settings');
}

// User ka progress data fetch karte hain
$current_user = current_user();
$user_id = $current_user['id'];

$stmt = $pdo->prepare("
    SELECT t.id, t.title, t.status, t.due_date, t.priority,
           c.first_name, c.last_name, c.company_name,
           p.project_name
    FROM tasks t
    LEFT JOIN clients c ON t.client_id = c.id
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ?
    ORDER BY (t.status = 'pending') DESC, t.due_date ASC
    LIMIT 25
");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll();

render_header('My Work & Progress', 'operations');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📋 My Work & User Progress</h1>
        <p class="page-subtitle">Assigned tasks, activities, service workflow aur client status — ek hi view me milta hai.</p>
    </div>
</div>

<div class="card">
    <div class="card-title">✅ My Assigned Tasks</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Task</th><th>Client</th><th>Project</th><th>Priority</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($tasks)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No tasks assigned to you yet.</td></tr>
            <?php else: ?>
                <?php foreach ($tasks as $ts): ?>
                    <tr>
                        <td><strong><?= e($ts['title']) ?></strong></td>
                        <td><?= e($ts['first_name'] . ' ' . $ts['last_name']) ?></td>
                        <td><?= e($ts['project_name'] ?: '—') ?></td>
                        <td><span class="badge badge-<?= $ts['priority'] === 'high' ? 'danger' : 'secondary' ?>"><?= ucfirst($ts['priority']) ?></span></td>
                        <td><?= format_date($ts['due_date']) ?></td>
                        <td><span class="badge badge-<?= $ts['status'] === 'completed' ? 'success' : 'info' ?>"><?= ucfirst($ts['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>