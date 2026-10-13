<?php
use App\Core\Url;
$error = $error ?? '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Login') ?> · SPS CRM</title>
    <link rel="stylesheet" href="<?= Url::asset('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= Url::asset('assets/css/auth.css') ?>">
</head>
<body>
<main class="auth-page">
    <section class="auth-brand">
        <div class="brand-content">
            <div class="brand-mark">SPS</div>
            <h1>Syed Professional Services</h1>
            <p>Professional Business Operations Platform</p>
        </div>
    </section>
    <section class="auth-panel">
        <div class="login-card">
            <div class="login-heading">
                <span class="eyebrow">SPS CRM</span>
                <h2>Welcome back</h2>
                <p>Sign in to continue to your secure business workspace.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= Url::to('login') ?>" class="login-form">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="admin@sps.local" autocomplete="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="login-button">Sign In</button>
            </form>
            <div class="security-notice">
                <div>
                    <strong>After install</strong>
                    <p>Use the admin email/password you set in Configure.</p>
                </div>
            </div>
        </div>
    </section>
</main>
</body>
</html>
