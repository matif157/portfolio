<?php
include 'includes/db.php';
include "includes/auth.php";
error_reporting(0);

if (isset($_POST['add_new'])) {
   $msg = '';

   $client_type = $_POST['client_type'];


   $queryy = "INSERT INTO member_type(type_name) VALUES(?)";
   $stmt = mysqli_prepare($con, $queryy);
   mysqli_stmt_bind_param($stmt, "s", $client_type);
   $insert_query = mysqli_stmt_execute($stmt);

   if ($insert_query) {
      $msg .= "<div class='alert alert-success'>Client Type Has Been Added Successfully.</div>";
   } else {
      $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>";
   }
}

if (isset($_POST['edit_product'])) {
   $msg = '';
   $type_id = $_POST['type_id'];
   $type_name = $_POST['type_name'];


   $queryy = "UPDATE member_type SET type_name=? where type_id=?";
   $stmt = mysqli_prepare($con, $queryy);
   mysqli_stmt_bind_param($stmt, "si", $type_name, $type_id);
   $insert_query = mysqli_stmt_execute($stmt);

   if ($insert_query) {
      $msg .= "<div class='alert alert-success'>Client Type Has Been Updated Successfully.</div>";
   } else {
      $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
   }
}

if (isset($_POST['delete_product'])) {
   $msg = '';

   if ($usertype != '1') {
      $msg .= "<div class='alert alert-danger'>Only admin can delete a client type.</div>";
   } else {
      $type_id = $_POST['type_id'];

      $queryy = "DELETE FROM member_type where type_id=?";
      $stmt = mysqli_prepare($con, $queryy);
      mysqli_stmt_bind_param($stmt, "i", $type_id);
      $insert_query = mysqli_stmt_execute($stmt);

      if ($insert_query) {
         $msg .= "<div class='alert alert-success'>Client Type Has Been Deleted Successfully.</div>";
      } else {
         $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
      }
   }
}

if (isset($_POST['toggle_status'])) {
   $msg = '';
   $type_id = $_POST['type_id'];

   // Get current status (0 = Active, 1 = Disabled)
   $status_stmt = mysqli_prepare($con, "SELECT type_status FROM member_type WHERE type_id=?");
   mysqli_stmt_bind_param($status_stmt, "i", $type_id);
   mysqli_stmt_execute($status_stmt);
   $current_status = mysqli_fetch_assoc(mysqli_stmt_get_result($status_stmt))['type_status'];

   // Toggle status
   $new_status = ($current_status == '0') ? '1' : '0';
   $queryy = "UPDATE member_type SET type_status=? where type_id=?";
   $stmt = mysqli_prepare($con, $queryy);
   mysqli_stmt_bind_param($stmt, "ii", $new_status, $type_id);
   $insert_query = mysqli_stmt_execute($stmt);

   if ($insert_query) {
      $status_text = ($new_status == '1') ? 'Disabled' : 'Enabled';
      $msg .= "<div class='alert alert-success'>Client Type Has Been $status_text Successfully.</div>";
   } else {
      $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
   }
}


$sql = "
SELECT
    mt.type_id,
    mt.type_name,
    IFNULL(mc.client_count, 0) AS client_count,
    IFNULL(ic.invoice_count, 0) AS invoice_count,
    IFNULL(ic.paid_count, 0) AS paid_count,
    IFNULL(ic.partial_count, 0) AS partial_count,
    IFNULL(ic.unpaid_count, 0) AS unpaid_count
FROM member_type mt
LEFT JOIN (
    SELECT type, COUNT(*) AS client_count
    FROM members
    GROUP BY type
) mc ON mc.type = mt.type_id
LEFT JOIN (
    SELECT
        m.type,
        COUNT(bi.invoice_id) AS invoice_count,
        SUM(bi.invoice_status = 3) AS paid_count,
        SUM(bi.invoice_status = 2) AS partial_count,
        SUM(bi.invoice_status = 1) AS unpaid_count
    FROM members m
    JOIN billing_invoice bi ON bi.mem_id = m.member_id
    GROUP BY m.type
) ic ON ic.type = mt.type_id
ORDER BY client_count DESC
";

$res = mysqli_query($con, $sql);

$clientTypes = [];
$clientCounts = [];
$invoiceCounts = [];
$paidCounts = [];
$partialCounts = [];
$unpaidCounts = [];

while ($row = mysqli_fetch_assoc($res)) {
   $type_id = $row['type_id'];
   $type_name = $row['type_name'];

   $clientTypes[$type_id] = [
      'name' => $type_name,
      'client_count' => $row['client_count'],
      'invoice_count' => $row['invoice_count'],
      'paid_count' => $row['paid_count'],
      'partial_count' => $row['partial_count'],
      'unpaid_count' => $row['unpaid_count']
   ];
}


$tot = mysqli_fetch_assoc(
   mysqli_query($con, "
        SELECT
            (SELECT COUNT(*) FROM members) AS total_clients,
            (SELECT COUNT(*) FROM billing_invoice) AS total_invoices,
            (SELECT COALESCE(SUM(total_amount),0) FROM billing_invoice) AS total_revenue
    ")
);

$totalStats = [
   'total_clients' => $tot['total_clients'],
   'total_invoices' => $tot['total_invoices'],
   'total_revenue' => $tot['total_revenue']
];


$quer = "SELECT * FROM member_type ORDER By type_name ASC";
$total_pages = mysqli_query($con, $quer)->num_rows;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;

$num_results_on_page = 25;
$que = "SELECT * FROM member_type ORDER BY type_name ASC LIMIT ?,?";

$stmt = mysqli_prepare($con, $que);

$calc_page = ($page - 1) * $num_results_on_page;
mysqli_stmt_bind_param($stmt, 'ii', $calc_page, $num_results_on_page);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
   <title>Syed Professional Services - Dashboard</title>
   <?php include "includes/links.php"; ?>
   <style>
      .client-types-container {
         display: grid;
         grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
         gap: 20px;
         margin-bottom: 30px;
      }

      .client-type-card {
         background: #ffffff;
         border-radius: 12px;
         box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
         padding: 20px;
         display: flex;
         flex-direction: column;
         border-left: 4px solid #6FD943;
         transition: all 0.3s ease;
         position: relative;
         overflow: hidden;
         height: 100%;
      }

      .client-type-card::before {
         content: '';
         position: absolute;
         top: 0;
         left: 0;
         width: 100%;
         height: 100%;
         background: linear-gradient(135deg, rgba(111, 217, 67, 0.05) 0%, rgba(255, 255, 255, 0) 50%);
         z-index: 0;
      }

      .client-type-card:hover {
         transform: translateY(-5px);
         box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
         border-left-width: 6px;
      }

      .client-type-header {
         display: flex;
         align-items: center;
         margin-bottom: 15px;
         position: relative;
         z-index: 1;
      }

      .client-type-icon {
         width: 40px;
         height: 40px;
         background-color: rgba(111, 217, 67, 0.1);
         border-radius: 50%;
         display: flex;
         align-items: center;
         justify-content: center;
         margin-right: 12px;
         font-size: 1.2rem;
      }

      .client-type-name {
         font-weight: 700;
         color: #2c3e50;
         font-size: 1.2rem;
         flex-grow: 1;
      }

      .client-type-stats {
         display: flex;
         flex-wrap: wrap;
         gap: 10px;
         position: relative;
         z-index: 1;
      }

      .stat-item {
         text-align: center;
         padding: 10px 15px;
         background-color: rgba(111, 217, 67, 0.1);
         border-radius: 8px;
         flex: 1;
         min-width: 70px;
         transition: all 0.2s ease;
      }

      .stat-item:hover {
         background-color: rgba(111, 217, 67, 0.2);
         transform: scale(1.05);
      }

      .stat-number {
         font-size: 1.5rem;
         font-weight: 700;
         color: #6FD943;
         line-height: 1.2;
      }

      .stat-label {
         font-size: 0.7rem;
         color: #6c757d;
         text-transform: uppercase;
         letter-spacing: 0.5px;
         margin-top: 4px;
         font-weight: 600;
      }

      @keyframes pulse {
         0% {
            transform: scale(1);
         }

         50% {
            transform: scale(1.05);
         }

         100% {
            transform: scale(1);
         }
      }

      .row.mb-4 h5 {
         color: #2c3e50;
         font-weight: 700;
         font-size: 1.5rem;
         position: relative;
         padding-bottom: 10px;
         margin-bottom: 20px;
      }

      .row.mb-4 h5::after {
         content: '';
         position: absolute;
         bottom: 0;
         left: 0;
         width: 60px;
         height: 3px;
         background-color: #6FD943;
         border-radius: 3px;
      }

      @media (max-width: 768px) {
         .client-types-container {
            grid-template-columns: 1fr;
         }

         .stat-item {
            min-width: 60px;
            padding: 8px 10px;
         }

         .stat-number {
            font-size: 1.2rem;
         }
      }

      @media (max-width: 480px) {
         .client-type-stats {
            gap: 5px;
         }

         .stat-item {
            min-width: 50px;
            padding: 6px 8px;
         }

         .stat-number {
            font-size: 1rem;
         }

         .stat-label {
            font-size: 0.6rem;
         }
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
                        <h4 class="m-b-10">Client Types</h4>
                     </div>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item">Client Types</li>
                     </ul>
                  </div>
                  <div class="col">
                     <div class="float-end">
                        <button class="btn btn-primary" data-toggle="modal" data-target="#addClient">Add Client
                           Type</button>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <div class="row mb-4">
            <div class="col-sm-12">
               <h5 class="mb-3">Client Type Breakdown</h5>
            </div>
         </div>

         <div class="client-types-container">
            <?php foreach ($clientTypes as $type_id => $typeData): ?>
               <div class="client-type-card">
                  <div class="client-type-header">
                     <div class="client-type-icon">📋</div>
                     <div class="client-type-name"><?php echo $typeData['name']; ?></div>
                  </div>

                  <div class="client-type-stats">


                     <div class="stat-item">
                        <div class="stat-number">
                           <?php echo number_format($typeData['invoice_count']); ?>
                        </div>
                        <div class="stat-label">Invoices</div>
                     </div>

                     <div class="stat-item">
                        <div class="stat-number">
                           <?php echo number_format($typeData['paid_count']); ?>
                        </div>
                        <div class="stat-label">Paid</div>
                     </div>

                     <div class="stat-item">
                        <div class="stat-number">
                           <?php echo number_format($typeData['partial_count']); ?>
                        </div>
                        <div class="stat-label">Partial</div>
                     </div>

                     <div class="stat-item">
                        <div class="stat-number">
                           <?php echo number_format($typeData['unpaid_count']); ?>
                        </div>
                        <div class="stat-label">Unpaid</div>
                     </div>
                     <div class="stat-item">
                        <div class="stat-number">
                           <?php echo number_format($typeData['client_count']); ?>
                        </div>
                        <div class="stat-label">Clients</div>
                     </div>
                  </div>
               </div>
            <?php endforeach; ?>
         </div>
 

         <div class="row">
            <div class="col-sm-12">
               <?php if (isset($msg)) {
                  echo $msg;
               } ?>

               <div class="card">
                  <div class="card-body table-border-style">
                     <div class="table-responsive">
                        <div class="dataTable-wrapper dataTable-loading no-footer sortable searchable fixed-columns">
                           <div class="dataTable-container">
                              <table class="table datatable">
                                 <thead>
                                    <tr>
                                       <th data-sortable="" style="width: 13.3725%;"><a href="#"
                                             class="dataTable-sorter">Client Types</a></th>
                                       <th data-sortable="" style="width: 13.3725%;"><a href="#"
                                             class="dataTable-sorter">Status</a></th>
                                       <th width="200px" data-sortable="" style="width: 8.78764%;"><a href="#"
                                             class="dataTable-sorter">Action</a></th>
                                    </tr>
                                 </thead>
                                 <tbody>
                                    <?php while ($rrow = mysqli_fetch_assoc($result)): ?>
                                       <tr>
                                          <td class="Id"><?php echo $rrow['type_name']; ?></td>
                                          <td>
                                             <?php if ($rrow['type_status'] == '1') { ?>
                                                <span class="badge badge-pill badge-danger">Disabled</span>
                                             <?php } else { ?>
                                                <span class="badge badge-pill badge-success">Active</span>
                                             <?php } ?>
                                          </td>
                                          <td>
                                             <button class="btn btn-sm btn-primary tooltip1" data-toggle="modal"
                                                data-target="#editProduct<?php echo $rrow['type_id']; ?>"><i
                                                   class="ti ti-pencil text-white"></i></i><span class="tooltiptext1">Edit
                                                   Client</span></button>

                                             <button class="btn btn-sm btn-custom tooltip1" data-toggle="modal"
                                                data-target="#toggleStatus<?php echo $rrow['type_id']; ?>"><i
                                                   class="ti ti-ban text-white"></i><span
                                                   class="tooltiptext1"><?php echo ($rrow['type_status'] == '1') ? 'Enable Client' : 'Disable Client'; ?></span></button>
                                             <?php if ($usertype == '1') { ?>
                                                <button class="btn btn-sm btn-custom tooltip1" data-toggle="modal"
                                                   data-target="#deleteProduct<?php echo $rrow['type_id']; ?>"><i
                                                      class="ti ti-trash text-white"></i><span class="tooltiptext1">Delete
                                                      Client</span></button>
                                             <?php } else { ?>
                                                <button class="btn btn-sm btn-custom tooltip1" disabled
                                                   style="opacity:0.5;cursor:not-allowed;border:none;"><i
                                                      class="ti ti-trash text-white"></i><span class="tooltiptext1">Admin
                                                      Only</span></button>
                                             <?php } ?>
                                          </td>
                                       </tr>

                                 
                                       <div class="modal fade" id="deleteProduct<?php echo $rrow['type_id']; ?>">
                                          <div class="modal-dialog modal-md">
                                             <form method="POST">
                                                <div class="modal-content">

                                          
                                                   <div class="modal-body">
                                                      <div class="row mb-2">
                                                         <div class="col-md-12 mt-2">
                                                            <input name="type_id" type="hidden"
                                                               value="<?php echo $rrow['type_id']; ?>">
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



                                 
                                       <div class="modal fade" id="toggleStatus<?php echo $rrow['type_id']; ?>">
                                          <div class="modal-dialog modal-md">
                                             <form method="POST">
                                                <div class="modal-content">

                                                   <div class="modal-body">
                                                      <div class="row mb-2">
                                                         <div class="col-md-12 mt-2">
                                                            <input name="type_id" type="hidden"
                                                               value="<?php echo $rrow['type_id']; ?>">
                                                            <div class="swal2-icon swal2-warning swal2-icon-show"
                                                               style="display: flex;">
                                                               <div class="swal2-icon-content">!</div>
                                                            </div>
                                                            <h2 class="swal2-title" id="swal2-title"
                                                               style="display: block;">Are you sure?</h2>
                                                            <div class="swal2-html-container" id="swal2-html-container"
                                                               style="display: block;">
                                                               <?php echo ($rrow['type_status'] == '1') ?
                                                                  'This will enable the client type. Do you want to continue?' :
                                                                  'This will disable the client type. Do you want to continue?'; ?>
                                                            </div>
                                                         </div>
                                                      </div>
                                                   </div>

                                                   <div class="modal-footer" style="justify-content: center;">
                                                      <button type="button" class="btn btn-info"
                                                         data-dismiss="modal">Close</button>
                                                      <button type="submit" class="btn btn-primary"
                                                         name="toggle_status"><?php echo ($rrow['type_status'] == '1') ? 'Enable' : 'Disable'; ?></button>
                                                   </div>

                                                </div>
                                             </form>
                                          </div>
                                       </div>



                                       <div class="modal fade" id="editProduct<?php echo $rrow['type_id']; ?>">
                                          <div class="modal-dialog modal-lg">
                                             <form method="POST">
                                                <div class="modal-content">


                                                   <div class="modal-header">
                                                      <h4 class="modal-title">Edit Product</h4>
                                                   </div>

                                           
                                                   <div class="modal-body">
                                                      <div class="row mb-2">
                                                         <div class="col-md-12 mt-2">
                                                            <input name="type_id" type="hidden"
                                                               value="<?php echo $rrow['type_id']; ?>">
                                                            <label for="startdate" class="form-label">Product:</label>
                                                            <input class="form-control" required="required"
                                                               name="type_name" type="text" placeholder="Product Name"
                                                               value="<?php echo $rrow['type_name']; ?>">
                                                         </div>
                                                      </div>
                                                   </div>

                                      
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


                                    <?php endwhile; ?>
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


            <div class="modal fade" id="addClient">
               <div class="modal-dialog modal-lg">
                  <form method="POST">
                     <div class="modal-content">

            
                        <div class="modal-header">
                           <h4 class="modal-title">Add New Client Type</h4>
                        </div>

          
                        <div class="modal-body">
                           <div class="row mb-2">
                              <div class="col-md-12 mt-2">
                                 <label for="startdate" class="form-label">Client Type:</label>
                                 <input class="form-control" required="required" name="client_type" type="text"
                                    placeholder="Client Type">
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