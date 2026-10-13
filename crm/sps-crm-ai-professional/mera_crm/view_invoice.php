<?php
// =============================================
// FILE: view_invoice.php
// PURPOSE: View single invoice + payment history
// =============================================

require_once 'log_in.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

$sql = "SELECT i.*, c.full_name, c.client_id as client_code, c.email, c.phone, c.address
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE i.id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice not found.");
}

$items = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$items->execute([$id]);
$items = $items->fetchAll();

$payments = $conn->prepare("SELECT * FROM invoice_payments WHERE invoice_id = ? ORDER BY payment_date DESC");
$payments->execute([$id]);
$payments = $payments->fetchAll();

log_activity($conn, 'Invoice', 'Viewed', $id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= show($invoice['invoice_number']) ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .invoice-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.06);
            max-width: 900px;
        }
        .status-stamp {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 1px;
        }
        .stamp-unpaid { background: #fee2e2; color: #991b1b; }
        .stamp-partial { background: #fef3c7; color: #92400e; }
        .stamp-paid { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><?= SITE_NAME ?></div>
    <ul>
        <li><a href="dashboard.php">🏠 <span>Dashboard</span></a></li>
        <li><a href="clients.php">👥 <span>Clients</span></a></li>
        <li class="active"><a href="invoices.php">📄 <span>Invoices</span></a></li>
        <li><a href="activity_logs.php">📜 <span>Activity Logs</span></a></li>
        <li><a href="users.php">👤 <span>Users</span></a></li>
        <li><a href="logout.php">🚪 <span>Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <div class="flex justify-between items-center mb-20">
        <h1>Invoice <?= show($invoice['invoice_number']) ?></h1>
        <div class="flex gap-10">
            <a href="add_payment.php?invoice_id=<?= $id ?>" class="btn btn-success">+ Add Payment</a>
            <a href="mailto:<?= show($invoice['email']) ?>?subject=Invoice%20%23<?= urlencode($invoice['invoice_number']) ?>" class="btn btn-primary">Email Client</a>
            <a href="invoices.php" class="btn btn-secondary">Back</a>
        </div>
    </div>

    <div class="invoice-box">
        <!-- Header -->
        <div class="flex justify-between" style="margin-bottom:30px;">
            <div>
                <h2 style="margin:0 0 6px 0;"><?= SITE_NAME ?></h2>
                <p class="text-muted" style="font-size:13px; line-height:1.6;">
                    <?= SITE_ADDRESS ?><br>
                    Phone: <?= SITE_PHONE ?><br>
                    <?= SITE_EMAIL ?>
                </p>
            </div>
            <div style="text-align:right;">
                <?php
                $stampClass = 'stamp-unpaid';
                if ($invoice['status'] === 'Paid') $stampClass = 'stamp-paid';
                if ($invoice['status'] === 'Partial') $stampClass = 'stamp-partial';
                ?>
                <div class="status-stamp <?= $stampClass ?>"><?= strtoupper($invoice['status']) ?></div>
                <p style="margin-top:12px; font-size:14px;">
                    <strong>Invoice #:</strong> <?= show($invoice['invoice_number']) ?><br>
                    <strong>Created:</strong> <?= date('d M Y', strtotime($invoice['created_date'])) ?><br>
                    <strong>Due:</strong> <?= $invoice['due_date'] ? date('d M Y', strtotime($invoice['due_date'])) : '—' ?>
                </p>
            </div>
        </div>

        <!-- Bill To -->
        <div style="margin-bottom:30px; padding:18px; background:#f8fafc; border-radius:10px;">
            <strong style="color:#64748b; font-size:12px; text-transform:uppercase;">Bill To</strong>
            <p style="margin:6px 0 0 0; font-size:16px; font-weight:600;"><?= show($invoice['full_name']) ?></p>
            <p class="text-muted" style="font-size:13px;">
                <?= show($invoice['client_code']) ?><br>
                <?= show($invoice['email']) ?><br>
                <?= show($invoice['phone']) ?>
            </p>
        </div>

        <!-- Items -->
        <table style="margin-bottom:25px;">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                    <th>Provider</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= show($item['service_name']) ?></td>
                    <td><?= number_format((float)$item['quantity'], 2) ?></td>
                    <td>$<?= number_format((float)$item['unit_price'], 2) ?></td>
                    <td>$<?= number_format((float)$item['total'], 2) ?></td>
                    <td><?= show($item['service_provider']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div style="text-align:right; max-width:280px; margin-left:auto;">
            <p>Sub Total: <strong>$<?= number_format((float)$invoice['sub_total'], 2) ?></strong></p>
            <p>Grand Total: <strong>$<?= number_format((float)$invoice['grand_total'], 2) ?></strong></p>
            <p>Total Paid: <strong style="color:#10b981;">$<?= number_format((float)$invoice['total_paid'], 2) ?></strong></p>
            <p style="font-size:18px; margin-top:8px;">Balance: <strong style="color:#ef4444;">$<?= number_format((float)$invoice['balance'], 2) ?></strong></p>
        </div>

        <div style="margin-top:30px; padding-top:20px; border-top:1px solid #e2e8f0; font-size:13px; color:#64748b;">
            <p><strong>Zelle Payment Info:</strong> info@syedpro.com</p>
            <p>In the event of a returned or unsuccessful payment, a bank fee of $25 will be applied.</p>
            <p>We accept payment via Zelle, Credit/Debit Card, Cash and Cheque.</p>
            <p style="margin-top:10px;">Thank you for choosing Syed Professional Services</p>
        </div>
    </div>

    <!-- Payment History -->
    <div class="card" style="margin-top:30px;">
        <h3 style="margin-bottom:15px;">Payment History</h3>
        <?php if (count($payments) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                        <td>$<?= number_format((float)$p['amount'], 2) ?></td>
                        <td><?= show($p['payment_method']) ?></td>
                        <td><?= show($p['reference_no']) ?></td>
                        <td><?= show($p['notes']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">No payments recorded yet.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
