<?php
require_once __DIR__ . '/bootstrap.php';

$isAdmin        = hrm_is_admin();
$selectedUserId = $isAdmin ? (int) ($_GET['user_id'] ?? 0) : hrm_session_user_id();
$month          = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$year           = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$month          = max(1, min(12, $month));
$year           = max(2020, min(2100, $year));
$startDate      = sprintf('%04d-%02d-01', $year, $month);
$endDate        = date('Y-m-t', strtotime($startDate));
$employees      = $isAdmin ? hrm_fetch_employees(false) : [];
$monthNames     = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$monthName      = $monthNames[$month - 1] ?? date('F', mktime(0, 0, 0, $month, 1, $year));

$reportRows = [];
if ($isAdmin) {
    foreach ($employees as $employee) {
        if ($selectedUserId > 0 && (int) $employee['user_id'] !== $selectedUserId) {
            continue;
        }

        $reportRows[] = [
            'employee' => $employee,
            'summary'  => hrm_attendance_summary((int) $employee['user_id'], $startDate, $endDate),
        ];
    }
} else {
    $employee = hrm_fetch_employee(hrm_session_user_id());
    if ($employee) {
        $reportRows[] = [
            'employee' => $employee,
            'summary'  => hrm_attendance_summary(hrm_session_user_id(), $startDate, $endDate),
        ];
    }
}

$attendanceRows    = [];
$employeesInScope  = 0;
$employeesWithLogs = 0;
$totalMinutes      = 0;
$totalWorkingDays  = 0;
$scopeLabel        = $isAdmin ? 'All employees' : 'Your attendance history';

foreach ($reportRows as $report) {
    $employee = $report['employee'];
    if (!$employee) {
        continue;
    }

    $summary       = $report['summary'];
    $employeeName  = $employee['full_name'] ?: $employee['user_name'] ?: 'Employee';
    $employeeCode  = trim((string) ($employee['hrm_employee_code'] ?: $employee['device_employee_code'] ?: ''));
    $secondaryInfo = [];

    if (!empty($employee['department_name'])) {
        $secondaryInfo[] = $employee['department_name'];
    }
    if ($employeeCode !== '') {
        $secondaryInfo[] = $employeeCode;
    }
    if (!$secondaryInfo && !empty($employee['user_email'])) {
        $secondaryInfo[] = $employee['user_email'];
    }
    if (!$secondaryInfo && !empty($employee['user_login'])) {
        $secondaryInfo[] = $employee['user_login'];
    }

    $employeesInScope++;
    $totalMinutes += (int) ($summary['total_minutes'] ?? 0);

    if ($selectedUserId > 0 || !$isAdmin) {
        $scopeLabel = $employeeName;
    }

    if (!empty($summary['rows'])) {
        $employeesWithLogs++;
    }

    foreach ($summary['rows'] as $row) {
        $workingMinutes = (int) ($row['working_minutes'] ?? 0);
        if ($workingMinutes > 0) {
            $totalWorkingDays++;
        }

        $attendanceRows[] = [
            'employee_name'      => $employeeName,
            'employee_initials'  => hrm_initials($employeeName),
            'employee_secondary' => implode(' / ', $secondaryInfo),
            'employee_color'     => abs(crc32($employeeName)) % 6,
            'work_date'          => $row['work_date'],
            'check_in'           => $row['check_in'],
            'check_out'          => $row['check_out'],
            'working_minutes'    => $workingMinutes,
        ];
    }
}

usort($attendanceRows, static function ($left, $right) {
    $dateCompare = strcmp((string) ($right['work_date'] ?? ''), (string) ($left['work_date'] ?? ''));
    if ($dateCompare !== 0) {
        return $dateCompare;
    }

    $nameCompare = strcmp((string) ($left['employee_name'] ?? ''), (string) ($right['employee_name'] ?? ''));
    if ($nameCompare !== 0) {
        return $nameCompare;
    }

    return (int) ($right['working_minutes'] ?? 0) <=> (int) ($left['working_minutes'] ?? 0);
});

$showEmployeeColumn = $isAdmin && $selectedUserId === 0;
$totalHours         = number_format($totalMinutes / 60, 2);
$resultsCount       = count($attendanceRows);
$resultsCountLabel  = $resultsCount . ' ' . ($resultsCount === 1 ? 'entry' : 'entries');
$tableOrder         = $showEmployeeColumn ? [[1, 'desc'], [0, 'asc']] : [[0, 'desc']];
$tableColumnDefs    = $showEmployeeColumn
    ? [
        ['targets' => 0, 'width' => '30%'],
        ['targets' => 1, 'width' => '18%'],
        ['targets' => [2, 3], 'width' => '16%'],
        ['targets' => [4, 5], 'width' => '10%'],
    ]
    : [
        ['targets' => 0, 'width' => '24%'],
        ['targets' => [1, 2], 'width' => '22%'],
        ['targets' => [3, 4], 'width' => '14%'],
    ];

new_hrm_render_page_start(
    $isAdmin ? 'Attendance Logs' : 'My Attendance Logs',
    $isAdmin ? 'Attendance Logs' : 'My Attendance Logs',
    $isAdmin
        ? 'Review attendance day by day for any employee.'
        : 'Review your attendance history and total working hours.'
);
?>

<!-- DataTables CDN -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<style>
.al-filter-card {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    padding: 20px 22px 18px;
    margin-bottom: 20px;
    box-shadow: 0 4px 24px rgba(15, 23, 42, .06);
}

.al-filter-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    color: var(--hrm-text);
    margin-bottom: 16px;
}

.al-filter-title svg {
    width: 16px;
    height: 16px;
    stroke: var(--hrm-primary);
}

.al-filter-grid {
    display: grid;
    gap: 12px;
    align-items: end;
}

.al-filter-grid.has-employee {
    grid-template-columns: 2fr 1fr 1fr auto;
}

.al-filter-grid.no-employee {
    grid-template-columns: 1fr 1fr auto;
}

@media (max-width: 767px) {
    .al-filter-grid.has-employee,
    .al-filter-grid.no-employee {
        grid-template-columns: 1fr 1fr;
    }
}

.al-field label {
    display: block;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--hrm-muted);
    margin-bottom: 5px;
}

.al-field select,
.al-field input[type="number"] {
    width: 100%;
    border: 1.5px solid var(--hrm-border);
    border-radius: 8px;
    padding: 9px 12px;
    font-size: 13px;
    color: var(--hrm-text);
    background: #fafbfd;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
}

.al-field select:focus,
.al-field input[type="number"]:focus {
    border-color: var(--hrm-primary);
    box-shadow: 0 0 0 3px rgba(20, 86, 184, .10);
    background: #fff;
}

.al-submit-btn {
    padding: 9px 20px;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg, #1456b8, #1e75f0);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: opacity .15s, transform .15s;
    height: 40px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.al-submit-btn:hover {
    opacity: .9;
    transform: translateY(-1px);
}

.al-submit-btn svg {
    width: 14px;
    height: 14px;
    stroke: #fff;
}

.al-results-panel {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(15, 23, 42, .06);
}

.al-results-header {
    padding: 16px 22px;
    background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    border-bottom: 1px solid var(--hrm-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.al-results-title-wrap {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.al-results-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--hrm-text);
}

.al-results-title svg {
    width: 18px;
    height: 18px;
    stroke: var(--hrm-primary);
}

.al-results-sub {
    font-size: 12px;
    color: var(--hrm-muted);
    margin-top: 3px;
}

.al-results-count {
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--hrm-primary-soft);
    color: var(--hrm-primary);
}

.al-results-meta {
    padding: 14px 20px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.al-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.al-stat-pill.is-hours {
    background: var(--hrm-primary-soft);
    color: var(--hrm-primary);
}

.al-stat-pill.is-days {
    background: #e8f7ee;
    color: #15803d;
}

.al-stat-pill.is-period {
    background: #f1ebff;
    color: #6d28d9;
}

.al-stat-pill.is-people {
    background: #eef2f6;
    color: #475569;
}

.al-results-body {
    padding: 18px 20px 20px;
}

.al-employee-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.al-employee-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    flex-shrink: 0;
}

.al-employee-info {
    display: flex;
    flex-direction: column;
}

.al-employee-primary {
    font-size: 13px;
    font-weight: 700;
    color: var(--hrm-text);
}

.al-employee-secondary {
    font-size: 11px;
    color: var(--hrm-muted);
    margin-top: 1px;
}

.al-date-primary {
    font-size: 13px;
    font-weight: 700;
    color: var(--hrm-text);
}

.al-date-secondary {
    display: block;
    margin-top: 1px;
    font-size: 11px;
    color: var(--hrm-muted);
}

.al-dt-table thead th {
    background: #f4f7fb !important;
    color: var(--hrm-muted) !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .06em !important;
    border-bottom: 2px solid var(--hrm-border) !important;
    padding: 10px 12px !important;
    white-space: nowrap;
}

.al-dt-table td {
    vertical-align: middle !important;
    padding: 10px 12px !important;
    border-bottom: 1px solid #f1f5f9 !important;
    font-size: 13px;
}

.al-dt-table tbody tr:last-child td {
    border-bottom: none !important;
}

.al-dt-table tbody tr {
    transition: background .12s;
}

.al-dt-table tbody tr:hover td {
    background: #f5f9ff !important;
}

.al-time-empty {
    color: var(--hrm-muted);
    font-size: 12px;
}

.al-mins-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    background: var(--hrm-primary-soft);
    color: var(--hrm-primary);
}

.al-mins-zero {
    background: #eef2f6;
    color: var(--hrm-muted);
}

.al-checkin-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    font-weight: 700;
    color: #15803d;
}

.al-checkout-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    font-weight: 700;
    color: #b42318;
}

.al-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
}

.al-hours-value {
    font-weight: 700;
    color: var(--hrm-text);
}

div.dataTables_wrapper div.dataTables_length select,
div.dataTables_wrapper div.dataTables_filter input {
    border: 1.5px solid var(--hrm-border) !important;
    border-radius: 8px !important;
    padding: 6px 10px !important;
    font-size: 12px !important;
    background: #fff !important;
    outline: none !important;
    box-shadow: none !important;
}

div.dataTables_wrapper div.dataTables_filter input:focus {
    border-color: var(--hrm-primary) !important;
    box-shadow: 0 0 0 3px rgba(20, 86, 184, .10) !important;
}

div.dataTables_wrapper div.dataTables_length label,
div.dataTables_wrapper div.dataTables_filter label {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    font-size: 12px !important;
    color: var(--hrm-muted) !important;
    margin: 0 !important;
}

div.dataTables_wrapper div.dataTables_info {
    font-size: 12px !important;
    color: var(--hrm-muted) !important;
    padding-top: 10px !important;
}

div.dataTables_wrapper .pagination {
    gap: 3px;
    margin: 8px 0 0;
}

div.dataTables_wrapper .pagination .page-item .page-link {
    border-radius: 6px !important;
    font-size: 12px !important;
    padding: 5px 10px !important;
    border: 1px solid var(--hrm-border) !important;
    color: var(--hrm-text) !important;
    background: #fff !important;
    line-height: 1.4 !important;
}

div.dataTables_wrapper .pagination .page-item .page-link:hover {
    background: var(--hrm-primary-soft) !important;
    color: var(--hrm-primary) !important;
}

div.dataTables_wrapper .pagination .page-item.active .page-link {
    background: var(--hrm-primary) !important;
    border-color: var(--hrm-primary) !important;
    color: #fff !important;
}

div.dataTables_wrapper .pagination .page-item.disabled .page-link {
    opacity: .4 !important;
}

.al-empty {
    text-align: center;
    padding: 36px 20px;
    color: var(--hrm-muted);
    font-size: 13px;
}

.al-empty svg {
    width: 36px;
    height: 36px;
    stroke: var(--hrm-border);
    margin-bottom: 10px;
    display: block;
    margin-inline: auto;
}

.avc-0 {
    background: #e8f1ff;
    color: #1456b8;
}

.avc-1 {
    background: #e8f7ee;
    color: #15803d;
}

.avc-2 {
    background: #f1ebff;
    color: #6d28d9;
}

.avc-3 {
    background: #fff4df;
    color: #b45309;
}

.avc-4 {
    background: #feeceb;
    color: #b42318;
}

.avc-5 {
    background: #e0f7f5;
    color: #0f766e;
}
</style>

<div class="al-filter-card">
    <div class="al-filter-title">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
        </svg>
        Filter Attendance Logs
    </div>
    <form method="get" action="new_hrm/attendance_logs.php">
        <div class="al-filter-grid <?php echo $isAdmin ? 'has-employee' : 'no-employee'; ?>">
            <?php if ($isAdmin): ?>
                <div class="al-field">
                    <label>Employee</label>
                    <select name="user_id">
                        <option value="0">All Employees</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo (int) $employee['user_id']; ?>" <?php echo $selectedUserId === (int) $employee['user_id'] ? 'selected' : ''; ?>>
                                <?php echo hrm_e($employee['full_name'] ?: $employee['user_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="al-field">
                <label>Month</label>
                <select name="month">
                    <?php foreach ($monthNames as $index => $label): ?>
                        <?php $value = $index + 1; ?>
                        <option value="<?php echo $value; ?>" <?php echo $month === $value ? 'selected' : ''; ?>>
                            <?php echo hrm_e($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="al-field">
                <label>Year</label>
                <input type="number" name="year" min="2020" max="2100" value="<?php echo (int) $year; ?>" placeholder="2026">
            </div>

            <div>
                <button type="submit" class="al-submit-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    Load
                </button>
            </div>
        </div>
    </form>
</div>

<?php if (!$reportRows): ?>
    <div class="al-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5">
            <rect x="3" y="4" width="18" height="18" rx="2"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
        </svg>
        No employee records to display.
    </div>
<?php else: ?>
    <div class="al-results-panel">
        <div class="al-results-header">
            <div class="al-results-title-wrap">
                <div>
                    <div class="al-results-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                        </svg>
                        Attendance Logs
                    </div>
                    <div class="al-results-sub">
                        <?php echo hrm_e($scopeLabel . ' for ' . $monthName . ' ' . $year); ?>
                    </div>
                </div>
            </div>
            <span class="al-results-count"><?php echo hrm_e($resultsCountLabel); ?></span>
        </div>

        <div class="al-results-meta">
            <span class="al-stat-pill is-hours">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" width="12">
                    <circle cx="8" cy="8" r="6"/>
                    <polyline points="8 4 8 8 10.5 10"/>
                </svg>
                <?php echo hrm_e($totalHours); ?> hrs
            </span>
            <span class="al-stat-pill is-days">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" width="12">
                    <rect x="1" y="1" width="14" height="14" rx="2"/>
                    <polyline points="4.5 8.5 7 11 11.5 5.5"/>
                </svg>
                <?php echo (int) $totalWorkingDays; ?> working days
            </span>
            <span class="al-stat-pill is-period">
                <?php echo hrm_e($monthName . ' ' . $year); ?>
            </span>
            <?php if ($showEmployeeColumn): ?>
                <span class="al-stat-pill is-people">
                    <?php echo (int) $employeesInScope; ?> <?php echo $employeesInScope === 1 ? 'employee' : 'employees'; ?>
                </span>
            <?php elseif ($isAdmin && $employeesWithLogs > 1): ?>
                <span class="al-stat-pill is-people">
                    <?php echo (int) $employeesWithLogs; ?> employees with logs
                </span>
            <?php endif; ?>
        </div>

        <div class="al-results-body">
            <?php if (!$attendanceRows): ?>
                <div class="al-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5">
                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                    </svg>
                    No attendance logs found for this period.
                </div>
            <?php else: ?>
                <table id="attendanceLogTable" class="al-dt-table display nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <?php if ($showEmployeeColumn): ?>
                                <th>Employee</th>
                            <?php endif; ?>
                            <th>Date</th>
                            <th>Arrival</th>
                            <th>Departure</th>
                            <th>Working Mins</th>
                            <th>Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceRows as $row): ?>
                            <?php
                            $workingMinutes = (int) $row['working_minutes'];
                            $workingHours   = number_format($workingMinutes / 60, 2);
                            ?>
                            <tr>
                                <?php if ($showEmployeeColumn): ?>
                                    <td>
                                        <div class="al-employee-cell">
                                            <div class="al-employee-avatar avc-<?php echo (int) $row['employee_color']; ?>">
                                                <?php echo hrm_e($row['employee_initials']); ?>
                                            </div>
                                            <div class="al-employee-info">
                                                <span class="al-employee-primary"><?php echo hrm_e($row['employee_name']); ?></span>
                                                <?php if ($row['employee_secondary'] !== ''): ?>
                                                    <span class="al-employee-secondary"><?php echo hrm_e($row['employee_secondary']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                <?php endif; ?>
                                <td data-order="<?php echo hrm_e($row['work_date']); ?>">
                                    <span class="al-date-primary"><?php echo hrm_e(date('d M Y', strtotime($row['work_date']))); ?></span>
                                    <span class="al-date-secondary"><?php echo hrm_e(date('l', strtotime($row['work_date']))); ?></span>
                                </td>
                                <td data-order="<?php echo hrm_e($row['check_in'] ?: ''); ?>">
                                    <?php if ($row['check_in']): ?>
                                        <span class="al-checkin-badge">
                                            <span class="al-dot"></span>
                                            <?php echo hrm_e(date('h:i A', strtotime($row['check_in']))); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="al-time-empty">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-order="<?php echo hrm_e($row['check_out'] ?: ''); ?>">
                                    <?php if ($row['check_out']): ?>
                                        <span class="al-checkout-badge">
                                            <span class="al-dot"></span>
                                            <?php echo hrm_e(date('h:i A', strtotime($row['check_out']))); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="al-time-empty">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-order="<?php echo $workingMinutes; ?>">
                                    <span class="al-mins-badge <?php echo $workingMinutes === 0 ? 'al-mins-zero' : ''; ?>">
                                        <?php echo $workingMinutes; ?> min
                                    </span>
                                </td>
                                <td data-order="<?php echo $workingMinutes; ?>">
                                    <span class="al-hours-value"><?php echo hrm_e($workingHours); ?> hrs</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="<?php echo $showEmployeeColumn ? 4 : 3; ?>" style="text-align:right; font-size:12px; color:var(--hrm-muted); font-weight:700; text-transform:uppercase; letter-spacing:.05em;">
                                Total
                            </th>
                            <th style="font-size:13px; font-weight:800; color:var(--hrm-primary);">
                                <?php echo (int) $totalMinutes; ?> min
                            </th>
                            <th style="font-size:13px; font-weight:800; color:var(--hrm-primary);">
                                <?php echo hrm_e($totalHours); ?> hrs
                            </th>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
(function () {
    function init() {
        if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
            return setTimeout(init, 50);
        }

        var $table = $('#attendanceLogTable');
        if ($table.length && !$.fn.DataTable.isDataTable($table)) {
            $table.DataTable({
                pageLength: 15,
                responsive: true,
                autoWidth: false,
                order: <?php echo json_encode($tableOrder); ?>,
                language: {
                    search: '',
                    searchPlaceholder: 'Search attendance...',
                    lengthMenu: 'Show _MENU_ rows',
                    info: 'Showing _START_ to _END_ of _TOTAL_',
                    infoEmpty: 'No attendance logs found',
                    emptyTable: 'No attendance logs for this period',
                    paginate: { previous: '<', next: '>' }
                },
                dom: '<"d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"lf>rtip',
                columnDefs: <?php echo json_encode($tableColumnDefs); ?>
            });
        }
    }

    init();
})();
</script>

<?php
new_hrm_render_page_end();
?>
