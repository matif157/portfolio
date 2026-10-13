<?php
declare(strict_types=1);

namespace SPS\AI;

use PDO;
use Exception;
use SPS\Automation\OmnichannelDispatcher;
use SPS\Integration\TwilioAutomationClient;

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * AIPromptController
 * 
 * Natural Language Processing Controller for Voice Assistant & Automated Action Triggers.
 */
class AIPromptController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function processPrompt(string $userPrompt, int $userId): array
    {
        $prompt = strtolower(trim($userPrompt));
        $responseText = '';
        $speechText = '';
        $actionExecuted = 'none';

        // Intent 1: Call Client & Ask for Tax / Document Requirements
        if (strpos($prompt, 'call') !== false && (strpos($prompt, 'tax') !== false || strpos($prompt, 'document') !== false || strpos($prompt, 'statement') !== false)) {
            // Find target client (e.g., John Doe or first active client)
            $stmtC = $this->pdo->prepare("SELECT id, first_name, last_name, company_name, phone FROM clients WHERE status = 'active' ORDER BY id ASC LIMIT 1");
            $stmtC->execute();
            $client = $stmtC->fetch();

            if ($client) {
                $twilio = new TwilioAutomationClient(
                    get_setting('twilio_account_sid', ''),
                    get_setting('twilio_auth_token', ''),
                    get_setting('twilio_phone_number', '')
                );
                $dispatcher = new OmnichannelDispatcher($this->pdo, $twilio);

                $script = "Hello {$client['first_name']}. This is an automated follow-up call from Syed Professional Services requesting your outstanding tax documents and monthly bank statements.";
                $callRes = $twilio->initiateVoiceCall($client['phone'] ?? '', $script);

                $dispatcher->recordTimelineEntry(
                    (int)$client['id'],
                    null,
                    'voice',
                    'outbound',
                    'Voice Assistant Triggered Call',
                    $script,
                    'completed',
                    $callRes['sid'] ?? null,
                    65
                );

                $actionExecuted = 'twilio_call';
                $responseText = "Initiated Twilio voice call to " . ($client['company_name'] ?: "{$client['first_name']} {$client['last_name']}") . " ({$client['phone']}). Call SID: " . ($callRes['sid'] ?? 'SIMULATED');
                $speechText = "I have initiated an automated Twilio voice call to " . ($client['company_name'] ?: $client['first_name']) . " to request their tax documents.";
            } else {
                $responseText = "No active clients found to call.";
                $speechText = "No active clients found in database.";
            }
        
        // Intent 2: Query Missing Statements
        } elseif (strpos($prompt, 'missing') !== false || strpos($prompt, 'statement') !== false) {
            $missing = $this->pdo->query("SELECT COUNT(*) FROM bank_statements WHERE status = 'missing'")->fetchColumn();
            $responseText = "Found {$missing} missing monthly bank statements across active client business accounts.";
            $speechText = "There are currently {$missing} missing monthly bank statements requiring client follow up.";
            $actionExecuted = 'query_missing_statements';

        // Intent 3: Query Outstanding Receivables & Revenue
        } elseif (strpos($prompt, 'revenue') !== false || strpos($prompt, 'unpaid') !== false || strpos($prompt, 'receivables') !== false) {
            $totalDue = $this->pdo->query("SELECT COALESCE(SUM(balance_due), 0) FROM invoices WHERE status IN ('unpaid', 'overdue')")->fetchColumn();
            $responseText = "Total outstanding receivables due is " . format_currency($totalDue) . ".";
            $speechText = "Total outstanding receivables due is " . number_format((float)$totalDue, 2) . " dollars.";
            $actionExecuted = 'query_revenue';

        // Intent 4: General Status Summary
        } else {
            $clientsCount = $this->pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'active'")->fetchColumn();
            $projectsCount = $this->pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'open'")->fetchColumn();
            $responseText = "Syed Professional Services CRM Status: Tracking {$clientsCount} active clients and {$projectsCount} open service projects.";
            $speechText = "Syed Professional Services system is operational with {$clientsCount} active clients and {$projectsCount} open projects.";
            $actionExecuted = 'crm_status';
        }

        // Record AI Interaction
        $stmtAi = $this->pdo->prepare("
            INSERT INTO ai_interactions (user_id, action, prompt, response, confidence, reason)
            VALUES (?, ?, ?, ?, 0.98, ?)
        ");
        $stmtAi->execute([$userId, $actionExecuted, $userPrompt, $responseText, $speechText]);

        return [
            'success' => true,
            'action_executed' => $actionExecuted,
            'user_prompt' => $userPrompt,
            'response_text' => $responseText,
            'speech_text' => $speechText
        ];
    }
}

// Handler execution for incoming POST/JSON requests
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? $_POST;
$prompt = trim($data['prompt'] ?? '');

if (!empty($prompt)) {
    $controller = new AIPromptController($pdo);
    $response = $controller->processPrompt($prompt, $_SESSION['user_id'] ?? 1);
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'error' => 'No prompt provided.']);
}
