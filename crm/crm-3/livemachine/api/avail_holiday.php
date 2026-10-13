<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/Holiday.php';

Auth::requireCsrf();
$in = api_input();

$holidayId = (int)($in['holiday_id'] ?? 0);
$empId     = (int)($in['employee_id'] ?? 0);
$isPaid    = isset($in['is_paid']) ? (int)(bool)$in['is_paid'] : 1;

if ($holidayId <= 0 || $empId <= 0) api_fail('missing_fields');
if (!Holiday::find($holidayId)) api_fail('holiday_not_found', 404);
if (!Employee::find($empId)) api_fail('employee_not_found', 404);

$id = Holiday::availEmployee($holidayId, $empId, $isPaid, (string)(Auth::user()['name'] ?: 'admin'));
api_ok(['id' => $id]);
