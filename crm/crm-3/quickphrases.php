<?php

include 'includes/db.php';
include 'includes/auth.php';
  
$uid = (int) $_SESSION['user_id'];
$msg = '';
$msgType = ''; // 'success', 'warning', 'error'
 
/* ---------- ADD ---------- */
if (isset($_POST['add']) && !empty($_POST['phrase'])) {
    $p = mysqli_real_escape_string($con, trim($_POST['phrase']));
    $c = (int) mysqli_fetch_assoc(mysqli_query(
        $con,
        "SELECT COUNT(*) AS c FROM user_quick_phrases WHERE user_id=$uid"
    ))['c'];
    if ($c < 10) {
        $res = mysqli_query(
            $con,
            "INSERT IGNORE INTO user_quick_phrases (user_id, phrase) VALUES ($uid, '$p')"
        );
        $msg = $res ? "Phrase added successfully." : "Failed to add phrase.";
        $msgType = $res ? "success" : "error";
    } else {
        $msg = "Limit reached (10). Delete one first.";
        $msgType = "warning";
    }
}

/* ---------- EDIT ---------- */
if (isset($_POST['edit']) && !empty($_POST['phrase']) && isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    $p = mysqli_real_escape_string($con, trim($_POST['phrase']));
    $res = mysqli_query(
        $con,
        "UPDATE user_quick_phrases SET phrase='$p' WHERE id=$id AND user_id=$uid"
    );
    $msg = $res ? "Phrase updated successfully." : "Failed to update phrase.";
    $msgType = $res ? "success" : "error";
}

/* ---------- DELETE ---------- */
if (isset($_POST['del'])) {
    $id = (int) $_POST['del'];
    $res = mysqli_query(
        $con,
        "DELETE FROM user_quick_phrases WHERE id=$id AND user_id=$uid"
    );
    $msg = $res ? "Phrase deleted successfully." : "Failed to delete phrase.";
    $msgType = $res ? "success" : "error";
}

/* ---------- LIST ---------- */
$rows = mysqli_query(
    $con,
    "SELECT id, phrase FROM user_quick_phrases WHERE user_id=$uid ORDER BY id"
);
?>
<!doctype html>
<html>

<head>
    <title>My Quick Phrases</title>
    <?php include "includes/links.php"; ?>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .phrase-row td {
            vertical-align: middle;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="theme-3">
    <?php include "includes/header.php"; ?>
    <?php include "includes/sidebar.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <h4>My Quick Phrases (<?= mysqli_num_rows($rows) ?>/10)</h4>
            </div>

            <!-- ADD FORM -->
            <div class="card mb-4 shadow-sm border-light">
                <div class="card-body">
                    <form id="addForm" method="post" class="row g-2">
                        <div class="col-md-10">
                            <input type="text" name="phrase" class="form-control" placeholder="Type new phrase…"
                                required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100" name="add">Add</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- LIST / EDIT / DELETE -->
            <div class="card shadow-sm border-light">
                <div class="card-body">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:80%">Phrase</th>
                                <th style="width:20%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($r = mysqli_fetch_assoc($rows)): ?>
                                <tr class="phrase-row">
                                    <form class="editForm" method="post">
                                        <td>
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <input type="text" name="phrase" class="form-control form-control-sm"
                                                value="<?= htmlspecialchars($r['phrase']) ?>" required>
                                        </td>
                                        <td class="text-nowrap">
                                            <button class="btn btn-sm btn-warning me-1" name="edit"
                                                type="submit">Save</button>
                                            <button class="btn btn-sm btn-danger delBtn" type="button"
                                                data-id="<?= $r['id'] ?>">Del</button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <?php include "includes/footer.php"; ?>

    <script>
        $(document).ready(function () {
            // Show SweetAlert message if $msg is set
            <?php if ($msg): ?>
                Swal.fire({
                    icon: "<?= $msgType ?>",
                    title: "<?= addslashes($msg) ?>",
                    showConfirmButton: true,
                    timer: 3000
                });
            <?php endif; ?>

            // Delete confirmation using SweetAlert
            $('.delBtn').click(function () {
                let id = $(this).data('id');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This phrase will be deleted permanently!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Create and submit a hidden form to delete the phrase
                        let form = $('<form method="post"><input type="hidden" name="del" value="' + id + '"></form>');
                        $('body').append(form);
                        form.submit();
                    }
                });
            });
        });
    </script>
</body>

</html>