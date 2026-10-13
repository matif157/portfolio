<?php
/*  AI ASSISTANT  ─  shared helper library
    -------------------------------------------------------------------------
    Central place for:
      • ai_get() / ai_set()  — key/value settings stored in settings_marker
      • ai_can()             — capability check (crm_can OR admin)
      • gemini_call()        — Google Gemini REST wrapper (text + vision)
      • gemini_test()        — connectivity test for the Settings page
      • ai_db_schema()       — table/column/relationship map (read-only hmm)
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
            return array('error' => 'Gemini API key is not configured.');
        }
        $contents = array();
        $partsOut = array();
        foreach ($parts as $p) {
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
            return array('error' => 'Could not reach the Gemini API (network/SSL).');
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
/* ------------ DB relationship / schema map ------------------------------ */
if (!function_exists('ai_db_schema')) {
    function ai_db_schema()
    {
        return array(
            'modules' => array(
                'client'     => array('table' => 'members',                 'pk' => 'member_id', 'label' => 'Client (member)'),
                'invoice'    => array('table' => 'billing_invoice',         'pk' => 'invoice_id', 'label' => 'Invoice'),
                'invoice_item'=> array('table' => 'billing_invoice_items',  'pk' => 'inv_item_id', 'label' => 'Invoice line item'),
                'project'    => array('table' => 'projects',                'pk' => 'order_id', 'label' => 'Project'),
                'order'      => array('table' => 'orders',                  'pk' => 'order_id', 'label' => 'Service / order'),
                'membership' => array('table' => 'memberships',             'pk' => 'membership_id', 'label' => 'Service / product'),
                'note'       => array('table' => 'notestab',                'pk' => 'note_id', 'label' => 'Client note'),
                'comment'    => array('table' => 'comments',                'pk' => 'comment_id', 'label' => 'Client comment'),
                'payment_history' => array('table' => 'billing_payment_history', 'pk' => 'his_id', 'label' => 'Payment history'),
                'user'       => array('table' => 'users',                   'pk' => 'user_id', 'label' => 'Staff / user'),
            ),
            'relationships' => array(
                'members.member_id = billing_invoice.mem_id',
                'members.member_id = billing_invoice_items.mem_id',
                'members.member_id = projects.mem_id',
                'members.member_id = orders.mem_id',
                'members.member_id = notestab.member_id',
                'members.member_id = comments.member_id',
                'members.member_id = billing_payment_history.mem_id',
                'billing_invoice.invoice_id = billing_invoice_items.inv_id',
                'billing_invoice.invoice_id = billing_payment_history.inv_id',
                'orders.prod_id = memberships.membership_id',
                'projects.prod_id = memberships.membership_id',
                'billing_invoice.invoice_status = billing_invoice_status.status_id',
                'members.type = member_type.type_id',
                'members.member_id = documents.client_id',
                'members.member_id = spouse.member_id',
            ),
        );
    }
}
if (!function_exists('ai_module_descriptions')) {
    function ai_module_descriptions()
    {
        return array(
            'client'  => 'A client is stored in the members table. Key fields: member_id (id), name (first name), mname, lname, bus_name (business name), phone (work), cell_ph (cell), prm_email (primary email), sec_email, address/city/state/zip (business address), hstreet/hcity/hstate/hzip (home), dob, ss (SSN/ITIN), ein, type (client type id), status (1=active).',
            'invoice' => 'An invoice is stored in billing_invoice. Key fields: invoice_id, mem_id (client id), total_amount, paid_amount, balance, discount, invoice_status (1=pending/unpaid), due_date, invoice_date, billing_cycle. Line items are in billing_invoice_items (inv_id links to invoice_id, item_description, unit_price, quantity, total_price).',
            'membership' => 'memberships is the service/product catalog: membership_id, membership_name, membership_price.',
            'note'    => 'notestab holds client notes: note_id, member_id, notes_detail.',
            'comment' => 'comments holds client comments: comment_id, member_id, comment_detail.',
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
