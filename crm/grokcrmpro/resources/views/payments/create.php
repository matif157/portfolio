<div class="card"><div class="card-header">Record Payment</div><div class="card-body">
<form method="POST" action="<?= url('/payments') ?>">
<?= csrf_field() ?>
<div class="row g-3 mb-3">
    <div class="col-md-5">
        <label class="form-label">Client *</label>
        <select name="client_id" class="form-select" required onchange="location.href='<?= url('/payments/create') ?>?client_id='+this.value">
            <option value="">Select...</option>
            <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $preClient==$c['id']?'selected':'' ?>><?= e($c['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">Amount *</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
    <div class="col-md-2"><label class="form-label">Date</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
    <div class="col-md-2"><label class="form-label">Method</label>
        <select name="method" class="form-select">
            <option value="cash">Cash</option><option value="check">Check</option>
            <option value="bank_transfer">Bank Transfer</option><option value="card">Card</option>
            <option value="ach">ACH</option><option value="online">Online</option><option value="other">Other</option>
        </select>
    </div>
</div>
<?php if (!empty($openInvoices)): ?>
<h6>Allocate to Invoices</h6>
<table class="table table-sm">
<thead><tr><th>Invoice</th><th>Balance</th><th>Allocate</th></tr></thead>
<tbody>
<?php foreach ($openInvoices as $oi): ?>
<tr>
    <td><?= e($oi['invoice_number']) ?></td>
    <td><?= money($oi['balance_due']) ?></td>
    <td><input type="number" step="0.01" name="alloc[<?= $oi['id'] ?>]" class="form-control form-control-sm" value="<?= $preInvoice==$oi['id'] ? $oi['balance_due'] : '0' ?>" style="width:120px" max="<?= $oi['balance_due'] ?>"></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php elseif ($preClient): ?>
<p class="text-muted">No open invoices for this client. Payment will be recorded without allocation.</p>
<?php endif; ?>
<?php if ($preInvoice): ?><input type="hidden" name="invoice_id" value="<?= $preInvoice ?>"><?php endif; ?>
<div class="mb-3"><label class="form-label">Reference</label><input type="text" name="reference_number" class="form-control"></div>
<div class="mb-3"><label class="form-label">Note</label><textarea name="note" class="form-control" rows="2"></textarea></div>
<button class="btn btn-success">Record Payment</button>
<a href="<?= url('/payments') ?>" class="btn btn-secondary">Cancel</a>
</form>
</div></div>
