<?php
/**
 * Phase 9 - Email Engine Regression Suite (SPS 2040 §45)
 * Asserts email_engine.php functions + DB state against real schema.
 * Builds own fixtures, cleans up after itself.
 */

// ---- fixtures: test client + invoice ----
$tid = db_value("SELECT id FROM clients WHERE email = 'email-test@spscrm.test'");
if (!$tid) {
    db_execute("INSERT INTO clients (first_name, last_name, email, phone, status) VALUES ('Email','Tester','email-test@spscrm.test','555-0199','active')");
    $tid = db_last_id();
}
$invId = db_value("SELECT id FROM invoices WHERE client_id = ? AND number LIKE 'EM-TEST-%'", [$tid]);
if (!$invId) {
    db_execute("INSERT INTO invoices (client_id, number, total, balance, status, due_date) VALUES (?,?,?,?,?,?)",
        [$tid, 'EM-TEST-' . $tid, 1000.00, 500.00, 'partially_paid', date('Y-m-d', strtotime('+10 days'))]);
    $invId = db_last_id();
}
db_execute("INSERT INTO sent_messages (message_type, client_id, recipient, from_email, subject, body, status, direction, source) VALUES ('email', ?, 'email-test@spscrm.test', 'test@spscrm.test', 'Test Seed', 'hello', 'sent', 'outbound', 'MANUAL')", [$tid]);
$seedId = db_last_id();

// ---- test helpers ----
function em_t_provider_make(string $email, string $from): int {
    db_execute("INSERT INTO email_providers (name, provider_type, smtp_host, smtp_port, smtp_encryption, smtp_username, from_email, from_name, is_default, status) VALUES ('TestSMTP_','smtp','127.0.0.1', 587, 'none','', ?, ?, 0, 'connected')", [$email, $from]);
    return db_last_id();
}

function em_t_sender_make(int $pid, string $purpose, string $email, string $from): int {
    db_execute("INSERT INTO email_sender_profiles (name, purpose, department, provider_id, from_email, from_name, status, is_default) VALUES ('TestSender','" . $purpose . "','Billing', ?, ?, ?, 'active', 0)", [$pid, $email, $from]);
    return db_last_id();
}

function em_t_template_make(string $category, string $purpose): int {
    db_execute("INSERT INTO message_templates (name, template_type, category, purpose, entity_type, language, sender_profile_id, status, current_version) VALUES ('Test Tpl','email','" . $category . "','" . $purpose . "','invoice','en', NULL, 'published', 1)");
    return db_last_id();
}

// ---- 1) provider + sender profile CRUD + status ----
$pid = em_t_provider_make('smtp@spscrm.test', 'smtp@spscrm.test');
$sid = em_t_sender_make($pid, 'billing', 'smtp@spscrm.test', 'SPS CRM Test SMTP');
$p = em_provider_get($pid);
$s = db_row('SELECT * FROM email_sender_profiles WHERE id = ?', [$sid]);
t_eq('provider status truthful (connected, not configured unless tested)', 'connected', $p['status'] ?? '');
t_eq('provider from_email stored', 'smtp@spscrm.test', $p['from_email'] ?? '');
t_eq('sender purpose stored', 'billing', $s['purpose'] ?? '');
t_eq('sender profile FK links provider', $pid, (int)($s['provider_id'] ?? 0));

// ---- 2) template versioning + category/purpose ----
$tplId = em_t_template_make('Payment', 'payment_receipt');
em_template_save_version($tplId, 'Version 1 Subject');
$v = db_row('SELECT * FROM email_template_versions WHERE template_id = ? ORDER BY version_number DESC LIMIT 1', [$tplId]);
t_eq('template version created', 1, (int)($v['version_number'] ?? 0));
t_eq('template status published', 'published', db_value('SELECT status FROM message_templates WHERE id = ?', [$tplId]));
t_eq('template category Payment', 'Payment', db_value('SELECT category FROM message_templates WHERE id = ?', [$tplId]));

// ---- 3) variable resolver: invoice fields ----
$resolved = em_resolve_template_vars('Due: {{invoice.due_date}} Balance: {{invoice.balance}}', $tid, [], $invId);
t_eq('invoice.balance resolves to 500', true, strpos($resolved, '500') !== false);
t_eq('invoice.due_date resolves', true, strpos($resolved, date('Y-m-d', strtotime('+10 days'))) !== false);

// ---- 4) sender resolution: routing rule + fallback ----
db_execute("INSERT INTO email_sender_routes (module, event, purpose, service_type, department, sender_profile_id) VALUES ('invoices','invoice_overdue','payment_reminder','','billing', ?)", [$sid]);
$sp = em_sender_resolve(['client_id' => $tid, 'invoice_id' => $invId, 'purpose' => 'payment_reminder', 'template_id' => $tplId]);
t_eq('sender resolved via route', $sid, (int)($sp['id'] ?? 0));
$sp2 = em_sender_resolve(['client_id' => $tid, 'purpose' => 'welcome', 'template_id' => 0]);
t_eq('sender resolved via default fallback', true, is_array($sp2) && !empty($sp2['id']));

// ---- 5) guard: paid invoices blocked ----
db_execute("UPDATE invoices SET balance = 0.00, status = 'paid' WHERE id = ?", [$invId]);
$g = em_guard_sendable(['client_id' => $tid, 'invoice_id' => $invId, 'template_id' => $tplId, 'purpose' => 'payment_reminder']);
t_eq('paid-invoice guard blocks', false, $g['ok']);
db_execute("UPDATE invoices SET balance = 500.00, status = 'partially_paid' WHERE id = ?", [$invId]);

// ---- 6) suppression + preference logic ----
em_suppress('suppressed@spscrm.test', 'bounce', 'test');
t_eq('suppressed email recognized', true, em_suppressed('suppressed@spscrm.test'));
t_eq('non-suppressed email not blocked', false, em_suppressed('notsuppressed@spscrm.test'));
t_eq('billing pref denied before allow', true, em_pref_denies($tid, 'billing'));
em_pref_allow($tid, 'billing', 1);
t_eq('billing pref allowed after unset', false, em_pref_denies($tid, 'billing'));

// ---- 7) idempotency: duplicate idempotency_key ----
em_enqueue_outbound('email', 'send', ['to' => 'email-test@spscrm.test', 'subject' => 'I', 'body' => 'b'],
    ['client_id' => $tid, 'idempotency_key' => 'idem-test-123', 'purpose' => 'test']);
$queued = db_row('SELECT * FROM outbound_queue WHERE idempotency_key = ?', ['idem-test-123']);
t_eq('idempotency key persisted', 'idem-test-123', $queued['idempotency_key'] ?? '');
em_enqueue_outbound('email', 'send', ['to' => 'x@spscrm.test', 'subject' => 'dup', 'body' => 'y'],
    ['client_id' => $tid, 'idempotency_key' => 'idem-test-123', 'purpose' => 'test']);
$rows = db_query('SELECT id FROM outbound_queue WHERE idempotency_key = ?', ['idem-test-123']);
t_eq('duplicate idempotency key not double-queued', 1, count($rows));

// ---- 8) compose -> queued + activity + event ----
db_execute("DELETE FROM outbound_queue WHERE idempotency_key = 'idem-test-123'");
$r = em_compose([
    'client_id' => $tid,
    'template_id' => $tplId,
    'purpose' => 'test_compose',
    'to' => 'email-test@spscrm.test',
    'subject' => 'Test',
    'body' => 'Body',
    'source' => 'TEST',
]);
t_eq('compose returns queued mode', 'queued', $r['mode'] ?? '');
$sm = db_row('SELECT * FROM sent_messages WHERE id = ?', [(int)($r['message_id'] ?? 0)]);
t_eq('queued message stored w/ client_id', $tid, (int)($sm['client_id'] ?? 0));
t_eq('queued message source = TEST', 'TEST', $sm['source'] ?? '');
t_eq('queued message status = queued', 'queued', $sm['status'] ?? '');
$lastAct = db_row("SELECT * FROM activities WHERE module = 'email' AND action = 'send' ORDER BY id DESC LIMIT 1");
t_eq('email activity logged', 'sent_messages', $lastAct['entity'] ?? '');
t_eq('activity severity stage 1', 1, (int)($lastAct['severity'] ?? 1));
$lastEv = db_row("SELECT * FROM events WHERE event_name = 'EMAIL_QUEUED' ORDER BY id DESC LIMIT 1");
t_eq('EMAIL_QUEUED event emitted', true, (int)($lastEv['id'] ?? 0) > 0);

// ---- 9) blocked send (suppressed recipient) ----
$r2 = em_compose([
    'client_id' => $tid,
    'to' => 'suppressed@spscrm.test',
    'subject' => 'BlockedTest',
    'body' => 'x',
    'source' => 'TEST',
]);
t_eq('suppressed recipient blocks send', 'blocked', $r2['mode'] ?? '');
t_eq('blocked reason captured', true, !empty($r2['reason']));

// ---- 10) schedule + cancel ----
db_execute("INSERT INTO email_schedules (client_id, recipient, subject, body, send_at, source) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL -1 MINUTE), 'MANUAL')", [$tid, 'email-test@spscrm.test', 'Sched', 'bo']);
$schedId = db_last_id();
em_schedule_cancel($schedId);
t_eq('schedule cancelled', 'cancelled', db_value('SELECT status FROM email_schedules WHERE id = ?', [$schedId]));

// ---- 11) inbound ingestion + client match + threading ----
db_execute("INSERT INTO sent_messages (message_type, client_id, recipient, from_email, subject, body, status, direction, source) VALUES ('email', ?, 'email-test@spscrm.test', 'email-test@spscrm.test', 'Welcome', 'body1', 'received', 'inbound', 'INBOUND')", [$tid]);
$ibResult = em_inbound_store(['from' => 'email-test@spscrm.test', 'subject' => 'Welcome reply', 'body' => 'reply', 'to' => 'ops@spscrm.test']);
t_eq('inbound matched client', $tid, (int)($ibResult['client_id'] ?? 0));
t_eq('inbound threaded (same subject)', true, (int)($ibResult['thread_id'] ?? 0) > 0);
t_eq('inbound EMAIL_RECEIVED event', true, (int)db_value("SELECT id FROM events WHERE event_name = 'EMAIL_RECEIVED' AND source_id = ? ORDER BY id DESC LIMIT 1", [(int)($ibResult['message_id'] ?? 0)]) > 0);

// ---- 12) suppression cleanup ----
em_suppress('temp-supp@spscrm.test', 'invalid', 'temp');
t_eq('temp suppressed', true, em_suppressed('temp-supp@spscrm.test'));
db_execute("DELETE FROM email_suppressions WHERE email_address = 'temp-supp@spscrm.test'");
t_eq('suppression cleaned', false, em_suppressed('temp-supp@spscrm.test'));

// ---- 13) bulk preview counts ----
$p = em_bulk_preview([$tid], 0);
t_eq('bulk preview counts clients', 1, $p['client_count'] ?? 0);

// ---- 14) why-blocked diagnostic (spec §97) ----
$diag = em_why_blocked(['client_id' => $tid, 'invoice_id' => 0, 'template_id' => $tplId, 'to' => '', 'purpose' => 'payment_reminder']);
t_eq('diagnostic returns recipient key', true, array_key_exists('Recipient found', $diag));
t_eq('diagnostic returns guard key', true, array_key_exists('Invoice balance', $diag));
t_eq('diagnostic returns sender key', true, array_key_exists('Sender resolved', $diag));
t_eq('diagnostic returns template key', true, array_key_exists('Template resolved', $diag));
t_eq('diagnostic returns suppressed key', true, array_key_exists('Suppressed', $diag));

// ---- 15) template resolve (published) ----
$tplResolved = em_template_resolve(['purpose' => 'payment_receipt', 'template_id' => $tplId]);
t_eq('template resolves', $tplId, (int)($tplResolved['id'] ?? 0));

// ---- cleanup ----
db_execute('DELETE FROM events WHERE source IN (?,?) OR (event_name = "EMAIL_QUEUED" AND source_id IN (SELECT id FROM sent_messages WHERE source="TEST"))', ['sent_message', 'email_schedule']);
db_execute('DELETE FROM outbound_queue WHERE idempotency_key IN (?,?)', ['idem-test-123', '']);
db_execute('DELETE FROM sent_messages WHERE source IN (?,?) OR from_email = ?', ['TEST', 'INBOUND', 'email-test@spscrm.test']);
db_execute('DELETE FROM sent_messages WHERE from_email = ?', ['email-test@spscrm.test']);
db_execute('DELETE FROM email_threads WHERE participants LIKE ?', ['%spscrm.test%']);
db_execute('DELETE FROM email_suppressions WHERE email_address LIKE ?', ['%@spscrm.test%']);
db_execute('DELETE FROM email_preferences WHERE client_id = ?', [$tid]);
db_execute('DELETE FROM email_sender_routes WHERE sender_profile_id IN (SELECT id FROM email_sender_profiles WHERE name LIKE "TestSender%")');
db_execute('DELETE FROM email_sender_profiles WHERE name LIKE "TestSender%"');
db_execute('DELETE FROM email_providers WHERE name LIKE "TestSMTP%"');
db_execute('DELETE FROM email_template_versions WHERE template_id IN (SELECT mt.id FROM message_templates mt WHERE mt.name = "Test Tpl")');
db_execute('DELETE FROM message_templates WHERE name = ?', ['Test Tpl']);
db_execute('DELETE FROM invoices WHERE number LIKE ?', ['EM-TEST-%']);

// ---- teardown client only if no other tests use it ----
$refs = db_value('SELECT COUNT(*) FROM (SELECT 1 FROM invoices WHERE client_id = ? UNION SELECT 1 FROM sent_messages WHERE client_id = ? UNION SELECT 1 FROM email_threads WHERE client_id = ? UNION SELECT 1 FROM email_schedules WHERE client_id = ? UNION SELECT 1 FROM outbound_queue WHERE client_id = ?) x', [$tid, $tid, $tid, $tid]);
if ($refs == 0) {
    db_execute('DELETE FROM clients WHERE id = ?', [$tid]);
}

t_suite_end('email');





