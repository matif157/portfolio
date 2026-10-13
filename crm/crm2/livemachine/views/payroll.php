<?php
declare(strict_types=1);
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Payroll.php';
require_once __DIR__ . '/../partials/format.php';

Auth::requireLogin();
$pageTitle = 'Payroll';

$today = date('Y-m-d');
$from  = $_GET['from'] ?? date('Y-m-01');
$to    = $_GET['to']   ?? $today;
$emp   = isset($_GET['emp']) && $_GET['emp'] !== '' ? (int)$_GET['emp'] : null;
$view  = $_GET['view'] ?? 'daily'; // daily | summary

$cfg     = Payroll::settings();
$currency = $cfg['currency_symbol'];

$daily    = Payroll::daily($from, $to, $emp);
$summary  = Payroll::summary($from, $to);
if ($emp !== null) {
    $summary = array_values(array_filter($summary, fn($s) => $s['employee_id'] === $emp));
}

// Build daily groups grouped by employee for the daily view
$groupedDaily = [];
foreach ($daily as $r) {
    $key = $r['employee_id'];
    $groupedDaily[$key] ??= [
        'name'       => $r['name'],
        'department' => $r['department'],
        'rate'       => $r['hourly_rate'],
        'days'       => [],
        'total_hours'=> 0,
        'total_wage' => 0,
    ];
    $groupedDaily[$key]['days'][] = $r;
    $groupedDaily[$key]['total_hours'] += $r['net_hours'];
    $groupedDaily[$key]['total_wage']  += $r['daily_wage'];
}

$grandHours = array_sum(array_column($daily, 'net_hours'));
$grandWage  = array_sum(array_column($daily, 'daily_wage'));

$employees = Employee::all('', 1, 500);

$qs = http_build_query(array_filter([
    'route' => 'payroll',
    'from'  => $from,
    'to'    => $to,
    'emp'   => $emp,
    'view'  => $view,
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
                    <h3>Payroll
                        <span class="text-mute" style="font-weight:normal">
                            (<?= htmlspecialchars($from) ?> &rarr; <?= htmlspecialchars($to) ?>)
                        </span>
                    </h3>
                    <div class="flex">
                        <form method="get" class="flex" style="gap:6px;flex-wrap:wrap" data-tour="pay-filter">
                            <input type="hidden" name="route" value="payroll">
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
                            <select name="view" class="btn">
                                <option value="daily"   <?= $view === 'daily'   ? 'selected' : '' ?>>Daily</option>
                                <option value="summary" <?= $view === 'summary' ? 'selected' : '' ?>>Summary</option>
                            </select>
                            <button class="btn primary"><i data-lucide="filter"></i> Show</button>
                        </form>
                        <a class="btn" href="api/export_payroll.php?<?= htmlspecialchars($qs) ?>">
                            <i data-lucide="download"></i> CSV
                        </a>
                    </div>
                </header>

                <!-- KPI strip -->
                <div class="body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;padding:18px 20px;border-bottom:1px solid var(--border);background:var(--surface-2)" data-tour="pay-kpis">
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Employees with hours</div>
                        <div style="font-size:22px;font-weight:700"><?= count($groupedDaily) ?></div>
                    </div>
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Total net hours</div>
                        <div style="font-size:22px;font-weight:700"><?= number_format($grandHours, 2) ?></div>
                    </div>
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Total payable</div>
                        <div style="font-size:22px;font-weight:700;color:var(--primary)">
                            <?= htmlspecialchars($currency) ?> <?= number_format($grandWage, 2) ?>
                        </div>
                    </div>
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Days in range</div>
                        <div style="font-size:22px;font-weight:700">
                            <?= max(0, (int)((strtotime($to) - strtotime($from)) / 86400) + 1) ?>
                        </div>
                    </div>
                </div>

                <div class="body" style="padding:0;overflow-x:auto" data-tour="pay-table">
                <?php if ($view === 'summary'): ?>
                    <?php if (!$summary): ?>
                        <p class="empty" style="padding:40px">No payroll data in this range.</p>
                    <?php else: ?>
                        <table class="table" data-dt data-dt-title="payroll-summary" data-dt-order="[9, &quot;desc&quot;]">
                            <thead><tr>
                                <th>User&nbsp;ID</th><th>Name</th><th>Department</th>
                                <th class="text-right">Days&nbsp;worked</th>
                                <th class="text-right">Unpaid&nbsp;leave&nbsp;days</th>
                                <th class="text-right">Unpaid&nbsp;holiday&nbsp;days</th>
                                <th class="text-right">Net&nbsp;hours</th>
                                <th class="text-right">OT&nbsp;hours</th>
                                <th class="text-right">Rate (<?= htmlspecialchars($currency) ?>/hr)</th>
                                <th class="text-right">Total (<?= htmlspecialchars($currency) ?>)</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($summary as $s): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($s['device_user_id']) ?></code></td>
                                    <td><?= htmlspecialchars($s['name']) ?></td>
                                    <td><?= htmlspecialchars($s['department'] ?? '') ?></td>
                                    <td class="text-right"><?= (int)$s['days_worked'] ?></td>
                                    <td class="text-right"><?= ($s['unpaid_leave_days'] ?? 0) > 0 ? '<span class="badge absent">' . (int)$s['unpaid_leave_days'] . '</span>' : '<span class="text-mute">&mdash;</span>' ?></td>
                                    <td class="text-right"><?= ($s['unpaid_holiday_days'] ?? 0) > 0 ? '<span class="badge absent">' . (int)$s['unpaid_holiday_days'] . '</span>' : '<span class="text-mute">&mdash;</span>' ?></td>
                                    <td class="text-right"><?= number_format($s['total_hours'], 2) ?></td>
                                    <td class="text-right"><?= number_format($s['overtime_hours'] ?? 0, 2) ?></td>
                                    <td class="text-right"><?= number_format($s['hourly_rate'], 2) ?></td>
                                    <td class="text-right" style="font-weight:600">
                                        <?= htmlspecialchars($currency) ?> <?= number_format($s['total_wage'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--surface-2);font-weight:700">
                                    <td colspan="6" class="text-right">Grand total</td>
                                    <td class="text-right"><?= number_format($grandHours, 2) ?></td>
                                    <td colspan="2"></td>
                                    <td class="text-right" style="color:var(--primary)">
                                        <?= htmlspecialchars($currency) ?> <?= number_format($grandWage, 2) ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endif; ?>

                <?php else: /* DAILY view */ ?>
                    <?php if (!$groupedDaily): ?>
                        <p class="empty" style="padding:40px">
                            No punches in this range. Make sure attendance is synced from the device.
                        </p>
                    <?php else: foreach ($groupedDaily as $g): ?>
                        <details open style="border-bottom:1px solid var(--border)">
                            <summary style="padding:14px 20px;cursor:pointer;font-weight:600;background:var(--surface-2);display:flex;justify-content:space-between;align-items:center;gap:12px">
                                <span>
                                    <?= htmlspecialchars($g['name']) ?>
                                    <span class="text-mute" style="font-weight:normal;font-size:12px">
                                        &middot; <?= htmlspecialchars($g['department'] ?? 'No dept') ?>
                                        &middot; <?= count($g['days']) ?> days
                                        &middot; <?= htmlspecialchars($currency) ?> <?= number_format($g['rate'], 2) ?>/hr
                                    </span>
                                </span>
                                <span style="color:var(--primary);font-weight:700">
                                    <?= number_format($g['total_hours'], 2) ?>h &middot;
                                    <?= htmlspecialchars($currency) ?> <?= number_format($g['total_wage'], 2) ?>
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
                                    <th class="text-right">Net&nbsp;h</th>
                                    <th class="text-right">OT&nbsp;h</th>
                                    <th>Day</th>
                                    <th class="text-right">Rate</th>
                                    <th>Last Out</th>
                                    <th class="text-right">Over Time (<?= htmlspecialchars($currency) ?>)</th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($g['days'] as $d): ?>
                                    <?php
                                        $status          = $d['day_status'] ?? '';
                                        $isPaidLeave     = $status === 'leave_paid';
                                        $isUnpaidLeave   = $status === 'leave_unpaid';
                                        $isPaidHoliday   = $status === 'holiday_paid';
                                        $isUnpaidHoliday = $status === 'holiday_unpaid';
                                        $isTimeOff       = $isPaidLeave || $isUnpaidLeave || $isPaidHoliday || $isUnpaidHoliday;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($d['day']) ?></td>
                                        <td><?= $d['first_in'] ? substr($d['first_in'], 11, 5) : ($isTimeOff ? '<span class="text-mute">' . ($isPaidHoliday || $isUnpaidHoliday ? 'holiday' : 'leave') . '</span>' : '') ?></td>
                                        <td><?= $isTimeOff ? '<span class="text-mute">&mdash;</span>' : render_break_starts($d['breaks'] ?? []) ?></td>
                                        <td><?= $isTimeOff ? '<span class="text-mute">&mdash;</span>' : render_break_ends($d['breaks'] ?? []) ?></td>
                                        <td class="text-right text-mute"><?= ($d['meal_break_hours'] ?? 0) > 0 ? number_format($d['meal_break_hours'], 2) : '<span class="text-mute">&mdash;</span>' ?></td>
                                        <td class="text-right text-mute"><?= ($d['short_break_hours'] ?? 0) > 0 ? number_format($d['short_break_hours'], 2) : '<span class="text-mute">&mdash;</span>' ?></td>
                                        <td class="text-right"><?= number_format($d['gross_hours'], 2) ?></td>
                                        <td class="text-right text-mute"><?= number_format($d['break_hours'], 2) ?></td>
                                        <td class="text-right"><strong><?= number_format($d['net_hours'], 2) ?></strong></td>
                                        <td class="text-right"><?= ($d['overtime_hours'] ?? 0) > 0 ? '<strong style="color:var(--primary)">' . number_format($d['overtime_hours'], 2) . '</strong>' : '<span class="text-mute">&mdash;</span>' ?></td>
                                        <td>
                                            <?php if ($isPaidLeave): ?>
                                                <span class="badge present" title="Approved paid leave"><?= htmlspecialchars(ucfirst((string)($d['leave_type'] ?? 'leave'))) ?> leave</span>
                                            <?php elseif ($isUnpaidLeave): ?>
                                                <span class="badge absent" title="Approved unpaid leave — no wage credited"><?= htmlspecialchars(ucfirst((string)($d['leave_type'] ?? 'leave'))) ?> leave (unpaid)</span>
                                            <?php elseif ($isPaidHoliday): ?>
                                                <span class="badge present" title="Availed paid holiday"><?= htmlspecialchars((string)($d['holiday_name'] ?? 'Holiday')) ?></span>
                                            <?php elseif ($isUnpaidHoliday): ?>
                                                <span class="badge absent" title="Availed unpaid holiday — no wage credited"><?= htmlspecialchars((string)($d['holiday_name'] ?? 'Holiday')) ?> (unpaid)</span>
                                            <?php elseif ($status === 'full'): ?>
                                                <span class="badge present">Full day</span>
                                            <?php elseif ($status === 'half'): ?>
                                                <span class="badge late">Half day</span>
                                            <?php else: ?>
                                                <span class="text-mute">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right"><?= number_format($d['hourly_rate'], 2) ?></td>
                                        <td>
                                            <?= $d['last_out'] ? substr($d['last_out'], 11, 5) : '' ?>
                                            <?php if (!empty($d['on_break'])): ?>
                                                <span class="badge late" title="On break — has not clocked out for the day">Break Start</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right" style="font-weight:600">
                                            <?= number_format($d['daily_wage'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    <?php endforeach; endif; ?>
                <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body></html>
