<?php
/**
 * GRAVITY CRM - Clients Management
 * Phase 1: Foundation
 * 
 * Yahan clients create/edit/delete hote hain.
 * Search, filter, pagination bhi available hai.
 * Custom fields bhi yahan render hote hain (Customizer se).
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('clients', 'view');

$user = current_user();
$page_title = 'Clients';
$active_page = 'clients';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'create' || $action === 'update') {
        require_permission('clients', 'create');
        
        $client_id = (int)post('client_id', 0);
        $client_type_id = (int)post('client_type_id', 0);
        $first_name = sanitize(post('first_name'));
        $last_name = sanitize(post('last_name'));
        $email = sanitize(post('email'));
        $phone = sanitize(post('phone'));
        $mobile = sanitize(post('mobile'));
        $address_line1 = sanitize(post('address_line1'));
        $address_line2 = sanitize(post('address_line2'));
        $city = sanitize(post('city'));
        $state = sanitize(post('state'));
        $zip = sanitize(post('zip'));
        $country = sanitize(post('country'));
        $status = sanitize(post('status', 'active'));
        $assigned_user_id = (int)post('assigned_user_id', 0);
        $source = sanitize(post('source'));
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
                    'INSERT INTO clients (client_type_id, first_name, last_name, email, phone, mobile, address_line1, address_line2, city, state, zip, country, status, assigned_user_id, source, notes, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$client_type_id ?: null, $first_name, $last_name, $email, $phone, $mobile, $address_line1, $address_line2, $city, $state, $zip, $country, $status, $assigned_user_id ?: null, $source, $notes, $user['id']]
                );
                $client_id = db_last_id();
                
                save_custom_fields('clients', $client_id);
                log_activity('clients', 'create', $client_id, 'Created client: ' . $first_name . ' ' . $last_name);
                bus_emit(EVT_CLIENT_CREATED, [], ['source' => 'client', 'source_id' => $client_id]);
                flash('success', 'Client created successfully.');
                redirect('clients.php?view=' . $client_id);
            } else {
                $existing = db_row('SELECT * FROM clients WHERE id = ?', [$client_id]);
                if (!$existing) {
                    flash('error', 'Client not found.');
                    redirect('clients.php');
                }
                
                $changes = [];
                if ($existing['first_name'] !== $first_name) $changes['first_name'] = ['old' => $existing['first_name'], 'new' => $first_name];
                if ($existing['last_name'] !== $last_name) $changes['last_name'] = ['old' => $existing['last_name'], 'new' => $last_name];
                if ($existing['email'] !== $email) $changes['email'] = ['old' => $existing['email'], 'new' => $email];
                if ($existing['phone'] !== $phone) $changes['phone'] = ['old' => $existing['phone'], 'new' => $phone];
                if ($existing['status'] !== $status) $changes['status'] = ['old' => $existing['status'], 'new' => $status];
                
                db_execute(
                    'UPDATE clients SET client_type_id = ?, first_name = ?, last_name = ?, email = ?, phone = ?, mobile = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, zip = ?, country = ?, status = ?, assigned_user_id = ?, source = ?, notes = ? WHERE id = ?',
                    [$client_type_id ?: null, $first_name, $last_name, $email, $phone, $mobile, $address_line1, $address_line2, $city, $state, $zip, $country, $status, $assigned_user_id ?: null, $source, $notes, $client_id]
                );
                
                save_custom_fields('clients', $client_id);
                log_activity('clients', 'update', $client_id, 'Updated client: ' . $first_name . ' ' . $last_name, false, $changes);
                flash('success', 'Client updated successfully.');
                redirect('clients.php?view=' . $client_id);
            }
        } else {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        }
    }
    
    if ($action === 'delete') {
        require_permission('clients', 'delete');
        $client_id = (int)post('client_id', 0);
        $existing = db_row('SELECT * FROM clients WHERE id = ?', [$client_id]);
        if ($existing) {
            db_execute('UPDATE clients SET status = "archived" WHERE id = ?', [$client_id]);
            log_activity('clients', 'delete', $client_id, 'Archived client: ' . $existing['first_name'] . ' ' . $existing['last_name']);
            bus_emit('CLIENT_DELETED', [], ['source' => 'client', 'source_id' => $client_id]);
            flash('success', 'Client archived successfully.');
        }
        redirect('clients.php');
    }

    // SPS Operations: Add Business Entity
    if ($action === 'add_business') {
        require_permission('clients', 'edit');
        $client_id = (int)post('client_id');
        $name = sanitize(post('business_name'));
        $ein = sanitize(post('ein'));
        $b_type = sanitize(post('business_type', 'LLC'));
        $industry = sanitize(post('industry'));
        
        if (!empty($name)) {
            db_execute("INSERT INTO businesses (client_id, name, ein, business_type, industry, status, created_by) VALUES (?, ?, ?, ?, ?, 'active', ?)",
                [$client_id, $name, $ein, $b_type, $industry, $user['id']]
            );
            log_activity('businesses', 'create', db_last_id(), "Created business entity {$name} for client ID {$client_id}");
            flash('success', "Business entity '{$name}' created successfully.");
        }
        redirect("clients.php?view={$client_id}#businesses");
    }

    // SPS Operations: Enroll in Service & Create Operational Project Pipeline
    if ($action === 'enroll_service') {
        require_permission('clients', 'edit');
        $client_id = (int)post('client_id');
        $service_id = (int)post('service_id');
        $business_id = (int)post('business_id') ?: null;
        $custom_rate = post('custom_rate') !== '' ? (float)post('custom_rate') : null;
        
        $service = db_row("SELECT * FROM services WHERE id = ?", [$service_id]);
        if ($service) {
            $rate = $custom_rate !== null ? $custom_rate : (float)($service['fixed_price'] ?: 0);
            db_execute("INSERT INTO client_services (client_id, business_id, service_id, status, start_date, custom_rate, created_by) VALUES (?, ?, ?, 'active', CURDATE(), ?, ?)",
                [$client_id, $business_id, $service_id, $rate, $user['id']]
            );
            
            // Auto-create operational Project work order
            $client = db_row("SELECT * FROM clients WHERE id = ?", [$client_id]);
            $client_name = $client ? $client['first_name'] . ' ' . $client['last_name'] : "Client #{$client_id}";
            $prj_name = $service['name'] . ' - ' . $client_name;
            
            db_execute("
                INSERT INTO projects (client_id, business_id, service_id, name, description, status, priority, start_date, due_date, budget, created_by)
                VALUES (?, ?, ?, ?, ?, 'in_progress', 'medium', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?)
            ", [$client_id, $business_id, $service_id, $prj_name, "Operational project workflow for {$service['name']}", $rate, $user['id']]);
            $prj_id = db_last_id();

            // Run initial requirement evaluation
            evaluate_service_requirements($client_id, $service_id, $prj_id);

            log_activity('services', 'enroll', $service_id, "Enrolled client #{$client_id} into service: {$service['name']}");
            flash('success', "Enrolled into '{$service['name']}' and created operational project workflow.");
        }
        redirect("clients.php?view={$client_id}#services");
    }

    // SPS Operations: Realtime Service Evaluation
    if ($action === 'evaluate_service') {
        require_permission('clients', 'edit');
        $client_id = (int)post('client_id');
        $service_id = (int)post('service_id');
        $project_id = (int)post('project_id') ?: null;
        
        $eval = evaluate_service_requirements($client_id, $service_id, $project_id);
        if ($eval['status'] === 'fully_fulfilled') {
            flash('success', "Evaluation Complete: All {$eval['total_requirements']} prerequisites are fulfilled! Project workflow advanced.");
        } else {
            flash('warning', "Prerequisites Pending: {$eval['fulfilled_count']}/{$eval['total_requirements']} fulfilled. Missing items: " . implode(', ', $eval['missing_items']));
        }
        redirect("clients.php?view={$client_id}#services");
    }

    // SPS Operations: Update Requirement Status
    if ($action === 'update_requirement_status') {
        require_permission('clients', 'edit');
        $client_id = (int)post('client_id');
        $service_id = (int)post('service_id');
        $requirement_id = (int)post('requirement_id');
        $new_status = sanitize(post('status', 'fulfilled'));
        $note = sanitize(post('fulfilled_value', ''));

        $csr = db_row("SELECT id FROM client_service_requirements WHERE client_id = ? AND service_id = ? AND requirement_id = ?", [$client_id, $service_id, $requirement_id]);
        if ($csr) {
            db_execute("UPDATE client_service_requirements SET status = ?, fulfilled_value = ?, fulfilled_at = IF(? = 'fulfilled', NOW(), fulfilled_at) WHERE id = ?",
                [$new_status, $note, $new_status, $csr['id']]
            );
        } else {
            db_execute("INSERT INTO client_service_requirements (client_id, service_id, requirement_id, status, fulfilled_value, fulfilled_at) VALUES (?, ?, ?, ?, ?, IF(? = 'fulfilled', NOW(), NULL))",
                [$client_id, $service_id, $requirement_id, $new_status, $note, $new_status]
            );
        }
        flash('success', 'Requirement status updated.');
        redirect("clients.php?view={$client_id}#services");
    }

    // SPS Operations: Add Bank Account
    if ($action === 'add_bank_account') {
        require_permission('clients', 'edit');
        $client_id = (int)post('client_id');
        $business_id = (int)post('business_id');
        $bank_name = sanitize(post('bank_name'));
        $account_name = sanitize(post('account_name'));
        $account_type = sanitize(post('account_type', 'checking'));
        $last4 = sanitize(post('account_number_last4'));
        $year = (int)post('grid_year', date('Y'));

        if (!empty($bank_name) && !empty($last4)) {
            db_execute("INSERT INTO bank_accounts (business_id, client_id, bank_name, account_name, account_type, account_number_last4, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)",
                [$business_id, $client_id, $bank_name, $account_name, $account_type, $last4]
            );
            $account_id = db_last_id();
            sync_bank_statements_grid($account_id, $year);
            flash('success', "Bank account {$bank_name} (x-{$last4}) registered and {$year} statement checklist generated.");
        }
        redirect("clients.php?view={$client_id}#statements");
    }

    // SPS Operations: Upload or Verify Bank Statement
    if ($action === 'update_statement_status') {
        require_permission('clients', 'edit');
        $client_id = (int)post('client_id');
        $statement_id = (int)post('statement_id');
        $new_status = sanitize(post('status', 'received'));
        
        db_execute("UPDATE bank_statements SET status = ?, verified_at = IF(? IN ('verified','received'), NOW(), verified_at), verified_by = ? WHERE id = ?",
            [$new_status, $new_status, $user['id'], $statement_id]
        );
        flash('success', 'Bank statement status updated.');
        redirect("clients.php?view={$client_id}#statements");
    }

    // SPS Operations: Send Omnichannel Communication
    if ($action === 'send_communication') {
        require_permission('clients', 'edit');
        $client_id = (int)post('client_id');
        $channel = sanitize(post('channel', 'email'));
        $subject = sanitize(post('subject', 'Notice from Syed Professional Services'));
        $message_body = sanitize(post('message_body', ''));
        $project_id = (int)post('project_id') ?: null;

        if (!empty($message_body)) {
            $dispatch = dispatch_omnichannel_notice($client_id, $channel, $subject, $message_body, $project_id);
            if ($dispatch['success']) {
                flash('success', "Communication sent via " . strtoupper($channel) . ": " . $dispatch['message']);
            } else {
                flash('error', "Dispatch failed: " . $dispatch['message']);
            }
        }
        redirect("clients.php?view={$client_id}#communications");
    }

    // SPS Operations: Add Client Comment / Staff Note
    if ($action === 'add_comment') {
        require_permission('clients', 'edit');
        $client_id = (int)post('client_id');
        $comment = sanitize(post('comment'));
        if (!empty($comment)) {
            db_execute("INSERT INTO client_comments (client_id, user_id, comment) VALUES (?, ?, ?)", [$client_id, $user['id'], $comment]);
            log_communication($client_id, null, 'note', 'inbound', 'Staff Operational Note', $comment, 'completed', null, 0, $user['id']);
            flash('success', 'Staff note recorded.');
        }
        redirect("clients.php?view={$client_id}#communications");
    }

    // SPS Operations: Add B2B Relationship
    if ($action === 'add_relationship') {
        require_permission('clients', 'edit');
        $client_id = (int)post('client_id');
        $related_client_id = (int)post('related_client_id');
        $rel_type = sanitize(post('relationship_type', 'partner'));
        $strength = sanitize(post('strength', 'moderate'));
        $notes = sanitize(post('relationship_notes', ''));

        if ($related_client_id > 0 && $related_client_id !== $client_id) {
            db_execute("INSERT INTO client_relationships (client_id, related_client_id, relationship_type, strength, notes) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE relationship_type = VALUES(relationship_type), strength = VALUES(strength), notes = VALUES(notes)",
                [$client_id, $related_client_id, $rel_type, $strength, $notes]
            );
            flash('success', 'B2B relationship mapped successfully.');
        }
        redirect("clients.php?view={$client_id}#relationships");
    }
}

// Get client types
$client_types = db_query('SELECT * FROM client_types ORDER BY name');

// Get users for assignment
$users = db_query('SELECT id, first_name, last_name FROM users WHERE status = "active" ORDER BY first_name');

// Get all services catalog for enrollment dropdown
$all_sps_services = db_query('SELECT * FROM services WHERE status = "active" ORDER BY category, name');

// Get custom fields for clients
$custom_fields = db_query('SELECT * FROM custom_fields WHERE module = "clients" AND is_active = 1 ORDER BY sort_order');

// Build query with filters
$where = '1=1';
$params = [];

$search = trim(get('search', ''));
if ($search !== '') {
    $where .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR mobile LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

$type_filter = (int)get('type', 0);
if ($type_filter > 0) {
    $where .= ' AND client_type_id = ?';
    $params[] = $type_filter;
}

$status_filter = get('status', '');
if ($status_filter !== '') {
    $where .= ' AND status = ?';
    $params[] = $status_filter;
}

$assigned_filter = (int)get('assigned', 0);
if ($assigned_filter > 0) {
    $where .= ' AND assigned_user_id = ?';
    $params[] = $assigned_filter;
}

// Pagination
$per_page = (int)get_setting('items_per_page', 20);
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $per_page;

$total = (int)db_value("SELECT COUNT(*) FROM clients WHERE {$where}", $params);
$clients = db_query(
    "SELECT c.*, ct.name AS client_type_name, u.first_name AS assigned_first, u.last_name AS assigned_last,
            (SELECT COUNT(*) FROM businesses b WHERE b.client_id = c.id) as business_count,
            (SELECT COUNT(*) FROM client_services cs WHERE cs.client_id = c.id AND cs.status = 'active') as active_services_count,
            (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id AND p.status != 'completed') as open_projects_count
     FROM clients c 
     LEFT JOIN client_types ct ON c.client_type_id = ct.id 
     LEFT JOIN users u ON c.assigned_user_id = u.id 
     WHERE {$where} 
     ORDER BY c.created_at DESC 
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);
$total_pages = max(1, ceil($total / $per_page));

// Stats
$total_clients = (int)db_value('SELECT COUNT(*) FROM clients');
$active_clients = (int)db_value('SELECT COUNT(*) FROM clients WHERE status = "active"');
$archived_clients = (int)db_value('SELECT COUNT(*) FROM clients WHERE status = "archived"');

// Edit client
$edit_client = null;
$edit_custom_values = [];
if (get('edit')) {
    $edit_client = db_row('SELECT * FROM clients WHERE id = ?', [(int)get('edit')]);
    if ($edit_client) {
        $rows = db_query('SELECT custom_field_id, field_value FROM custom_field_values WHERE module = "clients" AND record_id = ?', [$edit_client['id']]);
        foreach ($rows as $row) {
            $edit_custom_values[$row['custom_field_id']] = $row['field_value'];
        }
    }
}

// 360-Degree Comprehensive Client Profile View
$view_client = null;
if (get('view')) {
    $client_view_id = (int)get('view');
    $view_client = db_row(
        'SELECT c.*, ct.name AS client_type_name, u.first_name AS assigned_first, u.last_name AS assigned_last 
         FROM clients c 
         LEFT JOIN client_types ct ON c.client_type_id = ct.id 
         LEFT JOIN users u ON c.assigned_user_id = u.id 
         WHERE c.id = ?',
        [$client_view_id]
    );
    
    if ($view_client) {
        log_activity('clients', 'view', $view_client['id'], 'Accessed 360Â° Profile: ' . $view_client['first_name'] . ' ' . $view_client['last_name']);
        
        // 1. Businesses & Entities
        $view_businesses = db_query('SELECT * FROM businesses WHERE client_id = ? ORDER BY id DESC', [$client_view_id]);
        
        // 2. Active Services & Requirements Fulfillment
        $view_services = db_query("
            SELECT cs.*, s.name as service_name, s.category, s.fixed_price, s.service_type, b.name as business_name,
                   (SELECT COUNT(*) FROM service_requirements sr WHERE sr.service_id = cs.service_id) as total_reqs,
                   (SELECT COUNT(*) FROM client_service_requirements csr WHERE csr.client_id = cs.client_id AND csr.service_id = cs.service_id AND csr.status IN ('fulfilled', 'waived')) as fulfilled_reqs
            FROM client_services cs
            JOIN services s ON cs.service_id = s.id
            LEFT JOIN businesses b ON cs.business_id = b.id
            WHERE cs.client_id = ?
            ORDER BY cs.id DESC
        ", [$client_view_id]);
        
        // Fetch detailed requirements for all services
        $service_reqs_map = [];
        foreach ($view_services as $vs) {
            $s_id = $vs['service_id'];
            $reqs = db_query("
                SELECT sr.*, csr.status as fulfillment_status, csr.fulfilled_value, csr.fulfilled_at
                FROM service_requirements sr
                LEFT JOIN client_service_requirements csr ON sr.id = csr.requirement_id AND csr.client_id = ? AND csr.service_id = ?
                WHERE sr.service_id = ?
                ORDER BY sr.sort_order ASC
            ", [$client_view_id, $s_id, $s_id]);
            $service_reqs_map[$vs['id']] = $reqs;
        }

        // 3. Projects & Workflows
        $view_projects = db_query('SELECT p.*, s.name as service_name, b.name as business_name FROM projects p LEFT JOIN services s ON p.service_id = s.id LEFT JOIN businesses b ON p.business_id = b.id WHERE p.client_id = ? ORDER BY p.id DESC', [$client_view_id]);

        // 4. Bank Accounts & Statements Matrix (Year 2026)
        $selected_stmt_year = (int)get('year', date('Y'));
        $view_bank_accounts = db_query('SELECT ba.*, b.name as business_name FROM bank_accounts ba JOIN businesses b ON ba.business_id = b.id WHERE ba.client_id = ? ORDER BY ba.id ASC', [$client_view_id]);
        
        $bank_matrix = [];
        if (!empty($view_bank_accounts)) {
            $acc_ids = array_column($view_bank_accounts, 'id');
            $in_clause = implode(',', array_fill(0, count($acc_ids), '?'));
            $raw_stmts = db_query("SELECT * FROM bank_statements WHERE bank_account_id IN ($in_clause) AND period_year = ?", array_merge($acc_ids, [$selected_stmt_year]));
            foreach ($raw_stmts as $rs) {
                $bank_matrix[$rs['bank_account_id']][$rs['period_month']] = $rs;
            }
        }

        // 5. Invoices & Payments
        $view_invoices = db_query('SELECT * FROM invoices WHERE client_id = ? ORDER BY invoice_date DESC', [$client_view_id]);

        // 6. Unified Omnichannel Timeline & Notes
        $view_communications = db_query('SELECT c.*, u.first_name, u.last_name FROM communications c LEFT JOIN users u ON c.user_id = u.id WHERE c.client_id = ? ORDER BY c.sent_at DESC', [$client_view_id]);

        // 7. B2B Relationships
        $view_relationships = db_query("
            SELECT cr.*, c.first_name as rel_first, c.last_name as rel_last, c.email as rel_email
            FROM client_relationships cr
            JOIN clients c ON cr.related_client_id = c.id
            WHERE cr.client_id = ?
        ", [$client_view_id]);

        // 8. Custom Fields
        $view_custom_values = db_query('SELECT cf.field_label, cfv.field_value FROM custom_field_values cfv JOIN custom_fields cf ON cfv.custom_field_id = cf.id WHERE cfv.module = "clients" AND cfv.record_id = ?', [$client_view_id]);

        // 9. AI 360-Degree Intelligence Briefing
        $ai_briefing = ai_generate_client_briefing($client_view_id);
    }
}

/**
 * Save custom field values
 */
function save_custom_fields(string $module, int $record_id): void {
    $fields = db_query('SELECT id FROM custom_fields WHERE module = ? AND is_active = 1', [$module]);
    foreach ($fields as $field) {
        $value = post('custom_' . $field['id'], '');
        $existing = db_row('SELECT id FROM custom_field_values WHERE custom_field_id = ? AND module = ? AND record_id = ?', [$field['id'], $module, $record_id]);
        if ($existing) {
            db_execute('UPDATE custom_field_values SET field_value = ? WHERE id = ?', [$value, $existing['id']]);
        } else {
            db_execute('INSERT INTO custom_field_values (custom_field_id, module, record_id, field_value, updated_by) VALUES (?, ?, ?, ?, ?)', [$field['id'], $module, $record_id, $value, current_user()['id']]);
        }
    }
}
?>

<?php include __DIR__ . '/header.php'; ?>

<?php if ($view_client): ?>
    <!-- 360-Degree Operational Client Workspace -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width:54px;height:54px;font-size:20px;">
                <?= strtoupper(substr($view_client['first_name'], 0, 1) . substr($view_client['last_name'], 0, 1)) ?>
            </div>
            <div>
                <h2 class="mb-0 fw-bold"><?= e($view_client['first_name'] . ' ' . $view_client['last_name']) ?></h2>
                <div class="text-muted small">
                    <span class="me-2"><i class="bi bi-tag"></i> <?= e($view_client['client_type_name'] ?? 'Client') ?></span>
                    <span class="me-2"><i class="bi bi-envelope"></i> <?= e($view_client['email'] ?: 'No email') ?></span>
                    <span class="me-2"><i class="bi bi-telephone"></i> <?= e($view_client['phone'] ?: ($view_client['mobile'] ?: 'No phone')) ?></span>
                    <span class="me-2"><i class="bi bi-person-badge"></i> Assigned: <?= e($view_client['assigned_first'] ? $view_client['assigned_first'] . ' ' . $view_client['assigned_last'] : 'Unassigned') ?></span>
                    <?= status_badge($view_client['status']) ?>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sendCommModal">
                <i class="bi bi-send"></i> Dispatch Notice
            </button>
            <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#enrollServiceModal">
                <i class="bi bi-plus-circle"></i> Enroll Service
            </button>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addBusinessModal">
                <i class="bi bi-building-add"></i> Add Business
            </button>
            <a href="clients.php?edit=<?= $view_client['id'] ?>" class="btn btn-sm btn-secondary-custom"><i class="bi bi-pencil"></i> Edit Profile</a>
            <a href="clients.php" class="btn btn-sm btn-secondary-custom"><i class="bi bi-arrow-left"></i> All Clients</a>
        </div>
    </div>

    <!-- AI 360-Degree Intelligence Briefing Card -->
    <div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color:#f8fafc;">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-3 py-2 fs-6"><i class="bi bi-robot me-1"></i> SPS AI Operations Intelligence</span>
                    <span class="badge bg-<?= ($ai_briefing['missing_requirements_count'] > 0 || $ai_briefing['missing_statements_count'] > 0) ? 'warning text-dark' : 'success' ?>">
                        <?= ($ai_briefing['missing_requirements_count'] > 0 || $ai_briefing['missing_statements_count'] > 0) ? 'Prerequisites Pending' : 'Compliance Up to Date' ?>
                    </span>
                </div>
                <div class="text-end small text-light opacity-75">
                    Live Operational Synthesis
                </div>
            </div>
            
            <div class="mt-3 fs-6 lh-base">
                <?= nl2br(e($ai_briefing['summary'])) ?>
            </div>

            <div class="d-flex gap-4 mt-3 pt-3 border-top border-secondary flex-wrap">
                <div><span class="text-white-50 small">Active Services:</span> <strong class="text-white"><?= count($view_services) ?></strong></div>
                <div><span class="text-white-50 small">Missing Prerequisites:</span> <strong class="text-warning"><?= $ai_briefing['missing_requirements_count'] ?></strong></div>
                <div><span class="text-white-50 small">Missing 2026 Statements:</span> <strong class="text-warning"><?= $ai_briefing['missing_statements_count'] ?> mo</strong></div>
                <div><span class="text-white-50 small">Outstanding Balance:</span> <strong class="text-<?= $ai_briefing['total_due'] > 0 ? 'danger' : 'success' ?>">$<?= number_format($ai_briefing['total_due'], 2) ?></strong></div>
            </div>
        </div>
    </div>

    <!-- 360-Degree Tabs Navigation -->
    <ul class="nav nav-tabs mb-4" id="clientWorkspaceTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" href="#services" data-bs-toggle="tab"><i class="bi bi-briefcase me-1"></i> Services & Requirements (<?= count($view_services) ?>)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#businesses" data-bs-toggle="tab"><i class="bi bi-buildings me-1"></i> Businesses & EINs (<?= count($view_businesses) ?>)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#statements" data-bs-toggle="tab"><i class="bi bi-bank me-1"></i> Bank Statements Matrix (<?= count($view_bank_accounts) ?>)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#projects" data-bs-toggle="tab"><i class="bi bi-kanban me-1"></i> Projects & Workflows (<?= count($view_projects) ?>)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#communications" data-bs-toggle="tab"><i class="bi bi-chat-left-text me-1"></i> Omnichannel Timeline (<?= count($view_communications) ?>)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#invoices" data-bs-toggle="tab"><i class="bi bi-receipt me-1"></i> Invoices & Billing (<?= count($view_invoices) ?>)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#relationships" data-bs-toggle="tab"><i class="bi bi-diagram-3 me-1"></i> B2B Network (<?= count($view_relationships) ?>)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#profile" data-bs-toggle="tab"><i class="bi bi-person-lines-fill me-1"></i> Core Profile</a>
        </li>
    </ul>

    <!-- Tab Content Panels -->
    <div class="tab-content">
        <!-- 1. Services & Requirements Fulfillment Panel -->
        <div class="tab-pane fade show active" id="services">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-check2-square text-primary"></i> Service Workflow Fulfillment Matrix</h5>
                <button class="btn btn-sm btn-primary-custom" data-bs-toggle="modal" data-bs-target="#enrollServiceModal">
                    <i class="bi bi-plus"></i> Enroll in New Service
                </button>
            </div>

            <?php if (empty($view_services)): ?>
                <div class="card p-4 text-center text-muted">
                    <i class="bi bi-briefcase display-6 mb-2"></i>
                    <p>No active services configured for this client.</p>
                    <div><button class="btn btn-sm btn-primary-custom" data-bs-toggle="modal" data-bs-target="#enrollServiceModal">Enroll in First Service</button></div>
                </div>
            <?php else: ?>
                <?php foreach ($view_services as $vs): 
                    $reqs = $service_reqs_map[$vs['id']] ?? [];
                    $total_req = count($reqs);
                    $done_req = count(array_filter($reqs, fn($r) => in_array($r['fulfillment_status'], ['fulfilled', 'waived'])));
                    $pct = $total_req > 0 ? round(($done_req / $total_req) * 100) : 100;
                ?>
                    <div class="card mb-4 border shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h6 class="mb-0 fw-bold text-primary"><?= e($vs['service_name']) ?></h6>
                                <small class="text-muted">
                                    Entity: <strong><?= e($vs['business_name'] ?: 'Individual / Main') ?></strong> â€¢ 
                                    Category: <?= e($vs['category']) ?> â€¢ 
                                    Rate: $<?= number_format($vs['custom_rate'] ?: $vs['fixed_price'], 2) ?>
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <div style="width:140px;">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>Fulfillment</span>
                                        <strong><?= $pct ?>%</strong>
                                    </div>
                                    <div class="progress" style="height:6px;">
                                        <div class="progress-bar bg-<?= $pct == 100 ? 'success' : ($pct > 50 ? 'primary' : 'warning') ?>" style="width: <?= $pct ?>%"></div>
                                    </div>
                                </div>
                                <form method="POST" action="" style="display:inline;">
                                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="evaluate_service">
                                    <input type="hidden" name="client_id" value="<?= $view_client['id'] ?>">
                                    <input type="hidden" name="service_id" value="<?= $vs['service_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-play-circle"></i> âš¡ Evaluate Workflow</button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($reqs)): ?>
                                <div class="p-3 text-muted small">No prerequisites defined for this service template.</div>
                            <?php else: ?>
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:30px;">#</th>
                                            <th>Prerequisite Deliverable / Item</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Fulfillment Value / Note</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reqs as $idx => $r): ?>
                                            <tr>
                                                <td><?= $idx + 1 ?></td>
                                                <td>
                                                    <strong><?= e($r['title']) ?></strong>
                                                    <?php if ($r['is_required']): ?>
                                                        <span class="badge bg-danger-subtle text-danger ms-1">Required</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($r['description'])): ?>
                                                        <div class="small text-muted"><?= e($r['description']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-secondary-subtle text-dark"><?= e(str_replace('_', ' ', $r['requirement_type'])) ?></span></td>
                                                <td>
                                                    <?php if ($r['fulfillment_status'] === 'fulfilled'): ?>
                                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Fulfilled</span>
                                                    <?php elseif ($r['fulfillment_status'] === 'waived'): ?>
                                                        <span class="badge bg-info"><i class="bi bi-shield-check me-1"></i> Waived</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark"><i class="bi bi-hourglass me-1"></i> Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small text-muted font-monospace">
                                                    <?= e($r['fulfilled_value'] ?: '-') ?>
                                                    <?php if (!empty($r['fulfilled_at'])): ?>
                                                        <div class="text-muted" style="font-size:11px;"><?= e(format_date($r['fulfilled_at'])) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <form method="POST" action="" style="display:inline;">
                                                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                                            <input type="hidden" name="action" value="update_requirement_status">
                                                            <input type="hidden" name="client_id" value="<?= $view_client['id'] ?>">
                                                            <input type="hidden" name="service_id" value="<?= $vs['service_id'] ?>">
                                                            <input type="hidden" name="requirement_id" value="<?= $r['id'] ?>">
                                                            <input type="hidden" name="status" value="<?= $r['fulfillment_status'] === 'fulfilled' ? 'pending' : 'fulfilled' ?>">
                                                            <button type="submit" class="btn btn-sm btn-<?= $r['fulfillment_status'] === 'fulfilled' ? 'outline-secondary' : 'success' ?>" title="Toggle Fulfilled">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="" style="display:inline;">
                                                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                                            <input type="hidden" name="action" value="update_requirement_status">
                                                            <input type="hidden" name="client_id" value="<?= $view_client['id'] ?>">
                                                            <input type="hidden" name="service_id" value="<?= $vs['service_id'] ?>">
                                                            <input type="hidden" name="requirement_id" value="<?= $r['id'] ?>">
                                                            <input type="hidden" name="status" value="waived">
                                                            <button type="submit" class="btn btn-sm btn-outline-info" title="Waive Requirement">
                                                                <i class="bi bi-shield-x"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 2. Businesses & Entities Panel -->
        <div class="tab-pane fade" id="businesses">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-buildings text-primary"></i> Registered Business Entities</h5>
                <button class="btn btn-sm btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addBusinessModal">
                    <i class="bi bi-plus"></i> Add Business Entity
                </button>
            </div>

            <?php if (empty($view_businesses)): ?>
                <div class="card p-4 text-center text-muted">
                    <p>No business entities registered for this client.</p>
                    <div><button class="btn btn-sm btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addBusinessModal">Add Business</button></div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($view_businesses as $biz): ?>
                        <div class="col-md-6">
                            <div class="card h-100 border shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="fw-bold mb-0"><a href="businesses.php?view=<?= $biz['id'] ?>"><?= e($biz['name']) ?></a></h6>
                                        <span class="badge bg-primary"><?= e($biz['business_type'] ?: 'LLC') ?></span>
                                    </div>
                                    <p class="text-muted small mb-3"><?= e($biz['industry'] ?: 'General Services') ?></p>
                                    <div class="small">
                                        <div><strong>EIN / Tax ID:</strong> <?= e($biz['ein'] ?: 'Not on file') ?></div>
                                        <div><strong>City / State:</strong> <?= e(($biz['city'] ?? '') . ', ' . ($biz['state'] ?? '')) ?></div>
                                        <div><strong>Status:</strong> <?= status_badge($biz['status']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. Bank Statements Matrix Panel -->
        <div class="tab-pane fade" id="statements">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold"><i class="bi bi-bank text-primary"></i> Monthly Bank Statement Collection Grid (<?= $selected_stmt_year ?>)</h5>
                    <small class="text-muted">Monthly PDF statement collection for bookkeeping preparation & tax filing</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addBankAccountModal">
                        <i class="bi bi-plus"></i> Add Bank Account
                    </button>
                </div>
            </div>

            <?php if (empty($view_bank_accounts)): ?>
                <div class="card p-4 text-center text-muted">
                    <i class="bi bi-bank display-6 mb-2"></i>
                    <p>No bank accounts configured for this client's entities.</p>
                    <div><button class="btn btn-sm btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addBankAccountModal">Add Bank Account</button></div>
                </div>
            <?php else: ?>
                <?php foreach ($view_bank_accounts as $acc): ?>
                    <div class="card mb-4 border shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= e($acc['bank_name']) ?></strong> (x-<?= e($acc['account_number_last4']) ?>) â€¢ 
                                <span class="badge bg-secondary"><?= ucfirst($acc['account_type']) ?></span> â€¢ 
                                Entity: <strong><?= e($acc['business_name']) ?></strong>
                            </div>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="send_communication">
                                <input type="hidden" name="client_id" value="<?= $view_client['id'] ?>">
                                <input type="hidden" name="channel" value="email">
                                <input type="hidden" name="subject" value="Missing <?= $selected_stmt_year ?> Bank Statements for <?= e($acc['business_name']) ?>">
                                <input type="hidden" name="message_body" value="Hello <?= e($view_client['first_name']) ?>, we are missing your <?= $selected_stmt_year ?> monthly statements for <?= e($acc['bank_name']) ?> (ending <?= e($acc['account_number_last4']) ?>). Please upload them via your client portal.">
                                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-envelope"></i> Send Statement Reminder</button>
                            </form>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-bordered text-center align-middle mb-0" style="font-size:13px;">
                                    <thead class="table-light">
                                        <tr>
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <th><?= get_month_name($m) ?></th>
                                            <?php endfor; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <?php for ($m = 1; $m <= 12; $m++): 
                                                $stmt = $bank_matrix[$acc['id']][$m] ?? null;
                                                $s_status = $stmt['status'] ?? 'missing';
                                                $s_id = $stmt['id'] ?? 0;
                                            ?>
                                                <td class="p-2">
                                                    <?php if ($s_status === 'received' || $s_status === 'verified'): ?>
                                                        <span class="badge bg-success w-100 py-2 d-block"><i class="bi bi-check-circle"></i> Rec'd</span>
                                                    <?php elseif ($s_status === 'na'): ?>
                                                        <span class="badge bg-secondary w-100 py-2 d-block">N/A</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark w-100 py-2 d-block"><i class="bi bi-exclamation-circle"></i> Missing</span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($s_id): ?>
                                                        <form method="POST" action="" class="mt-1">
                                                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                                            <input type="hidden" name="action" value="update_statement_status">
                                                            <input type="hidden" name="client_id" value="<?= $view_client['id'] ?>">
                                                            <input type="hidden" name="statement_id" value="<?= $s_id ?>">
                                                            <input type="hidden" name="status" value="<?= $s_status === 'missing' ? 'received' : 'missing' ?>">
                                                            <button type="submit" class="btn btn-link p-0 text-muted" style="font-size:11px;">
                                                                <?= $s_status === 'missing' ? 'Mark Rec\'d' : 'Mark Miss' ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 4. Projects & Workflows Panel -->
        <div class="tab-pane fade" id="projects">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-kanban text-primary"></i> Active Work Orders & Projects</h5>
                <a href="projects.php" class="btn btn-sm btn-primary-custom"><i class="bi bi-plus"></i> New Project</a>
            </div>

            <?php if (empty($view_projects)): ?>
                <div class="card p-4 text-center text-muted">No projects currently linked.</div>
            <?php else: ?>
                <table class="table-custom">
                    <thead><tr><th>Project Name</th><th>Service</th><th>Entity</th><th>Status</th><th>Progress</th><th>Due Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($view_projects as $prj): ?>
                            <tr>
                                <td><a href="projects.php?view=<?= $prj['id'] ?>"><strong><?= e($prj['name']) ?></strong></a></td>
                                <td><?= e($prj['service_name'] ?: 'Custom') ?></td>
                                <td><?= e($prj['business_name'] ?: 'Individual') ?></td>
                                <td><?= status_badge($prj['status']) ?></td>
                                <td style="width:140px;">
                                    <div class="progress" style="height:6px;">
                                        <div class="progress-bar" style="width: <?= (int)$prj['progress_value'] ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= (int)$prj['progress_value'] ?>%</small>
                                </td>
                                <td><?= e(format_date($prj['due_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- 5. Omnichannel Timeline Panel -->
        <div class="tab-pane fade" id="communications">
            <div class="row">
                <div class="col-md-7">
                    <h5 class="mb-3 fw-bold"><i class="bi bi-clock-history text-primary"></i> Unified Client Communication Timeline</h5>
                    <?php if (empty($view_communications)): ?>
                        <div class="card p-4 text-center text-muted">No communications recorded yet.</div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($view_communications as $comm): ?>
                                <div class="card mb-3 border shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div>
                                                <span class="badge bg-<?= $comm['channel'] === 'voice' ? 'danger' : ($comm['channel'] === 'sms' ? 'success' : ($comm['channel'] === 'email' ? 'primary' : 'secondary')) ?>">
                                                    <?= strtoupper($comm['channel']) ?>
                                                </span>
                                                <strong class="ms-2"><?= e($comm['subject'] ?: 'Outbound Notification') ?></strong>
                                            </div>
                                            <small class="text-muted"><?= e(format_date($comm['sent_at'])) ?></small>
                                        </div>
                                        <div class="small text-secondary lh-base mt-2">
                                            <?= nl2br(e($comm['message_body'])) ?>
                                        </div>
                                        <?php if ($comm['call_duration'] > 0): ?>
                                            <div class="small text-muted mt-2"><i class="bi bi-stopwatch"></i> Duration: <?= $comm['call_duration'] ?> seconds</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-5">
                    <div class="card border shadow-sm sticky-top" style="top:20px;">
                        <div class="card-header bg-light fw-bold"><i class="bi bi-send text-primary"></i> Dispatch Outbound Communication</div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="send_communication">
                                <input type="hidden" name="client_id" value="<?= $view_client['id'] ?>">

                                <div class="mb-3">
                                    <label class="form-label-custom">Channel</label>
                                    <select name="channel" class="form-control-custom">
                                        <option value="email">Email</option>
                                        <option value="sms">Twilio SMS</option>
                                        <option value="whatsapp">WhatsApp</option>
                                        <option value="voice">Automated Twilio Voice Call</option>
                                        <option value="note">Internal Staff Note</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label-custom">Subject / Header</label>
                                    <input type="text" name="subject" class="form-control-custom" value="Update from Syed Professional Services">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label-custom">Message Content</label>
                                    <textarea name="message_body" rows="4" class="form-control-custom" placeholder="Type your message or notes here..." required></textarea>
                                </div>

                                <button type="submit" class="btn-primary-custom w-100"><i class="bi bi-send"></i> Send Communication</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Invoices & Billing Panel -->
        <div class="tab-pane fade" id="invoices">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-receipt text-primary"></i> Billing & Invoice Ledger</h5>
                <a href="invoices.php" class="btn btn-sm btn-primary-custom"><i class="bi bi-plus"></i> Create Invoice</a>
            </div>

            <?php if (empty($view_invoices)): ?>
                <div class="card p-4 text-center text-muted">No invoices generated for this client yet.</div>
            <?php else: ?>
                <table class="table-custom">
                    <thead><tr><th>Invoice #</th><th>Date</th><th>Due Date</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($view_invoices as $inv): ?>
                            <tr>
                                <td><a href="invoices.php?view=<?= $inv['id'] ?>"><strong><?= e($inv['invoice_number']) ?></strong></a></td>
                                <td><?= e(format_date($inv['invoice_date'])) ?></td>
                                <td><?= e(format_date($inv['due_date'])) ?></td>
                                <td>$<?= number_format($inv['total_amount'], 2) ?></td>
                                <td>$<?= number_format($inv['amount_paid'], 2) ?></td>
                                <td><strong>$<?= number_format($inv['amount_due'], 2) ?></strong></td>
                                <td><?= status_badge($inv['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- 7. B2B Relationships Panel -->
        <div class="tab-pane fade" id="relationships">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold"><i class="bi bi-diagram-3 text-primary"></i> Corporate & Referral Relationship Map</h5>
                <button class="btn btn-sm btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addRelationshipModal">
                    <i class="bi bi-plus"></i> Link Related Client
                </button>
            </div>

            <?php if (empty($view_relationships)): ?>
                <div class="card p-4 text-center text-muted">No external relationships mapped.</div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($view_relationships as $rel): ?>
                        <div class="col-md-6">
                            <div class="card border shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="fw-bold mb-0"><?= e($rel['rel_first'] . ' ' . $rel['rel_last']) ?></h6>
                                        <span class="badge bg-info text-dark"><?= ucfirst($rel['relationship_type']) ?></span>
                                    </div>
                                    <div class="small text-muted mt-2">Strength: <?= ucfirst($rel['strength']) ?> â€¢ <?= e($rel['notes'] ?: 'No notes') ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 8. Core Profile Panel -->
        <div class="tab-pane fade" id="profile">
            <div class="card border shadow-sm">
                <div class="card-header bg-light fw-bold">General Client Details</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Address:</strong> <?= e($view_client['address_line1'] ?: 'Not set') ?></p>
                            <p><strong>City / State / ZIP:</strong> <?= e(($view_client['city'] ?? '') . ', ' . ($view_client['state'] ?? '') . ' ' . ($view_client['zip'] ?? '')) ?></p>
                            <p><strong>Country:</strong> <?= e($view_client['country'] ?: 'United States') ?></p>
                            <p><strong>Lead Source:</strong> <?= e($view_client['source'] ?: 'Direct Client') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Assigned Account Manager:</strong> <?= e($view_client['assigned_first'] ? $view_client['assigned_first'] . ' ' . $view_client['assigned_last'] : 'Unassigned') ?></p>
                            <p><strong>Created At:</strong> <?= e(format_date($view_client['created_at'])) ?></p>
                            <p><strong>Internal Notes:</strong> <?= nl2br(e($view_client['notes'] ?: 'No notes')) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals for Client Operations -->
    <!-- Modal 1: Enroll Service -->
    <div class="modal fade" id="enrollServiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-briefcase"></i> Enroll in SPS Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="enroll_service">
                    <input type="hidden" name="client_id" value="<?= $view_client['id'] ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label-custom">Select Service Template *</label>
                            <select name="service_id" class="form-control-custom" required>
                                <?php foreach ($all_sps_services as $srv): ?>
                                    <option value="<?= $srv['id'] ?>"><?= e($srv['name']) ?> ($<?= number_format($srv['fixed_price'], 2) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Target Business Entity (Optional)</label>
                            <select name="business_id" class="form-control-custom">
                                <option value="0">Individual / Personal Account</option>
                                <?php foreach ($view_businesses as $biz): ?>
                                    <option value="<?= $biz['id'] ?>"><?= e($biz['name']) ?> (EIN: <?= e($biz['ein']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Custom Agreed Rate ($) (Leave blank for default catalog price)</label>
                            <input type="number" step="0.01" name="custom_rate" class="form-control-custom" placeholder="e.g. 450.00">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Enroll & Start Pipeline</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 2: Add Business Entity -->
    <div class="modal fade" id="addBusinessModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-building"></i> Add Business Entity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_business">
                    <input type="hidden" name="client_id" value="<?= $view_client['id'] ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label-custom">Business Legal Name *</label>
                            <input type="text" name="business_name" class="form-control-custom" placeholder="e.g. Acme Logistics LLC" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">EIN / Federal Tax ID</label>
                            <input type="text" name="ein" class="form-control-custom" placeholder="XX-XXXXXXX">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Entity Structure</label>
                            <select name="business_type" class="form-control-custom">
                                <option value="LLC">Limited Liability Company (LLC)</option>
                                <option value="S-Corp">S-Corporation (1120S)</option>
                                <option value="C-Corp">C-Corporation (1120)</option>
                                <option value="Sole Proprietorship">Sole Proprietorship (Schedule C)</option>
                                <option value="Partnership">Partnership (1065)</option>
                                <option value="Non-Profit">Non-Profit (501c3)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Industry</label>
                            <input type="text" name="industry" class="form-control-custom" placeholder="e.g. Real Estate, Healthcare, Technology">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Save Business Entity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 3: Add Bank Account -->
    <div class="modal fade" id="addBankAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-bank"></i> Add Bank Account & Generate Checklist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_bank_account">
                    <input type="hidden" name="client_id" value="<?= $view_client['id'] ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label-custom">Business Entity *</label>
                            <select name="business_id" class="form-control-custom" required>
                                <?php foreach ($view_businesses as $biz): ?>
                                    <option value="<?= $biz['id'] ?>"><?= e($biz['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Bank Institution Name *</label>
                            <input type="text" name="bank_name" class="form-control-custom" placeholder="e.g. Chase, Bank of America, Wells Fargo" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label-custom">Account Type</label>
                                <select name="account_type" class="form-control-custom">
                                    <option value="checking">Checking</option>
                                    <option value="savings">Savings</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="money_market">Money Market</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-custom">Last 4 Digits *</label>
                                <input type="text" name="account_number_last4" class="form-control-custom" maxlength="4" placeholder="1234" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Register Account & Grid</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php else: ?>

    <!-- Clients List -->
    <div class="page-title">
        <h1><i class="bi bi-people"></i> Clients</h1>
        <button class="btn-primary-custom" onclick="toggleNewClient()"><i class="bi bi-plus"></i> New Client</button>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-people"></i></div>
                <div class="stat-value"><?= $total_clients ?></div>
                <div class="stat-label">Total Clients</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-person-check"></i></div>
                <div class="stat-value"><?= $active_clients ?></div>
                <div class="stat-label">Active Clients</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#f3f4f6;color:#4b5563;"><i class="bi bi-archive"></i></div>
                <div class="stat-value"><?= $archived_clients ?></div>
                <div class="stat-label">Archived</div>
            </div>
        </div>
    </div>
    
    <!-- New/Edit Client Form -->
    <div class="card" id="clientForm" style="display:<?= $edit_client ? 'block' : 'none' ?>;">
        <div class="card-header">
            <?= $edit_client ? 'Edit Client: ' . e($edit_client['first_name'] . ' ' . $edit_client['last_name']) : 'Create New Client' ?>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $edit_client ? 'update' : 'create' ?>">
                <input type="hidden" name="client_id" value="<?= $edit_client['id'] ?? 0 ?>">
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">First Name *</label>
                        <input type="text" name="first_name" class="form-control-custom" value="<?= e($edit_client['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Last Name *</label>
                        <input type="text" name="last_name" class="form-control-custom" value="<?= e($edit_client['last_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Client Type</label>
                        <select name="client_type_id" class="form-control-custom">
                            <option value="0">Select Type</option>
                            <?php foreach ($client_types as $ct): ?>
                                <option value="<?= $ct['id'] ?>" <?= ($edit_client['client_type_id'] ?? 0) == $ct['id'] ? 'selected' : '' ?>>
                                    <?= e($ct['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Email</label>
                        <input type="email" name="email" class="form-control-custom" value="<?= e($edit_client['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Phone</label>
                        <input type="text" name="phone" class="form-control-custom" value="<?= e($edit_client['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Mobile</label>
                        <input type="text" name="mobile" class="form-control-custom" value="<?= e($edit_client['mobile'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label-custom">Address Line 1</label>
                        <input type="text" name="address_line1" class="form-control-custom" value="<?= e($edit_client['address_line1'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Address Line 2</label>
                        <input type="text" name="address_line2" class="form-control-custom" value="<?= e($edit_client['address_line2'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label-custom">City</label>
                        <input type="text" name="city" class="form-control-custom" value="<?= e($edit_client['city'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">State</label>
                        <input type="text" name="state" class="form-control-custom" value="<?= e($edit_client['state'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">ZIP</label>
                        <input type="text" name="zip" class="form-control-custom" value="<?= e($edit_client['zip'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Country</label>
                        <input type="text" name="country" class="form-control-custom" value="<?= e($edit_client['country'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Status</label>
                        <select name="status" class="form-control-custom">
                            <option value="active" <?= ($edit_client['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($edit_client['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="archived" <?= ($edit_client['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Assigned To</label>
                        <select name="assigned_user_id" class="form-control-custom">
                            <option value="0">Unassigned</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($edit_client['assigned_user_id'] ?? 0) == $u['id'] ? 'selected' : '' ?>>
                                    <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Source</label>
                        <input type="text" name="source" class="form-control-custom" value="<?= e($edit_client['source'] ?? '') ?>" placeholder="Referral, Website, etc.">
                    </div>
                </div>
                
                <label class="form-label-custom">Notes</label>
                <textarea name="notes" class="form-control-custom" rows="3"><?= e($edit_client['notes'] ?? '') ?></textarea>
                
                <?php if (!empty($custom_fields)): ?>
                    <h5 class="mt-3 mb-3">Custom Fields</h5>
                    <div class="row">
                        <?php foreach ($custom_fields as $field): ?>
                            <div class="col-md-4">
                                <label class="form-label-custom"><?= e($field['field_label']) ?><?= $field['is_required'] ? ' *' : '' ?></label>
                                <?php if ($field['field_type'] === 'textarea'): ?>
                                    <textarea name="custom_<?= $field['id'] ?>" class="form-control-custom" rows="2"><?= e($edit_custom_values[$field['id']] ?? '') ?></textarea>
                                <?php elseif ($field['field_type'] === 'dropdown'): ?>
                                    <select name="custom_<?= $field['id'] ?>" class="form-control-custom">
                                        <option value="">Select</option>
                                        <?php foreach (explode(',', $field['dropdown_options'] ?? '') as $opt): ?>
                                            <option value="<?= e(trim($opt)) ?>" <?= ($edit_custom_values[$field['id']] ?? '') === trim($opt) ? 'selected' : '' ?>>
                                                <?= e(trim($opt)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($field['field_type'] === 'date'): ?>
                                    <input type="date" name="custom_<?= $field['id'] ?>" class="form-control-custom" value="<?= e($edit_custom_values[$field['id']] ?? '') ?>">
                                <?php elseif ($field['field_type'] === 'checkbox'): ?>
                                    <div class="form-check mt-2">
                                        <input type="checkbox" name="custom_<?= $field['id'] ?>" value="1" class="form-check-input" <?= ($edit_custom_values[$field['id']] ?? '') ? 'checked' : '' ?>>
                                    </div>
                                <?php else: ?>
                                    <input type="<?= $field['field_type'] === 'email' ? 'email' : ($field['field_type'] === 'number' || $field['field_type'] === 'integer' || $field['field_type'] === 'currency' ? 'number' : 'text') ?>" 
                                           name="custom_<?= $field['id'] ?>" 
                                           class="form-control-custom" 
                                           value="<?= e($edit_custom_values[$field['id']] ?? '') ?>"
                                           <?= !empty($field['char_length']) ? 'maxlength="' . (int)$field['char_length'] . '"' : '' ?>>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> <?= $edit_client ? 'Update' : 'Create' ?> Client</button>
                <button type="button" class="btn-secondary-custom" onclick="toggleNewClient()">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control-custom" placeholder="Search clients..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-control-custom">
                        <option value="0">All Types</option>
                        <?php foreach ($client_types as $ct): ?>
                            <option value="<?= $ct['id'] ?>" <?= $type_filter == $ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-control-custom">
                        <option value="">All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="archived" <?= $status_filter === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="assigned" class="form-control-custom">
                        <option value="0">All Staff</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $assigned_filter == $u['id'] ? 'selected' : '' ?>><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Filter</button>
                    <a href="clients.php" class="btn-secondary-custom">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Clients Table -->
    <div class="card">
        <div class="card-header">Clients (<?= $total ?>)</div>
        <div class="card-body">
            <?php if (empty($clients)): ?>
                <p class="text-muted">No clients found.</p>
            <?php else: ?>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><a href="clients.php?view=<?= $client['id'] ?>"><strong><?= e($client['first_name'] . ' ' . $client['last_name']) ?></strong></a></td>
                                <td><?= e($client['client_type_name'] ?? '-') ?></td>
                                <td><?= e($client['email']) ?></td>
                                <td><?= e($client['phone']) ?></td>
                                <td><?= status_badge($client['status']) ?></td>
                                <td><?= e($client['assigned_first'] ? $client['assigned_first'] . ' ' . $client['assigned_last'] : '-') ?></td>
                                <td><?= e(format_date($client['created_at'])) ?></td>
                                <td>
                                    <a href="clients.php?view=<?= $client['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-eye"></i></a>
                                    <a href="clients.php?edit=<?= $client['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-pencil"></i></a>
                                    <?php if ($client['status'] !== 'archived'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Archive this client?')">
                                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                            <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-archive"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?= pagination_links($total_pages, $page, 'clients.php?' . http_build_query(array_filter(['search' => $search, 'type' => $type_filter, 'status' => $status_filter, 'assigned' => $assigned_filter]))) ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleNewClient() {
    const form = document.getElementById('clientForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block' && !form.querySelector('input[name="client_id"]').value) {
        form.querySelectorAll('input[type="text"], input[type="email"], textarea').forEach(i => i.value = '');
        form.querySelectorAll('select').forEach(s => s.value = '0');
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>

