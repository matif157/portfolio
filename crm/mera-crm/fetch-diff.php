<?php
/**
 * =====================================================
 * FILE: fetch-diff.php
 * PURPOSE: Handle analyze / apply requests for Rector
 * =====================================================
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'log_in.php';
require_once 'RectorRunner.php';

$allowedRoles = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'] ?? '', $allowedRoles)) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$module = preg_replace('/[^a-z0-9_\-]/i', '', $input['module'] ?? '');
$mode   = $input['mode'] ?? 'dry-run';

$allowedModules = [
    'clients'  => 'Clients Module',
    'invoices' => 'Invoices Module',
    'users'    => 'Users Module',
    'projects' => 'Projects Module',
    'sandbox'  => 'Sandbox',
];

$runner = new RectorRunner(__DIR__, $allowedModules);
$path   = $runner->resolveModulePath($module);

if ($path === null) {
    echo json_encode(['success' => false, 'error' => 'Invalid or unauthorized module path']);
    exit;
}

$dryRun = ($mode !== 'apply');
$result = $runner->run($path, $dryRun);

if (!$result['success']) {
    echo json_encode($result);
    exit;
}

/**
 * Parse Rector JSON / text output into simple diff lines
 * Rector JSON structure can vary by version; we also fall back to raw output
 */
$stdout = $result['stdout'] ?? '';
$diffLines = [];
$filesChanged = 0;
$totalChanges = 0;

$json = json_decode($stdout, true);
if (is_array($json) && isset($json['file_diffs'])) {
    foreach ($json['file_diffs'] as $fileDiff) {
        $filesChanged++;
        $diff = $fileDiff['diff'] ?? '';
        foreach (explode("\n", $diff) as $line) {
            $diffLines[] = $line;
            if (str_starts_with($line, '+') || str_starts_with($line, '-')) {
                $totalChanges++;
            }
        }
    }
} else {
    // Fallback: treat raw output as diff-like text
    foreach (explode("\n", $stdout) as $line) {
        $diffLines[] = $line;
        if (str_starts_with($line, '+') || str_starts_with($line, '-')) {
            $totalChanges++;
        }
    }
    if ($totalChanges > 0) $filesChanged = 1;
}

echo json_encode([
    'success'        => true,
    'dry_run'        => $dryRun,
    'files_changed'  => $filesChanged,
    'total_changes'  => $totalChanges,
    'diff_lines'     => $diffLines,
    'raw'            => $stdout,
    'stderr'         => $result['stderr'] ?? ''
]);