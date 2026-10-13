<?php
// ============================================================
// SPS CRM - Calendar
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_event') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $date = $_POST['event_date'] ?? '';
    $time = $_POST['event_time'] ?: null;
    $client_id = (int)($_POST['client_id'] ?? 0) ?: null;
    $desc = trim($_POST['description'] ?? '');
    if ($title && $date) {
        db_insert(
            "INSERT INTO calendar_events (title, description, event_date, event_time, client_id, created_by, created_at) VALUES (?,?,?,?,?,?,NOW())",
            [$title, $desc ?: null, $date, $time, $client_id, current_user_id()]
        );
        flash_success('Event added.');
    }
    redirect('calendar.php?month=' . ($_POST['ret_month'] ?? date('Y-m')));
}

$month_str = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month_str)) $month_str = date('Y-m');
$month_ts = strtotime($month_str . '-01');
$first_dow = (int)date('w', $month_ts); // 0=Sun
$days_in_month = (int)date('t', $month_ts);
$prev_month = date('Y-m', strtotime('-1 month', $month_ts));
$next_month = date('Y-m', strtotime('+1 month', $month_ts));

$month_start = date('Y-m-01', $month_ts);
$month_end   = date('Y-m-t', $month_ts);

$events = db_rows(
    "SELECT id, title, event_date, event_time, client_id, 'event' as kind FROM calendar_events WHERE event_date BETWEEN ? AND ?
     UNION ALL
     SELECT id, title, reminder_date as event_date, reminder_time as event_time, NULL as client_id, 'reminder' as kind FROM reminders WHERE reminder_date BETWEEN ? AND ? AND status='active'
     ORDER BY event_date, event_time",
    [$month_start, $month_end, $month_start, $month_end]
);
$by_day = [];
foreach ($events as $ev) {
    $d = (int)date('j', strtotime($ev['event_date']));
    $by_day[$d][] = $ev;
}

$clients_list = db_rows("SELECT id, first_name, last_name FROM clients WHERE deleted_at IS NULL ORDER BY first_name");

render_header('Calendar', 'calendar');
?>
<?= render_flash() ?>

<div class="card">
  <div class="card-header">
    <h2><?= date('F Y', $month_ts) ?></h2>
    <div style="display:flex;gap:8px">
      <a href="calendar.php?month=<?= $prev_month ?>" class="btn btn-secondary btn-sm">← Prev</a>
      <a href="calendar.php?month=<?= date('Y-m') ?>" class="btn btn-secondary btn-sm">Today</a>
      <a href="calendar.php?month=<?= $next_month ?>" class="btn btn-secondary btn-sm">Next →</a>
      <button class="btn btn-primary btn-sm" onclick="openModal('add-event-modal')">+ Add Event</button>
    </div>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px">
      <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d) echo "<div>$d</div>"; ?>
    </div>
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px">
      <?php for ($i = 0; $i < $first_dow; $i++): ?>
      <div style="min-height:90px"></div>
      <?php endfor; ?>
      <?php for ($day = 1; $day <= $days_in_month; $day++):
        $is_today = $month_str === date('Y-m') && $day == (int)date('j');
      ?>
      <div style="min-height:90px;border:1px solid var(--border);border-radius:6px;padding:6px;background:<?= $is_today ? 'var(--primary-light)' : '#fff' ?>">
        <div style="font-size:12px;font-weight:<?= $is_today?700:500 ?>;color:<?= $is_today?'var(--primary)':'inherit' ?>"><?= $day ?></div>
        <?php if (!empty($by_day[$day])): foreach (array_slice($by_day[$day], 0, 3) as $ev): ?>
        <div style="font-size:10px;padding:2px 4px;margin-top:2px;border-radius:4px;background:<?= $ev['kind']==='reminder'?'#fef3c7':'#dbeafe' ?>;color:<?= $ev['kind']==='reminder'?'#92400e':'#1e40af' ?>;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e($ev['title']) ?>">
          <?= $ev['event_time'] ? date('g:ia', strtotime($ev['event_time'])) . ' ' : '' ?><?= e(truncate($ev['title'], 16)) ?>
        </div>
        <?php endforeach; if (count($by_day[$day]) > 3): ?>
        <div style="font-size:10px;color:var(--text-muted)">+<?= count($by_day[$day])-3 ?> more</div>
        <?php endif; endif; ?>
      </div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<div class="modal-overlay" id="add-event-modal">
  <div class="modal">
    <div class="modal-header"><h3>Add Calendar Event</h3><button class="modal-close" onclick="closeModal('add-event-modal')">×</button></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_event">
      <input type="hidden" name="ret_month" value="<?= $month_str ?>">
      <div class="modal-body">
        <div class="form-group"><label>Title <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required></div>
        <div class="form-row">
          <div class="form-group"><label>Date <span class="text-danger">*</span></label><input type="date" name="event_date" class="form-control" required value="<?= $month_start ?>"></div>
          <div class="form-group"><label>Time</label><input type="time" name="event_time" class="form-control"></div>
        </div>
        <div class="form-group"><label>Client</label>
          <select name="client_id" class="form-control">
            <option value="">— None —</option>
            <?php foreach ($clients_list as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['first_name'].' '.$c['last_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-event-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Event</button>
      </div>
    </form>
  </div>
</div>
<?php render_footer(); ?>
