<?php
/**
 * GRAVITY CRM - Leads Management
 * Phase 1: Foundation
 * 
 * Yahan leads create/edit/delete hote hain.
 * Lead ko client mein convert bhi kiya ja sakta hai.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('leads', 'view');

$user = current_user();
$page_title = 'Leads';
$active_page = 'leads';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create' || $action === 'update') {
        require_permission('leads', 'create');
        
        $lead_id = (int)post('lead_id', 0);
        $first_name = sanitize(post('first_name'));
        $last_name = sanitize(post('last_name'));
        $email = sanitize(post('email'));
        $phone = sanitize(post('phone'));
        $mobile = sanitize(post('mobile'));
        $source = sanitize(post('source'));
        $status = sanitize(post('status', 'new'));
        $assigned_user_id = (int)post('assigned_user_id', 0);
        $notes = sanitize(post('notes'));
        
        $errors = [];
        validate_required($first_name, 'First name', $errors);
        validate_required($last_name, 'Last name', $errors);
        
        if ($email !== '' && !validate_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        if (empty($errors)) {
            if ($action === 'create') {
                db_execute(
                    'INSERT INTO leads (first_name, last_name, email, phone, mobile, source, status, assigned_user_id, notes, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$first_name, $last_name, $email, $phone, $mobile, $source, $status, $assigned_user_id ?: null, $notes, $user['id']]
                );
                $lead_id = db_last_id();
                log_activity('leads', 'create', $lead_id, 'Created lead: ' . $first_name . ' ' . $last_name);
                bus_emit(EVT_LEAD_CREATED, [], ['source' => 'lead', 'source_id' => $lead_id]);
                flash('success', 'Lead created successfully.');
            } else {
                $existing = db_row('SELECT * FROM leads WHERE id = ?', [$lead_id]);
                if (!$existing) {
                    flash('error', 'Lead not found.');
                    redirect('leads.php');
                }
                
                db_execute(
                    'UPDATE leads SET first_name = ?, last_name = ?, email = ?, phone = ?, mobile = ?, source = ?, status = ?, assigned_user_id = ?, notes = ? WHERE id = ?',
                    [$first_name, $last_name, $email, $phone, $mobile, $source, $status, $assigned_user_id ?: null, $notes, $lead_id]
                );
                log_activity('leads', 'update', $lead_id, 'Updated lead: ' . $first_name . ' ' . $last_name);
                flash('success', 'Lead updated successfully.');
            }
            redirect('leads.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete') {
        require_permission('leads', 'delete');
        $lead_id = (int)post('lead_id', 0);
        
        $existing = db_row('SELECT * FROM leads WHERE id = ?', [$lead_id]);
        if (!$existing) {
            flash('error', 'Lead not found.');
            redirect('leads.php');
        }
        
        db_execute('DELETE FROM leads WHERE id = ?', [$lead_id]);
        log_activity('leads', 'delete', $lead_id, 'Deleted lead: ' . $existing['first_name'] . ' ' . $existing['last_name']);
        flash('success', 'Lead deleted successfully.');
        redirect('leads.php');
    }
    
    if ($action === 'convert') {
        require_permission('leads', 'edit');
        $lead_id = (int)post('lead_id', 0);
        
        $lead = db_row('SELECT * FROM leads WHERE id = ?', [$lead_id]);
        if (!$lead) {
            flash('error', 'Lead not found.');
            redirect('leads.php');
        }
        
        // Client create karo
        db_execute(
            'INSERT INTO clients (first_name, last_name, email, phone, mobile, source, status, assigned_user_id, notes, created_by) 
             VALUES (?, ?, ?, ?, ?, ?, "active", ?, ?, ?)',
            [$lead['first_name'], $lead['last_name'], $lead['email'], $lead['phone'], $lead['mobile'], $lead['source'], $lead['assigned_user_id'], $lead['notes'], $user['id']]
        );
        $client_id = db_last_id();
        
        // Lead ko converted mark karo
        db_execute('UPDATE leads SET status = "converted", converted_client_id = ? WHERE id = ?', [$client_id, $lead_id]);
        
        log_activity('leads', 'convert', $lead_id, 'Converted lead to client: ' . $lead['first_name'] . ' ' . $lead['last_name']);
        log_activity('clients', 'create', $client_id, 'Client created from lead: ' . $lead['first_name'] . ' ' . $lead['last_name']);
        bus_emit(EVT_LEAD_CONVERTED, [], ['source' => 'lead', 'source_id' => $lead_id]);
        bus_emit(EVT_CLIENT_CREATED, [], ['source' => 'client', 'source_id' => $client_id]);
        flash('success', 'Lead converted to client successfully.');
        redirect('clients.php?view=' . $client_id);
    }
}

// Get users for assignment
$users = db_query('SELECT id, first_name, last_name FROM users WHERE status = "active" ORDER BY first_name');

// Build query with filters
$where = '1=1';
$params = [];

$search = trim(get('search', ''));
if ($search !== '') {
    $where .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
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

$total = (int)db_value("SELECT COUNT(*) FROM leads WHERE {$where}", $params);
$leads = db_query(
    "SELECT l.*, u.first_name AS assigned_first, u.last_name AS assigned_last 
     FROM leads l 
     LEFT JOIN users u ON l.assigned_user_id = u.id 
     WHERE {$where} 
     ORDER BY l.created_at DESC 
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);
$total_pages = max(1, ceil($total / $per_page));

// Stats
$total_leads = (int)db_value('SELECT COUNT(*) FROM leads');
$new_leads = (int)db_value('SELECT COUNT(*) FROM leads WHERE status = "new"');
$converted_leads = (int)db_value('SELECT COUNT(*) FROM leads WHERE status = "converted"');

// Edit lead
$edit_lead = null;
if (get('edit')) {
    $edit_lead = db_row('SELECT * FROM leads WHERE id = ?', [(int)get('edit')]);
}
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-lightning"></i> Leads</h1>
    <button class="btn-primary-custom" onclick="toggleNewLead()"><i class="bi bi-plus"></i> New Lead</button>
</div>

<!-- Stats -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-lightning"></i></div>
            <div class="stat-value"><?= $total_leads ?></div>
            <div class="stat-label">Total Leads</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-star"></i></div>
            <div class="stat-value"><?= $new_leads ?></div>
            <div class="stat-label">New Leads</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-person-check"></i></div>
            <div class="stat-value"><?= $converted_leads ?></div>
            <div class="stat-label">Converted</div>
        </div>
    </div>
</div>

<!-- New/Edit Lead Form -->
<div class="card" id="leadForm" style="display:<?= $edit_lead ? 'block' : 'none' ?>;">
    <div class="card-header">
        <?= $edit_lead ? 'Edit Lead: ' . e($edit_lead['first_name'] . ' ' . $edit_lead['last_name']) : 'Create New Lead' ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $edit_lead ? 'update' : 'create' ?>">
            <input type="hidden" name="lead_id" value="<?= $edit_lead['id'] ?? 0 ?>">
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">First Name *</label>
                    <input type="text" name="first_name" class="form-control-custom" value="<?= e($edit_lead['first_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Last Name *</label>
                    <input type="text" name="last_name" class="form-control-custom" value="<?= e($edit_lead['last_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Source</label>
                    <input type="text" name="source" class="form-control-custom" value="<?= e($edit_lead['source'] ?? '') ?>" placeholder="Referral, Website, etc.">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Email</label>
                    <input type="email" name="email" class="form-control-custom" value="<?= e($edit_lead['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Phone</label>
                    <input type="text" name="phone" class="form-control-custom" value="<?= e($edit_lead['phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Mobile</label>
                    <input type="text" name="mobile" class="form-control-custom" value="<?= e($edit_lead['mobile'] ?? '') ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Status</label>
                    <select name="status" class="form-control-custom">
                        <option value="new" <?= ($edit_lead['status'] ?? '') === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="contacted" <?= ($edit_lead['status'] ?? '') === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                        <option value="qualified" <?= ($edit_lead['status'] ?? '') === 'qualified' ? 'selected' : '' ?>>Qualified</option>
                        <option value="converted" <?= ($edit_lead['status'] ?? '') === 'converted' ? 'selected' : '' ?>>Converted</option>
                        <option value="lost" <?= ($edit_lead['status'] ?? '') === 'lost' ? 'selected' : '' ?>>Lost</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Assigned To</label>
                    <select name="assigned_user_id" class="form-control-custom">
                        <option value="0">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($edit_lead['assigned_user_id'] ?? 0) == $u['id'] ? 'selected' : '' ?>>
                                <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <label class="form-label-custom">Notes</label>
            <textarea name="notes" class="form-control-custom" rows="3"><?= e($edit_lead['notes'] ?? '') ?></textarea>
            
            <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> <?= $edit_lead ? 'Update' : 'Create' ?> Lead</button>
            <button type="button" class="btn-secondary-custom" onclick="toggleNewLead()">Cancel</button>
        </form>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control-custom" placeholder="Search leads..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-control-custom">
                    <option value="">All Status</option>
                    <option value="new" <?= $status_filter === 'new' ? 'selected' : '' ?>>New</option>
                    <option value="contacted" <?= $status_filter === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                    <option value="qualified" <?= $status_filter === 'qualified' ? 'selected' : '' ?>>Qualified</option>
                    <option value="converted" <?= $status_filter === 'converted' ? 'selected' : '' ?>>Converted</option>
                    <option value="lost" <?= $status_filter === 'lost' ? 'selected' : '' ?>>Lost</option>
                </select>
            </div>
            <div class="col-md-5">
                <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Filter</button>
                <a href="leads.php" class="btn-secondary-custom">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Leads Table -->
<div class="card">
    <div class="card-header">Leads (<?= $total ?>)</div>
    <div class="card-body">
        <?php if (empty($leads)): ?>
            <p class="text-muted">No leads found.</p>
        <?php else: ?>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td><strong><?= e($lead['first_name'] . ' ' . $lead['last_name']) ?></strong></td>
                            <td><?= e($lead['email']) ?></td>
                            <td><?= e($lead['phone']) ?></td>
                            <td><?= e($lead['source'] ?: '-') ?></td>
                            <td><?= status_badge($lead['status']) ?></td>
                            <td><?= e($lead['assigned_first'] ? $lead['assigned_first'] . ' ' . $lead['assigned_last'] : '-') ?></td>
                            <td><?= e(format_date($lead['created_at'])) ?></td>
                            <td>
                                <a href="leads.php?edit=<?= $lead['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                                <?php if ($lead['status'] !== 'converted'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Convert this lead to a client?')">
                                        <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="convert">
                                        <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                                        <button type="submit" class="btn-primary-custom btn-sm"><i class="bi bi-person-check"></i></button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this lead?')">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                                    <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?= pagination_links($total_pages, $page, 'leads.php?' . http_build_query(array_filter(['search' => $search, 'status' => $status_filter]))) ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleNewLead() {
    const form = document.getElementById('leadForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block' && !form.querySelector('input[name="lead_id"]').value) {
        form.querySelectorAll('input[type="text"], input[type="email"], textarea').forEach(i => i.value = '');
        form.querySelector('select[name="status"]').value = 'new';
        form.querySelector('select[name="assigned_user_id"]').value = '0';
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>


