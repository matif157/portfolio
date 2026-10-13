<?php
/**
 * SPS Professional Services CRM
 * Manage Clients List
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$type = trim($_GET['type'] ?? '');

$query = "
    SELECT c.*, u.full_name as assigned_staff,
           (SELECT COUNT(*) FROM businesses b WHERE b.client_id = c.id) as business_count,
           (SELECT COALESCE(SUM(balance_due), 0) FROM invoices i WHERE i.client_id = c.id AND i.status IN ('unpaid','overdue','partially_paid')) as unpaid_balance
    FROM clients c
    LEFT JOIN users u ON c.assigned_staff_id = u.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.company_name LIKE ? OR c.email LIKE ? OR c.client_number LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if ($status !== '') {
    $query .= " AND c.status = ?";
    $params[] = $status;
}

if ($type !== '') {
    $query .= " AND c.client_type = ?";
    $params[] = $type;
}

$query .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Custom fields definition for client entity
$custom_fields = $pdo->query("SELECT * FROM custom_fields WHERE entity_type = 'client' ORDER BY sort_order ASC")->fetchAll();

render_header('Clients Management', 'clients');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Client Directory</h1>
        <p class="page-subtitle">Manage professional service client relationships, businesses, and billing balances</p>
    </div>
    <div>
        <a href="add_client.php" class="btn btn-primary">+ Add New Client</a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card">
    <form method="GET" action="clients.php" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
        <div style="flex:2; min-width:200px;">
            <label class="form-label">Search Client</label>
            <input type="text" name="search" class="form-control" placeholder="Name, Company, Email, Client #" value="<?= e($search) ?>">
        </div>

        <div style="flex:1; min-width:150px;">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="prospect" <?= $status === 'prospect' ? 'selected' : '' ?>>Prospect</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div style="flex:1; min-width:150px;">
            <label class="form-label">Client Type</label>
            <select name="type" class="form-control">
                <option value="">All Types</option>
                <option value="company" <?= $type === 'company' ? 'selected' : '' ?>>Company</option>
                <option value="individual" <?= $type === 'individual' ? 'selected' : '' ?>>Individual</option>
            </select>
        </div>

        <div>
            <button type="submit" class="btn btn-secondary">Filter Results</button>
            <a href="clients.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Clients Table -->
<div class="card">
    <div class="card-title">
        <span>Clients List (<?= count($clients) ?>)</span>
    </div>

    <?php if (empty($clients)): ?>
        <p style="text-align:center; padding:30px; color:var(--text-muted);">No clients found matching your search criteria.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Client #</th>
                        <th>Name / Company</th>
                        <th>Type</th>
                        <th>Contact Email & Phone</th>
                        <th>Businesses</th>
                        <th>Unpaid Balance</th>
                        <th>Assigned Staff</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $c): ?>
                        <tr>
                            <td><strong><?= e($c['client_number']) ?></strong></td>
                            <td>
                                <a href="client_profile.php?id=<?= $c['id'] ?>" style="font-weight:600; font-size:14px;">
                                    <?= e($c['company_name'] ? $c['company_name'] : ($c['first_name'] . ' ' . $c['last_name'])) ?>
                                </a>
                                <?php if ($c['company_name']): ?>
                                    <div style="font-size:12px; color:var(--text-muted);">Contact: <?= e($c['first_name'] . ' ' . $c['last_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-secondary"><?= ucfirst($c['client_type']) ?></span>
                            </td>
                            <td>
                                <div><?= e($c['email']) ?></div>
                                <div style="font-size:12px; color:var(--text-muted);"><?= e($c['phone'] ?: 'No phone') ?></div>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= $c['business_count'] ?> Businesses</span>
                            </td>
                            <td>
                                <?php if ($c['unpaid_balance'] > 0): ?>
                                    <span style="color:var(--danger); font-weight:700;"><?= format_currency($c['unpaid_balance']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--success); font-weight:600;">$0.00</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($c['assigned_staff'] ?: 'Unassigned') ?></td>
                            <td>
                                <?php
                                $badgeClass = 'badge-success';
                                if ($c['status'] === 'prospect') $badgeClass = 'badge-warning';
                                if ($c['status'] === 'inactive') $badgeClass = 'badge-danger';
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= ucfirst($c['status']) ?></span>
                            </td>
                            <td>
                                <a href="client_profile.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">Profile</a>
                                <a href="edit_client.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php render_footer(); ?>
