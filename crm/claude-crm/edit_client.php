<?php
// ============================================================
// SPS CRM - Edit Client
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('clients.edit');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$client = db_row("SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL", [$id]);
if (!$client) {
    flash_error('Client not found.');
    redirect('clients.php');
}

$errors = [];
$data = [
    'first_name' => $client['first_name'],
    'last_name'  => $client['last_name'],
    'email'      => $client['email'] ?? '',
    'phone'      => $client['phone'] ?? '',
    'status'     => $client['status'],
    'notes'      => $client['notes'] ?? '',
];

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
        // Log field-level changes (activity stage 3)
        $fields_to_track = ['first_name', 'last_name', 'email', 'phone', 'status', 'notes'];
        foreach ($fields_to_track as $f) {
            $old = $client[$f] ?? '';
            $new = $data[$f];
            if ((string)$old !== (string)$new) {
                log_change('clients', $id, $f, $old, $new, $id);
            }
        }

        db_query(
            "UPDATE clients SET first_name=?, last_name=?, email=?, phone=?, status=?, notes=?, updated_at=NOW() WHERE id=?",
            [$data['first_name'], $data['last_name'], $data['email'] ?: null, $data['phone'] ?: null,
             $data['status'], $data['notes'] ?: null, $id]
        );

        save_custom_fields('clients', $id, $_POST);

        log_activity('update', 'clients', $id, "Client updated: {$data['first_name']} {$data['last_name']}", $id);
        flash_success('Client updated successfully.');
        redirect('client_profile.php?id=' . $id);
    }
}

$custom_fields = get_custom_fields('clients');
$custom_values = get_custom_field_values('clients', $id);

render_header('Edit Client', 'clients');
?>
<?= render_flash() ?>

<div style="max-width:640px">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <a href="client_profile.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">← Back to Profile</a>
  </div>

  <div class="card">
    <div class="card-header"><h2>Edit Client — <?= e($client['first_name'] . ' ' . $client['last_name']) ?></h2></div>
    <div class="card-body">
      <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?= e($e) ?></div>
      <?php endforeach; ?>

      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
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
          <?= render_custom_field_input($field, $custom_values[$field['slug']] ?? '') ?>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Save Changes</button>
          <a href="client_profile.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php render_footer(); ?>
