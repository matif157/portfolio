<?php

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

    <title>
        Login - Syed Professional Services CRM
    </title>

    <link
        rel="stylesheet"
        href="<?= htmlspecialchars(
            \App\Core\Url::asset('assets/css/login.css'),
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >

</head>

<body>

<div class="login-page">

    <div class="login-card">

        <div class="login-brand">

            <div class="login-logo">
                SPS
            </div>

            <div>

                <strong>
                    Syed Professional Services
                </strong>

                <span>
                    CRM Portal
                </span>

            </div>

        </div>


        <div class="login-heading">

            <h1>
                Welcome back
            </h1>

            <p>
                Sign in to access your CRM workspace.
            </p>

        </div>


        <?php if ($error === 'missing'): ?>

            <div class="login-error">
                Please enter your email and password.
            </div>

        <?php elseif ($error === 'invalid'): ?>

            <div class="login-error">
                Invalid email or password.
            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="<?= htmlspecialchars(
                \App\Core\Url::to('login'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

            <div class="form-group">

                <label for="email">
                    Email address
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    placeholder="admin@sps.local"
                    autocomplete="username"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <div class="login-options">

                <label class="remember">

                    <input
                        type="checkbox"
                        name="remember"
                    >

                    <span>
                        Remember me
                    </span>

                </label>

                <span class="forgot">
                    Password recovery later
                </span>

            </div>


            <button
                type="submit"
                class="login-button"
            >
                Sign in
            </button>

        </form>


        <div class="login-footer">

            <span>
                Secure CRM workspace
            </span>

            <span>
                •
            </span>

            <span>
                SPS
            </span>

        </div>

    </div>

</div>

</body>

</html>