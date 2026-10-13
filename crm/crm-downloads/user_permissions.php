<?php
include "includes/auth.php";
include "includes/db.php";

if ($acc_add_edit_staff != "Yes") {
    header("Location: index.php");
    exit();
}

$user_id = intval($_GET['user_id']);
$user_query = mysqli_query($con, "SELECT * FROM users WHERE user_id='$user_id'");
$user = mysqli_fetch_assoc($user_query);

if (!$user)
    die("User not found");

// Get permissions
$permissions = [
    'add_new_client' => 'No',
    'view_clients_list' => 'No',
    'client_detail_area' => 'No',
    'client_billing_area' => 'No',
    'delete_client' => 'No',
    'view_products' => 'No',
    'add_edit_products' => 'No',
    'delete_products' => 'No',
    'view_staff' => 'No',
    'add_edit_staff' => 'No',
    'delete_staff' => 'No',
    'view_entity_form' => 'No',
    'add_edit_form' => 'No',
    'delete_form' => 'No',
    'view_product_cycle' => 'No',
    'add_edit_cycle' => 'No',
    'delete_cycle' => 'No',
    'invoice_and_billing' => 'No',
    'estimates_and_qutations' => 'No',
    'bank_card_area' => 'No',
    'client_active' => 'No',
    'view_all_projects' => 'No',
    'assign_projects' => 'No',
    'edit_project' => 'No',
    'delete_project' => 'No',
    'mark_project_completed' => 'No',
    'create_invoice' => 'No',
    'view_invoices' => 'No',
    'reopen_project' => 'No',
    'view_personal' => 'No',
    'view_company' => 'No',
    // Invoice field-level edit permissions
    'inv_edit_due' => 'No',
    'inv_edit_service' => 'No',
    'inv_edit_service_desc' => 'No',
    'inv_edit_service_amount' => 'No',
    'inv_edit_discount' => 'No',
    'inv_edit_paid_amount' => 'No',
    'inv_edit_service_provider' => 'No',
    'inv_edit_paid_date' => 'No',
];

// Check for custom permissions first
$perm_query = mysqli_query($con, "SELECT * FROM user_permissions WHERE user_id='$user_id'");
if (mysqli_num_rows($perm_query) > 0) {
    $custom_perms = mysqli_fetch_assoc($perm_query);
    unset($custom_perms['user_id']);
    $permissions = array_merge($permissions, $custom_perms);
}
// If no custom permissions, load role defaults
else {
    $default_query = mysqli_query($con, "SELECT * FROM dashboard_access WHERE user_type='{$user['user_type']}'");
    if (mysqli_num_rows($default_query) > 0) {
        $default_perms = mysqli_fetch_assoc($default_query);
        unset($default_perms['access_id'], $default_perms['user_type']);
        $permissions = array_merge($permissions, $default_perms);
    }
}

function refresh_user_permissions($user_id)
{
    global $con;

    // Fetch updated permissions from the database
    $perm_query = mysqli_query($con, "SELECT * FROM user_permissions WHERE user_id='$user_id'");
    if (mysqli_num_rows($perm_query) > 0) {
        $permissions = mysqli_fetch_assoc($perm_query);
        unset($permissions['user_id']);
    } else {
        $permissions = [];
    }

    // Update session permissions
    $_SESSION = array_merge($_SESSION, $permissions);
}

if (isset($_POST['save_permissions'])) {
    $errors = [];
    $user_check = mysqli_query($con, "SELECT * FROM users WHERE user_id='$user_id'");
    if (mysqli_num_rows($user_check) == 0)
        $errors[] = "User does not exist";

    if (empty($errors)) {
        // Prepare all permissions with default 'No'
        $update_data = [];
        foreach ($permissions as $key => $value) {
            $update_data[$key] = 'No';
        }

        // Update with submitted values
        foreach ($_POST as $key => $value) {
            if (array_key_exists($key, $permissions)) {
                $update_data[$key] = 'Yes';
            }
        }

        // Build SQL
        $set_values = [];
        foreach ($update_data as $field => $value) {
            $set_values[] = "`$field` = '$value'";
        }
        $set_sql = implode(", ", $set_values);

        $check = mysqli_query($con, "SELECT * FROM user_permissions WHERE user_id='$user_id'");
        if (mysqli_num_rows($check) > 0) {
            $query = "UPDATE user_permissions SET $set_sql WHERE user_id='$user_id'";
        } else {
            $query = "INSERT INTO user_permissions SET user_id='$user_id', $set_sql";
        }

        if (mysqli_query($con, $query)) {
            $success_msg = "<div class='alert alert-success'>Permissions updated successfully!</div>";

            // Refresh permissions for this user
            refresh_user_permissions($user_id);

            // If editing own permissions, refresh current session
            if ($_SESSION['user_id'] == $user_id) {
                refresh_user_permissions($user_id);
            }

            // Reload permissions display
            $perm_query = mysqli_query($con, "SELECT * FROM user_permissions WHERE user_id='$user_id'");
            $permissions = mysqli_fetch_assoc($perm_query);
            unset($permissions['user_id']);
        } else {
            $error_msg = "<div class='alert alert-danger'>Error: " . mysqli_error($con) . "</div>";
        }
    } else {
        $error_msg = "<div class='alert alert-danger'>" . implode("<br>", $errors) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>User Permissions - Syed Professional Services</title>
    <?php include "includes/links.php"; ?>
    <style>
        body {
            background-color: #f4f6f9;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #343a40;
        }

        .permission-section {
            background: #ffffff;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 35px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            animation: fadeIn 0.8s ease-out;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .permission-section:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        }

        .permission-section h5 {
            border-bottom: 3px solid #6FD943;
            padding-bottom: 14px;
            margin-bottom: 25px;
            color: #2c3e50;
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 0.6px;
            transition: color 0.3s ease;
        }

        .permission-section h5:hover {
            color: #6FD943;
        }

        .permission-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            animation: fadeInUp 0.7s ease-out;
        }

        .permission-table th {
            font-weight: 700;
            background-color: #6FD943 !important;
            color: #ffffff;
            padding: 16px 20px;
            text-align: left;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .permission-table td {
            vertical-align: middle;
            padding: 16px 20px;
            border-bottom: 1px solid #e3e6f0;
            border: none;
            transition: background-color 0.3s ease;
        }

        .permission-table tr:hover td {
            background-color: #6FD943;
        }

        .checkbox-lg {
            transform: scale(1.5);
            margin: 0 auto;
            display: block;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .checkbox-lg:hover {
            transform: scale(1.7);
            box-shadow: 0 0 8px rgba(111, 217, 67, 0.5);
        }

        .permission-header {
            background: linear-gradient(120deg, #6FD943, #4bb543);
            color: #ffffff;
            padding: 20px 28px;
            border-radius: 16px 16px 0 0;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            animation: slideDown 0.8s ease-out;
        }

        .user-info-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 30px;
            border-left: 6px solid #6FD943;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
            transition: box-shadow 0.3s ease, transform 0.3s ease;
            animation: fadeIn 0.9s ease-out;
        }

        .user-info-card:hover {
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-5px);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                            <div class="page-header-title">
                                <h4 class="m-b-10">Custom Permissions for <?= htmlspecialchars($user['user_name']) ?>
                                </h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="users.php">Staff</a></li>
                                <li class="breadcrumb-item">Permissions</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-12">
                    <?php if (isset($success_msg))
                        echo $success_msg; ?>
                    <?php if (isset($error_msg))
                        echo $error_msg; ?>

                    <div class="user-info-card">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>User ID:</strong> <?= $user['user_id'] ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Email:</strong> <?= htmlspecialchars($user['user_email']) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Position:</strong> <?= htmlspecialchars($user['user_desig']) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Status:</strong>
                                <span class="badge <?= $user['user_ban_status'] == 0 ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $user['user_ban_status'] == 0 ? 'Active' : 'Banned' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <form method="POST">
                                <div class="permission-section">
                                    <h5>Client Management</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered permission-table">
                                            <tbody>
                                                <tr>
                                                    <td width="80%"><b>View Personal Detail</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="view_personal"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['view_personal'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>View Company Detail</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="view_company"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['view_company'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td width="80%"><b>Add New Client</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="add_new_client"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['add_new_client'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>View Clients List</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="view_clients_list"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['view_clients_list'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Access Client Detail Area</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="client_detail_area"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['client_detail_area'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Access Client Billing Area</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="client_billing_area"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['client_billing_area'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Delete Clients</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="delete_client"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['delete_client'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Activate/Deactivate Clients</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="client_active"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['client_active'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="permission-section">
                                    <h5>Service Management</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered permission-table">
                                            <tbody>
                                                <tr>
                                                    <td width="80%"><b>View Service</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="view_products"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['view_products'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Add/Edit Products</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="add_edit_products"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['add_edit_products'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Delete Products</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="delete_products"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['delete_products'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <!-- <tr>
                                                    <td><b>View Product Cycles</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="view_product_cycle"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['view_product_cycle'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Add/Edit Product Cycles</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="add_edit_cycle"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['add_edit_cycle'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Delete Product Cycles</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="delete_cycle"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['delete_cycle'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr> -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="permission-section">
                                    <h5>Staff Management</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered permission-table">
                                            <tbody>
                                                <tr>
                                                    <td width="80%"><b>View Staff</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="view_staff"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['view_staff'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Add/Edit Staff</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="add_edit_staff"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['add_edit_staff'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Delete Staff</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="delete_staff"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['delete_staff'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>



                                <div class="permission-section">
                                    <h5>Financial Operations</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered permission-table">
                                            <tbody>
                                                <tr>
                                                    <td width="80%"><b>Invoice & Billing</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="invoice_and_billing"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['invoice_and_billing'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Estimates & Quotations</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="estimates_and_qutations"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['estimates_and_qutations'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Bank/Card Area Access</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="bank_card_area"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['bank_card_area'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="permission-section">
                                    <h5>Invoice Edit Access</h5>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-bordered permission-table">
                                            <tbody>
                                                <?php
                                                $inv_edit_fields = [
                                                    'inv_edit_due' => 'Due Date',
                                                    'inv_edit_service' => 'Service (add/remove &amp; name)',
                                                    'inv_edit_service_desc' => 'Service Description',
                                                    'inv_edit_service_amount' => 'Service Amount (price)',
                                                    'inv_edit_discount' => 'Discount',
                                                    'inv_edit_paid_amount' => 'Paid Amount',
                                                    'inv_edit_service_provider' => 'Service Provider',
                                                    'inv_edit_paid_date' => 'Paid Date',
                                                ];
                                                foreach ($inv_edit_fields as $fkey => $flabel) { ?>
                                                    <tr>
                                                        <td width="80%"><b><?= $flabel ?></b></td>
                                                        <td class="text-center">
                                                            <input type="checkbox" name="<?= $fkey ?>"
                                                                class="form-check-input checkbox-lg"
                                                                <?= ($permissions[$fkey] ?? 'No') == 'Yes' ? 'checked' : '' ?>>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="permission-section">
                                    <h5>Form Management</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered permission-table">
                                            <tbody>
                                                <tr>
                                                    <td width="80%"><b>View Entity Forms</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="view_entity_form"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['view_entity_form'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Add/Edit Entity Forms</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="add_edit_form"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['add_edit_form'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Delete Entity Forms</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="delete_form"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['delete_form'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="permission-section">
                                    <h5>Projects Management</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered permission-table">
                                            <tbody>
                                                <tr>
                                                    <td width="80%"><b>View All Staff Projects</b>
                                                        
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="view_all_projects"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['view_all_projects'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td width="80%"><b>Assign Projects</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="assign_projects"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['assign_projects'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Edit Projects</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="edit_project"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['edit_project'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Delete Projects</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="delete_project"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['delete_project'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Mark Project as Completed</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="mark_project_completed"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['mark_project_completed'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Create Invoice</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="create_invoice"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['create_invoice'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>View Invoices</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="view_invoices"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['view_invoices'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><b>Reopen Project</b></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="reopen_project"
                                                            class="form-check-input checkbox-lg"
                                                            <?= $permissions['reopen_project'] == 'Yes' ? 'checked' : '' ?>>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="text-center mt-4">
                                    <button type="submit" name="save_permissions" class="btn btn-primary btn-lg">
                                        <i class="ti ti-check"></i> Save Permissions
                                    </button>
                                    <a href="users.php" class="btn btn-secondary btn-lg">
                                        <i class="ti ti-arrow-back"></i> Back to Staff
                                    </a>
                                    <button type="button" class="btn btn-warning btn-lg" id="selectAllBtn">
                                        <i class="ti ti-checkbox"></i> Select All Permissions
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <script>
        // Select All functionality
        document.getElementById('selectAllBtn').addEventListener('click', function () {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            const isAnyUnchecked = [...checkboxes].some(checkbox => !checkbox.checked);

            checkboxes.forEach(checkbox => {
                checkbox.checked = isAnyUnchecked;
            });

            this.innerHTML = isAnyUnchecked
                ? '<i class="ti ti-checkbox"></i> Deselect All'
                : '<i class="ti ti-checkbox"></i> Select All';
        });
    </script>
</body>

</html>