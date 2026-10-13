<?php
/**
 * SPS Professional Services CRM
 * Global Helper Functions
 */

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
/**
 * Central Error Logging — system, integration aur automation errors
 * yahan error_logs table me save hote hain taake admin error_logs.php se
 * dekh aur monitor kar sake. (Phase 5 — Error Logs module)
 */
function log_system_error($level, $message, $source = 'system', $trace = null) {
    global $pdo;
    try {
        if (empty($pdo)) return;
        $stmt = $pdo->prepare("INSERT INTO error_logs (level, message, source, file, line, url, user_id, trace) VALUES (?, ?, ?, NULL, NULL, ?, ?, ?)");
        $stmt->execute([
            $level,
            mb_substr($message, 0, 2000),
            $source,
            $_SERVER['REQUEST_URI'] ?? null,
            $_SESSION['user_id'] ?? null,
            $trace ? mb_substr($trace, 0, 4000) : null,
        ]);
    } catch (Throwable $ignored) {
        // DB hi down ho to error log na ho sake — isko silently ignore karte hain
    }
}

// Fatal error shutdown handler — page crash ho jaye to bhi record ho jaye.
function gravity_fatal_handler() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        log_system_error('fatal', $err['message'], 'fatal-handler', 'File: ' . $err['file'] . ' Line: ' . $err['line']);
    }
}
register_shutdown_function('gravity_fatal_handler');


/**
 * HTML Sanitization Helper
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect Helper
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Flash Notification System
 */
function set_flash($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function get_flash_messages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function display_flash_messages() {
    $messages = get_flash_messages();
    if (empty($messages)) return;

    $icons = ['success'=>'✅','warning'=>'⚠️','danger'=>'❌','error'=>'❌','info'=>'ℹ️'];

    foreach ($messages as $msg) {
        $type = in_array($msg['type'], ['success','warning','danger','error','info']) ? $msg['type'] : 'info';
        $cls  = ($type === 'error') ? 'danger' : $type;
        $icon = $icons[$type] ?? 'ℹ️';
        echo '<div class="alert alert-' . $cls . '" data-auto-dismiss="6000">';
        echo '<span class="alert-icon">' . $icon . '</span>';
        echo '<span class="alert-content">' . e($msg['message']) . '</span>';
        echo '<button class="alert-close" type="button">&times;</button>';
        echo '</div>';
    }
}

/**
 * CSRF Protection
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        log_activity('CSRF Violation', 'security', 0, 'Invalid or missing CSRF token attempted', 'stage2_sensitive');
        die('<div style="font-family:sans-serif; padding:40px; text-align:center;"><h2>403 Forbidden - Security Token Validation Failed</h2><p><a href="javascript:history.back()">Go Back</a></p></div>');
    }
}

/**
 * Activity & Audit Logging Engine
 */
function log_activity($action, $module, $entity_id, $description, $stage = 'stage1_normal', $changes = []) {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? null;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activities (user_id, action, module, entity_id, description, stage) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $module, $entity_id, $description, $stage]);
        $activity_id = $pdo->lastInsertId();
        
        // Record field-level changes if stage3 data change
        if (!empty($changes) && is_array($changes)) {
            $stmtChange = $pdo->prepare("INSERT INTO activity_changes (activity_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?)");
            foreach ($changes as $field => $vals) {
                $stmtChange->execute([$activity_id, $field, $vals['old'] ?? null, $vals['new'] ?? null]);
            }
        }
    } catch (Exception $ex) {
        // Fail silently for activity log writes to avoid breaking primary operation
        error_log("Activity logging error: " . $ex->getMessage());
    }
}

/**
 * Bell Notification System Helper
 */
function create_notification($user_id, $title, $message, $link = null, $stage = 'info', $module = 'system') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link, stage, module) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $message, $link, $stage, $module]);
    } catch (Exception $ex) {
        error_log("Notification creation error: " . $ex->getMessage());
    }
}

/**
 * Currency & Date Formatters
 */
function format_currency($amount) {
    return '$' . number_format((float)$amount, 2);
}

function format_date($date_str) {
    if (empty($date_str)) return '-';
    return date('M j, Y', strtotime($date_str));
}

function format_datetime($dt_str) {
    if (empty($dt_str)) return '-';
    return date('M j, Y g:i A', strtotime($dt_str));
}

/**
 * Settings Helper
 */
function get_setting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Exception $ex) {
        return $default;
    }
}

function get_month_name($month_num) {
    $dateObj = DateTime::createFromFormat('!m', $month_num);
    return $dateObj ? $dateObj->format('F') : 'Month ' . $month_num;
}

/**
 * Twilio Call & SMS Dispatch Helper Engine
 */
function send_twilio_sms($client_id, $message_body) {
    global $pdo;
    $sid = get_setting('twilio_account_sid', '');
    $token = get_setting('twilio_auth_token', '');
    $from_num = get_setting('twilio_phone_number', '');
    
    // Fetch Client Phone Number
    $stmtC = $pdo->prepare("SELECT phone, first_name, company_name FROM clients WHERE id = ?");
    $stmtC->execute([$client_id]);
    $client = $stmtC->fetch();
    
    if (!$client || empty($client['phone'])) {
        return ['success' => false, 'error' => 'Client phone number not found.'];
    }
    
    $to_num = $client['phone'];
    $status = 'sent';
    $external_sid = 'SMS-' . bin2hex(random_bytes(8));
    
    if (!empty($sid) && !empty($token) && !empty($from_num)) {
        // Execute Real Twilio API cURL POST Call
        $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
        $data = [
            'From' => $from_num,
            'To' => $to_num,
            'Body' => $message_body
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $resp = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $json = json_decode($resp, true);
        if ($http_code == 201 && isset($json['sid'])) {
            $external_sid = $json['sid'];
            $status = 'delivered';
        } else {
            $status = 'failed';
        }
    }
    
    // Log to Communications Table (Recorded in Client Profile)
    $stmtIns = $pdo->prepare("INSERT INTO communications (client_id, channel, subject, message_body, direction, status, external_sid) VALUES (?, 'sms', 'Twilio SMS Notification', ?, 'outbound', ?, ?)");
    $stmtIns->execute([$client_id, $message_body, $status, $external_sid]);
    
    return ['success' => ($status !== 'failed'), 'sid' => $external_sid];
}

function send_twilio_call($client_id, $call_script) {
    global $pdo;
    $sid = get_setting('twilio_account_sid', '');
    $token = get_setting('twilio_auth_token', '');
    $from_num = get_setting('twilio_phone_number', '');
    
    $stmtC = $pdo->prepare("SELECT phone, first_name, company_name FROM clients WHERE id = ?");
    $stmtC->execute([$client_id]);
    $client = $stmtC->fetch();
    
    if (!$client || empty($client['phone'])) {
        return ['success' => false, 'error' => 'Client phone number not found.'];
    }
    
    $to_num = $client['phone'];
    $status = 'completed';
    $external_sid = 'CALL-' . bin2hex(random_bytes(8));
    $duration = rand(45, 120); // Simulated call duration in seconds
    
    if (!empty($sid) && !empty($token) && !empty($from_num)) {
        // Execute Real Twilio Call API cURL POST Call
        $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Calls.json";
        $twiml = '<Response><Say voice="alice">' . htmlspecialchars($call_script) . '</Say></Response>';
        $data = [
            'From' => $from_num,
            'To' => $to_num,
            'Twiml' => $twiml
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $resp = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $json = json_decode($resp, true);
        if ($http_code == 201 && isset($json['sid'])) {
            $external_sid = $json['sid'];
            $status = 'completed';
        } else {
            $status = 'failed';
        }
    }
    
    // Log Call to Communications Table
    $stmtIns = $pdo->prepare("INSERT INTO communications (client_id, channel, subject, message_body, direction, status, external_sid, call_duration) VALUES (?, 'voice', 'AI Automated Call', ?, 'outbound', ?, ?, ?)");
    $stmtIns->execute([$client_id, $call_script, $status, $external_sid, $duration]);
    
    return ['success' => ($status !== 'failed'), 'sid' => $external_sid, 'duration' => $duration];
}

/**
 * Universal Customizer Helper - Render Custom Fields for Any Entity Section
 */
function render_custom_fields($entity_type, $entity_id = 0) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM custom_fields WHERE entity_type = ? ORDER BY sort_order ASC");
    $stmt->execute([$entity_type]);
    $fields = $stmt->fetchAll();
    
    if (empty($fields)) return;
    
    $values = [];
    if ($entity_id > 0) {
        $stmtV = $pdo->prepare("SELECT field_id, field_value FROM custom_field_values WHERE entity_id = ?");
        $stmtV->execute([$entity_id]);
        $values = $stmtV->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    echo '<div class="custom-fields-section" style="margin-top:20px; padding-top:15px; border-top:1px dashed var(--border-color);">';
    echo '<h4 style="font-size:14px; font-weight:600; color:var(--secondary); margin-bottom:12px;">Custom Section Fields (' . ucfirst($entity_type) . ')</h4>';
    echo '<div class="form-row" style="display:flex; flex-wrap:wrap; gap:15px;">';
    
    foreach ($fields as $field) {
        $val = $values[$field['id']] ?? $field['default_value'] ?? '';
        $req = $field['is_required'] ? 'required' : '';
        $req_mark = $field['is_required'] ? ' <span style="color:var(--danger);">*</span>' : '';
        
        echo '<div class="form-col form-group" style="flex:1; min-width:220px;">';
        echo '<label class="form-label">' . e($field['field_label']) . $req_mark . '</label>';
        
        if ($field['field_type'] === 'textarea') {
            echo '<textarea name="custom_fields[' . $field['id'] . ']" class="form-control" ' . $req . '>' . e($val) . '</textarea>';
        } elseif ($field['field_type'] === 'dropdown') {
            $options = explode("\n", $field['dropdown_options'] ?? '');
            echo '<select name="custom_fields[' . $field['id'] . ']" class="form-control" ' . $req . '>';
            echo '<option value="">-- Select --</option>';
            foreach ($options as $opt) {
                $opt = trim($opt);
                if ($opt !== '') {
                    $sel = ($val === $opt) ? 'selected' : '';
                    echo '<option value="' . e($opt) . '" ' . $sel . '>' . e($opt) . '</option>';
                }
            }
            echo '</select>';
        } elseif ($field['field_type'] === 'date') {
            echo '<input type="date" name="custom_fields[' . $field['id'] . ']" class="form-control" value="' . e($val) . '" ' . $req . '>';
        } elseif ($field['field_type'] === 'currency' || $field['field_type'] === 'number') {
            echo '<input type="number" step="any" name="custom_fields[' . $field['id'] . ']" class="form-control" value="' . e($val) . '" ' . $req . '>';
        } elseif ($field['field_type'] === 'checkbox') {
            $chk = ($val === '1' || $val === 'on') ? 'checked' : '';
            echo '<label style="display:inline-flex; align-items:center; gap:8px; margin-top:8px; cursor:pointer;">';
            echo '<input type="checkbox" name="custom_fields[' . $field['id'] . ']" value="1" ' . $chk . '> Yes';
            echo '</label>';
        } else {
            echo '<input type="text" name="custom_fields[' . $field['id'] . ']" class="form-control" value="' . e($val) . '" ' . $req . '>';
        }
        
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
}

/**
 * Universal Customizer Helper - Save Custom Fields for Any Entity Section
 */
function save_custom_fields($entity_type, $entity_id, $custom_data) {
    global $pdo;
    if (empty($custom_data) || !is_array($custom_data)) return;
    
    $stmt = $pdo->prepare("
        INSERT INTO custom_field_values (field_id, entity_id, field_value) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)
    ");
    
    foreach ($custom_data as $field_id => $val) {
        $stmt->execute([intval($field_id), intval($entity_id), is_array($val) ? implode(',', $val) : trim((string)$val)]);
    }
}

/**
 * Automated Workflow Trigger Event Dispatcher
 */
function fire_workflow_event($event_type, $data = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM workflow_triggers WHERE event_type = ? AND is_active = 1");
        $stmt->execute([$event_type]);
        $triggers = $stmt->fetchAll();
        
        foreach ($triggers as $trig) {
            $client_id = intval($data['client_id'] ?? 0);
            $project_id = intval($data['project_id'] ?? 0);
            $action = $trig['action_type'];
            $payload = $trig['action_payload'];

            if ($action === 'twilio_call' && $client_id > 0) {
                send_twilio_call($client_id, $payload ?: "Automated trigger notice from Syed Professional Services.");
            } elseif ($action === 'twilio_sms' && $client_id > 0) {
                send_twilio_sms($client_id, $payload ?: "SPS Notice: Trigger alert executed for your account.");
            } elseif ($action === 'create_task' && $client_id > 0) {
                $pdo->prepare("INSERT INTO tasks (client_id, project_id, title, description, status, priority) VALUES (?, ?, ?, ?, 'pending', 'high')")
                    ->execute([$client_id, $project_id ?: null, $trig['title'], $payload]);
            }
        }
    } catch (Exception $e) {
        error_log("Workflow Trigger Error: " . $e->getMessage());
    }
}

/**
 * Module Status Checker
 * Yahan hum check kar rahe hain ke kia yeh module Customizer ke zarye enable hai ya nahi
 */
function is_module_enabled($module_key) {
    global $pdo;
    static $module_cache = null;
    if ($module_cache === null) {
        try {
            $stmt = $pdo->query("SELECT module_key, is_enabled FROM crm_modules");
            $module_cache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            return true; // Default to true if table not yet initialized
        }
    }
    return isset($module_cache[$module_key]) ? (bool)$module_cache[$module_key] : true;
}

/**
 * User Data Summary Helper
 * Yeh helper user ke create kiye hue records (Clients, Tasks, Invoices, Activities) ka count nikalta hai
 */
function get_user_entered_data_summary($user_id) {
    global $pdo;
    $summary = [
        'clients' => 0,
        'tasks_created' => 0,
        'tasks_assigned' => 0,
        'activities' => 0,
        'templates' => 0,
    ];
    
    try {
        // Clients entered by user (assigned or created)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE assigned_staff_id = ?");
        $stmt->execute([$user_id]);
        $summary['clients'] = $stmt->fetchColumn();

        // Tasks assigned to user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
        $stmt->execute([$user_id]);
        $summary['tasks_assigned'] = $stmt->fetchColumn();

        // Activities recorded by user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM activities WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $summary['activities'] = $stmt->fetchColumn();

        // Personal templates created
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_templates WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $summary['templates'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Summary calculation error: " . $e->getMessage());
    }
    
    return $summary;
}

/**
 * Soft Delete & Audit Record Logger
 * Jab koi record delete ho to uska snapshot JSON format me save karte hain taake baad me restore kiya ja sake
 */
function log_deleted_record($table, $record_id, $payload, $module, $description) {
    global $pdo;
    $user_id = $_SESSION['user_id'] ?? null;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activities (user_id, action, module, entity_id, description, stage, is_deleted_record, restore_payload, is_restored)
            VALUES (?, 'Delete Record', ?, ?, ?, 'stage2_sensitive', 1, ?, 0)
        ");
        $stmt->execute([$user_id, $module, $record_id, $description, json_encode($payload)]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Delete record logging error: " . $e->getMessage());
        return false;
    }
}

/**
 * Common Navigation Header Renderer — Next-Gen Sidebar Workspace Layout
 */
function render_header($page_title = 'Dashboard', $active_module = 'dashboard') {
    global $pdo;
    require_once __DIR__ . '/theme_helper.php';
    $user = current_user();
    $themeData = get_user_theme();
    $activeTheme = $themeData['theme'];
    $activeMode  = $themeData['mode'];

    // Base URL for asset paths
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
              . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
              . '/';

    // Notifications & Urgent Tasks
    $unread_count = 0; $recent_notifications = []; $urgent_tasks_count = 0; $urgent_tasks = [];
    if ($user) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
            $stmt->execute([$user['id']]); $unread_count = $stmt->fetchColumn();
            $stmtNotif = $pdo->prepare("SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) ORDER BY created_at DESC LIMIT 8");
            $stmtNotif->execute([$user['id']]); $recent_notifications = $stmtNotif->fetchAll();
            $stmtUrgent = $pdo->prepare("SELECT * FROM tasks WHERE (assigned_to = ? OR assigned_to IS NULL) AND priority = 'high' AND status != 'completed' ORDER BY due_date ASC LIMIT 5");
            $stmtUrgent->execute([$user['id']]); $urgent_tasks = $stmtUrgent->fetchAll(); $urgent_tasks_count = count($urgent_tasks);
        } catch (Exception $e) { error_log("Header data fetch error: " . $e->getMessage()); }
    }

    // User initials for avatar
    $user_initial = strtoupper(substr($user['full_name'] ?? 'U', 0, 1));
    ?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($activeTheme) ?>" data-mode="<?= e($activeMode) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <!-- Design System + Component Library -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/gravity-ui.css">

    <!-- Active Theme -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/themes/theme-<?= e($activeTheme) ?>.css">

    <!-- Legacy compatibility: map old CSS vars to new ones -->
    <style>
    :root {
        /* Bridge old variable names so existing pages still render */
        --primary:      var(--color-primary);
        --primary-hover:var(--color-primary-hover);
        --secondary:    var(--text-primary);
        --accent:       var(--color-accent);
        --success:      var(--color-success);
        --warning:      var(--color-warning);
        --danger:       var(--color-danger);
        --info:         var(--color-info);
        --bg-main:      var(--color-bg);
        --bg-card:      var(--color-surface);
        --text-dark:    var(--text-primary);
        --shadow-sm:    var(--shadow-sm);
        --shadow-md:    var(--shadow-md);
        --shadow-lg:    var(--shadow-lg);
    }
    </style>

    <!-- Page-level JS config -->
    <script>
    window.GRAVITY_USER_ID  = <?= intval($user['id'] ?? 0) ?>;
    window.GRAVITY_CSRF     = '<?= csrf_token() ?>';
    window.GRAVITY_BASE_URL = '<?= $base_url ?>';
    </script>
</head>
<body>

<div class="crm-shell" id="crmShell">

<!-- ═══════════════════════════ SIDEBAR ═══════════════════════════ -->
<aside class="crm-sidebar" id="crmSidebar">

    <!-- Brand Logo -->
    <a href="dashboard.php" class="sidebar-brand" title="Gravity CRM — Home">
        <div class="sidebar-brand-icon">G</div>
        <div class="sidebar-brand-text">
            <span class="sidebar-brand-name">GRAVITY CRM</span>
            <span class="sidebar-brand-tagline">Professional Services</span>
        </div>
    </a>

    <!-- Sidebar Toggle Button -->
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Sidebar">❮</button>

    <!-- Navigation -->
    <nav class="sidebar-nav">

        <!-- WORKSPACE -->
        <div class="nav-section-label">Workspace</div>

        <div class="nav-item <?= $active_module === 'dashboard' ? 'active' : '' ?>">
            <a href="dashboard.php" class="nav-link" title="Dashboard">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">Dashboard</span>
            </a>
        </div>

        <!-- CLIENTS -->
        <?php if (is_module_enabled('clients')): ?>
        <div class="nav-section-label">Clients</div>

        <div class="nav-item <?= in_array($active_module, ['clients','businesses','contacts','leads']) ? 'active' : '' ?> nav-item" data-nav-id="clients">
            <a href="#" class="nav-link nav-group-toggle" title="Clients & CRM">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Clients & CRM</span>
                <span class="nav-chevron">▶</span>
            </a>
            <div class="nav-submenu">
                <div class="nav-item <?= $active_module==='clients' ? 'active' : '' ?>">
                    <a href="clients.php" class="nav-link">All Clients</a>
                </div>
                <div class="nav-item">
                    <a href="add_client.php" class="nav-link">➕ Add Client</a>
                </div>
                <div class="nav-item <?= $active_module==='leads' ? 'active' : '' ?>">
                    <a href="leads.php" class="nav-link">🌟 Leads & Acquisition</a>
                </div>
                <div class="nav-item <?= $active_module==='businesses' ? 'active' : '' ?>">
                    <a href="businesses.php" class="nav-link">🏢 Businesses</a>
                </div>
                <div class="nav-item <?= $active_module==='contacts' ? 'active' : '' ?>">
                    <a href="contacts.php" class="nav-link">📇 Contacts</a>
                </div>
                <div class="nav-item">
                    <a href="relationship_tree.php" class="nav-link">🌳 Relationship Tree</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SERVICES -->
        <?php if (is_module_enabled('services')): ?>
        <div class="nav-section-label">Services</div>

        <div class="nav-item <?= in_array($active_module,['services','bank_statements']) ? 'active' : '' ?>" data-nav-id="services">
            <a href="#" class="nav-link nav-group-toggle" title="Services">
                <span class="nav-icon">⚙️</span>
                <span class="nav-label">Services</span>
                <span class="nav-chevron">▶</span>
            </a>
            <div class="nav-submenu">
                <div class="nav-item <?= $active_module==='services' ? 'active' : '' ?>">
                    <a href="services.php" class="nav-link">Service Catalog</a>
                </div>
                <div class="nav-item <?= $active_module==='bank_statements' ? 'active' : '' ?>">
                    <a href="bank_statements.php" class="nav-link">🏦 Bank Statements</a>
                </div>
                <div class="nav-item">
                    <a href="service_workflow.php" class="nav-link">Service Workflows</a>
                </div>
                <div class="nav-item">
                    <a href="service_requirements.php" class="nav-link">Requirements</a>
                </div>
                <div class="nav-item">
                    <a href="service_automation.php" class="nav-link">Automation Rules</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- OPERATIONS -->
        <?php if (is_module_enabled('projects') || is_module_enabled('tasks')): ?>
        <div class="nav-section-label">Operations</div>

        <div class="nav-item <?= in_array($active_module,['projects','tasks','calendar','reminders']) ? 'active' : '' ?>" data-nav-id="operations">
            <a href="#" class="nav-link nav-group-toggle" title="Operations">
                <span class="nav-icon">📋</span>
                <span class="nav-label">Projects & Tasks</span>
                <span class="nav-chevron">▶</span>
            </a>
            <div class="nav-submenu">
                <div class="nav-item <?= $active_module==='projects' ? 'active' : '' ?>">
                    <a href="projects.php" class="nav-link">Projects</a>
                </div>
                <div class="nav-item">
                    <a href="project_pipeline.php" class="nav-link">📊 Pipeline</a>
                </div>
                <div class="nav-item">
                    <a href="recurring_projects.php" class="nav-link">🔁 Recurring</a>
                </div>
                <div class="nav-item <?= $active_module==='tasks' ? 'active' : '' ?>">
                    <a href="tasks.php" class="nav-link">✅ Tasks</a>
                </div>
                <div class="nav-item <?= $active_module==='calendar' ? 'active' : '' ?>">
                    <a href="calendar.php" class="nav-link">📅 Calendar</a>
                </div>
                <div class="nav-item <?= $active_module==='reminders' ? 'active' : '' ?>">
                    <a href="reminders.php" class="nav-link">🔔 Reminders</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- FINANCE -->
        <?php if (is_module_enabled('invoices')): ?>
        <div class="nav-section-label">Finance</div>

        <div class="nav-item <?= in_array($active_module,['invoices','quotations','payments']) ? 'active' : '' ?>" data-nav-id="finance">
            <a href="#" class="nav-link nav-group-toggle" title="Finance">
                <span class="nav-icon">💰</span>
                <span class="nav-label">Finance</span>
                <span class="nav-chevron">▶</span>
            </a>
            <div class="nav-submenu">
                <div class="nav-item <?= $active_module==='quotations' ? 'active' : '' ?>">
                    <a href="quotations.php" class="nav-link">Quotations</a>
                </div>
                <div class="nav-item <?= $active_module==='invoices' ? 'active' : '' ?>">
                    <a href="invoices.php" class="nav-link">📄 Invoices</a>
                </div>
                <div class="nav-item <?= $active_module==='payments' ? 'active' : '' ?>">
                    <a href="payments.php" class="nav-link">💳 Payments</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- DOCUMENTS -->
        <?php if (is_module_enabled('documents')): ?>
        <div class="nav-section-label">Documents</div>

        <div class="nav-item <?= in_array($active_module,['documents','secure_area']) ? 'active' : '' ?>" data-nav-id="docs">
            <a href="#" class="nav-link nav-group-toggle" title="Documents">
                <span class="nav-icon">📁</span>
                <span class="nav-label">Documents</span>
                <span class="nav-chevron">▶</span>
            </a>
            <div class="nav-submenu">
                <div class="nav-item <?= $active_module==='documents' ? 'active' : '' ?>">
                    <a href="documents.php" class="nav-link">Repository</a>
                </div>
                <div class="nav-item <?= $active_module==='secure_area' ? 'active' : '' ?>">
                    <a href="secure_area.php" class="nav-link">🔒 Secure Vault</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- COMMUNICATIONS -->
        <?php if (is_module_enabled('communications')): ?>
        <div class="nav-section-label">Comms</div>

        <div class="nav-item <?= in_array($active_module,['communications','email','notifications']) ? 'active' : '' ?>" data-nav-id="comms">
            <a href="#" class="nav-link nav-group-toggle" title="Communications">
                <span class="nav-icon">💬</span>
                <span class="nav-label">Communications</span>
                <span class="nav-chevron">▶</span>
            </a>
            <div class="nav-submenu">
                <div class="nav-item <?= $active_module==='communications' ? 'active' : '' ?>">
                    <a href="communications.php" class="nav-link">Communication Log</a>
                </div>
                <div class="nav-item <?= $active_module==='email' ? 'active' : '' ?>">
                    <a href="email.php" class="nav-link">📧 Send Email</a>
                </div>
                <div class="nav-item <?= $active_module==='notifications' ? 'active' : '' ?>">
                    <a href="notifications.php" class="nav-link">🔔 Notifications</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- AI -->
        <div class="nav-section-label">AI Tools</div>
        <div class="nav-item">
            <button type="button" onclick="openVoiceModal()" class="nav-link" style="width:100%;text-align:left;border:none;cursor:pointer;background:none;">
                <span class="nav-icon">🎙️</span>
                <span class="nav-label">AI Voice Assistant</span>
            </button>
        </div>
        <div class="nav-item">
            <a href="ai_assistant.php" class="nav-link">
                <span class="nav-icon">🤖</span>
                <span class="nav-label">AI Assistant</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="dialer.php" class="nav-link">
                <span class="nav-icon">📞</span>
                <span class="nav-label">Smart Dialer</span>
            </a>
        </div>

        <!-- ADMIN -->
        <?php if (user_can('admin.settings') || user_can('admin.customizer')): ?>
        <div class="nav-section-label">Admin</div>
        <div class="nav-item <?= in_array($active_module,['admin','users','customizer','settings','roles']) ? 'active' : '' ?>" data-nav-id="admin">
            <a href="#" class="nav-link nav-group-toggle" title="Administration">
                <span class="nav-icon">🔧</span>
                <span class="nav-label">Administration</span>
                <span class="nav-chevron">▶</span>
            </a>
            <div class="nav-submenu">
                <div class="nav-item <?= $active_module==='users' ? 'active' : '' ?>">
                    <a href="users.php" class="nav-link">👥 Users & Staff</a>
                </div>
                <div class="nav-item <?= $active_module==='roles' ? 'active' : '' ?>">
                    <a href="roles.php" class="nav-link">🔑 Roles & Permissions</a>
                </div>
                <div class="nav-item <?= $active_module==='customizer' ? 'active' : '' ?>">
                    <a href="customizer.php" class="nav-link">🎛️ CRM Customizer</a>
                </div>
                <div class="nav-item">
                    <a href="workflow_triggers.php" class="nav-link">⚡ Workflow Triggers</a>
                </div>
                <div class="nav-item <?= $active_module==='settings' ? 'active' : '' ?>">
                    <a href="settings.php" class="nav-link">⚙️ System Settings</a>
                </div>
                <div class="nav-item">
                    <a href="activity_logs.php" class="nav-link">📜 Audit Trail</a>
                </div>
                <div class="nav-item">
                    <a href="error_logs.php" class="nav-link">🐞 Error Logs</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </nav><!-- /sidebar-nav -->

    <!-- Sidebar Footer: User Info -->
    <div class="sidebar-footer">
        <div class="sidebar-footer-user" data-popover="sidebarUserMenu">
            <div class="sidebar-user-avatar">
                <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
                    <img src="<?= e($user['avatar']) ?>" alt="Avatar">
                <?php else: ?>
                    <?= $user_initial ?>
                <?php endif; ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= e($user['full_name'] ?? 'User') ?></div>
                <div class="sidebar-user-role"><?= e($user['role_name'] ?? 'Staff') ?></div>
            </div>
        </div>

        <!-- Sidebar User Quick Menu -->
        <div class="popover user-dropdown-menu" id="sidebarUserMenu">
            <div class="user-dropdown-info">
                <div class="user-dropdown-name"><?= e($user['full_name'] ?? 'User') ?></div>
                <div class="user-dropdown-role"><?= e($user['role_name'] ?? 'Staff') ?></div>
            </div>
            <a href="profile.php" class="dropdown-item">👤 User Profile</a>
            <a href="personal_templates.php" class="dropdown-item">📄 Personal Templates</a>
            <a href="tasks.php?filter=my_tasks" class="dropdown-item">✅ My Tasks & Work</a>
            <a href="activity_logs.php" class="dropdown-item">📜 Activity & Audit Logs</a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" class="dropdown-item danger">🚪 Sign Out</a>
        </div>
    </div>

</aside><!-- /crm-sidebar -->

<!-- ═══════════════════════════ TOP COMMAND BAR ═══════════════════════════ -->
<header class="crm-topbar">

    <!-- Mobile Hamburger -->
    <button class="topbar-icon-btn" id="mobileMenuBtn" title="Open Menu" style="display:none;">☰</button>

    <!-- Global Search -->
    <form action="global_search.php" method="GET" class="topbar-search" id="globalSearchForm">
        <span class="topbar-search-icon">🔍</span>
        <input type="text" name="q" placeholder="Search clients, invoices, tasks..." id="globalSearchInput" autocomplete="off">
        <input type="hidden" name="module" value="all">
        <span class="topbar-search-kbd" title="Press Ctrl+K for command palette">⌘K</span>
    </form>

    <!-- Topbar Actions -->
    <div class="topbar-actions">

        <!-- AI Voice -->
        <button type="button" onclick="openVoiceModal()" class="topbar-btn ai-voice" title="Launch AI Voice Assistant">
            🎙️ <span>AI Voice</span>
        </button>

        <!-- AI Quick Add -->
        <button type="button" onclick="openQuickAddModal()" class="topbar-btn ai-quick" title="AI Quick Add">
            ⚡ <span>Quick Add</span>
        </button>

        <div class="topbar-sep"></div>

        <!-- Urgent Items -->
        <div style="position:relative;">
            <button type="button" class="topbar-icon-btn" data-popover="urgentDropdown" title="Urgent Action Items">
                🔥
                <?php if ($urgent_tasks_count > 0): ?>
                    <span class="topbar-badge"><?= $urgent_tasks_count ?></span>
                <?php endif; ?>
            </button>
            <div class="popover" id="urgentDropdown" style="min-width:320px;">
                <div class="popover-header">
                    <span class="popover-header-title">🔥 Urgent / High-Priority</span>
                    <a href="tasks.php?priority=high" class="popover-header-link">View All</a>
                </div>
                <?php if (empty($urgent_tasks)): ?>
                    <div class="popover-empty">No urgent items — you're clear! ✅</div>
                <?php else: ?>
                    <?php foreach ($urgent_tasks as $ut): ?>
                    <div class="popover-item">
                        <div style="font-weight:600;font-size:13px;color:var(--text-primary);"><?= e($ut['title']) ?></div>
                        <div style="font-size:11px;color:var(--color-danger);margin-top:2px;">Due: <?= format_date($ut['due_date']) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notification Bell -->
        <div style="position:relative;">
            <button class="topbar-icon-btn" data-popover="notifDropdown" title="Notifications">
                🔔
                <?php if ($unread_count > 0): ?>
                    <span class="topbar-badge"><?= $unread_count ?></span>
                <?php endif; ?>
            </button>
            <div class="popover" id="notifDropdown" style="min-width:340px;">
                <div class="popover-header">
                    <span class="popover-header-title">Notifications</span>
                    <a href="notifications.php" class="popover-header-link">View All</a>
                </div>
                <?php if (empty($recent_notifications)): ?>
                    <div class="popover-empty">No new notifications</div>
                <?php else: ?>
                    <?php foreach ($recent_notifications as $notif): ?>
                    <div class="popover-item">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                            <strong style="font-size:13px;color:var(--text-primary);"><?= e($notif['title']) ?></strong>
                            <span style="font-size:11px;color:var(--text-muted);white-space:nowrap;"><?= date('H:i', strtotime($notif['created_at'])) ?></span>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= e($notif['message']) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="topbar-sep"></div>

        <!-- User Profile Dropdown -->
        <div style="position:relative;">
            <div class="topbar-user-pill" data-popover="topbarUserMenu">
                <div class="topbar-user-avatar">
                    <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
                        <img src="<?= e($user['avatar']) ?>" alt="Avatar">
                    <?php else: ?>
                        <?= $user_initial ?>
                    <?php endif; ?>
                </div>
                <span class="topbar-user-name"><?= e($user['full_name'] ?? 'User') ?></span>
                <span class="topbar-user-chevron">▾</span>
            </div>

            <div class="popover user-dropdown-menu" id="topbarUserMenu">
                <div class="user-dropdown-info">
                    <div class="user-dropdown-name"><?= e($user['full_name'] ?? 'User') ?></div>
                    <div class="user-dropdown-role"><?= e($user['role_name'] ?? 'Staff') ?></div>
                </div>
                <!-- 1. User Profile -->
                <a href="profile.php" class="dropdown-item">👤 User Profile</a>
                <!-- 2. Personal Templates -->
                <a href="personal_templates.php" class="dropdown-item">📄 Personal Templates</a>
                <!-- 3. My Tasks & Work -->
                <a href="tasks.php?filter=my_tasks" class="dropdown-item">✅ My Tasks &amp; Work</a>
                <!-- 4. Activity & Audit Logs -->
                <a href="activity_logs.php" class="dropdown-item">📜 Activity &amp; Audit Logs</a>
                <div class="dropdown-divider"></div>

                <!-- Theme Switcher -->
                <div style="padding:8px 16px;border-bottom:1px solid var(--border-color);">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-muted);margin-bottom:6px;">Workspace Theme</div>
                    <div class="theme-grid">
                        <div class="theme-swatch <?= $activeTheme==='professional'?'active':'' ?>" data-theme="professional" title="Professional Core" style="background:linear-gradient(135deg,#0f172a 50%,#2563eb 50%);"></div>
                        <div class="theme-swatch <?= $activeTheme==='aurora'?'active':'' ?>" data-theme="aurora" title="Aurora Modern" style="background:linear-gradient(135deg,#1e1b4b 50%,#7c3aed 50%);"></div>
                        <div class="theme-swatch <?= $activeTheme==='executive'?'active':'' ?>" data-theme="executive" title="SPS Executive" style="background:linear-gradient(135deg,#0f1e3d 50%,#b45309 50%);"></div>
                        <div class="theme-swatch <?= $activeTheme==='studio'?'active':'' ?>" data-theme="studio" title="Studio Custom" style="background:linear-gradient(135deg,#1c1917 50%,#d97706 50%);"></div>
                        <div class="theme-swatch <?= $activeTheme==='focus'?'active':'' ?>" data-theme="focus" title="Eye Comfort" style="background:linear-gradient(135deg,#134e4a 50%,#0d9488 50%);"></div>
                    </div>
                </div>

                <!-- Dark Mode Toggle -->
                <div class="dark-mode-toggle">
                    <span>🌙 Dark Mode</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="darkModeToggle" <?= $activeMode==='dark'?'checked':'' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item danger">🚪 Sign Out</a>
            </div>
        </div>

    </div><!-- /topbar-actions -->

</header><!-- /crm-topbar -->

<!-- ═══════════════════════════ COMMAND PALETTE (Ctrl+K) ═══════════════════════════ -->
<div class="cmd-palette-overlay" id="cmdPaletteOverlay">
    <div class="cmd-palette">
        <div class="cmd-input-wrap">
            <span class="cmd-input-icon">🔍</span>
            <input type="text" class="cmd-input" placeholder="Search or type a command..." autocomplete="off">
        </div>
        <div class="cmd-results"></div>
        <div class="cmd-footer">
            <span class="cmd-hint"><span class="cmd-kbd">↑↓</span> navigate</span>
            <span class="cmd-hint"><span class="cmd-kbd">↵</span> open</span>
            <span class="cmd-hint"><span class="cmd-kbd">Esc</span> close</span>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ AI VOICE MODAL ═══════════════════════════ -->
<div class="modal-overlay" id="aiVoiceModal">
    <div class="modal-card modal-sm">
        <div class="modal-header">
            <span class="modal-title">🎙️ Gravity AI Voice Assistant</span>
            <button onclick="closeVoiceModal()" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:24px 20px;">

            <!-- Mic Button -->
            <button id="micBtn" type="button" onclick="gravityAI.startListening()" style="
                width:88px;height:88px;border-radius:50%;
                background:linear-gradient(135deg,#4f46e5,#06b6d4);
                border:none;cursor:pointer;
                display:flex;align-items:center;justify-content:center;
                font-size:38px;color:#fff;
                box-shadow:0 0 28px rgba(79,70,229,0.45);
                margin:0 auto 18px;
                transition:transform 0.2s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.2s ease;
            ">🎙️</button>

            <p id="speechStatus" style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">
                Click the mic or type a command below
            </p>

            <!-- Text Fallback Input -->
            <div style="display:flex;gap:8px;margin-bottom:14px;">
                <input id="aiTextInput" type="text" class="form-control"
                    placeholder="e.g. Show missing statements, revenue, or call client..."
                    style="flex:1;"
                    onkeydown="if(event.key==='Enter'){gravityAI.sendText(this.value);}" />
                <button type="button" class="btn btn-primary btn-sm"
                    onclick="gravityAI.sendText(document.getElementById('aiTextInput').value)">
                    Send
                </button>
            </div>

            <!-- AI Response Output -->
            <div id="speechText" style="
                background:var(--color-surface-sunken);
                border:1px solid var(--border-color);
                border-radius:8px;padding:12px;
                font-size:13px;color:var(--text-secondary);
                min-height:60px;text-align:left;
                line-height:1.6;
            ">Response will appear here…</div>
        </div>
        <div class="modal-footer" style="justify-content:space-between;align-items:center;">
            <span style="font-size:11px;color:var(--text-muted);">Gravity AI • Voice + Text Commands</span>
            <button type="button" onclick="closeVoiceModal()" class="btn btn-secondary btn-sm">Close</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ AI QUICK ADD MODAL ═══════════════════════════ -->
<div class="modal-overlay" id="quickAddModal">
    <div class="modal-card">
        <div class="modal-header">
            <span class="modal-title">⚡ AI Quick Add &amp; Command</span>
            <button onclick="closeQuickAddModal()" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:15px;">Type instructions in natural language to create records or query CRM intelligence:</p>
            <div class="form-group">
                <textarea id="quickAddInput" class="form-control" rows="3"
                    placeholder="e.g. Show outstanding receivables, call John Smith for tax documents, count missing statements…"
                    onkeydown="if((event.ctrlKey||event.metaKey)&&event.key==='Enter'){executeQuickAdd();}"></textarea>
            </div>
            <div id="quickAddResult" style="
                display:none;margin-top:12px;padding:12px;
                background:var(--color-surface-sunken);
                border:1px solid var(--border-color);
                border-radius:8px;font-size:13px;color:var(--text-secondary);
                line-height:1.6;
            "></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
                <a href="add_client.php" class="btn btn-sm btn-secondary">+ Client</a>
                <a href="tasks.php?action=new" class="btn btn-sm btn-secondary">+ Task</a>
                <a href="invoice_create.php" class="btn btn-sm btn-secondary">+ Invoice</a>
                <a href="personal_templates.php?action=new" class="btn btn-sm btn-secondary">+ Template</a>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeQuickAddModal()" class="btn btn-secondary">Cancel</button>
            <button type="button" id="quickAddBtn" onclick="executeQuickAdd()" class="btn btn-primary">Process with AI</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ MAIN CONTENT ═══════════════════════════ -->
<main class="crm-content">
    <div class="page-container">
    <?php display_flash_messages(); ?>
<?php
}

function render_footer() {
    global $pdo;
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
              . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
              . '/';
    ?>
    </div><!-- /page-container -->
</main><!-- /crm-content -->

</div><!-- /crm-shell -->

<!-- Global Application Script -->
<script src="<?= $base_url ?>assets/js/gravity-app.js"></script>

<footer style="text-align:center; padding:20px; color:var(--text-muted); font-size:12px; margin-top:40px; border-top:1px solid var(--border-color);">
    &copy; <?= date('Y') ?> Gravity CRM — AI Professional Services Platform. Running on Windows XAMPP.
</footer>

<?php
// =========================================================
// TAWK.TO LIVE CHAT WIDGET AUTO-INJECT
// =========================================================
$tawkto_enabled     = get_setting('tawkto_enabled', '0');
$tawkto_property_id = get_setting('tawkto_property_id', '');
$tawkto_widget_id   = get_setting('tawkto_widget_id', 'default');

if ($tawkto_enabled === '1' && !empty($tawkto_property_id)):
    $tw_name  = htmlspecialchars($_SESSION['user_name'] ?? 'Staff User', ENT_QUOTES);
    $tw_email = htmlspecialchars($_SESSION['user_email'] ?? '', ENT_QUOTES);
?>
<!--Tawk.to Live Chat Widget — Property: <?= e($tawkto_property_id) ?>-->
<script type="text/javascript">
var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
(function(){
    var s1 = document.createElement("script"), s0 = document.getElementsByTagName("script")[0];
    s1.async = true;
    s1.src = 'https://embed.tawk.to/<?= e($tawkto_property_id) ?>/<?= e($tawkto_widget_id) ?>';
    s1.charset = 'UTF-8';
    s1.setAttribute('crossorigin', '*');
    s0.parentNode.insertBefore(s1, s0);
})();

Tawk_API.onLoad = function() {
    Tawk_API.setAttributes({
        'name'  : '<?= $tw_name ?>',
        'email' : '<?= $tw_email ?>',
        'crm_role': '<?= htmlspecialchars($_SESSION['user_role_name'] ?? 'Staff', ENT_QUOTES) ?>'
    }, function(error) {
        if (error) { console.warn('Tawk.to attribute set error:', error); }
    });
};
</script>
<?php endif; ?>

</body>
</html>
    <?php
}

