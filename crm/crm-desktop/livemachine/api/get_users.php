<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * Pulls users from the device and reconciles against the employees table.
 * Returns the merged list (device users with their matching local employee id).
 */

try {
    $dev = open_device();
    $devUsers = $dev->getUsers();
    $dev->disconnect();
} catch (\Throwable $e) {
    api_fail('device_error', 500, ['detail' => $e->getMessage()]);
}

$merged   = [];
$reconciled = 0;
foreach ($devUsers as $u) {
    $localId = Employee::upsertFromDevice($u);
    $reconciled++;
    $merged[] = [
        'device_user_id' => (string)$u['user_id'],
        'name'           => $u['name'] ?: ('User ' . $u['user_id']),
        'role'           => (int)$u['role'],
        'card_no'        => (int)$u['card_no'],
        'local_id'       => $localId,
    ];
}

api_ok(['users' => $merged, 'reconciled' => $reconciled]);
