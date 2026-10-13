

<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'dompdf/autoload.inc.php';



include 'includes/db.php';
include "includes/auth.php";
include "includes/activity_log.php";
include "client_email.php"; // email compose helpers (also the send/template endpoint)

$client_id = $_GET['id'];

$status = $_GET['status'];
$msg = '';
$ostatus = '';

$userid = $_SESSION["user_id"];
$clientnamesecure = '';
include "client_field_notes.php";
include "module_history.php";
include "client_type_manual.php"; // settings page + shared profile engine (renders nothing when included)
include "includes/customizer.php"; // unified field customizer engine
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
$showFamilyTab = false;
$type_check = mysqli_query($con, "SELECT type FROM members WHERE member_id='$client_id' LIMIT 1");
if ($type_check && mysqli_num_rows($type_check) > 0) {
  $type_row = mysqli_fetch_assoc($type_check);
  $member_type_id = (int) ($type_row['type'] ?? 0);
  $showFamilyTab = in_array($member_type_id, [26, 28], true);
}

// Per-client-type profile manual (icon + which fields show). See client_type_manual.php.
$cpc_icon_url = cpc_type_icon_url($con, $member_type_id ?? 0);
// Raw path ('' when the type has no image) — the business hero keeps its
// briefcase glyph rather than falling back to the generic default image.
$cpc_icon_src = cpc_type_icon_src($con, $member_type_id ?? 0);
$cpc_visible  = cpc_type_field_config($con, $member_type_id ?? 0);

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
  if ($insert_associate) {

    $new_order_id = mysqli_insert_id($con);
    logActivity($con, $userid, 'CREATE', 'orders', $new_order_id, "Product added '{$servicename}' for Member ID: {$member_id}.");

    // A new order for an existing (mem_id, prod_id) supersedes any earlier active
    // orders + their auto-renewals — otherwise the renewal engine would treat them
    // as one merged timeline and keep renewing the old order with mixed bil_cyc_months.
    mysqli_query($con,
      "UPDATE orders SET order_status = -1
        WHERE mem_id = '$member_id' AND prod_id = '$prod_id'
          AND order_status = 1 AND order_id <> $new_order_id");
    mysqli_query($con,
      "UPDATE orders_auto_renewed SET order_status = -1
        WHERE mem_id = '$member_id' AND prod_id = '$prod_id'
          AND order_status = 1");

    // If the just-added service is already past its expiry, roll it forward immediately
    // (bypasses the daily lock so the user sees the renewal without waiting until tomorrow).
    if (!empty($expirydate) && $expirydate < date('Y-m-d')) {
      require_once 'includes/auto_renew_services.php';
      @run_service_auto_renewals($con, true);
    }

    $msg = "<div class='alert alert-success' role='alert'><b>Product Added Successfully</b></div>";
  }

}

if (isset($_POST['assigntask'])) {
  $order_id = $_POST['order_id'];
  $assigned_users = $_POST['assigned_users'];
  $associate_query = "UPDATE `orders` SET `assign_to`='$assigned_users' WHERE order_id='$order_id'";
  $insert_query = mysqli_query($con, $associate_query);
  if ($insert_query) {
    // LOG: Assigned task/product
    logActivity($con, $userid, 'UPDATE', 'orders', $order_id, "Assigned task/product ID: {$order_id} to user ID: {$assigned_users}.");

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
    // LOG: Added comment
    $new_comment_id = mysqli_insert_id($con);
    logActivity($con, $userid, 'CREATE', 'comments', $new_comment_id, "New comment added for Member ID: {$client_id}.");

    $msg .= "<div class='alert alert-success' role='alert'>
                    <b>Comment Has Been Posted</b>
                  </div>";
  } else {
    $msg .= "<div class='alert alert-danger' role='alert'>
                    <b>Something wrong!</b>
                  </div>" . $con->error;
  }
}

if (isset($_POST['addBankStatementComment'])) {
  $comment_details = $_POST['comment_details'];
  $current_time = date("Y-m-d H:i:s");

  $associate_query = "INSERT INTO comments(comment_detail, member_id, user_id, comment_time, comment_status, comment_updated_by) VALUES ('$comment_details','$client_id','$userid','$current_time','bank_statement','$userid')";

  $insert_associate = mysqli_query($con, $associate_query);

  if ($insert_associate) {
    $new_comment_id = mysqli_insert_id($con);
    logActivity($con, $userid, 'CREATE', 'bank_statement_comment', $new_comment_id, "New bank statement comment added for Member ID: {$client_id}.");

    $msg .= "<div class='alert alert-success' role='alert'>
                    <b>Bank Statement Comment Has Been Posted</b>
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
    // LOG: Added note
    $new_note_id = mysqli_insert_id($con);
    logActivity($con, $userid, 'CREATE', 'notestab', $new_note_id, "New note added for Member ID: {$client_id}.");

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
    // LOG: Added family member
    $new_spouse_id = mysqli_insert_id($con);
    logActivity($con, $userid, 'CREATE', 'spouse', $new_spouse_id, "Added family member '{$spousename}' for Member ID: {$client_id}.");

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
    // LOG: Updated family member
    logActivity($con, $userid, 'UPDATE', 'spouse', $spouse_id, "Updated family member ID: {$spouse_id} for Member ID: {$client_id}.");

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
  if ($insert_associate) {
    // LOG: Deactivated product
    logActivity($con, $userid, 'UPDATE', 'orders', $OrderID, "Deactivated product/order ID: {$OrderID}.");
  }
}
if (isset($_POST['deleteproduct'])) {
  $OrderID = $_POST['orderid'];
  $update_query = "delete from `orders`  where order_id='$OrderID'";
  $insert_associate = mysqli_query($con, $update_query);
  if ($insert_associate) {
    // LOG: Deleted product
    logActivity($con, $userid, 'DELETE', 'Order', $OrderID, "Deleted order ID: {$OrderID} for Member ID: {$client_id}.");
  }
}
if (isset($_POST['activateproduct'])) {
  $OrderID = $_POST['orderid'];
  $update_query = "UPDATE `orders` SET order_status='1' where order_id='$OrderID'";
  $insert_associate = mysqli_query($con, $update_query);
  if ($insert_associate) {
    // LOG: Activated product
    logActivity($con, $userid, 'UPDATE', 'orders', $OrderID, "Activated product/order ID: {$OrderID}.");
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
    // LOG: Accessed secured documents
    logActivity($con, $userid, 'VIEW', 'documents', $document_id, "Accessed secured documents for Member ID: {$client_id}.");
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
    // LOG: Added business
    $new_business_id = mysqli_insert_id($con);
    logActivity($con, $userid, 'CREATE', 'addibusiness', $new_business_id, "Associated Business – New business added  '{$bus_name}' for Member ID: {$client_id}.");

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

  $isBankStatementComment = false;
  if ($tblname === 'comments') {
    $statusRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT comment_status FROM comments WHERE comment_id='$id'"));
    $isBankStatementComment = (($statusRow['comment_status'] ?? '') === 'bank_statement');
  }

  $queryy = "DELETE FROM $tblname where $colname='$id'";
  $insert_query = mysqli_query($con, $queryy);
  if ($insert_query) {
    // LOG: Generic delete
    $deleteTypeMap = [
      'spouse' => 'Family Member',
      'addibusiness' => 'Business',
      'comments' => 'Comment',
    ];
    $deletePrefixMap = [
      'addibusiness' => 'Associated Business – ',
    ];
    $deleteObjectType = $isBankStatementComment ? 'bank_statement_comment' : $tblname;
    $deleteLabel = $isBankStatementComment ? 'Bank Statement Comment' : ($deleteTypeMap[$tblname] ?? 'Record');
    $deletePrefix = $deletePrefixMap[$tblname] ?? '';
    logActivity($con, $userid, 'DELETE', $deleteObjectType, $id, "{$deletePrefix}Deleted {$deleteLabel} (ID: {$id}) for Member ID: {$client_id}.");

    $msg .= "<div class='alert alert-success'>Record Been Deleted Successfully.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
  }
}


if (isset($_POST['loginSecuredArea'])) {
  $user_secured = trim($_POST['user_secured']);
  $pass_secured = $_POST['pass_secured'];
  $secureLoginOk = false;

  $stmt = $con->prepare("SELECT user_name FROM users WHERE LOWER(user_login)=LOWER(?) AND user_password=? AND user_login_status='1' AND user_ban_status='0' LIMIT 1");
  $secure_name = null;

  if ($stmt) {
    $stmt->bind_param("ss", $user_secured, $pass_secured);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $stmt->bind_result($secure_name);
      $stmt->fetch();
      $secureLoginOk = true;
      $_SESSION['secure_auth'] = 1;
      $_SESSION['secure_client_name'] = $secure_name;
      // LOG: Logged into secured area
      logActivity($con, $userid, 'LOGIN', 'user', $userid, "User '{$user_secured}' logged into the secured area for Member ID: {$client_id}.");
      $stmt->close();
      header("Location:secured_area.php?client_id=$client_id");
      exit;
    }
    $stmt->close();
  }

  if (!$secureLoginOk) {
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
      if ($addmethod) {
        // LOG: Moved order to completed
        $completed_order_id = mysqli_insert_id($con);
        logActivity($con, $userid, 'CREATE', 'orders_completed', $completed_order_id, "Moved completed order ID: {$order_id} to the completed orders table.");
      }

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

  $statusRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT comment_status FROM comments WHERE comment_id='$comment_id'"));
  $isBankStatementComment = (($statusRow['comment_status'] ?? '') === 'bank_statement');

  $update_query = "UPDATE comments
                    SET comment_detail = '$comment_details',
                        comment_updated_by = '$userid',
                        comment_time = '$current_time'
                    WHERE comment_id = '$comment_id'";

  $update_result = mysqli_query($con, $update_query);

  if ($update_result) {
    // LOG: Updated comment
    if ($isBankStatementComment) {
      logActivity($con, $userid, 'UPDATE', 'bank_statement_comment', $comment_id, "Updated bank statement comment ID: {$comment_id} for Member ID: {$client_id}.");
    } else {
      logActivity($con, $userid, 'UPDATE', 'comments', $comment_id, "Updated comment ID: {$comment_id} for Member ID: {$client_id}.");
    }

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
  $assoc_id_1 = mysqli_insert_id($con);

  mysqli_query(
    $con,
    "INSERT INTO business_member_assoc (business_id, member_id, user_id)
         VALUES ($client_id, $business_id, $userid)"
  );
  $assoc_id_2 = mysqli_insert_id($con);

  // LOG: Linked businesses
  logActivity($con, $userid, 'CREATE', 'business_member_assoc', $assoc_id_1, "Linked Member ID: {$client_id} with business ID: {$business_id}.");

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
    // LOG: Updated business
    logActivity($con, $userid, 'UPDATE', 'addibusiness', $business_id, "Updated business '{$bus_name}' (ID: {$business_id}).");

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
    // LOG: Added new invoice comment
    $new_comment_id = mysqli_insert_id($con);
    logActivity($con, $userid, 'CREATE', 'new_comments', $new_comment_id, "New invoice added comment for Member ID: {$client_id}.");

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
    // LOG: Updated new invoice comment
    logActivity($con, $userid, 'UPDATE', 'new_comments', $comment_id, "Updated invoice-related for Member ID: {$client_id}.");

    $msg .= "<div class='alert alert-success' role='alert'><b>Comment Updated Successfully</b></div>";

  } else {
    $msg .= "<div class='alert alert-danger' role='alert'><b>Error: " . mysqli_error($con) . "</b></div>";
  }
}


if (isset($_POST['delete_New_comment'])) {
  $comment_id = mysqli_real_escape_string($con, $_POST['comment_id']);

  $delete_query = "DELETE FROM new_comments WHERE comment_id = '$comment_id'";

  if (mysqli_query($con, $delete_query)) {
    // LOG: Deleted new invoice comment
    logActivity($con, $userid, 'DELETE', 'Invoice Comment', $comment_id, "Deleted invoice comment ID: {$comment_id} for Member ID: {$client_id}.");

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
  $new_id = mysqli_insert_id($con);
  logActivity($con, $uid, 'CREATE', 'member_services', $new_id, "Added a member service for Member ID: {$client_id}.");

  header("Location: client_details.php?id=$client_id&status=$status#member-services");
  exit;
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
  if (mysqli_affected_rows($con) > 0) {
    logActivity($con, $userid, 'UPDATE', 'member_services', $sid, "Updated a member service for Member ID: {$client_id}.");
  }

  header("Location: client_details.php?id=$client_id&status=$status#member-services");
  exit;
}
if (isset($_POST['delete_member_service'])) {
  $sid = (int) $_POST['service_id'];
  mysqli_query($con, "DELETE FROM member_services WHERE id=$sid AND member_id=$client_id");
  if (mysqli_affected_rows($con) > 0) {
    logActivity($con, $userid, 'DELETE', 'member_services', $sid, "Deleted member service ID: {$sid} for Member ID: {$client_id}.");
  }

  header("Location: client_details.php?id=$client_id&status=$status#member-services");
  exit;
}

/* ----------  edit ONE auto-renewed row  ---------- */
if (isset($_POST['update_auto_renewed'])) {
  $auto_id = (int) $_POST['edit_auto_id'];
  $start = mysqli_real_escape_string($con, $_POST['auto_start']);
  $exp = mysqli_real_escape_string($con, $_POST['auto_exp']);
  $cycle = (int) $_POST['auto_cycle'];
  $assigned = !empty($_POST['auto_assigned']) ? (int) $_POST['auto_assigned'] : 'NULL';

  mysqli_query(
    $con,
    "UPDATE orders_auto_renewed
           SET start_date     = '$start',
               exp_date       = '$exp',
               bil_cyc_months = $cycle,
               assign_to      = $assigned
         WHERE auto_id = $auto_id
         LIMIT 1"
  );

  logActivity(
    $con,
    $userid,
    'UPDATE',
    'orders_auto_renewed',
    $auto_id,
    "Updated auto-renewed order {$auto_id}"
  );

  header("Location: client_details.php?id=$client_id&status=$status#associated-products");
  exit;
}


if (isset($_POST['loginBankArea'])) {
  $user_bank = trim($_POST['user_bank']);
  $pass_bank = $_POST['pass_bank'];
  $bankLoginOk = false;

  $stmt = $con->prepare("SELECT user_name FROM users WHERE LOWER(user_login)=LOWER(?) AND user_password=? AND user_login_status='1' AND user_ban_status='0' LIMIT 1");
  $bank_user_name = null;

  if ($stmt) {
    $stmt->bind_param("ss", $user_bank, $pass_bank);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $stmt->bind_result($bank_user_name);
      $stmt->fetch();
      $bankLoginOk = true;
      // Set session variables for bank access
      $_SESSION['bank_auth'] = 1;
      $_SESSION['bank_user_name'] = $bank_user_name;
      $_SESSION['bank_access_time'] = time();

      // Log the activity
      logActivity($con, $userid, 'LOGIN', 'bank_statements', $client_id, "User '{$user_bank}' accessed bank statements area.");

      $stmt->close();
      // Redirect to bank statements page
      header("Location: bank_statements.php?client_id=$client_id");
      exit;
    }
    $stmt->close();
  }

if (!$bankLoginOk) {
    $msg .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
              <strong>Error!</strong> Wrong username or password. Please try again.
              <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
  }
}

function renderCommentsTabContent($con, $client_id)
{
  ob_start();
  ?>
  <div class="row">
    <div class="col-sm-12 col-md-6">
      <div class="card">
        <div class="card-body employee-detail-body fulls-card">
          <div class="row">
            <div class="col-md-7">
              <h5>Comments<?= mh_icon('comments') ?></h5>
            </div>
            <div class="col-md-5 text-right">
              <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addcomment">Add
                Comment</button>
              <button class="btn btn-secondary btn-sm" onclick="printComments()">Print</button>
            </div>
          </div>
          <hr>
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
                          AND (comment_status = '0' OR comment_status = '' OR comment_status IS NULL)
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
                      <div style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.5em;">
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
        </div>
      </div>
    </div>

    <div class="col-sm-12 col-md-6">
      <div class="card">
        <div class="card-body employee-detail-body fulls-card">
          <div class="row">
            <div class="col-md-7">
              <h5>Invoices Comments<?= mh_icon('invoice_comments') ?></h5>
            </div>
            <div class="col-md-5 text-right">
              <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addNewcomment">Add
                Comment</button>
              <button class="btn btn-secondary btn-sm" onclick="printNewComments()">Print</button>
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
                  $invoiceCommentsSql = "SELECT new_comments.*,
                          creator.user_login AS comment_by,
                          updater.user_login AS updated_by_name
                        FROM new_comments
                        LEFT JOIN users AS creator ON new_comments.user_id = creator.user_id
                        LEFT JOIN users AS updater ON new_comments.comment_updated_by = updater.user_id
                        WHERE new_comments.member_id='$client_id'
                        ORDER BY new_comments.comment_id DESC";
                  $query1 = mysqli_query($con, $invoiceCommentsSql);
                  while ($comment_row = mysqli_fetch_array($query1)) {
                    $commentdate = $comment_row['comment_time'];
                    $comment_detail = $comment_row["comment_detail"];
                    $comment_by = $comment_row["comment_by"] ?? 'Unknown';
                    $updated_by_name = $comment_row["updated_by_name"] ?? 'Unknown';
                    ?>
                    <tr>
                      <td><?php echo (($comment_row['invoice_id'] ?? 0) == 0) ? 'N/A' : htmlspecialchars($comment_row['invoice_id']); ?></td>
                      <td>
                        <?php
                        $timestamp = strtotime($commentdate);
                        echo date("m/d/Y", $timestamp) . "<br>" . date("H:i:s", $timestamp);
                        ?>
                      </td>
                      <td><?php echo htmlspecialchars($comment_by); ?></td>
                      <td style="max-width: 200px; white-space: normal; word-wrap: break-word;">
                        <div style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.5em;">
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

    <div class="col-sm-12 col-md-6 mt-3">
      <div class="card">
        <div class="card-body employee-detail-body fulls-card">
          <div class="row">
            <div class="col-md-7">
              <h5>Bank Statement Comments<?= mh_icon('bank_statement_comments') ?></h5>
            </div>
            <div class="col-md-5 text-right">
              <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addBankStatementComment">Add Comment</button>
              <button class="btn btn-secondary btn-sm" onclick="printBankStatementComments()">Print</button>
            </div>
          </div>
          <hr>
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
                $bankStatementCommentsSql = "SELECT comments.*, 
                          creator.user_login AS comment_by,
                          updater.user_login AS updated_by_name
                        FROM comments 
                        LEFT JOIN users AS creator ON comments.user_id = creator.user_id 
                        LEFT JOIN users AS updater ON comments.comment_updated_by = updater.user_id 
                        WHERE member_id='$client_id'
                          AND comment_status='bank_statement'
                        ORDER BY comment_id DESC";

                $bankStatementCommentsQuery = mysqli_query($con, $bankStatementCommentsSql);
                while ($bankCommentRow = mysqli_fetch_array($bankStatementCommentsQuery)) {
                  $commentdate = $bankCommentRow['comment_time'];
                  $comment_by = isset($bankCommentRow['comment_by']) ? $bankCommentRow['comment_by'] : 'Unknown';
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
                      <div style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.5em;">
                        <?php echo strip_tags($bankCommentRow["comment_detail"], '<p><br><b><i><strong><em>'); ?>
                      </div>
                    </td>
                    <td>
                      <button class="btn btn-sm btn-success" onclick="openViewModal(
                        '<?php echo htmlspecialchars(json_encode($comment_by), ENT_QUOTES, 'UTF-8'); ?>',
                        '<?php echo htmlspecialchars(json_encode($bankCommentRow['updated_by_name']), ENT_QUOTES, 'UTF-8'); ?>',
                        '<?php echo htmlspecialchars(json_encode($commentdate), ENT_QUOTES, 'UTF-8'); ?>',
                        '<?php echo htmlspecialchars(json_encode(strip_tags($bankCommentRow['comment_detail'], '<p><br><b><i><strong><em>')), ENT_QUOTES, 'UTF-8'); ?>'
                      )">View</button>
                      <button class="btn btn-sm btn-warning" onclick="openEditModal(
                        <?php echo $bankCommentRow['comment_id']; ?>,
                        `<?php echo addslashes(strip_tags($bankCommentRow['comment_detail'])); ?>`
                      )">Edit</button>
                      <button class="btn btn-sm btn-danger" onclick="openDeleteModal(
                        <?php echo $bankCommentRow['comment_id']; ?>
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

    <div class="col-sm-12 col-md-6">
      <div class="card">
        <div class="card-body">
          <h5>Project Comments<?= mh_icon('project_comments') ?></h5>
          <hr>
          <div class="table-responsive">
            <table id="projectCommentsTable" class="table datatable1" style="width:100%; table-layout: fixed;">
              <thead>
                <tr>
                  <th style="width: 10%;">Project ID</th>
                  <th style="width: 14%;">Date</th>
                  <th style="width: 10%;">By</th>
                  <th style="width: 51%;">Comment</th>
                  <th style="width: 15%;">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $clientId = (int) ($client_id ?? 0);
                if ($clientId) {
                  $projectSql = "SELECT pc.id,
                          pc.project_id,
                          pc.comment,
                          pc.status,
                          pc.created_at,
                          u.user_login
                    FROM project_comments pc
                    JOIN projects p ON p.order_id = pc.project_id
                    LEFT JOIN users u ON u.user_id = pc.user_id
                    WHERE p.mem_id = {$clientId}
                    ORDER BY pc.id DESC";
                  $res = mysqli_query($con, $projectSql);
                  if (mysqli_num_rows($res) === 0) {
                    echo '<tr><td colspan="5" class="text-muted text-center">No project comments yet.</td></tr>';
                  }
                  while ($row = mysqli_fetch_assoc($res)) {
                    echo '<tr>';
                    echo '<td>' . (int) $row['project_id'] . '</td>';
                    echo '<td>' . htmlspecialchars(date('m/d/Y h:i A', strtotime($row['created_at']))) . '</td>';
                    echo '<td>' . htmlspecialchars($row['user_login'] ?? 'System') . '</td>';
                    $commentRaw = strip_tags($row['comment'] ?? '');
                    $commentText = nl2br(htmlspecialchars($commentRaw));
                    $isLong = mb_strlen($commentRaw) > 120;
                    echo '<td>';
                    echo '<div class="pc-comment' . ($isLong ? ' clamped' : '') . '">' . $commentText . '</div>';
                    if ($isLong) {
                      echo '<span class="pc-toggle">See more</span>';
                    }
                    echo '</td>';
                    echo '<td>' . htmlspecialchars($row['status'] ?? 'open') . '</td>';
                    echo '</tr>';
                  }
                } else {
                  echo '<tr><td colspan="5" class="text-muted text-center">Client not selected.</td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="printableComments" style="display: none;">
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
        mysqli_data_seek($querr1, 0);
        $count = 1;
        while ($Comrow = mysqli_fetch_array($querr1)) {
          $commentdate = $Comrow['comment_time'];
          $comment_by = isset($Comrow['comment_by']) ? $Comrow['comment_by'] : 'Unknown';
          ?>
          <tr>
            <td><?php echo $count++; ?></td>
            <td><?php echo date("m/d/Y H:i:s", strtotime($commentdate)); ?></td>
            <td><?php echo htmlspecialchars($comment_by); ?></td>
            <td><?php echo strip_tags($Comrow["comment_detail"], '<p><br><b><i><strong><em>'); ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>

  <div id="printableNewComments" style="display: none;">
    <h2>All Invoice Comments</h2>
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
        mysqli_data_seek($query1, 0);
        $count = 1;
        while ($comment_row = mysqli_fetch_array($query1)) {
          $commentdate = $comment_row['comment_time'];
          $comment_detail = $comment_row['comment_detail'];
          $comment_by = $comment_row['comment_by'] ?? 'Unknown';
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

  <div id="printableBankStatementComments" style="display: none;">
    <h2>All Bank Statement Comments</h2>
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
        mysqli_data_seek($bankStatementCommentsQuery, 0);
        $count = 1;
        while ($bankCommentRow = mysqli_fetch_array($bankStatementCommentsQuery)) {
          $commentdate = $bankCommentRow['comment_time'];
          $comment_by = isset($bankCommentRow['comment_by']) ? $bankCommentRow['comment_by'] : 'Unknown';
          ?>
          <tr>
            <td><?php echo $count++; ?></td>
            <td><?php echo date('m/d/Y H:i:s', strtotime($commentdate)); ?></td>
            <td><?php echo htmlspecialchars($comment_by); ?></td>
            <td><?php echo strip_tags($bankCommentRow["comment_detail"], '<p><br><b><i><strong><em>'); ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
  <?php
  return ob_get_clean();
}

if (isset($_GET['lazy_tab']) && $_GET['lazy_tab'] === 'comments') {
  echo renderCommentsTabContent($con, $client_id);
  exit;
}

$permQuery = mysqli_query($con, "SELECT * FROM user_permissions WHERE user_id = '$userid' LIMIT 1");
$perms = mysqli_fetch_assoc($permQuery);
if (!$perms) {
  // No permissions row = deny all
  $perms = array_fill_keys(array_keys($perms ?? []), 'No');
}

$client_query = mysqli_query($con, "SELECT sms_enabled, email_enabled FROM client_button_settings WHERE member_id='$client_id'");
$client_data = mysqli_fetch_assoc($client_query);
$sms_enabled = $client_data['sms_enabled'] ?? 1;
$email_enabled = $client_data['email_enabled'] ?? 1;

// ── Dashboard Stats ──────────────────────────────────────────
$stat_active    = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM orders WHERE mem_id='$client_id' AND order_status='1'"))['c'] ?? 0);
$stat_completed = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM orders WHERE mem_id='$client_id' AND order_status='-1'"))['c'] ?? 0);
$stat_inactive  = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM orders WHERE mem_id='$client_id' AND order_status='0'"))['c'] ?? 0);
$stat_total_svc = $stat_active + $stat_completed + $stat_inactive;
$stat_projects  = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM projects WHERE mem_id='$client_id'"))['c'] ?? 0);
$stat_quotes    = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM quotations WHERE member_id='$client_id'"))['c'] ?? 0);
$stat_balance   = (float)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COALESCE(SUM(balance),0) AS b FROM billing_invoice WHERE mem_id='$client_id'"))['b'] ?? 0);
$stat_comments  = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM comments WHERE member_id='$client_id' AND (comment_status='0' OR comment_status='' OR comment_status IS NULL)"))['c'] ?? 0);

// ── Chart: services added per month (last 6 months) ──────────
$chart_labels = $chart_values = [];
for ($i = 5; $i >= 0; $i--) {
  $ym = date('Y-m', strtotime("-$i months"));
  $chart_labels[] = date('M Y', strtotime("-$i months"));
  $chart_values[] = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM orders WHERE mem_id='$client_id' AND DATE_FORMAT(start_date,'%Y-%m')='$ym'"))['c'] ?? 0);
}

// ── Chart: billing trend per month (last 6 months) ───────────
$billing_labels = $billing_total = $billing_paid = [];
for ($i = 5; $i >= 0; $i--) {
  $ym = date('Y-m', strtotime("-$i months"));
  $billing_labels[] = date('M Y', strtotime("-$i months"));
  $brow = mysqli_fetch_assoc(mysqli_query($con,
    "SELECT COALESCE(SUM(total_amount),0) AS tot, COALESCE(SUM(paid_amount),0) AS paid
     FROM billing_invoice WHERE mem_id='$client_id' AND DATE_FORMAT(due_date,'%Y-%m')='$ym'"));
  $billing_total[] = round((float)($brow['tot'] ?? 0), 2);
  $billing_paid[]  = round((float)($brow['paid'] ?? 0), 2);
}

// ── Client quick-view fields ──────────────────────────────────
$cq   = mysqli_query($con, "SELECT * FROM members WHERE member_id='$client_id' LIMIT 1");
$cmem = mysqli_fetch_assoc($cq);
$client_fullname = trim(($cmem['name'] ?? '') . ' ' . ($cmem['mname'] ?? '') . ' ' . ($cmem['lname'] ?? ''));
$client_email    = $cmem['prm_email'] ?? '';
$client_phone    = $cmem['cell_ph'] ?? '';
$client_since_display = (!empty($cmem['client_since']) && $cmem['client_since'] !== '0000-00-00')
  ? date("M d, Y", strtotime($cmem['client_since'])) : 'N/A';
$client_pic = !empty($cmem['mem_pic']) ? 'images/clients/' . $cmem['mem_pic'] : '';

// ── Email compose box: mailboxes we can send through + template library ──
$em_accounts = em_accounts($con);
$em_tpls = em_templates($con, $userid);
$em_tags = em_merge_tags();

// ── Hero card extra fields (business, category, balance) ─────
$hero_bus_name   = $cmem['bus_name']    ?? '';
$hero_bus_email  = $cmem['sec_email']   ?? '';
$hero_bus_phone  = $cmem['phone']       ?? '';
$hero_category   = $cmem['category']    ?? '';
$hero_ach_payer  = (int)($cmem['ach_payer'] ?? 0);
$hero_frequency  = $cmem['payer_frequency'] ?? '';
$hero_special    = (int)($cmem['specialClient'] ?? 0);
$hero_type_id    = (int)($cmem['type'] ?? 0);
$hero_type_name  = '';
if ($hero_type_id) {
  $_hr = mysqli_fetch_assoc(mysqli_query($con, "SELECT type_name FROM member_type WHERE type_id='$hero_type_id' LIMIT 1"));
  $hero_type_name = $_hr['type_name'] ?? '';
}
$hero_unpaid_row = mysqli_fetch_assoc(mysqli_query($con, "SELECT COALESCE(SUM(balance),0) AS b FROM billing_invoice WHERE mem_id='$client_id'"));
$hero_unpaid     = (float)($hero_unpaid_row['b'] ?? 0);

// ── Discounts (hero) ────────────────────────────────────────────
// Total discount given to this client across all invoices that
// carried a discount, plus the list of those invoices for the popup.
$hero_discount_total = 0.0;
$hero_discount_invoices = array();
$_diq = mysqli_query($con, "SELECT invoice_id, invoice_date, due_date, total_amount, discount, balance, filelink
                            FROM billing_invoice
                            WHERE mem_id='$client_id' AND discount IS NOT NULL AND discount > 0
                            ORDER BY invoice_id DESC");
if ($_diq) {
  while ($_drow = mysqli_fetch_assoc($_diq)) {
    $hero_discount_total += (float)$_drow['discount'];
    $hero_discount_invoices[] = $_drow;
  }
}

// ── Detail-card helper values ────────────────────────────────
$hero_dob_raw  = $cmem['dob'] ?? '';
$hero_dob_disp = (empty($hero_dob_raw) || $hero_dob_raw === '0000-00-00')
  ? '' : date('m/d/Y', strtotime($hero_dob_raw));
$hero_ssn      = $cmem['ss'] ?? '';
$hero_added_id = (int)($cmem['added_by'] ?? 0);
$hero_upd_id   = (int)($cmem['user_id']  ?? 0);
$hero_added_name = $hero_upd_name = '';
if ($hero_added_id) {
  $_u = mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id='$hero_added_id' LIMIT 1"));
  $hero_added_name = $_u['user_name'] ?? '';
}
if ($hero_upd_id) {
  $_u = mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id='$hero_upd_id' LIMIT 1"));
  $hero_upd_name = $_u['user_name'] ?? '';
}
$hero_home_addr = $cmem['hstreet']  ?? '';
$hero_home_city = $cmem['hcity']    ?? '';
$hero_home_state= $cmem['hstate']   ?? '';
$hero_home_zip  = $cmem['hzip']     ?? '';
$hero_home_cty  = $cmem['hcounty']  ?? '';
$hero_bus_start = (!empty($cmem['bus_start_date']) && $cmem['bus_start_date'] !== '0000-00-00')
  ? date('m/d/Y', strtotime($cmem['bus_start_date'])) : '';
$hero_bus_incorp= (!empty($cmem['incorp_date']) && $cmem['incorp_date'] !== '0000-00-00')
  ? date('m/d/Y', strtotime($cmem['incorp_date'])) : '';
$hero_designation = $cmem['member_pos'] ?? '';
$hero_file = $cmem['file']    ?? '';
$hero_ein  = $cmem['ein']     ?? '';
$hero_dba  = $cmem['dba']     ?? '';
$hero_pmb  = $cmem['tf']      ?? '';
$hero_other_ph    = $cmem['other_ph']    ?? '';
$hero_overseas_ph = $cmem['overseas_ph'] ?? '';
$hero_bcounty     = $cmem['bcounty']     ?? '';
$hero_bus_addr    = $cmem['address']     ?? '';
$hero_bus_city    = $cmem['city']        ?? '';
$hero_bus_state   = $cmem['state']       ?? '';
$hero_bus_zip     = $cmem['zip']         ?? '';

// Top provided services — top 3 invoiced items for this member (by frequency)
$hero_featured = [];
$_fs = mysqli_query($con, "SELECT TRIM(item_description) AS svc, COUNT(*) AS cnt
                           FROM billing_invoice_items
                           WHERE mem_id='$client_id' AND TRIM(item_description) <> ''
                           GROUP BY TRIM(item_description)
                           ORDER BY cnt DESC, svc ASC
                           LIMIT 3");
if ($_fs) {
  while ($_r = mysqli_fetch_assoc($_fs)) {
    if (!empty($_r['svc'])) $hero_featured[] = $_r['svc'];
  }
}

?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
  <title>Syed Professional Services - Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

  <?php include "includes/links.php"; ?>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <style type="text/css">
    /* ── Base ─────────────────────────────────────────────────── */
    :root {
      --brand:       #272469;
      --brand-dark:  #1f1d54;
      --brand-light: rgba(39,36,105,.12);
      --blue:        #4361ee;
      --purple:      #7c3aed;
      --orange:      #f97316;
      --red:         #ef4444;
      --teal:        #0d9488;
      --card-radius: 14px;
      --card-shadow: 0 4px 24px rgba(0,0,0,.07);
    }

    /* Page-scoped override: this page uses navy as its brand accent instead of the sitewide green */
    body.theme-3 .btn-primary {
      --bs-btn-bg: #272469 !important;
      --bs-btn-border-color: #272469 !important;
      --bs-btn-hover-bg: #1f1d54 !important;
      --bs-btn-hover-border-color: #1f1d54 !important;
      --bs-btn-active-bg: #1a1845 !important;
      --bs-btn-active-border-color: #1a1845 !important;
      --bs-btn-disabled-bg: #272469 !important;
      --bs-btn-disabled-border-color: #272469 !important;
    }
    body.theme-3 .btn-outline-primary {
      --bs-btn-color: #272469 !important;
      --bs-btn-border-color: #272469 !important;
      --bs-btn-hover-bg: #272469 !important;
      --bs-btn-hover-border-color: #272469 !important;
    }
    body.theme-3 .modal-header.bg-primary {
      background: #272469 !important;
    }

    .text-sm { font-size: 13px !important; margin-bottom: 5px; }

    .swal2-container.swal2-top-end>.swal2-popup {
      margin-top: 40px !important;
    }

    div.text { white-space: nowrap; overflow: hidden; text-overflow: "----"; }
    .form-label { margin-bottom: .5rem; margin-top: 15px; }
    #box { display: block; }

    /* ── Client Hero (PDF-style redesign) ───────────────────── */
    .client-hero {
      background: #132748;
      border-radius: 18px;
      margin-bottom: 24px;
      color: #fff;
      box-shadow: 0 14px 34px rgba(15, 23, 42, .16);
      overflow: hidden;
      position: relative;
    }
    /* Top action bar (toggles + buttons) */
    .client-hero-topbar {
      display: flex;
      flex-wrap: wrap;
      gap: 12px 14px;
      align-items: center;
      justify-content: space-evenly;
      padding: 16px 18px;
      background: linear-gradient(180deg, #737587 0%, #67697b 100%);
    }
    .client-hero-topbar > * { flex: 0 0 auto; }
    .client-hero-topbar .form-switch {
      margin: 0;
      padding-left: 0;
      min-height: auto;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .client-hero-topbar .form-check-input {
      cursor: pointer;
      width: 54px;
      height: 26px;
      margin: 0;
      border: none;
      border-radius: 999px;
      box-shadow: none;
      background-color: #ffffff;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3E%3Ccircle r='3' fill='%232f2e82'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-size: 18px 18px;
      background-position: 4px center;
      transition: background-position .2s ease, filter .2s ease;
    }
    .client-hero-topbar .form-check-input:checked {
      background-color: #ffffff;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3E%3Ccircle r='3' fill='%2316a34a'/%3E%3C/svg%3E");
      background-position: calc(100% - 4px) center;
    }
    .client-hero-topbar .form-check-input:focus { box-shadow: 0 0 0 .15rem rgba(255,255,255,.24); }
    .client-hero-topbar .form-check-input.me-2 { margin-right: 0 !important; }
    .client-hero-topbar .form-check-label,
    .client-hero-topbar .form-check-input + span {
      font-size: 1.02rem;
      font-weight: 700;
      color: #fff;
      letter-spacing: .01em;
    }

    .client-hero-main {
      padding: 28px 36px 32px;
      background: linear-gradient(90deg, #1a1d3d 0%, #14396a 100%);
    }

    /* Consistent pill action button (matches toolbar mockup) */
    .ctb {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 0 16px;
      height: 50px;
      border-radius: 0;
      font-size: .98rem;
      font-weight: 700;
      border: none;
      cursor: pointer;
      line-height: 1;
      text-decoration: none;
      transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
      white-space: nowrap;
      box-shadow: inset 0 0 0 1px rgba(255,255,255,.04);
    }
    .ctb:hover  { transform: translateY(-1px); filter: brightness(1.05); color: inherit; }
    .ctb:disabled { opacity: .55; cursor: not-allowed; transform: none; filter: none; }
    .ctb i { font-size: 2rem; }

    /* Colour variants — navy pill with GOLD icon for all action buttons,
       white pill for Calendar, red pill for Delete. */
    .ctb-navy,
    .ctb-alert,
    .ctb-green,
    .ctb-white {
      background: #2e2b7f;
      color: #fff;
    }
    .ctb-navy:hover,
    .ctb-alert:hover,
    .ctb-green:hover,
    .ctb-white:hover { color:#fff; }
    .ctb-navy i,
    .ctb-alert i,
    .ctb-green i,
    .ctb-white i { color: #e2bd44; }

    .ctb-calendar {
      background: transparent;
      color: #fff;
      padding: 0 4px;
      min-width: 36px;
      box-shadow: none;
    }
    .ctb-calendar:hover {
      color:#fff;
      background: transparent;
    }
    .ctb-calendar i {
      color: #fff;
      font-size: 1.75rem;
    }

    .ctb-red    { background: #dd2d19; color: #fff; }
    .ctb-red:hover { color:#fff; }
    .ctb-red   i { color: #fff; }

    /* Body: two-column person + business */
    .client-hero-body {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
      gap: 34px;
      align-items: start;
    }
    .ch-block {
      display: flex;
      align-items: flex-start;
      gap: 18px;
      min-width: 0;
    }
    .ch-block-body { flex: 1; min-width: 0; padding-top: 2px; }

    /* Gold/yellow circular avatar */
    .ch-avatar {
      width: 86px; height: 86px;
      border-radius: 50%;
      background: linear-gradient(135deg, #dcbf48 0%, #c6a02d 100%);
      display: flex; align-items: center; justify-content: center;
      color: #fff;
      font-size: 2.4rem;
      flex-shrink: 0;
      box-shadow: 0 10px 18px rgba(0,0,0,.18);
      overflow: hidden;
    }
    .ch-avatar img { width:100%; height:100%; object-fit:cover; border-radius:50%; }

    .ch-name {
      font-size: clamp(1.65rem, 2.3vw, 2.2rem);
      font-weight: 800;
      color: #fff;
      margin: 0 0 8px;
      line-height: 1.18;
      word-break: break-word;
    }
    .ch-line {
      display: flex;
      flex-wrap: wrap;
      gap: 6px 18px;
      font-size: 1.02rem;
      color: rgba(255,255,255,.96);
      margin-bottom: 5px;
    }
    .ch-line span { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
    .ch-line i { opacity: .96; font-size: 1rem; flex-shrink: 0; }
    /* Business category: header is a toggle, activity/NAICS shown on click */
    .ch-line .ch-cat {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 4px 8px;
      max-width: 100%;
    }
    .ch-cat-toggle {
      background: none;
      border: 0;
      padding: 0;
      color: inherit;
      font: inherit;
      text-align: left;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: flex-start;
      gap: 6px;
      white-space: nowrap;
    }
    .ch-cat-toggle:hover { text-decoration: underline; }
    .ch-cat-toggle i { font-size: .9rem; transition: transform .18s ease; }
    .ch-cat-toggle[aria-expanded="true"] i { transform: rotate(180deg); }
    .ch-line .ch-cat-sub { display: none; opacity: .9; }
    /* Activity/NAICS drops to its own row under the header and wraps onto
       as many lines as it needs, held to a readable column width. */
    .ch-line .ch-cat.is-open .ch-cat-sub {
      display: block;
      flex: 1 1 100%;
      max-width: 340px;
      margin-left: 24px;
      font-size: .92rem;
      line-height: 1.35;
      white-space: normal;
      overflow-wrap: anywhere;
    }
    .ch-id-badge {
      background: transparent;
      padding: 0;
      border-radius: 0;
      font-size: inherit;
      font-weight: 400;
      letter-spacing: 0;
    }

    /* Pill row */
    .ch-pills-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 18px;
    }
    .ch-pill {
      padding: 6px 14px;
      border-radius: 999px;
      font-size: .95rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      line-height: 1;
      border: 2px solid transparent;
      background: rgba(255,255,255,.08);
      color: #fff;
    }
    .ch-pill i { font-size: .82rem; }
    .ch-pill.status-active   { background: rgba(14,159,66,.16); color: #fff; border-color: #18d74f; }
    .ch-pill.status-inactive { background: rgba(220,38,38,.14); color: #fff; border-color: #ef4444; }
    .ch-pill.ach             { background: rgba(212,171,50,.14); color: #fff; border-color: #c89d16; }
    .ch-pill.freq            { background: rgba(114,98,198,.22); color: #fff; border-color: #d7d1ff; }
    .ch-pill.balance         { background: rgba(220,38,38,.14); color: #fff; border-color: #ff1616; }
    .ch-pill.balance.is-zero { background: rgba(255,255,255,.12); color: #e2e8f0; border-color: rgba(255,255,255,.24); }
    a.ch-pill.balance        { text-decoration: none; cursor: pointer; transition: background .15s ease, transform .15s ease; }
    a.ch-pill.balance:hover  { background: rgba(220,38,38,.28); transform: translateY(-1px); }
    .ch-pill.discount        { background: linear-gradient(135deg, rgba(16,185,129,.18), rgba(16,185,129,.06)); color: #a7f3d0; border-color: #10b981; }
    a.ch-pill.discount       { text-decoration: none; cursor: pointer; transition: background .18s ease, transform .18s ease, box-shadow .18s ease; }
    a.ch-pill.discount:hover { background: linear-gradient(135deg, rgba(16,185,129,.32), rgba(16,185,129,.12)); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(16,185,129,.28); }
    .ch-pill.special         { background: rgba(124,58,237,.22); color: #fff; border-color: #b697ff; }

    /* ── Client Discounts modal ──────────────────────────────── */
    #clientDiscountsModal .modal-content {
      border-radius: 16px;
      border: 0;
      overflow: hidden;
      box-shadow: 0 22px 60px rgba(5,15,40,.35);
    }
    #clientDiscountsModal .modal-header {
      background: linear-gradient(135deg, #10b981, #0e9f73);
      color: #fff;
      border: 0;
      padding: 16px 22px;
    }
    #clientDiscountsModal .modal-header .modal-title { font-weight: 800; }
    #clientDiscountsModal .modal-header .btn-close { filter: invert(1); opacity: .9; }
    #clientDiscountsModal .modal-body { padding: 20px 22px; }
    #clientDiscountsModal .modal-footer { border: 0; padding: 12px 22px; }
    .discount-summary {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 18px;
      border-radius: 12px;
      background: linear-gradient(135deg, rgba(16,185,129,.13), rgba(16,185,129,.04));
      border: 1px solid rgba(16,185,129,.25);
    }
    .discount-summary > i.fa-percent {
      font-size: 1.6rem;
      color: #10b981;
      background: rgba(16,185,129,.15);
      width: 46px; height: 46px;
      display: inline-flex; align-items: center; justify-content: center;
      border-radius: 12px;
    }
    .discount-summary .label { display: block; font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
    .discount-summary .value { display: block; font-size: 1.45rem; font-weight: 800; color: #0f9d72; }
    .discount-inv-table { font-size: .93rem; }
    .discount-inv-table thead th {
      font-size: .72rem; text-transform: uppercase; letter-spacing: .04em;
      color: #f97316; font-weight: 800;
      border-bottom: 2px solid #fee2d5;
    }
    .discount-inv-table tbody tr { transition: background .12s ease; }
    .discount-inv-table tbody tr:hover { background: rgba(16,185,129,.05); }
    .disc-inv-id { font-weight: 700; color: #0f172a; }
    .disc-amount { color: #10b981; font-weight: 700; }
    .disc-balance-pos { color: #ef4444; font-weight: 700; }
    .view-inv-btn {
      display: inline-flex; align-items: center;
      padding: 6px 13px;
      border-radius: 999px;
      font-size: .8rem; font-weight: 700;
      color: #fff;
      background: linear-gradient(135deg, #f97316, #ea5207);
      text-decoration: none;
      transition: transform .15s ease, box-shadow .15s ease;
    }
    .view-inv-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(249,115,22,.35); color: #fff; }

    /* ── Horizontal tab strip above the hero ─────────────────── */
    .ch-tab-strip {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 10px;
      margin: 8px 0 18px;
      flex-wrap: wrap;
    }
    .ch-tab {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: #fff;
      border: 1px solid #d9dceb;
      border-radius: 999px;
      padding: 0 18px;
      color: #223067;
      text-decoration: none;
      font-size: 1rem;
      font-weight: 700;
      box-shadow: 0 4px 10px rgba(17, 24, 39, .16);
      transition: all .2s;
      cursor: pointer;
      height: 52px;
      min-width: 52px;
      justify-content: center;
    }
    .ch-tab i { font-size: 1.35rem; }
    .ch-tab:hover {
      border-color: #b7bdd6;
      color: #1f2a60;
      transform: translateY(-1px);
    }
    .ch-tab.is-icon {
      padding: 0;
      width: 52px;
      border-radius: 50%;
    }
    .ch-tab.is-icon i { color: #d1af3e; }
    .ch-tab.is-active {
      background: linear-gradient(135deg, #2e2b7f, #252261);
      color: #fff;
      border-color: transparent;
      padding: 0 24px;
    }
    .ch-tab.is-active i { color: #d1af3e; }

    /* ── Floating behaviour on scroll ─────────────────────────
       Once scrolled past its normal spot, the strip becomes fixed
       and fades out; moving the cursor reveals it again for 3s.  */
    .ch-tab-strip-placeholder { height: 0; }
    .ch-tab-strip.ch-floating {
      position: fixed;
      top: 20px;
      margin: 0;
      z-index: 1020;
      opacity: 1;
      pointer-events: auto;
      transition: opacity .3s ease;
      background: #fff;
      padding: 8px 14px;
      box-shadow: 0 8px 24px rgba(17, 24, 39, .2);
    }
    .ch-tab-strip.ch-floating.ch-hidden {
      opacity: 0;
      pointer-events: none;
    }

    /* ── Two white detail cards inside the hero ─────────────── */
    .ch-details-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
      gap: 22px;
      margin-top: 28px;
    }
    .ch-detail-card {
      background: #fff;
      border-radius: 15px;
      padding: 20px 22px 16px;
      color: #10223d;
      box-shadow: 0 10px 26px rgba(0,0,0,.12);
    }
    .ch-detail-inner {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
      gap: 8px 34px;
    }
    .ch-detail-section-title {
      color: #24357d;
      font-weight: 800;
      font-size: clamp(1.2rem, 2.3vw, 1.85rem);
      margin: 0 0 10px;
      line-height: 1.15;
    }
    .ch-detail-field {
      font-size: 1.02rem;
      margin-bottom: 6px;
      line-height: 1.5;
      color: #0f172a;
      word-break: break-word;
    }
    .ch-detail-field b { color: #020617; font-weight: 800; margin-right: 4px; }
    .ch-detail-field .fv-empty { color: #94a3b8; font-style: italic; }
    .ch-featured-block { margin-top: 10px; }
    .ch-featured-list {
      list-style: disc;
      padding-left: 22px;
      margin: 2px 0 0;
      font-size: 1.02rem;
      color: #0f172a;
    }
    .ch-featured-list li { margin-bottom: 4px; }

    /* Alerts row (Alerts + ACH Alerts) — sits below the detail cards in the hero */
    .ch-alerts-row { margin-top: 22px; }
    .ch-alert-head {
      display: flex; align-items: center; justify-content: space-between;
      gap: 10px; margin-bottom: 12px;
    }
    .ch-alert-head .ch-detail-section-title { margin-bottom: 0; }
    .ch-count-badge {
      background: #f0ad4e; color: #3d2a00; font-weight: 800;
      border-radius: 999px; padding: 3px 12px; font-size: .85rem; white-space: nowrap;
    }
    .ch-count-badge.ch-badge-primary { background: #24357d; color: #fff; }
    .ch-alert-body { max-height: 360px; overflow-y: auto; }
    .ch-alert-body .alert { margin-bottom: 8px; }
    .ch-alert-empty { color: #64748b; font-style: italic; }

    /* ACH alerts shown as compact one-line rows; full details open in a modal */
    .ch-ach-list { display: flex; flex-direction: column; gap: 8px; }
    .ch-ach-item {
      display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
      background: #fff; border: 1px solid #e6eaf0; border-left: 4px solid #f0ad4e;
      border-radius: 10px; padding: 8px 12px;
    }
    .ch-ach-item.is-collected { border-left-color: #198754; }
    .ch-ach-item.is-cancelled { border-left-color: #cbd5e1; opacity: .75; }
    .ch-ach-amt { font-size: 1.15rem; font-weight: 800; color: #10223d; line-height: 1; }
    .ch-ach-id { color: #94a3b8; font-size: .8rem; font-weight: 700; }
    .ch-ach-due { color: #475569; font-size: .85rem; white-space: nowrap; }
    .ch-ach-due i { color: #94a3b8; margin-right: 5px; }
    .ch-ach-due b { color: #10223d; font-weight: 700; }
    .ch-ach-actions { margin-left: auto; display: inline-flex; gap: 5px; }

    /* ACH detail modal */
    .ach-detail-row { display: flex; justify-content: space-between; gap: 16px; padding: 9px 0; border-bottom: 1px solid #eef1f6; }
    .ach-detail-row:last-child { border-bottom: 0; }
    .ach-detail-row .lbl { color: #64748b; font-weight: 600; }
    .ach-detail-row .val { color: #10223d; font-weight: 700; text-align: right; white-space: pre-wrap; }

    @media (max-width: 992px) {
      .client-hero-main { padding: 24px 22px 26px; }
      .client-hero-body,
      .ch-details-row { grid-template-columns: 1fr; gap: 22px; }
    }

    @media (max-width: 640px) {
      .client-hero-topbar { gap: 10px; padding: 14px 14px 16px; }
      .client-hero-main { padding: 22px 16px 22px; }
      .ch-avatar { width: 74px; height: 74px; font-size: 2rem; }
      .ch-name { font-size: 1.55rem; }
      .ch-line,
      .ch-detail-field,
      .ch-featured-list { font-size: .95rem; }
      .ch-detail-inner { grid-template-columns: 1fr; gap: 8px 16px; }
    }

    /* Legacy helpers kept for back-compat */
    .client-hero-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .hero-badge {
      padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;
      display: inline-flex; align-items: center; gap: 5px;
    }
    .hero-badge.active   { background: rgba(111,217,67,.2); color: #6FD943; border:1px solid rgba(111,217,67,.4); }
    .hero-badge.inactive { background: rgba(239,68,68,.2);  color: #fca5a5; border:1px solid rgba(239,68,68,.4); }
    .hero-badge.info     { background: rgba(67,97,238,.2);  color: #93c5fd; border:1px solid rgba(67,97,238,.4); }

    /* ── Stat Cards ──────────────────────────────────────────── */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-card {
      border-radius: var(--card-radius);
      padding: 20px 18px;
      color: #fff;
      position: relative;
      overflow: hidden;
      box-shadow: var(--card-shadow);
      transition: transform .2s;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-card::before {
      content: '';
      position: absolute;
      top: -20px; right: -20px;
      width: 80px; height: 80px;
      border-radius: 50%;
      background: rgba(255,255,255,.1);
    }
    .stat-card .s-icon { font-size: 1.6rem; margin-bottom: 8px; opacity: .85; }
    .stat-card .s-value { font-size: 2rem; font-weight: 800; line-height: 1; }
    .stat-card .s-label { font-size: 0.78rem; opacity: .85; margin-top: 4px; font-weight: 500; }
    .sc-green  { background: linear-gradient(135deg, #6FD943, #55b432); }
    .sc-blue   { background: linear-gradient(135deg, #4361ee, #3a0ca3); }
    .sc-orange { background: linear-gradient(135deg, #f97316, #ea580c); }
    .sc-red    { background: linear-gradient(135deg, #ef4444, #b91c1c); }
    .sc-teal   { background: linear-gradient(135deg, #0d9488, #0f766e); }
    .sc-purple { background: linear-gradient(135deg, #7c3aed, #5b21b6); }

    /* ── Charts Row ──────────────────────────────────────────── */
    .charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
    @media (max-width:900px) { .charts-row { grid-template-columns: 1fr; } }
    .chart-card {
      background: #fff;
      border-radius: var(--card-radius);
      box-shadow: var(--card-shadow);
      padding: 20px 22px;
    }
    .chart-card h6 {
      font-size: .85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #6c757d;
      margin-bottom: 16px;
    }

    /* ── Section Headings (collapsible) ─────────────────────── */
    .section-heading {
      display: flex; align-items: center; gap: 10px;
      margin: 28px 0 0;
      padding: 12px 16px;
      background: #fff;
      border-radius: var(--card-radius) var(--card-radius) 0 0;
      border: 1px solid #eef0f4;
      border-bottom: 2px solid var(--brand);
      cursor: pointer;
      user-select: none;
      transition: background .15s;
      position: relative;
    }
    .section-heading:hover { background: var(--brand-light); }
    .section-heading .sh-icon {
      width: 32px; height: 32px;
      border-radius: 8px;
      background: var(--brand-light);
      color: var(--brand-dark);
      display: flex; align-items: center; justify-content: center;
      font-size: .9rem;
      flex-shrink: 0;
    }
    .section-heading h4 {
      margin: 0;
      font-size: .92rem;
      font-weight: 700;
      color: #1a1a2e;
    }
    .section-heading .sh-count {
      font-size: .72rem;
      background: #e9ecef;
      color: #6c757d;
      padding: 2px 8px;
      border-radius: 12px;
      font-weight: 600;
      margin-left: 4px;
    }
    .section-heading .sh-line {
      flex: 1;
    }
    .section-heading .sh-toggle {
      width: 26px; height: 26px;
      border-radius: 50%;
      background: var(--brand-light);
      color: var(--brand-dark);
      border: none;
      display: flex; align-items: center; justify-content: center;
      font-size: .7rem;
      flex-shrink: 0;
      transition: transform .25s;
      cursor: pointer;
      padding: 0;
    }
    .section-heading.collapsed .sh-toggle { transform: rotate(-90deg); }
    /* Section body */
    .section-body {
      overflow: hidden;
      transition: max-height .3s ease, opacity .25s ease, padding .25s ease;
      max-height: 9999px;
      opacity: 1;
      padding: 14px 0 4px;
      border: 1px solid #eef0f4;
      border-top: none;
      border-radius: 0 0 var(--card-radius) var(--card-radius);
      background: transparent;
    }
    .section-body.collapsed {
      max-height: 0 !important;
      opacity: 0;
      padding: 0;
      border: none;
    }

    /* ── Back to Top ─────────────────────────────────────────── */
    #backToTop {
      position: fixed;
      bottom: 28px; right: 16px;
      z-index: 1100;
      width: 38px; height: 38px;
      border-radius: 50%;
      background: var(--brand);
      color: #fff;
      border: none;
      box-shadow: 0 4px 12px rgba(111,217,67,.4);
      font-size: .85rem;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      opacity: 0;
      pointer-events: none;
      transition: opacity .25s, transform .25s;
      transform: translateY(10px);
    }
    #backToTop.visible { opacity: 1; pointer-events: auto; transform: translateY(0); }

    /* ── Collapse All bar ────────────────────────────────────── */
    #sectionControls {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      margin-bottom: 8px;
    }
    #sectionControls button {
      background: #fff;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      padding: 5px 14px;
      font-size: .78rem;
      font-weight: 600;
      color: #555;
      cursor: pointer;
      transition: all .15s;
    }
    #sectionControls button:hover { background: var(--brand-light); border-color: var(--brand); color: var(--brand-dark); }

    /* ── Cards ───────────────────────────────────────────────── */
    .card {
      border: none;
      border-radius: var(--card-radius);
      box-shadow: var(--card-shadow);
      transition: box-shadow .2s;
      min-width: 0;        /* prevent flex children from overflowing */
      overflow: hidden;
    }
    .card:hover { box-shadow: 0 8px 32px rgba(0,0,0,.1); }
    .card-header-pro {
      padding: 16px 20px;
      border-bottom: 1px solid #f0f2f5;
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 8px;
    }
    .card-header-pro h5 {
      margin: 0;
      font-size: .95rem;
      font-weight: 700;
      color: #1a1a2e;
      display: flex; align-items: center; gap: 8px;
    }
    .card-header-pro h5 i { color: var(--brand-dark); }

    /* ── Info Field Rows ─────────────────────────────────────── */
    .info-row { display: flex; align-items: baseline; gap: 6px; padding: 6px 0; border-bottom: 1px solid #f8f8f8; font-size: .85rem; }
    .info-row:last-child { border-bottom: none; }
    .info-row strong { color: #555; min-width: 130px; flex-shrink: 0; font-weight: 600; }
    .info-row span   { color: #222; }

    /* ── Table Enhancements ──────────────────────────────────── */
    .table thead th {
      background: #f8f9ff;
      font-size: .78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #6c757d;
      border-bottom: 2px solid #e9ecef;
      padding: 10px 12px;
    }
    .table tbody td { vertical-align: middle; font-size: .85rem; padding: 9px 12px; }
    .table.datatable1 tbody tr:hover { background-color: #f8fff4 !important; transition: background .15s; }
    /* Project Comments: keep rows aligned and clamp long comments */
    #projectCommentsTable tbody td { vertical-align: top; }
    .pc-comment { white-space: normal; overflow-wrap: break-word; word-wrap: break-word; line-height: 1.5em; }
    .pc-comment.clamped { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .pc-toggle { font-size: .75rem; font-weight: 600; color: #2e7d32; cursor: pointer; display: inline-block; margin-top: 4px; }
    .pc-toggle:hover { text-decoration: underline; }
    .status-pill {
      padding: 3px 10px;
      border-radius: 12px;
      font-size: .72rem;
      font-weight: 700;
      display: inline-block;
    }
    .pill-active   { background: rgba(111,217,67,.15); color: #2e7d32; }
    .pill-inactive { background: rgba(239,68,68,.12); color: #b91c1c; }
    .pill-done     { background: rgba(67,97,238,.12); color: #1d4ed8; }
    .pill-auto     { background: rgba(13,148,136,.12); color: #0f766e; }

    /* ── Auto-renewed row treatment ──────────────────────────── */
    .row-auto-renewed > td {
      background: #f1fafb;
      color: #334155;
      position: relative;
    }
    .row-auto-renewed > td:first-child {
      box-shadow: inset 3px 0 0 #17a2b8;
    }
    .row-auto-renewed:hover > td { background: #e6f5f7; }
    .row-auto-renewed .auto-seq {
      display: inline-block;
      margin-left: 6px;
      padding: 2px 8px;
      font-size: .68rem;
      font-weight: 700;
      letter-spacing: .02em;
      border-radius: 10px;
      background: rgba(23,162,184,.12);
      color: #0f766e;
      vertical-align: middle;
    }
    /* Per-row chevron toggle for auto-renewal children */
    .ar-toggle {
      width: 22px; height: 22px;
      border: 1px solid #cbd5e1;
      background: #fff;
      border-radius: 6px;
      font-size: .65rem;
      color: #475569;
      cursor: pointer;
      margin-right: 6px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: transform .2s ease, background .2s, border-color .2s, color .2s;
      vertical-align: middle;
    }
    .ar-toggle:hover { background: #f1fafb; border-color: #17a2b8; color: #17a2b8; }
    .ar-toggle.expanded {
      transform: rotate(90deg);
      background: #f1fafb;
      border-color: #17a2b8;
      color: #17a2b8;
    }
    .ar-count {
      font-size: .7rem;
      color: #64748b;
      margin-left: 6px;
      font-weight: 500;
      vertical-align: middle;
    }

    /* ── Action Buttons ──────────────────────────────────────── */
    .btn-action { border-radius: 8px; font-size: .78rem; font-weight: 600; padding: 5px 12px; }

    /* ── Responsive ──────────────────────────────────────────── */
    @media (max-width: 768px) {
      .stats-row { grid-template-columns: repeat(2, 1fr); }
      .client-hero { padding: 20px; }
      .client-hero-name { font-size: 1.2rem; }
      .nav-tabs .nav-link { padding: 10px 12px; font-size: .78rem; }
    }
    @media (max-width: 480px) {
      .stats-row { grid-template-columns: 1fr 1fr; }
    }

    /* ══════════════════════════════════════════════════════
       PERSONAL DETAILS SECTION
    ══════════════════════════════════════════════════════ */
    /* Profile banner */
    .pd-banner {
      display: flex;
      align-items: center;
      gap: 18px;
      background: linear-gradient(135deg,#1a1a2e 0%,#0f3460 100%);
      border-radius: var(--card-radius);
      padding: 20px 24px;
      color: #fff;
    }
    .pd-avatar {
      flex-shrink: 0;
      width: 80px; height: 80px;
      border-radius: 50%;
      overflow: hidden;
      border: 3px solid var(--brand);
      background: rgba(255,255,255,.1);
      display: flex; align-items: center; justify-content: center;
    }
    .pd-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .pd-initials { font-size: 1.6rem; font-weight: 700; color: var(--brand); }
    .pd-banner-info { flex: 1; min-width: 0; }
    .pd-banner-name { font-size: 1.2rem; font-weight: 700; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pd-banner-meta { display: flex; flex-wrap: wrap; gap: 10px 18px; font-size: .8rem; opacity: .85; }
    .pd-banner-meta span { display: flex; align-items: center; gap: 5px; }
    .pd-banner-meta i { color: var(--brand); }
    .pd-banner-actions { flex-shrink: 0; }

    /* Cards */
    .pd-card { border: none; border-radius: 0; box-shadow: var(--card-shadow); overflow: hidden; }
    .pd-card-header {
      background: linear-gradient(135deg,#1a1a2e,#0f3460);
      color: #fff;
      padding: 12px 18px;
      font-size: .85rem;
      font-weight: 600;
      letter-spacing: .03em;
      display: flex; align-items: center; gap: 8px;
    }
    .pd-card-header i { color: var(--brand); font-size: .9rem; }
    .pd-card-body { padding: 16px 18px; }

    /* Group labels */
    .pd-group-label {
      font-size: .7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--brand);
      padding: 6px 0 4px;
      border-bottom: 1px solid rgba(111,217,67,.2);
      margin-bottom: 6px;
      display: flex; align-items: center; gap: 6px;
    }
    .pd-group-label i { font-size: .75rem; }

    /* Field rows */
    .pd-field {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      padding: 5px 0;
      border-bottom: 1px solid #f0f0f0;
      gap: 10px;
      font-size: .83rem;
    }
    .pd-field:last-child { border-bottom: none; }
    .pd-label {
      color: #6c757d;
      font-weight: 500;
      white-space: nowrap;
      flex-shrink: 0;
      min-width: 110px;
    }
    .pd-value {
      color: #1a1a2e;
      text-align: right;
      word-break: break-word;
    }

    /* Associated businesses list */
    .pd-assoc-list { display: flex; flex-direction: column; gap: 4px; margin-top: 4px; }
    .pd-assoc-item {
      display: flex; align-items: center; gap: 7px;
      font-size: .8rem;
      color: var(--brand-dark);
      text-decoration: none;
      padding: 5px 8px;
      border-radius: 6px;
      background: rgba(111,217,67,.07);
      transition: background .15s;
    }
    .pd-assoc-item:hover { background: rgba(111,217,67,.18); color: var(--brand-dark); }
    .pd-assoc-item i { color: var(--brand); font-size: .78rem; }

    @media (max-width: 576px) {
      .pd-banner { flex-direction: column; text-align: center; }
      .pd-banner-meta { justify-content: center; }
      .pd-label { min-width: 90px; }
    }

    /* ── Reminder drawer ───────────────────────────────────────── */
    .rd-backdrop {
      position: fixed; inset: 0;
      background: rgba(17, 24, 39, .45);
      backdrop-filter: blur(2px);
      opacity: 0; visibility: hidden;
      transition: opacity .25s ease, visibility .25s ease;
      z-index: 1080;
    }
    .rd-backdrop.show { opacity: 1; visibility: visible; }

    .rd-drawer {
      position: fixed; top: 0; right: 0; bottom: 0;
      width: 420px; max-width: 100vw;
      background: #fff;
      box-shadow: -10px 0 30px rgba(0,0,0,.18);
      transform: translateX(100%);
      transition: transform .3s ease;
      z-index: 1090;
      display: flex; flex-direction: column;
    }
    .rd-drawer.show { transform: translateX(0); }

    .rd-header {
      padding: 14px 18px;
      background: linear-gradient(135deg, #272469 0%, #1f1d54 100%);
      color: #fff;
      display: flex; align-items: center; justify-content: space-between;
      gap: 10px;
    }
    .rd-header h5 { margin: 0; font-size: 1rem; font-weight: 600; }
    .rd-header small { opacity: .85; }
    .rd-close {
      background: rgba(255,255,255,.18);
      border: 0; color: #fff;
      width: 30px; height: 30px; border-radius: 50%;
      cursor: pointer; line-height: 1;
      transition: background .15s;
    }
    .rd-close:hover { background: rgba(255,255,255,.32); }
    .rd-header-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
    .rd-open-cal {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(255,255,255,.18); border: 0; color: #fff;
      padding: 7px 12px; border-radius: 8px;
      font-size: .82rem; font-weight: 600; line-height: 1;
      text-decoration: none; white-space: nowrap; cursor: pointer;
      transition: background .15s;
    }
    .rd-open-cal:hover { background: rgba(255,255,255,.32); color: #fff; }

    .rd-body { flex: 1 1 auto; overflow-y: auto; padding: 16px 18px; }

    .rd-section-title {
      font-size: .72rem; text-transform: uppercase;
      letter-spacing: .06em; color: #6b7280;
      font-weight: 600; margin: 6px 0 8px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .rd-section-title .badge {
      background: #eef2ff; color: #4f46e5;
      font-weight: 600; padding: 3px 8px; border-radius: 10px;
    }

    .rd-quick {
      background: #f9fafb; border: 1px solid #e5e7eb;
      border-radius: 10px; padding: 12px; margin-bottom: 18px;
    }
    .rd-quick .form-control {
      font-size: .85rem; padding: 7px 10px; margin-bottom: 8px;
    }
    .rd-quick .btn { font-size: .82rem; padding: 7px 14px; }

    .rd-list { display: flex; flex-direction: column; gap: 8px; }
    .rd-item {
      border: 1px solid #e5e7eb; border-radius: 10px;
      padding: 10px 12px; background: #fff;
      transition: box-shadow .15s, border-color .15s;
    }
    .rd-item:hover { border-color: #c7d2fe; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .rd-item.past { background: #fef2f2; border-color: #fecaca; }

    .rd-item-row { display: flex; align-items: flex-start; gap: 8px; }
    .rd-item-main { flex: 1 1 auto; min-width: 0; }
    .rd-item-title {
      font-weight: 600; color: #111827; font-size: .9rem;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .rd-item-meta {
      font-size: .75rem; color: #6b7280; margin-top: 2px;
      display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .rd-item-meta .dot { width: 3px; height: 3px; background: #d1d5db; border-radius: 50%; }
    .rd-item-desc {
      font-size: .78rem; color: #4b5563; margin-top: 6px;
      white-space: pre-wrap; word-break: break-word;
    }
    .rd-item-actions { display: flex; gap: 4px; flex-shrink: 0; }
    .rd-icon-btn {
      width: 28px; height: 28px; border-radius: 6px;
      border: 0; background: transparent; cursor: pointer;
      color: #6b7280; font-size: .82rem;
      display: inline-flex; align-items: center; justify-content: center;
      transition: background .15s, color .15s;
    }
    .rd-icon-btn:hover { background: #f3f4f6; color: #111827; }
    .rd-icon-btn.danger:hover { background: #fee2e2; color: #b91c1c; }

    .rd-empty {
      text-align: center; padding: 30px 16px; color: #9ca3af;
      border: 2px dashed #e5e7eb; border-radius: 10px;
    }
    .rd-empty i { font-size: 2rem; color: #d1d5db; margin-bottom: 8px; display: block; }

    .rd-footer {
      padding: 12px 18px; border-top: 1px solid #e5e7eb;
      background: #f9fafb;
    }
    .rd-footer a {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      padding: 9px 12px; border-radius: 8px;
      background: #fff; border: 1px solid #d1d5db;
      color: #374151; text-decoration: none; font-size: .85rem; font-weight: 500;
      transition: background .15s, border-color .15s, color .15s;
    }
    .rd-footer a:hover { background: #eef2ff; border-color: #c7d2fe; color: #4f46e5; }

    @media (max-width: 480px) {
      .rd-drawer { width: 100vw; }
    }

    /* SweetAlerts launched from the drawer need to sit above it. */
    .swal2-container.rd-swal { z-index: 2000 !important; }
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
    <?php if (isset($msg)) {
      echo $msg;
    } ?>
    <div class="dash-content">

      <!-- Breadcrumb slim bar -->
      <div class="page-header" style="padding-bottom:8px;">
        <div class="page-block">
          <div class="row align-items-center">
            <div class="col-auto">
              <button type="button" class="btn btn-sm me-1" onclick="window.history.back();" style="background-color:#272469;color:#fff;">&#8592;</button>
              <button type="button" class="btn btn-sm"    onclick="window.history.forward();" style="background-color:#272469;color:#fff;">&#8594;</button>
            </div>
            <div class="col-auto">
              <ul class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="view_clients.php">Clients</a></li>
                <li class="breadcrumb-item active">#<?php echo $client_id; ?></li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <div class="ch-tab-strip">
        <a href="#personal-detail" class="ch-tab is-active" data-target="personal-detail">
          <i class="fas fa-user"></i>
          <span>Personal</span>
        </a>
        <a href="#associated-products" class="ch-tab is-icon" title="Associated Services" data-target="associated-products">
          <i class="fas fa-shield-alt"></i>
        </a>
        <a href="#payment-plans" class="ch-tab is-icon" title="Payment Plans" data-target="payment-plans">
          <i class="fas fa-calendar-check"></i>
        </a>
        <a href="#comments" class="ch-tab is-icon" title="Comments" data-target="comments">
          <i class="fas fa-comments"></i>
        </a>
        <a href="#quotations" class="ch-tab is-icon" title="Quotations" data-target="quotations">
          <i class="fas fa-file-invoice"></i>
        </a>
        <a href="#member-services" class="ch-tab is-icon" title="Member Services" data-target="member-services">
          <i class="fas fa-briefcase"></i>
        </a>
        <a href="#businesses" class="ch-tab is-icon" title="Businesses" data-target="businesses">
          <i class="fas fa-tasks"></i>
        </a>
      </div>

      <!-- ── CLIENT HERO CARD (PDF-style redesign) ─────────────── -->
      <div class="client-hero mb-3">

        <!-- Top action bar: toggles + buttons -->
        <div class="client-hero-topbar">
          <?php if ($client_active == "Yes"): ?>
            <div class="form-check form-switch d-flex align-items-center">
              <input class="form-check-input activity-switch me-2" type="checkbox" role="switch"
                data-member-id="<?= $client_id ?>" <?= ($status == 1) ? 'checked' : '' ?>>
              <span><?= ($status == 1) ? 'Active' : 'Inactive' ?></span>
            </div>
          <?php endif; ?>
          <?php
          $btn_result = mysqli_query($con, "SELECT sms_enabled, email_enabled FROM client_button_settings WHERE member_id='$client_id' LIMIT 1");
          $btn = mysqli_fetch_assoc($btn_result);
          $sms_checked   = ($btn && $btn['sms_enabled'] == 1) ? 'checked' : '';
          $email_checked = ($btn && $btn['email_enabled'] == 1) ? 'checked' : '';
          ?>
          <div class="form-check form-switch d-flex align-items-center">
            <input class="form-check-input sms-switch me-2" type="checkbox" role="switch"
              id="smsSwitch<?= $client_id ?>" data-client-id="<?= $client_id ?>" <?= $sms_checked ?>>
            <span>SMS</span>
          </div>
          <div class="form-check form-switch d-flex align-items-center">
            <input class="form-check-input email-switch me-2" type="checkbox" role="switch"
              id="emailSwitch<?= $client_id ?>" data-client-id="<?= $client_id ?>" <?= $email_checked ?>>
            <span>Email</span>
          </div>

          <button class="ctb ctb-calendar" title="Reminders" onclick="openReminderDrawer()">
            <i class="fas fa-calendar-alt"></i>
          </button>
          <button class="ctb ctb-navy" onclick="sendManualSms(<?= (int)$client_id ?>)">
            <i class="fas fa-comments"></i> SMS
          </button>
          <button class="ctb ctb-navy" onclick="openEmailCompose()" title="Send an email to this client">
            <i class="fas fa-envelope"></i> Email
          </button>
          <button class="ctb ctb-alert" onclick="openAlertChoice()">
            <i class="fas fa-exclamation-triangle"></i> Alert
          </button>
          <?php
          $perm_bill = mysqli_fetch_assoc(mysqli_query($con, "SELECT invoice_and_billing FROM dashboard_access WHERE user_type='$user_type'"));
          if ($perm_bill['invoice_and_billing'] === 'Yes'): ?>
            <a href="client_billing.php?id=<?= $client_id ?>" class="ctb ctb-navy">
              <i class="fas fa-file-invoice"></i> Billing
            </a>
          <?php endif; ?>
          <?php
          $rrow = mysqli_fetch_assoc(mysqli_query($con, "SELECT has_secured_access FROM users WHERE user_id=$userid"));
          if ($rrow['has_secured_access'] == 1): ?>
            <button class="ctb ctb-green" data-toggle="modal" data-target="#login_secured_area">
              <i class="fas fa-lock"></i> Secured
            </button>
          <?php endif; ?>
          <button class="ctb ctb-navy" data-toggle="modal" data-target="#login_bank_area">
            <i class="fas fa-university"></i> Bank
          </button>
          <a href="edit_client.php?id=<?= $client_id ?>" class="ctb ctb-white">
            <i class="fas fa-edit"></i> Edit
          </a>
          <?php
          $perm_query = mysqli_query($con, "SELECT delete_client FROM dashboard_access WHERE user_type='$user_type'");
          $perm_del   = mysqli_fetch_assoc($perm_query);
          if ($perm_del['delete_client'] == 'Yes'):
            if ($status != 1): ?>
              <button type="button" class="ctb ctb-red" data-bs-toggle="modal" data-bs-target="#deleteClientModal">
                <i class="fas fa-trash"></i> Delete
              </button>
            <?php else: ?>
              <span class="d-inline-block" tabindex="0" title="Client must be inactive before deleting" style="cursor: not-allowed;">
                <button class="ctb ctb-red" disabled style="pointer-events: none;">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </span>
            <?php endif;
          endif; ?>
        </div>

        <div class="client-hero-main">

        <!-- Body: person (left) + business (right) -->
        <div class="client-hero-body">

          <!-- ── LEFT: personal ─────────────────────────── -->
          <div class="ch-block">
            <div class="ch-avatar">
              <?php if ($client_pic): ?>
                <img src="<?= htmlspecialchars($client_pic) ?>" alt="<?= htmlspecialchars($client_fullname) ?>"
                     onerror="this.outerHTML='<i class=\'fas fa-user\'></i>';">
              <?php else: ?>
                <i class="fas fa-user"></i>
              <?php endif; ?>
            </div>
            <div class="ch-block-body">
              <div class="ch-name"><?= htmlspecialchars(strtoupper($client_fullname)) ?></div>
              <div class="ch-line">
                <span class="ch-id-badge" title="Client ID"><i class="fas fa-id-card"></i> ID: <?= $client_id ?></span>
                <span><i class="fas fa-calendar-check"></i> Client since: <?= $client_since_display ?></span>
              </div>
              <div class="ch-line">
                <?= cf_render_hero_field($con, $client_id, 'email', $client_email, 'fas fa-envelope', 'Client Email') ?>
                <?= cf_render_hero_field($con, $client_id, 'phone', $client_phone, 'fas fa-phone', 'Client Phone No') ?>
              </div>
              <div class="ch-pills-row">
                <?php if ($status == 1): ?>
                  <span class="ch-pill status-active"><i class="fas fa-circle"></i> Active</span>
                <?php else: ?>
                  <span class="ch-pill status-inactive"><i class="fas fa-circle"></i> Inactive</span>
                <?php endif; ?>
                <?php if ($hero_special == 1): ?>
                  <span class="ch-pill special"><i class="fas fa-star"></i> Special</span>
                <?php endif; ?>
                <?php if ($hero_ach_payer): ?>
                  <span class="ch-pill ach"><i class="fas fa-university"></i> ACH Payer</span>
                <?php endif; ?>
                <?php if (!empty($hero_frequency) && $hero_frequency !== 'None'): ?>
                  <span class="ch-pill freq"><i class="fas fa-sync-alt"></i> <?= htmlspecialchars($hero_frequency) ?></span>
                <?php endif; ?>
                <?php if ($hero_unpaid > 0): ?>
                  <a class="ch-pill balance" href="inv_unpaid_full.php?client_id=<?= (int)$client_id ?>&preview=1" target="_blank" rel="noopener" title="View unpaid invoice history (PDF)"><i class="fas fa-dollar-sign"></i> Unpaid Balance: $<?= number_format($hero_unpaid, 0) ?> <i class="fas fa-file-pdf" style="margin-left:4px;opacity:.85;"></i></a>
                <?php else: ?>
                  <span class="ch-pill balance is-zero"><i class="fas fa-dollar-sign"></i> Unpaid Balance: $<?= number_format($hero_unpaid, 0) ?></span>
                <?php endif; ?>
                <?php if ($hero_discount_total > 0): ?>
                  <a class="ch-pill discount" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#clientDiscountsModal" title="View all discounted invoices"><i class="fas fa-tags"></i> Discounts: $<?= number_format($hero_discount_total, 2) ?> <i class="fas fa-chevron-right" style="margin-left:3px;opacity:.85;font-size:.7rem;"></i></a>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- ── RIGHT: business ────────────────────────── -->
          <div class="ch-block">
            <div class="ch-avatar">
              <?php if ($cpc_icon_src !== ''): ?>
                <img src="<?= htmlspecialchars($cpc_icon_src) ?>" alt="<?= htmlspecialchars($hero_type_name ?? '') ?>"
                     title="<?= htmlspecialchars($hero_type_name ?? '') ?>"
                     onerror="this.outerHTML='<i class=\'fas fa-briefcase\'></i>';">
              <?php else: ?>
                <i class="fas fa-briefcase"></i>
              <?php endif; ?>
            </div>
            <div class="ch-block-body">
              <div class="ch-name">
                <?= !empty($hero_bus_name) ? htmlspecialchars(strtoupper($hero_bus_name)) : '<span style="opacity:.55;font-style:italic;">No business on file</span>' ?>
              </div>
              <div class="ch-line">
                <?php
                  $b_email     = $hero_bus_email ?: $client_email;
                  $b_email_type = $hero_bus_email ? 'sec_email' : 'email';
                  $b_phone     = $hero_bus_phone ?: $client_phone;
                  $b_phone_type = $hero_bus_phone ? 'work_phone' : 'phone';
                ?>
                <?= cf_render_hero_field($con, $client_id, $b_email_type, $b_email, 'fas fa-envelope', 'Business Email') ?>
                <?= cf_render_hero_field($con, $client_id, $b_phone_type, $b_phone, 'fas fa-phone', 'Business Phone Number') ?>
              </div>
              <?php if (!empty($hero_category) || !empty($hero_type_name)): ?>
                <div class="ch-line">
                  <?php if (!empty($hero_category)): ?>
                    <?php
                      // Stored as "Industry Header - Activity Label (code)". Show the
                      // header with a caret; the activity is revealed on click.
                      $catParts = explode(' - ', $hero_category, 2);
                      $catHead  = trim($catParts[0]);
                      $catSub   = isset($catParts[1]) ? trim($catParts[1]) : '';
                    ?>
                    <?php if ($catSub !== ''): ?>
                      <span class="ch-cat" title="Business Category">
                        <i class="fas fa-briefcase"></i>
                        <button type="button" class="ch-cat-toggle" aria-expanded="false">
                          <?= htmlspecialchars($catHead) ?><i class="fas fa-caret-down"></i>
                        </button>
                        <span class="ch-cat-sub" title="<?= htmlspecialchars($catSub) ?>"><?= htmlspecialchars($catSub) ?></span>
                      </span>
                    <?php else: ?>
                      <span title="Business Category"><i class="fas fa-briefcase"></i><?= htmlspecialchars($catHead) ?></span>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if (!empty($hero_type_name)): ?>
                    <span title="Client Type"><i class="fas fa-user-tag"></i><?= htmlspecialchars($hero_type_name) ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

        </div><!-- /.client-hero-body -->

        <!-- ── Detail cards (two columns, match PDF layout) ──────── -->
        <div class="ch-details-row">

          <!-- LEFT: Client Details + Address + Featured Services -->
          <div class="ch-detail-card">
            <div class="ch-detail-inner">
              <?php
              // Honour the Client Type Manual: hide fields unticked for this type
              // and fields the client left blank (Added By/Updated By are fixed).
              $heroCD  = cpc_hero_row('DOB', $hero_dob_disp, $cpc_visible, 'dob');
              $heroCD .= cpc_hero_row('SSN', $hero_ssn, $cpc_visible, 'ss');
              $heroCD .= cpc_hero_row('Added By', $hero_added_name !== '' ? getInitials($hero_added_name) . ' (' . $hero_added_id . ')' : '');
              $heroCD .= cpc_hero_row('Updated By', $hero_upd_name !== '' ? getInitials($hero_upd_name) . ' (' . $hero_upd_id . ')' : '');
              if ($heroCD !== ''): ?>
              <div>
                <div class="ch-detail-section-title">Client Details</div>
                <?= $heroCD ?>
              </div>
              <?php endif; ?>
              <?php
              $heroAD  = cpc_hero_row('Address', $hero_home_addr, $cpc_visible, 'hstreet');
              $heroAD .= cpc_hero_row('City', $hero_home_city, $cpc_visible, 'hcity');
              $heroAD .= cpc_hero_row('State', $hero_home_state, $cpc_visible, 'hstate_zip');
              $heroAD .= cpc_hero_row('Zip', $hero_home_zip, $cpc_visible, 'hstate_zip');
              $heroAD .= cpc_hero_row('County', $hero_home_cty, $cpc_visible, 'hcounty');
              if ($heroAD !== ''): ?>
              <div>
                <div class="ch-detail-section-title">Address</div>
                <?= $heroAD ?>
              </div>
              <?php endif; ?>
            </div>

            <div class="ch-featured-block">
              <div class="ch-detail-section-title">Member Services</div>
              <?php if (!empty($hero_featured)): ?>
                <ul class="ch-featured-list">
                  <?php foreach ($hero_featured as $_svc): ?>
                    <li><?= htmlspecialchars($_svc) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <div class="ch-detail-field"><span class="fv-empty">No active services</span></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- RIGHT: Company Detail + Contact & Address -->
          <div class="ch-detail-card">
            <div class="ch-detail-inner">
              <?php
              $heroCO  = cpc_hero_row('Client Type', $hero_type_name);
              $heroCO .= cpc_hero_row('Designation', $hero_designation, $cpc_visible, 'member_pos');
              $heroCO .= cpc_hero_row('Business Start date', $hero_bus_start, $cpc_visible, 'bus_start_date');
              $heroCO .= cpc_hero_row('Incorporation Date', $hero_bus_incorp, $cpc_visible, 'incorp_date');
              $heroCO .= cpc_hero_row('File', $hero_file, $cpc_visible, 'file');
              $heroCO .= cpc_hero_row('EIN', $hero_ein, $cpc_visible, 'ein');
              $heroCO .= cpc_hero_row('DBA', $hero_dba, $cpc_visible, 'dba');
              $heroCO .= cpc_hero_row('PMB', $hero_pmb, $cpc_visible, 'tf');
              if ($heroCO !== ''): ?>
              <div>
                <div class="ch-detail-section-title">Company Detail</div>
                <?= $heroCO ?>
              </div>
              <?php endif; ?>
              <?php
              $heroCA  = cpc_hero_row('Phone (other)', $hero_other_ph, $cpc_visible, 'other_ph');
              $heroCA .= cpc_hero_row('Phone (overseas)', $hero_overseas_ph, $cpc_visible, 'overseas_ph');
              $heroCA .= cpc_hero_row('County', $hero_bcounty, $cpc_visible, 'bcounty');
              $heroCA .= cpc_hero_row('Business Address', $hero_bus_addr, $cpc_visible, 'address');
              $heroCA .= cpc_hero_row('Business City', $hero_bus_city, $cpc_visible, 'city');
              $heroCA .= cpc_hero_row('Business State', $hero_bus_state, $cpc_visible, 'state_zip');
              $heroCA .= cpc_hero_row('Business Zip', $hero_bus_zip, $cpc_visible, 'state_zip');
              if ($heroCA !== ''): ?>
              <div>
                <div class="ch-detail-section-title">Contact &amp; Address</div>
                <?= $heroCA ?>
              </div>
              <?php endif; ?>
            </div>
          </div>

        </div><!-- /.ch-details-row -->

        <!-- ── ALERTS ROW (Alerts + ACH Alerts, matches detail cards) ── -->
        <?php
        // Regular client alerts (client_warnings) — all active for this member.
        $warn_res = mysqli_query(
          $con,
          "SELECT w.id, w.warning, w.created_at, u.user_login, u.user_name, w.created_by
           FROM client_warnings w
           JOIN users u ON u.user_id = w.created_by
           WHERE w.member_id = $client_id AND w.is_active = 1
           ORDER BY w.id DESC"
        );
        $warns = [];
        while ($wr = mysqli_fetch_assoc($warn_res)) { $warns[] = $wr; }
        $warn = $warns[0] ?? null; // kept for the add/edit warning modal prefill

        // Ensure the ACH alerts table exists even before the AJAX endpoint is first hit.
        mysqli_query($con, "CREATE TABLE IF NOT EXISTS ach_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id INT NOT NULL,
            total_payment DECIMAL(12,2) NOT NULL DEFAULT 0,
            initiated_at DATETIME NULL,
            collection_at DATETIME NULL,
            bank_name VARCHAR(150) NULL,
            note TEXT NULL,
            status ENUM('view','collected','cancelled') NOT NULL DEFAULT 'view',
            collected_at DATETIME NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY member_id (member_id),
            KEY status (status)
        )");

        $ach_res    = mysqli_query($con, "SELECT a.*, u.user_name AS added_by_name FROM ach_alerts a LEFT JOIN users u ON u.user_id = a.created_by WHERE a.member_id='$client_id' ORDER BY a.id DESC");
        $ach_alerts = [];
        $ach_pending_total = 0;
        while ($a = mysqli_fetch_assoc($ach_res)) {
          $ach_alerts[] = $a;
          if ($a['status'] === 'view') $ach_pending_total += (float)$a['total_payment'];
        }
        // Alerts whose collection moment has arrived and are still pending -> reminder pop-up.
        $ach_due = [];
        foreach ($ach_alerts as $a) {
          if ($a['status'] === 'view' && !empty($a['collection_at']) && $a['collection_at'] !== '0000-00-00 00:00:00'
              && strtotime($a['collection_at']) <= time()) {
            $ach_due[] = $a;
          }
        }
        $achStatusBadge = ['view' => 'bg-warning text-dark', 'collected' => 'bg-success', 'cancelled' => 'bg-secondary'];
        ?>
        <div class="ch-details-row ch-alerts-row">

          <!-- LEFT: Alerts -->
          <div class="ch-detail-card" id="regularAlertsPanel">
            <div class="ch-alert-head">
              <div class="d-flex align-items-center">
                <div class="ch-detail-section-title">Alerts</div><?= mh_icon('alerts') ?>
              </div>
              <span class="ch-count-badge"><?= count($warns) ?></span>
            </div>
            <div class="ch-alert-body">
              <?php if (empty($warns)): ?>
                <div class="ch-alert-empty"><i class="fas fa-check-circle me-1 text-success"></i>No active alerts.</div>
              <?php else: foreach ($warns as $w):
                $canEdit   = in_array($_SESSION['user_type'], ['1','2']) || $_SESSION['user_id'] == $w['created_by'];
                $wInitials = getInitials($w['user_name'] ?: $w['user_login']);
              ?>
                <div class="alert alert-warning d-flex align-items-start mb-2 fv-alert-item" role="alert" data-warn-id="<?= (int)$w['id'] ?>">
                  <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
                  <div class="flex-grow-1">
                    <div class="fv-alert-msg"><?= nl2br(htmlspecialchars($w['warning'])) ?></div>
                    <div class="small text-muted mt-1">
                      <i class="far fa-clock me-1"></i><?= date('M d, Y g:i A', strtotime($w['created_at'])) ?>
                      &middot; <?= htmlspecialchars($wInitials) ?>(<?= (int)$w['created_by'] ?>)
                    </div>
                  </div>
                  <?php if ($canEdit): ?>
                    <button class="btn btn-sm btn-outline-secondary ms-1" onclick="editWarning(<?= (int)$w['id'] ?>)"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-outline-danger ms-1" onclick="delWarning(<?= (int)$w['id'] ?>)"><i class="fas fa-trash"></i></button>
                  <?php endif; ?>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </div>

          <!-- RIGHT: ACH Alerts -->
          <div class="ch-detail-card" id="achAlertsPanel">
            <div class="ch-alert-head">
              <div class="ch-detail-section-title">ACH Alerts</div>
              <span class="ch-count-badge ch-badge-primary">Pending: $<?= number_format($ach_pending_total, 2) ?></span>
            </div>
            <div class="ch-alert-body">
              <?php if (empty($ach_alerts)): ?>
                <div class="ch-alert-empty"><i class="fas fa-check-circle me-1 text-success"></i>No ACH alerts.</div>
              <?php else: ?>
              <div class="ch-ach-list">
                <?php foreach ($ach_alerts as $a):
                  $st       = $a['status'];
                  $stItem   = ['view' => 'is-pending', 'collected' => 'is-collected', 'cancelled' => 'is-cancelled'][$st] ?? '';
                  $initDisp = (!empty($a['initiated_at'])  && $a['initiated_at']  !== '0000-00-00 00:00:00') ? date('M d, Y g:i A', strtotime($a['initiated_at']))  : '—';
                  $coleDisp = (!empty($a['collection_at']) && $a['collection_at'] !== '0000-00-00 00:00:00') ? date('M d, Y g:i A', strtotime($a['collection_at'])) : '—';
                  $collDisp = (!empty($a['collected_at'])  && $a['collected_at']  !== '0000-00-00 00:00:00') ? date('M d, Y g:i A', strtotime($a['collected_at']))  : '';
                  $addedByName = $a['added_by_name'] ?: 'Unknown';
                  $addedByDisp = $addedByName . ' — ' . date('M d, Y g:i A', strtotime($a['created_at']));
                ?>
                  <div class="ch-ach-item <?= $stItem ?>">
                    <span class="ch-ach-amt">$<?= number_format((float)$a['total_payment'], 2) ?></span>
                    <span class="badge <?= $achStatusBadge[$st] ?? 'bg-secondary' ?> text-capitalize"><?= htmlspecialchars($st) ?></span>
                    <span class="ch-ach-id">#<?= (int)$a['id'] ?></span>
                    <span class="ch-ach-due"><i class="far fa-calendar-check"></i>Due <b><?= $coleDisp ?></b></span>
                    <span class="ch-ach-addedby small text-muted"><i class="far fa-user"></i> <?= htmlspecialchars($addedByName) ?></span>
                    <span class="ch-ach-actions">
                      <button class="btn btn-sm btn-outline-primary" title="View details"
                        onclick="achDetail(this)"
                        data-id="<?= (int)$a['id'] ?>"
                        data-amount="$<?= number_format((float)$a['total_payment'], 2) ?>"
                        data-status="<?= htmlspecialchars($st, ENT_QUOTES) ?>"
                        data-status-class="<?= $achStatusBadge[$st] ?? 'bg-secondary' ?>"
                        data-initiated="<?= htmlspecialchars($initDisp, ENT_QUOTES) ?>"
                        data-collection="<?= htmlspecialchars($coleDisp, ENT_QUOTES) ?>"
                        data-bank="<?= htmlspecialchars($a['bank_name'] ?: '—', ENT_QUOTES) ?>"
                        data-note="<?= htmlspecialchars($a['note'] ?: '—', ENT_QUOTES) ?>"
                        data-collected="<?= htmlspecialchars($collDisp ?: '—', ENT_QUOTES) ?>"
                        data-added-by="<?= htmlspecialchars($addedByDisp, ENT_QUOTES) ?>">
                        <i class="fas fa-eye"></i>
                      </button>
                      <?php if ($st === 'view'): ?>
                        <button class="btn btn-sm btn-success" onclick="achCollect(<?= (int)$a['id'] ?>)" title="Mark collected"><i class="fas fa-check"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="achCancel(<?= (int)$a['id'] ?>)" title="Cancel"><i class="fas fa-ban"></i></button>
                      <?php endif; ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          </div>

        </div><!-- /.ch-alerts-row -->
        </div><!-- /.client-hero-main -->

        <!-- Delete Client Modal (inline, inside hero wrapper) -->
        <div class="modal fade" id="deleteClientModal" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Confirm Delete Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <?php if ($status == 1): ?>
                  <div class="alert alert-danger"><strong>Warning!</strong> Deactivate the client first.</div>
                <?php else: ?>
                  <p>Are you sure you want to delete client <strong>#<?= $client_id ?></strong>? This cannot be undone.</p>
                <?php endif; ?>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <?php if ($status == 0): ?>
                  <form method="post" action="delete_client.php">
                    <input type="hidden" name="client_id" value="<?= $client_id ?>">
                    <button type="submit" class="btn btn-danger" name="delete_client" value="1">Delete Client</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Client Discounts Modal -->
        <div class="modal fade" id="clientDiscountsModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-tags me-2"></i>Discounted Invoices</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <?php if (empty($hero_discount_invoices)): ?>
                  <p class="text-muted mb-0">No discounts have been applied for this client yet.</p>
                <?php else: ?>
                  <div class="discount-summary mb-3">
                    <i class="fas fa-percent"></i>
                    <div>
                      <span class="label">Total Discounts Given</span>
                      <span class="value">$<?= number_format($hero_discount_total, 2) ?></span>
                    </div>
                  </div>
                  <div class="table-responsive">
                    <table class="table discount-inv-table align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Invoice</th>
                          <th>Date</th>
                          <th>Total</th>
                          <th>Discount</th>
                          <th>Balance</th>
                          <th class="text-end">Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $invBase = 'http://192.168.15.29/invoices/';
                        foreach ($hero_discount_invoices as $_inv):
                          $_d  = (float)$_inv['discount'];
                          $_bal = (float)$_inv['balance'];
                          $_dt = !empty($_inv['due_date']) ? date('m/d/Y', strtotime($_inv['due_date'])) : '';
                          $_f  = basename((string)$_inv['filelink']);
                        ?>
                        <tr>
                          <td class="disc-inv-id">#<?= (int)$_inv['invoice_id'] ?></td>
                          <td class="disc-inv-date"><?= htmlspecialchars($_dt) ?></td>
                          <td>$<?= number_format((float)$_inv['total_amount'], 2) ?></td>
                          <td class="disc-amount">-$<?= number_format($_d, 2) ?></td>
                          <td class="<?= $_bal > 0 ? 'disc-balance-pos' : '' ?>">$<?= number_format($_bal, 2) ?></td>
                          <td class="text-end">
                            <?php if (!empty($_f)): ?>
                              <a href="<?= htmlspecialchars($invBase . $_f) ?>" target="_blank" rel="noopener" class="view-inv-btn"><i class="fas fa-file-invoice me-1"></i>View Invoice</a>
                            <?php else: ?>
                              <span class="text-muted small">No file</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>
      </div><!-- /.client-hero -->

      <button id="backToTop" title="Back to top"><i class="fas fa-arrow-up"></i></button>
 
      <!-- Section controls -->
      <div id="sectionControls">
        <button id="btn-expand-all"><i class="fas fa-expand-alt me-1"></i> Expand All</button>
        <button id="btn-collapse-all"><i class="fas fa-compress-alt me-1"></i> Collapse All</button>
      </div>

      <!-- PAGE SECTIONS (single-page layout) -->
      <div id="clientPageContent">

        <!-- ==================== SECTION: PERSONAL DETAILS ==================== -->
        <div class="section-heading">
          <div class="sh-icon"><img src="<?= htmlspecialchars($cpc_icon_url) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;"></div>
          <h4>Personal Details</h4>
          <div class="sh-line"></div>
        </div>
        <div id="personal-detail" class="section-body">
          <?php
          $querr = mysqli_query($con, "SELECT * FROM members WHERE member_id='$client_id'");
          while ($fetch_member = mysqli_fetch_assoc($querr)) {
            $member_name = $fetch_member['name'];
            $imagePath   = !empty($fetch_member['mem_pic']) ? "images/clients/" . $fetch_member['mem_pic'] : '';
            $fullName    = trim(htmlspecialchars($fetch_member['name'] . ' ' . $fetch_member['mname'] . ' ' . $fetch_member['lname']));
            $initials    = strtoupper(substr($fetch_member['name'] ?? 'C', 0, 1) . substr($fetch_member['lname'] ?? '', 0, 1));
            $dob         = $fetch_member['dob'];
            $dobDisplay  = (empty($dob) || $dob === '0000-00-00') ? '<span class="text-muted fst-italic">N/A</span>' : DateTime::createFromFormat('Y-m-d', $dob)->format('M d, Y');
            $addedById   = (int) $fetch_member['added_by'];
            $addedName   = mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id=$addedById"))['user_name'] ?? 'Unknown';
            $updId       = (int) $fetch_member['user_id'];
            $updName     = ($updId !== $addedById) ? (mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id=$updId"))['user_name'] ?? 'Unknown') : null;
            $balRow      = mysqli_fetch_assoc(mysqli_query($con, "SELECT SUM(balance) AS bal FROM billing_invoice WHERE mem_id='$client_id'"));
            $balance     = number_format($balRow['bal'] ?: 0, 2);
            $tq          = mysqli_query($con, "SELECT type_name FROM member_type WHERE type_id='{$fetch_member['type']}'");
            $clientType  = htmlspecialchars(mysqli_fetch_assoc($tq)['type_name'] ?? '');
            $busStart    = (!empty($fetch_member['bus_start_date']) && $fetch_member['bus_start_date'] !== '0000-00-00') ? date("M d, Y", strtotime($fetch_member['bus_start_date'])) : '<span class="text-muted fst-italic">N/A</span>';
            $incorp      = (!empty($fetch_member['incorp_date'])    && $fetch_member['incorp_date']    !== '0000-00-00') ? date("M d, Y", strtotime($fetch_member['incorp_date']))    : '<span class="text-muted fst-italic">N/A</span>';
            $clientSince = (!empty($fetch_member['client_since'])   && $fetch_member['client_since']   !== '0000-00-00') ? date("M d, Y", strtotime($fetch_member['client_since']))   : 'N/A';
          ?>

          <!-- ── Profile Banner (hidden — hero + detail cards above replace it) ───── -->
          <div class="pd-banner mb-3" style="display:none;">
            <div class="pd-avatar">
              <?php if ($imagePath): ?>
                <img src="<?= $imagePath ?>" alt="<?= $fullName ?>" onerror="this.parentElement.innerHTML='<span class=\'pd-initials\'><?= $initials ?></span>'">
              <?php else: ?>
                <span class="pd-initials"><?= $initials ?></span>
              <?php endif; ?>
            </div>
            <div class="pd-banner-info">
              <div class="pd-banner-name"><?= $fullName ?></div>
              <div class="pd-banner-meta">
                <span><i class="fas fa-hashtag"></i> Client #<?= $fetch_member['member_id'] ?></span>
                <?php if (!empty($fetch_member['prm_email'])): ?>
                  <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($fetch_member['prm_email']) ?></span>
                <?php endif; ?>
                <?php if (!empty($fetch_member['cell_ph'])): ?>
                  <span><i class="fas fa-mobile-alt"></i> <?= htmlspecialchars($fetch_member['cell_ph']) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-calendar-check"></i> Client since <?= $clientSince ?></span>
              </div>
            </div>
            <?php if ($perms['view_personal'] === 'Yes'): ?>
            <div class="pd-banner-actions">
              <a href="edit_client.php?id=<?= $client_id ?>" class="btn btn-sm btn-light"><i class="fas fa-pencil-alt me-1"></i>Edit</a>
            </div>
            <?php endif; ?>
          </div>

          <!-- ── Two-column cards ───────────────────────────────────── -->
          <div class="row g-3">

            <!-- PERSONAL CARD -->
            <div class="col-lg-6 d-flex">
              <?php if ($perms['view_personal'] === 'Yes'): ?>
              <div class="card pd-card w-100">
                <div class="pd-card-header"><i class="fas fa-id-card"></i> Personal Information<?= mh_icon('personal') ?></div>
                <div class="pd-card-body">

                  <?php
                  // Config-driven (client_type_manual.php): fields show only if enabled
                  // for this client type AND the client actually filled them in.
                  $fixedPersonal = [
                    'client_id'       => '<div class="pd-field"><span class="pd-label">Client ID</span><span class="pd-value"><span class="badge" style="background:var(--brand);color:#fff;">#' . (int) $fetch_member['member_id'] . '</span></span></div>',
                    'full_name'       => '<div class="pd-field"><span class="pd-label">Full Name</span><span class="pd-value fw-semibold">' . $fullName . '</span></div>',
                    'billing_balance' => '<div class="pd-field"><span class="pd-label">Billing Balance</span><span class="pd-value ' . (($balRow['bal'] ?? 0) > 0 ? 'text-danger fw-bold' : 'text-success fw-bold') . '">$' . $balance . '</span>' . mh_invoice_icon('Payment History') . '</div>',
                    'added_by'        => '<div class="pd-field"><span class="pd-label">Added By</span><span class="pd-value">' . htmlspecialchars(getInitials($addedName)) . ' <span class="text-muted" style="font-size:.78rem;">(#' . $addedById . ')</span></span></div>',
                    'updated_by'      => $updName ? '<div class="pd-field"><span class="pd-label">Updated By</span><span class="pd-value">' . htmlspecialchars(getInitials($updName)) . ' <span class="text-muted" style="font-size:.78rem;">(#' . $updId . ')</span></span></div>' : '',
                  ];
                  cpc_render_card($con, $client_id, $fetch_member, 'personal', $cpc_visible, $fixedPersonal);
                  ?>

                </div>
              </div>
              <?php else: ?>
              <div class="card w-100 h-100">
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                  <i class="fas fa-user-lock fa-4x text-muted mb-3"></i>
                  <h5 class="text-muted mb-1">Personal Details Restricted</h5>
                  <p class="text-muted mb-0">Contact your administrator for access.</p>
                </div>
              </div>
              <?php endif; ?>
            </div><!-- /col personal -->

            <!-- COMPANY CARD -->
            <div class="col-lg-6 d-flex">
              <?php if ($perms['view_company'] === 'Yes'): ?>
              <div class="card pd-card w-100">
                <div class="pd-card-header"><i class="fas fa-building"></i> Company Information<?= mh_icon('company') ?></div>
                <div class="pd-card-body">

                  <?php
                  // Config-driven (client_type_manual.php). Client Type stays fixed and
                  // shows the type's assigned icon; other fields obey the manual + hide-empty.
                  $fixedCompany = [
                    'client_type' => '<div class="pd-field"><span class="pd-label">Client Type</span><span class="pd-value"><img src="' . htmlspecialchars($cpc_icon_url) . '" alt="" style="width:18px;height:18px;border-radius:4px;object-fit:cover;margin-right:6px;vertical-align:middle;">' . ($clientType ?: '<span class="text-muted fst-italic">—</span>') . '</span></div>',
                  ];
                  cpc_render_card($con, $client_id, $fetch_member, 'company', $cpc_visible, $fixedCompany);
                  echo customizer_render_profile_custom($con, 'clients', (int) $client_id, $cpc_visible);
                  ?>

                  <!-- Associated Businesses -->
                  <?php
                  $business_query = "SELECT DISTINCT linked.member_id, linked.bus_name, linked.status, linked.name, linked.mname, linked.lname
                    FROM (
                      SELECT m.member_id, m.bus_name, m.status, m.name, m.mname, m.lname FROM business_member_assoc bma JOIN members m ON bma.business_id = m.member_id WHERE bma.member_id = '$client_id'
                      UNION
                      SELECT m.member_id, m.bus_name, m.status, m.name, m.mname, m.lname FROM business_member_assoc bma JOIN members m ON bma.member_id = m.member_id WHERE bma.business_id = '$client_id'
                    ) AS linked WHERE linked.member_id <> '$client_id' ORDER BY linked.bus_name ASC";
                  $business_result = mysqli_query($con, $business_query);
                  if ($business_result && mysqli_num_rows($business_result) > 0): ?>
                  <div class="pd-group-label mt-3"><i class="fas fa-link"></i> Associated Businesses</div>
                  <div class="pd-assoc-list">
                    <?php while ($bus = mysqli_fetch_assoc($business_result)):
                      $bl = trim($bus['bus_name'] ?? '') ?: trim(implode(' ', array_filter([$bus['name'] ?? '', $bus['mname'] ?? '', $bus['lname'] ?? '']))) ?: 'Client #' . (int)$bus['member_id']; ?>
                    <a href="client_details.php?id=<?= (int)$bus['member_id'] ?>&status=<?= (int)($bus['status'] ?? 1) ?>" class="pd-assoc-item">
                      <i class="fas fa-building"></i><?= htmlspecialchars($bl) ?>
                    </a>
                    <?php endwhile; ?>
                  </div>
                  <?php endif; ?>

                </div>
              </div>
              <?php else: ?>
              <div class="card w-100 h-100">
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                  <i class="fas fa-user-lock fa-4x text-muted mb-3"></i>
                  <h5 class="text-muted mb-1">Company Details Restricted</h5>
                  <p class="text-muted mb-0">Contact your administrator for access.</p>
                </div>
              </div>
              <?php endif; ?>
            </div><!-- /col company -->

          </div><!-- /row -->
          <?php } ?>
        </div><!-- /#personal-detail -->

        <!-- ==================== SECTION: ASSOCIATED SERVICES ==================== -->
        <div class="section-heading">
          <div class="sh-icon"><i class="fas fa-tags"></i></div>
          <h4>Associated Services<?= mh_icon('orders') ?></h4>
          <div class="sh-line"></div>
        </div>
        <div id="associated-products" class="section-body">
          <div class="row">
            <div class="col-sm-12 col-md-12">
              <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row">
                    <div class="col-md-10">
                      <h5>Associated Services</h5>
                    </div>
                    <div class="col-md-2 text-right">
                      <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#associatedProducts">
                        <i class="fas fa-plus"></i> Add Service
                      </button>
                    </div>
                  </div>
                  <hr>
                  <div class="table-responsive">
                    <div class="dataTable-container" style="max-height:380px;overflow-y:auto;">
                      <table class="table datatable mb-0">
                        <thead>
                          <tr>
                            <th>Services</th>
                            <th>Renewal Cycle</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Renewal Date</th>
                            <th>Due Date</th>
                            <th>Inserted By</th>
                            <th>Assign To</th>
                            <th>Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          // Pre-fetch all auto-renewals for this client, grouped by parent order_id
                          $renew = mysqli_query($con, "
                            SELECT
                                oar.*,
                                m.membership_name,
                                u.user_login AS inserted_by,
                                au.user_name AS assigned_to_name
                            FROM orders_auto_renewed oar
                            LEFT JOIN memberships m ON m.membership_id = oar.prod_id
                            LEFT JOIN users u  ON u.user_id  = oar.user_id
                            LEFT JOIN users au ON au.user_id = oar.assign_to
                            LEFT JOIN orders o
                                ON o.order_id = oar.order_id
                              AND o.start_date = oar.start_date
                              AND o.exp_date   = oar.exp_date
                            WHERE oar.mem_id = $client_id
                              AND o.order_id IS NULL
                            ORDER BY oar.auto_id ASC
                          ");
                          // Renewals created by the engine are now stamped with their parent order_id;
                          // legacy rows have order_id = 0 and get routed by (prod_id, bil_cyc_months)
                          // so a 1-mon orphan goes to the 1-mon parent, not whichever happens to be oldest.
                          $autoByOrder       = [];
                          $autoByProdCycle   = [];
                          while ($r = mysqli_fetch_assoc($renew)) {
                            $oid = (int) $r['order_id'];
                            if ($oid > 0) {
                              $autoByOrder[$oid][] = $r;
                            } else {
                              $key = (int) $r['prod_id'] . ':' . (int) $r['bil_cyc_months'];
                              $autoByProdCycle[$key][] = $r;
                            }
                          }
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

                          $orig = mysqli_query($con, "
                            SELECT
                                o.*,
                                u.user_login AS inserted_by,
                                au.user_name  AS assigned_to_name
                            FROM orders o
                            LEFT JOIN users u  ON u.user_id  = o.user_id
                            LEFT JOIN users au ON au.user_id = o.assign_to
                            WHERE o.mem_id = $client_id
                            ORDER BY o.order_id DESC
                          ");

                          // Pre-pass: orphan renewals (legacy order_id = 0) attach to the OLDEST
                          // parent matching their (prod_id, bil_cyc_months) — so 1-mon orphans go to
                          // a 1-mon parent and 3-mon orphans go to a 3-mon parent.
                          $orphanOwner = [];
                          if (!empty($autoByProdCycle)) {
                            $oldestQ = mysqli_query($con,
                              "SELECT prod_id, bil_cyc_months, MIN(order_id) AS oldest_oid
                                 FROM orders WHERE mem_id = $client_id
                                GROUP BY prod_id, bil_cyc_months");
                            while ($oo = mysqli_fetch_assoc($oldestQ)) {
                              $key = (int) $oo['prod_id'] . ':' . (int) $oo['bil_cyc_months'];
                              $orphanOwner[$key] = (int) $oo['oldest_oid'];
                            }
                          }

                          // Pre-fetch memberships once (avoid N+1 inside the orders loop below).
                          $membershipMap = [];
                          $_mm = mysqli_query($con, "SELECT membership_id, membership_name FROM memberships");
                          if ($_mm) {
                            while ($_m = mysqli_fetch_assoc($_mm)) {
                              $membershipMap[(int) $_m['membership_id']] = $_m['membership_name'];
                            }
                          }

                          while ($row = mysqli_fetch_assoc($orig)):
                            $prodName = $membershipMap[(int) $row['prod_id']] ?? 'N/A';
                            $parentId   = (int) $row['order_id'];
                            $prodId     = (int) $row['prod_id'];
                            // Prefer renewals stamped with this parent's order_id; orphan renewals
                            // (legacy order_id = 0) go to the oldest parent matching (prod_id, cycle).
                            $children   = $autoByOrder[$parentId] ?? [];
                            $cycleKey   = $prodId . ':' . (int) $row['bil_cyc_months'];
                            if (isset($autoByProdCycle[$cycleKey]) && ($orphanOwner[$cycleKey] ?? 0) === $parentId) {
                              $children = array_merge($children, $autoByProdCycle[$cycleKey]);
                            }
                            $childCount = count($children);
                            ?>
                            <tr>
                              <td>
                                <?php if ($childCount > 0): ?>
                                  <button type="button" class="ar-toggle" data-ar-target="<?= $parentId ?>"
                                    aria-expanded="false"
                                    title="Show <?= $childCount ?> renewal<?= $childCount === 1 ? '' : 's' ?>">
                                    <i class="fas fa-chevron-right"></i>
                                  </button>
                                <?php endif; ?>
                                <?php if ($row['order_status'] == 1): ?>
                                  <span class="status-pill pill-active me-1"><i class="fas fa-circle" style="font-size:.5rem;"></i> Active</span>
                                <?php elseif ($row['order_status'] == -1): ?>
                                  <span class="status-pill pill-done me-1"><i class="fas fa-check"></i> Done</span>
                                <?php else: ?>
                                  <span class="status-pill pill-inactive me-1"><i class="fas fa-pause-circle" style="font-size:.65rem;"></i> Inactive</span>
                                <?php endif; ?>
                                <strong><?= htmlspecialchars($prodName) ?></strong>
                                <?php if ($childCount > 0): ?>
                                  <span class="ar-count"><?= $childCount ?> renewal<?= $childCount === 1 ? '' : 's' ?></span>
                                <?php endif; ?>
                              </td>
                              <td><?= (int) $row['bil_cyc_months'] ?> month<?= ((int) $row['bil_cyc_months'] === 1 ? '' : 's') ?></td>
                              <td><?= date('M d, Y', strtotime($row['start_date'])) ?></td>
                              <td>
                                <?php if ($row['order_status'] == -1): ?>
                                  <span class="text-muted">Completed</span>
                                <?php else: ?>
                                  <?= date('M d, Y', strtotime($row['exp_date'])) ?>
                                <?php endif; ?>
                              </td>
                              <td><?= !empty($row['renewal_date']) ? date('M d, Y', strtotime($row['renewal_date'])) : '<span class="text-muted">—</span>' ?></td>
                              <td><?= !empty($row['due_date']) ? date('M d, Y', strtotime($row['due_date'])) : '<span class="text-muted">—</span>' ?></td>
                              <td><?= htmlspecialchars($row['inserted_by']) ?></td>
                              <td><?= htmlspecialchars($row['assigned_to_name'] ?? '—') ?></td>
                              <td class="text-nowrap">
                                <button type="button" class="btn btn-outline-secondary btn-sm btn-action"
                                  onclick="openReassignModal(<?= $row['order_id'] ?>, <?= $row['assign_to'] ?? 'null' ?>)">
                                  <i class="fas fa-user-edit"></i> Re-assign
                                </button>
                                <form method="POST" style="display:inline;">
                                  <input type="hidden" name="orderid" value="<?= $row['order_id'] ?>">
                                  <button type="button" class="btn btn-outline-danger btn-sm btn-action"
                                    onclick="confirmDelete(<?= $row['order_id'] ?>)">Delete</button>
                                </form>
                                <button type="button" class="btn btn-outline-info btn-sm btn-action send-reminder"
                                  data-order-id="<?= $row['order_id'] ?>">
                                  <i class="fas fa-sms"></i> SMS
                                </button>
                              </td>
                            </tr>
                            <?php
                            $counter = 0;
                            foreach ($children as $autoRow):
                              $counter++;
                              $suffix = 'th';
                              if ($counter % 100 >= 11 && $counter % 100 <= 13) {
                                $suffix = 'th';
                              } elseif ($counter % 10 == 1) {
                                $suffix = 'st';
                              } elseif ($counter % 10 == 2) {
                                $suffix = 'nd';
                              } elseif ($counter % 10 == 3) {
                                $suffix = 'rd';
                              }
                              ?>
                              <tr class="row-auto-renewed" data-parent-order="<?= $parentId ?>" style="display:none;">
                                <td>
                                  <strong><?= htmlspecialchars($autoRow['membership_name']) ?></strong>
                                  <span class="auto-seq">Auto-Renewed #<?= $counter . $suffix ?></span>
                                </td>
                                <td><?= (int) $autoRow['bil_cyc_months'] ?> month<?= ((int) $autoRow['bil_cyc_months'] === 1 ? '' : 's') ?></td>
                                <td><?= date('D d M, Y', strtotime($autoRow['start_date'])) ?></td>
                                <td><?= date('D d M, Y', strtotime($autoRow['exp_date'])) ?></td>
                                <td><?= !empty($autoRow['renewal_date']) ? date('D d M, Y', strtotime($autoRow['renewal_date'])) : '<span class="text-muted">—</span>' ?></td>
                                <td><?= !empty($autoRow['due_date']) ? date('D d M, Y', strtotime($autoRow['due_date'])) : '<span class="text-muted">—</span>' ?></td>
                                <td><?= htmlspecialchars($autoRow['inserted_by']) ?></td>
                                <td><?= htmlspecialchars($autoRow['assigned_to_name'] ?? '—') ?></td>
                                <td class="text-nowrap">
                                  <span class="badge badge-light">Auto</span>
                                  <button type="button" class="btn btn-sm btn-outline-primary ms-1" onclick="openEditAutoModal(
                                    <?= $autoRow['auto_id'] ?>,
                                    '<?= htmlspecialchars($autoRow['start_date']) ?>',
                                    '<?= htmlspecialchars($autoRow['exp_date']) ?>',
                                    <?= (int) $autoRow['bil_cyc_months'] ?>,
                                    <?= $autoRow['assign_to'] ?? 0 ?>)">
                                    <i class="ti ti-pencil"></i>Edit
                                  </button>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          <?php endwhile; ?>
                        </tbody>
                      </table>
                    </div><!-- /dataTable-container -->
                  </div><!-- /table-responsive -->
                </div><!-- /card-body employee-detail-body -->
              </div><!-- /card -->
            </div><!-- /col -->
          </div><!-- /row -->

          <script>
            // Delegated click handler so it survives async DOM changes and
            // doesn't depend on the buttons existing when this script first runs.
            document.addEventListener('click', function (e) {
              const btn = e.target.closest('.ar-toggle');
              if (!btn) return;
              e.preventDefault();
              e.stopPropagation();
              const id = btn.getAttribute('data-ar-target');
              if (!id) return;
              const rows = document.querySelectorAll(
                'tr.row-auto-renewed[data-parent-order="' + id + '"]'
              );
              if (!rows.length) return;
              const expanded = btn.classList.toggle('expanded');
              btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
              const count = rows.length;
              btn.title = (expanded ? 'Hide ' : 'Show ') + count + ' renewal' + (count === 1 ? '' : 's');
              rows.forEach(r => r.style.display = expanded ? '' : 'none');
            });
          </script>

              <!-- Add Services Modal -->
              <div class="modal fade" id="associatedProducts">
                <div class="modal-dialog modal-lg">
                  <form method="POST">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h4 class="modal-title">Associated Services</h4>
                      </div>
                      <div class="modal-body">
                        <div class="row mb-2">
                          <input type="hidden" name="member_id" value="<?php echo $client_id; ?>" />
                          <input type="hidden" name="member_name" value="<?php echo $member_name ?? ''; ?>" />
                          <input type="hidden" name="user_id" value="<?php echo $userid ?? ''; ?>" />
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
                            <label for="cycle" class="form-label">Cycle (in months)</label>
                            <select class="form-control" id="cycle" name="cycle_id" required onchange="calculateExpiryDate()">
                              <option value="">Select cycle</option>
                              <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>"><?= $m ?> month<?= $m === 1 ? '' : 's' ?></option>
                              <?php endfor; ?>
                            </select>
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
                        <?php if (isset($user_type) && ($user_type == '1' || $user_type == '2')) { ?>
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
                      <div class="modal-footer">
                        <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" name="addassociatedproduct"
                          id="addassociatedproduct">Submit</button>
                      </div>
                    </div>
                  </form>
                </div><!-- /modal-dialog -->
              </div><!-- /modal#associatedProducts -->
        </div><!-- /#associated-products -->

        <!-- ==================== SECTION: COMMENTS ==================== -->
        <div class="section-heading">
          <div class="sh-icon"><i class="fas fa-comments"></i></div>
          <h4>Comments</h4>
          <div class="sh-line"></div>
        </div>
        <div id="comments" class="section-body">
          <?php echo renderCommentsTabContent($con, $client_id); ?>
        </div>

        <!-- ==================== SECTION: FAMILY ==================== -->
        <?php if ($showFamilyTab): ?>
        <div class="section-heading">
          <div class="sh-icon"><i class="fas fa-users"></i></div>
          <h4>Family Members<?= mh_icon('family') ?></h4>
          <div class="sh-line"></div>
        </div>
          <div id="family-members" class="section-body">
          <div class="row">
            <div class="col-sm-12 col-md-12">
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
          </div><!-- /#family-members -->
        <?php endif; ?>

        <!-- ==================== SECTION: PAYMENT PLANS ==================== -->
        <div class="section-heading">
          <div class="sh-icon"><i class="fas fa-calendar-check"></i></div>
          <h4>Payment Plans</h4>
          <div class="sh-line"></div>
        </div>
        <div id="payment-plans" class="section-body">
          <div class="row">
            <div class="col-sm-12 col-md-12">
              <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row align-items-center">
                    <div class="col-md-10">
                      <h5>Payment Plans</h5>
                    </div>
                    <div class="col-md-2 text-right">
                      <?php
                      $perm_pp = mysqli_fetch_assoc(mysqli_query($con, "SELECT invoice_and_billing FROM dashboard_access WHERE user_type='$user_type'"));
                      if (($perm_pp['invoice_and_billing'] ?? '') === 'Yes'): ?>
                        <a href="payment_plan.php?id=<?= $client_id ?>" class="btn btn-primary btn-sm">
                          <i class="fas fa-plus"></i> Add Plan
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                  <hr>
                  <div class="table-responsive">
                    <div class="dataTable-container" style="max-height:380px;overflow-y:auto;">
                      <table class="table datatable mb-0" style="min-width:900px;">
                        <thead>
                          <tr>
                            <th>Plan Name</th>
                            <th>Type</th>
                            <th>Billing Cycle</th>
                            <th>Installment</th>
                            <th>Total</th>
                            <th>Next Installment</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          $ppFmt = function ($d) {
                            if (empty($d) || $d === '0000-00-00' || $d === '0000-00-00 00:00:00') return '—';
                            $ts = strtotime($d);
                            return $ts ? date('M d, Y', $ts) : '—';
                          };
                          $ppMoney = function ($n) { return '$' . number_format((float) $n, 2); };
                          $ppRes = mysqli_query($con, "SELECT * FROM payment_plans WHERE client_id='" . (int) $client_id . "' ORDER BY plan_id DESC");
                          if ($ppRes && mysqli_num_rows($ppRes) > 0):
                            while ($pp = mysqli_fetch_assoc($ppRes)):
                              $ppActive = strcasecmp($pp['status'] ?? '', 'Active') === 0;
                          ?>
                            <tr>
                              <td><?= htmlspecialchars($pp['plan_name'] ?? '—') ?></td>
                              <td><?= htmlspecialchars($pp['plan_type'] ?? '—') ?></td>
                              <td><?= htmlspecialchars($pp['billing_cycle'] ?? '—') ?></td>
                              <td><?= $ppMoney($pp['installment_amount']) ?></td>
                              <td><?= $ppMoney($pp['total_amount']) ?></td>
                              <td><?= $ppFmt($pp['next_installment_date']) ?></td>
                              <td><?= $ppFmt($pp['start_date']) ?></td>
                              <td><?= $ppFmt($pp['end_date']) ?></td>
                              <td>
                                <span class="badge <?= $ppActive ? 'badge-success' : 'badge-secondary' ?>">
                                  <?= htmlspecialchars($pp['status'] ?: '—') ?>
                                </span>
                              </td>
                              <td>
                                <a href="view_payment_plan.php?plan_id=<?= (int) $pp['plan_id'] ?>" class="btn btn-info btn-sm" title="View">
                                  <i class="fas fa-eye"></i>
                                </a>
                              </td>
                            </tr>
                          <?php
                            endwhile;
                          else:
                          ?>
                            <tr>
                              <td colspan="10" class="text-center text-muted">No payment plans found for this client.</td>
                            </tr>
                          <?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div><!-- /#payment-plans -->

        <!-- ==================== SECTION: QUOTATIONS ==================== -->
        <div class="section-heading">
          <div class="sh-icon"><i class="fas fa-file-invoice"></i></div>
          <h4>Quotations</h4>
          <div class="sh-line"></div>
        </div>
        <div id="quotations" class="section-body">
          <div class="row">
            <div class="col-sm-12 col-md-12">
              <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row align-items-center">
                    <div class="col-md-10">
                      <h5>Quotations</h5>
                    </div>
                    <div class="col-md-2 text-end">
                      <!-- Add button if needed -->
                    </div>
                  </div>
                  <hr>
                  <div class="dataTable-container">
                    <table class="table datatable1" id="tblQuotations" style="width:100%;min-width:900px;">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Quotation SR</th>
                          <th>Date</th>
                          <th>Services</th>
                          <th>Sub-Total</th>
                          <th>Discount</th>
                          <th>Grand Total</th>
                          <th>Status</th>
                          <th>Created By</th>
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
                                <a href="quotations/<?php echo $row['file_name']; ?>" target="_blank"
                                  class="btn btn-sm btn-primary">View File</a>
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
        </div><!-- /#quotations -->

        <!-- ==================== SECTION: MEMBER SERVICES ==================== -->
        <div class="section-heading">
          <div class="sh-icon"><i class="fas fa-cogs"></i></div>
          <h4>Member Services</h4>
          <div class="sh-line"></div>
        </div>
        <div id="member-services" class="section-body">
          <div class="row">
            <div class="col">
              <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row">
                    <div class="col-md-8">
                      <h5>Member Services</h5>
                    </div>
                    <div class="col-md-4 text-end">
                      <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#modalAddService">Add Service</button>
                    </div>
                  </div>
                  <hr>
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
                        SELECT s.id, s.membership_id, m.membership_name, u.user_login, s.added_at,s.description
                        FROM member_services s
                        JOIN memberships m ON s.membership_id=m.membership_id
                        JOIN users u ON s.user_id=u.user_id
                        WHERE s.member_id=$client_id
                        ORDER BY s.added_at DESC");
                      $membershipOptions = [];
                      $_mo = mysqli_query($con, "SELECT membership_id, membership_name FROM memberships ORDER BY membership_name");
                      if ($_mo) {
                        while ($_m = mysqli_fetch_assoc($_mo)) { $membershipOptions[] = $_m; }
                      }
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

                        <!-- Edit modal per row -->
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
                                    $current = (int) $r['membership_id'];
                                    foreach ($membershipOptions as $opt):
                                      $sel = (int) $opt['membership_id'] == $current ? 'selected' : ''; ?>
                                      <option value="<?= (int) $opt['membership_id'] ?>" <?= $sel ?>>
                                        <?= htmlspecialchars($opt['membership_name']) ?>
                                      </option>
                                    <?php endforeach; ?>
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

          <!-- Add Service Modal -->
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
        </div><!-- /#member-services -->

        <!-- ==================== SECTION: BUSINESSES ==================== -->
        <div class="section-heading">
          <div class="sh-icon"><i class="fas fa-building"></i></div>
          <h4>Businesses<?= mh_icon('businesses') ?></h4>
          <div class="sh-line"></div>
        </div>
        <div id="businesses" class="section-body">
          <div class="row">
            <div class="col-sm-12 col-md-12">
              <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row">
                    <div class="col-md-10">
                      <h5>Associated Business(s)</h5>
                    </div>
                    <div class="col-md-2">
                      <button class="btn btn-primary" data-toggle="modal" data-target="#addbusiness">Add
                        Business</button>
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
                          // Pre-fetch reference tables once (avoid re-querying inside the business loop).
                          $statesList = $entityformList = $busactList = [];
                          $_pkS = mysqli_query($con, "SELECT state_id, state, state_code FROM states");
                          if ($_pkS) { while ($_s = mysqli_fetch_array($_pkS)) { $statesList[] = $_s; } }
                          $_pkE = mysqli_query($con, "SELECT entity_form_id, entity_form_name FROM entityform");
                          if ($_pkE) { while ($_e = mysqli_fetch_array($_pkE)) { $entityformList[] = $_e; } }
                          $_pkB = mysqli_query($con, "SELECT busact_id, busact_name FROM busact");
                          if ($_pkB) { while ($_b = mysqli_fetch_array($_pkB)) { $busactList[] = $_b; } }
                          while ($spousedata = mysqli_fetch_array($querr2)) {
                            ?>
                            <tr>
                              <td><?= htmlspecialchars($spousedata['president_name']); ?></td>
                              <td><?= htmlspecialchars($spousedata['bus_name']); ?></td>
                              <td><?= htmlspecialchars($spousedata['ein']); ?></td>
                              <td><?= htmlspecialchars($spousedata['ssn']); ?></td>
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
                                  <div class="modal-header" style="background-color: #272469; color: white;">
                                    <h5 class="modal-title">View Business Details</h5>

                                  </div>
                                  <div class="modal-body row">
                                    <div class="col-md-4"><strong>Business
                                        Name:</strong><br><?= $spousedata['bus_name']; ?>
                                    </div>
                                    <div class="col-md-4"><strong>President
                                        Name:</strong><br><?= $spousedata['president_name']; ?></div>
                                    <div class="col-md-4"><strong>President
                                        DOB:</strong><br><?= $spousedata['president_dob']; ?></div>
                                    <div class="col-md-4 mt-2">
                                      <strong>EIN:</strong><br><?= $spousedata['ein']; ?>
                                    </div>
                                    <div class="col-md-4 mt-2">
                                      <strong>SSN:</strong><br><?= $spousedata['ssn']; ?>
                                    </div>
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
                                    <button class="btn" style="background-color: #272469; color: white;"
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
                                    <div class="modal-header" style="background-color: #272469; color: white;">
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
                                          foreach ($statesList as $_s) {
                                            $selected = ($_s['state_id'] == $spousedata['incorp_state']) ? 'selected' : '';
                                            echo "<option value='{$_s['state_id']}' $selected>{$_s['state']}, {$_s['state_code']}</option>";
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
                                      <?php cf_render_field($con, $client_id, 'work_phone', $fetch_member['phone'] ?? '', 'Phone (work)'); ?>

                                      <!-- Row 10: Entity and Business Type -->
                                      <div class="form-group col-md-6">
                                        <label>Entity Form</label>
                                        <select name="entity_form_id" class="form-control select">
                                          <option value="">--Select--</option>
                                          <?php
                                          foreach ($entityformList as $_e) {
                                            $selected = ($_e['entity_form_id'] == $spousedata['entity_form_id']) ? 'selected' : '';
                                            echo "<option value='{$_e['entity_form_id']}' $selected>{$_e['entity_form_name']}</option>";
                                          }
                                          ?>
                                        </select>
                                      </div>
                                      <div class="form-group col-md-6">
                                        <label>Business Activity</label>
                                        <select name="busact_id" class="form-control select">
                                          <option value="">--Select--</option>
                                          <?php
                                          foreach ($busactList as $_b) {
                                            $selected = ($_b['busact_id'] == $spousedata['busact_id']) ? 'selected' : '';
                                            echo "<option value='{$_b['busact_id']}' $selected>{$_b['busact_name']}</option>";
                                          }
                                          ?>
                                        </select>
                                      </div>

                                    </div>

                                    <div class="modal-footer">
                                      <button type="submit" name="updatebusiness" class="btn"
                                        style="background-color: #272469; color: white;">Update</button>
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
                                      <button type="submit" name="delete" class="btn btn-danger">Yes,
                                        Delete</button>
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

          <!-- Link/Unlink Businesses Section -->
          <div class="row mt-4">
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
                            style="max-width: 100%; width: 100%;" required>
                            <option value="">-- Select Business --</option>
                            <?php
                            $businesses = mysqli_query($con, "SELECT * FROM members");
                            while ($biz = mysqli_fetch_array($businesses)) {
                              $selected = isset($_POST['business_id']) && $_POST['business_id'] == $biz['member_id'] ? 'selected' : '';

                              $client_name = htmlspecialchars(trim("{$biz['name']} {$biz['mname']} {$biz['lname']}"));
                              $business_name = htmlspecialchars(trim($biz['bus_name']));

                              echo "<option value='{$biz['member_id']}' $selected 
                                data-fullname='" . strtolower($client_name) . "' 
                                data-business='" . strtolower($business_name) . "'>
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
                                  <td>" . htmlspecialchars($president) . "</td>
                                  <td>" . htmlspecialchars($linked['bus_name']) . "</td>
                                  <td>" . htmlspecialchars($linked['ss']) . "</td>
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
        </div><!-- /#businesses -->

        <!-- ==================== SECTION: PROJECTS ==================== -->
        <div class="section-heading">
          <div class="sh-icon"><i class="fas fa-project-diagram"></i></div>
          <h4>Projects</h4>
          <div class="sh-line"></div>
        </div>
        <div id="projects" class="section-body">
          <div class="row">
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
                          $status_project = $project['project_status'];
                          $badgeClass = $status_project === 'completed' ? 'bg-success' : ($status_project === 'closed' ? 'bg-danger' : 'bg-warning');
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
                                <?php echo ucfirst($status_project); ?>
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
        </div><!-- /#projects -->

      </div><!-- /#clientPageContent -->
      <!-- End Page Sections -->

    </div>
  </div>



  <?php if ($showFamilyTab): ?>
    <div class="modal fade" id="addSpouse">
      <div class="modal-dialog modal-lg">
        <form method="POST">
          <div class="modal-content">
            <div class="modal-header">
              <h4 class="modal-title">Add MEMBER</h4>
            </div>
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

            <div class="modal-footer">
              <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary" name="addspouse">Submit</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

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
                      <input class="form-control" required="required" name="bus_city" type="text" placeholder="City">
                  </div>
                  <div class="col-md-6">
                    <label for="product" class="form-label">State</label><span class="text-danger">*
                      <input class="form-control" required="required" name="bus_state" type="text" placeholder="State">
                  </div>
                  <div class="col-md-6">
                    <label for="product" class="form-label">Zip Code</label><span class="text-danger">*
                      <input class="form-control" required="required" name="bus_zip" type="text" placeholder="Zip Code">
                  </div>
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
                    <input class="form-control" name="bus_start_date" type="date" placeholder="Business Start Date">
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
                        <option value="<?= $ICr["state_id"] ?>"><?= $ICr["state"] ?>,
                          <?= $ICr["state_code"] ?>
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
                        <option value="<?= $MTr["entity_form_id"] ?>"><?= $MTr["entity_form_name"] ?>
                        </option>
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

  <!-- The Comments Modal -->
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
                    echo '<button type="button" class="btn btn-sm btn-outline-primary me-1 mb-1" onclick="addPhraseRegular(\'' . htmlspecialchars($row['phrase']) . '\')">' . htmlspecialchars($row['phrase']) . '</button>';
                  }
                  ?>
                  <a href="quickphrases.php" class="btn btn-primary btn-sm">Edit Phrases</a>
                </div>

                <!-- Comment box -->
                <label for="comment_details" class="form-label">Comment (edit or type)</label>
                <textarea class="ckeditor" name="comment_details" id="comment_details_regular"></textarea>
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
            <h4 class="modal-title">Add Invoice Comment</h4>
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

  <div class="modal fade" id="addBankStatementComment">
    <div class="modal-dialog modal-lg">
      <form method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title">Add Bank Statement Comment</h4>
          </div>
          <div class="modal-body">
            <div class="row mb-2">
              <div class="col-md-12">
                <label class="form-label">Quick phrases</label>
                <div class="mb-2">
                  <?php
                  $uid = (int) $_SESSION['user_id'];
                  $res = mysqli_query(
                    $con,
                    "SELECT phrase FROM user_quick_phrases WHERE user_id=$uid ORDER BY id"
                  );
                  while ($row = mysqli_fetch_assoc($res)) {
                    echo '<button type="button" class="btn btn-sm btn-outline-primary me-1 mb-1" onclick="addPhraseBankStatement(\'' . htmlspecialchars($row['phrase']) . '\')">' . htmlspecialchars($row['phrase']) . '</button>';
                  }
                  ?>
                  <a href="quickphrases.php" class="btn btn-primary btn-sm">Edit Phrases</a>
                </div>

                <label for="comment_details_bank_statement" class="form-label">Comment (edit or type)</label>
                <textarea class="ckeditor" name="comment_details"
                  id="comment_details_bank_statement"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary" name="addBankStatementComment">Submit</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Comment Modals -->
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

  <div class="modal fade" id="editCommentModal">
    <div class="modal-dialog modal-lg">
      <form method="POST">
        <div class="modal-content">
          <div class="modal-header" style="background-color: #272469; color: white;">
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
              style="background-color: #272469; color: white;">Update</button>
          </div>
        </div>
      </form>
    </div>
  </div>

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

  <!-- New Comment Modals -->
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

  <div class="modal fade" id="editNewCommentModal">
    <div class="modal-dialog modal-lg">
      <form method="POST">
        <div class="modal-content">
          <div class="modal-header" style="background-color: #272469; color: white;">
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
              style="background-color: #272469; color: white;">Update</button>
          </div>
        </div>
      </form>
    </div>
  </div>

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

  <!-- Warning Modal -->
  <div class="modal fade" id="modalWarning" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title">Important Alert</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <textarea id="warningText" class="form-control" rows="4"
            placeholder="Type the warning everyone must read..."><?= $warn['warning'] ?? '' ?></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-warning" onclick="saveWarning()">Save Alert</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ACH Alert Modal -->
  <div class="modal fade" id="modalAch" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="fas fa-university me-2"></i>Add ACH Alert</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Total Payment <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" step="0.01" min="0" id="achTotal" class="form-control" placeholder="0.00">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Payment Initiated Date &amp; Time <span class="text-danger">*</span></label>
            <input type="datetime-local" id="achInitiated" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Payment Collection Date &amp; Time <span class="text-danger">*</span></label>
            <input type="datetime-local" id="achCollection" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Client Bank Name</label>
            <input type="text" id="achBank" class="form-control" placeholder="(optional)">
          </div>
          <div class="mb-3">
            <label class="form-label">Note / Description</label>
            <textarea id="achNote" class="form-control" rows="3" placeholder="Optional note..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" onclick="saveAch()">Submit Alert</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ACH Alert Detail Modal -->
  <div class="modal fade" id="modalAchDetail" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="fas fa-university me-2"></i>ACH Alert <span id="achDetailId"></span></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="ach-detail-row"><span class="lbl">Total Payment</span><span class="val" id="achDetailAmount"></span></div>
          <div class="ach-detail-row"><span class="lbl">Status</span><span class="val"><span class="badge" id="achDetailStatus"></span></span></div>
          <div class="ach-detail-row"><span class="lbl">Initiated</span><span class="val" id="achDetailInitiated"></span></div>
          <div class="ach-detail-row"><span class="lbl">Collection</span><span class="val" id="achDetailCollection"></span></div>
          <div class="ach-detail-row"><span class="lbl">Bank</span><span class="val" id="achDetailBank"></span></div>
          <div class="ach-detail-row"><span class="lbl">Note</span><span class="val" id="achDetailNote"></span></div>
          <div class="ach-detail-row"><span class="lbl">Collected</span><span class="val" id="achDetailCollected"></span></div>
          <div class="ach-detail-row"><span class="lbl">Added By</span><span class="val" id="achDetailAddedBy"></span></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Auto-Renewed Order Modal -->
  <div class="modal fade" id="editAutoModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="post">
        <div class="modal-content">
          <div class="modal-header bg-info text-white">
            <h5 class="modal-title">Edit Auto-Renewed Order</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="edit_auto_id" id="edit_auto_id">
            <div class="mb-3">
              <label class="form-label">Start Date</label>
              <input type="date" class="form-control" name="auto_start" id="auto_start" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Expiry Date</label>
              <input type="date" class="form-control" name="auto_exp" id="auto_exp" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Cycle (days)</label>
              <input type="number" class="form-control" name="auto_cycle" id="auto_cycle" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Re-assign to</label>
              <select name="auto_assigned" id="auto_assigned" class="form-control">
                <option value="">-- Nobody --</option>
                <?php
                $uu = mysqli_query($con, "SELECT user_id, user_name FROM users WHERE user_login_status=1 ORDER BY user_name");
                while ($u = mysqli_fetch_assoc($uu)) {
                  echo '<option value="' . $u['user_id'] . '">' . htmlspecialchars($u['user_name']) . '</option>';
                }
                ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" name="update_auto_renewed">Save changes</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Login Secured Area Modal -->
  <div class="modal fade" id="login_secured_area">
    <div class="modal-dialog modal-md">
      <form method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title">Login into Secured Area</h4>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <i class="fas fa-times text-danger"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="row mb-2">
              <div class="col-md-12 mt-2">
                <input name="user_secured" type="text" class="form-control my-2" placeholder="Username">
                <input name="pass_secured" type="password" class="form-control" placeholder="Password">
              </div>
            </div>
          </div>
          <div class="modal-footer" style="justify-content: center;">
            <button type="submit" name="loginSecuredArea" class="btn btn-primary">Login</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Login Bank Area Modal -->
  <div class="modal fade" id="login_bank_area">
    <div class="modal-dialog modal-md">
      <form method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h4 class="modal-title">Login to Access Bank Statements</h4>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <i class="fas fa-times text-danger"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="row mb-2">
              <div class="col-md-12 mt-2">
                <label class="form-label">Username</label>
                <input name="user_bank" type="text" class="form-control my-2" placeholder="Enter username" required>
                <label class="form-label">Password</label>
                <input name="pass_bank" type="password" class="form-control" placeholder="Enter password" required>
              </div>
            </div>
          </div>
          <div class="modal-footer" style="justify-content: center;">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" name="loginBankArea" class="btn btn-success">
              <i class="ti ti-lock-open"></i> Access Bank Statements
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Reminder Drawer ─────────────────────────────────────── -->
  <div class="rd-backdrop" id="rdBackdrop" onclick="closeReminderDrawer()"></div>
  <aside class="rd-drawer" id="rdDrawer" aria-hidden="true" aria-label="Reminders">
    <div class="rd-header">
      <div>
        <h5><i class="fas fa-bell me-1"></i> Reminders</h5>
        <small>Quick add — open calendar for advanced options</small>
      </div>
      <div class="rd-header-actions">
        <a href="reminder_calender.php?client_id=<?= (int) ($_GET['id'] ?? 0) ?>" target="_blank" rel="noopener"
           class="rd-open-cal" title="Open Reminder Calendar">
          <i class="far fa-calendar-alt"></i>
          <span>Calendar</span>
        </a>
        <button type="button" class="rd-close" onclick="closeReminderDrawer()" aria-label="Close">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>

    <div class="rd-body">
      <div class="rd-section-title">Quick add</div>
      <form class="rd-quick" id="rdQuickForm" onsubmit="return rdQuickAdd(event)">
        <input type="hidden" id="rdEditId" value="">
        <input type="text" class="form-control" id="rdTitle" placeholder="Reminder title" maxlength="120" required>
        <input type="datetime-local" class="form-control" id="rdDate" required>
        <textarea class="form-control" id="rdDesc" rows="2" placeholder="Notes (optional)" maxlength="500"></textarea>
        <div class="d-flex justify-content-between align-items-center mt-1">
          <button type="button" class="btn btn-link btn-sm p-0" id="rdCancelEdit" style="display:none;" onclick="rdResetForm()">
            Cancel edit
          </button>
          <div class="ms-auto d-flex align-items-center gap-2">
            <a href="reminder_calender.php?client_id=<?= (int) ($_GET['id'] ?? 0) ?>" target="_blank" rel="noopener"
               class="btn btn-outline-success btn-sm" title="Open Reminder Calendar">
              <i class="far fa-calendar-alt me-1"></i> Advance
            </a>
            <button type="submit" class="btn btn-success btn-sm" id="rdSaveBtn">
              <i class="fas fa-plus me-1"></i> Add reminder
            </button>
          </div>
        </div>
      </form>

      <div class="rd-section-title">
        <span>Existing</span>
        <span class="badge" id="rdCount">0</span>
      </div>
      <div class="rd-list" id="rdList">
        <div class="rd-empty" id="rdEmpty">
          <i class="far fa-calendar-check"></i>
          <div>No reminders yet</div>
        </div>
      </div>
    </div>

    <div class="rd-footer">
      <a href="reminder_calender.php?client_id=<?= (int) ($_GET['id'] ?? 0) ?>" target="_blank" rel="noopener">
        <i class="fas fa-external-link-alt"></i>
        Open in Reminder Calendar
      </a>
    </div>
  </aside>

  <?php include "includes/footer.php"; ?>
  <?php include "includes/scripts.php"; ?>

  <!-- ALL JAVASCRIPT -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
  <script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>

  <!-- ── Chart.js Initialization ───────────────────────────── -->
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    // Billing Trend: grouped bar (Invoiced vs Paid)
    const ctxBilling = document.getElementById('chartBillingTrend');
    if (ctxBilling) {
      new Chart(ctxBilling, {
        type: 'bar',
        data: {
          labels: <?= json_encode($billing_labels) ?>,
          datasets: [
            {
              label: 'Invoiced',
              data: <?= json_encode($billing_total) ?>,
              backgroundColor: 'rgba(67,97,238,.75)',
              borderColor: '#3a0ca3',
              borderWidth: 1.5,
              borderRadius: 5,
              borderSkipped: false
            },
            {
              label: 'Paid',
              data: <?= json_encode($billing_paid) ?>,
              backgroundColor: 'rgba(111,217,67,.75)',
              borderColor: '#1f1d54',
              borderWidth: 1.5,
              borderRadius: 5,
              borderSkipped: false
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                font: { size: 11 },
                callback: val => '$' + val.toLocaleString()
              },
              grid: { color: 'rgba(0,0,0,.05)' }
            },
            x: {
              ticks: { font: { size: 11 } },
              grid: { display: false }
            }
          },
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: ctx => ` ${ctx.dataset.label}: $${ctx.parsed.y.toLocaleString()}`
              }
            }
          }
        }
      });
    }

    // Services Trend Bar
    const ctxBar = document.getElementById('chartServicesTrend');
    if (ctxBar) {
      new Chart(ctxBar, {
        type: 'bar',
        data: {
          labels: <?= json_encode($chart_labels) ?>,
          datasets: [{
            label: 'Services Added',
            data: <?= json_encode($chart_values) ?>,
            backgroundColor: 'rgba(111,217,67,.7)',
            borderColor: '#1f1d54',
            borderWidth: 1.5,
            borderRadius: 6,
            borderSkipped: false
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              ticks: { stepSize: 1, font: { size: 11 } },
              grid: { color: 'rgba(0,0,0,.05)' }
            },
            x: {
              ticks: { font: { size: 11 } },
              grid: { display: false }
            }
          },
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: ctx => ` ${ctx.parsed.y} service(s) added`
              }
            }
          }
        }
      });
    }
  });
  </script>

  <script>
    // Initialize DataTables for all tables on the page
    document.addEventListener("DOMContentLoaded", function () {
      document.querySelectorAll('table.datatable1, table.datatable3').forEach(function (table) {
        if (table.dataset.datatableInitialized === '1') return;
        table.dataset.datatableInitialized = '1';
        new simpleDatatables.DataTable(table, {
          perPage: 10,
          perPageSelect: [10, 25, 50]
        });
      });
    });

    // Project Comments: toggle "See more" / "See less" (delegated so it survives pagination)
    document.addEventListener('click', function (e) {
      var toggle = e.target.closest('.pc-toggle');
      if (!toggle) return;
      var comment = toggle.parentElement.querySelector('.pc-comment');
      if (!comment) return;
      var clamped = comment.classList.toggle('clamped');
      toggle.textContent = clamped ? 'See more' : 'See less';
    });

    // Activity switch
    $(document).ready(function () {
      $('.activity-switch').change(function () {
        var $sw = $(this);
        var memberId = $sw.data('member-id');
        var status = $sw.is(':checked') ? 1 : 0;

        $sw.prop('disabled', true);

        $.ajax({
          url: 'update_status.php',
          type: 'POST',
          data: { member_id: memberId, status: status },
          success: function () {
            // Update the toggle's adjacent label
            $sw.siblings('span').text(status == 1 ? 'Active' : 'Inactive');

            // Update the hero status pill
            var $pill = $('.ch-pill.status-active, .ch-pill.status-inactive');
            if (status == 1) {
              $pill.removeClass('status-inactive').addClass('status-active')
                   .html('<i class="fas fa-circle"></i> Active');
            } else {
              $pill.removeClass('status-active').addClass('status-inactive')
                   .html('<i class="fas fa-circle"></i> Inactive');
            }

            // Keep URL in sync so refreshes / shared links reflect new status
            var newUrl = window.location.pathname + '?id=' + memberId + '&status=' + status;
            window.history.replaceState({ path: newUrl }, '', newUrl);
          },
          error: function (xhr, _s, error) {
            // Revert toggle on failure so UI matches DB
            $sw.prop('checked', status == 1 ? false : true);
            console.error(error);
          },
          complete: function () {
            $sw.prop('disabled', false);
          }
        });
      });
    });

    // Calculate expiry date as: start + N months - 1 day (so expiry is the last day of the period).
    function calculateExpiryDate() {
      const startVal = document.getElementById('start_date').value;
      const cycleMonths = parseInt(document.getElementById('cycle').value) || 0;
      if (!startVal || cycleMonths <= 0) return;

      const start = new Date(startVal + 'T00:00:00');
      const expiry = new Date(start);
      expiry.setMonth(expiry.getMonth() + cycleMonths);
      expiry.setDate(expiry.getDate() - 1);

      const yyyy = expiry.getFullYear();
      const mm = String(expiry.getMonth() + 1).padStart(2, '0');
      const dd = String(expiry.getDate()).padStart(2, '0');
      document.getElementById('expiry_date').value = `${yyyy}-${mm}-${dd}`;
    }

    // Confirm delete
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

    // Prevent form resubmission
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }

    // Select2 for business dropdown
    $(document).ready(function () {
      $('#business_id').select2({
        placeholder: "-- Select Business --",
        allowClear: true,
        dropdownParent: $('body'),
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

    // SMS/Email toggle
    document.querySelectorAll(".sms-switch, .email-switch").forEach(function (toggle) {
      toggle.addEventListener("change", function () {
        let clientId = this.dataset.clientId;
        let smsEnabled = document.getElementById("smsSwitch" + clientId).checked ? 1 : 0;
        let emailEnabled = document.getElementById("emailSwitch" + clientId).checked ? 1 : 0;
        fetch("update_sms_email_btn.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `toggle_buttons=1&client_id=${clientId}&sms_toggle=${smsEnabled}&email_toggle=${emailEnabled}`
        });
      });
    });

    // AJAX reminder
    $(document).on('click', '.send-reminder', function () {
      const btn = $(this);
      const orderId = btn.data('order-id');

      Swal.fire({
        title: 'Are you sure?',
        text: 'Do you want to send the SMS reminder?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, send it',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.fire({
          title: 'Sending…',
          text: 'Please wait while we send the SMS reminder.',
          allowOutsideClick: false,
          didOpen: () => Swal.showLoading()
        });

        $.post('send_order_sim_reminder.php', { order_id: orderId })
          .done(function (res) {
            if (res.status === 'ok') {
              Swal.fire({ icon: 'success', title: 'Sent!', text: res.message });
            } else {
              Swal.fire('Error', res.message, 'error');
            }
          })
          .fail(function () {
            Swal.fire('Error', 'Request failed.', 'error');
          });
      });
    });

    // Comment modal functions
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

    // New comment modal functions
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

    // Print functions
    function printComments() {
      var printContents = document.getElementById('printableComments').innerHTML;
      var printWindow = window.open('', '', 'height=600,width=800');
      printWindow.document.write('<html><head><title>All Comments</title></head><body>');
      printWindow.document.write('<button onclick="window.print()" style="position: fixed; top: 20px; right: 20px; padding: 10px 20px; background-color: #007BFF; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">🖨️ Print</button>');
      printWindow.document.write(printContents);
      printWindow.document.write('</body></html>');
      printWindow.document.close();
    }

    function printNewComments() {
      var printContents = document.getElementById('printableNewComments').innerHTML;
      var printWindow = window.open('', '', 'height=600,width=800');
      printWindow.document.write('<html><head><title>All Invoice Comments</title></head><body>');
      printWindow.document.write('<button onclick="window.print()" style="position: fixed; top: 20px; right: 20px; padding: 10px 20px; background-color: #007BFF; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">🖨️ Print</button>');
      printWindow.document.write(printContents);
      printWindow.document.write('</body></html>');
      printWindow.document.close();
    }

    function printBankStatementComments() {
      var printContents = document.getElementById('printableBankStatementComments').innerHTML;
      var printWindow = window.open('', '', 'height=600,width=800');
      printWindow.document.write('<html><head><title>All Bank Statement Comments</title></head><body>');
      printWindow.document.write('<button onclick="window.print()" style="position: fixed; top: 20px; right: 20px; padding: 10px 20px; background-color: #007BFF; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">Print</button>');
      printWindow.document.write(printContents);
      printWindow.document.write('</body></html>');
      printWindow.document.close();
    }

    // Warning functions
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
      const msgEl = document.querySelector('.fv-alert-item[data-warn-id="' + id + '"] .fv-alert-msg');
      const current = msgEl ? msgEl.innerText.trim() : '';
      Swal.fire({
        title: 'Edit warning — Client ID: <?= (int)$client_id ?>', input: 'textarea',
        inputValue: current,
        showCancelButton: true, confirmButtonText: 'Save'
      }).then(r => {
        if (r.isConfirmed) apiCall({ action: 'edit', id: id, warning: r.value }, () => location.reload());
      });
    }

    function delWarning(id) {
      Swal.fire({ title: 'Delete warning?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' })
        .then(r => { if (r.isConfirmed) apiCall({ action: 'delete', id: id }, () => location.reload()); });
    }

    // ── ACH Alerts ──────────────────────────────────────────────
    const ACH_MEMBER_ID = <?= (int)$client_id ?>;

    // Alert button -> choose between Regular and ACH alert.
    function openAlertChoice() {
      Swal.fire({
        title: 'Choose Alert Type',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '<i class="fas fa-exclamation-triangle"></i> Regular Alert',
        denyButtonText: '<i class="fas fa-university"></i> ACH Alert',
        confirmButtonColor: '#f0ad4e',
        denyButtonColor: '#0d6efd',
        cancelButtonText: 'Close'
      }).then(r => {
        if (r.isConfirmed) {
          new bootstrap.Modal(document.getElementById('modalWarning')).show();
        } else if (r.isDenied) {
          new bootstrap.Modal(document.getElementById('modalAch')).show();
        }
      });
    }

    function achApi(data, success) {
      fetch('ach_payment_collector.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(data).toString()
      }).then(r => r.text())
        .then(res => res === 'OK' ? success() : Swal.fire('Error', res, 'error'));
    }

    function saveAch() {
      const total = document.getElementById('achTotal').value.trim();
      const init  = document.getElementById('achInitiated').value;
      const coll  = document.getElementById('achCollection').value;
      const bank  = document.getElementById('achBank').value.trim();
      const note  = document.getElementById('achNote').value.trim();

      if (!total || parseFloat(total) <= 0) return Swal.fire('Error', 'Total payment is required', 'error');
      if (!init)  return Swal.fire('Error', 'Payment initiated date is required', 'error');
      if (!coll)  return Swal.fire('Error', 'Collection date is required', 'error');

      achApi({
        action: 'save', member_id: ACH_MEMBER_ID, total_payment: total,
        initiated_at: init, collection_at: coll, bank_name: bank, note: note
      }, () => location.reload());
    }

    function achDetail(btn) {
      const d = btn.dataset;
      document.getElementById('achDetailId').textContent         = '#' + d.id;
      document.getElementById('achDetailAmount').textContent     = d.amount;
      const stEl = document.getElementById('achDetailStatus');
      stEl.textContent = d.status;
      stEl.className   = 'badge text-capitalize ' + (d.statusClass || 'bg-secondary');
      document.getElementById('achDetailInitiated').textContent  = d.initiated;
      document.getElementById('achDetailCollection').textContent = d.collection;
      document.getElementById('achDetailBank').textContent       = d.bank;
      document.getElementById('achDetailNote').textContent       = d.note;
      document.getElementById('achDetailCollected').textContent  = d.collected;
      document.getElementById('achDetailAddedBy').textContent    = d.addedBy;
      new bootstrap.Modal(document.getElementById('modalAchDetail')).show();
    }

    function achCollect(id) {
      Swal.fire({ title: 'Mark as collected?', icon: 'question', showCancelButton: true, confirmButtonColor: '#198754', confirmButtonText: 'Yes, collected' })
        .then(r => { if (r.isConfirmed) achApi({ action: 'collect', id: id }, () => location.reload()); });
    }

    function achCancel(id) {
      Swal.fire({ title: 'Cancel this ACH alert?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, cancel' })
        .then(r => { if (r.isConfirmed) achApi({ action: 'cancel', id: id }, () => location.reload()); });
    }

    // ── Urgent collection-date reminder pop-up ──────────────────
    // Snooze (remind later) = 45 min via localStorage. Stop reminding shows
    // exactly one more time on the next visit, then never again.
    const ACH_DUE = <?= json_encode(array_map(function ($a) {
      return [
        'id' => (int)$a['id'],
        'total' => (float)$a['total_payment'],
        'initiated' => $a['initiated_at'],
        'collection' => $a['collection_at'],
      ];
    }, $ach_due)) ?>;

    function achReminderState(id) { return localStorage.getItem('ach_rem_' + id) || ''; }
    function achSnoozeUntil(id)   { return parseInt(localStorage.getItem('ach_snooze_' + id) || '0', 10); }

    function showAchReminders(queue) {
      if (!queue.length) return;
      const a = queue.shift();

      // Respect a still-active 45-minute snooze.
      if (Date.now() < achSnoozeUntil(a.id)) return showAchReminders(queue);

      const state = achReminderState(a.id);
      if (state === 'done') return showAchReminders(queue);
      // If we are in the "one last time" state, this showing is the final one.
      if (state === 'stopping') localStorage.setItem('ach_rem_' + a.id, 'done');

      const fmt = v => v && v !== '0000-00-00 00:00:00' ? v : '—';
      Swal.fire({
        icon: 'warning',
        title: '⚠ ACH Payment Due',
        html: `<div style="text-align:left">
                 <p><strong>Total Payment:</strong> $${Number(a.total).toLocaleString(undefined, {minimumFractionDigits: 2})}</p>
                 <p><strong>Initiated:</strong> ${fmt(a.initiated)}</p>
                 <p><strong>Collection Date:</strong> ${fmt(a.collection)}</p>
               </div>`,
        showCancelButton: true,
        confirmButtonText: 'Remind me later',
        cancelButtonText: 'Stop reminding',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        allowOutsideClick: false
      }).then(r => {
        if (r.isConfirmed) {
          // Remind again after 45 minutes.
          localStorage.setItem('ach_snooze_' + a.id, String(Date.now() + 45 * 60 * 1000));
        } else if (r.dismiss === Swal.DismissReason.cancel) {
          // Stop: if not already finalised, allow exactly one more showing later.
          if (achReminderState(a.id) !== 'done') localStorage.setItem('ach_rem_' + a.id, 'stopping');
        }
        showAchReminders(queue);
      });
    }

    document.addEventListener('DOMContentLoaded', function () {
      if (ACH_DUE.length) showAchReminders(ACH_DUE.slice());
    });

    // Business category: toggle the activity/NAICS line under the industry header
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.ch-cat-toggle');
      if (!btn) return;
      const wrap = btn.closest('.ch-cat');
      const open = !wrap.classList.contains('is-open');
      wrap.classList.toggle('is-open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    // Reassign modal
    const users = [
      <?php
      $users_q = mysqli_query($con, "SELECT user_id, user_name FROM users WHERE user_login_status = 1 ORDER BY user_name");
      while ($u = mysqli_fetch_assoc($users_q)) {
        echo "{ id: {$u['user_id']}, name: '" . addslashes($u['user_name']) . "' },";
      }
      ?>
    ];

    function openReassignModal(orderId, currentUserId) {
      let optionsHtml = '<option value="">-- Select User --</option>';
      users.forEach(user => {
        const selected = (user.id == currentUserId) ? 'selected' : '';
        optionsHtml += `<option value="${user.id}" ${selected}>${user.name}</option>`;
      });

      Swal.fire({
        title: 'Re-assign Task',
        html: `
          <input type="hidden" id="swal-order-id" value="${orderId}">
          <select id="swal-assigned-user" class="swal2-select" style="width: 100%; padding: 8px;" required>
            ${optionsHtml}
          </select>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Save Changes',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
          const assignedUser = document.getElementById('swal-assigned-user').value;
          if (!assignedUser) {
            Swal.showValidationMessage('Please select a user');
            return false;
          }
          return { order_id: orderId, assigned_users: assignedUser };
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const data = result.value;

          fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              'assigntask': '1',
              'order_id': data.order_id,
              'assigned_users': data.assigned_users
            })
          })
            .then(response => response.text())
            .then(() => {
              Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Task has been re-assigned successfully.',
                timer: 2000,
                showConfirmButton: false
              }).then(() => {
                location.reload();
              });
            })
            .catch(() => {
              Swal.fire('Error', 'Something went wrong. Please try again.', 'error');
            });
        }
      });
    }

    // Edit auto modal
    function openEditAutoModal(id, start, exp, cycle, assigned) {
      $('#edit_auto_id').val(id);
      $('#auto_start').val(start);
      $('#auto_exp').val(exp);
      $('#auto_cycle').val(cycle);
      $('#auto_assigned').val(assigned);
      $('#editAutoModal').modal('show');
    }

    // Manual SMS
    function sendManualSms(clientId) {
      const smsPhone = <?= json_encode($client_phone ?? '') ?>;
      const recipientPhone = smsPhone && smsPhone.trim() !== ""
        ? smsPhone
        : "<span class='text-danger'>No phone on file</span>";
      Swal.fire({
        title: 'Send manual SMS',
        html:
          "<div style='margin-bottom:10px;font-size:0.95em;color:#555;'>" +
          "<i class='ti ti-device-mobile'></i> Will be sent to: <b>" + recipientPhone + "</b>" +
          "</div>",
        input: 'textarea',
        inputLabel: 'Message',
        inputPlaceholder: 'Type your SMS here…',
        inputAttributes: { maxlength: 1600 },
        showCancelButton: true,
        confirmButtonText: 'Send',
        confirmButtonColor: '#25D366',
        showLoaderOnConfirm: true,
        preConfirm: (text) => {
          if (!text.trim()) {
            Swal.showValidationMessage('Message cannot be empty');
            return false;
          }
          return $.post('send_manual_sms.php', {
            client_id: clientId,
            sms_body: text.trim()
          }, null, 'text')
            .then(r => r)
            .catch(xhr => Swal.showValidationMessage(xhr.responseText || 'Send failed'));
        }
      }).then(res => {
        if (res.isConfirmed) Swal.fire('Sent!', 'SMS delivered.', 'success');
      });
    }

    // Action alert
    const CLIENT_ID = <?= (int) $_GET['id'] ?>;

    /* ── Reminder Drawer ─────────────────────────────────────── */
    function rdEsc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[c]));
    }

    function rdFormatDate(s) {
      if (!s) return '';
      const d = new Date(s.replace(' ', 'T'));
      if (isNaN(d)) return s;
      return d.toLocaleString(undefined, {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit'
      });
    }

    function rdIsPast(s) {
      if (!s) return false;
      const d = new Date(s.replace(' ', 'T'));
      return !isNaN(d) && d.getTime() < Date.now();
    }

    function openReminderDrawer() {
      document.getElementById('rdBackdrop').classList.add('show');
      const drawer = document.getElementById('rdDrawer');
      drawer.classList.add('show');
      drawer.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      rdResetForm();
      rdLoadList();
    }

    function closeReminderDrawer() {
      document.getElementById('rdBackdrop').classList.remove('show');
      const drawer = document.getElementById('rdDrawer');
      drawer.classList.remove('show');
      drawer.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && document.getElementById('rdDrawer').classList.contains('show')) {
        closeReminderDrawer();
      }
    });

    function rdResetForm() {
      document.getElementById('rdEditId').value = '';
      document.getElementById('rdTitle').value = '';
      document.getElementById('rdDate').value = '';
      document.getElementById('rdDesc').value = '';
      document.getElementById('rdSaveBtn').innerHTML = '<i class="fas fa-plus me-1"></i> Add reminder';
      document.getElementById('rdCancelEdit').style.display = 'none';
    }

    function rdLoadList() {
      const $list = $('#rdList');
      $list.html('<div class="rd-empty"><i class="fas fa-spinner fa-spin"></i><div>Loading…</div></div>');

      $.post('mem_rem_clndr.php', { action: 'list', client_id: CLIENT_ID }, res => {
        if (!res || res.status !== 'ok' || !Array.isArray(res.data) || res.data.length === 0) {
          $('#rdCount').text('0');
          $list.html(`
            <div class="rd-empty">
              <i class="far fa-calendar-check"></i>
              <div>No reminders yet</div>
            </div>
          `);
          return;
        }

        $('#rdCount').text(res.data.length);
        const html = res.data.map(r => {
          const past = rdIsPast(r.reminder_date);
          const desc = (r.reminder_description_plain || '').trim();
          return `
            <div class="rd-item${past ? ' past' : ''}">
              <div class="rd-item-row">
                <div class="rd-item-main">
                  <div class="rd-item-title" title="${rdEsc(r.reminder_title)}">${rdEsc(r.reminder_title)}</div>
                  <div class="rd-item-meta">
                    <i class="far fa-clock"></i>
                    <span>${rdEsc(rdFormatDate(r.reminder_date))}</span>
                    ${past ? '<span class="dot"></span><span style="color:#b91c1c;font-weight:500;">Past due</span>' : ''}
                  </div>
                  ${desc ? `<div class="rd-item-desc">${rdEsc(desc)}</div>` : ''}
                </div>
                <div class="rd-item-actions">
                  <button class="rd-icon-btn" title="Edit"
                    onclick='rdBeginEdit(${JSON.stringify({
                      id: r.reminder_id,
                      title: r.reminder_title,
                      date: r.reminder_date,
                      desc: r.reminder_description_plain || ''
                    }).replace(/'/g, "&#39;")})'>
                    <i class="fas fa-pen"></i>
                  </button>
                  <button class="rd-icon-btn danger" title="Delete"
                    onclick="rdDelete(${r.reminder_id})">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          `;
        }).join('');
        $list.html(html);
      }, 'json').fail(() => {
        $list.html('<div class="rd-empty"><i class="fas fa-exclamation-triangle"></i><div>Failed to load</div></div>');
      });
    }

    function rdBeginEdit(d) {
      document.getElementById('rdEditId').value = d.id;
      document.getElementById('rdTitle').value = d.title || '';
      document.getElementById('rdDate').value = (d.date || '').replace(' ', 'T').slice(0, 16);
      document.getElementById('rdDesc').value = d.desc || '';
      document.getElementById('rdSaveBtn').innerHTML = '<i class="fas fa-save me-1"></i> Save changes';
      document.getElementById('rdCancelEdit').style.display = 'inline-block';
      document.getElementById('rdTitle').focus();
      document.querySelector('.rd-body').scrollTo({ top: 0, behavior: 'smooth' });
    }

    function rdQuickAdd(e) {
      e.preventDefault();
      const id    = document.getElementById('rdEditId').value;
      const title = document.getElementById('rdTitle').value.trim();
      const date  = document.getElementById('rdDate').value;
      const desc  = document.getElementById('rdDesc').value;

      if (!title || !date) {
        Swal.fire({ icon: 'warning', title: 'Title and date are required', timer: 1800, showConfirmButton: false, customClass: { container: 'rd-swal' } });
        return false;
      }

      const payload = {
        action: id ? 'edit' : 'add',
        client_id: CLIENT_ID,
        reminder_id: id || '',
        reminder_title: title,
        reminder_description: desc,
        reminder_date: date.replace('T', ' ')
      };

      $('#rdSaveBtn').prop('disabled', true);
      $.post('mem_rem_clndr.php', payload, res => {
        $('#rdSaveBtn').prop('disabled', false);
        if (res.status === 'ok') {
          rdResetForm();
          rdLoadList();
          Swal.fire({ icon: 'success', title: res.message, timer: 1200, showConfirmButton: false, customClass: { container: 'rd-swal' } });
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Could not save', customClass: { container: 'rd-swal' } });
        }
      }, 'json').fail(() => {
        $('#rdSaveBtn').prop('disabled', false);
        Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed', customClass: { container: 'rd-swal' } });
      });
      return false;
    }

    function rdDelete(rid) {
      Swal.fire({
        title: 'Delete reminder?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Delete',
        customClass: { container: 'rd-swal' }
      }).then(r => {
        if (!r.isConfirmed) return;
        $.post('mem_rem_clndr.php', {
          action: 'delete', client_id: CLIENT_ID, reminder_id: rid
        }, res => {
          if (res.status === 'ok') {
            rdLoadList();
            // If we were editing the row we just deleted, clear the form.
            if (String(document.getElementById('rdEditId').value) === String(rid)) {
              rdResetForm();
            }
            Swal.fire({ icon: 'success', title: res.message, timer: 1000, showConfirmButton: false, customClass: { container: 'rd-swal' } });
          } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Delete failed', customClass: { container: 'rd-swal' } });
          }
        }, 'json').fail(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed', customClass: { container: 'rd-swal' } }));
      });
    }

    function createCommentEditor(textarea) {
      if (!textarea || textarea.ckeditorInstance || textarea.dataset.editorLoading === '1') {
        return;
      }

      textarea.dataset.editorLoading = '1';

      ClassicEditor
        .create(textarea, {
          toolbar: [
            'undo', 'redo', '|',
            'heading', '|',
            'bold', 'italic',
            'bulletedList', 'numberedList', '|',
            'outdent', 'indent', '|',
            'blockQuote', '|'
          ],
          language: 'en',
          licenseKey: ''
        })
        .then(editor => {
          textarea.ckeditorInstance = editor;
          delete textarea.dataset.editorLoading;
        })
        .catch(error => {
          delete textarea.dataset.editorLoading;
          console.error(error);
        });
    }

    $(document).on('shown.bs.modal', '#addcomment, #addNewcomment, #addBankStatementComment', function () {
      createCommentEditor(this.querySelector('textarea.ckeditor'));
    });

    // Fallback for modal triggers that open before the shown event hook runs.
    $(document).on('click', '[data-toggle="modal"][data-target="#addcomment"], [data-toggle="modal"][data-target="#addNewcomment"], [data-toggle="modal"][data-target="#addBankStatementComment"], [data-bs-toggle="modal"][data-bs-target="#addcomment"], [data-bs-toggle="modal"][data-bs-target="#addNewcomment"], [data-bs-toggle="modal"][data-bs-target="#addBankStatementComment"]', function () {
      const targetSelector = $(this).attr('data-target') || $(this).attr('data-bs-target');
      if (!targetSelector) {
        return;
      }

      const modal = document.querySelector(targetSelector);
      if (modal) {
        createCommentEditor(modal.querySelector('textarea.ckeditor'));
      }
    });

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

    function addPhraseRegular(text) {
      const inst = document.querySelector('#comment_details_regular')?.ckeditorInstance;
      if (inst) {
        inst.model.change(writer => {
          const pos = inst.model.document.selection.getFirstPosition();
          writer.insertText(text + '. ', pos);
        });
        inst.editing.view.focus();
      }
    }

    function addPhraseBankStatement(text) {
      const inst = document.querySelector('#comment_details_bank_statement')?.ckeditorInstance;
      if (inst) {
        inst.model.change(writer => {
          const pos = inst.model.document.selection.getFirstPosition();
          writer.insertText(text + '. ', pos);
        });
        inst.editing.view.focus();
      }
    }
  </script>

  <!-- ── Section Navigator & Collapse Logic ──────────────────── -->
  <script>
  (function () {
    /* ── 1. Make every section-heading a collapse toggle ── */
    document.querySelectorAll('.section-heading').forEach(function (heading) {
      // Find the immediately following .section-body sibling
      let body = heading.nextElementSibling;
      while (body && !body.classList.contains('section-body')) {
        body = body.nextElementSibling;
      }
      if (!body) return;

      // Add toggle button
      const btn = document.createElement('button');
      btn.className = 'sh-toggle';
      btn.innerHTML = '<i class="fas fa-chevron-down"></i>';
      btn.title = 'Toggle section';
      heading.appendChild(btn);

      function collapse() {
        heading.classList.add('collapsed');
        body.classList.add('collapsed');
        btn.querySelector('i').className = 'fas fa-chevron-right';
      }
      function expand() {
        heading.classList.remove('collapsed');
        body.classList.remove('collapsed');
        btn.querySelector('i').className = 'fas fa-chevron-down';
      }

      heading.addEventListener('click', function (e) {
        if (heading.classList.contains('collapsed')) expand();
        else collapse();
      });

      // Store refs on heading for global controls
      heading._collapseBody = collapse;
      heading._expandBody   = expand;
    });

    /* ── 2. Collapse All / Expand All ── */
    document.getElementById('btn-collapse-all')?.addEventListener('click', function () {
      document.querySelectorAll('.section-heading').forEach(h => h._collapseBody && h._collapseBody());
    });
    document.getElementById('btn-expand-all')?.addEventListener('click', function () {
      document.querySelectorAll('.section-heading').forEach(h => h._expandBody && h._expandBody());
    });

    /* ── 3. Top tab strip click → scroll + expand ── */
    document.querySelectorAll('.ch-tab-strip .ch-tab').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        const targetId = link.dataset.target;
        const body = document.getElementById(targetId);
        if (!body) return;

        // Expand if collapsed
        const heading = body.previousElementSibling;
        if (heading && heading.classList.contains('section-heading') && heading.classList.contains('collapsed')) {
          heading._expandBody && heading._expandBody();
        }

        // Smooth scroll with a small delay so expansion completes
        setTimeout(function () {
          body.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 50);
      });
    });

    /* ── 4. Highlight active top tab via IntersectionObserver ── */
    const topTabs  = document.querySelectorAll('.ch-tab-strip .ch-tab');
    const sectionBodies = document.querySelectorAll('.section-body');

    if ('IntersectionObserver' in window && sectionBodies.length) {
      const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            const id = entry.target.id;
            topTabs.forEach(function (tt) {
              tt.classList.toggle('is-active', tt.dataset.target === id);
            });
          }
        });
      }, { threshold: 0.15, rootMargin: '-60px 0px -60px 0px' });

      sectionBodies.forEach(function (s) { observer.observe(s); });
    }

    /* ── 5. Back-to-top button ── */
    const btt = document.getElementById('backToTop');
    if (btt) {
      window.addEventListener('scroll', function () {
        btt.classList.toggle('visible', window.scrollY > 400);
      }, { passive: true });
      btt.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    }

    /* ── 6. Top tab strip: float + auto-hide on scroll ── */
    const chStrip = document.querySelector('.ch-tab-strip');
    if (chStrip) {
      const chPlaceholder = document.createElement('div');
      chPlaceholder.className = 'ch-tab-strip-placeholder';
      chStrip.parentNode.insertBefore(chPlaceholder, chStrip);

      let chIsFloating = false;
      let chHideTimer = null;
      let chLeft = 0, chWidth = 0, chPlaceholderTop = 0;

      // Keep the floating strip below the fixed app header instead of
      // overlapping its buttons/icons.
      function chHeaderBottom() {
        const header = document.querySelector('.dash-header');
        return header ? Math.round(header.getBoundingClientRect().bottom) + 12 : 20;
      }

      function chMeasure() {
        const rect = chStrip.getBoundingClientRect();
        if (!chIsFloating) {
          chLeft = rect.left;
          chWidth = rect.width;
        }
        chPlaceholderTop = chPlaceholder.getBoundingClientRect().top + window.scrollY;
      }
      chMeasure();
      window.addEventListener('resize', function () {
        chMeasure();
        if (chIsFloating) chStrip.style.top = chHeaderBottom() + 'px';
      });

      function chShow() {
        chStrip.classList.remove('ch-hidden');
        clearTimeout(chHideTimer);
        chHideTimer = setTimeout(function () {
          chStrip.classList.add('ch-hidden');
        }, 3000);
      }

      function chEnterFloating() {
        chIsFloating = true;
        chPlaceholder.style.height = chStrip.offsetHeight + 'px';
        chStrip.style.left  = chLeft + 'px';
        chStrip.style.width = chWidth + 'px';
        chStrip.style.top   = chHeaderBottom() + 'px';
        chStrip.classList.add('ch-floating');
        chShow();
      }

      function chExitFloating() {
        chIsFloating = false;
        clearTimeout(chHideTimer);
        chStrip.classList.remove('ch-floating', 'ch-hidden');
        chStrip.style.left  = '';
        chStrip.style.width = '';
        chStrip.style.top   = '';
        chPlaceholder.style.height = '0px';
      }

      window.addEventListener('scroll', function () {
        if (window.scrollY > chPlaceholderTop + 10) {
          if (!chIsFloating) chEnterFloating();
        } else if (chIsFloating) {
          chExitFloating();
        }
      }, { passive: true });

      document.addEventListener('mousemove', function () {
        if (chIsFloating) chShow();
      });
    }
  })();
  </script>

  <!-- ══════════════════════════════════════════════════════════
       EMAIL COMPOSE  (Email button in the hero bar)
       Backed by client_email.php. The panel lives in a hidden holder
       and is handed to SweetAlert as its content when opened, so the
       whole compose box *is* a SweetAlert — no separate modal layer.
       Because SweetAlert only shows one popup at a time, problems are
       reported on an inline banner inside the panel (which also keeps
       whatever has been typed) instead of a second popup.
       ══════════════════════════════════════════════════════════ -->
  <style>
    /* Parking spot for the views while the SweetAlert is closed. */
    #cemHolder {
      display: none;
    }

    /* ── SweetAlert chrome for the email popups ──────────────── */
    .cem-swal-popup {
      position: relative;
      width: 940px !important;
      max-width: calc(100vw - 24px) !important;
      padding: 0 0 14px !important;
      border-radius: 0 !important;
      overflow: hidden !important;
    }

    /* This build of SweetAlert wraps the title and content in
       .swal2-header / .swal2-content, and those wrappers carry the side
       padding — zero them so the header bar reaches both edges. */
    .cem-swal-popup .swal2-header,
    .cem-swal-popup .swal2-content {
      padding: 0 !important;
      margin: 0 !important;
      width: 100%;
      display: block;
    }

    .cem-swal-popup .swal2-title {
      background: linear-gradient(135deg, var(--brand, #272469), var(--brand-dark, #1f1d54));
      color: #fff !important;
      font-size: 1rem !important;
      font-weight: 600;
      text-align: left;
      margin: 0 !important;
      padding: 15px 52px 15px 18px !important;
      width: 100%;
    }

    .cem-swal-popup .swal2-title small {
      display: block;
      font-size: .76rem;
      font-weight: 400;
      opacity: .75;
      margin-top: 2px;
    }

    .cem-swal-popup .swal2-close {
      position: absolute;
      top: 6px;
      right: 10px;
      margin: 0;
      z-index: 2;
      color: #fff !important;
      box-shadow: none !important;
      font-size: 1.9rem;
    }

    .cem-swal-popup .swal2-close:hover {
      color: #fff !important;
      opacity: .75;
    }

    .cem-swal-popup .swal2-actions {
      margin: 14px 18px 0 !important;
      justify-content: flex-start;
      width: auto;
    }

    .cem-swal-popup .swal2-styled {
      border-radius: 0 !important;
      font-size: .85rem;
      font-weight: 600;
      padding: 8px 18px !important;
      box-shadow: none !important;
    }

    .cem-swal-popup .swal2-confirm {
      background: var(--brand, #272469) !important;
    }

    .cem-swal-popup .swal2-cancel {
      background: #eef0f5 !important;
      color: #4a5069 !important;
    }

    .cem-swal-popup .swal2-validation-message {
      margin: 12px 18px 0 !important;
      border-radius: 0;
      text-align: left;
      justify-content: flex-start;
      white-space: pre-wrap;
      word-break: break-word;
      font-size: .84rem;
      line-height: 1.45;
    }

    /* Keep alerts above the sticky header/sidebar (SweetAlert's own
       default of 1060 sits under some of this theme's chrome). */
    .swal2-container {
      z-index: 30000 !important;
    }

    /* The form itself fills the popup edge to edge. */
    .cem-swal-html {
      margin: 0 !important;
      padding: 0 !important;
      text-align: left !important;
      color: inherit !important;
      max-height: calc(100vh - 230px);
      overflow-y: auto;
    }

    /* CKEditor hangs its link/table balloons off <body>; lift them over
       the SweetAlert container so they aren't buried behind it. */
    .ck-body-wrapper,
    .ck.ck-balloon-panel {
      z-index: 30050 !important;
    }

    /* The "Powered by CKEditor" badge that pops up on focus. NOTE: this is
       the attribution CKEditor's free/GPL build asks for — suppressing it
       is meant to go with a purchased licence key on the editor config. */
    .ck.ck-balloon-panel.ck-powered-by-balloon,
    .ck .ck-powered-by {
      display: none !important;
    }

    /* ── Field rows (inline label, Gmail-ish) ────────────────── */
    .cem-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 18px;
      border-bottom: 1px solid #eef0f5;
    }

    .cem-row>label {
      flex: 0 0 62px;
      margin: 0;
      font-size: .78rem;
      font-weight: 600;
      color: #7a8194;
      text-transform: uppercase;
      letter-spacing: .3px;
    }

    .cem-input {
      flex: 1 1 auto;
      min-width: 0;
      border: 0;
      outline: none;
      background: transparent;
      font-size: .9rem;
      color: #2b2f42;
      padding: 3px 0;
    }

    .cem-input::placeholder {
      color: #b3b8c7;
    }

    .cem-select {
      flex: 1 1 auto;
      min-width: 0;
      border: 1px solid #e3e6ef;
      border-radius: 0;
      background: #fff;
      font-size: .85rem;
      color: #2b2f42;
      padding: 5px 8px;
      height: 34px;
    }

    .cem-select:focus {
      outline: none;
      border-color: var(--brand, #272469);
    }

    /* From + Template share one row */
    .cem-row-split {
      display: flex;
      gap: 18px;
      padding: 11px 18px;
      border-bottom: 1px solid #eef0f5;
      background: #fbfcfe;
      flex-wrap: wrap;
    }

    /* Two equal halves — without this the "Send from" select sizes to its
       longest option while the template select eats the rest of the row. */
    .cem-field {
      flex: 1 1 0;
      min-width: 240px;
    }

    .cem-field>.cem-select {
      width: 100%;
      display: block;
    }

    .cem-field-label {
      display: block;
      font-size: .72rem;
      font-weight: 700;
      color: #7a8194;
      text-transform: uppercase;
      letter-spacing: .4px;
      margin-bottom: 4px;
    }

    .cem-field-row {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .cem-toggles {
      display: flex;
      gap: 10px;
      flex: 0 0 auto;
    }

    .cem-link {
      background: none;
      border: 0;
      color: var(--brand, #272469);
      font-size: .78rem;
      font-weight: 600;
      cursor: pointer;
      padding: 0;
      white-space: nowrap;
    }

    .cem-link:hover {
      text-decoration: underline;
    }

    /* ── Editor ──────────────────────────────────────────────── */
    .cem-editor {
      padding: 4px 8px 0;
    }

    .cem-editor .ck.ck-editor__editable_inline {
      border: 0 !important;
      box-shadow: none !important;
      min-height: 170px;
      padding: 10px;
    }

    .cem-editor .ck.ck-toolbar {
      border: 0 !important;
      border-bottom: 1px solid #eef0f5 !important;
      border-radius: 0 !important;
      background: #fbfcfe !important;
    }

    /* ── Merge tags ──────────────────────────────────────────── */
    .cem-tags {
      display: flex;
      align-items: center;
      gap: 5px;
      flex-wrap: wrap;
      padding: 8px 18px 10px;
      border-top: 1px solid #eef0f5;
    }

    .cem-tags-label {
      font-size: .72rem;
      font-weight: 700;
      color: #7a8194;
      text-transform: uppercase;
      letter-spacing: .4px;
      margin-right: 3px;
    }

    .cem-tag {
      cursor: pointer;
      font-size: .76rem;
      font-weight: 500;
      background: #f0f3f9;
      border: 1px solid #e3e7f0;
      border-radius: 0;
      padding: 4px 10px;
      color: #4a5069;
      transition: all .12s ease;
    }

    .cem-tag:hover {
      background: var(--brand, #272469);
      border-color: var(--brand, #272469);
      color: #fff;
    }

    .cem-swal-popup .swal2-footer {
      margin: 12px 18px 0 !important;
      padding-top: 10px !important;
      justify-content: flex-start;
      font-size: .8rem;
    }

    /* ── Visibility note (template form) ─────────────────────── */
    .cem-note {
      font-size: .8rem;
      color: #6c757d;
      margin-top: 6px;
    }

    .cem-note-public {
      color: #157347;
      font-weight: 600;
    }

    .cem-stack {
      padding: 12px 18px;
      border-bottom: 1px solid #eef0f5;
    }

    .cem-stack .cem-field-label {
      margin-bottom: 5px;
    }

    .cem-stack .cem-select,
    .cem-stack .cem-text {
      width: 100%;
      border: 1px solid #e3e6ef;
      border-radius: 0;
      padding: 7px 10px;
      font-size: .88rem;
      color: #2b2f42;
    }

    .cem-stack .cem-text:focus {
      outline: none;
      border-color: var(--brand, #272469);
    }

    .cem-hidden {
      display: none !important;
    }

    @media (max-width: 640px) {
      .cem-row>label {
        flex-basis: 46px;
        font-size: .72rem;
      }

      .cem-swal-html {
        max-height: calc(100vh - 260px);
      }
    }
  </style>

  <div id="cemHolder">

      <!-- ─────────── COMPOSE ─────────── -->
      <div id="cemComposeView">

        <div class="cem-row-split">
          <div class="cem-field">
            <label class="cem-field-label">Send from</label>
            <select class="cem-select" id="cemSender">
              <?php foreach ($em_accounts as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= (int) $a['is_default'] === 1 ? 'selected' : '' ?>>
                  <?= htmlspecialchars($a['cfg']['from_email'] ?? $a['title']) ?>
                </option>
              <?php endforeach; ?>
              <?php if (!$em_accounts): ?>
                <option value="0">No mailbox configured</option>
              <?php endif; ?>
            </select>
            <?php if (!$em_accounts): ?>
              <div class="cem-note text-danger">
                No sending mailbox is set up yet<?= em_is_admin()
                  ? ' — add one on the <a href="client_email.php" target="_blank">Email Templates</a> page.'
                  : '. Ask an admin to add one.' ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="cem-field">
            <label class="cem-field-label">Email template</label>
            <div class="cem-field-row">
              <select class="cem-select" id="cemTemplate">
                <option value="">— None (write manually) —</option>
                <optgroup label="My Templates" id="cemTplGroupMine">
                  <?php foreach ($em_tpls['mine'] as $t): ?>
                    <option value="<?= (int) $t['id'] ?>">
                      <?= htmlspecialchars($t['title']) ?> (<?= $t['visibility'] ?>)
                    </option>
                  <?php endforeach; ?>
                </optgroup>
                <optgroup label="Public Templates" id="cemTplGroupPublic">
                  <?php foreach ($em_tpls['public'] as $t): ?>
                    <option value="<?= (int) $t['id'] ?>">
                      <?= htmlspecialchars($t['title']) ?> — <?= htmlspecialchars($t['owner_name'] ?? 'shared') ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              </select>
              <button type="button" class="cem-link" onclick="cemOpenTemplateForm(0)" title="Add new template">
                <i class="fas fa-plus"></i> New
              </button>
              <button type="button" class="cem-link cem-hidden" id="cemEditTplBtn" title="Edit this template"
                onclick="cemOpenTemplateForm(parseInt(document.getElementById('cemTemplate').value || 0))">
                <i class="fas fa-pen"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="cem-row">
          <label for="cemTo">To</label>
          <input type="text" class="cem-input" id="cemTo"
            value="<?= htmlspecialchars($client_email ?: $hero_bus_email) ?>" placeholder="client@example.com">
          <div class="cem-toggles">
            <button type="button" class="cem-link" onclick="cemToggle('cemCcRow')">Cc</button>
            <button type="button" class="cem-link" onclick="cemToggle('cemBccRow')">Bcc</button>
          </div>
        </div>

        <div class="cem-row cem-hidden" id="cemCcRow">
          <label for="cemCc">Cc</label>
          <input type="text" class="cem-input" id="cemCc" placeholder="someone@example.com, other@example.com">
        </div>

        <div class="cem-row cem-hidden" id="cemBccRow">
          <label for="cemBcc">Bcc</label>
          <input type="text" class="cem-input" id="cemBcc" placeholder="hidden@example.com">
        </div>

        <div class="cem-row">
          <label for="cemSubject">Subject</label>
          <input type="text" class="cem-input" id="cemSubject" maxlength="255" placeholder="Subject line">
        </div>

        <div class="cem-editor">
          <textarea id="cemBody"></textarea>
        </div>

        <div class="cem-tags">
          <span class="cem-tags-label">Insert</span>
          <?php foreach ($em_tags as $tag => $desc):
            // Show a readable label ("Client Name"); the raw {token} is
            // still what gets inserted, and sits in the tooltip.
            $tag_label = ucwords(str_replace('_', ' ', trim($tag, '{}')));
            $tag_label = preg_replace('/\bId\b/', 'ID', $tag_label);
            ?>
            <span class="cem-tag" title="<?= htmlspecialchars($tag . ' — ' . $desc) ?>"
              onclick="cemInsertTag('<?= htmlspecialchars($tag) ?>')"><?= htmlspecialchars($tag_label) ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ─────────── TEMPLATE ADD / EDIT ─────────── -->
      <div id="cemTemplateView">
        <input type="hidden" id="cemTplId" value="">

        <div class="cem-stack">
          <label class="cem-field-label" for="cemTplTitle">Template name</label>
          <input type="text" class="cem-text" id="cemTplTitle" maxlength="150" placeholder="e.g. Documents Request">
        </div>

        <div class="cem-stack">
          <label class="cem-field-label" for="cemTplSubject">Subject</label>
          <input type="text" class="cem-text" id="cemTplSubject" maxlength="255" placeholder="Email subject line">
        </div>

        <div class="cem-editor">
          <textarea id="cemTplBody"></textarea>
        </div>

        <div class="cem-stack" style="border-top:1px solid #eef0f5;border-bottom:0;">
          <label class="cem-field-label">Mark as</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="cemTplVis" id="cemTplPrivate" checked
              onchange="cemVisNote()">
            <label class="form-check-label" for="cemTplPrivate">Private</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="cemTplVis" id="cemTplPublic" onchange="cemVisNote()">
            <label class="form-check-label" for="cemTplPublic">Public</label>
          </div>
          <div class="cem-note" id="cemVisNote"></div>
        </div>
      </div>

  </div>

  <script>
    /* ── Email compose ────────────────────────────────────────── */
    const CEM_CLIENT_ID = <?= (int) $client_id ?>;
    const CEM_TITLE = <?= json_encode('Email ' . $client_fullname) ?>;
    const CEM_SUBTITLE = <?= json_encode($client_email ?: $hero_bus_email ?: 'No email on file') ?>;

    let cemTemplates = {};   // id -> {id,title,subject,body,visibility,can_edit}
    <?php foreach (array_merge($em_tpls['mine'], $em_tpls['public']) as $t): ?>
      cemTemplates[<?= (int) $t['id'] ?>] = <?= json_encode([
        'id' => (int) $t['id'],
        'title' => $t['title'],
        'subject' => $t['subject'],
        'body' => $t['body'],
        'visibility' => $t['visibility'],
        'can_edit' => (bool) $t['can_edit'],
      ]) ?>;
    <?php endforeach; ?>

    let cemBodyEditor = null, cemTplBodyEditor = null;

    function cemPost(data) {
      const fd = new FormData();
      Object.keys(data).forEach(k => fd.append(k, data[k]));
      // Read as text first: a PHP warning/fatal printed alongside the JSON
      // would otherwise blow up r.json() and fail silently. The raw output
      // goes to the console for developers — never to the user, since it
      // carries file paths and line numbers.
      return fetch('client_email.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(t => {
          try {
            return JSON.parse(t);
          } catch (e) {
            console.error('client_email.php returned a non-JSON response:', t);
            return {
              success: false,
              message: 'Something went wrong at our end and the email was not sent. '
                + 'Please try again — if it keeps happening, contact support.'
            };
          }
        });
    }

    /* ── Inline notices ───────────────────────────────────────────
       The compose box is itself a SweetAlert, and SweetAlert only ever
       shows one popup, so a second Swal.fire() here would blow the
       compose box away (draft and all). Report inside the panel. */
    function cemEsc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /* Build each editor only once its own popup is on screen. Creating one
       while its view still sits in the hidden holder gives CKEditor a zero
       width, and it collapses the whole toolbar into the "⋮" overflow. */
    function cemInitEditor(selector, current, assign) {
      if (current) return Promise.resolve(current);
      if (typeof ClassicEditor === 'undefined') return Promise.resolve(null);
      return ClassicEditor.create(document.querySelector(selector))
        .then(ed => { assign(ed); return ed; })
        .catch(() => null);
    }

    function cemInitComposeEditor() {
      return cemInitEditor('#cemBody', cemBodyEditor, ed => { cemBodyEditor = ed; });
    }

    function cemInitTemplateEditor() {
      return cemInitEditor('#cemTplBody', cemTplBodyEditor, ed => { cemTplBodyEditor = ed; });
    }

    /* Each view is handed to SweetAlert as its content on open and moved
       back into the hidden holder on close, so the CKEditor instances —
       and any draft already typed — survive closing and reopening. */
    let cemAfterClose = null;

    /* Hold the view nodes by reference, not by id. If any other code on the
       page fires its own Swal.fire() while this box is open, SweetAlert
       swaps the popup content without running willClose — the node is torn
       out of the document and getElementById would never find it again.
       Holding the reference means we can just re-attach it next time. */
    const CEM_VIEWS = {
      cemComposeView: document.getElementById('cemComposeView'),
      cemTemplateView: document.getElementById('cemTemplateView')
    };

    function cemSwalBase(viewId, opts) {
      const view = CEM_VIEWS[viewId];
      return Object.assign({
        html: view,
        width: 940,
        showCloseButton: true,
        showCancelButton: true,
        focusConfirm: false,
        reverseButtons: false,
        buttonsStyling: true,
        allowOutsideClick: () => !Swal.isLoading(),
        showLoaderOnConfirm: true,
        customClass: { popup: 'cem-swal-popup', htmlContainer: 'cem-swal-html' },
        didOpen: () => cemInitComposeEditor(),
        willClose: () => {
          const holder = document.getElementById('cemHolder');
          if (holder && view) holder.appendChild(view);
        },
        didClose: () => {
          const cb = cemAfterClose;
          cemAfterClose = null;
          if (cb) cb();
        }
      }, opts);
    }

    function openEmailCompose() {
      Swal.fire(cemSwalBase('cemComposeView', {
        title: cemEsc(CEM_TITLE) + '<small>' + cemEsc(CEM_SUBTITLE) + '</small>',
        confirmButtonText: '<i class="fas fa-paper-plane"></i> Send Email',
        cancelButtonText: 'Cancel',
        footer: '<a href="client_email.php" target="_blank" class="cem-link">Manage templates</a>',
        preConfirm: () => cemSend()
      })).then(res => {
        if (res.isConfirmed && res.value) {
          Swal.fire({ icon: 'success', title: 'Sent!', text: res.value });
        }
      });
    }

    /* `after` runs once the popup is fully gone, so the next SweetAlert
       never races the closing animation. */
    function closeEmailCompose(after) {
      cemAfterClose = typeof after === 'function' ? after : null;
      Swal.close();
    }

    function cemToggle(id) {
      document.getElementById(id).classList.toggle('cem-hidden');
    }

    function cemInsertTag(tag) {
      if (!cemBodyEditor) return;
      cemBodyEditor.setData(cemBodyEditor.getData() + ' ' + tag);
      cemBodyEditor.editing.view.focus();
    }

    /* Picking a template fills subject + body, and reveals Edit when allowed. */
    document.getElementById('cemTemplate').addEventListener('change', function () {
      const id = parseInt(this.value || 0);
      const editBtn = document.getElementById('cemEditTplBtn');
      if (!id || !cemTemplates[id]) {
        editBtn.classList.add('cem-hidden');
        return;
      }
      const t = cemTemplates[id];
      document.getElementById('cemSubject').value = t.subject || '';
      if (cemBodyEditor) cemBodyEditor.setData(t.body || '');
      editBtn.classList.toggle('cem-hidden', !t.can_edit);
    });

    /* ── Inline template add / edit ───────────────────────────── */
    function cemVisNote() {
      const pub = document.getElementById('cemTplPublic').checked;
      document.getElementById('cemVisNote').innerHTML = pub
        ? '<span class="cem-note-public">Public — if you select public it will show to everyone in the CRM.</span>'
        : '';
    }

    /* Template add/edit is its own SweetAlert; the compose box closes
       first so only one popup is ever on screen, then reopens after. */
    function cemOpenTemplateForm(id) {
      const t = id ? cemTemplates[id] : null;
      if (t && !t.can_edit) {
        Swal.showValidationMessage('That template belongs to another user — you can use it, but not edit it.');
        return;
      }
      closeEmailCompose(() => cemTemplateSwal(t));
    }

    function cemTemplateSwal(t) {
      document.getElementById('cemTplId').value = t ? t.id : '';
      document.getElementById('cemTplTitle').value = t ? t.title : '';
      // Starting a new template? Carry over whatever is already typed.
      document.getElementById('cemTplSubject').value =
        t ? t.subject : document.getElementById('cemSubject').value;
      const pub = t ? (t.visibility === 'public') : false;
      document.getElementById('cemTplPublic').checked = pub;
      document.getElementById('cemTplPrivate').checked = !pub;
      cemVisNote();
      const startBody = t ? (t.body || '') : cemGetBody();

      Swal.fire(cemSwalBase('cemTemplateView', {
        title: (t ? 'Edit Email Template' : 'Add New Email Template')
          + '<small>' + (t
            ? (t.visibility === 'public' ? 'Public template' : 'Private template')
            : 'Saved to your template library') + '</small>',
        confirmButtonText: 'Save Template',
        cancelButtonText: 'Back',
        // Editor is built here, with the popup open, so its toolbar lays out
        // at full width; only then is the body filled in.
        didOpen: () => cemInitTemplateEditor().then(ed => { if (ed) ed.setData(startBody); }),
        preConfirm: () => cemSaveTemplate()
      })).then(() => openEmailCompose());   // either way, back to the compose box
    }

    /* Returns a promise resolving to the saved template, or false after
       putting the reason on SweetAlert's own validation bar. */
    function cemSaveTemplate() {
      const title = document.getElementById('cemTplTitle').value.trim();
      const subject = document.getElementById('cemTplSubject').value.trim();
      const body = cemTplBodyEditor ? cemTplBodyEditor.getData() : '';
      const visibility = document.getElementById('cemTplPublic').checked ? 'public' : 'private';

      if (!title) {
        Swal.showValidationMessage('Give the template a name.');
        return false;
      }

      return cemPost({
        em_action: 'tpl_save',
        id: document.getElementById('cemTplId').value || 0,
        title: title, subject: subject, body: body, visibility: visibility
      }).then(d => {
        if (!d.success) {
          Swal.showValidationMessage(d.message || 'Save failed.');
          return false;
        }

        const id = d.id;
        cemTemplates[id] = {
          id: id, title: title, subject: subject, body: body,
          visibility: visibility, can_edit: true
        };

        // Keep the dropdown in sync without a page reload.
        const sel = document.getElementById('cemTemplate');
        let opt = sel.querySelector('option[value="' + id + '"]');
        if (!opt) {
          opt = document.createElement('option');
          opt.value = id;
          document.getElementById('cemTplGroupMine').appendChild(opt);
        }
        opt.textContent = title + ' (' + visibility + ')';
        sel.value = id;

        // Load it into the compose box so it can be sent straight away.
        document.getElementById('cemSubject').value = subject;
        if (cemBodyEditor) cemBodyEditor.setData(body);
        document.getElementById('cemEditTplBtn').classList.remove('cem-hidden');
        return true;
      }).catch(err => {
        console.error('client_email.php request failed:', err);
        Swal.showValidationMessage('Could not reach the server. Check your connection and try again.');
        return false;
      });
    }

    /* ── Send ─────────────────────────────────────────────────── */
    /* Prefer the CKEditor API, but fall back to the editable div (and then the
       raw textarea) so a slow/failed editor binding can't silently block a send. */
    function cemGetBody() {
      if (cemBodyEditor) return cemBodyEditor.getData();
      const live = document.querySelector('#cemComposeView .ck-editor__editable');
      if (live) return live.innerHTML;
      return document.getElementById('cemBody').value || '';
    }

    /* Runs as the compose SweetAlert's preConfirm: resolve with the
       confirmation text to close and celebrate, or return false after
       putting the problem on SweetAlert's own validation bar. */
    function cemSend() {
      const to = document.getElementById('cemTo').value.trim();
      const subject = document.getElementById('cemSubject').value.trim();
      const body = cemGetBody();

      if (!to) {
        Swal.showValidationMessage('Enter at least one "To" address.');
        return false;
      }
      if (!subject) {
        Swal.showValidationMessage('Enter a subject line.');
        return false;
      }
      // Strip tags *and* the &nbsp; that an "empty" paragraph leaves behind.
      const bodyText = body.replace(/<[^>]*>/g, '')
        .replace(/&nbsp;|&#160;| /gi, ' ').trim();
      if (!bodyText) {
        Swal.showValidationMessage('Write a message before sending.');
        return false;
      }

      return cemPost({
        em_action: 'send',
        client_id: CEM_CLIENT_ID,
        sender_account_id: document.getElementById('cemSender').value || 0,
        to: to,
        cc: document.getElementById('cemCc').value.trim(),
        bcc: document.getElementById('cemBcc').value.trim(),
        subject: subject,
        body: body
      }).then(d => {
        if (!d.success) {
          Swal.showValidationMessage(d.message || 'Sending failed.');
          return false;
        }
        return d.message || 'Email sent.';
      }).catch(err => {
        console.error('client_email.php request failed:', err);
        Swal.showValidationMessage('Could not reach the server. Check your connection and try again.');
        return false;
      });
    }
  </script>

</body>

</html>
