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

$action = $_REQUEST['action'] ?? 'list';

function nhrm_fetch_leave($con, $id)
{
    $id = (int) $id;
    $res = mysqli_query($con, "SELECT * FROM hrm_leaves WHERE id=$id LIMIT 1");
    return $res ? mysqli_fetch_assoc($res) : null;
}

switch ($action) {

    case 'list': {
        $status   = mysqli_real_escape_string($con, $_GET['status'] ?? '');
        $reqUser  = (int) ($_GET['user_id'] ?? 0);

        $where = [];
        if ($status) {
            $where[] = "l.status='$status'";
        }
        if (!$isAdmin) {
            $where[] = "l.user_id=" . $sessionUserId;
        } elseif ($reqUser) {
            $where[] = "l.user_id=$reqUser";
        }
        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT l.*, lt.name AS leave_type_name, lt.color AS leave_color,
                       e.employee_code, u.user_name, hu.full_name
                FROM hrm_leaves l
                LEFT JOIN hrm_leave_types lt ON lt.id = l.leave_type_id
                LEFT JOIN hrm_employees e ON e.user_id = l.user_id
                LEFT JOIN users u ON u.user_id = l.user_id
                LEFT JOIN hrm_users hu ON hu.user_id = l.user_id
                $whereStr
                ORDER BY l.applied_at DESC";

        $r = mysqli_query($con, $sql);
        $rows = [];
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) {
                $row['from_date_fmt'] = date('d M Y', strtotime($row['from_date']));
                $row['to_date_fmt']   = date('d M Y', strtotime($row['to_date']));
                $row['applied_fmt']   = date('d M Y h:i A', strtotime($row['applied_at']));
                $row['display_name']  = $row['full_name'] ?: $row['user_name'] ?: ('User ' . $row['user_id']);
                $rows[] = $row;
            }
        }
        echo json_encode(['data' => $rows]);
        break;
    }

    case 'get': {
        $id = (int) ($_GET['id'] ?? 0);
        $row = nhrm_fetch_leave($con, $id);
        if (!$row) { echo json_encode(null); exit; }
        if (!$isAdmin && (int) $row['user_id'] !== $sessionUserId) {
            echo json_encode(null); exit;
        }
        echo json_encode($row);
        break;
    }

    case 'apply': {
        // Employees can apply for themselves; admins can apply on behalf of anyone.
        $postedUser = (int) ($_POST['user_id'] ?? 0);
        $user_id    = $isAdmin && $postedUser ? $postedUser : $sessionUserId;

        $leave_type_id = (int) ($_POST['leave_type_id'] ?? 0);
        $from_date     = mysqli_real_escape_string($con, $_POST['from_date'] ?? '');
        $to_date       = mysqli_real_escape_string($con, $_POST['to_date'] ?? '');
        $reason        = mysqli_real_escape_string($con, trim($_POST['reason'] ?? ''));

        if (!$user_id || !$leave_type_id || !$from_date || !$to_date) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']); exit;
        }
        if ($from_date > $to_date) {
            echo json_encode(['success' => false, 'message' => 'From date must not be after To date']); exit;
        }

        $total_days = (int) ((strtotime($to_date) - strtotime($from_date)) / 86400) + 1;

        $sql = "INSERT INTO hrm_leaves (user_id, leave_type_id, from_date, to_date, total_days, reason, status, applied_at)
                VALUES ($user_id, $leave_type_id, '$from_date', '$to_date', $total_days, '$reason', 'pending', NOW())";

        if (mysqli_query($con, $sql)) {
            echo json_encode(['success' => true, 'id' => mysqli_insert_id($con)]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        }
        break;
    }

    case 'assign': {
        if (!$isAdmin) { echo json_encode(['success' => false, 'message' => 'Forbidden']); exit; }

        $user_id       = (int) ($_POST['user_id'] ?? 0);
        $leave_type_id = (int) ($_POST['leave_type_id'] ?? 0);
        $from_date     = mysqli_real_escape_string($con, $_POST['from_date'] ?? '');
        $to_date       = mysqli_real_escape_string($con, $_POST['to_date'] ?? '');
        $reason        = mysqli_real_escape_string($con, trim($_POST['reason'] ?? ''));
        $admin_note    = mysqli_real_escape_string($con, trim($_POST['admin_note'] ?? ''));

        if (!$user_id || !$leave_type_id || !$from_date || !$to_date) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']); exit;
        }
        if ($from_date > $to_date) {
            echo json_encode(['success' => false, 'message' => 'From date must not be after To date']); exit;
        }

        $total_days = (int) ((strtotime($to_date) - strtotime($from_date)) / 86400) + 1;

        $sql = "INSERT INTO hrm_leaves (user_id, leave_type_id, from_date, to_date, total_days, reason, status, admin_note, applied_at, reviewed_at)
                VALUES ($user_id, $leave_type_id, '$from_date', '$to_date', $total_days, '$reason', 'approved', '$admin_note', NOW(), NOW())";

        if (mysqli_query($con, $sql)) {
            echo json_encode(['success' => true, 'id' => mysqli_insert_id($con)]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        }
        break;
    }

    case 'approve':
    case 'reject': {
        if (!$isAdmin) { echo json_encode(['success' => false, 'message' => 'Forbidden']); exit; }

        $id         = (int) ($_POST['id'] ?? 0);
        $admin_note = mysqli_real_escape_string($con, trim($_POST['admin_note'] ?? ''));
        $newStatus  = $action === 'approve' ? 'approved' : 'rejected';

        $sql = "UPDATE hrm_leaves
                SET status='$newStatus', admin_note='$admin_note', reviewed_at=NOW()
                WHERE id=$id";

        if (mysqli_query($con, $sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        }
        break;
    }

    case 'delete': {
        $id = (int) ($_POST['id'] ?? 0);
        $row = nhrm_fetch_leave($con, $id);
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Not found']); exit; }

        // Employee may only delete their own pending request.
        if (!$isAdmin) {
            if ((int) $row['user_id'] !== $sessionUserId) {
                echo json_encode(['success' => false, 'message' => 'Forbidden']); exit;
            }
            if (($row['status'] ?? '') !== 'pending') {
                echo json_encode(['success' => false, 'message' => 'Only pending requests can be cancelled']); exit;
            }
        }

        if (mysqli_query($con, "DELETE FROM hrm_leaves WHERE id=$id")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        }
        break;
    }

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
