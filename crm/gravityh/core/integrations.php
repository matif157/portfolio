<?php
/**
 * GRAVITY CRM - Integration Adapter Framework (SPS 2040 Phase 6)
 *
 * Spec coverage:
 *   Sec 34 Integration Center - truthful statuses, logs, never mark
 *          connected without a real passing test
 *   Sec 38 Adapter concept    - connect/test/health/sync/disconnect
 *   Sec 41 Outage behavior    - queued outbound, idempotency, bounded
 *          retries, abandonment escalates to the Exception Engine
 *
 * Vendor specifics stay inside the existing tested helpers in
 * functions.php (test_*_connection / send_crm_*); this layer adds
 * state, logging, queueing and honest reporting only.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

/**
 * Provider catalog: key => meta.
 * tester = existing functions.php function name; null = honestly
 * unverifiable until its OAuth flow ships.
 */
$GLOBALS['INT_PROVIDERS'] = [
    'smtp' => [
        'label' => 'Email (SMTP)',
        'icon'  => 'bi-envelope',
        'cfg'   => ['smtp_host','smtp_port','smtp_encryption','smtp_username','smtp_password_encrypted','email_from_address'],
        'tester'=> 'test_smtp_socket',
    ],
    'twilio' => [
        'label' => 'Twilio (SMS / Voice)',
        'icon'  => 'bi-telephone',
        'cfg'   => ['twilio_sid','twilio_token_encrypted','twilio_phone'],
        'tester'=> 'test_twilio_connection',
    ],
    'whatsapp' => [
        'label' => 'WhatsApp Business',
        'icon'  => 'bi-whatsapp',
        'cfg'   => ['whatsapp_provider','whatsapp_api_key','whatsapp_phone_number_id'],
        'tester'=> 'test_whatsapp_connection',
    ],
    'teams' => [
        'label' => 'Microsoft Teams',
        'icon'  => 'bi-microsoft-teams',
        'cfg'   => ['microsoft_teams_webhook'],
        'tester'=> 'test_teams_webhook',
    ],
    'stripe' => [
        'label' => 'Stripe Payments',
        'icon'  => 'bi-credit-card',
        'cfg'   => ['stripe_secret_key_encrypted','stripe_publishable_key'],
        'tester'=> 'test_stripe_connection',
    ],
    'tawkto' => [
        'label' => 'Tawk.to Live Chat',
        'icon'  => 'bi-chat-dots',
        'cfg'   => ['tawkto_property_id','tawkto_widget_id'],
        'tester'=> 'test_tawkto_config',
    ],
    'quickbooks' => [
        'label' => 'QuickBooks',
        'icon'  => 'bi-journal-bookmark',
        'cfg'   => ['quickbooks_client_id','quickbooks_client_secret_encrypted'],
        'tester'=> 'test_quickbooks_config', // format-level only; says so
    ],
    'onedrive' => [
        'label' => 'OneDrive (Files)',
        'icon'  => 'bi-cloud',
        'cfg'   => ['onedrive_client_id'],
        'tester'=> null,
    ],
];

/** Read one integration setting; decrypts keys ending in _encrypted. */
function int_cfg(string $key): string {
    $raw = get_setting($key, '');
    if ($raw === '' || $raw === null) { return ''; }
    return substr($key, -11) === '_encrypted' ? (string)decrypt_setting((string)$raw) : (string)$raw;
}

/** Connection row for a provider (virtual defaults when untouched). */
function int_row(string $provider_key): array {
    $row = db_row('SELECT * FROM integration_connections WHERE provider_key = ?', [$provider_key]);
    if ($row) { return $row; }
    return [
        'id' => 0, 'provider_key' => $provider_key, 'status' => 'not_configured',
        'status_message' => null, 'last_test_at' => null, 'last_test_ok' => null,
        'last_test_message' => null, 'last_sync_at' => null, 'connected_at' => null,
    ];
}

/** Persist status (+message); stamps connected_at / last_sync_at. */
function int_set_status(string $provider_key, string $status, ?string $message = null): void {
    db_execute(
        "INSERT INTO integration_connections (provider_key, status, status_message)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), status_message = VALUES(status_message),
            connected_at = IF(VALUES(status) = 'connected' AND connected_at IS NULL, NOW(), connected_at),
            last_sync_at = IF(VALUES(status) = 'syncing', NOW(), last_sync_at)",
        [$provider_key, $status, $message]
    );
}

/** Write an integration log line (never throws). */
function int_log(string $provider_key, string $action, string $level, string $message, array $context = [], ?int $duration_ms = null): void {
    try {
        $conn = db_row('SELECT id FROM integration_connections WHERE provider_key = ?', [$provider_key]);
        db_execute(
            'INSERT INTO integration_logs (connection_id, provider_key, direction, action, level, message, context, duration_ms)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $conn['id'] ?? null,
                $provider_key,
                ($context['direction'] ?? 'out') === 'in' ? 'in' : 'out',
                $action,
                in_array($level, ['info','warn','error'], true) ? $level : 'info',
                mb_substr($message, 0, 1000),
                json_encode($context, JSON_UNESCAPED_UNICODE),
                $duration_ms,
            ]
        );
    } catch (Throwable $e) {
        error_log('[Integrations] log failure: ' . $e->getMessage());
    }
}

/**
 * Run the real tester for a provider and record the outcome.
 * NEVER reports connected unless the underlying test returns success.
 *
 * @return array{ok:bool,status:string,message:string}
 */
function int_test(string $provider_key): array {
    $meta = $GLOBALS['INT_PROVIDERS'][$provider_key] ?? null;
    if (!$meta) {
        return ['ok' => false, 'status' => 'error', 'message' => 'Unknown provider.'];
    }

    $started = microtime(true);
    $ok = false;
    $message = '';
    $status  = 'not_configured';

    $missing = [];
    foreach (($meta['cfg'] ?? []) as $k) {
        if (int_cfg($k) === '') { $missing[] = $k; }
    }

    if (!empty($meta['tester']) && function_exists($meta['tester'])) {
        if ($missing && $provider_key !== 'quickbooks') {
            // QuickBooks format-test intentionally tolerates partial input.
            $ok = false;
            $message = 'Not configured. Missing settings: ' . implode(', ', $missing);
        } else {
            $res = int_call_tester($provider_key, $meta['tester']);
            $ok  = !empty($res['success'] ?? $res[0] ?? false);
            $message = (string)($res['message'] ?? ($res[1] ?? ''));
            $status  = $ok ? 'connected' : 'error';
        }
    } else {
        // No real verifier exists yet (e.g. OneDrive OAuth).
        $ok = false;
        $message = $missing
            ? 'Not configured. Missing settings: ' . implode(', ', $missing)
            : 'Values present, but no verification flow exists yet; connection cannot be claimed.';
        $status = 'not_configured';
    }

    $ms = (int)((microtime(true) - $started) * 1000);
    int_set_status($provider_key, $status, mb_substr($message, 0, 250));
    db_execute(
        'UPDATE integration_connections SET last_test_at = NOW(), last_test_ok = ?, last_test_message = ? WHERE provider_key = ?',
        [$ok ? 1 : 0, mb_substr($message, 0, 250), $provider_key]
    );
    int_log($provider_key, 'test', $ok ? 'info' : ($status === 'not_configured' ? 'warn' : 'error'),
            $message, ['result_status' => $status], $ms);

    return ['ok' => $ok, 'status' => $status, 'message' => $message];
}

/** Build tester arguments per provider from stored settings. */
function int_call_tester(string $provider_key, string $fn): array {
    switch ($provider_key) {
        case 'smtp':
            return call_user_func($fn,
                int_cfg('smtp_host'), ((int)int_cfg('smtp_port')) ?: 587,
                int_cfg('smtp_encryption') ?: 'tls', int_cfg('smtp_username'),
                int_cfg('smtp_password_encrypted'), int_cfg('email_from_address'));
        case 'twilio':
            return call_user_func($fn, int_cfg('twilio_sid'), int_cfg('twilio_token_encrypted'), int_cfg('twilio_phone'));
        case 'whatsapp':
            return call_user_func($fn, int_cfg('whatsapp_provider'), int_cfg('whatsapp_api_key'), int_cfg('whatsapp_phone_number_id'));
        case 'teams':
            return call_user_func($fn, int_cfg('microsoft_teams_webhook'));
        case 'stripe':
            return call_user_func($fn, int_cfg('stripe_secret_key_encrypted'), int_cfg('stripe_publishable_key'));
        case 'tawkto':
            return call_user_func($fn, int_cfg('tawkto_property_id'), int_cfg('tawkto_widget_id'));
        case 'quickbooks':
            return call_user_func($fn, int_cfg('quickbooks_client_id'), int_cfg('quickbooks_client_secret_encrypted'));
    }
    return ['success' => false, 'message' => 'No tester mapping.'];
}

/** Disconnect: reset state to not_configured (stored credentials kept). */
function int_disconnect(string $provider_key): void {
    db_execute(
        "UPDATE integration_connections SET status = 'not_configured', status_message = NULL,
            connected_at = NULL, last_sync_at = NULL WHERE provider_key = ?",
        [$provider_key]
    );
    int_log($provider_key, 'disconnect', 'info', 'Connection reset by administrator.');
}

/* ============================================================
   OUTBOUND QUEUE (spec Sec 41)
   ============================================================ */

/**
 * Queue an outbound provider action. Duplicate idempotency keys are
 * ignored so retries and webhooks can never create double sends.
 *
 * @return array{queued:bool,id:int}
 */
function int_enqueue_outbound(string $provider_key, string $action, array $payload, ?string $idempotency_key = null): array {
    $existing = $idempotency_key
        ? db_row('SELECT id FROM outbound_queue WHERE idempotency_key = ?', [$idempotency_key])
        : null;
    if ($existing) {
        return ['queued' => false, 'id' => (int)$existing['id']];
    }

    db_execute(
        'INSERT INTO outbound_queue (provider_key, action, payload, max_attempts, idempotency_key)
         VALUES (?, ?, ?, ?, ?)',
        [
            $provider_key,
            $action,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            max(1, (int)get_setting('outbound_max_attempts', '5')),
            $idempotency_key,
        ]
    );
    return ['queued' => true, 'id' => db_last_id()];
}

/**
 * Deliverer callables per provider. Each delegates to the proven
 * senders in functions.php - zero vendor logic duplicated here.
 */
$GLOBALS['INT_DELIVERERS'] = [
    'smtp' => function (array $p): array {
        return send_crm_email((string)($p['to'] ?? ''), (string)($p['subject'] ?? ''), (string)($p['body'] ?? ''));
    },
    // SPS 2040 Email Engine: multi-account SMTP with sender profiles,
    // per-provider credentials, CC/BCC, MIME attachments. Throws on
    // failure so the shared queue layer handles retries/abandonment.
    'email' => 'em_send_from_queue',
    'twilio' => function (array $p): array {
        return send_crm_sms((string)($p['to'] ?? ''), (string)($p['body'] ?? ''));
    },
    'teams' => function (array $p): array {
        return send_teams_notification((string)($p['title'] ?? 'Notification'), (string)($p['body'] ?? ''), (string)($p['url'] ?? ''));
    },
];

/**
 * Process queued outbound actions.
 * Success -> sent | Failure -> attempts++ until max -> abandoned,
 * which emits API_SYNC_FAILED so the Exception Engine opens a
 * critical integration_failure record automatically.
 */
function jobs_process_outbound(int $limit = 25): array {
    $report = ['sent' => 0, 'failed' => 0, 'abandoned' => 0];

    $rows = db_query(
        "SELECT * FROM outbound_queue WHERE status IN ('queued','failed') ORDER BY id ASC LIMIT " . max(1, $limit)
    );

    foreach ($rows as $q) {
        db_execute("UPDATE outbound_queue SET status = 'processing' WHERE id = ?", [(int)$q['id']]);

        $payload = json_decode((string)$q['payload'], true) ?: [];
        $deliver = $GLOBALS['INT_DELIVERERS'][$q['provider_key']] ?? null;

        try {
            if (!$deliver) {
                throw new RuntimeException('No deliverer registered for provider.');
            }
            $res = $deliver($payload);
            $ok  = !empty($res[0] ?? $res['success'] ?? false);
            $msg = (string)($res[1] ?? $res['message'] ?? '');

            if ($ok) {
                db_execute(
                    "UPDATE outbound_queue SET status = 'sent', sent_at = NOW(), last_error = NULL WHERE id = ?",
                    [(int)$q['id']]
                );
                int_log($q['provider_key'], 'outbound:' . $q['action'], 'info', 'Delivered: ' . $msg, ['queue_id' => (int)$q['id']]);
                $report['sent']++;
                continue;
            }
            throw new RuntimeException($msg !== '' ? $msg : 'Provider returned failure.');
        } catch (Throwable $ex) {
            $attempts = (int)$q['attempts'] + 1;
            $max      = (int)$q['max_attempts'];
            $abandon  = $attempts >= $max;

            db_execute(
                'UPDATE outbound_queue SET status = ?, attempts = ?, last_error = ? WHERE id = ?',
                [$abandon ? 'abandoned' : 'failed', $attempts, mb_substr($ex->getMessage(), 0, 500), (int)$q['id']]
            );
            int_log((string)$q['provider_key'], 'outbound:' . (string)$q['action'], 'error',
                    'Delivery failed (' . $attempts . '/' . $max . '): ' . $ex->getMessage(),
                    ['queue_id' => (int)$q['id']]);

            if ($abandon) {
                bus_emit(
                    EVT_API_SYNC_FAILED,
                    [
                        'title'  => 'Outbound delivery abandoned',
                        'detail' => $q['provider_key'] . ':' . $q['action'] . ' failed after ' . $max . ' attempts. Last error: ' . $ex->getMessage(),
                    ],
                    ['source' => 'outbound_queue', 'source_id' => (int)$q['id'],
                     'client_id' => !empty($payload['client_id']) ? (int)$payload['client_id'] : null]
                );
                $report['abandoned']++;
            } else {
                $report['failed']++;
            }
        }
    }

    return $report;
}