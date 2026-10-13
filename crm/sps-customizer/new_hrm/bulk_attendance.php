<?php
require_once __DIR__ . '/bootstrap.php';
hrm_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $mode = ($_POST['mode'] ?? 'in') === 'out' ? 'out' : 'in';
    $result = hrm_record_attendance($userId, $mode);
    hrm_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Attendance saved successfully.' : $result['message']);
    header('Location: bulk_attendance.php');
    exit();
}

$employees = hrm_fetch_employees(false);
$todayLogs = hrm_fetch_attendance_logs(null, date('Y-m-d'), date('Y-m-d'));

new_hrm_render_page_start('Manual Attendance', 'Manual Attendance', 'Admins can enter attendance manually for any employee.');
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<style>
.bulk-dt-table thead th {
    background: #f4f7fb !important;
    color: var(--hrm-muted) !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .06em !important;
    border-bottom: 2px solid var(--hrm-border) !important;
    white-space: nowrap;
    padding: 10px 12px !important;
}
.bulk-dt-table td {
    vertical-align: middle !important;
    padding: 10px 12px !important;
    border-bottom: 1px solid #f1f5f9 !important;
    font-size: 13px;
}
.bulk-dt-table tbody tr:last-child td { border-bottom: none !important; }
.bulk-dt-table tbody tr { transition: background .12s; }
.bulk-dt-table tbody tr:hover td { background: #f5f9ff !important; }

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
    box-shadow: 0 0 0 3px rgba(20,86,184,.10) !important;
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
div.dataTables_wrapper .pagination { gap: 3px; margin: 8px 0 0; }
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

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card hrm-section-card">
            <div class="card-header">
                <h5 class="mb-0">Add Attendance Entry</h5>
            </div>
            <div class="card-body">
                <form method="post" action="new_hrm/bulk_attendance.php">
                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <select name="user_id" class="form-control" required>
                            <option value="">Select employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo (int) $employee['user_id']; ?>">
                                    <?php echo hrm_e($employee['full_name'] ?: $employee['user_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Entry Type</label>
                        <select name="mode" class="form-control">
                            <option value="in">Arrival / Check In</option>
                            <option value="out">Departure / Check Out</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Entry</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8 mb-4">
        <div class="card hrm-section-card">
            <div class="card-header">
                <h5 class="mb-0">Today's Attendance Log</h5>
            </div>
            <div class="card-body">
                <table id="bulkAttendanceTable" class="bulk-dt-table display nowrap" style="width:100%">
                    <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Source</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($todayLogs as $log): ?>
                        <tr>
                            <td><?php echo hrm_e($log['user_name']); ?></td>
                            <td data-order="<?php echo (int) strtotime($log['log_time']); ?>">
                                <?php echo hrm_e(date('d M Y h:i A', strtotime($log['log_time']))); ?>
                            </td>
                            <td><?php echo (int) $log['state'] === 0 ? 'Check In' : 'Check Out'; ?></td>
                            <td><?php echo hrm_e(ucfirst($log['source'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    function init() {
        if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
            return setTimeout(init, 50);
        }
        var $tbl = $('#bulkAttendanceTable');
        if ($tbl.length && !$.fn.DataTable.isDataTable($tbl)) {
            $tbl.DataTable({
                pageLength: 10,
                responsive: true,
                order: [[1, 'desc']],
                language: {
                    search: '',
                    searchPlaceholder: 'Search attendance...',
                    lengthMenu: 'Show _MENU_ rows',
                    info: 'Showing _START_-_END_ of _TOTAL_',
                    infoEmpty: 'No attendance found',
                    emptyTable: 'No attendance has been recorded today',
                    paginate: { previous: '<', next: '>' }
                },
                dom: '<"d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"lf>rtip',
                columnDefs: [
                    { targets: 0, width: '32%' },
                    { targets: 1, width: '30%' }
                ]
            });
        }
    }
    init();
})();
</script>
<?php
new_hrm_render_page_end();
