<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Leave.php';

Auth::start();
if (!Auth::check()) {
    http_response_code(401);
    exit('unauthorized');
}

$year   = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : (int)date('Y');
$emp    = isset($_GET['emp']) && $_GET['emp'] !== '' ? (int)$_GET['emp'] : null;
$status = in_array($_GET['status'] ?? '', ['pending','approved','rejected'], true) ? $_GET['status'] : null;
$from   = sprintf('%04d-01-01', $year);
$to     = sprintf('%04d-12-31', $year);

$file = sprintf('leaves_%d_%s.csv', $year, date('Ymd_His'));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Employee','User ID','Department','Type','Paid','From','To','Days','Reason','Status','Decided By','Decided On','Note']);
foreach (Leave::listing($status, $emp, $from, $to) as $r) {
    fputcsv($out, [
        $r['employee_name'], $r['device_user_id'], $r['department'],
        Leave::typeLabel($r['leave_type']),
        $r['is_paid'] ? 'Paid' : 'Unpaid',
        $r['from_date'], $r['to_date'], $r['days'],
        $r['reason'], ucfirst($r['status']),
        $r['decided_by'], $r['decided_on'], $r['decision_note'],
    ]);
}
fclose($out);
