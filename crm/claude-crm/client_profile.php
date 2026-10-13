<?php
// ============================================================
// SPS CRM - Client Profile
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('clients.view');

$id = (int)($_GET['id'] ?? 0);
$client = db_row("SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL", [$id]);
if (!$client) {
    flash_error('Client not found.');
    redirect('clients.php');
}

$action = $_POST['action'] ?? '';

// ── Add Business ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_business') {
    verify_csrf();
    require_permission('businesses.manage');
    $name     = trim($_POST['name'] ?? '');
    $ein      = trim($_POST['ein'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if (!$name) {
        flash_error('Business name is required.');
    } else {
        $biz_id = db_insert(
            "INSERT INTO businesses (client_id, name, ein, industry, phone, email, created_at, updated_at)
             VALUES (?,?,?,?,?,?,NOW(),NOW())",
            [$id, $name, $ein ?: null, $industry ?: null, $phone ?: null, $email ?: null]
        );
        log_activity('create', 'businesses', $biz_id, "Business added: $name", $id);
        flash_success('Business added successfully.');
    }
    redirect('client_profile.php?id=' . $id . '&tab=businesses');
}

// ── Archive Business ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'archive_business') {
    verify_csrf();
    require_permission('businesses.manage');
    $biz_id = (int)($_POST['business_id'] ?? 0);
    $biz = db_row("SELECT * FROM businesses WHERE id=? AND client_id=?", [$biz_id, $id]);
    if ($biz) {
        db_query("UPDATE businesses SET deleted_at=NOW() WHERE id=?", [$biz_id]);
        log_activity('delete', 'businesses', $biz_id, "Business archived: {$biz['name']}", $id);
        flash_success('Business archived.');
    }
    redirect('client_profile.php?id=' . $id . '&tab=businesses');
}

// ── Add Comment ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_comment') {
    verify_csrf();
    $comment = trim($_POST['comment'] ?? '');
    if ($comment !== '') {
        $cid = db_insert(
            "INSERT INTO client_comments (client_id, user_id, comment, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())",
            [$id, current_user_id(), $comment]
        );
        log_activity('comment', 'clients', $cid, 'Comment added: ' . truncate($comment, 80), $id);
        flash_success('Comment added.');
    }
    redirect('client_profile.php?id=' . $id . '&tab=comments');
}

$tab = $_GET['tab'] ?? 'overview';
$valid_tabs = ['overview', 'businesses', 'services', 'projects', 'invoices', 'bank_statements', 'documents', 'tasks', 'comments', 'activity'];
if (!in_array($tab, $valid_tabs, true)) $tab = 'overview';

// ── Shared summary data ───────────────────────────────────
$businesses = db_rows("SELECT * FROM businesses WHERE client_id=? AND deleted_at IS NULL ORDER BY name", [$id]);
$business_ids = array_column($businesses, 'id');

$bank_accounts_count = $business_ids
    ? (int)db_val("SELECT COUNT(*) FROM bank_accounts WHERE business_id IN (" . implode(',', array_fill(0, count($business_ids), '?')) . ") AND is_active=1", $business_ids)
    : 0;

$unpaid_total = (float)(db_val("SELECT COALESCE(SUM(amount_due-amount_paid),0) FROM invoices WHERE client_id=? AND status IN ('sent','overdue') AND deleted_at IS NULL", [$id]) ?: 0);
$open_tasks   = (int)db_val("SELECT COUNT(*) FROM tasks WHERE client_id=? AND status!='completed'", [$id]);
$active_services = (int)db_val("SELECT COUNT(*) FROM client_services WHERE client_id=? AND status IN ('active','in_progress')", [$id]);

render_header($client['first_name'] . ' ' . $client['last_name'], 'clients');
?>
<?= render_flash() ?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
  <div style="display:flex;align-items:center;gap:12px">
    <a href="clients.php" class="btn btn-secondary btn-sm">← All Clients</a>
    <h2 style="font-size:20px"><?= e($client['first_name'] . ' ' . $client['last_name']) ?></h2>
    <?= status_badge($client['status']) ?>
  </div>
  <?php if (can('clients.edit')): ?>
  <a href="edit_client.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">✏️ Edit Client</a>
  <?php endif; ?>
</div>

<!-- Summary cards -->
<div class="stat-cards">
  <div class="stat-card"><div class="stat-value"><?= count($businesses) ?></div><div class="stat-label">Businesses</div></div>
  <div class="stat-card"><div class="stat-value"><?= $bank_accounts_count ?></div><div class="stat-label">Bank Accounts</div></div>
  <div class="stat-card"><div class="stat-value"><?= $active_services ?></div><div class="stat-label">Active Services</div></div>
  <div class="stat-card <?= $unpaid_total > 0 ? 'danger' : '' ?>"><div class="stat-value"><?= fmt_currency($unpaid_total) ?></div><div class="stat-label">Outstanding Balance</div></div>
  <div class="stat-card <?= $open_tasks > 0 ? 'warning' : '' ?>"><div class="stat-value"><?= $open_tasks ?></div><div class="stat-label">Open Tasks</div></div>
</div>

<div style="display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start">

  <!-- Tab nav -->
  <div class="card">
    <div style="padding:8px 0">
      <?php
      $tabs = [
        'overview' => '👤 Overview', 'businesses' => '🏢 Businesses', 'services' => '⚙️ Services',
        'projects' => '📋 Projects', 'invoices' => '🧾 Invoices', 'bank_statements' => '🏦 Bank Statements',
        'documents' => '📁 Documents', 'tasks' => '✅ Tasks', 'comments' => '💬 Comments', 'activity' => '📝 Activity',
      ];
      foreach ($tabs as $key => $label):
      ?>
      <a href="client_profile.php?id=<?= $id ?>&tab=<?= $key ?>"
         style="display:block;padding:9px 16px;font-size:13px;color:<?= $tab===$key?'#2563eb':'#334155' ?>;
                background:<?= $tab===$key?'var(--primary-light)':'transparent' ?>;
                border-left:3px solid <?= $tab===$key?'#2563eb':'transparent' ?>;text-decoration:none">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Tab content -->
  <div>
  <?php if ($tab === 'overview'): ?>
    <div class="card">
      <div class="card-header"><h3>Contact Information</h3></div>
      <div class="card-body">
        <div class="form-row">
          <div><div class="text-muted" style="font-size:12px">Email</div><div><?= e($client['email'] ?: '—') ?></div></div>
          <div><div class="text-muted" style="font-size:12px">Phone</div><div><?= e($client['phone'] ?: '—') ?></div></div>
          <div><div class="text-muted" style="font-size:12px">Client Since</div><div><?= fmt_date($client['created_at']) ?></div></div>
          <div><div class="text-muted" style="font-size:12px">Status</div><div><?= status_badge($client['status']) ?></div></div>
        </div>
        <?php if ($client['notes']): ?>
        <hr><div class="text-muted" style="font-size:12px;margin-bottom:4px">Notes</div><div><?= nl2br(e($client['notes'])) ?></div>
        <?php endif; ?>
      </div>
    </div>

  <?php elseif ($tab === 'businesses'): ?>
    <div class="card">
      <div class="card-header">
        <h3>Businesses</h3>
        <?php if (can('businesses.manage')): ?>
        <button class="btn btn-primary btn-sm" onclick="openModal('add-biz-modal')">+ Add Business</button>
        <?php endif; ?>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Name</th><th>EIN</th><th>Industry</th><th>Phone</th><th>Bank Statements</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if ($businesses): foreach ($businesses as $b): ?>
          <tr>
            <td style="font-weight:500"><?= e($b['name']) ?></td>
            <td><?= e($b['ein'] ?: '—') ?></td>
            <td><?= e($b['industry'] ?: '—') ?></td>
            <td><?= e($b['phone'] ?: '—') ?></td>
            <td><a href="bank_statements.php?business_id=<?= $b['id'] ?>" class="btn btn-xs btn-secondary">Manage</a></td>
            <td>
              <?php if (can('businesses.manage')): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Archive this business?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="archive_business">
                <input type="hidden" name="business_id" value="<?= $b['id'] ?>">
                <button type="submit" class="btn btn-xs btn-danger">Archive</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No businesses yet. Add one to start a service like Bank Statement Collection.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal-overlay" id="add-biz-modal">
      <div class="modal">
        <div class="modal-header"><h3>Add Business</h3><button class="modal-close" onclick="closeModal('add-biz-modal')">×</button></div>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_business">
          <div class="modal-body">
            <div class="form-group"><label>Business Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
            <div class="form-row">
              <div class="form-group"><label>EIN</label><input type="text" name="ein" class="form-control"></div>
              <div class="form-group"><label>Industry</label><input type="text" name="industry" class="form-control"></div>
            </div>
            <div class="form-row">
              <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
              <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('add-biz-modal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Add Business</button>
          </div>
        </form>
      </div>
    </div>

  <?php elseif ($tab === 'services'): ?>
    <?php $services = db_rows("SELECT cs.*, s.name as service_name FROM client_services cs LEFT JOIN services s ON s.id=cs.service_id WHERE cs.client_id=? ORDER BY cs.created_at DESC", [$id]); ?>
    <div class="card">
      <div class="card-header"><h3>Services</h3></div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Service</th><th>Status</th><th>Started</th><th>Completed</th></tr></thead>
          <tbody>
          <?php if ($services): foreach ($services as $s): ?>
          <tr><td><?= e($s['service_name'] ?: '—') ?></td><td><?= status_badge($s['status']) ?></td><td><?= fmt_date($s['started_at']) ?></td><td><?= fmt_date($s['completed_at']) ?></td></tr>
          <?php endforeach; else: ?>
          <tr><td colspan="4" class="text-center text-muted" style="padding:30px">No services started for this client yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($tab === 'projects'): ?>
    <?php $projects = db_rows("SELECT * FROM projects WHERE client_id=? AND deleted_at IS NULL ORDER BY created_at DESC", [$id]); ?>
    <div class="card">
      <div class="card-header"><h3>Projects</h3></div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Name</th><th>Status</th><th>Start</th><th>End</th></tr></thead>
          <tbody>
          <?php if ($projects): foreach ($projects as $p): ?>
          <tr><td><?= e($p['name']) ?></td><td><?= status_badge($p['status']) ?></td><td><?= fmt_date($p['start_date']) ?></td><td><?= fmt_date($p['end_date']) ?></td></tr>
          <?php endforeach; else: ?>
          <tr><td colspan="4" class="text-center text-muted" style="padding:30px">No projects yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($tab === 'invoices'): ?>
    <?php $invoices = db_rows("SELECT * FROM invoices WHERE client_id=? AND deleted_at IS NULL ORDER BY created_at DESC", [$id]); ?>
    <div class="card">
      <div class="card-header"><h3>Invoices</h3></div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Invoice #</th><th>Status</th><th>Due</th><th>Paid</th><th>Date</th></tr></thead>
          <tbody>
          <?php if ($invoices): foreach ($invoices as $inv): ?>
          <tr><td><?= e($inv['invoice_number']) ?></td><td><?= status_badge($inv['status']) ?></td><td><?= fmt_currency($inv['amount_due']) ?></td><td><?= fmt_currency($inv['amount_paid']) ?></td><td><?= fmt_date($inv['created_at']) ?></td></tr>
          <?php endforeach; else: ?>
          <tr><td colspan="5" class="text-center text-muted" style="padding:30px">No invoices yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($tab === 'bank_statements'): ?>
    <?php
    $accounts = $business_ids ? db_rows(
        "SELECT ba.*, b.name as biz_name,
         (SELECT COUNT(*) FROM bank_statements bs WHERE bs.bank_account_id=ba.id AND bs.status='received') as received_count,
         (SELECT COUNT(*) FROM bank_statements bs WHERE bs.bank_account_id=ba.id AND bs.status='missing') as missing_count
         FROM bank_accounts ba JOIN businesses b ON b.id=ba.business_id
         WHERE ba.business_id IN (" . implode(',', array_fill(0, count($business_ids), '?')) . ") AND ba.is_active=1
         ORDER BY b.name, ba.bank_name", $business_ids
    ) : [];
    ?>
    <div class="card">
      <div class="card-header">
        <h3>Bank Accounts</h3>
        <a href="bank_statements.php?client_id=<?= $id ?>" class="btn btn-sm btn-secondary">Open Bank Statements Module</a>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Business</th><th>Bank</th><th>Account</th><th>Received</th><th>Missing</th><th></th></tr></thead>
          <tbody>
          <?php if ($accounts): foreach ($accounts as $a): ?>
          <tr>
            <td><?= e($a['biz_name']) ?></td><td><?= e($a['bank_name']) ?></td><td>···<?= e($a['account_last4']) ?></td>
            <td><span class="badge badge-success"><?= $a['received_count'] ?></span></td>
            <td><?= $a['missing_count'] ? '<span class="badge badge-danger">'.$a['missing_count'].'</span>' : '<span class="badge badge-success">✓</span>' ?></td>
            <td><a href="bank_statements.php?account_id=<?= $a['id'] ?>" class="btn btn-xs btn-primary">Manage</a></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:30px">
            <?= $businesses ? 'No bank accounts registered yet. Add one from the Bank Statements module.' : 'Add a business first, then register a bank account to start the Bank Statement Collection service.' ?>
          </td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($tab === 'documents'): ?>
    <?php $docs = db_rows("SELECT d.*, CONCAT(u.first_name,' ',u.last_name) as uploaded_by_name FROM documents d LEFT JOIN users u ON u.id=d.uploaded_by WHERE d.client_id=? ORDER BY d.created_at DESC", [$id]); ?>
    <div class="card">
      <div class="card-header"><h3>Documents</h3></div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Title</th><th>Category</th><th>Uploaded By</th><th>Date</th></tr></thead>
          <tbody>
          <?php if ($docs): foreach ($docs as $d): ?>
          <tr><td><?= e($d['title']) ?></td><td><?= e($d['category'] ?: '—') ?></td><td><?= e($d['uploaded_by_name'] ?: '—') ?></td><td><?= fmt_date($d['created_at']) ?></td></tr>
          <?php endforeach; else: ?>
          <tr><td colspan="4" class="text-center text-muted" style="padding:30px">No documents uploaded for this client yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($tab === 'tasks'): ?>
    <?php $tasks = db_rows("SELECT t.*, CONCAT(u.first_name,' ',u.last_name) as assignee FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to WHERE t.client_id=? ORDER BY t.due_date IS NULL, t.due_date, t.created_at DESC", [$id]); ?>
    <div class="card">
      <div class="card-header"><h3>Tasks</h3></div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Title</th><th>Assigned To</th><th>Priority</th><th>Status</th><th>Due</th></tr></thead>
          <tbody>
          <?php if ($tasks): foreach ($tasks as $t): ?>
          <tr>
            <td><?= e($t['title']) ?></td><td><?= e($t['assignee'] ?: '—') ?></td>
            <td><?= status_badge($t['priority']) ?></td><td><?= status_badge($t['status']) ?></td><td><?= fmt_date($t['due_date']) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="5" class="text-center text-muted" style="padding:30px">No tasks for this client yet. Tasks are also created automatically by service automation (e.g. when all bank statements are received).</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($tab === 'comments'): ?>
    <?php $comments = db_rows("SELECT cc.*, CONCAT(u.first_name,' ',u.last_name) as author FROM client_comments cc LEFT JOIN users u ON u.id=cc.user_id WHERE cc.client_id=? ORDER BY cc.created_at DESC", [$id]); ?>
    <div class="card">
      <div class="card-header"><h3>Comments</h3></div>
      <div class="card-body">
        <form method="POST" style="margin-bottom:20px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_comment">
          <div class="form-group"><textarea name="comment" class="form-control" placeholder="Add a comment…" required></textarea></div>
          <button type="submit" class="btn btn-primary btn-sm">Post Comment</button>
        </form>
        <?php if ($comments): foreach ($comments as $c): ?>
        <div style="padding:12px 0;border-top:1px solid var(--border)">
          <div style="font-size:13px;font-weight:600"><?= e($c['author'] ?: 'System') ?> <span style="font-weight:400;color:var(--text-muted)">· <?= time_ago($c['created_at']) ?></span></div>
          <div style="font-size:13px;margin-top:4px"><?= nl2br(e($c['comment'])) ?></div>
        </div>
        <?php endforeach; else: ?>
        <div class="text-muted text-center" style="padding:20px">No comments yet.</div>
        <?php endif; ?>
      </div>
    </div>

  <?php elseif ($tab === 'activity'): ?>
    <?php $acts = db_rows("SELECT a.*, CONCAT(u.first_name,' ',u.last_name) as user_name FROM activities a LEFT JOIN users u ON u.id=a.user_id WHERE a.client_id=? ORDER BY a.created_at DESC LIMIT 100", [$id]); ?>
    <div class="card">
      <div class="card-header"><h3>Activity Log</h3></div>
      <div style="padding:8px 0">
      <?php if ($acts): foreach ($acts as $a): ?>
      <div style="padding:10px 20px;border-bottom:1px solid var(--border);font-size:13px">
        <?= e($a['description'] ?: $a['action']) ?>
        <div style="font-size:11px;color:var(--text-muted)"><?= e($a['user_name'] ?: 'System') ?> · <?= time_ago($a['created_at']) ?></div>
      </div>
      <?php endforeach; else: ?>
      <div class="text-muted text-center" style="padding:20px">No activity recorded yet.</div>
      <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
  </div>
</div>

<?php render_footer(); ?>
