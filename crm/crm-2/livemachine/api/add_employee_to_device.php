<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Auth::requireCsrf();

$in = api_input(); 

$id = isset($in['id']) ? (int)$in['id'] : 0;
$devUserId = trim((string)($in['device_user_id'] ?? ''));
$name      = trim((string)($in['name'] ?? ''));
$dept      = trim((string)($in['department'] ?? ''));
$desg      = trim((string)($in['designation'] ?? ''));
$cardNo    = (int)($in['card_no'] ?? 0);
$role      = (int)($in['role'] ?? 0);
$password  = preg_replace('/\D+/', '', (string)($in['password'] ?? ''));
$password  = substr((string)$password, 0, 8);

if ($devUserId === '' || $name === '') {
    api_fail('missing_fields');
}

// 1) DB upsert
$payload = compact('devUserId', 'name', 'dept', 'desg', 'cardNo', 'role');
$dbData = [
    'device_user_id' => $devUserId,
    'name'           => $name,
    'department'     => $dept ?: null,
    'designation'    => $desg ?: null,
    'card_no'        => $cardNo ?: null,
    'role'           => $role,
];

if ($id > 0) {
    Employee::update($id, $dbData);
    $localId = $id;
} else {
    $existing = Employee::findByDeviceId($devUserId);
    if ($existing) {
        Employee::update((int)$existing['id'], $dbData);
        $localId = (int)$existing['id'];
    } else {
        $localId = Employee::create($dbData);
    }
}

// 2) Push to device
$devicePushed = false;
$deviceError  = null;
try {
    $dev = open_device();
    $uid = is_numeric($devUserId) ? (int)$devUserId : crc32($devUserId) & 0xFFFF;
    $devicePushed = $dev->setUser($uid, $devUserId, $name, $role, $password, $cardNo);
    $dev->disconnect();
} catch (\Throwable $e) {
    $deviceError = $e->getMessage();
}

api_ok([
    'local_id'      => $localId,
    'device_pushed' => $devicePushed,
    'device_error'  => $deviceError,
]);
