```php
<?php
// ============================================================
// SPS CRM - Change Password
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// User must be logged in
require_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token
    verify_csrf();

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (
        empty($current_password) ||
        empty($new_password) ||
        empty($confirm_password)
    ) {
        $error = 'Please fill in all password fields.';
    }

    // New passwords must match
    elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation password do not match.';
    }

    // Minimum password length
    elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long.';
    }

    // New password cannot be the same as current password
    elseif ($current_password === $new_password) {
        $error = 'New password must be different from your current password.';
    }

    else {

        // Get current user
        $user = db_row(
            "SELECT id, password_hash
             FROM users
             WHERE id = ?
             LIMIT 1",
            [current_user_id()]
        );

        if (!$user) {

            $error = 'Unable to find your user account.';

        } else {

            $password_valid = false;

            /*
             * Normal secure password verification.
             */
            if (!empty($user['password_hash'])) {
                $password_valid = password_verify(
                    $current_password,
                    $user['password_hash']
                );
            }

            /*
             * Compatibility with an older/plain password record.
             *
             * If the existing account has an old password stored
             * directly instead of a PHP password hash, verify it
             * and immediately convert it to a secure hash.
             */
            if (
                !$password_valid &&
                !empty($user['password_hash'])
            ) {

                $stored_password =
                    (string)$user['password_hash'];

                $looks_like_hash =
                    str_starts_with($stored_password, '$2y$') ||
                    str_starts_with($stored_password, '$2a$') ||
                    str_starts_with($stored_password, '$2b$') ||
                    str_starts_with($stored_password, '$argon2i$') ||
                    str_starts_with($stored_password, '$argon2id$');

                if (
                    !$looks_like_hash &&
                    hash_equals(
                        $stored_password,
                        $current_password
                    )
                ) {

                    $password_valid = true;

                    // Upgrade old password to secure hash
                    $upgraded_hash = password_hash(
                        $current_password,
                        PASSWORD_DEFAULT
                    );

                    db_query(
                        "UPDATE users
                         SET password_hash = ?,
                             updated_at = NOW()
                         WHERE id = ?",
                        [
                            $upgraded_hash,
                            current_user_id()
                        ]
                    );
                }
            }

            if (!$password_valid) {

                $error = 'Current password is incorrect.';

            } else {

                // Create secure hash for the new password
                $new_password_hash = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                // Save new password and clear first-login requirement
                db_query(
                    "UPDATE users
                     SET password_hash = ?,
                         must_change_password = 0,
                         updated_at = NOW()
                     WHERE id = ?",
                    [
                        $new_password_hash,
                        current_user_id()
                    ]
                );

                // Update current session
                $_SESSION['must_change_password'] = false;

                // Log password change
                log_activity(
                    'update',
                    'users',
                    current_user_id(),
                    'Password changed'
                );

                $success =
                    'Your password has been changed successfully.';

                // Redirect after successful change
                redirect('dashboard.php');
            }
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
    Change Password — <?= e(APP_SHORT) ?>
</title>

<style>

*,*::before,*::after{
    box-sizing:border-box;
    margin:0;
    padding:0
}

body{
    font-family:-apple-system,
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

.password-box{
    background:#fff;
    border-radius:12px;

    padding:40px;

    width:100%;
    max-width:430px;

    box-shadow:
        0 25px 50px rgba(0,0,0,.3);
}

.logo{
    text-align:center;
    margin-bottom:25px;
}

.logo-text{
    font-size:28px;
    font-weight:800;
    color:#0f172a;
}

.logo-text span{
    color:#2563eb;
}

.logo-sub{
    font-size:13px;
    color:#64748b;
    margin-top:4px;
}

.page-title{
    font-size:20px;
    font-weight:700;
    color:#1e293b;
    margin-bottom:8px;
}

.page-description{
    font-size:13px;
    color:#64748b;
    line-height:1.5;
    margin-bottom:22px;
}

.form-group{
    margin-bottom:17px;
}

.form-group label{
    display:block;
    font-size:13px;
    font-weight:500;
    color:#374151;
    margin-bottom:6px;
}

.form-control{
    width:100%;

    padding:11px 14px;

    border:
        1px solid #e2e8f0;

    border-radius:8px;

    font-size:14px;

    color:#1e293b;

    transition:
        border-color .15s,
        box-shadow .15s;
}

.form-control:focus{
    outline:none;

    border-color:#2563eb;

    box-shadow:
        0 0 0 3px rgba(37,99,235,.1);
}

.btn-change{
    width:100%;

    padding:11px;

    background:#2563eb;

    color:#fff;

    border:none;

    border-radius:8px;

    font-size:14px;

    font-weight:600;

    cursor:pointer;

    margin-top:5px;
}

.btn-change:hover{
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

.alert-success{
    background:#dcfce7;

    color:#166534;

    border:
        1px solid #bbf7d0;

    padding:10px 14px;

    border-radius:8px;

    font-size:13px;

    margin-bottom:16px;
}

.password-note{
    margin-top:18px;

    font-size:12px;

    color:#64748b;

    line-height:1.5;
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

<div class="password-box">

    <div class="logo">

        <div class="logo-text">
            <span>SPS</span> CRM
        </div>

        <div class="logo-sub">
            Professional Services CRM
        </div>

    </div>

    <div class="page-title">
        Change Your Password
    </div>

    <div class="page-description">
        For security, you must change your temporary password
        before continuing to the CRM.
    </div>

    <?php if ($error): ?>

        <div class="alert-error">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <?php if ($success): ?>

        <div class="alert-success">
            <?= e($success) ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="change_password.php">

        <?= csrf_field() ?>

        <div class="form-group">

            <label for="current_password">
                Current Password
            </label>

            <input
                type="password"
                id="current_password"
                name="current_password"
                class="form-control"
                placeholder="Enter your current password"
                required
                autocomplete="current-password">

        </div>

        <div class="form-group">

            <label for="new_password">
                New Password
            </label>

            <input
                type="password"
                id="new_password"
                name="new_password"
                class="form-control"
                placeholder="Enter your new password"
                minlength="8"
                required
                autocomplete="new-password">

        </div>

        <div class="form-group">

            <label for="confirm_password">
                Confirm New Password
            </label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                class="form-control"
                placeholder="Enter your new password again"
                minlength="8"
                required
                autocomplete="new-password">

        </div>

        <button
            type="submit"
            class="btn-change">
            Change Password
        </button>

    </form>

    <div class="password-note">
        Your new password must contain at least 8 characters.
        After changing it, you will be redirected to the dashboard.
    </div>

    <div class="footer-note">
        <?= e(APP_NAME) ?> v<?= APP_VERSION ?>
    </div>

</div>

</body>
</html>
```
