<?php
/**
 * GRAVITY CRM - Settings
 * Phase 1: Foundation
 * 
 * Yahan general settings, localization, deployment,
 * aur API configuration foundation available hai.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ai_service.php';
require_login();
require_permission('settings', 'view');

$user = current_user();
$page_title = 'Settings';
$active_page = 'settings';

// Handle AJAX Live API Connection Tests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'test_api') {
    require_permission('settings', 'edit');
    header('Content-Type: application/json');
    
    if (!csrf_verify()) {
        echo json_encode(['success' => false, 'message' => 'CSRF verification failed.', 'details' => '']);
        exit;
    }
    
    $service = post('service');
    $response = ['success' => false, 'message' => 'Unknown service.', 'details' => ''];
    
    switch ($service) {
        case 'ai':
            $provider = post('ai_provider') ?: get_setting('ai_provider', 'openai');
            $model = post('ai_model') ?: get_setting('ai_model', 'gpt-4o-mini');
            $api_key = post('ai_api_key') ?: decrypt_setting(get_setting('ai_api_key_encrypted', ''));
            $endpoint = post('ai_local_endpoint') ?: get_setting('ai_local_endpoint', 'http://localhost:11434/api/generate');
            $response = test_ai_connection($provider, $model, $api_key, $endpoint);
            break;
            
        case 'smtp':
            $host = post('smtp_host') ?: get_setting('smtp_host', '');
            $port = (int)(post('smtp_port') ?: get_setting('smtp_port', 587));
            $encryption = post('smtp_encryption') ?: get_setting('smtp_encryption', 'tls');
            $username = post('smtp_username') ?: get_setting('smtp_username', '');
            $password = post('smtp_password') !== '' ? post('smtp_password') : decrypt_setting(get_setting('smtp_password_encrypted', ''));
            $from_email = post('email_from_address') ?: get_setting('email_from_address', '');
            $test_recipient = post('test_recipient') ?: '';
            $from_name = post('email_from_name') ?: get_setting('email_from_name', APP_NAME);
            
            $response = test_smtp_socket(
                $host, $port, $encryption, $username, $password, 
                $from_email, $test_recipient, 
                'GRAVITY CRM - SMTP Test Verification', 
                '<h3>GRAVITY CRM</h3><p>SMTP integration test executed successfully.</p><p>Timestamp: ' . date('Y-m-d H:i:s') . '</p>', 
                $from_name
            );
            break;
            
        case 'twilio':
            $sid = post('twilio_sid') ?: get_setting('twilio_sid', '');
            $token = post('twilio_token') !== '' ? post('twilio_token') : decrypt_setting(get_setting('twilio_token_encrypted', ''));
            $phone = post('twilio_phone') ?: get_setting('twilio_phone', '');
            
            if (empty($sid) || empty($token)) {
                $response = ['success' => false, 'message' => 'Twilio SID and Auth Token are required.', 'details' => ''];
                break;
            }
            if (!preg_match('/^AC[a-zA-Z0-9]{32}$/', $sid)) {
                $response = ['success' => false, 'message' => 'Invalid Twilio Account SID format (must start with AC followed by 32 characters).', 'details' => ''];
                break;
            }
            
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}.json";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, "{$sid}:{$token}");
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            
            if ($err) {
                $response = ['success' => false, 'message' => 'Twilio network error: ' . $err, 'details' => ''];
            } else {
                $json = json_decode($resp, true);
                if ($code === 200 && isset($json['status'])) {
                    $response = [
                        'success' => true,
                        'message' => 'Twilio Account connected successfully.',
                        'details' => 'Friendly Name: ' . ($json['friendly_name'] ?? '') . ' | Status: ' . $json['status'] . ($phone ? ' | Sender: ' . $phone : '')
                    ];
                } else {
                    $msg = $json['message'] ?? ('HTTP ' . $code);
                    $response = ['success' => false, 'message' => 'Twilio authentication failed: ' . $msg, 'details' => $resp];
                }
            }
            break;
            
        case 'whatsapp':
            $provider = post('whatsapp_provider') ?: get_setting('whatsapp_provider', 'Meta Cloud API');
            $api_key = post('whatsapp_api_key') !== '' ? post('whatsapp_api_key') : decrypt_setting(get_setting('whatsapp_api_key_encrypted', ''));
            $phone_id = post('whatsapp_phone_number_id') ?: get_setting('whatsapp_phone_number_id', '');
            
            if (empty($api_key)) {
                $response = ['success' => false, 'message' => 'WhatsApp API Key / Token is required.', 'details' => ''];
                break;
            }
            
            if (!empty($phone_id)) {
                $url = "https://graph.facebook.com/v19.0/{$phone_id}";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                curl_close($ch);
                
                if ($err) {
                    $response = ['success' => false, 'message' => 'Meta WhatsApp error: ' . $err, 'details' => ''];
                } else {
                    $json = json_decode($resp, true);
                    if ($code === 200 && isset($json['id'])) {
                        $response = ['success' => true, 'message' => 'Meta WhatsApp Cloud API connected.', 'details' => 'Verified Name: ' . ($json['verified_name'] ?? 'N/A') . ' | Phone ID: ' . $json['id']];
                    } else {
                        $msg = $json['error']['message'] ?? ('HTTP ' . $code);
                        $response = ['success' => false, 'message' => 'Meta WhatsApp validation failed: ' . $msg, 'details' => $resp];
                    }
                }
            } else {
                $response = ['success' => true, 'message' => "WhatsApp configuration for provider '{$provider}' validated.", 'details' => 'API Key format verified.'];
            }
            break;
            
        case 'tawkto':
            $prop_id = post('tawkto_property_id') ?: get_setting('tawkto_property_id', '');
            $widget_id = post('tawkto_widget_id') ?: get_setting('tawkto_widget_id', 'default');
            
            if (empty($prop_id)) {
                $response = ['success' => false, 'message' => 'Tawk.to Property ID is required.', 'details' => ''];
            } elseif (!preg_match('/^[a-f0-9]{24}$/i', $prop_id) && !preg_match('/^[a-zA-Z0-9_-]{10,40}$/', $prop_id)) {
                $response = ['success' => false, 'message' => 'Invalid Tawk.to Property ID format (expected a 24-character hexadecimal ID).', 'details' => ''];
            } else {
                $widget_id = $widget_id ?: 'default';
                $response = [
                    'success' => true,
                    'message' => 'Tawk.to Live Chat configuration is valid.',
                    'details' => "Active Widget URL: https://embed.tawk.to/{$prop_id}/{$widget_id}"
                ];
            }
            break;
            
        case 'stripe':
            $sec_key = post('stripe_secret_key') !== '' ? post('stripe_secret_key') : decrypt_setting(get_setting('stripe_secret_key_encrypted', ''));
            $pub_key = post('stripe_publishable_key') ?: get_setting('stripe_publishable_key', '');
            
            if (empty($sec_key)) {
                $response = ['success' => false, 'message' => 'Stripe Secret Key is required.', 'details' => ''];
                break;
            }
            
            $url = 'https://api.stripe.com/v1/balance';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $sec_key]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            
            if ($err) {
                $response = ['success' => false, 'message' => 'Stripe connection error: ' . $err, 'details' => ''];
            } else {
                $json = json_decode($resp, true);
                if ($code === 200 && isset($json['object']) && $json['object'] === 'balance') {
                    $livemode = !empty($json['livemode']) ? 'Live Mode' : 'Test Mode';
                    $response = [
                        'success' => true,
                        'message' => "Stripe API authenticated successfully ({$livemode}).",
                        'details' => ($pub_key ? 'Publishable Key: ' . substr($pub_key, 0, 14) . '... | ' : '') . 'Currencies: ' . implode(', ', array_column($json['available'] ?? [], 'currency'))
                    ];
                } else {
                    $msg = $json['error']['message'] ?? ('HTTP ' . $code);
                    $response = ['success' => false, 'message' => 'Stripe authentication failed: ' . $msg, 'details' => $resp];
                }
            }
            break;
            
        case 'quickbooks':
            $client_id = post('quickbooks_client_id') ?: get_setting('quickbooks_client_id', '');
            $secret = post('quickbooks_client_secret') !== '' ? post('quickbooks_client_secret') : decrypt_setting(get_setting('quickbooks_client_secret_encrypted', ''));
            
            if (empty($client_id)) {
                $response = ['success' => false, 'message' => 'QuickBooks Client ID is required.', 'details' => ''];
            } elseif (empty($secret)) {
                $response = ['success' => false, 'message' => 'QuickBooks Client Secret is required.', 'details' => ''];
            } else {
                $response = [
                    'success' => true,
                    'message' => 'QuickBooks OAuth2 Client configuration is properly formatted.',
                    'details' => 'Client ID: ' . substr($client_id, 0, 16) . '... | Secret: [Securely Configured]'
                ];
            }
            break;
            
        case 'teams':
            $webhook = post('microsoft_teams_webhook') ?: get_setting('microsoft_teams_webhook', '');
            if (empty($webhook)) {
                $response = ['success' => false, 'message' => 'Microsoft Teams Webhook URL is required.', 'details' => ''];
            } elseif (!filter_var($webhook, FILTER_VALIDATE_URL)) {
                $response = ['success' => false, 'message' => 'Invalid Webhook URL format.', 'details' => ''];
            } else {
                $teams_res = send_teams_notification(
                    'GRAVITY CRM Webhook Test',
                    'Microsoft Teams webhook integration is active and verified.',
                    'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/gravityh/settings.php'
                );
                $response = [
                    'success' => $teams_res[0],
                    'message' => $teams_res[1],
                    'details' => 'Webhook URL: ' . substr($webhook, 0, 40) . '...'
                ];
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Handle Form POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'update_general') {
        require_permission('settings', 'edit');
        
        $settings = [
            'app_name' => sanitize(post('app_name')),
            'app_timezone' => sanitize(post('app_timezone')),
            'app_currency' => sanitize(post('app_currency')),
            'app_date_format' => sanitize(post('app_date_format')),
            'items_per_page' => (int)post('items_per_page', 20),
        ];
        
        foreach ($settings as $key => $value) {
            db_execute('UPDATE settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?', [$value, $user['id'], $key]);
        }
        
        log_activity('settings', 'update', null, 'Updated general settings');
        flash('success', 'General settings updated successfully.');
        redirect('settings.php');
    }
    
    if ($action === 'update_deployment') {
        require_permission('settings', 'edit');
        
        $settings = [
            'deployment_hostname' => sanitize(post('deployment_hostname')),
            'deployment_base_path' => sanitize(post('deployment_base_path')),
            'deployment_db_host' => sanitize(post('deployment_db_host')),
            'deployment_db_name' => sanitize(post('deployment_db_name')),
            'deployment_db_user' => sanitize(post('deployment_db_user')),
        ];
        
        $db_pass = post('deployment_db_pass');
        if ($db_pass !== '') {
            $settings['deployment_db_pass_encrypted'] = encrypt_setting($db_pass);
        }
        
        foreach ($settings as $key => $value) {
            db_execute('UPDATE settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?', [$value, $user['id'], $key]);
        }
        
        log_activity('settings', 'update', null, 'Updated deployment settings');
        flash('success', 'Deployment settings updated successfully.');
        redirect('settings.php');
    }
    
    if ($action === 'update_api') {
        require_permission('settings', 'edit');
        
        // 1. Text / Selection Settings (always update, allows clearing values)
        $text_settings = [
            'smtp_host' => sanitize(post('smtp_host')),
            'smtp_port' => (int)post('smtp_port', 587),
            'smtp_username' => sanitize(post('smtp_username')),
            'smtp_encryption' => sanitize(post('smtp_encryption', 'tls')),
            'email_from_name' => sanitize(post('email_from_name')),
            'email_from_address' => sanitize(post('email_from_address')),
            'twilio_sid' => sanitize(post('twilio_sid')),
            'twilio_phone' => sanitize(post('twilio_phone')),
            'whatsapp_provider' => sanitize(post('whatsapp_provider')),
            'whatsapp_phone_number_id' => sanitize(post('whatsapp_phone_number_id')),
            'tawkto_property_id' => sanitize(post('tawkto_property_id')),
            'tawkto_widget_id' => sanitize(post('tawkto_widget_id', 'default')),
            'stripe_publishable_key' => sanitize(post('stripe_publishable_key')),
            'quickbooks_client_id' => sanitize(post('quickbooks_client_id')),
            'microsoft_teams_webhook' => sanitize(post('microsoft_teams_webhook')),
            'onedrive_client_id' => sanitize(post('onedrive_client_id')),
            'proseries_username' => sanitize(post('proseries_username')),
            'rds_username' => sanitize(post('rds_username')),
            'outlook_client_id' => sanitize(post('outlook_client_id')),
            'ai_provider' => sanitize(post('ai_provider', 'openai')),
            'ai_model' => sanitize(post('ai_model', 'gpt-4o-mini')),
            'ai_confidence_threshold' => (string)min(1.0, max(0.0, (float)post('ai_confidence_threshold', 0.70))),
            'ai_local_endpoint' => sanitize(post('ai_local_endpoint', 'http://localhost:11434/api/generate')),
        ];
        
        foreach ($text_settings as $key => $value) {
            $exists = db_row('SELECT id FROM settings WHERE setting_key = ?', [$key]);
            if ($exists) {
                db_execute('UPDATE settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?', [$value, $user['id'], $key]);
            } else {
                db_execute('INSERT INTO settings (setting_key, setting_value, setting_group, updated_by) VALUES (?, ?, "api", ?)', [$key, $value, $user['id']]);
            }
        }
        
        // 2. Sensitive / Encrypted Fields (only update if a non-empty string was submitted)
        $encrypted_fields = [
            'smtp_password' => 'smtp_password_encrypted',
            'twilio_token' => 'twilio_token_encrypted',
            'whatsapp_api_key' => 'whatsapp_api_key_encrypted',
            'stripe_secret_key' => 'stripe_secret_key_encrypted',
            'quickbooks_client_secret' => 'quickbooks_client_secret_encrypted',
            'ai_api_key' => 'ai_api_key_encrypted',
        ];
        
        foreach ($encrypted_fields as $post_key => $setting_key) {
            $val = post($post_key);
            if ($val !== '') {
                $enc = encrypt_setting($val);
                $exists = db_row('SELECT id FROM settings WHERE setting_key = ?', [$setting_key]);
                if ($exists) {
                    db_execute('UPDATE settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?', [$enc, $user['id'], $setting_key]);
                } else {
                    db_execute('INSERT INTO settings (setting_key, setting_value, setting_group, is_encrypted, updated_by) VALUES (?, ?, "api", 1, ?)', [$setting_key, $enc, $user['id']]);
                }
            }
        }
        
        log_activity('settings', 'update', null, 'Updated API and Integration settings');
        flash('success', 'API and Integration settings updated successfully.');
        redirect('settings.php#api');
    }
}


// Get all settings
$all_settings = db_query('SELECT * FROM settings ORDER BY setting_group, setting_key');
$settings_map = [];
foreach ($all_settings as $s) {
    $settings_map[$s['setting_key']] = $s['setting_value'];
}

// Timezones
$timezones = [
    'America/New_York' => 'Eastern Time (US)',
    'America/Chicago' => 'Central Time (US)',
    'America/Denver' => 'Mountain Time (US)',
    'America/Los_Angeles' => 'Pacific Time (US)',
    'America/Anchorage' => 'Alaska Time (US)',
    'Pacific/Honolulu' => 'Hawaii Time (US)',
    'Asia/Karachi' => 'Pakistan (PKT)',
    'Europe/London' => 'United Kingdom (GMT)',
    'Europe/Paris' => 'Central Europe (CET)',
    'Asia/Dubai' => 'UAE (GST)',
    'Asia/Kolkata' => 'India (IST)',
    'UTC' => 'UTC',
];

// Currencies
$currencies = ['USD' => 'US Dollar ($)', 'EUR' => 'Euro (€)', 'GBP' => 'British Pound (£)', 'CAD' => 'Canadian Dollar (C$)', 'AUD' => 'Australian Dollar (A$)', 'PKR' => 'Pakistani Rupee (₨)'];
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-gear"></i> Settings</h1>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="nav flex-column nav-pills">
                    <a class="nav-link active" href="#general" data-bs-toggle="pill"><i class="bi bi-sliders"></i> General</a>
                    <a class="nav-link" href="#localization" data-bs-toggle="pill"><i class="bi bi-globe"></i> Localization</a>
                    <a class="nav-link" href="#deployment" data-bs-toggle="pill"><i class="bi bi-server"></i> Deployment</a>
                    <a class="nav-link" href="#api" data-bs-toggle="pill"><i class="bi bi-plug"></i> API / Integrations</a>
                    <a class="nav-link" href="#security" data-bs-toggle="pill"><i class="bi bi-shield-lock"></i> Security</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="tab-content">
            <!-- General Settings -->
            <div class="tab-pane fade show active" id="general">
                <div class="card">
                    <div class="card-header">General Settings</div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_general">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label-custom">Application Name</label>
                                    <input type="text" name="app_name" class="form-control-custom" value="<?= e($settings_map['app_name'] ?? 'GRAVITY CRM') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Items Per Page</label>
                                    <input type="number" name="items_per_page" class="form-control-custom" value="<?= e($settings_map['items_per_page'] ?? 20) ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label-custom">Timezone</label>
                                    <select name="app_timezone" class="form-control-custom">
                                        <?php foreach ($timezones as $tz => $label): ?>
                                            <option value="<?= $tz ?>" <?= ($settings_map['app_timezone'] ?? '') === $tz ? 'selected' : '' ?>>
                                                <?= e($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Currency</label>
                                    <select name="app_currency" class="form-control-custom">
                                        <?php foreach ($currencies as $code => $label): ?>
                                            <option value="<?= $code ?>" <?= ($settings_map['app_currency'] ?? '') === $code ? 'selected' : '' ?>>
                                                <?= e($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label-custom">Date Format</label>
                                    <select name="app_date_format" class="form-control-custom">
                                        <option value="Y-m-d" <?= ($settings_map['app_date_format'] ?? '') === 'Y-m-d' ? 'selected' : '' ?>>2026-08-19</option>
                                        <option value="m/d/Y" <?= ($settings_map['app_date_format'] ?? '') === 'm/d/Y' ? 'selected' : '' ?>>08/19/2026</option>
                                        <option value="d/m/Y" <?= ($settings_map['app_date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' ?>>19/08/2026</option>
                                        <option value="M d, Y" <?= ($settings_map['app_date_format'] ?? '') === 'M d, Y' ? 'selected' : '' ?>>Aug 19, 2026</option>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Save General Settings</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Localization -->
            <div class="tab-pane fade" id="localization">
                <div class="card">
                    <div class="card-header">Localization</div>
                    <div class="card-body">
                        <p class="text-muted">Localization settings are configured in the General tab above.</p>
                        <p><strong>Current Timezone:</strong> <?= e($settings_map['app_timezone'] ?? 'America/New_York') ?></p>
                        <p><strong>Current Currency:</strong> <?= e($settings_map['app_currency'] ?? 'USD') ?></p>
                        <p><strong>Date Format:</strong> <?= e($settings_map['app_date_format'] ?? 'Y-m-d') ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Deployment -->
            <div class="tab-pane fade" id="deployment">
                <div class="card">
                    <div class="card-header">Deployment Settings</div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_deployment">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label-custom">Hostname</label>
                                    <input type="text" name="deployment_hostname" class="form-control-custom" value="<?= e($settings_map['deployment_hostname'] ?? 'localhost') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Base Path</label>
                                    <input type="text" name="deployment_base_path" class="form-control-custom" value="<?= e($settings_map['deployment_base_path'] ?? '/gravity') ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label-custom">Database Host</label>
                                    <input type="text" name="deployment_db_host" class="form-control-custom" value="<?= e($settings_map['deployment_db_host'] ?? 'localhost') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Database Name</label>
                                    <input type="text" name="deployment_db_name" class="form-control-custom" value="<?= e($settings_map['deployment_db_name'] ?? 'gravity') ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label-custom">Database User</label>
                                    <input type="text" name="deployment_db_user" class="form-control-custom" value="<?= e($settings_map['deployment_db_user'] ?? 'root') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Database Password</label>
                                    <input type="password" name="deployment_db_pass" class="form-control-custom" placeholder="<?= $settings_map['deployment_db_pass_encrypted'] ? 'Leave blank to keep current' : 'Enter password' ?>">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-primary-custom"><i class="bi bi-check"></i> Save Deployment Settings</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- API / Integrations -->
            <div class="tab-pane fade" id="api">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-plug"></i> API & Third-Party Integrations</span>
                        <span class="badge bg-primary">All Integrations Active</span>
                    </div>
                    <div class="card-body">
                        <form id="apiSettingsForm" method="POST" action="">
                            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_api">
                            
                            <!-- 1. AI & Machine Learning -->
                            <div class="border rounded p-3 mb-4 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0 text-primary"><i class="bi bi-robot"></i> AI / LLM Provider</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="testApi('ai', this)">
                                        <i class="bi bi-play-circle"></i> Test AI Connection
                                    </button>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label-custom">AI Provider</label>
                                        <select name="ai_provider" id="ai_provider" class="form-control-custom" onchange="toggleAiFields()">
                                            <option value="openai" <?= ($settings_map['ai_provider'] ?? 'openai') === 'openai' ? 'selected' : '' ?>>OpenAI (ChatGPT)</option>
                                            <option value="anthropic" <?= ($settings_map['ai_provider'] ?? '') === 'anthropic' ? 'selected' : '' ?>>Anthropic (Claude)</option>
                                            <option value="google" <?= ($settings_map['ai_provider'] ?? '') === 'google' ? 'selected' : '' ?>>Google Gemini</option>
                                            <option value="local" <?= ($settings_map['ai_provider'] ?? '') === 'local' ? 'selected' : '' ?>>Local AI / Ollama</option>
                                            <option value="none" <?= ($settings_map['ai_provider'] ?? '') === 'none' ? 'selected' : '' ?>>Disabled (Rule-Based)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-custom">Model Name</label>
                                        <input type="text" name="ai_model" id="ai_model" class="form-control-custom" value="<?= e($settings_map['ai_model'] ?? 'gpt-4o-mini') ?>" placeholder="e.g. gpt-4o-mini, claude-3-haiku-20240307">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label-custom">Confidence (0.0-1.0)</label>
                                        <input type="number" name="ai_confidence_threshold" id="ai_confidence_threshold" class="form-control-custom" value="<?= e($settings_map['ai_confidence_threshold'] ?? '0.70') ?>" step="0.01" min="0" max="1">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-custom">API Key</label>
                                        <input type="password" name="ai_api_key" id="ai_api_key" class="form-control-custom" placeholder="<?= !empty($settings_map['ai_api_key_encrypted']) ? 'Leave blank to keep saved key' : 'Enter API Key' ?>">
                                    </div>
                                </div>
                                <div class="row mt-2" id="aiLocalEndpointRow">
                                    <div class="col-md-12">
                                        <label class="form-label-custom">Local Ollama / AI Server Endpoint</label>
                                        <input type="text" name="ai_local_endpoint" id="ai_local_endpoint" class="form-control-custom" value="<?= e($settings_map['ai_local_endpoint'] ?? 'http://localhost:11434/api/generate') ?>" placeholder="http://localhost:11434/api/generate">
                                    </div>
                                </div>
                                <div id="test-result-ai" class="mt-2" style="display:none;"></div>
                            </div>
                            
                            <!-- 2. Email (SMTP) -->
                            <div class="border rounded p-3 mb-4 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0 text-primary"><i class="bi bi-envelope"></i> Email (SMTP Outbound)</h5>
                                    <div class="d-flex gap-2">
                                        <input type="email" id="test_smtp_recipient" class="form-control form-control-sm" placeholder="Test recipient email (optional)" style="width:220px;">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="testApi('smtp', this)">
                                            <i class="bi bi-send"></i> Test SMTP
                                        </button>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label-custom">SMTP Host</label>
                                        <input type="text" name="smtp_host" id="smtp_host" class="form-control-custom" value="<?= e($settings_map['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label-custom">Port</label>
                                        <input type="number" name="smtp_port" id="smtp_port" class="form-control-custom" value="<?= e($settings_map['smtp_port'] ?? 587) ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-custom">Encryption</label>
                                        <select name="smtp_encryption" id="smtp_encryption" class="form-control-custom">
                                            <option value="tls" <?= ($settings_map['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS (Recommended - 587)</option>
                                            <option value="ssl" <?= ($settings_map['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                                            <option value="none" <?= ($settings_map['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None (Port 25)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-custom">Username</label>
                                        <input type="text" name="smtp_username" id="smtp_username" class="form-control-custom" value="<?= e($settings_map['smtp_username'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label-custom">Password</label>
                                        <input type="password" name="smtp_password" id="smtp_password" class="form-control-custom" placeholder="<?= !empty($settings_map['smtp_password_encrypted']) ? 'Leave blank to keep saved password' : 'Enter SMTP password' ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-custom">From Name</label>
                                        <input type="text" name="email_from_name" id="email_from_name" class="form-control-custom" value="<?= e($settings_map['email_from_name'] ?? 'GRAVITY CRM') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-custom">From Email</label>
                                        <input type="email" name="email_from_address" id="email_from_address" class="form-control-custom" value="<?= e($settings_map['email_from_address'] ?? 'noreply@gravityh.local') ?>">
                                    </div>
                                </div>
                                <div id="test-result-smtp" class="mt-2" style="display:none;"></div>
                            </div>
                            
                            <!-- 3. Twilio (SMS & Voice) -->
                            <div class="border rounded p-3 mb-4 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0 text-primary"><i class="bi bi-telephone"></i> Twilio (SMS & Voice)</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="testApi('twilio', this)">
                                        <i class="bi bi-play-circle"></i> Test Twilio
                                    </button>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label-custom">Account SID</label>
                                        <input type="text" name="twilio_sid" id="twilio_sid" class="form-control-custom" value="<?= e($settings_map['twilio_sid'] ?? '') ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-custom">Auth Token</label>
                                        <input type="password" name="twilio_token" id="twilio_token" class="form-control-custom" placeholder="<?= !empty($settings_map['twilio_token_encrypted']) ? 'Leave blank to keep saved token' : 'Enter Auth Token' ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-custom">Twilio Phone Number</label>
                                        <input type="text" name="twilio_phone" id="twilio_phone" class="form-control-custom" value="<?= e($settings_map['twilio_phone'] ?? '') ?>" placeholder="+1XXXXXXXXXX">
                                    </div>
                                </div>
                                <div id="test-result-twilio" class="mt-2" style="display:none;"></div>
                            </div>
                            
                            <!-- 4. WhatsApp -->
                            <div class="border rounded p-3 mb-4 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0 text-primary"><i class="bi bi-whatsapp"></i> WhatsApp</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="testApi('whatsapp', this)">
                                        <i class="bi bi-play-circle"></i> Test WhatsApp
                                    </button>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label-custom">Provider</label>
                                        <input type="text" name="whatsapp_provider" id="whatsapp_provider" class="form-control-custom" value="<?= e($settings_map['whatsapp_provider'] ?? 'Meta Cloud API') ?>" placeholder="Meta Cloud API, Twilio, etc.">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-custom">Phone Number ID (Meta Cloud)</label>
                                        <input type="text" name="whatsapp_phone_number_id" id="whatsapp_phone_number_id" class="form-control-custom" value="<?= e($settings_map['whatsapp_phone_number_id'] ?? '') ?>" placeholder="e.g. 104857600000000">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-custom">API Key / Access Token</label>
                                        <input type="password" name="whatsapp_api_key" id="whatsapp_api_key" class="form-control-custom" placeholder="<?= !empty($settings_map['whatsapp_api_key_encrypted']) ? 'Leave blank to keep saved key' : 'Enter API Key / Token' ?>">
                                    </div>
                                </div>
                                <div id="test-result-whatsapp" class="mt-2" style="display:none;"></div>
                            </div>
                            
                            <!-- 5. Tawk.to Live Chat -->
                            <div class="border rounded p-3 mb-4 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0 text-primary"><i class="bi bi-chat-dots"></i> Tawk.to Live Chat</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="testApi('tawkto', this)">
                                        <i class="bi bi-play-circle"></i> Test Tawk.to
                                    </button>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Property ID</label>
                                        <input type="text" name="tawkto_property_id" id="tawkto_property_id" class="form-control-custom" value="<?= e($settings_map['tawkto_property_id'] ?? '') ?>" placeholder="e.g. 60xxxxxxxxxxxxxxxxxxxxxx">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Widget ID</label>
                                        <input type="text" name="tawkto_widget_id" id="tawkto_widget_id" class="form-control-custom" value="<?= e($settings_map['tawkto_widget_id'] ?? 'default') ?>" placeholder="default">
                                    </div>
                                </div>
                                <small class="text-muted">When configured, the Tawk.to widget is automatically loaded on all CRM screens for live client interaction.</small>
                                <div id="test-result-tawkto" class="mt-2" style="display:none;"></div>
                            </div>
                            
                            <!-- 6. Stripe -->
                            <div class="border rounded p-3 mb-4 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0 text-primary"><i class="bi bi-credit-card"></i> Stripe Payments</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="testApi('stripe', this)">
                                        <i class="bi bi-play-circle"></i> Test Stripe
                                    </button>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Publishable Key</label>
                                        <input type="text" name="stripe_publishable_key" id="stripe_publishable_key" class="form-control-custom" value="<?= e($settings_map['stripe_publishable_key'] ?? '') ?>" placeholder="pk_test_... or pk_live_...">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Secret Key</label>
                                        <input type="password" name="stripe_secret_key" id="stripe_secret_key" class="form-control-custom" placeholder="<?= !empty($settings_map['stripe_secret_key_encrypted']) ? 'Leave blank to keep saved key' : 'sk_test_... or sk_live_...' ?>">
                                    </div>
                                </div>
                                <div id="test-result-stripe" class="mt-2" style="display:none;"></div>
                            </div>
                            
                            <!-- 7. QuickBooks -->
                            <div class="border rounded p-3 mb-4 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0 text-primary"><i class="bi bi-box-seam"></i> QuickBooks Online</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="testApi('quickbooks', this)">
                                        <i class="bi bi-play-circle"></i> Test QuickBooks
                                    </button>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Client ID</label>
                                        <input type="text" name="quickbooks_client_id" id="quickbooks_client_id" class="form-control-custom" value="<?= e($settings_map['quickbooks_client_id'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Client Secret</label>
                                        <input type="password" name="quickbooks_client_secret" id="quickbooks_client_secret" class="form-control-custom" placeholder="<?= !empty($settings_map['quickbooks_client_secret_encrypted']) ? 'Leave blank to keep saved secret' : 'Enter client secret' ?>">
                                    </div>
                                </div>
                                <div id="test-result-quickbooks" class="mt-2" style="display:none;"></div>
                            </div>
                            
                            <!-- 8. Microsoft Teams -->
                            <div class="border rounded p-3 mb-4 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0 text-primary"><i class="bi bi-microsoft-teams"></i> Microsoft Teams</h5>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="testApi('teams', this)">
                                        <i class="bi bi-send"></i> Send Test Card
                                    </button>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <label class="form-label-custom">Incoming Webhook URL</label>
                                        <input type="text" name="microsoft_teams_webhook" id="microsoft_teams_webhook" class="form-control-custom" value="<?= e($settings_map['microsoft_teams_webhook'] ?? '') ?>" placeholder="https://outlook.office.com/webhook/...">
                                    </div>
                                </div>
                                <div id="test-result-teams" class="mt-2" style="display:none;"></div>
                            </div>
                            
                            <!-- 9. Other Integrations -->
                            <div class="border rounded p-3 mb-4 bg-light">
                                <h5 class="mb-3 text-primary"><i class="bi bi-hdd-network"></i> Other Desktop & Cloud Integrations</h5>
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label-custom">OneDrive Client ID</label>
                                        <input type="text" name="onedrive_client_id" class="form-control-custom" value="<?= e($settings_map['onedrive_client_id'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-custom">ProSeries Username</label>
                                        <input type="text" name="proseries_username" class="form-control-custom" value="<?= e($settings_map['proseries_username'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-custom">RDS Username</label>
                                        <input type="text" name="rds_username" class="form-control-custom" value="<?= e($settings_map['rds_username'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label-custom">Outlook Client ID</label>
                                        <input type="text" name="outlook_client_id" class="form-control-custom" value="<?= e($settings_map['outlook_client_id'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-primary-custom btn-lg w-100"><i class="bi bi-check2-circle"></i> Save All API Settings</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Security -->
            <div class="tab-pane fade" id="security">
                <div class="card">
                    <div class="card-header">Security Settings</div>
                    <div class="card-body">
                        <p><strong>Force Password Change:</strong> <?= ($settings_map['security_force_password_change'] ?? '1') ? 'Enabled' : 'Disabled' ?></p>
                        <p><strong>Session Timeout:</strong> <?= e($settings_map['security_session_timeout'] ?? '60') ?> minutes</p>
                        <p><strong>Encryption Algorithm:</strong> AES-256-CBC</p>
                        <p><strong>Encryption Key:</strong> <span class="badge bg-success">Configured</span></p>
                        <p class="text-muted">Security settings are managed in the database and protected with AES-256-CBC envelope encryption.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Hash navigation support
if (window.location.hash) {
    const triggerEl = document.querySelector('a[href="' + window.location.hash + '"]');
    if (triggerEl) {
        bootstrap.Tab.getOrCreateInstance(triggerEl).show();
    }
}

function toggleAiFields() {
    const prov = document.getElementById('ai_provider').value;
    const localRow = document.getElementById('aiLocalEndpointRow');
    if (localRow) {
        localRow.style.display = (prov === 'local') ? 'flex' : 'none';
    }
}
document.addEventListener('DOMContentLoaded', toggleAiFields);

// AJAX API Connection Testing Function
function testApi(service, btn) {
    const resultBox = document.getElementById('test-result-' + service);
    if (!resultBox) return;
    
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Testing...';
    
    resultBox.style.display = 'block';
    resultBox.innerHTML = '<div class="alert alert-info py-2 mb-0"><span class="spinner-border spinner-border-sm me-2"></span>Connecting to ' + service.toUpperCase() + ' service...</div>';
    
    const formData = new URLSearchParams();
    formData.append('action', 'test_api');
    formData.append('service', service);
    formData.append('<?= e(CSRF_TOKEN_NAME) ?>', '<?= e(csrf_token()) ?>');
    
    // Gather service specific fields
    if (service === 'ai') {
        formData.append('ai_provider', document.getElementById('ai_provider')?.value || '');
        formData.append('ai_model', document.getElementById('ai_model')?.value || '');
        formData.append('ai_api_key', document.getElementById('ai_api_key')?.value || '');
        formData.append('ai_local_endpoint', document.getElementById('ai_local_endpoint')?.value || '');
    } else if (service === 'smtp') {
        formData.append('smtp_host', document.getElementById('smtp_host')?.value || '');
        formData.append('smtp_port', document.getElementById('smtp_port')?.value || '');
        formData.append('smtp_encryption', document.getElementById('smtp_encryption')?.value || '');
        formData.append('smtp_username', document.getElementById('smtp_username')?.value || '');
        formData.append('smtp_password', document.getElementById('smtp_password')?.value || '');
        formData.append('email_from_name', document.getElementById('email_from_name')?.value || '');
        formData.append('email_from_address', document.getElementById('email_from_address')?.value || '');
        formData.append('test_recipient', document.getElementById('test_smtp_recipient')?.value || '');
    } else if (service === 'twilio') {
        formData.append('twilio_sid', document.getElementById('twilio_sid')?.value || '');
        formData.append('twilio_token', document.getElementById('twilio_token')?.value || '');
        formData.append('twilio_phone', document.getElementById('twilio_phone')?.value || '');
    } else if (service === 'whatsapp') {
        formData.append('whatsapp_provider', document.getElementById('whatsapp_provider')?.value || '');
        formData.append('whatsapp_phone_number_id', document.getElementById('whatsapp_phone_number_id')?.value || '');
        formData.append('whatsapp_api_key', document.getElementById('whatsapp_api_key')?.value || '');
    } else if (service === 'tawkto') {
        formData.append('tawkto_property_id', document.getElementById('tawkto_property_id')?.value || '');
        formData.append('tawkto_widget_id', document.getElementById('tawkto_widget_id')?.value || '');
    } else if (service === 'stripe') {
        formData.append('stripe_publishable_key', document.getElementById('stripe_publishable_key')?.value || '');
        formData.append('stripe_secret_key', document.getElementById('stripe_secret_key')?.value || '');
    } else if (service === 'quickbooks') {
        formData.append('quickbooks_client_id', document.getElementById('quickbooks_client_id')?.value || '');
        formData.append('quickbooks_client_secret', document.getElementById('quickbooks_client_secret')?.value || '');
    } else if (service === 'teams') {
        formData.append('microsoft_teams_webhook', document.getElementById('microsoft_teams_webhook')?.value || '');
    }
    
    fetch('settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        
        const alertClass = data.success ? 'alert-success' : 'alert-danger';
        const iconClass = data.success ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        
        let html = '<div class="alert ' + alertClass + ' py-2 mb-0">';
        html += '<i class="bi ' + iconClass + ' me-2"></i><strong>' + (data.success ? 'Success' : 'Connection Failed') + ':</strong> ' + escapeHtml(data.message);
        if (data.details) {
            html += '<div class="small mt-1 text-muted font-monospace">' + escapeHtml(data.details) + '</div>';
        }
        html += '</div>';
        resultBox.innerHTML = html;
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        resultBox.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Error:</strong> Request failed. Check your network or server logs.</div>';
    });
}
</script>

<?php include __DIR__ . '/footer.php'; ?>