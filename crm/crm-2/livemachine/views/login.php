<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../classes/Auth.php';

Auth::start();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['_csrf'] ?? null)) {
        $error = 'Session expired — please retry.';
    } elseif (Auth::attempt((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''))) {
        header('Location: ?route=dashboard'); exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
if (Auth::check()) { header('Location: ?route=dashboard'); exit; }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Attendance</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1><i data-lucide="fingerprint"></i> Attendance</h1>
        <p class="subtitle">Sign in to manage your devices and reports.</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrf()) ?>">
            <div class="field">
                <label>Username</label>
                <input name="username" autofocus required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button class="btn primary" type="submit">Sign in</button>
        </form>
    </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
