<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;

include 'includes/db.php';
include "includes/auth.php";

$client_id = $_GET['id'];

$status = $_GET['status'];
$msg = '';

$userid = $_SESSION["user_id"];
$clientnamesecure = '';

function getInitials($name)
{

  $words = explode(' ', $name);
  $initials = '';
  foreach ($words as $word) {
    $initials .= strtoupper(substr($word, 0, 1));
  }

  return $initials;
}

$user_full_name = $_SESSION['fullname'];

$user_initials = getInitials($user_full_name);



$user_type = $_SESSION['user_type'];

if (isset($_POST['addassociatedproduct'])) {
  $msg = '';
  $discountRow = '';

  $member_id = $_POST['member_id'];
  $prod_id = $_POST['prod_id'];
  $cycle_id = $_POST['cycle_id'];
  $bil_cyc_months = $cycle_id;
  $startdate = $_POST['startdate'];
  $expirydate = $_POST['expirydate'];
  $sub_total = $_POST['sub_total'] ?? '0.00';
  $discount = $_POST['discount'] ?? '0.00';
  $grand_total = $_POST['grand_total'] ?? '0.00';

  $prod_query = mysqli_query($con, "SELECT membership_name FROM memberships WHERE membership_id = '$prod_id'");
  $prod_data = mysqli_fetch_assoc($prod_query);
  $servicename = $prod_data['membership_name'] ?? 'Unknown Product';

  $description = $_POST['description'] ?? '';

  $assigned_users = $_POST['assigned_users'] ?? $userid;

  $time = time();
  $currentMonth = date("Y-m");
  $PDFFileN = "INV-" . $member_id . "-" . $currentMonth . "-" . $time . ".pdf";
  $Filename = "invoices/" . $PDFFileN;

  $associate_query = "INSERT INTO orders(
                        mem_id, 
                        prod_id, 
                        bil_cyc_id, 
                        bil_cyc_months, 
                        start_date, 
                        exp_date, 
                        user_id, 
                        sub_total, 
                        discount, 
                        total, 
                        assign_to
                    ) VALUES (
                        '$member_id', 
                        '$prod_id', 
                        '$cycle_id', 
                        '$bil_cyc_months', 
                        '$startdate', 
                        '$expirydate', 
                        '$userid', 
                        '$sub_total', 
                        '$discount', 
                        '$grand_total', 
                        '$assigned_users'
                    )";

  $insert_associate = mysqli_query($con, $associate_query);
  $msg = "<div class='alert alert-success' role='alert'><b>Product Added Successfully</b></div>";

}

if (isset($_POST['assigntask'])) {
  $order_id = $_POST['order_id'];
  $assigned_users = $_POST['assigned_users'];
  $associate_query = "UPDATE `orders` SET `assign_to`='$assigned_users' WHERE order_id='$order_id'";
  $insert_query = mysqli_query($con, $associate_query);
  if ($insert_query) {
    $ostatus .= "<div class='alert alert-success'>Task Has Been Assigned Successfully.</div>";
  } else {
    $ostatus .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
  }
}

if (isset($_POST['addcomment'])) {
  $comment_details = $_POST['comment_details'];
  $current_time = date("Y-m-d H:i:s");


  $associate_query = "INSERT INTO comments( comment_detail, member_id, user_id,comment_time,comment_status,comment_updated_by) VALUES ('$comment_details','$client_id','$userid','$current_time','0','$userid')";

  $insert_associate = mysqli_query($con, $associate_query);

  if ($insert_associate) {
    $msg .= "<div class='alert alert-success' role='alert'>
                    <b>Comment Has Been Posted</b>
                  </div>";
  } else {
    $msg .= "<div class='alert alert-danger' role='alert'>
                    <b>Something wrong!</b>
                  </div>" . $con->error;
  }
}

if (isset($_POST['addnotes'])) {
  $notes_detail = $_POST['notes_detail'];
  $notes_time = date("Y-m-d H:i:s");

  $client_id = mysqli_real_escape_string($con, $client_id);
  $userid = mysqli_real_escape_string($con, $userid);

  $associate_query = "INSERT INTO notestab (notes_detail, member_id, user_id, notes_time) VALUES ('$notes_detail', '$client_id', '$userid', '$notes_time')";

  $insert_associate = mysqli_query($con, $associate_query);

  if ($insert_associate) {
    $msg .= "<div class='alert alert-success' role='alert'>
                    <b>Notes Has Been Posted</b>
                  </div>";
  } else {
    $msg .= "<div class='alert alert-danger' role='alert'>
                    <b>Something wrong!</b>
                  </div>" . $con->error;
  }
}

if (isset($_POST['addspouse'])) {
  $msg = '';
  $relation = $_POST['relationship'];
  if ($relation == 'other') {
    $relation = $_POST['otherrelation'];
  }

  $spousename = $_POST['spousename'];
  $spousedob = $_POST['spousedob'];
  $spousess = $_POST['spousess'];
  $spousestreetaddress = $_POST['spousestreetaddress'];
  $spousecity = $_POST['spousecity'];
  $spouseoccupation = $_POST['spouseoccupation'];
  $spousephone = $_POST['spousephone'];
  $spouseemailaddress = $_POST['spouseemailaddress'];

  $sp_mname = isset($_POST['sp_mname']) ? $_POST['sp_mname'] : 'A';
  $sp_lname = isset($_POST['sp_lname']) ? $_POST['sp_lname'] : '';
  $sp_state = isset($_POST['sp_state']) ? $_POST['sp_state'] : '';
  $sp_zip = isset($_POST['sp_zip']) ? $_POST['sp_zip'] : '';


  $associate_query = "INSERT INTO spouse (sp_relation, sp_name, sp_dob, sp_ss, sp_street, sp_city, sp_occ, sp_phone, sp_email, sp_mname, sp_lname, member_id, sp_state, sp_zip, user_id) 
                           VALUES ('$relation', '$spousename', '$spousedob', '$spousess', '$spousestreetaddress', '$spousecity', '$spouseoccupation', '$spousephone', '$spouseemailaddress', '$sp_mname', '$sp_lname', '$client_id', '$sp_state', '$sp_zip', '$userid')";

  $insert_associate = mysqli_query($con, $associate_query);

  if ($insert_associate) {
    $msg .= "<div class='alert alert-success' role='alert'>
                    <b>Spouse Has Been Added</b>
                  </div>";
  } else {
    $msg .= "<div class='alert alert-danger' role='alert'>
                    <b>Something went wrong!</b>
                  </div>";
  }
}


if (isset($_POST['updatespouse'])) {
  $spouse_id = $_POST['spouse_id'];
  $spousename = $_POST['spousename'];
  $spousedob = $_POST['spousedob'];
  $spousess = $_POST['spousess'];
  $spousestreetaddress = $_POST['spousestreetaddress'];
  $spousecity = $_POST['spousecity'];
  $spouseoccupation = $_POST['spouseoccupation'];
  $spousephone = $_POST['spousephone'];
  $spouseemailaddress = $_POST['spouseemailaddress'];

  $update_query = "UPDATE spouse SET 
    sp_name='$spousename',
    sp_dob='$spousedob',
    sp_ss='$spousess',
    sp_street='$spousestreetaddress',
    sp_city='$spousecity',
    sp_occ='$spouseoccupation',
    sp_phone='$spousephone',
    sp_email='$spouseemailaddress'
    WHERE spouse_id='$spouse_id'";

  $update_result = mysqli_query($con, $update_query);

  if ($update_result) {
    $msg .= "<div class='alert alert-success' role='alert'>
                    <b>Family member updated successfully.</b>
                  </div>";

  } else {
    $msg .= "<div class='alert alert-danger' role='alert'>
                    <b>Something went wrong!</b>
                  </div>";
  }
}



if (isset($_POST['deactivateproduct'])) {
  $OrderID = $_POST['orderid'];
  $update_query = "UPDATE `orders` SET order_status='0' where order_id='$OrderID'";
  $insert_associate = mysqli_query($con, $update_query);
}
if (isset($_POST['deleteproduct'])) {
  $OrderID = $_POST['orderid'];
  $update_query = "delete from `orders`  where order_id='$OrderID'";
  $insert_associate = mysqli_query($con, $update_query);
}
if (isset($_POST['activateproduct'])) {
  $OrderID = $_POST['orderid'];
  $update_query = "UPDATE `orders` SET order_status='1' where order_id='$OrderID'";
  $insert_associate = mysqli_query($con, $update_query);
}


if (isset($_POST['adddocuments'])) {

  //Uploading Featured Image
  /*   $pic_name = $_FILES['green_card']['name'];
        $pic_temp = $_FILES['green_card']['tmp_name'];
        $pic_size = $_FILES['green_card']['size'];

        $pic_new = rand(100,1000) . '-' . $pic_name;
        $pic_store = "images/documents/".$pic_new;

        if(move_uploaded_file($pic_temp, $pic_store)){
            $pic = $pic_new;
        }*/

  unset($_SESSION['green_card_cer']);
  unset($_SESSION['citizenShip_certificate']);
  unset($_SESSION['nadara_id_cards']);
  unset($_SESSION['ssn_certificate']);

  $clientname = $_POST['clientname'];
  $clientphone = $_POST['clientphone'];
  $clientemail = $_POST['clientemail'];
  $clientssn = $_POST['clientssn'];


  $pic_name1 = $_FILES['green_card']['name'];
  $pic_temp1 = $_FILES['green_card']['tmp_name'];
  $pic_size1 = $_FILES['green_card']['size'];

  $pic_new1 = rand(100, 1000) . '-' . $pic_name1;
  $pic_store1 = "images/documents/" . $pic_new1;

  if (move_uploaded_file($pic_temp1, $pic_store1)) {
    $green_card = $pic_new1;
  }

  $pic_name2 = $_FILES['citizenShip_certificate']['name'];
  $pic_temp2 = $_FILES['citizenShip_certificate']['tmp_name'];
  $pic_size2 = $_FILES['citizenShip_certificate']['size'];

  $pic_new2 = rand(100, 1000) . '-' . $pic_name2;
  $pic_store2 = "images/documents/" . $pic_new2;

  if (move_uploaded_file($pic_temp2, $pic_store2)) {
    $citizenShip_certificate = $pic_new2;
  }


  $pic_name3 = $_FILES['nadara_id_cards']['name'];
  $pic_temp3 = $_FILES['nadara_id_cards']['tmp_name'];
  $pic_size3 = $_FILES['nadara_id_cards']['size'];

  $pic_new3 = rand(100, 1000) . '-' . $pic_name3;
  $pic_store3 = "images/documents/" . $pic_new3;

  if (move_uploaded_file($pic_temp3, $pic_store3)) {
    $nadara_id_cards = $pic_new3;
  }


  $pic_name4 = $_FILES['ssn_certificate']['name'];
  $pic_temp4 = $_FILES['ssn_certificate']['tmp_name'];
  $pic_size4 = $_FILES['ssn_certificate']['size'];

  $pic_new4 = rand(100, 1000) . '-' . $pic_name4;
  $pic_store4 = "images/documents/" . $pic_new4;

  if (move_uploaded_file($pic_temp4, $pic_store4)) {
    $ssn_certificate = $pic_new4;
  }



  $associate_query = "INSERT INTO `documents`(`uploaded_by`,`client_id`, `marriage_certificate`, `divorce_certificate`, `password`) VALUES ('$userid','$client_id','n/a','n/a','123456789')";
  $insert_associate = mysqli_query($con, $associate_query);
  $last_id = mysqli_insert_id($con);







  //////////////////////// SCAN 1 ///////////////////////////////////////////////     


  shell_exec('"C:\\Program Files (x86)\\Tesseract-OCR\\tesseract" "C:\\xampp\\htdocs\\syedpro_new\\images\\documents\\' . $green_card . '" out');

  //echo "<br><h3>OCR after reading</h3><br><pre>";

  $myfile = fopen("out.txt", "r") or die("Unable to open file!");
  $total_green_card_text = fread($myfile, filesize("out.txt"));
  fclose($myfile);
  "</pre>";
  echo "<br/><hr>";
  //echo $totaltext;


  $tg = $total_green_card_text;

  if (strpos($tg, $clientname) !== false) {
    $associate_query = "UPDATE `documents` SET `green_card`='$green_card' where document_id='$last_id'";
    $insert_associate = mysqli_query($con, $associate_query);
  } elseif (strpos($tg, $clientssn) !== false) {
    $associate_query = "UPDATE `documents` SET `green_card`='$green_card' where document_id='$last_id'";
    $insert_associate = mysqli_query($con, $associate_query);
  } else {
    $_SESSION['green_card_cer'] = $green_card;
    $msg .= "Green Not Found<br/>";
  }




  //////////////////////// SCAN 2 /////////////////////////////////////////////// 


  shell_exec('"C:\\Program Files (x86)\\Tesseract-OCR\\tesseract" "C:\\xampp\\htdocs\\syedpro_new\\images\\documents\\' . $citizenShip_certificate . '" out1');

  //echo "<br><h3>OCR after reading</h3><br><pre>";

  $myfile1 = fopen("out1.txt", "r") or die("Unable to open file!");
  $total_citizenShip_certificate_text = fread($myfile1, filesize("out1.txt"));
  fclose($myfile1);
  "</pre>";
  echo "<br/><hr>";
  //echo $totaltext;

  $tc = $total_citizenShip_certificate_text;
  if (strpos($tc, $clientname) !== false) {
    $associate_query = "UPDATE `documents` SET `citizenship_certificate`='$citizenShip_certificate' where document_id='$last_id'";
    $insert_associate = mysqli_query($con, $associate_query);
  } elseif (strpos($tc, $clientssn) !== false) {
    $associate_query = "UPDATE `documents` SET `citizenship_certificate`='$citizenShip_certificate' where document_id='$last_id'";
    $insert_associate = mysqli_query($con, $associate_query);
  } else {
    $_SESSION['citizenShip_certificate'] = $citizenShip_certificate;
    $msg .= "Citizen Not Found<br/>";
  }


  //////////////////////// SCAN 3 ///////////////////////////////////////////////     


  shell_exec('"C:\\Program Files (x86)\\Tesseract-OCR\\tesseract" "C:\\xampp\\htdocs\\syedpro_new\\images\\documents\\' . $nadara_id_cards . '" out2');

  //echo "<br><h3>OCR after reading</h3><br><pre>";

  $myfile1 = fopen("out2.txt", "r") or die("Unable to open file!");
  $total_nadara_id_cards_text = fread($myfile1, filesize("out2.txt"));
  fclose($myfile1);
  "</pre>";
  echo "<br/><hr>";
  //echo $totaltext;


  $tn = $total_nadara_id_cards_text;
  if (strpos($tn, $clientname) !== false) {
    $associate_query = "UPDATE `documents` SET `nadara_id_card`='$nadara_id_cards' where document_id='$last_id'";
    $insert_associate = mysqli_query($con, $associate_query);
  } elseif (strpos($tn, $clientssn) !== false) {
    $associate_query = "UPDATE `documents` SET `nadara_id_card`='$nadara_id_cards' where document_id='$last_id'";
    $insert_associate = mysqli_query($con, $associate_query);
  } else {
    $_SESSION['nadara_id_cards'] = $nadara_id_cards;
    $msg .= "Nadra Not Found<br/>";
  }





  //////////////////////// SCAN 4 ///////////////////////////////////////////////     


  shell_exec('"C:\\Program Files (x86)\\Tesseract-OCR\\tesseract" "C:\\xampp\\htdocs\\syedpro_new\\images\\documents\\' . $ssn_certificate . '" out3');

  //echo "<br><h3>OCR after reading</h3><br><pre>";

  $myfile3 = fopen("out3.txt", "r") or die("Unable to open file!");
  $total_ssn_certificate_text = fread($myfile3, filesize("out3.txt"));
  fclose($myfile3);
  "</pre>";
  echo "<br/><hr>";
  //echo $totaltext;


  $ts = $total_ssn_certificate_text;
  if (strpos($ts, $clientname) !== false) {
    $associate_query = "UPDATE `documents` SET `ssn_certificate`='$ssn_certificate' where document_id='$last_id'";
    $insert_associate = mysqli_query($con, $associate_query);
  } elseif (strpos($ts, $clientssn) !== false) {
    $associate_query = "UPDATE `documents` SET `ssn_certificate`='$ssn_certificate' where document_id='$last_id'";
    $insert_associate = mysqli_query($con, $associate_query);
  } else {
    $_SESSION['ssn_certificate'] = $ssn_certificate;
    $msg .= "SSN Not Found<br/>";
  }


  /////////////////////    VALIDATE SCANING IMAGES     /////////////////////////



  if ($insert_associate) {

    if (isset($_SESSION['green_card_cer'])) {
      header('Location: reupload.php?document=' . $last_id);
    } elseif (isset($_SESSION['citizenShip_certificate'])) {
      header('Location: reupload.php?document=' . $last_id);
    } elseif (isset($_SESSION['nadara_id_cards'])) {
      header('Location: reupload.php?document=' . $last_id);
    } elseif (isset($_SESSION['ssn_certificate'])) {
      header('Location: reupload.php?document=' . $last_id);
    } else {
      $msg .= "<div class='alert alert-success' role='alert'>
                    <b>Spouse Has Been Added</b>
                  </div>";
    }
  } else {
    $msg .= "<div class='alert alert-danger' role='alert'>
                    <b>Something wrong!</b>
                  </div>";
  }
}


if (isset($_POST['verifypassword'])) {
  $document_id = $_POST['documentid'];
  $password = $_POST['password'];

  $getcycle = mysqli_query($con, "SELECT * FROM documents where document_id='$document_id'");
  while ($getdetail = mysqli_fetch_array($getcycle)) {
    $encrypted_pass = $getdetail['password'];
  }

  if ($password == $encrypted_pass) {
    $_SESSION['encryptedpass'] = $password;
    header('Location: viewdocuments.php?document=' . $document_id);
  }
  $msg .= "<div class='alert alert-danger' role='alert'>
                    <b>Wrong Password, Please Try Again</b>
                  </div>";
}

if (isset($_POST['addbusiness'])) {
  $bus_name = $_POST['bus_name'] ?? '';
  $ssn = $_POST['ssn'] ?? '';
  $ein = $_POST['ein'] ?? '';
  $president_name = $_POST['president_name'] ?? '';
  $president_dob = $_POST['president_dob'] ?? '';
  $res_person = $_POST['res_person'] ?? '';
  $other_con_1 = $_POST['other_con_1'] ?? '';
  $other_con_2 = $_POST['other_con_2'] ?? '';

  $prm_per_street = $_POST['prm_per_street'] ?? '';
  $prm_per_city = $_POST['prm_per_city'] ?? '';
  $prm_per_state = $_POST['prm_per_state'] ?? '';
  $prm_per_zip = $_POST['prm_per_zip'] ?? '';

  $client_since = $_POST['client_since'] ?? '';
  $bus_start_date = $_POST['bus_start_date'] ?? '';
  $incorp_date = $_POST['incorp_date'] ?? '';
  $incorp_state = $_POST['incorp_state'] ?? '';

  $bus_street = $_POST['bus_street'] ?? '';
  $bus_city = $_POST['bus_city'] ?? '';
  $bus_state = $_POST['bus_state'] ?? '';
  $bus_zip = $_POST['bus_zip'] ?? '';

  $mail_street = $_POST['mail_street'] ?? '';
  $mail_city = $_POST['mail_city'] ?? '';
  $mail_state = $_POST['mail_state'] ?? '';
  $mail_zip = $_POST['mail_zip'] ?? '';


  $entity_form_id = !empty($_POST['entity_form_id']) ? (int) $_POST['entity_form_id'] : 0;
  $busact_id = !empty($_POST['busact_id']) ? (int) $_POST['busact_id'] : 0;

  $currentdate = date("Y-m-d");


  if (isset($_POST["coupon_question"])) {
    $mail_street = $bus_street;
    $mail_city = $bus_city;
    $mail_state = $bus_state;
    $mail_zip = $bus_zip;
  }

  $queryy = "INSERT INTO addibusiness
    (member_id, bus_name, ein, ssn, president_name, president_dob, res_person, other_con_1, other_con_2,
    prm_per_street, prm_per_city, prm_per_state, prm_per_zip,
    bus_street, bus_city, bus_state, bus_zip,
    mail_street, mail_city, mail_state, mail_zip,
    client_since, bus_start_date, incorp_date, incorp_state,
    entity_form_id, busact_id, user_id, date_year_end, tax_due_date)
    VALUES
    ('$client_id', '$bus_name', '$ein', '$ssn', '$president_name', '$president_dob', '$res_person', '$other_con_1', '$other_con_2',
    '$prm_per_street', '$prm_per_city', '$prm_per_state', '$prm_per_zip',
    '$bus_street', '$bus_city', '$bus_state', '$bus_zip',
    '$mail_street', '$mail_city', '$mail_state', '$mail_zip',
    '$client_since', '$bus_start_date', '$incorp_date', '$incorp_state',
    $entity_form_id, $busact_id, '$userid', '$currentdate', '$currentdate')";

  $insert_query = mysqli_query($con, $queryy);

  if ($insert_query) {
    $msg .= "<div class='alert alert-success'>Client Business Has Been Added Successfully.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
  }
}



if (isset($_POST['delete'])) {
  $msg = '';
  $id = $_POST['id'];
  $tblname = $_POST['tblname'];
  $colname = $_POST['colname'];
  $queryy = "DELETE FROM $tblname where $colname='$id'";
  $insert_query = mysqli_query($con, $queryy);
  if ($insert_query) {
    $msg .= "<div class='alert alert-success'>Record Been Deleted Successfully.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>DELETE FROM '$tblname' where '$colname'='$id'" . $con->error;
  }
}


if (isset($_POST['loginSecuredArea'])) {
  $user_secured = $_POST['user_secured'];
  $pass_secured = $_POST['pass_secured'];

  $query1 = "SELECT * FROM users WHERE user_login='$user_secured' AND user_password='$pass_secured' AND user_login_status='1' AND user_ban_status='0'";
  $result = mysqli_query($con, $query1);
  $rows = mysqli_num_rows($result);

  if ($rows > 0) {
    $row = mysqli_fetch_array($result);
    if ($row['user_login'] == $user_secured and $row['user_password'] == $pass_secured) {
      $_SESSION['secure_auth'] = 1;
      $_SESSION['secure_client_name'] = $row['user_name'];
      header("Location:secured_area.php?client_id=$client_id");
      exit;
    }
  } else {
    $msg .= "<div class='alert alert-danger' role='alert'>
              <b>Wrong username or password. Please try again.</b>
            </div>";
  }
}
if (isset($_POST['markordercomplete'])) {


  $ostatus = '';



  $order_id = $_POST['orderid'];
  $queryy = "UPDATE orders SET order_status='-1' WHERE order_id='$order_id'";


  $insert_query = mysqli_query($con, $queryy);
  if ($insert_query) {
    $order_query = mysqli_query($con, "SELECT * FROM orders WHERE order_id='$order_id'");
    while ($fetch_order = mysqli_fetch_array($order_query)) {
      $user_id = $_SESSION['user_id'];
      $order_id = $fetch_order['order_id'];
      $mem_id = $fetch_order['mem_id'];
      $prod_id = $fetch_order['prod_id'];
      $bil_cyc_id = $fetch_order['bil_cyc_id'];
      $bil_cyc_type = $fetch_order['bil_cyc_type'];
      $bil_cyc_months = $fetch_order['bil_cyc_months'];
      $start_date = $fetch_order['start_date'];
      $exp_date = $fetch_order['exp_date'];
      $sub_total = $fetch_order['sub_total'];
      $discount = $fetch_order['discount'];
      $total = $fetch_order['total'];
      $date_added = date('Y-m-d');
      $order_status = $fetch_order['order_status'];
      $assign_to = $fetch_order['assign_to'];

      $addmethod = mysqli_query($con, "INSERT INTO `orders_completed`(`mem_id`, `prod_id`, `bil_cyc_id`, `bil_cyc_type`, `bil_cyc_months`, `start_date`, `exp_date`, `sub_total`, `discount`, `total`, `date_added`, `user_id`, `order_status`, `assign_to`) VALUES ('$mem_id','$prod_id','$bil_cyc_id','$bil_cyc_type','$bil_cyc_months','$start_date','$exp_date','$sub_total','$discount','$total','$date_added','$user_id','-1','$assign_to')");

    }

    $ostatus .= "<div class='alert alert-success'>Task Has Been Marked Successfully.</div>";
  } else {
    $ostatus .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
  }
}



if (isset($_POST['edit_comment'])) {
  $comment_id = $_POST['edit_comment_id'];
  $comment_details = $_POST['comment_detail'];
  $current_time = date("Y-m-d H:i:s");

  $update_query = "UPDATE comments 
                    SET comment_detail = '$comment_details',
                        comment_updated_by = '$userid',
                        comment_time = '$current_time'
                    WHERE comment_id = '$comment_id'";

  $update_result = mysqli_query($con, $update_query);

  if ($update_result) {
    $msg .= "<div class='alert alert-success' role='alert'>
                 <b>Comment Updated Successfully</b>
               </div>";
  } else {
    $msg .= "<div class='alert alert-danger' role='alert'>
                 <b>Update failed: " . $con->error . "</b>
               </div>";
  }
}


if (isset($_POST['link_business'])) {
  $business_id = (int) $_POST['business_id'];
  $client_id = (int) $client_id;
  $userid = (int) $_SESSION['user_id'];


  mysqli_query(
    $con,
    "INSERT INTO business_member_assoc (business_id, member_id, user_id)
         VALUES ($business_id, $client_id, $userid)"
  );


  mysqli_query(
    $con,
    "INSERT INTO business_member_assoc (business_id, member_id, user_id)
         VALUES ($client_id, $business_id, $userid)"
  );

  $msg .= "<div class='alert alert-success'>Business linked successfully.</div>";
}


if (isset($_POST['unlink_business'])) {
  $link_id = $_POST['link_id'];
  $delete = mysqli_query($con, "DELETE FROM business_member_assoc WHERE id = '$link_id'");
  if ($delete) {
    $msg .= "<div class='alert alert-success'>Business unlinked successfully.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Error: " . mysqli_error($con) . "</div>";
  }
}


if (isset($_POST['updatebusiness'])) {
  $business_id = $_POST['business_id'];
  $bus_name = $_POST['bus_name'];
  $president_name = $_POST['president_name'];
  $president_dob = $_POST['president_dob'];
  $res_person = $_POST['res_person'];
  $ein = $_POST['ein'];
  $ssn = $_POST['ssn'];
  $client_since = $_POST['client_since'];
  $bus_start_date = $_POST['bus_start_date'];
  $incorp_date = $_POST['incorp_date'];
  $incorp_state = $_POST['incorp_state'];
  $bus_street = $_POST['bus_street'];
  $bus_city = $_POST['bus_city'];
  $bus_state = $_POST['bus_state'];
  $bus_zip = $_POST['bus_zip'];
  $mail_street = $_POST['mail_street'];
  $mail_city = $_POST['mail_city'];
  $mail_state = $_POST['mail_state'];
  $mail_zip = $_POST['mail_zip'];
  $prm_per_street = $_POST['prm_per_street'];
  $prm_per_city = $_POST['prm_per_city'];
  $prm_per_state = $_POST['prm_per_state'];
  $prm_per_zip = $_POST['prm_per_zip'];
  $other_con_1 = $_POST['other_con_1'];
  $other_con_2 = $_POST['other_con_2'];
  $entity_form_id = $_POST['entity_form_id'];
  $busact_id = $_POST['busact_id'];

  $update = mysqli_query($con, "
        UPDATE addibusiness SET
            bus_name = '$bus_name',
            president_name = '$president_name',
            president_dob = '$president_dob',
            res_person = '$res_person',
            ein = '$ein',
            ssn = '$ssn',
            client_since = '$client_since',
            bus_start_date = '$bus_start_date',
            incorp_date = '$incorp_date',
            incorp_state = '$incorp_state',
            bus_street = '$bus_street',
            bus_city = '$bus_city',
            bus_state = '$bus_state',
            bus_zip = '$bus_zip',
            mail_street = '$mail_street',
            mail_city = '$mail_city',
            mail_state = '$mail_state',
            mail_zip = '$mail_zip',
            prm_per_street = '$prm_per_street',
            prm_per_city = '$prm_per_city',
            prm_per_state = '$prm_per_state',
            prm_per_zip = '$prm_per_zip',
            other_con_1 = '$other_con_1',
            other_con_2 = '$other_con_2',
            entity_form_id = '$entity_form_id',
            busact_id = '$busact_id'
        WHERE addibusiness_id = '$business_id'
    ");

  if ($update) {
    $msg .= "<div class='alert alert-success'>Business updated successfully.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Error: " . mysqli_error($con) . "</div>";
  }

}

if (isset($_POST['addNewcomment'])) {
  $comment_details = mysqli_real_escape_string($con, $_POST['comment_details']);
  $current_time = date("Y-m-d H:i:s");

  $insert_comment = "INSERT INTO new_comments (comment_detail, member_id, user_id, comment_time, comment_status, comment_updated_by, invoice_id) 
                       VALUES ('$comment_details', '$client_id', '$userid', '$current_time', '0', '$userid', '0')";

  if (mysqli_query($con, $insert_comment)) {
    $msg .= "<div class='alert alert-success'><b>Comment Has Been Posted</b></div>";

  } else {

    $msg .= "<div class='alert alert-success'><b>Error: " . mysqli_error($con) . "</b></div>";
  }
}


if (isset($_POST['update_New_comment'])) {
  $comment_id = mysqli_real_escape_string($con, $_POST['comment_id']);
  $comment_details = mysqli_real_escape_string($con, $_POST['comment_detail']);
  $current_time = date("Y-m-d H:i:s");

  $update_comment = "UPDATE new_comments SET 
                      comment_detail = '$comment_details', 
                      comment_time = '$current_time', 
                      comment_updated_by = '$userid' 
                      WHERE comment_id = '$comment_id'";

  if (mysqli_query($con, $update_comment)) {
    $msg .= "<div class='alert alert-success' role='alert'><b>Comment Updated Successfully</b></div>";

  } else {
    $msg .= "<div class='alert alert-danger' role='alert'><b>Error: " . mysqli_error($con) . "</b></div>";
  }
}


if (isset($_POST['delete_New_comment'])) {
  $comment_id = mysqli_real_escape_string($con, $_POST['comment_id']);

  $delete_query = "DELETE FROM new_comments WHERE comment_id = '$comment_id'";

  if (mysqli_query($con, $delete_query)) {
    $msg .= "<div class='alert alert-success' role='alert'><b>Comment Deleted Successfully</b></div>";

  } else {
    $msg .= "<div class='alert alert-danger' role='alert'><b>Error: " . mysqli_error($con) . "</b></div>";
  }
}

if (isset($_POST['add_member_service'])) {
  $mid = (int) $_POST['membership_id'];
  $desc = mysqli_real_escape_string($con, $_POST['service_description'] ?? '');
  $uid = (int) $_SESSION['user_id'];

  mysqli_query($con, "
    INSERT INTO member_services (member_id, membership_id, user_id, description)
    VALUES ($client_id, $mid, $uid, '$desc')
    ON DUPLICATE KEY UPDATE 
      added_at = NOW(),
      description = VALUES(description)
  ");

  $msg .= '<div class="alert alert-success">Service added.</div>';
}

if (isset($_POST['edit_member_service'])) {
  $sid = (int) $_POST['service_id'];
  $mid = (int) $_POST['membership_id'];
  $desc = mysqli_real_escape_string($con, $_POST['service_description'] ?? '');

  mysqli_query($con, "
    UPDATE member_services 
    SET membership_id = $mid, description = '$desc' 
    WHERE id = $sid AND member_id = $client_id
  ");

  $msg .= '<div class="alert alert-success">Service updated.</div>';
}
if (isset($_POST['delete_member_service'])) {
  $sid = (int) $_POST['service_id'];
  mysqli_query($con, "DELETE FROM member_services WHERE id=$sid AND member_id=$client_id");
  $msg .= '<div class="alert alert-warning">Service removed.</div>';
}


$client_query = mysqli_query($con, "SELECT sms_enabled, email_enabled FROM client_button_settings WHERE member_id='$client_id'");
$client_data = mysqli_fetch_assoc($client_query);
$sms_enabled = $client_data['sms_enabled'] ?? 1;
$email_enabled = $client_data['email_enabled'] ?? 1;

?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
  <title>Syed Professional Services - Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />




  <?php include "includes/links.php"; ?>

  <style type="text/css">
    .text-sm {
      font-size: 13px !important;
      margin-bottom: 5px;
    }

    .swal2-container.swal2-top-end>.swal2-popup {
      margin-top: 40px !important;
    }
  </style>
  <style>
    div.text {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: "----";
    }

    .form-label {
      margin-bottom: 0.5rem;
      margin-top: 15px;
    }

    #box {
      display: block;
    }

    .client-menu-bar {
      position: sticky;
      top: 0;
      z-index: 1020;
      background-color: #f8f9fa;
      border-bottom: 1px solid #dee2e6;
    }

    .client-menu-bar .nav-link {
      font-size: 13px;
      padding: 6px 10px;
      color: #495057;
      border-radius: 0;
    }

    .client-menu-bar .nav-link.active {
      background-color: #6FD943;
      color: white;
      font-weight: bold;
    }

    #personal-detail,
    #associated-products,
    #comments,
    #family-members,
    #quotations,
    #member-services,
    #businesses,
    #documents,
    #notes,
    #projects {
      scroll-margin-top: 150px;
      /* match menu height */
    }
  </style>
  <!-- <script src="ckeditor/ckeditor.js"></script> -->
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
    <?php if (isset($msg)) {
      echo $msg;
    } ?>
    <div class="dash-content">
      <!-- Horizontal Sticky Menu Bar -->
      <div class="client-menu-bar sticky-top bg-light shadow-sm p-2 mb-3">
        <nav class="nav nav-pills nav-fill">
          <a class="nav-link" href="#personal-detail">Personal</a>
          <!-- <a class="nav-link" href="#company-detail">Company</a> -->
          <a class="nav-link" href="#associated-products">Products</a>
          <a class="nav-link" href="#comments">Comments</a>
          <!-- <a class="nav-link" href="#invoices-comments">Invoices</a> -->
          <a class="nav-link" href="#family-members">Family</a>
          <a class="nav-link" href="#quotations">Quotations</a>
          <a class="nav-link" href="#member-services">Services</a>
          <a class="nav-link" href="#businesses">Businesses</a>
          <a class="nav-link" href="#documents">Documents</a>
          <a class="nav-link" href="#notes">Notes</a>
          <a class="nav-link" href="#projects">Projects</a>
        </nav>
      </div>
      <div class="page-header">
        <div class="page-block">
          <div class="row align-items-center">

            <div class="col-auto">
              <div class="page-header-title">
                <h4 class="m-b-10">Manage Clients</h4>
              </div>
              <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item">
                  <a href="view_clients.php">Client</a>
                </li>

                <li class="breadcrumb-item">#<?php echo $client_id; ?></li>
              </ul>
            </div>
            <div class="col">

              <div class="d-flex flex-wrap justify-content-end gap-2 mt-3 m-2">

                <?php if ($client_active == "Yes") { ?>
                  <div class="form-check form-switch d-flex align-items-center me-2">
                    <input class="form-check-input activity-switch me-2" type="checkbox" role="switch"
                      data-member-id="<?php echo $client_id; ?>" <?php echo ($status == 1) ? "checked" : ""; ?>
                      style="background-color: <?php echo ($status == 1) ? '#6FD943' : '#6FD943'; ?>;">
                    <span class="fw-bold" style="color: <?php echo ($status == 1) ? '#6FD943' : 'red'; ?>;">
                      <?php echo ($status == 1) ? "Active" : "Inactive"; ?>
                    </span>
                  </div>
                <?php } ?>

                <?php
                // Get current button status for this client
                $btn_query = "SELECT sms_enabled, email_enabled 
              FROM client_button_settings 
              WHERE member_id = '$client_id' 
              LIMIT 1";
                $btn_result = mysqli_query($con, $btn_query);
                $btn = mysqli_fetch_assoc($btn_result);

                $sms_checked = ($btn && $btn['sms_enabled'] == 1) ? "checked" : "";
                $email_checked = ($btn && $btn['email_enabled'] == 1) ? "checked" : "";
                ?>

                <!-- SMS Switch -->
                <div class="form-check form-switch d-flex align-items-center me-3">
                  <input class="form-check-input sms-switch me-2" type="checkbox" role="switch"
                    id="smsSwitch<?php echo $client_id; ?>" data-client-id="<?php echo $client_id; ?>" <?php echo $sms_checked; ?>>
                  <span class="fw-bold">Send SMS</span>
                </div>

                <!-- Email Switch -->
                <div class="form-check form-switch d-flex align-items-center">
                  <input class="form-check-input email-switch me-2" type="checkbox" role="switch"
                    id="emailSwitch<?php echo $client_id; ?>" data-client-id="<?php echo $client_id; ?>" <?php echo $email_checked; ?>>
                  <span class="fw-bold">Send Email</span>
                </div>

                <button class="btn btn-sm btn-warning ms-2" data-bs-toggle="modal" data-bs-target="#modalWarning">
                  <i class="fas fa-exclamation-triangle"></i> Add Warning
                </button>

                <?php
                $perm_query = mysqli_query($con, "SELECT delete_client FROM dashboard_access WHERE user_type='$user_type'");
                $perm = mysqli_fetch_assoc($perm_query);

                if ($perm['delete_client'] == "Yes") {
                  if ($status != 1) { ?>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                      data-bs-target="#deleteClientModal">
                      <i class="ti ti-trash"></i> Delete Client
                    </button>
                  <?php } else { ?>
                    <span data-bs-toggle="modal" data-bs-target="#deactivateFirstModal">
                      <button class="btn btn-sm btn-danger" disabled title="You must deactivate the client before deleting"
                        style="background-color: #cccccc; border-color: #cccccc; cursor: not-allowed;">
                        <i class="ti ti-trash"></i> Delete Client
                      </button>
                    </span>
                  <?php }
                } ?>

                <!-- Edit Client -->
                <a href="edit_client.php?id=<?php echo $client_id; ?>" class="btn btn-sm btn-primary tooltip1"
                  data-bs-original-title="Edit Client Details">
                  <i class="ti ti-pencil"></i> Edit Client
                  <span class="tooltiptext1">Edit Client Details</span>
                </a>

                <?php
                $perm = mysqli_fetch_assoc(mysqli_query($con, "SELECT invoice_and_billing FROM dashboard_access WHERE user_type = '$user_type'"));
                if ($perm['invoice_and_billing'] === 'Yes'): ?>
                  <a href="client_billing.php?id=<?php echo $client_id; ?>" class="btn btn-sm btn-primary tooltip1"
                    data-bs-original-title="Billing Area">
                    <i class="ti ti-file-invoice"></i> Invoice & Billing
                    <span class="tooltiptext1">Billing Area</span>
                  </a>
                <?php endif; ?>

                <?php
                $user_id = $_SESSION['user_id'];
                $query = "SELECT has_secured_access FROM users WHERE user_id = $user_id";
                $result = mysqli_query($con, $query);
                $rrow = mysqli_fetch_assoc($result);

                if ($rrow['has_secured_access'] == 1) { ?>
                  <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#login_secured_area">
                    <i class="ti ti-lock"></i> Secured Area
                  </button>
                <?php } ?>

              </div>




              <!-- Delete Client Confirmation Modal -->
              <div class="modal fade" id="deleteClientModal" tabindex="-1" aria-labelledby="deleteClientModalLabel"
                aria-hidden="true">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="deleteClientModalLabel">Confirm Delete Client</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <?php if ($status == 1) { ?>
                        <div class="alert alert-danger">
                          <strong>Warning!</strong> You cannot delete an active client. Please deactivate the client
                          first.
                        </div>
                      <?php } else { ?>
                        <p>Are you sure you want to delete this client? This action cannot be undone.</p>
                        <p><strong>Client ID:</strong> <?php echo $client_id; ?></p>
                      <?php } ?>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <?php if ($status == 0) { ?>
                        <form method="post" action="delete_client.php">
                          <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                          <button type="submit" class="btn btn-danger" name="delete_client" value="1">
                            Delete Client
                          </button>
                        </form>

                      <?php } ?>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>


      <!-- ► IMPORTANT WARNING BANNER -->
      <?php
      $warn = mysqli_fetch_assoc(mysqli_query(
        $con,
        "SELECT id, warning, created_at, u.user_login, w.created_by
  FROM client_warnings w
  JOIN users u ON u.user_id = w.created_by
  WHERE w.member_id = $client_id AND w.is_active = 1
  ORDER BY w.id DESC LIMIT 1"
      ));

      if ($warn):
        $canEdit = in_array($_SESSION['user_type'], ['1', '2']) ||
          $_SESSION['user_id'] == $warn['created_by'];
        ?>
        <div class="alert alert-warning alert-dismissible fade show mb-0 d-flex align-items-center" role="alert">
          <strong><i class="fas fa-exclamation-triangle"></i> IMPORTANT NOTE:</strong>
          <span class="ms-2 flex-grow-1"><?= nl2br(htmlspecialchars($warn['warning'])) ?></span>

          <?php if ($canEdit): ?>
            <button class="btn btn-sm btn-outline-secondary me-1" onclick="editWarning(<?= $warn['id'] ?>)">
              <i class="fas fa-edit"></i> Edit</button>
            <button class="btn btn-sm btn-outline-danger" onclick="delWarning(<?= $warn['id'] ?>)">
              <i class="fas fa-trash"></i> Delete</button>
          <?php endif; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
      <!-- ◄ END WARNING BANNER -->

      <?php
      $querr = mysqli_query($con, "SELECT * FROM members WHERE member_id='$client_id'");
      while ($fetch_member = mysqli_fetch_array($querr)) {
        $specialClient = $fetch_member['specialClient'];
        $ach_payer_id = $fetch_member['ach_payer'];

        if ($specialClient == 1 || $ach_payer_id != 0) {
          ?>

          <div style="display: flex; gap: 15px; padding: 10px; background-color: #f5f5f5;">
            <?php

            if ($specialClient == 1) {
              ?>

              <div
                style="flex-basis: 50%; background-color: #d4edda; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="text-align: center; padding: 20px;">
                  <h5 style="font-size: 26px; margin: 0; color: #155724; font-family: 'Arial', sans-serif; font-weight: 600;">
                    Special Client
                  </h5>
                </div>
              </div>
              <?php
            }

            if ($ach_payer_id != 0) {
              ?>

              <div
                style="flex-basis: 50%; background-color: #d4edda; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="text-align: center; padding: 20px;">
                  <h5 style="font-size: 26px; margin: 0; color: #155724; font-family: 'Arial', sans-serif; font-weight: 600;">
                    ACH Payer
                  </h5>
                </div>
              </div>
              <?php
            }
            ?>
          </div>
          <?php
        }
      }
      ?>




      <!-- Personal Detail -->
      <div class="row">
        <div class="col-xl-12">
          <div class="row" id="personal-detail">
            <?php
            $querr = mysqli_query($con, "SELECT * FROM members where member_id='$client_id'");
            $c = 1;
            while ($fetch_member = mysqli_fetch_array($querr)) {
              $member_name = $fetch_member['name'];
              ?>
              <div class="col-sm-12 col-md-6">

                <div class="card ">
                  <div class="card-body employee-detail-body fulls-card">
                    <h5>Personal Detail</h5>
                    <hr>
                    <div class="row">
                      <div class="col-md-5">
                        <?php
                        $imagePath = !empty($fetch_member['mem_pic']) ? "images/clients/" . $fetch_member['mem_pic'] : "images/default-user.png";
                        ?>
                        <img src="<?php echo $imagePath; ?>" style="width: 100%;"
                          onerror="this.onerror=null;this.src='images/default-user.png';">
                      </div>


                      <div class="col-md-7">

                        <div class="info text-sm">
                          <strong class="font-bold">Client ID : </strong>
                          <span>#<?php echo $fetch_member['member_id']; ?></span>
                        </div>
                        <!-- <div class="info text-sm">
                            <strong class="font-bold">Client : </strong>
                            <span><?php echo $fetch_member['specialClient'] ?? 0; ?>
                            </span>
                          </div> -->
                        <div class="info text-sm font-style">
                          <strong class="font-bold">Name :</strong>
                          <span><?php echo $clientname = $fetch_member['name'] . " " . $fetch_member['mname'] . " " . $fetch_member['lname']; ?></span>
                        </div>
                        <div class="info text-sm font-style">
                          <strong class="font-bold">DOB :</strong>
                          <span>
                            <?php
                            $dob = $fetch_member['dob'];

                            if (empty($dob) || $dob === '0000-00-00') {
                              echo 'N/A';
                            } else {
                              $date = DateTime::createFromFormat('Y-m-d', $dob);
                              echo $date ? $date->format('m/d/Y') : 'N/A';
                            }
                            ?>
                          </span>
                        </div>
                        <!-- <div class="info text-sm font-style">
                          <strong class="font-bold">Fax: </strong>
                          <span><?php echo $fetch_member['fax']; ?></span>
                        </div> -->
                        <div class="info text-sm font-style">
                          <strong class="font-bold">Address: </strong>
                          <span><?php $clientaddress = $fetch_member['hstreet'] . ", " . $fetch_member['hcity'] . ", " . $fetch_member['hstate'] . ", " . $fetch_member['hzip'];
                          echo $fetch_member['hstreet']; ?></span>
                        </div>
                        <div class="info text-sm font-style">
                          <strong class="font-bold">City: </strong>
                          <span><?php echo $fetch_member['hcity']; ?></span>
                        </div>
                        <div class="info text-sm font-style">
                          <strong class="font-bold">State: </strong>
                          <span><?php echo $fetch_member['hstate']; ?></span>
                        </div>
                        <div class="info text-sm font-style">
                          <strong class="font-bold">Zip: </strong>
                          <span><?php echo $fetch_member['hzip']; ?></span>
                        </div>

                        <div class="info text-sm font-style">
                          <strong class="font-bold">County: </strong>
                          <span><?php echo $fetch_member['hcounty']; ?></span>
                        </div>


                        <div class="info text-sm font-style">
                          <strong class="font-bold">Phone (cell): </strong>
                          <span><?php echo $clientphone = $fetch_member['cell_ph']; ?></span>
                        </div>



                        <div class="info text-sm">
                          <strong class="font-bold">Primary Email : </strong>
                          <span>
                            <a href="mailto:<?php echo $clientemail = $fetch_member['prm_email']; ?>" target="_blank">
                              <?php echo $clientemail; ?>
                            </a>
                          </span>
                        </div>


                        <div class="info text-sm font-style">
                          <strong class="font-bold">SSN: </strong>
                          <span><?php echo $fetch_member['ss']; ?></span>
                        </div>
                        <div class="info text-sm font-style">
                          <strong class="font-bold">Billing Balance: </strong>
                          <span>
                            <?php
                            $result = mysqli_query($con, "SELECT SUM(balance) as balance_total from billing_invoice where mem_id='$client_id'");
                            $data = mysqli_fetch_assoc($result);
                            echo '$' . $data['balance_total'];
                            ?>
                          </span>
                        </div>
                        <div class="info text-sm font-style">
                          <strong class="font-bold">Client Since: </strong>
                          <span>
                            <?php
                            if (!empty($fetch_member['client_since']) && $fetch_member['client_since'] !== '0000-00-00') {
                              echo date("m/d/Y", strtotime($fetch_member['client_since']));
                            } else {
                              echo "N/A";
                            }
                            ?>
                          </span>
                        </div>


                        <?php
                        $user_id = (int) $fetch_member['user_id'];
                        $added_by_id = (int) $fetch_member['added_by'];

                        $added_user_q = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = $added_by_id");
                        $added_user_f = mysqli_fetch_assoc($added_user_q);
                        $added_by_name = $added_user_f ? $added_user_f['user_name'] : 'Unknown';
                        $added_by_display = $added_by_name !== 'Unknown' ? getInitials($added_by_name) . " ($added_by_id)" : "Unknown ($added_by_id)";

                        $updated_by_name = null;
                        $updated_by_display = null;

                        if ($user_id !== $added_by_id) {
                          $updated_user_q = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = $user_id");
                          $updated_user_f = mysqli_fetch_assoc($updated_user_q);
                          $updated_by_name = $updated_user_f ? $updated_user_f['user_name'] : 'Unknown';
                          $updated_by_display = $updated_by_name !== 'Unknown' ? getInitials($updated_by_name) . " ( $user_id)" : "Unknown ( $user_id)";
                        }
                        ?>

                        <div class="info text-sm font-style">
                          <strong class="font-bold">Added By: </strong>
                          <span><?php echo htmlspecialchars($added_by_display); ?></span>
                        </div>

                        <?php if ($updated_by_display !== null): ?>
                          <div class="info text-sm font-style">
                            <strong class="font-bold">Updated By: </strong>
                            <span><?php echo htmlspecialchars($updated_by_display); ?></span>
                          </div>
                        <?php endif; ?>


                      </div>
                    </div>
                  </div>
                </div>

              </div>
              <div class="col-sm-12 col-md-6">

                <div class="card ">
                  <div class="card-body employee-detail-body fulls-card">
                    <h5>Company Detail</h5>
                    <hr>
                    <div class="row" id="company-detail">

                      <div class="col-md-6">
                        <div class="info text-sm font-style">
                          <strong class="font-bold">Phone (work): </strong>
                          <span><?php echo $fetch_member['phone']; ?></span>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="info text-sm font-style">
                          <strong class="font-bold">Phone (overseas): </strong>
                          <span><?php echo $fetch_member['overseas_ph']; ?></span>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="info text-sm font-style">
                          <strong class="font-bold">Phone (other): </strong>
                          <span><?php echo $fetch_member['other_ph']; ?></span>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="info text-sm font-style">
                          <strong class="font-bold">Business Email :</strong>
                          <span>
                            <a href="mailto:<?php echo $fetch_member['sec_email']; ?>" target="_blank">
                              <?php echo $fetch_member['sec_email']; ?>
                            </a>
                          </span>
                        </div>
                      </div>


                      <div class="col-md-6">
                        <div class="info text-sm">
                          <strong class="font-bold">File :</strong>
                          <span><?php echo $fetch_member['file']; ?></span>
                        </div>
                      </div>



                      <div class="col-md-6">
                        <div class="info text-sm">
                          <strong class="font-bold">DBA :</strong>
                          <span><?php echo $fetch_member['dba']; ?></span>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="info text-sm">
                          <strong class="font-bold">EIN :</strong>
                          <span><?php echo $fetch_member['ein']; ?></span>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="info text-sm">
                          <strong class="font-bold">PMB :</strong>
                          <span><?php echo $fetch_member['tf']; ?></span>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="info text-sm">
                          <strong class="font-bold">Client Type :</strong>
                          <span><?php $mtype = $fetch_member['type'];
                          $querr = mysqli_query($con, "SELECT * FROM member_type where type_id='$mtype'");
                          while ($genetics = mysqli_fetch_array($querr)) {
                            echo $genetics['type_name'];
                          } ?></span>
                        </div>

                        <div class="info text-sm font-style">
                          <strong class="font-bold">category: </strong>
                          <span><?php echo $fetch_member['category']; ?></span>
                        </div>

                        <div class="info text-sm font-style">
                          <strong class="font-bold">County: </strong>
                          <span><?php echo $fetch_member['bcounty']; ?></span>
                        </div>
                      </div>
                      <div class="col-md-12">
                        <div class="info text-sm">
                          <strong class="font-bold">Business Name :</strong>
                          <span><?php echo $fetch_member['bus_name']; ?></span>
                        </div>
                      </div>
                      <div class="col-md-12">
                        <strong class="font-bold">Business Address :</strong>
                        <span><?php echo $fetch_member['address']; ?></span>
                      </div>
                      <div class="col-md-12">
                        <strong class="font-bold">Business City :</strong>
                        <span><?php echo $fetch_member['city']; ?></span>
                      </div>
                      <div class="col-md-12">
                        <strong class="font-bold">Business State :</strong>
                        <span><?php echo $fetch_member['state']; ?></span>
                      </div>
                      <div class="col-md-12">
                        <strong class="font-bold">Business Zip :</strong>
                        <span><?php echo $fetch_member['zip']; ?></span>
                      </div>
                    </div>
                    <div class="col-md-12">
                      <strong class="font-bold">Business Start date:</strong>
                      <span><?php echo date("m/d/Y", strtotime($fetch_member['bus_start_date'])); ?></span>
                    </div>

                    <div class="col-md-12">
                      <strong class="font-bold">Incorporation Date:</strong>
                      <span><?php echo date("m/d/Y", strtotime($fetch_member['incorp_date'])); ?></span>
                    </div>
                  </div>
                </div>
              </div>
            <?php } ?>
          </div>

          <!-- Associated Products -->
          <div class="row" id="associated-products">
            <div class="col-sm-12 col-md-12">
              <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row">
                    <div class="col-md-10">
                      <h5>Associated Products</h5>
                    </div>
                    <div class="col-md-2">
                      <button class="btn btn-primary" data-toggle="modal" data-target="#associatedProducts">
                        Add Proucts
                      </button>
                    </div>
                  </div>
                  <hr>
                  <div class="row">
                    <div class="dataTable-container" style="max-height: 300px; overflow-y: auto;">
                      <table class="table datatable">
                        <thead>
                          <tr>
                            <th>Product</th>
                            <th>Renewal Cycle</th>
                            <th>Start Date</th>
                            <th>Next Renewal Date</th>
                            <th>Inserted By</th>
                            <th>Action</th>
                          </tr>
                        </thead>
                        <tbody>

                          <!-- 1.  ORIGINAL orders (newest first) ---------------->
                          <?php
                          $orig = mysqli_query($con, "
                  SELECT o.*, u.user_login AS inserted_by
                  FROM   orders o
                  LEFT JOIN users u ON o.user_id = u.user_id
                  WHERE  o.mem_id = $client_id
                  ORDER  BY o.order_id DESC");

                          while ($row = mysqli_fetch_assoc($orig)):
                            $prodName = mysqli_fetch_assoc(mysqli_query($con, "
                           SELECT membership_name
                           FROM   memberships
                           WHERE  membership_id = {$row['prod_id']}"))['membership_name'] ?? 'N/A';
                            ?>
                            <tr style="color:#fff;background:<?= $row['order_status'] > 0 ? '#4cbb4c' : '#c55757' ?>">
                              <td><?= htmlspecialchars($prodName) ?></td>
                              <td><?= (int) $row['bil_cyc_months'] ?> days</td>
                              <td><?= date('D d M, Y', strtotime($row['start_date'])) ?></td>
                              <td>
                                <?php if ($row['order_status'] == -1): ?>
                                  Order Completed
                                <?php else: ?>
                                  <?= date('D d M, Y', strtotime($row['exp_date'])) ?>
                                <?php endif; ?>
                              </td>
                              <td><?= htmlspecialchars($row['inserted_by']) ?></td>
                              <td>
                                <form method="POST">
                                  <input type="hidden" name="orderid" value="<?= $row['order_id'] ?>">
                                  <button type="button" class="btn btn-warning btn-sm"
                                    onclick="confirmDelete(<?= $row['order_id'] ?>)">Delete</button>
                                </form>
                              </td>
                            </tr>
                          <?php endwhile; ?>


                          <!-- 2.  AUTO-RENEWED rows (mirror table) ------------->
                          <?php
                          // ORIGINAL – used to print rows
                          $renew = mysqli_query($con, "
                           SELECT  oar.*,
                                   m.membership_name,
                                   u.user_login AS inserted_by
                           FROM    orders_auto_renewed oar
                           LEFT JOIN memberships m ON m.membership_id = oar.prod_id
                           LEFT JOIN users u ON u.user_id = oar.user_id
                           WHERE   oar.mem_id = $client_id
                           ORDER BY oar.auto_id DESC");

                          // NEW – just for the toast
                          $toastQuery = mysqli_query($con, "
                           SELECT DISTINCT m.membership_name
                           FROM orders_auto_renewed oar
                           LEFT JOIN memberships m ON m.membership_id = oar.prod_id
                           WHERE oar.mem_id = $client_id
                          ");


                          $autoRenewedProducts = [];
                          while ($t = mysqli_fetch_assoc($toastQuery)) {
                            $autoRenewedProducts[] = $t['membership_name'];
                          }
                          $hasAutoRenewed = !empty($autoRenewedProducts);

                          $counter = 0;
                          while ($row = mysqli_fetch_assoc($renew)):
                            $counter++;
                            // Determine ordinal suffix
                            $suffix = 'th'; // Default suffix
                            if ($counter % 100 >= 11 && $counter % 100 <= 13) {
                              $suffix = 'th'; // Special cases: 11th, 12th, 13th
                            } elseif ($counter % 10 == 1) {
                              $suffix = 'st'; // 1st, 21st, etc.
                            } elseif ($counter % 10 == 2) {
                              $suffix = 'nd'; // 2nd, 22nd, etc.
                            } elseif ($counter % 10 == 3) {
                              $suffix = 'rd'; // 3rd, 23rd, etc.
                            }
                            ?>
                          <tr style="color:#fff;background:#17a2b8">
                            <td>
                              <?= htmlspecialchars($row['membership_name']) ?>
                              <small>(Auto-Renewed #
                                <?= $counter . $suffix ?>)
                              </small>
                            </td>
                            <td>
                              <?= (int) $row['bil_cyc_months'] ?> days
                            </td>
                            <td>
                              <?= date('D d M, Y', strtotime($row['start_date'])) ?>
                            </td>
                            <td>
                              <?= date('D d M, Y', strtotime($row['exp_date'])) ?>
                            </td>
                            <td>
                              <?= htmlspecialchars($row['inserted_by']) ?>
                            </td>
                            <td><span class="badge badge-light">Auto</span></td>
                          </tr>
                          <?php endwhile; ?>

                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>

              <!-- The Modal -->
              <div class="modal fade" id="associatedProducts">
                <div class="modal-dialog modal-lg">
                  <form method="POST">
                    <div class="modal-content">
                      <!-- Modal Header -->
                      <div class="modal-header">
                        <h4 class="modal-title">Associated Products</h4>
                      </div>

                      <!-- Modal body -->
                      <div class="modal-body">
                        <div class="row mb-2">
                          <input type="hidden" name="member_id" value="<?php echo $client_id; ?>" />
                          <input type="hidden" name="member_name" value="<? echo $member_name; ?>" />
                          <input type="hidden" name="user_id" value="<?php echo $userid; ?>" />
                          <div class="col-md-6">
                            <label for="product" class="form-label">Product</label>
                            <select class="form-control select" required="required" id="product" name="prod_id"
                              required>
                              <option value="" selected="selected">Select Any Option</option>
                              <?php
                              $querr = mysqli_query($con, "SELECT * FROM memberships");
                              $c = 1;
                              while ($genetics = mysqli_fetch_array($querr)) { ?>
                                <option value="<?php echo $genetics['membership_id']; ?>">
                                  <?php echo $genetics['membership_name']; ?>
                                </option>
                                <?php $c++;
                              } ?>
                            </select>
                          </div>
                          <div class="col-md-6">
                            <label for="cycle" class="form-label">Cycle (in days)</label>
                            <input type="number" class="form-control" id="cycle" name="cycle_id" readonly
                              placeholder="Select date range (days calculated automatically)">
                          </div>

                          <div class="col-md-12 mt-2" id="productslist"></div>
                          <div class="col-md-12 mt-2">
                            <label for="startdate" class="form-label">Start Date</label>
                            <input class="form-control" required="required" name="startdate" type="date" id="start_date"
                              onchange="calculateExpiryDate()">
                          </div>
                          <div class="col-md-12 mt-2">
                            <label for="expirydate" class="form-label">Expiry Date</label>
                            <input class="form-control" required="required" name="expirydate" type="date"
                              id="expiry_date">
                          </div>
                        </div>

                        <?php if ($user_type == '1' || $user_type == '2') { ?>
                          <div class="row">
                            <div class="col-md-12" style="text-align: left;">
                              <label for="unit_price" class="form-label"><b>Assign Task:</b></label>
                              <select class="form-control select" required="required" id="assigned_users"
                                name="assigned_users" required>
                                <option value="" selected="selected">Select Any User</option>
                                <?php
                                $querr = mysqli_query($con, "SELECT * FROM users");
                                $c = 1;
                                while ($cycles = mysqli_fetch_array($querr)) { ?>
                                  <option value="<?php echo $cycles["user_id"]; ?>">
                                    <?php echo $cycles["user_name"]; ?>
                                  </option>
                                  <?php $c++;
                                } ?>
                              </select>
                            </div>
                          </div>
                        <?php } ?>
                      </div>

                      <!-- Modal footer -->
                      <div class="modal-footer">
                        <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" name="addassociatedproduct"
                          id="addassociatedproduct">Submit</button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>

          <script>
            // Add required JavaScript functions
            function calculateExpiryDate() {
              const startDate = new Date(document.getElementById('start_date').value);
              const cycleDays = parseInt(document.getElementById('cycle').value) || 0;

              if (!isNaN(startDate.getTime()) && cycleDays > 0) {
                const expiryDate = new Date(startDate);
                expiryDate.setDate(expiryDate.getDate() + cycleDays);
                document.getElementById('expiry_date').value = expiryDate.toISOString().split('T')[0];
              }
            }
            /* Live calculation of days between start & expiry */
            ['start_date', 'expiry_date'].forEach(id => {
              document.getElementById(id).addEventListener('change', () => {
                const startEl = document.getElementById('start_date');
                const expiryEl = document.getElementById('expiry_date');
                const cycleEl = document.getElementById('cycle');

                if (startEl.value && expiryEl.value) {
                  const msPerDay = 1000 * 60 * 60 * 24;
                  const diff = Math.round(
                    (new Date(expiryEl.value) - new Date(startEl.value)) / msPerDay
                  );
                  cycleEl.value = diff;
                }
              });
            });
            function mydiscountrow() {
              const discountRow = document.getElementById('discountrow');
              discountRow.style.display = document.getElementById('dissbox').checked ? 'block' : 'none';
            }

            function getdiscount() {
              const subtotal = parseFloat(document.getElementById('sub_total').value) || 0;
              const discount = parseFloat(document.getElementById('discount').value) || 0;
              document.getElementById('grand_total').value = (subtotal - discount).toFixed(2);
            }

            function changetotal() {
              const subtotal = parseFloat(document.getElementById('sub_total').value) || 0;
              const discount = parseFloat(document.getElementById('discount').value) || 0;
              document.getElementById('grand_total').value = (subtotal - discount).toFixed(2);
            }


          </script>


          <!-- Comments -->


          <div class="row" id="comments">
            <div class="col-sm-12 col-md-6">
              <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row">
                    <div class="col-md-7">
                      <h5>Comments</h5>
                    </div>
                    <div class="col-md-5 text-right">
                      <button class="btn btn-primary" data-toggle="modal" data-target="#addcomment">Add Comment</button>
                      <button class="btn btn-primary" onclick="printComments()">Print</button>
                    </div>
                  </div>
                  <hr>

                  <!-- ✅ Added responsive wrapper -->
                  <div class="table-responsive">
                    <table class="table datatable1">
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th>By</th>
                          <th>Detail</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $sql = "SELECT comments.*, 
                     creator.user_login AS comment_by,
                     updater.user_login AS updated_by_name
                    FROM comments 
                    LEFT JOIN users AS creator ON comments.user_id = creator.user_id 
                    LEFT JOIN users AS updater ON comments.comment_updated_by = updater.user_id 
                    WHERE member_id='$client_id' 
                    ORDER BY comment_id DESC";

                        $querr1 = mysqli_query($con, $sql);
                        while ($Comrow = mysqli_fetch_array($querr1)) {
                          $commentdate = $Comrow['comment_time'];
                          $comment_by = isset($Comrow['comment_by']) ? $Comrow['comment_by'] : 'Unknown';
                          ?>
                          <tr>
                            <td>
                              <?php
                              $timestamp = strtotime($commentdate);
                              echo date("m/d/Y", $timestamp) . "<br>" . date("H:i:s", $timestamp);
                              ?>
                            </td>
                            <td><?php echo htmlspecialchars($comment_by); ?></td>
                            <td style="max-width: 200px; white-space: normal; word-wrap: break-word;">
                              <div style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; 
                              overflow: hidden; text-overflow: ellipsis; line-height: 1.5em;">
                                <?php echo strip_tags($Comrow["comment_detail"], '<p><br><b><i><strong><em>'); ?>
                              </div>
                            </td>
                            <td>
                              <button class="btn btn-sm btn-success" onclick="openViewModal(
                    '<?php echo htmlspecialchars(json_encode($comment_by), ENT_QUOTES, 'UTF-8'); ?>',
                    '<?php echo htmlspecialchars(json_encode($Comrow['updated_by_name']), ENT_QUOTES, 'UTF-8'); ?>',
                    '<?php echo htmlspecialchars(json_encode($commentdate), ENT_QUOTES, 'UTF-8'); ?>',
                    '<?php echo htmlspecialchars(json_encode(strip_tags($Comrow['comment_detail'], '<p><br><b><i><strong><em>')), ENT_QUOTES, 'UTF-8'); ?>'
                  )">View</button>
                              <button class="btn btn-sm btn-warning" onclick="openEditModal(
                    <?php echo $Comrow['comment_id']; ?>,
                    `<?php echo addslashes(strip_tags($Comrow['comment_detail'])); ?>`
                  )">Edit</button>
                              <button class="btn btn-sm btn-danger" onclick="openDeleteModal(
                    <?php echo $Comrow['comment_id']; ?>
                  )">Delete</button>
                            </td>
                          </tr>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                  <!-- ✅ End responsive wrapper -->

                </div>
              </div>
            </div>


            <!-- View Comment Modal -->
            <div class="modal fade" id="viewCommentModal">
              <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content shadow rounded-lg">
                  <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Comment Details</h5>
                  </div>
                  <div class="modal-body px-4 pt-3 pb-0" id="viewCommentBody"></div>
                  <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Edit Comment Modal -->
            <div class="modal fade" id="editCommentModal">
              <div class="modal-dialog modal-lg">
                <form method="POST">
                  <div class="modal-content">
                    <div class="modal-header" style="background-color: #6FD943; color: white;">
                      <h5 class="modal-title">Edit Comment</h5>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" name="edit_comment_id" id="edit_comment_id">
                      <div class="form-group">
                        <label>Comment:</label>
                        <textarea name="comment_detail" id="edit_comment_detail" class="form-control" rows="5"
                          required></textarea>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" name="edit_comment" class="btn"
                        style="background-color: #6FD943; color: white;">Update</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div class="modal fade" id="deleteCommentModal">
              <div class="modal-dialog modal-md">
                <form method="POST">
                  <div class="modal-content">
                    <div class="modal-body">
                      <input type="hidden" name="id" id="delete_comment_id">
                      <input name="tblname" type="hidden" value="comments">
                      <input name="colname" type="hidden" value="comment_id">
                      <div class="swal2-icon swal2-warning swal2-icon-show" style="display: flex;">
                        <div class="swal2-icon-content">!</div>
                      </div>
                      <h2 class="swal2-title">Are you sure?</h2>
                      <div class="swal2-html-container">This action cannot be undone. Do you want to continue?</div>
                    </div>
                    <div class="modal-footer" style="justify-content: center;">
                      <button type="button" class="btn btn-info" data-bs-dismiss="modal">Close</button>
                      <button type="submit" class="btn btn-primary" name="delete">Yes Delete</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>

            <script>
              function openViewModal(by, updatedBy, date, detail) {
                const body = `
    <div class="mb-3">
      <strong>Originally Created By:</strong>
      <div class="text-muted">${by}</div>
    </div>
    <div class="mb-3">
      <strong>Last Updated By:</strong>
      <div class="text-muted">${updatedBy}</div>
    </div>
    <div class="mb-3">
      <strong>Last Updated:</strong>
      <div class="text-muted">${date}</div>
    </div>
    <div class="border rounded p-3 bg-light" style="white-space: normal;">
      ${detail}
    </div>`;
                document.getElementById('viewCommentBody').innerHTML = body;
                $('#viewCommentModal').modal('show');
              }

              function openEditModal(id, detail) {
                document.getElementById('edit_comment_id').value = id;
                document.getElementById('edit_comment_detail').value = detail;
                $('#editCommentModal').modal('show');
              }

              function openDeleteModal(id) {
                document.getElementById('delete_comment_id').value = id;
                $('#deleteCommentModal').modal('show');
              }
            </script>


            <!-- Printable Comments Hidden Div -->
            <div id="printableComments" style="display: none;">
              <h2>All Comments</h2>
              <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                <thead>
                  <tr>
                    <th>#</th> <!-- Added counter column -->
                    <th>Date</th>
                    <th>By</th>
                    <th>Detail</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  mysqli_data_seek($querr1, 0); // reset pointer to reuse query result
                  $count = 1; // Initialize counter
                  while ($Comrow = mysqli_fetch_array($querr1)) {
                    $commentdate = $Comrow['comment_time'];
                    $comment_by = isset($Comrow['comment_by']) ? $Comrow['comment_by'] : 'Unknown';
                    ?>
                    <tr>
                      <td><?php echo $count++; ?></td> <!-- Print counter -->
                      <td>
                        <?php
                        $timestamp = strtotime($commentdate);
                        echo date("m/d/Y", $timestamp) . " " . date("H:i:s", $timestamp);
                        ?>
                      </td>
                      <td><?php echo htmlspecialchars($comment_by); ?></td>
                      <td><?php echo strip_tags($Comrow["comment_detail"], '<p><br><b><i><strong><em>'); ?></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>

            <script>
              function printComments() {
                var printContents = document.getElementById('printableComments').innerHTML;
                var printWindow = window.open('', '', 'height=600,width=800');
                printWindow.document.write('<html><head><title>All Comments</title></head><body>');
                printWindow.document.write('<button onclick="window.print()" style="position: fixed; top: 20px; right: 20px; padding: 10px 20px; background-color: #007BFF; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">🖨️ Print</button>');
                printWindow.document.write(printContents);
                printWindow.document.write('</body></html>');
                printWindow.document.close();
              }
            </script>


            <div class="col-sm-12 col-md-6">
              <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row">
                    <div class="col-md-7">
                      <h5>Invoices Comments</h5>
                    </div>
                    <div class="col-md-5 text-right">
                      <button class="btn btn-primary" data-toggle="modal" data-target="#addNewcomment">Add
                        Comment</button>
                      <button class="btn btn-primary" onclick="printNewComments()">Print</button>
                    </div>
                  </div>
                  <hr>
                  <div class="row">
                    <div class="dataTable-container">
                      <table class="table datatable1">
                        <thead>
                          <tr>
                            <th>Invoice ID</th>
                            <th>Date</th>
                            <th>By</th>
                            <th>Detail</th>
                            <th>Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          $query1 = mysqli_query($con, "SELECT * FROM new_comments WHERE member_id='$client_id' ORDER BY comment_id DESC");
                          while ($comment_row = mysqli_fetch_array($query1)) {
                            $comment_id = $comment_row['comment_id'];
                            $commentdate = $comment_row['comment_time'];
                            $comment_detail = $comment_row["comment_detail"];
                            $STAFF = $comment_row["user_id"];
                            $updated_by = $comment_row["comment_updated_by"];

                            $STAFF_query = mysqli_query($con, "SELECT * FROM users where user_id=$STAFF");
                            $STAFF_row = mysqli_fetch_array($STAFF_query);
                            $comment_by = $STAFF_row["user_login"];

                            $UPDATED_query = mysqli_query($con, "SELECT * FROM users where user_id=$updated_by");
                            $UPDATED_row = mysqli_fetch_array($UPDATED_query);
                            $updated_by_name = $UPDATED_row["user_login"];
                            ?>
                            <tr>
                              <td>
                                <?php echo (($comment_row['invoice_id'] ?? 0) == 0) ? 'N/A' : htmlspecialchars($comment_row['invoice_id']); ?>
                              </td>

                              <td>
                                <?php
                                $timestamp = strtotime($commentdate);
                                echo date("m/d/Y", $timestamp) . "<br>" . date("H:i:s", $timestamp);
                                ?>
                              </td>
                              <td><?php echo htmlspecialchars($comment_by); ?></td>
                              <td style="max-width: 200px; white-space: normal; word-wrap: break-word;">
                                <div
                                  style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.5em;">
                                  <?php echo strip_tags($comment_detail, '<p><br><b><i><strong><em>'); ?>
                                </div>
                              </td>
                              <td>
                                <button class="btn btn-sm btn-success" onclick="openNewViewModal(
    '<?php echo htmlspecialchars(json_encode($comment_by), ENT_QUOTES, 'UTF-8'); ?>',
    '<?php echo htmlspecialchars(json_encode($updated_by_name), ENT_QUOTES, 'UTF-8'); ?>',
    '<?php echo htmlspecialchars(json_encode($commentdate), ENT_QUOTES, 'UTF-8'); ?>',
    '<?php echo htmlspecialchars(json_encode(strip_tags($comment_detail, '<p><br><b><i><strong><em>')), ENT_QUOTES, 'UTF-8'); ?>'
  )">View</button>
                                <button class="btn btn-sm btn-warning" onclick="openNewEditModal(
    <?php echo $comment_row['comment_id']; ?>,
    `<?php echo addslashes(strip_tags($comment_row['comment_detail'])); ?>`
  )">Edit</button>
                                <button class="btn btn-sm btn-danger" onclick="openNewDeleteModal(
    <?php echo $comment_row['comment_id']; ?>
  )">Delete</button>
                              </td>
                            </tr>
                          <?php } ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>



            <!-- View New Comment Modal -->
            <div class="modal fade" id="viewNewCommentModal">
              <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content shadow rounded-lg">
                  <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Comment Details</h5>
                  </div>
                  <div class="modal-body px-4 pt-3 pb-0" id="viewNewCommentBody"></div>
                  <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Edit New Comment Modal -->
            <div class="modal fade" id="editNewCommentModal">
              <div class="modal-dialog modal-lg">
                <form method="POST">
                  <div class="modal-content">
                    <div class="modal-header" style="background-color: #6FD943; color: white;">
                      <h5 class="modal-title">Edit Comment</h5>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" name="comment_id" id="edit_new_comment_id">
                      <div class="form-group">
                        <label>Comment:</label>
                        <textarea name="comment_detail" id="edit_new_comment_detail" class="form-control" rows="5"
                          required></textarea>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      <button type="submit" name="update_New_comment" class="btn"
                        style="background-color: #6FD943; color: white;">Update</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>

            <!-- Delete New Confirmation Modal -->
            <div class="modal fade" id="deleteNewCommentModal">
              <div class="modal-dialog modal-md">
                <form method="POST">
                  <div class="modal-content">
                    <div class="modal-body">
                      <input type="hidden" name="comment_id" id="delete_new_comment_id">
                      <div class="swal2-icon swal2-warning swal2-icon-show" style="display: flex;">
                        <div class="swal2-icon-content">!</div>
                      </div>
                      <h2 class="swal2-title">Are you sure?</h2>
                      <div class="swal2-html-container">This action cannot be undone. Do you want to continue?</div>
                    </div>
                    <div class="modal-footer" style="justify-content: center;">
                      <button type="button" class="btn btn-info" data-bs-dismiss="modal">Close</button>
                      <button type="submit" class="btn btn-primary" name="delete_New_comment">Yes Delete</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>

            <script>
              // New functions for invoices comments
              function openNewViewModal(by, updatedBy, date, detail) {
                const body = `
    <div class="mb-3">
      <strong>Originally Created By:</strong>
      <div class="text-muted">${by}</div>
    </div>
    <div class="mb-3">
      <strong>Last Updated By:</strong>
      <div class="text-muted">${updatedBy}</div>
    </div>
    <div class="mb-3">
      <strong>Last Updated:</strong>
      <div class="text-muted">${date}</div>
    </div>
    <div class="border rounded p-3 bg-light" style="white-space: normal;">
      ${detail}
    </div>`;
                document.getElementById('viewNewCommentBody').innerHTML = body;
                $('#viewNewCommentModal').modal('show');
              }

              function openNewEditModal(id, detail) {
                document.getElementById('edit_new_comment_id').value = id;
                document.getElementById('edit_new_comment_detail').value = detail;
                $('#editNewCommentModal').modal('show');
              }

              function openNewDeleteModal(id) {
                document.getElementById('delete_new_comment_id').value = id;
                $('#deleteNewCommentModal').modal('show');
              }
            </script>

            <!-- Printable Comments Hidden Div -->
            <div id="printableNewComments" style="display: none;">
              <h2>All Comments</h2>
              <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>By</th>
                    <th>Detail</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  mysqli_data_seek($query1, 0); // reset pointer to reuse query result
                  $count = 1; // Initialize counter
                  while ($comment_row = mysqli_fetch_array($query1)) {
                    $commentdate = $comment_row['comment_time'];
                    $comment_detail = $comment_row['comment_detail'];
                    $STAFF = $comment_row['user_id'];

                    // Fetch user information for comment_by
                    $STAFF_query = mysqli_query($con, "SELECT user_login FROM users WHERE user_id=$STAFF");
                    $STAFF_row = mysqli_fetch_array($STAFF_query);
                    $comment_by = isset($STAFF_row['user_login']) ? $STAFF_row['user_login'] : 'Unknown';
                    ?>
                    <tr>
                      <td><?php echo $count++; ?></td>
                      <td><?php echo date('m/d/Y H:i:s', strtotime($commentdate)); ?></td>
                      <td><?php echo htmlspecialchars($comment_by); ?></td>
                      <td><?php echo strip_tags($comment_detail, '<p><br><b><i><strong><em>'); ?></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>

            <script>
              function printNewComments() {
                var printContents = document.getElementById('printableNewComments').innerHTML;
                var printWindow = window.open('', '', 'height=600,width=800');
                printWindow.document.write('<html><head><title>All Comments</title></head><body>');
                printWindow.document.write('<button onclick="window.print()" style="position: fixed; top: 20px; right: 20px; padding: 10px 20px; background-color: #007BFF; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">🖨️ Print</button>');
                printWindow.document.write(printContents);
                printWindow.document.write('</body></html>');
                printWindow.document.close();
              }
            </script>

            <!-- ADD FAMILY MEMBER -->

            <div class="col-sm-12 col-md-12" id="family-members">
              <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row">
                    <div class="col-md-10">
                      <h5>ADD FAMILY MEMBER</h5>
                    </div>
                    <div class="col-md-2">
                      <button class="btn btn-primary" data-toggle="modal" data-target="#addSpouse">Add Family</button>
                    </div>
                  </div>
                  <hr>
                  <div class="row">
                    <div class="dataTable-container">
                      <table class="table datatable2">
                        <thead>
                          <tr>
                            <th>Name</th>
                            <th>SSN</th>
                            <th>Address</th>
                            <th>Relation</th>
                            <th>Phone</th>
                            <th style="width: 20%;">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          $querr2 = mysqli_query($con, "SELECT * FROM spouse WHERE member_id='$client_id' ORDER BY spouse_id DESC");
                          while ($spousedata = mysqli_fetch_array($querr2)) {
                            ?>
                            <tr>
                              <td><?php echo htmlspecialchars($spousedata['sp_name']); ?></td>
                              <td><?php echo htmlspecialchars($spousedata['sp_ss']); ?></td>
                              <td><?php echo htmlspecialchars($spousedata['sp_street']); ?></td>
                              <td><?php echo htmlspecialchars($spousedata['sp_relation']); ?></td>
                              <td><?php echo htmlspecialchars($spousedata['sp_phone']); ?></td>
                              <td>
                                <button class="btn btn-sm btn-info" data-toggle="modal"
                                  data-target="#viewSpouse<?php echo $spousedata['spouse_id']; ?>">
                                  <i class="ti ti-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" data-toggle="modal"
                                  data-target="#editSpouse<?php echo $spousedata['spouse_id']; ?>">
                                  <i class="ti ti-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" data-toggle="modal"
                                  data-target="#deletec<?php echo $spousedata['spouse_id']; ?>">
                                  <i class="ti ti-trash"></i>
                                </button>
                              </td>
                            </tr>

                            <!-- View Modal -->
                            <div class="modal fade" id="viewSpouse<?php echo $spousedata['spouse_id']; ?>">
                              <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content">
                                  <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title mb-0">View Family Member</h5>
                                  </div>
                                  <div class="modal-body">
                                    <div class="container-fluid">
                                      <?php
                                      $fields = [
                                        'Name' => $spousedata['sp_name'],
                                        'DOB' => $spousedata['sp_dob'],
                                        'SSN' => $spousedata['sp_ss'],
                                        'Address' => $spousedata['sp_street'] . ', ' . $spousedata['sp_city'],
                                        'Relation' => $spousedata['sp_relation'],
                                        'Phone' => $spousedata['sp_phone'],
                                        'Email' => $spousedata['sp_email'],
                                        'Occupation' => $spousedata['sp_occ'],
                                      ];
                                      foreach ($fields as $label => $value): ?>
                                        <div class="row py-2 px-3 mb-2 bg-light rounded">
                                          <div class="col-4 font-weight-bold"><?php echo htmlspecialchars($label); ?>:</div>
                                          <div class="col-8"><?php echo htmlspecialchars($value); ?></div>
                                        </div>
                                      <?php endforeach; ?>
                                    </div>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                  </div>
                                </div>
                              </div>
                            </div>


                            <!-- Edit Modal -->
                            <div class="modal fade" id="editSpouse<?php echo $spousedata['spouse_id']; ?>">
                              <div class="modal-dialog modal-lg">
                                <form method="POST">
                                  <div class="modal-content">
                                    <div class="modal-header">
                                      <h4 class="modal-title">Edit Family Member</h4>
                                    </div>
                                    <div class="modal-body">
                                      <input type="hidden" name="spouse_id"
                                        value="<?php echo $spousedata['spouse_id']; ?>">
                                      <div class="row">
                                        <div class="col-md-6">
                                          <label class="form-label">Name</label>
                                          <input type="text" class="form-control" name="spousename"
                                            value="<?php echo htmlspecialchars($spousedata['sp_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">DOB</label>
                                          <input type="date" class="form-control" name="spousedob"
                                            value="<?php echo htmlspecialchars($spousedata['sp_dob']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">SSN</label>
                                          <input type="text" class="form-control" name="spousess"
                                            value="<?php echo htmlspecialchars($spousedata['sp_ss']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">Street Address</label>
                                          <input type="text" class="form-control" name="spousestreetaddress"
                                            value="<?php echo htmlspecialchars($spousedata['sp_street']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">City</label>
                                          <input type="text" class="form-control" name="spousecity"
                                            value="<?php echo htmlspecialchars($spousedata['sp_city']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">Occupation</label>
                                          <input type="text" class="form-control" name="spouseoccupation"
                                            value="<?php echo htmlspecialchars($spousedata['sp_occ']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">Phone</label>
                                          <input type="tel" class="form-control" name="spousephone"
                                            value="<?php echo htmlspecialchars($spousedata['sp_phone']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">Email Address</label>
                                          <input type="email" class="form-control" name="spouseemailaddress"
                                            value="<?php echo htmlspecialchars($spousedata['sp_email']); ?>" required>
                                        </div>
                                      </div>
                                    </div>
                                    <div class="modal-footer">
                                      <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                                      <button type="submit" class="btn btn-primary" name="updatespouse">Save
                                        Changes</button>
                                    </div>
                                  </div>
                                </form>
                              </div>
                            </div>

                            <!-- Delete Modal (already present in your original) -->
                            <div class="modal fade" id="deletec<?php echo $spousedata['spouse_id']; ?>">
                              <div class="modal-dialog modal-md">
                                <form method="POST">
                                  <div class="modal-content">
                                    <div class="modal-body">
                                      <div class="row mb-2">
                                        <div class="col-md-12 mt-2">
                                          <input name="id" type="hidden" value="<?php echo $spousedata['spouse_id']; ?>">
                                          <input name="tblname" type="hidden" value="spouse">
                                          <input name="colname" type="hidden" value="spouse_id">
                                          <div class="swal2-icon swal2-warning swal2-icon-show" style="display: flex;">
                                            <div class="swal2-icon-content">!</div>
                                          </div>
                                          <h2 class="swal2-title" id="swal2-title" style="display: block;">Are you sure?
                                          </h2>
                                          <div class="swal2-html-container" id="swal2-html-container"
                                            style="display: block;">This action cannot be undone. Do you want to continue?
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                    <div class="modal-footer" style="justify-content: center;">
                                      <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                                      <button type="submit" class="btn btn-primary" name="delete">Yes Delete</button>
                                    </div>
                                  </div>
                                </form>
                              </div>
                            </div>
                          <?php } ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>


          </div>

          <!-- ======  Q U O T A T I O N S   S E C T I O N  ====== -->
          <div class="row" id="quotations">
            <div class="col-sm-12 col-md-12">
              <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row align-items-center">
                    <div class="col-md-10">
                      <h5>Quotations</h5>
                    </div>
                    <div class="col-md-2 text-end">
                      <!-- <a href="add_quotation.php?member_id=<?php echo $client_id; ?>" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus"></i> New Quotation
                      </a> -->
                    </div>
                  </div>
                  <hr>

                  <div class="dataTable-container">
                    <table class="table datatable1" id="tblQuotations" style="width:100%;min-width:900px;">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Quotation&nbsp;SR</th>
                          <th>Date</th>
                          <th>Services</th>
                          <th>Sub-Total</th>
                          <th>Discount</th>
                          <th>Grand&nbsp;Total</th>
                          <th>Status</th>
                          <th>Created&nbsp;By</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $qQuot = mysqli_query(
                          $con,
                          "SELECT q.*, u.user_login AS creator
               FROM quotations q
               LEFT JOIN users u ON u.user_id = q.created_by
               WHERE q.member_id = '$client_id'
               ORDER BY q.quotation_id DESC"
                        );

                        if (mysqli_num_rows($qQuot) == 0): ?>
                          <tr>
                            <td colspan="10" class="text-center">No quotations found for this member.</td>
                          </tr>
                        <?php else:
                          $idx = 0;
                          while ($row = mysqli_fetch_assoc($qQuot)):
                            $idx++;
                            switch ($row['status']) {
                              case 'accepted':
                                $badge = 'bg-success';
                                break;
                              case 'rejected':
                                $badge = 'bg-danger';
                                break;
                              case 'expired':
                                $badge = 'bg-secondary';
                                break;
                              default:
                                $badge = 'bg-warning';
                                break;
                            }
                            ?>
                            <tr>
                              <td><?php echo $idx; ?></td>
                              <td><?php echo htmlspecialchars($row['quotation_sr']); ?></td>
                              <td><?php echo date('m/d/Y', strtotime($row['quotation_date'])); ?></td>
                              <td style="max-width:250px;white-space:normal;">
                                <?php
                                // grab only the text that appears before the first “<br” in every <td> of each <tr>
                                preg_match_all('/<td>(.*?)<br/i', $row['services'], $m);
                                $names = array_map('strip_tags', $m[1] ?? []);
                                $text = htmlspecialchars(implode(', ', $names));
                                echo substr($text, 0, 90) . (strlen($text) > 90 ? '…' : '');
                                ?>
                              </td>
                              <td><?php echo number_format($row['sub_total'], 2); ?></td>
                              <td><?php echo number_format($row['discount'], 2); ?></td>
                              <td><strong><?php echo number_format($row['grand_total'], 2); ?></strong></td>
                              <td><span class="badge <?php echo $badge; ?>"><?php echo ucfirst($row['status']); ?></span>
                              </td>
                              <td><?php echo htmlspecialchars($row['creator']); ?></td>
                              <td class="d-flex gap-1">
                                <a href="quotations/<?php echo $row['file_name']; ?>" target="_blank">View File</a>
                                <!-- <a href="edit_quotation.php?id=<?php echo $row['quotation_id']; ?>"
                                  class="btn btn-sm btn-warning" title="Edit">
                                  <i class="ti ti-pencil"></i>
                                </a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this quotation?');">
                                  <input type="hidden" name="id" value="<?php echo $row['quotation_id']; ?>">
                                  <input type="hidden" name="tblname" value="quotations">
                                  <input type="hidden" name="colname" value="quotation_id">
                                  <button type="submit" name="delete" class="btn btn-sm btn-danger">
                                    <i class="ti ti-trash"></i>
                                  </button>
                                </form> -->
                              </td>
                            </tr>
                          <?php endwhile; endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- =============  E N D   Q U O T A T I O N S  ============= -->

          <!-- Member Services -->
          <div class="row" id="member-services">
            <div class="col">
              <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row">
                    <div class="col-md-8">
                      <h5>Member Services</h5>
                    </div>
                    <div class="col-md-4 text-end">
                      <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#modalAddService">Add
                        Service</button>
                    </div>
                  </div>
                  <hr>

                  <!-- NO forced height / scroll -->
                  <table class="table table-sm datatable1">
                    <thead>
                      <tr>
                        <th>Service</th>
                        <th>Description</th>
                        <th>Added By</th>
                        <th>Date</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $svc = mysqli_query($con, "
                SELECT s.id, m.membership_name, u.user_login, s.added_at,s.description
                FROM member_services s
                JOIN memberships m ON s.membership_id=m.membership_id
                JOIN users u ON s.user_id=u.user_id
                WHERE s.member_id=$client_id
                ORDER BY s.added_at DESC");
                      while ($r = mysqli_fetch_assoc($svc)):
                        $sid = $r['id']; ?>
                        <tr>
                          <td><?= htmlspecialchars($r['membership_name']) ?></td>
                          <td><small><?= htmlspecialchars($r['description'] ?? '') ?></small></td>
                          <td><?= htmlspecialchars($r['user_login']) ?></td>
                          <td><?= date('m/d/Y', strtotime($r['added_at'])) ?></td>
                          <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal"
                              data-bs-target="#modalEditService<?= $sid ?>">Edit</button>
                            <form method="post" class="d-inline">
                              <input type="hidden" name="service_id" value="<?= $sid ?>">
                              <button type="submit" name="delete_member_service" class="btn btn-sm btn-danger"
                                onclick="return confirm('Remove this service?')">Del</button>
                            </form>
                          </td>
                        </tr>

                        <!-- edit modal per row -->
                        <div class="modal fade" id="modalEditService<?= $sid ?>" tabindex="-1">
                          <div class="modal-dialog">
                            <form method="post">
                              <div class="modal-content">
                                <div class="modal-header">
                                  <h5 class="modal-title">Edit Service</h5>
                                </div>
                                <div class="modal-body">
                                  <input type="hidden" name="service_id" value="<?= $sid ?>">
                                  <label class="form-label">Change Service</label>
                                  <select name="membership_id" class="form-control" required>
                                    <?php
                                    $current = mysqli_fetch_assoc(mysqli_query($con, "
                                SELECT membership_id FROM member_services WHERE id=$sid"))['membership_id'];
                                    $list = mysqli_query($con, "SELECT * FROM memberships ORDER BY membership_name");
                                    while ($row = mysqli_fetch_assoc($list)):
                                      $sel = $row['membership_id'] == $current ? 'selected' : ''; ?>
                                      <option value="<?= $row['membership_id'] ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($row['membership_name']) ?>
                                      </option>
                                    <?php endwhile; ?>
                                  </select>
                                  <label class="form-label mt-3">Description</label>
                                  <textarea name="service_description" class="form-control" rows="3"
                                    placeholder="Enter service description..."><?= htmlspecialchars($r['description'] ?? '') ?></textarea>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                  <button type="submit" name="edit_member_service" class="btn btn-primary">Save</button>
                                </div>
                              </div>
                            </form>
                          </div>
                        </div>
                      <?php endwhile; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- add modal -->
          <div class="modal fade" id="modalAddService" tabindex="-1">
            <div class="modal-dialog">
              <form method="post">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Add Member Service</h5>
                  </div>
                  <div class="modal-body">
                    <select name="membership_id" class="form-control" required>
                      <option value="">-- Choose --</option>
                      <?php
                      $list = mysqli_query($con, "SELECT * FROM memberships ORDER BY membership_name");
                      while ($row = mysqli_fetch_assoc($list)):
                        echo '<option value="' . $row['membership_id'] . '">' . htmlspecialchars($row['membership_name']) . '</option>';
                      endwhile;
                      ?>
                    </select>
                    <label class="form-label mt-3">Description</label>
                    <textarea name="service_description" class="form-control" rows="3"
                      placeholder="Enter service description..."></textarea>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_member_service" class="btn btn-primary">Add</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Associated Business(s) -->

        <div class="row" id="businesses">
          <div class="col-sm-12 col-md-12">
            <div class="card">
              <div class="card-body employee-detail-body fulls-card">
                <div class="row">
                  <div class="col-md-10">
                    <h5>Associated Business(s)</h5>
                  </div>
                  <div class="col-md-2">
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addbusiness">Add Business</button>
                  </div>
                </div>
                <hr>
                <div class="row">
                  <div class="dataTable-container">
                    <table class="table datatable3">
                      <thead>
                        <tr>
                          <th>Name</th>
                          <th>Business Name</th>
                          <th>EIN</th>
                          <th>SSN</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $querr2 = mysqli_query($con, "SELECT * FROM addibusiness WHERE member_id='$client_id' ORDER BY addibusiness_id DESC");
                        while ($spousedata = mysqli_fetch_array($querr2)) {
                          ?>
                          <tr>
                            <td><?= $spousedata['president_name']; ?></td>
                            <td><?= $spousedata['bus_name']; ?></td>
                            <td><?= $spousedata['ein']; ?></td>
                            <td><?= $spousedata['ssn']; ?></td>
                            <td>
                              <button class="btn btn-sm btn-info" data-toggle="modal"
                                data-target="#viewModal<?= $spousedata['addibusiness_id']; ?>">View</button>
                              <button class="btn btn-sm btn-success" data-toggle="modal"
                                data-target="#editModal<?= $spousedata['addibusiness_id']; ?>">Edit</button>
                              <button class="btn btn-sm btn-danger" data-toggle="modal"
                                data-target="#deleteModal<?= $spousedata['addibusiness_id']; ?>"><i
                                  class="ti ti-trash text-white"></i></button>
                            </td>
                          </tr>

                          <!-- View Modal -->
                          <div class="modal fade" id="viewModal<?= $spousedata['addibusiness_id']; ?>">
                            <div class="modal-dialog modal-lg">
                              <div class="modal-content" style="background-color: #fff;">
                                <div class="modal-header" style="background-color: #6FD943; color: white;">
                                  <h5 class="modal-title">View Business Details</h5>

                                </div>
                                <div class="modal-body row">
                                  <div class="col-md-4"><strong>Business Name:</strong><br><?= $spousedata['bus_name']; ?>
                                  </div>
                                  <div class="col-md-4"><strong>President
                                      Name:</strong><br><?= $spousedata['president_name']; ?></div>
                                  <div class="col-md-4"><strong>President
                                      DOB:</strong><br><?= $spousedata['president_dob']; ?></div>
                                  <div class="col-md-4 mt-2"><strong>EIN:</strong><br><?= $spousedata['ein']; ?></div>
                                  <div class="col-md-4 mt-2"><strong>SSN:</strong><br><?= $spousedata['ssn']; ?></div>
                                  <div class="col-md-4 mt-2"><strong>Responsible
                                      Person:</strong><br><?= $spousedata['res_person']; ?></div>
                                  <div class="col-md-4 mt-2"><strong>Client
                                      Since:</strong><br><?= $spousedata['client_since']; ?></div>
                                  <div class="col-md-4 mt-2"><strong>Business
                                      Start:</strong><br><?= $spousedata['bus_start_date']; ?></div>
                                  <div class="col-md-4 mt-2"><strong>Incorporation
                                      Date:</strong><br><?= $spousedata['incorp_date']; ?></div>
                                  <div class="col-md-4 mt-2"><strong>Incorp.
                                      State:</strong><br><?= $spousedata['incorp_state']; ?></div>
                                  <div class="col-md-4 mt-2"><strong>Business
                                      Address:</strong><br><?= $spousedata['bus_street'] . ', ' . $spousedata['bus_city'] . ', ' . $spousedata['bus_state'] . ' ' . $spousedata['bus_zip']; ?>
                                  </div>
                                  <div class="col-md-4 mt-2"><strong>Mailing
                                      Address:</strong><br><?= $spousedata['mail_street'] . ', ' . $spousedata['mail_city'] . ', ' . $spousedata['mail_state'] . ' ' . $spousedata['mail_zip']; ?>
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button class="btn" style="background-color: #6FD943; color: white;"
                                    data-dismiss="modal">Close</button>
                                </div>
                              </div>
                            </div>
                          </div>



                          <!-- Edit Modal -->
                          <div class="modal fade" id="editModal<?= $spousedata['addibusiness_id']; ?>">
                            <div class="modal-dialog modal-lg">
                              <form method="POST">
                                <input type="hidden" name="business_id" value="<?= $spousedata['addibusiness_id']; ?>">
                                <div class="modal-content" style="background-color: #fff;">
                                  <div class="modal-header" style="background-color: #6FD943; color: white;">
                                    <h5 class="modal-title">Edit Business</h5>

                                  </div>

                                  <div class="modal-body row">

                                    <!-- Row 1 -->
                                    <div class="form-group col-md-6">
                                      <label>Business Name</label>
                                      <input type="text" name="bus_name" class="form-control"
                                        value="<?= $spousedata['bus_name']; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                      <label>President Name</label>
                                      <input type="text" name="president_name" class="form-control"
                                        value="<?= $spousedata['president_name']; ?>">
                                    </div>

                                    <!-- Row 2 -->
                                    <div class="form-group col-md-6">
                                      <label>President DOB</label>
                                      <input type="date" name="president_dob" class="form-control"
                                        value="<?= $spousedata['president_dob']; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                      <label>Responsible Person</label>
                                      <input type="text" name="res_person" class="form-control"
                                        value="<?= $spousedata['res_person']; ?>">
                                    </div>

                                    <!-- Row 3 -->
                                    <div class="form-group col-md-6">
                                      <label>EIN</label>
                                      <input type="text" name="ein" class="form-control"
                                        value="<?= $spousedata['ein']; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                      <label>SSN</label>
                                      <input type="text" name="ssn" class="form-control"
                                        value="<?= $spousedata['ssn']; ?>">
                                    </div>

                                    <!-- Row 4 -->
                                    <div class="form-group col-md-6">
                                      <label>Client Since</label>
                                      <input type="date" name="client_since" class="form-control"
                                        value="<?= $spousedata['client_since']; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                      <label>Business Start Date</label>
                                      <input type="date" name="bus_start_date" class="form-control"
                                        value="<?= $spousedata['bus_start_date']; ?>">
                                    </div>

                                    <!-- Row 5 -->
                                    <div class="form-group col-md-6">
                                      <label>Incorporation Date</label>
                                      <input type="date" name="incorp_date" class="form-control"
                                        value="<?= $spousedata['incorp_date']; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                      <label>Incorporation State</label>
                                      <select name="incorp_state" class="form-control select">
                                        <option value="">--Select--</option>
                                        <?php
                                        $ICq = mysqli_query($con, "SELECT * FROM states");
                                        while ($ICr = mysqli_fetch_array($ICq)) {
                                          $selected = ($ICr["state_id"] == $spousedata['incorp_state']) ? 'selected' : '';
                                          echo "<option value='{$ICr["state_id"]}' $selected>{$ICr["state"]}, {$ICr["state_code"]}</option>";
                                        }
                                        ?>
                                      </select>
                                    </div>

                                    <!-- Row 6: Business Address -->
                                    <div class="form-group col-md-4">
                                      <label>Business Street</label>
                                      <input type="text" name="bus_street" class="form-control"
                                        value="<?= $spousedata['bus_street']; ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                      <label>Business City</label>
                                      <input type="text" name="bus_city" class="form-control"
                                        value="<?= $spousedata['bus_city']; ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                      <label>Business State</label>
                                      <input type="text" name="bus_state" class="form-control"
                                        value="<?= $spousedata['bus_state']; ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                      <label>Business ZIP</label>
                                      <input type="text" name="bus_zip" class="form-control"
                                        value="<?= $spousedata['bus_zip']; ?>">
                                    </div>

                                    <!-- Row 7: Mailing Address -->
                                    <div class="form-group col-md-4">
                                      <label>Mailing Street</label>
                                      <input type="text" name="mail_street" class="form-control"
                                        value="<?= $spousedata['mail_street']; ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                      <label>Mailing City</label>
                                      <input type="text" name="mail_city" class="form-control"
                                        value="<?= $spousedata['mail_city']; ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                      <label>Mailing State</label>
                                      <input type="text" name="mail_state" class="form-control"
                                        value="<?= $spousedata['mail_state']; ?>">
                                    </div>
                                    <div class="form-group col-md-4">
                                      <label>Mailing ZIP</label>
                                      <input type="text" name="mail_zip" class="form-control"
                                        value="<?= $spousedata['mail_zip']; ?>">
                                    </div>

                                    <!-- Row 8: Primary Person Address -->
                                    <div class="form-group col-md-6">
                                      <label>Primary Person Street</label>
                                      <input type="text" name="prm_per_street" class="form-control"
                                        value="<?= $spousedata['prm_per_street']; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                      <label>Primary Person City</label>
                                      <input type="text" name="prm_per_city" class="form-control"
                                        value="<?= $spousedata['prm_per_city']; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                      <label>Primary Person State</label>
                                      <input type="text" name="prm_per_state" class="form-control"
                                        value="<?= $spousedata['prm_per_state']; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                      <label>Primary Person ZIP</label>
                                      <input type="text" name="prm_per_zip" class="form-control"
                                        value="<?= $spousedata['prm_per_zip']; ?>">
                                    </div>

                                    <!-- Row 9: Other Contacts -->
                                    <div class="form-group col-md-6">
                                      <label>OPhone (cell):</label>
                                      <input type="text" name="other_con_1" class="form-control"
                                        value="<?= $spousedata['other_con_1']; ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                      <label>Phone (work): </label>
                                      <input type="text" name="other_con_2" class="form-control"
                                        value="<?= $spousedata['other_con_2']; ?>">
                                    </div>

                                    <!-- Row 10: Entity and Business Type -->
                                    <div class="form-group col-md-6">
                                      <label>Entity Form</label>
                                      <select name="entity_form_id" class="form-control select">
                                        <option value="">--Select--</option>
                                        <?php
                                        $MTq = mysqli_query($con, "SELECT * FROM entityform");
                                        while ($MTr = mysqli_fetch_array($MTq)) {
                                          $selected = ($MTr["entity_form_id"] == $spousedata['entity_form_id']) ? 'selected' : '';
                                          echo "<option value='{$MTr["entity_form_id"]}' $selected>{$MTr["entity_form_name"]}</option>";
                                        }
                                        ?>
                                      </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                      <label>Business Activity</label>
                                      <select name="busact_id" class="form-control select">
                                        <option value="">--Select--</option>
                                        <?php
                                        $BTq = mysqli_query($con, "SELECT * FROM busact");
                                        while ($BTr = mysqli_fetch_array($BTq)) {
                                          $selected = ($BTr["busact_id"] == $spousedata['busact_id']) ? 'selected' : '';
                                          echo "<option value='{$BTr["busact_id"]}' $selected>{$BTr["busact_name"]}</option>";
                                        }
                                        ?>
                                      </select>
                                    </div>

                                  </div>

                                  <div class="modal-footer">
                                    <button type="submit" name="updatebusiness" class="btn"
                                      style="background-color: #6FD943; color: white;">Update</button>
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                  </div>
                                </div>
                              </form>
                            </div>
                          </div>


                          <!-- Delete Modal -->
                          <div class="modal fade" id="deleteModal<?= $spousedata['addibusiness_id']; ?>">
                            <div class="modal-dialog modal-md">
                              <form method="POST">
                                <div class="modal-content">
                                  <div class="modal-body text-center">
                                    <input type="hidden" name="id" value="<?= $spousedata['addibusiness_id']; ?>">
                                    <input type="hidden" name="tblname" value="addibusiness">
                                    <input type="hidden" name="colname" value="addibusiness_id">
                                    <div class="swal2-icon swal2-warning swal2-icon-show" style="display: flex;">
                                      <div class="swal2-icon-content">!</div>
                                    </div>
                                    <h2 class="swal2-title">Are you sure?</h2>
                                    <p>This action cannot be undone. Do you want to continue?</p>
                                  </div>
                                  <div class="modal-footer justify-content-center">
                                    <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                                    <button type="submit" name="delete" class="btn btn-danger">Yes, Delete</button>
                                  </div>
                                </div>
                              </form>
                            </div>
                          </div>
                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>


        <!-- =============================== -->

        <div class="row mt-4" id="businesses">
          <div class="col-sm-12 col-md-12">
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <div class="col-md-10">
                    <h5>Link/Unlink Businesses</h5>
                  </div>
                </div>
                <hr>

                <!-- Link Business Form -->
                <div class="row mb-4">
                  <div class="col-md-12">
                    <form method="post" class="row">
                      <div class="col-md-10">
                        <label>Select Business to Link</label>
                        <select id="business_id" name="business_id" class="form-control"
                          style="max-width: 100%; width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; box-sizing: border-box;"
                          required>
                          <option value="">-- Select Business --</option>
                          <?php
                          $businesses = mysqli_query($con, "SELECT * FROM members");
                          while ($biz = mysqli_fetch_array($businesses)) {
                            $selected = isset($_POST['business_id']) && $_POST['business_id'] == $biz['member_id'] ? 'selected' : '';

                            $client_name = htmlspecialchars(trim("{$biz['name']} {$biz['mname']} {$biz['lname']}"));
                            $business_name = htmlspecialchars(trim($biz['bus_name']));

                            echo "<option value='{$biz['member_id']}' $selected 
      data-fullname='" . strtolower($client_name) . "' 
      data-business='" . strtolower($business_name) . "'
      style='max-width: 100%; overflow: hidden; text-overflow: ellipsis;'>
      {$client_name} ({$business_name})
    </option>";
                          }
                          ?>
                        </select>

                      </div>
                      <div class="col-md-2 align-self-end">
                        <button type="submit" name="link_business" class="btn btn-primary">
                          <i class="ti ti-link"></i> Link Business
                        </button>
                      </div>
                    </form>
                  </div>
                </div>

                <!-- Linked Businesses Table -->
                <div class="row my-2">
                  <div class="col-md-12 p-2">
                    <div class="table-responsive p-2">
                      <table class="table table-bordered mb-0">
                        <thead>
                          <tr>
                            <th>Name</th>
                            <th>Business Name</th>
                            <th>SSN</th>
                            <th>Linked Since</th>
                            <th>Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          $linkedQuery = "
  SELECT 
    bma.id AS link_id,
    bma.member_id AS client_id,
    bma.created_at,
    m.member_id AS business_id,
    m.bus_name,
    m.name,
    m.mname,
    m.lname,
    m.ss
  FROM business_member_assoc bma
  JOIN members m ON bma.business_id = m.member_id
  WHERE bma.member_id = '$client_id'
";

                          $linkedResult = mysqli_query($con, $linkedQuery);

                          if (mysqli_num_rows($linkedResult) > 0) {
                            while ($linked = mysqli_fetch_array($linkedResult)) {
                              $president = trim("{$linked['name']} {$linked['mname']} {$linked['lname']}");
                              echo "<tr>
      <td>{$president}</td>
       <td>{$linked['bus_name']}</td>
      <td>{$linked['ss']}</td>
      <td>" . date('m/d/y H:i:s', strtotime($linked['created_at'])) . "</td>
      <td class='d-flex gap-1'>
        <a href='client_details.php?id={$linked['business_id']}&status={$status}' class='btn btn-sm btn-info'>
  <i class='fas fa-eye'></i> View
</a>

        <form method='post'>
          <input type='hidden' name='link_id' value='{$linked['link_id']}'>
          <button type='submit' name='unlink_business' class='btn btn-sm btn-danger'>
            <i class='ti ti-unlink'></i> Unlink
          </button>
        </form>
      </td>
    </tr>";
                            }
                          } else {
                            echo "<tr><td colspan='5' class='text-center'>No businesses linked yet</td></tr>";
                          }
                          ?>

                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>

        <div class="row" id="documents">
          <div class="col-sm-12 col-md-12">
            <div class="card ">
              <div class="card-body employee-detail-body fulls-card">
                <div class="row">
                  <div class="col-md-9">
                    <h5>Client Document(s)</h5>
                  </div>
                  <div class="col-md-3">
                    <button class="btn btn-primary" data-toggle="modal" data-target="#uploaddocument"
                      style="float:left;">Upload Document</button>
                  </div>
                </div>
                <hr>
                <div class="row">
                  <div class="dataTable-container">
                    <table class="table datatable4">
                      <thead>
                        <tr>
                          <th data-sortable=""><a href="#" class="dataTable-sorter" style="width: 10.3725%;">Uploaded
                              By</a></th>
                          <th data-sortable=""><a href="#" class="dataTable-sorter">Documents</a></th>
                          <th data-sortable="" style="width: 10.3725%;"><a href="#" class="dataTable-sorter">Action</a>
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $getdoc = mysqli_query($con, "SELECT * FROM documents where client_id='$client_id'");
                        while ($fetchdoc = mysqli_fetch_array($getdoc)) {
                          $userid = $fetchdoc['uploaded_by'];
                          ?>
                          <tr>
                            <td><?php
                            $CSTq = mysqli_query($con, "SELECT * FROM users where user_id=$userid");
                            $CSTr = mysqli_fetch_array($CSTq);
                            echo $CSTr["user_login"];
                            ?></td>
                            <td><a href="#" data-toggle="modal"
                                data-target="#viewdocuments<?php echo $fetchdoc['document_id']; ?>">view documents</a>
                            </td>
                            <td>
                              <!-- <button class="btn btn-sm btn-primary"><i class="ti ti-pencil text-white"></i></i></button>-->
                              <button class="btn btn-sm btn-custom" data-toggle="modal"
                                data-target="#deletec<?php echo $fetchdoc['document_id']; ?>"><i
                                  class="ti ti-trash text-white"></i></button>
                            </td>
                          </tr>

                          <div class="modal fade" id="deletec<?php echo $fetchdoc['document_id']; ?>">
                            <div class="modal-dialog modal-md">
                              <form method="POST">
                                <div class="modal-content">

                                  <!-- Modal body -->
                                  <div class="modal-body">
                                    <div class="row mb-2">
                                      <div class="col-md-12 mt-2">
                                        <input name="id" type="hidden" value="<?php echo $fetchdoc['document_id']; ?>">
                                        <input name="tblname" type="hidden" value="documents">
                                        <input name="colname" type="hidden" value="document_id">
                                        <div class="swal2-icon swal2-warning swal2-icon-show" style="display: flex;">
                                          <div class="swal2-icon-content">!</div>
                                        </div>
                                        <h2 class="swal2-title" id="swal2-title" style="display: block;">Are you sure?
                                        </h2>
                                        <div class="swal2-html-container" id="swal2-html-container"
                                          style="display: block;">This action can not be undone. Do you want to
                                          continue?
                                        </div>
                                      </div>
                                    </div>
                                  </div>

                                  <!-- Modal footer -->
                                  <div class="modal-footer" style="justify-content: center;">
                                    <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary" name="delete">Yes Delete</button>
                                  </div>

                                </div>
                              </form>
                            </div>
                          </div>


                          <!-- Delete Product Modal -->
                          <div class="modal fade" id="viewdocuments<?php echo $fetchdoc['document_id']; ?>">
                            <div class="modal-dialog modal-md">
                              <form method="POST">
                                <div class="modal-content">

                                  <!-- Modal body -->
                                  <div class="modal-body">
                                    <div class="row mb-2">
                                      <div class="col-md-12 mt-2">
                                        <input name="documentid" type="hidden"
                                          value="<?php echo $fetchdoc['document_id']; ?>">
                                        <div class="swal2-icon swal2-warning swal2-icon-show" style="display: flex;">
                                          <div class="swal2-icon-content">!</div>
                                        </div>
                                        <h2 class="swal2-title" id="swal2-title" style="display: block;">Enter Password
                                        </h2>
                                        <input type="password" name="password" class="form-control"
                                          placeholder="Enter Password...." style="margin-top:20px">
                                      </div>
                                    </div>
                                  </div>

                                  <!-- Modal footer -->
                                  <div class="modal-footer" style="justify-content: center;">
                                    <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary" name="verifypassword">Verify</button>
                                  </div>

                                </div>
                              </form>
                            </div>
                          </div>

                        <?php } ?>
                      </tbody>
                    </table>
                  </div>
                </div>

              </div>
            </div>
          </div>

        </div>


      </div>



      <!-- The Business Add Modal -->
      <div class="modal fade" id="uploaddocument">
        <div class="modal-dialog modal-xl">
          <form method="POST" enctype="multipart/form-data">
            <div class="modal-content">

              <!-- Modal Header -->
              <div class="modal-header">
                <h4 class="modal-title">Upload Documents</h4>
              </div>

              <!-- Modal body -->
              <div class="modal-body">
                <div class="row mb-2">
                  <input type="hidden" name="clientname" value="<?php echo $clientname; ?>">
                  <input type="hidden" name="clientphone" value="<?php echo $clientphone; ?>">
                  <input type="hidden" name="clientemail" value="<?php echo $clientemail; ?>">
                  <input type="hidden" name="clientssn" value="<?php echo $clientssn ?? ''; ?>">


                  <div class="col-md-6 form-group" bis_skin_checked="1" style="margin:0px">
                    <label for="green_card" class="form-label" style="margin:0px">Green Card</label>
                    <div class="choose-file " bis_skin_checked="1">
                      <label for="green_card" class="form-label" style="width: 100%;">
                        <input type="file" class="form-control" name="green_card" id="green_card"
                          data-filename="pro_image_create">
                        <img id="image" class="mt-3" style="width:25%;">

                      </label>
                    </div>
                  </div>
                  <div class="col-md-6 form-group" bis_skin_checked="1" style="margin:0px">
                    <label for="citizenShip_certificate" class="form-label" style="margin:0px">CitizenShip
                      certificate</label>
                    <div class="choose-file " bis_skin_checked="1">
                      <label for="citizenShip_certificate" class="form-label" style="width: 100%;">
                        <input type="file" class="form-control" name="citizenShip_certificate"
                          id="citizenShip_certificate" data-filename="pro_image_create">
                        <img id="image" class="mt-3" style="width:25%;">

                      </label>
                    </div>
                  </div>
                  <div class="col-md-6 form-group" bis_skin_checked="1" style="margin:0px">
                    <label for="nadara_id_cards" class="form-label" style="margin:0px">Nadara Id Card</label>
                    <div class="choose-file " bis_skin_checked="1">
                      <label for="nadara_id_cards" class="form-label" style="width: 100%;">
                        <input type="file" class="form-control" name="nadara_id_cards" id="nadara_id_cards"
                          data-filename="pro_image_create">
                        <img id="image" class="mt-3" style="width:25%;">

                      </label>
                    </div>
                  </div>
                  <div class="col-md-6 form-group" bis_skin_checked="1" style="margin:0px">
                    <label for="ssn_certificate" class="form-label" style="margin:0px">SSN</label>
                    <div class="choose-file " bis_skin_checked="1">
                      <label for="ssn_certificate" class="form-label" style="width: 100%;">
                        <input type="file" class="form-control" name="ssn_certificate" id="ssn_certificate"
                          data-filename="pro_image_create">
                        <img id="image" class="mt-3" style="width:25%;">

                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Modal footer -->
              <div class="modal-footer">
                <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary" name="adddocuments">Submit</button>
              </div>

            </div>
          </form>
        </div>
      </div>



      <div class="row" id="notes">
        <div class="col-sm-12 col-md-12">
          <div class="card">
            <div class="card-body employee-detail-body fulls-card">
              <div class="row align-items-center">
                <div class="col-md-10">
                  <h5>Notes</h5>
                </div>
                <div class="col-md-2 pb-3">
                  <button class="btn btn-primary" data-toggle="modal" data-target="#addnotes">Add
                    Notes</button>
                </div>
                <hr>
                <div class="dataTable-container">
                  <table class="table datatable4">
                    <thead>
                      <tr>
                        <th>Notes</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      // Corrected fetch query from 'notestab'
                      $querr1 = mysqli_query($con, "SELECT notestab.*, users.user_login AS note_by FROM notestab LEFT JOIN users ON notestab.user_id = users.user_id WHERE member_id='$client_id' ORDER BY notes_id DESC");

                      $i = 0;
                      while ($noteRow = mysqli_fetch_array($querr1)) {
                        $notedate = $noteRow['notes_time'];
                        $note_detail[$i] = $noteRow["notes_detail"];
                        $note_by = isset($noteRow['note_by']) ? $noteRow['note_by'] : 'Unknown';
                        ?>
                        <tr>
                          <!-- <td><p><?php echo htmlspecialchars($note_by); ?></p></td> -->
                          <td>
                            <div
                              style="max-width: 100%; min-width: 100%; white-space: normal; overflow-wrap: break-word; padding: 5px;">
                              <?php echo htmlspecialchars_decode($noteRow["notes_detail"]); ?>
                            </div>
                          </td>

                          <td>
                            <button class="btn btn-sm btn-custom" data-toggle="modal"
                              data-target="#deleteNote<?php echo $noteRow['notes_id']; ?>">
                              <i class="ti ti-trash text-white"></i>
                            </button>
                          </td>
                        </tr>

                        <!-- Modal for delete confirmation -->
                        <div id="deleteNote<?php echo $noteRow['notes_id']; ?>" class="modal fade" role="dialog">
                          <div class="modal-dialog modal-md">
                            <form method="POST">
                              <div class="modal-content">
                                <div class="modal-body">
                                  <div class="row mb-2">
                                    <div class="col-md-12 mt-2">
                                      <input name="id" type="hidden" value="<?php echo $noteRow['notes_id']; ?>">
                                      <input name="tblname" type="hidden" value="notestab">
                                      <input name="colname" type="hidden" value="notes_id">
                                      <div class="swal2-icon swal2-warning swal2-icon-show" style="display: flex;">
                                        <div class="swal2-icon-content">!</div>
                                      </div>
                                      <h2 class="swal2-title" style="display: block;">Are you sure?</h2>
                                      <div class="swal2-html-container" style="display: block;">
                                        This action cannot be undone. Do you want to continue?
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                <div class="modal-footer" style="justify-content: center;">
                                  <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                                  <button type="submit" class="btn btn-primary" name="delete">Yes, Delete</button>
                                </div>
                              </div>
                            </form>
                          </div>
                        </div>
                        <?php $i++;
                      } ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>


        <!-- The Business Add Modal -->
        <div class="modal fade" id="addbusiness">
          <div class="modal-dialog modal-xl">
            <form method="POST">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Add Bussiness</h4>
                </div>

                <!-- Modal body -->
                <div class="modal-body">
                  <div class="row mb-2">
                    <div class="col-md-4">
                      <label for="product" class="form-label">Business Name</label><span class="text-danger">*
                        <input class="form-control" required="required" name="bus_name" type="text"
                          placeholder="Business Name">
                    </div>
                    <div class="col-md-4">
                      <label for="product" class="form-label">SSN</label><span class="text-danger">*
                        <input class="form-control" required="required" name="ssn" type="text" placeholder="SSN">
                    </div>
                    <div class="col-md-4">
                      <label for="product" class="form-label">EIN</label><span class="text-danger">*
                        <input class="form-control" required="required" name="ein" type="text" placeholder="EIN">
                    </div>
                    <div class="col-md-4">
                      <label for="product" class="form-label">President Name</label><span class="text-danger">*
                        <input class="form-control" required="required" name="president_name" type="text"
                          placeholder="President Name">
                    </div>
                    <div class="col-md-4">
                      <label for="product" class="form-label">President DOB</label><span class="text-danger">*
                        <input class="form-control" required="required" name="president_dob" type="date"
                          placeholder="President DOB">
                    </div>
                    <div class="col-md-4">
                      <label for="product" class="form-label">Responsible Person</label><span class="text-danger">*
                        <input class="form-control" required="required" name="res_person" type="text"
                          placeholder="Responsible Person">
                    </div>
                    <div class="col-md-6">
                      <label for="other_con_1" class="form-label">Phone: Cell</label><span class="text-danger">*</span>
                      <input class="form-control" required="required" name="other_con_1" type="tel" id="other_con_1"
                        placeholder="XXX-XXX-XXXX" inputmode="numeric" pattern="^\d{3}-\d{3}-\d{4}$" maxlength="12"
                        title="Please enter a phone number in the format XXX-XXX-XXXX">
                    </div>

                    <div class="col-md-6">
                      <label for="other_con_2" class="form-label">Phone: Work</label><span class="text-danger">*</span>
                      <input class="form-control" required="required" name="other_con_2" type="tel" id="other_con_2"
                        placeholder="XXX-XXX-XXXX" inputmode="numeric" pattern="^\d{3}-\d{3}-\d{4}$" maxlength="12"
                        title="Please enter a phone number in the format XXX-XXX-XXXX">
                    </div>

                    <script>
                      function formatPhoneInput(id) {
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

                      formatPhoneInput('other_con_1');
                      formatPhoneInput('other_con_2');
                    </script>

                  </div>
                  <div class="row" style="margin-top: 2rem !important;">
                    <div class="col-md-6 col-sm-12" style="border-right:1px solid #d1d1d1">
                      <h3>Primary Person Address</h3>
                      <div class="row mb-2">
                        <div class="col-md-6">
                          <label for="product" class="form-label">Street Address</label><span class="text-danger">*
                            <input class="form-control" required="required" name="prm_per_street" type="text"
                              placeholder="Street Address">
                        </div>
                        <div class="col-md-6">
                          <label for="product" class="form-label">City</label><span class="text-danger">*
                            <input class="form-control" required="required" name="prm_per_city" type="text"
                              placeholder="City">
                        </div>
                        <div class="col-md-6">
                          <label for="product" class="form-label">State</label><span class="text-danger">*
                            <input class="form-control" required="required" name="prm_per_state" type="text"
                              placeholder="State">
                        </div>
                        <div class="col-md-6">
                          <label for="product" class="form-label">Zip Code</label><span class="text-danger">*
                            <input class="form-control" required="required" name="prm_per_zip" type="text"
                              placeholder="Zip Code">
                        </div>

                      </div>
                    </div>
                    <div class="col-md-6 col-sm-12">
                      <h3>Business Address</h3>
                      <div class="row mb-2">
                        <div class="col-md-6">
                          <label for="product" class="form-label">Street Address</label><span class="text-danger">*
                            <input class="form-control" required="required" name="bus_street" type="text"
                              placeholder="Street Address">
                        </div>
                        <div class="col-md-6">
                          <label for="product" class="form-label">City</label><span class="text-danger">*
                            <input class="form-control" required="required" name="bus_city" type="text"
                              placeholder="City">
                        </div>
                        <div class="col-md-6">
                          <label for="product" class="form-label">State</label><span class="text-danger">*
                            <input class="form-control" required="required" name="bus_state" type="text"
                              placeholder="State">
                        </div>
                        <div class="col-md-6">
                          <label for="product" class="form-label">Zip Code</label><span class="text-danger">*
                            <input class="form-control" required="required" name="bus_zip" type="text"
                              placeholder="Zip Code">
                        </div>
                        <!-- <div class="col-md-12" style="margin-top:10px">
                          <div class="form-check">
                            <label class="form-check-label">
                              <input type="checkbox" name="coupon_question" class="form-check-input" id="show" value="1"
                                onchange="valueChanged()"> Mail Address Same as Business
                            </label>
                          </div>
                        </div> -->
                      </div>
                    </div>
                    <div class="col-md-6 col-sm-12" style="border-right:1px solid #d1d1d1;margin-top:2rem">
                      <div class="row mb-2">
                        <div class="col-md-6">
                          <label for="product" class="form-label">Client Since</label>
                          <input class="form-control" name="client_since" type="date" placeholder="Client Since">
                        </div>
                        <div class="col-md-6">
                          <label for="product" class="form-label">Business Start Date</label>
                          <input class="form-control" name="bus_start_date" type="date"
                            placeholder="Business Start Date">
                        </div>
                        <div class="col-md-6">
                          <label for="product" class="form-label">Incorporation Date</label>
                          <input class="form-control" name="incorp_date" type="date" placeholder="Incorporation Date">
                        </div>
                        <div class="col-md-6">
                          <label for="product" class="form-label">Incorporation State</label>
                          <select name="incorp_state" class="form-control select">
                            <option value="">--Select--</option>
                            <?php $ICq = mysqli_query($con, "SELECT * FROM states");
                            while ($ICr = mysqli_fetch_array($ICq)) { ?>
                              <option value="<?= $ICr["state_id"] ?>"><?= $ICr["state"] ?>, <?= $ICr["state_code"] ?>
                              </option>
                            <?php } ?>
                          </select>
                        </div>

                        <div class="col-md-6">
                          <label for="product" class="form-label">Entity Form</label>
                          <select name="entity_form_id" class="form-control select">
                            <option value="">--Select--</option>
                            <?php $MTq = mysqli_query($con, "SELECT * FROM entityform");
                            while ($MTr = mysqli_fetch_array($MTq)) { ?>
                              <option value="<?= $MTr["entity_form_id"] ?>"><?= $MTr["entity_form_name"] ?></option>
                            <?php } ?>
                          </select>
                        </div>

                        <div class="col-md-6">
                          <label for="product" class="form-label">Business Activity</label>
                          <select name="busact_id" class="form-control select">
                            <option value="">--Select--</option>
                            <?php $MTq = mysqli_query($con, "SELECT * FROM busact");
                            while ($MTr = mysqli_fetch_array($MTq)) { ?>
                              <option value="<?= $MTr["busact_id"] ?>"><?= $MTr["busact_name"] ?></option>
                            <?php } ?>
                          </select>
                        </div>

                      </div>
                    </div>
                    <div class="col-md-6 col-sm-12" id="box" style="margin-top:2rem">
                      <h3>Mailing Address</h3>
                      <div class="row mb-2">
                        <div class="col-md-6">
                          <label for="product" class="form-label">Street Address</label>
                          <input class="form-control" name="mail_street" type="text" placeholder="Street Address">
                        </div>
                        <div class="col-md-6">
                          <label for="product" class="form-label">City</label>
                          <input class="form-control" name="mail_city" type="text" placeholder="City">
                        </div>
                        <div class="col-md-6">
                          <label for="product" class="form-label">State</label>
                          <input class="form-control" name="mail_state" type="text" placeholder="State">
                        </div>
                        <div class="col-md-6">
                          <label for="product" class="form-label">Zip Code</label>
                          <input class="form-control" name="mail_zip" type="text" placeholder="Zip Code">
                        </div>

                      </div>
                    </div>
                  </div>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                  <button type="submit" class="btn btn-primary" name="addbusiness">Submit</button>
                </div>

              </div>
            </form>
          </div>
        </div>


        <!-- The Noteses Modal -->
        <div class="modal fade" id="addnotes">
          <div class="modal-dialog modal-lg">
            <form method="POST">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Add Notes</h4>
                </div>

                <!-- Modal body -->
                <div class="modal-body">
                  <div class="row mb-2">
                    <div class="col-md-12">
                      <label for="product" class="form-label">Notes</label>
                      <textarea class="ckeditor" name="notes_detail"></textarea>
                    </div>
                  </div>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                  <button type="submit" class="btn btn-primary" name="addnotes">Submit</button>
                </div>

              </div>
            </form>
          </div>
        </div>

        <div class="modal fade" id="addcomment">
          <div class="modal-dialog modal-lg">
            <form method="POST">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Add Comment</h4>
                </div>

                <!-- Modal body -->
                <div class="modal-body">
                  <div class="row mb-2">
                    <div class="col-md-12">
                      <label for="product" class="form-label">Comment</label>
                      <textarea class="ckeditor" name="comment_details"></textarea>
                    </div>
                  </div>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                  <button type="submit" class="btn btn-primary" name="addcomment">Submit</button>
                </div>

              </div>
            </form>
          </div>
        </div>


        <!-- ===========addnewcomments=============== -->
        <div class="modal fade" id="addNewcomment">
          <div class="modal-dialog modal-lg">
            <form method="POST">
              <div class="modal-content">
                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Add Comment</h4>
                </div>
                <!-- Modal body -->
                <div class="modal-body">
                  <div class="row mb-2">
                    <div class="col-md-12">

                      <!-- Quick phrases -->
                      <!-- Quick phrases -->
                      <label class="form-label">Quick phrases</label>
                      <div class="mb-2">
                        <?php
                        $uid = (int) $_SESSION['user_id'];
                        $res = mysqli_query(
                          $con,
                          "SELECT phrase FROM user_quick_phrases WHERE user_id=$uid ORDER BY id"
                        );
                        while ($row = mysqli_fetch_assoc($res)) {
                          echo '<button type="button" class="btn btn-sm btn-outline-primary me-1 mb-1" onclick="addPhraseGlobal(\'' . htmlspecialchars($row['phrase']) . '\')">' . htmlspecialchars($row['phrase']) . '</button>';
                        }
                        ?>
                        <a href="quickphrases.php" class="btn btn-primary btn-sm">Edit Phrases</a>

                      </div>

                      <!-- Comment box -->
                      <label for="comment_details" class="form-label">Comment (edit or type)</label>
                      <textarea class="ckeditor" name="comment_details" id="comment_details"></textarea>
                    </div>
                  </div>
                </div>
                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                  <button type="submit" class="btn btn-primary" name="addNewcomment">Submit</button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- ========================================================= -->

        <!-- The Spouse Modal -->
        <div class="modal fade" id="addSpouse">
          <div class="modal-dialog modal-lg">
            <form method="POST">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Add MEMBER</h4>
                </div>

                <!-- Modal body -->
                <div class="modal-body">
                  <div class="row mb-2">
                    <div class="col-md-6">

                      <label class="form-label">Relation</label>
                      <select class='form-control' id="relations" name="relationship">
                        <option value="brother">Brother</option>
                        <option value="sister">Sister</option>
                        <option value="mother">Mother</option>
                        <option value="father">Father</option>
                        <option value="spouses">Spouse(s)</option>
                        <option value="mother_in_law">Mother-in-law</option>
                        <option value="father_in_law">Father-in-law</option>
                        <option value="wife">Wife</option>
                        <option value="daughter">Daughter</option>
                        <option value="son">Son</option>
                        <option value="other">Other</option>
                      </select>
                      <div id="otherRelationField" style="display: none;">
                        <label for="otherRelation">Other Relation:</label>
                        <input type="text" class="form-control" name="otherrelation" id="otherRelation">
                      </div>
                      <label for="product" class="form-label"> Name</label>
                      <input class="form-control" required="required" name="spousename" type="text" placeholder=" Name">
                    </div>

                    <div class="col-md-6">
                      <label for="product" class="form-label"> DOB</label>
                      <input class="form-control" required="required" name="spousedob" type="date" placeholder=" DOB">
                    </div>
                    <div class="col-md-6">
                      <label for="product" class="form-label"> SS</label>
                      <input class="form-control" required="required" name="spousess" type="text" placeholder=" SS">
                    </div>
                    <div class="col-md-6">
                      <label for="product" class="form-label"> Street Address</label>
                      <input class="form-control" required="required" name="spousestreetaddress" type="text"
                        placeholder="Street Address ">
                    </div>
                    <div class="col-md-6">
                      <label for="product" class="form-label"> City</label>
                      <input class="form-control" required="required" name="spousecity" type="text" placeholder=" City">
                    </div>
                    <div class="col-md-6">
                      <label for="product" class="form-label"> Occupation</label>
                      <input class="form-control" required="required" name="spouseoccupation" type="text"
                        placeholder=" Occupation">
                    </div>
                    <div class="col-md-6">
                      <label for="product" class="form-label"> Phone</label>
                      <input class="form-control" required="required" name="spousephone" type="tel" placeholder="Phone">
                    </div>
                    <div class="col-md-6">
                      <label for="product" class="form-label"> Email Address</label>
                      <input class="form-control" required="required" name="spouseemailaddress" type="email"
                        placeholder=" Email Address">
                    </div>
                  </div>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                  <button type="submit" class="btn btn-primary" name="addspouse">Submit</button>
                </div>

              </div>
            </form>
          </div>
        </div>
        <input type="hidden" id="hasAutoRenewed_<?= $client_id ?>" value="<?= $hasAutoRenewed ? '1' : '0' ?>">
        <input type="hidden" id="autoRenewedProducts_<?= $client_id ?>"
          value="<?= htmlspecialchars(implode("\n", $autoRenewedProducts)) ?>">
        <!-- REMOVE THIS -->
        <!-- <div class="alert alert-info">
          <strong>⏳ Next auto-renewal in:</strong> <span id="renewal-countdown">Calculating...</span>
        </div> -->
        <!-- [ Main Content ] end -->
      </div>

      <div class="row" id="projects">
        <div class="col-sm-12 col-md-12">
          <div class="card">
            <div class="card-body employee-detail-body fulls-card">
              <div class="row align-items-center">
                <div class="col-md-10">
                  <h5>Projects</h5>
                </div>
                <div class="col-md-2 pb-3">
                  <button class="btn btn-primary" onclick="window.location.href='add_new_projects.php';">Add
                    Project</button>
                </div>
              </div>
              <hr>
              <!-- DataTable wrapper -->
              <div class="dataTable-container">
                <table class="table datatable1" style="width:100%; min-width:900px;">
                  <thead>
                    <tr>
                      <th>Project ID</th>
                      <th>Client</th>
                      <th>Business</th>
                      <th>Product</th>
                      <th>Title</th>
                      <th>Assigned To</th>
                      <th>Start Date</th>
                      <th>Due Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $query = mysqli_query($con, "
                SELECT 
                  p.*, 
                  m.name AS client_name, 
                  m.mname, 
                  m.lname, 
                  m.bus_name, 
                  pr.membership_name AS product_name, 
                  u.user_name AS assigned_to 
                FROM projects p
                JOIN members m ON p.mem_id = m.member_id
                JOIN memberships pr ON p.prod_id = pr.membership_id
                JOIN users u ON p.assign_to = u.user_id 
                WHERE p.mem_id = '$client_id'
                ORDER BY p.order_id DESC
              ");

                    while ($project = mysqli_fetch_assoc($query)) {
                      $status = $project['project_status'];
                      $badgeClass = $status === 'completed' ? 'bg-success' : ($status === 'closed' ? 'bg-danger' : 'bg-warning');
                      ?>
                      <tr>
                        <td>
                          <a href="project_details.php?id=<?php echo urlencode($project['order_id']); ?>">
                            <?php echo htmlspecialchars($project['order_id']); ?>
                          </a>
                        </td>

                        <td>
                          <?php echo htmlspecialchars($project['client_name'] . ' ' . $project['mname'] . ' ' . $project['lname']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($project['bus_name']); ?></td>
                        <td><?php echo htmlspecialchars($project['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($project['project_title']); ?></td>
                        <td><?php echo htmlspecialchars($project['assigned_to']); ?></td>
                        <td><?php echo htmlspecialchars($project['start_date']); ?></td>
                        <td><?php echo htmlspecialchars($project['exp_date']); ?></td>
                        <td>
                          <span class="badge <?php echo $badgeClass; ?>">
                            <?php echo ucfirst($status); ?>
                          </span>
                        </td>
                      </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>



    </div>


    <div class="modal fade" id="login_secured_area">
      <div class="modal-dialog modal-md">
        <form method="POST">
          <div class="modal-content">
            <div class="modal-header">
              <h4 class="modal-title">Login into Secured Area</h4>
              <!-- Close Button with Proper Red Icon -->
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <i class="fas fa-times text-danger"></i>
              </button>
            </div>

            <!-- Modal body -->
            <div class="modal-body">
              <div class="row mb-2">
                <div class="col-md-12 mt-2">
                  <input name="user_secured" type="text" class="form-control my-2">
                  <input name="pass_secured" type="password" class="form-control">
                </div>
              </div>
            </div>

            <!-- Modal footer -->
             <!-- added -->
            <div class="modal-footer" style="justify-content: center;">
              <button type="submit" name="loginSecuredArea" class="btn btn-primary">Login</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <!-- Modal Warning -->
    <div class="modal fade" id="modalWarning" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-warning text-dark">
            <h5 class="modal-title">Important Note / Warning</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <textarea id="warningText" class="form-control" rows="4"
              placeholder="Type the warning everyone must read..."><?=
                $warn['warning'] ?? '' ?></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-warning" onclick="saveWarning()">Save Warning</button>
          </div>
        </div>
      </div>
    </div>


    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>



    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var relationSelect = document.getElementById('relations');
        var otherRelationField = document.getElementById('otherRelationField');

        relationSelect.addEventListener('change', function () {
          if (relationSelect.value === 'other') {
            otherRelationField.style.display = 'block';
          } else {
            otherRelationField.style.display = 'none';
          }
        });
      });

      function calculateExpiryDate() {

        const startDateInput = document.getElementById('start_date');
        const startDate = new Date(startDateInput.value);


        const cycleSelect = document.getElementById('cycle');
        const selectedCycle = parseInt(cycleSelect.value, 10);

        const expiryDate = new Date(startDate);
        expiryDate.setDate(startDate.getDate() + selectedCycle);
        const formattedExpiryDate = expiryDate.toISOString().split('T')[0];
        const expiryDateInput = document.getElementById('expiry_date');
        expiryDateInput.value = formattedExpiryDate;
      }
    </script>
    <script type="text/javascript">
      $(document).ready(function () {
        $('.modal-footer .closemodal').click(function () {
          $('#myModal').modal('hide');
        });
        $('.comment-details-clickable').click(function () {
          var commentdate = $(this).attr('cd');
          var commentby = $(this).attr('cb');

          var commentDetails = $(this).text();
          $("#cd").text(commentdate);
          $("#cb").text(commentby);
          $("#cdd").text(commentDetails);
          $('#myModal').modal('show');

        });
      });
      const checkbox = document.getElementById('show');

      const box = document.getElementById('box');

      checkbox.addEventListener('click', function handleClick() {
        if (checkbox.checked) {
          box.style.display = 'none';
        } else {
          box.style.display = 'block';
        }
      });

      function calculateSum() {
        const inputFields = document.querySelectorAll('.sum');
        const totalSpan = document.getElementById("sub_total").value;


        var discount = document.getElementById("discount").value;
        var grand_total = document.getElementById("grand_total").value;
        let sum = 0;

        inputFields.forEach(input => {
          sum += parseFloat(input.value) || 0;
        });

        document.getElementById("sub_total").value = sum;
        var total = sum - discount;
        document.getElementById("grand_total").value = total;

      }

      function changetotal() {
        var sub_total = document.getElementById("sub_total").value;
        var discount = document.getElementById("discount").value;
        var grand_total = document.getElementById("grand_total").value;

        var total = sub_total - discount;
        document.getElementById("grand_total").value = total;
      }


      function getdiscount() {
        var sub_total = document.getElementById("sub_total").value;
        var discount = document.getElementById("discount").value;
        var grand_total = document.getElementById("grand_total").value;
        var total = sub_total - discount;
        document.getElementById("grand_total").value = total;

      }

      function myFunction() {
        var x = document.getElementById("myDIV");
        if (x.style.display === "none") {
          x.style.display = "block";
        } else {
          x.style.display = "none";
        }
      }

      function mydiscountrow() {
        var x = document.getElementById("discountrow");
        var totalSpan = document.getElementById("sub_total").value;


        if (x.style.display === "none") {
          x.style.display = "flex";
        } else {
          x.style.display = "none";
          document.getElementById("grand_total").value = totalSpan;
          document.getElementById("discount").value = 0;
        }
      }
    </script>
    <script type="text/javascript">

      $(document).ready(function () {
        $('.modal-footer .closemodal').click(function () {

          $('#myModal').modal('hide');
        });
        $('.comment-details-clickable').click(function () {

          var commentdate = $(this).attr('cd');
          var commentby = $(this).attr('cb');
          var commentto = $(this).attr('ct');
          var commentDetails = $(this).text();

          $("#ct").html(commentto);
          $("#cd").text(commentdate);
          $("#cb").text(commentby);
          $("#cdd").text(commentDetails);
          $('#myModal').modal('show');
        });
      });

      checkbox.addEventListener('click', function handleClick() {
        if (checkbox.checked) {
          box.style.display = 'none';
        } else {
          box.style.display = 'block';
        }
      });

      function calculateSum() {
        const inputFields = document.querySelectorAll('.sum');
        const totalSpan = document.getElementById("sub_total").value;

        var discount = document.getElementById("discount").value;
        var grand_total = document.getElementById("grand_total").value;

        let sum = 0;

        inputFields.forEach(input => {
          sum += parseFloat(input.value) || 0;
        });
        document.getElementById("sub_total").value = sum;

        var total = sum - discount;
        document.getElementById("grand_total").value = total;

      }

      function changetotal() {
        var sub_total = document.getElementById("sub_total").value;
        var discount = document.getElementById("discount").value;
        var grand_total = document.getElementById("grand_total").value;

        var total = sub_total - discount;
        document.getElementById("grand_total").value = total;
      }


      function getdiscount() {
        var sub_total = document.getElementById("sub_total").value;
        var discount = document.getElementById("discount").value;
        var grand_total = document.getElementById("grand_total").value;
        var total = sub_total - discount;
        document.getElementById("grand_total").value = total;



      }
      $(document).ready(function () {

      });

      function myFunction() {
        var x = document.getElementById("myDIV");
        if (x.style.display === "none") {
          x.style.display = "block";
        } else {
          x.style.display = "none";
        }
      }

      function mydiscountrow() {
        var x = document.getElementById("discountrow");
        var totalSpan = document.getElementById("sub_total").value;


        if (x.style.display === "none") {
          x.style.display = "flex";
        } else {
          x.style.display = "none";
          document.getElementById("grand_total").value = totalSpan;
          document.getElementById("discount").value = 0;
        }
      }
    </script>

    <!-- ------------------------------------------ -->
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
              var currentUrl = window.location.href.split('?')[0];
              var newUrl = currentUrl + '?id=' + memberId + '&status=' + status;
              window.history.pushState({ path: newUrl }, '', newUrl);
              window.location.reload();
            },
            error: function (xhr, status, error) {
              console.error(error);
            }
          });
        });
      });
    </script>


    <script>
      function confirmDelete(orderId) {
        Swal.fire({
          title: 'Are you sure?',
          text: "You won't be able to revert this!",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
          if (result.isConfirmed) {

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'orderid';
            input.value = orderId;

            var deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'deleteproduct';
            deleteInput.value = '1';

            form.appendChild(input);
            form.appendChild(deleteInput);

            document.body.appendChild(form);
            form.submit();
          }
        });
      }
    </script>
    <script>
      if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
      }
    </script>

    <script>
      $(document).ready(function () {
        $('#business_id').select2({
          placeholder: "-- Select Business --",
          allowClear: true,
          dropdownParent: $('body'), // ✅ attach to body to prevent clipping
          matcher: function (params, data) {
            if ($.trim(params.term) === '') return data;

            var term = params.term.toLowerCase();
            var terms = term.split(/\s+/);

            var fullname = ($(data.element).data('fullname') || '').toLowerCase();
            var business = ($(data.element).data('business') || '').toLowerCase();

            var match = terms.every(function (word) {
              return fullname.includes(word) || business.includes(word);
            });

            return match ? data : null;
          }
        });
      });


    </script>
    <script>
      document.querySelectorAll(".sms-switch, .email-switch").forEach(function (toggle) {
        toggle.addEventListener("change", function () {
          let clientId = this.dataset.clientId;
          let smsEnabled = document.getElementById("smsSwitch" + clientId).checked ? 1 : 0;
          let emailEnabled = document.getElementById("emailSwitch" + clientId).checked ? 1 : 0;
          fetch("update_sms_email_btn.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `toggle_buttons=1&client_id=${clientId}&sms_toggle=${smsEnabled}&email_toggle=${emailEnabled}`
          })
            .then(res => res.text())
            .then(data => {
              // console.log("Server response:", data);
            });
        });
      });
    </script>
    <script>
      $(document).ready(function () {
        if ($('#hasAutoRenewed_<?= $client_id ?>').val() === '1') {
          const products = $('#autoRenewedProducts_<?= $client_id ?>').val().replace(/\n/g, '<br>');
          const clientId = '<?= $client_id ?>';

          Swal.fire({
            toast: true,
            position: 'bottom-end',
            icon: 'info',
            html: `<strong>Auto-renewed orders for Client #${clientId}:</strong><br>${products}`,
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true
          });
        }
      });
    </script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        (function () {
          const start = new Date('2025-11-12T00:00:00');
          const interval = 24 * 60 * 60 * 1000;

          let toast = null;
          let timerId = null;

          function createOrUpdateToast(title) {
            if (toast && Swal.isVisible()) { Swal.update({ title }); return; }
            toast = Swal.fire({
              toast: true,
              position: 'bottom-end',
              icon: 'info',
              title: title,
              showConfirmButton: false,
              allowOutsideClick: false,
              timer: 0
            });
          }

          // function tick() {
          //   const now = new Date();
          //   const elapsed = now - start;
          //   const next = new Date(start.getTime() + Math.ceil(elapsed / interval) * interval);
          //   const diff = next - now;

          //   const hrs = Math.floor(diff / 3_600_000);
          //   const mins = Math.floor((diff % 3_600_000) / 60_000);
          //   const secs = Math.floor((diff % 60_000) / 1000);

          //   createOrUpdateToast(
          //     diff <= 0
          //       ? '🔄 Auto-renewal is running...'
          //       : `Next auto-renewal in: ${hrs}h ${mins}m ${secs}s`
          //   );
          // }

          const hasAutoRenewed = document.getElementById('hasAutoRenewed_<?= $client_id ?>')?.value === '1';
          // console.log('hasAutoRenewed:', hasAutoRenewed);

          if (hasAutoRenewed) {
            tick();
            timerId = setInterval(tick, 1000);

            document.addEventListener('visibilitychange', function () {
              if (!document.hidden) {
                clearInterval(timerId);
                tick();
                timerId = setInterval(tick, 1000);
              }
            });
          }
        })();
      });
    </script>
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll('.datatable1').forEach(function (table) {
          new simpleDatatables.DataTable(table, {
            perPage: 5,
            perPageSelect: [5, 10, 25, 50]
          });
        });
      });

    </script>



    <!-- =========================================================
     CKEditor 5 – FREE classic build (full toolbar, no licence)
     ========================================================== -->
    <script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>
    <script>
      /* ---------- 1.  turn every <textarea class="ckeditor"> into CKE 5  ---------- */
      document.querySelectorAll('textarea.ckeditor').forEach(tx => {
        ClassicEditor
          .create(tx, {
            toolbar: [
              'undo', 'redo', '|',
              'heading', '|',
              'bold', 'italic',
              'bulletedList', 'numberedList', '|',
              'outdent', 'indent', '|',
              'blockQuote',
              '|',
            ],
            language: 'en',
            licenseKey: ''
          })
          .then(editor => {
            tx.ckeditorInstance = editor;
          })
          .catch(console.error);
      });

      /* ---------- 2.  quick-phrase buttons (unchanged behaviour) ---------- */
      function addPhraseGlobal(text) {
        const inst = document.querySelector('#comment_details')?.ckeditorInstance;
        if (inst) {
          inst.model.change(writer => {
            const pos = inst.model.document.selection.getFirstPosition();
            writer.insertText(text + '. ', pos);
          });
          inst.editing.view.focus();
        }
      }
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <!-- =====  highlight menu on scroll / click ===== -->
    <script>
      (function () {
        const menu = document.querySelector('.client-menu-bar');
        const links = menu ? menu.querySelectorAll('.nav-link') : [];
        const sections = document.querySelectorAll('[id]');
        if (!menu || !links.length) return;

        function highlight() {
          let curr = '';
          sections.forEach(sec => {
            const rect = sec.getBoundingClientRect();
            if (rect.top <= 120 && rect.bottom >= 120) curr = sec.id;
          });
          links.forEach(l => l.classList.remove('active'));
          const active = menu.querySelector('a[href="#' + curr + '"]');
          if (active) active.classList.add('active');
        }
        window.addEventListener('scroll', highlight);
        highlight(); // first load
      })();
    </script>
    <script>
      /* universal helper */
      function apiCall(data, success) {
        fetch('warnings_ajax.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(data).toString()
        }).then(r => r.text())
          .then(res => res === 'OK' ? success() : Swal.fire('Error', res, 'error'));
      }

      function saveWarning() {
        const text = document.getElementById('warningText').value.trim();
        if (!text) return Swal.fire('Error', 'Text is empty', 'error');
        apiCall({ action: 'save', member_id: <?= $client_id ?>, warning: text }, () => location.reload());
      }
      function editWarning(id) {
        Swal.fire({
          title: 'Edit warning', input: 'textarea',
          inputValue: document.querySelector('.alert-warning span').textContent.trim(),
          showCancelButton: true, confirmButtonText: 'Save'
        }).then(r => {
          if (r.isConfirmed) apiCall({ action: 'edit', id: id, warning: r.value }, () => location.reload());
        });
      }
      function delWarning(id) {
        Swal.fire({ title: 'Delete warning?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' })
          .then(r => { if (r.isConfirmed) apiCall({ action: 'delete', id: id }, () => location.reload()); });
      }
    </script>
</body>

</body>

</html>