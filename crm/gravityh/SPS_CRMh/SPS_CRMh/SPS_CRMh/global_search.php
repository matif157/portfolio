<?php
/**
 * GRAVITY CRM ï¿½ Global Module-Wise Search Engine
 * 
 * Yahan hum authorization-aware multi-module search perform kar rahe hain.
 * User agar koi specific module choose kare to sirf usi me search hogi,
 * warna tamam authorized modules (Clients, Businesses, Leads, Services, Invoices, Tasks, Documents)
 * me parallel query run hogi aur results categorized format me display honge.
 */

require_once __DIR__ . '/auth.php';
require_login();

$query = trim($_GET['q'] ?? '');
$target_module = trim($_GET['module'] ?? 'all');
$results = [];
$total_count = 0;

if (!empty($query)) {
    $search_term = "%{$query}%";

    // 1. Clients Search (Permission: clients.view)
    if (($target_module === 'all' || $target_module === 'clients') && user_can('clients.view')) {
        $stmt = $pdo->prepare("
            SELECT id, client_number, first_name, last_name, company_name, email, phone, status 
            FROM clients 
            WHERE first_name LIKE ? OR last_name LIKE ? OR company_name LIKE ? OR email LIKE ? OR phone LIKE ? OR client_number LIKE ?
            LIMIT 15
        ");
        $stmt->execute([$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
        $clients = $stmt->fetchAll();
        if (!empty($clients)) {
            $results['Clients & Profiles'] = [
                'icon' => '??',
                'type' => 'client',
                'items' => $clients
            ];
            $total_count += count($clients);
        }
    }

    // 2. Businesses Search (Permission: businesses.manage / clients.view)
    if (($target_module === 'all' || $target_module === 'businesses') && (user_can('businesses.manage') || user_can('clients.view'))) {
        $stmt = $pdo->prepare("
            SELECT b.id, b.business_name, b.tax_id, b.entity_type, b.client_id, c.first_name, c.last_name
            FROM businesses b
            JOIN clients c ON b.client_id = c.id
            WHERE b.business_name LIKE ? OR b.tax_id LIKE ? OR b.registration_number LIKE ?
            LIMIT 15
        ");
        $stmt->execute([$search_term, $search_term, $search_term]);
        $businesses = $stmt->fetchAll();
        if (!empty($businesses)) {
            $results['Registered Businesses'] = [
                'icon' => '??',
                'type' => 'business',
                'items' => $businesses
            ];
            $total_count += count($businesses);
        }
    }

    // 3. Leads Search (Permission: leads.manage)
    if (($target_module === 'all' || $target_module === 'leads') && user_can('leads.manage')) {
        $stmt = $pdo->prepare("
            SELECT id, contact_name AS first_name, '' AS last_name, company_name, email, phone, status, source AS lead_source
            FROM leads 
            WHERE contact_name LIKE ? OR company_name LIKE ? OR email LIKE ? OR phone LIKE ?
            LIMIT 15
        ");
        $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
        $leads = $stmt->fetchAll();
        if (!empty($leads)) {
            $results['Prospects & Leads'] = [
                'icon' => '?',
                'type' => 'lead',
                'items' => $leads
            ];
            $total_count += count($leads);
        }
    }

    // 4. Services Search (Permission: services.view)
    if (($target_module === 'all' || $target_module === 'services') && user_can('services.view')) {
        $stmt = $pdo->prepare("
            SELECT id, service_code, title, description, price, recurring_period
            FROM services 
            WHERE title LIKE ? OR service_code LIKE ? OR description LIKE ?
            LIMIT 10
        ");
        $stmt->execute([$search_term, $search_term, $search_term]);
        $services = $stmt->fetchAll();
        if (!empty($services)) {
            $results['Services Catalog'] = [
                'icon' => '??',
                'type' => 'service',
                'items' => $services
            ];
            $total_count += count($services);
        }
    }

    // 5. Invoices Search (Permission: invoices.view)
    if (($target_module === 'all' || $target_module === 'invoices') && user_can('invoices.view')) {
        $stmt = $pdo->prepare("
            SELECT i.id, i.invoice_number, i.total_amount, i.status, i.due_date, c.first_name, c.last_name, c.company_name
            FROM invoices i
            JOIN clients c ON i.client_id = c.id
            WHERE i.invoice_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.company_name LIKE ?
            LIMIT 15
        ");
        $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
        $invoices = $stmt->fetchAll();
        if (!empty($invoices)) {
            $results['Invoices & Billing'] = [
                'icon' => '??',
                'type' => 'invoice',
                'items' => $invoices
            ];
            $total_count += count($invoices);
        }
    }

    // 6. Tasks Search (Permission: tasks.manage)
    if (($target_module === 'all' || $target_module === 'tasks') && user_can('tasks.manage')) {
        $stmt = $pdo->prepare("
            SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date, t.client_id, c.first_name, c.last_name
            FROM tasks t
            LEFT JOIN clients c ON t.client_id = c.id
            WHERE t.title LIKE ? OR t.description LIKE ?
            LIMIT 15
        ");
        $stmt->execute([$search_term, $search_term]);
        $tasks = $stmt->fetchAll();
        if (!empty($tasks)) {
            $results['Tasks & Work Items'] = [
                'icon' => '?',
                'type' => 'task',
                'items' => $tasks
            ];
            $total_count += count($tasks);
        }
    }

    // 7. Documents Search (Permission: documents.manage)
    if (($target_module === 'all' || $target_module === 'documents') && user_can('documents.manage')) {
        $stmt = $pdo->prepare("
            SELECT d.id, d.title, d.file_name, d.category, d.client_id, c.first_name, c.last_name
            FROM documents d
            LEFT JOIN clients c ON d.client_id = c.id
            WHERE d.title LIKE ? OR d.file_name LIKE ? OR d.category LIKE ?
            LIMIT 15
        ");
        $stmt->execute([$search_term, $search_term, $search_term]);
        $documents = $stmt->fetchAll();
        if (!empty($documents)) {
            $results['Documents & Vault'] = [
                'icon' => '??',
                'type' => 'document',
                'items' => $documents
            ];
            $total_count += count($documents);
        }
    }

    log_activity('Global Search', 'search', 0, "Searched query '$query' in module '$target_module' ($total_count matches)", 'stage1_normal');
}

render_header('Global Search Results', 'search');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">?? Global CRM Search</h1>
        <p class="page-subtitle">Module-wise unified search across authorized CRM records</p>
    </div>
</div>

<!-- Search Input Card -->
<div class="card" style="margin-bottom:24px;">
    <form action="global_search.php" method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <div style="flex:2; min-width:260px;">
            <input type="text" name="q" value="<?= e($query) ?>" class="form-control" placeholder="Search keywords (e.g. Smith, Bookkeeping, INV-2026, LLC, Tax)..." required autofocus>
        </div>
        <div style="flex:1; min-width:180px;">
            <select name="module" class="form-control">
                <option value="all" <?= $target_module === 'all' ? 'selected' : '' ?>>?? Search All Modules</option>
                <option value="clients" <?= $target_module === 'clients' ? 'selected' : '' ?>>?? Clients</option>
                <option value="businesses" <?= $target_module === 'businesses' ? 'selected' : '' ?>>?? Businesses</option>
                <option value="leads" <?= $target_module === 'leads' ? 'selected' : '' ?>>? Leads</option>
                <option value="services" <?= $target_module === 'services' ? 'selected' : '' ?>>?? Services</option>
                <option value="invoices" <?= $target_module === 'invoices' ? 'selected' : '' ?>>?? Invoices</option>
                <option value="tasks" <?= $target_module === 'tasks' ? 'selected' : '' ?>>? Tasks</option>
                <option value="documents" <?= $target_module === 'documents' ? 'selected' : '' ?>>?? Documents</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary" style="padding:10px 20px;">Search Records</button>
            <?php if (!empty($query)): ?>
                <a href="global_search.php" class="btn btn-secondary" style="padding:10px 14px;">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (!empty($query)): ?>
    <div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:15px; font-weight:600; color:var(--secondary);">
            Found <strong><?= $total_count ?></strong> results matching "<em><?= e($query) ?></em>" in <?= e($target_module === 'all' ? 'all authorized modules' : ucfirst($target_module)) ?>
        </div>
    </div>

    <?php if (empty($results)): ?>
        <div class="card" style="text-align:center; padding:50px 20px;">
            <div style="font-size:40px; margin-bottom:10px;">??</div>
            <h3 style="font-size:18px; color:var(--secondary); margin-bottom:6px;">No Matching Records Found</h3>
            <p style="color:var(--text-muted); font-size:13px; max-width:460px; margin:0 auto;">
                We couldn't find any authorized data matching your query. Try searching with a different keyword, account number, or broad term.
            </p>
        </div>
    <?php else: ?>
        <?php foreach ($results as $category_name => $cat_data): ?>
            <div class="card" style="margin-bottom:24px;">
                <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                    <span><?= $cat_data['icon'] ?> <?= e($category_name) ?></span>
                    <span class="badge badge-info"><?= count($cat_data['items']) ?> results</span>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <?php if ($cat_data['type'] === 'client'): ?>
                            <thead>
                                <tr>
                                    <th>Client Number</th>
                                    <th>Name</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cat_data['items'] as $item): ?>
                                    <tr>
                                        <td><code><?= e($item['client_number']) ?></code></td>
                                        <td><strong><?= e($item['first_name'] . ' ' . $item['last_name']) ?></strong></td>
                                        <td><?= e($item['company_name'] ?: 'ï¿½') ?></td>
                                        <td><?= e($item['email']) ?><br><span style="font-size:11px; color:var(--text-muted);"><?= e($item['phone']) ?></span></td>
                                        <td><span class="badge badge-<?= $item['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($item['status']) ?></span></td>
                                        <td><a href="client_profile.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-primary">Open Profile ?</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>

                        <?php elseif ($cat_data['type'] === 'business'): ?>
                            <thead>
                                <tr>
                                    <th>Business Name</th>
                                    <th>Entity Type</th>
                                    <th>Tax ID / EIN</th>
                                    <th>Associated Client</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cat_data['items'] as $item): ?>
                                    <tr>
                                        <td><strong><?= e($item['business_name']) ?></strong></td>
                                        <td><span class="badge badge-secondary"><?= e($item['entity_type']) ?></span></td>
                                        <td><code><?= e($item['tax_id'] ?: 'ï¿½') ?></code></td>
                                        <td><?= e($item['first_name'] . ' ' . $item['last_name']) ?></td>
                                        <td><a href="client_profile.php?id=<?= $item['client_id'] ?>#businesses" class="btn btn-sm btn-secondary">View Business ?</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>

                        <?php elseif ($cat_data['type'] === 'lead'): ?>
                            <thead>
                                <tr>
                                    <th>Lead Name</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cat_data['items'] as $item): ?>
                                    <tr>
                                        <td><strong><?= e($item['first_name'] . ' ' . $item['last_name']) ?></strong></td>
                                        <td><?= e($item['company_name'] ?: 'ï¿½') ?></td>
                                        <td><?= e($item['email']) ?></td>
                                        <td><span class="badge badge-info"><?= e($item['lead_source']) ?></span></td>
                                        <td><span class="badge badge-warning"><?= ucfirst($item['status']) ?></span></td>
                                        <td><a href="leads.php?lead_id=<?= $item['id'] ?>" class="btn btn-sm btn-primary">View Lead ?</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>

                        <?php elseif ($cat_data['type'] === 'service'): ?>
                            <thead>
                                <tr>
                                    <th>Service Code</th>
                                    <th>Service Title</th>
                                    <th>Billing Period</th>
                                    <th>Standard Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cat_data['items'] as $item): ?>
                                    <tr>
                                        <td><code><?= e($item['service_code']) ?></code></td>
                                        <td><strong><?= e($item['title']) ?></strong></td>
                                        <td><span class="badge badge-secondary"><?= ucfirst($item['recurring_period']) ?></span></td>
                                        <td><strong>$<?= number_format($item['price'], 2) ?></strong></td>
                                        <td><a href="service_workflow.php?service_id=<?= $item['id'] ?>" class="btn btn-sm btn-secondary">Workflow ?</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>

                        <?php elseif ($cat_data['type'] === 'invoice'): ?>
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Client / Company</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cat_data['items'] as $item): ?>
                                    <tr>
                                        <td><code><?= e($item['invoice_number']) ?></code></td>
                                        <td><strong><?= e($item['first_name'] . ' ' . $item['last_name']) ?></strong> (<?= e($item['company_name'] ?: 'Individual') ?>)</td>
                                        <td><strong>$<?= number_format($item['total_amount'], 2) ?></strong></td>
                                        <td><?= format_date($item['due_date']) ?></td>
                                        <td><span class="badge badge-<?= $item['status'] === 'paid' ? 'success' : 'danger' ?>"><?= ucfirst($item['status']) ?></span></td>
                                        <td><a href="invoice_view.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-primary">View Invoice ?</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>

                        <?php elseif ($cat_data['type'] === 'task'): ?>
                            <thead>
                                <tr>
                                    <th>Task Title</th>
                                    <th>Client</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cat_data['items'] as $item): ?>
                                    <tr>
                                        <td><strong><?= e($item['title']) ?></strong></td>
                                        <td><?= e($item['first_name'] ? $item['first_name'] . ' ' . $item['last_name'] : 'ï¿½') ?></td>
                                        <td><span class="badge badge-<?= $item['priority'] === 'high' ? 'danger' : 'secondary' ?>"><?= ucfirst($item['priority']) ?></span></td>
                                        <td><?= format_date($item['due_date']) ?></td>
                                        <td><span class="badge badge-info"><?= ucfirst($item['status']) ?></span></td>
                                        <td><a href="tasks.php" class="btn btn-sm btn-secondary">Open Tasks ?</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>

                        <?php elseif ($cat_data['type'] === 'document'): ?>
                            <thead>
                                <tr>
                                    <th>Document Title</th>
                                    <th>File Name</th>
                                    <th>Category</th>
                                    <th>Client</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cat_data['items'] as $item): ?>
                                    <tr>
                                        <td><strong><?= e($item['title']) ?></strong></td>
                                        <td><code><?= e($item['file_name']) ?></code></td>
                                        <td><span class="badge badge-info"><?= e($item['category']) ?></span></td>
                                        <td><?= e($item['first_name'] ? $item['first_name'] . ' ' . $item['last_name'] : 'ï¿½') ?></td>
                                        <td><a href="documents.php" class="btn btn-sm btn-secondary">Open Documents ?</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<?php render_footer(); ?>
