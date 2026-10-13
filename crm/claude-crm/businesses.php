<?php
// ============================================================
// SPS CRM - Businesses (cross-client list)
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('businesses.manage');

$search = trim($_GET['search'] ?? '');
$where = ['b.deleted_at IS NULL'];
$params = [];
if ($search) {
    $where[] = '(b.name LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR b.ein LIKE ?)';
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s, $s, $s]);
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$businesses = db_rows(
    "SELECT b.*, CONCAT(c.first_name,' ',c.last_name) as client_name, c.id as client_id,
     (SELECT COUNT(*) FROM bank_accounts ba WHERE ba.business_id=b.id AND ba.is_active=1) as account_count
     FROM businesses b JOIN clients c ON c.id=b.client_id
     $where_sql ORDER BY b.name LIMIT 200", $params
);

render_header('Businesses', 'businesses');
?>
<?= render_flash() ?>

<div class="card">
  <div class="card-header"><h2>Businesses</h2></div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" class="search-bar">
      <input type="text" name="search" class="form-control search-input" placeholder="Search by name, client, or EIN…" value="<?= e($search) ?>">
      <button type="submit" class="btn btn-primary">Search</button>
      <?php if ($search): ?><a href="businesses.php" class="btn btn-secondary">Clear</a><?php endif; ?>
    </form>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Business</th><th>Client</th><th>EIN</th><th>Industry</th><th>Bank Accounts</th><th></th></tr></thead>
      <tbody>
      <?php if ($businesses): foreach ($businesses as $b): ?>
      <tr>
        <td style="font-weight:500"><?= e($b['name']) ?></td>
        <td><a href="client_profile.php?id=<?= $b['client_id'] ?>"><?= e($b['client_name']) ?></a></td>
        <td><?= e($b['ein'] ?: '—') ?></td>
        <td><?= e($b['industry'] ?: '—') ?></td>
        <td><?= $b['account_count'] ?: '—' ?></td>
        <td><a href="client_profile.php?id=<?= $b['client_id'] ?>&tab=businesses" class="btn btn-xs btn-secondary">View</a></td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No businesses found. Add one from a client's profile.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php render_footer(); ?>
