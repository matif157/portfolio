<?php
/* delete_client.php */
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/activity_log.php';

/* ---------- 1. check POST fields ---------- */
if (empty($_POST['delete_client']) || empty($_POST['client_id'])) {
    $_SESSION['error'] = 'Delete button not pressed or client_id missing.';
    header('Location: view_clients.php');
    exit;
}

$client_id = (int) $_POST['client_id']; // sanitize early

/* ---------- 2. check client status ---------- */
/* name/business are read up-front: once the row is gone the activity log
   would have nothing but a bare id to show. */
$chk = mysqli_prepare(
    $con,
    'SELECT status, name, mname, lname, bus_name FROM members WHERE member_id = ? LIMIT 1'
);
mysqli_stmt_bind_param($chk, 'i', $client_id);
mysqli_stmt_execute($chk);
$chk_res = mysqli_stmt_get_result($chk);
if (!$chk_res || !($client = mysqli_fetch_assoc($chk_res))) {
    $_SESSION['error'] = 'Client lookup failed: ' . mysqli_error($con);
    header('Location: view_clients.php');
    exit;
}
if ($client['status'] == 1) {
    $_SESSION['error'] = 'Cannot delete an ACTIVE client – deactivate first.';
    header('Location: view_clients.php');
    exit;
}

/* collapse the gap an empty middle name leaves behind */
$client_label = trim(preg_replace(
    '/\s+/',
    ' ',
    $client['name'] . ' ' . $client['mname'] . ' ' . $client['lname']
));
if ($client_label === '' && !empty($client['bus_name'])) {
    $client_label = $client['bus_name'];
}
if ($client_label === '') {
    $client_label = 'Unnamed client';
}
$log_user_id = $_SESSION['user_id'] ?? 0;

/* ---------- 3. delete related data in a transaction ---------- */
mysqli_begin_transaction($con);

try {
    // Tables to delete first (children before parent).
    // bank_statements is FIRST because its FK is ON DELETE RESTRICT — leaving it
    // behind makes the members DELETE fail with error 1451.
    $kids = [
        'bank_statements' => 'member_id',
        'orders' => 'mem_id',
        'comments' => 'member_id',
        'notestab' => 'member_id',
        'spouse' => 'member_id',
        'billing_invoice_items' => 'mem_id',
        'billing_invoice' => 'mem_id',
        'billing_payment_history' => 'mem_id',
        'client_button_settings' => 'member_id',
        'renewals' => 'mem_id'
    ];

    foreach ($kids as $table => $col) {
        $stmt = mysqli_prepare($con, "DELETE FROM `$table` WHERE `$col` = ?");
        if (!$stmt) {
            throw new RuntimeException("Prepare for $table failed: " . mysqli_error($con));
        }
        mysqli_stmt_bind_param($stmt, 'i', $client_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException("DELETE from $table failed: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    }

    // Delete documents (client_id is TEXT, bind as string)
    $stmt = mysqli_prepare($con, "DELETE FROM `documents` WHERE `client_id` = ?");
    if (!$stmt) {
        throw new RuntimeException("Prepare for documents failed: " . mysqli_error($con));
    }
    mysqli_stmt_bind_param($stmt, 's', $client_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException("DELETE from documents failed: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    /* finally delete the client itself */
    $stmt = mysqli_prepare($con, "DELETE FROM members WHERE member_id = ? LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException("Prepare for members failed: " . mysqli_error($con));
    }
    mysqli_stmt_bind_param($stmt, 'i', $client_id);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException("DELETE from members failed: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    mysqli_commit($con);
    $_SESSION['success'] = "Client #$client_id deleted successfully.";

    /* logged after the commit so the entry survives even if logging itself fails */
    logActivity(
        $con,
        $log_user_id,
        'DELETE',
        'Client',
        $client_id,
        "Deleted Client {$client_label} (ID: {$client_id})."
    );
} catch (Throwable $e) {
    mysqli_rollback($con);
    $_SESSION['error'] = "Deletion failed: " . $e->getMessage();

    /* activity_logs.action is an ENUM — 'DELETE' is the only delete-ish value it
       accepts, so the failure is carried in the description. */
    logActivity(
        $con,
        $log_user_id,
        'DELETE',
        'Client',
        $client_id,
        "FAILED to delete client {$client_label} (ID: {$client_id}): " . $e->getMessage()
    );
}

/* ---------- 4. redirect back ---------- */
header('Location: view_clients.php');
exit;
