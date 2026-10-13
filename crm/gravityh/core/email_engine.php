<?php
/**
 * GRAVITY CRM - Enterprise Email Communication Engine (SPS 2040)
 *
 * Email is a platform capability: manual, scheduled, automated, bulk,
 * inbound, reply-threaded. It reuses the existing durable event bus,
 * outbound queue (idempotent, bounded retry, abandonment -> Exception
 * Engine), permission model and 3-stage audit/activity system. No
 * parallel queue or duplicate engines.
 *
 * Spec: Sec 1-5, 6-14 (providers/senders), 15-18 (templates/versions/
 * variables/context), 22-26 (payment & workflow), 33-37 (status/queue/
 * idempotency/suppression), 44-47, 60-66 (routing/bulk/schedule),
 * 84-88 (cron/events/exceptions/audit), 97-98 (why reasons).
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/event_bus.php';
require_once __DIR__ . '/integrations.php';

/* ---------- vocabularies ---------- */

function em_sources(): array {
    return ['MANUAL','AUTOMATION','WORKFLOW','SYSTEM','AI_ASSISTED','BULK','SCHEDULED','INBOUND','REPLY'];
}
function em_statuses(): array {
    return ['pending','draft','queued','processing','scheduled','sent','delivered','failed','bounced','suppressed','blocked','cancelled'];
}
function em_categories(): array {
    return ['General','Client Welcome','Service Started','Document Request','Document Received','Workflow Update','Deadline','Tax','Immigration','Bookkeeping','Sales Tax','Payroll','Invoice','Payment','Payment Reminder','Receipt','Quotation','Approval','Completion','Renewal','Internal','System'];
}
function em_status_badge(string $s): string {
    $map = ['sent'=>'success','delivered'=>'success','queued'=>'info','processing'=>'info','scheduled'=>'info','draft'=>'secondary','pending'=>'secondary','failed'=>'danger','bounced'=>'danger','blocked'=>'danger','suppressed'=>'warning','cancelled'=>'secondary'];
    return '<span class="badge badge-' . ($map[$s] ?? 'secondary') . '">' . e(ucfirst($s)) . '</span>';
}
function em_valid_email(string $e): bool {
    return filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

/* ---------- providers (multi-account SMTP) ---------- */

function em_provider_rows(): array {
    return db_query('SELECT * FROM email_providers ORDER BY is_default DESC, name ASC');
}
function em_provider_get(int $id): ?array {
    return db_row('SELECT * FROM email_providers WHERE id = ?', [$id]) ?: null;
}
function em_provider_save(array $d): int {
    $p = [
        'name'            => (string)($d['name'] ?? ''),
        'provider_type'   => in_array($d['provider_type'] ?? 'smtp', ['smtp','m365','gmail','internal'], true) ? $d['provider_type'] : 'smtp',
        'smtp_host'       => (string)($d['smtp_host'] ?? ''),
        'smtp_port'       => (int)($d['smtp_port'] ?? 587),
        'smtp_encryption' => in_array($d['smtp_encryption'] ?? 'tls', ['none','tls','ssl'], true) ? $d['smtp_encryption'] : 'tls',
        'smtp_username'   => (string)($d['smtp_username'] ?? ''),
        'from_email'      => (string)($d['from_email'] ?? ''),
        'from_name'       => (string)($d['from_name'] ?? ''),
        'reply_to'        => (string)($d['reply_to'] ?? ''),
        'daily_limit'     => (int)($d['daily_limit'] ?? 500),
        'is_default'      => !empty($d['is_default']) ? 1 : 0,
    ];
    if (!empty($d['smtp_password'])) {
        $p['smtp_password_encrypted'] = encrypt_setting((string)$d['smtp_password']);
    }
    if (!empty($d['id'])) {
        $cur = em_provider_get((int)$d['id']);
        if (!isset($p['smtp_password_encrypted']) && $cur) { $p['smtp_password_encrypted'] = $cur['smtp_password_encrypted']; }
        if (!empty($p['is_default'])) { db_execute('UPDATE email_providers SET is_default = 0'); }
        db_execute(
            'UPDATE email_providers SET name=?,provider_type=?,smtp_host=?,smtp_port=?,smtp_encryption=?,smtp_username=?,smtp_password_encrypted=?,from_email=?,from_name=?,reply_to=?,daily_limit=?,is_default=? WHERE id=?',
            [$p['name'],$p['provider_type'],$p['smtp_host'],$p['smtp_port'],$p['smtp_encryption'],$p['smtp_username'],$p['smtp_password_encrypted'] ?? null,$p['from_email'],$p['from_name'],$p['reply_to'],$p['daily_limit'],$p['is_default'],(int)$d['id']]
        );
        return (int)$d['id'];
    }
    if (!empty($p['is_default'])) { db_execute('UPDATE email_providers SET is_default = 0'); }
    db_execute(
        'INSERT INTO email_providers (name,provider_type,smtp_host,smtp_port,smtp_encryption,smtp_username,smtp_password_encrypted,from_email,from_name,reply_to,daily_limit,is_default) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$p['name'],$p['provider_type'],$p['smtp_host'],$p['smtp_port'],$p['smtp_encryption'],$p['smtp_username'],$p['smtp_password_encrypted'] ?? null,$p['from_email'],$p['from_name'],$p['reply_to'],$p['daily_limit'],$p['is_default']]
    );
    return db_last_id();
}
function em_provider_delete(int $id): void {
    db_execute('UPDATE email_sender_profiles SET provider_id = NULL WHERE provider_id = ?', [$id]);
    db_execute('DELETE FROM email_providers WHERE id = ?', [$id]);
}
function em_provider_default(): ?array {
    return db_row('SELECT * FROM email_providers WHERE is_default = 1 AND enabled = 1 LIMIT 1') ?: null;
}
/** Truthful provider test: connected is only set when a real test passes. */
function em_provider_test(int $id): array {
    $pr = em_provider_get($id);
    if (!$pr) { return [false, 'Provider not found.']; }
    if (isset($GLOBALS['EM_TRANSPORT'])) {
        $res = call_user_func($GLOBALS['EM_TRANSPORT'], $pr, ['test' => true]);
    } elseif (function_exists('test_smtp_socket')) {
        $pass = ($pr['smtp_password_encrypted'] ?? '') !== '' ? (string)decrypt_setting((string)$pr['smtp_password_encrypted']) : '';
        $res = test_smtp_socket((string)$pr['smtp_host'], (int)$pr['smtp_port'], (string)$pr['smtp_encryption'], (string)$pr['smtp_username'], $pass, (string)($pr['from_email'] ?: $pr['smtp_username']));
    } else {
        $res = [false, 'No transport available.'];
    }
    $ok = !empty($res[0] ?? $res['success'] ?? false);
    $msg = (string)($res[1] ?? $res['message'] ?? '');
    db_execute('UPDATE email_providers SET status = ?, last_test_at = NOW(), last_test_ok = ?, last_test_message = ? WHERE id = ?', [$ok ? 'connected' : 'error', $ok ? 1 : 0, $msg, $id]);
    return [$ok, $msg];
}

/* ---------- sender profiles ---------- */

function em_sender_rows(bool $activeOnly = false): array {
    return db_query('SELECT * FROM email_sender_profiles' . ($activeOnly ? ' WHERE status = "active"' : '') . ' ORDER BY is_default DESC, name ASC');
}
function em_sender_get(int $id): ?array {
    return db_row('SELECT * FROM email_sender_profiles WHERE id = ?', [$id]) ?: null;
}
function em_sender_save(array $d): int {
    $s = [
        'name' => (string)($d['name'] ?? ''),
        'purpose' => (string)($d['purpose'] ?? ''),
        'department' => (string)($d['department'] ?? ''),
        'provider_id' => !empty($d['provider_id']) ? (int)$d['provider_id'] : null,
        'from_email' => (string)($d['from_email'] ?? ''),
        'from_name' => (string)($d['from_name'] ?? ''),
        'reply_to' => (string)($d['reply_to'] ?? ''),
        'signature' => (string)($d['signature'] ?? ''),
        'allowed_modules' => (string)($d['allowed_modules'] ?? ''),
        'allowed_roles' => (string)($d['allowed_roles'] ?? ''),
        'status' => ($d['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        'is_default' => !empty($d['is_default']) ? 1 : 0,
    ];
    if (!empty($d['id'])) {
        if (!empty($s['is_default'])) { db_execute('UPDATE email_sender_profiles SET is_default = 0'); }
        db_execute(
            'UPDATE email_sender_profiles SET name=?,purpose=?,department=?,provider_id=?,from_email=?,from_name=?,reply_to=?,signature=?,allowed_modules=?,allowed_roles=?,status=?,is_default=? WHERE id=?',
            [$s['name'],$s['purpose'],$s['department'],$s['provider_id'],$s['from_email'],$s['from_name'],$s['reply_to'],$s['signature'],$s['allowed_modules'],$s['allowed_roles'],$s['status'],$s['is_default'],(int)$d['id']]
        );
        return (int)$d['id'];
    }
    if (!empty($s['is_default'])) { db_execute('UPDATE email_sender_profiles SET is_default = 0'); }
    db_execute(
        'INSERT INTO email_sender_profiles (name,purpose,department,provider_id,from_email,from_name,reply_to,signature,allowed_modules,allowed_roles,status,is_default) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$s['name'],$s['purpose'],$s['department'],$s['provider_id'],$s['from_email'],$s['from_name'],$s['reply_to'],$s['signature'],$s['allowed_modules'],$s['allowed_roles'],$s['status'],$s['is_default']]
    );
    return db_last_id();
}
function em_sender_default(): ?array {
    return db_row('SELECT * FROM email_sender_profiles WHERE is_default = 1 AND status = "active" LIMIT 1') ?: null;
}

/* ---------- sender routing rules ---------- */

function em_route_find(string $module, string $event = '', string $purpose = '', string $service_type = ''): ?array {
    return db_row(
        'SELECT * FROM email_sender_routes WHERE module = ? AND enabled = 1
         ORDER BY (purpose = ?) DESC, (event = ?) DESC, (service_type = ?) DESC, priority ASC LIMIT 1',
        [$module, $purpose, $event, $service_type]
    ) ?: null;
}
function em_sender_resolve(array $ctx): ?array {
    // 1. Explicit sender chosen by an authorized user (highest)
    if (!empty($ctx['sender_profile_id'])) {
        $s = em_sender_get((int)$ctx['sender_profile_id']);
        if ($s && $s['status'] === 'active') { return $s; }
    }
    // 2. Purpose/route (module + event + purpose + service_type)
    if (!empty($ctx['module'])) {
        $route = em_route_find((string)$ctx['module'], (string)($ctx['event'] ?? ''), (string)($ctx['purpose'] ?? ''), (string)($ctx['service_type'] ?? ''));
        if ($route) {
            $s = em_sender_get((int)$route['sender_profile_id']);
            if ($s && $s['status'] === 'active') { return $s; }
        }
    }
    // 3. Department sender
    if (!empty($ctx['department'])) {
        $s = db_row('SELECT * FROM email_sender_profiles WHERE department = ? AND status = "active" ORDER BY is_default DESC LIMIT 1', [(string)$ctx['department']]) ?: null;
        if ($s) { return $s; }
    }
    // 4. Organization default
    return em_sender_default();
}

/* ---------- email templates (message_templates store) ---------- */

function em_template_rows(string $type = 'email'): array {
    return db_query('SELECT * FROM message_templates WHERE template_type = ? ORDER BY updated_at DESC', [$type]);
}
function em_template_get(int $id): ?array {
    return db_row('SELECT * FROM message_templates WHERE id = ?', [$id]) ?: null;
}
/** Save a template. Publishing snapshots the current version. */
function em_template_save(array $d): int {
    $t = [
        'name' => (string)($d['name'] ?? ''),
        'category' => (string)($d['category'] ?? ''),
        'purpose' => (string)($d['purpose'] ?? ''),
        'entity_type' => (string)($d['entity_type'] ?? ''),
        'language' => (string)($d['language'] ?? 'en'),
        'sender_profile_id' => !empty($d['sender_profile_id']) ? (int)$d['sender_profile_id'] : null,
        'subject' => (string)($d['subject'] ?? ''),
        'body' => (string)($d['body'] ?? ''),
        'status' => in_array($d['status'] ?? 'draft', ['draft','published','archived'], true) ? $d['status'] : 'draft',
    ];
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (!empty($d['id'])) {
        $id = (int)$d['id'];
        db_execute(
            'UPDATE message_templates SET name=?,category=?,purpose=?,entity_type=?,language=?,sender_profile_id=?,subject=?,body=?,status=?,updated_at=NOW() WHERE id=?',
            [$t['name'],$t['category'],$t['purpose'],$t['entity_type'],$t['language'],$t['sender_profile_id'],$t['subject'],$t['body'],$t['status'],$id]
        );
        return $id;
    }
    db_execute(
        'INSERT INTO message_templates (name,template_type,category,purpose,entity_type,language,sender_profile_id,subject,body,status,current_version,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$t['name'],'email',$t['category'],$t['purpose'],$t['entity_type'],$t['language'],$t['sender_profile_id'],$t['subject'],$t['body'],$t['status'],1,$uid]
    );
    return db_last_id();
}
function em_template_publish(int $id): void {
    $cur = em_template_get($id);
    if (!$cur) { return; }
    $ver = (int)$cur['current_version'];
    db_execute('INSERT INTO email_template_versions (template_id, version_number, subject, body, changed_by) VALUES (?,?,?,?,?)', [$id, $ver, (string)$cur['subject'], (string)$cur['body'], (int)($_SESSION['user_id'] ?? 0)]);
    db_execute('UPDATE message_templates SET status = "published" WHERE id = ?', [$id]);
}
function em_template_duplicate(int $id): int {
    $cur = em_template_get($id);
    if (!$cur) { return 0; }
    $uid = (int)($_SESSION['user_id'] ?? 0);
    db_execute(
        'INSERT INTO message_templates (name,template_type,category,purpose,entity_type,language,sender_profile_id,subject,body,status,current_version,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$cur['name'] . ' (copy)','email',$cur['category'] ?? '',$cur['purpose'] ?? '',$cur['entity_type'] ?? '',$cur['language'] ?? 'en',$cur['sender_profile_id'],$cur['subject'],$cur['body'],'draft',1,$uid]
    );
    return db_last_id();
}
/** Resolve the best template for context with fallback chain. */
function em_template_resolve(array $ctx, ?int $override = null): ?array {
    if ($override) {
        $t = em_template_get($override);
        if ($t && $t['template_type'] === 'email' && $t['status'] !== 'archived') { return $t; }
    }
    $serviceType = (string)($ctx['service_type'] ?? '');
    $purpose = (string)($ctx['purpose'] ?? '');
    $category = (string)($ctx['category'] ?? '');
    $q = 'SELECT * FROM message_templates WHERE template_type = "email" AND status = "published"';
    $p = [];
    // NOTE: message_templates stores the entity scope in `entity_type` (verified against live schema).
    if ($serviceType !== '') { $q .= ' AND (entity_type = ? OR entity_type = "")'; $p[] = $serviceType; }
    else { $q .= ' AND entity_type = ""'; }
    if ($purpose !== '') { $q .= ' ORDER BY (purpose = ?) DESC, (category = ?) DESC, updated_at DESC LIMIT 1'; $p[] = $purpose; $p[] = $category; }
    else { $q .= ' ORDER BY (category = ?) DESC, updated_at DESC LIMIT 1'; $p[] = $category; }
    $t = db_row($q, $p) ?: null;
    if ($t) { return $t; }
    // System fallback
    return db_row('SELECT * FROM message_templates WHERE template_type = "email" AND status = "published" ORDER BY updated_at DESC LIMIT 1') ?: null;
}

/* ---------- variables (shortcodes) ---------- */

function em_vars_build(array $ctx): array {
    $v = [
        'company' => [
            'name' => (string)get_setting('app_name', 'CRM'),
            'phone' => (string)get_setting('company_phone', ''),
            'email' => (string)get_setting('company_email', ''),
            'website' => (string)get_setting('company_website', ''),
        ],
    ];
    if (!empty($ctx['client_id'])) {
        $c = db_row('SELECT * FROM clients WHERE id = ?', [(int)$ctx['client_id']]);
        if ($c) {
            $first = $c['first_name'] ?? '';
            $last = $c['last_name'] ?? '';
            $v['client'] = [
                'first_name'  => (string)$first,
                'last_name'   => (string)$last,
                'full_name'   => trim($first . ' ' . $last),
                'email'       => (string)($c['email'] ?? ''),
                'phone'       => (string)($c['phone'] ?? ''),
                'status'      => (string)($c['status'] ?? ''),
            ];
        }
    }
    if (!empty($ctx['business_id'])) {
        $b = db_row('SELECT * FROM businesses WHERE id = ?', [(int)$ctx['business_id']]);
        if ($b) { $v['business'] = ['name' => (string)($b['name'] ?? ''), 'address' => (string)($b['address_line1'] ?? ''), 'email' => (string)($b['email'] ?? '')]; }
    }
    if (!empty($ctx['service_id'])) {
        $s = db_row('SELECT * FROM services WHERE id = ?', [(int)$ctx['service_id']]);
        if ($s) { $v['service'] = ['name' => (string)($s['name'] ?? ''), 'category' => (string)($s['category'] ?? ''), 'status' => (string)($s['status'] ?? '')]; }
    }
    if (!empty($ctx['invoice_id'])) {
        $i = db_row('SELECT * FROM invoices WHERE id = ?', [(int)$ctx['invoice_id']]);
        if ($i) {
            $v['invoice'] = [
                'number' => (string)($i['invoice_number'] ?? ''),
                'total' => format_currency((float)($i['total_amount'] ?? 0)),
                'paid_amount' => format_currency((float)($i['amount_paid'] ?? 0)),
                'balance' => format_currency((float)($i['amount_due'] ?? 0)),
                'due_date' => (string)($i['due_date'] ?? ''),
                'status' => (string)($i['status'] ?? ''),
            ];
        }
    }
    if (!empty($ctx['deadline_id'])) {
        $d = db_row('SELECT * FROM deadlines WHERE id = ?', [(int)$ctx['deadline_id']]);
        if ($d) {
            $remaining = isset($d['due_date']) ? (int)ceil((strtotime((string)$d['due_date']) - time()) / 86400) : '';
            $v['deadline'] = ['date' => (string)($d['due_date'] ?? ''), 'days_remaining' => (string)$remaining, 'title' => (string)($d['title'] ?? '')];
        }
    }
    if (isset($ctx['user'])) {
        $u = $ctx['user'];
        $v['assigned_user'] = ['name' => (string)($u['name'] ?? (($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))), 'email' => (string)($u['email'] ?? '')];
    } elseif (!empty($ctx['assigned_user_id'])) {
        $u = db_row('SELECT u.first_name, u.last_name, u.email FROM users u WHERE u.id = ?', [(int)$ctx['assigned_user_id']]);
        if ($u) { $v['assigned_user'] = ['name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')), 'email' => (string)($u['email'] ?? '')]; }
    }
    if (!empty($ctx['payment'])) { $v['payment'] = $ctx['payment']; }
    return $v;
}
function em_vars_render(string $text, array $vars): string {
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function ($m) use ($vars) {
        $parts = explode('.', $m[1]);
        $node = $vars;
        foreach ($parts as $k) {
            if (!is_array($node) || !array_key_exists($k, $node)) { return ''; }
            $node = $node[$k];
        }
        return is_scalar($node) || $node === null ? (string)$node : '';
    }, $text);
}

/* ---------- recipients / suppression / preferences ---------- */

function em_recipient_resolve(array $ctx): ?string {
    if (!empty($ctx['to']) && em_valid_email((string)$ctx['to'])) { return (string)$ctx['to']; }
    if (!empty($ctx['client_id'])) {
        $c = db_row('SELECT email FROM clients WHERE id = ?', [(int)$ctx['client_id']]);
        if ($c && em_valid_email((string)$c['email'])) { return (string)$c['email']; }
    }
    if (!empty($ctx['business_id'])) {
        $b = db_row('SELECT email FROM businesses WHERE id = ?', [(int)$ctx['business_id']]);
        if ($b && em_valid_email((string)$b['email'])) { return (string)$b['email']; }
    }
    return null;
}
function em_suppressed(string $email): bool {
    return (bool)db_value('SELECT COUNT(*) FROM email_suppressions WHERE email_address = ?', [mb_strtolower(trim($email))]);
}
function em_pref_denies(?int $clientId, string $category): bool {
    if (!$clientId || $category === '') { return false; }
    $policy = (string)get_setting('email_suppression_policy', 'marketing_block_only');
    if ($policy === 'marketing_block_only' && $category !== 'marketing') { return false; }
    return (bool)db_value('SELECT COUNT(*) FROM email_preferences WHERE client_id = ? AND category = ? AND allow = 0', [$clientId, $category]);
}
/** Guard an outbound against stale state (e.g. paid invoices). */
function em_guard_sendable(array $ctx): array {
    if (!empty($ctx['invoice_id']) && !empty($ctx['purpose']) && preg_match('/reminder|overdue|due/i', (string)$ctx['purpose'])) {
        $i = db_row('SELECT * FROM invoices WHERE id = ?', [(int)$ctx['invoice_id']]);
        if ($i) {
            $bal = (float)($i['amount_due'] ?? 0);
            $st = (string)($i['status'] ?? '');
            if ($bal <= 0 || in_array($st, ['paid', 'cancelled'], true)) {
                return ['ok' => false, 'reason' => 'Invoice already ' . ($bal <= 0 ? 'paid' : $st) . ' (balance ' . format_currency($bal) . ')'];
            }
        }
    }
    return ['ok' => true, 'reason' => ''];
}
/* ---------- compose / send ---------- */

/** Insert a sent_messages record; returns new id. */
function em_insert_message(array $p, string $status, ?string $idem, string $fromEmail, string $fromName, string $replyTo, ?int $senderId, ?int $providerId, string $subject, string $body): int {
    db_execute(
        'INSERT INTO sent_messages (message_type,template_id,client_id,user_id,recipient,from_email,from_name,cc,bcc,reply_to,subject,body,status,provider,direction,source,sender_profile_id,provider_id,business_id,service_id,project_id,invoice_id,deadline_id,template_version,automation_rule_id,event_name,idempotency_key,sent_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            'email',
            !empty($p['template_id']) ? (int)$p['template_id'] : null,
            !empty($p['client_id']) ? (int)$p['client_id'] : null,
            !empty($p['user_id']) ? (int)$p['user_id'] : (isset($_SESSION['user_id']) && $_SESSION['user_id'] ? (int)$_SESSION['user_id'] : null),
            (string)$p['recipient'],
            $fromEmail,
            $fromName,
            (string)($p['cc'] ?? ''),
            (string)($p['bcc'] ?? ''),
            $replyTo,
            $subject,
            $body,
            $status,
            '',
            'outbound',
            $p['source'] ?? 'MANUAL',
            $senderId,
            $providerId,
            !empty($p['business_id']) ? (int)$p['business_id'] : null,
            !empty($p['service_id']) ? (int)$p['service_id'] : null,
            !empty($p['project_id']) ? (int)$p['project_id'] : null,
            !empty($p['invoice_id']) ? (int)$p['invoice_id'] : null,
            !empty($p['deadline_id']) ? (int)$p['deadline_id'] : null,
            !empty($p['template_id']) ? (int)$p['template_version'] : null,
            !empty($p['automation_rule_id']) ? (int)$p['automation_rule_id'] : null,
            (string)($p['event_name'] ?? ''),
            $idem,
            (int)($_SESSION['user_id'] ?? 0),
        ]
    );
    return db_last_id();
}

/**
 * Compose + dispatch an email. Returns descriptive result array.
 * mode: queued | scheduled | draft | blocked | suppressed | duplicate
 */
function em_compose(array $params): array {
    $recipient = (string)em_recipient_resolve($params);
    if ($recipient === '' || !em_valid_email($recipient)) {
        return ['id' => 0, 'mode' => 'blocked', 'reason' => 'Invalid or missing recipient email.'];
    }
    $params['recipient'] = $recipient;

    $guard = em_guard_sendable($params);
    if (!$guard['ok']) {
        return ['id' => 0, 'mode' => 'blocked', 'reason' => $guard['reason']];
    }

    $sender = em_sender_resolve($params);
    $provider = ($sender && !empty($sender['provider_id'])) ? em_provider_get((int)$sender['provider_id']) : em_provider_default();

    $tpl = em_template_resolve($params, !empty($params['template_id']) ? (int)$params['template_id'] : null);
    $vars = em_vars_build($params);
    $subject = (isset($params['subject']) && $params['subject'] !== '') ? (string)$params['subject'] : (string)($tpl['subject'] ?? '');
    $body = (isset($params['body']) && $params['body'] !== '') ? (string)$params['body'] : (string)($tpl['body'] ?? '');
    if ($sender && !empty($sender['signature'])) { $body .= "<br><div class=\"signature\">" . $sender['signature'] . "</div>"; }
    $subject = em_vars_render($subject, $vars);
    $body = em_vars_render($body, $vars);
    if (trim(strip_tags($subject)) === '' && trim(strip_tags($body)) === '') {
        return ['id' => 0, 'mode' => 'blocked', 'reason' => 'No subject/body and no usable template resolved.'];
    }

    // deterministic idempotency key (prevents duplicate automation sends)
    $idem = $params['idempotency_key'] ?? ('email|' . md5(json_encode([
        $params['source'] ?? 'MANUAL', $params['automation_rule_id'] ?? 0, $params['template_id'] ?? 0,
        $tpl['current_version'] ?? 1, $recipient, $params['event_name'] ?? '', $params['client_id'] ?? 0,
        date('Y-m-d')
    ])));
    if (empty($params['allow_duplicate']) && $idem !== '') {
        $existing = db_value('SELECT id FROM sent_messages WHERE idempotency_key = ?', [$idem]);
        if ($existing) { return ['id' => (int)$existing, 'mode' => 'queued', 'reason' => 'Duplicate suppressed (already queued/sent).', 'duplicate' => true]; }
    }

    $category = (string)($params['category'] ?? 'general');
    if (em_suppressed($recipient)) { return ['id' => 0, 'mode' => 'suppressed', 'reason' => 'Recipient is suppressed.']; }
    if (em_pref_denies(!empty($params['client_id']) ? (int)$params['client_id'] : null, $category)) {
        return ['id' => 0, 'mode' => 'suppressed', 'reason' => 'Client preference blocks ' . $category . ' email.'];
    }

    $senderId = $sender ? (int)$sender['id'] : null;
    $providerId = $provider ? (int)$provider['id'] : null;
    $fromEmail = ($sender && $sender['from_email']) ? $sender['from_email'] : (string)($provider['from_email'] ?? '');
    $fromName = ($sender && $sender['from_name']) ? $sender['from_name'] : (string)($provider['from_name'] ?? '');
    $replyTo = ($sender && $sender['reply_to']) ? $sender['reply_to'] : (string)($provider['reply_to'] ?? '');
    $source = in_array(($params['source'] ?? 'MANUAL'), em_sources(), true) ? $params['source'] : 'MANUAL';
    $params['source'] = $source;
    $params['template_version'] = $tpl['current_version'] ?? null;

    // SCHEDULED (future) -> Scheduling Center row only; cron will dispatch.
    if (!empty($params['schedule_at']) && strtotime((string)$params['schedule_at']) > time()) {
        $sendAt = date('Y-m-d H:i:s', strtotime((string)$params['schedule_at']));
        db_execute(
            'INSERT INTO email_schedules (client_id,business_id,service_id,project_id,invoice_id,template_id,sender_profile_id,recipient,subject,body,send_at,status,source,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                !empty($params['client_id']) ? (int)$params['client_id'] : null,
                !empty($params['business_id']) ? (int)$params['business_id'] : null,
                !empty($params['service_id']) ? (int)$params['service_id'] : null,
                !empty($params['project_id']) ? (int)$params['project_id'] : null,
                !empty($params['invoice_id']) ? (int)$params['invoice_id'] : null,
                !empty($params['template_id']) ? (int)$params['template_id'] : null,
                $senderId,
                $recipient,
                $subject,
                $body,
                $sendAt,
                'scheduled',
                $source,
                (int)($_SESSION['user_id'] ?? 0),
            ]
        );
        $sid = db_last_id();
        bus_emit(EVT_EMAIL_SCHEDULED, ['schedule_id' => $sid, 'recipient' => $recipient, 'subject' => $subject], ['source' => 'email_schedule', 'source_id' => $sid, 'client_id' => !empty($params['client_id']) ? (int)$params['client_id'] : null]);
        return ['id' => $sid, 'mode' => 'scheduled', 'reason' => 'Scheduled for ' . $sendAt];
    }

    // DRAFT
    if (!empty($params['save_draft'])) {
        $mid = em_insert_message($params, 'draft', null, $fromEmail, $fromName, $replyTo, $senderId, $providerId, $subject, $body);
        return ['id' => $mid, 'mode' => 'draft', 'reason' => 'Saved as draft'];
    }

    // IMMEDIATE -> queued + outbound queue (idempotent)
    $mid = em_insert_message($params, 'queued', $idem, $fromEmail, $fromName, $replyTo, $senderId, $providerId, $subject, $body);
    int_enqueue_outbound('email', 'send_email', ['message_id' => $mid, 'attachments' => $params['attachments'] ?? []], $idem);
    bus_emit(EVT_EMAIL_QUEUED, ['message_id' => $mid, 'recipient' => $recipient, 'subject' => $subject], ['source' => 'sent_message', 'source_id' => $mid, 'client_id' => !empty($params['client_id']) ? (int)$params['client_id'] : null]);
    return ['id' => $mid, 'mode' => 'queued', 'reason' => 'Queued for delivery'];
}

/**
 * Outbound-queue deliverer for provider_key 'email'. Returns [true, msg]
 * on success; throws RuntimeException on failure so the shared queue layer
 * manages bounded retries and abandonment -> Exception Engine.
 */
function em_send_from_queue(array $payload): array {
    $mid = (int)($payload['message_id'] ?? 0);
    $att = $payload['attachments'] ?? [];
    $m = db_row('SELECT * FROM sent_messages WHERE id = ?', [$mid]);
    if (!$m) { return [true, 'Message not found; treated as delivered.']; }
    if (in_array($m['status'], ['sent', 'delivered'], true)) { return [true, 'Already sent.']; }

    $provider = $m['provider_id'] ? em_provider_get((int)$m['provider_id']) : em_provider_default();
    if (!$provider) { throw new RuntimeException('No email provider configured for sending.'); }
    $sender = $m['sender_profile_id'] ? em_sender_get((int)$m['sender_profile_id']) : em_sender_default();

    $params = [
        'to'          => (string)$m['recipient'],
        'cc'          => (string)($m['cc'] ?? ''),
        'bcc'         => (string)($m['bcc'] ?? ''),
        'reply_to'    => (string)($m['reply_to'] ?? ($provider['reply_to'] ?? '')),
        'subject'     => (string)$m['subject'],
        'body'        => (string)$m['body'],
        'from_email'  => (string)($sender && $sender['from_email'] ? $sender['from_email'] : ($m['from_email'] ?: ($provider['from_email'] ?: $provider['smtp_username']))),
        'from_name'   => (string)($sender && $sender['from_name'] ? $sender['from_name'] : ($m['from_name'] ?: ($provider['from_name'] ?? ''))),
        'attachments' => $att,
        'message_id'  => $mid,
        'client_id'   => !empty($m['client_id']) ? (int)$m['client_id'] : null,
        'project_id'  => !empty($m['project_id']) ? (int)$m['project_id'] : null,
    ];

    db_execute("UPDATE sent_messages SET status = 'processing', attempts = attempts + 1 WHERE id = ?", [$mid]);
    $res = em_send_transport($provider, $params);
    $ok = !empty($res[0] ?? $res['success'] ?? false);
    $msg = (string)($res[1] ?? $res['message'] ?? '');
    if (!$ok) {
        db_execute("UPDATE sent_messages SET status = 'failed', error_message = ?, provider = ? WHERE id = ?", [mb_substr($msg, 0, 500), (string)($provider['name'] ?? ''), $mid]);
        throw new RuntimeException($msg);
    }
    $providerMsgId = (string)($res['message_id'] ?? ($res[2] ?? ''));
    db_execute("UPDATE sent_messages SET status = 'sent', sent_at = NOW(), delivered_at = NOW(), error_message = NULL, provider = ? WHERE id = ?", [(string)($provider['name'] ?? ''), $mid]);
    bus_emit(EVT_EMAIL_SENT, ['message_id' => $mid, 'recipient' => (string)$m['recipient'], 'subject' => (string)$m['subject']], ['source' => 'sent_message', 'source_id' => $mid, 'client_id' => !empty($m['client_id']) ? (int)$m['client_id'] : null]);
    em_record_activity($m, 'sent');
    return [true, 'Sent' . ($providerMsgId !== '' ? ' (provider id ' . $providerMsgId . ')' : '')];
}

/** Transport gateway: honors the offline EM_TRANSPORT test hook first. */
function em_send_transport(array $provider, array $params): array {
    if (isset($GLOBALS['EM_TRANSPORT'])) {
        return call_user_func($GLOBALS['EM_TRANSPORT'], $provider, $params);
    }
    return em_smtp_send($provider, (string)$params['to'], (string)$params['subject'], (string)$params['body'], $params);
}

/** RFC-ish "Name <email>" formatting. */
function em_format_header_email(string $email, string $name): string {
    $name = trim($name);
    return $name === '' ? $email : "=?UTF-8?B?" . base64_encode($name) . "?= <" . $email . ">";
}

/**
 * Self-contained SMTP transport (per-provider credentials, CC/BCC,
 * Reply-To, HTML body + optional MIME attachments). Returns [ok,msg].
 */
function em_smtp_send(array $provider, string $to, string $subject, string $htmlBody, array $opts = []): array {
    $host = (string)($provider['smtp_host'] ?? '');
    $port = (int)($provider['smtp_port'] ?? 587);
    $enc = (string)($provider['smtp_encryption'] ?? 'tls');
    $user = (string)($provider['smtp_username'] ?? '');
    $pass = ($provider['smtp_password_encrypted'] ?? '') !== '' && $provider['smtp_password_encrypted'] !== null ? (string)decrypt_setting((string)$provider['smtp_password_encrypted']) : '';
    $fromEmail = (string)($opts['from_email'] ?? $provider['from_email'] ?? $user);
    $fromName = (string)($opts['from_name'] ?? $provider['from_name'] ?? '');
    $cc = (string)($opts['cc'] ?? '');
    $bcc = (string)($opts['bcc'] ?? '');
    $replyTo = (string)($opts['reply_to'] ?? $provider['reply_to'] ?? '');
    $attachments = $opts['attachments'] ?? [];
    if ($host === '' || $fromEmail === '') { return [false, 'Provider SMTP host/from not configured.']; }

    $fp = @fsockopen(($enc === 'ssl' ? 'ssl://' : '') . $host, $port, $errno, $errstr, 20);
    if (!$fp) { return [false, 'SMTP connect failed: ' . $errstr]; }
    stream_set_timeout($fp, 20);
    if (!em_smtp_expect($fp, 220, $greet)) { fclose($fp); return [false, 'SMTP greeting failed: ' . trim($greet)]; }
    fwrite($fp, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
    em_smtp_expect($fp, 250, $ehlo);
    if ($enc === 'tls') {
        fwrite($fp, "STARTTLS\r\n");
        if (!em_smtp_expect($fp, 220, $s)) { fclose($fp); return [false, 'STARTTLS rejected: ' . trim($s)]; }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return [false, 'TLS negotiation failed.']; }
        fwrite($fp, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
        em_smtp_expect($fp, 250, $ehlo2);
    }
    if ($user !== '') {
        fwrite($fp, "AUTH LOGIN\r\n");
        if (!em_smtp_expect($fp, 334, $a1)) { fclose($fp); return [false, 'AUTH not accepted: ' . trim($a1)]; }
        fwrite($fp, base64_encode($user) . "\r\n");
        if (!em_smtp_expect($fp, 334, $a2)) { fclose($fp); return [false, 'AUTH username rejected: ' . trim($a2)]; }
        fwrite($fp, base64_encode($pass) . "\r\n");
        if (!em_smtp_expect($fp, 235, $a3)) { fclose($fp); return [false, 'AUTH failed (invalid credentials): ' . trim($a3)]; }
    }
    $rcpts = array_values(array_filter(array_merge([$to], $cc !== '' ? preg_split('/[;,\s]+/', $cc) : [], $bcc !== '' ? preg_split('/[;,\s]+/', $bcc) : [])));
    fwrite($fp, "MAIL FROM:<" . $fromEmail . ">\r\n");
    if (!em_smtp_expect($fp, 250, $m1)) { fclose($fp); return [false, 'MAIL FROM rejected: ' . trim($m1)]; }
    foreach ($rcpts as $rcpt) {
        $rcpt = trim($rcpt);
        if ($rcpt === '' || !em_valid_email($rcpt)) { continue; }
        fwrite($fp, "RCPT TO:<" . $rcpt . ">\r\n");
        if (!em_smtp_expect($fp, 250, $r1)) { fclose($fp); return [false, 'RCPT TO rejected (' . $rcpt . '): ' . trim($r1)]; }
    }
    fwrite($fp, "DATA\r\n");
    if (!em_smtp_expect($fp, 354, $d1)) { fclose($fp); return [false, 'DATA rejected: ' . trim($d1)]; }

    $boundary = '----=_GRAVITY_' . md5(uniqid('', true));
    $headers = "From: " . em_format_header_email($fromEmail, $fromName) . "\r\n";
    $headers .= "To: " . $to . "\r\n";
    if ($cc !== '') { $headers .= "Cc: " . $cc . "\r\n"; }
    if ($replyTo !== '') { $headers .= "Reply-To: " . $replyTo . "\r\n"; }
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "Date: " . date('r') . "\r\nMIME-Version: 1.0\r\n";
    if (!empty($attachments)) {
        $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n\r\n";
        $body = "--" . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $htmlBody . "\r\n";
        foreach ($attachments as $a) {
            $name = (string)($a['name'] ?? 'file');
            $data = (string)($a['data'] ?? '');
            $mime = (string)($a['mime'] ?? 'application/octet-stream');
            $body .= "--" . $boundary . "\r\nContent-Type: " . $mime . "; name=\"" . $name . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"" . $name . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode($data)) . "\r\n";
        }
        $body .= "--" . $boundary . "--\r\n";
    } else {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body = $htmlBody;
    }
    $headersBody = preg_replace("/(^|\r\n)\./", "$1..", $headers . "\r\n" . $body);
    fwrite($fp, $headersBody . "\r\n.\r\n");
    if (!em_smtp_expect($fp, 250, $d2)) { fclose($fp); return [false, 'Message data rejected: ' . trim($d2)]; }
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return [true, 'Message accepted by SMTP (' . $host . ').'];
}
function em_smtp_expect($fp, int $code, ?string &$resp): bool {
    $resp = '';
    while (($line = fgets($fp, 515)) !== false) {
        $resp .= $line;
        if (isset($line[3]) && $line[3] === ' ') { break; }
    }
    return (int)substr($resp, 0, 3) === $code;
}

/** Business-visible activity + communications timeline entry. */
function em_record_activity(array $m, string $action): void {
    try { log_activity('email', $action === 'sent' ? 'send' : 'receive', (int)$m['id'], 'Email ' . $action . ' to/from ' . ($m['recipient'] ?? '') . ': ' . ($m['subject'] ?? ''), false); }
    catch (Throwable $e) { error_log($e->getMessage()); }
    try {
        $clientId = !empty($m['client_id']) ? (int)$m['client_id'] : 0;
        if ($clientId > 0) {
            log_communication($clientId, !empty($m['project_id']) ? (int)$m['project_id'] : null, 'email', 'outbound', (string)($m['subject'] ?? ''), strip_tags((string)($m['body'] ?? '')), 'sent', null, 0, (int)($_SESSION['user_id'] ?? 0));
        }
    } catch (Throwable $e) { error_log($e->getMessage()); }
}

/* ---------- scheduling / bulk / inbound / reporting ---------- */

function em_process_schedules(int $limit = 50): array {
    $report = ['sent' => 0, 'skipped' => 0];
    $rows = db_query("SELECT * FROM email_schedules WHERE status = 'scheduled' AND send_at <= NOW() ORDER BY send_at ASC LIMIT " . max(1, (int)$limit));
    foreach ($rows as $s) {
        $params = [
            'client_id' => $s['client_id'], 'business_id' => $s['business_id'], 'service_id' => $s['service_id'],
            'project_id' => $s['project_id'], 'invoice_id' => $s['invoice_id'], 'template_id' => $s['template_id'],
            'sender_profile_id' => $s['sender_profile_id'], 'to' => $s['recipient'], 'subject' => $s['subject'],
            'body' => $s['body'], 'source' => $s['source'] ?: 'SCHEDULED',
            'idempotency_key' => 'sched|' . $s['id'], 'purpose' => 'scheduled',
        ];
        $r = em_compose($params);
        db_execute("UPDATE email_schedules SET status = ? WHERE id = ?", [$r['mode'] === 'queued' ? 'sent' : 'failed', (int)$s['id']]);
        $report[$r['mode'] === 'queued' ? 'sent' : 'skipped']++;
        if ($r['mode'] !== 'queued' && !empty($r['reason'])) { db_execute("UPDATE email_schedules SET body = CONCAT(COALESCE(body,''), ' [', ?, ']') WHERE id = ?", [$r['reason'], (int)$s['id']]); }
    }
    return $report;
}
function em_schedule_cancel(int $id): void {
    db_execute("UPDATE email_schedules SET status = 'cancelled' WHERE id = ? AND status = 'scheduled'", [$id]);
    bus_emit(EVT_EMAIL_CANCELLED, ['schedule_id' => $id], ['source' => 'email_schedule', 'source_id' => $id]);
}
function em_bulk_preview(array $clientIds, int $templateId): array {
    $valid = array_filter($clientIds, fn($c) => (int)$c > 0);
    $emailable = 0; $suppressed = 0; $invalid = 0;
    foreach ($valid as $cid) {
        $c = db_row('SELECT email FROM clients WHERE id = ?', [(int)$cid]);
        if ($c && em_valid_email((string)$c['email'])) { em_suppressed((string)$c['email']) ? $suppressed++ : $emailable++; }
        else { $invalid++; }
    }
    return ['client_count' => count($valid), 'emailable' => $emailable, 'suppressed' => $suppressed, 'invalid' => $invalid];
}
function em_bulk_send(array $clientIds, array $cfg): array {
    $sent = 0; $blocked = 0;
    foreach ($clientIds as $cid) {
        $params = array_merge($cfg, ['client_id' => (int)$cid, 'source' => 'BULK', 'purpose' => $cfg['purpose'] ?? 'bulk']);
        $r = em_compose($params);
        if (in_array($r['mode'], ['queued', 'scheduled'], true)) { $sent++; } else { $blocked++; }
    }
    return ['sent' => $sent, 'blocked' => $blocked];
}
function em_msg_rows(array $f = []): array {
    $q = 'SELECT sm.*, c.first_name, c.last_name FROM sent_messages sm LEFT JOIN clients c ON sm.client_id = c.id WHERE 1=1';
    $p = [];
    if (!empty($f['status'])) { $q .= ' AND sm.status = ?'; $p[] = $f['status']; }
    if (!empty($f['source'])) { $q .= ' AND sm.source = ?'; $p[] = $f['source']; }
    if (!empty($f['client_id'])) { $q .= ' AND sm.client_id = ?'; $p[] = (int)$f['client_id']; }
    if (!empty($f['invoice_id'])) { $q .= ' AND sm.invoice_id = ?'; $p[] = (int)$f['invoice_id']; }
    if (!empty($f['q'])) { $q .= ' AND (sm.subject LIKE ? OR sm.recipient LIKE ?)'; $p[] = '%' . $f['q'] . '%'; $p[] = '%' . $f['q'] . '%'; }
    $q .= ' ORDER BY sm.id DESC LIMIT 200';
    return db_query($q, $p);
}
function em_msg_get(int $id): ?array {
    return db_row('SELECT sm.*, c.first_name, c.last_name FROM sent_messages sm LEFT JOIN clients c ON sm.client_id = c.id WHERE sm.id = ?', [$id]) ?: null;
}
function em_schedule_rows(array $f = []): array {
    $q = 'SELECT * FROM email_schedules WHERE 1=1'; $p = [];
    if (!empty($f['status'])) { $q .= ' AND status = ?'; $p[] = $f['status']; }
    if (!empty($f['q'])) { $q .= ' AND (subject LIKE ? OR recipient LIKE ?)'; $p[] = '%' . $f['q'] . '%'; $p[] = '%' . $f['q'] . '%'; }
    $q .= ' ORDER BY send_at DESC LIMIT 150';
    return db_query($q, $p);
}
function em_thread_rows(array $f = []): array {
    $q = 'SELECT * FROM email_threads WHERE 1=1'; $p = [];
    if (!empty($f['client_id'])) { $q .= ' AND client_id = ?'; $p[] = (int)$f['client_id']; }
    if (!empty($f['status'])) { $q .= ' AND status = ?'; $p[] = $f['status']; }
    $q .= ' ORDER BY last_activity_at DESC LIMIT 150';
    return db_query($q, $p);
}
/** Inbound ingestion + client matching + threading. */
function em_inbound_store(array $mail): array {
    $from = mb_strtolower(trim((string)($mail['from'] ?? '')));
    $subject = (string)($mail['subject'] ?? '');
    $body = (string)($mail['body'] ?? '');
    $clientId = !empty($mail['client_id']) ? (int)$mail['client_id'] : 0;
    if (!$clientId) {
        $clientId = (int)db_value('SELECT id FROM clients WHERE LOWER(email) = ?', [$from]);
        if (!$clientId) { $clientId = (int)db_value('SELECT client_id FROM contacts WHERE LOWER(email) = ? LIMIT 1', [$from]); }
    }
    $match = $clientId ? 'matched' : 'unmatched';
    $threadId = 0;
    if ($clientId) {
        $norm = mb_substr(preg_replace('/^re:\s*/i', '', $subject), 0, 120);
        $thread = db_row('SELECT * FROM email_threads WHERE client_id = ? AND subject = ? ORDER BY id DESC LIMIT 1', [$clientId, $norm]);
        if ($thread) { $threadId = (int)$thread['id']; db_execute('UPDATE email_threads SET last_activity_at = NOW(), status = "waiting_staff" WHERE id = ?', [$threadId]); }
        else {
            db_execute('INSERT INTO email_threads (client_id,subject,participants,status,last_activity_at) VALUES (?,?,?,?,NOW())', [$clientId, $norm, $from, 'waiting_staff']);
            $threadId = db_last_id();
        }
    }
    db_execute('INSERT INTO sent_messages (message_type,client_id,recipient,from_email,subject,body,status,direction,source,thread_id) VALUES (?,?,?,?,?,?,?,?,?,?)', ['email', $clientId ?: null, $from, (string)($mail['to'] ?? ''), $subject, $body, 'received', 'inbound', 'INBOUND', $threadId ?: null]);
    $mid = db_last_id();
    bus_emit(EVT_EMAIL_RECEIVED, ['message_id' => $mid, 'from' => $from, 'subject' => $subject, 'match' => $match], ['source' => 'sent_message', 'source_id' => $mid, 'client_id' => $clientId ?: null]);
    return ['message_id' => $mid, 'thread_id' => $threadId, 'match' => $match];
}
function em_report(): array {
    return [
        'sent' => (int)db_value("SELECT COUNT(*) FROM sent_messages WHERE status IN ('sent','delivered') AND direction = 'outbound'"),
        'queued' => (int)db_value("SELECT COUNT(*) FROM sent_messages WHERE status = 'queued'"),
        'failed' => (int)db_value("SELECT COUNT(*) FROM sent_messages WHERE status = 'failed'"),
        'scheduled' => (int)db_value("SELECT COUNT(*) FROM email_schedules WHERE status = 'scheduled'"),
        'automated' => (int)db_value("SELECT COUNT(*) FROM sent_messages WHERE source IN ('AUTOMATION','WORKFLOW','SCHEDULED','BULK')"),
        'suppressed' => (int)db_value('SELECT COUNT(*) FROM email_suppressions'),
        'threads_open' => (int)db_value("SELECT COUNT(*) FROM email_threads WHERE status IN ('open','waiting_client','waiting_staff')"),
    ];
}
/** "Why didn't this email send?" diagnostic (spec Sec 97). */
function em_why_blocked(array $params): array {
    $recipient = (string)em_recipient_resolve($params);
    $guard = em_guard_sendable($params);
    $sender = em_sender_resolve($params);
    $tpl = em_template_resolve($params, !empty($params['template_id']) ? (int)$params['template_id'] : null);
    return [
        'Automation matched' => ['recipient' => !empty($params['to']) || ($params['client_id'] ?? 0) > 0],
        'Recipient found' => ['found' => $recipient !== '', 'email' => $recipient],
        'Recipient valid' => ['valid' => $recipient !== '' && em_valid_email($recipient)],
        'Invoice balance' => ['ok' => $guard['ok'], 'details' => $guard['reason']],
        'Sender resolved' => ['found' => (bool)$sender, 'sender' => $sender ? ($sender['name'] ?? '') : '(none)'],
        'Template resolved' => ['found' => (bool)$tpl, 'template' => $tpl ? ($tpl['name'] ?? '') : '(none)'],
        'Suppressed' => ['blocked' => $recipient !== '' && em_suppressed($recipient)],
        'Preference' => ['blocked' => em_pref_denies(!empty($params['client_id']) ? (int)$params['client_id'] : null, (string)($params['category'] ?? 'general'))],
    ];
}











