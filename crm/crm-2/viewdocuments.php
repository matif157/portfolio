<?php
   include "includes/auth.php";
   include "includes/db.php";
   $document_id=$_GET['document'];

   $getdoc = mysqli_query($con,"SELECT * FROM documents where document_id='$document_id'");
   while($fetchdoc = mysqli_fetch_array($getdoc)){
      $green_card=$fetchdoc['green_card'];
      $citizenship_certificate=$fetchdoc['citizenship_certificate'];
      $nadara_id_card=$fetchdoc['nadara_id_card'];
      $ssn_certificate=$fetchdoc['ssn_certificate'];
   }

 ?>
<!DOCTYPE html>
<html lang="en" dir="">
   <meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">
   <head>
      <title>ERPGo SaaS - Dashboard</title>
      <?php include "includes/links.php"; ?>

  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
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
                           <h4 class="m-b-10">Dashboard</h4>
                        </div>
                        <ul class="breadcrumb">
                           <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                           <li class="breadcrumb-item">Account</li>
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
                  
                     <div class="container">
                       <h2>Client Documents</h2>
                       <ul class="nav nav-tabs">
                        <?php if ($green_card!='') {?>
                         <li class="active"><a data-toggle="tab" href="#home">Green Card</a></li>
                        <?php } ?>
                        <?php if ($citizenship_certificate!='') {?>
                         <li><a data-toggle="tab" href="#menu1">Citizenship Certificate</a></li>
                         <?php } ?>
                        <?php if ($nadara_id_card!='') {?>
                         <li><a data-toggle="tab" href="#menu2">Nadra ID Card</a></li>
                         <?php } ?>
                        <?php if ($ssn_certificate!='') {?>
                         <li><a data-toggle="tab" href="#menu3">SSN Certificate</a></li>
                       <?php } ?>
                       </ul>

                       <div class="tab-content">
                         <div id="home" class="tab-pane fade in active">
                           <img src="images/documents/<?php echo $green_card; ?>">
                         </div>
                         <div id="menu1" class="tab-pane fade">
                           <img src="images/documents/<?php echo $citizenship_certificate; ?>">
                         </div>
                         <div id="menu2" class="tab-pane fade">
                          <img src="images/documents/<?php echo $nadara_id_card; ?>">
                         </div>
                         <div id="menu3" class="tab-pane fade">
                           <img src="images/documents/<?php echo $ssn_certificate; ?>">
                         </div>
                       </div>




               </div>
            </div>
            <!-- [ Main Content ] end -->
         </div>
      </div>
      <?php include "includes/footer.php"; ?>
      <?php include "includes/scripts.php"; ?>
    </body>
</html>