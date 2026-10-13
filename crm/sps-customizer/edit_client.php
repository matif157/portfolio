<?php
include "includes/db.php";
include "includes/auth.php";
include "includes/activity_log.php";
include "client_type_manual.php";
include "includes/customizer.php";

$client_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
 
if (isset($_POST['update_client'])) {
    $msg = '';
    $pic = '';

    $specialClient = $_POST['specialClient'];
    $membertype = $_POST['membertype'];
    // Types with Category hidden in the Client Type Manual carry no category.
    // Business clients submit the IRS Schedule C cascade (industry header +
    // NAICS code + label). When no new selection is submitted, keep whatever is
    // stored so editing other fields never wipes it.
    $isFullType = cpc_type_field_config($con, $membertype)['category'] ?? true;
    if (!$isFullType) {
        $category = '';
    } else {
        $cat_group = trim($_POST['category_group'] ?? '');
        $cat_code = trim($_POST['category_code'] ?? '');
        $cat_label = trim($_POST['category_label'] ?? '');
        if ($cat_code !== '' && $cat_group !== '' && $cat_label !== '') {
            $category = mysqli_real_escape_string($con, "$cat_group - $cat_label ($cat_code)");
        } else {
            // No new selection — keep whatever is already stored.
            $cid_esc = mysqli_real_escape_string($con, $client_id);
            $ex = mysqli_fetch_assoc(mysqli_query($con, "SELECT category FROM members WHERE member_id='$cid_esc'"));
            $category = mysqli_real_escape_string($con, $ex['category'] ?? '');
        }
    }
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $mname = $_POST['mname'];
    $lname = $_POST['lname'];
    $file = $_POST['file'];
    $ssn = $_POST['ssn'];
    $dba = isset($_POST['dba']) ? $_POST['dba'] : '';
    $ein = isset($_POST['ein']) ? $_POST['ein'] : '';
    $tfnumber = isset($_POST['tfnumber']) ? $_POST['tfnumber'] : '';
    $dob = $_POST['dob'];
    $phonework = isset($_POST['phonework']) ? $_POST['phonework'] : '';
    $client_since = !empty($_POST['client_since']) && strtotime($_POST['client_since']) !== false ? date('Y-m-d', strtotime($_POST['client_since'])) : date('Y-m-d');

    $phonecell = $_POST['phonecell'];
    $phoneother = isset($_POST['phoneother']) ? $_POST['phoneother'] : '';
    $phoneoverseas = isset($_POST['phoneoverseas']) ? $_POST['phoneoverseas'] : '';
    $fax = isset($_POST['fax']) ? $_POST['fax'] : '';
    $pemail = $_POST['pemail']; 
    $semail = isset($_POST['semail']) ? $_POST['semail'] : '';
    $hstate = $_POST['hstate'];
    $hcity = $_POST['hcity'];
    $hstreet = $_POST['hstreet'];
    $hzip = $_POST['hzip'];
    $hcounty = $_POST['hcounty'];
    $bstate = isset($_POST['bstate']) ? $_POST['bstate'] : '';
    $bcity = isset($_POST['bcity']) ? $_POST['bcity'] : '';
    $bstreet = isset($_POST['bstreet']) ? $_POST['bstreet'] : '';
    $bzip = isset($_POST['bzip']) ? $_POST['bzip'] : '';
    $bcounty = isset($_POST['bcounty']) ? $_POST['bcounty'] : '';
    $bus_name = isset($_POST['bus_name']) ? mysqli_real_escape_string($con, $_POST['bus_name']) : '';
    
    $member_pos = isset($_POST['member_pos']) ? mysqli_real_escape_string($con, $_POST['member_pos']) : '';
    // NEW – capture the drop-down
    $payer_frequency = isset($_POST['payer_frequency']) && in_array($_POST['payer_frequency'], ['None','Monthly','Quarterly']) ? $_POST['payer_frequency'] : 'None';

    $incorp_date = !empty($_POST['incorp_date']) && strtotime($_POST['incorp_date']) !== false ? date('Y-m-d', strtotime($_POST['incorp_date'])) : date('Y-m-d');
    $bus_start_date = !empty($_POST['bus_start_date']) && strtotime($_POST['bus_start_date']) !== false ? date('Y-m-d', strtotime($_POST['bus_start_date'])) : date('Y-m-d');
    $ach_payer = isset($_POST['ach_payer']) ? mysqli_real_escape_string($con, $_POST['ach_payer']) : '0';
    $address = $bstreet; // Map bstreet to address for consistency
    $businesssaddress = $bstreet; // Map bstreet to business_address for consistency

    // Uploading Featured Image
    $pic_name = $_FILES['post_image']['name']; 
    $pic_temp = $_FILES['post_image']['tmp_name'];
    $pic_size = $_FILES['post_image']['size'];

    $pic_new = rand(100, 1000) . '-' . $pic_name;
    $pic_store = "images/clients/" . $pic_new;

    if (move_uploaded_file($pic_temp, $pic_store)) {
        $pic = $pic_new;
    }
    $preimg = $_POST['preimg'];

    if ($pic == '') {
        $pic = $preimg;
    }

    // Adjust phonework for simplified (non-full) member types
    $phone_work_value = $isFullType ? $phonework : '';

    $queryy = "UPDATE members SET 
    file='$file', name='$name', dba='$dba', ein='$ein', ss='$ssn', tf='$tfnumber', 
    dob='$dob', phone='$phone_work_value', cell_ph='$phonecell', other_ph='$phoneother', 
    overseas_ph='$phoneoverseas', prm_email='$pemail', sec_email='$semail', 
    address='$address', fax='$fax', mem_pic='$pic', type='$membertype', 
    mname='$mname', lname='$lname', business_address='$businesssaddress', 
    hstate='$hstate', hcity='$hcity', hstreet='$hstreet', hzip='$hzip', hcounty='$hcounty',
    state='$bstate', city='$bcity', address='$bstreet', zip='$bzip', bcounty='$bcounty',
    bus_name='$bus_name', member_pos='$member_pos', specialClient='$specialClient', client_since='$client_since', 
    incorp_date='$incorp_date', bus_start_date='$bus_start_date', 
    user_id='$user_id', category='$category', ach_payer='$ach_payer' ,payer_frequency='$payer_frequency'
    WHERE member_id='$client_id'";

    $insert_query = mysqli_query($con, $queryy);
    if ($insert_query) {
        $client_full_name = trim($name . ' ' . $lname);
        $log_description = "Updated client: " . $client_full_name . " (ID: " . $client_id . ")";
        logActivity($con, $user_id, 'UPDATE', 'member', (int)$client_id, $log_description);

        // Customizer: save any custom field values submitted and audit the update.
        $custFields = customizer_custom_fields($con, 'clients');
        if (!empty($custFields)) {
            $custVals = customizer_extract_custom_values($custFields);
            customizer_save_record_values($con, 'clients', (int) $client_id, $custVals, (int) $user_id, $_SESSION['fullname'] ?? '');
        }
        customizer_audit($con, 'clients', (int) $client_id, 'name', 'Client Record', 'updated', (int) $user_id, $_SESSION['fullname'] ?? '', null, $client_full_name);

        $_SESSION['msg'] = "<div class='alert alert-success'>Client Has Been Updated Successfully.</div>";
        $status_row = mysqli_fetch_assoc(mysqli_query($con, "SELECT status FROM members WHERE member_id='$client_id'"));
        header("Location: client_details.php?id=" . $client_id . "&status=" . ($status_row['status'] ?? ''));
        exit;
    } else {
        $msg .= "<div class='alert alert-danger'>Something Went Wrong: " . $con->error . "</div>";
    }
}

// Client Type Manual field-visibility config for every type, embedded below so
// the form can show/hide fields per selected type without a round trip.
$cpcConfigByType = cpc_all_types_field_config($con);

/* Customizer: field overrides (label / required / max_length) applied to the
   form's system inputs on load, plus the existing custom field values. */
$cuz_override_obj = array();
$cuz_overrides = customizer_fields_for_module($con, 'clients');
foreach ($cuz_overrides as $ck => $cf) {
    if ($cf['is_system']) {
        $cuz_override_obj[$ck] = array(
            'label'   => $cf['label'],
            'req'     => (int) $cf['is_required'],
            'max'     => (int) $cf['max_length'],
        );
    }
}
$cuz_existing_values = is_numeric($client_id) ? customizer_record_values($con, 'clients', (int) $client_id) : array();
?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
    <title>Syed Professional Services - Dashboard</title>
    <?php include "includes/links.php"; ?>
    <style>
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
                                <li class="breadcrumb-item">Edit Client</li>
                            </ul>
                        </div>
                        <div class="col">
                        </div>
                    </div>
                </div>
            </div> 
            <?php
            $profile_query = mysqli_query($con, "SELECT * FROM members WHERE member_id='$client_id'");
            while ($fetch = mysqli_fetch_array($profile_query)) {
                $type = $fetch['type'];
                $cpcIsFullType = cpc_type_field_config($con, $type)['category'] ?? true;
                $category = $fetch['category'];
                // Recover the NAICS code from the stored "Header - Label (code)" value so
                // the picker can pre-select on edit (the header is derived from it in JS).
                $cat_code = '';
                if (preg_match('/\((\d{6})\)\s*$/', (string) $category, $cat_m)) {
                    $cat_code = $cat_m[1];
                }
                $member_pos    = $fetch['member_pos'];
                ?>
                <?php if (isset($msg)) {
                    echo $msg;
                } ?>
                <!-- Full Form -->
                <form method="POST" enctype="multipart/form-data" id="fullForm"
                    style="<?php echo !$cpcIsFullType ? 'display: none;' : 'display: block;'; ?>">
                    <div class="row mb-2">
                        <div class="col-md-4">
                            <label for="membertype" class="form-label">Type</label><span class="text-danger">*</span>
                            <select class="form-control select" required="required" id="membertype" name="membertype">
                                <option value="" selected="selected">Select Type</option>
                                <?php
                                $querr = mysqli_query($con, "SELECT * FROM member_type");
                                while ($genetics = mysqli_fetch_array($querr)) { ?>
                                    <option value="<?php echo $genetics['type_id']; ?>" <?php if ($type == $genetics['type_id'])
                                           echo "selected"; ?>>
                                        <?php echo $genetics['type_name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="specialClient" class="form-label">Sympathetic Client</label><span
                                    class="text-danger">*</span>
                                <select class="form-control" required="required" name="specialClient" id="specialClient">
                                    <option value="1" <?php if ($fetch['specialClient'] == 1)
                                        echo 'selected'; ?>>Yes</option>
                                    <option value="0" <?php if ($fetch['specialClient'] == 0)
                                        echo 'selected'; ?>>No</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="ach_payer" class="form-label">ACH Payer</label><span class="text-danger">*</span>
                                <select class="form-control select" required="required" name="ach_payer" id="ach_payer">
                                    <option value="" disabled>Select Yes or No</option>
                                    <option value="1" <?php if ($fetch['ach_payer'] == 1) echo 'selected'; ?>>Yes</option>
                                    <option value="0" <?php if ($fetch['ach_payer'] == 0) echo 'selected'; ?>>No</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="payer_frequency" class="form-label">Payer Type</label>
                            <select class="form-control" name="payer_frequency" id="payer_frequency">
                                <option value="None" <?= $fetch['payer_frequency']==='None' ? 'selected' : '' ?>>None</option>
                                <option value="Monthly" <?= $fetch['payer_frequency']==='Monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option value="Quarterly" <?= $fetch['payer_frequency']==='Quarterly' ? 'selected' : '' ?>>Quarterly</option>
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
                                    <input type="hidden" name="category_code" id="category_code" data-selected="<?= htmlspecialchars($cat_code) ?>">
                                    <input type="hidden" name="category_label" id="category_code_label" value="">
                                    <div class="cat-combo-panel" id="category_search_panel"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="name" class="form-label">First Name</label><span class="text-danger">*</span>
                                <input class="form-control" required="required" name="name" type="text"
                                    placeholder="Enter First Name" value="<?php echo $fetch['name'] ?>" id="name"
                                    oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="mname" class="form-label">Middle Name</label>
                                <input class="form-control" name="mname" type="text" placeholder="Enter Middle Name"
                                    value="<?php echo $fetch['mname'] ?>" id="mname"
                                    oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="lname" class="form-label">Last Name</label><span class="text-danger">*</span>
                                <input class="form-control" required="required" name="lname" type="text"
                                    placeholder="Enter Last Name" value="<?php echo $fetch['lname'] ?>" id="lname"
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
                                    placeholder="Enter Street" value="<?php echo $fetch['hstreet'] ?>" id="hstreet">
                            </div>
                        </div>
                        <div class="col-md-3" data-cpc-field="hcity">
                            <div class="form-group">
                                <label for="hcity" class="form-label">City</label><span class="text-danger">*</span>
                                <input class="form-control" required="required" name="hcity" type="text"
                                    placeholder="Enter City" value="<?php echo $fetch['hcity'] ?>" id="hcity">
                            </div>
                        </div>
                        <div class="col-md-3" data-cpc-field="hstate_zip">
                            <div class="form-group">
                                <label for="hstate" class="form-label">State</label><span class="text-danger">*</span>
                                <input class="form-control" required="required" name="hstate" type="text"
                                    placeholder="Enter State" value="<?php echo $fetch['hstate'] ?>" id="hstate">
                            </div>
                        </div>
                        <div class="col-md-3" data-cpc-field="hstate_zip">
                            <div class="form-group">
                                <label for="hzip" class="form-label">Zip</label><span class="text-danger">*</span>
                                <input class="form-control" required="required" name="hzip" type="text"
                                    placeholder="Enter Zip" value="<?php echo $fetch['hzip'] ?>" id="hzip"
                                    oninput="formatZipCode(this)">
                            </div>
                        </div>
                        <div class="col-md-12" data-cpc-field="hcounty">
                            <div class="form-group">
                                <label for="hcounty" class="form-label">County</label>
                                <input class="form-control" name="hcounty" type="text" placeholder="Enter County"
                                    value="<?php echo $fetch['hcounty'] ?>" id="hcounty">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="bus_name">
                            <div class="form-group">
                                <label for="bus_name" class="form-label">Business Name</label>
                                <input class="form-control" name="bus_name" type="text" placeholder="Enter Business Name"
                                    value="<?php echo $fetch['bus_name'] ?>" id="bus_name"
                                   >
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="member_pos">
                            <div class="form-group">
                                <label for="member_pos" class="form-label">Designation</label>
                                <input class="form-control"
                                name="member_pos"
                                id="member_pos"
                                type="text"
                                placeholder="Enter Designation"
                                value="<?php echo htmlspecialchars($member_pos) ?? ''; ?>">
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
                                    value="<?php echo $fetch['address'] ?>" id="bstreet">
                            </div>
                        </div>
                        <div class="col-md-3" data-cpc-field="city">
                            <div class="form-group">
                                <label for="bcity" class="form-label">City</label>
                                <input class="form-control" name="bcity" type="text" placeholder="Enter City"
                                    value="<?php echo $fetch['city'] ?>" id="bcity">
                            </div>
                        </div>
                        <div class="col-md-3" data-cpc-field="state_zip">
                            <div class="form-group">
                                <label for="bstate" class="form-label">State</label>
                                <input class="form-control" name="bstate" type="text" placeholder="Enter State"
                                    value="<?php echo $fetch['state'] ?>" id="bstate">
                            </div>
                        </div>
                        <div class="col-md-3" data-cpc-field="state_zip">
                            <div class="form-group">
                                <label for="bzip" class="form-label">Zip</label>
                                <input class="form-control" name="bzip" type="text" placeholder="Enter Zip"
                                    value="<?php echo $fetch['zip'] ?>" id="bzip" oninput="formatZipCode(this)">
                            </div>
                        </div>
                        <div class="col-md-12" data-cpc-field="bcounty">
                            <div class="form-group">
                                <label for="bcounty" class="form-label">County</label>
                                <input class="form-control" name="bcounty" type="text" placeholder="Enter County"
                                    value="<?php echo $fetch['bcounty'] ?>" id="bcounty">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="file">
                            <div class="form-group">
                                <label for="file" class="form-label">File</label>
                                <input class="form-control" name="file" type="text" value="<?php echo $fetch['file'] ?>"
                                    id="file" placeholder="Enter File No">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="ss">
                            <div class="form-group">
                                <label for="ssn" class="form-label">ITIN/SSN <small>(XXX-XX-XXXX)</small></label><span
                                    class="text-danger">*</span>
                                <input class="form-control" required="required" name="ssn" type="text" placeholder="SSN"
                                    id="ssn" value="<?php echo $fetch['ss'] ?>" maxlength="11">
                            </div>
                        </div>
                        <div class="col-md-4" data-cpc-field="dba">
                            <div class="form-group">
                                <label for="dba" class="form-label">DBA</label>
                                <input class="form-control" name="dba" type="text" placeholder="DBA"
                                    value="<?php echo $fetch['dba'] ?>" id="dba">
                            </div>
                        </div>
                        <div class="col-md-4" data-cpc-field="ein">
                            <div class="form-group">
                                <label for="ein" class="form-label">EIN</label>
                                <input class="form-control" name="ein" type="text" id="ein"
                                    value="<?php echo $fetch['ein'] ?>" placeholder="XX-XXXXXXX" maxlength="10">
                            </div>
                        </div>
                        <div class="col-md-4" data-cpc-field="tf">
                            <div class="form-group">
                                <label for="tfnumber" class="form-label">PMB</label>
                                <input class="form-control" name="tfnumber" type="text" id="tfnumber"
                                    value="<?php echo $fetch['tf'] ?>" placeholder="PMB">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="dob">
                            <div class="form-group">
                                <label for="dob" class="form-label">Date of Birth</label><span class="text-danger">*</span>
                                <input class="form-control" required="required" name="dob" type="date" id="dob"
                                    placeholder="Date of Birth" value="<?php echo $fetch['dob'] ?>">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="phone">
                            <div class="form-group">
                                <label for="phonework" class="form-label">Phone: Work</label>
                                <input class="form-control" name="phonework" type="tel" id="phonework"
                                    placeholder="XXX-XXX-XXXX" value="<?php echo $fetch['phone'] ?>" maxlength="12">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="cell_ph">
                            <div class="form-group">
                                <label for="phonecell" class="form-label">Phone: Cell</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" name="phonecell" type="tel" id="phonecell"
                                    placeholder="XXX-XXX-XXXX" required="required" value="<?php echo $fetch['cell_ph'] ?>"
                                    maxlength="12">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="other_ph">
                            <div class="form-group">
                                <label for="phoneother" class="form-label">Phone: Other</label>
                                <input class="form-control" name="phoneother" type="tel" id="phoneother"
                                    placeholder="XXX-XXX-XXXX" value="<?php echo $fetch['other_ph'] ?>" maxlength="12">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="overseas_ph">
                            <div class="form-group">
                                <label for="phoneoverseas" class="form-label">Phone: Overseas</label>
                                <input class="form-control" name="phoneoverseas" type="tel" id="phoneoverseas"
                                    placeholder="Phone: Overseas" value="<?php echo $fetch['overseas_ph'] ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="fax" class="form-label">Fax</label>
                                <input class="form-control" name="fax" type="text" id="fax" placeholder="Fax"
                                    value="<?php echo $fetch['fax'] ?>">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="prm_email">
                            <div class="form-group">
                                <label for="pemail" class="form-label">Primary Email</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" name="pemail" type="email" id="pemail"
                                    placeholder="Primary Email" required="required"
                                    value="<?php echo $fetch['prm_email'] ?>">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="sec_email">
                            <div class="form-group">
                                <label for="semail" class="form-label">Secondary Email</label>
                                <input class="form-control" name="semail" type="email" id="semail"
                                    placeholder="Secondary Email" value="<?php echo $fetch['sec_email'] ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="client_since" class="form-label">Client Since</label>
                                <input class="form-control" name="client_since" type="date" id="client_since"
                                    value="<?php echo $fetch['client_since'] ?>">
                            </div>
                        </div>
                        <div class="col-md-4" data-cpc-field="incorp_date">
                            <div class="form-group">
                                <label for="incorp_date" class="form-label">Incorporation Date</label>
                                <input class="form-control" name="incorp_date" type="date" id="incorp_date"
                                    value="<?php echo $fetch['incorp_date'] ?>">
                            </div>
                        </div>
                        <div class="col-md-4" data-cpc-field="bus_start_date">
                            <div class="form-group">
                                <label for="bus_start_date" class="form-label">Business Start Date</label>
                                <input class="form-control" name="bus_start_date" type="date" id="bus_start_date"
                                    value="<?php echo $fetch['bus_start_date'] ?>">
                            </div>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="hidden" name="preimg" value="<?php echo $fetch['mem_pic'] ?>">
                            <label for="pro_image" class="form-label">Profile Image</label>
                            <div class="choose-file">
                                <label for="pro_image" class="form-label" style="width: 100%;">
                                    <input type="file" class="form-control" name="post_image" id="pro_image"
                                        data-filename="pro_image_create">
                                    <img id="image" class="mt-3" style="width:25%;"
                                        src="images/clients/<?php echo $fetch['mem_pic'] ?>">
                                </label>
                            </div>
                        </div>
                        <?= customizer_render_custom_fields_block($con, 'clients', $cuz_existing_values) ?>
                        <div class="col-md-6" style="margin-top: 27px !important;">
                            <input type="submit" value="Update Client" name="update_client" class="btn btn-primary"
                                style="width: 100%;">
                        </div>
                    </div>
                </form>
                <!-- Simplified Form -->
                <form method="POST" enctype="multipart/form-data" id="simplifiedForm"
                    style="<?php echo !$cpcIsFullType ? 'display: block;' : 'display: none;'; ?>">
                    <div class="row mb-2">
                        <div class="col-md-4">
                            <label for="membertype_simple" class="form-label">Type</label><span class="text-danger">*</span>
                            <select class="form-control select" required="required" id="membertype_simple"
                                name="membertype">
                                <option value="" selected="selected">Select Type</option>
                                <?php
                                $querr = mysqli_query($con, "SELECT * FROM member_type");
                                while ($genetics = mysqli_fetch_array($querr)) { ?>
                                    <option value="<?php echo $genetics['type_id']; ?>" <?php if ($type == $genetics['type_id'])
                                           echo "selected"; ?>>
                                        <?php echo $genetics['type_name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="specialClient_simple" class="form-label">Sympathetic Client</label><span
                                    class="text-danger">*</span>
                                <select class="form-control" required="required" name="specialClient"
                                    id="specialClient_simple">
                                    <option value="1" <?php if ($fetch['specialClient'] == 1)
                                        echo 'selected'; ?>>Yes</option>
                                    <option value="0" <?php if ($fetch['specialClient'] == 0)
                                        echo 'selected'; ?>>No</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                        <div class="form-group">
                            <label for="ach_payer_simple" class="form-label">ACH Payer</label><span class="text-danger">*</span>
                            <select class="form-control select" required="required" name="ach_payer" id="ach_payer_simple">
                                <option value="" disabled>Select Yes or No</option>
                                <option value="1" <?php if ($fetch['ach_payer'] == 1)
                                    echo 'selected'; ?>>Yes</option>
                                <option value="0" <?php if ($fetch['ach_payer'] == 0)
                                    echo 'selected'; ?>>No</option>
                            </select>
                        </div>
                        </div>
                        <div class="col-md-4">
                        <label for="payer_frequency_simple" class="form-label">Payer Type</label>
                        <select class="form-control"  name="payer_frequency" id="payer_frequency_simple">
                        <option value="None" <?= $fetch['payer_frequency'] === 'None' ? 'selected' : '' ?>>None</option>
                                <option value="Monthly" <?= $fetch['payer_frequency'] === 'Monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option value="Quarterly" <?= $fetch['payer_frequency'] === 'Quarterly' ? 'selected' : '' ?>>Quarterly</option>
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
                                    <input type="hidden" name="category_code" id="category_code_simple" data-selected="<?= htmlspecialchars($cat_code) ?>">
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
                                    placeholder="Enter First Name" value="<?php echo $fetch['name'] ?>" id="name_simple"
                                    oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="mname_simple" class="form-label">Middle Name</label>
                                <input class="form-control" name="mname" type="text" placeholder="Enter Middle Name"
                                    value="<?php echo $fetch['mname'] ?>" id="mname_simple"
                                    oninput="this.value = this.value.replace(/[^a-zA-Z]/g, '')">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="lname_simple" class="form-label">Last Name</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" required="required" name="lname" type="text"
                                    placeholder="Enter Last Name" value="<?php echo $fetch['lname'] ?>" id="lname_simple"
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
                                    placeholder="Enter Street" value="<?php echo $fetch['hstreet'] ?>" id="hstreet_simple">
                            </div>
                        </div>
                        <div class="col-md-3" data-cpc-field="hcity">
                            <div class="form-group">
                                <label for="hcity_simple" class="form-label">City</label><span class="text-danger">*</span>
                                <input class="form-control" required="required" name="hcity" type="text"
                                    placeholder="Enter City" value="<?php echo $fetch['hcity'] ?>" id="hcity_simple">
                            </div>
                        </div>
                        <div class="col-md-3" data-cpc-field="hstate_zip">
                            <div class="form-group">
                                <label for="hstate_simple" class="form-label">State</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" required="required" name="hstate" type="text"
                                    placeholder="Enter State" value="<?php echo $fetch['hstate'] ?>" id="hstate_simple">
                            </div>
                        </div>
                        <div class="col-md-3" data-cpc-field="hstate_zip">
                            <div class="form-group">
                                <label for="hzip_simple" class="form-label">Zip</label><span class="text-danger">*</span>
                                <input class="form-control" required="required" name="hzip" type="text"
                                    placeholder="Enter Zip" value="<?php echo $fetch['hzip'] ?>" id="hzip_simple"
                                    oninput="formatZipCode(this)">
                            </div>
                        </div>
                        <div class="col-md-12" data-cpc-field="hcounty">
                            <div class="form-group">
                                <label for="hcounty_simple" class="form-label">County</label>
                                <input class="form-control" name="hcounty" type="text" placeholder="Enter County"
                                    value="<?php echo $fetch['hcounty'] ?>" id="hcounty_simple">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="file">
                            <div class="form-group">
                                <label for="file_simple" class="form-label">File</label>
                                <input class="form-control" name="file" type="text" value="<?php echo $fetch['file'] ?>"
                                    id="file_simple" placeholder="Enter File No">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="ss">
                            <div class="form-group">
                                <label for="ssn_simple" class="form-label">ITIN/SSN <small>(XXX-XX-XXXX)</small></label><span
                                    class="text-danger">*</span>
                                <input class="form-control" required="required" name="ssn" type="text" placeholder="SSN"
                                    id="ssn_simple" value="<?php echo $fetch['ss'] ?>" maxlength="11">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="dob">
                            <div class="form-group">
                                <label for="dob_simple" class="form-label">Date of Birth</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" required="required" name="dob" type="date" id="dob_simple"
                                    placeholder="Date of Birth" value="<?php echo $fetch['dob'] ?>">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="cell_ph">
                            <div class="form-group">
                                <label for="phonecell_simple" class="form-label">Phone: Cell</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" name="phonecell" type="tel" id="phonecell_simple"
                                    placeholder="XXX-XXX-XXXX" required="required" value="<?php echo $fetch['cell_ph'] ?>"
                                    maxlength="12">
                            </div>
                        </div>
                        <div class="col-md-6" data-cpc-field="prm_email">
                            <div class="form-group">
                                <label for="pemail_simple" class="form-label">Primary Email</label><span
                                    class="text-danger">*</span>
                                <input class="form-control" name="pemail" type="email" id="pemail_simple"
                                    placeholder="Primary Email" required="required"
                                    value="<?php echo $fetch['prm_email'] ?>">
                            </div>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="hidden" name="preimg" value="<?php echo $fetch['mem_pic'] ?>">
                            <label for="pro_image_simple" class="form-label">Profile Image</label>
                            <div class="choose-file">
                                <label for="pro_image_simple" class="form-label" style="width: 100%;">
                                    <input type="file" class="form-control" name="post_image" id="pro_image_simple"
                                        data-filename="pro_image_create_simple">
                                    <img id="image_simple" class="mt-3" style="width:25%;"
                                        src="images/clients/<?php echo $fetch['mem_pic'] ?>">
                                </label>
                            </div>
                        </div>
                        <?= customizer_render_custom_fields_block($con, 'clients', $cuz_existing_values) ?>
                        <div class="col-md-6" style="margin-top: 27px !important;">
                            <input type="submit" value="Update Client" name="update_client" class="btn btn-primary"
                                style="width: 100%;">
                        </div>
                    </div>
                </form>
            <?php } ?>
        </div>
    </div>
    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>
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
                // Copy values from full form to simplified form
                document.getElementById('specialClient_simple').value = document.getElementById('specialClient').value;
                document.getElementById('ach_payer_simple').value = document.getElementById('ach_payer').value;
                document.getElementById('name_simple').value = document.getElementById('name').value;
                document.getElementById('mname_simple').value = document.getElementById('mname').value;
                document.getElementById('lname_simple').value = document.getElementById('lname').value;
                document.getElementById('hstreet_simple').value = document.getElementById('hstreet').value;
                document.getElementById('hcity_simple').value = document.getElementById('hcity').value;
                document.getElementById('hstate_simple').value = document.getElementById('hstate').value;
                document.getElementById('hzip_simple').value = document.getElementById('hzip').value;
                document.getElementById('hcounty_simple').value = document.getElementById('hcounty').value;
                document.getElementById('file_simple').value = document.getElementById('file').value;
                document.getElementById('ssn_simple').value = document.getElementById('ssn').value;
                document.getElementById('dob_simple').value = document.getElementById('dob').value;
                document.getElementById('phonecell_simple').value = document.getElementById('phonecell').value;
                document.getElementById('pemail_simple').value = document.getElementById('pemail').value;
            } else {
                fullForm.style.display = 'block';
                simplifiedForm.style.display = 'none';
                document.getElementById('membertype').value = selectedType;
                // Copy values from simplified form to full form
                document.getElementById('specialClient').value = document.getElementById('specialClient_simple').value;
                document.getElementById('ach_payer').value = document.getElementById('ach_payer_simple').value;
                document.getElementById('name').value = document.getElementById('name_simple').value;
                document.getElementById('mname').value = document.getElementById('mname_simple').value;
                document.getElementById('lname').value = document.getElementById('lname_simple').value;
                document.getElementById('hstreet').value = document.getElementById('hstreet_simple').value;
                document.getElementById('hcity').value = document.getElementById('hcity_simple').value;
                document.getElementById('hstate').value = document.getElementById('hstate_simple').value;
                document.getElementById('hzip').value = document.getElementById('hzip_simple').value;
                document.getElementById('hcounty').value = document.getElementById('hcounty_simple').value;
                document.getElementById('file').value = document.getElementById('file_simple').value;
                document.getElementById('ssn').value = document.getElementById('ssn_simple').value;
                document.getElementById('dob').value = document.getElementById('dob_simple').value;
                document.getElementById('phonecell').value = document.getElementById('phonecell_simple').value;
                document.getElementById('pemail').value = document.getElementById('pemail_simple').value;
            }
            applyCpcVisibility(selectedType);
        }

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
        // Preselects on edit from the data-selected attribute (stored NAICS code).
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

        document.getElementById('membertype').addEventListener('change', toggleForms);
        document.getElementById('membertype_simple').addEventListener('change', toggleForms);

        // Initialise both combined industry/activity pickers (full + simplified forms)
        initCatCombo('category_search', 'category_search_panel', 'category_group', 'category_code');
        initCatCombo('category_search_simple', 'category_search_panel_simple', 'category_group_simple', 'category_code_simple');

        // The category fields are now hidden inputs, so HTML5 "required" no longer
        // blocks submission on its own — enforce it manually for the full form.
        document.getElementById('fullForm').addEventListener('submit', function (e) {
            if (!document.getElementById('category_code').value) {
                e.preventDefault();
                alert('Please select an Industry Category and Business Activity.');
                document.getElementById('category_search').focus();
            }
        });

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

        ['ssn', 'ssn_simple'].forEach(id => {
            if (document.getElementById(id)) {
                document.getElementById(id).addEventListener('input', function (event) {
                    let inputValue = event.target.value.replace(/\D/g, '');
                    let formattedValue = '';
                    if (inputValue.length > 9) {
                        inputValue = inputValue.substr(0, 9);
                    }
                    for (let i = 0; i < inputValue.length; i++) {
                        if (i === 3 || i === 5) {
                            formattedValue += '-';
                        }
                        formattedValue += inputValue[i];
                    }
                    event.target.value = formattedValue;
                });
            }
        });

        ['ein'].forEach(id => {
            if (document.getElementById(id)) {
                document.getElementById(id).addEventListener('input', function (event) {
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
            }
        });

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

        // ['bus_name'].forEach(id => {
        //     if (document.getElementById(id)) {
        //         document.getElementById(id).addEventListener('input', function () {
        //             this.value = this.value.replace(/[^a-zA-Z0-9 ]/g, '');
        //         });
        //     }
        // });

        // Initialize per-field visibility for the client's current type on page load.
        applyCpcVisibility('<?= (int) $type ?>');
    </script>
</body>

</html>
