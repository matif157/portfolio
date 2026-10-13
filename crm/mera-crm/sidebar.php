<?php
// Sidebar component for the CRM.
// Ye reusable layout fragment hai jo har protected page ko same navigation dega.
// Beginner note: is file ko include karna sirf HTML/markup ka hissa hai,
// backend logic ko change nahi karta.

$activePage = $activePage ?? basename($_SERVER['PHP_SELF']);

$navItems = [
    ['key' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => '�', 'href' => 'dashboard.php'],
    ['key' => 'clients.php', 'label' => 'Clients', 'icon' => '??', 'href' => 'clients.php'],
    ['key' => 'client.php', 'label' => 'Client List', 'icon' => '??', 'href' => 'client.php'],
    ['key' => 'invoices.php', 'label' => 'Invoices', 'icon' => '??', 'href' => 'invoices.php'],
    ['key' => 'services.php', 'label' => 'Services', 'icon' => '??', 'href' => 'services.php'],
    ['key' => 'users.php', 'label' => 'Users', 'icon' => '??', 'href' => 'users.php'],
    ['key' => 'roles.php', 'label' => 'Roles', 'icon' => '???', 'href' => 'roles.php'],
    ['key' => 'activity_logs.php', 'label' => 'Activity Logs', 'icon' => '???', 'href' => 'activity_logs.php'],
    ['key' => 'client_types.php', 'label' => 'Client Types', 'icon' => '???', 'href' => 'client_types.php'],
    ['key' => 'projects.php', 'label' => 'Projects', 'icon' => '??', 'href' => 'projects.php'],
    ['key' => 'db-autopilot.php', 'label' => 'DB Auto-Pilot', 'icon' => '???', 'href' => 'db-autopilot.php'],
    ['key' => 'self-healing.php', 'label' => 'Self-Healing', 'icon' => '??', 'href' => 'self-healing.php'],
    ['key' => 'sandbox.php', 'label' => 'Code Sandbox', 'icon' => '??', 'href' => 'sandbox.php'],
];
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">Syed Professional Services</div>
        <div class="sidebar-subtitle">CRM � Business Operations</div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item): ?>
            <a class="sidebar-link <?= ($activePage === $item['key']) ? 'active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
                <span class="icon"><?= htmlspecialchars($item['icon']) ?></span>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a class="sidebar-link" href="logout.php">
            <span class="icon">?</span>
            <span>Logout</span>
        </a>
    </div>
</aside>
