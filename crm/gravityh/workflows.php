<?php
/**
 * GRAVITY CRM - Workflow Engine
 * Phase 3: Service Automation & Workflow Engine
 * 
 * Yahan workflow templates create/edit/delete hote hain.
 * Workflow instances start/pause/complete hote hain clients ke liye.
 * Step-by-step progress tracking hota hai.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('workflows', 'view');

$user = current_user();
$page_title = 'Workflows';
$active_page = 'workflows';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create_workflow' || $action === 'update_workflow') {
        require_permission('workflows', 'create');
        
        $workflow_id = (int)post('workflow_id', 0);
        $name = sanitize(post('name'));
        $description = sanitize(post('description'));
        $workflow_key = sanitize(post('workflow_key'));
        $category = sanitize(post('category'));
        $is_active = post('is_active') ? 1 : 0;
        
        $errors = [];
        validate_required($name, 'Workflow name', $errors);
        validate_required($workflow_key, 'Workflow key', $errors);
        
        if (empty($errors)) {
            if ($action === 'create_workflow') {
                $existing = db_row('SELECT id FROM workflow_templates WHERE workflow_key = ?', [$workflow_key]);
                if ($existing) {
                    flash('error', 'Workflow key already exists. Please use a unique key.');
                    redirect('workflows.php');
                }
                
                db_execute(
                    'INSERT INTO workflow_templates (name, description, workflow_key, category, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?)',
                    [$name, $description, $workflow_key, $category, $is_active, $user['id']]
                );
                $workflow_id = db_last_id();
                log_activity('workflows', 'create', $workflow_id, 'Created workflow: ' . $name);
                flash('success', 'Workflow created successfully.');
            } else {
                $existing = db_row('SELECT * FROM workflow_templates WHERE id = ?', [$workflow_id]);
                if (!$existing) {
                    flash('error', 'Workflow not found.');
                    redirect('workflows.php');
                }
                
                $dup = db_row('SELECT id FROM workflow_templates WHERE workflow_key = ? AND id != ?', [$workflow_key, $workflow_id]);
                if ($dup) {
                    flash('error', 'Workflow key already exists. Please use a unique key.');
                    redirect('workflows.php');
                }
                
                db_execute(
                    'UPDATE workflow_templates SET name = ?, description = ?, workflow_key = ?, category = ?, is_active = ? WHERE id = ?',
                    [$name, $description, $workflow_key, $category, $is_active, $workflow_id]
                );
                log_activity('workflows', 'update', $workflow_id, 'Updated workflow: ' . $name);
                flash('success', 'Workflow updated successfully.');
            }
            redirect('workflows.php?view=' . $workflow_id);
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete_workflow') {
        require_permission('workflows', 'delete');
        $workflow_id = (int)post('workflow_id', 0);
        
        $existing = db_row('SELECT * FROM workflow_templates WHERE id = ?', [$workflow_id]);
        if (!$existing) {
            flash('error', 'Workflow not found.');
            redirect('workflows.php');
        }
        
        $running = (int)db_value('SELECT COUNT(*) FROM workflow_instances WHERE workflow_id = ? AND status IN ("not_started","in_progress","waiting")', [$workflow_id]);
        if ($running > 0) {
            flash('error', 'Cannot delete workflow with running instances. Cancel instances first.');
            redirect('workflows.php');
        }
        
        db_execute('DELETE FROM workflow_templates WHERE id = ?', [$workflow_id]);
        log_activity('workflows', 'delete', $workflow_id, 'Deleted workflow: ' . $existing['name']);
        flash('success', 'Workflow deleted successfully.');
        redirect('workflows.php');
    }
    
    if ($action === 'add_step') {
        require_permission('workflows', 'edit');
        $workflow_id = (int)post('workflow_id', 0);
        $step_name = sanitize(post('step_name'));
        $step_type = sanitize(post('step_type', 'task'));
        $assigned_role_id = (int)post('assigned_role_id', 0);
        $assigned_user_id = (int)post('assigned_user_id', 0);
        $is_required = post('is_required') ? 1 : 0;
        $step_order = (int)post('step_order', 0);
        
        $errors = [];
        validate_required($step_name, 'Step name', $errors);
        
        if (empty($errors)) {
            db_execute(
                'INSERT INTO workflow_steps (workflow_id, step_order, step_name, step_type, assigned_role_id, assigned_user_id, is_required) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$workflow_id, $step_order, $step_name, $step_type, $assigned_role_id ?: null, $assigned_user_id ?: null, $is_required]
            );
            log_activity('workflows', 'update', $workflow_id, 'Added step: ' . $step_name);
            flash('success', 'Step added successfully.');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
        redirect('workflows.php?view=' . $workflow_id);
    }
    
    if ($action === 'delete_step') {
        require_permission('workflows', 'edit');
        $step_id = (int)post('step_id', 0);
        $workflow_id = (int)post('workflow_id', 0);
        
        db_execute('DELETE FROM workflow_steps WHERE id = ? AND workflow_id = ?', [$step_id, $workflow_id]);
        log_activity('workflows', 'update', $workflow_id, 'Deleted workflow step');
        flash('success', 'Step deleted successfully.');
        redirect('workflows.php?view=' . $workflow_id);
    }
    
    if ($action === 'start_workflow') {
        require_permission('workflows', 'start');
        $workflow_id = (int)post('workflow_id', 0);
        $client_id = (int)post('client_id', 0);
        $project_id = (int)post('project_id', 0);
        
        if ($client_id <= 0) {
            flash('error', 'Please select a client to start the workflow.');
            redirect('workflows.php');
        }
        
        $workflow = db_row('SELECT * FROM workflow_templates WHERE id = ? AND is_active = 1', [$workflow_id]);
        if (!$workflow) {
            flash('error', 'Workflow not found or inactive.');
            redirect('workflows.php');
        }
        
        // Create workflow instance
        db_execute(
            'INSERT INTO workflow_instances (workflow_id, client_id, project_id, status, progress_value, started_by) VALUES (?, ?, ?, "not_started", 0, ?)',
            [$workflow_id, $client_id, $project_id ?: null, $user['id']]
        );
        $instance_id = db_last_id();
        
        // Create step instances
        $steps = db_query('SELECT * FROM workflow_steps WHERE workflow_id = ? AND is_active = 1 ORDER BY step_order', [$workflow_id]);
        $first_assigned_user = null;
        foreach ($steps as $i => $step) {
            $assigned_user = null;
            if ($step['assigned_user_id']) {
                $assigned_user = $step['assigned_user_id'];
            } elseif ($step['assigned_role_id']) {
                $role_user = db_row('SELECT id FROM users WHERE role_id = ? AND status = "active" ORDER BY id LIMIT 1', [$step['assigned_role_id']]);
                $assigned_user = $role_user['id'] ?? null;
            }
            if ($i === 0) {
                $first_assigned_user = $assigned_user;
            }
            
            $status = ($i === 0) ? 'pending' : 'pending';
            db_execute(
                'INSERT INTO workflow_step_instances (workflow_instance_id, step_id, step_order, step_type, title, status, assigned_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$instance_id, $step['id'], $step['step_order'], $step['step_type'], $step['step_name'], $status, $assigned_user]
            );
        }
        
        // Set first step as current
        $first_step = db_row('SELECT id FROM workflow_step_instances WHERE workflow_instance_id = ? ORDER BY step_order LIMIT 1', [$instance_id]);
        if ($first_step) {
            db_execute('UPDATE workflow_instances SET current_step_id = ?, status = "in_progress", progress_value = 5 WHERE id = ?', [$first_step['id'], $instance_id]);
        }
        
        // Notify assigned user
        if ($first_assigned_user) {
            notify($first_assigned_user, 'Workflow started: ' . $workflow['name'], 'You have a new workflow task for client: ' . client_name($client_id), 'workflows', $instance_id);
        }
        
        log_activity('workflows', 'start', $instance_id, 'Started workflow: ' . $workflow['name'] . ' for client #' . $client_id);
        flash('success', 'Workflow started successfully.');
        redirect('workflows.php?instance=' . $instance_id);
    }
    
    if ($action === 'advance_step') {
        require_permission('workflows', 'start');
        $step_instance_id = (int)post('step_instance_id', 0);
        $instance_id = (int)post('instance_id', 0);
        $notes = sanitize(post('step_notes'));
        
        $step = db_row('SELECT * FROM workflow_step_instances WHERE id = ? AND workflow_instance_id = ?', [$step_instance_id, $instance_id]);
        if (!$step) {
            flash('error', 'Step not found.');
            redirect('workflows.php');
        }
        
        // Mark current step as completed
        db_execute(
            'UPDATE workflow_step_instances SET status = "completed", completed_by = ?, completed_at = NOW(), progress_value = 100, notes = ? WHERE id = ?',
            [$user['id'], $notes, $step_instance_id]
        );
        
        // Find next pending step
        $next_raw = db_query('SELECT * FROM workflow_step_instances WHERE workflow_instance_id = ? AND status = "pending" ORDER BY step_order LIMIT 1', [$instance_id]);
        $next = !empty($next_raw) ? $next_raw[0] : null;
        
        $total_steps = (int)db_value('SELECT COUNT(*) FROM workflow_step_instances WHERE workflow_instance_id = ?', [$instance_id]);
        $completed_steps = (int)db_value('SELECT COUNT(*) FROM workflow_step_instances WHERE workflow_instance_id = ? AND status = "completed"', [$instance_id]);
        $progress = (int)(($completed_steps / max(1, $total_steps)) * 100);
        
        if ($next) {
            db_execute('UPDATE workflow_step_instances SET status = "in_progress", started_at = NOW() WHERE id = ?', [$next['id']]);
            db_execute('UPDATE workflow_instances SET current_step_id = ?, status = "in_progress", progress_value = ? WHERE id = ?', [$next['id'], $progress, $instance_id]);
            
            if ($next['assigned_user_id']) {
                notify($next['assigned_user_id'], 'Workflow step assigned', $next['title'], 'workflows', $instance_id);
            }
        } else {
            db_execute('UPDATE workflow_instances SET status = "completed", completed_at = NOW(), progress_value = 100, current_step_id = NULL WHERE id = ?', [$instance_id]);
            
            $instance = db_row('SELECT wi.*, w.name AS workflow_name FROM workflow_instances wi JOIN workflow_templates w ON wi.workflow_id = w.id WHERE wi.id = ?', [$instance_id]);
            if ($instance) {
                log_activity('workflows', 'complete', $instance_id, 'Completed workflow: ' . $instance['workflow_name']);
                notify($instance['started_by'] ?? $user['id'], 'Workflow completed', 'Workflow "' . $instance['workflow_name'] . '" has been completed.', 'workflows', $instance_id);
            }
            flash('success', 'Workflow completed! All steps are done.');
        }
        
        redirect('workflows.php?instance=' . $instance_id);
    }
    
    if ($action === 'update_step_status') {
        require_permission('workflows', 'start');
        $step_instance_id = (int)post('step_instance_id', 0);
        $instance_id = (int)post('instance_id', 0);
        $status = sanitize(post('step_status', 'pending'));
        $notes = sanitize(post('step_notes'));
        
        $valid_statuses = ['pending', 'in_progress', 'blocked', 'skipped'];
        if (!in_array($status, $valid_statuses)) {
            flash('error', 'Invalid status.');
            redirect('workflows.php');
        }
        
        db_execute(
            'UPDATE workflow_step_instances SET status = ?, notes = COALESCE(?, notes) WHERE id = ? AND workflow_instance_id = ?',
            [$status, $notes ?: null, $step_instance_id, $instance_id]
        );
        
        if ($status === 'blocked') {
            db_execute('UPDATE workflow_instances SET status = "blocked" WHERE id = ?', [$instance_id]);
            flash('warning', 'Step blocked. Workflow is now blocked.');
        } elseif ($status === 'in_progress') {
            db_execute('UPDATE workflow_instances SET status = "in_progress" WHERE id = ?', [$instance_id]);
            flash('success', 'Step marked as in progress.');
        } else {
            flash('success', 'Step status updated.');
        }
        
        redirect('workflows.php?instance=' . $instance_id);
    }
    
    if ($action === 'cancel_workflow') {
        require_permission('workflows', 'start');
        $instance_id = (int)post('instance_id', 0);
        
        db_execute('UPDATE workflow_instances SET status = "cancelled" WHERE id = ?', [$instance_id]);
        db_execute('UPDATE workflow_step_instances SET status = "skipped" WHERE workflow_instance_id = ? AND status IN ("pending","in_progress")', [$instance_id]);
        
        log_activity('workflows', 'cancel', $instance_id, 'Cancelled workflow instance');
        flash('success', 'Workflow cancelled.');
        redirect('workflows.php');
    }
}

// Get all workflow templates
$workflows = db_query(
    'SELECT wt.*, 
            (SELECT COUNT(*) FROM workflow_steps WHERE workflow_id = wt.id AND is_active = 1) AS step_count,
            (SELECT COUNT(*) FROM workflow_instances WHERE workflow_id = wt.id AND status IN ("not_started","in_progress","waiting")) AS running_count,
            (SELECT COUNT(*) FROM workflow_instances WHERE workflow_id = wt.id AND status = "completed") AS completed_count
     FROM workflow_templates wt ORDER BY wt.created_at DESC'
);
$total_workflows_count = count($workflows);

// Active workflow instances (recent)
$active_instances = db_query(
    'SELECT wi.*, w.name AS workflow_name, c.first_name, c.last_name, 
            (SELECT COUNT(*) FROM workflow_step_instances WHERE workflow_instance_id = wi.id AND status = "completed") AS steps_done,
            (SELECT COUNT(*) FROM workflow_step_instances WHERE workflow_instance_id = wi.id) AS total_steps
     FROM workflow_instances wi
     JOIN workflow_templates w ON wi.workflow_id = w.id
     LEFT JOIN clients c ON wi.client_id = c.id
     WHERE wi.status IN ("not_started","in_progress","waiting","blocked")
     ORDER BY wi.updated_at DESC
     LIMIT 20'
);

// Stats
$running_instances = (int)db_value('SELECT COUNT(*) FROM workflow_instances WHERE status IN ("not_started","in_progress","waiting")');
$completed_instances = (int)db_value('SELECT COUNT(*) FROM workflow_instances WHERE status = "completed"');

// View single workflow template
$view_workflow = null;
$view_steps = [];
$view_instances = [];
$all_roles = [];
$all_users = [];
if (get('view')) {
    $view_workflow = db_row('SELECT * FROM workflow_templates WHERE id = ?', [(int)get('view')]);
    if ($view_workflow) {
        $view_steps = db_query('SELECT * FROM workflow_steps WHERE workflow_id = ? AND is_active = 1 ORDER BY step_order', [$view_workflow['id']]);
        $view_instances = db_query(
            'SELECT wi.*, c.first_name, c.last_name, p.name AS project_name,
                    (SELECT COUNT(*) FROM workflow_step_instances WHERE workflow_instance_id = wi.id AND status = "completed") AS steps_done,
                    (SELECT COUNT(*) FROM workflow_step_instances WHERE workflow_instance_id = wi.id) AS total_steps
             FROM workflow_instances wi
             LEFT JOIN clients c ON wi.client_id = c.id
             LEFT JOIN projects p ON wi.project_id = p.id
             WHERE wi.workflow_id = ?
             ORDER BY wi.created_at DESC',
            [$view_workflow['id']]
        );
        $all_roles = db_query('SELECT id, name FROM roles ORDER BY name');
        $all_users = db_query('SELECT id, first_name, last_name FROM users WHERE status = "active" ORDER BY first_name');
    }
}

// View single instance
$view_instance = null;
$instance_steps = [];
if (get('instance')) {
    $view_instance = db_row(
        'SELECT wi.*, w.name AS workflow_name, w.description AS workflow_description, c.first_name, c.last_name, c.email, c.phone, p.name AS project_name
         FROM workflow_instances wi
         JOIN workflow_templates w ON wi.workflow_id = w.id
         LEFT JOIN clients c ON wi.client_id = c.id
         LEFT JOIN projects p ON wi.project_id = p.id
         WHERE wi.id = ?',
        [(int)get('instance')]
    );
    if ($view_instance) {
        $instance_steps = db_query(
            'SELECT wsi.*, u.first_name AS assigned_first, u.last_name AS assigned_last,
                    cb.first_name AS completed_first, cb.last_name AS completed_last
             FROM workflow_step_instances wsi
             LEFT JOIN users u ON wsi.assigned_user_id = u.id
             LEFT JOIN users cb ON wsi.completed_by = cb.id
             WHERE wsi.workflow_instance_id = ?
             ORDER BY wsi.step_order',
            [$view_instance['id']]
        );
    }
}

// Client dropdown for starting workflows
$clients = db_query('SELECT id, first_name, last_name FROM clients WHERE status = "active" ORDER BY first_name');
$projects = db_query('SELECT id, name FROM projects WHERE status IN ("not_started","in_progress","on_hold") ORDER BY name');
?>
<?php include __DIR__ . '/header.php'; ?>

<?php if ($view_instance): ?>
    <!-- Workflow Instance Detail View -->
    <div class="page-title">
        <h1><i class="bi bi-diagram-3"></i> <?= e($view_instance['workflow_name']) ?></h1>
        <div>
            <a href="workflows.php" class="btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back to Workflows</a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Instance Details</div>
                <div class="card-body">
                    <p><strong>Client:</strong> <?= e(($view_instance['first_name'] ?? '') . ' ' . ($view_instance['last_name'] ?? '')) ?></p>
                    <p><strong>Project:</strong> <?= e($view_instance['project_name'] ?: 'Not linked') ?></p>
                    <p><strong>Status:</strong> <?= status_badge($view_instance['status']) ?></p>
                    <p><strong>Progress:</strong></p>
                    <div class="progress mb-2" style="height:8px;">
                        <div class="progress-bar" style="width:<?= (int)$view_instance['progress_value'] ?>%;background:var(--primary);"></div>
                    </div>
                    <p class="mb-1"><?= (int)$view_instance['progress_value'] ?>%</p>
                    <p><strong>Started:</strong> <?= e(format_datetime($view_instance['started_at'])) ?></p>
                    <p><strong>Completed:</strong> <?= e(format_datetime($view_instance['completed_at'])) ?></p>
                    <p><strong>Started By:</strong> <?= e(user_name((int)$view_instance['started_by'])) ?></p>
                    
                    <?php if ($view_instance['status'] !== 'completed' && $view_instance['status'] !== 'cancelled'): ?>
                        <form method="POST" onsubmit="return confirm('Cancel this workflow instance?')">
                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="cancel_workflow">
                            <input type="hidden" name="instance_id" value="<?= $view_instance['id'] ?>">
                            <button type="submit" class="btn-danger-custom w-100"><i class="bi bi-x-circle"></i> Cancel Workflow</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Workflow Steps (<?= count($instance_steps) ?>)</div>
                <div class="card-body">
                    <?php if (empty($instance_steps)): ?>
                        <p class="text-muted">No steps found for this workflow.</p>
                    <?php else: ?>
                        <?php foreach ($instance_steps as $i => $s): ?>
                            <div class="mb-3 p-3" style="border:1px solid #e5e7eb;border-radius:8px;background:<?= $s['status'] === 'completed' ? '#f0fdf4' : ($s['status'] === 'blocked' ? '#fef2f2' : '#ffffff') ?>;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong>Step <?= $i + 1 ?>: <?= e($s['title']) ?></strong>
                                        <span class="badge badge-<?= $s['status'] === 'completed' ? 'success' : ($s['status'] === 'blocked' ? 'danger' : ($s['status'] === 'in_progress' ? 'primary' : 'secondary')) ?> ms-2">
                                            <?= e(ucfirst(str_replace('_', ' ', $s['status']))) ?>
                                        </span>
                                        <?php if ($s['step_type']): ?>
                                            <span class="badge badge-info ms-1"><?= e(ucfirst($s['step_type'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        Assigned: <?= e(!empty($s['assigned_first']) ? $s['assigned_first'] . ' ' . $s['assigned_last'] : 'Unassigned') ?>
                                    </small>
                                </div>
                                
                                <?php if ($s['started_at']): ?>
                                    <p class="mb-1"><small>Started: <?= e(format_datetime($s['started_at'])) ?></small></p>
                                <?php endif; ?>
                                <?php if ($s['completed_at']): ?>
                                    <p class="mb-1"><small>Completed: <?= e(format_datetime($s['completed_at'])) ?></small></p>
                                <?php endif; ?>
                                <?php if ($s['notes']): ?>
                                    <p class="mb-1"><small><em><?= e($s['notes']) ?></em></small></p>
                                <?php endif; ?>
                                
                                <?php if ($view_instance['status'] === 'in_progress' && $s['status'] !== 'completed' && $s['status'] !== 'skipped'): ?>
                                    <?php if ($s['status'] === 'in_progress' || in_array($s['status'], ['pending', 'blocked'])): ?>
                                        <form method="POST" class="mt-2">
                                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="advance_step">
                                            <input type="hidden" name="step_instance_id" value="<?= $s['id'] ?>">
                                            <input type="hidden" name="instance_id" value="<?= $view_instance['id'] ?>">
                                            <div class="d-flex gap-2">
                                                <input type="text" name="step_notes" class="form-control-custom" placeholder="Notes (optional)" style="margin-bottom:0;">
                                                <button type="submit" class="btn-primary-custom btn-sm"><i class="bi bi-check"></i> Complete</button>
                                            </div>
                                        </form>
                                        <form method="POST" class="mt-2 d-inline-block">
                                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="update_step_status">
                                            <input type="hidden" name="step_instance_id" value="<?= $s['id'] ?>">
                                            <input type="hidden" name="instance_id" value="<?= $view_instance['id'] ?>">
                                            <input type="hidden" name="step_status" value="<?= $s['status'] === 'blocked' ? 'in_progress' : 'blocked' ?>">
                                            <button type="submit" class="btn-secondary-custom btn-sm">
                                                <i class="bi bi-<?= $s['status'] === 'blocked' ? 'play' : 'exclamation-circle' ?>"></i>
                                                <?= $s['status'] === 'blocked' ? 'Unblock' : 'Block' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($view_workflow): ?>
    <!-- Workflow Template Detail View -->
    <div class="page-title">
        <h1><i class="bi bi-diagram-3"></i> <?= e($view_workflow['name']) ?></h1>
        <div>
            <a href="workflows.php" class="btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">Workflow Details</div>
                <div class="card-body">
                    <p><strong>Key:</strong> <?= e($view_workflow['workflow_key']) ?></p>
                    <p><strong>Category:</strong> <?= e($view_workflow['category'] ?: 'General') ?></p>
                    <p><strong>Status:</strong> <?= $view_workflow['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></p>
                    <p><strong>Description:</strong> <?= e($view_workflow['description'] ?: 'No description') ?></p>
                    <p><strong>Steps:</strong> <?= count($view_steps) ?></p>
                    <p><strong>Created:</strong> <?= e(format_datetime($view_workflow['created_at'])) ?></p>
                    
                    <?php if (user_can('workflows', 'edit')): ?>
                        <form method="POST" class="mt-3" style="display:inline;" onsubmit="return confirm('Delete this workflow?')">
                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_workflow">
                            <input type="hidden" name="workflow_id" value="<?= $view_workflow['id'] ?>">
                            <button type="submit" class="btn-danger-custom"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Start workflow for client -->
            <div class="card">
                <div class="card-header">Start for Client</div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="start_workflow">
                        <input type="hidden" name="workflow_id" value="<?= $view_workflow['id'] ?>">
                        <label class="form-label-custom">Client *</label>
                        <select name="client_id" class="form-control-custom" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label-custom">Project (Optional)</label>
                        <select name="project_id" class="form-control-custom">
                            <option value="">No Project</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-primary-custom"><i class="bi bi-play"></i> Start Workflow</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-7">
            <!-- Steps -->
            <div class="card">
                <div class="card-header">Workflow Steps (<?= count($view_steps) ?>)</div>
                <div class="card-body">
                    <?php if (empty($view_steps)): ?>
                        <p class="text-muted">No steps defined yet.</p>
                    <?php else: ?>
                        <?php foreach ($view_steps as $i => $step): ?>
                            <div class="mb-2 p-2 d-flex justify-content-between align-items-center" style="border:1px solid #e5e7eb;border-radius:8px;">
                                <div>
                                    <strong><?= $i + 1 ?>. <?= e($step['step_name']) ?></strong>
                                    <div>
                                        <span class="badge badge-info"><?= e(ucfirst($step['step_type'])) ?></span>
                                        <?php if ($step['assigned_role_id']): ?>
                                            <?php $role = db_row('SELECT name FROM roles WHERE id = ?', [$step['assigned_role_id']]); ?>
                                            <span class="badge badge-secondary"><?= $role ? e($role['name']) : 'Role' ?></span>
                                        <?php endif; ?>
                                        <?php if ($step['assigned_user_id']): ?>
                                            <span class="badge badge-secondary"><?= e(user_name((int)$step['assigned_user_id'])) ?></span>
                                        <?php endif; ?>
                                        <?= $step['is_required'] ? '<span class="badge badge-warning">Required</span>' : '<span class="badge badge-secondary">Optional</span>' ?>
                                    </div>
                                </div>
                                <?php if (user_can('workflows', 'edit')): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this step?')">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_step">
                                    <input type="hidden" name="step_id" value="<?= $step['id'] ?>">
                                    <input type="hidden" name="workflow_id" value="<?= $view_workflow['id'] ?>">
                                    <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (user_can('workflows', 'edit')): ?>
                    <hr>
                    <h6><i class="bi bi-plus-circle"></i> Add Step</h6>
                    <form method="POST" action="" class="row g-3">
                        <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_step">
                        <input type="hidden" name="workflow_id" value="<?= $view_workflow['id'] ?>">
                        <div class="col-md-4">
                            <label class="form-label-custom">Step Name *</label>
                            <input type="text" name="step_name" class="form-control-custom" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">Type</label>
                            <select name="step_type" class="form-control-custom">
                                <?php foreach (['task', 'notification', 'email', 'sms', 'status_change', 'document', 'condition', 'wait', 'approval'] as $type): ?>
                                    <option value="<?= $type ?>"><?= e(ucfirst($type)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">Role</label>
                            <select name="assigned_role_id" class="form-control-custom">
                                <option value="0">Any Role</option>
                                <?php foreach ($all_roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">User</label>
                            <select name="assigned_user_id" class="form-control-custom">
                                <option value="0">Any User</option>
                                <?php foreach ($all_users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">Order</label>
                            <input type="number" name="step_order" class="form-control-custom" value="<?= count($view_steps) + 1 ?>" min="1">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_required" value="1" class="form-check-input" checked>
                                <label class="form-check-label">Required Step</label>
                            </div>
                            <button type="submit" class="btn-primary-custom"><i class="bi bi-plus"></i> Add Step</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Instances of this template -->
            <div class="card">
                <div class="card-header">Instances (<?= count($view_instances) ?>)</div>
                <div class="card-body">
                    <?php if (empty($view_instances)): ?>
                        <p class="text-muted">No instances started yet.</p>
                    <?php else: ?>
                        <table class="table-custom">
                            <thead><tr><th>Client</th><th>Status</th><th>Progress</th><th>Started</th></tr></thead>
                            <tbody>
                            <?php foreach ($view_instances as $inst): ?>
                                <tr>
                                    <td><a href="workflows.php?instance=<?= $inst['id'] ?>"><?= e($inst['first_name'] . ' ' . $inst['last_name']) ?></a></td>
                                    <td><?= status_badge($inst['status']) ?></td>
                                    <td><?= (int)$inst['progress_value'] ?>% (<?= $inst['steps_done'] ?>/<?= $inst['total_steps'] ?>)</td>
                                    <td><?= e(format_datetime($inst['started_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Workflows List -->
    <div class="page-title">
        <h1><i class="bi bi-diagram-3"></i> Workflows</h1>
        <button class="btn-primary-custom" onclick="toggleNewWorkflow()"><i class="bi bi-plus"></i> New Workflow</button>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-diagram-3"></i></div>
                <div class="stat-value"><?= $total_workflows_count ?></div>
                <div class="stat-label">Total Workflows</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-play-circle"></i></div>
                <div class="stat-value"><?= $running_instances ?></div>
                <div class="stat-label">Running Instances</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-check-circle"></i></div>
                <div class="stat-value"><?= $completed_instances ?></div>
                <div class="stat-label">Completed Instances</div>
            </div>
        </div>
    </div>
    
    <!-- New Workflow Form -->
    <div class="card" id="workflowForm" style="display:none;">
        <div class="card-header">Create New Workflow</div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create_workflow">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Name *</label>
                        <input type="text" name="name" class="form-control-custom" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Workflow Key *</label>
                        <input type="text" name="workflow_key" class="form-control-custom" required placeholder="e.g. new_client_onboarding">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Category</label>
                        <input type="text" name="category" class="form-control-custom" placeholder="e.g. onboarding, tax, immigration">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-10">
                        <label class="form-label-custom">Description</label>
                        <textarea name="description" class="form-control-custom" rows="2"></textarea>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label-custom">Active</label>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" checked>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Create Workflow</button>
                <button type="button" class="btn-secondary-custom" onclick="toggleNewWorkflow()">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Active Instances -->
    <?php if (!empty($active_instances)): ?>
    <div class="card">
        <div class="card-header">Active Workflow Instances</div>
        <div class="card-body">
            <table class="table-custom">
                <thead><tr><th>Workflow</th><th>Client</th><th>Status</th><th>Progress</th><th>Steps</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($active_instances as $instance): ?>
                    <tr>
                        <td><a href="workflows.php?instance=<?= $instance['id'] ?>"><strong><?= e($instance['workflow_name']) ?></strong></a></td>
                        <td><?= e(($instance['first_name'] ?? '') . ' ' . ($instance['last_name'] ?? '')) ?></td>
                        <td><?= status_badge($instance['status']) ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <div class="flex-grow-1" style="background:#e5e7eb;border-radius:4px;height:8px;">
                                    <div style="width:<?= (int)$instance['progress_value'] ?>%;background:var(--primary);border-radius:4px;height:8px;"></div>
                                </div>
                                <small><?= (int)$instance['progress_value'] ?>%</small>
                            </div>
                        </td>
                        <td><?= $instance['steps_done'] ?> / <?= $instance['total_steps'] ?></td>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this workflow instance?')">
                                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="cancel_workflow">
                                <input type="hidden" name="instance_id" value="<?= $instance['id'] ?>">
                                <button type="submit" class="btn-danger-custom btn-sm" title="Cancel"><i class="bi bi-x-circle"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Start Workflow Form -->
    <div class="card">
        <div class="card-header"><i class="bi bi-node-plus"></i> Start Workflow for Client</div>
        <div class="card-body">
            <form method="POST" action="" class="row g-3">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="start_workflow">
                <div class="col-md-3">
                    <label class="form-label-custom">Workflow *</label>
                    <select name="workflow_id" class="form-control-custom" required>
                        <option value="">Select Workflow</option>
                        <?php foreach ($workflows as $wf): ?>
                            <option value="<?= $wf['id'] ?>"><?= e($wf['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">Client *</label>
                    <select name="client_id" class="form-control-custom" required>
                        <option value="">Select Client</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">Project (Optional)</label>
                    <select name="project_id" class="form-control-custom">
                        <option value="">No Project</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-play"></i> Start</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Workflow Templates Table -->
    <div class="card">
        <div class="card-header">Workflow Templates (<?= $total_workflows_count ?>)</div>
        <div class="card-body">
            <?php if (empty($workflows)): ?>
                <p class="text-muted">No workflows found. Create your first workflow above.</p>
            <?php else: ?>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Key</th>
                            <th>Category</th>
                            <th>Steps</th>
                            <th>Running</th>
                            <th>Completed</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workflows as $w): ?>
                            <tr>
                                <td><a href="workflows.php?view=<?= $w['id'] ?>"><strong><?= e($w['name']) ?></strong></a></td>
                                <td><?= e($w['workflow_key']) ?></td>
                                <td><?= e($w['category'] ?: '-') ?></td>
                                <td><?= $w['step_count'] ?></td>
                                <td><?= $w['running_count'] ?></td>
                                <td><?= $w['completed_count'] ?></td>
                                <td><?= $w['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                                <td>
                                    <a href="workflows.php?view=<?= $w['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-eye"></i></a>
                                    <?php if (user_can('workflows', 'edit')): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this workflow?')">
                                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_workflow">
                                            <input type="hidden" name="workflow_id" value="<?= $w['id'] ?>">
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
<?php endif; ?>

<script>
function toggleNewWorkflow() {
    const form = document.getElementById('workflowForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/footer.php'; ?>