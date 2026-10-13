```php
<?php
// ============================================================
// SPS CRM - Login Page
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Already logged in?
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals(csrf_token(), $token)) {

        $error = 'Invalid security token.';

    } else {

        $username =
            trim($_POST['username'] ?? '');

        $password =
            $_POST['password'] ?? '';

        if (
            empty($username) ||
            empty($password)
        ) {

            $error =
                'Please enter your username and password.';

        } elseif (
            !login_user(
                $username,
                $password
            )
        ) {

            $error =
                'Invalid username or password.';

            error_log(
                'Failed login attempt for username: ' .
                $username
            );

        } else {

            $redirect =
                $_SESSION['redirect_after_login'] ??
                'dashboard.php';

            unset(
                $_SESSION['redirect_after_login']
            );

            redirect($redirect);
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
    content="width=device-width, initial-scale=1.0">

<title>
    Login — <?= e(APP_SHORT) ?>
</title>

<style>

*,*::before,*::after{
    box-sizing:border-box;
    margin:0;
    padding:0
}

body{
    font-family:
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #0f172a 0%,
            #1e3a5f 100%
        );

    min-height:100vh;

    display:flex;

    align-items:center;

    justify-content:center;
}

.login-box{
    background:#fff;

    border-radius:12px;

    padding:40px;

    width:100%;

    max-width:400px;

    box-shadow:
        0 25px 50px rgba(0,0,0,.3);
}

.login-logo{
    text-align:center;

    margin-bottom:28px;
}

.login-logo .logo-text{
    font-size:28px;

    font-weight:800;

    color:#0f172a;
}

.login-logo .logo-text span{
    color:#2563eb;
}

.login-logo .logo-sub{
    font-size:13px;

    color:#64748b;

    margin-top:4px;
}

.form-group{
    margin-bottom:16px;
}

.form-group label{
    display:block;

    font-size:13px;

    font-weight:500;

    color:#374151;

    margin-bottom:5px;
}

.form-control{
    width:100%;

    padding:10px 14px;

    border:
        1px solid #e2e8f0;

    border-radius:8px;

    font-size:14px;

    color:#1e293b;
}

.form-control:focus{
    outline:none;

    border-color:#2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.1);
}

.btn-login{
    width:100%;

    padding:11px;

    background:#2563eb;

    color:#fff;

    border:none;

    border-radius:8px;

    font-size:14px;

    font-weight:600;

    cursor:pointer;

    margin-top:8px;
}

.btn-login:hover{
    background:#1d4ed8;
}

.alert-error{
    background:#fee2e2;

    color:#b91c1c;

    border:
        1px solid #fecaca;

    padding:10px 14px;

    border-radius:8px;

    font-size:13px;

    margin-bottom:16px;
}

.footer-note{
    text-align:center;

    font-size:12px;

    color:#94a3b8;

    margin-top:20px;
}

</style>

</head>

<body>

<div class="login-box">

    <div class="login-logo">

        <div class="logo-text">
            <span>SPS</span> CRM
        </div>

        <div class="logo-sub">
            Professional Services CRM
        </div>

    </div>

    <?php if ($error): ?>

        <div class="alert-error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>

        <div class="alert-error">
            <?= e($_SESSION['flash_error']) ?>
        </div>

        <?php
        unset($_SESSION['flash_error']);
        ?>

    <?php endif; ?>

    <form
        method="POST"
        action="login.php">

        <?= csrf_field() ?>

        <div class="form-group">

            <label for="username">
                Username
            </label>

            <input
                type="text"
                id="username"
                name="username"
                class="form-control"
                value="<?= e($username) ?>"
                placeholder="Enter your username"
                required
                autofocus
                autocomplete="username">

        </div>

        <div class="form-group">

            <label for="password">
                Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                placeholder="Enter your password"
                required
                autocomplete="current-password">

        </div>

        <button
            type="submit"
            class="btn-login">
            Sign In
        </button>

    </form>

    <div class="footer-note">
        <?= e(APP_NAME) ?>
        v<?= APP_VERSION ?>
    </div>

</div>

</body>
</html>
```
