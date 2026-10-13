<?php
declare(strict_types=1);

use SPS\Automation\OmnichannelDispatcher;
use SPS\Automation\ServiceAutomationEngine;
use SPS\Integration\TwilioAutomationClient;

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

try {
    $input = file_get_contents('php://input');
    $payload = json_decode($input, true) ?? $_POST;

    $contactName = trim($payload['contact_name'] ?? '');
    $companyName = trim($payload['company_name'] ?? '');
    $email = trim($payload['email'] ?? '');
    $phone = trim($payload['phone'] ?? '');
    $serviceCode = trim($payload['service_code'] ?? 'SPS-TAX-CORP-1120');
    $estimatedValue = floatval($payload['estimated_value'] ?? 1200.00);

    if (empty($contactName) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'contact_name and email are required.']);
        exit;
    }

    // 1. NLP Lead Scoring Algorithm (0 - 100)
    $score = 50;
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) $score += 15;
    if (!empty($phone) && strlen(preg_replace('/[^0-9]/', '', $phone)) >= 10) $score += 15;
    if (!empty($companyName)) $score += 10;
    if ($estimatedValue >= 1000) $score += 10;

    $pdo->beginTransaction();

    // Fetch Target Service Info
    $stmtS = $pdo->prepare("SELECT id, title FROM services WHERE service_code = ? LIMIT 1");
    $stmtS->execute([$serviceCode]);
    $service = $stmtS->fetch();
    $serviceId = $service ? (int)$service['id'] : 1;
    $serviceInterest = $service ? $service['title'] : 'Professional Service Delivery';

    // 2. Insert Lead Entry
    $nextL = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM leads")->fetchColumn();
    $leadCode = 'LEAD-' . date('Y') . '-' . str_pad((string)$nextL, 4, '0', STR_PAD_LEFT);
    $leadTitle = "Web Ingestion: " . ($companyName ?: $contactName);

    $stmtL = $pdo->prepare("
        INSERT INTO leads (title, contact_name, company_name, email, phone, estimated_value, service_interest, score, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'qualified', ?)
    ");
    $stmtL->execute([$leadTitle, $contactName, $companyName, $email, $phone, $estimatedValue, $serviceInterest, $score, json_encode($payload)]);
    $leadId = (int)$pdo->lastInsertId();

    // 3. Automated Client & Project Ingestion Engine
    $nextC = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM clients")->fetchColumn();
    $clientNumber = 'CLI-' . (1000 + $nextC);

    $nameParts = explode(' ', $contactName, 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? 'Client';

    $stmtC = $pdo->prepare("
        INSERT INTO clients (client_number, client_type, first_name, last_name, company_name, email, phone, status)
        VALUES (?, 'company', ?, ?, ?, ?, ?, 'active')
    ");
    $stmtC->execute([$clientNumber, $firstName, $lastName, $companyName ?: "$contactName LLC", $email, $phone]);
    $clientId = (int)$pdo->lastInsertId();

    // Create Business Entity
    $stmtB = $pdo->prepare("INSERT INTO businesses (client_id, business_name, entity_type, status) VALUES (?, ?, 'LLC', 'active')");
    $stmtB->execute([$clientId, $companyName ?: "$contactName LLC"]);
    $businessId = (int)$pdo->lastInsertId();

    // Spin up First Project Pipeline
    $nextP = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM projects")->fetchColumn();
    $projectCode = 'PRJ-' . date('Y') . '-' . str_pad((string)$nextP, 4, '0', STR_PAD_LEFT);
    $projectTitle = ($service ? $service['title'] : 'Professional Service Delivery') . " - " . ($companyName ?: $contactName);

    $stmtP = $pdo->prepare("
        INSERT INTO projects (project_code, client_id, business_id, service_id, title, status, start_date, due_date, total_amount)
        VALUES (?, ?, ?, ?, ?, 'open', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), ?)
    ");
    $stmtP->execute([$projectCode, $clientId, $businessId, $serviceId, $projectTitle, $estimatedValue]);
    $projectId = (int)$pdo->lastInsertId();

    $pdo->commit();

    // 4. Instantiate Automation Orchestrator to Check Requirements & Emit Dispatch
    $twilioClient = new TwilioAutomationClient(
        get_setting('twilio_account_sid', ''),
        get_setting('twilio_auth_token', ''),
        get_setting('twilio_phone_number', '')
    );
    $dispatcher = new OmnichannelDispatcher($pdo, $twilioClient);
    $orchestrator = new ServiceAutomationEngine($pdo, $dispatcher);

    $evalResult = $orchestrator->evaluateServiceWorkflow($clientId, $serviceId, $projectId);

    echo json_encode([
        'success' => true,
        'message' => 'Lead successfully ingested, converted to Client, and Project Pipeline initialized.',
        'data' => [
            'lead_code' => $leadCode,
            'lead_score' => $score,
            'client_number' => $clientNumber,
            'project_code' => $projectCode,
            'evaluation' => $evalResult
        ]
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
