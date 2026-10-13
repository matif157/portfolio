<?php
/**
 * GRAVITY CRM - AI Orchestrator (SPS 2040 Phase 7)
 *
 * Spec coverage:
 *   Sec 16 Controlled execution - proposal -> permission check ->
 *          validation -> business service -> audit -> response
 *   Sec 18 Decision audit       - provider/model/task/confidence/
 *          affected record/approval status stored for every call
 *   Sec 39 Provider strategy    - fast vs strong tiers, multi-provider
 *   Sec 40 Usage & cost         - tokens + estimated USD per request
 *
 * Transport delegates to the existing call_ai_api() in ai_service.php
 * (OpenAI / Anthropic / Google / Ollama). A test hook allows full
 * offline verification of governance logic.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

/** Approximate USD price per 1K tokens (blended). */
const AI_PRICE_PER_1K = [
    'gpt-4o-mini' => 0.0006,
    'gpt-4o'      => 0.0100,
    'gpt-3.5-turbo' => 0.0015,
    'claude-3-haiku' => 0.0008,
    'claude-3-5-sonnet' => 0.0090,
    'gemini-1.5-flash' => 0.00035,
    'gemini-1.5-pro'   => 0.0070,
];
const AI_PRICE_DEFAULT = 0.0020;

/** Resolve effective provider/model for a capability tier (Sec 39). */
function ai_tier_cfg(string $tier): array {
    // Direct reads (not get_setting) so admin changes apply immediately
    $p = (string)(db_value('SELECT setting_value FROM settings WHERE setting_key = ?', ["ai_tier_{$tier}_provider"]) ?? '');
    $m = (string)(db_value('SELECT setting_value FROM settings WHERE setting_key = ?', ["ai_tier_{$tier}_model"]) ?? '');
    return [
        'provider' => $p !== '' ? $p : get_setting('ai_provider', 'openai'),
        'model'    => $m !== '' ? $m : get_setting('ai_model', 'gpt-4o-mini'),
    ];
}

/**
 * Permission check honoring an EXPLICIT acting-user context
 * (CLI/background/AI flows) before falling back to the session.
 * Role-1 administrators always pass, mirroring user_can().
 */
function ai_user_can(array $user, string $module, string $action): bool {
    if (($user['role_id'] ?? 0) == 1 && !empty($user['id'])) { return true; }
    if (empty($user['id'])) { return false; }
    return user_can_scoped($module, $action);
}

/**
 * Single governed entry point into any model.
 *
 * @param string $task_type e.g. assistant|doc_classify|summary
 * @param array  $opts {tier,system,user,max_tokens,temperature,
 *                     client_id,module,record_id,interaction_type,user_id}
 * @return array{ok:bool,text:string,provider:string,model:string,
 *               tokens:int,cost:float,duration_ms:int,error:?string}
 */
function ai_call(string $task_type, array $opts = []): array {
    $tier    = ($opts['tier'] ?? 'fast') === 'strong' ? 'strong' : 'fast';
    $cfg     = ai_tier_cfg($tier);
    $user_id = $opts['user_id'] ?? (current_user()['id'] ?? null);
    $started = microtime(true);

    $ok = false; $text = ''; $usage = null; $error = null;

    if (isset($GLOBALS['AI_TRANSPORT'])) {
        // Offline test hook: fn(task_type, opts) => ['text'=>..,'usage'=>..]
        try {
            $res   = ($GLOBALS['AI_TRANSPORT'])($task_type, $opts);
            $text  = (string)($res['text'] ?? '');
            $usage = $res['usage'] ?? null;
            $ok    = isset($res['ok']) ? (bool)$res['ok'] : ($text !== '');
            if (!$ok) { $error = (string)($res['error'] ?? 'transport returned failure'); }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } else {
        require_once __DIR__ . '/../ai_service.php';
        $opts['provider'] = $cfg['provider'];
        $opts['model']    = $cfg['model'];
        $GLOBALS['AI_LAST_USAGE'] = null;
        $text = call_ai_api((string)($opts['system'] ?? ''), (string)($opts['user'] ?? ''), $opts);
        $usage = $GLOBALS['AI_LAST_USAGE'] ?? null;
        $ok  = ($text !== '');
        if (!$ok) { $error = ai_api_configured() ? 'Empty completion from provider.' : 'AI provider not configured.'; }
    }

    $prompt_tokens     = (int)($usage['prompt_tokens'] ?? 0);
    $completion_tokens = (int)($usage['completion_tokens'] ?? 0);
    $total_tokens      = (int)($usage['total_tokens'] ?? 0);
    if ($total_tokens === 0 && ($prompt_tokens || $completion_tokens)) {
        $total_tokens = $prompt_tokens + $completion_tokens;
    }
    if ($total_tokens === 0 && $text !== '') {
        $total_tokens = (int)ceil((strlen((string)($opts['user'] ?? '')) + strlen($text)) / 4);
    }

    $rate = AI_PRICE_PER_1K[$cfg['model']] ?? AI_PRICE_DEFAULT;
    $cost = round(($total_tokens / 1000) * $rate, 6);
    $ms   = (int)((microtime(true) - $started) * 1000);

    try {
        db_execute(
            'INSERT INTO ai_usage (user_id, provider, model, tier, task_type, prompt_tokens, completion_tokens, total_tokens, est_cost_usd, client_id, module, record_id, duration_ms, ok)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $user_id, $cfg['provider'], $cfg['model'], $tier, $task_type,
                $prompt_tokens, $completion_tokens, $total_tokens, $cost,
                !empty($opts['client_id']) ? (int)$opts['client_id'] : null,
                $opts['module'] ?? null,
                !empty($opts['record_id']) ? (int)$opts['record_id'] : null,
                $ms, $ok ? 1 : 0,
            ]
        );
        // Legacy timeline table requires a real user (user_id NOT NULL):
        // skip for background/CLI invocations - usage + decisions cover audit.
        if ($user_id) {
            db_execute(
                'INSERT INTO ai_interactions (user_id, interaction_type, user_input, ai_response, module, record_id, confidence, status)
                 VALUES (?, ?, ?, ?, ?, ?, NULL, ?)',
                [
                    $user_id,
                    in_array(($opts['interaction_type'] ?? ''), ['chat','voice','email','automation','customizer','other'], true)
                        ? $opts['interaction_type'] : 'automation',
                    mb_substr((string)($opts['user'] ?? ''), 0, 2000),
                    mb_substr($text, 0, 4000),
                    $opts['module'] ?? 'ai',
                    !empty($opts['record_id']) ? (int)$opts['record_id'] : null,
                    $ok ? 'completed' : 'failed',
                ]
            );
        }
    } catch (Throwable $e) {
        error_log('[AI] ledger failure: ' . $e->getMessage());
    }

    return [
        'ok' => $ok, 'text' => $text,
        'provider' => $cfg['provider'], 'model' => $cfg['model'],
        'tokens' => $total_tokens, 'cost' => $cost, 'duration_ms' => $ms,
        'error' => $error,
    ];
}

/** Force JSON out of a model call; one guided retry on decode failure. */
function ai_json(string $task_type, array $opts): ?array {
    for ($i = 0; $i < 2; $i++) {
        $r = ai_call($task_type, $opts);
        if (!$r['ok']) { return null; }
        $raw = trim($r['text']);
        if (str_starts_with($raw, '{') || str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) { return $decoded; }
        }
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) { return $decoded; }
        }
        $opts['user'] .= "\n\nIMPORTANT: Reply with valid JSON only.";
    }
    return null;
}

/* ============================================================
   DECISION AUDIT + APPROVAL LIFECYCLE (Sec 16 / Sec 18)
   ============================================================ */

/** Persist one audited AI decision. Returns its id. */
function ai_decision_write(array $d): int {
    db_execute(
        'INSERT INTO ai_decisions
            (user_id, task_type, provider, model, prompt_summary, response_summary,
             action_requested, affected_module, affected_record_id, confidence,
             needs_approval, approval_status, result_note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $d['user_id'] ?? null,
            $d['task_type'] ?? 'assistant',
            $d['provider'] ?? null,
            $d['model'] ?? null,
            mb_substr((string)($d['prompt_summary'] ?? ''), 0, 2000),
            mb_substr((string)($d['response_summary'] ?? ''), 0, 2000),
            $d['action_requested'] ?? null,
            $d['affected_module'] ?? null,
            !empty($d['affected_record_id']) ? (int)$d['affected_record_id'] : null,
            isset($d['confidence']) ? round((float)$d['confidence'], 4) : null,
            !empty($d['needs_approval']) ? 1 : 0,
            !empty($d['needs_approval']) ? 'pending' : 'auto',
            isset($d['result_note']) ? mb_substr((string)$d['result_note'], 0, 2000) : null,
        ]
    );
    return db_last_id();
}

/** Mark a pending decision approved/rejected by an administrator. */
function ai_decide(int $decision_id, bool $approve, int $admin_uid): bool {
    $row = db_row("SELECT * FROM ai_decisions WHERE id = ? AND approval_status = 'pending'", [$decision_id]);
    if (!$row) { return false; }

    db_execute(
        'UPDATE ai_decisions SET approval_status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?',
        [$approve ? 'approved' : 'rejected', $admin_uid, $decision_id]
    );
    log_activity('ai', $approve ? 'approved' : 'rejected', $decision_id, 'AI decision #' . $decision_id);

    // Approved tool-proposals execute here, using their stored arguments.
    if ($approve && str_starts_with((string)$row['action_requested'], 'tool:')) {
        $tool = substr((string)$row['action_requested'], 5);
        $args = json_decode((string)$row['result_note'], true) ?: [];
        $res  = ai_tool_execute($tool, $args, current_user() ?: []);
        db_execute(
            'UPDATE ai_decisions SET executed = 1, result_note = ? WHERE id = ?',
            [mb_substr(json_encode($res, JSON_UNESCAPED_UNICODE), 0, 2000), $decision_id]
        );
    }
    return true;
}

/* ============================================================
   TOOL REGISTRY (Sec 16 - the ONLY way AI touches business data)
   Every handler runs behind a server-side permission check;
   no raw SQL ever reaches the model.
   ============================================================ */
require_once __DIR__ . '/service_engine.php';

$GLOBALS['AI_TOOLS'] = [
    'clients.search' => [
        'perm'  => ['clients', 'view'],
        'desc'  => 'Find clients by name or email',
        'args'  => ['query' => 'string'],
        'handler' => function (array $a) {
            $q = '%' . trim((string)($a['query'] ?? '')) . '%';
            if ($q === '%%') { return []; }
            return db_query(
                'SELECT id, first_name, last_name, email, status FROM clients
                 WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?
                 ORDER BY id DESC LIMIT 10',
                [$q, $q, $q]
            );
        },
    ],
    'client.summary' => [
        'perm'  => ['clients', 'view'],
        'desc'  => 'Operational rollup for one client (Sec 46 answers)',
        'args'  => ['client_id' => 'int'],
        'handler' => function (array $a) {
            $cid = (int)($a['client_id'] ?? 0);
            $c = db_row('SELECT id, first_name, last_name, email, status FROM clients WHERE id = ?', [$cid]);
            if (!$c) { return ['error' => 'Client not found']; }
            return [
                'client'           => $c,
                'businesses'       => (int)db_value('SELECT COUNT(*) FROM businesses WHERE client_id = ?', [$cid]),
                'active_services'  => (int)db_value("SELECT COUNT(*) FROM client_services WHERE client_id = ? AND status = 'active'", [$cid]),
                'open_deadlines'   => (int)db_value("SELECT COUNT(*) FROM deadlines WHERE client_id = ? AND status IN ('upcoming','due_soon','overdue')", [$cid]),
                'overdue_invoices' => (int)db_value("SELECT COUNT(*) FROM invoices WHERE client_id = ? AND status = 'overdue'", [$cid]),
                'balance_due'      => (float)db_value("SELECT COALESCE(SUM(amount_due),0) FROM invoices WHERE client_id = ? AND status IN ('sent','partial','overdue')", [$cid]),
                'open_exceptions'  => (int)db_value("SELECT COUNT(*) FROM exceptions WHERE client_id = ? AND status NOT IN ('resolved','closed')", [$cid]),
            ];
        },
    ],
    'requirements.missing' => [
        'perm'  => ['services', 'view'],
        'desc'  => 'List outstanding service requirements',
        'args'  => [],
        'handler' => fn () => db_query(
            "SELECT cs.id AS assignment_id, c.first_name, c.last_name, s.name AS service, r.title AS requirement
             FROM client_service_requirements csr
             JOIN client_services cs ON cs.id = csr.client_services_id
             JOIN clients c ON c.id = cs.client_id
             JOIN services s ON s.id = cs.service_id
             JOIN service_requirements r ON r.id = csr.requirement_id
             WHERE csr.status IN ('not_started','requested','pending_client','rejected')
             ORDER BY csr.id DESC LIMIT 20"
        ),
    ],
    'invoices.overdue' => [
        'perm'  => ['invoices', 'view'],
        'desc'  => 'List overdue invoices',
        'args'  => [],
        'handler' => fn () => db_query(
            "SELECT id, invoice_number, client_id, due_date, total_amount, amount_due
             FROM invoices WHERE status = 'overdue' ORDER BY due_date ASC LIMIT 20"
        ),
    ],
    'service.start' => [
        'perm'     => ['services', 'edit'],
        'desc'     => 'Start a service for a client (full Sec 7 pipeline)',
        'args'     => ['client_id' => 'int', 'service_id' => 'int'],
        'approval' => true,
        'handler'  => function (array $a) {
            return svc_assign_service_to_client(
                (int)($a['client_id'] ?? 0),
                (int)($a['service_id'] ?? 0),
                ['user_id' => current_user()['id'] ?? null]
            );
        },
    ],
];

/** JSON schema of available tools, embedded into the assistant prompt. */
function ai_tool_schema(): string {
    $out = [];
    foreach ($GLOBALS['AI_TOOLS'] as $name => $t) {
        $out[$name] = ['desc' => $t['desc'], 'args' => $t['args']];
    }
    return (string)json_encode($out, JSON_UNESCAPED_UNICODE);
}

/**
 * Execute a registered tool behind its permission check.
 * Never throws; returns a uniform envelope.
 */
function ai_tool_execute(string $name, array $args, array $user): array {
    $t = $GLOBALS['AI_TOOLS'][$name] ?? null;
    if (!$t) {
        return ['ok' => false, 'perm_ok' => false, 'error' => 'Unknown tool.'];
    }
    [$module, $action] = $t['perm'];
    if (!ai_user_can($user, $module, $action)) {
        return ['ok' => false, 'perm_ok' => false,
                'error' => "Permission denied for {$module}.{$action}."];
    }
    try {
        $data = ($t['handler'])($args);
        log_activity('ai', 'tool_executed', null, "Tool {$name} executed", false);
        return ['ok' => true, 'perm_ok' => true, 'data' => $data];
    } catch (Throwable $e) {
        return ['ok' => false, 'perm_ok' => true, 'error' => $e->getMessage()];
    }
}

/* ============================================================
   NL ASSISTANT (Sec 17) - natural language -> controlled ops
   ============================================================ */

/**
 * Convert a natural-language request into a governed tool execution
 * or a plain informational answer. Every path is audited.
 *
 * @return array{ok:bool,text:string,executed:bool,pending?:bool,
 *               decision_id:int,data?:mixed,error?:string}
 */
function ai_assistant(string $message, array $user = []): array {
    $uid = $user['id'] ?? null;

    $system = "You are the SPS CRM operations assistant for a professional services firm.\n"
            . "Available tools (use ONLY these):\n" . ai_tool_schema() . "\n\n"
            . "If a tool should run, reply ONLY with JSON:\n"
            . '{"tool":"<name>","args":{...},"explain":"<why>","confidence":<0.0-1.0>}\n'
            . "For anything informational or conversational, reply ONLY with JSON:\n"
            . '{"answer":"<your reply>"}';

    $res = ai_call('assistant', [
        'tier' => 'strong',
        'system' => $system,
        'user' => $message,
        'temperature' => 0.2,
        'max_tokens' => 700,
        'interaction_type' => 'chat',
        'module' => 'assistant',
        'user_id' => $uid,
    ]);

    $prov = $res['provider']; $model = $res['model'];

    if (!$res['ok']) {
        $id = ai_decision_write([
            'user_id' => $uid, 'task_type' => 'assistant',
            'provider' => $prov, 'model' => $model,
            'prompt_summary' => $message,
            'response_summary' => 'transport failure: ' . ($res['error'] ?? ''),
        ]);
        return ['ok' => false, 'text' => 'AI transport failed: ' . ($res['error'] ?? 'unknown'),
                'executed' => false, 'decision_id' => $id, 'error' => $res['error']];
    }

    // Parse the model's JSON (fenced or bare)
    $raw = trim($res['text']);
    $decoded = null;
    if (preg_match('/\{.*\}/s', $raw, $m)) {
        $decoded = json_decode($m[0], true);
    }

    // Plain answer path
    if (is_array($decoded) && isset($decoded['answer']) && !isset($decoded['tool'])) {
        $id = ai_decision_write([
            'user_id' => $uid, 'task_type' => 'assistant',
            'provider' => $prov, 'model' => $model,
            'prompt_summary' => $message,
            'response_summary' => (string)$decoded['answer'],
        ]);
        return ['ok' => true, 'text' => (string)$decoded['answer'],
                'executed' => false, 'decision_id' => $id];
    }

    $tool  = is_array($decoded) ? (string)($decoded['tool'] ?? '') : '';
    $args  = is_array($decoded) ? (array)($decoded['args'] ?? []) : [];
    $conf  = is_array($decoded) ? (float)($decoded['confidence'] ?? 0) : 0.0;
    $expl  = is_array($decoded) ? (string)($decoded['explain'] ?? '') : '';
    $t     = $GLOBALS['AI_TOOLS'][$tool] ?? null;

    if (!$t || !is_array($decoded)) {
        $id = ai_decision_write([
            'user_id' => $uid, 'task_type' => 'assistant',
            'provider' => $prov, 'model' => $model,
            'prompt_summary' => $message,
            'response_summary' => mb_substr($raw, 0, 1000),
            'result_note' => 'unparseable-or-unknown-tool',
        ]);
        return ['ok' => false, 'text' => 'I could not map that request to a safe operation.',
                'executed' => false, 'decision_id' => $id, 'error' => 'unparseable'];
    }

    [$module, $action] = $t['perm'];
    $can       = ai_user_can($user, $module, $action);
    $threshold = (float)get_setting('ai_confidence_threshold', '0.70');
    $needs     = !empty($t['approval']) || !$can || ($conf > 0 && $conf < $threshold);

    $did = ai_decision_write([
        'user_id' => $uid, 'task_type' => 'assistant',
        'provider' => $prov, 'model' => $model,
        'prompt_summary' => $message,
        'response_summary' => $expl,
        'action_requested' => 'tool:' . $tool,
        'affected_module' => $module,
        'affected_record_id' => (int)($args['client_id'] ?? $args['service_id'] ?? 0),
        'confidence' => $conf,
        'needs_approval' => $needs,
        'result_note' => json_encode($args, JSON_UNESCAPED_UNICODE),
    ]);

    bus_emit(EVT_AI_RECOMMENDATION_CREATED,
        ['tool' => $tool, 'explain' => $expl, 'confidence' => $conf],
        ['source' => 'assistant', 'source_id' => $did]
    );

    if ($needs) {
        $reason = !empty($t['approval']) ? 'requires human approval'
                : (!$can ? 'insufficient permissions' : 'confidence below threshold');
        return ['ok' => true, 'text' => $expl . "\n[Pending approval: {$reason}]",
                'executed' => false, 'pending' => true, 'decision_id' => $did];
    }

    $exec = ai_tool_execute($tool, $args, $user);
    db_execute(
        'UPDATE ai_decisions SET executed = ?, result_note = ? WHERE id = ?',
        [$exec['ok'] ? 1 : 0,
         mb_substr(json_encode(['args' => $args, 'result' => $exec], JSON_UNESCAPED_UNICODE), 0, 2000),
         $did]
    );

    return [
        'ok' => (bool)$exec['ok'],
        'text' => $expl . ($exec['ok'] ? '' : ("\nError: " . ($exec['error'] ?? ''))),
        'executed' => true,
        'data' => $exec['data'] ?? null,
        'decision_id' => $did,
        'error' => $exec['error'] ?? null,
    ];
}

/* ============================================================
   DOCUMENT INTELLIGENCE (Sec 18)
   ============================================================ */

/**
 * Classify a document excerpt on the fast tier. Every result is
 * audited; sub-threshold confidence raises AI_LOW_CONFIDENCE so the
 * Exception Engine records it for human review.
 */
function ai_document_classify(int $document_id, string $excerpt, array $user = []): array {
    $uid = $user['id'] ?? null;

    $system = "You classify professional-services documents. Respond ONLY with JSON:\n"
            . '{"doc_type":"bank_statement|tax_form|id_card|receipt|invoice|contract|letter|other","confidence":<0.0-1.0>}';

    $res = ai_json('doc_classify', [
        'tier' => 'fast',
        'system' => $system,
        'user' => mb_substr($excerpt, 0, 4000),
        'temperature' => 0.1,
        'max_tokens' => 120,
        'module' => 'documents',
        'record_id' => $document_id,
        'user_id' => $uid,
    ]);

    $prov = null; $model = null;
    $type = 'unknown';
    $conf = 0.0;
    $ok   = is_array($res);

    if ($ok) {
        $type = (string)($res['doc_type'] ?? 'unknown');
        $conf = (float)($res['confidence'] ?? 0);
    }

    // Pull provider/model from the most recent ledger row for this task
    $last = db_row(
        "SELECT provider, model FROM ai_usage WHERE task_type = 'doc_classify' ORDER BY id DESC LIMIT 1"
    );
    if ($last) { $prov = $last['provider']; $model = $last['model']; }

    $threshold = (float)get_setting('ai_confidence_threshold', '0.70');
    $low = $conf > 0 && $conf < $threshold;

    $did = ai_decision_write([
        'user_id' => $uid,
        'task_type' => 'doc_classify',
        'provider' => $prov,
        'model' => $model,
        'prompt_summary' => mb_substr($excerpt, 0, 500),
        'response_summary' => "doc_type={$type} confidence={$conf}",
        'affected_module' => 'documents',
        'affected_record_id' => $document_id,
        'confidence' => $conf,
        'needs_approval' => false,
    ]);

    if (!$ok) {
        return ['decision_id' => $did, 'doc_type' => 'unknown', 'confidence' => 0.0,
                'needs_review' => true, 'error' => 'classification failed'];
    }

    if ($low) {
        bus_emit(EVT_AI_LOW_CONFIDENCE, [
            'title'  => "Low-confidence document classification (#{$document_id})",
            'detail' => "Classified as {$type} with confidence {$conf} (threshold {$threshold}).",
            'project_id' => null,
            'client_services_id' => null,
        ], ['source' => 'document', 'source_id' => $document_id]);
    }

    return [
        'decision_id' => $did,
        'doc_type' => $type,
        'confidence' => $conf,
        'needs_review' => $low,
    ];
}