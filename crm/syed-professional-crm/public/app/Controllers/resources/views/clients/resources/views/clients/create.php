<?php

use App\Core\Url;

$error = $_GET['error'] ?? null;

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Client - SPS CRM</title>

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

                <h1>Add Client</h1>

                <p>
                    Create a new client record.
                </p>

            </div>

            <a
                class="secondary-button"
                href="<?= Url::to('clients') ?>"
            >
                ← Back to Clients
            </a>

        </header>


        <?php if ($error === 'name'): ?>

            <div class="error-message">
                Client name is required.
            </div>

        <?php endif; ?>


        <section class="content-card">

            <form
                method="POST"
                action="<?= Url::to('clients/create') ?>"
            >

                <div class="form-grid">

                    <div class="form-field">

                        <label>
                            Full Name *
                        </label>

                        <input
                            type="text"
                            name="name"
                            placeholder="Enter client name"
                            required
                        >

                    </div>


                    <div class="form-field">

                        <label>
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                            placeholder="+92..."
                        >

                    </div>


                    <div class="form-field">

                        <label>
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            placeholder="client@example.com"
                        >

                    </div>


                    <div class="form-field">

                        <label>
                            CNIC
                        </label>

                        <input
                            type="text"
                            name="cnic"
                            placeholder="XXXXX-XXXXXXX-X"
                        >

                    </div>


                    <div class="form-field">

                        <label>
                            Service
                        </label>

                        <select name="service">

                            <option value="">
                                Select service
                            </option>

                            <option value="Immigration">
                                Immigration
                            </option>

                            <option value="Tax Services">
                                Tax Services
                            </option>

                        </select>

                    </div>


                    <div class="form-field">

                        <label>
                            Status
                        </label>

                        <select name="status">

                            <option value="Active">
                                Active
                            </option>

                            <option value="Pending">
                                Pending
                            </option>

                            <option value="Completed">
                                Completed
                            </option>

                        </select>

                    </div>

                </div>


                <div class="form-actions">

                    <a
                        class="secondary-button"
                        href="<?= Url::to('clients') ?>"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="primary-button"
                    >
                        Save Client
                    </button>

                </div>

            </form>

        </section>

    </main>

</div>

</body>

</html>