<?php
/**
 * GRAVITY CRM — Master Real-Time CRM Customizer
 * 
 * Yahan Administrator pore CRM ki layout, modules availability (enable/disable),
 * dynamic custom fields, aur user-facing titles real-time customize kar sakta hai.
 * Yeh Customizer seedha database ki crm_modules aur custom_fields tables se connected hai.
 */

require_once __DIR__ . '/auth.php';
require_permission('admin.customizer');

// Handle Customizer Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // 1. Toggle Module Enabled / Disabled State
    if ($action === 'update_modules') {
        $modules = $_POST['modules'] ?? [];
        $titles = $_POST['titles'] ?? [];
        
        $all_db_modules = $pdo->query("SELECT module_key FROM crm_modules")->fetchAll(PDO::FETCH_COLUMN);
        
        $stmtUpd = $pdo->prepare("UPDATE crm_modules SET is_enabled = ?, custom_title = ? WHERE module_key = ?");
        foreach ($all_db_modules as $mod_key) {
            $is_enabled = isset($modules[$mod_key]) ? 1 : 0;
            $custom_title = trim($titles[$mod_key] ?? '');
            $stmtUpd->execute([$is_enabled, $custom_title ?: null, $mod_key]);
        }

        log_activity('Customize Modules', 'customizer', 0, 'Updated module availability and custom navigation labels', 'stage2_sensitive');
        set_flash('success', 'CRM Modules and navigation titles updated successfully!');
        redirect('customizer.php?tab=modules');

    // 2. Add Custom Field to CRM Section
    } elseif ($action === 'add_field') {
        $entity_type = $_POST['entity_type'] ?? 'client';
        $field_name = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['field_name'] ?? ''));
        $field_label = trim($_POST['field_label'] ?? '');
        $field_type = $_POST['field_type'] ?? 'text';
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        $dropdown_options = trim($_POST['dropdown_options'] ?? '');
        $default_value = trim($_POST['default_value'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 99);

        if ($field_name && $field_label) {
            $stmt = $pdo->prepare("
                INSERT INTO custom_fields (entity_type, field_name, field_label, field_type, is_required, is_visible, dropdown_options, default_value, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$entity_type, $field_name, $field_label, $field_type, $is_required, $is_visible, $dropdown_options ?: null, $default_value ?: null, $sort_order]);

            log_activity('Add Custom Field', 'customizer', 0, "Added custom field '$field_label' to '$entity_type' section.", 'stage1_normal');
            set_flash('success', "Custom field '{$field_label}' added to " . ucfirst($entity_type) . " section!");
        }
        redirect('customizer.php?tab=fields&section=' . $entity_type);

    // 3. Delete Custom Field
    } elseif ($action === 'delete_field') {
        $field_id = intval($_POST['field_id']);
        $stmtSnap = $pdo->prepare("SELECT * FROM custom_fields WHERE id = ?");
        $stmtSnap->execute([$field_id]);
        $field = $stmtSnap->fetch();

        if ($field) {
            log_deleted_record('custom_fields', $field_id, $field, 'customizer', "Removed custom field '{$field['field_label']}' from '{$field['entity_type']}'");
            $pdo->prepare("DELETE FROM custom_fields WHERE id = ?")->execute([$field_id]);
            set_flash('success', 'Custom field removed from CRM.');
        }
        redirect('customizer.php?tab=fields');

    // 4. Update Visual Theme & Layout
    } elseif ($action === 'update_theme') {
        $app_theme = trim($_POST['app_theme'] ?? 'modern');
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('app_theme', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$app_theme]);

        log_activity('Update Theme', 'customizer', 0, "Changed global CRM theme to '$app_theme'", 'stage1_normal');
        set_flash('success', "CRM visual theme switched to " . strtoupper($app_theme) . "!");
        redirect('customizer.php?tab=themes');
    }
}

$active_tab = $_GET['tab'] ?? 'modules';
$section_filter = $_GET['section'] ?? 'all';

// Fetch Modules
$crm_modules = $pdo->query("SELECT * FROM crm_modules ORDER BY sidebar_order ASC")->fetchAll();

// Sections dictionary
$sections = [
    'client' => '👤 Client Profile',
    'business' => '🏬 Business Entity',
    'contact' => '📋 Contacts',
    'lead' => '⭐ Leads',
    'project' => '📁 Projects',
    'service' => '⚙️ Service Delivery',
    'invoice' => '💰 Invoices',
    'quotation' => '📄 Quotations / Estimates',
    'task' => '✅ Tasks',
    'document' => '📎 Documents',
];

$field_types = [
    'text' => 'Short Text',
    'textarea' => 'Long Text (Textarea)',
    'number' => 'Numeric',
    'currency' => 'Currency / Money',
    'date' => 'Date Picker',
    'datetime' => 'Date & Time',
    'dropdown' => 'Dropdown (Select)',
    'multiselect' => 'Multi-Select Choices',
    'checkbox' => 'Checkbox (Yes/No)',
    'email' => 'Email Address',
    'phone' => 'Phone Number',
    'url' => 'Website URL',
];

// Fetch Custom Fields
if ($section_filter === 'all') {
    $fields = $pdo->query("SELECT * FROM custom_fields ORDER BY entity_type, sort_order ASC")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM custom_fields WHERE entity_type = ? ORDER BY sort_order ASC");
    $stmt->execute([$section_filter]);
    $fields = $stmt->fetchAll();
}

$current_theme = get_setting('app_theme', 'modern');

render_header('Master CRM Customizer', 'admin');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🎛️ Master CRM Customizer &amp; Layout Manager</h1>
        <p class="page-subtitle">Configure module availability, custom fields, user-facing text, and real-time visual themes</p>
    </div>
</div>

<!-- Customizer Navigation Tabs -->
<div style="display:flex; gap:10px; border-bottom:2px solid var(--border-color); padding-bottom:12px; margin-bottom:24px;">
    <a href="customizer.php?tab=modules" class="btn <?= $active_tab === 'modules' ? 'btn-primary' : 'btn-secondary' ?>">
        📦 Module Manager (Enable / Disable)
    </a>
    <a href="customizer.php?tab=fields" class="btn <?= $active_tab === 'fields' ? 'btn-primary' : 'btn-secondary' ?>">
        🧩 Section Custom Fields
    </a>
    <a href="customizer.php?tab=themes" class="btn <?= $active_tab === 'themes' ? 'btn-primary' : 'btn-secondary' ?>">
        🎨 Themes &amp; Role Layouts
    </a>
</div>

<!-- TAB 1: MODULE MANAGER -->
<?php if ($active_tab === 'modules'): ?>
    <div class="card">
        <div class="card-title">
            <span>CRM Module Availability &amp; Display Titles</span>
            <span style="font-size:12px; font-weight:normal; color:var(--text-muted);">Unchecked modules will be hidden from navigation and access</span>
        </div>

        <form action="customizer.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_modules">

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:60px;">Status</th>
                            <th>Module Key</th>
                            <th>Icon</th>
                            <th>Default Name</th>
                            <th>Custom User-Facing Title</th>
                            <th>Menu Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($crm_modules as $mod): ?>
                            <tr>
                                <td>
                                    <label style="cursor:pointer;">
                                        <input type="checkbox" name="modules[<?= e($mod['module_key']) ?>]" value="1" <?= $mod['is_enabled'] ? 'checked' : '' ?> style="transform:scale(1.2);">
                                    </label>
                                </td>
                                <td><code><?= e($mod['module_key']) ?></code></td>
                                <td style="font-size:18px;"><?= e($mod['icon']) ?></td>
                                <td><strong><?= e($mod['module_name']) ?></strong></td>
                                <td>
                                    <input type="text" name="titles[<?= e($mod['module_key']) ?>]" value="<?= e($mod['custom_title'] ?: $mod['module_name']) ?>" class="form-control" style="max-width:240px; padding:6px 10px;">
                                </td>
                                <td><?= $mod['sidebar_order'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:20px; text-align:right;">
                <button type="submit" class="btn btn-primary" style="padding:10px 24px;">Save Module Settings</button>
            </div>
        </form>
    </div>

<!-- TAB 2: CUSTOM FIELDS -->
<?php elseif ($active_tab === 'fields'): ?>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <!-- Section Filter Tabs -->
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <a href="customizer.php?tab=fields&section=all" class="btn btn-sm btn-<?= $section_filter === 'all' ? 'primary' : 'secondary' ?>">All Sections</a>
            <?php foreach ($sections as $key => $label): ?>
                <a href="customizer.php?tab=fields&section=<?= $key ?>" class="btn btn-sm btn-<?= $section_filter === $key ? 'primary' : 'secondary' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <button onclick="document.getElementById('addFieldModal').style.display='flex'" class="btn btn-primary">+ Add Custom Field</button>
    </div>

    <!-- Fields Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>CRM Section</th>
                        <th>Field Label</th>
                        <th>Internal Key</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Visibility</th>
                        <th>Sort</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fields)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; color:var(--text-muted); padding:30px;">
                                No custom fields configured for this section yet. Click "+ Add Custom Field" above.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fields as $f): ?>
                            <tr>
                                <td><span class="badge badge-info"><?= e($sections[$f['entity_type']] ?? ucfirst($f['entity_type'])) ?></span></td>
                                <td><strong><?= e($f['field_label']) ?></strong></td>
                                <td><code><?= e($f['field_name']) ?></code></td>
                                <td><span class="badge badge-secondary"><?= e($field_types[$f['field_type']] ?? $f['field_type']) ?></span></td>
                                <td>
                                    <span class="badge badge-<?= $f['is_required'] ? 'danger' : 'secondary' ?>">
                                        <?= $f['is_required'] ? 'Required' : 'Optional' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $f['is_visible'] ? 'success' : 'warning' ?>">
                                        <?= $f['is_visible'] ? 'Visible' : 'Hidden' ?>
                                    </span>
                                </td>
                                <td><?= $f['sort_order'] ?></td>
                                <td>
                                    <form action="customizer.php" method="POST" style="display:inline;" onsubmit="return confirm('Remove this custom field?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_field">
                                        <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Field Modal -->
    <div class="modal-overlay" id="addFieldModal" style="display:none;">
        <div class="modal-card" style="max-width:600px;">
            <div class="modal-header">
                <span style="font-weight:700; color:#1e293b;">🎛️ Add Custom Field to CRM Section</span>
                <button onclick="document.getElementById('addFieldModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
            </div>

            <form action="customizer.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_field">

                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-col form-group">
                            <label class="form-label">CRM Section *</label>
                            <select name="entity_type" class="form-control" required>
                                <?php foreach ($sections as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $section_filter === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-col form-group">
                            <label class="form-label">Field Type *</label>
                            <select name="field_type" class="form-control" id="fieldTypeSelect" onchange="toggleDropdownOptions(this.value)" required>
                                <?php foreach ($field_types as $val => $label): ?>
                                    <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col form-group">
                            <label class="form-label">Field Label (Visible to Users) *</label>
                            <input type="text" name="field_label" class="form-control" placeholder="e.g. Immigration Case Number" required>
                        </div>
                        <div class="form-col form-group">
                            <label class="form-label">Internal Field Key *</label>
                            <input type="text" name="field_name" class="form-control" placeholder="e.g. case_number" required>
                        </div>
                    </div>

                    <div id="dropdownOptionsGroup" style="display:none;" class="form-group">
                        <label class="form-label">Dropdown / Choice Options (one per line)</label>
                        <textarea name="dropdown_options" class="form-control" rows="3" placeholder="Option A&#10;Option B&#10;Option C"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-col form-group">
                            <label class="form-label">Default Value</label>
                            <input type="text" name="default_value" class="form-control" placeholder="Leave blank for none">
                        </div>
                        <div class="form-col form-group">
                            <label class="form-label">Sort Position</label>
                            <input type="number" name="sort_order" class="form-control" value="99" min="1">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col form-group">
                            <label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" name="is_required" value="1"> <strong>Mandatory / Required</strong>
                            </label>
                        </div>
                        <div class="form-col form-group">
                            <label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" name="is_visible" value="1" checked> <strong>Visible in Views &amp; Forms</strong>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" onclick="document.getElementById('addFieldModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Field to Section</button>
                </div>
            </form>
        </div>
    </div>

<!-- TAB 3: THEMES & LAYOUTS -->
<?php elseif ($active_tab === 'themes'): ?>
    <div class="card">
        <div class="card-title">🎨 Global CRM Visual Theme Engine</div>
        <p style="color:var(--text-muted); font-size:13px; margin-bottom:20px;">
            Select a real-time visual stylesheet for the entire CRM. Changes reflect across all screens and user sessions immediately.
        </p>

        <form action="customizer.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_theme">

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:16px; margin-bottom:24px;">
                <!-- Modern -->
                <label style="border:2px solid <?= $current_theme === 'modern' ? 'var(--primary)' : 'var(--border-color)' ?>; padding:16px; border-radius:10px; cursor:pointer; background:#ffffff; display:block;">
                    <input type="radio" name="app_theme" value="modern" <?= $current_theme === 'modern' ? 'checked' : '' ?>>
                    <strong style="margin-left:6px; font-size:14px;">🌟 Modern</strong>
                    <div style="font-size:12px; color:var(--text-muted); margin-top:6px;">Clean, crisp Royal Blue &amp; Slate dashboard design.</div>
                </label>

                <!-- Advanced CSS -->
                <label style="border:2px solid <?= $current_theme === 'advanced' ? 'var(--primary)' : 'var(--border-color)' ?>; padding:16px; border-radius:10px; cursor:pointer; background:#ffffff; display:block;">
                    <input type="radio" name="app_theme" value="advanced" <?= $current_theme === 'advanced' ? 'checked' : '' ?>>
                    <strong style="margin-left:6px; font-size:14px;">⚡ Advanced CSS</strong>
                    <div style="font-size:12px; color:var(--text-muted); margin-top:6px;">Futuristic Violet, Cyan accents &amp; sleek card styling.</div>
                </label>

                <!-- Bold -->
                <label style="border:2px solid <?= $current_theme === 'bold' ? 'var(--primary)' : 'var(--border-color)' ?>; padding:16px; border-radius:10px; cursor:pointer; background:#ffffff; display:block;">
                    <input type="radio" name="app_theme" value="bold" <?= $current_theme === 'bold' ? 'checked' : '' ?>>
                    <strong style="margin-left:6px; font-size:14px;">💥 Bold</strong>
                    <div style="font-size:12px; color:var(--text-muted); margin-top:6px;">High-contrast typography with strong visual weight.</div>
                </label>

                <!-- Basic -->
                <label style="border:2px solid <?= $current_theme === 'basic' ? 'var(--primary)' : 'var(--border-color)' ?>; padding:16px; border-radius:10px; cursor:pointer; background:#ffffff; display:block;">
                    <input type="radio" name="app_theme" value="basic" <?= $current_theme === 'basic' ? 'checked' : '' ?>>
                    <strong style="margin-left:6px; font-size:14px;">📄 Basic</strong>
                    <div style="font-size:12px; color:var(--text-muted); margin-top:6px;">Minimalist, monochrome, ultra-fast clean gray styling.</div>
                </label>

                <!-- Pure Black -->
                <label style="border:2px solid <?= $current_theme === 'black' ? 'var(--primary)' : 'var(--border-color)' ?>; padding:16px; border-radius:10px; cursor:pointer; background:#0a0a0a; color:#fff; display:block;">
                    <input type="radio" name="app_theme" value="black" <?= $current_theme === 'black' ? 'checked' : '' ?>>
                    <strong style="margin-left:6px; font-size:14px; color:#fff;">🖤 Pure Black (AMOLED)</strong>
                    <div style="font-size:12px; color:#a1a1aa; margin-top:6px;">Deep true-black palette engineered for OLED screens.</div>
                </label>

                <!-- Night Eye -->
                <label style="border:2px solid <?= $current_theme === 'night_eye' ? 'var(--primary)' : 'var(--border-color)' ?>; padding:16px; border-radius:10px; cursor:pointer; background:#1e222d; color:#fff; display:block;">
                    <input type="radio" name="app_theme" value="night_eye" <?= $current_theme === 'night_eye' ? 'checked' : '' ?>>
                    <strong style="margin-left:6px; font-size:14px; color:#fff;">🌙 Night Eye (Dark)</strong>
                    <div style="font-size:12px; color:#9ca3af; margin-top:6px;">Blue-light reduction dark mode for comfortable evening work.</div>
                </label>

                <!-- User Friendly -->
                <label style="border:2px solid <?= $current_theme === 'user_friendly' ? 'var(--primary)' : 'var(--border-color)' ?>; padding:16px; border-radius:10px; cursor:pointer; background:#f0fdfa; display:block;">
                    <input type="radio" name="app_theme" value="user_friendly" <?= $current_theme === 'user_friendly' ? 'checked' : '' ?>>
                    <strong style="margin-left:6px; font-size:14px; color:#0f766e;">🍃 User-Friendly</strong>
                    <div style="font-size:12px; color:#134e4a; margin-top:6px;">Calming teal &amp; warm emerald palette for everyday staff comfort.</div>
                </label>

                <!-- Navy -->
                <label style="border:2px solid <?= $current_theme === 'navy' ? 'var(--primary)' : 'var(--border-color)' ?>; padding:16px; border-radius:10px; cursor:pointer; background:#ffffff; display:block;">
                    <input type="radio" name="app_theme" value="navy" <?= $current_theme === 'navy' ? 'checked' : '' ?>>
                    <strong style="margin-left:6px; font-size:14px;">⚓ Navy Blue</strong>
                    <div style="font-size:12px; color:var(--text-muted); margin-top:6px;">Corporate deep navy styling for financial institutions.</div>
                </label>
            </div>

            <div style="text-align:right;">
                <button type="submit" class="btn btn-primary" style="padding:10px 24px;">Apply Selected Theme</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<script>
function toggleDropdownOptions(type) {
    const g = document.getElementById('dropdownOptionsGroup');
    if (g) {
        g.style.display = (type === 'dropdown' || type === 'multiselect') ? 'block' : 'none';
    }
}
</script>

<?php render_footer(); ?>
