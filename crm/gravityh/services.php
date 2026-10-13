<?php
/**
 * GRAVITY CRM - Services Management
 * Phase 2: Core CRM Modules
 * 
 * Yahan services create/edit/delete hote hain.
 * Client services assignment bhi yahan se hoti hai.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/core/service_engine.php';
require_login();
require_permission('services', 'view');

$user = current_user();
$page_title = 'Services';
$active_page = 'services';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create' || $action === 'update') {
        require_permission('services', 'create');
        
        $service_id = (int)post('service_id', 0);
        $name = sanitize(post('name'));
        $description = sanitize(post('description'));
        $service_type = sanitize(post('service_type'));
        $category = sanitize(post('category'));
        $is_recurring = post('is_recurring') ? 1 : 0;
        $billing_type = sanitize(post('billing_type', 'hourly'));
        $hourly_rate = (float)post('hourly_rate', 0);
        $fixed_price = (float)post('fixed_price', 0);
        $estimated_hours = (float)post('estimated_hours', 0);
        $is_taxable = post('is_taxable') ? 1 : 0;
        $tax_rate = (float)post('tax_rate', 0);
        $status = sanitize(post('status', 'active'));
        
        $errors = [];
        validate_required($name, 'Service name', $errors);
        
        if (empty($errors)) {
            if ($action === 'create') {
                db_execute(
                    'INSERT INTO services (name, description, service_type, category, is_recurring, billing_type, hourly_rate, fixed_price, estimated_hours, is_taxable, tax_rate, status, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$name, $description, $service_type, $category, $is_recurring, $billing_type, $hourly_rate ?: null, $fixed_price ?: null, $estimated_hours ?: null, $is_taxable, $tax_rate ?: null, $status, $user['id']]
                );
                $service_id = db_last_id();
                log_activity('services', 'create', $service_id, 'Created service: ' . $name);
                flash('success', 'Service created successfully.');
            } else {
                $existing = db_row('SELECT * FROM services WHERE id = ?', [$service_id]);
                if (!$existing) {
                    flash('error', 'Service not found.');
                    redirect('services.php');
                }
                
                db_execute(
                    'UPDATE services SET name = ?, description = ?, service_type = ?, category = ?, is_recurring = ?, billing_type = ?, hourly_rate = ?, fixed_price = ?, estimated_hours = ?, is_taxable = ?, tax_rate = ?, status = ? WHERE id = ?',
                    [$name, $description, $service_type, $category, $is_recurring, $billing_type, $hourly_rate ?: null, $fixed_price ?: null, $estimated_hours ?: null, $is_taxable, $tax_rate ?: null, $status, $service_id]
                );
                log_activity('services', 'update', $service_id, 'Updated service: ' . $name);
                flash('success', 'Service updated successfully.');
            }
            redirect('services.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete') {
        require_permission('services', 'delete');
        $service_id = (int)post('service_id', 0);
        
        $existing = db_row('SELECT * FROM services WHERE id = ?', [$service_id]);
        if (!$existing) {
            flash('error', 'Service not found.');
            redirect('services.php');
        }
        
        // Soft delete - inactive
        db_execute('UPDATE services SET status = "inactive" WHERE id = ?', [$service_id]);
        log_activity('services', 'delete', $service_id, 'Deactivated service: ' . $existing['name']);
        flash('success', 'Service deactivated successfully.');
        redirect('services.php');
    }
    
    if ($action === 'assign_client') {
        require_permission('services', 'edit');
        $client_id = (int)post('client_id', 0);
        $service_id = (int)post('service_id', 0);
        $custom_rate = (float)post('custom_rate', 0);
        $status = sanitize(post('status', 'active'));
        $start_date = sanitize(post('start_date'));
        $end_date = sanitize(post('end_date'));
        $notes = sanitize(post('notes'));
        
        if ($client_id <= 0 || $service_id <= 0) {
            flash('error', 'Please select both client and service.');
            redirect('services.php');
        }
        
        $existing = db_row('SELECT id FROM client_services WHERE client_id = ? AND service_id = ?', [$client_id, $service_id]);
        if ($existing) {
            db_execute(
                'UPDATE client_services SET status = ?, custom_rate = ?, start_date = ?, end_date = ?, notes = ? WHERE id = ?',
                [$status, $custom_rate ?: null, $start_date ?: null, $end_date ?: null, $notes, $existing['id']]
            );
            log_activity('services', 'assign', $existing['id'], 'Updated client-service assignment');
            flash('success', 'Client service assignment updated.');
        } else {
            // SPS 2040 §7 pipeline: freeze version -> requirements -> workflow -> deadlines -> SERVICE_STARTED
            $result = svc_assign_service_to_client($client_id, $service_id, [
                'custom_rate' => $custom_rate ?: null,
                'start_date'  => $start_date ?: null,
                'end_date'    => $end_date ?: null,
                'notes'       => $notes,
                'status'      => $status,
                'user_id'     => $user['id'],
            ]);
            log_activity('services', 'assign', $result['client_services_id'], 'Assigned service to client');
            flash('success', 'Service assigned to client.'
                . (!empty($result['workflow_instance_id']) ? ' Workflow #' . $result['workflow_instance_id'] . ' started.' : '')
                . ($result['requirements_created'] > 0 ? ' ' . $result['requirements_created'] . ' requirement(s) generated.' : '')
                . ($result['deadlines_created'] > 0 ? ' ' . $result['deadlines_created'] . ' deadline(s) scheduled.' : ''));
        }
        redirect('services.php');
    }
    
    if ($action === 'unassign_client') {
        require_permission('services', 'edit');
        $assignment_id = (int)post('assignment_id', 0);
        db_execute('DELETE FROM client_services WHERE id = ?', [$assignment_id]);
        log_activity('services', 'unassign', $assignment_id, 'Unassigned service from client');
        flash('success', 'Service assignment removed.');
        redirect('services.php');
    }
}

// Filters
$where = '1=1';
$params = [];

$search = trim(get('search', ''));
if ($search !== '') {
    $where .= ' AND (name LIKE ? OR service_type LIKE ? OR category LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$type_filter = get('type', '');
if ($type_filter !== '') {
    $where .= ' AND service_type = ?';
    $params[] = $type_filter;
}

$status_filter = get('status', '');
if ($status_filter !== '') {
    $where .= ' AND status = ?';
    $params[] = $status_filter;
}

// Pagination
$per_page = (int)get_setting('items_per_page', 20);
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $per_page;

$total = (int)db_value("SELECT COUNT(*) FROM services WHERE {$where}", $params);
$services = db_query(
    "SELECT * FROM services WHERE {$where} ORDER BY created_at DESC LIMIT {$per_page} OFFSET {$offset}",
    $params
);
$total_pages = max(1, ceil($total / $per_page));

// Stats
$total_services = (int)db_value('SELECT COUNT(*) FROM services');
$active_services = (int)db_value('SELECT COUNT(*) FROM services WHERE status = "active"');
$recurring_services = (int)db_value('SELECT COUNT(*) FROM services WHERE is_recurring = 1');

// Dropdown data
$clients = db_query('SELECT id, first_name, last_name FROM clients WHERE status = "active" ORDER BY first_name');
$all_services = db_query('SELECT id, name FROM services WHERE status = "active" ORDER BY name');
$service_types = db_query('SELECT DISTINCT service_type FROM services WHERE service_type IS NOT NULL AND service_type != "" ORDER BY service_type');

// Edit service
$edit_service = null;
if (get('edit')) {
    $edit_service = db_row('SELECT * FROM services WHERE id = ?', [(int)get('edit')]);
}

// View service
$view_service = null;
if (get('view')) {
    $view_service = db_row(
        'SELECT s.*, u.first_name AS created_first, u.last_name AS created_last 
         FROM services s 
         LEFT JOIN users u ON s.created_by = u.id 
         WHERE s.id = ?',
        [(int)get('view')]
    );
    if ($view_service) {
        log_activity('services', 'view', $view_service['id'], 'Viewed service: ' . $view_service['name']);
        $view_assignments = db_query(
            'SELECT cs.*, c.first_name, c.last_name, c.email 
             FROM client_services cs 
             JOIN clients c ON cs.client_id = c.id 
             WHERE cs.service_id = ?',
            [$view_service['id']]
        );
    }
}
?>
<?php include __DIR__ . '/header.php'; ?>

<?php if ($view_service): ?>
    <!-- Service Profile View -->
    <div class="page-title">
        <h1><i class="bi bi-briefcase"></i> <?= e($view_service['name']) ?></h1>
        <div>
            <a href="services.php?edit=<?= $view_service['id'] ?>" class="btn-secondary-custom"><i class="bi bi-pencil"></i> Edit</a>
            <a href="services.php" class="btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Service Information</div>
                <div class="card-body">
                    <p><strong>Type:</strong> <?= e($view_service['service_type'] ?: 'Not set') ?></p>
                    <p><strong>Category:</strong> <?= e($view_service['category'] ?: 'Not set') ?></p>
                    <p><strong>Billing:</strong> <?= e(ucfirst($view_service['billing_type'])) ?></p>
                    <p><strong>Hourly Rate:</strong> <?= $view_service['hourly_rate'] ? format_currency($view_service['hourly_rate']) : 'N/A' ?></p>
                    <p><strong>Fixed Price:</strong> <?= $view_service['fixed_price'] ? format_currency($view_service['fixed_price']) : 'N/A' ?></p>
                    <p><strong>Recurring:</strong> <?= $view_service['is_recurring'] ? 'Yes' : 'No' ?></p>
                    <p><strong>Taxable:</strong> <?= $view_service['is_taxable'] ? 'Yes (' . e($view_service['tax_rate']) . '%)' : 'No' ?></p>
                    <p><strong>Status:</strong> <?= status_badge($view_service['status']) ?></p>
                    <p><strong>Description:</strong> <?= e($view_service['description'] ?: 'No description') ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Clients Using This Service (<?= count($view_assignments) ?>)</div>
                <div class="card-body">
                    <?php if (empty($view_assignments)): ?>
                        <p class="text-muted">No clients currently using this service.</p>
                    <?php else: ?>
                        <table class="table-custom">
                            <thead><tr><th>Client</th><th>Status</th><th>Custom Rate</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($view_assignments as $assign): ?>
                                    <tr>
                                        <td><a href="clients.php?view=<?= $assign['client_id'] ?>"><?= e($assign['first_name'] . ' ' . $assign['last_name']) ?></a></td>
                                        <td><?= status_badge($assign['status']) ?></td>
                                        <td><?= $assign['custom_rate'] ? format_currency($assign['custom_rate']) : 'Default' ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this service from client?')">
                                                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="unassign_client">
                                                <input type="hidden" name="assignment_id" value="<?= $assign['id'] ?>">
                                                <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-x"></i></button>
                                            </form>
                                        </td>
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
    <!-- Services List -->
    <div class="page-title">
        <h1><i class="bi bi-briefcase"></i> Services</h1>
        <button class="btn-primary-custom" onclick="toggleNewService()"><i class="bi bi-plus"></i> New Service</button>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-briefcase"></i></div>
                <div class="stat-value"><?= $total_services ?></div>
                <div class="stat-label">Total Services</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-check-circle"></i></div>
                <div class="stat-value"><?= $active_services ?></div>
                <div class="stat-label">Active Services</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-arrow-repeat"></i></div>
                <div class="stat-value"><?= $recurring_services ?></div>
                <div class="stat-label">Recurring Services</div>
            </div>
        </div>
    </div>
    
    <!-- New/Edit Service Form -->
    <div class="card" id="serviceForm" style="display:<?= $edit_service ? 'block' : 'none' ?>;">
        <div class="card-header">
            <?= $edit_service ? 'Edit Service: ' . e($edit_service['name']) : 'Create New Service' ?>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $edit_service ? 'update' : 'create' ?>">
                <input type="hidden" name="service_id" value="<?= $edit_service['id'] ?? 0 ?>">
                
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label-custom">Service Name *</label>
                        <input type="text" name="name" class="form-control-custom" value="<?= e($edit_service['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Status</label>
                        <select name="status" class="form-control-custom">
                            <option value="active" <?= ($edit_service['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($edit_service['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Service Type</label>
                        <select name="service_type" class="form-control-custom">
                            <option value="">Select Type</option>
                            <?php foreach (['Tax', 'Immigration', 'Bookkeeping', 'Consulting', 'Payroll', 'Audit', 'Legal', 'Other'] as $type): ?>
                                <option value="<?= $type ?>" <?= ($edit_service['service_type'] ?? '') === $type ? 'selected' : '' ?>><?= $type ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Category</label>
                        <input type="text" name="category" class="form-control-custom" value="<?= e($edit_service['category'] ?? '') ?>" placeholder="e.g. Individual Tax">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Billing Type</label>
                        <select name="billing_type" class="form-control-custom">
                            <option value="hourly" <?= ($edit_service['billing_type'] ?? '') === 'hourly' ? 'selected' : '' ?>>Hourly</option>
                            <option value="fixed" <?= ($edit_service['billing_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                            <option value="retainer" <?= ($edit_service['billing_type'] ?? '') === 'retainer' ? 'selected' : '' ?>>Retainer</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label-custom">Hourly Rate</label>
                        <input type="number" name="hourly_rate" step="0.01" class="form-control-custom" value="<?= e($edit_service['hourly_rate'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Fixed Price</label>
                        <input type="number" name="fixed_price" step="0.01" class="form-control-custom" value="<?= e($edit_service['fixed_price'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Est. Hours</label>
                        <input type="number" name="estimated_hours" step="0.25" class="form-control-custom" value="<?= e($edit_service['estimated_hours'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Tax Rate (%)</label>
                        <input type="number" name="tax_rate" step="0.01" class="form-control-custom" value="<?= e($edit_service['tax_rate'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label-custom">Description</label>
                        <textarea name="description" class="form-control-custom" rows="3"><?= e($edit_service['description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-4">
                            <input type="checkbox" name="is_recurring" value="1" class="form-check-input" <?= ($edit_service['is_recurring'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label">Recurring Service</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_taxable" value="1" class="form-check-input" <?= ($edit_service['is_taxable'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label">Taxable</label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> <?= $edit_service ? 'Update' : 'Create' ?> Service</button>
                <button type="button" class="btn-secondary-custom" onclick="toggleNewService()">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Assign Service to Client -->
    <?php if (user_can('services', 'edit')): ?>
    <div class="card">
        <div class="card-header"><i class="bi bi-link"></i> Assign Service to Client</div>
        <div class="card-body">
            <form method="POST" action="" class="row g-3">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="assign_client">
                <div class="col-md-3">
                    <label class="form-label-custom">Client *</label>
                    <select name="client_id" class="form-control-custom" required>
                        <option value="">Select Client</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>"><?= e($client['first_name'] . ' ' . $client['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-custom">Service *</label>
                    <select name="service_id" class="form-control-custom" required>
                        <option value="">Select Service</option>
                        <?php foreach ($all_services as $svc): ?>
                            <option value="<?= $svc['id'] ?>"><?= e($svc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-custom">Custom Rate</label>
                    <input type="number" name="custom_rate" step="0.01" class="form-control-custom" placeholder="Optional">
                </div>
                <div class="col-md-2">
                    <label class="form-label-custom">Status</label>
                    <select name="status" class="form-control-custom">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">Notes</label>
                    <input type="text" name="notes" class="form-control-custom" placeholder="Optional">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-link-45deg"></i> Assign</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="card">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control-custom" placeholder="Search services..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-control-custom">
                        <option value="">All Types</option>
                        <?php foreach (['tax', 'immigration', 'accounting', 'consulting', 'payroll', 'legal', 'other'] as $type): ?>
                            <option value="<?= $type ?>" <?= strtolower($type_filter) === $type ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-control-custom">
                        <option value="">All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Filter</button>
                    <a href="services.php" class="btn-secondary-custom">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Services Table -->
    <div class="card">
        <div class="card-header">Services (<?= $total ?>)</div>
        <div class="card-body">
            <?php if (empty($services)): ?>
                <p class="text-muted">No services found.</p>
            <?php else: ?>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Billing</th>
                            <th>Rate/Price</th>
                            <th>Recurring</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td><a href="services.php?view=<?= $service['id'] ?>"><strong><?= e($service['name']) ?></strong></a></td>
                                <td><?= e($service['service_type'] ?: '-') ?></td>
                                <td><?= e(ucfirst($service['billing_type'])) ?></td>
                                <td>
                                    <?= $service['hourly_rate'] ? format_currency($service['hourly_rate']) . '/hr' : ($service['fixed_price'] ? format_currency($service['fixed_price']) : '-') ?>
                                </td>
                                <td><?= $service['is_recurring'] ? '<i class="bi bi-check-circle text-success"></i>' : '-' ?></td>
                                <td><?= status_badge($service['status']) ?></td>
                                <td>
                                    <a href="services.php?view=<?= $service['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-eye"></i></a>
                                    <a href="services.php?edit=<?= $service['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                                    <?php if ($service['status'] !== 'inactive'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this service?')">
                                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                            <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-pause-circle"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?= pagination_links($total_pages, $page, 'services.php?' . http_build_query(array_filter(['search' => $search, 'type' => $type_filter, 'status' => $status_filter]))) ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleNewService() {
    const form = document.getElementById('serviceForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block' && document.querySelector('input[name="service_id"]').value === '0') {
        form.querySelectorAll('input[type="text"], input[type="number"], textarea').forEach(i => i.value = '');
        form.querySelectorAll('select').forEach(s => s.value = '');
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>