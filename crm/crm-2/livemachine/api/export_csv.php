<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Attendance.php';
require_once __DIR__ . '/../classes/Payroll.php';

Auth::start();
if (!Auth::check()) {
    http_response_code(401);
    exit('unauthorized');
}

$kind = $_GET['kind'] ?? 'attendance';   // attendance | monthly | workinghours
$from = $_GET['from'] ?? null;
$to   = $_GET['to']   ?? null;
$q    = $_GET['q']    ?? null;
$ym   = $_GET['ym']   ?? date('Y-m');
$emp  = isset($_GET['emp']) && $_GET['emp'] !== '' ? (int)$_GET['emp'] : null;

$file = sprintf('attendance_%s_%s.csv', $kind, date('Ymd_His'));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

$cfg = Payroll::settings();

/** Map the internal day_status to a human label for spreadsheets. */
$dayLabel = static fn(string $s): string => $s === 'full' ? 'Full day' : ($s === 'half' ? 'Half day' : '');

/** Break Start / Break End cells, each listing the day's breaks (Meal/Short tagged). */
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
    foreach ($breaks as $b) {
        $parts[] = $b['ongoing'] ? 'open' : (string)$b['end'];
    }
    return implode('; ', $parts);
};

if ($kind === 'monthly') {
    fputcsv($out, ['Employee ID','Name','Department','Date','First In','Last Out','Punches','Break Start','Break End','Meal Break h','Short Break h','Gross Hours','Break Hours','Worked Hours','Overtime Hours','Arrival','Day']);
    foreach (Attendance::monthlyReport($ym, $emp) as $r) {
        if (empty($r['day'])) continue;
        $d    = Payroll::computeDay($r['punch_times'] ? explode(',', $r['punch_times']) : [], $cfg, (string)$r['day']);
        $late = $d['first_in'] && substr($d['first_in'], 11, 5) > $cfg['late_threshold'];
        fputcsv($out, [
            $r['device_user_id'],
            $r['name'],
            $r['department'],
            $r['day'],
            $d['first_in'] ? substr($d['first_in'], 11, 5) : '',
            $d['last_out'] ? substr($d['last_out'], 11, 5) : '',
            $r['punches'],
            $breakStarts($d['breaks']),
            $breakEnds($d['breaks']),
            round($d['meal_break_hours'], 2),
            round($d['short_break_hours'], 2),
            round($d['gross_hours'], 2),
            round($d['break_hours'], 2),
            round($d['net_hours'], 2),
            round($d['overtime_hours'], 2),
            $late ? 'Late' : 'On time',
            $dayLabel($d['day_status']),
        ]);
    }
} elseif ($kind === 'workinghours') {
    $rfrom = $from ?: date('Y-m-01');
    $rto   = $to   ?: date('Y-m-d');
    fputcsv($out, ['Employee ID','Name','Department','Date','First In','Last Out','Break Start','Break End','Meal Break h','Short Break h','Gross Hours','Break Hours','Worked Hours','Overtime Hours','Arrival','Day']);
    foreach (Attendance::rangeReport($rfrom, $rto, $emp) as $r) {
        if (empty($r['day'])) continue;
        $d    = Payroll::computeDay($r['punch_times'] ? explode(',', $r['punch_times']) : [], $cfg, (string)$r['day']);
        $late = $d['first_in'] && substr($d['first_in'], 11, 5) > $cfg['late_threshold'];
        fputcsv($out, [
            $r['device_user_id'],
            $r['name'],
            $r['department'],
            $r['day'],
            $d['first_in'] ? substr($d['first_in'], 11, 5) : '',
            $d['last_out'] ? substr($d['last_out'], 11, 5) : '',
            $breakStarts($d['breaks']),
            $breakEnds($d['breaks']),
            round($d['meal_break_hours'], 2),
            round($d['short_break_hours'], 2),
            round($d['gross_hours'], 2),
            round($d['break_hours'], 2),
            round($d['net_hours'], 2),
            round($d['overtime_hours'], 2),
            $late ? 'Late' : 'On time',
            $dayLabel($d['day_status']),
        ]);
    }
} else {
    // Mirrors the Attendance page's review filter, and carries the review state
    // through so a crossed-off punch is never mistaken for a counted one.
    $review = (string)($_GET['review'] ?? '');
    fputcsv($out, ['Punch Time','Device Time','Device User ID','Employee','Department','Type','Source','Arrival','Review','Review Note']);
    foreach (Attendance::listing($from, $to, $q, 50000, $emp, $review !== '' ? $review : null) as $r) {
        fputcsv($out, [
            $r['punch_time'],
            $r['device_punch_time'] ?? '',
            $r['device_user_id'],
            $r['employee_name'],
            $r['department'],
            $r['type_label'] ?? '',
            $r['source'],
            $r['display_status'] ?? '',
            $r['review_status'] === 'rejected' ? 'WRONG — not counted' : ($r['review_status'] === 'approved' ? 'Correct' : ''),
            $r['review_note'] ?? '',
        ]);
    }
}
fclose($out);
