<?php
// =============================================
// FILE: view_client.php
// PURPOSE: View single client profile
// =============================================

require_once 'log_in.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    die("Client not found.");
}

log_activity($conn, 'Client', 'Viewed', $id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Profile - <?= show($client['full_name']) ?></title>
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
            <h1><?= show($client['full_name']) ?></h1>
            <p class="text-muted">Client ID: <?= show($client['client_id']) ?></p>
        </div>
        <div class="flex gap-10">
            <a href="edit_client.php?id=<?= $client['id'] ?>" class="btn btn-primary">Edit</a>
            <a href="add_invoice.php?client_id=<?= $client['id'] ?>" class="btn btn-success">Create Invoice</a>
            <a href="clients.php" class="btn btn-secondary">Back</a>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
        <div style="background:#d1fae5; color:#065f46; padding:12px 18px; border-radius:8px; margin-bottom:20px;">
            Client created successfully.
        </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px;">
        <div class="card">
            <h3 style="margin-bottom:18px;">Basic Information</h3>
            <table>
                <tr><td style="width:140px; color:#64748b;">Client ID</td><td><strong><?= show($client['client_id']) ?></strong></td></tr>
                <tr><td style="color:#64748b;">Full Name</td><td><?= show($client['full_name']) ?></td></tr>
                <tr><td style="color:#64748b;">Type</td><td><span class="badge badge-info"><?= show($client['client_type']) ?></span></td></tr>
                <tr><td style="color:#64748b;">Phone</td><td><?= show($client['phone'] ?: $client['phone_cell']) ?></td></tr>
                <tr><td style="color:#64748b;">Email</td><td><?= show($client['email'] ?: $client['email_primary']) ?></td></tr>
                <tr><td style="color:#64748b;">Address</td><td><?= show($client['address']) ?></td></tr>
            </table>
        </div>

        <div class="card">
            <h3 style="margin-bottom:18px;">Financial</h3>
            <table>
                <tr><td style="width:140px; color:#64748b;">Balance</td><td>$<?= number_format((float)$client['balance'], 2) ?></td></tr>
                <tr><td style="color:#64748b;">Unpaid</td><td style="color:#ef4444; font-weight:600;">$<?= number_format((float)$client['unpaid_balance'], 2) ?></td></tr>
                <tr><td style="color:#64748b;">Status</td><td>
                    <?php if ($client['is_active']): ?>
                        <span class="badge badge-success">Active</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Inactive</span>
                    <?php endif; ?>
                </td></tr>
                <tr><td style="color:#64748b;">Created</td><td><?= date('d M Y', strtotime($client['created_at'])) ?></td></tr>
            </table>
        </div>
    </div>
</div>

</body>
</html>
