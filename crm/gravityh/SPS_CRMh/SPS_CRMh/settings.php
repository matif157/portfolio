<?php
/**
 * SPS Professional Services CRM
 * System Settings, Twilio API, SMTP Email & Theme Customizer
 */
require_once __DIR__ . '/auth.php';
require_permission('admin.settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $settings_to_update = [
        'company_name'              => trim($_POST['company_name'] ?? 'Syed Professional Services (SPS) CRM'),
        'app_theme'                 => trim($_POST['app_theme'] ?? 'navy'),
        'timezone'                  => trim($_POST['timezone'] ?? 'America/New_York'),
        'currency'                  => trim($_POST['currency'] ?? 'USD'),
        // ---- SMTP Email Settings ----
        'email_provider_status'     => $_POST['email_provider_status'] ?? 'not_configured',
        'smtp_host'                 => trim($_POST['smtp_host'] ?? ''),
        'smtp_port'                 => trim($_POST['smtp_port'] ?? '587'),
        'smtp_username'             => trim($_POST['smtp_username'] ?? ''),
        'smtp_password'             => trim($_POST['smtp_password'] ?? ''),
        'smtp_from_email'           => trim($_POST['smtp_from_email'] ?? ''),
        // ---- Twilio Voice & SMS ----
        'sms_provider_status'       => $_POST['sms_provider_status'] ?? 'not_configured',
        'voice_provider_status'     => $_POST['voice_provider_status'] ?? 'not_configured',
        'twilio_account_sid'        => trim($_POST['twilio_account_sid'] ?? ''),
        'twilio_auth_token'         => trim($_POST['twilio_auth_token'] ?? ''),
        'twilio_phone_number'       => trim($_POST['twilio_phone_number'] ?? ''),
        // ---- WhatsApp Business API ----
        'whatsapp_provider_status'  => $_POST['whatsapp_provider_status'] ?? 'not_configured',
        'whatsapp_phone_number_id'  => trim($_POST['whatsapp_phone_number_id'] ?? ''),
        'whatsapp_access_token'     => trim($_POST['whatsapp_access_token'] ?? ''),
        // ---- Tawk.to Live Chat ----
        // Tawk.to se live chat widget CRM ke har page par auto-inject hoga
        'tawkto_property_id'        => trim($_POST['tawkto_property_id'] ?? ''),
        'tawkto_widget_id'          => trim($_POST['tawkto_widget_id'] ?? 'default'),
        'tawkto_enabled'            => isset($_POST['tawkto_enabled']) ? '1' : '0',
        'tawkto_api_key'            => trim($_POST['tawkto_api_key'] ?? ''),
        // ---- Stripe Payment Gateway ----
        'stripe_publishable_key'    => trim($_POST['stripe_publishable_key'] ?? ''),
        'stripe_secret_key'         => trim($_POST['stripe_secret_key'] ?? ''),
        'stripe_webhook_secret'     => trim($_POST['stripe_webhook_secret'] ?? ''),
        // ---- AI & Misc ----
        'ocr_provider_status'       => $_POST['ocr_provider_status'] ?? 'not_configured',
        'ai_confidence_threshold'   => trim($_POST['ai_confidence_threshold'] ?? '0.85'),
    ];

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settings_to_update as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    log_activity('Update System Settings', 'settings', 0, 'Updated Twilio API, SMTP, Theme, and integration settings', 'stage2_sensitive');
    set_flash('success', 'System settings, Twilio credentials, and CRM Theme saved successfully!');
    redirect('settings.php');
}

$theme = get_setting('app_theme', 'navy');
$email_status = get_setting('email_provider_status', 'not_configured');
$sms_status = get_setting('sms_provider_status', 'not_configured');
$whatsapp_status = get_setting('whatsapp_provider_status', 'not_configured');
$voice_status = get_setting('voice_provider_status', 'not_configured');
$ocr_status = get_setting('ocr_provider_status', 'not_configured');
$ai_threshold = get_setting('ai_confidence_threshold', '0.85');

render_header('System Settings & API Configuration', 'admin');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">System Settings & Twilio API Configuration</h1>
        <p class="page-subtitle">Configure Twilio Call/SMS APIs, Email SMTP, WhatsApp, Theme customizer, and AI thresholds</p>
    </div>
</div>

<!-- Integration Provider Status Cards -->
<div class="card" style="border-top:4px solid var(--accent);">
    <div class="card-title">Integration Connectivity Overview</div>
    <div class="stats-grid" style="grid-template-columns: repeat(5, 1fr);">
        <div style="padding:12px; background:#f8fafc; border-radius:6px; border:1px solid var(--border-color);">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted);">TWILIO VOICE CALLS</div>
            <div style="margin-top:6px;"><span class="badge badge-<?= get_setting('twilio_account_sid') ? 'success' : 'danger' ?>"><?= get_setting('twilio_account_sid') ? '✓ Twilio Ready' : '✗ Credentials Missing' ?></span></div>
        </div>

        <div style="padding:12px; background:#f8fafc; border-radius:6px; border:1px solid var(--border-color);">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted);">TWILIO SMS API</div>
            <div style="margin-top:6px;"><span class="badge badge-<?= get_setting('twilio_account_sid') ? 'success' : 'danger' ?>"><?= get_setting('twilio_account_sid') ? '✓ SMS Ready' : '✗ Credentials Missing' ?></span></div>
        </div>

        <div style="padding:12px; background:#f8fafc; border-radius:6px; border:1px solid var(--border-color);">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted);">EMAIL SMTP</div>
            <div style="margin-top:6px;"><span class="badge badge-<?= get_setting('smtp_host') ? 'success' : 'danger' ?>"><?= get_setting('smtp_host') ? '✓ SMTP Configured' : '✗ Config Missing' ?></span></div>
        </div>

        <div style="padding:12px; background:#f8fafc; border-radius:6px; border:1px solid var(--border-color);">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted);">WHATSAPP BUSINESS</div>
            <div style="margin-top:6px;"><span class="badge badge-<?= get_setting('whatsapp_phone_number_id') ? 'success' : 'danger' ?>"><?= get_setting('whatsapp_phone_number_id') ? '✓ Connected' : '✗ Not Configured' ?></span></div>
        </div>

        <div style="padding:12px; background:#f8fafc; border-radius:6px; border:1px solid var(--border-color);">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted);">CRM THEME</div>
            <div style="margin-top:6px;"><span class="badge badge-info"><?= strtoupper($theme) ?> THEME</span></div>
        </div>
    </div>
</div>

<div class="card" style="max-width:850px; margin:0 auto;">
    <form action="settings.php" method="POST">
        <?= csrf_field() ?>

        <h3 style="font-size:16px; font-weight:600; margin-bottom:15px; color:var(--secondary);">🎨 CRM Visual Theme Customizer</h3>

        <div class="form-group">
            <label class="form-label">Select Application Theme Palette</label>
            <select name="app_theme" class="form-control">
                <option value="modern" <?= $theme === 'modern' ? 'selected' : '' ?>>🌟 Modern (Clean Royal Blue & Slate)</option>
                <option value="advanced" <?= $theme === 'advanced' ? 'selected' : '' ?>>⚡ Advanced CSS (Vibrant Violet & Cyan)</option>
                <option value="bold" <?= $theme === 'bold' ? 'selected' : '' ?>>💥 Bold (High Contrast & Strong Weight)</option>
                <option value="basic" <?= $theme === 'basic' ? 'selected' : '' ?>>📄 Basic (Minimalist Gray Monochrome)</option>
                <option value="black" <?= $theme === 'black' ? 'selected' : '' ?>>🖤 Pure Black (True AMOLED OLED)</option>
                <option value="night_eye" <?= $theme === 'night_eye' ? 'selected' : '' ?>>🌙 Night Eye (Dark Slate Blue)</option>
                <option value="user_friendly" <?= $theme === 'user_friendly' ? 'selected' : '' ?>>🍃 User-Friendly (Warm Teal & Emerald)</option>
                <option value="navy" <?= $theme === 'navy' ? 'selected' : '' ?>>⚓ Executive Navy (Classic Corporate)</option>
                <option value="emerald" <?= $theme === 'emerald' ? 'selected' : '' ?>>🌲 Emerald Green (Fresh Accounting)</option>
                <option value="glassmorphism" <?= $theme === 'glassmorphism' ? 'selected' : '' ?>>💎 Glassmorphism Indigo (Modern UI)</option>
                <option value="luxury" <?= $theme === 'luxury' ? 'selected' : '' ?>>👑 Luxury Gold (Executive Premium)</option>
            </select>
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Changes the entire CRM color palette across all pages immediately on save.</div>
        </div>

        <h3 style="font-size:16px; font-weight:600; margin:30px 0 15px 0; color:var(--secondary);">📞 Twilio Telephony & Call / SMS API Setup</h3>

        <div class="form-group">
            <label class="form-label">Twilio Account SID</label>
            <input type="text" name="twilio_account_sid" class="form-control" value="<?= e(get_setting('twilio_account_sid')) ?>" placeholder="ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Twilio Auth Token</label>
                <input type="password" name="twilio_auth_token" class="form-control" value="<?= e(get_setting('twilio_auth_token')) ?>" placeholder="••••••••••••••••••••••••••••••••">
            </div>
            <div class="form-col form-group">
                <label class="form-label">Twilio Outbound Phone Number</label>
                <input type="text" name="twilio_phone_number" class="form-control" value="<?= e(get_setting('twilio_phone_number')) ?>" placeholder="+15550192834">
            </div>
        </div>

        <h3 style="font-size:16px; font-weight:600; margin:30px 0 15px 0; color:var(--secondary);">📧 Email SMTP Server Credentials</h3>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control" value="<?= e(get_setting('smtp_host', 'smtp.gmail.com')) ?>">
            </div>
            <div class="form-col form-group">
                <label class="form-label">SMTP Port</label>
                <input type="text" name="smtp_port" class="form-control" value="<?= e(get_setting('smtp_port', '587')) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">SMTP Username</label>
                <input type="text" name="smtp_username" class="form-control" value="<?= e(get_setting('smtp_username')) ?>" placeholder="user@gmail.com">
            </div>
            <div class="form-col form-group">
                <label class="form-label">SMTP Password / App Secret</label>
                <input type="password" name="smtp_password" class="form-control" value="<?= e(get_setting('smtp_password')) ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Default From Email Address</label>
            <input type="email" name="smtp_from_email" class="form-control" value="<?= e(get_setting('smtp_from_email', 'billing@syedservices.com')) ?>">
        </div>

        <h3 style="font-size:16px; font-weight:600; margin:30px 0 15px 0; color:var(--secondary);">💬 WhatsApp Business API Setup</h3>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">WhatsApp Phone Number ID</label>
                <input type="text" name="whatsapp_phone_number_id" class="form-control" value="<?= e(get_setting('whatsapp_phone_number_id')) ?>" placeholder="104928194012">
            </div>
            <div class="form-col form-group">
                <label class="form-label">WhatsApp Business Access Token</label>
                <input type="password" name="whatsapp_access_token" class="form-control" value="<?= e(get_setting('whatsapp_access_token')) ?>">
            </div>
        </div>

        <h3 style="font-size:16px; font-weight:600; margin:30px 0 15px 0; color:var(--secondary);">💬 Tawk.to Live Chat Widget Integration</h3>

        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:14px 16px; margin-bottom:18px; font-size:13px;">
            <strong>How to connect Tawk.to:</strong>
            1. Go to <a href="https://dashboard.tawk.to" target="_blank" style="color:var(--accent);">dashboard.tawk.to</a> →
            2. Administration → Chat Widget → Copy your <strong>Property ID</strong> &amp; <strong>Widget ID</strong> from the embed script URL →
            3. Paste them below and Save. The live chat bubble will automatically appear on all CRM pages.
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Tawk.to Property ID</label>
                <input type="text" name="tawkto_property_id" class="form-control"
                    value="<?= e(get_setting('tawkto_property_id')) ?>"
                    placeholder="e.g. 5e1234abcde7f123456789ab">
                <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">Found in your Tawk.to dashboard embed snippet URL</div>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Tawk.to Widget ID</label>
                <input type="text" name="tawkto_widget_id" class="form-control"
                    value="<?= e(get_setting('tawkto_widget_id', 'default')) ?>"
                    placeholder="default">
            </div>
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Tawk.to REST API Key <span style="font-size:11px;color:var(--text-muted);">(for reading chat history)</span></label>
                <input type="password" name="tawkto_api_key" class="form-control"
                    value="<?= e(get_setting('tawkto_api_key')) ?>"
                    placeholder="Your Tawk.to REST API Key">
            </div>
            <div class="form-col form-group" style="display:flex;align-items:center;gap:12px;padding-top:28px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
                    <input type="checkbox" name="tawkto_enabled" value="1" <?= get_setting('tawkto_enabled','0') === '1' ? 'checked' : '' ?>>
                    Enable Tawk.to Live Chat Widget on all CRM pages
                </label>
                <?php if (get_setting('tawkto_property_id')): ?>
                    <span class="badge badge-success">✓ Widget Configured</span>
                <?php else: ?>
                    <span class="badge badge-danger">✗ Not Connected</span>
                <?php endif; ?>
            </div>
        </div>

        <h3 style="font-size:16px; font-weight:600; margin:30px 0 15px 0; color:var(--secondary);">💳 Stripe Payment Gateway</h3>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Stripe Publishable Key</label>
                <input type="text" name="stripe_publishable_key" class="form-control"
                    value="<?= e(get_setting('stripe_publishable_key')) ?>"
                    placeholder="pk_live_...">
            </div>
            <div class="form-col form-group">
                <label class="form-label">Stripe Secret Key</label>
                <input type="password" name="stripe_secret_key" class="form-control"
                    value="<?= e(get_setting('stripe_secret_key')) ?>"
                    placeholder="sk_live_...">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Stripe Webhook Secret <span style="font-size:11px;color:var(--text-muted);">(whsec_... for payment event confirmations)</span></label>
            <input type="password" name="stripe_webhook_secret" class="form-control"
                value="<?= e(get_setting('stripe_webhook_secret')) ?>"
                placeholder="whsec_...">
            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">
                Stripe Webhook URL to add in your Stripe Dashboard:
                <code><?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/SPS_CRM/stripe_webhook.php' ?></code>
            </div>
        </div>

        <h3 style="font-size:16px; font-weight:600; margin:30px 0 15px 0; color:var(--secondary);">🤖 AI Engine &amp; General Settings</h3>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Company Name</label>
                <input type="text" name="company_name" class="form-control" value="<?= e(get_setting('company_name', 'Syed Professional Services (SPS) CRM')) ?>">
            </div>
            <div class="form-col form-group">
                <label class="form-label">AI Minimum Confidence Threshold</label>
                <input type="text" name="ai_confidence_threshold" class="form-control" value="<?= e(get_setting('ai_confidence_threshold', '0.85')) ?>">
                <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">0.85 = 85%. Actions below this create a human review task.</div>
            </div>
        </div>

        <div style="margin-top:25px;">
            <button type="submit" class="btn btn-primary">💾 Save All Configuration &amp; Integrations</button>
        </div>
    </form>
</div>

<?php render_footer(); ?>

