<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

api_ok([
    'counts'  => Attendance::dashboardCounts(),
    'hourly'  => Attendance::hourlyToday(),
]);
