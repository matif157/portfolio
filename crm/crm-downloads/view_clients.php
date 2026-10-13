<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
// error_reporting(E_ALL);

include 'includes/db.php';
include 'includes/auth.php';

if (isset($_POST['delete'])) {
   $msg = '';
   $member_id = $_POST['member_id'];
   $queryy = "DELETE FROM members where member_id='$member_id'";
   $insert_query = mysqli_query($con, $queryy);

   if ($insert_query) {
      $msg .= "<div class='alert alert-success'>Client Has Been Deleted Successfully.</div>";
   } else {
      $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
   }
}

// Use the connection already established by db.php
$mysqli = $con;

// Get all parameters from URL
$profile_status = isset($_GET['profile_status']) ? $_GET['profile_status'] : '';
$member_type = isset($_GET['member_type']) ? $_GET['member_type'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : ''; //   ADD THIS LINE
$adv_client_id = isset($_GET['adv_client_id']) ? trim($_GET['adv_client_id']) : '';
$adv_name = isset($_GET['adv_name']) ? trim($_GET['adv_name']) : '';
$adv_client_type = isset($_GET['adv_client_type']) ? trim($_GET['adv_client_type']) : '';
$adv_email = isset($_GET['adv_email']) ? trim($_GET['adv_email']) : '';
$adv_category = isset($_GET['adv_category']) ? trim($_GET['adv_category']) : '';
$adv_phone = isset($_GET['adv_phone']) ? trim($_GET['adv_phone']) : '';
$adv_cell_phone = isset($_GET['adv_cell_phone']) ? trim($_GET['adv_cell_phone']) : '';
$adv_other_phone = isset($_GET['adv_other_phone']) ? trim($_GET['adv_other_phone']) : '';
$show_advanced = isset($_GET['show_advanced']) ? $_GET['show_advanced'] : '';

$num_results_on_page = 25;

// Get all member types for the filter dropdown
$member_types_query = "SELECT type_id, type_name FROM member_type ORDER BY type_name";
$member_types_result = $mysqli->query($member_types_query);
$member_types = [];
if ($member_types_result) {
   while ($type_row = $member_types_result->fetch_assoc()) {
      $member_types[] = $type_row;
   }
}

$client_id_options = [];
$client_id_result = $mysqli->query("SELECT DISTINCT member_id FROM members WHERE member_id IS NOT NULL ORDER BY member_id DESC");
if ($client_id_result) {
   while ($row = $client_id_result->fetch_assoc()) {
      $client_id_options[] = (string) $row['member_id'];
   }
}

$name_options = [];
$name_result = $mysqli->query("SELECT DISTINCT TRIM(CONCAT_WS(' ', name, mname, lname)) AS full_name FROM members WHERE TRIM(CONCAT_WS(' ', name, mname, lname)) <> '' ORDER BY full_name");
if ($name_result) {
   while ($row = $name_result->fetch_assoc()) {
      $name_options[] = $row['full_name'];
   }
}

$email_options = [];
$email_result = $mysqli->query("
   SELECT DISTINCT email FROM (
      SELECT TRIM(prm_email) AS email FROM members
      UNION
      SELECT TRIM(sec_email) AS email FROM members
   ) e
   WHERE email IS NOT NULL AND email <> ''
   ORDER BY email
");
if ($email_result) {
   while ($row = $email_result->fetch_assoc()) {
      $email_options[] = $row['email'];
   }
}

$category_options = [];
$category_result = $mysqli->query("SELECT DISTINCT category FROM members WHERE category IS NOT NULL AND TRIM(category) <> '' ORDER BY category");
if ($category_result) {
   while ($category_row = $category_result->fetch_assoc()) {
      $category_options[] = $category_row['category'];
   }
}

$phone_options = [];
$phone_result = $mysqli->query("SELECT DISTINCT phone FROM members WHERE phone IS NOT NULL AND TRIM(phone) <> '' ORDER BY phone");
if ($phone_result) {
   while ($row = $phone_result->fetch_assoc()) {
      $phone_options[] = $row['phone'];
   }
}

$cell_phone_options = [];
$cell_phone_result = $mysqli->query("SELECT DISTINCT cell_ph FROM members WHERE cell_ph IS NOT NULL AND TRIM(cell_ph) <> '' ORDER BY cell_ph");
if ($cell_phone_result) {
   while ($row = $cell_phone_result->fetch_assoc()) {
      $cell_phone_options[] = $row['cell_ph'];
   }
}

$other_phone_options = [];
$other_phone_result = $mysqli->query("SELECT DISTINCT other_ph FROM members WHERE other_ph IS NOT NULL AND TRIM(other_ph) <> '' ORDER BY other_ph");
if ($other_phone_result) {
   while ($row = $other_phone_result->fetch_assoc()) {
      $other_phone_options[] = $row['other_ph'];
   }
}

// Initialize base query with JOIN to member_type table
$base_query = "SELECT m.*, mt.type_name FROM members m LEFT JOIN member_type mt ON m.type = mt.type_id";
$count_query = "SELECT COUNT(*) as total FROM members m";

// Build WHERE conditions
$where_conditions = [];
if ($profile_status !== '') {
   $where_conditions[] = "m.status='$profile_status'";
}

if ($member_type !== '') {
   $where_conditions[] = "m.type='$member_type'";
}

//   ADD SEARCH FILTER LOGIC HERE
if ($search !== '') {
   $search_escaped = mysqli_real_escape_string($mysqli, $search);
   $search_no_hyphen = str_replace('-', '', $search_escaped);

   if (is_numeric($search) || is_numeric($search_no_hyphen)) {
      // Search by ID or phone numbers
      $where_conditions[] = "(
            m.member_id = '$search_escaped'
            OR REPLACE(m.phone, '-', '') LIKE '%$search_no_hyphen%'
            OR REPLACE(m.cell_ph, '-', '') LIKE '%$search_no_hyphen%'
            OR REPLACE(m.other_ph, '-', '') LIKE '%$search_no_hyphen%'
            OR REPLACE(m.overseas_ph, '-', '') LIKE '%$search_no_hyphen%'
            OR REPLACE(m.fax, '-', '') LIKE '%$search_no_hyphen%'
            OR m.ein LIKE '%$search_escaped%'
        )";
   } else {
      // Search by name, email, business name
      $searchWords = explode(' ', $search_escaped);
      $search_conditions = [];
      foreach ($searchWords as $word) {
         $word = mysqli_real_escape_string($mysqli, $word);
         $search_conditions[] = "(
                m.name LIKE '%$word%' 
                OR m.mname LIKE '%$word%' 
                OR m.lname LIKE '%$word%' 
                OR m.bus_name LIKE '%$word%' 
                OR m.prm_email LIKE '%$word%' 
                OR m.sec_email LIKE '%$word%'
                OR m.ein LIKE '%$word%'
            )";
      }
      $where_conditions[] = "(" . implode(" AND ", $search_conditions) . ")";
   }
}

if ($adv_client_id !== '') {
   $adv_client_id_escaped = mysqli_real_escape_string($mysqli, $adv_client_id);
   $where_conditions[] = "m.member_id = '$adv_client_id_escaped'";
}

if ($adv_name !== '') {
   $adv_name_escaped = mysqli_real_escape_string($mysqli, $adv_name);
   $where_conditions[] = "TRIM(CONCAT_WS(' ', m.name, m.mname, m.lname)) = '$adv_name_escaped'";
}

if ($adv_client_type !== '') {
   $adv_client_type_escaped = mysqli_real_escape_string($mysqli, $adv_client_type);
   $where_conditions[] = "m.type = '$adv_client_type_escaped'";
}

if ($adv_email !== '') {
   $adv_email_escaped = mysqli_real_escape_string($mysqli, $adv_email);
   $where_conditions[] = "(m.prm_email = '$adv_email_escaped' OR m.sec_email = '$adv_email_escaped')";
}

if ($adv_category !== '') {
   $adv_category_escaped = mysqli_real_escape_string($mysqli, $adv_category);
   $where_conditions[] = "m.category = '$adv_category_escaped'";
}

if ($adv_phone !== '') {
   $adv_phone_escaped = mysqli_real_escape_string($mysqli, $adv_phone);
   $where_conditions[] = "m.phone = '$adv_phone_escaped'";
}

if ($adv_cell_phone !== '') {
   $adv_cell_phone_escaped = mysqli_real_escape_string($mysqli, $adv_cell_phone);
   $where_conditions[] = "m.cell_ph = '$adv_cell_phone_escaped'";
}

if ($adv_other_phone !== '') {
   $adv_other_phone_escaped = mysqli_real_escape_string($mysqli, $adv_other_phone);
   $where_conditions[] = "m.other_ph = '$adv_other_phone_escaped'";
}

// Add WHERE clause if conditions exist
if (!empty($where_conditions)) {
   $where_clause = " WHERE " . implode(" AND ", $where_conditions);
   $base_query .= $where_clause;
   $count_query .= $where_clause;
}

// Get total number of records
$total_result = $mysqli->query($count_query);
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $num_results_on_page);

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$page = max(1, min($page, $total_pages)); // Ensure page is within valid range

// Calculate offset
$offset = ($page - 1) * $num_results_on_page;

// Final query with pagination
$final_query = $base_query . " ORDER BY m.member_id DESC LIMIT $offset, $num_results_on_page";

// Execute the query
$result = $mysqli->query($final_query);
$has_selected_advanced_filter = $adv_client_id !== '' || $adv_name !== '' || $adv_client_type !== '' || $adv_email !== '' || $adv_category !== '' || $adv_phone !== '' || $adv_cell_phone !== '' || $adv_other_phone !== '';
$advanced_query_suffix = '';
$advanced_query_suffix .= '&adv_client_id=' . urlencode($adv_client_id);
$advanced_query_suffix .= '&adv_name=' . urlencode($adv_name);
$advanced_query_suffix .= '&adv_client_type=' . urlencode($adv_client_type);
$advanced_query_suffix .= '&adv_email=' . urlencode($adv_email);
$advanced_query_suffix .= '&adv_category=' . urlencode($adv_category);
$advanced_query_suffix .= '&adv_phone=' . urlencode($adv_phone);
$advanced_query_suffix .= '&adv_cell_phone=' . urlencode($adv_cell_phone);
$advanced_query_suffix .= '&adv_other_phone=' . urlencode($adv_other_phone);
$advanced_open = ($show_advanced === '1') || $has_selected_advanced_filter || ($search !== '');
?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
   <title>Syed Professional Services - Dashboard</title>
   <?php include "includes/links.php"; ?>
   <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
</head>

<body class="theme-3">
   <!-- [ Pre-loader ] start -->
   <div class="loader-bg">
      <div class="loader-track">
         <div class="loader-fill"></div>
      </div>
   </div>
   <?php include 'includes/sidebar.php'; ?>
   <?php include 'includes/header.php'; ?>
   <!-- [ Main Content ] start -->

   <div class="dash-container">
      <div class="dash-content">
         <div class="page-header">
            <div class="page-block">
               <div class="row align-items-center">
                  <div class="col-auto">
                     <div class="page-header-title">
                        <h4 class="m-b-10"> Manage Clients</h4>
                     </div>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item">View Clients</li>
                     </ul>
                  </div>
               </div>
            </div>
         </div>
         <div class="row">
            <div class="col-sm-12">
               <?php if (isset($msg)) {
                  echo $msg;
               } ?>
               <!-- [ Main Content ] start -->
               <div class="card">
                  <div class="card-body">
                     <form method="GET" action="view_clients.php" class="mb-3" id="clientsFilterForm">
                        <div class="row">
                           <div class="col-md-4">
                              <select name="profile_status" class="form-select">
                                 <option value="">All Status</option>
                                 <option value="1" <?php if ($profile_status == '1')
                                    echo 'selected'; ?>>Active</option>
                                 <option value="0" <?php if ($profile_status == '0')
                                    echo 'selected'; ?>>Inactive</option>
                              </select>
                           </div>
                           <div class="col-md-4">
                              <select name="member_type" class="form-select">
                                 <option value="">All Member Types</option>
                                 <?php foreach ($member_types as $type): ?>
                                    <option value="<?php echo $type['type_id']; ?>" <?php if ($member_type == $type['type_id'])
                                          echo 'selected'; ?>>
                                       <?php echo $type['type_name']; ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="col-md-4">
                              <button type="submit" class="btn btn-primary w-100">Filter</button>
                           </div>
                        </div>
                        <div class="row mt-2">
                           <div class="col-md-12 d-flex gap-2">
                              <button type="button" class="btn btn-outline-secondary btn-sm"
                                 id="toggleAdvancedFilters">Advanced</button>
                              <a href="view_clients.php" class="btn btn-outline-danger btn-sm"
                                 id="resetFiltersBtn">Reset Filters</a>
                              <input type="hidden" name="show_advanced" id="showAdvancedInput"
                                 value="<?php echo $advanced_open ? '1' : '0'; ?>">
                           </div>
                        </div>
                        <div id="advancedFiltersBlock" class="row mt-2 <?php echo $advanced_open ? '' : 'd-none'; ?>">
                           <div class="col-md-4 mt-2">
                              <label class="form-label mb-1">Client ID</label>
                              <select name="adv_client_id" class="form-select filter-select2">
                                 <option value="">All Client ID</option>
                                 <?php foreach ($client_id_options as $client_id_option): ?>
                                    <option value="<?php echo htmlspecialchars($client_id_option); ?>" <?php if ($adv_client_id === $client_id_option)
                                          echo 'selected'; ?>>
                                       <?php echo htmlspecialchars($client_id_option); ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="col-md-4 mt-2">
                              <label class="form-label mb-1">Name</label>
                              <select name="adv_name" class="form-select filter-select2">
                                 <option value="">All Name</option>
                                 <?php foreach ($name_options as $name_option): ?>
                                    <option value="<?php echo htmlspecialchars($name_option); ?>" <?php if ($adv_name === $name_option)
                                          echo 'selected'; ?>>
                                       <?php echo htmlspecialchars($name_option); ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="col-md-4 mt-2">
                              <label class="form-label mb-1">Client Type</label>
                              <select name="adv_client_type" class="form-select filter-select2">
                                 <option value="">All Client Types</option>
                                 <?php foreach ($member_types as $type): ?>
                                    <option value="<?php echo $type['type_id']; ?>" <?php if ($adv_client_type == $type['type_id'])
                                          echo 'selected'; ?>>
                                       <?php echo htmlspecialchars($type['type_name']); ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="col-md-4 mt-2">
                              <label class="form-label mb-1">Email</label>
                              <select name="adv_email" class="form-select filter-select2">
                                 <option value="">All Email</option>
                                 <?php foreach ($email_options as $email_option): ?>
                                    <option value="<?php echo htmlspecialchars($email_option); ?>" <?php if ($adv_email === $email_option)
                                          echo 'selected'; ?>>
                                       <?php echo htmlspecialchars($email_option); ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="col-md-4 mt-2">
                              <label class="form-label mb-1">Category</label>
                              <select name="adv_category" class="form-select filter-select2">
                                 <option value="">All Category</option>
                                 <?php foreach ($category_options as $category_option): ?>
                                    <option value="<?php echo htmlspecialchars($category_option); ?>" <?php if ($adv_category === $category_option)
                                          echo 'selected'; ?>>
                                       <?php echo htmlspecialchars($category_option); ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="col-md-4 mt-2">
                              <label class="form-label mb-1">Phone</label>
                              <select name="adv_phone" class="form-select filter-select2">
                                 <option value="">All Phone</option>
                                 <?php foreach ($phone_options as $phone_option): ?>
                                    <option value="<?php echo htmlspecialchars($phone_option); ?>" <?php if ($adv_phone === $phone_option)
                                          echo 'selected'; ?>>
                                       <?php echo htmlspecialchars($phone_option); ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="col-md-4 mt-2">
                              <label class="form-label mb-1">Cell Phone</label>
                              <select name="adv_cell_phone" class="form-select filter-select2">
                                 <option value="">All Cell Phone</option>
                                 <?php foreach ($cell_phone_options as $cell_phone_option): ?>
                                    <option value="<?php echo htmlspecialchars($cell_phone_option); ?>" <?php if ($adv_cell_phone === $cell_phone_option)
                                          echo 'selected'; ?>>
                                       <?php echo htmlspecialchars($cell_phone_option); ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="col-md-4 mt-2">
                              <label class="form-label mb-1">Other Phone</label>
                              <select name="adv_other_phone" class="form-select filter-select2">
                                 <option value="">All Other Phone</option>
                                 <?php foreach ($other_phone_options as $other_phone_option): ?>
                                    <option value="<?php echo htmlspecialchars($other_phone_option); ?>" <?php if ($adv_other_phone === $other_phone_option)
                                          echo 'selected'; ?>>
                                       <?php echo htmlspecialchars($other_phone_option); ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                        </div>
                     </form>
                     <?php if ($search !== ''): ?>
                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                           <span>Showing results for: <strong><?php echo htmlspecialchars($search); ?></strong>
                              (<?php echo $total_rows; ?> records found)</span>
                           <a href="view_clients.php<?php echo $profile_status !== '' ? '?profile_status=' . $profile_status : ''; ?>"
                              class="btn btn-sm btn-outline-secondary">Clear Search</a>
                        </div>
                     <?php endif; ?>
                     <div class="d-flex justify-content-center">
                        <select id="exportOption" class="form-select" onchange="filterClients()">
                           <option value="" disabled selected>Select Client Status</option>
                           <option value="all">All Clients</option>
                           <option value="active">Active Clients</option>
                           <option value="inactive">Inactive Clients</option>
                        </select>

                        <!-- Export to Excel Button -->
                        <button id="exportToExcel" class="btn btn-lg btn-success mx-2">Export to Excel</button>

                        <!-- Preview Data Button -->
                        <button type="button" onclick="submitPreviewForm()" class="btn btn-lg btn-warning mx-2">Preview
                           Data</button>

                        <!-- Hidden Preview Form -->
                        <form id="previewForm" action="preview.php" method="POST" style="display: none;">
                           <input type="hidden" name="export_option" id="exportOptionInput">
                        </form>
                     </div>
                  </div>
               </div>

               <script>
                  document.addEventListener('DOMContentLoaded', function () {
                     var storedFilter = localStorage.getItem('selectedFilter');
                     if (storedFilter) {
                        document.getElementById('exportOption').value = storedFilter;
                     } else {
                        document.getElementById('exportOption').value = 'active';
                        localStorage.setItem('selectedFilter', 'active');
                        filterClients();
                     }
                  });

                  function filterClients() {
                     var filter = document.getElementById('exportOption').value;
                     if (filter === 'all') {
                        localStorage.removeItem('selectedFilter');
                     } else {
                        localStorage.setItem('selectedFilter', filter);
                     }
                  }

                  function submitPreviewForm() {
                     var selectedOption = document.getElementById('exportOption').value;
                     if (selectedOption === 'all') {
                        alert('Please select an option other than "All"');
                        return;
                     }
                     document.getElementById('exportOptionInput').value = selectedOption;
                     document.getElementById('previewForm').submit();
                  }
               </script>

               <div class="card-body table-border-style">
                  <div class="table-responsive">
                     <table class="table datatable">
                        <thead>
                           <tr>
                              <th><a href="#" class="dataTable-sorter">Client ID</a></th>
                              <th data-sortable="" style="width: 10.2395%;"><a href="#"
                                    class="dataTable-sorter">Name</a></th>
                              <th data-sortable="" style="width: 16.2762%;"><a href="#" class="dataTable-sorter">Client
                                    Type</a></th>
                              <th data-sortable="" style="width: 12%;"><a href="#" class="dataTable-sorter">Email</a>
                              </th>
                              <th data-sortable="" style="width: 6.9537%;"><a href="#" class="dataTable-sorter">Billing
                                    Balance</a> 
                              </th>
                              <th>Category</th>
                              <!-- <th data-sortable="" style="width: 5.9272%;"><a href="#"
                                    class="dataTable-sorter">Phone</a></th> -->
                              <th data-sortable="" style="width: 5.9272%;"><a href="#" class="dataTable-sorter">Cell
                                    Phone</a></th>
                              <!-- <th data-sortable="" style="width: 5.9272%;"><a href="#" class="dataTable-sorter">Other
                                    Phone</a></th> -->
                              <?php if ($client_active == "Yes") { ?>
                                 <th data-sortable="" style="width: 5.0000%;"><a href="#"
                                       class="dataTable-sorter">Activity</a></th>
                              <?php } ?>
                              <th width="200px" data-sortable="" style="width: 8.78764%;"><a href="#"
                                    class="dataTable-sorter">Action</a></th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php while ($rrow = $result->fetch_assoc()): ?>
                              <tr class="client-row"
                                 data-status="<?php echo $rrow['status'] == 1 ? 'active' : 'inactive'; ?>">
                                 <td class="Id">
                                    <?php if ($acc_detail_client == "Yes") { ?>
                                       <a href="client_details.php?id=<?php echo $rrow['member_id']; ?>&status=<?php echo $rrow['status']; ?>"
                                          class="btn btn-outline-primary"
                                          target="_blank">#<?php echo $rrow['member_id']; ?></a>
                                    <?php } else { ?>
                                       <a href="#" class="btn btn-outline-primary">#<?php echo $rrow['member_id']; ?></a>
                                    <?php } ?>
                                 </td>
                                 <td class="font-style">
                                    <?php echo $rrow['name'] . " " . $rrow['mname'] . " " . $rrow['lname']; ?><br /><small><?php echo $rrow['bus_name']; ?></small>
                                 </td>
                                 <td><?php echo $rrow['type_name'] ?? 'Unknown'; ?></td>
                                 <td class="font-style"><?php echo $rrow['prm_email']; ?></td>
                                 <td class="font-style">
                                    <?php
                                    $balQ = mysqli_query($con, "SELECT SUM(balance) AS bal FROM billing_invoice WHERE mem_id='{$rrow['member_id']}'");
                                    $balR = mysqli_fetch_assoc($balQ);
                                    echo number_format((float) ($balR['bal'] ?? 0), 2);
                                    ?>
                                 </td>
                                 <td> <?php echo $rrow['category'] ?></td>
                                 <!-- <td class="font-style"><?php echo $rrow["phone"]; ?></td> -->
                                 <td class="font-style"><?php echo $rrow["cell_ph"]; ?></td>
                                 <!-- <td class="font-style"><?php echo $rrow["other_ph"]; ?></td> -->
                                 <?php if ($client_active == "Yes") { ?>
                                    <td>
                                       <div class="form-check form-switch">
                                          <input class="form-check-input activity-switch" type="checkbox" role="switch"
                                             data-member-id="<?php echo $rrow['member_id']; ?>" <?php if ($rrow["status"] == 1) {
                                                   echo "checked";
                                                } ?>>
                                       </div>
                                    </td>
                                 <?php } ?>
                                 <td>
                                    <a href="edit_client.php?id=<?php echo $rrow['member_id']; ?>"
                                       class="btn btn-sm btn-primary tooltip1"><i class="ti ti-pencil"></i><span
                                          class="tooltiptext1">Edit Client</span></a>
                                    <?php if ($acc_detail_client == "Yes") { ?>
                                       <a
                                          href="client_details.php?id=<?php echo $rrow['member_id']; ?>&status=<?php echo $rrow['status']; ?>">
                                          <button class="btn btn-sm btn-info tooltip1"><i
                                                class="ti ti-eye text-white"></i><span class="tooltiptext1">View
                                                Client</span></button>
                                       </a>
                                    <?php } ?>
                                    <?php if ($acc_billing_client == "Yes") { ?>
                                       <a href="client_billing.php?id=<?php echo $rrow['member_id']; ?>">
                                          <button class="btn btn-sm btn-warning tooltip1"><svg
                                                xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-coins"
                                                width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                                                stroke="currentColor" fill="none" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                                <path
                                                   d="M9 14c0 1.657 2.686 3 6 3s6 -1.343 6 -3s-2.686 -3 -6 -3s-6 1.343 -6 3z">
                                                </path>
                                                <path d="M9 14v4c0 1.656 2.686 3 6 3s6 -1.344 6 -3v-4"></path>
                                                <path
                                                   d="M3 6c0 1.072 1.144 2.062 3 2.598s4.144 .536 6 0c1.856 -.536 3 -1.526 3 -2.598c0 -1.072 -1.144 -2.062 -3 -2.598s-4.144 -.536 -6 0c-1.856 .536 -3 1.526 -3 2.598z">
                                                </path>
                                                <path d="M3 6v10c0 .888 .772 1.45 2 2"></path>
                                                <path d="M3 11c0 .888 .772 1.45 2 2"></path>
                                             </svg>
                                             <span class="tooltiptext1">Client Billing</span></button>
                                       </a>
                                    <?php } ?>
                                    <?php if ($acc_delete_client == "Yes") { ?>
                                       <button class="btn btn-sm btn-custom tooltip1" data-toggle="modal"
                                          data-target="#deleteclient<?php echo $rrow['member_id']; ?>"><i
                                             class="ti ti-trash text-white"></i><span class="tooltiptext1">Delete
                                             Client</span></button>
                                    <?php } ?>
                                 </td>
                              </tr>

                              <!-- Delete Product Modal -->
                              <div class="modal fade" id="deleteclient<?php echo $rrow['member_id']; ?>">
                                 <div class="modal-dialog modal-md">
                                    <form method="POST">
                                       <div class="modal-content">
                                          <!-- Modal body -->
                                          <div class="modal-body">
                                             <div class="row mb-2">
                                                <div class="col-md-12 mt-2">
                                                   <input name="member_id" type="hidden"
                                                      value="<?php echo $rrow['member_id']; ?>">
                                                   <div class="swal2-icon swal2-warning swal2-icon-show"
                                                      style="display: flex;">
                                                      <div class="swal2-icon-content">!</div>
                                                   </div>
                                                   <h2 class="swal2-title" id="swal2-title" style="display: block;">Are you
                                                      sure?</h2>
                                                   <div class="swal2-html-container" id="swal2-html-container"
                                                      style="display: block;">This action can not be undone. Do you want to
                                                      continue?</div>
                                                </div>
                                             </div>
                                          </div>

                                          <!-- Modal footer -->
                                          <div class="modal-footer" style="justify-content: center;">
                                             <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                                             <button type="submit" class="btn btn-primary" name="delete">Yes
                                                Delete</button>
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
                     <?php if ($total_pages > 1): ?>
                        <ul class="news">
                           <?php if ($page > 1): ?>
                              <li class="prev">
                                 <a
                                    href="view_clients.php?page=<?php echo $page - 1; ?><?php echo $profile_status !== '' ? '&profile_status=' . $profile_status : ''; ?><?php echo $member_type !== '' ? '&member_type=' . $member_type : ''; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?><?php echo $advanced_query_suffix; ?><?php echo $advanced_open ? '&show_advanced=1' : ''; ?>">Prev</a>
                              </li>
                           <?php endif; ?>

                           <?php if ($page > 3): ?>
                              <li class="start">
                                 <a
                                    href="view_clients.php?page=1<?php echo $profile_status !== '' ? '&profile_status=' . $profile_status : ''; ?><?php echo $member_type !== '' ? '&member_type=' . $member_type : ''; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?><?php echo $advanced_query_suffix; ?><?php echo $advanced_open ? '&show_advanced=1' : ''; ?>">1</a>
                              </li>
                              <li class="dots">...</li>
                           <?php endif; ?>

                           <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                              <?php if ($i == $page): ?>
                                 <li class="current"><span><?php echo $i; ?></span></li>
                              <?php else: ?>
                                 <li class="page">
                                    <a
                                       href="view_clients.php?page=<?php echo $i; ?><?php echo $profile_status !== '' ? '&profile_status=' . $profile_status : ''; ?><?php echo $member_type !== '' ? '&member_type=' . $member_type : ''; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?><?php echo $advanced_query_suffix; ?><?php echo $advanced_open ? '&show_advanced=1' : ''; ?>"><?php echo $i; ?></a>
                                 </li>
                              <?php endif; ?>
                           <?php endfor; ?>

                           <?php if ($page < $total_pages - 2): ?>
                              <li class="dots">...</li>
                              <li class="end">
                                 <a
                                    href="view_clients.php?page=<?php echo $total_pages; ?><?php echo $profile_status !== '' ? '&profile_status=' . $profile_status : ''; ?><?php echo $member_type !== '' ? '&member_type=' . $member_type : ''; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?><?php echo $advanced_query_suffix; ?><?php echo $advanced_open ? '&show_advanced=1' : ''; ?>"><?php echo $total_pages; ?></a>
                              </li>
                           <?php endif; ?>

                           <?php if ($page < $total_pages): ?>
                              <li class="next">
                                 <a
                                    href="view_clients.php?page=<?php echo $page + 1; ?><?php echo $profile_status !== '' ? '&profile_status=' . $profile_status : ''; ?><?php echo $member_type !== '' ? '&member_type=' . $member_type : ''; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?><?php echo $advanced_query_suffix; ?><?php echo $advanced_open ? '&show_advanced=1' : ''; ?>">Next</a>
                              </li>
                           <?php endif; ?>
                        </ul>
                     <?php endif; ?>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <!-- [ Main Content ] end -->
   </div>
   </div>
   <?php include 'includes/footer.php'; ?>
   <?php include 'includes/scripts.php'; ?>
   <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
   <script>
      $(document).ready(function () {
         if ($.fn.select2) {
            $('.filter-select2').select2({
               width: '100%'
            });
         }

         $('#toggleAdvancedFilters').on('click', function () {
            $('#advancedFiltersBlock').toggleClass('d-none');
            var isOpen = !$('#advancedFiltersBlock').hasClass('d-none');
            $('#showAdvancedInput').val(isOpen ? '1' : '0');
         });

         $('#clientsFilterForm').on('submit', function () {
            var isOpen = !$('#advancedFiltersBlock').hasClass('d-none');
            $('#showAdvancedInput').val(isOpen ? '1' : '0');
         });
      });
   </script>
   <style>
      .select2-container {
         width: 100% !important;
      }

      .select2-container .select2-selection--single {
         height: 44px !important;
         border: 1px solid #d0d5dd !important;
         border-radius: 8px !important;
         background-color: #fff !important;
         padding: 7px 12px !important;
      }

      .select2-container--default .select2-selection--single .select2-selection__rendered {
         line-height: 28px !important;
         color: #101828 !important;
         padding-left: 0 !important;
         padding-right: 28px !important;
      }

      .select2-container--default .select2-selection--single .select2-selection__arrow {
         height: 42px !important;
         right: 10px !important;
      }

      .select2-container--default.select2-container--open .select2-selection--single {
         border-color: #86b7fe !important;
         box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15) !important;
      }

      .select2-dropdown {
         border: 1px solid #d0d5dd !important;
         border-radius: 8px !important;
         overflow: hidden;
      }

      .select2-results__option {
         padding: 8px 12px !important;
      }

      .select2-container--default .select2-results__option--highlighted[aria-selected] {
         background-color: #5EB839 !important;
      }

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
   <script type="text/javascript">
      new DataTable('#example');
   </script>
</body>
<script>
   $(document).ready(function () {
      $('.activity-switch').change(function () {
         var memberId = $(this).data('member-id');
         var status = $(this).is(':checked') ? 1 : 0;

         $.ajax({
            url: 'update_status.php',
            type: 'POST',
            data: {
               member_id: memberId,
               status: status
            },
            success: function (response) {
               // console.log(response);
               window.location.reload();
            },
            error: function (xhr, status, error) {
               // console.error(error);
            }
         });
      });
   });
</script>

</html>

<script>
   $(document).ready(function () {
      $('#exportToExcel').click(function () {
         var exportOption = $('#exportOption').val();

         // Send AJAX request to fetch data
         $.ajax({
            url: 'export_to_excel.php',
            type: 'POST',
            data: {
               export_option: exportOption
            },
            success: function (response) {
               // Trigger file download
               var blob = new Blob([response], {
                  type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
               });
               var link = document.createElement('a');
               link.href = window.URL.createObjectURL(blob);
               link.download = 'clients.csv';
               link.click();
            },
            error: function (xhr, status, error) {
               // console.error(error);
            }
         });
      });
   });
</script>