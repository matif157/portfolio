<?php
// ============================================================
// SPS CRM - Dashboard
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();

// ── Stats ─────────────────────────────────────────────────
$stats = [];

// Clients
$stats['total_clients']   = (int)db_val("SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL");
$stats['active_clients']  = (int)db_val("SELECT COUNT(*) FROM clients WHERE status='active' AND deleted_at IS NULL");
$stats['new_clients_month'] = (int)db_val("SELECT COUNT(*) FROM clients WHERE MONTH(created_at)=MONTH(CURRENT_DATE) AND YEAR(created_at)=YEAR(CURRENT_DATE) AND deleted_at IS NULL");

// Projects
$stats['open_projects']     = (int)db_val("SELECT COUNT(*) FROM projects WHERE status='open' AND deleted_at IS NULL");
$stats['upcoming_projects'] = (int)db_val("SELECT COUNT(*) FROM projects WHERE status='upcoming' AND deleted_at IS NULL");
$stats['expired_projects']  = (int)db_val("SELECT COUNT(*) FROM projects WHERE status='expired' AND deleted_at IS NULL");

// Finance
$stats['outstanding_invoices'] = (int)db_val("SELECT COUNT(*) FROM invoices WHERE status IN ('sent','overdue') AND deleted_at IS NULL");
$stats['outstanding_amount']   = (float)db_val("SELECT COALESCE(SUM(amount_due - amount_paid),0) FROM invoices WHERE status IN ('sent','overdue') AND deleted_at IS NULL") ?: 0;
$stats['payments_this_month']  = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=MONTH(CURRENT_DATE) AND YEAR(payment_date)=YEAR(CURRENT_DATE)") ?: 0;

// Services
$stats['active_services']      = (int)db_val("SELECT COUNT(*) FROM client_services WHERE status IN ('active','in_progress')");
$stats['missing_requirements'] = (int)db_val("SELECT COUNT(*) FROM bank_statements WHERE status='missing'");
$stats['pending_approvals']    = (int)db_val("SELECT COUNT(*) FROM approvals WHERE status='pending'");

// Tasks
$stats['my_tasks_today'] = (int)db_val(
    "SELECT COUNT(*) FROM tasks WHERE assigned_to=? AND status!='completed' AND (due_date IS NULL OR due_date<=CURRENT_DATE)",
    [current_user_id()]
);

// Recent clients
$recent_clients = db_rows(
    "SELECT c.*, 
     (SELECT COUNT(*) FROM invoices i WHERE i.client_id=c.id AND i.status IN ('sent','overdue') AND i.deleted_at IS NULL) as unpaid_invoices
     FROM clients c WHERE c.deleted_at IS NULL ORDER BY c.created_at DESC LIMIT 5"
);

// Recent activity
$recent_activity = db_rows(
    "SELECT a.*, CONCAT(u.first_name,' ',u.last_name) as user_name
     FROM activities a LEFT JOIN users u ON u.id=a.user_id
     ORDER BY a.created_at DESC LIMIT 10"
);

// Upcoming reminders
$upcoming_reminders = db_rows(
    "SELECT r.*, CONCAT(u.first_name,' ',u.last_name) as assigned_name
     FROM reminders r LEFT JOIN users u ON u.id=r.assigned_to
     WHERE r.reminder_date >= CURRENT_DATE AND r.status='active'
     ORDER BY r.reminder_date ASC LIMIT 5"
);

// Bank statement missing summary
$missing_accounts = db_rows(
    "SELECT ba.*, c.first_name, c.last_name, b.name as business_name,
     (SELECT COUNT(*) FROM bank_statements bs WHERE bs.bank_account_id=ba.id AND bs.status='missing') as missing_count
     FROM bank_accounts ba
     JOIN businesses b ON b.id=ba.business_id
     JOIN clients c ON c.id=b.client_id
     WHERE ba.is_active=1
     HAVING missing_count > 0
     ORDER BY missing_count DESC LIMIT 5"
);

// Automation status
$auto_status = [
    'email'    => provider_configured('email'),
    'sms'      => provider_configured('sms'),
    'whatsapp' => provider_configured('whatsapp'),
    'voice'    => provider_configured('voice'),
    'ai'       => provider_configured('ai'),
];

render_header('Dashboard', 'dashboard');
?>
<?= render_flash() ?>

<!-- ── Stat Cards ─────────────────────────────── -->
<div class="stat-cards">
  <div class="stat-card primary">
    <div class="stat-value"><?= $stats['total_clients'] ?></div>
    <div class="stat-label">Total Clients</div>
    <div class="stat-sub"><?= $stats['active_clients'] ?> active · <?= $stats['new_clients_month'] ?> new this month</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $stats['active_services'] ?></div>
    <div class="stat-label">Active Services</div>
  </div>
  <div class="stat-card <?= $stats['open_projects'] > 0 ? 'primary' : '' ?>">
    <div class="stat-value"><?= $stats['open_projects'] ?></div>
    <div class="stat-label">Open Projects</div>
    <div class="stat-sub"><?= $stats['upcoming_projects'] ?> upcoming · <?= $stats['expired_projects'] ?> expired</div>
  </div>
  <div class="stat-card <?= $stats['outstanding_invoices'] > 0 ? 'danger' : '' ?>">
    <div class="stat-value"><?= $stats['outstanding_invoices'] ?></div>
    <div class="stat-label">Outstanding Invoices</div>
    <div class="stat-sub"><?= fmt_currency($stats['outstanding_amount']) ?> owed</div>
  </div>
  <div class="stat-card success">
    <div class="stat-value"><?= fmt_currency($stats['payments_this_month']) ?></div>
    <div class="stat-label">Payments This Month</div>
  </div>
  <div class="stat-card <?= $stats['missing_requirements'] > 0 ? 'warning' : '' ?>">
    <div class="stat-value"><?= $stats['missing_requirements'] ?></div>
    <div class="stat-label">Missing Statements</div>
  </div>
  <div class="stat-card <?= $stats['pending_approvals'] > 0 ? 'warning' : '' ?>">
    <div class="stat-value"><?= $stats['pending_approvals'] ?></div>
    <div class="stat-label">Pending Approvals</div>
  </div>
  <div class="stat-card <?= $stats['my_tasks_today'] > 0 ? 'warning' : '' ?>">
    <div class="stat-value"><?= $stats['my_tasks_today'] ?></div>
    <div class="stat-label">My Tasks Due</div>
  </div>
</div>

<!-- ── Main Grid ──────────────────────────────── -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">

<!-- Left column -->
<div style="display:flex;flex-direction:column;gap:20px">

<!-- Recent Clients -->
<div class="card">
  <div class="card-header">
    <h3>Recent Clients</h3>
    <a href="clients.php" class="btn btn-sm btn-secondary">View All</a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Name</th><th>Status</th><th>Unpaid</th><th>Added</th><th></th></tr></thead>
      <tbody>
      <?php if ($recent_clients): foreach ($recent_clients as $c): ?>
      <tr>
        <td><a href="client_profile.php?id=<?= $c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></a></td>
        <td><?= status_badge($c['status']) ?></td>
        <td><?= $c['unpaid_invoices'] > 0 ? '<span class="badge badge-danger">' . $c['unpaid_invoices'] . '</span>' : '<span class="text-muted">—</span>' ?></td>
        <td class="text-muted"><?= fmt_date($c['created_at']) ?></td>
        <td><a href="client_profile.php?id=<?= $c['id'] ?>" class="btn btn-xs btn-secondary">View</a></td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="5" class="text-center text-muted" style="padding:24px">No clients yet. <a href="add_client.php">Add your first client</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Missing Bank Statements -->
<?php if ($missing_accounts): ?>
<div class="card">
  <div class="card-header">
    <h3>⚠️ Missing Bank Statements</h3>
    <a href="bank_statements.php" class="btn btn-sm btn-secondary">Manage</a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Client</th><th>Business</th><th>Account</th><th>Missing</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($missing_accounts as $ba): ?>
      <tr>
        <td><?= e($ba['first_name'] . ' ' . $ba['last_name']) ?></td>
        <td><?= e($ba['business_name']) ?></td>
        <td><?= e($ba['bank_name']) ?> ···<?= e($ba['account_last4']) ?></td>
        <td><span class="badge badge-danger"><?= $ba['missing_count'] ?> months</span></td>
        <td><a href="bank_statements.php?account_id=<?= $ba['id'] ?>" class="btn btn-xs btn-warning">Review</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Recent Activity -->
<div class="card">
  <div class="card-header">
    <h3>Recent Activity</h3>
    <a href="activity_logs.php" class="btn btn-sm btn-secondary">View All</a>
  </div>
  <div style="padding:8px 0">
  <?php if ($recent_activity): foreach ($recent_activity as $act): ?>
  <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 20px;border-bottom:1px solid var(--border)">
    <div style="width:32px;height:32px;border-radius:50%;background:var(--primary-light);color:var(--primary);
      display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
      <?= strtoupper(substr($act['user_name'] ?? 'S', 0, 1)) ?>
    </div>
    <div style="flex:1;min-width:0">
      <div style="font-size:13px"><?= e(truncate($act['description'] ?: $act['action'], 80)) ?></div>
      <div style="font-size:11px;color:var(--text-muted)"><?= e($act['user_name']) ?> · <?= time_ago($act['created_at']) ?></div>
    </div>
  </div>
  <?php endforeach; else: ?>
  <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px">No recent activity</div>
  <?php endif; ?>
  </div>
</div>

</div><!-- /left column -->

<!-- Right column -->
<div style="display:flex;flex-direction:column;gap:20px">

<!-- Quick Actions -->
<div class="card">
  <div class="card-header"><h3>Quick Actions</h3></div>
  <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
    <a href="add_client.php" class="btn btn-primary w-full">+ Add Client</a>
    <a href="bank_statements.php?action=upload" class="btn btn-secondary w-full">📄 Upload Statement</a>
    <a href="invoices.php?action=create" class="btn btn-secondary w-full">🧾 Create Invoice</a>
    <a href="projects.php?action=add" class="btn btn-secondary w-full">📋 New Project</a>
    <a href="tasks.php?action=add" class="btn btn-secondary w-full">✅ Add Task</a>
    <a href="reminders.php?action=add" class="btn btn-secondary w-full">🔔 Set Reminder</a>
  </div>
</div>

<!-- Upcoming Reminders -->
<div class="card">
  <div class="card-header">
    <h3>Upcoming Reminders</h3>
    <a href="reminders.php" class="btn btn-sm btn-secondary">All</a>
  </div>
  <div style="padding:0">
  <?php if ($upcoming_reminders): foreach ($upcoming_reminders as $r): ?>
  <div style="padding:12px 20px;border-bottom:1px solid var(--border)">
    <div style="font-size:13px;font-weight:500"><?= e($r['title']) ?></div>
    <div style="font-size:12px;color:var(--text-muted)"><?= fmt_date($r['reminder_date']) ?> <?= $r['reminder_time'] ? '· ' . date('g:i A', strtotime($r['reminder_time'])) : '' ?></div>
    <?php if ($r['assigned_name']): ?>
    <div style="font-size:11px;color:var(--text-muted)">→ <?= e($r['assigned_name']) ?></div>
    <?php endif; ?>
  </div>
  <?php endforeach; else: ?>
  <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:12px">No upcoming reminders</div>
  <?php endif; ?>
  </div>
</div>

<!-- Automation Status -->
<div class="card">
  <div class="card-header"><h3>Automation Status</h3></div>
  <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
  <?php foreach (['email'=>'📧 Email','sms'=>'📱 SMS','whatsapp'=>'💬 WhatsApp','voice'=>'📞 Voice','ai'=>'🤖 AI'] as $key => $label): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px">
      <span><?= $label ?></span>
      <?php if ($auto_status[$key]): ?>
        <span class="badge badge-success">✓ Connected</span>
      <?php else: ?>
        <span class="badge badge-secondary">✗ Not configured</span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <a href="settings.php?tab=communications" class="btn btn-sm btn-secondary" style="margin-top:4px">Configure</a>
  </div>
</div>

</div><!-- /right column -->
</div><!-- /grid -->

<?php render_footer(); ?>
