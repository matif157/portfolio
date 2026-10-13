<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/Leave.php';

Auth::requireCsrf();
$in = api_input();

$empId = (int)($in['employee_id'] ?? 0);
$from  = trim((string)($in['from_date'] ?? ''));
$to    = trim((string)($in['to_date'] ?? '')) ?: $from;
$type  = trim((string)($in['leave_type'] ?? 'annual'));

if ($empId <= 0 || $from === '') api_fail('missing_fields');
if (!Employee::find($empId))      api_fail('employee_not_found', 404);

$id = Leave::create([
    'employee_id' => $empId,
    'leave_type'  => $type,
    'is_paid'     => isset($in['is_paid']) ? (int)(bool)$in['is_paid'] : (Leave::TYPES[$type]['paid'] ?? 1),
    'from_date'   => $from,
    'to_date'     => $to,
    'reason'      => (string)($in['reason'] ?? ''),
    // Admin may record an already-approved leave directly; defaults to pending.
    'status'      => in_array($in['status'] ?? '', ['pending','approved','rejected'], true)
                        ? $in['status'] : 'pending',
]);

api_ok(['id' => $id]);
