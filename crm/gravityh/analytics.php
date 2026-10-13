<?php
/**
 * GRAVITY CRM - Operational Analytics (SPS 2040 Phase 8, Sec 32-33)
 * Answers the management question set with permission-scoped queries.
 * Distinct from the financial 'reports' module.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('analytics', 'view');

$page_title = 'Analytics';
$active_page = 'analytics';

// 1. Busiest services
$busiest = db_query(
    'SELECT s.name, COUNT(cs.id) AS active_assignments
     FROM client_services cs JOIN services s ON s.id = cs.service_id
     WHERE cs.status = \'active\'
     GROUP BY s.id, s.name ORDER BY active_assignments DESC LIMIT 8'
);

// 2. Most-missing requirements
$missing_reqs = db_query(
    "SELECT r.title, COUNT(csr.id) AS open_count
     FROM client_service_requirements csr
     JOIN service_requirements r ON r.id = csr.requirement_id
     WHERE csr.status IN ('not_started','requested','pending_client','rejected')
     GROUP BY r.id, r.title ORDER BY open_count DESC LIMIT 8"
);

// 3. Blocked / on_hold work
$blocked = (int)db_value("SELECT COUNT(*) FROM projects WHERE status IN ('on_hold','not_started')");
$blocked += (int)db_value("SELECT COUNT(*) FROM workflow_instances WHERE status IN ('blocked','waiting')");

// 4. Overdue invoices
$overdue_inv = db_row(
    "SELECT COUNT(*) AS n, COALESCE(SUM(amount_due),0) AS amount
     FROM invoices WHERE status = 'overdue'"
);

// 5. Failing automations (30d)
$auto_fail = (int)db_value(
    "SELECT COUNT(*) FROM automation_logs WHERE action_result = 'failed' AND executed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
);

// 6. Failing integrations (30d)
$int_fail = (int)db_value(
    "SELECT COUNT(*) FROM integration_logs WHERE level = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
);
$abandoned = (int)db_value("SELECT COUNT(*) FROM outbound_queue WHERE status = 'abandoned'");

// 7. Open exceptions by category
$exc_by_cat = db_query(
    "SELECT category, COUNT(*) AS n FROM exceptions WHERE status NOT IN ('resolved','closed')
     GROUP BY category ORDER BY n DESC LIMIT 6"
);

// 8. Frontline backlog - exceptions by severity
$exc_sev = db_query(
    "SELECT severity, COUNT(*) AS n FROM exceptions WHERE status NOT IN ('resolved','closed')
     GROUP BY severity"
);

// 9. Staff workload (open workflow steps by assignee)
$workload = db_query(
    "SELECT u.first_name, u.last_name, COUNT(wsi.id) AS open_steps
     FROM workflow_step_instances wsi
     JOIN users u ON u.id = wsi.assigned_user_id
     WHERE wsi.status IN ('pending','in_progress')
     GROUP BY u.id, u.first_name, u.last_name ORDER BY open_steps DESC LIMIT 8"
);

// 10. Revenue by service (from invoice items)
$revenue = db_query(
    'SELECT s.name, SUM(ii.line_total) AS revenue, COUNT(DISTINCT i.id) AS invoices
     FROM invoice_items ii JOIN services s ON s.id = ii.service_id
     JOIN invoices i ON i.id = ii.invoice_id
     WHERE i.status <> \'cancelled\'
     GROUP BY s.id, s.name ORDER BY revenue DESC LIMIT 8'
);

// 11. AI spend this month
$ai_cost = db_value("SELECT COALESCE(SUM(est_cost_usd),0) FROM ai_usage WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");

// 12. Delivery time (avg days, completed projects)
$avg_delivery = db_value(
    "SELECT ROUND(AVG(DATEDIFF(COALESCE(completed_at, updated_at), start_date)),1)
     FROM projects WHERE status = 'completed' AND start_date IS NOT NULL"
);

include __DIR__ . '/header.php';
?>
<div class="page-title">
    <h1><i class="bi bi-bar-chart"></i> Operational Analytics</h1>
</div>

<!-- KPI cards -->
<div class="card"><div class="card-body" style="display:flex;flex-wrap:wrap;gap:22px;">
    <div><strong><?= (int)$blocked ?></strong><br><span class="text-muted">Blocked / on-hold work</span></div>
    <div><strong><?= (int)$overdue_inv['n'] ?></strong><br><span class="text-muted">Overdue invoices (<?= format_currency($overdue_inv['amount']) ?>)</span></div>
    <div><strong><?= $auto_fail + $int_fail + $abandoned ?></strong><br><span class="text-muted">Automation/integration failures (30d)</span></div>
    <div><strong><?= e($avg_delivery ?: '-') ?></strong><br><span class="text-muted">Avg delivery (days)</span></div>
    <div><strong>$<?= number_format((float)$ai_cost, 2) ?></strong><br><span class="text-muted">AI spend this month</span></div>
</div></div>

<div class="card"><div class="card-body">
<div class="row">
    <div class="col-md-6">
        <h6 class="text-muted">BUSIEST SERVICES</h6>
        <table class="table-custom" style="width:100%;">
        <thead><tr><th>Service</th><th>Active</th></tr></thead>
        <tbody>
        <?php foreach ($busiest as $r): ?>
            <tr><td><?= e($r['name']) ?></td><td><?= (int)$r['active_assignments'] ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="col-md-6">
        <h6 class="text-muted">MOST-MISSING REQUIREMENTS</h6>
        <table class="table-custom" style="width:100%;">
        <thead><tr><th>Requirement</th><th>Open</th></tr></thead>
        <tbody>
        <?php foreach ($missing_reqs as $r): ?>
            <tr><td><?= e($r['title']) ?></td><td><?= (int)$r['open_count'] ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
</div></div>

<div class="card"><div class="card-body">
<div class="row">
    <div class="col-md-6">
        <h6 class="text-muted">EXCEPTIONS BY CATEGORY</h6>
        <table class="table-custom" style="width:100%;">
        <thead><tr><th>Category</th><th>Open</th></tr></thead>
        <tbody>
        <?php foreach ($exc_by_cat as $r): ?>
            <tr><td><?= e(ucwords(str_replace('_',' ',$r['category']))) ?></td>
                <td><?= (int)$r['n'] ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="col-md-6">
        <h6 class="text-muted">STAFF WORKLOAD (open steps)</h6>
        <table class="table-custom" style="width:100%;">
        <thead><tr><th>User</th><th>Open</th></tr></thead>
        <tbody>
        <?php foreach ($workload as $r): ?>
            <tr><td><?= e($r['first_name'].' '.$r['last_name']) ?></td>
                <td><?= (int)$r['open_steps'] ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
</div></div>

<div class="card"><div class="card-body">
    <h6 class="text-muted">REVENUE BY SERVICE</h6>
    <table class="table-custom" style="width:100%;">
    <thead><tr><th>Service</th><th>Invoices</th><th>Revenue</th></tr></thead>
    <tbody>
    <?php foreach ($revenue as $r): ?>
        <tr><td><?= e($r['name']) ?></td><td><?= (int)$r['invoices'] ?></td>
            <td><?= format_currency($r['revenue']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
</div></div>

<?php include __DIR__ . '/footer.php'; ?>