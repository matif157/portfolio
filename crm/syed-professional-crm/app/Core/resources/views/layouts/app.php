<?php
/**
 * =====================================================
 * FILE: app.php
 * PURPOSE: CRM ka main visual layout.
 *
 * WORKFLOW:
 * Dashboard content
 *      ↓
 * Main layout
 *      ↓
 * Sidebar + Topbar + Content
 *      ↓
 * Browser UI
 *
 * NOTE:
 * Har page ke liye same sidebar/topbar dobara likhne ki
 * zaroorat nahi hogi.
 * =====================================================
 */

$pageTitle = $pageTitle ?? 'SPS CRM';
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
        <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
        - SPS CRM
    </title>

    <!--
        Main CRM stylesheet.

        Url::asset() ke through public assets ka correct URL
        generate hoga.
    -->
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
            \App\Core\Url::asset('assets/css/app.css'),
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
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

            <div class="brand-text">

                <strong>
                    Syed Professional
                </strong>

                <span>
                    Services CRM
                </span>

            </div>

        </div>


        <!-- Main navigation -->

        <nav class="sidebar-nav">

            <div class="nav-section-title">
                MAIN
            </div>

            <a
                href="<?= \App\Core\Url::to('') ?>"
                class="nav-item active"
            >
                <span class="nav-icon">⌂</span>
                <span>Dashboard</span>
            </a>

            <a href="#" class="nav-item">
                <span class="nav-icon">👥</span>
                <span>Clients</span>
            </a>

            <a href="#" class="nav-item">
                <span class="nav-icon">▣</span>
                <span>Cases</span>
            </a>

            <div class="nav-section-title">
                SERVICES
            </div>

            <a href="#" class="nav-item">
                <span class="nav-icon">₨</span>
                <span>Tax Services</span>
            </a>

            <a href="#" class="nav-item">
                <span class="nav-icon">◎</span>
                <span>Immigration</span>
            </a>

            <a href="#" class="nav-item">
                <span class="nav-icon">▤</span>
                <span>Documents</span>
            </a>

            <div class="nav-section-title">
                FINANCE
            </div>

            <a href="#" class="nav-item">
                <span class="nav-icon">▰</span>
                <span>Invoices</span>
            </a>

            <a href="#" class="nav-item">
                <span class="nav-icon">◉</span>
                <span>Payments</span>
            </a>

            <div class="nav-section-title">
                SYSTEM
            </div>

            <a href="#" class="nav-item">
                <span class="nav-icon">⚙</span>
                <span>Settings</span>
            </a>

        </nav>


        <!-- Sidebar footer -->

        <div class="sidebar-footer">

            <div class="secure-badge">

                <span class="secure-dot"></span>

                <div>
                    <strong>System Secure</strong>
                    <small>Local development</small>
                </div>

            </div>

        </div>

    </aside>


    <!-- =================================================
         MAIN AREA
         ================================================= -->

    <main class="main-area">


        <!-- TOPBAR -->

        <header class="topbar">

            <div class="mobile-menu">
                ☰
            </div>

            <div class="topbar-search">

                <span>⌕</span>

                <input
                    type="text"
                    id="globalSearch"
                    placeholder="Search clients, cases, documents..."
                >

            </div>


            <div class="topbar-actions">

                <button
                    type="button"
                    class="icon-button"
                    title="Notifications"
                >
                    🔔
                    <span class="notification-dot"></span>
                </button>


                <div class="profile">

                    <div class="avatar">
                        MA
                    </div>

                    <div class="profile-info">

                        <strong>
                            <?= htmlspecialchars(
                                $userName ?? 'User',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                        <small>
                            Administrator
                        </small>

                    </div>

                </div>

            </div>

        </header>


        <!-- =================================================
             PAGE CONTENT
             ================================================= -->

        <section class="page-content">

            <?= $content ?? '' ?>

        </section>

    </main>

</div>


<!-- Main JavaScript -->

<script
    src="<?= htmlspecialchars(
        \App\Core\Url::asset('assets/js/app.js'),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
></script>

</body>

</html>