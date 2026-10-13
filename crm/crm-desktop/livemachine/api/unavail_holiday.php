<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/Holiday.php';

Auth::requireCsrf();
$in = api_input();

$id = (int)($in['id'] ?? 0);
if ($id <= 0) api_fail('missing_fields');

Holiday::removeAvail($id);
api_ok(['deleted' => true]);
