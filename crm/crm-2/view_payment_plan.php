<?php
/* =============================================================================
 * Payment Plan — View (read-only detail of a saved plan)
 * -----------------------------------------------------------------------------
 * Reads a saved plan (payment_plans + payment_plan_items) by ?plan_id and
 * renders the summary cards, tabs, plan summary sidebar and the upcoming
 * installment schedule. Created by the Payment Plan create form (payment_plan.php).
 * ===========================================================================*/

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

include "includes/db.php";
include "includes/auth.php";
include "includes/activity_log.php";
require_once 'includes/customizer.php';
require_once 'includes/report_utils.php';

$userid         = $_SESSION['user_id'] ?? 0;
$user_full_name = $_SESSION['fullname'] ?? '';
$plan_id        = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : 0;

/* ---- Fetch the plan ----------------------------------------------------- */
$plan = null;
if ($plan_id > 0) {
    $pr = mysqli_query($con, "SELECT * FROM payment_plans WHERE plan_id=" . $plan_id . " LIMIT 1");
    if ($pr) { $plan = mysqli_fetch_assoc($pr); }
}

$client_id = $plan ? (int) $plan['client_id'] : 0;
$stat      = 0;

/* ---- Client + meta ------------------------------------------------------ */
$client_name = '';
if ($client_id > 0) {
    $cm = mysqli_fetch_assoc(mysqli_query($con, "SELECT name, mname, lname, bus_name, status FROM members WHERE member_id='" . $client_id . "' LIMIT 1"));
    if ($cm) {
        $full = trim(($cm['name'] ?? '') . ' ' . ($cm['mname'] ?? '') . ' ' . ($cm['lname'] ?? ''));
        $client_name = $cm['bus_name'] ?: $full;
        $stat = (int) ($cm['status'] ?? 0);
    }
}

/* ---- Items -------------------------------------------------------------- */
$items = [];
$total = 0.0; $brk_m = 0.0; $brk_q = 0.0;
if ($plan) {
    $ir = mysqli_query($con, "SELECT * FROM payment_plan_items WHERE plan_id=" . $plan_id . " ORDER BY sort_order ASC, item_id ASC");
    if ($ir) {
        while ($it = mysqli_fetch_assoc($ir)) {
            $items[] = $it;
            $amt = (float) $it['amount'];
            $total += $amt;
            if ($it['frequency'] === 'Monthly')        { $brk_m += $amt; }
            elseif ($it['frequency'] === 'Quarterly')  { $brk_q += $amt; }
        }
    }
}

/* ---- Created / updated by ----------------------------------------------- */
function pp_user_name($con, $id)
{
    $id = (int) $id;
    if ($id <= 0) return '';
    $u = mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id='" . $id . "' LIMIT 1"));
    return $u['user_name'] ?? '';
}
$created_by_name = $plan ? pp_user_name($con, $plan['created_by']) : '';
$updated_by_name = $plan ? pp_user_name($con, $plan['updated_by']) : '';

/* ---- Helpers ------------------------------------------------------------ */
function pp_d($d, $fmt = 'M d, Y')
{
    if (empty($d) || $d === '0000-00-00' || $d === '0000-00-00 00:00:00') return '—';
    $ts = strtotime($d);
    return $ts ? date($fmt, $ts) : '—';
}
function pp_money($n) { return '$' . number_format((float) $n, 2); }

/* ---- Build the upcoming installment schedule ---------------------------- */
$installments = [];
if ($plan && !empty($plan['next_installment_date']) && $plan['next_installment_date'] !== '0000-00-00') {
    $freq  = $plan['installment_frequency'] ?: 'Monthly';
    $amt   = (float) $plan['installment_amount'];
    $cur   = strtotime($plan['next_installment_date']);
    $hasEnd = (!empty($plan['end_date']) && $plan['end_date'] !== '0000-00-00');
    $end   = $hasEnd ? strtotime($plan['end_date']) : strtotime('+1 year', $cur);

    $step = ['Monthly' => '+1 month', 'Quarterly' => '+3 months', 'Annually' => '+1 year', 'Weekly' => '+1 week'];
    $inc  = $step[$freq] ?? '+1 month';

    $i = 0;
    while ($cur <= $end && $i < 60) {
        $installments[] = [
            'date'   => date('Y-m-d', $cur),
            'amount' => $amt,
            'status' => $i === 0 ? 'Upcoming' : 'Scheduled',
        ];
        $cur = strtotime($inc, $cur);
        $i++;
    }
}

$is_active = $plan && strcasecmp($plan['status'] ?? '', 'Active') === 0;

/* ===========================================================================
 * Invoice generation from the installment schedule.
 * Creates real billing_invoice + billing_invoice_items rows using the exact
 * same insert pattern as client_billing.php / show_projects.php, writes the
 * PDF to invoices/ and links it, then records the plan <-> invoice mapping
 * in payment_plan_invoices. New table only — existing tables untouched.
 * ===========================================================================*/

/* ---- New table for this module (existing tables untouched) --------------- */
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `payment_plan_invoices` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `plan_id` INT(11) NOT NULL,
  `installment_no` INT(11) NOT NULL,
  `due_date` DATE DEFAULT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `invoice_id` INT(11) NOT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ppi_plan_inst` (`plan_id`, `installment_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ---- Existing invoices already generated from this plan ------------------ */
$plan_invoices = [];
if ($plan_id > 0) {
    $piq = mysqli_query($con, "SELECT t.installment_no, t.invoice_id, bi.filelink
                               FROM payment_plan_invoices t
                               LEFT JOIN billing_invoice bi ON bi.invoice_id = t.invoice_id
                               WHERE t.plan_id=" . $plan_id . " ORDER BY t.installment_no ASC");
    if ($piq) {
        while ($prow = mysqli_fetch_assoc($piq)) {
            $plan_invoices[(int) $prow['installment_no']] = $prow;
        }
    }
}

/* ---- local initials helper (getInitials is not loaded on this page) ------ */
function pp_initials($name, $uid = 0)
{
    $initials = '';
    foreach (explode(' ', (string) $name) as $word) {
        if ($word !== '') {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return $initials . ($uid > 0 ? '(' . $uid . ')' : '');
}

/* ---- create one billing_invoice + items + PDF + mapping (mirrors client_billing.php) ---- */
function pp_create_installment_invoice($con, $plan, $due_date, $install_no, $userid, $user_full_name)
{
    $client_id = (int) $plan['client_id'];
    $plan_id   = (int) $plan['plan_id'];
    $amount    = (float) $plan['installment_amount'];
    if ($amount <= 0 || $client_id <= 0) {
        return array('ok' => false, 'error' => 'Invalid plan amount or client.');
    }

    $invoice_date  = date("m/d/Y h:i:s");
    $invoice_date2 = date("Y-m-d h:i:s");
    $amount_fmt    = number_format($amount, 2, '.', '');
    $emp_initials  = pp_initials($user_full_name, $userid);
    $billing_cycle = !empty($plan['billing_cycle']) ? $plan['billing_cycle'] : '';
    $is_ach        = (strcasecmp((string) $plan['payment_method'], 'ACH') === 0) ? 1 : 0;
    $due_sql       = mysqli_real_escape_string($con, $due_date);

    /* 1. header (same insert pattern as client_billing.php:563) */
    $ins = "INSERT INTO billing_invoice(mem_id, billing_id, temp_id, invoice_date, filelink,
                 due_date, total_amount, paid_amount, discount, balance,
                 invoice_status, payment_type, payment_details, paid_date,
                 billing_cycle, service_provider_id, is_ach, employee_initials)
           VALUES ('$client_id','0','0','$invoice_date','',
                   '$due_sql','$amount_fmt','0','0.00','$amount_fmt',
                   '1','0','0','0000-00-00',
                   '" . mysqli_real_escape_string($con, $billing_cycle) . "','0','$is_ach',
                   '" . mysqli_real_escape_string($con, $emp_initials) . "')";
    mysqli_query($con, $ins);
    $invoice_no = (int) mysqli_insert_id($con);
    if ($invoice_no <= 0) {
        return array('ok' => false, 'error' => 'Failed to insert invoice header.');
    }

    customizer_save_posted_values($con, 'invoices', $invoice_no, $userid, $user_full_name);

    /* 2. line item: one per installment = the installment amount, so the
          invoice total always matches balance (like any other invoice) */
    $desc = $plan['plan_name'] . ' - Installment #' . $install_no;
    $sub  = ($due_disp = trim($due_date)) !== '' ? 'Due ' . $due_disp : '';
    $line_rows = '<tr class="item"><td><b>' . htmlspecialchars($desc) . '</b>'
               . ($sub !== '' ? '<p style="font-size:12px;margin:0;">' . htmlspecialchars($sub) . '</p>' : '')
               . '</td><td>$' . $amount_fmt . '</td></tr>';
    $q = "INSERT INTO `billing_invoice_items`(`inv_id`, `temp_id`, `billing_id`, `mem_id`, `item_description`, `item_sub_description`, `unit_price`, `quantity`, `total_price`, `date_added`, `user_id`, `item_date`)
          VALUES ('$invoice_no', 0, 0, '$client_id',
                  '" . mysqli_real_escape_string($con, $desc) . "',
                  '" . mysqli_real_escape_string($con, $sub) . "',
                  '$amount_fmt', '1', '$amount_fmt', '$invoice_date2', '$userid', '$invoice_date2')";
    mysqli_query($con, $q);

    /* 3. client info for the PDF (same branching as client_billing.php) */
    $name = 'Client #' . $client_id; $address = ''; $address1 = ''; $email = ''; $phone = '';
    $cq = mysqli_query($con, "SELECT * FROM members WHERE member_id='$client_id' LIMIT 1");
    if ($cq && ($fc = mysqli_fetch_assoc($cq))) {
        $bus_name = $fc['bus_name'] ?? '';
        $dba      = $fc['dba'] ?? '';
        if ($bus_name !== '') {
            $name = $bus_name; $address = $fc['address'] ?? '';
            $address1 = ($fc['hcity'] ?? '') . ', ' . ($fc['hstate'] ?? '') . ', ' . ($fc['hzip'] ?? '');
            $email = $fc['prm_email'] ?? '';
        } elseif ($dba !== '') {
            $name = $dba; $address = $fc['address'] ?? '';
            $address1 = ($fc['hcity'] ?? '') . ', ' . ($fc['hstate'] ?? '') . ', ' . ($fc['hzip'] ?? '');
            $email = $fc['prm_email'] ?? '';
        } else {
            $name = trim(($fc['name'] ?? '') . ' ' . ($fc['lname'] ?? ''));
            $email = $fc['sec_email'] ?? '';
            $address = $fc['hstreet'] ?? '';
            $address1 = ($fc['hcity'] ?? '') . ', ' . ($fc['hstate'] ?? '') . ', ' . ($fc['hzip'] ?? '');
            $phone = $fc['phone'] ?? '';
        }
        $phone = $fc['cell_ph'] ?? '';
    }

    /* 4. PDF filename + content + render + link */
    $due_display = date('m/d/Y', strtotime($due_date));
    $US_Date = date('m-d-Y');
    $PDFFileN = "INV-{$invoice_no}-MemID-{$client_id}-{$US_Date}.pdf";
    $Filename = "invoices/" . $PDFFileN;
    $ppLogoSrc   = report_logo_src('storage/uploads/logo/logo-dark.png');
    $ppStatusSrc = report_logo_src('storage/uploads/logo/unpaid.jpg');

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
                           <img src="' . $ppLogoSrc . '" alt="Company logo " style="width: 60%; max-width: 300px" />
                        </td>
                        <td>
                           Invoice #: ' . $invoice_no . '<br />
                           By: ' . htmlspecialchars($emp_initials) . '(' . $userid . ')<br/>
                           Created: ' . $invoice_date . '<br />
                           Due: ' . $due_display . '<br />
                           <img src="' . $ppStatusSrc . '" alt="Company logo" style="width: 30%;margin-top:12px; max-width: 300px" />
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
                           ' . htmlspecialchars($name) . '.<br />
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
                <td><b>Service Provider:</b> ' . htmlspecialchars($emp_initials) . ' (' . $userid . ')</td>
           </tr>
            ' . $line_rows . '
             <tr class="total">
                    <td></td>
                    <td style="font-weight:bold;">Total: $' . $amount_fmt . '</td>
                </tr>
             <tr class="total">
                    <td></td>
                    <td style="font-weight:bold;">Paid: $0.00</td>
                </tr>
                <tr class="total">
                    <td></td>
                    <td style="font-weight:bold;">Balance: $' . $amount_fmt . '</td>
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

    try {
        $dompdf = new Dompdf(array('enable_remote' => false));
        $dompdf->loadHtml($content);
        $dompdf->render();
        file_put_contents($Filename, $dompdf->output());
        mysqli_query($con, "UPDATE billing_invoice SET filelink='" . mysqli_real_escape_string($con, $PDFFileN) . "' WHERE invoice_id=$invoice_no");
    } catch (Exception $e) {
        /* invoice row + items stay valid; the PDF can be rebuilt later */
    }

    /* 5. activity log + plan<->invoice mapping */
    logActivity($con, $userid, 'CREATE', 'billing_invoice', $invoice_no, "Created invoice #{$invoice_no} from payment plan #{$plan_id}, installment #{$install_no} (due {$due_date}) for client ID: {$client_id} with a total of \${$amount_fmt}.");
    mysqli_query($con, "INSERT INTO payment_plan_invoices(plan_id, installment_no, due_date, amount, invoice_id, created_by, created_at)
                        VALUES ('$plan_id', '$install_no', '$due_sql', '$amount_fmt', '$invoice_no', '$userid', '" . date('Y-m-d H:i:s') . "')");

    return array('ok' => true, 'invoice_id' => $invoice_no);
}

/* ---- Save handlers (run before any HTML so a redirect works) ------------- */
if ($plan_id > 0 && isset($_POST['create_invoice']) && $plan) {
    $install_no = (int) ($_POST['install_no'] ?? 0);
    $due_date   = trim($_POST['due_date'] ?? '');
    $amount     = (float) ($_POST['amount'] ?? 0);
    $err        = '';
    if ($install_no < 1 || $due_date === '') {
        $err = 'Missing installment information.';
    } elseif (isset($plan_invoices[$install_no])) {
        $err = 'An invoice already exists for installment #' . $install_no . '.';
    } else {
        $r = pp_create_installment_invoice($con, $plan, $due_date, $install_no, $userid, $user_full_name);
        if ($r['ok']) {
            header("Location: view_payment_plan.php?plan_id=" . $plan_id . "&inv_created=" . $r['invoice_id'] . "&inv_inst=" . $install_no . "&inv_amt=" . number_format($amount, 2, '.', ''));
            exit;
        }
        $err = $r['error'];
    }
    header("Location: view_payment_plan.php?plan_id=" . $plan_id . "&inv_error=" . urlencode($err));
    exit;
}

if ($plan_id > 0 && isset($_POST['create_all_invoices']) && $plan) {
    $created = 0;
    foreach ($installments as $k => $ins) {
        $inst_no = $k + 1;
        if (isset($plan_invoices[$inst_no])) { continue; }
        $r = pp_create_installment_invoice($con, $plan, $ins['date'], $inst_no, $userid, $user_full_name);
        if ($r['ok']) { $created++; }
    }
    if ($created > 0) {
        header("Location: view_payment_plan.php?plan_id=" . $plan_id . "&bulk_created=" . $created);
    } else {
        header("Location: view_payment_plan.php?plan_id=" . $plan_id . "&inv_error=" . urlencode('All installments already have invoices.'));
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="">

<head>
   <meta charset="utf-8">
   <title>Payment Plan | Syed Professional Services</title>
   <?php include "includes/links.php"; ?>

   <style>
      .pv-page {
         --pv-primary:#51459d; --pv-navy:#2a2c5e; --pv-ink:#293240; --pv-head:#1c232f;
         --pv-muted:#6b7585; --pv-line:#e7e9f1; --pv-soft:#f7f8fc;
         font-family: var(--bs-body-font-family, "Open Sans", sans-serif);
         font-size:.875rem; line-height:1.5; color:var(--pv-ink);
      }
      .pv-page * { box-sizing:border-box; }

      .pv-card { background:#fff; border:1px solid var(--pv-line); border-radius:12px; padding:20px 22px; margin-bottom:18px; box-shadow:0 1px 2px rgba(20,30,60,.03); }

      /* header */
      .pv-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; }
      .pv-title { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
      .pv-title h3 { margin:0; font-weight:600; color:var(--pv-head); font-size:1.4rem; }
      .pv-sub { margin:6px 0 0; color:var(--pv-muted); font-weight:600; letter-spacing:.02em; text-transform:uppercase; font-size:.8125rem; }
      .pv-meta { margin:10px 0 0; color:var(--pv-muted); font-size:.8125rem; display:flex; gap:18px; flex-wrap:wrap; }
      .pv-meta b { color:var(--pv-ink); font-weight:600; }
      .pv-head-actions { display:flex; gap:10px; }
      .pv-btn { border:0; border-radius:10px; padding:9px 16px; font-weight:600; font-size:.875rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:7px; }
      .pv-btn-navy { background:var(--pv-navy); color:#fff; }
      .pv-btn-navy:hover { background:#1f2047; color:#fff; }
      .pv-btn-light { background:#fff; color:var(--pv-ink); border:1px solid var(--pv-line); }
      .pv-btn-light:hover { background:var(--pv-soft); }

      .pv-chip { display:inline-block; padding:3px 12px; border-radius:20px; font-size:.74rem; font-weight:700; }
      .pv-chip.active { background:#e4f7ec; color:#1f9d57; }
      .pv-chip.inactive { background:#fdeaea; color:#d23f3f; }

      /* summary cards strip */
      .pv-cards { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:18px; }
      .pv-stat { background:#fff; border:1px solid var(--pv-line); border-radius:12px; padding:14px 16px; }
      .pv-stat .lbl { color:var(--pv-muted); font-size:.74rem; text-transform:uppercase; letter-spacing:.03em; font-weight:600; margin-bottom:6px; }
      .pv-stat .val { color:var(--pv-head); font-weight:600; font-size:.95rem; }
      .pv-stat .val small { color:var(--pv-muted); font-weight:600; }
      .pv-pill { background:#eef0fb; color:var(--pv-primary); border-radius:7px; padding:1px 8px; font-size:.85rem; font-weight:700; }

      /* tabs */
      .pv-tabs { display:flex; gap:4px; border-bottom:1px solid var(--pv-line); margin-bottom:16px; flex-wrap:wrap; }
      .pv-tab { background:transparent; border:0; border-bottom:2px solid transparent; padding:9px 14px; font-size:.875rem; font-weight:600; color:var(--pv-muted); cursor:pointer; }
      .pv-tab.active { color:var(--pv-primary); border-bottom-color:var(--pv-primary); }
      .pv-pane { display:none; }
      .pv-pane.active { display:block; }

      /* tables */
      .pv-table { width:100%; border-collapse:separate; border-spacing:0; }
      .pv-table thead th { background:var(--pv-soft); color:#5a6173; font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.02em; padding:10px; text-align:left; border-bottom:1px solid var(--pv-line); }
      .pv-table tbody td { padding:11px 10px; border-bottom:1px solid var(--pv-line); font-size:.875rem; color:var(--pv-ink); vertical-align:top; }
      .pv-table .col-no { width:46px; color:var(--pv-muted); }
      .pv-table .num { text-align:right; white-space:nowrap; }
      .pv-total-row td { font-weight:700; color:var(--pv-head); border-top:2px solid var(--pv-line); }
      .pv-st { display:inline-block; padding:2px 10px; border-radius:20px; font-size:.74rem; font-weight:700; }
      .pv-st.upcoming { background:#eaf1ff; color:#2b6ad6; }
      .pv-st.scheduled { background:#f1f3f7; color:#6b7585; }
      .pv-empty { color:var(--pv-muted); text-align:center; padding:28px 10px; font-size:.875rem; }

      /* summary sidebar */
      .pv-summary { position:sticky; top:84px; }
      .pv-sum-h { font-weight:600; color:var(--pv-head); font-size:1rem; margin:0 0 12px; }
      .pv-install-amt { font-size:1.75rem; font-weight:700; color:var(--pv-primary); line-height:1.1; }
      .pv-install-cap { color:var(--pv-muted); font-size:.8125rem; margin-bottom:3px; }
      .pv-sum-row { display:flex; justify-content:space-between; gap:10px; padding:7px 0; border-bottom:1px dashed var(--pv-line); font-size:.8125rem; }
      .pv-sum-row span { color:var(--pv-muted); }
      .pv-sum-row b { color:var(--pv-head); font-weight:600; text-align:right; }
      .pv-break { margin-top:16px; background:var(--pv-soft); border-radius:11px; padding:12px 14px; }
      .pv-break h6 { font-size:.75rem; text-transform:uppercase; letter-spacing:.02em; color:#5a6173; font-weight:600; margin:0 0 8px; }
      .pv-agree { margin-top:14px; background:#fff7e9; border:1px solid #f3e2bf; border-radius:10px; padding:10px 13px; font-size:.8125rem; color:#8a6d2f; }
      .pv-sign { margin-top:14px; }
      .pv-sign .box { border:1px dashed var(--pv-line); border-radius:10px; height:84px; display:flex; align-items:center; justify-content:center; color:var(--pv-muted); font-size:.8125rem; }
      .pv-notes-box { white-space:pre-wrap; color:var(--pv-ink); font-size:.875rem; background:var(--pv-soft); border:1px solid var(--pv-line); border-radius:10px; padding:12px 14px; }

      @media (max-width: 992px){ .pv-cards { grid-template-columns:repeat(2,1fr); } .pv-summary { position:static; } }
      @media (max-width: 560px){ .pv-cards { grid-template-columns:1fr; } }
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
         <div class="pv-page">

            <!-- breadcrumb -->
            <div class="page-header">
               <div class="page-block">
                  <div class="row align-items-center">
                     <div class="col-auto">
                        <button type="button" class="btn btn-sm me-1" onclick="window.history.back();" style="background-color:#6FD943;color:#fff;">&#8592;</button>
                     </div>
                     <div class="col-auto">
                        <ul class="breadcrumb mb-0">
                           <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                           <li class="breadcrumb-item"><a href="view_clients.php">Clients</a></li>
                           <?php if ($client_id > 0): ?>
                              <li class="breadcrumb-item"><a href="client_details.php?id=<?php echo $client_id; ?>&status=<?php echo $stat; ?>">#<?php echo $client_id; ?></a></li>
                           <?php endif; ?>
                           <li class="breadcrumb-item active">Payment Plan</li>
                        </ul>
                     </div>
                  </div>
               </div>
            </div>

            <?php if (!$plan): ?>
               <div class="pv-card">
                  <div class="pv-empty">Payment plan not found. <a href="payment_plan.php<?php echo $client_id ? '?id=' . $client_id : ''; ?>">Create a new plan</a>.</div>
               </div>
            <?php else: ?>

            <?php
            $inv_created = isset($_GET['inv_created']) ? (int) $_GET['inv_created'] : 0;
            $bulk_created = isset($_GET['bulk_created']) ? (int) $_GET['bulk_created'] : 0;
            $inv_error = isset($_GET['inv_error']) ? trim($_GET['inv_error']) : '';
            $inv_inst = isset($_GET['inv_inst']) ? (int) $_GET['inv_inst'] : 0;
            $inv_amt = isset($_GET['inv_amt']) ? (float) $_GET['inv_amt'] : 0.0;
            if ($inv_created > 0) {
                $bl = mysqli_query($con, "SELECT filelink FROM billing_invoice WHERE invoice_id='" . $inv_created . "' LIMIT 1");
                $fl = $bl ? mysqli_fetch_assoc($bl) : null;
                $flink = ($fl && !empty($fl['filelink'])) ? 'invoices/' . $fl['filelink'] : '';
                echo '<div class="alert alert-success" style="margin-bottom:18px;"><i class="fas fa-check-circle"></i> Invoice <b>#' . $inv_created . '</b> created';
                if ($inv_inst > 0) { echo ' for installment <b>#' . $inv_inst . '</b>'; }
                if ($inv_amt > 0) { echo ' (' . pp_money($inv_amt) . ')'; }
                if ($flink) { echo ' &mdash; <a href="' . htmlspecialchars($flink) . '" target="_blank"><i class="fas fa-file-pdf"></i> View PDF</a>'; }
                echo '.</div>';
            } elseif ($bulk_created > 0) {
                echo '<div class="alert alert-success" style="margin-bottom:18px;"><i class="fas fa-check-circle"></i> <b>' . $bulk_created . '</b> invoice(s) created from this plan. <a href="invoice_billings.php"><i class="fas fa-arrow-right"></i> Open Invoices</a></div>';
            } elseif ($inv_error !== '') {
                echo '<div class="alert alert-warning" style="margin-bottom:18px;"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($inv_error) . '</div>';
            }
            ?>

            <!-- header -->
            <div class="pv-card">
               <div class="pv-head">
                  <div>
                     <div class="pv-title">
                        <h3><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                        <span class="pv-chip <?php echo $is_active ? 'active' : 'inactive'; ?>"><?php echo htmlspecialchars($plan['status'] ?: 'Active'); ?></span>
                     </div>
                     <?php if ($client_name): ?><div class="pv-sub"><?php echo htmlspecialchars($client_name); ?></div><?php endif; ?>
                     <div class="pv-meta">
                        <span>Plan Created: <b><?php echo pp_d($plan['created_at']); ?></b></span>
                        <span>Effective: <b><?php echo pp_d($plan['effective_date']); ?></b></span>
                        <span>Duration: <b><?php echo pp_d($plan['start_date']); ?> &ndash; <?php echo pp_d($plan['end_date']); ?></b></span>
                     </div>
                  </div>
                  <div class="pv-head-actions">
                     <a class="pv-btn pv-btn-light" href="payment_plan.php?id=<?php echo $client_id; ?>&plan_id=<?php echo $plan_id; ?>"><i class="fas fa-pen"></i> Edit Plan</a>
                     <a class="pv-btn pv-btn-navy" href="payment_plan.php?id=<?php echo $client_id; ?>"><i class="fas fa-plus"></i> New Plan</a>
                  </div>
               </div>
            </div>

            <!-- summary cards -->
            <div class="pv-cards">
               <div class="pv-stat"><div class="lbl">Plan Type</div><div class="val"><?php echo htmlspecialchars($plan['plan_type'] ?: '—'); ?></div></div>
               <div class="pv-stat"><div class="lbl">Billing Cycle</div><div class="val"><?php echo htmlspecialchars($plan['billing_cycle'] ?: '—'); ?></div></div>
               <div class="pv-stat"><div class="lbl">Payment Method</div><div class="val"><?php echo htmlspecialchars($plan['payment_method'] ?: '—'); ?></div></div>
               <div class="pv-stat"><div class="lbl">Next Installment</div><div class="val"><?php echo pp_d($plan['next_installment_date']); ?> &nbsp;<span class="pv-pill"><?php echo pp_money($plan['installment_amount']); ?></span></div></div>
               <div class="pv-stat"><div class="lbl">Status</div><div class="val"><span class="pv-chip <?php echo $is_active ? 'active' : 'inactive'; ?>"><?php echo htmlspecialchars($plan['status'] ?: 'Active'); ?></span></div></div>
            </div>

            <div class="row">
               <!-- LEFT -->
               <div class="col-lg-8">
                  <div class="pv-card">
                     <div class="pv-tabs">
                        <button class="pv-tab active" data-tab="items">Payment Plan Items</button>
                        <button class="pv-tab" data-tab="schedule">Installment Schedule</button>
                        <button class="pv-tab" data-tab="history">History &amp; Notes</button>
                        <button class="pv-tab" data-tab="docs">Documents</button>
                     </div>

                     <!-- items -->
                     <div class="pv-pane active" id="pane-items">
                        <table class="pv-table">
                           <thead>
                              <tr><th class="col-no">#</th><th>Item</th><th class="num">Amount</th><th>Frequency</th><th>Details</th></tr>
                           </thead>
                           <tbody>
                              <?php if ($items): $n = 0; foreach ($items as $it): $n++; ?>
                                 <tr>
                                    <td class="col-no"><?php echo $n; ?></td>
                                    <td><?php echo htmlspecialchars($it['service_name']); ?></td>
                                    <td class="num"><?php echo pp_money($it['amount']); ?></td>
                                    <td><?php echo htmlspecialchars($it['frequency'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($it['details'] ?: '—'); ?></td>
                                 </tr>
                              <?php endforeach; else: ?>
                                 <tr><td colspan="5" class="pv-empty">No items on this plan.</td></tr>
                              <?php endif; ?>
                           </tbody>
                           <?php if ($items): ?>
                           <tfoot>
                              <tr class="pv-total-row"><td colspan="2">Total Plan Amount</td><td class="num"><?php echo pp_money($total); ?></td><td colspan="2"></td></tr>
                           </tfoot>
                           <?php endif; ?>
                        </table>
                     </div>

<!-- schedule -->
                      <div class="pv-pane" id="pane-schedule">
                         <?php $total_inst = count($installments); $done_inst = count($plan_invoices); $remain_inst = max(0, $total_inst - $done_inst); ?>
                         <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                            <div class="pv-meta" style="margin:0;">
                               <span>Frequency: <b><?php echo htmlspecialchars($plan['installment_frequency'] ?: '—'); ?></b></span>
                               <span>Next Installment: <b><?php echo pp_d($plan['next_installment_date']); ?></b></span>
                               <span>Installment Amount: <b><?php echo pp_money($plan['installment_amount']); ?></b></span>
                               <span>Invoiced: <b><?php echo $done_inst; ?>/<?php echo $total_inst; ?></b></span>
                            </div>
                            <?php if ($total_inst > 0 && $remain_inst > 0): ?>
                               <form method="post" onsubmit="return confirm('Create invoices for the <?php echo $remain_inst; ?> remaining installment(s)?');">
                                  <input type="hidden" name="create_all_invoices" value="1">
                                  <button type="submit" class="pv-btn pv-btn-navy"><i class="fas fa-file-invoice"></i> Create All Invoices</button>
                               </form>
                            <?php elseif ($total_inst > 0): ?>
                               <span class="pv-chip active"><i class="fas fa-check"></i> All installments invoiced</span>
                            <?php endif; ?>
                         </div>
                         <table class="pv-table">
                            <thead><tr><th class="col-no">#</th><th>Due Date</th><th class="num">Amount</th><th>Status</th><th>Invoice</th></tr></thead>
                            <tbody>
                               <?php if ($installments): foreach ($installments as $k => $ins): $inst_no = $k + 1; $inv_row = isset($plan_invoices[$inst_no]) ? $plan_invoices[$inst_no] : null; ?>
                                  <tr>
                                     <td class="col-no"><?php echo $inst_no; ?></td>
                                     <td><?php echo pp_d($ins['date']); ?></td>
                                     <td class="num"><?php echo pp_money($ins['amount']); ?></td>
                                     <td><span class="pv-st <?php echo strtolower($ins['status']); ?>"><?php echo $ins['status']; ?></span></td>
                                     <td>
                                        <?php if ($inv_row): ?>
                                           <?php if (!empty($inv_row['filelink'])): ?>
                                              <a href="invoices/<?php echo htmlspecialchars($inv_row['filelink']); ?>" target="_blank" class="pv-btn pv-btn-light" style="padding:3px 10px;font-size:.76rem;">#<?php echo (int) $inv_row['invoice_id']; ?> <i class="fas fa-file-pdf"></i></a>
                                           <?php else: ?>
                                              <span class="pv-chip active">#<?php echo (int) $inv_row['invoice_id']; ?></span>
                                           <?php endif; ?>
                                        <?php else: ?>
                                           <form method="post" style="display:inline;">
                                              <input type="hidden" name="create_invoice" value="1">
                                              <input type="hidden" name="install_no" value="<?php echo $inst_no; ?>">
                                              <input type="hidden" name="due_date" value="<?php echo $ins['date']; ?>">
                                              <input type="hidden" name="amount" value="<?php echo htmlspecialchars($ins['amount']); ?>">
                                              <button type="submit" class="pv-btn pv-btn-light" style="padding:3px 10px;font-size:.76rem;"><i class="fas fa-plus"></i> Create Invoice</button>
                                           </form>
                                        <?php endif; ?>
                                     </td>
                                  </tr>
                               <?php endforeach; else: ?>
                                  <tr><td colspan="5" class="pv-empty">No installment schedule defined.</td></tr>
                               <?php endif; ?>
                            </tbody>
                         </table>
                      </div>

                     <!-- history & notes -->
                     <div class="pv-pane" id="pane-history">
                        <?php if (!empty($plan['notes'])): ?>
                           <div class="pv-notes-box"><?php echo htmlspecialchars($plan['notes']); ?></div>
                        <?php else: ?>
                           <div class="pv-empty">No notes added.</div>
                        <?php endif; ?>
                        <div class="pv-meta" style="margin-top:14px;">
                           <span>Created: <b><?php echo pp_d($plan['created_at'], 'M d, Y g:i A'); ?></b><?php echo $created_by_name ? ' by <b>' . htmlspecialchars($created_by_name) . '</b>' : ''; ?></span>
                           <?php if (!empty($plan['updated_at']) && $plan['updated_at'] !== '0000-00-00 00:00:00'): ?>
                              <span>Updated: <b><?php echo pp_d($plan['updated_at'], 'M d, Y g:i A'); ?></b><?php echo $updated_by_name ? ' by <b>' . htmlspecialchars($updated_by_name) . '</b>' : ''; ?></span>
                           <?php endif; ?>
                        </div>
                     </div>

                     <!-- documents -->
                     <div class="pv-pane" id="pane-docs">
                        <div class="pv-empty">No documents attached to this plan.</div>
                     </div>
                  </div>

                  <!-- upcoming installments -->
                  <div class="pv-card">
                     <div class="pv-sum-h" style="margin-bottom:14px;">Upcoming Installments</div>
                     <table class="pv-table">
                        <thead><tr><th class="col-no">#</th><th>Due Date</th><th class="num">Amount</th><th>Status</th><th>Payment Method</th></tr></thead>
                        <tbody>
                           <?php if ($installments): foreach (array_slice($installments, 0, 6) as $k => $ins): ?>
                              <tr>
                                 <td class="col-no"><?php echo $k + 1; ?></td>
                                 <td><?php echo pp_d($ins['date']); ?></td>
                                 <td class="num"><?php echo pp_money($ins['amount']); ?></td>
                                 <td><span class="pv-st <?php echo strtolower($ins['status']); ?>"><?php echo $ins['status']; ?></span></td>
                                 <td><?php echo htmlspecialchars($plan['payment_method'] ?: '—'); ?></td>
                              </tr>
                           <?php endforeach; else: ?>
                              <tr><td colspan="5" class="pv-empty">No upcoming installments.</td></tr>
                           <?php endif; ?>
                        </tbody>
                     </table>
                  </div>
               </div>

               <!-- RIGHT — summary -->
               <div class="col-lg-4">
                  <div class="pv-summary">
                     <div class="pv-card">
                        <div class="pv-sum-h">Plan Summary</div>
                        <div class="pv-install-cap"><?php echo htmlspecialchars(($plan['installment_frequency'] ?: 'Monthly')); ?> Installment (<?php echo htmlspecialchars($plan['payment_method'] ?: '—'); ?>)</div>
                        <div class="pv-install-amt"><?php echo pp_money($plan['installment_amount']); ?></div>

                        <div style="margin-top:16px;">
                           <div class="pv-sum-row"><span>Next Payment Date</span><b><?php echo pp_d($plan['next_installment_date']); ?></b></div>
                           <div class="pv-sum-row"><span>Payment Method</span><b><?php echo htmlspecialchars($plan['payment_method'] ?: '—'); ?></b></div>
                           <div class="pv-sum-row"><span>Plan Duration</span><b><?php echo pp_d($plan['start_date']); ?> &ndash; <?php echo pp_d($plan['end_date']); ?></b></div>
                           <div class="pv-sum-row"><span>Billing Cycle</span><b><?php echo htmlspecialchars($plan['billing_cycle'] ?: '—'); ?></b></div>
                           <div class="pv-sum-row"><span>Status</span><b><span class="pv-chip <?php echo $is_active ? 'active' : 'inactive'; ?>"><?php echo htmlspecialchars($plan['status'] ?: 'Active'); ?></span></b></div>
                        </div>

                        <div class="pv-break">
                           <h6>Installment Breakdown</h6>
                           <div class="pv-sum-row"><span>Quarterly (QTRLY)</span><b><?php echo pp_money($brk_q); ?></b></div>
                           <div class="pv-sum-row"><span>Monthly</span><b><?php echo pp_money($brk_m); ?></b></div>
                           <div class="pv-sum-row" style="border-bottom:0;"><span><b style="color:var(--pv-head);">Total</b></span><b><?php echo pp_money($total); ?></b></div>
                        </div>

                        <div class="pv-agree">Client Agrees<br><b>As of <?php echo pp_d($plan['effective_date'] ?: $plan['created_at']); ?></b></div>

                        <div class="pv-sign">
                           <label style="font-size:.8125rem;font-weight:600;color:#46505f;margin-bottom:6px;display:block;">Signature</label>
                           <div class="box">Signature</div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>

            <?php endif; ?>
         </div>
      </div>
   </div>

   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>

   <script>
      (function () {
         var tabs  = document.querySelectorAll('.pv-tab');
         var panes = document.querySelectorAll('.pv-pane');
         tabs.forEach(function (t) {
            t.addEventListener('click', function () {
               tabs.forEach(function (x) { x.classList.remove('active'); });
               panes.forEach(function (p) { p.classList.remove('active'); });
               t.classList.add('active');
               var pane = document.getElementById('pane-' + t.getAttribute('data-tab'));
               if (pane) { pane.classList.add('active'); }
            });
         });
      })();
   </script>
</body>

</html>
