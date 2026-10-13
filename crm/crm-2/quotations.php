<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
require_once 'dompdf/autoload.inc.php';
require_once 'includes/activity_log.php';

use Dompdf\Dompdf;

include 'includes/db.php';
include "includes/auth.php";

$userid = $_SESSION["user_id"];
function getInitials($name)
{

  $words = explode(' ', $name);

  $initials = '';
  foreach ($words as $word) {
    $initials .= strtoupper(substr($word, 0, 1));
  }

  return $initials;
}

function imageToDataUri($relativePath)
{
  $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
  if (!is_file($fullPath)) {
    return '';
  }
  $mime = function_exists('mime_content_type') ? mime_content_type($fullPath) : 'image/png';
  $data = @file_get_contents($fullPath);
  if ($data === false) {
    return '';
  }
  return 'data:' . $mime . ';base64,' . base64_encode($data);
}

$user_full_name = $_SESSION['fullname'];

$user_initials = getInitials($user_full_name);

$msg = '';
$drows = '';

if (!empty($_POST['ajax_change_status'])) {
  $id = intval($_POST['quotation_id']);
  $stat = $_POST['new_status'];

  // Get quotation SR# for logging
  $sr_query = $con->prepare("SELECT quotation_sr FROM quotations WHERE quotation_id = ?");
  $sr_query->bind_param('i', $id);
  $sr_query->execute();
  $sr_result = $sr_query->get_result()->fetch_assoc();
  $quotation_sr = $sr_result['quotation_sr'] ?? 'Unknown';
  $sr_query->close();

  $st = $con->prepare("UPDATE quotations SET status = ? WHERE quotation_id = ?");
  $st->bind_param('si', $stat, $id);
  $st->execute();

  // Log the status change activity
  logActivity($con, $userid, 'UPDATE', 'quotation', $id, "Status changed to '$stat' for Quotation SR#$quotation_sr");

  exit;
}

if (
  isset($_POST['quotation_id']) &&
  !isset($_POST['add_note']) &&
  !isset($_POST['edit_note']) &&
  !isset($_POST['delete_note']) &&
  !isset($_POST['delete_quotation']) &&
  !isset($_POST['update_quotation']) &&
  !isset($_POST['createinvoice']) &&
  !isset($_POST['convert_to_invoice'])
) {
  $quotation_id = $_POST['quotation_id'];

  $query = "SELECT n.*, u.user_name FROM quotation_notes n
              LEFT JOIN users u ON n.created_by = u.user_id 
              WHERE n.quotation_id = ? 
              ORDER BY n.created_at DESC";

  $stmt = mysqli_prepare($con, $query);
  mysqli_stmt_bind_param($stmt, "i", $quotation_id);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);

  if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
      $created_at = date('M d, Y h:i A', strtotime($row['created_at']));
      $note_content = htmlspecialchars($row['note_content']);
      $created_by = htmlspecialchars($row['user_name']);

      echo '<div class="note-item" id="note-' . $row['note_id'] . '">';
      echo '<p class="note-text" id="txt-' . $row['note_id'] . '" contenteditable="false">' . nl2br($note_content) . '</p>';
      echo '<small class="text-muted">By ' . $created_by . ' on ' . $created_at . '</small>';
      echo '<div class="note-actions">';
      echo '<button type="button" class="btn btn-sm btn-warning" onclick="enableEdit(' . $row['note_id'] . ')">Edit</button>';
      echo '<button type="button" class="btn btn-sm btn-danger" onclick="delNote(' . $row['note_id'] . ')">Delete</button>';
      echo '</div>';
      echo '</div>';
    }
  } else {
    echo '<p class="text-center text-muted">No notes found for this quotation.</p>';
  }

  mysqli_stmt_close($stmt);
  exit;
}

if (isset($_POST['add_note'])) {
  $quotation_id = $_POST['quotation_id'];
  $note_content = $_POST['note_content'];

  // Get quotation SR# for logging
  $sr_query = $con->prepare("SELECT quotation_sr FROM quotations WHERE quotation_id = ?");
  $sr_query->bind_param('i', $quotation_id);
  $sr_query->execute();
  $sr_result = $sr_query->get_result()->fetch_assoc();
  $quotation_sr = $sr_result['quotation_sr'] ?? 'Unknown';
  $sr_query->close();

  $query = "INSERT INTO quotation_notes (quotation_id, note_content, created_by) VALUES (?, ?, ?)";
  $stmt = mysqli_prepare($con, $query);

  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "isi", $quotation_id, $note_content, $userid);
    mysqli_stmt_execute($stmt);
    $msg = "<div class='alert alert-success'>Note added successfully.</div>";

    // Log the note addition activity
    logActivity($con, $userid, 'CREATE', 'quotation_note', null, "Note added to Quotation SR#$quotation_sr");
  } else {
    $msg = "<div class='alert alert-danger'>Error preparing statement.</div>";
  }
}

if (isset($_POST['edit_note'])) {
  $note_id = $_POST['note_id'];
  $note_content = $_POST['note_content'];

  // Get quotation SR# for logging
  $sr_query = $con->prepare("SELECT q.quotation_sr FROM quotations q
                              JOIN quotation_notes n ON q.quotation_id = n.quotation_id 
                              WHERE n.note_id = ?");
  $sr_query->bind_param('i', $note_id);
  $sr_query->execute();
  $sr_result = $sr_query->get_result()->fetch_assoc();
  $quotation_sr = $sr_result['quotation_sr'] ?? 'Unknown';
  $sr_query->close();

  $query = "UPDATE quotation_notes SET note_content = ? WHERE note_id = ?";
  $stmt = mysqli_prepare($con, $query);
  mysqli_stmt_bind_param($stmt, "si", $note_content, $note_id);
  mysqli_stmt_execute($stmt);

  $msg = "<div class='alert alert-success'>Note updated successfully.</div>";

  // Log the note edit activity
  logActivity($con, $userid, 'UPDATE', 'quotation_note', $note_id, "Note updated in Quotation SR#$quotation_sr");
}

if (isset($_POST['delete_note'])) {
  $note_id = $_POST['note_id'];

  // Get quotation SR# for logging
  $sr_query = $con->prepare("SELECT q.quotation_sr FROM quotations q
                              JOIN quotation_notes n ON q.quotation_id = n.quotation_id 
                              WHERE n.note_id = ?");
  $sr_query->bind_param('i', $note_id);
  $sr_query->execute();
  $sr_result = $sr_query->get_result()->fetch_assoc();
  $quotation_sr = $sr_result['quotation_sr'] ?? 'Unknown';
  $sr_query->close();

  $query = "DELETE FROM quotation_notes WHERE note_id = ?";
  $stmt = mysqli_prepare($con, $query);
  mysqli_stmt_bind_param($stmt, "i", $note_id);
  mysqli_stmt_execute($stmt);

  $msg = "<div class='alert alert-success'>Note deleted successfully.</div>";

  // Log the note deletion activity
  logActivity($con, $userid, 'DELETE', 'quotation_note', $note_id, "Note deleted from Quotation SR#$quotation_sr");
}

if (isset($_POST["createinvoice"])) {
  $msg = '';
  $variable = '';
  $drow = '';
  $member_id = !empty($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
  $time = time();
  $m = date('m');
  $curDate = date('m/d/Y');
  $curDateMySQL = date('m/d/Y');
  $currentMonth = date("Y-m");
  $ranvalue = rand(1000, 100000);

  $date = date_create();
  $ranvalue = date_timestamp_get($date);

  $PDFFileN = "QUO-" . $currentMonth . "-" . $ranvalue . ".pdf";
  $Filename = "quotations/" . $PDFFileN;

  $services = $_POST['service'];
  $price = $_POST['unit_price'];
  $description = $_POST['description'];

  if (is_array($description)) {
    $descriptionOutput = '';
    foreach ($description as $desc) {
      $descriptionOutput .= htmlspecialchars($desc) . '<br>';
    }
  } else {
    $descriptionOutput = htmlspecialchars($description);
  }

  $price_single = array_sum($price);
  $size = count($services);

  $name = $_POST['name'];
  $email = $_POST['email'];

  $city = $_POST['city'];
  $state = $_POST['state'];
  $zip = $_POST['zip'];
  $phone = $_POST['phonecell'];

  $subtotal = $_POST['sub_total'];
  $discount = $_POST['discount'];
  $grandfinal = $_POST['grand_total'];
  $address = $city . ', ' . $state . ' ' . $zip;
  $discountRow = '';

  if (strpos($subtotal, '.') === false) {
    $subtotal = number_format($subtotal, 2, '.', '');
  }

  if (strpos($grandfinal, '.') === false) {
    $grandfinal = number_format($grandfinal, 2, '.', '');
  }

  if ($discount > 0) {
    if (strpos($discount, '.') === false) {

      $discount = number_format($discount, 2, '.', '');
    }

    $discountRow = ' <tr class="total">
                    <td></td>
                    <td style="font-size:20px;font-weight:bold;">Discount: $' . $discount . '</td>
                </tr>';
  } else {
    $discountRow = '';
  }

  for ($i = 0; $i < $size; $i++) {
    $servicess = $services[$i];
    $priceee = $price[$i];
    if (strpos($priceee, '.') === false) {
      $priceee = number_format($priceee, 2, '.', '');
    }
    /* take the description that belongs to THIS service */
    $desc = htmlspecialchars($description[$i] ?? '');

    $drows .= '
    <tr class="item">
        <td>' . $servicess . '<br><small>' . $desc . '</small></td>
        <td>$' . $priceee . '</td>
    </tr>';
  }

  $logoDataUri = imageToDataUri('storage/uploads/logo/logo-dark.png');

  $content = '<style>
.invoice-box {
  position:relative;
  height:99%;
  width:100%;
  margin: auto;
  border: 1px solid #eee;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
  font-size: 16px;
  line-height: 24px;
  color: #555;
  }

  .invoice-box h4 {
    position: absolute;
    bottom: -15px;
    left: 50%;
    transform: translateX(-50%);
  }
         .invoice-box table {
            line-height: inherit;
            text-align: left;
            border-collapse: collapse;
         }

         .invoice-box table td {
            padding: 5px;
            vertical-align: top;
         }

         .invoice-box table tr td:nth-child(2) {
            text-align: right;
         }

         .invoice-box table tr.top table td {
            padding-bottom: 20px;
         }

         .invoice-box table tr.top table td.title {
            font-size: 45px;
            line-height: 45px;
            color: #333;
         }

         .invoice-box table tr.information table td {
            padding-bottom: 40px;
         }

         .invoice-box table tr.heading td {
            background: #252161;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            color: white;
            font-size:22px;
         }

         .invoice-box table tr.details td {
            padding-bottom: 20px;
         }

         .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
         }

         .invoice-box table tr.item.last td {
            border-bottom: none;
         }

         .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
         }
      </style>
      <div class="invoice-box">
         <table style="width:100%">
         <tr class="total">
                    <td style="font-size:30px;font-weight:bold;color:#252161;text-align:right;padding-top:20px;font-family:sans-serif">Quotation</td>
                </tr> 
            <tr class="top">
               <td colspan="2">
                  <table style="width:100%">
                     <tr>
                        <td class="title">
                           <img src="' . $logoDataUri . '" alt="Company logo" style="width: 60%; max-width: 300px" />
                        </td>
                        <td> 
                           SR: #' . $ranvalue . '<br/>
                            By: ' . $user_initials . ' (' . $userid . ')<br/>
                           Date: ' . $curDateMySQL . '
                        </td>
                     </tr>
                  </table>
               </td>
            </tr>

            <tr class="information">
               <td colspan="2">
                  <table style="width:100%">
                     <tr>
                         <td>
                          <b>From:</b> <br /> 
                           Syed Professional Services<br />
                           1012 Coney Island Ave<br/>Brooklyn, NY 11230<br />
                           (718) 859-5392<br />
                           (718) 859-2237<br />
                           Email:invoice@syedpro.com<br />
                        </td>

                      <td>
                            <b>To:</b> <br />
                            ' . $name . '.<br />
                            ' . $city . '<br />
                            ' . $state . ' ' . $zip . '<br />
                            ' . $phone . '<br />
                            ' . $email . '<br />
                        </td>
                     </tr>
                  </table>
               </td>
            </tr>

            <tr class="heading">
               <td>Service</td>
               <td>Price</td>
            </tr>' . $drows . '

            <tr class="total">

                    <td></td>
                    <td style="font-weight:bold;">Sub Total: $' . $subtotal . '</td>
                </tr>' . $discountRow . '
             <tr class="total">
                    <td></td>
                    <td style="font-weight:bold;">Grand Total: $' . $grandfinal . '</td>
                </tr>        
            </table>
            <h4><u>Thank you for choosing Syed Professional Services<u/></h4>
            </div>';

  $queryy = "INSERT INTO `quotations`(`quotation_sr`,`name`, `phone`, `email`, `address`, `services`, `sub_total`, `discount`, `grand_total`, `quotation_date`, `status`, `created_by`,`file_name`,`member_id`) VALUES ('$ranvalue','$name','$phone','$email','$address','$drows','$subtotal','$discount','$grandfinal','$curDate','sent','$userid','$PDFFileN','$member_id')";
  $insert_query = mysqli_query($con, $queryy);
  if ($insert_query) {
    $msg .= "<div class='alert alert-success'>Quotation Has Been Sent Successfully.</div>";

    // Log the quotation creation activity with SR#
    logActivity($con, $userid, 'CREATE', 'quotation', $con->insert_id, "Quotation SR#$ranvalue created for $name - Total: $$grandfinal");
  } else {
    $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>";
  }

  $dompdf = new Dompdf(array('enable_remote' => false));

  $dompdf->loadHtml("$content");
  $dompdf->render();

  $output = $dompdf->output();
  file_put_contents($Filename, $output);
  header("Location: quotations.php?success=1");
  exit;
}

if (isset($_POST["update_quotation"])) {
  $msg = '';
  $drows = '';
  $qid = (int) $_POST['quotation_id'];
  $member_id = !empty($_POST['member_id']) ? (int) $_POST['member_id'] : 0;

  /* fetch existing record so we can preserve SR#, file_name, and original date */
  $exq = $con->prepare("SELECT quotation_sr, file_name, quotation_date FROM quotations WHERE quotation_id = ?");
  $exq->bind_param('i', $qid);
  $exq->execute();
  $exrow = $exq->get_result()->fetch_assoc();
  $exq->close();

  if (!$exrow) {
    header("Location: quotations.php?err=notfound");
    exit;
  }

  $ranvalue = $exrow['quotation_sr'];
  $PDFFileN = $exrow['file_name'];
  $curDate = $exrow['quotation_date'];
  $curDateMySQL = $curDate;
  $Filename = "quotations/" . $PDFFileN;

  $services = $_POST['service'];
  $price = $_POST['unit_price'];
  $description = $_POST['description'];
  $size = count($services);

  $name = $_POST['name'];
  $email = $_POST['email'];
  $city = $_POST['city'];
  $state = $_POST['state'];
  $zip = $_POST['zip'];
  $phone = $_POST['phonecell'];
  $subtotal = $_POST['sub_total'];
  $discount = $_POST['discount'];
  $grandfinal = $_POST['grand_total'];
  $address = $city . ', ' . $state . ' ' . $zip;
  $discountRow = '';

  if (strpos($subtotal, '.') === false) {
    $subtotal = number_format($subtotal, 2, '.', '');
  }
  if (strpos($grandfinal, '.') === false) {
    $grandfinal = number_format($grandfinal, 2, '.', '');
  }
  if ($discount > 0) {
    if (strpos($discount, '.') === false) {
      $discount = number_format($discount, 2, '.', '');
    }
    $discountRow = ' <tr class="total">
                    <td></td>
                    <td style="font-size:20px;font-weight:bold;">Discount: $' . $discount . '</td>
                </tr>';
  }

  for ($i = 0; $i < $size; $i++) {
    $servicess = $services[$i];
    $priceee = $price[$i];
    if (strpos($priceee, '.') === false) {
      $priceee = number_format($priceee, 2, '.', '');
    }
    $desc = htmlspecialchars($description[$i] ?? '');
    $drows .= '
    <tr class="item">
        <td>' . $servicess . '<br><small>' . $desc . '</small></td>
        <td>$' . $priceee . '</td>
    </tr>';
  }

  $logoDataUri = imageToDataUri('storage/uploads/logo/logo-dark.png');

  $content = '<style>
.invoice-box {
  position:relative;
  height:99%;
  width:100%;
  margin: auto;
  border: 1px solid #eee;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
  font-size: 16px;
  line-height: 24px;
  color: #555;
  }
  .invoice-box h4 {
    position: absolute;
    bottom: -15px;
    left: 50%;
    transform: translateX(-50%);
  }
         .invoice-box table { line-height: inherit; text-align: left; border-collapse: collapse; }
         .invoice-box table td { padding: 5px; vertical-align: top; }
         .invoice-box table tr td:nth-child(2) { text-align: right; }
         .invoice-box table tr.top table td { padding-bottom: 20px; }
         .invoice-box table tr.top table td.title { font-size: 45px; line-height: 45px; color: #333; }
         .invoice-box table tr.information table td { padding-bottom: 40px; }
         .invoice-box table tr.heading td { background: #252161; border-bottom: 1px solid #ddd; font-weight: bold; color: white; font-size:22px; }
         .invoice-box table tr.details td { padding-bottom: 20px; }
         .invoice-box table tr.item td { border-bottom: 1px solid #eee; }
         .invoice-box table tr.item.last td { border-bottom: none; }
         .invoice-box table tr.total td:nth-child(2) { border-top: 2px solid #eee; font-weight: bold; }
      </style>
      <div class="invoice-box">
         <table style="width:100%">
         <tr class="total">
                    <td style="font-size:30px;font-weight:bold;color:#252161;text-align:right;padding-top:20px;font-family:sans-serif">Quotation</td>
                </tr>
            <tr class="top">
               <td colspan="2">
                  <table style="width:100%">
                     <tr>
                        <td class="title">
                           <img src="' . $logoDataUri . '" alt="Company logo" style="width: 60%; max-width: 300px" />
                        </td>
                        <td>
                           SR: #' . $ranvalue . '<br/>
                            By: ' . $user_initials . ' (' . $userid . ')<br/>
                           Date: ' . $curDateMySQL . '
                        </td>
                     </tr>
                  </table>
               </td>
            </tr>
            <tr class="information">
               <td colspan="2">
                  <table style="width:100%">
                     <tr>
                         <td>
                          <b>From:</b> <br />
                           Syed Professional Services<br />
                           1012 Coney Island Ave<br/>Brooklyn, NY 11230<br />
                           (718) 859-5392<br />
                           (718) 859-2237<br />
                           Email:invoice@syedpro.com<br />
                        </td>
                      <td>
                            <b>To:</b> <br />
                            ' . $name . '.<br />
                            ' . $city . '<br />
                            ' . $state . ' ' . $zip . '<br />
                            ' . $phone . '<br />
                            ' . $email . '<br />
                        </td>
                     </tr>
                  </table>
               </td>
            </tr>
            <tr class="heading">
               <td>Service</td>
               <td>Price</td>
            </tr>' . $drows . '
            <tr class="total">
                    <td></td>
                    <td style="font-weight:bold;">Sub Total: $' . $subtotal . '</td>
                </tr>' . $discountRow . '
             <tr class="total">
                    <td></td>
                    <td style="font-weight:bold;">Grand Total: $' . $grandfinal . '</td>
                </tr>
            </table>
            <h4><u>Thank you for choosing Syed Professional Services<u/></h4>
            </div>';

  $upd = $con->prepare("UPDATE quotations
                          SET name = ?, phone = ?, email = ?, address = ?, services = ?,
                              sub_total = ?, discount = ?, grand_total = ?, member_id = ?
                          WHERE quotation_id = ?");
  $upd->bind_param('ssssssssii', $name, $phone, $email, $address, $drows, $subtotal, $discount, $grandfinal, $member_id, $qid);
  $upd->execute();
  $upd->close();

  $dompdf = new Dompdf(array('enable_remote' => false));
  $dompdf->loadHtml("$content");
  $dompdf->render();
  $output = $dompdf->output();
  file_put_contents($Filename, $output);

  logActivity($con, $userid, 'UPDATE', 'quotation', $qid, "Quotation SR#$ranvalue updated for $name - Total: $$grandfinal");

  header("Location: quotations.php?updated=1");
  exit;
}

if (isset($_POST['convert_to_invoice']) && !empty($_POST['convert_quotation_id'])) {

  $qid = (int) $_POST['convert_quotation_id'];

  /* ---- 1.  read quotation header ---- */
  $qh = $con->prepare("SELECT * FROM quotations WHERE quotation_id = ?");
  $qh->bind_param('i', $qid);
  $qh->execute();
  $q = $qh->get_result()->fetch_assoc();
  $qh->close();
  if (!$q) {
    $msg .= "<div class='alert alert-danger'>Quotation not found.</div>";
    goto afterConvert;
  }

  $mem_id = (int) ($q['member_id'] ?: 0);
  $client = $con->query("SELECT * FROM members WHERE member_id = $mem_id")->fetch_assoc();

  if (!$client) {

    if (!empty($_POST['convert_to_invoice'])) {
      echo json_encode([
        'success' => false,
        'msg' => 'This member does not exist – invoice cannot be created.'
      ]);
      exit;
    }

    $msg .= "<div class='alert alert-danger'>Linked member not found.</div>";
    goto afterConvert;
  }

  $client_id = $mem_id;
  $service_provider_id = $_SESSION['user_id'];
  $is_ach = 0;
  $user_initials = getInitials($_SESSION['fullname']);
  $curDateMySQL = date('m/d/Y');
  $invoice_date2 = date('Y-m-d H:i:s');
  $invoice_date = date('m/d/Y h:i:s');
  $duedate = date('Y-m-d', strtotime('+7 days'));
  $billing_month = '';

  $sub_total = (float) $q['sub_total'];
  $discount = (float) $q['discount'];
  $grand_total = (float) $q['grand_total'];

  $PDFFileN = "INV-{$client_id}-" . date('Y-m') . '-' . time() . ".pdf";
  $Filename = "invoices/" . $PDFFileN;

  $cols = [
    'mem_id',
    'billing_id',
    'temp_id',
    'invoice_date',
    'filelink',
    'due_date',
    'total_amount',
    'paid_amount',
    'discount',
    'balance',
    'invoice_status',
    'payment_type',
    'payment_details',
    'paid_date',
    'billing_cycle',
    'service_provider_id',
    'is_ach',
    'employee_initials'
  ];

  $placeHolders = implode(',', array_fill(0, count($cols), '?')); // 18 × "?"

  $sql = "INSERT INTO billing_invoice (" . implode(',', $cols) . ") VALUES ($placeHolders)";

  $stmt = $con->prepare($sql);
  $vars = [
    $client_id,
    0,
    0,
    $invoice_date,
    $PDFFileN,
    $duedate,
    $sub_total,
    0 . 0,
    $discount,
    $grand_total,
    1,
    0,
    '',
    $duedate,
    $billing_month,
    $service_provider_id,
    $is_ach,
    $user_initials
  ];

  $types = 'iissssddddiiisssis';
  $stmt->bind_param($types, ...$vars);
  $stmt->execute();
  $invoice_no = $con->insert_id;
  $stmt->close();

  // Log the quotation to invoice conversion with SR#
  $quotation_sr = $q['quotation_sr'] ?? 'Unknown';
  logActivity($con, $userid, 'CREATE', 'invoice', $invoice_no, "Invoice created from Quotation SR#$quotation_sr - Invoice #$invoice_no");

  $dom = new DOMDocument();
  @$dom->loadHTML($q['services']);
  $rows = $dom->getElementsByTagName('tr');
  foreach ($rows as $tr) {
    $cells = $tr->getElementsByTagName('td');
    if ($cells->length < 2)
      continue;

    $service = trim($cells->item(0)->nodeValue);
    $price = (float) filter_var($cells->item(1)->nodeValue, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    $con->query(
      "INSERT INTO billing_invoice_items
          (inv_id,temp_id,billing_id,mem_id,item_description,item_sub_description,
           unit_price,quantity,total_price,date_added,user_id,item_date)
          VALUES ($invoice_no,0,0,$client_id,'" . mysqli_real_escape_string($con, $service) . "','',
                  $price,1,$price,'$invoice_date2','{$_SESSION['user_id']}','$invoice_date2')"
    );
  }

  if ($client['bus_name']) {
    $name = $client['bus_name'];
    $address = $client['address'];
    $address1 = $client['hcity'] . ', ' . $client['hstate'] . ', ' . $client['hzip'];
    $email = $client['prm_email'];
  } elseif ($client['dba']) {
    $name = $client['dba'];
    $address = $client['address'];
    $address1 = $client['hcity'] . ', ' . $client['hstate'] . ', ' . $client['hzip'];
    $email = $client['prm_email'];
  } else {
    $name = $client['name'] . ' ' . $client['lname'];
    $email = $client['sec_email'];
    $address = $client['hstreet'];
    $address1 = $client['hcity'] . ', ' . $client['hstate'] . ', ' . $client['hzip'];
  }
  $phone = $client['cell_ph'];
  $spName = '';
  if ($service_provider_id) {
    $sp = $con->query("SELECT user_name FROM users WHERE user_id = $service_provider_id")->fetch_assoc();
    $spName = getInitials($sp['user_name']);
  }

  /* 6c.  discount row */
  $discountRow = '';
  if ($discount > 0) {
    $discountRow = '<tr class="total"><td></td>
          <td style="font-size:20px;font-weight:bold;">Discount: $' . number_format($discount, 2) . '</td></tr>';
  }

  $drows = '';
  $dom = new DOMDocument();
  @$dom->loadHTML($q['services']);
  $rows = $dom->getElementsByTagName('tr');

  foreach ($rows as $tr) {
    $cells = $tr->getElementsByTagName('td');
    if ($cells->length < 2)
      continue;
    $html = $dom->saveHTML($cells->item(0));

    // service text before the first <br>
    preg_match('/^(.+?)<br/i', $html, $m);
    $service = trim(strip_tags($m[1] ?? ''));

    // description inside <small>…</small>
    preg_match('/<small>(.+?)<\/small>/i', $html, $m);
    $description = trim(strip_tags($m[1] ?? ''));
    $price = (float) filter_var(
      $cells->item(1)->nodeValue,
      FILTER_SANITIZE_NUMBER_FLOAT,
      FILTER_FLAG_ALLOW_FRACTION
    );

    $drows .= '
    <tr class="item">
        <td><b>' . htmlspecialchars($service) . '</b>' .
      ($description ? '<br><small>' . htmlspecialchars($description) . '</small>' : '') .
      '</td>
        <td>$' . number_format($price, 2) . '</td>
    </tr>';
  }

  /* 6e.  PDF content (identical to your template) */
  $logoDataUri = imageToDataUri('storage/uploads/logo/logo-dark.png');
  $unpaidDataUri = imageToDataUri('storage/uploads/logo/unpaid.jpg');
  // added
  $content = '<style>
.invoice-box {
  position:relative;
  height:99%;
  width:100%;
  margin: auto;
  border: 1px solid #eee;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
  font-size: 16px;
  line-height: 24px;
  color: #555;
  }

.invoice-box .footer-note {
  position: absolute;
  bottom: -20px;
  left: 50%;
  transform: translateX(-50%);
  text-align: center;
  font-size: 14px;
}

.invoice-box .footer-note p {
  margin: 0;
  font-size: 14px;
  white-space: nowrap; 
}

.invoice-box .footer-note h4 {
  margin-top: 5px; 
  font-size: 16px;
}
         .invoice-box table {
            line-height: inherit;
            text-align: left;
            border-collapse: collapse;
         }

         .invoice-box table td {
            padding: 5px;
            vertical-align: top;
         }

         .invoice-box table tr td:nth-child(2) {
            text-align: right;
         }

         .invoice-box table tr.top table td {
            padding-bottom: 20px;
         }

         .invoice-box table tr.top table td.title {
            font-size: 45px;
            line-height: 45px;
            color: #333;
         }

         .invoice-box table tr.information table td {
            padding-bottom: 40px;
         }

         .invoice-box table tr.heading td {
            background: #8b742a;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            color: white;
            font-size:22px;
         }

         .invoice-box table tr.details td {
            padding-bottom: 20px;
         }

         .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
         }

         .invoice-box table tr.item.last td {
            border-bottom: none;
         }

         .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
         }
      </style>
    <div class="invoice-box">
      <table style="width:100%">
        <tr class="top">
          <td colspan="2">
            <table style="width:100%">
              <tr>
                <td class="title">
                  <img src="' . $logoDataUri . '" />
                </td>
                <td>
                  Invoice #: ' . $invoice_no . '<br/>
                  Q By: ' . $user_initials . ' (' . $_SESSION['user_id'] . ')<br/>
                  Created: ' . $curDateMySQL . '<br/>
                  Due: ' . date('m/d/Y', strtotime($duedate)) . '<br/>
                  <img src="' . $unpaidDataUri . '" style="width:15%;margin-top:12px"/>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr class="information"><td colspan="2">
          <table style="width:100%">
            <tr>
              <td><b>From:</b><br>
                Syed Professional Services<br>
                1012 Coney Island Ave<br>Brooklyn, NY 11230<br>
                Phone: (718) 859-5392<br>
                Fax: (718) 859-2237<br>
                invoice@syedpro.com
              </td>
              <td><b>To:</b><br>
                ' . $name . '<br>' . $address . '<br>' . $address1 . '<br>' . $phone . '<br>' . $email . '
              </td>
            </tr>
          </table>
        </td></tr>
        <tr class="heading">
          <td>Service</td><td>Price</td>
        </tr>
        ' . $drows . '
        <tr class="total"><td></td><td style="font-weight:bold;">Sub Total: $' . number_format($sub_total, 2) . '</td></tr>
        ' . $discountRow . '
        <tr class="total"><td></td><td style="font-weight:bold;">Grand Total: $' . number_format($grand_total, 2) . '</td></tr>
      </table>
      <div class="footer-note">
        <p>In the event of a returned or unsuccessful payment, a bank fee of \$25 will be applied.<br>
        We accept payment via Zelle and direct debit card.</p>
        <h4><u>Thank you for choosing Syed Professional Services</u></h4>
      </div>
    </div>';

  /* 6f.  generate PDF */
  $dompdf = new Dompdf(['enable_remote' => false]);
  $dompdf->loadHtml($content);
  $dompdf->render();
  file_put_contents($Filename, $dompdf->output());

  /* ---- 7.  mark quotation "invoiced" ---- */
  $con->query("UPDATE quotations SET status='invoiced' WHERE quotation_id=$qid");
  // ✅ Save invoice filename to quotations table
  $invFilename = "invoices/$PDFFileN";
  $upd = $con->prepare("UPDATE quotations SET invoice_file = ? WHERE quotation_id = ?");
  $upd->bind_param('si', $invFilename, $qid);
  $upd->execute();
  echo json_encode(['success' => true, 'pdf_url' => "invoices/$PDFFileN"]);
  exit;
}
afterConvert:

// Add logging for quotation deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quotation_id']) && !isset($_POST['add_note']) && !isset($_POST['edit_note']) && !isset($_POST['delete_note']) && !isset($_POST["createinvoice"]) && !isset($_POST['update_quotation'])) {
  $quotation_id = $_POST['quotation_id'];

  // Get quotation SR# for logging before deletion
  $sr_query = $con->prepare("SELECT quotation_sr FROM quotations WHERE quotation_id = ?");
  $sr_query->bind_param('i', $quotation_id);
  $sr_query->execute();
  $sr_result = $sr_query->get_result()->fetch_assoc();
  $quotation_sr = $sr_result['quotation_sr'] ?? 'Unknown';
  $sr_query->close();

  $delete_query = "DELETE FROM quotations WHERE quotation_id = ?";
  if ($delete_stmt = $con->prepare($delete_query)) {
    $delete_stmt->bind_param('i', $quotation_id);
    $delete_stmt->execute();

    // Log the quotation deletion activity
    logActivity($con, $userid, 'DELETE', 'quotation', $quotation_id, "Quotation SR#$quotation_sr deleted");

    header("Location: {$_SERVER['PHP_SELF']}");
    exit();
  }
}

$countQuery = "SELECT COUNT(*) AS total_rows FROM quotations";
$countResult = $con->query($countQuery);
$total_pages = $countResult ? (int) ($countResult->fetch_assoc()['total_rows'] ?? 0) : 0;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;

$num_results_on_page = 25;
$que = "
    SELECT 
        q.*,
        m.status AS member_status
    FROM quotations q
    LEFT JOIN members m ON m.member_id = q.member_id
    ORDER BY q.quotation_id DESC
    LIMIT ?,?
";

if ($listStmt = $con->prepare($que)) {
  $calc_page = ($page - 1) * $num_results_on_page;
  $listStmt->bind_param('ii', $calc_page, $num_results_on_page);
  $listStmt->execute();

  $result = $listStmt->get_result();

?>
  <!DOCTYPE html>
  <html lang="en" dir="">
  <meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

  <head>
    <title>Syed Professional Services - Dashboard</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <?php include "includes/links.php"; ?>

    <style type="text/css">
      .text-sm {
        font-size: 13px !important;
        margin-bottom: 5px;
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

      .badge-warning {
        color: #fff;
        background-color: #c0a145;
      }

      .form-group {
        margin-bottom: 0.3rem;
      }

      .label {
        display: inline;
        padding: 0.2em 0.6em 0.3em;
        font-size: 75%;
        font-weight: 700;
        line-height: 1;
        color: #fff;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 0.25em;
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
      }

      .label-warning {
        background-color: #f0ad4e;
      }

      .label-danger {
        background-color: #d9534f;
      }

      .label-success {
        background-color: #5cb85c;
      }

      .bor {
        border: 2px solid red;
      }

      .notes-container {
        max-height: 150px;
        overflow-y: auto;
      }

      .note-item {
        padding: 8px;
        border-bottom: 1px solid #eee;
      }

      .note-item:last-child {
        border-bottom: none;
      }

      .note-actions {
        margin-top: 5px;
      }

      .note-actions button {
        margin-right: 5px;
        padding: 2px 6px;
        font-size: 12px;
      }

      .service-item {
        transition: transform 0.25s ease, box-shadow 0.25s ease, background-color 0.25s ease;
        cursor: pointer;
      }

      .service-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        background-color: #6FD943;
      }

      .service-item input[type="checkbox"] {
        cursor: pointer;
      }

      /* ============================================================
         === Quotations page polish (visual only) ===
         ============================================================ */

      /* ---- Page header bar ---- */
      .quote-pagebar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 18px;
      }

      .quote-pagebar .breadcrumb {
        margin: 0;
        padding: 0;
        background: transparent;
      }

      .quote-pagebar h4 {
        margin: 0 0 4px 0;
        font-weight: 600;
        letter-spacing: 0.2px;
      }

      .quote-pagebar .btn-add {
        padding: 8px 18px;
        font-weight: 500;
        border-radius: 6px;
        box-shadow: 0 2px 6px rgba(37, 33, 97, 0.18);
      }

      .quote-pagebar .btn-add i {
        margin-right: 6px;
      }

      /* ---- KPI / stat cards ---- */
      .kpi-row {
        margin-top: 0;
      }

      .kpi-card {
        border: 0;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 14px rgba(40, 40, 90, 0.08);
        position: relative;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }

      .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(40, 40, 90, 0.14);
      }

      .kpi-card .card-body {
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        text-align: left;
      }

      .kpi-card .kpi-icon {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.18);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: #fff;
      }

      .kpi-card .kpi-meta {
        flex: 1;
        min-width: 0;
      }

      .kpi-card .kpi-value {
        font-size: 1.55rem;
        font-weight: 600;
        line-height: 1.1;
        color: #fff;
        margin: 0;
        word-break: break-word;
      }

      .kpi-card .kpi-label {
        font-size: 0.82rem;
        font-weight: 500;
        opacity: 0.9;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 0.4px;
      }

      /* ---- Section card (chart + list) ---- */
      .quote-section-card {
        border: 0;
        border-radius: 10px;
        box-shadow: 0 4px 14px rgba(40, 40, 90, 0.06);
      }

      .quote-section-card .card-header {
        background: #fff;
        border-bottom: 1px solid #eef0f5;
        padding: 14px 20px;
        font-weight: 600;
        font-size: 0.95rem;
        color: #2c2f48;
      }

      /* ---- List header (inside list card) ---- */
      .list-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 14px;
      }

      .list-toolbar h5 {
        margin: 0;
        font-weight: 600;
        color: #2c2f48;
      }

      /* ---- Table polish ---- */
      .quote-table thead th {
        background: #f6f7fb;
        color: #495065;
        font-weight: 600;
        font-size: 12.5px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        border-bottom: 2px solid #e6e8f0 !important;
        padding: 12px 10px;
      }

      .quote-table thead th a.dataTable-sorter {
        color: inherit;
      }

      .quote-table tbody td {
        vertical-align: middle;
        padding: 11px 10px;
        font-size: 13.5px;
        border-top: 1px solid #f0f1f6;
      }

      .quote-table tbody tr:hover {
        background: #fafbff;
      }

      .quote-table .id-pill {
        display: inline-block;
        padding: 4px 10px;
        background: #5EB839;
        color: #fff;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
      }

      .quote-table .file-link {
        display: inline-block;
        font-weight: 500;
        font-size: 12.5px;
      }

      .quote-table .amount-col {
        font-weight: 600;
        color: #2c2f48;
        white-space: nowrap;
      }

      /* ---- Status select tinting (color hint) ---- */
      .status-select {
        min-width: 110px;
        font-size: 12px !important;
        font-weight: 500;
        border-radius: 6px;
      }

      .status-select.status-sent {
        background: #fff7e6;
        border-color: #f0ad4e;
        color: #8a5a00;
      }

      .status-select.status-accepted {
        background: #e8f7ee;
        border-color: #5cb85c;
        color: #1f6b34;
      }

      .status-select.status-rejected {
        background: #fdecec;
        border-color: #d9534f;
        color: #8a2724;
      }

      /* ---- Action button cluster ---- */
      .quote-actions {
        display: inline-flex;
        gap: 4px;
        align-items: center;
        flex-wrap: nowrap;
      }

      .quote-actions .btn {
        padding: 5px 9px;
        line-height: 1;
        border-radius: 6px;
      }

      .quote-actions .btn i {
        font-size: 14px;
      }

      /* ---- Modal headers ---- */
      .modal-header.modal-header-pro {
        background: linear-gradient(135deg, #2c2f73 0%, #4046a8 100%);
        color: #fff;
        border-bottom: 0;
        padding: 14px 20px;
      }

      .modal-header.modal-header-pro .modal-title {
        color: #fff;
        font-weight: 600;
      }

      .modal-header.modal-header-pro .close,
      .modal-header.modal-header-pro .close span {
        color: #fff;
        opacity: 0.85;
      }

      .modal-header.modal-header-pro .close:hover {
        opacity: 1;
      }

      /* ---- Sticky DataTable header inside the responsive wrapper ---- */
      .dataTable-container {
        position: relative;
      }

      /* Tighten the chart card so it doesn't dominate the page */
      #statusChart {
        max-height: 240px;
      }
    </style>
    <script src="ckeditor/ckeditor.js"></script>
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
    <?php
    $_quote_stats = $con->query(
      "SELECT
         COUNT(*) AS total_n,
         SUM(status = 'accepted') AS accepted_n,
         SUM(status = 'sent') AS sent_n,
         SUM(status = 'rejected') AS rejected_n,
         COALESCE(SUM(CASE WHEN status = 'accepted' THEN CAST(grand_total AS DECIMAL(10,2)) ELSE 0 END), 0) AS accepted_revenue
       FROM quotations"
    )->fetch_assoc();
    $_qs_total    = (int)   ($_quote_stats['total_n']    ?? 0);
    $_qs_accepted = (int)   ($_quote_stats['accepted_n'] ?? 0);
    $_qs_sent     = (int)   ($_quote_stats['sent_n']     ?? 0);
    $_qs_rejected = (int)   ($_quote_stats['rejected_n'] ?? 0);
    $_qs_revenue  = (float) ($_quote_stats['accepted_revenue'] ?? 0);

    /* ---------- 2.  STATUS BREAKDOWN ---------- */
    $statusStmt = $con->query("SELECT status, COUNT(*) c FROM quotations GROUP BY status");
    $statusLabels = [];
    $statusCounts = [];
    while ($row = $statusStmt->fetch_assoc()) {
      $statusLabels[] = ucfirst($row['status']);
      $statusCounts[] = $row['c'];
    }

    /* ---------- 3.  MONTHLY TREND (LAST 12 MONTHS) ---------- */
    $trend = $con->query("
  SELECT DATE_FORMAT(quotation_date,'%Y-%m') ym,
         COUNT(*)        cnt,
         SUM(CAST(grand_total AS DECIMAL(10,2))) revenue
  FROM quotations
  WHERE quotation_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
  GROUP BY ym
  ORDER BY ym
");
    $trendMonths = [];
    $trendCount = [];
    $trendRevenue = [];
    while ($r = $trend->fetch_assoc()) {
      $trendMonths[] = date('M Y', strtotime($r['ym'] . '-01'));
      $trendCount[] = $r['cnt'];
      $trendRevenue[] = $r['revenue'];
    }
    ?><!-- ===== REPORTS SECTION ===== -->

    <!-- ===== /REPORTS ===== -->
    <!-- [ Main Content ] start -->
    <div class="dash-container">
      <?php if (isset($msg)) {
        echo $msg;
      } ?>
      <div class="dash-content">
        <div class="page-header">
          <div class="page-block">
            <div class="row align-items-center">

              <div class="col-auto">
                <div class="page-header-title">
                  <h4 class="m-b-10">Estimate/Quotes</h4>
                </div>
                <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                  <li class="breadcrumb-item">Estimate/Quotes</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="row">

          <div class="col-xl-12">
            <div class="row">
              <?php if (($_SESSION['user_type'] ?? '') == '1'): ?>
              <div class="row mt-5">
                <!-- Accepted Estimates Revenue -->
                <div class="col-lg col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="theme-avtar bg-success"><i class="ti ti-currency-dollar"></i></div>
                      <p class="text-muted text-sm mt-3 mb-1">Revenue</p>
                      <h6 class="mb-2">Accepted Revenue</h6>
                      <h3 class="mb-0">$<?= number_format($_qs_revenue, 2) ?></h3>
                    </div>
                  </div>
                </div>
                <!-- Accepted Estimates -->
                <div class="col-lg col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="theme-avtar bg-primary"><i class="ti ti-circle-check"></i></div>
                      <p class="text-muted text-sm mt-3 mb-1">Count</p>
                      <h6 class="mb-2">Accepted Estimates</h6>
                      <h3 class="mb-0"><?= number_format($_qs_accepted) ?></h3>
                    </div>
                  </div>
                </div>
                <!-- Sent Estimates -->
                <div class="col-lg col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="theme-avtar bg-info"><i class="ti ti-send"></i></div>
                      <p class="text-muted text-sm mt-3 mb-1">Count</p>
                      <h6 class="mb-2">Sent Estimates</h6>
                      <h3 class="mb-0"><?= number_format($_qs_sent) ?></h3>
                    </div>
                  </div>
                </div>
                <!-- Rejected Estimates -->
                <div class="col-lg col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="theme-avtar bg-danger"><i class="ti ti-x"></i></div>
                      <p class="text-muted text-sm mt-3 mb-1">Count</p>
                      <h6 class="mb-2">Rejected Estimates</h6>
                      <h3 class="mb-0"><?= number_format($_qs_rejected) ?></h3>
                    </div>
                  </div>
                </div>
                <!-- Total Estimates -->
                <div class="col-lg col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="theme-avtar bg-warning"><i class="ti ti-file-invoice"></i></div>
                      <p class="text-muted text-sm mt-3 mb-1">Count</p>
                      <h6 class="mb-2">Total Estimates</h6>
                      <h3 class="mb-0"><?= number_format($_qs_total) ?></h3>
                    </div>
                  </div>
                </div>
              </div>
              <?php else: ?>
              <div class="row mt-5">
                <!-- Accepted Estimates -->
                <div class="col-lg-3 col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="theme-avtar bg-primary"><i class="ti ti-circle-check"></i></div>
                      <p class="text-muted text-sm mt-3 mb-1">Count</p>
                      <h6 class="mb-2">Accepted Estimates</h6>
                      <h3 class="mb-0"><?= number_format($_qs_accepted) ?></h3>
                    </div>
                  </div>
                </div>
                <!-- Sent Estimates -->
                <div class="col-lg-3 col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="theme-avtar bg-info"><i class="ti ti-send"></i></div>
                      <p class="text-muted text-sm mt-3 mb-1">Count</p>
                      <h6 class="mb-2">Sent Estimates</h6>
                      <h3 class="mb-0"><?= number_format($_qs_sent) ?></h3>
                    </div>
                  </div>
                </div>
                <!-- Rejected Estimates -->
                <div class="col-lg-3 col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="theme-avtar bg-danger"><i class="ti ti-x"></i></div>
                      <p class="text-muted text-sm mt-3 mb-1">Count</p>
                      <h6 class="mb-2">Rejected Estimates</h6>
                      <h3 class="mb-0"><?= number_format($_qs_rejected) ?></h3>
                    </div>
                  </div>
                </div>
                <!-- Total Estimates -->
                <div class="col-lg-3 col-md-6 mb-3">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="theme-avtar bg-warning"><i class="ti ti-file-invoice"></i></div>
                      <p class="text-muted text-sm mt-3 mb-1">Count</p>
                      <h6 class="mb-2">Total Estimates</h6>
                      <h3 class="mb-0"><?= number_format($_qs_total) ?></h3>
                    </div>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <div class="row mt-2">
                <!-- Status doughnut -->
                <div class="col-md-12">
                  <div class="card quote-section-card">
                    <div class="card-header">Estimate Status Breakdown</div>
                    <div class="card-body">
                      <canvas id="statusChart" height="200"></canvas>
                    </div>
                  </div>
                </div>

              </div>
            </div>
            <div class="row mt-3">
              <div class="col-sm-12 col-md-12">

                <div class="card quote-section-card">
                  <div class="card-body employee-detail-body fulls-card">
                    <div class="list-toolbar">
                      <h5>Estimate List</h5>
                      <button class="btn btn-primary btn-add" data-toggle="modal" data-target="#associatedProducts">
                        <i class="ti ti-plus"></i> New Estimate
                      </button>
                    </div>
                    <hr>
                    <div class="row">
                      <div class="dataTable-container table-responsive">
                        <table class="table datatable quote-table">
                          <thead>
                            <tr>

                              <th data-sortable=""><a href="#" class="dataTable-sorter">Sr#</a></th>
                              <th data-sortable=""><a href="#" class="dataTable-sorter">Date</a></th>
                              <th data-sortable=""><a href="#" class="dataTable-sorter">Name</a></th>
                              <th data-sortable=""><a href="#" class="dataTable-sorter">Email</a></th>
                              <th data-sortable=""><a href="#" class="dataTable-sorter">Phone</a></th>
                              <th data-sortable=""><a href="#" class="dataTable-sorter">Amount</a></th>
                              <th data-sortable=""><a href="#" class="dataTable-sorter">File</a></th>
                              <th data-sortable=""><a href="#" class="dataTable-sorter">Status</a></th>
                              <th data-sortable=""><a href="#" class="dataTable-sorter">Notes</a></th>
                              <th data-sortable=""><a href="#" class="dataTable-sorter">ACT</a></th>
                              <th>Invoice</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php while ($rrow = $result->fetch_assoc()):
                              /* parse services HTML back into structured rows for the Edit modal */
                              $existingServices = [];
                              if (!empty($rrow['services'])) {
                                preg_match_all(
                                  '#<tr class="item">\s*<td>(.*?)<br>\s*<small>(.*?)</small>\s*</td>\s*<td>\$([\d,\.]+)</td>\s*</tr>#s',
                                  $rrow['services'],
                                  $svcMatches,
                                  PREG_SET_ORDER
                                );
                                foreach ($svcMatches as $sm) {
                                  $existingServices[] = [
                                    'name' => trim($sm[1]),
                                    'desc' => html_entity_decode(trim($sm[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                                    'price' => str_replace(',', '', trim($sm[3])),
                                  ];
                                }
                              }
                              /* split stored "City, State Zip" address back into parts */
                              $editCity = '';
                              $editState = '';
                              $editZip = '';
                              $addrRaw = trim((string) $rrow['address']);
                              if ($addrRaw !== '') {
                                $addrParts = explode(',', $addrRaw, 2);
                                $editCity = trim($addrParts[0] ?? '');
                                $rest = trim($addrParts[1] ?? '');
                                if ($rest !== '') {
                                  $lastSpace = strrpos($rest, ' ');
                                  if ($lastSpace !== false) {
                                    $editState = trim(substr($rest, 0, $lastSpace));
                                    $editZip = trim(substr($rest, $lastSpace + 1));
                                  } else {
                                    $editState = $rest;
                                  }
                                }
                              }
                              $editPayload = [
                                'id' => (int) $rrow['quotation_id'],
                                'sr' => $rrow['quotation_sr'],
                                'name' => $rrow['name'],
                                'phone' => $rrow['phone'],
                                'email' => $rrow['email'],
                                'city' => $editCity,
                                'state' => $editState,
                                'zip' => $editZip,
                                'sub_total' => $rrow['sub_total'],
                                'discount' => $rrow['discount'],
                                'grand_total' => $rrow['grand_total'],
                                'member_id' => (int) $rrow['member_id'],
                                'services' => $existingServices,
                              ];
                              $editJson = htmlspecialchars(json_encode($editPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                            ?>
                              <tr>

                                <td>
                                  <?php echo $rrow["quotation_sr"]; ?>
                                </td>
                                <td>
                                  <?php $OSdate = $rrow["quotation_date"]; ?>
                                  <?php echo date("m/d/y", strtotime($OSdate)); ?>
                                </td>
                                <td>
                                  <?php if ((int) $rrow['member_id'] > 0): ?>
                                    <a href="client_details.php?id=<?= $rrow['member_id'] ?>&status=<?= $rrow['member_status'] ?>"
                                      target="_blank">
                                      <?= htmlspecialchars($rrow["name"]) ?>
                                    </a>
                                  <?php else: ?>
                                    <?= htmlspecialchars($rrow["name"]) ?>
                                  <?php endif; ?>
                                </td>
                                <td><?php echo $rrow["email"]; ?>
                                </td>
                                <td><?php echo $rrow["phone"]; ?>
                                </td>
                                <td class="amount-col">$<?php echo number_format((float) ($rrow["grand_total"] ?? 0), 2); ?></td>
                                <td>
                                  <!-- QUOTATION : hover-preview -->
                                  <a href="quotations/<?= $rrow['file_name'] ?>" class="view-file-preview file-link"
                                    data-file="<?= htmlspecialchars($rrow['file_name']) ?>" target="_blank">
                                    <i class="ti ti-file-text"></i> View File
                                  </a>

                                  <?php if (!empty($rrow['invoice_file'])): ?>
                                    <br>
                                    <!-- INVOICE : hover-preview -->
                                    <a href="<?= $rrow['invoice_file'] ?>" class="view-file-preview file-link"
                                      data-file="<?= basename($rrow['invoice_file']) ?>" target="_blank">
                                      <i class="ti ti-file-invoice"></i> View Invoice
                                    </a>
                                  <?php endif; ?>
                                </td>
                                <td>
                                  <select class="status-select form-control form-control-sm status-<?= htmlspecialchars($rrow['status']) ?>"
                                    data-id="<?php echo $rrow['quotation_id']; ?>">
                                    <option value="sent" <?php echo $rrow['status'] === 'sent' ? 'selected' : ''; ?>>Sent
                                    </option>
                                    <option value="accepted" <?php echo $rrow['status'] === 'accepted' ? 'selected' : ''; ?>>
                                      Accepted</option>
                                    <option value="rejected" <?php echo $rrow['status'] === 'rejected' ? 'selected' : ''; ?>>
                                      Rejected</option>
                                  </select>
                                </td>
                                <td>
                                  <div class="quote-actions">
                                    <button type="button" class="btn btn-sm btn-info" data-toggle="modal"
                                      data-target="#viewNotesModal" onclick="loadNotes(<?php echo $rrow['quotation_id']; ?>)"
                                      title="View notes">
                                      <i class="ti ti-eye text-white"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success" data-toggle="modal"
                                      data-target="#addNoteModal"
                                      onclick="setQuotationId(<?php echo $rrow['quotation_id']; ?>)"
                                      title="Add note">
                                      <i class="ti ti-plus text-white"></i>
                                    </button>
                                  </div>
                                </td>
                                <td>
                                  <div class="quote-actions">
                                    <button type="button" class="btn btn-sm btn-primary edit-quotation-btn"
                                      title="Edit Quotation"
                                      data-quotation="<?= $editJson ?>">
                                      <i class="ti ti-pencil text-white"></i>
                                    </button>

                                    <button type="button" class="btn btn-sm btn-danger tooltip1" data-toggle="modal"
                                      data-target="#deleteConfirmationModal"
                                      onclick="setTopId(<?php echo $rrow['quotation_id']; ?>)"
                                      title="Delete">
                                      <i class="ti ti-trash text-white"></i>
                                    </button>

                                    <form method="post" action="send_quotation_reminder.php" style="display:inline">
                                      <input type="hidden" name="quotation_id" value="<?= $rrow['quotation_id'] ?>">
                                      <button type="submit" class="btn btn-sm btn-warning"
                                        title="Send reminder email to client">
                                        <i class="ti ti-mail"></i>
                                      </button>
                                    </form>
                                  </div>
                                </td>

                                <td>
                                  <form method="post" style="display:inline" id="convertForm<?= $rrow['quotation_id'] ?>">
                                    <input type="hidden" name="convert_quotation_id" value="<?= $rrow['quotation_id'] ?>">
                                    <button type="button" class="btn btn-sm btn-success convert-btn"
                                      data-id="<?= $rrow['quotation_id'] ?>" onclick="confirmConvert(this)"
                                      title="Convert to invoice">
                                      <i class="ti ti-file"></i> Invoice
                                    </button>
                                  </form>
                                </td>
                              </tr>

                            <?php endwhile; ?>
                            <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" role="dialog"
                              aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
                              <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                  <div class="modal-header modal-header-pro">
                                    <h5 class="modal-title" id="deleteConfirmationModalLabel">Delete Confirmation</h5>
                                  </div>
                                  <div class="modal-body">
                                    Are you sure you want to delete quotation <strong id="deleteTarget"></strong>?
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <!-- View Notes Modal -->
                            <div class="modal fade" id="viewNotesModal" tabindex="-1" role="dialog"
                              aria-labelledby="viewNotesModalLabel" aria-hidden="true">
                              <div class="modal-dialog modal-lg" role="document">
                                <div class="modal-content">
                                  <div class="modal-header modal-header-pro">
                                    <h5 class="modal-title" id="viewNotesModalLabel">Quotation Notes</h5>
                                  </div>
                                  <div class="modal-body">
                                    <div class="notes-container" id="notesContainer">
                                      <!-- Notes will be loaded here via AJAX -->
                                    </div>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                  </div>
                                </div>
                              </div>
                            </div>

                            <!-- Add Note Modal -->
                            <div class="modal fade" id="addNoteModal" tabindex="-1" role="dialog"
                              aria-labelledby="addNoteModalLabel" aria-hidden="true">
                              <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                  <div class="modal-header modal-header-pro">
                                    <h5 class="modal-title" id="addNoteModalLabel">Add New Note</h5>
                                  </div>
                                  <form method="post" action="">
                                    <div class="modal-body">
                                      <input type="hidden" name="quotation_id" id="noteQuotationId">
                                      <div class="form-group">
                                        <label for="note_content">Note Content</label>
                                        <textarea class="form-control" id="note_content" name="note_content" rows="5"
                                          required></textarea>
                                      </div>
                                    </div>
                                    <div class="modal-footer">
                                      <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                      <button type="submit" name="add_note" class="btn btn-primary">Add Note</button>
                                    </div>
                                  </form>
                                </div>
                              </div>
                            </div>

                            <!-- Edit Note Modal -->
                            <div class="modal fade" id="editNoteModal" tabindex="-1" role="dialog"
                              aria-labelledby="editNoteModalLabel" aria-hidden="true">
                              <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                  <div class="modal-header modal-header-pro">
                                    <h5 class="modal-title" id="editNoteModalLabel">Edit Note</h5>
                                  </div>
                                  <form method="post" action="">
                                    <div class="modal-body">
                                      <input type="hidden" name="note_id" id="editNoteId">
                                      <div class="form-group">
                                        <label for="edit_note_content">Note Content</label>
                                        <textarea class="form-control" id="edit_note_content" name="note_content" rows="5"
                                          required></textarea>
                                      </div>
                                    </div>
                                    <div class="modal-footer">
                                      <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                      <button type="submit" name="edit_note" class="btn btn-primary">Update Note</button>
                                    </div>
                                  </form>
                                </div>
                              </div>
                            </div>

                            <!-- Delete Note Confirmation Modal -->
                            <div class="modal fade" id="deleteNoteModal" tabindex="-1" role="dialog"
                              aria-labelledby="deleteNoteModalLabel" aria-hidden="true">
                              <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                  <div class="modal-header modal-header-pro">
                                    <h5 class="modal-title" id="deleteNoteModalLabel">Delete Note Confirmation</h5>
                                  </div>
                                  <form method="post" action="">
                                    <div class="modal-body">
                                      <p>Are you sure you want to delete this note?</p>
                                      <input type="hidden" name="note_id" id="deleteNoteId">
                                    </div>
                                    <div class="modal-footer">
                                      <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                      <button type="submit" name="delete_note" class="btn btn-danger">Delete Note</button>
                                    </div>
                                  </form>
                                </div>
                              </div>
                            </div>

                            <script>
                              let topId = 0; // holds clicked row id
                              function setTopId(id) {
                                topId = id;
                                document.getElementById('deleteTarget').textContent = '#' + id;
                              } // called by trash-button onclick

                              const confirmDeleteBtn = document.getElementById("confirmDelete");
                              if (confirmDeleteBtn) {
                                confirmDeleteBtn.addEventListener("click", function() {
                                  const f = document.createElement('form');
                                  f.method = 'post';
                                  f.action = '';
                                  f.innerHTML = `
    <input type="hidden" name="quotation_id" value="${topId}">
    <input type="hidden" name="delete_quotation" value="1">`;
                                  document.body.appendChild(f);
                                  f.submit();
                                });
                              }

                              function setQuotationId(quotationId) {
                                document.getElementById('noteQuotationId').value = quotationId;
                              }

                              function loadNotes(quotationId) {
                                currentQuotationId = quotationId;
                                $.ajax({
                                  url: window.location.href,
                                  type: 'POST',
                                  data: {
                                    quotation_id: quotationId
                                  },
                                  success: function(response) {
                                    $('#notesContainer').html(response);
                                  }
                                });
                              }

                              function editNote(noteId, noteContent) {
                                document.getElementById('editNoteId').value = noteId;
                                document.getElementById('edit_note_content').value = noteContent;
                                $('#viewNotesModal').modal('hide');
                                $('#editNoteModal').modal('show');
                              }

                              function deleteNote(noteId) {
                                document.getElementById('deleteNoteId').value = noteId;
                                $('#viewNotesModal').modal('hide');
                                $('#deleteNoteModal').modal('show');
                              }
                            </script>
                          </tbody>
                        </table>
                      </div>

                      <div class="dataTable-bottom">
                        <?php if (ceil($total_pages / $num_results_on_page) > 0): ?>

                          <ul class="news">
                            <?php if ($page > 1): ?>
                              <li class="prev"><a href="quotations.php?page=<?php echo $page - 1 ?>">Prev</a></li>
                            <?php endif; ?>

                            <?php if ($page > 3): ?>
                              <li class="start"><a href="quotations.php?page=1">1</a></li>
                              <li class="dots">...</li>
                            <?php endif; ?>

                            <?php if ($page - 2 > 0): ?>
                              <li class="page"><a
                                  href="quotations.php?page=<?php echo $page - 2 ?>"><?php echo $page - 2 ?></a></li>
                            <?php endif; ?>
                            <?php if ($page - 1 > 0): ?>
                              <li class="page"><a
                                  href="quotations.php?page=<?php echo $page - 1 ?>"><?php echo $page - 1 ?></a></li>
                            <?php endif; ?>

                            <li class="currentpage"><a
                                href="quotations.php?page=<?php echo $page ?>"><?php echo $page ?></a></li>

                            <?php if ($page + 1 < ceil($total_pages / $num_results_on_page) + 1): ?>
                              <li class="page"><a
                                  href="quotations.php?page=<?php echo $page + 1 ?>"><?php echo $page + 1 ?></a></li>
                            <?php endif; ?>
                            <?php if ($page + 2 < ceil($total_pages / $num_results_on_page) + 1): ?>
                              <li class="page"><a
                                  href="quotations.php?page=<?php echo $page + 2 ?>"><?php echo $page + 2 ?></a></li>
                            <?php endif; ?>

                            <?php if ($page < ceil($total_pages / $num_results_on_page) - 2): ?>
                              <li class="dots">...</li>
                              <li class="end"><a
                                  href="quotations.php?page=<?php echo ceil($total_pages / $num_results_on_page) ?>"><?php echo ceil($total_pages / $num_results_on_page) ?></a>
                              </li>
                            <?php endif; ?>

                            <?php if ($page < ceil($total_pages / $num_results_on_page)): ?>
                              <li class="next"><a href="quotations.php?page=<?php echo $page + 1 ?>">Next</a></li>
                            <?php endif; ?>
                          </ul>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- The Modal -->
                <div class="modal fade" id="associatedProducts">
                  <div class="modal-dialog modal-xl">
                    <form method="POST">
                      <div class="modal-content">

                        <!-- Modal Header -->
                        <div class="modal-header modal-header-pro">
                          <h4 class="modal-title">New Estimate / Quotation</h4>
                        </div>

                        <!-- Modal body -->
                        <div class="modal-body">
                          <div class="row">
                            <div class="col-md-12">
                              <div class="form-group">
                                <input type="hidden" name="member_id" id="hid_member_id" value="">
                                <label for="member_select" class="form-label">Select Member</label>
                                <select class="form-control" id="member_select" onchange="fillMemberDetails()">
                                  <option value="">-- Select Member --</option>
                                  <?php
                                  $members = mysqli_query($con, "SELECT * FROM members ORDER BY name ASC");
                                  while ($m = mysqli_fetch_assoc($members)) {
                                    $memberId = (int) $m['member_id'];
                                    $fullName = htmlspecialchars(trim($m['name'] . ' ' . $m['mname'] . ' ' . $m['lname']));

                                    // what the USER sees:  id | name
                                    $visibleText = $memberId . ' | ' . $fullName;

                                    // what the JS uses to fill the fields (real values, not the visible text)
                                    $phone = htmlspecialchars(
                                      !empty($m['cell_ph']) ? $m['cell_ph'] : (!empty($m['phone']) ? $m['phone'] : 'N/A')
                                    );

                                    $email = htmlspecialchars(
                                      !empty($m['prm_email']) ? $m['prm_email'] : (!empty($m['sec_email']) ? $m['sec_email'] : 'N/A')
                                    );

                                    $city = htmlspecialchars(
                                      !empty($m['city']) ? $m['city'] : (!empty($m['hcity']) ? $m['hcity'] : 'N/A')
                                    );

                                    $state = htmlspecialchars(
                                      !empty($m['state']) ? $m['state'] : (!empty($m['hstate']) ? $m['hstate'] : 'N/A')
                                    );

                                    $zip = htmlspecialchars(
                                      !empty($m['zip']) ? $m['zip'] : (!empty($m['hzip']) ? $m['hzip'] : 'N/A')
                                    );

                                    echo "<option value='{$memberId}'
            data-name=\"{$fullName}\"
            data-phone=\"{$phone}\"
            data-email=\"{$email}\"
            data-city=\"{$city}\"
            data-state=\"{$state}\"
            data-zip=\"{$zip}\">
            {$visibleText}
          </option>";
                                  }
                                  ?>
                                </select>
                              </div>
                            </div>
                            <div class="col-md-4">
                              <div class="form-group">
                                <label for="name" class="form-label">Name</label><span class="text-danger">*</span>
                                <input class="form-control" required="required" name="name" type="text"
                                  placeholder="Enter Name" value="" id="name">
                              </div>
                            </div>
                            <div class="col-md-4">
                              <div class="form-group">
                                <label for="phonecell" class="form-label">Phone<span class="text-danger">*</span></label>
                                <input class="form-control" name="phonecell" type="tel" id="phonecell"
                                  placeholder="Phone:Cell" required="required" maxlength="14"
                                  oninput="formatPhoneNumber(this)">
                              </div>

                              <script>
                                function formatPhoneNumber(input) {

                                  let numbers = input.value.replace(/\D/g, '');

                                  if (numbers.length > 0) {
                                    numbers = '(' + numbers;
                                  }
                                  if (numbers.length > 4) {
                                    numbers = numbers.slice(0, 4) + ') ' + numbers.slice(4);
                                  }
                                  if (numbers.length > 9) {
                                    numbers = numbers.slice(0, 9) + '-' + numbers.slice(9);
                                  }
                                  input.value = numbers.slice(0, 14);
                                }
                              </script>

                            </div>
                            <div class="col-md-4">
                              <div class="form-group">
                                <label for="phonecell" class="form-label">Email<span class="text-danger"></span></label>
                                <input class="form-control" name="email" type="text" id="email" placeholder="Email">
                              </div>
                            </div>
                            <div class="row">
                              <div class="col-md-4">
                                <div class="form-group">
                                  <label for="city" class="form-label">City<span class="text-danger"></span></label>
                                  <input class="form-control" name="city" type="text" placeholder="City" id="city">
                                </div>
                              </div>

                              <div class="col-md-4">
                                <div class="form-group">
                                  <label for="state" class="form-label">State<span class="text-danger"></span></label>
                                  <input class="form-control" name="state" type="text" placeholder="State" id="state">
                                </div>
                              </div>

                              <div class="col-md-4">
                                <div class="form-group">
                                  <label for="zip" class="form-label">Zip<span class="text-danger"></span></label>
                                  <input class="form-control" name="zip" type="text" placeholder="Zip" id="zip">
                                </div>
                              </div>
                            </div>

                          </div>

                          <?php
                          $queryy = mysqli_query($con, "SELECT * FROM memberships");
                          $c = 1;
                          while ($services1 = mysqli_fetch_array($queryy)) {
                          ?>
                            <div class="form-group mt-2" id="category-dropdown<?php echo $services1['membership_id']; ?>">

                            </div>

                          <?php } ?>
                          <div class="col-md-12 mb-2">
                            <input type="text" id="serviceFilter" class="form-control"
                              placeholder="Type to filter services…">
                          </div>
                          <p style="color: #428bca;cursor: pointer;" onclick="myFunction()"><b><i class="fa fa-plus"
                                aria-hidden="true"></i> Add another service</b></p>
                          <div id="myDIV" style="display:block;">

                            <div class="row clearfix mb-3">
                              <?php
                              $query = mysqli_query($con, "SELECT * FROM memberships ORDER BY membership_name ASC");
                              $c = 1;
                              while ($services = mysqli_fetch_array($query)) {
                              ?>

                                <div class="col-md-4 service-item" style="margin-bottom: 8px;">
                                  <input type="hidden" name="main-catt"
                                    id="main-catt<?php echo $services['membership_id']; ?>"
                                    value="<?php echo $services['membership_id']; ?>">
                                  <div class="form-check-inline">
                                    <label class="form-check-label"
                                      for="addservice<?php echo $services['membership_id']; ?>">
                                      <input type="checkbox" class="form-check-input"
                                        id="addservice<?php echo $services['membership_id']; ?>" name="vehicle2"
                                        value="<?php echo $services['membership_id']; ?>">
                                      <?php echo $services['membership_name']; ?>
                                    </label>
                                  </div>
                                </div>

                                <script>
                                  $(document).ready(function() {
                                    $("#addservice<?php echo $services['membership_id']; ?>").on("click", function() {
                                      var main_id = $('#main-catt<?php echo $services['membership_id']; ?>').val();
                                      var sub_total = $('#sub_total').val();
                                      var discount = $('#discount').val();
                                      var grand_total = $('#grand_total').val();
                                      console.log("inside function");
                                      var myDIV = $('#myDIV').hide();

                                      $.ajax({
                                        url: "add-another-service.php",
                                        type: "POST",
                                        data: {
                                          main_id: main_id,
                                          sub_total: sub_total,
                                          discount: discount,
                                          grand_total: grand_total

                                        },
                                        cache: false,
                                        success: function(result) {
                                          $("#category-dropdown<?php echo $services['membership_id']; ?>").html(result);

                                        }
                                      });
                                    });
                                  });
                                </script>

                              <?php $c++;
                              } ?>
                            </div>

                          </div>
                          <div class="row">
                            <div class="col-md-10" style="text-align: end;"><label for="unit_price"
                                class="form-label">Subtotal</label></div>
                            <div class="col-md-2">
                              <input class="form-control" required="required" name="sub_total" type="number"
                                placeholder="Sub Total" id="sub_total" value="0" onchange="changetotal()"
                                onkeypress="changetotal()" onkeyup="changetotal()">
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-md-10" style="text-align: end;"><label for="unit_price"
                                class="form-label"></label></div>
                            <div class="col-md-2">
                              <label for="dissbox" class="form-label"><input type="checkbox" name="dissbox" id="dissbox"
                                  onchange="mydiscountrow()"> Add Discount</label>

                            </div>
                          </div>
                          <div class="row" style="display:none;" id="discountrow">
                            <div class="col-md-10" style="text-align: end;"><label for="unit_price"
                                class="form-label">Discount</label></div>
                            <div class="col-md-2">
                              <input class="form-control" required="required" name="discount" type="number"
                                placeholder="Discount" id="discount" value="0" onchange="getdiscount()"
                                onkeypress="getdiscount()" onkeyup="getdiscount()">
                            </div>
                          </div>

                          <div class="row">
                            <div class="col-md-10" style="text-align: end;"><label for="unit_price"
                                class="form-label">Subtotal</label></div>
                            <div class="col-md-2">
                              <input class="form-control" required="required" name="grand_total" type="number"
                                placeholder="Grand Total" id="grand_total" value="0">
                            </div>
                          </div>

                        </div> 

                        <!-- Modal footer -->
                        <div class="modal-footer">
                          <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                          <button type="submit" class="btn btn-primary" name="createinvoice">Submit</button>
                        </div>

                      </div>
                    </form>
                  </div>
                </div>

                <!-- ===== Edit Quotation Modal ===== -->
                <div class="modal fade" id="editQuotationModal" tabindex="-1" role="dialog"
                  aria-labelledby="editQuotationModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-xl" role="document">
                    <form method="POST" id="editQuotationForm">
                      <div class="modal-content">
                        <div class="modal-header modal-header-pro">
                          <h4 class="modal-title" id="editQuotationModalLabel">Edit Quotation
                            <small id="edit_sr_label" style="color:rgba(255,255,255,0.8);font-weight:400;"></small>
                          </h4>
                        </div>

                        <div class="modal-body">
                          <input type="hidden" name="update_quotation" value="1">
                          <input type="hidden" name="quotation_id" id="edit_quotation_id" value="">
                          <input type="hidden" name="member_id" id="edit_member_id" value="">

                          <div class="row">
                            <div class="col-md-4">
                              <div class="form-group">
                                <label class="form-label">Name<span class="text-danger">*</span></label>
                                <input class="form-control" required name="name" type="text" id="edit_name">
                              </div>
                            </div>
                            <div class="col-md-4">
                              <div class="form-group">
                                <label class="form-label">Phone<span class="text-danger">*</span></label>
                                <input class="form-control" required name="phonecell" type="tel" id="edit_phonecell"
                                  maxlength="14" oninput="formatPhoneNumber(this)">
                              </div>
                            </div>
                            <div class="col-md-4">
                              <div class="form-group">
                                <label class="form-label">Email</label>
                                <input class="form-control" name="email" type="text" id="edit_email">
                              </div>
                            </div>
                            <div class="col-md-4">
                              <div class="form-group">
                                <label class="form-label">City</label>
                                <input class="form-control" name="city" type="text" id="edit_city">
                              </div>
                            </div>
                            <div class="col-md-4">
                              <div class="form-group">
                                <label class="form-label">State</label>
                                <input class="form-control" name="state" type="text" id="edit_state">
                              </div>
                            </div>
                            <div class="col-md-4">
                              <div class="form-group">
                                <label class="form-label">Zip</label>
                                <input class="form-control" name="zip" type="text" id="edit_zip">
                              </div>
                            </div>
                          </div>

                          <hr>

                          <div class="row align-items-center mb-2">
                            <div class="col-md-12">
                              <h6 class="mb-0">Services</h6>
                            </div>
                          </div>

                          <!-- Container for existing + newly-added service rows -->
                          <div id="edit_services_container" class="mb-3">
                            <!-- rows injected via JS / AJAX (add-another-service.php) -->
                          </div>

                          <div class="col-md-12 mb-2 px-0">
                            <input type="text" id="editServiceFilter" class="form-control"
                              placeholder="Type to filter services…">
                          </div>

                          <div class="row clearfix mb-3">
                            <?php
                            $editServicesQuery = mysqli_query($con, "SELECT * FROM memberships ORDER BY membership_name ASC");
                            while ($editSvc = mysqli_fetch_array($editServicesQuery)) {
                              $emid = (int) $editSvc['membership_id'];
                            ?>
                              <div class="col-md-4 service-item edit-service-pick" style="margin-bottom: 8px;">
                                <div class="form-check-inline">
                                  <label class="form-check-label" for="edit_addservice<?= $emid ?>">
                                    <input type="checkbox" class="form-check-input edit-membership-checkbox"
                                      id="edit_addservice<?= $emid ?>"
                                      data-membership-id="<?= $emid ?>">
                                    <?= htmlspecialchars($editSvc['membership_name']) ?>
                                  </label>
                                </div>
                              </div>
                            <?php
                            }
                            ?>
                          </div>

                          <div class="row">
                            <div class="col-md-10 text-right"><label class="form-label">Subtotal</label></div>
                            <div class="col-md-2">
                              <input class="form-control" required name="sub_total" type="number" step="0.01"
                                id="edit_sub_total" value="0" readonly>
                            </div>
                          </div>

                          <div class="row">
                            <div class="col-md-10 text-right">
                              <label class="form-label">
                                <input type="checkbox" id="edit_dissbox" onchange="editToggleDiscount()"> Apply Discount
                              </label>
                            </div>
                          </div>

                          <div class="row" id="edit_discountrow" style="display:none;">
                            <div class="col-md-10 text-right"><label class="form-label">Discount</label></div>
                            <div class="col-md-2">
                              <input class="form-control" name="discount" type="number" step="0.01"
                                id="edit_discount" value="0" onchange="editRecalcGrand()" onkeyup="editRecalcGrand()">
                            </div>
                          </div>

                          <div class="row">
                            <div class="col-md-10 text-right"><label class="form-label">Grand Total</label></div>
                            <div class="col-md-2">
                              <input class="form-control" required name="grand_total" type="number" step="0.01"
                                id="edit_grand_total" value="0" readonly>
                            </div>
                          </div>
                        </div>

                        <div class="modal-footer">
                          <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                          <button type="submit" class="btn btn-primary">Save Changes &amp; Regenerate PDF</button>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>
                <!-- ===== /Edit Quotation Modal ===== -->

              </div>
            </div>

            <script>
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

                console.log("Sub Total=" + sub_total);
                console.log("Discount=" + discount);
                console.log("Grand=" + grand_total);
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

              /* ============ Edit Quotation modal ============ */
              function editEscapeAttr(s) {
                return String(s == null ? '' : s)
                  .replace(/&/g, '&amp;')
                  .replace(/"/g, '&quot;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;');
              }

              /* Build a service row in the SAME visual format as add-another-service.php
                 so existing services and newly-added ones look and behave identically. */
              function editBuildServiceRow(svc) {
                svc = svc || {
                  name: '',
                  desc: '',
                  price: ''
                };
                var name = editEscapeAttr(svc.name);
                var desc = editEscapeAttr(svc.desc);
                var price = svc.price !== undefined && svc.price !== null && svc.price !== '' ? svc.price : 0;
                return '' +
                  '<div class="row mb-2 edit-service-row" style="background:#e9e9e9;padding:10px 0;border-radius:4px;border-bottom:2px dashed #b9b9b9;">' +
                  '  <div class="col-md-4">' +
                  '    <label class="form-label">Service</label>' +
                  '    <input class="form-control" required name="service[]" type="text" value="' + name + '">' +
                  '  </div>' +
                  '  <div class="col-md-5">' +
                  '    <label class="form-label">Description</label>' +
                  '    <textarea name="description[]" class="form-control" rows="1">' + desc + '</textarea>' +
                  '  </div>' +
                  '  <div class="col-md-2">' +
                  '    <label class="form-label">Price</label>' +
                  '    <input class="form-control edit-service-price" required name="unit_price[]" type="number" min="0" step="0.01" value="' + price + '">' +
                  '  </div>' +
                  '  <div class="col-md-1 d-flex align-items-end">' +
                  '    <button type="button" class="btn btn-danger btn-sm edit-remove-service" title="Remove">&times;</button>' +
                  '  </div>' +
                  '</div>';
              }

              /* Walk every row inside #edit_services_container, sum the Price inputs.
                 Works for our pre-rendered rows AND any HTML appended by add-another-service.php
                 (which uses name="unit_price[]"). */
              function editRecalcSubtotal() {
                var sum = 0;
                document.querySelectorAll('#edit_services_container input[name="unit_price[]"]').forEach(function(inp) {
                  sum += parseFloat(inp.value) || 0;
                });
                document.getElementById('edit_sub_total').value = sum.toFixed(2);
                editRecalcGrand();
              }

              function editRecalcGrand() {
                var sub = parseFloat(document.getElementById('edit_sub_total').value) || 0;
                var disc = parseFloat(document.getElementById('edit_discount').value) || 0;
                var grand = sub - disc;
                if (grand < 0) grand = 0;
                document.getElementById('edit_grand_total').value = grand.toFixed(2);
              }

              function editToggleDiscount() {
                var row = document.getElementById('edit_discountrow');
                var checked = document.getElementById('edit_dissbox').checked;
                if (checked) {
                  row.style.display = 'flex';
                } else {
                  row.style.display = 'none';
                  document.getElementById('edit_discount').value = 0;
                  editRecalcGrand();
                }
              }

              /* Rows returned from add-another-service.php don't include a remove button.
                 After every append, ensure each row inside the container has one. */
              function editEnsureRemoveButtons() {
                document.querySelectorAll('#edit_services_container > .row').forEach(function(row) {
                  if (row.querySelector('.edit-remove-service')) return;
                  var slot = document.createElement('div');
                  slot.className = 'col-md-12 text-end mt-1';
                  slot.innerHTML = '<button type="button" class="btn btn-danger btn-sm edit-remove-service" title="Remove">&times; Remove</button>';
                  row.appendChild(slot);
                });
              }

              /* Live filter the membership checkbox grid in the Edit modal. */
              document.addEventListener('input', function(e) {
                if (e.target && e.target.id === 'editServiceFilter') {
                  var q = e.target.value.trim().toLowerCase();
                  document.querySelectorAll('#editQuotationModal .edit-service-pick').forEach(function(item) {
                    var label = (item.textContent || '').trim().toLowerCase();
                    item.style.display = !q || label.indexOf(q) !== -1 ? '' : 'none';
                  });
                  return;
                }
                /* Recalc when any price input inside the edit services container changes. */
                if (e.target && e.target.name === 'unit_price[]' &&
                  e.target.closest('#edit_services_container')) {
                  editRecalcSubtotal();
                }
              });

              /* Open Edit modal: populate fields + render existing services. */
              document.addEventListener('click', function(e) {
                var btn = e.target.closest('.edit-quotation-btn');
                if (btn) {
                  var data;
                  try {
                    data = JSON.parse(btn.getAttribute('data-quotation'));
                  } catch (err) {
                    console.error('Edit quotation: invalid data', err);
                    return;
                  }

                  document.getElementById('edit_quotation_id').value = data.id || '';
                  document.getElementById('edit_member_id').value = data.member_id || '';
                  document.getElementById('edit_sr_label').textContent = data.sr ? '(SR# ' + data.sr + ')' : '';
                  document.getElementById('edit_name').value = data.name || '';
                  document.getElementById('edit_phonecell').value = data.phone || '';
                  document.getElementById('edit_email').value = data.email || '';
                  document.getElementById('edit_city').value = data.city || '';
                  document.getElementById('edit_state').value = data.state || '';
                  document.getElementById('edit_zip').value = data.zip || '';
                  document.getElementById('edit_discount').value = data.discount || 0;

                  var discVal = parseFloat(data.discount) || 0;
                  document.getElementById('edit_dissbox').checked = discVal > 0;
                  document.getElementById('edit_discountrow').style.display = discVal > 0 ? 'flex' : 'none';

                  /* Reset checkbox grid + filter */
                  var filter = document.getElementById('editServiceFilter');
                  if (filter) filter.value = '';
                  document.querySelectorAll('#editQuotationModal .edit-membership-checkbox').forEach(function(cb) {
                    cb.checked = false;
                  });
                  document.querySelectorAll('#editQuotationModal .edit-service-pick').forEach(function(item) {
                    item.style.display = '';
                  });

                  /* Render existing services as editable rows */
                  var container = document.getElementById('edit_services_container');
                  container.innerHTML = '';
                  var svcs = Array.isArray(data.services) ? data.services : [];
                  svcs.forEach(function(s) {
                    container.insertAdjacentHTML('beforeend', editBuildServiceRow(s));
                  });

                  editRecalcSubtotal();

                  if (window.jQuery) {
                    jQuery('#editQuotationModal').modal('show');
                  } else {
                    document.getElementById('editQuotationModal').style.display = 'block';
                  }
                  return;
                }

                /* Remove a service row */
                var rm = e.target.closest('.edit-remove-service');
                if (rm) {
                  var row = rm.closest('.row');
                  if (row) row.parentNode.removeChild(row);
                  editRecalcSubtotal();
                  return;
                }
              });

              /* Membership checkbox click → AJAX to add-another-service.php → append row.
                 Mirrors the Add modal's flow (line ~1888) but appends into the Edit container
                 and lets users add the same service multiple times. */
              document.addEventListener('change', function(e) {
                var cb = e.target.closest('#editQuotationModal .edit-membership-checkbox');
                if (!cb) return;
                if (!cb.checked) return; // only fire on check
                var membershipId = cb.dataset.membershipId;
                cb.checked = false; // act like a button so it can be added repeatedly

                if (!window.jQuery) {
                  console.error('jQuery is required for the edit-modal AJAX append.');
                  return;
                }
                jQuery.ajax({
                  url: 'add-another-service.php',
                  type: 'POST',
                  data: {
                    main_id: membershipId,
                    sub_total: 0,
                    discount: 0,
                    grand_total: 0
                  },
                  cache: false,
                  success: function(result) {
                    /* Use vanilla append so the inline <script> from the response (which
                       writes to the Add-modal's #sub_total/#grand_total) doesn't run and
                       silently mutate the Add modal's state. We recalc ourselves below. */
                    document.getElementById('edit_services_container')
                      .insertAdjacentHTML('beforeend', result);
                    editEnsureRemoveButtons();
                    editRecalcSubtotal();
                  }
                });
              });
            </script>
            <!-- [ Main Content ] end -->
          </div>
        </div>
        <?php include "includes/footer.php"; ?>
        <script src="https://js.pusher.com/7.0.3/pusher.min.js"></script>
        <script>
          if (typeof window.pusher === 'undefined' && typeof window.Pusher !== 'undefined') {
            window.pusher = new Pusher("7c05973e1ae0fe5b7f28", {
              cluster: "us3",
              forceTLS: true
            });
          }
        </script>
        <?php include "includes/scripts.php"; ?>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
          /* ---- turn the members drop-down into Select2 ---- */
          $("#member_select").select2({
            dropdownParent: $("#associatedProducts"), // keep it inside the modal
            width: '100%', // fill the column
            placeholder: 'Start typing a name…',
            allowClear: true
          });
        </script>
        <script type="text/javascript">
          const checkbox = document.getElementById('show');
          const box = document.getElementById('box');
          if (checkbox && box) {
            checkbox.addEventListener('click', function handleClick() {
              if (checkbox.checked) {
                box.style.display = 'none';
              } else {
                box.style.display = 'block';
              }
            });
          }
        </script>
        <script>
          $(function() {
            $('.status-select').on('change', function() {
              const $sel = $(this);
              const id = $sel.data('id');
              const stat = $sel.val();

              /* keep tint class in sync with the chosen status */
              $sel.removeClass('status-sent status-accepted status-rejected')
                .addClass('status-' + stat);

              $.post('', // same page
                {
                  ajax_change_status: 1,
                  quotation_id: id,
                  new_status: stat
                },
                function(res) {
                  // optional: brief green flash
                  $sel.css('background', '#d4edda');
                  setTimeout(() => $sel.css('background', ''), 600);
                }
              );
            });
          });
        </script>
        <script>
          let currentQuotationId = 0;

          function enableEdit(id) {
            let p = document.getElementById('txt-' + id);
            p.contentEditable = true;
            p.focus();

            let box = p.parentElement.querySelector('.note-actions');
            box.innerHTML = `
        <button class="btn btn-sm btn-success" onclick="saveNote(${id})">Save</button>
        <button class="btn btn-sm btn-secondary" onclick="loadNotes(currentQuotationId)">Cancel</button>`;
          }

          function saveNote(id) {
            let text = document.getElementById('txt-' + id).innerText;
            $.post('', {
              edit_note: true,
              note_id: id,
              note_content: text
            }, () => loadNotes(currentQuotationId));
          }

          function delNote(id) {
            if (!confirm('Delete this note?')) return;
            $.post('', {
              delete_note: true,
              note_id: id
            }, () => loadNotes(currentQuotationId));
          }
        </script>

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
        <script>
          /* ---------- STATUS DOUGHNUT ---------- */
          const statusCtx = document.getElementById('statusChart');
          new Chart(statusCtx, {
            type: 'bar',
            data: {
              labels: <?= json_encode($statusLabels) ?>,
              datasets: [{
                data: <?= json_encode($statusCounts) ?>,
                backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#6c757d']
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false
            }
          });
        </script>
        <script>
          function fillMemberDetails() {
            const select = document.getElementById('member_select');
            const selected = select.options[select.selectedIndex];

            document.getElementById('name').value = selected.dataset.name || '';
            document.getElementById('phonecell').value = selected.dataset.phone || '';
            document.getElementById('email').value = selected.dataset.email || '';
            document.getElementById('city').value = selected.dataset.city || '';
            document.getElementById('state').value = selected.dataset.state || '';
            document.getElementById('zip').value = selected.dataset.zip || '';
            document.getElementById('hid_member_id').value = select.value;
          }
        </script>
        <script>
          function confirmConvert(button) {
            const quotationId = button.getAttribute('data-id');
            const $btn = $(button);
            const originalText = $btn.html();

            Swal.fire({
              title: 'Convert to Invoice?',
              text: 'This will create a new invoice from the quotation.',
              icon: 'question',
              showCancelButton: true,
              confirmButtonColor: '#28a745',
              cancelButtonColor: '#6c757d',
              confirmButtonText: 'Yes, Convert!',
              cancelButtonText: 'Cancel'
            }).then((result) => {
              if (result.isConfirmed) {
                // Disable button + show loading
                $btn.prop('disabled', true).html('<i class="ti ti-loader"></i> Converting...');

                // Submit form via AJAX
                $.post('', {
                    convert_to_invoice: true,
                    convert_quotation_id: quotationId
                  })
                  .done(function(response) {
                    try {
                      const res = JSON.parse(response);

                      if (res.success) { // ✅ everything OK
                        Swal.fire({
                          title: 'Success!',
                          text: 'Invoice created successfully!',
                          icon: 'success',
                          timer: 2000,
                          showConfirmButton: false
                        }).then(() => {
                          if (res.pdf_url) window.open(res.pdf_url, '_blank');
                          location.reload();
                        });
                      } else { // ❌ server explicitly said “failed”
                        Swal.fire('Error', res.msg || 'Conversion failed.', 'error');
                        $btn.prop('disabled', false).html(originalText); // restore button
                      }
                    } catch (e) { // malformed answer
                      Swal.fire('Error', 'Failed to parse server response.', 'error');
                      $btn.prop('disabled', false).html(originalText);
                    }
                  })
                  .fail(function() {
                    Swal.fire('Error', 'Failed to convert quotation.', 'error');
                    $btn.prop('disabled', false).html(originalText);
                  });
              }
            });
          }
        </script>
        <script>
          /* simple live-filter for the service grid */
          const serviceFilter = document.getElementById('serviceFilter');
          if (serviceFilter) {
            serviceFilter.addEventListener('keyup', function() {
              const kw = this.value.toLowerCase();
              document.querySelectorAll('.service-item').forEach(function(div) {
                div.style.display = div.textContent.toLowerCase().includes(kw) ? '' : 'none';
              });
            });
          }
        </script>

        <script>
          $(document).ready(function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
              Swal.fire({
                title: 'Success!',
                text: 'Quotation created successfully.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });

              // Optional: clean URL after showing
              window.history.replaceState({}, document.title, window.location.pathname);
            }
          });
        </script>
        <!-- deletion fixed -->
        <script>
          /* ---- disable "Invoice" button if quotation is already invoiced ---- */
          $(function() {
            $('table.datatable tbody tr').each(function() {
              const $row = $(this);
              // if row contains a "View Invoice" link, kill the button
              if ($row.find('a[href*="invoices/"]').length) {
                $row.find('button.convert-btn')
                  .prop('disabled', true)
                  .text('Invoiced')
                  .removeClass('btn-success')
                  .addClass('btn-outline-secondary');
              }
            });
          });
        </script>

        <script>
          /* ===== PDF HOVER PREVIEW (Quotation + Invoice) ===== */
          $(function() {
            const $overlay = $(`
        <div id="pdfPreviewOverlay"
             style="position:fixed; top:0; left:0; width:100%; height:100%;
                    background:rgba(0,0,0,.65); z-index:9999; display:none;">
          <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
                      width:70%; max-width:800px; height:80%; max-height:600px;
                      background:#fff; border-radius:6px; overflow:hidden;
                      box-shadow:0 5px 25px rgba(0,0,0,.3);">
              <button id="closePdfPreview"
                      style="position:absolute; right:8px; top:8px; z-index:1;
                             border:none; background:#e74c3c; color:#fff;
                             padding:4px 10px; border-radius:3px; cursor:pointer;">&times;</button>
              <iframe id="pdfPreviewFrame" src="" width="100%" height="100%" frameborder="0"></iframe>
          </div>
        </div>`).appendTo('body');

            let hoverTimer = null;

            $(document).on('mouseenter', '.view-file-preview', function() {
              const fileName = $(this).data('file');
              const folder = fileName.includes('QUO-') ? 'quotations/' : 'invoices/';
              const pdfPath = folder + fileName;

              clearTimeout(hoverTimer);
              hoverTimer = setTimeout(function() {
                $overlay.fadeIn(180);
                $('#pdfPreviewFrame').attr('src', pdfPath);
              }, 400);
            });

            $(document).on('mouseleave', '.view-file-preview', function() {
              clearTimeout(hoverTimer);
            });

            // Close overlay on click outside or on × button
            $overlay.on('click', function(e) {
              if (e.target === this || e.target.id === 'closePdfPreview') {
                $overlay.fadeOut(150);
                $('#pdfPreviewFrame').attr('src', ''); // free memory
              }
            });
          });
        </script>

        <script>
          $(function() {
            if ($.fn && typeof $.fn.DataTable === 'function') {
              $('.datatable').DataTable({
                pageLength: 25,
                searching: true,
                ordering: true,
                info: true
              });
            }
          });
        </script>
        <script>
          document.addEventListener('error', function(e) {
            const target = e.target;
            if (!target || target.tagName !== 'IMG' || !target.src) return;
            if (target.src.indexOf('/uploads/staff_images/') === -1) return;
            if (target.dataset.fallbackApplied === '1') return;
            target.dataset.fallbackApplied = '1';
            target.src = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
          }, true);
        </script>
        <script>
          /* ---- quotation-reminder feedback ---- */
          $(document).on('submit', 'form[action="send_quotation_reminder.php"]', function(e) {
            e.preventDefault();
            const $btn = $(this).find('button');
            $btn.prop('disabled', true).html('<i class="ti ti-loader"></i> Sending...');

            $.post($(this).attr('action'), $(this).serialize())
              .done(function(res) {
                Swal.fire({
                  title: 'Sent!',
                  text: res,
                  icon: 'success',
                  timer: 2000,
                  showConfirmButton: false
                });
              })
              .fail(function() {
                Swal.fire('Error', 'Could not send reminder.', 'error');
              })
              .always(function() {
                $btn.prop('disabled', false).html('<i class="ti ti-mail"></i>');
              });
          });
        </script>
  </body>

  </html>
<?php
  $listStmt->close();
}
?>