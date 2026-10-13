<?php
// ============================================================
// SPS CRM - Service Workflow Editor
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
$step_types = ['checkbox','comment','file_upload','website_link','invoice','quotation','payment',
               'client_input','crm_field','secure_area','year_selector','month_selector','task','approval','automatic_action'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    verify_csrf();
    require_permission('services.manage');
    $name  = trim($_POST['step_name'] ?? '');
    $type  = $_POST['step_type'] ?? 'checkbox';
    $level = $_POST['requirement_level'] ?? 'required';
    if ($name && in_array($type, $step_types, true)) {
        $max_sort = (int)db_val("SELECT COALESCE(MAX(sort_order),0) FROM service_workflow_steps WHERE service_id=?", [$service_id]);
        db_insert(
            "INSERT INTO service_workflow_steps (service_id, step_name, step_type, requirement_level, sort_order) VALUES (?,?,?,?,?)",
            [$service_id, $name, $type, $level, $max_sort + 1]
        );
        flash_success('Workflow step added.');
    }
    redirect('service_workflow.php?service_id=' . $service_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    verify_csrf();
    require_permission('services.manage');
    db_query("DELETE FROM service_workflow_steps WHERE id=? AND service_id=?", [(int)($_POST['step_id'] ?? 0), $service_id]);
    flash_success('Step removed.');
    redirect('service_workflow.php?service_id=' . $service_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['move_up', 'move_down'], true)) {
    verify_csrf();
    require_permission('services.manage');
    $step_id = (int)($_POST['step_id'] ?? 0);
    $step = db_row("SELECT * FROM service_workflow_steps WHERE id=? AND service_id=?", [$step_id, $service_id]);
    if ($step) {
        $cmp = $action === 'move_up' ? '<' : '>';
        $ord = $action === 'move_up' ? 'DESC' : 'ASC';
        $neighbor = db_row("SELECT * FROM service_workflow_steps WHERE service_id=? AND sort_order $cmp ? ORDER BY sort_order $ord LIMIT 1", [$service_id, $step['sort_order']]);
        if ($neighbor) {
            db_query("UPDATE service_workflow_steps SET sort_order=? WHERE id=?", [$neighbor['sort_order'], $step['id']]);
            db_query("UPDATE service_workflow_steps SET sort_order=? WHERE id=?", [$step['sort_order'], $neighbor['id']]);
        }
    }
    redirect('service_workflow.php?service_id=' . $service_id);
}

$steps = db_rows("SELECT * FROM service_workflow_steps WHERE service_id=? ORDER BY sort_order", [$service_id]);

render_header('Workflow — ' . $service['name'], 'services');
?>
<?= render_flash() ?>
<div style="margin-bottom:16px"><a href="services.php" class="btn btn-secondary btn-sm">← Service Catalog</a></div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

<div class="card">
  <div class="card-header"><h2>Workflow — <?= e($service['name']) ?></h2></div>
  <div class="card-body">
    <?php if ($steps): $i = 0; foreach ($steps as $s): $i++; ?>
    <div style="display:flex;align-items:center;gap:12px;padding:12px 0;<?= $i < count($steps) ? 'border-bottom:1px solid var(--border)' : '' ?>">
      <div style="width:26px;height:26px;border-radius:50%;background:var(--primary-light);color:var(--primary);
        display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0"><?= $i ?></div>
      <div style="flex:1">
        <div style="font-weight:500"><?= e($s['step_name']) ?></div>
        <div style="font-size:12px;color:var(--text-muted)">
          <span class="badge badge-secondary"><?= e(str_replace('_',' ',$s['step_type'])) ?></span>
          <?= $s['requirement_level'] === 'required' ? '<span class="badge badge-danger">Required</span>' : ($s['requirement_level'] === 'conditional' ? '<span class="badge badge-warning">Conditional</span>' : '<span class="badge badge-secondary">Optional</span>') ?>
        </div>
      </div>
      <?php if (can('services.manage')): ?>
      <div style="display:flex;gap:4px">
        <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_up"><input type="hidden" name="step_id" value="<?= $s['id'] ?>"><button class="btn btn-xs btn-secondary" <?= $i===1?'disabled':'' ?>>↑</button></form>
        <form method="POST" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_down"><input type="hidden" name="step_id" value="<?= $s['id'] ?>"><button class="btn btn-xs btn-secondary" <?= $i===count($steps)?'disabled':'' ?>>↓</button></form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Remove this step?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="step_id" value="<?= $s['id'] ?>"><button class="btn btn-xs btn-danger">Remove</button></form>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; else: ?>
    <div class="text-muted text-center" style="padding:30px">No workflow steps defined yet. Add the first one on the right.</div>
    <?php endif; ?>
  </div>
</div>

<?php if (can('services.manage')): ?>
<div class="card">
  <div class="card-header"><h3>Add Step</h3></div>
  <div class="card-body">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-group"><label>Step Name</label><input type="text" name="step_name" class="form-control" required></div>
      <div class="form-group"><label>Type</label>
        <select name="step_type" class="form-control">
          <?php foreach ($step_types as $t): ?>
          <option value="<?= $t ?>"><?= e(ucwords(str_replace('_',' ',$t))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Requirement Level</label>
        <select name="requirement_level" class="form-control">
          <option value="required">Required</option>
          <option value="optional">Optional</option>
          <option value="conditional">Conditional</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary w-full">+ Add Step</button>
    </form>
  </div>
</div>
<?php endif; ?>

</div>
<?php render_footer(); ?>
