<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$pdo = db();
$u = current_user();
$shell_title = 'Dashboard';

$stats = [
    'Clients' => 0,
    'Leads' => 0,
    'Opportunities' => 0,
    'Active Services' => 0,
    'Open Projects' => 0,
    'Open Tasks' => 0,
    'Receivables' => 0.0,
    'Payments' => 0.0,
    'Missing Docs' => 0,
    'Notifications' => 0,
];

function dash_count(PDO $pdo, string $sql): int
{
    try {
        return (int)$pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        error_log('[SPS DASHBOARD COUNT] ' . $e->getMessage());
        return 0;
    }
}

function dash_amount(PDO $pdo, string $sql): float
{
    try {
        return (float)$pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        error_log('[SPS DASHBOARD AMOUNT] ' . $e->getMessage());
        return 0.0;
    }
}

/*
 * Every optional/upgrade-dependent table is checked before query execution.
 * This prevents a partially upgraded gpt database from crashing the dashboard.
 */
if (table_exists($pdo, 'clients')) {
    $stats['Clients'] = dash_count($pdo, "SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL");
}

if (table_exists($pdo, 'leads')) {
    $stats['Leads'] = dash_count($pdo, "SELECT COUNT(*) FROM leads WHERE status NOT IN ('converted','lost')");
}

if (table_exists($pdo, 'opportunities')) {
    $stats['Opportunities'] = dash_count($pdo, "SELECT COUNT(*) FROM opportunities WHERE status='open'");
}

if (table_exists($pdo, 'client_services')) {
    $stats['Active Services'] = dash_count(
        $pdo,
        "SELECT COUNT(*) FROM client_services WHERE status NOT IN ('completed','closed')"
    );
}

if (table_exists($pdo, 'projects')) {
    $stats['Open Projects'] = dash_count(
        $pdo,
        "SELECT COUNT(*) FROM projects WHERE status IN ('open','upcoming')"
    );
}

if (table_exists($pdo, 'tasks')) {
    $stats['Open Tasks'] = dash_count(
        $pdo,
        "SELECT COUNT(*) FROM tasks WHERE status NOT IN ('completed','cancelled')"
    );
}

if (table_exists($pdo, 'invoices')) {
    $stats['Receivables'] = dash_amount(
        $pdo,
        "SELECT COALESCE(SUM(total_amount-paid_amount),0)
         FROM invoices
         WHERE status NOT IN ('paid','cancelled')"
    );
}

if (table_exists($pdo, 'payments')) {
    $stats['Payments'] = dash_amount(
        $pdo,
        "SELECT COALESCE(SUM(amount),0) FROM payments"
    );
}

if (table_exists($pdo, 'client_service_requirements')) {
    $stats['Missing Docs'] = dash_count(
        $pdo,
        "SELECT COUNT(*) FROM client_service_requirements WHERE status='missing'"
    );
}

if (table_exists($pdo, 'notifications') && $u) {
    $stats['Notifications'] = dash_count(
        $pdo,
        "SELECT COUNT(*) FROM notifications
         WHERE user_id=" . (int)$u['id'] . " AND is_read=0"
    );
}

/* Recent activity is optional during installation. */
$recent = [];
if (table_exists($pdo, 'activities')) {
    try {
        $recent = $pdo->query(
            "SELECT a.*,
                    COALESCE(CONCAT(u.first_name,' ',u.last_name),'System') AS user_name
             FROM activities a
             LEFT JOIN users u ON u.id=a.user_id
             ORDER BY a.id DESC
             LIMIT 12"
        )->fetchAll();
    } catch (Throwable $e) {
        error_log('[SPS DASHBOARD ACTIVITY] ' . $e->getMessage());
    }
}

/* Appointments are optional until the relevant module/database upgrade exists. */
$upcoming = [];
if (table_exists($pdo, 'appointments')) {
    try {
        $upcoming = $pdo->query(
            "SELECT a.*,
                    CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,'')) AS client_name
             FROM appointments a
             LEFT JOIN clients c ON c.id=a.client_id
             WHERE a.start_at >= NOW()
               AND a.status='scheduled'
             ORDER BY a.start_at
             LIMIT 6"
        )->fetchAll();
    } catch (Throwable $e) {
        error_log('[SPS DASHBOARD APPOINTMENTS] ' . $e->getMessage());
    }
}

$enterpriseReady = phase6_ready($pdo);
$automationReady = phase3_ready($pdo);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard · SPS CRM</title>
<link rel="stylesheet" href="css/sps-shell.css">
<style>
body{
    margin:0;
    background:#06101d;
    color:#edf6ff;
    font-family:Arial,sans-serif;
}
.page{
    padding:22px;
    max-width:1450px;
    margin:auto;
}
.hero{
    display:flex;
    justify-content:space-between;
    align-items:end;
    gap:18px;
    flex-wrap:wrap;
    margin-bottom:18px;
}
.hero h1{font-size:48px;margin:0}
.muted{color:#7892aa}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{
    background:#315ee7;
    color:#fff;
    border:1px solid #6ea8ff55;
    border-radius:10px;
    padding:10px 13px;
    font-weight:800;
    text-decoration:none;
}
.btn.secondary{background:#102742;color:#dff7ff}
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:11px}
.stat{
    background:linear-gradient(145deg,#102742d9,#05111ff2);
    border:1px solid #ffffff14;
    border-radius:16px;
    padding:17px;
}
.stat span{
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.12em;
    color:#7892aa;
}
.stat strong{display:block;font-size:27px;margin-top:7px}
.stat em{font-style:normal;color:#66e8ff;font-size:11px}
.layout{
    display:grid;
    grid-template-columns:1.4fr .8fr;
    gap:16px;
    margin-top:16px;
}
.card{
    background:#102742bb;
    border:1px solid #ffffff15;
    border-radius:18px;
    padding:20px;
}
.quick{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}
.quick a{
    padding:13px;
    border:1px solid #ffffff12;
    border-radius:12px;
    color:#dff7ff;
    text-decoration:none;
    background:#ffffff05;
}
.quick small{display:block;color:#7892aa;margin-top:4px}
.row{
    display:flex;
    justify-content:space-between;
    gap:15px;
    padding:12px 0;
    border-bottom:1px solid #ffffff10;
    font-size:12px;
}
.badge{
    display:inline-block;
    padding:4px 8px;
    border-radius:99px;
    background:#52e7ff10;
    border:1px solid #52e7ff25;
    color:#9beeff;
    font-size:10px;
}
.notice{
    margin-bottom:16px;
    padding:12px 14px;
    border:1px solid #f2d799;
    background:#fff8e80d;
    border-radius:12px;
    color:#f5ddaa;
}
.notice a{color:#9edcff}
@media(max-width:1100px){.stats{grid-template-columns:repeat(3,1fr)}}
@media(max-width:820px){.layout{grid-template-columns:1fr}.quick{grid-template-columns:repeat(2,1fr)}}
@media(max-width:520px){.stats{grid-template-columns:1fr 1fr}.hero h1{font-size:36px}.page{padding:14px}}
</style>
</head>
<body class="sps-shell-page">
<aside class="sps-sidebar">
    <div class="sps-sidebar-brand">
        <div class="sps-logo-orb">S</div>
        <div class="sps-brand-text">
            <strong>SPS CRM</strong>
            <span>2050 OPERATIONS CORE</span>
        </div>
        <button class="sps-sidebar-toggle" id="spsSidebarToggle" type="button">☰</button>
    </div>
    <?php include __DIR__ . '/sidebar_nav.php'; ?>
</aside>

<div class="sps-shell-main">
    <div class="sps-shell-topbar">
        <div class="sps-breadcrumbs">SPS CRM / Command Center</div>
        <button id="spsMobileMenu" class="sps-sidebar-mobile-toggle" type="button">☰</button>
    </div>

    <main class="page">
        <?php if (!$automationReady || !$enterpriseReady): ?>
            <div class="notice">
                <strong>Some database modules are not installed yet.</strong>
                The dashboard is still safe to open; missing module counts are shown as zero.
                <?php if (!$enterpriseReady): ?>
                    <a href="upgrade_enterprise.php">Run enterprise upgrade</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="hero">
            <div>
                <h1>Command Center</h1>
                <div class="muted">
                    Live operational state from the gpt database · <?= date('d M Y H:i') ?>
                </div>
            </div>
            <div class="actions">
                <a class="btn" href="module.php?entity=leads">+ Lead</a>
                <a class="btn" href="invoice_create.php">+ Invoice</a>
                <a class="btn" href="services.php">+ Service</a>
            </div>
        </section>

        <section class="stats">
            <?php foreach ($stats as $label => $value): ?>
                <div class="stat">
                    <span><?= e($label) ?></span>
                    <strong>
                        <?= in_array($label, ['Receivables','Payments'], true)
                            ? '$' . number_format((float)$value, 2)
                            : number_format((int)$value) ?>
                    </strong>
                    <em>LIVE</em>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="layout">
            <section class="card">
                <h2>Quick Operations</h2>
                <div class="quick">
                    <a href="clients.php">Clients<small>CRM entities</small></a>
                    <a href="module.php?entity=opportunities">Pipeline<small>Sales value</small></a>
                    <a href="services.php">Services<small>Workflow executions</small></a>
                    <a href="bank_statements.php">Bank Statements<small>Monthly collection</small></a>
                    <a href="tasks.php">Tasks<small>Staff workload</small></a>
                    <a href="appointments.php">Appointments<small>Client meetings</small></a>
                    <a href="invoices.php">Invoices<small>Receivables</small></a>
                    <a href="reports.php">Reports<small>Intelligence</small></a>
                </div>

                <h2 style="margin-top:25px">Recent Activity</h2>
                <?php if (!$recent): ?>
                    <p class="muted">No activity recorded yet.</p>
                <?php endif; ?>

                <?php foreach ($recent as $r): ?>
                    <div class="row">
                        <span>
                            <strong><?= e($r['user_name']) ?></strong>
                            · <?= e($r['description'] ?: $r['action']) ?>
                        </span>
                        <span class="muted"><?= e($r['created_at']) ?></span>
                    </div>
                <?php endforeach; ?>
            </section>

            <section>
                <div class="card">
                    <h2>Upcoming Appointments</h2>
                    <?php foreach ($upcoming as $a): ?>
                        <div class="row">
                            <span>
                                <strong><?= e($a['title']) ?></strong><br>
                                <span class="muted"><?= e($a['client_name']) ?></span>
                            </span>
                            <span class="badge"><?= e($a['start_at']) ?></span>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$upcoming): ?>
                        <p class="muted">No upcoming appointments.</p>
                    <?php endif; ?>
                </div>

                <div class="card" style="margin-top:16px">
                    <h2>System Health</h2>
                    <p class="muted">
                        Database connection:
                        <span class="badge">ONLINE</span>
                    </p>
                    <p class="muted">
                        Enterprise modules:
                        <a href="upgrade_enterprise.php">Check / Upgrade</a>
                    </p>
                    <p class="muted">
                        Health:
                        <a href="health_check.php">Run health check</a>
                    </p>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.sps-sidebar');
    const toggle = document.getElementById('spsSidebarToggle');
    const mobile = document.getElementById('spsMobileMenu');

    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('is-collapsed');
        });
    }

    if (mobile && sidebar) {
        mobile.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }
});
</script>
</body>
</html>
