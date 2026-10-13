<?php
/**
 * GRAVITY CRM - Businesses Management
 * Phase 1: Foundation
 * 
 * Yahan businesses create/edit/delete hote hain.
 * Businesses clients se linked hote hain.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('businesses', 'view');

$user = current_user();
$page_title = 'Businesses';
$active_page = 'businesses';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create' || $action === 'update') {
        require_permission('businesses', 'create');
        
        $business_id = (int)post('business_id', 0);
        $client_id = (int)post('client_id', 0);
        $name = sanitize(post('name'));
        $legal_name = sanitize(post('legal_name'));
        $ein = sanitize(post('ein'));
        $business_type = sanitize(post('business_type'));
        $industry = sanitize(post('industry'));
        $address_line1 = sanitize(post('address_line1'));
        $address_line2 = sanitize(post('address_line2'));
        $city = sanitize(post('city'));
        $state = sanitize(post('state'));
        $zip = sanitize(post('zip'));
        $country = sanitize(post('country'));
        $phone = sanitize(post('phone'));
        $email = sanitize(post('email'));
        $website = sanitize(post('website'));
        $status = sanitize(post('status', 'active'));
        
        $errors = [];
        validate_required($name, 'Business name', $errors);
        
        if ($client_id <= 0) {
            $errors[] = 'Please select a client.';
        }
        
        if ($email !== '' && !validate_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        if (empty($errors)) {
            if ($action === 'create') {
                db_execute(
                    'INSERT INTO businesses (client_id, name, legal_name, ein, business_type, industry, address_line1, address_line2, city, state, zip, country, phone, email, website, status, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$client_id, $name, $legal_name, $ein, $business_type, $industry, $address_line1, $address_line2, $city, $state, $zip, $country, $phone, $email, $website, $status, $user['id']]
                );
                $business_id = db_last_id();
                log_activity('businesses', 'create', $business_id, 'Created business: ' . $name);
                flash('success', 'Business created successfully.');
            } else {
                $existing = db_row('SELECT * FROM businesses WHERE id = ?', [$business_id]);
                if (!$existing) {
                    flash('error', 'Business not found.');
                    redirect('businesses.php');
                }
                
                db_execute(
                    'UPDATE businesses SET client_id = ?, name = ?, legal_name = ?, ein = ?, business_type = ?, industry = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, zip = ?, country = ?, phone = ?, email = ?, website = ?, status = ? WHERE id = ?',
                    [$client_id, $name, $legal_name, $ein, $business_type, $industry, $address_line1, $address_line2, $city, $state, $zip, $country, $phone, $email, $website, $status, $business_id]
                );
                log_activity('businesses', 'update', $business_id, 'Updated business: ' . $name);
                flash('success', 'Business updated successfully.');
            }
            redirect('businesses.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete') {
        require_permission('businesses', 'delete');
        $business_id = (int)post('business_id', 0);
        
        $existing = db_row('SELECT * FROM businesses WHERE id = ?', [$business_id]);
        if (!$existing) {
            flash('error', 'Business not found.');
            redirect('businesses.php');
        }
        
        // Soft delete - archive
        db_execute('UPDATE businesses SET status = "archived" WHERE id = ?', [$business_id]);
        log_activity('businesses', 'delete', $business_id, 'Archived business: ' . $existing['name']);
        flash('success', 'Business archived successfully.');
        redirect('businesses.php');
    }
}

// Get clients for dropdown
$clients = db_query('SELECT id, first_name, last_name FROM clients WHERE status = "active" ORDER BY first_name');

// Build query with filters
$where = '1=1';
$params = [];

$search = trim(get('search', ''));
if ($search !== '') {
    $where .= ' AND (b.name LIKE ? OR b.legal_name LIKE ? OR b.ein LIKE ? OR b.email LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}

$client_filter = (int)get('client', 0);
if ($client_filter > 0) {
    $where .= ' AND b.client_id = ?';
    $params[] = $client_filter;
}

$status_filter = get('status', '');
if ($status_filter !== '') {
    $where .= ' AND b.status = ?';
    $params[] = $status_filter;
}

// Pagination
$per_page = (int)get_setting('items_per_page', 20);
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $per_page;

$total = (int)db_value("SELECT COUNT(*) FROM businesses b WHERE {$where}", $params);
$businesses = db_query(
    "SELECT b.*, c.first_name AS client_first, c.last_name AS client_last 
     FROM businesses b 
     LEFT JOIN clients c ON b.client_id = c.id 
     WHERE {$where} 
     ORDER BY b.created_at DESC 
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);
$total_pages = max(1, ceil($total / $per_page));

// Stats
$total_businesses = (int)db_value('SELECT COUNT(*) FROM businesses');
$active_businesses = (int)db_value('SELECT COUNT(*) FROM businesses WHERE status = "active"');

// Edit business
$edit_business = null;
if (get('edit')) {
    $edit_business = db_row('SELECT * FROM businesses WHERE id = ?', [(int)get('edit')]);
}

// View business
$view_business = null;
if (get('view')) {
    $view_business = db_row(
        'SELECT b.*, c.first_name AS client_first, c.last_name AS client_last 
         FROM businesses b 
         LEFT JOIN clients c ON b.client_id = c.id 
         WHERE b.id = ?',
        [(int)get('view')]
    );
    if ($view_business) {
        log_activity('businesses', 'view', $view_business['id'], 'Viewed business: ' . $view_business['name']);
        $view_contacts = db_query('SELECT * FROM contacts WHERE business_id = ?', [$view_business['id']]);
    }
}
?>
<?php include __DIR__ . '/header.php'; ?>

<?php if ($view_business): ?>
    <!-- Business Profile View -->
    <div class="page-title">
        <h1><i class="bi bi-building"></i> <?= e($view_business['name']) ?></h1>
        <div>
            <a href="businesses.php?edit=<?= $view_business['id'] ?>" class="btn-secondary-custom"><i class="bi bi-pencil"></i> Edit</a>
            <a href="businesses.php" class="btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Business Information</div>
                <div class="card-body">
                    <p><strong>Legal Name:</strong> <?= e($view_business['legal_name'] ?: 'Not set') ?></p>
                    <p><strong>EIN:</strong> <?= e($view_business['ein'] ?: 'Not set') ?></p>
                    <p><strong>Type:</strong> <?= e($view_business['business_type'] ?: 'Not set') ?></p>
                    <p><strong>Industry:</strong> <?= e($view_business['industry'] ?: 'Not set') ?></p>
                    <p><strong>Client:</strong> <a href="clients.php?view=<?= $view_business['client_id'] ?>"><?= e($view_business['client_first'] . ' ' . $view_business['client_last']) ?></a></p>
                    <p><strong>Email:</strong> <?= e($view_business['email'] ?: 'Not set') ?></p>
                    <p><strong>Phone:</strong> <?= e($view_business['phone'] ?: 'Not set') ?></p>
                    <p><strong>Website:</strong> <?= e($view_business['website'] ?: 'Not set') ?></p>
                    <p><strong>Address:</strong> <?= e($view_business['address_line1'] ?: 'Not set') ?></p>
                    <p><strong>City:</strong> <?= e($view_business['city'] ?: 'Not set') ?></p>
                    <p><strong>State:</strong> <?= e($view_business['state'] ?: 'Not set') ?></p>
                    <p><strong>ZIP:</strong> <?= e($view_business['zip'] ?: 'Not set') ?></p>
                    <p><strong>Status:</strong> <?= status_badge($view_business['status']) ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Contacts (<?= count($view_contacts) ?>)</div>
                <div class="card-body">
                    <?php if (empty($view_contacts)): ?>
                        <p class="text-muted">No contacts linked to this business.</p>
                    <?php else: ?>
                        <table class="table-custom">
                            <thead><tr><th>Name</th><th>Position</th><th>Email</th><th>Phone</th></tr></thead>
                            <tbody>
                                <?php foreach ($view_contacts as $contact): ?>
                                    <tr>
                                        <td><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?></td>
                                        <td><?= e($contact['position'] ?: '-') ?></td>
                                        <td><?= e($contact['email']) ?></td>
                                        <td><?= e($contact['phone']) ?></td>
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
    <!-- Businesses List -->
    <div class="page-title">
        <h1><i class="bi bi-building"></i> Businesses</h1>
        <button class="btn-primary-custom" onclick="toggleNewBusiness()"><i class="bi bi-plus"></i> New Business</button>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-building"></i></div>
                <div class="stat-value"><?= $total_businesses ?></div>
                <div class="stat-label">Total Businesses</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-building-check"></i></div>
                <div class="stat-value"><?= $active_businesses ?></div>
                <div class="stat-label">Active Businesses</div>
            </div>
        </div>
    </div>
    
    <!-- New/Edit Business Form -->
    <div class="card" id="businessForm" style="display:<?= $edit_business ? 'block' : 'none' ?>;">
        <div class="card-header">
            <?= $edit_business ? 'Edit Business: ' . e($edit_business['name']) : 'Create New Business' ?>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $edit_business ? 'update' : 'create' ?>">
                <input type="hidden" name="business_id" value="<?= $edit_business['id'] ?? 0 ?>">
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Business Name *</label>
                        <input type="text" name="name" class="form-control-custom" value="<?= e($edit_business['name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Legal Name</label>
                        <input type="text" name="legal_name" class="form-control-custom" value="<?= e($edit_business['legal_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Client *</label>
                        <select name="client_id" class="form-control-custom" required>
                            <option value="0">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= ($edit_business['client_id'] ?? 0) == $client['id'] ? 'selected' : '' ?>>
                                    <?= e($client['first_name'] . ' ' . $client['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">EIN</label>
                        <input type="text" name="ein" class="form-control-custom" value="<?= e($edit_business['ein'] ?? '') ?>" placeholder="XX-XXXXXXX">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Business Type</label>
                        <input type="text" name="business_type" class="form-control-custom" value="<?= e($edit_business['business_type'] ?? '') ?>" placeholder="LLC, Corp, etc.">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Industry</label>
                        <input type="text" name="industry" class="form-control-custom" value="<?= e($edit_business['industry'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label-custom">Address Line 1</label>
                        <input type="text" name="address_line1" class="form-control-custom" value="<?= e($edit_business['address_line1'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Address Line 2</label>
                        <input type="text" name="address_line2" class="form-control-custom" value="<?= e($edit_business['address_line2'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label-custom">City</label>
                        <input type="text" name="city" class="form-control-custom" value="<?= e($edit_business['city'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">State</label>
                        <input type="text" name="state" class="form-control-custom" value="<?= e($edit_business['state'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">ZIP</label>
                        <input type="text" name="zip" class="form-control-custom" value="<?= e($edit_business['zip'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Country</label>
                        <input type="text" name="country" class="form-control-custom" value="<?= e($edit_business['country'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Phone</label>
                        <input type="text" name="phone" class="form-control-custom" value="<?= e($edit_business['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Email</label>
                        <input type="email" name="email" class="form-control-custom" value="<?= e($edit_business['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Website</label>
                        <input type="text" name="website" class="form-control-custom" value="<?= e($edit_business['website'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Status</label>
                        <select name="status" class="form-control-custom">
                            <option value="active" <?= ($edit_business['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($edit_business['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="archived" <?= ($edit_business['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> <?= $edit_business ? 'Update' : 'Create' ?> Business</button>
                <button type="button" class="btn-secondary-custom" onclick="toggleNewBusiness()">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control-custom" placeholder="Search businesses..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="client" class="form-control-custom">
                        <option value="0">All Clients</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $client_filter == $client['id'] ? 'selected' : '' ?>>
                                <?= e($client['first_name'] . ' ' . $client['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control-custom">
                        <option value="">All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="archived" <?= $status_filter === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Filter</button>
                    <a href="businesses.php" class="btn-secondary-custom">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Businesses Table -->
    <div class="card">
        <div class="card-header">Businesses (<?= $total ?>)</div>
        <div class="card-body">
            <?php if (empty($businesses)): ?>
                <p class="text-muted">No businesses found.</p>
            <?php else: ?>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Client</th>
                            <th>EIN</th>
                            <th>Type</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($businesses as $biz): ?>
                            <tr>
                                <td><a href="businesses.php?view=<?= $biz['id'] ?>"><strong><?= e($biz['name']) ?></strong></a></td>
                                <td><?= e($biz['client_first'] ? $biz['client_first'] . ' ' . $biz['client_last'] : '-') ?></td>
                                <td><?= e($biz['ein'] ?: '-') ?></td>
                                <td><?= e($biz['business_type'] ?: '-') ?></td>
                                <td><?= e($biz['email']) ?></td>
                                <td><?= status_badge($biz['status']) ?></td>
                                <td>
                                    <a href="businesses.php?view=<?= $biz['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-eye"></i></a>
                                    <a href="businesses.php?edit=<?= $biz['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                                    <?php if ($biz['status'] !== 'archived'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Archive this business?')">
                                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="business_id" value="<?= $biz['id'] ?>">
                                            <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-archive"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?= pagination_links($total_pages, $page, 'businesses.php?' . http_build_query(array_filter(['search' => $search, 'client' => $client_filter, 'status' => $status_filter]))) ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleNewBusiness() {
    const form = document.getElementById('businessForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block' && !form.querySelector('input[name="business_id"]').value) {
        form.querySelectorAll('input[type="text"], input[type="email"]').forEach(i => i.value = '');
        form.querySelectorAll('select').forEach(s => s.value = '0');
        form.querySelector('select[name="status"]').value = 'active';
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>