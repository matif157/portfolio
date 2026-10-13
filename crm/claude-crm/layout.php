<?php
// ============================================================
// SPS CRM - Shared Layout Helpers
// Usage: render_header('Page Title') and render_footer()
// ============================================================

function render_header(string $page_title = '', string $active_menu = ''): void {
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/functions.php';

    $user_name  = current_user_name();
    $user_id    = current_user_id();
    $role       = current_role();
    $notif_count = get_unread_notification_count($user_id);
    $app_name   = APP_NAME;
    $full_title  = $page_title ? $page_title . ' — ' . APP_SHORT : APP_SHORT;
    $csrf        = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($full_title) ?></title>
<meta name="csrf-token" content="<?= e($csrf) ?>">
<style>
/* ── Global Reset & Variables ─────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --primary:#2563eb;--primary-dark:#1d4ed8;--primary-light:#eff6ff;
  --secondary:#64748b;--success:#16a34a;--danger:#dc2626;
  --warning:#d97706;--info:#0891b2;--dark:#0f172a;--light:#f8fafc;
  --border:#e2e8f0;--text:#1e293b;--text-muted:#64748b;
  --sidebar-w:240px;--header-h:56px;--radius:8px;
  --shadow:0 1px 3px rgba(0,0,0,.1),0 1px 2px rgba(0,0,0,.06);
  --shadow-md:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -1px rgba(0,0,0,.06);
}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
  font-size:14px;color:var(--text);background:#f1f5f9;line-height:1.5}
a{color:var(--primary);text-decoration:none}
a:hover{color:var(--primary-dark);text-decoration:underline}
img{max-width:100%}
/* ── Layout ───────────────────────────────────────────── */
#app{display:flex;min-height:100vh}
#sidebar{width:var(--sidebar-w);background:var(--dark);color:#cbd5e1;
  flex-shrink:0;overflow-y:auto;position:fixed;top:0;left:0;
  height:100vh;z-index:100;display:flex;flex-direction:column}
#main-content{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column}
#header{height:var(--header-h);background:#fff;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 24px;position:sticky;top:0;z-index:50;box-shadow:var(--shadow)}
#page-body{padding:24px;flex:1}
/* ── Sidebar ──────────────────────────────────────────── */
.sidebar-logo{padding:16px 20px;border-bottom:1px solid #1e293b;
  font-size:15px;font-weight:700;color:#f1f5f9;letter-spacing:.3px}
.sidebar-logo span{color:var(--primary)}
.sidebar-section{padding:8px 0}
.sidebar-section-label{padding:6px 16px 4px;font-size:10px;font-weight:600;
  letter-spacing:.8px;text-transform:uppercase;color:#475569}
.sidebar-link{display:flex;align-items:center;gap:8px;padding:7px 16px;
  color:#94a3b8;font-size:13px;cursor:pointer;transition:all .15s;
  border-left:3px solid transparent}
.sidebar-link:hover{color:#f1f5f9;background:#1e293b;text-decoration:none}
.sidebar-link.active{color:#fff;background:#1e293b;border-left-color:var(--primary)}
.sidebar-link .icon{width:16px;text-align:center;font-style:normal}
.sidebar-group summary{display:flex;align-items:center;gap:8px;padding:7px 16px;
  color:#94a3b8;font-size:13px;cursor:pointer;list-style:none;
  border-left:3px solid transparent;transition:all .15s}
.sidebar-group summary:hover{color:#f1f5f9;background:#1e293b}
.sidebar-group summary::after{content:'▸';margin-left:auto;font-size:11px;transition:.2s}
.sidebar-group[open] summary::after{content:'▾'}
.sidebar-group .sub-links{background:#0a1628}
.sidebar-group .sub-links a{display:block;padding:6px 16px 6px 40px;color:#64748b;
  font-size:12.5px;transition:all .15s}
.sidebar-group .sub-links a:hover{color:#f1f5f9;background:#1e293b;text-decoration:none}
.sidebar-group .sub-links a.active{color:#93c5fd}
/* ── Header ───────────────────────────────────────────── */
.header-left{font-weight:600;font-size:15px;color:var(--text)}
.header-right{display:flex;align-items:center;gap:16px}
.notif-btn{position:relative;background:none;border:none;cursor:pointer;
  font-size:20px;padding:4px;line-height:1}
.notif-badge{position:absolute;top:-4px;right:-4px;background:var(--danger);
  color:#fff;font-size:10px;font-weight:700;border-radius:50%;
  width:18px;height:18px;display:flex;align-items:center;justify-content:center}
.user-menu{position:relative}
.user-btn{display:flex;align-items:center;gap:8px;background:none;border:none;
  cursor:pointer;font-size:13px;color:var(--text);padding:4px 8px;border-radius:6px}
.user-btn:hover{background:var(--light)}
.user-avatar{width:32px;height:32px;border-radius:50%;background:var(--primary);
  color:#fff;display:flex;align-items:center;justify-content:center;
  font-weight:600;font-size:13px}
.dropdown-menu{position:absolute;right:0;top:calc(100% + 4px);background:#fff;
  border:1px solid var(--border);border-radius:var(--radius);
  box-shadow:var(--shadow-md);min-width:180px;z-index:200;display:none}
.dropdown-menu.open{display:block}
.dropdown-menu a,.dropdown-menu button{display:flex;align-items:center;gap:8px;
  padding:9px 16px;color:var(--text);font-size:13px;width:100%;
  background:none;border:none;cursor:pointer;text-align:left}
.dropdown-menu a:hover,.dropdown-menu button:hover{background:var(--light);text-decoration:none}
.dropdown-divider{border-top:1px solid var(--border);margin:4px 0}
/* ── Cards ────────────────────────────────────────────── */
.card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);
  box-shadow:var(--shadow)}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between}
.card-header h2,.card-header h3{font-size:15px;font-weight:600;margin:0}
.card-body{padding:20px}
.stat-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:24px}
.stat-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);
  padding:20px;box-shadow:var(--shadow)}
.stat-card .stat-value{font-size:28px;font-weight:700;color:var(--text);line-height:1}
.stat-card .stat-label{font-size:12px;color:var(--text-muted);margin-top:4px}
.stat-card .stat-sub{font-size:12px;color:var(--text-muted);margin-top:8px;padding-top:8px;border-top:1px solid var(--border)}
.stat-card.primary .stat-value{color:var(--primary)}
.stat-card.success .stat-value{color:var(--success)}
.stat-card.danger  .stat-value{color:var(--danger)}
.stat-card.warning .stat-value{color:var(--warning)}
/* ── Tables ───────────────────────────────────────────── */
.table-wrapper{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 14px;text-align:left;border-bottom:1px solid var(--border)}
th{font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.4px;
  color:var(--text-muted);background:#f8fafc}
tbody tr:hover{background:#f8fafc}
/* ── Forms ────────────────────────────────────────────── */
.form-group{margin-bottom:16px}
.form-group label{display:block;font-weight:500;font-size:13px;margin-bottom:5px;color:var(--text)}
.form-control{width:100%;padding:8px 12px;border:1px solid var(--border);
  border-radius:6px;font-size:14px;color:var(--text);background:#fff;
  transition:border-color .15s}
.form-control:focus{outline:none;border-color:var(--primary);
  box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.form-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px}
.form-actions{display:flex;gap:10px;margin-top:20px;padding-top:20px;border-top:1px solid var(--border)}
select.form-control{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2364748b'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right 10px center;padding-right:28px}
textarea.form-control{resize:vertical;min-height:80px}
/* ── Buttons ──────────────────────────────────────────── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;
  border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;
  border:1px solid transparent;transition:all .15s;line-height:1.4;text-decoration:none}
.btn:hover{text-decoration:none}
.btn-primary{background:var(--primary);color:#fff;border-color:var(--primary)}
.btn-primary:hover{background:var(--primary-dark);border-color:var(--primary-dark);color:#fff}
.btn-secondary{background:#fff;color:var(--text);border-color:var(--border)}
.btn-secondary:hover{background:#f8fafc}
.btn-success{background:var(--success);color:#fff}
.btn-success:hover{background:#15803d;color:#fff}
.btn-danger{background:var(--danger);color:#fff}
.btn-danger:hover{background:#b91c1c;color:#fff}
.btn-warning{background:var(--warning);color:#fff}
.btn-sm{padding:5px 10px;font-size:12px}
.btn-xs{padding:3px 8px;font-size:11px}
/* ── Badges ───────────────────────────────────────────── */
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:12px;
  font-size:11px;font-weight:600;letter-spacing:.2px}
.badge-primary{background:#dbeafe;color:#1d4ed8}
.badge-success{background:#dcfce7;color:#15803d}
.badge-danger{background:#fee2e2;color:#b91c1c}
.badge-warning{background:#fef3c7;color:#92400e}
.badge-info{background:#e0f2fe;color:#0369a1}
.badge-secondary{background:#f1f5f9;color:#475569}
.badge-dark{background:#e2e8f0;color:#1e293b}
/* ── Alerts ───────────────────────────────────────────── */
.alert{padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:13px}
.alert-success{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.alert-danger{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
.alert-warning{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.alert-info{background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd}
/* ── Modals ───────────────────────────────────────────── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
  z-index:500;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:var(--radius);max-width:560px;width:90%;
  max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-md)}
.modal-header{padding:16px 20px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between}
.modal-header h3{font-size:15px;font-weight:600;margin:0}
.modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);line-height:1}
.modal-body{padding:20px}
.modal-footer{padding:16px 20px;border-top:1px solid var(--border);
  display:flex;gap:10px;justify-content:flex-end}
/* ── Pagination ───────────────────────────────────────── */
.pagination{display:flex;align-items:center;gap:4px;margin-top:16px;flex-wrap:wrap}
.pagination a{padding:6px 10px;border:1px solid var(--border);border-radius:5px;
  font-size:13px;color:var(--text);transition:.15s}
.pagination a:hover{background:var(--primary);color:#fff;border-color:var(--primary);text-decoration:none}
.pagination a.active{background:var(--primary);color:#fff;border-color:var(--primary)}
.pg-info{margin-left:8px;font-size:12px;color:var(--text-muted)}
/* ── Search bar ───────────────────────────────────────── */
.search-bar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
.search-input{flex:1;min-width:200px;max-width:320px}
/* ── Misc ─────────────────────────────────────────────── */
.text-muted{color:var(--text-muted)}
.text-danger{color:var(--danger)}
.text-success{color:var(--success)}
.text-center{text-align:center}
.mt-1{margin-top:4px}.mt-2{margin-top:8px}.mt-3{margin-top:16px}.mt-4{margin-top:24px}
.mb-1{margin-bottom:4px}.mb-2{margin-bottom:8px}.mb-3{margin-bottom:16px}
.gap-1{gap:4px}.gap-2{gap:8px}
.flex{display:flex}.flex-wrap{flex-wrap:wrap}.items-center{align-items:center}.justify-between{justify-content:space-between}
.w-full{width:100%}.hidden{display:none}
.page-title{font-size:20px;font-weight:700;margin-bottom:20px;color:var(--text)}
.section-title{font-size:16px;font-weight:600;margin-bottom:12px;color:var(--text)}
hr{border:none;border-top:1px solid var(--border);margin:16px 0}
/* Notification dropdown */
.notif-dropdown{position:absolute;right:0;top:calc(100% + 8px);background:#fff;
  border:1px solid var(--border);border-radius:var(--radius);
  box-shadow:var(--shadow-md);width:340px;z-index:200;display:none}
.notif-dropdown.open{display:block}
.notif-dropdown-header{padding:12px 16px;border-bottom:1px solid var(--border);
  display:flex;justify-content:space-between;align-items:center;font-weight:600;font-size:13px}
.notif-item{padding:12px 16px;border-bottom:1px solid var(--border);font-size:13px;cursor:pointer}
.notif-item:hover{background:var(--light)}
.notif-item.unread{background:#eff6ff}
.notif-item-title{font-weight:500}
.notif-item-time{font-size:11px;color:var(--text-muted);margin-top:2px}
.notif-dropdown-footer{padding:10px 16px;text-align:center;font-size:12px}
/* Workflow progress */
.workflow-steps{display:flex;flex-direction:column;gap:4px}
.workflow-step{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:6px;font-size:13px}
.workflow-step.done{color:var(--success)}
.workflow-step.active{background:var(--primary-light);color:var(--primary);font-weight:600}
.workflow-step.pending{color:var(--text-muted)}
.workflow-step .step-icon{width:20px;height:20px;border-radius:50%;border:2px solid currentColor;
  display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
/* Missing months grid */
.months-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px}
.month-cell{padding:10px;border:1px solid var(--border);border-radius:6px;text-align:center;font-size:12px}
.month-cell.received{background:#dcfce7;border-color:#86efac;color:#15803d}
.month-cell.missing{background:#fee2e2;border-color:#fca5a5;color:#b91c1c}
.month-cell.pending{background:#fef3c7;border-color:#fde68a;color:#92400e}
.month-cell .month-name{font-weight:600}
.month-cell .month-status{font-size:10px;margin-top:2px}
/* Responsive */
@media(max-width:768px){
  #sidebar{transform:translateX(-100%);transition:.3s}
  #sidebar.open{transform:translateX(0)}
  #main-content{margin-left:0}
}
</style>
</head>
<body>
<div id="app">
<!-- ── Sidebar ───────────────────────────────────── -->
<nav id="sidebar">
  <div class="sidebar-logo"><span>SPS</span> CRM</div>

  <div class="sidebar-section">
    <a href="dashboard.php" class="sidebar-link <?= $active_menu==='dashboard'?'active':'' ?>">
      <i class="icon">⊞</i> Dashboard
    </a>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-section-label">CRM</div>
    <details class="sidebar-group" <?= in_array($active_menu,['clients','businesses','contacts','leads'])?'open':'' ?>>
      <summary><i class="icon">👥</i> CRM</summary>
      <div class="sub-links">
        <a href="clients.php" class="<?= $active_menu==='clients'?'active':'' ?>">Clients</a>
        <a href="businesses.php" class="<?= $active_menu==='businesses'?'active':'' ?>">Businesses</a>
        <a href="contacts.php" class="<?= $active_menu==='contacts'?'active':'' ?>">Contacts</a>
        <a href="leads.php" class="<?= $active_menu==='leads'?'active':'' ?>">Leads</a>
      </div>
    </details>

    <details class="sidebar-group" <?= in_array($active_menu,['services','service_view','service_workflow','service_requirements','service_automation'])?'open':'' ?>>
      <summary><i class="icon">⚙️</i> Services</summary>
      <div class="sub-links">
        <a href="services.php" class="<?= $active_menu==='services'?'active':'' ?>">Service Catalog</a>
        <a href="service_requirements.php" class="<?= $active_menu==='service_requirements'?'active':'' ?>">Requirements</a>
        <a href="service_workflow.php" class="<?= $active_menu==='service_workflow'?'active':'' ?>">Workflows</a>
        <a href="service_automation.php" class="<?= $active_menu==='service_automation'?'active':'' ?>">Automation</a>
      </div>
    </details>

    <details class="sidebar-group" <?= in_array($active_menu,['projects','tasks','calendar','reminders'])?'open':'' ?>>
      <summary><i class="icon">📋</i> Operations</summary>
      <div class="sub-links">
        <a href="projects.php" class="<?= $active_menu==='projects'?'active':'' ?>">Projects</a>
        <a href="tasks.php" class="<?= $active_menu==='tasks'?'active':'' ?>">Tasks</a>
        <a href="calendar.php" class="<?= $active_menu==='calendar'?'active':'' ?>">Calendar</a>
        <a href="reminders.php" class="<?= $active_menu==='reminders'?'active':'' ?>">Reminders</a>
      </div>
    </details>

    <details class="sidebar-group" <?= in_array($active_menu,['quotations','invoices','payments'])?'open':'' ?>>
      <summary><i class="icon">💰</i> Finance</summary>
      <div class="sub-links">
        <a href="quotations.php" class="<?= $active_menu==='quotations'?'active':'' ?>">Quotations</a>
        <a href="invoices.php" class="<?= $active_menu==='invoices'?'active':'' ?>">Invoices</a>
        <a href="payments.php" class="<?= $active_menu==='payments'?'active':'' ?>">Payments</a>
      </div>
    </details>

    <details class="sidebar-group" <?= in_array($active_menu,['documents','secure_area','bank_statements'])?'open':'' ?>>
      <summary><i class="icon">📁</i> Documents</summary>
      <div class="sub-links">
        <a href="documents.php" class="<?= $active_menu==='documents'?'active':'' ?>">Documents</a>
        <a href="secure_area.php" class="<?= $active_menu==='secure_area'?'active':'' ?>">Secure Area</a>
        <a href="bank_statements.php" class="<?= $active_menu==='bank_statements'?'active':'' ?>">Bank Statements</a>
      </div>
    </details>

    <details class="sidebar-group" <?= in_array($active_menu,['communications','email','notifications'])?'open':'' ?>>
      <summary><i class="icon">💬</i> Communication</summary>
      <div class="sub-links">
        <a href="communications.php" class="<?= $active_menu==='communications'?'active':'' ?>">Communications</a>
        <a href="email.php" class="<?= $active_menu==='email'?'active':'' ?>">Email</a>
        <a href="notifications.php" class="<?= $active_menu==='notifications'?'active':'' ?>">Notifications</a>
      </div>
    </details>

    <details class="sidebar-group" <?= in_array($active_menu,['reports'])?'open':'' ?>>
      <summary><i class="icon">📊</i> Reports</summary>
      <div class="sub-links">
        <a href="reports.php?tab=crm">CRM Reports</a>
        <a href="reports.php?tab=service">Service Reports</a>
        <a href="reports.php?tab=project">Project Reports</a>
        <a href="reports.php?tab=financial">Financial Reports</a>
        <a href="reports.php?tab=activity">Activity Reports</a>
      </div>
    </details>

    <?php if (can('admin.users')): ?>
    <details class="sidebar-group" <?= in_array($active_menu,['users','roles','permissions','customizer','settings'])?'open':'' ?>>
      <summary><i class="icon">🔧</i> Administration</summary>
      <div class="sub-links">
        <a href="users.php" class="<?= $active_menu==='users'?'active':'' ?>">Users</a>
        <a href="roles.php" class="<?= $active_menu==='roles'?'active':'' ?>">Roles</a>
        <a href="permissions.php" class="<?= $active_menu==='permissions'?'active':'' ?>">Permissions</a>
        <a href="customizer.php" class="<?= $active_menu==='customizer'?'active':'' ?>">Customizer</a>
        <a href="settings.php" class="<?= $active_menu==='settings'?'active':'' ?>">Settings</a>
      </div>
    </details>
    <?php endif; ?>

    <details class="sidebar-group" <?= in_array($active_menu,['ai_assistant','ai_automation'])?'open':'' ?>>
      <summary><i class="icon">🤖</i> AI</summary>
      <div class="sub-links">
        <a href="ai_assistant.php" class="<?= $active_menu==='ai_assistant'?'active':'' ?>">AI Assistant</a>
        <a href="ai_automation.php" class="<?= $active_menu==='ai_automation'?'active':'' ?>">AI Automation</a>
      </div>
    </details>

    <a href="activity_logs.php" class="sidebar-link <?= $active_menu==='activity_logs'?'active':'' ?>">
      <i class="icon">📝</i> Activity Logs
    </a>
  </div>
</nav>

<!-- ── Main ──────────────────────────────────────── -->
<div id="main-content">
  <!-- Header -->
  <header id="header">
    <div class="header-left"><?= e($page_title ?: APP_SHORT) ?></div>
    <div class="header-right">
      <!-- Notifications -->
      <div style="position:relative">
        <button class="notif-btn" id="notif-btn" title="Notifications">
          🔔
          <?php if ($notif_count > 0): ?>
          <span class="notif-badge"><?= $notif_count > 9 ? '9+' : $notif_count ?></span>
          <?php endif; ?>
        </button>
        <div class="notif-dropdown" id="notif-dropdown">
          <div class="notif-dropdown-header">
            <span>Notifications</span>
            <a href="notifications.php" style="font-size:12px;font-weight:400">View all</a>
          </div>
          <div id="notif-list"><div style="padding:20px;text-align:center;color:var(--text-muted);font-size:12px">Loading…</div></div>
          <div class="notif-dropdown-footer">
            <a href="activity_logs.php">View all activity</a>
          </div>
        </div>
      </div>

      <!-- User menu -->
      <div class="user-menu">
        <button class="user-btn" id="user-btn">
          <div class="user-avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
          <span><?= e($user_name) ?></span>
          <span style="font-size:10px;color:var(--text-muted)">▾</span>
        </button>
        <div class="dropdown-menu" id="user-dropdown">
          <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
            <div style="font-weight:600;font-size:13px"><?= e($user_name) ?></div>
            <div style="font-size:12px;color:var(--text-muted)"><?= e($role) ?></div>
          </div>
          <a href="change_password.php">🔑 Change Password</a>
          <div class="dropdown-divider"></div>
          <a href="logout.php" onclick="return confirm('Log out?')">🚪 Logout</a>
        </div>
      </div>
    </div>
  </header>

  <!-- Page Body -->
  <div id="page-body">
<?php
}

function render_footer(): void {
?>
  </div><!-- /page-body -->
</div><!-- /main-content -->
</div><!-- /app -->

<script>
// ── Dropdowns ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  // User dropdown
  const userBtn = document.getElementById('user-btn');
  const userDropdown = document.getElementById('user-dropdown');
  if (userBtn) {
    userBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      userDropdown.classList.toggle('open');
      document.getElementById('notif-dropdown').classList.remove('open');
    });
  }

  // Notifications
  const notifBtn = document.getElementById('notif-btn');
  const notifDropdown = document.getElementById('notif-dropdown');
  if (notifBtn) {
    notifBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      notifDropdown.classList.toggle('open');
      if (notifDropdown.classList.contains('open')) loadNotifications();
      userDropdown.classList.remove('open');
    });
  }

  // Close dropdowns on outside click
  document.addEventListener('click', function() {
    document.querySelectorAll('.dropdown-menu,.notif-dropdown').forEach(el => el.classList.remove('open'));
  });

  // Flash messages auto-hide
  document.querySelectorAll('.alert').forEach(function(el) {
    setTimeout(function() { el.style.opacity = '0'; el.style.transition = 'opacity .5s'; setTimeout(() => el.remove(), 500); }, 5000);
  });
});

function loadNotifications() {
  fetch('ajax_notifications.php')
    .then(r => r.json())
    .then(data => {
      const list = document.getElementById('notif-list');
      if (!data.length) {
        list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:12px">No new notifications</div>';
        return;
      }
      list.innerHTML = data.slice(0, 8).map(n =>
        `<div class="notif-item ${n.is_read?'':'unread'}" onclick="markNotifRead(${n.id}, '${n.link||''}')">
          <div class="notif-item-title">${escHtml(n.title)}</div>
          <div style="font-size:12px;color:var(--text-muted)">${escHtml(n.message)}</div>
          <div class="notif-item-time">${escHtml(n.time_ago)}</div>
        </div>`
      ).join('');
    }).catch(() => {});
}

function markNotifRead(id, link) {
  fetch('ajax_notifications.php?action=mark_read&id=' + id, {method:'POST'}).then(() => {
    if (link) window.location.href = link;
    else loadNotifications();
  });
}

function escHtml(str) {
  const d = document.createElement('div'); d.textContent = str; return d.innerHTML;
}

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

// Confirm delete helper
function confirmDelete(msg, url) {
  if (confirm(msg || 'Delete this record?')) window.location.href = url;
}

// Modal helpers
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
});
</script>
</body>
</html>
<?php
}
