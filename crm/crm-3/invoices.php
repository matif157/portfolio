<?php
include 'includes/db.php';
include "includes/auth.php";
error_reporting(0);

if (isset($_POST['addpayment'])) {
   $msg = '';
   $newstatus = '';

   $invoice_id = $_POST['invoice_id'];
   $totalamount = $_POST['totalamount'];
   $balance = $_POST['balance'];
   $recievedamount = $_POST['recievedamount'];
   $paidamount = $_POST['paidamount'];

   // $newpaid=$paidamount+$recievedamount;

   // $newbalance=$totalamount-$newpaid;


   $newpaid = $recievedamount;
   $newbalance = $balance - $newpaid;
   $newpaid = $newpaid + $paidamount;

   if ($newbalance <= 0) {
      $newstatus = '3';
   } elseif ($newbalance < $totalamount) {
      $newstatus = '2';
   } else {
      $newstatus = '1';
   }



   $queryy = "UPDATE billing_invoice SET total_amount='$totalamount',balance='$newbalance',paid_amount='$newpaid',invoice_status='$newstatus' where invoice_id='$invoice_id'";

   $insert_query = mysqli_query($con, $queryy);

   if ($insert_query) {
      $msg .= "<div class='alert alert-success'>Payment Has Been Added.</div>";
   } else {
      $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
   }
}

if (isset($_POST['delete_invoice'])) {
   $msg = '';

   $invoice_id = $_POST['invoice_id'];

   $queryy = "DELETE FROM billing_invoice where invoice_id='$invoice_id'";
   $insert_query = mysqli_query($con, $queryy);

   if ($insert_query) {
      $msg .= "<div class='alert alert-success'>Record Been Deleted Successfully.</div>";
   } else {
      $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
   }
}
?>

<?php
// Below is optional, remove if you have already connected to your database.
$mysqli = mysqli_connect('localhost', $username, $password, $database);

if (isset($_GET['status'])) {
   $status = $_GET['status'];
   $quer = "SELECT * FROM billing_invoice where invoice_status='$status' ORDER BY invoice_id DESC";
} else {
   $quer = "SELECT * FROM billing_invoice ORDER BY invoice_id DESC";
}

$total_pages = $mysqli->query($quer)->num_rows;

// Check if the page number is specified and check if it's a number, if not return the default page number which is 1.
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;

// Number of results to show on each page.
$num_results_on_page = 25;

if (isset($_GET['status'])) {
   $status = $_GET['status'];
   $que = "SELECT * FROM billing_invoice where invoice_status='$status' ORDER BY invoice_id DESC LIMIT ?,?";
} else {
   $que = "SELECT * FROM billing_invoice ORDER BY invoice_id DESC LIMIT ?,?";
}




if ($stmt = $mysqli->prepare($que)) {
   // Calculate the page to get the results we need from our table.
   $calc_page = ($page - 1) * $num_results_on_page;
   $stmt->bind_param('ii', $calc_page, $num_results_on_page);
   $stmt->execute();
   // Get the results...
   $result = $stmt->get_result();
   ?>

   <!DOCTYPE html>
   <html lang="en" dir="">
   <meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

   <head>
      <title>Syed Professional Services - Dashboard</title>
      <?php include "includes/links.php"; ?>
      <style type="text/css">
         .badge-warning {
            color: #fff;
            background-color: #c0a145;
         }
      </style>
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
                           <h4 class="m-b-10">Invoices</h4>
                        </div>
                        <ul class="breadcrumb">
                           <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                           <li class="breadcrumb-item">Invoices List</li>
                        </ul>
                     </div>
                     <div class="col">
                        <div class="float-end">

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
                              <div class="dataTable-top">
                                 <div class="dataTable-dropdown">
                                 </div>
                                 <div class="dataTable-search" style="width: 36%;">
                                    <form action="invoices.php">
                                       <select class="form-control" name="status" onchange="this.form.submit()">
                                          <option value="">Select Any Option</option>
                                          <option value="3">Paid</option>
                                          <option value="1">UnPaid</option>
                                          <option value="2">Partial Paid</option>
                                       </select>
                                    </form>
                                 </div>
                              </div>




                              <div class="dataTable-container">
                                 <table class="table datatable">
                                    <thead>
                                       <tr>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Sr#</a></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Client</a></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Invoice</a></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Due Date</a></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Total</a></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Paid</a></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Balance</a></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Status</a></th>
                                          <th width="200px" data-sortable="" style="width: 8.78764%;"><a href="#"
                                                class="dataTable-sorter">Action</a></th>
                                       </tr>
                                    </thead>
                                    <tbody>
                                       <?php $c = 1;
                                       while ($Podrow = $result->fetch_assoc()): ?>
                                          <tr>
                                             <td><?php echo $c; ?></td>
                                             <td align="center">
                                                <?php $memid = $Podrow["mem_id"];
                                                $mem_querr = mysqli_query($con, "SELECT * FROM members where member_id='$memid'");
                                                while ($mem_genetics = mysqli_fetch_array($mem_querr)) {
                                                   echo $mem_genetics['name'] . ' ' . $mem_genetics['mname'] . ' ' . $mem_genetics['lname'];
                                                } ?>
                                             </td>

                                             <td align="center">
                                                <?php echo $Podrow["invoice_id"]; ?>
                                             </td>
                                             <td align="center">
                                                <?php $OSdate = $Podrow["due_date"]; ?>
                                                <?php echo date("D d M, Y", strtotime($OSdate)); ?>
                                             </td>
                                             <td align="center"><?php echo $Podrow["total_amount"]; ?></td>
                                             <td align="center"><?php echo $Podrow["paid_amount"]; ?>
                                             </td>
                                             <td align="center"><?php echo $Podrow["balance"]; ?>
                                             </td>
                                             <td align="center">
                                                <?php
                                                $invoice_status = $Podrow["invoice_status"];

                                                $fetch_status = mysqli_query($con, "SELECT * FROM billing_invoice_status WHERE status_id=$invoice_status");
                                                while ($fetch_record = mysqli_fetch_array($fetch_status)) {
                                                   $status_value = $fetch_record['status_text'];
                                                }
                                                ?>
                                                <?php if ($status_value == 'Not Paid') { ?>
                                                   <span class="badge badge-pill badge-danger">UnPaid</span>
                                                <?php } elseif ($status_value == 'Partial Paid') { ?>
                                                   <span class="badge badge-pill badge-warning">Partial Paid</span>
                                                <?php } elseif ($status_value == 'Paid') { ?>
                                                   <span class="badge badge-pill badge-success">Paid</span>
                                                <?php } ?>

                                             </td>
                                             <td>
                                                <a href="invoices/<?php echo $Podrow['filelink']; ?>">

                                                   <button class="btn btn-sm btn-primary tooltip1"><i
                                                         class="ti ti-eye text-white">
                                                      </i><span class="tooltiptext1">View Invoice</span></button>
                                                </a>
                                                <button class="btn btn-sm btn-custom tooltip1" data-toggle="modal"
                                                   data-bs-original-title="Delete Invoice"
                                                   data-target="#deleteclient<?php echo $Podrow['invoice_id']; ?>"><i
                                                      class="ti ti-trash text-white"></i><span class="tooltiptext1">Delete
                                                      Invoice</span></button>
                                                <button class="btn btn-sm btn-warning" data-toggle="modal"
                                                   data-target="#addpayment<?php echo $Podrow['invoice_id']; ?>">Payment</button>
                                             </td>
                                          </tr>
                                          <!-- Delete Product Modal -->
                                          <div class="modal fade" id="deleteclient<?php echo $Podrow['invoice_id']; ?>">
                                             <div class="modal-dialog modal-md">
                                                <form method="POST">
                                                   <div class="modal-content">

                                                      <!-- Modal body -->
                                                      <div class="modal-body">
                                                         <div class="row mb-2">
                                                            <div class="col-md-12 mt-2">
                                                               <input name="invoice_id" type="hidden"
                                                                  value="<?php echo $Podrow['invoice_id']; ?>">
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
                                                            name="delete_invoice">Yes Delete</button>
                                                      </div>

                                                   </div>
                                                </form>
                                             </div>
                                          </div>

                                          <!-- The Modal -->
                                          <div class="modal fade" id="addpayment<?php echo $Podrow['invoice_id']; ?>">
                                             <div class="modal-dialog modal-lg">
                                                <form method="POST">
                                                   <div class="modal-content">

                                                      <!-- Modal Header -->
                                                      <div class="modal-header">
                                                         <h4 class="modal-title">Add Payment For This Invoice</h4>
                                                      </div>

                                                      <!-- Modal body -->
                                                      <div class="modal-body">
                                                         <div class="row mb-2">
                                                            <input type="hidden" name="invoice_id"
                                                               value="<?php echo $Podrow['invoice_id']; ?>" />
                                                            <input type="hidden" name="paidamount"
                                                               value="<?php echo $Podrow['paid_amount']; ?>" />
                                                            <div class="col-md-6 mt-2">
                                                               <label for="startdate" class="form-label">Total Amount</label>
                                                               <input class="form-control" required="required"
                                                                  name="totalamount" type="text"
                                                                  value="<?php echo $Podrow['total_amount']; ?>">
                                                            </div>
                                                            <div class="col-md-6 mt-2">
                                                               <label for="expirydate" class="form-label">Balance</label>
                                                               <input class="form-control" required="required" name="balance"
                                                                  type="text" value="<?php echo $Podrow['balance']; ?>">
                                                            </div>
                                                            <div class="col-md-12 mt-2">
                                                               <label for="expirydate" class="form-label">Amount you want
                                                                  recieved?</label>
                                                               <input class="form-control" required="required"
                                                                  name="recievedamount" type="text">
                                                            </div>
                                                         </div>
                                                      </div>

                                                      <!-- Modal footer -->
                                                      <div class="modal-footer">
                                                         <button type="button" class="btn btn-info"
                                                            data-dismiss="modal">Close</button>
                                                         <button type="submit" class="btn btn-primary"
                                                            name="addpayment">Submit</button>
                                                      </div>

                                                   </div>
                                                </form>
                                             </div>
                                          </div>


                                          <?php $c++;
                                       endwhile; ?>
                                    </tbody>
                                 </table>
                              </div>
                              <div class="dataTable-bottom">
                                 <?php if (ceil($total_pages / $num_results_on_page) > 0): ?>
                                    <ul class="news">
                                       <?php
                                       // Preserve the status parameter in the URLs
                                       $status_param = isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : '';
                                       ?>

                                       <?php if ($page > 1): ?>
                                          <li class="prev"><a
                                                href="invoices.php?page=<?php echo $page - 1 . $status_param; ?>">Prev</a></li>
                                       <?php endif; ?>

                                       <?php if ($page > 3): ?>
                                          <li class="start"><a href="invoices.php?page=1<?php echo $status_param; ?>">1</a></li>
                                          <li class="dots">...</li>
                                       <?php endif; ?>

                                       <?php if ($page - 2 > 0): ?>
                                          <li class="page"><a
                                                href="invoices.php?page=<?php echo $page - 2 . $status_param; ?>"><?php echo $page - 2; ?></a>
                                          </li>
                                       <?php endif; ?>
                                       <?php if ($page - 1 > 0): ?>
                                          <li class="page"><a
                                                href="invoices.php?page=<?php echo $page - 1 . $status_param; ?>"><?php echo $page - 1; ?></a>
                                          </li>
                                       <?php endif; ?>

                                       <li class="currentpage"><a
                                             href="invoices.php?page=<?php echo $page . $status_param; ?>"><?php echo $page; ?></a>
                                       </li>

                                       <?php if ($page + 1 < ceil($total_pages / $num_results_on_page) + 1): ?>
                                          <li class="page"><a
                                                href="invoices.php?page=<?php echo $page + 1 . $status_param; ?>"><?php echo $page + 1; ?></a>
                                          </li>
                                       <?php endif; ?>
                                       <?php if ($page + 2 < ceil($total_pages / $num_results_on_page) + 1): ?>
                                          <li class="page"><a
                                                href="invoices.php?page=<?php echo $page + 2 . $status_param; ?>"><?php echo $page + 2; ?></a>
                                          </li>
                                       <?php endif; ?>

                                       <?php if ($page < ceil($total_pages / $num_results_on_page) - 2): ?>
                                          <li class="dots">...</li>
                                          <li class="end"><a
                                                href="invoices.php?page=<?php echo ceil($total_pages / $num_results_on_page) . $status_param; ?>"><?php echo ceil($total_pages / $num_results_on_page); ?></a>
                                          </li>
                                       <?php endif; ?>

                                       <?php if ($page < ceil($total_pages / $num_results_on_page)): ?>
                                          <li class="next"><a
                                                href="invoices.php?page=<?php echo $page + 1 . $status_param; ?>">Next</a></li>
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
               <div class="modal fade" id="addClient">
                  <div class="modal-dialog modal-lg">
                     <form method="POST">
                        <div class="modal-content">

                           <!-- Modal Header -->
                           <div class="modal-header">
                              <h4 class="modal-title">Add New Client Type</h4>
                           </div>

                           <!-- Modal body -->
                           <div class="modal-body">
                              <div class="row mb-2">
                                 <div class="col-md-12 mt-2">
                                    <label for="startdate" class="form-label">Client Type:</label>
                                    <input class="form-control" required="required" name="client_type" type="text"
                                       placeholder="Client Type">
                                 </div>
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
      </style>

   </body>
   <script>
      // Initialize tooltips globally
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
         return new bootstrap.Tooltip(tooltipTriggerEl);
      });
   </script>

   </html>
   <?php
   $stmt->close();
}
?>