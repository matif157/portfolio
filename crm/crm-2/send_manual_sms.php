<?php
require_once __DIR__ . '/Twilio/autoload.php';
use Twilio\Rest\Client;
include 'includes/db.php';
include 'includes/auth.php';
include 'includes/activity_log.php';

$clientId = (int) ($_POST['client_id'] ?? 0);
if (!$clientId) {
    http_response_code(400);
    exit('Missing client');
}

$m = mysqli_fetch_assoc(mysqli_query(
    $con,
    "SELECT name, lname, cell_ph, member_id FROM members WHERE member_id=$clientId LIMIT 1"
));
if (!$m || empty($m['cell_ph'])) {
    http_response_code(422);
    exit('No phone');
}

$raw = preg_replace('/\D/', '', $m['cell_ph']);
if (preg_match('/^[2-9]\d{9}$/', $raw))
    $phoneRaw = '92' . $raw;
elseif (preg_match('/^1[2-9]\d{9}$/', $raw))
    $phoneRaw = $raw;
else {
    http_response_code(422);
    exit('Bad phone');
}

$body = trim($_POST['sms_body'] ?? '');
if ($body === '') {
    http_response_code(400);
    exit('Empty message');
}

try {
    $twilio = new Client('ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX', 'XXXXXXXXXX_AUTH_TOKEN_XXXXXXXXXX');
    $twilio->messages->create('+' . $phoneRaw, [
        'messagingServiceSid' => 'MG28b94ac8c108df135551ebc5e0ecaebe',
        'body' => $body
    ]);
} catch (Exception $e) {
    http_response_code(500);
    exit('Twilio error: ' . $e->getMessage());
}

// same history / activity you already have
$empId = (int) ($_SESSION['user_id'] ?? 0);
$now = date('Y-m-d H:i:s');
mysqli_query($con, "
    INSERT INTO email_history
    (client_id,invoice_id,type,subject,body,To_mail,From_mail,sent_by,sent_at)
    VALUES
    ('{$m['member_id']}',0,'SMS','Manual SMS','$body','{$m['cell_ph']}','+18669635928',$empId,'$now')");

logActivity(
    $con,
    $empId,
    'EXPORT',
    'billing_invoice',
    0,
    "Sent manual SMS to {$m['name']} {$m['lname']} (Member ID: {$m['member_id']}, Phone: {$m['cell_ph']})."
);
 
echo 'OK';