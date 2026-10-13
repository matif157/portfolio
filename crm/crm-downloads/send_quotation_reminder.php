<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/activity_log.php';
require_once 'PHPMailer/PHPMailerAutoload.php';

if (!isset($_POST['quotation_id'])) {
    echo 'Invalid request.';
    exit;
}

$qid = (int) $_POST['quotation_id'];

/* ---- fetch quotation ---- */
$q = $con->prepare('SELECT * FROM quotations WHERE quotation_id = ?');
$q->bind_param('i', $qid);
$q->execute();
$quot = $q->get_result()->fetch_assoc();
if (!$quot) {
    echo 'Quotation not found.';
    exit;
}

/* ---- Determine client source and fetch details ---- */
$mid = (int) $quot['member_id'];
$isMember = false;
$client = null;

// Check if member exists in members table
if ($mid > 0) {
    $client = $con->query("SELECT * FROM members WHERE member_id = $mid")->fetch_assoc();
    if ($client) {
        $isMember = true;
    }
}  
 
// If not a member or member not found, use quotation table data
if (!$isMember) {
    $client = [
        'member_id' => 0, // Non-member indicator
        'name' => $quot['name'],
        'lname' => '', // Quotations table doesn't have separate last name
        'prm_email' => $quot['email'],
        'sec_email' => ''
    ];
    // Try to split name into first and last name (optional enhancement)
    $nameParts = explode(' ', trim($quot['name']), 2);
    if (count($nameParts) > 1) {
        $client['name'] = $nameParts[0];
        $client['lname'] = $nameParts[1];
    }
}

// Determine email address
if ($isMember) {
    $email = $client['prm_email'] ?: $client['sec_email'];
} else {
    $email = $client['prm_email']; // From quotations table
}

if (!$email) {
    echo 'No email address.';
    exit;
}

/* ---- PDF path ---- */
$pdfPath = __DIR__ . '/quotations/' . $quot['file_name'];
if (!file_exists($pdfPath)) {
    echo 'Quotation PDF missing.';
    exit;
}

/* ---- extract clean service names from HTML ---- */
$serviceNames = [];
if (preg_match_all('/<td>([^<]+)(?:<br>|<\/td>)/', $quot['services'], $matches)) {
    // Only take odd-numbered matches (service names), skip even-numbered matches (prices)
    foreach ($matches[1] as $index => $service) {
        if ($index % 2 == 0) { // Only take service names, skip prices
            $service = trim(strip_tags($service));
            if (!empty($service)) {
                $serviceNames[] = $service;
            }
        }
    }
}
$serviceList = !empty($serviceNames) ? implode(' and ', $serviceNames) : 'requested services';

/* ---- PHPMailer ---- */
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
$mail->Subject = 'Quotation #' . $quot['quotation_sr'];
$mail->isHTML(true);

/* ---- sender details ---- */ 
$userid = (int) $_SESSION['user_id'];
$sender = $con->query("SELECT user_name FROM users WHERE user_id = $userid")->fetch_assoc();
$senderName = $sender ? htmlspecialchars($sender['user_name']) : 'Administrator';
 
$mail->Body = '
<html>
<head><title>Quotation</title></head>
<body>
<p>Dear ' . htmlspecialchars(trim($client['name'] . ' ' . $client['lname'])) . ',</p>
<p>We hope this message finds you well.</p>
<p>Please find attached the quotation <strong>#' . $quot['quotation_sr'] . '</strong> for <strong>' . htmlspecialchars($serviceList) . '</strong>, prepared as per your requirements.</p>
<p>We kindly request you to review the same and let us know if you require any clarification, revision, or additional information. We will be glad to assist you.</p>
<p>We look forward to your feedback and the opportunity to work with you.</p>
<br>
<p>Yours sincerely,<br>
<strong>' . $senderName . '</strong><br>
</body>
</html>';

$mail->AltBody = "Dear {$client['name']},\n\nWe hope this message finds you well.\n\nPlease find attached the quotation #{$quot['quotation_sr']} for {$serviceList}, prepared as per your requirements.\n\nWe kindly request you to review the same and let us know if you require any clarification, revision, or additional information. We will be glad to assist you.\n\nWe look forward to your feedback and the opportunity to work with you.\n\nYours sincerely,\n{$senderName}\nAccount Receivable";
$mail->addAttachment($pdfPath);

/* ---- send ---- */
if (!$mail->send()) {
    echo 'Failed to send email: ' . $mail->ErrorInfo;
} else {
    echo 'Quotation sent to ' . $email;

    /* ---- history / activity ---- */
    $userid = (int) $_SESSION['user_id'];
    $subj = mysqli_real_escape_string($con, $mail->Subject);
    $bod = mysqli_real_escape_string($con, $mail->AltBody);
    $to = mysqli_real_escape_string($con, $email);
    $from = mysqli_real_escape_string($con, $mail->From);
    
    // Use 0 for client_id if not a member, or actual member_id if member
    $clientIdForDb = $isMember ? $mid : 0;

    $con->query("
    INSERT INTO email_history
        (client_id, invoice_id, subject, body, sent_by, type, To_mail, From_mail)
    VALUES
        ('$clientIdForDb', $qid, '$subj', '$bod', '$userid',
         'general', '$to', '$from')
");

    $fullName = trim($client['name'] . ' ' . $client['lname']);
    $memberInfo = $isMember ? " (Member ID: {$mid})" : " (Non-member)";
    logActivity(
        $con,
        $userid,
        'EXPORT',
        'quotations',
        $qid,
        "Sent quotation email to {$fullName}{$memberInfo}, Email: {$email} for quotation SR#{$quot['quotation_sr']}."
    );
}
?>