<?php
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/activity_log.php';
include 'includes/customizer.php';

function pd_project_member_id($con, $project_id)
{
    $res = mysqli_query($con, "SELECT mem_id FROM projects WHERE order_id='" . mysqli_real_escape_string($con, $project_id) . "'");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row['mem_id'] ?? null;
}

$current_user_id = $_SESSION['user_id'];
$current_user_type = $_SESSION['user_type'];
$client_id = $_GET['id'];
$project_id = isset($_GET['id']) && is_numeric($_GET['id']) ? mysqli_real_escape_string($con, $_GET['id']) : null;

// Admins (user_type 1) can open any project. Other staff need the
// "View All Staff Projects" permission to open projects not assigned to them.
$can_view_all_projects = ($current_user_type == 1);
if (!$can_view_all_projects) {
    $vapRes = mysqli_query($con, "SELECT view_all_projects FROM user_permissions WHERE user_id = '$current_user_id' LIMIT 1");
    if ($vapRes && ($vapRow = mysqli_fetch_assoc($vapRes)) && (($vapRow['view_all_projects'] ?? 'No') === 'Yes')) {
        $can_view_all_projects = true;
    }
}

// Admins can always see project invoices. Other staff need the "View Invoices" permission.
$can_view_invoices = ($current_user_type == 1);
if (!$can_view_invoices) {
    $viRes = mysqli_query($con, "SELECT view_invoices FROM user_permissions WHERE user_id = '$current_user_id' LIMIT 1");
    if ($viRes && ($viRow = mysqli_fetch_assoc($viRes)) && (($viRow['view_invoices'] ?? 'No') === 'Yes')) {
        $can_view_invoices = true;
    }
}

// Current user initials for bookkeeping month checkboxes
$bkCurrentUser     = $_SESSION['fullname'] ?? $_SESSION['username'] ?? '';
if (!$bkCurrentUser) {
    $uq = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '$current_user_id' LIMIT 1");
    if ($uq && ($ur = mysqli_fetch_assoc($uq))) $bkCurrentUser = $ur['user_name'] ?? '';
}
$bkCurrentInitials = 'ME';
if ($bkCurrentUser) {
    $parts = array_values(array_filter(explode(' ', trim($bkCurrentUser))));
    if ($parts) $bkCurrentInitials = strtoupper(implode('', array_map(fn($p) => $p[0], $parts)));
}

// AJAX: toggle bookkeeping month from project details page
if (!empty($_POST['bk_action']) && $_POST['bk_action'] === 'toggle_month') {
    header('Content-Type: application/json');
    $pid  = (int)($_POST['bk_pid']  ?? 0);
    $midx = (int)($_POST['bk_midx'] ?? 0);
    $chk  = (int)($_POST['bk_checked'] ?? 0);
    $init = $chk ? $bkCurrentInitials : '';
    $stmt = mysqli_prepare($con, "INSERT INTO bk_month_assignments (project_id, month_index, initials, is_checked, checked_by, checked_at)
        VALUES (?,?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE initials=VALUES(initials), is_checked=VALUES(is_checked), checked_by=VALUES(checked_by), checked_at=VALUES(checked_at)");
    mysqli_stmt_bind_param($stmt, 'iisis', $pid, $midx, $init, $chk, $bkCurrentUser);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => (bool)$ok, 'initials' => $init]);
    exit;
}

$user_query = "SELECT * FROM users WHERE user_id = '$current_user_id'";
$user_result = mysqli_query($con, $user_query);
$user = mysqli_fetch_assoc($user_result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['complete_project'])) {
        $project_id = mysqli_real_escape_string($con, $_POST['project_id']);
        $query = "UPDATE projects SET project_status = 'completed' WHERE order_id = '$project_id'";
        mysqli_query($con, $query);
        // header("Location: project_details.php?id=$project_id&status=completed");
        // exit();
    }
    if (isset($_POST['completion_comment']) && !empty($_POST['completion_comment'])) {
        $comment = mysqli_real_escape_string($con, $_POST['completion_comment']);
        $current_user_id = $_SESSION['user_id'];
        $comment_query = "INSERT INTO project_comments (project_id, user_id, comment, created_at, status)
                         VALUES ('$project_id', '$current_user_id', '$comment', NOW(), 'Completed')";
        if (mysqli_query($con, $comment_query)) {
            $new_comment_id = mysqli_insert_id($con);
            $pd_mem_id = pd_project_member_id($con, $project_id);
            logActivity($con, $current_user_id, 'CREATE', 'project_comments', $new_comment_id, "New project comment added for Project ID: {$project_id}" . ($pd_mem_id ? ", Member ID: {$pd_mem_id}." : "."));
        }
    }
    if (isset($_POST['reopen_project'])) {
        $project_id = mysqli_real_escape_string($con, $_POST['project_id']);
        $query = "UPDATE projects SET project_status = 'open' WHERE order_id = '$project_id'";
        mysqli_query($con, $query);
        // header("Location: project_details.php?id=$project_id&status=reopened");
        // exit();
    }


    if (isset($_POST['edit_comment'])) {
        $comment_id = isset($_POST['comment_id']) && is_numeric($_POST['comment_id']) ? mysqli_real_escape_string($con, $_POST['comment_id']) : null;
        $comment_text = isset($_POST['comment']) ? mysqli_real_escape_string($con, $_POST['comment']) : '';

        if ($comment_id && !empty($comment_text)) {
            // First verify the comment belongs to the current user (unless admin)
            $verify_query = "SELECT user_id FROM project_comments WHERE id = '$comment_id'";
            $verify_result = mysqli_query($con, $verify_query);

            if (mysqli_num_rows($verify_result) > 0) {
                $comment_data = mysqli_fetch_assoc($verify_result);

                // Only allow edit if user is admin or comment owner
                if ($current_user_type == 1 || $comment_data['user_id'] == $current_user_id) {
                    $update_query = "UPDATE project_comments SET comment = '$comment_text' WHERE id = '$comment_id'";

                    if (mysqli_query($con, $update_query)) {
                        $pd_mem_id = pd_project_member_id($con, $project_id);
                        logActivity($con, $current_user_id, 'UPDATE', 'project_comments', $comment_id, "Updated project comment ID: {$comment_id} for Project ID: {$project_id}" . ($pd_mem_id ? ", Member ID: {$pd_mem_id}." : "."));
                        $_SESSION['success_message'] = "Comment updated successfully!";
                    } else {
                        $_SESSION['error_message'] = "Error updating comment: " . mysqli_error($con);
                    }
                } else {
                    $_SESSION['error_message'] = "You don't have permission to edit this comment.";
                }
            } else {
                $_SESSION['error_message'] = "Comment not found.";
            }

            // Redirect back to the project page
            header("Location: project_details.php?id=$project_id");
            exit();
        }
    }

    // Handle comment deletion
    if (isset($_POST['delete_comment'])) {
        $comment_id = isset($_POST['comment_id']) && is_numeric($_POST['comment_id']) ? mysqli_real_escape_string($con, $_POST['comment_id']) : null;

        if ($comment_id) {
            // First verify the comment belongs to the current user (unless admin)
            $verify_query = "SELECT user_id FROM project_comments WHERE id = '$comment_id'";
            $verify_result = mysqli_query($con, $verify_query);

            if (mysqli_num_rows($verify_result) > 0) {
                $comment_data = mysqli_fetch_assoc($verify_result);

                // Only allow delete if user is admin or comment owner
                if ($current_user_type == 1 || $comment_data['user_id'] == $current_user_id) {
                    $delete_query = "DELETE FROM project_comments WHERE id = '$comment_id'";

                    if (mysqli_query($con, $delete_query)) {
                        $pd_mem_id = pd_project_member_id($con, $project_id);
                        logActivity($con, $current_user_id, 'DELETE', 'project_comments', $comment_id, "Deleted project comment ID: {$comment_id} for Project ID: {$project_id}" . ($pd_mem_id ? ", Member ID: {$pd_mem_id}." : "."));
                        $_SESSION['success_message'] = "Comment deleted successfully!";
                    } else {
                        $_SESSION['error_message'] = "Error deleting comment: " . mysqli_error($con);
                    }
                } else {
                    $_SESSION['error_message'] = "You don't have permission to delete this comment.";
                }
            } else {
                $_SESSION['error_message'] = "Comment not found.";
            }

            // Redirect back to the project page
            header("Location: project_details.php?id=$project_id");
            exit();
        }
    }

}

if ($project_id) {
    if ($can_view_all_projects) {
        $projects_query = "SELECT p.*, m.name, m.mname, m.lname, m.bus_name, m.member_id AS client_id, m.status, pr.membership_name AS product_name, u.user_name AS assigned_to
                         FROM projects p
                         JOIN members m ON p.mem_id = m.member_id
                         JOIN memberships pr ON p.prod_id = pr.membership_id
                         JOIN users u ON p.assign_to = u.user_id
                         WHERE p.order_id = '$project_id'";
    } else {
        $projects_query = "SELECT p.*, m.name, m.mname, m.lname, m.bus_name, m.member_id AS client_id, m.status, pr.membership_name AS product_name, u.user_name AS assigned_to 
                         FROM projects p
                         JOIN members m ON p.mem_id = m.member_id
                         JOIN memberships pr ON p.prod_id = pr.membership_id
                         JOIN users u ON p.assign_to = u.user_id
                         WHERE p.order_id = '$project_id' AND p.assign_to = '$current_user_id'";
    }
}
$projects_result = mysqli_query($con, $projects_query);

$specific_project = null;
$linkedSpouseId = null;
$linkRow = null;
if ($project_id) {
    $lnkQ = mysqli_query($con, "SELECT link_id, spouse_id, created_at
                                FROM project_spouse_link
                                WHERE project_id = $project_id
                                LIMIT 1");
    if (mysqli_num_rows($lnkQ)) {
        $linkRow = mysqli_fetch_assoc($lnkQ);
        $linkedSpouseId = $linkRow['spouse_id'];
    }
}
if ($project_id) {
    if ($can_view_all_projects) {
        $specific_query = "SELECT p.*, m.name, m.mname, m.lname, m.bus_name, m.member_id AS client_id, m.status, m.type, pr.membership_name AS product_name, u.user_name AS assigned_to
                         FROM projects p
                         JOIN members m ON p.mem_id = m.member_id
                         JOIN memberships pr ON p.prod_id = pr.membership_id
                         JOIN users u ON p.assign_to = u.user_id
                         WHERE p.order_id = '$project_id'";
    } else {
        $specific_query = "SELECT p.*, m.name, m.mname, m.lname, m.bus_name, m.member_id AS client_id, m.status, m.type, pr.membership_name AS product_name, u.user_name AS assigned_to
                         FROM projects p
                         JOIN members m ON p.mem_id = m.member_id
                         JOIN memberships pr ON p.prod_id = pr.membership_id
                         JOIN users u ON p.assign_to = u.user_id
                         WHERE p.order_id = '$project_id' AND p.assign_to = '$current_user_id'";
    }
    $specific_result = mysqli_query($con, $specific_query);
    $specific_project = mysqli_fetch_assoc($specific_result);
}

// Bookkeeping panel data — only when this project's product is Bookkeeping
$bkIsBookkeeping = false;
$bkProjectRow    = null;
$bkMonthData     = array_fill(0, 12, ['initials' => '', 'checked' => 0]);
$bkProdName      = $specific_project['product_name'] ?? '';
$bkProdNameNorm  = strtolower(preg_replace('/\s+/', '', $bkProdName));
if ($specific_project && strpos($bkProdNameNorm, 'bookkeeping') !== false) {
    $bkIsBookkeeping = true;
    $bkTitle = mysqli_real_escape_string($con, $specific_project['project_title'] ?? '');
    $bq = mysqli_query($con, "SELECT * FROM bk_projects WHERE name = '$bkTitle' ORDER BY id DESC LIMIT 1");
    if ($bq && ($bkProjectRow = mysqli_fetch_assoc($bq))) {
        $bid = (int)$bkProjectRow['id'];
        $mq = mysqli_query($con, "SELECT month_index, initials, is_checked FROM bk_month_assignments WHERE project_id = $bid");
        if ($mq) {
            while ($mr = mysqli_fetch_assoc($mq)) {
                $bkMonthData[(int)$mr['month_index']] = [
                    'initials' => $mr['initials'] ?? '',
                    'checked'  => (int)$mr['is_checked'],
                ];
            }
        }
    }
}


// Helper: produce uppercase initials from a name (mirrors show_projects.php::getInitials)
if (!function_exists('getInitials')) {
    function getInitials($name)
    {
        $words = explode(' ', (string) $name);
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return $initials;
    }
}

// Fetch invoices attached to this project + invoices available for attaching (admin only)
$projectInvoices    = [];
$availableInvoices  = [];
if ($specific_project && $can_view_invoices) {
    $attachedIdsRaw = $specific_project['project_invoices'] ?? '';
    $attachedIds    = array_values(array_filter(array_map('trim', explode(',', $attachedIdsRaw)), 'strlen'));

    if (!empty($attachedIds)) {
        $escIds   = array_map(fn($v) => mysqli_real_escape_string($con, $v), $attachedIds);
        $idList   = "'" . implode("','", $escIds) . "'";
        $invQuery = "SELECT invoice_id, invoice_date, filelink, invoice_status, due_date, total_amount,
                            paid_amount, balance, discount, mem_id, service_provider_id, payment_details
                     FROM billing_invoice
                     WHERE invoice_id IN ($idList)
                     ORDER BY invoice_date DESC";
        $invRes = mysqli_query($con, $invQuery);
        while ($invRes && ($r = mysqli_fetch_assoc($invRes))) {
            $spName = '';
            if (!empty($r['service_provider_id'])) {
                $spId = mysqli_real_escape_string($con, $r['service_provider_id']);
                $spQ  = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '$spId' LIMIT 1");
                if ($spQ && ($spR = mysqli_fetch_assoc($spQ))) {
                    $spName = getInitials($spR['user_name']);
                }
            }
            $r['service_provider_name'] = $spName;
            $projectInvoices[] = $r;
        }
    }

    // Invoices for this client that are not already attached (attaching is admin only)
    if ($current_user_type == 1) {
    $clientId         = (int) $specific_project['client_id'];
    $alreadyAttached  = array_map('intval', $attachedIds);
    $availQuery       = "SELECT invoice_id, invoice_date, total_amount
                         FROM billing_invoice
                         WHERE mem_id = $clientId
                         ORDER BY invoice_date DESC";
    $availRes = mysqli_query($con, $availQuery);
    while ($availRes && ($r = mysqli_fetch_assoc($availRes))) {
        if (!in_array((int) $r['invoice_id'], $alreadyAttached, true)) {
            $availableInvoices[] = $r;
        }
    }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['uploadFiles'])) {
    $project_id = mysqli_real_escape_string($con, $_POST['project_id']);
    $description = isset($_POST['documentDescription']) ? mysqli_real_escape_string($con, $_POST['documentDescription']) : '';
    $user_id = $_SESSION['user_id'];

    if ($can_view_all_projects) {
        $access_query = "SELECT * FROM projects WHERE order_id = '$project_id'";
    } else {
        $access_query = "SELECT * FROM projects WHERE order_id = '$project_id' AND (assign_to = '$user_id' OR '$current_user_type' = 1)";
    }
    $access_result = mysqli_query($con, $access_query);

    if (mysqli_num_rows($access_result) == 0) {
        $uploadError = "You don't have permission to upload documents to this project.";
    } else {
        $uploadDir = __DIR__ . '/Uploads/projects/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true)) {
                $uploadError = "Project upload directory does not exist and could not be created.";
            }
        }
        if (!is_writable($uploadDir)) {
            $uploadError = "Directory is not writable.";
        }
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        if (empty($uploadError) && isset($_FILES['projectFiles']) && is_array($_FILES['projectFiles']['tmp_name'])) {
            foreach ($_FILES['projectFiles']['tmp_name'] as $key => $tmpName) {
                if (empty($tmpName))
                    continue;
                $fileName = $_FILES['projectFiles']['name'][$key];
                $fileSize = $_FILES['projectFiles']['size'][$key];
                $fileType = $_FILES['projectFiles']['type'][$key];
                $fileError = $_FILES['projectFiles']['error'][$key];
                if ($fileError !== UPLOAD_ERR_OK) {
                    continue;
                }
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if ($fileSize > $maxFileSize) {
                    $uploadError = "File $fileName exceeds the maximum allowed size.";
                    continue;
                }
                $originalSafeName = preg_replace('/[^A-Za-z0-9\.\-_]/', '', pathinfo($fileName, PATHINFO_FILENAME));
                $newFileName = $originalSafeName . "." . $fileExt;
                $filePath = $uploadDir . '/' . $newFileName;
                if (move_uploaded_file($tmpName, $filePath)) {
                    $query = "INSERT INTO project_documents 
                             (project_id, user_id, file_name, file_path, file_type, file_size, description) 
                             VALUES ('$project_id', '$user_id', '$newFileName', '$filePath', '$fileType', '$fileSize', '$description')";
                    if (mysqli_query($con, $query)) {
                        echo "<script>
                                Swal.fire({
                                    title: 'Success!',
                                    text: 'File uploaded successfully!',
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                });
                              </script>";
                    } else {
                        $uploadError = "Database error: " . mysqli_error($con);
                        unlink($filePath);
                    }
                } else {
                    $uploadError = "Failed to move $fileName. System error: " . error_get_last()['message'];
                }
            }
        } elseif (empty($uploadError)) {
            $uploadError = "No files selected.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit_comment'])) {
        $comment = mysqli_real_escape_string($con, $_POST['comment']);
        $current_user_id = $_SESSION['user_id'];
        if (!empty($comment)) {
            $comment_query = "INSERT INTO project_comments (project_id, user_id, comment, created_at, status)
                             VALUES ('$project_id', '$current_user_id', '$comment', NOW(), 'Comment')";
            if (mysqli_query($con, $comment_query)) {
                $new_comment_id = mysqli_insert_id($con);
                $pd_mem_id = pd_project_member_id($con, $project_id);
                logActivity($con, $current_user_id, 'CREATE', 'project_comments', $new_comment_id, "New project comment added for Project ID: {$project_id}" . ($pd_mem_id ? ", Member ID: {$pd_mem_id}." : "."));
                echo "<script>
                        Swal.fire({
                            title: 'Success!',
                            text: 'Comment added successfully!',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                      </script>";
            } else {
                $commentError = "Error saving comment: " . mysqli_error($con);
            }
        } else {
            $commentError = "Comment cannot be empty";
        }
    }


    /* ---- link spouse ---- */
    if (isset($_POST['link_spouse'])) {
        $pid = (int) $_POST['project_id'];
        $sid = (int) $_POST['spouse_id'];
        $uid = (int) $_SESSION['user_id'];

        mysqli_query($con, "INSERT INTO project_spouse_link (project_id, spouse_id, created_by)
                        VALUES ($pid, $sid, $uid)");
        header("Location: project_details.php?id=$pid");
        exit;
    }

    /* ---- unlink spouse ---- */
    if (isset($_POST['unlink_spouse'])) {
        $lid = (int) $_POST['link_id'];
        mysqli_query($con, "DELETE FROM project_spouse_link WHERE link_id = $lid");
        header("Location: project_details.php?id=$project_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html> 

<head>
    <title><?= $current_user_type == 1 ? 'All Projects' : 'My Projects' ?></title>
    <?php include "includes/links.php"; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <style>
        .project-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .project-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-left: 5px solid #6fd943;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            padding: 20px;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .project-card.completed {
            border-left-color: #dc3545;
            opacity: 0.9;
        }

        .project-title {
            color: #333;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-open {
            background-color: #e6f4ea;
            color: #2e7d32;
        }

        .status-completed {
            background-color: #fdecea;
            color: #c62828;
        }

        .highlighted {
            background-color: 0 6px 30px rgba(182, 186, 203, 0.3);
        }

        .action-btn {
            margin: 5px 5px 0 0;
            border-radius: 30px;
            font-size: 0.85rem;
            padding: 6px 12px;
            min-width: 90px;
        }

        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .client-link {
            color: #007bff;
            text-decoration: none;
        }

        .client-link:hover {
            text-decoration: underline;
        }

        .modal-content {
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, #6fd943, #4caf50);
            color: #fff;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .modal-footer {
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .list-group-item {
            border: none;
            border-bottom: 1px solid #f1f1f1;
            padding: 12px 15px;
        }

        .list-group-item:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>
    <?php include "includes/header.php"; ?>
    <?php include "includes/sidebar.php"; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <div class="dash-container">
        <div class="dash-content">
            <?php if (isset($_GET['status'])): ?>
                <div class="alert alert-success">
                    <?php
if ($_GET['status'] == 'completed')
echo "Project marked as completed!";
elseif ($_GET['status'] == 'reopened')
echo "Project reopened!";
elseif ($_GET['status'] == 'invoice_associated')
echo "Invoice attached to project successfully!";
elseif ($_GET['status'] == 'invoice_detached')
echo "Invoice detached from project successfully!";
elseif ($_GET['status'] == 'payment_added')
echo "Payment added successfully!";
                    ?>
                </div>
            <?php endif; ?>
            <div class="page-header">
                <h4><?= $current_user_type == 1 ? 'All Projects' : 'My Projects' ?></h4>
            </div>
            <p class="text-muted">
                <?= $current_user_type == 1 ? 'Showing all projects' : 'All projects assigned to ' . htmlspecialchars($user['user_name']) ?>
            </p>
            <div class="row">
                <?php if (mysqli_num_rows($projects_result) > 0): ?>
                    <?php while ($project = mysqli_fetch_assoc($projects_result)):
// Concatenate full name
$full_name = trim(
    htmlspecialchars($project['name']) . ' ' .
    ($project['mname'] ? htmlspecialchars($project['mname']) . ' ' : '') .
    ($project['lname'] ? htmlspecialchars($project['lname']) : '')
);
                        ?>
                        <div class="card <?= $project['project_status'] == 'completed' ? 'completed' : '' ?> 
                             <?= $project_id && $project['order_id'] == $project_id ? 'highlighted' : '' ?>">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8  p-3">
                                        <h5 class="project-title mb-3" style="font-size: 1.2rem; font-weight: 700;">
                                            Project ID:
                                            <span style="font-size: 1rem; font-weight: 500;">
                                                <?= $project['order_id'] ?>
                                            </span>
                                        </h5>
                                        <h5 class="project-title mb-3" style="font-size: 1.2rem; font-weight: 700;">
                                            Project Title:
                                            <span style="font-size: 1rem; font-weight: 500;">
                                                <?= htmlspecialchars($project['project_title']) ?>
                                            </span>
                                        </h5>


                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <div class="p-2 border rounded">
                                                    <strong>Client:</strong>
                                                    <a href="client_details.php?id=<?= $project['client_id'] ?>&status=<?= htmlspecialchars($project['status']) ?>"
                                                        class="client-link">
                                                        <?= $full_name ?> (ID: <?= $project['client_id'] ?>)
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <div class="p-2 border rounded">
                                                    <strong>Business Name:</strong>
                                                    <?= htmlspecialchars($project['bus_name'] ?? 'N/A') ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <div class="p-2 border rounded">
                                                    <strong>Product:</strong>
                                                    <?= htmlspecialchars($project['product_name']) ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <div class="p-2 border rounded">
                                                    <strong>Description:</strong>
                                                    <?= htmlspecialchars($project['project_description']) ?>
                                                </div>
                                            </div>
                                            <?php if ($current_user_type == 1): ?>
                                                <div class="col-md-4 mb-2">
                                                    <div class="p-2 border rounded">
                                                        <strong>Assigned To:</strong>
                                                        <?= htmlspecialchars($project['assigned_to']) ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php
                                            // Customizer: show admin-added custom field values for this project.
                                            if (function_exists('customizer_custom_fields')) {
                                                $cuzCustom = customizer_custom_fields($con, 'projects');
                                                $cuzVals = function_exists('customizer_record_values')
                                                    ? customizer_record_values($con, 'projects', (int) $project['order_id'])
                                                    : array();
                                                foreach ($cuzCustom as $cuzKey => $cuzDef) {
                                                    if (!$cuzDef['status']) {
                                                        continue;
                                                    }
                                                    $cuzVal = trim((string) ($cuzVals[$cuzKey] ?? ''));
                                                    if ($cuzVal === '') {
                                                        continue;
                                                    }
                                                    ?>
                                                    <div class="col-md-4 mb-2">
                                                        <div class="p-2 border rounded">
                                                            <strong><?= htmlspecialchars($cuzDef['label'] !== '' ? $cuzDef['label'] : $cuzKey) ?>:</strong>
                                                            <?= htmlspecialchars($cuzVal) ?>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <div class="col-md-4 text-right">
                                        <span class="status-badge status-<?= $project['project_status'] ?>">
                                            <?= ucfirst($project['project_status']) ?>
                                        </span>
                                        <p class="mt-2"><strong>Due:</strong> <?= $project['exp_date'] ?></p>
                                        <div class="btn-group">
                                            <?php if ($project['project_status'] == 'open' && ($current_user_type == 1 || $project['assign_to'] == $current_user_id)): ?>
                                                <button type="button" class="btn btn-success btn-sm action-btn py-5"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#completeModal<?= $project['order_id'] ?>">
                                                    <i class="fas fa-check-circle"></i> Complete
                                                </button>
                                            <?php elseif ($project['project_status'] == 'completed' && $current_user_type == 1): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="project_id" value="<?= $project['order_id'] ?>">
                                                    <button type="submit" name="reopen_project"
                                                        class="btn btn-warning btn-sm action-btn py-5">
                                                        <i class="fas fa-redo-alt"></i> Reopen
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <button class="btn btn-primary btn-sm action-btn py-5" title="View Comments History"
                                                data-bs-toggle="modal"
                                                data-bs-target="#commentsModal<?= $project['order_id'] ?>">
                                                <i class="fas fa-comment-dots"></i> Comments
                                            </button>
                                            <div class="modal fade border" id="commentsModal<?= $project['order_id'] ?>"
                                                tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                                                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                    <div class="modal-content border-0"
                                                        style="box-shadow: 0 5px 20px rgba(0,0,0,0.15); border-radius: 10px;">
                                                        <div class="modal-header text-white"
                                                            style="background:rgba(112, 217, 67, 0.88) !important;">
                                                            <h5 class="modal-title fw-bold">
                                                                <i class="fas fa-comments me-2"></i>
                                                                Comments for Project #<?= $project['order_id'] ?>
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white"
                                                                data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body p-0">
                                                            <?php
$comments_query = "SELECT pc.*, u.user_name 
                                                                             FROM project_comments pc
                                                                             LEFT JOIN users u ON pc.user_id = u.user_id
                                                                             WHERE pc.project_id = '" . $project['order_id'] . "' 
                                                                             ORDER BY pc.created_at DESC";
$comments_result = mysqli_query($con, $comments_query);
                                                            if (mysqli_num_rows($comments_result) > 0): ?>
                                                                <div class="list-group list-group-flush">
                                                                    <?php while ($comment_row = mysqli_fetch_assoc($comments_result)):
$isCurrentUser = ($comment_row['user_id'] == $current_user_id);
                                                                        ?>
                                                                        <div class="list-group-item border-0 py-3"
                                                                            style="border-bottom: 1px solid #f0f0f0 !important;">
                                                                            <div class="d-flex align-items-center">
                                                                                <div class="flex-shrink-0 d-flex align-items-center justify-content-center text-white rounded-circle me-3"
                                                                                    style="width: 36px; height: 36px; background: <?= $isCurrentUser ? 'linear-gradient(135deg, #3a7bd5 0%, #00d2ff 100%)' : 'linear-gradient(135deg, #8e9eab 0%, #eef2f3 100%)' ?>;">
                                                                                    <i class="fas fa-user fa-sm"></i>
                                                                                </div>
                                                                                <div class="flex-grow-1">
                                                                                    <div
                                                                                        class="d-flex justify-content-between align-items-center mb-1">
                                                                                        <strong
                                                                                            class="<?= $isCurrentUser ? 'text-primary' : 'text-dark' ?>"
                                                                                            style="font-size: 0.9rem;">
                                                                                            <?= htmlspecialchars($comment_row['user_name'] ?? 'User ' . $comment_row['user_id']) ?>
                                                                                            <p class="mb-0"
                                                                                                style="font-size: 0.9rem; white-space: pre-wrap;">
                                                                                                <?= htmlspecialchars($comment_row['comment']) ?>
                                                                                            </p>
                                                                                        </strong>
                                                                                        <small class="text-muted"
                                                                                            style="font-size: 0.8rem;">
                                                                                            <i class="far fa-clock me-1"></i>
                                                                                            <?= date('M j, g:i A', strtotime($comment_row['created_at'])) ?>
                                                                                        </small>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php endwhile; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="text-center py-5">
                                                                    <i class="far fa-comment-dots fa-4x mb-3"
                                                                        style="color: #e0e0e0;"></i>
                                                                    <h5 class="text-muted">No comments yet</h5>
                                                                    <p class="text-muted">Be the first to comment!</p>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer bg-light"
                                                            style="border-radius: 0 0 10px 10px;">
                                                            <button type="button"
                                                                class="btn btn-outline-primary rounded-pill px-4"
                                                                data-bs-dismiss="modal">
                                                                <i class="fas fa-times me-1"></i> Close
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal fade" id="completeModal<?= $project['order_id'] ?>" tabindex="-1"
                                                aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Complete Project
                                                                    #<?= $project['order_id'] ?></h5>
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="project_id"
                                                                    value="<?= $project['order_id'] ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Completion Comment</label>
                                                                    <textarea class="form-control" name="completion_comment"
                                                                        rows="4" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="submit" name="complete_project"
                                                                    class="btn btn-success">Complete</button>
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">Cancel</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No projects found.
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($bkIsBookkeeping): ?>
            <?php $bkMonths = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC']; ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-book me-2"></i>Bookkeeping &mdash; Monthly Assignment</h5>
                            <div class="d-flex align-items-center gap-2">
                                <small class="text-muted me-2">Check a month to mark your initials (<?= htmlspecialchars($bkCurrentInitials) ?>)</small>
                                <a href="bookeeping.php?project_id=<?= (int)$specific_project['order_id'] ?>&client_id=<?= (int)$specific_project['client_id'] ?>"
                                    class="btn btn-sm btn-info" title="Open Bookkeeping sheet">
                                    <i class="fas fa-external-link-alt me-1"></i> Open Bookkeeping
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!$bkProjectRow): ?>
                                <div class="alert alert-warning mb-0">
                                    No bookkeeping record found for this project. The bookkeeping row is created automatically when the project is created with the &quot;Bookkeeping&quot; service.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered text-center mb-0" style="table-layout:fixed;">
                                        <thead style="background:#f1f5fb;">
                                            <tr>
                                                <?php foreach ($bkMonths as $m): ?>
                                                    <th style="font-size:11px;"><?= $m ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <?php foreach ($bkMonths as $mi => $mLabel):
                                                    $info = $bkMonthData[$mi];
                                                    $chk  = (int)$info['checked'];
                                                    $init = htmlspecialchars($info['initials']);
                                                ?>
                                                    <td style="vertical-align:middle;padding:6px;">
                                                        <label style="display:flex;flex-direction:column;align-items:center;gap:2px;cursor:pointer;margin:0;">
                                                            <input type="checkbox"
                                                                data-bkpid="<?= (int)$bkProjectRow['id'] ?>"
                                                                data-bkmidx="<?= $mi ?>"
                                                                onchange="bkPdToggleMonth(this)"
                                                                <?= $chk ? 'checked' : '' ?>
                                                                style="width:16px;height:16px;accent-color:#2a8a3e;">
                                                            <span class="bk-pd-init" id="bkpd_init_<?= (int)$bkProjectRow['id'].'_'.$mi ?>" style="font-size:11px;font-weight:700;color:#1a6e30;min-height:13px;line-height:1;"><?= $init ?></span>
                                                        </label>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted d-block mt-2">All bookkeeping projects are also visible (read-only) in the Bookkeeping sheet.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <script>
            const BK_PD_INITIALS = <?= json_encode($bkCurrentInitials) ?>;
            async function bkPdToggleMonth(chk) {
                const pid    = chk.dataset.bkpid;
                const midx   = chk.dataset.bkmidx;
                const chkd   = chk.checked ? 1 : 0;
                const initEl = document.getElementById('bkpd_init_' + pid + '_' + midx);
                const prev   = initEl.textContent;
                initEl.textContent = chkd ? BK_PD_INITIALS : '';
                const fd = new FormData();
                fd.append('bk_action',  'toggle_month');
                fd.append('bk_pid',     pid);
                fd.append('bk_midx',    midx);
                fd.append('bk_checked', chkd);
                try {
                    const res  = await fetch(window.location.pathname + window.location.search, {method:'POST', body:fd});
                    const data = await res.json();
                    if (!data.success) {
                        chk.checked = !chk.checked;
                        initEl.textContent = prev;
                    }
                } catch(e) {
                    chk.checked = !chk.checked;
                    initEl.textContent = prev;
                }
            }
            </script>
            <?php endif; ?>

            <!-- Link / Unlink Family Member -->
            <?php $isImmigration = in_array((int)($specific_project['type'] ?? 0), [26, 28]); ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-user-friends me-2"></i>Link Family Member</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!$isImmigration): ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-lock me-2"></i>Family member linking is only available for <strong>Immigration</strong> and <strong>USA Immigration</strong> member types.
                                </div>
                            <?php else: ?>
                            <!-- Link Form -->
                            <form method="post" class="row mb-4">
                                <input type="hidden" name="project_id" value="<?= $specific_project['order_id'] ?>">
                                <div class="col-md-10">
                                    <label class="form-label">Select Family Member</label>
                                    <select name="spouse_id" class="form-control" required>
                                        <option value="">-- none --</option>
                                        <?php
$famQ = mysqli_query(
    $con,
    "SELECT *from spouse where member_id=" . (int) $specific_project['client_id']
);
while ($f = mysqli_fetch_assoc($famQ)):
$full = htmlspecialchars(trim($f['sp_name'] . ' ' . $f['sp_mname'] . ' ' . $f['sp_lname']));
$selected = ($linkedSpouseId == $f['spouse_id']) ? 'selected' : '';
echo "<option value='{$f['spouse_id']}' $selected>$full</option>";
endwhile;
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-1 align-self-end">
                                    <button type="submit" name="link_spouse" class="btn btn-primary btn-sm">
                                        <i class="ti ti-link"></i> Save Link
                                    </button>
                                </div>
                            </form>

                            <!-- Linked Table -->
                            <?php if ($linkedSpouseId):
$s = mysqli_fetch_assoc(mysqli_query(
        $con,
        "SELECT sp_name, sp_mname, sp_lname, sp_ss, sp_relation, sp_time
                                    FROM spouse
                                    WHERE spouse_id = $linkedSpouseId"
));
                                ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered mt-3">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>SSN</th>
                                                <th>Relation</th>
                                                <th>Linked Since</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?= htmlspecialchars(trim($s['sp_name'] . ' ' . $s['sp_mname'] . ' ' . $s['sp_lname'])) ?>
                                                </td>
                                                <td><?= $s['sp_ss'] ?></td>
                                                <td><?= $s['sp_relation'] ?></td>
                                                <td><?= date('m/d/y H:i', strtotime($linkRow['created_at'])) ?></td>
                                                <td>
                                                    <form method="post">
                                                        <input type="hidden" name="link_id"
                                                            value="<?= $linkRow['link_id'] ?>">
                                                        <button type="submit" name="unlink_spouse"
                                                            class="btn btn-sm btn-danger">
                                                            <i class="ti ti-unlink"></i> Unlink
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3 text-muted">No family member linked yet</div>
                            <?php endif; ?>
                            <?php endif; // end $isImmigration ?>
</div>
                    </div>
                </div>
            </div>

            <?php if ($project_id && $specific_project):
                // Concatenate full name for specific project
                $specific_full_name = trim(
                    htmlspecialchars($specific_project['name']) . ' ' .
                    ($specific_project['mname'] ? htmlspecialchars($specific_project['mname']) . ' ' : '') .
                    ($specific_project['lname'] ? htmlspecialchars($specific_project['lname']) : '')
                );
                ?>

                <?php if ($can_view_invoices): ?>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Attached Invoices
                                    <span class="badge bg-primary ms-2"><?= count($projectInvoices) ?></span>
                                </h5>
                                <?php if ($current_user_type == 1): ?>
                                <button type="button" class="btn btn-sm btn-success"
                                    data-bs-toggle="modal" data-bs-target="#pdSelectInvoiceModal"
                                    title="Attach Existing Invoice">
                                    <i class="fas fa-link me-1"></i> Attach Invoice
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($projectInvoices)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered text-center align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Invoice ID</th>
                                                    <th>Date</th>
                                                    <th>Due Date</th>
                                                    <th>Total</th>
                                                    <th>Balance</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($projectInvoices as $inv):
switch ((int) $inv['invoice_status']) {
    case 1: $stText = 'Unpaid';       $stClass = 'bg-danger';            break;
    case 2: $stText = 'Partial Paid'; $stClass = 'bg-warning text-dark'; break;
    case 3: $stText = 'Paid';         $stClass = 'bg-success';           break;
    default: $stText = 'Unknown';     $stClass = 'bg-secondary';
}
$invDate = !empty($inv['invoice_date']) ? date('m/d/Y', strtotime($inv['invoice_date'])) : 'N/A';
$dueDate = !empty($inv['due_date'])     ? date('m/d/Y', strtotime($inv['due_date']))     : 'N/A';
$totalA  = isset($inv['total_amount']) ? number_format($inv['total_amount'], 2) : '0.00';
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($inv['invoice_id']) ?></td>
                                                    <td><?= $invDate ?></td>
                                                    <td><?= $dueDate ?></td>
                                                    <td>$<?= $totalA ?></td>
                                                    <td>$<?= htmlspecialchars($inv['balance']) ?></td>
                                                    <td><span class="badge <?= $stClass ?>"><?= $stText ?></span></td>
                                                    <td>
                                                        <a href="invoices/<?= htmlspecialchars($inv['filelink']) ?>"
                                                           target="_blank"
                                                           class="btn btn-sm btn-primary"
                                                           title="View Invoice">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($current_user_type == 1): ?>
                                                        <button type="button" class="btn btn-sm btn-success pd-add-payment-btn"
                                                            data-invoice-id="<?= htmlspecialchars($inv['invoice_id']) ?>"
                                                            data-total-amount="<?= htmlspecialchars($inv['total_amount']) ?>"
                                                            data-paid-amount="<?= htmlspecialchars($inv['paid_amount']) ?>"
                                                            data-balance="<?= htmlspecialchars($inv['balance']) ?>"
                                                            data-discount="<?= htmlspecialchars($inv['discount']) ?>"
                                                            data-client-id="<?= htmlspecialchars($inv['mem_id']) ?>"
                                                            data-service-provider-name="<?= htmlspecialchars($inv['service_provider_name']) ?>"
                                                            data-bs-toggle="modal" data-bs-target="#pdAddPaymentModal"
                                                            title="Add Payment">
                                                            <i class="fas fa-money-bill"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-info pd-payment-history-btn"
                                                            data-invoice-id="<?= htmlspecialchars($inv['invoice_id']) ?>"
                                                            data-bs-toggle="modal" data-bs-target="#pdPaymentHistoryModal"
                                                            title="Payment History">
                                                            <i class="fas fa-history"></i>
                                                        </button>
                                                        <form method="POST" action="show_projects.php" class="d-inline"
                                                              onsubmit="return confirm('Detach this invoice from the project?');">
                                                            <input type="hidden" name="detach_invoice" value="1">
                                                            <input type="hidden" name="project_id"  value="<?= htmlspecialchars($specific_project['order_id']) ?>">
                                                            <input type="hidden" name="invoice_id"  value="<?= htmlspecialchars($inv['invoice_id']) ?>">
                                                            <input type="hidden" name="return_to"   value="project_details">
                                                            <button type="submit" class="btn btn-sm btn-danger" title="Detach Invoice">
                                                                <i class="fas fa-unlink"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-file-invoice fa-3x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">No invoices attached to this project yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-file-upload me-2"></i>Upload Documents</h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($uploadError)): ?>
                                    <div class="alert alert-danger"><?= htmlspecialchars($uploadError) ?></div>
                                <?php endif; ?>
                                <?php if (isset($uploadSuccess)): ?>
                                    <div class="alert alert-success">Files uploaded successfully!</div>
                                <?php endif; ?>
                                <form method="POST" enctype="multipart/form-data" class="mb-4">
                                    <input type="hidden" name="project_id" value="<?= $specific_project['order_id'] ?>">
                                    <div class="mb-3">
                                        <label for="projectFiles" class="form-label">Select files to upload</label>
                                        <input class="form-control" type="file" id="projectFiles" name="projectFiles[]"
                                            multiple required>
                                        <div class="form-text">
                                            Allowed formats: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF (Max 10MB each)
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="documentDescription" class="form-label">Description (Optional)</label>
                                        <textarea class="form-control" id="documentDescription" name="documentDescription"
                                            rows="2"></textarea>
                                    </div>
                                    <div class="card-footer bg-light">
                                        <button type="submit" name="uploadFiles" class="btn btn-success">
                                            <i class="fas fa-upload me-1"></i> Upload Files
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-comments me-2"></i>Project Comments</h5>
                                <span class="badge bg-primary">
                                    <?php
                                    $commentCountQuery = "SELECT COUNT(*) as total FROM project_comments 
                                                        WHERE project_id = '$project_id'";
                                    $commentCountResult = mysqli_query($con, $commentCountQuery);
                                    $commentCount = mysqli_fetch_assoc($commentCountResult)['total'];
                                    echo $commentCount . " comment" . ($commentCount != 1 ? 's' : '');
                                    ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="mb-4">
                                    <input type="hidden" name="project_id" value="<?= $project_id ?>">
                                    <div class="mb-3">
                                        <label for="comment" class="form-label">Add Comment</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="3"
                                            required></textarea>
                                    </div>
                                    <button type="submit" name="submit_comment" class="btn btn-primary">
                                        <i class="fas fa-comment me-1"></i> Post Comment
                                    </button>
                                </form>
                                <?php
                                $comments_query = "SELECT pc.*, u.user_name 
                                                 FROM project_comments pc
                                                 LEFT JOIN users u ON pc.user_id = u.user_id
                                                 WHERE pc.project_id = '$project_id' 
                                                 ORDER BY pc.created_at DESC";
                                $comments_result = mysqli_query($con, $comments_query);
                                if (mysqli_num_rows($comments_result) > 0): ?>
                                    <div class="list-group">
                                        <?php while ($comment = mysqli_fetch_assoc($comments_result)):
                                            $isCurrentUser = ($comment['user_id'] == $current_user_id);
                                            ?>
                                            <div class="list-group-item mb-3 rounded shadow-sm">
                                                <div class="d-flex align-items-start">
                                                    <div class="flex-shrink-0">
                                                        <div class="user-icon bg-<?= $isCurrentUser ? 'primary' : 'secondary' ?> 
                                                            text-white rounded-circle d-flex align-items-center justify-content-center"
                                                            style="width: 40px; height: 40px;">
                                                            <?= strtoupper(substr($comment['user_name'], 0, 1)) ?>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <h6 class="mb-0">
                                                                <?= htmlspecialchars($comment['user_name']) ?>
                                                                <?php if ($isCurrentUser): ?>
                                                                    <span class="badge bg-info">You</span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <small class="text-muted">
                                                                <?= date('M j, Y \a\t g:i a', strtotime($comment['created_at'])) ?>
                                                            </small>
                                                        </div>
                                                        <p class="mb-0"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                                                        <div class="d-flex justify-content-end mt-2">
                                                            <div class="btn-group btn-group-sm">
                                                                <button class="btn btn-outline-primary edit-comment"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#editCommentModal<?= $comment['id'] ?>"
                                                                    title="Edit Comment">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-outline-danger delete-comment"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#deleteCommentModal<?= $comment['id'] ?>"
                                                                    title="Delete Comment">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <?php if ($comment['status']): ?>
                                                            <span
                                                                class="badge bg-<?= $comment['status'] == 'Completed' ? 'success' : 'secondary' ?> mt-2">
                                                                <?= $comment['status'] ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Edit Comment Modal -->
                                            <div class="modal fade" id="editCommentModal<?= $comment['id'] ?>" tabindex="-1"
                                                aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Edit Comment</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                    aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="comment_id"
                                                                    value="<?= $comment['id'] ?>">
                                                                <div class="mb-3">
                                                                    <label for="comment<?= $comment['id'] ?>"
                                                                        class="form-label">Comment</label>
                                                                    <textarea class="form-control" id="comment<?= $comment['id'] ?>"
                                                                        name="comment" rows="4"
                                                                        required><?= htmlspecialchars($comment['comment']) ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="submit" name="edit_comment"
                                                                    class="btn btn-primary">Save Changes</button>
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">Cancel</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Delete Comment Modal -->
                                            <div class="modal fade" id="deleteCommentModal<?= $comment['id'] ?>" tabindex="-1"
                                                aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Delete Comment</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                    aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="comment_id"
                                                                    value="<?= $comment['id'] ?>">
                                                                <p>Are you sure you want to delete this comment? This action cannot
                                                                    be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="submit" name="delete_comment"
                                                                    class="btn btn-danger">Delete</button>
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">Cancel</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No comments yet. Be the first to add one!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($project_id): ?>
                <div class="alert alert-danger mt-4">
                    Project not found or you don't have permission to view it.
                </div>
            <?php endif; ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-folder-open me-2"></i>Project Documents</h5>
                            <span class="badge bg-primary">
                                <?php
                                $docCountQuery = "SELECT COUNT(*) as total FROM project_documents WHERE project_id = '$project_id'";
                                $docCountResult = mysqli_query($con, $docCountQuery);
                                $docCount = mysqli_fetch_assoc($docCountResult)['total'];
                                echo $docCount . " document" . ($docCount != 1 ? 's' : '');
                                ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <?php
                            $documentsQuery = "SELECT d.*, u.user_name, u.user_email 
                                             FROM project_documents d
                                             JOIN users u ON d.user_id = u.user_id
                                             WHERE d.project_id = '$project_id' 
                                             ORDER BY d.upload_date DESC";
                            $documentsResult = mysqli_query($con, $documentsQuery);
                            if (mysqli_num_rows($documentsResult) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>File</th>
                                                <th>Uploaded By</th>
                                                <th>Description</th>
                                                <th>Size</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($document = mysqli_fetch_assoc($documentsResult)):
                                                $fileSize = round($document['file_size'] / 1024, 1); // KB
                                                $fileIcon = getFileIcon($document['file_type']);
                                                $uploadDate = date('M j, Y g:i A', strtotime($document['upload_date']));
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="<?= $fileIcon ?> fa-lg me-3"></i>
                                                            <div>
                                                                <div class="fw-bold">
                                                                    <?= htmlspecialchars($document['file_name']) ?>
                                                                </div>
                                                                <small
                                                                    class="text-muted"><?= strtoupper(pathinfo($document['file_name'], PATHINFO_EXTENSION)) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div><?= htmlspecialchars($document['user_name']) ?></div>
                                                        <small
                                                            class="text-muted"><?= htmlspecialchars($document['user_email']) ?></small>
                                                    </td>
                                                    <td><?= !empty($document['description']) ? htmlspecialchars($document['description']) : '<span class="text-muted">No description</span>' ?>
                                                    </td>
                                                    <td><?= $fileSize ?> KB</td>
                                                    <td><?= $uploadDate ?></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="Uploads/projects/<?= htmlspecialchars($document['file_name']) ?>"
                                                                class="btn btn-outline-primary" download>
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                            <a href="Uploads/projects/<?= htmlspecialchars($document['file_name']) ?>"
                                                                class="btn btn-outline-secondary" target="_blank">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <?php if ($current_user_type == 1): ?>
                                                                <button class="btn btn-outline-danger delete-document"
                                                                    data-id="<?= $document['document_id'] ?>"
                                                                    data-file="<?= htmlspecialchars($document['file_name']) ?>"
                                                                    title="Delete">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-outline-danger"
                                                                    title="You don't have permission to delete" disabled>
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                                    <p class="text-muted">No documents uploaded yet</p>
                                    <p class="text-muted">Upload your first document using the form above</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    function getFileIcon($fileType)
    {
        $icons = [
            'pdf' => 'fa-file-pdf text-danger',
            'word' => 'fa-file-word text-primary',
            'excel' => 'fa-file-excel text-success',
            'image' => 'fa-file-image text-info',
            'default' => 'fa-file text-secondary'
        ];
        if (strpos($fileType, 'pdf') !== false)
            return 'fas ' . $icons['pdf'];
        if (strpos($fileType, 'word') !== false)
            return 'fas ' . $icons['word'];
        if (strpos($fileType, 'excel') !== false)
            return 'fas ' . $icons['excel'];
        if (strpos($fileType, 'image') !== false)
            return 'fas ' . $icons['image'];
        return 'fas ' . $icons['default'];
    }
    ?>
    <script>
        $(document).ready(function () {
            $('.delete-document').click(function (e) {
                e.preventDefault();
                const documentId = $(this).data('id');
                const row = $(this).closest('tr');
                if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
                    $.ajax({
                        url: 'delete_document.php',
                        method: 'POST',
                        data: {
                            document_id: documentId,

                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                row.fadeOut(300, function () {
                                    $(this).remove();
                                    showAlert('Document deleted successfully', 'success');
                                });
                            } else {
                                showAlert('Error: ' + response.error, 'danger');
                            }
                        },
                        error: function (xhr, status, error) {
                            showAlert('Error communicating with server: ' + error, 'danger');
                        }
                    });
                }
            });
            $('.view-document').click(function (e) {
                e.preventDefault();
                const filePath = $(this).data('path');
                const fileExt = filePath.split('.').pop().toLowerCase();
                if (fileExt === 'pdf') {
                    window.open(filePath, '_blank');
                } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
                    $('#previewModal .modal-body').html(`<img src="${filePath}" class="img-fluid" alt="Document preview">`);
                    $('#previewModal').modal('show');
                } else {
                    $('#previewModal .modal-body').html(`
                        <div class="text-center py-4">
                            <i class="fas fa-file fa-5x text-muted mb-3"></i>
                            <h5>Preview not available</h5>
                            <p>This file type cannot be previewed in browser.</p>
                            <a href="${filePath}" class="btn btn-primary" download>
                                <i class="fas fa-download me-2"></i>Download File
                            </a>
                        </div>
                    `);
                    $('#previewModal').modal('show');
                }
            });
            function showAlert(message, type) {
                const alert = $(`<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`);
                $('.dash-content').prepend(alert);
                setTimeout(() => alert.alert('close'), 5000);
            }
            $(document).on('click', '[data-bs-toggle="modal"]', function () {
                $('.modal.show').modal('hide');
                $('.modal-backdrop').remove();
            });
            $('.modal').on('hidden.bs.modal', function () {
                $('body').removeClass('modal-open');
                $('body').css('padding-right', '');
            });
        });
    </script>
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Document Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($project_id && $specific_project && $current_user_type == 1): ?>
    <!-- Attach Existing Invoice Modal -->
    <div class="modal fade" id="pdSelectInvoiceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="show_projects.php" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Attach Existing Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="select_existing_invoice" value="1">
                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($specific_project['order_id']) ?>">
                    <input type="hidden" name="return_to" value="project_details">
                    <div class="mb-3">
                        <label for="pdInvoiceSelect" class="form-label">Select an invoice for client
                            <strong><?= $specific_full_name ?></strong>
                        </label>
                        <select class="form-select" id="pdInvoiceSelect" name="invoice_id" required>
                            <option value="">-- Select an Invoice --</option>
                            <?php foreach ($availableInvoices as $av):
$avDate  = !empty($av['invoice_date']) ? date('m/d/Y', strtotime($av['invoice_date'])) : 'N/A';
$avTotal = isset($av['total_amount']) ? number_format($av['total_amount'], 2) : '0.00';
                            ?>
                                <option value="<?= htmlspecialchars($av['invoice_id']) ?>">
                                    # <?= htmlspecialchars($av['invoice_id']) ?> &mdash; Date: <?= $avDate ?>, Amount: $<?= $avTotal ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($availableInvoices)): ?>
                            <small class="text-muted d-block mt-2">No unattached invoices found for this client.</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" <?= empty($availableInvoices) ? 'disabled' : '' ?>>
                        <i class="fas fa-link me-1"></i> Attach
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Payment Modal -->
    <div class="modal fade" id="pdAddPaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="show_projects.php" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Payment For Invoice #<span id="pdAddPaymentInvoiceId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="invoice_id"        id="pd_add_payment_invoice_id">
                    <input type="hidden" name="paidamount"        id="pd_add_payment_paidamount">
                    <input type="hidden" name="memberid"          id="pd_add_payment_memberid">
                    <input type="hidden" name="old_discount"      id="pd_add_payment_old_discount">
                    <input type="hidden" name="return_to"         value="project_details">
                    <input type="hidden" name="return_project_id" value="<?= htmlspecialchars($specific_project['order_id']) ?>">
                    <div class="row mb-2">
                        <div class="col-md-6 mt-2">
                            <label class="form-label">Date</label>
                            <input class="form-control" type="date" name="duedate" id="pd_add_payment_duedate">
                        </div>
                        <div class="col-md-6 mt-2">
                            <label class="form-label">Discount</label>
                            <input type="text" class="form-control" id="pd_add_payment_after_discount" name="after_discount">
                        </div>
                        <div class="col-md-6 mt-2">
                            <label class="form-label">Employee Initials</label>
                            <input class="form-control" id="pd_employee_initials" name="employee_initials" value="">
                        </div>
                        <div class="col-md-6 mt-2">
                            <label class="form-label">Total Amount</label>
                            <input class="form-control" required name="totalamount" type="text" id="pd_add_payment_totalamount">
                        </div>
                        <div class="col-md-6 mt-2">
                            <label class="form-label">Balance</label>
                            <input class="form-control" required name="balance" type="text" id="pd_add_payment_balance">
                        </div>
                        <div class="col-md-12 mt-2">
                            <label class="form-label">Payment Type</label>
                            <select class="form-control" id="pdPaymentTypeSelect" name="payment_type">
                                <option value="">Select Payment Type</option>
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                                <option value="Card">Card</option>
                                <option value="ACH">ACH</option>
                                <option value="Zelle">Zelle</option>
                                <option value="Other">Other</option>
                            </select>
                            <input type="text" class="form-control mt-2 d-none" id="pdOtherPaymentInput" placeholder="Please specify payment type">
                        </div>
                        <div class="col-md-12 mt-2">
                            <label class="form-label">Service Provider</label>
                            <input type="text" class="form-control" id="pd_add_payment_service_provider" readonly>
                        </div>
                        <div class="col-md-12 mt-2">
                            <label class="form-label">Amount Received</label>
                            <input class="form-control" required name="recievedamount" type="text">
                        </div>
                        <div class="col-md-12 mt-2">
                            <label class="form-label">Description</label>
                            <input class="form-control" required name="description" type="text" value="">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" name="addpayment">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment History Modal -->
    <div class="modal fade" id="pdPaymentHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment History for Invoice #<span id="pdPaymentHistoryInvoiceId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Balance</th>
                                <th>Description</th>
                                <th>Employee</th>
                            </tr>
                        </thead>
                        <tbody id="pdPaymentHistoryBody"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).on('click', '.pd-add-payment-btn', function () {
            const $b = $(this);
            const invoiceId   = $b.data('invoice-id');
            const totalAmount = $b.data('total-amount');
            const paidAmount  = $b.data('paid-amount');
            const balance     = $b.data('balance');
            const discount    = $b.data('discount');
            const clientId    = $b.data('client-id');
            const spName      = $b.data('service-provider-name') || '';

            $('#pdAddPaymentInvoiceId').text(invoiceId);
            $('#pd_add_payment_invoice_id').val(invoiceId);
            $('#pd_add_payment_totalamount').val(totalAmount);
            $('#pd_add_payment_balance').val(balance);
            $('#pd_add_payment_paidamount').val(paidAmount);
            $('#pd_add_payment_old_discount').val(discount);
            $('#pd_add_payment_memberid').val(clientId);
            $('#pd_add_payment_after_discount').val(discount);
            $('#pd_add_payment_service_provider').val(spName);

            const today = new Date().toISOString().split('T')[0];
            $('#pd_add_payment_duedate').val(today);

            const fullName = "<?= isset($_SESSION['fullname']) ? addslashes($_SESSION['fullname']) : '' ?>";
            const parts = fullName.split(' ');
            let initials = '';
            if (parts.length > 0 && parts[0]) initials += parts[0][0];
            if (parts.length > 1 && parts[1]) initials += parts[1][0];
            $('#pd_employee_initials').val(initials.toUpperCase());

            $('#pdPaymentTypeSelect').val('');
            $('#pdOtherPaymentInput').val('').addClass('d-none');
        });

        $('#pdPaymentTypeSelect').on('change', function () {
            const other = document.getElementById('pdOtherPaymentInput');
            if (this.value === 'Other') {
                other.classList.remove('d-none');
                other.value = '';
                other.focus();
            } else {
                other.classList.add('d-none');
                other.value = '';
            }
        });

        $(document).on('click', '.pd-payment-history-btn', function () {
            const invoiceId = $(this).data('invoice-id');
            $('#pdPaymentHistoryInvoiceId').text(invoiceId);
            $('#pdPaymentHistoryBody').html('<tr><td colspan="5" class="text-center">Loading...</td></tr>');
            $.getJSON('invoicehistory.php', { invoice_id: invoiceId }, function (data) {
                if (data && data.length > 0) {
                    let rows = '';
                    data.forEach(row => {
                        rows += `<tr>
                            <td>${row.date}</td>
                            <td>${parseFloat(row.paid).toFixed(2)}</td>
                            <td>${parseFloat(row.balance).toFixed(2)}</td>
                            <td>${row.detail}</td>
                            <td>${row.employee}</td>
                        </tr>`;
                    });
                    $('#pdPaymentHistoryBody').html(rows);
                } else {
                    $('#pdPaymentHistoryBody').html('<tr><td colspan="5" class="text-center">No payment history found</td></tr>');
                }
            }).fail(function () {
                $('#pdPaymentHistoryBody').html('<tr><td colspan="5" class="text-center text-danger">Failed to load history</td></tr>');
            });
        });
    </script>
    <?php endif; ?>

    <?php include "includes/footer.php"; ?>
</body>

</html>