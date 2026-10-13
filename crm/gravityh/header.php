<?php
/**
 * GRAVITY CRM - Shared Header & Sidebar
 * Phase 1: Foundation
 * 
 * Yahan CRM ka professional header hai jisme:
 * - AI Voice Assistant
 * - AI Chat / Quick Add
 * - Activities
 * - Tasks
 * - Urgent items
 * - Global Search
 * 
 * Sidebar mein enabled modules dikhte hain jo Customizer se control hote hain.
 */

require_once __DIR__ . '/functions.php';

$current_user = current_user();
if (!$current_user) {
    redirect('login.php');
}

$theme_vars = active_theme_vars();
$role_layout = current_role_layout();
$unread_count = unread_notification_count();
$notifications = recent_notifications(8);
$enabled_modules = enabled_modules();
$flashes = get_flashes();

// Global search results (if search query present)
$search_results = [];
$search_query = trim(get('q', ''));
$search_module = get('module', '');

if ($search_query !== '') {
    $search_results = perform_global_search($search_query, $search_module);
}

// Theme Engine: resolve UI preferences (cookie -> org default -> built-in)
$ui_prefs = ui_preferences();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($ui_prefs['theme']) ?>" data-mode="<?= e($ui_prefs['mode']) ?>" data-density="<?= e($ui_prefs['density']) ?>" data-sidebar="<?= e($ui_prefs['sidebar']) ?>" data-motion="<?= e($ui_prefs['motion']) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title ?? APP_NAME) ?> - <?= e(get_setting('app_name', APP_NAME)) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --primary: <?= e($theme_vars['--primary'] ?? '#2563eb') ?>;
            --bg: <?= e($theme_vars['--bg'] ?? '#f1f5f9') ?>;
            --card: <?= e($theme_vars['--card'] ?? '#ffffff') ?>;
            --text: <?= e($theme_vars['--text'] ?? '#0f172a') ?>;
            --sidebar-bg: <?= e($theme_vars['--sidebar-bg'] ?? '#1e293b') ?>;
            --sidebar-text: <?= e($theme_vars['--sidebar-text'] ?? '#e2e8f0') ?>;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        
        /* ===== TOP HEADER ===== */
        .top-header {
            background: var(--card);
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 0.5rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .header-brand {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            white-space: nowrap;
        }
        .header-brand i { margin-right: 0.4rem; }
        
        /* Global Search */
        .global-search {
            flex: 1;
            max-width: 500px;
            position: relative;
        }
        .global-search form {
            display: flex;
            gap: 0.3rem;
        }
        .global-search input[type="text"] {
            flex: 1;
            padding: 0.45rem 0.8rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .global-search select {
            padding: 0.45rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.85rem;
            background: #fff;
        }
        .global-search button {
            padding: 0.45rem 0.8rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--card);
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1001;
            display: none;
        }
        .search-results.show { display: block; }
        .search-results .result-group {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .search-results .result-group-title {
            padding: 0.3rem 0.8rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #6b7280;
        }
        .search-results .result-item {
            padding: 0.4rem 0.8rem;
            display: block;
            color: var(--text);
            text-decoration: none;
            font-size: 0.9rem;
        }
        .search-results .result-item:hover { background: #f3f4f6; }
        
        /* Header Icons */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: auto;
        }
        .header-icon-btn {
            position: relative;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #4b5563;
            padding: 0.4rem 0.6rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }
        .header-icon-btn:hover { background: #f3f4f6; color: var(--primary); }
        .header-icon-btn .badge-count {
            position: absolute;
            top: 0;
            right: 0;
            background: #ef4444;
            color: #fff;
            font-size: 0.65rem;
            border-radius: 50%;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }
        
        /* Dropdowns */
        .dropdown-menu-custom {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--card);
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            min-width: 280px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1002;
            display: none;
            margin-top: 0.5rem;
        }
        .dropdown-menu-custom.show { display: block; }
        .dropdown-header {
            padding: 0.8rem 1rem;
            font-weight: 600;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dropdown-item-custom {
            padding: 0.6rem 1rem;
            display: block;
            color: var(--text);
            text-decoration: none;
            font-size: 0.9rem;
            border-bottom: 1px solid #f9fafb;
        }
        .dropdown-item-custom:hover { background: #f3f4f6; }
        .dropdown-item-custom .item-title { font-weight: 500; }
        .dropdown-item-custom .item-time { font-size: 0.75rem; color: #6b7280; }
        .dropdown-footer {
            padding: 0.6rem 1rem;
            text-align: center;
            border-top: 1px solid #f3f4f6;
        }
        .dropdown-footer a { color: var(--primary); text-decoration: none; font-size: 0.85rem; }
        
        /* User Dropdown */
        .user-menu {
            position: relative;
        }
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid var(--primary);
        }
        .user-dropdown {
            min-width: 220px;
        }
        .user-dropdown .user-info {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #f3f4f6;
        }
        .user-dropdown .user-info img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 0.5rem;
        }
        .user-dropdown .user-info .name { font-weight: 600; }
        .user-dropdown .user-info .role { font-size: 0.8rem; color: #6b7280; }
        
        /* ===== SIDEBAR ===== */
        .layout {
            display: flex;
            min-height: calc(100vh - 60px);
        }
        .sidebar {
            width: 240px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 1rem 0;
            flex-shrink: 0;
            overflow-y: auto;
        }
        .sidebar .nav-section {
            padding: 0.5rem 1rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
        }
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.6rem 1rem;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); }
        .sidebar .nav-link.active { background: var(--primary); color: #fff; }
        .sidebar .nav-link i { width: 20px; text-align: center; }
        
        .main-content {
            flex: 1;
            padding: 1.5rem;
            overflow-x: hidden;
        }
        
        /* ===== COMMON COMPONENTS ===== */
        .card {
            background: var(--card);
            border: 1px solid rgba(0,0,0,0.06);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            margin-bottom: 1rem;
        }
        .card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f3f4f6;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-body { padding: 1.25rem; }
        
        .btn-primary-custom {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary-custom:hover { opacity: 0.9; color: #fff; }
        
        .btn-secondary-custom {
            background: #e5e7eb;
            color: #374151;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary-custom:hover { background: #d1d5db; }
        
        .btn-danger-custom {
            background: #ef4444;
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
        }
        .btn-danger-custom:hover { background: #dc2626; }
        
        .table-custom {
            width: 100%;
            border-collapse: collapse;
        }
        .table-custom th {
            text-align: left;
            padding: 0.75rem 1rem;
            background: #f9fafb;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
        }
        .table-custom td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.9rem;
        }
        .table-custom tr:hover td { background: #f9fafb; }
        
        .badge {
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-secondary { background: #f3f4f6; color: #4b5563; }
        .badge-primary { background: #dbeafe; color: #1e40af; }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        
        .form-control-custom {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }
        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-label-custom {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 0.3rem;
            color: #374151;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-card {
            background: var(--card);
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid rgba(0,0,0,0.06);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
        }
        .stat-card .stat-value { font-size: 1.5rem; font-weight: 700; }
        .stat-card .stat-label { font-size: 0.85rem; color: #6b7280; }
        
        .pagination ul {
            display: flex;
            list-style: none;
            gap: 0.3rem;
        }
        .pagination a {
            padding: 0.4rem 0.7rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text);
            font-size: 0.85rem;
        }
        .pagination li.active a {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        
        /* Quick Add Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.show { display: flex; }
        .modal-box {
            background: var(--card);
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            padding: 1.5rem;
        }
        .modal-box h3 { margin-bottom: 1rem; }
        .modal-close {
            float: right;
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #6b7280;
        }
        
        /* AI Chat Panel */
        .ai-chat-panel {
            position: fixed;
            right: 0;
            top: 0;
            bottom: 0;
            width: 380px;
            background: var(--card);
            box-shadow: -4px 0 16px rgba(0,0,0,0.1);
            z-index: 1500;
            display: none;
            flex-direction: column;
        }
        .ai-chat-panel.show { display: flex; }
        .ai-chat-header {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
        }
        .ai-chat-messages {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .ai-message {
            max-width: 85%;
            padding: 0.6rem 0.9rem;
            border-radius: 12px;
            font-size: 0.9rem;
        }
        .ai-message.bot {
            background: #f3f4f6;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        .ai-message.user {
            background: var(--primary);
            color: #fff;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        .ai-chat-input {
            padding: 1rem;
            border-top: 1px solid #f3f4f6;
            display: flex;
            gap: 0.5rem;
        }
        .ai-chat-input input {
            flex: 1;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
        }
        .ai-chat-input button {
            padding: 0.5rem 0.9rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .global-search { max-width: 200px; }
            .header-actions { gap: 0.2rem; }
            .main-content { padding: 1rem; }
        }
    </style>
    <!-- Theme Engine: token system, theme packs, components, layout, legacy bridge -->
    <link href="assets/css/design-tokens.css" rel="stylesheet">
    <link href="assets/css/themes.css" rel="stylesheet">
    <link href="assets/css/components.css" rel="stylesheet">
    <link href="assets/css/layout.css" rel="stylesheet">
    <link href="assets/css/legacy-bridge.css" rel="stylesheet">
<?php
// Studio Custom: apply the admin-defined theme record (themes table) to the
// token system so saved custom colors take effect without editing CSS files.
$studio_active = ($ui_prefs['theme'] ?? '') === 'studio';
$studio_colors = $studio_active
    && !empty($theme_vars['--primary']) && !empty($theme_vars['--bg'])
    && !empty($theme_vars['--card']) && !empty($theme_vars['--text']);
?>
<?php if ($studio_colors): ?>
    <style>
    html[data-theme="studio"] {
        --color-primary: <?= e($theme_vars['--primary']) ?>;
        --color-bg: <?= e($theme_vars['--bg']) ?>;
        --color-surface: <?= e($theme_vars['--card']) ?>;
        --color-text: <?= e($theme_vars['--text']) ?>;
        --color-sidebar-bg: <?= e($theme_vars['--sidebar-bg'] ?? '#111827') ?>;
        --color-sidebar-text: <?= e($theme_vars['--sidebar-text'] ?? '#f9fafb') ?>;
    }
    </style>
<?php endif; ?>
    <script>
    /* Theme Engine boot: resolve "auto" color mode before first paint
       so the correct dark/light surface is applied with no flash. */
    window.GRAVITY_PREFS = <?= json_encode($ui_prefs) ?>;
    (function () {
        if (window.GRAVITY_PREFS.mode === 'auto') {
            var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-mode', dark ? 'dark' : 'light');
        }
    })();
    </script>
</head>
<body>
<div class="sidebar-backdrop" data-sidebar-close aria-hidden="true"></div>

<!-- ===== TOP HEADER ===== -->
<header class="top-header">
    <a href="dashboard.php" class="header-brand">
        <i class="bi bi-boxes"></i><?= e(get_setting('app_name', APP_NAME)) ?>
    </a>
    
    <!-- Global Search -->
    <div class="global-search">
        <form method="GET" action="" id="globalSearchForm">
            <input type="text" name="q" placeholder="Search..." value="<?= e($search_query) ?>" id="globalSearchInput" autocomplete="off">
            <select name="module" id="globalSearchModule">
                <option value="">All Modules</option>
                <?php foreach ($enabled_modules as $mod): ?>
                    <option value="<?= e($mod['module_key']) ?>" <?= $search_module === $mod['module_key'] ? 'selected' : '' ?>>
                        <?= e($mod['module_label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit"><i class="bi bi-search"></i></button>
        </form>
        
        <?php if (!empty($search_results)): ?>
        <div class="search-results show">
            <?php foreach ($search_results as $group => $items): ?>
                <?php if (!empty($items)): ?>
                <div class="result-group">
                    <div class="result-group-title"><?= e(ucfirst($group)) ?></div>
                    <?php foreach ($items as $item): ?>
                        <a href="<?= e($item['url']) ?>" class="result-item">
                            <i class="bi bi-<?= e($item['icon'] ?? 'circle') ?>"></i> <?= e($item['title']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Header Actions -->
    <div class="header-actions">
        <!-- Sidebar toggle (Theme Engine: remembered via cookie) -->
        <button class="header-icon-btn" data-sidebar-toggle title="Toggle sidebar" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>

        <!-- Light/Dark quick toggle (Theme Engine) -->
        <button class="header-icon-btn" data-mode-toggle title="Toggle light/dark mode" aria-label="Toggle light or dark mode">
            <i class="bi bi-circle-half"></i>
        </button>

        <!-- AI Voice Assistant -->
        <button class="header-icon-btn" title="AI Voice Assistant" onclick="startVoiceAssistant()">
            <i class="bi bi-mic-fill"></i>
        </button>
        
        <!-- AI Chat -->
        <button class="header-icon-btn" title="AI Chat" onclick="toggleAIChat()">
            <i class="bi bi-robot"></i>
        </button>
        
        <!-- Quick Add -->
        <button class="header-icon-btn" title="Quick Add" onclick="toggleQuickAdd()">
            <i class="bi bi-plus-circle"></i>
        </button>
        
        <!-- Activities -->
        <div class="user-menu">
            <button class="header-icon-btn" title="Activities" onclick="toggleDropdown('activitiesDropdown')">
                <i class="bi bi-clock-history"></i>
            </button>
            <div class="dropdown-menu-custom" id="activitiesDropdown">
                <div class="dropdown-header">
                    Recent Activities
                    <a href="activities.php" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">View All</a>
                </div>
                <?php
                $recent_activities = db_query('SELECT a.*, u.first_name, u.last_name FROM activities a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 8');
                if (empty($recent_activities)): ?>
                    <div class="dropdown-item-custom" style="color:#6b7280;">No activities yet</div>
                <?php else: ?>
                    <?php foreach ($recent_activities as $act): ?>
                        <a href="activities.php" class="dropdown-item-custom">
                            <div class="item-title"><?= e($act['description'] ?? ucfirst($act['action']) . ' ' . $act['module']) ?></div>
                            <div class="item-time"><?= e(time_ago($act['created_at'])) ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tasks -->
        <div class="user-menu">
            <button class="header-icon-btn" title="Tasks" onclick="toggleDropdown('tasksDropdown')">
                <i class="bi bi-check2-square"></i>
            </button>
            <div class="dropdown-menu-custom" id="tasksDropdown">
                <div class="dropdown-header">
                    My Tasks
                    <a href="tasks.php" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">View All</a>
                </div>
                <?php
                $my_tasks = db_query('SELECT * FROM user_progress WHERE user_id = ? AND status != "completed" ORDER BY updated_at DESC LIMIT 8', [$current_user['id']]);
                if (empty($my_tasks)): ?>
                    <div class="dropdown-item-custom" style="color:#6b7280;">No pending tasks</div>
                <?php else: ?>
                    <?php foreach ($my_tasks as $task): ?>
                        <a href="user_progress.php" class="dropdown-item-custom">
                            <div class="item-title"><?= e($task['progress_label'] ?? $task['module']) ?></div>
                            <div class="item-time"><?= e(status_badge($task['status'])) ?> <?= e($task['progress_value']) ?>%</div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Urgent Items -->
        <div class="user-menu">
            <button class="header-icon-btn" title="Urgent Items" onclick="toggleDropdown('urgentDropdown')">
                <i class="bi bi-exclamation-triangle"></i>
            </button>
            <div class="dropdown-menu-custom" id="urgentDropdown">
                <div class="dropdown-header">Urgent Items</div>
                <?php
                $urgent_items = db_query('SELECT * FROM user_progress WHERE user_id = ? AND status = "blocked" ORDER BY updated_at DESC LIMIT 8', [$current_user['id']]);
                if (empty($urgent_items)): ?>
                    <div class="dropdown-item-custom" style="color:#6b7280;">No urgent items</div>
                <?php else: ?>
                    <?php foreach ($urgent_items as $item): ?>
                        <a href="user_progress.php" class="dropdown-item-custom">
                            <div class="item-title"><?= e($item['progress_label'] ?? $item['module']) ?></div>
                            <div class="item-time">Blocked</div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Notifications -->
        <div class="user-menu">
            <button class="header-icon-btn" title="Notifications" onclick="toggleDropdown('notificationsDropdown')">
                <i class="bi bi-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="badge-count"><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu-custom" id="notificationsDropdown">
                <div class="dropdown-header">
                    Notifications
                    <a href="notifications.php" style="font-size:0.8rem;color:var(--primary);text-decoration:none;">View All</a>
                </div>
                <?php if (empty($notifications)): ?>
                    <div class="dropdown-item-custom" style="color:#6b7280;">No notifications</div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <a href="notifications.php" class="dropdown-item-custom">
                            <div class="item-title"><?= e($notif['title']) ?></div>
                            <div class="item-time"><?= e(time_ago($notif['created_at'])) ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- User Menu -->
        <div class="user-menu">
            <img src="<?= e(profile_picture_url($current_user)) ?>" alt="Profile" class="user-avatar" onclick="toggleDropdown('userDropdown')">
            <div class="dropdown-menu-custom user-dropdown" id="userDropdown">
                <div class="user-info">
                    <img src="<?= e(profile_picture_url($current_user)) ?>" alt="Profile">
                    <div class="name"><?= e($current_user['first_name'] . ' ' . $current_user['last_name']) ?></div>
                    <div class="role"><?= e($current_user['role_name']) ?></div>
                </div>
                <a href="profile.php" class="dropdown-item-custom"><i class="bi bi-person"></i> User Profile</a>
                <a href="templates.php" class="dropdown-item-custom"><i class="bi bi-file-earmark-text"></i> Personal Templates</a>
                <a href="user_progress.php" class="dropdown-item-custom"><i class="bi bi-graph-up"></i> My Progress</a>
                <a href="activities.php" class="dropdown-item-custom"><i class="bi bi-clock-history"></i> My Activities</a>
                <a href="tasks.php" class="dropdown-item-custom"><i class="bi bi-check2-square"></i> My Tasks</a>
                <div class="dropdown-footer">
                    <a href="logout.php" style="color:#ef4444;"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- ===== LAYOUT: SIDEBAR + CONTENT ===== -->
<div class="layout">
    <aside class="sidebar">
        <div class="nav-section">Main</div>
        <?php foreach ($enabled_modules as $mod): ?>
            <?php if (module_accessible($mod['module_key'])): ?>
                <a href="<?= e($mod['module_key']) ?>.php" class="nav-link <?= ($active_page ?? '') === $mod['module_key'] ? 'active' : '' ?>">
                    <i class="bi <?= e($mod['icon'] ?? 'bi-circle') ?>"></i>
                    <?= e($mod['module_label']) ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </aside>
    
    <main class="main-content">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>

<!-- ===== QUICK ADD MODAL ===== -->
<div class="modal-overlay" id="quickAddModal">
    <div class="modal-box">
        <button class="modal-close" onclick="toggleQuickAdd()">&times;</button>
        <h3><i class="bi bi-plus-circle"></i> Quick Add</h3>
        <div class="quick-add-options">
            <a href="clients.php?action=add" class="dropdown-item-custom"><i class="bi bi-person-plus"></i> Add Client</a>
            <a href="leads.php?action=add" class="dropdown-item-custom"><i class="bi bi-lightning"></i> Add Lead</a>
            <a href="contacts.php?action=add" class="dropdown-item-custom"><i class="bi bi-person-lines-fill"></i> Add Contact</a>
            <a href="businesses.php?action=add" class="dropdown-item-custom"><i class="bi bi-building"></i> Add Business</a>
            <a href="templates.php?action=add" class="dropdown-item-custom"><i class="bi bi-file-earmark-text"></i> Add Template</a>
        </div>
    </div>
</div>

<!-- ===== AI CHAT PANEL ===== -->
<div class="ai-chat-panel" id="aiChatPanel">
    <div class="ai-chat-header">
        <span><i class="bi bi-robot"></i> AI Assistant</span>
        <button class="modal-close" onclick="toggleAIChat()">&times;</button>
    </div>
    <div class="ai-chat-messages" id="aiChatMessages">
        <div class="ai-message bot">Hello! I'm your GRAVITY AI Assistant. How can I help you today?</div>
    </div>
    <div class="ai-chat-input">
        <input type="text" id="aiChatInput" placeholder="Ask me anything..." onkeydown="if(event.key==='Enter')sendAIChat()">
        <button onclick="sendAIChat()"><i class="bi bi-send"></i></button>
    </div>
</div>

<script>
// ===== DROPDOWN TOGGLES =====
function toggleDropdown(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const isShow = el.classList.contains('show');
    // Close all dropdowns
    document.querySelectorAll('.dropdown-menu-custom').forEach(d => d.classList.remove('show'));
    if (!isShow) el.classList.add('show');
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-menu') && !e.target.closest('.global-search')) {
        document.querySelectorAll('.dropdown-menu-custom').forEach(d => d.classList.remove('show'));
    }
});

// ===== QUICK ADD =====
function toggleQuickAdd() {
    document.getElementById('quickAddModal').classList.toggle('show');
}

// ===== AI CHAT =====
function toggleAIChat() {
    document.getElementById('aiChatPanel').classList.toggle('show');
}

function sendAIChat() {
    const input = document.getElementById('aiChatInput');
    const message = input.value.trim();
    if (!message) return;
    
    const messages = document.getElementById('aiChatMessages');
    messages.innerHTML += '<div class="ai-message user">' + escapeHtml(message) + '</div>';
    input.value = '';
    
    // Send to AI endpoint
    fetch('ai_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'message=' + encodeURIComponent(message) + '&csrf_token=' + encodeURIComponent('<?= csrf_token() ?>')
    })
    .then(r => r.json())
    .then(data => {
        messages.innerHTML += '<div class="ai-message bot">' + escapeHtml(data.response || 'I could not process that request.') + '</div>';
        messages.scrollTop = messages.scrollHeight;
    })
    .catch(() => {
        messages.innerHTML += '<div class="ai-message bot">Sorry, I encountered an error. Please try again.</div>';
    });
}

// ===== AI VOICE ASSISTANT =====
function startVoiceAssistant() {
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        alert('Voice recognition is not supported in this browser. Please use Chrome or Edge.');
        return;
    }
    
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const recognition = new SpeechRecognition();
    recognition.lang = 'en-US';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;
    
    recognition.onresult = function(event) {
        const transcript = event.results[0][0].transcript;
        document.getElementById('aiChatInput').value = transcript;
        toggleAIChat();
        sendAIChat();
    };
    
    recognition.onerror = function(event) {
        alert('Voice recognition error: ' + event.error);
    };
    
    recognition.start();
}

// ===== GLOBAL SEARCH =====
document.getElementById('globalSearchInput')?.addEventListener('input', function() {
    const q = this.value.trim();
    if (q.length < 2) {
        document.querySelector('.search-results')?.classList.remove('show');
        return;
    }
    // Live search via fetch
    const module = document.getElementById('globalSearchModule').value;
    fetch('global_search.php?q=' + encodeURIComponent(q) + '&module=' + encodeURIComponent(module))
        .then(r => r.json())
        .then(data => {
            const container = document.querySelector('.search-results');
            if (!container) return;
            container.innerHTML = '';
            for (const [group, items] of Object.entries(data)) {
                if (items.length === 0) continue;
                let html = '<div class="result-group"><div class="result-group-title">' + escapeHtml(group.charAt(0).toUpperCase() + group.slice(1)) + '</div>';
                items.forEach(item => {
                    html += '<a href="' + escapeHtml(item.url) + '" class="result-item"><i class="bi ' + escapeHtml(item.icon || 'bi-circle') + '"></i> ' + escapeHtml(item.title) + '</a>';
                });
                html += '</div>';
                container.innerHTML += html;
            }
            container.classList.add('show');
        });
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>