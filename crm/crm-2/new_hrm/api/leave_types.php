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

hrm_ensure_break_schema();

$action = $_REQUEST['action'] ?? 'list';

switch ($action) {

    case 'list': {
        // Everyone logged in can read leave types (needed for the apply form);
        // non-admins only see active ones.
        $whereStr = $isAdmin ? '' : 'WHERE is_active=1';
        $r = mysqli_query($con, "SELECT * FROM hrm_leave_types $whereStr ORDER BY id ASC");
        $rows = [];
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) { $rows[] = $row; }
        }
        echo json_encode(['data' => $rows]);
        break;
    }

    case 'get': {
        if (!$isAdmin) { echo json_encode(null); exit; }
        $id = (int) ($_GET['id'] ?? 0);
        $r  = mysqli_query($con, "SELECT * FROM hrm_leave_types WHERE id=$id");
        echo json_encode($r ? (mysqli_fetch_assoc($r) ?: null) : null);
        break;
    }

    case 'create': {
        if (!$isAdmin) { echo json_encode(['success' => false, 'message' => 'Forbidden']); exit; }

        $name     = mysqli_real_escape_string($con, trim($_POST['name'] ?? ''));
        $color    = mysqli_real_escape_string($con, trim($_POST['color'] ?? '#4f46e5'));
        $max_days = (int) ($_POST['max_days'] ?? 0);
        $is_paid  = !empty($_POST['is_paid']) ? 1 : 0;

        if ($name === '') { echo json_encode(['success' => false, 'message' => 'Name is required']); exit; }

        $sql = "INSERT INTO hrm_leave_types (name, color, max_days, is_paid) VALUES ('$name', '$color', $max_days, $is_paid)";
        if (mysqli_query($con, $sql)) {
            echo json_encode(['success' => true, 'id' => mysqli_insert_id($con)]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        }
        break;
    }

    case 'update': {
        if (!$isAdmin) { echo json_encode(['success' => false, 'message' => 'Forbidden']); exit; }

        $id        = (int) ($_POST['id'] ?? 0);
        $name      = mysqli_real_escape_string($con, trim($_POST['name'] ?? ''));
        $color     = mysqli_real_escape_string($con, trim($_POST['color'] ?? '#4f46e5'));
        $max_days  = (int) ($_POST['max_days'] ?? 0);
        $is_active = (int) ($_POST['is_active'] ?? 1);
        $is_paid   = !empty($_POST['is_paid']) ? 1 : 0;

        $sql = "UPDATE hrm_leave_types
                SET name='$name', color='$color', max_days=$max_days, is_active=$is_active, is_paid=$is_paid
                WHERE id=$id";
        if (mysqli_query($con, $sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        }
        break;
    }

    case 'delete': {
        if (!$isAdmin) { echo json_encode(['success' => false, 'message' => 'Forbidden']); exit; }
        $id = (int) ($_POST['id'] ?? 0);
        if (mysqli_query($con, "DELETE FROM hrm_leave_types WHERE id=$id")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        }
        break;
    }

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
