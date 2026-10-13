<?php
/**
 * GRAVITY CRM - Login Page
 * Phase 1: Foundation
 * 
 * Yahan user authentication handle hoti hai.
 * Password hash verify karke session start kiya jata hai.
 */

require_once __DIR__ . '/functions.php';

// Agar pehle se logged in hain to dashboard par bhejo
if (current_user()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $username = sanitize(post('username'));
    $password = post('password');
    
    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        // User ko database se fetch karo
        $user = db_row('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1', [$username, $username]);
        
        // Per-IP burst guard (generous: shared office IPs are expected, Spec §27)
        if (ip_throttled($username)) {
            $error = 'Too many attempts. Please try again later.';
            log_security_event('login_throttled', 'Throttled login attempt for: ' . $username);
        } elseif ($user && $user['status'] === 'active' && is_user_locked($user)) {
            // Temporary lockout with administrator release (Users screen)
            $error = 'Account temporarily locked due to failed attempts. Try again later or contact an administrator.';
            log_security_event('login_blocked_locked', 'Blocked login (account locked): ' . $username, (int)$user['id']);
        } elseif ($user && $user['status'] === 'active') {
            $password_valid = false;
            
            // Sentinel hash check - first login upgrade
            if ($user['password_hash'] === '__DEFAULT_PASSWORD_HASH__') {
                // Default password check
                if ($password === 'ChangeMeImmediately!123') {
                    $password_valid = true;
                    // Proper hash mein upgrade karo
                    db_execute('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_BCRYPT), $user['id']]);
                }
            } else {
                $password_valid = password_verify($password, $user['password_hash']);
            }
            
            if ($password_valid) {
                // Clear failure counter / lock
                reset_failed_logins((int)$user['id']);
                log_login_attempt($username, true);
                
                // Session set karo
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                
                // Session/device metadata (Spec §27): identity = account + session, never IP alone
                $_SESSION['login_meta'] = [
                    'session_id'   => session_id(),
                    'ip'           => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    'logged_in_at' => date('Y-m-d H:i:s'),
                ];
                
                // Last login update karo
                db_execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
                
                // Stage-2 security event (Spec §29)
                log_security_event('login', 'User logged in: ' . $username, (int)$user['id'], 'info');
                
                // Force password change on first login
                if ($user['must_change_password'] == 1) {
                    redirect('profile.php?force_change=1');
                }
                
                redirect('dashboard.php');
            } else {
                // Failed attempt: count toward lockout + persist for throttling
                record_failed_login((int)$user['id']);
                log_login_attempt($username, false);
                log_security_event('login_failed', 'Failed login attempt for: ' . $username);
                $error = 'Invalid username or password.';
            }
        } else {
            // Unknown username or inactive account - generic response (no enumeration)
            log_login_attempt($username, false);
            log_security_event('login_failed', 'Failed login attempt for: ' . $username);
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e(get_setting('app_name', APP_NAME)) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #2563eb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 2rem;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo i {
            font-size: 3rem;
            color: #2563eb;
        }
        .login-logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-top: 0.5rem;
        }
        .login-logo p {
            color: #6b7280;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.4rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-login:hover { background: #1d4ed8; }
        .error-alert {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            border: 1px solid #fecaca;
        }
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #6b7280;
            font-size: 0.8rem;
        }
        .login-footer a {
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <i class="bi bi-boxes"></i>
                <h1><?= e(get_setting('app_name', APP_NAME)) ?></h1>
                <p>Professional Services CRM</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-alert"><?= e($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autofocus autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                </button>
            </form>
            
            <div class="login-footer">
                <p>Default login: <strong>admin</strong> / <strong>ChangeMeImmediately!123</strong></p>
                <p>You will be required to change your password after first login.</p>
            </div>
        </div>
    </div>
</body>
</html>