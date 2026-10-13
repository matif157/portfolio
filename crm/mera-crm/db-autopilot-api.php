<?php
/**
 * =====================================================
 * FILE: db-autopilot-api.php
 * PURPOSE: Secure API for DB Auto-Pilot actions
 * =====================================================
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'log_in.php';
require_once 'MigrationArchitect.php';
require_once 'QueryOptimizer.php';

$allowed = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'] ?? '', $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$architect = new MigrationArchitect($pdo, __DIR__);
$optimizer = new QueryOptimizer($pdo);

switch ($action) {
    case 'schema':
        echo json_encode(['success' => true, 'tables' => $architect->getSchema()]);
        break;

    case 'generate_migration':
        $prompt = trim($input['prompt'] ?? '');
        echo json_encode($architect->generateFromPrompt($prompt));
        break;

    case 'save_migration':
        $sql = $input['sql'] ?? '';
        // Extra safety: only allow DDL-looking statements
        if (!preg_match('/^\s*(CREATE|ALTER|DROP\s+INDEX|CREATE\s+INDEX)/i', $sql)) {
            echo json_encode(['success' => false, 'error' => 'Only migration DDL can be saved']);
            break;
        }
        echo json_encode($architect->saveMigration($sql));
        break;

    case 'suggest_indexes':
        echo json_encode($optimizer->suggestIndexes());
        break;

    case 'slow_queries':
        echo json_encode($optimizer->analyzePerformance());
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}