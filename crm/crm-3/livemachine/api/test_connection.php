<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Auth::requireCsrf();
$in = api_input();

$ip   = trim((string)($in['ip']   ?? ''));
$port = (int)($in['port']     ?? 4370);
$pw   = (int)($in['password'] ?? 0);
if ($ip === '') api_fail('missing_ip');

$dev = new ZKDevice($ip, $port, $pw, 6, __DIR__ . '/../logs/device.log');
$pingOk = $dev->ping();
if (!$pingOk) api_ok(['ping' => false, 'connect' => false, 'time' => null]);

$connOk = $dev->connect();
$time   = $connOk ? $dev->getDeviceTime() : null;
$dev->disconnect();

api_ok(['ping' => true, 'connect' => $connOk, 'time' => $time]);
