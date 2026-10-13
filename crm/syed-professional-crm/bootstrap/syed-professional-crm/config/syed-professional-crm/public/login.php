<?php
/**
 * =====================================================
 * FILE: login.php
 * PURPOSE: SPS CRM user login page
 *
 * WORKFLOW:
 * 1. Application bootstrap load.
 * 2. Authentication services initialize.
 * 3. Already logged-in user dashboard par jata hai.
 * 4. POST request par CSRF token verify hota hai.
 * 5. Email/password validate hotay hain.
 * 6. Auth::attempt() credentials verify karta hai.
 * 7. Successful login par safe return destination use hoti hai.
 * 8. Failed login par generic error show hota hai.
 *
 * SECURITY:
 * - CSRF protection
 * - Password hashing verification
 * - Session regeneration
 * - Safe internal redirect
 * - XSS-safe output
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
 * 2. REQUIRED SERVICES
 * =====================================================
 */

require_once dirname(__DIR__)
    . '/app/Core/SessionManager.php';

require_once dirname(__DIR__)
    . '/app/Auth/Auth.php';

require_once dirname(__DIR__)
    . '/app/Auth/AuthGuard.php';

require_once dirname(__DIR__)
    . '/app/Security/Csrf.php';

require_once dirname(__DIR__)
    . '/app/Security/SafeRedirect.php';


/*
 * Session service.
 */
$session = new SessionManager();


/*
 * Database connection.
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
 * CSRF protection service.
 */
$csrf = new Csrf(
    $session
);


/*
 * =====================================================
 * 3. ALREADY AUTHENTICATED USER
 * =====================================================
 */

$authGuard->redirectIfAuthenticated();


/*
 * =====================================================
 * 4. FORM STATE
 * =====================================================
 */

$error = '';

$email = '';


/*
 * =====================================================
 * 5. SAFE RETURN DESTINATION
 * =====================================================
 *
 * Login page ke URL se return_to retrieve.
 *
 * Example:
 *
 * login.php?return_to=clients.php
 */
$returnTo = $_GET['return_to'] ?? null;


/*
 * SafeRedirect user-provided destination ko validate karega.
 */
$safeReturnTo = SafeRedirect::path(
    is_string($returnTo)
        ? $returnTo
        : null,
    'dashboard.php'
);


/*
 * =====================================================
 * 6. LOGIN FORM PROCESSING
 * =====================================================
 */

if (Request::isPost()) {

    /*
     * =================================================
     * CSRF VALIDATION
     * =================================================
     *
     * Form action se pehle security token verify.
     *
     * Agar token invalid hai to authentication attempt
     * bhi nahi karna.
     */
    if (!$csrf->validateRequest()) {

        /*
         * Generic security error.
         */
        $error = 'Your session security check failed. Please try again.';

    } else {

        /*
         * Email retrieve.
         */
        $email = Request::postString(
            'email'
        );


        /*
         * Password retrieve.
         *
         * Password ko trim nahi karna.
         */
        $password = $_POST['password'] ?? '';


        /*
         * Return destination bhi POST ke through preserve karenge.
         */
        $postedReturnTo = $_POST['return_to'] ?? null;


        /*
         * POST return destination ko safe validate.
         */
        $safeReturnTo = SafeRedirect::path(
            is_string($postedReturnTo)
                ? $postedReturnTo
                : null,
            'dashboard.php'
        );


        /*
         * =================================================
         * BASIC INPUT VALIDATION
         * =================================================
         */

        if (
            $email === '' ||
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            /*
             * Invalid email.
             */
            $error = 'Please enter a valid email address.';

        } elseif (
            !is_string($password) ||
            $password === ''
        ) {

            /*
             * Missing password.
             */
            $error = 'Please enter your password.';

        } else {

            /*
             * =================================================
             * AUTHENTICATION ATTEMPT
             * =================================================
             */
            if (
                $auth->attempt(
                    $email,
                    $password
                )
            ) {

                /*
                 * =================================================
                 * SUCCESSFUL LOGIN
                 * =================================================
                 *
                 * Safe internal destination par redirect.
                 */
                Url::redirect(
                    $safeReturnTo
                );
            }


            /*
             * Generic authentication failure.
             *
             * Email existence disclose nahi karni.
             */
            $error = 'The email or password is incorrect.';
        }
    }
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
        Login | Syed Professional Services CRM
    </title>

</head>

<body>

    <main>

        <section>

            <h1>
                Syed Professional Services
            </h1>

            <p>
                CRM Login
            </p>


            <?php if ($error !== ''): ?>

                <!--
                    htmlspecialchars() = PHP built-in security function.

                    Why:
                    Error message ko HTML-safe output karta hai.
                -->
                <div
                    role="alert"
                >
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="<?= htmlspecialchars(
                    Url::to('login.php'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

                <?php
                /*
                 * CSRF hidden field.
                 *
                 * Browser is token ko POST request ke saath send karega.
                 */
                echo $csrf->field();
                ?>


                <!--
                    Return destination hidden field.

                    Is value ko SafeRedirect pehle validate kar chuka hai.
                -->
                <input
                    type="hidden"
                    name="return_to"
                    value="<?= htmlspecialchars(
                        $safeReturnTo,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >


                <div>

                    <label
                        for="email"
                    >
                        Email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $email,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        autocomplete="username"
                        required
                    >

                </div>


                <div>

                    <label
                        for="password"
                    >
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <button
                    type="submit"
                >
                    Sign In
                </button>

            </form>

        </section>

    </main>

</body>

</html>