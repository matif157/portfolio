<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once 'dompdf/autoload.inc.php';
// reference the Dompdf namespace
use Dompdf\Dompdf;

include 'includes/db.php';
include "includes/auth.php";
include "includes/activity_log.php";
// require_once 'stripe_payment_ins.php';

$current_date = date('m/d/Y');
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

$client_id = $_GET['id'] ?? 200000;
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

function normalizePhoneForMatch($phone)
{
  return preg_replace('/\D+/', '', (string) $phone);
}

function findInstantInvoiceClientMatches($con, $phone, $email)
{
  $conditions = [];
  $normalizedPhone = normalizePhoneForMatch($phone);
  $normalizedEmail = strtolower(trim((string) $email));

  if ($normalizedPhone !== '') {
    $safePhone = mysqli_real_escape_string($con, $normalizedPhone);
    $phoneColumns = ['m.cell_ph', 'm.phone', 'm.other_ph', 'm.overseas_ph'];
    $phoneChecks = [];

    foreach ($phoneColumns as $column) {
      $phoneChecks[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($column, '-', ''), ' ', ''), '(', ''), ')', ''), '+', ''), '.', '') = '$safePhone'";
    }

    $conditions[] = '(' . implode(' OR ', $phoneChecks) . ')';
  }

  if ($normalizedEmail !== '') {
    $safeEmail = mysqli_real_escape_string($con, $normalizedEmail);
    $conditions[] = "(LOWER(TRIM(m.prm_email)) = '$safeEmail' OR LOWER(TRIM(m.sec_email)) = '$safeEmail')";
  }

  if (empty($conditions)) {
    return [];
  }

  $query = "SELECT 
              m.member_id,
              m.name,
              m.mname,
              m.lname,
              m.bus_name,
              m.cell_ph,
              m.phone,
              m.other_ph,
              m.overseas_ph,
              m.prm_email,
              m.sec_email,
              m.status,
              mt.type_name
            FROM members m
            LEFT JOIN member_type mt ON m.type = mt.type_id
            WHERE " . implode(' OR ', $conditions) . "
            ORDER BY m.member_id DESC
            LIMIT 10";

  $result = mysqli_query($con, $query);
  $matches = [];

  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $matchedOn = [];

      if ($normalizedPhone !== '') {
        foreach (['cell_ph', 'phone', 'other_ph', 'overseas_ph'] as $field) {
          if (normalizePhoneForMatch($row[$field] ?? '') === $normalizedPhone) {
            $matchedOn[] = 'Phone';
            break;
          }
        }
      }

      if ($normalizedEmail !== '') {
        foreach (['prm_email', 'sec_email'] as $field) {
          if (strtolower(trim((string) ($row[$field] ?? ''))) === $normalizedEmail) {
            $matchedOn[] = 'Email';
            break;
          }
        }
      }

      $row['matched_on'] = implode(' & ', array_unique($matchedOn));
      $matches[] = $row;
    }
  }

  return $matches;
}

function renderInstantInvoiceMatchCards($matches)
{
  if (empty($matches)) {
    return '';
  }

  $html = '<div class="alert alert-warning mt-3 mb-3"><strong>Matching client profile(s) found.</strong> Please review the client ID and module before creating this instant invoice.</div>';
  $html .= '<div class="row g-3">';

  foreach ($matches as $match) {
    $clientName = trim(($match['name'] ?? '') . ' ' . ($match['mname'] ?? '') . ' ' . ($match['lname'] ?? ''));
    if ($clientName === '') {
      $clientName = $match['bus_name'] ?: 'Client';
    }

    $statusLabel = ((int) ($match['status'] ?? 0) === 1) ? 'Active' : 'Inactive';
    $moduleLabel = $match['type_name'] ?: 'Unknown';
    $phoneLabel = $match['cell_ph'] ?: ($match['phone'] ?: ($match['other_ph'] ?: ($match['overseas_ph'] ?: 'N/A')));
    $emailLabel = $match['prm_email'] ?: ($match['sec_email'] ?: 'N/A');
    $matchedOn = $match['matched_on'] ?: 'Phone / Email';
    $clientId = (int) $match['member_id'];

    $html .= '
      <div class="col-md-6">
        <div class="card border-warning h-100 shadow-sm">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h6 class="mb-0">' . htmlspecialchars($clientName) . '</h6>
              <span class="badge bg-warning text-dark">Matched on ' . htmlspecialchars($matchedOn) . '</span>
            </div>
            <div class="small text-muted mb-2">Client ID: <strong>#' . $clientId . '</strong></div>
            <div class="small mb-1"><strong>Module:</strong> ' . htmlspecialchars($moduleLabel) . '</div>
            <div class="small mb-1"><strong>Phone:</strong> ' . htmlspecialchars($phoneLabel) . '</div>
            <div class="small mb-1"><strong>Email:</strong> ' . htmlspecialchars($emailLabel) . '</div>
            <div class="small mb-3"><strong>Status:</strong> ' . htmlspecialchars($statusLabel) . '</div>
            <a href="client_details.php?id=' . $clientId . '" class="btn btn-sm btn-outline-primary" target="_blank">Open Client Profile</a>
          </div>
        </div>
      </div>';
  }

  $html .= '</div>';

  return $html;
}

function renderPendingInstantInvoiceItems($services, $descriptions, $prices)
{
  if (empty($services) || !is_array($services)) {
    return '';
  }

  $html = '<div class="alert alert-info mt-3 mb-2">Your last invoice line items were preserved below so you can review the matched client and continue without re-entering them.</div>';
  $html .= '<div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>Service</th><th>Description</th><th style="width:140px;">Unit Price</th></tr></thead><tbody>';

  foreach ($services as $index => $service) {
    $serviceValue = (string) $service;
    $descriptionValue = (string) ($descriptions[$index] ?? '');
    $priceValue = (string) ($prices[$index] ?? '');

    if (trim($serviceValue) === '' && trim($descriptionValue) === '' && trim($priceValue) === '') {
      continue;
    }

    $html .= '<tr>
      <td>
        ' . htmlspecialchars($serviceValue) . '
        <input type="hidden" name="service[]" value="' . htmlspecialchars($serviceValue, ENT_QUOTES) . '">
      </td>
      <td>
        ' . htmlspecialchars($descriptionValue) . '
        <input type="hidden" name="description[]" value="' . htmlspecialchars($descriptionValue, ENT_QUOTES) . '">
      </td>
      <td>
        ' . htmlspecialchars($priceValue) . '
        <input type="hidden" name="unit_price[]" value="' . htmlspecialchars($priceValue, ENT_QUOTES) . '">
      </td>
    </tr>';
  }

  $html .= '</tbody></table></div>';

  return $html;
}

$msg = '';
$drows = '';
$instantInvoiceMatches = [];
$instantInvoicePendingData = [];
$reopenInstantInvoiceModal = false;


$user_full_name = $_SESSION['fullname'];
$user_initials = getInitials($user_full_name);
$emp_id_initials = getInitials($user_full_name) . '(' . $userid . ')';
$msg = '';

if (isset($_POST["createinvoice"])) {
  $msg = '';
  $variable = '';
  $drow = '';
  $instantInvoicePendingData = $_POST;

  $name = mysqli_real_escape_string($con, $_POST['inst_name'] ?? '');
  $business_name = mysqli_real_escape_string($con, $_POST['inst_business_name'] ?? '');
  $ssn = mysqli_real_escape_string($con, $_POST['inst_ssn'] ?? '');
  $phone = mysqli_real_escape_string($con, $_POST['inst_phone'] ?? '');
  $email = mysqli_real_escape_string($con, $_POST['inst_email'] ?? '');
  $address = mysqli_real_escape_string($con, $_POST['inst_address'] ?? '');
  $city = mysqli_real_escape_string($con, $_POST['inst_city'] ?? '');
  $state = mysqli_real_escape_string($con, $_POST['inst_state'] ?? '');
  $zip = mysqli_real_escape_string($con, $_POST['inst_zip'] ?? '');

  // Build address line 2
  $address1 = trim("$city, $state, $zip", ', ');

  $time = time();
  $m = date('m');
  $curDate = date('m/d/Y');
  $curDateMySQL = date('m/d/Y');
  $currentMonth = date("Y-m");

  $services = $_POST['service'];
  $price = $_POST['unit_price'];
  $description = $_POST['description'];

  $sub_total = $_POST['sub_total'];
  $discount = $_POST['discount'];
  $grand_total = $_POST['grand_total'];

  $discountRow = '';

  $service_provider_id = $_POST['Service_Provider'];
  $is_ach = !empty($_POST['mark_ach']) ? 1 : 0;
  $service_provider_name = '';

  if (!empty($service_provider_id)) {
    $sp_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . mysqli_real_escape_string($con, $service_provider_id) . "'");
    if ($sp_result = mysqli_fetch_assoc($sp_query)) {
      $service_provider_name = $sp_result['user_name'];
    }
  }
  $service_provider_name = getInitials($service_provider_name);

  if (strpos($discount, '.') === false) {
    $discount = sprintf("%.2f", $discount);
  }
  if ($discount > 0) {
    $discountRow = ' <tr class="total">
                    <td></td>
                    <td style="font-size:20px;font-weight:bold;">Discount: $' . $discount . '</td>
                </tr>';
  } else {
    $discountRow = '';
  }

  $price_single = array_sum($price);
  $size = count($services);

  $invoice_date = date("m/d/Y h:i:s");
  $invoice_date2 = date("Y-m-d h:i:s");
  $duedate = $_POST['billing_cycle'];
  $billing_month = '';

  $invoice_status = $_POST['invoice_status'] ?? '1';
  $bypassClientMatch = !empty($_POST['bypass_client_match']);

  $paid_date = '0000-00-00'; // Default for unpaid
  if ($invoice_status == '2' || $invoice_status == '3') {
    // Partial Paid or Paid - get date from form or use today
    $paid_date = !empty($_POST['paid_date']) ? $_POST['paid_date'] : date('Y-m-d');
  }

  if (strpos($sub_total, '.') === false) {
    $sub_total = sprintf("%.2f", $sub_total);
  }
  if (strpos($grand_total, '.') === false) {
    $grand_total = sprintf("%.2f", $grand_total);
  }
 
 

  if ($invoice_status == '2') {
    $status_img = 'http://192.168.15.29/storage/uploads/logo/partial_payment.jpg';
    $status_width = '40%';
  } elseif ($invoice_status == '3') {
    $status_img = 'http://192.168.15.29/storage/uploads/logo/paid.png';
    $status_width = '40%';
  } else {
    $status_img = 'http://192.168.15.29/storage/uploads/logo/unpaid.jpg';
    $status_width = '15%';
  }

  $instantInvoiceMatches = findInstantInvoiceClientMatches($con, $phone, $email);

  if (!$bypassClientMatch && !empty($instantInvoiceMatches)) {
    $reopenInstantInvoiceModal = true;
    $msg .= "<div class='alert alert-warning'>A client profile already exists with the same phone number or email. Review the matched client ID and module before proceeding.</div>";
  } else {
  $insert_billing = "INSERT INTO billing_invoice_instant(
    mem_id, name, business_name, ssn, phone, email, address, city, state, zip,
    billing_id, temp_id, invoice_date, filelink,
    due_date, total_amount, paid_amount, discount, balance,
    invoice_status, payment_type, payment_details, paid_date,
    billing_cycle, service_provider_id, is_ach, employee_initials
) VALUES (
    '$client_id', '$name', '$business_name', '$ssn', '$phone', '$email', '$address', '$city', '$state', '$zip',
    '0','0','$invoice_date','',
    '$duedate','$sub_total','0','$discount','$grand_total',
    '$invoice_status','0','0','$paid_date','$billing_month',
    '$service_provider_id','$is_ach','$emp_id_initials'
)";

  // ✅ MISSING LINE - Execute the query!
  mysqli_query($con, $insert_billing);

  // Now get the insert ID

  $invoice_no = mysqli_insert_id($con);

  $US_Date = date('m-d-Y');
  $PDFFileN = "INV-{$invoice_no}-inst-{$US_Date}.pdf";
  $Filename = "instant_invoices/" . $PDFFileN;



  for ($i = 0; $i < $size; $i++) {
    $servicess = $services[$i];
    $priceee = $price[$i];
    if (strpos($priceee, '.') === false) {
      $priceee = sprintf("%.2f", $priceee);
    }
    $descriptionn = $description[$i];
    $drows .= '<tr class="item">
               <td><b>' . $services[$i] . '</b><p style="font-size:12px;margin:0px">' . $descriptionn . '</p></td>
               <td>$' . $priceee . '</td>
            </tr>';
    $que = "INSERT INTO `billing_invoice_items`(`inv_id`, `temp_id`, `billing_id`, `mem_id`, `item_description`, `item_sub_description`, `unit_price`, `quantity`, `total_price`, `date_added`, `user_id`, `item_date`) VALUES ('$invoice_no', 0, 0,'$client_id','$servicess','$descriptionn','$priceee','1','$priceee','$invoice_date2','$userid','$invoice_date2')";
    mysqli_query($con, $que);
  }


  $originalFormat = 'Y-m-d';
  $desiredFormat = 'm/d/Y';
  $dateTime = DateTime::createFromFormat($originalFormat, $duedate);
  if ($dateTime) {
    $duedate = $dateTime->format($desiredFormat);
  }


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
         <img src="http://192.168.15.29/storage/uploads/logo/logo-dark.png" alt="Company logo" style="width: 60%; max-width: 300px" />
      </td>
      <td>
   Invoice #: ' . $invoice_no . '<br />
   By: ' . $user_initials . '(' . $userid . ')<br/>
   Created: ' . $curDateMySQL . '<br />
   Due: ' . $duedate . '<br />' .
    (($invoice_status == '3' || $invoice_status == '2') && $paid_date != '0000-00-00'
      ? 'Paid Date: ' . date('m/d/Y', strtotime($paid_date)) . '<br />'
      : '') .
    '<img src="' . $status_img . '" alt="Status" style="width: ' . $status_width . ';margin-top:12px; max-width: 300px" />
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
                          Phone: (718) 859-5392<br />
                          Fax: (718) 859-2237<br />
                          invoice@syedpro.com<br />
                        </td>
                        <td>
                        <b>To:</b> <br />
                        ' . htmlspecialchars($name) . '<br />
                        ' . htmlspecialchars($address) . '<br />
                        ' . htmlspecialchars($address1) . '<br />
                        ' . htmlspecialchars($phone) . '<br />
                        ' . htmlspecialchars($email) . '<br />
                        </td>
                     </tr>
                  </table>
               </td>
            </tr>
            <tr class="heading">
               <td>Service</td>
               <td>Price</td>
            </tr>
           <tr class="details">
  <td><b>Service Provider:</b> ' . $service_provider_name . ' (' . $service_provider_id . ')</td>
</tr>
            ' . $drows . '
            <td><b>Description:</b></td>
             <tr class="total">
                    <td></td>
                    <td style="font-weight:bold;">Sub Total: $' . $sub_total . '</td>
                </tr>' . $discountRow . '
             <tr class="total">
                    <td></td>
                    <td style="font-weight:bold;">Grand Total: $' . $grand_total . '</td>
                </tr>
                <tr class="details">
         
      </tr>
            </table>
           <div class="footer-note">
          <p>In the event of a returned or unsuccessful payment, a bank fee of $25 will be applied.
          <br>We accept the payment via Zelle,Credit or debit Card,Cash and cheque.
          </p>
          <h4><u>Thank you for choosing Syed Professional Services</u></h4>
</div>
            </div>';


  $dompdf = new Dompdf(array('enable_remote' => true));
  $dompdf->loadHtml($content);
  $dompdf->render();
  file_put_contents($Filename, $dompdf->output());


  mysqli_query($con, "UPDATE billing_invoice_instant
                     SET filelink = '" . mysqli_real_escape_string($con, $PDFFileN) . "'
                     WHERE invoice_id = $invoice_no");


  if (mysqli_affected_rows($con) > 0) {
    logActivity($con, $userid, 'CREATE', 'billing_invoice_instant', $invoice_no, "Created new instant invoice #{$invoice_no} with a total of \${$grand_total}.");
    $msg .= "<div class='alert alert-success'>Invoice created successfully! ✅</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Failed to create invoice. ❌ Please try again.</div>";
  }
  }
}





if (isset($_POST['update_invoice'])) {
  $invoice_id = $_POST['invoice_id'];
  $due_date = $_POST['edit_due_date'];
  $paid_date = !empty($_POST['edit_paid_date']) ? date('Y-m-d', strtotime($_POST['edit_paid_date'])) : '0000-00-00';

  $total_amount = $_POST['edit_total_amount'];
  $paid_amount = $_POST['edit_paid_amount'];
  $balance = $_POST['edit_balance'];
  $description = $_POST['description'];
  $user_id = $_SESSION["user_id"];
  $discount = $_POST['edit_discount'];
  $service_provider_id = $_POST['edit_service_provider'];

  $name = mysqli_real_escape_string($con, $_POST['edit_name']);
  $business_name = mysqli_real_escape_string($con, $_POST['edit_business_name'] ?? '');
  $ssn = mysqli_real_escape_string($con, $_POST['edit_ssn'] ?? '');
  $phone = mysqli_real_escape_string($con, $_POST['edit_phone']);
  $email = mysqli_real_escape_string($con, $_POST['edit_email']);
  $address = mysqli_real_escape_string($con, $_POST['edit_address']);
  $city = mysqli_real_escape_string($con, $_POST['edit_city']);
  $state = mysqli_real_escape_string($con, $_POST['edit_state']);
  $zip = mysqli_real_escape_string($con, $_POST['edit_zip']);

  $is_ach = !empty($_POST['edit_mark_ach']) ? 1 : 0;

  /* service-provider name (initials) */
  $service_provider_name = '';
  if (!empty($service_provider_id)) {
    $sp_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . mysqli_real_escape_string($con, $service_provider_id) . "'");
    if ($sp_result = mysqli_fetch_assoc($sp_query)) {
      $service_provider_name = getInitials($sp_result['user_name']);
    }
  }

  /* payment status */
  if ($paid_amount >= $total_amount)
    $payment_status = 3;
  elseif ($paid_amount > 0)
    $payment_status = 2;
  else
    $payment_status = 1;

  /* ----------  1.  read the OLD row BEFORE we overwrite it  ---------- */
  $oldRow = mysqli_fetch_assoc(
    mysqli_query($con, "SELECT * FROM billing_invoice_instant WHERE invoice_id='" . (int) $invoice_id . "' LIMIT 1")
  );

  /* ----------  2.  update the invoice  --------------------------------*/
  $update_query = "UPDATE billing_invoice_instant SET 
                   due_date='$due_date', 
                   paid_date='$paid_date', 
                   total_amount='$total_amount', 
                   paid_amount='$paid_amount', 
                   balance='$balance', 
                   invoice_status='$payment_status', 
                   discount='$discount', 
                   payment_details='" . mysqli_real_escape_string($con, $description) . "',
                   service_provider_id='" . (int) $service_provider_id . "',
                   name='$name',
                   business_name='$business_name',
                   ssn='$ssn',
                   phone='$phone',
                   email='$email',
                   address='$address',
                   city='$city',
                   state='$state',
                   zip='$zip',
                   is_ach='$is_ach',
                   last_edited_by='$emp_id_initials'
                 WHERE invoice_id='" . (int) $invoice_id . "'";

  if (mysqli_query($con, $update_query)) {

    /* ---- PDF re-generation (your existing code) --------------------- */
    $curDateMySQL = date('m/d/Y');
    $US_Date = date('m-d-Y');
    $PDFFileN = "INV-{$invoice_id}-inst-{$US_Date}.pdf";
    $Filename = "instant_invoices/" . $PDFFileN;



    $creatorRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT employee_initials FROM billing_invoice_instant WHERE invoice_id='" . (int) $invoice_id . "' LIMIT 1"));
    $original_creator = $creatorRow['employee_initials'];
    $current_editor = $emp_id_initials;

    $items_query = mysqli_query($con, "SELECT * FROM billing_invoice_items WHERE inv_id='" . (int) $invoice_id . "'");
    $drows = '';
    while ($item = mysqli_fetch_array($items_query)) {
      $drows .= '<tr class="item">
                  <td><b>' . htmlspecialchars($item['item_description']) . '</b>
                      <p style="font-size:12px;margin:0px">' . htmlspecialchars($item['item_sub_description']) . '</p></td>
                  <td>$' . number_format($item['unit_price'], 2) . '</td>
                </tr>';
    }

    switch ($payment_status) {
      case '3':
        $status_text = "Paid";
        $image_src = "http://192.168.15.29/storage/uploads/logo/paid.png";
        break;
      case '2':
        $status_text = "Partial Paid";
        $image_src = "http://192.168.15.29/storage/uploads/logo/partial_payment.jpg";
        break;
      default:
        $status_text = "Unpaid";
        $image_src = "http://192.168.15.29/storage/uploads/logo/unpaid.jpg";
        break;
    }

  $discountRow = '';
  if ($discount > 0) {
    $discountRow = "<tr class='total'><td></td><td>Discount: \$" . number_format($discount, 2) . "</td></tr>";
  }
  $due_us = date('m/d/Y', strtotime($due_date));
  $paid_date_html = (($payment_status == 2 || $payment_status == 3) && $paid_date !== '0000-00-00')
    ? "Paid Date: " . date('m/d/Y', strtotime($paid_date)) . "<br />" : '';

    /* ---- PDF content (unchanged) ----------------------------------- */
    $content = <<<EOD
<style>
.invoice-box {
  position: relative;
  height: 99%;
  width: 100%;
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
                            <img src="http://192.168.15.29/storage/uploads/logo/logo-dark.png" alt="Company logo" style="width: 60%; max-width: 300px" />
                        </td>
                        <td>
                            Invoice #: {$invoice_id}<br />
                            By: {$original_creator}<br />
                            Created: {$curDateMySQL}<br />
                            Edit By: {$current_editor}<br />
                            Due: {$due_us}<br />
                            {$paid_date_html}
                            <img src="{$image_src}" alt="Company logo" style="width: 40%; margin-top: 12px; max-width: 300px" /> 
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
                            1012 Coney Island Ave<br />Brooklyn, NY 11230<br />
                            Phone: (718) 859-5392<br />
                            Fax: (718) 859-2237<br />
                            invoice@syedpro.com<br />
                        </td>
                        <td>
                        <b>To:</b> <br />
                        {$name}<br />
                        {$address}<br />
                        {$city}, {$state}, {$zip}<br />
                        {$phone}<br />
                        {$email}<br />
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr class="heading">
            <td>Service</td>
            <td>Price</td>
        </tr>
        <tr class="details">
            <td><b>Service Provider:</b> {$service_provider_name} ({$service_provider_id})</td>
        </tr>
        {$drows}
        <tr class="details">
            <td><b>Description:</b> {$description}</td> 
        </tr>
        {$discountRow}
        <tr class="total">
            <td></td>
            <td>Total: \${$total_amount}</td>
        </tr>
        <tr class="total">
            <td></td>
            <td>Paid: \${$paid_amount}</td>
        </tr>
        <tr class="total">
            <td></td>
            <td>Balance: \${$balance}</td>
        </tr>
        
    </table>
    <div class="footer-note">
        <p>In the event of a returned or unsuccessful payment, a bank fee of \$25 will be applied.
        <br>We accept the payment via Zelle,Credit or debit Card,Cash and cheque.
        </p>
        <h4><u>Thank you for choosing Syed Professional Services</u></h4>
    </div>
</div>
EOD;

    $dompdf = new Dompdf(['enable_remote' => true]);
    $dompdf->loadHtml($content);
    $dompdf->render();
    file_put_contents($Filename, $dompdf->output());

    /* ---- update PDF filelink --------------------------------------- */
    mysqli_query($con, "UPDATE billing_invoice_instant SET filelink='" . mysqli_real_escape_string($con, $PDFFileN) . "' WHERE invoice_id='" . (int) $invoice_id . "'");

    /* ----------  3.  detailed change-log  ---------------------------- */
    $new = [
      'due_date' => $due_date,
      'paid_date' => $paid_date,
      'total_amount' => $total_amount,
      'paid_amount' => $paid_amount,
      'balance' => $balance,
      'discount' => $discount,
      'service_provider_id' => $service_provider_id,
      'payment_details' => $description,
      'name' => $name,
      'phone' => $phone,
      'email' => $email,
      'address' => $address,
      'city' => $city,
      'state' => $state,
      'zip' => $zip,
    ];

    $changed = [];

    foreach ($new as $k => $v) {
      $oldVal = $oldRow[$k] ?? '';

      if ((string) $v !== (string) $oldVal) {
        $label = ucwords(str_replace('_', ' ', $k));

        // Format date fields in US format
        if (stripos($k, 'date') !== false && $v) {
          $oldVal = $oldVal ? date('m-d-Y', strtotime($oldVal)) : '';
          $v = $v ? date('m-d-Y', strtotime($v)) : '';
        }

        // Special handling for service provider
        if ($k === 'service_provider_id' && $v) {
          $spOld = $oldVal
            ? mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id='" . (int) $oldVal . "'"))['user_name']
            : '';
          $spNew = mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id='" . (int) $v . "'"))['user_name'];
          $changed[] = "Service Provider changes {$spOld} To {$spNew}";
        }
        // All other fields
        else {
          $changed[] = "{$label} changes {$oldVal} To {$v}";
        }
      }
    }

    // Combine changes for logging
    $changeText = empty($changed) ? 'no fields changed' : implode(' | ', $changed);

    // Log activity
    logActivity(
      $con,
      $userid,
      'UPDATE',
      'billing_invoice_instant',
      $invoice_id,
      "Edited instant invoice #{$invoice_id} – {$changeText}"
    );

    $msg .= "<div class='alert alert-success'>Invoice updated successfully.</div>";


  } else {
    $msg .= "<div class='alert alert-danger'>Error updating invoice: " . mysqli_error($con) . "</div>";
  }
}



if (isset($_POST['delete_invoice'])) {
  $msg = '';

  $invoice_id = $_POST['invoice_id'];

  $queryy = "DELETE FROM billing_invoice_instant where invoice_id='$invoice_id'";
  $insert_query = mysqli_query($con, $queryy);

  if ($insert_query) {
    logActivity($con, $userid, 'DELETE', 'billing_invoice_instant', $invoice_id, "Deleted instant invoice #{$invoice_id}.");
    $msg .= "<div class='alert alert-success'>Record Been Deleted Successfully.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
  }
}

if (isset($_POST['addpayment'])) {
  $msg = '';
  $newstatus = '';
  $invoice_id = $_POST['invoice_id'];
  $totalamount = $_POST['totalamount'];
  $balance = $_POST['balance'];
  $recievedamount = $_POST['recievedamount'];
  $paidamount = $_POST['paidamount'];
  $duedate = $_POST['duedate'];
  $memberid = $_POST['memberid'];
  $date = date_create($duedate);
  $drows = '';
  $after_discount = $_POST['after_discount'];
  $old_discount = $_POST['old_discount'];

  $discount1 = $after_discount - $old_discount;
  $newpaid = $recievedamount;
  $newbalance = $balance - $newpaid - $discount1;
  $newpaid = $newpaid + $paidamount;
  $description = $_POST['description'];
  $payment_types = [];
  if (!empty($_POST['payment_type'])) {
    // Try to decode JSON first
    $payment_types = json_decode($_POST['payment_type'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      // Fallback to array if not JSON
      $payment_types = is_array($_POST['payment_type']) ? $_POST['payment_type'] : [$_POST['payment_type']];
    }
  } elseif (!empty($_POST['payment_types'])) {
    // Alternative: get from payment_types[] array 
    $payment_types = is_array($_POST['payment_types']) ? $_POST['payment_types'] : [$_POST['payment_types']];
  }

  // Clean and validate payment types
  $payment_types = array_filter(array_map('trim', $payment_types));
  $payment_methods_str = !empty($payment_types) ? implode(', ', $payment_types) : 'N/A';

  // Get service provider info from invoice
  $invoice_query = mysqli_query($con, "SELECT service_provider_id FROM billing_invoice_instant WHERE invoice_id='$invoice_id'");
  $invoice_data = mysqli_fetch_assoc($invoice_query);
  $service_provider_id = $invoice_data['service_provider_id'] ?? null;
  $service_provider_name = '';
  $is_ach = !empty($_POST['edit_mark_ach']) ? 1 : 0;
  if (!empty($service_provider_id)) {
    $sp_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . mysqli_real_escape_string($con, $service_provider_id) . "'");
    if ($sp_result = mysqli_fetch_assoc($sp_query)) {
      $service_provider_name = $sp_result['user_name'];
    }
  }
  $service_provider_name = getInitials($service_provider_name);



  $filepath = '';

  if ($newbalance <= 0) {
    $newstatus = '3';
    $filepath .= '<img src="http://192.168.15.29/storage/uploads/logo/paid.png" alt="Company logo" style="width: 30%;margin-top:12px; max-width: 300px" />';
    $status_text = 'Paid';
  } elseif ($newbalance < $totalamount) {
    $newstatus = '2';
    $filepath .= '<img src="http://192.168.15.29/storage/uploads/logo/partial_payment.jpg" alt="Company logo"  style="width: 30%;margin-top:12px; max-width: 300px" />';
    $status_text = 'Partial Paid';
  } else {
    $newstatus = '1';
    $filepath .= '<img src="http://192.168.15.29/storage/uploads/logo/unpaid.jpg" alt="Company logo" style="width: 30%;margin-top:12px; max-width: 300px" />';
    $status_text = 'UnPaid';
  }

  $time = time();
  $m = date('m');
  $curDate = date('m/d/Y');
  $curDateMySQL = date('m/d/Y');
  $currentMonth = date("Y-m");

  $US_Date = date('m-d-Y');
  $PDFFileN = "INV-{$invoice_id}-MemID-{$memberid}-{$US_Date}.pdf";
  $Filename = "instant_invoices/" . $PDFFileN;

  $invoice_client_query = mysqli_query($con, "SELECT name, phone, email, address, city, state, zip 
                                            FROM billing_invoice_instant 
                                            WHERE invoice_id='$invoice_id' 
                                            LIMIT 1");
  $inv_data = mysqli_fetch_assoc($invoice_client_query);

  $name = $inv_data['name'];
  $phone = $inv_data['phone'];
  $email = $inv_data['email'];
  $address = $inv_data['address'];
  $address1 = $inv_data['city'] . ", " . $inv_data['state'] . ", " . $inv_data['zip'];

  // $email = $fetch_client['prm_email'];
  // $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
  // $phone = $fetch_client['phone'];

  // Get all invoice items
  $drows = ''; // Initialize empty string for items
  $fetch_item_query = mysqli_query($con, "SELECT * FROM billing_invoice_items WHERE inv_id='$invoice_id'");
  while ($fetch_inv_items = mysqli_fetch_array($fetch_item_query)) {
    $drows .= '<tr class="item">
                   <td>
                       <b>' . htmlspecialchars($fetch_inv_items['item_description']) . '</b>
                       <p style="font-size:12px;margin:0px">' . htmlspecialchars($fetch_inv_items['item_sub_description']) . '</p>
                   </td>
                   <td>$' . number_format($fetch_inv_items['unit_price'], 2) . '</td>
                </tr>';
  }


  $initials_name = getInitials($user_full_name);

  $totalamount = str_replace(',', '', $totalamount);
  $paidamount = str_replace(',', '', $paidamount);
  $balance = str_replace(',', '', $balance);



  $user_initials = $initials_name . '(' . $userid . ')';
  $paid_date = $_POST['paid_date'] ?: date('Y-m-d');
  // Update invoice record
  $queryy = "UPDATE billing_invoice_instant SET 
               total_amount='$totalamount', 
               balance='$newbalance', 
               paid_amount='$newpaid', 
               invoice_status='$newstatus', 
               filelink='$PDFFileN', 
               payment_details='$description', 
               discount='$after_discount',
                employee_initials='$emp_id_initials' ,
                is_ach='$is_ach',
                paid_date = '$paid_date'
               WHERE invoice_id='$invoice_id'";

  $insert_query = mysqli_query($con, $queryy);
  $date2 = date('Y-m-d h:i:s');
  $query = "INSERT INTO `billing_payment_history` 
          (`mem_id`, `inv_id`, `paid`, `balance`, `detail`, `date`, `employee`, `payment_method`) 
          VALUES('$memberid','$invoice_id','$newpaid','$balance','$description','$date2','$emp_id_initials', '$payment_methods_str')";

  mysqli_query($con, $query);

  if ($insert_query) {
    $originalFormat = 'Y-m-d';
    $desiredFormat = 'm/d/Y';
    $dateTime = DateTime::createFromFormat($originalFormat, $duedate);
    if ($dateTime) {
      $duedate = $dateTime->format($desiredFormat);
    }

    $discountRow = '';
    $discount = number_format($totalamount - ($newpaid + $newbalance), 2);

    if ($discount > 0) {
      $discountRow = '<tr class="total">
                <td></td>
                <td style="font-size:20px;font-weight:bold;">Discount: $' . $discount . '</td>
            </tr>';
    }

    // Generate PDF content
    $content = '
        <style>
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
            width: 100%;
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
        .invoice-box table tr.heading td {
            background: #8b742a;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            color: white;
            font-size:22px;
        }
        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }
        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
        }
        </style>
        <div class="invoice-box">
            <table>
                <tr class="top">
                    <td colspan="2">
                        <table>
                            <tr>
                                <td class="title">
                                    <img src="http://192.168.15.29/storage/uploads/logo/logo-dark.png" style="width:60%; max-width:300px;">
                                </td>
                                <td>
                                    Invoice #: ' . $invoice_id . '<br>
                                    By: ' . $user_initials . '<br>
                                    Created: ' . $curDateMySQL . '<br>
                                    Due: ' . $duedate . '<br>
                                    ' . $status_text . ': ' . date('m/d/Y', strtotime($paid_date)) . '<br>
                                    ' . $filepath . '
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="information">
                    <td colspan="2">
                        <table>
                            <tr>
                                <td>
                                    From:<br>
                                    Syed Professional Services<br>
                                    1012 Coney Island Ave<br>
                                    Brooklyn, NY 11230<br>
                                    Phone: (718) 859-5392<br />
                                    Fax: (718) 859-2237<br />
                                    invoice@syedpro.com
                                </td>
                                <td>
                                    <b>To:</b> <br>
                                    ' . $name . '<br>
                                    ' . $address . '<br>
                                    ' . $address1 . '<br>
                                    ' . $phone . '<br>
                                    ' . $email . '
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="heading">
                    <td>Service</td>
                    <td>Price</td>
                </tr>
               <tr class="details">
  <td><b>Service Provider:</b> ' . $service_provider_name . ' (' . $service_provider_id . ')</td>
</tr>
                ' . $drows . '
                 <tr class="details">
                    <td><b>Description:</b> ' . htmlspecialchars($description) . '</td>
                </tr>
                <tr class="total">
                    <td></td>
                    <td>Total: $' . $totalamount . '</td>
                </tr>
                <tr class="total">
                    <td></td>
                    <td>Paid: $' . $newpaid . '</td>
                </tr>
                ' . $discountRow . '
                <tr class="total">
                    <td></td>
                    <td>Balance: $' . $newbalance . '</td>
                </tr>
               
                <tr class="details">
    <td><b>Payment Method(s):</b> ' . $payment_methods_str . '</td>
</tr>

            </table>
            <div class="footer-note">
                <p>In the event of a returned or unsuccessful payment, a bank fee of $25 will be applied.
                <br>We accept the payment via Zelle,Credit or debit Card,Cash and cheque.
                </p>
                <h4><u>Thank you for choosing Syed Professional Services</u></h4>
            </div>
        </div>';

    // Generate PDF
    $dompdf = new Dompdf(array('enable_remote' => true));
    $dompdf->loadHtml($content);
    $dompdf->render();
    $output = $dompdf->output();
    file_put_contents($Filename, $output);

    logActivity($con, $userid, 'UPDATE', 'billing_invoice_instant', $invoice_id, "Added payment of \${$newpaid} to instant invoice #{$invoice_id}. New balance: \${$newbalance}.");
    $msg .= "<div class='alert alert-success'>Payment Has Been Added.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . mysqli_error($con);
  }
}








if (isset($_POST['add_invoice_comment'])) {
  $invoice_id = intval($_POST['invoice_id']);   // from the hidden input
  $member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
  $comment = mysqli_real_escape_string($con, $_POST['invoice_comment']);
  $user_id = $_SESSION['user_id'];
  $time = date('Y-m-d H:i:s');

  $query = "INSERT INTO instant_comments
              (comment_detail, member_id, ins_invoice_id, user_id, comment_time, comment_status, comment_updated_by)
              VALUES ('$comment', '$member_id', '$invoice_id', '$user_id', '$time', 0, '$user_id')";

  if (mysqli_query($con, $query)) {
    $new_comment_id = mysqli_insert_id($con);
    logActivity($con, $userid, 'CREATE', 'instant_comments', $new_comment_id, "Added a comment to instant invoice #{$invoice_id}.");
    $msg .= "<div class='alert alert-success'>Comment added successfully.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Error adding comment: " . mysqli_error($con) . "</div>";
  }
}



function removeNegativeYear($date)
{
  return str_replace(', -0001', '', $date);
}

?>
<?php
// ── Invoice summary stats for admin graphs ──────────────────────────────────
$_inv_stats = mysqli_fetch_assoc(mysqli_query($con,
  "SELECT
     COUNT(*) AS total_invoices,
     SUM(invoice_status = '1') AS unpaid_count,
     SUM(invoice_status = '2') AS partial_count,
     SUM(invoice_status = '3') AS paid_count,
     COALESCE(SUM(CASE WHEN invoice_status = '1' THEN total_amount ELSE 0 END), 0) AS unpaid_revenue,
     COALESCE(SUM(CASE WHEN invoice_status = '3' THEN paid_amount  ELSE 0 END), 0) AS paid_revenue,
     COALESCE(SUM(total_amount), 0) AS overall_revenue
   FROM billing_invoice_instant"
));
$_stat_total     = (int)   ($_inv_stats['total_invoices'] ?? 0);
$_stat_unpaid_n  = (int)   ($_inv_stats['unpaid_count']   ?? 0);
$_stat_partial_n = (int)   ($_inv_stats['partial_count']  ?? 0);
$_stat_paid_n    = (int)   ($_inv_stats['paid_count']     ?? 0);
$_stat_unpaid_r  = (float) ($_inv_stats['unpaid_revenue'] ?? 0);
$_stat_paid_r    = (float) ($_inv_stats['paid_revenue']   ?? 0);
$_stat_overall_r = (float) ($_inv_stats['overall_revenue']?? 0);
?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
  <title>SPS - INST INV</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

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

    .hidden {
      display: none;
    }


    .table.datatable tbody tr:hover {
      background-color: #fff9c4 !important;
      transition: background-color .15s ease;
    }

    .invisible-btn {
      visibility: hidden;
      pointer-events: none;
    }

    .billing-tabs-shell {
      background: linear-gradient(135deg, #ffffff 0%, #f7f9fc 100%);
      border: 1px solid #e6ebf2;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
      padding: 18px 18px 0;
    }

    .billing-tabs-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 16px;
    }

    .billing-tabs-title {
      margin: 0;
      font-size: 1.05rem;
      font-weight: 700;
      color: #1f2937;
    }

    #invoiceBillingTabs {
      border: 0;
      gap: 10px;
      margin-bottom: 0 !important;
      flex-wrap: wrap;
    }

    #invoiceBillingTabs .nav-item {
      margin-bottom: 0;
    }

    #invoiceBillingTabs .nav-link {
      border: 1px solid #d7deea;
      border-radius: 999px;
      background: #fff;
      color: #4b5563;
      font-weight: 600;
      padding: 10px 18px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s ease;
    }

    #invoiceBillingTabs .nav-link:hover {
      border-color: #6fd943;
      color: #1f2937;
      box-shadow: 0 6px 18px rgba(111, 217, 67, 0.12);
    }

    #invoiceBillingTabs .nav-link.active {
      background: linear-gradient(135deg, #6fd943 0%, #57bb31 100%);
      border-color: #6fd943;
      color: #fff;
      box-shadow: 0 10px 20px rgba(111, 217, 67, 0.22);
    }

    #invoiceBillingTabContent {
      margin-top: 16px;
    }

    #invoiceBillingTabContent .card {
      border: 1px solid #e8edf5;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    }

    @media (max-width: 767px) {
      .billing-tabs-shell {
        padding: 14px 14px 0;
      }

      .billing-tabs-header {
        flex-direction: column;
        align-items: stretch;
      }

      #invoiceBillingTabs .nav-link {
        width: 100%;
        justify-content: center;
      }
    }
  </style>

  <script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>
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
    <?php if (isset($msg)) {
      echo $msg;
    } ?>
    <div class="dash-content">
      <div class="page-header">
        <div class="page-block">
          <div class="row align-items-center">

            <div class="col-auto">
              <div class="page-header-title">
                <h4 class="m-b-10">Instant Invoices</h4>
              </div>

              <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Instant invoices</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <?php if (($_SESSION['user_type'] ?? '') == '1'): ?>
      <div class="row mb-3">
        <!-- Total Unpaid Invoices -->
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="theme-avtar bg-danger"><i class="ti ti-alert-circle"></i></div>
                <p class="text-muted text-sm mt-3 mb-1">Count</p>
                <h6 class="mb-2">Total Unpaid Invoices</h6>
                <h3 class="mb-0"><?php echo $_stat_unpaid_n; ?></h3>
              </div>
              <div style="width:110px;height:110px;">
                <canvas id="instUnpaidCountChart"></canvas>
              </div>
            </div>
          </div>
        </div>
        <!-- Unpaid Invoice Revenue -->
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="theme-avtar bg-warning"><i class="ti ti-currency-dollar"></i></div>
                <p class="text-muted text-sm mt-3 mb-1">Revenue</p>
                <h6 class="mb-2">Unpaid Revenue</h6>
                <h3 class="mb-0">$<?php echo number_format($_stat_unpaid_r, 0); ?></h3>
              </div>
              <div style="width:110px;height:110px;">
                <canvas id="instUnpaidRevChart"></canvas>
              </div>
            </div>
          </div>
        </div>
        <!-- Paid Invoice Revenue -->
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="theme-avtar bg-success"><i class="ti ti-currency-dollar"></i></div>
                <p class="text-muted text-sm mt-3 mb-1">Revenue</p>
                <h6 class="mb-2">Paid Revenue</h6>
                <h3 class="mb-0">$<?php echo number_format($_stat_paid_r, 0); ?></h3>
              </div>
              <div style="width:110px;height:110px;">
                <canvas id="instPaidRevChart"></canvas>
              </div>
            </div>
          </div>
        </div>
        <!-- Overall Revenue -->
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="theme-avtar bg-primary"><i class="ti ti-chart-bar"></i></div>
                <p class="text-muted text-sm mt-3 mb-1">Total</p>
                <h6 class="mb-2">Overall Revenue</h6>
                <h3 class="mb-0">$<?php echo number_format($_stat_overall_r, 0); ?></h3>
              </div>
              <div style="width:110px;height:110px;">
                <canvas id="instOverallRevChart"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="row mb-3">
        <!-- Total Unpaid Invoices -->
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="theme-avtar bg-danger"><i class="ti ti-alert-circle"></i></div>
                <p class="text-muted text-sm mt-3 mb-1">Count</p>
                <h6 class="mb-2">Total Unpaid Invoices</h6>
                <h3 class="mb-0"><?php echo $_stat_unpaid_n; ?></h3>
              </div>
            </div>
          </div>
        </div>
        <!-- Total Partial Paid Invoices -->
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="theme-avtar bg-warning"><i class="ti ti-clock"></i></div>
                <p class="text-muted text-sm mt-3 mb-1">Count</p>
                <h6 class="mb-2">Partial Paid Invoices</h6>
                <h3 class="mb-0"><?php echo $_stat_partial_n; ?></h3>
              </div>
            </div>
          </div>
        </div>
        <!-- Total Paid Invoices -->
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="theme-avtar bg-success"><i class="ti ti-circle-check"></i></div>
                <p class="text-muted text-sm mt-3 mb-1">Count</p>
                <h6 class="mb-2">Paid Invoices</h6>
                <h3 class="mb-0"><?php echo $_stat_paid_n; ?></h3>
              </div>
            </div>
          </div>
        </div>
        <!-- Total Invoices -->
        <div class="col-lg-3 col-md-6 mb-3">
          <div class="card h-100">
            <div class="card-body d-flex align-items-center justify-content-between">
              <div>
                <div class="theme-avtar bg-primary"><i class="ti ti-file-invoice"></i></div>
                <p class="text-muted text-sm mt-3 mb-1">Count</p>
                <h6 class="mb-2">Total Invoices</h6>
                <h3 class="mb-0"><?php echo $_stat_total; ?></h3>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="row">
        <div class="col-xl-12">
          <div class="row">


            <div class="col-sm-12 col-md-12">
              <div class="billing-tabs-shell">
                <div class="billing-tabs-header">
                  <div>
                    <h5 class="billing-tabs-title">Instant Invoice &amp; Billing</h5>
                  </div>
                </div>
                <ul class="nav nav-tabs" id="invoiceBillingTabs" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link active" id="invoices-tab" data-toggle="tab" href="#invoices-pane" role="tab"
                      aria-controls="invoices-pane" aria-selected="true">
                      <i class="ti ti-file-invoice"></i>
                      <span>Invoices</span>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" id="invoice-comments-tab" data-toggle="tab" href="#invoice-comments-pane"
                      role="tab" aria-controls="invoice-comments-pane" aria-selected="false">
                      <i class="ti ti-message-circle"></i>
                      <span>Invoice Comments</span>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" id="email-history-tab" data-toggle="tab" href="#email-history-pane" role="tab"
                      aria-controls="email-history-pane" aria-selected="false">
                      <i class="ti ti-mail"></i>
                      <span>Email History</span>
                    </a>
                  </li>
                </ul>
                <div class="tab-content" id="invoiceBillingTabContent">
                <div class="tab-pane fade show active" id="invoices-pane" role="tabpanel"
                  aria-labelledby="invoices-tab">
                  <div class="card ">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row">
                    <div class="col-md-9">
                      <h5>Instant Invoices</h5>
                    </div>
                    <div class="col-md-3 d-flex justify-content-end">
                      <!-- <button class="btn btn-primary ms-2" onclick="sendManualSms()">
                        <i class="ti ti-message-circle"></i> Manual SMS
                      </button> -->
                      <!-- <button class="btn btn-primary ms-2" title="Preview History" onclick="pickHistoryPreview()">
                        <i class="ti ti-file-text"></i>
                      </button> -->
                      <button class="btn btn-primary" data-toggle="modal" data-target="#associatedProducts">
                        Add New
                      </button>
                    </div>

                  </div>
                  <hr>

                  <?php
                  $filter_service = isset($_GET['filter_service']) ? trim($_GET['filter_service']) : '';
                  $filter_user    = isset($_GET['filter_user']) ? (int) $_GET['filter_user'] : 0;
                  ?>
                  <div class="row mb-3">
                    <div class="col-md-12">
                      <form method="GET" action="instant_invoice.php" class="row g-3 align-items-end">
                        <?php if (isset($_GET['id'])): ?>
                          <input type="hidden" name="id" value="<?php echo htmlspecialchars($_GET['id']); ?>">
                        <?php endif; ?>
                        <div class="col-md-5">
                          <label class="form-label mb-1">Filter by Service</label>
                          <select name="filter_service" class="form-control filter-select2" style="width:100%;">
                            <option value="">All Services</option>
                            <?php
                            $svc_q = mysqli_query($con, "SELECT DISTINCT TRIM(it.item_description) AS sname
                                                          FROM billing_invoice_items it
                                                          INNER JOIN billing_invoice_instant bi ON bi.invoice_id = it.inv_id
                                                          WHERE TRIM(it.item_description) <> ''
                                                          ORDER BY sname ASC");
                            while ($svc_r = mysqli_fetch_assoc($svc_q)) {
                              $sname = $svc_r['sname'];
                              $sel = ($filter_service !== '' && $filter_service === $sname) ? 'selected' : '';
                              echo '<option value="' . htmlspecialchars($sname, ENT_QUOTES) . '" ' . $sel . '>' . htmlspecialchars($sname) . '</option>';
                            }
                            ?>
                          </select>
                        </div>
                        <div class="col-md-5">
                          <label class="form-label mb-1">Filter by Service Provider</label>
                          <select name="filter_user" class="form-control filter-select2" style="width:100%;">
                            <option value="">All Users</option>
                            <?php
                            $usr_q = mysqli_query($con, "SELECT DISTINCT u.user_id, u.user_name
                                                          FROM users u
                                                          INNER JOIN billing_invoice_instant bi ON bi.service_provider_id = u.user_id
                                                          ORDER BY u.user_name ASC");
                            while ($usr_r = mysqli_fetch_assoc($usr_q)) {
                              $sel = ($filter_user > 0 && $filter_user === (int) $usr_r['user_id']) ? 'selected' : '';
                              echo '<option value="' . (int) $usr_r['user_id'] . '" ' . $sel . '>' . htmlspecialchars($usr_r['user_name']) . '</option>';
                            }
                            ?>
                          </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                          <button type="submit" class="btn btn-primary flex-fill">Apply</button>
                          <?php if ($filter_service !== '' || $filter_user > 0): ?>
                            <a href="instant_invoice.php<?php echo isset($_GET['id']) ? '?id=' . htmlspecialchars($_GET['id']) : ''; ?>" class="btn btn-secondary flex-fill">Clear</a>
                          <?php endif; ?>
                        </div>
                      </form>
                    </div>
                  </div>
                  <div class="row">
                    <div class="dataTable-container">
                      <table class="table datatable">
                        <thead>
                          <tr>
                            <th data-sortable=""><a href="#" class="dataTable-sorter">Invoice</a></th>
                            <th data-sortable=""><a href="#" class="dataTable-sorter">Details</a></th>
                            <th data-sortable=""><a href="#" class="dataTable-sorter">Due
                                Date</a></th>
                            <th data-sortable=""><a href="#" class="dataTable-sorter">Paid
                                Date</a></th>
                            <th data-sortable=""><a href="#" class="dataTable-sorter">Total</a></th>
                            <th data-sortable=""><a href="#" class="dataTable-sorter">Paid</a></th>
                            <th data-sortable=""><a href="#" class="dataTable-sorter">Balance</a></th>
                            <th data-sortable=""><a href="#" class="dataTable-sorter">Status</a></th>

                            <th width="250px" data-sortable="" style="width: 8.78764%;"><a href="#"
                                class="dataTable-sorter">Action</a></th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          $invoice_where = [];
                          if ($filter_service !== '') {
                            $safe_svc = mysqli_real_escape_string($con, $filter_service);
                            $invoice_where[] = "invoice_id IN (SELECT DISTINCT inv_id FROM billing_invoice_items WHERE TRIM(item_description) = '$safe_svc')";
                          }
                          if ($filter_user > 0) {
                            $invoice_where[] = "service_provider_id = '" . $filter_user . "'";
                          }
                          $invoice_where_sql = empty($invoice_where) ? '' : ' WHERE ' . implode(' AND ', $invoice_where);
                          $querr1 = mysqli_query($con, "SELECT * FROM billing_invoice_instant $invoice_where_sql order by invoice_id desc");

                          // === Hoisted lookups: fetch once, reuse inside the loop ===
                          $_invoiceStatuses = [];
                          $_resStatuses = mysqli_query($con, "SELECT status_id, status_text FROM billing_invoice_status");
                          while ($_r = mysqli_fetch_assoc($_resStatuses)) {
                            $_invoiceStatuses[(int)$_r['status_id']] = $_r['status_text'];
                          }

                          $_allUsers = [];
                          $_resUsers = mysqli_query($con, "SELECT user_id, user_name FROM users ORDER BY user_name ASC");
                          while ($_r = mysqli_fetch_assoc($_resUsers)) {
                            $_allUsers[(int)$_r['user_id']] = $_r['user_name'];
                          }

                          $_quickPhrases = [];
                          $_uidForPhrases = (int) ($_SESSION['user_id'] ?? 0);
                          if ($_uidForPhrases > 0) {
                            $_resPhrases = mysqli_query($con, "SELECT phrase FROM user_quick_phrases WHERE user_id=$_uidForPhrases ORDER BY id");
                            while ($_r = mysqli_fetch_assoc($_resPhrases)) {
                              $_quickPhrases[] = $_r['phrase'];
                            }
                          }
                          // === /hoisted lookups ===

                          $c = 1;
                          while ($Podrow = mysqli_fetch_array($querr1)) {
                            ?>
                            <tr>
                              <td><?php echo $Podrow["invoice_id"]; ?></td>
                              <td>
                                <?php if (!empty($Podrow["name"])) { ?>
                                  <div style="font-weight:500;"><?php echo 'Name: ' . $Podrow["name"]; ?></div>
                                <?php } ?>

                                <?php if (!empty($Podrow["business_name"])) { ?>
                                  <div style="font-size:12px; color:#666;"><?php echo 'Business: ' . $Podrow["business_name"]; ?>
                                  </div>
                                <?php } ?>

                                <?php if (!empty($Podrow["ssn"])) { ?>
                                  <div style="font-size:12px; color:#666;"><?php echo 'SSN: ' . $Podrow["ssn"]; ?></div>
                                <?php } ?>

                                <?php if (!empty($Podrow["email"])) { ?>
                                  <div style="font-size:12px; color:#666;">
                                    Email: <a href="mailto:<?php echo $Podrow['email']; ?>">
                                      <?php echo $Podrow['email']; ?>
                                    </a>
                                  </div>
                                <?php } ?>

                                <?php if (!empty($Podrow["phone"])) { ?>
                                  <div style="font-size:12px; color:#666;"><?php echo 'Phone: ' . $Podrow["phone"]; ?>
                                  </div>
                                <?php } ?>

                                <?php if (!empty($Podrow["address"])) { ?>
                                  <div style="font-size:12px; color:#999;"><?php echo 'Address: ' . $Podrow["address"]; ?>
                                  </div>
                                <?php } ?>
                              </td>


                              
                              <td>
                                <?php $OSdate = $Podrow["due_date"]; ?>
                                <?php
                                $thisdate = date("m/d/y", strtotime($OSdate));
                                echo $processedDate1 = removeNegativeYear($thisdate);
                                ?>
                              </td>
                              <td>
                                <?php
                                $OSdate = $Podrow["paid_date"];
                                if ($OSdate == '0000-00-00' || empty($OSdate)) {
                                  echo "N/A";
                                } else {
                                  $thisdate = date("m/d/y", strtotime($OSdate));
                                  echo $processedDate1 = removeNegativeYear($thisdate);
                                }
                                ?>
                              </td>

                              <td><?php echo $Podrow["total_amount"]; ?></td>
                              <td><?php echo $Podrow["paid_amount"]; ?>
                              </td>
                              <td>
                                Balance: <?php echo $Podrow["balance"]; ?><br>
                                Discount: <?php echo $Podrow["discount"]; ?>
                              </td>

                              </td>
                              <td>
                                <?php
                                $invoice_status = (int) $Podrow["invoice_status"];
                                $status_value = $_invoiceStatuses[$invoice_status] ?? '';
                                ?>
                                <?php if ($status_value == 'Not Paid') { ?>
                                  <span class="badge badge-pill badge-danger">UnPaid</span>
                                <?php } elseif ($status_value == 'Partial Paid') { ?>
                                  <span class="badge badge-pill badge-warning">Partial
                                    Paid</span>
                                <?php } elseif ($status_value == 'Paid') { ?>
                                  <span class="badge badge-pill badge-success">Paid</span>
                                <?php } ?>

                              </td>


                              <td>

                                <div class="d-flex justify-content-between mb-1">
                                  <a href="instant_invoices/<?php echo $Podrow['filelink']; ?>"
                                    class="btn btn-sm btn-primary tooltip1 view-file-preview"
                                    data-file="<?php echo htmlspecialchars($Podrow['filelink']); ?>" target="_blank">
                                    <i class="ti ti-eye text-white"></i>
                                    <span class="tooltiptext1">View Invoice</span>
                                  </a>

                                  <!-- Payment Button (icon only with tooltip) -->
                                  <button class="btn btn-sm btn-warning" data-bs-toggle="modal"
                                    data-bs-target="#addpayment<?php echo $Podrow['invoice_id']; ?>"
                                    data-bs-toggle="tooltip1" data-bs-placement="top" title="Add Payment">
                                    <i class="ti ti-cash"></i>
                                  </button>

                                  <!-- Payment History Button (icon only with tooltip) -->
                                  <button class="btn btn-sm btn-success" id="<?php echo $Podrow['invoice_id']; ?>"
                                    onclick="gethistory(<?php echo $Podrow['invoice_id']; ?>)" data-bs-toggle="tooltip1"
                                    data-bs-placement="top" title="Payment History">
                                    <i class="ti ti-history"></i>
                                  </button>


                                  <!-- Edit Button (icon only with tooltip) -->
                                  <button class="btn btn-sm btn-info" data-bs-toggle="modal"
                                    data-bs-target="#editInvoiceModal<?php echo $Podrow['invoice_id']; ?>"
                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Invoice">
                                    <i class="ti ti-pencil text-white"></i>
                                  </button>

                                </div>

                                <div class="d-flex justify-content-between">
                                  <button class="btn btn-sm btn-warning tooltip1"
                                    onclick="sendReminder(<?php echo $Podrow['invoice_id']; ?>)">
                                    <i class="ti ti-mail text-white"></i>
                                    <span class="tooltiptext1">Send Reminder</span>
                                  </button>
                                  <!-- =====  NEW WhatsApp button  ===== -->
                                  <button class="btn btn-sm btn-success tooltip1"
                                    onclick="sendWhatsAppReminder(<?php echo $Podrow['invoice_id']; ?>)">
                                    <!-- Tabler "brand-whatsapp" icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                      fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                      stroke-linejoin="round" class="ti ti-brand-whatsapp">
                                      <path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9L3 21" />
                                      <path
                                        d="M9 10a.5 .5 0 0 0 1 0V9a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1v-1a.5 .5 0 0 0 -1 0v1a.5 .5 0 0 0 1 0V15" />
                                    </svg>
                                    <span class="tooltiptext1">SMS Reminder</span>
                                  </button>
                                  <!-- Tabler Button with Tooltip -->
                                  <button type="button" class="btn btn-icon btn-sm btn-success"
                                    data-bs-toggle="modal"
                                    data-bs-target="#addCommentModal<?php echo $Podrow['invoice_id']; ?>"
                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Add Comment">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                      viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                      stroke-linecap="round" stroke-linejoin="round">
                                      <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                      <path d="M3 20l1.5 -1.5" />
                                      <path d="M4 17h16a2 2 0 0 0 2 -2v-10a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v12" />
                                      <path d="M8 9h8" />
                                      <path d="M8 13h4" />
                                    </svg>
                                  </button>
                                  <button class="btn btn-sm btn-custom tooltip1" data-toggle="modal"
                                    data-target="#deleteclient<?php echo $Podrow['invoice_id']; ?>"><i
                                      class="ti ti-trash text-white"></i><span class="tooltiptext1">Delete
                                      Payment</span></button>
                                </div>
                                <!-- strip buttons previously added here  -->
                                      


                                      <!-- buttons end -->

                              </td>


                            </tr>
                            <input type="hidden" id="invoicehistorydata">

                            <div class="modal fade" id="editInvoiceModal<?php echo $Podrow['invoice_id']; ?>">
                              <div class="modal-dialog modal-xl">
                                <form method="POST">
                                  <div class="modal-content">
                                    <div class="modal-header">
                                      <h4 class="modal-title">Edit Invoice</h4>
                                    </div>

                                    <div class="modal-body">
                                      <input type="hidden" name="invoice_id"
                                        value="<?php echo $Podrow['invoice_id']; ?>" />


                                      <!-- Customer Info Section -->
                                      <div class="row g-3 mb-4 pb-3 border-bottom bg-light rounded p-3">
                                        <div class="col-md-4">
                                          <label class="form-label fw-bold">Name <span
                                              class="text-danger">*</span></label>
                                          <input type="text" name="edit_name" class="form-control" required
                                            value="<?php echo htmlspecialchars($Podrow['name']); ?>">
                                        </div>

                                        <div class="col-md-4">
                                          <label class="form-label">Phone</label>
                                          <input type="text" name="edit_phone" class="form-control" id="inst_phone"
                                            value="<?php echo htmlspecialchars($Podrow['phone']); ?>">
                                        </div>

                                        <div class="col-md-4">
                                          <label class="form-label">Email</label>
                                          <input type="email" name="edit_email" class="form-control"
                                            value="<?php echo htmlspecialchars($Podrow['email']); ?>">
                                        </div>

                                        <div class="col-md-6">
                                          <label class="form-label">Business Name</label>
                                          <input type="text" name="edit_business_name" class="form-control"
                                            value="<?php echo htmlspecialchars($Podrow['business_name'] ?? ''); ?>">
                                        </div>

                                        <div class="col-md-6">
                                          <label class="form-label">SSN</label>
                                          <input type="text" name="edit_ssn" class="form-control"
                                            value="<?php echo htmlspecialchars($Podrow['ssn'] ?? ''); ?>"
                                            placeholder="123-45-6789">
                                        </div>

                                        <div class="col-12">
                                          <label class="form-label">Address</label>
                                          <input type="text" name="edit_address" class="form-control"
                                            value="<?php echo htmlspecialchars($Podrow['address']); ?>">
                                        </div>

                                        <div class="col-md-4">
                                          <label class="form-label">City</label>
                                          <input type="text" name="edit_city" class="form-control"
                                            value="<?php echo htmlspecialchars($Podrow['city']); ?>">
                                        </div>

                                        <div class="col-md-4">
                                          <label class="form-label">State / Province</label>
                                          <input type="text" name="edit_state" class="form-control"
                                            value="<?php echo htmlspecialchars($Podrow['state']); ?>">
                                        </div>

                                        <div class="col-md-4">
                                          <label class="form-label">ZIP / Postal Code</label>
                                          <input type="text" name="edit_zip" class="form-control"
                                            value="<?php echo htmlspecialchars($Podrow['zip']); ?>">
                                        </div>
                                      </div>

                                      <div class="row mb-2">
                                        <div class="col-md-6">
                                          <label class="form-label">Due Date</label>
                                          <input type="date" class="form-control" name="edit_due_date"
                                            value="<?php echo $Podrow['due_date']; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">Paid Date</label>
                                          <input type="date" class="form-control" name="edit_paid_date"
                                            value="<?php echo $Podrow['paid_date'] !== '0000-00-00' ? $Podrow['paid_date'] : ''; ?>">
                                        </div>
                                      </div>

                                      <div class="row mb-2">
                                        <div class="col-md-6">
                                          <label class="form-label">Total Amount</label>
                                          <input type="number" step="0.01" class="form-control" name="edit_total_amount"
                                            id="edit_total_amount" value="<?php echo $Podrow['total_amount']; ?>"
                                            required>
                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">Paid Amount</label>
                                          <input type="number" step="0.01" class="form-control" name="edit_paid_amount"
                                            id="edit_paid_amount" value="<?php echo $Podrow['paid_amount']; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                          <label class="form-label">Balance</label>
                                          <input type="number" step="0.01" class="form-control" name="edit_balance"
                                            id="edit_balance" value="<?php echo $Podrow['balance']; ?>" required>
                                        </div>
                                        <div class="col-md-6 mt-2">
                                          <label class="form-label">Discount</label>
                                          <input type="number" step="0.01" name="edit_discount" id="edit_discount"
                                            class="form-control" value="<?php echo $Podrow['discount']; ?>" required>
                                        </div>

                                        <div class="col-md-12 mt-2">
                                          <label class="form-label">Service Provider</label>
                                          <select name="edit_service_provider" class="form-control" required>
                                            <option value="">Select Service Provider</option>
                                            <?php
                                            foreach ($_allUsers as $_uid => $_uname) {
                                              $sel = ($_uid == $Podrow['service_provider_id']) ? 'selected' : '';
                                              echo '<option value="' . $_uid . '" ' . $sel . '>' . htmlspecialchars($_uname) . '</option>';
                                            }
                                            ?>
                                          </select>
                                        </div>

                                        <div class="col-md-12 mt-2">
                                          <label class="form-label">Description</label>
                                          <input class="form-control" name="description" type="text"
                                            value="<?php echo htmlspecialchars($Podrow['payment_details']); ?>">
                                        </div>
                                      </div>
                                    </div>

                                    <div class="modal-footer">
                                      <button type="button" class="btn btn-info" data-bs-dismiss="modal">Close</button>
                                      <button type="submit" class="btn btn-primary" name="update_invoice">Save
                                        Changes</button>
                                    </div>
                                  </div>
                                </form>
                              </div>
                            </div>
                            <!-- ======================  /EDIT-INVOICE MODAL  ====================== -->
                            <!-- per-row scripts removed; consolidated below the loop -->





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
                                          <div class="swal2-icon swal2-warning swal2-icon-show" style="display: flex;">
                                            <div class="swal2-icon-content">!
                                            </div>
                                          </div>
                                          <h2 class="swal2-title" id="swal2-title" style="display: block;">Are you
                                            sure?</h2>
                                          <div class="swal2-html-container" id="swal2-html-container"
                                            style="display: block;">This action
                                            can not be undone. Do you want to
                                            continue?</div>
                                        </div>
                                      </div>
                                    </div>

                                    <!-- Modal footer -->
                                    <div class="modal-footer" style="justify-content: center;">
                                      <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                                      <button type="submit" class="btn btn-primary" name="delete_invoice">Yes
                                        Delete</button>
                                    </div>

                                  </div>
                                </form>
                              </div>
                            </div>
                            <!-- Add Comment Modal  -->
                            <!-- Add Comment Modal  -->
                            <div class="modal fade" id="addCommentModal<?php echo $Podrow['invoice_id']; ?>">
                              <div class="modal-dialog modal-lg">
                                <form method="POST">
                                  <div class="modal-content">
                                    <div class="modal-header">
                                      <h4 class="modal-title">Add Comment</h4>
                                    </div>
                                    <div class="modal-body">
                                      <input type="hidden" name="invoice_id" value="<?php echo $Podrow['invoice_id']; ?>">

                                      <div class="row mb-2">
                                        <div class="col-md-12">

                                          <!-- quick phrases -->
                                          <label class="form-label">Quick phrases</label>
                                          <div class="mb-2">
                                            <?php
                                            foreach ($_quickPhrases as $_phrase) {
                                              echo '<button type="button" class="btn btn-sm btn-outline-primary me-1 mb-1"
                          onclick="addPhrase(\'' . htmlspecialchars($_phrase) . '\')">'
                                                . htmlspecialchars($_phrase) . '</button>';
                                            }
                                            ?>
                                            <a href="quickphrases.php" class="btn btn-primary btn-sm">Edit Phrases</a>
                                          </div>


                                          <label for="invoice_comment" class="form-label">Comment (edit or type)</label>
                                          <textarea class="ckeditor" name="invoice_comment"
                                            id="invoice_comment<?php echo $Podrow['invoice_id']; ?>"></textarea>

                                        </div>
                                      </div>
                                    </div>

                                    <div class="modal-footer">
                                      <button type="button" class="btn btn-info" data-bs-dismiss="modal">Close</button>
                                      <button type="submit" class="btn btn-primary"
                                        name="add_invoice_comment">Submit</button>
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
                                      <h4 class="modal-title">Add Payment For This
                                        Invoice</h4>
                                    </div>

                                    <!-- Modal body -->
                                    <div class="modal-body">
                                      <div class="row mb-2">
                                        <input type="hidden" name="invoice_id"
                                          value="<?php echo $Podrow['invoice_id']; ?>" />
                                        <input type="hidden" name="paidamount"
                                          value="<?php echo $Podrow['paid_amount']; ?>" />
                                        <input type="hidden" name="memberid" value="<?php echo $Podrow['mem_id']; ?>" />
                                        <div class="col-md-6 mt-2">
                                          <label for="duedate" class="form-label">Date</label>
                                          <input class="form-control" type="date" name="duedate"
                                            value="<?php echo $Podrow['due_date']; ?>" />
                                        </div>
                                        <div class="col-md-6 mt-2">
                                          <label for="paid_date" class="form-label">Paid Date</label>
                                          <input type="date" class="form-control" name="paid_date"
                                            value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-6 mt-2">
                                          <label for="after_discount" class="form-label">Discount</label>
                                          <input type="text" id='after_discount' name='after_discount'
                                            class='form-control' value='<?php echo $Podrow["discount"]; ?>'>
                                          <input type="hidden" id='old_discount' name='old_discount' class='form-control'
                                            value='<?php echo $Podrow["discount"]; ?>'>
                                        </div>
                                        <div class="col-md-6 mt-2">
                                          <label for="employee_initials" class="form-label">Employee
                                            Initials</label>
                                          <input class="form-control" id="employee_initials" name="employee_initials"
                                            name="text" value="<?php $name = explode(' ', $_SESSION['fullname']);
                                            if (count($name) > 1) {


                                              if ($name[0] != '') {
                                                echo substr($name[0], 0, 1);
                                              }
                                              if ($name[1] != '') {
                                                echo substr($name[1], 0, 1);
                                              }
                                            } else {
                                              if ($name[0] != '') {
                                                echo substr($name[0], 0, 1);
                                              }
                                            }
                                            ?>" />
                                        </div>
                                        <div class="col-md-6 mt-2">
                                          <label for="startdate" class="form-label">Total
                                            Amount</label>
                                          <input class="form-control" required="required" name="totalamount" type="text"
                                            value="<?php echo $Podrow['total_amount']; ?>">
                                        </div>
                                        <div class="col-md-6 mt-2">
                                          <label for="expirydate" class="form-label">Balance</label>
                                          <input class="form-control" required="required" name="balance" type="text"
                                            value="<?php echo $Podrow['balance']; ?>">
                                        </div>

                                        <div class="col-md-12 mt-2">
                                          <label class="form-label">Payment Type(s)</label>
                                          <div id="paymentTypeContainer<?php echo $Podrow['invoice_id']; ?>">
                                            <div class="payment-type-wrapper mb-3">
                                              <select class="form-select payment-select"
                                                onchange="handlePaymentSelectChange(this, '<?php echo $Podrow['invoice_id']; ?>')">
                                                <option value="">-- Select Payment Type --</option>
                                                <option value="Cash">Cash</option>
                                                <option value="Check">Check</option>
                                                <option value="Card">Card</option>
                                                <option value="ACH">ACH</option>
                                                <option value="Zelle">Zelle</option>
                                                <option value="Other">Other</option>
                                              </select>
                                              <input type="text" class="form-control payment-other-input mt-2"
                                                placeholder="Enter Payment Type" style="display: none;">

                                            </div>
                                          </div>



                                          <input type="hidden" name="payment_type"
                                            id="paymentTypeHidden<?php echo $Podrow['invoice_id']; ?>">
                                        </div>

                                        <!-- payment-type script removed; consolidated below the loop -->








                                        <div class="col-md-12 mt-2">
                                          <label for="service_provider" class="form-label">Service Provider</label>
                                          <?php
                                          $service_provider_id = (int) $Podrow['service_provider_id'];
                                          $sp_name = $_allUsers[$service_provider_id] ?? '';
                                          ?>
                                          <input type="text" class="form-control" value="<?php echo htmlspecialchars($sp_name); ?>"
                                            readonly>
                                        </div>
                                        <div class="col-md-12 mt-2">
                                          <label for="expirydate" class="form-label">Amount Receivable </label>
                                          <input class="form-control" required="required" name="recievedamount"
                                            type="text">
                                        </div>
                                        <div class="col-md-12 mt-2">
                                          <label for="description" class="form-label">Description</label>
                                          <input class="form-control" required="required" id="description"
                                            name="description" type="text" value="">
                                        </div>
                                      </div>
                                    </div>

                                    <!-- Modal footer -->
                                    <div class="modal-footer">
                                      <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                                      <button type="submit" class="btn btn-primary" name="addpayment">Submit</button>
                                    </div>

                                  </div>
                                </form>
                              </div>
                            </div>




                            <?php $c++;
                          } ?>
                        </tbody>
                      </table>
                      <!-- ===== Consolidated invoice-row scripts (moved out of loop) ===== -->
                      <script>
                        /* Service-checkbox listener (delegated, runs once for all edit modals) */
                        $(function () {
                          $('.service-chk:checked').each(function () { triggerServiceLoad($(this).val()); });
                          $(document).on('click', '.service-chk', function () {
                            var id = $(this).val();
                            if ($(this).is(':checked')) triggerServiceLoad(id);
                            else $('#category-dropdown' + id).empty();
                          });

                          /* Balance auto-calc on edit modals (delegated by name attribute) */
                          $(document).on('input',
                            '[name="edit_total_amount"], [name="edit_paid_amount"], [name="edit_discount"]',
                            function () {
                              var $form = $(this).closest('form');
                              var t = parseFloat($form.find('[name="edit_total_amount"]').val()) || 0;
                              var p = parseFloat($form.find('[name="edit_paid_amount"]').val()) || 0;
                              var d = parseFloat($form.find('[name="edit_discount"]').val()) || 0;
                              $form.find('[name="edit_balance"]').val(((t - d) - p).toFixed(2));
                            }
                          );

                          /* Add-payment form submit: refresh hidden field before submit */
                          $(document).on('submit', '.modal[id^="addpayment"] form', function () {
                            var modalId = $(this).closest('.modal').attr('id');
                            var invoiceId = modalId.replace('addpayment', '');
                            updatePaymentTypesHiddenField(invoiceId);
                          });
                        });

                        function triggerServiceLoad(mainId) {
                          $.ajax({
                            url: "add-another-service.php",
                            type: "POST",
                            data: {
                              main_id: mainId,
                              sub_total: $('#edit_total_amount').val(),
                              discount: $('#edit_discount').val(),
                              grand_total: $('#edit_balance').val()
                            },
                            success: function (html) { $('#category-dropdown' + mainId).html(html); }
                          });
                        }

                        /* Payment-type helpers (single global definition) */
                        function handlePaymentSelectChange(selectElem, invoiceId) {
                          var wrapper = selectElem.closest('.payment-type-wrapper');
                          var otherInput = wrapper.querySelector('.payment-other-input');
                          otherInput.style.display = (selectElem.value === "Other") ? "block" : "none";
                          updatePaymentTypesHiddenField(invoiceId);
                        }
                        function addPaymentTypeField(invoiceId) {
                          var container = document.getElementById('paymentTypeContainer' + invoiceId);
                          var wrapper = document.createElement("div");
                          wrapper.className = "payment-type-wrapper mb-3";
                          var select = document.createElement("select");
                          select.className = "form-select payment-select";
                          select.innerHTML =
                            '<option value="">-- Select Payment Type --</option>' +
                            '<option value="Cash">Cash</option>' +
                            '<option value="Check">Check</option>' +
                            '<option value="Card">Card</option>' +
                            '<option value="ACH">ACH</option>' +
                            '<option value="Zelle">Zelle</option>' +
                            '<option value="Other">Other</option>';
                          select.onchange = function () { handlePaymentSelectChange(this, invoiceId); };
                          var otherInput = document.createElement("input");
                          otherInput.type = "text";
                          otherInput.className = "form-control payment-other-input mt-2";
                          otherInput.placeholder = "Enter Payment Type";
                          otherInput.style.display = "none";
                          otherInput.oninput = function () { updatePaymentTypesHiddenField(invoiceId); };
                          var removeBtn = document.createElement("button");
                          removeBtn.type = "button";
                          removeBtn.className = "btn btn-danger mt-2";
                          removeBtn.textContent = "Remove";
                          removeBtn.onclick = function () {
                            wrapper.remove();
                            updatePaymentTypesHiddenField(invoiceId);
                          };
                          wrapper.appendChild(select);
                          wrapper.appendChild(otherInput);
                          wrapper.appendChild(removeBtn);
                          container.appendChild(wrapper);
                        }
                        function removePaymentTypeField(button, invoiceId) {
                          button.closest('.payment-type-wrapper').remove();
                          updatePaymentTypesHiddenField(invoiceId);
                        }
                        function updatePaymentTypesHiddenField(invoiceId) {
                          var wrappers = document.querySelectorAll('#paymentTypeContainer' + invoiceId + ' .payment-type-wrapper');
                          var values = Array.from(wrappers).map(function (wrapper) {
                            var select = wrapper.querySelector(".payment-select");
                            var otherInput = wrapper.querySelector(".payment-other-input");
                            return select.value === "Other"
                              ? (otherInput.value.trim() || "Other")
                              : select.value;
                          }).filter(function (v) { return v !== ""; });
                          var hidden = document.getElementById('paymentTypeHidden' + invoiceId);
                          if (hidden) hidden.value = JSON.stringify(values);
                        }
                      </script>
                    </div>
                  </div>
                </div>
              </div>

              <div id="invoicehistory" class="modal fade" role="dialog">
                <div class="modal-dialog modal-lg modal-dialog-scrollable"> <!-- scrollable for vertical overflow -->
                  <div class="modal-content">
                    <div class="modal-header">
                      <h4 class="modal-title">Invoice History</h4>
                    </div>

                    <div class="modal-body">
                      <div class="table-responsive"> <!-- enables horizontal scroll for wide tables -->
                        <table class="table table-bordered table-striped" id="invoiceTable">
                          <thead>
                            <tr>
                              <th>Paid Date</th>
                              <th>Payment Details</th>
                              <th>Paid Amount</th>
                              <th>Balance</th>
                              <th>Payment Added By</th>
                              <th>Payment Method</th>
                            </tr>
                          </thead>
                          <tbody id="invoiceData">
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <div class="modal-footer">
                      <button type="button" class="btn btn-primary" id="btnDownloadHistory">
                        <i class="ti ti-file"></i> Download PDF
                      </button>
                      <button type="button" class="btn btn-info" onclick="closeModal()">Close</button>
                    </div>
                  </div>
                </div>
              </div>



              <!-- The Modal -->
              <div class="modal fade" id="associatedProducts">
                <div class="modal-dialog modal-dialog modal-xl">
                  <?php
                  $pendingInvoiceServices = (isset($instantInvoicePendingData['service']) && is_array($instantInvoicePendingData['service'])) ? $instantInvoicePendingData['service'] : [];
                  $pendingInvoiceDescriptions = (isset($instantInvoicePendingData['description']) && is_array($instantInvoicePendingData['description'])) ? $instantInvoicePendingData['description'] : [];
                  $pendingInvoicePrices = (isset($instantInvoicePendingData['unit_price']) && is_array($instantInvoicePendingData['unit_price'])) ? $instantInvoicePendingData['unit_price'] : [];
                  ?>
                  <form method="POST">
                    <div class="modal-content">

                      <!-- Modal Header -->
                      <div class="modal-header">
                        <h4 class="modal-title">Add Instant Invoice</h4>
                      </div>

                      <!-- Modal body -->
                      <div class="modal-body">
                        <input type="hidden" name="bypass_client_match" id="bypass_client_match"
                          value="<?php echo !empty($instantInvoicePendingData['bypass_client_match']) ? '1' : '0'; ?>">

                        <?php if (!empty($instantInvoiceMatches)) {
                          echo renderInstantInvoiceMatchCards($instantInvoiceMatches);
                        } ?>

                        <div class="row g-3 mb-4 pb-3 border-bottom bg-light rounded p-3">
                          <div class="col-md-4">
                            <label class="form-label fw-bold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="inst_name" class="form-control" required
                              placeholder="Client or Business name"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['inst_name'] ?? ''); ?>">
                          </div>

                          <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="inst_phone" id="inst_phone" class="form-control"
                              placeholder="0300-1234567"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['inst_phone'] ?? ''); ?>">
                          </div>

                          <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="inst_email" class="form-control" placeholder="client@example.com"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['inst_email'] ?? ''); ?>">
                          </div>

                          <div class="col-md-6">
                            <label class="form-label">Business Name</label>
                            <input type="text" name="inst_business_name" class="form-control"
                              placeholder="Business name (if any)"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['inst_business_name'] ?? ''); ?>">
                          </div>

                          <div class="col-md-6">
                            <label class="form-label">SSN</label>
                            <input type="text" name="inst_ssn" class="form-control" placeholder="123-45-6789"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['inst_ssn'] ?? ''); ?>">
                          </div>

                          <div class="col-12">
                            <label class="form-label">Address</label>
                            <input type="text" name="inst_address" class="form-control"
                              placeholder="House #, Street name"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['inst_address'] ?? ''); ?>">
                          </div>

                          <div class="col-md-4">
                            <label class="form-label">City</label>  
                            <input type="text" name="inst_city" class="form-control" placeholder="Lahore"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['inst_city'] ?? ''); ?>">
                          </div>

                          <div class="col-md-4">
                            <label class="form-label">State / Province</label>
                            <input type="text" name="inst_state" class="form-control" placeholder="Punjab"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['inst_state'] ?? ''); ?>">
                          </div>

                          <div class="col-md-4">
                            <label class="form-label">ZIP / Postal Code</label>
                            <input type="text" name="inst_zip" class="form-control" placeholder="54000"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['inst_zip'] ?? ''); ?>">
                          </div>

                          <div class="col-md-12">
                            <label for="invoice_status" class="form-label">Payment Status</label>
                            <select class="form-control" name="invoice_status" id="invoice_status" required>
                              <option value="1" <?php echo (($instantInvoicePendingData['invoice_status'] ?? '1') == '1') ? 'selected' : ''; ?>>Unpaid</option>
                              <option value="2" <?php echo (($instantInvoicePendingData['invoice_status'] ?? '') == '2') ? 'selected' : ''; ?>>Partial Paid</option>
                              <option value="3" <?php echo (($instantInvoicePendingData['invoice_status'] ?? '') == '3') ? 'selected' : ''; ?>>Paid</option>
                            </select>
                          </div>
                          <div class="col-md-12" id="paid_date_wrapper" style="display: none;">
                            <label for="paid_date" class="form-label">Paid Date <span
                                class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="paid_date" id="paid_date"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['paid_date'] ?? date('Y-m-d')); ?>">
                          </div>

                          <script>
                            document.getElementById('invoice_status').addEventListener('change', function () {
                              const wrapper = document.getElementById('paid_date_wrapper');
                              const paidDateInput = document.getElementById('paid_date');

                              if (this.value == '2' || this.value == '3') { // Partial Paid or Paid
                                wrapper.style.display = 'block';
                                paidDateInput.required = true;
                              } else { // Unpaid
                                wrapper.style.display = 'none';
                                paidDateInput.required = false;
                                paidDateInput.value = '';
                              }
                            });
                            // Trigger on page load to set initial state
                            document.getElementById('invoice_status').dispatchEvent(new Event('change'));
                          </script>

                        </div>
                        <?php
                        $queryy = mysqli_query($con, "SELECT * FROM memberships");
                        $c = 1;
                        while ($services1 = mysqli_fetch_array($queryy)) {
                          ?>
                          <div class="form-group mt-2" id="category-dropdown<?php echo $services1['membership_id']; ?>">

                          </div>

                        <?php } ?>

                        <?php echo renderPendingInstantInvoiceItems($pendingInvoiceServices, $pendingInvoiceDescriptions, $pendingInvoicePrices); ?>


                        <div id="myDIV" style="display:block;">
                          <!-- ===== Searchable service dropdown (INVOICE MODAL) ===== -->
                          <div class="col-md-12 mt-2">
                            <label for="servicesDropdown" class="form-label">Select Services</label>

                            <div class="dropdown w-100">
                              <button class="btn btn-outline-primary dropdown-toggle w-100 text-start" type="button"
                                id="servicesDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                Select Services
                              </button>

                              <div class="dropdown-menu p-3 w-100" aria-labelledby="servicesDropdown"
                                style="max-height: 300px; overflow-y: auto; min-width: 100%;">

                                <!-- live-search box -->
                                <input type="text" class="form-control mb-2" id="serviceSearchBox"
                                  placeholder="Type to search services…" autocomplete="off">

                                <!-- services list -->
                                <div id="serviceList">
                                  <?php
                                  $query = mysqli_query($con, "SELECT * FROM memberships ORDER BY membership_name ASC");
                                  while ($services = mysqli_fetch_array($query)) {
                                    $mid = $services['membership_id'];
                                    ?>
                                    <div class="form-check mb-2 service-item">
                                      <input type="hidden" name="main-catt" id="main-catt<?php echo $mid; ?>"
                                        value="<?php echo $mid; ?>">
                                      <input type="checkbox" class="form-check-input" id="addservice<?php echo $mid; ?>"
                                        name="vehicle2" value="<?php echo $mid; ?>">
                                      <label class="form-check-label" for="addservice<?php echo $mid; ?>"
                                        style="font-size: 14px;">
                                        <?php echo htmlspecialchars($services['membership_name']); ?>
                                      </label>
                                    </div>
                                  <?php } ?>
                                </div><!-- /#serviceList -->
                              </div><!-- /.dropdown-menu -->
                            </div><!-- /.dropdown -->
                          </div>



                        </div>
                        <p style="color: #428bca;cursor: pointer;" onclick="myFunction()"><b><i class="fa fa-plus"
                              aria-hidden="true"></i> Add another
                            service</b></p>
                        <script>
                          function closeModal() {
                            $('#invoicehistory').modal('hide');
                          }

                          function gethistory(id) {
                            var invoice_id = id;
                            $.ajax({
                              url: 'invoicehistory.php',
                              type: 'GET',
                              data: { invoice_id: invoice_id },
                              success: function (response) {
                                var data = JSON.parse(response);
                                $('#invoiceData').empty();
                                data.forEach(function (item) {
                                  var row = "<tr>";
                                  row += "<td>" + new Date(item.date).toLocaleDateString("en-US") + "</td>";
                                  row += "<td>" + item.detail + "</td>";
                                  row += "<td>" + item.paid + "</td>";
                                  row += "<td>" + item.balance + "</td>";
                                  row += "<td>" + item.employee + "</td>";
                                  row += "<td>" + (item.payment_method || 'N/A') + "</td>"; // ✅
                                  row += "</tr>";
                                  $('#invoiceData').append(row);
                                });
                                $('#invoicehistory').data('invoice-id', id);
                                $('#invoicehistory').modal('show');
                              }
                            });
                          }

                          $(document).ready(function () {
                            $('input[type=checkbox]').on("click", function () {
                              var id = $(this).attr('id').replace('addservice', '');
                              if ($(this).is(":checked")) {
                                var main_id = $('#main-catt' + id).val();
                                var sub_total = $('#sub_total').val();
                                var discount = $('#discount').val();
                                var grand_total = $('#grand_total').val();
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
                                  success: function (result) {
                                    $("#category-dropdown" + id).html(result);
                                  }
                                });
                              } else {
                                $("#category-dropdown" + id).empty();
                              }
                            });
                          });
                        </script>

                        <div class="row mb-2">
                          <div class="col-md-12">
                            <label for="" class="form-label">Service Provider</label>
                            <select id="Service_Provider" name="Service_Provider" class="form-control select2" required>
                              <option value="">Select Service Provider</option>
                              <?php
                              $query = "SELECT user_id, user_name FROM users order by user_name ASC";
                              $result = mysqli_query($con, $query);

                              while ($user = mysqli_fetch_assoc($result)) {
                                $selectedProvider = (($instantInvoicePendingData['Service_Provider'] ?? '') == $user['user_id']) ? ' selected' : '';
                                echo '<option value="' . htmlspecialchars($user['user_id']) . '"' . $selectedProvider . '>' . htmlspecialchars($user['user_name']) . '</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="row mb-2">
                          <div class="col-md-12">
                            <label for="billing_cycle" class="form-label">DUE DATE</label>
                            <input type="date" class="form-control" required id="billing_cycle" name="billing_cycle"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['billing_cycle'] ?? ''); ?>">
                          </div>
                        </div>
                        <div class="row">
                          <div class="col-md-10" style="text-align: end;"><label for="unit_price"
                              class="form-label"><b>Subtotal</b></label>
                          </div>
                          <div class="col-md-2">
                            <input class="form-control" required="required" name="sub_total" type="number" step="0.01"
                              placeholder="Sub Total" id="sub_total"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['sub_total'] ?? '0'); ?>" onchange="changetotal()"
                              onkeypress="changetotal()" onkeyup="changetotal()">
                          </div>
                        </div>
                        <div class="row">
                          <div class="col-md-10" style="text-align: end;"><label for="unit_price"
                              class="form-label"></label></div>
                          <div class="col-md-2">
                            <label for="dissbox" class="form-label"><input type="checkbox" name="dissbox" id="dissbox"
                                <?php echo !empty($instantInvoicePendingData['discount']) && (float) $instantInvoicePendingData['discount'] > 0 ? 'checked' : ''; ?>
                                onchange="mydiscountrow()">
                              Add Discount</label>

                          </div>
                        </div>
                        <div class="row" style="display:none;" id="discountrow">
                          <div class="col-md-10" style="text-align: end;"><label for="unit_price"
                              class="form-label"><b>Discount</b></label>
                          </div>
                          <div class="col-md-2">
                            <input class="form-control" required="required" name="discount" type="number"
                              placeholder="Discount" step="0.01" id="discount"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['discount'] ?? '0'); ?>" onchange="getdiscount()"
                              onkeypress="getdiscount()" onkeyup="getdiscount()">
                          </div>
                        </div>

                        <div class="row">
                          <div class="col-md-10" style="text-align: end;"><label for="unit_price"
                              class="form-label"><b>Total</b></label>
                          </div>
                          <div class="col-md-2">
                            <input class="form-control" required="required" name="grand_total" type="number"
                              placeholder="Grand Total" step="0.01" id="grand_total"
                              value="<?php echo htmlspecialchars($instantInvoicePendingData['grand_total'] ?? '0'); ?>">
                          </div>
                        </div>


                      </div>
                      <!-- to send -->
                      <!-- Modal footer -->
                      <div class="modal-footer">

                        <button type="button" class="btn btn-info" data-dismiss="modal" id="closeButton">Close</button>

                        <script>
                          document.getElementById('closeButton').addEventListener('click',
                            function () {
                              location.reload(true);
                            });
                        </script>

                        <?php if (!empty($instantInvoiceMatches)) { ?>
                          <button type="submit" class="btn btn-outline-primary" name="createinvoice"
                            onclick="document.getElementById('bypass_client_match').value='0';">Re-check Match</button>
                          <button type="submit" class="btn btn-warning" name="createinvoice"
                            onclick="document.getElementById('bypass_client_match').value='1';">Proceed Anyway</button>
                        <?php } else { ?>
                          <button type="submit" class="btn btn-primary" name="createinvoice"
                            onclick="document.getElementById('bypass_client_match').value='0';">Submit</button>
                        <?php } ?>
                      </div>

                    </div>
                  </form>
                </div>
              </div>


                </div>
                <div class="tab-pane fade" id="invoice-comments-pane" role="tabpanel"
                  aria-labelledby="invoice-comments-tab">
                  <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row">
                    <div class="col-md-12">
                      <h5>Invoice Comments</h5>
                    </div>
                  </div>
                  <hr>
                  <div class="row">
                    <div class="table-responsive">
                      <table class="table datatable1" id="invoiceCommentsTable">
                        <thead>
                          <tr>
                            <th>Invoice ID</th>
                            <th>Date</th>
                            <th>By</th>
                            <th>Detail</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          $invoiceCommentsSql = "SELECT instant_comments.*,
                                  creator.user_login AS comment_by
                                FROM instant_comments
                                INNER JOIN billing_invoice_instant bii ON instant_comments.ins_invoice_id = bii.invoice_id
                                LEFT JOIN users AS creator ON instant_comments.user_id = creator.user_id
                                ORDER BY instant_comments.comment_id DESC";
                          $invoiceCommentsResult = mysqli_query($con, $invoiceCommentsSql);
                          while ($comment_row = mysqli_fetch_array($invoiceCommentsResult)) {
                            $commentdate = $comment_row['comment_time'];
                            $comment_detail = $comment_row["comment_detail"];
                            $comment_by = $comment_row["comment_by"] ?? 'Unknown';
                            ?>
                            <tr>
                              <td><?php echo (($comment_row['ins_invoice_id'] ?? 0) == 0) ? 'N/A' : htmlspecialchars($comment_row['ins_invoice_id']); ?></td>
                              <td>
                                <?php
                                $timestamp = strtotime($commentdate);
                                echo date("m/d/Y", $timestamp) . "<br>" . date("H:i:s", $timestamp);
                                ?>
                              </td>
                              <td><?php echo htmlspecialchars($comment_by); ?></td>
                              <td style="max-width: 320px; white-space: normal; word-wrap: break-word;">
                                <div style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.5em;">
                                  <?php echo strip_tags($comment_detail, '<p><br><b><i><strong><em>'); ?>
                                </div>
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
                <div class="tab-pane fade" id="email-history-pane" role="tabpanel"
                  aria-labelledby="email-history-tab">
                  <div class="card">
                <div class="card-body employee-detail-body fulls-card">
                  <div class="row">
                    <div class="col-md-10">
                      <h5>Email History</h5>
                    </div>
                    <div class="col-md-2"></div>
                  </div>
                  <hr>
                  <div class="row">
                    <div class="dataTable-container"><!-- same wrapper as invoices -->
                      <table class="table datatable1" id="emailHistoryTable">
                        <thead>
                          <tr>
                            <th data-sortable><a href="#" class="dataTable-sorter">Invoice ID</a></th>
                            <th data-sortable><a href="#" class="dataTable-sorter">Date Sent</a></th>
                            <th data-sortable><a href="#" class="dataTable-sorter">Type</a></th>
                            <th data-sortable><a href="#" class="dataTable-sorter">Subject</a></th>
                            <th data-sortable><a href="#" class="dataTable-sorter">Sent By</a></th>
                            <th data-sortable><a href="#" class="dataTable-sorter">To</a></th>
                            <th data-sortable><a href="#" class="dataTable-sorter">From</a></th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          $emails = mysqli_query($con, "
                        SELECT eh.*, u.user_name
                        FROM email_history eh
                        JOIN users u ON eh.sent_by = u.user_id
                        WHERE eh.client_id = '$client_id'
                        ORDER BY eh.sent_at DESC");
                          while ($row = mysqli_fetch_assoc($emails)) {
                            echo "
                        <tr>
                          <td>" . htmlspecialchars($row['invoice_id']) . "</td>
                          <td>" . date("m/d/Y h:i A", strtotime($row['sent_at'])) . "</td>
                          <td>" . htmlspecialchars($row['type']) . "</td>
                          <td>" . htmlspecialchars($row['subject']) . "</td>
                          <td>" . htmlspecialchars($row['user_name']) . "</td>
                          <td>" . htmlspecialchars($row['To_mail'] ?? '') . "</td>
                          <td>" . htmlspecialchars($row['From_mail'] ?? '') . "</td>
                        </tr>";
                          } ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
                </div>
              </div>
            </div>
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

              //totalSpan.textContent = sum;
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

              // console.log("Sub Total=" + sub_total);
              // console.log("Discount=" + discount);
              // console.log("Grand=" + grand_total);


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
          <!-- SweetAlert2 -->
          <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

          <script>
            // ── Email Reminder ────────────────────────────────────────────────
            function sendReminder(invoiceId) {
              Swal.fire({
                title: "📩 Send Email Reminder",
                html: `<b>Invoice #${invoiceId}</b><br>Confirm sending the reminder now?`,
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#6FD943",
                cancelButtonColor: "#9e9e9e",
                confirmButtonText: "<i class='ti ti-send'></i> Send Now",
                cancelButtonText: "Cancel",
                reverseButtons: true,
                customClass: {
                  popup: "rounded-4 shadow-lg p-3",
                  confirmButton: "px-4 py-2 rounded-3",
                  cancelButton: "px-4 py-2 rounded-3"
                }
              }).then((result) => {
                if (!result.isConfirmed) return;

                Swal.fire({
                  title: "⏳ Sending...",
                  html: "<small>Please wait...</small>",
                  allowOutsideClick: false,
                  showConfirmButton: false,
                  didOpen: () => Swal.showLoading()
                });

                $.ajax({
                  url: "send_inst_inv_reminder.php",
                  type: "POST",
                  data: { invoice_id: invoiceId },
                  success: function (response) {
                    Swal.fire({
                      title: "✅ Sent!",
                      html: `<b>${response || "Reminder email sent successfully"}</b>`,
                      icon: "success",
                      confirmButtonColor: "#6FD943"
                    });
                  },
                  error: function (xhr) {
                    Swal.fire({
                      title: "⚠️ Failed",
                      html: xhr.responseText || "Could not send the reminder.",
                      icon: "error",
                      confirmButtonColor: "#dc3545"
                    });
                  }
                });
              });
            }

            // ── WhatsApp / SMS Reminder (unified chooser) ─────────────────────
            function sendWhatsAppReminder(invoiceId) {
              Swal.fire({
                title: "💬 Send Reminder",
                html: `<b>Invoice #${invoiceId}</b><br>Choose delivery method:`,
                icon: "question",
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonColor: "#25D366",
                denyButtonColor: "#0d6efd",
                cancelButtonColor: "#9e9e9e",
                confirmButtonText: '<i class="fa fa-whatsapp"></i> WhatsApp',
                denyButtonText: '<i class="fa fa-envelope"></i> SMS',
                cancelButtonText: "Cancel",
                reverseButtons: true
              }).then((result) => {
                if (result.isConfirmed) {
                  // WhatsApp
                  sendReminderAjax(invoiceId, "whatsapp");
                } else if (result.isDenied) {
                  // SMS
                  sendReminderAjax(invoiceId, "sms");
                }
                // cancel → do nothing
              });
            }

            function sendReminderAjax(invoiceId, type) {   // type = "whatsapp" | "sms"
              const isWhatsApp = type === "whatsapp";
              const color = isWhatsApp ? "#25D366" : "#0d6efd";
              const icon = isWhatsApp ? "fa-whatsapp" : "fa-envelope";

              Swal.fire({
                title: "⏳ Sending...",
                html: `<small>Sending via ${isWhatsApp ? "WhatsApp" : "SMS"}...</small>`,
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
              });

              $.ajax({
                url: isWhatsApp ? "send_whatsapp_reminder.php" : "send_inst_sim_reminder.php",
                type: "POST",
                data: { invoice_id: invoiceId },
                success: function (resp) {
                  Swal.fire({
                    title: "✅ Sent!",
                    html: `<b>${resp || "Message sent successfully"}</b>`,
                    icon: "success",
                    confirmButtonColor: color
                  });
                },
                error: function (xhr) {
                  Swal.fire({
                    title: "⚠️ Failed",
                    html: xhr.responseText || "Delivery failed. Please try again.",
                    icon: "error",
                    confirmButtonColor: "#dc3545"
                  });
                }
              });
            }

            // ── Manual / Custom SMS ───────────────────────────────────────────
            function sendManualSms(clientId) {
              Swal.fire({
                title: "Send Manual SMS",
                input: "textarea",
                inputLabel: "Message",
                inputPlaceholder: "Type your message here...",
                inputAttributes: { maxlength: 1600 },
                showCancelButton: true,
                confirmButtonText: "Send",
                confirmButtonColor: "#25D366",
                showLoaderOnConfirm: true,
                preConfirm: (text) => {
                  if (!text?.trim()) {
                    Swal.showValidationMessage("Message cannot be empty");
                    return false;
                  }

                  return $.post("send_manual_sms.php", {
                    client_id: clientId,
                    sms_body: text.trim()
                  }, null, "text")
                    .then(response => response)
                    .catch(xhr => {
                      Swal.showValidationMessage(xhr.responseText || "Failed to send SMS");
                      return false;
                    });
                }
              }).then((result) => {
                if (result.isConfirmed) {
                  Swal.fire("Success!", "SMS has been sent.", "success");
                }
              });
            }
          </script>



          <script>
            /* -----------------------------------------------------------
               Quick-phrase – CKEditor 5 ONLY (visible modal textarea)
            ----------------------------------------------------------- */
            function addPhrase(text) {
              /* the textarea inside the currently open Bootstrap modal */
              const tx = document.querySelector('.modal.show textarea.ckeditor');
              if (!tx || !tx.ckeditorInstance) return;   // safety

              const editor = tx.ckeditorInstance;
              editor.model.change(writer => {
                writer.insertText(text + '. ', editor.model.document.selection.getFirstPosition());
              });
              editor.editing.view.focus();
            }
          </script>
        </div>
      </div>
      <!-- =========================
              footer file
       ======================== -->

      <?php include "includes/scripts.php"; ?>
      <script type="text/javascript">
        $(document).ready(function () {
          // Event listener for opening the modal
          $('.comment-details-clickable').click(function () {
            var commentdate = $(this).attr('cd');
            var commentby = $(this).attr('cb');
            var commentDetails = $(this).text();

            $("#cd").text(commentdate);
            $("#cb").text(commentby);
            $("#cdd").text(commentDetails);
            $('#myModal').modal('show');
          });

          // Event listener for closing the modal
          $('.closemodal').click(function () {
            $('#myModal').modal('hide');
          });

          // Event listener for checkbox
          const checkbox = document.getElementById('show');
          const box = document.getElementById('box');

          checkbox.addEventListener('click', function handleClick() {
            if (checkbox.checked) {
              box.style.display = 'none';
            } else {
              box.style.display = 'block';
            }
          });
        });
      </script>

      <script>
        /* ----------  lazy-init CKE 5: build the editor only when its modal opens ---------- */
        (function () {
          function initCkeIn(modalEl) {
            const tx = modalEl.querySelector('textarea.ckeditor');
            if (!tx || tx.dataset.ckeReady === '1') return;
            tx.dataset.ckeReady = '1';
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
              .then(editor => { tx.ckeditorInstance = editor; })
              .catch(console.error);
          }

          /* Bootstrap 5 (data-bs-toggle) */
          document.querySelectorAll('.modal').forEach(m => {
            m.addEventListener('shown.bs.modal', () => initCkeIn(m));
          });

          /* Bootstrap 4 fallback (data-toggle) — jQuery already loaded */
          if (window.jQuery) {
            jQuery(document).on('shown.bs.modal', '.modal', function () { initCkeIn(this); });
          }
        })();

        /* ----------  quick-phrase buttons (unchanged behaviour) ---------- */

      </script>

  <?php if (($_SESSION['user_type'] ?? '') == '1'): ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    (function () {
      var unpaidN  = <?php echo $_stat_unpaid_n; ?>;
      var paidN    = <?php echo $_stat_paid_n; ?>;
      var otherN   = <?php echo max(0, $_stat_total - $_stat_unpaid_n - $_stat_paid_n); ?>;
      var unpaidR  = <?php echo $_stat_unpaid_r; ?>;
      var paidR    = <?php echo $_stat_paid_r; ?>;
      var overallR = <?php echo $_stat_overall_r; ?>;

      var donutOpts = {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 9 }, boxWidth: 10 } } }
      };

      // Donut — Total Unpaid Invoices (count)
      new Chart(document.getElementById('instUnpaidCountChart'), {
        type: 'doughnut',
        data: {
          labels: ['Unpaid', 'Paid', 'Other'],
          datasets: [{ data: [unpaidN, paidN, otherN], backgroundColor: ['#dc3545', '#28a745', '#6c757d'] }]
        },
        options: donutOpts
      });

      // Bar — Unpaid vs Paid Revenue
      new Chart(document.getElementById('instUnpaidRevChart'), {
        type: 'bar',
        data: {
          labels: ['Unpaid', 'Paid'],
          datasets: [{ data: [unpaidR, paidR], backgroundColor: ['#ffc107', '#28a745'] }]
        },
        options: {
          responsive: true, maintainAspectRatio: true,
          scales: {
            y: { beginAtZero: true, ticks: { font: { size: 8 }, callback: function(v){ return '$' + v.toLocaleString(); } } },
            x: { ticks: { font: { size: 8 } } }
          },
          plugins: { legend: { display: false } }
        }
      });

      // Donut — Paid Revenue share
      new Chart(document.getElementById('instPaidRevChart'), {
        type: 'doughnut',
        data: {
          labels: ['Paid', 'Outstanding'],
          datasets: [{ data: [paidR, Math.max(0, overallR - paidR)], backgroundColor: ['#28a745', '#dc3545'] }]
        },
        options: donutOpts
      });

      // Bar — Overall Revenue breakdown
      new Chart(document.getElementById('instOverallRevChart'), {
        type: 'bar',
        data: {
          labels: ['Unpaid', 'Paid', 'Total'],
          datasets: [{ data: [unpaidR, paidR, overallR], backgroundColor: ['#dc3545', '#28a745', '#007bff'] }]
        },
        options: {
          responsive: true, maintainAspectRatio: true,
          scales: {
            y: { beginAtZero: true, ticks: { font: { size: 8 }, callback: function(v){ return '$' + v.toLocaleString(); } } },
            x: { ticks: { font: { size: 8 } } }
          },
          plugins: { legend: { display: false } }
        }
      });
    })();
  </script>
  <?php endif; ?>

</body>
<script type="text/javascript">
  const card_cvv = document.getElementById("card_cvv");
  let card_cvv_length = '';
  const card_cvv_Handler = function (e) {
    card_cvv_length = e.target.value;
    if (card_cvv_length.length >= 4) {
      e.target.value = card_cvv_length.substring(0, 4);
    }
  }
  card_cvv.addEventListener('input', card_cvv_Handler);

  const card_no = document.getElementById("card_no");
  let card_no_length = '';
  const card_no_Handler = function (e) {
    card_no_length = e.target.value;
    if (card_no_length.length >= 16) {
      e.target.value = card_no_length.substring(0, 16);
    }
  }
  card_no.addEventListener('input', card_no_Handler);

  function card_expiry_onchange() {
    let others = document.getElementById("card_type").value;
    let hidbankcardname = document.getElementById("hid_bank_card_name");
    if (others == "others") {
      hidbankcardname.innerHTML =
        "<div class='row'><div class='col-md-6'><label for='bankcardtype' class='form-label'>Bank Card Type</label><input type='text' id='bankcardtype' name='bankcardtype' class='form-control' placeholder='Bank Card Type'></div></div>";
    } else {
      hidbankcardname.innerHTML = "";
    }
  }

  function formatZipCode(input) {
    // Remove any non-numeric characters
    let zip = input.value.replace(/\D/g, '');

    // Limit the length to 5 characters
    zip = zip.substring(0, 5);

    // Update the input value
    input.value = zip;
  }
</script>

<!-- =========  PDF PREVIEW (hover) – same code as invoice-list page  ========= -->
<script>
  $(function () {
    const $overlay = $(`
         <div id="pdfPreviewOverlay"
         style="position:fixed; top:0; left:0; width:100%; height:100%;
         background:rgba(0,0,0,.65); z-index:9999; display:none;">
         <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
         width:70%; max-width:800px; height:80%; max-height:600px;
         background:#fff; border-radius:6px; overflow:hidden;
         box-shadow:0 5px 25px rgba(0,0,0,.3);">
      
      
      <button id="closePdfPreview"
      style="position:absolute; right:8px; top:8px; z-index:2;
      border:none; background:#e74c3c; color:#fff;
      padding:4px 10px; border-radius:3px; cursor:pointer;">&times;</button>
      
      <div style="position:absolute; top:0; left:0; width:100%; height:100%; overflow:hidden;">
      <iframe id="pdfPreviewFrame" src="" width="100%" height="100%" frameborder="0"
      style="position:absolute; 
      zoom:1.35;
      -moz-transform:scale(1);
      -moz-transform-origin:top left;">
      </iframe>
      </div>
    </div>
  </div>`).appendTo('body');

    let hoverTimer = null;

    $(document).on('mouseenter', '.view-file-preview', function () {
      const pdfPath = 'instant_invoices/' + $(this).data('file');   // file name we attach below
      clearTimeout(hoverTimer);
      hoverTimer = setTimeout(function () {
        $overlay.fadeIn(180);
        $('#pdfPreviewFrame').attr('src', pdfPath);
      }, 400);
    });

    $(document).on('mouseleave', '.view-file-preview', () => clearTimeout(hoverTimer));

    /* ---- close overlay ---- */
    $overlay.on('click', function (e) {
      if (e.target === this || e.target.id === 'closePdfPreview') {
        $overlay.fadeOut(150);
        $('#pdfPreviewFrame').attr('src', '');   // free memory
      }
    });
  });
</script>
<script>/* ----------  DOWNLOAD INSTANT-INVOICE HISTORY PDF  ---------- */
  $(document).on('click', '#btnDownloadHistory', function () {
    const invId = $('#invoicehistory').data('invoice-id');   // we store it when the modal opens
    if (!invId) return;

    const url = 'instant_invoice_history_pdf.php?invoice_id=' + invId;
    window.open(url, '_blank');          // stream the PDF
  });</script>
<script>
  if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
  }
</script>
<?php if ($reopenInstantInvoiceModal) { ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const modalElement = document.getElementById('associatedProducts');
      if (!modalElement) return;

      if (window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
      } else if (window.jQuery) {
        $('#associatedProducts').modal('show');
      }

      if (parseFloat(document.getElementById('discount')?.value || 0) > 0) {
        document.getElementById('discountrow').style.display = 'flex';
      }
    });
  </script>
<?php } ?>
<script>
  /* ---- SweetAlert2 choice ---- */
  function pickHistoryPreview(clientId) {
    Swal.fire({
      title: 'Which invoices?',
      text: 'Do you want to preview PAID or UN-PAID history?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Paid',
      cancelButtonText: 'Un-paid',
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#dc3545',
      reverseButtons: true
    }).then((result) => {
      if (result.isConfirmed) {                    // PAID
        window.open('inv_full_his.php?client_id=' + clientId + '&preview=1', '_blank');
      } else if (result.dismiss === Swal.DismissReason.cancel) { // UN-PAID
        window.open('inv_unpaid_full.php?client_id=' + clientId + '&preview=1', '_blank');
      }
    });
  }

  /* ---- filter by FIRST letters ---- */
  $(document).on('keyup', '#serviceSearchBox', function () {
    const term = $(this).val().toLowerCase().trim();
    $('#serviceList .service-item').each(function () {
      const label = $(this).find('label').text().toLowerCase();
      // match if ANY word in the label starts with the typed term
      const show = !term || label.split(/\s+/).some(w => w.startsWith(term));
      $(this).toggle(show);
    });
  });


  /* ----------  remove service & re-calc totals – NO external file  ---------- */
  $(document).on('change', 'input[name="vehicle2"]', function () {
    const $chk = $(this);
    const id = $chk.val();
    if (!$chk.is(':checked')) {                 // user un-ticked
      $('#category-dropdown' + id).empty(); // 1. wipe the row

      /* 2.  re-add every remaining service to get fresh totals */
      let newSub = 0;
      $('.sum').each(function () { newSub += parseFloat(this.value) || 0; });
      const discount = parseFloat($('#discount').val()) || 0;
      $('#sub_total').val(newSub.toFixed(2));
      $('#grand_total').val((newSub - discount).toFixed(2));
    }
  });

  ['inst_phone'].forEach(id => {
    if (document.getElementById(id)) {
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
  });

</script>
<?php // echo getStripeJavaScript(); ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
  $(function () {
    if ($.fn.select2) {
      $('.filter-select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: function () { return $(this).find('option:first').text(); },
        allowClear: true
      });
    }
  });
</script>
<?php include 'highlighter.php'; ?>
</html>
