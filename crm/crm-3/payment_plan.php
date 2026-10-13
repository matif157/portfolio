<?php
/* =============================================================================
 * Payment Plan — Create  (Client Profile › Invoice & Billing)
 * -----------------------------------------------------------------------------
 * Self-contained module. Creates its own tables on first load (no existing
 * table is altered). Captures the plan + its service items and saves them.
 * ===========================================================================*/

error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";
include "includes/activity_log.php";
require_once 'includes/customizer.php';

$userid         = $_SESSION['user_id']  ?? 0;
$user_type      = $_SESSION['user_type'] ?? '';
$user_full_name = $_SESSION['fullname'] ?? '';

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stat      = isset($_GET['status']) ? intval($_GET['status']) : 0;

$msg = '';

/* ---- New tables for this module (existing tables untouched) -------------- */
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `payment_plans` (
  `plan_id` INT(11) NOT NULL AUTO_INCREMENT,
  `client_id` INT(11) NOT NULL,
  `plan_name` VARCHAR(255) NOT NULL,
  `plan_type` VARCHAR(100) DEFAULT NULL,
  `billing_cycle` VARCHAR(50) DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `payment_method_other` VARCHAR(150) DEFAULT NULL,
  `effective_date` DATE DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
  `installment_frequency` VARCHAR(50) DEFAULT NULL,
  `next_installment_date` DATE DEFAULT NULL,
  `installment_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `updated_by` INT(11) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`plan_id`),
  KEY `idx_pp_client` (`client_id`),
  KEY `idx_pp_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($con, "CREATE TABLE IF NOT EXISTS `payment_plan_items` (
  `item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `plan_id` INT(11) NOT NULL,
  `service_id` INT(11) DEFAULT NULL,
  `service_name` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `frequency` VARCHAR(50) DEFAULT NULL,
  `details` VARCHAR(500) DEFAULT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`item_id`),
  KEY `idx_ppi_plan` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ---- Dropdown option sets (edit freely) --------------------------------- */
$PLAN_TYPES       = ['Standard', 'Retainer', 'Installment', 'Custom'];
$BILLING_CYCLES   = ['Monthly', 'Quarterly', 'Annually'];
$PAYMENT_METHODS  = ['ACH', 'Card', 'Check', 'Cash', 'Zelle', 'Others'];
$STATUSES         = ['Active', 'Inactive'];
$ITEM_FREQUENCIES = ['Monthly', 'Quarterly', 'Yearly'];
$INSTALL_FREQS    = ['Monthly', 'Quarterly', 'Annually', 'Weekly'];

/* ---- Save handler (runs before any HTML so redirect works) --------------- */
if (isset($_POST['save_payment_plan'])) {

    $in = [
        'client_id'             => intval($_POST['client_id'] ?? 0),
        'plan_name'             => trim($_POST['plan_name'] ?? ''),
        'plan_type'             => trim($_POST['plan_type'] ?? ''),
        'billing_cycle'         => trim($_POST['billing_cycle'] ?? ''),
        'payment_method'        => trim($_POST['payment_method'] ?? ''),
        'payment_method_other'  => trim($_POST['payment_method_other'] ?? ''),
        'effective_date'        => trim($_POST['effective_date'] ?? ''),
        'start_date'            => trim($_POST['start_date'] ?? ''),
        'end_date'              => trim($_POST['end_date'] ?? ''),
        'status'                => trim($_POST['status'] ?? 'Active'),
        'installment_frequency' => trim($_POST['installment_frequency'] ?? ''),
        'next_installment_date' => trim($_POST['next_installment_date'] ?? ''),
        'installment_amount'    => (float) ($_POST['installment_amount'] ?? 0),
        'total_amount'          => (float) ($_POST['total_amount'] ?? 0),
        'notes'                 => trim($_POST['notes'] ?? ''),
    ];

    $item_service_id = $_POST['item_service_id'] ?? [];
    $item_name       = $_POST['item_name']       ?? [];
    $item_amount     = $_POST['item_amount']     ?? [];
    $item_frequency  = $_POST['item_frequency']  ?? [];
    $item_details    = $_POST['item_details']    ?? [];

    $errors = [];
    if ($in['client_id'] <= 0)   { $errors[] = 'Please select a client.'; }
    if ($in['plan_name'] === '') { $errors[] = 'Plan Name is required.'; }
    if ($in['start_date'] && $in['end_date'] && strtotime($in['end_date']) <= strtotime($in['start_date'])) {
        $errors[] = 'Plan end date must be later than the start date.';
    }

    $items = [];
    foreach ($item_name as $i => $nm) {
        $nm = trim($nm);
        if ($nm === '' && (float) ($item_amount[$i] ?? 0) == 0) { continue; }
        $items[] = [
            'service_id' => intval($item_service_id[$i] ?? 0),
            'name'       => $nm,
            'amount'     => (float) ($item_amount[$i] ?? 0),
            'frequency'  => trim($item_frequency[$i] ?? ''),
            'details'    => trim($item_details[$i] ?? ''),
        ];
    }
    if (!$items) { $errors[] = 'Add at least one plan item.'; }

    if ($errors) {
        $msg = "<div class='alert alert-danger' role='alert'><b>Could not save:</b><br>"
             . implode('<br>', array_map('htmlspecialchars', $errors)) . "</div>";
    } else {
        $now = date('Y-m-d H:i:s');
        $post_plan_id = intval($_POST['plan_id'] ?? 0);
        $eff = $in['effective_date'] ?: null;
        $sd  = $in['start_date'] ?: null;
        $ed  = $in['end_date'] ?: null;
        $nid = $in['next_installment_date'] ?: null;
        $pmo = $in['payment_method'] === 'Others' ? $in['payment_method_other'] : null;

        // (re)write the plan's item rows
        $pp_write_items = function ($con, $pid, $items) {
            $istmt = mysqli_prepare($con,
                "INSERT INTO payment_plan_items
                   (plan_id, service_id, service_name, amount, frequency, details, sort_order)
                 VALUES (?,?,?,?,?,?,?)");
            foreach ($items as $idx => $it) {
                $sid = $it['service_id'] ?: null;
                mysqli_stmt_bind_param($istmt, "iisdssi",
                    $pid, $sid, $it['name'], $it['amount'], $it['frequency'], $it['details'], $idx);
                mysqli_stmt_execute($istmt);
            }
            mysqli_stmt_close($istmt);
        };

        if ($post_plan_id > 0) {
            // ---------- UPDATE existing plan ----------
            $stmt = mysqli_prepare($con,
                "UPDATE payment_plans SET
                   client_id=?, plan_name=?, plan_type=?, billing_cycle=?, payment_method=?, payment_method_other=?,
                   effective_date=?, start_date=?, end_date=?, status=?, installment_frequency=?, next_installment_date=?,
                   installment_amount=?, total_amount=?, notes=?, updated_by=?, updated_at=?
                 WHERE plan_id=?");
            mysqli_stmt_bind_param(
                $stmt, "isssssssssssddsisi",
                $in['client_id'], $in['plan_name'], $in['plan_type'], $in['billing_cycle'],
                $in['payment_method'], $pmo, $eff, $sd, $ed, $in['status'],
                $in['installment_frequency'], $nid, $in['installment_amount'], $in['total_amount'],
                $in['notes'], $userid, $now, $post_plan_id
            );
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                mysqli_query($con, "DELETE FROM payment_plan_items WHERE plan_id=" . $post_plan_id);
                $pp_write_items($con, $post_plan_id, $items);
                customizer_save_posted_values($con, 'payment_plans', (int) $post_plan_id, (int) $userid, $_SESSION['fullname'] ?? '');
                if (function_exists('logActivity')) {
                    logActivity($con, $userid, 'UPDATE', 'payment_plans', $post_plan_id,
                        "Updated payment plan '{$in['plan_name']}' for client ID: {$in['client_id']}.");
                }
                header("Location: view_payment_plan.php?plan_id={$post_plan_id}");
                exit;
            } else {
                $msg = "<div class='alert alert-danger' role='alert'><b>Update failed:</b> "
                     . htmlspecialchars(mysqli_error($con)) . "</div>";
            }
        } else {
            // ---------- INSERT new plan ----------
            $stmt = mysqli_prepare($con,
                "INSERT INTO payment_plans
                   (client_id, plan_name, plan_type, billing_cycle, payment_method, payment_method_other,
                    effective_date, start_date, end_date, status, installment_frequency, next_installment_date,
                    installment_amount, total_amount, notes, created_by, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param(
                $stmt, "isssssssssssddsis",
                $in['client_id'], $in['plan_name'], $in['plan_type'], $in['billing_cycle'],
                $in['payment_method'], $pmo, $eff, $sd, $ed, $in['status'],
                $in['installment_frequency'], $nid, $in['installment_amount'], $in['total_amount'],
                $in['notes'], $userid, $now
            );
            if (mysqli_stmt_execute($stmt)) {
                $new_plan_id = mysqli_insert_id($con);
                mysqli_stmt_close($stmt);
                $pp_write_items($con, $new_plan_id, $items);
                customizer_save_posted_values($con, 'payment_plans', (int) $new_plan_id, (int) $userid, $_SESSION['fullname'] ?? '');
                if (function_exists('logActivity')) {
                    logActivity($con, $userid, 'CREATE', 'payment_plans', $new_plan_id,
                        "Created payment plan '{$in['plan_name']}' for client ID: {$in['client_id']}.");
                }
                header("Location: view_payment_plan.php?plan_id={$new_plan_id}");
                exit;
            } else {
                $msg = "<div class='alert alert-danger' role='alert'><b>Save failed:</b> "
                     . htmlspecialchars(mysqli_error($con)) . "</div>";
            }
        }
    }
}

if (isset($_GET['saved'])) {
    $msg = "<div class='alert alert-success' role='alert'><b>Payment plan saved successfully.</b></div>";
}

/* ---- Client (from context) ---------------------------------------------- */
$client_display = '';
if ($client_id > 0) {
    $cq = mysqli_query($con, "SELECT member_id, name, mname, lname, bus_name FROM members WHERE member_id='" . $client_id . "' LIMIT 1");
    if ($cq && ($cm = mysqli_fetch_assoc($cq))) {
        $full = trim(($cm['name'] ?? '') . ' ' . ($cm['mname'] ?? '') . ' ' . ($cm['lname'] ?? ''));
        $client_display = $cm['bus_name'] ?: $full;
    }
}

/* ---- All clients (for the searchable Client dropdown) ------------------- */
$clients = [];
$clq = mysqli_query($con, "SELECT member_id, name, mname, lname, bus_name FROM members ORDER BY bus_name ASC, name ASC");
if ($clq) {
    while ($r = mysqli_fetch_assoc($clq)) {
        $full  = trim(($r['name'] ?? '') . ' ' . ($r['mname'] ?? '') . ' ' . ($r['lname'] ?? ''));
        $label = $r['bus_name'] ?: $full;
        if ($label === '') { $label = 'Client #' . $r['member_id']; }
        $clients[] = ['id' => (int) $r['member_id'], 'label' => $label];
    }
}

/* ---- Services (memberships) for the item rows --------------------------- */
$services = [];
$sq = mysqli_query($con, "SELECT membership_id, membership_name, membership_price FROM memberships ORDER BY membership_name ASC");
if ($sq) {
    while ($r = mysqli_fetch_assoc($sq)) {
        $services[] = [
            'id'    => (int) $r['membership_id'],
            'name'  => $r['membership_name'],
            'price' => number_format((float) ($r['membership_price'] ?? 0), 2, '.', ''),
        ];
    }
}

/* ---- Edit mode: load an existing plan to prefill the form --------------- */
$edit_plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : 0;
$E = null; $E_items = [];
if ($edit_plan_id > 0) {
    $E = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM payment_plans WHERE plan_id=" . $edit_plan_id . " LIMIT 1"));
    if ($E) {
        $client_id = (int) $E['client_id'];
        $ir = mysqli_query($con, "SELECT * FROM payment_plan_items WHERE plan_id=" . $edit_plan_id . " ORDER BY sort_order ASC, item_id ASC");
        if ($ir) { while ($row = mysqli_fetch_assoc($ir)) { $E_items[] = $row; } }
    } else {
        $edit_plan_id = 0;
    }
}
$is_edit = $edit_plan_id > 0 && $E;

function pp_clean_date($d) { return (empty($d) || $d === '0000-00-00') ? '' : $d; }

$v_name    = $is_edit ? $E['plan_name'] : '';
$v_type    = $is_edit ? $E['plan_type'] : '';
$v_billing = $is_edit ? $E['billing_cycle'] : '';
$v_method  = $is_edit ? $E['payment_method'] : '';
$v_status  = $is_edit ? $E['status'] : 'Active';
$v_eff     = $is_edit ? pp_clean_date($E['effective_date']) : '';
$v_start   = $is_edit ? pp_clean_date($E['start_date']) : '';
$v_end     = $is_edit ? pp_clean_date($E['end_date']) : '';
$v_ifreq   = $is_edit ? $E['installment_frequency'] : '';
$v_nextins = $is_edit ? pp_clean_date($E['next_installment_date']) : '';
$v_instamt = $is_edit ? number_format((float) $E['installment_amount'], 2, '.', '') : '0.00';
$v_notes   = $is_edit ? $E['notes'] : '';

function getInitials($name)
{
    $initials = '';
    foreach (explode(' ', (string) $name) as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}
$user_initials = getInitials($user_full_name);
?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="">

<head>
   <meta charset="utf-8">
   <title>Payment Plan | Syed Professional Services</title>
   <?php include "includes/links.php"; ?>

   <style>
      .pp-page {
         --pp-primary:#51459d; --pp-primary-dark:#3d3479; --pp-navy:#2a2c5e; --pp-ink:#293240; --pp-head:#1c232f;
         --pp-muted:#6b7585; --pp-line:#e7e9f1; --pp-soft:#f7f8fc;
         font-family: var(--bs-body-font-family, "Open Sans", sans-serif);
         font-size: 0.875rem; line-height: 1.5; color: var(--pp-ink);
      }
      .pp-page * { box-sizing: border-box; }

      /* header */
      .pp-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
      .pp-head h3 { margin:0 0 4px; font-weight:600; color:var(--pp-head); font-size:1.5rem; line-height:1.2; }
      .pp-head p  { margin:0; color:var(--pp-muted); font-size:.875rem; }
      .pp-head-actions { display:flex; align-items:center; gap:10px; }
      .pp-req { color:var(--pp-muted); font-size:.8125rem; }
      .pp-req b { color:#e2456a; }

      .pp-btn { border:0; border-radius:10px; padding:9px 18px; font-weight:600; font-size:.875rem; cursor:pointer; transition:.15s; text-decoration:none; display:inline-block; }
      .pp-btn-primary { background:var(--pp-navy); color:#fff; }
      .pp-btn-primary:hover { background:#1f2047; color:#fff; }
      .pp-btn-light { background:#fff; color:var(--pp-ink); border:1px solid var(--pp-line); }
      .pp-btn-light:hover { background:var(--pp-soft); }

      /* cards & numbered sections */
      .pp-card { background:#fff; border:1px solid var(--pp-line); border-radius:12px; padding:20px 22px; margin-bottom:18px; box-shadow:0 1px 2px rgba(20,30,60,.03); }
      .pp-sec-title { display:flex; align-items:center; gap:11px; margin-bottom:4px; }
      .pp-badge { width:26px; height:26px; border-radius:50%; background:var(--pp-navy); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.8125rem; font-weight:600; flex:0 0 auto; }
      .pp-sec-title h5 { margin:0; font-weight:600; color:var(--pp-head); font-size:1rem; }
      .pp-sec-sub { color:var(--pp-muted); font-size:.8125rem; margin:2px 0 16px 37px; }

      /* form fields */
      .pp-page .pp-grid { display:grid !important; grid-template-columns:repeat(4,1fr); gap:14px 16px; align-items:start; }
      .pp-page .pp-grid.pp-grid-3 { grid-template-columns:repeat(3,1fr); }
      .pp-page .pp-field { display:flex !important; flex-direction:column; min-width:0; }
      .pp-page .pp-field.col-2 { grid-column:span 2; }
      .pp-page .pp-field.col-4 { grid-column:span 4; }
      .pp-page .pp-field label { font-size:.8125rem; font-weight:600; color:#46505f; margin-bottom:6px; display:block; }
      .pp-page .pp-field label .pp-star { color:#e2456a; }
      .pp-page .pp-input,
      .pp-page .pp-select,
      .pp-page .pp-textarea {
         display:block !important; width:100% !important; max-width:100% !important; height:auto; box-sizing:border-box;
         border:1px solid var(--pp-line); border-radius:10px; padding:8px 12px; margin:0;
         font-size:.875rem; line-height:1.5; color:var(--pp-ink); background:#fff; transition:.15s;
      }
      .pp-page select.pp-select { -webkit-appearance:auto; -moz-appearance:auto; appearance:auto; }
      .pp-page .pp-input:focus, .pp-page .pp-select:focus, .pp-page .pp-textarea:focus { outline:none; border-color:var(--pp-primary); box-shadow:0 0 0 3px rgba(81,69,157,.15); }

      /* searchable Client dropdown (Choices.js) styled like the form fields */
      .pp-page .pp-field .choices { margin-bottom:0; width:100%; }
      .pp-page .pp-field .choices__inner { min-height:38px; padding:5px 12px; border-radius:10px; border:1px solid var(--pp-line); background:#fff; font-size:.875rem; line-height:1.5; }
      .pp-page .pp-field .choices.is-focused .choices__inner { border-color:var(--pp-primary); box-shadow:0 0 0 3px rgba(81,69,157,.15); }
      .pp-page .pp-field .choices__list--single { padding:2px 0; }
      .pp-page .pp-field .choices__list--dropdown { font-size:.875rem; }
      .pp-textarea { resize:vertical; min-height:78px; }
      .pp-page .pp-duration { display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:2px; border:1px solid var(--pp-line); border-radius:10px; padding:0 8px; background:#fff; }
      .pp-page .pp-duration:focus-within { border-color:var(--pp-primary); box-shadow:0 0 0 3px rgba(81,69,157,.15); }
      .pp-page .pp-duration .sep { color:var(--pp-muted); }
      .pp-page .pp-dur-field { width:100%; border:0; outline:none; background:transparent; padding:7px 2px; margin:0; font-size:.875rem; line-height:1.5; color:var(--pp-ink); box-shadow:none; }

      /* calendar icon on single date fields */
      .pp-page .pp-date-ico {
         background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7585' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E");
         background-repeat:no-repeat; background-position:left 11px center; background-size:16px; padding-left:34px !important;
      }
      /* Status field colour reflects state (green = active) */
      .pp-page #statusSelect.is-active { border-color:#3bb273; }
      .pp-page #statusSelect.is-active:focus { box-shadow:0 0 0 3px rgba(59,178,115,.18); }
      .pp-page #statusSelect.is-inactive { border-color:#e06464; }
      .pp-input-money { position:relative; }
      .pp-input-money .cur { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--pp-muted); font-size:.85rem; }
      .pp-input-money input { padding-left:26px; text-align:right; }

      /* plan items table */
      .pp-items-wrap { width:100%; overflow:visible; }
      .pp-items { width:100%; table-layout:fixed; border-collapse:separate; border-spacing:0; }
      .pp-items thead th { background:var(--pp-soft); color:#5a6173; font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.02em; padding:10px 10px; text-align:left; border-bottom:1px solid var(--pp-line); }
      .pp-items td { font-size:.875rem; }
      .pp-items thead th:first-child { border-top-left-radius:10px; }
      .pp-items thead th:last-child  { border-top-right-radius:10px; text-align:center; }
      .pp-items td { padding:8px 10px; border-bottom:1px solid var(--pp-line); vertical-align:middle; }
      .pp-items .col-no   { width:42px; color:var(--pp-muted); font-weight:600; text-align:center; }
      .pp-items .col-amt  { width:150px; }
      .pp-items .col-freq { width:150px; }
      .pp-items .col-act  { width:90px; text-align:center; }
      .pp-row-action { background:transparent; border:0; cursor:pointer; color:var(--pp-muted); padding:6px; border-radius:7px; }
      .pp-row-action:hover { background:var(--pp-soft); color:var(--pp-primary); }
      .pp-row-action.del:hover { color:#e2456a; }
      .pp-add-item { margin-top:14px; background:#eef0fb; color:var(--pp-primary); border:1px dashed #c7c9ec; border-radius:10px; padding:9px 16px; font-weight:600; font-size:.8125rem; cursor:pointer; }
      .pp-add-item:hover { background:#e6e8fa; }
      .pp-items-total { display:flex; justify-content:flex-end; align-items:center; gap:16px; margin-top:16px; font-size:.875rem; color:var(--pp-muted); }
      .pp-items-total b { color:var(--pp-head); font-size:1.0625rem; font-weight:600; }

      /* the Choices.js service select inside rows */
      .pp-items .choices { margin-bottom:0; }
      .pp-items .choices__inner { min-height:38px; padding:4px 7px; border-radius:10px; border-color:var(--pp-line); background:#fff; font-size:.875rem; }
      .pp-items .choices__list--dropdown { font-size:.875rem; }

      /* summary sidebar */
      .pp-summary { position:sticky; top:84px; }
      .pp-summary .pp-card { padding:18px 18px; }
      .pp-sum-h { font-weight:600; color:var(--pp-head); font-size:1rem; margin:0 0 14px; }
      .pp-sum-h small { color:var(--pp-muted); font-weight:400; }
      .pp-install-amt { font-size:1.75rem; font-weight:700; color:var(--pp-primary); line-height:1.1; }
      .pp-install-cap { color:var(--pp-muted); font-size:.8125rem; margin-top:3px; }
      .pp-sum-rows { margin:16px 0 4px; }
      .pp-sum-row { display:flex; justify-content:space-between; gap:10px; padding:7px 0; border-bottom:1px dashed var(--pp-line); font-size:.8125rem; }
      .pp-sum-row span { color:var(--pp-muted); }
      .pp-sum-row b { color:var(--pp-head); font-weight:600; text-align:right; }
      .pp-chip { display:inline-block; padding:2px 10px; border-radius:20px; font-size:.75rem; font-weight:600; }
      .pp-chip.active { background:#e4f7ec; color:#1f9d57; }
      .pp-chip.inactive { background:#fdeaea; color:#d23f3f; }
      .pp-break { margin-top:16px; background:var(--pp-soft); border-radius:11px; padding:12px 14px; }
      .pp-break h6 { font-size:.75rem; text-transform:uppercase; letter-spacing:.02em; color:#5a6173; font-weight:600; margin:0 0 8px; }
      .pp-break .pp-sum-row { border-bottom-color:#e3e6f0; }
      .pp-agree { margin-top:14px; background:#fff7e9; border:1px solid #f3e2bf; border-radius:10px; padding:10px 13px; font-size:.8125rem; color:#8a6d2f; }
      .pp-sign { margin-top:14px; }
      .pp-sign .box { border:1px dashed var(--pp-line); border-radius:10px; height:84px; display:flex; align-items:center; justify-content:center; color:var(--pp-muted); font-size:.8125rem; }

      @media (max-width: 992px){ .pp-page .pp-grid { grid-template-columns:repeat(2,1fr); } .pp-page .pp-field.col-2,.pp-page .pp-field.col-4{ grid-column:span 2; } .pp-summary{ position:static; } }
      @media (max-width: 560px){ .pp-page .pp-grid { grid-template-columns:1fr; } .pp-page .pp-field.col-2,.pp-page .pp-field.col-4{ grid-column:span 1; } }
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
      <?php if ($msg) { echo $msg; } ?>
      <div class="dash-content">
         <div class="pp-page">

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
                              <li class="breadcrumb-item"><a href="client_billing.php?id=<?php echo $client_id; ?>&status=<?php echo $stat; ?>">Billing</a></li>
                           <?php endif; ?>
                           <li class="breadcrumb-item active">Payment Plan</li>
                        </ul>
                     </div>
                  </div>
               </div>
            </div>

            <form method="POST" id="paymentPlanForm" autocomplete="off">
               <input type="hidden" name="save_payment_plan" value="1">
               <input type="hidden" name="plan_id" value="<?php echo $is_edit ? $edit_plan_id : 0; ?>">
               <input type="hidden" name="total_amount" id="totalAmountInput" value="0">

               <!-- title row -->
               <div class="pp-head">
                  <div>
                     <h3><?php echo $is_edit ? 'Edit Payment Plan' : 'Create Payment Plan'; ?></h3>
                     <p>Add plan details, items, and schedule for this client.</p>
                  </div>
                  <div class="pp-head-actions">
                     <span class="pp-req"><b>*</b> Required fields</span>
                     <a class="pp-btn pp-btn-light" href="<?php echo $is_edit ? "view_payment_plan.php?plan_id={$edit_plan_id}" : ($client_id > 0 ? "client_billing.php?id={$client_id}&status={$stat}" : 'index.php'); ?>">Cancel</a>
                     <button type="submit" class="pp-btn pp-btn-primary"><?php echo $is_edit ? 'Update Plan' : 'Save Plan'; ?></button>
                  </div>
               </div>

               <div class="row">
                  <!-- ============ LEFT COLUMN ============ -->
                  <div class="col-lg-8">

                     <!-- 1. Basic Information -->
                     <div class="pp-card">
                        <div class="pp-sec-title"><span class="pp-badge">1</span><h5>Basic Information</h5></div>
                        <div class="pp-sec-sub">Core details that describe this payment plan.</div>

                        <div class="pp-grid">
                           <!-- Row 1 -->
                           <div class="pp-field">
                              <label>Client <span class="pp-star">*</span></label>
                              <select class="pp-client-select" name="client_id" id="clientSelect">
                                 <option value="">Select Client</option>
                                 <?php foreach ($clients as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] === $client_id ? 'selected' : ''; ?>>
                                       <?php echo htmlspecialchars($c['label']); ?>
                                    </option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="pp-field">
                              <label>Plan Name <span class="pp-star">*</span></label>
                              <input type="text" class="pp-input" name="plan_name" id="planName" placeholder="e.g. 2026 Payment Plan" value="<?php echo htmlspecialchars($v_name); ?>" required>
                           </div>
                           <div class="pp-field">
                              <label>Plan Type</label>
                              <select class="pp-select" name="plan_type">
                                 <?php foreach ($PLAN_TYPES as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $t === $v_type ? 'selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="pp-field">
                              <label>Billing Cycle <span class="pp-star">*</span></label>
                              <select class="pp-select" name="billing_cycle" id="billingCycle" required>
                                 <?php foreach ($BILLING_CYCLES as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>" <?php echo $b === $v_billing ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                                 <?php endforeach; ?>
                              </select>
                           </div>

                           <!-- Row 2 -->
                           <div class="pp-field">
                              <label>Payment Method <span class="pp-star">*</span></label>
                              <select class="pp-select" name="payment_method" id="paymentMethod" required>
                                 <?php foreach ($PAYMENT_METHODS as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $p === $v_method ? 'selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="pp-field">
                              <label>Effective Date</label>
                              <input type="text" class="pp-input pp-date" name="effective_date" id="effectiveDate" placeholder="Select date" value="<?php echo htmlspecialchars($v_eff); ?>">
                           </div>
                           <div class="pp-field">
                              <label>Plan Duration <span class="pp-star">*</span></label>
                              <div class="pp-duration">
                                 <input type="text" name="start_date" id="startDate" placeholder="Start date" value="<?php echo htmlspecialchars($v_start); ?>">
                                 <span class="sep">&ndash;</span>
                                 <input type="text" name="end_date" id="endDate" placeholder="End date" value="<?php echo htmlspecialchars($v_end); ?>">
                              </div>
                           </div>
                           <div class="pp-field">
                              <label>Status</label>
                              <select class="pp-select" name="status" id="statusSelect">
                                 <?php foreach ($STATUSES as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $s === $v_status ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                        </div>
                     </div>

                     <!-- 2. Plan Items -->
                     <div class="pp-card">
                        <div class="pp-sec-title"><span class="pp-badge">2</span><h5>Plan Items</h5></div>
                        <div class="pp-sec-sub">Add the items/services included in this payment plan.</div>

                        <div class="pp-items-wrap">
                           <table class="pp-items">
                              <thead>
                                 <tr>
                                    <th class="col-no">#</th>
                                    <th>Item / Service <span class="pp-star">*</span></th>
                                    <th class="col-amt">Amount <span class="pp-star">*</span></th>
                                    <th class="col-freq">Frequency <span class="pp-star">*</span></th>
                                    <th>Details / Remarks</th>
                                    <th class="col-act">Actions</th>
                                 </tr>
                              </thead>
                              <tbody id="itemsBody"><!-- rows injected by JS --></tbody>
                           </table>
                        </div>

                        <button type="button" class="pp-add-item" id="addItemBtn"><i class="fas fa-plus"></i> Add Item</button>

                        <div class="pp-items-total">
                           <span>Total Plan Amount</span>
                           <b id="totalPlanAmount">$0.00</b>
                        </div>
                     </div>

                     <!-- 3. Installment Schedule -->
                     <div class="pp-card">
                        <div class="pp-sec-title"><span class="pp-badge">3</span><h5>Installment Schedule</h5></div>
                        <div class="pp-sec-sub">Define how installments will be billed.</div>

                        <div class="pp-grid pp-grid-3">
                           <div class="pp-field">
                              <label>Installment Frequency</label>
                              <select class="pp-select" name="installment_frequency" id="installFreq">
                                 <?php foreach ($INSTALL_FREQS as $f): ?>
                                    <option value="<?php echo htmlspecialchars($f); ?>" <?php echo $f === $v_ifreq ? 'selected' : ''; ?>><?php echo htmlspecialchars($f); ?></option>
                                 <?php endforeach; ?>
                              </select>
                           </div>
                           <div class="pp-field">
                              <label>Next Installment Date</label>
                              <input type="text" class="pp-input pp-date" name="next_installment_date" id="nextInstallDate" placeholder="Select date" value="<?php echo htmlspecialchars($v_nextins); ?>">
                           </div>
                           <div class="pp-field">
                              <label>Installment Amount</label>
                              <div class="pp-input-money">
                                 <span class="cur">$</span>
                                 <input type="number" step="0.01" min="0" class="pp-input" name="installment_amount" id="installAmount" value="<?php echo htmlspecialchars($v_instamt); ?>">
                              </div>
                           </div>
                        </div>
                        <div class="pp-sec-sub" style="margin:14px 0 0 0;">System will calculate installments automatically based on frequency and plan duration.</div>
                     </div>
                  </div>

                  <!-- ============ RIGHT COLUMN — SUMMARY ============ -->
                  <div class="col-lg-4">
                     <div class="pp-summary">
                        <div class="pp-card">
                           <div class="pp-sum-h">Plan Summary <small>(Preview)</small></div>

                           <div class="pp-install-cap" id="sumInstallCap" style="margin:0 0 4px;">Monthly Installment (ACH)</div>
                           <div class="pp-install-amt" id="sumInstallAmt">$0.00</div>

                           <div class="pp-sum-rows">
                              <div class="pp-sum-row"><span>Next Payment Date</span><b id="sumNextDate">&mdash;</b></div>
                              <div class="pp-sum-row"><span>Payment Method</span><b id="sumMethod">ACH</b></div>
                              <div class="pp-sum-row"><span>Plan Duration</span><b id="sumDuration">&mdash;</b></div>
                              <div class="pp-sum-row"><span>Billing Cycle</span><b id="sumBilling">Monthly</b></div>
                              <div class="pp-sum-row"><span>Status</span><b><span class="pp-chip active" id="sumStatus">Active</span></b></div>
                           </div>

                           <div class="pp-break">
                              <h6>Installment Breakdown</h6>
                              <div class="pp-sum-row"><span>Quarterly (QTRLY)</span><b id="brkQuarterly">$0.00</b></div>
                              <div class="pp-sum-row"><span>Monthly</span><b id="brkMonthly">$0.00</b></div>
                              <div class="pp-sum-row" style="border-bottom:0;"><span><b style="color:var(--pp-head);">Total</b></span><b id="brkTotal">$0.00</b></div>
                           </div>

                           <div class="pp-agree">
                              Client Agrees<br><b id="sumAgreeDate">As of <?php echo date('M d, Y'); ?></b>
                           </div>

                           <div class="pp-sign">
                              <label style="font-size:.8125rem;font-weight:600;color:#46505f;margin-bottom:6px;display:block;">Notes</label>
                              <textarea class="pp-textarea" name="notes" placeholder="Add any notes or special instructions"><?php echo htmlspecialchars($v_notes); ?></textarea>
                           </div>

<div class="pp-sign">
                               <label style="font-size:.8125rem;font-weight:600;color:#46505f;margin-bottom:6px;display:block;">Signature</label>
                               <div class="box">Click to add signature</div>
                            </div>
                         </div>
                      </div>
                   </div>

                   <!-- ============ CUSTOM FIELDS ============ -->
                   <div class="col-12 mt-3">
                      <div class="pp-card">
                         <div class="row">
                            <?php echo customizer_render_custom_fields_block(
                                $con, 'payment_plans',
                                $is_edit ? customizer_record_values($con, 'payment_plans', (int) $edit_plan_id) : array()
                            ); ?>
                         </div>
                      </div>
                   </div>
                </div>
             </form>
         </div>
      </div>
   </div>

   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>

   <!-- Service option list for the Plan Items rows -->
   <script>
      var PP_SERVICES = <?php echo json_encode($services, JSON_UNESCAPED_UNICODE); ?>;
      var PP_FREQS    = <?php echo json_encode($ITEM_FREQUENCIES, JSON_UNESCAPED_UNICODE); ?>;
      var PP_EDIT_ITEMS = <?php echo json_encode(array_map(function ($it) {
          return [
              'service_id'   => (int) $it['service_id'],
              'service_name' => $it['service_name'],
              'amount'       => (float) $it['amount'],
              'frequency'    => $it['frequency'],
              'details'      => $it['details'],
          ];
      }, $E_items), JSON_UNESCAPED_UNICODE); ?>;
   </script>

   <script>
      (function () {
         "use strict";

         var itemsBody  = document.getElementById('itemsBody');
         var rowSeq     = 0;
         var choicesMap = {}; // rowId -> Choices instance

         function money(n) {
            n = parseFloat(n) || 0;
            return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
         }

         /* ---- build one plan item row ---------------------------------- */
         function buildRow(data) {
            var id = ++rowSeq;
            var tr = document.createElement('tr');
            tr.className = 'pp-item-row';
            tr.dataset.rid = id;

            var serviceOpts = '<option value="">Select service</option>';
            PP_SERVICES.forEach(function (s) {
               serviceOpts += '<option value="' + s.id + '" data-price="' + s.price + '">' +
                  String(s.name).replace(/</g, '&lt;') + '</option>';
            });

            var freqOpts = '';
            PP_FREQS.forEach(function (f) { freqOpts += '<option value="' + f + '">' + f + '</option>'; });

            tr.innerHTML =
               '<td class="col-no pp-row-no"></td>' +
               '<td>' +
                  '<select class="pp-service" name="item_service_id[]" data-rid="' + id + '">' + serviceOpts + '</select>' +
                  '<input type="hidden" class="pp-item-name" name="item_name[]" value="">' +
               '</td>' +
               '<td class="col-amt">' +
                  '<div class="pp-input-money"><span class="cur">$</span>' +
                  '<input type="number" step="0.01" min="0" class="pp-input pp-amount" name="item_amount[]" value="0.00"></div>' +
               '</td>' +
               '<td class="col-freq">' +
                  '<select class="pp-select pp-freq" name="item_frequency[]">' + freqOpts + '</select>' +
               '</td>' +
               '<td>' +
                  '<input type="text" class="pp-input pp-details" name="item_details[]" placeholder="Optional notes">' +
               '</td>' +
               '<td class="col-act">' +
                  '<button type="button" class="pp-row-action dup" title="Duplicate"><i class="fas fa-copy"></i></button>' +
                  '<button type="button" class="pp-row-action del" title="Remove"><i class="fas fa-trash"></i></button>' +
               '</td>';

            itemsBody.appendChild(tr);

            // searchable service dropdown
            var sel = tr.querySelector('.pp-service');
            var inst = new Choices(sel, {
               searchEnabled: true,
               shouldSort: false,
               itemSelectText: '',
               placeholder: true,
               searchPlaceholderValue: 'Search services…'
            });
            choicesMap[id] = inst;

            // when a service is chosen: snapshot its name + autofill price if amount is empty/zero
            sel.addEventListener('change', function () {
               var opt = sel.options[sel.selectedIndex];
               tr.querySelector('.pp-item-name').value = opt ? opt.text : '';
               var price = opt ? parseFloat(opt.getAttribute('data-price') || 0) : 0;
               var amt = tr.querySelector('.pp-amount');
               if (price > 0 && (parseFloat(amt.value) || 0) === 0) { amt.value = price.toFixed(2); }
               recalc();
            });

            tr.querySelector('.pp-amount').addEventListener('input', recalc);
            tr.querySelector('.pp-freq').addEventListener('change', recalc);
            tr.querySelector('.del').addEventListener('click', function () { removeRow(tr); });
            tr.querySelector('.dup').addEventListener('click', function () { duplicateRow(tr); });

            // prefill (edit mode)
            if (data) {
               if (data.service_id) { inst.setChoiceByValue(String(data.service_id)); }
               if (data.service_name) { tr.querySelector('.pp-item-name').value = data.service_name; }
               tr.querySelector('.pp-amount').value = (parseFloat(data.amount) || 0).toFixed(2);
               if (data.frequency) { tr.querySelector('.pp-freq').value = data.frequency; }
               tr.querySelector('.pp-details').value = data.details || '';
            }

            renumber();
            return tr;
         }

         function removeRow(tr) {
            var id = tr.dataset.rid;
            if (choicesMap[id]) { try { choicesMap[id].destroy(); } catch (e) {} delete choicesMap[id]; }
            tr.remove();
            if (!itemsBody.querySelector('tr')) { buildRow(); } // always keep one row
            renumber();
            recalc();
         }

         function duplicateRow(tr) {
            var newTr = buildRow();
            var srcSel = tr.querySelector('.pp-service');
            if (srcSel.value) {
               choicesMap[newTr.dataset.rid].setChoiceByValue(srcSel.value);
               newTr.querySelector('.pp-item-name').value = tr.querySelector('.pp-item-name').value;
            }
            newTr.querySelector('.pp-amount').value  = tr.querySelector('.pp-amount').value;
            newTr.querySelector('.pp-freq').value    = tr.querySelector('.pp-freq').value;
            newTr.querySelector('.pp-details').value = tr.querySelector('.pp-details').value;
            renumber();
            recalc();
         }

         function renumber() {
            var n = 0;
            itemsBody.querySelectorAll('tr').forEach(function (tr) {
               tr.querySelector('.pp-row-no').textContent = (++n);
            });
         }

         /* ---- totals + live breakdown --------------------------------- */
         function recalc() {
            var total = 0, m = 0, q = 0, y = 0;
            itemsBody.querySelectorAll('tr').forEach(function (tr) {
               var amt = parseFloat(tr.querySelector('.pp-amount').value) || 0;
               var fr  = tr.querySelector('.pp-freq').value;
               total += amt;
               if (fr === 'Monthly')        m += amt;
               else if (fr === 'Quarterly') q += amt;
               else if (fr === 'Yearly')    y += amt;
            });

            document.getElementById('totalPlanAmount').textContent = money(total);
            document.getElementById('totalAmountInput').value      = total.toFixed(2);
            document.getElementById('brkQuarterly').textContent = money(q);
            document.getElementById('brkMonthly').textContent   = money(m);
            document.getElementById('brkTotal').textContent     = money(total);
         }

         /* ---- summary sidebar bindings -------------------------------- */
         var DASH = '—', ENDASH = '–', ELLIPSIS = '…';
         function syncSummary() {
            document.getElementById('sumMethod').textContent  = val('paymentMethod') || DASH;
            document.getElementById('sumBilling').textContent = val('billingCycle') || DASH;

            var statusSel = document.getElementById('statusSelect');
            var st = statusSel.value;
            statusSel.classList.toggle('is-active', st === 'Active');
            statusSel.classList.toggle('is-inactive', st !== 'Active');
            var chip = document.getElementById('sumStatus');
            chip.textContent = st;
            chip.className = 'pp-chip ' + (st === 'Active' ? 'active' : 'inactive');

            var inst = parseFloat(document.getElementById('installAmount').value) || 0;
            document.getElementById('sumInstallAmt').textContent = money(inst);
            document.getElementById('sumInstallCap').textContent = val('installFreq') + ' Installment (' + (val('paymentMethod') || DASH) + ')';

            var nd = document.getElementById('nextInstallDate').value;
            document.getElementById('sumNextDate').textContent = nd ? fmtDate(nd) : DASH;

            var sd = document.getElementById('startDate').value;
            var ed = document.getElementById('endDate').value;
            document.getElementById('sumDuration').textContent = (sd || ed) ? (fmtDate(sd) + ' ' + ENDASH + ' ' + fmtDate(ed)) : DASH;
         }
         function val(id){ var el=document.getElementById(id); return el?el.value:''; }
         function fmtDate(s){ if(!s) return ELLIPSIS; var d=new Date(s+'T00:00:00'); if(isNaN(d)) return s; return d.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}); }

         /* ---- payment method change updates summary -------------------- */
         document.getElementById('paymentMethod').addEventListener('change', syncSummary);

         /* ---- wire summary-affecting inputs --------------------------- */
         ['installAmount','installFreq','nextInstallDate','statusSelect','billingCycle'].forEach(function(id){
            var el=document.getElementById(id); if(el){ el.addEventListener('input',syncSummary); el.addEventListener('change',syncSummary); }
         });

         /* ---- flatpickr date pickers ---------------------------------- */
         if (window.flatpickr) {
            flatpickr('#effectiveDate',  { altInput: true, altInputClass: 'pp-input pp-date-ico', altFormat: 'F j, Y', dateFormat: 'Y-m-d', onChange: syncSummary });
            flatpickr('#nextInstallDate',{ altInput: true, altInputClass: 'pp-input pp-date-ico', altFormat: 'F j, Y', dateFormat: 'Y-m-d', onChange: syncSummary });
            var fpEnd = flatpickr('#endDate', { altInput: true, altInputClass: 'pp-dur-field', altFormat: 'F j, Y', dateFormat: 'Y-m-d', onChange: syncSummary });
            flatpickr('#startDate', {
               altInput: true, altInputClass: 'pp-dur-field', altFormat: 'F j, Y', dateFormat: 'Y-m-d',
               onChange: function (sel) {
                  if (sel[0]) {
                     var next = new Date(sel[0].getTime());
                     next.setDate(next.getDate() + 1);     // end must be at least the next day
                     fpEnd.set('minDate', next);
                     if (fpEnd.selectedDates[0] && fpEnd.selectedDates[0] < next) { fpEnd.clear(); }
                  }
                  syncSummary();
               }
            });
         }

         /* ---- validation on submit ------------------------------------ */
         document.getElementById('paymentPlanForm').addEventListener('submit', function (e) {
            if (!document.getElementById('clientSelect').value) {
               e.preventDefault();
               alert('Please select a client.');
               return false;
            }
            var sd = document.getElementById('startDate').value;
            var ed = document.getElementById('endDate').value;
            if (sd && ed && new Date(ed) <= new Date(sd)) {
               e.preventDefault();
               alert('Plan end date must be later than the start date.');
               return false;
            }
            // ensure each row's hidden name reflects current selection
            itemsBody.querySelectorAll('tr').forEach(function (tr) {
               var sel = tr.querySelector('.pp-service');
               var opt = sel.options[sel.selectedIndex];
               if (opt && opt.value) { tr.querySelector('.pp-item-name').value = opt.text; }
            });
         });

         /* ---- searchable Client dropdown ------------------------------ */
         if (window.Choices) {
            var clientEl = document.getElementById('clientSelect');
            if (clientEl) {
               new Choices(clientEl, {
                  searchEnabled: true,
                  shouldSort: false,
                  itemSelectText: '',
                  placeholder: true,
                  searchPlaceholderValue: 'Search clients…'
               });
            }
         }

         /* ---- init ---------------------------------------------------- */
         document.getElementById('addItemBtn').addEventListener('click', function () { buildRow(); });
         if (window.PP_EDIT_ITEMS && PP_EDIT_ITEMS.length) {
            PP_EDIT_ITEMS.forEach(function (it) { buildRow(it); });   // edit: prefill saved items
         } else {
            buildRow();          // create: start with one empty row
         }
         recalc();
         syncSummary();
      })();
   </script>
</body>

</html>
