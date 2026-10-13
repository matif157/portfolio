<?php
/**
 * SPS Professional Services CRM
 * Service Catalog & Definition Manager
 */
require_once __DIR__ . '/auth.php';
require_permission('services.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_service') {
    verify_csrf();
    $service_code = strtoupper(trim($_POST['service_code']));
    $title = trim($_POST['title']);
    $category_id = intval($_POST['category_id']);
    $price = floatval($_POST['price']);
    $recurring_period = $_POST['recurring_period'] ?? 'monthly';
    $description = trim($_POST['description'] ?? '');

    if (!empty($service_code) && !empty($title)) {
        $stmt = $pdo->prepare("INSERT INTO services (category_id, service_code, title, description, price, recurring_period) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$category_id, $service_code, $title, $description, $price, $recurring_period]);
        set_flash('success', "Service '$title' added to catalog.");
        redirect('services.php');
    }
}

$services = $pdo->query("
    SELECT s.*, sc.name as category_name,
           (SELECT COUNT(*) FROM service_workflow_steps sw WHERE sw.service_id = s.id) as step_count,
           (SELECT COUNT(*) FROM service_requirements sr WHERE sr.service_id = s.id) as req_count
    FROM services s
    LEFT JOIN service_categories sc ON s.category_id = sc.id
    ORDER BY s.id ASC
")->fetchAll();

$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name")->fetchAll();

render_header('Service Catalog', 'services');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Service Catalog</h1>
        <p class="page-subtitle">Standardized professional service definitions, workflow engine, and requirements</p>
    </div>
    <div>
        <button onclick="document.getElementById('addServiceModal').style.display = 'block'" class="btn btn-primary">+ Add New Service</button>
    </div>
</div>

<div id="addServiceModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="card" style="max-width:550px; margin:80px auto;">
        <div class="card-title">
            <span>Add Catalog Service</span>
            <button onclick="document.getElementById('addServiceModal').style.display = 'none'" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
        </div>
        <form action="services.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_service">

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Service Code *</label>
                    <input type="text" name="service_code" class="form-control" required placeholder="SRV-TAX-01">
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Service Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="Corporate Bookkeeping Service">
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Base Fee / Price ($)</label>
                    <input type="number" step="0.01" name="price" class="form-control" required value="450.00">
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Recurring Schedule</label>
                    <select name="recurring_period" class="form-control">
                        <option value="none">One-time Service</option>
                        <option value="monthly">Monthly Recurring</option>
                        <option value="quarterly">Quarterly Recurring</option>
                        <option value="annually">Annual Recurring</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Service Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>

            <div style="margin-top:15px; text-align:right;">
                <button type="button" onclick="document.getElementById('addServiceModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Service</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Service Code</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Recurring</th>
                    <th>Workflow Steps</th>
                    <th>Requirements</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $srv): ?>
                    <tr <?= $srv['id'] == 1 ? 'style="background:#f0f9ff;"' : '' ?>>
                        <td><strong><?= e($srv['service_code']) ?></strong></td>
                        <td>
                            <a href="service_workflow.php?service_id=<?= $srv['id'] ?>"><strong><?= e($srv['title']) ?></strong></a>
                            <?php if ($srv['id'] == 1): ?>
                                <span class="badge badge-success" style="margin-left:5px;">AUTOMATED END-TO-END</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($srv['category_name'] ?: 'General') ?></td>
                        <td><strong><?= format_currency($srv['price']) ?></strong></td>
                        <td><span class="badge badge-secondary"><?= ucfirst($srv['recurring_period']) ?></span></td>
                        <td><span class="badge badge-info"><?= $srv['step_count'] ?> Steps</span></td>
                        <td><span class="badge badge-info"><?= $srv['req_count'] ?> Requirements</span></td>
                        <td>
                            <?php if ($srv['id'] == 1): ?>
                                <a href="bank_statements.php" class="btn btn-sm btn-primary">⚡ Open Statement Engine</a>
                            <?php else: ?>
                                <a href="service_workflow.php?service_id=<?= $srv['id'] ?>" class="btn btn-sm btn-secondary">View Workflow</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
