<?php
/**
 * SPS Professional Services CRM
 * Generic Service Workflow Engine & Step Progression UI
 */
require_once __DIR__ . '/auth.php';
require_permission('services.view');

$service_id = intval($_GET['service_id'] ?? 1);
$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    set_flash('danger', 'Service definition not found.');
    redirect('services.php');
}

$steps = $pdo->prepare("SELECT * FROM service_workflow_steps WHERE service_id = ? ORDER BY step_number ASC");
$steps->execute([$service_id]);
$step_list = $steps->fetchAll();

$requirements = $pdo->prepare("SELECT * FROM service_requirements WHERE service_id = ? ORDER BY sort_order ASC");
$requirements->execute([$service_id]);
$req_list = $requirements->fetchAll();

// Active Service Executions
$client_services = $pdo->prepare("
    SELECT cs.*, c.first_name, c.last_name, c.company_name, b.business_name
    FROM client_services cs
    JOIN clients c ON cs.client_id = c.id
    LEFT JOIN businesses b ON cs.business_id = b.id
    WHERE cs.service_id = ?
    ORDER BY cs.created_at DESC
");
$client_services->execute([$service_id]);
$executions = $client_services->fetchAll();

render_header("Workflow Engine: {$service['title']}", 'services');
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= e($service['title']) ?> (Workflow Engine)</h1>
        <p class="page-subtitle">Service Code: <?= e($service['service_code']) ?> • Recurring Schedule: <?= ucfirst($service['recurring_period']) ?></p>
    </div>
    <div>
        <?php if ($service_id == 1): ?>
            <a href="bank_statements.php" class="btn btn-primary">⚡ Open Statement Automation Matrix</a>
        <?php endif; ?>
        <a href="services.php" class="btn btn-secondary">← Service Catalog</a>
    </div>
</div>

<!-- Workflow Step Progression Diagram -->
<div class="card" style="border-top:4px solid var(--accent);">
    <div class="card-title">Standard Workflow Progression Sequence</div>

    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
        <?php foreach ($step_list as $idx => $st): ?>
            <div style="flex:1; min-width:180px; background:#f8fafc; padding:15px; border-radius:8px; border:1px solid var(--border-color); position:relative;">
                <div style="font-size:11px; font-weight:700; color:var(--accent); text-transform:uppercase; margin-bottom:4px;">
                    Step <?= $st['step_number'] ?>: <?= ucfirst(str_replace('_', ' ', $st['step_type'])) ?>
                </div>
                <div style="font-weight:600; font-size:14px; margin-bottom:6px;"><?= e($st['title']) ?></div>
                <div style="font-size:12px; color:var(--text-muted);"><?= e($st['description']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 2fr; gap:20px;">
    <!-- Service Requirements -->
    <div class="card">
        <div class="card-title">Required Documentation & Inputs</div>
        <?php if (empty($req_list)): ?>
            <p style="font-size:13px; color:var(--text-muted);">No requirements configured for this service.</p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($req_list as $req): ?>
                    <div style="padding:10px; border-bottom:1px solid var(--border-color); font-size:13px;">
                        <strong>• <?= e($req['title']) ?></strong>
                        <?php if ($req['is_required']): ?>
                            <span class="badge badge-danger" style="margin-left:6px;">Required</span>
                        <?php endif; ?>
                        <div style="font-size:12px; color:var(--text-muted); margin-top:2px;"><?= e($req['description']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Active Client Deliveries -->
    <div class="card">
        <div class="card-title">Active Service Deliveries (<?= count($executions) ?>)</div>
        <?php if (empty($executions)): ?>
            <p style="font-size:13px; color:var(--text-muted);">No active client deliveries for this service.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client / Business</th>
                            <th>Current Step</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($executions as $ex): ?>
                            <tr>
                                <td>
                                    <strong><?= e($ex['company_name'] ?: "{$ex['first_name']} {$ex['last_name']}") ?></strong>
                                    <?php if ($ex['business_name']): ?>
                                        <br><span style="font-size:12px; color:var(--text-muted);"><?= e($ex['business_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-info">Step <?= $ex['current_step_number'] ?></span></td>
                                <td><span class="badge badge-warning"><?= ucfirst(str_replace('_', ' ', $ex['status'])) ?></span></td>
                                <td><?= format_date($ex['start_date']) ?></td>
                                <td>
                                    <?php if ($service_id == 1): ?>
                                        <a href="bank_statements.php?business_id=<?= $ex['business_id'] ?>" class="btn btn-sm btn-primary">Process Statements</a>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">In Progress</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
