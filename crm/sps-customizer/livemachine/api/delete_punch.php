<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Auth::requireCsrf();
$in = api_input();

$id = (int)($in['id'] ?? 0);
if ($id <= 0) api_fail('missing_fields');
if (!Attendance::findPunch($id)) api_fail('not_found', 404);

// A device punch is crossed off rather than deleted — deleting it would only let
// the device re-import the same row on the next sync. Manual entries go for good.
$outcome = Attendance::deletePunch($id);
api_ok(['deleted' => $outcome === 'deleted', 'outcome' => $outcome]);
