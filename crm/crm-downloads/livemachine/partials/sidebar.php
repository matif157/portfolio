<?php
require_once __DIR__ . '/../classes/Leave.php';
$route = $_GET['route'] ?? 'dashboard';
$pendingLeaves = Leave::pendingCount();
$nav = [
    'dashboard'  => ['Dashboard',  'layout-dashboard'],
    'employees'  => ['Employees',  'users'],
    'attendance' => ['Attendance', 'clipboard-check'],
    'reports'    => ['Reports',    'bar-chart-3'],
    'workinghours' => ['Working Hours', 'clock'],
    'payroll'    => ['Payroll',    'wallet'],
    'leaves'     => ['Leaves',     'calendar-days'],
    'holidays'   => ['Holidays',   'flag'],
    'salary'     => ['Salary Setup','badge-dollar-sign'],
    'settings'   => ['Settings',   'settings'],
];
?>
<aside class="sidebar">
    <div class="brand">
        <i data-lucide="fingerprint"></i>
        <span>Attendance</span>
    </div>
    <nav data-tour="sidebar-nav">
        <?php foreach ($nav as $key => [$label, $icon]): ?>
            <a href="?route=<?= $key ?>" class="<?= $route === $key ? 'active' : '' ?>">
                <i data-lucide="<?= $icon ?>"></i><span><?= $label ?></span>
                <?php if ($key === 'leaves' && $pendingLeaves > 0): ?>
                    <span class="badge late" style="margin-left:auto"><?= $pendingLeaves ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        <a href="?route=logout" style="margin-top:auto">
            <i data-lucide="log-out"></i><span>Logout</span>
        </a>
    </nav>
    <div class="footer">
        Signed in as <strong><?= htmlspecialchars(Auth::user()['name']) ?></strong>
    </div>
</aside>
