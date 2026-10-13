<?php
require_once __DIR__ . '/bootstrap.php';

$isAdmin      = hrm_is_admin();
$targetUserId = $isAdmin && isset($_GET['user_id']) ? (int) $_GET['user_id'] : hrm_session_user_id();
if ($targetUserId <= 0) { $targetUserId = hrm_session_user_id(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recordUserId = $isAdmin ? (int) ($_POST['user_id'] ?? 0) : hrm_session_user_id();
    $mode         = ($_POST['mode'] ?? 'in') === 'out' ? 'out' : 'in';
    $result       = hrm_record_attendance($recordUserId, $mode);
    hrm_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Attendance recorded.' : $result['message']);
    header('Location: attendance.php' . ($isAdmin ? '?user_id=' . $recordUserId : ''));
    exit();
}

$employees        = $isAdmin ? hrm_fetch_employees(false) : [];
$selectedEmployee = hrm_fetch_employee($targetUserId);
$status           = hrm_today_attendance_status($targetUserId);
$logs             = hrm_fetch_attendance_logs($targetUserId, date('Y-m-d'), date('Y-m-d'));

new_hrm_render_page_start(
    $isAdmin ? 'Attendance' : 'My Attendance',
    $isAdmin ? 'Attendance' : 'My Attendance',
    $isAdmin
    ? 'Manually record check-in and check-out for any employee.'
    : 'Record your arrival and departure times for today.'
);
?>

<!-- ── CDN: DataTables ── -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<style>
/* ── Layout ── */
.att-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 20px;
    align-items: start;
}
@media (max-width: 991px) { .att-layout { grid-template-columns: 1fr; } }

/* ── Clock Panel ── */
.att-clock-panel {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(15,23,42,.07);
    position: sticky;
    top: 20px;
}

/* Live clock header */
.att-clock-hero {
    background: linear-gradient(135deg, #0f3d8c 0%, #1456b8 50%, #1e75f0 100%);
    padding: 24px 22px 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.att-clock-hero::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 130px; height: 130px;
    border-radius: 50%;
    background: rgba(255,255,255,.06);
    pointer-events: none;
}
.att-clock-hero::after {
    content: '';
    position: absolute;
    bottom: -30px; left: -30px;
    width: 100px; height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,.05);
    pointer-events: none;
}
.att-clock-date {
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .10em;
    color: rgba(255,255,255,.65);
    margin-bottom: 6px;
}
.att-clock-time {
    font-size: 40px; font-weight: 800;
    color: #fff; line-height: 1;
    letter-spacing: -.02em;
    font-variant-numeric: tabular-nums;
}
.att-clock-seconds {
    font-size: 22px; font-weight: 600;
    color: rgba(255,255,255,.6);
}

/* Employee selector */
.att-panel-body { padding: 20px 20px 22px; }

.att-select-wrap { margin-bottom: 18px; }
.att-select-label {
    font-size: 10px; font-weight: 800;
    text-transform: uppercase; letter-spacing: .08em;
    color: var(--hrm-muted); margin-bottom: 6px;
    display: block;
}
.att-select-wrap select {
    width: 100%;
    border: 1.5px solid var(--hrm-border);
    border-radius: 8px;
    padding: 9px 12px;
    font-size: 13px;
    color: var(--hrm-text);
    background: #fafbfd;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
    cursor: pointer;
}
.att-select-wrap select:focus {
    border-color: var(--hrm-primary);
    box-shadow: 0 0 0 3px rgba(20,86,184,.10);
}

/* Stat rows */
.att-stat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 20px;
}
.att-stat-tile {
    border: 1px solid var(--hrm-border);
    border-radius: 10px;
    padding: 12px 14px;
    background: #fafbfd;
}
.att-stat-label {
    font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .07em;
    color: var(--hrm-muted); margin-bottom: 5px;
}
.att-stat-value {
    font-size: 16px; font-weight: 800;
    color: var(--hrm-text);
}
.att-stat-value.is-green { color: var(--hrm-success); }
.att-stat-value.is-amber { color: var(--hrm-warning); }
.att-stat-value.is-muted { color: var(--hrm-muted); font-weight: 600; font-size: 14px; }

/* Employee info */
.att-emp-info {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px;
    border: 1px solid var(--hrm-border);
    border-radius: 10px;
    background: #fafbfd;
    margin-bottom: 18px;
}
.att-emp-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: var(--hrm-primary-soft); color: var(--hrm-primary);
    font-size: 13px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.att-emp-name { font-size: 13px; font-weight: 700; color: var(--hrm-text); }
.att-emp-sub  { font-size: 11px; color: var(--hrm-muted); margin-top: 2px; }

/* Check-in / out button */
.att-action-btn {
    width: 100%;
    padding: 13px 16px;
    border-radius: 10px;
    border: none;
    font-size: 14px; font-weight: 800;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: opacity .15s, transform .15s, box-shadow .15s;
    letter-spacing: .01em;
}
.att-action-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,0,0,.14); }
.att-action-btn:active { transform: translateY(0); }
.att-action-btn.is-checkin {
    background: linear-gradient(135deg, #15803d, #22c55e);
    color: #fff;
    box-shadow: 0 3px 12px rgba(21,128,61,.25);
}
.att-action-btn.is-checkout {
    background: linear-gradient(135deg, #b42318, #ef4444);
    color: #fff;
    box-shadow: 0 3px 12px rgba(180,35,24,.25);
}
.att-action-btn svg { width: 18px; height: 18px; stroke: currentColor; }

/* Status pill */
.att-already-done {
    text-align: center;
    padding: 12px 16px;
    border-radius: 10px;
    background: #e8f7ee;
    color: var(--hrm-success);
    font-size: 13px; font-weight: 700;
    border: 1px solid #bbf0cc;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}

/* ── Entries Panel ── */
.att-entries-panel {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(15,23,42,.07);
}
.att-entries-header {
    padding: 16px 22px;
    background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    border-bottom: 1px solid var(--hrm-border);
    display: flex; align-items: center; justify-content: space-between;
}
.att-entries-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 14px; font-weight: 700; color: var(--hrm-text);
}
.att-entries-title svg { width: 18px; height: 18px; stroke: var(--hrm-primary); }
.att-entries-badge {
    font-size: 11px; font-weight: 700;
    padding: 4px 10px; border-radius: 999px;
    background: var(--hrm-primary-soft); color: var(--hrm-primary);
}
.att-entries-body { padding: 18px 20px; }

/* DataTable */
.att-dt-table thead th {
    background: #f4f7fb !important;
    color: var(--hrm-muted) !important;
    font-size: 11px !important; font-weight: 700 !important;
    text-transform: uppercase !important; letter-spacing: .06em !important;
    border-bottom: 2px solid var(--hrm-border) !important;
    padding: 10px 12px !important; white-space: nowrap;
}
.att-dt-table td {
    vertical-align: middle !important;
    padding: 10px 12px !important;
    border-bottom: 1px solid #f1f5f9 !important;
    font-size: 13px;
}
.att-dt-table tbody tr:last-child td { border-bottom: none !important; }
.att-dt-table tbody tr { transition: background .12s; }
.att-dt-table tbody tr:hover td { background: #f5f9ff !important; }

/* Badges */
.att-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 700;
}
.att-badge.is-in     { background: #e8f7ee; color: #15803d; }
.att-badge.is-out    { background: #feeceb; color: #b42318; }
.att-badge.is-source { background: #eef2f6; color: #475569; }
.att-badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

/* DT controls */
div.dataTables_wrapper div.dataTables_length select,
div.dataTables_wrapper div.dataTables_filter input {
    border: 1.5px solid var(--hrm-border) !important;
    border-radius: 8px !important; padding: 6px 10px !important;
    font-size: 12px !important; background: #fff !important;
    outline: none !important; box-shadow: none !important;
}
div.dataTables_wrapper div.dataTables_filter input:focus {
    border-color: var(--hrm-primary) !important;
    box-shadow: 0 0 0 3px rgba(20,86,184,.10) !important;
}
div.dataTables_wrapper div.dataTables_length label,
div.dataTables_wrapper div.dataTables_filter label {
    display: flex !important; align-items: center !important; gap: 6px !important;
    font-size: 12px !important; color: var(--hrm-muted) !important; margin: 0 !important;
}
div.dataTables_wrapper div.dataTables_info {
    font-size: 12px !important; color: var(--hrm-muted) !important; padding-top: 10px !important;
}
div.dataTables_wrapper .pagination { gap: 3px; margin: 8px 0 0; }
div.dataTables_wrapper .pagination .page-item .page-link {
    border-radius: 6px !important; font-size: 12px !important;
    padding: 5px 10px !important; border: 1px solid var(--hrm-border) !important;
    color: var(--hrm-text) !important; background: #fff !important; line-height: 1.4 !important;
}
div.dataTables_wrapper .pagination .page-item .page-link:hover {
    background: var(--hrm-primary-soft) !important; color: var(--hrm-primary) !important;
}
div.dataTables_wrapper .pagination .page-item.active .page-link {
    background: var(--hrm-primary) !important; border-color: var(--hrm-primary) !important; color: #fff !important;
}
div.dataTables_wrapper .pagination .page-item.disabled .page-link { opacity: .4 !important; }
</style>

<div class="att-layout">

    <!-- ── LEFT: Clock + Action Panel ── -->
    <div class="att-clock-panel">

        <!-- Live clock -->
        <div class="att-clock-hero">
            <div class="att-clock-date" id="attDate"><?php echo date('l, d F Y'); ?></div>
            <div class="att-clock-time">
                <span id="attHH">--</span>:<span id="attMM">--</span><span class="att-clock-seconds">:<span id="attSS">--</span></span>
            </div>
        </div>

        <div class="att-panel-body">

            <!-- Employee selector (admin only) -->
            <?php if ($isAdmin): ?>
            <div class="att-select-wrap">
                <label class="att-select-label">Select Employee</label>
                <form method="get" action="new_hrm/attendance.php" id="empSwitchForm">
                    <select name="user_id" onchange="document.getElementById('empSwitchForm').submit()">
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo (int)$emp['user_id']; ?>"
                                <?php echo (int)$emp['user_id'] === $targetUserId ? 'selected' : ''; ?>>
                                <?php echo hrm_e($emp['full_name'] ?: $emp['user_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <?php endif; ?>

            <!-- Employee info chip -->
            <div class="att-emp-info">
                <div class="att-emp-avatar">
                    <?php echo hrm_e(hrm_initials($selectedEmployee['full_name'] ?: $selectedEmployee['user_name'] ?: 'U')); ?>
                </div>
                <div>
                    <div class="att-emp-name">
                        <?php echo hrm_e($selectedEmployee['full_name'] ?: $selectedEmployee['user_name'] ?: ($_SESSION['fullname'] ?? 'Employee')); ?>
                    </div>
                    <div class="att-emp-sub">
                        <?php echo hrm_e($selectedEmployee['designation_name'] ?: $selectedEmployee['user_desig'] ?: 'Employee'); ?>
                    </div>
                </div>
            </div>

            <!-- Today's stats -->
            <div class="att-stat-grid">
                <div class="att-stat-tile">
                    <div class="att-stat-label">Arrival</div>
                    <div class="att-stat-value is-green">
                        <?php echo $status['arrival'] ? hrm_e(date('h:i A', strtotime($status['arrival']))) : '<span class="att-stat-value is-muted">--</span>'; ?>
                    </div>
                </div>
                <div class="att-stat-tile">
                    <div class="att-stat-label">Departure</div>
                    <div class="att-stat-value is-amber">
                        <?php echo $status['departure'] ? hrm_e(date('h:i A', strtotime($status['departure']))) : '<span class="att-stat-value is-muted">--</span>'; ?>
                    </div>
                </div>
            </div>

            <!-- Action button -->
            <form method="post" action="new_hrm/attendance.php<?php echo $isAdmin ? '?user_id=' . $targetUserId : ''; ?>">
                <?php if ($isAdmin): ?>
                    <input type="hidden" name="user_id" value="<?php echo $targetUserId; ?>">
                <?php endif; ?>

                <?php if ($status['can_check_in']): ?>
                    <input type="hidden" name="mode" value="in">
                    <button type="submit" class="att-action-btn is-checkin">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                            <polyline points="10 17 15 12 10 7"/>
                            <line x1="15" y1="12" x2="3" y2="12"/>
                        </svg>
                        Check In
                    </button>
                <?php else: ?>
                    <input type="hidden" name="mode" value="out">
                    <button type="submit" class="att-action-btn is-checkout">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Check Out
                    </button>
                <?php endif; ?>
            </form>

        </div>
    </div>

    <!-- ── RIGHT: Today's Entries DataTable ── -->
    <div class="att-entries-panel">
        <div class="att-entries-header">
            <div class="att-entries-title">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                Today's Entries
            </div>
            <span class="att-entries-badge"><?php echo date('d M Y'); ?></span>
        </div>
        <div class="att-entries-body">
            <table id="attendanceLogsTable" class="att-dt-table display nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Action</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo hrm_e(date('d M Y  h:i A', strtotime($log['log_time']))); ?></td>
                        <td>
                            <?php if ((int)$log['state'] === 0): ?>
                                <span class="att-badge is-in">
                                    <span class="att-badge-dot"></span>
                                    Check In
                                </span>
                            <?php else: ?>
                                <span class="att-badge is-out">
                                    <span class="att-badge-dot"></span>
                                    Check Out
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="att-badge is-source">
                                <?php echo hrm_e(ucfirst($log['source'])); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /att-layout -->

<!-- Live clock script -->
<script>
(function () {
    function pad(n) { return String(n).padStart(2, '0'); }
    function tick() {
        var now = new Date();
        document.getElementById('attHH').textContent = pad(now.getHours() % 12 || 12);
        document.getElementById('attMM').textContent = pad(now.getMinutes());
        document.getElementById('attSS').textContent = pad(now.getSeconds());
    }
    tick();
    setInterval(tick, 1000);
})();

/* DataTable init */
(function () {
    function init() {
        if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
            return setTimeout(init, 50);
        }
        var $tbl = $('#attendanceLogsTable');
        if ($tbl.length && !$.fn.DataTable.isDataTable($tbl)) {
            $tbl.DataTable({
                pageLength: 10,
                responsive: true,
                order: [[0, 'desc']],
                language: {
                    search: '',
                    searchPlaceholder: 'Search entries…',
                    lengthMenu: 'Show _MENU_ rows',
                    info: 'Showing _START_–_END_ of _TOTAL_',
                    infoEmpty: 'No entries found',
                    emptyTable: 'No attendance recorded today',
                    paginate: { previous: '‹', next: '›' }
                },
                dom: '<"d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"lf>rtip',
                columnDefs: [
                    { targets: 0, width: '44%' },
                    { targets: 1, width: '28%' },
                    { targets: 2, width: '28%' }
                ]
            });
        }
    }
    init();
})();
</script>

<?php
new_hrm_render_page_end();
?>