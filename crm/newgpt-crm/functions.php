<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['_csrf'] ?? '', $sent)) {
        http_response_code(419);
        die('Invalid or expired security token. Please go back and try again.');
    }
}

function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function post_string(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function post_int(string $key, int $default = 0): int
{
    return (int)($_POST[$key] ?? $default);
}

function money(float|int|string $amount): string
{
    return number_format((float)$amount, 2);
}

function status_badge(string $status): string
{
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge badge-' . e($status) . '">' . e($label) . '</span>';
}

function table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
         AND table_name = ?'
    );
    $stmt->execute([$table]);

    return $cache[$table] = (bool)$stmt->fetchColumn();
}

function phase2_ready(PDO $pdo): bool
{
    foreach ([
        'tasks',
        'calendar_events',
        'reminders',
        'invoices',
        'invoice_items',
        'client_comments'
    ] as $table) {
        if (!table_exists($pdo, $table)) {
            return false;
        }
    }

    return true;
}

function phase3_ready(PDO $pdo): bool
{
    foreach ([
        'service_instances',
        'service_step_instances',
        'bank_accounts',
        'bank_statement_periods',
        'automation_rules',
        'automation_logs',
        'documents',
        'notifications'
    ] as $table) {
        if (!table_exists($pdo, $table)) {
            return false;
        }
    }

    return true;
}

function phase4_ready(PDO $pdo): bool
{
    foreach ([
        'payments',
        'document_requirements',
        'client_document_requirements',
        'custom_fields',
        'custom_field_values',
        'secure_data',
        'service_messages'
    ] as $table) {
        if (!table_exists($pdo, $table)) {
            return false;
        }
    }

    return true;
}

function phase5_ready(PDO $pdo): bool
{
    foreach ([
        'quotations',
        'quotation_items',
        'project_comments',
        'task_comments',
        'recurring_project_series',
        'user_preferences',
        'settings'
    ] as $table) {
        if (!table_exists($pdo, $table)) {
            return false;
        }
    }

    return true;
}

function phase6_ready(PDO $pdo): bool
{
    foreach ([
        'password_resets',
        'login_attempts',
        'communication_templates',
        'approvals',
        'exceptions',
        'workflow_checkpoints'
    ] as $table) {
        if (!table_exists($pdo, $table)) {
            return false;
        }
    }

    return true;
}

function notify_user(
    PDO $pdo,
    ?int $userId,
    ?int $clientId,
    string $type,
    string $title,
    string $message
): void {
    if (!table_exists($pdo, 'notifications')) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO notifications
        (user_id, client_id, notification_type, title, message)
        VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $clientId, $type, $title, $message]);
}

function automation_log(
    PDO $pdo,
    string $eventName,
    string $actionName,
    ?int $clientId,
    ?int $instanceId,
    string $status,
    string $message,
    ?int $ruleId = null
): void {
    if (!table_exists($pdo, 'automation_logs')) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO automation_logs
        (rule_id, event_name, action_name, client_id, service_instance_id, status, message)
        VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $ruleId,
        $eventName,
        $actionName,
        $clientId,
        $instanceId,
        $status,
        $message
    ]);
}

function activity(
    PDO $pdo,
    string $action,
    string $module,
    ?int $recordId = null,
    string $description = ''
): void {
    if (!table_exists($pdo, 'activities')) {
        return;
    }

    $userId = $_SESSION['user_id'] ?? null;

    $stmt = $pdo->prepare(
        'INSERT INTO activities
        (user_id, action, module, record_id, description, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $userId,
        $action,
        $module,
        $recordId,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
    ]);
}

function ensure_installed(PDO $pdo): void
{
    try {
        $pdo->query('SELECT 1 FROM users LIMIT 1');
    } catch (Throwable $e) {
        http_response_code(503);
        die(
            'SPS CRM is not initialized. Import crm.sql into the gpt database first.'
        );
    }
}

function sps_encryption_key(): string
{
    $dir = __DIR__ . '/storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $path = $dir . '/.key';
    if (!is_file($path)) {
        file_put_contents($path, base64_encode(random_bytes(32)), LOCK_EX);
        @chmod($path, 0600);
    }
    $key = base64_decode(trim((string)file_get_contents($path)), true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('SPS encryption key is invalid.');
    }
    return $key;
}

function sps_encrypt(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', sps_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Unable to encrypt secure data.');
    }
    return base64_encode($iv . $tag . $cipher);
}

function sps_decrypt(string $encoded): string
{
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 28) {
        throw new RuntimeException('Invalid encrypted data.');
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', sps_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        throw new RuntimeException('Unable to decrypt secure data.');
    }
    return $plain;
}
