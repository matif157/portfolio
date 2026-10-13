<?php
/**
 * =====================================================
 * FILE: execute-sandbox.php
 * PURPOSE: Analyze + safely execute PHP snippets
 * =====================================================
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'log_in.php'; // for role check if needed
require_once 'ASTSecurityGuard.php';

$input = json_decode(file_get_contents('php://input'), true);
$code  = $input['code'] ?? '';
$mode  = $input['mode'] ?? 'analyze'; // analyze | execute

if (trim($code) === '') {
    echo json_encode(['success' => false, 'error' => 'No code provided']);
    exit;
}

// Strip opening PHP tag if user included it (we will wrap ourselves)
$code = preg_replace('/^\s*<\?php\s*/i', '', $code);
$code = preg_replace('/\?>\s*$/', '', $code);

$guard = new ASTSecurityGuard();
$analysis = $guard->analyze('<?php ' . $code);

if (!$analysis['safe']) {
    echo json_encode([
        'success'  => false,
        'error'    => $analysis['error'],
        'ast'      => $analysis['ast'] ?? null,
        'security' => 'failed'
    ]);
    exit;
}

// Only analyze mode
if ($mode === 'analyze') {
    echo json_encode([
        'success'  => true,
        'security' => 'passed',
        'ast'      => $analysis['ast'],
        'message'  => 'Code passed AST security checks'
    ]);
    exit;
}

// ===================== SAFE EXECUTION =====================
/**
 * We execute inside output buffering + try/catch.
 * Still not a full container isolation, but combined with AST
 * blacklist it significantly reduces risk for internal CRM use.
 */

$startTime = hrtime(true);
$startMem  = memory_get_usage(true);

ob_start();
$returnValue = null;
$executionError = null;

try {
    // Controlled closure – limits variable scope leakage
    $runner = function () use ($code) {
        // You can expose limited safe helpers here if needed
        return eval($code);
    };

    $returnValue = $runner();
} catch (Throwable $e) {
    $executionError = $e->getMessage() . ' on line ' . $e->getLine();
}

$output = ob_get_clean();

$endTime = hrtime(true);
$endMem  = memory_get_peak_usage(true);

$executionTimeMs = round(($endTime - $startTime) / 1e6, 2);
$peakMemoryKb    = round(($endMem) / 1024, 1);

if ($executionError) {
    echo json_encode([
        'success'           => false,
        'error'             => 'Runtime Error: ' . $executionError,
        'security'          => 'passed',
        'execution_time_ms' => $executionTimeMs,
        'peak_memory_kb'    => $peakMemoryKb,
        'ast'               => $analysis['ast']
    ]);
    exit;
}

echo json_encode([
    'success'           => true,
    'security'          => 'passed',
    'output'            => $output,
    'return_value'      => $returnValue,
    'execution_time_ms' => $executionTimeMs,
    'peak_memory_kb'    => $peakMemoryKb,
    'ast'               => $analysis['ast']
]);