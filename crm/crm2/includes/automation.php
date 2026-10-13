<?php
/* =============================================================================
   SPS CRM AUTOMATION ENGINE
   -----------------------------------------------------------------------------
   A Zapier-style workflow engine embedded in the CRM.

   Triggers  : schedule (cron), record create/update (poller), webhook, manual
   Logic     : conditions (AND/OR trees), if/else branches, loops, delays, stop
   Actions   : email, sms, http, update_record, insert_record, sql, log, notify
   Storage   : sps_automations, sps_automation_logs, sps_automation_queue,
               sps_automation_probe
   -----------------------------------------------------------------------------
   Public API:
     aut_ensure_tables($con)
     aut_dispatch($con, $wfId, $runType, $payload, $extras = [])
     aut_poll_once($con)          -- scheduler/poller tick
     aut_run_wf_now($con, $wfId)  -- manual "run now" (no trigger payload)
     aut_sample_row($con, $table, $id)
   ---------------------------------------------------------------------------*/

if (!function_exists('aut_ensure_tables')) {

/* ── object alias → real table + friendly placeholder name ─────────────── */
function aut_tables() {
    return [
        'members'               => 'client',
        'billing_invoice'       => 'invoice',
        'billing_invoice_instant' => 'instant_invoice',
        'billing_payment_history' => 'payment',
        'orders'                => 'order',
        'orders_completed'      => 'completed_order',
        'bank_statements'       => 'statement',
        'client_banks'          => 'bank',
        'quotations'            => 'quotation',
        'email_history'         => 'email',
        'memberships'           => 'membership',
        'users'                 => 'user',
        'member_services'       => 'service',
    ];
}
function aut_alias_to_table($alias) {
    $m = aut_tables();
    return isset($m[$alias]) ? $alias : (array_search($alias, $m) !== false ? array_search($alias, $m) : ($alias !== '' ? $alias : null));
}

/* ── table DDL ─────────────────────────────────────────────────────────── */
function aut_ensure_tables($con) {
    $con->query("CREATE TABLE IF NOT EXISTS sps_automations (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        status TINYINT NOT NULL DEFAULT 1,
        trigger_type ENUM('schedule','record','webhook','manual') NOT NULL DEFAULT 'manual',
        trigger_config LONGTEXT NULL,
        steps LONGTEXT NULL,
        run_count INT NOT NULL DEFAULT 0,
        last_run_at DATETIME NULL,
        last_run_status VARCHAR(40) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $con->query("CREATE TABLE IF NOT EXISTS sps_automation_logs (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        automation_id INT NOT NULL,
        run_type VARCHAR(20) NOT NULL,
        object_type VARCHAR(100) NULL,
        object_id INT NULL,
        status VARCHAR(20) NOT NULL,
        message TEXT NULL,
        steps_log LONGTEXT NULL,
        started_at DATETIME NULL,
        finished_at DATETIME NULL,
        KEY idx_wf (automation_id, id),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $con->query("CREATE TABLE IF NOT EXISTS sps_automation_queue (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        automation_id INT NOT NULL,
        resume_step INT NOT NULL DEFAULT 0,
        payload LONGTEXT NULL,
        run_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME NULL,
        KEY idx_runat (run_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $con->query("CREATE TABLE IF NOT EXISTS sps_automation_probe (
        wf_id INT NOT NULL,
        object_type VARCHAR(100) NOT NULL,
        object_id INT NOT NULL,
        row_hash CHAR(32) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (wf_id, object_type, object_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ── tiny helpers ──────────────────────────────────────────────────────── */
function aut_json($v) { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function aut_cfg($wf) {
    $t = json_decode((string)($wf['trigger_config'] ?? '{}'), true);
    return is_array($t) ? $t : [];
}
function aut_steps($wf) {
    $s = json_decode((string)($wf['steps'] ?? '[]'), true);
    return is_array($s) ? $s : [];
}
function aut_sanitize_steps($steps) {
    $allowed = ['type','label','to','subject','body','from_email','from_name','minutes','url','method','headers',
        'content_type','object_type','id','fields','sql','action','object_id','user_id','description','recipients',
        'message','condition','then','else','steps','messaging_sid','op','f','v'];
    $out = [];
    foreach ((array)$steps as $s) {
        if (!is_array($s)) continue;
        $clean = [];
        foreach ($s as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            if (in_array($k, ['then','else','steps'])) {
                if (is_array($v)) $clean[$k] = aut_sanitize_steps($v);
                else $clean[$k] = [];
            } elseif ($k === 'condition') {
                $clean[$k] = aut_sanitize_cond($v);
            } elseif ($k === 'headers' || $k === 'fields') {
                $clean[$k] = is_array($v) ? $v : [];
            } else {
                $clean[$k] = $v;
            }
        }
        if (!empty($clean['type'])) $out[] = $clean;
    }
    return $out;
}
function aut_sanitize_cond($node) {
    if (!is_array($node)) return [];
    if (isset($node['op'])) {
        return ['f' => (string)($node['f'] ?? ''), 'op' => (string)($node['op'] ?? 'eq'), 'v' => ($node['v'] ?? '')];
    }
    $out = [];
    foreach (['and','or'] as $k) {
        if (isset($node[$k]) && is_array($node[$k])) {
            $out[$k] = array_map('aut_sanitize_cond', $node[$k]);
        }
    }
    if (isset($node['not'])) $out['not'] = aut_sanitize_cond($node['not']);
    return $out;
}
function aut_exec($con, $sql) { return @mysqli_query($con, $sql); }
function aut_q($con, string $sql) : array {
    $r = aut_exec($con, $sql);
    $out = [];
    if ($r) { while ($row = mysqli_fetch_assoc($r)) $out[] = $row; }
    return $out;
}
function aut_now() { return date('Y-m-d H:i:s'); }

/* ── placeholder resolution ────────────────────────────────────────────── */
function aut_placeholder($str, $ctx) {
    if ($str === null) return '';
    if (!is_string($str)) return $str;
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($ctx) {
        $key = $m[1];
        // fixed now.* tokens (no spaces/hyphens survive placeholder regex)
        if (strpos($key, 'now.') === 0) {
            $t = substr($key, 4);
            $map = ['date'=>'Y-m-d','datetime'=>'Y-m-d H:i:s','year'=>'Y','month'=>'m','day'=>'d','hour'=>'H','minute'=>'i','second'=>'s','weekday'=>'l','timestamp'=>'U'];
            return isset($map[$t]) ? date($map[$t]) : '';
        }
        $parts = explode('.', $key, 2);
        $root = $parts[0];
        $rest = $parts[1] ?? '';
        if ($root === 'meta') {
            if ($rest === 'run_id' && isset($ctx['meta']['run_id'])) return (string)$ctx['meta']['run_id'];
            if ($rest === 'wf_id' && isset($ctx['meta']['wf_id'])) return (string)$ctx['meta']['wf_id'];
            if ($rest === 'wf_name' && isset($ctx['meta']['wf_name'])) return (string)$ctx['meta']['wf_name'];
            if ($rest === 'run_type' && isset($ctx['meta']['run_type'])) return (string)$ctx['meta']['run_type'];
            return '';
        }
        if ($root === 'trigger') {
            if ($rest !== '' && isset($ctx['trigger'][$rest])) return (string)$ctx['trigger'][$rest];
            if (is_array($ctx['trigger'])) return '';
            return '';
        }
        if ($root === 'item' && isset($ctx['item']) && is_array($ctx['item'])) {
            if ($rest !== '' && array_key_exists($rest, $ctx['item'])) return (string)$ctx['item'][$rest];
            return $rest !== '' && isset($ctx['item'][$rest]) ? (string)$ctx['item'][$rest] : '';
        }
        // object or friendly alias (client/invoice/...) → the trigger row
        if ($root === 'object' || $root === ($ctx['object_alias'] ?? 'object')) {
            if ($ctx['object'] !== null && array_key_exists($rest, (array)$ctx['object'])) return (string)$ctx['object'][$rest];
            return '';
        }
        return '';
    }, (string)$str);
}

/* ── condition evaluation ──────────────────────────────────────────────── */
function aut_val(&$ctx, $field) {
    if ($field === null) return null;
    if (is_numeric($field) && !is_string($field)) return (string)$field;
    $f = aut_placeholder((string)$field, $ctx);
    // object.field alias syntax
    if (preg_match('/^object\.(.+)$/', $f, $mm) && isset($ctx['object'][$mm[1]])) return (string)$ctx['object'][$mm[1]];
    if (preg_match('/^' . preg_quote($ctx['object_alias'] ?? 'object', '/') . '\.(.+)$/', $f, $mm) && isset($ctx['object'][$mm[1]])) return (string)$ctx['object'][$mm[1]];
    if (preg_match('/^item\.(.+)$/', $f, $mm) && isset($ctx['item'][$mm[1]])) return (string)$ctx['item'][$mm[1]];
    return $f;
}

function aut_eval_cond($node, $ctx) {
    if (!is_array($node)) return (bool)$node;
    if (isset($node['op'])) {
        return aut_cmp($node['op'], aut_val($ctx, $node['f'] ?? ''), aut_val($ctx, $node['v'] ?? ''), $node['f'] ?? '', $ctx);
    }
    $keys = array_keys($node);
    $first = $keys[0] ?? '';
    $list = $node[$first] ?? [];
    if (!is_array($list)) $list = [];
    if ($first === 'and') { foreach ($list as $n) { if (!aut_eval_cond($n, $ctx)) return false; } return true; }
    if ($first === 'or')  { foreach ($list as $n) { if (aut_eval_cond($n, $ctx)) return true; } return false; }
    if ($first === 'not') { return !aut_eval_cond($list, $ctx); }
    return true;
}

function aut_cmp($op, $a, $b, $field, $ctx) {
    $a = trim((string)$a);
    $b = trim((string)$b);
    switch ($op) {
        case 'eq':      return $a === $b;
        case 'neq':     return $a !== $b;
        case 'contains':return $b !== '' && strpos($a, $b) !== false;
        case 'ncontains':return $b === '' || strpos($a, $b) === false;
        case 'gt':      return is_numeric($a) && is_numeric($b) ? (float)$a > (float)$b : $a > $b;
        case 'gte':     return is_numeric($a) && is_numeric($b) ? (float)$a >= (float)$b : $a >= $b;
        case 'lt':      return is_numeric($a) && is_numeric($b) ? (float)$a < (float)$b : $a < $b;
        case 'lte':     return is_numeric($a) && is_numeric($b) ? (float)$a <= (float)$b : $a <= $b;
        case 'in':      $list = array_map('trim', explode(',', $b)); return in_array($a, $list, true);
        case 'nin':     $list = array_map('trim', explode(',', $b)); return !in_array($a, $list, true);
        case 'empty':   return $a === '' || $a === null;
        case 'nempty':  return $a !== '' && $a !== null;
        case 'regex':   return $b !== '' && @preg_match($b, $a) === 1;
        default:        return true;
    }
}

/* ── record fetch ──────────────────────────────────────────────────────── */
function aut_sample_row($con, $table, $id) {
    return aut_fetch_row($con, $table, $id);
}
function aut_row_by_pk($con, $table) {
    // discover primary key column
    $cols = aut_q($con, "SHOW COLUMNS FROM `$table`");
    foreach ($cols as $c) if (($c['Key'] ?? '') === 'PRI') return $c['Field'];
    return 'id';
}
function aut_fetch_row($con, $table, $id) {
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', aut_alias_to_table((string)$table));
    if ($t === '') return [];
    $pk = aut_row_by_pk($con, $t);
    $i = (int)$id;
    $r = aut_q($con, "SELECT * FROM `$t` WHERE `$pk` = $i LIMIT 1");
    return $r[0] ?? [];
}

/* ── EMAIL action ──────────────────────────────────────────────────────── */
function aut_email_module($con) {
    $r = aut_q($con, "SELECT config FROM email_module WHERE kind='account' AND id=1 LIMIT 1");
    $j = json_decode((string)($r[0]['config'] ?? '{}'), true);
    return is_array($j) ? $j : [];
}
function aut_send_email($con, $to, $subject, $body, $fromOverride = '', $fromNameOverride = '') {
    $cfg = aut_email_module($con);
    if ($to === '' || strpos($to, '@') === false) return 'No valid recipient';
    require_once __DIR__ . '/../PHPMailer/PHPMailerAutoload.php';
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['host']      ?? 'smtp.office365.com';
        $mail->Port       = (int)($cfg['port'] ?? 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username']  ?? '';
        $mail->Password   = $cfg['password']  ?? '';
        $mail->SMTPSecure = $cfg['secure']    ?? 'tls';
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($fromOverride ?: ($cfg['from_email'] ?? ''), $fromNameOverride ?: ($cfg['from_name'] ?? 'SPS CRM'));
        if ($mail->From === '') return 'SMTP "from" email not configured';
        foreach (array_map('trim', explode(',', $to)) as $rcpt) { if ($rcpt !== '') $mail->addAddress($rcpt); }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>','<br />','</p>','</div>','</tr>'], ["\n","\n","\n","\n","\n"], $body));
        if (!$mail->send()) return 'SMTP error: ' . $mail->ErrorInfo;
        return true;
    } catch (Exception $e) {
        return 'SMTP error: ' . $e->getMessage();
    }
}

/* ── SMS action ────────────────────────────────────────────────────────── */
function aut_send_sms($con, $to, $body, $messaging_sid = '') {
    if ($to === '') return 'No phone recipient';
    $sid = defined('TWILIO_ACCOUNT_SID')  ? TWILIO_ACCOUNT_SID  : '';
    $tok = defined('TWILIO_AUTH_TOKEN')   ? TWILIO_AUTH_TOKEN   : '';
    $from = $messaging_sid ?: (defined('TWILIO_MESSAGING_SID') ? TWILIO_MESSAGING_SID : (defined('TWILIO_PHONE_NUMBER') ? TWILIO_PHONE_NUMBER : ''));
    if ($sid === '' || $tok === '') return 'Twilio not configured';
    require_once __DIR__ . '/../Twilio/autoload.php';
    try {
        $twilio = new Twilio\Rest\Client($sid, $tok);
        $payload = ['body' => $body];
        $digits = preg_replace('/\D/', '', $to);
        if (strpos($from, 'MG') === 0) { $payload['messagingServiceSid'] = $from; }
        else { $payload['from'] = $from; }
        if ($digits !== '' && $digits[0] !== '+') $digits = ($digits[0] === '0' ? '92' : '+') . $digits;
        $twilio->messages->create((strlen($digits) < 12 && $digits[0] !== '+') ? '+' . $digits : ('+' === $digits[0] ? $digits : '+' . $digits), $payload);
        return true;
    } catch (Exception $e) {
        return 'Twilio error: ' . $e->getMessage();
    }
}

/* ── HTTP action ───────────────────────────────────────────────────────── */
function aut_http($url, $method, $headers, $body, $contentType) {
    if ($url === '') return 'No URL';
    $ch = curl_init($url);
    $hdrs = [];
    if (is_array($headers)) foreach ($headers as $k => $v) $hdrs[] = "$k: $v";
    if ($contentType !== '') $hdrs[] = 'Content-Type: ' . $contentType;
    $method = strtoupper($method ?: 'GET');
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => true];
    if ($hdrs) $opts[CURLOPT_HTTPHEADER] = $hdrs;
    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
        $opts[CURLOPT_POSTFIELDS] = (string)$body;
    } else if ($method === 'DELETE') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err !== '') return 'HTTP error: ' . $err;
    if ($code >= 400) return "HTTP $code: " . substr((string)$resp, 0, 200);
    return true . ' (HTTP ' . $code . ')';
}

/* ── activity log ──────────────────────────────────────────────────────── */
function aut_log_activity($con, $userId, $action, $objectType, $objectId, $description) {
    $uid = (int)$userId;
    $oid = ($objectId === '' || $objectId === null) ? 'NULL' : (int)$objectId;
    $u = $uid > 0 ? $uid : 'NULL';
    $a = mysqli_real_escape_string($con, (string)$action);
    $o = mysqli_real_escape_string($con, (string)$objectType);
    $d = mysqli_real_escape_string($con, (string)$description);
    $ip = mysqli_real_escape_string($con, $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    $ua = mysqli_real_escape_string($con, $_SERVER['HTTP_USER_AGENT'] ?? '');
    return aut_exec($con, "INSERT INTO activity_logs (user_id,action,object_type,object_id,description,ip_address,user_agent) VALUES ($u,'$a','$o',$oid,'$d','$ip','$ua')");
}

/* ── chat notify ───────────────────────────────────────────────────────── */
function aut_notify($con, $recipients, $message) {
    $ids = [];
    $m = mysqli_real_escape_string($con, (string)$message);
    if (is_string($recipients)) $recipients = [$recipients];
    foreach ((array)$recipients as $r) {
        $r = strtolower(trim((string)$r));
        if ($r === '') continue;
        if (is_numeric($r)) { $ids[] = (int)$r; continue; }
        if ($r === 'admins') {
            foreach (aut_q($con, "SELECT user_id FROM users WHERE user_type IN (1,2) AND user_login_status=1") as $u) $ids[] = (int)$u['user_id'];
        } elseif ($r === 'all') {
            foreach (aut_q($con, "SELECT user_id FROM users WHERE user_login_status=1") as $u) $ids[] = (int)$u['user_id'];
        }
    }
    $ids = array_unique($ids);
    // sender must reference a real user (chat_messages FK)
    $sender = (int)($_SESSION['user_id'] ?? 0);
    if ($sender <= 0) {
        $admin = aut_q($con, "SELECT user_id FROM users WHERE user_type IN (1,2) AND user_login_status=1 ORDER BY user_id LIMIT 1");
        $sender = (int)($admin[0]['user_id'] ?? 0);
    }
    $n = 0;
    foreach ($ids as $uid) {
        if ($sender > 0 && aut_exec($con, "INSERT INTO chat_messages (sender_id,recipient_id,message,sent_at,is_read) VALUES ($sender,$uid,'$m',NOW(),0)")) $n++;
    }
    return $n . ' notified';
}

/* ── step runner ───────────────────────────────────────────────────────── */
function aut_run_steps($con, $wf, &$ctx, $steps, &$stepLog, &$hasErrors, $startIdx = 0) {
    $idx = $startIdx;
    $count = is_array($steps) ? count($steps) : 0;
    while ($idx < $count) {
        $step = $steps[$idx];
        if (!is_array($step)) { $idx++; continue; }
        $t = $step['type'] ?? '';
        $label = $step['label'] ?? $t;
        $r = aut_exec_step($con, $wf, $ctx, $step, $hasErrors, $stepLog);
        $stepLog[] = ['step' => $idx, 'type' => $t, 'label' => $label, 'ok' => ($r['continue'] ?? true), 'result' => $r['msg'] ?? '', 'mod' => $r['mod'] ?? ''];
        if (($r['continue'] ?? true) === false) return; // stop / delay / fatal
        $idx++;
    }
}

function aut_exec_step($con, $wf, &$ctx, $step, &$hasErrors, &$stepLog) {
    $t = $step['type'] ?? '';
    try {
        switch ($t) {
            case 'stop':
                return ['continue' => false, 'msg' => 'Workflow stopped'];
            case 'delay': {
                $mins = max(1, (int)(aut_placeholder($step['minutes'] ?? '1', $ctx)));
                $payload = aut_json($ctx);
                if (!isset($ctx['meta'])) $ctx['meta'] = [];
                $runAt = date('Y-m-d H:i:s', time() + $mins * 60);
                aut_exec($con, "INSERT INTO sps_automation_queue (automation_id,resume_step,payload,run_at) VALUES (" . (int)$wf['id'] . "," . (int)($step['_resume'] ?? 0) . ",'" . mysqli_real_escape_string($con, $payload) . "','$runAt')");
                $ctx['_paused'] = true;
                return ['continue' => false, 'msg' => "Paused; resuming in $mins minute(s)"];
            }
            case 'email': {
                $to = aut_placeholder($step['to'] ?? '', $ctx);
                $subj = aut_placeholder($step['subject'] ?? '', $ctx);
                $body = aut_placeholder($step['body'] ?? '', $ctx);
                $r = aut_send_email($con, $to, $subj, $body, $step['from_email'] ?? '', $step['from_name'] ?? '');
                if ($r !== true) { $hasErrors = true; return ['continue' => true, 'msg' => (string)$r]; }
                // record in history if object is a client/invoice
                $cid = isset($ctx['object']['member_id']) ? (int)$ctx['object']['member_id'] : 0;
                $iid = isset($ctx['object']['invoice_id']) ? (int)$ctx['object']['invoice_id'] : 0;
                if ($cid > 0) {
                    $e = mysqli_real_escape_string($con, $subj); $b = mysqli_real_escape_string($con, $body);
                    $tr = mysqli_real_escape_string($con, $to);
                    $fr = mysqli_real_escape_string($con, $step['from_email'] ?? 'automation');
                    $snd = (int)($_SESSION['user_id'] ?? 0);
                    if ($snd <= 0) { $ad = aut_q($con, "SELECT user_id FROM users WHERE user_type IN (1,2) AND user_login_status=1 ORDER BY user_id LIMIT 1"); $snd = (int)($ad[0]['user_id'] ?? 0); }
                    $sb = $snd > 0 ? $snd : 'NULL';
                    aut_exec($con, "INSERT INTO email_history (client_id,invoice_id,type,subject,body,To_mail,From_mail,sent_by,sent_at) VALUES ($cid,$iid,'general','$e','$b','$tr','$fr',$sb,NOW())");
                }
                return ['continue' => true, 'msg' => "Email sent to $to"];
            }
            case 'sms': {
                $to = aut_placeholder($step['to'] ?? '', $ctx);
                $body = aut_placeholder($step['body'] ?? '', $ctx);
                $r = aut_send_sms($con, $to, $body, aut_placeholder($step['messaging_sid'] ?? '', $ctx));
                if ($r !== true) { $hasErrors = true; return ['continue' => true, 'msg' => (string)$r]; }
                return ['continue' => true, 'msg' => "SMS sent to $to"];
            }
            case 'http': {
                $url = aut_placeholder($step['url'] ?? '', $ctx);
                $method = $step['method'] ?? 'GET';
                $headers = [];
                foreach ((array)($step['headers'] ?? []) as $k => $v) $headers[$k] = aut_placeholder($v, $ctx);
                $body = aut_placeholder($step['body'] ?? '', $ctx);
                $ct = aut_placeholder($step['content_type'] ?? '', $ctx);
                $r = aut_http($url, $method, $headers, $body, $ct);
                if ($r !== true) { $hasErrors = true; return ['continue' => true, 'msg' => (string)$r]; }
                return ['continue' => true, 'msg' => "HTTP $method ok"];
            }
            case 'update_record': {
                $table = aut_alias_to_table($step['object_type'] ?? '');
                $id = (int)aut_placeholder($step['id'] ?? '', $ctx);
                $fields = (array)($step['fields'] ?? []);
                if ($table === '' || $id <= 0 || !$fields) return ['continue' => true, 'msg' => 'update_record skipped (bad target)'];
                $set = []; $vals = [];
                foreach ($fields as $k => $v) { $set[] = "`" . preg_replace('/[^a-zA-Z0-9_]/', '', $k) . "`=?"; $vals[] = (string)aut_placeholder($v, $ctx); }
                $sql = "UPDATE `$table` SET " . implode(',', $set) . " WHERE " . aut_row_by_pk($con, $table) . "=$id";
                $stmt = mysqli_prepare($con, $sql);
                if ($stmt) { $types = str_repeat('s', count($vals)); mysqli_stmt_bind_param($stmt, $types, ...$vals); mysqli_stmt_execute($stmt); $n = mysqli_stmt_affected_rows($stmt); mysqli_stmt_close($stmt); }
                return ['continue' => true, 'msg' => "Updated $table #$id (" . (count($fields)) . ' field(s))'];
            }
            case 'insert_record': {
                $table = aut_alias_to_table($step['object_type'] ?? '');
                $fields = (array)($step['fields'] ?? []);
                if ($table === '' || !$fields) return ['continue' => true, 'msg' => 'insert_record skipped (no fields)'];
                $cols = []; $vals = [];
                foreach ($fields as $k => $v) { $cols[] = "`" . preg_replace('/[^a-zA-Z0-9_]/', '', $k) . "`"; $vals[] = (string)aut_placeholder($v, $ctx); }
                $sql = "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($vals), '?')) . ")";
                $stmt = mysqli_prepare($con, $sql);
                if ($stmt) { $types = str_repeat('s', count($vals)); mysqli_stmt_bind_param($stmt, $types, ...$vals); mysqli_stmt_execute($stmt); $newId = mysqli_insert_id($con); mysqli_stmt_close($stmt); }
                return ['continue' => true, 'msg' => "Inserted into $table (#" . ($newId ?? '?') . ')'];
            }
            case 'sql': {
                $sql = aut_placeholder($step['sql'] ?? '', $ctx);
                if (trim($sql) === '') return ['continue' => true, 'msg' => 'Empty SQL'];
                if (aut_exec($con, $sql)) {
                    $n = mysqli_affected_rows($con);
                    return ['continue' => true, 'msg' => 'SQL ok (' . $n . ' affected)'];
                } else { $hasErrors = true; return ['continue' => true, 'msg' => 'SQL error: ' . mysqli_error($con)]; }
            }
            case 'log': {
                $uid = (int)aut_placeholder($step['user_id'] ?? '0', $ctx);
                $oid = aut_placeholder($step['object_id'] ?? '', $ctx);
                aut_log_activity($con, $uid, $step['action'] ?? 'UPDATE', $step['object_type'] ?? '', ($oid === '' ? null : $oid), aut_placeholder($step['description'] ?? '', $ctx));
                return ['continue' => true, 'msg' => 'Activity logged'];
            }
            case 'notify': {
                $n = aut_notify($con, $step['recipients'] ?? 'admins', aut_placeholder($step['message'] ?? '', $ctx));
                return ['continue' => true, 'msg' => $n];
            }
            case 'condition': {
                $ok = aut_eval_cond($step['condition'] ?? [], $ctx);
                if ($ok) { if (isset($step['then']) && is_array($step['then'])) aut_run_steps($con, $wf, $ctx, $step['then'], $stepLog, $hasErrors); }
                else { if (isset($step['else']) && is_array($step['else'])) aut_run_steps($con, $wf, $ctx, $step['else'], $stepLog, $hasErrors); }
                return ['continue' => true, 'msg' => $ok ? 'Condition matched (then)' : 'Condition false (else)'];
            }
            case 'loop': {
                $sql = aut_placeholder($step['sql'] ?? '', $ctx);
                $rows = $sql !== '' ? aut_q($con, $sql) : [];
                $rows = array_slice($rows, 0, 1000);
                $body = is_array($step['steps'] ?? null) ? $step['steps'] : [];
                foreach ($rows as $row) { $ctx['item'] = $row; aut_run_steps($con, $wf, $ctx, $body, $stepLog, $hasErrors); }
                unset($ctx['item']);
                return ['continue' => true, 'msg' => 'Looped ' . count($rows) . ' row(s)'];
            }
            default:
                return ['continue' => true, 'msg' => 'Unknown step type "' . htmlspecialchars((string)$t) . '"'];
        }
    } catch (Exception $e) {
        $hasErrors = true;
        return ['continue' => true, 'msg' => 'Exception: ' . $e->getMessage()];
    }
}

/* ── dispatch ──────────────────────────────────────────────────────────── */
function aut_dispatch($con, $wfId, $runType, $payload, $extras = []) {
    $wf = aut_q($con, "SELECT * FROM sps_automations WHERE id=" . (int)$wfId . " LIMIT 1")[0] ?? null;
    if (!$wf) return ['ok' => false, 'msg' => 'Workflow not found'];
    if ((int)$wf['status'] !== 1 && $runType !== 'test') return ['ok' => false, 'msg' => 'Workflow disabled'];
    $steps = aut_steps($wf);
    $trig = aut_cfg($wf);

    $objectType = $payload['object_type'] ?? $trig['object'] ?? '';
    $objectId   = (int)($payload['object_id'] ?? 0);
    $row = [];
    $table = aut_alias_to_table($objectType);
    if ($table && $objectId > 0) $row = aut_fetch_row($con, $table, $objectId);
    if (!$row && !empty($payload['row']) && is_array($payload['row'])) $row = $payload['row'];

    $ctx = [
        'object' => $row,
        'object_type' => $objectType,
        'object_id' => $objectId,
        'object_alias' => $table !== null && isset(aut_tables()[$table]) ? aut_tables()[$table] : $objectType,
        'item' => null,
        'trigger' => array_merge(['type' => $runType, 'time' => aut_now(), 'user_id' => $extras['user_id'] ?? 0, 'user_name' => $extras['user_name'] ?? ''], (array)($payload['trigger'] ?? [])),
        'meta' => ['wf_id' => (int)$wf['id'], 'wf_name' => $wf['name'], 'run_type' => $runType, 'run_id' => 0],
        'data' => (array)($payload['data'] ?? []),
    ];

    $now = aut_now();
    $logId = null;
    $stmt = mysqli_prepare($con, "INSERT INTO sps_automation_logs (automation_id,run_type,object_type,object_id,status,started_at) VALUES (?,?,?,?,'running',?)");
    if ($stmt) { mysqli_stmt_bind_param($stmt, "issis", $wfId, $runType, $objectType, $objectId, $now); mysqli_stmt_execute($stmt); $logId = mysqli_insert_id($con); mysqli_stmt_close($stmt); }
    $ctx['meta']['run_id'] = (int)$logId;

    $stepLog = [];
    $hasErrors = false;
    // attach resume hints for delay steps
    for ($i = 0; $i < count($steps); $i++) { $steps[$i]['_index_next'] = $i + 1; $steps[$i]['_resume'] = $i + 1; }

    aut_run_steps($con, $wf, $ctx, $steps, $stepLog, $hasErrors);
    $paused = !empty($ctx['_paused']);

    $status = $paused ? 'paused' : ($hasErrors ? 'error' : 'success');
    if ($paused) $status = 'paused';
    $msg = count($stepLog) . ' step(s) executed' . ($hasErrors ? ' (some failed)' : '');
    aut_exec($con, "UPDATE sps_automation_logs SET status='$status', message='" . mysqli_real_escape_string($con, $msg) . "', steps_log='" . mysqli_real_escape_string($con, aut_json($stepLog)) . "', finished_at='" . aut_now() . "' WHERE id=$logId");
    aut_exec($con, "UPDATE sps_automations SET run_count=run_count+1, last_run_at='" . aut_now() . "', last_run_status='$status' WHERE id=" . (int)$wfId);

    return ['ok' => true, 'status' => $status, 'log_id' => $logId, 'steps' => $stepLog, 'msg' => $msg];
}

/* ── resume from queue ─────────────────────────────────────────────────── */
function aut_resume_queue_item($con, $queue) {
    $wf = aut_q($con, "SELECT * FROM sps_automations WHERE id=" . (int)$queue['automation_id'] . " LIMIT 1")[0] ?? null;
    if (!$wf) { aut_exec($con, "DELETE FROM sps_automation_queue WHERE id=" . (int)$queue['id']); return; }
    $ctx = json_decode((string)$queue['payload'], true);
    if (!is_array($ctx)) $ctx = ['object' => null];
    $steps = aut_steps($wf);
    // preserve run identity
    $logId = (int)($ctx['meta']['run_id'] ?? 0);
    $stepLog = [];
    $hasErrors = false;
    $start = []; $cur = null;
    aut_run_steps($con, $wf, $ctx, array_slice($steps, (int)$queue['resume_step']), $stepLog, $hasErrors, 0);
    $status = $hasErrors ? 'error' : 'success';
    $msg = count($stepLog) . ' step(s) executed (resumed)' . ($hasErrors ? ' (some failed)' : '');
    if ($logId) aut_exec($con, "UPDATE sps_automation_logs SET status='$status', message='" . mysqli_real_escape_string($con, $msg) . "', steps_log=CONCAT(COALESCE(steps_log,''), '{\"resumed\":true,\"row\":[" . mysqli_real_escape_string($con, aut_json($stepLog)) . "]}'), finished_at='" . aut_now() . "' WHERE id=$logId");
    aut_exec($con, "DELETE FROM sps_automation_queue WHERE id=" . (int)$queue['id']);
}

/* ── cron matching ─────────────────────────────────────────────────────── */
function aut_cron_match($expr, $nowArr) {
    if (!is_array($expr)) return false;
    foreach (['min' => $nowArr['min'], 'hour' => $nowArr['hour'], 'dom' => $nowArr['dom'], 'mon' => $nowArr['mon'], 'dow' => $nowArr['dow']] as $k => $v) {
        $field = isset($expr[$k]) && $expr[$k] !== null && $expr[$k] !== '' ? $expr[$k] : (($k === 'dom' || $k === 'mon') ? '1' : '*');
        if (!aut_cron_field($field, $v)) return false;
    }
    return true;
}
function aut_cron_field($field, $value) {
    foreach (explode(',', $field) as $part) {
        $step = 1;
        if (strpos($part, '/') !== false) list($r, $step) = explode('/', $part, 2);
        else $r = $part;
        $step = max(1, (int)$step);
        if ($r === '*') { if ($value % $step === 0) return true; continue; }
        if (strpos($r, '-') !== false) { list($a, $b) = array_map('intval', explode('-', $r, 2)); if ($value >= $a && $value <= $b && ($value - $a) % $step === 0) return true; continue; }
        if ((int)$r === (int)$value) return true;
    }
    return false;
}
function aut_parse_cron($s) {
    $parts = preg_split('/\s+/', trim($s));
    if (count($parts) < 5) return null;
    return ['min' => $parts[0], 'hour' => $parts[1], 'dom' => $parts[2], 'mon' => $parts[3], 'dow' => $parts[4]];
}

/* ── poller tick ───────────────────────────────────────────────────────── */
function aut_poll_once($con, $force = false) {
    aut_ensure_tables($con);
    $logDir = __DIR__ . '/error';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $lock = $logDir . '/.sps_automation_last_run';
    $nowT = time();
    // One poll per minute (skip if polled within last 50s) unless forced
    if (!$force && file_exists($lock)) {
        if ($nowT - @filemtime($lock) < 50) return ['ran' => false, 'reason' => 'throttled'];
    }
    @file_put_contents($lock, $nowT);
    $started = aut_now();
    $report = ['polled_at' => $started, 'schedules' => 0, 'created' => 0, 'updated' => 0, 'queue' => 0];

    // 1) queue first (delayed steps due now)
    $due = aut_q($con, "SELECT * FROM sps_automation_queue WHERE run_at <= '$started' ORDER BY id LIMIT 50");
    foreach ($due as $q) { aut_resume_queue_item($con, $q); $report['queue']++; }

    // 2) schedules
    $nowA = ['min' => (int)date('i'), 'hour' => (int)date('G'), 'dom' => (int)date('j'), 'mon' => (int)date('n'), 'dow' => (int)date('w')];
    $wfs = aut_q($con, "SELECT * FROM sps_automations WHERE status=1 AND trigger_type='schedule'");
    foreach ($wfs as $wf) {
        $cfg = aut_cfg($wf);
        $cronStr = $cfg['cron'] ?? ($cfg['cron_expr'] ?? '');
        $expr = aut_parse_cron($cronStr);
        if (!$expr) continue;
        // same-minute guard
        $last = strtotime((string)$wf['last_run_at'] ?: '2000-01-01 00:00:00');
        $thisMinute = mktime($nowA['hour'], $nowA['min'], 0, $nowA['mon'], $nowA['dom'], date('Y'));
        $lastMinute = $last !== false ? mktime((int)date('G', $last), (int)date('i', $last), 0, (int)date('n', $last), (int)date('j', $last), (int)date('Y', $last)) : 0;
        if ($lastMinute >= $thisMinute) continue;
        if (!aut_cron_match($expr, $nowA)) continue;
        $report['schedules']++;
        $query = $cfg['query'] ?? '';
        $qObj = trim($cfg['object'] ?? '');
        if (trim($query) !== '' && $qObj !== '') {
            $qTable = aut_alias_to_table($qObj);
            foreach (aut_q($con, $query) as $row) {
                $pk = aut_row_by_pk($con, $qTable ?: 'members');
                $oid = (int)($row[$pk] ?? 0);
                aut_dispatch($con, (int)$wf['id'], 'schedule', ['object_type' => $qObj, 'object_id' => $oid, 'row' => $row]);
            }
        } else {
            aut_dispatch($con, (int)$wf['id'], 'schedule', ['object_type' => $qObj, 'object_id' => 0, 'data' => []]);
        }
    }

    // 3) record create (watermark)
    $recWfs = aut_q($con, "SELECT * FROM sps_automations WHERE status=1 AND trigger_type='record'");
    foreach ($recWfs as $wf) {
        $cfg = aut_cfg($wf);
        $obj = $cfg['object'] ?? '';
        $table = aut_alias_to_table($obj);
        if ($table === '') continue;
        $evt = $cfg['event'] ?? 'created';
        $pk = aut_row_by_pk($con, $table);
        $maxQ = aut_q($con, "SELECT MAX(`$pk`) mx FROM `$table`");
        $max = (int)($maxQ[0]['mx'] ?? 0);
        $mark = (int)($cfg['watermark'] ?? 0);
        if ($mark === 0) {
            // baseline — never dispatch, just remember where we are
            $cfg['watermark'] = $max;
            $cfg2 = aut_json($cfg);
            aut_exec($con, "UPDATE sps_automations SET trigger_config='" . mysqli_real_escape_string($con, $cfg2) . "' WHERE id=" . (int)$wf['id']);
            continue;
        }
        if ($max > $mark) {
            if ($evt === 'updated') {
                // for updated events, initial scan marks probe, no dispatch
                $rows = aut_q($con, "SELECT `$pk` FROM `$table` WHERE `$pk` > $mark ORDER BY `$pk` ASC LIMIT 500");
                foreach ($rows as $r) { $id = (int)$r[$pk]; $row = aut_fetch_row($con, $table, $id); aut_probe_upsert($con, $wf, $table, $id, $row, true); }
            } else {
                $rows = aut_q($con, "SELECT `$pk` FROM `$table` WHERE `$pk` > $mark ORDER BY `$pk` ASC LIMIT 500");
                foreach ($rows as $r) {
                    $id = (int)$r[$pk];
                    aut_dispatch($con, (int)$wf['id'], 'record', ['object_type' => $obj, 'object_id' => $id]);
                    $report['created']++;
                }
            }
            $cfg['watermark'] = $max;
            aut_exec($con, "UPDATE sps_automations SET trigger_config='" . mysqli_real_escape_string($con, aut_json($cfg)) . "' WHERE id=" . (int)$wf['id']);
        }
        // 4) record update (probe) — also runs for event=updated on existing rows each tick
        if ($evt === 'updated') {
            $limit = max(10, min(500, (int)($cfg['probe_limit'] ?? 200)));
            $cols = (array)($cfg['fields'] ?? []);
            $rows = aut_q($con, "SELECT `$pk` FROM `$table` ORDER BY `$pk` DESC LIMIT $limit");
            foreach ($rows as $r) {
                $id = (int)$r[$pk];
                $row = aut_fetch_row($con, $table, $id);
                $report['updated'] += aut_probe_upsert($con, $wf, $table, $id, $row, false, $cols, $obj) ? 1 : 0;
            }
        }
    }

    return $report;
}

function aut_probe_upsert($con, $wf, $table, $id, $row, $baseline, $cols = [], $obj = '') {
    if (!$row) return false;
    $keys = $cols;
    if (!$keys) $keys = array_slice(array_keys($row), 0, 14);
    $hash = md5(serialize(array_intersect_key($row, array_flip($keys))));
    $wfi = (int)$wf['id'];
    $cur = aut_q($con, "SELECT row_hash FROM sps_automation_probe WHERE wf_id=$wfi AND object_type='" . mysqli_real_escape_string($con, $table) . "' AND object_id=$id LIMIT 1");
    $upd = "INSERT INTO sps_automation_probe (wf_id,object_type,object_id,row_hash) VALUES ($wfi,'" . mysqli_real_escape_string($con, $table) . "',$id,'$hash') ON DUPLICATE KEY UPDATE row_hash='$hash'";
    aut_exec($con, $upd);
    if ($baseline || !$cur) return false;
    if ($cur[0]['row_hash'] !== $hash) {
        aut_dispatch($con, $wfi, 'record', ['object_type' => $obj ?: $table, 'object_id' => $id]);
        return true;
    }
    return false;
}

/* ── manual run ────────────────────────────────────────────────────────── */
function aut_run_wf_now($con, $wfId, $payload = []) {
    aut_ensure_tables($con);
    $uid = $_SESSION['user_id'] ?? 0;
    return aut_dispatch($con, (int)$wfId, 'manual', ['object_type' => $payload['object_type'] ?? '', 'object_id' => (int)($payload['object_id'] ?? 0), 'data' => (array)($payload['data'] ?? [])], ['user_id' => $uid, 'user_name' => $_SESSION['fullname'] ?? '']);
}

} /* end function_exists guard */