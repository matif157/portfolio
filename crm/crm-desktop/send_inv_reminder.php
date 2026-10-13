<?php
// send_reminder.php
include 'includes/db.php';
include "includes/auth.php";
include "includes/activity_log.php";
require_once 'PHPMailer/PHPMailerAutoload.php';

if (!isset($_POST['invoice_id'])) {
    echo "Invalid request.";
    exit;
}

$invoice_id = (int) $_POST['invoice_id'];

// Get invoice details
$invoice_query = mysqli_query($con, "SELECT * FROM billing_invoice WHERE invoice_id = $invoice_id");
$invoice = mysqli_fetch_assoc($invoice_query);

if (!$invoice) {
    echo "Invoice not found.";
    exit;
}

// Get Client details
$member_id = $invoice['mem_id'];
$client_query = mysqli_query($con, "SELECT * FROM members WHERE member_id = $member_id");
$client = mysqli_fetch_assoc($client_query);

if (!$client) {
    echo "Client not found.";
    exit;
}

$email = $client['prm_email'] ?: $client['sec_email'];
if (!$email) {
    echo "No email address found.";
    exit;
}

function resolveInvoicePdfPath($invoice)
{
    $filelink = trim((string) ($invoice['filelink'] ?? ''));
    if ($filelink === '') {
        return '';
    }

    $pdf_path = __DIR__ . '/invoices/' . basename($filelink);
    return file_exists($pdf_path) ? $pdf_path : '';
}

// PHPMailer setup (Common for both Paid and Unpaid)
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

$userid = (int) $_SESSION['user_id'];
$sender = $con->query("SELECT user_name FROM users WHERE user_id = $userid")->fetch_assoc();
$senderName = $sender ? htmlspecialchars($sender['user_name']) : 'Administrator';

$client_full_name = htmlspecialchars(trim($client['name'] . ' ' . $client['lname']));
$is_paid = ((float) $invoice['balance'] <= 0 || (int) $invoice['invoice_status'] === 3);
$pdf_path = resolveInvoicePdfPath($invoice);

// ==========================================
// LOGIC: PAID INVOICE (Confirmation Email)
// ==========================================
if ($is_paid) {
    if ($pdf_path === '') {
        echo "Paid invoice PDF not found.";
        exit;
    }

    $paid_amount = number_format((float) $invoice['paid_amount'], 2); // Formatting amount to 2 decimal places

    // Subject for Paid Invoice
    $mail->Subject = "Payment Received – $" . $paid_amount;

    // HTML Body for Paid Invoice
    $mail->Body = '
    <html>
    <head><title>Payment Confirmation</title></head>
    <body>
  <p>Dear ' . $client_full_name . ',</p>
  <p>We are pleased to confirm that we have received your payment of <strong>$' . $paid_amount . '</strong> for Invoice #<strong>' . $invoice_id . '</strong>.</p>
  <p>Thank you for choosing Syed Professional Services and for your continued trust in our team.</p>
  <p>Please find the paid invoice attached for your records. Should you require any further assistance, we are always here to help.</p><br>
  <p>Best regards,<br>
    <strong>' . $senderName . '</strong><br><br><br>
    Syed Professional Services<br>
    A Team of Professionals<br>
    1012 Coney Island Avenue<br>
    Brooklyn NY, 11230<br>
    Tel: (718) 859-5392<br> 
    Fax: (718) 859-2237<br>
    WhatsApp: (347) 873-4373 (Msg. Only)
  </p>
</body>
    </html>';

    // Plain Text Body for Paid Invoice
    $mail->AltBody = "Dear {$client['name']},\n\nThank you very much for the payment of $" . $paid_amount . ". We sincerely appreciate your timely payment.\n\nWarm regards,\nMuhammad Atif";

    // Attach PDF for paid confirmation emails too
    $mail->addAttachment($pdf_path);

    $email_type = 'invoice_reminder';

}
// ==========================================
// LOGIC: UNPAID INVOICE (Reminder Email)
// ==========================================
else {
    if ($pdf_path === '') {
        echo "Invoice PDF not found.";
        exit;
    }

    $total_amount = number_format((float) $invoice['balance'], 2);

    // Subject for Unpaid Invoice
    $mail->Subject = 'Payment Reminder – Invoice #' . $invoice_id;

    // HTML Body for Reminder
    $mail->Body = '
    <html>
    <head><title>Invoice Reminder</title></head>
    <body>
      <p>Dear ' . $client_full_name . ',</p>
      <p>I hope you are doing well.</p>
      <p>This is a friendly reminder that payment for Invoice <strong>#' . $invoice_id . '</strong> issued by Syed Professional Services is due. The total amount payable for this invoice is <strong>$' . $total_amount . '</strong>.</p>
      <p>Payment can be made via:</p>
      <ul>
        <li><strong>Zelle:</strong> sps@syedpro.com</li>
        <li>Check</li>
        <li>Cash</li>
        <li>Card</li>
      </ul>
      <p>If you have already made the payment, please reply with the receipt or confirmation.</p>
      <p>Thank you for your business.</p>
      <br>
      <p>Best regards,<br>
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

    // Plain Text Body for Reminder
    $mail->AltBody = "Dear {$client['name']},\n\n"
        . "I hope you are doing well.\n\n"
        . "This is a friendly reminder that payment for Invoice #{$invoice_id} issued by Syed Professional Services is due. "
        . "The total amount payable for this invoice is \${$total_amount}.\n\n"
        . "Payment can be made via:\n"
        . "- Zelle: sps@syedpro.com\n"
        . "- Check\n"
        . "- Cash\n"
        . "- Card\n\n"
        . "If you have already made the payment, please reply with the receipt or confirmation.\n\n"
        . "Thank you for your business.\n\n"
        . "Best regards,\n{$senderName}";

    // Attach PDF only for unpaid invoice
    $mail->addAttachment($pdf_path);

    $email_type = 'invoice_reminder';
}

// ==========================================
// SEND EMAIL
// ==========================================
if (!$mail->send()) {
    echo "Failed to send email: " . $mail->ErrorInfo;
} else {

    // Success Message based on type
    if ($is_paid) {
        echo "Payment confirmation sent to {$email}";
    } else {
        echo "Reminder sent to {$email}";
    }

    // Log to Database
    $userid = (int) $_SESSION['user_id'];
    $subject_esc = mysqli_real_escape_string($con, $mail->Subject);
    $body_esc = mysqli_real_escape_string($con, $mail->AltBody);
    $To_mail = mysqli_real_escape_string($con, $email);
    $From_mail = mysqli_real_escape_string($con, 'invoice@syedpro.com');
 
    mysqli_query($con, "
        INSERT INTO email_history
        (client_id, invoice_id, subject, body, sent_by, type, To_mail, From_mail)
        VALUES
        ('$member_id', '$invoice_id', '$subject_esc', '$body_esc', '$userid',
        '$email_type', '$To_mail', '$From_mail')
    ");

    // Log Activity
    $client_full_name_log = trim($client['name'] . ' ' . $client['lname']);

    if ($is_paid) {
        logActivity(
            $con,
            $userid,
            'EXPORT',
            'billing_invoice',
            $invoice_id,
            "Sent payment confirmation email to {$client_full_name_log} (Member ID: {$member_id}, Email: {$email}) for invoice #{$invoice_id}."
        );
    } else {
        logActivity(
            $con,
            $userid,
            'EXPORT',
            'billing_invoice',
            $invoice_id,
            "Sent invoice reminder email to {$client_full_name_log} (Member ID: {$member_id}, Email: {$email}) for invoice #{$invoice_id}."
        );
    }
}
?>
