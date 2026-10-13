<?php
// =============================================
// FILE: users.php
// PURPOSE: List staff / system users
// =============================================

require_once 'log_in.php';
require_login();

$users = $conn->query("
    SELECT u.*, r.role_name 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    ORDER BY u.id ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="sidebar">
    <div class="brand"><?= SITE_NAME ?></div>
    <ul>
        <li><a href="dashboard.php">🏠 <span>Dashboard</span></a></li>
        <li><a href="clients.php">👥 <span>Clients</span></a></li>
        <li><a href="invoices.php">📄 <span>Invoices</span></a></li>
        <li><a href="activity_logs.php">📜 <span>Activity Logs</span></a></li>
        <li class="active"><a href="users.php">👤 <span>Users</span></a></li>
        <li><a href="logout.php">🚪 <span>Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <div class="flex justify-between items-center mb-20">
        <div>
            <h1>Staff & Users</h1>
            <p class="text-muted">Manage system users and roles</p>
        </div>
    </div>

    <div class="card" style="padding:0; overflow:hidden;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Designation</th>
                    <th>Status</th>
                    <th>Last Login</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><strong><?= show($u['full_name']) ?></strong></td>
                    <td><?= show($u['username']) ?></td>
                    <td><?= show($u['email']) ?></td>
                    <td><span class="badge badge-info"><?= show($u['role_name'] ?? '—') ?></span></td>
                    <td><?= show($u['designation']) ?></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted">
                        <?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Never' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top:25px;">
        <h3>Roles & Permissions</h3>
        <p class="text-muted">Roles and permissions tables are already created in the database.</p>
        <p>Next steps you can add later:</p>
        <ul style="margin:12px 0 0 20px; color:#475569;">
            <li>Add / Edit User form</li>
            <li>Assign roles to users</li>
            <li>Permission check helper function</li>
            <li>Protect pages based on permission</li>
        </ul>
    </div>
</div>

</body>
</html>
