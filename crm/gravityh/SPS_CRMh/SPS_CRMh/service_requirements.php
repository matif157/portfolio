<?php
/**
 * SPS Professional Services CRM
 * Service Requirements Configuration Manager
 */
require_once __DIR__ . '/auth.php';
require_permission('services.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_requirement') {
    verify_csrf();
    $service_id = intval($_POST['service_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $requirement_type = $_POST['requirement_type'] ?? 'file_upload';
    $is_required = isset($_POST['is_required']) ? 1 : 0;

    if (!empty($title) && $service_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO service_requirements (service_id, title, description, requirement_type, is_required) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$service_id, $title, $description, $requirement_type, $is_required]);
        set_flash('success', "Requirement '$title' added.");
        redirect("service_requirements.php?service_id=$service_id");
    }
}

$services = $pdo->query("SELECT id, title, service_code FROM services ORDER BY title")->fetchAll();
$selected_service_id = intval($_GET['service_id'] ?? ($services[0]['id'] ?? 1));

$reqs = $pdo->prepare("SELECT * FROM service_requirements WHERE service_id = ? ORDER BY sort_order ASC");
$reqs->execute([$selected_service_id]);
$requirements = $reqs->fetchAll();

render_header('Service Requirements', 'services');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Service Requirements Configurator</h1>
        <p class="page-subtitle">Define required information, documents, and inputs per service category</p>
    </div>
</div>

<div class="card">
    <form method="GET" action="service_requirements.php" style="display:flex; gap:12px; align-items:flex-end;">
        <div style="flex:1;">
            <label class="form-label">Select Service</label>
            <select name="service_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($services as $srv): ?>
                    <option value="<?= $srv['id'] ?>" <?= $selected_service_id == $srv['id'] ? 'selected' : '' ?>>
                        <?= e($srv['title']) ?> (<?= e($srv['service_code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">
        <span>Configured Requirements</span>
        <button onclick="document.getElementById('addReqModal').style.display = 'block'" class="btn btn-sm btn-primary">+ Add Requirement Item</button>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Requirement Title</th>
                    <th>Type</th>
                    <th>Required / Optional</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requirements)): ?>
                    <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No requirements configured.</td></tr>
                <?php else: ?>
                    <?php foreach ($requirements as $r): ?>
                        <tr>
                            <td><strong><?= e($r['title']) ?></strong></td>
                            <td><span class="badge badge-info"><?= ucfirst(str_replace('_', ' ', $r['requirement_type'])) ?></span></td>
                            <td><span class="badge badge-<?= $r['is_required'] ? 'danger' : 'secondary' ?>"><?= $r['is_required'] ? 'Mandatory' : 'Optional' ?></span></td>
                            <td><?= e($r['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addReqModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="card" style="max-width:500px; margin:80px auto;">
        <div class="card-title">
            <span>Add Requirement</span>
            <button onclick="document.getElementById('addReqModal').style.display = 'none'" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
        </div>
        <form action="service_requirements.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_requirement">
            <input type="hidden" name="service_id" value="<?= $selected_service_id ?>">

            <div class="form-group">
                <label class="form-label">Requirement Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="Monthly PDF Bank Statements">
            </div>

            <div class="form-group">
                <label class="form-label">Requirement Input Type</label>
                <select name="requirement_type" class="form-control">
                    <option value="bank_statement">Bank Statement File</option>
                    <option value="file_upload">General File Upload</option>
                    <option value="text_input">Text Input / Notes</option>
                    <option value="checkbox">Checkbox Confirmation</option>
                    <option value="approval">Professional Approval</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" name="is_required" value="1" checked> Is Mandatory (Blocks workflow progression if missing)
                </label>
            </div>

            <div class="form-group">
                <label class="form-label">Description / Instructions</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>

            <div style="margin-top:15px; text-align:right;">
                <button type="button" onclick="document.getElementById('addReqModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Requirement</button>
            </div>
        </form>
    </div>
</div>

<?php render_footer(); ?>
