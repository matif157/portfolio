<?php
      include "includes/db.php";
      include "includes/auth.php";

      $document_id=$_GET['document'];

   if (isset($_POST['yesvalid'])) {
    $msg='';    

    if (isset($_POST['greencard'])) {
       $greencard=$_POST['greencard'];
       $associate_query="UPDATE `documents` SET `green_card`='$greencard' WHERE `document_id`='$document_id'";
      $insert_associate = mysqli_query($con,$associate_query);
    }



    if (isset($_POST['nadracard'])) {
       $nadracard=$_POST['nadracard'];
       $associate_query="UPDATE `documents` SET `nadara_id_card`='$nadracard' WHERE `document_id`='$document_id'";
      $insert_associate = mysqli_query($con,$associate_query);
    }




    if (isset($_POST['citizenship'])) {
       $citizenship=$_POST['citizenship'];
       $associate_query="UPDATE `documents` SET `citizenship_certificate`='$citizenship' WHERE `document_id`='$document_id'";
      $insert_associate = mysqli_query($con,$associate_query);
    }



    if (isset($_POST['ssncertificate'])) {
       $ssncertificate=$_POST['ssncertificate'];
       $associate_query="UPDATE `documents` SET `ssn_certificate`='$ssncertificate' WHERE `document_id`='$document_id'";
      $insert_associate = mysqli_query($con,$associate_query);
    }


    $getcycle = mysqli_query($con,"SELECT * FROM documents where document_id='$document_id'");
    while($getdetail = mysqli_fetch_array($getcycle)){
            $clientid=$getdetail['client_id'];        
    }

    $status = 0;
    $statusquery = mysqli_query($con, "SELECT status FROM members WHERE member_id='$clientid'");
    if ($statusquery) {
        $statusdetail = mysqli_fetch_array($statusquery);
        if ($statusdetail !== false) {
            $status = $statusdetail['status'];
        }
    }
    unset($_SESSION['green_card']);
    unset($_SESSION['citizenShip_certificate']);
    unset($_SESSION['nadara_id_cards']);
    unset($_SESSION['ssn_certificate']);

    header('Location: client_details.php?id='.$clientid.'&status='.$status);  
   

}


if (isset($_POST['uploadagain'])) {



      $green_card='';$citizenShip_certificate='';$nadara_id_cards='';$ssn_certificate='';

        $pic_name1 = $_FILES['green_card']['name'];
        $pic_temp1 = $_FILES['green_card']['tmp_name'];
        $pic_size1 = $_FILES['green_card']['size'];

        $pic_new1 = rand(100,1000) . '-' . $pic_name1;
        $pic_store1 = "images/documents/".$pic_new1;

        if(move_uploaded_file($pic_temp1, $pic_store1)){
            $green_card = $pic_new1;
        }




        $pic_name2 = $_FILES['citizenShip_certificate']['name'];
        $pic_temp2 = $_FILES['citizenShip_certificate']['tmp_name'];
        $pic_size2 = $_FILES['citizenShip_certificate']['size'];

        $pic_new2 = rand(100,1000) . '-' . $pic_name2;
        $pic_store2 = "images/documents/".$pic_new2;

        if(move_uploaded_file($pic_temp2, $pic_store2)){
            $citizenShip_certificate = $pic_new2;
        }

     
        $pic_name3 = $_FILES['nadara_id_cards']['name'];
        $pic_temp3 = $_FILES['nadara_id_cards']['tmp_name'];
        $pic_size3 = $_FILES['nadara_id_cards']['size'];

        $pic_new3 = rand(100,1000) . '-' . $pic_name3;
        $pic_store3 = "images/documents/".$pic_new3;

        if(move_uploaded_file($pic_temp3, $pic_store3)){
            $nadara_id_cards = $pic_new3;
        }


        $pic_name4 = $_FILES['ssn_certificate']['name'];
        $pic_temp4 = $_FILES['ssn_certificate']['tmp_name'];
        $pic_size4 = $_FILES['ssn_certificate']['size'];

        $pic_new4 = rand(100,1000) . '-' . $pic_name4;
        $pic_store4 = "images/documents/".$pic_new4;

        if(move_uploaded_file($pic_temp4, $pic_store4)){
            $ssn_certificate = $pic_new4;
        }

   if ($greencard!='') {
       $associate_query="UPDATE `documents` SET `green_card`='$green_card' WHERE `document_id`='$document_id'";
      $insert_associate = mysqli_query($con,$associate_query);
    }



    if ($nadara_id_cards!='') {
       $associate_query="UPDATE `documents` SET `nadara_id_card`='$nadara_id_cards' WHERE `document_id`='$document_id'";
      $insert_associate = mysqli_query($con,$associate_query);
    }




    if ($citizenShip_certificate!='') {
       $associate_query="UPDATE `documents` SET `citizenship_certificate`='$citizenShip_certificate' WHERE `document_id`='$document_id'";
      $insert_associate = mysqli_query($con,$associate_query);
    }



    if ($ssn_certificate!='') {
       $associate_query="UPDATE `documents` SET `ssn_certificate`='$ssn_certificate' WHERE `document_id`='$document_id'";
      $insert_associate = mysqli_query($con,$associate_query);
    }


$getcycle = mysqli_query($con,"SELECT * FROM documents where document_id='$document_id'");
    while($getdetail = mysqli_fetch_array($getcycle)){
            $clientid=$getdetail['client_id'];        
    }

    $status = 0;
    $statusquery = mysqli_query($con, "SELECT status FROM members WHERE member_id='$clientid'");
    if ($statusquery) {
        $statusdetail = mysqli_fetch_array($statusquery);
        if ($statusdetail !== false) {
            $status = $statusdetail['status'];
        }
    }
    
    unset($_SESSION['green_card']);
    unset($_SESSION['citizenShip_certificate']);
    unset($_SESSION['nadara_id_cards']);
    unset($_SESSION['ssn_certificate']);

    header('Location: client_details.php?id='.$clientid.'&status='.$status); 




}


 ?>
<!DOCTYPE html>
<html lang="en" dir="">
   <meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">
   <head>
      <title>ERPGo SaaS - Dashboard</title>
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
                           <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                           <li class="breadcrumb-item">Account</li>
                        </ul>
                     </div>
                     <div class="col">
                     </div>
                  </div>
               </div>
            </div>
            <div class="row">
              <form method="POST">
               <div class="col-sm-12">
                  <!-- [ Main Content ] start -->
                  <p style="    font-size: 17px;line-height: 28px;letter-spacing: 0px;word-spacing: 3px;">We are having error while scaning these documents Please confirm uploaded documents are valid for this client, If they are invalid you can reupload these documents ortherwise click on <b>"Yes Documents Are Valid":</b></p>
               </div>
               <?php if(isset($msg)){ echo $msg; } ?>
               <div class="col-md-12" style="margin-bottom:15px;">
                  <?php if (isset($_SESSION['green_card_cer'])) {$greenimage=$_SESSION['green_card_cer']; ?>
                     <input type="hidden" name="greencard" value="<?php echo $greenimage; ?>">
                     <img src="images/documents/<?php echo $greenimage; ?>">
                  <?php } ?>   
               </div>
               <div class="col-md-12"  style="margin-bottom:15px;">
                  <?php if (isset($_SESSION['citizenShip_certificate'])) {$citizenship=$_SESSION['citizenShip_certificate'];?>
                     <input type="hidden" name="citizenship" value="<?php echo $citizenship; ?>">
                      <img src="images/documents/<?php echo $citizenship; ?>">
                  <?php } ?>
               </div>
               <div class="col-md-12"  style="margin-bottom:15px;">
                  <?php if (isset($_SESSION['nadara_id_cards'])) {$nadara_id_cards=$_SESSION['nadara_id_cards']; ?>
                     <input type="hidden" name="nadracard" value="<?php echo $nadara_id_cards; ?>">
                     <img src="images/documents/<?php echo $nadara_id_cards; ?>">
                  <?php } ?>   
               </div>
               <div class="col-md-12"  style="margin-bottom:15px;">
                  <?php if (isset($_SESSION['ssn_certificate'])) { $ssnimage=$_SESSION['ssn_certificate'];?>
                     <input type="hidden" name="ssncertificate" value="<?php echo $ssnimage; ?>">
                      <img src="images/documents/<?php echo $ssnimage; ?>">
                  <?php } ?>
               </div>
               <div class="col-md-12">           
               
                  <input type="submit" class="btn btn-success" value="Yes Documents Are Valid" name="yesvalid" style="float:right;margin: 15px 10px;">
               </form>

               </div>
               <div class="row" style="padding:0px;">
                  <div class="col-md-9"></div>
                  <div class="col-md-3">
                      <button class="btn btn-primary" data-toggle="modal" data-target="#uploaddocument" style="float:right;    background: #008ecc;border-color: #008ecc;">Upload Again Document</button>
                  </div>
               </div>





                        <!-- The Business Add Modal -->
                      <div class="modal fade" id="uploaddocument">
                        <div class="modal-dialog modal-xl">
                        <form method="POST"   enctype="multipart/form-data">
                          <div class="modal-content">
                          
                            <!-- Modal Header -->
                            <div class="modal-header">
                              <h4 class="modal-title">Reupload Documents</h4>
                            </div>
                            
                            <!-- Modal body -->
                            <div class="modal-body">
                              <div class="row mb-2">
                              <?php if (isset($_SESSION['green_card_cer'])) {?>   
                                <div class="col-md-6 form-group" bis_skin_checked="1" style="margin:0px">
                                    <label for="green_card" class="form-label" style="margin:0px">Green Card</label>
                                    <div class="choose-file " bis_skin_checked="1">
                                        <label for="green_card" class="form-label" style="width: 100%;">
                                            <input type="file" class="form-control" name="green_card" id="green_card" data-filename="pro_image_create">
                                            <img id="image" class="mt-3" style="width:25%;">

                                        </label>
                                    </div>
                                </div>
                              <?php } ?>  
                               <?php if (isset($_SESSION['citizenShip_certificate'])) {?>
                                <div class="col-md-6 form-group" bis_skin_checked="1" style="margin:0px">
                                    <label for="citizenShip_certificate" class="form-label" style="margin:0px">CitizenShip certificate</label>
                                    <div class="choose-file " bis_skin_checked="1">
                                        <label for="citizenShip_certificate" class="form-label" style="width: 100%;">
                                            <input type="file" class="form-control" name="citizenShip_certificate" id="citizenShip_certificate" data-filename="pro_image_create">
                                            <img id="image" class="mt-3" style="width:25%;">

                                        </label>
                                    </div>
                                </div>
                              <?php } ?>
                              
                              <?php if (isset($_SESSION['nadara_id_cards'])) { ?>  
                                <div class="col-md-6 form-group" bis_skin_checked="1" style="margin:0px">
                                    <label for="nadara_id_cards" class="form-label" style="margin:0px">Nadara Id Card</label>
                                    <div class="choose-file " bis_skin_checked="1">
                                        <label for="nadara_id_cards" class="form-label" style="width: 100%;">
                                            <input type="file" class="form-control" name="nadara_id_cards" id="nadara_id_cards" data-filename="pro_image_create">
                                            <img id="image" class="mt-3" style="width:25%;">

                                        </label>
                                    </div>
                                </div>
                             <?php } ?>

                               <?php if (isset($_SESSION['ssn_certificate'])) {?>
                                <div class="col-md-6 form-group" bis_skin_checked="1" style="margin:0px">
                                    <label for="ssn_certificate" class="form-label" style="margin:0px">SSN</label>
                                    <div class="choose-file " bis_skin_checked="1">
                                        <label for="ssn_certificate" class="form-label" style="width: 100%;">
                                            <input type="file" class="form-control" name="ssn_certificate" id="ssn_certificate" data-filename="pro_image_create">
                                            <img id="image" class="mt-3" style="width:25%;">

                                        </label>
                                    </div>
                                </div>
                             <?php } ?>
                              </div> 
                            </div>
                             
                            <!-- Modal footer -->
                            <div class="modal-footer">
                              <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                              <button type="submit" class="btn btn-primary" name="uploadagain">Upload</button>
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
    </body>
</html>