<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// added
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

include "includes/db.php";
include "includes/auth.php";
include "includes/activity_log.php";

function pc_project_member_id($con, $project_id)
{
    $res = mysqli_query($con, "SELECT mem_id FROM projects WHERE order_id='" . mysqli_real_escape_string($con, $project_id) . "'");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row['mem_id'] ?? null;
}

error_reporting(0);

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['fullname'];

// Fetch user_type_name to check for Administrator
$type_query = "SELECT user_type_name FROM user_type WHERE user_type_id = ?";
$stmt_type = $con->prepare($type_query);
$stmt_type->bind_param('i', $_SESSION['user_type']);
$stmt_type->execute();
$type_result = $stmt_type->get_result();
$user_type_name = $type_result->fetch_assoc()['user_type_name'] ?? 'Restricted';
$is_admin = ($user_type_name === 'Administrator');

// Function to get initials 
function getInitials($name)
{
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}

$user_initials = getInitials($user_full_name);
$user_type = $_SESSION['user_type'];
$cstatus = '';

if (isset($_POST['editnewcomment'])) {
    $comment_id = mysqli_real_escape_string($con, $_POST['comment_id']);
    $comment_detail = mysqli_real_escape_string($con, $_POST['comment_detail']);

    // Check if user has permission to edit this comment
    $check_query = "SELECT user_id, project_id FROM project_comments WHERE id = ?";
    $stmt_check = $con->prepare($check_query);
    $stmt_check->bind_param('i', $comment_id);
    $stmt_check->execute();
    $res = $stmt_check->get_result();

    if ($row = $res->fetch_assoc()) {
        if ($row['user_id'] == $user_id || $is_admin) {
            $queryy = "UPDATE project_comments SET comment = '$comment_detail' WHERE id = '$comment_id'";
            $update_query = mysqli_query($con, $queryy);
            if ($update_query) {
                $pc_mem_id = pc_project_member_id($con, $row['project_id']);
                logActivity($con, $user_id, 'UPDATE', 'project_comments', $comment_id, "Updated project comment ID: {$comment_id} for Project ID: {$row['project_id']}" . ($pc_mem_id ? ", Member ID: {$pc_mem_id}." : "."));
                $cstatus .= "<div class='alert alert-success'>Project comment updated successfully.</div>";
            } else {
                $cstatus .= "<div class='alert alert-danger'>Update failed: " . $con->error . "</div>";
            }
        } else {
            $cstatus .= "<div class='alert alert-danger'>Access denied. Only your own comments are editable.</div>";
        }
    }
}

// Handle Delete Comment
if (isset($_POST['deletenewcomment'])) {
    $comment_id = mysqli_real_escape_string($con, $_POST['comment_id']);

    // Check if user has permission to delete this comment
    $check_query = "SELECT user_id, project_id FROM project_comments WHERE id = ?";
    $stmt_check = $con->prepare($check_query);
    $stmt_check->bind_param('i', $comment_id);
    $stmt_check->execute();
    $res = $stmt_check->get_result();

    if ($row = $res->fetch_assoc()) {
        if ($row['user_id'] == $user_id || $is_admin) {
            $delete_query = "DELETE FROM project_comments WHERE id = '$comment_id'";
            $delete_result = mysqli_query($con, $delete_query);
            if ($delete_result) {
                $pc_mem_id = pc_project_member_id($con, $row['project_id']);
                logActivity($con, $user_id, 'DELETE', 'project_comments', $comment_id, "Deleted project comment ID: {$comment_id} for Project ID: {$row['project_id']}" . ($pc_mem_id ? ", Member ID: {$pc_mem_id}." : "."));
                $cstatus .= "<div class='alert alert-success'>Project comment deleted successfully.</div>";
            } else {
                $cstatus .= "<div class='alert alert-danger'>Delete failed: " . $con->error . "</div>";
            }
        } else {
            $cstatus .= "<div class='alert alert-danger'>You are not authorized to delete this comment.</div>";
        }
    }
}

// Filters
$filter_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE conditions
$where_conditions = [];
$params = [];
$types = '';

if ($filter_user_id > 0) {
    $where_conditions[] = "pc.user_id = ?";
    $params[] = $filter_user_id;
    $types .= 'i';
}

if (!empty($search_term)) {
    $where_conditions[] = "(pc.comment LIKE ? OR p.project_title LIKE ? OR pr.membership_name LIKE ? OR CONCAT(m.name, ' ', m.mname, ' ', m.lname) LIKE ? OR m.bus_name LIKE ?)";
    $like_term = '%' . $search_term . '%';
    $params = array_merge($params, array_fill(0, 5, $like_term));
    $types .= 'sssss';
}

// Visibility logic: admin sees all, regular users see only their own comments
if (!$is_admin) {
    $where_conditions[] = "pc.user_id = ?";
    $params[] = $user_id;
    $types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Query for project comments
$mysqli = $con = mysqli_connect($host, $username, $password, $database, $port);

// Total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM project_comments pc
    JOIN projects p ON pc.project_id = p.order_id
    LEFT JOIN memberships pr ON p.prod_id = pr.membership_id
    LEFT JOIN members m ON p.mem_id = m.member_id
    $where_clause
";
$stmt_count = $mysqli->prepare($count_query);
if (!empty($params)) {
    // Reset params for count query (no LIMIT params yet)
    $count_params = $params;
    $count_types = $types;
    $stmt_count->bind_param($count_types, ...$count_params);
}
$stmt_count->execute();
$total_pages = $stmt_count->get_result()->fetch_assoc()['total'];

// Pagination
$inewpage = isset($_GET['inewpage']) && is_numeric($_GET['inewpage']) ? (int) $_GET['inewpage'] : 1;
$num_results_on_page = ($inewpage == 2) ? 20 : 25;
$calc_page = ($inewpage - 1) * $num_results_on_page;

// Main data query
$que = "
    SELECT pc.*, p.project_title, 
           pr.membership_name AS product_name,
           CONCAT(m.name, ' ', m.mname, ' ', m.lname) as client_name,
           m.bus_name, m.member_id as client_id
    FROM project_comments pc
    JOIN projects p ON pc.project_id = p.order_id
    LEFT JOIN memberships pr ON p.prod_id = pr.membership_id
    LEFT JOIN members m ON p.mem_id = m.member_id
    $where_clause
    ORDER BY pc.id DESC
    LIMIT ?, ?
";
$types .= 'ii';
$params[] = $calc_page;
$params[] = $num_results_on_page;

$stmt = $mysqli->prepare($que);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
    <title>Syed Professional Services - Project Comments</title>
    <?php include "includes/links.php"; ?>
    <!-- Add SweetAlert CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css ">
    <link rel="stylesheet" href="assets/datatable/jquery.dataTables.min.css">
    <link rel="stylesheet" href="assets/datatable/responsive.dataTables.min.css">
    <script src="assets/datatable/jquery-3.7.0.js"></script>
    <script src="assets/datatable/jquery.dataTables.min.js"></script>
    <script src="assets/datatable/dataTables.responsive.min.js"></script>
    <style>
        /* 1. force long words to break ******************/
        .comment-container {
            max-width: 100%;
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }

        /* 2. keep the column widths strict ************/
        .datatable1 {
            /* table-layout: fixed; */
            /* width: 100%; */
        }

        .comment-text {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            /* 3 lines instead of 2 */
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            max-height: 4.2em;
            /* 3 lines × 1.4 line-height */
            transition: max-height 0.3s ease;
        }

        .comment-text.expanded {
            -webkit-line-clamp: unset;
            max-height: none;
        }

        .see-more-btn {
            padding: 0;
            font-size: 0.8rem;
            color: #007bff;
            background: none;
            border: none;
            margin-top: 5px;
            display: none;
        }

        .see-more-btn:hover {
            text-decoration: underline;
        }

        .see-more-btn.visible {
            display: inline-block;
        }
    </style>
</head>

<body class="theme-3">
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>
    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title">
                                <h4 class="m-b-10">Project Comments</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Project Comments</li>
                            </ul>
                        </div>
                        <div class="col"></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <?php if (isset($cstatus))
                            echo $cstatus; ?>
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Project Comments</h5>
                            <form method="GET" class="d-flex align-items-center gap-2">
                                <input type="text" name="search" class="form-control form-control-sm"
                                    placeholder="Search comments, project or client..."
                                    value="<?= htmlspecialchars($search_term) ?>" style="min-width:200px;">

                                <?php if ($is_admin): ?>
                                <select name="user_id" class="form-control form-control-sm">
                                    <option value="">All Users</option>
                                    <?php
                                    $user_query = "SELECT DISTINCT u.user_id, u.user_name
                                   FROM project_comments c
                                   JOIN users u ON c.user_id = u.user_id
                                   ORDER BY u.user_name";
                                    $ures = mysqli_query($con, $user_query);
                                    while ($urow = mysqli_fetch_assoc($ures)) {
                                        $selected = ($filter_user_id == $urow['user_id']) ? 'selected' : '';
                                        echo "<option value='{$urow['user_id']}' $selected>" . htmlspecialchars($urow['user_name']) . "</option>";
                                    }
                                    ?>
                                </select>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-primary btn-sm">Filter</button>

                                <?php if ($filter_user_id || $search_term): ?>
                                    <a href="project_comments.php" class="btn btn-secondary btn-sm">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table datatable1">
                                    <thead>
                                        <tr>
                                            <th>By</th>
                                            <th>Project ID</th>
                                            <th>Project</th>
                                            <th>Product</th>
                                            <th>Comment</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($rrow = $result->fetch_assoc()): ?>
                                            <?php
                                            /* ------------  EDIT MODAL  ------------ */
                                            // to comment
                                            $rrow['comment'] = str_replace('&nbsp;', ' ', $rrow['comment']);
                                            $modalId = 'editnewcomment' . $rrow['id'];
                                            ?>
                                            <div class="modal fade" id="<?= $modalId ?>">
                                                <div class="modal-dialog modal-lg">
                                                    <form method="POST">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h4 class="modal-title">Edit Project Comment</h4>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="comment_id"
                                                                    value="<?= htmlspecialchars($rrow['id']) ?>">
                                                                <div class="form-group">
                                                                    <label>Comment Text</label>
                                                                    <textarea class="form-control" name="comment_detail"
                                                                        rows="5"><?= htmlspecialchars($rrow['comment']) ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-info"
                                                                    data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-primary"
                                                                    name="editnewcomment">Save Changes</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>

                                            <!-- Delete Modal -->
                                            <div class="modal fade" id="deleteModal<?= $rrow['id'] ?>">
                                                <div class="modal-dialog">
                                                    <form method="POST">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Delete Project Comment?</h5>
                                                                <button type="button" class="close"
                                                                    data-dismiss="modal">&times;</button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="comment_id"
                                                                    value="<?= $rrow['id'] ?>">
                                                                <p>Are you sure you want to delete this comment? This action
                                                                    cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="deletenewcomment"
                                                                    class="btn btn-danger">Delete</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
 
                                            <tr>
                                                <td>
                                                    <?php
                                                    $uid = $rrow['user_id'];
                                                    $uRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id=$uid"));
                                                    echo $uRow['user_name'] . '<br><small>' .
                                                        date("D d M, Y", strtotime($rrow['created_at'])) . '</small>';
                                                    ?>
                                                </td>
 
                                                <td>
                                                    <?php
                                                    echo '<a href="project_details.php?id=' . $rrow['project_id'] . '">' .
                                                        htmlspecialchars($rrow['project_id']);
                                                    ?>
                                                </td>

                                                <td> 
                                                    <?php
                                                    echo '<a href="project_details.php?id=' . $rrow['project_id'] . '">' .
                                                        htmlspecialchars($rrow['project_title']) .
                                                        '</a><br><span>' . htmlspecialchars($rrow['client_name']) . '</span>';
                                                    ?>
                                                </td>

                                                <td>
                                                    <?= htmlspecialchars($rrow['product_name'] ?? 'N/A') ?>
                                                </td>

                                                <td>
                                                    <div class="comment-container">
                                                        <div class="comment-text"
                                                            data-full-text="<?= htmlspecialchars(strip_tags($rrow['comment']), ENT_QUOTES) ?>">
                                                            <?= nl2br(htmlspecialchars(strip_tags($rrow['comment']))) ?>
                                                        </div>
                                                        <button class="btn btn-sm btn-link see-more-btn">See more</button>
                                                    </div>
                                                </td>

                                                <td class="text-center">
                                                    <!-- Edit button with permission check -->
                                                    <button class="btn btn-sm btn-warning tooltip1 edit-btn"
                                                        data-comment-id="<?= $rrow['id'] ?>"
                                                        data-comment-owner="<?= $rrow['user_id'] ?>"
                                                        data-modal="<?= $modalId ?>">
                                                        <i class="ti ti-pencil text-white"></i>
                                                        <span class="tooltiptext1">Edit</span>
                                                    </button>

                                                    <!-- Delete button with permission check -->
                                                    <button class="btn btn-sm btn-danger tooltip1 delete-btn"
                                                        data-comment-id="<?= $rrow['id'] ?>"
                                                        data-comment-owner="<?= $rrow['user_id'] ?>"
                                                        data-delete-modal="deleteModal<?= $rrow['id'] ?>">
                                                        <i class="ti ti-trash text-white"></i>
                                                        <span class="tooltiptext1">Delete</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- ---------------  PAGINATION  --------------- -->
                        <?php
                        $itotalPages = ceil($total_pages / $num_results_on_page);
                        if ($itotalPages > 1):
                            ?>
                            <div class="dataTable-bottom border">
                                <ul class="news">
                                    <?php
                                    /* -----  PREV  ----- */
                                    if ($inewpage > 1) {
                                        $base_params = [];
                                        if ($filter_user_id)
                                            $base_params[] = "user_id=$filter_user_id";
                                        if ($search_term)
                                            $base_params[] = "search=" . urlencode($search_term);
                                        $param_str = !empty($base_params) ? '&' . implode('&', $base_params) : '';

                                        echo '<li class="prev"><a href="project_comments.php?inewpage=' . ($inewpage - 1) . $param_str . '">Prev</a></li>';
                                    }

                                    /* -----  LEFT WINDOW  ----- */
                                    if ($inewpage > 3) {
                                        echo '<li class="start"><a href="project_comments.php?inewpage=1">1</a></li>';
                                        echo '<li class="dots">...</li>';
                                    }

                                    /* -----  MIDDLE  ----- */
                                    for ($i = max(1, $inewpage - 2); $i <= min($itotalPages, $inewpage + 2); $i++) {
                                        $cls = ($i == $inewpage) ? 'currentpage' : 'page';
                                        $base_params = [];
                                        if ($filter_user_id)
                                            $base_params[] = "user_id=$filter_user_id";
                                        if ($search_term)
                                            $base_params[] = "search=" . urlencode($search_term);
                                        $param_str = !empty($base_params) ? '&' . implode('&', $base_params) : '';

                                        echo '<li class="' . $cls . '"><a href="project_comments.php?inewpage=' . $i . $param_str . '">' . $i . '</a></li>';
                                    }

                                    /* -----  RIGHT WINDOW  ----- */
                                    if ($inewpage < $itotalPages - 2) {
                                        echo '<li class="dots">...</li>';
                                        $base_params = [];
                                        if ($filter_user_id)
                                            $base_params[] = "user_id=$filter_user_id";
                                        if ($search_term)
                                            $base_params[] = "search=" . urlencode($search_term);
                                        $param_str = !empty($base_params) ? '&' . implode('&', $base_params) : '';

                                        echo '<li class="end"><a href="project_comments.php?inewpage=' . $itotalPages . $param_str . '">' . $itotalPages . '</a></li>';
                                    }

                                    /* -----  NEXT  ----- */
                                    if ($inewpage < $itotalPages) {
                                        $base_params = [];
                                        if ($filter_user_id)
                                            $base_params[] = "user_id=$filter_user_id";
                                        if ($search_term)
                                            $base_params[] = "search=" . urlencode($search_term);
                                        $param_str = !empty($base_params) ? '&' . implode('&', $base_params) : '';

                                        echo '<li class="next"><a href="project_comments.php?inewpage=' . ($inewpage + 1) . $param_str . '">Next</a></li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <!-- Add SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11 "></script>

    <script>
        // Handle "See more" button clicks for comments
        document.addEventListener('DOMContentLoaded', function () {
            // Current user ID and admin status from PHP
            const currentUserId = <?= $user_id ?>;
            const isAdmin = <?= $is_admin ? 'true' : 'false' ?>;

            // Check permission and show appropriate modal or alert
            function checkPermission(commentOwnerId, modalId, isDelete = false) {
                if (commentOwnerId == currentUserId || isAdmin) {
                    // User has permission, show the modal
                    $('#' + modalId).modal('show');
                } else {
                    // User doesn't have permission, show SweetAlert
                    Swal.fire({
                        icon: 'error',
                        title: 'Access Denied',
                        text: 'Only your own comments are editable.',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    });
                }
            }

            // Edit button click handler
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function () {
                    const commentOwnerId = parseInt(this.dataset.commentOwner);
                    const modalId = this.dataset.modal;
                    checkPermission(commentOwnerId, modalId);
                });
            });

            // Delete button click handler
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function () {
                    const commentOwnerId = parseInt(this.dataset.commentOwner);
                    const modalId = this.dataset.deleteModal;
                    checkPermission(commentOwnerId, modalId, true);
                });
            });

            // Function to check if text is overflowing (wrapping to new lines)
            function checkTextOverflow() {
                const commentTexts = document.querySelectorAll('.comment-text');

                commentTexts.forEach(function (commentText) {
                    const seeMoreBtn = commentText.nextElementSibling;

                    // Clone the element to measure its full height
                    const clone = commentText.cloneNode(true);
                    clone.style.position = 'absolute';
                    clone.style.visibility = 'hidden';
                    clone.style.height = 'auto';
                    clone.style.maxHeight = 'none';
                    clone.style.webkitLineClamp = 'unset';
                    commentText.parentNode.appendChild(clone);

                    // Compare heights
                    const isOverflowing = clone.offsetHeight > commentText.offsetHeight;

                    // Remove the clone
                    commentText.parentNode.removeChild(clone);

                    // Show/hide the button based on overflow
                    if (isOverflowing) {
                        seeMoreBtn.classList.add('visible');
                    } else {
                        seeMoreBtn.classList.remove('visible');
                    }
                });
            }

            // Initial check with a small delay to ensure rendering is complete
            setTimeout(checkTextOverflow, 100);

            // Re-check on window resize
            window.addEventListener('resize', function () {
                setTimeout(checkTextOverflow, 100);
            });

            // Handle click events for see more/less buttons
            const seeMoreButtons = document.querySelectorAll('.see-more-btn');

            seeMoreButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const commentText = this.previousElementSibling;

                    if (this.textContent === 'See more') {
                        commentText.classList.add('expanded');
                        this.textContent = 'See less';
                    } else {
                        commentText.classList.remove('expanded');
                        this.textContent = 'See more';
                    }
                });
            });
        });

        const tables = document.querySelectorAll('.datatable1');
        tables.forEach(table => {
            new simpleDatatables.DataTable(table, {
                perPage: 25,
                searchable: true,
                // fixedHeight: true
            });
        });
    </script>
</body>

</html>
