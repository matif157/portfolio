<?php
/**
 * GRAVITY CRM - Automation Rules
 * Phase 3: Service Automation & Workflow Engine
 * 
 * Yahan automation rules create/edit/delete/activate hote hain.
 * Trigger-based automation: clients, leads, projects, invoices etc.
 * Action types: notify, create_task, update_status, send_email, start_workflow.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('automation', 'view');

$user = current_user();
$page_title = 'Automation';
$active_page = 'automation';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create_rule' || $action === 'update_rule') {
        require_permission('automation', 'edit');
        
        $rule_id = (int)post('rule_id', 0);
        $name = sanitize(post('name'));
        $trigger_module = sanitize(post('trigger_module'));
        $trigger_event = sanitize(post('trigger_event'));
        $trigger_field = sanitize(post('trigger_field'));
        $trigger_value = sanitize(post('trigger_value'));
        $action_type = sanitize(post('action_type'));
        $is_active = post('is_active') ? 1 : 0;
        
        // Build action config
        $action_config = [];
        if ($action_type === 'notify') {
            $action_config['user_id'] = (int)post('notify_user_id', 0);
            $action_config['message'] = sanitize(post('notify_message'));
        } elseif ($action_type === 'create_task') {
            $action_config['title'] = sanitize(post('task_title'));
            $action_config['assignee_id'] = (int)post('task_assignee_id', 0);
            $action_config['due_in_days'] = (int)post('task_due_in_days', 0);
        } elseif ($action_type === 'update_status') {
            $action_config['module'] = sanitize(post('update_status_module'));
            $action_config['status'] = sanitize(post('update_status_value'));
        } elseif ($action_type === 'send_email') {
            $action_config['template_id'] = (int)post('email_template_id', 0);
        } elseif ($action_type === 'send_sms') {
            $action_config['template_id'] = (int)post('sms_template_id', 0);
        } elseif ($action_type === 'start_workflow') {
            $action_config['workflow_id'] = (int)post('workflow_id', 0);
        }
        $action_json = json_encode($action_config);
        
        $errors = [];
        validate_required($name, 'Rule name', $errors);
        validate_required($trigger_module, 'Trigger module', $errors);
        validate_required($trigger_event, 'Trigger event', $errors);
        validate_required($action_type, 'Action type', $errors);
        
        if (empty($errors)) {
            if ($action === 'create_rule') {
                db_execute(
                    'INSERT INTO automation_rules (name, trigger_module, trigger_event, trigger_field, trigger_value, action_type, action_config, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$name, $trigger_module, $trigger_event, $trigger_field ?: null, $trigger_value ?: null, $action_type, $action_json, $is_active, $user['id']]
                );
                log_activity('automation', 'create', db_last_id(), 'Created automation rule: ' . $name);
                flash('success', 'Automation rule created successfully.');
            } else {
                $existing = db_row('SELECT * FROM automation_rules WHERE id = ?', [$rule_id]);
                if (!$existing) {
                    flash('error', 'Automation rule not found.');
                    redirect('automation.php');
                }
                
                db_execute(
                    'UPDATE automation_rules SET name = ?, trigger_module = ?, trigger_event = ?, trigger_field = ?, trigger_value = ?, action_type = ?, action_config = ?, is_active = ? WHERE id = ?',
                    [$name, $trigger_module, $trigger_event, $trigger_field ?: null, $trigger_value ?: null, $action_type, $action_json, $is_active, $rule_id]
                );
                log_activity('automation', 'update', $rule_id, 'Updated automation rule: ' . $name);
                flash('success', 'Automation rule updated successfully.');
            }
            redirect('automation.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete_rule') {
        require_permission('automation', 'edit');
        $rule_id = (int)post('rule_id', 0);
        
        $existing = db_row('SELECT * FROM automation_rules WHERE id = ?', [$rule_id]);
        if (!$existing) {
            flash('error', 'Automation rule not found.');
            redirect('automation.php');
        }
        
        db_execute('DELETE FROM automation_rules WHERE id = ?', [$rule_id]);
        log_activity('automation', 'delete', $rule_id, 'Deleted automation rule: ' . $existing['name']);
        flash('success', 'Automation rule deleted successfully.');
        redirect('automation.php');
    }
    
    if ($action === 'toggle_rule') {
        require_permission('automation', 'edit');
        $rule_id = (int)post('rule_id', 0);
        
        $rule = db_row('SELECT * FROM automation_rules WHERE id = ?', [$rule_id]);
        if (!$rule) {
            flash('error', 'Automation rule not found.');
            redirect('automation.php');
        }
        
        $new_status = $rule['is_active'] ? 0 : 1;
        db_execute('UPDATE automation_rules SET is_active = ? WHERE id = ?', [$new_status, $rule_id]);
        log_activity('automation', 'update', $rule_id, ($new_status ? 'Activated' : 'Deactivated') . ' automation rule: ' . $rule['name']);
        flash('success', 'Automation rule ' . ($new_status ? 'activated' : 'deactivated') . '.');
        redirect('automation.php');
    }
    
    if ($action === 'test_rule') {
        require_permission('automation', 'edit');
        $rule_id = (int)post('rule_id', 0);
        
        $rule = db_row('SELECT * FROM automation_rules WHERE id = ?', [$rule_id]);
        if (!$rule) {
            flash('error', 'Automation rule not found.');
            redirect('automation.php');
        }
        
        // Simulate the rule execution
        $result = execute_automation_rule($rule, ['test' => true]);
        db_execute(
            'INSERT INTO automation_logs (rule_id, trigger_module, trigger_event, record_id, action_type, action_result, message, executed_by) VALUES (?, ?, ?, NULL, ?, ?, ?, ?)',
            [$rule_id, $rule['trigger_module'], $rule['trigger_event'], $rule['action_type'], $result['success'] ? 'completed' : 'failed', $result['message'], $user['id']]
        );
        
        flash($result['success'] ? 'success' : 'warning', 'Test: ' . $result['message']);
        redirect('automation.php');
    }
}

// Get all automation rules
$rules = db_query(
    'SELECT ar.*, u.first_name AS created_first, u.last_name AS created_last 
     FROM automation_rules ar 
     LEFT JOIN users u ON ar.created_by = u.id 
     ORDER BY ar.created_at DESC'
);

// Recent automation logs
$recent_logs = db_query(
    'SELECT al.*, ar.name AS rule_name FROM automation_logs al 
     LEFT JOIN automation_rules ar ON al.rule_id = ar.id 
     ORDER BY al.executed_at DESC LIMIT 50'
);

// Stats
$active_rules = (int)db_value('SELECT COUNT(*) FROM automation_rules WHERE is_active = 1');
$inactive_rules = (int)db_value('SELECT COUNT(*) FROM automation_rules WHERE is_active = 0');
$total_rules = $active_rules + $inactive_rules;
$completed_logs = (int)db_value('SELECT COUNT(*) FROM automation_logs WHERE action_result = "completed"');
$failed_logs = (int)db_value('SELECT COUNT(*) FROM automation_logs WHERE action_result = "failed"');

// Data for dropdowns
$users = db_query('SELECT id, first_name, last_name FROM users WHERE status = "active" ORDER BY first_name');
$workflows = db_query('SELECT id, name FROM workflow_templates ORDER BY name');
$email_templates = db_query('SELECT id, name FROM message_templates WHERE template_type = "email" ORDER BY name');
$sms_templates = db_query('SELECT id, name FROM message_templates WHERE template_type = "sms" ORDER BY name');

// Edit rule
$edit_rule = null;
if (get('edit')) {
    $edit_rule = db_row('SELECT * FROM automation_rules WHERE id = ?', [(int)get('edit')]);
    if ($edit_rule && $edit_rule['action_config']) {
        $edit_rule['action_config_arr'] = json_decode($edit_rule['action_config'], true) ?: [];
    }
}

/**
 * Execute an automation rule
 * 
 * @param array $rule
 * @param array $context Record context (record_id, module data)
 * @return array [success, message]
 */
function execute_automation_rule(array $rule, array $context = []): array {
    $config = json_decode($rule['action_config'] ?? '{}', true) ?: [];
    
    switch ($rule['action_type']) {
        case 'notify':
            $target_user = $config['user_id'] ?? 0;
            $message = $config['message'] ?? 'Automation notification';
            $record_id = $context['record_id'] ?? null;
            
            if ($target_user > 0) {
                db_execute(
                    'INSERT INTO notifications (user_id, title, message, module, record_id) VALUES (?, ?, ?, ?, ?)',
                    [$target_user, 'Automation: ' . $rule['name'], $message, $rule['trigger_module'], $record_id]
                );
                return [true, 'Notification sent to user #' . $target_user];
            }
            return [false, 'No target user specified for notification.'];
            
        case 'create_task':
            $title = $config['title'] ?? 'Automated task';
            $assignee_id = $config['assignee_id'] ?? 0;
            $due_in_days = (int)($config['due_in_days'] ?? 0);
            
            if ($assignee_id > 0) {
                $record_id = $context['record_id'] ?? null;
                $due_date = $due_in_days > 0 ? date('Y-m-d', strtotime('+' . $due_in_days . ' days')) : null;
                $module = $rule['trigger_module'];
                
                db_execute(
                    'INSERT INTO user_progress (user_id, module, record_id, progress_label, progress_value, status) VALUES (?, ?, ?, ?, 0, "not_started")',
                    [$assignee_id, $module, $record_id, $title]
                );
                
                return [true, 'Task created: ' . $title . ($due_date ? ' (due ' . $due_date . ')' : '')];
            }
            return [false, 'No assignee specified for task.'];
            
        case 'update_status':
            $module = $config['module'] ?? '';
            $new_status = $config['status'] ?? '';
            $record_id = $context['record_id'] ?? 0;
            
            if ($module && $new_status && $record_id > 0) {
                $allowed_modules = ['clients', 'leads', 'contacts', 'businesses', 'projects', 'invoices'];
                if (in_array($module, $allowed_modules)) {
                    $table_columns = db_columns($module);
                    if (in_array('status', $table_columns)) {
                        db_execute("UPDATE {$module} SET status = ? WHERE id = ?", [$new_status, $record_id]);
                        return [true, 'Status of ' . $module . ' #' . $record_id . ' updated to ' . $new_status];
                    }
                }
            }
            return [false, 'Invalid status update configuration.'];
            
        case 'send_email':
            $template_id = (int)($config['template_id'] ?? 0);
            if ($template_id > 0) {
                $rule_name = $rule['name'];
                db_execute(
                    'INSERT INTO sent_messages (message_type, template_id, client_id, recipient, subject, body, status, provider, sent_by) 
                     VALUES ("email", ?, ?, ?, "Test Email", ?, "pending", "auto", NULL)',
                    [$template_id, $context['client_id'] ?? null, $context['email'] ?? '', 'Automated email from rule: ' . $rule_name]
                );
                return [true, 'Email scheduled for delivery using template #' . $template_id];
            }
            return [false, 'No email template selected.'];
            
        case 'send_sms':
            $template_id = (int)($config['template_id'] ?? 0);
            if ($template_id > 0) {
                db_execute(
                    'INSERT INTO sent_messages (message_type, template_id, client_id, recipient, status, provider, sent_by) VALUES ("sms", ?, ?, ?, "pending", "auto", NULL)',
                    [$template_id, $context['client_id'] ?? null, $context['phone'] ?? '']
                );
                return [true, 'SMS scheduled for template #' . $template_id];
            }
            return [false, 'No SMS template selected.'];
            
        case 'start_workflow':
            $workflow_id = (int)($config['workflow_id'] ?? 0);
            $client_id = $context['client_id'] ?? 0;
            
            if ($workflow_id > 0 && $client_id > 0) {
                db_execute(
                    'INSERT INTO workflow_instances (workflow_id, client_id, status, progress_value, started_by) VALUES (?, ?, "not_started", 0, NULL)',
                    [$workflow_id, $client_id]
                );
                return [true, 'Workflow #' . $workflow_id . ' started for client #' . $client_id];
            }
            return [false, 'Workflow or client not specified.'];
            
        default:
            return [false, 'Unknown action type: ' . $rule['action_type']];
    }
}

/**
 * Get column names of a table
 *
 * @param string $table
 * @return array
 */
function db_columns(string $table): array {
    $cols = db_query("SHOW COLUMNS FROM {$table}");
    return array_column($cols, 'Field');
}
?>
<?php include __DIR__ . '/header.php'; ?>

<?php if ($edit_rule): ?>
    <!-- Edit Rule Form -->
    <div class="page-title">
        <h1><i class="bi bi-gear"></i> Edit Automation Rule</h1>
        <a href="automation.php" class="btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
    
    <div class="card">
        <div class="card-header">Rule Configuration</div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_rule">
                <input type="hidden" name="rule_id" value="<?= $edit_rule['id'] ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label-custom">Rule Name *</label>
                        <input type="text" name="name" class="form-control-custom" value="<?= e($edit_rule['name']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Active</label>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" <?= $edit_rule['is_active'] ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Trigger Module *</label>
                        <select name="trigger_module" class="form-control-custom">
                            <?php foreach (['clients', 'leads', 'projects', 'invoices', 'documents', 'services'] as $mod): ?>
                                <option value="<?= $mod ?>" <?= $edit_rule['trigger_module'] === $mod ? 'selected' : '' ?>><?= e(ucfirst($mod)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Trigger Event *</label>
                        <select name="trigger_event" class="form-control-custom">
                            <?php foreach (['create', 'update', 'status_change', 'due_soon', 'overdue'] as $evt): ?>
                                <option value="<?= $evt ?>" <?= $edit_rule['trigger_event'] === $evt ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $evt))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Action Type *</label>
                        <select name="action_type" class="form-control-custom action-type-select">
                            <?php foreach (['notify', 'create_task', 'update_status', 'send_email', 'send_sms', 'start_workflow'] as $at): ?>
                                <option value="<?= $at ?>" <?= $edit_rule['action_type'] === $at ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $at))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Trigger Field</label>
                        <input type="text" name="trigger_field" class="form-control-custom" value="<?= e($edit_rule['trigger_field'] ?? '') ?>" placeholder="e.g. status">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Trigger Value</label>
                        <input type="text" name="trigger_value" class="form-control-custom" value="<?= e($edit_rule['trigger_value'] ?? '') ?>" placeholder="e.g. qualified">
                    </div>
                </div>
                
                <hr>
                <h6>Action Configuration</h6>
                <?php $cfg = $edit_rule['action_config_arr'] ?? []; ?>
                
                <div class="config-block" id="notifyConfig" style="display:<?= $edit_rule['action_type'] === 'notify' ? 'block' : 'none' ?>;">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label-custom">Notify User</label>
                            <select name="notify_user_id" class="form-control-custom">
                                <option value="0">Select User</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($cfg['user_id'] ?? 0) == $u['id'] ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Message</label>
                            <input type="text" name="notify_message" class="form-control-custom" value="<?= e($cfg['message'] ?? '') ?>" placeholder="Notification message">
                        </div>
                    </div>
                </div>
                
                <div class="config-block" id="taskConfig" style="display:<?= $edit_rule['action_type'] === 'create_task' ? 'block' : 'none' ?>;">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label-custom">Task Title</label>
                            <input type="text" name="task_title" class="form-control-custom" value="<?= e($cfg['title'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Assignee</label>
                            <select name="task_assignee_id" class="form-control-custom">
                                <option value="0">Select User</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($cfg['assignee_id'] ?? 0) == $u['id'] ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Due In (days)</label>
                            <input type="number" name="task_due_in_days" class="form-control-custom" value="<?= e($cfg['due_in_days'] ?? 0) ?>" min="0">
                        </div>
                    </div>
                </div>
                
                <div class="config-block" id="statusConfig" style="display:<?= $edit_rule['action_type'] === 'update_status' ? 'block' : 'none' ?>;">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label-custom">Module</label>
                            <select name="update_status_module" class="form-control-custom">
                                <?php foreach (['clients', 'projects', 'invoices'] as $mod): ?>
                                    <option value="<?= $mod ?>" <?= ($cfg['module'] ?? '') === $mod ? 'selected' : '' ?>><?= e(ucfirst($mod)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">New Status</label>
                            <input type="text" name="update_status_value" class="form-control-custom" value="<?= e($cfg['status'] ?? '') ?>" placeholder="e.g. in_progress">
                        </div>
                    </div>
                </div>
                
                <div class="config-block" id="emailConfig" style="display:<?= $edit_rule['action_type'] === 'send_email' ? 'block' : 'none' ?>;">
                    <div class="row">
                        <div class="col-md-12">
                            <label class="form-label-custom">Email Template</label>
                            <select name="email_template_id" class="form-control-custom">
                                <option value="0">Select Template</option>
                                <?php foreach ($email_templates as $et): ?>
                                    <option value="<?= $et['id'] ?>" <?= ($cfg['template_id'] ?? 0) == $et['id'] ? 'selected' : '' ?>><?= e($et['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="config-block" id="smsConfig" style="display:<?= $edit_rule['action_type'] === 'send_sms' ? 'block' : 'none' ?>;">
                    <div class="row">
                        <div class="col-md-12">
                            <label class="form-label-custom">SMS Template</label>
                            <select name="sms_template_id" class="form-control-custom">
                                <option value="0">Select Template</option>
                                <?php foreach ($sms_templates as $st): ?>
                                    <option value="<?= $st['id'] ?>" <?= ($cfg['template_id'] ?? 0) == $st['id'] ? 'selected' : '' ?>><?= e($st['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="config-block" id="workflowConfig" style="display:<?= $edit_rule['action_type'] === 'start_workflow' ? 'block' : 'none' ?>;">
                    <div class="row">
                        <div class="col-md-12">
                            <label class="form-label-custom">Workflow</label>
                            <select name="workflow_id" class="form-control-custom">
                                <option value="0">Select Workflow</option>
                                <?php foreach ($workflows as $wf): ?>
                                    <option value="<?= $wf['id'] ?>" <?= ($cfg['workflow_id'] ?? 0) == $wf['id'] ? 'selected' : '' ?>><?= e($wf['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Update Rule</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Automation List -->
    <div class="page-title">
        <h1><i class="bi bi-gear"></i> Automation</h1>
        <button class="btn-primary-custom" onclick="toggleNewRule()"><i class="bi bi-plus"></i> New Rule</button>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-gear"></i></div>
                <div class="stat-value"><?= $total_rules ?></div>
                <div class="stat-label">Total Rules</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-toggle-on"></i></div>
                <div class="stat-value"><?= $active_rules ?></div>
                <div class="stat-label">Active Rules</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-check-circle"></i></div>
                <div class="stat-value"><?= $completed_logs ?></div>
                <div class="stat-label">Completed Runs</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fee2e2;color:#991b1b;"><i class="bi bi-x-circle"></i></div>
                <div class="stat-value"><?= $failed_logs ?></div>
                <div class="stat-label">Failed Runs</div>
            </div>
        </div>
    </div>
    
    <!-- New Rule Form -->
    <div class="card" id="ruleForm" style="display:none;">
        <div class="card-header">Create Automation Rule</div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create_rule">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label-custom">Rule Name *</label>
                        <input type="text" name="name" class="form-control-custom" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Trigger Module *</label>
                        <select name="trigger_module" class="form-control-custom">
                            <?php foreach (['clients','leads','projects','invoices','documents','services'] as $mod): ?>
                                <option value="<?= $mod ?>"><?= e(ucfirst($mod)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Trigger Event *</label>
                        <select name="trigger_event" class="form-control-custom">
                            <?php foreach (['create', 'update', 'status_change', 'due_soon', 'overdue'] as $evt): ?>
                                <option value="<?= $evt ?>"><?= e(ucwords(str_replace('_', ' ', $evt))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Trigger Field</label>
                        <input type="text" name="trigger_field" class="form-control-custom" placeholder="e.g. status">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Trigger Value</label>
                        <input type="text" name="trigger_value" class="form-control-custom" placeholder="e.g. qualified">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Action Type *</label>
                        <select name="action_type" class="form-control-custom action-type-select">
                            <?php foreach (['notify','create_task','update_status','send_email','send_sms','start_workflow'] as $at): ?>
                                <option value="<?= $at ?>"><?= e(ucwords(str_replace('_', ' ', $at))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <hr>
                
                <!-- Action config blocks -->
                <div class="config-block" id="notifyConfig" style="display:none;">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label-custom">Notify User</label>
                            <select name="notify_user_id" class="form-control-custom">
                                <option value="0">Select</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Message</label>
                            <input type="text" name="notify_message" class="form-control-custom">
                        </div>
                    </div>
                </div>
                <div class="config-block" id="taskConfig" style="display:none;">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label-custom">Task Title</label>
                            <input type="text" name="task_title" class="form-control-custom">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Assignee</label>
                            <select name="task_assignee_id" class="form-control-custom">
                                <option value="0">Select</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Due In (days)</label>
                            <input type="number" name="task_due_in_days" class="form-control-custom" min="0" value="7">
                        </div>
                    </div>
                </div>
                <div class="config-block" id="statusConfig" style="display:none;">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label-custom">Module</label>
                            <select name="update_status_module" class="form-control-custom">
                                <?php foreach (['clients', 'projects', 'invoices'] as $mod): ?>
                                    <option value="<?= $mod ?>"><?= e(ucfirst($mod)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">New Status</label>
                            <input type="text" name="update_status_value" class="form-control-custom" placeholder="e.g. in_progress">
                        </div>
                    </div>
                </div>
                <div class="config-block" id="emailConfig" style="display:none;">
                    <div class="row">
                        <div class="col-md-12">
                            <label class="form-label-custom">Email Template</label>
                            <select name="email_template_id" class="form-control-custom">
                                <option value="0">Select Template</option>
                                <?php foreach ($email_templates as $et): ?>
                                    <option value="<?= $et['id'] ?>"><?= e($et['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="config-block" id="smsConfig" style="display:none;">
                    <div class="row">
                        <div class="col-md-12">
                            <label class="form-label-custom">SMS Template</label>
                            <select name="sms_template_id" class="form-control-custom">
                                <option value="0">Select Template</option>
                                <?php foreach ($sms_templates as $st): ?>
                                    <option value="<?= $st['id'] ?>"><?= e($st['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="config-block" id="workflowConfig" style="display:none;">
                    <div class="row">
                        <div class="col-md-12">
                            <label class="form-label-custom">Workflow</label>
                            <select name="workflow_id" class="form-control-custom">
                                <option value="0">Select Workflow</option>
                                <?php foreach ($workflows as $wf): ?>
                                    <option value="<?= $wf['id'] ?>"><?= e($wf['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Create Rule</button>
            </form>
        </div>
    </div>
    
    <!-- Rules List -->
    <div class="card">
        <div class="card-header">Automation Rules (<?= $total_rules ?>)</div>
        <div class="card-body">
            <?php if (empty($rules)): ?>
                <p class="text-muted">No automation rules yet.</p>
            <?php else: ?>
                <table class="table-custom">
                    <thead><tr><th>Name</th><th>Trigger</th><th>Action</th><th>Status</th><th>Last Executed</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($rules as $rule): ?>
                        <tr>
                            <td><strong><?= e($rule['name']) ?></strong></td>
                            <td>
                                <?= e(ucfirst($rule['trigger_module'])) ?> &rarr; <?= e($rule['trigger_event']) ?>
                                <?php if ($rule['trigger_field']): ?>
                                    (<?= e($rule['trigger_field']) ?>=<?= e($rule['trigger_value']) ?>)
                                <?php endif; ?>
                            </td>
                            <td><?= e(ucwords(str_replace('_', ' ', $rule['action_type']))) ?></td>
                            <td><?= $rule['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                            <td>
                                <?php $last_log_row = db_query('SELECT executed_at FROM automation_logs WHERE rule_id = ? ORDER BY executed_at DESC LIMIT 1', [$rule['id']]); ?>
                                <?= !empty($last_log_row) ? e(time_ago($last_log_row[0]['executed_at'])) : '-' ?>
                            </td>
                            <td>
                                <a href="automation.php?edit=<?= $rule['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle_rule">
                                    <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                                    <button type="submit" class="btn-secondary-custom btn-sm" title="<?= $rule['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                        <i class="bi bi-<?= $rule['is_active'] ? 'toggle-off' : 'toggle-on' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this rule?')">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_rule">
                                    <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                                    <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="test_rule">
                                    <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                                    <button type="submit" class="btn-secondary-custom btn-sm" title="Test rule"><i class="bi bi-play"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Execution Log -->
    <div class="card">
        <div class="card-header">Execution Log</div>
        <div class="card-body">
            <?php if (empty($recent_logs)): ?>
                <p class="text-muted">No automation logs yet.</p>
            <?php else: ?>
                <table class="table-custom">
                    <thead><tr><th>Time</th><th>Rule</th><th>Module</th><th>Event</th><th>Action</th><th>Result</th><th>Message</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?= e(time_ago($log['executed_at'])) ?></td>
                            <td><?= e($log['rule_name'] ?? 'Deleted rule') ?></td>
                            <td><?= e(ucfirst($log['trigger_module'])) ?></td>
                            <td><?= e($log['trigger_event']) ?></td>
                            <td><?= e(ucwords(str_replace('_', ' ', $log['action_type']))) ?></td>
                            <td>
                                <?= $log['action_result'] === 'completed' ? '<span class="badge badge-success">Completed</span>' : ($log['action_result'] === 'failed' ? '<span class="badge badge-danger">Failed</span>' : '<span class="badge badge-warning">Skipped</span>') ?>
                            </td>
                            <td><?= e(truncate($log['message'] ?? '', 50)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleNewRule() {
    const form = document.getElementById('ruleForm');
    if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// Show/hide config based on action type
const configMap = {
    'notify': 'notifyConfig',
    'create_task': 'taskConfig',
    'update_status': 'statusConfig',
    'send_email': 'emailConfig',
    'send_sms': 'smsConfig',
    'start_workflow': 'workflowConfig'
};

function updateConfigVisibility(select) {
    const at = select.value;
    Object.keys(configMap).forEach(key => {
        const el = document.getElementById(configMap[key]);
        if (el) el.style.display = (key === at) ? 'block' : 'none';
    });
}

document.querySelectorAll('.action-type-select').forEach(select => {
    select.addEventListener('change', function() { updateConfigVisibility(this); });
    updateConfigVisibility(select);
});
</script>

<?php include __DIR__ . '/footer.php'; ?>