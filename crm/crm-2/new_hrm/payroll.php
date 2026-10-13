<?php
require_once __DIR__ . '/bootstrap.php';
hrm_require_admin();

$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payroll'])) {
    $saved = hrm_save_payroll_snapshot((int) $_POST['month'], (int) $_POST['year']);
    hrm_flash('success', "Payroll generated for {$saved} employee(s).");
    header('Location: payroll.php?month=' . (int) $_POST['month'] . '&year=' . (int) $_POST['year']);
    exit();
}

$employees = hrm_fetch_employees(false);
$previews = [];
foreach ($employees as $employee) {
    $previews[] = hrm_salary_preview((int) $employee['user_id'], $month, $year);
}
$history = hrm_fetch_payroll_history(null, 20);

new_hrm_render_page_start('Payroll', 'Payroll', 'Salary is calculated from total worked minutes and can be saved into payroll history.');
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<style>
.pay-dt-table thead th {
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

.pay-dt-table td {
    vertical-align: middle !important;
    padding: 10px 12px !important;
    border-bottom: 1px solid #f1f5f9 !important;
    font-size: 13px;
}

.pay-dt-table tbody tr:last-child td { border-bottom: none !important; }
.pay-dt-table tbody tr { transition: background .12s; }
.pay-dt-table tbody tr:hover td { background: #f5f9ff !important; }

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

<div class="card hrm-section-card mb-4">
    <div class="card-body">
        <form method="get" action="new_hrm/payroll.php" class="row align-items-end">
            <div class="col-md-3 mb-3">
                <label class="form-label">Month</label>
                <input type="number" min="1" max="12" name="month" class="form-control" value="<?php echo (int) $month; ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Year</label>
                <input type="number" min="2020" max="2100" name="year" class="form-control" value="<?php echo (int) $year; ?>">
            </div>
            <div class="col-md-3 mb-3">
                <button type="submit" class="btn btn-light w-100">Preview</button>
            </div>
            <div class="col-md-3 mb-3">
                <button form="generate-payroll-form" type="submit" class="btn btn-primary w-100">Save Payroll</button>
            </div>
        </form>
        <form id="generate-payroll-form" method="post" action="new_hrm/payroll.php?month=<?php echo (int) $month; ?>&year=<?php echo (int) $year; ?>">
            <input type="hidden" name="month" value="<?php echo (int) $month; ?>">
            <input type="hidden" name="year" value="<?php echo (int) $year; ?>">
            <input type="hidden" name="generate_payroll" value="1">
        </form>
    </div>
</div>

<div class="card hrm-section-card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Payroll Preview</h5>
    </div>
    <div class="card-body">
        <table id="payrollPreviewTable" class="pay-dt-table display nowrap" style="width:100%">
            <thead>
            <tr>
                <th>Employee</th>
                <th>Total Working Time</th>
                <th>Gross</th>
                <th>Allowance</th>
                <th>Deduction</th>
                <th>Net Salary</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($previews as $preview): ?>
                <?php if (!$preview) { continue; } ?>
                <tr>
                    <td><?php echo hrm_e($preview['employee']['full_name'] ?: $preview['employee']['user_name']); ?></td>
                    <td data-order="<?php echo (int) ($preview['working_seconds'] ?? 0); ?>"><?php echo hrm_e(hrm_format_hms($preview['working_seconds'] ?? 0)); ?></td>
                    <td data-order="<?php echo (float) $preview['gross_salary']; ?>"><?php echo hrm_e($preview['currency']); ?> <?php echo hrm_e(number_format($preview['gross_salary'], 2)); ?></td>
                    <td data-order="<?php echo (float) $preview['allowance']; ?>"><?php echo hrm_e($preview['currency']); ?> <?php echo hrm_e(number_format($preview['allowance'], 2)); ?></td>
                    <td data-order="<?php echo (float) $preview['deduction']; ?>"><?php echo hrm_e($preview['currency']); ?> <?php echo hrm_e(number_format($preview['deduction'], 2)); ?></td>
                    <td data-order="<?php echo (float) $preview['net_salary']; ?>" class="fw-bold"><?php echo hrm_e($preview['currency']); ?> <?php echo hrm_e(number_format($preview['net_salary'], 2)); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card hrm-section-card">
    <div class="card-header">
        <h5 class="mb-0">Saved Payroll History</h5>
    </div>
    <div class="card-body">
        <table id="payrollHistoryTable" class="pay-dt-table display nowrap" style="width:100%">
            <thead>
            <tr>
                <th>Employee</th>
                <th>Month</th>
                <th>Basic</th>
                <th>Allowance</th>
                <th>Deduction</th>
                <th>Net</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $row): ?>
                <tr>
                    <td><?php echo hrm_e($row['user_name']); ?></td>
                    <td data-order="<?php echo sprintf('%04d-%02d', (int) $row['pay_year'], (int) $row['pay_month']); ?>"><?php echo hrm_e(date('F Y', strtotime($row['pay_year'] . '-' . $row['pay_month'] . '-01'))); ?></td>
                    <td data-order="<?php echo (float) $row['basic_salary']; ?>"><?php echo hrm_e(number_format((float) $row['basic_salary'], 2)); ?></td>
                    <td data-order="<?php echo (float) $row['allowance']; ?>"><?php echo hrm_e(number_format((float) $row['allowance'], 2)); ?></td>
                    <td data-order="<?php echo (float) $row['deduction']; ?>"><?php echo hrm_e(number_format((float) $row['deduction'], 2)); ?></td>
                    <td data-order="<?php echo (float) $row['net_salary']; ?>"><?php echo hrm_e(number_format((float) $row['net_salary'], 2)); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    function init() {
        if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
            return setTimeout(init, 50);
        }

        var dtDefaults = {
            pageLength: 10,
            responsive: true,
            language: {
                search: '',
                searchPlaceholder: 'Search payroll...',
                lengthMenu: 'Show _MENU_ rows',
                info: 'Showing _START_-_END_ of _TOTAL_ entries',
                infoEmpty: 'No entries found',
                infoFiltered: '(filtered from _MAX_)',
                emptyTable: 'No data available',
                paginate: { previous: '<', next: '>' }
            },
            dom: '<"d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"lf>rtip'
        };

        var $previewTable = $('#payrollPreviewTable');
        if ($previewTable.length && !$.fn.DataTable.isDataTable($previewTable)) {
            $previewTable.DataTable($.extend(true, {}, dtDefaults, {
                order: [[5, 'desc']],
                columnDefs: [
                    { targets: 0, width: '28%' },
                    { targets: 1, width: '14%' },
                    { targets: [2, 3, 4, 5], width: '14%' }
                ],
                language: $.extend({}, dtDefaults.language, {
                    emptyTable: 'No payroll preview available for this period'
                })
            }));
        }

        var $historyTable = $('#payrollHistoryTable');
        if ($historyTable.length && !$.fn.DataTable.isDataTable($historyTable)) {
            $historyTable.DataTable($.extend(true, {}, dtDefaults, {
                order: [[1, 'desc']],
                columnDefs: [
                    { targets: 0, width: '24%' },
                    { targets: 1, width: '18%' },
                    { targets: [2, 3, 4, 5], width: '14%' }
                ],
                language: $.extend({}, dtDefaults.language, {
                    searchPlaceholder: 'Search history...',
                    emptyTable: 'No saved payroll history yet'
                })
            }));
        }
    }

    init();
})();
</script>
<?php
new_hrm_render_page_end();
