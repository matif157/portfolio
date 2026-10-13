<?php
$pageTitle = $pageTitle ?? 'CRM';
$pageSubtitle = $pageSubtitle ?? 'Business operations';
$showSearch = $showSearch ?? true;
?>
<div class="topbar">
    <div class="topbar-title">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p><?= htmlspecialchars($pageSubtitle) ?></p>
    </div>
    <div class="topbar-actions">
        <?php if (!empty($showSearch)): ?>
            <input class="topbar-search" type="search" placeholder="Search records...">
        <?php endif; ?>
        <a class="btn btn-outline btn-sm" href="logout.php">Logout</a>
    </div>
</div>
