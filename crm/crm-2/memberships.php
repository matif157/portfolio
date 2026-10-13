<?php
include 'includes/db.php';
include "includes/auth.php";
include "includes/activity_log.php";
require_once 'includes/customizer.php';
error_reporting(0);

$log_user_id = $_SESSION['user_id'] ?? null;

/*
 * Post/Redirect/Get: every form post below stores its result message in the
 * session and redirects back to this page, so refreshing never re-submits
 * (no "confirm form resubmission" browser prompt).
 */
function memberships_redirect($msg)
{
   $_SESSION['memberships_flash'] = $msg;
   $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
   header('Location: memberships.php?page=' . $page);
   exit;
}

/*
 * On-demand product insights (expanded row on memberships.php).
 * Total/active clients use the real member<->product link (orders /
 * orders_auto_renewed / member_services). Invoice figures are matched by the
 * invoice line-item text (item_description = product name), since invoices are
 * only linked to a client, not to a product, in this schema.
 */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'product_stats') {
   header('Content-Type: application/json; charset=utf-8');
   $pid = (int) ($_GET['membership_id'] ?? 0);
   $out = ['success' => false];
   if ($pid > 0) {
      $name = '';
      if ($st = mysqli_prepare($con, "SELECT membership_name FROM memberships WHERE membership_id=? LIMIT 1")) {
         mysqli_stmt_bind_param($st, 'i', $pid);
         mysqli_stmt_execute($st);
         $rs = mysqli_stmt_get_result($st);
         if ($row = mysqli_fetch_assoc($rs))
            $name = (string) $row['membership_name'];
         mysqli_stmt_close($st);
      }

      // Distinct clients BILLED for this product (invoice line-item text match).
      $totalClients = 0;
      if ($name !== '' && ($st = mysqli_prepare($con, "SELECT COUNT(DISTINCT bi.mem_id) c
               FROM billing_invoice bi
               JOIN billing_invoice_items bii ON bii.inv_id = bi.invoice_id
               WHERE LOWER(TRIM(bii.item_description)) = LOWER(TRIM(?)) AND bi.mem_id > 0"))) {
         mysqli_stmt_bind_param($st, 's', $name);
         mysqli_stmt_execute($st);
         $rs = mysqli_stmt_get_result($st);
         $totalClients = (int) (mysqli_fetch_assoc($rs)['c'] ?? 0);
         mysqli_stmt_close($st);
      }

      // Invoices whose line-item text matches this product name.
      // invoice_status: 1 = Unpaid, 2 = Partial, 3 = Paid.
      $totalInv = $paid = $partial = $unpaid = 0;
      $collected = 0.0;
      $outstanding = 0.0;
      if ($name !== '' && ($st = mysqli_prepare($con, "SELECT bi.invoice_status s, bi.paid_amount p, bi.balance b
               FROM billing_invoice bi
               WHERE bi.invoice_id IN (
                  SELECT DISTINCT inv_id FROM billing_invoice_items
                  WHERE LOWER(TRIM(item_description)) = LOWER(TRIM(?))
               )"))) {
         mysqli_stmt_bind_param($st, 's', $name);
         mysqli_stmt_execute($st);
         $rs = mysqli_stmt_get_result($st);
         while ($r = mysqli_fetch_assoc($rs)) {
            $totalInv++;
            $s = (int) $r['s'];
            if ($s === 3)
               $paid++;
            elseif ($s === 2)
               $partial++;
            elseif ($s === 1)
               $unpaid++;
            $collected += (float) $r['p'];
            if ($s === 1 || $s === 2)
               $outstanding += (float) $r['b'];
         }
         mysqli_stmt_close($st);
      }

      // Service trend BY TIME — matched invoices grouped by month. Uses due_date
      // (a real DATE); invoice_date is a free-text varchar and can't be grouped.
      $trend = [];
      if ($name !== '' && ($st = mysqli_prepare($con, "SELECT DATE_FORMAT(bi.due_date,'%Y-%m') ym, COUNT(*) c
               FROM billing_invoice bi
               WHERE bi.invoice_id IN (
                  SELECT DISTINCT inv_id FROM billing_invoice_items
                  WHERE LOWER(TRIM(item_description)) = LOWER(TRIM(?))
               )
               AND bi.due_date IS NOT NULL AND bi.due_date <> '0000-00-00'
               AND bi.due_date >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH)
               GROUP BY ym ORDER BY ym"))) {
         mysqli_stmt_bind_param($st, 's', $name);
         mysqli_stmt_execute($st);
         $rs = mysqli_stmt_get_result($st);
         while ($r = mysqli_fetch_assoc($rs))
            $trend[] = ['label' => (string) $r['ym'], 'count' => (int) $r['c']];
         mysqli_stmt_close($st);
      }

      // The clients billed for this product (used for the type/category breakdowns).
      $clientSub = "SELECT DISTINCT bi.mem_id AS mid
                    FROM billing_invoice bi
                    JOIN billing_invoice_items bii ON bii.inv_id = bi.invoice_id
                    WHERE LOWER(TRIM(bii.item_description)) = LOWER(TRIM(?)) AND bi.mem_id > 0";

      // BY CLIENT TYPES (members.type -> member_type.type_name).
      $byType = [];
      if ($name !== '' && ($st = mysqli_prepare($con, "SELECT COALESCE(NULLIF(TRIM(mt.type_name),''),'Unknown') label, COUNT(*) c
               FROM ($clientSub) s
               JOIN members m ON m.member_id = s.mid
               LEFT JOIN member_type mt ON mt.type_id = m.type
               GROUP BY label ORDER BY c DESC LIMIT 12"))) {
         mysqli_stmt_bind_param($st, 's', $name);
         mysqli_stmt_execute($st);
         $rs = mysqli_stmt_get_result($st);
         while ($r = mysqli_fetch_assoc($rs))
            $byType[] = ['label' => (string) $r['label'], 'count' => (int) $r['c']];
         mysqli_stmt_close($st);
      }

      // BY CLIENT CATEGORIES (members.category).
      $byCategory = [];
      if ($name !== '' && ($st = mysqli_prepare($con, "SELECT CASE WHEN m.category IS NULL OR TRIM(m.category)='' THEN 'Uncategorized' ELSE TRIM(m.category) END label, COUNT(*) c
               FROM ($clientSub) s
               JOIN members m ON m.member_id = s.mid
               GROUP BY label ORDER BY c DESC LIMIT 12"))) {
         mysqli_stmt_bind_param($st, 's', $name);
         mysqli_stmt_execute($st);
         $rs = mysqli_stmt_get_result($st);
         while ($r = mysqli_fetch_assoc($rs))
            $byCategory[] = ['label' => (string) $r['label'], 'count' => (int) $r['c']];
         mysqli_stmt_close($st);
      }

      $out = [
         'success' => true,
         'name' => $name,
         'total_clients' => $totalClients,
         'total_invoices' => $totalInv,
         'paid' => $paid,
         // "Unpaid" = anything not fully paid (unpaid + partial).
         'unpaid' => $unpaid + $partial,
         'trend_by_time' => $trend,
         'by_type' => $byType,
         'by_category' => $byCategory,
      ];
   }
   echo json_encode($out);
   exit;
}

if (isset($_POST['add_new'])) {
   $msg = '';

   $product_name = $_POST['product_name'];
   $product_price = $_POST['product_price'];

   $queryy = "INSERT INTO memberships(membership_name,membership_price) VALUES('$product_name','$product_price')";
   $insert_query = mysqli_query($con, $queryy);

   if ($insert_query) {
      $new_product_id = (int) mysqli_insert_id($con);
      customizer_save_posted_values($con, 'memberships', $new_product_id, $log_user_id, $_SESSION['fullname'] ?? '');
      $log_name = trim((string) $product_name);
      $log_price = number_format((float) $product_price, 2);
      logActivity($con, $log_user_id, 'CREATE', 'memberships', $new_product_id, "New service '{$log_name}' added (ID: {$new_product_id}) with price \${$log_price}.");
      $msg .= "<div class='alert alert-success'>Service Has Been Added Successfully.</div>";
   } else {
      $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
   }

   memberships_redirect($msg);
}

if (isset($_POST['edit_product'])) {
   $msg = '';

   $product_name = $_POST['product_name'];
   $product_id = $_POST['product_id'];
   $product_price = $_POST['product_price'];

   // Capture the current values so the log can show exactly what changed.
   $old_product = mysqli_fetch_assoc(mysqli_query($con, "SELECT membership_name, membership_price FROM memberships WHERE membership_id='$product_id'"));

   $queryy = "UPDATE memberships SET membership_name='$product_name',membership_price='$product_price' where membership_id='$product_id'";
   $insert_query = mysqli_query($con, $queryy);

   if ($insert_query) {
      customizer_save_posted_values($con, 'memberships', (int) $product_id, $log_user_id, $_SESSION['fullname'] ?? '');
      $log_name = trim((string) $product_name);
      $changes = [];
      if ($old_product) {
         $old_name = trim((string) $old_product['membership_name']);
         if ($old_name !== $log_name)
            $changes[] = "name changed from '{$old_name}' to '{$log_name}'";
         if ((float) $old_product['membership_price'] !== (float) $product_price)
            $changes[] = "price changed from \$" . number_format((float) $old_product['membership_price'], 2)
               . " to \$" . number_format((float) $product_price, 2);
      }
      $log_description = "Updated service '{$log_name}' (ID: {$product_id})"
         . ($changes ? ": " . implode('; ', $changes) . "." : " – no field values were changed.");
      logActivity($con, $log_user_id, 'UPDATE', 'memberships', $product_id, $log_description);
      $msg .= "<div class='alert alert-success'>Service Has Been Updated Successfully.</div>";
   } else {
      $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
   }

   memberships_redirect($msg);
}

if (isset($_POST['delete_product'])) {
   $msg = '';

   if ($usertype != '1') {
      $msg .= "<div class='alert alert-danger'>Only admin can delete a service.</div>";
   } else {
      $product_id = $_POST['product_id'];

      // Read the record before deleting so the log keeps a readable name/price.
      $deleted_product = mysqli_fetch_assoc(mysqli_query($con, "SELECT membership_name, membership_price FROM memberships WHERE membership_id='$product_id'"));

      $queryy = "DELETE FROM memberships where membership_id='$product_id'";
      $insert_query = mysqli_query($con, $queryy);

      if ($insert_query) {
         $deleted_name = trim((string) ($deleted_product['membership_name'] ?? 'Unknown'));
         $deleted_price = number_format((float) ($deleted_product['membership_price'] ?? 0), 2);
         logActivity($con, $log_user_id, 'DELETE', 'memberships', $product_id, "Deleted service '{$deleted_name}' (ID: {$product_id}) with price \${$deleted_price}.");
         $msg .= "<div class='alert alert-success'>Service Has Been Deleted Successfully.</div>";
      } else {
         $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
      }
   }

   memberships_redirect($msg);
}

if (isset($_POST['toggle_status'])) {
   $msg = '';

   $product_id = (int) $_POST['product_id'];

   // Get current status
   $status_result = mysqli_query($con, "SELECT membership_status, membership_name FROM memberships WHERE membership_id='$product_id'");
   $status_row = mysqli_fetch_assoc($status_result);
   $current_status = $status_row['membership_status'];
   $toggle_name = $status_row['membership_name'] ?? 'Unknown';

   // Toggle status (0 = Active, 1 = Disabled)
   $new_status = ($current_status == '0') ? '1' : '0';
   $queryy = "UPDATE memberships SET membership_status='$new_status' WHERE membership_id='$product_id'";
   $insert_query = mysqli_query($con, $queryy);

   if ($insert_query) {
      $status_text = ($new_status == '1') ? 'Disabled' : 'Enabled';
      $log_name = trim((string) $toggle_name);
      logActivity($con, $log_user_id, 'UPDATE', 'memberships', $product_id, "{$status_text} service '{$log_name}' (ID: {$product_id}) – status changed from "
         . ($current_status == '1' ? 'Disabled' : 'Active') . " to " . ($new_status == '1' ? 'Disabled' : 'Active') . ".");
      $msg .= "<div class='alert alert-success'>Service Has Been $status_text Successfully.</div>";
   } else {
      $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
   }

   memberships_redirect($msg);
}


// Show (and clear) the message left by the redirect above.
if (isset($_SESSION['memberships_flash'])) {
   $msg = $_SESSION['memberships_flash'];
   unset($_SESSION['memberships_flash']);
}
?>
<?php
$mysqli = $con;

$quer = "SELECT * FROM memberships ORDER By membership_name ASC";

$total_pages = $mysqli->query($quer)->num_rows;

// Check if the page number is specified and check if it's a number, if not return the default page number which is 1.
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;

// Number of results to show on each page.
$num_results_on_page = 25;
$que = "SELECT * FROM memberships ORDER BY membership_name ASC LIMIT ?,?";


if ($stmt = $mysqli->prepare($que)) {
   // Calculate the page to get the results we need from our table.
   $calc_page = ($page - 1) * $num_results_on_page;
   $stmt->bind_param('ii', $calc_page, $num_results_on_page);
   $stmt->execute();
   // Get the results...
   $result = $stmt->get_result();
   $products = $result->fetch_all(MYSQLI_ASSOC);

   /*
    * Per-product metrics for the visible page, shown directly as columns. All four
    * derive from invoices matched by line-item text (item_description = product
    * name), so they stay consistent with each other:
    *   Clients  = distinct clients (billing_invoice.mem_id) billed for the product
    *   Invoices = distinct matched invoices
    *   Paid     = matched invoices with invoice_status = 3
    *   Unpaid   = matched invoices not fully paid (status <> 3)
    * One aggregate query for the whole page keeps it fast.
    */
   $statsById = [];
   foreach ($products as $p)
      $statsById[(int) $p['membership_id']] = ['clients' => 0, 'inv' => 0, 'paid' => 0, 'unpaid' => 0];

   if ($products) {
      $nameToId = [];
      $clientSets = [];
      foreach ($products as $p) {
         $mid = (int) $p['membership_id'];
         $nameToId[mb_strtolower(trim((string) $p['membership_name']))] = $mid;
         $clientSets[$mid] = [];
      }
      $escNames = [];
      foreach (array_keys($nameToId) as $nm) {
         if ($nm === '')
            continue;
         $escNames[] = "'" . mysqli_real_escape_string($con, $nm) . "'";
      }
      if ($escNames) {
         $inNames = implode(',', $escNames);
         // One row per (product, invoice): gives invoice counts and the client set.
         $iq = mysqli_query($con, "SELECT LOWER(TRIM(bii.item_description)) k, bi.invoice_id, bi.mem_id, bi.invoice_status s
                  FROM billing_invoice_items bii
                  JOIN billing_invoice bi ON bi.invoice_id = bii.inv_id
                  WHERE LOWER(TRIM(bii.item_description)) IN ($inNames)
                  GROUP BY bi.invoice_id, k, bi.mem_id, bi.invoice_status");
         if ($iq) {
            while ($r = mysqli_fetch_assoc($iq)) {
               $k = (string) $r['k'];
               if (!isset($nameToId[$k]))
                  continue;
               $pid = $nameToId[$k];
               $statsById[$pid]['inv']++;
               if ((int) $r['s'] === 3)
                  $statsById[$pid]['paid']++;
               else
                  $statsById[$pid]['unpaid']++;
               $mid = (int) $r['mem_id'];
               if ($mid > 0)
                  $clientSets[$pid][$mid] = true;
            }
            foreach ($clientSets as $pid => $set)
               $statsById[$pid]['clients'] = count($set);
         }
      }
   }
   ?>

   <!DOCTYPE html>
   <html lang="en" dir="">
   <meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

   <head>
      <title>Syed Professional Services - Dashboard</title>
      <?php include "includes/links.php"; ?>
   </head>

   <body class="theme-3">
      <!-- [ Pre-loader ] start -->
      <div class="loader-bg">
         <div class="loader-track">
            <div class="loader-fill"></div>
         </div>
      </div>
      <?php include "includes/sidebar.php"; ?>
      <?php include "includes/header.php"; ?>
      <!-- [ Main Content ] start -->
      <div class="dash-container">
         <div class="dash-content">
            <div class="page-header">
               <div class="page-block">
                  <div class="row align-items-center">
                     <div class="col-auto">
                        <div class="page-header-title">
                           <h4 class="m-b-10">Manage Services</h4>
                        </div>
                        <ul class="breadcrumb">
                           <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                           <li class="breadcrumb-item">Services</li>
                        </ul>
                     </div>
                     <div class="col">
                        <div class="float-end">
                           <?php if ($acc_add_edit_products == "Yes") { ?>
                              <button class="btn btn-primary" data-toggle="modal" data-target="#addProduct">Add New
                                 Service</button><?php } ?>
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
                  <!-- [ Main Content ] start -->
                  <div class="card">
                     <div class="card-body table-border-style">
                        <div class="table-responsive">
                           <div class="dataTable-wrapper dataTable-loading no-footer sortable searchable fixed-columns">
                              <div class="dataTable-container">
                                 <table class="table datatable">
                                    <thead>
                                       <tr>
                                          <th data-sortable="" style="width: 13.3725%;"><a href="#"
                                                class="dataTable-sorter">Services</a></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Price</a></th>
                                          <th data-sortable="" class="text-center"><a href="#"
                                                class="dataTable-sorter">Clients</a></th>
                                          <th data-sortable="" class="text-center"><a href="#"
                                                class="dataTable-sorter">Invoices</a></th>
                                          <th data-sortable="" class="text-center"><a href="#"
                                                class="dataTable-sorter">Paid</a></th>
                                          <th data-sortable="" class="text-center"><a href="#"
                                                class="dataTable-sorter">Unpaid</a></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Status</a></th>
                                          <th width="200px" data-sortable="" style="width: 8.78764%;"><a href="#"
                                                class="dataTable-sorter">Action</a></th>
                                       </tr>
                                    </thead>
                                    <tbody>
                                       <?php foreach ($products as $rrow):
                                          $st = $statsById[(int) $rrow['membership_id']] ?? ['clients' => 0, 'inv' => 0, 'paid' => 0, 'unpaid' => 0]; ?>
                                          <tr>
                                             <td class="Id"><?php echo $rrow['membership_name']; ?></td>
                                             <td class="Id"><?php echo "$" . $rrow['membership_price']; ?></td>
                                             <td class="text-center"><span
                                                   class="pcol pcol-clients"><?php echo (int) $st['clients']; ?></span></td>
                                             <td class="text-center"><span
                                                   class="pcol pcol-inv"><?php echo (int) $st['inv']; ?></span></td>
                                             <td class="text-center"><span
                                                   class="pcol pcol-paid"><?php echo (int) $st['paid']; ?></span></td>
                                             <td class="text-center"><span
                                                   class="pcol pcol-unpaid"><?php echo (int) $st['unpaid']; ?></span></td>
                                             <td>
                                                <?php if ($rrow['membership_status'] == '1') { ?>
                                                   <span class="badge badge-pill badge-danger">Disabled</span>
                                                <?php } else { ?>
                                                   <span class="badge badge-pill badge-success">Active</span>
                                                <?php } ?>
                                             </td>
                                             <td>
                                                <button type="button"
                                                   class="btn btn-sm btn-info tooltip1 pstats-toggle"
                                                   data-id="<?php echo (int) $rrow['membership_id']; ?>"><i
                                                      class="ti ti-chart-bar text-white"></i><span
                                                      class="tooltiptext1">View trends</span></button>

                                                <?php if ($acc_add_edit_products == "Yes") { ?>
                                                   <button class="btn btn-sm btn-primary tooltip1" data-toggle="modal"
                                                      data-target="#editProduct<?php echo $rrow['membership_id']; ?>"><i
                                                         class="ti ti-pencil text-white"></i></i><span class="tooltiptext1">Edit
                                                         Service</span></button>

                                                   <button class="btn btn-sm btn-custom tooltip1" data-toggle="modal"
                                                      data-target="#toggleStatus<?php echo $rrow['membership_id']; ?>"><i
                                                         class="ti ti-ban text-white"></i><span
                                                         class="tooltiptext1"><?php echo ($rrow['membership_status'] == '1') ? 'Enable Service' : 'Disable Service'; ?></span></button>
                                                <?php } ?>

                                                <?php if ($usertype == '1') { ?>
                                                   <button class="btn btn-sm btn-custom tooltip1" data-toggle="modal"
                                                      data-target="#deleteProduct<?php echo $rrow['membership_id']; ?>"><i
                                                         class="ti ti-trash text-white"></i><span class="tooltiptext1">Delete
                                                         Service</span></button>
                                                <?php } else { ?>
                                                   <button class="btn btn-sm btn-custom tooltip1" disabled
                                                      style="opacity:0.5;cursor:not-allowed;border:none;"><i
                                                         class="ti ti-trash text-white"></i><span class="tooltiptext1">Admin
                                                         Only</span></button>
                                                <?php } ?>
                                             </td>
                                          </tr>

                                          <!-- Delete Product Modal -->
                                          <div class="modal fade" id="deleteProduct<?php echo $rrow['membership_id']; ?>">
                                             <div class="modal-dialog modal-md">
                                                <form method="POST">
                                                   <div class="modal-content">

                                                      <!-- Modal body -->
                                                      <div class="modal-body">
                                                         <div class="row mb-2">
                                                            <div class="col-md-12 mt-2">
                                                               <input name="product_id" type="hidden"
                                                                  value="<?php echo $rrow['membership_id']; ?>">
                                                               <div class="swal2-icon swal2-warning swal2-icon-show"
                                                                  style="display: flex;">
                                                                  <div class="swal2-icon-content">!</div>
                                                               </div>
                                                               <h2 class="swal2-title" id="swal2-title"
                                                                  style="display: block;">Are you sure?</h2>
                                                               <div class="swal2-html-container" id="swal2-html-container"
                                                                  style="display: block;">This action can not be undone. Do
                                                                  you want to continue?</div>
                                                            </div>
                                                         </div>
                                                      </div>

                                                      <!-- Modal footer -->
                                                      <div class="modal-footer" style="justify-content: center;">
                                                         <button type="button" class="btn btn-info"
                                                            data-dismiss="modal">Close</button>
                                                         <button type="submit" class="btn btn-primary"
                                                            name="delete_product">Yes Delete</button>
                                                      </div>

                                                   </div>
                                                </form>
                                             </div>
                                          </div>



                                          <!-- Toggle Status Modal -->
                                          <div class="modal fade" id="toggleStatus<?php echo $rrow['membership_id']; ?>">
                                             <div class="modal-dialog modal-md">
                                                <form method="POST">
                                                   <div class="modal-content">

                                                      <!-- Modal body -->
                                                      <div class="modal-body">
                                                         <div class="row mb-2">
                                                            <div class="col-md-12 mt-2">
                                                               <input name="product_id" type="hidden"
                                                                  value="<?php echo $rrow['membership_id']; ?>">
                                                               <div class="swal2-icon swal2-warning swal2-icon-show"
                                                                  style="display: flex;">
                                                                  <div class="swal2-icon-content">!</div>
                                                               </div>
                                                               <h2 class="swal2-title" id="swal2-title"
                                                                  style="display: block;">Are you sure?</h2>
                                                               <div class="swal2-html-container" id="swal2-html-container"
                                                                  style="display: block;">
                                                                  <?php echo ($rrow['membership_status'] == '1') ?
                                                                     'This will enable the service. Do you want to continue?' :
                                                                     'This will disable the service. Do you want to continue?'; ?>
                                                               </div>
                                                            </div>
                                                         </div>
                                                      </div>

                                                      <!-- Modal footer -->
                                                      <div class="modal-footer" style="justify-content: center;">
                                                         <button type="button" class="btn btn-info"
                                                            data-dismiss="modal">Close</button>
                                                         <button type="submit" class="btn btn-primary"
                                                            name="toggle_status"><?php echo ($rrow['membership_status'] == '1') ? 'Enable' : 'Disable'; ?></button>
                                                      </div>

                                                   </div>
                                                </form>
                                             </div>
                                          </div>



                                          <!-- Edit Product Modal -->
                                          <div class="modal fade" id="editProduct<?php echo $rrow['membership_id']; ?>">
                                             <div class="modal-dialog modal-lg">
                                                <form method="POST">
                                                   <div class="modal-content">

                                                      <!-- Modal Header -->
                                                      <div class="modal-header">
                                                         <h4 class="modal-title">Edit Service</h4>
                                                      </div>

                                                      <!-- Modal body -->
                                                      <div class="modal-body">
                                                         <div class="row mb-2">
                                                            <div class="col-md-12 mt-2">
                                                               <input name="product_id" type="hidden"
                                                                  value="<?php echo $rrow['membership_id']; ?>">
                                                               <label for="startdate" class="form-label">Service:</label>
                                                               <input class="form-control" required="required"
                                                                  name="product_name" type="text" placeholder="Service Name"
                                                                  value="<?php echo $rrow['membership_name']; ?>">

                                                               <label for="startdate" class="form-label">Price:</label>
                                                               <input class="form-control" required="required"
                                                                  name="product_price" type="text" placeholder="Service Price"
                                                                  value="<?php echo $rrow['membership_price']; ?>">
                                                            </div>
                                                         </div>
                                                         <div class="row">
                                                            <?php echo customizer_render_custom_fields_block($con, 'memberships', customizer_record_values($con, 'memberships', (int) $rrow['membership_id'])); ?>
                                                         </div>
                                                      </div>

                                                      <!-- Modal footer -->
                                                      <div class="modal-footer">
                                                         <button type="button" class="btn btn-info"
                                                            data-dismiss="modal">Close</button>
                                                         <button type="submit" class="btn btn-primary"
                                                            name="edit_product">Update Value</button>
                                                      </div>

                                                   </div>
                                                </form>
                                             </div>
                                          </div>


                                       <?php endforeach; ?>
                                    </tbody>
                                 </table>
                              </div>
                              <div class="dataTable-bottom">
                                 <?php if (ceil($total_pages / $num_results_on_page) > 0): ?>

                                    <ul class="news">
                                       <?php if ($page > 1): ?>
                                          <li class="prev"><a href="memberships.php?page=<?php echo $page - 1 ?>">Prev</a></li>
                                       <?php endif; ?>

                                       <?php if ($page > 3): ?>
                                          <li class="start"><a href="memberships.php?page=1">1</a></li>
                                          <li class="dots">...</li>
                                       <?php endif; ?>

                                       <?php if ($page - 2 > 0): ?>
                                          <li class="page"><a
                                                href="memberships.php?page=<?php echo $page - 2 ?>"><?php echo $page - 2 ?></a>
                                          </li>
                                       <?php endif; ?>
                                       <?php if ($page - 1 > 0): ?>
                                          <li class="page"><a
                                                href="memberships.php?page=<?php echo $page - 1 ?>"><?php echo $page - 1 ?></a>
                                          </li>
                                       <?php endif; ?>

                                       <li class="currentpage"><a
                                             href="memberships.php?page=<?php echo $page ?>"><?php echo $page ?></a></li>

                                       <?php if ($page + 1 < ceil($total_pages / $num_results_on_page) + 1): ?>
                                          <li class="page"><a
                                                href="memberships.php?page=<?php echo $page + 1 ?>"><?php echo $page + 1 ?></a>
                                          </li>
                                       <?php endif; ?>
                                       <?php if ($page + 2 < ceil($total_pages / $num_results_on_page) + 1): ?>
                                          <li class="page"><a
                                                href="memberships.php?page=<?php echo $page + 2 ?>"><?php echo $page + 2 ?></a>
                                          </li>
                                       <?php endif; ?>

                                       <?php if ($page < ceil($total_pages / $num_results_on_page) - 2): ?>
                                          <li class="dots">...</li>
                                          <li class="end"><a
                                                href="memberships.php?page=<?php echo ceil($total_pages / $num_results_on_page) ?>"><?php echo ceil($total_pages / $num_results_on_page) ?></a>
                                          </li>
                                       <?php endif; ?>

                                       <?php if ($page < ceil($total_pages / $num_results_on_page)): ?>
                                          <li class="next"><a href="memberships.php?page=<?php echo $page + 1 ?>">Next</a></li>
                                       <?php endif; ?>
                                    </ul>
                                 <?php endif; ?>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>


               <!-- Add New Product Modal -->
               <div class="modal fade" id="addProduct">
                  <div class="modal-dialog modal-lg">
                     <form method="POST">
                        <div class="modal-content">

                           <!-- Modal Header -->
                           <div class="modal-header">
                              <h4 class="modal-title">Add New Service</h4>
                           </div>

                           <!-- Modal body -->
                           <div class="modal-body">
                              <div class="row mb-2">
                                 <div class="col-md-12 mt-2">
                                    <label for="startdate" class="form-label">Service:</label>
                                    <input class="form-control" required="required" name="product_name" type="text"
                                       placeholder="Service Name">
                                    <br>
                                    <label for="startdate" class="form-label">Price:</label>
                                    <input class="form-control" required="required" name="product_price" type="text"
                                       placeholder="Service Price">
                                 </div>
                              </div>
                              <div class="row">
                                 <?php echo customizer_render_custom_fields_block($con, 'memberships'); ?>
                              </div>
                           </div>

                           <!-- Modal footer -->
                           <div class="modal-footer">
                              <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                              <button type="submit" class="btn btn-primary" name="add_new">Add New</button>
                           </div>

                        </div>
                     </form>
                  </div>
               </div>

            </div>
            <!-- [ Main Content ] end -->
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

         .pcol {
            display: inline-block;
            min-width: 34px;
            padding: 3px 9px;
            border-radius: 999px;
            font-weight: 700;
            font-size: .82rem;
            background: #eef2f7;
            color: #334155;
         }

         .pcol-clients {
            background: #e0f2fe;
            color: #075985;
         }

         .pcol-paid {
            background: #dcfce7;
            color: #166534;
         }

         .pcol-unpaid {
            background: #fee2e2;
            color: #991b1b;
         }

         .pstats-row > td {
            background: #f6f8fc;
            padding: 0 !important;
         }

         .pstats-panel {
            padding: 16px 18px;
         }

         .pstats-title {
            font-size: .82rem;
            font-weight: 700;
            color: #252161;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
         }

         .pstats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
         }

         .pstat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 14px;
            border-left: 4px solid #252161;
         }

         .pstat-card.tone-green {
            border-left-color: #16a34a;
         }

         .pstat-card.tone-amber {
            border-left-color: #f59e0b;
         }

         .pstat-card.tone-red {
            border-left-color: #ef4444;
         }

         .pstat-card.tone-blue {
            border-left-color: #0ea5e9;
         }

         .pstat-label {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            font-weight: 700;
         }

         .pstat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.1;
            margin-top: 4px;
         }

         .pstats-charts {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 14px;
         }

         .pchart-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 14px;
         }

         .pchart-card.pchart-wide {
            grid-column: 1 / -1;
         }

         .pchart-title {
            font-size: .78rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
         }

         .pchart-empty {
            padding: 30px 10px;
            text-align: center;
            color: #94a3b8;
            font-size: .82rem;
         }

         @media (max-width: 767.98px) {
            .pstats-charts {
               grid-template-columns: 1fr;
            }
         }

         .pstats-loading {
            padding: 20px 18px;
            color: #64748b;
            font-size: .88rem;
         }

         .pstats-toggle.open {
            background: #252161;
            border-color: #252161;
         }
      </style>
      <script>
         (function () {
            let panelCharts = [];

            function clearPanels() {
               panelCharts.forEach(c => { try { c.destroy(); } catch (e) { } });
               panelCharts = [];
               document.querySelectorAll('.pstats-row').forEach(r => r.remove());
               document.querySelectorAll('.pstats-toggle.open').forEach(b => b.classList.remove('open'));
            }

            function panelHtml(d, uid) {
               return `
                  <div class="pstats-panel">
                     <div class="pstats-title"><i class="ti ti-chart-bar"></i> ${d.name ? d.name : 'Service'} — service trends</div>
                     <div class="pstats-charts">
                        <div class="pchart-card pchart-wide">
                           <div class="pchart-title">Service trend by time</div>
                           <div id="chartTime${uid}" class="pchart-body"></div>
                        </div>
                        <div class="pchart-card">
                           <div class="pchart-title">By client types</div>
                           <div id="chartType${uid}" class="pchart-body"></div>
                        </div>
                        <div class="pchart-card">
                           <div class="pchart-title">By client categories</div>
                           <div id="chartCat${uid}" class="pchart-body"></div>
                        </div>
                     </div>
                  </div>`;
            }

            function noData(el) {
               el.innerHTML = '<div class="pchart-empty">No data available.</div>';
            }

            function renderTimeChart(el, rows) {
               if (!rows || !rows.length) return noData(el);
               const chart = new ApexCharts(el, {
                  chart: { type: 'area', height: 230, toolbar: { show: false } },
                  series: [{ name: 'Invoices', data: rows.map(r => r.count) }],
                  xaxis: { categories: rows.map(r => r.label) },
                  colors: ['#252161'],
                  dataLabels: { enabled: false },
                  stroke: { curve: 'smooth', width: 2 },
                  fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .05 } },
                  grid: { borderColor: '#eef2f7' }
               });
               chart.render();
               panelCharts.push(chart);
            }

            function renderBarChart(el, rows, color) {
               if (!rows || !rows.length) return noData(el);
               const chart = new ApexCharts(el, {
                  chart: { type: 'bar', height: 230, toolbar: { show: false } },
                  plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '70%' } },
                  series: [{ name: 'Clients', data: rows.map(r => r.count) }],
                  xaxis: { categories: rows.map(r => r.label) },
                  colors: [color],
                  dataLabels: { enabled: true },
                  grid: { borderColor: '#eef2f7' }
               });
               chart.render();
               panelCharts.push(chart);
            }

            document.addEventListener('click', function (e) {
               const btn = e.target.closest('.pstats-toggle');
               if (!btn) return;
               e.preventDefault();
               const tr = btn.closest('tr');
               const id = btn.getAttribute('data-id');
               if (!tr || !id) return;

               // Collapse if this row's panel is already open.
               const next = tr.nextElementSibling;
               const alreadyOpen = next && next.classList.contains('pstats-row') && next.dataset.for === id;
               clearPanels();
               if (alreadyOpen) return;

               const colCount = tr.children.length;
               const detail = document.createElement('tr');
               detail.className = 'pstats-row';
               detail.dataset.for = id;
               detail.innerHTML = `<td colspan="${colCount}"><div class="pstats-loading"><i class="ti ti-loader-2"></i> Loading insights…</div></td>`;
               tr.parentNode.insertBefore(detail, tr.nextSibling);
               btn.classList.add('open');

               fetch('memberships.php?ajax=product_stats&membership_id=' + encodeURIComponent(id), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                  .then(r => r.json())
                  .then(d => {
                     if (!d || !d.success) {
                        detail.querySelector('td').innerHTML = '<div class="pstats-loading text-danger">Unable to load insights.</div>';
                        return;
                     }
                     detail.querySelector('td').innerHTML = panelHtml(d, id);
                     renderTimeChart(detail.querySelector('#chartTime' + id), d.trend_by_time);
                     renderBarChart(detail.querySelector('#chartType' + id), d.by_type, '#0ea5e9');
                     renderBarChart(detail.querySelector('#chartCat' + id), d.by_category, '#f59e0b');
                  })
                  .catch(() => {
                     detail.querySelector('td').innerHTML = '<div class="pstats-loading text-danger">Network error while loading insights.</div>';
                  });
            });
         })();
      </script>

   </body>

   </html>
<?php } ?> 