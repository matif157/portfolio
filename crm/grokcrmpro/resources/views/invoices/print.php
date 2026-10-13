<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= e($invoice['invoice_number']) ?></title>
<style>
body{font-family:Arial,sans-serif;font-size:13px;color:#222;padding:30px 40px;margin:0}
.header{display:flex;justify-content:space-between;border-bottom:2px solid #c9a227;padding-bottom:15px;margin-bottom:20px}
.logo{font-weight:700;font-size:16px;color:#1e4d8c}
.logo span{display:block;font-size:11px;color:#c9a227;font-weight:600}
.meta{text-align:right;font-size:12px;line-height:1.6}
.stamp{display:inline-block;border:2px solid #dc3545;color:#dc3545;font-weight:700;font-size:11px;padding:2px 10px;margin-top:6px;letter-spacing:1px}
.addr{display:flex;justify-content:space-between;margin-bottom:20px;font-size:12px}
table{width:100%;border-collapse:collapse;margin:15px 0}
th{background:#8B7355;color:#fff;padding:10px 12px;text-align:left}
th:last-child,td:last-child{text-align:right}
td{padding:10px 12px;border-bottom:1px solid #e5e5e5}
.totals{float:right;width:240px}
.totals td{padding:4px 0;border:none}
.grand td{font-weight:700;font-size:15px;border-top:2px solid #333;padding-top:8px}
.footer{clear:both;margin-top:50px;text-align:center;font-size:12px;color:#555;border-top:1px solid #ddd;padding-top:15px;font-style:italic}
@media print{.no-print{display:none}}
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:15px;text-align:right">
    <button onclick="window.print()" style="padding:8px 20px;background:#0d6efd;color:#fff;border:none;border-radius:4px;cursor:pointer">Print / Save PDF</button>
</div>
<div class="header">
    <div class="logo"><?= e(strtoupper(explode(' ', $company['name'])[0] ?? 'SYED')) ?><span>PROFESSIONAL SERVICES</span></div>
    <div class="meta">
        <div><strong>Invoice #:</strong> <?= e($invoice['invoice_number']) ?></div>
        <div><strong>Created:</strong> <?= e($invoice['issue_date']) ?></div>
        <div><strong>Due:</strong> <?= e($invoice['due_date'] ?? '-') ?></div>
        <div class="stamp"><?= strtoupper(e($invoice['status'])) ?></div>
    </div>
</div>
<div class="addr">
    <div style="width:48%">
        <div style="color:#666;font-size:11px;text-transform:uppercase;margin-bottom:4px">From:</div>
        <strong><?= e($company['name']) ?></strong><br>
        <?= e($company['street']) ?><br>
        <?= e($company['city']) ?>, <?= e($company['state']) ?> <?= e($company['zip']) ?><br>
        <?= e($company['phone']) ?><br><?= e($company['email']) ?>
    </div>
    <div style="width:48%;text-align:right">
        <div style="color:#666;font-size:11px;text-transform:uppercase;margin-bottom:4px">To:</div>
        <strong><?= e($invoice['bill_to_name']) ?></strong><br>
        <?= e($invoice['bill_to_company'] ?? '') ?><br>
        <span style="white-space:pre-line"><?= e($invoice['bill_to_address'] ?? '') ?></span><br>
        <?= e($invoice['bill_to_phone'] ?? '') ?><br><?= e($invoice['bill_to_email'] ?? '') ?>
    </div>
</div>
<table>
<thead><tr><th>Service</th><th>Price</th></tr></thead>
<tbody>
<?php foreach ($items as $it): ?>
<tr><td><?= e($it['description']) ?></td><td><?= money($it['line_total']) ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
<div class="totals">
<table>
<tr><td>Subtotal</td><td><?= money($invoice['subtotal']) ?></td></tr>
<?php if ($invoice['discount_total']>0): ?><tr><td>Discount</td><td>-<?= money($invoice['discount_total']) ?></td></tr><?php endif; ?>
<tr class="grand"><td>Grand Total</td><td><?= money($invoice['grand_total']) ?></td></tr>
<tr><td>Paid</td><td><?= money($invoice['amount_paid']) ?></td></tr>
<tr><td>Balance</td><td><?= money($invoice['balance_due']) ?></td></tr>
</table>
</div>
<div class="footer">Thank you for choosing <?= e($company['name']) ?></div>
</body>
</html>
