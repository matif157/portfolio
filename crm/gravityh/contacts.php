<?php
/**
 * GRAVITY CRM - Contacts Management
 * Phase 1: Foundation
 * 
 * Yahan contacts create/edit/delete hote hain.
 * Contacts clients aur businesses se linked ho sakte hain.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('contacts', 'view');

$user = current_user();
$page_title = 'Contacts';
$active_page = 'contacts';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create' || $action === 'update') {
        require_permission('contacts', 'create');
        
        $contact_id = (int)post('contact_id', 0);
        $client_id = (int)post('client_id', 0);
        $business_id = (int)post('business_id', 0);
        $first_name = sanitize(post('first_name'));
        $last_name = sanitize(post('last_name'));
        $email = sanitize(post('email'));
        $phone = sanitize(post('phone'));
        $mobile = sanitize(post('mobile'));
        $position = sanitize(post('position'));
        $is_primary = post('is_primary') ? 1 : 0;
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
                    'INSERT INTO contacts (client_id, business_id, first_name, last_name, email, phone, mobile, position, is_primary, notes, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$client_id ?: null, $business_id ?: null, $first_name, $last_name, $email, $phone, $mobile, $position, $is_primary, $notes, $user['id']]
                );
                $contact_id = db_last_id();
                log_activity('contacts', 'create', $contact_id, 'Created contact: ' . $first_name . ' ' . $last_name);
                flash('success', 'Contact created successfully.');
            } else {
                $existing = db_row('SELECT * FROM contacts WHERE id = ?', [$contact_id]);
                if (!$existing) {
                    flash('error', 'Contact not found.');
                    redirect('contacts.php');
                }
                
                db_execute(
                    'UPDATE contacts SET client_id = ?, business_id = ?, first_name = ?, last_name = ?, email = ?, phone = ?, mobile = ?, position = ?, is_primary = ?, notes = ? WHERE id = ?',
                    [$client_id ?: null, $business_id ?: null, $first_name, $last_name, $email, $phone, $mobile, $position, $is_primary, $notes, $contact_id]
                );
                log_activity('contacts', 'update', $contact_id, 'Updated contact: ' . $first_name . ' ' . $last_name);
                flash('success', 'Contact updated successfully.');
            }
            redirect('contacts.php');
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete') {
        require_permission('contacts', 'delete');
        $contact_id = (int)post('contact_id', 0);
        
        $existing = db_row('SELECT * FROM contacts WHERE id = ?', [$contact_id]);
        if (!$existing) {
            flash('error', 'Contact not found.');
            redirect('contacts.php');
        }
        
        db_execute('DELETE FROM contacts WHERE id = ?', [$contact_id]);
        log_activity('contacts', 'delete', $contact_id, 'Deleted contact: ' . $existing['first_name'] . ' ' . $existing['last_name']);
        flash('success', 'Contact deleted successfully.');
        redirect('contacts.php');
    }
}

// Get clients and businesses for dropdowns
$clients = db_query('SELECT id, first_name, last_name FROM clients WHERE status = "active" ORDER BY first_name');
$businesses = db_query('SELECT id, name FROM businesses WHERE status = "active" ORDER BY name');

// Build query with filters
$where = '1=1';
$params = [];

$search = trim(get('search', ''));
if ($search !== '') {
    $where .= ' AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}

$client_filter = (int)get('client', 0);
if ($client_filter > 0) {
    $where .= ' AND c.client_id = ?';
    $params[] = $client_filter;
}

// Pagination
$per_page = (int)get_setting('items_per_page', 20);
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $per_page;

$total = (int)db_value("SELECT COUNT(*) FROM contacts c WHERE {$where}", $params);
$contacts = db_query(
    "SELECT c.*, cl.first_name AS client_first, cl.last_name AS client_last, b.name AS business_name 
     FROM contacts c 
     LEFT JOIN clients cl ON c.client_id = cl.id 
     LEFT JOIN businesses b ON c.business_id = b.id 
     WHERE {$where} 
     ORDER BY c.created_at DESC 
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);
$total_pages = max(1, ceil($total / $per_page));

// Edit contact
$edit_contact = null;
if (get('edit')) {
    $edit_contact = db_row('SELECT * FROM contacts WHERE id = ?', [(int)get('edit')]);
}
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-person-lines-fill"></i> Contacts</h1>
    <button class="btn-primary-custom" onclick="toggleNewContact()"><i class="bi bi-plus"></i> New Contact</button>
</div>

<!-- New/Edit Contact Form -->
<div class="card" id="contactForm" style="display:<?= $edit_contact ? 'block' : 'none' ?>;">
    <div class="card-header">
        <?= $edit_contact ? 'Edit Contact: ' . e($edit_contact['first_name'] . ' ' . $edit_contact['last_name']) : 'Create New Contact' ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $edit_contact ? 'update' : 'create' ?>">
            <input type="hidden" name="contact_id" value="<?= $edit_contact['id'] ?? 0 ?>">
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">First Name *</label>
                    <input type="text" name="first_name" class="form-control-custom" value="<?= e($edit_contact['first_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Last Name *</label>
                    <input type="text" name="last_name" class="form-control-custom" value="<?= e($edit_contact['last_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Position</label>
                    <input type="text" name="position" class="form-control-custom" value="<?= e($edit_contact['position'] ?? '') ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Linked Client</label>
                    <select name="client_id" class="form-control-custom">
                        <option value="0">None</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= ($edit_contact['client_id'] ?? 0) == $client['id'] ? 'selected' : '' ?>>
                                <?= e($client['first_name'] . ' ' . $client['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Linked Business</label>
                    <select name="business_id" class="form-control-custom">
                        <option value="0">None</option>
                        <?php foreach ($businesses as $biz): ?>
                            <option value="<?= $biz['id'] ?>" <?= ($edit_contact['business_id'] ?? 0) == $biz['id'] ? 'selected' : '' ?>>
                                <?= e($biz['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Primary Contact</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="is_primary" value="1" class="form-check-input" id="isPrimary" <?= ($edit_contact['is_primary'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isPrimary">Yes</label>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label-custom">Email</label>
                    <input type="email" name="email" class="form-control-custom" value="<?= e($edit_contact['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Phone</label>
                    <input type="text" name="phone" class="form-control-custom" value="<?= e($edit_contact['phone'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Mobile</label>
                    <input type="text" name="mobile" class="form-control-custom" value="<?= e($edit_contact['mobile'] ?? '') ?>">
                </div>
            </div>
            
            <label class="form-label-custom">Notes</label>
            <textarea name="notes" class="form-control-custom" rows="3"><?= e($edit_contact['notes'] ?? '') ?></textarea>
            
            <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> <?= $edit_contact ? 'Update' : 'Create' ?> Contact</button>
            <button type="button" class="btn-secondary-custom" onclick="toggleNewContact()">Cancel</button>
        </form>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control-custom" placeholder="Search contacts..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="client" class="form-control-custom">
                    <option value="0">All Clients</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['id'] ?>" <?= $client_filter == $client['id'] ? 'selected' : '' ?>>
                            <?= e($client['first_name'] . ' ' . $client['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Filter</button>
                <a href="contacts.php" class="btn-secondary-custom">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Contacts Table -->
<div class="card">
    <div class="card-header">Contacts (<?= $total ?>)</div>
    <div class="card-body">
        <?php if (empty($contacts)): ?>
            <p class="text-muted">No contacts found.</p>
        <?php else: ?>
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Client</th>
                        <th>Business</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Primary</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contacts as $contact): ?>
                        <tr>
                            <td><strong><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?></strong></td>
                            <td><?= e($contact['position'] ?: '-') ?></td>
                            <td><?= e($contact['client_first'] ? $contact['client_first'] . ' ' . $contact['client_last'] : '-') ?></td>
                            <td><?= e($contact['business_name'] ?: '-') ?></td>
                            <td><?= e($contact['email']) ?></td>
                            <td><?= e($contact['phone']) ?></td>
                            <td><?= $contact['is_primary'] ? '<span class="badge badge-success">Primary</span>' : '-' ?></td>
                            <td>
                                <a href="contacts.php?edit=<?= $contact['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this contact?')">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>">
                                    <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?= pagination_links($total_pages, $page, 'contacts.php?' . http_build_query(array_filter(['search' => $search, 'client' => $client_filter]))) ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleNewContact() {
    const form = document.getElementById('contactForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block' && !form.querySelector('input[name="contact_id"]').value) {
        form.querySelectorAll('input[type="text"], input[type="email"], textarea').forEach(i => i.value = '');
        form.querySelectorAll('select').forEach(s => s.value = '0');
        form.querySelector('input[name="is_primary"]').checked = false;
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>