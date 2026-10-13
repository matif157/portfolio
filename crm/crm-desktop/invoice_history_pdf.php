<?php

declare(strict_types=1);
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

include 'includes/db.php';

$invoice_id = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : 0;
if ($invoice_id <= 0) {
    exit('Invoice ID missing');
}

/* ---------- 1. FOLDER HANDLING ---------- */
$saveDir = __DIR__ . '/invoice_history';
if (!is_dir($saveDir)) {
    mkdir($saveDir, 0755, true);
}
$pdfFile = "$saveDir/INV-HISTORY-$invoice_id.pdf";

/* ---------- 2. FETCH INVOICE DATA ---------- */
$q = "
    SELECT i.*, s.status_text,
           m.name, m.lname, m.bus_name, m.dba,
           m.hstreet, m.hcity, m.hstate, m.hzip, m.cell_ph, m.prm_email,
           u.user_name, u.user_id
    FROM billing_invoice i
    LEFT JOIN billing_invoice_status s ON s.status_id = i.invoice_status
    LEFT JOIN members m ON m.member_id = i.mem_id
    LEFT JOIN users u ON u.user_id = i.service_provider_id
    WHERE i.invoice_id = $invoice_id
";
$inv = mysqli_fetch_assoc(mysqli_query($con, $q));
if (!$inv) {
    exit('Invoice not found');
}

/* ---------- 3. FORMAT DATA ---------- */
$clientName = $inv['bus_name'] ?: $inv['dba'] ?: trim($inv['name'] . ' ' . $inv['lname']);
$clientAddress = '';
if (!empty($inv['hstreet']))
    $clientAddress .= htmlspecialchars($inv['hstreet']) . '<br>';
if (!empty($inv['hcity']) || !empty($inv['hstate']) || !empty($inv['hzip'])) {
    $clientAddress .= trim(htmlspecialchars($inv['hcity']) . ', ' . htmlspecialchars($inv['hstate']) . ' ' . htmlspecialchars($inv['hzip']));
}
$clientEmail = !empty($inv['prm_email']) ? htmlspecialchars($inv['prm_email']) : 'N/A';
$clientPhone = !empty($inv['cell_ph']) ? htmlspecialchars($inv['cell_ph']) : 'N/A';

$invDate = date('m/d/y', strtotime($inv['invoice_date']));
$dueDate = date('m/d/y', strtotime($inv['due_date']));

// Service Provider Format
$spFormatted = '—';
if ($inv['user_id'] && !empty($inv['user_name'])) {
    $nameParts = explode(' ', trim($inv['user_name']));
    $initials = '';
    foreach ($nameParts as $part) {
        if (!empty($part)) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }
    $spFormatted = $initials . '(' . $inv['user_id'] . ')';
}

$employeeInitials = !empty($inv['employee_initials']) ? htmlspecialchars($inv['employee_initials']) : '—';
$lastEditedBy = !empty($inv['last_edited_by']) ? htmlspecialchars($inv['last_edited_by']) : '—';

// Paid Date
$realPaidDate = ($inv['paid_date'] === '0000-00-00' || !$inv['paid_date'])
    ? (mysqli_fetch_assoc(mysqli_query(
        $con,
        "SELECT DATE_FORMAT(MAX(date),'%m/%d/%Y') AS pd FROM billing_payment_history WHERE inv_id = $invoice_id"
    ))['pd'] ?? '—')
    : date('m/d/Y', strtotime($inv['paid_date']));

/* ---------- 4. ITEMS ---------- */
$itemsHtml = '';
$res = mysqli_query($con, "SELECT item_description, item_sub_description, unit_price, total_price 
FROM billing_invoice_items WHERE inv_id = $invoice_id ORDER BY inv_item_id");
while ($row = mysqli_fetch_assoc($res)) {
    $desc = htmlspecialchars($row['item_description']);
    $sub = htmlspecialchars($row['item_sub_description']);
    $subLine = $sub ? '<div style="font-size:10px; color:#666; padding-top:2px;">' . $sub . '</div>' : '';
    $itemsHtml .= '
    <tr>
        <td style="padding: 8px; border-bottom: 1px solid #ddd; vertical-align: top;">
            <div style="font-weight: bold;">' . $desc . '</div>
            ' . $subLine . '
        </td>
        <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right; vertical-align: top;">$' . number_format((float) $row['unit_price'], 2) . '</td>
        <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right; vertical-align: top;">$' . number_format((float) $row['total_price'], 2) . '</td>
    </tr>';
}

/* ---------- 5. PAYMENT HISTORY ---------- */
$historyHtml = '';
$res = mysqli_query($con, "
    SELECT DATE_FORMAT(date,'%m/%d/%Y') AS pd, paid, balance, detail, employee, payment_method
    FROM billing_payment_history
    WHERE inv_id = $invoice_id
    ORDER BY date DESC, his_id DESC
");
if (mysqli_num_rows($res)) {
    while ($row = mysqli_fetch_assoc($res)) {
        $historyHtml .= '
        <tr>
            <td style="border: 1px solid #000; padding: 6px; font-size: 11px;">' . $row['pd'] . '</td>
            <td style="border: 1px solid #000; padding: 6px; font-size: 11px; text-align: right;">$' . number_format((float) $inv['total_amount'], 2) . '</td>
            <td style="border: 1px solid #000; padding: 6px; font-size: 11px; text-align: right;">$' . number_format((float) $row['paid'], 2) . '</td>
            <td style="border: 1px solid #000; padding: 6px; font-size: 11px; text-align: right;">$' . number_format((float) $row['balance'], 2) . '</td>
            <td style="border: 1px solid #000; padding: 6px; font-size: 11px;">' . htmlspecialchars($row['payment_method'] ?: '—') . '</td>
            <td style="border: 1px solid #000; padding: 6px; font-size: 11px;">' . htmlspecialchars($row['employee'] ?: '—') . '</td>
            <td style="border: 1px solid #000; padding: 6px; font-size: 11px;">' . htmlspecialchars($row['detail'] ?: '—') . '</td>
        </tr>';
    }
} else {
    $historyHtml = '<tr><td colspan="7" style="border: 1px solid #000; padding: 10px; text-align: center; color: #999;">No payment history available.</td></tr>';
}


$logoPath = __DIR__ . '/storage/uploads/logo/logo-dark.png';
$watermarkPath = __DIR__ . '/storage/uploads/logo/logo-darks.jpeg';

// Convert images to base64 for embedding in PDF
$logoBase64 = '';
$watermarkBase64 = '';

if (file_exists($logoPath)) {
    $logoData = file_get_contents($logoPath);
    $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
}

if (file_exists($watermarkPath)) {
    $watermarkData = file_get_contents($watermarkPath);
    $watermarkBase64 = 'data:image/jpeg;base64,' . base64_encode($watermarkData);
}

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Invoice #' . $invoice_id . '</title>
<style>
    @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400 ;700&display=swap");
    body { 
        font-family: "Inter", Arial, sans-serif; 
        font-size: 12px; 
        line-height: 1.4; 
        margin: 0; 
        padding: 20px; 
        position: relative;
    }
    
    .watermark {
        position: absolute;
        top: 55%;
        left: 75%;
        transform: translate(-50%, -50%);
        width: 250mm;
        height: 250mm;
        background-image: url("' . $watermarkBase64 . '");
        background-repeat: no-repeat;
        background-position: center;
        background-size: contain;
        opacity: 0.2;
        pointer-events: none;
        z-index: 0;
    }
    
    .content {
        position: relative;
        z-index: 1;
    }
    
    .header { 
        border-bottom: 3px solid #003366; 
        padding-bottom: 20px; 
        margin-bottom: 20px; 
    }
    
    .company-name { 
        font-size: 28px; 
        font-weight: bold; 
        color: #003366; 
        margin-bottom: 10px; 
    }
    
    .invoice-info { 
        text-align: right; 
    }
    
    .section { 
        margin-bottom: 25px; 
    }
    
    .section-title { 
        font-size: 16px; 
        font-weight: bold; 
        color: #003366; 
        margin-bottom: 10px; 
        padding-bottom: 5px; 
    }
    
    table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-bottom: 15px; 
    }
    
    th { 
        background-color: #003366; 
        color: white; 
        padding: 10px; 
        text-align: left; 
        font-weight: bold; 
    }
    
    td { 
        padding: 8px; 
    }
    
    .totals { 
        width: 300px; 
        float: right; 
    }
    
    .total-row { 
        border-top: 2px solid #000; 
        padding-top: 8px; 
    }
    
    .clear { 
        clear: both; 
    }
     
    .footer { 
        text-align: center; 
        margin-top: 30px; 
        font-size: 11px; 
        color: #666; 
    }
    
    .logo {
        max-width: 280px;
        height: 150px; 
    }
</style>
</head>
<body>

<div class="watermark"></div>

<div class="content">
<div class="header">
    <table width="100%">
        <tr>
            <td width="65%" style="vertical-align: top;">
                <img src="' . $logoBase64 . '" alt="Syed Professional Services Logo" class="logo" />
            </td>
            <td width="35%" style="vertical-align: top; text-align: right;">
                <div style="font-weight: bold; font-size: 18px;">INVOICE #' . $invoice_id . '</div>
                <div style="margin-top: 10px;">
                    <strong>Status:</strong> ' . htmlspecialchars($inv['status_text'] ?? '—') . '<br>
                    <strong>By:</strong> ' . $employeeInitials . '<br>';

if (trim($lastEditedBy) !== '' && $lastEditedBy !== '—') {
    $html .= '<strong>Edited By:</strong> ' . $lastEditedBy . '<br>';
}
$html .= '
                    <strong>Invoice Date:</strong> ' . $invDate . '<br>
                    <strong>Due Date:</strong> ' . $dueDate . '<br>
                    <strong>Paid Date:</strong> ' . htmlspecialchars($realPaidDate) . '<br>
                    <strong>Service Provider:</strong> ' . $spFormatted . '
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <table width="100%">
        <tr>
            <td width="50%" style="vertical-align: top;">
                <div class="section-title">FROM:</div>
                <div>
                    <strong>Syed Professional Services</strong><br>
                    102 Coney Island Ave<br>
                    Brooklyn, NY 11230<br>
                    Email: info@syedpro.com<br>
                    Phone: (718) 859-6392<br>
                    Fax: (718) 859-2237
                </div>
            </td>
            <td width="50%" style="vertical-align: top;  text-align: right;">
                <div class="section-title">TO:</div>
                <div>
                    <strong>' . htmlspecialchars($clientName) . '</strong><br>
                    ' . $clientAddress . '<br>
                    Email: ' . $clientEmail . '<br>
                    Phone: ' . $clientPhone . '
                </div>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <table>
        <thead>
            <tr>
                <th width="60%">SERVICES DESCRIPTION</th>
                <th width="20%" style="text-align: right;">PRICE</th>
                <th width="20%" style="text-align: right;">TOTAL</th>
            </tr>
        </thead>
        <tbody>' . $itemsHtml . '
        <tr>
            <td colspan="2" style="text-align: right; padding: 8px; border-top: 1px solid #ddd;">Subtotal:</td>
            <td style="text-align: right; padding: 8px; border-top: 1px solid #ddd;">$' . number_format((float) $inv['total_amount'] + (float) $inv['discount'], 2) . '</td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: right; padding: 8px;">Discount:</td>
            <td style="text-align: right; padding: 8px;">$' . number_format((float) $inv['discount'], 2) . '</td>
        </tr>
        <tr class="total-row">
            <td colspan="2" style="text-align: right; padding: 8px; border-top: 2px solid #000;"><strong>Total:</strong></td>
            <td style="text-align: right; padding: 8px; border-top: 2px solid #000;"><strong>$' . number_format((float) $inv['total_amount'], 2) . '</strong></td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: right; padding: 8px;">Paid:</td>
            <td style="text-align: right; padding: 8px;">$' . number_format((float) $inv['paid_amount'], 2) . '</td>
        </tr>
        <tr class="total-row">
            <td colspan="2" style="text-align: right; padding: 8px; border-top: 2px solid #000;"><strong>Balance:</strong></td>
            <td style="text-align: right; padding: 8px; border-top: 2px solid #000;"><strong>$' . number_format((float) $inv['balance'], 2) . '</strong></td>
        </tr>
        </tbody>
    </table>
</div>

<div class="clear"></div>

<div class="section">
    <div class="section-title">PAYMENT HISTORY</div>
    <table style="border: 1px solid #000;">
    <thead>
    <tr>
    <th style="border: 1px solid #000; padding: 8px; background: #f0f0f0; color: black; font-size: 12px;">DATE</th>
    <th style="border: 1px solid #000; padding: 8px; background: #f0f0f0; color: black; font-size: 12px;">TOTAL AMOUNT</th>
    <th style="border: 1px solid #000; padding: 8px; background: #f0f0f0; color: black; font-size: 12px;">AMOUNT PAID</th>
    <th style="border: 1px solid #000; padding: 8px; background: #f0f0f0; color: black; font-size: 12px;">BALANCE</th>
    <th style="border: 1px solid #000; padding: 8px; background: #f0f0f0; color: black; font-size: 12px;">METHOD</th>
    <th style="border: 1px solid #000; padding: 8px; background: #f0f0f0; color: black; font-size: 12px;">PAYMENT ADDED</th>
    <th style="border: 1px solid #000; padding: 8px; background: #f0f0f0; color: black; font-size: 12px;">PAYMENT DESC.</th>
</tr>
</thead>
        <tbody>' . $historyHtml . '</tbody>
    </table>
</div>

<div class="footer">
    <div>In the event of a returned or unsuccessful payment, a bank fee of $25 will be applied.</div>
    <div style="margin-top: 10px; font-weight: bold;">Thank you for choosing Syed Professional Services</div>
</div>
</div>
</body>
</html>
';

/* ---------- 7. GENERATE PDF ---------- */
$dompdf = new Dompdf([
    'enable_remote' => true,  // Changed to true to allow loading images
    'defaultFont' => 'Helvetica',
    'isHtml5ParserEnabled' => true,
    'isPhpEnabled' => false
]);

$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html);
$dompdf->render();

// Save the PDF file
file_put_contents($pdfFile, $dompdf->output());

// Output the PDF to browser
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Invoice-' . $invoice_id . '.pdf"');
echo $dompdf->output();
exit;