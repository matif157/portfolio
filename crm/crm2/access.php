<?php include "includes/auth.php"; ?>
<?php
include "includes/db.php";
 if (isset($_POST['save_admin'])) {
    $msg='';
    $user_type="1";   

    $add_add_client=$add_view_client=$add_detail_client=$add_billing_client=$add_delete_client=$add_view_products=$add_add_edit_products=$add_delete_products=$add_view_staff=$add_add_edit_staff=$add_delete_staff=$add_view_entity=$add_add_edit_entity=$add_delete_entity=$add_view_product=$add_add_edit_product=$add_delete_product=$add_invoice_billing=$add_estimates_and_qutations=$add_bank_card_area=$add_client_active="";

    if (isset($_POST['add_add_client'])) {
      $add_add_client=$_POST['add_add_client'];
    }


    if (isset($_POST['add_view_client'])) {
      $add_view_client=$_POST['add_view_client'];
    }


    if (isset($_POST['add_detail_client'])) {
      $add_detail_client=$_POST['add_detail_client'];
    }


    if (isset($_POST['add_billing_client'])) {
      $add_billing_client=$_POST['add_billing_client'];
    }

    if (isset($_POST['add_delete_client'])) {
      $add_delete_client=$_POST['add_delete_client'];
    }

   if (isset($_POST['add_view_products'])) {
       $add_view_products=$_POST['add_view_products'];
    }


     if (isset($_POST['add_add_edit_products'])) {
       $add_add_edit_products=$_POST['add_add_edit_products'];
    }
    if (isset($_POST['add_delete_products'])) {
       $add_delete_products=$_POST['add_delete_products'];
    }
    if (isset($_POST['add_view_staff'])) {
       $add_view_staff=$_POST['add_view_staff'];
    }
    if (isset($_POST['add_add_edit_staff'])) {
       $add_add_edit_staff=$_POST['add_add_edit_staff'];
    }
    if (isset($_POST['add_delete_staff'])) {
      $add_delete_staff=$_POST['add_delete_staff']; 
    }
    if (isset($_POST['add_view_entity'])) {
       $add_view_entity=$_POST['add_view_entity'];
    }
    if (isset($_POST['add_add_edit_entity'])) {
       $add_add_edit_entity=$_POST['add_add_edit_entity'];
    }
    if (isset($_POST['add_delete_entity'])) {
       $add_delete_entity=$_POST['add_delete_entity'];
    }
    if (isset($_POST['add_view_product'])) {
       $add_view_product=$_POST['add_view_product'];
    }
    if (isset($_POST['add_add_edit_product'])) {
       $add_add_edit_product=$_POST['add_add_edit_product'];
    }
    if (isset($_POST['add_delete_product'])) {
       $add_delete_product=$_POST['add_delete_product'];
    } 

    if (isset($_POST['add_invoice_billing'])) {
       $add_invoice_billing=$_POST['add_invoice_billing'];
    } 

    if (isset($_POST['add_estimates_and_qutations'])) {
       $add_estimates_and_qutations=$_POST['add_estimates_and_qutations'];
    }  

    if (isset($_POST['add_bank_card_area'])) {
       $add_bank_card_area=$_POST['add_bank_card_area'];
    }  
    if (isset($_POST['add_client_active'])) {
       $add_client_active=$_POST['add_client_active'];
    }  

    


     $query="UPDATE `dashboard_access` SET `user_type`='1',`add_new_client`='$add_add_client',`view_clients_list`='$add_view_client',`client_detail_area`='$add_detail_client',`client_billing_area`='$add_billing_client',`delete_client`='$add_delete_client',`view_products`='$add_view_products',`add_edit_products`='$add_add_edit_products',`delete_products`='$add_delete_products',`view_staff`='$add_view_staff',`add_edit_staff`='$add_add_edit_staff',`delete_staff`='$add_delete_staff',`view_entity_form`='$add_view_entity',`add_edit_form`='$add_add_edit_entity',`delete_form`='$add_delete_entity',`view_product_cycle`='$add_view_product',`add_edit_cycle`='$add_add_edit_product',`delete_cycle`='$add_delete_product',invoice_and_billing='$add_invoice_billing',estimates_and_qutations='$add_estimates_and_qutations',bank_card_area='$add_bank_card_area', client_active='$add_client_active' WHERE `user_type`='1'";

 
    $insert_query = mysqli_query($con,$query);



    }



if (isset($_POST['save_manager'])) {
    $msg='';
    $user_type="2";   

    $man_add_client=$man_view_client=$man_detail_client=$man_billing_client=$man_delete_client=$man_view_products=$man_add_edit_products=$man_delete_products=$man_view_staff=$man_add_edit_staff=$man_delete_staff=$man_view_entity=$man_add_edit_entity=$man_delete_entity=$man_view_product=$man_add_edit_product=$man_delete_product=$man_invoice_billing=$man_estimates_and_qutations=$man_bank_card_area=$man_client_active="";

    if (isset($_POST['man_add_client'])) {
      $man_add_client=$_POST['man_add_client'];
    }


    if (isset($_POST['man_view_client'])) {
      $man_view_client=$_POST['man_view_client'];
    }


    if (isset($_POST['man_detail_client'])) {
      $man_detail_client=$_POST['man_detail_client'];
    }


    if (isset($_POST['man_billing_client'])) {
      $man_billing_client=$_POST['man_billing_client'];
    }

    if (isset($_POST['man_delete_client'])) {
      $man_delete_client=$_POST['man_delete_client'];
    }

   if (isset($_POST['man_view_products'])) {
       $man_view_products=$_POST['man_view_products'];
    }


     if (isset($_POST['man_add_edit_products'])) {
       $man_add_edit_products=$_POST['man_add_edit_products'];
    }
    if (isset($_POST['man_delete_products'])) {
       $man_delete_products=$_POST['man_delete_products'];
    }
    if (isset($_POST['man_view_staff'])) {
       $man_view_staff=$_POST['man_view_staff'];
    }
    if (isset($_POST['man_add_edit_staff'])) {
       $man_add_edit_staff=$_POST['man_add_edit_staff'];
    }
    if (isset($_POST['man_delete_staff'])) {
      $man_delete_staff=$_POST['man_delete_staff']; 
    }
    if (isset($_POST['man_view_entity'])) {
       $man_view_entity=$_POST['man_view_entity'];
    }
    if (isset($_POST['man_add_edit_entity'])) {
       $man_add_edit_entity=$_POST['man_add_edit_entity'];
    }
    if (isset($_POST['man_delete_entity'])) {
       $man_delete_entity=$_POST['man_delete_entity'];
    }
    if (isset($_POST['man_view_product'])) {
       $man_view_product=$_POST['man_view_product'];
    }
    if (isset($_POST['man_add_edit_product'])) {
       $man_add_edit_product=$_POST['man_add_edit_product'];
    }
    if (isset($_POST['man_delete_product'])) {
       $man_delete_product=$_POST['man_delete_product'];
    }

    if (isset($_POST['man_invoice_billing'])) {
       $man_invoice_billing=$_POST['man_invoice_billing'];
    } 

    if (isset($_POST['man_estimates_and_qutations'])) {
       $man_estimates_and_qutations=$_POST['man_estimates_and_qutations'];
    }      
    if (isset($_POST['man_bank_card_area'])) {
       $man_bank_card_area=$_POST['man_bank_card_area'];
    } 
    if (isset($_POST['man_client_active'])) {
       $man_client_active=$_POST['man_client_active'];
    } 



    $query="UPDATE `dashboard_access` SET `user_type`='2',`add_new_client`='$man_add_client',`view_clients_list`='$man_view_client',`client_detail_area`='$man_detail_client',`client_billing_area`='$man_billing_client',`delete_client`='$man_delete_client',`view_products`='$man_view_products',`add_edit_products`='$man_add_edit_products',`delete_products`='$man_delete_products',`view_staff`='$man_view_staff',`add_edit_staff`='$man_add_edit_staff',`delete_staff`='$man_delete_staff',`view_entity_form`='$man_view_entity',`add_edit_form`='$man_add_edit_entity',`delete_form`='$man_delete_entity',`view_product_cycle`='$man_view_product',`add_edit_cycle`='$man_add_edit_product',`delete_cycle`='$man_delete_product',invoice_and_billing='$man_invoice_billing',estimates_and_qutations='$man_estimates_and_qutations',bank_card_area='$man_bank_card_area', client_active='$man_client_active' WHERE `user_type`='2'";
    $insert_query = mysqli_query($con,$query);



    }



    if (isset($_POST['save_employee'])) {
    $msg='';
    $user_type="3";   

    $emp_add_client=$emp_view_client=$emp_detail_client=$emp_billing_client=$emp_delete_client=$emp_view_products=$emp_add_edit_products=$emp_delete_products=$emp_view_staff=$emp_add_edit_staff=$emp_delete_staff=$emp_view_entity=$emp_add_edit_entity=$emp_delete_entity=$emp_view_product=$emp_add_edit_product=$emp_delete_product=$emp_invoice_billing=$emp_estimates_and_qutations=$emp_bank_card_area=$emp_client_active="";

    if (isset($_POST['emp_add_client'])) {
      $emp_add_client=$_POST['emp_add_client'];
    }


    if (isset($_POST['emp_view_client'])) {
      $emp_view_client=$_POST['emp_view_client'];
    }


    if (isset($_POST['emp_detail_client'])) {
      $emp_detail_client=$_POST['emp_detail_client'];
    }


    if (isset($_POST['emp_billing_client'])) {
      $emp_billing_client=$_POST['emp_billing_client'];
    }

    if (isset($_POST['emp_delete_client'])) {
      $emp_delete_client=$_POST['emp_delete_client'];
    }

   if (isset($_POST['emp_view_products'])) {
       $emp_view_products=$_POST['emp_view_products'];
    }


     if (isset($_POST['emp_add_edit_products'])) {
       $emp_add_edit_products=$_POST['emp_add_edit_products'];
    }
    if (isset($_POST['emp_delete_products'])) {
       $emp_delete_products=$_POST['emp_delete_products'];
    }
    if (isset($_POST['emp_view_staff'])) {
       $emp_view_staff=$_POST['emp_view_staff'];
    }
    if (isset($_POST['emp_add_edit_staff'])) {
       $emp_add_edit_staff=$_POST['emp_add_edit_staff'];
    }
    if (isset($_POST['emp_delete_staff'])) {
      $emp_delete_staff=$_POST['emp_delete_staff']; 
    }
    if (isset($_POST['emp_view_entity'])) {
       $emp_view_entity=$_POST['emp_view_entity'];
    }
    if (isset($_POST['emp_add_edit_entity'])) {
       $emp_add_edit_entity=$_POST['emp_add_edit_entity'];
    }
    if (isset($_POST['emp_delete_entity'])) {
       $emp_delete_entity=$_POST['emp_delete_entity'];
    }
    if (isset($_POST['emp_view_product'])) {
       $emp_view_product=$_POST['emp_view_product'];
    }
    if (isset($_POST['emp_add_edit_product'])) {
       $emp_add_edit_product=$_POST['emp_add_edit_product'];
    }
    if (isset($_POST['emp_delete_product'])) {
       $emp_delete_product=$_POST['emp_delete_product'];
    }  

     if (isset($_POST['emp_invoice_billing'])) {
       $emp_invoice_billing=$_POST['emp_invoice_billing'];
    } 

    if (isset($_POST['emp_estimates_and_qutations'])) {
       $emp_estimates_and_qutations=$_POST['emp_estimates_and_qutations'];
    } 

    if (isset($_POST['emp_bank_card_area'])) {
       $emp_bank_card_area=$_POST['emp_bank_card_area'];
    }   

    if (isset($_POST['emp_client_active'])) {
       $emp_client_active=$_POST['emp_client_active'];
    }   

    $query="UPDATE `dashboard_access` SET `user_type`='3',`add_new_client`='$emp_add_client',`view_clients_list`='$emp_view_client',`client_detail_area`='$emp_detail_client',`client_billing_area`='$emp_billing_client',`delete_client`='$emp_delete_client',`view_products`='$emp_view_products',`add_edit_products`='$emp_add_edit_products',`delete_products`='$emp_delete_products',`view_staff`='$emp_view_staff',`add_edit_staff`='$emp_add_edit_staff',`delete_staff`='$emp_delete_staff',`view_entity_form`='$emp_view_entity',`add_edit_form`='$emp_add_edit_entity',`delete_form`='$emp_delete_entity',`view_product_cycle`='$emp_view_product',`add_edit_cycle`='$emp_add_edit_product',`delete_cycle`='$emp_delete_product',invoice_and_billing='$emp_invoice_billing',estimates_and_qutations='$emp_estimates_and_qutations',bank_card_area='$emp_bank_card_area', client_active='$emp_client_active' WHERE `user_type`='3'";
    $insert_query = mysqli_query($con,$query);



    }



    if (isset($_POST['save_internee'])) {
    $msg='';
    $user_type="4";   

    $int_add_client=$int_view_client=$int_detail_client=$int_billing_client=$int_delete_client=$int_view_products=$int_add_edit_products=$int_delete_products=$int_view_staff=$int_add_edit_staff=$int_delete_staff=$int_view_entity=$int_add_edit_entity=$int_delete_entity=$int_view_product=$int_add_edit_product=$int_delete_product=$int_invoice_billing=$int_estimates_and_qutations=$int_bank_card_area=$int_client_active="";

    if (isset($_POST['int_add_client'])) {
      $int_add_client=$_POST['int_add_client'];
    }


    if (isset($_POST['int_view_client'])) {
      $int_view_client=$_POST['int_view_client'];
    }


    if (isset($_POST['int_detail_client'])) {
      $int_detail_client=$_POST['int_detail_client'];
    }


    if (isset($_POST['int_billing_client'])) {
      $int_billing_client=$_POST['int_billing_client'];
    }

    if (isset($_POST['int_delete_client'])) {
      $int_delete_client=$_POST['int_delete_client'];
    }

   if (isset($_POST['int_view_products'])) {
       $int_view_products=$_POST['int_view_products'];
    }


     if (isset($_POST['int_add_edit_products'])) {
       $int_add_edit_products=$_POST['int_add_edit_products'];
    }
    if (isset($_POST['int_delete_products'])) {
       $int_delete_products=$_POST['int_delete_products'];
    }
    if (isset($_POST['int_view_staff'])) {
       $int_view_staff=$_POST['int_view_staff'];
    }
    if (isset($_POST['int_add_edit_staff'])) {
       $int_add_edit_staff=$_POST['int_add_edit_staff'];
    }
    if (isset($_POST['int_delete_staff'])) {
      $int_delete_staff=$_POST['int_delete_staff']; 
    }
    if (isset($_POST['int_view_entity'])) {
       $int_view_entity=$_POST['int_view_entity'];
    }
    if (isset($_POST['int_add_edit_entity'])) {
       $int_add_edit_entity=$_POST['int_add_edit_entity'];
    }
    if (isset($_POST['int_delete_entity'])) {
       $int_delete_entity=$_POST['int_delete_entity'];
    }
    if (isset($_POST['int_view_product'])) {
       $int_view_product=$_POST['int_view_product'];
    }
    if (isset($_POST['int_add_edit_product'])) {
       $int_add_edit_product=$_POST['int_add_edit_product'];
    }
    if (isset($_POST['int_delete_product'])) {
       $int_delete_product=$_POST['int_delete_product'];
    }   

    if (isset($_POST['int_invoice_billing'])) {
       $int_invoice_billing=$_POST['int_invoice_billing'];
    } 

    if (isset($_POST['int_estimates_and_qutations'])) {
       $int_estimates_and_qutations=$_POST['int_estimates_and_qutations'];
    } 

    if (isset($_POST['int_bank_card_area'])) {
       $int_bank_card_area=$_POST['int_bank_card_area'];
    }  

    if (isset($_POST['int_client_active'])) {
       $int_client_active=$_POST['int_client_active'];
    }  


    $query="UPDATE `dashboard_access` SET `user_type`='4',`add_new_client`='$int_add_client',`view_clients_list`='$int_view_client',`client_detail_area`='$int_detail_client',`client_billing_area`='$int_billing_client',`delete_client`='$int_delete_client',`view_products`='$int_view_products',`add_edit_products`='$int_add_edit_products',`delete_products`='$int_delete_products',`view_staff`='$int_view_staff',`add_edit_staff`='$int_add_edit_staff',`delete_staff`='$int_delete_staff',`view_entity_form`='$int_view_entity',`add_edit_form`='$int_add_edit_entity',`delete_form`='$int_delete_entity',`view_product_cycle`='$int_view_product',`add_edit_cycle`='$int_add_edit_product',`delete_cycle`='$int_delete_product',invoice_and_billing='$int_invoice_billing',estimates_and_qutations='$int_estimates_and_qutations',bank_card_area='$int_bank_card_area', client_active='$int_client_active' WHERE `user_type`='4'";
      $insert_query = mysqli_query($con,$query);
    }


$add_query = mysqli_query($con,"SELECT * FROM dashboard_access where `user_type`='1'");
    while($add_fetch = mysqli_fetch_array($add_query)){
            $add_add_client=$add_fetch['add_new_client'];
            $add_view_client=$add_fetch['view_clients_list'];
            $add_detail_client=$add_fetch['client_detail_area'];
            $add_billing_client=$add_fetch['client_billing_area'];
            $add_delete_client=$add_fetch['delete_client'];
            $add_view_products=$add_fetch['view_products'];
            $add_add_edit_products=$add_fetch['add_edit_products'];
            $add_delete_products=$add_fetch['delete_products'];
            $add_view_staff=$add_fetch['view_staff'];
            $add_add_edit_staff=$add_fetch['add_edit_staff'];
            $add_delete_staff=$add_fetch['delete_staff'];
            $add_view_entity=$add_fetch['view_entity_form'];
            $add_add_edit_entity=$add_fetch['add_edit_form'];
            $add_delete_entity=$add_fetch['delete_form'];
            $add_view_product=$add_fetch['view_product_cycle'];
            $add_add_edit_product=$add_fetch['add_edit_cycle'];
            $add_delete_product=$add_fetch['delete_cycle'];


            $add_invoice_billing=$add_fetch['invoice_and_billing'];
            $add_estimates_and_qutations=$add_fetch['estimates_and_qutations'];
            $add_bank_card_area=$add_fetch['bank_card_area'];
            $add_client_active=$add_fetch['client_active'];

    }

$man_query = mysqli_query($con,"SELECT * FROM dashboard_access where `user_type`='2'");
    while($man_fetch = mysqli_fetch_array($man_query)){
            $man_add_client=$man_fetch['add_new_client'];
            $man_view_client=$man_fetch['view_clients_list'];
            $man_detail_client=$man_fetch['client_detail_area'];
            $man_billing_client=$man_fetch['client_billing_area'];
            $man_delete_client=$man_fetch['delete_client'];
            $man_view_products=$man_fetch['view_products'];
            $man_add_edit_products=$man_fetch['add_edit_products'];
            $man_delete_products=$man_fetch['delete_products'];
            $man_view_staff=$man_fetch['view_staff'];
            $man_add_edit_staff=$man_fetch['add_edit_staff'];
            $man_delete_staff=$man_fetch['delete_staff'];
            $man_view_entity=$man_fetch['view_entity_form'];
            $man_add_edit_entity=$man_fetch['add_edit_form'];
            $man_delete_entity=$man_fetch['delete_form'];
            $man_view_product=$man_fetch['view_product_cycle'];
            $man_add_edit_product=$man_fetch['add_edit_cycle'];
            $man_delete_product=$man_fetch['delete_cycle'];

            $man_invoice_billing=$man_fetch['invoice_and_billing'];
            $man_estimates_and_qutations=$man_fetch['estimates_and_qutations'];
            $man_bank_card_area=$man_fetch['bank_card_area'];
            $man_client_active=$man_fetch['client_active'];

    }


$emp_query = mysqli_query($con,"SELECT * FROM dashboard_access where `user_type`='3'");
    while($emp_fetch = mysqli_fetch_array($emp_query)){
            $emp_add_client=$emp_fetch['add_new_client'];
            $emp_view_client=$emp_fetch['view_clients_list'];
            $emp_detail_client=$emp_fetch['client_detail_area'];
            $emp_billing_client=$emp_fetch['client_billing_area'];
            $emp_delete_client=$emp_fetch['delete_client'];
            $emp_view_products=$emp_fetch['view_products'];
            $emp_add_edit_products=$emp_fetch['add_edit_products'];
            $emp_delete_products=$emp_fetch['delete_products'];
            $emp_view_staff=$emp_fetch['view_staff'];
            $emp_add_edit_staff=$emp_fetch['add_edit_staff'];
            $emp_delete_staff=$emp_fetch['delete_staff'];
            $emp_view_entity=$emp_fetch['view_entity_form'];
            $emp_add_edit_entity=$emp_fetch['add_edit_form'];
            $emp_delete_entity=$emp_fetch['delete_form'];
            $emp_view_product=$emp_fetch['view_product_cycle'];
            $emp_add_edit_product=$emp_fetch['add_edit_cycle'];
            $emp_delete_product=$emp_fetch['delete_cycle'];

            $emp_invoice_billing=$emp_fetch['invoice_and_billing'];
            $emp_estimates_and_qutations=$emp_fetch['estimates_and_qutations'];
            $emp_bank_card_area=$emp_fetch['bank_card_area'];
            $emp_client_active=$emp_fetch['client_active'];

    }



    $int_query = mysqli_query($con,"SELECT * FROM dashboard_access where `user_type`='4'");
    while($int_fetch = mysqli_fetch_array($int_query)){
            $int_add_client=$int_fetch['add_new_client'];
            $int_view_client=$int_fetch['view_clients_list'];
            $int_detail_client=$int_fetch['client_detail_area'];
            $int_billing_client=$int_fetch['client_billing_area'];
            $int_delete_client=$int_fetch['delete_client'];
            $int_view_products=$int_fetch['view_products'];
            $int_add_edit_products=$int_fetch['add_edit_products'];
            $int_delete_products=$int_fetch['delete_products'];
            $int_view_staff=$int_fetch['view_staff'];
            $int_add_edit_staff=$int_fetch['add_edit_staff'];
            $int_delete_staff=$int_fetch['delete_staff'];
            $int_view_entity=$int_fetch['view_entity_form'];
            $int_add_edit_entity=$int_fetch['add_edit_form'];
            $int_delete_entity=$int_fetch['delete_form'];
            $int_view_product=$int_fetch['view_product_cycle'];
            $int_add_edit_product=$int_fetch['add_edit_cycle'];
            $int_delete_product=$int_fetch['delete_cycle'];

            $int_invoice_billing=$int_fetch['invoice_and_billing'];
            $int_estimates_and_qutations=$int_fetch['estimates_and_qutations'];
            $int_bank_card_area=$int_fetch['bank_card_area'];
            $int_client_active=$int_fetch['client_active'];
      
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
                           <h4 class="m-b-10">    Dashboard</h4>
                        </div>
                        <ul class="breadcrumb">
                           <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                           <li class="breadcrumb-item">Manage Access</li>
                        </ul>
                     </div>
                     <div class="col">
                     </div>
                  </div>
               </div>
            </div>
            <div class="row">
               <div class="col-sm-12">
                  <!-- [ Main Content ] start -->
                    <div class="card ">
                            <div class="card-body employee-detail-body fulls-card">
                                <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:15px;">
                                    <h5 class="mb-0">Manage Access</h5>
                                    <div class="module-filter-wrap" style="min-width:280px;">
                                        <label for="moduleFilter" class="form-label fw-bold mb-1" style="font-size:0.85rem;color:#555;">Filter by Module</label>
                                        <select id="moduleFilter" class="form-select form-control" style="border:2px solid #6FD943;border-radius:8px;font-weight:600;cursor:pointer;">
                                            <option value="all">-- All Modules --</option>
                                            <option value="client">Client Management</option>
                                            <option value="product">Product Management</option>
                                            <option value="staff">Staff Management</option>
                                            <option value="form">Form Management</option>
                                            <option value="finance">Financial Operations</option>
                                        </select>
                                    </div>
                                </div>
                                <hr>
                              <div class="dataTable-container">
                                 <table class="table datatable dataTable-table" id="accessTable">
                                    <thead>
                                       <tr>
                                          <th data-sortable=""></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Administrator</a></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Manager</a></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Employee</a></th>
                                          <th data-sortable=""><a href="#" class="dataTable-sorter">Internee</a></th>

                                       </tr>
                                    </thead>
                                    <tbody>
                                    <form method="POST">
                                       <tr data-module="client">
                                          <td class="Id"><b>Add New Client</b></td>
                                          <td><input type="checkbox" name="add_add_client" class="form-check-input" value="Yes" <?php if($add_add_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_add_client" class="form-check-input" value="Yes" <?php if($man_add_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_add_client" class="form-check-input" value="Yes" <?php if($emp_add_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_add_client" class="form-check-input" value="Yes" <?php if($int_add_client=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="client">
                                          <td class="Id"><b>View Clients List</b></td>
                                          <td><input type="checkbox" name="add_view_client" class="form-check-input" value="Yes" <?php if($add_view_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_view_client" class="form-check-input" value="Yes" <?php if($man_view_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_view_client" class="form-check-input" value="Yes" <?php if($emp_view_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_view_client" class="form-check-input" value="Yes" <?php if($int_view_client=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="client">
                                          <td class="Id"><b>Client Detail Area</b></td>
                                          <td><input type="checkbox" name="add_detail_client" class="form-check-input" value="Yes" <?php if($add_detail_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_detail_client" class="form-check-input" value="Yes" <?php if($man_detail_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_detail_client" class="form-check-input" value="Yes" <?php if($emp_detail_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_detail_client" class="form-check-input" value="Yes" <?php if($int_detail_client=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="client">
                                          <td class="Id"><b>Client Billing Area</b></td>
                                          <td><input type="checkbox" name="add_billing_client" class="form-check-input" value="Yes" <?php if($add_billing_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_billing_client" class="form-check-input" value="Yes" <?php if($man_billing_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_billing_client" class="form-check-input" value="Yes" <?php if($emp_billing_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_billing_client" class="form-check-input" value="Yes" <?php if($int_billing_client=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="client">
                                          <td class="Id"><b>Delete Client</b></td>
                                          <td><input type="checkbox" name="add_delete_client" class="form-check-input" value="Yes" <?php if($add_delete_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_delete_client" class="form-check-input" value="Yes" <?php if($man_delete_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_delete_client" class="form-check-input" value="Yes" <?php if($emp_delete_client=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_delete_client" class="form-check-input" value="Yes" <?php if($int_delete_client=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="client">
                                          <td class="Id"><b>Client Active/Inactive</b></td>
                                          <td><input type="checkbox" name="add_client_active" class="form-check-input" value="Yes"  <?php if($add_client_active=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_client_active" class="form-check-input" value="Yes"  <?php if($man_client_active=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_client_active" class="form-check-input" value="Yes"  <?php if($emp_client_active=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_client_active" class="form-check-input" value="Yes"  <?php if($int_client_active=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="product">
                                          <td class="Id"><b>View Products</b></td>
                                          <td><input type="checkbox" name="add_view_products" class="form-check-input" value="Yes" <?php if($add_view_products=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_view_products" class="form-check-input" value="Yes" <?php if($man_view_products=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_view_products" class="form-check-input" value="Yes" <?php if($emp_view_products=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_view_products" class="form-check-input" value="Yes"<?php if($int_view_products=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="product">
                                          <td class="Id"><b>Add/Edit Products</b></td>
                                          <td><input type="checkbox" name="add_add_edit_products" class="form-check-input" value="Yes" <?php if($add_add_edit_products=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_add_edit_products" class="form-check-input" value="Yes" <?php if($man_add_edit_products=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_add_edit_products" class="form-check-input" value="Yes" <?php if($emp_add_edit_products=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_add_edit_products" class="form-check-input" value="Yes" <?php if($int_add_edit_products=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="product">
                                          <td class="Id"><b>Delete Products</b></td>
                                          <td><input type="checkbox" name="add_delete_products" class="form-check-input" value="Yes" <?php if($add_delete_products=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_delete_products" class="form-check-input" value="Yes" <?php if($man_delete_products=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_delete_products" class="form-check-input" value="Yes" <?php if($emp_delete_products=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_delete_products" class="form-check-input" value="Yes" <?php if($int_delete_products=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="product">
                                          <td class="Id"><b>View Product Cycle</b></td>
                                          <td><input type="checkbox" name="add_view_product" class="form-check-input" value="Yes" <?php if($add_view_product=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_view_product" class="form-check-input" value="Yes" <?php if($man_view_product=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_view_product" class="form-check-input" value="Yes" <?php if($emp_view_product=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_view_product" class="form-check-input" value="Yes" <?php if($int_view_product=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="product">
                                          <td class="Id"><b>Add/Edit Product Cycle</b></td>
                                          <td><input type="checkbox" name="add_add_edit_product" class="form-check-input" value="Yes"  <?php if($add_add_edit_product=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_add_edit_product" class="form-check-input" value="Yes"  <?php if($man_add_edit_product=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_add_edit_product" class="form-check-input" value="Yes"  <?php if($emp_add_edit_product=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_add_edit_product" class="form-check-input" value="Yes"  <?php if($int_add_edit_product=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="product">
                                          <td class="Id"><b>Delete Product Cycle</b></td>
                                          <td><input type="checkbox" name="add_delete_product" class="form-check-input" value="Yes" <?php if($add_delete_product=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_delete_product" class="form-check-input" value="Yes" <?php if($man_delete_product=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_delete_product" class="form-check-input" value="Yes" <?php if($emp_delete_product=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_delete_product" class="form-check-input" value="Yes" <?php if($int_delete_product=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="staff">
                                          <td class="Id"><b>View Staff</b></td>
                                          <td><input type="checkbox" name="add_view_staff" class="form-check-input" value="Yes" <?php if($add_view_staff=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_view_staff" class="form-check-input" value="Yes" <?php if($man_view_staff=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_view_staff" class="form-check-input" value="Yes" <?php if($emp_view_staff=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_view_staff" class="form-check-input" value="Yes" <?php if($int_view_staff=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="staff">
                                          <td class="Id"><b>Add/Edit Staff</b></td>
                                          <td><input type="checkbox" name="add_add_edit_staff" class="form-check-input" value="Yes" <?php if($add_add_edit_staff=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_add_edit_staff" class="form-check-input" value="Yes" <?php if($man_add_edit_staff=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_add_edit_staff" class="form-check-input" value="Yes" <?php if($emp_add_edit_staff=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_add_edit_staff" class="form-check-input" value="Yes" <?php if($int_add_edit_staff=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="staff">
                                          <td class="Id"><b>Delete Staff</b></td>
                                          <td><input type="checkbox" name="add_delete_staff" class="form-check-input" value="Yes" <?php if($add_delete_staff=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_delete_staff" class="form-check-input" value="Yes" <?php if($man_delete_staff=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_delete_staff" class="form-check-input" value="Yes" <?php if($emp_delete_staff=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_delete_staff" class="form-check-input" value="Yes" <?php if($int_delete_staff=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="form">
                                          <td class="Id"><b>View Entity Form</b></td>
                                          <td><input type="checkbox" name="add_view_entity" class="form-check-input" value="Yes" <?php if($add_view_entity=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_view_entity" class="form-check-input" value="Yes" <?php if($man_view_entity=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_view_entity" class="form-check-input" value="Yes" <?php if($emp_view_entity=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_view_entity" class="form-check-input" value="Yes" <?php if($int_view_entity=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="form">
                                          <td class="Id"><b>Add/Edit Entity Form</b></td>
                                          <td><input type="checkbox" name="add_add_edit_entity" class="form-check-input" value="Yes" <?php if($add_add_edit_entity=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_add_edit_entity" class="form-check-input" value="Yes" <?php if($man_add_edit_entity=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_add_edit_entity" class="form-check-input" value="Yes" <?php if($emp_add_edit_entity=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_add_edit_entity" class="form-check-input" value="Yes" <?php if($int_add_edit_entity=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="form">
                                          <td class="Id"><b>Delete Entity Form</b></td>
                                          <td><input type="checkbox" name="add_delete_entity" class="form-check-input" value="Yes" <?php if($add_delete_entity=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_delete_entity" class="form-check-input" value="Yes" <?php if($man_delete_entity=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_delete_entity" class="form-check-input" value="Yes" <?php if($emp_delete_entity=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_delete_entity" class="form-check-input" value="Yes" <?php if($int_delete_entity=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="finance">
                                          <td class="Id"><b>Invoice & Billing</b></td>
                                          <td><input type="checkbox" name="add_invoice_billing" class="form-check-input" value="Yes"  <?php if($add_invoice_billing=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_invoice_billing" class="form-check-input" value="Yes"  <?php if($man_invoice_billing=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_invoice_billing" class="form-check-input" value="Yes"  <?php if($emp_invoice_billing=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_invoice_billing" class="form-check-input" value="Yes"  <?php if($int_invoice_billing=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="finance">
                                          <td class="Id"><b>Estimates & Qutations</b></td>
                                          <td><input type="checkbox" name="add_estimates_and_qutations" class="form-check-input" value="Yes"  <?php if($add_estimates_and_qutations=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_estimates_and_qutations" class="form-check-input" value="Yes"  <?php if($man_estimates_and_qutations=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_estimates_and_qutations" class="form-check-input" value="Yes"  <?php if($emp_estimates_and_qutations=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_estimates_and_qutations" class="form-check-input" value="Yes"  <?php if($int_estimates_and_qutations=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                        <tr data-module="finance">
                                          <td class="Id"><b>Bank/Card Area</b></td>
                                          <td><input type="checkbox" name="add_bank_card_area" class="form-check-input" value="Yes"  <?php if($add_bank_card_area=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="man_bank_card_area" class="form-check-input" value="Yes"  <?php if($man_bank_card_area=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="emp_bank_card_area" class="form-check-input" value="Yes"  <?php if($emp_bank_card_area=="Yes"){echo "checked";} ?>></td>
                                          <td><input type="checkbox" name="int_bank_card_area" class="form-check-input" value="Yes"  <?php if($int_bank_card_area=="Yes"){echo "checked";} ?>></td>
                                       </tr>
                                       <tr data-module="action-row">
                                          <td class="Id"></td>
                                          <td><input type="submit" name="save_admin" class="btn btn-success" value="Save"></td>
                                          <td><input type="submit" name="save_manager" class="btn btn-success" value="Save"></td>
                                          <td><input type="submit" name="save_employee" class="btn btn-success" value="Save"></td>
                                          <td><input type="submit" name="save_internee" class="btn btn-success" value="Save"></td>
                                       </tr>
                                       </form>
                                    </tbody>
                                 </table>
                              </div>
                            </div>
                        </div>

                        <script>
                            (function(){
                                const moduleFilter = document.getElementById('moduleFilter');
                                const table = document.getElementById('accessTable');
                                if (!moduleFilter || !table) return;

                                function applyFilter(value) {
                                    const rows = table.querySelectorAll('tbody tr[data-module]');
                                    rows.forEach(row => {
                                        const mod = row.getAttribute('data-module');
                                        if (mod === 'action-row') {
                                            row.style.display = '';
                                            return;
                                        }
                                        if (value === 'all' || mod === value) {
                                            row.style.display = '';
                                        } else {
                                            row.style.display = 'none';
                                        }
                                    });
                                }

                                moduleFilter.addEventListener('change', function(){
                                    applyFilter(this.value);
                                });

                                applyFilter(moduleFilter.value);
                            })();
                        </script>



               </div>
            </div>
            <!-- [ Main Content ] end -->
         </div>
      </div>
      <?php include "includes/footer.php"; ?>
      <?php include "includes/scripts.php"; ?>
    </body>
</html>