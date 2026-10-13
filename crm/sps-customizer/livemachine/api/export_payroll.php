<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Payroll.php';

Auth::start();
if (!Auth::check()) {
    http_response_code(401);
    exit('unauthorized');
}

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$emp  = isset($_GET['emp']) && $_GET['emp'] !== '' ? (int)$_GET['emp'] : null;
$view = ($_GET['view'] ?? 'daily') === 'summary' ? 'summary' : 'daily';

$cfg      = Payroll::settings();
$currency = $cfg['currency_symbol'];

$file = sprintf('payroll_%s_%s_to_%s.csv', $view, str_replace('-', '', $from), str_replace('-', '', $to));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

if ($view === 'summary') {
    fputcsv($out, [
        'User ID','Name','Department','Days Worked','Unpaid Leave Days','Unpaid Holiday Days','Net Hours','Overtime Hours',
        "Hourly Rate ($currency)", "Total Wage ($currency)"
    ]);
    $rows = Payroll::summary($from, $to);
    if ($emp !== null) {
        $rows = array_values(array_filter($rows, fn($s) => $s['employee_id'] === $emp));
    }
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['device_user_id'], $r['name'], $r['department'],
            $r['days_worked'], $r['unpaid_leave_days'] ?? 0, $r['unpaid_holiday_days'] ?? 0,
            $r['total_hours'], $r['overtime_hours'] ?? 0,
            $r['hourly_rate'], $r['total_wage'],
        ]);
    }
} else {
    $breakStarts = static function (array $breaks): string {
        $parts = [];
        foreach ($breaks as $b) {
            $label = ($b['type'] ?? 'short') === 'meal' ? 'Meal' : 'Short';
            $parts[] = $label . ' ' . $b['start'];
        }
        return implode('; ', $parts);
    };
    $breakEnds = static function (array $breaks): string {
        $parts = [];
        foreach ($breaks as $b) { $parts[] = $b['ongoing'] ? 'open' : (string)$b['end']; }
        return implode('; ', $parts);
    };
    fputcsv($out, [
        'User ID','Name','Department','Date','First In','Last Out','Break Start','Break End',
        'Meal Break h','Short Break h','Gross Hours','Break Hours','Net Hours','Overtime Hours','Day',
        "Hourly Rate ($currency)", "Daily Wage ($currency)"
    ]);
    foreach (Payroll::daily($from, $to, $emp) as $r) {
        $dayLabel = match ($r['day_status']) {
            'full'           => 'Full day',
            'half'           => 'Half day',
            'leave_paid'     => ucfirst((string)($r['leave_type'] ?? 'paid')) . ' leave',
            'leave_unpaid'   => ucfirst((string)($r['leave_type'] ?? 'unpaid')) . ' leave (unpaid)',
            'holiday_paid'   => (string)($r['holiday_name'] ?? 'Holiday'),
            'holiday_unpaid' => (string)($r['holiday_name'] ?? 'Holiday') . ' (unpaid)',
            default          => '',
        };
        fputcsv($out, [
            $r['device_user_id'], $r['name'], $r['department'],
            $r['day'],
            $r['first_in'] ? substr($r['first_in'], 11, 5) : '',
            $r['last_out'] ? substr($r['last_out'], 11, 5) : '',
            $breakStarts($r['breaks'] ?? []),
            $breakEnds($r['breaks'] ?? []),
            $r['meal_break_hours'] ?? 0, $r['short_break_hours'] ?? 0,
            $r['gross_hours'], $r['break_hours'], $r['net_hours'], $r['overtime_hours'] ?? 0, $dayLabel,
            $r['hourly_rate'], $r['daily_wage'],
        ]);
    }
}
fclose($out);
