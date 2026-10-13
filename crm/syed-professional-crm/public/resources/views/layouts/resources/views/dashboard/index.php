<?php

$pageTitle = 'Dashboard';

ob_start();

?>

<div class="page-header">

    <div>
        <div class="eyebrow">
            OVERVIEW
        </div>

        <h1>
            Dashboard
        </h1>

        <p>
            Welcome back. Here's what's happening across SPS today.
        </p>
    </div>

    <div class="page-actions">

        <button class="button secondary">
            Export
        </button>

        <button class="button primary">
            + New Client
        </button>

    </div>

</div>


<!-- SUMMARY -->

<div class="stats-grid">

    <div class="stat-card">

        <div class="stat-icon clients">
            ◉
        </div>

        <div class="stat-content">

            <span class="stat-label">
                Total Clients
            </span>

            <strong class="stat-value">
                7,000+
            </strong>

            <span class="stat-meta positive">
                Active client records
            </span>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon projects">
            □
        </div>

        <div class="stat-content">

            <span class="stat-label">
                Active Projects
            </span>

            <strong class="stat-value">
                —
            </strong>

            <span class="stat-meta">
                Awaiting database connection
            </span>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon invoices">
            ₨
        </div>

        <div class="stat-content">

            <span class="stat-label">
                Outstanding Invoices
            </span>

            <strong class="stat-value">
                —
            </strong>

            <span class="stat-meta">
                Awaiting finance data
            </span>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon services">
            ◆
        </div>

        <div class="stat-content">

            <span class="stat-label">
                Active Services
            </span>

            <strong class="stat-value">
                4
            </strong>

            <span class="stat-meta positive">
                Service engine ready
            </span>

        </div>

    </div>

</div>


<!-- MAIN DASHBOARD -->

<div class="dashboard-grid">


    <section class="panel workflow-panel">

        <div class="panel-header">

            <div>
                <h2>
                    Workflow Overview
                </h2>

                <p>
                    Service and project progress
                </p>
            </div>

            <button class="text-button">
                View all
            </button>

        </div>


        <div class="workflow-empty">

            <div class="empty-icon">
                ◇
            </div>

            <strong>
                Workflow data will appear here
            </strong>

            <p>
                Once services and projects are connected,
                their workflow progress will be shown here.
            </p>

        </div>

    </section>


    <section class="panel activity-panel">

        <div class="panel-header">

            <div>
                <h2>
                    Recent Activity
                </h2>

                <p>
                    Latest CRM events
                </p>
            </div>

            <button class="text-button">
                View timeline
            </button>

        </div>


        <div class="activity-list">

            <div class="activity-item">

                <div class="activity-marker">
                    +
                </div>

                <div>
                    <strong>
                        CRM initialized
                    </strong>

                    <span>
                        System
                    </span>
                </div>

            </div>


            <div class="activity-item">

                <div class="activity-marker">
                    ◉
                </div>

                <div>
                    <strong>
                        Client data migration pending
                    </strong>

                    <span>
                        Migration Engine
                    </span>
                </div>

            </div>


            <div class="activity-item">

                <div class="activity-marker">
                    ◆
                </div>

                <div>
                    <strong>
                        Service engine initialized
                    </strong>

                    <span>
                        System
                    </span>
                </div>

            </div>

        </div>

    </section>


    <section class="panel invoices-panel">

        <div class="panel-header">

            <div>
                <h2>
                    Outstanding Invoices
                </h2>

                <p>
                    Finance overview
                </p>
            </div>

            <button class="text-button">
                View invoices
            </button>

        </div>


        <div class="empty-row">
            Invoice data will connect during the migration phase.
        </div>

    </section>


    <section class="panel notifications-panel">

        <div class="panel-header">

            <div>
                <h2>
                    Notifications
                </h2>

                <p>
                    System alerts and reminders
                </p>
            </div>

            <button class="text-button">
                View all
            </button>

        </div>


        <div class="empty-row">
            No new notifications.
        </div>

    </section>

</div>


<!-- QUICK ACCESS -->

<section class="panel quick-panel">

    <div class="panel-header">

        <div>
            <h2>
                Quick Access
            </h2>

            <p>
                Frequently used CRM areas
            </p>
        </div>

    </div>


    <div class="quick-grid">

        <a href="#" class="quick-card">
            <strong>Clients</strong>
            <span>Manage client records</span>
        </a>

        <a href="#" class="quick-card">
            <strong>Services</strong>
            <span>Manage service workflows</span>
        </a>

        <a href="#" class="quick-card">
            <strong>Projects</strong>
            <span>Track active projects</span>
        </a>

        <a href="#" class="quick-card">
            <strong>Invoices</strong>
            <span>Manage billing and payments</span>
        </a>

        <a href="#" class="quick-card">
            <strong>Documents</strong>
            <span>Secure document management</span>
        </a>

        <a href="#" class="quick-card">
            <strong>Comments & Activity</strong>
            <span>View the complete timeline</span>
        </a>

    </div>

</section>


<?php

$content = ob_get_clean();

require __DIR__ . '/../layouts/app.php';