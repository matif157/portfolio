<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Auth::requireCsrf();
$in = api_input();

$devUserId = trim((string)($in['device_user_id'] ?? ''));
$start     = trim((string)($in['break_start'] ?? ''));
$end       = trim((string)($in['break_end'] ?? ''));

if ($devUserId === '' || $start === '' || $end === '') api_fail('missing_fields');

$ts1 = strtotime($start);
$ts2 = strtotime($end);
if ($ts1 === false || $ts2 === false) api_fail('bad_time');
if ($ts2 <= $ts1) api_fail('end_before_start');

$startSql = date('Y-m-d H:i:s', $ts1);
$endSql   = date('Y-m-d H:i:s', $ts2);

// A break is the gap between a Break Start (out, type 2) and Break End (in, type 3).
// computeDay() pairs them by their position in the day's punch order; the stored
// type is for labelling. Both are 'manual' so they survive a device re-sync.
$insStart = Attendance::insertLog($devUserId, $startSql, 2, 'manual');
$insEnd   = Attendance::insertLog($devUserId, $endSql,   3, 'manual');

api_ok([
    'inserted'    => (int)$insStart + (int)$insEnd,
    'break_start' => $startSql,
    'break_end'   => $endSql,
]);
