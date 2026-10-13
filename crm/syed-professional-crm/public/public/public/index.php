<?php
/**
 * =====================================================
 * FILE: index.php
 * PURPOSE: SPS CRM ka temporary main dashboard
 *
 * WORKFLOW:
 *
 * Login
 *   ↓
 * Dashboard
 *   ↓
 * Clients / Services / Documents
 *
 * Abhi dashboard intentionally lightweight hai.
 * Baad mein isi ko complete enterprise dashboard banayenge.
 * =====================================================
 */

require_once dirname(__DIR__) . '/config/config.php';

require_login();


/*
|--------------------------------------------------------------------------
| BASIC USER INFORMATION
|--------------------------------------------------------------------------
*/

$userName =
    $_SESSION['user_name']
    ?? 'SPS User';


/*
|--------------------------------------------------------------------------
| CLIENT COUNT
|--------------------------------------------------------------------------
|
| COUNT(*) = SQL aggregate function.
|
| 7,000 clients hon to database directly count karega.
| Hum saare 7,000 records PHP mein load nahi kar rahe.
|
| Ye important hai for scalability.
|
*/

$clientCount = 0;

try {

    $stmt = db()->query(
        "SELECT COUNT(*) AS total
         FROM clients"
    );

    $clientCount =
        (int) $stmt->fetch()['total'];

} catch (Throwable $exception) {

    /*
     * Agar clients table abhi exist nahi karti to dashboard
     * crash nahi karega.
     */

    $clientCount = 0;
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
        Dashboard | SPS CRM
    </title>

    <link
        rel="stylesheet"
        href="<?= APP_URL ?>/assets/css/app.css"
    >

</head>

<body>

<div class="crm-shell">


    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="brand-logo small">
                SPS
            </div>

            <div>
                <strong>
                    SPS CRM
                </strong>

                <small>
                    Operations
                </small>
            </div>

        </div>


        <nav>

            <a
                class="nav-link active"
                href="<?= APP_URL ?>/index.php"
            >
                <span>▦</span>
                Dashboard
            </a>


            <a
                class="nav-link"
                href="<?= APP_URL ?>/clients.php"
            >
                <span>◉</span>
                Clients
            </a>


            <a
                class="nav-link"
                href="<?= APP_URL ?>/service.php"
            >
                <span>◆</span>
                File a Service
            </a>


            <a
                class="nav-link"
                href="#"
            >
                <span>□</span>
                Documents
            </a>


            <a
                class="nav-link"
                href="#"
            >
                <span>◫</span>
                Invoices
            </a>


            <a
                class="nav-link"
                href="#"
            >
                <span>⚙</span>
                Settings
            </a>

        </nav>


        <div class="sidebar-bottom">

            <div class="user-mini">

                <strong>
                    <?= e($userName) ?>
                </strong>

                <small>
                    SPS Staff
                </small>

            </div>


            <a
                class="logout-link"
                href="<?= APP_URL ?>/logout.php"
            >
                Sign out
            </a>

        </div>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">


        <header class="topbar">

            <div>

                <span class="eyebrow">
                    SPS OPERATIONS
                </span>

                <h1>
                    Good to see you, <?= e($userName) ?>
                </h1>

            </div>


            <a
                href="<?= APP_URL ?>/service.php"
                class="btn btn-primary"
            >
                + File New Service
            </a>

        </header>


        <!-- STAT CARDS -->

        <section class="stats-grid">

            <div class="stat-card">

                <span>
                    Total Clients
                </span>

                <strong>
                    <?= number_format($clientCount) ?>
                </strong>

                <small>
                    Active CRM records
                </small>

            </div>


            <div class="stat-card">

                <span>
                    Active Services
                </span>

                <strong>
                    0
                </strong>

                <small>
                    Workflow cases
                </small>

            </div>


            <div class="stat-card">

                <span>
                    Missing Documents
                </span>

                <strong>
                    0
                </strong>

                <small>
                    Requires attention
                </small>

            </div>


            <div class="stat-card">

                <span>
                    Pending Review
                </span>

                <strong>
                    0
                </strong>

                <small>
                    Staff action required
                </small>

            </div>

        </section>


        <!-- SERVICE QUICK ACTIONS -->

        <section class="dashboard-section">

            <div class="section-heading">

                <div>

                    <span class="eyebrow">
                        WORKFLOW
                    </span>

                    <h2>
                        Start a service
                    </h2>

                </div>

            </div>


            <div class="service-grid">

                <a
                    class="service-card"
                    href="<?= APP_URL ?>/service.php?type=tax"
                >

                    <span class="service-icon">
                        $
                    </span>

                    <h3>
                        Tax Service
                    </h3>

                    <p>
                        Start a guided tax filing workflow.
                    </p>

                    <span class="service-action">
                        Start →
                    </span>

                </a>


                <a
                    class="service-card"
                    href="<?= APP_URL ?>/service.php?type=immigration"
                >

                    <span class="service-icon">
                        ✦
                    </span>

                    <h3>
                        Immigration Service
                    </h3>

                    <p>
                        Start a guided immigration case.
                    </p>

                    <span class="service-action">
                        Start →
                    </span>

                </a>


                <a
                    class="service-card"
                    href="<?= APP_URL ?>/service.php?type=business"
                >

                    <span class="service-icon">
                        B
                    </span>

                    <h3>
                        Business Service
                    </h3>

                    <p>
                        Registration and business workflow.
                    </p>

                    <span class="service-action">
                        Start →
                    </span>

                </a>

            </div>

        </section>


    </main>

</div>

</body>

</html>