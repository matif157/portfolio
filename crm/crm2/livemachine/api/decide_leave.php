<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/Leave.php';

Auth::requireCsrf();
$in = api_input();

$id     = (int)($in['id'] ?? 0);
$status = (string)($in['status'] ?? '');
$note   = (string)($in['note'] ?? '');

if ($id <= 0 || $status === '') api_fail('missing_fields');
if (!in_array($status, ['approved','rejected','pending'], true)) api_fail('bad_status');
if (!Leave::find($id)) api_fail('not_found', 404);

Leave::decide($id, $status, (string)(Auth::user()['name'] ?: 'admin'), $note);
api_ok(['status' => $status]);
