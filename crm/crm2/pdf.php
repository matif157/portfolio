<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

include 'includes/db.php';

$invoice_id = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : 0;
if ($invoice_id <= 0) {
    die('Invoice ID missing');
}

/* ---------- FETCH INVOICE DATA ---------- */
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
    die('Invoice not found');
}

$clientName = $inv['bus_name'] ?: $inv['dba'] ?: trim($inv['name'] . ' ' . $inv['lname']);
$clientStreet = htmlspecialchars($inv['hstreet'] ?? '');
$clientCityStateZip = htmlspecialchars(trim($inv['hcity'] . ', ' . $inv['hstate'] . ' ' . $inv['hzip']));
$clientEmail = htmlspecialchars($inv['prm_email'] ?? '');
$clientPhone = htmlspecialchars($inv['cell_ph'] ?? '');

$invDate = date('m/d/y', strtotime($inv['invoice_date']));
$dueDate = date('m/d/y', strtotime($inv['due_date']));

// ---------- Format as Initials(ID) only ----------
$spId = $inv['user_id'] ?? '';

if ($spId && !empty($inv['user_name'])) {
    $nameParts = explode(' ', trim($inv['user_name']));
    $initials = '';
    foreach ($nameParts as $part) {
        if (!empty($part)) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }
    $spFormatted = $initials . '(' . $spId . ')';
} else {
    $spFormatted = '—';
}

$employeeInitials = !empty($inv['employee_initials']) ? htmlspecialchars($inv['employee_initials']) : '—';

// Last edited by from billing_invoice
$lastEditedBy = !empty($inv['last_edited_by']) ? htmlspecialchars($inv['last_edited_by']) : '—';

/* ---------- ITEMS ---------- */
$itemsHtml = '';
$res = mysqli_query($con, "SELECT item_description, item_sub_description, unit_price, total_price 
FROM billing_invoice_items WHERE inv_id = $invoice_id ORDER BY inv_item_id");
while ($row = mysqli_fetch_assoc($res)) {
    $desc = htmlspecialchars($row['item_description']);
    $sub = htmlspecialchars($row['item_sub_description']);
    $subLine = $sub ? $sub : '';
    $itemsHtml .= '
        <tr>
            <td class="service-row-text">
                <div class="bold">' . $desc . '</div>
                ' . $subLine . '
            </td>
            <td class="price">$' . number_format((float) $row['unit_price'], 2) . '</td>
            <td class="total">$' . number_format((float) $row['total_price'], 2) . '</td>
        </tr>';
}
if ($itemsHtml === '') {
    $itemsHtml = '<tr><td colspan="3" style="text-align:center; padding:10px; color:#999;">No services listed.</td></tr>';
}

/* ---------- PAYMENT HISTORY ---------- */
$historyHtml = '';
$res = mysqli_query($con, "
    SELECT DATE_FORMAT(date,'%c/%e/%Y') AS pd, paid, balance, detail, employee, payment_method
    FROM billing_payment_history
    WHERE inv_id = $invoice_id
    ORDER BY date DESC, his_id DESC
");
if (mysqli_num_rows($res)) {
    while ($row = mysqli_fetch_assoc($res)) {
        $historyHtml .= '
            <tr>
                <td>' . $row['pd'] . '</td>
                <td>$' . number_format((float) $row['paid'], 2) . '</td>
                <td>$' . number_format((float) $row['balance'], 2) . '</td>
                <td>' . htmlspecialchars($row['payment_method'] ?: '—') . '</td>
                <td>' . htmlspecialchars($row['employee'] ?: '—') . '</td>
                <td>' . htmlspecialchars($row['detail'] ?: '—') . '</td>
            </tr>';
    }
} else {
    $historyHtml = '<tr><td colspan="6" style="text-align:center; padding:10px; color:#999;">No payment history available.</td></tr>';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Invoice #<?php echo $invoice_id; ?> - Syed Professional Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f0f0f0;
            padding: 20px 0;
            font-family: "Inter", Arial, Helvetica, sans-serif;
        }

        .invoice-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .watermark {
            position: absolute;
            top: 35%;
            left: 60%;
            transform: translate(-50%, -30%);
            width: 180mm;
            height: 180mm;
            background-image: url("storage/uploads/logo/logo-darks.jpeg");
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
            padding: 18mm 15mm;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10mm;
            padding-bottom: 6mm;
            border-bottom: 4px solid #003366;
        }

        .logo {
            width: 65mm;
            height: auto;
        }

        .invoice-details {
            text-align: right;
            font-size: 15.916px;
            line-height: 26px;
            color: #3f3948;
            font-weight: 400;
        }

        .invoice-details strong {
            font-weight: 700;
        }

        .invoice-details p {
            margin: 0;
        }

        .addresses {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12mm;
        }

        .from,
        .to {
            width: 48%;
            font-size: 15.916px;
            line-height: 21px;
            color: #3f3948;
            font-weight: 400;
        }

        .from {
            text-align: left;
        }

        .to {
            text-align: right;
        }

        .from h3,
        .to h3 {
            font-size: 12pt;
            font-weight: bold;
            color: #003366;
            margin-bottom: 3mm;
        }

        .service-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10mm;
        }

        .service-table th {
            background-color: #003366;
            color: #ffffff;
            padding: 4mm 3mm;
            text-align: left;
            font-size: 16.7363px;
            line-height: 23px;
            font-weight: 700;
        }

        .service-row-text {
            font-size: 12px;
            line-height: 16px;
            color: #3f3948;
            font-weight: 400;
        }

        .service-row-text .bold {
            font-weight: 700;
        }

        .service-table td {
            padding: 1mm 2mm;
            border-bottom: 1px solid #aaa;
            vertical-align: top;
        }

        .service-table .price,
        .service-table .total {
            font-weight: bold;
            color: #000;
            /* text-align: right; */
        }

        .totals {
            float: right;
            width: 100mm;
            text-align: right;
            font-size: 16px;
            color: #000000;
            border-top: 2px double #000;
        }

        .totals p {
            display: flex;
            justify-content: space-around;
            padding: 1mm 0;
        }

        .totals .label {
            font-weight: 400;
        }

        .totals .value {
            font-weight: 700;
        }

        .payment-history {
            margin-top: 15mm;
            margin-bottom: 10mm;
        }

        .payment-history h3 {
            font-size: 20px;
            line-height: 16px;
            color: #000000;
            font-weight: 700;
            margin-bottom: 4mm;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            border: 1px solid #000;
        }

        .payment-table th {
            /* background-color: #f0f0f0; */
            padding: 3mm;
            text-align: left;
            border-bottom: 2px solid #aaa;
            font-weight: 600;
            border: 1px solid #000;
        }

        .payment-table td {
            padding: 2.5mm 3mm;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
            line-height: 16px;
            color: #000000;
            font-weight: 400;
            border: 1px solid #000;
        }

        .note,
        .thanks {
            text-align: center;
            margin-left: auto;
            margin-right: auto;
            max-width: 100%;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .invoice-container {
                margin: 0;
                box-shadow: none;
                width: 100%;
            }

            @page {
                margin: 0;
            }
        }
    </style>
</head>

<body>

    <div class="invoice-container">
        <div class="watermark"></div>

        <div class="content">
            <div class="header">
                <img src="storage/uploads/logo/logo-dark.png" alt="Syed Professional Services Logo" class="logo" />
                <div class="invoice-details">
                    <p><strong>Invoice No :</strong> #<?php echo $invoice_id; ?></p>
                    <p><strong>By :</strong> <?php echo $employeeInitials; ?></p>
                    <?php
                    $edited = trim($inv['last_edited_by'] ?? '');
                    if ($edited !== '' && $edited !== '—'): ?>
                        <p><strong>Edited By :</strong> <?php echo htmlspecialchars($edited); ?></p>
                    <?php endif; ?>
                    <p><strong>Invoice Date :</strong> <?php echo $invDate; ?></p>
                    <p><strong>Due Date :</strong> <?php echo $dueDate; ?></p>
                    <p><strong>Service Provider:</strong> <?php echo $spFormatted; ?></p>
                </div>
            </div>

            <div class="addresses">
                <div class="from">
                    <h3>FROM :</h3>
                    <div>Syed Professional Services</div>
                    <div>102 Coney Island Ave,</div>
                    <div>Brooklyn, NY 11230</div>
                    <div>Email: info@syedpro.com</div>
                    <div>Phone: (718) 859-6392</div>
                    <div>Fax: (718) 859-2237</div>
                </div>
                <div class="to">
                    <h3>TO :</h3>
                    <div><?php echo htmlspecialchars($clientName); ?></div>
                    <div><?php echo $clientStreet; ?></div>
                    <div><?php echo $clientCityStateZip; ?></div>
                    <div><?php echo $clientEmail; ?></div>
                    <div><?php echo $clientPhone; ?></div>
                </div>
            </div>

            <table class="service-table">
                <thead>
                    <tr>
                        <th>SERVICE DESCRIPTION</th>
                        <th>PRICE</th>
                        <th>TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $itemsHtml; ?>
                </tbody>
            </table>

            <div class="totals">
                <p>
                    <span class="label">Sub-total :</span>
                    <span
                        class="value">$<?php echo number_format((float) $inv['total_amount'] + (float) $inv['discount'], 2); ?></span>
                </p>
                <p>
                    <span class="label">Discount :</span>
                    <span class="value">$<?php echo number_format((float) $inv['discount'], 2); ?></span>
                </p>
            </div>
            <div class="totals">
                <p>
                    <span class="label"><strong>Total :</strong></span>
                    <span class="value">$<?php echo number_format((float) $inv['total_amount'], 2); ?></span>
                </p>
                <p>
                    <span class="label"><strong>Paid :</strong></span>
                    <span class="value">$<?php echo number_format((float) $inv['paid_amount'], 2); ?></span>
                </p>
                <p>
                    <span class="label"><strong>Balance :</strong></span>
                    <span class="value">$<?php echo number_format((float) $inv['balance'], 2); ?></span>
                </p>
            </div>
            <div style="clear: both"></div>

            <div class="payment-history">
                <h3>Payment History</h3>
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>DATE</th>
                            <th>AMOUNT PAID</th>
                            <th>BALANCE</th>
                            <th>METHOD</th>
                            <th>PAYMENT ADDED</th>
                            <th>PAYMENT DESC.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo $historyHtml; ?>
                    </tbody>
                </table>
            </div>

            <div class="note">
                In the event of a returned or unsuccessful payment, a bank fee of $25 will be applied.
            </div>

            <div class="thanks">
                Thank you for choosing Syed Professional Services
            </div>
        </div>
    </div>
</body>

</html>
