<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../classes/Holiday.php';

Auth::requireCsrf();
$in = api_input();

$year = (int)($in['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) api_fail('bad_year');

$added = Holiday::loadUsaFederalHolidays($year);
api_ok(['added' => $added]);
