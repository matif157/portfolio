<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";

function esc($value)
{
   return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$invoices_result = mysqli_query($con, "
   SELECT * FROM (
      SELECT
         bii.invoice_id,
         bii.mem_id,
         bii.name,
         bii.email,
         bii.invoice_date,
         bii.due_date,
         bii.total_amount,
         bii.paid_amount,
         bii.balance,
         bii.invoice_status,
         bii.filelink,
         (SELECT MAX(spl.created_at) FROM stripe_payment_links spl WHERE spl.invoice_id = bii.invoice_id) AS last_link_sent_at,
         (SELECT COUNT(*) FROM stripe_payment_links spl WHERE spl.invoice_id = bii.invoice_id) AS links_count,
         'Instant' AS invoice_type
      FROM billing_invoice_instant bii
      WHERE EXISTS (
         SELECT 1 FROM stripe_payment_links spl WHERE spl.invoice_id = bii.invoice_id
      )

      UNION ALL

      SELECT
         bi.invoice_id,
         bi.mem_id,
         COALESCE(NULLIF(TRIM(CONCAT(m.name, ' ', m.lname)), ''), m.bus_name, 'N/A') AS name,
         COALESCE(NULLIF(m.prm_email, ''), m.sec_email, 'N/A') AS email,
         bi.invoice_date,
         bi.due_date,
         bi.total_amount,
         bi.paid_amount,
         bi.balance,
         bi.invoice_status,
         bi.filelink,
         (SELECT MAX(spl.created_at) FROM stripe_payment_links spl WHERE spl.invoice_id = bi.invoice_id) AS last_link_sent_at,
         (SELECT COUNT(*) FROM stripe_payment_links spl WHERE spl.invoice_id = bi.invoice_id) AS links_count,
         'Other' AS invoice_type
      FROM billing_invoice bi
      LEFT JOIN members m ON m.member_id = bi.mem_id
      WHERE EXISTS (
         SELECT 1 FROM stripe_payment_links spl WHERE spl.invoice_id = bi.invoice_id
      )
   ) x
   ORDER BY x.invoice_id DESC
   LIMIT 200
");

$link_history_result = mysqli_query($con, "
   SELECT
      spl.id,
      spl.invoice_id,
      spl.member_id,
      spl.amount,
      spl.status,
      spl.payment_url,
      spl.stripe_payment_link_id,
      spl.paid_amount,
      spl.paid_at,
      spl.created_at,
      spl.stripe_receipt_url,
      COALESCE(NULLIF(bii.name, ''), NULLIF(TRIM(CONCAT(m.name, ' ', m.lname)), ''), m.bus_name, 'N/A') AS name,
      COALESCE(NULLIF(bii.email, ''), NULLIF(m.prm_email, ''), m.sec_email, 'N/A') AS email,
      CASE
         WHEN bii.invoice_id IS NOT NULL THEN 'Instant'
         WHEN bi.invoice_id IS NOT NULL THEN 'Other'
         ELSE 'Other'
      END AS invoice_type
   FROM stripe_payment_links spl
   LEFT JOIN billing_invoice_instant bii ON bii.invoice_id = spl.invoice_id
   LEFT JOIN billing_invoice bi ON bi.invoice_id = spl.invoice_id
   LEFT JOIN members m ON m.member_id = bi.mem_id
   ORDER BY spl.id DESC
   LIMIT 300
");

$reminders_result = mysqli_query($con, "
   SELECT
      eh.invoice_id,
      eh.sent_at,
      eh.type,
      eh.subject,
      eh.To_mail,
      eh.From_mail,
      u.user_name,
      CASE
         WHEN EXISTS (
            SELECT 1
            FROM billing_invoice_instant bii
            WHERE bii.invoice_id = eh.invoice_id
              AND bii.mem_id = eh.client_id
         ) THEN 'Instant'
         ELSE 'Other'
      END AS invoice_type
   FROM email_history eh
   LEFT JOIN users u ON eh.sent_by = u.user_id
   WHERE eh.type = 'invoice_reminder'
     AND (
         EXISTS (
            SELECT 1
            FROM billing_invoice_instant bii
            WHERE bii.invoice_id = eh.invoice_id
              AND bii.mem_id = eh.client_id
         )
         OR EXISTS (
            SELECT 1
            FROM billing_invoice bi
            WHERE bi.invoice_id = eh.invoice_id
              AND bi.mem_id = eh.client_id
         )
     )
   ORDER BY eh.sent_at DESC
   LIMIT 300
");

$payment_link_invoices = [];
$payment_link_history = [];
$sent_payment_reminders = [];

if ($invoices_result) {
   while ($row = mysqli_fetch_assoc($invoices_result)) {
      $payment_link_invoices[] = $row;
   }
}
if ($link_history_result) {
   while ($row = mysqli_fetch_assoc($link_history_result)) {
      $payment_link_history[] = $row;
   }
}
if ($reminders_result) {
   while ($row = mysqli_fetch_assoc($reminders_result)) {
      $sent_payment_reminders[] = $row;
   }
}

$total_invoices = count($payment_link_invoices);
$total_links = count($payment_link_history);
$total_reminders = count($sent_payment_reminders);
$paid_count = 0;
$partial_count = 0;
$unpaid_count = 0;
$open_balance = 0.0;

foreach ($payment_link_invoices as $inv) {
   $status = (int) $inv['invoice_status'];
   if ($status === 3) {
      $paid_count++;
   } elseif ($status === 2) {
      $partial_count++;
   } else {
      $unpaid_count++;
   }
   $open_balance += (float) $inv['balance'];
}
?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
   <title>Syed Professional Services</title>
   <?php include "includes/links.php"; ?>
   <style>
      :root {
         --pl-primary: #6FD943;
         --pl-primary-dark: #52b82a;
         --pl-primary-soft: #eefbe8;
         --pl-border: #d7edcd;
         --pl-white: #ffffff;
         --pl-text: #1f2a1f;
         --pl-muted: #5f6d5f;
      }

      .pl-page-header {
         border: 1px solid var(--pl-border);
         border-radius: 12px;
         padding: 18px 22px;
         margin-bottom: 18px;
         background: linear-gradient(135deg, var(--pl-white) 0%, var(--pl-primary-soft) 100%);
      }

      .pl-title {
         margin: 0;
         color: var(--pl-primary-dark);
         font-size: 22px;
         font-weight: 700;
      }

      .pl-subtitle {
         margin: 6px 0 0;
         color: var(--pl-muted);
         font-size: 13px;
      }

      .pl-metric {
         border: 1px solid var(--pl-border);
         border-radius: 12px;
         background: var(--pl-white);
         padding: 14px 16px;
         margin-bottom: 12px;
         min-height: 96px;
         box-shadow: 0 8px 20px rgba(31, 42, 31, 0.07);
      }

      .metric-green {
         background: linear-gradient(140deg, #ffffff 0%, #ecfae6 100%);
         border-color: #bae6a8;
      }

      .metric-blue {
         background: linear-gradient(140deg, #ffffff 0%, #eaf4ff 100%);
         border-color: #bbd9ff;
      }

      .metric-orange {
         background: linear-gradient(140deg, #ffffff 0%, #fff3e8 100%);
         border-color: #ffd7b3;
      }

      .metric-purple {
         background: linear-gradient(140deg, #ffffff 0%, #f1ebff 100%);
         border-color: #d8c6ff;
      }

      .pl-metric .label {
         margin: 0;
         font-size: 12px;
         color: var(--pl-muted);
         text-transform: uppercase;
         letter-spacing: .4px;
      }

      .pl-metric .value {
         margin: 8px 0 0;
         font-size: 26px;
         font-weight: 700;
         color: var(--pl-text);
         line-height: 1.1;
      }

      .pl-metric .sub {
         margin: 8px 0 0;
         font-size: 12px;
         color: var(--pl-muted);
      }

      .pl-section-card {
         border: 1px solid var(--pl-border);
         border-radius: 12px;
         overflow: hidden;
         background: var(--pl-white);
         box-shadow: 0 10px 24px rgba(31, 42, 31, 0.08);
      }

      .section-invoices {
         border-color: #bae6a8;
      }

      .section-links {
         border-color: #bbd9ff;
      }

      .section-reminders {
         border-color: #ffd7b3;
      }

      .pl-section-head {
         display: flex;
         justify-content: space-between;
         align-items: center;
         gap: 12px;
         padding: 14px 16px;
         border-bottom: 1px solid var(--pl-border);
         background: var(--pl-primary-soft);
      }

      .section-invoices .pl-section-head {
         background: linear-gradient(90deg, #eaf9e4 0%, #f8fff5 100%);
      }

      .section-links .pl-section-head {
         background: linear-gradient(90deg, #eaf4ff 0%, #f6fbff 100%);
      }

      .section-reminders .pl-section-head {
         background: linear-gradient(90deg, #fff3e8 0%, #fffaf5 100%);
      }

      .pl-section-title {
         margin: 0;
         font-size: 16px;
         font-weight: 700;
         color: var(--pl-text);
      }

      .pl-search {
         width: 320px;
         max-width: 100%;
      }

      .pl-tabs {
         border: 1px solid var(--pl-border);
         border-radius: 10px;
         background: var(--pl-white);
         padding: 5px;
         display: inline-flex;
         gap: 6px;
         margin-bottom: 14px;
      }

      .pl-tab {
         border: 0;
         border-radius: 8px;
         padding: 8px 14px;
         font-size: 13px;
         font-weight: 600;
         background: transparent;
         color: var(--pl-muted);
      }

      .pl-tab.active {
         background: var(--pl-primary);
         color: var(--pl-white);
      }

      .pl-table-wrap {
         overflow: auto;
         max-height: 520px;
      }

      .pl-table {
         margin: 0;
      }

      .pl-table thead th {
         position: sticky;
         top: 0;
         background: var(--pl-primary-soft);
         z-index: 5;
         font-size: 12px;
         letter-spacing: .2px;
         color: var(--pl-text);
         border-bottom: 1px solid var(--pl-border);
         white-space: nowrap;
      }

      .pl-table td {
         vertical-align: middle;
         white-space: nowrap;
      }

      .badge-warning {
         color: var(--pl-white);
         background-color: var(--pl-primary-dark);
      }

      .pl-pill {
         border: 1px solid var(--pl-border);
         border-radius: 999px;
         padding: 4px 10px;
         font-size: 11px;
         color: var(--pl-text);
         background: var(--pl-white);
      }

      .pl-hidden {
         display: none;
      }
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
         <div class="pl-page-header">
            <h4 class="pl-title">Payment Link Invoices</h4>
            <p class="pl-subtitle">Read-only visibility for payment-link invoices, link send history, and reminder logs.</p>
         </div>

         <div class="row">
            <div class="col-6 col-md-3">
               <div class="pl-metric metric-green">
                  <p class="label">Invoices</p>
                  <p class="value"><?php echo $total_invoices; ?></p>
                  <p class="sub">Generated via payment links</p>
               </div>
            </div>
            <div class="col-6 col-md-3">
               <div class="pl-metric metric-blue">
                  <p class="label">Link History</p>
                  <p class="value"><?php echo $total_links; ?></p>
                  <p class="sub">Total links sent</p>
               </div>
            </div>
            <div class="col-6 col-md-3">
               <div class="pl-metric metric-orange">
                  <p class="label">Reminders</p>
                  <p class="value"><?php echo $total_reminders; ?></p>
                  <p class="sub">Sent reminder emails</p>
               </div>
            </div>
            <div class="col-6 col-md-3">
               <div class="pl-metric metric-purple">
                  <p class="label">Open Balance</p>
                  <p class="value">$<?php echo number_format($open_balance, 2); ?></p>
                  <p class="sub"><?php echo $paid_count; ?> paid, <?php echo $partial_count; ?> partial, <?php echo $unpaid_count; ?> unpaid</p>
               </div>
            </div>
         </div>

         <div class="pl-tabs" role="tablist" aria-label="Payment Link Sections">
            <button class="pl-tab active" data-tab-target="tab-invoices" type="button">Invoices</button>
            <button class="pl-tab" data-tab-target="tab-links" type="button">Payment Link History</button>
            <button class="pl-tab" data-tab-target="tab-reminders" type="button">Payment Reminders</button>
         </div>

         <div class="pl-tab-panel" id="tab-invoices">
            <div class="card pl-section-card section-invoices">
               <div class="pl-section-head">
                  <h5 class="pl-section-title">Invoices Generated Via Payment Links</h5>
                  <input type="text" class="form-control pl-search table-search" data-target-table="table-invoices"
                     placeholder="Search invoice, client, email, status">
               </div>
               <div class="pl-table-wrap">
                  <table class="table table-bordered table-striped pl-table" id="table-invoices">
                     <thead>
                        <tr>
                           <th>Invoice ID</th>
                           <th>Type</th>
                           <th>Client</th>
                           <th>Email</th>
                           <th>Total</th>
                           <th>Paid</th>
                           <th>Balance</th>
                           <th>Status</th>
                           <th>Links Sent</th>
                           <th>Last Link Sent</th>
                           <th>Invoice</th>
                        </tr>
                     </thead>
                     <tbody>
                        <?php if ($total_invoices > 0) { ?>
                           <?php foreach ($payment_link_invoices as $row) { ?>
                              <?php $status = (int) $row['invoice_status']; ?>
                              <tr>
                                 <td><?php echo esc($row['invoice_id']); ?></td>
                                 <td><span class="pl-pill"><?php echo esc($row['invoice_type']); ?></span></td>
                                 <td><?php echo esc($row['name']); ?></td>
                                 <td><?php echo esc($row['email']); ?></td>
                                 <td>$<?php echo number_format((float) $row['total_amount'], 2); ?></td>
                                 <td>$<?php echo number_format((float) $row['paid_amount'], 2); ?></td>
                                 <td>$<?php echo number_format((float) $row['balance'], 2); ?></td>
                                 <td>
                                    <?php if ($status === 3) { ?>
                                       <span class="badge badge-success">Paid</span>
                                    <?php } elseif ($status === 2) { ?>
                                       <span class="badge badge-warning">Partial Paid</span>
                                    <?php } else { ?>
                                       <span class="badge badge-danger">Unpaid</span>
                                    <?php } ?>
                                 </td>
                                 <td><span class="pl-pill"><?php echo esc($row['links_count']); ?></span></td>
                                 <td>
                                    <?php echo !empty($row['last_link_sent_at']) ? esc(date("m/d/Y h:i A", strtotime($row['last_link_sent_at']))) : '-'; ?>
                                 </td>
                                 <td>
                                    <?php $invoice_dir = ($row['invoice_type'] === 'Instant') ? 'instant_invoices' : 'invoices'; ?>
                                    <?php if (!empty($row['filelink'])) { ?>
                                       <a class="btn btn-sm btn-primary" href="<?php echo esc($invoice_dir); ?>/<?php echo esc($row['filelink']); ?>"
                                          target="_blank">View</a>
                                    <?php } else { ?>
                                       -
                                    <?php } ?>
                                 </td>
                              </tr>
                           <?php } ?>
                        <?php } else { ?>
                           <tr>
                              <td colspan="11" class="text-center">No payment-link invoices found.</td>
                           </tr>
                        <?php } ?>
                     </tbody>
                  </table>
               </div>
            </div>
         </div>

         <div class="pl-tab-panel pl-hidden" id="tab-links">
            <div class="card pl-section-card section-links">
               <div class="pl-section-head">
                  <h5 class="pl-section-title">Sent Payment Link History</h5>
                  <input type="text" class="form-control pl-search table-search" data-target-table="table-links"
                     placeholder="Search invoice, client, email, status">
               </div>
               <div class="pl-table-wrap">
                  <table class="table table-bordered table-striped pl-table" id="table-links">
                     <thead>
                        <tr>
                           <th>Sent At</th>
                           <th>Invoice ID</th>
                           <th>Type</th>
                           <th>Client</th>
                           <th>Email</th>
                           <th>Amount</th>
                           <th>Status</th>
                           <th>Paid At</th>
                           <th>Receipt</th>
                           <th>Payment Link</th>
                        </tr>
                     </thead>
                     <tbody>
                        <?php if ($total_links > 0) { ?>
                           <?php foreach ($payment_link_history as $row) { ?>
                              <tr>
                                 <td><?php echo esc(date("m/d/Y h:i A", strtotime($row['created_at']))); ?></td>
                                 <td><?php echo esc($row['invoice_id']); ?></td>
                                 <td><span class="pl-pill"><?php echo esc($row['invoice_type']); ?></span></td>
                                 <td><?php echo esc($row['name']); ?></td>
                                 <td><?php echo esc($row['email']); ?></td>
                                 <td>$<?php echo number_format((float) $row['amount'], 2); ?></td>
                                 <td><span class="pl-pill"><?php echo esc(ucfirst((string) $row['status'])); ?></span></td>
                                 <td><?php echo !empty($row['paid_at']) ? esc(date("m/d/Y h:i A", strtotime($row['paid_at']))) : '-'; ?>
                                 </td>
                                 <td>
                                    <?php if (!empty($row['stripe_receipt_url'])) { ?>
                                       <a href="<?php echo esc($row['stripe_receipt_url']); ?>" target="_blank">Receipt</a>
                                    <?php } else { ?>
                                       -
                                    <?php } ?>
                                 </td>
                                 <td><a href="<?php echo esc($row['payment_url']); ?>" target="_blank">Open Link</a></td>
                              </tr>
                           <?php } ?>
                        <?php } else { ?>
                           <tr>
                              <td colspan="10" class="text-center">No payment links sent yet.</td>
                           </tr>
                        <?php } ?>
                     </tbody>
                  </table>
               </div>
            </div>
         </div>

         <div class="pl-tab-panel pl-hidden" id="tab-reminders">
            <div class="card pl-section-card section-reminders">
               <div class="pl-section-head">
                  <h5 class="pl-section-title">Sent Payment Reminders</h5>
                  <input type="text" class="form-control pl-search table-search" data-target-table="table-reminders"
                     placeholder="Search invoice, subject, user, email">
               </div>
               <div class="pl-table-wrap">
                  <table class="table table-bordered table-striped pl-table" id="table-reminders">
                     <thead>
                        <tr>
                           <th>Sent At</th>
                           <th>Invoice ID</th>
                           <th>Type</th>
                           <th>Email Type</th>
                           <th>Subject</th>
                           <th>Sent By</th>
                           <th>To</th>
                           <th>From</th>
                        </tr>
                     </thead>
                     <tbody>
                        <?php if ($total_reminders > 0) { ?>
                           <?php foreach ($sent_payment_reminders as $row) { ?>
                              <tr>
                                 <td><?php echo esc(date("m/d/Y h:i A", strtotime($row['sent_at']))); ?></td>
                                 <td><?php echo esc($row['invoice_id']); ?></td>
                                 <td><span class="pl-pill"><?php echo esc($row['invoice_type']); ?></span></td>
                                 <td><?php echo esc($row['type']); ?></td>
                                 <td><?php echo esc($row['subject']); ?></td>
                                 <td><?php echo esc($row['user_name']); ?></td>
                                 <td><?php echo esc($row['To_mail']); ?></td>
                                 <td><?php echo esc($row['From_mail']); ?></td>
                              </tr>
                           <?php } ?>
                        <?php } else { ?>
                           <tr>
                              <td colspan="8" class="text-center">No payment reminders found.</td>
                           </tr>
                        <?php } ?>
                     </tbody>
                  </table>
               </div>
            </div>
         </div>
      </div>
   </div>

   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>
   <script>
      (function () {
         const tabs = document.querySelectorAll('.pl-tab');
         const panels = document.querySelectorAll('.pl-tab-panel');

         tabs.forEach(tab => {
            tab.addEventListener('click', function () {
               const targetId = this.getAttribute('data-tab-target');
               tabs.forEach(t => t.classList.remove('active'));
               panels.forEach(p => p.classList.add('pl-hidden'));

               this.classList.add('active');
               const panel = document.getElementById(targetId);
               if (panel) panel.classList.remove('pl-hidden');
            });
         });

         const searches = document.querySelectorAll('.table-search');
         searches.forEach(input => {
            input.addEventListener('input', function () {
               const tableId = this.getAttribute('data-target-table');
               const table = document.getElementById(tableId);
               if (!table) return;

               const q = this.value.toLowerCase().trim();
               const rows = table.querySelectorAll('tbody tr');
               rows.forEach(row => {
                  const text = row.textContent.toLowerCase();
                  row.style.display = text.indexOf(q) !== -1 ? '' : 'none';
               });
            });
         });
      })();
   </script>
</body>

</html>
