<?php
/**
 * Invoice Print / PDF Template
 * Matches Syed Professional Services invoice layout
 */
require_once __DIR__ . '/../includes/log_in.php';
require_once __DIR__ . '/../includes/settings_helper.php';
require_permission('Invoice', 'print');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT i.*, m.member_name, m.bus_name, m.prm_email, m.phone, m.cell_ph,
                              m.address, m.city, m.state, m.zip
                       FROM invoices i
                       LEFT JOIN members m ON i.mem_id = m.id
                       WHERE i.id = ?");
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) {
    die('Invoice not found');
}

$items = $pdo->prepare("SELECT * FROM invoice_items WHERE inv_id = ? ORDER BY id");
$items->execute([$id]);
$items = $items->fetchAll();

$company = get_company_info();
$statusLabel = invoice_status_label((int)$inv['invoice_status']);
$statusColor = invoice_status_color((int)$inv['invoice_status']);

// Staff initials
$staff = $currentUser['full_name'] ?? '';
$initials = '';
foreach (explode(' ', $staff) as $w) {
    if ($w !== '') $initials .= strtoupper($w[0]);
}
$userId = $_SESSION['user_id'] ?? '';

// Bill-to name
$toName = $inv['member_name'] ?: ($inv['guest_name'] ?? 'Client');
$toBus  = $inv['bus_name'] ?: ($inv['guest_business'] ?? '');
$toEmail = $inv['prm_email'] ?: ($inv['guest_email'] ?? '');
$toPhone = $inv['cell_ph'] ?: ($inv['phone'] ?: ($inv['guest_phone'] ?? ''));
$toAddr = trim(($inv['address'] ?? '') ?: ($inv['guest_address'] ?? ''));
$toCity = $inv['city'] ?: ($inv['guest_city'] ?? '');
$toState = $inv['state'] ?: ($inv['guest_state'] ?? '');
$toZip = $inv['zip'] ?: ($inv['guest_zip'] ?? '');

$prefix = get_setting('invoice_prefix', 'INV-');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?= $inv['id'] ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            color: #222;
            background: #fff;
            padding: 30px 40px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            border-bottom: 2px solid #c9a227;
            padding-bottom: 15px;
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .logo-circle {
            width: 55px; height: 55px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e4d8c, #0d6efd);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 18px;
        }
        .logo-text {
            font-size: 16px;
            font-weight: 700;
            color: #1e4d8c;
            line-height: 1.2;
        }
        .logo-text span { color: #c9a227; font-size: 12px; font-weight: 600; display: block; }
        .meta {
            text-align: right;
            font-size: 12px;
            line-height: 1.6;
        }
        .status-badge {
            display: inline-block;
            border: 2px solid <?= $statusColor ?>;
            color: <?= $statusColor ?>;
            font-weight: 700;
            font-size: 11px;
            padding: 2px 10px;
            margin-top: 6px;
            letter-spacing: 1px;
        }
        .addresses {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        .addr-block { width: 48%; }
        .addr-block h4 {
            font-size: 12px;
            color: #666;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .addr-block .name { font-weight: 700; font-size: 14px; margin-bottom: 3px; }
        .addr-block div { line-height: 1.5; font-size: 12px; }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.items thead th {
            background: #8B7355;
            color: #fff;
            padding: 10px 12px;
            text-align: left;
            font-size: 13px;
        }
        table.items thead th:last-child { text-align: right; }
        table.items tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: top;
        }
        table.items tbody td:last-child { text-align: right; }
        table.items .sub-desc {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
        }
        .totals {
            float: right;
            width: 240px;
            margin-top: 5px;
        }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 5px 0; font-size: 13px; }
        .totals td:last-child { text-align: right; font-weight: 600; }
        .totals .grand td {
            font-size: 15px;
            font-weight: 700;
            border-top: 2px solid #333;
            padding-top: 8px;
        }
        .description-box {
            clear: both;
            margin-top: 40px;
            padding-top: 10px;
        }
        .description-box h4 { font-size: 13px; margin-bottom: 6px; color: #444; }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #555;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        .footer u { font-style: italic; }
        .no-print { margin-bottom: 15px; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 10px 20px; }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align:right">
    <button onclick="window.print()" style="padding:8px 20px;background:#0d6efd;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px">
        🖨 Print / Save as PDF
    </button>
    <a href="view.php?id=<?= $id ?>" style="margin-left:10px;font-size:13px">← Back to Invoice</a>
</div>

<div class="header">
    <div class="logo-area">
        <div class="logo-circle">SP</div>
        <div class="logo-text">
            <?= e(strtoupper(explode(' ', $company['name'])[0] ?? 'SYED')) ?><br>
            <span>PROFESSIONAL SERVICES</span>
        </div>
    </div>
    <div class="meta">
        <div><strong>Invoice #:</strong> <?= $inv['id'] ?></div>
        <div><strong>By:</strong> <?= e($initials) ?> (<?= $userId ?>)</div>
        <div><strong>Created:</strong> <?= $inv['invoice_date'] ? date('m/d/Y', strtotime($inv['invoice_date'])) : '-' ?></div>
        <div><strong>Due:</strong> <?= $inv['due_date'] ? date('m/d/Y', strtotime($inv['due_date'])) : '-' ?></div>
        <div class="status-badge"><?= e($statusLabel) ?></div>
    </div>
</div>

<div class="addresses">
    <div class="addr-block">
        <h4>From:</h4>
        <div class="name"><?= e($company['name']) ?></div>
        <div><?= e($company['street']) ?></div>
        <div><?= e($company['city']) ?>, <?= e($company['state']) ?> <?= e($company['zip']) ?></div>
        <div><?= e($company['phone']) ?></div>
        <?php if ($company['fax']): ?><div><?= e($company['fax']) ?></div><?php endif; ?>
        <div><?= e($company['email']) ?></div>
    </div>
    <div class="addr-block" style="text-align:right">
        <h4>To:</h4>
        <div class="name"><?= e($toName) ?></div>
        <?php if ($toBus): ?><div><?= e($toBus) ?></div><?php endif; ?>
        <?php if ($toAddr): ?><div><?= e($toAddr) ?></div><?php endif; ?>
        <?php if ($toCity || $toState || $toZip): ?>
            <div><?= e(trim("$toCity, $toState $toZip", ', ')) ?></div>
        <?php endif; ?>
        <?php if ($toPhone): ?><div><?= e($toPhone) ?></div><?php endif; ?>
        <?php if ($toEmail): ?><div><?= e($toEmail) ?></div><?php endif; ?>
    </div>
</div>

<table class="items">
    <thead>
        <tr>
            <th style="width:75%">Service</th>
            <th style="width:25%">Price</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="2" style="text-align:center;color:#999">No line items</td></tr>
        <?php else: foreach ($items as $it): ?>
            <tr>
                <td>
                    <?= e($it['item_description']) ?>
                    <?php if (!empty($it['item_sub_description'])): ?>
                        <div class="sub-desc"><?= e($it['item_sub_description']) ?></div>
                    <?php endif; ?>
                    <?php if ((float)$it['quantity'] != 1): ?>
                        <div class="sub-desc">Qty: <?= $it['quantity'] ?> × <?= money($it['unit_price']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= money($it['total_price']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<div class="totals">
    <table>
        <tr>
            <td>Sub Total:</td>
            <td><?= money($inv['total_amount'] + $inv['discount']) ?></td>
        </tr>
        <?php if ((float)$inv['discount'] > 0): ?>
        <tr>
            <td>Discount:</td>
            <td>-<?= money($inv['discount']) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ((float)$inv['paid_amount'] > 0): ?>
        <tr>
            <td>Paid:</td>
            <td><?= money($inv['paid_amount']) ?></td>
        </tr>
        <tr>
            <td>Balance:</td>
            <td><?= money($inv['balance']) ?></td>
        </tr>
        <?php endif; ?>
        <tr class="grand">
            <td>Grand Total:</td>
            <td><?= money($inv['total_amount']) ?></td>
        </tr>
    </table>
</div>

<div class="description-box">
    <h4>Description:</h4>
    <div style="min-height:40px;color:#555">
        <?= e($inv['payment_details'] ?? '') ?>
    </div>
</div>

<div class="footer">
    <u>Thank you for choosing <?= e($company['name']) ?></u>
</div>

</body>
</html>
