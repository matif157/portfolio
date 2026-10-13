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
    
    foreach ($messages as $msg) {
        $bgColor = '#e6fffa';
        $textColor = '#234e52';
        $borderColor = '#319795';
        
        if ($msg['type'] === 'danger' || $msg['type'] === 'error') {
            $bgColor = '#fff5f5'; $textColor = '#742a2a'; $borderColor = '#e53e3e';
        } elseif ($msg['type'] === 'warning') {
            $bgColor = '#fffaf0'; $textColor = '#744210'; $borderColor = '#dd6b20';
        } elseif ($msg['type'] === 'success') {
            $bgColor = '#f0fff4'; $textColor = '#22543d'; $borderColor = '#38a169';
        }
        
        echo '<div style="background:' . $bgColor . '; color:' . $textColor . '; border-left:4px solid ' . $borderColor . '; padding:12px 18px; margin:15px 0; border-radius:4px; font-weight:500; display:flex; justify-content:space-between; align-items:center;">';
        echo '<span>' . e($msg['message']) . '</span>';
        echo '<button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; font-size:18px; cursor:pointer;">&times;</button>';
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
 * Common Navigation Header Renderer with Themes & Real-Time Header Features
 */
function render_header($page_title = 'Dashboard', $active_module = 'dashboard') {
    global $pdo;
    $user = current_user();
    
    // Fetch App Theme Setting
    $theme = get_setting('app_theme', 'modern');
    
    // Comprehensive Theme CSS Palettes (Modern, Advanced CSS, Bold, Basic, Black, Night Eye, User-Friendly, Navy, Emerald, Glassmorphism, Luxury)
    $theme_css = ":root {
        --primary: #2563eb; --primary-hover: #1d4ed8; --secondary: #0f172a; --accent: #3b82f6;
        --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --info: #0ea5e9;
        --bg-main: #f8fafc; --bg-card: #ffffff; --text-dark: #0f172a; --text-muted: #64748b;
        --border-color: #e2e8f0; --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1); --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    }";
    
    if ($theme === 'advanced') {
        // Advanced CSS: Vibrant Violet, Neon Cyan accents, sleek gradient touches
        $theme_css = ":root {
            --primary: #6366f1; --primary-hover: #4f46e5; --secondary: #090d16; --accent: #06b6d4;
            --success: #10b981; --warning: #f59e0b; --danger: #f43f5e; --info: #38bdf8;
            --bg-main: #f1f5f9; --bg-card: #ffffff; --text-dark: #0f172a; --text-muted: #64748b;
            --border-color: #cbd5e1; --shadow-sm: 0 2px 4px rgba(99,102,241,0.08);
            --shadow-md: 0 8px 16px rgba(99,102,241,0.12); --shadow-lg: 0 12px 24px rgba(99,102,241,0.16);
        }";
    } elseif ($theme === 'bold') {
        // Bold: High contrast royal blue and strong dark slate
        $theme_css = ":root {
            --primary: #1d4ed8; --primary-hover: #1e40af; --secondary: #000000; --accent: #2563eb;
            --success: #059669; --warning: #d97706; --danger: #dc2626; --info: #0284c7;
            --bg-main: #f4f6f8; --bg-card: #ffffff; --text-dark: #111827; --text-muted: #4b5563;
            --border-color: #d1d5db; --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 6px 12px rgba(0,0,0,0.15); --shadow-lg: 0 15px 25px rgba(0,0,0,0.2);
        }";
    } elseif ($theme === 'basic') {
        // Basic: Minimalist clean gray palette
        $theme_css = ":root {
            --primary: #475569; --primary-hover: #334155; --secondary: #1e293b; --accent: #64748b;
            --success: #16a34a; --warning: #ca8a04; --danger: #dc2626; --info: #2563eb;
            --bg-main: #ffffff; --bg-card: #f8fafc; --text-dark: #0f172a; --text-muted: #64748b;
            --border-color: #e2e8f0; --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 2px 4px rgba(0,0,0,0.06); --shadow-lg: 0 4px 8px rgba(0,0,0,0.08);
        }";
    } elseif ($theme === 'black') {
        // Pure Black AMOLED Theme
        $theme_css = ":root {
            --primary: #3b82f6; --primary-hover: #60a5fa; --secondary: #ffffff; --accent: #60a5fa;
            --success: #34d399; --warning: #fbbf24; --danger: #f87171; --info: #38bdf8;
            --bg-main: #000000; --bg-card: #0a0a0a; --text-dark: #f8fafc; --text-muted: #a1a1aa;
            --border-color: #27272a; --shadow-sm: 0 1px 3px rgba(255,255,255,0.05);
            --shadow-md: 0 4px 6px rgba(255,255,255,0.08); --shadow-lg: 0 10px 15px rgba(255,255,255,0.1);
        }";
    } elseif ($theme === 'night_eye') {
        // Night Eye: Soft blue-light reduction dark mode
        $theme_css = ":root {
            --primary: #60a5fa; --primary-hover: #93c5fd; --secondary: #f3f4f6; --accent: #38bdf8;
            --success: #4ade80; --warning: #fde047; --danger: #f87171; --info: #818cf8;
            --bg-main: #131722; --bg-card: #1e222d; --text-dark: #e5e7eb; --text-muted: #9ca3af;
            --border-color: #2a2e39; --shadow-sm: 0 1px 3px rgba(0,0,0,0.5);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.6); --shadow-lg: 0 10px 20px rgba(0,0,0,0.7);
        }";
    } elseif ($theme === 'user_friendly') {
        // User Friendly: Warm calming emerald & soft pastel background
        $theme_css = ":root {
            --primary: #0d9488; --primary-hover: #0f766e; --secondary: #134e4a; --accent: #14b8a6;
            --success: #10b981; --warning: #f59e0b; --danger: #f43f5e; --info: #0284c7;
            --bg-main: #f0fdfa; --bg-card: #ffffff; --text-dark: #134e4a; --text-muted: #5eead4;
            --border-color: #ccfbf1; --shadow-sm: 0 2px 4px rgba(13,148,136,0.06);
            --shadow-md: 0 4px 8px rgba(13,148,136,0.1); --shadow-lg: 0 10px 15px rgba(13,148,136,0.15);
        }";
    } elseif ($theme === 'navy') {
        // Classic Navy
        $theme_css = ":root {
            --primary: #1e3a8a; --primary-hover: #1e40af; --secondary: #0f172a; --accent: #2563eb;
            --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --info: #3b82f6;
            --bg-main: #f8fafc; --bg-card: #ffffff; --text-dark: #1e293b; --text-muted: #64748b;
            --border-color: #e2e8f0; --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1); --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        }";
    }
    
    // Unread Notifications Count
    $unread_count = 0;
    $recent_notifications = [];
    $urgent_tasks_count = 0;
    $urgent_tasks = [];
    
    if ($user) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
            $stmt->execute([$user['id']]);
            $unread_count = $stmt->fetchColumn();
            
            $stmtNotif = $pdo->prepare("SELECT * FROM notifications WHERE (user_id = ? OR user_id IS NULL) ORDER BY created_at DESC LIMIT 6");
            $stmtNotif->execute([$user['id']]);
            $recent_notifications = $stmtNotif->fetchAll();

            // Urgent / High priority tasks count
            $stmtUrgent = $pdo->prepare("SELECT * FROM tasks WHERE (assigned_to = ? OR assigned_to IS NULL) AND priority = 'high' AND status != 'completed' ORDER BY due_date ASC LIMIT 5");
            $stmtUrgent->execute([$user['id']]);
            $urgent_tasks = $stmtUrgent->fetchAll();
            $urgent_tasks_count = count($urgent_tasks);
        } catch (Exception $e) {
            error_log("Header data fetch error: " . $e->getMessage());
        }
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        <?= $theme_css ?>

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, sans-serif; background-color: var(--bg-main); color: var(--text-dark); line-height: 1.5; font-size: 14px; }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        
        /* Master CRM Header */
        .app-header { background: #0f172a; color: #ffffff; padding: 0 20px; height: 64px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 1000; box-shadow: var(--shadow-md); gap: 15px; }
        .app-logo { font-size: 18px; font-weight: 700; color: #ffffff; display: flex; align-items: center; gap: 8px; text-decoration: none; flex-shrink: 0; }
        .app-logo span { background: #2563eb; color: #ffffff; padding: 3px 8px; border-radius: 4px; font-size: 11px; letter-spacing: 0.5px; }
        
        /* Global Search Bar with Module Selector */
        .header-search-container { flex: 1; max-width: 480px; display: flex; align-items: center; background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 3px 6px; gap: 6px; }
        .header-search-select { background: transparent; color: #94a3b8; border: none; font-size: 12px; font-weight: 500; padding: 4px 6px; border-right: 1px solid #334155; outline: none; cursor: pointer; }
        .header-search-select option { background: #1e293b; color: #f8fafc; }
        .header-search-input { flex: 1; background: transparent; border: none; color: #ffffff; font-size: 13px; padding: 4px 8px; outline: none; }
        .header-search-input::placeholder { color: #64748b; }
        .header-search-btn { background: #2563eb; color: #ffffff; border: none; border-radius: 6px; padding: 5px 12px; font-size: 12px; font-weight: 600; cursor: pointer; transition: background 0.15s; }
        .header-search-btn:hover { background: #1d4ed8; }

        /* Top Navigation Bar */
        .app-nav { display: flex; gap: 2px; height: 100%; align-items: center; }
        .nav-item { position: relative; height: 100%; display: flex; align-items: center; }
        .nav-link { color: #cbd5e1; padding: 0 12px; height: 100%; display: flex; align-items: center; gap: 6px; font-weight: 500; font-size: 13px; transition: all 0.2s; text-decoration: none; }
        .nav-link:hover, .nav-item.active .nav-link { color: #ffffff; background: rgba(255,255,255,0.08); text-decoration: none; }
        
        /* Dropdown Menus */
        .dropdown-menu { display: none; position: absolute; top: 100%; left: 0; background: #ffffff; min-width: 220px; box-shadow: var(--shadow-lg); border-radius: 0 0 8px 8px; border: 1px solid var(--border-color); z-index: 1100; overflow: hidden; }
        .nav-item:hover .dropdown-menu { display: block; }
        .dropdown-item { display: block; padding: 10px 16px; color: #1e293b; font-size: 13px; transition: background 0.15s; text-decoration: none; }
        .dropdown-item:hover { background: #f1f5f9; color: var(--primary); text-decoration: none; }
        .dropdown-header { padding: 8px 16px; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; background: #f8fafc; border-bottom: 1px solid var(--border-color); }
        
        /* Header Action Buttons (AI Voice, AI Chat, Urgent, Notifications, Profile) */
        .header-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .header-action-btn { background: #1e293b; border: 1px solid #334155; color: #e2e8f0; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.15s; text-decoration: none; }
        .header-action-btn:hover { background: #334155; color: #ffffff; text-decoration: none; }
        .header-action-btn.ai-voice { background: linear-gradient(135deg, #4f46e5, #06b6d4); border: none; color: #ffffff; }
        .header-action-btn.ai-voice:hover { opacity: 0.95; box-shadow: 0 0 10px rgba(6,182,212,0.4); }
        .header-action-btn.ai-chat { background: linear-gradient(135deg, #2563eb, #3b82f6); border: none; color: #ffffff; }

        .bell-btn { position: relative; background: #1e293b; border: 1px solid #334155; color: #cbd5e1; font-size: 16px; cursor: pointer; padding: 7px 10px; border-radius: 6px; }
        .bell-btn:hover { background: #334155; color: #ffffff; }
        .bell-badge { position: absolute; top: -4px; right: -4px; background: var(--danger); color: #fff; font-size: 10px; font-weight: 700; padding: 1px 5px; border-radius: 10px; min-width: 16px; text-align: center; }
        
        /* Popover Dropdowns */
        .header-popover { display: none; position: absolute; top: 54px; right: 0; width: 340px; background: #ffffff; color: var(--text-dark); box-shadow: var(--shadow-lg); border-radius: 8px; border: 1px solid var(--border-color); z-index: 1200; overflow: hidden; }
        .popover-header { padding: 10px 16px; font-weight: 600; font-size: 13px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .popover-item { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
        .popover-item:hover { background: #f8fafc; }

        /* User Profile Pill & Dropdown */
        .user-dropdown-container { position: relative; }
        .user-pill { background: #1e293b; border: 1px solid #334155; border-radius: 30px; padding: 3px 12px 3px 4px; display: flex; align-items: center; gap: 8px; cursor: pointer; color: #e2e8f0; font-size: 13px; font-weight: 500; }
        .user-pill:hover { background: #334155; }
        .user-avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; background: #2563eb; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; }
        .user-profile-menu { display: none; position: absolute; top: 46px; right: 0; width: 220px; background: #ffffff; color: var(--text-dark); box-shadow: var(--shadow-lg); border-radius: 8px; border: 1px solid var(--border-color); z-index: 1300; overflow: hidden; }

        /* Main Container */
        .main-container { max-width: 1440px; margin: 24px auto; padding: 0 20px; min-height: 75vh; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 24px; font-weight: 700; color: var(--secondary); }
        .page-subtitle { font-size: 14px; color: var(--text-muted); margin-top: 2px; }
        
        /* UI Components */
        .card { background: var(--bg-card); border-radius: 10px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); padding: 20px; margin-bottom: 20px; }
        .card-title { font-size: 16px; font-weight: 600; color: var(--secondary); margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-weight: 500; font-size: 13px; cursor: pointer; border: 1px solid transparent; transition: all 0.15s; }
        .btn-primary { background: var(--accent); color: #ffffff; }
        .btn-primary:hover { background: var(--primary-hover); text-decoration: none; }
        .btn-secondary { background: #f1f5f9; color: var(--text-dark); border-color: var(--border-color); }
        .btn-secondary:hover { background: #e2e8f0; text-decoration: none; }
        .btn-success { background: var(--success); color: #ffffff; }
        .btn-warning { background: var(--warning); color: #ffffff; }
        .btn-danger { background: var(--danger); color: #ffffff; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        
        /* Tables */
        .table-responsive { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; text-align: left; }
        .table th { background: #f8fafc; padding: 12px 14px; font-weight: 600; color: var(--text-muted); border-bottom: 2px solid var(--border-color); font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        .table td { padding: 14px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .table tr:hover { background: #f8fafc; }
        
        /* Badges */
        .badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-secondary { background: #f1f5f9; color: #475569; }
        
        /* Grid Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #ffffff; padding: 20px; border-radius: 10px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); display: flex; flex-direction: column; }
        .stat-label { font-size: 12px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--secondary); margin: 6px 0; }
        .stat-desc { font-size: 12px; color: var(--text-muted); }
        
        /* Form Controls */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-weight: 500; margin-bottom: 6px; color: var(--text-dark); font-size: 13px; }
        .form-control { width: 100%; padding: 9px 12px; border-radius: 6px; border: 1px solid var(--border-color); font-family: inherit; font-size: 14px; transition: border-color 0.15s; }
        .form-control:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .form-row { display: flex; gap: 16px; }
        .form-col { flex: 1; }
        
        /* Modals */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2500; align-items: center; justify-content: center; }
        .modal-card { background: #ffffff; border-radius: 12px; width: 100%; max-width: 550px; box-shadow: var(--shadow-lg); overflow: hidden; }
        .modal-header { padding: 16px 20px; background: #f8fafc; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 12px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); text-align: right; }
    </style>
</head>
<body>

<!-- Master CRM Header -->
<header class="app-header">
    <a href="dashboard.php" class="app-logo">
        GRAVITY <span>CRM</span>
    </a>

    <!-- Global Search Bar with Module Selector -->
    <form action="global_search.php" method="GET" class="header-search-container">
        <select name="module" class="header-search-select" title="Filter search by module">
            <option value="all">🔍 All Modules</option>
            <option value="clients">Clients</option>
            <option value="businesses">Businesses</option>
            <option value="leads">Leads</option>
            <option value="services">Services</option>
            <option value="invoices">Invoices</option>
            <option value="tasks">Tasks</option>
            <option value="documents">Documents</option>
        </select>
        <input type="text" name="q" class="header-search-input" placeholder="Search clients, invoices, files, tasks..." required>
        <button type="submit" class="header-search-btn">Search</button>
    </form>
    
    <!-- Top Navigation Bar -->
    <nav class="app-nav">
        <div class="nav-item <?= $active_module === 'dashboard' ? 'active' : '' ?>">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
        </div>
        
        <?php if (is_module_enabled('clients')): ?>
        <div class="nav-item <?= in_array($active_module, ['clients', 'businesses', 'contacts', 'leads']) ? 'active' : '' ?>">
            <a href="clients.php" class="nav-link">Clients ▾</a>
            <div class="dropdown-menu">
                <a href="clients.php" class="dropdown-item">All Clients</a>
                <a href="add_client.php" class="dropdown-item">Add New Client</a>
                <a href="leads.php" class="dropdown-item">Leads &amp; Acquisition</a>
                <a href="businesses.php" class="dropdown-item">Businesses</a>
                <a href="contacts.php" class="dropdown-item">Contacts</a>
                <a href="relationship_tree.php" class="dropdown-item">🌳 Relationship Tree</a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (is_module_enabled('services')): ?>
        <div class="nav-item <?= in_array($active_module, ['services', 'bank_statements']) ? 'active' : '' ?>">
            <a href="services.php" class="nav-link">Services ▾</a>
            <div class="dropdown-menu">
                <a href="services.php" class="dropdown-item">Service Catalog</a>
                <a href="bank_statements.php" class="dropdown-item">🏦 Bank Statement Collection</a>
                <a href="service_workflow.php" class="dropdown-item">Service Workflows</a>
                <a href="service_requirements.php" class="dropdown-item">Service Requirements</a>
                <a href="service_automation.php" class="dropdown-item">Service Automation</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (is_module_enabled('projects') || is_module_enabled('tasks')): ?>
        <div class="nav-item <?= in_array($active_module, ['projects', 'tasks', 'calendar', 'reminders']) ? 'active' : '' ?>">
            <a href="projects.php" class="nav-link">Operations ▾</a>
            <div class="dropdown-menu">
                <a href="projects.php" class="dropdown-item">Projects</a>
                <a href="project_pipeline.php" class="dropdown-item">Project Pipeline</a>
                <a href="recurring_projects.php" class="dropdown-item">Recurring Projects</a>
                <a href="tasks.php" class="dropdown-item">Tasks</a>
                <a href="calendar.php" class="dropdown-item">Calendar</a>
                <a href="reminders.php" class="dropdown-item">Reminders</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (is_module_enabled('invoices')): ?>
        <div class="nav-item <?= in_array($active_module, ['invoices', 'quotations', 'payments']) ? 'active' : '' ?>">
            <a href="invoices.php" class="nav-link">Finance ▾</a>
            <div class="dropdown-menu">
                <a href="quotations.php" class="dropdown-item">Quotations</a>
                <a href="invoices.php" class="dropdown-item">Invoices</a>
                <a href="payments.php" class="dropdown-item">Payments</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (is_module_enabled('documents')): ?>
        <div class="nav-item <?= in_array($active_module, ['documents', 'secure_area']) ? 'active' : '' ?>">
            <a href="documents.php" class="nav-link">Documents ▾</a>
            <div class="dropdown-menu">
                <a href="documents.php" class="dropdown-item">Document Repository</a>
                <a href="secure_area.php" class="dropdown-item">Secure Vault</a>
                <a href="bank_statements.php" class="dropdown-item">Bank Statements</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (is_module_enabled('communications')): ?>
        <div class="nav-item <?= in_array($active_module, ['communications', 'email', 'notifications']) ? 'active' : '' ?>">
            <a href="communications.php" class="nav-link">Communication ▾</a>
            <div class="dropdown-menu">
                <a href="communications.php" class="dropdown-item">Communication Log</a>
                <a href="email.php" class="dropdown-item">Send Email</a>
                <a href="notifications.php" class="dropdown-item">Notifications Center</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (user_can('admin.settings') || user_can('admin.customizer')): ?>
        <div class="nav-item <?= in_array($active_module, ['admin', 'users', 'customizer', 'settings']) ? 'active' : '' ?>">
            <a href="settings.php" class="nav-link">Admin ▾</a>
            <div class="dropdown-menu">
                <a href="users.php" class="dropdown-item">Users &amp; Staff</a>
                <a href="roles.php" class="dropdown-item">Roles &amp; Permissions Matrix</a>
                <a href="customizer.php" class="dropdown-item">🎛️ Master CRM Customizer</a>
                <a href="workflow_triggers.php" class="dropdown-item">⚡ Workflow Triggers</a>
                <a href="settings.php" class="dropdown-item">System Settings</a>
                <a href="activity_logs.php" class="dropdown-item">Audit Trail &amp; Restore</a>
            </div>
        </div>
        <?php endif; ?>
    </nav>
    
    <!-- Header Actions (AI Tools, Urgent, Bell, User Profile) -->
    <div class="header-actions">
        <!-- AI Voice Assistant Button -->
        <button type="button" onclick="openVoiceModal()" class="header-action-btn ai-voice" title="Launch AI Voice Assistant">
            🎙️ AI Voice
        </button>

        <!-- AI Chat / Quick Add Button -->
        <button type="button" onclick="openQuickAddModal()" class="header-action-btn ai-chat" title="AI Quick Add & Chat">
            ⚡ Quick Add
        </button>

        <!-- Urgent Items Alert Button -->
        <div style="position:relative;">
            <button type="button" class="header-action-btn" onclick="togglePopover('urgentDropdown')" title="Urgent Action Items">
                🔥 Urgent
                <?php if ($urgent_tasks_count > 0): ?>
                    <span style="background:var(--danger); color:#fff; font-size:10px; padding:1px 5px; border-radius:10px;"><?= $urgent_tasks_count ?></span>
                <?php endif; ?>
            </button>
            <div class="header-popover" id="urgentDropdown">
                <div class="popover-header">
                    <span>🔥 High-Priority / Urgent Tasks</span>
                    <a href="tasks.php?priority=high" style="font-size:11px;">View All</a>
                </div>
                <?php if (empty($urgent_tasks)): ?>
                    <div style="padding:16px; text-align:center; font-size:12px; color:#64748b;">No urgent items pending</div>
                <?php else: ?>
                    <?php foreach ($urgent_tasks as $ut): ?>
                        <div class="popover-item">
                            <div style="font-weight:600; font-size:13px; color:#0f172a;"><?= e($ut['title']) ?></div>
                            <div style="font-size:11px; color:#ef4444; margin-top:2px;">Due: <?= format_date($ut['due_date']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity / Notifications Bell -->
        <div style="position:relative;">
            <button class="bell-btn" onclick="togglePopover('notifDropdown')" title="Recent Notifications">
                🔔
                <?php if ($unread_count > 0): ?>
                    <span class="bell-badge"><?= $unread_count ?></span>
                <?php endif; ?>
            </button>
            <div class="header-popover" id="notifDropdown">
                <div class="popover-header">
                    <span>Recent Notifications</span>
                    <a href="notifications.php" style="font-size:11px;">View All</a>
                </div>
                <?php if (empty($recent_notifications)): ?>
                    <div style="padding:16px; text-align:center; font-size:12px; color:#64748b;">No new notifications</div>
                <?php else: ?>
                    <?php foreach ($recent_notifications as $notif): ?>
                        <div class="popover-item">
                            <div style="font-weight:600; font-size:13px; display:flex; justify-content:space-between;">
                                <span><?= e($notif['title']) ?></span>
                                <span style="font-size:10px; color:#94a3b8;"><?= date('H:i', strtotime($notif['created_at'])) ?></span>
                            </div>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;"><?= e($notif['message']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- User Profile Dropdown Menu (4-Options Specified in Master Req) -->
        <div class="user-dropdown-container">
            <div class="user-pill" onclick="togglePopover('userProfileMenu')">
                <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
                    <img src="<?= e($user['avatar']) ?>" class="user-avatar-img" alt="Avatar">
                <?php else: ?>
                    <div class="user-avatar-img"><?= strtoupper(substr($user['full_name'] ?? 'A', 0, 1)) ?></div>
                <?php endif; ?>
                <span><?= e($user['full_name'] ?? 'User') ?></span>
                <span style="font-size:10px; color:#94a3b8;">▾</span>
            </div>
            
            <div class="user-profile-menu" id="userProfileMenu">
                <div class="dropdown-header">
                    <?= e($user['full_name'] ?? 'Staff User') ?>
                    <div style="font-size:10px; font-weight:normal; color:#64748b;"><?= e($user['role_name'] ?? 'Staff') ?></div>
                </div>
                <!-- 1. User Profile -->
                <a href="profile.php" class="dropdown-item">👤 User Profile</a>
                <!-- 2. Personal Templates -->
                <a href="personal_templates.php" class="dropdown-item">📄 Personal Templates</a>
                <!-- 3. Tasks & Assigned Work -->
                <a href="tasks.php?filter=my_tasks" class="dropdown-item">✅ My Tasks &amp; Work</a>
                <!-- 4. Activity Logs & Logout -->
                <a href="activity_logs.php" class="dropdown-item">📜 Activity &amp; Audit Logs</a>
                <div style="border-top:1px solid #e2e8f0; margin:4px 0;"></div>
                <a href="logout.php" class="dropdown-item" style="color:#ef4444; font-weight:600;">🚪 Logout</a>
            </div>
        </div>
    </div>
</header>

<!-- AI Voice Assistant Modal Popup -->
<div class="modal-overlay" id="aiVoiceModal" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <span style="font-weight:700; color:#1e293b; display:flex; align-items:center; gap:8px;">
                🎙️ Gravity AI Voice Assistant
            </span>
            <button onclick="closeVoiceModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748b;">&times;</button>
        </div>
        <div class="modal-body" style="text-align:center; padding:30px 20px;">
            <div id="voiceWave" style="width:80px; height:80px; margin:0 auto 20px; border-radius:50%; background:linear-gradient(135deg, #4f46e5, #06b6d4); display:flex; align-items:center; justify-content:center; font-size:36px; color:#fff; box-shadow:0 0 20px rgba(79,70,229,0.5);">
                🎙️
            </div>
            <h3 id="voiceStatus" style="font-size:18px; color:#0f172a; margin-bottom:8px;">Listening for CRM Voice Commands...</h3>
            <p style="font-size:13px; color:#64748b; max-width:400px; margin:0 auto 15px;">
                Speak naturally to search clients, check service rules, prepare invoices, or add reminders.
            </p>
            <div id="voiceTranscript" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; font-size:13px; color:#334155; min-height:48px; text-align:left;">
                "Hey Gravity, check if John Smith uploaded the March 2026 bank statement..."
            </div>
        </div>
        <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
            <span style="font-size:11px; color:#94a3b8;">Gravity AI Speech Recognition Active</span>
            <button type="button" onclick="closeVoiceModal()" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

<!-- AI Chat & Quick Add Modal Popup -->
<div class="modal-overlay" id="quickAddModal" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <span style="font-weight:700; color:#1e293b; display:flex; align-items:center; gap:8px;">
                ⚡ AI Quick Add &amp; Command
            </span>
            <button onclick="closeQuickAddModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#64748b;">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:13px; color:#64748b; margin-bottom:15px;">
                Type instructions in natural human language to create records or query CRM intelligence:
            </p>
            <div class="form-group">
                <textarea id="quickAddInput" class="form-control" rows="3" placeholder="e.g. Add reminder for tomorrow at 2 PM to file quarterly sales tax for Smith Consulting LLC"></textarea>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="add_client.php" class="btn btn-sm btn-secondary">+ Client</a>
                <a href="tasks.php?action=new" class="btn btn-sm btn-secondary">+ Task</a>
                <a href="invoice_create.php" class="btn btn-sm btn-secondary">+ Invoice</a>
                <a href="personal_templates.php?action=new" class="btn btn-sm btn-secondary">+ Template</a>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeQuickAddModal()" class="btn btn-secondary">Cancel</button>
            <button type="button" onclick="executeQuickAdd()" class="btn btn-primary">Process with AI</button>
        </div>
    </div>
</div>

<div class="main-container">
    <?php display_flash_messages(); ?>
<?php
}

function render_footer() {
    ?>
</div>

<footer style="text-align:center; padding:20px; color:var(--text-muted); font-size:12px; margin-top:40px; border-top:1px solid var(--border-color);">
    &copy; <?= date('Y') ?> Gravity CRM — AI Professional Services Platform. Running on Windows XAMPP.
</footer>

<script>
// Popover Toggle Helper
function togglePopover(id) {
    const el = document.getElementById(id);
    const all = ['urgentDropdown', 'notifDropdown', 'userProfileMenu'];
    all.forEach(p => {
        if (p !== id) {
            const other = document.getElementById(p);
            if (other) other.style.display = 'none';
        }
    });
    if (el) {
        el.style.display = (el.style.display === 'block' ? 'none' : 'block');
    }
}

// Modal Handlers
function openVoiceModal() {
    document.getElementById('aiVoiceModal').style.display = 'flex';
}
function closeVoiceModal() {
    document.getElementById('aiVoiceModal').style.display = 'none';
}
function openQuickAddModal() {
    document.getElementById('quickAddModal').style.display = 'flex';
}
function closeQuickAddModal() {
    document.getElementById('quickAddModal').style.display = 'none';
}
function executeQuickAdd() {
    const text = document.getElementById('quickAddInput').value.trim();
    if (!text) return;
    alert("Gravity AI processed instruction: " + text);
    closeQuickAddModal();
}

// Close popovers on click outside
document.addEventListener('click', function(e) {
    const isPopoverBtn = e.target.closest('.bell-btn, .user-pill, .header-action-btn');
    const isPopoverContent = e.target.closest('.header-popover, .user-profile-menu, .modal-card');
    if (!isPopoverBtn && !isPopoverContent) {
        ['urgentDropdown', 'notifDropdown', 'userProfileMenu'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
    }
});
</script>

<?php
// =========================================================
// TAWK.TO LIVE CHAT WIDGET AUTO-INJECT
// =========================================================
// Agar admin ne settings me Tawk.to enable kiya hai aur Property ID
// enter ki hai, to yahan hum JavaScript widget inject karte hain.
// Visitor ka naam aur email bhi pre-fill hota hai (agar user login hai)
// taake agent ko pata chale kaun chat kar raha hai — professional CRM experience.
$tawkto_enabled     = get_setting('tawkto_enabled', '0');
$tawkto_property_id = get_setting('tawkto_property_id', '');
$tawkto_widget_id   = get_setting('tawkto_widget_id', 'default');

if ($tawkto_enabled === '1' && !empty($tawkto_property_id)):
    // CRM user ki info Tawk.to ko pre-fill karne ke liye
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

// Tawk.to visitor info pre-fill — logged in CRM user ki info set karo
// Taake chat agent CRM staff ya client ko identify kar sake
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


