<?php
declare(strict_types=1);

$__sidebar_user = function_exists('current_user') ? current_user() : null;
$__sidebar_page = basename($_SERVER['PHP_SELF'] ?? '');

$__sidebar_settings = [];
try {
    if (isset($pdo) && function_exists('table_exists') && table_exists($pdo, 'settings')) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('layout_sidebar','layout_compact','layout_theme','layout_accent')");
        foreach ($stmt as $row) {
            $__sidebar_settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Throwable $e) {
    // Visual settings must never break page rendering.
}

$__sidebar_mode = $__sidebar_settings['layout_sidebar'] ?? 'expanded';
$__sidebar_compact = ($__sidebar_settings['layout_compact'] ?? '0') === '1';
$__sidebar_theme = $__sidebar_settings['layout_theme'] ?? 'dark';
?>
<aside class="sps-sidebar <?= $__sidebar_mode === 'collapsed' ? 'is-collapsed' : '' ?> <?= $__sidebar_compact ? 'is-compact' : '' ?>" data-theme="<?= e($__sidebar_theme) ?>">
    <div class="sps-sidebar-brand">
        <div class="sps-logo-orb">S</div>
        <div class="sps-brand-text">
            <strong>SPS CRM</strong>
            <span>2050 Operations Core</span>
        </div>
        <button type="button" class="sps-sidebar-toggle" id="spsSidebarToggle" aria-label="Toggle sidebar">☰</button>
    </div>

    <div class="sps-sidebar-scroll">
        <div class="sps-nav-label">COMMAND</div>
        <a class="sps-nav-item <?= $__sidebar_page === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
            <span class="sps-nav-icon">⌂</span><span>Dashboard</span>
        </a>

        <div class="sps-nav-label">CRM</div>
        <a class="sps-nav-item <?= $__sidebar_page === 'clients.php' || $__sidebar_page === 'client_profile.php' ? 'active' : '' ?>" href="clients.php">
            <span class="sps-nav-icon">◎</span><span>Clients</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'businesses.php' ? 'active' : '' ?>" href="businesses.php">
            <span class="sps-nav-icon">▣</span><span>Businesses</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'services.php' || $__sidebar_page === 'service_workflow.php' ? 'active' : '' ?>" href="services.php">
            <span class="sps-nav-icon">✦</span><span>Services</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'projects.php' ? 'active' : '' ?>" href="projects.php">
            <span class="sps-nav-icon">◈</span><span>Projects</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'tasks.php' ? 'active' : '' ?>" href="tasks.php">
            <span class="sps-nav-icon">✓</span><span>Tasks</span>
        </a>

        <div class="sps-nav-label">REVENUE</div>
        <a class="sps-nav-item <?= in_array($__sidebar_page, ['quotations.php'], true) ? 'active' : '' ?>" href="quotations.php">
            <span class="sps-nav-icon">⌁</span><span>Quotations</span>
        </a>
        <a class="sps-nav-item <?= in_array($__sidebar_page, ['invoices.php', 'invoice_create.php', 'invoice_view.php'], true) ? 'active' : '' ?>" href="invoices.php">
            <span class="sps-nav-icon">▤</span><span>Invoices</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'payments.php' ? 'active' : '' ?>" href="payments.php">
            <span class="sps-nav-icon">$</span><span>Payments</span>
        </a>

        <div class="sps-nav-label">OPERATIONS</div>
        <a class="sps-nav-item <?= $__sidebar_page === 'bank_statements.php' ? 'active' : '' ?>" href="bank_statements.php">
            <span class="sps-nav-icon">⌗</span><span>Bank Statements</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'documents.php' ? 'active' : '' ?>" href="documents.php">
            <span class="sps-nav-icon">□</span><span>Documents</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'calendar.php' ? 'active' : '' ?>" href="calendar.php">
            <span class="sps-nav-icon">◫</span><span>Calendar</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'service_automation.php' ? 'active' : '' ?>" href="service_automation.php">
            <span class="sps-nav-icon">⚡</span><span>Automation</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'approvals.php' ? 'active' : '' ?>" href="approvals.php">
            <span class="sps-nav-icon">◆</span><span>Approvals</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'exceptions.php' ? 'active' : '' ?>" href="exceptions.php">
            <span class="sps-nav-icon">!</span><span>Exceptions</span>
        </a>

        <div class="sps-nav-label">INTELLIGENCE</div>
        <a class="sps-nav-item <?= $__sidebar_page === 'reports.php' ? 'active' : '' ?>" href="reports.php">
            <span class="sps-nav-icon">◌</span><span>Reports</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'notifications.php' ? 'active' : '' ?>" href="notifications.php">
            <span class="sps-nav-icon">🔔</span><span>Notifications</span>
        </a>

        <div class="sps-nav-label">CONTROL</div>
        <a class="sps-nav-item <?= $__sidebar_page === 'users.php' ? 'active' : '' ?>" href="users.php">
            <span class="sps-nav-icon">◉</span><span>Users</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'customizer.php' ? 'active' : '' ?>" href="customizer.php">
            <span class="sps-nav-icon">⌘</span><span>Customizer</span>
        </a>
        <a class="sps-nav-item <?= $__sidebar_page === 'settings.php' ? 'active' : '' ?>" href="settings.php">
            <span class="sps-nav-icon">⚙</span><span>Layout Settings</span>
        </a>
    </div>

    <div class="sps-sidebar-footer">
        <div class="sps-user-chip">
            <div class="sps-user-avatar"><?= e(strtoupper(substr(($__sidebar_user['first_name'] ?? 'S'), 0, 1))) ?></div>
            <div class="sps-user-info">
                <strong><?= e(trim(($__sidebar_user['first_name'] ?? 'SPS').' '.($__sidebar_user['last_name'] ?? 'User'))) ?></strong>
                <span><?= e($__sidebar_user['role_name'] ?? 'User') ?></span>
            </div>
        </div>
        <a class="sps-logout" href="logout.php">↗ Sign out</a>
    </div>
</aside>
