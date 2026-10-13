<?php
require_once __DIR__ . '/bootstrap.php';

$isAdmin   = hrm_is_admin();
$userId    = hrm_session_user_id();
$employees = $isAdmin ? hrm_fetch_employees(false) : [];

new_hrm_render_page_start(
    $isAdmin ? 'Manage Breaks' : 'My Breaks',
    $isAdmin ? 'Manage Breaks' : 'My Breaks',
    $isAdmin
        ? 'Track and review employee break activity in real time.'
        : 'Start and end your breaks; review your break history.'
);
?>

<!-- ── DataTables CDN ── -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<!-- ── METRIC STRIP ── -->
<div class="hrm-metrics-strip">
    <div class="hrm-metric-tile hrm-tile--blue">
        <div class="hrm-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8h1a4 4 0 0 1 0 8h-1"/>
                <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/>
                <line x1="6" y1="1" x2="6" y2="4"/>
                <line x1="10" y1="1" x2="10" y2="4"/>
                <line x1="14" y1="1" x2="14" y2="4"/>
            </svg>
        </div>
        <div class="hrm-tile-body">
            <span class="hrm-tile-label">Today's Breaks</span>
            <span class="hrm-tile-value" id="bkKpiToday">0</span>
        </div>
        <div class="hrm-tile-shine"></div>
    </div>

    <div class="hrm-metric-tile hrm-tile--amber">
        <div class="hrm-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="hrm-tile-body">
            <span class="hrm-tile-label">On Break Now</span>
            <span class="hrm-tile-value" id="bkKpiOpen">0</span>
        </div>
        <div class="hrm-tile-shine"></div>
    </div>

    <div class="hrm-metric-tile hrm-tile--green">
        <div class="hrm-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
        </div>
        <div class="hrm-tile-body">
            <span class="hrm-tile-label">Avg Duration (mo)</span>
            <span class="hrm-tile-value" id="bkKpiAvg">0<small style="font-size:14px;font-weight:600;opacity:.8;margin-left:4px;">m</small></span>
        </div>
        <div class="hrm-tile-shine"></div>
    </div>

    <div class="hrm-metric-tile hrm-tile--violet">
        <div class="hrm-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
        <div class="hrm-tile-body">
            <span class="hrm-tile-label">Total Minutes (mo)</span>
            <span class="hrm-tile-value" id="bkKpiMin">0</span>
        </div>
        <div class="hrm-tile-shine"></div>
    </div>
</div>

<!-- ── ACTIVE-BREAK / START PANEL ── -->
<div class="hrm-panel bk-action-panel">
    <div class="hrm-panel-head">
        <div class="hrm-panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hrm-panel-icon">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            <span id="bkActionTitle">Break Controls</span>
        </div>
        <span class="hrm-panel-badge" id="bkStatusBadge">Idle</span>
    </div>
    <div class="hrm-panel-body">
        <div id="bkActiveBox" class="bk-active-box d-none">
            <div class="bk-active-info">
                <div class="bk-active-label">Currently on break since</div>
                <div class="bk-active-since" id="bkActiveSince">—</div>
                <div class="bk-active-name text-muted small" id="bkActiveName" style="<?php echo $isAdmin ? '' : 'display:none;'; ?>"></div>
            </div>
            <div class="bk-timer-wrap">
                <div class="bk-timer" id="bkTimer">00:00:00</div>
                <button class="btn btn-danger bk-end-btn" id="bkEndBtn">
                    <i class="ti ti-player-stop-filled"></i> End Break
                </button>
            </div>
        </div>

        <div id="bkIdleBox" class="bk-idle-box">
            <div class="row g-3 align-items-end">
                <?php if ($isAdmin): ?>
                <div class="col-md-5">
                    <label class="form-label">Start break for</label>
                    <select class="form-select" id="bkStartUser">
                        <option value="<?php echo (int) $userId; ?>">— Me —</option>
                        <?php foreach ($employees as $e): ?>
                            <?php if ((int) $e['user_id'] === (int) $userId) continue; ?>
                            <option value="<?php echo (int) $e['user_id']; ?>">
                                <?php echo hrm_e(($e['full_name'] ?: $e['user_name']) . ($e['hrm_employee_code'] ? ' [' . $e['hrm_employee_code'] . ']' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-<?php echo $isAdmin ? 3 : 4; ?>">
                    <label class="form-label">Break Type</label>
                    <select class="form-select" id="bkStartType">
                        <option value="meal">Meal Break</option>
                        <option value="short" selected>Short Break</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-<?php echo $isAdmin ? 2 : 5; ?>">
                    <label class="form-label">Reason (optional)</label>
                    <input type="text" class="form-control" id="bkStartReason" placeholder="e.g. Lunch, Coffee">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-success w-100 bk-start-btn" id="bkStartBtn">
                        <i class="ti ti-player-play-filled"></i> Start Break
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── HISTORY TABLE ── -->
<div class="hrm-panel">
    <div class="hrm-panel-head">
        <div class="hrm-panel-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hrm-panel-icon">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <?php echo $isAdmin ? 'All Break Records' : 'My Break History'; ?>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <input type="date" class="form-control form-control-sm bk-filter-date" id="bkFilterFrom" title="From">
            <input type="date" class="form-control form-control-sm bk-filter-date" id="bkFilterTo" title="To">
            <select class="form-select form-select-sm bk-filter-sel" id="bkFilterStatus">
                <option value="">All</option>
                <option value="open">Open</option>
                <option value="closed">Closed</option>
            </select>
            <?php if ($isAdmin): ?>
            <select class="form-select form-select-sm bk-filter-sel" id="bkFilterUser">
                <option value="">All employees</option>
                <?php foreach ($employees as $e): ?>
                    <option value="<?php echo (int) $e['user_id']; ?>">
                        <?php echo hrm_e($e['full_name'] ?: $e['user_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button class="btn btn-sm btn-light" id="bkFilterReset" title="Reset filters">
                <i class="ti ti-refresh"></i>
            </button>
            <?php if ($isAdmin): ?>
            <button class="btn btn-sm btn-primary" id="bkManualAddBtn" title="Add a break record manually">
                <i class="ti ti-plus"></i> Manual Add
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="hrm-panel-body">
        <table id="bkTable" class="hrm-dt-table display nowrap" style="width:100%">
            <thead>
                <tr>
                    <?php if ($isAdmin): ?><th>Employee</th><?php endif; ?>
                    <th>Date</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Duration</th>
                    <th>Type</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- ═══ MANUAL ADD MODAL ═══ -->
<div class="modal fade" id="bkManualModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Break Manually</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Useful for recording a break that was forgotten. Leave <strong>End time</strong> empty to create an open (in-progress) break.
                </p>
                <form id="bkManualForm">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" name="user_id" id="bkManualUser">
                                <option value="">— Select Employee —</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?php echo (int) $e['user_id']; ?>">
                                        <?php echo hrm_e(($e['full_name'] ?: $e['user_name']) . ($e['hrm_employee_code'] ? ' [' . $e['hrm_employee_code'] . ']' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="break_date" id="bkManualDate" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="start_time" id="bkManualStart">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End time <span class="text-muted small">(optional)</span></label>
                            <input type="time" class="form-control" name="end_time" id="bkManualEnd">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Break Type</label>
                            <select class="form-select" name="break_type" id="bkManualType">
                                <option value="meal">Meal Break</option>
                                <option value="short" selected>Short Break</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reason</label>
                            <input type="text" class="form-control" name="reason" id="bkManualReason" placeholder="e.g. Lunch, Coffee">
                        </div>
                        <div id="bkManualPreview" class="col-12 d-none">
                            <div class="alert alert-info py-2 mb-0 small">
                                Duration: <strong id="bkManualDuration">—</strong>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="bkManualSave">
                    <i class="ti ti-check"></i> Save Break
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* ── KPI tiles + panel chrome (shared look) ── */
.hrm-metrics-strip {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px;
}
@media (max-width: 1199px) { .hrm-metrics-strip { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 575px)  { .hrm-metrics-strip { grid-template-columns: 1fr; } }
.hrm-metric-tile {
    position: relative; overflow: hidden; border-radius: 14px; padding: 20px;
    display: flex; align-items: center; gap: 16px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    transition: transform .18s ease, box-shadow .18s ease;
}
.hrm-metric-tile:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,.10); }
.hrm-tile--blue   { background: linear-gradient(135deg, #1456b8 0%, #1e75f0 100%); color: #fff; }
.hrm-tile--green  { background: linear-gradient(135deg, #15803d 0%, #22c55e 100%); color: #fff; }
.hrm-tile--amber  { background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%); color: #fff; }
.hrm-tile--violet { background: linear-gradient(135deg, #6d28d9 0%, #a855f7 100%); color: #fff; }
.hrm-tile-icon {
    flex-shrink: 0; width: 44px; height: 44px; border-radius: 10px;
    background: rgba(255,255,255,.18);
    display: flex; align-items: center; justify-content: center;
}
.hrm-tile-icon svg { width: 22px; height: 22px; stroke: #fff; }
.hrm-tile-body { display: flex; flex-direction: column; gap: 4px; }
.hrm-tile-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; opacity: .85; }
.hrm-tile-value { font-size: 32px; font-weight: 800; line-height: 1; }
.hrm-tile-shine {
    position: absolute; top: -30px; right: -30px; width: 100px; height: 100px;
    border-radius: 50%; background: rgba(255,255,255,.10); pointer-events: none;
}

.hrm-panel {
    background: #fff; border: 1px solid var(--hrm-border);
    border-radius: 14px; overflow: hidden;
    box-shadow: 0 2px 16px rgba(15,23,42,.05); margin-bottom: 20px;
}
.hrm-panel-head {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 14px 20px;
    background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    border-bottom: 1px solid var(--hrm-border);
}
.hrm-panel-title { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 700; color: var(--hrm-text); }
.hrm-panel-icon { width: 18px; height: 18px; stroke: var(--hrm-primary); flex-shrink: 0; }
.hrm-panel-badge {
    font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px;
    background: var(--hrm-primary-soft); color: var(--hrm-primary);
}
.hrm-panel-badge.is-live {
    background: var(--hrm-warning-soft); color: var(--hrm-warning);
    animation: bkPulse 1.4s infinite;
}
@keyframes bkPulse { 0%,100% { opacity: 1; } 50% { opacity: .55; } }
.hrm-panel-body { padding: 16px 20px; }

/* ── DataTable ── */
.hrm-dt-table { font-size: 13px; }
.hrm-dt-table thead th {
    background: #f4f7fb !important; color: var(--hrm-muted) !important;
    font-size: 11px !important; font-weight: 700 !important;
    text-transform: uppercase !important; letter-spacing: .06em !important;
    border-bottom: 2px solid var(--hrm-border) !important; white-space: nowrap;
}
.hrm-dt-table tbody tr { transition: background .12s; }
.hrm-dt-table tbody tr:hover td { background: #f0f6ff !important; }
.hrm-dt-table td { vertical-align: middle !important; padding: 9px 12px !important; border-bottom: 1px solid #f1f5f9 !important; }
.hrm-cell-person { display: flex; align-items: center; gap: 9px; }
.hrm-cell-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--hrm-primary-soft); color: var(--hrm-primary);
    font-size: 11px; font-weight: 800;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

div.dataTables_wrapper div.dataTables_length select,
div.dataTables_wrapper div.dataTables_filter input {
    border: 1px solid var(--hrm-border) !important; border-radius: 8px !important;
    padding: 6px 10px !important; font-size: 13px !important;
    outline: none !important; box-shadow: none !important;
    background: #fff !important; color: var(--hrm-text) !important; min-width: 60px;
}
div.dataTables_wrapper div.dataTables_filter input { min-width: 200px; }
div.dataTables_wrapper div.dataTables_info,
div.dataTables_wrapper div.dataTables_length label,
div.dataTables_wrapper div.dataTables_filter label {
    font-size: 12px !important; color: var(--hrm-muted) !important;
    display: flex !important; align-items: center !important; gap: 6px !important; margin: 0 !important;
}
div.dataTables_wrapper .pagination { gap: 4px; margin: 8px 0 0; }
div.dataTables_wrapper .pagination .page-item .page-link {
    border-radius: 6px !important; font-size: 12px !important; padding: 5px 10px !important;
    border: 1px solid var(--hrm-border) !important; color: var(--hrm-text) !important;
    background: #fff !important;
}
div.dataTables_wrapper .pagination .page-item.active .page-link {
    background: var(--hrm-primary) !important; border-color: var(--hrm-primary) !important; color: #fff !important;
}

/* ── Break-specific ── */
.bk-action-panel.is-live { box-shadow: 0 6px 28px rgba(245, 158, 11, 0.25); }
.bk-action-panel.is-live .hrm-panel-head { background: linear-gradient(180deg, #fff8ec 0%, #fff 100%); }

.bk-active-box {
    display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap;
    padding: 12px 4px;
}
.bk-active-info { min-width: 220px; }
.bk-active-label { font-size: 12px; color: var(--hrm-muted); font-weight: 600; }
.bk-active-since { font-size: 18px; color: var(--hrm-text); font-weight: 700; margin-top: 2px; }
.bk-active-name { margin-top: 4px; }

.bk-timer-wrap { display: flex; align-items: center; gap: 16px; }
.bk-timer {
    font-family: 'Courier New', monospace;
    font-size: 30px; font-weight: 800; letter-spacing: 2px;
    color: var(--hrm-warning); padding: 8px 18px;
    background: var(--hrm-warning-soft); border-radius: 10px;
    border: 1px solid rgba(180, 83, 9, 0.25);
    min-width: 160px; text-align: center;
}
.bk-end-btn, .bk-start-btn { font-weight: 700; padding: 9px 18px; border-radius: 8px; }

.bk-filter-date, .bk-filter-sel { width: auto; min-width: 130px; font-size: 12px; }
.bk-actions .btn-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; padding: 0; border-radius: 6px;
    border: 1px solid var(--hrm-border); background: #fff; color: var(--hrm-muted);
    margin: 0 2px; transition: all .15s;
}
.bk-actions .btn-icon:hover { transform: translateY(-1px); }
.bk-actions .btn-icon.is-stop:hover   { background: var(--hrm-warning-soft); color: var(--hrm-warning); border-color: var(--hrm-warning); }
.bk-actions .btn-icon.is-delete:hover { background: var(--hrm-danger-soft);  color: var(--hrm-danger);  border-color: var(--hrm-danger); }

@media (max-width: 767px) {
    .bk-active-box { flex-direction: column; align-items: flex-start; }
    .bk-timer { font-size: 24px; min-width: 130px; }
}
</style>

<script>
(function () {
    var isAdmin       = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    var sessionUserId = <?php echo (int) $userId; ?>;
    var apiBreaks     = 'new_hrm/api/breaks.php';

    var liveTimer = null;
    var currentBreak = null;

    function toast(kind, msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: kind, title: msg, showConfirmButton: false, timer: 2200 });
        } else { alert(msg); }
    }
    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
        });
    }
    function formatDuration(totalSeconds) {
        totalSeconds = Math.max(0, totalSeconds | 0);
        var h = Math.floor(totalSeconds / 3600);
        var m = Math.floor((totalSeconds % 3600) / 60);
        var s = totalSeconds % 60;
        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }
    function formatMinutes(min) {
        min = Math.max(0, min | 0);
        if (min < 60) return min + 'm';
        return Math.floor(min / 60) + 'h ' + (min % 60) + 'm';
    }

    function setLiveState(breakRow) {
        currentBreak = breakRow;

        if (breakRow) {
            $('#bkIdleBox').addClass('d-none');
            $('#bkActiveBox').removeClass('d-none');
            $('#bkStatusBadge').addClass('is-live').text('On Break');
            $('.bk-action-panel').addClass('is-live');
            $('#bkActionTitle').text('Break In Progress');
            $('#bkActiveSince').text(
                new Date(breakRow.start_time.replace(' ', 'T')).toLocaleString()
            );
            if (isAdmin) {
                $('#bkActiveName').text(breakRow.display_name || ('User ' + breakRow.user_id));
            }

            if (liveTimer) clearInterval(liveTimer);
            var startMs = new Date(breakRow.start_time.replace(' ', 'T')).getTime();
            function tick() {
                var sec = Math.floor((Date.now() - startMs) / 1000);
                $('#bkTimer').text(formatDuration(sec));
            }
            tick();
            liveTimer = setInterval(tick, 1000);
        } else {
            $('#bkIdleBox').removeClass('d-none');
            $('#bkActiveBox').addClass('d-none');
            $('#bkStatusBadge').removeClass('is-live').text('Idle');
            $('.bk-action-panel').removeClass('is-live');
            $('#bkActionTitle').text('Break Controls');
            if (liveTimer) { clearInterval(liveTimer); liveTimer = null; }
        }
    }

    function refreshCurrent() {
        $.getJSON(apiBreaks + '?action=current', function (res) {
            setLiveState(res && res.data ? res.data : null);
        });
    }

    function refreshKpis() {
        $.getJSON(apiBreaks + '?action=stats', function (res) {
            var d = (res && res.data) || {};
            $('#bkKpiToday').text(d.today_breaks || 0);
            $('#bkKpiOpen').text(d.on_break_now || 0);
            $('#bkKpiAvg').html((d.month_avg || 0) + '<small style="font-size:14px;font-weight:600;opacity:.8;margin-left:4px;">m</small>');
            $('#bkKpiMin').text(d.month_minutes || 0);
        });
    }

    function init() {
        if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
            return setTimeout(init, 50);
        }

        var dtDefaults = {
            pageLength: 10,
            responsive: true,
            language: {
                search: '', searchPlaceholder: 'Search…',
                lengthMenu: 'Show _MENU_ rows',
                info: 'Showing _START_–_END_ of _TOTAL_ entries',
                infoEmpty: 'No entries found',
                emptyTable: 'No break records found',
                paginate: { previous: '‹', next: '›' }
            },
            dom: '<"hrm-dt-controls d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"lf>rtip'
        };

        function tableUrl() {
            var params = $.param({
                action: 'list',
                user_id: isAdmin ? ($('#bkFilterUser').val() || 0) : 0,
                status:  $('#bkFilterStatus').val() || '',
                date_from: $('#bkFilterFrom').val() || '',
                date_to:   $('#bkFilterTo').val() || ''
            });
            return apiBreaks + '?' + params;
        }

        var cols = [];
        if (isAdmin) {
            cols.push({
                data: 'display_name',
                render: function (v, t, r) {
                    return '<div class="hrm-cell-person">'
                        + '<div class="hrm-cell-avatar">' + (v ? v.charAt(0).toUpperCase() : 'U') + '</div>'
                        + '<span>' + escapeHtml(v || ('User ' + r.user_id)) + '</span>'
                        + '</div>';
                }
            });
        }
        cols = cols.concat([
            { data: 'break_date', render: function (v) { return v ? new Date(v).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }) : '—'; } },
            { data: 'start_time_fmt' },
            { data: 'end_time_fmt', render: function (v, t, r) { return r.is_open ? '<span class="hrm-badge is-warning">In progress</span>' : escapeHtml(v); } },
            { data: 'duration_minutes', render: function (v, t, r) {
                if (r.is_open) return '<span class="text-warning fw-bold">' + formatMinutes(r.live_minutes || 0) + ' (live)</span>';
                return v != null ? '<strong>' + formatMinutes(v) + '</strong>' : '<span class="text-muted">—</span>';
            } },
            { data: 'break_type', render: function (v) {
                var t = (v || 'other').toLowerCase();
                var label = t === 'meal' ? 'Meal' : (t === 'short' ? 'Short' : 'Other');
                var cls   = t === 'meal' ? 'is-accent' : (t === 'short' ? 'is-success' : 'is-neutral');
                return '<span class="hrm-badge ' + cls + '">' + label + '</span>';
            } },
            { data: 'reason', render: function (v) {
                if (!v) return '<span class="text-muted">—</span>';
                return '<span title="' + escapeHtml(v) + '" style="max-width:160px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle;">' + escapeHtml(v) + '</span>';
            } },
            { data: 'is_open', render: function (v) {
                return parseInt(v, 10) === 1
                    ? '<span class="hrm-badge is-warning">Open</span>'
                    : '<span class="hrm-badge is-success">Closed</span>';
            } },
            { data: null, orderable: false, render: function (d, t, r) {
                var html = '<div class="bk-actions">';
                if (parseInt(r.is_open, 10) === 1 && (isAdmin || parseInt(r.user_id, 10) === sessionUserId)) {
                    html += '<button class="btn-icon is-stop" data-act="end" data-id="' + r.id + '" title="End break"><i class="ti ti-player-stop-filled"></i></button>';
                }
                if (isAdmin || parseInt(r.user_id, 10) === sessionUserId) {
                    html += '<button class="btn-icon is-delete" data-act="delete" data-id="' + r.id + '" title="Delete"><i class="ti ti-trash"></i></button>';
                }
                html += '</div>';
                return html;
            } }
        ]);

        var bkTable = $('#bkTable').DataTable($.extend(true, {}, dtDefaults, {
            ajax: { url: tableUrl(), dataSrc: 'data' },
            order: [[isAdmin ? 2 : 1, 'desc']],
            columns: cols
        }));

        function reloadAll() {
            bkTable.ajax.url(tableUrl()).load(null, false);
            refreshKpis();
            refreshCurrent();
        }

        $('#bkFilterStatus, #bkFilterFrom, #bkFilterTo, #bkFilterUser').on('change', function () {
            bkTable.ajax.url(tableUrl()).load(null, false);
        });

        $('#bkFilterReset').on('click', function () {
            $('#bkFilterStatus, #bkFilterFrom, #bkFilterTo, #bkFilterUser').val('');
            bkTable.ajax.url(tableUrl()).load(null, false);
        });

        // ── Start break
        $('#bkStartBtn').on('click', function () {
            var data = {
                user_id: isAdmin ? ($('#bkStartUser').val() || sessionUserId) : sessionUserId,
                reason:  $('#bkStartReason').val() || '',
                break_type: $('#bkStartType').val() || 'other'
            };
            $.post(apiBreaks + '?action=start', data, function (res) {
                if (res.success) {
                    toast('success', 'Break started');
                    $('#bkStartReason').val('');
                    reloadAll();
                } else {
                    toast('error', res.message || 'Failed to start break');
                }
            }, 'json');
        });

        // ── End the active break (button in active box)
        $('#bkEndBtn').on('click', function () {
            if (!currentBreak) { refreshCurrent(); return; }
            $.post(apiBreaks + '?action=end', { id: currentBreak.id }, function (res) {
                if (res.success) {
                    var mins = res.data && res.data.duration_minutes != null
                        ? formatMinutes(res.data.duration_minutes) : '—';
                    toast('success', 'Break ended (' + mins + ')');
                    reloadAll();
                } else {
                    toast('error', res.message || 'Failed to end break');
                }
            }, 'json');
        });

        // ── Row actions
        $('#bkTable tbody').on('click', '.btn-icon[data-act="end"]', function () {
            var id = $(this).data('id');
            $.post(apiBreaks + '?action=end', { id: id }, function (res) {
                if (res.success) {
                    toast('success', 'Break ended');
                    reloadAll();
                } else {
                    toast('error', res.message || 'Failed');
                }
            }, 'json');
        });
        $('#bkTable tbody').on('click', '.btn-icon[data-act="delete"]', function () {
            var id = $(this).data('id');
            if (!confirm('Delete this break record?')) return;
            $.post(apiBreaks + '?action=delete', { id: id }, function (res) {
                if (res.success) {
                    toast('success', 'Deleted');
                    reloadAll();
                } else {
                    toast('error', res.message || 'Failed');
                }
            }, 'json');
        });

        // ── Manual add (admin only)
        if (isAdmin) {
            var bkManualModal = new bootstrap.Modal(document.getElementById('bkManualModal'));

            $('#bkManualAddBtn').on('click', function () {
                $('#bkManualForm')[0].reset();
                $('#bkManualDate').val(new Date().toISOString().slice(0, 10));
                $('#bkManualPreview').addClass('d-none');
                bkManualModal.show();
            });

            function updateManualPreview() {
                var d = $('#bkManualDate').val();
                var s = $('#bkManualStart').val();
                var e = $('#bkManualEnd').val();
                if (!d || !s || !e) { $('#bkManualPreview').addClass('d-none'); return; }
                var startMs = new Date(d + 'T' + s).getTime();
                var endMs   = new Date(d + 'T' + e).getTime();
                if (!(endMs > startMs)) { $('#bkManualPreview').addClass('d-none'); return; }
                var mins = Math.floor((endMs - startMs) / 60000);
                $('#bkManualDuration').text(formatMinutes(mins));
                $('#bkManualPreview').removeClass('d-none');
            }
            $('#bkManualDate, #bkManualStart, #bkManualEnd').on('change input', updateManualPreview);

            $('#bkManualSave').on('click', function () {
                var data = {
                    user_id:    $('#bkManualUser').val(),
                    break_date: $('#bkManualDate').val(),
                    start_time: $('#bkManualStart').val(),
                    end_time:   $('#bkManualEnd').val(),
                    reason:     $('#bkManualReason').val(),
                    break_type: $('#bkManualType').val() || 'other'
                };
                if (!data.user_id || !data.break_date || !data.start_time) {
                    toast('error', 'Employee, date and start time are required.');
                    return;
                }
                $.post(apiBreaks + '?action=manual_add', data, function (res) {
                    if (res.success) {
                        bkManualModal.hide();
                        toast('success', 'Break added');
                        reloadAll();
                    } else {
                        toast('error', res.message || 'Failed to save');
                    }
                }, 'json');
            });
        }

        // Initial loads
        refreshKpis();
        refreshCurrent();

        // Periodic KPI/current refresh (so admins see other people's status update)
        setInterval(function () {
            refreshCurrent();
            refreshKpis();
        }, 30000);
    }

    init();
})();
</script>

<?php new_hrm_render_page_end(); ?>
