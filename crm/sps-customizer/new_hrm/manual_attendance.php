<?php
/**
 * NEW HRM - Manual Attendance Entry
 * For PC Downtime Situations
 * Admin can edit any employee, Users can edit only themselves
 * Edit & Delete via Modal Dialogs
 */

require_once __DIR__ . '/bootstrap.php';

global $con;

$isAdmin = hrm_is_admin();
$userId = hrm_session_user_id();
$targetUserId = $isAdmin && isset($_GET['user_id']) ? (int)$_GET['user_id'] : $userId;
$message = '';
$error = '';
$messageType = 'success'; // or 'danger'

// Get all employees (for admin dropdown)
$employees = [];
if ($isAdmin) {
    $employees = hrm_fetch_employees(false);
}

// ── HANDLE DELETE (POST from modal) ──
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delId = (int)$_POST['delete_daily_id'];
    try {
        $res = mysqli_query($con, "SELECT user_id, work_date FROM hrm_attendance_daily WHERE daily_id = {$delId}");
        $delRec = mysqli_fetch_assoc($res);
        if (!$delRec) throw new Exception('Record not found.');

        $safeDate = mysqli_real_escape_string($con, $delRec['work_date']);
        mysqli_query($con,
            "DELETE FROM hrm_attendance_logs 
             WHERE user_id = {$delRec['user_id']} 
             AND DATE(log_time) = '{$safeDate}' 
             AND source = 'manual_pc_down'");

        mysqli_query($con, "DELETE FROM hrm_attendance_daily WHERE daily_id = {$delId}");

        /* Also drop any manually-entered break tied to this user+date.
           Live-timer breaks (other reasons) are preserved. */
        $delUserId = (int) $delRec['user_id'];
        mysqli_query($con,
            "DELETE FROM hrm_breaks
             WHERE user_id = {$delUserId}
             AND break_date = '{$safeDate}'
             AND reason = 'Manual entry'");

        $message = 'Attendance record deleted successfully.';
        $messageType = 'success';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ── HANDLE FORM SUBMISSION (ADD or UPDATE) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'update')) {
    try {
        $target_user_id = $isAdmin ? (int)$_POST['employee_id'] : $userId;
        $work_date      = $_POST['work_date'] ?? date('Y-m-d');
        $check_in       = $_POST['check_in']  ?? null;
        $check_out      = $_POST['check_out'] ?? null;
        $daily_id       = !empty($_POST['daily_id']) ? (int)$_POST['daily_id'] : null;

        if (!$isAdmin && $target_user_id !== $userId) {
            throw new Exception('You can only edit your own attendance.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $work_date)) {
            throw new Exception('Invalid date format.');
        }
        if (strtotime($work_date) > strtotime(date('Y-m-d'))) {
            throw new Exception('Cannot add attendance for future dates.');
        }
        if (empty($check_in) && empty($check_out)) {
            throw new Exception('Please provide at least check-in or check-out time.');
        }
        if (!empty($check_in)  && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $check_in)) {
            throw new Exception('Invalid check-in time format.');
        }
        if (!empty($check_out) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $check_out)) {
            throw new Exception('Invalid check-out time format.');
        }
        if (!empty($check_in)  && substr_count($check_in,  ':') === 1) { $check_in  .= ':00'; }
        if (!empty($check_out) && substr_count($check_out, ':') === 1) { $check_out .= ':00'; }

        if (!empty($check_in) && !empty($check_out)) {
            if (strtotime($check_out) <= strtotime($check_in)) {
                throw new Exception('Check-out time must be after check-in time.');
            }
        }

        $check_in_datetime  = !empty($check_in)  ? ($work_date . ' ' . $check_in)  : null;
        $check_out_datetime = !empty($check_out) ? ($work_date . ' ' . $check_out) : null;

        $targetEmployee = hrm_fetch_employee($target_user_id);
        $deviceUserId = ($targetEmployee['device_user_id'] ?? '')
        ?: ($targetEmployee['hrm_employee_code'] ?? '')
        ?: ('manual-' . $target_user_id);

        // ── UPDATE MODE ──
        if ($daily_id && $isAdmin) {
            $safeDate = mysqli_real_escape_string($con, $work_date);
            mysqli_query($con,
                "DELETE FROM hrm_attendance_logs 
                 WHERE user_id = {$target_user_id} 
                 AND DATE(log_time) = '{$safeDate}' 
                 AND source = 'manual_pc_down'");

            if (!empty($check_in)) {
                $stmt = mysqli_prepare($con,
                    "INSERT INTO hrm_attendance_logs (user_id, device_user_id, log_time, state, source)
                     VALUES (?, ?, ?, 0, 'manual_pc_down')");
                mysqli_stmt_bind_param($stmt, 'iss', $target_user_id, $deviceUserId, $check_in_datetime);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            if (!empty($check_out)) {
                $stmt = mysqli_prepare($con,
                    "INSERT INTO hrm_attendance_logs (user_id, device_user_id, log_time, state, source)
                     VALUES (?, ?, ?, 1, 'manual_pc_down')");
                mysqli_stmt_bind_param($stmt, 'iss', $target_user_id, $deviceUserId, $check_out_datetime);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            $update_parts = [];
            $safeCheckin = $check_in_datetime ? "'" . mysqli_real_escape_string($con, $check_in_datetime) . "'" : 'NULL';
            $safeCheckout = $check_out_datetime ? "'" . mysqli_real_escape_string($con, $check_out_datetime) . "'" : 'NULL';

            $update_parts[] = "first_in = {$safeCheckin}";
            $update_parts[] = "last_out = {$safeCheckout}";

            $final_in  = !empty($check_in)  ? strtotime($check_in_datetime)  : null;
            $final_out = !empty($check_out) ? strtotime($check_out_datetime) : null;

            if ($final_in && $final_out) {
                $working_minutes = (int)(($final_out - $final_in) / 60);
                $update_parts[] = "working_minutes = {$working_minutes}";
                $update_parts[] = "status = 'present'";
            } elseif ($final_in || $final_out) {
                $update_parts[] = "working_minutes = 0";
                $update_parts[] = "status = 'partial'";
            } else {
                $update_parts[] = "working_minutes = 0";
                $update_parts[] = "status = 'absent'";
            }

            mysqli_query($con,
                "UPDATE hrm_attendance_daily SET " . implode(', ', $update_parts) .
            " WHERE daily_id = {$daily_id}");

            $message = 'Attendance updated successfully!';
            $messageType = 'success';
        }
        // ── INSERT MODE ──
        else {
            if (!empty($check_in)) {
                $stmt = mysqli_prepare($con,
                    "INSERT INTO hrm_attendance_logs (user_id, device_user_id, log_time, state, source)
                     VALUES (?, ?, ?, 0, 'manual_pc_down')");
                mysqli_stmt_bind_param($stmt, 'iss', $target_user_id, $deviceUserId, $check_in_datetime);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            if (!empty($check_out)) {
                $stmt = mysqli_prepare($con,
                    "INSERT INTO hrm_attendance_logs (user_id, device_user_id, log_time, state, source)
                     VALUES (?, ?, ?, 1, 'manual_pc_down')");
                mysqli_stmt_bind_param($stmt, 'iss', $target_user_id, $deviceUserId, $check_out_datetime);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            $safeWorkDate = mysqli_real_escape_string($con, $work_date);
            $result = mysqli_query($con,
                "SELECT daily_id, first_in, last_out FROM hrm_attendance_daily
                 WHERE user_id = {$target_user_id} AND work_date = '{$safeWorkDate}'");
            $record = $result ? mysqli_fetch_assoc($result) : null;

            if ($record) {
                $update_parts = [];
                if (!empty($check_in)) {
                    $safeCheckin = mysqli_real_escape_string($con, $check_in_datetime);
                    $update_parts[] = "first_in = '{$safeCheckin}'";
                }
                if (!empty($check_out)) {
                    $safeCheckout = mysqli_real_escape_string($con, $check_out_datetime);
                    $update_parts[] = "last_out = '{$safeCheckout}'";
                }
                if (!empty($update_parts)) {
                    $final_in  = !empty($check_in)  ? strtotime($check_in_datetime)  : ($record['first_in']  ? strtotime($record['first_in'])  : null);
                    $final_out = !empty($check_out) ? strtotime($check_out_datetime) : ($record['last_out'] ? strtotime($record['last_out']) : null);
                    if ($final_in && $final_out) {
                        $working_minutes  = (int)(($final_out - $final_in) / 60);
                        $update_parts[]   = "working_minutes = {$working_minutes}";
                        $update_parts[]   = "status = 'present'";
                    } elseif ($final_in || $final_out) {
                        $update_parts[]   = "status = 'partial'";
                    }
                    mysqli_query($con,
                        "UPDATE hrm_attendance_daily SET " . implode(', ', $update_parts) .
                    " WHERE daily_id = {$record['daily_id']}");
                }
            } else {
                $first_in        = !empty($check_in)  ? $check_in_datetime  : null;
                $last_out        = !empty($check_out) ? $check_out_datetime : null;
                $working_minutes = 0;
                $status          = 'absent';
                if ($first_in && $last_out) {
                    $working_minutes = (int)((strtotime($last_out) - strtotime($first_in)) / 60);
                    $status = 'present';
                } elseif ($first_in || $last_out) {
                    $status = 'partial';
                }
                $safeFirstIn  = $first_in  ? "'" . mysqli_real_escape_string($con, $first_in)  . "'" : 'NULL';
                $safeLastOut  = $last_out  ? "'" . mysqli_real_escape_string($con, $last_out)  . "'" : 'NULL';
                mysqli_query($con,
                    "INSERT INTO hrm_attendance_daily (user_id, work_date, first_in, last_out, working_minutes, status)
                     VALUES ({$target_user_id}, '{$safeWorkDate}', {$safeFirstIn}, {$safeLastOut}, {$working_minutes}, '{$status}')");
            }

            $message = 'Attendance added successfully!';
            $messageType = 'success';
        }

        /* ── Break Start / Break End upsert (manual-entry break) ──
           Same row is overwritten on each save; clearing both fields removes it.
           Live-timer breaks recorded via breaks.php (different reason) are untouched. */
        $break_in  = trim($_POST['break_in']  ?? '');
        $break_out = trim($_POST['break_out'] ?? '');

        if ($break_in  !== '' && substr_count($break_in,  ':') === 1) { $break_in  .= ':00'; }
        if ($break_out !== '' && substr_count($break_out, ':') === 1) { $break_out .= ':00'; }

        if ($break_in !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $break_in)) {
            throw new Exception('Invalid break start time format.');
        }
        if ($break_out !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $break_out)) {
            throw new Exception('Invalid break end time format.');
        }
        if (($break_in !== '') !== ($break_out !== '')) {
            throw new Exception('Please provide both Break Start and Break End, or leave both empty.');
        }

        $brUserId = (int) $target_user_id;
        $brDate   = mysqli_real_escape_string($con, $work_date);

        // Always clear the existing manual-entry break for this user/date first.
        mysqli_query($con,
            "DELETE FROM hrm_breaks
             WHERE user_id = {$brUserId}
             AND break_date = '{$brDate}'
             AND reason = 'Manual entry'");

        if ($break_in !== '' && $break_out !== '') {
            $bStartTs = strtotime($work_date . ' ' . $break_in);
            $bEndTs   = strtotime($work_date . ' ' . $break_out);
            if (!$bStartTs || !$bEndTs) {
                throw new Exception('Invalid break date/time.');
            }
            if ($bEndTs <= $bStartTs) {
                throw new Exception('Break end time must be after break start time.');
            }
            $bStartEsc = mysqli_real_escape_string($con, date('Y-m-d H:i:s', $bStartTs));
            $bEndEsc   = mysqli_real_escape_string($con, date('Y-m-d H:i:s', $bEndTs));
            $bDuration = (int) floor(($bEndTs - $bStartTs) / 60);
            $brCreatedBy = (int) $userId;

            mysqli_query($con,
                "INSERT INTO hrm_breaks
                    (user_id, break_date, start_time, end_time, duration_minutes, reason, break_type, created_by)
                 VALUES
                    ({$brUserId}, '{$brDate}', '{$bStartEsc}', '{$bEndEsc}', {$bDuration}, 'Manual entry', 'meal', {$brCreatedBy})");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch records for display
$attendance_records = [];

/* Break aggregate per user+date:
   - total_break_minutes covers BOTH manually-entered and live-timer breaks
   - manual_break_start/end pre-fill the Edit modal */
$breakJoin = "LEFT JOIN (
    SELECT user_id,
           break_date,
           SUM(GREATEST(0, COALESCE(duration_minutes, 0))) AS total_break_minutes,
           MIN(CASE WHEN reason='Manual entry' THEN start_time END) AS manual_break_start,
           MAX(CASE WHEN reason='Manual entry' THEN end_time   END) AS manual_break_end
    FROM hrm_breaks
    WHERE end_time IS NOT NULL
    GROUP BY user_id, break_date
) br ON br.user_id = had.user_id AND br.break_date = had.work_date";

if ($isAdmin) {
    $query = "SELECT had.*, u.user_name, hu.full_name,
                     br.total_break_minutes, br.manual_break_start, br.manual_break_end,
                     COALESCE(ss.allowed_break_minutes, 60) AS allowed_break_minutes
              FROM hrm_attendance_daily had
              JOIN users u ON had.user_id = u.user_id
              LEFT JOIN hrm_users hu ON had.user_id = hu.user_id
              LEFT JOIN hrm_salary_settings ss ON ss.user_id = had.user_id
              {$breakJoin}
              ORDER BY had.work_date DESC LIMIT 100";
} else {
    $safeTargetUserId = (int)$targetUserId;
    $query = "SELECT had.*, u.user_name, hu.full_name,
                     br.total_break_minutes, br.manual_break_start, br.manual_break_end,
                     COALESCE(ss.allowed_break_minutes, 60) AS allowed_break_minutes
              FROM hrm_attendance_daily had
              JOIN users u ON had.user_id = u.user_id
              LEFT JOIN hrm_users hu ON had.user_id = hu.user_id
              LEFT JOIN hrm_salary_settings ss ON ss.user_id = had.user_id
              {$breakJoin}
              WHERE had.user_id = {$safeTargetUserId}
              ORDER BY had.work_date DESC LIMIT 30";
}
$result = mysqli_query($con, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $attendance_records[] = $row;
    }
}

$employee_name = '';
$employee = hrm_fetch_employee($targetUserId);
if ($employee) {
    $employee_name = $employee['full_name'] ?? $employee['user_name'] ?? 'Unknown';
}

// Build employees JSON for modal dropdown
$employeesJson = [];
foreach ($employees as $emp) {
    $employeesJson[] = [
        'id'   => (int)$emp['user_id'],
        'name' => $emp['full_name'] ?? $emp['user_name'] ?? 'Unknown'
    ];
}

// Build records JSON for JS
$recordsJson = [];
foreach ($attendance_records as $rec) {
    $recordsJson[] = [
        'daily_id'        => (int)$rec['daily_id'],
        'user_id'         => (int)$rec['user_id'],
        'full_name'       => $rec['full_name'] ?? $rec['user_name'] ?? 'Unknown',
        'work_date'       => $rec['work_date'],
        'first_in'        => $rec['first_in'] ? date('H:i:s', strtotime($rec['first_in'])) : '',
        'last_out'        => $rec['last_out'] ? date('H:i:s', strtotime($rec['last_out'])) : '',
        'working_minutes' => (int)$rec['working_minutes'],
        'status'          => $rec['status'] ?? 'absent',
        'break_in'        => !empty($rec['manual_break_start']) ? date('H:i:s', strtotime($rec['manual_break_start'])) : '',
        'break_out'       => !empty($rec['manual_break_end'])   ? date('H:i:s', strtotime($rec['manual_break_end']))   : '',
        'break_minutes'   => (int)($rec['total_break_minutes'] ?? 0),
    ];
}

new_hrm_render_page_start(
    'Manual Attendance',
    'Manual Attendance Entry',
    'Add or edit check-in and check-out times for PC downtime situations'
);
?>

<!-- ── DataTables CDN ── -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.bootstrap5.min.js"></script>

<style>
/* ─────────────────────────────────────────
   MANUAL ATTENDANCE — REDESIGNED STYLES
───────────────────────────────────────── */

.ma-page-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 1.5rem; padding-bottom: 1rem;
    border-bottom: 1px solid var(--hrm-border);
}
.ma-page-title   { font-size: 20px; font-weight: 600; color: var(--hrm-text); letter-spacing: -.02em; margin: 0; }
.ma-page-sub     { font-size: 12px; color: var(--hrm-muted); margin-top: 3px; }
.ma-role-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600;
    background: #E6F1FB; color: #185FA5; border: 1px solid #B5D4F4;
}

.ma-grid { display: grid; grid-template-columns: 360px 1fr; gap: 18px; margin-bottom: 18px; }

.ma-card { background: #fff; border: 1px solid var(--hrm-border); border-radius: 12px; overflow: hidden; }
.ma-card-header {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 18px; border-bottom: 1px solid var(--hrm-border); background: #f8fafc;
}
.ma-card-header-icon {
    width: 30px; height: 30px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; background: #E6F1FB;
}
.ma-card-header h5 { font-size: 14px; font-weight: 600; color: var(--hrm-text); margin: 0; }
.ma-card-body { padding: 18px; }

.ma-alert {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 10px 14px; border-radius: 8px; font-size: 12px; margin-bottom: 16px;
    animation: maFadeIn .25s ease;
}
@keyframes maFadeIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }
.ma-alert.success { background: #EAF3DE; color: #3B6D11; border: 1px solid #C0DD97; }
.ma-alert.danger  { background: #FCEBEB; color: #A32D2D; border: 1px solid #F7C1C1; }

.ma-label {
    display: block; font-size: 11px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .06em;
    color: var(--hrm-muted); margin-bottom: 5px;
}
.ma-hint  { font-size: 11px; color: var(--hrm-muted); margin-top: 4px; }
.ma-input, .ma-select {
    width: 100%; padding: 9px 11px; font-size: 13px;
    border: 1px solid var(--hrm-border); border-radius: 8px;
    background: #fff; color: var(--hrm-text);
    transition: border-color .15s, box-shadow .15s; outline: none;
}
.ma-input:focus, .ma-select:focus {
    border-color: #378ADD; box-shadow: 0 0 0 3px rgba(55,138,221,.12);
}
.ma-time-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.ma-btn-save {
    display: flex; align-items: center; justify-content: center; gap: 7px;
    width: 100%; padding: 11px; margin-top: 18px;
    background: #185FA5; color: #fff; border: none;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: background .15s, transform .08s;
}
.ma-btn-save:hover  { background: #0C447C; }
.ma-btn-save:active { transform: scale(.98); }

.ma-table-wrap { overflow-x: auto; }
.ma-table { width: 100%; border-collapse: collapse; min-width: 460px; }
.ma-table thead th {
    padding: 9px 14px; font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .07em;
    color: var(--hrm-muted); text-align: left;
    background: #f8fafc; border-bottom: 1px solid var(--hrm-border);
    white-space: nowrap;
}
.ma-table tbody td {
    padding: 11px 14px; border-bottom: 1px solid var(--hrm-border);
    vertical-align: middle; font-size: 13px; color: var(--hrm-text);
}
.ma-table tbody tr:last-child td { border-bottom: none; }
.ma-table tbody tr:hover td     { background: #f8fbff; }

.ma-time      { font-family: 'Courier New', monospace; font-size: 12px; font-weight: 500; }
.ma-time.empty{ color: var(--hrm-muted); }
.ma-duration  { font-size: 12px; color: var(--hrm-muted); font-variant-numeric: tabular-nums; }

.ma-status {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
}
.ma-status.present { background: #EAF3DE; color: #3B6D11; }
.ma-status.partial { background: #FAEEDA; color: #854F0B; }
.ma-status.absent  { background: #FCEBEB; color: #A32D2D; }
.ma-dot            { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

.ma-actions { display: flex; align-items: center; gap: 4px; }
.ma-btn-action {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px;
    border: 1px solid transparent; cursor: pointer;
    transition: all .12s; background: transparent;
}
.ma-btn-action.edit   { color: #185FA5; border-color: #B5D4F4; background: #E6F1FB; }
.ma-btn-action.edit:hover   { background: #185FA5; color: #fff; }
.ma-btn-action.delete { color: #A32D2D; border-color: #F7C1C1; background: #FCEBEB; }
.ma-btn-action.delete:hover { background: #A32D2D; color: #fff; }

.ma-empty { padding: 36px 18px; text-align: center; color: var(--hrm-muted); font-size: 13px; }

/* ─────────────────────────────────────────
   MODAL STYLES
───────────────────────────────────────── */
.ma-modal-overlay {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(15,23,42,.45); backdrop-filter: blur(4px);
    align-items: center; justify-content: center;
    padding: 20px;
    animation: maOverlayIn .2s ease;
}
.ma-modal-overlay.active { display: flex; }
@keyframes maOverlayIn { from { opacity: 0; } to { opacity: 1; } }

.ma-modal {
    background: #fff; border-radius: 16px;
    width: 100%; max-width: 460px;
    box-shadow: 0 25px 60px rgba(0,0,0,.18), 0 0 0 1px rgba(0,0,0,.05);
    animation: maModalIn .25s cubic-bezier(.22,1,.36,1);
    overflow: hidden;
}
@keyframes maModalIn { from { opacity:0; transform:scale(.95) translateY(10px); } to { opacity:1; transform:scale(1) translateY(0); } }

.ma-modal-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px; border-bottom: 1px solid var(--hrm-border);
    background: #f8fafc;
}
.ma-modal-head-left { display: flex; align-items: center; gap: 10px; }
.ma-modal-head-icon {
    width: 34px; height: 34px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
}
.ma-modal-head-icon.edit   { background: #E6F1FB; }
.ma-modal-head-icon.delete { background: #FCEBEB; }
.ma-modal-title { font-size: 15px; font-weight: 700; color: var(--hrm-text); margin: 0; }
.ma-modal-subtitle { font-size: 11px; color: var(--hrm-muted); margin-top: 1px; }

.ma-modal-close {
    width: 30px; height: 30px; border-radius: 8px;
    border: 1px solid var(--hrm-border); background: #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all .12s; color: var(--hrm-muted);
}
.ma-modal-close:hover { background: #f1f5f9; color: var(--hrm-text); }

.ma-modal-body { padding: 22px; }

.ma-modal-foot {
    display: flex; align-items: center; justify-content: flex-end; gap: 8px;
    padding: 16px 22px; border-top: 1px solid var(--hrm-border);
    background: #f8fafc;
}

.ma-modal-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 9px 18px; border-radius: 8px;
    font-size: 13px; font-weight: 600; cursor: pointer;
    transition: all .12s; border: 1px solid transparent;
}
.ma-modal-btn.secondary {
    background: #fff; color: var(--hrm-muted); border-color: var(--hrm-border);
}
.ma-modal-btn.secondary:hover { background: #f1f5f9; color: var(--hrm-text); }
.ma-modal-btn.primary {
    background: #185FA5; color: #fff; border-color: #185FA5;
}
.ma-modal-btn.primary:hover { background: #0C447C; }
.ma-modal-btn.danger {
    background: #A32D2D; color: #fff; border-color: #A32D2D;
}
.ma-modal-btn.danger:hover { background: #7E2222; }

/* Delete modal specifics */
.ma-delete-info {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px; border-radius: 10px;
    background: #FFF8F8; border: 1px solid #FDD;
    margin-bottom: 16px;
}
.ma-delete-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: #FCEBEB; color: #A32D2D;
    font-size: 13px; font-weight: 800;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ma-delete-name { font-size: 14px; font-weight: 600; color: var(--hrm-text); }
.ma-delete-date { font-size: 12px; color: var(--hrm-muted); margin-top: 2px; }

.ma-delete-warning {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 14px; border-radius: 8px;
    background: #FFFBEB; border: 1px solid #FDE68A;
    font-size: 12px; color: #854F0B; line-height: 1.5;
}
.ma-delete-warning svg { flex-shrink: 0; margin-top: 1px; }

/* Edit modal field group */
.ma-modal-field { margin-bottom: 16px; }
.ma-modal-field:last-child { margin-bottom: 0; }

/* ── DataTable wrapper ── */
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

.hrm-dt-controls { margin-bottom: 12px; }
div.dataTables_wrapper div.dataTables_length select,
div.dataTables_wrapper div.dataTables_filter input {
    border: 1px solid var(--hrm-border) !important; border-radius: 8px !important;
    padding: 6px 10px !important; font-size: 13px !important; outline: none !important;
    box-shadow: none !important; background: #fff !important; color: var(--hrm-text) !important; min-width: 60px;
}
div.dataTables_wrapper div.dataTables_filter input { min-width: 200px; }
div.dataTables_wrapper div.dataTables_filter input:focus {
    border-color: var(--hrm-primary) !important; box-shadow: 0 0 0 3px rgba(20,86,184,.10) !important;
}
div.dataTables_wrapper div.dataTables_info,
div.dataTables_wrapper div.dataTables_length label,
div.dataTables_wrapper div.dataTables_filter label {
    font-size: 12px !important; color: var(--hrm-muted) !important;
    display: flex !important; align-items: center !important; gap: 6px !important; margin: 0 !important;
}
div.dataTables_wrapper div.dataTables_info { padding-top: 8px !important; }

div.dataTables_wrapper .pagination { gap: 4px; margin: 8px 0 0; }
div.dataTables_wrapper .pagination .page-item .page-link {
    border-radius: 6px !important; font-size: 12px !important; padding: 5px 10px !important;
    border: 1px solid var(--hrm-border) !important; color: var(--hrm-text) !important;
    background: #fff !important; line-height: 1.4 !important;
}
div.dataTables_wrapper .pagination .page-item .page-link:hover {
    background: var(--hrm-primary-soft) !important; color: var(--hrm-primary) !important;
}
div.dataTables_wrapper .pagination .page-item.active .page-link {
    background: var(--hrm-primary) !important; border-color: var(--hrm-primary) !important; color: #fff !important;
}
div.dataTables_wrapper .pagination .page-item.disabled .page-link { opacity: .4 !important; }

.hrm-dt-status-pills {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    padding: 10px 18px; border-bottom: 1px solid var(--hrm-border); background: #fff;
}
.hrm-dt-pills-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .06em; color: var(--hrm-muted); margin-right: 2px;
}
.hrm-dt-pill {
    padding: 4px 12px; font-size: 11px; font-weight: 600;
    border-radius: 999px; border: 1px solid var(--hrm-border);
    background: #fff; color: var(--hrm-muted); cursor: pointer; transition: all .12s;
}
.hrm-dt-pill--active                      { background: var(--hrm-primary); color: #fff; border-color: var(--hrm-primary); }
.hrm-dt-pill--active.hrm-dt-pill--present { background: #EAF3DE; color: #3B6D11; border-color: #C0DD97; }
.hrm-dt-pill--active.hrm-dt-pill--partial { background: #FAEEDA; color: #854F0B; border-color: #FAC775; }
.hrm-dt-pill--active.hrm-dt-pill--absent  { background: #FCEBEB; color: #A32D2D; border-color: #F7C1C1; }

@media (max-width: 900px) { .ma-grid { grid-template-columns: 1fr; } }
</style>

<div class="ma-page-header">
    <div>
        <h1 class="ma-page-title">Manual Attendance</h1>
        <p class="ma-page-sub">Add or edit check-in / check-out times for PC downtime situations</p>
    </div>
    <?php if ($isAdmin): ?>
        <span class="ma-role-badge">
            <svg width="11" height="11" viewBox="0 0 12 12" fill="currentColor"><path d="M6 1a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zm0 7c2.67 0 4 1.12 4 1.67V11H2v-1.33C2 9.12 3.33 8 6 8z"/></svg>
            Admin view
        </span>
    <?php else: ?>
        <span class="ma-role-badge" style="background:#E1F5EE;color:#0F6E56;border-color:#9FE1CB;">
            <svg width="11" height="11" viewBox="0 0 12 12" fill="currentColor"><path d="M6 1a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zm0 7c2.67 0 4 1.12 4 1.67V11H2v-1.33C2 9.12 3.33 8 6 8z"/></svg>
            My attendance
        </span>
    <?php endif; ?>
</div>

<div class="ma-grid">

    <!-- ── Form Card ── -->
    <div class="ma-card">
        <div class="ma-card-header">
            <div class="ma-card-header-icon">⏱</div>
            <h5>Add Attendance</h5>
        </div>
        <div class="ma-card-body">

            <?php if ($message): ?>
                <div class="ma-alert <?= htmlspecialchars($messageType) ?>">
                    <?php if ($messageType === 'success'): ?>
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M2 7.5L5.5 11L12 3.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    <?php else: ?>
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="7" cy="7" r="6"/><path d="M7 4v3.5M7 9.5v.5" stroke-linecap="round"/>
                        </svg>
                    <?php endif; ?>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="ma-alert danger">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="7" cy="7" r="6"/><path d="M7 4v3.5M7 9.5v.5" stroke-linecap="round"/>
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="add">

                <?php if ($isAdmin): ?>
                    <div class="mb-3">
                        <label class="ma-label">Employee</label>
                        <select name="employee_id" class="ma-select" required>
                            <option value="">— choose employee —</option>
                            <?php foreach ($employees as $emp): ?>
                                <?php 
$empName = $emp['full_name'] ?? $emp['user_name'] ?? 'Unknown';
$selected = ($targetUserId == $emp['user_id']) ? 'selected' : '';
                                ?>
                                <option value="<?= htmlspecialchars((string)$emp['user_id']) ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($empName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="ma-hint">Admin can modify any employee's attendance</div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="ma-label">Work Date</label>
                    <input type="date" name="work_date" class="ma-input" value="<?= date('Y-m-d') ?>" required>
                    <div class="ma-hint">Cannot be a future date</div>
                </div>

                <div class="ma-time-row">
                    <div class="mb-3">
                        <label class="ma-label">Check-In</label>
                        <input type="time" name="check_in" class="ma-input" step="1">
                        <div class="ma-hint">24-hour format</div>
                    </div>
                    <div class="mb-3">
                        <label class="ma-label">Check-Out</label>
                        <input type="time" name="check_out" class="ma-input" step="1">
                        <div class="ma-hint">Must be after check-in</div>
                    </div>
                </div>

                <div class="ma-time-row">
                    <div class="mb-3">
                        <label class="ma-label">Break Start <span style="color:var(--hrm-muted);font-weight:500;text-transform:none;letter-spacing:0;">(optional)</span></label>
                        <input type="time" name="break_in" class="ma-input" step="1">
                        <div class="ma-hint">Leave empty for no break</div>
                    </div>
                    <div class="mb-3">
                        <label class="ma-label">Break End <span style="color:var(--hrm-muted);font-weight:500;text-transform:none;letter-spacing:0;">(optional)</span></label>
                        <input type="time" name="break_out" class="ma-input" step="1">
                        <div class="ma-hint">Must be after break start</div>
                    </div>
                </div>

                <button type="submit" class="ma-btn-save">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 7.5L5.5 11L12 3.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Save Attendance
                </button>
            </form>
        </div>
    </div>

    <!-- ── Records Card ── -->
    <div class="ma-card">
        <div class="ma-card-header">
            <div class="ma-card-header-icon" style="background:#E1F5EE;">📊</div>
            <h5>
                <?php if (!$isAdmin && !empty($employee_name)): ?>
                    Records for <?= htmlspecialchars($employee_name) ?>
                <?php else: ?>
                    All Employees
                <?php endif; ?>
            </h5>
        </div>

        <div class="ma-table-wrap" style="padding:0 18px 18px;">
            <table class="hrm-dt-table display nowrap" id="ma-table" style="width:100%">
                <thead>
                    <tr>
                        <?php if ($isAdmin): ?><th>Employee</th><?php endif; ?>
                        <th>Date</th>
                        <th>Check-In</th>
                        <th>Check-Out</th>
                        <th>Break</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <?php if ($isAdmin): ?><th style="text-align:center;">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="ma-tbody">
                    <?php foreach ($attendance_records as $rec): ?>
                        <?php
$empName   = $rec['full_name'] ?? $rec['user_name'] ?? 'Unknown';
$initials  = mb_strtoupper(mb_substr($empName, 0, 1));
$words     = explode(' ', $empName);
if (count($words) > 1) {
    $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1));
}
$statusVal = $rec['status'] ?? 'absent';
$hours = intdiv((int)$rec['working_minutes'], 60);
$mins  = (int)$rec['working_minutes'] % 60;
$breakMins = (int)($rec['total_break_minutes'] ?? 0);
$breakStartFmt = !empty($rec['manual_break_start']) ? date('g:i A', strtotime($rec['manual_break_start'])) : '';
$breakEndFmt   = !empty($rec['manual_break_end'])   ? date('g:i A', strtotime($rec['manual_break_end']))   : '';
$allowedBreak  = (int)($rec['allowed_break_minutes'] ?? 60);
$breakPaid     = $breakMins <= $allowedBreak;
$breakColor    = $breakPaid ? '#15803d' : '#b91c1c'; // green / red
$breakTitle    = $breakPaid
    ? "Within {$allowedBreak} min allowance (paid)"
    : "Exceeds allowance by " . ($breakMins - $allowedBreak) . " min (deducted from pay)";
                        ?>
                        <tr data-status="<?= htmlspecialchars($statusVal) ?>"
                            data-name="<?= htmlspecialchars(strtolower($empName)) ?>"
                            data-date="<?= htmlspecialchars(strtolower(date('M d, Y', strtotime($rec['work_date'])))) ?>">

                            <?php if ($isAdmin): ?>
                            <td>
                                <div class="hrm-cell-person">
                                    <div class="hrm-cell-avatar"><?= htmlspecialchars($initials) ?></div>
                                    <span><?= htmlspecialchars($empName) ?></span>
                                </div>
                            </td>
                            <?php endif; ?>

                            <td><?= date('M d, Y', strtotime($rec['work_date'])) ?></td>

                            <td>
                                <span class="ma-time <?= empty($rec['first_in']) ? 'empty' : '' ?>">
                                    <?= $rec['first_in'] ? date('H:i', strtotime($rec['first_in'])) : '—' ?>
                                </span>
                            </td>

                            <td>
                                <span class="ma-time <?= empty($rec['last_out']) ? 'empty' : '' ?>">
                                    <?= $rec['last_out'] ? date('H:i', strtotime($rec['last_out'])) : '—' ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($breakStartFmt && $breakEndFmt): ?>
                                    <span class="ma-time"><?= htmlspecialchars($breakStartFmt . '–' . $breakEndFmt) ?></span>
                                    <div class="ma-duration" style="color:<?= $breakColor ?>;font-weight:700;" title="<?= htmlspecialchars($breakTitle) ?>">
                                        <?= $breakMins ?> min
                                    </div>
                                <?php elseif ($breakMins > 0): ?>
                                    <span class="ma-time" style="color:<?= $breakColor ?>;font-weight:700;" title="<?= htmlspecialchars($breakTitle) ?>">
                                        <?= $breakMins ?> min
                                    </span>
                                <?php else: ?>
                                    <span class="ma-time empty">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="ma-duration">
                                <?= $rec['working_minutes'] > 0 ? sprintf('%dh %02dm', $hours, $mins) : '—' ?>
                            </td>

                            <td>
                                <span class="ma-status <?= htmlspecialchars($statusVal) ?>">
                                    <span class="ma-dot"></span>
                                    <?= htmlspecialchars(ucfirst($statusVal)) ?>
                                </span>
                            </td>

                            <?php if ($isAdmin): ?>
                            <td style="text-align:center;">
                                <div class="ma-actions">
                                    <button type="button" class="ma-btn-action edit" title="Edit"
                                        onclick="maOpenEdit(<?= (int)$rec['daily_id'] ?>)">
                                        <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M2 10v2h2l7-7-2-2-7 7z"/><path d="M9 2l3 3"/>
                                        </svg>
                                    </button>
                                    <button type="button" class="ma-btn-action delete" title="Delete"
                                        onclick="maOpenDelete(<?= (int)$rec['daily_id'] ?>)">
                                        <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M2 4h10M5.5 4v7h3V4M4 2h6"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     EDIT MODAL
═══════════════════════════════════════ -->
<div class="ma-modal-overlay" id="maEditOverlay">
    <div class="ma-modal">
        <div class="ma-modal-head">
            <div class="ma-modal-head-left">
                <div class="ma-modal-head-icon edit">✏️</div>
                <div>
                    <div class="ma-modal-title">Edit Attendance</div>
                    <div class="ma-modal-subtitle">Modify check-in / check-out times</div>
                </div>
            </div>
            <button type="button" class="ma-modal-close" onclick="maCloseEdit()">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 3l8 8M11 3l-8 8" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <form id="maEditForm" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="daily_id" id="maEditDailyId">

            <div class="ma-modal-body">
                <?php if ($isAdmin): ?>
                    <div class="ma-modal-field">
                        <label class="ma-label">Employee</label>
                        <select name="employee_id" id="maEditEmployee" class="ma-select" required>
                            <option value="">— choose employee —</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= (int)$emp['user_id'] ?>">
                                    <?= htmlspecialchars($emp['full_name'] ?? $emp['user_name'] ?? 'Unknown') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="ma-modal-field">
                    <label class="ma-label">Work Date</label>
                    <input type="date" name="work_date" id="maEditDate" class="ma-input" required>
                </div>

                <div class="ma-time-row">
                    <div class="ma-modal-field">
                        <label class="ma-label">Check-In</label>
                        <input type="time" name="check_in" id="maEditCheckin" class="ma-input" step="1">
                    </div>
                    <div class="ma-modal-field">
                        <label class="ma-label">Check-Out</label>
                        <input type="time" name="check_out" id="maEditCheckout" class="ma-input" step="1">
                    </div>
                </div>

                <div class="ma-time-row">
                    <div class="ma-modal-field">
                        <label class="ma-label">Break Start <span style="color:var(--hrm-muted);font-weight:500;text-transform:none;letter-spacing:0;">(optional)</span></label>
                        <input type="time" name="break_in" id="maEditBreakIn" class="ma-input" step="1">
                    </div>
                    <div class="ma-modal-field">
                        <label class="ma-label">Break End <span style="color:var(--hrm-muted);font-weight:500;text-transform:none;letter-spacing:0;">(optional)</span></label>
                        <input type="time" name="break_out" id="maEditBreakOut" class="ma-input" step="1">
                    </div>
                </div>
            </div>

            <div class="ma-modal-foot">
                <button type="button" class="ma-modal-btn secondary" onclick="maCloseEdit()">Cancel</button>
                <button type="submit" class="ma-modal-btn primary">
                    <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 7.5L5.5 11L12 3.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Update Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════
     DELETE MODAL
═══════════════════════════════════════ -->
<div class="ma-modal-overlay" id="maDeleteOverlay">
    <div class="ma-modal">
        <div class="ma-modal-head">
            <div class="ma-modal-head-left">
                <div class="ma-modal-head-icon delete">🗑️</div>
                <div>
                    <div class="ma-modal-title">Delete Attendance</div>
                    <div class="ma-modal-subtitle">This action cannot be undone</div>
                </div>
            </div>
            <button type="button" class="ma-modal-close" onclick="maCloseDelete()">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 3l8 8M11 3l-8 8" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <div class="ma-modal-body">
            <div class="ma-delete-info">
                <div class="ma-delete-avatar" id="maDelAvatar">?</div>
                <div>
                    <div class="ma-delete-name" id="maDelName">—</div>
                    <div class="ma-delete-date" id="maDelDate">—</div>
                </div>
            </div>

            <div class="ma-delete-warning">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6">
                    <path d="M8 1L1 14h14L8 1z" stroke-linejoin="round"/>
                    <path d="M8 6v4" stroke-linecap="round"/>
                    <circle cx="8" cy="12.5" r=".7" fill="currentColor" stroke="none"/>
                </svg>
                <div>
                    Deleting this record will also remove the associated manual log entries (<strong>source: manual_pc_down</strong>) for this date. The daily attendance summary will be permanently removed.
                </div>
            </div>
        </div>

        <form id="maDeleteForm" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="delete_daily_id" id="maDeleteDailyId">

            <div class="ma-modal-foot">
                <button type="button" class="ma-modal-btn secondary" onclick="maCloseDelete()">Cancel</button>
                <button type="submit" class="ma-modal-btn danger">
                    <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 4h10M5.5 4v7h3V4M4 2h6"/>
                    </svg>
                    Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<script>
/* ── Records lookup (from PHP) ── */
const maRecords = <?= json_encode($recordsJson, JSON_UNESCAPED_UNICODE) ?>;

function maGetRecord(id) {
    return maRecords.find(function(r) { return r.daily_id === id; }) || null;
}

function maGetInitials(name) {
    if (!name) return '?';
    var w = name.trim().split(/\s+/);
    if (w.length > 1) return (w[0][0] + w[w.length-1][0]).toUpperCase();
    return name[0].toUpperCase();
}

/* ── EDIT MODAL ── */
function maOpenEdit(dailyId) {
    var rec = maGetRecord(dailyId);
    if (!rec) return;

    document.getElementById('maEditDailyId').value = rec.daily_id;
    <?php if ($isAdmin): ?>
    document.getElementById('maEditEmployee').value = rec.user_id;
    <?php endif; ?>
    document.getElementById('maEditDate').value = rec.work_date;
    document.getElementById('maEditCheckin').value = rec.first_in || '';
    document.getElementById('maEditCheckout').value = rec.last_out || '';
    document.getElementById('maEditBreakIn').value  = rec.break_in  || '';
    document.getElementById('maEditBreakOut').value = rec.break_out || '';

    document.getElementById('maEditOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function maCloseEdit() {
    document.getElementById('maEditOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

/* ── DELETE MODAL ── */
function maOpenDelete(dailyId) {
    var rec = maGetRecord(dailyId);
    if (!rec) return;

    document.getElementById('maDeleteDailyId').value = rec.daily_id;
    document.getElementById('maDelAvatar').textContent = maGetInitials(rec.full_name);
    document.getElementById('maDelName').textContent = rec.full_name;

    var dateStr = rec.work_date;
    try {
        var d = new Date(dateStr + 'T00:00:00');
        dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch(e) {}
    document.getElementById('maDelDate').textContent = dateStr;

    document.getElementById('maDeleteOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function maCloseDelete() {
    document.getElementById('maDeleteOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

/* ── Close on overlay click ── */
document.getElementById('maEditOverlay').addEventListener('click', function(e) {
    if (e.target === this) maCloseEdit();
});
document.getElementById('maDeleteOverlay').addEventListener('click', function(e) {
    if (e.target === this) maCloseDelete();
});

/* ── Close on Escape ── */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('maEditOverlay').classList.contains('active')) maCloseEdit();
        if (document.getElementById('maDeleteOverlay').classList.contains('active')) maCloseDelete();
    }
});

/* ═══════════════════════════════════════
   DATATABLE INIT
═══════════════════════════════════════ */
(function initManualAttendanceDT() {
    function init() {
        if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
            return setTimeout(init, 50);
        }

        var sfActive = 'all';

        var pillHtml =
            '<div class="hrm-dt-status-pills">' +
                '<span class="hrm-dt-pills-label">Filter:</span>' +
                '<button class="hrm-dt-pill hrm-dt-pill--active" data-sf="all">All</button>' +
                '<button class="hrm-dt-pill" data-sf="present">Present</button>' +
                '<button class="hrm-dt-pill" data-sf="partial">Partial</button>' +
                '<button class="hrm-dt-pill" data-sf="absent">Absent</button>' +
            '</div>';
        $('#ma-table').closest('.ma-card').find('.ma-card-header').after(pillHtml);

        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (settings.nTable.id !== 'ma-table') return true;
            if (sfActive === 'all') return true;
            var row    = settings.oInstance.api().row(dataIndex).node();
            var status = $(row).data('status') || '';
            return status === sfActive;
        });

        var $tbl = $('#ma-table');
        if ($tbl.length && !$.fn.DataTable.isDataTable($tbl)) {
            var dt = $tbl.DataTable({
                pageLength: 10,
                responsive: true,
                order: [[<?= $isAdmin ? '1' : '0' ?>, 'desc']],
                language: {
                    search:            '',
                    searchPlaceholder: 'Search…',
                    lengthMenu:        'Show _MENU_ rows',
                    info:              'Showing _START_–_END_ of _TOTAL_ entries',
                    infoEmpty:         'No entries found',
                    infoFiltered:      '(filtered from _MAX_)',
                    emptyTable:        'No attendance records found',
                    paginate:          { previous: '‹', next: '›' }
                },
                dom: '<"hrm-dt-controls d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"lf>rtip',
                drawCallback: function () {
                    $(this.api().table().container()).find('.hrm-badge, .ma-status').each(function () {
                        $(this).css('display', 'inline-flex');
                    });
                }
            });

            $(document).on('click', '.hrm-dt-pill', function () {
                sfActive = $(this).data('sf');
                $('.hrm-dt-pill').removeClass('hrm-dt-pill--active hrm-dt-pill--present hrm-dt-pill--partial hrm-dt-pill--absent');
                $(this).addClass('hrm-dt-pill--active hrm-dt-pill--' + sfActive);
                dt.draw();
            });
        }
    }
    init();
})();
</script>

<?php
new_hrm_render_page_end();
?>