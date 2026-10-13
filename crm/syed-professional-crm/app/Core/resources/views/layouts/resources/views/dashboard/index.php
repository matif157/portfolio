<?php
/**
 * =====================================================
 * FILE: index.php
 * PURPOSE: SPS CRM dashboard ka main UI.
 *
 * WORKFLOW:
 * App.php
 *   ↓
 * Dashboard data
 *   ↓
 * Ye view
 *   ↓
 * Layout
 *   ↓
 * Browser
 *
 * NOTE:
 * Statistics abhi temporary sample values hain.
 * Real database values later repositories se aayengi.
 * =====================================================
 */

ob_start();
?>

<div class="page-header">

    <div>

        <div class="eyebrow">
            OVERVIEW
        </div>

        <h1>
            Good evening, <?= htmlspecialchars(
                $userName ?? 'Atif',
                ENT_QUOTES,
                'UTF-8'
            ) ?> 👋
        </h1>

        <p>
            Welcome back. Here's what's happening across
            your professional services today.
        </p>

    </div>


    <div class="page-actions">

        <button class="btn btn-secondary">
            Export
        </button>

        <button class="btn btn-primary">
            + New Client
        </button>

    </div>

</div>


<!-- =====================================================
     STATISTICS
     ===================================================== -->

<div class="stats-grid">

    <?php foreach ($stats as $stat): ?>

        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-icon <?= htmlspecialchars(
                    $stat['icon'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>">
                    <?php
                    // Simple text icons for now.
                    // Later we can replace these with SVG icon system.
                    echo match ($stat['icon']) {
                        'users' => '👥',
                        'briefcase' => '▣',
                        'tax' => '₨',
                        'globe' => '◎',
                        default => '•'
                    };
                    ?>
                </div>

                <span class="stat-menu">
                    ⋮
                </span>

            </div>

            <div class="stat-value">

                <?= htmlspecialchars(
                    $stat['value'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

            <div class="stat-label">

                <?= htmlspecialchars(
                    $stat['label'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

            <div class="stat-trend">

                <?= htmlspecialchars(
                    $stat['trend'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        </div>

    <?php endforeach; ?>

</div>


<!-- =====================================================
     MAIN DASHBOARD GRID
     ===================================================== -->

<div class="dashboard-grid">


    <!-- SERVICE OVERVIEW -->

    <section class="panel service-panel">

        <div class="panel-header">

            <div>

                <h2>
                    Service Overview
                </h2>

                <p>
                    Your main service workflows
                </p>

            </div>

            <a href="#">
                View all
            </a>

        </div>


        <div class="service-list">

            <?php foreach ($services as $service): ?>

                <div class="service-row">

                    <div class="service-icon <?= htmlspecialchars(
                        $service['class'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">

                        <?php
                        echo match ($service['class']) {
                            'tax' => '₨',
                            'immigration' => '◎',
                            'documents' => '▤',
                            default => '•'
                        };
                        ?>

                    </div>


                    <div class="service-info">

                        <strong>
                            <?= htmlspecialchars(
                                $service['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                        <span>
                            <?= htmlspecialchars(
                                $service['description'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                    </div>


                    <div class="service-count">

                        <?= htmlspecialchars(
                            $service['count'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </section>


    <!-- QUICK ACTIONS -->

    <section class="panel quick-panel">

        <div class="panel-header">

            <div>

                <h2>
                    Quick Actions
                </h2>

                <p>
                    Frequently used actions
                </p>

            </div>

        </div>


        <div class="quick-grid">

            <button class="quick-action">

                <span>👤</span>

                <strong>
                    Add Client
                </strong>

                <small>
                    Create new client
                </small>

            </button>


            <button class="quick-action">

                <span>▣</span>

                <strong>
                    New Case
                </strong>

                <small>
                    Start a service case
                </small>

            </button>


            <button class="quick-action">

                <span>₨</span>

                <strong>
                    New Invoice
                </strong>

                <small>
                    Create invoice
                </small>

            </button>


            <button class="quick-action">

                <span>▤</span>

                <strong>
                    Upload Document
                </strong>

                <small>
                    Add client document
                </small>

            </button>

        </div>

    </section>

</div>


<!-- =====================================================
     RECENT CLIENTS
     ===================================================== -->

<section class="panel clients-panel">

    <div class="panel-header">

        <div>

            <h2>
                Recent Clients
            </h2>

            <p>
                Latest client activity
            </p>

        </div>

        <a href="#">
            View clients
        </a>

    </div>


    <div class="table-wrapper">

        <table class="crm-table">

            <thead>

                <tr>

                    <th>
                        Client
                    </th>

                    <th>
                        Service
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Updated
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php foreach ($recentClients as $client): ?>

                    <tr>

                        <td>

                            <div class="client-cell">

                                <div class="client-avatar">

                                    <?= strtoupper(
                                        substr(
                                            $client['name'],
                                            0,
                                            1
                                        )
                                    ) ?>

                                </div>

                                <strong>
                                    <?= htmlspecialchars(
                                        $client['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                            </div>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $client['service'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <td>

                            <span class="status status-<?= strtolower(
                                htmlspecialchars(
                                    $client['status'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ) ?>">

                                <?= htmlspecialchars(
                                    $client['status'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $client['date'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <td>

                            <button class="table-action">
                                View →
                            </button>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</section>


<!-- =====================================================
     DEVELOPMENT NOTICE
     ===================================================== -->

<div class="development-notice">

    <div class="development-icon">
        ✓
    </div>

    <div>

        <strong>
            CRM foundation is running
        </strong>

        <p>
            This dashboard currently uses temporary sample data.
            Your existing SQL client database will be connected
            after the interface and workflow structure are finalized.
        </p>

    </div>

</div>


<?php

/**
 * Dashboard HTML ko variable mein capture kar rahe hain.
 *
 * ob_get_clean() = PHP built-in function.
 * Output buffer ka content return karta hai.
 */
$content = ob_get_clean();


/**
 * Main layout load kar rahe hain.
 *
 * require = PHP language construct.
 * Dashboard content ko common CRM layout ke andar place karega.
 */
require __DIR__ . '/../layouts/app.php';