<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Auth::requireCsrf();
$in = api_input();

$devUserId = trim((string)($in['device_user_id'] ?? ''));
$when      = trim((string)($in['punch_time'] ?? ''));
$type      = (int)($in['punch_type'] ?? 0);

if ($devUserId === '' || $when === '') api_fail('missing_fields');

$ts = strtotime($when);
if ($ts === false) api_fail('bad_time');
$mysqlTime = date('Y-m-d H:i:s', $ts);

$ok = Attendance::insertLog($devUserId, $mysqlTime, $type, 'manual');

// A false here means uk_user_time rejected it: that employee already has a punch
// at that exact minute. Report it rather than claiming a silent success.
if (!$ok) api_fail('duplicate', 409);

api_ok(['inserted' => true]);
