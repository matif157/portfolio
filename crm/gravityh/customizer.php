<?php
/**
 * GRAVITY CRM - Customizer
 * Phase 1: Foundation
 * 
 * Yahan administrator CRM customize kar sakta hai:
 * - Custom fields create/edit/delete
 * - Module enable/disable
 * - Module icons aur ordering
 * 
 * Customizer ka asar poore CRM par hota hai (real-time).
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('customizer', 'view');

$user = current_user();
$page_title = 'Customizer';
$active_page = 'customizer';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create_field' || $action === 'update_field') {
        require_permission('customizer', 'edit');
        
        $field_id = (int)post('field_id', 0);
        $module = sanitize(post('module'));
        $field_name = sanitize(post('field_name'));
        $field_label = sanitize(post('field_label'));
        $field_type = sanitize(post('field_type', 'text'));
        $is_required = post('is_required') ? 1 : 0;
        $default_value = sanitize(post('default_value'));
        $dropdown_options = sanitize(post('dropdown_options'));
        $min_value = sanitize(post('min_value'));
        $max_value = sanitize(post('max_value'));
        $char_length = (int)post('char_length', 0);
        $visibility = sanitize(post('visibility', 'visible'));
        $sort_order = (int)post('sort_order', 0);
        $is_active = post('is_active') ? 1 : 0;
        
        $errors = [];
        validate_required($module, 'Module', $errors);
        validate_required($field_name, 'Field name', $errors);
        validate_required($field_label, 'Field label', $errors);
        
        if (empty($errors)) {
            if ($action === 'create_field') {
                db_execute(
                    'INSERT INTO custom_fields (module, field_name, field_label, field_type, is_required, default_value, dropdown_options, min_value, max_value, char_length, visibility, sort_order, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$module, $field_name, $field_label, $field_type, $is_required, $default_value, $dropdown_options, $min_value, $max_value, $char_length, $visibility, $sort_order, $is_active]
                );
                $field_id = db_last_id();
                log_activity('customizer', 'create', $field_id, 'Created custom field: ' . $field_label . ' for ' . $module);
                flash('success', 'Custom field created successfully.');
            } else {
                $existing = db_row('SELECT * FROM custom_fields WHERE id = ?', [$field_id]);
                if (!$existing) {
                    flash('error', 'Field not found.');
                    redirect('customizer.php');
                }
                db_execute(
                    'UPDATE custom_fields SET module = ?, field_name = ?, field_label = ?, field_type = ?, is_required = ?, default_value = ?, dropdown_options = ?, min_value = ?, max_value = ?, char_length = ?, visibility = ?, sort_order = ?, is_active = ? WHERE id = ?',
                    [$module, $field_name, $field_label, $field_type, $is_required, $default_value, $dropdown_options, $min_value, $max_value, $char_length, $visibility, $sort_order, $is_active, $field_id]
                );
                log_activity('customizer', 'update', $field_id, 'Updated custom field: ' . $field_label);
                flash('success', 'Custom field updated successfully.');
            }
            redirect('customizer.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete_field') {
        require_permission('customizer', 'edit');
        $field_id = (int)post('field_id', 0);
        
        $existing = db_row('SELECT * FROM custom_fields WHERE id = ?', [$field_id]);
        if ($existing) {
            db_execute('DELETE FROM custom_fields WHERE id = ?', [$field_id]);
            log_activity('customizer', 'delete', $field_id, 'Deleted custom field: ' . $existing['field_label']);
            flash('success', 'Custom field deleted successfully.');
        }
        redirect('customizer.php');
    }
    
    if ($action === 'update_module') {
        require_permission('customizer', 'edit');
        $module_key = sanitize(post('module_key'));
        $module_label = sanitize(post('module_label'));
        $icon = sanitize(post('icon'));
        $sort_order = (int)post('sort_order', 0);
        $is_enabled = post('is_enabled') ? 1 : 0;
        
        $existing = db_row('SELECT * FROM module_configs WHERE module_key = ?', [$module_key]);
        if ($existing) {
            db_execute(
                'UPDATE module_configs SET module_label = ?, icon = ?, sort_order = ?, is_enabled = ?, updated_by = ? WHERE module_key = ?',
                [$module_label, $icon, $sort_order, $is_enabled, $user['id'], $module_key]
            );
            log_activity('customizer', 'update', $existing['id'], 'Updated module config: ' . $module_label);
            flash('success', 'Module configuration updated successfully.');
        }
        redirect('customizer.php');
    }
}

// Get all custom fields
$custom_fields = db_query('SELECT * FROM custom_fields ORDER BY module, sort_order');

// Group by module
$fields_by_module = [];
foreach ($custom_fields as $field) {
    $fields_by_module[$field['module']][] = $field;
}

// Get module configs
$module_configs = db_query('SELECT * FROM module_configs ORDER BY sort_order');

// Edit field
$edit_field = null;
if (get('edit_field')) {
    $edit_field = db_row('SELECT * FROM custom_fields WHERE id = ?', [(int)get('edit_field')]);
}

// Available modules for custom fields
$available_modules = ['clients', 'leads', 'contacts', 'businesses', 'users'];
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-sliders"></i> Customizer</h1>
    <button class="btn-primary-custom" onclick="toggleNewField()"><i class="bi bi-plus"></i> New Custom Field</button>
</div>

<!-- New/Edit Field Form -->
<div class="card" id="fieldForm" style="display:<?= $edit_field ? 'block' : 'none' ?>;">
    <div class="card-header">
        <?= $edit_field ? 'Edit Custom Field: ' . e($edit_field['field_label']) : 'Create New Custom Field' ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $edit_field ? 'update_field' : 'create_field' ?>">
            <input type="hidden" name="field_id" value="<?= $edit_field['id'] ?? 0 ?>">
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Module *</label>
                    <select name="module" class="form-control-custom" required>
                        <?php foreach ($available_modules as $mod): ?>
                            <option value="<?= $mod ?>" <?= ($edit_field['module'] ?? '') === $mod ? 'selected' : '' ?>>
                                <?= e(ucfirst($mod)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Field Name *</label>
                    <input type="text" name="field_name" class="form-control-custom" value="<?= e($edit_field['field_name'] ?? '') ?>" placeholder="e.g. ssn" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Field Label *</label>
                    <input type="text" name="field_label" class="form-control-custom" value="<?= e($edit_field['field_label'] ?? '') ?>" placeholder="e.g. SSN" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Field Type</label>
                    <select name="field_type" class="form-control-custom">
                        <?php
                        $types = ['text', 'textarea', 'number', 'integer', 'currency', 'email', 'phone', 'date', 'datetime', 'dropdown', 'multiselect', 'checkbox', 'url'];
                        foreach ($types as $type): ?>
                            <option value="<?= $type ?>" <?= ($edit_field['field_type'] ?? '') === $type ? 'selected' : '' ?>>
                                <?= e(ucfirst($type)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Visibility</label>
                    <select name="visibility" class="form-control-custom">
                        <option value="visible" <?= ($edit_field['visibility'] ?? '') === 'visible' ? 'selected' : '' ?>>Visible</option>
                        <option value="hidden" <?= ($edit_field['visibility'] ?? '') === 'hidden' ? 'selected' : '' ?>>Hidden</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control-custom" value="<?= e($edit_field['sort_order'] ?? 0) ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Default Value</label>
                    <input type="text" name="default_value" class="form-control-custom" value="<?= e($edit_field['default_value'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Dropdown Options (comma separated)</label>
                    <input type="text" name="dropdown_options" class="form-control-custom" value="<?= e($edit_field['dropdown_options'] ?? '') ?>" placeholder="Option1, Option2, Option3">
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Max Length</label>
                    <input type="number" name="char_length" class="form-control-custom" value="<?= e($edit_field['char_length'] ?? 0) ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Min Value</label>
                    <input type="text" name="min_value" class="form-control-custom" value="<?= e($edit_field['min_value'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Max Value</label>
                    <input type="text" name="max_value" class="form-control-custom" value="<?= e($edit_field['max_value'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input type="checkbox" name="is_required" value="1" class="form-check-input" id="isRequired" <?= ($edit_field['is_required'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isRequired">Required</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" <?= ($edit_field['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> <?= $edit_field ? 'Update' : 'Create' ?> Field</button>
            <button type="button" class="btn-secondary-custom" onclick="toggleNewField()">Cancel</button>
        </form>
    </div>
</div>

<!-- Module Configs -->
<div class="card">
    <div class="card-header">Module Configuration</div>
    <div class="card-body">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Label</th>
                    <th>Icon</th>
                    <th>Sort</th>
                    <th>Enabled</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($module_configs as $mod): ?>
                    <tr>
                        <td><strong><?= e($mod['module_key']) ?></strong></td>
                        <td><?= e($mod['module_label']) ?></td>
                        <td><i class="bi <?= e($mod['icon']) ?>"></i> <?= e($mod['icon']) ?></td>
                        <td><?= $mod['sort_order'] ?></td>
                        <td><?= $mod['is_enabled'] ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-danger">Disabled</span>' ?></td>
                        <td>
                            <button class="btn-secondary-custom btn-sm" onclick="toggleModuleEdit(<?= $mod['id'] ?>)"><i class="bi bi-pencil"></i></button>
                        </td>
                    </tr>
                    <tr id="moduleEdit_<?= $mod['id'] ?>" style="display:none;">
                        <td colspan="6">
                            <form method="POST" action="" class="row g-2">
                                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update_module">
                                <input type="hidden" name="module_key" value="<?= e($mod['module_key']) ?>">
                                <div class="col-md-3">
                                    <input type="text" name="module_label" class="form-control-custom" value="<?= e($mod['module_label']) ?>">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="icon" class="form-control-custom" value="<?= e($mod['icon']) ?>" placeholder="bi-icon-name">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" name="sort_order" class="form-control-custom" value="<?= $mod['sort_order'] ?>">
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check mt-2">
                                        <input type="checkbox" name="is_enabled" value="1" class="form-check-input" id="modEnabled_<?= $mod['id'] ?>" <?= $mod['is_enabled'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="modEnabled_<?= $mod['id'] ?>">Enabled</label>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn-primary-custom btn-sm"><i class="bi bi-check"></i> Save</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Custom Fields -->
<div class="card">
    <div class="card-header">Custom Fields (<?= count($custom_fields) ?>)</div>
    <div class="card-body">
        <?php if (empty($custom_fields)): ?>
            <p class="text-muted">No custom fields yet. Click "New Custom Field" to create one.</p>
        <?php else: ?>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Label</th>
                        <th>Field Name</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Status</th>
                        <th>Sort</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($custom_fields as $field): ?>
                        <tr>
                            <td><?= e(ucfirst($field['module'])) ?></td>
                            <td><strong><?= e($field['field_label']) ?></strong></td>
                            <td><?= e($field['field_name']) ?></td>
                            <td><?= e(ucfirst($field['field_type'])) ?></td>
                            <td><?= $field['is_required'] ? '<span class="badge badge-danger">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?></td>
                            <td><?= $field['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                            <td><?= $field['sort_order'] ?></td>
                            <td>
                                <a href="customizer.php?edit_field=<?= $field['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this field?')">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_field">
                                    <input type="hidden" name="field_id" value="<?= $field['id'] ?>">
                                    <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleNewField() {
    const form = document.getElementById('fieldForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block' && !form.querySelector('input[name="field_id"]').value) {
        form.querySelectorAll('input[type="text"], input[type="number"]').forEach(i => i.value = '');
        form.querySelector('select[name="module"]').value = 'clients';
        form.querySelector('select[name="field_type"]').value = 'text';
        form.querySelector('select[name="visibility"]').value = 'visible';
        form.querySelector('input[name="is_required"]').checked = false;
        form.querySelector('input[name="is_active"]').checked = true;
    }
}

function toggleModuleEdit(id) {
    const row = document.getElementById('moduleEdit_' + id);
    row.style.display = row.style.display === 'none' ? '' : 'none';
}
</script>

<?php include __DIR__ . '/footer.php'; ?>