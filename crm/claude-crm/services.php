<?php
// ============================================================
// SPS CRM - Service Catalog
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_service') {
    verify_csrf();
    require_permission('services.manage');

    $sid        = (int)($_POST['id'] ?? 0);
    $code       = strtoupper(trim($_POST['code'] ?? ''));
    $name       = trim($_POST['name'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $category   = (int)($_POST['category_id'] ?? 0) ?: null;
    $price      = (float)($_POST['price'] ?? 0);
    $recurring  = !empty($_POST['is_recurring']) ? 1 : 0;
    $interval   = $_POST['recurrence_interval'] ?? null;
    $is_active  = !empty($_POST['is_active']) ? 1 : 0;

    if (!$code || !$name) {
        flash_error('Code and name are required.');
    } else {
        if ($sid) {
            db_query(
                "UPDATE services SET category_id=?, code=?, name=?, description=?, price=?, is_recurring=?, recurrence_interval=?, is_active=? WHERE id=?",
                [$category, $code, $name, $desc ?: null, $price, $recurring, $interval, $is_active, $sid]
            );
            log_activity('update', 'services', $sid, "Service updated: $name");
            flash_success('Service updated.');
        } else {
            $sid = db_insert(
                "INSERT INTO services (category_id, code, name, description, price, is_recurring, recurrence_interval, is_active, created_at)
                 VALUES (?,?,?,?,?,?,?,?,NOW())",
                [$category, $code, $name, $desc ?: null, $price, $recurring, $interval, $is_active]
            );
            log_activity('create', 'services', $sid, "Service created: $name");
            flash_success('Service added.');
        }
    }
    redirect('services.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_category') {
    verify_csrf();
    require_permission('services.manage');
    $name = trim($_POST['category_name'] ?? '');
    if ($name) {
        db_insert("INSERT INTO service_categories (name, sort_order) VALUES (?, 0)", [$name]);
        flash_success('Category added.');
    }
    redirect('services.php');
}

$categories = db_rows("SELECT * FROM service_categories ORDER BY sort_order, name");
$services = db_rows(
    "SELECT s.*, sc.name as category_name,
     (SELECT COUNT(*) FROM service_requirements sr WHERE sr.service_id=s.id) as req_count,
     (SELECT COUNT(*) FROM service_workflow_steps sw WHERE sw.service_id=s.id) as step_count,
     (SELECT COUNT(*) FROM client_services cs WHERE cs.service_id=s.id AND cs.status IN ('active','in_progress')) as active_count
     FROM services s LEFT JOIN service_categories sc ON sc.id = s.category_id
     ORDER BY sc.sort_order, s.name"
);

render_header('Service Catalog', 'services');
?>
<?= render_flash() ?>

<div class="card">
  <div class="card-header">
    <h2>Service Catalog</h2>
    <div style="display:flex;gap:8px">
      <?php if (can('services.manage')): ?>
      <button class="btn btn-secondary btn-sm" onclick="openModal('add-cat-modal')">+ Category</button>
      <button class="btn btn-primary btn-sm" onclick="openServiceModal()">+ Add Service</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Price</th><th>Recurring</th><th>Requirements</th><th>Workflow</th><th>In Use</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if ($services): foreach ($services as $s): ?>
      <tr>
        <td><code><?= e($s['code']) ?></code></td>
        <td style="font-weight:500"><?= e($s['name']) ?></td>
        <td><?= e($s['category_name'] ?: '—') ?></td>
        <td><?= $s['price'] > 0 ? fmt_currency($s['price']) : '—' ?></td>
        <td><?= $s['is_recurring'] ? e(ucfirst($s['recurrence_interval'] ?: 'yes')) : '—' ?></td>
        <td><a href="service_requirements.php?service_id=<?= $s['id'] ?>"><?= $s['req_count'] ?> requirement<?= $s['req_count']==1?'':'s' ?></a></td>
        <td><a href="service_workflow.php?service_id=<?= $s['id'] ?>"><?= $s['step_count'] ?> step<?= $s['step_count']==1?'':'s' ?></a></td>
        <td class="text-center"><?= $s['active_count'] ?: '—' ?></td>
        <td><?= $s['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
        <td>
          <?php if (can('services.manage')): ?>
          <button class="btn btn-xs btn-secondary" onclick='openServiceModal(<?= json_encode($s) ?>)'>Edit</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="10" class="text-center text-muted" style="padding:30px">No services yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Category Modal -->
<div class="modal-overlay" id="add-cat-modal">
  <div class="modal">
    <div class="modal-header"><h3>Add Category</h3><button class="modal-close" onclick="closeModal('add-cat-modal')">×</button></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_category">
      <div class="modal-body">
        <div class="form-group"><label>Category Name</label><input type="text" name="category_name" class="form-control" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-cat-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Add/Edit Service Modal -->
<div class="modal-overlay" id="svc-modal">
  <div class="modal">
    <div class="modal-header"><h3 id="svc-modal-title">Add Service</h3><button class="modal-close" onclick="closeModal('svc-modal')">×</button></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_service">
      <input type="hidden" name="id" id="svc-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label>Code <span class="text-danger">*</span></label><input type="text" name="code" id="svc-code" class="form-control" required></div>
          <div class="form-group"><label>Category</label>
            <select name="category_id" id="svc-category" class="form-control">
              <option value="">— None —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Name <span class="text-danger">*</span></label><input type="text" name="name" id="svc-name" class="form-control" required></div>
        <div class="form-group"><label>Description</label><textarea name="description" id="svc-desc" class="form-control"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label>Price</label><input type="number" step="0.01" name="price" id="svc-price" class="form-control" value="0"></div>
          <div class="form-group"><label>Recurrence</label>
            <select name="recurrence_interval" id="svc-interval" class="form-control">
              <option value="">One-time</option>
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="annually">Annually</option>
            </select>
          </div>
        </div>
        <label class="checkbox-label"><input type="checkbox" name="is_recurring" id="svc-recurring" value="1"> Recurring service</label>
        <label class="checkbox-label"><input type="checkbox" name="is_active" id="svc-active" value="1" checked> Active</label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('svc-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Service</button>
      </div>
    </form>
  </div>
</div>

<script>
function openServiceModal(svc) {
  document.getElementById('svc-modal-title').textContent = svc ? 'Edit Service' : 'Add Service';
  document.getElementById('svc-id').value = svc ? svc.id : '';
  document.getElementById('svc-code').value = svc ? svc.code : '';
  document.getElementById('svc-name').value = svc ? svc.name : '';
  document.getElementById('svc-desc').value = svc ? svc.description || '' : '';
  document.getElementById('svc-price').value = svc ? svc.price : 0;
  document.getElementById('svc-category').value = svc ? (svc.category_id || '') : '';
  document.getElementById('svc-interval').value = svc ? (svc.recurrence_interval || '') : '';
  document.getElementById('svc-recurring').checked = svc ? !!Number(svc.is_recurring) : false;
  document.getElementById('svc-active').checked = svc ? !!Number(svc.is_active) : true;
  openModal('svc-modal');
}
</script>
<?php render_footer(); ?>
