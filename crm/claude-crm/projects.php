<?php
// ============================================================
// SPS CRM - Projects
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('projects.manage');

$view_id = (int)($_GET['id'] ?? 0);
$action = $_POST['action'] ?? '';

// ── Add project ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    verify_csrf();
    $client_id  = (int)($_POST['client_id'] ?? 0) ?: null;
    $business_id = (int)($_POST['business_id'] ?? 0) ?: null;
    $service_id = (int)($_POST['service_id'] ?? 0) ?: null;
    $name       = trim($_POST['name'] ?? '');
    $start_date = $_POST['start_date'] ?: null;
    $end_date   = $_POST['end_date'] ?: null;
    $recurring  = !empty($_POST['is_recurring']) ? 1 : 0;

    if (!$name) {
        flash_error('Project name is required.');
    } else {
        $pid = db_insert(
            "INSERT INTO projects (client_id, business_id, service_id, name, status, start_date, end_date, is_recurring, cycle_number, created_by, created_at, updated_at)
             VALUES (?,?,?,?, 'open', ?, ?, ?, 1, ?, NOW(), NOW())",
            [$client_id, $business_id, $service_id, $name, $start_date, $end_date, $recurring, current_user_id()]
        );
        log_activity('create', 'projects', $pid, "Project created: $name", $client_id);
        flash_success('Project created.');
        redirect('projects.php?id=' . $pid);
    }
    redirect('projects.php');
}

// ── Update status ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'set_status') {
    verify_csrf();
    $pid = (int)($_POST['project_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['open','upcoming','expired','completed','closed'], true)) {
        $project = db_row("SELECT * FROM projects WHERE id=?", [$pid]);
        db_query("UPDATE projects SET status=?, updated_at=NOW() WHERE id=?", [$status, $pid]);
        log_change('projects', $pid, 'status', $project['status'] ?? '', $status, $project['client_id'] ?? null);
        log_activity('update', 'projects', $pid, "Project status changed to $status", $project['client_id'] ?? null);

        // Recurring renewal: when marked completed, spin up next cycle
        if ($status === 'completed' && !empty($project['is_recurring']) && $project['end_date']) {
            $interval_days = max(1, (strtotime($project['end_date']) - strtotime($project['start_date'] ?: $project['end_date'])) / 86400);
            $next_start = $project['end_date'];
            $next_end   = date('Y-m-d', strtotime($project['end_date'] . " +{$interval_days} days"));
            $new_id = db_insert(
                "INSERT INTO projects (client_id, business_id, service_id, name, status, start_date, end_date, is_recurring, parent_project_id, cycle_number, created_by, created_at, updated_at)
                 VALUES (?,?,?,?, 'upcoming', ?, ?, 1, ?, ?, ?, NOW(), NOW())",
                [$project['client_id'], $project['business_id'], $project['service_id'], $project['name'],
                 $next_start, $next_end, $pid, $project['cycle_number'] + 1, current_user_id()]
            );
            log_activity('create', 'projects', $new_id, "Recurring project cycle #" . ($project['cycle_number'] + 1) . " generated", $project['client_id']);
        }
    }
    redirect($view_id ? 'projects.php?id=' . $pid : 'projects.php');
}

// ── Add comment ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_comment') {
    verify_csrf();
    $pid = (int)($_POST['project_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($comment) {
        db_insert("INSERT INTO project_comments (project_id, user_id, comment, created_at) VALUES (?,?,?,NOW())", [$pid, current_user_id(), $comment]);
        flash_success('Comment added.');
    }
    redirect('projects.php?id=' . $pid);
}

// ============================================================
// DETAIL VIEW
// ============================================================
if ($view_id) {
    $project = db_row(
        "SELECT p.*, CONCAT(c.first_name,' ',c.last_name) as client_name, b.name as business_name, s.name as service_name
         FROM projects p
         LEFT JOIN clients c ON c.id=p.client_id LEFT JOIN businesses b ON b.id=p.business_id LEFT JOIN services s ON s.id=p.service_id
         WHERE p.id=? AND p.deleted_at IS NULL", [$view_id]
    );
    if (!$project) { flash_error('Project not found.'); redirect('projects.php'); }

    $comments = db_rows("SELECT pc.*, CONCAT(u.first_name,' ',u.last_name) as author FROM project_comments pc LEFT JOIN users u ON u.id=pc.user_id WHERE pc.project_id=? ORDER BY pc.created_at DESC", [$view_id]);
    $tasks = db_rows("SELECT t.*, CONCAT(u.first_name,' ',u.last_name) as assignee FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to WHERE t.project_id=? ORDER BY t.due_date", [$view_id]);
    $cycles = $project['is_recurring'] ? db_rows(
        "SELECT id, cycle_number, status, start_date, end_date FROM projects WHERE (id=? OR parent_project_id=? OR id IN (SELECT parent_project_id FROM projects WHERE id=?)) AND deleted_at IS NULL ORDER BY cycle_number",
        [$view_id, $project['parent_project_id'] ?: $view_id, $view_id]
    ) : [];

    render_header($project['name'], 'projects');
?>
<?= render_flash() ?>
<div style="margin-bottom:16px"><a href="projects.php" class="btn btn-secondary btn-sm">← All Projects</a></div>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
  <div>
    <h2 style="font-size:20px"><?= e($project['name']) ?> <?= status_badge($project['status']) ?> <?= $project['is_recurring'] ? '<span class="badge badge-info">Cycle #'.$project['cycle_number'].'</span>' : '' ?></h2>
    <div class="text-muted" style="font-size:13px;margin-top:4px">
      <?= $project['client_name'] ? '<a href="client_profile.php?id='.$project['client_id'].'">'.e($project['client_name']).'</a>' : 'No client' ?>
      <?= $project['business_name'] ? ' · ' . e($project['business_name']) : '' ?>
      <?= $project['service_name'] ? ' · ' . e($project['service_name']) : '' ?>
      · <?= fmt_date($project['start_date']) ?> → <?= fmt_date($project['end_date']) ?>
    </div>
  </div>
  <form method="POST" style="display:flex;gap:8px;align-items:center">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="set_status">
    <input type="hidden" name="project_id" value="<?= $view_id ?>">
    <select name="status" class="form-control" style="width:150px" onchange="this.form.submit()">
      <?php foreach (['open','upcoming','expired','completed','closed'] as $st): ?>
      <option value="<?= $st ?>" <?= $project['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">
<div>
  <?php if ($project['is_recurring'] && count($cycles) > 1): ?>
  <div class="card" style="margin-bottom:20px">
    <div class="card-header"><h3>Recurring Cycles</h3></div>
    <div class="table-wrapper">
      <table><thead><tr><th>Cycle</th><th>Status</th><th>Period</th></tr></thead><tbody>
      <?php foreach ($cycles as $c): ?>
      <tr class="<?= $c['id']==$view_id ? 'text-bold' : '' ?>">
        <td><a href="projects.php?id=<?= $c['id'] ?>">#<?= $c['cycle_number'] ?><?= $c['id']==$view_id?' (current)':'' ?></a></td>
        <td><?= status_badge($c['status']) ?></td>
        <td><?= fmt_date($c['start_date']) ?> → <?= fmt_date($c['end_date']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:20px">
    <div class="card-header"><h3>Tasks</h3><a href="tasks.php?project_id=<?= $view_id ?>&action=add" class="btn btn-xs btn-secondary">+ Add Task</a></div>
    <div class="table-wrapper">
      <table><thead><tr><th>Title</th><th>Assignee</th><th>Status</th><th>Due</th></tr></thead><tbody>
      <?php if ($tasks): foreach ($tasks as $t): ?>
      <tr><td><?= e($t['title']) ?></td><td><?= e($t['assignee'] ?: '—') ?></td><td><?= status_badge($t['status']) ?></td><td><?= fmt_date($t['due_date']) ?></td></tr>
      <?php endforeach; else: ?>
      <tr><td colspan="4" class="text-center text-muted" style="padding:20px">No tasks linked to this project yet.</td></tr>
      <?php endif; ?>
      </tbody></table>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3>Comments</h3></div>
    <div class="card-body">
      <form method="POST" style="margin-bottom:16px">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_comment"><input type="hidden" name="project_id" value="<?= $view_id ?>">
        <textarea name="comment" class="form-control" placeholder="Add a comment…" required></textarea>
        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px">Post</button>
      </form>
      <?php if ($comments): foreach ($comments as $c): ?>
      <div style="padding:10px 0;border-top:1px solid var(--border);font-size:13px">
        <b><?= e($c['author'] ?: 'System') ?></b> <span class="text-muted">· <?= time_ago($c['created_at']) ?></span>
        <div style="margin-top:2px"><?= nl2br(e($c['comment'])) ?></div>
      </div>
      <?php endforeach; else: ?>
      <div class="text-muted text-center" style="padding:16px">No comments yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Details</h3></div>
  <div class="card-body" style="font-size:13px;display:flex;flex-direction:column;gap:10px">
    <div><span class="text-muted">Status</span><br><?= status_badge($project['status']) ?></div>
    <div><span class="text-muted">Recurring</span><br><?= $project['is_recurring'] ? 'Yes' : 'No' ?></div>
    <div><span class="text-muted">Created</span><br><?= fmt_date($project['created_at']) ?></div>
  </div>
</div>
</div>
<?php render_footer(); exit; ?>
<?php
}

// ============================================================
// LIST VIEW
// ============================================================
$status_filter = $_GET['status'] ?? '';
$where = ['p.deleted_at IS NULL'];
$params = [];
if ($status_filter) { $where[] = 'p.status = ?'; $params[] = $status_filter; }
$where_sql = 'WHERE ' . implode(' AND ', $where);

$projects = db_rows(
    "SELECT p.*, CONCAT(c.first_name,' ',c.last_name) as client_name, s.name as service_name
     FROM projects p LEFT JOIN clients c ON c.id=p.client_id LEFT JOIN services s ON s.id=p.service_id
     $where_sql ORDER BY p.created_at DESC LIMIT 200",
    $params
);

$counts = db_row(
    "SELECT
       SUM(status='open') as open_c, SUM(status='upcoming') as upcoming_c,
       SUM(status='expired') as expired_c, SUM(status='completed') as completed_c, SUM(status='closed') as closed_c
     FROM projects WHERE deleted_at IS NULL"
);

$clients_list = db_rows("SELECT id, first_name, last_name FROM clients WHERE deleted_at IS NULL ORDER BY first_name");
$services_list = db_rows("SELECT id, name FROM services WHERE is_active=1 ORDER BY name");

render_header('Projects', 'projects');
?>
<?= render_flash() ?>

<div class="stat-cards">
  <?php foreach (['open'=>['Open',$counts['open_c']],'upcoming'=>['Upcoming',$counts['upcoming_c']],'expired'=>['Expired',$counts['expired_c']],'completed'=>['Completed',$counts['completed_c']],'closed'=>['Closed',$counts['closed_c']]] as $key => [$label,$val]): ?>
  <a href="projects.php?status=<?= $key ?>" style="text-decoration:none">
    <div class="stat-card <?= $status_filter===$key?'primary':'' ?>"><div class="stat-value"><?= (int)$val ?></div><div class="stat-label"><?= $label ?> Projects</div></div>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header">
    <h2>Projects <?= $status_filter ? '— ' . ucfirst($status_filter) : '' ?></h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('add-proj-modal')">+ New Project</button>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Name</th><th>Client</th><th>Service</th><th>Status</th><th>Start</th><th>End</th><th>Recurring</th></tr></thead>
      <tbody>
      <?php if ($projects): foreach ($projects as $p): ?>
      <tr>
        <td><a href="projects.php?id=<?= $p['id'] ?>" style="font-weight:500"><?= e($p['name']) ?></a></td>
        <td><?= $p['client_name'] ? e($p['client_name']) : '—' ?></td>
        <td><?= e($p['service_name'] ?: '—') ?></td>
        <td><?= status_badge($p['status']) ?></td>
        <td><?= fmt_date($p['start_date']) ?></td>
        <td><?= fmt_date($p['end_date']) ?></td>
        <td><?= $p['is_recurring'] ? '🔁 #' . $p['cycle_number'] : '—' ?></td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No projects yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="add-proj-modal">
  <div class="modal">
    <div class="modal-header"><h3>New Project</h3><button class="modal-close" onclick="closeModal('add-proj-modal')">×</button></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group"><label>Project Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
        <div class="form-group"><label>Client</label>
          <select name="client_id" class="form-control">
            <option value="">— None —</option>
            <?php foreach ($clients_list as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['first_name'].' '.$c['last_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Service</label>
          <select name="service_id" class="form-control">
            <option value="">— None —</option>
            <?php foreach ($services_list as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control"></div>
          <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control"></div>
        </div>
        <label class="checkbox-label"><input type="checkbox" name="is_recurring" value="1"> Recurring project (auto-renews when marked Completed)</label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-proj-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Project</button>
      </div>
    </form>
  </div>
</div>
<?php render_footer(); ?>
