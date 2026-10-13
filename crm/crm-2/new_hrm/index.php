<?php
require_once __DIR__ . '/bootstrap.php';

$isAdmin = hrm_is_admin();
$userId  = hrm_session_user_id();
$employee = hrm_fetch_employee($userId);

new_hrm_render_page_start(
    $isAdmin ? 'HRM Dashboard' : 'My HRM Dashboard',
    $isAdmin ? 'HRM Dashboard' : 'My HRM Dashboard',
    $isAdmin
    ? 'Manage employees, attendance, and payroll from one place.'
    : 'View your employee details, attendance, and salary summary.'
);

/* ── ADMIN VIEW ─────────────────────────────────────── */
if ($isAdmin):
$stats      = hrm_fetch_dashboard_stats();
$recentLogs = array_slice(hrm_fetch_attendance_logs(null, date('Y-m-01'), date('Y-m-d')), 0, 10);
$employees  = hrm_fetch_employees(false); // fetch ALL for the salary DataTable
?>

<!-- ── METRIC STRIP ── -->
<div class="hrm-metrics-strip">

    <div class="hrm-metric-tile hrm-tile--blue">
        <div class="hrm-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <div class="hrm-tile-body">
            <span class="hrm-tile-label">Total Employees</span>
            <span class="hrm-tile-value"><?php echo (int)$stats['employee_count']; ?></span>
        </div>
        <div class="hrm-tile-shine"></div>
    </div>

    <div class="hrm-metric-tile hrm-tile--green">
        <div class="hrm-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 11 12 14 22 4"/>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
        </div>
        <div class="hrm-tile-body">
            <span class="hrm-tile-label">Today's Check-ins</span>
            <span class="hrm-tile-value"><?php echo (int)$stats['today_checkins']; ?></span>
        </div>
        <div class="hrm-tile-shine"></div>
    </div>

    <div class="hrm-metric-tile hrm-tile--amber">
        <div class="hrm-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </div>
        <div class="hrm-tile-body">
            <span class="hrm-tile-label">Today's Check-outs</span>
            <span class="hrm-tile-value"><?php echo (int)$stats['today_checkouts']; ?></span>
        </div>
        <div class="hrm-tile-shine"></div>
    </div>

    <div class="hrm-metric-tile hrm-tile--violet">
        <div class="hrm-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="hrm-tile-body">
            <span class="hrm-tile-label">Still Clocked In</span>
            <span class="hrm-tile-value"><?php echo (int)$stats['open_attendance']; ?></span>
        </div>
        <div class="hrm-tile-shine"></div>
    </div>

</div><!-- /metrics-strip -->

<!-- ── MAIN GRID ── -->
<div class="hrm-dash-grid">

    <!-- Attendance Table -->
    <div class="hrm-panel hrm-panel--wide">
        <div class="hrm-panel-head">
            <div class="hrm-panel-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hrm-panel-icon">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                Recent Attendance Activity
            </div>
            <span class="hrm-panel-badge"><?php echo count($recentLogs); ?> entries</span>
        </div>
        <div class="hrm-panel-body">
            <table id="adminAttendanceTable" class="hrm-dt-table display nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Date &amp; Time</th>
                        <th>Action</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td>
                            <div class="hrm-cell-person">
                                <div class="hrm-cell-avatar"><?php echo hrm_e(hrm_initials($log['user_name'] ?: 'U')); ?></div>
                                <span><?php echo hrm_e($log['user_name'] ?: 'Unknown'); ?></span>
                            </div>
                        </td>
                        <td><?php echo hrm_e(date('d M Y  h:i A', strtotime($log['log_time']))); ?></td>
                        <td>
                            <?php if ((int)$log['state'] === 0): ?>
                                <span class="hrm-badge is-success">
                                    <svg viewBox="0 0 12 12" fill="currentColor" width="8"><circle cx="6" cy="6" r="6"/></svg>
                                    Check In
                                </span>
                            <?php else: ?>
                                <span class="hrm-badge is-danger">
                                    <svg viewBox="0 0 12 12" fill="currentColor" width="8"><circle cx="6" cy="6" r="6"/></svg>
                                    Check Out
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><span class="hrm-badge is-neutral"><?php echo hrm_e(ucfirst($log['source'])); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Salary Preview DataTable -->
    <div class="hrm-panel">
        <div class="hrm-panel-head">
            <div class="hrm-panel-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hrm-panel-icon">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
                This Month Salary Preview
            </div>
            <span class="hrm-panel-badge"><?php echo date('M Y'); ?></span>
        </div>
        <div class="hrm-panel-body">
            <?php if (!$employees): ?>
                <div class="hrm-empty-state">No employee records available yet.</div>
            <?php else: ?>
            <table id="salaryPreviewTable" class="hrm-dt-table display nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Hours</th>
                        <th>Net Salary</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($employees as $row):
$preview = hrm_salary_preview((int)$row['user_id'], (int)date('m'), (int)date('Y'));
if (!$preview) { continue; }
$name = $preview['employee']['full_name'] ?: $preview['employee']['user_name'];
                ?>
                    <tr>
                        <td>
                            <div class="hrm-cell-person">
                                <div class="hrm-cell-avatar"><?php echo hrm_e(hrm_initials($name)); ?></div>
                                <span><?php echo hrm_e($name); ?></span>
                            </div>
                        </td>
                        <td><span class="hrm-badge is-accent"><?php echo hrm_e(ucfirst($preview['salary_type'])); ?></span></td>
                        <td data-order="<?php echo (float)$preview['hours']; ?>">
                            <?php echo hrm_e(number_format($preview['hours'], 2)); ?> hrs
                        </td>
                        <td data-order="<?php echo (float)$preview['net_salary']; ?>">
                            <span class="hrm-salary-currency"><?php echo hrm_e($preview['currency']); ?></span>
                            <strong><?php echo hrm_e(number_format($preview['net_salary'], 2)); ?></strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /dash-grid -->

<?php

/* ── EMPLOYEE VIEW ─────────────────────────────────── */
else:
$attendanceStatus = hrm_today_attendance_status($userId);
$salaryPreview    = hrm_salary_preview($userId, (int)date('m'), (int)date('Y'));
$recentLogs       = array_slice(hrm_fetch_attendance_logs($userId, date('Y-m-01'), date('Y-m-d')), 0, 8);
?>

<!-- ── EMPLOYEE PROFILE STRIP ── -->
<div class="hrm-emp-strip">

    <div class="hrm-emp-profile">
        <div class="hrm-emp-avatar">
            <?php echo hrm_e(hrm_initials($employee['full_name'] ?: $employee['user_name'] ?: 'U')); ?>
        </div>
        <div>
            <div class="hrm-emp-name"><?php echo hrm_e($employee['full_name'] ?: $employee['user_name'] ?: ($_SESSION['fullname'] ?? 'Employee')); ?></div>
            <div class="hrm-emp-meta">
                <?php echo hrm_e($employee['designation_name'] ?: $employee['user_desig'] ?: 'Employee'); ?>
                <?php if ($employee['department_name']): ?>
                    &nbsp;&bull;&nbsp;<?php echo hrm_e($employee['department_name']); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="hrm-emp-stats">
        <div class="hrm-emp-stat">
            <span class="hrm-emp-stat-label">Today's Arrival</span>
            <span class="hrm-emp-stat-value hrm-color-green">
                <?php echo hrm_e($attendanceStatus['arrival'] ? date('h:i A', strtotime($attendanceStatus['arrival'])) : '--'); ?>
            </span>
        </div>
        <div class="hrm-emp-stat-divider"></div>
        <div class="hrm-emp-stat">
            <span class="hrm-emp-stat-label">Today's Departure</span>
            <span class="hrm-emp-stat-value hrm-color-amber">
                <?php echo hrm_e($attendanceStatus['departure'] ? date('h:i A', strtotime($attendanceStatus['departure'])) : '--'); ?>
            </span>
        </div>
        <div class="hrm-emp-stat-divider"></div>
        <div class="hrm-emp-stat">
            <span class="hrm-emp-stat-label">Estimated Salary</span>
            <span class="hrm-emp-stat-value hrm-color-blue">
                <?php echo $salaryPreview
? hrm_e(($salaryPreview['currency'] ?: 'PKR') . ' ' . number_format($salaryPreview['net_salary'], 2))
                    : 'PKR 0.00'; ?>
            </span>
        </div>
    </div>

</div>

<!-- ── EMPLOYEE ATTENDANCE TABLE ── -->
<div class="hrm-panel">
    <div class="hrm-panel-head">
        <div class="hrm-panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hrm-panel-icon">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            My Attendance This Month
        </div>
        <span class="hrm-panel-badge"><?php echo date('M Y'); ?></span>
    </div>
    <div class="hrm-panel-body">
        <table id="empAttendanceTable" class="hrm-dt-table display nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>Action</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$recentLogs): ?>
                <tr><td colspan="3" class="text-center text-muted py-3">No attendance entries recorded yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td><?php echo hrm_e(date('d M Y  h:i A', strtotime($log['log_time']))); ?></td>
                    <td>
                        <?php if ((int)$log['state'] === 0): ?>
                            <span class="hrm-badge is-success">
                                <svg viewBox="0 0 12 12" fill="currentColor" width="8"><circle cx="6" cy="6" r="6"/></svg>
                                Check In
                            </span>
                        <?php else: ?>
                            <span class="hrm-badge is-danger">
                                <svg viewBox="0 0 12 12" fill="currentColor" width="8"><circle cx="6" cy="6" r="6"/></svg>
                                Check Out
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><span class="hrm-badge is-neutral"><?php echo hrm_e(ucfirst($log['source'])); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     DASHBOARD-SCOPED STYLES
     ══════════════════════════════════════════════════════════ -->
<style>
/* ── Metric tiles ── */
.hrm-metrics-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 1199px) { .hrm-metrics-strip { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 575px)  { .hrm-metrics-strip { grid-template-columns: 1fr; } }

.hrm-metric-tile {
    position: relative;
    overflow: hidden;
    border-radius: 14px;
    padding: 20px 20px 18px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    transition: transform .18s ease, box-shadow .18s ease;
}
.hrm-metric-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,.10); }
.hrm-tile--blue   { background: linear-gradient(135deg, #1456b8 0%, #1e75f0 100%); color: #fff; }
.hrm-tile--green  { background: linear-gradient(135deg, #15803d 0%, #22c55e 100%); color: #fff; }
.hrm-tile--amber  { background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%); color: #fff; }
.hrm-tile--violet { background: linear-gradient(135deg, #6d28d9 0%, #a855f7 100%); color: #fff; }

.hrm-tile-icon {
    flex-shrink: 0;
    width: 44px; height: 44px;
    border-radius: 10px;
    background: rgba(255,255,255,.18);
    display: flex; align-items: center; justify-content: center;
}
.hrm-tile-icon svg { width: 22px; height: 22px; stroke: #fff; }
.hrm-tile-body { display: flex; flex-direction: column; gap: 4px; }
.hrm-tile-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; opacity: .85; }
.hrm-tile-value { font-size: 32px; font-weight: 800; line-height: 1; }
.hrm-tile-shine {
    position: absolute;
    top: -30px; right: -30px;
    width: 100px; height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,.10);
    pointer-events: none;
}

/* ── Main grid ── */
.hrm-dash-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 20px;
}
@media (max-width: 991px) { .hrm-dash-grid { grid-template-columns: 1fr; } }

/* ── Panels ── */
.hrm-panel {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 16px rgba(15,23,42,.05);
}
 

.hrm-panel-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 20px;
    background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    border-bottom: 1px solid var(--hrm-border);
}
.hrm-panel-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 14px; font-weight: 700; color: var(--hrm-text);
}
.hrm-panel-icon { width: 18px; height: 18px; stroke: var(--hrm-primary); flex-shrink: 0; }
.hrm-panel-badge {
    font-size: 11px; font-weight: 700;
    padding: 4px 10px; border-radius: 999px;
    background: var(--hrm-primary-soft); color: var(--hrm-primary);
}
.hrm-panel-body { padding: 16px 20px; }
.hrm-panel-body--flush { padding: 0; }

/* ── DataTable wrapper ── */
.hrm-dt-table { font-size: 13px; }
.hrm-dt-table thead th {
    background: #f4f7fb !important;
    color: var(--hrm-muted) !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .06em !important;
    border-bottom: 2px solid var(--hrm-border) !important;
    white-space: nowrap;
}
.hrm-dt-table tbody tr { transition: background .12s; }
.hrm-dt-table tbody tr:hover td { background: #f0f6ff !important; }
.hrm-dt-table td { vertical-align: middle !important; padding: 9px 12px !important; border-bottom: 1px solid #f1f5f9 !important; }

/* ── Cell person ── */
.hrm-cell-person { display: flex; align-items: center; gap: 9px; }
.hrm-cell-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--hrm-primary-soft); color: var(--hrm-primary);
    font-size: 11px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.hrm-cell-avatar--sm { width: 36px; height: 36px; font-size: 12px; }

/* ── Salary rows ── */
.hrm-salary-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 20px;
    border-bottom: 1px solid #f1f5f9;
    gap: 12px;
    transition: background .12s;
}
.hrm-salary-row:last-child { border-bottom: none; }
.hrm-salary-row:hover { background: #f8fbff; }
.hrm-salary-name { font-size: 13px; font-weight: 700; color: var(--hrm-text); }
.hrm-salary-meta { font-size: 11px; color: var(--hrm-muted); margin-top: 2px; }
.hrm-salary-amount { text-align: right; white-space: nowrap; }
.hrm-salary-currency { font-size: 11px; color: var(--hrm-muted); margin-right: 3px; }
.hrm-salary-figure { font-size: 15px; font-weight: 800; color: var(--hrm-text); }
.hrm-empty-state { padding: 28px; text-align: center; color: var(--hrm-muted); font-size: 13px; }

/* ── Employee profile strip ── */
.hrm-emp-strip {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 20px;
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 14px;
    padding: 20px 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 16px rgba(15,23,42,.05);
}
.hrm-emp-profile { display: flex; align-items: center; gap: 16px; }
.hrm-emp-avatar {
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, var(--hrm-primary-soft), #dce8ff);
    color: var(--hrm-primary); font-size: 18px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px var(--hrm-primary);
}
.hrm-emp-name { font-size: 17px; font-weight: 800; color: var(--hrm-text); }
.hrm-emp-meta { font-size: 12px; color: var(--hrm-muted); margin-top: 3px; }

.hrm-emp-stats { display: flex; align-items: center; gap: 0; }
.hrm-emp-stat { text-align: center; padding: 0 28px; }
.hrm-emp-stat-divider { width: 1px; height: 40px; background: var(--hrm-border); }
.hrm-emp-stat-label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--hrm-muted); margin-bottom: 5px; }
.hrm-emp-stat-value { display: block; font-size: 18px; font-weight: 800; }
.hrm-color-green { color: var(--hrm-success); }
.hrm-color-amber { color: var(--hrm-warning); }
.hrm-color-blue  { color: var(--hrm-primary); }

@media (max-width: 767px) {
    .hrm-emp-strip { flex-direction: column; align-items: flex-start; }
    .hrm-emp-stats { flex-wrap: wrap; gap: 16px; }
    .hrm-emp-stat-divider { display: none; }
    .hrm-emp-stat { padding: 0 12px 0 0; text-align: left; }
}

/* ── DataTables overrides ── */
.hrm-dt-controls { margin-bottom: 12px; }

div.dataTables_wrapper div.dataTables_length select,
div.dataTables_wrapper div.dataTables_filter input {
    border: 1px solid var(--hrm-border) !important;
    border-radius: 8px !important;
    padding: 6px 10px !important;
    font-size: 13px !important;
    outline: none !important;
    box-shadow: none !important;
    background: #fff !important;
    color: var(--hrm-text) !important;
    min-width: 60px;
}
div.dataTables_wrapper div.dataTables_filter input { min-width: 200px; }
div.dataTables_wrapper div.dataTables_filter input:focus {
    border-color: var(--hrm-primary) !important;
    box-shadow: 0 0 0 3px rgba(20,86,184,.10) !important;
}
div.dataTables_wrapper div.dataTables_info,
div.dataTables_wrapper div.dataTables_length label,
div.dataTables_wrapper div.dataTables_filter label {
    font-size: 12px !important;
    color: var(--hrm-muted) !important;
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    margin: 0 !important;
}
div.dataTables_wrapper div.dataTables_info { padding-top: 8px !important; }

/* Pagination (Bootstrap5 DataTables) */
div.dataTables_wrapper .pagination { gap: 4px; margin: 8px 0 0; }
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
div.dataTables_wrapper .pagination .page-item.disabled .page-link { opacity: .4 !important; }
</style>

<!-- ── DataTables: CDN assets (load them here so they're available before init) ── -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<!-- ── DataTables init ── -->
<script>
(function hrmInitDT() {
    /* Run as soon as jQuery + DataTables are both ready.
       Works whether scripts.php already loaded jQuery or not. */
    function init() {
        if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
            return setTimeout(init, 50);
        }

        var dtDefaults = {
            pageLength: 10,
            responsive: true,
            language: {
                search:          '',
                searchPlaceholder: 'Search…',
                lengthMenu:      'Show _MENU_ rows',
                info:            'Showing _START_–_END_ of _TOTAL_ entries',
                infoEmpty:       'No entries found',
                infoFiltered:    '(filtered from _MAX_)',
                emptyTable:      'No data available',
                paginate:        { previous: '‹', next: '›' }
            },
            dom: '<"hrm-dt-controls d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"lf>rtip',
            drawCallback: function () {
                /* Re-apply badge styles after every redraw */
                $(this.api().table().container()).find('.hrm-badge').each(function () {
                    $(this).css('display', 'inline-flex');
                });
            }
        };

        <?php if ($isAdmin): ?>
        var $adminTbl = $('#adminAttendanceTable');
        if ($adminTbl.length && !$.fn.DataTable.isDataTable($adminTbl)) {
            $adminTbl.DataTable($.extend(true, {}, dtDefaults, {
                order: [[1, 'desc']],
                columnDefs: [
                    { targets: 0, width: '26%' },
                    { targets: 1, width: '28%' },
                    { targets: [2, 3], width: '14%' }
                ]
            }));
        }

        var $salaryTbl = $('#salaryPreviewTable');
        if ($salaryTbl.length && !$.fn.DataTable.isDataTable($salaryTbl)) {
            $salaryTbl.DataTable($.extend(true, {}, dtDefaults, {
                pageLength: 10,
                order: [[3, 'desc']], // sort by net salary descending
                columnDefs: [
                    { targets: 0, width: '40%' },
                    { targets: 1, width: '16%' },
                    { targets: 2, width: '18%' },
                    { targets: 3, width: '26%' }
                ]
            }));
        }
        <?php else: ?>
        var $empTbl = $('#empAttendanceTable');
        if ($empTbl.length && !$.fn.DataTable.isDataTable($empTbl)) {
            $empTbl.DataTable($.extend(true, {}, dtDefaults, {
                order: [[0, 'desc']]
            }));
        }
        <?php endif; ?>
    }

    /* Kick off immediately — the CDN scripts just above are synchronous,
       so by the time this inline script runs they are already evaluated. */
    init();
})();
</script>

<?php
new_hrm_render_page_end();