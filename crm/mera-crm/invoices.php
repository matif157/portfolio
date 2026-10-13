<?php
// =============================================
// FILE: invoices.php
// PURPOSE: List all invoices
// =============================================

require_once 'log_in.php';
require_login();

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "SELECT i.*, c.full_name, c.client_id as client_code
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (i.invoice_number LIKE ? OR c.full_name LIKE ? OR c.client_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status !== '') {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY i.id DESC LIMIT 300";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoices - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="sidebar">
    <div class="brand"><?= SITE_NAME ?></div>
    <ul>
        <li><a href="dashboard.php">🏠 <span>Dashboard</span></a></li>
        <li><a href="clients.php">👥 <span>Clients</span></a></li>
        <li class="active"><a href="invoices.php">📄 <span>Invoices</span></a></li>
        <li><a href="activity_logs.php">📜 <span>Activity Logs</span></a></li>
        <li><a href="users.php">👤 <span>Users</span></a></li>
        <li><a href="logout.php">🚪 <span>Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <div class="flex justify-between items-center mb-20">
        <div>
            <h1>Invoices</h1>
            <p class="text-muted">Total: <?= count($invoices) ?> invoices</p>
        </div>
        <a href="add_invoice.php" class="btn btn-primary">+ Create Invoice</a>
    </div>

    <form method="GET" class="card" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; padding:18px 22px;">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
               placeholder="Search Invoice #, Client..." 
               style="flex:1; min-width:220px; padding:11px 14px; border:1px solid #e2e8f0; border-radius:8px;">
        
        <select name="status" style="padding:11px 14px; border:1px solid #e2e8f0; border-radius:8px;">
            <option value="">All Status</option>
            <option value="Unpaid" <?= $status==='Unpaid'?'selected':'' ?>>Unpaid</option>
            <option value="Partial" <?= $status==='Partial'?'selected':'' ?>>Partial</option>
            <option value="Paid" <?= $status==='Paid'?'selected':'' ?>>Paid</option>
        </select>

        <button type="submit" class="btn btn-primary">Search</button>
        <a href="invoices.php" class="btn btn-secondary">Clear</a>
    </form>

    <div class="card" style="padding:0; overflow:hidden;">
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Due</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($invoices) > 0): ?>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><strong><?= show($inv['invoice_number']) ?></strong></td>
                        <td><?= show($inv['full_name']) ?><br><small class="text-muted"><?= show($inv['client_code']) ?></small></td>
                        <td><?= date('d M Y', strtotime($inv['created_date'])) ?></td>
                        <td><?= $inv['due_date'] ? date('d M Y', strtotime($inv['due_date'])) : '—' ?></td>
                        <td>$<?= number_format((float)$inv['grand_total'], 2) ?></td>
                        <td>$<?= number_format((float)$inv['total_paid'], 2) ?></td>
                        <td>$<?= number_format((float)$inv['balance'], 2) ?></td>
                        <td>
                            <?php
                            $badge = 'badge-warning';
                            if ($inv['status'] === 'Paid') $badge = 'badge-success';
                            if ($inv['status'] === 'Partial') $badge = 'badge-info';
                            ?>
                            <span class="badge <?= $badge ?>"><?= show($inv['status']) ?></span>
                        </td>
                        <td>
                            <a href="view_invoice.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-primary">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align:center; padding:40px; color:#94a3b8;">
                            No invoices found. <a href="add_invoice.php">Create your first invoice</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
