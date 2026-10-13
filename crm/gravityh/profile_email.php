<?php
/**
 * GRAVITY CRM - User Email Configuration
 * Phase 1: Foundation
 * 
 * Yahan user apna personal email/SMTP configuration set kar sakta hai.
 * Isse user apne email se messages bhej sakta hai (Phase 2+ mein use hoga).
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('profile', 'edit');

$user = current_user();
$page_title = 'Email Configuration';
$active_page = 'profile';

$config = db_row('SELECT * FROM user_email_configs WHERE user_id = ?', [$user['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $smtp_host = sanitize(post('smtp_host'));
    $smtp_port = (int)post('smtp_port', 587);
    $smtp_username = sanitize(post('smtp_username'));
    $smtp_password = post('smtp_password');
    $smtp_encryption = sanitize(post('smtp_encryption', 'tls'));
    $from_name = sanitize(post('from_name'));
    $from_email = sanitize(post('from_email'));
    $is_active = post('is_active') ? 1 : 0;
    
    $errors = [];
    if ($is_active) {
        validate_required($smtp_host, 'SMTP Host', $errors);
        validate_required($smtp_username, 'SMTP Username', $errors);
        validate_required($from_email, 'From Email', $errors);
        if (!validate_email($from_email)) {
            $errors[] = 'Please enter a valid from email.';
        }
    }
    
    if (empty($errors)) {
        // Password encrypt karo (basic encryption for Phase 1)
        $encrypted_password = $smtp_password !== '' ? base64_encode(openssl_encrypt($smtp_password, 'AES-256-CBC', ENCRYPTION_KEY, 0, substr(hash('sha256', ENCRYPTION_KEY), 0, 16))) : ($config['smtp_password_encrypted'] ?? '');
        
        if ($config) {
            db_execute(
                'UPDATE user_email_configs SET smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password_encrypted = ?, smtp_encryption = ?, from_name = ?, from_email = ?, is_active = ? WHERE user_id = ?',
                [$smtp_host, $smtp_port, $smtp_username, $encrypted_password, $smtp_encryption, $from_name, $from_email, $is_active, $user['id']]
            );
        } else {
            db_execute(
                'INSERT INTO user_email_configs (user_id, smtp_host, smtp_port, smtp_username, smtp_password_encrypted, smtp_encryption, from_name, from_email, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$user['id'], $smtp_host, $smtp_port, $smtp_username, $encrypted_password, $smtp_encryption, $from_name, $from_email, $is_active]
            );
        }
        
        log_activity('profile', 'update', $user['id'], 'Email configuration updated');
        flash('success', 'Email configuration saved successfully.');
        redirect('profile.php');
    } else {
        foreach ($errors as $err) {
            flash('error', $err);
        }
    }
}

// Refresh config after save
$config = db_row('SELECT * FROM user_email_configs WHERE user_id = ?', [$user['id']]);
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-envelope"></i> Email Configuration</h1>
    <a href="profile.php" class="btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back to Profile</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Personal SMTP Settings</div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label-custom">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control-custom" value="<?= e($config['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-control-custom" value="<?= e($config['smtp_port'] ?? 587) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">Encryption</label>
                            <select name="smtp_encryption" class="form-control-custom">
                                <option value="tls" <?= ($config['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= ($config['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="none" <?= ($config['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label-custom">SMTP Username</label>
                            <input type="text" name="smtp_username" class="form-control-custom" value="<?= e($config['smtp_username'] ?? '') ?>" placeholder="you@gmail.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">SMTP Password</label>
                            <input type="password" name="smtp_password" class="form-control-custom" placeholder="<?= $config['smtp_password_encrypted'] ? 'Leave blank to keep current' : 'Enter password' ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label-custom">From Name</label>
                            <input type="text" name="from_name" class="form-control-custom" value="<?= e($config['from_name'] ?? '') ?>" placeholder="John Smith">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">From Email</label>
                            <input type="email" name="from_email" class="form-control-custom" value="<?= e($config['from_email'] ?? '') ?>" placeholder="you@example.com">
                        </div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" <?= ($config['is_active'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Use this email configuration for sending emails</label>
                    </div>
                    
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Save Configuration</button>
                    <a href="profile.php" class="btn-secondary-custom">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Information</div>
            <div class="card-body">
                <p class="text-muted">
                    <i class="bi bi-info-circle"></i> Configure your personal SMTP settings to send emails from your own email account.
                </p>
                <p class="text-muted">
                    <i class="bi bi-shield-lock"></i> Your password is encrypted before being stored.
                </p>
                <p class="text-muted">
                    <i class="bi bi-person-gear"></i> Administrators can control whether user email or system email is used for specific modules.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>