<?php
include 'includes/db.php';
include "includes/auth.php";
include 'includes/activity_log.php';
error_reporting(0);
 $secure = $_SESSION['secure_auth'];

 $client_id = $_GET['client_id'];
 $userid = $_SESSION["user_id"];
 $secure_client_name = $_SESSION['secure_client_name'];
function getInitials($name)
{
   $words = explode(' ', $name);
   $initials = '';
   foreach ($words as $word) {
      $initials .= strtoupper(substr($word, 0, 1));
   }
   return $initials;
}


$user_full_name = $_SESSION['fullname'];
$user_initials = getInitials($user_full_name);
$user_type = $_SESSION['user_type'];
// Set this based on your requirements
 $acc_bank_card_area = "Yes";

// Info / change-history buttons on each secured section (same helper client_details.php uses)
include "module_history.php";

// Handle secured comments
if (isset($_POST['save'])) {
    $commentDetail = $_POST['commentsecureInput'];
    $scomment_time = date("Y-m-d h:i:s");

    if (isset($_POST['commentId']) && $_POST['commentId'] != "") {
        $commentId = $_POST['commentId'];
        // Use prepared statements to prevent SQL injection
        $stmt = $con->prepare("UPDATE securedcomments SET scomment_detail = ?, user_id = ?, scomment_time = ? WHERE scomment_id = ?");
        $stmt->bind_param("sisi", $commentDetail, $userid, $scomment_time, $commentId);
        $stmt->execute();
        logActivity($con, $userid, 'UPDATE', 'secured_comment', (int)$commentId, "Updated secured comment ID $commentId for client ID: $client_id in secured area");
    } else {
        // Use prepared statements
        $stmt = $con->prepare("INSERT INTO securedcomments (scomment_detail, user_id, scomment_time, member_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sisi", $commentDetail, $userid, $scomment_time, $client_id);
        $stmt->execute();
        $new_comment_id = $stmt->insert_id;
        logActivity($con, $userid, 'CREATE', 'secured_comment', $new_comment_id, "Added secured comment for client ID: $client_id in secured area");
    }
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?client_id=$client_id");
    exit();
}

if (isset($_POST['delete_comment']) && $_POST['comment_id'] != "") {
    $commentId = $_POST['comment_id'];
    // Use prepared statements
    $stmt = $con->prepare("DELETE FROM securedcomments WHERE scomment_id = ?");
    $stmt->bind_param("i", $commentId);
    $stmt->execute();
    logActivity($con, $userid, 'DELETE', 'secured_comment', (int)$commentId, "Deleted secured comment ID $commentId for client ID: $client_id in secured area");
    header("Location: " . $_SERVER['PHP_SELF'] . "?client_id=$client_id");
    exit();
}

// Handle card operations
if (isset($_POST['addcard'])) {
    $card_type = $_POST['card_type'];
    $card_name = $_POST['card_name'];
    $card_no = $_POST['card_no'];
    $card_exp_mo = $_POST['card_exp_mo'];
    $card_exp_yo = $_POST['card_exp_yo'];
    $card_cvv = $_POST['card_cvv'];
    $zip = $_POST['zip'];
    $address = $_POST['address'];
    $userid = $_SESSION['user_id'];

    if ($card_type == 'others') {
        $card_type = $_POST['bankcardtype'];
        $queryy0 = "SELECT * FROM billing_card_type where card_type_name = ?";
        $stmt = $con->prepare($queryy0);
        $stmt->bind_param("s", $card_type);
        $stmt->execute();
        $res0 = $stmt->get_result();
        $rowscount0 = $res0->num_rows;

        if ($rowscount0 == 0) {
            $queryy1 = "SELECT MAX(card_type_id) as maxid FROM billing_card_type";
            $res1 = mysqli_query($con, $queryy1);
            $rows1 = mysqli_fetch_array($res1);
            $maxid = $rows1['maxid'];
            $maxid = $maxid + 1;

            $queryy2 = "INSERT INTO billing_card_type (card_type_id, card_type_name) VALUES (?, ?)";
            $stmt = $con->prepare($queryy2);
            $stmt->bind_param("is", $maxid, $card_type);
            $stmt->execute();

            $card_type = $maxid;
        }
    }

    $date_added = date('Y-m-d');
    $queryy = "INSERT INTO `billing_card`(`mem_id`, `card_type`, `card_onname`, `card_number`, `card_exp_month`, `card_exp_year`, `card_cvv`, `card_zip`, `card_address`, `user_id`, `date_added`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $con->prepare($queryy);
    $stmt->bind_param("isssiisssis", $client_id, $card_type, $card_name, $card_no, $card_exp_mo, $card_exp_yo, $card_cvv, $zip, $address, $userid, $date_added);
    $stmt->execute();
    $new_card_id = $stmt->insert_id;
    logActivity($con, $userid, 'CREATE', 'billing_card', $new_card_id, "Added new card (name: $card_name) for client ID: $client_id in secured area");
    header("Location: " . $_SERVER['PHP_SELF'] . "?client_id=$client_id");
    exit();
}

if (isset($_POST['delete_card'])) {
    $card_id = $_POST['card_id'];
    $stmt = $con->prepare("DELETE FROM billing_card where card_id=?");
    $stmt->bind_param("i", $card_id);
    $stmt->execute();
    logActivity($con, $userid, 'DELETE', 'billing_card', (int)$card_id, "Deleted card ID $card_id for client ID: $client_id in secured area");
    header("Location: " . $_SERVER['PHP_SELF'] . "?client_id=$client_id");
    exit();
}

if (isset($_POST['updatecard'])) {
    $card_id = $_POST['card_id'];
    $card_type = $_POST['card_type'];
    $card_name = $_POST['card_name'];
    $card_no = $_POST['card_no'];
    $card_exp_mo = $_POST['card_exp_mo'];
    $card_exp_yo = $_POST['card_exp_yo'];
    $card_cvv = $_POST['card_cvv'];
    $zip = $_POST['zip'];
    $address = $_POST['address'];
    $userid = $_SESSION['user_id'];

    $insert_history_query = "INSERT INTO card_edit_history (card_id, edited_by, card_name, card_no, card_exp_month, card_exp_year, card_cvv, card_zip, card_address)
                           SELECT card_id, ?, card_onname, card_number, card_exp_month, card_exp_year, card_cvv, card_zip, card_address
                           FROM billing_card
                           WHERE card_id = ?";
    $stmt = $con->prepare($insert_history_query);
    $stmt->bind_param("ii", $userid, $card_id);
    $stmt->execute();

    $query = "UPDATE billing_card SET card_type=?, card_onname=?, card_number=?, card_exp_month=?, card_exp_year=?, card_cvv=?, card_zip=?, card_address=? WHERE card_id=?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("isssisssi", $card_type, $card_name, $card_no, $card_exp_mo, $card_exp_yo, $card_cvv, $zip, $address, $card_id);
    $stmt->execute();
    logActivity($con, $userid, 'UPDATE', 'billing_card', (int)$card_id, "Updated card ID $card_id (name: $card_name) for client ID: $client_id in secured area");
    header("Location: " . $_SERVER['PHP_SELF'] . "?client_id=$client_id");
    exit();
}

// Handle bank operations
if (isset($_POST['addbank'])) {
    $bank_name = $_POST['bank_name'];
    $account_number = $_POST['account_number'];
    $routing_no = $_POST['routing_no'];
    $bank_user_name = $_POST['bank_user_name'];
    $bank_password = $_POST['bank_password'];
    $addedby = $_SESSION["user_id"];
    $date_added = date('Y-m-d');

    $queryy = "INSERT INTO `bank_accounts`(`bank_name`, `account_no`, `routing_no`, `client_id`, `added_by`,`bank_user_name`,`bank_password`,`date_added`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $con->prepare($queryy);
    $stmt->bind_param("sssiisss", $bank_name, $account_number, $routing_no, $client_id, $addedby, $bank_user_name, $bank_password, $date_added);
    $stmt->execute();
    $new_bank_id = $stmt->insert_id;
    logActivity($con, $addedby, 'CREATE', 'bank_account', $new_bank_id, "Added bank account \"$bank_name\" for client ID: $client_id in secured area");
    header("Location: " . $_SERVER['PHP_SELF'] . "?client_id=$client_id");
    exit();
}

if (isset($_POST['delete_bank'])) {
    $bank_id = $_POST['card_id']; // Note: input name is card_id, but it's for bank_id
    $stmt = $con->prepare("DELETE FROM bank_accounts where bank_id=?");
    $stmt->bind_param("i", $bank_id);
    $stmt->execute();
    logActivity($con, $userid, 'DELETE', 'bank_account', (int)$bank_id, "Deleted bank account ID $bank_id for client ID: $client_id in secured area");
    header("Location: " . $_SERVER['PHP_SELF'] . "?client_id=$client_id");
    exit();
}

if (isset($_POST['updatebank'])) {
    $bank_id = $_POST['bank_id'];
    $bank_name = $_POST['bank_name'];
    $account_number = $_POST['account_number'];
    $routing_no = $_POST['routing_no'];
    $bank_user_name = $_POST['bank_user_name'];
    $bank_password = $_POST['bank_password'];
    $updated_by = $_SESSION['user_id'];
    $date_updated = date('Y-m-d');

    $query = "UPDATE bank_accounts SET bank_name=?, account_no=?, routing_no=?, bank_user_name=?, bank_password=?, added_by=?, date_updated=? WHERE bank_id=?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("sssssssi", $bank_name, $account_number, $routing_no, $bank_user_name, $bank_password, $updated_by, $date_updated, $bank_id);
    $result = $stmt->execute();

    if ($result) {
        logActivity($con, $updated_by, 'UPDATE', 'bank_account', (int)$bank_id, "Updated bank account ID $bank_id (\"$bank_name\") for client ID: $client_id in secured area");
        header("Location: " . $_SERVER['PHP_SELF'] . "?client_id=$client_id");
        exit();
    } else {
        echo "Error updating bank account: " . $stmt->error;
    }
} 
?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">
<!-- to send -->
<head>
    <title>Syed Professional Services - Dashboard</title>
    <?php include "includes/links.php"; ?>
    <!-- Replaced with CKEditor 5 CDN -->
    <script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>
</head>
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.3.1/css/all.css"
    integrity="sha384-mzrmE5qonljUremFsqc01SB46JvROS7bZs3IO2EmfFsd15uHvIt+Y8vEf7N7fWAU" crossorigin="anonymous">
<style>
    .table-container {
        max-height: 400px;
        overflow-y: auto;
    }

    .tooltip1 {
        position: relative;
        display: inline-block;
    }

    .tooltip1 .tooltiptext1 {
        visibility: hidden;
        width: 120px;
        background-color: #555;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 5px;
        position: absolute;
        z-index: 1;
        bottom: 125%;
        left: 50%;
        margin-left: -60px;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .tooltip1:hover .tooltiptext1 {
        visibility: visible;
        opacity: 1;
    }

    .swal2-icon {
        justify-content: center;
        margin-bottom: 10px;
    }

    .swal2-icon-content {
        font-size: 24px;
        font-weight: bold;
    }
</style>

<body class="theme-3">
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>

    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <!-- Secured Comments Section -->
            <div class="card mb-4">
                <div class="card-body employee-detail-body fulls-card">
                    <div class="row">
                        <div class="col-md-10">
                            <h5>Secured Area<?= mh_icon('secured_comments') ?></h5>
                            <p>Secured User: <?php echo htmlspecialchars($secure_client_name); ?></p>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary addcommentmodal" data-toggle="modal"
                                data-target="#addcomment">Add New</button>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="dataTable-container table-container">
                            <table class="table datatable">
                                <thead>
                                    <tr>
                                        <th style="width: 12%;">Time</th>
                                        <th style="width: 12%;">Comment By</th>
                                        <th style="width: 18%;">Client</th>
                                        <th style="width: 18%;">Business</th>
                                        <th style="width: 30%;">Detail</th>
                                        <th style="width: 10%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $q1 = "SELECT securedcomments.*, members.name, members.mname, members.lname, members.bus_name
                                            FROM securedcomments
                                            JOIN members ON securedcomments.member_id = members.member_id
                                            WHERE securedcomments.member_id = ?";
                                    $stmt = $con->prepare($q1);
                                    $stmt->bind_param("i", $client_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();

                                    while ($Podrow = mysqli_fetch_array($result)) {
                                        $scomment_id = $Podrow["scomment_id"];
                                        ?>
                                        <tr class="border border-success-subtle">
                                            <td><?php echo $Podrow["scomment_time"]; ?></td>
                                            <td>
                                                <?php
                                                $user_id = $Podrow["user_id"];
                                                $user_query = "SELECT user_name FROM users WHERE user_id = ?";
                                                $user_stmt = $con->prepare($user_query);
                                                $user_stmt->bind_param("i", $user_id);
                                                $user_stmt->execute();
                                                $user_result = $user_stmt->get_result();
                                                $user_row = mysqli_fetch_array($user_result);
                                                if ($user_row) {
                                                    echo htmlspecialchars(getInitials($user_row["user_name"])) . " (" . htmlspecialchars($user_id) . ")";
                                                    } else {
                                                        echo "Unknown (" . htmlspecialchars($user_id) . ")";
                                                    }
                                                $user_stmt->close();
                                                ?>
                                            </td>
                                            <td><?php echo $Podrow["name"] . ' ' . $Podrow["mname"] . ' ' . $Podrow["lname"]; ?></td>
                                            <td style="word-wrap: break-word; white-space: normal; overflow-wrap: break-word;"><?php echo $Podrow["bus_name"]; ?></td>
                                            <td>
                                                <div style="max-height: 100px; overflow-y: auto; white-space: normal;">
                                                    <?php echo $Podrow["scomment_detail"]; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary btn-edit-product tooltip1"
                                                    data-toggle="modal" data-target="#editCommentModal"
                                                    data-comment-id="<?php echo $Podrow["scomment_id"]; ?>"
                                                    data-comment-detail="<?php echo htmlspecialchars($Podrow["scomment_detail"], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <i class="ti ti-pencil text-white"></i>
                                                    <span class="tooltiptext1">Edit</span>
                                                </button>
                                                <button class="btn btn-sm btn-custom tooltip1 delete-comment"
                                                    data-toggle="modal"
                                                    data-target="#deleteModal<?php echo $Podrow['scomment_id']; ?>">
                                                    <i class="ti ti-trash text-white"></i>
                                                    <span class="tooltiptext1">Delete</span>
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- Delete Comment Modal -->
                                        <div class="modal fade deleteshowmodal"
                                            id="deleteModal<?php echo $Podrow['scomment_id']; ?>">
                                            <div class="modal-dialog modal-md">
                                                <form method="POST">
                                                    <div class="modal-content">
                                                        <div class="modal-body">
                                                            <div class="row mb-2">
                                                                <div class="col-md-12 mt-2">
                                                                    <input name="comment_id" type="hidden"
                                                                        value="<?php echo $Podrow['scomment_id']; ?>">
                                                                    <div class="swal2-icon swal2-warning swal2-icon-show"
                                                                        style="display: flex;">
                                                                        <div class="swal2-icon-content">!</div>
                                                                    </div>
                                                                    <h2 class="swal2-title" style="display: block;">Are you
                                                                        sure?</h2>
                                                                    <div class="swal2-html-container"
                                                                        style="display: block;">This action cannot be
                                                                        undone.</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer" style="justify-content: center;">
                                                            <button type="button" class="btn btn-info"
                                                                data-dismiss="modal">Close</button>
                                                            <button type="submit" class="btn btn-primary"
                                                                name="delete_comment">Delete</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Cards Section -->
            <?php if ($acc_bank_card_area == "Yes") { ?>
                <div class="card mb-4">
                    <div class="card-body employee-detail-body fulls-card">
                        <div class="row">
                            <div class="col-md-10">
                                <h5>Payment Cards Info<?= mh_icon('cards') ?></h5>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary" data-toggle="modal" data-target="#addcard">Add New
                                    Card</button>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="dataTable-container table-container">
                                <table class="table datatable1">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Type</th>
                                            <th>Name</th>
                                            <th>Card No</th>
                                            <th>Exp Mo</th>
                                            <th>Exp Yr</th>
                                            <th>CVV</th>
                                            <th>Zip</th>
                                            <th>Address</th>
                                            <th>Date Added</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $querr1 = mysqli_query($con, "SELECT * FROM billing_card 
                                                                    INNER JOIN billing_card_type 
                                                                    ON billing_card.card_type = billing_card_type.card_type_id 
                                                                    AND billing_card.mem_id = $client_id");
                                        $c = 1;
                                        while ($Podrow = mysqli_fetch_array($querr1)) {
                                            ?>
                                            <tr>
                                                <td><?php echo $c; ?></td>
                                                <td><span
                                                        class="badge badge-pill badge-success"><?php echo $Podrow["card_type_name"]; ?></span>
                                                </td>
                                                <td><?php echo $Podrow["card_onname"]; ?></td>
                                                <td><?php echo $Podrow["card_number"]; ?></td>
                                                <td><?php echo $Podrow["card_exp_month"]; ?></td>
                                                <td><?php echo $Podrow["card_exp_year"]; ?></td>
                                                <td><?php echo $Podrow["card_cvv"]; ?></td>
                                                <td><?php echo $Podrow["card_zip"]; ?></td>
                                                <td><?php echo $Podrow["card_address"]; ?></td>
                                                <td><?php echo !empty($Podrow["date_added"]) ? date('m/d/Y', strtotime($Podrow["date_added"])) : '—'; ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning tooltip1" data-toggle="modal"
                                                        data-target="#editcard<?php echo $Podrow['card_id']; ?>">
                                                        <i class="ti ti-pencil text-white"></i>
                                                        <span class="tooltiptext1">Edit</span>
                                                    </button>
                                                    <button class="btn btn-sm btn-info tooltip1" data-toggle="modal"
                                                        data-target="#cardhis<?php echo $Podrow['card_id']; ?>">
                                                        <i class="ti ti-clock text-white"></i>
                                                        <span class="tooltiptext1">History</span>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger tooltip1" data-toggle="modal"
                                                        data-target="#deletecard<?php echo $Podrow['card_id']; ?>">
                                                        <i class="ti ti-trash text-white"></i>
                                                        <span class="tooltiptext1">Delete</span>
                                                    </button>
                                                </td>
                                            </tr>

                                            <!-- Card History Modal -->
                                            <div class="modal fade" id="cardhis<?php echo $Podrow['card_id']; ?>">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h4 class="modal-title">Card Edit History</h4>
                                                            <button type="button" class="close"
                                                                data-dismiss="modal">&times;</button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row font-weight-bold mb-2" style="background:#f8f9fd">
                                                                <div class="col">Date Edited</div>
                                                                <div class="col">Edited By</div>
                                                                <div class="col">Card Name</div>
                                                                <div class="col">Card Number</div>
                                                                <div class="col">Expiry Month</div>
                                                                <div class="col">Expiry Year</div>
                                                                <div class="col">CVV</div>
                                                                <div class="col">Zip</div>
                                                                <div class="col">Address</div>
                                                            </div>
                                                            <?php
                                                            $edit_history_query = mysqli_query($con, "SELECT card_edit_history.*, user_name AS username 
                                                                                                    FROM card_edit_history 
                                                                                                    JOIN users ON card_edit_history.edited_by = users.user_id
                                                                                                    WHERE card_id = '$Podrow[card_id]' 
                                                                                                    ORDER BY edit_date DESC");
                                                            while ($edit_row = mysqli_fetch_array($edit_history_query)) {
                                                                ?>
                                                                <div class="row mb-2">
                                                                    <div class="col border" style="font-size:12px">
                                                                        <?php echo date('Y-m-d', strtotime($edit_row['edit_date'])); ?>
                                                                    </div>
                                                                    <div class="col border"><?php echo $edit_row['username']; ?>
                                                                    </div>
                                                                    <div class="col border"><?php echo $edit_row['card_name']; ?>
                                                                    </div>
                                                                    <div class="col border"><?php echo $edit_row['card_no']; ?>
                                                                    </div>
                                                                    <div class="col border">
                                                                        <?php echo $edit_row['card_exp_month']; ?>
                                                                    </div>
                                                                    <div class="col border">
                                                                        <?php echo $edit_row['card_exp_year']; ?>
                                                                    </div>
                                                                    <div class="col border"><?php echo $edit_row['card_cvv']; ?>
                                                                    </div>
                                                                    <div class="col border"><?php echo $edit_row['card_zip']; ?>
                                                                    </div>
                                                                    <div class="col border"><?php echo $edit_row['card_address']; ?>
                                                                    </div>
                                                                </div>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Edit Card Modal -->
                                            <div class="modal fade" id="editcard<?php echo $Podrow['card_id']; ?>">
                                                <div class="modal-dialog modal-lg">
                                                    <form method="POST">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h4 class="modal-title">Edit Card Info</h4>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="card_id"
                                                                    value="<?php echo $Podrow['card_id']; ?>">
                                                                <div class="row">
                                                                    <div class="col-md-12">
                                                                        <label>Card Type</label>
                                                                        <select class="form-control" required name="card_type">
                                                                            <option value="" selected>Select Card Type</option>
                                                                            <?php
                                                                            $querr = mysqli_query($con, "SELECT * FROM billing_card_type");
                                                                            while ($genetics = mysqli_fetch_array($querr)) { ?>
                                                                                <option value="<?= $genetics['card_type_id']; ?>"
                                                                                    <?= ($Podrow['card_type'] == $genetics['card_type_id']) ? 'selected="selected"' : ''; ?>>
                                                                                    <?= $genetics['card_type_name']; ?>
                                                                                </option>
                                                                            <?php } ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label>Name On Card</label>
                                                                        <input type="text" name="card_name" class="form-control"
                                                                            value="<?php echo $Podrow['card_onname']; ?>">
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label>Card No (16 digits)</label>
                                                                        <input type="text" name="card_no" class="form-control"
                                                                            value="<?php echo $Podrow['card_number']; ?>">
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label>Expiry Month</label>
                                                                        <select class="form-control" required
                                                                            name="card_exp_mo">
                                                                            <?php for ($i = 1; $i <= 12; $i++) { ?>
                                                                                <option value="<?php echo $i; ?>" <?php if ($Podrow['card_exp_month'] == $i)
                                                                                    echo 'selected'; ?>>
                                                                                    <?php echo $i; ?>
                                                                                </option>
                                                                            <?php } ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label>Expiry Year</label>
                                                                        <input type="number" name="card_exp_yo"
                                                                            class="form-control"
                                                                            value="<?php echo $Podrow['card_exp_year']; ?>">
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label>CVV (4 Digits)</label>
                                                                        <input type="text" name="card_cvv"
                                                                            class="form-control" inputmode="numeric"
                                                                            maxlength="4"
                                                                            oninput="this.value=this.value.replace(/\D/g,'').slice(0,4)"
                                                                            value="<?php echo $Podrow['card_cvv']; ?>">
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label>Zip</label>
                                                                        <input type="text" name="zip" class="form-control"
                                                                            value="<?php echo $Podrow['card_zip']; ?>">
                                                                    </div>
                                                                    <div class="col-md-12">
                                                                        <label>Address</label>
                                                                        <input type="text" name="address" class="form-control"
                                                                            value="<?php echo $Podrow['card_address']; ?>">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-info"
                                                                    data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-primary"
                                                                    name="updatecard">Save</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>

                                            <!-- Delete Card Modal -->
                                            <div class="modal fade" id="deletecard<?php echo $Podrow['card_id']; ?>">
                                                <div class="modal-dialog modal-md">
                                                    <form method="POST">
                                                        <div class="modal-content">
                                                            <div class="modal-body">
                                                                <div class="row mb-2">
                                                                    <div class="col-md-12 mt-2">
                                                                        <input name="card_id" type="hidden"
                                                                            value="<?php echo $Podrow['card_id']; ?>">
                                                                        <div class="swal2-icon swal2-warning swal2-icon-show"
                                                                            style="display: flex;">
                                                                            <div class="swal2-icon-content">!</div>
                                                                        </div>
                                                                        <h2 class="swal2-title" style="display: block;">Are you
                                                                            sure?</h2>
                                                                        <div class="swal2-html-container"
                                                                            style="display: block;">This action cannot be
                                                                            undone.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer" style="justify-content: center;">
                                                                <button type="button" class="btn btn-info"
                                                                    data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-danger"
                                                                    name="delete_card">Delete</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            <?php $c++;
                                        } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bank Accounts Section -->
                <div class="card">
                    <div class="card-body employee-detail-body fulls-card">
                        <div class="row">
                            <div class="col-md-9">
                                <h5>Bank Account's Info<?= mh_icon('bank_accounts') ?></h5>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary" data-toggle="modal" data-target="#addbankaccount">Add Bank
                                    Account</button>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="dataTable-container table-container">
                                <table class="table datatable2">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Bank Name</th>
                                            <th>Login Details</th>
                                            <th>Account No</th>
                                            <th>Routing No</th>
                                            <th>Date Added</th>
                                            <th>Date Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $bank_querr1 = mysqli_query($con, "SELECT * FROM bank_accounts where client_id=$client_id");
                                        $c = 1;
                                        while ($fetch_bank = mysqli_fetch_array($bank_querr1)) {
                                            ?>
                                            <tr>
                                                <td><?php echo $c; ?></td>
                                                <td><?php echo $fetch_bank["bank_name"]; ?></td>
                                                <td>
                                                    <strong>User:</strong> <?php echo $fetch_bank["bank_user_name"]; ?><br />
                                                    <strong>Pass:</strong> <?php echo $fetch_bank["bank_password"]; ?>
                                                </td>
                                                <td><?php echo $fetch_bank["account_no"]; ?></td>
                                                <td><?php echo $fetch_bank["routing_no"]; ?></td>
                                                <td><?php echo !empty($fetch_bank["date_added"]) ? date('m/d/Y', strtotime($fetch_bank["date_added"])) : '—'; ?></td>
                                                <td><?php echo !empty($fetch_bank["date_updated"]) ? date('m/d/Y', strtotime($fetch_bank["date_updated"])) : '—'; ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning tooltip1" data-toggle="modal"
                                                        data-target="#editbank<?php echo $fetch_bank['bank_id']; ?>">
                                                        <i class="ti ti-pencil text-white"></i>
                                                        <span class="tooltiptext1">Edit</span>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger tooltip1" data-toggle="modal"
                                                        data-target="#deletebank<?php echo $fetch_bank['bank_id']; ?>">
                                                        <i class="ti ti-trash text-white"></i>
                                                        <span class="tooltiptext1">Delete</span>
                                                    </button>
                                                </td>
                                            </tr>

                                            <!-- Edit Bank Modal -->
                                            <div class="modal fade" id="editbank<?php echo $fetch_bank['bank_id']; ?>">
                                                <div class="modal-dialog modal-lg">
                                                    <form method="POST">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h4 class="modal-title">Edit Bank Account</h4>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="bank_id"
                                                                    value="<?php echo $fetch_bank['bank_id']; ?>">
                                                                <div class="row">
                                                                    <div class="col-md-12">
                                                                        <label>Bank Name</label>
                                                                        <input type="text" name="bank_name" class="form-control"
                                                                            value="<?php echo $fetch_bank['bank_name']; ?>">
                                                                    </div>
                                                                    <div class="col-md-12">
                                                                        <label>Account Number</label>
                                                                        <input type="text" name="account_number"
                                                                            class="form-control"
                                                                            value="<?php echo $fetch_bank['account_no']; ?>">
                                                                    </div>
                                                                    <div class="col-md-12">
                                                                        <label>Routing Number</label>
                                                                        <input type="text" name="routing_no"
                                                                            class="form-control"
                                                                            value="<?php echo $fetch_bank['routing_no']; ?>">
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label>Bank Username</label>
                                                                        <input type="text" name="bank_user_name"
                                                                            class="form-control"
                                                                            value="<?php echo $fetch_bank['bank_user_name']; ?>">
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label>Bank Password</label>
                                                                        <input type="text" name="bank_password"
                                                                            class="form-control"
                                                                            value="<?php echo $fetch_bank['bank_password']; ?>">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-info"
                                                                    data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-primary"
                                                                    name="updatebank">Save</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>

                                            <!-- Delete Bank Modal -->
                                            <div class="modal fade" id="deletebank<?php echo $fetch_bank['bank_id']; ?>">
                                                <div class="modal-dialog modal-md">
                                                    <form method="POST">
                                                        <div class="modal-content">
                                                            <div class="modal-body">
                                                                <div class="row mb-2">
                                                                    <div class="col-md-12 mt-2">
                                                                        <input name="card_id" type="hidden"
                                                                            value="<?php echo $fetch_bank['bank_id']; ?>">
                                                                        <div class="swal2-icon swal2-warning swal2-icon-show"
                                                                            style="display: flex;">
                                                                            <div class="swal2-icon-content">!</div>
                                                                        </div>
                                                                        <h2 class="swal2-title" style="display: block;">Are you
                                                                            sure?</h2>
                                                                        <div class="swal2-html-container"
                                                                            style="display: block;">This action cannot be
                                                                            undone.</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer" style="justify-content: center;">
                                                                <button type="button" class="btn btn-info"
                                                                    data-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-danger"
                                                                    name="delete_bank">Delete</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            <?php $c++;
                                        } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Add Comment Modal -->
    <div class="modal fade" id="addcomment">
        <div class="modal-dialog modal-lg">
            <form method="POST" id="commentForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Add Comment</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="commentId" name="commentId" value="">
                        <input type="hidden" id="commentsecureInput" name="commentsecureInput" value="">

                        <!-- Quick Phrases Section -->
                        <label class="form-label">Quick phrases</label>
                        <div class="mb-2">
                            <?php
                            $uid = (int) $_SESSION['user_id'];
                            $res = mysqli_query($con, "SELECT phrase FROM user_quick_phrases WHERE user_id=$uid ORDER BY id");
                            while ($row = mysqli_fetch_assoc($res)) {
                                // Pass the editor ID to the addPhrase function
                                echo '<button type="button" class="btn btn-sm btn-outline-primary me-1 mb-1"
                                        onclick="addPhrase(\'' . htmlspecialchars($row['phrase'], ENT_QUOTES) . '\', \'commentsecure\')">'
                                    . htmlspecialchars($row['phrase']) . '</button>';
                            }
                            ?>
                            <a href="quickphrases.php" class="btn btn-primary btn-sm">Edit Phrases</a>
                        </div>

                        <label for="commentsecure" class="form-label">Comment</label>
                        <!-- Changed from div to textarea for CKEditor -->
                        <textarea id="commentsecure" name="commentsecure" class="ckeditor"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" name="save" id="save">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Comment Modal -->
    <div class="modal fade" id="editCommentModal">
        <div class="modal-dialog modal-md">
            <form method="POST" id="editCommentForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Edit Comment</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editCommentId" name="commentId">
                        
                        <!-- Quick Phrases Section -->
                        <label class="form-label">Quick phrases</label>
                        <div class="mb-2">
                            <?php
                            $uid = (int) $_SESSION['user_id'];
                            $res = mysqli_query($con, "SELECT phrase FROM user_quick_phrases WHERE user_id=$uid ORDER BY id");
                            while ($row = mysqli_fetch_assoc($res)) {
                                // Pass the editor ID to the addPhrase function
                                echo '<button type="button" class="btn btn-sm btn-outline-primary me-1 mb-1"
                                        onclick="addPhrase(\'' . htmlspecialchars($row['phrase'], ENT_QUOTES) . '\', \'editCommentInput\')">'
                                    . htmlspecialchars($row['phrase']) . '</button>';
                            }
                            ?>
                            <a href="quickphrases.php" class="btn btn-primary btn-sm">Edit Phrases</a>
                        </div>

                        <label for="editCommentInput" class="form-label">Comment</label>
                        <!-- Changed to use CKEditor class -->
                        <textarea class="ckeditor" id="editCommentInput" name="commentsecureInput" rows="5"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" name="save">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Card Modal -->
    <div class="modal fade" id="addcard">
        <div class="modal-dialog modal-lg">
            <form method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Add Payment Card</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <label>Card Type</label>
                                <select class="form-control" required id="card_type" name="card_type"
                                    onchange="card_expiry_onchange()">
                                    <option value="" selected>Select Card Type</option>
                                    <?php
                                    $querr = mysqli_query($con, "SELECT * FROM billing_card_type");
                                    while ($genetics = mysqli_fetch_array($querr)) { ?>
                                        <option value="<?php echo $genetics['card_type_id']; ?>">
                                            <?php echo $genetics['card_type_name']; ?>
                                        </option>
                                    <?php } ?>
                                    <option value="others">Others</option>
                                </select>
                            </div>
                            <div id="hid_bank_card_name"></div>
                            <div class="col-md-6">
                                <label>Name On Card</label>
                                <input type="text" name="card_name" class="form-control" placeholder="Name on card">
                            </div>
                            <div class="col-md-6">
                                <label>Card No (16 digits)</label>
                                <input type="text" id="card_no" name="card_no" class="form-control"
                                    placeholder="Card number">
                            </div>
                            <div class="col-md-6">
                                <label>Expiry Month</label>
                                <select class="form-control" required id="card_exp_mo" name="card_exp_mo">
                                    <?php for ($i = 1; $i <= 12; $i++) { ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label>Expiry Year</label>
                                <input type="number" id="card_exp_yo" name="card_exp_yo" class="form-control"
                                    placeholder="Expiry year">
                            </div>
                            <div class="col-md-6">
                                <label>CVV (4 Digits)</label>
                                <input type="text" id="card_cvv" name="card_cvv" class="form-control"
                                    placeholder="CVV" inputmode="numeric" maxlength="4"
                                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,4)">
                            </div>
                            <div class="col-md-6">
                                <label>Zip</label>
                                <input type="text" name="zip" class="form-control" placeholder="Zip"
                                    oninput="formatZipCode(this)">
                            </div>
                            <div class="col-md-12">
                                <label>Address</label>
                                <input type="text" name="address" class="form-control" placeholder="Address">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" name="addcard">Add Card</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Bank Account Modal -->
    <div class="modal fade" id="addbankaccount">
        <div class="modal-dialog modal-lg">
            <form method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Add Bank Account</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <label>Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" placeholder="Bank name">
                            </div>
                            <div class="col-md-12">
                                <label>Account Number</label>
                                <input type="text" name="account_number" class="form-control"
                                    placeholder="Account number">
                            </div>
                            <div class="col-md-12">
                                <label>Routing Number</label>
                                <input type="text" name="routing_no" class="form-control" placeholder="Routing number">
                            </div>
                            <div class="col-md-6">
                                <label>Bank Username</label>
                                <input type="text" name="bank_user_name" class="form-control" placeholder="Username">
                            </div>
                            <div class="col-md-6">
                                <label>Bank Password</label>
                                <input type="text" name="bank_password" class="form-control" placeholder="Password">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" name="addbank">Add Account</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
      // Store CKEditor instances in a global object for easy access
      window.ckeditorInstances = {};
 
      /* ---------- 1.  turn every <textarea class="ckeditor"> into CKE 5  ---------- */
      document.querySelectorAll('textarea.ckeditor').forEach(tx => {
        ClassicEditor
          .create(tx, {
            toolbar: {
              items: [
                'undo', 'redo',
                '|', 'heading',
                '|', 'bold', 'italic',
                '|', 'link', 'uploadImage',
                '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
              ],
              shouldNotGroupWhenFull: true
            },
            language: 'en',
            licenseKey: ''
          })
          .then(editor => {
            // Store the instance with its ID as the key
            window.ckeditorInstances[tx.id] = editor;
          })
          .catch(error => {
            console.error(error);
          });
      });

      // Function to add a phrase to the specified CKEditor instance
      function addPhrase(text, editorId) {
        // Find the editor instance by ID
        const editor = window.ckeditorInstances[editorId];
        
        if (!editor) {
          console.error('Editor instance not found for ID: ' + editorId);
          return;
        }

        // Insert the text at the current cursor position
        editor.model.change(writer => {
          // Get the current selection position
          const position = editor.model.document.selection.getFirstPosition();
          
          // Insert the text with a space after it
          writer.insertText(text + ' ', position);
        });
        
        // Focus the editor
        editor.editing.view.focus();
      }
      
      $(document).ready(function () {
          // Handle comment editing
          $('.btn-edit-product').click(function () {
              var commentId = $(this).data('comment-id');
              var commentDetail = $(this).data('comment-detail');

              // Create temporary element to decode HTML entities
              var tempElement = document.createElement('textarea');
              tempElement.innerHTML = commentDetail;
              var decodedCommentDetail = tempElement.value;

              $('#editCommentId').val(commentId);
              
              // Set data in CKEditor after it's initialized
              setTimeout(function() {
                  const editor = window.ckeditorInstances['editCommentInput'];
                  if (editor) {
                      editor.setData(decodedCommentDetail);
                  }
              }, 100); // Small delay to ensure modal is rendered
              
              $('#editCommentModal').modal('show');
          });

          // Prepare new comment modal
          $('.addcommentmodal').click(function () {
              $('#commentId').val('');
              // Clear CKEditor content
              setTimeout(function() {
                  const editor = window.ckeditorInstances['commentsecure'];
                  if (editor) {
                      editor.setData('');
                  }
              }, 100); // Small delay to ensure modal is rendered
          });

          // Handle comment saving for the main comment form
          $('#commentForm').on('submit', function (event) {
              // Get data from CKEditor and set to hidden input
              const editor = window.ckeditorInstances['commentsecure'];
              if (editor) {
                  $("#commentsecureInput").val(editor.getData());
              } else {
                   // If editor not found, prevent submission or handle error
                   console.error("CKEditor instance for 'commentsecure' not found on submit.");
                   event.preventDefault(); 
              }
          });

          // Handle comment saving for the edit comment form
          $('#editCommentForm').on('submit', function (event) {
              // Get data from CKEditor and set to hidden input
              const editor = window.ckeditorInstances['editCommentInput'];
              if (editor) {
                  $("#commentsecureInput").val(editor.getData());
              } else {
                   console.error("CKEditor instance for 'editCommentInput' not found on submit.");
                   event.preventDefault();
              }
          });

          // Card input validation
          $('#card_no').on('input', function () {
              if (this.value.length > 16) {
                  this.value = this.value.slice(0, 16);
              }
          });

          $('#card_cvv').on('input', function () {
              if (this.value.length > 4) {
                  this.value = this.value.slice(0, 4);
              }
          });

          // Handle "Other" card type
          $('#card_type').change(function () {
              if ($(this).val() === 'others') {
                  $('#hid_bank_card_name').html(`
                      <div class="row mt-3">
                          <div class="col-md-12">
                              <label>Bank Card Type</label>
                              <input type="text" name="bankcardtype" class="form-control" placeholder="Enter card type">
                          </div>
                      </div>
                  `);
              } else {
                  $('#hid_bank_card_name').html('');
              }
          });
      });
 
      // Format zip code input
      function formatZipCode(input) {
          let zip = input.value.replace(/\D/g, '');
          zip = zip.substring(0, 5);
          input.value = zip;
      }

      // Handle page refresh when closing modals (optional)
      $('button[data-dismiss="modal"]').click(function () {
          // Removed location.reload() to prevent unwanted refreshes
      });
    </script>
</body>
</html>
<?php
// $_SESSION['secure_auth']=0;
?>