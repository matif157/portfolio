<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Auth::requireCsrf();

try {
    $dev = open_device();
    $ok  = $dev->restart();
    $dev->disconnect();
    api_ok(['restarted' => $ok]);
} catch (\Throwable $e) {
    api_fail('device_error', 500, ['detail' => $e->getMessage()]);
}
