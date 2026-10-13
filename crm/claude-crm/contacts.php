<?php
// ============================================================
// SPS CRM - Contacts
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('businesses.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verify_csrf();
    $client_id = (int)($_POST['client_id'] ?? 0) ?: null;
    $business_id = (int)($_POST['business_id'] ?? 0) ?: null;
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $primary = !empty($_POST['is_primary']) ? 1 : 0;

    if (!$first || !$last) {
        flash_error('First and last name are required.');
    } else {
        $cid = db_insert(
            "INSERT INTO contacts (client_id, business_id, first_name, last_name, title, email, phone, is_primary, created_at)
             VALUES (?,?,?,?,?,?,?,?,NOW())",
            [$client_id, $business_id, $first, $last, $title ?: null, $email ?: null, $phone ?: null, $primary]
        );
        log_activity('create', 'contacts', $cid, "Contact added: $first $last", $client_id);
        flash_success('Contact added.');
    }
    redirect('contacts.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    db_query("DELETE FROM contacts WHERE id=?", [(int)($_POST['contact_id'] ?? 0)]);
    flash_success('Contact removed.');
    redirect('contacts.php');
}

$search = trim($_GET['search'] ?? '');
$where = [];
$params = [];
if ($search) {
    $where[] = "(ct.first_name LIKE ? OR ct.last_name LIKE ? OR ct.email LIKE ?)";
    $s = '%' . $search . '%';
    $params = [$s, $s, $s];
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$contacts = db_rows(
    "SELECT ct.*, CONCAT(c.first_name,' ',c.last_name) as client_name, b.name as business_name
     FROM contacts ct LEFT JOIN clients c ON c.id=ct.client_id LEFT JOIN businesses b ON b.id=ct.business_id
     $where_sql ORDER BY ct.first_name LIMIT 200", $params
);
$clients_list = db_rows("SELECT id, first_name, last_name FROM clients WHERE deleted_at IS NULL ORDER BY first_name");
$businesses_list = db_rows("SELECT id, name, client_id FROM businesses WHERE deleted_at IS NULL ORDER BY name");

render_header('Contacts', 'contacts');
?>
<?= render_flash() ?>

<div class="card">
  <div class="card-header">
    <h2>Contacts</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('add-contact-modal')">+ Add Contact</button>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" class="search-bar">
      <input type="text" name="search" class="form-control search-input" placeholder="Search contacts…" value="<?= e($search) ?>">
      <button type="submit" class="btn btn-primary">Search</button>
    </form>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Name</th><th>Title</th><th>Client</th><th>Business</th><th>Email</th><th>Phone</th><th></th></tr></thead>
      <tbody>
      <?php if ($contacts): foreach ($contacts as $ct): ?>
      <tr>
        <td style="font-weight:500"><?= e($ct['first_name'].' '.$ct['last_name']) ?> <?= $ct['is_primary'] ? '<span class="badge badge-primary">Primary</span>' : '' ?></td>
        <td><?= e($ct['title'] ?: '—') ?></td>
        <td><?= $ct['client_name'] ? '<a href="client_profile.php?id='.$ct['client_id'].'">'.e($ct['client_name']).'</a>' : '—' ?></td>
        <td><?= e($ct['business_name'] ?: '—') ?></td>
        <td><?= e($ct['email'] ?: '—') ?></td>
        <td><?= e($ct['phone'] ?: '—') ?></td>
        <td>
          <form method="POST" style="display:inline" onsubmit="return confirm('Remove this contact?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="contact_id" value="<?= $ct['id'] ?>">
            <button class="btn btn-xs btn-danger">🗑</button>
          </form>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No contacts yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="add-contact-modal">
  <div class="modal">
    <div class="modal-header"><h3>Add Contact</h3><button class="modal-close" onclick="closeModal('add-contact-modal')">×</button></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label>First Name <span class="text-danger">*</span></label><input type="text" name="first_name" class="form-control" required></div>
          <div class="form-group"><label>Last Name <span class="text-danger">*</span></label><input type="text" name="last_name" class="form-control" required></div>
        </div>
        <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control"></div>
        <div class="form-row">
          <div class="form-group"><label>Client</label>
            <select name="client_id" class="form-control">
              <option value="">— None —</option>
              <?php foreach ($clients_list as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['first_name'].' '.$c['last_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Business</label>
            <select name="business_id" class="form-control">
              <option value="">— None —</option>
              <?php foreach ($businesses_list as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
          <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
        </div>
        <label class="checkbox-label"><input type="checkbox" name="is_primary" value="1"> Primary contact</label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-contact-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Contact</button>
      </div>
    </form>
  </div>
</div>
<?php render_footer(); ?>
