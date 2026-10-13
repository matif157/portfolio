<?php
require_once __DIR__ . '/bootstrap.php';
hrm_require_admin();

$selectedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$month          = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$year           = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$startDate      = sprintf('%04d-%02d-01', $year, $month);
$endDate        = date('Y-m-t', strtotime($startDate));
$employees      = hrm_fetch_employees(false);

$reportRows = [];
foreach ($employees as $employee) {
    if ($selectedUserId > 0 && (int) $employee['user_id'] !== $selectedUserId) {
        continue;
    }

    $reportRows[] = [
        'employee' => $employee,
        'summary'  => hrm_attendance_summary((int) $employee['user_id'], $startDate, $endDate),
    ];
}

$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));

new_hrm_render_page_start(
    'Attendance Report',
    'Attendance Report',
    'Attendance is grouped by day and converted into working minutes for reporting and salary calculations.'
);
?>

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
    grid-template-columns: 2fr 1fr 1fr auto;
}

@media (max-width: 767px) {
    .al-filter-grid {
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

.al-report-panel {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(15, 23, 42, .06);
    margin-bottom: 20px;
}

.al-report-header {
    padding: 14px 22px;
    background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    border-bottom: 1px solid var(--hrm-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.al-report-emp {
    display: flex;
    align-items: center;
    gap: 10px;
}

.al-report-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--hrm-primary-soft);
    color: var(--hrm-primary);
    font-size: 12px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.al-report-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--hrm-text);
}

.al-report-period {
    font-size: 11px;
    color: var(--hrm-muted);
    margin-top: 1px;
}

.al-report-stats {
    display: flex;
    align-items: center;
    gap: 6px;
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

.al-report-body {
    padding: 18px 20px;
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
</style>

<div class="al-filter-card">
    <div class="al-filter-title">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
        </svg>
        Filter Attendance Report
    </div>
    <form method="get" action="new_hrm/attendance_report.php">
        <div class="al-filter-grid">
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

            <div class="al-field">
                <label>Month</label>
                <select name="month">
                    <?php
                    $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                    foreach ($monthNames as $i => $label):
                        $value = $i + 1;
                    ?>
                        <option value="<?php echo $value; ?>" <?php echo $month === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
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
<?php endif; ?>

<?php foreach ($reportRows as $report): ?>
    <?php
    $employee = $report['employee'];
    $summary  = $report['summary'];
    $name     = $employee['full_name'] ?: $employee['user_name'];
    $initials = hrm_initials($name);
    $totalHours = number_format($summary['total_hours'], 2);
    $dayCount = count(array_filter($summary['rows'], fn($row) => (int) $row['working_minutes'] > 0));
    $tableId = 'alTable_' . (int) $employee['user_id'];
    ?>
    <div class="al-report-panel">
        <div class="al-report-header">
            <div class="al-report-emp">
                <div class="al-report-avatar"><?php echo hrm_e($initials); ?></div>
                <div>
                    <div class="al-report-name"><?php echo hrm_e($name); ?></div>
                    <div class="al-report-period"><?php echo $monthName . ' ' . $year; ?></div>
                </div>
            </div>
            <div class="al-report-stats">
                <span class="al-stat-pill is-hours">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" width="12">
                        <circle cx="8" cy="8" r="6"/>
                        <polyline points="8 4 8 8 10.5 10"/>
                    </svg>
                    <?php echo $totalHours; ?> hrs
                </span>
                <span class="al-stat-pill is-days">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" width="12">
                        <polyline points="9 7 12 10 9 13"/>
                        <line x1="4" y1="10" x2="12" y2="10"/>
                        <rect x="1" y="1" width="14" height="14" rx="2"/>
                    </svg>
                    <?php echo $dayCount; ?> working days
                </span>
                <span class="al-stat-pill is-period">
                    <?php echo $monthName . ' ' . $year; ?>
                </span>
            </div>
        </div>

        <div class="al-report-body">
            <?php if (!$summary['rows']): ?>
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
                <table id="<?php echo $tableId; ?>" class="al-dt-table display nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Arrival</th>
                            <th>Departure</th>
                            <th>Working Mins</th>
                            <th>Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($summary['rows'] as $row): ?>
                        <?php
                        $mins = (int) $row['working_minutes'];
                        $hours = number_format($mins / 60, 2);
                        ?>
                        <tr>
                            <td data-order="<?php echo hrm_e($row['work_date']); ?>">
                                <?php echo hrm_e(date('d M Y', strtotime($row['work_date']))); ?>
                            </td>
                            <td>
                                <?php if ($row['check_in']): ?>
                                    <span class="al-checkin-badge">
                                        <span class="al-dot"></span>
                                        <?php echo hrm_e(date('h:i A', strtotime($row['check_in']))); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="al-time-empty">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['check_out']): ?>
                                    <span class="al-checkout-badge">
                                        <span class="al-dot"></span>
                                        <?php echo hrm_e(date('h:i A', strtotime($row['check_out']))); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="al-time-empty">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-order="<?php echo $mins; ?>">
                                <span class="al-mins-badge <?php echo $mins === 0 ? 'al-mins-zero' : ''; ?>">
                                    <?php echo $mins; ?> min
                                </span>
                            </td>
                            <td data-order="<?php echo $mins; ?>">
                                <?php echo $hours; ?> hrs
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" style="text-align:right; font-size:12px; color:var(--hrm-muted); font-weight:700; text-transform:uppercase; letter-spacing:.05em;">
                                Total
                            </th>
                            <th style="font-size:13px; font-weight:800; color:var(--hrm-primary);">
                                <?php echo array_sum(array_column($summary['rows'], 'working_minutes')); ?> min
                            </th>
                            <th style="font-size:13px; font-weight:800; color:var(--hrm-primary);">
                                <?php echo $totalHours; ?> hrs
                            </th>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<script>
(function () {
    var tableIds = <?php
    $tableIds = array_map(fn($report) => 'alTable_' . (int) $report['employee']['user_id'], $reportRows);
    echo json_encode($tableIds);
    ?>;

    function init() {
        if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
            return setTimeout(init, 50);
        }

        tableIds.forEach(function (id) {
            var $table = $('#' + id);
            if ($table.length && !$.fn.DataTable.isDataTable($table)) {
                $table.DataTable({
                    pageLength: 15,
                    responsive: true,
                    order: [[0, 'asc']],
                    language: {
                        search: '',
                        searchPlaceholder: 'Search...',
                        lengthMenu: 'Show _MENU_ rows',
                        info: 'Showing _START_-_END_ of _TOTAL_',
                        infoEmpty: 'No entries',
                        emptyTable: 'No attendance logs for this period',
                        paginate: { previous: '<', next: '>' }
                    },
                    dom: '<"d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"lf>rtip',
                    columnDefs: [
                        { targets: 0, width: '22%' },
                        { targets: [1, 2], width: '20%' },
                        { targets: [3, 4], width: '14%' }
                    ]
                });
            }
        });
    }

    init();
})();
</script>

<?php
new_hrm_render_page_end();
