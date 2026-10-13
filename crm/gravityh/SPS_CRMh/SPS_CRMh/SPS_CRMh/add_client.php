<?php
/**
 * SPS Professional Services CRM
 * Add Client Form & Dynamic Custom Fields Handler
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.create');

// Fetch Staff Users
$staff_users = $pdo->query("SELECT id, full_name FROM users WHERE status = 'active'")->fetchAll();

// Fetch Custom Fields for Client
$custom_fields = $pdo->query("SELECT * FROM custom_fields WHERE entity_type = 'client' ORDER BY sort_order ASC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $client_type = $_POST['client_type'] ?? 'company';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $assigned_staff_id = !empty($_POST['assigned_staff_id']) ? intval($_POST['assigned_staff_id']) : null;
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip = trim($_POST['zip'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if (empty($first_name) || empty($last_name)) {
        $errors[] = 'First Name and Last Name are required.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    
    if (empty($errors)) {
        // Auto-generate client number
        $next_id = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM clients")->fetchColumn();
        $client_number = 'CLI-' . (1000 + $next_id);
        
        $stmt = $pdo->prepare("
            INSERT INTO clients 
            (client_number, client_type, first_name, last_name, company_name, email, phone, status, assigned_staff_id, address, city, state, zip, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $client_number, $client_type, $first_name, $last_name, $company_name, $email, $phone,
            $status, $assigned_staff_id, $address, $city, $state, $zip, $notes
        ]);
        $client_id = $pdo->lastInsertId();
        
        // Save Custom Field Values
        if (!empty($custom_fields)) {
            $stmtCF = $pdo->prepare("INSERT INTO custom_field_values (field_id, entity_id, field_value) VALUES (?, ?, ?)");
            foreach ($custom_fields as $cf) {
                $field_key = 'cf_' . $cf['id'];
                if (isset($_POST[$field_key])) {
                    $val = is_array($_POST[$field_key]) ? implode(', ', $_POST[$field_key]) : trim($_POST[$field_key]);
                    $stmtCF->execute([$cf['id'], $client_id, $val]);
                }
            }
        }
        
        log_activity('Add Client', 'clients', $client_id, "Created client #$client_number ($first_name $last_name)");
        set_flash('success', "Client $client_number created successfully!");
        redirect("client_profile.php?id=$client_id");
    }
}

render_header('Add New Client', 'clients');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Add New Client</h1>
        <p class="page-subtitle">Register a new individual or corporate client</p>
    </div>
    <div>
        <a href="clients.php" class="btn btn-secondary">← Back to Clients</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div style="background:#fff5f5; color:#742a2a; border-left:4px solid #e53e3e; padding:12px; margin-bottom:20px; border-radius:4px;">
        <?php foreach ($errors as $err): ?>
            <div>• <?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <form action="add_client.php" method="POST">
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Client Type *</label>
                <select name="client_type" class="form-control" required>
                    <option value="company">Company / Business Entity</option>
                    <option value="individual">Individual / Sole Proprietor</option>
                </select>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Company Name (If applicable)</label>
                <input type="text" name="company_name" class="form-control" placeholder="Acme Corporation LLC">
            </div>
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">First Name *</label>
                <input type="text" name="first_name" class="form-control" required>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Last Name *</label>
                <input type="text" name="last_name" class="form-control" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Primary Email *</label>
                <input type="email" name="email" class="form-control" placeholder="client@example.com" required>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" placeholder="(555) 000-0000">
            </div>
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Assigned Staff Member</label>
                <select name="assigned_staff_id" class="form-control">
                    <option value="">-- Select Staff --</option>
                    <?php foreach ($staff_users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= e($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="active">Active</option>
                    <option value="prospect">Prospect</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <h3 style="font-size:14px; font-weight:600; margin:20px 0 10px 0; color:var(--text-dark);">Address Details</h3>

        <div class="form-group">
            <label class="form-label">Street Address</label>
            <input type="text" name="address" class="form-control" placeholder="100 Main St, Suite 400">
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control">
            </div>
            <div class="form-col form-group">
                <label class="form-label">State</label>
                <input type="text" name="state" class="form-control">
            </div>
            <div class="form-col form-group">
                <label class="form-label">ZIP Code</label>
                <input type="text" name="zip" class="form-control">
            </div>
        </div>

        <?php if (!empty($custom_fields)): ?>
            <h3 style="font-size:14px; font-weight:600; margin:20px 0 10px 0; color:var(--text-dark);">Custom Fields (Managed via Customizer)</h3>
            <div class="form-row" style="flex-wrap:wrap;">
                <?php foreach ($custom_fields as $cf): ?>
                    <div class="form-col form-group" style="min-width:45%;">
                        <label class="form-label"><?= e($cf['field_label']) ?> <?= $cf['is_required'] ? '*' : '' ?></label>
                        <?php if ($cf['field_type'] === 'dropdown'): ?>
                            <select name="cf_<?= $cf['id'] ?>" class="form-control" <?= $cf['is_required'] ? 'required' : '' ?>>
                                <option value="">-- Select --</option>
                                <?php foreach (explode(',', $cf['dropdown_options']) as $opt): ?>
                                    <option value="<?= trim($opt) ?>"><?= trim($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($cf['field_type'] === 'textarea'): ?>
                            <textarea name="cf_<?= $cf['id'] ?>" class="form-control" rows="3" <?= $cf['is_required'] ? 'required' : '' ?>></textarea>
                        <?php else: ?>
                            <input type="<?= $cf['field_type'] === 'number' ? 'number' : ($cf['field_type'] === 'date' ? 'date' : 'text') ?>" 
                                   name="cf_<?= $cf['id'] ?>" class="form-control" value="<?= e($cf['default_value']) ?>" <?= $cf['is_required'] ? 'required' : '' ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Internal Client Notes</label>
            <textarea name="notes" class="form-control" rows="3"></textarea>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn btn-primary">Save Client Profile</button>
            <a href="clients.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php render_footer(); ?>
