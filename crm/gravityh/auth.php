<?php
/**
 * GRAVITY CRM - Authentication & Authorization
 * Phase 1: Foundation
 * 
 * Yahan session authentication, login requirement, role checks
 * aur permission checks handle kiye gaye hain.
 */

require_once __DIR__ . '/db.php';

/**
 * Get the currently logged-in user
 * 
 * @return array|null
 */
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    
    static $user = null;
    static $loaded = false;
    
    if (!$loaded) {
        $user = db_row('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? AND u.status = "active"', [$_SESSION['user_id']]);
        $loaded = true;
    }
    
    return $user;
}

/**
 * Require login - redirect to login page if not authenticated
 */
function require_login(): void {
    if (!current_user()) {
        redirect('login.php');
    }
}

/**
 * Check if the current user has a specific permission
 * 
 * @param string $module
 * @param string $action
 * @return bool
 */
function user_can(string $module, string $action = 'view'): bool {
    $user = current_user();
    if (!$user) {
        return false;
    }
    
    // Administrator (role 1) has full access
    if ((int)$user['role_id'] === 1) {
        return true;
    }
    
    static $permissions = null;
    if ($permissions === null) {
        $rows = db_query(
            'SELECT p.module, p.action FROM permissions p 
             JOIN role_permissions rp ON p.id = rp.permission_id 
             WHERE rp.role_id = ?',
            [$user['role_id']]
        );
        $permissions = [];
        foreach ($rows as $row) {
            $permissions[$row['module']][] = $row['action'];
        }
    }
    
    return isset($permissions[$module]) && in_array($action, $permissions[$module]);
}

/**
 * Require a specific permission - die with access denied if not allowed
 * 
 * @param string $module
 * @param string $action
 */
function require_permission(string $module, string $action = 'view'): void {
    if (!user_can($module, $action)) {
        http_response_code(403);
        die('Access denied. You do not have permission to access this resource.');
    }
}

/**
 * Check if a module is enabled in the Customizer
 * 
 * @param string $module_key
 * @return bool
 */
function module_enabled(string $module_key): bool {
    static $modules = null;
    if ($modules === null) {
        $rows = db_query('SELECT module_key, is_enabled FROM module_configs');
        $modules = [];
        foreach ($rows as $row) {
            $modules[$row['module_key']] = (bool)$row['is_enabled'];
        }
    }
    return $modules[$module_key] ?? true;
}

/**
 * Generate a CSRF token
 * 
 * @return string
 */
function csrf_token(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify a CSRF token
 * 
 * @param string|null $token
 * @return bool
 */
function csrf_verify(?string $token = null): bool {
    $token = $token ?? ($_POST[CSRF_TOKEN_NAME] ?? '');
    return !empty($token) && hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
}

/**
 * Require a valid CSRF token for state-changing requests
 */
function require_csrf(): void {
    if (!csrf_verify()) {
        http_response_code(419);
        die('Invalid or expired security token. Please go back and try again.');
    }
}

/**
 * Log an activity
 * 
 * @param string $module
 * @param string $action
 * @param int|null $record_id
 * @param string|null $description
 * @param bool $is_sensitive
 * @param array $changes Field changes for audit trail
 * @return int Activity ID
 */
function log_activity(string $module, string $action, ?int $record_id = null, ?string $description = null, bool $is_sensitive = false, array $changes = []): int {
    $user = current_user();
    
    db_execute(
        'INSERT INTO activities (user_id, module, action, record_id, description, ip_address, user_agent, is_sensitive) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $user['id'] ?? null,
            $module,
            $action,
            $record_id,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $is_sensitive ? 1 : 0,
        ]
    );
    
    $activity_id = db_last_id();
    
    // Store field changes for audit trail
    foreach ($changes as $field => $change) {
        if (is_array($change) && isset($change['old'], $change['new'])) {
            db_execute(
                'INSERT INTO activity_changes (activity_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?)',
                [$activity_id, $field, (string)$change['old'], (string)$change['new']]
            );
        }
    }
    
    return $activity_id;
}

/**
 * Create a notification for a user
 * 
 * @param int $user_id
 * @param string $title
 * @param string|null $message
 * @param string|null $module
 * @param int|null $record_id
 * @return int
 */
function notify(int $user_id, string $title, ?string $message = null, ?string $module = null, ?int $record_id = null): int {
    db_execute(
        'INSERT INTO notifications (user_id, title, message, module, record_id) VALUES (?, ?, ?, ?, ?)',
        [$user_id, $title, $message, $module, $record_id]
    );
    return db_last_id();
}

/**
 * Get unread notification count for current user
 * 
 * @return int
 */
function unread_notification_count(): int {
    $user = current_user();
    if (!$user) {
        return 0;
    }
    return (int) db_value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$user['id']]);
}

/**
 * Get recent notifications for current user
 * 
 * @param int $limit
 * @return array
 */
function recent_notifications(int $limit = 10): array {
    $user = current_user();
    if (!$user) {
        return [];
    }
    return db_query('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?', [$user['id'], $limit]);
}

/**
 * Get a setting value
 * 
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function get_setting(string $key, $default = null) {
    static $settings = null;
    if ($settings === null) {
        $rows = db_query('SELECT setting_key, setting_value FROM settings');
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings[$key] ?? $default;
}

/**
 * Get the active theme CSS variables
 * 
 * @return array
 */
function active_theme_vars(): array {
    static $vars = null;
    if ($vars === null) {
        $theme = db_row('SELECT css_variables FROM themes WHERE is_active = 1 LIMIT 1');
        $vars = $theme ? json_decode($theme['css_variables'] ?? '{}', true) : [];
    }
    return $vars;
}

/**
 * Get the current user's role layout
 * 
 * @return array|null
 */
function current_role_layout(): ?array {
    $user = current_user();
    if (!$user) {
        return null;
    }
    return db_row('SELECT rl.*, t.name AS theme_name, t.css_variables FROM role_layouts rl LEFT JOIN themes t ON rl.theme_id = t.id WHERE rl.role_id = ?', [$user['role_id']]);
}

/* ============================================================
   PHASE 3 (SPS 2040) - SECURITY & PERMISSION SCOPE SUITE
   Spec §27 (login lockout/rate limit), §28 (stage + data scope),
   §29 (Stage-2 sensitive events w/ severity + session metadata)
   Append-only: no existing function was modified.
   ============================================================ */

/**
 * Client IP (best-effort; IP is never treated as identity per Spec §27)
 */
function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * Integer security setting with sane floor
 */
function sec_int(string $key, int $default): int {
    return max(1, (int) get_setting($key, (string) $default));
}

/* ------------------------------------------------------------
   PERMISSION MAP + DATA SCOPE (Spec §28)
   ROLE -> MODULE -> ACTION -> STAGE -> DATA SCOPE
   ------------------------------------------------------------ */

/**
 * Full permission map for current user incl. data_scope.
 * Shape: [module][action] => 'all'|'team'|'own'
 */
function user_permission_map(): array {
    static $map = null;
    static $loaded = false;

    $user = current_user();
    if (!$user) {
        return [];
    }

    if (!$loaded) {
        $map = [];
        $rows = db_query(
            'SELECT p.module, p.action, rp.data_scope
             FROM permissions p
             JOIN role_permissions rp ON p.id = rp.permission_id
             WHERE rp.role_id = ?',
            [$user['role_id']]
        );
        foreach ($rows as $row) {
            $map[$row['module']][$row['action']] = $row['data_scope'] ?? 'all';
        }
        $loaded = true;
    }

    return $map;
}

/**
 * Team IDs the current user belongs to
 *
 * @return int[]
 */
function current_user_team_ids(): array {
    static $team_ids = null;
    static $loaded = false;

    $user = current_user();
    if (!$user) {
        return [];
    }

    if (!$loaded) {
        $rows = db_query(
            'SELECT team_id FROM team_members WHERE user_id = ?',
            [$user['id']]
        );
        $team_ids = array_map('intval', array_column($rows, 'team_id'));
        $loaded = true;
    }

    return $team_ids;
}

/**
 * Scope-aware permission check (Spec §28 DATA SCOPE dimension).
 * Records without owner/team context pass only when scope is 'all'.
 */
function user_can_scoped(string $module, string $action = 'view', ?int $record_owner_user_id = null, ?int $record_team_id = null): bool {
    $user = current_user();
    if (!$user) {
        return false;
    }

    // Administrator bypass
    if ((int) $user['role_id'] === 1) {
        return true;
    }

    $map = user_permission_map();
    if (!isset($map[$module]) || !array_key_exists($action, $map[$module])) {
        return false;
    }

    $scope = $map[$module][$action];

    if ($scope === 'all') {
        return true;
    }

    if ($scope === 'own') {
        return $record_owner_user_id !== null && (int) $record_owner_user_id === (int) $user['id'];
    }

    if ($scope === 'team') {
        if ($record_team_id !== null) {
            return in_array((int) $record_team_id, current_user_team_ids(), true);
        }
        // Without explicit team context, allow when the user owns the record
        return $record_owner_user_id !== null && (int) $record_owner_user_id === (int) $user['id'];
    }

    return false;
}

/**
 * Require a scope-aware permission; 403 otherwise
 */
function require_permission_scoped(string $module, string $action = 'view', ?int $record_owner_user_id = null, ?int $record_team_id = null): void {
    if (!user_can_scoped($module, $action, $record_owner_user_id, $record_team_id)) {
        http_response_code(403);
        die('Access denied. You do not have permission to access this resource.');
    }
}

/**
 * ROLE VISIBILITY (Spec §28 final dimension).
 * No rows configured for a role = that role may see everyone.
 */
function can_see_role(int $target_role_id): bool {
    $user = current_user();
    if (!$user) {
        return false;
    }
    if ((int) $user['role_id'] === 1 || (int) $target_role_id === (int) $user['role_id']) {
        return true;
    }
    $configured = (int) db_value(
        'SELECT COUNT(*) FROM role_visibility WHERE role_id = ?',
        [$user['role_id']]
    );
    if ($configured === 0) {
        return true;
    }
    return (bool) db_value(
        'SELECT COUNT(*) FROM role_visibility WHERE role_id = ? AND visible_role_id = ?',
        [$user['role_id'], $target_role_id]
    );
}

/* ------------------------------------------------------------
   LOGIN LOCKOUT + RATE LIMITING (Spec §27)
   Account lockout with configurable threshold/duration and
   administrator release. IP throttling is only a generous
   burst guard - shared office IPs must not lock teams out.
   ------------------------------------------------------------ */

/**
 * Is this user account currently locked?
 */
function is_user_locked(?array $user): bool {
    if (!$user || empty($user['locked_until'])) {
        return false;
    }
    return strtotime((string) $user['locked_until']) > time();
}

/**
 * Record failed login against a real account; auto-lock at threshold
 */
function record_failed_login(int $user_id): void {
    db_execute(
        'UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?',
        [$user_id]
    );

    $attempts = (int) db_value(
        'SELECT failed_login_attempts FROM users WHERE id = ?',
        [$user_id]
    );

    $max = sec_int('security_max_failed_attempts', 5);
    $minutes = sec_int('security_lockout_minutes', 15);

    if ($attempts >= $max) {
        db_execute(
            'UPDATE users
             SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
             WHERE id = ? AND (locked_until IS NULL OR locked_until <= NOW())',
            [$minutes, $user_id]
        );

        log_security_event(
            'account_locked',
            "Account locked for {$minutes} minutes after {$attempts} failed attempts.",
            $user_id,
            'critical'
        );

        // Notify active administrators (administrator-release workflow)
        $admins = db_query('SELECT id FROM users WHERE role_id = 1 AND status = \'active\'');
        foreach ($admins as $admin) {
            notify((int) $admin['id'], 'Account locked',
                "A user account was locked after {$attempts} failed attempts. You can release it in Users.",
                'users', $user_id);
        }
    }
}

/**
 * Clear failure counter + lock (successful login or admin release)
 */
function reset_failed_logins(int $user_id): void {
    db_execute(
        'UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?',
        [$user_id]
    );
}

/**
 * Administrator release of a locked account (caller enforces permissions)
 */
function unlock_user_account(int $user_id): bool {
    $existing = db_row('SELECT id, username FROM users WHERE id = ?', [$user_id]);
    if (!$existing) {
        return false;
    }
    reset_failed_logins($user_id);
    log_security_event('account_unlocked', 'Administrator released lock for user: ' . $existing['username'], $user_id);
    return true;
}

/**
 * Persist a login attempt row (IP throttle source + forensics); lazy-prunes old rows
 */
function log_login_attempt(string $username, bool $successful): void {
    try {
        db_execute(
            'INSERT INTO login_attempts (username, ip_address, successful) VALUES (?, ?, ?)',
            [mb_substr($username, 0, 190), client_ip(), $successful ? 1 : 0]
        );
    } catch (Throwable $e) {
        error_log('login_attempts insert failed: ' . $e->getMessage());
    }

    // Probabilistic prune: keep ~48h of history without paying cost every request
    if (random_int(1, 50) === 1) {
        db_execute('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 2 DAY)');
    }
}

/**
 * Generous per-IP failed-attempt guard over a rolling window.
 * Deliberately high threshold: shared office IPs must not lock teams out (Spec §27).
 */
function ip_throttled(string $username): bool {
    $window_minutes = sec_int('security_ip_window_minutes', 60);
    $max = sec_int('security_ip_max_attempts', 30);

    $count = (int) db_value(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = ? AND successful = 0
           AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)',
        [client_ip(), $window_minutes]
    );

    return $count >= $max;
}

/* ------------------------------------------------------------
   STAGE-2 SECURITY EVENTS (Spec §29)
   Sensitive events with severity + session metadata.
   Never logs passwords, tokens or other secrets.
   ------------------------------------------------------------ */

/**
 * Log a Stage-2 security event (activities.is_sensitive = 1)
 *
 * @param string $action e.g. login, logout, login_failed, account_locked
 * @param string $severity info|warning|critical
 * @return int Activity ID
 */
function log_security_event(string $action, string $description = '', ?int $record_id = null, string $severity = 'warning'): int {
    $user = current_user();
    $sid = session_id();

    if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
        $severity = 'warning';
    }

    db_execute(
        'INSERT INTO activities
            (user_id, module, action, record_id, description, ip_address, user_agent, is_sensitive, severity, session_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
        [
            $user['id'] ?? null,
            'auth',
            $action,
            $record_id,
            $description,
            client_ip(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $severity,
            ($sid !== '' ? substr($sid, 0, 128) : null),
        ]
    );

    return db_last_id();
}

/**
 * Resolve the current user's UI theme preferences (Phase 2: Theme Engine).
 *
 * Priority: user cookie (JS-managed, 30 days) -> organization default (settings
 * table) -> built-in default. Cookie values are validated against allow-lists
 * so a tampered cookie can never inject arbitrary attribute values into the
 * <html> tag or the theme CSS scopes.
 *
 * @return array{theme:string,mode:string,density:string,sidebar:string,motion:string}
 */
function ui_preferences(): array {
    $org_mode = get_setting('ui_default_mode', 'auto');
    $prefs = [
        'theme'   => get_setting('ui_default_theme', 'professional'),
        'mode'    => in_array($org_mode, ['light', 'dark', 'auto'], true) ? $org_mode : 'auto',
        'density' => 'standard',
        'sidebar' => 'expanded',
        'motion'  => 'full',
    ];

    $allowed = [
        'theme'   => ['professional', 'aurora', 'sps', 'focus', 'studio'],
        'mode'    => ['light', 'dark', 'auto'],
        'density' => ['compact', 'standard', 'comfortable'],
        'sidebar' => ['expanded', 'collapsed'],
        'motion'  => ['full', 'reduced'],
    ];

    $raw = $_COOKIE['gravity_ui'] ?? '';
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($allowed as $key => $values) {
                if (isset($decoded[$key]) && in_array($decoded[$key], $values, true)) {
                    $prefs[$key] = $decoded[$key];
                }
            }
        }
    }

    return $prefs;
}