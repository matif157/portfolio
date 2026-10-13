<?php
// ============================================================
// SPS CRM - Leads
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('clients.view');

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    verify_csrf();
    require_permission('clients.create');
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $source_id = (int)($_POST['source_id'] ?? 0) ?: null;
    $notes = trim($_POST['notes'] ?? '');

    if (!$first || !$last) {
        flash_error('First and last name are required.');
    } else {
        $lid = db_insert(
            "INSERT INTO leads (first_name, last_name, email, phone, source_id, status, assigned_to, notes, created_at, updated_at)
             VALUES (?,?,?,?,?, 'new', ?, ?, NOW(), NOW())",
            [$first, $last, $email ?: null, $phone ?: null, $source_id, current_user_id(), $notes ?: null]
        );
        log_activity('create', 'leads', $lid, "Lead created: $first $last");
        flash_success('Lead added.');
    }
    redirect('leads.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'set_status') {
    verify_csrf();
    require_permission('clients.edit');
    $lid = (int)($_POST['lead_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['new','contacted','qualified','unqualified','converted'], true)) {
        db_query("UPDATE leads SET status=?, updated_at=NOW() WHERE id=?", [$status, $lid]);
    }
    redirect('leads.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'convert') {
    verify_csrf();
    require_permission('clients.create');
    $lid = (int)($_POST['lead_id'] ?? 0);
    $lead = db_row("SELECT * FROM leads WHERE id=?", [$lid]);
    if ($lead && !$lead['converted_client_id']) {
        db_begin();
        try {
            $client_id = db_insert(
                "INSERT INTO clients (first_name, last_name, email, phone, status, assigned_to, notes, created_at, updated_at)
                 VALUES (?,?,?,?, 'active', ?, ?, NOW(), NOW())",
                [$lead['first_name'], $lead['last_name'], $lead['email'], $lead['phone'], current_user_id(), $lead['notes']]
            );
            db_query("UPDATE leads SET status='converted', converted_client_id=?, updated_at=NOW() WHERE id=?", [$client_id, $lid]);
            db_commit();
            log_activity('create', 'clients', $client_id, "Client converted from lead: {$lead['first_name']} {$lead['last_name']}", $client_id);
            flash_success('Lead converted to client.');
            redirect('client_profile.php?id=' . $client_id);
        } catch (Throwable $e) {
            db_rollback();
            error_log('Lead conversion failed: ' . $e->getMessage());
            flash_error('Unable to convert lead.');
        }
    }
    redirect('leads.php');
}

$status_filter = $_GET['status'] ?? '';
$where = [];
$params = [];
if ($status_filter) { $where[] = 'l.status = ?'; $params[] = $status_filter; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$leads = db_rows(
    "SELECT l.*, ls.name as source_name FROM leads l LEFT JOIN lead_sources ls ON ls.id=l.source_id
     $where_sql ORDER BY l.created_at DESC LIMIT 200", $params
);
$sources = db_rows("SELECT * FROM lead_sources ORDER BY name");

render_header('Leads', 'leads');
?>
<?= render_flash() ?>

<div class="card">
  <div class="card-header">
    <h2>Leads</h2>
    <?php if (can('clients.create')): ?>
    <button class="btn btn-primary btn-sm" onclick="openModal('add-lead-modal')">+ Add Lead</button>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" class="search-bar">
      <select name="status" class="form-control" style="width:180px" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <?php foreach (['new','contacted','qualified','unqualified','converted'] as $s): ?>
        <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Source</th><th>Status</th><th>Added</th><th></th></tr></thead>
      <tbody>
      <?php if ($leads): foreach ($leads as $l): ?>
      <tr>
        <td style="font-weight:500"><?= e($l['first_name'].' '.$l['last_name']) ?></td>
        <td><?= e($l['email'] ?: '—') ?></td>
        <td><?= e($l['phone'] ?: '—') ?></td>
        <td><?= e($l['source_name'] ?: '—') ?></td>
        <td>
          <?php if ($l['status'] === 'converted'): ?>
          <?= status_badge('converted') ?>
          <?php else: ?>
          <form method="POST" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
            <select name="status" class="form-control" style="padding:3px 6px;font-size:12px" onchange="this.form.submit()">
              <?php foreach (['new','contacted','qualified','unqualified'] as $s): ?>
              <option value="<?= $s ?>" <?= $l['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <?php endif; ?>
        </td>
        <td class="text-muted"><?= fmt_date($l['created_at']) ?></td>
        <td>
          <?php if ($l['status'] !== 'converted' && can('clients.create')): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Convert this lead into a client?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="convert"><input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
            <button class="btn btn-xs btn-primary">Convert</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No leads yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="add-lead-modal">
  <div class="modal">
    <div class="modal-header"><h3>Add Lead</h3><button class="modal-close" onclick="closeModal('add-lead-modal')">×</button></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label>First Name <span class="text-danger">*</span></label><input type="text" name="first_name" class="form-control" required></div>
          <div class="form-group"><label>Last Name <span class="text-danger">*</span></label><input type="text" name="last_name" class="form-control" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
          <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
        </div>
        <div class="form-group"><label>Source</label>
          <select name="source_id" class="form-control">
            <option value="">— Unknown —</option>
            <?php foreach ($sources as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-lead-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Lead</button>
      </div>
    </form>
  </div>
</div>
<?php render_footer(); ?>
