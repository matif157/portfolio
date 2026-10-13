<?php
// send_reminder.php
include 'includes/db.php';
include "includes/auth.php";
include 'includes/activity_log.php';
require_once 'PHPMailer/PHPMailerAutoload.php';

if (!isset($_POST['invoice_id'])) {
    echo "Invalid request.";
    exit;
}
   
$invoice_id = (int) $_POST['invoice_id'];

// Get invoice with prepared statement to prevent SQL injection
$stmt = mysqli_prepare($con, "SELECT * FROM billing_invoice_instant WHERE invoice_id = ?");
mysqli_stmt_bind_param($stmt, "i", $invoice_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$invoice = mysqli_fetch_assoc($result);

if (!$invoice) {
    echo "Invoice not found.";
    exit;
}

$email = $invoice['email'];
$member_id = $invoice['mem_id'];

// Validate email address
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email address.";
    exit;
}

$pdf_path = __DIR__ . '/instant_invoices/' . $invoice['filelink'];
if (!file_exists($pdf_path)) {
    echo "Invoice PDF not found.";
    exit;
}

// Paid invoices get a payment confirmation; unpaid ones get a reminder.
$is_paid = ((float) $invoice['balance'] <= 0 || (int) $invoice['invoice_status'] === 3);

// PHPMailer setup (common for both paid and unpaid)
$mail = new PHPMailer;
$mail->isSMTP();
$mail->Host = 'smtp.office365.com';
$mail->SMTPAuth = true;
$mail->Username = 'invoice@syedpro.com';
$mail->Password = 'bgtqpxznmghqwkrc';
$mail->SMTPSecure = 'tls';
$mail->Port = 587;
$mail->CharSet = 'UTF-8';

$mail->setFrom('invoice@syedpro.com', 'Syed Professional Services');
$mail->addAddress($email);
$mail->isHTML(true);

/* ---- sender details ---- */
$userid = (int) $_SESSION['user_id'];
$sender = $con->query("SELECT user_name FROM users WHERE user_id = $userid")->fetch_assoc();
$senderName = $sender ? htmlspecialchars($sender['user_name']) : 'Administrator';

$client_full_name = htmlspecialchars(trim($invoice['name']));

// ==========================================
// LOGIC: PAID INVOICE (Confirmation Email)
// ==========================================
if ($is_paid) {
    $paid_amount = number_format((float) $invoice['paid_amount'], 2);

    $mail->Subject = "Payment Received – $" . $paid_amount;

    $mail->Body = '
<html>
<head><title>Payment Confirmation</title></head>
<body>
  <p>Dear ' . $client_full_name . ',</p>
  <p>We are pleased to confirm that we have received your payment of <strong>$' . $paid_amount . '</strong> for instant Invoice #<strong>' . $invoice_id . '</strong>.</p>
  <p>Thank you for choosing Syed Professional Services and for your continued trust in our team.</p>
  <p>Please find the paid invoice attached for your records. Should you require any further assistance, we are always here to help.</p>

  <br>
  <p>Best regards,<br>
  <strong>' . $senderName . '</strong><br><br>
  Syed Professional Services<br>
  A Team of Professionals<br>
  1012 Coney Island Avenue<br>
  Brooklyn NY, 11230<br>
  Tel: (718) 859-5392<br>
  Fax: (718) 859-2237<br>
  WhatsApp: (347) 873-4373 (Msg. Only)</p>
</body>
</html>';

    $mail->AltBody = "Dear " . trim($invoice['name']) . ",\n\nWe are pleased to confirm that we have received your payment of \${$paid_amount} for instant invoice #{$invoice_id}.\nPlease find the paid invoice attached for your records.\n\nThank you for choosing Syed Professional Services.";
}
// ==========================================
// LOGIC: UNPAID INVOICE (Reminder Email)
// ==========================================
else {
    $mail->Subject = 'Reminder: instant Invoice #' . $invoice_id;

    $mail->Body = '
<html>
<head><title>Invoice Reminder</title></head>
<body>
  <p>Dear ' . $client_full_name . ',</p>
  <p>This is a friendly reminder that your invoice <strong>#' . $invoice_id . '</strong>.</p>
  <p>Please find the attached invoice PDF.</p>
  <p>If you have already paid, kindly disregard this message.</p>

  <br>
  <p>Thank you,<br>
  <strong>' . $senderName . '</strong><br>
  Account Receivable<br><br>
  Syed Professional Services<br>
  A Team of Professionals<br>
  1012 Coney Island Avenue<br>
  Brooklyn NY, 11230<br>
  Tel: (718) 859-5392<br>
  Fax: (718) 859-2237<br>
  WhatsApp: (347) 873-4373 (Msg. Only)</p>
</body>
</html>';

    $mail->AltBody = "Dear " . trim($invoice['name']) . ",\n\nThis is a reminder that invoice #{$invoice_id} is due.\nPlease check the attached PDF.\n\nThank you.";
}

// Attach PDF (both paid confirmation and reminder include it)
$mail->addAttachment($pdf_path);

// Send
if (!$mail->send()) {
    echo $is_paid ? "Failed to send payment confirmation: " . $mail->ErrorInfo
                  : "Failed to send reminder: " . $mail->ErrorInfo;
} else {
    echo $is_paid ? "Payment confirmation sent to {$email}" : "Reminder sent to {$email}";

    $userid = (int) $_SESSION['user_id'];
    // No need to escape values when using prepared statements
    $subject = $mail->Subject;
    $body = $mail->AltBody;
    $To_mail = $email;
    $From_mail = $mail->From;
    $type = 'invoice_reminder'; // Using shorter value to avoid truncation

    // Using prepared statement for email history
    $stmt = mysqli_prepare($con, "
    INSERT INTO email_history
      (client_id, invoice_id, subject, body, sent_by, type, To_mail, From_mail)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt, "iississs", $member_id, $invoice_id, $subject, $body, $userid, $type, $To_mail, $From_mail);
    mysqli_stmt_execute($stmt);

    $client_full_name_log = trim($invoice['name']);
    $email_used = $email;

    if ($is_paid) {
        logActivity(
            $con,
            $userid,
            'EXPORT',
            'billing_invoice_instant',
            $invoice_id,
            "Sent payment confirmation email to {$client_full_name_log}, Email: {$email_used} for instant invoice #{$invoice_id}."
        );
    } else {
        logActivity(
            $con,
            $userid,
            'EXPORT',
            'billing_invoice_instant',
            $invoice_id,
            "Sent invoice reminder email to {$client_full_name_log}, Email: {$email_used} for instant invoice #{$invoice_id}."
        );
    }
}
?>