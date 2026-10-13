<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'SPS CRM') ?> — <?= e($appName ?? 'SPS CRM') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body>
<aside class="sidebar">
    <div class="brand"><span class="orb">SP</span> SPS CRM</div>
    <nav>
        <div class="nav-label">Main</div>
        <a href="<?= url('/dashboard') ?>" class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
        <a href="<?= url('/clients') ?>" class="nav-item"><i class="bi bi-people"></i> Clients</a>
        <a href="<?= url('/services') ?>" class="nav-item"><i class="bi bi-box-seam"></i> Services</a>
        <a href="<?= url('/estimates') ?>" class="nav-item"><i class="bi bi-file-earmark-text"></i> Estimates</a>
        <a href="<?= url('/invoices') ?>" class="nav-item"><i class="bi bi-receipt"></i> Invoices</a>
        <a href="<?= url('/payments') ?>" class="nav-item"><i class="bi bi-cash-stack"></i> Payments</a>
        <div class="nav-label">System</div>
        <a href="<?= url('/settings') ?>" class="nav-item"><i class="bi bi-sliders"></i> Settings</a>
        <a href="<?= url('/logout') ?>" class="nav-item"><i class="bi bi-box-arrow-left"></i> Logout</a>
    </nav>
</aside>
<div class="main">
    <header class="topbar">
        <h1><?= e($title ?? '') ?></h1>
        <div class="user-chip">
            <?= e($user['full_name'] ?? '') ?>
            <span class="badge bg-primary"><?= e($user['role_name'] ?? '') ?></span>
        </div>
    </header>
    <div class="content">
        <?php
        $msg = \App\Core\Session::getFlash('msg');
        $msgType = \App\Core\Session::getFlash('msg_type', 'info');
        if ($msg): ?>
            <div class="alert alert-<?= e($msgType) ?> alert-dismissible fade show"><?= e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
