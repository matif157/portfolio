<div class="row justify-content-center"><div class="col-lg-7">
<div class="card"><div class="card-header">Add Service</div><div class="card-body">
<form method="POST" action="<?= url('/services') ?>">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" required></div>
    <div class="row g-2">
        <div class="col-6"><label class="form-label">Code</label><input type="text" name="service_code" class="form-control"></div>
        <div class="col-6"><label class="form-label">Category</label>
            <select name="category_id" class="form-select">
                <option value="">—</option>
                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="row g-2 mt-1">
        <div class="col-4"><label class="form-label">Default Price</label><input type="number" step="0.01" name="default_price" class="form-control" value="0"></div>
        <div class="col-4"><label class="form-label">Billing Type</label>
            <select name="billing_type" class="form-select">
                <option value="one_time">One-Time</option>
                <option value="recurring">Recurring</option>
                <option value="deposit">Deposit</option>
                <option value="hourly">Hourly</option>
            </select>
        </div>
        <div class="col-4"><label class="form-label">Tax Rate %</label><input type="number" step="0.01" name="tax_rate" class="form-control" value="0"></div>
    </div>
    <div class="mb-3 mt-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    <button class="btn btn-primary">Save</button>
    <a href="<?= url('/services') ?>" class="btn btn-secondary">Cancel</a>
</form>
</div></div></div></div>
