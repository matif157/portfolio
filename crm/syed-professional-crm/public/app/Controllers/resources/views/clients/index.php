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

    <title>Clients - SPS CRM</title>

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
            <div class="sidebar-logo">SPS</div>

            <div>
                <strong>Syed Professional</strong>
                <span>Services CRM</span>
            </div>
        </div>

        <nav class="sidebar-nav">

            <a href="<?= Url::to('dashboard') ?>">
                Dashboard
            </a>

            <a class="active" href="<?= Url::to('clients') ?>">
                Clients
            </a>

            <a href="#">
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
                <h1>Clients</h1>

                <p>
                    Manage your client records and service cases.
                </p>
            </div>

            <a
                class="primary-button"
                href="<?= Url::to('clients/create') ?>"
            >
                + Add Client
            </a>

        </header>


        <?php if (isset($_GET['created'])): ?>

            <div class="success-message">
                Client form submitted successfully.
            </div>

        <?php endif; ?>


        <section class="content-card">

            <div class="table-header">

                <div>
                    <strong>Client Directory</strong>

                    <span>
                        <?= count($clients) ?> sample records
                    </span>
                </div>

                <input
                    type="search"
                    class="search-input"
                    placeholder="Search clients..."
                >

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                    <tr>

                        <th>ID</th>
                        <th>Client</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Action</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($clients as $client): ?>

                        <tr>

                            <td>
                                #<?= htmlspecialchars(
                                    $client['id'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $client['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $client['phone'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $client['email'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
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
                                    $client['status']
                                ) ?>">

                                    <?= htmlspecialchars(
                                        $client['status'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <button
                                    class="table-action"
                                    type="button"
                                >
                                    View
                                </button>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>

</html>