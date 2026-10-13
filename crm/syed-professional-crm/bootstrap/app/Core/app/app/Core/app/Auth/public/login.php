<?php
/**
 * =====================================================
 * FILE: login.php
 * PURPOSE: SPS CRM user login page
 *
 * WORKFLOW:
 * 1. Application bootstrap load hota hai.
 * 2. Session/authentication service available hoti hai.
 * 3. Agar user already logged in hai to dashboard par jata hai.
 * 4. POST request par email/password validate hotay hain.
 * 5. Auth::attempt() credentials verify karta hai.
 * 6. Successful login par dashboard redirect.
 * 7. Failed login par safe error message show.
 *
 * SECURITY:
 * - Password database mein plain text nahi hota.
 * - password_verify() hash verify karta hai.
 * - Successful login par session ID regenerate hoti hai.
 * =====================================================
 */

declare(strict_types=1);


/*
 * =====================================================
 * 1. APPLICATION BOOTSTRAP
 * =====================================================
 *
 * require_once = PHP built-in language construct.
 *
 * Is se:
 * - configuration
 * - container
 * - database
 * - error handling
 * - environment
 * sab initialize hotay hain.
 */
require_once dirname(__DIR__)
    . '/bootstrap/app.php';


/*
 * =====================================================
 * 2. SESSION + AUTH SERVICES
 * =====================================================
 *
 * SessionManager aur Auth classes load karna.
 */
require_once dirname(__DIR__)
    . '/app/Core/SessionManager.php';

require_once dirname(__DIR__)
    . '/app/Auth/Auth.php';


/*
 * Session service create.
 */
$session = new SessionManager();


/*
 * Database PDO Container se retrieve.
 */
$pdo = $container->get(
    'db'
);


/*
 * Authentication service create.
 */
$auth = new Auth(
    $pdo,
    $session
);


/*
 * =====================================================
 * 3. ALREADY LOGGED-IN USER
 * =====================================================
 *
 * Agar user already authenticated hai,
 * login form dobara show karne ki zaroorat nahi.
 */
if ($auth->check()) {

    /*
     * Url::redirect() internal CRM redirect perform karta hai.
     *
     * IMPORTANT:
     * Dashboard protected route hai.
     */
    Url::redirect(
        'dashboard.php'
    );
}


/*
 * =====================================================
 * 4. FORM STATE
 * =====================================================
 */

$error = '';


$email = '';


/*
 * =====================================================
 * 5. LOGIN FORM PROCESSING
 * =====================================================
 *
 * Request::isPost() check karta hai ke form submit hua ya nahi.
 */
if (Request::isPost()) {

    /*
     * Submitted email retrieve.
     */
    $email = Request::postString(
        'email'
    );


    /*
     * Submitted password retrieve.
     *
     * Password ko trim nahi kar rahe.
     *
     * Why?
     * Password ke beginning/end spaces bhi technically
     * password ka part ho sakte hain.
     */
    $password = $_POST['password'] ?? '';


    /*
     * Basic validation.
     */
    if (
        $email === '' ||
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        /*
         * User-friendly validation message.
         */
        $error = 'Please enter a valid email address.';

    } elseif (
        !is_string($password) ||
        $password === ''
    ) {

        /*
         * Password missing.
         */
        $error = 'Please enter your password.';

    } else {

        /*
         * =================================================
         * AUTHENTICATION ATTEMPT
         * =================================================
         *
         * Auth service:
         *
         * email lookup
         * ↓
         * account status
         * ↓
         * password_verify()
         * ↓
         * session regeneration
         * ↓
         * authenticated session
         */
        if (
            $auth->attempt(
                $email,
                $password
            )
        ) {

            /*
             * Login successful.
             *
             * User ko dashboard par bhejo.
             */
            Url::redirect(
                'dashboard.php'
            );
        }


        /*
         * Failed authentication.
         *
         * Generic message use kar rahe hain.
         *
         * "Email exists but password wrong"
         * jaisi information attacker ko nahi deni.
         */
        $error = 'The email or password is incorrect.';
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
                    Error text ko HTML-safe banata hai.

                    Is se malicious HTML/JavaScript browser mein
                    execute hone ka risk reduce hota hai.
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