<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_login();
$pdo=db();
if(!table_exists($pdo,'invoices')) redirect('upgrade_phase5.php');

$id=(int)($_GET['id']??0);
$st=$pdo->prepare("SELECT i.*,CONCAT(c.first_name,' ',c.last_name) client_name,c.email client_email,c.phone client_phone FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.id=?");
$st->execute([$id]);$invoice=$st->fetch();
if(!$invoice){http_response_code(404);die('Invoice not found.');}
$st=$pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id");$st->execute([$id]);$items=$st->fetchAll();
$balance=(float)$invoice['total_amount']-(float)$invoice['paid_amount'];
$shell_title='Invoice '.$invoice['invoice_number'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($invoice['invoice_number'])?> · SPS CRM</title>
<link rel="stylesheet" href="assets/css/future-theme.css"><link rel="stylesheet" href="assets/css/sps-shell.css"><link rel="stylesheet" href="assets/css/invoice-print.css"><script defer src="assets/js/future-ui.js"></script><script defer src="assets/js/sps-shell.js"></script>
<style>
.invoice-view{padding:22px}.invoice-actions-bar{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap}.invoice-paper{max-width:980px;margin:0 auto;background:#fff;color:#152033;border-radius:4px;box-shadow:0 25px 70px rgba(0,0,0,.28);padding:52px}.invoice-head{display:flex;justify-content:space-between;gap:30px;border-bottom:2px solid #e7edf3;padding-bottom:26px}.invoice-brand{font-size:25px;font-weight:900;color:#101b2b}.invoice-micro{font-size:11px;color:#718099;letter-spacing:.14em;text-transform:uppercase}.invoice-meta{text-align:right}.invoice-meta strong{font-size:20px}.invoice-to{display:grid;grid-template-columns:1fr 1fr;gap:30px;padding:30px 0}.invoice-label{font-size:10px;text-transform:uppercase;letter-spacing:.14em;color:#8591a2;margin-bottom:7px}.invoice-paper table{width:100%;border-collapse:collapse}.invoice-paper th,.invoice-paper td{padding:12px 8px;border-bottom:1px solid #e8edf2;text-align:left}.invoice-paper th{font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:#8190a1}.money{text-align:right!important}.invoice-summary{margin-left:auto;width:min(360px,100%);margin-top:25px}.invoice-summary div{display:flex;justify-content:space-between;padding:8px}.invoice-summary .grand{font-size:20px;font-weight:900;border-top:2px solid #1d2d41;margin-top:5px;padding-top:14px}.invoice-note{margin-top:35px;padding-top:18px;border-top:1px solid #e7edf3;color:#657388;font-size:12px;line-height:1.6}.status-chip{display:inline-block;padding:5px 9px;border-radius:99px;background:#edf4ff;color:#315ee7;font-weight:800;font-size:10px;text-transform:uppercase;letter-spacing:.08em}@media(max-width:700px){.invoice-paper{padding:25px}.invoice-head,.invoice-to{grid-template-columns:1fr;display:grid}.invoice-meta{text-align:left}.invoice-paper{overflow:auto}.invoice-paper table{min-width:650px}}@media print{body{background:#fff!important;padding-left:0!important}.sps-sidebar,.sps-shell-topbar,.invoice-actions-bar{display:none!important}.invoice-view{padding:0}.invoice-paper{box-shadow:none;max-width:none;border-radius:0;padding:20mm}.invoice-paper *{color:#152033!important}}
</style></head>
<body class="module-invoice-view sps-shell-page"><?php include __DIR__.'/sidebar.php'; ?><div class="sps-shell-main"><?php include __DIR__.'/shell_topbar.php'; ?><main class="invoice-view">
<div class="invoice-actions-bar"><div><h1 style="margin:0">Invoice <?=e($invoice['invoice_number'])?></h1><div class="muted">Professional printable template</div></div><div style="display:flex;gap:8px"><a class="btn secondary" href="invoices.php">← Invoices</a><a class="btn" href="payments.php?invoice_id=<?=$invoice['id']?>">Record Payment</a><button class="btn" onclick="window.print()">Print / Save PDF</button></div></div>
<section class="invoice-paper">
<div class="invoice-head"><div><div class="invoice-brand">SYED PROFESSIONAL SERVICES</div><div class="invoice-micro">Professional Services · SPS CRM</div></div><div class="invoice-meta"><div class="invoice-micro">Invoice</div><strong><?=e($invoice['invoice_number'])?></strong><div style="margin-top:7px"><span class="status-chip"><?=e($invoice['status'])?></span></div></div></div>
<div class="invoice-to"><div><div class="invoice-label">Bill To</div><strong><?=e($invoice['client_name'])?></strong><div><?=e($invoice['client_email']?:'')?></div><div><?=e($invoice['client_phone']?:'')?></div></div><div><div class="invoice-label">Invoice Details</div><div><strong>Issue:</strong> <?=e($invoice['issue_date'])?></div><div><strong>Due:</strong> <?=e($invoice['due_date']?:'—')?></div></div></div>
<table><thead><tr><th>Description</th><th class="money">Qty</th><th class="money">Unit Price</th><th class="money">Line Total</th></tr></thead><tbody>
<?php foreach($items as $item):?><tr><td><?=e($item['description'])?></td><td class="money"><?=money($item['quantity'])?></td><td class="money">$<?=money($item['unit_price'])?></td><td class="money">$<?=money($item['line_total'])?></td></tr><?php endforeach;?></tbody></table>
<div class="invoice-summary"><div><span>Subtotal</span><strong>$<?=money($invoice['subtotal'])?></strong></div><div><span>Tax</span><strong>$<?=money($invoice['tax_amount'])?></strong></div><div><span>Paid</span><strong>$<?=money($invoice['paid_amount'])?></strong></div><div class="grand"><span>Balance Due</span><strong>$<?=money($balance)?></strong></div></div>
<?php if(trim((string)$invoice['notes'])!==''):?><div class="invoice-note"><strong>Notes</strong><br><?=nl2br(e($invoice['notes']))?></div><?php endif;?>
<div class="invoice-note">Thank you for choosing Syed Professional Services. This invoice was generated by SPS CRM.</div>
</section></main></div></body></html>
