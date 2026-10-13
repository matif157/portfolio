<?php
/**
 * =====================================================
 * FILE: client.php
 * PURPOSE: Individual client profile
 *
 * WORKFLOW:
 *
 * Clients
 *   ↓
 * Client selected
 *   ↓
 * Client information
 *   ↓
 * Start service
 *
 * Future:
 * Documents, invoices, service history, notes aur
 * automation isi profile ke andar connect honge.
 * =====================================================
 */

require_once dirname(__DIR__) . '/config/config.php';

require_login();


$id =
    (int) ($_GET['id'] ?? 0);


if ($id <= 0) {

    header(
        'Location: ' . APP_URL . '/clients.php'
    );

    exit();
}


$stmt = db()->prepare(
    "SELECT *
     FROM clients
     WHERE id = ?
     LIMIT 1"
);


$stmt->execute([$id]);


$client =
    $stmt->fetch();


if (!$client) {

    http_response_code(404);

    exit('Client not found.');
}

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
        Client | SPS CRM
    </title>

    <link
        rel="stylesheet"
        href="<?= APP_URL ?>/assets/css/app.css"
    >

</head>

<body>

<div class="crm-shell">

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="brand-logo small">
                SPS
            </div>

            <strong>SPS CRM</strong>

        </div>

        <nav>

            <a
                class="nav-link"
                href="<?= APP_URL ?>/index.php"
            >
                ▦ Dashboard
            </a>

            <a
                class="nav-link active"
                href="<?= APP_URL ?>/clients.php"
            >
                ◉ Clients
            </a>

            <a
                class="nav-link"
                href="<?= APP_URL ?>/service.php"
            >
                ◆ File a Service
            </a>

        </nav>

    </aside>


    <main class="main-content">

        <header class="topbar">

            <div>

                <span class="eyebrow">
                    CLIENT PROFILE
                </span>

                <h1>

                    <?= e(
                        trim(
                            ($client['first_name'] ?? '')
                            . ' '
                            . ($client['last_name'] ?? '')
                        )
                    ) ?>

                </h1>

            </div>


            <a
                class="btn btn-primary"
                href="<?= APP_URL ?>/service.php?client_id=<?= (int) $client['id'] ?>"
            >
                + File Service
            </a>

        </header>


        <section class="profile-grid">

            <div class="profile-card">

                <span class="eyebrow">
                    BASIC INFORMATION
                </span>

                <h2>
                    Client Details
                </h2>


                <div class="detail-row">

                    <span>
                        Full Name
                    </span>

                    <strong>

                        <?= e(
                            trim(
                                ($client['first_name'] ?? '')
                                . ' '
                                . ($client['last_name'] ?? '')
                            )
                        ) ?>

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Email
                    </span>

                    <strong>

                        <?= e(
                            $client['email']
                            ?? '—'
                        ) ?>

                    </strong>

                </div>


                <div class="detail-row">

                    <span>
                        Phone
                    </span>

                    <strong>

                        <?= e(
                            $client['phone']
                            ?? '—'
                        ) ?>

                    </strong>

                </div>

            </div>


            <div class="profile-card">

                <span class="eyebrow">
                    NEXT ACTION
                </span>

                <h2>
                    Start a Service
                </h2>

                <p class="muted">
                    Choose a service and the CRM will guide
                    staff through the required information.
                </p>


                <a
                    class="btn btn-primary btn-full"
                    href="<?= APP_URL ?>/service.php?client_id=<?= (int) $client['id'] ?>"
                >
                    Start Guided Filing
                </a>

            </div>

        </section>

    </main>

</div>

</body>

</html>