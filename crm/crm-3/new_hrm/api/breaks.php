<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/hrm_helpers.php';

global $con;

$isAdmin = hrm_is_admin();
$sessionUserId = hrm_session_user_id();

if (!$sessionUserId) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

/* ── Auto-bootstrap the hrm_breaks table on first call ── */
mysqli_query($con, "CREATE TABLE IF NOT EXISTS hrm_breaks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    break_date DATE NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    duration_minutes INT NULL,
    reason VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, break_date),
    INDEX idx_open (user_id, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure break_type column exists (Meal / Short / Other)
$__bt_chk = mysqli_query($con, "SHOW COLUMNS FROM hrm_breaks LIKE 'break_type'");
if ($__bt_chk && mysqli_num_rows($__bt_chk) === 0) {
    mysqli_query($con, "ALTER TABLE hrm_breaks ADD COLUMN break_type ENUM('meal','short','other') NOT NULL DEFAULT 'other' AFTER reason");
}

function nhrm_normalize_break_type($v)
{
    $v = strtolower(trim((string) $v));
    return in_array($v, ['meal', 'short', 'other'], true) ? $v : 'other';
}

$action = $_REQUEST['action'] ?? 'list';

function nhrm_break_open_for($con, $userId)
{
    $userId = (int) $userId;
    $r = mysqli_query($con, "SELECT * FROM hrm_breaks WHERE user_id=$userId AND end_time IS NULL ORDER BY id DESC LIMIT 1");
    return $r ? mysqli_fetch_assoc($r) : null;
}

function nhrm_break_row($con, $id)
{
    $id = (int) $id;
    $r = mysqli_query($con, "SELECT * FROM hrm_breaks WHERE id=$id LIMIT 1");
    return $r ? mysqli_fetch_assoc($r) : null;
}

switch ($action) {

    case 'current': {
        // Returns the requester's open break (admins may inspect any user).
        $reqUser = $isAdmin && !empty($_GET['user_id'])
            ? (int) $_GET['user_id']
            : $sessionUserId;
        $row = nhrm_break_open_for($con, $reqUser);
        if ($row) {
            $row['elapsed_minutes'] = max(0, (int) floor((time() - strtotime($row['start_time'])) / 60));
        }
        echo json_encode(['data' => $row]);
        break;
    }

    case 'list': {
        $reqUser  = (int) ($_GET['user_id'] ?? 0);
        $dateFrom = mysqli_real_escape_string($con, $_GET['date_from'] ?? '');
        $dateTo   = mysqli_real_escape_string($con, $_GET['date_to'] ?? '');
        $status   = $_GET['status'] ?? '';   // '', 'open', 'closed'

        $where = [];
        if (!$isAdmin) {
            $where[] = "b.user_id=" . $sessionUserId;
        } elseif ($reqUser) {
            $where[] = "b.user_id=$reqUser";
        }
        if ($dateFrom) $where[] = "b.break_date >= '$dateFrom'";
        if ($dateTo)   $where[] = "b.break_date <= '$dateTo'";
        if ($status === 'open')   $where[] = "b.end_time IS NULL";
        if ($status === 'closed') $where[] = "b.end_time IS NOT NULL";

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT b.*, u.user_name, hu.full_name, e.employee_code
                FROM hrm_breaks b
                LEFT JOIN users u ON u.user_id = b.user_id
                LEFT JOIN hrm_users hu ON hu.user_id = b.user_id
                LEFT JOIN hrm_employees e ON e.user_id = b.user_id
                $whereStr
                ORDER BY b.start_time DESC
                LIMIT 500";

        $r = mysqli_query($con, $sql);
        $rows = [];
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) {
                $row['display_name']    = $row['full_name'] ?: $row['user_name'] ?: ('User ' . $row['user_id']);
                $row['start_time_fmt']  = date('d M Y h:i A', strtotime($row['start_time']));
                $row['end_time_fmt']    = $row['end_time'] ? date('d M Y h:i A', strtotime($row['end_time'])) : '';
                $row['is_open']         = $row['end_time'] === null ? 1 : 0;
                if ($row['is_open']) {
                    $row['live_minutes'] = max(0, (int) floor((time() - strtotime($row['start_time'])) / 60));
                }
                $rows[] = $row;
            }
        }
        echo json_encode(['data' => $rows]);
        break;
    }

    case 'stats': {
        // Aggregates for the KPI strip. Admins see global, employees see their own.
        $scope = !$isAdmin ? "user_id=" . $sessionUserId : '1=1';
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        $out = [
            'today_breaks'    => 0,
            'on_break_now'    => 0,
            'month_minutes'   => 0,
            'month_avg'       => 0,
        ];

        $q = mysqli_query($con, "SELECT COUNT(*) AS c FROM hrm_breaks WHERE $scope AND break_date='$today'");
        if ($q && ($r = mysqli_fetch_assoc($q))) $out['today_breaks'] = (int) $r['c'];

        $q = mysqli_query($con, "SELECT COUNT(*) AS c FROM hrm_breaks WHERE $scope AND end_time IS NULL");
        if ($q && ($r = mysqli_fetch_assoc($q))) $out['on_break_now'] = (int) $r['c'];

        $q = mysqli_query($con, "SELECT COALESCE(SUM(duration_minutes),0) AS s, COALESCE(AVG(duration_minutes),0) AS a
                                 FROM hrm_breaks
                                 WHERE $scope AND break_date >= '$monthStart' AND duration_minutes IS NOT NULL");
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            $out['month_minutes'] = (int) $r['s'];
            $out['month_avg']     = round((float) $r['a'], 1);
        }

        echo json_encode(['data' => $out]);
        break;
    }

    case 'start': {
        $postedUser = (int) ($_POST['user_id'] ?? 0);
        $user_id    = $isAdmin && $postedUser ? $postedUser : $sessionUserId;
        $reason     = mysqli_real_escape_string($con, trim($_POST['reason'] ?? ''));

        // Reject if already on break.
        $existing = nhrm_break_open_for($con, $user_id);
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'A break is already in progress.', 'open_id' => $existing['id']]);
            exit;
        }

        $createdBy = $sessionUserId;
        $breakType = nhrm_normalize_break_type($_POST['break_type'] ?? 'other');
        $sql = "INSERT INTO hrm_breaks (user_id, break_date, start_time, reason, break_type, created_by)
                VALUES ($user_id, CURDATE(), NOW(), '$reason', '$breakType', $createdBy)";
        if (mysqli_query($con, $sql)) {
            echo json_encode(['success' => true, 'id' => mysqli_insert_id($con)]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        }
        break;
    }

    case 'manual_add': {
        // Admin-only: insert a break with custom start/end (e.g. for forgotten logs).
        if (!$isAdmin) { echo json_encode(['success' => false, 'message' => 'Forbidden']); exit; }

        $user_id    = (int) ($_POST['user_id'] ?? 0);
        $break_date = mysqli_real_escape_string($con, $_POST['break_date'] ?? '');
        $start_time = mysqli_real_escape_string($con, $_POST['start_time'] ?? '');
        $end_time   = mysqli_real_escape_string($con, $_POST['end_time'] ?? '');
        $reason     = mysqli_real_escape_string($con, trim($_POST['reason'] ?? ''));

        if (!$user_id || !$break_date || !$start_time) {
            echo json_encode(['success' => false, 'message' => 'Employee, date and start time are required.']);
            exit;
        }

        $startDt = $break_date . ' ' . $start_time;
        $startTs = strtotime($startDt);
        if (!$startTs) {
            echo json_encode(['success' => false, 'message' => 'Invalid start date/time.']); exit;
        }

        $endSql   = 'NULL';
        $durSql   = 'NULL';
        if ($end_time !== '') {
            $endDt = $break_date . ' ' . $end_time;
            $endTs = strtotime($endDt);
            if (!$endTs) { echo json_encode(['success' => false, 'message' => 'Invalid end time.']); exit; }
            if ($endTs <= $startTs) {
                echo json_encode(['success' => false, 'message' => 'End time must be after start time.']); exit;
            }
            $endEsc = mysqli_real_escape_string($con, date('Y-m-d H:i:s', $endTs));
            $duration = (int) floor(($endTs - $startTs) / 60);
            $endSql = "'$endEsc'";
            $durSql = (string) $duration;
        } else {
            // Open break — only one per user allowed.
            $existing = nhrm_break_open_for($con, $user_id);
            if ($existing) {
                echo json_encode(['success' => false, 'message' => 'This employee already has an open break.']);
                exit;
            }
        }

        $startEsc = mysqli_real_escape_string($con, date('Y-m-d H:i:s', $startTs));
        $dateEsc  = mysqli_real_escape_string($con, date('Y-m-d', $startTs));
        $createdBy = $sessionUserId;

        $breakType = nhrm_normalize_break_type($_POST['break_type'] ?? 'other');
        $sql = "INSERT INTO hrm_breaks (user_id, break_date, start_time, end_time, duration_minutes, reason, break_type, created_by)
                VALUES ($user_id, '$dateEsc', '$startEsc', $endSql, $durSql, '$reason', '$breakType', $createdBy)";

        if (mysqli_query($con, $sql)) {
            echo json_encode(['success' => true, 'id' => mysqli_insert_id($con)]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        }
        break;
    }

    case 'end': {
        // End by id (preferred). Fallback: end the requester's own open break.
        $id = (int) ($_POST['id'] ?? 0);
        $row = $id ? nhrm_break_row($con, $id) : nhrm_break_open_for($con, $sessionUserId);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'No open break to end.']);
            exit;
        }
        if (!$isAdmin && (int) $row['user_id'] !== $sessionUserId) {
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }
        if (!empty($row['end_time'])) {
            echo json_encode(['success' => false, 'message' => 'This break is already closed.']);
            exit;
        }

        $rid = (int) $row['id'];
        $sql = "UPDATE hrm_breaks
                SET end_time = NOW(),
                    duration_minutes = GREATEST(0, TIMESTAMPDIFF(MINUTE, start_time, NOW()))
                WHERE id=$rid";
        if (mysqli_query($con, $sql)) {
            $updated = nhrm_break_row($con, $rid);
            echo json_encode(['success' => true, 'data' => $updated]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        }
        break;
    }

    case 'delete': {
        $id  = (int) ($_POST['id'] ?? 0);
        $row = nhrm_break_row($con, $id);
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Not found']); exit; }

        if (!$isAdmin && (int) $row['user_id'] !== $sessionUserId) {
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }

        if (mysqli_query($con, "DELETE FROM hrm_breaks WHERE id=$id")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        }
        break;
    }

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
