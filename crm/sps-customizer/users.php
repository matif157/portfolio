<?php
include "includes/auth.php";
include "includes/db.php";

// Create permissions table if not exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS user_permissions (
    user_id INT PRIMARY KEY,
    add_new_client ENUM('Yes','No') DEFAULT 'No',
    view_clients_list ENUM('Yes','No') DEFAULT 'No',
    client_detail_area ENUM('Yes','No') DEFAULT 'No',
    client_billing_area ENUM('Yes','No') DEFAULT 'No',
    delete_client ENUM('Yes','No') DEFAULT 'No',
    view_products ENUM('Yes','No') DEFAULT 'No',
    add_edit_products ENUM('Yes','No') DEFAULT 'No',
    delete_products ENUM('Yes','No') DEFAULT 'No',
    view_staff ENUM('Yes','No') DEFAULT 'No',
    add_edit_staff ENUM('Yes','No') DEFAULT 'No',
    delete_staff ENUM('Yes','No') DEFAULT 'No',
    view_entity_form ENUM('Yes','No') DEFAULT 'No',
    add_edit_form ENUM('Yes','No') DEFAULT 'No',
    delete_form ENUM('Yes','No') DEFAULT 'No',
    view_product_cycle ENUM('Yes','No') DEFAULT 'No',
    add_edit_cycle ENUM('Yes','No') DEFAULT 'No',
    delete_cycle ENUM('Yes','No') DEFAULT 'No',
    invoice_and_billing ENUM('Yes','No') DEFAULT 'No',
    estimates_and_qutations ENUM('Yes','No') DEFAULT 'No',
    bank_card_area ENUM('Yes','No') DEFAULT 'No',
    client_active ENUM('Yes','No') DEFAULT 'No',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";
mysqli_query($con, $create_table_sql);

error_reporting(0);

if (isset($_GET['data'])) {
    $msg = '';
    $data = $_GET['data'];
    if ($data == "saved") {
        $msg .= "<div class='alert alert-success'>Staff Member Has Been Added Successfully.</div>";
    } elseif ($data == "updated") {
        $msg .= "<div class='alert alert-success'>Staff Member Has Been Updated Successfully.</div>";
    } elseif ($data == "disabled") {
        $msg .= "<div class='alert alert-success'>Staff Member Has Been Disabled Successfully.</div>";
    } elseif ($data == "enabled") {
        $msg .= "<div class='alert alert-success'>Staff Member Has Been Enabled Successfully.</div>";
    }
}

if (isset($_POST['add_new'])) {

    $full_name = $_POST["fullname"];
    $designation = $_POST["designation"];
    $email = $_POST["email"];
    $username = $_POST["username"];
    $password = $_POST["pass"];
    $usertype = $_POST["usertype"];

    //Uploading Featured Image
    $pic_name = $_FILES['post_image']['name'];
    $pic_temp = $_FILES['post_image']['tmp_name'];
    $pic_size = $_FILES['post_image']['size'];

    $pic_new = rand(100, 1000) . '-' . $pic_name;
    $pic_store = "uploads/staff_images/" . $pic_new;

    if (move_uploaded_file($pic_temp, $pic_store)) {
        $pic = $pic_new;
    }

    $has_secured_access = $_POST["has_secured_access"];

    $user_country = isset($_POST['user_country']) ? strtoupper(trim($_POST['user_country'])) : 'PK';
    if ($user_country !== 'US' && $user_country !== 'PK') { $user_country = 'PK'; }

    $insertdata = "INSERT INTO users(user_login,user_password,user_name,user_email,user_desig,user_pic,user_type,has_secured_access,user_country) VALUES('$username', '$password', '$full_name', '$email', '$designation', '$pic', '$usertype', '$has_secured_access', '$user_country')";
    $insert_query = mysqli_query($con, $insertdata);

    if ($insert_query) {
        header("Location: users.php?data=saved");
        exit;
    } else {
        $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
    }

}

if (isset($_POST['update_product'])) {
    $msg = '';

    $staffid = $_POST['staffid'];
    $full_name = $_POST["fullname"];
    $designation = $_POST["designation"];
    $email = $_POST["email"];
    $username = $_POST["username"];
    $password = $_POST["pass"];
    $usertype = $_POST["usertype"];

    $has_secured_access = $_POST["has_secured_access"];

    $user_country = isset($_POST['user_country']) ? strtoupper(trim($_POST['user_country'])) : 'PK';
    if ($user_country !== 'US' && $user_country !== 'PK') { $user_country = 'PK'; }

    $queryy = "UPDATE users SET user_login='$username',user_password='$password',user_name='$full_name',user_email='$email',user_desig='$designation',user_type='$usertype',has_secured_access='$has_secured_access',user_country='$user_country' WHERE user_id='$staffid'";
    $insert_query = mysqli_query($con, $queryy);

    if ($insert_query) {
        header("Location: users.php?data=updated");
        exit;
    } else {
        $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
    }

}
if (isset($_POST['toggle_status'])) {
    $msg = '';
    $id = $_POST['id'];

    // Get current status
    $status_query = "SELECT user_ban_status FROM users WHERE user_id='$id'";
    $status_result = mysqli_query($con, $status_query);
    $current_status = mysqli_fetch_assoc($status_result)['user_ban_status'];

    // Toggle status (0 = Active, 1 = Disabled)
    $new_status = ($current_status == '0') ? '1' : '0';
    $queryy = "UPDATE users SET user_ban_status='$new_status' WHERE user_id='$id'";
    $insert_query = mysqli_query($con, $queryy);

    if ($insert_query) {
        $status_message = ($new_status == '1') ? 'disabled' : 'enabled';
        header("Location: users.php?data=$status_message");
        exit;
    } else {
        $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
    }
}
if (isset($_POST['delete_product'])) {
    $msg = '';

    $id = $_POST['id'];

    $queryy = "DELETE FROM users where user_id='$id'";
    $insert_query = mysqli_query($con, $queryy);
    echo "<script>window.location = 'users.php';</script>";
    exit;

}

$mysqli = mysqli_connect('localhost', $username, $password, $database, $port);

$quer = "SELECT * FROM users ORDER By user_name desc";

$total_pages = $mysqli->query($quer)->num_rows;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;

$num_results_on_page = 25;
$que = "SELECT * FROM users ORDER BY user_name ASC LIMIT ?,?";

if ($stmt = $mysqli->prepare($que)) {
    $calc_page = ($page - 1) * $num_results_on_page;
    $stmt->bind_param('ii', $calc_page, $num_results_on_page);
    $stmt->execute();
    $result = $stmt->get_result();
    ?>

    <!DOCTYPE html>
    <html lang="en" dir="">
    <meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

    <head>
        <title>Syed Professional Services - Dashboard</title>
        <?php include "includes/links.php"; ?>
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
                                    <h4 class="m-b-10">Manage Staff</h4>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                    <li class="breadcrumb-item">Staff</li>
                                </ul>
                            </div>
                            <div class="col"> 
                                <div class="float-end">
                                    <?php if ($acc_add_edit_staff == "Yes") { ?>
                                        <button class="btn btn-primary" data-toggle="modal" data-target="#addProduct">Add
                                            Staff</button><?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <?php if (isset($msg)) {
                            echo $msg;
                        } ?>
                        <div class="card">
                            <div class="card-body table-border-style">
                                <div class="table-responsive">
                                    <div
                                        class="dataTable-wrapper dataTable-loading no-footer sortable searchable fixed-columns">
                                        <div class="dataTable-top">
                                            <div class="dataTable-dropdown">
                                                <label>
                                                    <select class="dataTable-selector">
                                                        <option value="5">5</option>
                                                        <option value="10" selected="">10</option>
                                                        <option value="15">15</option>
                                                        <option value="20">20</option>
                                                        <option value="25">25</option>
                                                    </select>
                                                    entries per page
                                                </label>
                                            </div>
                                            <div class="dataTable-search"><input class="dataTable-input"
                                                    placeholder="Search..." type="text"></div>
                                        </div>
                                        <div class="dataTable-container">
                                            <table class="table datatable dataTable-table">
                                                <thead>
                                                    <tr>
                                                        <th data-sortable=""><a href="#" class="dataTable-sorter">User
                                                                ID</a></th>
                                                        <th data-sortable=""><a href="#" class="dataTable-sorter">Name</a>
                                                        </th>
                                                        <th data-sortable=""><a href="#" class="dataTable-sorter">Email</a>
                                                        </th>
                                                        <th data-sortable=""><a href="#"
                                                                class="dataTable-sorter">Designation</a></th>
                                                        <th data-sortable=""><a href="#" class="dataTable-sorter">Type</a>
                                                        </th>
                                                        <th data-sortable=""><a href="#" class="dataTable-sorter">Status</a>
                                                        </th>
                                                        <th><a href="#" class="dataTable-sorter">Action</a></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($rrow = $result->fetch_assoc()): ?>
                                                        <tr>
                                                            <td>
                                                                <a href="user_profile.php?id=<?php echo $rrow['user_id']; ?>"
                                                                    class="btn btn-outline-primary">
                                                                    <?php echo $rrow['user_id']; ?>
                                                                </a>
                                                            </td>

                                                            <td><a href="userproinv.php?id=<?php echo $rrow['user_id']; ?>"
                                                                    target="_blank"><?php echo $rrow['user_name']; ?></a></td>
                                                            <td><?php echo $rrow['user_email']; ?></td>
                                                            <td><?php echo $rrow['user_desig']; ?></td>
                                                            <td>
                                                                <?php
                                                                $mtype = $rrow['user_type'];
                                                                $query = mysqli_query($con, "SELECT * FROM user_type where user_type_id='$mtype'");
                                                                while ($genetics = mysqli_fetch_array($query)) {
                                                                    echo $genetics['user_type_name'];
                                                                }
                                                                ?>
                                                                <?php if ($rrow['has_secured_access'] == 1): ?>
                                                                    <span class="badge bg-success ms-2">Secured Area Access</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($rrow['user_ban_status'] == '0') { ?>
                                                                    <span class="badge badge-pill badge-success">Active</span>
                                                                <?php } else { ?>
                                                                    <span class="badge badge-pill badge-danger">Ban</span>
                                                                <?php } ?>
                                                            </td>
                                                            <td>
                                                                <a href="staff_details.php?id=<?php echo $rrow['user_id']; ?>">
                                                                    <button class="btn btn-sm btn-info tooltip1"><i
                                                                            class="ti ti-eye text-white"></i></i><span
                                                                            class="tooltiptext1">View User</span></button></a>
                                                                <?php if ($acc_add_edit_staff == "Yes") { ?>
                                                                    <button class="btn btn-sm btn-primary tooltip1"
                                                                        data-toggle="modal"
                                                                        data-target="#editProduct<?php echo $rrow['user_id']; ?>"><i
                                                                            class="ti ti-pencil text-white"></i></i><span
                                                                            class="tooltiptext1">Edit User</span></button>
                                                                <?php } ?>

                                                                <?php if ($acc_delete_staff == "Yes") { ?>
                                                                    <button class="btn btn-sm btn-custom tooltip1"
                                                                        data-toggle="modal"
                                                                        data-target="#toggleStatus<?php echo $rrow['user_id']; ?>">
                                                                        <i class="ti ti-ban text-white"></i>
                                                                        <span
                                                                            class="tooltiptext1"><?php echo ($rrow['user_ban_status'] == '0') ? 'Disable User' : 'Enable User'; ?></span>
                                                                    </button>
                                                                <?php } ?>

                                                                <?php if ($acc_add_edit_staff == "Yes") { ?>
                                                                    <button
                                                                        class="btn btn-sm <?php echo ($rrow['has_secured_access'] ? 'btn-success' : 'btn-warning'); ?> tooltip1"
                                                                        data-toggle="modal"
                                                                        data-target="#securedAccessModal<?php echo $rrow['user_id']; ?>">
                                                                        <i class="ti ti-shield text-white"></i>
                                                                        <span class="tooltiptext1">
                                                                            <?php echo ($rrow['has_secured_access'] ? 'Revoke Access' : 'Grant Access'); ?>
                                                                        </span>
                                                                    </button>
                                                                <?php } ?>

                                                                <?php if ($acc_add_edit_staff == "Yes") { ?>
                                                                    <a href="user_permissions.php?user_id=<?= $rrow['user_id'] ?>"
                                                                        class="btn btn-sm btn-warning tooltip1">
                                                                        <i class="ti ti-lock text-white"></i>
                                                                        <span class="tooltiptext1">Custom Permissions</span>
                                                                    </a>
                                                                <?php } ?>
                                                                <!-- View Projects & Invoices button -->
                                                                <a href="userproinv.php?uid=<?= $rrow['user_id'] ?>"
                                                                    class="btn btn-sm btn-success tooltip1" target="_blank">
                                                                    <i class="ti ti-briefcase text-white"></i>
                                                                    <span class="tooltiptext1">Projects & Invoices</span>
                                                                </a>

                                                            </td>
                                                        </tr>


                                                        <div class="modal fade"
                                                            id="securedAccessModal<?php echo $rrow['user_id']; ?>">
                                                            <div class="modal-dialog modal-md">
                                                                <form method="POST" action="secured_access.php">
                                                                    <div class="modal-content">
                                                                        <div class="modal-body">
                                                                            <input type="hidden" name="user_id"
                                                                                value="<?php echo $rrow['user_id']; ?>">
                                                                            <input type="hidden" name="current_status"
                                                                                value="<?php echo $rrow['has_secured_access']; ?>">
                                                                            <h4>Are you sure?</h4>
                                                                            <p>
                                                                                <?php
                                                                                if ($rrow['has_secured_access']) {
                                                                                    echo "Revoke secured area access from " . $rrow['user_name'] . "?";
                                                                                } else {
                                                                                    echo "Grant secured area access to " . $rrow['user_name'] . "?";
                                                                                }
                                                                                ?>
                                                                            </p>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-info"
                                                                                data-dismiss="modal">Cancel</button>
                                                                            <button type="submit" class="btn btn-primary"
                                                                                name="update_secured_access">Confirm</button>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>

                                                        <div class="modal fade"
                                                            id="toggleStatus<?php echo $rrow['user_id']; ?>">
                                                            <div class="modal-dialog modal-md">
                                                                <form method="POST">
                                                                    <div class="modal-content">
                                                                        <div class="modal-body">
                                                                            <div class="row mb-2">
                                                                                <div class="col-md-12 mt-2">
                                                                                    <input name="id" type="hidden"
                                                                                        value="<?php echo $rrow['user_id']; ?>">
                                                                                    <div class="swal2-icon swal2-warning swal2-icon-show"
                                                                                        style="display: flex;">
                                                                                        <div class="swal2-icon-content">!</div>
                                                                                    </div>
                                                                                    <h2 class="swal2-title" id="swal2-title"
                                                                                        style="display: block;">
                                                                                        Are you sure?
                                                                                    </h2>
                                                                                    <div class="swal2-html-container"
                                                                                        id="swal2-html-container"
                                                                                        style="display: block;">
                                                                                        <?php echo ($rrow['user_ban_status'] == '0') ?
                                                                                            'This will disable the user account. Do you want to continue?' :
                                                                                            'This will enable the user account. Do you want to continue?'; ?>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer"
                                                                            style="justify-content: center;">
                                                                            <button type="button" class="btn btn-info"
                                                                                data-dismiss="modal">Close</button>
                                                                            <button type="submit" class="btn btn-primary"
                                                                                name="toggle_status">
                                                                                <?php echo ($rrow['user_ban_status'] == '0') ? 'Disable' : 'Enable'; ?>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>

                                                        <div class="modal fade" id="editProduct<?php echo $rrow['user_id']; ?>">
                                                            <div class="modal-dialog modal-lg">
                                                                <form method="POST">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h4 class="modal-title">Edit Staff Member</h4>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <div class="row mb-2">
                                                                                <div class="col-md-6 mt-2">
                                                                                    <input type="hidden" name="staffid"
                                                                                        value="<?php echo $rrow['user_id']; ?>">
                                                                                    <label for="startdate"
                                                                                        class="form-label">Full Name:</label>
                                                                                    <input class="form-control"
                                                                                        required="required" name="fullname"
                                                                                        type="text" placeholder="Full Name"
                                                                                        value="<?php echo $rrow['user_name']; ?>">
                                                                                </div>
                                                                                <div class="col-md-6 mt-2">
                                                                                    <label for="startdate"
                                                                                        class="form-label">Designation:</label>
                                                                                    <input class="form-control"
                                                                                        required="required" name="designation"
                                                                                        type="text" placeholder="Designation"
                                                                                        value="<?php echo $rrow['user_desig']; ?>">
                                                                                </div>
                                                                                <div class="col-md-12 mt-2">
                                                                                    <label for="startdate"
                                                                                        class="form-label">Email:</label>
                                                                                    <input class="form-control"
                                                                                        required="required" name="email"
                                                                                        type="email" placeholder="Email Address"
                                                                                        value="<?php echo $rrow['user_email']; ?>">
                                                                                </div>
                                                                                <div class="col-md-6 mt-2">
                                                                                    <label for="startdate"
                                                                                        class="form-label">Username:</label>
                                                                                    <input class="form-control"
                                                                                        required="required" name="username"
                                                                                        type="text" placeholder="Username"
                                                                                        value="<?php echo $rrow['user_login']; ?>">
                                                                                </div>
                                                                                <div class="col-md-6 mt-2">
                                                                                    <label for="startdate"
                                                                                        class="form-label">Password:</label>
                                                                                    <input class="form-control"
                                                                                        required="required" name="pass"
                                                                                        type="text" placeholder="Password"
                                                                                        value="<?php echo $rrow['user_password']; ?>">
                                                                                </div>
                                                                                <div class="col-md-6 mt-2">
                                                                                    <label class="form-label">Login Type</label>
                                                                                    <select class="form-control select"
                                                                                        required="required" name="usertype">
                                                                                        <option value="" selected disabled>
                                                                                            Select User Type</option>
                                                                                        <option value="1" <?php if ($rrow['user_type'] == '1') {
                                                                                            echo "selected";
                                                                                        } ?>>Administrator
                                                                                        </option>
                                                                                        <option value="2" <?php if ($rrow['user_type'] == '2') {
                                                                                            echo "selected";
                                                                                        } ?>>Manager</option>
                                                                                        <option value="3" <?php if ($rrow['user_type'] == '3') {
                                                                                            echo "selected";
                                                                                        } ?>>Employee</option>
                                                                                        <option value="4" <?php if ($rrow['user_type'] == '4') {
                                                                                            echo "selected";
                                                                                        } ?>>Internee</option>
                                                                                        <option value="5" <?php if ($rrow['user_type'] == '5') {
                                                                                            echo "selected";
                                                                                        } ?>>Restricted</option>
                                                                                    </select>
                                                                                </div>
                                                                                <div class="col-md-6 mt-2">
                                                                                    <label class="form-label">Secured Area
                                                                                        Access</label>
                                                                                    <select class="form-control select"
                                                                                        name="has_secured_access">
                                                                                        <option value="0" <?php echo ($rrow['has_secured_access'] == 0) ? 'selected="selected"' : ''; ?>>No
                                                                                            Access</option>
                                                                                        <option value="1" <?php echo ($rrow['has_secured_access'] == 1) ? 'selected="selected"' : ''; ?>>Has
                                                                                            Access</option>
                                                                                    </select>
                                                                                </div>
                                                                                <?php $__row_country = strtoupper(trim((string) ($rrow['user_country'] ?? 'PK'))); ?>
                                                                                <div class="col-md-6 mt-2">
                                                                                    <label class="form-label">Country</label>
                                                                                    <select class="form-control select"
                                                                                        required="required" name="user_country">
                                                                                        <option value="PK" <?php echo ($__row_country === 'PK') ? 'selected="selected"' : ''; ?>>Pakistan (PK)</option>
                                                                                        <option value="US" <?php echo ($__row_country === 'US') ? 'selected="selected"' : ''; ?>>United States (US)</option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-info"
                                                                                data-dismiss="modal">Close</button>
                                                                            <button type="submit" class="btn btn-primary"
                                                                                name="update_product">Update Staff</button>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>

                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="dataTable-bottom">
                                            <?php if (ceil($total_pages / $num_results_on_page) > 0): ?>
                                                <ul class="news">
                                                    <?php if ($page > 1): ?>
                                                        <li class="prev"><a href="users.php?page=<?php echo $page - 1 ?>">Prev</a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($page > 3): ?>
                                                        <li class="start"><a href="users.php?page=1">1</a></li>
                                                        <li class="dots">...</li>
                                                    <?php endif; ?>
                                                    <?php if ($page - 2 > 0): ?>
                                                        <li class="page"><a
                                                                href="users.php?page=<?php echo $page - 2 ?>"><?php echo $page - 2 ?></a>
                                                        </li><?php endif; ?>
                                                    <?php if ($page - 1 > 0): ?>
                                                        <li class="page"><a
                                                                href="users.php?page=<?php echo $page - 1 ?>"><?php echo $page - 1 ?></a>
                                                        </li><?php endif; ?>
                                                    <li class="currentpage"><a
                                                            href="users.php?page=<?php echo $page ?>"><?php echo $page ?></a>
                                                    </li>
                                                    <?php if ($page + 1 < ceil($total_pages / $num_results_on_page) + 1): ?>
                                                        <li class="page"><a
                                                                href="users.php?page=<?php echo $page + 1 ?>"><?php echo $page + 1 ?></a>
                                                        </li><?php endif; ?>
                                                    <?php if ($page + 2 < ceil($total_pages / $num_results_on_page) + 1): ?>
                                                        <li class="page"><a
                                                                href="users.php?page=<?php echo $page + 2 ?>"><?php echo $page + 2 ?></a>
                                                        </li><?php endif; ?>
                                                    <?php if ($page < ceil($total_pages / $num_results_on_page) - 2): ?>
                                                        <li class="dots">...</li>
                                                        <li class="end"><a
                                                                href="users.php?page=<?php echo ceil($total_pages / $num_results_on_page) ?>"><?php echo ceil($total_pages / $num_results_on_page) ?></a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($page < ceil($total_pages / $num_results_on_page)): ?>
                                                        <li class="next"><a href="users.php?page=<?php echo $page + 1 ?>">Next</a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="addProduct">
                        <div class="modal-dialog modal-lg">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h4 class="modal-title">Add New Staff Member</h4>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-2">
                                            <div class="col-md-6 mt-2">
                                                <label for="startdate" class="form-label">Full Name:</label>
                                                <input class="form-control" required="required" name="fullname" type="text"
                                                    placeholder="Full Name">
                                            </div>
                                            <div class="col-md-6 mt-2">
                                                <label for="startdate" class="form-label">Designation:</label>
                                                <input class="form-control" required="required" name="designation"
                                                    type="text" placeholder="Designation">
                                            </div>
                                            <div class="col-md-12 mt-2">
                                                <label for="startdate" class="form-label">Email:</label>
                                                <input class="form-control" required="required" name="email" type="email"
                                                    placeholder="Email Address">
                                            </div>
                                            <div class="col-md-6 mt-2">
                                                <label for="startdate" class="form-label">Username:</label>
                                                <input class="form-control" required="required" name="username" type="text"
                                                    placeholder="Username">
                                            </div>
                                            <div class="col-md-6 mt-2">
                                                <label for="startdate" class="form-label">Password:</label>
                                                <input class="form-control" required="required" name="pass" type="password"
                                                    placeholder="Password">
                                            </div>
                                            <div class="col-md-6 mt-2">
                                                <label class="form-label">Secured Area Access</label>
                                                <select class="form-control select" name="has_secured_access">
                                                    <option value="0" selected>No Access</option>
                                                    <option value="1">Has Access</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mt-2">
                                                <label class="form-label">Login Type</label>
                                                <select class="form-control select" required="required" name="usertype">
                                                    <option value="" selected disabled>Select User Type</option>
                                                    <option value="1">Administrator</option>
                                                    <option value="2">Manager</option>
                                                    <option value="3">Employee</option>
                                                    <option value="4">Internee</option>
                                                    <option value="5">Restricted</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mt-2">
                                                <label class="form-label">Country</label>
                                                <select class="form-control select" required="required" name="user_country">
                                                    <option value="PK" selected>Pakistan (PK)</option>
                                                    <option value="US">United States (US)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mt-2">
                                                <label for="pro_image" class="form-label">Profile Image</label>
                                                <div class="choose-file ">
                                                    <label for="pro_image" class="form-label" style="width: 100%;">
                                                        <input type="file" class="form-control" name="post_image"
                                                            id="pro_image" data-filename="pro_image_create">
                                                        <img id="image" class="mt-3" style="width:25%;">
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-primary" name="add_new">Add New</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include "includes/footer.php"; ?>
        <?php include "includes/scripts.php"; ?>
        <style>
            .news {
                list-style-type: none;
                padding: 10px 0;
                display: inline-flex;
                justify-content: space-between;
                box-sizing: border-box;
            }

            .news li {
                box-sizing: border-box;
                padding-right: 10px;
            }

            .news li a {
                box-sizing: border-box;
                background-color: #e2e6e6;
                padding: 8px;
                text-decoration: none;
                font-size: 12px;
                font-weight: bold;
                color: #616872;
                border-radius: 4px;
            } 

            .news li a:hover {
                background-color: #d4dada;
            }

            .news .next a,
            .news .prev a {
                text-transform: uppercase;
                font-size: 12px;
            }

            .news .currentpage a {
                background-color: #518acb;
                color: #fff;
            }

            .news .currentpage a:hover {
                background-color: #518acb;
            }
        </style>
    </body>

    </html>
    <?php
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        try { @$stmt->close(); } catch (\Throwable $e) { /* already closed */ }
    }
}
?>