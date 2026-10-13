<?php
    session_start();
    // include "includes/db.php";
    if(!isset($_SESSION["username"])) {
        header("Location: login.php");
        exit();
    }

// Auto-renew expired services on first page-load of the day (file-locked).
if (isset($con)) {
    require_once __DIR__ . '/auto_renew_services.php';
    @run_service_auto_renewals($con);
}

// Automation scheduler/poller — throttled to once per minute (file-locked).
if (isset($con)) {
    require_once __DIR__ . '/automation.php';
    @aut_poll_once($con);
}

$usertype=$_SESSION['user_type'];

//echo "SELECT * FROM dashboard_access where `user_type`='$usertype'";

/* --- NEW ROLES & PERMISSIONS LAYER -------------------------------------
   Resolve the user's permissions from the unified Roles system and map them
   back onto the legacy $acc_* variables so the existing pages keep working
   unchanged. If the Roles tables cannot be used (e.g. very first migration is
   still in progress), we transparently fall back to the legacy behaviour. */
require_once __DIR__ . '/roles.php';

if (isset($con) && $con instanceof mysqli && isset($_SESSION['user_id'])) {
    $rolesShim = roles_shim($con, (int) $_SESSION['user_id'], $usertype);
    extract($rolesShim);
    if (!isset($client_active)) {
        $client_active = $rolesShim['acc_client_active'] ?? 'No';
    }
} else {
    /* Legacy fallback – should not normally run, kept for safety. */
    if ($usertype=='1') {
            $acc_add_client="Yes"; $acc_view_client="Yes"; $acc_detail_client="Yes";
            $acc_billing_client="Yes"; $acc_delete_client="Yes"; $acc_view_products="Yes";
            $acc_add_edit_products="Yes"; $acc_delete_products="Yes"; $acc_view_staff="Yes";
            $acc_add_edit_staff="Yes"; $acc_delete_staff="Yes"; $acc_view_entity="Yes";
            $acc_add_edit_entity="Yes"; $acc_delete_entity="Yes"; $acc_view_product="Yes";
            $acc_add_edit_product="Yes"; $acc_delete_product="Yes"; $acc_invoice_and_billing="Yes";
            $acc_estimates_and_qutations="Yes"; $acc_bank_card_area="Yes"; $client_active="Yes";
    }
    else {
        $acc_query = mysqli_query($con,"SELECT * FROM dashboard_access where `user_type`='$usertype'");
        while($acc_fetch = mysqli_fetch_array($acc_query)){
            $acc_add_client=$acc_fetch['add_new_client'];
            $acc_view_client=$acc_fetch['view_clients_list'];
            $acc_detail_client=$acc_fetch['client_detail_area'];
            $acc_billing_client=$acc_fetch['client_billing_area'];
            $acc_delete_client=$acc_fetch['delete_client'];
            $acc_view_products=$acc_fetch['view_products'];
            $acc_add_edit_products=$acc_fetch['add_edit_products'];
            $acc_delete_products=$acc_fetch['delete_products'];
            $acc_view_staff=$acc_fetch['view_staff'];
            $acc_add_edit_staff=$acc_fetch['add_edit_staff'];
            $acc_delete_staff=$acc_fetch['delete_staff'];
            $acc_view_entity=$acc_fetch['view_entity_form'];
            $acc_add_edit_entity=$acc_fetch['add_edit_form'];
            $acc_delete_entity=$acc_fetch['delete_form'];
            $acc_view_product=$acc_fetch['view_product_cycle'];
            $acc_add_edit_product=$acc_fetch['add_edit_cycle'];
            $acc_delete_product=$acc_fetch['delete_cycle'];
            $acc_invoice_and_billing=$acc_fetch['invoice_and_billing'];
            $acc_estimates_and_qutations=$acc_fetch['estimates_and_qutations'];
            $acc_bank_card_area=$acc_fetch['bank_card_area'];
            $client_active = $acc_fetch['client_active'];
         }
    }
}

//echo $acc_add_client." here is ";


?>
