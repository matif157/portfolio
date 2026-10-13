<?php
/**
 * GRAVITY CRM - Themes & Layouts
 * Phase 1: Foundation
 * 
 * Yahan administrator themes activate kar sakta hai.
 * Role-specific layouts bhi configure kiye ja sakte hain.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('themes', 'view');

$user = current_user();
$page_title = 'Themes & Layouts';
$active_page = 'themes';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'activate_theme') {
        require_permission('themes', 'edit');
        $theme_id = (int)post('theme_id', 0);
        
        $theme = db_row('SELECT * FROM themes WHERE id = ?', [$theme_id]);
        if (!$theme) {
            flash('error', 'Theme not found.');
            redirect('themes.php');
        }
        
        // Deactivate all, activate selected
        db_execute('UPDATE themes SET is_active = 0');
        db_execute('UPDATE themes SET is_active = 1 WHERE id = ?', [$theme_id]);
        
        log_activity('themes', 'update', $theme_id, 'Activated theme: ' . $theme['name']);
        flash('success', 'Theme activated successfully.');
        redirect('themes.php');
    }
    
    if ($action === 'update_role_layout') {
        require_permission('themes', 'edit');
        $role_id = (int)post('role_id', 0);
        $theme_id = (int)post('theme_id', 0);
        $layout_key = sanitize(post('layout_key'));
        
        $existing = db_row('SELECT * FROM role_layouts WHERE role_id = ?', [$role_id]);
        if ($existing) {
            db_execute('UPDATE role_layouts SET theme_id = ?, layout_key = ?, updated_by = ? WHERE role_id = ?', [$theme_id ?: null, $layout_key, $user['id'], $role_id]);
        } else {
            db_execute('INSERT INTO role_layouts (role_id, theme_id, layout_key, updated_by) VALUES (?, ?, ?, ?)', [$role_id, $theme_id ?: null, $layout_key, $user['id']]);
        }
        
        log_activity('themes', 'update', $role_id, 'Updated role layout for role ID: ' . $role_id);
        flash('success', 'Role layout updated successfully.');
        redirect('themes.php');
    }
}

// Get all themes
$themes = db_query('SELECT * FROM themes ORDER BY id');

// Get roles and their layouts
$roles = db_query(
    'SELECT r.*, rl.theme_id, rl.layout_key, t.name AS theme_name 
     FROM roles r 
     LEFT JOIN role_layouts rl ON r.id = rl.role_id 
     LEFT JOIN themes t ON rl.theme_id = t.id 
     ORDER BY r.id'
);

// Available layout keys
$layout_keys = ['default', 'immigration', 'ar', 'bookkeeper', 'ceo', 'frontdesk', 'taxfiler', 'manager', 'staff', 'viewer'];
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-palette"></i> Themes & Layouts</h1>
</div>

<!-- Themes -->
<div class="card">
    <div class="card-header">Available Themes</div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($themes as $theme): ?>
                <div class="col-md-4 mb-3">
                    <div class="card" style="border:<?= $theme['is_active'] ? '2px solid var(--primary)' : '1px solid #e5e7eb' ?>;">
                        <div class="card-body">
                            <?php
                            $css_vars = $theme['css_variables'] ? json_decode($theme['css_variables'], true) : [];
                            $primary_color = $css_vars['--primary'] ?? '#2563eb';
                            $sidebar_color = $css_vars['--sidebar-bg'] ?? '#1e293b';
                            ?>
                            <div style="height:60px;border-radius:8px;background:linear-gradient(135deg, <?= e($primary_color) ?>, <?= e($sidebar_color) ?>);margin-bottom:1rem;"></div>
                            <h5><?= e($theme['name']) ?></h5>
                            <p class="text-muted" style="font-size:0.85rem;"><?= e($theme['description']) ?></p>
                            <?php if ($theme['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="activate_theme">
                                    <input type="hidden" name="theme_id" value="<?= $theme['id'] ?>">
                                    <button type="submit" class="btn-primary-custom btn-sm"><i class="bi bi-check"></i> Activate</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Role Layouts -->
<div class="card">
    <div class="card-header">Role-Specific Layouts</div>
    <div class="card-body">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Theme</th>
                    <th>Layout</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><strong><?= e($role['name']) ?></strong></td>
                        <td><?= e($role['theme_name'] ?? 'Default') ?></td>
                        <td><?= e(ucfirst($role['layout_key'] ?? 'default')) ?></td>
                        <td>
                            <button class="btn-secondary-custom btn-sm" onclick="toggleRoleLayout(<?= $role['id'] ?>)"><i class="bi bi-pencil"></i></button>
                        </td>
                    </tr>
                    <tr id="roleLayout_<?= $role['id'] ?>" style="display:none;">
                        <td colspan="4">
                            <form method="POST" action="" class="row g-2">
                                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update_role_layout">
                                <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                                <div class="col-md-4">
                                    <select name="theme_id" class="form-control-custom">
                                        <option value="0">Default Theme</option>
                                        <?php foreach ($themes as $theme): ?>
                                            <option value="<?= $theme['id'] ?>" <?= ($role['theme_id'] ?? 0) == $theme['id'] ? 'selected' : '' ?>>
                                                <?= e($theme['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select name="layout_key" class="form-control-custom">
                                        <?php foreach ($layout_keys as $key): ?>
                                            <option value="<?= $key ?>" <?= ($role['layout_key'] ?? '') === $key ? 'selected' : '' ?>>
                                                <?= e(ucfirst($key)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
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

<script>
function toggleRoleLayout(id) {
    const row = document.getElementById('roleLayout_' + id);
    row.style.display = row.style.display === 'none' ? '' : 'none';
}
</script>

<?php include __DIR__ . '/footer.php'; ?>