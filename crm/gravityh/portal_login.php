<?php
/**
 * GRAVITY CRM - Client Portal Login (SPS 2040 Phase 8, Sec 26)
 * Separate auth scope from the staff CRM.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/core/portal.php';

if (portal_user()) {
    redirect('portal_index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = sanitize(post('email'));
    $password = post('password');
    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $res = portal_authenticate($email, $password);
        if (!empty($res['ok'])) {
            redirect('portal_index.php');
        }
        $error = $res['message'];
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Portal - <?= e(get_setting('app_name', APP_NAME)) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;}
    .card{max-width:420px;width:100%;border:none;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.08);}
    .btn-primary{width:100%;}
</style>
</head>
<body>
<div class="container">
<div class="card"><div class="card-body p-4">
    <h4 class="mb-1"><i class="bi bi-house-lock"></i> Client Portal</h4>
    <p class="text-muted" style="font-size:.9rem;"><?= e(get_setting('app_name', APP_NAME)) ?></p>
    <?php if ($error): ?><div class="alert alert-danger py-2" style="font-size:.9rem;"><?= e($error) ?></div><?php endif; ?>
    <form method="POST">
        <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required autofocus autocomplete="username">
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary"><i class="bi bi-box-arrow-in-right"></i> Sign In</button>
    </form>
    <p class="text-muted mt-3" style="font-size:.8rem;">Contact your office for portal access.</p>
</div></div>
</div>
</body>
</html>