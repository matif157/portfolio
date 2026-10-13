<?php
/**
 * =====================================================
 * FILE: resources/views/layouts/app.php
 * PURPOSE: Main SPS CRM frontend layout
 *
 * WORKFLOW:
 *
 * Page
 *   ↓
 * Sidebar
 *   ↓
 * Topbar
 *   ↓
 * Main Content
 *   ↓
 * Footer
 *
 * =====================================================
 */


/*
|--------------------------------------------------------------------------
| PAGE VARIABLES
|--------------------------------------------------------------------------
|
| ?? = PHP null coalescing operator.
|
| Agar child page ne $pageTitle define kiya hai to
| usko use karega.
|
| Agar define nahi kiya to default title use hoga.
|
*/

$pageTitle =
    $pageTitle
    ?? 'Dashboard';


$pageDescription =
    $pageDescription
    ?? 'Syed Professional Services CRM';


/*
|--------------------------------------------------------------------------
| HTML DOCUMENT START
|--------------------------------------------------------------------------
*/

?>

<!DOCTYPE html>

<html
    lang="en"
    dir="ltr"
>

<head>

    <meta charset="UTF-8">

    <!--
    viewport meta tag responsive UI ke liye important hai.
    Is se mobile/tablet par layout properly scale hota hai.
    -->

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($pageTitle) ?>
        -
        SPS CRM
    </title>


    <!--
    htmlspecialchars() = PHP built-in security function.

    Why:
    Page title ko HTML-safe banata hai.

    Future mein title dynamic database se aaye to
    XSS risk reduce hota hai.
    -->

    <link
        rel="stylesheet"
        href="assets/css/app.css"
    >

</head>


<body>


<!--
================================================================
CRM APPLICATION WRAPPER
================================================================
-->

<div class="crm-app">


    <!--
    ==============================================================
    SIDEBAR
    ==============================================================

    Sidebar CRM ke main modules ka permanent navigation
    provide karega.
    -->

    <aside class="sidebar">


        <!--
        Brand area.

        Future mein yahan SPS logo/image add karenge.
        -->

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


        <!--
        MAIN NAVIGATION
        -->

        <nav class="sidebar-nav">


            <div class="nav-section-title">
                WORKSPACE
            </div>


            <a
                href="index.php"
                class="nav-item active"
            >

                <span class="nav-icon">
                    ◈
                </span>

                <span>
                    Dashboard
                </span>

            </a>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">
                    ◉
                </span>

                <span>
                    Clients
                </span>

            </a>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">
                    ◇
                </span>

                <span>
                    Services
                </span>

            </a>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">
                    ◎
                </span>

                <span>
                    Service Cases
                </span>

            </a>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">
                    □
                </span>

                <span>
                    Documents
                </span>

            </a>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">
                    ✓
                </span>

                <span>
                    Tasks
                </span>

            </a>


            <div class="nav-section-title">
                FINANCE
            </div>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">
                    $
                </span>

                <span>
                    Invoices
                </span>

            </a>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">
                    $
                </span>

                <span>
                    Payments
                </span>

            </a>


            <div class="nav-section-title">
                MANAGEMENT
            </div>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">
                    ▣
                </span>

                <span>
                    Reports
                </span>

            </a>


            <a
                href="#"
                class="nav-item"
            >

                <span class="nav-icon">
                    ⚙
                </span>

                <span>
                    Settings
                </span>

            </a>

        </nav>


        <!--
        ==========================================================
        SIDEBAR FOOTER
        ==========================================================
        -->

        <div class="sidebar-footer">

            <div class="security-status">

                <span class="status-dot"></span>

                <div>

                    <strong>
                        System Secure
                    </strong>

                    <small>
                        SPS CRM
                    </small>

                </div>

            </div>

        </div>

    </aside>



    <!--
    ==============================================================
    MAIN APPLICATION AREA
    ==============================================================
    -->

    <main class="main-area">


        <!--
        ==========================================================
        TOPBAR
        ==========================================================
        -->

        <header class="topbar">


            <div class="topbar-left">

                <button
                    type="button"
                    class="mobile-menu-button"
                >
                    ☰
                </button>


                <div>

                    <div class="breadcrumb">
                        SPS CRM
                        <span>/</span>
                        <?= htmlspecialchars($pageTitle) ?>
                    </div>

                    <div class="page-description">

                        <?= htmlspecialchars($pageDescription) ?>

                    </div>

                </div>

            </div>


            <div class="topbar-right">


                <!--
                Notification button.

                Backend later unread notifications ka count
                yahan dynamically show karega.
                -->

                <button
                    type="button"
                    class="topbar-button"
                    title="Notifications"
                >
                    ♢

                    <span class="notification-badge">
                        3
                    </span>

                </button>


                <!--
                User profile area.

                Future backend mein current logged-in user
                ka real name/designation yahan aayega.
                -->

                <div class="user-profile">

                    <div class="user-avatar">
                        A
                    </div>

                    <div class="user-details">

                        <strong>
                            Administrator
                        </strong>

                        <span>
                            SPS Administration
                        </span>

                    </div>

                </div>

            </div>

        </header>



        <!--
        ==========================================================
        PAGE CONTENT
        ==========================================================
        -->

        <section class="content-area">

            <?php

            /*
            |--------------------------------------------------------------------------
            | CHILD PAGE CONTENT
            |--------------------------------------------------------------------------
            |
            | $content variable child page se aayega.
            |
            | Future architecture mein proper View renderer
            | is responsibility ko handle karega.
            |
            */

            if (
                isset($content)
            ) {

                echo $content;

            }

            ?>

        </section>


        <!--
        ==========================================================
        FOOTER
        ==========================================================
        -->

        <footer class="app-footer">

            <span>
                © <?= date('Y') ?> Syed Professional Services
            </span>

            <span>
                SPS CRM
            </span>

        </footer>


    </main>

</div>

</body>

</html>