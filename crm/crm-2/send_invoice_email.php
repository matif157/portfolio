
<?php
$to = $client_email;
$subject = "Invoice From Syed Professional Services";
$message = "Body of the email";

// File to be attached
$file_path = $Filename;
$file_name = $PDFFileN;
$file_type = "application/pdf";

// Read the file content
$file_content = file_get_contents($file_path);
$file_content = chunk_split(base64_encode($file_content));

// Headers for attachment
$headers = "From: demo@americaslittleleaders.com\r\n";
$headers .= "Reply-To: demo@americaslittleleaders.com\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"boundary\"\r\n";
 
// Message body
$body = "--boundary\r\n";
$body .= "Content-Type: text/plain; charset=\"utf-8\"\r\n";
$body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$body .= $message . "\r\n\r\n";

// Attachment
$body .= "--boundary\r\n";
$body .= "Content-Type: $file_type; name=\"$file_name\"\r\n";
$body .= "Content-Transfer-Encoding: base64\r\n";
$body .= "Content-Disposition: attachment; filename=\"$file_name\"\r\n\r\n";
$body .= $file_content . "\r\n\r\n";

// End of message
$body .= "--boundary--";

// Send the email
$mail_success = mail($to, $subject, $body, $headers);

// Check if the email was sent successfully
if ($mail_success) {
    echo "Email sent successfully.";
} else {
    echo "Error sending email.";
}
?>
