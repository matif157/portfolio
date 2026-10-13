<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$cfg = DeviceConfig::load();
$dev = new ZKDevice($cfg->ip, $cfg->port, $cfg->password, 4, __DIR__ . '/../logs/device.log');

api_ok([
    'online'    => $dev->ping(),
    'ip'        => $cfg->ip,
    'port'      => $cfg->port,
    'brand'     => $cfg->brand,
    'last_sync' => $cfg->lastSync,
]);
