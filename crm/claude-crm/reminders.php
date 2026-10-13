<?php
// ============================================================
// SPS CRM - Reminders
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $date  = $_POST['reminder_date'] ?? '';
    $time  = $_POST['reminder_time'] ?: null;
    $recurrence = in_array($_POST['recurrence'] ?? '', ['none','daily','weekly','monthly','custom'], true) ? $_POST['recurrence'] : 'none';
    $assigned_to = (int)($_POST['assigned_to'] ?? 0) ?: current_user_id();
    $client_ids = array_filter(array_map('intval', $_POST['client_ids'] ?? []));

    if (!$title || !$date) {
        flash_error('Title and date are required.');
    } else {
        $rid = db_insert(
            "INSERT INTO reminders (title, description, reminder_date, reminder_time, recurrence, assigned_to, status, created_by, created_at, updated_at)
             VALUES (?,?,?,?,?,?, 'active', ?, NOW(), NOW())",
            [$title, $desc ?: null, $date, $time, $recurrence, $assigned_to, current_user_id()]
        );
        foreach ($client_ids as $cid) {
            db_query("INSERT IGNORE INTO reminder_clients (reminder_id, client_id) VALUES (?,?)", [$rid, $cid]);
        }
        flash_success('Reminder created.');
    }
    redirect('reminders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'complete') {
    verify_csrf();
    db_query("UPDATE reminders SET status='completed', updated_at=NOW() WHERE id=?", [(int)($_POST['reminder_id'] ?? 0)]);
    flash_success('Reminder marked complete.');
    redirect('reminders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    verify_csrf();
    db_query("DELETE FROM reminders WHERE id=?", [(int)($_POST['reminder_id'] ?? 0)]);
    flash_success('Reminder deleted.');
    redirect('reminders.php');
}

$show = $_GET['show'] ?? 'active';
$where = $show === 'all' ? '1=1' : "r.status = '$show'";
$reminders = db_rows(
    "SELECT r.*, CONCAT(u.first_name,' ',u.last_name) as assignee,
     GROUP_CONCAT(CONCAT(c.first_name,' ',c.last_name) SEPARATOR ', ') as client_names
     FROM reminders r
     LEFT JOIN users u ON u.id = r.assigned_to
     LEFT JOIN reminder_clients rc ON rc.reminder_id = r.id
     LEFT JOIN clients c ON c.id = rc.client_id
     WHERE $where
     GROUP BY r.id
     ORDER BY r.reminder_date ASC LIMIT 200"
);

$clients_list = db_rows("SELECT id, first_name, last_name FROM clients WHERE deleted_at IS NULL ORDER BY first_name");
$users_list = db_rows("SELECT id, first_name, last_name FROM users WHERE is_active=1 ORDER BY first_name");

render_header('Reminders', 'reminders');
?>
<?= render_flash() ?>

<div class="card">
  <div class="card-header">
    <h2>Reminders</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('add-rem-modal')">+ Add Reminder</button>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" class="search-bar">
      <select name="show" class="form-control" style="width:160px" onchange="this.form.submit()">
        <option value="active" <?= $show==='active'?'selected':'' ?>>Active</option>
        <option value="completed" <?= $show==='completed'?'selected':'' ?>>Completed</option>
        <option value="all" <?= $show==='all'?'selected':'' ?>>All</option>
      </select>
    </form>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Title</th><th>Clients</th><th>Assigned</th><th>Date</th><th>Recurrence</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if ($reminders): foreach ($reminders as $r): ?>
      <tr>
        <td style="font-weight:500"><?= e($r['title']) ?><?php if($r['description']): ?><div class="text-muted" style="font-weight:400;font-size:12px"><?= e(truncate($r['description'],60)) ?></div><?php endif; ?></td>
        <td><?= e($r['client_names'] ?: '—') ?></td>
        <td><?= e($r['assignee'] ?: '—') ?></td>
        <td><?= fmt_date($r['reminder_date']) ?> <?= $r['reminder_time'] ? date('g:i A', strtotime($r['reminder_time'])) : '' ?></td>
        <td><?= ucfirst($r['recurrence']) ?></td>
        <td><?= status_badge($r['status']) ?></td>
        <td style="white-space:nowrap">
          <?php if ($r['status'] === 'active'): ?>
          <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="complete"><input type="hidden" name="reminder_id" value="<?= $r['id'] ?>"><button class="btn btn-xs btn-secondary">✓ Done</button></form>
          <?php endif; ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this reminder?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="reminder_id" value="<?= $r['id'] ?>"><button class="btn btn-xs btn-danger">🗑</button></form>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No reminders found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="add-rem-modal">
  <div class="modal">
    <div class="modal-header"><h3>Add Reminder</h3><button class="modal-close" onclick="closeModal('add-rem-modal')">×</button></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group"><label>Title <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required></div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Date <span class="text-danger">*</span></label><input type="date" name="reminder_date" class="form-control" required></div>
          <div class="form-group"><label>Time</label><input type="time" name="reminder_time" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Recurrence</label>
            <select name="recurrence" class="form-control">
              <option value="none">None</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="custom">Custom</option>
            </select>
          </div>
          <div class="form-group"><label>Assigned To</label>
            <select name="assigned_to" class="form-control">
              <option value="">Me</option>
              <?php foreach ($users_list as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['first_name'].' '.$u['last_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Clients (multi-select)</label>
          <select name="client_ids[]" class="form-control" multiple size="5">
            <?php foreach ($clients_list as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['first_name'].' '.$c['last_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-rem-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Reminder</button>
      </div>
    </form>
  </div>
</div>
<?php render_footer(); ?>
