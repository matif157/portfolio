<?php
/**
 * GRAVITY CRM - Personal Templates
 * Phase 1: Foundation
 * 
 * Yahan users apne personal templates create kar sakte hain:
 * - Word files
 * - Excel files
 * - Notepad/text templates
 * - Similar supported template types
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('templates', 'view');

$user = current_user();
$page_title = 'Personal Templates';
$active_page = 'templates';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create' || $action === 'update') {
        require_permission('templates', 'create');
        
        $template_id = (int)post('template_id', 0);
        $name = sanitize(post('name'));
        $template_type = sanitize(post('template_type', 'text'));
        $content = post('content');
        $is_shared = post('is_shared') ? 1 : 0;
        
        $errors = [];
        validate_required($name, 'Template name', $errors);
        
        if (empty($errors)) {
            if ($action === 'create') {
                db_execute(
                    'INSERT INTO user_templates (user_id, name, template_type, content, is_shared) VALUES (?, ?, ?, ?, ?)',
                    [$user['id'], $name, $template_type, $content, $is_shared]
                );
                $template_id = db_last_id();
                log_activity('templates', 'create', $template_id, 'Created template: ' . $name);
                flash('success', 'Template created successfully.');
            } else {
                // Verify ownership
                $existing = db_row('SELECT * FROM user_templates WHERE id = ? AND user_id = ?', [$template_id, $user['id']]);
                if (!$existing) {
                    flash('error', 'Template not found or you do not have permission.');
                    redirect('templates.php');
                }
                db_execute(
                    'UPDATE user_templates SET name = ?, template_type = ?, content = ?, is_shared = ? WHERE id = ? AND user_id = ?',
                    [$name, $template_type, $content, $is_shared, $template_id, $user['id']]
                );
                log_activity('templates', 'update', $template_id, 'Updated template: ' . $name);
                flash('success', 'Template updated successfully.');
            }
            redirect('templates.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete') {
        require_permission('templates', 'delete');
        $template_id = (int)post('template_id', 0);
        
        $existing = db_row('SELECT * FROM user_templates WHERE id = ? AND user_id = ?', [$template_id, $user['id']]);
        if ($existing) {
            db_execute('DELETE FROM user_templates WHERE id = ? AND user_id = ?', [$template_id, $user['id']]);
            log_activity('templates', 'delete', $template_id, 'Deleted template: ' . $existing['name']);
            flash('success', 'Template deleted successfully.');
        } else {
            flash('error', 'Template not found or you do not have permission.');
        }
        redirect('templates.php');
    }
    
    if ($action === 'download') {
        $template_id = (int)post('template_id', 0);
        $template = db_row('SELECT * FROM user_templates WHERE id = ? AND (user_id = ? OR is_shared = 1)', [$template_id, $user['id']]);
        
        if ($template) {
            $ext = 'txt';
            $mime = 'text/plain';
            switch ($template['template_type']) {
                case 'word':
                    $ext = 'doc';
                    $mime = 'application/msword';
                    break;
                case 'excel':
                    $ext = 'xls';
                    $mime = 'application/vnd.ms-excel';
                    break;
                case 'pdf':
                    $ext = 'pdf';
                    $mime = 'application/pdf';
                    break;
            }
            
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $template['name']) . '.' . $ext . '"');
            echo $template['content'];
            exit;
        }
    }
}

// Get templates
$my_templates = db_query('SELECT * FROM user_templates WHERE user_id = ? ORDER BY updated_at DESC', [$user['id']]);
$shared_templates = db_query('SELECT t.*, u.first_name, u.last_name FROM user_templates t JOIN users u ON t.user_id = u.id WHERE t.is_shared = 1 AND t.user_id != ? ORDER BY t.updated_at DESC', [$user['id']]);

// Edit template
$edit_template = null;
if (get('edit')) {
    $edit_template = db_row('SELECT * FROM user_templates WHERE id = ? AND user_id = ?', [(int)get('edit'), $user['id']]);
}
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-file-earmark-text"></i> Personal Templates</h1>
    <button class="btn-primary-custom" onclick="toggleNewTemplate()"><i class="bi bi-plus"></i> New Template</button>
</div>

<!-- New Template Form -->
<div class="card" id="newTemplateForm" style="display:<?= $edit_template ? 'block' : 'none' ?>;">
    <div class="card-header">
        <?= $edit_template ? 'Edit Template' : 'Create New Template' ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $edit_template ? 'update' : 'create' ?>">
            <input type="hidden" name="template_id" value="<?= $edit_template['id'] ?? 0 ?>">
            
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label-custom">Template Name *</label>
                    <input type="text" name="name" class="form-control-custom" value="<?= e($edit_template['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">Template Type</label>
                    <select name="template_type" class="form-control-custom">
                        <option value="text" <?= ($edit_template['template_type'] ?? '') === 'text' ? 'selected' : '' ?>>Text / Notepad</option>
                        <option value="word" <?= ($edit_template['template_type'] ?? '') === 'word' ? 'selected' : '' ?>>Word</option>
                        <option value="excel" <?= ($edit_template['template_type'] ?? '') === 'excel' ? 'selected' : '' ?>>Excel</option>
                        <option value="pdf" <?= ($edit_template['template_type'] ?? '') === 'pdf' ? 'selected' : '' ?>>PDF</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">Share</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="is_shared" value="1" class="form-check-input" id="isShared" <?= ($edit_template['is_shared'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isShared">Share with team</label>
                    </div>
                </div>
            </div>
            
            <label class="form-label-custom">Template Content</label>
            <textarea name="content" class="form-control-custom" rows="12" style="font-family:monospace;" placeholder="Enter your template content here..."><?= e($edit_template['content'] ?? '') ?></textarea>
            
            <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> <?= $edit_template ? 'Update' : 'Create' ?> Template</button>
            <button type="button" class="btn-secondary-custom" onclick="toggleNewTemplate()">Cancel</button>
        </form>
    </div>
</div>

<!-- My Templates -->
<div class="card">
    <div class="card-header">My Templates (<?= count($my_templates) ?>)</div>
    <div class="card-body">
        <?php if (empty($my_templates)): ?>
            <p class="text-muted">No templates yet. Click "New Template" to create one.</p>
        <?php else: ?>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Shared</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_templates as $template): ?>
                        <tr>
                            <td><strong><?= e($template['name']) ?></strong></td>
                            <td>
                                <?php
                                $type_icons = ['text' => 'bi-file-text', 'word' => 'bi-file-word', 'excel' => 'bi-file-excel', 'pdf' => 'bi-file-pdf'];
                                ?>
                                <i class="bi <?= e($type_icons[$template['template_type']] ?? 'bi-file-text') ?>"></i>
                                <?= e(ucfirst($template['template_type'])) ?>
                            </td>
                            <td><?= $template['is_shared'] ? '<span class="badge badge-success">Shared</span>' : '<span class="badge badge-secondary">Private</span>' ?></td>
                            <td><?= e(time_ago($template['updated_at'])) ?></td>
                            <td>
                                <a href="templates.php?edit=<?= $template['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this template?')">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                    <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="download">
                                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                    <button type="submit" class="btn-primary-custom btn-sm"><i class="bi bi-download"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Shared Templates -->
<div class="card">
    <div class="card-header">Shared Templates (<?= count($shared_templates) ?>)</div>
    <div class="card-body">
        <?php if (empty($shared_templates)): ?>
            <p class="text-muted">No shared templates available.</p>
        <?php else: ?>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Created By</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shared_templates as $template): ?>
                        <tr>
                            <td><strong><?= e($template['name']) ?></strong></td>
                            <td><i class="bi bi-file-text"></i> <?= e(ucfirst($template['template_type'])) ?></td>
                            <td><?= e($template['first_name'] . ' ' . $template['last_name']) ?></td>
                            <td><?= e(time_ago($template['updated_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="download">
                                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                    <button type="submit" class="btn-primary-custom btn-sm"><i class="bi bi-download"></i></button>
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
function toggleNewTemplate() {
    const form = document.getElementById('newTemplateForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
        // Clear edit mode if opening fresh
        if (!form.querySelector('input[name="template_id"]').value) {
            form.querySelector('input[name="name"]').value = '';
            form.querySelector('textarea').value = '';
        }
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>