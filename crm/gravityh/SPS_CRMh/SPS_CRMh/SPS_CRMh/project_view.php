<?php
/**
 * SPS Professional Services CRM
 * Detailed Project Profile & Realtime Service Automation Engine
 */
require_once __DIR__ . '/auth.php';
require_permission('services.view');

$project_id = intval($_GET['id'] ?? 0);

// Handle Realtime Service Automation Execution inside Project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    
    $stmtP = $pdo->prepare("SELECT p.*, c.first_name, c.email, c.phone FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
    $stmtP->execute([$project_id]);
    $prj = $stmtP->fetch();

    if ($prj) {
        if ($_POST['action'] === 'run_project_automation') {
            // 1. Fetch missing requirements for project service
            $missing_reqs = $pdo->prepare("SELECT title FROM service_requirements WHERE service_id = ? AND is_required = 1");
            $missing_reqs->execute([$prj['service_id'] ?: 1]);
            $req_titles = $missing_reqs->fetchAll(PDO::FETCH_COLUMN);

            $req_str = !empty($req_titles) ? implode(', ', $req_titles) : 'Required onboarding documents';
            $msg = "Hello {$prj['first_name']}, regarding your project '{$prj['title']}', please provide: {$req_str}. Upload via portal or reply to this message.";
            
            // Execute Outbound Email
            $pdo->prepare("INSERT INTO communications (client_id, channel, subject, message_body, direction, status) VALUES (?, 'email', ?, ?, 'outbound', 'sent')")
                ->execute([$prj['client_id'], "Realtime Service Automation: {$prj['title']}", $msg]);

            // Execute Twilio SMS
            send_twilio_sms($prj['client_id'], "SPS Service Alert: Please provide missing items for {$prj['title']}: {$req_str}");

            log_activity('Realtime Service Automation', 'projects', $project_id, "Executed realtime automation scan for Project #{$prj['project_code']}. Dispatched Email & Twilio SMS to client.");
            set_flash('success', "Realtime Service Automation Executed! Dispatched automated Email & Twilio SMS for required items.");
        } elseif ($_POST['action'] === 'twilio_call_client') {
            $script = "Hello {$prj['first_name']}. This is an automated notification from Syed Professional Services regarding your project {$prj['title']}. We require outstanding documentation to complete your service workflow. Please check your client portal.";
            $res = send_twilio_call($prj['client_id'], $script);
            set_flash('success', "Twilio Voice Call initiated to {$prj['phone']}! Call Duration: {$res['duration']}s logged to client profile.");
        } elseif ($_POST['action'] === 'twilio_sms_client') {
            $sms_text = trim($_POST['sms_body'] ?? "SPS Reminder: Please upload required documentation for {$prj['title']}.");
            send_twilio_sms($prj['client_id'], $sms_text);
            set_flash('success', "Twilio SMS dispatched to client phone!");
        }
    }
    redirect("project_view.php?id=$project_id");
}

$stmt = $pdo->prepare("
    SELECT p.*, c.first_name, c.last_name, c.company_name, c.email, c.phone, b.business_name, s.title as service_title, s.id as service_id
    FROM projects p
    JOIN clients c ON p.client_id = c.id
    LEFT JOIN businesses b ON p.business_id = b.id
    LEFT JOIN services s ON p.service_id = s.id
    WHERE p.id = ?
");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    set_flash('danger', 'Project record not found.');
    redirect('projects.php');
}

// Service Workflow Steps
$steps = $pdo->prepare("SELECT * FROM service_workflow_steps WHERE service_id = ? ORDER BY step_number ASC");
$steps->execute([$project['service_id'] ?: 1]);
$step_list = $steps->fetchAll();

// Service Requirements
$reqs = $pdo->prepare("SELECT * FROM service_requirements WHERE service_id = ? ORDER BY sort_order ASC");
$reqs->execute([$project['service_id'] ?: 1]);
$req_list = $reqs->fetchAll();

// Tasks & Invoices
$tasks = $pdo->prepare("SELECT t.*, u.full_name as staff_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id WHERE t.project_id = ? ORDER BY t.due_date ASC");
$tasks->execute([$project_id]);
$task_list = $tasks->fetchAll();

$invoices = $pdo->prepare("SELECT * FROM invoices WHERE project_id = ? ORDER BY issue_date DESC");
$invoices->execute([$project_id]);
$invoice_list = $invoices->fetchAll();

render_header("Project Details: {$project['project_code']}", 'projects');
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= e($project['title']) ?></h1>
        <p class="page-subtitle">Project Code: <?= e($project['project_code']) ?> • Service: <?= e($project['service_title'] ?: 'Bookkeeping & Tax') ?> • Client: <?= e($project['company_name'] ?: "{$project['first_name']} {$project['last_name']}") ?></p>
    </div>
    <div>
        <form action="project_view.php?id=<?= $project_id ?>" method="POST" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_project_automation">
            <button type="submit" class="btn btn-primary">⚡ Execute Realtime Service Automation</button>
        </form>
        <a href="projects.php" class="btn btn-secondary">← Back to Projects</a>
    </div>
</div>

<!-- Project Overview KPIs -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Project Status</span>
        <span class="stat-value" style="font-size:20px;">
            <span class="badge badge-<?= $project['status'] === 'completed' ? 'success' : 'warning' ?>"><?= ucfirst($project['status']) ?></span>
        </span>
        <span class="stat-desc">Target Due Date: <?= format_date($project['due_date']) ?></span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Service Title</span>
        <span class="stat-value" style="font-size:18px; color:var(--accent);"><?= e($project['service_title'] ?: 'Bookkeeping') ?></span>
        <span class="stat-desc">Standard SPS Workflow</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Total Fee Value</span>
        <span class="stat-value"><?= format_currency($project['total_amount']) ?></span>
        <span class="stat-desc">Collected: <?= format_currency($project['paid_amount']) ?></span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Automated Client Touchpoint</span>
        <div style="display:flex; gap:6px; margin-top:8px;">
            <form action="project_view.php?id=<?= $project_id ?>" method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="twilio_call_client">
                <button type="submit" class="btn btn-sm btn-warning">📞 Twilio Call</button>
            </form>
            <form action="project_view.php?id=<?= $project_id ?>" method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="twilio_sms_client">
                <button type="submit" class="btn btn-sm btn-secondary">💬 Twilio SMS</button>
            </form>
        </div>
    </div>
</div>

<!-- REALTIME SERVICE AUTOMATION PIPELINE TAB -->
<div class="card" style="border-top:4px solid var(--accent);">
    <div class="card-title">
        <span>⚡ Realtime Service Automation & Workflow Steps Pipeline</span>
        <span class="badge badge-success">Live Realtime Engine</span>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
        <?php foreach ($step_list as $st): ?>
            <div style="flex:1; min-width:180px; background:#f8fafc; padding:15px; border-radius:8px; border:1px solid var(--border-color);">
                <div style="font-size:11px; font-weight:700; color:var(--accent); text-transform:uppercase; margin-bottom:4px;">
                    Step <?= $st['step_number'] ?>: <?= ucfirst(str_replace('_', ' ', $st['step_type'])) ?>
                </div>
                <div style="font-weight:600; font-size:13px; margin-bottom:4px;"><?= e($st['title']) ?></div>
                <div style="font-size:12px; color:var(--text-muted);"><?= e($st['description']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
    <!-- Required Documents & Items -->
    <div class="card">
        <div class="card-title">Service Documentation Requirements</div>
        <?php if (empty($req_list)): ?>
            <p style="font-size:13px; color:var(--text-muted);">No requirements configured for this service.</p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <?php foreach ($req_list as $r): ?>
                    <div style="padding:10px; background:#f8fafc; border-radius:6px; border:1px solid var(--border-color); font-size:13px;">
                        <strong>• <?= e($r['title']) ?></strong>
                        <span class="badge badge-<?= $r['is_required'] ? 'danger' : 'secondary' ?>" style="margin-left:6px;"><?= $r['is_required'] ? 'Mandatory' : 'Optional' ?></span>
                        <div style="font-size:12px; color:var(--text-muted); margin-top:2px;"><?= e($r['description']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Project Tasks -->
    <div class="card">
        <div class="card-title">Tasks & Operational Milestones (<?= count($task_list) ?>)</div>
        <?php if (empty($task_list)): ?>
            <p style="font-size:13px; color:var(--text-muted);">No operational tasks created.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Task Title</th>
                        <th>Assigned Staff</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($task_list as $t): ?>
                        <tr>
                            <td><strong><?= e($t['title']) ?></strong></td>
                            <td><?= e($t['staff_name'] ?: 'Unassigned') ?></td>
                            <td><span class="badge badge-<?= $t['status'] === 'completed' ? 'success' : 'warning' ?>"><?= ucfirst($t['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
