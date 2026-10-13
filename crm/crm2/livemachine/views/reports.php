<?php
declare(strict_types=1);
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Attendance.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Payroll.php';
require_once __DIR__ . '/../partials/format.php';

Auth::requireLogin();
$pageTitle = 'Reports';
$ym   = $_GET['ym'] ?? date('Y-m');
$emp  = isset($_GET['emp']) && $_GET['emp'] !== '' ? (int)$_GET['emp'] : null;
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to']   ?? ''));

/* A date range, when given, wins over the month picker. Filling in only one side
   means "just that day"; a reversed range is swapped rather than returning nothing. */
$useRange = $from !== '' || $to !== '';
if ($useRange) {
    $from = $from !== '' ? $from : $to;
    $to   = $to   !== '' ? $to   : $from;
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
}

$cfg       = Payroll::settings();
$rows      = $useRange
    ? Attendance::rangeReport($from, $to, $emp)
    : Attendance::monthlyReport($ym, $emp);
$employees = Employee::all('', 1, 500);

// Name of the filtered employee, for the panel heading (null = all employees).
$empName = null;
foreach ($employees as $e) {
    if ($emp === (int)$e['id']) {
        $empName = $e['name'];
        break;
    }
}

/* Export the same slice that is on screen. A date range maps to the workinghours
   export, which already takes from/to/emp; the month maps to the monthly one. */
$csvQs = http_build_query(array_filter($useRange
    ? ['kind' => 'workinghours', 'from' => $from, 'to' => $to, 'emp' => $emp]
    : ['kind' => 'monthly',      'ym'   => $ym,   'emp' => $emp]));

/* Flatten to one row per employee-day so it renders as a single searchable table.
   Hours/break/day-verdict come from Payroll::computeDay (same source as Payroll). */
$flat = [];
foreach ($rows as $r) {
    if (empty($r['day'])) {
        continue;
    }
    $times = $r['punch_times'] ? explode(',', $r['punch_times']) : [];
    $d     = Payroll::computeDay($times, $cfg, (string)$r['day']);
    $late  = $d['first_in'] && substr($d['first_in'], 11, 5) > $cfg['late_threshold'];
    $flat[] = [
        'name'     => $r['name'],
        'dept'     => $r['department'] ?? '',
        'day'      => $r['day'],
        'first'    => $d['first_in'] ? substr($d['first_in'], 11, 5) : '',
        'last'     => $d['last_out'] ? substr($d['last_out'], 11, 5) : '',
        'gross'    => $d['gross_hours'],
        'break'    => $d['break_hours'],
        'breaks'   => $d['breaks'],
        'meal'     => $d['meal_break_hours'],
        'short'    => $d['short_break_hours'],
        'hours'    => $d['net_hours'],
        'ot'       => $d['overtime_hours'],
        'status'   => $d['day_status'],
        'on_break' => $d['on_break'],
        'late'     => $late ? 1 : 0,
    ];
}

/* Newest first: order by date, then by first punch time of that day. */
usort($flat, static fn(array $a, array $b): int => ($b['day'] <=> $a['day']) ?: ($b['first'] <=> $a['first']));

require __DIR__ . '/../partials/head.php';
?>
<div class="app">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>
        <div class="content">
            <section class="panel">
                <header>
                    <h3>
                        <?php if ($useRange): ?>
                            Report &mdash; <?= htmlspecialchars($from) ?> &rarr; <?= htmlspecialchars($to) ?>
                        <?php else: ?>
                            Monthly report &mdash; <?= htmlspecialchars($ym) ?>
                        <?php endif; ?>
                        <?php if ($empName !== null): ?>
                            <span class="text-mute" style="font-weight:normal">(<?= htmlspecialchars($empName) ?>)</span>
                        <?php endif; ?>
                    </h3>
                    <div class="flex">
                        <form method="get" class="flex" style="gap:6px;flex-wrap:wrap" data-tour="rep-filter">
                            <input type="hidden" name="route" value="reports">
                            <input type="month" name="ym" value="<?= htmlspecialchars($ym) ?>" class="btn"
                                   title="<?= $useRange ? 'Ignored while a date range is set — clear the dates to use the month' : 'Whole month' ?>">
                            <span class="text-mute">or</span>
                            <input type="date" class="btn" name="from" value="<?= htmlspecialchars($from) ?>" title="From date">
                            <span class="text-mute">to</span>
                            <input type="date" class="btn" name="to" value="<?= htmlspecialchars($to) ?>" title="To date">
                            <select name="emp" class="btn">
                                <option value="">All employees</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?= (int)$e['id'] ?>" <?= $emp === (int)$e['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['name']) ?> (<?= htmlspecialchars($e['device_user_id']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn primary"><i data-lucide="filter"></i> Show</button>
                        </form>
                        <?php if ($useRange): ?>
                            <a class="btn" href="?route=reports&ym=<?= urlencode($ym) ?><?= $emp !== null ? '&emp=' . $emp : '' ?>"
                               title="Back to the whole month"><i data-lucide="x"></i> Clear dates</a>
                        <?php endif; ?>
                        <a class="btn" href="api/export_csv.php?<?= htmlspecialchars($csvQs) ?>"><i data-lucide="download"></i> CSV</a>
                    </div>
                </header>
                <div class="body" style="padding:0" data-tour="rep-table">
                    <?php if (!$flat): ?>
                        <p class="empty" style="padding:40px">
                            No data for
                            <?= $empName !== null ? htmlspecialchars($empName) . ' in' : '' ?>
                            <?= $useRange
                                ? htmlspecialchars($from) . ' &rarr; ' . htmlspecialchars($to)
                                : 'this month' ?>.
                        </p>
                    <?php else: ?>
                        <table class="table" data-dt data-dt-title="monthly-report" data-dt-order="[[2, &quot;desc&quot;], [3, &quot;desc&quot;]]">
                            <thead><tr>
                                <th>Employee</th><th>Department</th><th>Date</th>
                                <th>First In</th>
                                <th>Break Start</th><th>Break End</th>
                                <th class="text-right">Meal&nbsp;h</th>
                                <th class="text-right">Short&nbsp;h</th>
                                <th class="text-right">Gross&nbsp;h</th>
                                <th class="text-right">Break&nbsp;h</th>
                                <th class="text-right">Worked&nbsp;h</th>
                                <th class="text-right">OT&nbsp;h</th>
                                <th>Arrival</th><th>Last Out</th><th>Day</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($flat as $d): ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['name']) ?></td>
                                    <td><?= htmlspecialchars($d['dept']) ?></td>
                                    <td><?= htmlspecialchars($d['day']) ?></td>
                                    <td><?= htmlspecialchars($d['first']) ?></td>
                                    <td><?= render_break_starts($d['breaks']) ?></td>
                                    <td><?= render_break_ends($d['breaks']) ?></td>
                                    <td class="text-right text-mute"><?= (float)$d['meal'] > 0 ? number_format((float)$d['meal'], 2) : '<span class="text-mute">&mdash;</span>' ?></td>
                                    <td class="text-right text-mute"><?= (float)$d['short'] > 0 ? number_format((float)$d['short'], 2) : '<span class="text-mute">&mdash;</span>' ?></td>
                                    <td class="text-right"><?= number_format((float)$d['gross'], 2) ?></td>
                                    <td class="text-right text-mute"><?= number_format((float)$d['break'], 2) ?></td>
                                    <td class="text-right"><strong><?= number_format((float)$d['hours'], 2) ?></strong></td>
                                    <td class="text-right"><?= (float)$d['ot'] > 0 ? '<strong style="color:var(--primary)">' . number_format((float)$d['ot'], 2) . '</strong>' : '<span class="text-mute">&mdash;</span>' ?></td>
                                    <td><span class="badge <?= $d['late'] ? 'late' : 'present' ?>"><?= $d['late'] ? 'Late' : 'On time' ?></span></td>
                                    <td>
                                        <?= htmlspecialchars($d['last']) ?>
                                        <?php if ($d['on_break']): ?>
                                            <span class="badge late" title="On break — has not clocked out for the day">Break Start</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($d['status'] === 'full'): ?>
                                            <span class="badge present">Full day</span>
                                        <?php elseif ($d['status'] === 'half'): ?>
                                            <span class="badge late">Half day</span>
                                        <?php else: ?>
                                            <span class="text-mute">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body></html>
