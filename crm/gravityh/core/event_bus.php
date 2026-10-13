<?php
/**
 * GRAVITY CRM - Event Bus (SPS 2040 Phase 5)
 *
 * Spec coverage:
 *   Sec 35 Events / consumers / background operations
 *   Sec 36 Automation bridging (delegates to existing rules engine)
 *   Sec 37 Exception auto-opening from failure-class events
 *
 * Design notes:
 *   - bus_emit() persists durably, then attempts synchronous dispatch.
 *   - Consumer failures never propagate into business flow; the event is
 *     marked failed with last_error and retried by the background runner.
 *   - The automation consumer delegates to the pre-existing
 *     trigger_automation() engine so current rules keep firing unchanged.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

/** Canonical event names (spec Sec 35 list, extended where operational). */
const EVT_CLIENT_CREATED           = 'CLIENT_CREATED';
const EVT_BUSINESS_CREATED         = 'BUSINESS_CREATED';
const EVT_SERVICE_STARTED          = 'SERVICE_STARTED';
const EVT_REQUIREMENT_MISSING      = 'REQUIREMENT_MISSING';
const EVT_DOCUMENT_UPLOADED        = 'DOCUMENT_UPLOADED';
const EVT_TASK_OVERDUE             = 'TASK_OVERDUE';
const EVT_DEADLINE_APPROACHING     = 'DEADLINE_APPROACHING';
const EVT_DEADLINE_OVERDUE         = 'DEADLINE_OVERDUE';
const EVT_DEADLINE_BREACHED        = 'DEADLINE_BREACHED';
const EVT_INVOICE_CREATED          = 'INVOICE_CREATED';
const EVT_INVOICE_STATUS_CHANGED   = 'INVOICE_STATUS_CHANGED';
const EVT_INVOICE_OVERDUE          = 'INVOICE_OVERDUE';
const EVT_PAYMENT_RECEIVED         = 'PAYMENT_RECEIVED';
const EVT_LEAD_CREATED             = 'LEAD_CREATED';
const EVT_LEAD_CONVERTED           = 'LEAD_CONVERTED';
const EVT_PROJECT_COMPLETED        = 'PROJECT_COMPLETED';
const EVT_PROJECT_EXPIRED          = 'PROJECT_EXPIRED';
const EVT_EMAIL_RECEIVED           = 'EMAIL_RECEIVED';
const EVT_API_SYNC_COMPLETED       = 'API_SYNC_COMPLETED';
const EVT_API_SYNC_FAILED          = 'API_SYNC_FAILED';
const EVT_EMAIL_QUEUED     = 'EMAIL_QUEUED';
const EVT_EMAIL_SENT       = 'EMAIL_SENT';
const EVT_EMAIL_DELIVERED  = 'EMAIL_DELIVERED';
const EVT_EMAIL_FAILED     = 'EMAIL_FAILED';
const EVT_EMAIL_BOUNCED    = 'EMAIL_BOUNCED';
const EVT_EMAIL_REPLIED    = 'EMAIL_REPLIED';
const EVT_EMAIL_SCHEDULED  = 'EMAIL_SCHEDULED';
const EVT_EMAIL_CANCELLED  = 'EMAIL_CANCELLED';

/**
 * EVENT -> [automation_module, automation_event] bridge map.
 * Events listed here replay through the legacy trigger_automation()
 * rules engine so every existing admin-defined rule keeps working.
 */
$GLOBALS['EVT_AUTOMATION_MAP'] = [
    EVT_CLIENT_CREATED         => ['clients', 'create'],
    EVT_LEAD_CREATED           => ['leads', 'create'],
    EVT_LEAD_CONVERTED         => ['leads', 'convert'],
    EVT_INVOICE_CREATED        => ['invoices', 'create'],
    EVT_PAYMENT_RECEIVED       => ['invoices', 'payment'],
    EVT_INVOICE_STATUS_CHANGED => ['invoices', 'status_change'],
    EVT_DOCUMENT_UPLOADED      => ['documents', 'upload'],
    EVT_SERVICE_STARTED        => ['services', 'start'],
];

/** Runtime consumer registry: [event_name|'*'][] => callable(payload, eventRow) */
$GLOBALS['EVT_CONSUMERS'] = [];

/**
 * Register a consumer for an event name, or '*' for all events.
 * Later registrations run after earlier ones.
 */
function bus_register_consumer(string $event, callable $handler): void {
    $GLOBALS['EVT_CONSUMERS'][$event][] = $handler;
}

/**
 * Emit a durable event and attempt immediate synchronous dispatch.
 * Never throws: dispatch problems are recorded on the row for retry.
 *
 * @param string $event One of the EVT_* names
 * @param array  $payload Arbitrary serializable context
 * @param array  $ctx {source:string|null, source_id:int|null, client_id:int|null}
 * @return int events.id
 */
function bus_emit(string $event, array $payload = [], array $ctx = []): int {
    db_execute(
        'INSERT INTO events (event_name, payload, source, source_id, client_id)
         VALUES (?, ?, ?, ?, ?)',
        [
            $event,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $ctx['source'] ?? null,
            !empty($ctx['source_id']) ? (int)$ctx['source_id'] : null,
            !empty($ctx['client_id']) ? (int)$ctx['client_id'] : null,
        ]
    );
    $event_id = db_last_id();

    try {
        bus_dispatch($event_id);
    } catch (Throwable $e) {
        error_log('[EventBus] dispatch crash #' . $event_id . ': ' . $e->getMessage());
    }

    return $event_id;
}

/**
 * Dispatch one event to all matching consumers.
 * Marks processed on full success; failed (with error text) otherwise.
 */
function bus_dispatch(int $event_id): bool {
    $ev = db_row('SELECT * FROM events WHERE id = ?', [$event_id]);
    if (!$ev || $ev['status'] === 'processed') {
        return false;
    }

    db_execute(
        "UPDATE events SET status = 'processing', attempts = attempts + 1, dispatched_at = NOW(), last_error = NULL WHERE id = ?",
        [$event_id]
    );

    $payload = json_decode((string)$ev['payload'], true) ?: [];
    $errors  = [];

    foreach ([$ev['event_name'], '*'] as $key) {
        foreach ($GLOBALS['EVT_CONSUMERS'][$key] ?? [] as $handler) {
            try {
                $handler($payload, $ev);
            } catch (Throwable $e) {
                $errors[] = get_class($e) . ':' . $e->getMessage();
            }
        }
    }

    if (empty($errors)) {
        db_execute(
            "UPDATE events SET status = 'processed', processed_at = NOW() WHERE id = ?",
            [$event_id]
        );
        return true;
    }

    db_execute(
        "UPDATE events SET status = 'failed', last_error = ? WHERE id = ?",
        [implode(' | ', array_slice($errors, 0, 5)), $event_id]
    );
    return false;
}

/**
 * Background retry sweep for pending/failed events.
 * Returns number successfully processed this pass.
 */
function bus_dispatch_pending(int $limit = 50): int {
    $rows = db_query(
        "SELECT id FROM events WHERE status IN ('pending','failed') ORDER BY id ASC LIMIT " . max(1, (int)$limit)
    );
    $ok = 0;
    foreach ($rows as $r) {
        if (bus_dispatch((int)$r['id'])) { $ok++; }
    }
    return $ok;
}

/* ============================================================
   BUILT-IN CONSUMERS
   ============================================================ */

/**
 * Bridge into the pre-existing automation rules engine so every
 * admin-defined rule keeps firing, now with durability + retry.
 */
function bus_consumer_automation(array $payload, array $ev): void {
    $map = $GLOBALS['EVT_AUTOMATION_MAP'][$ev['event_name']] ?? null;
    if (!$map || !function_exists('trigger_automation')) {
        return; // not automation-bound, or engine absent
    }
    trigger_automation($map[0], $map[1], (int)($ev['source_id'] ?? 0));
}

/**
 * Alert active administrators on failure-class events.
 */
function bus_consumer_admin_alerts(array $payload, array $ev): void {
    $alert_on = [
        EVT_API_SYNC_FAILED    => 'Integration sync failed',
        EVT_DEADLINE_BREACHED  => 'Deadline breached',
        EVT_INVOICE_OVERDUE    => 'Invoice went overdue',
        EVT_TASK_OVERDUE       => 'Task went overdue',
    ];
    if (!isset($alert_on[$ev['event_name']])) {
        return;
    }
    $admins = db_query("SELECT id FROM users WHERE role_id = 1 AND status = 'active'");
    foreach ($admins as $a) {
        notify(
            (int)$a['id'],
            $alert_on[$ev['event_name']],
            (string)($payload['title'] ?? 'Event #' . $ev['id']),
            'events',
            (int)$ev['id']
        );
    }
}

/**
 * Auto-open exceptions for failure-class events (spec Sec 37).
 * De-duplicated per (category, source, source_id) while open.
 */
function bus_consumer_exceptions(array $payload, array $ev): void {
    $map = [
        EVT_API_SYNC_FAILED   => ['integration_failure', 'high',     'Integration sync failed'],
        EVT_DEADLINE_BREACHED => ['deadline_breach',     'critical', 'Deadline breached'],
        EVT_AI_LOW_CONFIDENCE => ['ai_low_confidence',   'medium',   'AI result below confidence threshold'],
    ];
    // Workflow-failure / payment-mismatch events arrive with later phases;
    // add their mappings here once those EVT_ constants exist.
    if (!isset($map[$ev['event_name']])) {
        return;
    }
    [$category, $severity, $title] = $map[$ev['event_name']];

    $open = db_value(
        "SELECT COUNT(*) FROM exceptions WHERE category = ? AND source = ? AND source_id = ? AND status <> 'closed'",
        [$category, (string)($ev['source'] ?? 'event'), (int)($ev['source_id'] ?? 0)]
    );
    if ((int)$open > 0) { return; } // one open exception per source

    db_execute(
        'INSERT INTO exceptions (category, severity, title, detail, client_id, project_id, client_services_id, source, source_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $category,
            $severity,
            (string)($payload['title'] ?? $title),
            (string)($payload['detail'] ?? ''),
            !empty($ev['client_id']) ? (int)$ev['client_id'] : null,
            !empty($payload['project_id']) ? (int)$payload['project_id'] : null,
            !empty($payload['client_services_id']) ? (int)$payload['client_services_id'] : null,
            (string)($ev['source'] ?? 'event'),
            (int)($ev['source_id'] ?? 0),
        ]
    );
}

/* ---- Registration (order = execution order) ---- */
bus_register_consumer('*', 'bus_consumer_automation');
bus_register_consumer('*', 'bus_consumer_admin_alerts');
bus_register_consumer('*', 'bus_consumer_exceptions');

/* ============================================================
   BACKGROUND OPERATIONS (spec Sec 35)
   Invoked by cron.php (CLI preferred) or an admin from browser.
   All transitions are state-guarded => naturally idempotent.
   ============================================================ */

function jobs_run_background(): array {
    $report = [
        'deadlines_due_soon' => 0,
        'deadlines_overdue'  => 0,
        'deadline_breaches'  => 0,
        'invoices_overdue'   => 0,
        'events_retried'     => 0,
    ];

    // 1. Approaching windows: upcoming -> due_soon inside warn_days
    $soon = db_query(
        "SELECT * FROM deadlines WHERE status = 'upcoming' AND due_date <= DATE_ADD(NOW(), INTERVAL warn_days DAY)"
    );
    foreach ($soon as $d) {
        db_execute("UPDATE deadlines SET status = 'due_soon' WHERE id = ? AND status = 'upcoming'", [(int)$d['id']]);
        bus_emit(
            EVT_DEADLINE_APPROACHING,
            [
                'title'              => (string)$d['title'],
                'due_date'           => (string)$d['due_date'],
                'warn_days'          => (int)$d['warn_days'],
                'client_services_id' => $d['client_services_id'] !== null ? (int)$d['client_services_id'] : null,
            ],
            ['source' => 'deadline', 'source_id' => (int)$d['id'], 'client_id' => $d['client_id'] !== null ? (int)$d['client_id'] : null]
        );
        $report['deadlines_due_soon']++;
    }

    // 2. Overdue transition + grace-period breach escalation
    $grace = max(1, (int)get_setting('deadline_breach_grace_days', '3'));
    $od = db_query(
        "SELECT * FROM deadlines WHERE status IN ('upcoming','due_soon') AND due_date < NOW()"
    );
    foreach ($od as $d) {
        db_execute(
            "UPDATE deadlines SET status = 'overdue' WHERE id = ? AND status IN ('upcoming','due_soon')",
            [(int)$d['id']]
        );
        $ctx = ['source' => 'deadline', 'source_id' => (int)$d['id'], 'client_id' => $d['client_id'] !== null ? (int)$d['client_id'] : null];
        bus_emit(EVT_DEADLINE_OVERDUE, ['title' => (string)$d['title'], 'due_date' => (string)$d['due_date']], $ctx);
        $report['deadlines_overdue']++;

        if (strtotime((string)$d['due_date']) < strtotime('-' . $grace . ' days')) {
            db_execute('UPDATE deadlines SET escalated = 1 WHERE id = ?', [(int)$d['id']]);
            bus_emit(
                EVT_DEADLINE_BREACHED,
                [
                    'title'              => (string)$d['title'],
                    'detail'             => "Deadline exceeded breach grace of {$grace} day(s).",
                    'project_id'         => $d['project_id'] !== null ? (int)$d['project_id'] : null,
                    'client_services_id' => $d['client_services_id'] !== null ? (int)$d['client_services_id'] : null,
                ],
                $ctx
            );
            $report['deadline_breaches']++;
        }
    }

    // 3. Invoice overdue sync with finance statuses
    $inv = db_query(
        "SELECT id, invoice_number, client_id FROM invoices WHERE status = 'sent' AND due_date < CURDATE()"
    );
    foreach ($inv as $i) {
        db_execute("UPDATE invoices SET status = 'overdue' WHERE id = ? AND status = 'sent'", [(int)$i['id']]);
        bus_emit(
            EVT_INVOICE_OVERDUE,
            ['title' => 'Invoice ' . $i['invoice_number'] . ' is overdue', 'invoice_number' => (string)$i['invoice_number']],
            ['source' => 'invoice', 'source_id' => (int)$i['id'], 'client_id' => (int)$i['client_id']]
        );
        $report['invoices_overdue']++;
    }

    // 4. Retry pending/failed events (spec Sec 41 retry behavior)
    $report['events_retried'] = bus_dispatch_pending(100);

    // 5. Retention cleanup for processed events
    $retention = max(1, (int)get_setting('events_retention_days', '30'));
    db_execute(
        "DELETE FROM events WHERE status = 'processed' AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$retention]
    );

    return $report;
}
