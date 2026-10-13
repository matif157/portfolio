<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/Payroll.php';

Auth::requireCsrf();

$in   = api_input();
$id   = (int)  ($in['id']           ?? 0);
$rate = (float)($in['hourly_rate']  ?? 0);

if ($id <= 0)          api_fail('invalid_employee');
if ($rate < 0)         api_fail('invalid_rate');
if ($rate > 99999999)  api_fail('rate_too_large');

if (!Employee::find($id)) api_fail('employee_not_found', 404);

Payroll::setHourlyRate($id, $rate);
api_ok(['id' => $id, 'hourly_rate' => $rate]);
