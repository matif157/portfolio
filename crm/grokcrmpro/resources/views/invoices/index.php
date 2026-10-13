<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2" method="GET">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search..." value="<?= e($q) ?>" style="min-width:180px">
        <select name="status" class="form-select form-select-sm" style="width:140px">
            <option value="">All Status</option>
            <?php foreach (['draft','sent','partially_paid','paid','overdue','cancelled'] as $st): ?>
            <option value="<?= $st ?>" <?= $status===$st?'selected':'' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary">Filter</button>
    </form>
    <?php if (has_permission('invoice.create')): ?>
    <a href="<?= url('/invoices/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Invoice</a>
    <?php endif; ?>
</div>
<div class="card"><div class="card-body p-0">
<table class="table table-hover table-sm mb-0">
<thead><tr><th>Number</th><th>Client</th><th>Date</th><th>Due</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php if (empty($invoices)): ?>
<tr><td colspan="9" class="text-center text-muted py-4">No invoices</td></tr>
<?php else: foreach ($invoices as $inv): ?>
<tr>
    <td><a href="<?= url('/invoices/'.$inv['id']) ?>"><?= e($inv['invoice_number']) ?></a></td>
    <td><?= e($inv['client_name'] ?? '-') ?></td>
    <td><?= e($inv['issue_date']) ?></td>
    <td><?= e($inv['due_date'] ?? '-') ?></td>
    <td><?= money($inv['grand_total']) ?></td>
    <td><?= money($inv['amount_paid']) ?></td>
    <td class="<?= $inv['balance_due']>0?'text-danger fw-bold':'' ?>"><?= money($inv['balance_due']) ?></td>
    <td><span class="badge bg-secondary"><?= e($inv['status']) ?></span></td>
    <td><a href="<?= url('/invoices/'.$inv['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div></div>
