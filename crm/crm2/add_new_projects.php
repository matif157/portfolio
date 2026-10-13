<?php
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/activity_log.php';
include 'includes/customizer.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$current_user_id = $_SESSION['user_id'];
$current_user_type = $_SESSION['user_type'] ?? null;

// Check assign_projects permission 
$assign_projects_perm = 'No';
$perm_query = mysqli_query($con, "SELECT assign_projects FROM user_permissions WHERE user_id = '$current_user_id'");
if ($perm_row = mysqli_fetch_assoc($perm_query)) {
    $assign_projects_perm = $perm_row['assign_projects'];
}

if (!$current_user_id || !$current_user_type) {
    header("Location: login.php");
    exit();
}

$error = '';
$msg = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['form_type'] == 'project') {
        $product_id = mysqli_real_escape_string($con, $_POST['product_id']);
        $start_date = mysqli_real_escape_string($con, $_POST['start_date']);
        $exp_date = mysqli_real_escape_string($con, $_POST['exp_date']);
        $project_title = mysqli_real_escape_string($con, $_POST['project_title']);
        $project_description = mysqli_real_escape_string($con, $_POST['project_description']);
        $project_status = 'open';

        // Only allow assigning to others if user has assign_projects permission or is user_type == 1
        if (($current_user_type == 1 || $assign_projects_perm == 'Yes') && isset($_POST['assign_to']) && !empty($_POST['assign_to'])) {
            $assign_to = mysqli_real_escape_string($con, $_POST['assign_to']);
        } else {
            $assign_to = $current_user_id; // Default to self
        }

        // Determine if bookkeeping (allows multi-client)
        $prodNameQ = mysqli_query($con, "SELECT membership_name FROM memberships WHERE membership_id = '$product_id' LIMIT 1");
        $prodNameRow = $prodNameQ ? mysqli_fetch_assoc($prodNameQ) : null;
        $prodName = $prodNameRow['membership_name'] ?? '';
        $prodNameNorm = strtolower(preg_replace('/\s+/', '', $prodName));
        $isBookkeeping = strpos($prodNameNorm, 'bookkeeping') !== false;

        // Normalize client_id input — may be array (bookkeeping multi) or scalar
        $client_ids = [];
        if (isset($_POST['client_id'])) {
            if (is_array($_POST['client_id'])) {
                foreach ($_POST['client_id'] as $cid) {
                    $cid = trim($cid);
                    if ($cid !== '') $client_ids[] = $cid;
                }
            } else {
                $cid = trim($_POST['client_id']);
                if ($cid !== '') $client_ids[] = $cid;
            }
        }

        if (empty($client_ids)) {
            $error = "Please select at least one client.";
        } else {
            $creatorName = '';
            if ($isBookkeeping) {
                $creatorQ = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '$current_user_id' LIMIT 1");
                $creatorRow = $creatorQ ? mysqli_fetch_assoc($creatorQ) : null;
                $creatorName = $creatorRow['user_name'] ?? '';
            }

            $created_ids = [];
            $had_error = false;
            foreach ($client_ids as $cidRaw) {
                $client_id = mysqli_real_escape_string($con, $cidRaw);

                $query = "INSERT INTO projects (mem_id, prod_id, start_date, exp_date, user_id, assign_to, project_title, project_description, project_status)
                          VALUES ('$client_id', '$product_id', '$start_date', '$exp_date', '$current_user_id', '$assign_to', '$project_title', '$project_description', '$project_status')";

                if (mysqli_query($con, $query)) {
                    $new_project_id = mysqli_insert_id($con);
                    $created_ids[] = $new_project_id;
                    logActivity($con, $current_user_id, 'CREATE', 'project', $new_project_id, "Created new project #$new_project_id: \"$project_title\" for client ID: $client_id");

                    // Persist custom field values (EAV) for this project.
                    $cuzCustom = function_exists('customizer_custom_fields') ? customizer_custom_fields($con, 'projects') : array();
                    if (!empty($cuzCustom)) {
                        $cuzVals = customizer_extract_custom_values($cuzCustom);
                        customizer_save_record_values($con, 'projects', $new_project_id, $cuzVals, $current_user_id, ($_SESSION['fullname'] ?? ''));
                    }

                    if ($isBookkeeping) {
                        $bkName = mysqli_real_escape_string($con, $project_title);
                        $bkCreator = mysqli_real_escape_string($con, $creatorName);
                        @mysqli_query($con, "INSERT INTO bk_projects (name, corp_type, year_end, due_date, related, created_by, row_class, john_note, status_text, date_note) VALUES ('$bkName', '', '', '', '', '$bkCreator', 'row-green', '', '', '')");
                    }
                } else {
                    $had_error = true;
                    $error = "Error creating project: " . mysqli_error($con);
                    break;
                }
            }

            if (!$had_error && !empty($created_ids)) {
                $first_id = $created_ids[0];
                header("Location: show_projects.php?status=created&project_id=$first_id");
                exit();
            }
        }
    }

    if ($_POST['form_type'] == 'client') {
        $form_variant = isset($_POST['form_variant']) ? $_POST['form_variant'] : 'full';
        $specialClient = isset($_POST['specialClient']) ? mysqli_real_escape_string($con, $_POST['specialClient']) : 0;
        $ach_payer = isset($_POST['ach_payer']) ? mysqli_real_escape_string($con, $_POST['ach_payer']) : '0';
        $membertype = mysqli_real_escape_string($con, $_POST['membertype']);
        // Set category to empty string for membertype 1 or 8, otherwise use custom category if "Other" is selected
        $category = ($membertype == 1 || $membertype == 8) ? '' : mysqli_real_escape_string($con, $_POST['category'] === 'Other' ? $_POST['custom_category'] : $_POST['category']);
        $name = mysqli_real_escape_string($con, $_POST['name']);
        $mname = mysqli_real_escape_string($con, $_POST['mname']);
        $lname = mysqli_real_escape_string($con, $_POST['lname']);
        $file = mysqli_real_escape_string($con, $_POST['file']);
        $ssn = mysqli_real_escape_string($con, $_POST['ssn']);
        $dob = mysqli_real_escape_string($con, $_POST['dob']);
        $phonecell = mysqli_real_escape_string($con, $_POST['phonecell']);
        $pemail = mysqli_real_escape_string($con, $_POST['pemail']);
        $hstate = mysqli_real_escape_string($con, $_POST['hstate']);
        $hcity = mysqli_real_escape_string($con, $_POST['hcity']);
        $hstreet = mysqli_real_escape_string($con, $_POST['hstreet']);
        $hzip = mysqli_real_escape_string($con, $_POST['hzip']);
        $hcounty = mysqli_real_escape_string($con, $_POST['hcounty']);

        // Fields only in full form
        $dba = ($form_variant == 'full' && isset($_POST['dba'])) ? mysqli_real_escape_string($con, $_POST['dba']) : '';
        $ein = ($form_variant == 'full' && isset($_POST['ein'])) ? mysqli_real_escape_string($con, $_POST['ein']) : '';
        $tfnumber = ($form_variant == 'full' && isset($_POST['tfnumber'])) ? mysqli_real_escape_string($con, $_POST['tfnumber']) : '';
        $phonework = ($form_variant == 'full' && isset($_POST['phonework'])) ? mysqli_real_escape_string($con, $_POST['phonework']) : '';
        $phoneother = ($form_variant == 'full' && isset($_POST['phoneother'])) ? mysqli_real_escape_string($con, $_POST['phoneother']) : '';
        $phoneoverseas = ($form_variant == 'full' && isset($_POST['phoneoverseas'])) ? mysqli_real_escape_string($con, $_POST['phoneoverseas']) : '';
        $fax = ($form_variant == 'full' && isset($_POST['fax'])) ? mysqli_real_escape_string($con, $_POST['fax']) : '';
        $semail = ($form_variant == 'full' && isset($_POST['semail'])) ? mysqli_real_escape_string($con, $_POST['semail']) : '';
        $bname = ($form_variant == 'full' && isset($_POST['bname'])) ? mysqli_real_escape_string($con, $_POST['bname']) : '';
        $bstate = ($form_variant == 'full' && isset($_POST['bstate'])) ? mysqli_real_escape_string($con, $_POST['bstate']) : '';
        $bcity = ($form_variant == 'full' && isset($_POST['bcity'])) ? mysqli_real_escape_string($con, $_POST['bcity']) : '';
        $bstreet = ($form_variant == 'full' && isset($_POST['bstreet'])) ? mysqli_real_escape_string($con, $_POST['bstreet']) : '';
        $bzip = ($form_variant == 'full' && isset($_POST['bzip'])) ? mysqli_real_escape_string($con, $_POST['bzip']) : '';
        $bcounty = ($form_variant == 'full' && isset($_POST['bcounty'])) ? mysqli_real_escape_string($con, $_POST['bcounty']) : '';
        $client_since = ($form_variant == 'full' && !empty($_POST['client_since'])) ? mysqli_real_escape_string($con, $_POST['client_since']) : date("Y-m-d");
        $incorp_date = ($form_variant == 'full' && !empty($_POST['incorp_date'])) ? mysqli_real_escape_string($con, $_POST['incorp_date']) : date("Y-m-d");
        $bus_start_date = ($form_variant == 'full' && !empty($_POST['bus_start_date'])) ? mysqli_real_escape_string($con, $_POST['bus_start_date']) : date("Y-m-d");

        // Handle file upload
        $pic = '';
        if (!empty($_FILES['post_image']['name'])) {
            $pic_name = $_FILES['post_image']['name'];
            $pic_temp = $_FILES['post_image']['tmp_name'];
            $pic_new = rand(100, 1000) . '-' . $pic_name;
            $pic_store = "images/clients/" . $pic_new;

            if (move_uploaded_file($pic_temp, $pic_store)) {
                $pic = $pic_new;
            }
        }

        $currentdate = date("Y-m-d");

        $queryy = "INSERT INTO members (
    file, name, dba, ein, ss, tf, dob, phone, cell_ph, other_ph, overseas_ph,
    prm_email, sec_email, fax, mem_pic, type, added_by, mname, lname, bus_name,
    hzip, hstate, hcity, hstreet, hcounty, city, state, address, zip, bcounty,
    occupation, idcard, pmb, hapartment, datelast, dateupdated,
    duedate, closingm, incorp_date, incorpstate, user_id, business_address, client_since, specialClient, bus_start_date, category, ach_payer
) VALUES (
    '$file', '$name', '$dba', '$ein', '$ssn', '$tfnumber', '$dob', '$phonework',
    '$phonecell', '$phoneother', '$phoneoverseas', '$pemail', '$semail', '$fax',
    '$pic', '$membertype', '1', '$mname', '$lname', '$bname', '$hzip', '$hstate',
    '$hcity', '$hstreet', '$hcounty', '$bcity', '$bstate', '$bstreet', '$bzip', '$bcounty',
    '', '', '', '', '$currentdate', '$currentdate', '', '', '$incorp_date', '', 0, '', '$client_since', '$specialClient', '$bus_start_date', '$category', '$ach_payer'
);";

        if (mysqli_query($con, $queryy)) {
            $new_client_id = mysqli_insert_id($con);
            $client_full_name = trim("$name $mname $lname");
            logActivity($con, $current_user_id, 'CREATE', 'member', $new_client_id, "Added new client: $client_full_name (ID: $new_client_id)");
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=client&status=client_added&client_id=$new_client_id");
            exit();
        } else {
            $error = "<div class='alert alert-danger'>Error adding client: " . mysqli_error($con) . "</div>";
        }
    }
}

// Get data for selects
$assignable_staff = [];
if ($current_user_type == 1 || $assign_projects_perm == 'Yes') {
    $assignable_staff = mysqli_query($con, "SELECT user_id, user_name FROM users WHERE user_type > 1");
}
$clients = mysqli_query($con, "SELECT member_id, name, mname, lname, bus_name FROM members");
$products = mysqli_query($con, "SELECT membership_id, membership_name FROM memberships");
$member_types = mysqli_query($con, "SELECT * FROM member_type");

// Build the customizer override map for system fields (label / required / max).
$cuz_override_obj = array();
if (function_exists('customizer_fields_for_module')) {
    foreach (customizer_fields_for_module($con, 'projects') as $fkey => $fdef) {
        $cuz_override_obj[$fkey] = array(
            'label' => $fdef['label'],
            'req'   => (int) $fdef['is_required'] === 1,
            'max'   => (int) $fdef['max_length'],
        );
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Create New Project / Add Client</title>
    <?php include "includes/links.php"; ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .nav-buttons {
            margin-bottom: 20px;
        }

        .nav-buttons button {
            margin-right: 10px;
        }

        .hidden {
            display: none;
        }

        .select2-container {
            box-sizing: border-box;
        }

        .select2-dropdown {
            z-index: 10000;
        }

        .select2-container--default .select2-selection--single {
            height: calc(1.5em + 0.75rem + 2px);
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding-left: 0;
            color: #495057;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 100%;
        }

        .dash-content .select2-container {
            width: 100% !important;
        }

        .dash-content {
            position: relative;
        }

        .form-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .form-section h6 {
            margin-bottom: 15px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <?php include "includes/header.php"; ?>
    <?php include "includes/sidebar.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header py-3">
                <h4>Manage Projects & Clients</h4>
            </div>

            <?php if (isset($msg) && $msg != ''): ?>
                <?= $msg ?>
            <?php endif; ?>

            <?php if (isset($error) && $error != ''): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <div class="nav-buttons">
                <button id="showProjectForm" class="btn btn-primary">Create Project</button>
                <?php if ($acc_add_client == "Yes") { ?>
                    <button id="showClientForm" class="btn btn-secondary">Add Client</button>
                <?php } else { ?>
                    <span data-bs-toggle="tooltip" data-bs-placement="top" title="You do not have permission">
                        <button class="btn btn-secondary" disabled style="pointer-events: none;">Add Client</button>
                    </span>
                <?php } ?>
                <script>
                    document.addEventListener("DOMContentLoaded", function () {
                        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                            return new bootstrap.Tooltip(tooltipTriggerEl);
                        });
                    });
                </script>
            </div>

            <!-- Project Creation Form -->
            <div id="projectForm" class="card card-body">
                <form method="POST" action="">
                    <input type="hidden" name="form_type" value="project">

                    <div class="form-group">
                        <label>Product/Service</label>
                        <select name="product_id" id="product_id" class="form-control select2" required>
                            <option value="">Select Product</option>
                            <?php
                            mysqli_data_seek($products, 0);
                            while ($product = mysqli_fetch_assoc($products)):
                                $pn = strtolower(preg_replace('/\s+/', '', $product['membership_name']));
                                $isBk = strpos($pn, 'bookkeeping') !== false ? '1' : '0';
                            ?>
                                <option value="<?= $product['membership_id'] ?>" data-bookkeeping="<?= $isBk ?>"><?= $product['membership_name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Client <small id="client_multi_hint" class="text-muted">(you can select multiple clients)</small></label>
                        <select name="client_id[]" id="client_id" class="form-control select2" multiple required>
                            <?php
                            mysqli_data_seek($clients, 0);
                            while ($client = mysqli_fetch_assoc($clients)): ?>
                                <option value="<?= $client['member_id'] ?>">
                                    <?= 'Member ID: ' . $client['member_id'] . str_repeat(' ', 10) . 'Name: ' . $client['name'] . ' ' . $client['mname'] . ' ' . $client['lname'] . str_repeat(' ', 10) . 'Business: ' . $client['bus_name'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="exp_date" class="form-control" required>
                    </div>

                    <div class="form-group" data-cpc-field="project_title">
                        <label>Project Title</label>
                        <input type="text" name="project_title" class="form-control" required>
                    </div>

                    <div class="form-group" data-cpc-field="project_description">
                        <label>Project Description</label>
                        <textarea name="project_description" class="form-control" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Assign To</label>
                        <select name="assign_to" class="form-control select2" <?= ($current_user_type != 1 && $assign_projects_perm != 'Yes') ? 'disabled' : '' ?>>
                            <option value="<?= $current_user_id ?>" selected>
                                <?php
                                $result = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '$current_user_id'");
                                $current_user_name = ($row = mysqli_fetch_assoc($result)) ? $row['user_name'] : 'Myself';
                                echo $current_user_name;
                                ?>
                            </option>
                            <?php if ($current_user_type == 1 || $assign_projects_perm == 'Yes'): ?>
                                <?php while ($staff = mysqli_fetch_assoc($assignable_staff)): ?>
                                    <?php if ($staff['user_id'] != $current_user_id): ?>
                                        <option value="<?= $staff['user_id'] ?>"><?= $staff['user_name'] ?></option>
                                    <?php endif; ?>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <?php if ($current_user_type != 1 && $assign_projects_perm != 'Yes'): ?>
                            <input type="hidden" name="assign_to" value="<?= $current_user_id ?>">
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <?= function_exists('customizer_render_custom_fields_block') ? customizer_render_custom_fields_block($con, 'projects') : '' ?>
                    </div>

                    <button type="submit" class="btn btn-primary">Create Project</button>
                </form>
            </div>

            <!-- Add Client Form (Full) -->
            <div id="clientForm" class="card card-body hidden">
                <form method="POST" action="" enctype="multipart/form-data" id="fullForm">
                    <input type="hidden" name="form_type" value="client">
                    <input type="hidden" name="form_variant" value="full">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="membertype" class="form-label">Type</label><span
                                    class="text-danger">*</span>
                                <select class="form-control select2" required="required" id="membertype"
                                    name="membertype">
                                    <option value="" selected="selected">Select Type</option>
                                    <?php
                                    mysqli_data_seek($member_types, 0);
                                    while ($genetics = mysqli_fetch_assoc($member_types)): ?>
                                        <option value="<?= $genetics['type_id'] ?>">
                                            <?= $genetics['type_name'] ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="specialClient" class="form-label">Sympathetic Client</label><span
                                    class="text-danger">*</span>
                                <select class="form-control select2" required="required" name="specialClient"
                                    id="specialClient">
                                    <option value="" disabled selected>Select Yes or No</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ach_payer" class="form-label">ACH Payer</label><span
                                    class="text-danger">*</span>
                                <select class="form-control select2" required="required" name="ach_payer"
                                    id="ach_payer">
                                    <option value="" disabled selected>Select Yes or No</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6" id="category_container">
                            <div class="form-group">
                                <label for="category" class="form-label">Category</label><span
                                    class="text-danger">*</span>
                                <select class="form-control select2" required="required" name="category" id="category">
                                    <option value="" disabled selected>Select Category</option>
                                    <option value="Information Technology (IT)">Information Technology (IT)</option>
                                    <option value="Business & Management">Business & Management</option>
                                    <option value="Finance & Accounting">Finance & Accounting</option>
                                    <option value="Marketing & Advertising">Marketing & Advertising</option>
                                    <option value="Healthcare & Medical">Healthcare & Medical</option>
                                    <option value="Education & Teaching">Education & Teaching</option>
                                    <option value="Engineering">Engineering</option>
                                    <option value="Law & Legal Services">Law & Legal Services</option>
                                    <option value="Psychology & Mental Health">Psychology & Mental Health</option>
                                    <option value="Arts & Design">Arts & Design</option>
                                    <option value="Fashion & Beauty">Fashion & Beauty</option>
                                    <option value="Writing & Content Creation">Writing & Content Creation</option>
                                    <option value="Media & Communication">Media & Communication</option>
                                    <option value="Science & Research">Science & Research</option>
                                    <option value="Sales & Retail">Sales & Retail</option>
                                    <option value="Real Estate">Real Estate</option>
                                    <option value="Construction & Architecture">Construction & Architecture</option>
                                    <option value="Travel & Hospitality">Travel & Hospitality</option>
                                    <option value="Food & Culinary Arts">Food & Culinary Arts</option>
                                    <option value="Social Work & Non-Profit">Social Work & Non-Profit</option>
                                    <option value="Fitness & Sports">Fitness & Sports</option>
                                    <option value="Human Resources">Human Resources</option>
                                    <option value="Customer Support & Services">Customer Support & Services</option>
                                    <option value="Manufacturing & Production">Manufacturing & Production</option>
                                    <option value="Logistics & Supply Chain">Logistics & Supply Chain</option>
                                    <option value="Government & Public Services">Government & Public Services</option>
                                    <option value="Agriculture & Environment">Agriculture & Environment</option>
                                    <option value="Automotive">Automotive</option>
                                    <option value="E-commerce">E-commerce</option>
                                    <option value="Gaming & Animation">Gaming & Animation</option>
                                    <option value="Telecommunications">Telecommunications</option>
                                    <option value="Event Management">Event Management</option>
                                    <option value="Entrepreneurship / Startups">Entrepreneurship / Startups</option>
                                    <option value="Religious / Spiritual Work">Religious / Spiritual Work</option>
                                    <option value="Other">Other (Please specify)</option>
                                </select>
                                <input type="text" class="form-control mt-2" name="custom_category" id="custom_category"
                                    placeholder="Specify category" style="display: none;">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="name" class="form-label">First Name</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" required name="name" type="text"
                                    placeholder="Enter First Name" id="name"
                                    oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="mname" class="form-label">Middle Name</label>
                                <input class="form-control" name="mname" type="text" placeholder="Enter Middle Name"
                                    id="mname" oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="lname" class="form-label">Last Name</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" required name="lname" type="text"
                                    placeholder="Enter Last Name" id="lname"
                                    oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                            </div>
                        </div>

                        <div class="col-md-12 form-section">
                            <h6>Personal Address:</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hstreet" class="form-label">Street</label><span
                                            class="text-danger">*</span>
                                        <input class="form-control" required name="hstreet" type="text"
                                            placeholder="Enter Street" id="hstreet">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hcity" class="form-label">City</label><span
                                            class="text-danger">*</span>
                                        <input class="form-control" required name="hcity" type="text"
                                            placeholder="Enter City" id="hcity">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hstate" class="form-label">State</label><span
                                            class="text-danger">*</span>
                                        <input class="form-control" required name="hstate" type="text"
                                            placeholder="Enter State" id="hstate">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hzip" class="form-label">Zip</label><span
                                            class="text-danger">*</span>
                                        <input class="form-control" required name="hzip" type="text"
                                            placeholder="Enter Zip" id="hzip">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hcounty" class="form-label">County</label>
                                        <input class="form-control" name="hcounty" type="text"
                                            placeholder="Enter County" id="hcounty">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="bname" class="form-label">Business Name</label>
                                <input class="form-control" name="bname" type="text" placeholder="Enter Business Name"
                                    id="bname">
                            </div>
                        </div>

                        <div class="col-md-12 form-section">
                            <h6>Business Address:</h6>
                            <label><input type="checkbox" id="sameAddressCheckbox"> Same as Personal Address</label>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="bstreet" class="form-label">Street</label>
                                        <input class="form-control" name="bstreet" type="text"
                                            placeholder="Enter Street" id="bstreet">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="bcity" class="form-label">City</label>
                                        <input class="form-control" name="bcity" type="text" placeholder="Enter City"
                                            id="bcity">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="bstate" class="form-label">State</label>
                                        <input class="form-control" name="bstate" type="text" placeholder="Enter State"
                                            id="bstate">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="bzip" class="form-label">Zip</label>
                                        <input class="form-control" name="bzip" type="text" placeholder="Enter Zip"
                                            id="bzip">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="bcounty" class="form-label">County</label>
                                        <input class="form-control" name="bcounty" type="text"
                                            placeholder="Enter County" id="bcounty">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="file" class="form-label">File</label>
                                    <input class="form-control" name="file" type="text" id="file"
                                        placeholder="Enter File No">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="ssn" class="form-label">SSN</label><span class="text-danger">*</span>
                                    <input class="form-control" required name="ssn" type="text" maxlength="11"
                                        placeholder="XXX-XX-XXXX" id="ssn">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="dba" class="form-label">DBA</label>
                                    <input class="form-control" name="dba" type="text" placeholder="DBA" id="dba">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="ein" class="form-label">EIN</label>
                                    <input class="form-control" name="ein" type="text" maxlength="10"
                                        placeholder="XX-XXXXXXX" id="ein">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="tfnumber" class="form-label">TF Number</label>
                                    <input class="form-control" name="tfnumber" type="tel" id="tfnumber"
                                        placeholder="TF Number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="dob" class="form-label">Date of Birth</label><span
                                        class="text-danger">*</span>
                                    <input class="form-control" required name="dob" type="date" id="dob"
                                        placeholder="Date of Birth">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phonework" class="form-label">Phone: Work</label>
                                    <input class="form-control" name="phonework" type="tel" id="phonework"
                                        placeholder="XXX-XXX-XXXX" maxlength="12">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phonecell" class="form-label">Phone: Cell</label><span
                                        class="text-danger">*</span>
                                    <input class="form-control" required name="phonecell" type="tel" id="phonecell"
                                        placeholder="XXX-XXX-XXXX" maxlength="12">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phoneother" class="form-label">Phone: Other</label>
                                    <input class="form-control" name="phoneother" type="tel" id="phoneother"
                                        placeholder="XXX-XXX-XXXX" maxlength="12">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phoneoverseas" class="form-label">Phone: Overseas</label>
                                    <input class="form-control" name="phoneoverseas" type="tel" id="phoneoverseas"
                                        placeholder="Phone: Overseas">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pemail" class="form-label">Primary Email</label><span
                                        class="text-danger">*</span>
                                    <input class="form-control" required name="pemail" type="email" id="pemail"
                                        placeholder="Primary Email">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="semail" class="form-label">Secondary Email</label>
                                    <input class="form-control" name="semail" type="email" id="semail"
                                        placeholder="Secondary Email">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="client_since" class="form-label">Client Since</label>
                                    <input class="form-control" name="client_since" type="date" id="client_since">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="incorp_date" class="form-label">Incorporation Date</label>
                                    <input class="form-control" name="incorp_date" type="date" id="incorp_date">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="bus_start_date" class="form-label">Business Start Date</label>
                                    <input class="form-control" name="bus_start_date" type="date" id="bus_start_date">
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="pro_image" class="form-label">Profile Image</label>
                                <div class="choose-file">
                                    <label for="pro_image" class="form-label" style="width: 100%;">
                                        <input type="file" class="form-control" name="post_image" id="pro_image"
                                            data-filename="pro_image_create">
                                        <img id="image" class="mt-3" style="width:25%;">
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" name="add_client" class="btn btn-primary">Add New Client</button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Add Client Form (Simplified) -->
                <form method="POST" action="" enctype="multipart/form-data" id="simplifiedForm" style="display: none;">
                    <input type="hidden" name="form_type" value="client">
                    <input type="hidden" name="form_variant" value="simplified">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="membertype_simple" class="form-label">Type</label><span
                                    class="text-danger">*</span>
                                <select class="form-control select2" required="required" id="membertype_simple"
                                    name="membertype">
                                    <option value="" selected="selected">Select Type</option>
                                    <?php
                                    mysqli_data_seek($member_types, 0);
                                    while ($genetics = mysqli_fetch_assoc($member_types)): ?>
                                        <option value="<?= $genetics['type_id'] ?>">
                                            <?= $genetics['type_name'] ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="specialClient_simple" class="form-label">Sympathetic Client</label><span
                                    class="text-danger">*</span>
                                <select class="form-control select2" required="required" name="specialClient"
                                    id="specialClient_simple">
                                    <option value="" disabled selected>Select Yes or No</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ach_payer_simple" class="form-label">ACH Payer</label><span
                                    class="text-danger">*</span>
                                <select class="form-control select2" required="required" name="ach_payer"
                                    id="ach_payer_simple">
                                    <option value="" disabled selected>Select Yes or No</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6" id="category_container_simple" style="display: none;">
                            <div class="form-group">
                                <label for="category_simple" class="form-label">Category</label><span
                                    class="text-danger">*</span>
                                <select class="form-control select2" name="category" id="category_simple">
                                    <option value="" disabled selected>Select Category</option>
                                    <option value="Information Technology (IT)">Information Technology (IT)</option>
                                    <option value="Business & Management">Business & Management</option>
                                    <option value="Finance & Accounting">Finance & Accounting</option>
                                    <option value="Marketing & Advertising">Marketing & Advertising</option>
                                    <option value="Healthcare & Medical">Healthcare & Medical</option>
                                    <option value="Education & Teaching">Education & Teaching</option>
                                    <option value="Engineering">Engineering</option>
                                    <option value="Law & Legal Services">Law & Legal Services</option>
                                    <option value="Psychology & Mental Health">Psychology & Mental Health</option>
                                    <option value="Arts & Design">Arts & Design</option>
                                    <option value="Fashion & Beauty">Fashion & Beauty</option>
                                    <option value="Writing & Content Creation">Writing & Content Creation</option>
                                    <option value="Media & Communication">Media & Communication</option>
                                    <option value="Science & Research">Science & Research</option>
                                    <option value="Sales & Retail">Sales & Retail</option>
                                    <option value="Real Estate">Real Estate</option>
                                    <option value="Construction & Architecture">Construction & Architecture</option>
                                    <option value="Travel & Hospitality">Travel & Hospitality</option>
                                    <option value="Food & Culinary Arts">Food & Culinary Arts</option>
                                    <option value="Social Work & Non-Profit">Social Work & Non-Profit</option>
                                    <option value="Fitness & Sports">Fitness & Sports</option>
                                    <option value="Human Resources">Human Resources</option>
                                    <option value="Customer Support & Services">Customer Support & Services</option>
                                    <option value="Manufacturing & Production">Manufacturing & Production</option>
                                    <option value="Logistics & Supply Chain">Logistics & Supply Chain</option>
                                    <option value="Government & Public Services">Government & Public Services</option>
                                    <option value="Agriculture & Environment">Agriculture & Environment</option>
                                    <option value="Automotive">Automotive</option>
                                    <option value="E-commerce">E-commerce</option>
                                    <option value="Gaming & Animation">Gaming & Animation</option>
                                    <option value="Telecommunications">Telecommunications</option>
                                    <option value="Event Management">Event Management</option>
                                    <option value="Entrepreneurship / Startups">Entrepreneurship / Startups</option>
                                    <option value="Religious / Spiritual Work">Religious / Spiritual Work</option>
                                    <option value="Other">Other (Please specify)</option>
                                </select>
                                <input type="text" class="form-control mt-2" name="custom_category"
                                    id="custom_category_simple" placeholder="Specify category" style="display: none;">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name_simple" class="form-label">First Name</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" required name="name" type="text"
                                    placeholder="Enter First Name" id="name_simple"
                                    oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="mname_simple" class="form-label">Middle Name</label>
                                <input class="form-control" name="mname" type="text" placeholder="Enter Middle Name"
                                    id="mname_simple" oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="lname_simple" class="form-label">Last Name</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" required name="lname" type="text"
                                    placeholder="Enter Last Name" id="lname_simple"
                                    oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                            </div>
                        </div>

                        <div class="col-md-12 form-section">
                            <h6>Personal Address:</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hstreet_simple" class="form-label">Street</label><span
                                            class="text-danger">*</span>
                                        <input class="form-control" required name="hstreet" type="text"
                                            placeholder="Enter Street" id="hstreet_simple">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hcity_simple" class="form-label">City</label><span
                                            class="text-danger">*</span>
                                        <input class="form-control" required name="hcity" type="text"
                                            placeholder="Enter City" id="hcity_simple">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hstate_simple" class="form-label">State</label><span
                                            class="text-danger">*</span>
                                        <input class="form-control" required name="hstate" type="text"
                                            placeholder="Enter State" id="hstate_simple">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hzip_simple" class="form-label">Zip</label><span
                                            class="text-danger">*</span>
                                        <input class="form-control" required name="hzip" type="text"
                                            placeholder="Enter Zip" id="hzip_simple">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hcounty_simple" class="form-label">County</label>
                                        <input class="form-control" name="hcounty" type="text"
                                            placeholder="Enter County" id="hcounty_simple">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="file_simple" class="form-label">File</label>
                                <input class="form-control" name="file" type="text" id="file_simple"
                                    placeholder="Enter File No">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ssn_simple" class="form-label">SSN</label><span class="text-danger">*</span>
                                <input class="form-control" required name="ssn" type="text" maxlength="11"
                                    placeholder="XXX-XX-XXXX" id="ssn_simple">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="dob_simple" class="form-label">Date of Birth</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" required name="dob" type="date" id="dob_simple"
                                    placeholder="Date of Birth">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phonecell_simple" class="form-label">Phone: Cell</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" required name="phonecell" type="tel" id="phonecell_simple"
                                    placeholder="XXX-XXX-XXXX" maxlength="12">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="pemail_simple" class="form-label">Primary Email</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" required name="pemail" type="email" id="pemail_simple"
                                    placeholder="Primary Email">
                            </div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="pro_image_simple" class="form-label">Profile Image</label>
                            <div class="choose-file">
                                <label for="pro_image_simple" class="form-label" style="width: 100%;">
                                    <input type="file" class="form-control" name="post_image" id="pro_image_simple"
                                        data-filename="pro_image_create_simple">
                                    <img id="image_simple" class="mt-3" style="width:25%;">
                                </label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" name="add_client" class="btn btn-primary">Add New Client</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {

            /* ----------  Auto-open Client tab + SweetAlert after redirect ---------- */
            const urlParams = new URLSearchParams(window.location.search);

            if (urlParams.get('tab') === 'client') {
                $('#showClientForm').click();          // open client form
            }

            if (urlParams.get('status') === 'client_added') {
                const cid = urlParams.get('client_id');
                Swal.fire({
                    icon: 'success',
                    title: 'Client Added!',
                    text: `Client ID: ${cid} has been created successfully.`,
                    confirmButtonText: 'OK'
                });
                // optional: clean URL bar
                window.history.replaceState({}, document.title, window.location.pathname + '?tab=client');
            }

            $('.select2').select2();

            // Client select is always multi-select (one project is created per selected client)
            $('#client_id').select2({ placeholder: 'Select one or more Clients' });

            $('#showProjectForm').click(function () {
                $('#projectForm').show();
                $('#clientForm').hide();
                $(this).addClass('btn-primary').removeClass('btn-secondary');
                $('#showClientForm').addClass('btn-secondary').removeClass('btn-primary');
            });

            $('#showClientForm').click(function () {
                $('#clientForm').show();
                $('#projectForm').hide();
                $(this).addClass('btn-primary').removeClass('btn-secondary');
                $('#showProjectForm').addClass('btn-secondary').removeClass('btn-primary');
                toggleForms();
            });

            function toggleForms() {
                var memberType = $('#fullForm').is(':visible') ? $('#membertype').val() : $('#membertype_simple').val();
                memberType = memberType || '';
                const fullForm = $('#fullForm');
                const simplifiedForm = $('#simplifiedForm');
                const categoryContainer = $('#category_container');
                const categoryContainerSimple = $('#category_container_simple');

                if (memberType === '1' || memberType === '8') {
                    fullForm.hide();
                    simplifiedForm.show();
                    $('#membertype_simple').val(memberType).trigger('change.select2');
                    categoryContainerSimple.hide();
                    $('#category_simple').removeAttr('required');
                    $('#category_simple').val('').trigger('change.select2');
                    $('#custom_category_simple').hide().removeAttr('required').val('');
                    $('#specialClient_simple').val($('#specialClient').val()).trigger('change.select2');
                    $('#name_simple').val($('#name').val());
                    $('#mname_simple').val($('#mname').val());
                    $('#lname_simple').val($('#lname').val());
                    $('#hstreet_simple').val($('#hstreet').val());
                    $('#hcity_simple').val($('#hcity').val());
                    $('#hstate_simple').val($('#hstate').val());
                    $('#hzip_simple').val($('#hzip').val());
                    $('#hcounty_simple').val($('#hcounty').val());
                    $('#file_simple').val($('#file').val());
                    $('#ssn_simple').val($('#ssn').val());
                    $('#dob_simple').val($('#dob').val());
                    $('#phonecell_simple').val($('#phonecell').val());
                    $('#pemail_simple').val($('#pemail').val());
                    $('#ach_payer_simple').val($('#ach_payer').val()).trigger('change.select2');
                } else {
                    fullForm.show();
                    simplifiedForm.hide();
                    $('#membertype').val(memberType).trigger('change.select2');
                    categoryContainer.show();
                    $('#category').attr('required', 'required');
                    $('#category').val($('#category_simple').val()).trigger('change.select2');
                    $('#custom_category').val($('#custom_category_simple').val());
                    $('#specialClient').val($('#specialClient_simple').val()).trigger('change.select2');
                    $('#name').val($('#name_simple').val());
                    $('#mname').val($('#mname_simple').val());
                    $('#lname').val($('#lname_simple').val());
                    $('#hstreet').val($('#hstreet_simple').val());
                    $('#hcity').val($('#hcity_simple').val());
                    $('#hstate').val($('#hstate_simple').val());
                    $('#hzip').val($('#hzip_simple').val());
                    $('#hcounty').val($('#hcounty_simple').val());
                    $('#file').val($('#file_simple').val());
                    $('#ssn').val($('#ssn_simple').val());
                    $('#dob').val($('#dob_simple').val());
                    $('#phonecell').val($('#phonecell_simple').val());
                    $('#pemail').val($('#pemail_simple').val());
                    $('#ach_payer').val($('#ach_payer_simple').val()).trigger('change.select2');
                }
                // Update custom category visibility after form toggle
                toggleCustomCategory('category', 'custom_category');
                toggleCustomCategory('category_simple', 'custom_category_simple');
            }

            // Toggle custom category input visibility
            function toggleCustomCategory(selectId, inputId) {
                const select = $(`#${selectId}`);
                const customInput = $(`#${inputId}`);
                if (select.val() === 'Other') {
                    customInput.show().attr('required', 'required');
                } else {
                    customInput.hide().removeAttr('required').val('');
                }
            }

            $('#membertype, #membertype_simple').on('change', function () {
                toggleForms();
            });

            $('#category').on('change', function () {
                toggleCustomCategory('category', 'custom_category');
            });

            $('#category_simple').on('change', function () {
                toggleCustomCategory('category_simple', 'custom_category_simple');
            });

            $('#sameAddressCheckbox').change(function () {
                if (this.checked) {
                    $('#bstreet').val($('#hstreet').val());
                    $('#bcity').val($('#hcity').val());
                    $('#bstate').val($('#hstate').val());
                    $('#bzip').val($('#hzip').val());
                    $('#bcounty').val($('#hcounty').val());
                } else {
                    $('#bstreet').val('');
                    $('#bcity').val('');
                    $('#bstate').val('');
                    $('#bzip').val('');
                    $('#bcounty').val('');
                }
            }); 

            ['ssn', 'ssn_simple'].forEach(id => {
                $(`#${id}`).on('input', function (event) {
                    let inputValue = event.target.value.replace(/\D/g, '');
                    let formattedValue = '';
                    for (let i = 0; i < inputValue.length; i++) {
                        if (i === 3 || i === 5) {
                            formattedValue += '-';
                        }
                        formattedValue += inputValue[i];
                    }
                    event.target.value = formattedValue;
                });
            });

            function formatPhoneInput(e) {
                let phone = e.target.value.replace(/\D/g, '');
                if (phone.length > 3 && phone.length <= 6) {
                    phone = phone.substring(0, 3) + '-' + phone.substring(3);
                } else if (phone.length > 6) {
                    phone = phone.substring(0, 3) + '-' + phone.substring(3, 6) + '-' + phone.substring(6, 10);
                }
                e.target.value = phone;
            }

            ['phonework', 'phonecell', 'phoneother', 'phonecell_simple'].forEach(id => {
                $(`#${id}`).on('input', formatPhoneInput);
            });

            $('#ein').on('input', function (event) {
                let inputValue = event.target.value.replace(/\D/g, '');
                let formattedValue = '';
                inputValue = inputValue.substr(0, 9);
                if (inputValue.length > 2) {
                    formattedValue += inputValue.substr(0, 2) + '-';
                    inputValue = inputValue.substr(2);
                }
                formattedValue += inputValue;
                event.target.value = formattedValue;
            });

            ['hzip', 'bzip', 'hzip_simple'].forEach(id => {
                $(`#${id}`).on('input', function () {
                    let zip = this.value.replace(/\D/g, '');
                    if (zip.length > 5) {
                        zip = zip.substring(0, 5) + '-' + zip.substring(5, 9);
                    }
                    this.value = zip;
                });
            });

            ['pro_image', 'pro_image_simple'].forEach(id => {
                $(`#${id}`).change(function () {
                    if (this.files && this.files[0]) {
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            $(`#image${id === 'pro_image' ? '' : '_simple'}`).attr('src', e.target.result);
                        }
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            });

            // Initialize category visibility when client form is shown
            $('#showClientForm').click(function () {
                const memberType = $('#membertype').val() || $('#membertype_simple').val() || '';
                const categoryContainer = $('#category_container');
                const categoryContainerSimple = $('#category_container_simple');
                if (memberType === '1' || memberType === '8') {
                    categoryContainerSimple.hide();
                    $('#category_simple').removeAttr('required');
                    $('#category_simple').val('').trigger('change.select2');
                    $('#custom_category_simple').hide().removeAttr('required').val('');
                } else {
                    categoryContainer.show();
                    $('#category').attr('required', 'required');
                }
                toggleCustomCategory('category', 'custom_category');
                toggleCustomCategory('category_simple', 'custom_category_simple');
            });

        });
 
    </script>

    <!-- ===== Customizer: apply label / required / max-length overrides ===== -->
    <script>
        const CUZ_OVERRIDES = <?= json_encode($cuz_override_obj) ?>;
        function applyCustomizerOverrides() {
            document.querySelectorAll('[data-cpc-field]').forEach(function (el) {
                const key = el.dataset.cpcField;
                const ov = CUZ_OVERRIDES[key];
                if (!ov) return;
                const label = el.querySelector('label');
                if (label && ov.label && label.firstChild && label.firstChild.nodeType === 3) label.firstChild.textContent = ov.label;
                if (typeof ov.req !== 'undefined') {
                    el.querySelectorAll('input, select, textarea').forEach(function (inp) {
                        if (ov.req) inp.required = true; else inp.required = false;
                        if (ov.max && (inp.type === 'text' || inp.type === 'tel' || inp.type === 'email' || inp.type === 'number')) {
                            inp.maxLength = ov.max;
                        }
                    });
                }
            });
        }
        document.addEventListener('DOMContentLoaded', applyCustomizerOverrides);
    </script>
</body>

</html>