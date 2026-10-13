<?php
// ============================================================
// SPS CRM - Activity / Audit Logs
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('reports.view');

$module = $_GET['module'] ?? '';
$user_id = (int)($_GET['user_id'] ?? 0);
$stage = $_GET['stage'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = ['1=1'];
$params = [];
if ($module) { $where[] = 'a.module = ?'; $params[] = $module; }
if ($user_id) { $where[] = 'a.user_id = ?'; $params[] = $user_id; }
if ($stage !== '') { $where[] = 'a.stage = ?'; $params[] = (int)$stage; }
if ($date_from) { $where[] = 'a.created_at >= ?'; $params[] = $date_from . ' 00:00:00'; }
if ($date_to) { $where[] = 'a.created_at <= ?'; $params[] = $date_to . ' 23:59:59'; }
$where_sql = implode(' AND ', $where);

$page = max(1, (int)($_GET['page'] ?? 1));
$total = (int)db_val("SELECT COUNT(*) FROM activities a WHERE $where_sql", $params);
$pg = paginate($total, $page, 50);

$activities = db_rows(
    "SELECT a.*, CONCAT(u.first_name,' ',u.last_name) as user_name, CONCAT(c.first_name,' ',c.last_name) as client_name
     FROM activities a LEFT JOIN users u ON u.id=a.user_id LEFT JOIN clients c ON c.id=a.client_id
     WHERE $where_sql ORDER BY a.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

$modules = db_rows("SELECT DISTINCT module FROM activities ORDER BY module");
$users_list = db_rows("SELECT id, first_name, last_name FROM users ORDER BY first_name");

render_header('Activity Logs', 'activity_logs');
?>
<?= render_flash() ?>

<div class="card">
  <div class="card-header"><h2>Activity Logs</h2></div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" class="search-bar" style="flex-wrap:wrap">
      <select name="module" class="form-control" style="width:160px">
        <option value="">All Modules</option>
        <?php foreach ($modules as $m): ?><option value="<?= e($m['module']) ?>" <?= $module===$m['module']?'selected':'' ?>><?= e($m['module']) ?></option><?php endforeach; ?>
      </select>
      <select name="user_id" class="form-control" style="width:160px">
        <option value="">All Users</option>
        <?php foreach ($users_list as $u): ?><option value="<?= $u['id'] ?>" <?= $user_id==$u['id']?'selected':'' ?>><?= e($u['first_name'].' '.$u['last_name']) ?></option><?php endforeach; ?>
      </select>
      <select name="stage" class="form-control" style="width:180px">
        <option value="">All Stages</option>
        <option value="1" <?= $stage==='1'?'selected':'' ?>>Stage 1 — Normal</option>
        <option value="2" <?= $stage==='2'?'selected':'' ?>>Stage 2 — Sensitive Access</option>
        <option value="3" <?= $stage==='3'?'selected':'' ?>>Stage 3 — Data Change</option>
      </select>
      <input type="date" name="date_from" class="form-control" style="width:150px" value="<?= e($date_from) ?>">
      <input type="date" name="date_to" class="form-control" style="width:150px" value="<?= e($date_to) ?>">
      <button type="submit" class="btn btn-primary">Filter</button>
      <a href="activity_logs.php" class="btn btn-secondary">Clear</a>
    </form>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Description</th><th>Client</th><th>Stage</th></tr></thead>
      <tbody>
      <?php if ($activities): foreach ($activities as $a): ?>
      <tr>
        <td class="text-muted" style="white-space:nowrap"><?= fmt_datetime($a['created_at']) ?></td>
        <td><?= e($a['user_name'] ?: 'System') ?></td>
        <td><span class="badge badge-secondary"><?= e($a['module']) ?></span></td>
        <td><?= e($a['action']) ?></td>
        <td><?= e($a['description'] ?: '—') ?></td>
        <td><?= $a['client_name'] ? '<a href="client_profile.php?id='.$a['client_id'].'">'.e($a['client_name']).'</a>' : '—' ?></td>
        <td>
          <?php if ($a['stage'] == 2): ?><span class="badge badge-warning">Sensitive</span>
          <?php elseif ($a['stage'] == 3): ?><span class="badge badge-info">Change</span>
          <?php else: ?><span class="badge badge-secondary">Normal</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No activity recorded yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div style="padding:16px 20px">
    <?= render_pagination($pg, 'activity_logs.php?' . http_build_query(compact('module','user_id','stage','date_from','date_to'))) ?>
  </div>
</div>
<?php render_footer(); ?>
