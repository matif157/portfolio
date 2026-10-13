<?php
/**
 * =====================================================
 * FILE: dashboard.php
 * PURPOSE: Authenticated SPS CRM users ka main dashboard
 *
 * WORKFLOW:
 * 1. Application bootstrap hota hai.
 * 2. Authentication services load hoti hain.
 * 3. AuthGuard user ko verify karta hai.
 * 4. Unauthorized user login page par redirect hota hai.
 * 5. Authenticated user dashboard dekh sakta hai.
 * 6. Current user information session se retrieve hoti hai.
 *
 * IMPORTANT:
 * Yeh protected page hai.
 * Is file ka HTML authentication check ke baad hi render hona chahiye.
 * =====================================================
 */

declare(strict_types=1);


/*
 * =====================================================
 * 1. APPLICATION BOOTSTRAP
 * =====================================================
 */

require_once dirname(__DIR__)
    . '/bootstrap/app.php';


/*
 * =====================================================
 * 2. AUTHENTICATION SERVICES
 * =====================================================
 */

require_once dirname(__DIR__)
    . '/app/Core/SessionManager.php';

require_once dirname(__DIR__)
    . '/app/Auth/Auth.php';

require_once dirname(__DIR__)
    . '/app/Auth/AuthGuard.php';


/*
 * Session service.
 */
$session = new SessionManager();


/*
 * Central database connection.
 */
$pdo = $container->get(
    'db'
);


/*
 * Authentication service.
 */
$auth = new Auth(
    $pdo,
    $session
);


/*
 * Authentication guard.
 */
$authGuard = new AuthGuard(
    $auth
);


/*
 * =====================================================
 * 3. PROTECTED PAGE CHECK
 * =====================================================
 *
 * requireLogin():
 *
 * Logged-in user:
 *     continue
 *
 * Guest:
 *     login.php
 *
 * IMPORTANT:
 * Yeh check HTML se pehle hona chahiye.
 */
$authGuard->requireLogin();


/*
 * =====================================================
 * 4. CURRENT USER INFORMATION
 * =====================================================
 *
 * Authenticated user ka naam session se retrieve.
 */
$userName = $auth->name();


/*
 * Security:
 *
 * htmlspecialchars() = PHP built-in function.
 *
 * Database/session value ko HTML mein safely print karne ke
 * liye special characters encode karta hai.
 */
$safeUserName = htmlspecialchars(
    $userName ?? 'User',
    ENT_QUOTES,
    'UTF-8'
);

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

</head>

<body>

    <header>

        <div>

            <h1>
                SPS CRM
            </h1>

            <p>
                Welcome,
                <?= $safeUserName ?>
            </p>

        </div>


        <!--
            Logout normal internal CRM action hai.
            Is button se logout.php execute hoga.
        -->
        <a
            href="<?= htmlspecialchars(
                Url::to('logout.php'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
            Logout
        </a>

    </header>


    <main>

        <section>

            <h2>
                Dashboard
            </h2>

            <p>
                You are successfully authenticated.
            </p>


            <!--
                Future modules yahan gradually add honge:

                Clients
                Leads
                Services
                Projects
                Tasks
                Documents
                Invoices
                Payments
                Reports
                Automation
                Settings
            -->

        </section>

    </main>

</body>

</html>