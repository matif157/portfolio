<?php
/**
 * GRAVITY CRM - AI Service
 * Phase 4: AI & Intelligent Automation
 * 
 * Yahan actual AI model integration (OpenAI, Anthropic, Google, Local/Ollama)
 * ke functions hain. Agar API key configured hai to real API call hota hai,
 * warna rule-based fallback use hota hai.
 */

require_once __DIR__ . '/functions.php';

/**
 * Get decrypted AI API key
 *
 * @return string
 */
function get_ai_api_key(): string {
    $encrypted = get_setting('ai_api_key_encrypted', '');
    return decrypt_setting($encrypted);
}

/**
 * Check if AI API is configured
 * 
 * @return bool
 */
function ai_api_configured(): bool {
    $provider = get_setting('ai_provider', 'openai');
    if ($provider === 'none') {
        return false;
    }
    if ($provider === 'local') {
        return true;
    }
    return !empty(get_ai_api_key());
}

/**
 * Call an AI API to generate a response
 * 
 * @param string $system_prompt
 * @param string $user_msg
 * @param array $options
 * @return string The AI response (empty if API not available)
 */
function call_ai_api(string $system_prompt, string $user_msg, array $options = []): string {
    if (!ai_api_configured()) {
        return '';
    }
    
    $model = $options['model'] ?? get_setting('ai_model', 'gpt-4o-mini');
    $api_key = get_ai_api_key();
    
    switch ($options['provider'] ?? get_setting('ai_provider', 'openai')) {
        case 'openai':
            return call_openai_api($api_key, $model, $system_prompt, $user_msg, $options);
        case 'anthropic':
            return call_anthropic_api($api_key, $model, $system_prompt, $user_msg, $options);
        case 'google':
            return call_google_api($api_key, $model, $system_prompt, $user_msg, $options);
        case 'local':
            return call_local_api($model, $system_prompt, $user_msg, $options);
        default:
            return '';
    }
}

/**
 * Call OpenAI API
 */
function call_openai_api(string $api_key, string $model, string $system_prompt, string $user_msg, array $options): string {
    $url = 'https://api.openai.com/v1/chat/completions';
    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_msg]
        ],
        'max_tokens' => $options['max_tokens'] ?? 1024,
        'temperature' => $options['temperature'] ?? 0.7,
    ];
    $response = http_request_json($url, json_encode($payload), [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
    ]);
    if (!$response || isset($response['error'])) {
        return '';
    }
    $GLOBALS['AI_LAST_USAGE'] = $response['usage'] ?? null;
    return $response['choices'][0]['message']['content'] ?? '';
}

/**
 * Call Anthropic (Claude) API
 */
function call_anthropic_api(string $api_key, string $model, string $system_prompt, string $user_msg, array $options): string {
    $url = 'https://api.anthropic.com/v1/messages';
    $payload = [
        'model' => $model,
        'system' => $system_prompt,
        'messages' => [['role' => 'user', 'content' => $user_msg]],
        'max_tokens' => $options['max_tokens'] ?? 1024,
    ];
    $response = http_request_json($url, json_encode($payload), [
        'x-api-key: ' . $api_key,
        'Content-Type: application/json',
        'anthropic-version: 2023-06-01',
    ]);
    if (!$response || isset($response['error'])) {
        return '';
    }
    $GLOBALS['AI_LAST_USAGE'] = $response['usage'] ?? null;
    $content = $response['content'] ?? [];
    return is_array($content) && isset($content[0]['text']) ? $content[0]['text'] : (is_string($content) ? $content : '');
}

/**
 * Call Google Gemini API
 */
function call_google_api(string $api_key, string $model, string $system_prompt, string $user_msg, array $options): string {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
    $payload = [
        'contents' => [['parts' => [['text' => $system_prompt . "\n\n" . $user_msg]]]],
        'generationConfig' => [
            'maxOutputTokens' => $options['max_tokens'] ?? 1024,
            'temperature' => $options['temperature'] ?? 0.7,
        ]
    ];
    $response = http_request_json($url, json_encode($payload), ['Content-Type: application/json']);
    if (!$response || isset($response['error'])) {
        return '';
    }
    $GLOBALS['AI_LAST_USAGE'] = $response['usageMetadata'] ?? null;
    return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

/**
 * Call local/ollama API
 */
function call_local_api(string $model, string $system_prompt, string $user_msg, array $options): string {
    $endpoint = get_setting('ai_local_endpoint', 'http://localhost:11434/api/generate');
    $payload = [
        'model' => $model ?: 'llama3',
        'prompt' => $system_prompt . "\n\nUser: " . $user_msg . "\n\nAssistant:",
        'stream' => false,
        'options' => [
            'temperature' => $options['temperature'] ?? 0.7,
            'num_predict' => $options['max_tokens'] ?? 1024,
        ]
    ];
    $response = http_request_json($endpoint, json_encode($payload), ['Content-Type: application/json']);
    if (!$response) {
        return '';
    }
    $GLOBALS['AI_LAST_USAGE'] = isset($response['eval_count']) ? ['total_tokens' => (int)$response['eval_count']] : null;
    return $response['response'] ?? '';
}

/**
 * Make an HTTP POST request and return parsed JSON
 * 
 * @param string $url
 * @param string $data
 * @param array $headers
 * @return array|null
 */
function http_request_json(string $url, string $data, array $headers = []): ?array {
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        return null;
    }
    $result = json_decode($response, true);
    return $result;
}

/**
 * Generate AI-assisted email composition
 * 
 * @param int $client_id
 * @param string $purpose
 * @param string $additional_context
 * @return string
 */
function generate_ai_email(int $client_id, string $purpose = 'general', string $additional_context = ''): string {
    $client = db_row('SELECT * FROM clients WHERE id = ?', [$client_id]);
    if (!$client) {
        return '';
    }
    
    $system_prompt = "You are a professional CRM assistant for " . get_setting('app_name', 'GRAVITY CRM') . 
        ". Write a concise, professional email. Use placeholders: company_name=" . get_setting('app_name', 'GRAVITY CRM') . 
        ", client_first_name=" . ($client['first_name'] ?? '') . 
        ", client_last_name=" . ($client['last_name'] ?? '') . ".";
    
    $user_msg = "Write an email with purpose '{$purpose}' to client {$client['first_name']} {$client['last_name']} (email: {$client['email']}).";
    if ($additional_context) {
        $user_msg .= " Additional context: {$additional_context}";
    }
    
    $result = call_ai_api($system_prompt, $user_msg, ['max_tokens' => 500]);
    
    if (empty($result)) {
        // Fallback to template-based generation
        $templates = db_query('SELECT body FROM message_templates WHERE template_type = "email" ORDER BY id LIMIT 1');
        if (!empty($templates)) {
            $body = $templates[0]['body'];
            $body = str_replace(['{client_first_name}', '{client_last_name}', '{app_name}', '{company_name}'],
                [$client['first_name'], $client['last_name'], get_setting('app_name', 'GRAVITY CRM'), get_setting('app_name', 'GRAVITY CRM')], $body);
            return $body;
        }
        return "Dear {$client['first_name']},\n\nThank you for your interest in our services.\n\nBest regards,\n" . get_setting('app_name', 'GRAVITY CRM');
    }
    
    return $result;
}

/**
 * Generate AI-powered insights/summary from CRM data
 * 
 * @return string
 */
function generate_ai_insights(): string {
    $system_prompt = "You are a CRM analytics assistant. Generate a concise 3-point executive summary highlighting key metrics, trends, and items requiring attention. Format as plain text with numbered points.";
    
    $parts = [];
    $parts[] = "Clients: " . db_value('SELECT COUNT(*) FROM clients') . " total, " . db_value('SELECT COUNT(*) FROM clients WHERE status="active"') . " active";
    $parts[] = "Leads: " . db_value('SELECT COUNT(*) FROM leads') . " total, " . db_value('SELECT COUNT(*) FROM leads WHERE status="qualified"') . " qualified";
    $parts[] = "Projects: " . db_value('SELECT COUNT(*) FROM projects WHERE status="in_progress"') . " active";
    $parts[] = "Outstanding: $" . number_format((float)db_value('SELECT SUM(amount_due) FROM invoices WHERE status IN ("sent","partial","overdue")'), 2);
    $parts[] = "Workflows: " . db_value('SELECT COUNT(*) FROM workflow_instances WHERE status="completed"') . " completed, " . db_value('SELECT COUNT(*) FROM workflow_instances WHERE status IN ("not_started","in_progress","waiting")') . " running";
    
    $user_msg = "Generate a 3-point CRM summary from this data:\n" . implode("\n", $parts);
    $result = call_ai_api($system_prompt, $user_msg, ['max_tokens' => 400]);
    
    if (empty($result)) {
        return "AI insights are unavailable. Configure your AI API key in Settings > API / Integrations.";
    }
    return $result;
}

/**
 * Get CRM context for AI queries
 * 
 * @param array $user
 * @param string $query
 * @return string
 */
function get_crm_context_for_ai(array $user, string $query): string {
    $context = [];
    $lower = strtolower($query);
    
    if (strpos($lower, 'client') !== false) {
        $context[] = "Clients: " . db_value('SELECT COUNT(*) FROM clients') . " total, " . db_value('SELECT COUNT(*) FROM clients WHERE status="active"') . " active";
        $recent = db_query('SELECT first_name, last_name, status FROM clients ORDER BY created_at DESC LIMIT 5');
        if (!empty($recent)) {
            $context[] = "Recent: " . implode(', ', array_map(function($c) { return $c['first_name'] . ' ' . $c['last_name'] . ' (' . $c['status'] . ')'; }, $recent));
        }
    }
    
    if (strpos($lower, 'lead') !== false) {
        $context[] = "Leads: " . db_value('SELECT COUNT(*) FROM leads') . " total";
        $status_counts = db_query("SELECT status, COUNT(*) as cnt FROM leads GROUP BY status");
        foreach ($status_counts as $sc) $context[] = "{$sc['cnt']} leads: {$sc['status']}";
    }
    
    if (strpos($lower, 'invoice') !== false) {
        $context[] = "Invoices: " . db_value('SELECT COUNT(*) FROM invoices') . " total, $" . number_format((float)db_value('SELECT SUM(amount_due) FROM invoices WHERE status IN ("sent","partial","overdue")'), 2) . " outstanding";
    }
    
    if (strpos($lower, 'task') !== false || strpos($lower, 'progress') !== false) {
        $pending = db_query('SELECT progress_label FROM user_progress WHERE user_id = ? AND status != "completed" ORDER BY updated_at DESC LIMIT 10', [$user['id']]);
        $context[] = "Your pending tasks: " . count($pending);
    }
    
    return implode("\n", $context);
}

/**
 * Generate AI response (Rule-based engine with live CRM context)
 *
 * @param string $message
 * @param array $user
 * @return string
 */
function generate_ai_response(string $message, array $user): string {
    $msg = strtolower($message);
    
    // Help command
    if (strpos($msg, 'help') !== false || strpos($msg, 'what can you do') !== false) {
        return "I can help you with:\n\n" .
               "• Search clients, leads, contacts, and businesses\n" .
               "• Explain service workflows\n" .
               "• Create reminders and tasks\n" .
               "• Answer questions about your CRM data\n\n" .
               "Try asking: \"Show me my clients\" or \"How many leads do we have?\"";
    }
    
    // Client count
    if (strpos($msg, 'how many client') !== false || strpos($msg, 'client count') !== false) {
        $count = (int)db_value('SELECT COUNT(*) FROM clients');
        return "You currently have {$count} client(s) in the CRM.";
    }
    
    // Lead count
    if (strpos($msg, 'how many lead') !== false || strpos($msg, 'lead count') !== false) {
        $count = (int)db_value('SELECT COUNT(*) FROM leads');
        return "You currently have {$count} lead(s) in the CRM.";
    }
    
    // Recent clients
    if (strpos($msg, 'recent client') !== false || strpos($msg, 'latest client') !== false) {
        $clients = db_query('SELECT first_name, last_name, created_at FROM clients ORDER BY created_at DESC LIMIT 5');
        if (empty($clients)) {
            return "No clients found in the CRM yet.";
        }
        $response = "Here are your 5 most recent clients:\n\n";
        foreach ($clients as $i => $client) {
            $response .= ($i + 1) . ". " . $client['first_name'] . " " . $client['last_name'] . " (added " . date('M j', strtotime($client['created_at'])) . ")\n";
        }
        return $response;
    }
    
    // Search client by name
    if (strpos($msg, 'find client') !== false || strpos($msg, 'search client') !== false) {
        preg_match('/client\s+(.+)/', $msg, $matches);
        if (isset($matches[1])) {
            $search = '%' . trim($matches[1]) . '%';
            $clients = db_query('SELECT id, first_name, last_name, email, phone FROM clients WHERE first_name LIKE ? OR last_name LIKE ? LIMIT 5', [$search, $search]);
            if (empty($clients)) {
                return "No clients found matching that name.";
            }
            $response = "I found these clients:\n\n";
            foreach ($clients as $client) {
                $response .= "• " . $client['first_name'] . " " . $client['last_name'] . " - " . ($client['email'] ?: 'no email') . "\n";
            }
            return $response;
        }
    }
    
    // My tasks
    if (strpos($msg, 'my task') !== false || strpos($msg, 'my progress') !== false) {
        $tasks = db_query('SELECT progress_label, status, progress_value FROM user_progress WHERE user_id = ? AND status != "completed" LIMIT 5', [$user['id']]);
        if (empty($tasks)) {
            return "You have no pending tasks. Great job!";
        }
        $response = "Here are your pending tasks:\n\n";
        foreach ($tasks as $task) {
            $response .= "• " . ($task['progress_label'] ?: $task['status']) . " (" . $task['progress_value'] . "%)\n";
        }
        return $response;
    }
    
    // Who am I
    if (strpos($msg, 'who am i') !== false) {
        return "You are " . ($user['first_name'] ?? 'User') . " " . ($user['last_name'] ?? '') . " with the role of " . ($user['role_name'] ?? 'Staff') . ".";
    }
    
    // Default response
    return "I understand you're asking about: \"" . $message . "\".\n\n" .
           "I can help with CRM queries such as:\n" .
           "• \"How many clients do we have?\"\n" .
           "• \"Show me recent clients\"\n" .
           "• \"Find client [name]\"\n" .
           "• \"What are my tasks?\"\n\n" .
           "To enable generative LLM responses, configure an AI Provider (OpenAI, Anthropic, Gemini, or Ollama) in Settings > API.";
}

/**
 * Test AI API connection and credentials
 *
 * @param string $provider
 * @param string $model
 * @param string $api_key
 * @param string $endpoint
 * @return array [success, message, details]
 */
function test_ai_connection(string $provider, string $model, string $api_key, string $endpoint = ''): array {
    if ($provider === 'none') {
        return [
            'success' => true,
            'message' => 'Rule-Based AI Assistant is active (no external API required).',
            'details' => 'Built-in natural language queries (clients, leads, tasks, workflows) are operational.'
        ];
    }
    
    if ($provider === 'local') {
        $url = $endpoint ?: 'http://localhost:11434/api/tags';
        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'cURL PHP extension is not enabled.', 'details' => ''];
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($err) {
            return ['success' => false, 'message' => 'Failed to connect to local AI server: ' . $err, 'details' => 'Ensure Ollama or Local AI is running at ' . $url];
        }
        return ['success' => true, 'message' => 'Local AI server is reachable (HTTP ' . $code . ').', 'details' => substr($resp, 0, 200)];
    }
    
    if (empty($api_key)) {
        return ['success' => false, 'message' => 'API Key is empty.', 'details' => 'Please enter a valid API key for ' . strtoupper($provider)];
    }
    
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'cURL PHP extension is not enabled.', 'details' => ''];
    }
    
    if ($provider === 'openai') {
        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) {
            return ['success' => false, 'message' => 'OpenAI network error: ' . $err, 'details' => ''];
        }
        $json = json_decode($resp, true);
        if ($code === 200 && isset($json['data'])) {
            return ['success' => true, 'message' => 'OpenAI API key is valid and connected successfully.', 'details' => 'Found ' . count($json['data']) . ' accessible OpenAI models.'];
        }
        $msg = $json['error']['message'] ?? ('HTTP status ' . $code);
        return ['success' => false, 'message' => 'OpenAI validation failed: ' . $msg, 'details' => $resp];
    }
    
    if ($provider === 'anthropic') {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $model ?: 'claude-3-haiku-20240307',
            'max_tokens' => 10,
            'messages' => [['role' => 'user', 'content' => 'ping']]
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) {
            return ['success' => false, 'message' => 'Anthropic network error: ' . $err, 'details' => ''];
        }
        $json = json_decode($resp, true);
        if ($code === 200) {
            return ['success' => true, 'message' => 'Anthropic Claude API connected successfully.', 'details' => ''];
        }
        $msg = $json['error']['message'] ?? ('HTTP status ' . $code);
        return ['success' => false, 'message' => 'Anthropic validation failed: ' . $msg, 'details' => $resp];
    }
    
    if ($provider === 'google') {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($api_key);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) {
            return ['success' => false, 'message' => 'Google Gemini network error: ' . $err, 'details' => ''];
        }
        $json = json_decode($resp, true);
        if ($code === 200 && isset($json['models'])) {
            return ['success' => true, 'message' => 'Google Gemini API key is valid and connected.', 'details' => 'Available Gemini models: ' . count($json['models'])];
        }
        $msg = $json['error']['message'] ?? ('HTTP status ' . $code);
        return ['success' => false, 'message' => 'Google Gemini validation failed: ' . $msg, 'details' => $resp];
    }
    
    return ['success' => false, 'message' => 'Unknown AI provider: ' . $provider, 'details' => ''];
}

/**
 * Generate AI 360-Degree Intelligence Briefing for a Client
 * Synthesizes client status, business entities, bank statement completion percentage,
 * active service workflow requirements, outstanding invoices, and recommends the exact next action.
 *
 * @param int $client_id
 * @return array [summary => string, recommendations => array, missing_items => array, risk_level => string]
 */
function ai_generate_client_briefing(int $client_id): array {
    $client = db_row("SELECT * FROM clients WHERE id = ?", [$client_id]);
    if (!$client) {
        return ['summary' => 'Client not found.', 'recommendations' => [], 'missing_items' => [], 'risk_level' => 'low'];
    }

    $businesses = db_query("SELECT * FROM businesses WHERE client_id = ?", [$client_id]);
    $services = db_query("
        SELECT cs.*, s.name as service_name, s.fixed_price, b.name as business_name
        FROM client_services cs
        JOIN services s ON cs.service_id = s.id
        LEFT JOIN businesses b ON cs.business_id = b.id
        WHERE cs.client_id = ?
    ", [$client_id]);

    $projects = db_query("SELECT * FROM projects WHERE client_id = ?", [$client_id]);
    $invoices = db_query("SELECT * FROM invoices WHERE client_id = ?", [$client_id]);

    // Check missing requirements
    $missing_reqs = db_query("
        SELECT csr.*, sr.title as req_title, s.name as service_name
        FROM client_service_requirements csr
        JOIN service_requirements sr ON csr.requirement_id = sr.id
        JOIN services s ON csr.service_id = s.id
        WHERE csr.client_id = ? AND csr.status = 'pending' AND sr.is_required = 1
    ", [$client_id]);

    // Check missing bank statements
    $missing_statements = db_query("
        SELECT bs.*, ba.bank_name, ba.account_number_last4, b.name as business_name
        FROM bank_statements bs
        JOIN bank_accounts ba ON bs.bank_account_id = ba.id
        JOIN businesses b ON ba.business_id = b.id
        WHERE ba.client_id = ? AND bs.status = 'missing' AND bs.period_year = 2026
        ORDER BY bs.period_month ASC
    ", [$client_id]);

    // Calculate Financials
    $total_billed = array_sum(array_column($invoices, 'total_amount'));
    $total_due = array_sum(array_column($invoices, 'amount_due'));

    $client_name = trim($client['first_name'] . ' ' . $client['last_name']);
    $business_names = implode(', ', array_column($businesses, 'name')) ?: 'None';

    $context_text = "Client: {$client_name}\n" .
                    "Businesses: {$business_names}\n" .
                    "Active Services: " . count($services) . "\n" .
                    "Open Projects: " . count(array_filter($projects, fn($p) => $p['status'] !== 'completed')) . "\n" .
                    "Missing Service Requirements: " . count($missing_reqs) . " (" . implode(', ', array_column($missing_reqs, 'req_title')) . ")\n" .
                    "Missing 2026 Bank Statements: " . count($missing_statements) . " month(s)\n" .
                    "Total Invoiced: \${$total_billed} | Outstanding Due: \${$total_due}\n";

    $system_prompt = "You are the Senior AI Operations Architect for Syed Professional Services (SPS). Analyze the client operational portfolio and provide: 1) A clear 2-3 sentence executive situation summary, 2) The single highest-priority recommended next action, 3) An estimated operational risk level (Low, Medium, High). Keep your response concise, professional, and actionable.";

    $ai_response = '';
    if (ai_api_configured()) {
        $ai_response = call_ai_api($system_prompt, "Analyze this client:\n" . $context_text);
    }

    if (empty($ai_response)) {
        // High quality rule-based synthesis
        $risk = (count($missing_reqs) > 2 || count($missing_statements) > 3 || $total_due > 1000) ? 'High' : (count($missing_reqs) > 0 || count($missing_statements) > 0 ? 'Medium' : 'Low');
        $summary = "{$client_name} currently has " . count($businesses) . " registered business entity and " . count($services) . " active service engagement(s). ";
        if (count($missing_reqs) > 0 || count($missing_statements) > 0) {
            $summary .= "Workflow is currently blocked by " . count($missing_reqs) . " missing prerequisite(s) and " . count($missing_statements) . " missing bank statement(s).";
        } else {
            $summary .= "All compliance requirements and documentation are up to date.";
        }

        $recommendation = count($missing_reqs) > 0 
            ? "Dispatch automated missing prerequisite notice for: " . implode(', ', array_slice(array_column($missing_reqs, 'req_title'), 0, 2))
            : (count($missing_statements) > 0 
                ? "Send automated bank statement reminder for missing months (" . count($missing_statements) . " months)" 
                : ($total_due > 0 ? "Follow up on outstanding balance of $" . number_format($total_due, 2) : "Review current deliverables and schedule upcoming milestone."));

        $ai_response = $summary . "\n\n**Recommended Next Action:** " . $recommendation . "\n\n**Risk Level:** " . $risk;
    }

    return [
        'summary' => $ai_response,
        'missing_requirements_count' => count($missing_reqs),
        'missing_statements_count' => count($missing_statements),
        'total_due' => $total_due,
        'context' => $context_text
    ];
}

/**
 * Generate AI-crafted client communication draft tailored to specific pending items
 *
 * @param int $client_id
 * @param string $purpose (missing_docs, statement_reminder, invoice_followup, welcome)
 * @param array $extra_context
 * @return array [subject => string, email_body => string, sms_body => string]
 */
function ai_draft_client_communication(int $client_id, string $purpose, array $extra_context = []): array {
    $client = db_row("SELECT * FROM clients WHERE id = ?", [$client_id]);
    $first_name = $client['first_name'] ?? 'Valued Client';
    $company = $client['company_name'] ?: 'your company';

    $subject = '';
    $email_body = '';
    $sms_body = '';

    switch ($purpose) {
        case 'missing_docs':
            $items = $extra_context['items'] ?? 'required service documentation';
            $service = $extra_context['service_name'] ?? 'accounting & tax services';
            $subject = "Action Required: Missing Documentation for {$service}";
            $email_body = "Dear {$first_name},\n\nWe are currently preparing your {$service} at Syed Professional Services.\n\nTo ensure timely processing and avoid compliance delays, we require the following item(s):\n• {$items}\n\nPlease upload these documents directly through your client portal or reply with the attached files.\n\nThank you,\nSyed Professional Services Team";
            $sms_body = "SPS Notice: Hi {$first_name}, please upload {$items} for your {$service} via the client portal.";
            break;

        case 'statement_reminder':
            $year = $extra_context['year'] ?? date('Y');
            $months = $extra_context['months'] ?? 'pending months';
            $bank = $extra_context['bank_name'] ?? 'your bank account';
            $subject = "Action Required: Missing {$year} Bank Statements for {$company}";
            $email_body = "Dear {$first_name},\n\nWe are reconciling your monthly bookkeeping for {$year}.\n\nWe are currently missing statements for:\n• Account: {$bank}\n• Period: {$months} {$year}\n\nPlease upload the PDF statements to your portal so we can complete your monthly reconciliation.\n\nWarm regards,\nSyed Professional Services Team";
            $sms_body = "SPS Reminder: Hi {$first_name}, {$year} bank statements for {$months} are needed for your monthly bookkeeping.";
            break;

        case 'invoice_followup':
            $inv_num = $extra_context['invoice_number'] ?? 'INV-001';
            $amount = $extra_context['amount_due'] ?? '0.00';
            $due_date = $extra_context['due_date'] ?? 'due date';
            $subject = "Reminder: Invoice #{$inv_num} is Pending Payment";
            $email_body = "Dear {$first_name},\n\nThis is a friendly reminder that Invoice #{$inv_num} in the amount of \${$amount} was due on {$due_date}.\n\nYou can review and pay your invoice securely online through your client portal.\n\nThank you for your business,\nSyed Professional Services";
            $sms_body = "SPS Notice: Hi {$first_name}, Invoice #{$inv_num} for \${$amount} is pending. Please review your portal to pay online.";
            break;

        default:
            $subject = "Update regarding your services with Syed Professional Services";
            $email_body = "Dear {$first_name},\n\nWe are actively working on your deliverables. Please let us know if you have any questions.\n\nBest regards,\nSyed Professional Services";
            $sms_body = "SPS Alert: Hi {$first_name}, our team is processing your accounts. Let us know if you need any assistance.";
            break;
    }

    return [
        'subject' => $subject,
        'email_body' => $email_body,
        'sms_body' => $sms_body
    ];
}

