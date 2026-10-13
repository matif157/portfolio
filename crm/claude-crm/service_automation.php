<?php
// ============================================================
// SPS CRM - Automation Engine (rules + logs)
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();

$events = [
    'CLIENT_CREATED','SERVICE_REQUESTED','DOCUMENT_UPLOADED','DOCUMENT_MISSING',
    'CLIENT_RESPONDED','TASK_COMPLETED','DEADLINE_APPROACHING','INVOICE_CREATED',
    'PAYMENT_RECEIVED','PROJECT_EXPIRED','PROJECT_COMPLETED',
];
$action_types = ['send_email','send_sms','send_whatsapp','trigger_voice_call','create_task','notify_staff','escalate'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_rule') {
    verify_csrf();
    require_permission('services.manage');
    $name    = trim($_POST['name'] ?? '');
    $trigger = $_POST['trigger_event'] ?? '';
    $atype   = $_POST['action_type'] ?? '';
    $delay   = (int)($_POST['delay_days'] ?? 0);
    $active  = !empty($_POST['is_active']) ? 1 : 0;

    if ($name && in_array($trigger, $events, true) && in_array($atype, $action_types, true)) {
        db_insert(
            "INSERT INTO automation_rules (name, trigger_event, action_type, delay_days, is_active, created_at) VALUES (?,?,?,?,?,NOW())",
            [$name, $trigger, $atype, $delay, $active]
        );
        flash_success('Automation rule created.');
    } else {
        flash_error('Please fill in all fields correctly.');
    }
    redirect('service_automation.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_rule') {
    verify_csrf();
    require_permission('services.manage');
    $rid = (int)($_POST['rule_id'] ?? 0);
    db_query("UPDATE automation_rules SET is_active = 1 - is_active WHERE id=?", [$rid]);
    redirect('service_automation.php');
}

$rules = db_rows("SELECT * FROM automation_rules ORDER BY created_at DESC");
$logs  = db_rows("SELECT * FROM automation_logs ORDER BY created_at DESC LIMIT 30");

// Live automation status — the ONE thing genuinely wired up end-to-end today
$bank_stats = db_row(
    "SELECT
      (SELECT COUNT(*) FROM bank_accounts WHERE is_active=1) as total_accounts,
      (SELECT COUNT(DISTINCT bank_account_id) FROM bank_statements WHERE status='missing') as accounts_with_missing,
      (SELECT COUNT(*) FROM bank_statements WHERE status='missing') as missing_statements,
      (SELECT COUNT(*) FROM bank_statements WHERE status='received') as received_statements"
);

render_header('Service Automation', 'service_automation');
?>
<?= render_flash() ?>

<div class="card" style="margin-bottom:20px">
  <div class="card-header"><h3>Live Automation — Bank Statement Collection</h3></div>
  <div class="card-body">
    <p class="text-muted" style="font-size:13px;margin-bottom:12px">
      This is the one service with a fully wired automation loop today: registering a bank account
      generates its missing-month checklist, uploads auto-match and validate, and completing every
      required month auto-creates a bookkeeping review task. Everything else on this page is
      configuration for future rules — it does not fire yet.
    </p>
    <div class="stat-cards" style="margin-bottom:0">
      <div class="stat-card"><div class="stat-value"><?= (int)$bank_stats['total_accounts'] ?></div><div class="stat-label">Active Accounts</div></div>
      <div class="stat-card success"><div class="stat-value"><?= (int)$bank_stats['received_statements'] ?></div><div class="stat-label">Statements Received</div></div>
      <div class="stat-card <?= $bank_stats['missing_statements'] > 0 ? 'warning' : '' ?>"><div class="stat-value"><?= (int)$bank_stats['missing_statements'] ?></div><div class="stat-label">Statements Missing</div></div>
      <div class="stat-card"><div class="stat-value"><?= (int)$bank_stats['accounts_with_missing'] ?></div><div class="stat-label">Accounts Needing Follow-up</div></div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

<div class="card">
  <div class="card-header"><h3>Automation Rules (config)</h3></div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Name</th><th>Trigger</th><th>Action</th><th>Delay</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if ($rules): foreach ($rules as $r): ?>
      <tr>
        <td style="font-weight:500"><?= e($r['name']) ?></td>
        <td><code style="font-size:11px"><?= e($r['trigger_event']) ?></code></td>
        <td><?= e(str_replace('_',' ',$r['action_type'])) ?></td>
        <td><?= $r['delay_days'] ?> day<?= $r['delay_days']==1?'':'s' ?></td>
        <td><?= $r['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Paused</span>' ?></td>
        <td>
          <?php if (can('services.manage')): ?>
          <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_rule"><input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
            <button class="btn btn-xs btn-secondary"><?= $r['is_active'] ? 'Pause' : 'Activate' ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="6" class="text-center text-muted" style="padding:24px">No automation rules configured yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card-header" style="border-top:1px solid var(--border)"><h3>Recent Automation Log</h3></div>
  <div style="padding:8px 0">
  <?php if ($logs): foreach ($logs as $l): ?>
  <div style="padding:8px 20px;border-bottom:1px solid var(--border);font-size:13px;display:flex;justify-content:space-between">
    <span><?= e($l['event']) ?> — <?= e($l['message'] ?: $l['result']) ?></span>
    <span class="text-muted"><?= time_ago($l['created_at']) ?></span>
  </div>
  <?php endforeach; else: ?>
  <div class="text-muted text-center" style="padding:20px">No automation events logged yet.</div>
  <?php endif; ?>
  </div>
</div>

<?php if (can('services.manage')): ?>
<div class="card">
  <div class="card-header"><h3>New Rule</h3></div>
  <div class="card-body">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_rule">
      <div class="form-group"><label>Rule Name</label><input type="text" name="name" class="form-control" required></div>
      <div class="form-group"><label>Trigger Event</label>
        <select name="trigger_event" class="form-control">
          <?php foreach ($events as $ev): ?><option value="<?= $ev ?>"><?= e($ev) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Action</label>
        <select name="action_type" class="form-control">
          <?php foreach ($action_types as $at): ?><option value="<?= $at ?>"><?= e(ucwords(str_replace('_',' ',$at))) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Delay (days)</label><input type="number" name="delay_days" class="form-control" value="0" min="0"></div>
      <label class="checkbox-label"><input type="checkbox" name="is_active" value="1" checked> Active</label>
      <button type="submit" class="btn btn-primary w-full" style="margin-top:8px">+ Create Rule</button>
    </form>
  </div>
</div>
<?php endif; ?>

</div>
<?php render_footer(); ?>
