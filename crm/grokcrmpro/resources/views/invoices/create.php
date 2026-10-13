<div class="card"><div class="card-header">New Invoice</div><div class="card-body">
<form method="POST" action="<?= url('/invoices') ?>">
<?= csrf_field() ?>
<div class="row g-3 mb-3">
    <div class="col-md-5">
        <label class="form-label">Client *</label>
        <select name="client_id" class="form-select" required>
            <option value="">Select...</option>
            <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $preClient==$c['id']?'selected':'' ?>><?= e($c['full_name']) ?><?= $c['company_name']?' — '.e($c['company_name']):'' ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">Issue Date</label><input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
    <div class="col-md-3"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime("+{$dueDays} days")) ?>"></div>
</div>
<h6>Line Items</h6>
<table class="table table-sm line-items">
<thead><tr><th>Service</th><th>Description</th><th>Qty</th><th>Price</th><th></th></tr></thead>
<tbody>
<tr>
    <td><select name="service_id[]" class="form-select form-select-sm" onchange="if(this.selectedOptions[0].dataset.price){this.closest('tr').querySelector('.price').value=this.selectedOptions[0].dataset.price;this.closest('tr').querySelector('.desc').value=this.selectedOptions[0].text}">
        <option value="">—</option>
        <?php foreach ($services as $s): ?><option value="<?= $s['id'] ?>" data-price="<?= $s['default_price'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
    </select></td>
    <td><input type="text" name="description[]" class="form-control form-control-sm desc" required></td>
    <td><input type="number" step="0.01" name="quantity[]" class="form-control form-control-sm qty" value="1" style="width:80px"></td>
    <td><input type="number" step="0.01" name="unit_price[]" class="form-control form-control-sm price" value="0" style="width:100px"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger remove-line">&times;</button></td>
</tr>
</tbody>
</table>
<button type="button" class="btn btn-sm btn-outline-secondary add-line mb-3">+ Add Line</button>
<div class="row g-2 mb-3">
    <div class="col-md-3"><label class="form-label">Discount</label><input type="number" step="0.01" name="discount_total" class="form-control" value="0"></div>
    <div class="col-md-3"><label class="form-label">Tax</label><input type="number" step="0.01" name="tax_total" class="form-control" value="0"></div>
</div>
<div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
<button class="btn btn-primary">Create Invoice</button>
<a href="<?= url('/invoices') ?>" class="btn btn-secondary">Cancel</a>
</form>
</div></div>
