<?php
/**
 * SPS Professional Services CRM
 * Manage Client Businesses
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

$client_id = intval($_GET['client_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_business') {
    verify_csrf();
    $target_client_id = intval($_POST['client_id']);
    $business_name = trim($_POST['business_name']);
    $registration_number = trim($_POST['registration_number'] ?? '');
    $tax_id = trim($_POST['tax_id'] ?? '');
    $entity_type = trim($_POST['entity_type'] ?? 'LLC');
    $industry = trim($_POST['industry'] ?? '');
    $fiscal_year_end = trim($_POST['fiscal_year_end'] ?? 'December');

    if (!empty($business_name) && $target_client_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO businesses (client_id, business_name, registration_number, tax_id, entity_type, industry, fiscal_year_end) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$target_client_id, $business_name, $registration_number, $tax_id, $entity_type, $industry, $fiscal_year_end]);
        $bus_id = $pdo->lastInsertId();

        log_activity('Add Business', 'businesses', $bus_id, "Registered business $business_name for client #$target_client_id");
        set_flash('success', "Business entity '$business_name' registered successfully.");
        redirect("businesses.php" . ($client_id ? "?client_id=$client_id" : ""));
    }
}

$query = "
    SELECT b.*, c.first_name, c.last_name, c.company_name,
           (SELECT COUNT(*) FROM bank_accounts ba WHERE ba.business_id = b.id) as account_count
    FROM businesses b
    JOIN clients c ON b.client_id = c.id
    WHERE 1=1
";
$params = [];
if ($client_id > 0) {
    $query .= " AND b.client_id = ?";
    $params[] = $client_id;
}
$query .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$businesses = $stmt->fetchAll();

$all_clients = $pdo->query("SELECT id, first_name, last_name, company_name FROM clients ORDER BY company_name, first_name")->fetchAll();

render_header('Businesses Directory', 'businesses');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Client Businesses</h1>
        <p class="page-subtitle">Corporate entities and legal structures registered under clients</p>
    </div>
    <div>
        <button onclick="document.getElementById('addBusinessModal').style.display = 'block'" class="btn btn-primary">+ Add Business Entity</button>
    </div>
</div>

<!-- Modal Form for Adding Business -->
<div id="addBusinessModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="card" style="max-width:550px; margin:80px auto;">
        <div class="card-title">
            <span>Add Business Entity</span>
            <button onclick="document.getElementById('addBusinessModal').style.display = 'none'" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
        </div>
        <form action="businesses.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_business">

            <div class="form-group">
                <label class="form-label">Client Owner *</label>
                <select name="client_id" class="form-control" required>
                    <option value="">-- Select Client --</option>
                    <?php foreach ($all_clients as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= $client_id == $cl['id'] ? 'selected' : '' ?>>
                            <?= e($cl['company_name'] ?: "{$cl['first_name']} {$cl['last_name']}") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Business Name *</label>
                <input type="text" name="business_name" class="form-control" required placeholder="ABC Enterprises LLC">
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Entity Type</label>
                    <select name="entity_type" class="form-control">
                        <option value="LLC">LLC</option>
                        <option value="C-Corp">C-Corporation</option>
                        <option value="S-Corp">S-Corporation</option>
                        <option value="Partnership">Partnership</option>
                        <option value="Sole Proprietorship">Sole Proprietorship</option>
                    </select>
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Tax ID / EIN</label>
                    <input type="text" name="tax_id" class="form-control" placeholder="12-3456789">
                </div>
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">State Registration #</label>
                    <input type="text" name="registration_number" class="form-control">
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Industry</label>
                    <input type="text" name="industry" class="form-control" placeholder="Consulting, Retail, etc.">
                </div>
            </div>

            <div style="margin-top:15px; text-align:right;">
                <button type="button" onclick="document.getElementById('addBusinessModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Business</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Business Name</th>
                    <th>Client Owner</th>
                    <th>Entity Type</th>
                    <th>EIN / Tax ID</th>
                    <th>Bank Accounts</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($businesses)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No businesses found.</td></tr>
                <?php else: ?>
                    <?php foreach ($businesses as $b): ?>
                        <tr>
                            <td><strong><?= e($b['business_name']) ?></strong></td>
                            <td>
                                <a href="client_profile.php?id=<?= $b['client_id'] ?>">
                                    <?= e($b['company_name'] ?: "{$b['first_name']} {$b['last_name']}") ?>
                                </a>
                            </td>
                            <td><span class="badge badge-secondary"><?= e($b['entity_type']) ?></span></td>
                            <td><?= e($b['tax_id'] ?: '-') ?></td>
                            <td><a href="bank_accounts.php?business_id=<?= $b['id'] ?>"><span class="badge badge-info"><?= $b['account_count'] ?> Accounts</span></a></td>
                            <td><span class="badge badge-success"><?= ucfirst($b['status']) ?></span></td>
                            <td>
                                <a href="bank_accounts.php?business_id=<?= $b['id'] ?>" class="btn btn-sm btn-secondary">Bank Accounts</a>
                                <a href="bank_statements.php?business_id=<?= $b['id'] ?>" class="btn btn-sm btn-primary">Statements Matrix</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
