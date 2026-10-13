<?php
require_once __DIR__ . '/bootstrap.php';
hrm_require_admin();

$monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$selectedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$month = isset($_GET['month']) ? max(1, min(12, (int) $_GET['month'])) : (int) date('m');
$year = isset($_GET['year']) ? max(2020, min(2100, (int) $_GET['year'])) : (int) date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnUserId = isset($_POST['selected_user_id']) ? (int) $_POST['selected_user_id'] : 0;
    $returnMonth = isset($_POST['selected_month']) ? max(1, min(12, (int) $_POST['selected_month'])) : (int) date('m');
    $returnYear = isset($_POST['selected_year']) ? max(2020, min(2100, (int) $_POST['selected_year'])) : (int) date('Y');

    $userId = (int) ($_POST['employee_id'] ?? 0);
    $employee = hrm_fetch_employee($userId);
    if ($employee) {
        hrm_ensure_break_schema();

        $salaryType = ($_POST['salary_type'] ?? 'hourly') === 'monthly' ? 'monthly' : 'hourly';
        $basicSalary = (float) ($_POST['basic_salary'] ?? 0);
        $hourlyRate = (float) ($_POST['hourly_rate'] ?? 0);
        $allowance = (float) ($_POST['allowance_amount'] ?? 0);
        $deduction = (float) ($_POST['deduction_amount'] ?? 0);
        $currency = trim($_POST['currency'] ?? 'PKR') ?: 'PKR';
        $allowedBreak = max(0, (int) ($_POST['allowed_break_minutes'] ?? 60));
        $paidLeaveHours = max(0.0, (float) ($_POST['paid_leave_daily_hours'] ?? 8.0));

        $stmt = mysqli_prepare(
            $con,
            "INSERT INTO hrm_salary_settings
                (user_id, salary_type, basic_salary, hourly_rate, allowance_amount, deduction_amount, currency, allowed_break_minutes, paid_leave_daily_hours)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                salary_type = VALUES(salary_type),
                basic_salary = VALUES(basic_salary),
                hourly_rate = VALUES(hourly_rate),
                allowance_amount = VALUES(allowance_amount),
                deduction_amount = VALUES(deduction_amount),
                currency = VALUES(currency),
                allowed_break_minutes = VALUES(allowed_break_minutes),
                paid_leave_daily_hours = VALUES(paid_leave_daily_hours)"
        );

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                'isddddsid',
                $userId, $salaryType, $basicSalary, $hourlyRate, $allowance, $deduction, $currency, $allowedBreak, $paidLeaveHours
            );
            $ok = mysqli_stmt_execute($stmt);
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            hrm_flash($ok ? 'success' : 'error', $ok ? 'Salary settings updated.' : ('Save failed: ' . $err));
        } else {
            hrm_flash('error', 'Save failed: ' . mysqli_error($con));
        }
    } else {
        hrm_flash('error', 'Employee was not found.');
    }

    $redirectParams = [
        'month' => $returnMonth,
        'year' => $returnYear,
    ];
    if ($returnUserId > 0) {
        $redirectParams['user_id'] = $returnUserId;
    }

    header('Location: set_salary.php?' . http_build_query($redirectParams));
    exit();
}

$employees = hrm_fetch_employees(false);
$salaryRows = [];
$totalHours = 0.0;

foreach ($employees as $employee) {
    if ($selectedUserId > 0 && (int) $employee['user_id'] !== $selectedUserId) {
        continue;
    }

    $preview = hrm_salary_preview((int) $employee['user_id'], $month, $year);
    if ($preview) {
        $totalHours += (float) $preview['hours'];
    }

    $salaryRows[] = [
        'employee' => $employee,
        'preview' => $preview,
    ];
}

$monthName = $monthNames[$month - 1];

new_hrm_render_page_start(
    'Salary Settings',
    'Salary Settings',
    'Admins can set salary rates, allowances, and deductions for every employee.'
);
?>

<style>
.ss-filter-card {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    padding: 20px 22px 18px;
    margin-bottom: 20px;
    box-shadow: 0 4px 24px rgba(15, 23, 42, .06);
}

.ss-filter-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    color: var(--hrm-text);
    margin-bottom: 16px;
}

.ss-filter-title svg {
    width: 16px;
    height: 16px;
    stroke: var(--hrm-primary);
}

.ss-filter-grid {
    display: grid;
    gap: 12px;
    align-items: end;
    grid-template-columns: 2fr 1fr 1fr auto;
}

@media (max-width: 767px) {
    .ss-filter-grid {
        grid-template-columns: 1fr 1fr;
    }
}

.ss-field label {
    display: block;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--hrm-muted);
    margin-bottom: 5px;
}

.ss-field select,
.ss-field input[type="number"],
.ss-field input[type="text"] {
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

.ss-field select:focus,
.ss-field input[type="number"]:focus,
.ss-field input[type="text"]:focus {
    border-color: var(--hrm-primary);
    box-shadow: 0 0 0 3px rgba(20, 86, 184, .10);
    background: #fff;
}

.ss-submit-btn {
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
    display: inline-flex;
    align-items: center;
    gap: 6px;
    justify-content: center;
}

.ss-submit-btn:hover {
    opacity: .9;
    transform: translateY(-1px);
}

.ss-submit-btn svg {
    width: 14px;
    height: 14px;
    stroke: #fff;
}

.ss-filter-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
}

.ss-summary-pill,
.ss-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.ss-summary-pill.is-people {
    background: var(--hrm-primary-soft);
    color: var(--hrm-primary);
}

.ss-summary-pill.is-hours {
    background: #e8f7ee;
    color: #15803d;
}

.ss-summary-pill.is-period,
.ss-stat-pill.is-period {
    background: #f1ebff;
    color: #6d28d9;
}

.ss-panel {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(15, 23, 42, .06);
    margin-bottom: 20px;
}

.ss-panel-header {
    padding: 14px 22px;
    background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    border-bottom: 1px solid var(--hrm-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.ss-panel-employee {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ss-avatar {
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

.ss-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--hrm-text);
}

.ss-meta {
    font-size: 11px;
    color: var(--hrm-muted);
    margin-top: 1px;
}

.ss-panel-stats {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.ss-stat-pill.is-type {
    background: var(--hrm-primary-soft);
    color: var(--hrm-primary);
}

.ss-stat-pill.is-hours {
    background: #e8f7ee;
    color: #15803d;
}

.ss-stat-pill.is-net {
    background: #fff4df;
    color: #b45309;
}

.ss-panel-body {
    padding: 18px 20px;
}

.ss-settings-grid {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
    gap: 18px;
    align-items: start;
}

@media (max-width: 991px) {
    .ss-settings-grid {
        grid-template-columns: 1fr;
    }
}

.ss-form-card,
.ss-preview-card {
    border: 1px solid var(--hrm-border);
    border-radius: 14px;
    background: #fafbfd;
    padding: 18px;
}

.ss-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

@media (max-width: 767px) {
    .ss-form-grid {
        grid-template-columns: 1fr;
    }
}

.ss-form-note {
    margin-top: 12px;
    font-size: 12px;
    color: var(--hrm-muted);
}

.ss-form-actions {
    display: flex;
    justify-content: flex-start;
    margin-top: 18px;
}

.ss-preview-title {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--hrm-muted);
    margin-bottom: 12px;
}

.ss-preview-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ss-preview-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #edf2f7;
}

.ss-preview-row:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.ss-preview-label {
    font-size: 12px;
    color: var(--hrm-muted);
}

.ss-preview-value {
    font-size: 14px;
    font-weight: 800;
    color: var(--hrm-text);
    text-align: right;
}

.ss-preview-value.is-net {
    color: var(--hrm-primary);
}

.ss-empty {
    text-align: center;
    padding: 36px 20px;
    color: var(--hrm-muted);
    font-size: 13px;
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(15, 23, 42, .06);
}

.ss-empty svg {
    width: 36px;
    height: 36px;
    stroke: var(--hrm-border);
    margin-bottom: 10px;
    display: block;
    margin-inline: auto;
}
</style>

<div class="ss-filter-card">
    <div class="ss-filter-title">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
        </svg>
        Filter Salary Settings
    </div>

    <form method="get" action="new_hrm/set_salary.php">
        <div class="ss-filter-grid">
            <div class="ss-field">
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

            <div class="ss-field">
                <label>Month</label>
                <select name="month">
                    <?php foreach ($monthNames as $index => $label): ?>
                        <?php $value = $index + 1; ?>
                        <option value="<?php echo $value; ?>" <?php echo $month === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ss-field">
                <label>Year</label>
                <input type="number" name="year" min="2020" max="2100" value="<?php echo (int) $year; ?>" placeholder="2026">
            </div>

            <div>
                <button type="submit" class="ss-submit-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    Load
                </button>
            </div>
        </div>
    </form>

    <div class="ss-filter-summary">
        <span class="ss-summary-pill is-people"><?php echo count($salaryRows); ?> employee<?php echo count($salaryRows) === 1 ? '' : 's'; ?></span>
        <span class="ss-summary-pill is-hours"><?php echo hrm_e(number_format($totalHours, 2)); ?> hrs preview</span>
        <span class="ss-summary-pill is-period"><?php echo hrm_e($monthName . ' ' . $year); ?></span>
    </div>
</div>

<?php if (!$salaryRows): ?>
    <div class="ss-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5">
            <rect x="3" y="4" width="18" height="18" rx="2"/>
            <line x1="3" y1="10" x2="21" y2="10"/>
            <line x1="8" y1="2" x2="8" y2="6"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
        </svg>
        No employee records to display.
    </div>
<?php endif; ?>

<?php foreach ($salaryRows as $row): ?>
    <?php
    $employee = $row['employee'];
    $preview = $row['preview'];
    $name = $employee['full_name'] ?: $employee['user_name'];
    $salaryType = ($employee['salary_type'] ?? 'hourly') === 'monthly' ? 'monthly' : 'hourly';
    $currency = $employee['currency'] ?: ($preview['currency'] ?? 'PKR');
    $department = $employee['department_name'] ?: 'No department';
    $designation = $employee['designation_name'] ?: ($employee['user_desig'] ?: 'Salary settings');
    $hours = $preview ? number_format((float) $preview['hours'], 2) : '0.00';
    $minutes = $preview ? (int) $preview['minutes'] : 0;
    $grossSalary = $preview ? number_format((float) $preview['gross_salary'], 2) : '0.00';
    $allowance = number_format((float) ($employee['allowance_amount'] ?? ($preview['allowance'] ?? 0)), 2);
    $deduction = number_format((float) ($employee['deduction_amount'] ?? ($preview['deduction'] ?? 0)), 2);
    $netSalary = $preview ? number_format((float) $preview['net_salary'], 2) : '0.00';
    ?>
    <div class="ss-panel">
        <div class="ss-panel-header">
            <div class="ss-panel-employee">
                <div class="ss-avatar"><?php echo hrm_e(hrm_initials($name ?: 'U')); ?></div>
                <div>
                    <div class="ss-name"><?php echo hrm_e($name); ?></div>
                    <div class="ss-meta"><?php echo hrm_e($department . ' - ' . $designation); ?></div>
                </div>
            </div>

            <div class="ss-panel-stats">
                <span class="ss-stat-pill is-type"><?php echo hrm_e(ucfirst($salaryType)); ?></span>
                <span class="ss-stat-pill is-hours"><?php echo hrm_e($hours); ?> hrs</span>
                <span class="ss-stat-pill is-net"><?php echo hrm_e($currency . ' ' . $netSalary); ?></span>
                <span class="ss-stat-pill is-period"><?php echo hrm_e($monthName . ' ' . $year); ?></span>
            </div>
        </div>

        <div class="ss-panel-body">
            <div class="ss-settings-grid">
                <form method="post" action="new_hrm/set_salary.php" class="ss-form-card">
                    <input type="hidden" name="employee_id" value="<?php echo (int) $employee['user_id']; ?>">
                    <input type="hidden" name="selected_user_id" value="<?php echo (int) $selectedUserId; ?>">
                    <input type="hidden" name="selected_month" value="<?php echo (int) $month; ?>">
                    <input type="hidden" name="selected_year" value="<?php echo (int) $year; ?>">

                    <div class="ss-form-grid">
                        <div class="ss-field">
                            <label>Salary Type</label>
                            <select name="salary_type">
                                <option value="hourly" <?php echo $salaryType === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                                <option value="monthly" <?php echo $salaryType === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                        </div>

                        <div class="ss-field">
                            <label>Currency</label>
                            <input type="text" name="currency" value="<?php echo hrm_e($currency ?: 'PKR'); ?>">
                        </div>

                        <div class="ss-field">
                            <label>Basic Salary</label>
                            <input type="number" step="0.01" min="0" name="basic_salary" value="<?php echo hrm_e($employee['basic_salary'] ?? '0'); ?>">
                        </div>

                        <div class="ss-field">
                            <label>Hourly Rate</label>
                            <input type="number" step="0.01" min="0" name="hourly_rate" value="<?php echo hrm_e($employee['hourly_rate'] ?? '0'); ?>">
                        </div>

                        <div class="ss-field">
                            <label>Allowance</label>
                            <input type="number" step="0.01" name="allowance_amount" value="<?php echo hrm_e($employee['allowance_amount'] ?? '0'); ?>">
                        </div>

                        <div class="ss-field">
                            <label>Deduction</label>
                            <input type="number" step="0.01" name="deduction_amount" value="<?php echo hrm_e($employee['deduction_amount'] ?? '0'); ?>">
                        </div>

                        <div class="ss-field">
                            <label>Allowed Break (min/day)</label>
                            <input type="number" step="1" min="0" name="allowed_break_minutes" value="<?php echo hrm_e($employee['allowed_break_minutes'] ?? '60'); ?>">
                        </div>

                        <div class="ss-field">
                            <label>Paid Leave (hrs/day)</label>
                            <input type="number" step="0.5" min="0" name="paid_leave_daily_hours" value="<?php echo hrm_e($employee['paid_leave_daily_hours'] ?? '8'); ?>">
                        </div>
                    </div>

                    <div class="ss-form-note">
                        Preview values are calculated from attendance for <?php echo hrm_e($monthName . ' ' . $year); ?>.
                    </div>

                    <div class="ss-form-actions">
                        <button type="submit" class="ss-submit-btn">Save Salary Settings</button>
                    </div>
                </form>

                <div class="ss-preview-card">
                    <div class="ss-preview-title">Preview Summary</div>
                    <div class="ss-preview-list">
                        <div class="ss-preview-row">
                            <span class="ss-preview-label">Clocked-in minutes</span>
                            <strong class="ss-preview-value"><?php echo hrm_e(number_format($preview['working_minutes'] ?? $minutes)); ?> min</strong>
                        </div>
                        <div class="ss-preview-row">
                            <span class="ss-preview-label">Break minutes</span>
                            <strong class="ss-preview-value"><?php echo hrm_e(number_format($preview['break_minutes'] ?? 0)); ?> min</strong>
                        </div>
                        <div class="ss-preview-row">
                            <span class="ss-preview-label">Break deducted (overage)</span>
                            <strong class="ss-preview-value"><?php echo hrm_e(number_format($preview['deducted_break_minutes'] ?? 0)); ?> min</strong>
                        </div>
                        <div class="ss-preview-row">
                            <span class="ss-preview-label">Paid leave credit</span>
                            <strong class="ss-preview-value"><?php echo hrm_e(number_format($preview['paid_leave_minutes'] ?? 0)); ?> min</strong>
                        </div>
                        <div class="ss-preview-row">
                            <span class="ss-preview-label">Paid hours</span>
                            <strong class="ss-preview-value"><?php echo hrm_e($hours); ?> hrs</strong>
                        </div>
                        <div class="ss-preview-row">
                            <span class="ss-preview-label">Gross salary</span>
                            <strong class="ss-preview-value"><?php echo hrm_e($currency . ' ' . $grossSalary); ?></strong>
                        </div>
                        <div class="ss-preview-row">
                            <span class="ss-preview-label">Allowance</span>
                            <strong class="ss-preview-value"><?php echo hrm_e($currency . ' ' . $allowance); ?></strong>
                        </div>
                        <div class="ss-preview-row">
                            <span class="ss-preview-label">Deduction</span>
                            <strong class="ss-preview-value"><?php echo hrm_e($currency . ' ' . $deduction); ?></strong>
                        </div>
                        <div class="ss-preview-row">
                            <span class="ss-preview-label">Net salary</span>
                            <strong class="ss-preview-value is-net"><?php echo hrm_e($currency . ' ' . $netSalary); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php
new_hrm_render_page_end();
?>
