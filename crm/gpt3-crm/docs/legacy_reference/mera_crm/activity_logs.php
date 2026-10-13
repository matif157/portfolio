<?php
// =============================================
// FILE: activity_logs.php
// PURPOSE: View system activity logs
// =============================================

require_once 'log_in.php';
require_login();

$search = trim($_GET['search'] ?? '');
$module = trim($_GET['module'] ?? '');
$action = trim($_GET['action'] ?? '');

$sql = "SELECT * FROM activity_logs WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (user_name LIKE ? OR record_id LIKE ? OR module_name LIKE ? OR action LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($module !== '') {
    $sql .= " AND module_name = ?";
    $params[] = $module;
}

if ($action !== '') {
    $sql .= " AND action = ?";
    $params[] = $action;
}

$sql .= " ORDER BY id DESC LIMIT 300";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity Logs - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="sidebar">
    <div class="brand"><?= SITE_NAME ?></div>
    <ul>
        <li><a href="dashboard.php">🏠 <span>Dashboard</span></a></li>
        <li><a href="clients.php">👥 <span>Clients</span></a></li>
        <li><a href="invoices.php">📄 <span>Invoices</span></a></li>
        <li class="active"><a href="activity_logs.php">📜 <span>Activity Logs</span></a></li>
        <li><a href="users.php">👤 <span>Users</span></a></li>
        <li><a href="logout.php">🚪 <span>Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <h1>Activity Logs</h1>
    <p class="text-muted mb-20">Total shown: <?= count($logs) ?></p>

    <form method="GET" class="card" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; padding:18px 22px;">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
               placeholder="Search user, action, record..." 
               style="flex:1; min-width:200px; padding:11px 14px; border:1px solid #e2e8f0; border-radius:8px;">

        <select name="module" style="padding:11px 14px; border:1px solid #e2e8f0; border-radius:8px;">
            <option value="">All Modules</option>
            <option value="Invoice" <?= $module==='Invoice'?'selected':'' ?>>Invoice</option>
            <option value="Client" <?= $module==='Client'?'selected':'' ?>>Client</option>
            <option value="Auth" <?= $module==='Auth'?'selected':'' ?>>Auth</option>
        </select>

        <select name="action" style="padding:11px 14px; border:1px solid #e2e8f0; border-radius:8px;">
            <option value="">All Actions</option>
            <option value="Created" <?= $action==='Created'?'selected':'' ?>>Created</option>
            <option value="Updated" <?= $action==='Updated'?'selected':'' ?>>Updated</option>
            <option value="Viewed" <?= $action==='Viewed'?'selected':'' ?>>Viewed</option>
            <option value="Payment Added" <?= $action==='Payment Added'?'selected':'' ?>>Payment Added</option>
            <option value="Login" <?= $action==='Login'?'selected':'' ?>>Login</option>
            <option value="Logout" <?= $action==='Logout'?'selected':'' ?>>Logout</option>
        </select>

        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="activity_logs.php" class="btn btn-secondary">Clear</a>
    </form>

    <div class="card" style="padding:0; overflow:hidden;">
        <table>
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>User</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Record ID</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($logs) > 0): ?>
                    <?php foreach ($logs as $log): ?>
                    <?php
                        $badge = 'badge-info';
                        if ($log['action'] === 'Created') $badge = 'badge-success';
                        if ($log['action'] === 'Updated') $badge = 'badge-warning';
                        if ($log['action'] === 'Payment Added') $badge = 'badge-purple';
                        if (in_array($log['action'], ['Soft Deleted','Deleted'])) $badge = 'badge-danger';
                    ?>
                    <tr>
                        <td><?= date('d M Y, h:i A', strtotime($log['created_at'])) ?></td>
                        <td><?= show($log['user_name']) ?></td>
                        <td><?= show($log['module_name']) ?></td>
                        <td><span class="badge <?= $badge ?>"><?= show($log['action']) ?></span></td>
                        <td><?= show($log['record_id']) ?></td>
                        <td class="text-muted"><?= show($log['ip_address']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">No logs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
