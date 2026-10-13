<?php
/**
 * =====================================================
 * FILE: resources/views/dashboard.php
 * PURPOSE: SPS CRM main dashboard
 *
 * WORKFLOW:
 *
 * Dashboard
 *   ↓
 * Business overview
 *   ↓
 * Active service cases
 *   ↓
 * Pending tasks
 *   ↓
 * Documents
 *   ↓
 * Automation activity
 *
 * IMPORTANT:
 * Abhi dashboard dummy/static data use kar raha hai.
 *
 * Backend phase mein ye values database se aayengi.
 * =====================================================
 */


/*
|--------------------------------------------------------------------------
| PAGE CONFIGURATION
|--------------------------------------------------------------------------
*/

$pageTitle =
    'Dashboard';


$pageDescription =
    'Your service operations at a glance';


/*
|--------------------------------------------------------------------------
| DASHBOARD CONTENT
|--------------------------------------------------------------------------
|
| Output buffering PHP ka built-in mechanism hai.
|
| ob_start() HTML output ko temporarily memory mein rakhta hai.
| ob_get_clean() us content ko variable mein return karta hai.
|
| Is se layout file ke andar content inject kar sakte hain.
|
*/

ob_start();

?>

<div class="dashboard-page">


    <!--
    ==========================================================
    WELCOME HEADER
    ==========================================================
    -->

    <div class="page-header">

        <div>

            <span class="eyebrow">
                OPERATIONS CENTER
            </span>

            <h1>
                Good morning, Administrator
            </h1>

            <p>
                Here's what is happening across your
                professional services operation.
            </p>

        </div>


        <div class="page-actions">

            <button
                type="button"
                class="button button-secondary"
            >
                View Reports
            </button>


            <button
                type="button"
                class="button button-primary"
            >
                + New Service Case
            </button>

        </div>

    </div>



    <!--
    ==========================================================
    KPI CARDS
    ==========================================================
    -->

    <div class="stats-grid">


        <div class="stat-card">

            <div class="stat-card-top">

                <span class="stat-label">
                    ACTIVE CLIENTS
                </span>

                <span class="stat-icon">
                    ◉
                </span>

            </div>

            <strong class="stat-value">
                248
            </strong>

            <span class="stat-change positive">
                ↑ 12% this month
            </span>

        </div>


        <div class="stat-card">

            <div class="stat-card-top">

                <span class="stat-label">
                    ACTIVE CASES
                </span>

                <span class="stat-icon">
                    ◇
                </span>

            </div>

            <strong class="stat-value">
                86
            </strong>

            <span class="stat-change">
                14 require attention
            </span>

        </div>


        <div class="stat-card">

            <div class="stat-card-top">

                <span class="stat-label">
                    PENDING DOCUMENTS
                </span>

                <span class="stat-icon">
                    □
                </span>

            </div>

            <strong class="stat-value">
                31
            </strong>

            <span class="stat-change warning">
                8 overdue
            </span>

        </div>


        <div class="stat-card">

            <div class="stat-card-top">

                <span class="stat-label">
                    OUTSTANDING
                </span>

                <span class="stat-icon">
                    $
                </span>

            </div>

            <strong class="stat-value">
                $18,420
            </strong>

            <span class="stat-change">
                23 invoices
            </span>

        </div>

    </div>



    <!--
    ==========================================================
    MAIN DASHBOARD GRID
    ==========================================================
    -->

    <div class="dashboard-grid">


        <!--
        ========================================================
        ACTIVE SERVICE CASES
        ========================================================
        -->

        <section class="panel panel-large">

            <div class="panel-header">

                <div>

                    <span class="panel-kicker">
                        WORK QUEUE
                    </span>

                    <h2>
                        Active Service Cases
                    </h2>

                </div>


                <a
                    href="#"
                    class="panel-link"
                >
                    View all →
                </a>

            </div>


            <div class="case-list">


                <div class="case-row">

                    <div class="case-service-icon">
                        TX
                    </div>

                    <div class="case-info">

                        <strong>
                            Tax Filing — 2025
                        </strong>

                        <span>
                            Client #SP-1024
                        </span>

                    </div>

                    <div class="case-status status-review">
                        Under Review
                    </div>

                    <div class="case-progress">

                        <span>
                            72%
                        </span>

                        <div class="progress-bar">

                            <div
                                class="progress-fill"
                                style="width:72%"
                            ></div>

                        </div>

                    </div>

                </div>



                <div class="case-row">

                    <div class="case-service-icon">
                        IM
                    </div>

                    <div class="case-info">

                        <strong>
                            Immigration Case
                        </strong>

                        <span>
                            Client #SP-1047
                        </span>

                    </div>

                    <div class="case-status status-warning">
                        Documents
                    </div>

                    <div class="case-progress">

                        <span>
                            48%
                        </span>

                        <div class="progress-bar">

                            <div
                                class="progress-fill"
                                style="width:48%"
                            ></div>

                        </div>

                    </div>

                </div>



                <div class="case-row">

                    <div class="case-service-icon">
                        BK
                    </div>

                    <div class="case-info">

                        <strong>
                            Monthly Bookkeeping
                        </strong>

                        <span>
                            Client #SP-1081
                        </span>

                    </div>

                    <div class="case-status status-active">
                        In Progress
                    </div>

                    <div class="case-progress">

                        <span>
                            64%
                        </span>

                        <div class="progress-bar">

                            <div
                                class="progress-fill"
                                style="width:64%"
                            ></div>

                        </div>

                    </div>

                </div>


            </div>

        </section>



        <!--
        ========================================================
        QUICK ACTIONS
        ========================================================
        -->

        <section class="panel">

            <div class="panel-header">

                <div>

                    <span class="panel-kicker">
                        SHORTCUTS
                    </span>

                    <h2>
                        Quick Actions
                    </h2>

                </div>

            </div>


            <div class="quick-actions">


                <button
                    type="button"
                    class="quick-action"
                >

                    <span>
                        +
                    </span>

                    <div>

                        <strong>
                            New Client
                        </strong>

                        <small>
                            Create client profile
                        </small>

                    </div>

                </button>


                <button
                    type="button"
                    class="quick-action"
                >

                    <span>
                        +
                    </span>

                    <div>

                        <strong>
                            New Service
                        </strong>

                        <small>
                            Start guided filing
                        </small>

                    </div>

                </button>


                <button
                    type="button"
                    class="quick-action"
                >

                    <span>
                        ↑
                    </span>

                    <div>

                        <strong>
                            Upload Document
                        </strong>

                        <small>
                            Add client document
                        </small>

                    </div>

                </button>


                <button
                    type="button"
                    class="quick-action"
                >

                    <span>
                        $
                    </span>

                    <div>

                        <strong>
                            Create Invoice
                        </strong>

                        <small>
                            Bill a client
                        </small>

                    </div>

                </button>


            </div>

        </section>

    </div>



    <!--
    ==========================================================
    AUTOMATION ACTIVITY
    ==========================================================
    -->

    <section class="panel">

        <div class="panel-header">

            <div>

                <span class="panel-kicker">
                    AUTOMATION ENGINE
                </span>

                <h2>
                    Recent Activity
                </h2>

            </div>

            <a
                href="#"
                class="panel-link"
            >
                View activity →
            </a>

        </div>


        <div class="activity-list">


            <div class="activity-item">

                <span class="activity-dot"></span>

                <div>

                    <strong>
                        Document request sent automatically
                    </strong>

                    <p>
                        Tax Filing — Client #SP-1024
                    </p>

                </div>

                <time>
                    8 min ago
                </time>

            </div>


            <div class="activity-item">

                <span class="activity-dot"></span>

                <div>

                    <strong>
                        Workflow step completed
                    </strong>

                    <p>
                        Immigration Case — Client #SP-1047
                    </p>

                </div>

                <time>
                    24 min ago
                </time>

            </div>


            <div class="activity-item">

                <span class="activity-dot"></span>

                <div>

                    <strong>
                        Payment reminder scheduled
                    </strong>

                    <p>
                        Invoice #INV-2081
                    </p>

                </div>

                <time>
                    1 hr ago
                </time>

            </div>


        </div>

    </section>


</div>

<?php

/*
|--------------------------------------------------------------------------
| CONTENT CAPTURE
|--------------------------------------------------------------------------
|
| ob_get_clean() = PHP built-in function.
|
| Ye dashboard ka generated HTML $content variable mein
| store karta hai.
|
*/

$content =
    ob_get_clean();


/*
|--------------------------------------------------------------------------
| MAIN APPLICATION LAYOUT
|--------------------------------------------------------------------------
|
| Dashboard ab common CRM shell ke andar render hoga.
|
*/

require_once
    __DIR__ . '/layouts/app.php';