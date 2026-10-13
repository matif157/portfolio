<?php

use App\Core\Url;

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Services - SPS CRM</title>

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
            Url::asset('assets/css/app.css'),
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

</head>

<body>

<div class="crm-page">

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="sidebar-logo">
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


        <nav class="sidebar-nav">

            <a href="<?= Url::to('dashboard') ?>">
                Dashboard
            </a>

            <a href="<?= Url::to('clients') ?>">
                Clients
            </a>

            <a
                class="active"
                href="<?= Url::to('services') ?>"
            >
                Services
            </a>

            <a href="#">
                Immigration
            </a>

            <a href="#">
                Tax Services
            </a>

            <a href="#">
                Documents
            </a>

            <a href="#">
                Payments
            </a>

            <a href="#">
                Reports
            </a>

        </nav>


        <div class="sidebar-bottom">

            <a href="<?= Url::to('logout') ?>">
                Logout
            </a>

        </div>

    </aside>


    <main class="main-content">

        <header class="topbar">

            <div>

                <h1>
                    Services
                </h1>

                <p>
                    Manage the professional services offered by SPS.
                </p>

            </div>

        </header>


        <section class="service-grid">

            <?php foreach ($services as $service): ?>

                <article class="service-card">

                    <div class="service-icon">
                        SPS
                    </div>

                    <div class="service-card-content">

                        <div class="service-card-heading">

                            <h2>
                                <?= htmlspecialchars(
                                    $service['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h2>

                            <span class="status status-active">
                                <?= htmlspecialchars(
                                    $service['status'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </div>

                        <div class="service-category">

                            <?= htmlspecialchars(
                                $service['category'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </div>

                        <p>

                            <?= htmlspecialchars(
                                $service['description'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </p>

                        <button
                            type="button"
                            class="table-action"
                        >
                            Open Service
                        </button>

                    </div>

                </article>

            <?php endforeach; ?>

        </section>

    </main>

</div>

</body>

</html>