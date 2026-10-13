<?php
/**
 * GRAVITY CRM — Master Intelligent Executive & Staff Dashboard
 * 
 * Yahan dashboard user ke assigned role layout profile (CEO, Bookkeeper, Tax Filer, AR, Front Desk, Immigration)
 * ke mutabiq dynamic widgets, KPIs, aur action items display karta hai.
 */

require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();

// Fetch Role Layout Profile
$stmtRole = $pdo->prepare("SELECT layout_profile FROM roles WHERE id = ?");
$stmtRole->execute([$user['role_id']]);
$layout_profile = $stmtRole->fetchColumn() ?: 'custom';

// Common Metrics
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'active'")->fetchColumn();
$active_services = $pdo->query("SELECT COUNT(*) FROM client_services WHERE status = 'in_progress'")->fetchColumn();
$open_projects = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'open'")->fetchColumn();
$unpaid_invoices = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('unpaid', 'partially_paid', 'overdue')")->fetchColumn();
$outstanding_balance = $pdo->query("SELECT COALESCE(SUM(balance_due), 0) FROM invoices WHERE status IN ('unpaid', 'partially_paid', 'overdue')")->fetchColumn();

// Bank Statement Metrics
$total_accounts = $pdo->query("SELECT COUNT(*) FROM bank_accounts WHERE is_active = 1")->fetchColumn();
$total_statements = $pdo->query("SELECT COUNT(*) FROM bank_statements")->fetchColumn();
$received_statements = $pdo->query("SELECT COUNT(*) FROM bank_statements WHERE status IN ('received', 'verified')")->fetchColumn();
$missing_statements = $pdo->query("SELECT COUNT(*) FROM bank_statements WHERE status = 'missing'")->fetchColumn();

// Leads & Front Desk Metrics
$total_leads = $pdo->query("SELECT COUNT(*) FROM leads WHERE status NOT IN ('won', 'lost')")->fetchColumn();

// Fetch Missing Statements Breakdown
$missing_summary = $pdo->query("
    SELECT b.business_name, c.first_name, c.last_name, ba.bank_name, ba.account_number_last4, COUNT(bs.id) as missing_count
    FROM bank_statements bs
    JOIN bank_accounts ba ON bs.bank_account_id = ba.id
    JOIN businesses b ON ba.business_id = b.id
    JOIN clients c ON b.client_id = c.id
    WHERE bs.status = 'missing'
    GROUP BY ba.id
    ORDER BY missing_count DESC
    LIMIT 5
")->fetchAll();

// Pending Tasks
$tasks = $pdo->query("
    SELECT t.*, c.first_name, c.last_name, c.company_name 
    FROM tasks t 
    LEFT JOIN clients c ON t.client_id = c.id 
    WHERE t.status = 'pending' 
    ORDER BY CASE WHEN t.priority = 'high' THEN 1 ELSE 2 END, t.due_date ASC 
    LIMIT 6
")->fetchAll();

// Recent Activities
$activities = $pdo->query("
    SELECT a.*, u.full_name 
    FROM activities a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 6
")->fetchAll();

render_header('Executive Workspace Dashboard', 'dashboard');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📊 Workspace Dashboard</h1>
        <p class="page-subtitle">
            Welcome back, <strong><?= e($user['full_name']) ?></strong> • 
            <span class="badge badge-info"><?= strtoupper(e($layout_profile)) ?> PROFILE</span>
        </p>
    </div>
    <div style="display:flex; gap:8px;">
        <button type="button" onclick="openQuickAddModal()" class="btn btn-primary">⚡ Quick Add</button>
        <?php if ($layout_profile === 'bookkeeper' || $layout_profile === 'ceo'): ?>
            <a href="bank_statements.php" class="btn btn-secondary">🏦 Statement Center</a>
        <?php endif; ?>
        <?php if ($layout_profile === 'ar'): ?>
            <a href="invoice_create.php" class="btn btn-secondary">+ Create Invoice</a>
        <?php endif; ?>
        <?php if ($layout_profile === 'front_desk'): ?>
            <a href="leads.php" class="btn btn-secondary">⭐ Intake Leads</a>
        <?php endif; ?>
    </div>
</div>

<!-- Dynamic KPIs Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Active Clients</span>
        <span class="stat-value primary"><?= number_format($total_clients) ?></span>
        <span class="stat-desc">Enrolled in professional services</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Active Workflows</span>
        <span class="stat-value primary"><?= number_format($active_services) ?></span>
        <span class="stat-desc">In-progress service deliveries</span>
    </div>

    <?php if ($layout_profile === 'ar' || $layout_profile === 'ceo'): ?>
        <div class="stat-card">
            <span class="stat-label">Outstanding Receivables</span>
            <span class="stat-value danger"><?= format_currency($outstanding_balance) ?></span>
            <span class="stat-desc"><?= $unpaid_invoices ?> unpaid invoices</span>
        </div>
    <?php else: ?>
        <div class="stat-card">
            <span class="stat-label">Open Projects</span>
            <span class="stat-value primary"><?= number_format($open_projects) ?></span>
            <span class="stat-desc">Client service series</span>
        </div>
    <?php endif; ?>

    <?php if ($layout_profile === 'front_desk'): ?>
        <div class="stat-card">
            <span class="stat-label">Pipeline Leads</span>
            <span class="stat-value primary"><?= number_format($total_leads) ?></span>
            <span class="stat-desc">Prospective inquiries</span>
        </div>
    <?php else: ?>
        <div class="stat-card">
            <span class="stat-label">Missing Statements</span>
            <span class="stat-value <?= $missing_statements > 0 ? 'danger' : 'success' ?>"><?= number_format($missing_statements) ?></span>
            <span class="stat-desc">From <?= $total_accounts ?> active accounts</span>
        </div>
    <?php endif; ?>
</div>

<!-- Role Specific Section: Bank Statement Collection for Bookkeeper & CEO -->
<?php if (in_array($layout_profile, ['ceo', 'bookkeeper', 'tax_filer', 'custom'])): ?>
    <div class="card card-accent-primary">
        <div class="card-header" style="background:transparent; border:none; padding:0 0 var(--space-4) 0;">
            <h3 class="card-title">🏦 Monthly Bank Statement Collection &amp; Bookkeeping Automation</h3>
            <a href="bank_statements.php" class="btn btn-sm btn-primary">Open Statement Matrix →</a>
        </div>

        <div class="grid-3" style="margin-bottom:15px;">
            <div style="background:var(--color-surface-sunken); padding:15px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                <div style="font-size:11px; color:var(--text-muted); font-weight:700; letter-spacing:0.5px;">ACTIVE BANK ACCOUNTS</div>
                <div style="font-size:22px; font-weight:700; color:var(--color-primary);"><?= $total_accounts ?></div>
            </div>

            <div style="background:var(--color-success-subtle); padding:15px; border-radius:var(--radius-md); border:1px solid rgba(16,185,129,0.3);">
                <div style="font-size:11px; color:var(--color-success); font-weight:700; letter-spacing:0.5px;">RECEIVED STATEMENTS</div>
                <div style="font-size:22px; font-weight:700; color:var(--color-success);"><?= $received_statements ?></div>
            </div>

            <div style="background:var(--color-danger-subtle); padding:15px; border-radius:var(--radius-md); border:1px solid rgba(239,68,68,0.3);">
                <div style="font-size:11px; color:var(--color-danger); font-weight:700; letter-spacing:0.5px;">MISSING STATEMENTS</div>
                <div style="font-size:22px; font-weight:700; color:var(--color-danger);"><?= $missing_statements ?></div>
            </div>
        </div>

        <?php if (!empty($missing_summary)): ?>
            <h4 style="font-size:13px; font-weight:600; margin-bottom:10px; color:var(--text-secondary);">Urgent Missing Statement Portfolios:</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client / Business</th>
                            <th>Bank &amp; Account</th>
                            <th>Missing Count</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($missing_summary as $ms): ?>
                            <tr>
                                <td>
                                    <strong><?= e($ms['business_name']) ?></strong><br>
                                    <span style="font-size:12px; color:var(--text-muted);"><?= e($ms['first_name'] . ' ' . $ms['last_name']) ?></span>
                                </td>
                                <td><?= e($ms['bank_name']) ?> (x-<?= e($ms['account_number_last4']) ?>)</td>
                                <td><span class="badge badge-danger"><?= $ms['missing_count'] ?> Months Missing</span></td>
                                <td>
                                    <a href="bank_statements.php" class="btn btn-sm btn-secondary">Upload &amp; Remind</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Two-Column Operations Layout: Tasks & Activity -->
<div class="grid-2">
    <!-- Tasks Section -->
    <div class="card">
        <div class="card-header" style="background:transparent; border:none; padding:0 0 var(--space-4) 0;">
            <h3 class="card-title">📋 Priority Work Tasks</h3>
            <a href="tasks.php" class="btn btn-sm btn-secondary">View All Tasks →</a>
        </div>
        <?php if (empty($tasks)): ?>
            <p style="color:var(--text-muted); font-size:13px; padding:15px 0;">No pending tasks at this moment.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Task Title</th>
                            <th>Client / Entity</th>
                            <th>Priority</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $t): ?>
                            <tr>
                                <td><strong><?= e($t['title']) ?></strong></td>
                                <td><?= e($t['company_name'] ?: ($t['first_name'] . ' ' . $t['last_name'])) ?></td>
                                <td><span class="badge badge-<?= $t['priority'] === 'high' ? 'danger' : 'secondary' ?>"><?= ucfirst($t['priority']) ?></span></td>
                                <td><?= format_date($t['due_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity Log -->
    <div class="card">
        <div class="card-header" style="background:transparent; border:none; padding:0 0 var(--space-4) 0;">
            <h3 class="card-title">📜 Recent CRM Operations Feed</h3>
            <a href="activity_logs.php" class="btn btn-sm btn-secondary">Audit Trail →</a>
        </div>
        <?php if (empty($activities)): ?>
            <p style="color:var(--text-muted); font-size:13px; padding:15px 0;">No recent activities recorded.</p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($activities as $act): ?>
                    <div style="padding:10px 12px; background:var(--color-surface-sunken); border:1px solid var(--border-color); border-radius:var(--radius-md); font-size:13px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:2px;">
                            <strong style="color:var(--text-primary);"><?= e($act['action']) ?></strong>
                            <span style="font-size:11px; color:var(--text-muted);"><?= format_datetime($act['created_at']) ?></span>
                        </div>
                        <div style="color:var(--text-secondary);"><?= e($act['description']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>

