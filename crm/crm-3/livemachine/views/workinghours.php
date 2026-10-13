<?php
declare(strict_types=1);
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Attendance.php';
require_once __DIR__ . '/../classes/Payroll.php';
require_once __DIR__ . '/../partials/format.php';

Auth::requireLogin();
$pageTitle = 'Working Hours';

$today = date('Y-m-d');
$from  = $_GET['from'] ?? date('Y-m-01');
$to    = $_GET['to']   ?? $today;
$emp   = isset($_GET['emp']) && $_GET['emp'] !== '' ? (int)$_GET['emp'] : null;

$cfg  = Payroll::settings();
$rows = Attendance::rangeReport($from, $to, $emp);

/* Group daily rows by employee, accumulating per-employee totals. Hours, break and
   the Full/Half-day verdict all come from Payroll::computeDay so the figures match
   the Payroll page exactly. */
$grouped = [];
foreach ($rows as $r) {
    if (empty($r['day'])) {
        continue;
    }
    $times = $r['punch_times'] ? explode(',', $r['punch_times']) : [];
    $d     = Payroll::computeDay($times, $cfg, (string)$r['day']);
    $late  = $d['first_in'] && substr($d['first_in'], 11, 5) > $cfg['late_threshold'];

    $key = (int)$r['id'];
    $grouped[$key] ??= [
        'name'        => $r['name'],
        'department'  => $r['department'] ?? '',
        'days'        => [],
        'total_hours' => 0.0,
        'total_break' => 0.0,
        'total_ot'    => 0.0,
    ];
    $grouped[$key]['days'][] = [
        'day'    => $r['day'],
        'first'  => $d['first_in'] ? substr($d['first_in'], 11, 5) : '',
        'last'   => $d['last_out'] ? substr($d['last_out'], 11, 5) : '',
        'gross'  => $d['gross_hours'],
        'break'  => $d['break_hours'],
        'breaks' => $d['breaks'],
        'meal'   => $d['meal_break_hours'],
        'short'  => $d['short_break_hours'],
        'hours'  => $d['net_hours'],
        'ot'     => $d['overtime_hours'],
        'status' => $d['day_status'],
        'on_break' => $d['on_break'],
        'late'   => $late ? 1 : 0,
    ];
    $grouped[$key]['total_hours'] += $d['net_hours'];
    $grouped[$key]['total_break'] += $d['break_hours'];
    $grouped[$key]['total_ot']    += $d['overtime_hours'];
}

$grandHours = 0.0;
foreach ($grouped as $g) {
    $grandHours += $g['total_hours'];
}
$daysInRange = max(0, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);

$employees = Employee::all('', 1, 500);

$qs = http_build_query(array_filter([
    'kind' => 'workinghours',
    'from' => $from,
    'to'   => $to,
    'emp'  => $emp,
]));

require __DIR__ . '/../partials/head.php';
?>
<div class="app">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>
        <div class="content">
            <section class="panel">
                <header>
                    <h3>Working Hours
                        <span class="text-mute" style="font-weight:normal">
                            (<?= htmlspecialchars($from) ?> &rarr; <?= htmlspecialchars($to) ?>)
                        </span>
                    </h3>
                    <div class="flex">
                        <form method="get" class="flex" style="gap:6px;flex-wrap:wrap" data-tour="wh-filter">
                            <input type="hidden" name="route" value="workinghours">
                            <input type="date" class="btn" name="from" value="<?= htmlspecialchars($from) ?>">
                            <span class="text-mute">to</span>
                            <input type="date" class="btn" name="to"   value="<?= htmlspecialchars($to) ?>">
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
                        <a class="btn" href="api/export_csv.php?<?= htmlspecialchars($qs) ?>">
                            <i data-lucide="download"></i> CSV
                        </a>
                    </div>
                </header>

                <!-- KPI strip -->
                <div class="body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;padding:18px 20px;border-bottom:1px solid var(--border);background:var(--surface-2)" data-tour="wh-kpis">
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Employees with hours</div>
                        <div style="font-size:22px;font-weight:700"><?= count($grouped) ?></div>
                    </div>
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Total worked hours (net of break)</div>
                        <div style="font-size:22px;font-weight:700;color:var(--primary)"><?= number_format($grandHours, 2) ?></div>
                    </div>
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Days in range</div>
                        <div style="font-size:22px;font-weight:700"><?= $daysInRange ?></div>
                    </div>
                </div>

                <div class="body" style="padding:0;overflow-x:auto" data-tour="wh-table">
                <?php if (!$grouped): ?>
                    <p class="empty" style="padding:40px">
                        No punches in this range. Make sure attendance is synced from the device.
                    </p>
                <?php else: foreach ($grouped as $g): ?>
                    <details open style="border-bottom:1px solid var(--border)">
                        <summary style="padding:14px 20px;cursor:pointer;font-weight:600;background:var(--surface-2);display:flex;justify-content:space-between;align-items:center;gap:12px">
                            <span>
                                <?= htmlspecialchars($g['name']) ?>
                                <span class="text-mute" style="font-weight:normal;font-size:12px">
                                    &middot; <?= htmlspecialchars($g['department'] !== '' ? $g['department'] : 'No dept') ?>
                                    &middot; <?= count($g['days']) ?> days worked
                                </span>
                            </span>
                            <span style="color:var(--primary);font-weight:700">
                                <?= number_format($g['total_hours'], 2) ?> h total
                            </span>
                        </summary>
                        <table class="table">
                            <thead><tr>
                                <th>Date</th>
                                <th>First In</th>
                                <th>Break Start</th>
                                <th>Break End</th>
                                <th class="text-right">Meal&nbsp;h</th>
                                <th class="text-right">Short&nbsp;h</th>
                                <th class="text-right">Gross&nbsp;h</th>
                                <th class="text-right">Break&nbsp;h</th>
                                <th class="text-right">Worked&nbsp;h</th>
                                <th class="text-right">OT&nbsp;h</th>
                                <th>Arrival</th>
                                <th>Last Out</th>
                                <th>Day</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($g['days'] as $d): ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['day']) ?></td>
                                    <td><?= htmlspecialchars($d['first']) ?></td>
                                    <td><?= render_break_starts($d['breaks']) ?></td>
                                    <td><?= render_break_ends($d['breaks']) ?></td>
                                    <td class="text-right text-mute"><?= $d['meal'] > 0 ? number_format($d['meal'], 2) : '<span class="text-mute">&mdash;</span>' ?></td>
                                    <td class="text-right text-mute"><?= $d['short'] > 0 ? number_format($d['short'], 2) : '<span class="text-mute">&mdash;</span>' ?></td>
                                    <td class="text-right"><?= number_format($d['gross'], 2) ?></td>
                                    <td class="text-right text-mute"><?= number_format($d['break'], 2) ?></td>
                                    <td class="text-right"><strong><?= number_format($d['hours'], 2) ?></strong></td>
                                    <td class="text-right"><?= $d['ot'] > 0 ? '<strong style="color:var(--primary)">' . number_format($d['ot'], 2) . '</strong>' : '<span class="text-mute">&mdash;</span>' ?></td>
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
                            <tfoot>
                                <tr style="background:var(--surface-2);font-weight:700">
                                    <td colspan="7" class="text-right">Total</td>
                                    <td class="text-right text-mute"><?= number_format($g['total_break'], 2) ?></td>
                                    <td class="text-right" style="color:var(--primary)"><?= number_format($g['total_hours'], 2) ?> h</td>
                                    <td class="text-right"><?= number_format($g['total_ot'], 2) ?></td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </details>
                <?php endforeach; endif; ?>
                </div>
            </section>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body></html>
