<?php
// ============================================================
// SPS CRM - Service Requirements Editor
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();

$service_id = (int)($_GET['service_id'] ?? 0);
$service = db_row("SELECT * FROM services WHERE id = ?", [$service_id]);
if (!$service) { flash_error('Service not found.'); redirect('services.php'); }

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    verify_csrf();
    require_permission('services.manage');
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $type  = $_POST['requirement_type'] ?? 'document';
    $req   = !empty($_POST['is_required']) ? 1 : 0;
    if ($title) {
        $max_sort = (int)db_val("SELECT COALESCE(MAX(sort_order),0) FROM service_requirements WHERE service_id=?", [$service_id]);
        db_insert(
            "INSERT INTO service_requirements (service_id, title, description, requirement_type, is_required, sort_order) VALUES (?,?,?,?,?,?)",
            [$service_id, $title, $desc ?: null, $type, $req, $max_sort + 1]
        );
        flash_success('Requirement added.');
    }
    redirect('service_requirements.php?service_id=' . $service_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    verify_csrf();
    require_permission('services.manage');
    db_query("DELETE FROM service_requirements WHERE id=? AND service_id=?", [(int)($_POST['req_id'] ?? 0), $service_id]);
    flash_success('Requirement removed.');
    redirect('service_requirements.php?service_id=' . $service_id);
}

$requirements = db_rows("SELECT * FROM service_requirements WHERE service_id=? ORDER BY sort_order", [$service_id]);

render_header('Requirements — ' . $service['name'], 'services');
?>
<?= render_flash() ?>
<div style="margin-bottom:16px"><a href="services.php" class="btn btn-secondary btn-sm">← Service Catalog</a></div>

<div class="card">
  <div class="card-header"><h2>Requirements — <?= e($service['name']) ?></h2></div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>#</th><th>Title</th><th>Description</th><th>Type</th><th>Required</th><th></th></tr></thead>
      <tbody>
      <?php if ($requirements): foreach ($requirements as $r): ?>
      <tr>
        <td><?= $r['sort_order'] ?></td>
        <td style="font-weight:500"><?= e($r['title']) ?></td>
        <td class="text-muted"><?= e($r['description'] ?: '—') ?></td>
        <td><span class="badge badge-secondary"><?= e(ucfirst(str_replace('_',' ',$r['requirement_type']))) ?></span></td>
        <td><?= $r['is_required'] ? '<span class="badge badge-danger">Required</span>' : '<span class="badge badge-secondary">Optional</span>' ?></td>
        <td>
          <?php if (can('services.manage')): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Remove this requirement?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="req_id" value="<?= $r['id'] ?>">
            <button type="submit" class="btn btn-xs btn-danger">Remove</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No requirements defined yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (can('services.manage')): ?>
  <div class="card-body" style="border-top:1px solid var(--border)">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required></div>
        <div class="form-group"><label>Type</label>
          <select name="requirement_type" class="form-control">
            <option value="document">Document</option>
            <option value="information">Information</option>
            <option value="question">Question</option>
            <option value="approval">Approval</option>
          </select>
        </div>
      </div>
      <div class="form-group"><label>Description</label><input type="text" name="description" class="form-control"></div>
      <label class="checkbox-label"><input type="checkbox" name="is_required" value="1" checked> Required</label>
      <div class="form-actions"><button type="submit" class="btn btn-primary btn-sm">+ Add Requirement</button></div>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
