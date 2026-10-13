<?php
// =============================================
// FILE NAME        : view_invoice.php
// PURPOSE          : Professional Invoice View (matching original PDF sample)
// =============================================
// =============================================
// ACTIVITY LOG - Invoice Viewed
// =============================================
 require_once 'log_in.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
// Safe version - only basic columns

// URL se invoice id le rahe hain
$id = $_GET['id'] ?? 0;
 // Log activity
log_activity($conn, 'Invoice', 'Viewed', $id);
$sql = "SELECT i.*, 
               c.full_name, 
               c.email,  
               c.phone,
               c.address
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.client_id
        WHERE i.id = ?";
// Invoice + Client data ek saath le rahe hain (JOIN use kiya)

$stmt = $conn->prepare($sql);
$stmt->execute([$id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inv) {
    echo "<h2>Invoice not found</h2>";
    exit();
}

// Invoice items (services) le rahe hain
$itemsStmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Payment history le rahe hain
$payStmt = $conn->prepare("SELECT * FROM invoice_payments WHERE invoice_id = ? ORDER BY payment_date DESC");
$payStmt->execute([$id]);
$payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

// Total paid calculate kar rahe hain
$totalPaid = 0;
foreach ($payments as $p) {
    $totalPaid += $p['amount'];
}
$balance = $inv['grand_total'] - $totalPaid;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?= htmlspecialchars($inv['invoice_number']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background:#f1f5f9; margin:0; padding:30px; }
        .invoice-box {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
        }
        .unpaid-stamp {
            position: absolute;
            top: 80px;
            right: 40px;
            border: 3px solid #ef4444;
            color: #ef4444;
            font-weight: bold;
            font-size: 22px;
            padding: 8px 18px;
            transform: rotate(-12deg);
            border-radius: 6px;
        }
        .header { display:flex; justify-content:space-between; margin-bottom:30px; }
        .logo { font-size:22px; font-weight:bold; color:#1e2937; }
        .meta { text-align:right; font-size:14px; }
        .from-to { display:flex; justify-content:space-between; margin:30px 0; }
        .from-to div { width:45%; }
        table { width:100%; border-collapse:collapse; margin:25px 0; }
        th { background:#8b6914; color:white; padding:12px; text-align:left; }
        td { padding:12px; border-bottom:1px solid #e2e8f0; }
        .totals { text-align:right; margin-top:20px; }
        .actions { margin-top:40px; display:flex; gap:12px; flex-wrap:wrap; }
        .btn { padding:12px 22px; border-radius:8px; text-decoration:none; color:white; font-weight:600; }
    </style>
</head>
<body>

<div class="invoice-box">
    <?php if ($inv['status'] == 'Unpaid'): ?>
        <div class="unpaid-stamp">UNPAID</div>
    <?php endif; ?>

    <div class="header">
        <div class="logo">
            Syed Professional Services<br>
            <small style="font-size:13px; color:#64748b;">1012 Coney Island Ave, Brooklyn, NY 11230</small>
        </div>
        <div class="meta">
            <strong>Invoice #:</strong> <?= htmlspecialchars($inv['invoice_number']) ?><br>
            <strong>By:</strong> <?= htmlspecialchars($inv['created_by']) ?><br>
            <strong>Created:</strong> <?= htmlspecialchars($inv['created_date']) ?><br>
            <strong>Due:</strong> <?= htmlspecialchars($inv['due_date']) ?>
        </div>
    </div>

    <div class="from-to">
        <div>
            <strong>From:</strong><br>
            Syed Professional Services<br>
            1012 Coney Island Ave<br>
            Brooklyn, NY 11230<br>
            Phone: (718) 859-5392<br>
            Fax: (718) 859-2237<br>
            invoice@syedpro.com
        </div>
        <div>
         
    <strong>To:</strong><br>
    <?= htmlspecialchars($inv['business_name'] ?? $inv['full_name'] ?? 'Client') ?><br>
    <?= htmlspecialchars($inv['business_street'] ?? $inv['street'] ?? $inv['address'] ?? '') ?><br>
    <?= htmlspecialchars(
        ($inv['business_city'] ?? $inv['city'] ?? '') . ', ' . 
        ($inv['business_state'] ?? $inv['state'] ?? '') . ' ' . 
        ($inv['business_zip'] ?? $inv['zip'] ?? '')
    ) ?><br>
    <?= htmlspecialchars($inv['phone_cell'] ?? $inv['phone'] ?? '') ?><br>
    <?= htmlspecialchars($inv['email_primary'] ?? $inv['email'] ?? '') ?>
</div>
    </div>
<a href="mailto:<?= htmlspecialchars($inv['email_primary'] ?? $inv['email'] ?? '') ?>?subject=Invoice%20%23<?= $inv['invoice_number'] ?>&body=Dear%20Client,%0A%0APlease%20find%20your%20invoice%20details.%0A%0AThank%20you." 
   class="btn" style="background:#3b82f6;">
   Email to Client
</a><button onclick="window.print()" class="btn" style="background:#8b5cf6; border:none; cursor:pointer;">
    📄 Download / Print PDF
</button>
    <table>
        <thead>
            <tr>
                <th>Service</th>
                <th style="text-align:right;">Price</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <strong>Service Provider:</strong> <?= htmlspecialchars($item['service_provider']) ?><br>
                    <?= htmlspecialchars($item['service_name']) ?>
                </td>
                <td style="text-align:right;">$<?= number_format($item['price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <p>Sub Total: <strong>$<?= number_format($inv['sub_total'], 2) ?></strong></p>
        <p style="font-size:18px;">Grand Total: <strong>$<?= number_format($inv['grand_total'], 2) ?></strong></p>
        <p>Total Paid: $<?= number_format($totalPaid, 2) ?></p>
        <p style="color:<?= $balance > 0 ? '#ef4444' : '#059669' ?>;">
            Balance: <strong>$<?= number_format($balance, 2) ?></strong>
        </p>
    </div>

    <p style="margin-top:30px;"><strong>Zelle Payment Info:</strong> <?= htmlspecialchars($inv['zelle_info']) ?></p>
    <p style="font-size:13px; color:#64748b;">
        In the event of a returned or unsuccessful payment, a bank fee of $25 will be applied.<br>
        We accept the payment via Zelle, Credit or debit Card, Cash and cheque.
    </p>
    <p style="text-align:center; margin-top:30px; font-weight:600;">Thank you for choosing Syed Professional Services</p>

    <?php if (count($payments) > 0): ?>
        <h3 style="margin-top:40px; border-top:1px solid #e2e8f0; padding-top:20px;">Payment History</h3>
        <table>
            <thead>
                <tr style="background:#f1f5f9;">
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['payment_date']) ?></td>
                    <td>$<?= number_format($p['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($p['payment_method']) ?></td>
                    <td><?= htmlspecialchars($p['notes']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="actions">
        <a href="edit_invoice.php?id=<?= $id ?>" class="btn" style="background:#eab308;">Edit Invoice</a>
        <a href="add_payment.php?invoice_id=<?= $id ?>" class="btn" style="background:#10b981;">+ Add Payment</a>
        <a href="mailto:<?= htmlspecialchars($inv['email_primary'] ?: $inv['email']) ?>?subject=Invoice%20%23<?= $inv['invoice_number'] ?>&body=Dear%20Client,%0A%0APlease%20find%20your%20invoice%20details.%0A%0AThank%20you." class="btn" style="background:#3b82f6;">Email to Client</a>
        <a href="invoices.php" class="btn" style="background:#64748b;">Back to List</a>
    </div>
</div>

</body>
</html>