<?php
// ============================================================
// SPS CRM - Manage Clients
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('clients.view');

// ── Handle deletions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    require_permission('clients.delete');
    $id = (int)($_POST['client_id'] ?? 0);
    if ($id) {
        $client = db_row("SELECT * FROM clients WHERE id=? AND deleted_at IS NULL", [$id]);
        if ($client) {
            db_query("UPDATE clients SET deleted_at=NOW(), status='archived' WHERE id=?", [$id]);
            log_activity('delete', 'clients', $id, 'Client archived: ' . $client['first_name'] . ' ' . $client['last_name'], $id);
            flash_success('Client archived successfully.');
        }
    }
    redirect('clients.php');
}

// ── Filters & Pagination ──────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));

// Build WHERE clause
$where  = ['c.deleted_at IS NULL'];
$params = [];

if ($search) {
    $where[]  = '(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($status) {
    $where[]  = 'c.status = ?';
    $params[] = $status;
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// Count
$total  = (int)db_val("SELECT COUNT(*) FROM clients c $where_sql", $params);
$pg     = paginate($total, $page);

// Fetch clients
$clients = db_rows(
    "SELECT c.*,
     COALESCE(inv_sum.total_due, 0) as outstanding_balance,
     COALESCE(inv_sum.unpaid_count, 0) as unpaid_invoices,
     (SELECT COUNT(*) FROM client_services cs WHERE cs.client_id=c.id AND cs.status IN ('active','in_progress')) as active_services,
     (SELECT COUNT(*) FROM businesses b WHERE b.client_id=c.id AND b.deleted_at IS NULL) as business_count
     FROM clients c
     LEFT JOIN (
       SELECT client_id,
              SUM(amount_due - amount_paid) as total_due,
              COUNT(*) as unpaid_count
       FROM invoices WHERE status IN ('sent','overdue') AND deleted_at IS NULL
       GROUP BY client_id
     ) inv_sum ON inv_sum.client_id = c.id
     $where_sql
     ORDER BY c.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

// Stats for header cards
$total_clients   = (int)db_val("SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL");
$active_clients  = (int)db_val("SELECT COUNT(*) FROM clients WHERE status='active' AND deleted_at IS NULL");
$unpaid_clients  = (int)db_val("SELECT COUNT(DISTINCT client_id) FROM invoices WHERE status IN ('sent','overdue') AND deleted_at IS NULL");
$total_outstanding = (float)(db_val("SELECT COALESCE(SUM(amount_due - amount_paid),0) FROM invoices WHERE status IN ('sent','overdue') AND deleted_at IS NULL") ?: 0);

render_header('Clients', 'clients');
?>
<?= render_flash() ?>

<!-- Summary cards -->
<div class="stat-cards">
  <div class="stat-card primary">
    <div class="stat-value"><?= $total_clients ?></div>
    <div class="stat-label">Total Clients</div>
  </div>
  <div class="stat-card success">
    <div class="stat-value"><?= $active_clients ?></div>
    <div class="stat-label">Active Clients</div>
  </div>
  <div class="stat-card <?= $unpaid_clients > 0 ? 'warning' : '' ?>">
    <div class="stat-value"><?= $unpaid_clients ?></div>
    <div class="stat-label">Clients with Unpaid Invoices</div>
  </div>
  <div class="stat-card <?= $total_outstanding > 0 ? 'danger' : '' ?>">
    <div class="stat-value"><?= fmt_currency($total_outstanding) ?></div>
    <div class="stat-label">Outstanding Balance</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>Clients</h2>
    <?php if (can('clients.create')): ?>
    <a href="add_client.php" class="btn btn-primary">+ Add Client</a>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <!-- Search & Filters -->
    <form method="GET" class="search-bar">
      <input type="text" name="search" class="form-control search-input"
             placeholder="Search by name, email, phone…" value="<?= e($search) ?>">
      <select name="status" class="form-control" style="width:160px">
        <option value="">All Statuses</option>
        <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
        <option value="lead" <?= $status==='lead'?'selected':'' ?>>Lead</option>
        <option value="archived" <?= $status==='archived'?'selected':'' ?>>Archived</option>
      </select>
      <button type="submit" class="btn btn-primary">Search</button>
      <?php if ($search || $status): ?>
      <a href="clients.php" class="btn btn-secondary">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Businesses</th>
          <th>Services</th>
          <th>Unpaid</th>
          <th>Outstanding</th>
          <th>Status</th>
          <th>Added</th>
          <th style="width:100px">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($clients): foreach ($clients as $c): ?>
      <tr>
        <td>
          <a href="client_profile.php?id=<?= $c['id'] ?>" style="font-weight:500">
            <?= e($c['first_name'] . ' ' . $c['last_name']) ?>
          </a>
        </td>
        <td><?= e($c['email'] ?? '—') ?></td>
        <td><?= e($c['phone'] ?? '—') ?></td>
        <td class="text-center"><?= $c['business_count'] ?: '—' ?></td>
        <td class="text-center">
          <?= $c['active_services'] ? '<span class="badge badge-primary">' . $c['active_services'] . '</span>' : '<span class="text-muted">—</span>' ?>
        </td>
        <td class="text-center">
          <?= $c['unpaid_invoices'] ? '<span class="badge badge-danger">' . $c['unpaid_invoices'] . '</span>' : '<span class="text-muted">—</span>' ?>
        </td>
        <td>
          <?= $c['outstanding_balance'] > 0
            ? '<span style="color:var(--danger);font-weight:600">' . fmt_currency($c['outstanding_balance']) . '</span>'
            : '<span class="text-muted">—</span>' ?>
        </td>
        <td><?= status_badge($c['status']) ?></td>
        <td class="text-muted"><?= fmt_date($c['created_at']) ?></td>
        <td>
          <div style="display:flex;gap:4px">
            <a href="client_profile.php?id=<?= $c['id'] ?>" class="btn btn-xs btn-secondary" title="View">👁</a>
            <?php if (can('clients.edit')): ?>
            <a href="edit_client.php?id=<?= $c['id'] ?>" class="btn btn-xs btn-secondary" title="Edit">✏️</a>
            <?php endif; ?>
            <?php if (can('clients.delete')): ?>
            <button type="button" class="btn btn-xs btn-danger" title="Archive"
              onclick="archiveClient(<?= $c['id'] ?>, '<?= e($c['first_name'] . ' ' . $c['last_name']) ?>')">🗑</button>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr>
        <td colspan="10" class="text-center text-muted" style="padding:40px">
          <?= $search || $status ? 'No clients match your search.' : 'No clients yet. <a href="add_client.php">Add your first client</a>' ?>
        </td>
      </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div style="padding:16px 20px">
    <?= render_pagination($pg, 'clients.php?' . http_build_query(['search'=>$search,'status'=>$status])) ?>
  </div>
</div>

<!-- Archive form -->
<form id="archive-form" method="POST" action="clients.php" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="client_id" id="archive-client-id">
</form>

<script>
function archiveClient(id, name) {
  if (confirm('Archive client "' + name + '"?\n\nTheir data will be kept but the client will be hidden from active lists.')) {
    document.getElementById('archive-client-id').value = id;
    document.getElementById('archive-form').submit();
  }
}
</script>
<?php render_footer(); ?>
