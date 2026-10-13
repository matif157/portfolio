<?php
/**
 * GRAVITY CRM - Regression Runner (CLI)
 * Usage: php tests/run.php [--filter=suite_key]
 */

require_once __DIR__ . '/harness.php';

$filter = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--filter=')) {
        $filter = substr($arg, 9);
    }
}

if (php_sapi_name() !== 'cli') {
    echo "Run this from the command line:  php tests/run.php\n";
    exit(1);
}

echo "=== GRAVITY CRM REGRESSION SUITE ===\n";

foreach ($GLOBALS['TESTS'] as $key => $t) {
    if ($filter !== null && $filter !== $key) {
        continue;
    }
    $before_pass = $GLOBALS['RUN_PASS'];
    $before_fail = $GLOBALS['RUN_FAIL'];
    echo "--- {$key}: {$t['desc']} ---\n";
    try {
        call_user_func($t['fn']);
    } catch (Throwable $e) {
        $GLOBALS['RUN_FAIL']++;
        echo "  FATAL: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
    }
    if ($GLOBALS['RUN_FAIL'] > $before_fail) {
        $GLOBALS['RUN_FAILED_SUITES'][] = $key;
    }
    echo "\n";
}

echo "=== RESULT ===\n";
echo "PASS: {$GLOBALS['RUN_PASS']}\n";
echo "FAIL: {$GLOBALS['RUN_FAIL']}\n";
if ($GLOBALS['RUN_FAILED_SUITES']) {
    echo "Failed suites: " . implode(', ', $GLOBALS['RUN_FAILED_SUITES']) . "\n";
}

exit($GLOBALS['RUN_FAIL'] > 0 ? 1 : 0);