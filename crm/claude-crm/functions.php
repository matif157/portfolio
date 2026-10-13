<?php
// ============================================================
// SPS CRM - Shared Helper Functions
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ─── Output / Escaping ───────────────────────────────────────

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── Redirect ────────────────────────────────────────────────

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ─── Flash Messages ──────────────────────────────────────────

function flash_success(string $msg): void {
    $_SESSION['flash_success'] = $msg;
}

function flash_error(string $msg): void {
    $_SESSION['flash_error'] = $msg;
}

function flash_info(string $msg): void {
    $_SESSION['flash_info'] = $msg;
}

function render_flash(): string {
    $html = '';
    foreach (['flash_success' => 'success', 'flash_error' => 'danger', 'flash_info' => 'info'] as $key => $type) {
        if (!empty($_SESSION[$key])) {
            $html .= '<div class="alert alert-' . $type . '">' . e($_SESSION[$key]) . '</div>';
            unset($_SESSION[$key]);
        }
    }
    return $html;
}

// ─── Date / Time ─────────────────────────────────────────────

function fmt_date(?string $date): string {
    if (!$date) return '—';
    return date(APP_DATE_FORMAT, strtotime($date));
}

function fmt_datetime(?string $dt): string {
    if (!$dt) return '—';
    return date(APP_DATETIME_FORMAT, strtotime($dt));
}

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}

// ─── Currency ────────────────────────────────────────────────

function fmt_currency(float $amount): string {
    return APP_CURRENCY_SYMBOL . number_format($amount, 2);
}

// ─── Pagination ──────────────────────────────────────────────

function paginate(int $total, int $page, int $per_page = PAGE_SIZE): array {
    $total_pages = max(1, (int)ceil($total / $per_page));
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;
    return [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $per_page,
        'total_pages' => $total_pages,
        'offset'      => $offset,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $total_pages,
    ];
}

function render_pagination(array $pg, string $base_url): string {
    if ($pg['total_pages'] <= 1) return '';
    $sep = str_contains($base_url, '?') ? '&' : '?';
    $html = '<div class="pagination">';
    if ($pg['has_prev']) {
        $html .= '<a href="' . $base_url . $sep . 'page=' . ($pg['page'] - 1) . '">&laquo; Prev</a>';
    }
    // Show up to 7 page links
    $start = max(1, $pg['page'] - 3);
    $end   = min($pg['total_pages'], $pg['page'] + 3);
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pg['page'] ? ' active' : '';
        $html .= '<a href="' . $base_url . $sep . 'page=' . $i . '" class="' . $active . '">' . $i . '</a>';
    }
    if ($pg['has_next']) {
        $html .= '<a href="' . $base_url . $sep . 'page=' . ($pg['page'] + 1) . '">Next &raquo;</a>';
    }
    $html .= '<span class="pg-info">Showing ' . (($pg['page']-1)*$pg['per_page']+1) . '–' . min($pg['total'], $pg['page']*$pg['per_page']) . ' of ' . $pg['total'] . '</span>';
    $html .= '</div>';
    return $html;
}

// ─── Activity Logging ────────────────────────────────────────

function log_activity(
    string $action,
    string $module,
    int $record_id = 0,
    string $description = '',
    ?int $client_id = null,
    int $stage = 1
): void {
    try {
        $user_id = $_SESSION['user_id'] ?? 0;
        db_query(
            "INSERT INTO activities (user_id, action, module, record_id, description, client_id, stage, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$user_id, $action, $module, $record_id, $description, $client_id, $stage, $_SERVER['REMOTE_ADDR'] ?? '']
        );
    } catch (Throwable $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}

// Log a field change (stage 3)
function log_change(string $module, int $record_id, string $field, $old_val, $new_val, ?int $client_id = null): void {
    if ($old_val === $new_val) return;
    try {
        $user_id = $_SESSION['user_id'] ?? 0;
        db_query(
            "INSERT INTO activity_changes (user_id, module, record_id, field_name, old_value, new_value, client_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$user_id, $module, $record_id, $field, (string)$old_val, (string)$new_val, $client_id]
        );
    } catch (Throwable $e) {
        error_log('Change log failed: ' . $e->getMessage());
    }
}

// ─── Notifications ───────────────────────────────────────────

function create_notification(int $user_id, string $title, string $message, string $type = 'info', ?string $link = null, ?int $client_id = null): void {
    try {
        db_query(
            "INSERT INTO notifications (user_id, title, message, type, link, client_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$user_id, $title, $message, $type, $link, $client_id]
        );
    } catch (Throwable $e) {
        error_log('Notification create failed: ' . $e->getMessage());
    }
}

function get_unread_notification_count(int $user_id): int {
    return (int)db_val("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$user_id]);
}

// ─── File Uploads ────────────────────────────────────────────

function handle_file_upload(array $file, string $subfolder = ''): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error code: ' . $file['error']];
    }
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'error' => 'File too large. Maximum size: ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_TYPES, true)) {
        return ['success' => false, 'error' => 'File type not allowed: .' . $ext];
    }
    // Validate MIME
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed_mimes = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png',  'gif'  => 'image/gif',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv'  => 'text/csv',   'txt'  => 'text/plain',
        'zip'  => 'application/zip',
    ];
    if (isset($allowed_mimes[$ext]) && $mime !== $allowed_mimes[$ext]) {
        // Allow text/plain for csv too
        if (!($ext === 'csv' && $mime === 'text/plain')) {
            return ['success' => false, 'error' => 'File MIME type mismatch.'];
        }
    }

    $dir = UPLOAD_PATH . ($subfolder ? rtrim($subfolder, '/') . '/' : '');
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
    $filename  = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safe_name;
    $dest      = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }
    return [
        'success'   => true,
        'filename'  => $filename,
        'path'      => $dest,
        'size'      => $file['size'],
        'mime'      => $mime,
        'original'  => $file['name'],
    ];
}

// ─── Validation ──────────────────────────────────────────────

function validate_required(array $data, array $fields): array {
    $errors = [];
    foreach ($fields as $field => $label) {
        if (empty($data[$field]) && $data[$field] !== '0') {
            $errors[] = $label . ' is required.';
        }
    }
    return $errors;
}

function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_phone(string $phone): bool {
    return preg_match('/^\+?[\d\s\-().]{7,20}$/', $phone) === 1;
}

// ─── String Helpers ──────────────────────────────────────────

function slug(string $str): string {
    return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($str)));
}

function truncate(string $str, int $len = 80): string {
    return mb_strlen($str) > $len ? mb_substr($str, 0, $len) . '…' : $str;
}

function status_badge(string $status): string {
    $map = [
        'active'      => 'badge-success',
        'inactive'    => 'badge-secondary',
        'pending'     => 'badge-warning',
        'complete'    => 'badge-success',
        'completed'   => 'badge-success',
        'open'        => 'badge-primary',
        'closed'      => 'badge-dark',
        'expired'     => 'badge-danger',
        'draft'       => 'badge-secondary',
        'paid'        => 'badge-success',
        'unpaid'      => 'badge-danger',
        'partial'     => 'badge-warning',
        'sent'        => 'badge-info',
        'overdue'     => 'badge-danger',
        'cancelled'   => 'badge-dark',
        'in_progress' => 'badge-primary',
        'review'      => 'badge-warning',
        'approved'    => 'badge-success',
        'rejected'    => 'badge-danger',
        'missing'     => 'badge-danger',
        'received'    => 'badge-success',
        'upcoming'    => 'badge-info',
        'escalated'   => 'badge-danger',
    ];
    $class = $map[strtolower($status)] ?? 'badge-secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

// ─── Permission check helper ──────────────────────────────────

function can(string $perm): bool {
    require_once __DIR__ . '/auth.php';
    return user_can($perm);
}

// ─── Custom Fields ───────────────────────────────────────────

function get_custom_fields(string $entity): array {
    return db_rows(
        "SELECT * FROM custom_fields WHERE entity = ? AND is_active = 1 ORDER BY sort_order ASC",
        [$entity]
    );
}

function get_custom_field_values(string $entity, int $record_id): array {
    $rows = db_rows(
        "SELECT cf.slug, cfv.value
         FROM custom_field_values cfv
         JOIN custom_fields cf ON cf.id = cfv.field_id
         WHERE cfv.entity = ? AND cfv.record_id = ?",
        [$entity, $record_id]
    );
    return array_column($rows, 'value', 'slug');
}

function save_custom_fields(string $entity, int $record_id, array $post_data): void {
    $fields = get_custom_fields($entity);
    foreach ($fields as $field) {
        $val = $post_data['cf_' . $field['slug']] ?? null;
        if ($val === null) $val = '';
        // Upsert
        db_query(
            "INSERT INTO custom_field_values (entity, record_id, field_id, value)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$entity, $record_id, $field['id'], (string)$val]
        );
    }
}

function render_custom_field_input(array $field, string $current_value = ''): string {
    $name  = 'cf_' . e($field['slug']);
    $label = e($field['label']);
    $req   = $field['is_required'] ? ' required' : '';
    $val   = e($current_value);

    $html = '<div class="form-group">';
    $html .= '<label>' . $label . ($field['is_required'] ? ' <span class="text-danger">*</span>' : '') . '</label>';

    switch ($field['field_type']) {
        case 'textarea':
            $html .= '<textarea name="' . $name . '" class="form-control"' . $req . '>' . $val . '</textarea>';
            break;
        case 'dropdown':
            $opts = array_filter(explode("\n", $field['options'] ?? ''));
            $html .= '<select name="' . $name . '" class="form-control"' . $req . '>';
            $html .= '<option value="">— Select —</option>';
            foreach ($opts as $opt) {
                $opt = trim($opt);
                $sel = $current_value === $opt ? ' selected' : '';
                $html .= '<option value="' . e($opt) . '"' . $sel . '>' . e($opt) . '</option>';
            }
            $html .= '</select>';
            break;
        case 'checkbox':
            $chk = $current_value ? ' checked' : '';
            $html .= '<label class="checkbox-label"><input type="checkbox" name="' . $name . '" value="1"' . $chk . '> ' . $label . '</label>';
            break;
        case 'date':
            $html .= '<input type="date" name="' . $name . '" class="form-control" value="' . $val . '"' . $req . '>';
            break;
        case 'number':
        case 'integer':
        case 'currency':
            $html .= '<input type="number" name="' . $name . '" class="form-control" value="' . $val . '"' . $req . '>';
            break;
        case 'email':
            $html .= '<input type="email" name="' . $name . '" class="form-control" value="' . $val . '"' . $req . '>';
            break;
        case 'phone':
            $html .= '<input type="tel" name="' . $name . '" class="form-control" value="' . $val . '"' . $req . '>';
            break;
        case 'url':
            $html .= '<input type="url" name="' . $name . '" class="form-control" value="' . $val . '"' . $req . '>';
            break;
        default: // text
            $html .= '<input type="text" name="' . $name . '" class="form-control" value="' . $val . '"' . $req . '>';
    }

    $html .= '</div>';
    return $html;
}

// ─── Bank Statement Automation ───────────────────────────────

function get_missing_bank_statement_months(int $bank_account_id): array {
    $account = db_row("SELECT * FROM bank_accounts WHERE id = ?", [$bank_account_id]);
    if (!$account) return [];

    $service_start = $account['service_start_date'] ?? date('Y-m-01', strtotime('-12 months'));
    $through       = date('Y-m-01'); // through last month

    $start_ts = strtotime($service_start);
    $end_ts   = strtotime('-1 month', strtotime($through));

    // Get received months
    $received = db_rows(
        "SELECT statement_year, statement_month FROM bank_statements
         WHERE bank_account_id = ? AND status = 'received'",
        [$bank_account_id]
    );
    $received_set = [];
    foreach ($received as $r) {
        $received_set[$r['statement_year'] . '-' . str_pad($r['statement_month'], 2, '0', STR_PAD_LEFT)] = true;
    }

    $missing = [];
    $ts = $start_ts;
    while ($ts <= $end_ts) {
        $y = (int)date('Y', $ts);
        $m = (int)date('n', $ts);
        $key = $y . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
        if (!isset($received_set[$key])) {
            $missing[] = ['year' => $y, 'month' => $m, 'key' => $key];
        }
        $ts = strtotime('+1 month', $ts);
    }
    return $missing;
}

function month_name(int $month): string {
    return date('F', mktime(0, 0, 0, $month, 1));
}

// ─── Communication helper ────────────────────────────────────

function provider_configured(string $type): bool {
    return match($type) {
        'email'     => !empty(MAIL_HOST) && !empty(MAIL_USERNAME),
        'sms'       => !empty(SMS_PROVIDER) && !empty(SMS_API_KEY),
        'whatsapp'  => !empty(WHATSAPP_PROVIDER) && !empty(WHATSAPP_API_KEY),
        'voice'     => !empty(VOICE_PROVIDER) && !empty(VOICE_AUTH_TOKEN),
        'ai'        => !empty(AI_PROVIDER) && !empty(AI_API_KEY),
        default     => false,
    };
}

function log_communication(int $client_id, string $channel, string $direction, string $subject, string $body, string $status = 'sent', ?int $user_id = null): int {
    return db_insert(
        "INSERT INTO communications (client_id, user_id, channel, direction, subject, body, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [$client_id, $user_id ?? ($_SESSION['user_id'] ?? null), $channel, $direction, $subject, $body, $status]
    );
}

// CSRF Protection
function csrf_field(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

function verify_csrf(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            die('CSRF token validation failed.');
        }
    }
}

function post_string(string $key, string $default = ''): string {
    return htmlspecialchars(trim($_POST[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
