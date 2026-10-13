<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// added
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

include "includes/db.php";
include "includes/auth.php";

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
    $check_query = "SELECT user_id FROM new_comments WHERE comment_id = ?";
    $stmt_check = $con->prepare($check_query);
    $stmt_check->bind_param('i', $comment_id);
    $stmt_check->execute();
    $res = $stmt_check->get_result();

    if ($row = $res->fetch_assoc()) {
        if ($row['user_id'] == $user_id || $is_admin) {
            $queryy = "UPDATE new_comments SET comment_detail = '$comment_detail', comment_updated_by = '$user_id' WHERE comment_id = '$comment_id'";
            $update_query = mysqli_query($con, $queryy);
            if ($update_query) {
                $cstatus .= "<div class='alert alert-success'>Invoice comment updated successfully.</div>";
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
    $check_query = "SELECT user_id FROM new_comments WHERE comment_id = ?";
    $stmt_check = $con->prepare($check_query);
    $stmt_check->bind_param('i', $comment_id);
    $stmt_check->execute();
    $res = $stmt_check->get_result();

    if ($row = $res->fetch_assoc()) {
        if ($row['user_id'] == $user_id || $is_admin) {
            $delete_query = "DELETE FROM new_comments WHERE comment_id = '$comment_id'";
            $delete_result = mysqli_query($con, $delete_query);
            if ($delete_result) {
                $cstatus .= "<div class='alert alert-success'>Invoice comment deleted successfully.</div>";
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
    $where_conditions[] = "nc.user_id = ?";
    $params[] = $filter_user_id;
    $types .= 'i';
}

if (!empty($search_term)) {
    $where_conditions[] = "(nc.comment_detail LIKE ? OR m.name LIKE ? OR m.mname LIKE ? OR m.lname LIKE ? OR m.bus_name LIKE ?)";
    $like_term = '%' . $search_term . '%';
    $params = array_merge($params, array_fill(0, 5, $like_term));
    $types .= 'sssss';
}

// Visibility logic: unread, updated by others, or own comments
$where_conditions[] = "
    (nc.comment_status = '0'
    OR (nc.comment_updated_by NOT LIKE ? 
        AND nc.comment_updated_by NOT LIKE ? 
        AND nc.comment_updated_by NOT LIKE ? 
        AND nc.comment_updated_by != ?)
    OR nc.user_id = ?)
";
$like_patterns = ["%,$user_id,%", "$user_id,%", "%,$user_id", $user_id];
foreach ($like_patterns as $pattern) {
    $params[] = $pattern;
}
$params[] = $user_id;
$types .= 'ssssi';

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Query for invoice comments
$mysqli = $con = mysqli_connect($host, $username, $password, $database, $port);

// Total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM new_comments nc
    JOIN members m ON nc.member_id = m.member_id
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
    SELECT nc.*
    FROM new_comments nc
    JOIN members m ON nc.member_id = m.member_id
    $where_clause
    ORDER BY nc.comment_id DESC
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
    <title>Syed Professional Services - Invoice Comments</title>
    <?php include "includes/links.php"; ?>
    <!-- Add SweetAlert CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
                                <h4 class="m-b-10">Invoice Comments</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Invoice Comments</li>
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
                            <h5 class="mb-0">Invoice Comments</h5>
                            <form method="GET" class="d-flex align-items-center gap-2">
                                <input type="text" name="search" class="form-control form-control-sm"
                                    placeholder="Search comments or client..."
                                    value="<?= htmlspecialchars($search_term) ?>" style="min-width:200px;">

                                <select name="user_id" class="form-control form-control-sm">
                                    <option value="">All Users</option>
                                    <?php
                                    $user_query = "SELECT DISTINCT u.user_id, u.user_name 
                                   FROM new_comments c 
                                   JOIN users u ON c.user_id = u.user_id 
                                   ORDER BY u.user_name";
                                    $ures = mysqli_query($con, $user_query);
                                    while ($urow = mysqli_fetch_assoc($ures)) {
                                        $selected = ($filter_user_id == $urow['user_id']) ? 'selected' : '';
                                        echo "<option value='{$urow['user_id']}' $selected>" . htmlspecialchars($urow['user_name']) . "</option>";
                                    }
                                    ?>
                                </select>

                                <button type="submit" class="btn btn-primary btn-sm">Filter</button>

                                <?php if ($filter_user_id || $search_term): ?>
                                    <a href="invoice_comments.php" class="btn btn-secondary btn-sm">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table datatable1">
                                    <thead>
                                        <tr>
                                            <th>By</th>
                                            <th>Client</th>
                                            <th>Invoice ID</th>
                                            <th>Comment</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead> 
                                    <tbody>
                                        <?php while ($rrow = $result->fetch_assoc()): ?>
                                            <?php
                                            /* ------------  EDIT MODAL  ------------ */
                                            // to comment
                                            $rrow['comment_detail'] = str_replace('&nbsp;', ' ', $rrow['comment_detail']);
                                            $modalId = 'editnewcomment' . $rrow['comment_id'];
                                            ?>
                                            <div class="modal fade" id="<?= $modalId ?>">
                                                <div class="modal-dialog modal-lg">
                                                    <form method="POST">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h4 class="modal-title">Edit Invoice Comment</h4>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="comment_id"
                                                                    value="<?= htmlspecialchars($rrow['comment_id']) ?>">
                                                                <div class="form-group">
                                                                    <label>Comment Text</label>
                                                                    <textarea class="form-control" name="comment_detail"
                                                                        rows="5"><?= htmlspecialchars($rrow['comment_detail']) ?></textarea>
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
                                            <div class="modal fade" id="deleteModal<?= $rrow['comment_id'] ?>">
                                                <div class="modal-dialog">
                                                    <form method="POST">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Delete Invoice Comment?</h5>
                                                                <button type="button" class="close"
                                                                    data-dismiss="modal">&times;</button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="comment_id"
                                                                    value="<?= $rrow['comment_id'] ?>">
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
                                                        date("D d M, Y", strtotime($rrow['comment_time'])) . '</small>';
                                                    ?>
                                                </td>

                                                <td>
                                                    <?php
                                                    $mid = $rrow['member_id'];
                                                    $mRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT name,mname,lname,bus_name,status FROM members WHERE member_id=$mid"));
                                                    echo '<a href="client_details.php?id=' . $mid . '&status=' . $mRow['status'] . '">' .
                                                        htmlspecialchars($mRow['name'] . ' ' . $mRow['mname'] . ' ' . $mRow['lname']) .
                                                        '</a><br><span>' . htmlspecialchars($mRow['bus_name']) . '</span>';
                                                    ?>
                                                </td>

                                                <td><?php echo ($rrow['invoice_id'] == 0) ? 'N/A' : $rrow['invoice_id']; ?></td>
                                                <td>
                                                    <div class="comment-container">
                                                        <div class="comment-text"
                                                            data-full-text="<?= htmlspecialchars(strip_tags($rrow['comment_detail']), ENT_QUOTES) ?>">
                                                            <?= nl2br(htmlspecialchars(strip_tags($rrow['comment_detail']))) ?>
                                                        </div>
                                                        <button class="btn btn-sm btn-link see-more-btn">See more</button>
                                                    </div>
                                                </td>

                                                <td class="text-center">
                                                    <!-- Edit button with permission check -->
                                                    <button class="btn btn-sm btn-warning tooltip1 edit-btn"
                                                        data-comment-id="<?= $rrow['comment_id'] ?>"
                                                        data-comment-owner="<?= $rrow['user_id'] ?>"
                                                        data-modal="<?= $modalId ?>">
                                                        <i class="ti ti-pencil text-white"></i>
                                                        <span class="tooltiptext1">Edit</span>
                                                    </button>

                                                    <!-- Delete button with permission check -->
                                                    <button class="btn btn-sm btn-danger tooltip1 delete-btn"
                                                        data-comment-id="<?= $rrow['comment_id'] ?>"
                                                        data-comment-owner="<?= $rrow['user_id'] ?>"
                                                        data-delete-modal="deleteModal<?= $rrow['comment_id'] ?>">
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

                                        echo '<li class="prev"><a href="invoice_comments.php?inewpage=' . ($inewpage - 1) . $param_str . '">Prev</a></li>';
                                    }

                                    /* -----  LEFT WINDOW  ----- */
                                    if ($inewpage > 3) {
                                        echo '<li class="start"><a href="invoice_comments.php?inewpage=1">1</a></li>';
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

                                        echo '<li class="' . $cls . '"><a href="invoice_comments.php?inewpage=' . $i . $param_str . '">' . $i . '</a></li>';
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

                                        echo '<li class="end"><a href="invoice_comments.php?inewpage=' . $itotalPages . $param_str . '">' . $itotalPages . '</a></li>';
                                    }

                                    /* -----  NEXT  ----- */
                                    if ($inewpage < $itotalPages) {
                                        $base_params = [];
                                        if ($filter_user_id)
                                            $base_params[] = "user_id=$filter_user_id";
                                        if ($search_term)
                                            $base_params[] = "search=" . urlencode($search_term);
                                        $param_str = !empty($base_params) ? '&' . implode('&', $base_params) : '';

                                        echo '<li class="next"><a href="invoice_comments.php?inewpage=' . ($inewpage + 1) . $param_str . '">Next</a></li>';
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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