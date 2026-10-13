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
<html lang="en" data-theme="professional" data-mode="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Component Library -->
    <link rel="stylesheet" href="assets/css/gravity-ui.css">
    <!-- Professional Theme -->
    <link rel="stylesheet" href="assets/css/themes/theme-professional.css">

    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            height: 100vh;
            background-color: #0b0f17;
            font-family: var(--font-sans);
            color: var(--text-primary);
            overflow: hidden;
        }

        .login-container {
            display: flex;
            width: 100%;
            height: 100%;
        }

        /* Brand Panel (Left side) */
        .brand-panel {
            flex: 1.2;
            background: radial-gradient(circle at top left, #1e1b4b, #090d16 80%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }

        .brand-panel::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, transparent 70%);
            top: -200px;
            left: -200px;
            pointer-events: none;
        }

        .brand-logo-large {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-logo-large span {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: var(--radius-sm);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .brand-middle {
            max-width: 500px;
            z-index: 2;
        }

        .brand-headline {
            font-size: 40px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.15;
            margin-bottom: var(--space-4);
            letter-spacing: -1px;
        }

        .brand-tagline-large {
            font-size: 16px;
            color: #94a3b8;
            line-height: 1.6;
        }

        .brand-footer {
            font-size: 12px;
            color: #475569;
        }

        /* Form Panel (Right side) */
        .form-panel {
            flex: 1;
            background-color: #0d1117;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            border-left: 1px solid rgba(255, 255, 255, 0.05);
        }

        .form-card {
            width: 100%;
            max-width: 400px;
        }

        .form-header {
            margin-bottom: 30px;
        }

        .form-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
        }

        .form-header p {
            color: #8b949e;
            font-size: 14px;
        }

        .form-control-dark {
            background: #161b22 !important;
            border: 1px solid #30363d !important;
            color: #e6edf3 !important;
        }

        .form-control-dark:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
        }

        .demo-toggle-btn {
            font-size: 12px;
            color: #58a6ff;
            background: none;
            border: none;
            padding: 0;
            margin-top: 15px;
            cursor: pointer;
            text-decoration: underline;
        }

        .demo-credentials-box {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: var(--radius-md);
            font-size: 12px;
            color: #8b949e;
            animation: fadeIn 0.2s ease-in-out;
        }

        .demo-credentials-box code {
            color: #58a6ff;
            font-family: var(--font-mono);
            background: rgba(255,255,255,0.05);
            padding: 2px 4px;
            border-radius: 4px;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .brand-panel {
                display: none;
            }
            .form-panel {
                flex: 1;
                border-left: none;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <!-- Brand Panel (Left) -->
    <div class="brand-panel">
        <div class="brand-logo-large">
            GRAVITY <span>Workspace</span>
        </div>
        
        <div class="brand-middle">
            <h1 class="brand-headline">Next-Generation Professional CRM</h1>
            <p class="brand-tagline-large">Integrated operations, automated service delivery, unified communication, and execution center for taxation, bookkeeping, and client success.</p>
        </div>
        
        <div class="brand-footer">
            &copy; <?= date('Y') ?> Gravity Professional Services Platform. Running securely on Windows.
        </div>
    </div>

    <!-- Form Panel (Right) -->
    <div class="form-panel">
        <div class="form-card">
            <div class="form-header">
                <h2>Sign In</h2>
                <p>Enter your workspace identifiers below</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px;">
                    <span class="alert-icon">❌</span>
                    <span class="alert-content"><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <?= csrf_field() ?>
                
                <div class="form-group">
                    <label class="form-label" style="color: #c9d1d9;">Username or Email</label>
                    <input type="text" name="username" class="form-control form-control-dark" placeholder="Enter username..." required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: #c9d1d9;">Password</label>
                    <input type="password" name="password" class="form-control form-control-dark" placeholder="Enter password..." required>
                </div>

                <button type="submit" class="btn btn-primary w-full mt-4 btn-lg">Sign In to Workspace</button>
            </form>

            <button type="button" class="demo-toggle-btn" onclick="toggleDemoBox()">Need Admin credentials?</button>

            <div class="demo-credentials-box" id="demoBox">
                <strong>Workspace Access Token Credentials:</strong><br>
                <span style="display:block; margin-top:5px;">Username: <code>admin</code></span>
                <span style="display:block; margin-top:2px;">Password: <code>ChangeMeImmediately!123</code></span>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDemoBox() {
    const box = document.getElementById('demoBox');
    const btn = document.querySelector('.demo-toggle-btn');
    if (box.style.display === 'block') {
        box.style.display = 'none';
        btn.textContent = 'Need Admin credentials?';
    } else {
        box.style.display = 'block';
        btn.textContent = 'Hide Admin credentials';
    }
}
</script>

</body>
</html>
