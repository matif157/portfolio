<?php
  include 'includes/db.php';
  include "includes/auth.php";
  error_reporting(0);

    if (isset($_POST['add_new'])) {
    $msg=''; 
    
    $entity_form=$_POST['entity_form'];

    $queryy="INSERT INTO entityform(entity_form_name) VALUES('$entity_form')";    
    $insert_query = mysqli_query($con,$queryy);
    
    if($insert_query){
            $msg.="<div class='alert alert-success'>Entity Form Has Been Added Successfully.</div>";
        }
    else{
           $msg.="<div class='alert alert-danger'>Something Went Wrong.</div>".$con->error; 
        }

   }

   if (isset($_POST['edit_product'])) {
    $msg=''; 
    
    $entity_form_name=$_POST['entity_form_name'];
    $entity_form_id=$_POST['entity_form_id'];

    $queryy="UPDATE entityform SET entity_form_name='$entity_form_name' where entity_form_id='$entity_form_id'";    
    $insert_query = mysqli_query($con,$queryy);
    
    if($insert_query){
            $msg.="<div class='alert alert-success'>Entity Form Has Been Updated Successfully.</div>";
        }
    else{
           $msg.="<div class='alert alert-danger'>Something Went Wrong.</div>".$con->error; 
        }

   }

    if (isset($_POST['delete_product'])) {
    $msg=''; 
    
   $entity_form_id=$_POST['entity_form_id'];

    $queryy="DELETE FROM entityform where entity_form_id='$entity_form_id'";    
    $insert_query = mysqli_query($con,$queryy);
    
    if($insert_query){
            $msg.="<div class='alert alert-success'>Entity Form Has Been Deleted Successfully.</div>";
        }
    else{
           $msg.="<div class='alert alert-danger'>Something Went Wrong.</div>".$con->error; 
        }

   }


 ?>
<?php
// Below is optional, remove if you have already connected to your database.
$mysqli = mysqli_connect('localhost', $username,$password,$database);

$quer="SELECT * FROM entityform ORDER By entity_form_id desc"; 

$total_pages = $mysqli->query($quer)->num_rows;

// Check if the page number is specified and check if it's a number, if not return the default page number which is 1.
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;

// Number of results to show on each page.
$num_results_on_page = 25;
 $que="SELECT * FROM entityform ORDER BY entity_form_id DESC LIMIT ?,?";


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
                           <h4 class="m-b-10">Entity Form</h4>
                        </div>
                        <ul class="breadcrumb">
                           <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                           <li class="breadcrumb-item">Entity Form</li>
                        </ul>
                     </div>
                     <div class="col">
                        <div class="float-end">
                            <?php if ($acc_add_edit_entity=="Yes") {?> 
                            <button class="btn btn-primary" data-toggle="modal" data-target="#addEntity">Add Entity Form</button>
                              <?php } ?>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="row">
               <div class="col-sm-12">
                   <?php if(isset($msg)){ echo $msg; } ?>
                  <!-- [ Main Content ] start -->
                  <div class="card">
                     <div class="card-body table-border-style">
                        <div class="table-responsive">
                           <div class="dataTable-wrapper dataTable-loading no-footer sortable searchable fixed-columns">
                              <!-- <div class="dataTable-top">
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
                                 <div class="dataTable-search"><input class="dataTable-input" placeholder="Search..." type="text"></div>
                              </div> -->




                              <div class="dataTable-container">
                                  <table class="table datatable">
                                    <thead>
                                       <tr>
                                          <th data-sortable="" style="width: 13.3725%;"><a href="#" class="dataTable-sorter">Products</a></th>
                                          <th width="200px" data-sortable="" style="width: 8.78764%;"><a href="#" class="dataTable-sorter">Action</a></th>
                                       </tr>
                                    </thead>
                                    <tbody>
                                       <?php while ($rrow = $result->fetch_assoc()): ?>
                                       <tr>
                                          <td class="Id"><?php echo $rrow['entity_form_name']; ?></td>
                                          <td>
                                             <?php if ($acc_add_edit_entity=="Yes") {?> 
                                             <button class="btn btn-sm btn-primary tooltip1" data-toggle="modal" data-target="#editProduct<?php echo $rrow['entity_form_id']; ?>"><i class="ti ti-pencil text-white"></i></i><span class="tooltiptext1">Edit Product</span></button>
                                             <?php } ?>

                                             <?php if ($acc_delete_entity=="Yes") {?> 
                                             <button class="btn btn-sm btn-custom tooltip1" data-toggle="modal" data-target="#deleteProduct<?php echo $rrow['entity_form_id']; ?>"><i class="ti ti-trash text-white"></i><span class="tooltiptext1">Delete Product</span></button><?php }?>
                                          </td>
                                       </tr>

         <!-- Delete Product Modal -->
           <div class="modal fade" id="deleteProduct<?php echo $rrow['entity_form_id']; ?>">
             <div class="modal-dialog modal-md">
             <form method="POST">
               <div class="modal-content">
               
                 <!-- Modal body -->
                 <div class="modal-body">
                   <div class="row mb-2">
                     <div class="col-md-12 mt-2">
                        <input name="entity_form_id" type="hidden" value="<?php echo $rrow['entity_form_id']; ?>"> 
                        <div class="swal2-icon swal2-warning swal2-icon-show" style="display: flex;"><div class="swal2-icon-content">!</div></div>
                        <h2 class="swal2-title" id="swal2-title" style="display: block;">Are you sure?</h2>
                        <div class="swal2-html-container" id="swal2-html-container" style="display: block;">This action can not be undone. Do you want to continue?</div>
                     </div>
                   </div>  
                 </div>
                 
                 <!-- Modal footer -->
                 <div class="modal-footer" style="justify-content: center;">
                   <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                   <button type="submit" class="btn btn-primary" name="delete_product">Yes Delete</button>
                 </div>
                 
               </div>
               </form>
             </div>
           </div>



         <!-- Edit Product Modal -->
           <div class="modal fade" id="editProduct<?php echo $rrow['entity_form_id']; ?>">
             <div class="modal-dialog modal-lg">
             <form method="POST">
               <div class="modal-content">
               
                 <!-- Modal Header -->
                 <div class="modal-header">
                   <h4 class="modal-title">Edit Product</h4>
                 </div>
                 
                 <!-- Modal body -->
                 <div class="modal-body">
                   <div class="row mb-2">
                     <div class="col-md-12 mt-2">
                        <input name="entity_form_id" type="hidden" value="<?php echo $rrow['entity_form_id']; ?>"> 
                      <label for="startdate" class="form-label">Product:</label>
                      <input class="form-control" required="required" name="entity_form_name" type="text" placeholder="Product Name" value="<?php echo $rrow['entity_form_name']; ?>"> 
                     </div>
                   </div>  
                 </div>
                 
                 <!-- Modal footer -->
                 <div class="modal-footer">
                   <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                   <button type="submit" class="btn btn-primary" name="edit_product">Update Value</button>
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
            <li class="prev"><a href="memberships.php?page=<?php echo $page-1 ?>">Prev</a></li>
            <?php endif; ?>

            <?php if ($page > 3): ?>
            <li class="start"><a href="memberships.php?page=1">1</a></li>
            <li class="dots">...</li>
            <?php endif; ?>

            <?php if ($page-2 > 0): ?><li class="page"><a href="memberships.php?page=<?php echo $page-2 ?>"><?php echo $page-2 ?></a></li><?php endif; ?>
            <?php if ($page-1 > 0): ?><li class="page"><a href="memberships.php?page=<?php echo $page-1 ?>"><?php echo $page-1 ?></a></li><?php endif; ?>

            <li class="currentpage"><a href="memberships.php?page=<?php echo $page ?>"><?php echo $page ?></a></li>

            <?php if ($page+1 < ceil($total_pages / $num_results_on_page)+1): ?><li class="page"><a href="memberships.php?page=<?php echo $page+1 ?>"><?php echo $page+1 ?></a></li><?php endif; ?>
            <?php if ($page+2 < ceil($total_pages / $num_results_on_page)+1): ?><li class="page"><a href="memberships.php?page=<?php echo $page+2 ?>"><?php echo $page+2 ?></a></li><?php endif; ?>

            <?php if ($page < ceil($total_pages / $num_results_on_page)-2): ?>
            <li class="dots">...</li>
            <li class="end"><a href="memberships.php?page=<?php echo ceil($total_pages / $num_results_on_page) ?>"><?php echo ceil($total_pages / $num_results_on_page) ?></a></li>
            <?php endif; ?>

            <?php if ($page < ceil($total_pages / $num_results_on_page)): ?>
            <li class="next"><a href="memberships.php?page=<?php echo $page+1 ?>">Next</a></li>
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
           <div class="modal fade" id="addEntity">
             <div class="modal-dialog modal-lg">
             <form method="POST">
               <div class="modal-content">
               
                 <!-- Modal Header -->
                 <div class="modal-header">
                   <h4 class="modal-title">Add New Entity Form</h4>
                 </div>
                 
                 <!-- Modal body -->
                 <div class="modal-body">
                   <div class="row mb-2">
                     <div class="col-md-12 mt-2">
                      <label for="startdate" class="form-label">Entity Form:</label>
                      <input class="form-control" required="required" name="entity_form" type="text" placeholder="Entity Form"> 
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
            <style>        .news {
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
         .news .next a, .news .prev a {
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
   $stmt->close();
}
?>