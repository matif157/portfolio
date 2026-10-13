<?php
// ============================================================
// SPS CRM - Communications Log
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'log') {
    verify_csrf();
    $client_id = (int)($_POST['client_id'] ?? 0);
    $channel = $_POST['channel'] ?? 'email';
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (!$client_id || !$body) {
        flash_error('Client and message body are required.');
    } else {
        $configured = provider_configured($channel);
        $status = $configured ? 'sent' : 'drafted (' . $channel . ' provider not configured)';
        log_communication($client_id, $channel, 'outbound', $subject, $body, $status);
        log_activity('communication', 'communications', 0, ucfirst($channel) . " logged: " . truncate($subject ?: $body, 60), $client_id);
        flash_success($configured ? 'Message sent and logged.' : 'Provider not configured — message drafted and logged, not actually sent.');
    }
    redirect('communications.php');
}

$channel_filter = $_GET['channel'] ?? '';
$client_filter = (int)($_GET['client_id'] ?? 0);
$where = [];
$params = [];
if ($channel_filter) { $where[] = 'co.channel = ?'; $params[] = $channel_filter; }
if ($client_filter) { $where[] = 'co.client_id = ?'; $params[] = $client_filter; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$comms = db_rows(
    "SELECT co.*, CONCAT(c.first_name,' ',c.last_name) as client_name, CONCAT(u.first_name,' ',u.last_name) as user_name
     FROM communications co LEFT JOIN clients c ON c.id=co.client_id LEFT JOIN users u ON u.id=co.user_id
     $where_sql ORDER BY co.created_at DESC LIMIT 200", $params
);
$clients_list = db_rows("SELECT id, first_name, last_name FROM clients WHERE deleted_at IS NULL ORDER BY first_name");

render_header('Communications', 'communications');
?>
<?= render_flash() ?>

<div class="alert alert-info" style="margin-bottom:16px">
  Automation status:
  Email <?= provider_configured('email') ? '✓ configured' : '✗ not configured' ?> ·
  SMS <?= provider_configured('sms') ? '✓ configured' : '✗ not configured' ?> ·
  WhatsApp <?= provider_configured('whatsapp') ? '✓ configured' : '✗ not configured' ?> ·
  Voice <?= provider_configured('voice') ? '✓ configured' : '✗ not configured' ?>
  — <a href="settings.php">configure</a>. Unconfigured channels are logged as drafts, never faked as sent.
</div>

<div class="card">
  <div class="card-header">
    <h2>Communications</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('log-comm-modal')">+ Log Message</button>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" class="search-bar">
      <select name="channel" class="form-control" style="width:160px" onchange="this.form.submit()">
        <option value="">All Channels</option>
        <?php foreach (['email','sms','whatsapp','voice','portal'] as $c): ?>
        <option value="<?= $c ?>" <?= $channel_filter===$c?'selected':'' ?>><?= ucfirst($c) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Date</th><th>Client</th><th>Channel</th><th>Direction</th><th>Subject / Body</th><th>Status</th><th>By</th></tr></thead>
      <tbody>
      <?php if ($comms): foreach ($comms as $c): ?>
      <tr>
        <td class="text-muted"><?= fmt_datetime($c['created_at']) ?></td>
        <td><?= $c['client_name'] ? '<a href="client_profile.php?id='.$c['client_id'].'">'.e($c['client_name']).'</a>' : '—' ?></td>
        <td><span class="badge badge-secondary"><?= e(ucfirst($c['channel'])) ?></span></td>
        <td><?= e(ucfirst($c['direction'])) ?></td>
        <td><?= e($c['subject'] ?: truncate($c['body'], 60)) ?></td>
        <td><?= e($c['status']) ?></td>
        <td><?= e($c['user_name'] ?: 'System') ?></td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No communications logged yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="log-comm-modal">
  <div class="modal">
    <div class="modal-header"><h3>Log Message</h3><button class="modal-close" onclick="closeModal('log-comm-modal')">×</button></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="log">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label>Client <span class="text-danger">*</span></label>
            <select name="client_id" class="form-control" required>
              <option value="">— Select —</option>
              <?php foreach ($clients_list as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['first_name'].' '.$c['last_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Channel</label>
            <select name="channel" class="form-control">
              <option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option><option value="voice">Voice</option><option value="portal">Portal</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Subject</label><input type="text" name="subject" class="form-control"></div>
        <div class="form-group"><label>Message <span class="text-danger">*</span></label><textarea name="body" class="form-control" required></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('log-comm-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Send / Log</button>
      </div>
    </form>
  </div>
</div>
<?php render_footer(); ?>
