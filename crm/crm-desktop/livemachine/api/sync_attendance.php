<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Auth::requireCsrf();

$pdo = Database::pdo();
$pdo->prepare("INSERT INTO sync_history (started_at, status) VALUES (NOW(), 'running')")->execute();
$historyId = (int)$pdo->lastInsertId();

$fetched = 0; $inserted = 0; $skipped = 0; $err = null;
$deviceTime = null; $drift = null; $warning = null;

try {
    $dev = open_device();

    // Read the device clock BEFORE importing. A device whose clock has slipped
    // stamps every punch wrong, and the punches themselves give no hint of it —
    // this is the only place the gap is visible, so record it on the sync and
    // warn, rather than silently importing another batch of bad timestamps.
    $deviceTime = $dev->getDeviceTime();
    if ($deviceTime !== null) {
        $devTs = strtotime($deviceTime);
        $srvTs = (int)(new DateTimeImmutable('now', new DateTimeZone('America/New_York')))->format('U');
        if ($devTs !== false) {
            $drift = $devTs - $srvTs;
            $tolerance = (int)($pdo->query(
                "SELECT `value` FROM settings WHERE `key`='clock_drift_tolerance_seconds'"
            )->fetchColumn() ?: 120);
            if (abs($drift) > $tolerance) {
                $warning = sprintf(
                    'Device clock is %s by %s (device %s). Punches imported now carry that error — fix it with Sync device time, then review the affected rows.',
                    $drift > 0 ? 'ahead' : 'behind',
                    gmdate(abs($drift) >= 3600 ? 'G\h i\m' : 'i\m s\s', abs($drift)),
                    $deviceTime
                );
            }
        }
    }

    $logs    = $dev->getAttendanceLogs();
    $fetched = count($logs);

    // Only import what we do not already hold. The device keeps handing back its
    // whole buffer every sync, including punches that were corrected or crossed
    // off here — matching on the untouched device_punch_time anchor means those
    // are recognised as already-seen and left alone.
    $known = Attendance::knownDeviceLogs($logs);

    foreach ($logs as $log) {
        $userId = (string)$log['user_id'];
        $when   = (string)$log['timestamp'];

        if (isset($known[$userId . "\0" . $when])) { $skipped++; continue; }

        if (Attendance::insertLog($userId, $when, (int)($log['type'] ?? 0), 'device')) {
            $inserted++;
            $known[$userId . "\0" . $when] = true;   // guard against duplicates within one batch
        } else {
            $skipped++;                              // lost a race, or blocked by uk_user_time
        }
    }

    $dev->disconnect();
    DeviceConfig::load()->markSynced();
} catch (\Throwable $e) {
    $err = $e->getMessage();
}

$pdo->prepare(
    "UPDATE sync_history
        SET completed_at = NOW(), device_time = :dt, clock_skew_seconds = :drift,
            records_fetched = :f, records_inserted = :i, records_skipped = :sk,
            status = :s, message = :m
      WHERE id = :id"
)->execute([
    ':dt'    => $deviceTime,
    ':drift' => $drift,
    ':f'     => $fetched,
    ':i'     => $inserted,
    ':sk'    => $skipped,
    ':s'     => $err ? 'failed' : 'success',
    ':m'     => $err ?? $warning,
    ':id'    => $historyId,
]);

if ($err) api_fail('sync_failed', 500, ['detail' => $err]);

api_ok([
    'fetched'     => $fetched,
    'inserted'    => $inserted,
    'skipped'     => $skipped,
    'device_time' => $deviceTime,
    'drift'       => $drift,
    'warning'     => $warning,
]);
