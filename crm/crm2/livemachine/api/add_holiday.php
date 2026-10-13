<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/Holiday.php';

Auth::requireCsrf();
$in = api_input();

$name  = trim((string)($in['name'] ?? ''));
$date  = trim((string)($in['holiday_date'] ?? ''));
$notes = trim((string)($in['notes'] ?? ''));

if ($name === '' || $date === '') api_fail('missing_fields');
if (!strtotime($date)) api_fail('bad_date');

try {
    $id = Holiday::create($name, $date, isset($in['is_paid']) ? (int)(bool)$in['is_paid'] : 1, $notes !== '' ? $notes : null);
} catch (\RuntimeException $e) {
    api_fail($e->getMessage());
}

api_ok(['id' => $id]);
