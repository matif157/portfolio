<?php
/**
 * GRAVITY CRM - AI Assistant Console (SPS 2040 Phase 7)
 * Sec 17 NL operations · Sec 16 approval gates · Sec 40 usage/cost
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('ai', 'view');

$user = current_user();
$page_title = 'AI Assistant';
$active_page = 'assistant';

$ai_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');

    if ($action === 'ask') {
        require_permission('ai', 'use');
        $msg = trim((string)post('message'));
        if ($msg !== '') {
            $ai_result = ai_assistant($msg, $user);
            log_activity('ai', 'ask', null, mb_substr($msg, 0, 120));
        }
    }

    if ($action === 'decide' && user_can('ai', 'manage')) {
        $did = (int)post('decision_id', 0);
        $approve = post('approve') ? true : false;
        if (ai_decide($did, $approve, (int)$user['id'])) {
            flash('success', 'Decision #' . $did . ($approve ? ' approved and executed.' : ' rejected.'));
        } else {
            flash('error', 'Decision not found or already resolved.');
        }
        redirect('assistant.php');
    }
}

$month_start = date('Y-m-01 00:00:00');
$usage_month = db_row(
    'SELECT COUNT(*) AS calls, COALESCE(SUM(total_tokens),0) AS tokens,
            COALESCE(SUM(est_cost_usd),0) AS cost,
            COALESCE(SUM(CASE WHEN ok = 0 THEN 1 ELSE 0 END),0) AS failures
     FROM ai_usage WHERE created_at >= ?',
    [$month_start]
);

$by_task = db_query(
    'SELECT task_type, COUNT(*) n, SUM(total_tokens) tokens, SUM(est_cost_usd) cost
     FROM ai_usage WHERE created_at >= ? GROUP BY task_type ORDER BY cost DESC LIMIT 6',
    [$month_start]
);

$pending = db_query(
    "SELECT d.*, u.first_name, u.last_name
     FROM ai_decisions d LEFT JOIN users u ON u.id = d.user_id
     WHERE d.approval_status = 'pending'
     ORDER BY d.created_at DESC LIMIT 20"
);

$recent = db_query(
    "SELECT d.*, u.first_name, u.last_name
     FROM ai_decisions d LEFT JOIN users u ON u.id = d.user_id
     ORDER BY d.id DESC LIMIT 12"
);

function ai_conf_badge(?float $c): string {
    if ($c === null) { return '-'; }
    $col = $c >= 0.85 ? '#15803d' : ($c >= 0.70 ? '#a16207' : '#b91c1c');
    return '<span style="color:' . $col . ';font-weight:600;">' . number_format($c * 100, 0) . '%</span>';
}

include __DIR__ . '/header.php';
?>
<div class="page-title">
    <h1><i class="bi bi-stars"></i> AI Assistant</h1>
</div>

<!-- Usage this month (Sec 40) -->
<div class="card"><div class="card-body">
    <strong>This month:</strong>
    <?= (int)$usage_month['calls'] ?> calls ·
    <?= number_format((int)$usage_month['tokens']) ?> tokens ·
    est. $<?= number_format((float)$usage_month['cost'], 4) ?> ·
    failures: <?= (int)$usage_month['failures'] ?>
</div></div>

<!-- Ask -->
<?php if (user_can('ai', 'use')): ?>
<div class="card">
<div class="card-header">Ask the assistant</div>
<div class="card-body">
<form method="POST">
    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="ask">
    <textarea name="message" class="form-control-custom" rows="3" required
        placeholder='e.g. "Show clients missing requirements" · "Summarize client #1" · "Which invoices are overdue?"'></textarea>
    <button class="btn-primary-custom" style="margin-top:8px;"><i class="bi bi-send"></i> Send</button>
</form>

<?php if ($ai_result): ?>
<div style="margin-top:14px;padding:12px;border-left:4px solid <?= $ai_result['ok'] ? '#15803d' : '#b91c1c' ?>;background:#f8fafc;border-radius:6px;">
    <div style="white-space:pre-wrap;"><?= e($ai_result['text']) ?></div>
    <div style="margin-top:8px;font-size:12px;color:#6b7280;">
        <?php if (!empty($ai_result['executed'])): ?>
            ✅ Executed
        <?php elseif (!empty($ai_result['pending'])): ?>
            ⏳ Awaiting administrator approval
        <?php endif; ?>
        &nbsp;|&nbsp; Decision #<?= (int)($ai_result['decision_id'] ?? 0) ?>
        <?= isset($ai_result['model']) ? ' · ' . e($ai_result['provider'] . '/' . $ai_result['model']) : '' ?>
        <?= isset($ai_result['tokens']) ? ' · ' . number_format((int)$ai_result['tokens']) . ' tokens' : '' ?>
    </div>
    <?php if (!empty($ai_result['data'])): ?>
    <pre style="margin-top:8px;font-size:11px;background:#fff;padding:8px;border-radius:4px;overflow:auto;max-height:220px;"><?= e(json_encode($ai_result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    <?php endif; ?>
</div>
<?php endif; ?>
</div></div>
<?php endif; ?>

<!-- Pending approvals (Sec 16 gates) -->
<?php if ($pending): ?>
<div class="card">
<div class="card-header">Pending approvals (<?= count($pending) ?>)</div>
<div class="card-body">
<table class="table-custom">
<thead><tr><th>#</th><th>Requested by</th><th>Action</th><th>Explanation</th><th>Conf.</th><th>Asked</th><th></th></tr></thead>
<tbody>
<?php foreach ($pending as $d): ?>
<tr>
    <td><?= (int)$d['id'] ?></td>
    <td><?= e(trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?: 'system') ?></td>
    <td><code><?= e($d['action_requested']) ?></code></td>
    <td><?= e(mb_strimwidth((string)$d['response_summary'], 0, 80, '...')) ?></td>
    <td><?= ai_conf_badge($d['confidence'] !== null ? (float)$d['confidence'] : null) ?></td>
    <td><?= e(format_datetime($d['created_at'])) ?></td>
    <td>
    <?php if (user_can('ai', 'manage')): ?>
        <form method="POST" style="display:inline;">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="decide">
            <input type="hidden" name="decision_id" value="<?= (int)$d['id'] ?>">
            <button name="approve" value="1" class="btn-primary-custom btn-sm"><i class="bi bi-check"></i></button>
            <button name="approve" value="0" class="btn-danger-custom btn-sm" onclick="this.form.submit()"><i class="bi bi-x"></i></button>
        </form>
    <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>
<?php endif; ?>

<!-- Recent decisions (Sec 18 audit) -->
<div class="card">
<div class="card-header">Recent decisions</div>
<div class="card-body">
<table class="table-custom">
<thead><tr><th>#</th><th>Task</th><th>Model</th><th>Action / Answer</th><th>Conf.</th><th>Status</th><th>When</th></tr></thead>
<tbody>
<?php foreach ($recent as $d): ?>
<tr>
    <td><?= (int)$d['id'] ?></td>
    <td><?= e($d['task_type']) ?></td>
    <td><small><?= e(($d['provider'] ?? '-') . '/' . ($d['model'] ?? '-')) ?></small></td>
    <td><?= e(mb_strimwidth((string)($d['action_requested'] ?: $d['response_summary']), 0, 70, '...')) ?></td>
    <td><?= ai_conf_badge($d['confidence'] !== null ? (float)$d['confidence'] : null) ?></td>
    <td><?= e(ucfirst($d['approval_status'])) ?><?= !empty($d['executed']) ? ' ✓' : '' ?></td>
    <td><?= e(format_datetime($d['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>

<!-- Cost by task -->
<?php if ($by_task): ?>
<div class="card">
<div class="card-header">Cost by task type (this month)</div>
<div class="card-body">
<table class="table-custom">
<thead><tr><th>Task</th><th>Calls</th><th>Tokens</th><th>Est. cost</th></tr></thead>
<tbody>
<?php foreach ($by_task as $t): ?>
<tr>
    <td><?= e($t['task_type']) ?></td>
    <td><?= (int)$t['n'] ?></td>
    <td><?= number_format((int)$t['tokens']) ?></td>
    <td>$<?= number_format((float)$t['cost'], 4) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
