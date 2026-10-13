<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// Read-only: returns the biometric device's current clock (CMD_GET_TIME)
// WITHOUT modifying it. Used by the live header clock. GET, no CSRF —
// same shape as device_status.php.
try {
    $dev  = open_device();
    $time = $dev->getDeviceTime();
    $dev->disconnect();
    if ($time === null) {
        api_fail('time_unavailable', 502);
    }
    api_ok(['device_time' => $time]);
} catch (\Throwable $e) {
    api_fail('device_error', 500, ['detail' => $e->getMessage()]);
}
