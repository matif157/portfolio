<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

include "includes/db.php";
include "includes/auth.php";
include "includes/activity_log.php";

function getInitials($name)
{
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}

$userid           = $_SESSION['user_id'];
$user_full_name   = $_SESSION['fullname'];
$user_initials    = getInitials($user_full_name);
$emp_id_initials  = getInitials($user_full_name) . '(' . $userid . ')';
$user_type        = $_SESSION['user_type'] ?? '';

$msg = '';
$success_count = 0;
$failed_count  = 0;
$generated_invoices = [];

/* ============================================================
   BULK INVOICE GENERATION HANDLER
   Loops selected clients and replicates client_billing.php flow.
============================================================ */
if (isset($_POST['bulkcreateinvoice'])) {

    $client_ids       = isset($_POST['client_ids'])  ? (array) $_POST['client_ids']  : [];
    $services_post    = isset($_POST['service'])     ? (array) $_POST['service']     : [];
    $price_post       = isset($_POST['unit_price'])  ? (array) $_POST['unit_price']  : [];
    $description_post = isset($_POST['description']) ? (array) $_POST['description'] : [];

    $sub_total           = $_POST['sub_total']        ?? 0;
    $discount            = $_POST['discount']         ?? 0;
    $grand_total         = $_POST['grand_total']      ?? 0;
    $duedate             = $_POST['billing_cycle']    ?? date('Y-m-d');
    $service_provider_id = $_POST['Service_Provider'] ?? '';
    $is_ach              = !empty($_POST['mark_ach']) ? 1 : 0;

    if (strpos($discount, '.')    === false) { $discount    = sprintf("%.2f", $discount); }
    if (strpos($sub_total, '.')   === false) { $sub_total   = sprintf("%.2f", $sub_total); }
    if (strpos($grand_total, '.') === false) { $grand_total = sprintf("%.2f", $grand_total); }

    $service_provider_name = '';
    if (!empty($service_provider_id)) {
        $sp_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . mysqli_real_escape_string($con, $service_provider_id) . "'");
        if ($sp_result = mysqli_fetch_assoc($sp_query)) {
            $service_provider_name = getInitials($sp_result['user_name']);
        }
    }

    $invoice_date  = date("m/d/Y h:i:s");
    $invoice_date2 = date("Y-m-d h:i:s");
    $curDateMySQL  = date('m/d/Y');
    $US_Date       = date('m-d-Y');

    $dateTime = DateTime::createFromFormat('Y-m-d', $duedate);
    $duedate_display = $dateTime ? $dateTime->format('m/d/Y') : $duedate;

    $size = count($services_post);

    if (empty($client_ids)) {
        $msg = "<div class='alert alert-danger'>Please select at least one client.</div>";
    } elseif ($size === 0) {
        $msg = "<div class='alert alert-danger'>Please add at least one service.</div>";
    } else {

        foreach ($client_ids as $client_id) {
            $client_id = (int) $client_id;
            if ($client_id <= 0) { continue; }

            $discountRow = ($discount > 0) ? '
            <tr class="total">
               <td></td>
               <td style="font-size:20px;font-weight:bold;">Discount: $' . $discount . '</td>
            </tr>' : '';

            $insert_billing = "INSERT INTO billing_invoice(mem_id, billing_id, temp_id, invoice_date, filelink,
                              due_date, total_amount, paid_amount, discount, balance,
                              invoice_status, payment_type, payment_details, paid_date,
                              billing_cycle, service_provider_id, is_ach, employee_initials)
                            VALUES ('$client_id','0','0','$invoice_date','',
                                    '$duedate','$sub_total','0','$discount','$grand_total',
                                    '1','0','0','0000-00-00','',
                                    '" . (int) $service_provider_id . "','$is_ach','$emp_id_initials')";
            if (!mysqli_query($con, $insert_billing)) { $failed_count++; continue; }

            $invoice_no = mysqli_insert_id($con);
            $PDFFileN   = "INV-{$invoice_no}-MemID-{$client_id}-{$US_Date}.pdf";
            $Filename   = "invoices/" . $PDFFileN;

            $name = $address = $address1 = $email = $phone = '';
            $client_query = mysqli_query($con, "SELECT * FROM members where member_id='$client_id'");
            while ($fetch_client = mysqli_fetch_array($client_query)) {
                $bus_name = $fetch_client['bus_name'];
                $dba      = $fetch_client['dba'];
                if ($bus_name != '') {
                    $name     = $bus_name;
                    $address  = $fetch_client['address'];
                    $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
                    $email    = $fetch_client['prm_email'];
                } else if ($dba != '') {
                    $name     = $dba;
                    $address  = $fetch_client['address'];
                    $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
                    $email    = $fetch_client['prm_email'];
                } else {
                    $name     = $fetch_client['name'] . " " . $fetch_client['lname'];
                    $email    = $fetch_client['sec_email'];
                    $address  = $fetch_client['hstreet'];
                    $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
                }
                $phone = $fetch_client['cell_ph'];
            }

            $drows = '';
            for ($i = 0; $i < $size; $i++) {
                $servicess    = $services_post[$i];
                $priceee      = $price_post[$i] ?? 0;
                if (strpos($priceee, '.') === false) { $priceee = sprintf("%.2f", $priceee); }
                $descriptionn = $description_post[$i] ?? '';

                $drows .= '<tr class="item">
                  <td><b>' . htmlspecialchars($servicess) . '</b><p style="font-size:12px;margin:0px">' . htmlspecialchars($descriptionn) . '</p></td>
                  <td>$' . $priceee . '</td>
               </tr>';

                $servicess_e    = mysqli_real_escape_string($con, $servicess);
                $descriptionn_e = mysqli_real_escape_string($con, $descriptionn);
                $priceee_e      = mysqli_real_escape_string($con, $priceee);

                $que = "INSERT INTO `billing_invoice_items`(`inv_id`, `temp_id`, `billing_id`, `mem_id`, `item_description`, `item_sub_description`, `unit_price`, `quantity`, `total_price`, `date_added`, `user_id`, `item_date`)
                    VALUES ('$invoice_no', 0, 0,'$client_id','$servicess_e','$descriptionn_e','$priceee_e','1','$priceee_e','$invoice_date2','$userid','$invoice_date2')";
                mysqli_query($con, $que);
            }

            $content = '<style>
.invoice-box { position:relative; height:99%; width:100%; margin:auto; border:1px solid #eee;
   box-shadow:0 0 10px rgba(0,0,0,.15); font-size:16px; line-height:24px; color:#555; }
.invoice-box .footer-note { position:absolute; bottom:-20px; left:50%; transform:translateX(-50%); text-align:center; font-size:14px; }
.invoice-box .footer-note p { margin:0; font-size:14px; white-space:nowrap; }
.invoice-box .footer-note h4 { margin-top:5px; font-size:16px; }
.invoice-box table { line-height:inherit; text-align:left; border-collapse:collapse; }
.invoice-box table td { padding:5px; vertical-align:top; }
.invoice-box table tr td:nth-child(2) { text-align:right; }
.invoice-box table tr.top table td { padding-bottom:20px; }
.invoice-box table tr.top table td.title { font-size:45px; line-height:30px; color:#333; }
.invoice-box table tr.information table td { padding-bottom:30px; }
.invoice-box table tr.heading td { background:#8b742a; border-bottom:1px solid #ddd; font-weight:bold; color:#fff; font-size:22px; }
.invoice-box table tr.details td { padding-bottom:20px; }
.invoice-box table tr.item td { border-bottom:1px solid #eee; }
.invoice-box table tr.total td:nth-child(2) { border-top:2px solid #eee; font-weight:bold; }
.bank-details { font-size:14px; line-height:1.6; margin-top:10px; }
.bank-details strong { color:#333; }
</style>
<div class="invoice-box">
   <table style="width:100%">
      <tr class="top"><td colspan="2"><table style="width:100%"><tr>
         <td class="title"><img src="http://192.168.15.29/storage/uploads/logo/logo-dark.png" style="width:60%;max-width:300px"/></td>
         <td>Invoice #: ' . $invoice_no . '<br/>By: ' . $user_initials . '(' . $userid . ')<br/>Created: ' . $curDateMySQL . '<br/>Due: ' . $duedate_display . '<br/>
            <img src="http://192.168.15.29/storage/uploads/logo/unpaid.jpg" style="width:30%;margin-top:12px;max-width:300px"/></td>
      </tr></table></td></tr>
      <tr class="information"><td colspan="2"><table style="width:100%"><tr>
         <td><b>From:</b><br/>Syed Professional Services<br/>1012 Coney Island Ave<br/>Brooklyn, NY 11230<br/>Phone: (718) 859-5392<br/>Fax: (718) 859-2237<br/>invoice@syedpro.com</td>
         <td><b>To:</b><br/>' . $name . '<br/>' . $address . '<br/>' . $address1 . '<br/>' . $phone . '<br/>' . $email . '</td>
      </tr></table></td></tr>
      <tr class="heading"><td>Service</td><td>Price</td></tr>
      <tr class="details"><td><b>Service Provider:</b> ' . $service_provider_name . ' (' . $service_provider_id . ')</td></tr>
      ' . $drows . '
      <tr class="total"><td></td><td style="font-weight:bold;">Sub Total: $' . $sub_total . '</td></tr>
      ' . $discountRow . '
      <tr class="total"><td></td><td style="font-weight:bold;">Grand Total: $' . $grand_total . '</td></tr>
      <tr class="details"><td><div class="bank-details"><strong>Zelle Payment Info:</strong> info@syedpro.com</div></td></tr>
   </table>
   <div class="footer-note">
      <p>In the event of a returned or unsuccessful payment, a bank fee of $25 will be applied.<br>We accept the payment via Zelle, Credit or debit Card, Cash and cheque.</p>
      <h4><u>Thank you for choosing Syed Professional Services</u></h4>
   </div>
</div>';

            try {
                $dompdf = new Dompdf(array('enable_remote' => true));
                $dompdf->loadHtml($content);
                $dompdf->render();
                file_put_contents($Filename, $dompdf->output());
            } catch (Exception $e) { /* swallow */ }

            mysqli_query($con, "UPDATE billing_invoice
                              SET filelink = '" . mysqli_real_escape_string($con, $PDFFileN) . "'
                              WHERE invoice_id = $invoice_no");

            logActivity($con, $userid, 'CREATE', 'billing_invoice', $invoice_no,
            "Bulk-created invoice #{$invoice_no} for client ID: {$client_id} with a total of \${$grand_total}.");

            $success_count++;
            $generated_invoices[] = ['invoice_no' => $invoice_no, 'client_id' => $client_id, 'name' => $name, 'file' => $PDFFileN];
        }

        if ($success_count > 0) {
            $msg .= "<div class='alert alert-success'><i class='ti ti-check'></i> " . $success_count . " invoice(s) created successfully.</div>";
        }
        if ($failed_count > 0) {
            $msg .= "<div class='alert alert-danger'>" . $failed_count . " invoice(s) failed.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">
<head>
   <title>Bulk Invoice Generation - Syed Professional Services</title>
   <?php include "includes/links.php"; ?>
   <style>
      :root {
         --bi-primary: #4f46e5;
         --bi-primary-dark: #3730a3;
         --bi-primary-soft: #eef2ff;
         --bi-primary-soft-2: #e0e7ff;
         --bi-accent: #0ea5e9;
         --bi-navy: #0f172a;
         --bi-navy-2: #1e293b;
         --bi-navy-3: #334155;
         --bi-border: #e2e8f0;
         --bi-border-2: #cbd5e1;
         --bi-bg: #f8fafc;
         --bi-text: #0f172a;
         --bi-text-strong: #020617;
         --bi-muted: #64748b;
         --bi-muted-2: #94a3b8;
         --bi-success: #10b981;
         --bi-success-soft: #d1fae5;
         --bi-warning: #f59e0b;
         --bi-danger: #ef4444;
         --bi-radius: 14px;
         --bi-radius-sm: 10px;
         --bi-shadow-sm: 0 1px 2px rgba(15,23,42,.04);
         --bi-shadow: 0 1px 3px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.04);
         --bi-shadow-md: 0 4px 12px rgba(15,23,42,.06), 0 2px 4px rgba(15,23,42,.04);
         --bi-shadow-lg: 0 10px 25px rgba(15,23,42,.08), 0 4px 10px rgba(15,23,42,.04);
      }
      body.theme-3 .dash-content { background: var(--bi-bg); }
      .dash-content { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; }

      /* Page hero */
      .bi-hero {
         background: linear-gradient(135deg, #1e293b 0%, #312e81 50%, #4f46e5 100%);
         color: #fff; border-radius: var(--bi-radius);
         padding: 28px 32px; margin-bottom: 20px;
         display:flex; align-items:center; justify-content:space-between;
         box-shadow: var(--bi-shadow-lg);
         position: relative; overflow: hidden;
      }
      .bi-hero::before {
         content:""; position:absolute; right:-60px; top:-60px;
         width:240px; height:240px; border-radius:50%;
         background: radial-gradient(circle, rgba(255,255,255,.12) 0%, transparent 70%);
      }
      .bi-hero::after {
         content:""; position:absolute; left:-40px; bottom:-80px;
         width:200px; height:200px; border-radius:50%;
         background: radial-gradient(circle, rgba(14,165,233,.18) 0%, transparent 70%);
      }
      .bi-hero > * { position:relative; z-index:1; }
      .bi-hero h4 { color:#fff; margin:0 0 8px 0; font-weight:700; font-size:22px; letter-spacing:-0.3px; }
      .bi-hero .hero-sub { color: rgba(255,255,255,.75); font-size:13px; margin-bottom:10px; }
      .bi-hero .breadcrumb {
         background:transparent; padding:0; margin:0; font-size:12px;
      }
      .bi-hero .breadcrumb-item, .bi-hero .breadcrumb-item a { color: rgba(255,255,255,.7); }
      .bi-hero .breadcrumb-item.active { color:#fff; font-weight:500; }
      .bi-hero .hero-icon {
         width:64px; height:64px; border-radius:16px;
         background: rgba(255,255,255,.15);
         backdrop-filter: blur(10px);
         border: 1px solid rgba(255,255,255,.2);
         display:flex; align-items:center; justify-content:center;
         font-size:32px; color:#fff;
      }

      /* Stepper */
      .bi-stepper {
         display:flex; align-items:center; gap:12px;
         background:#fff; border:1px solid var(--bi-border); border-radius: var(--bi-radius);
         padding: 14px 22px; margin-bottom: 18px; box-shadow: var(--bi-shadow-sm);
      }
      .bi-step { display:flex; align-items:center; gap:10px; flex:1; }
      .bi-step .num {
         width:28px; height:28px; border-radius:50%; background: var(--bi-bg);
         color: var(--bi-muted); display:flex; align-items:center; justify-content:center;
         font-weight:700; font-size:13px; border: 2px solid var(--bi-border);
         transition: all .2s;
      }
      .bi-step.active .num { background: var(--bi-primary); color:#fff; border-color: var(--bi-primary); box-shadow: 0 0 0 4px var(--bi-primary-soft); }
      .bi-step.done .num { background: var(--bi-success); color:#fff; border-color: var(--bi-success); }
      .bi-step .label { font-size:13px; font-weight:600; color: var(--bi-muted); }
      .bi-step.active .label, .bi-step.done .label { color: var(--bi-text); }
      .bi-stepper .connector { flex:1; height:2px; background: var(--bi-border); margin: 0 4px; border-radius:2px; transition: background .2s; min-width: 30px; }
      .bi-stepper .connector.done { background: var(--bi-success); }

      /* Card */
      .bulk-card {
         background:#fff; border: 1px solid var(--bi-border); border-radius: var(--bi-radius);
         box-shadow: var(--bi-shadow-sm);
         margin-bottom: 18px; overflow: hidden;
         transition: box-shadow .2s;
      }
      .bulk-card:hover { box-shadow: var(--bi-shadow); }
      .bulk-card .card-header {
         background:#fff; border-bottom: 1px solid var(--bi-border);
         padding: 18px 24px; display:flex; align-items:center; justify-content:space-between;
      }
      .bulk-card .card-header h5 {
         margin:0; font-weight:700; font-size:15px; color:var(--bi-text);
         display:flex; align-items:center; gap:12px; letter-spacing:-0.2px;
      }
      .bulk-card .card-header small { color: var(--bi-muted); font-size:12px; }
      .bulk-card .card-body { padding: 24px; }

      .step-pill {
         display:inline-flex; align-items:center; justify-content:center;
         width:30px; height:30px; border-radius:8px;
         background: linear-gradient(135deg, var(--bi-primary) 0%, var(--bi-primary-dark) 100%);
         color: #fff;
         font-size:13px; font-weight:700;
         box-shadow: 0 2px 4px rgba(79,70,229,.25);
      }
      .section-icon {
         width:32px; height:32px; border-radius:8px;
         background: var(--bi-primary-soft); color: var(--bi-primary);
         display:inline-flex; align-items:center; justify-content:center;
         font-size:18px;
      }

      /* Shared bits used by client items */
      .client-avatar {
         width:40px; height:40px; border-radius:10px;
         background: linear-gradient(135deg, var(--bi-primary-soft) 0%, var(--bi-primary-soft-2) 100%);
         color: var(--bi-primary);
         display:flex; align-items:center; justify-content:center;
         font-weight:700; font-size:13px; flex-shrink:0;
         border: 1px solid var(--bi-primary-soft-2);
      }

      /* ========= Dual-list client picker ========= */
      .bi-dual {
         display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
      }
      .bi-col {
         background: #fff; border: 1px solid var(--bi-border);
         border-radius: var(--bi-radius-sm);
         display: flex; flex-direction: column;
         overflow: hidden; min-height: 480px;
      }
      .bi-col-selected {
         background: linear-gradient(180deg, var(--bi-primary-soft) 0%, #fff 35%);
         border-color: var(--bi-primary-soft-2);
      }
      .bi-col-header {
         display: flex; align-items: center; justify-content: space-between;
         padding: 12px 14px; border-bottom: 1px solid var(--bi-border);
         background: #fafbfc;
      }
      .bi-col-selected .bi-col-header { background: rgba(255,255,255,.65); }
      .bi-col-title {
         display: inline-flex; align-items: center; gap: 8px;
         font-weight: 700; font-size: 12px; color: var(--bi-text);
         text-transform: uppercase; letter-spacing: .4px;
      }
      .bi-col-title i { font-size: 18px; color: var(--bi-primary); }
      .bi-count {
         display: inline-flex; align-items: center; justify-content: center;
         min-width: 24px; height: 22px; padding: 0 8px;
         border-radius: 11px; background: #fff; border: 1px solid var(--bi-border);
         font-size: 12px; font-weight: 700; color: var(--bi-muted);
      }
      .bi-count.primary { background: var(--bi-primary); color: #fff; border-color: var(--bi-primary); }

      .bi-col-action {
         border: 1px solid var(--bi-border); background: #fff;
         border-radius: 8px; padding: 6px 10px;
         font-size: 12px; font-weight: 600; color: var(--bi-text);
         cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
         transition: all .15s;
      }
      .bi-col-action:hover { background: var(--bi-primary-soft); border-color: var(--bi-primary-soft-2); color: var(--bi-primary); }
      .bi-col-action.danger { color: var(--bi-danger); }
      .bi-col-action.danger:hover { background: #fef2f2; border-color: #fecaca; color: var(--bi-danger); }
      .bi-col-action i { font-size: 14px; }
      .bi-col-action:disabled { opacity: .45; cursor: not-allowed; background:#fff; color: var(--bi-muted); border-color: var(--bi-border); }

      .bi-col-search {
         position: relative;
         padding: 10px 12px;
         border-bottom: 1px solid var(--bi-border);
         background: #fff;
      }
      .bi-col-search i {
         position: absolute; left: 24px; top: 50%; transform: translateY(-50%);
         color: var(--bi-muted-2); font-size: 16px; pointer-events: none;
      }
      .bi-col-search input {
         width: 100%; height: 38px;
         padding: 0 12px 0 36px;
         border-radius: 8px; border: 1px solid var(--bi-border); background: #fff;
         font-size: 13px; transition: all .15s;
      }
      .bi-col-search input:focus {
         border-color: var(--bi-primary); box-shadow: 0 0 0 3px var(--bi-primary-soft); outline: none;
      }

      .bi-col-list {
         flex: 1; overflow-y: auto;
         padding: 6px;
         max-height: 420px; min-height: 340px;
      }
      .bi-col-list::-webkit-scrollbar { width: 8px; }
      .bi-col-list::-webkit-scrollbar-track { background: var(--bi-bg); }
      .bi-col-list::-webkit-scrollbar-thumb { background: var(--bi-border-2); border-radius: 4px; }
      .bi-col-list::-webkit-scrollbar-thumb:hover { background: var(--bi-muted-2); }

      .client-item {
         display: flex; align-items: center; gap: 10px;
         padding: 9px 10px; margin: 0 0 4px 0;
         border-radius: 8px; background: #fff;
         border: 1px solid transparent;
         transition: background .12s, border-color .12s, transform .12s;
      }
      .client-item:last-child { margin-bottom: 0; }
      .client-item:hover { background: var(--bi-bg); border-color: var(--bi-border); }
      .bi-col-list[data-side="selected"] .client-item {
         background: #fff; border-color: var(--bi-primary-soft-2);
         box-shadow: 0 1px 2px rgba(79,70,229,.06);
      }
      .bi-col-list[data-side="selected"] .client-item:hover { background: var(--bi-primary-soft); }
      .bi-col-list[data-side="avail"] .ci-remove { display: none; }
      .bi-col-list[data-side="selected"] .ci-add { display: none; }

      .client-item .client-info { flex: 1; min-width: 0; }
      .client-item .client-name {
         font-weight: 600; color: var(--bi-text); font-size: 13px;
         white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      }
      .client-item .client-meta {
         color: var(--bi-muted); font-size: 11px; margin-top: 2px;
         white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      }
      .client-item .client-avatar { width: 34px; height: 34px; font-size: 12px; border-radius: 8px; }
      .bi-col-list[data-side="selected"] .client-item .client-avatar {
         background: var(--bi-primary); color: #fff; border-color: var(--bi-primary);
      }

      .ci-add, .ci-remove {
         width: 30px; height: 30px;
         border: 1px solid var(--bi-border); background: #fff;
         border-radius: 8px; color: var(--bi-muted);
         display: inline-flex; align-items: center; justify-content: center;
         cursor: pointer; flex-shrink: 0;
         transition: all .12s; padding: 0;
      }
      .ci-add:hover {
         background: var(--bi-primary); color: #fff; border-color: var(--bi-primary);
         transform: scale(1.06);
      }
      .ci-remove { color: var(--bi-danger); background: #fef2f2; border-color: #fee2e2; }
      .ci-remove:hover {
         background: var(--bi-danger); color: #fff; border-color: var(--bi-danger);
         transform: scale(1.06);
      }
      .ci-add i, .ci-remove i { font-size: 16px; }

      .bi-empty-pane {
         padding: 40px 18px; text-align: center; color: var(--bi-muted);
         font-size: 13px;
      }
      .bi-empty-pane i { font-size: 30px; color: var(--bi-muted-2); display: block; margin-bottom: 6px; }
      .bi-empty-pane small { display:block; margin-top: 4px; font-size: 12px; color: var(--bi-muted-2); }

      @media (max-width: 768px) {
         .bi-dual { grid-template-columns: 1fr; }
         .bi-col { min-height: 380px; }
         .bi-col-list { max-height: 320px; min-height: 240px; }
      }

      /* Service catalog items (left column) */
      .svc-item {
         display: flex; align-items: center; gap: 10px;
         padding: 9px 10px; margin: 0 0 4px 0;
         border-radius: 8px; background: #fff;
         border: 1px solid transparent;
         transition: background .12s, border-color .12s;
      }
      .svc-item:last-child { margin-bottom: 0; }
      .svc-item:hover { background: var(--bi-bg); border-color: var(--bi-border); }
      .svc-item.is-selected { display: none; }
      .svc-item-icon {
         width: 34px; height: 34px; border-radius: 8px;
         background: linear-gradient(135deg, var(--bi-primary-soft) 0%, var(--bi-primary-soft-2) 100%);
         color: var(--bi-primary);
         display: flex; align-items: center; justify-content: center;
         border: 1px solid var(--bi-primary-soft-2);
         flex-shrink: 0;
      }
      .svc-item-icon i { font-size: 18px; }
      .svc-item-info { flex: 1; min-width: 0; }
      .svc-item-name {
         font-weight: 600; color: var(--bi-text); font-size: 13px;
         white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      }
      .svc-item-meta { color: var(--bi-muted); font-size: 11px; margin-top: 2px; }
      .svc-item-price {
         background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;
         border-radius: 6px; padding: 3px 8px;
         font-weight: 700; font-size: 12px;
         font-variant-numeric: tabular-nums;
         flex-shrink: 0;
      }

      /* Selected services list (right column) */
      .bi-col-list-svc { padding: 10px; max-height: 520px; min-height: 360px; }

      /* Service row card (editable) */
      .svc-row {
         background: #fff;
         border: 1px solid var(--bi-primary-soft-2);
         border-radius: var(--bi-radius-sm);
         margin-bottom: 10px;
         transition: box-shadow .15s, border-color .15s;
         box-shadow: 0 1px 2px rgba(79,70,229,.06);
         overflow: hidden;
      }
      .svc-row:last-child { margin-bottom: 0; }
      .svc-row:hover { box-shadow: var(--bi-shadow); border-color: var(--bi-primary); }
      .svc-row-head {
         display: flex; align-items: center; gap: 10px;
         padding: 10px 12px;
         background: linear-gradient(180deg, var(--bi-primary-soft) 0%, #fff 100%);
         border-bottom: 1px solid var(--bi-primary-soft-2);
      }
      .svc-row-icon {
         width: 32px; height: 32px; border-radius: 8px;
         background: #fff; color: var(--bi-primary);
         display: flex; align-items: center; justify-content: center;
         border: 1px solid var(--bi-primary-soft-2);
         flex-shrink: 0;
      }
      .svc-row-icon i { font-size: 18px; }
      .svc-row-title-input {
         flex: 1; min-width: 0;
         border: 1px solid transparent !important;
         background: transparent !important;
         font-weight: 700 !important; font-size: 14px !important;
         padding: 6px 8px !important;
         color: var(--bi-text) !important;
         border-radius: 6px !important;
      }
      .svc-row-title-input:focus {
         background: #fff !important;
         border-color: var(--bi-primary) !important;
         box-shadow: 0 0 0 3px var(--bi-primary-soft) !important;
         outline: none;
      }
      .svc-row-remove {
         width: 32px; height: 32px; border-radius: 8px;
         color: var(--bi-danger); background: #fff;
         border: 1px solid #fee2e2; cursor: pointer;
         display: inline-flex; align-items: center; justify-content: center;
         transition: all .15s;
         flex-shrink: 0; padding: 0;
      }
      .svc-row-remove:hover {
         background: var(--bi-danger); color: #fff; border-color: var(--bi-danger);
         transform: scale(1.05);
      }
      .svc-row-remove i { font-size: 16px; }
      .svc-row-body { padding: 12px 14px; }
      .svc-row-body label.form-label {
         font-size: 11px !important; color: var(--bi-muted); font-weight: 600 !important;
         margin-bottom: 6px; text-transform: uppercase; letter-spacing: .3px;
      }
      .svc-row-body .form-control {
         border-radius: 8px; border-color: var(--bi-border); font-size: 13px;
         padding: 8px 10px;
      }
      .svc-row-body .form-control:focus {
         border-color: var(--bi-primary); box-shadow: 0 0 0 3px var(--bi-primary-soft);
      }
      .svc-price-input {
         display: flex; align-items: stretch;
         border: 1px solid var(--bi-border); border-radius: 8px;
         background: #fff; overflow: hidden;
         transition: border-color .12s, box-shadow .12s;
      }
      .svc-price-input:focus-within {
         border-color: var(--bi-primary); box-shadow: 0 0 0 3px var(--bi-primary-soft);
      }
      .svc-price-input .svc-price-prefix {
         padding: 0 10px; background: var(--bi-bg); color: var(--bi-muted);
         font-weight: 700; font-size: 13px;
         border-right: 1px solid var(--bi-border);
         display: inline-flex; align-items: center;
      }
      .svc-price-input input.form-control {
         border: none !important; box-shadow: none !important;
         border-radius: 0 !important; flex: 1; font-weight: 600;
      }

      /* Sub-card sections (Invoice Settings, Totals) */
      .bi-section {
         background: #fff; border: 1px solid var(--bi-border);
         border-radius: var(--bi-radius-sm);
         margin-top: 18px; overflow: hidden;
      }
      .bi-section-head {
         padding: 12px 16px; border-bottom: 1px solid var(--bi-border);
         background: #fafbfc;
         display: flex; align-items: center; justify-content: space-between;
      }
      .bi-section-title {
         display: inline-flex; align-items: center; gap: 8px;
         font-weight: 700; font-size: 12px; color: var(--bi-text);
         text-transform: uppercase; letter-spacing: .4px;
      }
      .bi-section-title i { font-size: 18px; color: var(--bi-primary); }
      .bi-section-sub {
         font-weight: 500; font-size: 11px; color: var(--bi-muted);
         text-transform: none; letter-spacing: 0; margin-left: 6px;
      }
      .bi-section-body { padding: 16px; }

      /* Service dual-list ratio (catalog narrower, selected wider) */
      .bi-dual.svc-dual { grid-template-columns: 1fr 1.4fr; }
      @media (max-width: 992px) {
         .bi-dual.svc-dual { grid-template-columns: 1fr; }
      }

      /* Legacy class kept for safety — not used in new layout */
      .svc-empty {
         padding: 28px 20px; text-align: center; color: var(--bi-muted);
         border: 2px dashed var(--bi-border); border-radius: var(--bi-radius-sm);
         background: var(--bi-bg); font-size: 13px;
      }
      .svc-empty i { font-size: 28px; color: var(--bi-muted-2); display: block; margin-bottom: 6px; }

      /* Totals */
      .totals-card {
         background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
         border:1px solid var(--bi-border); border-radius: var(--bi-radius-sm);
         padding:20px 22px; margin-top:18px; max-width: 480px; margin-left:auto;
         box-shadow: var(--bi-shadow-sm);
      }
      /* Totals strip — slim single-row summary */
      .totals-strip {
         display: flex; align-items: stretch;
         margin-top: 16px;
         background: #fff;
         border: 1px solid var(--bi-border);
         border-radius: var(--bi-radius-sm);
         overflow: hidden;
         box-shadow: var(--bi-shadow-sm);
      }
      .totals-segment {
         flex: 1; min-width: 0;
         display: flex; align-items: center; gap: 10px;
         padding: 10px 14px;
      }
      .totals-segment + .totals-segment { border-left: 1px solid var(--bi-border); }
      .totals-segment-label {
         font-size: 11px; font-weight: 700; color: var(--bi-muted);
         text-transform: uppercase; letter-spacing: .4px;
         white-space: nowrap;
         display: inline-flex; align-items: center; gap: 6px;
         margin: 0; cursor: default;
      }
      .totals-segment-label[for] { cursor: pointer; }
      .totals-segment-label input[type="checkbox"] {
         accent-color: var(--bi-primary); cursor: pointer;
         width: 14px; height: 14px; margin: 0;
      }
      .totals-segment-input {
         display: flex; align-items: stretch; flex: 1; min-width: 0;
         border: 1px solid var(--bi-border); border-radius: 6px;
         background: #fff; overflow: hidden;
         transition: border-color .12s, box-shadow .12s;
      }
      .totals-segment-input:focus-within {
         border-color: var(--bi-primary); box-shadow: 0 0 0 3px var(--bi-primary-soft);
      }
      .totals-segment-input .prefix {
         padding: 0 8px; background: var(--bi-bg); color: var(--bi-muted);
         font-weight: 700; font-size: 12px;
         border-right: 1px solid var(--bi-border);
         display: inline-flex; align-items: center;
      }
      .totals-segment-input input.form-control {
         border: none !important; box-shadow: none !important;
         border-radius: 0 !important; flex: 1; min-width: 0;
         padding: 6px 8px !important;
         font-weight: 700; font-size: 13px;
         text-align: right; font-variant-numeric: tabular-nums;
         height: auto;
      }
      .totals-segment-input input.form-control:disabled {
         background: var(--bi-bg); color: var(--bi-muted-2);
      }
      /* Grand total — typography accent only, no heavy fill */
      .totals-segment.grand {
         background: linear-gradient(180deg, var(--bi-primary-soft) 0%, #fff 100%);
      }
      .totals-segment.grand .totals-segment-label { color: var(--bi-primary-dark); }
      .totals-segment.grand .totals-segment-input { border-color: var(--bi-primary-soft-2); }
      .totals-segment.grand .totals-segment-input .prefix {
         background: #fff; color: var(--bi-primary);
         border-right-color: var(--bi-primary-soft-2);
      }
      .totals-segment.grand .totals-segment-input input.form-control {
         color: var(--bi-primary-dark); background: #fff !important;
         font-size: 15px; font-weight: 800;
      }
      @media (max-width: 768px) {
         .totals-strip { flex-direction: column; }
         .totals-segment + .totals-segment { border-left: none; border-top: 1px solid var(--bi-border); }
      }
      .totals-row {
         display:flex; align-items:center; justify-content:space-between;
         padding:8px 0; gap:14px;
      }
      .totals-row label {
         margin:0; color: var(--bi-text); font-weight:500; font-size:14px;
         display:flex; align-items:center; gap:8px;
      }
      .totals-row input {
         width:170px; text-align:right; border-radius: 8px;
         border: 1px solid var(--bi-border); font-weight:600;
      }
      .totals-row input:focus { border-color: var(--bi-primary); box-shadow: 0 0 0 3px var(--bi-primary-soft); }
      .totals-row.grand {
         border-top: 1px dashed var(--bi-border); margin-top:10px; padding-top:16px;
      }
      .totals-row.grand label { font-weight:700; font-size:16px; color: var(--bi-text-strong); }
      .totals-row.grand input {
         font-weight:700; font-size:16px;
         background: linear-gradient(135deg, var(--bi-primary) 0%, var(--bi-primary-dark) 100%);
         border-color: var(--bi-primary); color: #fff;
         box-shadow: 0 4px 12px rgba(79,70,229,.25);
      }

      /* Form labels & inputs */
      .form-label { font-weight:600; font-size:13px; color: var(--bi-text); margin-bottom:6px; }
      .form-control, .form-select {
         border: 1px solid var(--bi-border); border-radius: var(--bi-radius-sm);
         font-size:14px; padding:10px 14px; transition: all .15s;
      }
      .form-control:focus, .form-select:focus {
         border-color: var(--bi-primary); box-shadow: 0 0 0 3px var(--bi-primary-soft); outline:none;
      }

      .service-search-list {
         max-height:280px; overflow-y:auto; border:1px solid var(--bi-border);
         border-radius:8px; padding:10px; background:#fff;
      }
      .service-item { padding:6px 4px; border-radius:6px; }
      .service-item:hover { background:#f7f9fc; }
      .service-item label { cursor:pointer; margin:0; }

      /* Select2 polish */
      .select2-container--default .select2-selection--multiple {
         min-height: 42px; border:1px solid var(--bi-border); border-radius:10px; padding:4px 8px;
      }
      .select2-container--default.select2-container--focus .select2-selection--multiple {
         border-color: var(--bi-primary); box-shadow: 0 0 0 3px rgba(67,97,238,.15);
      }
      .select2-container--default .select2-selection--multiple .select2-selection__choice {
         background: var(--bi-primary-soft); border:1px solid #cfdcff;
         color: var(--bi-primary); border-radius:6px; padding:2px 8px; font-weight:500;
      }
      .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
         color: var(--bi-primary); margin-right:4px;
      }
      .select2-container--default .select2-results__option--highlighted[aria-selected] {
         background: var(--bi-primary);
      }

      /* Sticky submit bar */
      .submit-bar {
         position: sticky; bottom: 0; z-index: 10;
         background: rgba(255,255,255,.95);
         backdrop-filter: blur(10px);
         border-top: 1px solid var(--bi-border);
         padding:16px 24px;
         display:flex; align-items:center; justify-content:space-between;
         flex-wrap:wrap; gap:12px;
      }
      .submit-bar .left { color: var(--bi-muted); font-size:13px; display:inline-flex; align-items:center; gap:8px; }
      .submit-bar .left i { color: var(--bi-primary); font-size:18px; }
      .submit-bar .right { display:flex; gap:10px; }
      .submit-bar .right .btn { padding:10px 22px; font-weight:600; border-radius:10px; font-size:14px; transition: all .15s; }
      .submit-bar .btn-light {
         background:#fff; border:1px solid var(--bi-border); color: var(--bi-text);
      }
      .submit-bar .btn-light:hover { background: var(--bi-bg); border-color: var(--bi-border-2); }
      .submit-bar .btn-primary {
         background: linear-gradient(135deg, var(--bi-primary) 0%, var(--bi-primary-dark) 100%);
         border:none; color:#fff;
         box-shadow: 0 4px 12px rgba(79,70,229,.30);
         display:inline-flex; align-items:center; gap:6px;
      }
      .submit-bar .btn-primary:hover {
         transform: translateY(-1px);
         box-shadow: 0 6px 16px rgba(79,70,229,.40);
      }

      /* Wizard panels & nav */
      .step-panel { display: none; }
      .step-panel.active { display: block; animation: fadeSlide .25s ease; }
      @keyframes fadeSlide {
         from { opacity: 0; transform: translateY(6px); }
         to   { opacity: 1; transform: translateY(0); }
      }
      .wizard-nav {
         display:flex; align-items:center; justify-content:space-between;
         padding: 16px 24px; border-top: 1px solid var(--bi-border);
         background: #fafbfc; flex-wrap:wrap; gap:10px;
      }
      .wizard-nav .left, .wizard-nav .right { display:flex; gap:10px; align-items:center; }
      .wizard-nav .hint { color: var(--bi-muted); font-size:13px; display:inline-flex; align-items:center; gap:6px; }
      .wizard-nav .hint i { color: var(--bi-primary); font-size:18px; }
      .wizard-nav .btn { padding:10px 22px; font-weight:600; border-radius:10px; font-size:14px; transition: all .15s; display:inline-flex; align-items:center; gap:6px; }
      .wizard-nav .btn-prev { background:#fff; border:1px solid var(--bi-border); color: var(--bi-text); }
      .wizard-nav .btn-prev:hover { background: var(--bi-bg); border-color: var(--bi-border-2); }
      .wizard-nav .btn-prev:disabled { opacity:.5; cursor:not-allowed; }
      .wizard-nav .btn-next {
         background: linear-gradient(135deg, var(--bi-primary) 0%, var(--bi-primary-dark) 100%);
         border:none; color:#fff;
         box-shadow: 0 4px 12px rgba(79,70,229,.25);
      }
      .wizard-nav .btn-next:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(79,70,229,.35); }
      .wizard-nav .btn-next:disabled { background: var(--bi-border-2); cursor:not-allowed; box-shadow:none; transform:none; }
      .wizard-nav .btn-success {
         background: linear-gradient(135deg, var(--bi-success) 0%, #047857 100%);
         border:none; color:#fff;
         box-shadow: 0 4px 12px rgba(16,185,129,.30);
      }
      .wizard-nav .btn-success:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(16,185,129,.40); }

      /* Review */
      .review-grid { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
      .review-block {
         background:#fff; border:1px solid var(--bi-border); border-radius: var(--bi-radius-sm);
         padding: 18px;
      }
      .review-block h6 {
         margin:0 0 12px 0; font-size:12px; font-weight:700; color: var(--bi-muted);
         text-transform:uppercase; letter-spacing:.5px;
         display:flex; align-items:center; gap:8px;
      }
      .review-block h6 i { color: var(--bi-primary); font-size:16px; }
      .review-list { max-height: 200px; overflow-y:auto; padding-right:4px; }
      .review-item {
         display:flex; justify-content:space-between; align-items:center;
         padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size:13px;
      }
      .review-item:last-child { border-bottom:none; }
      .review-item .name { font-weight:500; color: var(--bi-text); }
      .review-item .meta { color: var(--bi-muted); font-size:12px; }
      .review-item .price { font-weight:700; color: var(--bi-primary); }
      .review-totals { background: linear-gradient(135deg, var(--bi-primary) 0%, var(--bi-primary-dark) 100%); color:#fff; }
      .review-totals h6 { color: rgba(255,255,255,.8); }
      .review-totals h6 i { color: #fff; }
      .review-totals .review-item { border-bottom-color: rgba(255,255,255,.15); }
      .review-totals .review-item .name { color: rgba(255,255,255,.9); }
      .review-totals .review-item .price { color:#fff; font-size:15px; }
      .review-totals .grand .name { font-weight:700; font-size:14px; }
      .review-totals .grand .price { font-size:20px; }

      @media (max-width: 768px) {
         .review-grid { grid-template-columns: 1fr; }
      }

      .empty-state { padding:50px 20px; text-align:center; color: var(--bi-muted); }
      .empty-state i { font-size:40px; color: var(--bi-muted-2); display:block; margin-bottom:10px; }

      /* Generated invoices table */
      .generated-table { margin: 0; }
      .generated-table th {
         background: var(--bi-bg); color: var(--bi-muted); font-weight:600;
         font-size:11px; text-transform:uppercase; letter-spacing:.5px;
         border:none; padding:14px 18px;
      }
      .generated-table td { padding:14px 18px; border-top:1px solid var(--bi-border); vertical-align:middle; font-size:14px; }
      .generated-table tbody tr:hover { background: var(--bi-bg); }
      .generated-table .btn-outline-primary {
         border-color: var(--bi-primary); color: var(--bi-primary); border-radius:8px;
         font-weight:500; font-size:12px; padding:6px 14px;
      }
      .generated-table .btn-outline-primary:hover {
         background: var(--bi-primary); color:#fff;
      }

      /* Alerts */
      .alert { border-radius: var(--bi-radius-sm); border:1px solid transparent; padding:14px 18px; font-size:14px; }
      .alert-success { background: var(--bi-success-soft); border-color:#a7f3d0; color:#065f46; }
      .alert-danger { background:#fee2e2; border-color:#fecaca; color:#991b1b; }

      @media (max-width: 992px) {
         .bi-sticky-col { position: static !important; }
      }
      @media (max-width: 768px) {
         .totals-card { max-width: 100%; }
         .totals-row input { width: 130px; }
         .bi-hero { padding: 22px 20px; flex-direction:column; align-items:flex-start; gap:14px; }
         .bi-stepper { flex-direction:column; align-items:flex-start; gap:10px; }
         .bi-step .connector { display:none; }
      }
   </style>
</head>

<body class="theme-3">
   <div class="loader-bg"><div class="loader-track"><div class="loader-fill"></div></div></div>

   <?php include "includes/sidebar.php"; ?>
   <?php include "includes/header.php"; ?>

   <div class="dash-container">
      <div class="dash-content">

         <!-- Hero -->
         <div class="bi-hero">
            <div>
               <h4>Bulk Invoice Generation</h4>
               <div class="hero-sub">Create invoices for multiple clients in a single workflow.</div>
               <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                  <li class="breadcrumb-item">Billing</li>
                  <li class="breadcrumb-item active">Bulk Invoice</li>
               </ul>
            </div>
            <div class="hero-icon"><i class="ti ti-file-invoice"></i></div>
         </div>

         <!-- Stepper -->
         <div class="bi-stepper" id="biStepper">
            <div class="bi-step active" data-step="1">
               <div class="num">1</div>
               <div class="label">Select Clients</div>
            </div>
            <div class="connector"></div>
            <div class="bi-step" data-step="2">
               <div class="num">2</div>
               <div class="label">Configure Services</div>
            </div>
            <div class="connector"></div>
            <div class="bi-step" data-step="3">
               <div class="num">3</div>
               <div class="label">Review &amp; Generate</div>
            </div>
         </div>

         <?php if (!empty($msg)) echo $msg; ?>

         <?php if (!empty($generated_invoices)): ?>
            <div class="bulk-card" style="margin-bottom:16px;">
               <div class="card-header">
                  <h5><i class="ti ti-file-invoice"></i> Generated Invoices</h5>
                  <small><?php echo count($generated_invoices); ?> created</small>
               </div>
               <div class="card-body" style="padding:0;">
                  <table class="table mb-0 generated-table" style="font-size:14px;">
                     <thead>
                        <tr>
                           <th style="padding:10px 16px;">Invoice #</th>
                           <th>Client</th>
                           <th>Client ID</th>
                           <th class="text-end" style="padding-right:16px;">PDF</th>
                        </tr>
                     </thead>
                     <tbody>
                        <?php foreach ($generated_invoices as $gi): ?>
                           <tr>
                              <td style="padding:10px 16px;"><b>#<?php echo (int)$gi['invoice_no']; ?></b></td>
                              <td><?php echo htmlspecialchars($gi['name']); ?></td>
                              <td><?php echo (int)$gi['client_id']; ?></td>
                              <td class="text-end" style="padding-right:16px;">
                                 <a href="invoices/<?php echo htmlspecialchars($gi['file']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    View PDF
                                 </a>
                              </td>
                           </tr>
                        <?php endforeach; ?>
                     </tbody>
                  </table>
               </div>
            </div>
         <?php endif; ?>

         <form method="POST" id="bulkInvoiceForm">

            <!-- Step 1: Select Clients -->
            <div class="bulk-card step-panel active" data-panel="1" style="margin-bottom:16px;">
               <div class="card-header">
                  <h5><span class="step-pill">1</span> Select Clients</h5>
                  <small>All active clients are listed</small>
               </div>
               <div class="card-body">

                  <div class="bi-dual">

                     <!-- Available column -->
                     <div class="bi-col">
                        <div class="bi-col-header" style="justify-content:flex-end;">
                           <button type="button" class="bi-col-action" id="addAllVisible" title="Add all currently visible clients">
                              <i class="ti ti-arrow-bar-to-right"></i> Add all visible
                           </button>
                        </div>
                        <div class="bi-col-search">
                           <i class="ti ti-search"></i>
                           <input type="text" id="availSearch" placeholder="Search by name, business or ID…">
                        </div>
                        <div class="bi-col-list" id="availList" data-side="avail">
                           <?php
$clients_q = mysqli_query($con, "
                              SELECT m.member_id, m.name, m.lname, m.bus_name, m.dba, m.prm_email
                              FROM members m
                              WHERE m.status = 1
                              ORDER BY m.bus_name ASC, m.name ASC
                           ");
$client_idx = 0;
if ($clients_q && mysqli_num_rows($clients_q) > 0) {
    while ($cl = mysqli_fetch_assoc($clients_q)) {
        $cid     = (int) $cl['member_id'];
        $display = trim($cl['bus_name']) !== '' ? $cl['bus_name']
        : (trim($cl['dba']) !== '' ? $cl['dba']
            : trim($cl['name'] . ' ' . $cl['lname']));
        if ($display === '') { $display = "Client #" . $cid; }

        $row_search = strtolower($display . ' ' . $cid . ' ' . $cl['prm_email']);
                              ?>
                              <div class="client-item"
                                   id="ci_<?php echo $cid; ?>"
                                   data-cid="<?php echo $cid; ?>"
                                   data-order="<?php echo $client_idx; ?>"
                                   data-search="<?php echo htmlspecialchars($row_search); ?>">
                                 <input class="client-checkbox" type="checkbox"
                                        name="client_ids[]" value="<?php echo $cid; ?>"
                                        id="client_<?php echo $cid; ?>" hidden>
                                 <div class="client-avatar"><?php echo htmlspecialchars(getInitials($display)); ?></div>
                                 <div class="client-info">
                                    <div class="client-name">
                                       <?php echo htmlspecialchars($display); ?>
                                    </div>
                                    <div class="client-meta">
                                       ID: <?php echo $cid; ?>
                                       <?php if (!empty($cl['prm_email'])): ?>
                                          &nbsp;·&nbsp; <?php echo htmlspecialchars($cl['prm_email']); ?>
                                       <?php endif; ?>
                                    </div>
                                 </div>
                                 <button type="button" class="ci-add" title="Add to selected"><i class="ti ti-plus"></i></button>
                                 <button type="button" class="ci-remove" title="Remove from selected"><i class="ti ti-x"></i></button>
                              </div>
                              <?php
        $client_idx++;
    }
} else {
    echo '<div class="bi-empty-pane"><i class="ti ti-mood-empty"></i><div>No active clients found.</div></div>';
}
                           ?>
                           <div class="bi-empty-pane" id="availEmpty" style="display:none;">
                              <i class="ti ti-search-off"></i>
                              <div>No matches.</div>
                           </div>
                        </div>
                     </div>

                     <!-- Selected column -->
                     <div class="bi-col bi-col-selected">
                        <div class="bi-col-header">
                           <span class="bi-col-title"><i class="ti ti-checks"></i> Selected <span class="bi-count primary" id="selCount">0</span></span>
                           <button type="button" class="bi-col-action danger" id="clearAll" title="Remove all selected">
                              <i class="ti ti-x"></i> Clear all
                           </button>
                        </div>
                        <div class="bi-col-search">
                           <i class="ti ti-search"></i>
                           <input type="text" id="selSearch" placeholder="Search selected…">
                        </div>
                        <div class="bi-col-list" id="selList" data-side="selected">
                           <div class="bi-empty-pane" id="selEmpty">
                              <i class="ti ti-arrow-left"></i>
                              <div>No clients selected yet.</div>
                              <small>Pick clients from the Available list.</small>
                           </div>
                        </div>
                     </div>

                  </div>
               </div>
               <div class="wizard-nav">
                  <div class="left">
                     <span class="hint"><i class="ti ti-info-circle"></i> <span id="selHint">Select one or more clients to continue.</span></span>
                  </div>
                  <div class="right">
                     <button type="button" class="btn btn-next" data-go="2">
                        Next: Configure Services <i class="ti ti-arrow-right"></i>
                     </button>
                  </div>
               </div>
            </div>

            <!-- Step 2: Invoice Details -->
            <div class="bulk-card step-panel" data-panel="2" style="margin-bottom:16px;">
               <div class="card-header">
                  <h5><span class="step-pill">2</span> Configure Services &amp; Details</h5>
                  <small>Applied identically to every selected client</small>
               </div>
               <div class="card-body">

                  <!-- Services dual-list -->
                  <div class="bi-dual svc-dual">

                     <!-- Catalog (available) -->
                     <div class="bi-col">
                        <div class="bi-col-header">
                           <span class="bi-col-title"><i class="ti ti-package"></i> Catalog <span class="bi-count" id="svcAvailCount">0</span></span>
                           <button type="button" class="bi-col-action" id="svcAddAll" title="Add all currently visible services">
                              <i class="ti ti-arrow-bar-to-right"></i> Add all visible
                           </button>
                        </div>
                        <div class="bi-col-search">
                           <i class="ti ti-search"></i>
                           <input type="text" id="svcAvailSearch" placeholder="Search services…">
                        </div>
                        <div class="bi-col-list" id="svcAvailList" data-side="avail">
                           <?php
$svc_idx = 0;
$query = mysqli_query($con, "SELECT * FROM memberships ORDER BY membership_name ASC");
while ($services = mysqli_fetch_array($query)) {
    $mid       = (int) $services['membership_id'];
    $sname     = $services['membership_name'];
    $sprice    = (float) $services['membership_price'];
    $svc_search = strtolower($sname . ' ' . $mid);
                           ?>
                           <div class="svc-item"
                                id="svci_<?php echo $mid; ?>"
                                data-mid="<?php echo $mid; ?>"
                                data-name="<?php echo htmlspecialchars($sname, ENT_QUOTES); ?>"
                                data-price="<?php echo $sprice; ?>"
                                data-order="<?php echo $svc_idx; ?>"
                                data-search="<?php echo htmlspecialchars($svc_search, ENT_QUOTES); ?>">
                              <div class="svc-item-icon"><i class="ti ti-package"></i></div>
                              <div class="svc-item-info">
                                 <div class="svc-item-name"><?php echo htmlspecialchars($sname); ?></div>
                                 <div class="svc-item-meta">ID: <?php echo $mid; ?></div>
                              </div>
                              <div class="svc-item-price">$<?php echo number_format($sprice, 2); ?></div>
                              <button type="button" class="ci-add" title="Add to selected"><i class="ti ti-plus"></i></button>
                           </div>
                           <?php
    $svc_idx++;
}
                           ?>
                           <div class="bi-empty-pane" id="svcAvailEmpty" style="display:none;">
                              <i class="ti ti-search-off"></i>
                              <div>No matches.</div>
                           </div>
                        </div>
                     </div>

                     <!-- Selected services (editable) -->
                     <div class="bi-col bi-col-selected">
                        <div class="bi-col-header">
                           <span class="bi-col-title"><i class="ti ti-list-check"></i> Selected Services <span class="bi-count primary" id="svcSelCount">0</span></span>
                           <button type="button" class="bi-col-action danger" id="svcClearAll" title="Remove all selected">
                              <i class="ti ti-x"></i> Clear all
                           </button>
                        </div>
                        <div class="bi-col-list bi-col-list-svc" id="serviceRowsContainer" data-side="selected">
                           <div class="bi-empty-pane" id="serviceEmpty">
                              <i class="ti ti-package"></i>
                              <div>No services added yet.</div>
                              <small>Pick services from the Catalog on the left.</small>
                           </div>
                        </div>
                     </div>

                  </div>

                  <!-- Invoice Details (Settings + Totals combined) -->
                  <div class="bi-section">
                     <div class="bi-section-head">
                        <span class="bi-section-title"><i class="ti ti-file-invoice"></i> Invoice Details <span class="bi-section-sub">applied to every selected client</span></span>
                     </div>
                     <div class="bi-section-body">

                        <!-- Provider + Due Date -->
                        <div class="row g-3">
                           <div class="col-md-6">
                              <label class="form-label">Service Provider</label>
                              <select id="Service_Provider" name="Service_Provider" class="form-control" required>
                                 <option value="">Select Service Provider</option>
                                 <?php
$sp_q = mysqli_query($con, "SELECT user_id, user_name FROM users ORDER BY user_name ASC");
while ($u = mysqli_fetch_assoc($sp_q)) {
    echo '<option value="' . htmlspecialchars($u['user_id']) . '">' . htmlspecialchars($u['user_name']) . '</option>';
}
                                 ?>
                              </select>
                           </div>
                           <div class="col-md-6">
                              <label for="billing_cycle" class="form-label">Due Date</label>
                              <input type="date" class="form-control" required id="billing_cycle" name="billing_cycle">
                           </div>
                        </div>

                        <!-- Totals: slim single-row strip -->
                        <div class="totals-strip">
                           <div class="totals-segment">
                              <span class="totals-segment-label">Subtotal</span>
                              <div class="totals-segment-input">
                                 <span class="prefix">$</span>
                                 <input class="form-control" required name="sub_total" type="number" step="0.01"
                                        id="sub_total" value="0" onkeyup="changetotal()" onchange="changetotal()">
                              </div>
                           </div>
                           <div class="totals-segment">
                              <label class="totals-segment-label" for="dissbox">
                                 <input type="checkbox" id="dissbox" onchange="mydiscountrow()">
                                 Discount
                              </label>
                              <div class="totals-segment-input">
                                 <span class="prefix">$</span>
                                 <input class="form-control" name="discount" type="number" step="0.01"
                                        id="discount" value="0" disabled
                                        onkeyup="getdiscount()" onchange="getdiscount()">
                              </div>
                           </div>
                           <div class="totals-segment grand">
                              <span class="totals-segment-label">Grand Total</span>
                              <div class="totals-segment-input">
                                 <span class="prefix">$</span>
                                 <input class="form-control" required name="grand_total" type="number" step="0.01"
                                        id="grand_total" value="0" readonly>
                              </div>
                           </div>
                        </div>

                     </div>
                  </div>

               </div>

               <div class="wizard-nav">
                  <div class="left">
                     <button type="button" class="btn btn-prev" data-go="1">
                        <i class="ti ti-arrow-left"></i> Previous
                     </button>
                  </div>
                  <div class="right">
                     <span class="hint"><i class="ti ti-info-circle"></i> Add services, provider &amp; due date.</span>
                     <button type="button" class="btn btn-next" data-go="3">
                        Next: Review <i class="ti ti-arrow-right"></i>
                     </button>
                  </div>
               </div>
            </div>

            <!-- Step 3: Review & Generate -->
            <div class="bulk-card step-panel" data-panel="3" style="margin-bottom:16px;">
               <div class="card-header">
                  <h5><span class="step-pill">3</span> Review &amp; Generate</h5>
                  <small>Verify everything before generating invoices</small>
               </div>
               <div class="card-body">
                  <div class="review-grid">
                     <div class="review-block">
                        <h6><i class="ti ti-users"></i> Selected Clients (<span id="reviewClientCount">0</span>)</h6>
                        <div class="review-list" id="reviewClients"></div>
                     </div>
                     <div class="review-block">
                        <h6><i class="ti ti-package"></i> Services (<span id="reviewServiceCount">0</span>)</h6>
                        <div class="review-list" id="reviewServices"></div>
                     </div>
                     <div class="review-block">
                        <h6><i class="ti ti-user-check"></i> Service Provider &amp; Due Date</h6>
                        <div class="review-item">
                           <span class="name">Service Provider</span>
                           <span class="meta" id="reviewProvider">—</span>
                        </div>
                        <div class="review-item">
                           <span class="name">Due Date</span>
                           <span class="meta" id="reviewDueDate">—</span>
                        </div>
                     </div>
                     <div class="review-block review-totals">
                        <h6><i class="ti ti-receipt"></i> Invoice Totals (per client)</h6>
                        <div class="review-item">
                           <span class="name">Subtotal</span>
                           <span class="price" id="reviewSubtotal">$0.00</span>
                        </div>
                        <div class="review-item">
                           <span class="name">Discount</span>
                           <span class="price" id="reviewDiscount">$0.00</span>
                        </div>
                        <div class="review-item grand">
                           <span class="name">Grand Total</span>
                           <span class="price" id="reviewGrand">$0.00</span>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="wizard-nav">
                  <div class="left">
                     <button type="button" class="btn btn-prev" data-go="2">
                        <i class="ti ti-arrow-left"></i> Previous
                     </button>
                     <button type="button" class="btn btn-prev" onclick="window.location.reload();">Reset</button>
                  </div>
                  <div class="right">
                     <span class="hint"><i class="ti ti-info-circle"></i> One invoice &amp; PDF per client.</span>
                     <button type="submit" name="bulkcreateinvoice" class="btn btn-success" onclick="return confirmBulkCreate();">
                        <i class="ti ti-send"></i> Generate Invoices
                     </button>
                  </div>
               </div>
            </div>

         </form>

      </div>
   </div>

   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>

   <script>
      /* ===== Dual-list client picker ===== */
      (function () {
         const $availList   = document.getElementById('availList');
         const $selList     = document.getElementById('selList');
         const $availSearch = document.getElementById('availSearch');
         const $selSearch   = document.getElementById('selSearch');
         const $selCount    = document.getElementById('selCount');
         const $availEmpty  = document.getElementById('availEmpty');
         const $selEmpty    = document.getElementById('selEmpty');
         const $addAll      = document.getElementById('addAllVisible');
         const $clearAll    = document.getElementById('clearAll');
         const $selHint     = document.getElementById('selHint');

         if (!$availList || !$selList) return;

         function items(side) {
            const root = side === 'avail' ? $availList : $selList;
            return Array.from(root.querySelectorAll('.client-item'));
         }
         function visibleItems(side) {
            return items(side).filter(el => el.style.display !== 'none');
         }
         function applyFilter(query, listEl) {
            const q = (query || '').trim().toLowerCase();
            listEl.querySelectorAll('.client-item').forEach(el => {
               const match = q === '' || (el.dataset.search || '').indexOf(q) !== -1;
               el.style.display = match ? '' : 'none';
            });
         }
         function updateEmptyStates() {
            const availTotal = items('avail').length;
            const availVis   = visibleItems('avail').length;
            const selTotal   = items('selected').length;

            if (availTotal === 0) {
               $availEmpty.style.display = '';
               $availEmpty.innerHTML = '<i class="ti ti-checks"></i><div>All clients moved to Selected.</div>';
            } else if (availVis === 0) {
               $availEmpty.style.display = '';
               $availEmpty.innerHTML = '<i class="ti ti-search-off"></i><div>No matches.</div>';
            } else {
               $availEmpty.style.display = 'none';
            }
            $selEmpty.style.display = selTotal === 0 ? '' : 'none';
         }
         function refresh() {
            const selTotal   = items('selected').length;
            $selCount.textContent   = selTotal;
            $clearAll.disabled = selTotal === 0;
            $addAll.disabled   = visibleItems('avail').length === 0;
            $selHint.textContent = selTotal > 0
               ? selTotal + ' client(s) selected.'
               : 'Select one or more clients to continue.';
            updateEmptyStates();
            if (window.updateStepper) window.updateStepper();
         }
         function moveToSelected(item) {
            const cb = item.querySelector('.client-checkbox');
            if (cb) cb.checked = true;
            $selList.appendChild(item);
            applyFilter($selSearch.value, $selList);
         }
         function moveToAvailable(item) {
            const cb = item.querySelector('.client-checkbox');
            if (cb) cb.checked = false;
            const order = parseInt(item.dataset.order, 10) || 0;
            const siblings = items('avail');
            let inserted = false;
            for (let i = 0; i < siblings.length; i++) {
               const sib = siblings[i];
               if ((parseInt(sib.dataset.order, 10) || 0) > order) {
                  $availList.insertBefore(item, sib);
                  inserted = true;
                  break;
               }
            }
            if (!inserted) {
               if ($availEmpty && $availEmpty.parentNode === $availList) {
                  $availList.insertBefore(item, $availEmpty);
               } else {
                  $availList.appendChild(item);
               }
            }
            applyFilter($availSearch.value, $availList);
         }

         window.updateStepper = function () {
            const clientsOk = document.querySelectorAll('.client-checkbox:checked').length > 0;
            const svcOk = document.querySelectorAll('input[name="service[]"]').length > 0;
            const s1 = document.querySelector('.bi-step[data-step="1"]');
            const s2 = document.querySelector('.bi-step[data-step="2"]');
            const s3 = document.querySelector('.bi-step[data-step="3"]');
            if (!s1) return;
            s1.classList.toggle('done', clientsOk);
            s1.classList.toggle('active', !clientsOk);
            s2.classList.toggle('done', clientsOk && svcOk);
            s2.classList.toggle('active', clientsOk && !svcOk);
            s3.classList.toggle('active', clientsOk && svcOk);
            const c1 = s1.nextElementSibling; if (c1) c1.classList.toggle('done', clientsOk);
            const c2 = s2.nextElementSibling; if (c2) c2.classList.toggle('done', clientsOk && svcOk);
         };

         $availList.addEventListener('click', function (e) {
            const btn = e.target.closest('.ci-add');
            if (!btn) return;
            const item = btn.closest('.client-item');
            if (item) { moveToSelected(item); refresh(); }
         });
         $selList.addEventListener('click', function (e) {
            const btn = e.target.closest('.ci-remove');
            if (!btn) return;
            const item = btn.closest('.client-item');
            if (item) { moveToAvailable(item); refresh(); }
         });
         $availList.addEventListener('dblclick', function (e) {
            const item = e.target.closest('.client-item');
            if (item && $availList.contains(item)) { moveToSelected(item); refresh(); }
         });
         $selList.addEventListener('dblclick', function (e) {
            const item = e.target.closest('.client-item');
            if (item && $selList.contains(item)) { moveToAvailable(item); refresh(); }
         });
         $availSearch.addEventListener('input', function () {
            applyFilter(this.value, $availList);
            refresh();
         });
         $selSearch.addEventListener('input', function () {
            applyFilter(this.value, $selList);
            refresh();
         });
         $addAll.addEventListener('click', function () {
            const vis = visibleItems('avail');
            if (vis.length === 0) return;
            vis.forEach(moveToSelected);
            refresh();
         });
         $clearAll.addEventListener('click', function () {
            if (items('selected').length === 0) return;
            if (!confirm('Remove all selected clients?')) return;
            items('selected').forEach(moveToAvailable);
            refresh();
         });

         refresh();
      })();

      /* ===== Dual-list service picker ===== */
      (function () {
         const $availList   = document.getElementById('svcAvailList');
         const $availSearch = document.getElementById('svcAvailSearch');
         const $availCount  = document.getElementById('svcAvailCount');
         const $selCount    = document.getElementById('svcSelCount');
         const $availEmpty  = document.getElementById('svcAvailEmpty');
         const $addAll      = document.getElementById('svcAddAll');
         const $clearAll    = document.getElementById('svcClearAll');

         if (!$availList) return;

         function items() {
            return Array.from($availList.querySelectorAll('.svc-item:not(.is-selected)'));
         }
         function visibleItems() {
            return items().filter(el => el.style.display !== 'none');
         }
         function applyFilter(query) {
            const q = (query || '').trim().toLowerCase();
            $availList.querySelectorAll('.svc-item').forEach(el => {
               const match = q === '' || (el.dataset.search || '').indexOf(q) !== -1;
               el.style.display = match ? '' : 'none';
            });
         }

         window.refreshSvcPicker = function () {
            const availTotal = items().length;
            const availVis   = visibleItems().length;
            const selTotal   = document.querySelectorAll('.svc-row').length;
            $availCount.textContent = availTotal;
            $selCount.textContent   = selTotal;
            $clearAll.disabled = selTotal === 0;
            $addAll.disabled   = availVis === 0;

            if (availTotal === 0) {
               $availEmpty.style.display = '';
               $availEmpty.innerHTML = '<i class="ti ti-checks"></i><div>All services moved to Selected.</div>';
            } else if (availVis === 0) {
               $availEmpty.style.display = '';
               $availEmpty.innerHTML = '<i class="ti ti-search-off"></i><div>No matches.</div>';
            } else {
               $availEmpty.style.display = 'none';
            }

            toggleSvcEmpty();
            if (window.updateStepper) window.updateStepper();
         };

         function pickItem(item) {
            if (!item || item.classList.contains('is-selected')) return;
            const mid   = item.dataset.mid;
            if (document.getElementById('svcRow_' + mid)) return;
            const name  = item.dataset.name || '';
            const price = parseFloat(item.dataset.price) || 0;
            addServiceRow(mid, name, price);
            item.classList.add('is-selected');
         }

         $availList.addEventListener('click', function (e) {
            const btn = e.target.closest('.ci-add');
            if (!btn) return;
            pickItem(btn.closest('.svc-item'));
            recalcTotals();
            window.refreshSvcPicker();
         });
         $availList.addEventListener('dblclick', function (e) {
            const item = e.target.closest('.svc-item');
            if (item) {
               pickItem(item);
               recalcTotals();
               window.refreshSvcPicker();
            }
         });

         $availSearch.addEventListener('input', function () {
            applyFilter(this.value);
            window.refreshSvcPicker();
         });

         $addAll.addEventListener('click', function () {
            const vis = visibleItems();
            if (vis.length === 0) return;
            vis.forEach(pickItem);
            recalcTotals();
            window.refreshSvcPicker();
         });

         $clearAll.addEventListener('click', function () {
            const rows = document.querySelectorAll('.svc-row');
            if (rows.length === 0) return;
            if (!confirm('Remove all selected services?')) return;
            rows.forEach(r => {
               const mid = r.id.replace('svcRow_', '');
               r.remove();
               const it = document.getElementById('svci_' + mid);
               if (it) it.classList.remove('is-selected');
            });
            recalcTotals();
            window.refreshSvcPicker();
         });

         window.refreshSvcPicker();
      })();

      function addServiceRow(mid, name, price) {
         const safeMid = String(mid).replace(/[^a-zA-Z0-9_-]/g, '');
         const html = `
            <div class="svc-row" id="svcRow_${safeMid}">
               <div class="svc-row-head">
                  <div class="svc-row-icon"><i class="ti ti-package"></i></div>
                  <input class="form-control svc-row-title-input" required name="service[]" type="text" value="${escapeHtml(name)}" placeholder="Service name">
                  <button type="button" class="svc-row-remove" title="Remove" onclick="removeServiceRow('${safeMid}')"><i class="ti ti-x"></i></button>
               </div>
               <div class="svc-row-body">
                  <div class="row g-2">
                     <div class="col-md-8">
                        <label class="form-label">Description (optional)</label>
                        <textarea name="description[]" class="form-control" rows="1" placeholder="Add a short description…"></textarea>
                     </div>
                     <div class="col-md-4">
                        <label class="form-label">Unit Price</label>
                        <div class="svc-price-input">
                           <span class="svc-price-prefix">$</span>
                           <input class="form-control sum" required name="unit_price[]" type="number" step="0.01"
                                  min="0" value="${price.toFixed(2)}" oninput="recalcTotals()">
                        </div>
                     </div>
                  </div>
               </div>
            </div>`;
         document.getElementById('serviceRowsContainer').insertAdjacentHTML('beforeend', html);
         toggleSvcEmpty();
         if (window.updateStepper) window.updateStepper();
      }

      function removeServiceRow(mid) {
         const row = document.getElementById('svcRow_' + mid);
         if (row) row.remove();
         const item = document.getElementById('svci_' + mid);
         if (item) item.classList.remove('is-selected');
         recalcTotals();
         toggleSvcEmpty();
         if (window.refreshSvcPicker) window.refreshSvcPicker();
         if (window.updateStepper) window.updateStepper();
      }

      function toggleSvcEmpty() {
         const has = document.querySelectorAll('input[name="service[]"]').length > 0;
         const el = document.getElementById('serviceEmpty');
         if (el) el.style.display = has ? 'none' : 'block';
      }

      function escapeHtml(s) {
         return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
      }

      /* ===== Totals ===== */
      function recalcTotals() {
         let sum = 0;
         document.querySelectorAll('.sum').forEach(i => { sum += parseFloat(i.value) || 0; });
         document.getElementById('sub_total').value = sum.toFixed(2);
         changetotal();
      }
      function changetotal() {
         const s = parseFloat(document.getElementById('sub_total').value) || 0;
         const d = parseFloat(document.getElementById('discount').value)  || 0;
         document.getElementById('grand_total').value = (s - d).toFixed(2);
      }
      function getdiscount() { changetotal(); }
      function mydiscountrow() {
         const cb  = document.getElementById('dissbox');
         const inp = document.getElementById('discount');
         if (cb.checked) {
            inp.disabled = false;
         } else {
            inp.disabled = true;
            inp.value = 0;
            changetotal();
         }
      }

      /* ===== Wizard navigation ===== */
      (function () {
         const panels = document.querySelectorAll('.step-panel');
         const steps  = document.querySelectorAll('.bi-step');

         window.goToStep = function (n) {
            n = parseInt(n, 10);
            if (!n) return;

            // Validation when moving forward
            const current = parseInt(document.querySelector('.step-panel.active')?.dataset.panel || '1', 10);
            if (n > current) {
               if (current === 1) {
                  if (document.querySelectorAll('.client-checkbox:checked').length === 0) {
                     alert('Please select at least one client to continue.');
                     return;
                  }
               }
               if (current === 2) {
                  recalcTotals();
                  if (document.querySelectorAll('input[name="service[]"]').length === 0) {
                     alert('Please add at least one service from the Catalog.');
                     return;
                  }
                  if (!document.getElementById('Service_Provider').value) {
                     alert('Please select a Service Provider.');
                     return;
                  }
                  if (!document.getElementById('billing_cycle').value) {
                     alert('Please select a Due Date.');
                     return;
                  }
               }
            }

            panels.forEach(p => p.classList.toggle('active', parseInt(p.dataset.panel, 10) === n));

            // Update top stepper visuals (active = current; done = passed)
            steps.forEach(s => {
               const sn = parseInt(s.dataset.step, 10);
               s.classList.toggle('active', sn === n);
               s.classList.toggle('done', sn < n);
            });
            document.querySelectorAll('.bi-stepper .connector').forEach((c, idx) => {
               c.classList.toggle('done', idx + 1 < n);
            });

            if (n === 3) populateReview();

            window.scrollTo({ top: 0, behavior: 'smooth' });
         };

         document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-go]');
            if (!btn) return;
            window.goToStep(btn.dataset.go);
         });

         // Allow clicking the top stepper to jump (only backwards or to current)
         steps.forEach(s => {
            s.style.cursor = 'pointer';
            s.addEventListener('click', function () {
               const target = parseInt(this.dataset.step, 10);
               const current = parseInt(document.querySelector('.step-panel.active')?.dataset.panel || '1', 10);
               if (target <= current) window.goToStep(target);
               else window.goToStep(target); // forward triggers validation
            });
         });
      })();

      function populateReview() {
         // Clients
         const checked = document.querySelectorAll('.client-checkbox:checked');
         document.getElementById('reviewClientCount').textContent = checked.length;
         const cHtml = Array.from(checked).map(cb => {
            const row = cb.closest('.client-item') || cb.closest('.client-row');
            const name = row?.querySelector('.client-name')?.childNodes[0]?.textContent.trim() || ('Client #' + cb.value);
            return `<div class="review-item"><span class="name">${escapeHtml(name)}</span><span class="meta">ID: ${cb.value}</span></div>`;
         }).join('');
         document.getElementById('reviewClients').innerHTML = cHtml || '<div class="review-item"><span class="meta">No clients selected.</span></div>';

         // Services
         const svcRows = document.querySelectorAll('.svc-row');
         document.getElementById('reviewServiceCount').textContent = svcRows.length;
         const sHtml = Array.from(svcRows).map(r => {
            const sn = r.querySelector('input[name="service[]"]')?.value || '';
            const sp = parseFloat(r.querySelector('input[name="unit_price[]"]')?.value || 0).toFixed(2);
            return `<div class="review-item"><span class="name">${escapeHtml(sn)}</span><span class="price">$${sp}</span></div>`;
         }).join('');
         document.getElementById('reviewServices').innerHTML = sHtml || '<div class="review-item"><span class="meta">No services added.</span></div>';

         // Provider & Date
         const sp = document.getElementById('Service_Provider');
         document.getElementById('reviewProvider').textContent = sp.options[sp.selectedIndex]?.text || '—';
         const dd = document.getElementById('billing_cycle').value;
         document.getElementById('reviewDueDate').textContent = dd ? new Date(dd).toLocaleDateString('en-US') : '—';

         // Totals
         const sub = parseFloat(document.getElementById('sub_total').value) || 0;
         const dis = parseFloat(document.getElementById('discount').value) || 0;
         const gt  = parseFloat(document.getElementById('grand_total').value) || 0;
         document.getElementById('reviewSubtotal').textContent = '$' + sub.toFixed(2);
         document.getElementById('reviewDiscount').textContent = '$' + dis.toFixed(2);
         document.getElementById('reviewGrand').textContent    = '$' + gt.toFixed(2);
      }

      /* ===== Submit confirmation ===== */
      function confirmBulkCreate() {
         const n = document.querySelectorAll('.client-checkbox:checked').length;
         if (n === 0) { alert('Please select at least one client.'); return false; }

         recalcTotals();

         const svc = document.querySelectorAll('input[name="service[]"]').length;
         if (svc === 0) { alert('Please add at least one service from the Catalog.'); return false; }
         return confirm("Generate invoices for " + n + " client(s)?");
      }
   </script>
</body>
</html>
