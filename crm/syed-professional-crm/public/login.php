<?php
/**
 * =====================================================
 * FILE: login.php
 * PURPOSE: SPS CRM temporary login system
 *
 * WORKFLOW:
 *
 * Login form
 *     ↓
 * Email + Password
 *     ↓
 * users table check
 *     ↓
 * Password verification
 *     ↓
 * Session create
 *     ↓
 * Dashboard
 *
 * Bhai ye temporary authentication layer hai.
 * Final version mein CSRF, rate limiting, audit logging,
 * MFA/2FA aur advanced permission system add hoga.
 * =====================================================
 */

require_once dirname(__DIR__) . '/config/config.php';


/*
|--------------------------------------------------------------------------
| ALREADY LOGGED IN
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'])) {

    header(
        'Location: ' . APP_URL . '/index.php'
    );

    exit();
}


$error = '';


/*
|--------------------------------------------------------------------------
| LOGIN FORM PROCESSING
|--------------------------------------------------------------------------
|
| $_SERVER = PHP built-in superglobal.
|
| REQUEST_METHOD check karta hai ke form POST hua hai ya
| user sirf page dekh raha hai.
|
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email =
        trim($_POST['email'] ?? '');

    $password =
        $_POST['password'] ?? '';


    /*
     * Basic validation.
     */

    if ($email === '' || $password === '') {

        $error =
            'Please enter your email and password.';

    } else {

        try {

            /*
             * Prepared statement.
             *
             * Why:
             * User input ko directly SQL string mein concatenate
             * nahi karna. Is se SQL injection ka risk reduce hota hai.
             */

            $stmt = db()->prepare(
                "SELECT *
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );


            $stmt->execute([$email]);


            $user = $stmt->fetch();


            /*
             * password_verify() = PHP built-in function.
             *
             * Why:
             * Plain password ko database mein compare nahi karte.
             * Hashed password verify hota hai.
             */

            if (
                $user &&
                isset($user['password']) &&
                password_verify(
                    $password,
                    $user['password']
                )
            ) {

                /*
                 * session_regenerate_id() = PHP built-in.
                 *
                 * Why:
                 * Successful login ke baad session fixation
                 * risk reduce karta hai.
                 */

                session_regenerate_id(true);


                $_SESSION['user_id'] =
                    $user['id'];

                $_SESSION['user_name'] =
                    $user['full_name']
                    ?? $user['name']
                    ?? 'SPS User';


                /*
                 * Dashboard par bhej do.
                 */

                header(
                    'Location: ' . APP_URL . '/index.php'
                );

                exit();

            } else {

                $error =
                    'Email or password is incorrect.';
            }

        } catch (Throwable $exception) {

            /*
             * Temporary development error.
             *
             * Production mein database details user ko
             * directly nahi dikhani.
             */

            $error =
                'Login system is not connected correctly yet: '
                . $exception->getMessage();
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
        Login | SPS CRM
    </title>

    <link
        rel="stylesheet"
        href="<?= APP_URL ?>/assets/css/app.css"
    >

</head>

<body class="auth-body">

<div class="auth-layout">

    <section class="auth-brand">

        <div>

            <div class="brand-logo">
                SPS
            </div>

            <h1>
                Syed Professional Services
            </h1>

            <p>
                Professional Services Management Platform
            </p>

        </div>

    </section>


    <section class="auth-form-area">

        <div class="login-card">

            <span class="eyebrow">
                SPS CRM
            </span>

            <h2>
                Welcome back
            </h2>

            <p class="muted">
                Sign in to access your business workspace.
            </p>


            <?php if ($error !== ''): ?>

                <div class="alert alert-danger">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>


            <form method="POST">

                <div class="form-group">

                    <label>
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        placeholder="you@example.com"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        placeholder="Enter password"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn btn-primary btn-full"
                >
                    Sign In
                </button>

            </form>


            <div class="security-note">
                🔒 Authorized SPS personnel only
            </div>

        </div>

    </section>

</div>

</body>

</html>