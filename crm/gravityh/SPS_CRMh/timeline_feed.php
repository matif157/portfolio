<?php
/**
 * SPS Professional Services CRM
 * JSON API Endpoint for Unified Timeline Feed
 */
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');

$client_id = intval($_GET['client_id'] ?? 0);
if ($client_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid client_id']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM communications WHERE client_id = ? ORDER BY sent_at DESC LIMIT 50");
$stmt->execute([$client_id]);
$timeline = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'client_id' => $client_id,
    'timeline' => $timeline
]);
