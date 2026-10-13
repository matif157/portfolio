<?php
/**
 * Modern 2050 Header + Sidebar
 * REPLACE your includes/header.php with this file
 * Requires: assets/css/crm2050.css
 */
if (!isset($currentUser)) {
    die('Access denied - include log_in.php first');
}
$pageTitle = $pageTitle ?? 'Dashboard';

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if (preg_match('#/(clients|invoices|payments|notes|banking|cards|reports|reminders|warnings|imports|customizer|services|client_types|error_logs)/?$#', $scriptDir)) {
    $base = dirname($scriptDir);
} else {
    $base = $scriptDir === '/' ? '' : $scriptDir;
}
$base = rtrim($base, '/');

function nav_active($titles, $pageTitle) {
    if (!is_array($titles)) $titles = [$titles];
    foreach ($titles as $t) {
        if ($pageTitle === $t || strpos($pageTitle, $t) !== false) return 'active';
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — mera_crm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/crm2050.css">
</head>
<body>

<div class="sidebar">
    <div class="brand">
        <div class="logo-orb">SP</div>
        <span>mera_crm</span>
    </div>

    <nav class="nav flex-column py-2">
        <div class="nav-section">Main</div>
        <a class="nav-link <?= nav_active('Dashboard', $pageTitle) ?>" href="<?= $base ?>/dashboard.php">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
        <a class="nav-link <?= nav_active(['Client', 'Clients'], $pageTitle) ?>" href="<?= $base ?>/clients/index.php">
            <i class="bi bi-people"></i> Clients
        </a>
        <a class="nav-link <?= nav_active('Invoice', $pageTitle) ?>" href="<?= $base ?>/invoices/index.php">
            <i class="bi bi-receipt"></i> Invoices
        </a>

        <div class="nav-section">Finance</div>
        <a class="nav-link <?= nav_active('Bank', $pageTitle) ?>" href="<?= $base ?>/banking/index.php">
            <i class="bi bi-bank"></i> Banking
        </a>
        <a class="nav-link <?= nav_active('Card', $pageTitle) ?>" href="<?= $base ?>/cards/index.php">
            <i class="bi bi-credit-card"></i> Credit Cards
        </a>
        <a class="nav-link <?= nav_active('Report', $pageTitle) ?>" href="<?= $base ?>/reports/index.php">
            <i class="bi bi-bar-chart-line"></i> Reports
        </a>

        <div class="nav-section">Operations</div>
        <a class="nav-link <?= nav_active('Service', $pageTitle) ?>" href="<?= $base ?>/services/index.php">
            <i class="bi bi-box-seam"></i> Services
        </a>
        <a class="nav-link <?= nav_active('Reminder', $pageTitle) ?>" href="<?= $base ?>/reminders/index.php">
            <i class="bi bi-bell"></i> Reminders
        </a>
        <a class="nav-link <?= nav_active('Warning', $pageTitle) ?>" href="<?= $base ?>/warnings/index.php">
            <i class="bi bi-exclamation-triangle"></i> Warnings
        </a>
        <a class="nav-link <?= nav_active('Client Type', $pageTitle) ?>" href="<?= $base ?>/client_types/index.php">
            <i class="bi bi-tags"></i> Client Types
        </a>

        <div class="nav-section">System</div>
        <?php if (function_exists('has_permission') && has_permission('System', 'customizer')): ?>
        <a class="nav-link <?= nav_active('Customizer', $pageTitle) ?>" href="<?= $base ?>/customizer/index.php">
            <i class="bi bi-sliders"></i> Customizer
        </a>
        <?php else: ?>
        <a class="nav-link <?= nav_active('Customizer', $pageTitle) ?>" href="<?= $base ?>/customizer/index.php">
            <i class="bi bi-sliders"></i> Customizer
        </a>
        <?php endif; ?>

        <?php if (function_exists('has_permission') && has_permission('System', 'users')): ?>
        <a class="nav-link <?= nav_active('User Management', $pageTitle) ?>" href="<?= $base ?>/users.php">
            <i class="bi bi-person-gear"></i> Users
        </a>
        <?php endif; ?>
        <?php if (function_exists('has_permission') && has_permission('System', 'roles')): ?>
        <a class="nav-link <?= nav_active('Roles', $pageTitle) ?>" href="<?= $base ?>/roles.php">
            <i class="bi bi-shield-lock"></i> Roles
        </a>
        <?php endif; ?>
        <?php if (function_exists('has_permission') && has_permission('System', 'logs')): ?>
        <a class="nav-link <?= nav_active('Activity Logs', $pageTitle) ?>" href="<?= $base ?>/activity_logs.php">
            <i class="bi bi-clock-history"></i> Activity Logs
        </a>
        <?php endif; ?>
        <a class="nav-link <?= nav_active('Error Logs', $pageTitle) ?>" href="<?= $base ?>/error_logs/index.php">
            <i class="bi bi-bug"></i> Error Logs
        </a>

        <hr>
        <a class="nav-link" href="<?= $base ?>/logout.php">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </nav>
</div>

<div class="main-content">
    <div class="topbar">
        <h5><?= htmlspecialchars($pageTitle) ?></h5>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small">
                <?= htmlspecialchars($currentUser['full_name']) ?>
                <span class="badge bg-primary ms-1"><?= htmlspecialchars($currentUser['role_name']) ?></span>
            </span>
        </div>
    </div>
    <div class="content-area">
        <?php if (isset($_SESSION['msg'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['msg_type'] ?? 'info') ?> alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="filter:invert(1)"></button>
            </div>
            <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
        <?php endif; ?>
