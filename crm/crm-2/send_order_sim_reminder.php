<?php
header('Content-Type: application/json');
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/activity_log.php';
require_once 'Twilio/autoload.php';
use Twilio\Rest\Client;

$order_id = (int) ($_POST['order_id'] ?? 0);
if (!$order_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing order ID']);
    exit;
}

$q = mysqli_query($con, "
    SELECT o.order_id, o.exp_date, o.total,
           m.name, m.lname, m.cell_ph, m.member_id,
           p.membership_name
    FROM orders o
    JOIN members m ON m.member_id = o.mem_id
    JOIN memberships p ON p.membership_id = o.prod_id
    WHERE o.order_id = $order_id
    LIMIT 1");
$order = mysqli_fetch_assoc($q);
if (!$order) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

$raw = preg_replace('/\D/', '', $order['cell_ph']);
if (!preg_match('/^[2-9][0-9]{9}$/', $raw)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid US phone']);
    exit;
}
$phone = '+92' . $raw;

$msg = "Hi {$order['name']}, a quick reminder to please send your {$order['membership_name']} documents to bankstatements@syedpro.com ahead of the due date of 20 Dec. Thank you! Syed Professional Services 718-859-5392.";


$sid = 'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
$token = 'XXXXXXXXXX_AUTH_TOKEN_XXXXXXXXXX';
$twilio = new Client($sid, $token);

try {
    $twilio->messages->create($phone, [
        'from' => '+18669635928',
        'body' => $msg
    ]);
    $employeeId = $_SESSION['user_id'] ?? null;
    $now = date('Y-m-d H:i:s');

    $body = mysqli_real_escape_string($con, $msg);
    $toMail = mysqli_real_escape_string($con, $order['cell_ph']);

    mysqli_query($con, "
    INSERT INTO email_history
    (client_id, type, subject, body, `To_mail`, `From_mail`, sent_by, sent_at)
    VALUES
    ({$order['member_id']}, 'sms', 'Product SMS Reminder',
     '{$body}', '{$toMail}', '+18669635928', {$employeeId}, '{$now}')
");

    $uid = (int) $_SESSION['user_id'];
    logActivity(
        $con,
        $uid,
        'EXPORT',
        'orders',
        $order_id,
        "Sent SMS reminder for {$order['membership_name']} to {$order['name']} {$order['lname']} ({$order['cell_ph']})"

    );

    echo json_encode(['status' => 'ok', 'message' => 'SMS reminder sent successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Twilio: ' . $e->getMessage()]);
}
?>