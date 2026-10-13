<?php


require_once __DIR__ . '/loadEnv.php';
require_once __DIR__ . '/dompdf/autoload.inc.php';
require_once __DIR__ . '/includes/activity_log.php';

use Dompdf\Dompdf;

define('STRIPE_SECRET_KEY', $_ENV['STRIPE_SECRET_KEY'] ?? '');
define('STRIPE_PUBLISHABLE_KEY', $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '');

function spEsc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function spGetInitials($name)
{
    $words = preg_split('/\s+/', trim((string) $name));
    $initials = '';
    foreach ($words as $word) {
        if ($word !== '') {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return $initials;
}

function spParseMoney($value)
{
    $value = str_replace(',', '', (string) $value);
    $value = preg_replace('/[^0-9.\-]/', '', $value);
    return is_numeric($value) ? (float) $value : 0.0;
}

function spNormalizeDate($value, $fallback = '')
{
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $fallback !== '' ? $fallback : $value;
    }

    return date('m/d/Y', $timestamp);
}

function spFormatPaymentLabel($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = str_replace(['_', '-'], ' ', strtolower($value));
    return ucwords($value);
}

function spBuildStripePaymentDescription($paymentType, $cardBrand = '')
{
    $paymentType = spFormatPaymentLabel($paymentType);
    $cardBrand = spFormatPaymentLabel($cardBrand);

    if ($paymentType !== '' && $cardBrand !== '') {
        return $paymentType . ' - ' . $cardBrand;
    }

    if ($paymentType !== '') {
        return $paymentType;
    }

    if ($cardBrand !== '') {
        return $cardBrand;
    }

    return 'Paid via Stripe';
}

function spMapStripePaymentTypeId($paymentType)
{
    $paymentType = strtolower(trim((string) $paymentType));

    return match ($paymentType) {
        'card' => 1,
        'bank_account', 'us_bank_account', 'ach', 'ach_debit' => 4,
        default => 1,
    };
}

function spBaseUrl()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseDir = str_replace('\\', '/', dirname($scriptName));
    $baseDir = rtrim($baseDir, '/');
    if ($baseDir === '' || $baseDir === '.') {
        $baseDir = '';
    }

    return $scheme . '://' . $host . $baseDir;
}

function spGetStatusMeta($statusId)
{
    $baseUrl = spBaseUrl();
    $statusId = (int) $statusId;

    if ($statusId === 3) {
        return [
            'label' => 'Paid',
            'image' => $baseUrl . '/storage/uploads/logo/paid.png'
        ];
    }

    if ($statusId === 2) {
        return [
            'label' => 'Partial Paid',
            'image' => $baseUrl . '/storage/uploads/logo/partial_payment.jpg'
        ];
    }

    return [
        'label' => 'Unpaid',
        'image' => $baseUrl . '/storage/uploads/logo/unpaid.jpg'
    ];
}

function spResolveContactData($con, $memberId)
{
    $memberId = (int) $memberId;
    $query = mysqli_query($con, "SELECT * FROM members WHERE member_id = {$memberId} LIMIT 1");
    if (!$query || mysqli_num_rows($query) === 0) {
        return [
            'name' => 'Unknown Client',
            'address' => 'Address not available',
            'address2' => '',
            'phone' => 'Phone not available',
            'email' => 'Email not available'
        ];
    }

    $row = mysqli_fetch_assoc($query);
    $businessName = trim((string) ($row['bus_name'] ?? ''));
    $dba = trim((string) ($row['dba'] ?? ''));
    $name = $businessName !== '' ? $businessName : ($dba !== '' ? $dba : trim((string) ($row['name'] ?? '') . ' ' . ($row['lname'] ?? '')));
    $address = trim((string) ($row['address'] ?? ''));
    if ($address === '') {
        $address = trim((string) ($row['hstreet'] ?? ''));
    }
    $address2 = trim((string) ($row['hcity'] ?? '') . ', ' . ($row['hstate'] ?? '') . ' ' . ($row['hzip'] ?? ''));
    $phone = trim((string) ($row['cell_ph'] ?? ''));
    if ($phone === '') {
        $phone = trim((string) ($row['phone'] ?? ''));
    }
    $email = trim((string) ($row['prm_email'] ?? ''));
    if ($email === '') {
        $email = trim((string) ($row['sec_email'] ?? ''));
    }

    return [
        'name' => $name !== '' ? $name : 'Unknown Client',
        'address' => $address !== '' ? $address : 'Address not available',
        'address2' => $address2,
        'phone' => $phone !== '' ? $phone : 'Phone not available',
        'email' => $email !== '' ? $email : 'Email not available'
    ];
}

function spFetchInvoiceItems($con, $invoiceId)
{
    $invoiceId = (int) $invoiceId;
    $items = [];

    $query = mysqli_query(
        $con,
        "SELECT item_description, item_sub_description, unit_price, quantity, total_price
         FROM billing_invoice_items
         WHERE inv_id = {$invoiceId}
         ORDER BY inv_item_id ASC"
    );

    while ($query && ($row = mysqli_fetch_assoc($query))) {
        $items[] = [
            'service' => trim((string) ($row['item_description'] ?? '')),
            'description' => trim((string) ($row['item_sub_description'] ?? '')),
            'unit_price' => spParseMoney($row['unit_price'] ?? 0),
            'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
            'total_price' => spParseMoney($row['total_price'] ?? 0),
        ];
    }

    return $items;
}

function spEnsureItemsFallback($invoiceRow, $items)
{
    if (!empty($items)) {
        return $items;
    }

    $amount = spParseMoney($invoiceRow['total_amount'] ?? 0);
    if ($amount <= 0) {
        $amount = spParseMoney($invoiceRow['balance'] ?? 0);
    }

    return [[
        'service' => ' ',
        'description' => ' ',
        'unit_price' => $amount,
        'quantity' => 1,
        'total_price' => $amount
    ]];
}

function spGetServiceProviderLine($con, $invoiceRow)
{
    $providerId = (int) ($invoiceRow['service_provider_id'] ?? 0);
    if ($providerId <= 0) {
        return '';
    }

    $query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = {$providerId} LIMIT 1");
    if (!$query || mysqli_num_rows($query) === 0) {
        return '<tr class="details"><td><b>Service Provider:</b> #' . $providerId . '</td><td></td></tr>';
    }

    $row = mysqli_fetch_assoc($query);
    $name = trim((string) ($row['user_name'] ?? ''));
    $label = spGetInitials($name);
    if ($label === '') {
        $label = $name !== '' ? $name : ('#' . $providerId);
    }

    return '<tr class="details"><td><b>Service Provider:</b> ' . spEsc($label) . ' (' . $providerId . ')</td><td></td></tr>';
}

function spBuildInvoiceHtml($con, $invoiceRow, $memberRow, $items, $payment_methods_str, $user_initials)
{
    $bus_name = trim((string) ($memberRow['bus_name'] ?? ''));
    $dba = trim((string) ($memberRow['dba'] ?? ''));
    if ($bus_name !== '') {
        $name = $bus_name;
        $address = trim((string) ($memberRow['address'] ?? ''));
        $address1 = trim((string) ($memberRow['hcity'] ?? '') . ", " . ($memberRow['hstate'] ?? '') . ", " . ($memberRow['hzip'] ?? ''));
        $email = trim((string) ($memberRow['prm_email'] ?? ''));
    } elseif ($dba !== '') {
        $name = $dba;
        $address = trim((string) ($memberRow['address'] ?? ''));
        $address1 = trim((string) ($memberRow['hcity'] ?? '') . ", " . ($memberRow['hstate'] ?? '') . ", " . ($memberRow['hzip'] ?? ''));
        $email = trim((string) ($memberRow['prm_email'] ?? ''));
    } else {
        $name = trim((string) ($memberRow['name'] ?? '') . " " . ($memberRow['lname'] ?? ''));
        $email = trim((string) ($memberRow['sec_email'] ?? ''));
        $address = trim((string) ($memberRow['hstreet'] ?? ''));
        $address1 = trim((string) ($memberRow['hcity'] ?? '') . ", " . ($memberRow['hstate'] ?? '') . ", " . ($memberRow['hzip'] ?? ''));
    }
    $phone = trim((string) ($memberRow['cell_ph'] ?? ''));

    $service_provider_id = (int) ($invoiceRow['service_provider_id'] ?? 0);
    $service_provider_name = '';
    if ($service_provider_id > 0) {
        $sp_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . mysqli_real_escape_string($con, (string) $service_provider_id) . "'");
        if ($sp_result = mysqli_fetch_assoc($sp_query)) {
            $service_provider_name = $sp_result['user_name'];
        }
    }
    $service_provider_name = spGetInitials($service_provider_name);

    $itemsHtml = '';
    foreach ($items as $item) {
        $itemsHtml .= '<tr class="item">
                   <td>
                       <b>' . spEsc($item['service']) . '</b>
                       <p style="font-size:12px;margin:0px">' . spEsc($item['description']) . '</p>
                   </td>
                   <td>$' . number_format($item['unit_price'], 2) . '</td>
                </tr>';
    }
 
    $invoice_status = (int) ($invoiceRow['invoice_status'] ?? 1);
    if ($invoice_status === 3) {
        $status_text = 'Paid';
        $filepath = '<img src="http://192.168.15.29/storage/uploads/logo/paid.png" alt="Company logo" style="width: 30%;margin-top:12px; max-width: 300px" />';
    } elseif ($invoice_status === 2) {
        $status_text = 'Partial Paid';
        $filepath = '<img src="http://192.168.15.29/storage/uploads/logo/partial_payment.jpg" alt="Company logo"  style="width: 30%;margin-top:12px; max-width: 300px" />';
    } else {
        $status_text = 'UnPaid';
        $filepath = '<img src="http://192.168.15.29/storage/uploads/logo/unpaid.jpg" alt="Company logo" style="width: 30%;margin-top:12px; max-width: 300px" />';
    }

    $curDateMySQL = date('m/d/Y');
    $duedate = spNormalizeDate($invoiceRow['due_date'] ?? '', '');

    $paid_date = trim((string) ($invoiceRow['paid_date'] ?? ''));
    if ($paid_date === '' || $paid_date === '0000-00-00') {
        $paid_date = date('Y-m-d');
    }

    $totalamount = number_format(spParseMoney($invoiceRow['total_amount'] ?? 0), 2);
    $paidamount = number_format(spParseMoney($invoiceRow['paid_amount'] ?? 0), 2);
    $balance = number_format(spParseMoney($invoiceRow['balance'] ?? 0), 2);

    $discount = number_format(spParseMoney($invoiceRow['total_amount'] ?? 0) - (spParseMoney($invoiceRow['paid_amount'] ?? 0) + spParseMoney($invoiceRow['balance'] ?? 0)), 2);
    $discountRow = '';
    if ((float) $discount > 0) {
        $discountRow = '<tr class="total">
                <td></td>
                <td style="font-size:20px;font-weight:bold;">Discount: $' . $discount . '</td>
            </tr>';
    }

    $description = trim((string) ($invoiceRow['payment_details'] ?? ''));
    if (strpos($description, '0 |') === 0 || strpos($description, '0|') === 0) {
        $description = trim(substr($description, 2));
        if (strpos($description, '|') === 0) {
            $description = trim(substr($description, 1));
        }
    }
    if ($description === '0') {
        $description = '';
    }

    $qr_image_path = 'http://192.168.15.29/storage/uploads/logo/qr.png';

    return '
        <style>
        .invoice-box {
            position:relative;
            height:99%;
            width:100%;
            margin: auto;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 16px;
            line-height: 24px;
            color: #555;
        }
       .invoice-box .footer-note {
  position: absolute;
  bottom: -20px;
  left: 50%;
  transform: translateX(-50%);
  text-align: center;
  font-size: 14px;
}

.invoice-box .footer-note p {
  margin: 0;
  font-size: 14px;
  white-space: nowrap; 
}

.invoice-box .footer-note h4 {
  margin-top: 5px; 
  font-size: 16px;
}
        .invoice-box table {
            width: 100%;
            line-height: inherit;
            text-align: left;
            border-collapse: collapse;
        }
        .invoice-box table td {
            padding: 5px;
            vertical-align: top;
        }
        .invoice-box table tr td:nth-child(2) {
            text-align: right;
        }
        .invoice-box table tr.heading td {
            background: #8b742a;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            color: white;
            font-size:22px;
        }
        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }
        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
        }
      .bank-details {
      font-size: 14px;
      line-height: 1.6;
      margin-top: 10px;
      }
      .bank-details strong {
      color: #333;
      }
            
      </style>
        <div class="invoice-box">
            <table>
                <tr class="top">
                    <td colspan="2">
                        <table>
                            <tr>
                                <td class="title">
                                    <img src="http://192.168.15.29/storage/uploads/logo/logo-dark.png" style="width:60%; max-width:300px;">
                                </td>
                                <td>
                                    Invoice #: ' . (int) ($invoiceRow['invoice_id'] ?? 0) . '<br>
                                    By: ' . spEsc($user_initials) . '<br>
                                    Created: ' . $curDateMySQL . '<br>
                                    Due: ' . $duedate . '<br>
                                    ' . $status_text . ': ' . date('m/d/Y', strtotime($paid_date)) . '<br>
                                    ' . $filepath . '
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="information">
                    <td colspan="2">
                        <table>
                            <tr>
                                <td>
                                    From:<br>
                                    Syed Professional Services<br>
                                    1012 Coney Island Ave<br>
                                    Brooklyn, NY 11230<br>
                                    Phone: (718) 859-5392<br />
                                    Fax: (718) 859-2237<br />
                                    invoice@syedpro.com
                                </td>
                                <td>
                                    <b>To:</b> <br>
                                    ' . spEsc($name) . '<br>
                                    ' . spEsc($address) . '<br>
                                    ' . spEsc($address1) . '<br>
                                    ' . spEsc($phone) . '<br>
                                    ' . spEsc($email) . '
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="heading">
                    <td>Service</td>
                    <td>Price</td>
                </tr>
               <tr class="details">
  <td><b>Service Provider:</b> ' . spEsc($service_provider_name) . ' (' . (int) $service_provider_id . ')</td>
</tr>
                ' . $itemsHtml . '
                ' . ($description !== '' ? '<tr class="details">
                    <td colspan="2"><b>Description:</b> ' . spEsc($description) . '</td>
                </tr>' : '') . '
                <tr class="total">
                    <td></td>
                    <td>Total: $' . $totalamount . '</td>
                </tr>
                <tr class="total">
                    <td></td>
                    <td>Paid: $' . $paidamount . '</td>
                </tr>
                ' . $discountRow . '
                <tr class="total">
                    <td></td>
                    <td>Balance: $' . $balance . '</td>
                </tr>
                <tr class="details">
            <td>
                <div class="bank-details">
                    <strong>Bank Details:</strong><br/>
                    Bank Name: Chase Bank<br/>
                    Account Number: 096077428865<br/>
                    Routing Number: 21000021<br/>
                    <b>Payment Method(s):</b> ' . spEsc($payment_methods_str) . '
                </div>
            </td>
             
        </tr>
            </table>
            <div class="footer-note">
                <p>In the event of a returned or unsuccessful payment, a bank fee of $25 will be applied.
                <br>We accept the payment via Zelle,Credit or debit Card,Cash and cheque.
                </p>
                <h4><u>Thank you for choosing Syed Professional Services</u></h4>
            </div>
        </div>';
}

function spRebuildInvoicePdf($con, $invoiceId)
{
    $invoiceId = (int) $invoiceId;
    $query = mysqli_query($con, "SELECT * FROM billing_invoice WHERE invoice_id = {$invoiceId} LIMIT 1");
    if (!$query || mysqli_num_rows($query) === 0) {
        return false;
    }

    $invoiceRow = mysqli_fetch_assoc($query);
    $memberId = (int) ($invoiceRow['mem_id'] ?? 0);
    $memberQuery = mysqli_query($con, "SELECT * FROM members WHERE member_id = {$memberId} LIMIT 1");
    $memberRow = $memberQuery ? mysqli_fetch_assoc($memberQuery) : [];
    $items = spEnsureItemsFallback($invoiceRow, spFetchInvoiceItems($con, $invoiceId));

    $existingFileLink = basename(trim((string) ($invoiceRow['filelink'] ?? '')));
    $datePart = date('m-d-Y');
    $invoiceDate = trim((string) ($invoiceRow['invoice_date'] ?? ''));
    if ($invoiceDate !== '' && strtotime($invoiceDate) !== false) {
        $datePart = date('m-d-Y', strtotime($invoiceDate));
    }
    $expectedFileName = sprintf('INV-%d-MemID-%d-%s.pdf', $invoiceId, $memberId, $datePart);
    $targetFileName = $existingFileLink !== '' ? $existingFileLink : $expectedFileName;
    if (strtolower(pathinfo($targetFileName, PATHINFO_EXTENSION)) !== 'pdf') {
        $targetFileName .= '.pdf';
    }

    $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'invoices';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }

    $fullPath = $targetDir . DIRECTORY_SEPARATOR . $targetFileName;
    $payment_methods_str = 'Stripe';
    $user_id = $_SESSION['user_id'] ?? 0;
    $user_initials = '';
    if ($user_id) {
        $user_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . (int) $user_id . "' LIMIT 1");
        if ($user_query && ($user_row = mysqli_fetch_assoc($user_query))) {
            $user_initials = spGetInitials($user_row['user_name']);
        }
        if ($user_initials !== '') {
            $user_initials .= '(' . (int) $user_id . ')';
        }
    }
    if ($user_initials === '') {
        $user_initials = 'NA';
    }
    $html = spBuildInvoiceHtml($con, $invoiceRow, $memberRow, $items, $payment_methods_str, $user_initials);
    $dompdf = new Dompdf(['enable_remote' => true]);
    $dompdf->loadHtml($html);
    $dompdf->render();
    file_put_contents($fullPath, $dompdf->output());

    if ($existingFileLink === '' || $existingFileLink !== $targetFileName) {
        mysqli_query(
            $con,
            "UPDATE billing_invoice SET filelink = '" . mysqli_real_escape_string($con, $targetFileName) . "' WHERE invoice_id = {$invoiceId}"
        );
    }

    return true;
}


function createStripeTable($con)
{
    $sql = "CREATE TABLE IF NOT EXISTS `stripe_payment_links` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `invoice_id` int(11) NOT NULL,
      `member_id` int(11) NOT NULL,
      `stripe_payment_link_id` varchar(255) NOT NULL,
      `stripe_product_id` varchar(255) DEFAULT NULL,
      `stripe_price_id` varchar(255) DEFAULT NULL,
      `amount` decimal(10,2) NOT NULL,
      `currency` varchar(3) DEFAULT 'usd',
      `status` enum('pending','paid','expired','cancelled') DEFAULT 'pending',
      `payment_url` text NOT NULL,
      `payment_intent_id` varchar(255) DEFAULT NULL,
      `stripe_receipt_url` text DEFAULT NULL,
      `paid_amount` decimal(10,2) DEFAULT 0.00,
      `paid_at` datetime DEFAULT NULL,
      `confirmed_by` int(11) DEFAULT NULL,
      `confirmed_at` datetime DEFAULT NULL,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `invoice_id` (`invoice_id`),
      KEY `member_id` (`member_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    mysqli_query($con, $sql);
}



class StripePaymentManager
{
    private $stripe;

    public function __construct()
    {

        $stripeInitFile = __DIR__ . '/stripe-php-13.0.0/init.php';

        if (!file_exists($stripeInitFile)) {
            throw new Exception('Stripe PHP library not found at: ' . $stripeInitFile .
                '. Please download from https://github.com/stripe/stripe-php and extract to root folder.');
        }

        require_once $stripeInitFile;

        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        $this->stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);
    }

    public function createPaymentLink($invoice_id, $amount, $customer_data)
    {
        try {
            // USD ONLY - No currency conversion
            $currency = 'usd';
            $amount_cents = round($amount * 100); // Convert dollars to cents

            $product = $this->stripe->products->create([
                'name' => 'Invoice #' . $invoice_id,
                'description' => 'Payment for services - Syed Professional Services',
                'metadata' => [
                    'internal_invoice_id' => $invoice_id,
                    'customer_id' => $customer_data['member_id'] ?? ''
                ]
            ]);

            $price = $this->stripe->prices->create([
                'product' => $product->id,
                'unit_amount' => $amount_cents,
                'currency' => $currency, // Always 'usd'
            ]);

            $price = $this->stripe->prices->create([
                'product' => $product->id,
                'unit_amount' => $amount_cents,
                'currency' => $currency, // 'pkr' or 'usd'
            ]);

            $payment_link = $this->stripe->paymentLinks->create([
                'line_items' => [['price' => $price->id, 'quantity' => 1]],
                'after_completion' => [
                    'type' => 'hosted_confirmation',
                    'hosted_confirmation' => [
                        'custom_message' => 'Thank you for your payment! Please contact Syed Professional Services at (718) 859-5392 to confirm your payment has been processed in our system.'
                    ]
                ],
                'metadata' => [
                    'internal_invoice_id' => $invoice_id,
                    'customer_id' => $customer_data['member_id'] ?? ''
                ],
                'invoice_creation' => [
                    'enabled' => true,
                    'invoice_data' => [
                        'description' => 'Invoice #' . $invoice_id,
                        'footer' => 'Syed Professional Services | 1012 Coney Island Ave, Brooklyn, NY 11230 | (718) 859-5392'
                    ]
                ]
            ]);

            return [
                'success' => true,
                'payment_link_id' => $payment_link->id,
                'url' => $payment_link->url,
                'amount' => $amount,
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id
            ];

        } catch (\Exception $e) {
            error_log('Stripe Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function checkPaymentStatus($payment_link_id)
    {
        try {
            // Method 1: Search by payment_link ID in metadata
            $paymentIntents = $this->stripe->paymentIntents->all([
                'limit' => 10,
            ]);

            foreach ($paymentIntents->data as $intent) {
                // Check if this payment used our payment link
                if ($intent->status === 'succeeded') {
                    // Look for the charge with payment link reference
                    foreach ($paymentIntents->data as $intent) {

                        if (
                            $intent->status === 'succeeded' &&
                            (($intent->metadata['payment_link'] ?? null) === $payment_link_id)
                        ) {
                            $charge = null;
                            if (!empty($intent->latest_charge)) {
                                $charge = $this->stripe->charges->retrieve($intent->latest_charge, []);
                            }

                            $paymentType = '';
                            $cardBrand = '';
                            $receiptUrl = null;

                            if ($charge) {
                                $receiptUrl = $charge->receipt_url ?? null;
                                $paymentDetails = $charge->payment_method_details ?? null;
                                if ($paymentDetails) {
                                    $paymentType = (string) ($paymentDetails->type ?? '');
                                    if (!empty($paymentDetails->card)) {
                                        $cardBrand = (string) ($paymentDetails->card->brand ?? '');
                                    }
                                }
                            }

                            if ($paymentType === '' && !empty($intent->payment_method_types)) {
                                $paymentType = (string) $intent->payment_method_types[0];
                            }

                            return [
                                'paid' => true,
                                'amount_received' => $intent->amount_received / 100,
                                'payment_intent_id' => $intent->id,
                                'payment_method' => $paymentType !== '' ? $paymentType : implode(', ', $intent->payment_method_types),
                                'card_brand' => $cardBrand,
                                'receipt_url' => $receiptUrl,
                                'paid_at' => date('Y-m-d H:i:s', $intent->created)
                            ];
                        }
                    }

                }
            }

            // Method 2: Check checkout sessions
            $sessions = $this->stripe->checkout->sessions->all([
                'limit' => 20,
            ]);

            foreach ($sessions->data as $session) {
                if ($session->payment_link === $payment_link_id && $session->payment_status === 'paid') {
                    return [
                        'paid' => true,
                        'amount_received' => $session->amount_total / 100,
                        'payment_intent_id' => $session->payment_intent,
                        'payment_method' => 'card',
                        'card_brand' => '',
                        'receipt_url' => null,
                        'paid_at' => date('Y-m-d H:i:s', $session->created)
                    ];
                }
            }

            return ['paid' => false];

        } catch (\Exception $e) {
            error_log('Check Payment Error: ' . $e->getMessage());
            return ['paid' => false, 'error' => $e->getMessage()];
        }
    }
}

// ============== AJAX HANDLERS ==============
if (isset($_GET['stripe_ajax'])) {
    session_start();
    header('Content-Type: application/json');

    $action = $_GET['stripe_ajax'];
    $con = $GLOBALS['con'] ?? null;

    if (!$con && isset($GLOBALS['mysqli'])) {
        $con = $GLOBALS['mysqli'];
    }

    if (!$con) {
        // Try to include your db connection
        if (file_exists(__DIR__ . '/includes/db.php')) {
            require_once __DIR__ . '/includes/db.php';
        }
        $con = $GLOBALS['con'] ?? null;
    }

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    // Create table if not exists
    createStripeTable($con);

    $stripe = new StripePaymentManager();

    switch ($action) {
        case 'generate_link':
            $invoice_id = intval($_POST['invoice_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);

            if (!$invoice_id || $amount <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
                exit;
            }

            // Get invoice details
            $query = mysqli_query($con, "
                SELECT bi.*, m.member_id, m.prm_email, m.name, m.lname, m.bus_name, m.cell_ph
                FROM billing_invoice bi
                JOIN members m ON bi.mem_id = m.member_id
                WHERE bi.invoice_id = '$invoice_id'
                LIMIT 1
            ");

            if (!$data = mysqli_fetch_assoc($query)) {
                echo json_encode(['success' => false, 'error' => 'Invoice not found']);
                exit;
            }

            $customer_data = [
                'member_id' => $data['member_id'],
                'name' => $data['bus_name'] ?: $data['name'] . ' ' . $data['lname'],
                'email' => $data['prm_email'],
                'phone' => $data['cell_ph']
            ];

            $result = $stripe->createPaymentLink($invoice_id, $amount, $customer_data);

            if ($result['success']) {
                $link_id = mysqli_real_escape_string($con, $result['payment_link_id']);
                $product_id = mysqli_real_escape_string($con, $result['stripe_product_id']);
                $price_id = mysqli_real_escape_string($con, $result['stripe_price_id']);
                $url = mysqli_real_escape_string($con, $result['url']);
                $member_id = $data['member_id'];

                $insert = "INSERT INTO stripe_payment_links 
                           (invoice_id, member_id, stripe_payment_link_id, stripe_product_id, stripe_price_id, 
                            amount, payment_url, status) 
                           VALUES ('$invoice_id', '$member_id', '$link_id', '$product_id', '$price_id',
                                   '$amount', '$url', 'pending')";

                if (mysqli_query($con, $insert)) {
                    // Log activity if function exists
                    if (function_exists('logActivity')) {
                        logActivity(
                            $con,
                            $_SESSION['user_id'],
                            'CREATE',
                            'stripe_payment_links',
                            mysqli_insert_id($con),
                            "Created Stripe payment link for invoice #$invoice_id"
                        );
                    }

                    echo json_encode([
                        'success' => true,
                        'url' => $result['url'],
                        'link_id' => $result['payment_link_id']
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Database error']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => $result['error']]);
            }
            break;

        case 'check_payment':
            $link_id = $_POST['link_id'] ?? '';
            $invoice_id = intval($_POST['invoice_id'] ?? 0);

            if (!$link_id || !$invoice_id) {
                echo json_encode(['success' => false, 'error' => 'Missing parameters']);
                exit;
            }

            $status = $stripe->checkPaymentStatus($link_id);

            if ($status['paid']) {
                $paid_amount = $status['amount_received'];
                $payment_intent = mysqli_real_escape_string($con, $status['payment_intent_id']);
                $receipt_url = mysqli_real_escape_string($con, $status['receipt_url'] ?? '');
                $user_id = $_SESSION['user_id'];
                $paid_at = $status['paid_at'];

                $invoiceRow = mysqli_fetch_assoc(mysqli_query(
                    $con,
                    "SELECT mem_id, balance, payment_details, paid_amount, total_amount FROM billing_invoice WHERE invoice_id = '$invoice_id' LIMIT 1"
                ));
                $member_id = $invoiceRow['mem_id'] ?? 0;
                $prev_balance = isset($invoiceRow['balance']) ? (float) $invoiceRow['balance'] : 0.0;
                $current_paid_amount = isset($invoiceRow['paid_amount']) ? (float) $invoiceRow['paid_amount'] : 0.0;
                $new_paid_amount_total = $current_paid_amount + (float) $paid_amount;
                $new_balance = max(0, $prev_balance - (float) $paid_amount);
                $new_invoice_status = $new_balance <= 0 ? 3 : 2;
                $effective_paid_date = $paid_at && strtotime($paid_at) ? date('Y-m-d', strtotime($paid_at)) : date('Y-m-d');

                $stripe_payment_type = trim((string) ($status['payment_method'] ?? ''));
                $stripe_card_brand = trim((string) ($status['card_brand'] ?? ''));
                $payment_type_id = spMapStripePaymentTypeId($stripe_payment_type);
                $new_details = spBuildStripePaymentDescription($stripe_payment_type, $stripe_card_brand);
                $payment_method = 'Stripe';

                mysqli_query($con, "UPDATE stripe_payment_links 
                                   SET status = 'paid',
                                       paid_amount = '$paid_amount',
                                       payment_intent_id = '$payment_intent',
                                       stripe_receipt_url = '$receipt_url',
                                       paid_at = '{$paid_at}',
                                       confirmed_by = '$user_id',
                                       confirmed_at = NOW()
                                   WHERE stripe_payment_link_id = '$link_id'");

                mysqli_query($con, "UPDATE billing_invoice 
                                   SET paid_amount = '$new_paid_amount_total',
                                       balance = '$new_balance',
                                       invoice_status = '$new_invoice_status',
                                       payment_type = '$payment_type_id',
                                       paid_date = '$effective_paid_date',
                                       payment_details = '" . mysqli_real_escape_string($con, $new_details) . "'
                                   WHERE invoice_id = '$invoice_id'");

                $date2 = date('Y-m-d h:i:s');
                $employee_initials = '';
                $user_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . (int) $user_id . "' LIMIT 1");
                if ($user_query && ($user_row = mysqli_fetch_assoc($user_query))) {
                    $employee_initials = spGetInitials($user_row['user_name']);
                }
                if ($employee_initials === '') {
                    $employee_initials = (string) $user_id;
                }
                mysqli_query(
                    $con,
                    "INSERT INTO billing_payment_history 
                     (mem_id, inv_id, paid, balance, detail, date, employee, payment_method)
                     VALUES ('" . (int) $member_id . "', '$invoice_id', '$paid_amount', '$prev_balance', '" . mysqli_real_escape_string($con, $new_details) . "', '$date2', '" . mysqli_real_escape_string($con, $employee_initials) . "', '" . mysqli_real_escape_string($con, $payment_method) . "')"
                );

                spRebuildInvoicePdf($con, $invoice_id);
                if (function_exists('logActivity')) {
                    logActivity(
                        $con,
                        $user_id,
                        'UPDATE',
                        'billing_invoice',
                        $invoice_id,
                        "Payment received via Stripe for invoice #{$invoice_id} - Amount: $" . number_format($paid_amount, 2)
                    );
                }

                echo json_encode([
                    'success' => true,
                    'paid' => true,
                    'amount' => number_format($paid_amount, 2),
                    'payment_method' => $payment_method,
                    'payment_details' => $new_details,
                    'paid_at' => date('m/d/Y h:i A', strtotime($status['paid_at'])),
                    'receipt_url' => $status['receipt_url']
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'paid' => false,
                    'message' => 'No payment received yet'
                ]);
            }
            break;

        case 'send_email':
            $invoice_id = intval($_POST['invoice_id'] ?? 0);
            $payment_url = $_POST['payment_url'] ?? '';

            // Get invoice and client details
            $query = mysqli_query($con, "
                SELECT bi.*, m.prm_email, m.name, m.lname, m.bus_name
                FROM billing_invoice bi
                JOIN members m ON bi.mem_id = m.member_id
                WHERE bi.invoice_id = '$invoice_id'
                LIMIT 1
            ");

            if (!$data = mysqli_fetch_assoc($query)) {
                echo json_encode(['success' => false, 'error' => 'Invoice not found']);
                exit;
            }

            $to = $data['prm_email'];
            if (empty($to)) {
                echo json_encode(['success' => false, 'error' => 'Client has no email address']);
                exit;
            }

            // Validate email format
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'Invalid email address: ' . $to]);
                exit;
            }

            $client_name = $data['bus_name'] ?: $data['name'] . ' ' . $data['lname'];
            $amount = number_format($data['balance'], 2);
            $subject = "Stripe Payment Option";

            // Email body
            $message = "
                    <html>
                    <body style='font-family: Arial, sans-serif; line-height:1.6; color:#333;'>
                    <p>Dear Client,</p>
                    <p>We accept payments via Stripe for your convenience. Please note that Stripe charges a 4% processing fee. If you choose to proceed with this payment method, we kindly request that you include the additional 4% fee with your payment.</p>
                    <p>Please click the button below to make your payment:</p>
                    <p>
                    <a href='{$payment_url}' style='display:inline-block;padding:12px 25px;background:#28a745;color:#ffffff;text-decoration:none;border-radius:5px;font-weight:bold;'>
                    Pay with Stripe
                    </a>
                    </p>
                    <p>Thank you for your understanding.</p>
                    <p>Please let us know if you would prefer an alternative payment method.</p>
                    <p>
                    Best regards,<br>
                    Syed Professional Services
                    </p>
                    </body>
                    </html>
                    ";

            // Try PHPMailer first, fallback to mail()
            $phpmailer_paths = [
                __DIR__ . '/PHPMailer/PHPMailerAutoload.php',
                __DIR__ . '/PHPMailer/src/PHPMailer.php',
                __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
            ];

            $phpmailer_found = false;
            foreach ($phpmailer_paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    $phpmailer_found = true;
                    break;
                }
            }

            if ($phpmailer_found && class_exists('PHPMailer')) {
                // Use PHPMailer with Office 365
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = 'smtp.office365.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'invoice@syedpro.com';
                    $mail->Password = 'bgtqpxznmghqwkrc'; // App password
                    $mail->SMTPSecure = 'tls';
                    $mail->Port = 587;
                    $mail->CharSet = 'UTF-8';

                    // Enable debug output (remove in production)
                    // $mail->SMTPDebug = 2;

                    $mail->setFrom('invoice@syedpro.com', 'Syed Professional Services');
                    $mail->addAddress($to);
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $message;
                    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));

                    $mail->send();

                    // Log success
                    if (function_exists('logActivity')) {
                        @logActivity(
                            $con,
                            $_SESSION['user_id'],
                            'EMAIL_SENT',
                            'stripe_payment_links',
                            $invoice_id,
                            "Payment link email sent to $to via Office 365"
                        );
                    }

                    echo json_encode(['success' => true, 'message' => 'Email sent successfully to ' . $to]);
                    exit;

                } catch (Exception $e) {
                    error_log("PHPMailer Error: " . $e->getMessage());
                    // Fall through to try mail() as backup
                }
            }

            // Fallback to PHP mail()
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: Syed Professional Services <invoice@syedpro.com>\r\n";

            if (@mail($to, $subject, $message, $headers)) {
                if (function_exists('logActivity')) {
                    @logActivity(
                        $con,
                        $_SESSION['user_id'],
                        'EMAIL_SENT',
                        'stripe_payment_links',
                        $invoice_id,
                        "Payment link email sent to $to via mail()"
                    );
                }
                echo json_encode(['success' => true, 'message' => 'Email sent (via mail)']);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to send email. PHPMailer not found and mail() failed.',
                    'debug' => 'Please install PHPMailer in /PHPMailer folder or configure server mail'
                ]);
            }
            break;
    }
    exit;
}

// ============== HELPER FUNCTION TO RENDER BUTTONS ==============
function renderStripeButtons($con, $invoice_id, $balance)
{
    // Create table if not exists (first run)
    createStripeTable($con);

    $query = mysqli_query($con, "
        SELECT * FROM stripe_payment_links 
        WHERE invoice_id = '$invoice_id' 
        AND status = 'pending'
        ORDER BY id DESC 
        LIMIT 1
    ");
    $link = mysqli_fetch_assoc($query);

    if ($link) {
        // Link exists - show copy, check, email buttons
        return '
        <button class="btn btn-sm btn-info tooltip1" 
                onclick="copyStripeLink(\'' . $link['payment_url'] . '\')"
                title="Copy Payment Link">
            <i class="ti ti-link"></i>
        </button>
        <button class="btn btn-sm btn-success tooltip1" 
                onclick="checkStripePayment(\'' . $link['stripe_payment_link_id'] . '\', ' . $invoice_id . ')"
                title="Check if Paid">
            <i class="ti ti-refresh"></i>
        </button>
        <button class="btn btn-sm btn-outline-primary tooltip1" 
                onclick="sendStripeEmail(' . $invoice_id . ', \'' . $link['payment_url'] . '\')"
                title="Email Link to Client">
            <i class="ti ti-mail"></i>
        </button>';
    } else {
        // No link - show generate button
        return '
        <button class="btn btn-sm btn-outline-success tooltip1" 
                onclick="generateStripeLink(' . $invoice_id . ', ' . $balance . ')"
                title="Generate Stripe Payment Link">
            <i class="ti ti-credit-card"></i> Stripe
        </button>';
    }
}

// ============== JAVASCRIPT TO ECHO IN YOUR PAGE ==============
function getStripeJavaScript()
{
    return <<<JS
<script>
function generateStripeLink(invoiceId, amount) {
    console.log('Generate link for invoice:', invoiceId, 'amount:', amount);
    
    if (amount <= 0) {
        Swal.fire("success", "Invoice is already Paid", "success");
        return;
    }

    Swal.fire({
        title: "Generate Stripe Link?",
        html: 'Create secure payment link for <b>$' + amount + '</b>?<br><small>Client pays on Stripe\'s secure page</small>',
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, Generate",
        cancelButtonText: "Cancel",
        confirmButtonColor: "#28a745"
    }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.fire({
            title: "Creating Link...",
            html: "Please wait...",
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch("stripe_payment.php?stripe_ajax=generate_link", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "invoice_id=" + encodeURIComponent(invoiceId) + "&amount=" + encodeURIComponent(amount)
        })
        .then(response => response.json())
        .then(data => {
            console.log('Response:', data);
            if (!data.success) throw new Error(data.error || "Unknown error");

            Swal.fire({
                title: "✅ Link Created!",
                html: '<div style="text-align:left;">' +
                        '<p><strong>Payment URL:</strong></p>' +
                        '<div class="input-group mb-3">' +
                            '<input type="text" class="form-control" value="' + data.url + '" readonly onclick="this.select()">' +
                            '<button class="btn btn-outline-secondary" onclick="copyStripeLink(\'' + data.url + '\')"><i class="ti ti-copy"></i></button>' +
                        '</div>' +
                        '<p class="text-muted">Click "Check if Paid" button after client pays</p>' +
                      '</div>',
                width: "600px",
                showConfirmButton: false,
                showCloseButton: true
            });
            
            setTimeout(() => location.reload(), 3000);
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire("Error", error.message, "error");
        });
    });
}

function checkStripePayment(linkId, invoiceId) {
    console.log('Checking payment for link:', linkId, 'invoice:', invoiceId);
    
    Swal.fire({
        title: "Checking Payment...",
        html: "Querying Stripe for payment status...",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch("stripe_payment.php?stripe_ajax=check_payment", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "link_id=" + encodeURIComponent(linkId) + "&invoice_id=" + encodeURIComponent(invoiceId)
    })
    .then(response => response.json())
    .then(data => {
        console.log('Check payment response:', data);
        
        if (!data.success) {
            throw new Error(data.error || "Failed to check payment");
        }
        
        if (data.paid) {
            Swal.fire({
                icon: "success",
                title: "✅ Payment Received!",
                html: '<div style="text-align:left;">' +
                        '<p><strong>Amount:</strong> $' + data.amount + '</p>' +
                        '<p><strong>Method:</strong> ' + data.payment_method + '</p>' +
                        (data.payment_details ? '<p><strong>Description:</strong> ' + data.payment_details + '</p>' : '') +
                        '<p><strong>Time:</strong> ' + data.paid_at + '</p>' +
                        (data.receipt_url ? '<a href="' + data.receipt_url + '" target="_blank" class="btn btn-sm btn-primary">View Receipt</a>' : '') +
                      '</div>',
                confirmButtonText: "Reload Page"
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: "info",
                title: "Not Paid Yet",
                text: "No payment found for this link. Ask client to complete payment or try again in a moment.",
                footer: '<a href="https://dashboard.stripe.com/test/payments" target="_blank">Check Stripe Dashboard</a>'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire("Error", error.message, "error");
    });
}

function sendStripeEmail(invoiceId, paymentUrl) {
    console.log('Sending email for invoice:', invoiceId);
    
    Swal.fire({
        title: "Send Email?",
        text: "Send payment link to client's email?",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, Send",
        cancelButtonText: "Cancel"
    }).then((result) => {
        if (!result.isConfirmed) return;
        
        Swal.fire({
            title: "Sending...",
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch("stripe_payment.php?stripe_ajax=send_email", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "invoice_id=" + encodeURIComponent(invoiceId) + "&payment_url=" + encodeURIComponent(paymentUrl)
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) throw new Error(data.error);
            Swal.fire("Sent!", "Payment link email sent to client", "success");
        })
        .catch(error => {
            Swal.fire("Error", error.message, "error");
        });
    });
}

function copyStripeLink(url) {
    // IMMEDIATELY try to copy using the method that doesn't require focus
    // Don't use await or promises that create delays
    const success = fallbackCopyToClipboard(url);
    
    // Only try modern API as backup if fallback somehow failed
    if (!success && navigator.clipboard && window.isSecureContext) {
        // Try modern API but catch focus errors
        navigator.clipboard.writeText(url).then(() => {
            Swal.fire({
                toast: true,
                position: "top-end",
                icon: "success",
                title: "Link copied!",
                showConfirmButton: false,
                timer: 1500
            });
        }).catch((err) => {
            console.error('Clipboard API failed:', err);
            // Already tried fallback, show manual dialog
            showManualCopyDialog(url);
        });
    }
}

// Fallback using execCommand - works synchronously without focus requirements
function fallbackCopyToClipboard(text) {
    // Create a temporary input element
    const input = document.createElement("input");
    input.value = text;
    
    // Position it off-screen but in DOM
    input.style.cssText = "position:fixed;top:-9999px;left:-9999px;opacity:0;";
    
    document.body.appendChild(input);
    
    // Select the content immediately
    input.focus();
    input.select();
    input.setSelectionRange(0, 99999);
    
    let successful = false;
    
    try {
        // Execute copy immediately while we have focus
        successful = document.execCommand('copy');
    } catch (err) {
        console.error('execCommand failed:', err);
    }
    
    // Clean up immediately
    document.body.removeChild(input);
    
    // Show feedback immediately
    if (successful) {
        Swal.fire({
            toast: true,
            position: "top-end",
            icon: "success",
            title: "Link copied!",
            showConfirmButton: false,
            timer: 1500
        });
    }
    
    return successful;
}


function showManualCopyDialog(text) {
    Swal.fire({
        title: "Copy this URL",
        html: '<input type="text" id="manual-copy-url" value="' + text + '" style="width:100%;padding:10px;font-size:14px;" readonly>',
        showConfirmButton: true,
        confirmButtonText: "Close",
        didOpen: () => {
            const input = document.getElementById('manual-copy-url');
            if (input) {
                input.focus();
                input.select();
            }
        }
    });
}
</script>
JS;
}
