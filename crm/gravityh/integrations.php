<?php
/**
 * GRAVITY CRM - Integration Center (SPS 2040 Phase 6)
 * Spec Sec 34: truthful provider statuses, test/disconnect/logs,
 * never claims connected without a real passing test.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('integrations', 'view');

$user = current_user();
$page_title = 'Integration Center';
$active_page = 'integrations';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_permission('integrations', 'edit');
    $action = post('action');
    $provider = sanitize(post('provider'));

    if ($action === 'test' && isset($GLOBALS['INT_PROVIDERS'][$provider])) {
        $res = int_test($provider);
        flash($res['ok'] ? 'success' : ($res['status'] === 'not_configured' ? 'info' : 'error'),
              ucfirst($provider) . ': ' . $res['message']);
        redirect('integrations.php');
    }

    if ($action === 'disconnect' && isset($GLOBALS['INT_PROVIDERS'][$provider])) {
        int_disconnect($provider);
        log_activity('integrations', 'disconnect', null, 'Disconnected ' . $provider);
        flash('success', ucfirst($provider) . ' disconnected.');
        redirect('integrations.php');
    }

    if ($action === 'process_queue') {
        $rep = jobs_process_outbound(50);
        flash('info', 'Queue processed: sent ' . $rep['sent'] . ', failed ' . $rep['failed'] . ', abandoned ' . $rep['abandoned'] . '.');
        redirect('integrations.php');
    }

    if ($action === 'retry_queue') {
        $qid = (int)post('queue_id', 0);
        db_execute("UPDATE outbound_queue SET status = 'queued' WHERE id = ? AND status IN ('failed','abandoned')", [$qid]);
        log_activity('integrations', 'retry_queue', $qid, "Requeued outbound #{$qid}");
        flash('success', 'Item requeued for delivery.');
        redirect('integrations.php');
    }

    flash('error', 'Unknown action.');
    redirect('integrations.php');
}

$view_log = sanitize(get('log', ''));

// Provider rows (catalog joined with persisted state)
$providers = [];
foreach ($GLOBALS['INT_PROVIDERS'] as $key => $meta) {
    $row = int_row($key);
    $providers[] = [
        'key' => $key,
        'label' => $meta['label'],
        'icon' => $meta['icon'],
        'status' => $row['status'],
        'status_message' => $row['status_message'],
        'last_test_at' => $row['last_test_at'],
        'last_test_ok' => $row['last_test_ok'],
        'last_test_message' => $row['last_test_message'],
        'connected_at' => $row['connected_at'],
    ];
}

function int_status_badge(string $s): string {
    $map = [
        'connected'      => ['Connected', '#15803d'],
        'not_configured' => ['Not configured', '#6b7280'],
        'token_expired'  => ['Token expired', '#b45309'],
        'error'          => ['Error', '#b91c1c'],
        'syncing'        => ['Syncing', '#1d4ed8'],
    ];
    [$label, $color] = $map[$s] ?? [ucwords(str_replace('_', ' ', $s)), '#4b5563'];
    return '<span style="background:' . $color . ';color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;">' . e($label) . '</span>';
}

$queue_rows = db_query(
    "SELECT id, provider_key, action, status, attempts, max_attempts, last_error, idempotency_key, created_at
     FROM outbound_queue ORDER BY (status IN ('queued','failed','processing')) DESC, id DESC LIMIT 30"
);

$log_rows = [];
if ($view_log !== '' && isset($GLOBALS['INT_PROVIDERS'][$view_log])) {
    $log_rows = db_query(
        'SELECT action, level, message, duration_ms, created_at FROM integration_logs
         WHERE provider_key = ? ORDER BY id DESC LIMIT 50',
        [$view_log]
    );
}

include __DIR__ . '/header.php';
?>
<div class="page-title">
    <h1><i class="bi bi-plug"></i> Integration Center</h1>
</div>

<?php if ($view_log === ''): ?>

<div class="card"><div class="card-body">
<form method="POST" style="display:inline;">
    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="process_queue">
    <button class="btn-primary-custom"><i class="bi bi-arrow-repeat"></i> Process Outbound Queue</button>
</form>
<span class="text-muted" style="margin-left:10px;">Cron equivalent: <code>php cron.php</code></span>
</div></div>

<div class="card">
<div class="card-header">Providers (<?= count($providers) ?>)</div>
<div class="card-body">
<table class="table-custom">
<thead><tr><th>Provider</th><th>Status</th><th>Last test</th><th>Connected since</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($providers as $p): ?>
<tr>
    <td><i class="bi <?= e($p['icon']) ?>"></i> <strong><?= e($p['label']) ?></strong></td>
    <td><?= int_status_badge($p['status']) ?><?php if (!empty($p['status_message'])): ?><br><small class="text-muted"><?= e(mb_strimwidth((string)$p['status_message'],0,70,'...')) ?></small><?php endif; ?></td>
    <td><?= $p['last_test_at'] ? e(format_datetime($p['last_test_at'])) : '-' ?></td>
    <td><?= $p['connected_at'] ? e(format_datetime($p['connected_at'])) : '-' ?></td>
    <td>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="test">
            <input type="hidden" name="provider" value="<?= e($p['key']) ?>">
            <button class="btn-primary-custom btn-sm" title="Run real connection test"><i class="bi bi-shield-check"></i> Test</button>
        </form>
        <?php if ($p['status'] !== 'not_configured'): ?>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Disconnect <?= e($p['label']) ?>?');">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="disconnect">
            <input type="hidden" name="provider" value="<?= e($p['key']) ?>">
            <button class="btn-danger-custom btn-sm"><i class="bi bi-plug"></i></button>
        </form>
        <?php endif; ?>
        <a class="btn-secondary-custom btn-sm" href="integrations.php?log=<?= e($p['key']) ?>" title="Logs"><i class="bi bi-clock-history"></i></a>
        <a class="btn-secondary-custom btn-sm" href="settings.php" title="Configure in Settings"><i class="bi bi-gear"></i></a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p class="text-muted" style="margin-top:8px;">A provider is only marked <strong>Connected</strong> after a live credential test succeeds. Providers without an available verification flow stay <em>Not configured</em>.</p>
</div></div>

<div class="card">
<div class="card-header">Outbound Queue (latest 30)</div>
<div class="card-body">
<?php if (!$queue_rows): ?>
    <p class="text-muted">Queue is empty.</p>
<?php else: ?>
<table class="table-custom">
<thead><tr><th>#</th><th>Provider</th><th>Action</th><th>Status</th><th>Attempts</th><th>Last error</th><th></th></tr></thead>
<tbody>
<?php foreach ($queue_rows as $q): ?>
<tr>
    <td><?= (int)$q['id'] ?></td>
    <td><?= e($q['provider_key']) ?></td>
    <td><?= e($q['action']) ?></td>
    <td><?= int_status_badge($q['status'] === 'sent' ? 'connected' : ($q['status'] === 'abandoned' || $q['status'] === 'failed' ? 'error' : 'syncing')) ?> <?= e($q['status']) ?></td>
    <td><?= (int)$q['attempts'] ?>/<?= (int)$q['max_attempts'] ?></td>
    <td><small class="text-muted"><?= e(mb_strimwidth((string)($q['last_error'] ?? ''),0,60,'...')) ?></small></td>
    <td>
        <?php if (in_array($q['status'], ['failed','abandoned']) && user_can('integrations','edit')): ?>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="retry_queue">
            <input type="hidden" name="queue_id" value="<?= (int)$q['id'] ?>">
            <button class="btn-secondary-custom btn-sm"><i class="bi bi-arrow-counterclockwise"></i></button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div></div>

<?php else: ?>

<div class="card">
<div class="card-header">
    Logs: <?= e(ucfirst($view_log)) ?>
    <a href="integrations.php" class="btn-secondary-custom btn-sm" style="float:right;">Back</a>
</div>
<div class="card-body">
<?php if (!$log_rows): ?>
    <p class="text-muted">No log entries for this provider yet. Run a Test first.</p>
<?php else: ?>
<table class="table-custom">
<thead><tr><th>When</th><th>Level</th><th>Action</th><th>Message</th><th>ms</th></tr></thead>
<tbody>
<?php foreach ($log_rows as $l): ?>
<tr>
    <td><?= e(format_datetime($l['created_at'])) ?></td>
    <td><?= int_status_badge($l['level']==='error'?'error':($l['level']==='warn'?'token_expired':'connected')) ?></td>
    <td><?= e($l['action']) ?></td>
    <td><?= e(mb_strimwidth((string)$l['message'],0,120,'...')) ?></td>
    <td><?= $l['duration_ms'] !== null ? (int)$l['duration_ms'] : '-' ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div></div>

<?php endif; ?>
<?php include __DIR__ . '/footer.php'; ?>
