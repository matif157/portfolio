<?php
/**
 * =====================================================
 * FILE: dashboard.php
 * PURPOSE: CRM ka main dashboard UI.
 * WORKFLOW:
 *
 * Sidebar
 *    ↓
 * CRM Modules
 *
 * Topbar
 *    ↓
 * Search / Notifications / User
 *
 * Dashboard
 *    ↓
 * Statistics
 *    ↓
 * Service Workflow
 *    ↓
 * Recent Activity
 *
 * Abhi data demo hai.
 * Future mein ye MySQL + automation engine se آئے گا.
 * =====================================================
 */

use App\Core\Url;

$title = $title ?? 'CRM Dashboard';
$stats = $stats ?? [];
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($title) ?>
        -
        Syed Professional Services
    </title>

    <!--
    |--------------------------------------------------------------------------
    | Main CRM stylesheet
    |--------------------------------------------------------------------------
    |
    | htmlspecialchars() = PHP built-in security function.
    | Yahan title ko safe HTML output mein convert karta hai.
    |
    -->

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(Url::asset('css/app.css')) ?>"
    >

</head>

<body>

<div class="crm-shell">

    <!-- =================================================
         SIDEBAR
         ================================================= -->

    <aside class="sidebar">

        <div class="brand">

            <div class="brand-mark">
                SPS
            </div>

            <div>

                <strong>
                    Syed Professional
                </strong>

                <span>
                    Services CRM
                </span>

            </div>

        </div>

        <div class="sidebar-section">

            <span class="sidebar-label">
                MAIN
            </span>

            <a
                href="<?= Url::to('') ?>"
                class="nav-item active"
            >
                <span>▣</span>
                Dashboard
            </a>

            <a href="#" class="nav-item">
                <span>👥</span>
                Clients
            </a>

            <a href="#" class="nav-item">
                <span>📁</span>
                Cases
            </a>

            <a href="#" class="nav-item">
                <span>⚙</span>
                Services
            </a>

        </div>

        <div class="sidebar-section">

            <span class="sidebar-label">
                OPERATIONS
            </span>

            <a href="#" class="nav-item">
                <span>✓</span>
                Tasks
            </a>

            <a href="#" class="nav-item">
                <span>📄</span>
                Documents
            </a>

            <a href="#" class="nav-item">
                <span>💳</span>
                Billing
            </a>

            <a href="#" class="nav-item">
                <span>🔔</span>
                Notifications
            </a>

        </div>

        <div class="sidebar-section">

            <span class="sidebar-label">
                MANAGEMENT
            </span>

            <a href="#" class="nav-item">
                <span>📊</span>
                Reports
            </a>

            <a href="#" class="nav-item">
                <span>🤖</span>
                Automation
            </a>

            <a href="#" class="nav-item">
                <span>🔐</span>
                Administration
            </a>

        </div>

        <div class="sidebar-bottom">

            <div class="system-status">

                <span class="status-dot"></span>

                <div>

                    <strong>
                        System Online
                    </strong>

                    <small>
                        SPS CRM
                    </small>

                </div>

            </div>

        </div>

    </aside>


    <!-- =================================================
         MAIN CONTENT
         ================================================= -->

    <main class="main-content">

        <!-- TOPBAR -->

        <header class="topbar">

            <div>

                <span class="eyebrow">
                    SYED PROFESSIONAL SERVICES
                </span>

                <h1>
                    Dashboard
                </h1>

            </div>

            <div class="topbar-actions">

                <button class="icon-button">
                    🔔
                </button>

                <div class="user-profile">

                    <div class="avatar">
                        AT
                    </div>

                    <div>

                        <strong>
                            Administrator
                        </strong>

                        <small>
                            System Admin
                        </small>

                    </div>

                </div>

            </div>

        </header>


        <!-- DASHBOARD BODY -->

        <section class="dashboard-body">

            <!-- STATISTICS -->

            <div class="stats-grid">

                <?php foreach ($stats as $stat): ?>

                    <div class="stat-card">

                        <div class="stat-top">

                            <span class="stat-icon">
                                <?= htmlspecialchars($stat['icon']) ?>
                            </span>

                        </div>

                        <strong class="stat-value">
                            <?= htmlspecialchars($stat['value']) ?>
                        </strong>

                        <span class="stat-label">
                            <?= htmlspecialchars($stat['label']) ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            </div>


            <!-- =================================================
                 SERVICE AUTOMATION PREVIEW
                 ================================================= -->

            <div class="section-heading">

                <div>

                    <span class="eyebrow">
                        AUTOMATION ENGINE
                    </span>

                    <h2>
                        Service Workflow Center
                    </h2>

                </div>

                <button class="primary-button">
                    + Start Service
                </button>

            </div>


            <div class="workflow-grid">

                <!-- TAX -->

                <div class="workflow-card">

                    <div class="workflow-icon">
                        TAX
                    </div>

                    <div class="workflow-content">

                        <h3>
                            Tax Services
                        </h3>

                        <p>
                            Guided tax service workflow with
                            document collection and review stages.
                        </p>

                        <div class="workflow-progress">

                            <div class="progress-bar">

                                <span style="width:72%;"></span>

                            </div>

                            <small>
                                72% workflow automation
                            </small>

                        </div>

                    </div>

                </div>


                <!-- IMMIGRATION -->

                <div class="workflow-card">

                    <div class="workflow-icon">
                        IMM
                    </div>

                    <div class="workflow-content">

                        <h3>
                            Immigration Services
                        </h3>

                        <p>
                            Case-driven workflow for documents,
                            forms, tasks and review stages.
                        </p>

                        <div class="workflow-progress">

                            <div class="progress-bar">

                                <span style="width:58%;"></span>

                            </div>

                            <small>
                                58% workflow automation
                            </small>

                        </div>

                    </div>

                </div>


                <!-- GENERAL -->

                <div class="workflow-card">

                    <div class="workflow-icon">
                        SPS
                    </div>

                    <div class="workflow-content">

                        <h3>
                            Other Services
                        </h3>

                        <p>
                            Flexible workflow system for custom
                            professional services.
                        </p>

                        <div class="workflow-progress">

                            <div class="progress-bar">

                                <span style="width:45%;"></span>

                            </div>

                            <small>
                                45% workflow automation
                            </small>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 RECENT ACTIVITY
                 ================================================= -->

            <div class="section-heading activity-heading">

                <div>

                    <span class="eyebrow">
                        LIVE OPERATIONS
                    </span>

                    <h2>
                        Recent Activity
                    </h2>

                </div>

            </div>


            <div class="activity-card">

                <div class="activity-row">

                    <div class="activity-badge">
                        DOC
                    </div>

                    <div class="activity-text">

                        <strong>
                            Client document uploaded
                        </strong>

                        <span>
                            Tax service workflow
                        </span>

                    </div>

                    <time>
                        Just now
                    </time>

                </div>


                <div class="activity-row">

                    <div class="activity-badge">
                        TAX
                    </div>

                    <div class="activity-text">

                        <strong>
                            Tax case moved to review
                        </strong>

                        <span>
                            Workflow automation
                        </span>

                    </div>

                    <time>
                        12 min ago
                    </time>

                </div>


                <div class="activity-row">

                    <div class="activity-badge">
                        IMM
                    </div>

                    <div class="activity-text">

                        <strong>
                            Immigration case updated
                        </strong>

                        <span>
                            Required document received
                        </span>

                    </div>

                    <time>
                        31 min ago
                    </time>

                </div>

            </div>

        </section>

    </main>

</div>

</body>
</html>