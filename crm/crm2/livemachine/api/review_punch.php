<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * Tick / cross a punch from the Attendance page.
 *
 *   status=approved   the punch is correct as recorded
 *   status=rejected   the device stamped it wrong — exclude it from every count,
 *                     report and wage figure, but keep the row so the device
 *                     cannot re-import the same bad timestamp on the next sync
 *   status=unreviewed clear the mark
 */

Auth::requireCsrf();
$in = api_input();

$id     = (int)($in['id'] ?? 0);
$status = trim((string)($in['status'] ?? ''));
$note   = trim((string)($in['note'] ?? ''));

if ($id <= 0 || $status === '') api_fail('missing_fields');
if (!Attendance::findPunch($id)) api_fail('not_found', 404);

try {
    Attendance::setReview($id, $status, $note !== '' ? $note : null, Auth::user()['name'] ?: 'admin');
} catch (\InvalidArgumentException $e) {
    api_fail('bad_status');
}

api_ok(['review_status' => $status]);
