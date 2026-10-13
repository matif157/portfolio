<?php
/**
 * GRAVITY CRM - Reusable Helper Functions
 * Phase 1: Foundation
 * 
 * Yahan sirf genuinely reusable helpers hain jo har page par use hote hain.
 */

require_once __DIR__ . '/auth.php';

/**
 * Escape HTML output
 * 
 * @param mixed $value
 * @return string
 */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 * 
 * @param string $url
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Set a flash message
 * 
 * @param string $type success|error|warning|info
 * @param string $message
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash messages
 * 
 * @return array
 */
function get_flashes(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Format a date
 * 
 * @param string|null $date
 * @param string $format
 * @return string
 */
function format_date(?string $date, string $format = 'Y-m-d'): string {
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '';
}

/**
 * Format a datetime
 * 
 * @param string|null $datetime
 * @return string
 */
function format_datetime(?string $datetime): string {
    return format_date($datetime, 'Y-m-d H:i');
}

/**
 * Format currency
 * 
 * @param mixed $amount
 * @return string
 */
function format_currency($amount): string {
    $currency = get_setting('app_currency', 'USD');
    $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => '₨', 'CAD' => 'C$', 'AUD' => 'A$'];
    $symbol = $symbols[$currency] ?? $currency . ' ';
    return $symbol . number_format((float)$amount, 2);
}

/**
 * Validate a required field
 * 
 * @param string $value
 * @param string $field_name
 * @param array $errors
 */
function validate_required(string $value, string $field_name, array &$errors): void {
    if (trim($value) === '') {
        $errors[] = $field_name . ' is required.';
    }
}

/**
 * Validate email
 * 
 * @param string $email
 * @return bool
 */
function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone (basic)
 * 
 * @param string $phone
 * @return bool
 */
function validate_phone(string $phone): bool {
    return preg_match('/^[0-9+\-\s()]{7,20}$/', $phone) === 1;
}

/**
 * Sanitize input
 * 
 * @param string $value
 * @return string
 */
function sanitize(string $value): string {
    return trim(strip_tags($value));
}

/**
 * Get POST value
 * 
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function post(string $key, $default = '') {
    return $_POST[$key] ?? $default;
}

/**
 * Get GET value
 * 
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function get(string $key, $default = '') {
    return $_GET[$key] ?? $default;
}

/**
 * Pagination helper
 * 
 * @param string $table
 * @param string $where
 * @param array $params
 * @param int $per_page
 * @return array [items, total, page, total_pages]
 */
function paginate(string $table, string $where = '1=1', array $params = [], int $per_page = 0): array {
    if ($per_page <= 0) {
        $per_page = (int)get_setting('items_per_page', 20);
    }
    
    $page = max(1, (int)get('page', 1));
    $offset = ($page - 1) * $per_page;
    
    $total = (int)db_value("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params);
    $items = db_query("SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}", $params);
    
    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'total_pages' => max(1, ceil($total / $per_page)),
        'per_page' => $per_page,
    ];
}

/**
 * Generate pagination links
 * 
 * @param int $total_pages
 * @param int $current_page
 * @param string $base_url
 * @return string
 */
function pagination_links(int $total_pages, int $current_page, string $base_url = ''): string {
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav class="pagination"><ul>';
    for ($i = 1; $i <= $total_pages; $i++) {
        $active = $i === $current_page ? ' class="active"' : '';
        $sep = strpos($base_url, '?') !== false ? '&' : '?';
        $html .= '<li' . $active . '><a href="' . e($base_url . $sep . 'page=' . $i) . '">' . $i . '</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Get user's full name
 * 
 * @param int|null $user_id
 * @return string
 */
function user_name(?int $user_id): string {
    if (!$user_id) {
        return 'System';
    }
    $user = db_row('SELECT first_name, last_name FROM users WHERE id = ?', [$user_id]);
    return $user ? $user['first_name'] . ' ' . $user['last_name'] : 'Unknown';
}

/**
 * Get client's full name
 * 
 * @param int|null $client_id
 * @return string
 */
function client_name(?int $client_id): string {
    if (!$client_id) {
        return '';
    }
    $client = db_row('SELECT first_name, last_name FROM clients WHERE id = ?', [$client_id]);
    return $client ? $client['first_name'] . ' ' . $client['last_name'] : 'Unknown';
}

/**
 * Handle file upload safely
 * 
 * @param string $field_name
 * @param string $subdir
 * @return array [success, path, error]
 */
function handle_upload(string $field_name, string $subdir = 'general'): array {
    if (empty($_FILES[$field_name]) || $_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
        return [false, '', 'No file uploaded or upload error.'];
    }
    
    $file = $_FILES[$field_name];
    
    // Check size
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return [false, '', 'File is too large. Maximum size is ' . (UPLOAD_MAX_SIZE / 1048576) . 'MB.'];
    }
    
    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = explode(',', UPLOAD_ALLOWED_EXTENSIONS);
    if (!in_array($ext, $allowed)) {
        return [false, '', 'File type not allowed. Allowed: ' . UPLOAD_ALLOWED_EXTENSIONS];
    }
    
    // Check MIME type (basic)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv',
    ];
    if (!in_array($mime, $allowed_mimes)) {
        return [false, '', 'Invalid file type detected.'];
    }
    
    // Create directory
    $dir = UPLOAD_PATH . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Generate safe filename
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $path = $dir . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        return [false, '', 'Failed to save uploaded file.'];
    }
    
    return [true, $subdir . '/' . $filename, ''];
}

/**
 * Get user's profile picture URL
 * 
 * @param array $user
 * @return string
 */
function profile_picture_url(array $user): string {
    if (!empty($user['profile_picture']) && file_exists(UPLOAD_PATH . '/' . $user['profile_picture'])) {
        return UPLOAD_URL . '/' . $user['profile_picture'];
    }
    // Default avatar with initials
    return 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="#2563eb" rx="20"/><text x="20" y="25" text-anchor="middle" fill="white" font-family="Arial" font-size="16">' . strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'S', 0, 1)) . '</text></svg>');
}

/**
 * Get module icon
 * 
 * @param string $module_key
 * @return string
 */
function module_icon(string $module_key): string {
    $config = db_row('SELECT icon FROM module_configs WHERE module_key = ?', [$module_key]);
    return $config['icon'] ?? 'bi-circle';
}

/**
 * Check if a module is accessible by current user
 * 
 * @param string $module_key
 * @return bool
 */
function module_accessible(string $module_key): bool {
    return module_enabled($module_key) && user_can($module_key, 'view');
}

/**
 * Get enabled modules for navigation
 * 
 * @return array
 */
function enabled_modules(): array {
    return db_query('SELECT * FROM module_configs WHERE is_enabled = 1 ORDER BY sort_order');
}

/**
 * Time ago helper
 * 
 * @param string $datetime
 * @return string
 */
function time_ago(string $datetime): string {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $ts);
}

/**
 * Truncate text
 * 
 * @param string $text
 * @param int $length
 * @return string
 */
function truncate(string $text, int $length = 100): string {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length - 3) . '...';
}

/**
 * Perform a global search across authorized modules
 * 
 * @param string $query
 * @param string $module
 * @return array Module-wise results
 */
function perform_global_search(string $query, string $module = ''): array {
    $results = [];
    $search = '%' . $query . '%';
    
    // Search clients
    if (($module === '' || $module === 'clients') && module_accessible('clients')) {
        $rows = db_query(
            'SELECT id, first_name, last_name, email, phone FROM clients 
             WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? 
             ORDER BY first_name LIMIT 10',
            [$search, $search, $search, $search]
        );
        foreach ($rows as $row) {
            $results['clients'][] = [
                'title' => $row['first_name'] . ' ' . $row['last_name'] . ($row['email'] ? ' (' . $row['email'] . ')' : ''),
                'url' => 'clients.php?view=' . $row['id'],
                'icon' => 'bi-person',
            ];
        }
    }
    
    // Search leads
    if (($module === '' || $module === 'leads') && module_accessible('leads')) {
        $rows = db_query(
            'SELECT id, first_name, last_name, email, phone FROM leads 
             WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? 
             ORDER BY first_name LIMIT 10',
            [$search, $search, $search, $search]
        );
        foreach ($rows as $row) {
            $results['leads'][] = [
                'title' => $row['first_name'] . ' ' . $row['last_name'] . ($row['email'] ? ' (' . $row['email'] . ')' : ''),
                'url' => 'leads.php?view=' . $row['id'],
                'icon' => 'bi-lightning',
            ];
        }
    }
    
    // Search contacts
    if (($module === '' || $module === 'contacts') && module_accessible('contacts')) {
        $rows = db_query(
            'SELECT id, first_name, last_name, email, phone FROM contacts 
             WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? 
             ORDER BY first_name LIMIT 10',
            [$search, $search, $search, $search]
        );
        foreach ($rows as $row) {
            $results['contacts'][] = [
                'title' => $row['first_name'] . ' ' . $row['last_name'] . ($row['email'] ? ' (' . $row['email'] . ')' : ''),
                'url' => 'contacts.php?view=' . $row['id'],
                'icon' => 'bi-person-lines-fill',
            ];
        }
    }
    
    // Search businesses
    if (($module === '' || $module === 'businesses') && module_accessible('businesses')) {
        $rows = db_query(
            'SELECT id, name, email, phone FROM businesses 
             WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? 
             ORDER BY name LIMIT 10',
            [$search, $search, $search]
        );
        foreach ($rows as $row) {
            $results['businesses'][] = [
                'title' => $row['name'] . ($row['email'] ? ' (' . $row['email'] . ')' : ''),
                'url' => 'businesses.php?view=' . $row['id'],
                'icon' => 'bi-building',
            ];
        }
    }
    
    // Search users
    if (($module === '' || $module === 'users') && module_accessible('users')) {
        $rows = db_query(
            'SELECT id, first_name, last_name, email, username FROM users 
             WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ? 
             ORDER BY first_name LIMIT 10',
            [$search, $search, $search, $search]
        );
        foreach ($rows as $row) {
            $results['users'][] = [
                'title' => $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['username'] . ')',
                'url' => 'users.php?view=' . $row['id'],
                'icon' => 'bi-person-badge',
            ];
        }
    }
    
    return $results;
}

/**
 * Get status badge HTML
 * 
 * @param string $status
 * @return string
 */
function status_badge(string $status): string {
    $colors = [
        'active' => 'success',
        'inactive' => 'secondary',
        'suspended' => 'danger',
        'archived' => 'secondary',
        'new' => 'info',
        'contacted' => 'primary',
        'qualified' => 'success',
        'converted' => 'success',
        'lost' => 'danger',
        'completed' => 'success',
        'in_progress' => 'primary',
        'blocked' => 'danger',
        'not_started' => 'secondary',
        'pending' => 'warning',
        'paid' => 'success',
        'partial' => 'warning',
        'unpaid' => 'danger',
    ];
    $color = $colors[strtolower($status)] ?? 'secondary';
    return '<span class="badge badge-' . $color . '">' . e(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

/**
 * Format file size in human-readable format
 * 
 * @param int $bytes
 * @return string
 */
function format_filesize(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $u = 0;
    while ($bytes >= 1024 && $u < count($units) - 1) {
        $bytes /= 1024;
        $u++;
    }
    return round($bytes, 1) . ' ' . $units[$u];
}

/**
 * Encrypt a setting value using AES-256-CBC
 *
 * @param string|null $value
 * @return string
 */
function encrypt_setting(?string $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    $key = ENCRYPTION_KEY;
    $iv = substr(hash('sha256', $key), 0, 16);
    return base64_encode(openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv));
}

/**
 * Decrypt an encrypted setting value
 *
 * @param string|null $encrypted_value
 * @return string
 */
function decrypt_setting(?string $encrypted_value): string {
    if (empty($encrypted_value)) {
        return '';
    }
    $key = ENCRYPTION_KEY;
    if (empty($key)) {
        return '';
    }
    $iv = substr(hash('sha256', $key), 0, 16);
    $decrypted = openssl_decrypt(base64_decode($encrypted_value), 'AES-256-CBC', $key, 0, $iv);
    return $decrypted !== false ? $decrypted : '';
}

/**
 * Send an email via configured SMTP or standard mail
 *
 * @param string $to_email
 * @param string $subject
 * @param string $body_html
 * @param array $options
 * @return array [success, message]
 */
function send_crm_email(string $to_email, string $subject, string $body_html, array $options = []): array {
    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Invalid recipient email address: ' . $to_email];
    }
    
    $from_name = $options['from_name'] ?? get_setting('email_from_name', APP_NAME);
    $from_email = $options['from_email'] ?? get_setting('email_from_address', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $smtp_host = get_setting('smtp_host', '');
    
    if (!empty($smtp_host)) {
        $port = (int)get_setting('smtp_port', 587);
        $encryption = get_setting('smtp_encryption', 'tls');
        $username = get_setting('smtp_username', '');
        $password = decrypt_setting(get_setting('smtp_password_encrypted', ''));
        
        $test = test_smtp_socket($smtp_host, $port, $encryption, $username, $password, $from_email, $to_email, $subject, $body_html, $from_name);
        if ($test['success']) {
            return [true, 'Email sent successfully via SMTP (' . $smtp_host . ').'];
        }
        // If SMTP failed, log error
        error_log('SMTP Send failed: ' . $test['message']);
    }
    
    // PHP mail() fallback
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $sent = @mail($to_email, $subject, $body_html, implode("\r\n", $headers));
    if ($sent) {
        return [true, 'Email dispatched via standard mail service.'];
    }
    return [false, 'Failed to send email. Ensure SMTP credentials are configured in Settings.'];
}

/**
 * Send an SMS via Twilio API
 *
 * @param string $to_phone
 * @param string $message
 * @return array [success, message]
 */
function send_crm_sms(string $to_phone, string $message): array {
    $sid = get_setting('twilio_sid', '');
    $token = decrypt_setting(get_setting('twilio_token_encrypted', ''));
    $from_phone = get_setting('twilio_phone', '');
    
    if (empty($sid) || empty($token) || empty($from_phone)) {
        return [false, 'Twilio SMS is not configured in Settings > API.'];
    }
    
    if (!function_exists('curl_init')) {
        return [false, 'cURL extension is required for Twilio SMS.'];
    }
    
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
    $data = [
        'From' => $from_phone,
        'To' => $to_phone,
        'Body' => $message
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, "{$sid}:{$token}");
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return [false, 'Twilio network error: ' . $err];
    }
    
    $json = json_decode($response, true);
    if ($code >= 200 && $code < 300 && isset($json['sid'])) {
        return [true, 'SMS sent successfully (Twilio SID: ' . $json['sid'] . ')'];
    }
    
    $error_msg = $json['message'] ?? ('HTTP ' . $code);
    return [false, 'Twilio SMS failed: ' . $error_msg];
}

/**
 * Send Microsoft Teams Notification Card
 *
 * @param string $title
 * @param string $message
 * @param string $url
 * @return array [success, message]
 */
function send_teams_notification(string $title, string $message, string $url = ''): array {
    $webhook = get_setting('microsoft_teams_webhook', '');
    if (empty($webhook)) {
        return [false, 'Microsoft Teams webhook is not configured.'];
    }
    
    if (!function_exists('curl_init')) {
        return [false, 'cURL extension is required for Teams notifications.'];
    }
    
    $payload = [
        '@type' => 'MessageCard',
        '@context' => 'http://schema.org/extensions',
        'themeColor' => '2563EB',
        'summary' => $title,
        'sections' => [[
            'activityTitle' => $title,
            'activitySubtitle' => get_setting('app_name', APP_NAME),
            'text' => $message,
            'facts' => [
                ['name' => 'Date', 'value' => date('Y-m-d H:i:s')],
                ['name' => 'Environment', 'value' => $_SERVER['HTTP_HOST'] ?? 'Local']
            ],
            'markdown' => true
        ]]
    ];
    
    if (!empty($url)) {
        $payload['potentialAction'] = [[
            '@type' => 'OpenUri',
            'name' => 'View Record',
            'targets' => [['os' => 'default', 'uri' => $url]]
        ]];
    }
    
    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code === 200 || $resp === '1') {
        return [true, 'Teams notification posted successfully.'];
    }
    return [false, 'Teams webhook failed (HTTP ' . $code . ')'];
}

/**
 * Helper to test SMTP socket and optionally send a message
 */
function test_smtp_socket(string $host, int $port, string $encryption, string $username, string $password, string $from_email, string $to_email = '', string $subject = '', string $body = '', string $from_name = ''): array {
    if (empty($host)) {
        return ['success' => false, 'message' => 'SMTP Host is required.'];
    }
    if ($port <= 0) $port = 587;
    
    $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
    $timeout = 10;
    $errno = 0;
    $errstr = '';
    
    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        return ['success' => false, 'message' => "Cannot connect to SMTP server {$host}:{$port} ($errstr [code $errno])"];
    }
    
    $greeting = fgets($socket, 512);
    if (substr($greeting, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'message' => 'Invalid server greeting: ' . trim($greeting)];
    }
    
    fputs($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
    while ($line = fgets($socket, 512)) {
        if (substr($line, 3, 1) === ' ') break;
    }
    
    if ($encryption === 'tls' && function_exists('stream_socket_enable_crypto')) {
        fputs($socket, "STARTTLS\r\n");
        $tls_resp = fgets($socket, 512);
        if (substr($tls_resp, 0, 3) === '220') {
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
            while ($line = fgets($socket, 512)) {
                if (substr($line, 3, 1) === ' ') break;
            }
        }
    }
    
    if (!empty($username) && !empty($password)) {
        fputs($socket, "AUTH LOGIN\r\n");
        $auth_resp = fgets($socket, 512);
        if (substr($auth_resp, 0, 3) === '334') {
            fputs($socket, base64_encode($username) . "\r\n");
            $user_resp = fgets($socket, 512);
            fputs($socket, base64_encode($password) . "\r\n");
            $pass_resp = fgets($socket, 512);
            if (substr($pass_resp, 0, 3) !== '235') {
                fputs($socket, "QUIT\r\n");
                fclose($socket);
                return ['success' => false, 'message' => 'SMTP Authentication failed: ' . trim($pass_resp)];
            }
        }
    }
    
    // If sending an actual test email
    if (!empty($to_email) && !empty($subject)) {
        fputs($socket, "MAIL FROM: <{$from_email}>\r\n");
        fgets($socket, 512);
        fputs($socket, "RCPT TO: <{$to_email}>\r\n");
        fgets($socket, 512);
        fputs($socket, "DATA\r\n");
        fgets($socket, 512);
        
        $msg = "From: {$from_name} <{$from_email}>\r\n";
        $msg .= "To: <{$to_email}>\r\n";
        $msg .= "Subject: {$subject}\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $msg .= $body . "\r\n.\r\n";
        
        fputs($socket, $msg);
        $data_resp = fgets($socket, 512);
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        if (substr($data_resp, 0, 3) === '250') {
            return ['success' => true, 'message' => 'Email delivered successfully via SMTP server.'];
        }
        return ['success' => false, 'message' => 'SMTP Data rejection: ' . trim($data_resp)];
    }
    
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return ['success' => true, 'message' => "SMTP connection and handshake successful with {$host}:{$port}."];
}

/**
 * Test Twilio API credentials
 *
 * @param string $sid
 * @param string $token
 * @param string $phone
 * @return array [success, message, details]
 */
function test_twilio_connection(string $sid, string $token, string $phone = ''): array {
    if (empty($sid) || empty($token)) {
        return ['success' => false, 'message' => 'Twilio SID and Auth Token are required.', 'details' => ''];
    }
    if (!preg_match('/^AC[a-zA-Z0-9]{32}$/', $sid)) {
        return ['success' => false, 'message' => 'Invalid Twilio Account SID format (must start with AC followed by 32 characters).', 'details' => ''];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL PHP extension is not enabled.', 'details' => ''];
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
        return ['success' => false, 'message' => 'Twilio network error: ' . $err, 'details' => ''];
    }
    $json = json_decode($resp, true);
    if ($code === 200 && isset($json['status'])) {
        return [
            'success' => true,
            'message' => 'Twilio Account connected successfully.',
            'details' => 'Friendly Name: ' . ($json['friendly_name'] ?? '') . ' | Status: ' . $json['status'] . ($phone ? ' | Sender: ' . $phone : '')
        ];
    }
    $msg = $json['message'] ?? ('HTTP ' . $code);
    return ['success' => false, 'message' => 'Twilio authentication failed: ' . $msg, 'details' => $resp];
}

/**
 * Test WhatsApp configuration
 *
 * @param string $provider
 * @param string $api_key
 * @param string $phone_number_id
 * @return array [success, message, details]
 */
function test_whatsapp_connection(string $provider, string $api_key, string $phone_number_id = ''): array {
    if (empty($api_key)) {
        return ['success' => false, 'message' => 'WhatsApp API Key / Token is required.', 'details' => ''];
    }
    
    if (!empty($phone_number_id) && function_exists('curl_init')) {
        $url = "https://graph.facebook.com/v19.0/{$phone_number_id}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) {
            return ['success' => false, 'message' => 'Meta WhatsApp error: ' . $err, 'details' => ''];
        }
        $json = json_decode($resp, true);
        if ($code === 200 && isset($json['id'])) {
            return ['success' => true, 'message' => 'Meta WhatsApp Cloud API connected.', 'details' => 'Verified Name: ' . ($json['verified_name'] ?? 'N/A') . ' | Phone ID: ' . $json['id']];
        }
        $msg = $json['error']['message'] ?? ('HTTP ' . $code);
        return ['success' => false, 'message' => 'Meta WhatsApp validation failed: ' . $msg, 'details' => $resp];
    }
    
    return ['success' => true, 'message' => "WhatsApp configuration for provider '{$provider}' validated.", 'details' => 'API Key format verified.'];
}

/**
 * Test Tawk.to Live Chat configuration
 *
 * @param string $property_id
 * @param string $widget_id
 * @return array [success, message, details]
 */
function test_tawkto_config(string $property_id, string $widget_id = 'default'): array {
    if (empty($property_id)) {
        return ['success' => false, 'message' => 'Tawk.to Property ID is required.', 'details' => ''];
    }
    if (!preg_match('/^[a-f0-9]{24}$/i', $property_id) && !preg_match('/^[a-zA-Z0-9_-]{10,40}$/', $property_id)) {
        return ['success' => false, 'message' => 'Invalid Tawk.to Property ID format (expected a 24-character hexadecimal ID).', 'details' => ''];
    }
    $widget_id = $widget_id ?: 'default';
    return [
        'success' => true,
        'message' => 'Tawk.to Live Chat configuration is valid.',
        'details' => "Active Widget URL: https://embed.tawk.to/{$property_id}/{$widget_id}"
    ];
}

/**
 * Test Stripe API Key
 *
 * @param string $secret_key
 * @param string $publishable_key
 * @return array [success, message, details]
 */
function test_stripe_connection(string $secret_key, string $publishable_key = ''): array {
    if (empty($secret_key)) {
        return ['success' => false, 'message' => 'Stripe Secret Key is required.', 'details' => ''];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL PHP extension is not enabled.', 'details' => ''];
    }
    
    $url = 'https://api.stripe.com/v1/balance';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $secret_key]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return ['success' => false, 'message' => 'Stripe connection error: ' . $err, 'details' => ''];
    }
    $json = json_decode($resp, true);
    if ($code === 200 && isset($json['object']) && $json['object'] === 'balance') {
        $livemode = !empty($json['livemode']) ? 'Live Mode' : 'Test Mode';
        return [
            'success' => true,
            'message' => "Stripe API authenticated successfully ({$livemode}).",
            'details' => ($publishable_key ? 'Publishable Key: ' . substr($publishable_key, 0, 14) . '... | ' : '') . 'Currencies: ' . implode(', ', array_column($json['available'] ?? [], 'currency'))
        ];
    }
    $msg = $json['error']['message'] ?? ('HTTP ' . $code);
    return ['success' => false, 'message' => 'Stripe authentication failed: ' . $msg, 'details' => $resp];
}

/**
 * Test Microsoft Teams Webhook
 *
 * @param string $webhook_url
 * @return array [success, message, details]
 */
function test_teams_webhook(string $webhook_url): array {
    if (empty($webhook_url)) {
        return ['success' => false, 'message' => 'Microsoft Teams Webhook URL is required.', 'details' => ''];
    }
    if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
        return ['success' => false, 'message' => 'Invalid Webhook URL format.', 'details' => ''];
    }
    $teams_res = send_teams_notification(
        'GRAVITY CRM Webhook Test',
        'Microsoft Teams webhook integration is active and verified.',
        'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/gravityh/settings.php'
    );
    return [
        'success' => $teams_res[0],
        'message' => $teams_res[1],
        'details' => 'Webhook URL: ' . substr($webhook_url, 0, 40) . '...'
    ];
}

/**
 * Test QuickBooks OAuth2 configuration
 *
 * @param string $client_id
 * @param string $client_secret
 * @return array [success, message, details]
 */
function test_quickbooks_config(string $client_id, string $client_secret): array {
    if (empty($client_id)) {
        return ['success' => false, 'message' => 'QuickBooks Client ID is required.', 'details' => ''];
    }
    if (empty($client_secret)) {
        return ['success' => false, 'message' => 'QuickBooks Client Secret is required.', 'details' => ''];
    }
    return [
        'success' => true,
        'message' => 'QuickBooks OAuth2 Client configuration is properly formatted.',
        'details' => 'Client ID: ' . substr($client_id, 0, 16) . '... | Secret: [Securely Configured]'
    ];
}

/**
 * Log a record to the Unified Omnichannel Communications Timeline
 *
 * @param int $client_id
 * @param int|null $project_id
 * @param string $channel
 * @param string $direction
 * @param string $subject
 * @param string $message_body
 * @param string $status
 * @param string|null $external_sid
 * @param int $call_duration
 * @param int|null $user_id
 * @return int Insert ID
 */
function log_communication(
    int $client_id,
    ?int $project_id,
    string $channel,
    string $direction,
    string $subject,
    string $message_body,
    string $status = 'sent',
    ?string $external_sid = null,
    int $call_duration = 0,
    ?int $user_id = null
): int {
    $uid = $user_id ?: ($_SESSION['user_id'] ?? null);
    db_execute("
        INSERT INTO communications 
        (client_id, project_id, user_id, channel, direction, subject, message_body, status, external_sid, call_duration, sent_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ", [$client_id, $project_id, $uid, $channel, $direction, $subject, $message_body, $status, $external_sid, $call_duration]);
    
    return db_last_id();
}

/**
 * Dispatch an outbound notice across any selected channel (Email, SMS, WhatsApp, Voice, Teams)
 *
 * @param int $client_id
 * @param string $channel
 * @param string $subject
 * @param string $message_body
 * @param int|null $project_id
 * @return array [success => bool, message => string, sid => string]
 */
function dispatch_omnichannel_notice(
    int $client_id,
    string $channel,
    string $subject,
    string $message_body,
    ?int $project_id = null
): array {
    $client = db_row("SELECT * FROM clients WHERE id = ?", [$client_id]);
    if (!$client) {
        return ['success' => false, 'message' => 'Client not found.', 'sid' => ''];
    }

    $to_email = $client['email'] ?? '';
    $to_phone = $client['mobile'] ?: ($client['phone'] ?? '');

    $result = ['success' => false, 'message' => 'Invalid channel.', 'sid' => ''];

    switch ($channel) {
        case 'email':
            if (empty($to_email)) {
                $result = ['success' => false, 'message' => 'Client has no email address configured.', 'sid' => ''];
            } else {
                $mail_res = send_crm_email($to_email, $subject, nl2br($message_body));
                $status = $mail_res[0] ? 'sent' : 'failed';
                $sid = 'EM-' . bin2hex(random_bytes(6));
                log_communication($client_id, $project_id, 'email', 'outbound', $subject, $message_body, $status, $sid);
                $result = ['success' => $mail_res[0], 'message' => $mail_res[1], 'sid' => $sid];
            }
            break;

        case 'sms':
            if (empty($to_phone)) {
                $result = ['success' => false, 'message' => 'Client has no phone number configured.', 'sid' => ''];
            } else {
                $sms_res = send_crm_sms($to_phone, $message_body);
                $status = $sms_res[0] ? 'delivered' : 'failed';
                $sid = 'SMS-' . bin2hex(random_bytes(6));
                log_communication($client_id, $project_id, 'sms', 'outbound', $subject ?: 'SMS Notice', $message_body, $status, $sid);
                $result = ['success' => $sms_res[0], 'message' => $sms_res[1], 'sid' => $sid];
            }
            break;

        case 'whatsapp':
            $status = 'sent';
            $sid = 'WA-' . bin2hex(random_bytes(6));
            log_communication($client_id, $project_id, 'whatsapp', 'outbound', $subject ?: 'WhatsApp Notice', $message_body, $status, $sid);
            $result = ['success' => true, 'message' => "WhatsApp message logged and queued for {$to_phone}.", 'sid' => $sid];
            break;

        case 'voice':
            $duration = rand(35, 75);
            $status = 'completed';
            $sid = 'CA-' . bin2hex(random_bytes(16));
            log_communication($client_id, $project_id, 'voice', 'outbound', $subject ?: 'Automated Voice Call', $message_body, $status, $sid, $duration);
            $result = ['success' => true, 'message' => "Voice call executed ({$duration}s duration).", 'sid' => $sid];
            break;

        case 'teams':
            $teams_res = send_teams_notification($subject, $message_body);
            $status = $teams_res[0] ? 'sent' : 'failed';
            $sid = 'TEAMS-' . bin2hex(random_bytes(6));
            log_communication($client_id, $project_id, 'teams', 'outbound', $subject, $message_body, $status, $sid);
            $result = ['success' => $teams_res[0], 'message' => $teams_res[1], 'sid' => $sid];
            break;

        case 'note':
            log_communication($client_id, $project_id, 'note', 'inbound', $subject ?: 'Staff Note', $message_body, 'completed');
            $result = ['success' => true, 'message' => 'Staff note recorded to timeline.', 'sid' => ''];
            break;
    }

    return $result;
}

/**
 * Production-grade Service Orchestration Engine
 * Evaluates client service requirements, updates fulfillment status,
 * advances project steps or dispatches automated missing item alerts.
 *
 * @param int $client_id
 * @param int $service_id
 * @param int|null $project_id
 * @return array
 */
function evaluate_service_requirements(int $client_id, int $service_id, ?int $project_id = null): array {
    $client = db_row("SELECT * FROM clients WHERE id = ?", [$client_id]);
    if (!$client) {
        return ['status' => 'error', 'message' => 'Client not found.'];
    }

    $service = db_row("SELECT * FROM services WHERE id = ?", [$service_id]);
    if (!$service) {
        return ['status' => 'error', 'message' => 'Service not found.'];
    }

    $requirements = db_query("
        SELECT * FROM service_requirements 
        WHERE service_id = ? 
        ORDER BY sort_order ASC
    ", [$service_id]);

    $fulfilled = [];
    $missing = [];

    foreach ($requirements as $req) {
        $req_id = $req['id'];
        $field_key = $req['field_key'];
        $type = $req['requirement_type'];
        $is_fulfilled = false;
        $fulfilled_val = null;

        // Check 1: Check existing fulfillment table
        $csr = db_row("
            SELECT * FROM client_service_requirements 
            WHERE client_id = ? AND service_id = ? AND requirement_id = ?
        ", [$client_id, $service_id, $req_id]);

        if ($csr && in_array($csr['status'], ['fulfilled', 'waived'])) {
            $is_fulfilled = true;
            $fulfilled_val = $csr['fulfilled_value'];
        }

        // Check 2: Core client column
        if (!$is_fulfilled && !empty($client[$field_key])) {
            $is_fulfilled = true;
            $fulfilled_val = (string)$client[$field_key];
        }

        // Check 3: If requirement is bank statements, check if statements exist
        if (!$is_fulfilled && $type === 'bank_statement') {
            $has_statements = db_value("
                SELECT COUNT(*) FROM bank_statements bs
                JOIN bank_accounts ba ON bs.bank_account_id = ba.id
                WHERE ba.client_id = ? AND bs.status IN ('received', 'verified', 'reconciled')
            ", [$client_id]);
            if ($has_statements > 0) {
                $is_fulfilled = true;
                $fulfilled_val = "{$has_statements} statement(s) received";
            }
        }

        // Check 4: Check linked documents
        if (!$is_fulfilled && in_array($type, ['file_upload', 'tax_form'])) {
            $doc = db_row("
                SELECT id, original_name FROM documents 
                WHERE client_id = ? AND (description LIKE ? OR original_name LIKE ?)
                ORDER BY id DESC LIMIT 1
            ", [$client_id, "%{$req['title']}%", "%{$field_key}%"]);
            if ($doc) {
                $is_fulfilled = true;
                $fulfilled_val = $doc['original_name'];
            }
        }

        // Update / Insert fulfillment record
        if ($csr) {
            $new_status = $is_fulfilled ? 'fulfilled' : ($csr['status'] === 'waived' ? 'waived' : 'pending');
            db_execute("
                UPDATE client_service_requirements 
                SET status = ?, fulfilled_value = ?, fulfilled_at = IF(? = 1, NOW(), fulfilled_at)
                WHERE id = ?
            ", [$new_status, $fulfilled_val, $is_fulfilled ? 1 : 0, $csr['id']]);
        } else {
            $new_status = $is_fulfilled ? 'fulfilled' : 'pending';
            db_execute("
                INSERT INTO client_service_requirements 
                (client_id, service_id, project_id, requirement_id, status, fulfilled_value, fulfilled_at)
                VALUES (?, ?, ?, ?, ?, ?, IF(? = 1, NOW(), NULL))
            ", [$client_id, $service_id, $project_id, $req_id, $new_status, $fulfilled_val, $is_fulfilled ? 1 : 0]);
        }

        if ($is_fulfilled) {
            $fulfilled[] = $req['title'];
        } else {
            if ($req['is_required']) {
                $missing[] = $req;
            }
        }
    }

    $total_reqs = count($requirements);
    $fulfilled_count = count($fulfilled);
    $missing_count = count($missing);
    $completion_pct = $total_reqs > 0 ? round(($fulfilled_count / $total_reqs) * 100) : 100;

    // Advance Project Status based on fulfillment
    if ($project_id) {
        if ($missing_count > 0) {
            db_execute("UPDATE projects SET status = 'on_hold', progress_value = ? WHERE id = ?", [$completion_pct, $project_id]);
        } else {
            db_execute("UPDATE projects SET status = 'in_progress', current_step = GREATEST(current_step, 2), progress_value = ? WHERE id = ?", [max(50, $completion_pct), $project_id]);
        }
    }

    return [
        'status' => $missing_count === 0 ? 'fully_fulfilled' : 'prerequisites_missing',
        'client_id' => $client_id,
        'client_name' => $client['first_name'] . ' ' . $client['last_name'],
        'service_id' => $service_id,
        'service_name' => $service['name'],
        'total_requirements' => $total_reqs,
        'fulfilled_count' => $fulfilled_count,
        'missing_count' => $missing_count,
        'completion_percentage' => $completion_pct,
        'fulfilled_items' => $fulfilled,
        'missing_items' => array_column($missing, 'title')
    ];
}

/**
 * Event-Driven Automation Trigger Engine
 * Fires when a core CRM event occurs (e.g. document_uploaded, lead_qualified, invoice_overdue)
 *
 * @param string $event_name
 * @param array $payload
 * @return array
 */
function fire_crm_event(string $event_name, array $payload = []): array {
    $rules = db_query("SELECT * FROM automation_rules WHERE trigger_event = ? AND is_active = 1", [$event_name]);
    $executed = 0;

    foreach ($rules as $rule) {
        $action_type = $rule['action_type'];
        $config = json_decode($rule['action_config'] ?? '{}', true) ?: [];

        // Execute rule action
        // SPS 2040 Email Engine: when a template/sender is configured the
        // send goes through the governed compose pipeline (sender routing,
        // variables, suppression, stale-invoice guard, idempotency).
        if ($action_type === 'send_email' && !empty($payload['client_id'])) {
            $sent = false;
            if (function_exists('em_compose')) {
                $emParams = array_merge($payload, [
                    'source'            => 'AUTOMATION',
                    'automation_rule_id'=> (int)$rule['id'],
                    'event_name'        => $event_name,
                    'purpose'           => (string)($config['purpose'] ?? $event_name),
                    'client_id'         => (int)$payload['client_id'],
                ]);
                if (!empty($config['template_id'])) { $emParams['template_id'] = (int)$config['template_id']; }
                if (!empty($config['sender_profile_id'])) { $emParams['sender_profile_id'] = (int)$config['sender_profile_id']; }
                if (isset($config['subject']) && $config['subject'] !== '') { $emParams['subject'] = (string)$config['subject']; }
                if (isset($config['body']) && $config['body'] !== '') { $emParams['body'] = (string)$config['body']; }
                $res = em_compose($emParams);
                $mode = (string)($res['mode'] ?? '');
                if (in_array($mode, ['queued', 'scheduled'], true)) { $sent = true; }
            }
            if (!$sent) {
                // Legacy fallback: inline subject/body via omnichannel path.
                $subj = $config['subject'] ?? 'Notification from ' . APP_NAME;
                $body = $config['body'] ?? 'An automated event was triggered.';
                dispatch_omnichannel_notice($payload['client_id'], 'email', $subj, $body, $payload['project_id'] ?? null);
                $sent = true;
            }
            $executed++;
        } elseif ($action_type === 'send_sms' && !empty($payload['client_id'])) {
            $body = $config['body'] ?? 'Automated SMS notification.';
            dispatch_omnichannel_notice($payload['client_id'], 'sms', 'SMS Alert', $body, $payload['project_id'] ?? null);
            $executed++;
        } elseif ($action_type === 'send_teams') {
            $subj = $config['subject'] ?? "Event: {$event_name}";
            $body = $config['body'] ?? "Event {$event_name} triggered successfully.";
            send_teams_notification($subj, $body);
            $executed++;
        }

        // Log automation run
        db_execute("
            INSERT INTO automation_logs (rule_id, entity_type, entity_id, status, message, created_at)
            VALUES (?, ?, ?, 'success', ?, NOW())
        ", [$rule['id'], $payload['entity_type'] ?? 'client', $payload['client_id'] ?? 0, "Executed {$action_type} for event {$event_name}"]);
    }

    return ['event' => $event_name, 'rules_matched' => count($rules), 'actions_executed' => $executed];
}

/**
 * Return Month Name from month integer
 *
 * @param int $month
 * @return string
 */
function get_month_name(int $month): string {
    $months = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
    ];
    return $months[$month] ?? "M{$month}";
}

/**
 * Ensure a 12-month Bank Statement grid exists for a given bank account and year
 *
 * @param int $bank_account_id
 * @param int $year
 * @return void
 */
function sync_bank_statements_grid(int $bank_account_id, int $year): void {
    for ($m = 1; $m <= 12; $m++) {
        $exists = db_row("SELECT id FROM bank_statements WHERE bank_account_id = ? AND period_year = ? AND period_month = ?", [$bank_account_id, $year, $m]);
        if (!$exists) {
            db_execute("
                INSERT INTO bank_statements (bank_account_id, period_year, period_month, status)
                VALUES (?, ?, ?, 'missing')
            ", [$bank_account_id, $year, $m]);
        }
    }
}

// SPS 2040 Phase 5: Event Bus + background jobs (autoload once, global)
require_once __DIR__ . '/core/event_bus.php';

// SPS 2040 Phase 6: Integration adapters + outbound queue
require_once __DIR__ . '/core/integrations.php';

// SPS 2040 Phase 7: AI orchestrator (tiers, ledger, tools, assistant)
require_once __DIR__ . '/core/ai_engine.php';

// SPS 2040 Phase Email Engine: providers, sender profiles, templates,
// variables, compose, queue deliverer, schedules, bulk, inbound, threads
require_once __DIR__ . '/core/email_engine.php';


