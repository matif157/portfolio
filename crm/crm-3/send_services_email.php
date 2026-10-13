<?php
// send_product_email.php  (PHPMailer 5.2.x + A4 HTML invoice)

if (!isset($insert_associate) || $insert_associate !== true)
    return;

/* ---------- 1. Grab the client record ---------- */
$client_email_query = mysqli_query($con, "SELECT * FROM members WHERE member_id=" . (int) $member_id);
$client = mysqli_fetch_assoc($client_email_query);
if (!$client || !$client['prm_email'])
    return;

$client_email = $client['prm_email'];

/* ---------- 2. Load PHPMailer ---------- */
require 'PHPMailer/PHPMailerAutoload.php';
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
$mail->addAddress($client_email);
$mail->Subject = 'Invoice – New Product Added';
$mail->isHTML(true);

/* ---------- 3. A4-style invoice HTML ---------- */
$invoiceNo = $invoice_no;
$invoiceDate = date('m/d/Y');
$dueDate = date('m/d/Y', strtotime($expirydate));

/* Build the client block exactly as you supplied */
$clientBlock = '
    <strong>Client ID:</strong> #' . $client['member_id'] . '<br>
    <strong>Name:</strong> ' . trim($client['name'] . ' ' . $client['mname'] . ' ' . $client['lname']) . '<br>
    <strong>DOB:</strong> ' . date('m/d/Y', strtotime($client['dob'])) . '<br>
    <strong>Address:</strong> ' . $client['hstreet'] . '<br>
    <strong>City:</strong> ' . $client['hcity'] . '<br>
    <strong>State:</strong> ' . $client['hstate'] . '<br>
    <strong>Zip:</strong> ' . $client['hzip'] . '<br>
    <strong>County:</strong> ' . $client['hcounty'] . '<br>
    <strong>Phone (cell):</strong> ' . $client['cell_ph'] . '<br>
    <strong>Primary Email:</strong> ' . $client['prm_email'] . '<br>
    <strong>SSN:</strong> ' . $client['ss'] . '<br>
    <strong>Billing Balance:</strong> $' . number_format($client['balance_total'] ?? 0, 2) . '<br>
    <strong>Client Since:</strong> ' . date('m/d/Y', strtotime($client['client_since'])) . '<br>
';

$mail->Body = '
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Invoice</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background: #f8f9fc;
      font-family: "Segoe UI", Roboto, Arial, sans-serif;
      color: #333;
      font-size: 14px;
    }
    .invoice {
      max-width: 800px;
      margin: 30px auto;
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    .invoice-header {
      background: #70d943cf;
      color: #fff;
      padding: 25px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .invoice-header h1 {
      margin: 0;
      font-size: 22px;
      letter-spacing: 1px;
    }
    .invoice-header span {
      display: block;
      font-size: 13px;
      opacity: 0.85;
    }
    .section {
      padding: 25px 30px;
      border-bottom: 1px solid #f0f0f0;
    }
    .section h3 {
      margin: 0 0 12px;
      font-size: 16px;
      color: #4a00e0;
      border-left: 4px solid #8e2de2;
      padding-left: 8px;
    }
    /* ---- Fixed Client Details Table ---- */
    .client-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      margin-top: 10px;
    }
    .client-table td {
      width: 33.33%;
      padding: 8px 12px;
      vertical-align: top;
      border: 1px solid #eee;
      font-size: 13px;
    }
    .client-table strong {
      display: block;
      font-weight: 600;
      margin-bottom: 3px;
      color: #444;
    }
    /* ---- Product Table ---- */
    table.products {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    table.products th, table.products td {
      padding: 12px 10px;
      border: 1px solid #eee;
      text-align: left;
    }
    table.products th {
      background: #fafafa;
      font-size: 13px;
      color: #555;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .total {
      text-align: right;
      padding: 15px 0;
      font-size: 16px;
      font-weight: bold;
      color: #222;
    }
    .invoice-footer {
      background: #fafafa;
      text-align: center;
      padding: 15px;
      font-size: 12px;
      color: #777;
      border-top: 1px solid #eee;
    }
  </style>
</head>
<body>
  <div class="invoice">
    <!-- Header -->
    <div class="invoice-header">
      <div>
        <h1>Invoice #' . $invoiceNo . '</h1>
        <span>Issued: ' . $invoiceDate . ' | Due: ' . $dueDate . '</span>
      </div>
      
    </div>

    <!-- Client Details -->
    <div class="section">
      <h3>Client Details</h3>
      <table class="client-table">
        <tr>
          <td><strong>Client ID</strong> #' . $client['member_id'] . '</td>
          <td><strong>Name</strong> ' . trim($client['name'] . " " . $client['mname'] . " " . $client['lname']) . '</td>
          <td><strong>DOB</strong> ' . date("m/d/Y", strtotime($client["dob"])) . '</td>
        </tr>
        <tr>
          <td><strong>Address</strong> ' . $client['hstreet'] . '</td>
          <td><strong>City</strong> ' . $client['hcity'] . '</td>
          <td><strong>State</strong> ' . $client['hstate'] . '</td>
        </tr>
        <tr>
          <td><strong>Zip</strong> ' . $client['hzip'] . '</td>
          <td><strong>County</strong> ' . $client['hcounty'] . '</td>
          <td><strong>Phone</strong> ' . $client['cell_ph'] . '</td>
        </tr>
        <tr>
          <td><strong>Email</strong> ' . $client['prm_email'] . '</td>
          <td><strong>SSN</strong> ' . $client['ss'] . '</td>
          <td><strong>Balance</strong> $' . number_format($client['balance_total'] ?? 0, 2) . '</td>
        </tr>
        <tr>
          <td><strong>Client Since</strong> ' . date("m/d/Y", strtotime($client["client_since"])) . '</td>
          <td></td>
          <td></td>
        </tr>
      </table>
    </div>

    <!-- Product Details -->
    <div class="section">
      <h3>Product Details</h3>
      <table class="products">
        <thead>
          <tr>
            <th>Item</th>
            <th>Start</th>
            <th>Expiry</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>' . htmlspecialchars($servicename) . '</td>
            <td>' . date("m/d/Y", strtotime($startdate)) . '</td>
            <td>' . date("m/d/Y", strtotime($expirydate)) . '</td>
            <td>$' . number_format((float) $grand_total, 2) . '</td>
          </tr>
        </tbody>
      </table>
      <div class="total">Grand Total: $' . number_format((float) $grand_total, 2) . '</div>
    </div>

    <!-- Footer -->
    <div class="invoice-footer">
      Thank you for choosing <strong>Syed Professional Services</strong>.<br>
      &copy; ' . date("Y") . ' All rights reserved.
    </div>
  </div>
</body>
</html>';


$mail->AltBody = strip_tags(str_replace('<br>', "\n", $clientBlock)) . "\n\n"
    . "Product: {$servicename}\n"
    . "Start: {$startdate}\n"
    . "Expiry: {$expirydate}\n"
    . "Total: \${$grand_total}";

/* ---------- 4. Attach the PDF invoice ---------- */
$pdfPath = __DIR__ . '/' . $Filename;
if (file_exists($pdfPath)) {
    $mail->addAttachment($pdfPath);
}


/* ---------- 5. Send quietly ---------- */
if (!$mail->send()) {
    error_log('SMTP error: ' . $mail->ErrorInfo);
    $msg .= "<div class='alert alert-danger' role='alert'>
                <b>Email sending failed:</b> " . htmlspecialchars($mail->ErrorInfo) . "
             </div>";
} else {
    $msg .= "<div class='alert alert-success' role='alert'>
                <b>Email has been sent successfully to " . htmlspecialchars($client_email) . "</b>
             </div>";
}

?>