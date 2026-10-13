<?php
/*  SPS ASSISTANT  ─  shared helper library
    -------------------------------------------------------------------------
    Central place for:
      • ai_get() / ai_set()   — key/value settings stored in settings_marker
      • ai_can()              — capability check (crm_can OR admin)
      • gemini_call()         — Google Gemini REST wrapper (text + vision)
      • ollama_call()         — local Ollama REST wrapper (offline LLM)
      • gemini_test()/ollama_test() — connectivity tests for the Settings page
      • ai_db_schema()        — table/column/relationship map
    ------------------------------------------------------------------------- */
if (!function_exists('ai_get')) {
    function ai_get($key, $default = '')
    {
        global $con;
        $key = mysqli_real_escape_string($con, $key);
        $q = mysqli_query($con, "SELECT marker_value FROM settings_marker WHERE marker_key='$key'");
        if ($q && mysqli_num_rows($q) > 0) {
            $r = mysqli_fetch_assoc($q);
            return $r['marker_value'];
        }
        return $default;
    }
}
if (!function_exists('ai_set')) {
    function ai_set($key, $value)
    {
        global $con;
        $key = mysqli_real_escape_string($con, $key);
        $value = mysqli_real_escape_string($con, $value);
        return mysqli_query($con, "INSERT INTO settings_marker (marker_key, marker_value)
                                   VALUES ('$key', '$value')
                                   ON DUPLICATE KEY UPDATE marker_value = VALUES(marker_value)");
    }
}
/* Effective capabilities: admin (user_type=1) always has them; otherwise the
   per-role/per-user permissions from the roles engine are used. */
if (!function_exists('ai_can')) {
    function ai_can($perm)
    {
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == '1') {
            return true;
        }
        if (function_exists('crm_can')) {
            return crm_can($perm);
        }
        return false;
    }
}
if (!function_exists('ai_can_use')) {
    function ai_can_use()
    {
        return ai_can('ai.use');
    }
}
if (!function_exists('ai_can_write')) {
    function ai_can_write()
    {
        return ai_can('ai.write');
    }
}
/* ------------ Gemini REST wrapper -------------------------------------- */
if (!function_exists('gemini_call')) {
    /* $parts = array of array('text'=>..., 'image_b64'=>..., 'mime'=>...) */
    function gemini_call($parts, $systemPrompt = '')
    {
        $apiKey = ai_get('ai_api_key', '');
        $model = ai_get('ai_model', 'gemini-3.6-flash');
        if (empty(trim($apiKey))) {
            return array('error' => 'NO_API_KEY', 'message' => 'The Gemini API key is not configured. Go to AI Settings and add your API key.');
        }
        $contents = array();
        $partsOut = array();
        foreach ($parts as $p) {
            if (!empty($p['file_b64'])) {
                /* Generic binary upload (PDF, image, etc.) passed to Gemini. */
                $partsOut[] = array(
                    'inline_data' => array(
                        'mime_type' => !empty($p['mime']) ? $p['mime'] : 'application/octet-stream',
                        'data' => $p['file_b64'],
                    ),
                );
            }
            if (!empty($p['image_b64'])) {
                $partsOut[] = array(
                    'inline_data' => array(
                        'mime_type' => !empty($p['mime']) ? $p['mime'] : 'image/png',
                        'data' => $p['image_b64'],
                    ),
                );
            }
            if (isset($p['text']) && $p['text'] !== '') {
                $partsOut[] = array('text' => $p['text']);
            }
        }
        $contents[] = array('parts' => $partsOut);
        $body = array('contents' => $contents);
        if ($systemPrompt !== '') {
            $body['system_instruction'] = array('parts' => array(array('text' => $systemPrompt)));
        }
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
        $json = json_encode($body);
        $ctx = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => 60,
                'ignore_errors' => true,
            ),
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false),
        ));
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return array('error' => 'NETWORK', 'message' => 'Could not reach the Gemini API (network/SSL). Please check your internet connection.');
        }
        $resp = json_decode($raw, true);
        if (isset($resp['candidates'][0]['content']['parts'][0]['text'])) {
            return array('text' => $resp['candidates'][0]['content']['parts'][0]['text']);
        }
        if (isset($resp['error']['message'])) {
            return array('error' => $resp['error']['message']);
        }
        return array('error' => 'Unexpected Gemini response: ' . substr($raw, 0, 500));
    }
}
if (!function_exists('gemini_test')) {
    function gemini_test()
    {
        $r = gemini_call(array(array('text' => 'Reply with exactly: OK')));
        if (isset($r['text'])) {
            return array('success' => true, 'message' => 'Connected. Model replied: ' . trim($r['text']));
        }
        return array('success' => false, 'message' => $r['error'] ?? 'Unknown error');
    }
}
/* ------------ Ollama (local LLM) REST wrapper ---------------------------- */
if (!function_exists('ollama_base_url')) {
    function ollama_base_url()
    {
        $u = ai_get('ai_ollama_url', 'http://localhost:11434');
        return rtrim($u, '/');
    }
}
if (!function_exists('ollama_model')) {
    function ollama_model()
    {
        return ai_get('ai_ollama_model', 'mistral');
    }
}
if (!function_exists('ollama_models_list')) {
    function ollama_models_list()
    {
        $url = ollama_base_url() . '/api/tags';
        $raw = @file_get_contents($url);
        if ($raw === false) { return array('error' => 'Could not reach Ollama at ' . $url . '. Is Ollama running?'); }
        $resp = json_decode($raw, true);
        if (!isset($resp['models'])) { return array('error' => 'Unexpected Ollama response.'); }
        $out = array();
        foreach ($resp['models'] as $m) {
            $out[] = array('name' => $m['name'] ?? '', 'size' => isset($m['size']) ? round($m['size'] / (1024 * 1024 * 1024), 1) . ' GB' : '', 'modified' => $m['modified_at'] ?? '');
        }
        return array('models' => $out);
    }
}
if (!function_exists('ollama_call')) {
    /* $parts = array of array('text'=>...) — Ollama only handles text for now.
       Returns array('text'=>...) or array('error'=>..., 'message'=>...). */
    function ollama_call($parts, $systemPrompt = '')
    {
        $url = ollama_base_url() . '/api/generate';
        $model = ollama_model();
        $prompt = '';
        foreach ($parts as $p) {
            if (isset($p['text']) && $p['text'] !== '') { $prompt .= $p['text'] . "\n"; }
        }
        $prompt = trim($prompt);
        if ($prompt === '') { $prompt = 'Hello.'; }
        $body = array(
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => array('temperature' => 0.3),
        );
        if ($systemPrompt !== '') { $body['system'] = $systemPrompt; }
        $json = json_encode($body);
        $ctx = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n",
                'content' => $json,
                'timeout' => 300,
                'ignore_errors' => true,
            ),
        ));
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return array('error' => 'OLLAMA_UNREACHABLE', 'message' => 'Could not reach the Ollama server at ' . $url . '. Is it running and not busy?');
        }
        $resp = json_decode($raw, true);
        if (isset($resp['response'])) {
            return array('text' => trim($resp['response']));
        }
        if (isset($resp['error'])) {
            return array('error' => $resp['error']);
        }
        return array('error' => 'Unexpected Ollama response: ' . substr($raw, 0, 500));
    }
}
if (!function_exists('ollama_test')) {
    function ollama_test()
    {
        $r = ollama_call(array(array('text' => 'Reply with exactly: OK')));
        if (isset($r['text'])) {
            return array('success' => true, 'message' => 'Local LLM connected. Model replied: ' . trim($r['text']));
        }
        return array('success' => false, 'message' => $r['message'] ?? ($r['error'] ?? 'Unknown error'));
    }
}
/* ------------ Groq (free cloud LLM) wrapper ------------------------------ */
if (!function_exists('ai_groq_key')) {
    function ai_groq_key() { return ai_get('ai_groq_key', ''); }
}
if (!function_exists('ai_groq_model')) {
    function ai_groq_model() { return ai_get('ai_groq_model', 'llama-3.3-70b-versatile'); }
}
if (!function_exists('groq_call')) {
    /* $parts = array of array('text'=>...) — Groq is text-only here (OCR stays on Gemini vision). */
    function groq_call($parts, $systemPrompt = '')
    {
        $apiKey = trim(ai_groq_key());
        if ($apiKey === '') { return array('error' => 'NO_GROQ_KEY', 'message' => 'No Groq API key configured.'); }
        $messages = array();
        if ($systemPrompt !== '') { $messages[] = array('role' => 'system', 'content' => $systemPrompt); }
        $userText = '';
        foreach ($parts as $p) {
            if (isset($p['text']) && $p['text'] !== '') { $userText .= $p['text'] . "\n"; }
        }
        $userText = trim($userText);
        if ($userText === '') { $userText = 'Hello.'; }
        $messages[] = array('role' => 'user', 'content' => $userText);
        $url = 'https://api.groq.com/openai/v1/chat/completions';
        $body = array('model' => ai_groq_model(), 'messages' => $messages, 'temperature' => 0.3, 'max_tokens' => 4096);
        $json = json_encode($body);
        $ctx = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Authorization: Bearer " . $apiKey . "\r\nContent-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\n",
                'content' => $json,
                'timeout' => 120,
                'ignore_errors' => true,
            ),
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false),
        ));
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) { return array('error' => 'NETWORK', 'message' => 'Could not reach the Groq API (network/SSL). Check your internet connection.'); }
        $resp = json_decode($raw, true);
        if (isset($resp['choices'][0]['message']['content'])) { return array('text' => trim($resp['choices'][0]['message']['content'])); }
        if (isset($resp['error']['message'])) { return array('error' => $resp['error']['message']); }
        return array('error' => 'Unexpected Groq response: ' . substr($raw, 0, 300));
    }
}
if (!function_exists('groq_test')) {
    function groq_test()
    {
        $r = groq_call(array(array('text' => 'Reply with exactly: OK')));
        if (isset($r['text'])) { return array('success' => true, 'message' => 'Groq fallback connected. Model replied: ' . trim($r['text'])); }
        return array('success' => false, 'message' => $r['message'] ?? ($r['error'] ?? 'Unknown error'));
    }
}
if (!function_exists('ai_groq_status')) {
    /* Cached Groq reachability so we don't ping the API on every check. */
    function ai_groq_status($force = false)
    {
        $haveKey = (trim(ai_groq_key()) !== '');
        if (!$haveKey) { return array('ok' => false, 'set' => false); }
        $ts = (int) ai_get('ai_status_groq_at', 0);
        $cur = ai_get('ai_status_groq', '0');
        if (!$force && (time() - $ts) < 120) { return array('ok' => ($cur === '1'), 'set' => true); }
        $t = groq_test();
        $ok = !empty($t['success']) ? '1' : '0';
        ai_set('ai_status_groq', $ok);
        ai_set('ai_status_groq_at', (string) time());
        return array('ok' => ($ok === '1'), 'set' => true);
    }
}
/* ------------ Backend (online Gemini / offline Ollama) mode -------------- */
if (!function_exists('ai_mode')) {
    function ai_mode()
    {
        $m = ai_get('ai_mode', 'online');
        return ($m === 'offline') ? 'offline' : 'online';
    }
}
if (!function_exists('ai_switch_mode')) {
    function ai_switch_mode($mode)
    {
        ai_set('ai_mode', ($mode === 'offline') ? 'offline' : 'online');
        return ai_mode();
    }
}
if (!function_exists('ai_backend_status')) {
    /* Check which backend(s) are currently reachable. Gemini/Groq results are
       cached (120s) so repeated checks don't burn API quota. Returns:
       array('online'=>bool, 'offline'=>bool, 'current'=>'online'|'offline',
             'gemini_ok'=>bool, 'groq_ok'=>bool, 'ollama_ok'=>bool,
             'api_key_set'=>bool, 'groq_key_set'=>bool, 'ollama_model'=>string,
             'provider'=Chosen active provider for messaging, 'message'=>...) */
    function ai_backend_status()
    {
        $geminiOk = false;
        $haveKey = (trim(ai_get('ai_api_key', '')) !== '');
        if ($haveKey) {
            $ts = (int) ai_get('ai_status_gemini_at', 0);
            $cur = ai_get('ai_status_gemini', '0');
            if ((time() - $ts) < 120) {
                $geminiOk = ($cur === '1');
            } else {
                $t = gemini_test();
                $geminiOk = !empty($t['success']);
                ai_set('ai_status_gemini', $geminiOk ? '1' : '0');
                ai_set('ai_status_gemini_at', (string) time());
            }
        }
        $groq = ai_groq_status();
        $ollamaOk = false;
        $l = ollama_models_list();
        if (!isset($l['error'])) { $ollamaOk = true; }
        $active = 'none';
        $msg = 'No AI backend reachable.';
        if ($geminiOk) { $active = 'gemini'; $msg = 'Gemini is online' . ($ollamaOk ? ' and Ollama is available.' : '.') . ($groq['ok'] ? ' Groq fallback ready.' : ''); }
        elseif ($groq['ok']) { $active = 'groq'; $msg = $ollamaOk ? 'Groq fallback online, Ollama available.' : 'Gemini is down — using the Groq cloud fallback.'; }
        return array(
            'online'  => $geminiOk || $groq['ok'],
            'offline' => $ollamaOk,
            'current' => ai_mode(),
            'gemini_ok' => $geminiOk,
            'groq_ok' => $groq['ok'],
            'groq_key_set' => $groq['set'],
            'ollama_ok' => $ollamaOk,
            'api_key_set' => $haveKey,
            'ollama_model' => ollama_model(),
            'provider' => $active,
            'message' => $msg,
        );
    }
}
if (!function_exists('ai_backend_call')) {
    /* Route a chat prompt to the active backend chain:
       online  → Gemini → Groq (cloud fallback) → Ollama
       offline → Ollama → Gemini → Groq
       Returns array('text'=>...) or the first provider's error. */
    function ai_backend_call($parts, $systemPrompt)
    {
        $chain = (ai_mode() === 'offline')
            ? array('ollama_call', 'gemini_call', 'groq_call')
            : array('gemini_call', 'groq_call', 'ollama_call');
        $first = null;
        foreach ($chain as $fn) {
            $r = $fn($parts, $systemPrompt);
            if (isset($r['text'])) { return $r; }
            if ($first === null) { $first = $r; }
        }
        return $first;
    }
}
/* ------------ DB relationship / schema map ------------------------------ */
if (!function_exists('ai_db_schema')) {
    function ai_db_schema()
    {
        return array(
            'modules' => array(
                'client'     => array('table' => 'members',                 'pk' => 'member_id', 'label' => 'Client (member)'),
                'invoice'    => array('table' => 'billing_invoice',         'pk' => 'invoice_id', 'label' => 'Invoice'),
                'instant_invoice' => array('table' => 'billing_invoice_instant', 'pk' => 'invoice_id', 'label' => 'Instant / one-off invoice'),
                'invoice_item'=> array('table' => 'billing_invoice_items',  'pk' => 'inv_item_id', 'label' => 'Invoice line item'),
                'invoice_status' => array('table' => 'billing_invoice_status', 'pk' => 'status_id', 'label' => 'Invoice status'),
                'project'    => array('table' => 'projects',                'pk' => 'order_id', 'label' => 'Project'),
                'order'      => array('table' => 'orders',                  'pk' => 'order_id', 'label' => 'Service / order'),
                'membership' => array('table' => 'memberships',             'pk' => 'membership_id', 'label' => 'Service / product'),
                'note'       => array('table' => 'notestab',                'pk' => 'note_id', 'label' => 'Client note'),
                'comment'    => array('table' => 'comments',                'pk' => 'comment_id', 'label' => 'Client comment'),
                'payment_history' => array('table' => 'billing_payment_history', 'pk' => 'his_id', 'label' => 'Payment history'),
                'payment_type' => array('table' => 'billing_payment_type',  'pk' => 'id', 'label' => 'Payment type'),
                'estimate'   => array('table' => 'quotations',              'pk' => 'quotation_id', 'label' => 'Estimate / quotation'),
                'plan'       => array('table' => 'payment_plans',           'pk' => 'plan_id', 'label' => 'Payment plan'),
                'plan_item'  => array('table' => 'payment_plan_items',      'pk' => 'item_id', 'label' => 'Payment plan line item'),
                'member_service' => array('table' => 'member_services',     'pk' => 'id', 'label' => 'Client service assignment'),
                'billing_info'=> array('table' => 'billing_info',           'pk' => 'billing_id', 'label' => 'Billing cycle info'),
                'user'       => array('table' => 'users',                   'pk' => 'user_id', 'label' => 'Staff / user'),
                'activity'   => array('table' => 'activity_logs',           'pk' => 'id', 'label' => 'Activity log'),
            ),
            'relationships' => array(
                'members.member_id = billing_invoice.mem_id',
                'members.member_id = billing_invoice_instant.mem_id',
                'members.member_id = billing_invoice_items.mem_id',
                'members.member_id = projects.mem_id',
                'members.member_id = orders.mem_id',
                'members.member_id = notestab.member_id',
                'members.member_id = comments.member_id',
                'members.member_id = billing_payment_history.mem_id',
                'members.member_id = quotations.member_id',
                'members.member_id = payment_plans.client_id',
                'members.member_id = member_services.member_id',
                'members.member_id = billing_info.mem_id',
                'members.member_id = activity_logs.record_id',
                'members.member_id = documents.client_id',
                'members.member_id = spouse.member_id',
                'members.member_id = billing_balance_adjustment.mem_id',
                'billing_invoice.invoice_id = billing_invoice_items.inv_id',
                'billing_invoice_instant.invoice_id = billing_invoice_items.inv_id',
                'billing_invoice.invoice_id = billing_payment_history.inv_id',
                'billing_invoice.invoice_id = new_comments.invoice_id',
                'orders.prod_id = memberships.membership_id',
                'projects.prod_id = memberships.membership_id',
                'member_services.membership_id = memberships.membership_id',
                'billing_invoice.invoice_status = billing_invoice_status.status_id',
                'billing_invoice.payment_type = billing_payment_type.id',
                'billing_invoice_instant.payment_type = billing_payment_type.id',
                'members.type = member_type.type_id',
                'payment_plans.plan_id = payment_plan_items.plan_id',
                'quotations.quotation_id = quotation_notes.quotation_id',
                'orders.mem_id = members.member_id',
                'orders.prod_id = memberships.membership_id',
                'users.user_id = activity_logs.user_id',
            ),
        );
    }
}
if (!function_exists('ai_module_descriptions')) {
    function ai_module_descriptions()
    {
        return array(
            'client'  => 'A client is stored in the members table. Key fields: member_id (id), name (first name), mname, lname, bus_name (business name), phone (work), cell_ph (cell), prm_email (primary email), sec_email, address/city/state/zip (business address), hstreet/hcity/hstate/hzip (home), dob, ss (SSN/ITIN), ein, type (client type id), status (1=active). To ADD a client, respond with NEWCLIENT: followed by JSON. Available client types (type): 1=Individual, 2=Business, 3=Corporation, 4=Partnership, 5=Non-profit.',
            'invoice' => 'An invoice is stored in billing_invoice. Key fields: invoice_id, mem_id (client id), total_amount, paid_amount, balance, discount, invoice_status (1=pending/unpaid, 2=partial, 3=paid, 4=disputed), due_date, invoice_date, billing_cycle, payment_type (id), service_provider_id, is_ach (0/1). Line items are in billing_invoice_items (inv_id links to invoice_id, item_description, unit_price, quantity, total_price). To ADD an invoice, respond with NEWINVOICE: followed by JSON {mem_id, due_date, items:[{item_description, unit_price, quantity}]}.',
            'instant_invoice' => 'An instant/one-off invoice is stored in billing_invoice_instant (same columns as billing_invoice PLUS denormalized client info: name, business_name, phone, email, address, city, state, zip, ssn). To ADD one, respond with NEWINSTANT: followed by JSON {name, business_name, email, phone, address, city, state, zip, ssn, due_date, items:[{item_description, unit_price, quantity}]}.',
            'project' => 'A project is stored in projects (PK is order_id). Key fields: order_id, mem_id (client id), prod_id (service id), project_title, project_description, project_status (open/closed/completed), project_type, start_date, exp_date, assign_to (user id), user_id. To ADD a project, respond with NEWPROJECT: followed by JSON {mem_id, prod_id, project_title, project_description, project_status, project_type, start_date}.',
            'estimate' => 'An estimate/quotation is stored in quotations. Key fields: quotation_id, member_id, quotation_sr, name, phone, email, address, services, sub_total, discount, grand_total, quotation_date, status, created_by. To ADD one, respond with NEWESTIMATE: followed by JSON {member_id, name, phone, email, address, services, sub_total, discount, grand_total}.',
            'plan' => 'A payment plan is stored in payment_plans. Key fields: plan_id, client_id, plan_name, plan_type (Standard/Retainer/Installment/Custom), billing_cycle (Monthly/Quarterly/Annually), payment_method (ACH/Card/Check/Cash/Zelle), effective_date, start_date, end_date, status (Active/Inactive), total_amount. Line items are in payment_plan_items (plan_id, service_name, amount, frequency, details). To ADD one, respond with NEWPLAN: followed by JSON {client_id, plan_name, plan_type, billing_cycle, payment_method, start_date, end_date, total_amount, items:[{service_name, amount, frequency}]}.',
            'membership' => 'memberships is the service/product catalog: membership_id, membership_name, membership_price.',
            'note'    => 'notestab holds client notes: note_id, member_id, notes_detail. To ADD a note, respond with NOTE: <member_id> | <text>.',
            'comment' => 'comments holds client comments: comment_id, member_id, comment_detail.',
            'payment_history' => 'billing_payment_history tracks payments: his_id, mem_id, inv_id, paid, balance, detail, date, time, employee, payment_method.',
        );
    }
}

/* ------------ Local / offline AI fallback -------------------------------- */
/* Used when the Gemini API is unreachable (no internet). Answers basic,
   offline-safe questions with a rule-based responder so the assistant still
   "works" for common queries. Returns array('text'=>...) or array('error'=>...). */
if (!function_exists('ai_local_answer')) {
    function ai_local_answer($msg)
    {
        $m = strtolower(trim($msg));
        if ($m === '') {
            return array('text' => 'I\'m running in offline mode. Ask me something simple, or type "help" to see what I can do offline.');
        }
        // Greetings
        if (preg_match('/^(hi|hello|hey|good (morning|afternoon|evening)|salam|hi there|yo)\b/', $m)) {
            return array('text' => 'Hello! I\'m currently in offline mode, so I can only answer basic questions right now (live data + AI answers need an internet connection). Try "help" to see what I can do.');
        }
        // How are you / who are you
        if (strpos($m, 'how are you') !== false) {
            return array('text' => 'I\'m running in offline mode but I\'m ready to help with basic questions. Connect to the internet for live data lookups and AI answers.');
        }
        if (strpos($m, 'who are you') !== false || strpos($m, 'what are you') !== false || strpos($m, 'your name') !== false) {
            return array('text' => 'I\'m the Syed Pro CRM AI assistant. Right now I\'m in offline mode — connect to the internet and I can search your clients, invoices, projects, generate Excel/PDF reports, read images (OCR), and add clients or notes with your approval.');
        }
        // Thanks
        if (preg_match('/(thanks|thank you|thx|cheers)/', $m)) {
            return array('text' => 'You\'re welcome! Just note I\'m in offline mode right now — reconnect for live data help.');
        }
        // Help / capabilities
        if (preg_match('/\b(help|what can you do|capabilit|features|commands|your options)\b/', $m)) {
            return array('text' =>
                "I'm in OFFLINE mode, so I can only answer basic questions. Here's what I can do HERE:\n"
                . "- Say 'hi' / 'hello'\n"
                . "- Ask 'who are you' / 'what can you do'\n"
                . "- Say 'open invoices', 'open clients', 'open projects', 'open settings', 'open dashboard' to navigate\n"
                . "- Say 'thank you'\n\n"
                . "When you're ONLINE I can also: search your CRM data, generate Excel/PDF/docs, read images (OCR), and add clients or notes (with your confirmation).");
        }
        // Offline status
        if (preg_match('/\b(offline|internet|online|connection|network)\b/', $m)) {
            return array('text' => 'Right now I\'m working in offline mode because the AI service (Gemini) can\'t be reached — this usually means there\'s no internet connection. I can still answer basic questions and open pages, but live data lookups, OCR, exports, and AI answers need an internet connection.');
        }
        // open <module> navigation
        if (preg_match('/\bopen (invoices?|clients?|projects?|services?|products?|memberships?|dashboard|home|settings|reports|calendar|tasks?|messages?|payments?)\b/', $m, $nav)) {
            $page = strtolower($nav[1]);
            $map = array(
                'invoice' => 'invoice_billings.php',
                'invoices' => 'invoice_billings.php',
                'client' => 'view_clients.php',
                'clients' => 'view_clients.php',
                'project' => 'projects.php',
                'projects' => 'projects.php',
                'service' => 'memberships.php',
                'services' => 'memberships.php',
                'product' => 'memberships.php',
                'products' => 'memberships.php',
                'membership' => 'memberships.php',
                'memberships' => 'memberships.php',
                'dashboard' => 'index.php',
                'home' => 'index.php',
                'settings' => 'ai_settings.php',
                'calendar' => 'reminder_calender.php',
                'messages' => 'messanger.php',
                'payments' => 'billing_payments.php',
            );
            $url = isset($map[$page]) ? $map[$page] : 'index.php';
            return array('text' => "Opening $page (offline shortcut).\n→ /$url");
        }
        // Add/create intent needs internet + confirmation
        if (preg_match('/\b(add|create|new|insert)\b/', $m)) {
            return array('text' => "Adding or creating records needs an internet connection (and happens only with your confirmation). I'm currently offline, so I can't do that right now. Reconnect and I'll walk you through it.");
        }
        // Data queries that need internet
        if (preg_match('/\b(list|show|find|search|how many|count|get|total|balance|unpaid|invoice|client|project|sales|tax|profit)\b/', $m)) {
            return array('text' => "That looks like a data question, which needs an internet connection (I run my data searches through the Gemini AI online). I'm currently offline. Please reconnect to the internet, then I can look that up for you. Offline I can still do basics like greetings, navigation, and 'what can you do'.");
        }
        // Fallback
        return array('text' => "I'm in offline mode, so I can answer only basic questions (greetings, navigation, and 'what can you do'). I couldn't match your query to one of those. Reconnect to the internet for AI-powered answers and live data. Type 'help' to see your offline options.");
    }
}

/* ================= AI sessions (chat memory) ============================ */
/* Sessions let the assistant remember the whole conversation. A session is
   'active' until the user asks to end it ('end session'), which keeps each
   task self-contained. History rows are appended per message.              */

if (!function_exists('ai_session_create')) {
    function ai_session_create($uid, $title = 'New Session')
    {
        global $con;
        $uid = (int) $uid;
        $title = mysqli_real_escape_string($con, $title);
        /* End any existing active session for this user first. */
        mysqli_query($con, "UPDATE ai_sessions SET status='ended', ended_at=NOW() WHERE user_id=$uid AND status='active'");
        mysqli_query($con, "INSERT INTO ai_sessions (user_id, title, status) VALUES ($uid, '$title', 'active')");
        return (int) mysqli_insert_id($con);
    }
}
if (!function_exists('ai_session_active')) {
    function ai_session_active($uid)
    {
        global $con;
        $uid = (int) $uid;
        $q = mysqli_query($con, "SELECT * FROM ai_sessions WHERE user_id=$uid AND status='active' ORDER BY session_id DESC LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) {
            return mysqli_fetch_assoc($q);
        }
        return null;
    }
}
if (!function_exists('ai_session_get')) {
    function ai_session_get($uid, $session_id)
    {
        global $con;
        $uid = (int) $uid;
        $session_id = (int) $session_id;
        $q = mysqli_query($con, "SELECT * FROM ai_sessions WHERE session_id=$session_id AND user_id=$uid");
        return ($q && mysqli_num_rows($q) > 0) ? mysqli_fetch_assoc($q) : null;
    }
}
if (!function_exists('ai_session_list')) {
    function ai_session_list($uid, $limit = 50)
    {
        global $con;
        $uid = (int) $uid;
        $limit = (int) $limit;
        $q = mysqli_query($con, "SELECT session_id, title, status, created_at, ended_at
                                 FROM ai_sessions WHERE user_id=$uid
                                 ORDER BY created_at DESC LIMIT $limit");
        $out = array();
        if ($q) { while ($r = $q->fetch_assoc()) { $out[] = $r; } }
        return $out;
    }
}
if (!function_exists('ai_session_end')) {
    function ai_session_end($uid, $session_id = 0)
    {
        global $con;
        $uid = (int) $uid;
        if ($session_id <= 0) {
            mysqli_query($con, "UPDATE ai_sessions SET status='ended', ended_at=NOW() WHERE user_id=$uid AND status='active'");
            return true;
        }
        $session_id = (int) $session_id;
        mysqli_query($con, "UPDATE ai_sessions SET status='ended', ended_at=NOW() WHERE session_id=$session_id AND user_id=$uid");
        return true;
    }
}
if (!function_exists('ai_session_title')) {
    function ai_session_title($uid, $session_id, $title)
    {
        global $con;
        $uid = (int) $uid;
        $session_id = (int) $session_id;
        $title = mysqli_real_escape_string($con, $title);
        mysqli_query($con, "UPDATE ai_sessions SET title='$title' WHERE session_id=$session_id AND user_id=$uid");
    }
}
if (!function_exists('ai_chat_append')) {
    function ai_chat_append($uid, $session_id, $role, $content, $kind = 'text')
    {
        global $con;
        $uid = (int) $uid;
        $session_id = (int) $session_id;
        $role = in_array($role, array('user', 'assistant', 'system')) ? $role : 'user';
        $content = mysqli_real_escape_string($con, (string) $content);
        $kind = mysqli_real_escape_string($con, $kind);
        mysqli_query($con, "INSERT INTO ai_chat_history (session_id, user_id, role, content, kind)
                            VALUES ($session_id, $uid, '$role', '$content', '$kind')");
        return (int) mysqli_insert_id($con);
    }
}
if (!function_exists('ai_chat_history')) {
    function ai_chat_history($uid, $session_id, $limit = 100)
    {
        global $con;
        $uid = (int) $uid;
        $session_id = (int) $session_id;
        $limit = (int) $limit;
        $q = mysqli_query($con, "SELECT role, content, kind, created_at FROM ai_chat_history
                                 WHERE session_id=$session_id AND user_id=$uid
                                 ORDER BY chat_id ASC LIMIT $limit");
        $out = array();
        if ($q) { while ($r = $q->fetch_assoc()) { $out[] = $r; } }
        return $out;
    }
}
/* Build a conversation transcript (for the Gemini prompt) from stored history.
   Skips 'system' marker rows (used for stored file summaries).             */
if (!function_exists('ai_build_transcript')) {
    function ai_build_transcript($uid, $session_id)
    {
        $rows = ai_chat_history($uid, $session_id, 200);
        $transcript = '';
        foreach ($rows as $r) {
            if ($r['role'] === 'system') { continue; }
            $who = ($r['role'] === 'user') ? 'User' : 'Assistant';
            $transcript .= "$who: " . $r['content'] . "\n";
        }
        return trim($transcript);
    }
}

/* ================= File upload / text extraction ======================== */
/* Handles DOC, DOCX, PDF, TXT, CSV, and image (OCR) uploads. Returns
   array('text'=>..., 'file'=>name, 'kind'=>...) or array('error'=>...).    */

if (!function_exists('ai_extract_file_text')) {
    function ai_extract_file_text($tmp, $name)
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'txt':
            case 'csv':
            case 'log':
            case 'md':
                $raw = @file_get_contents($tmp);
                if ($raw === false) { return array('error' => 'Could not read the file.'); }
                return array('text' => $raw, 'kind' => 'text');
            case 'pdf':
                return ai_extract_pdf($tmp);
            case 'docx':
                return ai_extract_docx($tmp);
            case 'doc':
                /* Legacy binary .doc cannot be parsed without extra tools. */
                return array('text' => "[Unable to parse legacy .doc — please re-save as .docx, .txt, or .pdf.]", 'kind' => 'text');
            default:
                return array('error' => 'Unsupported file type: .' . $ext . ' (supported: txt, csv, pdf, docx, doc, and images).');
        }
    }
}
if (!function_exists('ai_extract_pdf')) {
    function ai_extract_pdf($tmp)
    {
        $raw = @file_get_contents($tmp);
        if ($raw === false) { return array('error' => 'Could not read the PDF.'); }
        /* Very small files are often Not-A-PDF; validate the header. */
        if (strpos($raw, '%PDF') !== 0) { return array('text' => '[The uploaded .pdf does not appear to be a valid PDF.]', 'kind' => 'text'); }
        /* Native text: pull the stream content between BT/ET (text objects). */
        if (preg_match_all('/BT(.*?)ET/s', $raw, $m)) {
            $txt = '';
            $stream = implode(' ', $m[1]);
            /* Extract parentheses strings (PDF text show). */
            if (preg_match_all('/\(([^)]*)\)\s*T[Jj]/', $stream, $s)) {
                $txt = implode("\n", $s[1]);
            }
            if (trim($txt) !== '') {
                /* Decode common PDF escapes. */
                $txt = str_replace(array('\\(', '\\)', '\\\\'), array('(', ')', '\\'), $txt);
                return array('text' => $txt, 'kind' => 'pdf');
            }
        }
        /* Fall back: let Gemini read the PDF directly. */
        return array('text' => '[PDF text extraction limited — will ask Gemini to read it.]', 'kind' => 'pdf');
    }
}
if (!function_exists('ai_extract_docx')) {
    function ai_extract_docx($tmp)
    {
        if (!class_exists('ZipArchive')) { return array('text' => '[DOCX parsing requires the Zip extension.]', 'kind' => 'text'); }
        $zip = new ZipArchive();
        if (@$zip->open($tmp) !== true) { return array('text' => '[Failed to open the .docx file.]', 'kind' => 'text'); }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) { return array('text' => '[No document body found in this .docx.]', 'kind' => 'text'); }
        /* Strip tags, keep paragraph breaks. */
        $xml = preg_replace('/<w:p[ >]/', "\n<w:p >", $xml);
        $txt = html_entity_decode(strip_tags($xml), ENT_QUOTES, 'UTF-8');
        $txt = preg_replace('/[ \t]+\n/', "\n", $txt);
        $txt = preg_replace("/\n{3,}/", "\n\n", $txt);
        return array('text' => trim($txt), 'kind' => 'docx');
    }
}

/* ================= OCR: per-module field extraction ====================== */
/* The SPS Assistant can read a document/image and turn it into a *previewable*
   insert (client, invoice, instant invoice, estimate, project). Each module
   has its own expected JSON keys that match the front-end preview builders.  */
if (!function_exists('ai_ocr_module_specs')) {
    function ai_ocr_module_specs()
    {
        return array(
            'client'   => array('label' => 'client / contact / business card',
                'keys' => 'name, lname, bus_name, phone, cell_ph, prm_email, address, city, state, zip, ss, ein, dob'),
            'invoice'  => array('label' => 'invoice / bill / sales receipt',
                'keys' => 'mem_id, client_name, due_date, billing_cycle, items'),
            'instant'  => array('label' => 'one-off / instant invoice / receipt',
                'keys' => 'name, business_name, email, phone, address, city, state, zip, ssn, due_date, items'),
            'estimate' => array('label' => 'estimate / quotation / quote',
                'keys' => 'name, phone, email, address, services, sub_total, discount, grand_total'),
            'project'  => array('label' => 'project / job specification sheet',
                'keys' => 'client_name, project_title, project_description, project_type'),
        );
    }
}
if (!function_exists('ai_ocr_build_prompt')) {
    /* Build the extraction prompt for a specific module, or an auto-detect
       prompt (target = 'auto') that classifies the document first.         */
    function ai_ocr_build_prompt($target = 'auto', $source = 'document')
    {
        $specs = ai_ocr_module_specs();
        if ($target === 'auto') {
            $list = '';
            foreach ($specs as $k => $s) { $list .= "- " . $s['label'] . " (type \"$k\")\n"; }
            return "You are reading a $source. First decide what kind of SPS CRM record it is among:\n$list"
                . "Output EXACTLY this format:\n"
                . "TYPE: <one of " . implode('|', array_keys($specs)) . ">\n"
                . "JSON: {…}\n"
                . "SUMMARY: one short line describing the document.\n"
                . "The JSON must use the exact keys for the chosen type (see below) and nothing else.\n\n"
                . "Keys per type:\n"
                . ai_ocr_keys_text($specs);
        }
        $s = $specs[$target] ?? $specs['client'];
        $jsonDesc = ($target === 'invoice' || $target === 'instant') ? "items is an array of {item_description, unit_price, quantity}. " : '';
        return "You are reading a $source that should be entered as a SPS CRM \"{$s['label']}\" record.\n"
            . "Extract the following fields and return them as a SINGLE-LINE JSON object with EXACTLY these keys: {$s['keys']}. "
            . "Use empty strings for missing values. $jsonDesc"
            . "Then on the next line give a one-line summary of what the document is.\n"
            . "IMPORTANT: return ONLY the JSON line followed by the summary line — no other text.";
    }
}
if (!function_exists('ai_ocr_keys_text')) {
    function ai_ocr_keys_text($specs)
    {
        $out = '';
        foreach ($specs as $k => $s) {
            $out .= "- $k: {" . $s['keys'] . "}\n";
        }
        return $out;
    }
}
if (!function_exists('ai_ocr_parse')) {
    /* Turn a model response into array(preview_type, fields, summary).      */
    function ai_ocr_parse($text, $target = 'auto')
    {
        $text = trim($text);
        $preview = 'newclient';
        if ($target !== 'auto') {
            $map = array('client' => 'newclient', 'invoice' => 'newinvoice', 'instant' => 'newinstant', 'estimate' => 'newestimate', 'project' => 'newproject');
            $preview = isset($map[$target]) ? $map[$target] : 'newclient';
        } else {
            if (preg_match('/TYPE\s*[:=]\s*(\w+)/i', $text, $m)) {
                $t = strtolower(trim($m[1]));
                $map = array('client' => 'newclient', 'invoice' => 'newinvoice', 'instant' => 'newinstant', 'estimate' => 'newestimate', 'project' => 'newproject');
                if (isset($map[$t])) $preview = $map[$t];
            }
        }
        $fields = array();
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $fields = json_decode($m[0], true);
            if (!is_array($fields)) $fields = array();
        }
        /* Normalize invoice/instant items to an array. */
        foreach (array('items') as $k) {
            if (isset($fields[$k]) && is_array($fields[$k])) {
                foreach ($fields[$k] as &$it) {
                    if (is_array($it)) {
                        $it['item_description'] = (string) ($it['item_description'] ?? ($it['desc'] ?? ''));
                        $it['unit_price'] = $it['unit_price'] ?? ($it['price'] ?? 0);
                        $it['quantity'] = $it['quantity'] ?? 1;
                    }
                }
                unset($it);
                $fields[$k] = array_values(array_filter($fields[$k], 'is_array'));
            }
        }
        $summary = '';
        if (preg_match('/SUMMARY\s*[:=]\s*(.+)/i', $text, $m)) {
            $summary = trim($m[1]);
        }
        if ($summary === '') {
            $lines = preg_split('/\r?\n/', $text);
            $summary = trim($lines[count($lines) - 1] ?? '');
        }
        return array('preview_type' => $preview, 'fields' => $fields, 'summary' => $summary);
    }
}
