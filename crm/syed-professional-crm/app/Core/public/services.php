<?php
/**
 * =====================================================
 * FILE: services.php
 * PURPOSE: SPS services ko friendly guided interface mein
 * show karna.
 *
 * WORKFLOW:
 *
 * Client
 *    ↓
 * Service Selection
 *    ↓
 * Service Information
 *    ↓
 * Start Service
 *    ↓
 * Guided Workflow
 *
 * FUTURE:
 * Tax aur Immigration ke liye yahin se
 * secure automated workflows launch honge.
 * =====================================================
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application bootstrap
|--------------------------------------------------------------------------
|
| require_once = PHP built-in.
| CRM ke core systems initialize karta hai.
|
*/
$container = require_once dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Url;

/*
|--------------------------------------------------------------------------
| Temporary service catalog
|--------------------------------------------------------------------------
|
| Ye abhi static catalog hai.
|
| Future architecture:
|
| services
|    ↓
| service_workflows
|    ↓
| workflow_steps
|    ↓
| automation_rules
|
*/
$services = [

    [
        'code' => 'TAX',
        'name' => 'Tax Services',
        'description' =>
            'Guided tax preparation workflow with information collection, document tracking and review stages.',
        'category' => 'Tax',
        'level' => 'High Automation',
    ],

    [
        'code' => 'IMM',
        'name' => 'Immigration Services',
        'description' =>
            'Case-based immigration workflow with document collection, review checkpoints and task management.',
        'category' => 'Immigration',
        'level' => 'Controlled Automation',
    ],

    [
        'code' => 'BOOK',
        'name' => 'Bookkeeping',
        'description' =>
            'Recurring bookkeeping workflow for client records, documents, review and reporting.',
        'category' => 'Accounting',
        'level' => 'High Automation',
    ],

    [
        'code' => 'BIZ',
        'name' => 'Business Services',
        'description' =>
            'Business registration and administrative service workflows with guided client steps.',
        'category' => 'Business',
        'level' => 'High Automation',
    ],

];

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
        Services - Syed Professional Services CRM
    </title>

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
                class="nav-item"
            >
                <span>▣</span>
                Dashboard
            </a>

            <a
                href="<?= Url::to('clients.php') ?>"
                class="nav-item"
            >
                <span>👥</span>
                Clients
            </a>

            <a href="#" class="nav-item">
                <span>📁</span>
                Cases
            </a>

            <a
                href="<?= Url::to('services.php') ?>"
                class="nav-item active"
            >
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

        <header class="topbar">

            <div>

                <span class="eyebrow">
                    SERVICE MANAGEMENT
                </span>

                <h1>
                    Services
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


        <section class="dashboard-body">

            <div class="section-heading">

                <div>

                    <span class="eyebrow">
                        SPS SERVICE CATALOG
                    </span>

                    <h2>
                        Start a Professional Service
                    </h2>

                </div>

            </div>


            <!-- =================================================
                 USER GUIDANCE
                 ================================================= -->

            <div class="service-notice">

                <div class="notice-icon">
                    !
                </div>

                <div>

                    <strong>
                        Guided Service Filing
                    </strong>

                    <p>
                        Bhai, service start karne se pehle CRM
                        user ko required information, documents,
                        expected steps aur important warnings
                        clearly show karega.
                    </p>

                </div>

            </div>


            <!-- =================================================
                 SERVICE CARDS
                 ================================================= -->

            <div class="service-grid">

            <?php foreach ($services as $service): ?>

                <article class="service-card">

                    <div class="service-card-top">

                        <div class="service-code">
                            <?= htmlspecialchars($service['code']) ?>
                        </div>

                        <span class="automation-label">
                            <?= htmlspecialchars($service['level']) ?>
                        </span>

                    </div>


                    <div class="service-card-body">

                        <span class="service-category">
                            <?= htmlspecialchars($service['category']) ?>
                        </span>

                        <h3>
                            <?= htmlspecialchars($service['name']) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars($service['description']) ?>
                        </p>

                    </div>


                    <div class="service-card-footer">

                        <button class="primary-button">

                            Start Service
                            →

                        </button>

                    </div>

                </article>

            <?php endforeach; ?>

            </div>


            <!-- =================================================
                 AUTOMATION FLOW
                 ================================================= -->

            <div class="section-heading service-flow-heading">

                <div>

                    <span class="eyebrow">
                        HOW SPS CRM WORKS
                    </span>

                    <h2>
                        Guided Service Workflow
                    </h2>

                </div>

            </div>


            <div class="service-flow">

                <div class="flow-step">

                    <span>
                        01
                    </span>

                    <strong>
                        Select Service
                    </strong>

                    <small>
                        User chooses the required SPS service.
                    </small>

                </div>


                <div class="flow-line"></div>


                <div class="flow-step">

                    <span>
                        02
                    </span>

                    <strong>
                        Client Information
                    </strong>

                    <small>
                        CRM checks required client information.
                    </small>

                </div>


                <div class="flow-line"></div>


                <div class="flow-step">

                    <span>
                        03
                    </span>

                    <strong>
                        Documents
                    </strong>

                    <small>
                        Required documents are identified.
                    </small>

                </div>


                <div class="flow-line"></div>


                <div class="flow-step">

                    <span>
                        04
                    </span>

                    <strong>
                        Review
                    </strong>

                    <small>
                        Staff reviews the case before processing.
                    </small>

                </div>


                <div class="flow-line"></div>


                <div class="flow-step">

                    <span>
                        05
                    </span>

                    <strong>
                        Completion
                    </strong>

                    <small>
                        CRM tracks completion and final status.
                    </small>

                </div>

            </div>

        </section>

    </main>

</div>

</body>

</html>