<?php
/**
 * SPS Professional Services CRM
 * Edit Client Form & Custom Fields
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.edit');

$client_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    set_flash('danger', 'Client not found.');
    redirect('clients.php');
}

$staff_users = $pdo->query("SELECT id, full_name FROM users WHERE status = 'active'")->fetchAll();
$custom_fields = $pdo->query("SELECT * FROM custom_fields WHERE entity_type = 'client' ORDER BY sort_order ASC")->fetchAll();

// Fetch existing custom field values for client
$cf_stmt = $pdo->prepare("SELECT field_id, field_value FROM custom_field_values WHERE entity_id = ?");
$cf_stmt->execute([$client_id]);
$existing_cf_values = $cf_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

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
    
    if (empty($first_name) || empty($last_name)) {
        $errors[] = 'First Name and Last Name are required.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    
    if (empty($errors)) {
        $stmtUpdate = $pdo->prepare("
            UPDATE clients SET 
            client_type = ?, first_name = ?, last_name = ?, company_name = ?, email = ?, phone = ?, 
            status = ?, assigned_staff_id = ?, address = ?, city = ?, state = ?, zip = ?, notes = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $client_type, $first_name, $last_name, $company_name, $email, $phone,
            $status, $assigned_staff_id, $address, $city, $state, $zip, $notes, $client_id
        ]);
        
        // Update Custom Fields
        if (!empty($custom_fields)) {
            $stmtCF = $pdo->prepare("INSERT INTO custom_field_values (field_id, entity_id, field_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)");
            foreach ($custom_fields as $cf) {
                $field_key = 'cf_' . $cf['id'];
                if (isset($_POST[$field_key])) {
                    $val = is_array($_POST[$field_key]) ? implode(', ', $_POST[$field_key]) : trim($_POST[$field_key]);
                    $stmtCF->execute([$cf['id'], $client_id, $val]);
                }
            }
        }
        
        log_activity('Edit Client', 'clients', $client_id, "Updated client information for {$client['client_number']}");
        set_flash('success', "Client {$client['client_number']} updated successfully.");
        redirect("client_profile.php?id=$client_id");
    }
}

render_header("Edit Client - {$client['client_number']}", 'clients');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Client: <?= e($client['client_number']) ?></h1>
        <p class="page-subtitle">Update contact details, assigned staff, and custom parameters</p>
    </div>
    <div>
        <a href="client_profile.php?id=<?= $client_id ?>" class="btn btn-secondary">← Back to Profile</a>
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
    <form action="edit_client.php?id=<?= $client_id ?>" method="POST">
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Client Type *</label>
                <select name="client_type" class="form-control" required>
                    <option value="company" <?= $client['client_type'] === 'company' ? 'selected' : '' ?>>Company / Business Entity</option>
                    <option value="individual" <?= $client['client_type'] === 'individual' ? 'selected' : '' ?>>Individual / Sole Proprietor</option>
                </select>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Company Name</label>
                <input type="text" name="company_name" class="form-control" value="<?= e($client['company_name']) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">First Name *</label>
                <input type="text" name="first_name" class="form-control" value="<?= e($client['first_name']) ?>" required>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Last Name *</label>
                <input type="text" name="last_name" class="form-control" value="<?= e($client['last_name']) ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Primary Email *</label>
                <input type="email" name="email" class="form-control" value="<?= e($client['email']) ?>" required>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?= e($client['phone']) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Assigned Staff Member</label>
                <select name="assigned_staff_id" class="form-control">
                    <option value="">-- Select Staff --</option>
                    <?php foreach ($staff_users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $client['assigned_staff_id'] == $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="active" <?= $client['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="prospect" <?= $client['status'] === 'prospect' ? 'selected' : '' ?>>Prospect</option>
                    <option value="inactive" <?= $client['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>

        <h3 style="font-size:14px; font-weight:600; margin:20px 0 10px 0; color:var(--text-dark);">Address Details</h3>

        <div class="form-group">
            <label class="form-label">Street Address</label>
            <input type="text" name="address" class="form-control" value="<?= e($client['address']) ?>">
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="<?= e($client['city']) ?>">
            </div>
            <div class="form-col form-group">
                <label class="form-label">State</label>
                <input type="text" name="state" class="form-control" value="<?= e($client['state']) ?>">
            </div>
            <div class="form-col form-group">
                <label class="form-label">ZIP Code</label>
                <input type="text" name="zip" class="form-control" value="<?= e($client['zip']) ?>">
            </div>
        </div>

        <?php if (!empty($custom_fields)): ?>
            <h3 style="font-size:14px; font-weight:600; margin:20px 0 10px 0; color:var(--text-dark);">Custom Fields</h3>
            <div class="form-row" style="flex-wrap:wrap;">
                <?php foreach ($custom_fields as $cf): ?>
                    <?php $val = $existing_cf_values[$cf['id']] ?? $cf['default_value']; ?>
                    <div class="form-col form-group" style="min-width:45%;">
                        <label class="form-label"><?= e($cf['field_label']) ?></label>
                        <?php if ($cf['field_type'] === 'dropdown'): ?>
                            <select name="cf_<?= $cf['id'] ?>" class="form-control">
                                <option value="">-- Select --</option>
                                <?php foreach (explode(',', $cf['dropdown_options']) as $opt): ?>
                                    <option value="<?= trim($opt) ?>" <?= trim($opt) === $val ? 'selected' : '' ?>><?= trim($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($cf['field_type'] === 'textarea'): ?>
                            <textarea name="cf_<?= $cf['id'] ?>" class="form-control" rows="3"><?= e($val) ?></textarea>
                        <?php else: ?>
                            <input type="<?= $cf['field_type'] === 'number' ? 'number' : ($cf['field_type'] === 'date' ? 'date' : 'text') ?>" 
                                   name="cf_<?= $cf['id'] ?>" class="form-control" value="<?= e($val) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Internal Client Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= e($client['notes']) ?></textarea>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn btn-primary">Update Client Profile</button>
            <a href="client_profile.php?id=<?= $client_id ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php render_footer(); ?>
