<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Auth::requireCsrf();

$in = api_input();
$id = (int)($in['id'] ?? 0);
if ($id <= 0) api_fail('bad_id');

$emp = Employee::find($id);
if (!$emp) api_fail('not_found', 404);

$devicePushed = false;
$deviceError  = null;
try {
    $dev = open_device();
    $uid = is_numeric($emp['device_user_id']) ? (int)$emp['device_user_id'] : crc32($emp['device_user_id']) & 0xFFFF;
    $devicePushed = $dev->deleteUser($uid);
    $dev->disconnect();
} catch (\Throwable $e) {
    $deviceError = $e->getMessage();
}

Employee::delete($id);

api_ok(['device_pushed' => $devicePushed, 'device_error' => $deviceError]);
