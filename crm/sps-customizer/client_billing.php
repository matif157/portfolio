<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
require_once 'dompdf/autoload.inc.php';
// reference the Dompdf namespace
use Dompdf\Dompdf;

include 'includes/db.php';
include "includes/auth.php";
include "includes/activity_log.php";
require_once 'stripe_payment.php';

$current_date = date('m/d/Y');
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

$client_id = $_GET['id'];
$userid = $_SESSION["user_id"];
$user_type = $_SESSION['user_type'] ?? '';
/* By / Created / Payment Method on an invoice are admin-only (user_type 1). */
$is_admin = ($user_type == '1');

/* Field-level invoice-edit permissions (admins can edit everything).
   Columns live in user_permissions; a missing column is treated as 'No'. */
$invPermRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM user_permissions WHERE user_id='" . (int) $userid . "' LIMIT 1")) ?: [];
$cap_due              = $is_admin || (($invPermRow['inv_edit_due'] ?? 'No') === 'Yes');
$cap_service          = $is_admin || (($invPermRow['inv_edit_service'] ?? 'No') === 'Yes');
$cap_service_desc     = $is_admin || (($invPermRow['inv_edit_service_desc'] ?? 'No') === 'Yes');
$cap_service_amount   = $is_admin || (($invPermRow['inv_edit_service_amount'] ?? 'No') === 'Yes');
$cap_discount         = $is_admin || (($invPermRow['inv_edit_discount'] ?? 'No') === 'Yes');
$cap_paid_amount      = $is_admin || (($invPermRow['inv_edit_paid_amount'] ?? 'No') === 'Yes');
$cap_service_provider = $is_admin || (($invPermRow['inv_edit_service_provider'] ?? 'No') === 'Yes');
$cap_paid_date        = $is_admin || (($invPermRow['inv_edit_paid_date'] ?? 'No') === 'Yes');

/* ===== AJAX: edit / delete a recorded payment from the Invoice History popup =====
   Placed before client_field_notes.php (which echoes HTML on include) so the JSON
   response is never polluted. recalc_invoice_from_payments() / payment_history_rows()
   are defined further down — top-level functions are hoisted, so they're callable here. */
if (isset($_POST['ajax_payment_edit'])) {
  header('Content-Type: application/json');
  if (!$is_admin) {
    echo json_encode(['ok' => false, 'error' => 'Only admins can edit payment history.']);
    exit;
  }
  $his_id     = (int) ($_POST['his_id'] ?? 0);
  $invoice_id = (int) ($_POST['invoice_id'] ?? 0);

  $payRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM billing_payment_history WHERE his_id='$his_id' AND inv_id='$invoice_id' LIMIT 1"));
  if (!$payRow) {
    echo json_encode(['ok' => false, 'error' => 'Payment not found.']);
    exit;
  }

  $paid = (float) ($_POST['paid'] ?? 0);
  if ($paid < 0) {
    $paid = 0;
  }
  $detail = trim($_POST['detail'] ?? '');
  $method = trim($_POST['payment_method'] ?? '');

  /* Balance and Payment Added By are user-controlled; blank keeps the existing value. */
  $balanceRaw = trim($_POST['balance'] ?? '');
  $balanceF   = ($balanceRaw === '') ? number_format((float) $payRow['balance'], 2, '.', '') : number_format((float) $balanceRaw, 2, '.', '');
  $employee   = trim($_POST['employee'] ?? '');
  if ($employee === '') {
    $employee = $payRow['employee'] ?? '';
  }

  /* normalise date to Y-m-d (accept m/d/Y or Y-m-d); blank keeps the existing date */
  $dateSql = $payRow['date'];
  $dateIn  = trim($_POST['date'] ?? '');
  if ($dateIn !== '') {
    $ts = strtotime($dateIn);
    if ($ts !== false) {
      $dateSql = date('Y-m-d', $ts);
    }
  }

  $paidF     = number_format($paid, 2, '.', '');
  $detailE   = mysqli_real_escape_string($con, $detail);
  $methodE   = mysqli_real_escape_string($con, $method);
  $dateE     = mysqli_real_escape_string($con, $dateSql);
  $employeeE = mysqli_real_escape_string($con, $employee);

  mysqli_query($con, "UPDATE billing_payment_history SET paid='$paidF', balance='$balanceF', date='$dateE', detail='$detailE', payment_method='$methodE', employee='$employeeE' WHERE his_id='$his_id'");

  $inv = recalc_invoice_from_payments($con, $invoice_id);

  logActivity($con, $userid, 'UPDATE', 'billing_payment_history', $his_id, "Edited payment (history #{$his_id}) on invoice #{$invoice_id}. Paid: \${$paidF}, method: {$method}.");

  echo json_encode(['ok' => true, 'invoice' => $inv, 'payments' => payment_history_rows($con, $invoice_id)]);
  exit;
}

if (isset($_POST['ajax_payment_delete'])) {
  header('Content-Type: application/json');
  if (!$is_admin) {
    echo json_encode(['ok' => false, 'error' => 'Only admins can delete payment history.']);
    exit;
  }
  $his_id     = (int) ($_POST['his_id'] ?? 0);
  $invoice_id = (int) ($_POST['invoice_id'] ?? 0);

  $payRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM billing_payment_history WHERE his_id='$his_id' AND inv_id='$invoice_id' LIMIT 1"));
  if (!$payRow) {
    echo json_encode(['ok' => false, 'error' => 'Payment not found.']);
    exit;
  }

  mysqli_query($con, "DELETE FROM billing_payment_history WHERE his_id='$his_id'");

  $inv = recalc_invoice_from_payments($con, $invoice_id);
  $logPaid = is_array($inv) ? $inv['paid'] : '?';
  $logBal  = is_array($inv) ? $inv['balance'] : '?';

  logActivity($con, $userid, 'DELETE', 'billing_payment_history', $his_id, "Deleted payment of \${$payRow['paid']} (history #{$his_id}) from invoice #{$invoice_id} via Invoice History popup. New paid: \${$logPaid}, balance: \${$logBal}.");

  echo json_encode(['ok' => true, 'invoice' => $inv, 'payments' => payment_history_rows($con, $invoice_id)]);
  exit;
}

// Function to get initials
include "client_field_notes.php";
include "module_history.php";
function getInitials($name)
{

  $words = explode(' ', $name);
  $initials = '';
  foreach ($words as $word) {
    $initials .= strtoupper(substr($word, 0, 1));
  }

  return $initials;
}

/**
 * Rebuild an invoice's PDF from its current database state.
 * Used after a payment is deleted so the saved document stays in sync
 * with the recalculated paid amount / balance / status.
 */
function regenerate_invoice_pdf($con, $invoice_id)
{
  $invoice_id = (int) $invoice_id;
  $inv = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM billing_invoice WHERE invoice_id='$invoice_id' LIMIT 1"));
  if (!$inv) {
    return false;
  }
  $memberid = (int) $inv['mem_id'];

  /* client */
  $name = $address = $address1 = $email = $phone = '';
  if ($fc = mysqli_fetch_array(mysqli_query($con, "SELECT * FROM members WHERE member_id='$memberid'"))) {
    if ($fc['bus_name'] != '') {
      $name = $fc['bus_name'];
      $address = $fc['address'];
      $email = $fc['prm_email'];
    } elseif ($fc['dba'] != '') {
      $name = $fc['dba'];
      $address = $fc['address'];
      $email = $fc['prm_email'];
    } else {
      $name = $fc['name'] . " " . $fc['lname'];
      $address = $fc['hstreet'];
      $email = $fc['sec_email'];
    }
    $address1 = $fc['hcity'] . ", " . $fc['hstate'] . ", " . $fc['hzip'];
    $phone = $fc['cell_ph'];
  }

  /* items */
  $drows = '';
  $itq = mysqli_query($con, "SELECT * FROM billing_invoice_items WHERE inv_id='$invoice_id' ORDER BY inv_item_id ASC");
  while ($it = mysqli_fetch_array($itq)) {
    $drows .= '<tr class="item"><td><b>' . htmlspecialchars($it['item_description']) . '</b><p style="font-size:12px;margin:0px">' . htmlspecialchars($it['item_sub_description']) . '</p></td><td>$' . number_format($it['unit_price'], 2) . '</td></tr>';
  }

  /* service provider initials */
  $sp_name = '';
  if (!empty($inv['service_provider_id'])) {
    if ($spr = mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id='" . (int) $inv['service_provider_id'] . "'"))) {
      $sp_name = getInitials($spr['user_name']);
    }
  }

  /* payment method from the most recent remaining payment */
  $pm = mysqli_fetch_assoc(mysqli_query($con, "SELECT payment_method FROM billing_payment_history WHERE inv_id='$invoice_id' ORDER BY his_id DESC LIMIT 1"));
  $payment_methods_str = ($pm && $pm['payment_method']) ? $pm['payment_method'] : '';

  $img_base = 'http://192.168.15.29/storage/uploads/logo/';
  switch ((string) $inv['invoice_status']) {
    case '3': $status_text = 'Paid'; $status_img = $img_base . 'paid.png'; break;
    case '2': $status_text = 'Partial Paid'; $status_img = $img_base . 'partial_payment.jpg'; break;
    default:  $status_text = 'UnPaid'; $status_img = $img_base . 'unpaid.jpg'; break;
  }

  $totalamount = number_format((float) $inv['total_amount'], 2);
  $newpaid     = number_format((float) $inv['paid_amount'], 2);
  $newbalance  = number_format((float) $inv['balance'], 2);
  $discount    = (float) $inv['discount'];

  $created = !empty($inv['invoice_date']) ? date('m/d/Y', strtotime($inv['invoice_date'])) : '';
  $due     = (!empty($inv['due_date']) && $inv['due_date'] != '0000-00-00') ? date('m/d/Y', strtotime($inv['due_date'])) : '';
  $by      = htmlspecialchars((string) ($inv['employee_initials'] ?? ''));
  $description = htmlspecialchars((string) ($inv['payment_details'] ?? ''));

  $paidLine = '';
  if (($inv['invoice_status'] == 2 || $inv['invoice_status'] == 3) && !empty($inv['paid_date']) && $inv['paid_date'] != '0000-00-00') {
    $paidLine = $status_text . ': ' . date('m/d/Y', strtotime($inv['paid_date'])) . '<br>';
  }
  $discountRow = $discount > 0
    ? '<tr class="total"><td></td><td style="font-weight:bold;">Discount: $' . number_format($discount, 2) . '</td></tr>'
    : '';
  $pmLine = $payment_methods_str !== '' ? '<br><b>Payment Method(s):</b> ' . htmlspecialchars($payment_methods_str) : '';

  $content = '
  <style>
    .invoice-box{position:relative;height:99%;width:100%;margin:auto;border:1px solid #eee;box-shadow:0 0 10px rgba(0,0,0,0.15);font-size:16px;line-height:24px;color:#555;}
    .invoice-box .footer-note{position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);text-align:center;font-size:14px;}
    .invoice-box .footer-note p{margin:0;font-size:14px;white-space:nowrap;}
    .invoice-box .footer-note h4{margin-top:5px;font-size:16px;}
    .invoice-box table{width:100%;line-height:inherit;text-align:left;border-collapse:collapse;}
    .invoice-box table td{padding:5px;vertical-align:top;}
    .invoice-box table tr td:nth-child(2){text-align:right;}
    .invoice-box table tr.heading td{background:#8b742a;border-bottom:1px solid #ddd;font-weight:bold;color:white;font-size:22px;}
    .invoice-box table tr.item td{border-bottom:1px solid #eee;}
    .invoice-box table tr.total td:nth-child(2){border-top:2px solid #eee;font-weight:bold;}
    .bank-details{font-size:14px;line-height:1.6;margin-top:10px;} .bank-details strong{color:#333;}
  </style>
  <div class="invoice-box">
    <table>
      <tr class="top"><td colspan="2"><table><tr>
        <td class="title"><img src="' . $img_base . 'logo-dark.png" style="width:60%;max-width:300px;"></td>
        <td>
          Invoice #: ' . $invoice_id . '<br>
          By: ' . $by . '<br>
          Created: ' . $created . '<br>
          Due: ' . $due . '<br>
          ' . $paidLine . '
          <img src="' . $status_img . '" style="width:30%;margin-top:12px;max-width:300px" />
        </td>
      </tr></table></td></tr>
      <tr class="information"><td colspan="2"><table><tr>
        <td>From:<br>Syed Professional Services<br>1012 Coney Island Ave<br>Brooklyn, NY 11230<br>Phone: (718) 859-5392<br>Fax: (718) 859-2237<br>invoice@syedpro.com</td>
        <td><b>To:</b> <br>' . $name . '<br>' . $address . '<br>' . $address1 . '<br>' . $phone . '<br>' . $email . '</td>
      </tr></table></td></tr>
      <tr class="heading"><td>Service</td><td>Price</td></tr>
      <tr class="details"><td><b>Service Provider:</b> ' . $sp_name . ' (' . $inv['service_provider_id'] . ')</td></tr>
      ' . $drows . '
      <tr class="details"><td colspan="2"><b>Description:</b> ' . $description . '</td></tr>
      <tr class="total"><td></td><td>Total: $' . $totalamount . '</td></tr>
      <tr class="total"><td></td><td>Paid: $' . $newpaid . '</td></tr>
      ' . $discountRow . '
      <tr class="total"><td></td><td>Balance: $' . $newbalance . '</td></tr>
      <tr class="details"><td><div class="bank-details"><strong>Zelle Payment Info:</strong> info@syedpro.com' . $pmLine . '</div></td></tr>
    </table>
    <div class="footer-note">
      <p>A late fee of $35 per month will be applied to any outstanding balance not paid within the due date.
      <br>We accept the payment via Zelle,Credit or debit Card,Cash and cheque.</p>
      <h4><u>Thank you for choosing Syed Professional Services</u></h4>
    </div>
  </div>';

  $dompdf = new Dompdf(array('enable_remote' => true));
  $dompdf->loadHtml($content);
  $dompdf->render();
  $US_Date = date('m-d-Y');
  $PDFFileN = "INV-{$invoice_id}-MemID-{$memberid}-{$US_Date}.pdf";
  file_put_contents("invoices/" . $PDFFileN, $dompdf->output());
  mysqli_query($con, "UPDATE billing_invoice SET filelink='" . mysqli_real_escape_string($con, $PDFFileN) . "' WHERE invoice_id='$invoice_id'");
  return true;
}

/**
 * Recalculate an invoice from its current payment rows after a payment is
 * edited or deleted. Total paid is the objective sum of every payment; the
 * invoice's current balance and paid date follow the most recent payment row
 * (whose Balance the user now edits directly). Per-row balances are left as
 * entered. The saved PDF is then regenerated.
 * Returns the new ['paid','balance','status','paid_date'] for the AJAX response.
 */
function recalc_invoice_from_payments($con, $invoice_id)
{
  $invoice_id = (int) $invoice_id;
  $inv = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM billing_invoice WHERE invoice_id='$invoice_id' LIMIT 1"));
  if (!$inv) {
    return false;
  }

  $total = (float) $inv['total_amount'];
  $disc  = (float) $inv['discount'];

  /* total paid is the objective sum of every recorded payment */
  $sumRow  = mysqli_fetch_assoc(mysqli_query($con, "SELECT COALESCE(SUM(paid),0) AS paid_sum FROM billing_payment_history WHERE inv_id='$invoice_id'"));
  $newPaid = (float) $sumRow['paid_sum'];
  if ($newPaid < 0) {
    $newPaid = 0;
  }

  /* current balance / paid date follow the latest payment row (user-controlled);
     with no rows left, fall back to total minus discount. */
  $last = mysqli_fetch_assoc(mysqli_query($con, "SELECT balance, date FROM billing_payment_history WHERE inv_id='$invoice_id' ORDER BY his_id DESC LIMIT 1"));
  if ($last) {
    $newBalance  = (float) $last['balance'];
    $newPaidDate = (!empty($last['date']) && $last['date'] != '0000-00-00') ? $last['date'] : '0000-00-00';
  } else {
    $newBalance  = $total - $disc;
    $newPaidDate = '0000-00-00';
  }

  if ($total > 0 && $newBalance <= 0) {
    $newStatus = 3;
  } elseif ($newPaid > 0) {
    $newStatus = 2;
  } else {
    $newStatus = 1;
  }

  $newPaidF = number_format($newPaid, 2, '.', '');
  $newBalF  = number_format($newBalance, 2, '.', '');

  mysqli_query($con, "UPDATE billing_invoice SET paid_amount='$newPaidF', balance='$newBalF', invoice_status='$newStatus', paid_date='$newPaidDate' WHERE invoice_id='$invoice_id'");

  /* keep the saved PDF in sync with the new totals */
  regenerate_invoice_pdf($con, $invoice_id);

  return ['paid' => $newPaidF, 'balance' => $newBalF, 'status' => (int) $newStatus, 'paid_date' => $newPaidDate];
}

/**
 * Payment-history rows for an invoice, in the shape the Invoice History popup
 * renders (same data as invoicehistory.php). Used to refresh the popup after
 * an inline edit/delete without a second request.
 */
function payment_history_rows($con, $invoice_id)
{
  $invoice_id = (int) $invoice_id;
  $out = [];
  $res = mysqli_query($con, "SELECT * FROM billing_payment_history WHERE inv_id='$invoice_id' ORDER BY his_id ASC");
  if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
      $out[] = $row;
    }
  }
  return $out;
}

/**
 * Undo whatever a balance adjustment previously applied to an invoice.
 * Applied payments are tagged with "[BalAdj:<id>]" in billing_payment_history.detail
 * (no dedicated link column needed) so they can be found and removed again here;
 * every invoice touched is then recalculated. Safe to call even if nothing was ever applied.
 */
function revert_balance_adjustment_payment($con, $adj_id)
{
  $adj_id = (int) $adj_id;
  $tagE = mysqli_real_escape_string($con, "[BalAdj:{$adj_id}]");

  $invIds = [];
  $res = mysqli_query($con, "SELECT DISTINCT inv_id FROM billing_payment_history WHERE detail LIKE '%$tagE%'");
  while ($row = mysqli_fetch_assoc($res)) {
    $invIds[] = (int) $row['inv_id'];
  }
  if (empty($invIds)) {
    return;
  }

  mysqli_query($con, "DELETE FROM billing_payment_history WHERE detail LIKE '%$tagE%'");

  foreach ($invIds as $invId) {
    recalc_invoice_from_payments($con, $invId);
  }
}

/**
 * Apply a CREDIT balance adjustment to its linked invoice as a real payment,
 * tagged so revert_balance_adjustment_payment() can find and undo it later.
 */
function apply_balance_adjustment_to_invoice($con, $adj_id, $client_id, $invoice_id, $amount, $date, $method, $desc, $employee)
{
  $invoice_id = (int) $invoice_id;
  $inv = mysqli_fetch_assoc(mysqli_query($con, "SELECT balance FROM billing_invoice WHERE invoice_id='$invoice_id' LIMIT 1"));
  if (!$inv) {
    return;
  }

  $amountF    = number_format((float) $amount, 2, '.', '');
  $newBalance = number_format((float) $inv['balance'] - (float) $amount, 2, '.', '');
  $detail     = "Credit applied from Balance Adjustment #{$adj_id} [BalAdj:{$adj_id}]" . ($desc !== '' ? " - {$desc}" : '');

  $detailE   = mysqli_real_escape_string($con, $detail);
  $dateE     = mysqli_real_escape_string($con, $date);
  $methodE   = mysqli_real_escape_string($con, $method !== '' ? $method : 'Credit Balance');
  $employeeE = mysqli_real_escape_string($con, $employee);
  $client_id = (int) $client_id;

  mysqli_query($con, "INSERT INTO billing_payment_history (mem_id, inv_id, paid, balance, detail, date, employee, payment_method)
                       VALUES ('$client_id', '$invoice_id', '$amountF', '$newBalance', '$detailE', '$dateE', '$employeeE', '$methodE')");

  recalc_invoice_from_payments($con, $invoice_id);
}

/** Deterministic path for a balance adjustment's PDF receipt — file_exists() on this doubles as the "has a PDF" flag, no DB column needed. */
function balance_adjustment_pdf_path($adj_id, $client_id)
{
  return "invoices/ADJ-" . (int) $adj_id . "-MemID-" . (int) $client_id . ".pdf";
}

/** Render and save a receipt PDF for a balance adjustment, matching the existing invoice PDF letterhead. */
function generate_balance_adjustment_pdf($con, $adj_id, $client_id)
{
  $adj_id    = (int) $adj_id;
  $client_id = (int) $client_id;

  $adj = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM billing_balance_adjustment WHERE id='$adj_id' LIMIT 1"));
  if (!$adj) {
    return;
  }

  $cmem     = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM members WHERE member_id='$client_id' LIMIT 1")) ?: [];
  $bus_name = $cmem['bus_name'] ?? '';
  $dba      = $cmem['dba'] ?? '';
  if ($bus_name !== '') {
    $name = $bus_name;
  } elseif ($dba !== '') {
    $name = $dba;
  } else {
    $name = trim(($cmem['name'] ?? '') . ' ' . ($cmem['lname'] ?? ''));
  }
  $isBiz    = ($bus_name !== '' || $dba !== '');
  $email    = $isBiz ? ($cmem['prm_email'] ?? '') : ($cmem['sec_email'] ?? '');
  $address  = $isBiz ? ($cmem['address'] ?? '') : ($cmem['hstreet'] ?? '');
  $address1 = ($cmem['hcity'] ?? '') . ', ' . ($cmem['hstate'] ?? '') . ', ' . ($cmem['hzip'] ?? '');
  $phone    = $cmem['cell_ph'] ?? '';

  $typeLabel   = $adj['adjustment_type'] === 'CREDIT' ? 'CREDIT (Client Sent Excess / Credit Balance)' : 'DEBIT (Manual Charge / Amount Owed)';
  $invoiceLine = $adj['invoice_id'] ? 'INV-' . (int) $adj['invoice_id'] : 'N/A';
  $adjDate     = date('m/d/Y', strtotime($adj['adjustment_date']));

  $content = '
  <style>
    .invoice-box{position:relative;height:99%;width:100%;margin:auto;border:1px solid #eee;box-shadow:0 0 10px rgba(0,0,0,0.15);font-size:16px;line-height:24px;color:#555;}
    .invoice-box table{width:100%;line-height:inherit;text-align:left;border-collapse:collapse;}
    .invoice-box table td{padding:5px;vertical-align:top;}
    .invoice-box table tr td:nth-child(2){text-align:right;}
    .invoice-box table tr.heading td{background:#8b742a;border-bottom:1px solid #ddd;font-weight:bold;color:white;font-size:22px;}
    .invoice-box table tr.details td{border-bottom:1px solid #eee;}
    .invoice-box table tr.total td:nth-child(2){border-top:2px solid #eee;font-weight:bold;}
  </style>
  <div class="invoice-box">
    <table>
      <tr class="top"><td colspan="2"><table><tr>
        <td class="title"><img src="http://192.168.15.29/storage/uploads/logo/logo-dark.png" style="width:60%;max-width:300px;"></td>
        <td>
          Adjustment #: ' . $adj_id . '<br>
          Date: ' . $adjDate . '<br>
        </td>
      </tr></table></td></tr>
      <tr class="information"><td colspan="2"><table><tr>
        <td>From:<br>Syed Professional Services<br>1012 Coney Island Ave<br>Brooklyn, NY 11230<br>Phone: (718) 859-5392<br>Fax: (718) 859-2237<br>invoice@syedpro.com</td>
        <td><b>To:</b> <br>' . htmlspecialchars($name) . '<br>' . htmlspecialchars($address) . '<br>' . htmlspecialchars($address1) . '<br>' . htmlspecialchars($phone) . '<br>' . htmlspecialchars($email) . '</td>
      </tr></table></td></tr>
      <tr class="heading"><td>Balance Adjustment Receipt</td><td></td></tr>
      <tr class="details"><td><b>Type:</b> ' . $typeLabel . '</td><td></td></tr>
      <tr class="details"><td><b>Payment Method:</b> ' . htmlspecialchars($adj['payment_method']) . '</td><td></td></tr>
      <tr class="details"><td><b>Linked Invoice:</b> ' . $invoiceLine . '</td><td></td></tr>
      <tr class="details"><td colspan="2"><b>Description:</b> ' . nl2br(htmlspecialchars($adj['description'])) . '</td></tr>
      <tr class="total"><td></td><td>Total Sent by Client: $' . number_format((float) $adj['total_sent_by_client'], 2) . '</td></tr>
      <tr class="total"><td></td><td>' . ($adj['adjustment_type'] === 'CREDIT' ? 'Credit Amount' : 'Debit Amount') . ': $' . number_format((float) $adj['total_amount'], 2) . '</td></tr>
    </table>
  </div>';

  $dompdf = new Dompdf(array('enable_remote' => true));
  $dompdf->loadHtml($content);
  $dompdf->render();
  file_put_contents(balance_adjustment_pdf_path($adj_id, $client_id), $dompdf->output());
}

$msg = '';
$drows = '';
$show_payment_email_popup = false;
$payment_email_invoice_id = 0;

$bill_query = mysqli_query($con, "Select * from billing_info Where mem_id=$client_id");
$MBnumrows = mysqli_num_rows($bill_query);
while ($fetch_bill_info = mysqli_fetch_array($bill_query)) {
  $BMonths = $fetch_bill_info["bil_cyc_id"];
  $BiD = $fetch_bill_info["billing_id"];
}

$user_full_name = $_SESSION['fullname'];
$user_initials = getInitials($user_full_name);
$emp_id_initials = getInitials($user_full_name) . '(' . $userid . ')';
$msg = '';

if (isset($_POST["createinvoice"])) {
  $msg = '';
  $variable = '';
  $drow = '';

  $time = time();
  $m = date('m');
  $curDate = date('m/d/Y');
  $curDateMySQL = date('m/d/Y');
  $currentMonth = date("Y-m");

  $services = $_POST['service'] ?? [];
  $price = $_POST['unit_price'] ?? [];
  $description = $_POST['description'] ?? [];

  if (!empty($services)) {

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

  $qr_image_path = 'http://192.168.15.29/storage/uploads/logo/qr.png';

  if (strpos($sub_total, '.') === false) {
    $sub_total = sprintf("%.2f", $sub_total);
  }
  if (strpos($grand_total, '.') === false) {
    $grand_total = sprintf("%.2f", $grand_total);
  }

  /* 1.  insert row (no filelink yet) */
  $insert_billing = "INSERT INTO billing_invoice(mem_id, billing_id, temp_id, invoice_date, filelink,
                                               due_date, total_amount, paid_amount, discount, balance,
                                               invoice_status, payment_type, payment_details, paid_date,
                                               billing_cycle, service_provider_id, is_ach, employee_initials)
                     VALUES ('$client_id','0','0','$invoice_date','',
                             '$duedate','$sub_total','0','$discount','$grand_total',
                             '1','0','0','0000-00-00','$billing_month',
                             '$service_provider_id','$is_ach','$emp_id_initials')";
  mysqli_query($con, $insert_billing);
  $invoice_no = mysqli_insert_id($con);          // <-- invoice-id we need

  /* 2.  build filename: inv-{invoice_id}-MemID-{client_id}-{US date} */
  $US_Date = date('m-d-Y');                      // US format
  $PDFFileN = "INV-{$invoice_no}-MemID-{$client_id}-{$US_Date}.pdf";
  $Filename = "invoices/" . $PDFFileN;

  /* 3.  client data (unchanged) */
  $client_query = mysqli_query($con, "SELECT * FROM members where member_id='$client_id'");
  while ($fetch_client = mysqli_fetch_array($client_query)) {
    $bus_name = $fetch_client['bus_name'];
    $dba = $fetch_client['dba'];
    if ($bus_name != '') {
      $name = $bus_name;
      $address = $fetch_client['address'];
      $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
      $email = $fetch_client['prm_email'];
    } else if ($dba != '') {
      $name = $dba;
      $address = $fetch_client['address'];
      $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
      $email = $fetch_client['prm_email'];
    } else {
      $name = $fetch_client['name'] . " " . $fetch_client['lname'];
      $email = $fetch_client['sec_email'];
      $address = $fetch_client['hstreet'];
      $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
      $phone = $fetch_client['phone'];
    }
    $phone = $fetch_client['cell_ph'];
  }

  /* 4.  items loop (unchanged) */
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

  /* 5.  due-date format tweak (unchanged) */
  $originalFormat = 'Y-m-d';
  $desiredFormat = 'm/d/Y';
  $dateTime = DateTime::createFromFormat($originalFormat, $duedate);
  if ($dateTime) {
    $duedate = $dateTime->format($desiredFormat);
  }

  /* 6.  build HTML content (same as yours) */
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
  line-height: 30px;
  color: #333;
}
.invoice-box table tr.information table td {
  padding-bottom: 30px;
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
  .bank-details {
  font-size: 14px;
  line-height: 1.6;
  margin-top: 10px;
}
.bank-details strong {
  color: #333;
}
</style>
      <div class="invoice-box">
         <table style="width:100%">
            <tr class="top">
               <td colspan="2">
                  <table style="width:100%">
                     <tr>
                        <td class="title">
                           <img src="http://192.168.15.29/storage/uploads/logo/logo-dark.png" alt="Company logo " style="width: 60%; max-width: 300px" />
                        </td>
                        <td>
                           Invoice #: ' . $invoice_no . '<br />
                           By: ' . $user_initials . '(' . $userid . ')<br/>
                           Created: ' . $curDateMySQL . '<br />
                           Due: ' . $duedate . '<br />
                           <img src="http://192.168.15.29/storage/uploads/logo/unpaid.jpg" alt="Company logo" style="width: 30%;margin-top:12px; max-width: 300px" />
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
                           ' . $name . '<br />
                           ' . $address . '<br />
                           ' . $address1 . '<br />
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
            </tr>
           <tr class="details">
  <td><b>Service Provider:</b> ' . $service_provider_name . ' (' . $service_provider_id . ')</td>
</tr>
            ' . $drows . '
             <tr class="total">
                    <td></td>
                    <td style="font-weight:bold;">Sub Total: $' . $sub_total . '</td>
                </tr>' . $discountRow . '
             <tr class="total">
                    <td></td>
                    <td style="font-weight:bold;">Grand Total: $' . $grand_total . '</td>
                </tr>
                <tr class="details">
         <td>
            <div class="bank-details">
               <strong>Zelle Payment Info:</strong> info@syedpro.com
            </div>
         </td>
           
      </tr>
            </table>
           <div class="footer-note">
          <p>A late fee of $35 per month will be applied to any outstanding balance not paid within the due date.
          <br>We accept the payment via Zelle,Credit or debit Card,Cash and cheque.
          </p>
          <h4><u>Thank you for choosing Syed Professional Services</u></h4>
</div>
            </div>';

  /* 7.  generate PDF */
  $dompdf = new Dompdf(array('enable_remote' => true));
  $dompdf->loadHtml($content);
  $dompdf->render();
  file_put_contents($Filename, $dompdf->output());

  /* 8.  update row with final filename */
  mysqli_query($con, "UPDATE billing_invoice
                     SET filelink = '" . mysqli_real_escape_string($con, $PDFFileN) . "'
                     WHERE invoice_id = $invoice_no");

  /* 9.  feedback / logging */
  if (mysqli_affected_rows($con) > 0) {
    logActivity($con, $userid, 'CREATE', 'billing_invoice', $invoice_no, "Created new invoice #{$invoice_no} for client ID: {$client_id} with a total of \${$grand_total}.");
    $msg .= "<div class='alert alert-success'>Invoice created successfully! ✅</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Failed to create invoice. ❌ Please try again.</div>";
  }

  } else {
    $msg .= "<div class='alert alert-danger'>Please add at least one service before submitting the invoice.</div>";
  }
}





if (isset($_POST['update_invoice'])) {
  $invoice_id = $_POST['invoice_id'];
  $user_id = $_SESSION["user_id"];
  $is_ach = !empty($_POST['edit_mark_ach']) ? 1 : 0;
  $description = $_POST['edit_payment_details'] ?? '';

  /* admin-only header fields: By (creator) / Created date / Payment method */
  $employee_initials_edit = isset($_POST['edit_employee_initials']) ? trim($_POST['edit_employee_initials']) : '';
  $invoice_date_edit = !empty($_POST['edit_invoice_date']) ? date('m/d/Y H:i:s', strtotime($_POST['edit_invoice_date'])) : '';
  $payment_method_edit = isset($_POST['edit_payment_method']) ? trim($_POST['edit_payment_method']) : null;

  /* ----------  read the OLD row + items BEFORE we overwrite them  ---------- */
  $oldRow = mysqli_fetch_assoc(
    mysqli_query($con, "SELECT * FROM billing_invoice WHERE invoice_id='" . (int) $invoice_id . "' LIMIT 1")
  );

  /* old payment method (from the most recent payment) for change logging */
  $oldPmRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT payment_method FROM billing_payment_history WHERE inv_id='" . (int) $invoice_id . "' ORDER BY his_id DESC LIMIT 1"));
  $oldPaymentMethod = $oldPmRow['payment_method'] ?? '';

  $oldItems = [];
  $oiQ = mysqli_query($con, "SELECT item_description, item_sub_description, unit_price FROM billing_invoice_items WHERE inv_id='" . (int) $invoice_id . "' ORDER BY inv_item_id ASC");
  while ($oi = mysqli_fetch_assoc($oiQ)) {
    $oldItems[] = $oi;
  }

  /* ----------  field-level permissions: use posted value only when allowed, else keep stored ---------- */
  $due_date            = $cap_due              ? ($_POST['edit_due_date'] ?? ($oldRow['due_date'] ?? '')) : ($oldRow['due_date'] ?? '');
  $discount            = $cap_discount         ? ($_POST['edit_discount'] ?? ($oldRow['discount'] ?? '0.00')) : ($oldRow['discount'] ?? '0.00');
  $paid_amount         = $cap_paid_amount      ? ($_POST['edit_paid_amount'] ?? ($oldRow['paid_amount'] ?? '0.00')) : ($oldRow['paid_amount'] ?? '0.00');
  $service_provider_id = $cap_service_provider ? ($_POST['edit_service_provider'] ?? ($oldRow['service_provider_id'] ?? 0)) : ($oldRow['service_provider_id'] ?? 0);
  if ($cap_paid_date) {
    $paid_date = !empty($_POST['edit_paid_date']) ? date('Y-m-d', strtotime($_POST['edit_paid_date'])) : '0000-00-00';
  } else {
    $paid_date = $oldRow['paid_date'] ?? '0000-00-00';
  }

  /* By / Created / Payment method stay admin-only regardless of field permissions */
  if ($is_admin) {
    $employee_initials_final = $employee_initials_edit !== '' ? $employee_initials_edit : ($oldRow['employee_initials'] ?? '');
    $invoice_date_final = $invoice_date_edit !== '' ? $invoice_date_edit : ($oldRow['invoice_date'] ?? '');
  } else {
    $employee_initials_final = $oldRow['employee_initials'] ?? '';
    $invoice_date_final = $oldRow['invoice_date'] ?? '';
    $payment_method_edit = null; // do not touch the payment method
  }

  /* ----------  line items: honor posted values per service / description / amount permission ---------- */
  $postNames  = isset($_POST['service']) ? $_POST['service'] : [];
  $postDescs  = isset($_POST['description']) ? $_POST['description'] : [];
  $postPrices = isset($_POST['unit_price']) ? $_POST['unit_price'] : [];

  $finalItems = [];
  if ($cap_service) {
    /* may add/remove rows and rename services */
    $cnt = count($postNames);
    for ($i = 0; $i < $cnt; $i++) {
      $nm = trim($postNames[$i] ?? '');
      $ds = $cap_service_desc   ? trim($postDescs[$i] ?? '')      : trim($oldItems[$i]['item_sub_description'] ?? '');
      $pr = $cap_service_amount ? (float) ($postPrices[$i] ?? 0)  : (float) ($oldItems[$i]['unit_price'] ?? 0);
      if ($nm === '' && $pr == 0) {
        continue;
      }
      $finalItems[] = ['name' => $nm, 'desc' => $ds, 'price' => $pr];
    }
  } else {
    /* structure locked to existing rows; only description / amount may change (matched by row) */
    foreach ($oldItems as $i => $oi) {
      $nm = $oi['item_description'];
      $ds = $cap_service_desc   ? trim($postDescs[$i] ?? $oi['item_sub_description']) : $oi['item_sub_description'];
      $pr = $cap_service_amount ? (float) ($postPrices[$i] ?? $oi['unit_price'])      : (float) $oi['unit_price'];
      $finalItems[] = ['name' => $nm, 'desc' => $ds, 'price' => $pr];
    }
  }

  /* totals are derived from the line items (subtotal) minus discount, minus paid */
  $total_amount = 0;
  foreach ($finalItems as $it) {
    $total_amount += $it['price'];
  }
  $total_amount = number_format($total_amount, 2, '.', '');
  $balance = number_format(((float) $total_amount - (float) $discount) - (float) $paid_amount, 2, '.', '');

  /* service-provider name (initials) */
  $service_provider_name = '';
  if (!empty($service_provider_id)) {
    $sp_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . mysqli_real_escape_string($con, $service_provider_id) . "'");
    if ($sp_result = mysqli_fetch_assoc($sp_query)) {
      $service_provider_name = getInitials($sp_result['user_name']);
    }
  }

  $qr_image_path = 'http://192.168.15.29/storage/uploads/logo/qr.png';

  /* payment status */
  if ($paid_amount >= $total_amount)
    $payment_status = 3;
  elseif ($paid_amount > 0)
    $payment_status = 2;
  else
    $payment_status = 1;

  /* ----------  2.  update the invoice  --------------------------------*/
  $update_query = "UPDATE billing_invoice SET
                     due_date='$due_date',
                     paid_date='$paid_date',
                     total_amount='$total_amount',
                     paid_amount='$paid_amount',
                     balance='$balance',
                     invoice_status='$payment_status',
                     discount='$discount',
                     payment_details='" . mysqli_real_escape_string($con, $description) . "',
                     service_provider_id='" . (int) $service_provider_id . "',
                     is_ach='$is_ach',
                     employee_initials='" . mysqli_real_escape_string($con, $employee_initials_final) . "',
                     invoice_date='" . mysqli_real_escape_string($con, $invoice_date_final) . "',
                     last_edited_by='$emp_id_initials'
                   WHERE invoice_id='" . (int) $invoice_id . "'";

  if (mysqli_query($con, $update_query)) {

    /* ---- PDF re-generation (your existing code) --------------------- */
    /* "Created" reflects the (possibly edited) invoice creation date, not today */
    $curDateMySQL = $invoice_date_final !== '' ? date('m/d/Y', strtotime($invoice_date_final)) : date('m/d/Y');
    $US_Date = date('m-d-Y');
    $PDFFileN = "INV-{$invoice_id}-MemID-{$client_id}-{$US_Date}.pdf";
    $Filename = "invoices/" . $PDFFileN;

    $client_query = mysqli_query($con, "SELECT * FROM members where member_id='$client_id'");
    while ($fetch_client = mysqli_fetch_array($client_query)) {
      $bus_name = $fetch_client['bus_name'];
      $dba = $fetch_client['dba'];
      if ($bus_name != '') {
        $name = $bus_name;
        $address = $fetch_client['address'];
        $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
        $email = $fetch_client['prm_email'];
      } else if ($dba != '') {
        $name = $dba;
        $address = $fetch_client['address'];
        $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
        $email = $fetch_client['prm_email'];
      } else {
        $name = $fetch_client['name'] . " " . $fetch_client['lname'];
        $email = $fetch_client['sec_email'];
        $address = $fetch_client['hstreet'];
        $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
        $phone = $fetch_client['phone'];
      }
      $phone = $fetch_client['cell_ph'];
    }

    $creatorRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT employee_initials FROM billing_invoice WHERE invoice_id='" . (int) $invoice_id . "' LIMIT 1"));
    $original_creator = $creatorRow['employee_initials'];
    $current_editor = $emp_id_initials;

    /* payment method is recorded per-payment; update the most recent payment record */
    if ($payment_method_edit !== null && $payment_method_edit !== '') {
      $latestPh = mysqli_fetch_assoc(mysqli_query($con, "SELECT his_id FROM billing_payment_history WHERE inv_id='" . (int) $invoice_id . "' ORDER BY his_id DESC LIMIT 1"));
      if ($latestPh) {
        mysqli_query($con, "UPDATE billing_payment_history SET payment_method='" . mysqli_real_escape_string($con, $payment_method_edit) . "' WHERE his_id='" . (int) $latestPh['his_id'] . "'");
      }
    }
    /* payment method to print on the invoice (reflects the edit above) */
    $pmRowPdf = mysqli_fetch_assoc(mysqli_query($con, "SELECT payment_method FROM billing_payment_history WHERE inv_id='" . (int) $invoice_id . "' ORDER BY his_id DESC LIMIT 1"));
    $payment_method_pdf = $pmRowPdf['payment_method'] ?? '';

    /* ---- replace line items with the permitted/edited set ---------- */
    mysqli_query($con, "DELETE FROM billing_invoice_items WHERE inv_id='" . (int) $invoice_id . "'");
    $item_ts = date("Y-m-d h:i:s");
    foreach ($finalItems as $it) {
      $iName = mysqli_real_escape_string($con, $it['name']);
      $iDesc = mysqli_real_escape_string($con, $it['desc']);
      $iPrice = number_format((float) $it['price'], 2, '.', '');
      mysqli_query($con, "INSERT INTO `billing_invoice_items`(`inv_id`, `temp_id`, `billing_id`, `mem_id`, `item_description`, `item_sub_description`, `unit_price`, `quantity`, `total_price`, `date_added`, `user_id`, `item_date`) VALUES ('" . (int) $invoice_id . "', 0, 0, '" . (int) $client_id . "', '$iName', '$iDesc', '$iPrice', '1', '$iPrice', '$item_ts', '" . (int) $userid . "', '$item_ts')");
    }

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
      ? "{$status_text}: " . date('m/d/Y', strtotime($paid_date)) . "<br />" : '';

    /* payment method line (only shown when a payment exists) */
    $payment_method_html = ($payment_method_pdf !== '')
      ? "<br><b>Payment Method(s):</b> " . htmlspecialchars($payment_method_pdf) : '';

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
  line-height: 30px;
  color: #333;
}
.invoice-box table tr.information table td {
  padding-bottom: 30px;
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
  .bank-details {
  font-size: 14px;
  line-height: 1.6;
  margin-top: 10px;
}
.bank-details strong {
  color: #333;
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
                            <img src="{$image_src}" alt="Company logo" style="width: 30%;margin-top:12px; max-width: 300px" /> 
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
                            {$name}.<br />
                            {$address}<br />
                            {$address1}<br />
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
        <tr>
            <td colspan="2"><b>Description:</b> {$description}</td> 
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
                <tr class="details">
            <td>
                <div class="bank-details">
              <strong>Zelle Payment Info:</strong> info@syedpro.com{$payment_method_html}
            </div>
            </td>
              
        </tr>
    </table>
    <div class="footer-note">
        <p>A late fee of \$35 per month will be applied to any outstanding balance not paid within the due date.
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
    mysqli_query($con, "UPDATE billing_invoice SET filelink='" . mysqli_real_escape_string($con, $PDFFileN) . "' WHERE invoice_id='" . (int) $invoice_id . "'");

    /* ----------  3.  detailed change-log  ---------------------------- */
    $new = [
      'due_date' => $due_date,
      'paid_date' => $paid_date,
      'invoice_date' => $invoice_date_final,
      'employee_initials' => $employee_initials_final,
      'total_amount' => $total_amount,
      'paid_amount' => $paid_amount,
      'balance' => $balance,
      'discount' => $discount,
      'service_provider_id' => $service_provider_id,
      'payment_details' => $description,
    ];

    $changed = [];

    foreach ($new as $k => $v) {
      $oldVal = $oldRow[$k] ?? '';

      if ((string) $v !== (string) $oldVal) {
        $labelMap = ['payment_details' => 'Invoice Description', 'invoice_date' => 'Created Date', 'employee_initials' => 'Created By'];
        $label = $labelMap[$k] ?? ucwords(str_replace('_', ' ', $k));

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

    /* ---- line-item (service) changes ------------------------------
     * Invoice items only store the service name, so the service ID is looked
     * up from the memberships table by name for the activity log.
     */
    $svcIdByName = [];
    $msQ = mysqli_query($con, "SELECT membership_id, membership_name FROM memberships");
    while ($ms = mysqli_fetch_assoc($msQ)) {
      $svcIdByName[strtolower(trim($ms['membership_name']))] = $ms['membership_id'];
    }
    $svcLabel = function ($name) use ($svcIdByName) {
      $id = $svcIdByName[strtolower(trim($name))] ?? null;
      return "'" . trim($name) . "'" . ($id !== null ? " (Service ID: {$id})" : " (Service ID: N/A)");
    };

    $oldByName = [];
    foreach ($oldItems as $oi) {
      $oldByName[strtolower(trim($oi['item_description']))] = $oi;
    }
    $newByName = [];
    foreach ($finalItems as $ni) {
      $newByName[strtolower(trim($ni['name']))] = $ni;
    }

    foreach ($newByName as $key => $ni) {
      if (!isset($oldByName[$key])) {
        $changed[] = "Service added " . $svcLabel($ni['name']) . " at \$" . number_format((float) $ni['price'], 2);
        continue;
      }
      $oi = $oldByName[$key];
      if ((float) $oi['unit_price'] !== (float) $ni['price']) {
        $changed[] = "Service " . $svcLabel($ni['name']) . " price changes \$"
          . number_format((float) $oi['unit_price'], 2) . " To \$" . number_format((float) $ni['price'], 2);
      }
      if (trim((string) $oi['item_sub_description']) !== trim((string) $ni['desc'])) {
        $changed[] = "Service " . $svcLabel($ni['name']) . " description changes "
          . trim((string) $oi['item_sub_description']) . " To " . trim((string) $ni['desc']);
      }
    }
    foreach ($oldByName as $key => $oi) {
      if (!isset($newByName[$key])) {
        $changed[] = "Service removed " . $svcLabel($oi['item_description']) . " at \$" . number_format((float) $oi['unit_price'], 2);
      }
    }

    // Payment method lives in the payment history, not on the invoice row.
    // Only logged when a non-empty value actually changed (a blank field is ignored, never wiped).
    if ($payment_method_edit !== null && $payment_method_edit !== '' && (string) $payment_method_edit !== (string) $oldPaymentMethod) {
      $changed[] = "Payment Method changes {$oldPaymentMethod} To {$payment_method_edit}";
    }

    // Combine changes for logging
    $changeText = empty($changed) ? 'no fields changed' : implode(' | ', $changed);

    // Log activity
    logActivity(
      $con,
      $userid,
      'UPDATE',
      'billing_invoice',
      $invoice_id,
      "Edited invoice #{$invoice_id} – {$changeText}"
    );

    $msg .= "<div class='alert alert-success'>Invoice updated successfully.</div>";


  } else {
    $msg .= "<div class='alert alert-danger'>Error updating invoice: " . mysqli_error($con) . "</div>";
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
  $invoice_query = mysqli_query($con, "SELECT service_provider_id FROM billing_invoice WHERE invoice_id='$invoice_id'");
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

  $qr_image_path = 'http://192.168.15.29/storage/uploads/logo/qr.png';

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
  $Filename = "invoices/" . $PDFFileN;

  // Get client details
  $client_query = mysqli_query($con, "SELECT * FROM members where member_id='$memberid'");
  while ($fetch_client = mysqli_fetch_array($client_query)) {
    $bus_name = $fetch_client['bus_name'];
    $dba = $fetch_client['dba'];
    if ($bus_name != '') {
      $name = $bus_name;
      $address = $fetch_client['address'];
      $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
      $email = $fetch_client['prm_email'];
    } else if ($dba != '') {
      $name = $dba;
      $address = $fetch_client['address'];
      $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
      $email = $fetch_client['prm_email'];
    } else {
      $name = $fetch_client['name'] . " " . $fetch_client['lname'];
      $email = $fetch_client['sec_email'];
      $address = $fetch_client['hstreet'];
      $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
      $phone = $fetch_client['phone'];
    }
    $phone = $fetch_client['cell_ph'];
  }

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
  $queryy = "UPDATE billing_invoice SET 
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
          VALUES('$memberid','$invoice_id','$recievedamount','$newbalance','$description','$date2','$emp_id_initials', '$payment_methods_str')";

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
      .bank-details {
      font-size: 14px;
      line-height: 1.6;
      margin-top: 10px;
      }
      .bank-details strong {
      color: #333;
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
                    <td colspan="2"><b>Description:</b> ' . htmlspecialchars($description) . '</td>
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
            <td>
                <div class="bank-details">
                   <strong>Zelle Payment Info:</strong> info@syedpro.com </br>
                    <b>Payment Method(s):</b> ' . $payment_methods_str . '
                </div>
            </td>
              
        </tr>
            </table>
            <div class="footer-note">
                <p>A late fee of $35 per month will be applied to any outstanding balance not paid within the due date.
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

    logActivity($con, $userid, 'UPDATE', 'billing_invoice', $invoice_id, "Added payment of \${$newpaid} to invoice #{$invoice_id} for client ID: {$memberid}. New balance: \${$newbalance}.");
    $msg .= "<div class='alert alert-success'>Payment Has Been Added.</div>";
    $show_payment_email_popup = true;
    $payment_email_invoice_id = (int) $invoice_id;
  } else {
    $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . mysqli_error($con);
  }
}



if (isset($_POST['delete_invoice'])) {
  $msg = '';

  $invoice_id = $_POST['invoice_id'];

  $queryy = "DELETE FROM billing_invoice where invoice_id='$invoice_id'";
  $insert_query = mysqli_query($con, $queryy);

  if ($insert_query) {
    logActivity($con, $userid, 'DELETE', 'billing_invoice', $invoice_id, "Deleted invoice #{$invoice_id} for Member ID: {$client_id}.");
    $msg .= "<div class='alert alert-success'>Record Been Deleted Successfully.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
  }
}

/* delete a single recorded payment and recalculate the invoice */
if (isset($_POST['delete_payment'])) {
  $msg = '';
  $his_id = (int) $_POST['his_id'];
  $invoice_id = (int) $_POST['invoice_id'];

  $payRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM billing_payment_history WHERE his_id='$his_id' AND inv_id='$invoice_id' LIMIT 1"));

  if (!$payRow) {
    $msg .= "<div class='alert alert-danger'>Payment not found.</div>";
  } else {
    $deletedPaid = (float) $payRow['paid'];

    mysqli_query($con, "DELETE FROM billing_payment_history WHERE his_id='$his_id'");

    /* recalculate the invoice from its current values minus the deleted payment */
    $invRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM billing_invoice WHERE invoice_id='$invoice_id' LIMIT 1"));
    if ($invRow) {
      $total = (float) $invRow['total_amount'];
      $disc  = (float) $invRow['discount'];
      $newPaid = (float) $invRow['paid_amount'] - $deletedPaid;
      if ($newPaid < 0) {
        $newPaid = 0;
      }
      $newBalance = $total - $disc - $newPaid;

      if ($newPaid >= $total && $total > 0) {
        $newStatus = 3;
      } elseif ($newPaid > 0) {
        $newStatus = 2;
      } else {
        $newStatus = 1;
      }

      /* paid_date follows the most recent remaining payment; cleared when none remain */
      $remain = mysqli_fetch_assoc(mysqli_query($con, "SELECT date FROM billing_payment_history WHERE inv_id='$invoice_id' ORDER BY his_id DESC LIMIT 1"));
      $newPaidDate = ($remain && !empty($remain['date']) && $remain['date'] != '0000-00-00') ? $remain['date'] : '0000-00-00';

      $newPaidF = number_format($newPaid, 2, '.', '');
      $newBalF  = number_format($newBalance, 2, '.', '');

      mysqli_query($con, "UPDATE billing_invoice SET paid_amount='$newPaidF', balance='$newBalF', invoice_status='$newStatus', paid_date='$newPaidDate' WHERE invoice_id='$invoice_id'");

      /* keep the saved PDF in sync with the new totals */
      regenerate_invoice_pdf($con, $invoice_id);

      logActivity($con, $userid, 'DELETE', 'billing_payment_history', $his_id, "Deleted payment of \${$payRow['paid']} (history #{$his_id}) from invoice #{$invoice_id}. New paid: \${$newPaidF}, balance: \${$newBalF}.");
      $msg .= "<div class='alert alert-success'>Payment deleted and invoice updated.</div>";
    } else {
      $msg .= "<div class='alert alert-danger'>Invoice not found.</div>";
    }
  }
}


$ach_payer = 0;
$res = mysqli_query($con, "SELECT ach_payer FROM members WHERE member_id = $client_id LIMIT 1");
if ($res && $row = mysqli_fetch_assoc($res)) {
  $ach_payer = (int) $row['ach_payer'];
}
if (isset($_POST['addcard'])) {
  $card_type = $_POST['card_type'];
  $card_name = $_POST['card_name'];
  $card_no = $_POST['card_no'];
  $card_exp_mo = $_POST['card_exp_mo'];
  $card_exp_yo = $_POST['card_exp_yo'];
  $card_cvv = $_POST['card_cvv'];
  $zip = $_POST['zip'];

  if ($card_type == 'others') {
    $card_type = $_POST['bankcardtype'];

    $queryy0 = "SELECT * FROM billing_card_type where card_type_name = '$card_type'";

    $res0 = mysqli_query($con, $queryy0);

    $rowscount0 = mysqli_num_rows($res0);

    if ($rowscount0 == 0) {
      $queryy1 = "SELECT MAX(card_type_id) as maxid FROM billing_card_type";
      $res1 = mysqli_query($con, $queryy1);
      $rows1 = mysqli_fetch_array($res1);
      $maxid = $rows1['maxid'];
      $maxid = $maxid + 1;

      $queryy2 = "INSERT INTO billing_card_type (card_type_id, card_type_name) VALUES ($maxid, '$card_type')";
      mysqli_query($con, $queryy2);

      $card_type = $maxid;
    }
  }

  $queryy = "INSERT INTO `billing_card`(`mem_id`, `card_type`, `card_onname`, `card_number`, `card_exp_month`, `card_exp_year`, `card_cvv`, `card_zip`, `user_id`) VALUES ('$client_id','$card_type','$card_name','$card_no','$card_exp_mo','$card_exp_yo','$card_cvv','$zip','$userid')";

  $insert_query = mysqli_query($con, $queryy);

  if ($insert_query) {
    $new_card_id = mysqli_insert_id($con);
    logActivity($con, $userid, 'CREATE', 'billing_card', $new_card_id, "Added a new card ending in " . substr($card_no, -4) . " for client ID: {$client_id}.");
    $msg .= "<div class='alert alert-success'>Card Has Been Added.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
  }
}

if (isset($_POST['delete_card'])) {
  $msg = '';

  $card_id = $_POST['card_id'];

  $queryy = "DELETE FROM billing_card where card_id='$card_id'";
  $insert_query = mysqli_query($con, $queryy);

  if ($insert_query) {
    logActivity($con, $userid, 'DELETE', 'billing_card', $card_id, "Deleted a card for client ID: {$client_id}.");
    $msg .= "<div class='alert alert-success'>Card Been Deleted Successfully.</div>";
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
      $_SESSION['bank_auth'] = 1;
      $_SESSION['bank_user_name'] = $bank_user_name;
      $_SESSION['bank_access_time'] = time();

      logActivity($con, $userid, 'LOGIN', 'bank_statements', $client_id, "User '{$user_bank}' accessed bank statements area for Client ID: {$client_id}.");

      $stmt->close();
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




if (isset($_POST['updatecard'])) {
  $card_id = $_POST['card_id'];
  $card_type = $_POST['card_type'];
  $card_name = $_POST['card_name'];
  $card_no = $_POST['card_no'];
  $card_exp_mo = $_POST['card_exp_mo'];
  $card_exp_yo = $_POST['card_exp_yo'];
  $card_cvv = $_POST['card_cvv'];
  $zip = $_POST['zip'];
  $userid = $_SESSION['user_id']; // Get user id of the person making the edit

  $insert_history_query = "INSERT INTO card_edit_history (card_id, edited_by, card_name, card_no, card_exp_month, card_exp_year, card_cvv, card_zip)
                           SELECT card_id, $userid, card_onname, card_number, card_exp_month, card_exp_year, card_cvv, card_zip
                           FROM billing_card
                           WHERE card_id = '$card_id'";

  mysqli_query($con, $insert_history_query);

  // Update the card details in the billing_card table
  $query = "UPDATE billing_card SET card_type='$card_type', card_onname='$card_name', card_number='$card_no', card_exp_month='$card_exp_mo', card_exp_year='$card_exp_yo', card_cvv='$card_cvv', card_zip='$zip' WHERE card_id='$card_id'";

  $result = mysqli_query($con, $query);

  if ($result) {
    logActivity($con, $userid, 'UPDATE', 'billing_card', $card_id, "Updated card information for card ID: {$card_id} belonging to client ID: {$client_id}.");
    $msg .= "<div class='alert alert-success'>Card Info Updated Successfully.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
  }
}



if (isset($_POST['addbank'])) {
  $bank_name = $_POST['bank_name'];
  $account_number = $_POST['account_number'];
  $routing_no = $_POST['routing_no'];
  $bank_user_name = $_POST['bank_user_name'];
  $bank_password = $_POST['bank_password'];

  $client_id = $_GET['id'];
  $addedby = $_SESSION["user_id"];

  $queryy = "INSERT INTO `bank_accounts`(`bank_name`, `account_no`, `routing_no`, `client_id`, `added_by`,`bank_user_name`,`bank_password`) VALUES ('$bank_name','$account_number','$routing_no','$client_id','$addedby','$bank_user_name','$bank_password')";

  $insert_query = mysqli_query($con, $queryy);

  if ($insert_query) {
    $new_bank_id = mysqli_insert_id($con);
    logActivity($con, $userid, 'CREATE', 'bank_accounts', $new_bank_id, "Added a new bank account for client ID: {$client_id}.");
    $msg .= "<div class='alert alert-success'>Bank Account Has Been Added.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
  }
}
if (isset($_POST['delete_bank'])) {
  $msg = '';

  $card_id = $_POST['card_id'];

  $queryy = "DELETE FROM bank_accounts where bank_id='$card_id'";
  $insert_query = mysqli_query($con, $queryy);

  if ($insert_query) {
    logActivity($con, $userid, 'DELETE', 'bank_accounts', $bank_id, "Deleted a bank account for client ID: {$client_id}.");
    $msg .= "<div class='alert alert-success'>Bank Account Been Deleted Successfully.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
  }
}

if (isset($_POST['add_invoice_comment'])) {
  $invoice_id = intval($_POST['invoice_id']);   // from the hidden input
  $member_id = intval($_GET['id']);            // client id from URL
  $comment = mysqli_real_escape_string($con, $_POST['invoice_comment']);
  $user_id = $_SESSION['user_id'];
  $time = date('Y-m-d H:i:s');

  $query = "INSERT INTO new_comments 
              (comment_detail, member_id, invoice_id, user_id, comment_time, comment_status, comment_updated_by)
              VALUES ('$comment', '$member_id', '$invoice_id', '$user_id', '$time', 0, '$user_id')";

  if (mysqli_query($con, $query)) {
    $new_comment_id = mysqli_insert_id($con);
    logActivity($con, $userid, 'CREATE', 'new_comments', $new_comment_id, "Added a comment to invoice #{$invoice_id} for client ID: {$member_id}.");
    $msg .= "<div class='alert alert-success'>Comment added successfully.</div>";
  } else {
    $msg .= "<div class='alert alert-danger'>Error adding comment: " . mysqli_error($con) . "</div>";
  }
}

if (isset($_POST['add_balance_adjustment'])) {
  $adj_type          = ($_POST['adjustment_type'] ?? '') === 'DEBIT' ? 'DEBIT' : 'CREDIT';
  $adj_total_amount  = (float) ($_POST['adjustment_total_amount'] ?? 0);
  $adj_sent_amount   = (float) ($_POST['adjustment_sent_amount'] ?? 0);
  $adj_method_raw    = trim($_POST['adjustment_payment_method'] ?? '');
  $adj_desc_raw      = trim($_POST['adjustment_description'] ?? '');
  $adj_method        = mysqli_real_escape_string($con, $adj_method_raw);
  $adj_desc          = mysqli_real_escape_string($con, $adj_desc_raw);
  $adj_invoice_id    = !empty($_POST['adjustment_invoice_id']) ? (int) $_POST['adjustment_invoice_id'] : 0;
  $adj_generate_pdf  = !empty($_POST['adjustment_generate_pdf']);

  $adj_date_obj = DateTime::createFromFormat('Y-m-d', trim($_POST['adjustment_date'] ?? ''));
  $adj_date     = $adj_date_obj ? $adj_date_obj->format('Y-m-d') : date('Y-m-d');

  if ($adj_total_amount > 0 && $adj_method_raw !== '') {
    $invoice_val = $adj_invoice_id > 0 ? "'$adj_invoice_id'" : 'NULL';
    $query = "INSERT INTO billing_balance_adjustment
                (mem_id, invoice_id, adjustment_type, total_amount, total_sent_by_client, payment_method, adjustment_date, description)
              VALUES
                ('$client_id', $invoice_val, '$adj_type', '$adj_total_amount', '$adj_sent_amount', '$adj_method', '$adj_date', '$adj_desc')";

    if (mysqli_query($con, $query)) {
      $new_adj_id = mysqli_insert_id($con);
      logActivity($con, $userid, 'CREATE', 'billing_balance_adjustment', $new_adj_id, "Added a $adj_type balance adjustment of \${$adj_total_amount} for client ID: {$client_id}.");

      /* a CREDIT linked to an invoice is applied immediately as a real payment on that invoice */
      if ($adj_type === 'CREDIT' && $adj_invoice_id > 0) {
        apply_balance_adjustment_to_invoice($con, $new_adj_id, $client_id, $adj_invoice_id, $adj_total_amount, $adj_date, $adj_method_raw, $adj_desc_raw, $emp_id_initials);
      }

      if ($adj_generate_pdf) {
        generate_balance_adjustment_pdf($con, $new_adj_id, $client_id);
      }

      $msg .= "<div class='alert alert-success'>Balance adjustment added successfully.</div>";
    } else {
      $msg .= "<div class='alert alert-danger'>Error adding balance adjustment: " . mysqli_error($con) . "</div>";
    }
  } else {
    $msg .= "<div class='alert alert-danger'>Please enter a valid amount and payment method.</div>";
  }
}

if (isset($_POST['update_balance_adjustment'])) {
  $upd_adj_id = (int) ($_POST['adjustment_id'] ?? 0);
  $existingAdj = $upd_adj_id > 0
    ? mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM billing_balance_adjustment WHERE id='$upd_adj_id' AND mem_id='$client_id' AND deleted_at IS NULL LIMIT 1"))
    : null;

  if (!$is_admin) {
    $msg .= "<div class='alert alert-danger'>Only admins can edit balance adjustments.</div>";
  } elseif (!$existingAdj) {
    $msg .= "<div class='alert alert-danger'>Balance adjustment not found.</div>";
  } else {
    $adj_type          = ($_POST['adjustment_type'] ?? '') === 'DEBIT' ? 'DEBIT' : 'CREDIT';
    $adj_total_amount  = (float) ($_POST['adjustment_total_amount'] ?? 0);
    $adj_sent_amount   = (float) ($_POST['adjustment_sent_amount'] ?? 0);
    $adj_method_raw    = trim($_POST['adjustment_payment_method'] ?? '');
    $adj_desc_raw      = trim($_POST['adjustment_description'] ?? '');
    $adj_method        = mysqli_real_escape_string($con, $adj_method_raw);
    $adj_desc          = mysqli_real_escape_string($con, $adj_desc_raw);
    $adj_invoice_id    = !empty($_POST['adjustment_invoice_id']) ? (int) $_POST['adjustment_invoice_id'] : 0;
    $adj_generate_pdf  = !empty($_POST['adjustment_generate_pdf']);

    $adj_date_obj = DateTime::createFromFormat('Y-m-d', trim($_POST['adjustment_date'] ?? ''));
    $adj_date     = $adj_date_obj ? $adj_date_obj->format('Y-m-d') : date('Y-m-d');

    if ($adj_total_amount > 0 && $adj_method_raw !== '') {
      /* undo whatever this adjustment previously applied to an invoice, then save the new values and re-apply if applicable */
      revert_balance_adjustment_payment($con, $upd_adj_id);

      $invoice_val = $adj_invoice_id > 0 ? "'$adj_invoice_id'" : 'NULL';
      $query = "UPDATE billing_balance_adjustment SET
                  invoice_id=$invoice_val,
                  adjustment_type='$adj_type',
                  total_amount='$adj_total_amount',
                  total_sent_by_client='$adj_sent_amount',
                  payment_method='$adj_method',
                  adjustment_date='$adj_date',
                  description='$adj_desc'
                WHERE id='$upd_adj_id' AND mem_id='$client_id'";

      if (mysqli_query($con, $query)) {
        if ($adj_type === 'CREDIT' && $adj_invoice_id > 0) {
          apply_balance_adjustment_to_invoice($con, $upd_adj_id, $client_id, $adj_invoice_id, $adj_total_amount, $adj_date, $adj_method_raw, $adj_desc_raw, $emp_id_initials);
        }

        if ($adj_generate_pdf) {
          generate_balance_adjustment_pdf($con, $upd_adj_id, $client_id);
        }

        logActivity($con, $userid, 'UPDATE', 'billing_balance_adjustment', $upd_adj_id, "Edited balance adjustment #{$upd_adj_id} for client ID: {$client_id}.");
        $msg .= "<div class='alert alert-success'>Balance adjustment updated successfully.</div>";
      } else {
        $msg .= "<div class='alert alert-danger'>Error updating balance adjustment: " . mysqli_error($con) . "</div>";
      }
    } else {
      $msg .= "<div class='alert alert-danger'>Please enter a valid amount and payment method.</div>";
    }
  }
}

if (isset($_POST['delete_balance_adjustment'])) {
  $del_adj_id = (int) ($_POST['adjustment_id'] ?? 0);
  if ($del_adj_id > 0) {
    revert_balance_adjustment_payment($con, $del_adj_id);
    mysqli_query($con, "UPDATE billing_balance_adjustment SET deleted_at = NOW() WHERE id='$del_adj_id' AND mem_id='$client_id'");
    logActivity($con, $userid, 'DELETE', 'billing_balance_adjustment', $del_adj_id, "Removed balance adjustment #{$del_adj_id} for client ID: {$client_id}.");
    $msg .= "<div class='alert alert-success'>Balance adjustment removed.</div>";
  }
}


$client_settings = mysqli_fetch_assoc(mysqli_query($con, "
    SELECT sms_enabled, email_enabled 
    FROM client_button_settings 
    WHERE member_id = '$client_id' 
    LIMIT 1
"));

// If no row exists, treat as "not set" and show message
if (!$client_settings) {
  $sms_enabled = 0;
  $email_enabled = 0;
  $prefs_missing = true;
} else {
  $sms_enabled = (int) ($client_settings['sms_enabled'] ?? 0);
  $email_enabled = (int) ($client_settings['email_enabled'] ?? 0);
  $prefs_missing = false;
}
$sms_checked = ($client_settings && (int) $client_settings['sms_enabled'] === 1) ? "checked" : "";
$email_checked = ($client_settings && (int) $client_settings['email_enabled'] === 1) ? "checked" : "";

$permQuery = mysqli_query($con, "SELECT * FROM user_permissions WHERE user_id = '$userid' LIMIT 1");
$perms = mysqli_fetch_assoc($permQuery);
if (!$perms) {
  $perms = ['view_personal' => 'No', 'view_company' => 'No'];
}

function removeNegativeYear($date)
{
  return str_replace(', -0001', '', $date);
}

?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
  <title>Syed Professional Services - Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

  <?php include "includes/links.php"; ?>

  <style type="text/css">
    .text-sm {
      font-size: 13px !important;
      margin-bottom: 5px;
    }

    .swal2-container {
      z-index: 20000 !important;
    }
  </style>
  <style>
    /* Page-scoped override: this page uses navy as its brand accent instead of the sitewide green (parity with client_details.php) */
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

    /* ----------  invoice-row hover highlight  ---------- */
    .table.datatable tbody tr:hover {
      background-color: #fff9c4 !important;
      /* soft yellow */
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

    .billing-tabs-subtitle {
      margin: 4px 0 0;
      font-size: 0.875rem;
      color: #6b7280;
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
      border-color: #272469;
      color: #1f2937;
      box-shadow: 0 6px 18px rgba(39, 36, 105, 0.12);
    }

    #invoiceBillingTabs .nav-link.active {
      background: linear-gradient(135deg, #272469 0%, #1f1d54 100%);
      border-color: #272469;
      color: #fff;
      box-shadow: 0 10px 20px rgba(39, 36, 105, 0.22);
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

    /* ─── Client Hero card (parity with client_details.php) ─── */
    .client-hero {
      background: #132748;
      border-radius: 18px;
      margin-bottom: 24px;
      color: #fff;
      box-shadow: 0 14px 34px rgba(15, 23, 42, .16);
      overflow: hidden;
      position: relative;
    }
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
    .client-hero-topbar .form-check-input:focus { box-shadow: 0 0 0 .15rem rgba(255, 255, 255, .24); }
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
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .04);
    }
    .ctb:hover  { transform: translateY(-1px); filter: brightness(1.05); color: inherit; }
    .ctb:disabled { opacity: .55; cursor: not-allowed; transform: none; filter: none; }
    .ctb i { font-size: 2rem; }
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
    .ctb-calendar:hover { color:#fff; background: transparent; }
    .ctb-calendar i { color: #fff; font-size: 1.75rem; }
    .ctb-red    { background: #dd2d19; color: #fff; }
    .ctb-red:hover { color:#fff; }
    .ctb-red i  { color: #fff; }

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
    .ch-avatar {
      width: 86px; height: 86px;
      border-radius: 50%;
      background: linear-gradient(135deg, #dcbf48 0%, #c6a02d 100%);
      display: flex; align-items: center; justify-content: center;
      color: #fff;
      font-size: 2.4rem;
      flex-shrink: 0;
      box-shadow: 0 10px 18px rgba(0, 0, 0, .18);
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
      color: rgba(255, 255, 255, .96);
      margin-bottom: 5px;
    }
    .ch-line span { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
    .ch-line i { opacity: .96; font-size: 1rem; flex-shrink: 0; }
    .ch-id-badge {
      background: transparent;
      padding: 0;
      border-radius: 0;
      font-size: inherit;
      font-weight: 400;
      letter-spacing: 0;
    }

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
      background: rgba(255, 255, 255, .08);
      color: #fff;
    }
    .ch-pill i { font-size: .82rem; }
    .ch-pill.status-active   { background: rgba(14, 159, 66, .16); color: #fff; border-color: #18d74f; }
    .ch-pill.status-inactive { background: rgba(220, 38, 38, .14); color: #fff; border-color: #ef4444; }
    .ch-pill.ach             { background: rgba(212, 171, 50, .14); color: #fff; border-color: #c89d16; }
    .ch-pill.freq            { background: rgba(114, 98, 198, .22); color: #fff; border-color: #d7d1ff; }
    .ch-pill.balance         { background: rgba(220, 38, 38, .14); color: #fff; border-color: #ff1616; }
    .ch-pill.balance.is-zero { background: rgba(255, 255, 255, .12); color: #e2e8f0; border-color: rgba(255, 255, 255, .24); }
    a.ch-pill.balance        { text-decoration: none; cursor: pointer; transition: background .15s ease, transform .15s ease; }
    a.ch-pill.balance:hover  { background: rgba(220, 38, 38, .28); transform: translateY(-1px); }
    .ch-pill.special         { background: rgba(124, 58, 237, .22); color: #fff; border-color: #b697ff; }
    .ch-pill.credit          { background: rgba(14, 159, 66, .16); color: #fff; border-color: #18d74f; }
    .ch-pill.debit           { background: rgba(234, 88, 12, .18); color: #fff; border-color: #f97316; }
    .ch-pill.debit.is-zero   { background: rgba(255, 255, 255, .12); color: #e2e8f0; border-color: rgba(255, 255, 255, .24); }

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
      box-shadow: 0 10px 26px rgba(0, 0, 0, .12);
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
  </style>
  <!-- <script src="ckeditor/ckeditor.js"></script> -->
  <!-- =====  CKEditor 5 – FREE classic build (full toolbar, no licence)  ===== -->
  <script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>
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
      <?php
// ─── Status + hero card data prep (mirrors client_details.php) ───
$st     = mysqli_fetch_assoc(mysqli_query($con, "SELECT status FROM members WHERE member_id=" . intval($client_id) . " LIMIT 1"));
$stat   = (int) ($st['status'] ?? 0);
$status = $stat;

$cq   = mysqli_query($con, "SELECT * FROM members WHERE member_id='$client_id' LIMIT 1");
$cmem = mysqli_fetch_assoc($cq);
$client_fullname = trim(($cmem['name'] ?? '') . ' ' . ($cmem['mname'] ?? '') . ' ' . ($cmem['lname'] ?? ''));
$client_email    = $cmem['prm_email'] ?? '';
$client_phone    = $cmem['cell_ph'] ?? '';
$client_since_display = (!empty($cmem['client_since']) && $cmem['client_since'] !== '0000-00-00')
? date("M d, Y", strtotime($cmem['client_since'])) : 'N/A';
$client_pic = !empty($cmem['mem_pic']) ? 'images/clients/' . $cmem['mem_pic'] : '';

$hero_bus_name   = $cmem['bus_name']    ?? '';
$hero_bus_email  = $cmem['sec_email']   ?? '';
$hero_bus_phone  = $cmem['phone']       ?? '';
$hero_category   = $cmem['category']    ?? '';
$hero_ach_payer  = (int) ($cmem['ach_payer'] ?? 0);
$hero_frequency  = $cmem['payer_frequency'] ?? '';
$hero_special    = (int) ($cmem['specialClient'] ?? 0);
$hero_type_id    = (int) ($cmem['type'] ?? 0);
$hero_type_name  = '';
if ($hero_type_id) {
    $_hr = mysqli_fetch_assoc(mysqli_query($con, "SELECT type_name FROM member_type WHERE type_id='$hero_type_id' LIMIT 1"));
    $hero_type_name = $_hr['type_name'] ?? '';
}
$hero_unpaid_row = mysqli_fetch_assoc(mysqli_query($con, "SELECT COALESCE(SUM(balance),0) AS b FROM billing_invoice WHERE mem_id='$client_id'"));
$hero_unpaid     = (float) ($hero_unpaid_row['b'] ?? 0);

$hero_debit_adj_row = mysqli_fetch_assoc(mysqli_query($con, "SELECT COALESCE(SUM(total_amount),0) AS b FROM billing_balance_adjustment WHERE mem_id='$client_id' AND adjustment_type='DEBIT' AND deleted_at IS NULL"));
$hero_debit         = (float) ($hero_debit_adj_row['b'] ?? 0);

$hero_credit_row = mysqli_fetch_assoc(mysqli_query($con, "SELECT COALESCE(SUM(total_amount),0) AS b FROM billing_balance_adjustment WHERE mem_id='$client_id' AND adjustment_type='CREDIT' AND deleted_at IS NULL"));
$hero_credit     = (float) ($hero_credit_row['b'] ?? 0);

$hero_dob_raw  = $cmem['dob'] ?? '';
$hero_dob_disp = (empty($hero_dob_raw) || $hero_dob_raw === '0000-00-00')
? '' : date('m/d/Y', strtotime($hero_dob_raw));
$hero_ssn      = $cmem['ss'] ?? '';
$hero_added_id = (int) ($cmem['added_by'] ?? 0);
$hero_upd_id   = (int) ($cmem['user_id']  ?? 0);
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
$hero_home_state = $cmem['hstate']   ?? '';
$hero_home_zip  = $cmem['hzip']     ?? '';
$hero_home_cty  = $cmem['hcounty']  ?? '';
$hero_bus_start = (!empty($cmem['bus_start_date']) && $cmem['bus_start_date'] !== '0000-00-00')
? date('m/d/Y', strtotime($cmem['bus_start_date'])) : '';
$hero_bus_incorp = (!empty($cmem['incorp_date']) && $cmem['incorp_date'] !== '0000-00-00')
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

$hero_featured = [];
$_fs = mysqli_query($con, "SELECT DISTINCT m.membership_name
                                  FROM orders o
                                  JOIN memberships m ON m.membership_id = o.prod_id
                                  WHERE o.mem_id='$client_id' AND (o.order_status IS NULL OR o.order_status=1)
                                  ORDER BY m.membership_name LIMIT 6");
if ($_fs) {
    while ($_r = mysqli_fetch_assoc($_fs)) {
        if (!empty($_r['membership_name'])) $hero_featured[] = $_r['membership_name'];
    }
}
      ?>

      <div class="page-header">
        <div class="page-block">
          <div class="row align-items-center">
            <div class="col-auto">
              <button type="button" class="btn btn-sm me-1" onclick="window.history.back();" style="background-color:#272469;color:#fff;">&#8592;</button>
              <button type="button" class="btn btn-sm" onclick="window.history.forward();" style="background-color:#272469;color:#fff;">&#8594;</button>
            </div>
            <div class="col-auto">
              <ul class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="view_clients.php">Clients</a></li>
                <li class="breadcrumb-item"><a href="client_details.php?id=<?php echo $client_id; ?>&status=<?php echo $stat; ?>">#<?php echo $client_id; ?></a></li>
                <li class="breadcrumb-item active">Billing</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- ── CLIENT HERO CARD ─────────────────────────────────── -->
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

          <button class="ctb ctb-calendar" title="Action alert" onclick="openActionAlert()">
            <i class="fas fa-calendar-alt"></i>
          </button>
          <button class="ctb ctb-navy" onclick="sendManualSms(<?= (int) $client_id ?>)">
            <i class="fas fa-comments"></i> SMS
          </button>
          <button class="ctb ctb-alert" data-bs-toggle="modal" data-bs-target="#modalWarning">
            <i class="fas fa-exclamation-triangle"></i> Alert
          </button>
          <?php
$perm_bill = mysqli_fetch_assoc(mysqli_query($con, "SELECT invoice_and_billing FROM dashboard_access WHERE user_type='$user_type'"));
          if ($perm_bill && $perm_bill['invoice_and_billing'] === 'Yes'): ?>
            <a href="client_billing.php?id=<?= $client_id ?>" class="ctb ctb-navy">
              <i class="fas fa-file-invoice"></i> Billing
            </a>
          <?php endif; ?>
          <?php
$rrow = mysqli_fetch_assoc(mysqli_query($con, "SELECT has_secured_access FROM users WHERE user_id=$userid"));
          if ($rrow && $rrow['has_secured_access'] == 1): ?>
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
$perm_del   = $perm_query ? mysqli_fetch_assoc($perm_query) : null;
if ($perm_del && $perm_del['delete_client'] == 'Yes'):
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
                  <span class="ch-id-badge"><i class="fas fa-id-card"></i> ID: <?= $client_id ?></span>
                  <span><i class="fas fa-calendar-check"></i> Client since: <?= $client_since_display ?></span>
                </div>
                <div class="ch-line">
                  <?php if ($client_email): ?>
                    <span><i class="fas fa-envelope"></i><?= htmlspecialchars($client_email) ?></span>
                  <?php endif; ?>
                  <?php if ($client_phone): ?>
                    <span><i class="fas fa-phone"></i><?= htmlspecialchars($client_phone) ?></span>
                  <?php endif; ?>
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
                    <a class="ch-pill balance" href="inv_unpaid_full.php?client_id=<?= (int) $client_id ?>&preview=1" target="_blank" rel="noopener" title="View unpaid invoice history (PDF)"><i class="fas fa-dollar-sign"></i> Unpaid Balance: $<?= number_format($hero_unpaid, 0) ?> <i class="fas fa-file-pdf" style="margin-left:4px;opacity:.85;"></i></a>
                  <?php else: ?>
                    <span class="ch-pill balance is-zero"><i class="fas fa-dollar-sign"></i> Unpaid Balance: $<?= number_format($hero_unpaid, 0) ?></span>
                  <?php endif; ?>
                  <?php if ($hero_debit > 0): ?>
                    <span class="ch-pill debit"><i class="fas fa-arrow-down"></i> Debit Amount: $<?= number_format($hero_debit, 2) ?></span>
                  <?php endif; ?>
                  <?php if ($hero_credit > 0): ?>
                    <span class="ch-pill credit"><i class="fas fa-arrow-up"></i> Credit Balance: $<?= number_format($hero_credit, 2) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- ── RIGHT: business ────────────────────────── -->
            <div class="ch-block">
              <div class="ch-avatar">
                <i class="fas fa-briefcase"></i>
              </div>
              <div class="ch-block-body">
                <div class="ch-name">
                  <?= !empty($hero_bus_name) ? htmlspecialchars(strtoupper($hero_bus_name)) : '<span style="opacity:.55;font-style:italic;">No business on file</span>' ?>
                </div>
                <div class="ch-line">
                  <?php $b_email = $hero_bus_email ?: $client_email; ?>
                  <?php if ($b_email): ?>
                    <span><i class="fas fa-envelope"></i><?= htmlspecialchars($b_email) ?></span>
                  <?php endif; ?>
                  <?php $b_phone = $hero_bus_phone ?: $client_phone; ?>
                  <?php if ($b_phone): ?>
                    <span><i class="fas fa-phone"></i><?= htmlspecialchars($b_phone) ?></span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($hero_category) || !empty($hero_type_name)): ?>
                  <div class="ch-line">
                    <?php if (!empty($hero_category)): ?>
                      <span><i class="fas fa-briefcase"></i><?= htmlspecialchars($hero_category) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($hero_type_name)): ?>
                      <span><i class="fas fa-user-tag"></i><?= htmlspecialchars($hero_type_name) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

          </div><!-- /.client-hero-body -->

          <!-- ── Detail cards (two columns) ──────── -->
          <div class="ch-details-row">

            <!-- LEFT: Client Details + Address + Featured Services -->
            <div class="ch-detail-card">
              <div class="ch-detail-inner">
                <div>
                  <div class="ch-detail-section-title">Client Details</div>
                  <div class="ch-detail-field"><b>DOB:</b>
                    <?= $hero_dob_disp !== '' ? htmlspecialchars($hero_dob_disp) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>SSN:</b>
                    <?= $hero_ssn !== '' ? htmlspecialchars($hero_ssn) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>Added By:</b>
                    <?= $hero_added_name !== '' ? htmlspecialchars(getInitials($hero_added_name)) . ' (' . $hero_added_id . ')' : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>Updated By:</b>
                    <?= $hero_upd_name !== '' ? htmlspecialchars(getInitials($hero_upd_name)) . ' (' . $hero_upd_id . ')' : '<span class="fv-empty">—</span>' ?>
                  </div>
                </div>
                <div>
                  <div class="ch-detail-section-title">Address</div>
                  <div class="ch-detail-field"><b>Address:</b>
                    <?= $hero_home_addr !== '' ? htmlspecialchars($hero_home_addr) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>City:</b>
                    <?= $hero_home_city !== '' ? htmlspecialchars($hero_home_city) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>State:</b>
                    <?= $hero_home_state !== '' ? htmlspecialchars($hero_home_state) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>Zip:</b>
                    <?= $hero_home_zip !== '' ? htmlspecialchars($hero_home_zip) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>County:</b>
                    <?= $hero_home_cty !== '' ? htmlspecialchars($hero_home_cty) : '<span class="fv-empty">—</span>' ?>
                  </div>
                </div>
              </div>

              <div class="ch-featured-block">
                <div class="ch-detail-section-title">TOP PROVIDED SERVICES</div>
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
                <div>
                  <div class="ch-detail-section-title">Company Detail</div>
                  <div class="ch-detail-field"><b>Client Type:</b>
                    <?= $hero_type_name !== '' ? htmlspecialchars($hero_type_name) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>Designation:</b>
                    <?= $hero_designation !== '' ? htmlspecialchars($hero_designation) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>Business Start date:</b>
                    <?= $hero_bus_start !== '' ? htmlspecialchars($hero_bus_start) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>Incorporation Date:</b>
                    <?= $hero_bus_incorp !== '' ? htmlspecialchars($hero_bus_incorp) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>File:</b>
                    <?= $hero_file !== '' ? htmlspecialchars($hero_file) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>EIN:</b>
                    <?= $hero_ein !== '' ? htmlspecialchars($hero_ein) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>DBA:</b>
                    <?= $hero_dba !== '' ? htmlspecialchars($hero_dba) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>PMB:</b>
                    <?= $hero_pmb !== '' ? htmlspecialchars($hero_pmb) : '<span class="fv-empty">—</span>' ?>
                  </div>
                </div>
                <div>
                  <div class="ch-detail-section-title">Contact &amp; Address</div>
                  <div class="ch-detail-field"><b>Phone (other):</b>
                    <?= $hero_other_ph !== '' ? htmlspecialchars($hero_other_ph) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>Phone (overseas):</b>
                    <?= $hero_overseas_ph !== '' ? htmlspecialchars($hero_overseas_ph) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>County:</b>
                    <?= $hero_bcounty !== '' ? htmlspecialchars($hero_bcounty) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>Business Address:</b>
                    <?= $hero_bus_addr !== '' ? htmlspecialchars($hero_bus_addr) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>Business City:</b>
                    <?= $hero_bus_city !== '' ? htmlspecialchars($hero_bus_city) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>Business State:</b>
                    <?= $hero_bus_state !== '' ? htmlspecialchars($hero_bus_state) : '<span class="fv-empty">—</span>' ?>
                  </div>
                  <div class="ch-detail-field"><b>Business Zip:</b>
                    <?= $hero_bus_zip !== '' ? htmlspecialchars($hero_bus_zip) : '<span class="fv-empty">—</span>' ?>
                  </div>
                </div>
              </div>
            </div>

          </div><!-- /.ch-details-row -->
        </div><!-- /.client-hero-main -->

        <!-- Delete Client Modal -->
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
      </div><!-- /.client-hero -->

      <!-- WARNING BANNER -->
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

      <div class="row">
        <div class="col-xl-12">
          <?php /* Personal/Company detail cards replaced by the client-hero card above. */
          if (false): ?>
          <div class="row">
            <?php
$querr = mysqli_query($con, "SELECT * FROM members where member_id='$client_id'");
$c = 1;
while ($fetch_member = mysqli_fetch_array($querr)) {
    $member_name = $fetch_member['name'];
              ?>
              <?php if ($perms['view_personal'] === 'Yes'): ?>
                <div class="col-sm-12 col-md-6">

                  <div class="card ">
                    <div class="card-body employee-detail-body fulls-card">
                      <h4>Personal Detail</h4>
                      <hr>
                      <div class="row">
                        <div class="col-md-5">
                          <img
                            src="<?php echo !empty($fetch_member['mem_pic']) ? 'images/clients/' . $fetch_member['mem_pic'] : 'images/default-user.png'; ?>"
                            style="width: 100%;">

                        </div>
                        <div class="col-md-7">
                          <div class="info text-sm">
                            <strong class="font-bold">Client ID : </strong>
                            <span>#<?php echo $fetch_member['member_id']; ?></span>
                          </div>
                          <div class="info text-sm font-style">
                            <strong class="font-bold">Name :</strong>
                            <span><?php echo $clientname = $fetch_member['name'] . " " . $fetch_member['mname'] . " " . $fetch_member['lname']; ?></span>
                          </div>
                          <div class="info text-sm font-style">
                            <strong class="font-bold">DOB :</strong>
                            <span><?php
    $date = DateTime::createFromFormat('Y-m-d', $fetch_member['dob']);
    echo $date ? $date->format('m/d/Y') : 'Invalid date';
                            ?></span>
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


                          <?php cf_render_field($con, $client_id, 'phone', $fetch_member['cell_ph'], 'Phone (cell)'); ?>


                          <?php cf_render_field($con, $client_id, 'email', $fetch_member['prm_email'], 'Primary Email', true); ?>


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
                        </div>
                      </div>
                    </div>
                  </div>

                </div>
              <?php else: ?>
                <div class="col-sm-12 col-md-6 d-flex">
                  <div class="card w-100 h-100 shadow-sm">
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                      <div class="mb-3"><i class="fas fa-user-lock fa-4x text-muted"></i></div>
                      <h5 class="text-muted mb-1">Personal Details Restricted</h5>
                      <p class="text-muted mb-2">You do not have permission to view this information.</p>
                      <small class="text-muted">Contact your administrator for access.</small>
                    </div>
                  </div>
                </div>
              <?php endif; ?>

              <?php if ($perms['view_company'] === 'Yes'): ?>
                <div class="col-sm-12 col-md-6">

                  <div class="card ">
                    <div class="card-body employee-detail-body fulls-card">
                      <h4>Company Detail</h4>
                      <hr>
                      <div class="row">

                        <div class="col-md-6">
                          <div class="info text-sm font-style">
                            <strong class="font-bold">Phone (work): </strong>
                            <span><?php echo $fetch_member['cell_ph']; ?></span>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <?php cf_render_field($con, $client_id, 'overseas_phone', $fetch_member['overseas_ph'], 'Phone (overseas)'); ?>
                        </div>
                        <div class="col-md-6">
                          <div class="info text-sm font-style">
                            <strong class="font-bold">Phone (other): </strong>
                            <span><?php echo $fetch_member['other_ph']; ?></span>
                          </div>
                        </div>

                        <div class="col-md-6">
                          <?php cf_render_field($con, $client_id, 'sec_email', $fetch_member['sec_email'], 'Business Email', true); ?>
                        </div>

                        <div class="col-md-6">
                          <div class="info text-sm">
                            <strong class="font-bold">File :</strong>
                            <span><?php echo $fetch_member['file']; ?></span>
                          </div>
                        </div>

                        <div class="col-md-6">
                          <div class="info text-sm">
                            <strong class="font-bold">County :</strong>
                            <span><?php echo $clientssn = $fetch_member['bcounty']; ?></span>
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
                            <strong class="font-bold">TF :</strong>
                            <span><?php echo $fetch_member['tf']; ?></span>
                          </div>
                        </div>
                        <div class="col-md-12">
                          <div class="info text-sm">
                            <strong class="font-bold">category:</strong>
                            <span><?php echo $fetch_member['category']; ?></span>
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
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <div class="col-sm-12 col-md-6 d-flex">
                  <div class="card w-100 h-100 shadow-sm">
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                      <div class="mb-3"><i class="fas fa-user-lock fa-4x text-muted"></i></div>
                      <h5 class="text-muted mb-1">Company Details Restricted</h5>
                      <p class="text-muted mb-2">You do not have permission to view this information.</p>
                      <small class="text-muted">Contact your administrator for access.</small>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            <?php } ?>
          </div>
          <?php endif; ?>


          <div class="row">
            <div class="col-sm-12 col-md-12">
              <div class="billing-tabs-shell">
                <div class="billing-tabs-header">
                  <div>
                    <h5 class="billing-tabs-title">Invoice &amp; Billing</h5>
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
                      <span>Sent Invoice History</span>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" id="balance-credit-tab" data-toggle="tab" href="#balance-credit-pane" role="tab"
                      aria-controls="balance-credit-pane" aria-selected="false">
                      <i class="ti ti-report-money"></i>
                      <span>Balance &amp; Credit Statement</span>
                    </a>
                  </li>
                </ul>

                <div class="tab-content" id="invoiceBillingTabContent">
                  <div class="tab-pane fade show active" id="invoices-pane" role="tabpanel"
                    aria-labelledby="invoices-tab">
                    <div class="card ">
                      <div class="card-body employee-detail-body fulls-card">
                        <div class="row">
                          <div class="col-md-7">
                            <h5>Invoices<?= mh_icon('invoices') ?></h5>
                          </div>
                          <div class="col-md-5 text-end">
                            <button class="btn btn-primary" data-toggle="modal" data-target="#associatedProducts">
                              Add New
                            </button>
                            <button class="btn btn-primary ms-2" onclick="sendManualSms(<?= (int) $_GET['id'] ?>)">
                              <i class="ti ti-message-circle"></i> Manual SMS
                            </button>
                            <button class="btn btn-primary ms-2" title="Preview History"
                              onclick="pickHistoryPreview(<?= (int) $client_id ?>)">
                              <i class="ti ti-file-text"></i>
                            </button>
                            <button class="btn btn-primary ms-2" title="Send Invoice History"
                              onclick="sendEmailHistory(<?= (int) $client_id ?>)">
                              <i class="ti ti-mail"></i>
                            </button>
                          </div>

                        </div>
                        <hr>
                        <div class="row">
                          <div class="dataTable-container">
                            <table class="table datatable">
                              <thead>
                                <tr>
                                  <th data-sortable=""><a href="#" class="dataTable-sorter">Sr#</a></th>
                                  <th data-sortable=""><a href="#" class="dataTable-sorter">Invoice</a></th>
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
                                $querr1 = mysqli_query($con, "SELECT * FROM billing_invoice WHERE mem_id=$client_id ORDER BY invoice_id DESC");

                                /* memberships list for the edit-invoice service picker (fetched once) */
                                $invEditMemberships = [];
                                $invEditMembQ = mysqli_query($con, "SELECT membership_id, membership_name, membership_price FROM memberships ORDER BY membership_name ASC");
                                while ($invEditMembRow = mysqli_fetch_assoc($invEditMembQ)) {
                                  $invEditMemberships[] = $invEditMembRow;
                                }

                                $c = 1;
                                while ($Podrow = mysqli_fetch_array($querr1)) {
                                  $invId = (int) $Podrow['invoice_id'];

                                  $phRes = mysqli_query($con, "SELECT COUNT(*) AS cnt FROM billing_payment_history WHERE inv_id=$invId");
                                  $hasPaymentHistory = ($phRes && ($phRow = mysqli_fetch_assoc($phRes))) ? ((int) $phRow['cnt'] > 0) : false;

                                  $cmRes = mysqli_query($con, "SELECT COUNT(*) AS cnt FROM new_comments WHERE invoice_id=$invId");
                                  $hasInvoiceComments = ($cmRes && ($cmRow = mysqli_fetch_assoc($cmRes))) ? ((int) $cmRow['cnt'] > 0) : false;
                                  ?>
                                  <tr>
                                    <td><?php echo $c; ?></td>
                                    <td><?php echo $Podrow["invoice_id"]; ?></td>
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
                                      $invoice_status = $Podrow["invoice_status"];

                                      $fetch_status = mysqli_query($con, "SELECT * FROM billing_invoice_status WHERE status_id=$invoice_status");
                                      while ($fetch_record = mysqli_fetch_array($fetch_status)) {
                                        $status_value = $fetch_record['status_text'];
                                      }
                                      ?>
                                      <?php
                                      if ($status_value == 'Not Paid') {
                                        $dueRaw = $Podrow['due_date'];
                                        $isOverdue = false;
                                        if (!empty($dueRaw) && $dueRaw !== '0000-00-00') {
                                          $isOverdue = strtotime($dueRaw) <= strtotime(date('Y-m-d'));
                                        }
                                        if ($isOverdue) { ?>
                                          <span class="badge badge-pill badge-danger">Due</span>
                                        <?php } else { ?>
                                          <span class="badge badge-pill" style="background-color:#6c757d;color:#fff;">Unpaid</span>
                                        <?php }
                                      } elseif ($status_value == 'Partial Paid') { ?>
                                        <span class="badge badge-pill badge-warning">Partial
                                          Paid</span>
                                      <?php } elseif ($status_value == 'Paid') { ?>
                                        <span class="badge badge-pill badge-success">Paid</span>
                                      <?php } ?>

                                    </td>


                                    <td>

                                      <div class="d-flex justify-content-between mb-1">
                                        <a href="invoices/<?php echo $Podrow['filelink']; ?>"
                                          class="btn btn-sm btn-primary tooltip1 view-file-preview"
                                          data-file="<?php echo htmlspecialchars($Podrow['filelink']); ?>"
                                          target="_blank">
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
                                        <button
                                          class="btn btn-sm <?php echo $hasPaymentHistory ? 'btn-success' : 'btn-light text-muted border'; ?>"
                                          id="<?php echo $Podrow['invoice_id']; ?>"
                                          onclick="gethistory(<?php echo $Podrow['invoice_id']; ?>)"
                                          data-bs-toggle="tooltip1" data-bs-placement="top"
                                          title="<?php echo $hasPaymentHistory ? 'Payment History' : 'No payments recorded'; ?>">
                                          <i class="ti <?php echo $hasPaymentHistory ? 'ti-history' : 'ti-history-off'; ?>"></i>
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
                                          onclick="sendReminder(<?php echo (int) $Podrow['invoice_id']; ?>, '<?php echo addslashes($status_value); ?>')">
                                          <i class="ti ti-mail text-white"></i>
                                          <span class="tooltiptext1">Send Reminder</span>
                                        </button>
                                        <!-- =====  NEW WhatsApp button  ===== -->
                                        <button class="btn btn-sm btn-success tooltip1"
                                          onclick="sendWhatsAppReminder(<?php echo $Podrow['invoice_id']; ?>)">
                                          <!-- Tabler "brand-whatsapp" icon -->
                                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" class="ti ti-mail">
                                            <path d="M4 4h16v16H4z" />
                                            <polyline points="22,6 12,13 2,6" />
                                          </svg>
                                          <span class="tooltiptext1">SMS Reminder</span>
                                        </button>
                                        <!-- Tabler Button with Tooltip -->
                                        <button type="button"
                                          class="btn btn-icon btn-sm <?php echo $hasInvoiceComments ? 'btn-success' : 'btn-light text-muted border'; ?>"
                                          data-bs-toggle="modal"
                                          data-bs-target="#addCommentModal<?php echo $Podrow['invoice_id']; ?>"
                                          data-bs-toggle="tooltip" data-bs-placement="top"
                                          title="<?php echo $hasInvoiceComments ? 'Add Comment' : 'No comments yet'; ?>">
                                          <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24"
                                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <?php if ($hasInvoiceComments) { ?>
                                              <path d="M3 20l1.5 -1.5" />
                                              <path d="M4 17h16a2 2 0 0 0 2 -2v-10a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v12" />
                                              <path d="M8 9h8" />
                                              <path d="M8 13h4" />
                                            <?php } else { ?>
                                              <path d="M8 9h2m4 0h.01" />
                                              <path d="M11.5 17h-3.5l-4 4v-13a2 2 0 0 1 2 -2h4m4 0h6a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2" />
                                              <path d="M3 3l18 18" />
                                            <?php } ?>
                                          </svg>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-custom tooltip1"
                                          onclick="openInvoiceDelete(<?php echo (int) $Podrow['invoice_id']; ?>)"><i
                                            class="ti ti-trash text-white"></i><span
                                            class="tooltiptext1">Delete</span></button>
                                      </div>

                                      <div class="d-flex justify-content-between mt-1 ">
                                        <?php echo renderStripeButtons($con, $Podrow['invoice_id'], $Podrow['balance']); ?>
                                        <button class="btn btn-sm btn-custom tooltip1 invisible-btn"><i
                                            class="ti ti-trash text-white"></i></button>
                                      </div>
                                    </td>


                                  </tr>
                                  <input type="hidden" id="invoicehistorydata">

                                  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>


                                  <div class="modal fade" id="editInvoiceModal<?php echo $Podrow['invoice_id']; ?>">
                                    <div class="modal-dialog modal-lg">
                                      <form method="POST" id="editInvoiceForm<?php echo $Podrow['invoice_id']; ?>">
                                        <div class="modal-content">
                                          <div class="modal-header">
                                            <h4 class="modal-title">Edit Invoice</h4>
                                          </div>

                                          <div class="modal-body">
                                            <input type="hidden" name="invoice_id"
                                              value="<?php echo $Podrow['invoice_id']; ?>" />

                                            <div class="row mb-2">
                                              <div class="col-md-6">
                                                <label class="form-label">Due Date</label>
                                                <input type="date" class="form-control" name="edit_due_date"
                                                  value="<?php echo $Podrow['due_date']; ?>" <?php echo $cap_due ? 'required' : 'disabled'; ?>>
                                              </div>
                                              <div class="col-md-6">
                                                <label class="form-label">Paid Date</label>
                                                <input type="date" class="form-control" name="edit_paid_date"
                                                  value="<?php echo $Podrow['paid_date'] !== '0000-00-00' ? $Podrow['paid_date'] : ''; ?>"
                                                  <?php echo $cap_paid_date ? '' : 'disabled'; ?>>
                                              </div>
                                            </div>

                                            <div class="row mb-2">
                                              <div class="col-md-6">
                                                <label class="form-label">By (Created By)</label>
                                                <?php
                                                /* user dropdown: selecting a user stores "Initials(user_id)" e.g. A(2) */
                                                $current_by = $Podrow['employee_initials'] ?? '';
                                                $byMatched = false;
                                                $byOptions = '';
                                                $byQ = mysqli_query($con, "SELECT user_id, user_name FROM users ORDER BY user_name ASC");
                                                while ($byU = mysqli_fetch_assoc($byQ)) {
                                                  $byVal = getInitials($byU['user_name']) . '(' . $byU['user_id'] . ')';
                                                  $bySel = '';
                                                  if (!$byMatched && $byVal === $current_by) {
                                                    $bySel = 'selected';
                                                    $byMatched = true;
                                                  }
                                                  $byOptions .= '<option value="' . htmlspecialchars($byVal, ENT_QUOTES) . '" ' . $bySel . '>'
                                                    . htmlspecialchars($byU['user_name'] . ' (' . $byVal . ')') . '</option>';
                                                }
                                                ?>
                                                <select class="form-control" name="edit_employee_initials" <?php echo $is_admin ? '' : 'disabled'; ?>>
                                                  <?php if (!$byMatched && $current_by !== '') { ?>
                                                    <option value="<?php echo htmlspecialchars($current_by, ENT_QUOTES); ?>" selected>
                                                      <?php echo htmlspecialchars($current_by); ?> (current)
                                                    </option>
                                                  <?php } ?>
                                                  <option value="">Select User</option>
                                                  <?php echo $byOptions; ?>
                                                </select>
                                              </div>
                                              <div class="col-md-6">
                                                <label class="form-label">Created</label>
                                                <input type="date" class="form-control" name="edit_invoice_date"
                                                  value="<?php echo !empty($Podrow['invoice_date']) ? date('Y-m-d', strtotime($Podrow['invoice_date'])) : ''; ?>"
                                                  <?php echo $is_admin ? '' : 'disabled'; ?>>
                                              </div>
                                            </div>

                                            <hr>
                                            <h6 class="mb-2">Services</h6>

                                            <!-- existing + newly-added line items -->
                                            <div class="inv-edit-services mb-2"
                                              data-form="editInvoiceForm<?php echo $Podrow['invoice_id']; ?>">
                                              <?php
                                              $invItemsQ = mysqli_query($con, "SELECT * FROM billing_invoice_items WHERE inv_id='" . (int) $Podrow['invoice_id'] . "'");
                                              while ($invItem = mysqli_fetch_assoc($invItemsQ)) {
                                                ?>
                                                <div class="row mb-2 inv-edit-srow"
                                                  style="background:#e9e9e9;padding:10px 0;border-radius:4px;border-bottom:2px dashed #b9b9b9;">
                                                  <div class="col-md-4">
                                                    <label class="form-label">Service</label>
                                                    <input class="form-control" required name="service[]" type="text"
                                                      form="editInvoiceForm<?php echo $Podrow['invoice_id']; ?>"
                                                      value="<?php echo htmlspecialchars($invItem['item_description'], ENT_QUOTES); ?>"
                                                      <?php echo $cap_service ? '' : 'readonly'; ?>>
                                                  </div>
                                                  <div class="col-md-5">
                                                    <label class="form-label">Description</label>
                                                    <textarea name="description[]" class="form-control"
                                                      form="editInvoiceForm<?php echo $Podrow['invoice_id']; ?>"
                                                      rows="1" <?php echo $cap_service_desc ? '' : 'readonly'; ?>><?php echo htmlspecialchars($invItem['item_sub_description'], ENT_QUOTES); ?></textarea>
                                                  </div>
                                                  <div class="col-md-2">
                                                    <label class="form-label">Price</label>
                                                    <input class="form-control inv-edit-price" required name="unit_price[]"
                                                      type="number" min="0" step="0.01"
                                                      form="editInvoiceForm<?php echo $Podrow['invoice_id']; ?>"
                                                      value="<?php echo htmlspecialchars($invItem['unit_price'], ENT_QUOTES); ?>"
                                                      oninput="invEditRecalc(this)" <?php echo $cap_service_amount ? '' : 'readonly'; ?>>
                                                  </div>
                                                  <div class="col-md-1 d-flex align-items-end">
                                                    <?php if ($cap_service) { ?>
                                                      <button type="button" class="btn btn-danger btn-sm"
                                                        onclick="invEditRemoveRow(this)" title="Remove">&times;</button>
                                                    <?php } ?>
                                                  </div>
                                                </div>
                                              <?php } ?>
                                            </div>

                                            <?php if ($cap_service) { ?>
                                              <!-- service picker: selecting a service auto-adds a row with its price -->
                                              <div class="row mb-3">
                                                <div class="col-md-12">
                                                  <select class="form-control inv-edit-picker"
                                                    onchange="invEditAddPicked(this)">
                                                    <option value="">+ Select a service to add…</option>
                                                    <?php foreach ($invEditMemberships as $invMs) { ?>
                                                      <option value="<?php echo htmlspecialchars($invMs['membership_name'], ENT_QUOTES); ?>"
                                                        data-price="<?php echo htmlspecialchars($invMs['membership_price'], ENT_QUOTES); ?>">
                                                        <?php echo htmlspecialchars($invMs['membership_name']); ?>
                                                      </option>
                                                    <?php } ?>
                                                  </select>
                                                </div>
                                              </div>
                                            <?php } ?>

                                            <div class="row mb-2">
                                              <div class="col-md-6">
                                                <label class="form-label">Total Amount</label>
                                                <input type="number" step="0.01" class="form-control inv-edit-total"
                                                  name="edit_total_amount"
                                                  value="<?php echo $Podrow['total_amount']; ?>" readonly required>
                                              </div>
                                              <div class="col-md-6">
                                                <label class="form-label">Discount</label>
                                                <input type="number" step="0.01" name="edit_discount"
                                                  class="form-control inv-edit-discount"
                                                  value="<?php echo $Podrow['discount']; ?>" oninput="invEditRecalc(this)"
                                                  <?php echo $cap_discount ? 'required' : 'readonly'; ?>>
                                              </div>
                                              <div class="col-md-6 mt-2">
                                                <label class="form-label">Paid Amount</label>
                                                <input type="number" step="0.01" class="form-control inv-edit-paid"
                                                  name="edit_paid_amount"
                                                  value="<?php echo $Podrow['paid_amount']; ?>" oninput="invEditRecalc(this)"
                                                  <?php echo $cap_paid_amount ? 'required' : 'readonly'; ?>>
                                              </div>
                                              <div class="col-md-6 mt-2">
                                                <label class="form-label">Balance</label>
                                                <input type="number" step="0.01" class="form-control inv-edit-balance"
                                                  name="edit_balance" value="<?php echo $Podrow['balance']; ?>" readonly
                                                  required>
                                              </div>

                                              <div class="col-md-12 mt-2">
                                                <label class="form-label">Service Provider</label>
                                                <select name="edit_service_provider" class="form-control" <?php echo $cap_service_provider ? 'required' : 'disabled'; ?>>
                                                  <option value="">Select Service Provider</option>
                                                  <?php
                                                  $uQ = mysqli_query($con, "SELECT user_id, user_name FROM users ORDER BY user_name ASC");
                                                  while ($u = mysqli_fetch_assoc($uQ)) {
                                                    $sel = ($u['user_id'] == $Podrow['service_provider_id']) ? 'selected' : '';
                                                    echo '<option value="' . $u['user_id'] . '" ' . $sel . '>' . htmlspecialchars($u['user_name']) . '</option>';
                                                  }
                                                  ?>
                                                </select>
                                              </div>

                                              <div class="col-md-12 mt-2">
                                                <label class="form-label">Description</label>
                                                <input class="form-control" name="edit_payment_details" type="text"
                                                  value="<?php echo htmlspecialchars($Podrow['payment_details']); ?>">
                                              </div>

                                              <?php
                                              /* Payment method only exists once a payment has been recorded.
                                                 Pre-fill from the most recent payment and edit that record. */
                                              if ($hasPaymentHistory) {
                                                $pmRow = mysqli_fetch_assoc(mysqli_query($con, "SELECT payment_method FROM billing_payment_history WHERE inv_id='" . (int) $Podrow['invoice_id'] . "' ORDER BY his_id DESC LIMIT 1"));
                                                $currentPaymentMethod = $pmRow['payment_method'] ?? '';
                                                ?>
                                                <div class="col-md-12 mt-2">
                                                  <label class="form-label">Payment Method</label>
                                                  <input class="form-control" name="edit_payment_method" type="text"
                                                    list="pmOptions<?php echo $Podrow['invoice_id']; ?>"
                                                    value="<?php echo htmlspecialchars($currentPaymentMethod, ENT_QUOTES); ?>"
                                                    placeholder="e.g. Cash, Zelle" <?php echo $is_admin ? '' : 'disabled'; ?>>
                                                  <datalist id="pmOptions<?php echo $Podrow['invoice_id']; ?>">
                                                    <option value="Cash"></option>
                                                    <option value="Check"></option>
                                                    <option value="Card"></option>
                                                    <option value="ACH"></option>
                                                    <option value="Zelle"></option>
                                                  </datalist>
                                                  <small class="text-muted">Updates the most recent payment record for this
                                                    invoice. Separate multiple methods with commas.</small>
                                                </div>
                                              <?php } ?>
                                            </div>
                                          </div>

                                          <div class="modal-footer">
                                            <button type="button" class="btn btn-info"
                                              data-bs-dismiss="modal">Close</button>
                                            <button type="submit" class="btn btn-primary" name="update_invoice">Save
                                              Changes</button>
                                          </div>
                                        </div>
                                      </form>
                                    </div>
                                  </div>
                                  <!-- ======================  /EDIT-INVOICE MODAL  ====================== -->

                                  <script>
                                    function applyDecimalInputs() {
                                      $('input[name="unit_price[]"], .sum').each(function () {
                                        $(this).attr('step', '0.01');
                                        $(this).attr('inputmode', 'decimal');
                                      });
                                    }

                                    /* ---------- auto-load rows for already-checked services ---------- */
                                    $(function () {
                                      $('.service-chk:checked').each(function () {
                                        var id = $(this).val();
                                        triggerServiceLoad(id);
                                      });

                                      /* ---------- live listener for future check/uncheck ---------- */
                                      $(document).on('click', '.service-chk', function () {
                                        var id = $(this).val();
                                        if ($(this).is(':checked')) {
                                          triggerServiceLoad(id);
                                        } else {
                                          $('#category-dropdown' + id).empty();
                                        }
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
                                        success: function (html) {
                                          $('#category-dropdown' + mainId).html(html);
                                          applyDecimalInputs();
                                        }
                                      });
                                    }
                                  </script>

                                  <script>
                                    /* ===== Invoice edit modal: editable line items + live totals =====
                                       Defined once; all handlers are scoped to the triggering modal via
                                       closest('.modal-content'), so they work across every per-invoice modal. */
                                    if (!window.invEditBound) {
                                      window.invEditBound = true;

                                      window.invEditEsc = function (s) {
                                        return String(s == null ? '' : s)
                                          .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                                          .replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                      };

                                      window.invEditRecalc = function (el) {
                                        var box = el.closest('.modal-content');
                                        if (!box) return;
                                        var discEl = box.querySelector('.inv-edit-discount');
                                        var paidEl = box.querySelector('.inv-edit-paid');
                                        var totalEl = box.querySelector('.inv-edit-total');
                                        var balEl = box.querySelector('.inv-edit-balance');
                                        var disc = parseFloat(discEl ? discEl.value : 0) || 0;
                                        var paid = parseFloat(paidEl ? paidEl.value : 0) || 0;

                                        var priceInputs = box.querySelectorAll('.inv-edit-price');
                                        if (priceInputs.length === 0) {
                                          /* no line items present: keep the stored total, just refresh balance */
                                          var t0 = parseFloat(totalEl ? totalEl.value : 0) || 0;
                                          if (balEl) balEl.value = ((t0 - disc) - paid).toFixed(2);
                                          return;
                                        }

                                        var sub = 0;
                                        priceInputs.forEach(function (p) { sub += parseFloat(p.value) || 0; });
                                        if (totalEl) totalEl.value = sub.toFixed(2);
                                        if (balEl) balEl.value = ((sub - disc) - paid).toFixed(2);
                                      };

                                      window.invEditRemoveRow = function (btn) {
                                        var box = btn.closest('.modal-content');
                                        var row = btn.closest('.inv-edit-srow');
                                        if (row) row.parentNode.removeChild(row);
                                        if (box) {
                                          var any = box.querySelector('.inv-edit-price') || box.querySelector('.inv-edit-discount');
                                          if (any) window.invEditRecalc(any);
                                        }
                                      };

                                      window.invEditAddPicked = function (btn) {
                                        var box = btn.closest('.modal-content');
                                        var picker = box.querySelector('.inv-edit-picker');
                                        if (!picker || !picker.value) return;
                                        var opt = picker.options[picker.selectedIndex];
                                        var name = picker.value;
                                        var price = opt.getAttribute('data-price') || '0';
                                        var container = box.querySelector('.inv-edit-services');
                                        /* inputs must be bound to the invoice form via the form= attribute
                                           (same as the PHP-rendered rows), otherwise the browser does not
                                           submit them and the added service is silently dropped. */
                                        var formId = container.getAttribute('data-form') || '';
                                        var formAttr = formId ? ' form="' + window.invEditEsc(formId) + '"' : '';
                                        var div = document.createElement('div');
                                        div.className = 'row mb-2 inv-edit-srow';
                                        div.setAttribute('style', 'background:#e9e9e9;padding:10px 0;border-radius:4px;border-bottom:2px dashed #b9b9b9;');
                                        div.innerHTML =
                                          '<div class="col-md-4"><label class="form-label">Service</label>' +
                                          '<input class="form-control" required name="service[]" type="text"' + formAttr + ' value="' + window.invEditEsc(name) + '"></div>' +
                                          '<div class="col-md-5"><label class="form-label">Description</label>' +
                                          '<textarea name="description[]" class="form-control" rows="1"' + formAttr + '></textarea></div>' +
                                          '<div class="col-md-2"><label class="form-label">Price</label>' +
                                          '<input class="form-control inv-edit-price" required name="unit_price[]" type="number" min="0" step="0.01"' + formAttr + ' value="' + window.invEditEsc(price) + '" oninput="invEditRecalc(this)"></div>' +
                                          '<div class="col-md-1 d-flex align-items-end">' +
                                          '<button type="button" class="btn btn-danger btn-sm" onclick="invEditRemoveRow(this)" title="Remove">&times;</button></div>';
                                        container.appendChild(div);
                                        picker.value = '';
                                        window.invEditRecalc(div.querySelector('.inv-edit-price'));
                                      };
                                    }
                                  </script>




                                  <!-- ====== Invoice / payment delete (SweetAlert2) ======
                                       Registers this invoice's recorded payments for the JS delete flow.
                                       The popups themselves are defined once by openInvoiceDelete() below. -->
                                  <?php
                                  $payListJs = [];
                                  $payQ = mysqli_query($con, "SELECT * FROM billing_payment_history WHERE inv_id='" . (int) $Podrow['invoice_id'] . "' ORDER BY his_id ASC");
                                  if ($payQ) {
                                    while ($pay = mysqli_fetch_assoc($payQ)) {
                                      $payListJs[] = [
                                        'his_id'   => (int) $pay['his_id'],
                                        'date'     => (!empty($pay['date']) && $pay['date'] != '0000-00-00') ? date('m/d/Y', strtotime($pay['date'])) : 'N/A',
                                        'detail'   => htmlspecialchars($pay['detail'] ?? '', ENT_QUOTES),
                                        'paid'     => number_format((float) $pay['paid'], 2),
                                        'balance'  => number_format((float) $pay['balance'], 2),
                                        'employee' => htmlspecialchars($pay['employee'] ?? '', ENT_QUOTES),
                                        'method'   => htmlspecialchars(($pay['payment_method'] ?: 'N/A'), ENT_QUOTES),
                                      ];
                                    }
                                  }
                                  ?>
                                  <script>
                                    window.invoicePaymentData = window.invoicePaymentData || {};
                                    window.invoicePaymentData[<?php echo (int) $Podrow['invoice_id']; ?>] = <?php echo json_encode($payListJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                                  </script>
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
                                            <input type="hidden" name="invoice_id"
                                              value="<?php echo $Podrow['invoice_id']; ?>">

                                            <div class="row mb-2">
                                              <div class="col-md-12">

                                                <!-- quick phrases -->
                                                <label class="form-label">Quick phrases</label>
                                                <div class="mb-2">
                                                  <?php
                                                  $uid = (int) $_SESSION['user_id'];
                                                  $res = mysqli_query(
                                                    $con,
                                                    "SELECT phrase FROM user_quick_phrases WHERE user_id=$uid ORDER BY id"
                                                  );
                                                  while ($row = mysqli_fetch_assoc($res)) {
                                                    echo '<button type="button" class="btn btn-sm btn-outline-primary me-1 mb-1"
                          onclick="addPhrase(\'' . htmlspecialchars($row['phrase']) . '\')">'
                                                      . htmlspecialchars($row['phrase']) . '</button>';
                                                  }
                                                  ?>
                                                  <a href="quickphrases.php" class="btn btn-primary btn-sm">Edit
                                                    Phrases</a>
                                                </div>


                                                <label for="invoice_comment" class="form-label">Comment
                                                  (edit or type)</label>
                                                <textarea class="ckeditor" name="invoice_comment"
                                                  id="invoice_comment<?php echo $Podrow['invoice_id']; ?>"></textarea>

                                              </div>
                                            </div>
                                          </div>

                                          <div class="modal-footer">
                                            <button type="button" class="btn btn-info"
                                              data-bs-dismiss="modal">Close</button>
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
                                              <input type="hidden" name="memberid"
                                                value="<?php echo $Podrow['mem_id']; ?>" />
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
                                                <input type="hidden" id='old_discount' name='old_discount'
                                                  class='form-control' value='<?php echo $Podrow["discount"]; ?>'>
                                              </div>
                                              <div class="col-md-6 mt-2">
                                                <label for="employee_initials" class="form-label">Employee
                                                  Initials</label>
                                                <input class="form-control" id="employee_initials"
                                                  name="employee_initials" name="text" value="<?php $name = explode(' ', $_SESSION['fullname']);
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
                                                <input class="form-control" required="required" name="totalamount"
                                                  type="text" value="<?php echo $Podrow['total_amount']; ?>">
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

                                              <script>
                                                function handlePaymentSelectChange(selectElem, invoiceId) {
                                                  const wrapper = selectElem.closest('.payment-type-wrapper');
                                                  const otherInput = wrapper.querySelector('.payment-other-input');

                                                  if (selectElem.value === "Other") {
                                                    otherInput.style.display = "block";
                                                  } else {
                                                    otherInput.style.display = "none";
                                                  }

                                                  updatePaymentTypesHiddenField(invoiceId);
                                                }

                                                function addPaymentTypeField(invoiceId) {
                                                  const container = document.getElementById(`paymentTypeContainer${invoiceId}`);
                                                  const wrapper = document.createElement("div");
                                                  wrapper.className = "payment-type-wrapper mb-3";

                                                  const select = document.createElement("select");
                                                  select.className = "form-select payment-select";
                                                  select.innerHTML = `
      <option value="">-- Select Payment Type --</option>
      <option value="Cash">Cash</option>
      <option value="Check">Check</option>
      <option value="Card">Card</option>
      <option value="ACH">ACH</option>
      <option value="Zelle">Zelle</option>
      <option value="Other">Other</option>
    `;
                                                  select.onchange = function () {
                                                    handlePaymentSelectChange(this, invoiceId);
                                                  };

                                                  const otherInput = document.createElement("input");
                                                  otherInput.type = "text";
                                                  otherInput.className = "form-control payment-other-input mt-2";
                                                  otherInput.placeholder = "Enter Payment Type";
                                                  otherInput.style.display = "none";
                                                  otherInput.oninput = () => updatePaymentTypesHiddenField(invoiceId);

                                                  const removeBtn = document.createElement("button");
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
                                                  const wrappers = document.querySelectorAll(`#paymentTypeContainer${invoiceId} .payment-type-wrapper`);
                                                  const values = Array.from(wrappers).map(wrapper => {
                                                    const select = wrapper.querySelector(".payment-select");
                                                    const otherInput = wrapper.querySelector(".payment-other-input");
                                                    return select.value === "Other"
                                                      ? (otherInput.value.trim() || "Other")
                                                      : select.value;
                                                  }).filter(val => val !== "");
                                                  document.getElementById(`paymentTypeHidden${invoiceId}`).value = JSON.stringify(values);
                                                }

                                                document.addEventListener("DOMContentLoaded", function () {
                                                  const invoiceId = "<?php echo $Podrow['invoice_id']; ?>";
                                                  const addBtn = document.getElementById(`addPaymentTypeBtn${invoiceId}`);

                                                  if (addBtn) {
                                                    addBtn.addEventListener("click", function () {
                                                      addPaymentTypeField(invoiceId);
                                                    });
                                                  }

                                                  const form = document.querySelector(`#addpayment<?php echo $Podrow['invoice_id']; ?> form`);
                                                  if (form) {
                                                    form.addEventListener("submit", function () {
                                                      updatePaymentTypesHiddenField(invoiceId);
                                                    });
                                                  }

                                                  document.querySelectorAll(`#paymentTypeContainer${invoiceId} .payment-select`).forEach(select => {
                                                    select.addEventListener('change', () => handlePaymentSelectChange(select, invoiceId));
                                                  });

                                                  document.querySelectorAll(`#paymentTypeContainer${invoiceId} .payment-other-input`).forEach(input => {
                                                    input.addEventListener('input', () => updatePaymentTypesHiddenField(invoiceId));
                                                  });
                                                });
                                              </script>








                                              <div class="col-md-12 mt-2">
                                                <label for="service_provider" class="form-label">Service
                                                  Provider</label>
                                                <?php
                                                $service_provider_id = $Podrow['service_provider_id'];
                                                $sp_name = '';
                                                if (!empty($service_provider_id)) {
                                                  $sp_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '$service_provider_id'");
                                                  if ($sp_row = mysqli_fetch_assoc($sp_query)) {
                                                    $sp_name = $sp_row['user_name'];
                                                  }
                                                }
                                                ?>
                                                <input type="text" class="form-control" value="<?php echo $sp_name; ?>"
                                                  readonly>
                                              </div>
                                              <div class="col-md-12 mt-2">
                                                <label for="expirydate" class="form-label">Amount Receivable
                                                </label>
                                                <input class="form-control" required="required" name="recievedamount"
                                                  type="text"
                                                  onblur="if(this.value.trim()!==''){var n=parseFloat(this.value.replace(/,/g,''));if(!isNaN(n))this.value=n.toFixed(2);}">
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
                                            <button type="submit" class="btn btn-primary"
                                              name="addpayment">Submit</button>
                                          </div>

                                        </div>
                                      </form>
                                    </div>
                                  </div>




                                  <?php $c++;
                                } ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div id="invoicehistory" class="modal fade" role="dialog">
                      <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <!-- scrollable for vertical overflow -->
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
                                    <th class="text-center">Actions</th>
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
                    <script>
                      if (typeof applyDecimalInputs !== 'function') {
                        function applyDecimalInputs() {
                          $('input[name="unit_price[]"], .sum').each(function () {
                            $(this).attr('step', '0.01');
                            $(this).attr('inputmode', 'decimal');
                          });
                        }
                      }
                    </script>
                    <div class="modal fade" id="associatedProducts">
                      <div class="modal-dialog modal-lg">
                        <form method="POST">
                          <div class="modal-content">

                            <!-- Modal Header -->
                            <div class="modal-header">
                              <h4 class="modal-title">Add Invoice Item</h4>
                            </div>

                            <!-- Modal body -->
                            <div class="modal-body">

                              <div class="form-group mt-2" id="invoiceItemsContainer"></div>


                              <div id="myDIV" style="display:block;">
                                <!-- ===== Searchable service dropdown (INVOICE MODAL) ===== -->
                                <div class="col-md-12 mt-2">
                                  <label for="servicesDropdown" class="form-label">Select Services</label>

                                  <div class="dropdown w-100">
                                    <button class="btn btn-outline-primary dropdown-toggle w-100 text-start"
                                      type="button" id="servicesDropdown" data-bs-toggle="dropdown"
                                      aria-expanded="false">
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
                                            <input type="checkbox" class="form-check-input"
                                              id="addservice<?php echo $mid; ?>" name="vehicle2"
                                              value="<?php echo $mid; ?>">
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
                              <?php
                              /* Users for the "Payment Added By" dropdown in the Invoice History popup.
                                 Value matches the stored format INITIALS(user_id), e.g. MA(166). */
                              $phUsersJs = [];
                              $phUq = mysqli_query($con, "SELECT user_id, user_name FROM users ORDER BY user_name ASC");
                              if ($phUq) {
                                while ($phU = mysqli_fetch_assoc($phUq)) {
                                  $phVal = getInitials($phU['user_name']) . '(' . $phU['user_id'] . ')';
                                  $phUsersJs[] = ['value' => $phVal, 'label' => $phU['user_name'] . ' — ' . $phVal];
                                }
                              }
                              ?>
                              <script>
                                window.phUsers = <?php echo json_encode($phUsersJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                                window.isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;

                                function closeModal() {
                                  $('#invoicehistory').modal('hide');
                                }

                                /* Last-loaded rows for this popup — used to re-render on Cancel. */
                                window.phHistoryData = window.phHistoryData || [];

                                function gethistory(id) {
                                  $.ajax({
                                    url: 'invoicehistory.php',
                                    type: 'GET',
                                    dataType: 'json',
                                    data: { invoice_id: id },
                                    success: function (data) {
                                      if (!Array.isArray(data)) {
                                        data = [];
                                      }
                                      $('#invoicehistory').data('invoice-id', id);
                                      $('#invoicehistory').data('dirty', false);
                                      phRenderHistory(data);
                                      $('#invoicehistory').modal('show');
                                    },
                                    error: function (xhr) {
                                      console.error('Failed to load payment history:', xhr.status, xhr.responseText);
                                      alert('Could not load payment history. Please try again.');
                                    }
                                  });
                                }

                                /* Render the payment rows. Paid Date, Payment Details, Paid Amount and
                                   Payment Method are editable per-row; Balance and Payment Added By are
                                   locked (Balance is derived; Added By is the audit trail). */
                                function phRenderHistory(data) {
                                  window.phHistoryData = Array.isArray(data) ? data : [];
                                  var $tb = $('#invoiceData').empty();

                                  if (!window.phHistoryData.length) {
                                    $tb.append('<tr><td colspan="7" class="text-center text-muted">No payment history found.</td></tr>');
                                    return;
                                  }

                                  window.phHistoryData.forEach(function (item) {
                                    var rawDate = (item.date && item.date !== '0000-00-00') ? item.date : '';
                                    var dispDate = rawDate ? new Date(rawDate + 'T00:00:00').toLocaleDateString('en-US') : 'N/A';
                                    var paid = (item.paid != null) ? item.paid : '';
                                    var bal = (item.balance != null) ? item.balance : '';
                                    var detail = item.detail || '';
                                    var method = item.payment_method || '';

                                    var $tr = $('<tr>')
                                      .attr('data-his-id', item.his_id)
                                      .attr('data-raw-date', rawDate)
                                      .attr('data-paid', paid)
                                      .attr('data-detail', detail)
                                      .attr('data-method', method)
                                      .attr('data-balance', bal)
                                      .attr('data-employee', item.employee || '');

                                    $tr.append($('<td class="ph-date">').text(dispDate));
                                    $tr.append($('<td class="ph-detail">').text(detail));
                                    $tr.append($('<td class="ph-paid">').text(paid));
                                    $tr.append($('<td class="ph-balance">').text(bal));
                                    $tr.append($('<td class="ph-employee">').text(item.employee || ''));
                                    $tr.append($('<td class="ph-method">').text(method || 'N/A'));
                                    var phDisabled = window.isAdmin ? '' : ' disabled title="Admin only"';
                                    $tr.append(
                                      '<td class="ph-actions text-center" style="white-space:nowrap;">' +
                                      '<button type="button" class="btn btn-sm btn-info ph-edit-btn"' + phDisabled + (window.isAdmin ? ' title="Edit"' : '') + '><i class="ti ti-pencil"></i></button> ' +
                                      '<button type="button" class="btn btn-sm btn-danger ph-del-btn"' + phDisabled + (window.isAdmin ? ' title="Delete"' : '') + '><i class="ti ti-trash"></i></button>' +
                                      '</td>'
                                    );
                                    $tb.append($tr);
                                  });
                                }

                                /* Swap a row into edit mode (only the four editable cells become inputs). */
                                function phEnterEdit($tr) {
                                  var rawDate = $tr.attr('data-raw-date') || '';
                                  var paid = $tr.attr('data-paid') || '';
                                  var detail = $tr.attr('data-detail') || '';
                                  var method = $tr.attr('data-method') || '';
                                  var balance = $tr.attr('data-balance') || '';
                                  var employee = $tr.attr('data-employee') || '';

                                  $tr.find('.ph-date').empty().append(
                                    $('<input type="date" class="form-control form-control-sm ph-in-date">').val(rawDate)
                                  );
                                  $tr.find('.ph-detail').empty().append(
                                    $('<input type="text" class="form-control form-control-sm ph-in-detail">').val(detail)
                                  );
                                  $tr.find('.ph-paid').empty().append(
                                    $('<input type="number" min="0" step="0.01" class="form-control form-control-sm ph-in-paid">').val(paid)
                                  );
                                  $tr.find('.ph-balance').empty().append(
                                    $('<input type="number" step="0.01" class="form-control form-control-sm ph-in-balance">').val(balance)
                                  );
                                  $tr.find('.ph-method').empty().append(
                                    $('<input type="text" class="form-control form-control-sm ph-in-method">').val(method)
                                  );

                                  /* Payment Added By: dropdown of users (value = INITIALS(user_id)). */
                                  var $sel = $('<select class="form-control form-control-sm ph-in-employee"></select>');
                                  var matched = false;
                                  (window.phUsers || []).forEach(function (u) {
                                    var $opt = $('<option>').attr('value', u.value).text(u.label);
                                    if (u.value === employee) {
                                      $opt.prop('selected', true);
                                      matched = true;
                                    }
                                    $sel.append($opt);
                                  });
                                  /* keep the current value selectable even if it isn't in the user list */
                                  if (employee && !matched) {
                                    $sel.prepend($('<option>').attr('value', employee).text(employee).prop('selected', true));
                                  }
                                  $tr.find('.ph-employee').empty().append($sel);

                                  $tr.find('.ph-actions').html(
                                    '<button type="button" class="btn btn-sm btn-success ph-save-btn" title="Save"><i class="ti ti-check"></i></button> ' +
                                    '<button type="button" class="btn btn-sm btn-secondary ph-cancel-btn" title="Cancel"><i class="ti ti-x"></i></button>'
                                  );
                                }

                                /* Delegated handlers (rows are rebuilt on every load). */
                                $(document).on('click', '#invoiceData .ph-edit-btn', function () {
                                  phEnterEdit($(this).closest('tr'));
                                });

                                $(document).on('click', '#invoiceData .ph-cancel-btn', function () {
                                  phRenderHistory(window.phHistoryData);
                                });

                                $(document).on('click', '#invoiceData .ph-save-btn', function () {
                                  var $btn = $(this);
                                  var $tr = $btn.closest('tr');
                                  var invId = $('#invoicehistory').data('invoice-id');
                                  $btn.prop('disabled', true);
                                  $.ajax({
                                    url: window.location.href,
                                    type: 'POST',
                                    dataType: 'json',
                                    data: {
                                      ajax_payment_edit: 1,
                                      his_id: $tr.attr('data-his-id'),
                                      invoice_id: invId,
                                      date: $tr.find('.ph-in-date').val(),
                                      paid: $tr.find('.ph-in-paid').val(),
                                      detail: $tr.find('.ph-in-detail').val(),
                                      payment_method: $tr.find('.ph-in-method').val(),
                                      balance: $tr.find('.ph-in-balance').val(),
                                      employee: $tr.find('.ph-in-employee').val()
                                    },
                                    success: function (res) {
                                      if (res && res.ok) {
                                        $('#invoicehistory').data('dirty', true);
                                        phRenderHistory(res.payments);
                                        Swal.fire({ icon: 'success', title: 'Payment updated', timer: 1200, showConfirmButton: false });
                                      } else {
                                        Swal.fire('Error', (res && res.error) || 'Could not save the payment.', 'error');
                                        $btn.prop('disabled', false);
                                      }
                                    },
                                    error: function () {
                                      Swal.fire('Error', 'Could not save the payment. Please try again.', 'error');
                                      $btn.prop('disabled', false);
                                    }
                                  });
                                });

                                $(document).on('click', '#invoiceData .ph-del-btn', function () {
                                  var $tr = $(this).closest('tr');
                                  var hisId = $tr.attr('data-his-id');
                                  var invId = $('#invoicehistory').data('invoice-id');
                                  Swal.fire({
                                    title: 'Delete this payment?',
                                    html: 'This payment will be removed and the invoice <b>balance &amp; status</b> recalculated.<br>' +
                                      '<span style="color:#dc3545;font-size:13px;">This action cannot be undone.</span>',
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonText: '<i class="ti ti-trash"></i> Yes, delete payment',
                                    cancelButtonText: 'Cancel',
                                    confirmButtonColor: '#dc3545',
                                    cancelButtonColor: '#6c757d',
                                    reverseButtons: true
                                  }).then(function (r) {
                                    if (!r.isConfirmed) {
                                      return;
                                    }
                                    $.ajax({
                                      url: window.location.href,
                                      type: 'POST',
                                      dataType: 'json',
                                      data: { ajax_payment_delete: 1, his_id: hisId, invoice_id: invId },
                                      success: function (res) {
                                        if (res && res.ok) {
                                          $('#invoicehistory').data('dirty', true);
                                          phRenderHistory(res.payments);
                                          Swal.fire({ icon: 'success', title: 'Payment deleted', timer: 1200, showConfirmButton: false });
                                        } else {
                                          Swal.fire('Error', (res && res.error) || 'Could not delete the payment.', 'error');
                                        }
                                      },
                                      error: function () {
                                        Swal.fire('Error', 'Could not delete the payment. Please try again.', 'error');
                                      }
                                    });
                                  });
                                });

                                /* Edits/deletes change the invoice's overall totals; reload once the
                                   popup is closed so the main billing table reflects the new figures. */
                                $(document).on('hidden.bs.modal', '#invoicehistory', function () {
                                  if ($(this).data('dirty')) {
                                    window.location.reload();
                                  }
                                });

                                $(document).ready(function () {
                                  applyDecimalInputs();
                                  /* Selecting a service ADDS a line item; it does not stay "checked" —
                                     the box resets itself so the same service can be added again.
                                     Removing a line item is only done via its own delete button. */
                                  $(document).on("click", 'input[name="vehicle2"]', function () {
                                    var $chk = $(this);
                                    var id = $chk.attr('id').replace('addservice', '');
                                    if ($chk.is(":checked")) {
                                      var main_id = $('#main-catt' + id).val();
                                      var sub_total = $('#sub_total').val();
                                      var discount = $('#discount').val();
                                      var grand_total = $('#grand_total').val();
                                      $('#myDIV').hide();
                                      $chk.prop('checked', false);

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
                                          $("#invoiceItemsContainer").append(result);
                                          applyDecimalInputs();
                                        }
                                      });
                                    }
                                  });
                                });
                              </script>

                              <div class="row mb-2">
                                <div class="col-md-12">
                                  <label for="" class="form-label">Service Provider</label>
                                  <select id="Service_Provider" name="Service_Provider" class="form-control select2"
                                    required>
                                    <option value="">Select Service Provider</option>
                                    <?php
                                    $query = "SELECT user_id, user_name FROM users order by user_name ASC";
                                    $result = mysqli_query($con, $query);

                                    while ($user = mysqli_fetch_assoc($result)) {
                                      echo '<option value="' . htmlspecialchars($user['user_id']) . '">' . htmlspecialchars($user['user_name']) . '</option>';
                                    }
                                    ?>
                                  </select>
                                </div>
                              </div>
                              <!-- <?php if ($ach_payer): ?>
                          <div class="col-md-12 mt-2" style="
                            padding: 12px 18px;
                            background-color: #f0f3f5;
                            border: 1px solid #dee2e6;
                            border-radius: 6px;
                            transition: background-color 0.2s ease;
                            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                        ">
                            <label style="
                                font-family: 'Inter', 'Segoe UI', sans-serif;
                                font-size: 1.05em;
                                color: #212529;
                                font-weight: 500;
                                cursor: pointer;
                                display: flex;
                                align-items: center;
                                user-select: none;
                            ">
                              <input type="checkbox" name="mark_ach" value="1" style="
                                        margin-right: 12px;
                                        transform: scale(1.35);
                                        accent-color: #0d6efd;
                                        border: 2px solid #adb5bd;
                                        cursor: pointer;
                                    ">
                              ACH invoice
                            </label>
                          </div>
                        <?php endif; ?> -->
                              <div class="row mb-2">
                                <div class="col-md-12">
                                  <label for="billing_cycle" class="form-label">DUE DATE</label>
                                  <input type="date" class="form-control" required id="billing_cycle"
                                    name="billing_cycle">
                                </div>
                              </div>
                              <div class="row">
                                <div class="col-md-10" style="text-align: end;"><label for="unit_price"
                                    class="form-label"><b>Subtotal</b></label>
                                </div>
                                <div class="col-md-2">
                                  <input class="form-control" required="required" name="sub_total" type="number"
                                    step="0.01" placeholder="Sub Total" id="sub_total" value="0"
                                    onchange="changetotal()" onkeypress="changetotal()" onkeyup="changetotal()">
                                </div>
                              </div>
                              <div class="row">
                                <div class="col-md-10" style="text-align: end;"><label for="unit_price"
                                    class="form-label"></label></div>
                                <div class="col-md-2">
                                  <label for="dissbox" class="form-label"><input type="checkbox" name="dissbox"
                                      id="dissbox" onchange="mydiscountrow()">
                                    Add Discount</label>

                                </div>
                              </div>
                              <div class="row" style="display:none;" id="discountrow">
                                <div class="col-md-10" style="text-align: end;"><label for="unit_price"
                                    class="form-label"><b>Discount</b></label>
                                </div>
                                <div class="col-md-2">
                                  <input class="form-control" required="required" name="discount" type="number"
                                    placeholder="Discount" step="0.01" id="discount" value="0" onchange="getdiscount()"
                                    onkeypress="getdiscount()" onkeyup="getdiscount()">
                                </div>
                              </div>

                              <div class="row">
                                <div class="col-md-10" style="text-align: end;"><label for="unit_price"
                                    class="form-label"><b>Total</b></label>
                                </div>
                                <div class="col-md-2">
                                  <input class="form-control" required="required" name="grand_total" type="number"
                                    placeholder="Grand Total" step="0.01" id="grand_total" value="0">
                                </div>
                              </div>


                            </div>
                            <!-- to send -->
                            <!-- Modal footer -->
                            <div class="modal-footer">

                              <button type="button" class="btn btn-info" data-dismiss="modal"
                                id="closeButton">Close</button>

                              <script>
                                document.getElementById('closeButton').addEventListener('click',
                                  function () {
                                    location.reload(true);
                                  });
                              </script>

                              <button type="submit" class="btn btn-primary" name="createinvoice">Submit</button>
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
                            <h5>Invoice Comments<?= mh_icon('invoice_comments') ?></h5>
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
                                $invoiceCommentsSql = "SELECT new_comments.*,
                                      creator.user_login AS comment_by
                                    FROM new_comments
                                    LEFT JOIN users AS creator ON new_comments.user_id = creator.user_id
                                    WHERE new_comments.member_id='$client_id'
                                    ORDER BY new_comments.comment_id DESC";
                                $invoiceCommentsResult = mysqli_query($con, $invoiceCommentsSql);
                                while ($comment_row = mysqli_fetch_array($invoiceCommentsResult)) {
                                  $commentdate = $comment_row['comment_time'];
                                  $comment_detail = $comment_row["comment_detail"];
                                  $comment_by = $comment_row["comment_by"] ?? 'Unknown';
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
                                    <td style="max-width: 320px; white-space: normal; word-wrap: break-word;">
                                      <div
                                        style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.5em;">
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
                            <h5>Sent Invoice History</h5>
                          </div>
                          <div class="col-md-2"></div>
                        </div>
                        <hr>
                        <div class="row">
                          <div class="table-responsive">
                            <table class="table datatable2" id="emailHistoryTable">
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
                                  $rawType = (string) ($row['type'] ?? '');
                                  $rawSubject = (string) ($row['subject'] ?? '');
                                  $isPaidSubject = stripos($rawSubject, 'Payment Received') !== false;

                                  if (stripos($rawType, 'SMS') !== false) {
                                    $displayType = $isPaidSubject ? 'SMS for paid invoice' : 'SMS for unpaid invoice';
                                  } elseif ($rawType === 'invoice_reminder') {
                                    $displayType = $isPaidSubject ? 'Email for paid invoice' : 'Email for unpaid invoice';
                                  } else {
                                    $displayType = $rawType;
                                  }

                                  if (preg_match('/^Reminder:\s*Invoice\s*#?(\d+)/i', $rawSubject, $m)) {
                                    $displaySubject = 'Unpaid Invoice Sent #' . $m[1];
                                  } elseif (strcasecmp(trim($rawSubject), 'SMS Reminder') === 0) {
                                    $displaySubject = $isPaidSubject ? 'Payment Received via SMS' : 'Unpaid Invoice Sent via SMS';
                                  } else {
                                    $displaySubject = $rawSubject;
                                  }

                                  echo "
                            <tr>
                              <td>" . htmlspecialchars((string) ($row['invoice_id'] ?? '')) . "</td>
                              <td>" . date("m/d/Y h:i A", strtotime($row['sent_at'])) . "</td>
                              <td>" . htmlspecialchars($displayType) . "</td>
                              <td>" . htmlspecialchars($displaySubject) . "</td>
                              <td>" . htmlspecialchars((string) ($row['user_name'] ?? '')) . "</td>
                              <td>" . htmlspecialchars((string) ($row['To_mail'] ?? '')) . "</td>
                              <td>" . htmlspecialchars((string) ($row['From_mail'] ?? '')) . "</td>
                            </tr>";
                                } ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade" id="balance-credit-pane" role="tabpanel"
                    aria-labelledby="balance-credit-tab">
                    <div class="card">
                      <div class="card-body employee-detail-body fulls-card">
                        <div class="row">
                          <div class="col-md-12">
                            <h5><i class="ti ti-edit" style="margin-right:6px;"></i>Create Balance Adjustment</h5>
                          </div>
                        </div>
                        <hr>
                        <form method="post">
                          <div class="row">
                            <div class="col-md-6 mb-3">
                              <label class="form-label">Adjustment Type</label>
                              <select class="form-select" name="adjustment_type">
                                <option value="DEBIT">DEBIT (Manual Charge / Amount Owed)</option>
                                <option value="CREDIT">CREDIT (Client Sent Excess / Credit Balance)</option>
                              </select>
                            </div>
                            <div class="col-md-6 mb-3">
                              <label class="form-label">Link to Invoice (optional)</label>
                              <select class="form-select" name="adjustment_invoice_id">
                                <option value="">&mdash; None &mdash;</option>
                                <?php
                                $adjInvoices = mysqli_query($con, "SELECT invoice_id, due_date, total_amount FROM billing_invoice WHERE mem_id='$client_id' ORDER BY invoice_id DESC");
                                while ($adjInvRow = mysqli_fetch_assoc($adjInvoices)) {
                                  echo '<option value="' . (int) $adjInvRow['invoice_id'] . '">INV-' . (int) $adjInvRow['invoice_id']
                                    . ' — due ' . htmlspecialchars($adjInvRow['due_date']) . ' — $' . number_format((float) $adjInvRow['total_amount'], 2) . '</option>';
                                }
                                ?>
                              </select>
                            </div>
                            <div class="col-md-6 mb-3">
                              <label class="form-label">Total Amount ($)</label>
                              <input type="number" step="0.01" min="0.01" class="form-control" name="adjustment_total_amount" required>
                            </div>
                            <div class="col-md-6 mb-3">
                              <label class="form-label">Total Sent by Client ($)</label>
                              <input type="number" step="0.01" min="0" class="form-control" name="adjustment_sent_amount" required>
                            </div>
                            <div class="col-md-6 mb-3">
                              <label class="form-label">Payment Method</label>
                              <select class="form-select" name="adjustment_payment_method" required>
                                <option value="">-- Select Payment Method --</option>
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                                <option value="Card">Card</option>
                                <option value="ACH">ACH</option>
                                <option value="Zelle">Zelle</option>
                                <option value="Other">Other</option>
                              </select>
                            </div>
                            <div class="col-md-6 mb-3">
                              <label class="form-label">Paid / Adjustment Date</label>
                              <input type="date" class="form-control" name="adjustment_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-12 mb-3">
                              <label class="form-label">Payment Description / Notes</label>
                              <textarea class="form-control" name="adjustment_description" rows="3" placeholder="Enter transaction details or notes..."></textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                              <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="adjGeneratePdf" name="adjustment_generate_pdf" value="1">
                                <label class="form-check-label" for="adjGeneratePdf">Generate PDF receipt</label>
                              </div>
                            </div>
                          </div>
                          <div class="text-end">
                            <button type="submit" name="add_balance_adjustment" class="btn btn-success">
                              <i class="fas fa-plus"></i> Add Entry
                            </button>
                          </div>
                        </form>

                        <hr>

                        <div class="row">
                          <div class="col-md-12">
                            <h5>Balance & Credit Statement</h5>
                          </div>
                        </div>
                        <div class="row">
                          <div class="table-responsive">
                            <table class="table datatable1" id="balanceCreditTable">
                              <thead>
                                <tr>
                                  <th>Date</th>
                                  <th>Type</th>
                                  <th>Amount</th>
                                  <th>Total Sent by Client</th>
                                  <th>Payment Method</th>
                                  <th>Invoice</th>
                                  <th>Description</th>
                                  <th>Actions</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php
                                $adjRows = mysqli_query($con, "SELECT * FROM billing_balance_adjustment WHERE mem_id='$client_id' AND deleted_at IS NULL ORDER BY adjustment_date DESC, id DESC");
                                while ($adjRow = mysqli_fetch_assoc($adjRows)) {
                                  $adjBadgeClass = $adjRow['adjustment_type'] === 'CREDIT' ? 'badge bg-success' : 'badge bg-danger';
                                  $adjPdfPath    = balance_adjustment_pdf_path($adjRow['id'], $client_id);
                                  $adjHasPdf     = file_exists($adjPdfPath);
                                  ?>
                                  <tr>
                                    <td><?= date('m/d/Y', strtotime($adjRow['adjustment_date'])) ?></td>
                                    <td><span class="<?= $adjBadgeClass ?>"><?= htmlspecialchars($adjRow['adjustment_type']) ?></span></td>
                                    <td>$<?= number_format((float) $adjRow['total_amount'], 2) ?></td>
                                    <td>$<?= number_format((float) $adjRow['total_sent_by_client'], 2) ?></td>
                                    <td><?= htmlspecialchars($adjRow['payment_method']) ?></td>
                                    <td><?= $adjRow['invoice_id'] ? 'INV-' . (int) $adjRow['invoice_id'] : 'N/A' ?></td>
                                    <td style="max-width: 260px; white-space: normal; word-wrap: break-word;"><?= htmlspecialchars($adjRow['description']) ?></td>
                                    <td>
                                      <?php if ($adjHasPdf): ?>
                                        <a href="<?= htmlspecialchars($adjPdfPath) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light border" title="View PDF">
                                          <i class="fas fa-file-pdf"></i>
                                        </a>
                                      <?php endif; ?>
                                      <?php if ($is_admin): ?>
                                        <button type="button" class="btn btn-sm btn-light border" title="Edit" data-bs-toggle="modal" data-bs-target="#editAdjModal<?= (int) $adjRow['id'] ?>">
                                          <i class="ti ti-pencil"></i>
                                        </button>
                                        <form method="post" style="display:inline-block" onsubmit="return confirm('Remove this balance adjustment entry?');">
                                          <input type="hidden" name="adjustment_id" value="<?= (int) $adjRow['id'] ?>">
                                          <button type="submit" name="delete_balance_adjustment" class="btn btn-sm btn-light text-danger border" title="Delete">
                                            <i class="fas fa-trash"></i>
                                          </button>
                                        </form>

                                        <div class="modal fade" id="editAdjModal<?= (int) $adjRow['id'] ?>" tabindex="-1">
                                          <div class="modal-dialog modal-lg">
                                            <form method="post">
                                              <div class="modal-content">
                                                <div class="modal-header">
                                                  <h5 class="modal-title">Edit Balance Adjustment</h5>
                                                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                  <input type="hidden" name="adjustment_id" value="<?= (int) $adjRow['id'] ?>">
                                                  <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                      <label class="form-label">Adjustment Type</label>
                                                      <select class="form-select" name="adjustment_type">
                                                        <option value="DEBIT" <?= $adjRow['adjustment_type'] === 'DEBIT' ? 'selected' : '' ?>>DEBIT (Manual Charge / Amount Owed)</option>
                                                        <option value="CREDIT" <?= $adjRow['adjustment_type'] === 'CREDIT' ? 'selected' : '' ?>>CREDIT (Client Sent Excess / Credit Balance)</option>
                                                      </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                      <label class="form-label">Link to Invoice (optional)</label>
                                                      <select class="form-select" name="adjustment_invoice_id">
                                                        <option value="">&mdash; None &mdash;</option>
                                                        <?php
                                                        mysqli_data_seek($adjInvoices, 0);
                                                        while ($adjInvRow = mysqli_fetch_assoc($adjInvoices)) {
                                                          $selected = ((int) $adjRow['invoice_id'] === (int) $adjInvRow['invoice_id']) ? 'selected' : '';
                                                          echo '<option value="' . (int) $adjInvRow['invoice_id'] . '" ' . $selected . '>INV-' . (int) $adjInvRow['invoice_id']
                                                            . ' — due ' . htmlspecialchars($adjInvRow['due_date']) . ' — $' . number_format((float) $adjInvRow['total_amount'], 2) . '</option>';
                                                        }
                                                        ?>
                                                      </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                      <label class="form-label">Total Amount ($)</label>
                                                      <input type="number" step="0.01" min="0.01" class="form-control" name="adjustment_total_amount" value="<?= htmlspecialchars((string) $adjRow['total_amount']) ?>" required>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                      <label class="form-label">Total Sent by Client ($)</label>
                                                      <input type="number" step="0.01" min="0" class="form-control" name="adjustment_sent_amount" value="<?= htmlspecialchars((string) $adjRow['total_sent_by_client']) ?>" required>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                      <label class="form-label">Payment Method</label>
                                                      <select class="form-select" name="adjustment_payment_method" required>
                                                        <option value="">-- Select Payment Method --</option>
                                                        <?php foreach (['Cash', 'Check', 'Card', 'ACH', 'Zelle', 'Other'] as $pm): ?>
                                                          <option value="<?= $pm ?>" <?= $adjRow['payment_method'] === $pm ? 'selected' : '' ?>><?= $pm ?></option>
                                                        <?php endforeach; ?>
                                                      </select>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                      <label class="form-label">Paid / Adjustment Date</label>
                                                      <input type="date" class="form-control" name="adjustment_date" value="<?= htmlspecialchars(date('Y-m-d', strtotime($adjRow['adjustment_date']))) ?>" required>
                                                    </div>
                                                    <div class="col-md-12 mb-3">
                                                      <label class="form-label">Payment Description / Notes</label>
                                                      <textarea class="form-control" name="adjustment_description" rows="3"><?= htmlspecialchars($adjRow['description']) ?></textarea>
                                                    </div>
                                                    <div class="col-md-12 mb-3">
                                                      <div class="form-check">
                                                        <input type="checkbox" class="form-check-input" id="adjGeneratePdf<?= (int) $adjRow['id'] ?>" name="adjustment_generate_pdf" value="1" <?= $adjHasPdf ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="adjGeneratePdf<?= (int) $adjRow['id'] ?>">Generate PDF receipt</label>
                                                      </div>
                                                    </div>
                                                  </div>
                                                </div>
                                                <div class="modal-footer">
                                                  <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                                  <button type="submit" name="update_balance_adjustment" class="btn btn-primary">Save Changes</button>
                                                </div>
                                              </div>
                                            </form>
                                          </div>
                                        </div>
                                      <?php endif; ?>
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

            <!-- ====== Invoice / payment deletion (SweetAlert2) ====== -->
            <script>
              /* Build a hidden POST form on the fly and submit it to the current page.
                 The PHP handlers look for delete_invoice / delete_payment in $_POST. */
              function submitInvoiceDeleteForm(fields) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                Object.keys(fields).forEach(function (k) {
                  var input = document.createElement('input');
                  input.type = 'hidden';
                  input.name = k;
                  input.value = fields[k];
                  form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
              }

              /* Step 1 — ask what to delete: the whole invoice or one of its payments. */
              function openInvoiceDelete(invoiceId) {
                Swal.fire({
                  title: 'Delete Invoice #' + invoiceId,
                  html: 'What would you like to delete?<br>' +
                    '<span style="font-size:13px;color:#6c757d;">Remove the entire invoice, or just one of its recorded payments.</span>',
                  icon: 'question',
                  showCancelButton: true,
                  showDenyButton: true,
                  confirmButtonText: '<i class="ti ti-trash"></i> Delete the Invoice',
                  denyButtonText: '<i class="ti ti-cash"></i> Delete the Added Payment',
                  cancelButtonText: 'Cancel',
                  confirmButtonColor: '#dc3545',
                  denyButtonColor: '#f0ad4e',
                  cancelButtonColor: '#6c757d',
                  focusConfirm: false
                }).then(function (r) {
                  if (r.isConfirmed) {
                    swalConfirmDeleteInvoice(invoiceId);
                  } else if (r.isDenied) {
                    openPaymentsManager(invoiceId);
                  }
                });
              }

              /* Step 2a — final confirmation before deleting the whole invoice. */
              function swalConfirmDeleteInvoice(invoiceId) {
                Swal.fire({
                  title: 'Delete the entire invoice?',
                  html: 'Invoice <b>#' + invoiceId + '</b> and all of its recorded payments will be permanently deleted.<br>' +
                    '<span style="color:#dc3545;font-size:13px;">This action cannot be undone.</span>',
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonText: '<i class="ti ti-trash"></i> Yes, delete invoice',
                  cancelButtonText: 'Cancel',
                  confirmButtonColor: '#dc3545',
                  cancelButtonColor: '#6c757d',
                  reverseButtons: true
                }).then(function (r) {
                  if (r.isConfirmed) {
                    submitInvoiceDeleteForm({ invoice_id: invoiceId, delete_invoice: '1' });
                  } else {
                    openInvoiceDelete(invoiceId); // back to the choice screen
                  }
                });
              }

              /* Step 2b — list the recorded payments (mirrors the Payment History view)
                 with a delete action on each row. */
              function openPaymentsManager(invoiceId) {
                var payments = (window.invoicePaymentData && window.invoicePaymentData[invoiceId]) || [];
                if (!payments.length) {
                  Swal.fire({
                    icon: 'info',
                    title: 'No Payments Recorded',
                    text: 'There are no recorded payments to delete for invoice #' + invoiceId + '.',
                    confirmButtonColor: '#6c757d'
                  });
                  return;
                }

                var rows = payments.map(function (p) {
                  return '<tr>' +
                    '<td style="white-space:nowrap;">' + p.date + '</td>' +
                    '<td style="text-align:left;">' + p.detail + '</td>' +
                    '<td style="text-align:right;white-space:nowrap;">$' + p.paid + '</td>' +
                    '<td style="text-align:right;white-space:nowrap;">$' + p.balance + '</td>' +
                    '<td>' + p.employee + '</td>' +
                    '<td>' + p.method + '</td>' +
                    '<td style="text-align:center;">' +
                    '<button type="button" class="btn btn-sm btn-danger" title="Delete this payment" ' +
                    'onclick="swalConfirmDeletePayment(' + invoiceId + ',' + p.his_id + ')">' +
                    '<i class="ti ti-trash"></i></button></td>' +
                    '</tr>';
                }).join('');

                var html =
                  '<div class="table-responsive" style="max-height:55vh;overflow:auto;">' +
                    '<table class="table table-bordered table-striped" style="margin-bottom:0;font-size:14px;text-align:center;vertical-align:middle;">' +
                      '<thead><tr>' +
                        '<th>Paid Date</th><th>Payment Details</th><th>Paid</th><th>Balance</th>' +
                        '<th>Added By</th><th>Method</th><th>Action</th>' +
                      '</tr></thead>' +
                      '<tbody>' + rows + '</tbody>' +
                    '</table>' +
                  '</div>';

                Swal.fire({
                  title: 'Delete Added Payment — Invoice #' + invoiceId,
                  html: html,
                  width: 880,
                  showConfirmButton: false,
                  showCloseButton: true,
                  showCancelButton: true,
                  cancelButtonText: 'Close',
                  cancelButtonColor: '#6c757d'
                });
              }

              /* Step 3 — final confirmation before deleting a single payment. */
              function swalConfirmDeletePayment(invoiceId, hisId) {
                Swal.fire({
                  title: 'Delete this payment?',
                  html: 'This payment will be removed and the invoice <b>balance &amp; status</b> will be recalculated.<br>' +
                    '<span style="color:#dc3545;font-size:13px;">This action cannot be undone.</span>',
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonText: '<i class="ti ti-trash"></i> Yes, delete payment',
                  cancelButtonText: 'Cancel',
                  confirmButtonColor: '#dc3545',
                  cancelButtonColor: '#6c757d',
                  reverseButtons: true
                }).then(function (r) {
                  if (r.isConfirmed) {
                    submitInvoiceDeleteForm({ invoice_id: invoiceId, his_id: hisId, delete_payment: '1' });
                  } else {
                    openPaymentsManager(invoiceId); // back to the payment list
                  }
                });
              }
            </script>

            <script>
              function sendReminder(invoiceId, statusValue) {
                if (prefsMissing) {
                  Swal.fire("⚠️ Preferences Missing", "Client preferences not set. Please enable email reminders in settings.", "warning");
                  return;
                }
                if (!clientPrefs.emailEnabled) {
                  Swal.fire("⛔ Blocked", "Email reminders are disabled for this client.", "info");
                  return;
                }
                if (!clientPrefs.emailEnabled) {
                  Swal.fire({
                    title: "⛔ Blocked",
                    html: "<b>Email reminders are disabled for this client.</b>",
                    icon: "info",
                    confirmButtonColor: "#9e9e9e",
                    background: "#fff",
                    color: "#333",
                    customClass: { popup: "rounded-4 shadow-md p-3" }
                  });
                  return;
                }

                const status = (statusValue || "").toLowerCase().replace(/\s+/g, " ").trim();
                let titleText = "📩 Send Unpaid Invoice Reminder";
                let bodyText = "Do you want to send a reminder?";
                if (status === "paid") {
                  titleText = "Payment Received Confirmation";
                  bodyText = "Do you want to send the confirmation email?";
                } else if (status === "partial paid" || status === "partial") {
                  titleText = "📩 Send Partial Invoice Reminder";
                  bodyText = "Do you want to send a reminder?";
                }

                const recipientEmail = clientPrefs.email && clientPrefs.email.trim() !== ""
                  ? clientPrefs.email
                  : "<span class='text-danger'>No email on file</span>";
                const recipientHtml =
                  "<div style='margin-top:6px;font-size:0.9em;color:#555;'>" +
                  "<i class='ti ti-mail'></i> Will be sent to: <b>" + recipientEmail + "</b>" +
                  "</div>";

                Swal.fire({
                  title: titleText,
                  html: "<b>Invoice #" + invoiceId + "</b><br>" + bodyText + recipientHtml,
                  icon: "question",
                  background: "#fff",
                  color: "#333",
                  showCancelButton: true,
                  confirmButtonColor: "#272469",
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
                  if (result.isConfirmed) {
                    Swal.fire({
                      title: "⏳ Sending...",
                      html: "<small>Please wait while we send your reminder.</small>",
                      allowOutsideClick: false,
                      showConfirmButton: false,
                      background: "#fff",
                      color: "#333",
                      customClass: { popup: "rounded-4 shadow-md p-3" },
                      didOpen: () => Swal.showLoading()
                    });

                    $.ajax({
                      url: "send_inv_reminder.php",
                      type: "POST",
                      data: { invoice_id: invoiceId },
                      success: function (response) {
                        var isFailure = /invalid|not found|failed|no email/i.test(response);
                        Swal.fire({
                          title: isFailure ? "⚠️ Failed!" : "✅ Reminder Sent!",
                          html: "<b>" + response + "</b>",
                          icon: isFailure ? "error" : "success",
                          confirmButtonColor: isFailure ? "#dc3545" : "#272469",
                          background: "#fff",
                          color: "#333",
                          customClass: { popup: "rounded-4 shadow-md p-3" }
                        });
                      },
                      error: function () {
                        Swal.fire({
                          title: "⚠️ Failed!",
                          html: "There was a problem sending the reminder.",
                          icon: "error",
                          confirmButtonColor: "#272469",
                          background: "#fff",
                          color: "#333",
                          customClass: { popup: "rounded-4 shadow-md p-3" }
                        });
                      }
                    });
                  }
                });
              }
              /* Sends the payment confirmation email immediately, without the
                 extra "are you sure" prompt — the caller (the post-payment
                 popup) already asked the user once. */
              function sendPaymentConfirmationNow(invoiceId) {
                if (prefsMissing) {
                  Swal.fire("⚠️ Preferences Missing", "Client preferences not set. Please enable email reminders in settings.", "warning");
                  return;
                }
                if (!clientPrefs.emailEnabled) {
                  Swal.fire({
                    title: "⛔ Blocked",
                    html: "<b>Email reminders are disabled for this client.</b>",
                    icon: "info",
                    confirmButtonColor: "#9e9e9e",
                    background: "#fff",
                    color: "#333",
                    customClass: { popup: "rounded-4 shadow-md p-3" }
                  });
                  return;
                }

                Swal.fire({
                  title: "⏳ Sending...",
                  html: "<small>Please wait while we send your payment confirmation.</small>",
                  allowOutsideClick: false,
                  showConfirmButton: false,
                  background: "#fff",
                  color: "#333",
                  customClass: { popup: "rounded-4 shadow-md p-3" },
                  didOpen: () => Swal.showLoading()
                });

                $.ajax({
                  url: "send_inv_reminder.php",
                  type: "POST",
                  data: { invoice_id: invoiceId },
                  success: function (response) {
                    var isFailure = /invalid|not found|failed|no email/i.test(response);
                    Swal.fire({
                      title: isFailure ? "⚠️ Failed!" : "✅ Confirmation Sent!",
                      html: "<b>" + response + "</b>",
                      icon: isFailure ? "error" : "success",
                      confirmButtonColor: isFailure ? "#dc3545" : "#272469",
                      background: "#fff",
                      color: "#333",
                      customClass: { popup: "rounded-4 shadow-md p-3" }
                    });
                  },
                  error: function () {
                    Swal.fire({
                      title: "⚠️ Failed!",
                      html: "There was a problem sending the confirmation email.",
                      icon: "error",
                      confirmButtonColor: "#272469",
                      background: "#fff",
                      color: "#333",
                      customClass: { popup: "rounded-4 shadow-md p-3" }
                    });
                  }
                });
              }

              /* ----------  WhatsApp reminder ---------- */
              /* ----------  UNIFIED WHATSAPP / SMS REMINDER  ---------- */
              function sendWhatsAppReminder(invoiceId) {

                /* ---- same guards you already had ---- */
                if (prefsMissing) {
                  Swal.fire("⚠️ Preferences Missing",
                    "Client preferences not set. Please enable reminders in settings.", "warning");
                  return;
                }
                if (!clientPrefs.smsEnabled) {
                  Swal.fire("⛔ Blocked", "WhatsApp / SMS reminders are disabled for this client.", "info");
                  return;
                }

                Swal.fire({
                  title: "💬 SEND PAYMENT CONFIRMATION EMAIL",
                  html: `<b>Invoice #${invoiceId}</b><br>Choose how to deliver the reminder:`,
                  icon: "question",
                  showCancelButton: true,
                  confirmButtonColor: "#25D366",
                  cancelButtonColor: "#9e9e9e",
                  confirmButtonText: '<i class="fa fa-whatsapp"></i> WhatsApp',
                  cancelButtonText: "Cancel",
                  /* second button */
                  showDenyButton: true,
                  denyButtonColor: "#0d6efd",
                  denyButtonText: '<i class="fa fa-envelope"></i> SMS / SIM',

                  reverseButtons: true
                }).then((result) => {

                  /* user picked WhatsApp */
                  if (result.isConfirmed) {
                    sendReminderAjax(invoiceId, "whatsapp");
                  }
                  /* user picked SMS */
                  else if (result.isDenied) {
                    sendReminderAjax(invoiceId, "sms");
                  }
                  /* cancel – do nothing */
                });
              }

              /* ---- generic helper that calls the proper endpoint ---- */
              function sendReminderAjax(invoiceId, type /* "whatsapp" | "sms" */) {
                Swal.fire({
                  title: "⏳ Sending…",
                  html: "<small>Please wait while we send your reminder.</small>",
                  allowOutsideClick: false,
                  showConfirmButton: false,
                  didOpen: () => Swal.showLoading()
                });

                $.ajax({
                  url: type === "whatsapp"
                    ? "send_whatsapp_reminder.php"
                    : "send_sim_reminder.php",   // your existing SMS file
                  type: "POST",
                  data: { invoice_id: invoiceId },
                  success: function (resp) {
                    var isFailure = /invalid|not found|failed|no email|no phone|error/i.test(resp);
                    Swal.fire({
                      title: isFailure ? "⚠️ Failed" : "✅ Sent!",
                      html: `<b>${resp}</b>`,
                      icon: isFailure ? "error" : "success"
                    });
                  },
                  error: function (xhr) {
                    Swal.fire({
                      title: "⚠️ Failed",
                      html: xhr.responseText,
                      icon: "error"
                    });
                  }
                });
              }

              function sendManualSms(clientId) {
                const recipientPhone = clientPrefs.phone && clientPrefs.phone.trim() !== ""
                  ? clientPrefs.phone
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

              // Action alert (reminder calendar)
              const CLIENT_ID = <?= (int) $_GET['id'] ?>;

              function openActionAlert() {
                Swal.fire({
                  title: 'Choose an action',
                  icon: 'question',
                  showCancelButton: true,
                  confirmButtonText: 'Add',
                  showDenyButton: true,
                  denyButtonText: 'Edit',
                  confirmButtonColor: '#28a745',
                  denyButtonColor: '#ffc107',
                  cancelButtonColor: '#6c757d',
                  didOpen: () => {
                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'swal2-confirm swal2-styled';
                    deleteBtn.style.backgroundColor = '#dc3545';
                    deleteBtn.innerText = 'Delete';
                    deleteBtn.onclick = () => { Swal.close(); loadExisting('delete'); };
                    Swal.getConfirmButton().parentNode.insertBefore(deleteBtn, Swal.getDenyButton());
                  }
                }).then(r => {
                  if (r.isConfirmed) handleAdd();
                  else if (r.isDenied) loadExisting('edit');
                });
              }

              function handleAdd() {
                reminderForm('Add Reminder', 'add');
              }

              function loadExisting(actionType) {
                $.post('mem_rem_clndr.php', { action: 'get', client_id: CLIENT_ID }, res => {
                  if (res.status !== 'ok') {
                    Swal.fire('No reminder found', '', 'info');
                    return;
                  }
                  reminderForm(
                    actionType === 'edit' ? 'Edit Reminder' : 'Delete Reminder',
                    actionType,
                    res.data
                  );
                }, 'json');
              }

              function reminderForm(title, action, data = {}) {
                Swal.fire({
                  title: title,
                  html: `
          <input id="reminder_title" class="swal2-input" placeholder="Title" value="${data.reminder_title || ''}" ${action === 'delete' ? 'disabled' : ''}>
          <textarea id="reminder_description" class="swal2-textarea" placeholder="Description" ${action === 'delete' ? 'disabled' : ''}>${data.reminder_description || ''}</textarea>
          <input id="reminder_date" type="datetime-local" class="swal2-input" value="${data.reminder_date ? data.reminder_date.replace(' ', 'T') : ''}" ${action === 'delete' ? 'disabled' : ''}>
        `,
                  confirmButtonText: action === 'delete' ? 'Delete' : 'Save',
                  confirmButtonColor: action === 'delete' ? '#dc3545' : '#28a745',
                  preConfirm: () => ({
                    action: action,
                    client_id: CLIENT_ID,
                    reminder_id: data.reminder_id,
                    reminder_title: document.getElementById('reminder_title').value,
                    reminder_description: document.getElementById('reminder_description').value,
                    reminder_date: document.getElementById('reminder_date').value
                  })
                }).then(r => {
                  if (r.isConfirmed) ajaxAction(r.value);
                });
              }

              function ajaxAction(data) {
                $.post('mem_rem_clndr.php', data, res => {
                  Swal.fire(res.message, '', res.status === 'ok' ? 'success' : 'error');
                }, 'json').fail(() => Swal.fire('Request failed', '', 'error'));
              }
            </script>

            <script>
              const clientPrefs = {
                smsEnabled: <?= $sms_enabled ?? 1 ?>,
                emailEnabled: <?= $email_enabled ?? 1 ?>,
                email: <?= json_encode($cmem['prm_email'] ?: ($cmem['sec_email'] ?? '')) ?>,
                phone: <?= json_encode($cmem['cell_ph'] ?? '') ?>
              };
              let prefsMissing = <?= $prefs_missing ? 'true' : 'false' ?>;

            </script>

            <script>
              // SMS/Email toggle
              document.querySelectorAll(".sms-switch, .email-switch").forEach(function (toggle) {
                toggle.addEventListener("change", function () {
                  const changed = this;
                  let clientId = changed.dataset.clientId;
                  let smsEnabled = document.getElementById("smsSwitch" + clientId).checked ? 1 : 0;
                  let emailEnabled = document.getElementById("emailSwitch" + clientId).checked ? 1 : 0;
                  changed.disabled = true;
                  fetch("update_sms_email_btn.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `toggle_buttons=1&client_id=${clientId}&sms_toggle=${smsEnabled}&email_toggle=${emailEnabled}`
                  })
                    .then(function (res) { return res.text(); })
                    .then(function (text) {
                      if (!/success/i.test(text)) throw new Error(text);
                      // Keep in-memory prefs in sync so reminders respect the new state without a page reload
                      clientPrefs.smsEnabled = smsEnabled;
                      clientPrefs.emailEnabled = emailEnabled;
                      prefsMissing = false;
                    })
                    .catch(function (err) {
                      // Revert the switch so the UI matches what's actually saved
                      changed.checked = !changed.checked;
                      console.error("Failed to save reminder preference:", err);
                      Swal.fire("⚠️ Save failed", "Could not update the reminder preference. Please try again.", "error");
                    })
                    .finally(function () {
                      changed.disabled = false;
                    });
                });
              });

              // Activity switch
              $(document).ready(function () {
                $('.activity-switch').change(function () {
                  var memberId = $(this).data('member-id');
                  var status = $(this).is(':checked') ? 1 : 0;

                  $.ajax({
                    url: 'update_status.php',
                    type: 'POST',
                    data: { member_id: memberId, status: status },
                    success: function () {
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
                Swal.fire({
                  title: 'Edit warning', input: 'textarea',
                  inputValue: document.querySelector('.alert-warning span')?.textContent.trim() || '',
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

            <?php if ($show_payment_email_popup && $payment_email_invoice_id > 0): ?>
              <script>
                document.addEventListener("DOMContentLoaded", function () {
                  const invoiceId = <?= (int) $payment_email_invoice_id ?>;
                  Swal.fire({
                    title: "Send Payment Confirmation Email",
                    html: "<b>Invoice #" + invoiceId + "</b><br>Do you want to send it now?",
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonColor: "#272469",
                    cancelButtonColor: "#9e9e9e",
                    confirmButtonText: "Send Now",
                    cancelButtonText: "Not Now",
                    reverseButtons: true
                  }).then((result) => {
                    if (result.isConfirmed) {
                      sendPaymentConfirmationNow(invoiceId);
                    }
                  });
                });
              </script>
            <?php endif; ?>

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

        <!-- Warning Modal -->
        <div class="modal fade" id="modalWarning" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Alert / Warning</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <textarea id="warningText" class="form-control" rows="4"
                  placeholder="Type the warning everyone must read..."><?= $warn['warning'] ?? '' ?></textarea>
              </div>
              <div class="modal-footer">
                <button class="btn btn-warning" onclick="saveWarning()">Save Alert</button>
              </div>
            </div>
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
                      <input name="user_bank" type="text" class="form-control my-2" placeholder="Enter username"
                        required>
                      <label class="form-label">Password</label>
                      <input name="pass_bank" type="password" class="form-control" placeholder="Enter password"
                        required>
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
          /* ----------  turn every invoice-comment textarea into CKE 5  ---------- */
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
                licenseKey: ''               // free build
              })
              .then(editor => {
                /* store instance so quick-phrase still works */
                tx.ckeditorInstance = editor;
              })
              .catch(console.error);
          });

          /* ----------  quick-phrase buttons (unchanged behaviour) ---------- */

        </script>
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
    const defaultZoom = 100;
    let previewZoom = defaultZoom;

    const $overlay = $(`
         <div id="pdfPreviewOverlay"
         style="position:fixed; top:0; left:0; width:100%; height:100%;
         background:rgba(0,0,0,.65); z-index:9999; display:none;">
         <div id="pdfPreviewPanel" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
         width:86%; max-width:1000px; height:90%; max-height:900px;
         background:#fff; border-radius:6px; overflow:hidden;
         box-shadow:0 5px 25px rgba(0,0,0,.3);">
      
      <button id="closePdfPreview"
      style="position:absolute; right:8px; top:8px; z-index:2;
      border:none; background:#e74c3c; color:#fff;
      padding:4px 10px; border-radius:3px; cursor:pointer;">&times;</button>
      
      <div id="pdfPreviewViewport" style="position:absolute; top:0; left:0; width:100%; height:100%; overflow:auto;">
      <iframe id="pdfPreviewFrame" src="" width="100%" height="100%" frameborder="0"
      style="position:absolute; 
      top:0; left:0;
      transform-origin:top left;
      transition:transform .12s ease;">
      </iframe>
      </div>
    </div>
  </div>`).appendTo('body');

    let hoverTimer = null;

    function applyPreviewZoom() {
      const scale = previewZoom / 100;
      $('#pdfPreviewFrame').css({
        transform: 'scale(' + scale + ')',
        width: (100 / scale) + '%',
        height: (100 / scale) + '%'
      });
      $('#pdfZoomValue').text(previewZoom + '%');
    }

    function changeZoom(step) {
      previewZoom = Math.max(60, Math.min(220, previewZoom + step));
      applyPreviewZoom();
    }

    $(document).on('click', '#pdfZoomIn', function () {
      changeZoom(10);
    });

    $(document).on('click', '#pdfZoomOut', function () {
      changeZoom(-10);
    });

    $(document).on('click', '#pdfZoomReset', function () {
      previewZoom = defaultZoom;
      applyPreviewZoom();
    });

    $(document).on('wheel', '#pdfPreviewViewport', function (e) {
      e.preventDefault();
      changeZoom(e.originalEvent.deltaY < 0 ? 10 : -10);
    });

    $(document).on('mouseenter', '.view-file-preview', function () {
      const pdfPath = 'invoices/' + $(this).data('file');   // file name we attach below
      clearTimeout(hoverTimer);
      hoverTimer = setTimeout(function () {
        previewZoom = defaultZoom;
        applyPreviewZoom();
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
<script>/* ----------  DOWNLOAD INVOICE-HISTORY PDF  ---------- */
  $(document).on('click', '#btnDownloadHistory', function () {
    const invId = $('#invoicehistory').data('invoice-id');   // we store it when the modal opens
    if (!invId) return;

    const url = 'invoice_history_pdf.php?invoice_id=' + invId;

    const $overlay = $('#pdfPreviewOverlay');
    if ($overlay.length) {
      $overlay.fadeIn(180);
      $('#pdfPreviewFrame').attr('src', url);
    } else {
      window.open(url, '_blank');          // fallback: stream the PDF
    }
  });</script>
<script>
  if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
  }
</script>
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


  function sendUnpaidEmailHistory(clientId) {
    Swal.fire({
      title: 'Send Unpaid History?',
      text: 'Email the UN-PAID invoice history to this client?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Send',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#28a745',
      reverseButtons: true,
    }).then((result) => {
      if (!result.isConfirmed) return;

      Swal.fire({
        title: 'Sending...',
        html: 'Please wait while we send the email.',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      $.ajax({
        url: 'email_history_handler.php',
        type: 'POST',
        data: {
          client_id: clientId,
          type: 'unpaid'
        },
        dataType: 'json',
        success: function (result) {
          Swal.close();
          if (result.success) {
            Swal.fire({
              icon: 'success',
              title: 'Success!',
              text: result.message,
              timer: 2000,
              showConfirmButton: false
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: result.message || 'Unknown error occurred'
            });
          }
        },
        error: function (xhr, status, error) {
          Swal.close();
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to send email: ' + error
          });
        }
      });
    });
  }

  function sendEmailHistory(clientId) {
    Swal.fire({
      title: 'Which invoice history?',
      text: 'Do you want to send PAID or UN-PAID invoices?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Paid',
      cancelButtonText: 'Un-paid',
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#dc3545',
      reverseButtons: true,
    }).then((result) => {
      let type = '';
      if (result.isConfirmed) {
        type = 'paid';
      } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
        type = 'unpaid';
      } else {
        return;
      }

      // Show loading spinner
      Swal.fire({
        title: 'Sending...',
        html: 'Please wait while we send the email.',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      // Send AJAX
      $.ajax({
        url: 'email_history_handler.php',
        type: 'POST',
        data: {
          client_id: clientId,
          type: type
        },
        dataType: 'json',
        success: function (result) {
          Swal.close();
          if (result.success) {
            Swal.fire({
              icon: 'success',
              title: 'Success!',
              text: result.message,
              timer: 2000,
              showConfirmButton: false
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: result.message || 'Unknown error occurred'
            });
          }
        },
        error: function (xhr, status, error) {


          // ✅ Close loading first
          Swal.close();

          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to send email: ' + error
          });
        }
      });
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



</script>
<?php echo getStripeJavaScript(); ?>
<?php include 'highlighter.php'; ?>

</html>