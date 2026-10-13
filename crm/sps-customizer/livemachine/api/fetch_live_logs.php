<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * Live-feed polling endpoint.
 *  - Optional ?after=<last_known_id>   → returns rows newer than that id (no device fetch)
 *  - Optional ?sync=1                  → first hit the device, ingest new punches, then return.
 */

$after = isset($_GET['after']) ? (int)$_GET['after'] : null;
$sync  = !empty($_GET['sync']);

$inserted = 0;
$skipped  = 0;
$deviceTime = null;

if ($sync) {
    try {
        $dev = open_device();
        $deviceTime = $dev->getDeviceTime();

        // The device re-offers its whole buffer on every poll, and this endpoint
        // runs every 30 seconds. Filter against what we already hold rather than
        // firing 200-odd doomed INSERTs a minute at the database.
        $logs  = $dev->getAttendanceLogs();
        $known = Attendance::knownDeviceLogs($logs);

        foreach ($logs as $log) {
            $userId = (string)$log['user_id'];
            $when   = (string)$log['timestamp'];
            if (isset($known[$userId . "\0" . $when])) { $skipped++; continue; }

            if (Attendance::insertLog($userId, $when, (int)($log['type'] ?? 0), 'device')) {
                $inserted++;
                $known[$userId . "\0" . $when] = true;
            } else {
                $skipped++;
            }
        }

        $dev->disconnect();
        DeviceConfig::load()->markSynced();
    } catch (\Throwable $e) {
        api_fail('sync_failed', 500, ['detail' => $e->getMessage()]);
    }
}

api_ok([
    'logs'        => Attendance::recent(50, $after),
    'inserted'    => $inserted,
    'skipped'     => $skipped,
    'device_time' => $deviceTime,
    'server_time' => date('Y-m-d H:i:s'),
]);
