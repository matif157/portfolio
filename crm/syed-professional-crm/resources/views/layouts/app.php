<?php
use App\Core\{Auth,Url};
$title = $title ?? 'SPS CRM';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($title) ?> · SPS CRM</title>
    <link rel="stylesheet" href="<?= Url::asset('assets/css/app.css') ?>">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">SPS <span>CRM</span></div>
        <nav>
            <?php
            $links = [
                'dashboard' => 'Dashboard',
                'clients' => 'Clients',
                'services' => 'Services',
                'cases' => 'Service Cases',
                'projects' => 'Projects',
                'invoices' => 'Invoices',
                'payments' => 'Payments',
                'documents' => 'Documents',
                'tasks' => 'Tasks',
                'comments' => 'Comments',
                'notifications' => 'Notifications',
                'reports' => 'Reports',
                'automation' => 'Automation',
                'configure' => 'Configure',
            ];
            foreach ($links as $u => $n):
            ?>
                <a href="<?= Url::to($u) ?>"><?= htmlspecialchars($n) ?></a>
            <?php endforeach; ?>
        </nav>
        <a class="logout" href="<?= Url::to('logout') ?>">Logout</a>
    </aside>
    <main class="main">
        <header class="top">
            <div>
                <strong><?= htmlspecialchars(Auth::name() ?: 'Guest') ?></strong>
                <small><?= htmlspecialchars(Auth::role() ?: '') ?></small>
            </div>
        </header>
        <section class="content"><?= $content ?? '' ?></section>
    </main>
</div>
</body>
</html>
