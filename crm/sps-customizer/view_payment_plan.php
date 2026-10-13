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

include "includes/db.php";
include "includes/auth.php";

$userid    = $_SESSION['user_id'] ?? 0;
$plan_id   = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : 0;

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
                        <div class="pv-meta" style="margin:0 0 14px;">
                           <span>Frequency: <b><?php echo htmlspecialchars($plan['installment_frequency'] ?: '—'); ?></b></span>
                           <span>Next Installment: <b><?php echo pp_d($plan['next_installment_date']); ?></b></span>
                           <span>Installment Amount: <b><?php echo pp_money($plan['installment_amount']); ?></b></span>
                        </div>
                        <table class="pv-table">
                           <thead><tr><th class="col-no">#</th><th>Due Date</th><th class="num">Amount</th><th>Status</th></tr></thead>
                           <tbody>
                              <?php if ($installments): foreach ($installments as $k => $ins): ?>
                                 <tr>
                                    <td class="col-no"><?php echo $k + 1; ?></td>
                                    <td><?php echo pp_d($ins['date']); ?></td>
                                    <td class="num"><?php echo pp_money($ins['amount']); ?></td>
                                    <td><span class="pv-st <?php echo strtolower($ins['status']); ?>"><?php echo $ins['status']; ?></span></td>
                                 </tr>
                              <?php endforeach; else: ?>
                                 <tr><td colspan="4" class="pv-empty">No installment schedule defined.</td></tr>
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
