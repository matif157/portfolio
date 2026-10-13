<?php
declare(strict_types=1);

/**
 * Standalone smoke-test for the ZKDevice driver.
 *
 *   php test_connection.php                  # uses defaults below
 *   php test_connection.php 192.168.15.76 4370 0
 */

require_once __DIR__ . '/classes/ZKDevice.php';

$ip      = $argv[1] ?? '192.168.15.76';
$port    = (int)($argv[2] ?? 4370);
$commKey = (int)($argv[3] ?? 0);

echo "=== ZKDevice smoke-test ===\n";
echo "IP:        {$ip}\n";
echo "Port:      {$port}\n";
echo "comm_key:  {$commKey}\n\n";

$logPath = __DIR__ . '/logs/device.log';
$dev = new ZKDevice($ip, $port, $commKey, 8, $logPath);

echo "[1/5] Ping (TCP open?)            ... ";
echo $dev->ping() ? "OK\n" : "FAIL — port closed or host unreachable\n";

echo "[2/5] CMD_CONNECT + auth          ... ";
$connected = $dev->connect();
echo $connected ? "OK\n" : "FAIL (see logs/device.log)\n";

if (!$connected) {
    echo "\nIf the device responded with CMD_ACK_UNAUTH, the comm_key (password) is set.\n";
    echo "Retry with the correct comm_key:  php test_connection.php {$ip} {$port} <key>\n";
    exit(1);
}

echo "[3/5] CMD_GET_TIME                 ... ";
$t = $dev->getDeviceTime();
echo $t ?? "n/a", "\n";

echo "[4/5] getUsers() — first 5         ...\n";
try {
    $users = $dev->getUsers();
    printf("    fetched %d user(s)\n", count($users));
    foreach (array_slice($users, 0, 5) as $u) {
        printf("    uid=%-5d user_id=%-10s name=%s\n", $u['uid'], $u['user_id'], $u['name']);
    }
} catch (\Throwable $e) {
    echo "    error: ", $e->getMessage(), "\n";
}

echo "[5/5] getAttendanceLogs() — last 5 ...\n";
try {
    $logs = $dev->getAttendanceLogs();
    printf("    fetched %d log(s)\n", count($logs));
    foreach (array_slice($logs, -5) as $l) {
        printf("    %s  user_id=%-10s status=%d type=%d\n",
            $l['timestamp'], $l['user_id'], $l['status'], $l['type']);
    }
} catch (\Throwable $e) {
    echo "    error: ", $e->getMessage(), "\n";
}

$dev->disconnect();
echo "\nDone. (Detailed log written to logs/device.log)\n";
