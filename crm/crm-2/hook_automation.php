<?php
/* =============================================================================
   SPS CRM AUTOMATION — WEBHOOK TRIGGER ENTRY POINT
   -----------------------------------------------------------------------------
   External systems fire workflows by POSTing to this script. The token in the
   URL must match a webhook-triggered workflow's configured token.

   Drop a payload like:
     curl -X POST "https://your-crm/crm/hook_automation.php?token=ABC123" \
          -H "Content-Type: application/json" \
          -d '{"object_type":"members","object_id":5,"event":"webhook","data":{"note":"from zapier-like"}}'

   A matching (active, token matches) workflow is dispatched for every matching
   public JSON field: object_type, object_id, event, data.
   ---------------------------------------------------------------------------*/
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/automation.php';

header('Content-Type: application/json');
aut_ensure_tables($con);

$token  = trim($_GET['token']  ?? '');
$event  = trim($_GET['event']  ?? '');

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!is_array($data)) $data = [];

if ($token === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing token']);
    http_response_code(400);
    exit;
}

$payload = [
    'object_type' => $data['object_type'] ?? '',
    'object_id'   => (int)($data['object_id'] ?? 0),
    'event'       => $data['event'] ?? ($event ?: 'webhook'),
    'data'        => (array)($data['data'] ?? []),
];

// Find active webhook workflows that accept this token
$wf = aut_q($con, "SELECT * FROM sps_automations WHERE status=1 AND trigger_type='webhook'");
$matched = [];
foreach ($wf as $w) {
    $cfg = aut_cfg($w);
    if (($cfg['token'] ?? '') === $token) $matched[] = $w;
}

if (!$matched) {
    echo json_encode(['ok' => false, 'error' => 'No workflow matches token']);
    http_response_code(404);
    exit;
}

$results = [];
foreach ($matched as $w) {
    $results[] = aut_dispatch($con, (int)$w['id'], 'webhook', $payload, ['user_id' => (int)($data['user_id'] ?? 0)]);
}

echo json_encode(['ok' => true, 'fired' => count($results), 'results' => $results]);