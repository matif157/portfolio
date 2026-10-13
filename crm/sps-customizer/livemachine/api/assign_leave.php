<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/Leave.php';

Auth::requireCsrf();
$in = api_input();

$empId = (int)($in['employee_id'] ?? 0);
$type  = trim((string)($in['leave_type'] ?? ''));
$year  = (int)($in['year'] ?? date('Y'));
$days  = (float)($in['allocated_days'] ?? 0);

if ($empId <= 0 || $type === '') api_fail('missing_fields');
if (!Employee::find($empId))     api_fail('employee_not_found', 404);
if ($year < 2000 || $year > 2100) api_fail('bad_year');

Leave::assign($empId, $type, $year, max(0.0, $days));
api_ok(['assigned' => true]);
