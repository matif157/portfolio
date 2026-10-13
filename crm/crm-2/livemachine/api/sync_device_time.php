<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Auth::requireCsrf();

try {
    $dev = open_device();
    // The ZKTeco stores a bare wall clock with no timezone concept, and
    // decodeTime() reads it back verbatim into attendance_logs.punch_time.
    // PHP runs as UTC on this server, so a plain new DateTimeImmutable('now')
    // pushed the device to UTC and stamped every punch 4 hours ahead of local.
    // Pin it to the business timezone so the device face, punch_time, and the
    // existing historical rows all agree.
    $ok  = $dev->setDeviceTime(new DateTimeImmutable('now', new DateTimeZone('America/New_York')));
    $newTime = $dev->getDeviceTime();
    $dev->disconnect();
    api_ok(['set' => $ok, 'device_time' => $newTime]);
} catch (\Throwable $e) {
    api_fail('device_error', 500, ['detail' => $e->getMessage()]);
}
