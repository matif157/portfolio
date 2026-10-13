<?php
/**
 * GRAVITY CRM — Recurring Project Series & Cycle Tracking Engine
 * 
 * Yahan multi-month recurring projects (e.g. Monthly Bookkeeping, Quarterly Tax Estimates)
 * ke renewal cycles automatically generate aur manage hote hain, bina duplicate entries ke.
 */

require_once __DIR__ . '/auth.php';
require_permission('services.view');

// Handle Manual Next Cycle Spawn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'spawn_next_cycle') {
    verify_csrf();
    $project_id = intval($_POST['project_id']);

    $stmtP = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmtP->execute([$project_id]);
    $prj = $stmtP->fetch();

    if ($prj && $prj['is_recurring']) {
        $next_cycle = $prj['cycle_number'] + 1;
        $next_code = $prj['project_code'] . '-C' . $next_cycle;
        $start_date = date('Y-m-d', strtotime('+1 month', strtotime($prj['start_date'] ?: date('Y-m-d'))));
        $due_date = date('Y-m-d', strtotime('+1 month', strtotime($prj['due_date'] ?: date('Y-m-d'))));

        $stmtNew = $pdo->prepare("
            INSERT INTO projects (project_code, client_id, business_id, service_id, title, description, status, priority, start_date, due_date, total_amount, is_recurring, recurrence_pattern, series_id, parent_project_id, cycle_number)
            VALUES (?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, ?, 1, ?, ?, ?, ?)
        ");
        $stmtNew->execute([
            $next_code,
            $prj['client_id'],
            $prj['business_id'],
            $prj['service_id'],
            $prj['title'] . " (Cycle #$next_cycle)",
            $prj['description'],
            $prj['priority'],
            $start_date,
            $due_date,
            $prj['total_amount'],
            $prj['recurrence_pattern'],
            $prj['series_id'] ?: ('SERIES-' . $prj['id']),
            $prj['id'],
            $next_cycle
        ]);
        $new_project_id = $pdo->lastInsertId();

        // Automatically create cycle task
        $pdo->prepare("INSERT INTO tasks (project_id, client_id, title, description, status, priority, due_date) VALUES (?, ?, ?, ?, 'pending', 'medium', ?)")
            ->execute([$new_project_id, $prj['client_id'], "Commence Cycle #$next_cycle: {$prj['title']}", "Collect deliverables and start cycle execution.", $due_date]);

        log_activity('Spawned Project Cycle', 'projects', $new_project_id, "Generated recurring cycle #$next_cycle for {$prj['project_code']}", 'stage1_normal');
        set_flash('success', "Renewal Cycle #$next_cycle successfully spawned!");
    }
    redirect('recurring_projects.php');
}

// Fetch Recurring Projects
$projects = $pdo->query("
    SELECT p.*, c.first_name, c.last_name, c.company_name, b.business_name
    FROM projects p
    JOIN clients c ON p.client_id = c.id
    LEFT JOIN businesses b ON p.business_id = b.id
    WHERE p.is_recurring = 1
    ORDER BY p.series_id, p.cycle_number ASC
")->fetchAll();

render_header('Recurring Projects Engine', 'projects');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🔄 Recurring Project Series Engine</h1>
        <p class="page-subtitle">Track multi-month service renewal cycles (e.g., 1 Apr → 1 May → 1 Jun) with automated task initialization</p>
    </div>
    <div>
        <a href="projects.php" class="btn btn-secondary">← Back to All Projects</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Series ID</th>
                    <th>Project Code</th>
                    <th>Title / Contract</th>
                    <th>Client Entity</th>
                    <th>Recurrence</th>
                    <th>Cycle #</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr><td colspan="9" style="text-align:center; color:var(--text-muted); padding:30px;">No recurring project series configured.</td></tr>
                <?php else: ?>
                    <?php foreach ($projects as $p): ?>
                        <tr>
                            <td><code><?= e($p['series_id'] ?: 'SERIES-' . $p['id']) ?></code></td>
                            <td><a href="project_view.php?id=<?= $p['id'] ?>"><strong><?= e($p['project_code']) ?></strong></a></td>
                            <td><?= e($p['title']) ?></td>
                            <td><?= e($p['company_name'] ?: "{$p['first_name']} {$p['last_name']}") ?></td>
                            <td><span class="badge badge-info">🔄 <?= ucfirst($p['recurrence_pattern'] ?: 'Monthly') ?></span></td>
                            <td><span class="badge badge-secondary">Cycle #<?= $p['cycle_number'] ?></span></td>
                            <td><?= format_date($p['due_date']) ?></td>
                            <td><span class="badge badge-<?= $p['status'] === 'open' ? 'success' : 'warning' ?>"><?= ucfirst($p['status']) ?></span></td>
                            <td>
                                <form action="recurring_projects.php" method="POST" style="display:inline;" onsubmit="return confirm('Spawn next renewal cycle for this project?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="spawn_next_cycle">
                                    <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">➕ Spawn Next Cycle</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>

