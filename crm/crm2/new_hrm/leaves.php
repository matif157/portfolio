<?php
require_once __DIR__ . '/bootstrap.php';

$isAdmin  = hrm_is_admin();
$userId   = hrm_session_user_id();
$employees = $isAdmin ? hrm_fetch_employees(false) : [];

new_hrm_render_page_start(
    $isAdmin ? 'Manage Leaves' : 'My Leaves',
    $isAdmin ? 'Manage Leaves' : 'My Leaves',
    $isAdmin
        ? 'Review, approve, assign and configure employee leave.'
        : 'Apply for leave and track the status of your requests.'
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
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
            </svg>
        </div>
        <div class="hrm-tile-body">
            <span class="hrm-tile-label">Total Requests</span>
            <span class="hrm-tile-value" id="lvKpiTotal">0</span>
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
            <span class="hrm-tile-label">Pending</span>
            <span class="hrm-tile-value" id="lvKpiPending">0</span>
        </div>
        <div class="hrm-tile-shine"></div>
    </div>

    <div class="hrm-metric-tile hrm-tile--green">
        <div class="hrm-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="hrm-tile-body">
            <span class="hrm-tile-label">Approved</span>
            <span class="hrm-tile-value" id="lvKpiApproved">0</span>
        </div>
        <div class="hrm-tile-shine"></div>
    </div>

    <div class="hrm-metric-tile hrm-tile--violet">
        <div class="hrm-tile-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </div>
        <div class="hrm-tile-body">
            <span class="hrm-tile-label">Rejected</span>
            <span class="hrm-tile-value" id="lvKpiRejected">0</span>
        </div>
        <div class="hrm-tile-shine"></div>
    </div>
</div>

<!-- ── TABS ── -->
<?php if ($isAdmin): ?>
<ul class="nav nav-tabs lv-tabs mb-3" id="lvTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#lvTabRequests">All Requests</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#lvTabAssign">Assign Leave</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#lvTabTypes">Leave Types</a></li>
</ul>
<?php endif; ?>

<div class="tab-content">

    <!-- ═══ REQUESTS ═══ -->
    <div class="tab-pane fade show active" id="lvTabRequests">
        <div class="hrm-panel">
            <div class="hrm-panel-head">
                <div class="hrm-panel-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hrm-panel-icon">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <?php echo $isAdmin ? 'All Leave Requests' : 'My Leave Requests'; ?>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm lv-filter-select" id="lvFilterStatus">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <button class="btn btn-sm btn-primary" id="lvApplyBtn">
                        <i class="ti ti-plus"></i> Apply for Leave
                    </button>
                </div>
            </div>
            <div class="hrm-panel-body">
                <table id="lvTable" class="hrm-dt-table display nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <?php if ($isAdmin): ?><th>Employee</th><?php endif; ?>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Days</th>
                            <th>Reason</th>
                            <th>Applied</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <!-- ═══ ASSIGN ═══ -->
    <div class="tab-pane fade" id="lvTabAssign">
        <div class="hrm-panel" style="max-width:720px;">
            <div class="hrm-panel-head">
                <div class="hrm-panel-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hrm-panel-icon">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <polyline points="17 11 19 13 23 9"/>
                    </svg>
                    Assign Leave to Employee
                </div>
                <span class="hrm-panel-badge">Auto-approved</span>
            </div>
            <div class="hrm-panel-body">
                <p class="text-muted small mb-3">
                    Assign leave directly to an employee. The request will be marked as <strong>Approved</strong> immediately.
                </p>
                <form id="lvAssignForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" name="user_id" id="lvAssignUser">
                                <option value="">— Select Employee —</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?php echo (int) $e['user_id']; ?>">
                                        <?php echo hrm_e(($e['full_name'] ?: $e['user_name']) . ($e['hrm_employee_code'] ? ' [' . $e['hrm_employee_code'] . ']' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="leave_type_id" id="lvAssignType">
                                <option value="">— Select Type —</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">From Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="from_date" id="lvAssignFrom">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">To Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="to_date" id="lvAssignTo">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" name="reason" rows="2" id="lvAssignReason" placeholder="Optional"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Admin Note</label>
                            <input type="text" class="form-control" name="admin_note" id="lvAssignNote" placeholder="Internal note (optional)">
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-primary" id="lvAssignSubmit">
                                <i class="ti ti-check"></i> Assign &amp; Approve
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══ LEAVE TYPES ═══ -->
    <div class="tab-pane fade" id="lvTabTypes">
        <div class="hrm-panel">
            <div class="hrm-panel-head">
                <div class="hrm-panel-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="hrm-panel-icon">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                        <line x1="7" y1="7" x2="7.01" y2="7"/>
                    </svg>
                    Leave Types
                </div>
                <button class="btn btn-sm btn-primary" id="lvAddTypeBtn">
                    <i class="ti ti-plus"></i> Add Leave Type
                </button>
            </div>
            <div class="hrm-panel-body">
                <table id="lvTypesTable" class="hrm-dt-table display nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Color</th>
                            <th>Max Days / Year</th>
                            <th>Paid</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══ APPLY MODAL ═══ -->
<div class="modal fade" id="lvApplyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Apply for Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="lvApplyForm">
                    <div class="row g-3">
                        <?php if ($isAdmin): ?>
                        <div class="col-md-12">
                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" name="user_id" id="lvApplyUser">
                                <option value="">— Select Employee —</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?php echo (int) $e['user_id']; ?>">
                                        <?php echo hrm_e(($e['full_name'] ?: $e['user_name']) . ($e['hrm_employee_code'] ? ' [' . $e['hrm_employee_code'] . ']' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-12">
                            <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="leave_type_id" id="lvApplyType">
                                <option value="">— Select Type —</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">From <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="from_date" id="lvApplyFrom">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">To <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="to_date" id="lvApplyTo">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" name="reason" rows="3" id="lvApplyReason" placeholder="Describe the reason for leave…"></textarea>
                        </div>
                        <div id="lvApplyPreview" class="col-12 d-none">
                            <div class="alert alert-info py-2 mb-0 small">
                                Duration: <strong id="lvApplyDays">—</strong> day(s)
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="lvApplySubmit">
                    <i class="ti ti-send"></i> Submit Request
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- ═══ REVIEW MODAL ═══ -->
<div class="modal fade" id="lvReviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
        <div class="modal-content">
            <div class="modal-header" id="lvReviewHeader">
                <h5 class="modal-title" id="lvReviewTitle">Review Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="lvReviewId">
                <input type="hidden" id="lvReviewAction">
                <div id="lvReviewInfo" class="text-muted small mb-3"></div>
                <label class="form-label">Note to Employee (optional)</label>
                <textarea class="form-control" id="lvReviewNote" rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn" id="lvReviewConfirm">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ TYPE MODAL ═══ -->
<div class="modal fade" id="lvTypeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lvTypeTitle">Add Leave Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="lvTypeForm">
                    <input type="hidden" id="lvTypeId">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lvTypeName" placeholder="e.g. Annual Leave">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Badge Color</label>
                            <input type="color" class="form-control form-control-color" id="lvTypeColor" value="#4f46e5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Days / Year</label>
                            <input type="number" class="form-control" id="lvTypeMaxDays" min="0" value="0" placeholder="0 = unlimited">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="lvTypePaid">
                                <label class="form-check-label" for="lvTypePaid">
                                    Paid leave <small class="text-muted">(employee gets paid_leave_daily_hours × hourly_rate for each approved day)</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-12 d-none" id="lvTypeActiveRow">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="lvTypeActive" checked>
                                <label class="form-check-label" for="lvTypeActive">Active</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="lvTypeSave"><span id="lvTypeSaveLabel">Add Type</span></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* ── KPI metric tiles (shared look from index.php) ── */
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
    padding: 20px;
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

/* ── Panels ── */
.hrm-panel {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 16px rgba(15,23,42,.05);
    margin-bottom: 20px;
}
.hrm-panel-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px;
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

/* ── DataTable look ── */
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

.hrm-cell-person { display: flex; align-items: center; gap: 9px; }
.hrm-cell-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--hrm-primary-soft); color: var(--hrm-primary);
    font-size: 11px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

/* ── DataTables wrapper controls ── */
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
div.dataTables_wrapper .pagination .page-item.active .page-link {
    background: var(--hrm-primary) !important;
    border-color: var(--hrm-primary) !important;
    color: #fff !important;
}

.lv-tabs .nav-link {
    border: 1px solid transparent;
    color: var(--hrm-muted);
    font-weight: 600;
    font-size: 13px;
    padding: 8px 14px;
    border-radius: 8px 8px 0 0;
}
.lv-tabs .nav-link.active {
    color: var(--hrm-primary);
    background: #fff;
    border-color: var(--hrm-border) var(--hrm-border) #fff;
}
.lv-filter-select { width: auto; min-width: 150px; font-size: 12px; }
.lv-actions .btn-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; padding: 0; border-radius: 6px;
    border: 1px solid var(--hrm-border); background: #fff; color: var(--hrm-muted);
    margin: 0 2px; transition: all .15s;
}
.lv-actions .btn-icon:hover { transform: translateY(-1px); }
.lv-actions .btn-icon.is-approve:hover { background: var(--hrm-success-soft); color: var(--hrm-success); border-color: var(--hrm-success); }
.lv-actions .btn-icon.is-reject:hover  { background: var(--hrm-danger-soft);  color: var(--hrm-danger);  border-color: var(--hrm-danger); }
.lv-actions .btn-icon.is-delete:hover  { background: var(--hrm-danger-soft);  color: var(--hrm-danger);  border-color: var(--hrm-danger); }
.lv-type-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 700;
    border: 1px solid;
}
.lv-color-swatch {
    display: inline-block; width: 18px; height: 18px;
    border-radius: 5px; border: 1px solid rgba(0,0,0,.1);
    vertical-align: middle; margin-right: 6px;
}
</style>

<script>
(function () {
    var isAdmin     = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    var apiLeaves   = 'new_hrm/api/leaves.php';
    var apiTypes    = 'new_hrm/api/leave_types.php';

    function toast(kind, msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: kind, title: msg, showConfirmButton: false, timer: 2200 });
        } else {
            alert(msg);
        }
    }
    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
        });
    }
    function badgeForStatus(s) {
        var cls = { pending: 'is-warning', approved: 'is-success', rejected: 'is-danger' }[s] || 'is-neutral';
        return '<span class="hrm-badge ' + cls + '">' + s.charAt(0).toUpperCase() + s.slice(1) + '</span>';
    }
    function typeChip(name, color) {
        var c = color || '#4f46e5';
        return '<span class="lv-type-chip" style="background:' + c + '15;color:' + c + ';border-color:' + c + '40;">'
            + escapeHtml(name || '—') + '</span>';
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
                emptyTable: 'No leave requests found',
                paginate: { previous: '‹', next: '›' }
            },
            dom: '<"hrm-dt-controls d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"lf>rtip'
        };

        // ───── KPI helper ─────
        function updateKPIs(rows) {
            $('#lvKpiTotal').text(rows.length);
            $('#lvKpiPending').text(rows.filter(function (r) { return r.status === 'pending'; }).length);
            $('#lvKpiApproved').text(rows.filter(function (r) { return r.status === 'approved'; }).length);
            $('#lvKpiRejected').text(rows.filter(function (r) { return r.status === 'rejected'; }).length);
        }

        // ───── REQUESTS TABLE ─────
        function leavesUrl() {
            return apiLeaves + '?action=list&status=' + encodeURIComponent($('#lvFilterStatus').val() || '');
        }

        var leavesColumns = [];
        if (isAdmin) {
            leavesColumns.push({
                data: 'display_name',
                render: function (v, t, r) {
                    return '<div class="hrm-cell-person">'
                        + '<div class="hrm-cell-avatar">' + (v ? v.charAt(0).toUpperCase() : 'U') + '</div>'
                        + '<span>' + escapeHtml(v || ('User ' + r.user_id)) + '</span>'
                        + '</div>';
                }
            });
        }
        leavesColumns = leavesColumns.concat([
            { data: 'leave_type_name', render: function (v, t, r) { return typeChip(v, r.leave_color); } },
            { data: 'from_date_fmt' },
            { data: 'to_date_fmt' },
            { data: 'total_days', render: function (v) { return '<strong>' + v + '</strong>'; } },
            { data: 'reason', render: function (v) {
                if (!v) return '<span class="text-muted">—</span>';
                return '<span title="' + escapeHtml(v) + '" style="max-width:160px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle;">' + escapeHtml(v) + '</span>';
            } },
            { data: 'applied_fmt', render: function (v) { return '<span class="text-muted small">' + escapeHtml(v) + '</span>'; } },
            { data: 'status', render: badgeForStatus },
            { data: null, orderable: false, render: function (d, t, r) {
                var html = '<div class="lv-actions">';
                if (isAdmin && r.status === 'pending') {
                    html += '<button class="btn-icon is-approve" title="Approve" data-act="approve" data-id="' + r.id + '"><i class="ti ti-check"></i></button>';
                    html += '<button class="btn-icon is-reject" title="Reject" data-act="reject" data-id="' + r.id + '"><i class="ti ti-x"></i></button>';
                }
                if (isAdmin || r.status === 'pending') {
                    html += '<button class="btn-icon is-delete" title="Delete" data-act="delete" data-id="' + r.id + '"><i class="ti ti-trash"></i></button>';
                }
                html += '</div>';
                return html;
            } }
        ]);

        var lvTable = $('#lvTable').DataTable($.extend(true, {}, dtDefaults, {
            ajax: {
                url: leavesUrl(),
                dataSrc: function (json) {
                    var rows = json.data || [];
                    updateKPIs(rows);
                    return rows;
                }
            },
            order: [[isAdmin ? 6 : 5, 'desc']],
            columns: leavesColumns
        }));

        $('#lvFilterStatus').on('change', function () {
            lvTable.ajax.url(leavesUrl()).load();
        });

        // ───── ROW ACTIONS (delete only here; approve/reject handled below) ─────
        $('#lvTable tbody').on('click', '.btn-icon[data-act="delete"]', function () {
            var id = $(this).data('id');
            if (!confirm('Delete this leave record?')) return;
            $.post(apiLeaves + '?action=delete', { id: id }, function (res) {
                if (res.success) { toast('success', 'Deleted'); lvTable.ajax.reload(null, false); }
                else toast('error', res.message || 'Failed to delete');
            }, 'json');
        });

        // ───── LEAVE TYPES dropdowns ─────
        function loadTypesDropdowns() {
            $.getJSON(apiTypes + '?action=list', function (res) {
                var types = (res.data || []).filter(function (t) { return parseInt(t.is_active, 10) === 1; });
                ['#lvApplyType', '#lvAssignType'].forEach(function (sel) {
                    var $sel = $(sel);
                    if (!$sel.length) return;
                    $sel.find('option:not(:first)').remove();
                    types.forEach(function (t) {
                        $sel.append('<option value="' + t.id + '">' + escapeHtml(t.name) + (t.max_days > 0 ? ' (max ' + t.max_days + 'd)' : '') + '</option>');
                    });
                });
            });
        }
        loadTypesDropdowns();

        // ───── APPLY MODAL ─────
        var lvApplyModal = new bootstrap.Modal(document.getElementById('lvApplyModal'));
        $('#lvApplyBtn').on('click', function () {
            $('#lvApplyForm')[0].reset();
            $('#lvApplyPreview').addClass('d-none');
            lvApplyModal.show();
        });
        $('#lvApplyFrom, #lvApplyTo').on('change', function () {
            var f = $('#lvApplyFrom').val(), t = $('#lvApplyTo').val();
            if (f && t && f <= t) {
                var days = Math.floor((new Date(t) - new Date(f)) / 86400000) + 1;
                $('#lvApplyDays').text(days);
                $('#lvApplyPreview').removeClass('d-none');
            } else {
                $('#lvApplyPreview').addClass('d-none');
            }
        });
        $('#lvApplySubmit').on('click', function () {
            var lt = $('#lvApplyType').val(), f = $('#lvApplyFrom').val(), t = $('#lvApplyTo').val();
            if (!lt || !f || !t) { toast('error', 'Please fill all required fields'); return; }
            if (isAdmin && !$('#lvApplyUser').val()) { toast('error', 'Please select an employee'); return; }

            $.post(apiLeaves + '?action=apply', $('#lvApplyForm').serialize(), function (res) {
                if (res.success) {
                    lvApplyModal.hide();
                    toast('success', 'Leave request submitted');
                    lvTable.ajax.reload(null, false);
                } else {
                    toast('error', res.message || 'Failed to submit');
                }
            }, 'json');
        });

        // ───── ADMIN-ONLY: ASSIGN, REVIEW, TYPES ─────
        if (isAdmin) {
            // Assign
            $('#lvAssignSubmit').on('click', function () {
                var u = $('#lvAssignUser').val(), lt = $('#lvAssignType').val();
                var f = $('#lvAssignFrom').val(), t = $('#lvAssignTo').val();
                if (!u || !lt || !f || !t) { toast('error', 'Please fill all required fields'); return; }

                $.post(apiLeaves + '?action=assign', $('#lvAssignForm').serialize(), function (res) {
                    if (res.success) {
                        $('#lvAssignForm')[0].reset();
                        toast('success', 'Leave assigned and approved');
                        lvTable.ajax.reload(null, false);
                    } else {
                        toast('error', res.message || 'Failed to assign');
                    }
                }, 'json');
            });

            // Review
            var lvReviewModal = new bootstrap.Modal(document.getElementById('lvReviewModal'));
            function openReview(id, action) {
                $('#lvReviewId').val(id);
                $('#lvReviewAction').val(action);
                $('#lvReviewNote').val('');
                var isApprove = action === 'approve';
                $('#lvReviewTitle').text(isApprove ? 'Approve Leave Request' : 'Reject Leave Request');
                $('#lvReviewHeader').css('background', isApprove ? 'var(--hrm-success-soft)' : 'var(--hrm-danger-soft)');
                $('#lvReviewConfirm').removeClass('btn-success btn-danger')
                    .addClass(isApprove ? 'btn-success' : 'btn-danger')
                    .html('<i class="ti ti-' + (isApprove ? 'check' : 'x') + '"></i> ' + (isApprove ? 'Approve' : 'Reject'));

                $.getJSON(apiLeaves + '?action=get&id=' + id, function (d) {
                    if (d) {
                        $('#lvReviewInfo').html(
                            '<strong>' + escapeHtml(d.user_id) + '</strong> · '
                            + escapeHtml(d.from_date) + ' → ' + escapeHtml(d.to_date)
                            + ' · <strong>' + d.total_days + '</strong> day(s)'
                        );
                    }
                });
                lvReviewModal.show();
            }
            // Make openReview accessible to row handler above
            window.__lvOpenReview = openReview;

            $('#lvReviewConfirm').on('click', function () {
                var id = $('#lvReviewId').val();
                var act = $('#lvReviewAction').val();
                var note = $('#lvReviewNote').val();
                $.post(apiLeaves + '?action=' + act, { id: id, admin_note: note }, function (res) {
                    lvReviewModal.hide();
                    if (res.success) {
                        toast('success', act === 'approve' ? 'Leave approved' : 'Leave rejected');
                        lvTable.ajax.reload(null, false);
                    } else {
                        toast('error', res.message || 'Operation failed');
                    }
                }, 'json');
            });

            // Types table — lazy load on tab show
            var typesTable = null;
            function buildTypesTable() {
                if (typesTable) { typesTable.ajax.reload(null, false); return; }
                typesTable = $('#lvTypesTable').DataTable($.extend(true, {}, dtDefaults, {
                    ajax: { url: apiTypes + '?action=list', dataSrc: 'data' },
                    columns: [
                        { data: null, orderable: false, render: function (d, t, r, m) { return '<span class="text-muted">' + (m.row + 1) + '</span>'; } },
                        { data: 'name', render: function (v, t, r) { return '<strong style="color:' + (r.color || '#4f46e5') + '">' + escapeHtml(v) + '</strong>'; } },
                        { data: 'color', render: function (v) { return '<span class="lv-color-swatch" style="background:' + v + '"></span>' + escapeHtml(v); } },
                        { data: 'max_days', render: function (v) { return parseInt(v, 10) > 0 ? '<strong>' + v + '</strong> days' : '<span class="text-muted">Unlimited</span>'; } },
                        { data: 'is_paid', render: function (v) { return parseInt(v, 10) === 1 ? '<span class="hrm-badge is-success">Paid</span>' : '<span class="hrm-badge is-neutral">Unpaid</span>'; } },
                        { data: 'is_active', render: function (v) { return parseInt(v, 10) === 1 ? '<span class="hrm-badge is-success">Active</span>' : '<span class="hrm-badge is-danger">Inactive</span>'; } },
                        { data: null, orderable: false, render: function (d, t, r) {
                            return '<div class="lv-actions">'
                                + '<button class="btn-icon" data-act="edit-type" data-id="' + r.id + '" title="Edit"><i class="ti ti-pencil"></i></button>'
                                + '<button class="btn-icon is-delete" data-act="del-type" data-id="' + r.id + '" title="Delete"><i class="ti ti-trash"></i></button>'
                                + '</div>';
                        } }
                    ]
                }));
            }

            $('a[data-bs-toggle="tab"][href="#lvTabTypes"]').on('shown.bs.tab', buildTypesTable);

            // Type modal
            var lvTypeModal = new bootstrap.Modal(document.getElementById('lvTypeModal'));
            $('#lvAddTypeBtn').on('click', function () {
                $('#lvTypeTitle').text('Add Leave Type');
                $('#lvTypeSaveLabel').text('Add Type');
                $('#lvTypeId').val('');
                $('#lvTypeName').val('');
                $('#lvTypeColor').val('#4f46e5');
                $('#lvTypeMaxDays').val(0);
                $('#lvTypePaid').prop('checked', false);
                $('#lvTypeActive').prop('checked', true);
                $('#lvTypeActiveRow').addClass('d-none');
                lvTypeModal.show();
            });

            $('#lvTypesTable tbody').on('click', '.btn-icon', function () {
                var id = $(this).data('id');
                var act = $(this).data('act');
                if (act === 'edit-type') {
                    $.getJSON(apiTypes + '?action=get&id=' + id, function (d) {
                        if (!d) { toast('error', 'Not found'); return; }
                        $('#lvTypeTitle').text('Edit Leave Type');
                        $('#lvTypeSaveLabel').text('Save Changes');
                        $('#lvTypeId').val(d.id);
                        $('#lvTypeName').val(d.name);
                        $('#lvTypeColor').val(d.color);
                        $('#lvTypeMaxDays').val(d.max_days);
                        $('#lvTypePaid').prop('checked', parseInt(d.is_paid, 10) === 1);
                        $('#lvTypeActive').prop('checked', parseInt(d.is_active, 10) === 1);
                        $('#lvTypeActiveRow').removeClass('d-none');
                        lvTypeModal.show();
                    });
                } else if (act === 'del-type') {
                    if (!confirm('Delete this leave type?')) return;
                    $.post(apiTypes + '?action=delete', { id: id }, function (res) {
                        if (res.success) {
                            toast('success', 'Leave type deleted');
                            typesTable.ajax.reload(null, false);
                            loadTypesDropdowns();
                        } else toast('error', res.message || 'Delete failed');
                    }, 'json');
                }
            });

            $('#lvTypeSave').on('click', function () {
                var id = $('#lvTypeId').val();
                var name = $('#lvTypeName').val().trim();
                if (!name) { toast('error', 'Name is required'); return; }
                var data = {
                    name: name,
                    color: $('#lvTypeColor').val(),
                    max_days: $('#lvTypeMaxDays').val(),
                    is_paid: $('#lvTypePaid').is(':checked') ? 1 : 0,
                    is_active: $('#lvTypeActive').is(':checked') ? 1 : 0
                };
                var action = id ? 'update' : 'create';
                if (id) data.id = id;

                $.post(apiTypes + '?action=' + action, data, function (res) {
                    if (res.success) {
                        lvTypeModal.hide();
                        toast('success', id ? 'Leave type updated' : 'Leave type added');
                        if (typesTable) typesTable.ajax.reload(null, false);
                        loadTypesDropdowns();
                    } else {
                        toast('error', res.message || 'Operation failed');
                    }
                }, 'json');
            });
        }

        // Row handler for approve/reject (admin only — buttons aren't rendered for employees)
        $('#lvTable tbody').on('click', '.btn-icon[data-act="approve"], .btn-icon[data-act="reject"]', function () {
            if (window.__lvOpenReview) {
                window.__lvOpenReview($(this).data('id'), $(this).data('act'));
            }
        });
    }

    init();
})();
</script>

<?php new_hrm_render_page_end(); ?>
