<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Receipt <?= e($payment['payment_number']) ?></title>
<style>body{font-family:Arial;padding:40px;color:#222}h1{font-size:20px}.box{border:1px solid #ddd;padding:20px;max-width:500px;margin:0 auto}@media print{.no-print{display:none}}</style>
</head><body>
<div class="no-print" style="text-align:right;margin-bottom:15px"><button onclick="window.print()">Print</button></div>
<div class="box">
<h1>Payment Receipt</h1>
<p><strong><?= e($company['name']) ?></strong><br><?= e($company['street']) ?><br><?= e($company['phone']) ?></p>
<hr>
<p>Receipt #: <strong><?= e($payment['payment_number']) ?></strong><br>
Date: <?= e($payment['payment_date']) ?><br>
Client: <?= e($payment['client_name']) ?><br>
Amount: <strong><?= money($payment['amount']) ?></strong><br>
Method: <?= e($payment['method']) ?></p>
<?php if ($allocations): ?>
<p>Applied to:</p>
<ul><?php foreach ($allocations as $a): ?><li><?= e($a['invoice_number']) ?> — <?= money($a['amount']) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<p style="margin-top:30px;font-style:italic;text-align:center">Thank you for your payment</p>
</div></body></html>
