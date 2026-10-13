<?php
/**
 * GRAVITY CRM - Projects Management
 * Phase 2: Core CRM Modules
 * 
 * Yahan projects create/edit/delete hote hain.
 * Project mein tasks add/update/delete kiye ja sakte hain.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('projects', 'view');

$user = current_user();
$page_title = 'Projects';
$active_page = 'projects';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create' || $action === 'update') {
        require_permission('projects', 'create');
        
        $project_id = (int)post('project_id', 0);
        $client_id = (int)post('client_id', 0);
        $name = sanitize(post('name'));
        $description = sanitize(post('description'));
        $status = sanitize(post('status', 'not_started'));
        $priority = sanitize(post('priority', 'medium'));
        $start_date = sanitize(post('start_date'));
        $due_date = sanitize(post('due_date'));
        $assigned_user_id = (int)post('assigned_user_id', 0);
        $progress_value = min(100, max(0, (int)post('progress_value', 0)));
        $budget = (float)post('budget', 0);
        $notes = sanitize(post('notes'));
        
        $errors = [];
        validate_required($name, 'Project name', $errors);
        if ($client_id <= 0) {
            $errors[] = 'Please select a client.';
        }
        
        if (empty($errors)) {
            if ($action === 'create') {
                db_execute(
                    'INSERT INTO projects (client_id, name, description, status, priority, start_date, due_date, assigned_user_id, progress_value, budget, notes, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$client_id, $name, $description, $status, $priority, $start_date ?: null, $due_date ?: null, $assigned_user_id ?: null, $progress_value, $budget ?: null, $notes, $user['id']]
                );
                $project_id = db_last_id();
                
                // Create user_progress record for assigned user
                if ($assigned_user_id > 0) {
                    db_execute(
                        'INSERT INTO user_progress (user_id, module, record_id, progress_label, progress_value, status) VALUES (?, "projects", ?, ?, ?, ?)',
                        [$assigned_user_id, $project_id, $name, $progress_value, $progress_value > 0 ? 'in_progress' : 'not_started']
                    );
                }
                
                log_activity('projects', 'create', $project_id, 'Created project: ' . $name);
                flash('success', 'Project created successfully.');
            } else {
                $existing = db_row('SELECT * FROM projects WHERE id = ?', [$project_id]);
                if (!$existing) {
                    flash('error', 'Project not found.');
                    redirect('projects.php');
                }
                
                db_execute(
                    'UPDATE projects SET client_id = ?, name = ?, description = ?, status = ?, priority = ?, start_date = ?, due_date = ?, assigned_user_id = ?, progress_value = ?, budget = ?, notes = ? WHERE id = ?',
                    [$client_id, $name, $description, $status, $priority, $start_date ?: null, $due_date ?: null, $assigned_user_id ?: null, $progress_value, $budget ?: null, $notes, $project_id]
                );
                
                // Update project completed_at
                if ($status === 'completed') {
                    db_execute('UPDATE projects SET completed_at = NOW() WHERE id = ?', [$project_id]);
                }
                
                log_activity('projects', 'update', $project_id, 'Updated project: ' . $name);
                flash('success', 'Project updated successfully.');
            }
            redirect('projects.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete') {
        require_permission('projects', 'delete');
        $project_id = (int)post('project_id', 0);
        
        $existing = db_row('SELECT * FROM projects WHERE id = ?', [$project_id]);
        if (!$existing) {
            flash('error', 'Project not found.');
            redirect('projects.php');
        }
        
        db_execute('DELETE FROM projects WHERE id = ?', [$project_id]);
        log_activity('projects', 'delete', $project_id, 'Deleted project: ' . $existing['name']);
        flash('success', 'Project deleted successfully.');
        redirect('projects.php');
    }
    
    if ($action === 'add_task') {
        $project_id = (int)post('project_id', 0);
        $title = sanitize(post('title'));
        $description = sanitize(post('description'));
        $assigned_user_id = (int)post('assigned_user_id', 0);
        $due_date = sanitize(post('due_date'));
        $priority = sanitize(post('priority', 'medium'));
        
        $errors = [];
        validate_required($title, 'Task title', $errors);
        
        if (empty($errors)) {
            db_execute(
                'INSERT INTO project_tasks (project_id, title, description, assigned_user_id, due_date, priority, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$project_id, $title, $description, $assigned_user_id ?: null, $due_date ?: null, $priority, $user['id']]
            );
            $task_id = db_last_id();
            
            // Create user_progress for assigned user
            if ($assigned_user_id > 0) {
                db_execute(
                    'INSERT INTO user_progress (user_id, module, record_id, progress_label, progress_value, status) VALUES (?, "project_tasks", ?, ?, 0, "not_started")',
                    [$assigned_user_id, $task_id, $title]
                );
            }
            
            log_activity('projects', 'add_task', $project_id, 'Added task to project: ' . $title);
            flash('success', 'Task added successfully.');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
        redirect('projects.php?view=' . $project_id);
    }
    
    if ($action === 'update_task') {
        $task_id = (int)post('task_id', 0);
        $title = sanitize(post('title'));
        $description = sanitize(post('description'));
        $status = sanitize(post('status', 'pending'));
        $assigned_user_id = (int)post('assigned_user_id', 0);
        $due_date = sanitize(post('due_date'));
        $priority = sanitize(post('priority', 'medium'));
        $progress_value = min(100, max(0, (int)post('progress_value', 0)));
        $project_id = (int)post('project_id', 0);
        
        $existing = db_row('SELECT * FROM project_tasks WHERE id = ?', [$task_id]);
        if (!$existing) {
            flash('error', 'Task not found.');
            redirect('projects.php');
        }
        
        db_execute(
            'UPDATE project_tasks SET title = ?, description = ?, status = ?, assigned_user_id = ?, due_date = ?, priority = ?, progress_value = ?, completed_at = ? WHERE id = ?',
            [$title, $description, $status, $assigned_user_id ?: null, $due_date ?: null, $priority, $progress_value, $status === 'completed' ? date('Y-m-d H:i:s') : null, $task_id]
        );
        
        // Update user_progress linked record
        db_execute(
            'UPDATE user_progress SET progress_value = ?, status = ?, progress_label = ? WHERE module = "project_tasks" AND record_id = ?',
            [$progress_value, $progress_value >= 100 ? 'completed' : ($progress_value > 0 ? 'in_progress' : 'not_started'), $title, $task_id]
        );
        
        log_activity('projects', 'update_task', $project_id, 'Updated task: ' . $title);
        flash('success', 'Task updated successfully.');
        redirect('projects.php?view=' . ($project_id ?: $existing['project_id']));
    }
    
    if ($action === 'delete_task') {
        $task_id = (int)post('task_id', 0);
        $existing = db_row('SELECT * FROM project_tasks WHERE id = ?', [$task_id]);
        if (!$existing) {
            flash('error', 'Task not found.');
            redirect('projects.php');
        }
        
        db_execute('DELETE FROM project_tasks WHERE id = ?', [$task_id]);
        db_execute('DELETE FROM user_progress WHERE module = "project_tasks" AND record_id = ?', [$task_id]);
        log_activity('projects', 'delete_task', $existing['project_id'], 'Deleted task: ' . $existing['title']);
        flash('success', 'Task deleted successfully.');
        redirect('projects.php?view=' . $existing['project_id']);
    }
}

// Filters
$where = '1=1';
$params = [];

$search = trim(get('search', ''));
if ($search !== '') {
    $where .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like);
}

$status_filter = get('status', '');
if ($status_filter !== '') {
    $where .= ' AND p.status = ?';
    $params[] = $status_filter;
}

$assigned_filter = (int)get('assigned', 0);
if ($assigned_filter > 0) {
    $where .= ' AND p.assigned_user_id = ?';
    $params[] = $assigned_filter;
}

// Pagination
$per_page = (int)get_setting('items_per_page', 20);
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $per_page;

$total = (int)db_value("SELECT COUNT(*) FROM projects p WHERE {$where}", $params);
$projects = db_query(
    "SELECT p.*, c.first_name, c.last_name, u.first_name AS assigned_first, u.last_name AS assigned_last 
     FROM projects p 
     JOIN clients c ON p.client_id = c.id 
     LEFT JOIN users u ON p.assigned_user_id = u.id 
     WHERE {$where} 
     ORDER BY p.created_at DESC 
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);
$total_pages = max(1, ceil($total / $per_page));

// Stats
$total_projects = (int)db_value('SELECT COUNT(*) FROM projects');
$in_progress = (int)db_value('SELECT COUNT(*) FROM projects WHERE status = "in_progress"');
$completed_projects = (int)db_value('SELECT COUNT(*) FROM projects WHERE status = "completed"');
$overdue = (int)db_value('SELECT COUNT(*) FROM projects WHERE status NOT IN ("completed","cancelled") AND due_date < CURDATE()');

// Dropdown data
$clients = db_query('SELECT id, first_name, last_name FROM clients WHERE status = "active" ORDER BY first_name');
$users = db_query('SELECT id, first_name, last_name FROM users WHERE status = "active" ORDER BY first_name');

// Edit project
$edit_project = null;
if (get('edit')) {
    $edit_project = db_row('SELECT * FROM projects WHERE id = ?', [(int)get('edit')]);
}

// View project
$view_project = null;
if (get('view')) {
    $view_project = db_row(
        'SELECT p.*, c.first_name AS client_first, c.last_name AS client_last, u.first_name AS assigned_first, u.last_name AS assigned_last 
         FROM projects p 
         JOIN clients c ON p.client_id = c.id 
         LEFT JOIN users u ON p.assigned_user_id = u.id 
         WHERE p.id = ?',
        [(int)get('view')]
    );
    if ($view_project) {
        log_activity('projects', 'view', $view_project['id'], 'Viewed project: ' . $view_project['name']);
        $view_tasks = db_query(
            'SELECT pt.*, u.first_name AS assigned_first, u.last_name AS assigned_last 
             FROM project_tasks pt 
             LEFT JOIN users u ON pt.assigned_user_id = u.id 
             WHERE pt.project_id = ? 
             ORDER BY pt.sort_order, pt.created_at',
            [$view_project['id']]
        );
        $view_invoices = db_query('SELECT * FROM invoices WHERE project_id = ?', [$view_project['id']]);
        $view_documents = db_query('SELECT * FROM documents WHERE project_id = ?', [$view_project['id']]);
    }
}
?>
<?php include __DIR__ . '/header.php'; ?>

<?php if ($view_project): ?>
    <!-- Project Profile View -->
    <div class="page-title">
        <h1><i class="bi bi-kanban"></i> <?= e($view_project['name']) ?></h1>
        <div>
            <a href="projects.php?edit=<?= $view_project['id'] ?>" class="btn-secondary-custom"><i class="bi bi-pencil"></i> Edit</a>
            <a href="projects.php" class="btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Project Information</div>
                <div class="card-body">
                    <p><strong>Client:</strong> <a href="clients.php?view=<?= $view_project['client_id'] ?>"><?= e($view_project['client_first'] . ' ' . $view_project['client_last']) ?></a></p>
                    <p><strong>Status:</strong> <?= status_badge($view_project['status']) ?></p>
                    <p><strong>Priority:</strong> <?= status_badge($view_project['priority']) ?></p>
                    <p><strong>Start Date:</strong> <?= e(format_date($view_project['start_date'])) ?></p>
                    <p><strong>Due Date:</strong> <?= e(format_date($view_project['due_date'])) ?> <?= $view_project['due_date'] && strtotime($view_project['due_date']) < time() && !in_array($view_project['status'], ['completed', 'cancelled']) ? '<span class="badge badge-danger">Overdue</span>' : '' ?></p>
                    <p><strong>Assigned To:</strong> <?= e($view_project['assigned_first'] ? $view_project['assigned_first'] . ' ' . $view_project['assigned_last'] : 'Unassigned') ?></p>
                    <p><strong>Budget:</strong> <?= $view_project['budget'] ? format_currency($view_project['budget']) : 'N/A' ?></p>
                    <p><strong>Billed:</strong> <?= format_currency($view_project['billed_amount']) ?></p>
                    <p><strong>Created:</strong> <?= e(format_date($view_project['created_at'])) ?></p>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between">
                            <strong>Progress</strong>
                            <span><?= $view_project['progress_value'] ?>%</span>
                        </div>
                        <div class="progress" style="height:8px;background:#e5e7eb;border-radius:4px;">
                            <div class="progress-bar" style="width:<?= (int)$view_project['progress_value'] ?>%;background:var(--primary);border-radius:4px;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">Notes</div>
                <div class="card-body">
                    <p><?= e($view_project['notes'] ?: 'No notes') ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Add Task -->
            <div class="card">
                <div class="card-header"><i class="bi bi-plus-circle"></i> Add Task</div>
                <div class="card-body">
                    <form method="POST" action="" class="row g-3">
                        <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_task">
                        <input type="hidden" name="project_id" value="<?= $view_project['id'] ?>">
                        <div class="col-md-6">
                            <label class="form-label-custom">Task Title *</label>
                            <input type="text" name="title" class="form-control-custom" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Assigned To</label>
                            <select name="assigned_user_id" class="form-control-custom">
                                <option value="0">Unassigned</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Due Date</label>
                            <input type="date" name="due_date" class="form-control-custom">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Priority</label>
                            <select name="priority" class="form-control-custom">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label-custom">Description</label>
                            <input type="text" name="description" class="form-control-custom" placeholder="Optional task description">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn-primary-custom"><i class="bi bi-plus"></i> Add Task</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Project Tasks -->
            <div class="card">
                <div class="card-header">Tasks (<?= count($view_tasks) ?>)</div>
                <div class="card-body">
                    <?php if (empty($view_tasks)): ?>
                        <p class="text-muted">No tasks yet.</p>
                    <?php else: ?>
                        <?php foreach ($view_tasks as $task): ?>
                            <div class="p-3 mb-2" style="border:1px solid #e5e7eb;border-radius:8px;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= e($task['title']) ?></strong>
                                        <span class="badge badge-<?= $task['status'] === 'completed' ? 'success' : ($task['status'] === 'blocked' ? 'danger' : ($task['status'] === 'in_progress' ? 'primary' : 'secondary')) ?>">
                                            <?= e(ucfirst(str_replace('_', ' ', $task['status']))) ?>
                                        </span>
                                        <span class="badge badge-warning"><?= e(ucfirst($task['priority'])) ?></span>
                                        <div class="text-muted" style="font-size:0.85rem;">
                                            Assigned: <?= e($task['assigned_first'] ? $task['assigned_first'] . ' ' . $task['assigned_last'] : 'Unassigned') ?> | Due: <?= e(format_date($task['due_date'])) ?>
                                        </div>
                                        <?php if ($task['description']): ?>
                                            <div class="text-muted" style="font-size:0.85rem;"><?= e($task['description']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" class="d-flex gap-2" onsubmit="return confirm('Delete this task?')">
                                        <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                        <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                                <div class="mt-2">
                                    <div class="progress" style="height:6px;background:#e5e7eb;border-radius:4px;">
                                        <div class="progress-bar" style="width:<?= (int)$task['progress_value'] ?>%;background:var(--primary);border-radius:4px;"></div>
                                    </div>
                                    <small class="text-muted"><?= $task['progress_value'] ?>%</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($view_invoices)): ?>
            <div class="card">
                <div class="card-header">Invoices (<?= count($view_invoices) ?>)</div>
                <div class="card-body">
                    <table class="table-custom">
                        <thead><tr><th>Number</th><th>Date</th><th>Status</th><th>Amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($view_invoices as $inv): ?>
                                <tr>
                                    <td><a href="invoices.php?view=<?= $inv['id'] ?>"><?= e($inv['invoice_number']) ?></a></td>
                                    <td><?= e(format_date($inv['invoice_date'])) ?></td>
                                    <td><?= status_badge($inv['status']) ?></td>
                                    <td><?= format_currency($inv['total_amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($view_documents)): ?>
            <div class="card">
                <div class="card-header">Documents (<?= count($view_documents) ?>)</div>
                <div class="card-body">
                    <table class="table-custom">
                        <thead><tr><th>Title</th><th>File</th></tr></thead>
                        <tbody>
                            <?php foreach ($view_documents as $doc): ?>
                                <tr>
                                    <td><?= e($doc['title']) ?></td>
                                    <td><a href="documents.php?view=<?= $doc['id'] ?>"><?= e($doc['file_name']) ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- Projects List -->
    <div class="page-title">
        <h1><i class="bi bi-kanban"></i> Projects</h1>
        <button class="btn-primary-custom" onclick="toggleNewProject()"><i class="bi bi-plus"></i> New Project</button>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-kanban"></i></div>
                <div class="stat-value"><?= $total_projects ?></div>
                <div class="stat-label">Total Projects</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-lightning"></i></div>
                <div class="stat-value"><?= $in_progress ?></div>
                <div class="stat-label">In Progress</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-check-circle"></i></div>
                <div class="stat-value"><?= $completed_projects ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fee2e2;color:#991b1b;"><i class="bi bi-exclamation-circle"></i></div>
                <div class="stat-value"><?= $overdue ?></div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>
    </div>
    
    <!-- New/Edit Project Form -->
    <div class="card" id="projectForm" style="display:<?= $edit_project ? 'block' : 'none' ?>;">
        <div class="card-header">
            <?= $edit_project ? 'Edit Project: ' . e($edit_project['name']) : 'Create New Project' ?>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $edit_project ? 'update' : 'create' ?>">
                <input type="hidden" name="project_id" value="<?= $edit_project['id'] ?? 0 ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label-custom">Project Name *</label>
                        <input type="text" name="name" class="form-control-custom" value="<?= e($edit_project['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Client *</label>
                        <select name="client_id" class="form-control-custom" required>
                            <option value="0">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= ($edit_project['client_id'] ?? 0) == $client['id'] ? 'selected' : '' ?>>
                                    <?= e($client['first_name'] . ' ' . $client['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Status</label>
                        <select name="status" class="form-control-custom">
                            <option value="not_started" <?= ($edit_project['status'] ?? '') === 'not_started' ? 'selected' : '' ?>>Not Started</option>
                            <option value="in_progress" <?= ($edit_project['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="on_hold" <?= ($edit_project['status'] ?? '') === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                            <option value="completed" <?= ($edit_project['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= ($edit_project['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label-custom">Priority</label>
                        <select name="priority" class="form-control-custom">
                            <option value="low" <?= ($edit_project['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                            <option value="medium" <?= ($edit_project['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="high" <?= ($edit_project['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                            <option value="urgent" <?= ($edit_project['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Start Date</label>
                        <input type="date" name="start_date" class="form-control-custom" value="<?= e($edit_project['start_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Due Date</label>
                        <input type="date" name="due_date" class="form-control-custom" value="<?= e($edit_project['due_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Budget</label>
                        <input type="number" name="budget" step="0.01" class="form-control-custom" value="<?= e($edit_project['budget'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Assigned To</label>
                        <select name="assigned_user_id" class="form-control-custom">
                            <option value="0">Unassigned</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($edit_project['assigned_user_id'] ?? 0) == $u['id'] ? 'selected' : '' ?>>
                                    <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Progress (%)</label>
                        <input type="number" name="progress_value" min="0" max="100" class="form-control-custom" value="<?= e($edit_project['progress_value'] ?? 0) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Description</label>
                        <input type="text" name="description" class="form-control-custom" value="<?= e($edit_project['description'] ?? '') ?>">
                    </div>
                </div>
                
                <label class="form-label-custom">Notes</label>
                <textarea name="notes" class="form-control-custom" rows="3"><?= e($edit_project['notes'] ?? '') ?></textarea>
                
                <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> <?= $edit_project ? 'Update' : 'Create' ?> Project</button>
                <button type="button" class="btn-secondary-custom" onclick="toggleNewProject()">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control-custom" placeholder="Search projects..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control-custom">
                        <option value="">All Status</option>
                        <option value="not_started" <?= $status_filter === 'not_started' ? 'selected' : '' ?>>Not Started</option>
                        <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="on_hold" <?= $status_filter === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="assigned" class="form-control-custom">
                        <option value="0">All Staff</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $assigned_filter == $u['id'] ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Filter</button>
                    <a href="projects.php" class="btn-secondary-custom">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Projects Table -->
    <div class="card">
        <div class="card-header">Projects (<?= $total ?>)</div>
        <div class="card-body">
            <?php if (empty($projects)): ?>
                <p class="text-muted">No projects found.</p>
            <?php else: ?>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Assigned To</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <?php
                            $is_overdue = $project['due_date'] && strtotime($project['due_date']) < time() && !in_array($project['status'], ['completed', 'cancelled']);
                            ?>
                            <tr>
                                <td><a href="projects.php?view=<?= $project['id'] ?>"><strong><?= e($project['name']) ?></strong></a></td>
                                <td><?= e($project['first_name'] . ' ' . $project['last_name']) ?></td>
                                <td><?= status_badge($project['status']) ?></td>
                                <td><?= status_badge($project['priority']) ?></td>
                                <td><?= e($project['assigned_first'] ? $project['assigned_first'] . ' ' . $project['assigned_last'] : '-') ?></td>
                                <td><?= e(format_date($project['due_date'])) ?> <?= $is_overdue ? '<span class="badge badge-danger">Overdue</span>' : '' ?></td>
                                <td>
                                    <a href="projects.php?view=<?= $project['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-eye"></i></a>
                                    <a href="projects.php?edit=<?= $project['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this project?')">
                                        <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                                        <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?= pagination_links($total_pages, $page, 'projects.php?' . http_build_query(array_filter(['search' => $search, 'status' => $status_filter, 'assigned' => $assigned_filter]))) ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleNewProject() {
    const form = document.getElementById('projectForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/footer.php'; ?>