<?php
// =============================================
// FILE: clients.php
// PURPOSE: List all clients with search + soft delete
// =============================================

require_once 'log_in.php';
require_login();

$search = trim($_GET['search'] ?? '');
$type   = trim($_GET['type'] ?? '');

$sql = "SELECT * FROM clients WHERE deleted_at IS NULL";
$params = [];

if ($search !== '') {
    $sql .= " AND (client_id LIKE ? OR full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($type !== '') {
    $sql .= " AND client_type = ?";
    $params[] = $type;
}

$sql .= " ORDER BY id DESC LIMIT 300";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Soft delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->prepare("UPDATE clients SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
    log_activity($conn, 'Client', 'Soft Deleted', $id);
    header("Location: clients.php?msg=deleted");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clients - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="sidebar">
    <div class="brand"><?= SITE_NAME ?></div>
    <ul>
        <li><a href="dashboard.php">🏠 <span>Dashboard</span></a></li>
        <li class="active"><a href="clients.php">👥 <span>Clients</span></a></li>
        <li><a href="invoices.php">📄 <span>Invoices</span></a></li>
        <li><a href="activity_logs.php">📜 <span>Activity Logs</span></a></li>
        <li><a href="users.php">👤 <span>Users</span></a></li>
        <li><a href="logout.php">🚪 <span>Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <div class="flex justify-between items-center mb-20">
        <div>
            <h1>Manage Clients</h1>
            <p class="text-muted">Total: <?= count($clients) ?> clients</p>
        </div>
        <a href="add_client.php" class="btn btn-primary">+ Add New Client</a>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div style="background:#d1fae5; color:#065f46; padding:12px 18px; border-radius:8px; margin-bottom:20px;">
            Client moved to trash (soft deleted).
        </div>
    <?php endif; ?>

    <!-- Search & Filter -->
    <form method="GET" class="card" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; padding:18px 22px;">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
               placeholder="Search Client ID, Name, Email, Phone..." 
               style="flex:1; min-width:220px; padding:11px 14px; border:1px solid #e2e8f0; border-radius:8px;">
        
        <select name="type" style="padding:11px 14px; border:1px solid #e2e8f0; border-radius:8px;">
            <option value="">All Types</option>
            <option value="INDIVIDUAL" <?= $type==='INDIVIDUAL'?'selected':'' ?>>INDIVIDUAL</option>
            <option value="CORPORATION" <?= $type==='CORPORATION'?'selected':'' ?>>CORPORATION</option>
            <option value="LLC" <?= $type==='LLC'?'selected':'' ?>>LLC</option>
            <option value="PARTNERSHIP" <?= $type==='PARTNERSHIP'?'selected':'' ?>>PARTNERSHIP</option>
        </select>

        <button type="submit" class="btn btn-primary">Search</button>
        <a href="clients.php" class="btn btn-secondary">Clear</a>
    </form>

    <div class="card" style="padding:0; overflow:hidden;">
        <table>
            <thead>
                <tr>
                    <th>Client ID</th>
                    <th>Full Name</th>
                    <th>Type</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Balance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($clients) > 0): ?>
                    <?php foreach ($clients as $c): ?>
                    <tr>
                        <td><strong><?= show($c['client_id']) ?></strong></td>
                        <td><?= show($c['full_name']) ?></td>
                        <td><span class="badge badge-info"><?= show($c['client_type']) ?></span></td>
                        <td><?= show($c['phone'] ?: $c['phone_cell']) ?></td>
                        <td><?= show($c['email'] ?: $c['email_primary']) ?></td>
                        <td>$<?= number_format((float)$c['unpaid_balance'], 2) ?></td>
                        <td>
                            <a href="view_client.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary">View</a>
                            <a href="edit_client.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                            <a href="clients.php?delete=<?= $c['id'] ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Move this client to trash?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:40px; color:#94a3b8;">
                            No clients found. <a href="add_client.php">Add your first client</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
