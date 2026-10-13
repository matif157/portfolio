<?php
/* ----------------------------------------------------
   TWILIO – SMS Invoice Reminder  (SMS ONLY)
   Uses Messaging Service  MG28b94ac8c108df135551ebc5e0ecaebe
   Updated to use billing_invoice_instant table
---------------------------------------------------- */

$invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
if (!$invoiceId) {
    http_response_code(400);
    exit('Missing invoice_id');
}

include 'includes/db.php';
include "includes/auth.php";
include "includes/activity_log.php";

// Updated query to use billing_invoice_instant table
$query = "
    SELECT invoice_id, total_amount, balance, due_date, invoice_date,
    mem_id, name, phone, email
    FROM billing_invoice_instant
    WHERE invoice_id = $invoiceId
    LIMIT 1";
$result = mysqli_query($con, $query);
if (!$result || !mysqli_num_rows($result)) {
    http_response_code(404);
    exit('Invoice not found');
}
$invoice = mysqli_fetch_assoc($result);

// Get invoice items with service details
$itemsQuery = "
    SELECT item_description, item_sub_description, total_price
    FROM billing_invoice_items 
    WHERE inv_id = $invoiceId 
    ORDER BY inv_item_id ASC";
$itemsResult = mysqli_query($con, $itemsQuery);
$invoiceItems = [];
if ($itemsResult && mysqli_num_rows($itemsResult)) {
    while ($item = mysqli_fetch_assoc($itemsResult)) {
        $invoiceItems[] = $item;
    }
}
 
$raw = preg_replace('/\D/', '', $invoice['phone']);

if (preg_match('/^[2-9][0-9]{9}$/', $raw)) {
    $phoneRaw = '1' . $raw;
} elseif (preg_match('/^1[2-9][0-9]{9}$/', $raw)) {
    $phoneRaw = $raw;
} else {
    http_response_code(422);
    exit('Unusable phone number: ' . $invoice['phone']);
}

$twilioTo = '+' . $phoneRaw;

if ($invoice['balance'] == 0) {
    $status = 'Paid';
} elseif ($invoice['balance'] < $invoice['total_amount']) {
    $status = 'Partial Paid';
} elseif (strtotime($invoice['due_date']) < time()) {
    $status = 'Overdue';
} else {
    $status = 'Due';
}

// Build enhanced message with service details
$msg = "Hi {$invoice['name']}, this is a reminder from Syed Professional Services. ";
$msg .= "Invoice #{$invoice['invoice_id']}, issued on " . date('m/d/Y', strtotime($invoice['invoice_date'])) . ", ";
$msg .= "is {$status} as of {$invoice['due_date']}. ";
$msg .= "The outstanding balance is \${$invoice['balance']}, Breakdown of services:\n";

// Add service details
foreach ($invoiceItems as $item) {
    $serviceName = trim($item['item_description']);
    $serviceDesc = trim($item['item_sub_description']);
    $servicePrice = number_format($item['total_price'], 2);

    if (!empty($serviceDesc)) {
        $msg .= "• {$serviceName}: {$serviceDesc}\n";
    } else {
        $msg .= "• {$serviceName} ({$servicePrice})\n";
    }
}
 
$msg .= "Please process the payment at your earliest convenience. If already paid, kindly disregard. ";
$msg .= "For questions, call 718-859-5392. Thank you!";

$sid = 'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
$token = 'XXXXXXXXXX_AUTH_TOKEN_XXXXXXXXXX';

$twilioTo = '+' . $phoneRaw;
$serviceSid = 'MG28b94ac8c108df135551ebc5e0ecaebe';

require_once __DIR__ . '/Twilio/autoload.php';
use Twilio\Rest\Client;

try {
    $twilio = new Client($sid, $token);
    $message = $twilio->messages->create($twilioTo, [
        'messagingServiceSid' => $serviceSid,
        'from' => '+18669635928',
        'body' => $msg
    ]);

    $employeeId = $_SESSION['user_id'] ?? 0;
    $now = date('Y-m-d H:i:s');
    $type = 'SMS';
    $subject = 'SMS Reminder';
    $body = $msg;
    $toMail = $invoice['phone'];
    $fromMail = '+18669635928';

    mysqli_query($con, "
        INSERT INTO email_history
        (client_id, invoice_id, type, subject, body, To_mail, From_mail, sent_by, sent_at)
        VALUES
        ('{$invoice['mem_id']}', '$invoiceId', '$type', '$subject', '$body', '$toMail', '$fromMail', '$employeeId', '$now')
    ");

    echo "Sent On SMS Number: +" . $phoneRaw . "\n";
    $userid = (int) $_SESSION['user_id'];

    $client_full_name = $invoice['name'];
    $client_phone = $invoice['phone'];
    $member_id = $invoice['mem_id'];

    logActivity(
        $con,
        $userid,
        'EXPORT',
        'billing_invoice_instant',
        $invoiceId,
        "Sent instant invoice reminder via SMS to {$client_full_name}, Phone: {$client_phone}) for invoice #{$invoiceId}."
    );
} catch (Exception $e) {
    http_response_code(500);
    echo 'Twilio error: ' . $e->getMessage();
}