<?php
/**
 * GRAVITY CRM - Background Runner (SPS 2040 Phase 5)
 *
 * Schedule via Windows Task Scheduler / cron:
 *   php C:\xampp\htdocs\gravityh\cron.php
 * Browser access requires an admin session (settings.view).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/core/event_bus.php';

$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    require_login();
    require_permission('settings', 'view');
}

$report = jobs_run_background();
$outbound = jobs_process_outbound(25);
$report['outbound'] = $outbound;

// SPS 2040 Email Engine: dispatch due scheduled emails, then process
// the shared outbound queue (which now carries 'email' deliverer work).
if (function_exists('em_process_schedules')) {
    $report['email_schedules'] = em_process_schedules(50);
}

log_activity('system', 'cron_background', null, 'Background jobs: ' . json_encode($report));

if ($is_cli) {
    echo json_encode($report), PHP_EOL;
} else {
    flash('info', 'Background run complete: ' . json_encode($report));
    redirect('dashboard.php');
}
