<?php
// ============================================================
// FILE: index.php - Login Page
// ============================================================

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'config/db.php';
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.username, u.password, u.role_id, u.is_active, r.role_name 
                               FROM users u 
                               LEFT JOIN roles r ON u.role_id = r.id 
                               WHERE u.username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password']) && $user['is_active'] == 1) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role_id']   = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];

            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            log_activity('login', 'Auth', 'User logged in successfully');

            header("Location: dashboard.php");
            exit();
        } else {
            $error = 'Invalid username or password, or account is inactive.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>mera_crm - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%); min-height: 100vh; display: flex; align-items: center; }
        .login-card { border: none; border-radius: 12px; box-shadow: 0 15px 35px rgba(0,0,0,.3); }
        .login-header { background: #0d6efd; color: #fff; border-radius: 12px 12px 0 0; padding: 1.5rem; text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card login-card">
                <div class="login-header">
                    <h3 class="mb-0"><i class="bi bi-building"></i> mera_crm</h3>
                    <small>Professional Services Management</small>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="POST" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control form-control-lg" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control form-control-lg" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">Sign In</button>
                    </form>
                    <div class="text-center mt-3 small text-muted">
                        Default: <strong>admin</strong> / <strong>admin123</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
