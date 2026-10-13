<?php
/**
 * =====================================================
 * FILE: fetch-security-report.php
 * PURPOSE: Secure API to trigger Psalm taint scan
 * =====================================================
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'log_in.php';
require_once 'TaintScanner.php';

$allowed = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'] ?? '', $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'scan';

if ($action !== 'scan') {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

$scanner = new TaintScanner(__DIR__);
$result  = $scanner->scan();

echo json_encode($result);