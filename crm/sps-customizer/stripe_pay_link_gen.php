<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";
require_once 'stripe_payment.php';

$user_id = $_SESSION['user_id'];

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
$user_initials  = getInitials($user_full_name);
$user_type      = $_SESSION['user_type'];

// AJAX: return invoices for selected client
if (isset($_GET['ajax_invoices'])) {
   header('Content-Type: application/json');
   $mid = (int) ($_GET['member_id'] ?? 0);
   if (!$mid) { echo json_encode([]); exit; }
   $q = mysqli_query($con, "
       SELECT invoice_id, total_amount, paid_amount, balance, invoice_status, due_date
       FROM billing_invoice WHERE mem_id = $mid ORDER BY invoice_id DESC
   ");
   $rows = [];
   while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;
   echo json_encode($rows);
   exit;
}

// AJAX: return rendered Stripe buttons for selected invoice
if (isset($_GET['ajax_stripe_buttons'])) {
   $invoice_id = (int) ($_GET['invoice_id'] ?? 0);
   $balance    = (float) ($_GET['balance'] ?? 0);
   if (!$invoice_id) { echo '<span class="text-danger">Invalid invoice.</span>'; exit; }
   echo renderStripeButtons($con, $invoice_id, $balance);
   exit;
}

// Fetch clients that have at least one invoice
$clients_query = mysqli_query($con, "
    SELECT DISTINCT m.member_id,
           TRIM(m.bus_name) AS bus_name,
           TRIM(m.dba) AS dba,
           CONCAT(TRIM(m.name),' ',TRIM(m.lname)) AS full_name,
           COALESCE(NULLIF(TRIM(m.bus_name),''), NULLIF(TRIM(m.dba),''), CONCAT(TRIM(m.name),' ',TRIM(m.lname))) AS client_name
    FROM members m
    INNER JOIN billing_invoice bi ON bi.mem_id = m.member_id
    ORDER BY client_name ASC
");
?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">
<head>
   <title>Syed Professional Services</title>
   <?php include "includes/links.php"; ?>
   <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
   <style>
   /* Select2 custom overrides */
   .select2-container--default .select2-selection--single {
      border: 1.5px solid #e2e8f0;
      border-radius: 8px !important;
      height: 44px;
      display: flex;
      align-items: center;
      padding: 0 10px;
   }
   .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 44px;
      color: #2d3748;
      font-size: 14px;
      padding-left: 4px;
   }
   .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 44px;
   }
   .select2-container--default.select2-container--focus .select2-selection--single,
   .select2-container--default.select2-container--open .select2-selection--single {
      border-color: #4680ff;
      box-shadow: 0 0 0 3px rgba(70,128,255,.15);
   }
   .select2-dropdown {
      border: 1.5px solid #e2e8f0;
      border-radius: 8px !important;
      box-shadow: 0 8px 24px rgba(0,0,0,.1);
   }
   .select2-container--default .select2-search--dropdown .select2-search__field {
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      padding: 6px 10px;
      font-size: 13px;
   }
   .select2-container--default .select2-results__option--highlighted[aria-selected] {
      background-color: #4680ff;
   }
   .select2-results__option {
      font-size: 14px;
      padding: 8px 12px;
   }
   .s2-client-id {
      font-size: 11px;
      color: #adb5bd;
      margin-left: 6px;
   }
   .s2-subtext {
      font-size: 11px;
      color: #718096;
      display: block;
   }
   </style>
   <style>
      /* Step indicator */
      .sp-steps {
         display: flex;
         align-items: center;
         margin-bottom: 2rem;
      }
      .sp-step {
         display: flex;
         flex-direction: column;
         align-items: center;
         flex: 1;
         position: relative;
      }
      .sp-step:not(:last-child)::after {
         content: '';
         position: absolute;
         top: 20px;
         left: 60%;
         width: 80%;
         height: 2px;
         background: #dee2e6;
         z-index: 0;
      }
      .sp-step.active:not(:last-child)::after,
      .sp-step.done:not(:last-child)::after {
         background: #4680ff;
      }
      .sp-step-circle {
         width: 40px;
         height: 40px;
         border-radius: 50%;
         border: 2px solid #dee2e6;
         background: #fff;
         display: flex;
         align-items: center;
         justify-content: center;
         font-weight: 700;
         font-size: 15px;
         color: #adb5bd;
         position: relative;
         z-index: 1;
         transition: all .25s;
      }
      .sp-step.active .sp-step-circle {
         border-color: #4680ff;
         background: #4680ff;
         color: #fff;
         box-shadow: 0 0 0 4px rgba(70,128,255,.2);
      }
      .sp-step.done .sp-step-circle {
         border-color: #17ad37;
         background: #17ad37;
         color: #fff;
      }
      .sp-step-label {
         margin-top: 6px;
         font-size: 12px;
         font-weight: 600;
         color: #adb5bd;
         text-align: center;
      }
      .sp-step.active .sp-step-label,
      .sp-step.done .sp-step-label {
         color: #333;
      }

      /* Section cards */
      .sp-section {
         border: 1px solid #e2e8f0;
         border-radius: 10px;
         margin-bottom: 1.5rem;
         transition: opacity .3s, transform .3s;
      }
      .sp-section.locked {
         opacity: .45;
         pointer-events: none;
      }
      .sp-section-header {
         display: flex;
         align-items: center;
         gap: 12px;
         padding: 16px 20px;
         border-bottom: 1px solid #e2e8f0;
         background: #f8faff;
         border-radius: 10px 10px 0 0;
      }
      .sp-section-icon {
         width: 36px;
         height: 36px;
         border-radius: 8px;
         background: #4680ff;
         color: #fff;
         display: flex;
         align-items: center;
         justify-content: center;
         font-size: 16px;
         flex-shrink: 0;
      }
      .sp-section-title {
         font-weight: 700;
         font-size: 15px;
         margin: 0;
         color: #1a202c;
      }
      .sp-section-body {
         padding: 20px;
      }

      /* Invoice detail card */
      .inv-detail-grid {
         display: grid;
         grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
         gap: 12px;
         margin-top: 16px;
      }
      .inv-detail-item {
         background: #f8faff;
         border: 1px solid #e2e8f0;
         border-radius: 8px;
         padding: 12px 14px;
      }
      .inv-detail-item .label {
         font-size: 11px;
         font-weight: 600;
         color: #718096;
         text-transform: uppercase;
         letter-spacing: .5px;
         margin-bottom: 4px;
      }
      .inv-detail-item .value {
         font-size: 16px;
         font-weight: 700;
         color: #1a202c;
      }

      /* Stripe action panel */
      .stripe-action-panel {
         background: linear-gradient(135deg, #667eea10, #764ba210);
         border: 1px dashed #764ba2;
         border-radius: 10px;
         padding: 20px;
         margin-top: 16px;
         display: flex;
         align-items: center;
         gap: 12px;
         flex-wrap: wrap;
      }
      .stripe-action-panel .stripe-logo {
         font-size: 13px;
         font-weight: 700;
         color: #635bff;
         letter-spacing: 1px;
         margin-right: 8px;
      }

      /* Custom select styling */
      .sp-select {
         border: 1.5px solid #e2e8f0;
         border-radius: 8px;
         padding: 10px 14px;
         font-size: 14px;
         color: #2d3748;
         transition: border-color .2s, box-shadow .2s;
         background-color: #fff;
         width: 100%;
      }
      .sp-select:focus {
         border-color: #4680ff;
         box-shadow: 0 0 0 3px rgba(70,128,255,.15);
         outline: none;
      }

      /* Search box */
      .sp-search-wrap {
         position: relative;
         margin-bottom: 10px;
      }
      .sp-search-wrap input {
         padding-left: 36px;
      }
      .sp-search-wrap .search-icon {
         position: absolute;
         left: 11px;
         top: 50%;
         transform: translateY(-50%);
         color: #adb5bd;
         pointer-events: none;
      }
   </style>
</head>
<body class="theme-3">

<div class="loader-bg">
   <div class="loader-track"><div class="loader-fill"></div></div>
</div>

<?php include "includes/sidebar.php"; ?>
<?php include "includes/header.php"; ?>

<div class="dash-container">
   <div class="dash-content">

      <!-- Page header -->
      <div class="card card-body mb-3">
         <div class="page-block">
            <div class="row align-items-center">
               <div class="col-auto">
                  <div class="page-header-title">
                     <h4 class="m-b-10">Stripe Payment Link</h4>
                  </div>
                  <ul class="breadcrumb">
                     <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                     <li class="breadcrumb-item">Stripe Payment Link</li>
                  </ul>
               </div>
            </div>
         </div>
      </div>

      <div class="card">
         <div class="card-body">

            <!-- Step indicators -->
            <div class="sp-steps mb-4">
               <div class="sp-step active" id="step1-indicator">
                  <div class="sp-step-circle">1</div>
                  <div class="sp-step-label">Select Client</div>
               </div>
               <div class="sp-step locked" id="step2-indicator">
                  <div class="sp-step-circle">2</div>
                  <div class="sp-step-label">Select Invoice</div>
               </div>
               <div class="sp-step locked" id="step3-indicator">
                  <div class="sp-step-circle">&#10003;</div>
                  <div class="sp-step-label">Generate & Send</div>
               </div>
            </div>

            <!-- Step 1: Client -->
            <div class="sp-section" id="step1-section">
               <div class="sp-section-header">
                  <div class="sp-section-icon"><i class="ti ti-user"></i></div>
                  <p class="sp-section-title">Step 1 — Select Client</p>
               </div>
               <div class="sp-section-body">
                  <select id="clientSelect" class="sp-select" style="width:100%;">
                     <option value="">-- Search by name, business or ID --</option>
                     <?php while ($cl = mysqli_fetch_assoc($clients_query)):
                        $busname  = htmlspecialchars($cl['bus_name']);
                        $dba      = htmlspecialchars($cl['dba']);
                        $fullname = htmlspecialchars($cl['full_name']);
                        $display  = htmlspecialchars($cl['client_name']);
                        $mid      = (int)$cl['member_id'];
                     ?>
                     <option value="<?= $mid ?>"
                        data-id="<?= $mid ?>"
                        data-busname="<?= $busname ?>"
                        data-dba="<?= $dba ?>"
                        data-fullname="<?= $fullname ?>">
                        <?= $display ?>
                     </option>
                     <?php endwhile; ?>
                  </select>
               </div>
            </div>

            <!-- Step 2: Invoice -->
            <div class="sp-section locked" id="step2-section">
               <div class="sp-section-header">
                  <div class="sp-section-icon" style="background:#f39c12;"><i class="ti ti-file-invoice"></i></div>
                  <p class="sp-section-title">Step 2 — Select Invoice</p>
               </div>
               <div class="sp-section-body">
                  <select id="invoiceSelect" class="sp-select" style="width:100%;">
                     <option value="">Select a client first...</option>
                  </select>

                  <!-- Invoice detail preview -->
                  <div class="inv-detail-grid" id="invoiceDetails" style="display:none;">
                     <div class="inv-detail-item">
                        <div class="label">Invoice #</div>
                        <div class="value" id="det-invid">—</div>
                     </div>
                     <div class="inv-detail-item">
                        <div class="label">Total</div>
                        <div class="value" id="det-total">—</div>
                     </div>
                     <div class="inv-detail-item">
                        <div class="label">Paid</div>
                        <div class="value text-success" id="det-paid">—</div>
                     </div>
                     <div class="inv-detail-item">
                        <div class="label">Balance</div>
                        <div class="value text-danger" id="det-balance">—</div>
                     </div>
                     <div class="inv-detail-item">
                        <div class="label">Due Date</div>
                        <div class="value" id="det-due">—</div>
                     </div>
                     <div class="inv-detail-item">
                        <div class="label">Status</div>
                        <div class="value" id="det-status">—</div>
                     </div>
                  </div>
               </div>
            </div>

            <!-- Step 3: Stripe actions -->
            <div class="sp-section locked" id="step3-section">
               <div class="sp-section-header">
                  <div class="sp-section-icon" style="background:#635bff;"><i class="ti ti-credit-card"></i></div>
                  <p class="sp-section-title">Step 3 — Generate &amp; Send Payment Link</p>
               </div>
               <div class="sp-section-body">
                  <p class="text-muted mb-2" style="font-size:13px;">
                     Use the buttons below to generate a Stripe payment link, copy it, check payment status, or email it to the client.
                  </p>
                  <div class="stripe-action-panel">
                     <span class="stripe-logo">STRIPE</span>
                     <div id="stripeButtons"></div>
                  </div>
               </div>
            </div>

         </div>
      </div>

   </div>
</div>

<?php include "includes/footer.php"; ?>
<?php include "includes/scripts.php"; ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<?= getStripeJavaScript() ?>

<script>
// Track the currently selected invoice so we can refresh buttons without reload
let _currentInvoiceId = null;
let _currentBalance   = null;

function refreshStripePanel() {
   if (!_currentInvoiceId) return;
   fetch('stripe_pay_link_gen.php?ajax_stripe_buttons=1&invoice_id=' + _currentInvoiceId + '&balance=' + _currentBalance)
      .then(r => r.text())
      .then(html => { document.getElementById('stripeButtons').innerHTML = html; });
}

// Override generateStripeLink: same logic but refreshes panel instead of reloading page
function generateStripeLink(invoiceId, amount) {
   if (amount <= 0) {
      Swal.fire('Info', 'Invoice is already paid', 'info');
      return;
   }
   Swal.fire({
      title: 'Generate Stripe Link?',
      html: 'Create secure payment link for <b>$' + amount + '</b>?<br><small>Client pays on Stripe\'s secure page</small>',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, Generate',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#28a745'
   }).then(result => {
      if (!result.isConfirmed) return;
      Swal.fire({ title: 'Creating Link...', html: 'Please wait...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

      fetch('stripe_payment.php?stripe_ajax=generate_link', {
         method: 'POST',
         headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
         body: 'invoice_id=' + encodeURIComponent(invoiceId) + '&amount=' + encodeURIComponent(amount)
      })
      .then(r => r.json())
      .then(data => {
         if (!data.success) throw new Error(data.error || 'Unknown error');
         // Refresh panel immediately so buttons appear right away
         refreshStripePanel();
         Swal.fire({
            title: '✅ Link Created!',
            html: '<div style="text-align:left;">' +
                     '<p><strong>Payment URL:</strong></p>' +
                     '<div class="input-group mb-3">' +
                        '<input type="text" class="form-control" value="' + data.url + '" readonly onclick="this.select()">' +
                        '<button class="btn btn-outline-secondary" onclick="copyStripeLink(\'' + data.url + '\')"><i class="ti ti-copy"></i></button>' +
                     '</div>' +
                     '<p class="text-muted">Use the buttons below to copy, check or email this link</p>' +
                  '</div>',
            width: '600px',
            showConfirmButton: true,
            confirmButtonText: 'OK'
         });
      })
      .catch(error => Swal.fire('Error', error.message, 'error'));
   });
}

// Override checkStripePayment: same logic but refreshes panel instead of reloading page
function checkStripePayment(linkId, invoiceId) {
   Swal.fire({ title: 'Checking Payment...', html: 'Querying Stripe...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

   fetch('stripe_payment.php?stripe_ajax=check_payment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'link_id=' + encodeURIComponent(linkId) + '&invoice_id=' + encodeURIComponent(invoiceId)
   })
   .then(r => r.json())
   .then(data => {
      if (!data.success) throw new Error(data.error || 'Failed to check payment');
      if (data.paid) {
         Swal.fire({
            icon: 'success',
            title: '✅ Payment Received!',
            html: '<div style="text-align:left;">' +
                     '<p><strong>Amount:</strong> $' + data.amount + '</p>' +
                     '<p><strong>Method:</strong> ' + data.payment_method + '</p>' +
                     (data.payment_details ? '<p><strong>Details:</strong> ' + data.payment_details + '</p>' : '') +
                     '<p><strong>Time:</strong> ' + data.paid_at + '</p>' +
                     (data.receipt_url ? '<a href="' + data.receipt_url + '" target="_blank" class="btn btn-sm btn-primary">View Receipt</a>' : '') +
                  '</div>',
            confirmButtonText: 'OK'
         }).then(() => refreshStripePanel());
      } else {
         Swal.fire({ icon: 'info', title: 'Not Paid Yet', text: 'No payment found. Ask the client to complete payment.' });
      }
   })
   .catch(error => Swal.fire('Error', error.message, 'error'));
}

const STATUS_LABELS  = { 1: 'Unpaid', 2: 'Partial Paid', 3: 'Paid' };
const STATUS_CLASSES = { 1: 'text-danger', 2: 'text-warning', 3: 'text-success' };

function setStepState(stepNum) {
   [1,2,3].forEach(n => {
      const ind = document.getElementById('step' + n + '-indicator');
      const sec = document.getElementById('step' + n + '-section');
      if (n < stepNum) {
         ind.className = 'sp-step done';
         sec.classList.remove('locked');
      } else if (n === stepNum) {
         ind.className = 'sp-step active';
         sec.classList.remove('locked');
      } else {
         ind.className = 'sp-step locked';
         sec.classList.add('locked');
      }
   });
}

// Select2 custom matcher: searches name, bus_name, dba, full_name, and ID
function clientMatcher(params, data) {
   if (!params.term || params.term.trim() === '') return data;
   const term = params.term.trim().toLowerCase();
   const el   = data.element;
   const searchable = [
      data.text,
      el ? (el.dataset.busname  || '') : '',
      el ? (el.dataset.dba      || '') : '',
      el ? (el.dataset.fullname || '') : '',
      el ? (el.dataset.id       || '') : ''
   ].join(' ').toLowerCase();
   return searchable.includes(term) ? data : null;
}

// Select2 custom template: show ID tag and sub-line
function clientTemplate(opt) {
   if (!opt.id) return opt.text;
   const el       = opt.element;
   const id       = el ? el.dataset.id : '';
   const busname  = el ? el.dataset.busname  : '';
   const fullname = el ? el.dataset.fullname : '';
   let sub = '';
   if (busname && busname !== opt.text) sub = busname;
   else if (fullname && fullname.trim() !== opt.text) sub = fullname;

   return $('<span>' + opt.text +
      '<span class="s2-client-id">ID: ' + id + '</span>' +
      (sub ? '<span class="s2-subtext">' + sub + '</span>' : '') +
      '</span>');
}

function invoiceMatcher(params, data) {
   if (!params.term || params.term.trim() === '') return data;
   const term = params.term.trim().toLowerCase();
   const el   = data.element;
   const searchable = [
      data.text,
      el ? (el.dataset.invoiceid || '') : ''
   ].join(' ').toLowerCase();
   return searchable.includes(term) ? data : null;
}

function invoiceTemplate(opt) {
   if (!opt.id) return opt.text;
   const el      = opt.element;
   const invId   = el ? el.dataset.invoiceid : '';
   const balance = el ? el.dataset.balance   : '';
   const status  = el ? el.dataset.statuslabel : '';
   const cls     = el ? el.dataset.statusclass  : '';
   return $(`<span>
      <strong>Invoice #${invId}</strong>
      <span class="s2-client-id">Balance: $${parseFloat(balance).toFixed(2)}</span>
      <span class="s2-subtext ${cls}">${status}</span>
   </span>`);
}

function initInvoiceSelect2() {
   if ($('#invoiceSelect').hasClass('select2-hidden-accessible')) {
      $('#invoiceSelect').select2('destroy');
   }
   $('#invoiceSelect').select2({
      placeholder: 'Search by invoice ID or status...',
      allowClear: true,
      matcher: invoiceMatcher,
      templateResult: invoiceTemplate,
      width: '100%'
   });

   $('#invoiceSelect').on('select2:select', function () {
      const sel     = this;
      const opt     = sel.options[sel.selectedIndex];
      if (!sel.value) return;

      const statusId    = parseInt(opt.dataset.status);
      const statusLabel = STATUS_LABELS[statusId] || '—';
      const statusClass = STATUS_CLASSES[statusId] || '';
      const due = opt.dataset.due && opt.dataset.due !== '0000-00-00'
                  ? new Date(opt.dataset.due).toLocaleDateString('en-US') : 'N/A';

      document.getElementById('det-invid').textContent   = '#' + sel.value;
      document.getElementById('det-total').textContent   = '$' + parseFloat(opt.dataset.total).toFixed(2);
      document.getElementById('det-paid').textContent    = '$' + parseFloat(opt.dataset.paid).toFixed(2);
      document.getElementById('det-balance').textContent = '$' + parseFloat(opt.dataset.balance).toFixed(2);
      document.getElementById('det-due').textContent     = due;
      const statusEl = document.getElementById('det-status');
      statusEl.textContent = statusLabel;
      statusEl.className   = 'value ' + statusClass;
      document.getElementById('invoiceDetails').style.display = 'grid';

      _currentInvoiceId = sel.value;
      _currentBalance   = opt.dataset.balance;
      document.getElementById('stripeButtons').innerHTML = '<span class="text-muted">Loading...</span>';
      setStepState(3);

      fetch('stripe_pay_link_gen.php?ajax_stripe_buttons=1&invoice_id=' + sel.value + '&balance=' + opt.dataset.balance)
         .then(r => r.text())
         .then(html => { document.getElementById('stripeButtons').innerHTML = html; });
   });
}

$(document).ready(function () {
   $('#clientSelect').select2({
      placeholder: 'Search by name, business name, or client ID...',
      allowClear: true,
      matcher: clientMatcher,
      templateResult: clientTemplate,
      width: '100%'
   });

   initInvoiceSelect2();

   $('#clientSelect').on('change', function () {
      const memberId   = $(this).val();
      const invoiceSel = document.getElementById('invoiceSelect');
      const invDetails = document.getElementById('invoiceDetails');

      invDetails.style.display = 'none';
      document.getElementById('stripeButtons').innerHTML = '';

      if (!memberId) { setStepState(1); return; }
      setStepState(2);

      // Destroy Select2, reload options, reinit
      if ($('#invoiceSelect').hasClass('select2-hidden-accessible')) {
         $('#invoiceSelect').select2('destroy');
      }
      invoiceSel.innerHTML = '<option value="">Loading...</option>';

      fetch('stripe_pay_link_gen.php?ajax_invoices=1&member_id=' + memberId)
         .then(r => r.json())
         .then(invoices => {
            invoiceSel.innerHTML = '<option value=""></option>';
            if (!invoices.length) {
               invoiceSel.innerHTML = '<option value="">No invoices found</option>';
            } else {
               invoices.forEach(inv => {
                  const statusLabel = STATUS_LABELS[inv.invoice_status] || '';
                  const statusClass = STATUS_CLASSES[inv.invoice_status] || '';
                  const opt = document.createElement('option');
                  opt.value                  = inv.invoice_id;
                  opt.dataset.invoiceid      = inv.invoice_id;
                  opt.dataset.balance        = inv.balance;
                  opt.dataset.total          = inv.total_amount;
                  opt.dataset.paid           = inv.paid_amount;
                  opt.dataset.status         = inv.invoice_status;
                  opt.dataset.statuslabel    = statusLabel;
                  opt.dataset.statusclass    = statusClass;
                  opt.dataset.due            = inv.due_date || '';
                  opt.textContent            = `Invoice #${inv.invoice_id} | $${parseFloat(inv.balance).toFixed(2)} | ${statusLabel}`;
                  invoiceSel.appendChild(opt);
               });
            }
            initInvoiceSelect2();
         });
   });
});
</script>

</body>
</html>
