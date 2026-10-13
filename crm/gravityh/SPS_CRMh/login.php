<?php
/**
 * GRAVITY CRM — Secure Authentication & Login Portal
 * 
 * Yeh page user authentication handle karta hai:
 * 1. Username ya email match karta hai
 * 2. Password ko plain-text direct compare karta hai (no hashing)
 * 3. Session establish karta hai aur stage2 sensitive activity log save karta hai
 * 4. Prepared statements ke zarye SQL Injection se 100% mehfooz hai.
 */

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Prepared statement query to prevent SQL Injection
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && $password === $user['password']) {
            if ($user['status'] !== 'active') {
                $error = 'Your account is deactivated. Please contact administrator.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role_id'] = $user['role_id'];
                
                log_activity('User Login', 'auth', $user['id'], "User '{$user['username']}' logged in successfully", 'stage1_normal');
                set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect('dashboard.php');
            }
        } else {
            $error = 'Invalid username or password.';
            log_activity('Failed Login Attempt', 'auth', 0, "Failed login attempt for identifier: $username", 'stage2_sensitive');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #0f172a; color: #f8fafc; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: #1e293b; width: 100%; max-width: 420px; padding: 36px; border-radius: 12px; border: 1px solid #334155; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
        .brand { text-align: center; margin-bottom: 28px; }
        .brand h1 { font-size: 24px; color: #ffffff; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .brand h1 span { background: #2563eb; color: #ffffff; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        .brand p { font-size: 13px; color: #94a3b8; margin-top: 6px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #cbd5e1; }
        .form-control { width: 100%; padding: 11px 14px; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #ffffff; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.2); }
        .btn-submit { width: 100%; padding: 12px; background: #2563eb; color: #ffffff; border: none; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background 0.15s; }
        .btn-submit:hover { background: #1d4ed8; }
        .alert { background: #7f1d1d; color: #fca5a5; padding: 12px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; border: 1px solid #991b1b; }
        .demo-box { margin-top: 24px; padding: 14px; background: #0f172a; border-radius: 6px; border: 1px dashed #334155; font-size: 12px; color: #94a3b8; }
        .demo-box code { color: #60a5fa; background: rgba(255,255,255,0.05); padding: 2px 5px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="brand">
        <h1>GRAVITY <span>CRM</span></h1>
        <p>AI Professional Services Platform</p>
    </div>

    <?php if ($error): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <?= csrf_field() ?>
        
        <div class="form-group">
            <label class="form-label">Username or Email</label>
            <input type="text" name="username" class="form-control" placeholder="admin" required autofocus>
        </div>

        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••••••" required>
        </div>

        <button type="submit" class="btn-submit">Sign In to Workspace</button>
    </form>

    <div class="demo-box">
        <strong>Default Admin Credentials:</strong><br>
        Username: <code>admin</code><br>
        Password: <code>ChangeMeImmediately!123</code>
    </div>
</div>

</body>
</html>
