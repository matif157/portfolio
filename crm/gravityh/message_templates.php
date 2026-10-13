<?php
/**
 * GRAVITY CRM - Message Templates
 * Phase 3: Service Automation & Workflow Engine
 * 
 * Yahan email/SMS/WhatsApp/notification templates manage hote hain.
 * Templates workflow automation mein use hote hain.
 */

require_once __DIR__ . '/functions.php';
require_login();

$user = current_user();
$page_title = 'Message Templates';
$active_page = 'templates';

// Permission check
if (!user_can('templates', 'message_view') && !user_can('templates', 'view')) {
    http_response_code(403);
    die('Access denied. You do not have permission to access this resource.');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create' || $action === 'update') {
        $can_edit = user_can('templates', 'message_edit') || user_can('templates', 'edit');
        if (!$can_edit) {
            http_response_code(403);
            die('Access denied.');
        }
        
        $template_id = (int)post('template_id', 0);
        $name = sanitize(post('name'));
        $template_type = sanitize(post('template_type', 'email'));
        $subject = sanitize(post('subject'));
        $body = post('body', ''); // Keep as-is, may contain line breaks
        $is_active = post('is_active') ? 1 : 0;
        
        $errors = [];
        validate_required($name, 'Template name', $errors);
        validate_required($body, 'Template body', $errors);
        
        if (empty($errors)) {
            if ($action === 'create') {
                db_execute(
                    'INSERT INTO message_templates (name, template_type, subject, body, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?)',
                    [$name, $template_type, $subject ?: null, $body, $is_active, $user['id']]
                );
                log_activity('templates', 'create', db_last_id(), 'Created message template: ' . $name);
                flash('success', 'Message template created successfully.');
            } else {
                $existing = db_row('SELECT * FROM message_templates WHERE id = ?', [$template_id]);
                if (!$existing) {
                    flash('error', 'Template not found.');
                    redirect('message_templates.php');
                }
                
                db_execute(
                    'UPDATE message_templates SET name = ?, template_type = ?, subject = ?, body = ?, is_active = ? WHERE id = ?',
                    [$name, $template_type, $subject ?: null, $body, $is_active, $template_id]
                );
                log_activity('templates', 'message_update', $template_id, 'Updated message template: ' . $name);
                flash('success', 'Message template updated successfully.');
            }
            redirect('message_templates.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete') {
        $can_edit = user_can('templates', 'message_edit') || user_can('templates', 'edit');
        if (!$can_edit) {
            http_response_code(403);
            die('Access denied.');
        }
        
        $template_id = (int)post('template_id', 0);
        $existing = db_row('SELECT * FROM message_templates WHERE id = ?', [$template_id]);
        if (!$existing) {
            flash('error', 'Template not found.');
            redirect('message_templates.php');
        }
        
        db_execute('DELETE FROM message_templates WHERE id = ?', [$template_id]);
        log_activity('templates', 'message_delete', $template_id, 'Deleted message template: ' . $existing['name']);
        flash('success', 'Message template deleted successfully.');
        redirect('message_templates.php');
    }
}

// Filters
$type_filter = get('type', '');
$search = trim(get('search', ''));

$where = '1=1';
$params = [];
if ($type_filter !== '') {
    $where .= ' AND template_type = ?';
    $params[] = $type_filter;
}
if ($search !== '') {
    $where .= ' AND (name LIKE ? OR subject LIKE ? OR body LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$templates = db_query(
    "SELECT mt.*, u.first_name AS created_first, u.last_name AS created_last 
     FROM message_templates mt 
     LEFT JOIN users u ON mt.created_by = u.id 
     WHERE {$where} 
     ORDER BY mt.created_at DESC",
    $params
);

$counts = [
    'email' => (int)db_value('SELECT COUNT(*) FROM message_templates WHERE template_type = "email"'),
    'sms' => (int)db_value('SELECT COUNT(*) FROM message_templates WHERE template_type = "sms"'),
    'whatsapp' => (int)db_value('SELECT COUNT(*) FROM message_templates WHERE template_type = "whatsapp"'),
    'notification' => (int)db_value('SELECT COUNT(*) FROM message_templates WHERE template_type = "notification"'),
];

// Edit template
$edit_template = null;
if (get('edit')) {
    $edit_template = db_row('SELECT * FROM message_templates WHERE id = ?', [(int)get('edit')]);
}

$can_edit = user_can('templates', 'message_edit') || user_can('templates', 'edit');
$can_view = user_can('templates', 'message_view') || user_can('templates', 'view');
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-envelope-paper"></i> Message Templates</h1>
    <?php if ($can_edit): ?>
        <button class="btn-primary-custom" onclick="toggleNewTemplate()"><i class="bi bi-plus"></i> New Template</button>
    <?php endif; ?>
</div>

<!-- Templates by type -->
<div class="row mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-value"><?= $counts['email'] ?></div><div class="stat-label">Email Templates</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-value"><?= $counts['sms'] ?></div><div class="stat-label">SMS Templates</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-value"><?= $counts['whatsapp'] ?></div><div class="stat-label">WhatsApp Templates</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-value"><?= $counts['notification'] ?></div><div class="stat-label">Notification Templates</div></div></div>
</div>

<!-- Create/Edit Template Form -->
<div class="card" id="templateForm" style="display:<?= $edit_template ? 'block' : 'none' ?>;">
    <div class="card-header"><?= $edit_template ? 'Edit Template: ' . e($edit_template['name']) : 'Create New Template' ?></div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $edit_template ? 'update' : 'create' ?>">
            <input type="hidden" name="template_id" value="<?= $edit_template['id'] ?? 0 ?>">
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Template Name *</label>
                    <input type="text" name="name" class="form-control-custom" value="<?= e($edit_template['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Type</label>
                    <select name="template_type" class="form-control-custom">
                        <option value="email" <?= ($edit_template['template_type'] ?? '') === 'email' ? 'selected' : '' ?>>Email</option>
                        <option value="sms" <?= ($edit_template['template_type'] ?? '') === 'sms' ? 'selected' : '' ?>>SMS</option>
                        <option value="whatsapp" <?= ($edit_template['template_type'] ?? '') === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                        <option value="notification" <?= ($edit_template['template_type'] ?? '') === 'notification' ? 'selected' : '' ?>>Notification</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Active</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" <?= ($edit_template['is_active'] ?? 1) ? 'checked' : '' ?>>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <label class="form-label-custom">Subject (Email only)</label>
                    <input type="text" name="subject" class="form-control-custom" value="<?= e($edit_template['subject'] ?? '') ?>" placeholder="e.g. Welcome to {app_name}">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <label class="form-label-custom">Body *</label>
                    <textarea name="body" class="form-control-custom" rows="8" required><?= e($edit_template['body'] ?? '') ?></textarea>
                    <small class="text-muted">Available variables: {client_first_name}, {client_last_name}, {app_name}, {company_name}, {invoice_number}, {invoice_total}, {invoice_due_date}, {project_name}, {project_status}, {task_title}, {documents_needed}</small>
                </div>
            </div>
            
            <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> <?= $edit_template ? 'Update' : 'Create' ?> Template</button>
            <button type="button" class="btn-secondary-custom" onclick="toggleNewTemplate()">Cancel</button>
        </form>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control-custom" placeholder="Search templates..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-control-custom">
                    <option value="">All Types</option>
                    <option value="email" <?= $type_filter === 'email' ? 'selected' : '' ?>>Email</option>
                    <option value="sms" <?= $type_filter === 'sms' ? 'selected' : '' ?>>SMS</option>
                    <option value="whatsapp" <?= $type_filter === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                    <option value="notification" <?= $type_filter === 'notification' ? 'selected' : '' ?>>Notification</option>
                </select>
            </div>
            <div class="col-md-5">
                <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Filter</button>
                <a href="message_templates.php" class="btn-secondary-custom">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Templates List -->
<div class="card">
    <div class="card-header">Templates (<?= count($templates) ?>)</div>
    <div class="card-body">
        <?php if (empty($templates)): ?>
            <p class="text-muted">No message templates found.</p>
        <?php else: ?>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Body (preview)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $t): ?>
                        <tr>
                            <td><strong><?= e($t['name']) ?></strong></td>
                            <td><span class="badge badge-info"><?= e(ucfirst($t['template_type'])) ?></span></td>
                            <td><?= e($t['subject'] ?: '-') ?></td>
                            <td><?= e(truncate($t['body'], 80)) ?></td>
                            <td><?= $t['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                            <td>
                                <?php if ($can_edit): ?>
                                    <a href="message_templates.php?edit=<?= $t['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this template?')">
                                        <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
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
    const form = document.getElementById('templateForm');
    if (form) {
        // If closing and no edit template active, clear form
        if (form.style.display === 'block' && document.querySelector('input[name="template_id"]').value === '0') {
            form.querySelectorAll('input[type="text"], textarea').forEach(i => i.value = '');
        }
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>