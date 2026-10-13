<?php
/**
 * GRAVITY CRM — System Error Logs & Diagnostics Center
 *
 * Central log viewer for errors, warnings, and system diagnostics.
 * Admin-only access for operational troubleshooting.
 */

require_once __DIR__ . '/auth.php';
require_login();
require_permission('admin.settings');

// Handle Clear All Logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_logs') {
    verify_csrf();
    $cleared = $pdo->exec("DELETE FROM error_logs");
    log_activity('Clear Error Logs', 'admin', 0, "Admin cleared all $cleared system error log entries", 'stage2_sensitive');
    set_flash('success', 'All system error logs have been cleared.');
    redirect('error_logs.php');
}

// Handle Delete Single Log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_log') {
    verify_csrf();
    $log_id = intval($_POST['log_id'] ?? 0);
    if ($log_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM error_logs WHERE id = ?");
        $stmt->execute([$log_id]);
    }
    redirect('error_logs.php');
}

// Filters
$level_filter = trim($_GET['level'] ?? '');

$query = "
    SELECT e.*, u.full_name
    FROM error_logs e
    LEFT JOIN users u ON e.user_id = u.id
    WHERE 1=1
";
$params = [];
if ($level_filter !== '') {
    $query .= " AND e.level = ?";
    $params[] = $level_filter;
}
$query .= " ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$errors = $stmt->fetchAll();

// Summary counts
$count_critical = (int)$pdo->query("SELECT COUNT(*) FROM error_logs WHERE level = 'critical'")->fetchColumn();
$count_error    = (int)$pdo->query("SELECT COUNT(*) FROM error_logs WHERE level = 'error'")->fetchColumn();
$count_warning  = (int)$pdo->query("SELECT COUNT(*) FROM error_logs WHERE level = 'warning'")->fetchColumn();
$count_info     = (int)$pdo->query("SELECT COUNT(*) FROM error_logs WHERE level = 'info'")->fetchColumn();
$total_errors   = (int)$pdo->query("SELECT COUNT(*) FROM error_logs")->fetchColumn();

render_header('System Error Logs', 'admin');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🐞 System Error Logs</h1>
        <p class="page-subtitle">Central diagnostics center — system errors, integration failures, and automation exceptions</p>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-bottom:20px;">
    <?php if ($total_errors > 0): ?>
        <div class="stat-card">
            <span class="stat-label">Total Errors</span>
            <span class="stat-value" style="color:var(--danger);"><?= number_format($total_errors) ?></span>
            <span class="stat-desc">All system error log entries</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Critical</span>
            <span class="stat-value" style="color:var(--danger);"><?= number_format($count_critical) ?></span>
            <span class="stat-desc">Fatal / unrecoverable errors</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Errors</span>
            <span class="stat-value" style="color:#dc2626;"><?= number_format($count_error) ?></span>
            <span class="stat-desc">Runtime errors</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Warnings</span>
            <span class="stat-value" style="color:#ca8a04;"><?= number_format($count_warning) ?></span>
            <span class="stat-desc">Non-fatal issues</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Info</span>
            <span class="stat-value" style="color:#2563eb;"><?= number_format($count_info) ?></span>
            <span class="stat-desc">Informational entries</span>
        </div>
    <?php else: ?>
        <div class="stat-card" style="grid-column: 1 / -1; text-align:center;">
            <span class="stat-value" style="color:var(--success); font-size:28px;">✓</span>
            <span class="stat-desc">System is healthy — no error logs recorded.</span>
        </div>
    <?php endif; ?>
</div>

<!-- Level Filters -->
<div class="card" style="margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="error_logs.php" class="btn btn-sm <?= empty($level_filter) ? 'btn-primary' : 'btn-secondary' ?>">All Errors</a>
            <a href="error_logs.php?level=error" class="btn btn-sm <?= $level_filter === 'error' ? 'btn-primary' : 'btn-secondary' ?>">Errors</a>
            <a href="error_logs.php?level=critical" class="btn btn-sm <?= $level_filter === 'critical' ? 'btn-primary' : 'btn-secondary' ?>">Critical</a>
            <a href="error_logs.php?level=warning" class="btn btn-sm <?= $level_filter === 'warning' ? 'btn-primary' : 'btn-secondary' ?>">Warnings</a>
            <a href="error_logs.php?level=info" class="btn btn-sm <?= $level_filter === 'info' ? 'btn-primary' : 'btn-secondary' ?>">Info</a>
        </div>
        <?php if ($total_errors > 0): ?>
            <form method="POST" onsubmit="return confirm('Permanently delete ALL error logs? This cannot be undone.');" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="btn btn-sm btn-secondary" style="background:#fff5f5; color:#dc2626; border-color:#fecaca;">Clear All Logs</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Error Logs Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Level</th>
                    <th>Source</th>
                    <th>Message</th>
                    <th>File / Line</th>
                    <th>User</th>
                    <th>Trace</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($errors)): ?>
                    <tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px;">No error logs recorded for the selected filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($errors as $err): ?>
                        <tr>
                            <td><span style="font-size:12px; color:var(--text-muted);"><?= format_datetime($err['created_at']) ?></span></td>
                            <td>
                                <?php
                                $badgeClass = 'badge-secondary';
                                if ($err['level'] === 'critical' || $err['level'] === 'error') $badgeClass = 'badge-danger';
                                elseif ($err['level'] === 'warning') $badgeClass = 'badge-warning';
                                elseif ($err['level'] === 'info') $badgeClass = 'badge-info';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= e(ucfirst($err['level'])) ?></span>
                            </td>
                            <td><code><?= e($err['source']) ?></code></td>
                            <td><?= e($err['message']) ?></td>
                            <td>
                                <?php if (!empty($err['file'])): ?>
                                    <code><?= e(basename($err['file'])) ?>:<?= e($err['line']) ?></code>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:11px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($err['full_name'])): ?>
                                    <strong><?= e($err['full_name']) ?></strong>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:11px;">System / Automated</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($err['trace'])): ?>
                                    <button type="button" class="btn btn-xs btn-secondary" onclick="toggleTrace(this)" style="padding:2px 6px; font-size:11px;">View</button>
                                    <div class="trace-content" style="display:none; margin-top:6px;">
                                        <pre style="font-size:10px; background:#f8f9fa; border:1px solid var(--border-color); border-radius:4px; padding:8px; max-width:300px; overflow:auto;"><?= e($err['trace']) ?></pre>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:11px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <form method="POST" onsubmit="return confirm('Delete this error log entry?');" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_log">
                                    <input type="hidden" name="log_id" value="<?= $err['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-secondary" style="padding:2px 6px; font-size:11px;">Delete</button>
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

<script>
function toggleTrace(btn) {
    const div = btn.nextElementSibling;
    if (div.style.display === 'none') {
        div.style.display = 'block';
        btn.textContent = 'Hide';
    } else {
        div.style.display = 'none';
        btn.textContent = 'View';
    }
}
</script>