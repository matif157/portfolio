<?php
require_once __DIR__ . '/bootstrap.php';

$isAdmin = hrm_is_admin();
$month   = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$year    = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

$monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$monthName  = $monthNames[max(0, min(11, $month - 1))];

$salaryPreviews = [];
$salarySummary = [
    'employees' => 0,
    'total_minutes' => 0,
    'total_hours' => 0,
    'gross_total' => 0,
    'net_total' => 0,
    'avg_net' => 0,
];
$summaryCurrency = 'PKR';

if ($isAdmin) {
    $employees = hrm_fetch_employees(false);
    foreach ($employees as $employee) {
        $preview = hrm_salary_preview((int) $employee['user_id'], $month, $year);
        if (!$preview) {
            continue;
        }
        $salaryPreviews[] = $preview;
    }
    $salarySummary = hrm_payroll_summary($salaryPreviews);
    if ($salaryPreviews) {
        $summaryCurrency = $salaryPreviews[0]['currency'] ?: 'PKR';
    }
} else {
    $preview = hrm_salary_preview(hrm_session_user_id(), $month, $year);
    $history = hrm_fetch_payroll_history(hrm_session_user_id(), 12);
}

new_hrm_render_page_start(
    $isAdmin ? 'Salary Overview' : 'My Salary',
    $isAdmin ? 'Salary Overview' : 'My Salary',
    $isAdmin ? 'Quick salary preview for the selected month.' : 'Your salary is calculated automatically from working minutes.'
);
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<style>
.sal-layout {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 20px;
    align-items: start;
}

@media (max-width: 991px) {
    .sal-layout {
        grid-template-columns: 1fr;
    }
}

.sal-filter-panel {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(15, 23, 42, .07);
    position: sticky;
    top: 20px;
}

.sal-filter-header {
    padding: 18px 22px 16px;
    background: linear-gradient(135deg, #1456b8 0%, #1e75f0 100%);
    display: flex;
    align-items: center;
    gap: 12px;
}

.sal-filter-header-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(255, 255, 255, .18);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sal-filter-header-icon svg {
    width: 18px;
    height: 18px;
    stroke: #fff;
}

.sal-filter-header-title {
    color: #fff;
    font-size: 14px;
    font-weight: 700;
}

.sal-filter-header-sub {
    color: rgba(255, 255, 255, .75);
    font-size: 12px;
    margin-top: 1px;
}

.sal-filter-body {
    padding: 20px 22px 22px;
}

.sal-field {
    margin-bottom: 14px;
}

.sal-field label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--hrm-muted);
    margin-bottom: 5px;
}

.sal-field .form-control,
.sal-field select {
    border: 1.5px solid var(--hrm-border);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
    color: var(--hrm-text);
    background: #fafbfd;
    transition: border-color .15s, box-shadow .15s;
    width: 100%;
}

.sal-field .form-control:focus,
.sal-field select:focus {
    border-color: var(--hrm-primary);
    box-shadow: 0 0 0 3px rgba(20, 86, 184, .10);
    background: #fff;
    outline: none;
}

.sal-field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.sal-divider {
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .10em;
    color: var(--hrm-muted);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 16px 0 14px;
}

.sal-divider::before,
.sal-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--hrm-border);
}

.sal-btn-row {
    display: flex;
    gap: 8px;
    margin-top: 18px;
}

.sal-btn-primary {
    flex: 1;
    padding: 10px 16px;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg, #1456b8, #1e75f0);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity .15s, transform .15s;
    text-align: center;
}

.sal-btn-primary:hover {
    opacity: .9;
    transform: translateY(-1px);
}

.sal-btn-light {
    padding: 10px 16px;
    border-radius: 8px;
    border: 1.5px solid var(--hrm-border);
    background: #fff;
    color: var(--hrm-muted);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: border-color .15s;
}

.sal-btn-light:hover {
    border-color: var(--hrm-primary);
    color: var(--hrm-primary);
}

.sal-summary-stack {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.sal-summary-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #edf2f7;
}

.sal-summary-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}

.sal-summary-label {
    font-size: 12px;
    color: var(--hrm-muted);
}

.sal-summary-value {
    font-size: 14px;
    font-weight: 800;
    color: var(--hrm-text);
    text-align: right;
}

.sal-summary-value--net {
    color: var(--hrm-primary);
}

.sal-note-card {
    margin-top: 18px;
    padding: 14px 16px;
    border-radius: 12px;
    background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
    border: 1px solid #dbe7fb;
    color: var(--hrm-muted);
    font-size: 13px;
}

.sal-list-panel {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(15, 23, 42, .07);
}

.sal-list-header {
    padding: 16px 22px;
    background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    border-bottom: 1px solid var(--hrm-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.sal-list-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--hrm-text);
}

.sal-list-title svg {
    width: 18px;
    height: 18px;
    stroke: var(--hrm-primary);
}

.sal-list-count {
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--hrm-primary-soft);
    color: var(--hrm-primary);
}

.sal-list-body {
    padding: 18px 20px;
}

.sal-dt-table thead th {
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

.sal-dt-table td {
    vertical-align: middle !important;
    padding: 10px 12px !important;
    border-bottom: 1px solid #f1f5f9 !important;
    font-size: 13px;
}

.sal-dt-table tbody tr:last-child td {
    border-bottom: none !important;
}

.sal-dt-table tbody tr {
    transition: background .12s;
}

.sal-dt-table tbody tr:hover td {
    background: #f5f9ff !important;
}

.sal-name-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sal-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    flex-shrink: 0;
    background: var(--hrm-primary-soft);
    color: var(--hrm-primary);
}

.sal-name-info {
    display: flex;
    flex-direction: column;
}

.sal-name-primary {
    font-size: 13px;
    font-weight: 700;
    color: var(--hrm-text);
}

.sal-name-secondary {
    font-size: 11px;
    color: var(--hrm-muted);
    margin-top: 1px;
}

.sal-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}

.sal-badge--type {
    background: #f1ebff;
    color: #6d28d9;
}

.sal-currency {
    font-size: 11px;
    color: var(--hrm-muted);
    margin-right: 3px;
}

.sal-amount {
    white-space: nowrap;
}

.sal-actions-cell {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
}

.sal-action-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid transparent;
    transition: background .13s, border-color .13s;
}

.sal-action-link--light {
    background: var(--hrm-primary-soft);
    color: var(--hrm-primary);
}

.sal-action-link--light:hover {
    background: #d4e5ff;
    border-color: var(--hrm-primary);
}

.sal-action-link--primary {
    background: #eef5ff;
    color: #1456b8;
}

.sal-action-link--primary:hover {
    background: #dbeaff;
    border-color: #1456b8;
}

.sal-empty {
    padding: 28px;
    text-align: center;
    color: var(--hrm-muted);
    font-size: 13px;
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

@media (max-width: 767px) {
    .sal-field-row {
        grid-template-columns: 1fr;
    }

    .sal-btn-row {
        flex-direction: column;
    }
}
</style>

<?php if ($isAdmin): ?>

<div class="sal-layout">
    <div class="sal-filter-panel">
        <div class="sal-filter-header">
            <div class="sal-filter-header-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <div>
                <div class="sal-filter-header-title">Salary Controls</div>
                <div class="sal-filter-header-sub">Choose a month and review payroll totals before moving to payroll.</div>
            </div>
        </div>

        <div class="sal-filter-body">
            <form method="get" action="new_hrm/salary.php">
                <div class="sal-field-row">
                    <div class="sal-field">
                        <label>Month</label>
                        <select name="month" class="form-control">
                            <?php foreach ($monthNames as $index => $label): ?>
                                <?php $value = $index + 1; ?>
                                <option value="<?php echo $value; ?>" <?php echo $month === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sal-field">
                        <label>Year</label>
                        <input type="number" name="year" min="2020" max="2100" class="form-control" value="<?php echo (int) $year; ?>">
                    </div>
                </div>

                <div class="sal-btn-row">
                    <button type="submit" class="sal-btn-primary">Refresh Preview</button>
                    <a href="new_hrm/payroll.php?month=<?php echo (int) $month; ?>&year=<?php echo (int) $year; ?>" class="sal-btn-light">Payroll</a>
                </div>
            </form>

            <div class="sal-divider">This Period</div>

            <div class="sal-summary-stack">
                <div class="sal-summary-row">
                    <span class="sal-summary-label">Selected period</span>
                    <strong class="sal-summary-value"><?php echo hrm_e($monthName . ' ' . $year); ?></strong>
                </div>
                <div class="sal-summary-row">
                    <span class="sal-summary-label">Employees</span>
                    <strong class="sal-summary-value"><?php echo (int) $salarySummary['employees']; ?></strong>
                </div>
                <div class="sal-summary-row">
                    <span class="sal-summary-label">Total hours</span>
                    <strong class="sal-summary-value"><?php echo hrm_e(number_format((float) $salarySummary['total_hours'], 2)); ?></strong>
                </div>
                <div class="sal-summary-row">
                    <span class="sal-summary-label">Gross total</span>
                    <strong class="sal-summary-value"><?php echo hrm_e($summaryCurrency . ' ' . number_format((float) $salarySummary['gross_total'], 2)); ?></strong>
                </div>
                <div class="sal-summary-row">
                    <span class="sal-summary-label">Net total</span>
                    <strong class="sal-summary-value sal-summary-value--net"><?php echo hrm_e($summaryCurrency . ' ' . number_format((float) $salarySummary['net_total'], 2)); ?></strong>
                </div>
                <div class="sal-summary-row">
                    <span class="sal-summary-label">Average net</span>
                    <strong class="sal-summary-value"><?php echo hrm_e($summaryCurrency . ' ' . number_format((float) $salarySummary['avg_net'], 2)); ?></strong>
                </div>
            </div>

            <div class="sal-note-card">
                Use `Set Salary` to update rates for an employee, or move to `Payroll` to save approved payroll snapshots for this period.
            </div>
        </div>
    </div>

    <div class="sal-list-panel">
        <div class="sal-list-header">
            <div class="sal-list-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
                Current Salary Preview
            </div>
            <span class="sal-list-count"><?php echo count($salaryPreviews); ?> people</span>
        </div>

        <div class="sal-list-body">
            <?php if (!$salaryPreviews): ?>
                <div class="sal-empty">No salary previews are available for <?php echo hrm_e($monthName . ' ' . $year); ?>.</div>
            <?php else: ?>
                <table id="salaryOverviewTable" class="sal-dt-table display nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Hours</th>
                            <th>Gross</th>
                            <th>Net</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($salaryPreviews as $salaryPreview): ?>
                        <?php
                        $employeeName = $salaryPreview['employee']['full_name'] ?: $salaryPreview['employee']['user_name'];
                        $employeeLogin = $salaryPreview['employee']['user_login'] ?? '';
                        ?>
                        <tr>
                            <td>
                                <div class="sal-name-cell">
                                    <div class="sal-avatar"><?php echo hrm_e(hrm_initials($employeeName ?: 'U')); ?></div>
                                    <div class="sal-name-info">
                                        <span class="sal-name-primary"><?php echo hrm_e($employeeName); ?></span>
                                        <span class="sal-name-secondary">
                                            <?php echo hrm_e($employeeLogin ?: 'Salary preview'); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="sal-badge sal-badge--type"><?php echo hrm_e(ucfirst($salaryPreview['salary_type'])); ?></span>
                            </td>
                            <td data-order="<?php echo (float) $salaryPreview['hours']; ?>">
                                <?php echo hrm_e(number_format($salaryPreview['hours'], 2)); ?>
                            </td>
                            <td data-order="<?php echo (float) $salaryPreview['gross_salary']; ?>" class="sal-amount">
                                <span class="sal-currency"><?php echo hrm_e($salaryPreview['currency']); ?></span>
                                <strong><?php echo hrm_e(number_format($salaryPreview['gross_salary'], 2)); ?></strong>
                            </td>
                            <td data-order="<?php echo (float) $salaryPreview['net_salary']; ?>" class="sal-amount">
                                <span class="sal-currency"><?php echo hrm_e($salaryPreview['currency']); ?></span>
                                <strong><?php echo hrm_e(number_format($salaryPreview['net_salary'], 2)); ?></strong>
                            </td>
                            <td>
                                <div class="sal-actions-cell">
                                    <a href="new_hrm/set_salary.php" class="sal-action-link sal-action-link--light">Set Salary</a>
                                    <a href="new_hrm/payroll.php?month=<?php echo (int) $month; ?>&year=<?php echo (int) $year; ?>" class="sal-action-link sal-action-link--primary">Payroll</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>

<div class="sal-layout">
    <div class="sal-filter-panel">
        <div class="sal-filter-header">
            <div class="sal-filter-header-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <div>
                <div class="sal-filter-header-title">Current Salary Preview</div>
                <div class="sal-filter-header-sub">Review your monthly salary breakdown and refresh the selected period.</div>
            </div>
        </div>

        <div class="sal-filter-body">
            <form method="get" action="new_hrm/salary.php">
                <div class="sal-field-row">
                    <div class="sal-field">
                        <label>Month</label>
                        <select name="month" class="form-control">
                            <?php foreach ($monthNames as $index => $label): ?>
                                <?php $value = $index + 1; ?>
                                <option value="<?php echo $value; ?>" <?php echo $month === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sal-field">
                        <label>Year</label>
                        <input type="number" name="year" min="2020" max="2100" class="form-control" value="<?php echo (int) $year; ?>">
                    </div>
                </div>

                <div class="sal-btn-row">
                    <button type="submit" class="sal-btn-primary">Refresh Preview</button>
                </div>
            </form>

            <div class="sal-divider">My Summary</div>

            <?php if ($preview): ?>
                <?php $previewCurrency = $preview['currency'] ?: 'PKR'; ?>
                <div class="sal-summary-stack">
                    <div class="sal-summary-row">
                        <span class="sal-summary-label">Selected period</span>
                        <strong class="sal-summary-value"><?php echo hrm_e($monthName . ' ' . $year); ?></strong>
                    </div>
                    <div class="sal-summary-row">
                        <span class="sal-summary-label">Salary type</span>
                        <strong class="sal-summary-value"><?php echo hrm_e(ucfirst($preview['salary_type'])); ?></strong>
                    </div>
                    <div class="sal-summary-row">
                        <span class="sal-summary-label">Worked hours</span>
                        <strong class="sal-summary-value"><?php echo hrm_e(number_format($preview['hours'], 2)); ?></strong>
                    </div>
                    <div class="sal-summary-row">
                        <span class="sal-summary-label">Gross salary</span>
                        <strong class="sal-summary-value"><?php echo hrm_e($previewCurrency . ' ' . number_format($preview['gross_salary'], 2)); ?></strong>
                    </div>
                    <div class="sal-summary-row">
                        <span class="sal-summary-label">Allowance</span>
                        <strong class="sal-summary-value"><?php echo hrm_e($previewCurrency . ' ' . number_format($preview['allowance'], 2)); ?></strong>
                    </div>
                    <div class="sal-summary-row">
                        <span class="sal-summary-label">Deduction</span>
                        <strong class="sal-summary-value"><?php echo hrm_e($previewCurrency . ' ' . number_format($preview['deduction'], 2)); ?></strong>
                    </div>
                    <div class="sal-summary-row">
                        <span class="sal-summary-label">Net salary</span>
                        <strong class="sal-summary-value sal-summary-value--net"><?php echo hrm_e($previewCurrency . ' ' . number_format($preview['net_salary'], 2)); ?></strong>
                    </div>
                </div>
            <?php else: ?>
                <div class="sal-empty">Salary settings are not available yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="sal-list-panel">
        <div class="sal-list-header">
            <div class="sal-list-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                Payroll History
            </div>
            <span class="sal-list-count"><?php echo count($history); ?> records</span>
        </div>

        <div class="sal-list-body">
            <table id="salaryHistoryTable" class="sal-dt-table display nowrap" style="width:100%">
                <thead>
                    <tr>
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
                        <td data-order="<?php echo sprintf('%04d-%02d', (int) $row['pay_year'], (int) $row['pay_month']); ?>">
                            <?php echo hrm_e(date('F Y', strtotime($row['pay_year'] . '-' . $row['pay_month'] . '-01'))); ?>
                        </td>
                        <td data-order="<?php echo (float) $row['basic_salary']; ?>"><?php echo hrm_e(number_format((float) $row['basic_salary'], 2)); ?></td>
                        <td data-order="<?php echo (float) $row['allowance']; ?>"><?php echo hrm_e(number_format((float) $row['allowance'], 2)); ?></td>
                        <td data-order="<?php echo (float) $row['deduction']; ?>"><?php echo hrm_e(number_format((float) $row['deduction'], 2)); ?></td>
                        <td data-order="<?php echo (float) $row['net_salary']; ?>">
                            <strong><?php echo hrm_e(number_format((float) $row['net_salary'], 2)); ?></strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
(function hrmInitSalaryTables() {
    function init() {
        if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
            return setTimeout(init, 50);
        }

        var dtDefaults = {
            pageLength: 10,
            responsive: true,
            language: {
                search: '',
                searchPlaceholder: 'Search...',
                lengthMenu: 'Show _MENU_ rows',
                info: 'Showing _START_-_END_ of _TOTAL_ entries',
                infoEmpty: 'No entries found',
                infoFiltered: '(filtered from _MAX_)',
                emptyTable: 'No data available',
                paginate: { previous: '<', next: '>' }
            },
            dom: '<"d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"lf>rtip'
        };

        <?php if ($isAdmin): ?>
        var $salaryOverview = $('#salaryOverviewTable');
        if ($salaryOverview.length && !$.fn.DataTable.isDataTable($salaryOverview)) {
            $salaryOverview.DataTable($.extend(true, {}, dtDefaults, {
                order: [[4, 'desc']],
                columnDefs: [
                    { targets: 0, width: '32%' },
                    { targets: 1, width: '12%' },
                    { targets: 2, width: '12%' },
                    { targets: [3, 4], width: '15%' },
                    { targets: 5, orderable: false, searchable: false, width: '14%' }
                ]
            }));
        }
        <?php else: ?>
        var $salaryHistory = $('#salaryHistoryTable');
        if ($salaryHistory.length && !$.fn.DataTable.isDataTable($salaryHistory)) {
            $salaryHistory.DataTable($.extend(true, {}, dtDefaults, {
                order: [[0, 'desc']],
                columnDefs: [
                    { targets: 0, width: '24%' },
                    { targets: [1, 2, 3, 4], width: '19%' }
                ],
                language: $.extend({}, dtDefaults.language, {
                    emptyTable: 'No saved payroll history yet'
                })
            }));
        }
        <?php endif; ?>
    }

    init();
})();
</script>

<?php
new_hrm_render_page_end();
