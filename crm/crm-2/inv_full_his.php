<?php
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;
require_once 'includes/report_utils.php';
include 'includes/db.php';

$client_id = (int) ($_GET['client_id'] ?? 0);
if (!$client_id)
    die('Client ID missing');

$client = mysqli_fetch_assoc(mysqli_query($con, "
    SELECT name, lname, bus_name, dba, hstreet, hcity, hstate, hzip, cell_ph, prm_email
    FROM members WHERE member_id = $client_id"));

$clientName = $client['bus_name'] ?: $client['dba'] ?: trim($client['name'] . ' ' . $client['lname']);
$clientAddr = trim($client['hstreet'] . ', ' . $client['hcity'] . ', ' . $client['hstate'] . ' ' . $client['hzip'], ', ');
$clientPhone = $client['cell_ph'];
$clientEmail = $client['prm_email'];

$rowsHtml = '';
$result = mysqli_query($con, "
    SELECT  bi.invoice_id,
            bi.invoice_date,
            bi.due_date,
            bi.paid_date,
            bi.total_amount,
            bi.balance,
            bi.invoice_status,
            bi.service_provider_id,
            bi.filelink,
            bi.paid_amount,
            u.user_name AS sp_name,
            
            GROUP_CONCAT(DISTINCT bii.item_description SEPARATOR '<br>') AS services,
            GROUP_CONCAT(DISTINCT bii.item_sub_description SEPARATOR '<br>') AS descriptions,
            bph.paid,
            bph.payment_method,
            bph.employee,
            bph.detail,
            bph.date AS payment_date
    FROM billing_invoice bi
    LEFT JOIN billing_payment_history bph ON bph.inv_id = bi.invoice_id
    LEFT JOIN users u ON u.user_id = bi.service_provider_id
    LEFT JOIN billing_invoice_items bii ON bii.inv_id = bi.invoice_id
    WHERE bi.mem_id = $client_id
      AND bi.invoice_status IN (2,3)        
    GROUP BY bi.invoice_id
    ORDER BY bi.invoice_date DESC, bi.invoice_id DESC");

$totalBalance = 0;
$unpaidBalance = 0;
$rowCount = 0;
 
while ($r = mysqli_fetch_assoc($result)) {
    $totalBalance += $r['paid_amount'];
    $unpaidBalance += $r['total_amount'];
    $rowCount++;

    $rowClass = $rowCount % 2 == 0 ? 'even' : 'odd';

    $spDisplay = 'N/A';
    if ($r['sp_name']) {
        $tmp = explode(' ', $r['sp_name']);
        $spInitials = strtoupper(substr($tmp[0], 0, 1) . (isset($tmp[1]) ? substr($tmp[1], 0, 1) : ''));
        $spDisplay = $spInitials . '(' . ($r['service_provider_id'] ?: '') . ')';
    }



    $rowsHtml .= '
    <tr class="' . $rowClass . '">
        <td class="invoice-id">#' . ($r['invoice_id'] ?? '') . '</td>
        <td>' . (!empty($r['due_date']) ? date('m/d/Y', strtotime($r['due_date'])) : '') . '</td>
        <td>' . (!empty($r['paid_date']) ? date('m/d/Y', strtotime($r['paid_date'])) : '') . '</td>
        <td class="details">' . htmlspecialchars($r['detail'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>
        <td class="services">' . ($r['services'] ?? '') . '</td>
        <td class="descriptions">' . ($r['descriptions'] ?? '') . '</td>
        <td class="created-by">' . $spDisplay . '</td>
        <td class="amount">$' . number_format($r['total_amount'] ?? 0, 2) . '</td>
        <td class="amount paid">$' . number_format($r['paid_amount'] ?? 0, 2) . '</td>
        <td class="amount unpaid">$' . number_format($r['balance'] ?? 0, 2) . '</td>
             <td class="details">' . htmlspecialchars($r['payment_method'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td>
        <td>
    
  <a href="' . report_invoice_view_url($r['filelink']) . '"
     target="_blank"
     class="view-btn">VIEW FILE</a>
</td>
        <td class="service-provider">' . $spDisplay . '</td>
    </tr>';
}
/* ----------  build watermark data-URI ---------- */
$watermarkFile = __DIR__ . '/storage/uploads/logo/logo-darks.jpeg';
$watermarkSrc = '';
if (file_exists($watermarkFile)) {
    $watermarkSrc = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($watermarkFile));
}
$logoSrc = report_logo_src('storage/uploads/logo/logo-dark.png');
$dCardSrc = report_logo_src('storage/uploads/logo/d.png');
$curDate = date('F j, Y');
$US_Date = date('m-d-Y');

$content = '
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Paid Invoice History - ' . htmlspecialchars($clientName) . '</title>
<style>
@page { margin: 30px 25px; }
body { font-family: "Helvetica Neue", Arial, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 0; line-height: 1.4; }
.container { max-width: 100%; }


.header-table { width: 100%; margin-bottom: 15px; }
.header-table td { vertical-align: top; }
.logo-cell { width: 200px; }
.logo-box { display: inline-block; }
.logo-icon { width: 50px; height: 50px; background: #1e3a5f; border-radius: 8px; display: inline-block; vertical-align: middle; text-align: center; line-height: 50px; color: white; font-weight: bold; font-size: 18px; }
.logo-text { display: inline-block; vertical-align: middle; margin-left: 8px; }
.logo-text .brand { color: #1e3a5f; font-weight: 700; font-size: 14px; }
.logo-text .sub { color: #1e3a5f; font-size: 10px; font-weight: 600; }
.title-cell { text-align: right; }
.title-badge { display: inline-block; background: #38a169; color: white; padding: 8px 20px; border-radius: 4px; font-size: 16px; font-weight: 700; }
.meta-info { font-size: 10px; color: #666; margin-top: 5px; }


.info-section { width: 100%; background: #e8eaf06f; border-radius: 8px; margin-bottom: 20px; position: relative; overflow: hidden; }
.info-section td { width: 50%; padding: 20px 25px; vertical-align: top; }
.section-label { font-weight: 700; color: #1a1a1a; margin-bottom: 10px; font-size: 12px; }
.company-name, .client-name { font-weight: 600; font-size: 12px; margin-bottom: 3px; }
.info-line { color: #4a5568; font-size: 10px; line-height: 1.6; }

 
.summary-section { width: 100%; margin-bottom: 20px; }
.summary-cards { text-align: center; }
.summary-card { 
    display: inline-block; 
    background: url("' . $dCardSrc . '") no-repeat center center;
    background-size: cover;
    border-radius: 10px; 
    padding: 35px 30px; 
    margin-top: 50px;
    margin-right: 15px;
    margin-left: 15px;  
    min-width: 150px; 
    text-align: center;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.19), 0 6px 6px rgba(0, 0, 0, 0.23), 0 0 20px rgba(30, 58, 95, 0.2);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    transform: translateY(0);
}
.summary-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.4) 0%, rgba(255, 255, 255, 0.2) 100%);
    z-index: 1;
}
.summary-card::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, rgba(0, 0, 0, 0.1) 0%, transparent 50%, rgba(0, 0, 0, 0.1) 100%);
    z-index: 1;
}
.summary-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 14px 28px rgba(0, 0, 0, 0.25), 0 10px 10px rgba(0, 0, 0, 0.22), 0 0 30px rgba(30, 58, 95, 0.3);
}
.summary-card.unpaid { border-color: #c53030; }
.summary-card.total { border-color: #666; }
.card-content {
    position: relative;
    z-index: 2;
}
.card-icon { 
    display: block; 
    margin: 0 auto 8px; 
    width: 30px; 
    height: 30px;
    filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.2));
}
.summary-label { font-size: 9px; font-weight: 600;  text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8); }
.summary-card.total .summary-label { color: #666; }
.summary-value { font-size: 24px; font-weight: 700; text-shadow: 0 1px 3px rgba(255, 255, 255, 0.8); }
.summary-value.green { color: #38a169; }
.summary-value.red { color: #1e3a5f; }
.summary-value.totalamount { color: red; }


.payments-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.payments-table thead tr { background: #4a4a6a; }
.payments-table th { padding: 10px 6px; text-align: left; color: white; font-weight: 600; font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px; border: none; }
.payments-table td { padding: 12px 6px; border-bottom: 1px solid #e2e8f0; font-size: 9px; vertical-align: middle; }
.payments-table tr.even { background-color: #f0f2f5; }
.payments-table tr.odd { background-color: white; }
.invoice-id { font-weight: 600; color: #1e3a5f; }
.amount { font-weight: 600; }
.amount.unpaid { color: #c53030; }
.amount.paid { color: #38a169; }
.created-by { text-align: center; }
.service-provider { text-align: center; font-weight: 500; }
.details { max-width: 80px; word-wrap: break-word; }
.services { max-width: 60px; word-wrap: break-word; font-weight: 500; }
.descriptions { max-width: 140px; word-wrap: break-word; font-size: 8px; color: #4a5568; }
.view-btn { display: inline-block; background: #d4a017; color: white; padding: 4px 10px; border-radius: 3px; font-size: 8px; font-weight: 600; }
.watermark {
    position: fixed;
    top:   0;
    left: 10%;
    width: 100%; 
    height: 100%;
    text-align: center;
    opacity: 0.2;         
    z-index: 0;         
}
.watermark img {
    width: 80%;
    height: 60%;            
    margin-top: 10%;
}
.footer { margin-top: 20px; text-align: center; color: #666; font-size: 9px; }
</style>
</head>
<body>
<div class="container">
<div class="watermark"><img src="' . $watermarkSrc . '" /></div>
<table class="header-table">
<tr>
<td class="logo-cell">
    <div class="logo-box">
        <img src="' . $logoSrc . '" alt="" />
    </div>
</td>
<td class="title-cell">
    <div class="title-badge">Paid Invoice History</div>
    <div class="meta-info">
        Generated: ' . $curDate . '<br>
        Client ID: ' . $client_id . '
    </div>
</td>
</tr>
</table>

<table class="info-section">
<tr>
<td>
    
    <div class="section-label">FROM :</div>
    <div class="company-name">Syed Professional Services</div>
    <div class="info-line">
        1012 Coney Island Ave,<br>
        Brooklyn, NY 11230<br>
        Email: info@syedpro.com<br>
        Phone: (718) 859-5392<br>
        Fax: (718) 859-2237
    </div>
</td>
<td style="padding-left:250px;">
    <div class="section-label">CLIENT INFORMATION</div>
    <div class="client-name">' . htmlspecialchars($clientName) . '</div>
    <div class="info-line">
        ' . htmlspecialchars($clientAddr) . '<br>
        ' . ($clientPhone ? 'Phone: ' . htmlspecialchars($clientPhone) . '<br>' : '') . '
        ' . ($clientEmail ? 'Email: ' . htmlspecialchars($clientEmail) : '') . '
    </div>
</td>
</tr>
</table>

<div class="summary-section">
<div class="summary-cards">
    <div class="summary-card">
        <div class="card-content">
            <svg class="card-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#1e3a5f">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1.81.45 1.61 1.67 1.61 1.16 0 1.6-.64 1.6-1.46 0-.84-.68-1.22-1.88-1.56-1.77-.49-3.42-1.17-3.42-3.27 0-1.77 1.32-2.86 2.94-3.21V5h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.63-1.63-1.63-1.01 0-1.46.54-1.46 1.34 0 .74.49 1.11 1.78 1.46 1.81.49 3.52 1.15 3.52 3.37 0 1.82-1.33 2.96-3.1 3.21z"/>
            </svg>
            <div class="summary-label" style="color:#666;">TOTAL PAID</div>
            <div class="summary-value green">$' . number_format($totalBalance, 2) . '</div>
        </div>
    </div>
    <div class="summary-card unpaid" style="color:#666;">
        <div class="card-content">
            <svg class="card-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#c53030">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1.81.45 1.61 1.67 1.61 1.16 0 1.6-.64 1.6-1.46 0-.84-.68-1.22-1.88-1.56-1.77-.49-3.42-1.17-3.42-3.27 0-1.77 1.32-2.86 2.94-3.21V5h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.63-1.63-1.63-1.01 0-1.46.54-1.46 1.34 0 .74.49 1.11 1.78 1.46 1.81.49 3.52 1.15 3.52 3.37 0 1.82-1.33 2.96-3.1 3.21z"/>
            </svg>
            <div class="summary-label">TOTAL BALANCE</div>
            <div class="summary-value red">$' . number_format($unpaidBalance, 2) . '</div>
        </div>
    </div>
    <div class="summary-card total">
        <div class="card-content">
            <svg class="card-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#666">
                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
            </svg>
            <div class="summary-label" style="color:#666;">TOTAL TRANSCTIONS</div>
            <div class="summary-value totalamount">' . $rowCount . '</div>
        </div>
    </div>
</div>
</div>

<table class="payments-table">
<thead>
<tr>
    <th>Invoice</th>
    <th>Due Date</th>
    <th>Paid Date</th>
    <th>Invoice Description</th>
    <th>Services</th>
    <th>Service Description</th>
    <th>Added By</th>
    <th>Total Amount</th>
    <th>Paid Amount</th>
    <th>Balance</th>
    <th>Payment Method</th>
    <th>View Invoice</th>
    <th>Service Provider</th>
</tr>
</thead>
<tbody>
' . $rowsHtml . '
</tbody>
</table>

</div>
</body>
</html>';

$dompdf = new Dompdf([
    'enable_remote' => false,
    'defaultFont' => 'Helvetica',
    'isHtml5ParserEnabled' => true,
    'isPhpEnabled' => true,
]);

$dompdf->loadHtml($content);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$fileName = "Unpaid_Invoice_History_" . preg_replace('/[^a-zA-Z0-9]/', '_', $clientName) . "_" . $US_Date . ".pdf";
$dompdf->stream($fileName, ["Attachment" => false]);
?>