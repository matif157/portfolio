<?php
/* =============================================================================
   SPS CRM AUTOMATION — SCHEDULER / POLLER ENTRY POINT
   -----------------------------------------------------------------------------
   Runs all scheduled workflows, record-trigger polls, and due delayed steps.

   Two ways to drive it:
     1. Built-in: every authenticated page-load calls aut_poll_once() (throttled
        to once per minute via a file lock).  Works with zero setup.
     2. Dedicated: schedule this URL (or PHP binary) once per minute from
        Windows Task Scheduler / cron for exact timing:
           http://your-crm/crm/cron_automation.php?force=1
   ---------------------------------------------------------------------------*/
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/automation.php';

$force = (isset($_GET['force']) && $_GET['force'] === '1') || (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'force');

if (PHP_SAPI === 'cli') {
    $report = aut_poll_once($con, $force);
    echo json_encode($report) . "\n";
    exit;
}

header('Content-Type: application/json');
$report = aut_poll_once($con, $force);
echo json_encode($report);