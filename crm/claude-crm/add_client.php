<?php
// ============================================================
// SPS CRM - Add Client
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('clients.create');

$errors = [];
$data = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'status' => 'active', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $data['first_name'] = trim($_POST['first_name'] ?? '');
    $data['last_name']  = trim($_POST['last_name'] ?? '');
    $data['email']      = trim($_POST['email'] ?? '');
    $data['phone']      = trim($_POST['phone'] ?? '');
    $data['status']     = $_POST['status'] ?? 'active';
    $data['notes']      = trim($_POST['notes'] ?? '');

    $errors = validate_required($data, ['first_name' => 'First name', 'last_name' => 'Last name']);
    if ($data['email'] && !validate_email($data['email'])) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($data['phone'] && !validate_phone($data['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (!in_array($data['status'], ['active', 'inactive', 'lead', 'archived'], true)) {
        $data['status'] = 'active';
    }

    if (empty($errors)) {
        $client_id = db_insert(
            "INSERT INTO clients (first_name, last_name, email, phone, status, assigned_to, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [$data['first_name'], $data['last_name'], $data['email'] ?: null, $data['phone'] ?: null,
             $data['status'], current_user_id(), $data['notes'] ?: null]
        );

        save_custom_fields('clients', $client_id, $_POST);

        log_activity('create', 'clients', $client_id, "Client created: {$data['first_name']} {$data['last_name']}", $client_id);
        flash_success('Client added successfully.');
        redirect('client_profile.php?id=' . $client_id);
    }
}

$custom_fields = get_custom_fields('clients');

render_header('Add Client', 'clients');
?>
<?= render_flash() ?>

<div style="max-width:640px">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <a href="clients.php" class="btn btn-secondary btn-sm">← Back to Clients</a>
  </div>

  <div class="card">
    <div class="card-header"><h2>Add Client</h2></div>
    <div class="card-body">
      <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?= e($e) ?></div>
      <?php endforeach; ?>

      <form method="POST">
        <?= csrf_field() ?>
        <div class="form-row">
          <div class="form-group">
            <label>First Name <span class="text-danger">*</span></label>
            <input type="text" name="first_name" class="form-control" value="<?= e($data['first_name']) ?>" required>
          </div>
          <div class="form-group">
            <label>Last Name <span class="text-danger">*</span></label>
            <input type="text" name="last_name" class="form-control" value="<?= e($data['last_name']) ?>" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= e($data['phone']) ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control">
            <?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'lead' => 'Lead', 'archived' => 'Archived'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= $data['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" class="form-control"><?= e($data['notes']) ?></textarea>
        </div>

        <?php if ($custom_fields): ?>
        <hr>
        <div class="section-title" style="font-size:13px">Additional Fields</div>
        <?php foreach ($custom_fields as $field): ?>
          <?= render_custom_field_input($field) ?>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Add Client</button>
          <a href="clients.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php render_footer(); ?>
