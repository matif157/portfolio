<?php
include "includes/db.php";
include "includes/auth.php";
include "includes/activity_log.php";
include "client_type_manual.php";
include "includes/customizer.php";
$user_id = $_SESSION['user_id'];
$msg = '';

if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// Handle AJAX request for adding client
if (isset($_POST['add_client_ajax'])) {
    // Set content type header for JSON response
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => '', 'duplicate' => false, 'client_info' => null];

    try {
        // Check if user wants to bypass duplicate check
        $bypass_duplicate = isset($_POST['bypass_duplicate']) ? intval($_POST['bypass_duplicate']) : 0;

        // Validate membertype
        $membertype = isset($_POST['membertype']) && is_numeric($_POST['membertype']) ? intval($_POST['membertype']) : 0;
        if ($membertype === 0) {
            throw new Exception("Please select a valid member type.");
        }

        $specialClient = isset($_POST['specialClient']) ? $_POST['specialClient'] : 0;
        $ach_payer = isset($_POST['ach_payer']) && in_array($_POST['ach_payer'], ['0', '1']) ? intval($_POST['ach_payer']) : 0;
        $payer_frequency = isset($_POST['payer_frequency']) &&
            in_array($_POST['payer_frequency'], ['None', 'Monthly', 'Quarterly'])
            ? $_POST['payer_frequency'] : 'None';

        // Simplified form (types with Category hidden in the Client Type Manual)
        // does not submit a category. Business clients submit the IRS Schedule C
        // cascade (industry header + NAICS code + label); we store it as one
        // string: "Header - Label (code)".
        $isFullType = cpc_type_field_config($con, $membertype)['category'] ?? true;
        if (!$isFullType) {
            $category = '';
        } else {
            $cat_group = trim($_POST['category_group'] ?? '');
            $cat_code = trim($_POST['category_code'] ?? '');
            $cat_label = trim($_POST['category_label'] ?? '');
            if ($cat_code === '' || $cat_group === '' || $cat_label === '') {
                throw new Exception("Please select a business category (industry & activity).");
            }
            $category = mysqli_real_escape_string($con, "$cat_group - $cat_label ($cat_code)");
        }
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

        $dba = isset($_POST['dba']) ? mysqli_real_escape_string($con, $_POST['dba']) : '';
        $ein = isset($_POST['ein']) ? mysqli_real_escape_string($con, $_POST['ein']) : '';
        $tfnumber = isset($_POST['tfnumber']) ? mysqli_real_escape_string($con, $_POST['tfnumber']) : '';
        $phonework = isset($_POST['phonework']) ? mysqli_real_escape_string($con, $_POST['phonework']) : '';
        $phoneother = isset($_POST['phoneother']) ? mysqli_real_escape_string($con, $_POST['phoneother']) : '';
        $phoneoverseas = isset($_POST['phoneoverseas']) ? mysqli_real_escape_string($con, $_POST['phoneoverseas']) : '';
        $fax = isset($_POST['fax']) ? mysqli_real_escape_string($con, $_POST['fax']) : '';
        $semail = isset($_POST['semail']) ? mysqli_real_escape_string($con, $_POST['semail']) : '';
        $bname = isset($_POST['bname']) ? mysqli_real_escape_string($con, $_POST['bname']) : '';
        $bstate = isset($_POST['bstate']) ? mysqli_real_escape_string($con, $_POST['bstate']) : '';
        $bcity = isset($_POST['bcity']) ? mysqli_real_escape_string($con, $_POST['bcity']) : '';
        $bstreet = isset($_POST['bstreet']) ? mysqli_real_escape_string($con, $_POST['bstreet']) : '';
        $bzip = isset($_POST['bzip']) ? mysqli_real_escape_string($con, $_POST['bzip']) : '';
        $bcounty = isset($_POST['bcounty']) ? mysqli_real_escape_string($con, $_POST['bcounty']) : '';
        $client_since = !empty($_POST['client_since']) ? mysqli_real_escape_string($con, $_POST['client_since']) : date("Y-m-d");
        $incorp_date = !empty($_POST['incorp_date']) ? mysqli_real_escape_string($con, $_POST['incorp_date']) : date("Y-m-d");
        $bus_start_date = !empty($_POST['bus_start_date']) ? mysqli_real_escape_string($con, $_POST['bus_start_date']) : date("Y-m-d");
        $member_pos = isset($_POST['member_pos'])
            ? mysqli_real_escape_string($con, $_POST['member_pos'])
            : '';

        // Handle file upload
        $pic = '';
        if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == 0) {
            $pic_name = $_FILES['post_image']['name'];
            $pic_temp = $_FILES['post_image']['tmp_name'];
            $pic_store = "images/clients/" . rand(100, 1000) . '-' . basename($pic_name);

            if (!empty($pic_name) && move_uploaded_file($pic_temp, $pic_store)) {
                $pic = basename($pic_store);
            }
        }


        if (!$bypass_duplicate) {
            $conditions = [];

            // Only check phone if provided and not empty
            if (!empty($phonecell)) {
                $conditions[] = "m.cell_ph = '$phonecell'";
            }
            if (!empty($phonework)) {
                $conditions[] = "m.phone = '$phonework'";
            }
            // Only check business name if provided
            if (!empty($bname)) {
                $conditions[] = "m.bus_name = '$bname'";
            }

            // Only run query if we have conditions
            if (!empty($conditions)) {
                $duplicate_check_query = "SELECT m.*, mt.type_name FROM members m 
        LEFT JOIN member_type mt ON m.type = mt.type_id
        WHERE " . implode(' OR ', $conditions);

                $duplicate_result = mysqli_query($con, $duplicate_check_query);

                if (mysqli_num_rows($duplicate_result) > 0) {
                    $duplicate_client = mysqli_fetch_assoc($duplicate_result);

                    // Prepare client information for response - USE CORRECT COLUMN NAMES
                    $client_info = [
                        'id' => $duplicate_client['member_id'],
                        'name' => trim($duplicate_client['name'] . ' ' . $duplicate_client['lname']),
                        'type' => $duplicate_client['type_name'] ?: 'Unknown',
                        'phone' => $duplicate_client['cell_ph'] ?: $duplicate_client['phone'] ?: 'N/A',
                        'email' => $duplicate_client['prm_email'] ?: 'N/A',
                        'business' => $duplicate_client['bus_name'] ?: '',
                        'status' => $duplicate_client['status']
                    ];

                    $response['duplicate'] = true;
                    $response['client_info'] = $client_info;
                    $response['message'] = "Potential duplicate client found!";

                    echo json_encode($response);
                    exit;
                }
            }
        }

        $currentdate = date("Y-m-d");
        $phone_work_value = $isFullType ? $phonework : '';

        $queryy = "INSERT INTO members (
            file, name, dba, ein, ss, tf, dob, phone, cell_ph, other_ph, overseas_ph,
            prm_email, sec_email, fax, mem_pic, type, added_by, mname, lname, bus_name, member_pos,
            hzip, hstate, hcity, hstreet, city, state, address, zip,
            occupation, idcard, pmb, hapartment, datelast, dateupdated,
            duedate, closingm, incorp_date, incorpstate, user_id, business_address, client_since, specialClient, bus_start_date,
            hcounty, bcounty, category, ach_payer, payer_frequency
        ) VALUES (
            '$file', '$name', '$dba', '$ein', '$ssn', '$tfnumber', '$dob', '$phone_work_value',
            '$phonecell', '$phoneother', '$phoneoverseas', '$pemail', '$semail', '$fax',
            '$pic', '$membertype', '$user_id', '$mname', '$lname', '$bname','$member_pos', '$hzip', '$hstate',
            '$hcity', '$hstreet', '$bcity', '$bstate', '$bstreet', '$bzip',
            '', '', '', '', '$currentdate', '$currentdate', '', '', '$incorp_date', '', $user_id, '', '$client_since', '$specialClient', '$bus_start_date',
            '$hcounty', '$bcounty', '$category', '$ach_payer', '$payer_frequency'
        );";

        $insert_query = mysqli_query($con, $queryy);
        if ($insert_query) {
            // Get the ID of the newly inserted member
            $new_member_id = mysqli_insert_id($con);

            // Create a description for the log
            $client_full_name = $_POST['name'] . ' ' . $_POST['lname'];
            $log_description = "Added new client: " . $client_full_name . " (ID: " . $new_member_id . ")";

            // Log the activity
            logActivity($con, $user_id, 'CREATE', 'member', $new_member_id, $log_description);

            // Customizer: save any custom field values submitted and audit.
            $custFields = customizer_custom_fields($con, 'clients');
            if (!empty($custFields)) {
                $custVals = customizer_extract_custom_values($custFields);
                customizer_save_record_values($con, 'clients', $new_member_id, $custVals, (int) $user_id, $_SESSION['fullname'] ?? '');
            }
            customizer_audit($con, 'clients', $new_member_id, 'name', 'Client Record', 'created', (int) $user_id, $_SESSION['fullname'] ?? '', null, $client_full_name);

            $response['success'] = true;
            $response['message'] = "Client Has Been Added Successfully.";
            $response['client_id'] = $new_member_id;
        } else {
            throw new Exception("Something Went Wrong: " . mysqli_error($con));
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit; // Important: exit to prevent HTML output
}

// Handle regular form submission (fallback for non-JS users)
if (isset($_POST['add_client'])) {
    $pic = '';

    // Validate membertype
    $membertype = isset($_POST['membertype']) && is_numeric($_POST['membertype']) ? intval($_POST['membertype']) : 0;
    if ($membertype === 0) {
        $_SESSION['msg'] = "<div class='alert alert-danger'>Please select a valid member type.</div>";
        header("Location: add_client.php");
        exit;
    }

    $specialClient = isset($_POST['specialClient']) ? $_POST['specialClient'] : 0;
    $ach_payer = isset($_POST['ach_payer']) && in_array($_POST['ach_payer'], ['0', '1']) ? intval($_POST['ach_payer']) : 0;
    $payer_frequency = isset($_POST['payer_frequency']) &&
        in_array($_POST['payer_frequency'], ['None', 'Monthly', 'Quarterly'])
        ? $_POST['payer_frequency'] : 'None';
    // Simplified form (types with Category hidden in the Client Type Manual) does
    // not submit a category. Business clients submit the IRS Schedule C cascade;
    // stored as "Header - Label (code)".
    $isFullType = cpc_type_field_config($con, $membertype)['category'] ?? true;
    if (!$isFullType) {
        $category = '';
    } else {
        $cat_group = trim($_POST['category_group'] ?? '');
        $cat_code = trim($_POST['category_code'] ?? '');
        $cat_label = trim($_POST['category_label'] ?? '');
        if ($cat_code === '' || $cat_group === '' || $cat_label === '') {
            $_SESSION['msg'] = "<div class='alert alert-danger'>Please select a business category (industry & activity).</div>";
            header("Location: add_client.php");
            exit;
        }
        $category = mysqli_real_escape_string($con, "$cat_group - $cat_label ($cat_code)");
    }
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

    $dba = isset($_POST['dba']) ? mysqli_real_escape_string($con, $_POST['dba']) : '';
    $ein = isset($_POST['ein']) ? mysqli_real_escape_string($con, $_POST['ein']) : '';
    $tfnumber = isset($_POST['tfnumber']) ? mysqli_real_escape_string($con, $_POST['tfnumber']) : '';
    $phonework = isset($_POST['phonework']) ? mysqli_real_escape_string($con, $_POST['phonework']) : '';
    $phoneother = isset($_POST['phoneother']) ? mysqli_real_escape_string($con, $_POST['phoneother']) : '';
    $phoneoverseas = isset($_POST['phoneoverseas']) ? mysqli_real_escape_string($con, $_POST['phoneoverseas']) : '';
    $fax = isset($_POST['fax']) ? mysqli_real_escape_string($con, $_POST['fax']) : '';
    $semail = isset($_POST['semail']) ? mysqli_real_escape_string($con, $_POST['semail']) : '';
    $bname = isset($_POST['bname']) ? mysqli_real_escape_string($con, $_POST['bname']) : '';
    $bstate = isset($_POST['bstate']) ? mysqli_real_escape_string($con, $_POST['bstate']) : '';
    $bcity = isset($_POST['bcity']) ? mysqli_real_escape_string($con, $_POST['bcity']) : '';
    $bstreet = isset($_POST['bstreet']) ? mysqli_real_escape_string($con, $_POST['bstreet']) : '';
    $bzip = isset($_POST['bzip']) ? mysqli_real_escape_string($con, $_POST['bzip']) : '';
    $bcounty = isset($_POST['bcounty']) ? mysqli_real_escape_string($con, $_POST['bcounty']) : '';
    $client_since = !empty($_POST['client_since']) ? mysqli_real_escape_string($con, $_POST['client_since']) : date("Y-m-d");
    $incorp_date = !empty($_POST['incorp_date']) ? mysqli_real_escape_string($con, $_POST['incorp_date']) : date("Y-m-d");
    $bus_start_date = !empty($_POST['bus_start_date']) ? mysqli_real_escape_string($con, $_POST['bus_start_date']) : date("Y-m-d");
    $member_pos = isset($_POST['member_pos'])
        ? mysqli_real_escape_string($con, $_POST['member_pos'])
        : '';

    // Handle file upload
    $pic_name = $_FILES['post_image']['name'];
    $pic_temp = $_FILES['post_image']['tmp_name'];
    $pic_store = "images/clients/" . rand(100, 1000) . '-' . basename($pic_name);

    if (!empty($pic_name) && move_uploaded_file($pic_temp, $pic_store)) {
        $pic = basename($pic_store);
    } else {
        $pic = '';
    }

    $conditions = [];

    if (!empty($phonecell)) {
        $conditions[] = "cell_ph = '$phonecell'";
    }
    if (!empty($phonework)) {
        $conditions[] = "phone = '$phonework'";
    }
    if (!empty($bname)) {
        $conditions[] = "bus_name = '$bname'";
    }

    if (!empty($conditions)) {
        $duplicate_check_query = "SELECT * FROM members WHERE " . implode(' OR ', $conditions);
        $duplicate_result = mysqli_query($con, $duplicate_check_query);

        if (mysqli_num_rows($duplicate_result) > 0) {
            $_SESSION['msg'] = "<div class='alert alert-danger'>Client already exists with the same phone number or business name.</div>";
            header("Location: add_client.php");
            exit;
        }
    }

    $currentdate = date("Y-m-d");
    $phone_work_value = $isFullType ? $phonework : '';

    $queryy = "INSERT INTO members (
        file, name, dba, ein, ss, tf, dob, phone, cell_ph, other_ph, overseas_ph,
        prm_email, sec_email, fax, mem_pic, type, added_by, mname, lname, bus_name, member_pos,
        hzip, hstate, hcity, hstreet, city, state, address, zip,
        occupation, idcard, pmb, hapartment, datelast, dateupdated,
        duedate, closingm, incorp_date, incorpstate, user_id, business_address, client_since, specialClient, bus_start_date,
        hcounty, bcounty, category, ach_payer, payer_frequency
    ) VALUES (
        '$file', '$name', '$dba', '$ein', '$ssn', '$tfnumber', '$dob', '$phone_work_value',
        '$phonecell', '$phoneother', '$phoneoverseas', '$pemail', '$semail', '$fax',
        '$pic', '$membertype', '$user_id', '$mname', '$lname', '$bname','$member_pos', '$hzip', '$hstate',
        '$hcity', '$hstreet', '$bcity', '$bstate', '$bstreet', '$bzip',
        '', '', '', '', '$currentdate', '$currentdate', '', '', '$incorp_date', '', $user_id, '', '$client_since', '$specialClient', '$bus_start_date',
        '$hcounty', '$bcounty', '$category', '$ach_payer', '$payer_frequency'
    );";

    $insert_query = mysqli_query($con, $queryy);
    if ($insert_query) {
        // Get the ID of the newly inserted member
        $new_member_id = mysqli_insert_id($con);

        // Create a description for the log
        $client_full_name = $_POST['name'] . ' ' . $_POST['lname'];
        $log_description = "Added new client: " . $client_full_name . " (ID: " . $new_member_id . ")";

        // Log the activity
        logActivity($con, $user_id, 'CREATE', 'member', $new_member_id, $log_description);

        // Customizer: save any custom field values submitted and audit.
        $custFields = customizer_custom_fields($con, 'clients');
        if (!empty($custFields)) {
            $custVals = customizer_extract_custom_values($custFields);
            customizer_save_record_values($con, 'clients', $new_member_id, $custVals, (int) $user_id, $_SESSION['fullname'] ?? '');
        }
        customizer_audit($con, 'clients', $new_member_id, 'name', 'Client Record', 'created', (int) $user_id, $_SESSION['fullname'] ?? '', null, $client_full_name);

        $_SESSION['msg'] = "<div class='alert alert-success'>Client Has Been Added Successfully.</div>";
        header("Location: client_details.php?id=" . $new_member_id . "&status=1");
        exit;
    } else {
        $_SESSION['msg'] = "<div class='alert alert-danger'>Something Went Wrong: " . mysqli_error($con) . "</div>";
    }

    header("Location: add_client.php");
    exit;
}

// Client Type Manual field-visibility config for every type, embedded below so
// the form can show/hide fields per selected type without a round trip.
$cpcConfigByType = cpc_all_types_field_config($con);

/* Customizer: field overrides (label / required / max_length) applied to the
   form's system inputs on load. Encoded as JSON for the JS below. */
$cuz_override_obj = array();
$cuz_overrides = customizer_fields_for_module($con, 'clients');
foreach ($cuz_overrides as $ck => $cf) {
    if ($cf['is_system']) {
        $cuz_override_obj[$ck] = array(
            'label'   => $cf['label'],
            'req'     => (int) $cf['is_required'],
            'max'     => (int) $cf['max_length'],
            'status'  => (int) $cf['status'],
        );
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Syed Professional Services - Dashboard</title>
    <?php include "includes/links.php"; ?>
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .swal2-popup {
            font-size: 1rem;
        }

        .swal2-html-container {
            margin: 1em 0.75em;
        }

        .client-info {
            text-align: left;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .client-info-row {
            display: flex;
            margin-bottom: 8px;
        }

        .client-info-label {
            font-weight: bold;
            width: 100px;
        }

        .client-info-value {
            flex: 1;
        }

        /* Combined industry / business-activity search combobox */
        .cat-combo {
            position: relative;
        }

        .cat-combo-panel {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1055;
            max-height: 320px;
            overflow-y: auto;
            background-color: #ffffff;
            border: 1px solid #ced4da;
            border-radius: 6px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            margin-top: 4px;
        }

        .cat-combo-panel .cat-group {
            border-bottom: 1px solid #f1f1f1;
        }

        .cat-combo-panel .cat-group:last-child {
            border-bottom: none;
        }

        .cat-combo-panel .cat-group-header {
            padding: 8px 14px;
            font-weight: 600;
            font-size: 0.875rem;
            color: #293240;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 6px;
        }

        .cat-combo-panel .cat-group-header::after {
            content: '\25BE';
            font-size: 11px;
            color: #999;
            transform: rotate(-90deg);
            transition: transform .15s ease;
        }

        .cat-combo-panel .cat-group.expanded>.cat-group-header::after {
            transform: rotate(0deg);
        }

        .cat-combo-panel .cat-group-header:hover,
        .cat-combo-panel .cat-group.expanded>.cat-group-header {
            background-color: #f2f4ff;
            color: #4054b2;
        }

        .cat-combo-panel .cat-activity-list {
            display: none;
        }

        .cat-combo-panel .cat-group.expanded>.cat-activity-list {
            display: block;
        }

        .cat-combo-panel .cat-activity-item {
            padding: 6px 14px 6px 28px;
            font-size: 0.85rem;
            color: #293240;
            cursor: pointer;
        }

        .cat-combo-panel .cat-activity-item:hover {
            background-color: #e7f1ff;
        }

        .cat-combo-panel .cat-empty {
            padding: 12px 14px;
            font-size: 0.85rem;
            color: #888;
            font-style: italic;
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
                                <h4 class="m-b-10">Dashboard</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Add Client</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($msg)) {
                echo $msg;
            } ?>

            <!-- Full Form -->
            <form method="POST" enctype="multipart/form-data" id="fullForm">
                <input type="hidden" name="add_client_ajax" value="1">
                <input type="hidden" name="bypass_duplicate" id="bypass_duplicate" value="0">
                <div class="row mb-2">
                    <div class="col-md-6">
                        <label for="membertype" class="form-label">Type</label><span class="text-danger">*</span>
                        <select class="form-control select" required="required" id="membertype" name="membertype">
                            <option value="" selected="selected">Select Type</option>
                            <?php
                            $querr = mysqli_query($con, "SELECT * FROM member_type");
                            while ($genetics = mysqli_fetch_array($querr)) { ?>
                                <option value="<?php echo $genetics['type_id']; ?>">
                                    <?php echo $genetics['type_name']; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="specialClient" class="form-label">Sympathetic Client</label>
                            <select class="form-control"  name="specialClient" id="specialClient">
                                <option value="" disabled selected>Select Yes or No</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="ach_payer" class="form-label">ACH Payer</label>
                            <select class="form-control" name="ach_payer" id="ach_payer">
                                <option value="" disabled selected>Select Yes or No</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    <!-- NEW – Payer Frequency -->
                    <div class="col-md-6">
                        <label for="payer_frequency" class="form-label">Payer Type</label>
                        <select class="form-control" name="payer_frequency" id="payer_frequency">
                            <option value="None" selected>None</option>
                            <option value="Monthly">Monthly</option>
                            <option value="Quarterly">Quarterly</option>
                        </select>
                    </div>
                    <div class="col-md-12" id="category_container" data-cpc-field="category">
                        <div class="form-group">
                            <label for="category_search" class="form-label">Industry Category / Business Activity</label><span class="text-danger">*</span>
                            <div class="cat-combo">
                                <input type="text" class="form-control cat-combo-input" id="category_search"
                                    autocomplete="off"
                                    placeholder="Click to browse by industry, or type to search either field...">
                                <input type="hidden" name="category_group" id="category_group" data-selected="">
                                <input type="hidden" name="category_code" id="category_code" data-selected="">
                                <input type="hidden" name="category_label" id="category_code_label" value="">
                                <div class="cat-combo-panel" id="category_search_panel"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="name" class="form-label">First Name</label><span class="text-danger">*</span>
                            <input class="form-control" required="required" name="name" type="text"
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
                            <label for="lname" class="form-label">Last Name</label><span class="text-danger">*</span>
                            <input class="form-control" required="required" name="lname" type="text"
                                placeholder="Enter Last Name" id="lname"
                                oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <h6>Personal Address:</h6>
                    </div>
                    <div class="col-md-3" data-cpc-field="hstreet">
                        <div class="form-group">
                            <label for="hstreet" class="form-label">Street</label><span class="text-danger">*</span>
                            <input class="form-control" required="required" name="hstreet" type="text"
                                placeholder="Enter Street" id="hstreet">
                        </div>
                    </div>
                    <div class="col-md-3" data-cpc-field="hcity">
                        <div class="form-group">
                            <label for="hcity" class="form-label">City</label><span class="text-danger">*</span>
                            <input class="form-control" required="required" name="hcity" type="text"
                                placeholder="Enter City" id="hcity">
                        </div>
                    </div>
                    <div class="col-md-3" data-cpc-field="hstate_zip">
                        <div class="form-group">
                            <label for="hstate" class="form-label">State</label><span class="text-danger">*</span>
                            <input class="form-control" required="required" name="hstate" type="text"
                                placeholder="Enter State" id="hstate">
                        </div>
                    </div>
                    <div class="col-md-3" data-cpc-field="hstate_zip">
                        <div class="form-group">
                            <label for="hzip" class="form-label">Zip</label><span class="text-danger">*</span>
                            <input class="form-control" required="required" name="hzip" type="number"
                                placeholder="Enter Zip" id="hzip">
                        </div>
                    </div>
                    <div class="col-md-12" data-cpc-field="hcounty">
                        <div class="form-group">
                            <label for="hcounty" class="form-label">County</label>
                            <input class="form-control" name="hcounty" type="text" placeholder="Enter County"
                                id="hcounty">
                        </div>
                    </div>

                    <div class="col-md-6" data-cpc-field="bus_name">
                        <div class="form-group">
                            <label for="bname" class="form-label">Business Name</label>
                            <input class="form-control" name="bname" type="text" placeholder="Enter Business Name"
                                id="bname">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="member_pos">
                        <div class="form-group">
                            <label for="member_pos" class="form-label">Designation</label>
                            <input class="form-control" name="member_pos" id="member_pos" type="text"
                                placeholder="Enter Designation">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <h6>Business Address:</h6>
                        <label><input type="checkbox" id="sameAddressCheckbox"> Same as Personal Address</label>
                    </div>

                    <div class="col-md-3" data-cpc-field="address">
                        <div class="form-group">
                            <label for="bstreet" class="form-label">Street</label>
                            <input class="form-control" name="bstreet" type="text" placeholder="Enter Street"
                                id="bstreet">
                        </div>
                    </div>
                    <div class="col-md-3" data-cpc-field="city">
                        <div class="form-group">
                            <label for="bcity" class="form-label">City</label>
                            <input class="form-control" name="bcity" type="text" placeholder="Enter City" id="bcity">
                        </div>
                    </div>
                    <div class="col-md-3" data-cpc-field="state_zip">
                        <div class="form-group">
                            <label for="bstate" class="form-label">State</label>
                            <input class="form-control" name="bstate" type="text" placeholder="Enter State" id="bstate">
                        </div>
                    </div>
                    <div class="col-md-3" data-cpc-field="state_zip">
                        <div class="form-group">
                            <label for="bzip" class="form-label">Zip</label>
                            <input class="form-control" name="bzip" type="text" placeholder="Enter Zip" id="bzip">
                        </div>
                    </div>
                    <div class="col-md-12" data-cpc-field="bcounty">
                        <div class="form-group">
                            <label for="bcounty" class="form-label">County</label>
                            <input class="form-control" name="bcounty" type="text" placeholder="Enter County"
                                id="bcounty">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="file">
                        <div class="form-group">
                            <label for="file" class="form-label">File</label>
                            <input class="form-control" name="file" type="text" id="file" placeholder="Enter File No">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="ss">
                        <div class="form-group">
                            <label for="ssn" class="form-label">ITIN/SSN</label><span class="text-danger">*</span>
                            <input class="form-control" required="required" name="ssn" type="text" maxlength="11"
                                placeholder="XXX-XX-XXXX" id="ssn">
                        </div>
                    </div>
                    <div class="col-md-4" data-cpc-field="dba">
                        <div class="form-group">
                            <label for="dba" class="form-label">DBA</label>
                            <input class="form-control" name="dba" type="text" placeholder="DBA" id="dba">
                        </div>
                    </div>
                    <div class="col-md-4" data-cpc-field="ein">
                        <div class="form-group">
                            <label for="ein" class="form-label">EIN</label>
                            <input class="form-control" name="ein" type="text" maxlength="10" placeholder="XX-XXXXXXX"
                                id="ein">
                        </div>
                    </div>
                    <div class="col-md-4" data-cpc-field="tf">
                        <div class="form-group">
                            <label for="tfnumber" class="form-label">PMB</label>
                            <input class="form-control" name="tfnumber" type="tel" id="tfnumber" placeholder="PMB">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="dob">
                        <div class="form-group">
                            <label for="dob" class="form-label">Date of Birth</label><span class="text-danger">*</span>
                            <input class="form-control" required="required" name="dob" type="date" id="dob"
                                placeholder="Date of Birth">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="phone">
                        <div class="form-group">
                            <label for="phonework" class="form-label">Phone: Work</label>
                            <input class="form-control" name="phonework" type="tel" id="phonework"
                                placeholder="XXX-XXX-XXXX" maxlength="12">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="cell_ph">
                        <div class="form-group">
                            <label for="phonecell" class="form-label">Phone: Cell</label><span
                                class="text-danger">*</span>
                            <input class="form-control" name="phonecell" type="tel" id="phonecell"
                                placeholder="XXX-XXX-XXXX" required="required" maxlength="12">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="other_ph">
                        <div class="form-group">
                            <label for="phoneother" class="form-label">Phone: Other</label>
                            <input class="form-control" name="phoneother" type="tel" id="phoneother"
                                placeholder="XXX-XXX-XXXX" maxlength="12">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="overseas_ph">
                        <div class="form-group">
                            <label for="phoneoverseas" class="form-label">Phone: Overseas</label>
                            <input class="form-control" name="phoneoverseas" type="tel" id="phoneoverseas"
                                placeholder="Phone: Overseas">
                        </div>
                    </div>
                    <!-- added -->
                    <div class="col-md-6" data-cpc-field="prm_email">
                        <div class="form-group">
                            <label for="pemail" class="form-label">Primary Email</label><span
                                class="text-danger">*</span>
                            <input class="form-control" name="pemail" type="email" id="pemail"
                                placeholder="Primary Email" required="required">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="sec_email">
                        <div class="form-group">
                            <label for="semail" class="form-label">Business Email</label>
                            <input class="form-control" name="semail" type="email" id="semail"
                                placeholder="Business Email">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="client_since" class="form-label">Client Since</label>
                            <input class="form-control" name="client_since" type="date" id="client_since">
                        </div>
                    </div>
                    <div class="col-md-4" data-cpc-field="incorp_date">
                        <div class="form-group">
                            <label for="incorp_date" class="form-label">Incorporation Date</label>
                            <input class="form-control" name="incorp_date" type="date" id="incorp_date">
                        </div>
                    </div>
                    <div class="col-md-4" data-cpc-field="bus_start_date">
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
                                <img id="image" class="mt-3" style="width:25%; display:none;">
                            </label>
                        </div>
                    </div>
                    <?= customizer_render_custom_fields_block($con, 'clients') ?>
                    <div class="col-md-6" style="margin-top: 27px !important;">
                        <input type="submit" value="Add New Client" name="add_client" class="btn btn-primary"
                            style="width: 100%;">
                    </div>
                </div>
            </form>

            <!-- Simplified Form -->
            <form method="POST" enctype="multipart/form-data" id="simplifiedForm" style="display: none;">
                <input type="hidden" name="add_client_ajax" value="1">
                <input type="hidden" name="bypass_duplicate" id="bypass_duplicate_simple" value="0">
                <div class="row mb-2">
                    <div class="col-md-6">
                        <label for="membertype_simple" class="form-label">Type</label><span class="text-danger">*</span>
                        <select class="form-control select" required="required" id="membertype_simple"
                            name="membertype">
                            <option value="" selected="selected">Select Type</option>
                            <?php
                            $querr = mysqli_query($con, "SELECT * FROM member_type");
                            while ($genetics = mysqli_fetch_array($querr)) { ?>
                                <option value="<?php echo $genetics['type_id']; ?>">
                                    <?php echo $genetics['type_name']; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="specialClient_simple" class="form-label">Sympathetic Client</label>
                            <select class="form-control"  name="specialClient"
                                id="specialClient_simple">
                                <option value="" disabled selected>Select Yes or No</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="ach_payer" class="form-label">ACH Payer</label>
                            <select class="form-control"  name="ach_payer" id="ach_payer_simple">
                                <option value="" disabled selected>Select Yes or No</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>
                    <!-- NEW – Payer Frequency -->
                    <div class="col-md-6">
                        <label for="payer_frequency_simple" class="form-label">Payer Type</label>
                        <select class="form-control" name="payer_frequency" id="payer_frequency_simple">
                            <option value="None" selected>None</option>
                            <option value="Monthly">Monthly</option>
                            <option value="Quarterly">Quarterly</option>
                        </select>
                    </div>
                    <div class="col-md-12" id="category_container_simple" style="display: none;">
                        <div class="form-group">
                            <label for="category_search_simple" class="form-label">Industry Category / Business Activity</label>
                            <div class="cat-combo">
                                <input type="text" class="form-control cat-combo-input" id="category_search_simple"
                                    autocomplete="off"
                                    placeholder="Click to browse by industry, or type to search either field...">
                                <input type="hidden" name="category_group" id="category_group_simple" data-selected="">
                                <input type="hidden" name="category_code" id="category_code_simple" data-selected="">
                                <input type="hidden" name="category_label" id="category_code_simple_label" value="">
                                <div class="cat-combo-panel" id="category_search_panel_simple"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="name_simple" class="form-label">First Name</label><span
                                class="text-danger">*</span>
                            <input class="form-control" required="required" name="name" type="text"
                                placeholder="Enter First Name" id="name_simple"
                                oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="mname_simple" class="form-label">Middle Name</label>
                            <input class="form-control" name="mname" type="text" placeholder="Enter Middle Name"
                                id="mname_simple" oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="lname_simple" class="form-label">Last Name</label><span
                                class="text-danger">*</span>
                            <input class="form-control" required="required" name="lname" type="text"
                                placeholder="Enter Last Name" id="lname_simple"
                                oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <h6>Personal Address:</h6>
                    </div>
                    <div class="col-md-3" data-cpc-field="hstreet">
                        <div class="form-group">
                            <label for="hstreet_simple" class="form-label">Street</label><span
                                class="text-danger">*</span>
                            <input class="form-control" required="required" name="hstreet" type="text"
                                placeholder="Enter Street" id="hstreet_simple">
                        </div>
                    </div>
                    <div class="col-md-3" data-cpc-field="hcity">
                        <div class="form-group">
                            <label for="hcity_simple" class="form-label">City</label><span class="text-danger">*</span>
                            <input class="form-control" required="required" name="hcity" type="text"
                                placeholder="Enter City" id="hcity_simple">
                        </div>
                    </div>
                    <div class="col-md-3" data-cpc-field="hstate_zip">
                        <div class="form-group">
                            <label for="hstate_simple" class="form-label">State</label><span
                                class="text-danger">*</span>
                            <input class="form-control" required="required" name="hstate" type="text"
                                placeholder="Enter State" id="hstate_simple">
                        </div>
                    </div>
                    <div class="col-md-3" data-cpc-field="hstate_zip">
                        <div class="form-group">
                            <label for="hzip_simple" class="form-label">Zip</label><span class="text-danger">*</span>
                            <input class="form-control" required="required" name="hzip" type="number"
                                placeholder="Enter Zip" id="hzip_simple">
                        </div>
                    </div>
                    <div class="col-md-12" data-cpc-field="hcounty">
                        <div class="form-group">
                            <label for="hcounty_simple" class="form-label">County</label>
                            <input class="form-control" name="hcounty" type="text" placeholder="Enter County"
                                id="hcounty_simple">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="file">
                        <div class="form-group">
                            <label for="file_simple" class="form-label">File</label>
                            <input class="form-control" name="file" type="text" id="file_simple"
                                placeholder="Enter File No">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="ss">
                        <div class="form-group">
                            <label for="ssn_simple" class="form-label">ITIN/SSN</label><span class="text-danger">*</span>
                            <input class="form-control" required="required" name="ssn" type="text" maxlength="11"
                                placeholder="XXX-XX-XXXX" id="ssn_simple">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="dob">
                        <div class="form-group">
                            <label for="dob_simple" class="form-label">Date of Birth</label><span
                                class="text-danger">*</span>
                            <input class="form-control" required="required" name="dob" type="date" id="dob_simple"
                                placeholder="Date of Birth">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="cell_ph">
                        <div class="form-group">
                            <label for="phonecell_simple" class="form-label">Phone: Cell</label><span
                                class="text-danger">*</span>
                            <input class="form-control" name="phonecell" type="tel" id="phonecell_simple"
                                placeholder="XXX-XXX-XXXX" required="required" maxlength="12">
                        </div>
                    </div>
                    <div class="col-md-6" data-cpc-field="prm_email">
                        <div class="form-group">
                            <label for="pemail_simple" class="form-label">Primary Email</label><span
                                class="text-danger">*</span>
                            <input class="form-control" name="pemail" type="email" id="pemail_simple"
                                placeholder="Primary Email" required="required">
                        </div>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="pro_image_simple" class="form-label">Profile Image</label>
                        <div class="choose-file">
                            <label for="pro_image_simple" class="form-label" style="width: 100%;">
                                <input type="file" class="form-control" name="post_image" id="pro_image_simple"
                                    data-filename="pro_image_create_simple">
                                <img id="image_simple" class="mt-3" style="width:25%; display:none;">
                            </label>
                        </div>
                    </div>
                    <?= customizer_render_custom_fields_block($con, 'clients') ?>
                    <div class="col-md-6" style="margin-top: 27px !important;">
                        <input type="submit" value="Add New Client" name="add_client" class="btn btn-primary"
                            style="width: 100%;">
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // ===== Client Type Manual: per-type field visibility =====
        const CPC_TYPE_CONFIG = <?= json_encode($cpcConfigByType) ?>;

        // Cache which [data-cpc-field] controls started out required, so hiding
        // a field can also drop "required" (a display:none required field isn't
        // reliably barred from constraint validation across browsers) and a
        // later re-show can restore it.
        document.querySelectorAll('[data-cpc-field] [required]').forEach(function (inp) {
            inp.dataset.cpcWasRequired = '1';
        });

        function applyCpcVisibility(typeId) {
            const cfg = CPC_TYPE_CONFIG[typeId] || {};
            document.querySelectorAll('[data-cpc-field]').forEach(function (el) {
                const visible = cfg[el.dataset.cpcField] !== false;
                el.style.display = visible ? '' : 'none';
                el.querySelectorAll('[data-cpc-was-required="1"]').forEach(function (inp) {
                    inp.required = visible;
                });
            });
        }

        // ===== Customizer: apply label / required / max-length overrides =====
        const CUZ_OVERRIDES = <?= json_encode($cuz_override_obj) ?>;

        function applyCustomizerOverrides() {
            document.querySelectorAll('[data-cpc-field]').forEach(function (el) {
                const key = el.dataset.cpcField;
                const ov = CUZ_OVERRIDES[key];
                if (!ov) return;
                const label = el.querySelector('.form-label');
                if (label && ov.label) label.firstChild.textContent = ov.label + ' ';

                // Required / optional: update inputs in this group.
                if (typeof ov.req !== 'undefined') {
                    el.querySelectorAll('input, select, textarea').forEach(function (inp) {
                        if (ov.req) {
                            inp.dataset.cpcWasRequired = '1';
                            inp.required = true;
                        } else {
                            inp.dataset.cpcWasRequired = '1';
                            inp.required = false;
                        }
                        if (ov.max && (inp.type === 'text' || inp.type === 'tel' || inp.type === 'email' || inp.type === 'number')) {
                            inp.maxLength = ov.max;
                        }
                    });
                }
            });
        }

        document.addEventListener('DOMContentLoaded', applyCustomizerOverrides);

        // ===== IRS Schedule C business categories (industry header -> activity) =====
        const BUSINESS_CATEGORIES = {
  "Accommodation & Food Services": {"721100":"Traveler accommodation (including hotels, motels, & bed & breakfast inns)", "721210":"RV (recreational vehicle) parks & recreational camps", "721310":"Rooming & boarding houses, dormitories, & workers' camps", "722300":"Special food services (including food service contractors & caterers)", "722410":"Drinking places (alcoholic beverages)", "722511":"Full-service restaurants", "722513":"Limited-service restaurants", "722514":"Cafeterias, grill buffets, & buffets", "722515":"Snack & nonalcoholic beverage bars"},
  "Administrative, Support & Waste Management": {"561110":"Office administrative services", "561210":"Facilities support (management) services", "561300":"Employment services", "561410":"Document preparation services", "561420":"Telephone call centers (including telephone answering services & telemarketing bureaus)", "561430":"Business service centers (including private mail centers & copy shops)", "561440":"Collection agencies", "561450":"Credit bureaus", "561490":"Other business support services (including repossession services, court reporting, & stenotype services)", "561500":"Travel arrangement & reservation services", "561600":"Investigation & security services", "561710":"Exterminating & pest control services", "561720":"Janitorial services", "561730":"Landscaping services", "561740":"Carpet & upholstery cleaning services", "561790":"Other services to buildings & dwellings", "561900":"Other support services (including packaging & labeling services, & convention & trade show organizers)", "562000":"Waste management & remediation services"},
  "Agriculture, Forestry, Hunting & Fishing": {"112900":"Animal production (including breeding of cats and dogs)", "113000":"Forestry & logging (including forest nurseries & timber tracts)", "114110":"Fishing", "114210":"Hunting & trapping", "115110":"Support activities for crop production (including cotton ginning, soil preparation, planting, & cultivating)", "115210":"Support activities for animal production (including farriers)", "115310":"Support activities for forestry"},
  "Arts, Entertainment & Recreation": {"711100":"Performing arts companies", "711210":"Spectator sports (including professional sports clubs & racetrack operations)", "711300":"Promoters of performing arts, sports, & similar events", "711410":"Agents & managers for artists, athletes, entertainers, & other public figures", "711510":"Independent artists, writers, & performers", "712100":"Museums, historical sites, & similar institutions", "713100":"Amusement parks & arcades", "713200":"Gambling industries", "713900":"Other amusement & recreation services (including golf courses, skiing facilities, marinas, fitness centers, bowling centers, skating rinks, miniature golf courses)"},
  "Construction": {"236100":"Residential building construction", "236200":"Nonresidential building construction", "237100":"Utility system construction", "237210":"Land subdivision", "237310":"Highway, street, & bridge construction", "237990":"Other heavy & civil engineering construction", "238110":"Poured concrete foundation & structure contractors", "238120":"Structural steel & precast concrete construction contractors", "238130":"Framing carpentry contractors", "238140":"Masonry contractors", "238150":"Glass & glazing contractors", "238160":"Roofing contractors", "238170":"Siding contractors", "238190":"Other foundation, structure, & building exterior contractors", "238210":"Electrical contractors", "238220":"Plumbing, heating, & air-conditioning contractors", "238290":"Other building equipment contractors", "238310":"Drywall & insulation contractors", "238320":"Painting & wall covering contractors", "238330":"Flooring contractors", "238340":"Tile & terrazzo contractors", "238350":"Finish carpentry contractors", "238390":"Other building finishing contractors", "238910":"Site preparation contractors", "238990":"All other specialty trade contractors"},
  "Educational Services": {"611000":"Educational services (including schools, colleges, & universities)"},
  "Finance & Insurance": {"522100":"Depository credit intermediation (including commercial banking, savings institutions, & credit unions)", "522200":"Nondepository credit intermediation (including sales financing & consumer lending)", "522300":"Activities related to credit intermediation (including loan brokers)", "523150":"Investment banking & securities intermediation", "523160":"Commodity contracts intermediation", "523210":"Securities & commodity exchanges", "523900":"Other financial investment activities (including investment advice)", "524210":"Insurance agencies & brokerages", "524290":"Other insurance related activities"},
  "Health Care & Social Assistance": {"621111":"Offices of physicians (except mental health specialists)", "621112":"Offices of physicians, mental health specialists", "621210":"Offices of dentists", "621310":"Offices of chiropractors", "621320":"Offices of optometrists", "621330":"Offices of mental health practitioners (except physicians)", "621340":"Offices of physical therapists, occupational & speech therapists, & audiologists", "621391":"Offices of podiatrists", "621399":"Offices of all other miscellaneous health practitioners", "621400":"Outpatient care centers", "621510":"Medical & diagnostic laboratories", "621610":"Home health care services", "621900":"Other ambulatory health care services (including ambulance services, blood banks, & organ banks)", "622000":"Hospitals", "623000":"Nursing & residential care facilities", "624100":"Individual & family services", "624200":"Community food & housing & emergency & other relief services", "624310":"Vocational rehabilitation services", "624410":"Childcare services"},
  "Information & Publishing": {"512100":"Motion picture & video industries (except video rental)", "512200":"Sound recording industries", "513000":"Publishing industries", "516000":"Broadcasting & content providers", "517000":"Telecommunications (including wired, wireless, satellite, cable, resellers, & internet service providers)", "518210":"Computing infrastructure providers, data processing, web hosting, & related services", "519200":"Web search portals, libraries, archives, & other info. services"},
  "Manufacturing": {"311110":"Animal food mfg.", "311200":"Grain & oilseed milling", "311300":"Sugar & confectionery product mfg.", "311400":"Fruit & vegetable preserving & speciality food mfg.", "311500":"Dairy product mfg.", "311610":"Animal slaughtering & processing", "311710":"Seafood product preparation & packaging", "311800":"Bakeries & tortilla & dry pasta mfg.", "311900":"Other food mfg. (including coffee, tea, flavorings, & seasonings)", "312000":"Beverage & tobacco product mfg.", "313000":"Textile mills", "314000":"Textile product mills", "315000":"Apparel mfg.", "316110":"Leather & hide tanning & finishing", "316210":"Footwear mfg. (including leather, rubber, & plastics)", "316990":"Other leather & allied product mfg.", "321000":"Wood product mfg.", "322000":"Paper mfg.", "323100":"Printing & related support activities", "324100":"Petroleum & coal products mfg.", "325100":"Basic chemical mfg.", "325200":"Resin, synthetic rubber, & artificial & synthetic fibers & filaments mfg.", "325300":"Pesticide, fertilizer, & other agricultural chemical mfg.", "325410":"Pharmaceutical & medicine mfg.", "325500":"Paint, coating, & adhesive mfg.", "325600":"Soap, cleaning compound, & toilet preparation mfg.", "325900":"Other chemical product & preparation mfg.", "326000":"Plastics & rubber products mfg.", "327100":"Clay product & refractory mfg.", "327210":"Glass & glass product mfg.", "327300":"Cement & concrete product mfg.", "327400":"Lime & gypsum product mfg.", "327900":"Other nonmetallic mineral product mfg.", "331000":"Primary metal mfg.", "332000":"Fabricated metal product mfg.", "333000":"Machinery mfg.", "334000":"Computer & electronic product mfg.", "335000":"Electrical equipment, appliance, & component mfg.", "336000":"Transportation equipment mfg.", "337000":"Furniture & related product mfg.", "339110":"Medical equipment & supplies mfg.", "339900":"Other miscellaneous mfg."},
  "Mining & Extraction": {"211120":"Crude petroleum extraction", "211130":"Natural gas extraction", "212110":"Coal mining", "212200":"Metal ore mining", "212300":"Nonmetallic mineral mining & quarrying", "213110":"Support activities for mining"},
  "Professional, Scientific & Technical Services": {"541100":"Legal services", "541211":"Offices of certified public accountants", "541213":"Tax preparation services", "541214":"Payroll services", "541219":"Other accounting services", "541310":"Architectural services", "541320":"Landscape architecture services", "541330":"Engineering services", "541340":"Drafting services", "541350":"Building inspection services", "541360":"Geophysical surveying & mapping services", "541370":"Surveying & mapping (except geophysical) services", "541380":"Testing laboratories & services", "541400":"Specialized design services (including interior, industrial, graphic, & fashion design)", "541510":"Computer systems design & related services", "541600":"Management, scientific, & technical consulting services", "541700":"Scientific research & development services", "541800":"Advertising, public relations, & related services", "541910":"Market research & public opinion polling", "541920":"Photographic services", "541930":"Translation & interpretation services", "541940":"Veterinary services", "541990":"All other professional, scientific, & technical services"},
  "Real Estate, Rental & Leasing": {"531100":"Lessors of real estate (including miniwarehouses & self-storage units)", "531210":"Offices of real estate agents & brokers", "531310":"Real estate property managers", "531320":"Offices of real estate appraisers", "531390":"Other activities related to real estate", "532100":"Automotive equipment rental & leasing", "532210":"Consumer electronics & appliances rental", "532281":"Formal wear & costume rental", "532282":"Video tape & disc rental", "532283":"Home health equipment rental", "532284":"Recreational goods rental", "532289":"Other consumer goods rental", "532310":"General rental centers", "532400":"Commercial & industrial machinery & equipment rental & leasing"},
  "Retail Trade": {"441110":"New car dealers", "441120":"Used car dealers", "441210":"Recreational vehicle dealers (including motor home & travel trailer dealers)", "441222":"Boat dealers", "441227":"Motorcycle, ATV, & all other motor vehicle dealers", "441300":"Automotive parts, accessories, & tire retailers", "444110":"Home centers", "444120":"Paint & wallpaper retailers", "444140":"Hardware retailers", "444180":"Other building materials dealers", "444200":"Lawn & garden equipment & supplies retailers", "445100":"Grocery & convenience retailers", "445132":"Vending machine operators", "445230":"Fruit & vegetable retailers", "445240":"Meat retailers", "445250":"Fish & seafood retailers", "445290":"Other specialty food retailers", "445320":"Beer, wine, & liquor retailers", "446110":"Pharmacies & drug retailers", "446120":"Cosmetics, beauty supplies, & perfume retailers", "446130":"Optical goods retailers", "446190":"Other health & personal care retailers", "449110":"Furniture retailers", "449120":"Home furnishings retailers", "449210":"Electronics & appliance retailers (including computers)", "455000":"General merchandise retailers", "457100":"Gasoline stations (including convenience stores with gas)", "457210":"Fuel dealers (including heating oil & liquefied petroleum)", "458110":"Clothing & clothing accessories retailers", "458210":"Shoe retailers", "458310":"Jewelry retailers", "458320":"Luggage & leather goods retailers", "459110":"Sporting goods retailers", "459120":"Hobby, toy, & game retailers", "459130":"Sewing, needlework, & piece goods retailers", "459140":"Musical instrument, & supplies retailers", "459210":"Book retailers & news dealers (including newsstands)", "459310":"Florists", "459410":"Office supplies & stationery retailers", "459420":"Gift, novelty, & souvenir retailers", "459510":"Used merchandise retailers", "459910":"Pet & pet supplies retailers", "459920":"Art dealers", "459930":"Manufactured (mobile) home dealers", "459990":"All other miscellaneous retailers (including tobacco, candle, & trophy retailers)"},
  "Transportation & Warehousing": {"481000":"Air transportation", "482110":"Rail transportation", "483000":"Water transportation", "484110":"General freight trucking, local", "484120":"General freight trucking, long distance", "484200":"Specialized freight trucking (including household moving vans)", "485110":"Urban transit systems", "485210":"Interurban & rural bus transportation", "485300":"Taxi, limousine, & ridesharing service", "485410":"School & employee bus transportation", "485510":"Charter bus industry", "485990":"Other transit & ground passenger transportation", "486000":"Pipeline transportation", "487000":"Scenic & sightseeing transportation", "488000":"Support activities for transportation (including motor vehicle towing)", "492000":"Couriers & messengers", "493100":"Warehousing & storage (except leases of miniwarehouses & self-storage units)"},
  "Wholesale Trade": {"423100":"Motor vehicle & motor vehicle parts & supplies", "423200":"Furniture & home furnishing", "423300":"Lumber & other construction materials", "423400":"Professional & commercial equipment & supplies", "423500":"Metal & mineral (except petroleum)", "423600":"Household appliances & electrical & electronic goods", "423700":"Hardware & plumbing & heating equipment & supplies", "423800":"Machinery, equipment, & supplies", "423910":"Sporting & recreational goods & supplies", "423920":"Toy & hobby goods & supplies", "423930":"Recyclable materials", "423940":"Jewelry, watches, precious stones, & precious metals", "423990":"Other miscellaneous durable goods", "424100":"Paper & paper products", "424210":"Drugs & druggists' sundries", "424300":"Apparel, piece goods, & notions", "424400":"Grocery & related products", "424500":"Farm product raw materials", "424600":"Chemical & allied products", "424700":"Petroleum & petroleum products", "424800":"Beer, wine, & distilled alcoholic beverages", "424910":"Farm supplies", "424920":"Books, periodicals, & newspapers", "424930":"Flower, nursery stock, & florists' supplies", "424940":"Tobacco products & electronic cigarettes", "424950":"Paint, varnish, & supplies", "424990":"Other miscellaneous nondurable goods", "425120":"Wholesale trade agents & brokers"},
  "Other Services, Utilities & Unclassified": {"221000":"Utilities", "811110":"Automotive mechanical & electrical repair & maintenance", "811120":"Automotive body, paint, interior, & glass repair", "811190":"Other automotive repair & maintenance (including oil change, lubrication shops, & car washes)", "811210":"Electronic & precision equipment repair & maintenance", "811310":"Commercial & industrial machinery & equipment (except automotive & electronic) repair & maintenance", "811410":"Home & garden equipment & appliance repair & maintenance", "811420":"Reupholstery & furniture repair", "811430":"Footwear & leather goods repair", "811490":"Other personal & household goods repair & maintenance", "812111":"Barber shops", "812112":"Beauty salons", "812113":"Nail salons", "812190":"Other personal care services (including diet & weight reducing centers)", "812210":"Funeral homes & funeral services", "812220":"Cemeteries & crematories", "812310":"Coin-operated laundries & drycleaners", "812320":"Drycleaning & laundry services (except coin-operated)", "812330":"Linen & uniform supply", "812910":"Pet care (except veterinary) services", "812920":"Photofinishing", "812930":"Parking lots & garages", "812990":"All other personal services", "813000":"Religious, grantmaking, civic, professional, & similar organizations", "999000":"Unclassified establishments (unable to classify)"}
        };

        // Single searchable combobox for industry + business activity: click/focus
        // browses the full list collapsed by industry (click a header to toggle
        // its activities); typing filters both the industry name and the
        // activity code/label at once. Selecting an activity fills the hidden
        // category_group/category_code/category_label fields the backend expects.
        function initCatCombo(searchId, panelId, groupId, codeId) {
            const input = document.getElementById(searchId);
            const panel = document.getElementById(panelId);
            const groupField = document.getElementById(groupId);
            const codeField = document.getElementById(codeId);
            const labelField = document.getElementById(codeId + '_label');
            if (!input || !panel || !groupField || !codeField) return;

            const noResults = document.createElement('div');
            noResults.className = 'cat-empty';
            noResults.textContent = 'No matching industry or business activity found.';
            noResults.style.display = 'none';

            const groups = [];
            Object.keys(BUSINESS_CATEGORIES).forEach(function (header) {
                const groupEl = document.createElement('div');
                groupEl.className = 'cat-group';

                const headerEl = document.createElement('div');
                headerEl.className = 'cat-group-header';
                headerEl.textContent = header;
                groupEl.appendChild(headerEl);

                const listEl = document.createElement('div');
                listEl.className = 'cat-activity-list';

                const items = [];
                const acts = BUSINESS_CATEGORIES[header];
                Object.keys(acts).forEach(function (code) {
                    const label = acts[code];
                    const itemEl = document.createElement('div');
                    itemEl.className = 'cat-activity-item';
                    itemEl.textContent = code + ' - ' + label;
                    itemEl.addEventListener('click', function (e) {
                        e.stopPropagation();
                        select(header, code, label);
                    });
                    listEl.appendChild(itemEl);
                    items.push({ code: code, label: label, el: itemEl });
                });
                groupEl.appendChild(listEl);
                panel.appendChild(groupEl);

                headerEl.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (groupEl.classList.contains('expanded')) collapse(groupEl); else expand(groupEl);
                });

                groups.push({ header: header, groupEl: groupEl, items: items });
            });
            panel.appendChild(noResults);

            function expand(groupEl) { groupEl.classList.add('expanded'); }
            function collapse(groupEl) { groupEl.classList.remove('expanded'); }

            function select(header, code, label) {
                groupField.value = header;
                codeField.value = code;
                if (labelField) labelField.value = label;
                input.value = header + ' — ' + code + ' - ' + label;
                close();
            }

            function open() { panel.style.display = 'block'; }
            function close() { panel.style.display = 'none'; }

            // Default browse view: every industry collapsed, click to expand.
            function showAll() {
                noResults.style.display = 'none';
                groups.forEach(function (g) {
                    g.groupEl.style.display = '';
                    g.items.forEach(function (it) { it.el.style.display = ''; });
                    collapse(g.groupEl);
                });
                open();
            }

            // Search view: matches the industry name OR any activity code/label.
            function filter() {
                const q = input.value.trim().toLowerCase();
                if (!q) { showAll(); return; }
                let anyGroupMatch = false;
                groups.forEach(function (g) {
                    const headerMatch = g.header.toLowerCase().includes(q);
                    let anyItemMatch = false;
                    g.items.forEach(function (it) {
                        const match = headerMatch || (it.code + ' ' + it.label).toLowerCase().includes(q);
                        it.el.style.display = match ? '' : 'none';
                        if (match) anyItemMatch = true;
                    });
                    g.groupEl.style.display = anyItemMatch ? '' : 'none';
                    if (anyItemMatch) { expand(g.groupEl); anyGroupMatch = true; } else collapse(g.groupEl);
                });
                noResults.style.display = anyGroupMatch ? 'none' : 'block';
                open();
            }

            input.addEventListener('focus', function () { input.select(); showAll(); });
            input.addEventListener('click', function () { if (panel.style.display !== 'block') showAll(); });
            input.addEventListener('input', function () {
                groupField.value = '';
                codeField.value = '';
                if (labelField) labelField.value = '';
                filter();
            });
            input.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
            document.addEventListener('click', function (e) {
                if (!panel.contains(e.target) && e.target !== input) close();
            });

            // Restore a previously stored selection (edit page): the header is
            // recovered from the stored NAICS code when only the code is known.
            const wantCode = codeField.getAttribute('data-selected') || '';
            let wantGroup = groupField.getAttribute('data-selected') || '';
            if (!wantGroup && wantCode) {
                for (const header in BUSINESS_CATEGORIES) {
                    if (BUSINESS_CATEGORIES[header][wantCode]) { wantGroup = header; break; }
                }
            }
            if (wantGroup && wantCode && BUSINESS_CATEGORIES[wantGroup] && BUSINESS_CATEGORIES[wantGroup][wantCode]) {
                select(wantGroup, wantCode, BUSINESS_CATEGORIES[wantGroup][wantCode]);
            }
        }

        function toggleForms(event) {
            const selectedType = event.target.value;
            const fullForm = document.getElementById('fullForm');
            const simplifiedForm = document.getElementById('simplifiedForm');
            const cfg = CPC_TYPE_CONFIG[selectedType];
            const isFullType = cfg ? (cfg.category !== false) : true;

            if (!isFullType) {
                fullForm.style.display = 'none';
                simplifiedForm.style.display = 'block';
                document.getElementById('membertype_simple').value = selectedType;
                document.getElementById('ach_payer_simple').value = document.getElementById('ach_payer').value;
            } else {
                fullForm.style.display = 'block';
                simplifiedForm.style.display = 'none';
                document.getElementById('membertype').value = selectedType;
                document.getElementById('ach_payer').value = document.getElementById('ach_payer_simple').value;
            }
            applyCpcVisibility(selectedType);
        }

        document.getElementById('membertype').addEventListener('change', toggleForms);
        document.getElementById('membertype_simple').addEventListener('change', toggleForms);

        // Initialise both combined industry/activity pickers (full + simplified forms)
        initCatCombo('category_search', 'category_search_panel', 'category_group', 'category_code');
        initCatCombo('category_search_simple', 'category_search_panel_simple', 'category_group_simple', 'category_code_simple');

        // Same address checkbox logic for full form
        document.getElementById('sameAddressCheckbox').addEventListener('change', function () {
            if (this.checked) {
                document.getElementById('bstreet').value = document.getElementById('hstreet').value;
                document.getElementById('bcity').value = document.getElementById('hcity').value;
                document.getElementById('bstate').value = document.getElementById('hstate').value;
                document.getElementById('bzip').value = document.getElementById('hzip').value;
                document.getElementById('bcounty').value = document.getElementById('hcounty').value;
            } else {
                document.getElementById('bstreet').value = '';
                document.getElementById('bcity').value = '';
                document.getElementById('bstate').value = '';
                document.getElementById('bzip').value = '';
                document.getElementById('bcounty').value = '';
            }
        });

        // SSN formatting
        ['ssn', 'ssn_simple'].forEach(id => {
            document.getElementById(id).addEventListener('input', function (event) {
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

        // Phone formatting
        ['phonecell', 'phonecell_simple', 'phonework', 'phoneother'].forEach(id => {
            if (document.getElementById(id)) {
                document.getElementById(id).addEventListener('input', function (e) {
                    let phone = e.target.value.replace(/\D/g, '');
                    if (phone.length > 3 && phone.length <= 6) {
                        phone = phone.substring(0, 3) + '-' + phone.substring(3);
                    } else if (phone.length > 6) {
                        phone = phone.substring(0, 3) + '-' + phone.substring(3, 6) + '-' + phone.substring(6, 10);
                    }
                    e.target.value = phone;
                });
            }
        });

        // EIN formatting
        document.getElementById('ein').addEventListener('input', function (event) {
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

        // Zip code formatting
        ['hzip', 'bzip', 'hzip_simple'].forEach(id => {
            if (document.getElementById(id)) {
                document.getElementById(id).addEventListener('input', function () {
                    let zip = this.value.replace(/\D/g, '');
                    if (zip.length > 5) {
                        zip = zip.substring(0, 5) + '-' + zip.substring(5, 9);
                    }
                    this.value = zip;
                });
            }
        });

        // Handle form submission with SweetAlerts
        function handleFormSubmit(formId) {
            const form = document.getElementById(formId);

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                if (formId === 'fullForm' && !document.getElementById('category_code').value) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Information',
                        text: 'Please select an Industry Category and Business Activity.',
                        confirmButtonColor: '#3085d6'
                    });
                    document.getElementById('category_search').focus();
                    return;
                }

                const formData = new FormData(form);
                const submitBtn = form.querySelector('input[type="submit"]');
                const originalBtnText = submitBtn.value;

                // Show loading state
                submitBtn.value = 'Adding...';
                submitBtn.disabled = true;

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: data.message,
                                confirmButtonColor: '#3085d6',
                                confirmButtonText: 'OK'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'client_details.php?id=' + data.client_id + '&status=1';
                                }
                            });
                        } else if (data.duplicate) {
                            // Show duplicate client information with options
                            const clientInfo = data.client_info;
                            const clientInfoHtml = `
                            <div class="client-info">
                            
                            <div class="client-info-row">
                            <div class="client-info-label">Client ID:</div>
                            <div class="client-info-value"><a href="client_details.php?id=${clientInfo.id}&status=${clientInfo.status}" target="_blank">${clientInfo.id}</a></div>
                            </div>
                                <div class="client-info-row">
                                    <div class="client-info-label">Name:</div>
                                    <div class="client-info-value">${clientInfo.name}</div>
                                </div>
                                <div class="client-info-row">
                                    <div class="client-info-label">Type:</div>
                                    <div class="client-info-value">${clientInfo.type}</div>
                                </div>
                                <div class="client-info-row">
                                    <div class="client-info-label">Phone:</div>
                                    <div class="client-info-value">${clientInfo.phone}</div>
                                </div>
                                <div class="client-info-row">
                                    <div class="client-info-label">Email:</div>
                                    <div class="client-info-value">${clientInfo.email}</div>
                                </div>
                                ${clientInfo.business ? `
                                <div class="client-info-row">
                                    <div class="client-info-label">Business:</div>
                                    <div class="client-info-value">${clientInfo.business}</div>
                                </div>` : ''}
                                <div class="client-info-row">
                                    <div class="client-info-label">Status:</div>
                                    <div class="client-info-value">${clientInfo.status == 1 ? 'Active' : 'Inactive'}</div>
                                </div>
                            </div>
                        `;

                            Swal.fire({
                                title: 'Duplicate Client Found',
                                html: clientInfoHtml,
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Add Anyway',
                                cancelButtonText: 'Cancel'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    // Set bypass flag and resubmit
                                    document.getElementById(formId === 'fullForm' ? 'bypass_duplicate' : 'bypass_duplicate_simple').value = '1';
                                    form.dispatchEvent(new Event('submit'));
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                html: data.message,
                                confirmButtonColor: '#d33',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An unexpected error occurred. Please try again.',
                            confirmButtonColor: '#d33',
                            confirmButtonText: 'OK'
                        });
                    })
                    .finally(() => {
                        // Reset button state
                        submitBtn.value = originalBtnText;
                        submitBtn.disabled = false;
                    });
            });
        }

        // Initialize form handlers
        handleFormSubmit('fullForm');
        handleFormSubmit('simplifiedForm');

        // Image preview
        function setupImagePreview(inputId, imgId) {
            const input = document.getElementById(inputId);
            const img = document.getElementById(imgId);

            if (input && img) {
                input.addEventListener('change', function () {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            img.src = e.target.result;
                            img.style.display = 'block';
                        }
                        reader.readAsDataURL(file);
                    }
                });
            }
        }

        setupImagePreview('pro_image', 'image');
        setupImagePreview('pro_image_simple', 'image_simple');
    </script>
</body>

</html>