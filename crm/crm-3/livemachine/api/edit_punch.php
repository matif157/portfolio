<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Auth::requireCsrf();
$in = api_input();

$id   = (int)($in['id'] ?? 0);
$when = trim((string)($in['punch_time'] ?? ''));
$type = isset($in['punch_type']) && $in['punch_type'] !== '' ? (int)$in['punch_type'] : null;

if ($id <= 0 || $when === '') api_fail('missing_fields');
if (!Attendance::findPunch($id)) api_fail('not_found', 404);

try {
    Attendance::updatePunch($id, $when, $type);
} catch (\InvalidArgumentException $e) {
    api_fail('bad_time');
}

api_ok(['updated' => true]);
