<div class="row justify-content-center"><div class="col-lg-7">
<div class="card"><div class="card-header">Edit Service</div><div class="card-body">
<form method="POST" action="<?= url('/services/'.$service['id']) ?>">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?= e($service['name']) ?>" required></div>
    <div class="row g-2">
        <div class="col-6"><label class="form-label">Code</label><input type="text" name="service_code" class="form-control" value="<?= e($service['service_code'] ?? '') ?>"></div>
        <div class="col-6"><label class="form-label">Category</label>
            <select name="category_id" class="form-select">
                <option value="">—</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $service['category_id']==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="row g-2 mt-1">
        <div class="col-4"><label class="form-label">Default Price</label><input type="number" step="0.01" name="default_price" class="form-control" value="<?= e((string)$service['default_price']) ?>"></div>
        <div class="col-4"><label class="form-label">Billing Type</label>
            <select name="billing_type" class="form-select">
                <?php foreach (['one_time','recurring','deposit','hourly'] as $bt): ?>
                <option value="<?= $bt ?>" <?= $service['billing_type']===$bt?'selected':'' ?>><?= e($bt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-4"><label class="form-label">Tax Rate %</label><input type="number" step="0.01" name="tax_rate" class="form-control" value="<?= e((string)$service['tax_rate']) ?>"></div>
    </div>
    <div class="mb-3 mt-2"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= e($service['description'] ?? '') ?></textarea></div>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="is_active" id="act" <?= $service['is_active']?'checked':'' ?>>
        <label class="form-check-label" for="act">Active</label>
    </div>
    <button class="btn btn-primary">Update</button>
    <a href="<?= url('/services') ?>" class="btn btn-secondary">Cancel</a>
</form>
</div></div></div></div>
