<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

include "includes/db.php";
include "includes/auth.php";

$user_id = $_SESSION['user_id'];
// added 
function getInitials($name)
{
    return implode('', array_map(fn($w) => strtoupper(substr($w, 0, 1)), explode(' ', $name)));
}

$user_full_name = $_SESSION['fullname'];
$cstatus = '';

// Fetch users for dropdown
$users_result = mysqli_query($con, "SELECT user_id, user_name FROM users ORDER BY user_name");

// Handle CRUD
if (isset($_POST['addcomment'])) {
    $detail = mysqli_real_escape_string($con, $_POST['comment_detail']);
    $to = !empty($_POST['comment_to']) ? mysqli_real_escape_string($con, $_POST['comment_to']) : 'NULL';

    $q = "INSERT INTO general_comments (user_id, comment_to, comment_detail, comment_time) 
          VALUES ('$user_id', $to, '$detail', NOW())";
    $cstatus = mysqli_query($con, $q)
        ? "<div class='alert alert-success'>Comment added successfully.</div>"
        : "<div class='alert alert-danger'>Error: " . mysqli_error($con) . "</div>";
}

if (isset($_POST['editcomment'])) {
    $id = mysqli_real_escape_string($con, $_POST['comment_id']);
    $detail = mysqli_real_escape_string($con, $_POST['comment_detail']);
    $to = !empty($_POST['comment_to']) ? mysqli_real_escape_string($con, $_POST['comment_to']) : 'NULL';

    $q = "UPDATE general_comments SET 
            comment_detail = '$detail', 
            comment_to = $to,
            comment_updated_by = '$user_id',
            comment_updated_time = NOW()
          WHERE comment_id = '$id'";
    $cstatus = mysqli_query($con, $q)
        ? "<div class='alert alert-success'>Comment updated successfully.</div>"
        : "<div class='alert alert-danger'>Error: " . mysqli_error($con) . "</div>";
}

if (isset($_POST['deletecomment'])) {
    $id = mysqli_real_escape_string($con, $_POST['comment_id']);
    $q = "DELETE FROM general_comments WHERE comment_id = '$id'";
    $cstatus = mysqli_query($con, $q)
        ? "<div class='alert alert-success'>Comment deleted successfully.</div>"
        : "<div class='alert alert-danger'>Error: " . mysqli_error($con) . "</div>";
}

// Fetch all comments (no LIMIT — client-side pagination)
$query = "SELECT gc.*, 
                  u.user_name AS created_by_name,
                  e.user_name AS edited_by_name,
                  t.user_name AS to_user_name
           FROM general_comments gc
           LEFT JOIN users u ON gc.user_id = u.user_id
           LEFT JOIN users e ON gc.comment_updated_by = e.user_id
           LEFT JOIN users t ON gc.comment_to = t.user_id
           ORDER BY gc.comment_id DESC";
$result = mysqli_query($con, $query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Syed Professional Services - General Comments</title>
    <?php include "includes/links.php"; ?>

    <style>
        .comment-container {
            max-width: 100%;
            word-break: break-word;
            white-space: normal;
        }

        .comment-text {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
            max-height: 4.5em;
            transition: all .3s;
        }

        .comment-text.expanded {
            -webkit-line-clamp: unset;
            max-height: none;
        }

        .see-more-btn {
            font-size: 0.85rem;
            color: #007bff;
            background: none;
            border: none;
            padding: 0;
            margin-top: 4px;
            display: none;
        }

        .see-more-btn.visible {
            display: inline-block;
        }

        .see-more-btn:hover {
            text-decoration: underline;
            cursor: pointer;
        }

        .action-buttons .btn {
            margin: 0 3px;
        }
    </style>
</head>

<body class="theme-3">
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <h4 class="m-b-10">General Comments</h4>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">General Comments</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <?php if ($cstatus)
                            echo $cstatus; ?>

                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Comments Management</h5>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#addCommentModal">
                                Add New Comment
                            </button>
                        </div>

                        <div class="card-body">
                            <table class="table table-hover" id="commentsTable">
                                <thead>
                                    <tr>
                                        <th>Commented By</th>
                                        <th>Commented To</th>
                                        <th>Comment</th>
                                        <th>Date</th>
                                        <th>Edited By</th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['created_by_name']) ?></td>
                                            <td>
                                                <?= $row['comment_to']
                                                    ? htmlspecialchars($row['to_user_name'])
                                                    : '<span class="text-muted">General</span>'
                                                    ?>
                                            </td>
                                            <td>
                                                <div class="comment-container">
                                                    <div class="comment-text"
                                                        data-full="<?= htmlspecialchars($row['comment_detail'], ENT_QUOTES) ?>">
                                                        <?= htmlspecialchars($row['comment_detail']) ?>
                                                    </div>
                                                    <button type="button" class="see-more-btn">See more</button>
                                                </div>
                                            </td>
                                            <td><?= date("d M Y", strtotime($row['comment_time'])) ?></td>
                                            <td>
                                                <?php if ($row['comment_updated_by']): ?>
                                                    <?= htmlspecialchars($row['edited_by_name']) ?><br>
                                                    <small
                                                        class="text-muted"><?= date("d M Y", strtotime($row['comment_updated_time'])) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Not edited</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons text-center">
                                                <button class="btn btn-sm btn-info"
                                                    onclick="viewComment(<?= $row['comment_id'] ?>)">View</button>
                                                <button class="btn btn-sm btn-warning"
                                                    onclick="editComment(<?= $row['comment_id'] ?>, `<?= addslashes(htmlspecialchars($row['comment_detail'])) ?>`, '<?= $row['comment_to'] ?>')">Edit</button>
                                                <button class="btn btn-sm btn-danger"
                                                    onclick="deleteComment(<?= $row['comment_id'] ?>)">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <!-- Add Comment Modal -->
    <div class="modal fade" id="addCommentModal" tabindex="-1" role="dialog" aria-labelledby="addCommentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCommentModalLabel">Add New Comment</h5>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="comment_to">Comment To (Optional)</label>
                            <select class="form-control" id="comment_to" name="comment_to">
                                <option value="">General Comment</option>
                                <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['user_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="comment_detail">Comment</label>
                            <textarea class="form-control" id="comment_detail" name="comment_detail" rows="5"
                                required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="addcomment" class="btn btn-primary">Add Comment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Comment Modal -->
    <div class="modal fade" id="editCommentModal" tabindex="-1" role="dialog" aria-labelledby="editCommentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCommentModalLabel">Edit Comment</h5>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" id="edit_comment_id" name="comment_id">
                        <div class="form-group">
                            <label for="edit_comment_to">Comment To (Optional)</label>
                            <select class="form-control" id="edit_comment_to" name="comment_to">
                                <option value="">General Comment</option>
                                <?php
                                // Reset the result pointer to the beginning
                                mysqli_data_seek($users_result, 0);
                                while ($user = mysqli_fetch_assoc($users_result)):
                                    ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['user_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_comment_detail">Comment</label>
                            <textarea class="form-control" id="edit_comment_detail" name="comment_detail" rows="5"
                                required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="editcomment" class="btn btn-primary">Update Comment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Comment Modal -->
    <div class="modal fade" id="viewCommentModal" tabindex="-1" role="dialog" aria-labelledby="viewCommentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewCommentModalLabel">Comment Details</h5>
                </div>
                <div class="modal-body">
                    <div id="viewCommentContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteCommentModal" tabindex="-1" role="dialog"
        aria-labelledby="deleteCommentModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCommentModalLabel">Confirm Delete</h5>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this comment? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" id="delete_comment_id" name="comment_id">
                        <button type="submit" name="deletecomment" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple-DataTables using your local file
        document.addEventListener("DOMContentLoaded", function () {
            new simpleDatatables.DataTable("#commentsTable", {
                perPage: 25,
                perPageSelect: [10, 25, 50, 100],
                searchable: true,
                sortable: true,
                fixedHeight: false,
                labels: {
                    placeholder: "Search comments...",
                    noRows: "No comments found",
                    info: "Showing {start} - {end} of {rows}"
                }
            });
        });

        // See more / See less
        document.addEventListener("DOMContentLoaded", function () {
            function checkOverflow() {
                document.querySelectorAll('.comment-text').forEach(el => {
                    const btn = el.nextElementSibling;
                    const isOverflowing = el.scrollHeight > el.clientHeight;
                    btn.classList.toggle('visible', isOverflowing);
                });
            }

            checkOverflow();
            window.addEventListener('resize', checkOverflow);

            document.querySelectorAll('.see-more-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const text = this.previousElementSibling;
                    text.classList.toggle('expanded');
                    this.textContent = text.classList.contains('expanded') ? 'See less' : 'See more';
                });
            });
        });

        function viewComment(id) {
            const row = document.querySelector(`tr:has(button[onclick="viewComment(${id})"])`);
            const cells = row.cells;

            const html = `
        <div class="d-flex align-items-start gap-4 flex-wrap">
            <div><strong>By:</strong> ${cells[0].textContent}</div>
            <div><strong>Date:</strong> ${cells[3].textContent}</div>
            <div><strong>To:</strong> ${cells[1].innerHTML}</div>
            <div><strong>Comment:</strong> ${row.querySelector('.comment-text').dataset.full.replace(/\n/g, '<br>')}</div>
            ${!cells[4].textContent.includes('Not edited') ? `<div><strong>Edited:</strong> ${cells[4].innerHTML}</div>` : ''}
        </div>
    `;

            $('#viewCommentContent').html(html);
            $('#viewCommentModal').modal('show');
        }


        function editComment(id, detail, to) {
            $('#edit_comment_id').val(id);
            $('#edit_comment_detail').val(detail.replace(/`/g, "'"));
            $('#edit_comment_to').val(to || '');
            $('#editCommentModal').modal('show');
        }

        function deleteComment(id) {
            $('#delete_comment_id').val(id);
            $('#deleteCommentModal').modal('show');
        }
    </script>
</body>

</html>