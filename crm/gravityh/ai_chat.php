<?php
/**
 * GRAVITY CRM - AI Chat Endpoint
 * Phase 4: AI & Intelligent Automation
 * 
 * Yahan AI chat requests handle hote hain.
 * Agar API key configured hai to real LLM use karta hai,
 * warna rule-based fallback use karta hai.
 * AI sirf authorized CRM data par hi kaam karta hai.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ai_service.php';
require_login();
require_permission('ai', 'use');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['response' => 'Invalid request method.']);
    exit;
}

require_csrf();

$message = trim(post('message', ''));
if ($message === '') {
    echo json_encode(['response' => 'Please enter a message.']);
    exit;
}

$user = current_user();

// Phase 4: Try real AI API first, fall back to rule-based
$response = '';
$ai_status = 'completed';

if (ai_api_configured()) {
    // Real AI API integration
    $crm_context = get_crm_context_for_ai($user, $message);
    $system_prompt = "You are GRAVITY CRM's AI Assistant for a professional services firm (tax, legal, immigration). 
Answer questions based ONLY on CRM data provided. Be concise and professional.
User role: " . $user['role_name'] . ".
Available CRM context:\n" . $crm_context . "

If you cannot answer from CRM data, respond with a helpful message and suggest what CRM data the user could query.";
    
    $response = call_ai_api($system_prompt, $message, [
        'max_tokens' => 1500,
        'temperature' => 0.7,
    ]);
    
    if (empty($response)) {
        $response = '';
        $ai_status = 'failed';
    }
}

// Fallback: rule-based response if no AI or API failed
if (empty($response)) {
    $response = generate_ai_response($message, $user);
    $ai_status = 'completed';
}

// Log AI interaction
db_execute(
    'INSERT INTO ai_interactions (user_id, interaction_type, user_input, ai_response, status) VALUES (?, "chat", ?, ?, ?)',
    [$user['id'], $message, $response, $ai_status]
);

echo json_encode(['response' => $response, 'ai' => $ai_status !== 'failed' && ai_api_configured() ? 'api' : 'rule']);
exit;

/**
 * Generate AI response (Phase 1 rule-based)
 * Phase 4 mein actual AI model se replace hoga
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
        // Extract name after "client"
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
        return "You are " . $user['first_name'] . " " . $user['last_name'] . " with the role of " . $user['role_name'] . ".";
    }
    
    // Default response
    return "I understand you're asking about: \"" . $message . "\".\n\n" .
           "In this phase, I can help with basic CRM queries like:\n" .
           "• \"How many clients do we have?\"\n" .
           "• \"Show me recent clients\"\n" .
           "• \"Find client John\"\n" .
           "• \"What are my tasks?\"\n\n" .
           "Advanced AI capabilities will be available in Phase 4.";
}