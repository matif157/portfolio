<?php
/**
 * GRAVITY CRM - Dashboard
 * Phase 1: Foundation
 * 
 * Yahan main dashboard hai jisme stats, recent activities,
 * aur user-specific information dikhti hai.
 * Dashboard content role ke according change hota hai.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ai_service.php';
require_login();
require_permission('dashboard', 'view');

$user = current_user();
$page_title = 'Dashboard';
$active_page = 'dashboard';

// Stats
$total_clients = (int)db_value('SELECT COUNT(*) FROM clients');
$active_clients = (int)db_value('SELECT COUNT(*) FROM clients WHERE status = "active"');
$total_leads = (int)db_value('SELECT COUNT(*) FROM leads');
$new_leads = (int)db_value('SELECT COUNT(*) FROM leads WHERE status = "new"');
$total_businesses = (int)db_value('SELECT COUNT(*) FROM businesses');
$total_contacts = (int)db_value('SELECT COUNT(*) FROM contacts');
$total_users = (int)db_value('SELECT COUNT(*) FROM users WHERE status = "active"');

// Phase 3: Workflow stats
$total_workflows = (int)db_value('SELECT COUNT(*) FROM workflow_instances');
$running_workflows = (int)db_value("SELECT COUNT(*) FROM workflow_instances WHERE status IN ('not_started','in_progress','waiting')");
$completed_workflows = (int)db_value("SELECT COUNT(*) FROM workflow_instances WHERE status = 'completed'");

// Phase 4: Automation stats
$active_automations = (int)db_value('SELECT COUNT(*) FROM automation_rules WHERE is_active = 1');

// Phase 5: Audit/security
$recent_failed_logins = (int)db_value('SELECT COUNT(*) FROM activities WHERE module = "auth" AND action = "login_failed" AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');

// AI Insights (Phase 4) - only for admin/manager roles
$ai_insights = '';
if (user_can('ai', 'view') || user_can('dashboard', 'view')) {
    $ai_insights = generate_ai_insights();
}

// My tasks/progress
$my_progress = db_query('SELECT * FROM user_progress WHERE user_id = ? ORDER BY updated_at DESC LIMIT 5', [$user['id']]);
$my_blocked = (int)db_value('SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND status = "blocked"', [$user['id']]);

// Recent activities
$recent_activities = db_query(
    'SELECT a.*, u.first_name, u.last_name 
     FROM activities a 
     LEFT JOIN users u ON a.user_id = u.id 
     ORDER BY a.created_at DESC 
     LIMIT 10'
);

// Recent clients
$recent_clients = db_query('SELECT * FROM clients ORDER BY created_at DESC LIMIT 5');

// Recent leads
$recent_leads = db_query('SELECT * FROM leads ORDER BY created_at DESC LIMIT 5');

// Recent workflow instances (Phase 3)
$recent_workflows = db_query(
    'SELECT wi.*, wt.name AS workflow_name, c.first_name, c.last_name
     FROM workflow_instances wi
     JOIN workflow_templates wt ON wi.workflow_id = wt.id
     LEFT JOIN clients c ON wi.client_id = c.id
     ORDER BY wi.started_at DESC LIMIT 5'
);

// Role-based dashboard content
$role_layout = current_role_layout();
$layout_key = $role_layout['layout_key'] ?? 'default';
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-speedometer2"></i> Dashboard</h1>
    <span class="text-muted">Welcome back, <?= e($user['first_name']) ?>!</span>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-people"></i></div>
            <div class="stat-value"><?= $total_clients ?></div>
            <div class="stat-label">Total Clients (<?= $active_clients ?> active)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-lightning"></i></div>
            <div class="stat-value"><?= $total_leads ?></div>
            <div class="stat-label">Total Leads (<?= $new_leads ?> new)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-diagram-3"></i></div>
            <div class="stat-value"><?= $running_workflows ?></div>
            <div class="stat-label">Active Workflows</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-robot"></i></div>
            <div class="stat-value"><?= $active_automations ?></div>
            <div class="stat-label">Active Automations</div>
        </div>
    </div>
</div>

<?php if ($recent_failed_logins > 0): ?>
<div class="row mb-3">
    <div class="col-md-12">
        <div class="alert alert-warning" style="margin-bottom:0;">
            <i class="bi bi-exclamation-triangle"></i> 
            <strong>Security Alert:</strong> <?= $recent_failed_logins ?> failed login attempt(s) detected in the last 7 days. 
            <a href="audit_log.php?module=auth&action=login_failed&days=7" class="btn-secondary-custom btn-sm">Review Audit Log</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- My Tasks / Progress -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                My Tasks & Progress
                <a href="user_progress.php" class="btn-secondary-custom btn-sm">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($my_progress)): ?>
                    <p class="text-muted">No tasks assigned yet.</p>
                <?php else: ?>
                    <?php foreach ($my_progress as $progress): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <strong><?= e($progress['progress_label'] ?? $progress['module']) ?></strong>
                                <span><?= e($progress['progress_value']) ?>%</span>
                            </div>
                            <div class="progress" style="height:8px;background:#e5e7eb;border-radius:4px;">
                                <div class="progress-bar" style="width:<?= (int)$progress['progress_value'] ?>%;background:var(--primary);border-radius:4px;"></div>
                            </div>
                            <small class="text-muted"><?= status_badge($progress['status']) ?> Updated: <?= e(time_ago($progress['updated_at'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if ($my_blocked > 0): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle"></i> You have <?= $my_blocked ?> blocked item(s) requiring attention.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Clients -->
        <div class="card">
            <div class="card-header">
                Recent Clients
                <a href="clients.php" class="btn-secondary-custom btn-sm">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_clients)): ?>
                    <p class="text-muted">No clients yet.</p>
                <?php else: ?>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_clients as $client): ?>
                                <tr>
                                    <td><a href="clients.php?view=<?= $client['id'] ?>"><?= e($client['first_name'] . ' ' . $client['last_name']) ?></a></td>
                                    <td><?= e($client['email']) ?></td>
                                    <td><?= status_badge($client['status']) ?></td>
                                    <td><?= e(time_ago($client['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Workflows (Phase 3) -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-diagram-3"></i> Recent Workflows
                <a href="workflows.php" class="btn-secondary-custom btn-sm">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_workflows)): ?>
                    <p class="text-muted">No workflow instances yet.</p>
                <?php else: ?>
                    <table class="table-custom">
                        <thead><tr><th>Workflow</th><th>Client</th><th>Status</th><th>Progress</th></tr></thead>
                        <tbody>
                            <?php foreach ($recent_workflows as $w): ?>
                                <tr>
                                    <td><?= e($w['workflow_name']) ?></td>
                                    <td><?= e($w['first_name'] ? $w['first_name'] . ' ' . $w['last_name'] : '-') ?></td>
                                    <td><?= status_badge($w['status']) ?></td>
                                    <td><?= e($w['progress_value']) ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <!-- AI Insights (Phase 4) -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-graph-up-arrow"></i> AI Executive Insights
                <?php if (module_accessible('ai')): ?>
                    <a href="ai.php" class="btn-secondary-custom btn-sm">AI Assistant</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (ai_api_configured()): ?>
                    <span class="badge badge-success">Powered by <?= e(ucfirst(get_setting('ai_provider', 'openai'))) ?></span>
                <?php else: ?>
                    <span class="badge badge-warning">Rule-Based Mode</span>
                <?php endif; ?>
                <div style="margin-top:1rem;" class="ai-insights-content">
                    <?= e(nl2br($ai_insights)) ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="card">
            <div class="card-header">
                Recent Activities
                <a href="activities.php" class="btn-secondary-custom btn-sm">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                    <p class="text-muted">No activities yet.</p>
                <?php else: ?>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activities as $activity): ?>
                                <tr>
                                    <td><?= e($activity['first_name'] ? $activity['first_name'] . ' ' . $activity['last_name'] : 'System') ?></td>
                                    <td><?= e(ucfirst($activity['action'])) ?></td>
                                    <td><?= e(ucfirst($activity['module'])) ?></td>
                                    <td><?= e(time_ago($activity['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Leads -->
        <div class="card">
            <div class="card-header">
                Recent Leads
                <a href="leads.php" class="btn-secondary-custom btn-sm">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_leads)): ?>
                    <p class="text-muted">No leads yet.</p>
                <?php else: ?>
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_leads as $lead): ?>
                                <tr>
                                    <td><?= e($lead['first_name'] . ' ' . $lead['last_name']) ?></td>
                                    <td><?= e($lead['source'] ?: '-') ?></td>
                                    <td><?= status_badge($lead['status']) ?></td>
                                    <td><?= e(time_ago($lead['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="card">
            <div class="card-header">System Information</div>
            <div class="card-body">
                <p><strong>Active Users:</strong> <?= $total_users ?></p>
                <p><strong>Layout:</strong> <?= e(ucfirst($layout_key)) ?></p>
                <p><strong>Theme:</strong> <?= e($role_layout['theme_name'] ?? 'Modern') ?></p>
                <p><strong>Version:</strong> <?= e(APP_VERSION) ?></p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
