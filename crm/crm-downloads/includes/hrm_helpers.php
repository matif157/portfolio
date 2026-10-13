<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/activity_log.php';

function hrm_root_path()
{
    return dirname(__DIR__);
}

function hrm_session_user_id()
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
}

function hrm_is_admin()
{
    return isset($_SESSION['user_type']) && (string) $_SESSION['user_type'] === '1';
}

function hrm_require_admin()
{
    if (!hrm_is_admin()) {
        header('Location: index.php');
        exit();
    }
}

function hrm_e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function hrm_query_value($key, $default = '')
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

function hrm_display_name($employee)
{
    return $employee['full_name'] ?? $employee['user_name'] ?? 'Unknown';
}

function hrm_display_code($employee)
{
    return $employee['hrm_employee_code'] ?: ($employee['device_employee_code'] ?: '-');
}

function hrm_format_money($amount, $currency = 'PKR')
{
    return hrm_e($currency . ' ' . number_format((float) $amount, 2));
}

function hrm_format_hours($hours)
{
    return hrm_e(number_format((float) $hours, 2)) . ' hrs';
}

function hrm_format_date($value)
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y', strtotime($value));
}

function hrm_format_datetime($value)
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y h:i A', strtotime($value));
}

function hrm_badge_class($status)
{
    $status = strtolower((string) $status);
    return match ($status) {
        'active', 'approved', 'check in', 'present' => 'is-success',
        'inactive', 'rejected', 'check out' => 'is-danger',
        'on_leave', 'pending' => 'is-warning',
        'admin' => 'is-accent',
        default => 'is-neutral',
    };
}

function hrm_filter_employees($employees, $filters = [])
{
    $search = strtolower(trim((string) ($filters['search'] ?? '')));
    $departmentId = (int) ($filters['department_id'] ?? 0);
    $status = trim((string) ($filters['status'] ?? ''));
    $role = trim((string) ($filters['role'] ?? ''));

    return array_values(array_filter($employees, function ($employee) use ($search, $departmentId, $status, $role) {
        if ($search !== '') {
            $haystack = strtolower(implode(' ', [
                $employee['full_name'] ?? '',
                $employee['user_name'] ?? '',
                $employee['user_email'] ?? '',
                $employee['user_login'] ?? '',
                $employee['department_name'] ?? '',
                $employee['designation_name'] ?? '',
                $employee['hrm_employee_code'] ?? '',
                $employee['device_employee_code'] ?? '',
            ]));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }

        if ($departmentId > 0 && (int) ($employee['department_id'] ?? 0) !== $departmentId) {
            return false;
        }

        if ($status !== '' && ($employee['hrm_status'] ?? 'active') !== $status) {
            return false;
        }

        if ($role !== '' && hrm_selected_role($employee) !== $role) {
            return false;
        }

        return true;
    }));
}

function hrm_employee_stats($employees)
{
    $stats = [
        'total' => count($employees),
        'active' => 0,
        'inactive' => 0,
        'on_leave' => 0,
        'admins' => 0,
        'employees' => 0,
        'departments' => [],
        'salary_ready' => 0,
    ];

    foreach ($employees as $employee) {
        $status = $employee['hrm_status'] ?? 'active';
        if (isset($stats[$status])) {
            $stats[$status]++;
        }

        $role = hrm_selected_role($employee);
        if ($role === 'admin') {
            $stats['admins']++;
        } else {
            $stats['employees']++;
        }

        $departmentName = trim((string) ($employee['department_name'] ?? 'Unassigned'));
        $stats['departments'][$departmentName] = ($stats['departments'][$departmentName] ?? 0) + 1;

        if ((float) ($employee['hourly_rate'] ?? 0) > 0 || (float) ($employee['basic_salary'] ?? 0) > 0) {
            $stats['salary_ready']++;
        }
    }

    arsort($stats['departments']);

    return $stats;
}

function hrm_payroll_summary($previews)
{
    $summary = [
        'employees' => 0,
        'total_minutes' => 0,
        'total_hours' => 0,
        'gross_total' => 0,
        'net_total' => 0,
        'avg_net' => 0,
    ];

    foreach ($previews as $preview) {
        if (!$preview) {
            continue;
        }
        $summary['employees']++;
        $summary['total_minutes'] += (int) $preview['minutes'];
        $summary['gross_total'] += (float) $preview['gross_salary'];
        $summary['net_total'] += (float) $preview['net_salary'];
    }

    $summary['total_hours'] = round($summary['total_minutes'] / 60, 2);
    $summary['avg_net'] = $summary['employees'] > 0 ? round($summary['net_total'] / $summary['employees'], 2) : 0;

    return $summary;
}

function hrm_attendance_metrics($summaryRows)
{
    $metrics = [
        'days' => 0,
        'minutes' => 0,
        'hours' => 0,
        'avg_day_hours' => 0,
    ];

    foreach ($summaryRows as $row) {
        $metrics['days']++;
        $metrics['minutes'] += (int) ($row['working_minutes'] ?? 0);
    }

    $metrics['hours'] = round($metrics['minutes'] / 60, 2);
    $metrics['avg_day_hours'] = $metrics['days'] > 0 ? round($metrics['hours'] / $metrics['days'], 2) : 0;

    return $metrics;
}

function hrm_initials($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return 'NA';
    }

    $parts = preg_split('/\s+/', $name);
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }

    return substr($initials, 0, 4);
}

function hrm_flash($type, $message)
{
    $_SESSION['hrm_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function hrm_consume_flash()
{
    if (!isset($_SESSION['hrm_flash'])) {
        return null;
    }

    $flash = $_SESSION['hrm_flash'];
    unset($_SESSION['hrm_flash']);

    return $flash;
}

function hrm_fetch_all($sql)
{
    global $con;

    $rows = [];
    $result = mysqli_query($con, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function hrm_fetch_departments()
{
    return hrm_fetch_all("SELECT department_id, name FROM hrm_departments WHERE status='active' ORDER BY name");
}

function hrm_fetch_designations()
{
    return hrm_fetch_all("SELECT designation_id, department_id, name FROM hrm_designations ORDER BY name");
}

function hrm_employee_base_query()
{
    return "
        SELECT
            u.user_id,
            u.user_login,
            u.user_password,
            u.user_name,
            u.user_email,
            u.user_desig,
            u.user_pic,
            u.user_type,
            u.user_login_status,
            u.user_ban_status,
            hu.full_name,
            hu.phone,
            hu.role,
            hu.department_id,
            hu.designation_id,
            hu.employee_code AS hrm_employee_code,
            hu.join_date,
            hu.status AS hrm_status,
            hu.avatar,
            d.name AS department_name,
            ds.name AS designation_name,
            he.hrm_employee_id,
            he.device_user_id,
            he.device_uid,
            he.employee_code AS device_employee_code,
            he.status AS employee_status,
            ss.salary_id,
            ss.salary_type,
            ss.basic_salary,
            ss.hourly_rate,
            ss.allowance_amount,
            ss.deduction_amount,
            ss.currency
        FROM users u
        LEFT JOIN hrm_users hu ON hu.user_id = u.user_id
        LEFT JOIN hrm_departments d ON d.department_id = hu.department_id
        LEFT JOIN hrm_designations ds ON ds.designation_id = hu.designation_id
        LEFT JOIN hrm_employees he ON he.user_id = u.user_id
        LEFT JOIN hrm_salary_settings ss ON ss.user_id = u.user_id
        WHERE u.user_ban_status = 0
    ";
}

function hrm_fetch_employees($includeAdmins = true)
{
    $sql = hrm_employee_base_query();
    if (!$includeAdmins) {
        $sql .= " AND u.user_type <> 1";
    }
    $sql .= " ORDER BY COALESCE(hu.full_name, u.user_name) ASC";

    return hrm_fetch_all($sql);
}

function hrm_fetch_employee($userId)
{
    global $con;

    $userId = (int) $userId;
    $sql = hrm_employee_base_query() . " AND u.user_id = {$userId} LIMIT 1";
    $result = mysqli_query($con, $sql);

    return $result ? mysqli_fetch_assoc($result) : null;
}

function hrm_selected_role($employee)
{
    if (!$employee) {
        return 'employee';
    }

    if ((int) ($employee['user_type'] ?? 0) === 1 || ($employee['role'] ?? '') === 'admin') {
        return 'admin';
    }

    return 'employee';
}

function hrm_save_employee($data, $existingUserId = null)
{
    global $con;

    $existingUserId = $existingUserId ? (int) $existingUserId : null;
    $fullName = trim($data['full_name'] ?? '');
    $username = trim($data['user_login'] ?? '');
    $email = trim($data['user_email'] ?? '');
    $password = trim($data['user_password'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $designationText = trim($data['user_desig'] ?? '');
    $departmentId = (int) ($data['department_id'] ?? 0);
    $designationId = (int) ($data['designation_id'] ?? 0);
    $joinDate = trim($data['join_date'] ?? '') ?: date('Y-m-d');
    $status = in_array(($data['status'] ?? 'active'), ['active', 'inactive', 'on_leave'], true) ? $data['status'] : 'active';
    $employeeCode = trim($data['employee_code'] ?? '');
    $deviceUserId = trim($data['device_user_id'] ?? '');
    $role = ($data['role'] ?? 'employee') === 'admin' ? 'admin' : 'employee';
    $userType = $role === 'admin' ? 1 : 3;
    $salaryType = ($data['salary_type'] ?? 'hourly') === 'monthly' ? 'monthly' : 'hourly';
    $basicSalary = (float) ($data['basic_salary'] ?? 0);
    $hourlyRate = (float) ($data['hourly_rate'] ?? 0);
    $allowanceAmount = (float) ($data['allowance_amount'] ?? 0);
    $deductionAmount = (float) ($data['deduction_amount'] ?? 0);
    $currency = trim($data['currency'] ?? 'PKR') ?: 'PKR';

    if ($fullName === '' || $username === '' || $email === '') {
        return ['ok' => false, 'message' => 'Full name, username, and email are required.'];
    }

    if ($existingUserId === null && $password === '') {
        return ['ok' => false, 'message' => 'Password is required for a new employee.'];
    }

    mysqli_begin_transaction($con);

    try {
        $safeUsername = mysqli_real_escape_string($con, $username);
        $safeEmail = mysqli_real_escape_string($con, $email);
        $duplicateSql = "SELECT user_id FROM users WHERE (user_login = '{$safeUsername}' OR user_email = '{$safeEmail}')";
        if ($existingUserId !== null) {
            $duplicateSql .= " AND user_id <> {$existingUserId}";
        }
        $duplicateSql .= " LIMIT 1";

        $duplicateCheck = mysqli_query($con, $duplicateSql);
        if ($duplicateCheck && mysqli_num_rows($duplicateCheck) > 0) {
            mysqli_rollback($con);
            return ['ok' => false, 'message' => 'Username or email is already in use.'];
        }

        if ($existingUserId === null) {
            $stmt = mysqli_prepare(
                $con,
                "INSERT INTO users (user_login, user_password, user_name, user_email, user_desig, user_pic, user_type, user_login_status, user_ban_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0)"
            );
            $defaultPic = 'user-8.jpg';
            mysqli_stmt_bind_param($stmt, 'ssssssi', $username, $password, $fullName, $email, $designationText, $defaultPic, $userType);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $userId = (int) mysqli_insert_id($con);
        } else {
            $userId = $existingUserId;
            if ($password !== '') {
                $stmt = mysqli_prepare(
                    $con,
                    "UPDATE users
                     SET user_login = ?, user_password = ?, user_name = ?, user_email = ?, user_desig = ?, user_type = ?
                     WHERE user_id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'sssssii', $username, $password, $fullName, $email, $designationText, $userType, $userId);
            } else {
                $stmt = mysqli_prepare(
                    $con,
                    "UPDATE users
                     SET user_login = ?, user_name = ?, user_email = ?, user_desig = ?, user_type = ?
                     WHERE user_id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'ssssii', $username, $fullName, $email, $designationText, $userType, $userId);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        if ($employeeCode === '') {
            $employeeCode = 'EMP-' . str_pad((string) $userId, 4, '0', STR_PAD_LEFT);
        }
        if ($deviceUserId === '') {
            $deviceUserId = $employeeCode;
        }

        $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
        if ($passwordHash === null) {
            $existingHashResult = mysqli_query($con, "SELECT password_hash FROM hrm_users WHERE user_id = {$userId} LIMIT 1");
            $existingHashRow = $existingHashResult ? mysqli_fetch_assoc($existingHashResult) : null;
            $passwordHash = $existingHashRow['password_hash'] ?? password_hash($username, PASSWORD_DEFAULT);
        }

        $stmt = mysqli_prepare(
            $con,
            "INSERT INTO hrm_users
                (user_id, full_name, initials, email, phone, password_hash, role, department_id, designation_id, employee_code, join_date, status, avatar)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                initials = VALUES(initials),
                email = VALUES(email),
                phone = VALUES(phone),
                password_hash = VALUES(password_hash),
                role = VALUES(role),
                department_id = VALUES(department_id),
                designation_id = VALUES(designation_id),
                employee_code = VALUES(employee_code),
                join_date = VALUES(join_date),
                status = VALUES(status),
                avatar = VALUES(avatar)"
        );
        $initials = hrm_initials($fullName);
        $avatar = null;
        mysqli_stmt_bind_param(
            $stmt,
            'issssssiissss',
            $userId,
            $fullName,
            $initials,
            $email,
            $phone,
            $passwordHash,
            $role,
            $departmentId,
            $designationId,
            $employeeCode,
            $joinDate,
            $status,
            $avatar
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $employeeStatus = $status === 'inactive' ? 'inactive' : 'active';
        $stmt = mysqli_prepare(
            $con,
            "INSERT INTO hrm_employees (user_id, device_user_id, device_uid, employee_code, status, employee_name)
             VALUES (?, ?, NULL, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                device_user_id = VALUES(device_user_id),
                employee_code = VALUES(employee_code),
                status = VALUES(status),
                employee_name = VALUES(employee_name)"
        );
        mysqli_stmt_bind_param($stmt, 'issss', $userId, $deviceUserId, $employeeCode, $employeeStatus, $fullName);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare(
            $con,
            "INSERT INTO hrm_salary_settings (user_id, salary_type, basic_salary, hourly_rate, allowance_amount, deduction_amount, currency)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                salary_type = VALUES(salary_type),
                basic_salary = VALUES(basic_salary),
                hourly_rate = VALUES(hourly_rate),
                allowance_amount = VALUES(allowance_amount),
                deduction_amount = VALUES(deduction_amount),
                currency = VALUES(currency)"
        );
        mysqli_stmt_bind_param($stmt, 'isdddds', $userId, $salaryType, $basicSalary, $hourlyRate, $allowanceAmount, $deductionAmount, $currency);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($con);

        logActivity(
            $con,
            hrm_session_user_id(),
            $existingUserId === null ? 'CREATE' : 'UPDATE',
            'hrm_employee',
            $userId,
            ($existingUserId === null ? 'Added' : 'Updated') . " employee {$fullName}"
        );

        return ['ok' => true, 'user_id' => $userId];
    } catch (Throwable $e) {
        mysqli_rollback($con);
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function hrm_delete_employee($userId)
{
    global $con;

    $userId = (int) $userId;
    if ($userId === hrm_session_user_id()) {
        return ['ok' => false, 'message' => 'You cannot delete your own account.'];
    }

    $employee = hrm_fetch_employee($userId);
    if (!$employee) {
        return ['ok' => false, 'message' => 'Employee record not found.'];
    }

    mysqli_begin_transaction($con);

    try {
        mysqli_query($con, "DELETE FROM hrm_payroll WHERE user_id = {$userId}");
        mysqli_query($con, "DELETE FROM hrm_salary_settings WHERE user_id = {$userId}");
        mysqli_query($con, "DELETE FROM hrm_attendance_logs WHERE user_id = {$userId}");
        mysqli_query($con, "DELETE FROM hrm_employees WHERE user_id = {$userId}");
        mysqli_query($con, "DELETE FROM hrm_users WHERE user_id = {$userId}");
        mysqli_query($con, "DELETE FROM users WHERE user_id = {$userId}");

        mysqli_commit($con);

        logActivity(
            $con,
            hrm_session_user_id(),
            'DELETE',
            'hrm_employee',
            $userId,
            'Deleted employee ' . ($employee['full_name'] ?: $employee['user_name'])
        );

        return ['ok' => true];
    } catch (Throwable $e) {
        mysqli_rollback($con);
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function hrm_today_attendance_status($userId)
{
    global $con;

    $userId = (int) $userId;
    $rows = [];
    $stmt = mysqli_prepare(
        $con,
        "SELECT log_id, log_time, state
         FROM hrm_attendance_logs
         WHERE user_id = ? AND DATE(log_time) = CURDATE()
         ORDER BY log_time ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    $inCount = 0;
    $outCount = 0;
    $arrival = null;
    $departure = null;

    foreach ($rows as $row) {
        if ((int) $row['state'] === 0) {
            $inCount++;
            if ($arrival === null) {
                $arrival = $row['log_time'];
            }
        } else {
            $outCount++;
            $departure = $row['log_time'];
        }
    }

    return [
        'arrival' => $arrival,
        'departure' => $departure,
        'rows' => $rows,
        'can_check_in' => $inCount <= $outCount,
        'can_check_out' => $inCount > $outCount,
    ];
}

function hrm_record_attendance($userId, $mode)
{
    global $con;

    $userId = (int) $userId;
    $employee = hrm_fetch_employee($userId);
    if (!$employee) {
        return ['ok' => false, 'message' => 'Employee profile is missing.'];
    }

    $status = hrm_today_attendance_status($userId);
    if ($mode === 'in' && !$status['can_check_in']) {
        return ['ok' => false, 'message' => 'You are already checked in for today.'];
    }
    if ($mode === 'out' && !$status['can_check_out']) {
        return ['ok' => false, 'message' => 'You need to check in first.'];
    }

    $state = $mode === 'out' ? 1 : 0;
    $deviceUserId = $employee['device_user_id'] ?: ($employee['hrm_employee_code'] ?: (string) $userId);

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO hrm_attendance_logs (device_id, device_user_id, device_uid, user_id, log_time, state, source)
         VALUES (NULL, ?, NULL, ?, NOW(), ?, 'manual')"
    );
    mysqli_stmt_bind_param($stmt, 'sii', $deviceUserId, $userId, $state);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    logActivity(
        $con,
        $userId,
        'UPDATE',
        'attendance',
        $userId,
        ($mode === 'out' ? 'Checked out' : 'Checked in') . ' manually'
    );

    return ['ok' => true];
}

function hrm_fetch_attendance_logs($userId = null, $startDate = null, $endDate = null)
{
    global $con;

    $where = ["1=1"];
    if ($userId !== null) {
        $where[] = 'l.user_id = ' . (int) $userId;
    }
    if ($startDate) {
        $safeStart = mysqli_real_escape_string($con, $startDate);
        $where[] = "DATE(l.log_time) >= '{$safeStart}'";
    }
    if ($endDate) {
        $safeEnd = mysqli_real_escape_string($con, $endDate);
        $where[] = "DATE(l.log_time) <= '{$safeEnd}'";
    }

    $sql = "
        SELECT
            l.log_id,
            l.user_id,
            l.device_user_id,
            l.log_time,
            l.state,
            l.source,
            u.user_name
        FROM hrm_attendance_logs l
        LEFT JOIN users u ON u.user_id = l.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY l.log_time DESC
    ";

    return hrm_fetch_all($sql);
}

function hrm_attendance_summary($userId, $startDate, $endDate)
{
    global $con;

    $rows = [];
    $totalMinutes = 0;
    $stmt = mysqli_prepare(
        $con,
        "SELECT
            DATE(log_time) AS work_date,
            MIN(CASE WHEN state = 0 THEN log_time END) AS check_in,
            MAX(CASE WHEN state = 1 THEN log_time END) AS check_out
         FROM hrm_attendance_logs
         WHERE user_id = ? AND DATE(log_time) BETWEEN ? AND ?
         GROUP BY DATE(log_time)
         ORDER BY work_date DESC"
    );
    mysqli_stmt_bind_param($stmt, 'iss', $userId, $startDate, $endDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $minutes = 0;
        if (!empty($row['check_in']) && !empty($row['check_out'])) {
            $minutes = max(0, (int) round((strtotime($row['check_out']) - strtotime($row['check_in'])) / 60));
        }
        $row['working_minutes'] = $minutes;
        $totalMinutes += $minutes;
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    return [
        'rows' => $rows,
        'total_minutes' => $totalMinutes,
        'total_hours' => round($totalMinutes / 60, 2),
    ];
}

function hrm_salary_preview($userId, $month, $year)
{
    $employee = hrm_fetch_employee($userId);
    if (!$employee) {
        return null;
    }

    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate));
    $attendance = hrm_attendance_summary($userId, $startDate, $endDate);
    $minutes = $attendance['total_minutes'];
    $hours = $attendance['total_hours'];
    $salaryType = $employee['salary_type'] ?: 'hourly';
    $basicSalary = (float) ($employee['basic_salary'] ?? 0);
    $hourlyRate = (float) ($employee['hourly_rate'] ?? 0);
    $allowance = (float) ($employee['allowance_amount'] ?? 0);
    $deduction = (float) ($employee['deduction_amount'] ?? 0);

    $perMinuteRate = 0;
    if ($salaryType === 'monthly') {
        $monthlyMinutes = 26 * 8 * 60;
        $perMinuteRate = $monthlyMinutes > 0 ? $basicSalary / $monthlyMinutes : 0;
    } else {
        $perMinuteRate = $hourlyRate / 60;
    }

    $gross = round($minutes * $perMinuteRate, 2);
    $net = round($gross + $allowance - $deduction, 2);

    return [
        'employee' => $employee,
        'attendance' => $attendance,
        'pay_month' => (int) $month,
        'pay_year' => (int) $year,
        'gross_salary' => $gross,
        'net_salary' => $net,
        'minutes' => $minutes,
        'hours' => $hours,
        'allowance' => $allowance,
        'deduction' => $deduction,
        'basic_salary' => $basicSalary,
        'hourly_rate' => $hourlyRate,
        'currency' => $employee['currency'] ?: 'PKR',
        'salary_type' => $salaryType,
    ];
}

function hrm_save_payroll_snapshot($month, $year)
{
    global $con;

    $employees = hrm_fetch_employees(false);
    $saved = 0;

    foreach ($employees as $employee) {
        $preview = hrm_salary_preview((int) $employee['user_id'], $month, $year);
        if (!$preview) {
            continue;
        }

        $existingPayrollId = 0;
        $checkStmt = mysqli_prepare(
            $con,
            "SELECT payroll_id FROM hrm_payroll WHERE user_id = ? AND pay_month = ? AND pay_year = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($checkStmt, 'iii', $employee['user_id'], $month, $year);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        if ($checkRow = mysqli_fetch_assoc($checkResult)) {
            $existingPayrollId = (int) $checkRow['payroll_id'];
        }
        mysqli_stmt_close($checkStmt);

        if ($existingPayrollId > 0) {
            $stmt = mysqli_prepare(
                $con,
                "UPDATE hrm_payroll
                 SET basic_salary = ?, allowance = ?, deduction = ?, net_salary = ?, status = 'approved', processed_at = NOW()
                 WHERE payroll_id = ?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'ddddi',
                $preview['gross_salary'],
                $preview['allowance'],
                $preview['deduction'],
                $preview['net_salary'],
                $existingPayrollId
            );
        } else {
            $stmt = mysqli_prepare(
                $con,
                "INSERT INTO hrm_payroll (user_id, pay_month, pay_year, basic_salary, allowance, deduction, net_salary, status, processed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', NOW())"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'iiidddd',
                $employee['user_id'],
                $month,
                $year,
                $preview['gross_salary'],
                $preview['allowance'],
                $preview['deduction'],
                $preview['net_salary']
            );
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $saved++;
    }

    logActivity(
        $con,
        hrm_session_user_id(),
        'UPDATE',
        'hrm_payroll',
        null,
        "Generated payroll for {$month}/{$year}"
    );

    return $saved;
}

function hrm_fetch_payroll_history($userId = null, $limit = 12)
{
    global $con;

    $where = $userId !== null ? 'WHERE p.user_id = ' . (int) $userId : '';
    $limit = (int) $limit;

    $sql = "
        SELECT
            p.*,
            u.user_name
        FROM hrm_payroll p
        LEFT JOIN users u ON u.user_id = p.user_id
        {$where}
        ORDER BY p.pay_year DESC, p.pay_month DESC, p.payroll_id DESC
        LIMIT {$limit}
    ";

    return hrm_fetch_all($sql);
}

function hrm_fetch_dashboard_stats()
{
    global $con;

    $stats = [
        'employee_count' => 0,
        'today_checkins' => 0,
        'today_checkouts' => 0,
        'open_attendance' => 0,
    ];

    $result = mysqli_query($con, "SELECT COUNT(*) AS total FROM users WHERE user_ban_status = 0 AND user_type <> 1");
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        $stats['employee_count'] = (int) $row['total'];
    }

    $result = mysqli_query($con, "SELECT COUNT(*) AS total FROM hrm_attendance_logs WHERE DATE(log_time) = CURDATE() AND state = 0");
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        $stats['today_checkins'] = (int) $row['total'];
    }

    $result = mysqli_query($con, "SELECT COUNT(*) AS total FROM hrm_attendance_logs WHERE DATE(log_time) = CURDATE() AND state = 1");
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        $stats['today_checkouts'] = (int) $row['total'];
    }

    $result = mysqli_query(
        $con,
        "SELECT COUNT(*) AS total FROM (
            SELECT user_id
            FROM hrm_attendance_logs
            WHERE DATE(log_time) = CURDATE()
            GROUP BY user_id
            HAVING SUM(state = 0) > SUM(state = 1)
        ) open_logs"
    );
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        $stats['open_attendance'] = (int) $row['total'];
    }

    return $stats;
}

function hrm_render_page_start($title, $heading, $description = '')
{
    $root = hrm_root_path();
    chdir($root);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo hrm_e($title); ?></title>
    <base href="../">
    <?php include $root . '/includes/links.php'; ?>
    <style>
        :root {
            --hrm-bg: #f4f7fb;
            --hrm-surface: #ffffff;
            --hrm-surface-soft: #f8fafc;
            --hrm-border: #d9e2ec;
            --hrm-border-strong: #c5d1de;
            --hrm-text: #152334;
            --hrm-muted: #64748b;
            --hrm-primary: #1456b8;
            --hrm-primary-soft: #e8f1ff;
            --hrm-success: #15803d;
            --hrm-success-soft: #e8f7ee;
            --hrm-warning: #b45309;
            --hrm-warning-soft: #fff4df;
            --hrm-danger: #b42318;
            --hrm-danger-soft: #feeceb;
            --hrm-accent: #6d28d9;
            --hrm-accent-soft: #f1ebff;
            --hrm-shadow: 0 14px 32px rgba(15, 23, 42, 0.06);
        }
        body.theme-3 {
            background: linear-gradient(180deg, #f9fbfd 0%, var(--hrm-bg) 100%);
        }
        .dash-content {
            padding-bottom: 24px;
        }
        .hrm-page-hero {
            border: 1px solid var(--hrm-border);
            border-radius: 14px;
            background: linear-gradient(135deg, #ffffff 0%, #f7faff 55%, #eef4ff 100%);
            box-shadow: var(--hrm-shadow);
            overflow: hidden;
            position: relative;
        }
        .hrm-page-hero::after {
            content: "";
            position: absolute;
            inset: 0 0 auto auto;
            width: 280px;
            height: 280px;
            background: radial-gradient(circle, rgba(20, 86, 184, 0.16) 0%, rgba(20, 86, 184, 0) 72%);
            pointer-events: none;
        }
        .hrm-page-hero .card-body {
            padding: 24px 28px;
            position: relative;
            z-index: 1;
        }
        .hrm-page-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--hrm-primary-soft);
            color: var(--hrm-primary);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .hrm-page-summary {
            max-width: 760px;
            color: var(--hrm-muted);
            font-size: 14px;
            margin: 10px 0 0;
        }
        .hrm-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }
        .hrm-metric-card {
            border: 1px solid var(--hrm-border);
            border-radius: 12px;
            background: linear-gradient(180deg, var(--hrm-surface) 0%, var(--hrm-surface-soft) 100%);
            padding: 18px 18px 16px;
            height: 100%;
            box-shadow: var(--hrm-shadow);
        }
        .hrm-metric-label {
            color: var(--hrm-muted);
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-weight: 700;
        }
        .hrm-metric-value {
            font-size: 30px;
            font-weight: 700;
            color: var(--hrm-text);
        }
        .hrm-section-card {
            border: 1px solid var(--hrm-border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--hrm-shadow);
            background: var(--hrm-surface);
        }
        .hrm-section-card .card-header {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-bottom: 1px solid var(--hrm-border);
            padding: 16px 20px;
        }
        .hrm-section-card .card-body {
            padding: 18px 20px;
        }
        .hrm-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--hrm-primary-soft);
            color: var(--hrm-primary);
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .hrm-mini-label {
            color: var(--hrm-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 700;
        }
        .hrm-table td,
        .hrm-table th {
            vertical-align: middle;
        }
        .hrm-table thead th {
            border-bottom: 1px solid var(--hrm-border-strong);
            background: #f8fafc;
            color: var(--hrm-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-weight: 700;
        }
        .hrm-table tbody tr:hover {
            background: #f8fbff;
        }
        .hrm-filter-bar {
            border: 1px solid var(--hrm-border);
            border-radius: 12px;
            padding: 16px 18px 4px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            box-shadow: var(--hrm-shadow);
        }
        .hrm-stack {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .hrm-kv {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .hrm-kv-item {
            border: 1px solid var(--hrm-border);
            border-radius: 10px;
            padding: 14px 16px;
            background: var(--hrm-surface-soft);
        }
        .hrm-inline-stat {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #edf2f7;
        }
        .hrm-inline-stat:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .hrm-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
        }
        .hrm-badge.is-success { background: var(--hrm-success-soft); color: var(--hrm-success); }
        .hrm-badge.is-warning { background: var(--hrm-warning-soft); color: var(--hrm-warning); }
        .hrm-badge.is-danger { background: var(--hrm-danger-soft); color: var(--hrm-danger); }
        .hrm-badge.is-accent { background: var(--hrm-accent-soft); color: var(--hrm-accent); }
        .hrm-badge.is-neutral { background: #eef2f6; color: #475569; }
        .hrm-table-note {
            color: var(--hrm-muted);
            font-size: 12px;
        }
        .hrm-toolbar-title {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .hrm-toolbar-title h5 {
            margin: 0;
            color: var(--hrm-text);
        }
        .hrm-toolbar-title p {
            margin: 0;
            color: var(--hrm-muted);
            font-size: 13px;
        }
        .hrm-list-card {
            border: 1px solid var(--hrm-border);
            border-radius: 10px;
            padding: 14px 16px;
            background: #fff;
        }
        .hrm-list-card + .hrm-list-card {
            margin-top: 12px;
        }
        .hrm-grid-2 {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 18px;
        }
        .hrm-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }
        .hrm-form-section {
            border-top: 1px solid var(--hrm-border);
            margin-top: 18px;
            padding-top: 18px;
        }
        .hrm-empty {
            padding: 24px;
            text-align: center;
            border: 1px dashed var(--hrm-border-strong);
            border-radius: 12px;
            background: #fbfcfe;
            color: var(--hrm-muted);
        }
        .hrm-subtle {
            color: var(--hrm-muted);
        }
        @media (max-width: 991px) {
            .hrm-grid-2,
            .hrm-grid-3 {
                grid-template-columns: 1fr;
            }
            .hrm-actions {
                justify-content: flex-start;
                margin-top: 16px;
            }
        }
    </style>
</head>
<body class="theme-3">
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>

    <?php include $root . '/includes/sidebar.php'; ?>
    <?php include $root . '/includes/header.php'; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="card hrm-page-hero">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <span class="hrm-page-eyebrow">Workforce Operations</span>
                            <div class="page-header-title mt-3">
                                <h4 class="m-b-10"><?php echo hrm_e($heading); ?></h4>
                            </div>
                            <?php if ($description !== ''): ?>
                                <p class="hrm-page-summary"><?php echo hrm_e($description); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-lg-4">
                            <div class="hrm-actions">
                                <a href="new_hrm/index.php" class="btn btn-light">Dashboard</a>
                                <a href="new_hrm/employees.php" class="btn btn-light">People</a>
                                <a href="new_hrm/attendance_logs.php" class="btn btn-light">Attendance</a>
                                <?php if (hrm_is_admin()): ?>
                                    <a href="new_hrm/payroll.php" class="btn btn-primary">Payroll</a>
                                <?php else: ?>
                                    <a href="new_hrm/salary.php" class="btn btn-primary">My Salary</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            $flash = hrm_consume_flash();
            if ($flash):
                $alertClass = $flash['type'] === 'success' ? 'alert-success' : 'alert-danger';
                ?>
                <div class="alert <?php echo $alertClass; ?>"><?php echo hrm_e($flash['message']); ?></div>
            <?php endif; ?>
    <?php
}

function hrm_render_page_end()
{
    $root = hrm_root_path();
    ?>
        </div>
    </div>
    <?php include $root . '/includes/footer.php'; ?>
    <?php include $root . '/includes/scripts.php'; ?>
</body>
</html>
    <?php
}
