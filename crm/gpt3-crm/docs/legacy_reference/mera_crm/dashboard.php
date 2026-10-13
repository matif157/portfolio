<?php
// =============================================
// FILE: dashboard.php
// PURPOSE: Main Dashboard after successful login
// =============================================

require_once 'log_in.php';
require_login();   // Force login if not logged in

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');

// Quick stats
$total_clients = $conn->query("SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL")->fetchColumn();
$pending_invoices = $conn->query("SELECT COUNT(*) FROM invoices WHERE status IN ('Unpaid','Partial')")->fetchColumn();
$total_revenue = $conn->query("SELECT COALESCE(SUM(grand_total),0) FROM invoices")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="sidebar">
    <div class="brand"><?= SITE_NAME ?></div>
    <ul>
        <li class="active"><a href="dashboard.php">🏠 <span>Dashboard</span></a></li>
        <li><a href="clients.php">👥 <span>Clients</span></a></li>
        <li><a href="invoices.php">📄 <span>Invoices</span></a></li>
        <li><a href="activity_logs.php">📜 <span>Activity Logs</span></a></li>
        <li><a href="users.php">👤 <span>Users</span></a></li>
        <li><a href="logout.php">🚪 <span>Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <div class="flex justify-between items-center mb-20">
        <div>
            <h1 style="margin-bottom:4px;">Dashboard Overview</h1>
            <p class="text-muted">Welcome back, <?= $user_name ?></p>
        </div>
        <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
    </div>

    <!-- Stats Cards -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:22px; margin-top:10px;">
        <div class="stat-card">
            <h3 style="font-size:14px; color:#64748b; font-weight:500;">Total Active Clients</h3>
            <h1 style="font-size:38px; margin:12px 0; color:#0ea5e9;"><?= number_format($total_clients) ?></h1>
            <p style="color:#10b981; font-size:13px;">Clients in system</p>
        </div>

        <div class="stat-card">
            <h3 style="font-size:14px; color:#64748b; font-weight:500;">Pending Invoices</h3>
            <h1 style="font-size:38px; margin:12px 0; color:#f59e0b;"><?= number_format($pending_invoices) ?></h1>
            <p style="color:#ef4444; font-size:13px;">Need attention</p>
        </div>

        <div class="stat-card">
            <h3 style="font-size:14px; color:#64748b; font-weight:500;">Total Invoiced</h3>
            <h1 style="font-size:38px; margin:12px 0; color:#8b5cf6;">$<?= number_format($total_revenue, 2) ?></h1>
            <p style="color:#22d3ee; font-size:13px;">All time</p>
        </div>
    </div>

    <div class="card" style="margin-top:35px;">
        <h3 style="margin-bottom:12px;">Quick Actions</h3>
        <div class="flex gap-15" style="flex-wrap:wrap;">
            <a href="add_client.php" class="btn btn-primary">+ Add New Client</a>
            <a href="add_invoice.php" class="btn btn-primary">+ Create Invoice</a>
            <a href="clients.php" class="btn btn-secondary">View All Clients</a>
            <a href="invoices.php" class="btn btn-secondary">View All Invoices</a>
        </div>
    </div>
</div>

</body>
</html>
