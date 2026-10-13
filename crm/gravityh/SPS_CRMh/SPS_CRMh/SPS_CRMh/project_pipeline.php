<?php
/**
 * SPS Professional Services CRM
 * Visual Project Pipeline / Kanban Overview
 */
require_once __DIR__ . '/auth.php';
require_permission('services.view');

$statuses = ['open', 'upcoming', 'expired', 'completed', 'closed'];
$projects_by_status = [];

foreach ($statuses as $st) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.first_name, c.last_name, c.company_name, b.business_name
        FROM projects p
        JOIN clients c ON p.client_id = c.id
        LEFT JOIN businesses b ON p.business_id = b.id
        WHERE p.status = ?
        ORDER BY p.due_date ASC
    ");
    $stmt->execute([$st]);
    $projects_by_status[$st] = $stmt->fetchAll();
}

render_header('Project Pipeline', 'projects');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Project Operational Pipeline</h1>
        <p class="page-subtitle">Kanban pipeline view of active client deliverables by stage</p>
    </div>
    <div>
        <a href="projects.php" class="btn btn-secondary">Table View</a>
        <a href="recurring_projects.php" class="btn btn-primary">Recurring Series</a>
    </div>
</div>

<div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:16px;">
    <?php foreach ($statuses as $st): ?>
        <div style="background:#f8fafc; border:1px solid var(--border-color); border-radius:8px; padding:12px;">
            <div style="font-weight:700; font-size:13px; text-transform:uppercase; color:var(--secondary); margin-bottom:12px; display:flex; justify-content:space-between;">
                <span><?= $st ?></span>
                <span class="badge badge-secondary"><?= count($projects_by_status[$st]) ?></span>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php if (empty($projects_by_status[$st])): ?>
                    <div style="font-size:12px; color:var(--text-muted); text-align:center; padding:15px; background:#fff; border-radius:6px; border:1px dashed var(--border-color);">No projects</div>
                <?php else: ?>
                    <?php foreach ($projects_by_status[$st] as $p): ?>
                        <div style="background:#ffffff; padding:12px; border-radius:6px; border:1px solid var(--border-color); box-shadow:var(--shadow-sm);">
                            <div style="font-size:11px; font-weight:700; color:var(--accent);"><?= e($p['project_code']) ?></div>
                            <div style="font-weight:600; font-size:13px; margin:4px 0;"><a href="project_view.php?id=<?= $p['id'] ?>"><?= e($p['title']) ?></a></div>
                            <div style="font-size:12px; color:var(--text-muted);"><?= e($p['company_name'] ?: "{$p['first_name']} {$p['last_name']}") ?></div>
                            <div style="display:flex; justify-content:space-between; margin-top:8px; font-size:11px;">
                                <strong><?= format_currency($p['total_amount']) ?></strong>
                                <span style="color:var(--text-muted);"><?= format_date($p['due_date']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php render_footer(); ?>
