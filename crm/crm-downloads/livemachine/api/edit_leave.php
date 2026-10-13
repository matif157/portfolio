<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/Leave.php';

Auth::requireCsrf();
$in = api_input();

$id   = (int)($in['id'] ?? 0);
$type = trim((string)($in['leave_type'] ?? 'annual'));

if ($id <= 0) api_fail('missing_fields');
if (!Leave::find($id)) api_fail('not_found', 404);

Leave::update($id, [
    'leave_type' => $type,
    'is_paid'    => isset($in['is_paid']) ? (int)(bool)$in['is_paid'] : (Leave::TYPES[$type]['paid'] ?? 1),
]);

api_ok(['id' => $id]);
