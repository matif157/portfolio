<?php
$statusColors = ['draft'=>'#6c757d','sent'=>'#0d6efd','partially_paid'=>'#fd7e14','paid'=>'#198754','overdue'=>'#dc3545','cancelled'=>'#6c757d'];
$sc = $statusColors[$invoice['status']] ?? '#6c757d';
?>
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
    <h4 style="color:#fff" class="mb-0"><?= e($invoice['invoice_number']) ?>
        <span class="badge bg-secondary ms-2"><?= e($invoice['status']) ?></span>
    </h4>
    <div class="d-flex gap-2">
        <?php if (has_permission('payment.create') && $invoice['balance_due'] > 0): ?>
        <a href="<?= url('/payments/create?client_id='.$invoice['client_id'].'&invoice_id='.$invoice['id']) ?>" class="btn btn-success btn-sm"><i class="bi bi-cash"></i> Record Payment</a>
        <?php endif; ?>
        <a href="<?= url('/invoices/'.$invoice['id'].'/print') ?>" class="btn btn-outline-primary btn-sm" target="_blank"><i class="bi bi-printer"></i> Print / PDF</a>
        <a href="<?= url('/invoices') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="invoice-sheet">
            <div class="inv-head">
                <div>
                    <div style="font-weight:700;font-size:15px;color:#1e4d8c"><?= e(strtoupper(explode(' ', $company['name'])[0] ?? 'SYED')) ?></div>
                    <div style="font-size:11px;color:#c9a227;font-weight:600">PROFESSIONAL SERVICES</div>
                </div>
                <div style="text-align:right;font-size:12px;line-height:1.6">
                    <div><strong>Invoice #:</strong> <?= e($invoice['invoice_number']) ?></div>
                    <div><strong>Created:</strong> <?= e($invoice['issue_date']) ?></div>
                    <div><strong>Due:</strong> <?= e($invoice['due_date'] ?? '-') ?></div>
                    <div class="status-stamp" style="border-color:<?= $sc ?>;color:<?= $sc ?>"><?= strtoupper(e($invoice['status'])) ?></div>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:1rem;font-size:12px">
                <div style="width:48%">
                    <div style="color:#666;font-size:11px;text-transform:uppercase">From:</div>
                    <div style="font-weight:700"><?= e($company['name']) ?></div>
                    <div><?= e($company['street']) ?></div>
                    <div><?= e($company['city']) ?>, <?= e($company['state']) ?> <?= e($company['zip']) ?></div>
                    <div><?= e($company['phone']) ?></div>
                    <div><?= e($company['email']) ?></div>
                </div>
                <div style="width:48%;text-align:right">
                    <div style="color:#666;font-size:11px;text-transform:uppercase">To:</div>
                    <div style="font-weight:700"><?= e($invoice['bill_to_name']) ?></div>
                    <?php if ($invoice['bill_to_company']): ?><div><?= e($invoice['bill_to_company']) ?></div><?php endif; ?>
                    <div style="white-space:pre-line"><?= e($invoice['bill_to_address'] ?? '') ?></div>
                    <div><?= e($invoice['bill_to_phone'] ?? '') ?></div>
                    <div><?= e($invoice['bill_to_email'] ?? '') ?></div>
                </div>
            </div>
            <table>
                <thead><tr><th>Service</th><th>Price</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e($it['description']) ?></td>
                        <td><?= money($it['line_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="float:right;width:220px;font-size:13px">
                <div style="display:flex;justify-content:space-between"><span>Subtotal</span><strong><?= money($invoice['subtotal']) ?></strong></div>
                <div style="display:flex;justify-content:space-between;border-top:2px solid #333;padding-top:6px;margin-top:6px;font-size:15px"><span>Grand Total</span><strong><?= money($invoice['grand_total']) ?></strong></div>
                <div style="display:flex;justify-content:space-between"><span>Paid</span><strong><?= money($invoice['amount_paid']) ?></strong></div>
                <div style="display:flex;justify-content:space-between"><span>Balance</span><strong><?= money($invoice['balance_due']) ?></strong></div>
            </div>
            <div style="clear:both;margin-top:2.5rem;text-align:center;font-size:12px;color:#555;border-top:1px solid #ddd;padding-top:12px;font-style:italic">
                Thank you for choosing <?= e($company['name']) ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Summary</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Total</span><strong><?= money($invoice['grand_total']) ?></strong></div>
                <div class="d-flex justify-content-between mb-1"><span class="text-muted">Paid</span><strong class="text-success"><?= money($invoice['amount_paid']) ?></strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Balance</span><strong class="<?= $invoice['balance_due']>0?'text-danger':'' ?>"><?= money($invoice['balance_due']) ?></strong></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Payments Allocated</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Payment</th><th>Date</th><th>Amount</th></tr></thead>
                    <tbody>
                    <?php if (empty($allocations)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">None</td></tr>
                    <?php else: foreach ($allocations as $a): ?>
                        <tr>
                            <td><a href="<?= url('/payments/'.$a['payment_id']) ?>"><?= e($a['payment_number']) ?></a></td>
                            <td><?= e($a['payment_date']) ?></td>
                            <td class="text-success"><?= money($a['amount']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
