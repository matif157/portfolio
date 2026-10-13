<?php
/**
 * GRAVITY CRM - Regression Test Harness (SPS 2040 Phase 9, Sec 43-45)
 *
 * Plain-PHP CLI runner. Each suite is self-contained: it builds its own
 * fixtures, asserts against Phase 1-8 invariants, and cleans up after
 * itself so the CRM data stays untouched.
 *
 * Usage:  php tests/run.php [--filter=suite_key]
 */

// ---- runner state ----
$GLOBALS['TESTS'] = [];
$GLOBALS['RUN_PASS'] = 0;
$GLOBALS['RUN_FAIL'] = 0;
$GLOBALS['RUN_FAILED_SUITES'] = [];

/** Register a test suite: t_suite('key', 'Description', callable). */
function t_suite(string $key, string $desc, $fn): void {
    $GLOBALS['TESTS'][$key] = ['desc' => $desc, 'fn' => $fn];
}

/** Assert a truthy condition (or array of them); records outcome. */
function t_assert(string $name, ...$conditions): void {
    foreach ($conditions as $c) {
        if (!$c) {
            $GLOBALS['RUN_FAIL']++;
            echo "  FAIL - {$name}\n";
            return;
        }
    }
    $GLOBALS['RUN_PASS']++;
    echo "  PASS - {$name}\n";
}

/** Strict equality assertion. */
function t_eq(string $name, $expected, $actual): void {
    t_assert($name, $expected === $actual);
}

/** Record a fixture id for later teardown (rejected here; suites handle their own). */
function t_log(string $msg): void {
    echo "  ... {$msg}\n";
}

/** Finish a suite: separator line. */
function t_suite_end(string $key): void {
    echo "  suite [{$key}] done\n";
}

// ---- bootstrap the CRM layer once ----
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../core/event_bus.php';
require_once __DIR__ . '/../core/service_engine.php';
require_once __DIR__ . '/../core/integrations.php';
require_once __DIR__ . '/../core/email_engine.php';
require_once __DIR__ . '/../core/ai_engine.php';
require_once __DIR__ . '/../core/portal.php';

// Any suite file may advertise its own fixtures; load all suites.
$suiteGlob = str_replace('\\', '/', __DIR__) . '/suites/*.php';
foreach (glob($suiteGlob) ?: [] as $suiteFile) {
    require_once $suiteFile;
}