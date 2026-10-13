<?php
/**
 * AI Copilot Endpoint - Connected with Groq (Free + Fast)
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'db.php';
require_once 'VectorSearch.php';

// ========== GROQ API KEY ==========
define('GROQ_API_KEY', 'YOUR_GROQ_API_KEY_HERE');  // ← yahan apni key paste karo
define('GROQ_MODEL', 'llama-3.3-70b-versatile');    // ya llama-3.1-8b-instant (zyada requests)

$input = json_decode(file_get_contents('php://input'), true);
$query = trim($input['query'] ?? '');

if (empty($query)) {
    echo json_encode(['success' => false, 'error' => 'Empty query']);
    exit;
}

$query = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');

try {
    // Local context search
    $searcher = new VectorSearch();
    $contextItems = $searcher->search($query, 5);

    $contextText = "";
    foreach ($contextItems as $item) {
        $contextText .= "- [{$item['type']}] {$item['name']}: {$item['content']}\n";
    }

    $systemPrompt = "You are an expert PHP developer and CRM assistant for 'Syed Professional Services' CRM.
You understand both English and Urdu.
Use the following local CRM context when relevant:

$contextText

Rules:
- Answer clearly and helpfully
- If code is needed, give clean PHP code
- If user asks in Urdu, you can reply in Urdu
- Be concise but complete";

    // Call Groq API
    $payload = [
        "model" => GROQ_MODEL,
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $query]
        ],
        "temperature" => 0.6,
        "max_tokens" => 1024
    ];

    $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . GROQ_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'Groq API error: ' . $response]);
        exit;
    }

    $data = json_decode($response, true);
    $reply = $data['choices'][0]['message']['content'] ?? 'No response from AI';

    // Simple code detection
    $code = null;
    if (preg_match('/```(?:php)?\s*([\s\S]*?)```/', $reply, $matches)) {
        $code = trim($matches[1]);
        // Remove code block from reply text (optional)
        $reply = preg_replace('/```(?:php)?\s*[\s\S]*?```/', '', $reply);
    }

    echo json_encode([
        'success' => true,
        'reply'   => trim($reply),
        'code'    => $code
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}