<?php
// ============================================================
// SPS CRM - Tasks
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('tasks.manage');

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $assigned_to = (int)($_POST['assigned_to'] ?? 0) ?: current_user_id();
    $client_id = (int)($_POST['client_id'] ?? 0) ?: null;
    $project_id = (int)($_POST['project_id'] ?? 0) ?: null;
    $priority = in_array($_POST['priority'] ?? '', ['low','medium','high'], true) ? $_POST['priority'] : 'medium';
    $due_date = $_POST['due_date'] ?: null;

    if (!$title) {
        flash_error('Task title is required.');
    } else {
        $tid = db_insert(
            "INSERT INTO tasks (title, description, assigned_to, client_id, project_id, priority, status, due_date, created_by, created_at, updated_at)
             VALUES (?,?,?,?,?,?, 'open', ?, ?, NOW(), NOW())",
            [$title, $desc ?: null, $assigned_to, $client_id, $project_id, $priority, $due_date, current_user_id()]
        );
        log_activity('create', 'tasks', $tid, "Task created: $title", $client_id);
        if ($assigned_to !== current_user_id()) {
            create_notification($assigned_to, 'New Task Assigned', $title, 'info', 'tasks.php');
        }
        flash_success('Task added.');
    }
    redirect('tasks.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'set_status') {
    verify_csrf();
    $tid = (int)($_POST['task_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['open','in_progress','completed'], true)) {
        $task = db_row("SELECT * FROM tasks WHERE id=?", [$tid]);
        db_query("UPDATE tasks SET status=?, updated_at=NOW() WHERE id=?", [$status, $tid]);
        log_activity('update', 'tasks', $tid, "Task '{$task['title']}' marked $status", $task['client_id'] ?? null);
    }
    redirect('tasks.php?' . http_build_query($_GET));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    verify_csrf();
    $tid = (int)($_POST['task_id'] ?? 0);
    db_query("DELETE FROM tasks WHERE id=?", [$tid]);
    flash_success('Task deleted.');
    redirect('tasks.php');
}

// ── Filters ─────────────────────────────────────────────────
$status_filter = $_GET['status'] ?? 'open';
$mine_only = !empty($_GET['mine']);
$where = [];
$params = [];
if ($status_filter && $status_filter !== 'all') { $where[] = 't.status = ?'; $params[] = $status_filter; }
if ($mine_only) { $where[] = 't.assigned_to = ?'; $params[] = current_user_id(); }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$tasks = db_rows(
    "SELECT t.*, CONCAT(u.first_name,' ',u.last_name) as assignee,
     CONCAT(c.first_name,' ',c.last_name) as client_name, p.name as project_name
     FROM tasks t
     LEFT JOIN users u ON u.id=t.assigned_to
     LEFT JOIN clients c ON c.id=t.client_id
     LEFT JOIN projects p ON p.id=t.project_id
     $where_sql
     ORDER BY t.status='completed', t.due_date IS NULL, t.due_date ASC, t.created_at DESC
     LIMIT 200",
    $params
);

$users_list = db_rows("SELECT id, first_name, last_name FROM users WHERE is_active=1 ORDER BY first_name");
$clients_list = db_rows("SELECT id, first_name, last_name FROM clients WHERE deleted_at IS NULL ORDER BY first_name");

$open_count = (int)db_val("SELECT COUNT(*) FROM tasks WHERE status='open'");
$overdue_count = (int)db_val("SELECT COUNT(*) FROM tasks WHERE status!='completed' AND due_date < CURRENT_DATE");
$my_open = (int)db_val("SELECT COUNT(*) FROM tasks WHERE assigned_to=? AND status!='completed'", [current_user_id()]);

render_header('Tasks', 'tasks');
$prefill_project = (int)($_GET['project_id'] ?? 0);
$auto_open_modal = ($_GET['action'] ?? '') === 'add';
?>
<?= render_flash() ?>

<div class="stat-cards">
  <div class="stat-card"><div class="stat-value"><?= $open_count ?></div><div class="stat-label">Open Tasks</div></div>
  <div class="stat-card <?= $overdue_count>0?'danger':'' ?>"><div class="stat-value"><?= $overdue_count ?></div><div class="stat-label">Overdue</div></div>
  <div class="stat-card <?= $my_open>0?'warning':'' ?>"><div class="stat-value"><?= $my_open ?></div><div class="stat-label">Assigned To Me</div></div>
</div>

<div class="card">
  <div class="card-header">
    <h2>Tasks</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('add-task-modal')">+ Add Task</button>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" class="search-bar">
      <select name="status" class="form-control" style="width:160px" onchange="this.form.submit()">
        <?php foreach (['open'=>'Open','in_progress'=>'In Progress','completed'=>'Completed','all'=>'All'] as $val=>$label): ?>
        <option value="<?= $val ?>" <?= $status_filter===$val?'selected':'' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <label class="checkbox-label" style="margin:0"><input type="checkbox" name="mine" value="1" <?= $mine_only?'checked':'' ?> onchange="this.form.submit()"> Assigned to me only</label>
    </form>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Title</th><th>Client / Project</th><th>Assignee</th><th>Priority</th><th>Due</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if ($tasks): foreach ($tasks as $t):
        $is_overdue = $t['status'] !== 'completed' && $t['due_date'] && strtotime($t['due_date']) < strtotime('today');
      ?>
      <tr>
        <td style="font-weight:500"><?= e($t['title']) ?><?php if ($t['description']): ?><div class="text-muted" style="font-weight:400;font-size:12px"><?= e(truncate($t['description'], 60)) ?></div><?php endif; ?></td>
        <td>
          <?= $t['client_name'] ? '<a href="client_profile.php?id='.$t['client_id'].'">'.e($t['client_name']).'</a>' : '' ?>
          <?= $t['project_name'] ? '<div><a href="projects.php?id='.$t['project_id'].'" style="font-size:12px">'.e($t['project_name']).'</a></div>' : '' ?>
          <?= (!$t['client_name'] && !$t['project_name']) ? '—' : '' ?>
        </td>
        <td><?= e($t['assignee'] ?: '—') ?></td>
        <td><?= status_badge($t['priority']) ?></td>
        <td class="<?= $is_overdue ? 'text-danger' : '' ?>"><?= fmt_date($t['due_date']) ?></td>
        <td>
          <form method="POST" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="task_id" value="<?= $t['id'] ?>">
            <select name="status" class="form-control" style="padding:3px 6px;font-size:12px" onchange="this.form.submit()">
              <?php foreach (['open'=>'Open','in_progress'=>'In Progress','completed'=>'Completed'] as $val=>$label): ?>
              <option value="<?= $val ?>" <?= $t['status']===$val?'selected':'' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this task?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="task_id" value="<?= $t['id'] ?>">
            <button class="btn btn-xs btn-danger">🗑</button>
          </form>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No tasks found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="add-task-modal">
  <div class="modal">
    <div class="modal-header"><h3>Add Task</h3><button class="modal-close" onclick="closeModal('add-task-modal')">×</button></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group"><label>Title <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required></div>
        <div class="form-group"><label>Description</label><textarea name="description" class="form-control"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Assigned To</label>
            <select name="assigned_to" class="form-control">
              <option value="">Me</option>
              <?php foreach ($users_list as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['first_name'].' '.$u['last_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Priority</label>
            <select name="priority" class="form-control">
              <option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Client</label>
            <select name="client_id" class="form-control">
              <option value="">— None —</option>
              <?php foreach ($clients_list as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['first_name'].' '.$c['last_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Due Date</label><input type="date" name="due_date" class="form-control"></div>
        </div>
        <input type="hidden" name="project_id" value="<?= $prefill_project ?>">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-task-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Task</button>
      </div>
    </form>
  </div>
</div>
<?php if ($auto_open_modal): ?>
<script>openModal('add-task-modal');</script>
<?php endif; ?>
<?php render_footer(); ?>
